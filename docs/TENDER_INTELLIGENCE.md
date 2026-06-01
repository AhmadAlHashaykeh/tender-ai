# Tender Intelligence Module

## Purpose

The Tenders module (`GET /tenders`, `GET /tenders/{tender}`) is a **Tender Intelligence Registry**. Each materialized `tenders` row is a single profile; related `tender_items`, `bid_records`, `companies`, and `standardized_drugs` roll up under that tender.

This module is **database analytics only**. It does not call OpenAI or alter prediction logic.

## Data Sources

| Table / relation | Usage |
|------------------|--------|
| `tenders` | Primary registry entity |
| `tender_items` | Line items per tender |
| `bid_records` | Metrics, filters, history, summaries |
| `companies` | Company filters and company summary |
| `standardized_drugs` | Drug filters and drug summary |
| `countries` | Tender country and bid-record countries |
| `import_batches` / `import_rows` | Source traceability on profile and history |

Static mock tender cards are not used.

## Grouping Data Under a Tender

During import materialization, each `bid_records.tender_id` and `tender_items.tender_id` point at one `tenders.id`. The profile page lists every bid record for that tender. Metrics aggregate with `WHERE tender_id = ?`.

## Tenders Index (`tenders.index`)

### Summary stats (top cards)

- Total tenders (after filters)
- Total tender items
- Total bid records
- Total awarded records
- Total winning records (`is_winner`)
- Total awarded value
- Distinct countries, drugs, and companies across filtered tenders

### Table columns

Tender #, name (`title` or `tender_number`), country, year, version, items count, bid records, awarded count, companies, drugs, awarded value, avg price USD, last activity year, status, actions.

### Filters (GET, `withQueryString()`)

`search` (tender #, title, company, drug code/INN/name), `country_id`, `year`, `version`, `company_id`, `standardized_drug_id`, `bid_status`, `winner` (`all` / `yes` / `no`), `analytics_ready` (`all` / `yes` / `no`), `price_usd_min`, `price_usd_max`, `tender_value_min`, `tender_value_max`, `per_page` (25, 50, 100).

## Tender Profile (`tenders.show`)

### Header

Tender number, name, country, year, version, status, import batch (from bid records when present).

### KPI cards

Items, bid records, awarded records, companies, drugs, total awarded value, avg/min/max price USD.

### Items / bid records table

Paginated `bid_records` with drug, company, pricing, status, winner, analytics flag, import row, and links to management, company, and drug profiles.

### Company summary

Grouped by `company_id`: records, awarded, total awarded value, avg price USD.

### Drug summary

Grouped by `standardized_drug_id`: records, awarded, avg/min/max price USD.

## Backend

- `App\Http\Controllers\TenderController`
- `App\Services\Tender\TenderIntelligenceService` — index query, filters, aggregates, profile KPIs, summaries (`withCount` / `withSum` / subqueries; eager loading on history).

## Relationship with Prediction Engine

Tender intelligence surfaces **historical procurement context** (participants, drugs, values) used indirectly by predictions. The prediction engine reads `pricing_statistics` and analytics-eligible `bid_records`; this module does not recalculate predictions or call AI.

## Why No AI

Tender pages are for **verifiable operational intelligence** from materialized imports. AI summaries belong in recommendation/prediction flows and are excluded here.
