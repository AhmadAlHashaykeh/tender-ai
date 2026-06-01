# Standardization Engine (Phase 4A)

Phase 4A implements **rule-based standardization only**. No OpenAI, no AI APIs, no predictions, and no pricing statistics. Import rows remain the immutable source of truth; raw columns are never overwritten.

## Scope

| In scope | Out of scope (later phases) |
|----------|----------------------------|
| Text normalization helpers | OpenAI / AI-assisted matching |
| Country, company, drug, tender matching | `bid_records` creation |
| Exact, alias, and fuzzy matching | `tender_items` materialization |
| Confidence scoring per dimension | Pricing statistics |
| `standardization_status` on rows | Approve/reject workflow (UI placeholder only) |
| `standardization_suggestions` (rules/fuzzy) | Predictions |

## Pipeline

1. Phase 3 stores rows in `import_rows` with `validation_status` and import-time `normalized_data`.
2. `php artisan imports:standardize` runs `ImportRowStandardizationService` per row.
3. Results merge into `normalized_data.standardization` (JSON) and update confidence fields.
4. Matched entity IDs may be set on `import_rows` (`standardized_drug_id`, `company_id`) as **candidates** for Phase 4B.
5. Unmatched entities get `standardization_suggestions` with `source` = `rules` or `fuzzy`, `status` = `pending`.

## Services

| Service | Role |
|---------|------|
| `TextNormalizer` | Trim, lowercase, suffix/country/tender/drug normalization |
| `FuzzyMatcherService` | PHP `similar_text` similarity 0–100 |
| `CountryStandardizationService` | Map `raw_country` → `countries.id` |
| `CompanyStandardizationService` | Prefer `raw_company_name`, fallback `raw_winner` |
| `DrugStandardizationService` | Code, alias, INN+product, fuzzy product match |
| `TenderStandardizationService` | Tender number/year/version identity strength |
| `StandardizationSuggestionService` | Deduped suggestions via `input_hash` |
| `ImportRowStandardizationService` | Orchestrator + row/batch status |

## Confidence rules

### Drug

- Exact code: **95**
- Exact alias: **90**
- INN + similar product name: **85**
- Fuzzy product ≥ 92: **80**
- Fuzzy product 80–91: **65** (suggestion, review)
- Code + INN + product, no match: **60** + suggested payload
- Product name only: **45–60** by length
- No identity fields: **0**

### Company

- Exact `company_aliases.normalized_alias`: **95**
- Exact `companies.normalized_name`: **90**
- Company + winner same normalized value: **+5** bonus (capped 100)
- Fuzzy ≥ 90: **85**
- Fuzzy 80–89: **70**
- Name present, no match: **60** + suggestion
- Missing both fields: **0**

### Country

- Exact name: **95**
- Known alias / ISO code: **90**
- Fuzzy name ≥ 90: **80**
- Missing: **0**

### Tender

- Number + country + year: **90**
- Number + country: **75**
- Number only: **60**
- Missing number: **30** (warning)

Country confidence is stored in `normalized_data.country_confidence` (no dedicated column).

## Row status logic

**`auto_approved`** when:

- `validation_status` is `valid` or `warning`
- `drug_confidence` ≥ 85
- `company_confidence` ≥ 85
- `tender_confidence` ≥ 75
- country confidence ≥ 80

**`review_required`** when valid/warning but any threshold fails.

**`rejected`** when `invalid`, invalid Price USD, or missing required analytics fields (drug identity, country, year).

**`skipped`** for duplicate rows.

Rows are never deleted.

## Artisan command

```bash
php artisan imports:standardize
php artisan imports:standardize --batch=1
php artisan imports:standardize --limit=100
php artisan imports:standardize --only-pending
php artisan imports:standardize --dry-run
```

Batch metadata is updated with:

- `auto_approved_rows`
- `review_pending_rows`
- `standardization_rejected_rows`
- `standardization_skipped_rows`

## UI

`GET /standardization` — `StandardizationController@index` shows counts, filters, and review/auto-approved rows.

`POST /standardization/batches/{batch}/run` — standardize pending rows for a batch.

## Phase 4B (implemented)

Entity materialization is documented in [MATERIALIZATION_ENGINE.md](./MATERIALIZATION_ENGINE.md).

- Run `php artisan imports:materialize` after standardization
- Only `auto_approved` / `approved` rows are materialized
- `review_required` rows remain in queue until manually approved (future workflow)

Phase 4A prepares normalized JSON, confidence scores, candidate FKs, and pending suggestions.

## Tests

```bash
php artisan test --filter=Standardization
```

Uses `StandardizationReferenceSeeder` plus core country seeders.
