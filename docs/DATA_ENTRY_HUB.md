# TenderAI Data Entry Hub

The `/uploads` page is the primary entry point for data that feeds standardization, materialization, pricing statistics, and predictions.

## Three Sections

### 1. Upload Historical Excel Data

- Accepts `.xlsx`, `.csv` (legacy `.xls` should be saved as `.xlsx`)
- First row must include all 13 required headers (see list on the page)
- Processing: `ImportBatchService::storeUpload` → `ProcessImportBatchJob` (sync)
- Failed header validation redirects back to `/uploads` with an error (batch `status=failed`, `metadata.missing_headers`)

### 2. Add Historical Row Manually

- Same 13 fields as Excel, submitted as a form
- `ManualImportService` → `ImportBatchService::storeManualEntry`
- `source_type = manual`, one `import_row`, `row_type = winning_bid`, `standardization_status = pending`
- Uses `ImportValidatorService` and `DuplicateDetectionService` identically to file import
- Does **not** create companies, drugs, tenders, or bid records directly

After save, run the pipeline:

```bash
php artisan imports:standardize --only-pending
php artisan imports:materialize
php artisan stats:refresh --all
```

### 3. Add Upcoming Tender

- Future tender metadata for prediction workflows
- `UpcomingTenderService` creates:
  - `tenders` with `status = upcoming`, `title` = tender name
  - `tender_items` with expected drug/qty in `metadata`
- **No** `import_batches`, **no** `import_rows`, **no** `bid_records`
- Appears in AI Recommendations tender dropdown (tagged `[Upcoming]`)

## Historical vs Upcoming

| Aspect | Historical (Excel / manual) | Upcoming tender |
|--------|----------------------------|-----------------|
| Purpose | Past awarded prices | Future bid planning |
| Storage | `import_rows` | `tenders` + `tender_items` |
| Prices required | Price USD required | No prices |
| Bid records | After materialization | Never |
| AI training stats | Yes (after stats refresh) | Context only (tender link) |

## Configuration

Required header display labels: `config/import.php` → `header_labels`

Column mapping aliases: `config/import.php` → `expected_columns`
