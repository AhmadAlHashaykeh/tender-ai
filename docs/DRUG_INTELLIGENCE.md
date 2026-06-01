# Drug Intelligence Module

## Purpose

The Drugs module (`GET /drugs`, `GET /drugs/{drug}`) is a **Standardized Drug Intelligence Registry**. Each `standardized_drugs` row is a single profile; related `bid_records`, `tenders`, `companies`, `countries`, and `pricing_statistics` roll up under that drug.

This module is **database analytics only**. It does not call OpenAI or alter prediction logic.

## Data Sources

| Table / relation | Usage |
|------------------|--------|
| `standardized_drugs` | Primary registry entity |
| `drug_aliases` | Search and profile aliases |
| `bid_records` | Metrics, filters, bid history, summaries |
| `tenders` | Tender # and year in history |
| `companies` | Company filters and company summary |
| `countries` | Country filters and country summary |
| `pricing_statistics` | Pre-calculated pricing section and median on index |

Static mock drug cards are not used.

## Grouping Data Under a Standardized Drug

During materialization, each `bid_records.standardized_drug_id` links to one `standardized_drugs.id`. The profile lists all bid records for that drug. `pricing_statistics` rows are maintained by the statistics pipeline (not recalculated on this page).

## Drugs Index (`drugs.index`)

### Summary stats

- Total standardized drugs (after filters)
- Drugs with at least one bid record
- Total bid records
- Distinct countries and companies
- Average price USD across bid records

### Table columns

Code, INN, display name, form, dosage/strength, bid/awarded counts, countries, companies, avg price USD, median USD (global `pricing_statistics` when available), min/max price, last activity, pricing stats badge, actions.

### Filters

`search` (code, INN, display name, alias), `country_id`, `company_id`, `tender_number`, `year`, `bid_status`, `has_pricing_statistics` (`all` / `yes` / `no`), `price_usd_min`, `price_usd_max`, `per_page` (25, 50, 100).

## Drug Profile (`drugs.show`)

### Header

Display name, code, INN, form/strength, aliases.

### KPI cards

Bid records, awarded, countries, companies, tenders, avg/median/min/max/latest price USD, trend (from global pricing statistics when present).

### Pricing statistics section

Rows from `pricing_statistics` scoped by country, region, or global (`country_id` / `region_id` null): award count, last/avg/weighted/median/min/max prices, trend, top winner, calculated at.

### Bid history table

Paginated `bid_records` with tender, company, pricing, status, and links to management, company, and tender profiles.

### Company summary

Grouped by `company_id`: records, awarded, avg price USD, total awarded value.

### Country summary

Grouped by `country_id`: records, awarded, avg/min/max price USD.

## Backend

- `App\Http\Controllers\DrugController` — route parameter `{drug}` binds to `StandardizedDrug`
- `App\Services\Drug\DrugIntelligenceService`

## Relationship with Bid Records and Prediction

- **Bid records**: All tender participation and award lines for a drug.
- **Analytics eligibility**: `BidRecord::scopeAnalyticsEligible()` defines rows used when building `pricing_statistics`; the drug profile shows all bid records, not only analytics-eligible ones.
- **Prediction engine**: Consumes `pricing_statistics` (median, trend, weighted averages) as historical reference; this UI displays those aggregates without running predictions.

## Why No AI

Drug intelligence is for **inspectable registry and pricing history**. AI recommendations use separate controllers and are not rendered on these pages.
