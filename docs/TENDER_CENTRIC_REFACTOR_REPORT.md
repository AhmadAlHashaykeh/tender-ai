# Tender-Centric Recommendation Refactor — Implementation Report

**Date:** 2026-05-31  
**Objective:** Transform TenderAI from a generic drug pricing calculator into a tender-focused recommendation platform without changing pricing engine integrity.

---

## 1. Files Modified

### Backend
| File | Change |
|------|--------|
| `app/Http/Requests/StorePredictionRequest.php` | Tender required; country auto-derived; business-friendly validation messages |
| `app/Http/Controllers/AIRecommendationController.php` | Tender-first readiness; region eager-load; tender context passed to show view |
| `app/Services/Prediction/PredictionOrchestratorService.php` | Service-layer validation; tender-aware rationale |
| `app/Services/Prediction/PredictionContextBuilderService.php` | Tender metadata in snapshots; tender-specific awards hook |
| `app/Services/Prediction/TenderRecommendationContextService.php` | **New** — tender snapshot, country resolution, tender-scoped awards |
| `app/Enums/RecommendationMode.php` | Updated mode descriptions |
| `app/Console/Commands/GeneratePredictionCommand.php` | Requires `--tender` and `--quantity` |

### Views
| File | Change |
|------|--------|
| `resources/views/ai/recommendations/create.blade.php` | Tender-first form; auto country/region; frontend validation; mode copy |
| `resources/views/ai/recommendations/show.blade.php` | Prominent tender banner; backward-compat message; updated mode notices |

### Tests
| File | Change |
|------|--------|
| `tests/Support/CreatesTenderRecommendations.php` | **New** shared test helper |
| `tests/Feature/PredictionEngineTest.php` | All flows include tender |
| `tests/Feature/PredictionUiTest.php` | Tender validation, country derivation, banner, legacy fallback tests |
| `tests/Feature/AINarrativeLayerTest.php` | Tender in orchestrator calls |
| `tests/Feature/TenderRecommendationContextTest.php` | **New** context snapshot tests |

### Documentation
| File | Purpose |
|------|---------|
| `docs/MEDIAN_AUDIT_REPORT.md` | Median weight analysis (Task 6) |
| `docs/OUTCOME_TRACKING_PROPOSAL.md` | Future outcome tracking architecture (Task 8) |
| `docs/TENDER_CENTRIC_REFACTOR_REPORT.md` | This report (Task 10) |

---

## 2. Validation Changes

| Layer | Tender | Quantity | Country |
|-------|--------|----------|---------|
| **Frontend (JS)** | Required before submit | Required, numeric, > 0 | Auto from tender |
| **Form Request** | `required\|exists:tenders,id` | `required\|numeric\|gt:0` | Auto-merged from tender in `prepareForValidation()` |
| **Custom validator** | — | Business messages | Must match tender country |
| **Orchestrator** | Throws if missing | Throws if missing/invalid | Resolved from tender |

**Example messages:**
- ❌ "The quantity field is required."  
- ✅ "Please enter the required tender quantity."

---

## 3. UI Changes

### Create page (`/ai/recommendations/create`)
- Field order: **Tender → Drug → Quantity** (country removed as manual select)
- Country/region displayed read-only from selected tender
- Empty state when no tenders available
- Analysis mode descriptions updated with AI disclaimer
- Submit button: "Generate Tender Recommendation"
- Client-side validation before POST

### Show page (`/ai/recommendations/{uuid}`)
- **Tender Recommendation Context** banner at top with: name, number, country, quantity, analysis method, region
- Legacy predictions without tender: "Tender details are unavailable for older recommendations."
- Updated Business Calculation / AI-Assisted notices

---

## 4. Recommendation Flow — Before vs After

### Before
```
Drug + Country + Quantity + Tender (optional) → Recommendation
```
- Country manually selected
- Tender stored but not required
- Context snapshots lacked tender metadata
- Generic pricing tool UX

### After
```
Tender + Drug + Quantity → Tender-Specific Recommendation
```
- Tender **required** — defines market geography
- Country **auto-derived** from tender (read-only)
- Context snapshots include `tender_context`, `tender_specific_awards`, `tender_stats_availability`
- Pricing formula **unchanged** — same weighted blend, trend, quantity adjustments

---

## 5. Analysis Mode Improvements

| Mode | New Description |
|------|-----------------|
| **Business Calculation** | Uses historical tender awards, market statistics, and pricing rules to calculate a recommended bid price. |
| **AI-Assisted Analysis** | Uses the same historical tender data and calculations, then adds AI-powered market interpretation. **AI never changes the calculated price.** |

Visible notice on create and show pages reinforces that AI is interpretation-only.

---

## 6. Tender Context Improvements

- `TenderRecommendationContextService` centralizes tender metadata
- Context snapshots now store:
  - `tender_context` — name, number, country, region, status
  - `tender_specific_awards` — bid records for tender + drug (when available)
  - `tender_stats_availability` — hook for future tender-level statistics
- Rationale text references tender when available
- Recent winning bids in snapshots include `tender_id`

**Not fabricated:** When tender-specific stats don't exist, country/region/global stats are used as before.

---

## 7. Median Audit Findings

See [MEDIAN_AUDIT_REPORT.md](./MEDIAN_AUDIT_REPORT.md).

**Summary:** Median at 30% provides meaningful outlier protection. **Recommendation: keep current weight.** No formula changes made.

---

## 8. Future Outcome Tracking Proposal

See [OUTCOME_TRACKING_PROPOSAL.md](./OUTCOME_TRACKING_PROPOSAL.md).

**Summary:** Extend existing `prediction_accuracy_records` with winner price, difference %, submission/outcome dates. No migration implemented.

---

## 9. Tests Added/Updated

**Added:**
- `test_form_validation_requires_tender`
- `test_form_validation_shows_business_friendly_quantity_message`
- `test_form_validation_catches_missing_quantity`
- `test_country_is_derived_from_tender_on_store`
- `test_show_page_displays_tender_context_banner`
- `test_old_prediction_without_tender_shows_fallback_message`
- `TenderRecommendationContextTest` (2 tests)

**Updated:** All store/orchestrator tests now include `tender_id` and `quantity`.

**Result:** 53 tests passing in prediction suite.

---

## 10. Remaining Gaps Before Enterprise Readiness

| Gap | Priority |
|-----|----------|
| Tender-level pricing statistics (not just country/region/global) | High |
| Outcome tracking UI and migration | High |
| Auto-link recommendations from tender detail pages | Medium |
| Tender-specific quantity benchmarks | Medium |
| API endpoint for tender geography (if SPA/mobile) | Low |
| Median weight tuning after outlier resolution metrics | Low |
| Audit trail for recommendation inputs | Medium |

---

## Backward Compatibility

✅ Existing predictions without `tender_id` continue to render  
✅ Pricing engine formula and outputs unchanged  
✅ Failed predictions still handled gracefully  
✅ AI narrative layer unaffected (price never modified)
