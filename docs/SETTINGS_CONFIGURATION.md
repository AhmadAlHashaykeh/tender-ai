# Settings Configuration

TenderAI stores application configuration in the `settings` table (grouped key/value records with typed values). The `/settings` UI is the primary way to manage these values.

## Setting Groups

| Group | Purpose |
|-------|---------|
| `general` | Organization name, currency, date format, pagination, timezone, language |
| `prediction` | Backend prediction formula parameters (no OpenAI calls) |
| `standardization` | Auto-approval confidence thresholds and AI assist flags |
| `ai` | OpenAI provider configuration (encrypted API key) |

## Value Types

| Type | Storage | Notes |
|------|---------|-------|
| `string` | Plain text | Display strings, codes |
| `integer` | Numeric string | Thresholds, limits |
| `float` | Numeric string | Percents, multipliers |
| `boolean` | `1` / `0` | Feature flags |
| `json` | JSON string | Structured data (reserved) |
| `encrypted` | Laravel `Crypt` ciphertext | **API keys only** |

Columns: `key` (unique), `value`, `type`, `group`, `description`, `is_public`, `updated_by`, timestamps.

## General Keys

- `general.organization_name`
- `general.default_currency` (must exist in `currencies.code`)
- `general.date_format`
- `general.rows_per_page` (25, 50, or 100)
- `general.timezone`
- `general.language` (`en`, `ar`)

## Prediction Keys

- `prediction.calculation_model_version` (default `v1.0`)
- `prediction.backend_only_confidence_threshold` (default `80`)
- `prediction.trend_adjustment_cap` (default `7`)
- `prediction.aggressive_discount_percent` (default `3`)
- `prediction.conservative_premium_percent` (default `3`)
- `prediction.large_quantity_multiplier` (default `2`)
- `prediction.large_quantity_discount_percent` (default `3`)
- `prediction.small_quantity_multiplier` (default `0.5`)
- `prediction.small_quantity_premium_percent` (default `3`)

**Active now:** `PredictionCalculationService`, `PredictionScenarioService`, and `QuantityAdjustmentService` read these values.

**Reserved for Phase 6C:** OpenAI escalation when confidence is below `backend_only_confidence_threshold`.

## Standardization Keys

- `standardization.drug_auto_approve_min`
- `standardization.company_auto_approve_min`
- `standardization.row_auto_approve_min` (tender confidence in row resolution)
- `standardization.ai_auto_approve_min`
- `standardization.fuzzy_auto_approve_min` (reference; drug fuzzy logic unchanged)
- `standardization.max_ai_calls_per_batch`
- `standardization.enable_ai_assist`

**Active now:** `ImportRowStandardizationService` uses drug, company, and row (tender) thresholds from settings.

## OpenAI / AI Keys

- `ai.provider` (`openai` only)
- `ai.default_model` (default `gpt-4o-mini`)
- `ai.advanced_model`
- `ai.api_key` (**encrypted**)
- `ai.temperature` (default `0.2`)
- `ai.max_tokens` (default `800`)
- `ai.timeout_seconds` (default `60`)
- `ai.enable_narrative`
- `ai.enable_standardization_assist`
- `ai.rate_limit_per_user_per_hour` (default `10`)
- `ai.monthly_token_budget` (optional)
- `ai.system_prompt_version` (default `v1.0`)

## API Key Security

1. Keys are encrypted with Laravel `Crypt` before storage (`type = encrypted`).
2. The UI shows a **masked** value only (e.g. `sk-****abcd`), never the plaintext key.
3. Leaving the API key field empty on save **preserves** the existing key.
4. Use **Remove API Key** (`DELETE /settings/ai/api-key`) to clear the stored key.
5. Keys are **not** written to `.env` from the UI.
6. Only server-side code (`SettingsService::getEncrypted`) may decrypt.
7. Keys must not appear in Blade, JavaScript, logs, or API responses.

### Rotating an API Key

1. Open **Settings → AI Settings**.
2. Enter the new key in **API Key** and save (or remove the old key first, then save the new one).
3. Optionally run **Test OpenAI Connection** to verify.

## Routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/settings` | `settings.index` |
| PUT | `/settings/general` | `settings.general.update` |
| PUT | `/settings/prediction` | `settings.prediction.update` |
| PUT | `/settings/standardization` | `settings.standardization.update` |
| PUT | `/settings/ai` | `settings.ai.update` |
| DELETE | `/settings/ai/api-key` | `settings.ai.api-key.destroy` |
| POST | `/settings/ai/test` | `settings.ai.test` |
| POST | `/settings/users` | `settings.users.store` |
| POST | `/settings/users/{user}/toggle-status` | `settings.users.toggle-status` |

All routes require authentication (`auth`, `verified` middleware).

## Phase 6C (Not Active)

- AI prediction calls and narrative generation
- AI standardization assist execution
- Rate limit / token budget enforcement in production AI flows

Configuration can be saved now; runtime AI features will read these settings when Phase 6C ships.

## Seeding

Run `php artisan db:seed --class=SettingSeeder` (included in `DatabaseSeeder`) to restore defaults.

## Service Layer

`App\Services\Settings\SettingsService` provides typed getters, encrypted storage, masking, and bulk `updateGroup()` writes.
