# Backend Prediction Engine (Phase 6A)

Phase 6A delivers a **deterministic, explainable, backend-only** tender price recommendation engine. It consumes `pricing_statistics`, `bid_records`, and `outlier_flags` from Phase 5. **No OpenAI, no AI narrative, no accuracy learning.**

Phase 6C will add OpenAI only to explain or refine narratives; the backend continues to own the numbers.

## Scope

| In scope | Out of scope |
|----------|----------------|
| Recommended price + 3 scenarios | OpenAI / any AI API |
| Confidence & risk scoring | AI-generated narrative |
| Quantity adjustment | Prediction accuracy learning |
| Geographic fallback (country → region → global) | Frontend redesign |
| Auditable storage (`predictions`, `prediction_calculations`, `prediction_scenarios`, `prediction_context_snapshots`) | Replacing static demo UI (hidden, preserved) |

## Prerequisites (local DB)

If `pricing_statistics` is empty:

1. Upload import file  
2. Run standardization  
3. Materialize entities  
4. `php artisan stats:refresh`  
5. `php artisan predictions:generate --drug=ID --country=ID --quantity=NUMBER`

## Services

| Service | Role |
|---------|------|
| `PredictionOrchestratorService` | Validates input, creates prediction, coordinates pipeline, marks completed/failed |
| `PredictionCalculationService` | Resolves stats, applies formula, trend & quantity adjustments |
| `PricingStatsResolver` | Country → region → global fallback |
| `QuantityAdjustmentService` | Median historical quantity comparison |
| `PredictionConfidenceService` | 0–100 score from data quality signals |
| `PredictionRiskService` | low / medium / high from confidence, variance, outliers, fallback |
| `PredictionScenarioService` | aggressive / balanced / conservative prices |
| `PredictionContextBuilderService` | Compact JSON context + `prediction_context_snapshots` row |

## Recommended price formula

```text
base_price =
  weighted_avg_unit_price × 0.40
+ median_unit_price       × 0.30
+ last_unit_price         × 0.20
+ avg_unit_price          × 0.10
```

Missing components are dropped; remaining weights are **renormalized** proportionally. If no usable prices exist, prediction status = `failed`.

**Trend adjustment** (capped at 7%):

- `rising` → multiply by `1 + min(|trend_pct|, 7%)`
- `falling` → multiply by `1 − min(|trend_pct|, 7%)`
- `stable` / `unknown` → no change

**Quantity adjustment**:

```text
final = trend_adjusted_price × quantity_factor
```

| Condition | Factor |
|-----------|--------|
| Quantity missing | 1.00 |
| Requested ≥ 2× median historical | 0.97 |
| Requested ≤ 0.5× median historical | 1.03 |
| Otherwise | 1.00 |

## Fallback strategy

1. `standardized_drug_id` + `country_id` (country-level row)  
2. Same drug + `region_id` (country null)  
3. Same drug global (country and region null)  
4. None → failed prediction  

`fallback_level` is stored in `calculation_details` and context snapshot.

## Confidence scoring (cap 100)

| Signal | Points |
|--------|--------|
| award_count ≥ 10 | +30 |
| award_count 5–9 | +20 |
| award_count 2–4 | +10 |
| Recent award (≤ 24 months) | +15 |
| Stable trend | +15 |
| Partial trend data | +8 |
| Low variance (CV ≤ 15%) | +15 |
| No outliers | +10 |
| Country-level stats | +10 |
| Quantity provided | +5 |
| ≥ 3 distinct winners | +5 |

## Risk scoring

Risk points from: low confidence, high variance, low award count, outliers, non-country fallback. Mapped to **low** / **medium** / **high**.

## Scenario logic

| Scenario | Base rule | Adjustments |
|----------|-----------|-------------|
| Balanced | Backend recommended price | Recommended strategy |
| Aggressive | × 0.97 | Falling trend → × 0.95; high competition → slightly lower |
| Conservative | × 1.03 | Rising trend → × 1.05 |

`source` = `backend_template` on scenarios; prediction `source` = `backend_only`.

## HTTP routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/ai/recommendations/create` | `ai.recommendations.create` |
| POST | `/ai/recommendations` | `ai.recommendations.store` |
| GET | `/ai/recommendations/{prediction}` | `ai.recommendations.show` |

`Prediction` route key: `uuid`.

## CLI

```bash
php artisan predictions:generate --drug=1 --country=1 --quantity=10000 --user=1
```

## Phase 6B: Prediction UI & history (implemented)

See [PREDICTION_UI.md](./PREDICTION_UI.md) for page-level documentation.

| Route | Page |
|-------|------|
| `ai.recommendations.create` | Form with real drugs, countries, tenders; stats awareness; empty states |
| `ai.recommendations.store` | POST → backend prediction → redirect to show or back with error |
| `ai.recommendations.show` | Full result: scenarios, calculation, context snapshot |
| `predictions.index` | Paginated history with search and filters |
| `predictions.show` | Redirects to `ai.recommendations.show` |

**No OpenAI** in UI layer. Source badge shows `backend_only`. Filters: search, status, risk, source, date range.

## Phase 6C (future)

OpenAI may:

- Rewrite rationale for business users  
- Add narrative explanations from `prediction_context_snapshots`  

It must **not** replace `backend_recommended_price` unless explicitly designed in a later phase with guardrails.

## Model version

`prediction.calculation_model_version` from `settings` (default `v1.0`), stored on prediction and calculation rows.
