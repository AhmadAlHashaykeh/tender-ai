# Import Show Performance & Standardization Chunking — Report

## 1. Root cause

`GET /imports/{id}` timed out because the page triggered **O(n) PHP work over every import row**:

| Hot path | Problem |
|----------|---------|
| `ImportMaterializationService::batchMaterializationStats()` | `ImportRow::...->get()` then foreach row called `isAlreadyMaterialized()` → **one `EXISTS` query per row** on `bid_records` |
| `ImportBatchController::show()` | Called `refreshQualityScore()` → full-table scans on `import_rows` |
| `ImportPipelineService::rowCounts()` | Up to **10 separate COUNT queries** (acceptable alone, heavy combined) |
| Row preview | Loaded 50 rows (minor compared to materialization loop) |

For batch #4 with thousands of rows, the materialization stats loop alone issued thousands of SQL queries and exceeded the 60s PHP limit.

## 2. Exact slow methods fixed

- `ImportMaterializationService::batchMaterializationStats()` — rewritten with aggregate SQL + `EXISTS` subqueries (no row hydration loop).
- `ImportBatchController::show()` — removed `refreshQualityScore()` on page load; uses cached metadata via `ImportBatchStatsService::cachedQuality()`.
- `ImportBatchController::show()` — removed inline row table; summary + link to paginated preview only.
- `ImportPipelineService::rowCounts()` — delegated to `ImportBatchStatsService` (single aggregate query or cached metadata).
- `ImportBatchController::pricingStatsSummaryForBatch()` — replaced O(n²) `orWhere` pairs loop with `groupBy` + `whereExists`.
- `ImportRowStandardizationService::standardizeBatchWithProgress()` — large batches now go through **chunk jobs** via `StandardizationChunkService` (orchestrator pattern).

## 3. Files modified

- `app/Services/Materialization/ImportMaterializationService.php`
- `app/Services/Import/ImportBatchStatsService.php` (new)
- `app/Services/Import/ImportPipelineService.php`
- `app/Services/Import/ImportProgressService.php`
- `app/Services/Standardization/StandardizationChunkService.php` (new)
- `app/Services/Standardization/ImportRowStandardizationService.php`
- `app/Jobs/StandardizeImportBatchJob.php`
- `app/Jobs/StandardizeImportChunkJob.php` (new)
- `app/Http/Controllers/ImportBatchController.php`
- `app/Http/Controllers/StandardizationController.php`
- `app/Models/ImportBatch.php`, `StandardizationChunk.php` (new)
- `config/import.php` — `standardization_chunk_size`
- `resources/views/imports/show.blade.php`
- `resources/views/components/import-standardization-progress.blade.php` (new)
- `routes/web.php`
- `database/migrations/2026_05_31_300001_create_standardization_chunks_table.php`
- Tests: `ImportShowPerformanceTest.php`, `StandardizeImportBatchJobTest.php`

## 4. Query count before vs after (typical 5,000-row batch)

| Area | Before (approx.) | After (approx.) |
|------|------------------|-----------------|
| Materialization stats | 1 SELECT all rows + **5,000 EXISTS** | **~6 aggregate queries** |
| Quality score on show | ~8 COUNT + AVG scans | **0** (cached metadata) |
| Row preview on show | 1 SELECT LIMIT 50 | **0** (link only) |
| Pipeline row counts | ~10 COUNT queries | **1 aggregate** or metadata |
| **Total show page** | **5,000+** | **~15–20** |

## 5. New aggregate formulas

**Materialized**

```sql
COUNT(*) FROM import_rows
WHERE import_batch_id = ?
AND (bid_record_id IS NOT NULL OR EXISTS (
  SELECT 1 FROM bid_records WHERE source_import_row_id = import_rows.id
))
```

**Eligible pending** — valid/warning + auto_approved/approved + no bid link + JSON `price_usd` > 0 + `country_id` + drug/company/tender fields present.

**Failed materialization** — `json_extract(normalized_data, '$.materialization_status') = 'failed'`.

**Pending standardization / awaiting review** — status-based COUNTs.

**Ineligible** — `total - (materialized + failed + eligible + pending_std + review)` from batch `row_count`.

## 6. Standardization chunking design

```
Run Standardization (HTTP)
  → StandardizeImportBatchJob (orchestrator)
      → StandardizationChunkService::createChunksForBatch()
          (pending rows split by row_number, default 100 per chunk)
      → StandardizeImportChunkJob × N
          → warmup match index
          → standardize pending rows in [start_row_number, end_row_number]
          → update chunk counters + batch metadata
      → checkBatchFinalization() → completed / failed + quality refresh
```

Table: `standardization_chunks` (mirrors `import_chunks` pattern).

Config: `STANDARDIZATION_CHUNK_SIZE=100` / `import.standardization_chunk_size`.

## 7. Progress UI behavior

- **Import**: existing `import-processing-progress` polls `GET /imports/{id}/progress`.
- **Standardization**: `import-standardization-progress` polls same endpoint; reads `standardization` object in JSON.
- Displays: rows processed, chunks completed, auto-approved, review, rejected, failed rows.
- Reloads page when `standardization.is_complete` is true.

## 8. Retry behavior

- `POST /standardization/batches/{batch}/retry-failed` — resets **failed chunks only** to `pending`, re-dispatches `StandardizeImportChunkJob` for those chunks.
- Completed chunks are untouched (idempotent).

## 9. Queue command (XAMPP)

```bash
php artisan queue:work --queue=default --tries=3 --timeout=3600
```

## 10. Tests passed

```
php artisan test --filter="ImportShow|StandardizeImport|ImportChunk|ImportPipeline|ImportQuality"
```

29 tests — including 6 new `ImportShowPerformanceTest` cases (no full row load on show, aggregate materialization stats, chunk creation, progress API, retry failed chunks, legacy batches).

## Remaining limitations

- Eligible-pending SQL approximates PHP `isEligible()` rules; edge cases with unusual JSON shapes may differ slightly from per-row evaluation.
- `standardizeBatchWithProgress()` still exists for direct/CLI use on small batches; production UI uses chunk jobs.
- Pricing statistics count uses `whereExists` join — still proportional to materialized pairs, not import row count.
