# Company Intelligence Module

## Purpose

The Companies module (`GET /companies`, `GET /companies/{company}`) is a **Company Intelligence Registry**. Each materialized `companies` row is a single profile; all related `bid_records` (awarded, lost, participated, and future statuses) roll up under that company.

This module is **database analytics only**. It does not call OpenAI or render AI narrative blocks.

## Data Sources

| Table / relation | Usage |
|------------------|--------|
| `companies` | Primary registry entity |
| `company_aliases` | Search and profile aliases |
| `bid_records` | Metrics, filters, history, summaries |
| `tenders` | Tender #, year, links |
| `tender_items` | Product descriptions |
| `standardized_drugs` | Drug labels and drug summary |
| `countries` | Company country and country summary |
| `currencies` | Available on bid records (management links) |

Static mock company cards are not used.

## Grouping Bid Records Under a Company

During import materialization, each `bid_records.company_id` points at one `companies.id`. The profile page lists every bid record for that ID. Metrics (counts, sums, win rate, summaries) aggregate with `WHERE company_id = ?`.

Alias rows (`company_aliases`) support alternate names from imports; search matches company name or alias text.

## Companies Index (`companies.index`)

### Summary stats (top cards)

- Total companies (after filters)
- Companies with at least one `awarded` bid record
- Total bid records for filtered companies
- Total awarded value (`SUM(tender_value)` where `bid_status = awarded`)
- Average bid records per company
- Top country by company count (when available)

### Per-company columns

Bid record counts, awarded/lost/participated counts, win rate, total awarded value, average awarded price USD, distinct countries and drugs, last activity year, last drug name, activity status badge.

### Win rate

- If `lost` or `participated` records exist: `awarded / (awarded + lost + participated)`.
- If only awarded records exist: label **Awarded records only** (treated as 100% cautiously).
- Otherwise: em dash.

### Activity status

- **active**: latest `award_year` within the last 2 calendar years
- **inactive**: older activity
- **unknown**: no bid records or no year

### Filters (GET, `withQueryString()`)

`search`, `country_id`, `bid_status`, `year`, `tender_number`, `standardized_drug_id`, `awarded_value_min`, `awarded_value_max`, `has_awarded` (`all` / `yes` / `no`), `per_page` (25, 50, 100).

## Company Profile (`companies.show`)

### Header

Name, country, status, aliases, first seen (`MIN(created_at)` on bid records), last activity (`MAX(created_at)`).

### KPI cards

Bid records, awarded wins, lost/participated (when present), win rate, total awarded value, average awarded price USD, unique tenders, unique drugs, countries involved.

### Bid history table

Paginated `bid_records` with tender, drug, pricing, status, winner, analytics flag, import batch, and links to management / filtered management views.

### Drug summary

Grouped by `standardized_drug_id`: record count, awarded count, avg/min/max price USD, last year.

### Country summary

Grouped by `country_id`: records, awarded count, total awarded value, unique tenders.

## Backend

- `App\Http\Controllers\CompanyController`
- `App\Services\Company\CompanyIntelligenceService` — index query, filters, aggregates, profile KPIs, summaries (efficient `withCount` / `withSum` / subqueries; eager loading on history).

## Why No AI

Company pages are for **verifiable operational intelligence** from materialized tenders. AI summaries would duplicate prediction/recommendation flows and are intentionally excluded from this module.

## Future Bid Statuses

Supported statuses: `awarded`, `lost`, `participated`, `disqualified`, `cancelled`, `unknown`. Win rate and lost/participated KPIs already account for non-awarded participation; disqualified/cancelled/unknown appear in history and filters but are outside the win-rate denominator unless business rules change later.
