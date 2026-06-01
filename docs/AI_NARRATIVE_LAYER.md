# AI Narrative Layer

## Architecture

Phase 6C activates OpenAI as an optional explanation layer for completed backend predictions. Backend services still calculate recommended price, scenarios, confidence, risk, fallback scope, and pricing statistics. The AI layer runs after those records are committed.

Core classes:

- `App\Services\AI\OpenAIService` reads encrypted OpenAI settings, sends Laravel HTTP chat completion requests, parses responses, and logs safe usage metadata.
- `App\Services\AI\PredictionNarrativeService` builds a strict narrative prompt from real prediction records, context snapshots, calculations, and scenarios.
- `App\Services\Prediction\PredictionOrchestratorService` calls the narrative service only after a prediction completes successfully.
- `GET /ai/recommendations/create` shows whether the API key is configured and whether narrative generation is enabled.
- `POST /ai/recommendations` can opt into narrative generation with `generate_ai_narrative=1`; otherwise the backend prediction is created without an AI call.

## Safety Boundaries

AI never replaces or recalculates:

- `backend_recommended_price`
- pricing statistics
- calculation records
- confidence score
- risk level
- scenario prices

AI may only explain the backend result. If OpenAI fails, the prediction remains completed and the UI shows a safe unavailable/skipped notice.

## OpenAI Flow

1. Laravel verifies `ai.enable_narrative`.
2. The encrypted `ai.api_key` is read through `SettingsService`.
3. The prediction must be completed and meet `ai.narrative_min_confidence` (default `50`).
4. Real `prediction_context_snapshots`, `prediction_calculations`, and `prediction_scenarios` are summarized into a bounded prompt.
5. OpenAI returns concise narrative text.
6. The narrative is stored on `predictions` with model, token, timestamp, and latency metadata.
7. `ai_usage_logs` records success/failure, model, tokens, latency, feature, user, and prediction.

The recommendation create page only exposes the **Generate AI Narrative** toggle when both conditions are true:

- an encrypted OpenAI API key exists
- `ai.enable_narrative` is enabled

If the toggle is off, the orchestrator still creates the backend prediction and records a skipped narrative status. This allows users to keep backend prediction primary even when AI is configured.

## Fallback Behavior

Narrative generation is fail-safe:

- Missing API key: skipped.
- Disabled setting: skipped.
- User did not request narrative from the create page: skipped.
- Low confidence: skipped.
- Incomplete prediction context: skipped.
- Timeout, rate limit, empty response, or API error: prediction still succeeds; narrative is unavailable.

Raw API keys and request bodies are never logged.

## Prompt Rules

The system prompt instructs the model to act as a Pharmaceutical Tender Pricing Intelligence Analyst:

- backend calculations are authoritative
- never modify prices
- explain only
- use only supplied data
- state weak data quality or broad fallback clearly
- avoid guarantees and hallucinated market claims
- keep output professional, concise, and analytical

Production narratives are capped through `ai.max_tokens` and further limited for economical usage.

## Cost Awareness

`OpenAIService` uses the configured default model and caps narrative generation to a small token budget. `ai_usage_logs.estimated_cost_usd` is populated for known OpenAI models when token usage is returned.

## Manual Test

Admins can run:

`POST /settings/ai/test-narrative`

The route uses a harmless mock pricing payload and records usage under `prediction_narrative_test`. It verifies key access, request parsing, and response handling without using real prediction calculations.

For the full create prediction path:

1. Complete the pricing pipeline: Upload data, Standardize, Materialize, Refresh statistics.
2. Open `/ai/recommendations/create`.
3. Confirm the Readiness Checklist shows standardized drugs, countries, and pricing statistics.
4. If AI is configured, optionally check **Generate AI Narrative**.
5. Submit a drug, country, quantity, unit, and optional tender.
6. Confirm the result page shows the backend recommended price, scenarios, confidence, risk, calculation breakdown, context summary, and either the AI narrative or a backend-only notice.

Common production failure states should never produce a 500. Missing statistics redirect back to create with a clear error. AI failures mark the narrative unavailable and leave the backend prediction completed.
