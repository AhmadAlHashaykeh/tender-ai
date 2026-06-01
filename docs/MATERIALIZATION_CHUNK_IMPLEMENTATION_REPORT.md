# Materialization Chunk Implementation Report

## 1. Root Cause

`POST /imports/{id}/materialize` called `ImportMaterializationService::materializeBatch()` synchronously inside the HTTP request. For batch #3 (thousands of approved rows), each row triggered a full materialization chain inside a DB transaction:

- `BidRecord::exists()` per row
- Company lookup/create + alias upserts
- Drug lookup/create + alias upserts
- Tender lookup/create
- Tender item create
- Bid record create

This exceeded PHP’s 60-second `max_execution_time` in `ManagesTransactions.php`. The correct fix is chunked queue jobs with progress metadata—not raising the execution time limit.

## 2. Files Modified

| Area | Files |
|------|--------|
| Controller / routes | `app/Http/Controllers/ImportBatchController.php`, `routes/web.php` |
| Core materialization | `app/Services/Materialization/ImportMaterializationService.php`, `CompanyMaterializationService.php`, `DrugMaterializationService.php`, `TenderMaterializationService.php`, `BidRecordMaterializationService.php`, `Concerns/ManagesEntityAliases.php` |
| Chunk orchestration | `app/Services/Materialization/MaterializationChunkService.php`, `MaterializationLookupCache.php` |
| Pipeline / progress | `app/Services/Import/ImportPipelineService.php`, `ImportProgressService.php` |
| Model | `app/Models/ImportBatch.php` |
| Standardization cache | `app/Services/Standardization/EntityMatchIndexService.php` (`rememberDrug`) |
| Config | `config/import.php` |
| UI | `resources/views/imports/show.blade.php`, `resources/views/components/import-materialization-progress.blade.php` |
| Tests | `tests/Feature/MaterializationChunkTest.php`, `tests/Feature/MaterializationEngineTest.php` |

## 3. New Jobs

- **`MaterializeImportBatchJob`** — Orchestrator: creates `materialization_chunks` and dispatches chunk jobs. Skips if chunks already exist (idempotent re-dispatch guard).
- **`MaterializeImportChunkJob`** — Processes one chunk (default 100 rows), updates counters, syncs batch metadata, finalizes when all chunks complete. Retries up to 3 times; failed chunks do not block completed chunks.

## 4. New Table / Migration

**`materialization_chunks`** — `database/migrations/2026_05_31_400001_create_materialization_chunks_table.php`

| Column | Purpose |
|--------|---------|
| `import_batch_id`, `chunk_number`, `start_row_number`, `end_row_number` | Chunk boundaries |
| `status` | `pending`, `processing`, `completed`, `failed` |
| `total_rows`, `processed_rows`, `materialized_rows`, `skipped_rows`, `failed_rows` | Progress counters |
| `error_message`, `started_at`, `completed_at` | Diagnostics |

## 5. Controller Changes

- **`materialize`** — Dispatches `MaterializeImportBatchJob` via `MaterializationChunkService::dispatchBatchJob()`. Redirects immediately with: *"Materialization has started in the background."* No synchronous `materializeBatch()` in the HTTP path.
- **`retryFailedMaterializationChunks`** — `POST /imports/{id}/materialization/retry-failed` — Resets only `failed` chunks to `pending` and re-queues them.

## 6. UI Changes

- Live progress panel (`<x-import-materialization-progress>`) while `materialization_status === processing` (polls `/imports/{id}/progress`).
- Header actions: disabled “Materialization Running…”, retry failed chunks, view errors.
- Completion banner with materialized/skipped/failed counts and next-step hint (`php artisan stats:refresh`).
- Pipeline step detail shows in-progress row/chunk counts.

## 7. Progress Metadata (`import_batches.metadata`)

| Key | Values / meaning |
|-----|----------------|
| `materialization_status` | `not_started`, `processing`, `completed`, `failed` |
| `materialization_started_at` | ISO8601 |
| `materialization_completed_at` | ISO8601 |
| `materialization_processed_rows` | Rows touched in chunks |
| `materialization_total_rows` | Eligible rows at start |
| `materialization_materialized_rows` | Successful materializations |
| `materialization_skipped_rows` | Ineligible or already done |
| `materialization_failed_rows` | Per-row failures |
| `materialization_last_error` | Human-readable summary |
| `materialization_summary` | `{ processed, materialized, skipped, failed }` |
| `materialization_total_chunks` / `materialization_completed_chunks` / `materialization_failed_chunks` | Chunk progress |

## 8. Retry Behavior

- Only chunks with `status = failed` are reset and re-dispatched.
- Completed chunks are never re-run.
- Batch `materialization_status` returns to `processing` on retry.
- Row-level failures inside a chunk do not fail the whole chunk (existing try/catch per row in `ImportMaterializationService`).

## 9. Performance Improvements

**`MaterializationLookupCache`** (per chunk, cleared after each chunk):

- Preloads `source_import_row_id` for chunk rows → avoids per-row `exists` on `bid_records`.
- Reuses **`EntityMatchIndexService`** warmup for companies (normalized name) and drugs (code).
- Preloads tenders for countries present in the chunk.
- In-memory tender key cache for rows sharing tender numbers.
- Country model cache per `country_id`.
- Alias upsert deduplication within chunk (`company_id|normalized`, `drug_id|normalized`) to avoid duplicate insert attempts.

Config: `MATERIALIZATION_CHUNK_SIZE=100` or `config('import.materialization_chunk_size')`.

## 10. Queue Command for XAMPP

```bash
cd d:\xampp\htdocs\tendar-ai
php artisan queue:work --tries=3 --timeout=3600
```

Ensure `.env` has `QUEUE_CONNECTION=database` (or `redis`) and run `php artisan queue:table` + migrate if using the database driver.

## 11. Tests Passed

```
php artisan test --filter=Materialization
```

**15 tests, 77 assertions — all passed**, including:

- Controller dispatches job (no sync materialization)
- Chunk creation and orchestration
- Eligible-only processing, skip already materialized
- Failed row does not kill chunk
- Progress metadata + finalization
- Retry failed chunks only
- Import show renders progress panel

---

**Note:** CLI `php artisan imports:materialize` still uses synchronous `materializeBatch()` for operator-controlled runs with optional `--limit` and `--dry-run`. The web UI always uses the chunked queue path.
