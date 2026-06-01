# Chunked Import Processing — Implementation Report

## 1. Root cause summary

Large imports blocked the HTTP request because `ImportBatchService::confirmMappingAndProcess()` called `ProcessImportBatchJob::dispatchSync()`, which parsed the entire file, validated every row, and ran duplicate detection inside one database transaction before the browser received a response. That pattern caused long page freezes, risk of HTTP timeouts, no visibility into progress, and all-or-nothing failure behavior when the job struggled mid-file.

## 2. New architecture

```
Upload → Mapping wizard → Confirm mapping (HTTP returns immediately)
    → ProcessImportBatchJob (orchestrator, queued)
        → Validate headers / count rows
        → Create import_chunks records
        → Dispatch ProcessImportChunkJob × N (queued, one chunk each)
            → parseRowRange(start, end)
            → createImportRow per row (row-level try/catch)
            → detectForChunk (intra-batch + cross-batch duplicates)
            → update chunk + batch progress counters
    → When all chunks terminal → detectForBatch (reconcile) → finalizeBatchFromChunks
    → User polls GET /imports/{id}/progress every ~4s
```

Chunking runs **only after** mapping is confirmed. The Smart Column Mapping wizard is unchanged.

## 3. Files modified

| Area | Files |
|------|--------|
| Migration | `database/migrations/2026_05_31_200001_create_import_chunks_table.php` |
| Models | `app/Models/ImportChunk.php`, `app/Models/ImportBatch.php` |
| Enums | `app/Enums/ImportChunkStatus.php`, `app/Enums/ImportBatchStatus.php` (+ `queued`, `processing`) |
| Config | `config/import.php` (`chunk_size`) |
| Parser | `app/Services/Import/ImportParserService.php` (`parseRowRange`, `countDataRows`) |
| Services | `ImportBatchService`, `ImportChunkService`, `ImportProgressService`, `DuplicateDetectionService`, `ImportPipelineService` |
| Jobs | `ProcessImportBatchJob` (orchestrator), `ProcessImportChunkJob` (new) |
| HTTP | `ImportBatchController`, `routes/web.php` |
| UI | `imports/show.blade.php`, `import-status-badge`, `import-processing-progress` (new component) |
| Tests | `tests/Feature/ImportChunkProcessingTest.php` |

## 4. New table

**`import_chunks`**: `import_batch_id`, `chunk_number`, `start_row`, `end_row`, `status`, progress counters (`total_rows`, `processed_rows`, `valid_rows`, `warning_rows`, `invalid_rows`, `duplicate_rows`, `failed_rows`), `error_message`, `started_at`, `completed_at`.

Statuses: `pending`, `processing`, `completed`, `failed`.

## 5. New jobs

- **`ProcessImportChunkJob`** — processes a single chunk (`tries=3`, `timeout=3600`).
- **`ProcessImportBatchJob`** — orchestrator: creates chunks and dispatches chunk jobs (no longer processes the full file when `confirmed_mapping` is present).

## 6. New routes

| Method | Route | Name |
|--------|-------|------|
| GET | `/imports/{import}/progress` | `imports.progress` |
| POST | `/imports/{import}/chunks/retry-failed` | `imports.chunks.retry-failed` |

## 7. Progress endpoint format

`GET /imports/{id}/progress` returns JSON:

```json
{
  "status": "processing",
  "progress": 54,
  "processed_rows": 2000,
  "total_rows": 3675,
  "completed_chunks": 5,
  "total_chunks": 8,
  "valid_rows": 1740,
  "warning_rows": 210,
  "invalid_rows": 50,
  "duplicate_rows": 0,
  "failed_chunks": 0,
  "is_complete": false,
  "uses_chunks": true
}
```

Legacy batches without chunks use `uses_chunks: false` and derive metrics from `import_batches` columns.

## 8. UI behavior

- After mapping confirmation, user lands on `/imports/{id}` with a live progress panel (animated bar, row/chunk counters, validation counters).
- Message: *You can leave this page and return later.*
- Poll every 4 seconds; page reloads when `is_complete` is true.
- Pipeline actions (standardization, materialize) disabled while status is `queued` / `processing` / `parsing` / `validating`.
- On completion, **Run Standardization** is available as before.
- If `failed_chunks > 0`: **Retry Failed Chunks** and **View Errors** (preview link).

## 9. Retry behavior

`POST /imports/{import}/chunks/retry-failed`:

1. Finds chunks with `status = failed`.
2. Deletes `import_rows` for those chunk row numbers only (idempotent re-run).
3. Resets chunk counters and sets `pending`.
4. Dispatches `ProcessImportChunkJob` for failed chunks only.
5. Sets batch status back to `processing`.

Completed chunks are not reprocessed.

## 10. Queue worker (XAMPP)

Run in a separate terminal while importing:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=3600
```

Ensure `.env` uses `QUEUE_CONNECTION=database` (or `redis`), not `sync`, in production-like local setups.

## 11. Tests passed

```
php artisan test --filter=Import
```

- `ImportChunkProcessingTest` (8 tests): chunk creation, job dispatch, progress API, batch counters, partial failure, retry, legacy batches, cross-chunk duplicates.
- Existing `ImportPipelineTest`, `StandardizeImportBatchJobTest`, and unit tests continue to pass.

## 12. Remaining limitations

- Progress polling is HTTP-only (no WebSockets).
- Chunk orchestration re-reads the file per chunk (streaming per range; acceptable for local/XAMPP, may be optimized with shared parse cache later).
- Empty files finalize immediately with zero rows (no chunks).
- Final `detectForBatch` runs once at the end to reconcile duplicate links; very large batches may add a short finalization step.
- `View Errors` links to full preview; filtered invalid-only view is not yet implemented.

## Configuration

```env
IMPORT_CHUNK_SIZE=500
```

Default in `config/import.php`: `import.chunk_size` = 500.
