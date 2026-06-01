# Prediction UI

Blade pages for creating, viewing, and auditing backend price predictions. Backend prediction is authoritative. The OpenAI layer is optional and explanatory only.

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| GET | `/ai/recommendations/create` | `ai.recommendations.create` | `AIRecommendationController@create` |
| POST | `/ai/recommendations` | `ai.recommendations.store` | `AIRecommendationController@store` |
| GET | `/ai/recommendations/{prediction}` | `ai.recommendations.show` | `AIRecommendationController@show` |
| GET | `/predictions` | `predictions.index` | `PredictionController@index` |
| GET | `/predictions/{prediction}` | `predictions.show` | `PredictionController@show` → redirect |

`{prediction}` resolves by `uuid`.

## Pages

### Create (`/ai/recommendations/create`)

- Dropdowns: active standardized drugs, active countries, optional upcoming/historical tenders (latest 100).
- Drug labels use `Code — INN — Display Name`.
- Country labels use country name.
- Tender labels show `[Upcoming] Tender Name / Tender # / Country / Year` or `[Historical] Tender Name / Tender # / Country / Year`.
- Simple client-side filters are available above the drug, country, and tender selects. A server-side searchable select can be added later if catalogs become too large for a standard select.
- Quantity is required, numeric, and greater than zero. Quantity unit defaults to `units`.
- Optional **Generate AI Narrative** toggle is shown only when an OpenAI key is configured and `ai.enable_narrative` is enabled.
- Readiness Checklist shows standardized drugs count, countries count, pricing statistics count, AI key configured, and AI narrative enabled.
- Warnings appear when pricing statistics, standardized drugs, countries, or AI configuration are missing.
- Empty state disables submit when no drugs or countries exist.
- Link to prediction history; list of 5 recent predictions for current user.
- Required pipeline instructions are shown exactly as: `1. Upload data 2. Standardize 3. Materialize 4. Refresh statistics`.

### Result (`ai/recommendations/show`)

- Source/status badges plus a backend-only notice when AI was skipped or unavailable.
- Recommended price and backend recommended price.
- Confidence and risk.
- Three scenarios (aggressive, balanced, conservative).
- Calculation breakdown and context snapshot summary.
- AI narrative when successfully generated.
- Backend-only notice when AI is disabled, skipped, missing a key, below confidence threshold, or failed safely.
- Actions: back to history, create new prediction.

### History (`predictions/index`)

- Stats row: total, completed, failed, in progress, average confidence.
- GET filters: search (drug name / uuid), status, risk_level, source, date_from, date_to.
- Table with view link (via `predictions.show` redirect).
- Empty state with pipeline instructions.

## Blade components

| Component | Usage |
|-----------|--------|
| `x-prediction-status-badge` | processing, completed, failed, pending |
| `x-prediction-risk-badge` | low, medium, high |
| `x-prediction-source-badge` | backend_only, ai_assisted, cached |

## Create Prediction Flow

1. User opens `/ai/recommendations/create`.
2. Controller loads active standardized drugs, active countries, active/upcoming tenders, pricing statistics count, recent predictions, and AI configuration status.
3. User selects standardized drug, country, quantity, quantity unit, optional tender, and optional AI narrative.
4. `POST /ai/recommendations` validates input through `StorePredictionRequest`.
5. `PredictionOrchestratorService` creates the prediction, calculation, three scenarios, and context snapshot.
6. If backend prediction fails because no usable statistics exist, the user is redirected back with a clear error. No 500 should occur.
7. If backend prediction succeeds, optional AI narrative runs after backend records are committed.
8. User is redirected to `/ai/recommendations/{prediction}` using UUID route model binding.

## Data Prerequisites

1. Upload sample file (`/uploads`)
2. Standardize batch
3. Materialize import
4. `php artisan stats:refresh --all`
5. Open **AI Recommendation**, submit form
6. Open **Prediction History** to review entries

Without `pricing_statistics`, store returns to create with an error message (prediction row may be `failed`).

## Backend-Only vs AI Narrative

- Backend-only mode is always valid when data prerequisites exist.
- AI narrative requires an encrypted `ai.api_key`, `ai.enable_narrative=true`, and sufficient prediction confidence.
- AI never changes `recommended_price`, `backend_recommended_price`, calculations, scenarios, confidence, or risk.
- AI failure keeps the prediction completed and stores an unavailable/skipped notice for the result page.

## Common Failure Cases

- No standardized drugs: create page shows empty state and exact pipeline instructions; submit is disabled.
- No countries: create page shows empty state and exact pipeline instructions; submit is disabled.
- No pricing statistics: create page shows warning; submit is allowed so the backend can return a graceful failed prediction.
- Selected drug/country has no usable country, region, or global stats: store redirects back with a clear error.
- Invalid quantity: validation redirects back with field errors.
- AI API failure, missing API key, disabled narrative, or low confidence: backend prediction still succeeds; result page shows backend-only or AI unavailable notice.
