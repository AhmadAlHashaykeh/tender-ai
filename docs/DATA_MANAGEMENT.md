# Data Management Page

## Purpose

The **Data Management** page (`GET /management`) is the primary interface for viewing, filtering, searching, and editing materialized tender bid data after import, standardization, and materialization.

It replaces mock/card-only layouts with a scalable, paginated table suitable for large datasets.

## Primary Data Source

The main table is driven by **`bid_records`**, which represent analytics-ready materialized outcomes. Each row may join:

- `tender`, `tender_item`
- `standardized_drug`, `company`, `country`, `currency`
- `source_import_row` (`import_rows`), `import_batch`

Display fields prefer normalized/materialized values and fall back to raw import columns when links are missing (e.g. `standardized_drug.code` → `import_rows.raw_code`).

## Filters (GET, shareable URLs)

| Parameter | Description |
|-----------|-------------|
| `search` | Code, INN, product name, company, tender # |
| `country_id` | Country |
| `year` | Award year or tender year |
| `tender_number` | Tender number (partial match) |
| `company_id` | Company |
| `standardized_drug_id` | Standardized drug |
| `bid_status` | awarded, lost, participated, disqualified, cancelled, unknown |
| `analytics_ready` | all, yes, no |
| `winner` | all, winner, non_winner |
| `excluded` | all, excluded, included |
| `import_batch_id` | Import batch |
| `price_min` / `price_max` | Price USD range |
| `qty_min` / `qty_max` | Quantity range |
| `per_page` | 25, 50, or 100 (default 25) |

## Pagination

Laravel pagination is used; all rows are never loaded at once. Default page size is **25**.

## Editable Fields

Edit via `GET/PUT /management/bid-records/{id}`:

- `price_usd`, `original_awarded_price`, `quantity`, `tender_value`
- `bid_status`, `is_winner`, `is_analytics_ready`
- `excluded_from_stats`, `exclusion_reason`
- Optional: `company_id`, `standardized_drug_id`, `tender_id`

Updates store `edited_by` and `edited_at` in `bid_records.metadata`.

## Raw Data Safety

**Do not edit raw import row fields from this UI.** `import_rows` remain the source of truth for uploaded values. The edit form shows raw fields as read-only reference. Only `bid_records` (and optional FK links) are updated.

## Exclude / Include From Statistics

`POST /management/bid-records/{id}/toggle-exclusion` toggles `excluded_from_stats` without deleting the record.

## Details Page

`GET /management/bid-records/{id}` shows bid record fields, related entities, raw/normalized import data, and batch/materialization context.

## After Editing Pricing Fields

Materialized bid edits do **not** automatically refresh aggregated pricing statistics. After changing `price_usd`, `original_awarded_price`, `quantity`, or `tender_value`, run:

```bash
php artisan stats:refresh
```

## Routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/management` | `management.index` |
| GET | `/management/bid-records/{bidRecord}` | `management.bid-records.show` |
| GET | `/management/bid-records/{bidRecord}/edit` | `management.bid-records.edit` |
| PUT | `/management/bid-records/{bidRecord}` | `management.bid-records.update` |
| POST | `/management/bid-records/{bidRecord}/toggle-exclusion` | `management.bid-records.toggle-exclusion` |

## Implementation

- Controller: `App\Http\Controllers\ManagementController`
- Query/filters: `App\Services\Management\BidRecordManagementService`
- Tests: `tests/Feature/ManagementTest.php`
