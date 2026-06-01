# TenderAI Database Schema

Phase 2+ database foundation for pharmaceutical tender intelligence. This document describes table groups, relationships, and design philosophy. **Pricing statistics (Phase 5) are implemented; prediction logic and AI are not.**

## Database Driver

The project is configured for **MySQL** (`DB_CONNECTION=mysql`, database `tendar_ai`). Laravel queue, session, and cache also use the database driver where applicable.

If MySQL is unavailable, configure XAMPP/MySQL and create the database before running migrations. SQLite is not the target for production imports.

## Table Groups

### Reference Data

| Table | Purpose |
|-------|---------|
| `regions` | Geographic groupings (GCC, North Africa, Levant) |
| `countries` | Target markets linked to a region and default currency |
| `currencies` | ISO-style currency codes; **USD** is the default canonical reporting currency |

### Raw Import Pipeline

| Table | Purpose |
|-------|---------|
| `import_batches` | One uploaded Excel file and batch-level counters/status |
| `import_rows` | **Immutable raw row storage** — every Excel column preserved as typed raw fields plus full `raw_data` JSON |
| `import_row_duplicates` | Detected duplicate pairs within or across batches |

### Drug Standardization

| Table | Purpose |
|-------|---------|
| `standardized_drugs` | Canonical drug identity (INN, display name, strength, form, etc.) |
| `drugs` | Pre-standardization or linked raw drug records from imports |
| `drug_aliases` | Alternate names mapped to a standardized drug |

### Company Standardization

| Table | Purpose |
|-------|---------|
| `companies` | Canonical company records |
| `company_aliases` | Alternate company names (winners, bidders, legal names) |

### Tender Domain

| Table | Purpose |
|-------|---------|
| `tenders` | Tender header: number, country, year, optional version |
| `tender_items` | Line items within a tender, optionally linked to a standardized drug |
| `bid_records` | **Flexible bid facts** — winners today, other statuses tomorrow |

### Standardization Support

| Table | Purpose |
|-------|---------|
| `standardization_suggestions` | Pending or resolved mapping suggestions (rule/AI/manual) |
| `standardization_logs` | Audit trail of standardization actions |

### Statistics and Predictions (Foundation Only)

| Table | Purpose |
|-------|---------|
| `pricing_statistics` | Aggregated unit pricing per drug × geography |
| `cached_market_statistics` | Precomputed market snapshots for fast reads |
| `predictions` | User prediction requests (schema only in Phase 2) |
| `prediction_calculations` | Step-by-step calculation trace |
| `prediction_scenarios` | Alternative price/win-probability scenarios |
| `prediction_context_snapshots` | Frozen stats/context at prediction time |
| `prediction_historical_refs` | Historical bids used as prediction inputs |
| `prediction_accuracy_records` | Post-hoc accuracy evaluation |
| `ai_usage_logs` | Token/cost/latency audit for OpenAI (future) |
| `outlier_flags` | Statistical outliers on bids or import rows |

### System / Audit

| Table | Purpose |
|-------|---------|
| `settings` | Key-value application configuration |
| `audit_logs` | Polymorphic change audit |

### Laravel Defaults (Phase 1)

| Table | Purpose |
|-------|---------|
| `users`, `sessions`, `password_reset_tokens` | Breeze authentication |
| `cache`, `cache_locks` | Database cache |
| `jobs`, `job_batches`, `failed_jobs` | Queue processing |

## Important Relationships

```
Region 1──* Country 1──* Tender 1──* TenderItem 1──* BidRecord
                    │                      │
                    └── default Currency   └── StandardizedDrug
Country 1──* BidRecord
Company 1──* BidRecord
Company 1──* CompanyAlias
StandardizedDrug 1──* DrugAlias
StandardizedDrug 1──* PricingStatistic

ImportBatch 1──* ImportRow ──optional──► Tender, TenderItem, BidRecord, StandardizedDrug, Company
BidRecord ──optional──► source ImportRow

Prediction *──► User, StandardizedDrug, Tender (optional)
Prediction 1──* PredictionScenario
```

## Import Row Philosophy

Every Excel row is stored in `import_rows` with:

