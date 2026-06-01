# Reset Operational Data

Use this when you need a clean TenderAI slate while keeping accounts, configuration, and geographic reference data.

## Command

```bash
php artisan tenderai:reset-data
```

Non-interactive (CI/scripts):

```bash
php artisan tenderai:reset-data --force

# Skip automatic cache clear (e.g. in tests)
php artisan tenderai:reset-data --force --no-cache-clear
```

## What It Does

1. Shows a warning and row counts for business tables.
2. Asks for confirmation (unless `--force` is passed).
3. **Creates a database backup** before any deletes.
4. Disables foreign key checks safely.
5. Truncates business tables in dependency order.
6. Re-enables foreign key checks.
7. Prints a summary of cleared and preserved tables.
8. Verifies each area (Import, Product matching, Materialized data, Predictions, Jobs, etc.) reports **CLEAN**.
9. Runs `php artisan optimize:clear` (skip with `--no-cache-clear`).
10. Verifies the database connection and that users/settings/reference data are still readable.

## Tables Cleared

| Group | Tables |
|-------|--------|
| Import pipeline | `import_batches`, `import_rows`, `import_row_duplicates`, `import_mapping_templates` |
| Chunk orchestration | `import_chunks`, `standardization_chunks`, `materialization_chunks` |
| Standardization | `standardization_suggestions`, `standardization_logs` |
| Entities | `companies`, `company_aliases`, `standardized_drugs`, `drugs`, `drug_aliases` |
| Tender domain | `tenders`, `tender_items`, `bid_records` |
| Statistics | `pricing_statistics`, `cached_market_statistics`, `outlier_flags` |
| Predictions & AI | `predictions`, `prediction_calculations`, `prediction_scenarios`, `prediction_context_snapshots`, `prediction_historical_refs`, `prediction_accuracy_records`, `ai_usage_logs` |
| Audit | `audit_logs` |

## Tables Preserved

| Group | Tables |
|-------|--------|
| Accounts & config | `users`, `settings` |
| Reference data | `countries`, `regions`, `currencies` |
| Framework | `migrations`, `sessions`, `password_reset_tokens`, `cache`, `cache_locks` |

## Queue Tables Cleared (testing)

Stale queue jobs may reference deleted import batches. These are truncated on every reset:

- `jobs`
- `failed_jobs`
- `job_batches`

## Backup

Backups are written to:

```
storage/app/backups/
```

### MySQL (XAMPP / production)

- Filename pattern: `{database}_{YYYY-MM-DD_HHMMSS}.sql`
- Uses `mysqldump` with `--single-transaction`
- If `mysqldump` is not on PATH, set in `.env`:

```env
MYSQLDUMP_PATH=D:\xampp\mysql\bin\mysqldump.exe
```

### SQLite (local/testing)

- Copies the database file to `storage/app/backups/sqlite_{timestamp}.sqlite`
- In-memory SQLite (PHPUnit) skips backup automatically

### Manual backup (recommended before production reset)

```bash
# MySQL
D:\xampp\mysql\bin\mysqldump.exe -u root -p tendar_ai > backup_before_reset.sql

# Or via phpMyAdmin: Export → SQL
```

Keep backups off the web root. `storage/app/backups/` is not publicly served by default.

## After Reset

1. Confirm grouped verification shows **CLEAN** for Import data, Product matching, Materialized data, Predictions, and Jobs.
2. Confirm **System data preserved** lists Users, Countries, Currencies as OK.
3. Start the queue worker (cache is cleared automatically unless `--no-cache-clear`):

```bash
php artisan queue:work --queue=default --tries=3 --timeout=3600
```

4. Log in with your existing user account.
5. Re-import data via the upload hub (~500 rows), or seed test data:

```bash
php artisan tenderai:seed-test-data --fresh-domain --run-pipeline
```

4. Optionally run the test suite:

```bash
php artisan test
```

## Related Commands

| Command | Purpose |
|---------|---------|
| `tenderai:seed-test-data` | Seed a controlled 6-row test import (dev only) |
| `imports:standardize` | Run standardization on pending import rows |
| `imports:materialize` | Materialize approved rows into domain entities |
| `stats:refresh --all` | Rebuild pricing statistics |

## Safety Notes

- This is **destructive** and cannot be undone without restoring from backup.
- Always create and verify a backup before running on shared or production databases.
- User passwords and application settings are **not** removed.
- Country/region/currency reference data seeded at install remains intact.
