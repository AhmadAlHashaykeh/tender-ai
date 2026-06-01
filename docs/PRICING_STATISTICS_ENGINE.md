# Pricing Statistics Engine (Phase 5)

Phase 5 aggregates **analytics-ready winning bid records** into `pricing_statistics` and flags statistical outliers in `outlier_flags`. This layer feeds the future hybrid prediction engine (Phase 6). **No OpenAI, no predictions, no automatic outlier exclusion.**

## Eligible bid_record criteria

A record is included in pricing statistics when **all** of the following are true:

| Field | Requirement |
|-------|-------------|
| `is_analytics_ready` | `true` |
| `excluded_from_stats` | `false` |
| `bid_status` | `awarded` |
| `is_winner` | `true` |
| `price_usd` | not null and > 0 |
| `standardized_drug_id` | not null |
| `country_id` | not null |

Implemented as `BidRecord::scopeAnalyticsEligible()`.

## Geographic scopes

| Priority | Scope | `pricing_statistics` keys |
|----------|--------|---------------------------|
| 1 | Drug × country | `standardized_drug_id` + `country_id`, `region_id` null |
| 2 | Drug × region (fallback) | `standardized_drug_id` + `region_id`, `country_id` null |
| 3 | Drug global (fallback) | `standardized_drug_id` only, `country_id` and `region_id` null |

Regional/global rows are computed when `stats:refresh --all` runs (or default refresh with no filters).

## Statistics formulas

| Metric | Formula |
|--------|---------|
| `award_count` | Count of eligible records in group |
| `avg_unit_price` | Arithmetic mean of `price_usd` |
| `median_unit_price` | See median logic below |
| `weighted_avg_unit_price` | Weighted mean by award year |
| `min_unit_price` / `max_unit_price` | Min / max of `price_usd` |
| `price_std_dev` | Population standard deviation |
| `last_unit_price` | `price_usd` of latest award (by `award_year`, then `id`) |
| `last_award_date` | `YYYY-12-31` from `award_year`, or `created_at` date if year missing |
| `distinct_winners_count` | Distinct `company_id` count |
| `top_winner_company_id` | Company with highest win count in group |
| `currency_id` | Mode of `currency_id` on records, else USD |
| `stats_version` | `v1` on create; incremented (`v2`, `v3`, …) on update |
| `calculated_at` | Timestamp of calculation |

## Weighted average logic

Per record, year weight relative to the group's maximum `award_year`:

| Relative year | Weight |
|---------------|--------|
| Latest year (diff ≤ 0) | 3 |
| Previous year (diff = 1) | 2 |
| Older (diff ≥ 2) | 1 |
| Missing year | 1 |

```text
weighted_avg = sum(price * weight) / sum(weight)
```

## Median logic

Calculated in PHP (`PricingAggregationService::median`):

1. Sort prices numerically.
2. Odd count: middle value.
3. Even count: average of the two middle values.

## Trend logic

Built from **yearly median** prices (`award_year` → median `price_usd`):

- Fewer than 2 years with data → `trend_direction = unknown`, `trend_pct = null`.
- Compare earliest vs latest year medians:
  - Latest > earliest by **more than 5%** → `rising`
  - Latest < earliest by **more than 5%** → `falling`
  - Otherwise → `stable`

```text
trend_pct = ((latest_median - earliest_median) / earliest_median) * 100
```

## Outlier detection logic

`OutlierDetectionService` (IQR on `price_usd` per drug × country group):

- **&lt; 4 records:** no outliers flagged.
- Q1 = 25th percentile, Q3 = 75th percentile (linear interpolation).
- `IQR = Q3 - Q1`
- Lower bound = `Q1 - 1.5 * IQR`, upper bound = `Q3 + 1.5 * IQR`
- Prices outside bounds → row in `outlier_flags` (`entity_type = bid_record`, `flag_type = iqr_price_outlier`).
- **Does not** set `excluded_from_stats` automatically.
- Skips duplicate unresolved flags for the same bid + source (`pricing_statistics_refresh`).

## Services

| Service | Role |
|---------|------|
| `PricingAggregationService` | Average, median, weighted average, std dev, yearly medians, trend, IQR |
| `PricingStatisticsService` | Calculate and upsert `pricing_statistics` per scope |
| `OutlierDetectionService` | IQR outlier flags |
| `StatisticsRefreshService` | Orchestrate refresh + outlier detection |

## Command usage

```bash
# Refresh all drug/country groups (+ regional/global fallbacks)
php artisan stats:refresh --all

# Default (no options): same as full refresh with fallbacks
php artisan stats:refresh

# Subset
php artisan stats:refresh --drug=1 --country=2

# Dry run (no writes)
php artisan stats:refresh --dry-run
```

## Job

`RefreshPricingStatisticsJob` calls `StatisticsRefreshService::refreshSubset()`. Dispatch manually when needed; **not** wired to materialization automatically in Phase 5.

## UI

- `GET /statistics/pricing` (`statistics.pricing.index`) — paginated drug × country statistics.
- Import batch show page — summary of pricing stat groups after materialization.

## Manual pipeline (empty local DB)

1. Upload Excel/CSV (`/uploads`).
2. Standardize batch (`standardization:run` or UI).
3. Materialize (`imports:materialize` or UI).
4. Refresh statistics: `php artisan stats:refresh --all`.

## Phase 6A: Backend Prediction Engine (implemented)

See [PREDICTION_ENGINE.md](./PREDICTION_ENGINE.md). `pricing_statistics` provides:

- Baseline unit price ranges (avg, median, weighted avg, min/max).
- Market trend direction and `trend_pct` for scenario adjustments.
- `award_count` and `distinct_winners_count` for confidence / competition context.
- `top_winner_company_id` for competitor-aware recommendations.
- `stats_version` and `calculated_at` for cache invalidation and audit snapshots (`prediction_context_snapshots`).

Outlier flags inform review UX; prediction logic may down-weight flagged bids without auto-excluding them unless operators set `excluded_from_stats`.