1. **Dedicated raw columns** matching spreadsheet headers (`raw_code`, `raw_inn`, `raw_price_usd`, etc.) — always strings at ingest time to avoid silent data loss.
2. **`raw_data` JSON** — complete row payload for forward compatibility.
3. **`normalized_data` JSON** — populated in a later standardization phase only.
4. **Status fields** — `validation_status`, `standardization_status`, `row_type` as strings for portability.
5. **Confidence scores** — per entity (drug, company, tender) for future auto-approval thresholds from `settings`.
6. **Nullable FK links** — `standardized_drug_id`, `company_id`, `tender_id`, `tender_item_id`, `bid_record_id` filled after processing, not at raw ingest.

Raw data is never overwritten; corrections append via standardization logs and linked canonical records.

## Bid Records Flexibility

`bid_records` decouple **analytics facts** from import rows:

| Field | Role |
|-------|------|
| `price_usd` | **Canonical price** for statistics and predictions |
| `original_awarded_price` | Local currency amount as imported |
| `bid_status` | `awarded`, `lost`, `participated`, `disqualified`, `cancelled`, `unknown` (string, extensible) |
| `is_winner` | Boolean flag for winner rows |
| `row_type` | e.g. `winning_bid` for current historical data |
| `is_analytics_ready` | Gate before stats aggregation |
| `excluded_from_stats` | Manual or rule-based exclusion |

Current historical imports will map to: `bid_status = awarded`, `is_winner = true`, `row_type = winning_bid`.

## Price USD as Canonical Price

- Excel column **Price USD** maps to `import_rows.raw_price_usd` (string) and eventually `bid_records.price_usd` (decimal).
- **Awarded price** in local currency is preserved in `raw_awarded_price` / `original_awarded_price`.
- All `pricing_statistics` and `predictions` unit economics use USD unless explicitly converted using `currencies`.

## Tender Identity

Tenders are identified logically by:

- `tender_number`
- `country_id`
- `year`
- `version` (nullable)

Indexes exist on these columns; **no strict unique constraint** yet to avoid import-order issues. Uniqueness enforcement can be added in the import phase.

## Settings (Seeded Defaults)

| Key | Default |
|-----|---------|
| `prediction.calculation_model_version` | v1.0 |
| `prediction.backend_only_confidence_threshold` | 80 |
| `standardization.drug_auto_approve_min` | 85 |
| `standardization.company_auto_approve_min` | 85 |
| `standardization.row_auto_approve_min` | 80 |
| `standardization.ai_auto_approve_min` | 85 |
| `ai.provider` | openai |
| `ai.default_model` | gpt-4o-mini |

## Roles and Permissions

**Spatie Laravel Permission is not installed** in Phase 2. Breeze `users` table remains the sole auth model. Install roles/permissions in a dedicated auth phase if required.

## Import Pipeline (Phase 3)

Raw Excel/CSV upload is implemented. See **[IMPORT_PIPELINE.md](IMPORT_PIPELINE.md)** for upload flow, OpenSpout parser, validation rules, duplicate detection, and queue/sync behavior.

Phase 3 populates `import_batches` and `import_rows` only. Entity FKs on `import_rows` remain null until Phase 4.

## Future Import Phase Notes

1. Create `import_batch` → parse Excel → insert `import_rows` with `row_hash` deduplication.
2. Resolve country names to `countries` via aliases (future).
3. Standardize drugs/companies → populate FKs on `import_rows`.
4. Upsert `tenders`, `tender_items`, `bid_records` from approved rows.
5. Recalculate `pricing_statistics` via `php artisan stats:refresh` — see **[PRICING_STATISTICS_ENGINE.md](PRICING_STATISTICS_ENGINE.md)**.
6. OpenAI only for suggestions below auto-approve thresholds.

## Migration Files

| File | Tables |
|------|--------|
| `2026_05_25_100001_create_reference_tables.php` | regions, currencies, countries |
| `2026_05_25_100002_create_standardization_entity_tables.php` | standardized_drugs, drugs, drug_aliases, companies, company_aliases |
| `2026_05_25_100003_create_import_tables.php` | import_batches, import_rows, import_row_duplicates |
| `2026_05_25_100004_create_tender_domain_tables.php` | tenders, tender_items, bid_records + import_rows FKs |
| `2026_05_25_100005_create_standardization_support_tables.php` | standardization_suggestions, standardization_logs |
| `2026_05_25_100006_create_statistics_tables.php` | pricing_statistics, cached_market_statistics, outlier_flags |
| `2026_05_25_100007_create_predictions_tables.php` | predictions, prediction_*, ai_usage_logs |
| `2026_05_25_100008_create_system_audit_tables.php` | settings, audit_logs |
