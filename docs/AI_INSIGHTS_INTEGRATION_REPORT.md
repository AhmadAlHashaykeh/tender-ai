# AI Insights Integration Report

Implementation date: 2026-05-31

## Summary

AI is now an optional interpretation layer on every price recommendation. The pricing engine remains the single source of truth for price, confidence, risk, and bid scenarios. Users no longer choose between "Business Calculation" and "AI-Assisted Analysis" — one recommendation path exists, with AI insights generated automatically when configured.

---

## 1. Files Modified

| File | Change |
|------|--------|
| `app/Services/AI/PredictionNarrativeService.php` | Structured 5-section insights, caching, JSON parsing, business-language prompts |
| `app/Services/Prediction/PredictionOrchestratorService.php` | Single recommendation path; auto-generates insights after calculation |
| `app/Http/Controllers/AIRecommendationController.php` | Removed mode selection; added `regenerateInsights`; passes insight data to view |
| `app/Http/Requests/StorePredictionRequest.php` | Removed `recommendation_mode` validation |
| `routes/web.php` | Added `POST /ai/recommendations/{prediction}/regenerate-insights` |
| `resources/views/ai/recommendations/create.blade.php` | Removed analysis method radio buttons |
| `resources/views/ai/recommendations/show.blade.php` | AI Strategic Insights section, transparency notice, regenerate button |
| `tests/Feature/AINarrativeLayerTest.php` | Updated for structured insights and caching |
| `tests/Feature/PredictionUiTest.php` | Updated for unified flow and new UI |

**Unchanged (pricing engine):** `PredictionCalculationService`, scenario generation, discount logic, confidence/risk calculators.

---

## 2. AI Prompt Structure

### System prompt (role: strategist)

- Pharmaceutical tender pricing strategist
- Backend calculations are authoritative — never modify price, discount, confidence, risk, or scenarios
- Return **only valid JSON** with five keys
- Business language; avoid technical terms (narrative, prompt, backend, engine, model output)
- Each section: 2–4 sentences, professional and concise

### User prompt

Structured JSON payload grouped as:

- `tender_information`
- `pricing_information`
- `confidence`
- `market_statistics`
- `scenario_information`

Instruction: *"Generate AI strategic insights for this completed price recommendation. Return JSON only."*

---

## 3. Data Sent to AI

### Tender Information

| Field | Source |
|-------|--------|
| Tender Name | `predictions.tender.title` or context snapshot |
| Tender Number | `predictions.tender.tender_number` or context snapshot |
| Country | `predictions.tender.country.name` or context snapshot |
| Quantity | `predictions.quantity` + `quantity_unit` |

### Pricing Information

| Field | Source |
|-------|--------|
| Market Calculated Price | `predictions.market_calculated_price` or calculation details |
| User Discount | `predictions.discount_percentage` |
| Final Recommended Bid Price | `predictions.final_recommended_price` |

### Confidence

| Field | Source |
|-------|--------|
| Data Confidence | `predictions.confidence_score` |
| Risk Level | `predictions.risk_level` |

### Market Statistics

| Field | Source |
|-------|--------|
| Award Count | `prediction_calculations.historical_award_count` |
| Weighted Average | `prediction_calculations.weighted_average_price` |
| Median | `prediction_calculations.median_price` |
| Last Award Price | `prediction_calculations.last_winning_price` |
| Trend | direction + percentage from calculation |
| Distinct Winners | `context_snapshot.competition_summary.distinct_winners` |
| Competition Level | `prediction_calculations.competition_level` |

### Scenario Information

| Field | Source |
|-------|--------|
| Aggressive / Balanced / Conservative | `prediction_scenarios` (name, price, risk, is_recommended) |

All values come from database records or stored context snapshots — no fabricated numbers.

---

## 4. Data Excluded from AI

- Internal calculation weights and formula breakdowns (not required for interpretation)
- Raw OpenAI prompts or hashes (stored server-side only)
- User credentials or API keys
- Win probability as a guaranteed outcome metric
- Any ability to override calculated outputs

The AI is explicitly instructed **not** to suggest different prices, discounts, confidence scores, risk levels, or scenario prices.

---

## 5. New UI Sections

### Transparency notice (top of result page)

> The recommendation price is calculated using historical tender data and market statistics. AI insights provide additional interpretation and strategic guidance only.

### AI Strategic Insights (below recommendation summary / rationale)

Five subsections:

1. **Market Overview**
2. **Competition Analysis**
3. **Discount Review**
4. **Risk Commentary**
5. **Strategic Recommendation**

Additional UI:

- **Regenerate insights** button (when AI is configured and recommendation is completed)
- Generated timestamp when available
- Fallback message when insights are unavailable

### Removed from UI

- Analysis method radio buttons on create form
- Mode badges (Business Calculation / AI-Assisted Analysis)
- "AI Market Insight" single-block narrative
- Model name display on result page

---

## 6. Storage Strategy

Insights are stored on the `predictions` table:

| Column | Purpose |
|--------|---------|
| `ai_response_raw` (JSON) | Primary store: `insights_status`, `insights` (5 sections), `generated_at`, error/skip messages |
| `ai_narrative` (text) | Flattened copy for search/backward compatibility |
| `ai_narrative_generated_at` | Timestamp of last generation |
| `ai_model_used` | OpenAI model identifier |
| `ai_tokens_used` | Token consumption |
| `ai_response_ms` | Latency |
| `ai_prompt_hash` | Prompt integrity hash |
| `openai_called` | Whether OpenAI was invoked |

Structured insights JSON shape:

```json
{
  "insights_status": "success",
  "insights": {
    "market_overview": "...",
    "competition_analysis": "...",
    "discount_review": "...",
    "risk_commentary": "...",
    "strategic_recommendation": "..."
  },
  "generated_at": "2026-05-31T01:00:00+00:00"
}
```

---

## 7. Caching Strategy

| Trigger | Behavior |
|---------|----------|
| New recommendation created | Generate insights once after pricing completes (if AI enabled + confidence threshold met) |
| Page refresh / show view | **No regeneration** — read stored insights |
| User clicks "Regenerate insights" | `force_regenerate: true` — new OpenAI call, overwrite stored insights |
| Cached insights exist + no force flag | Return immediately with `status: cached` |

Skip conditions (no API call):

- AI disabled in settings
- No API key configured
- Prediction not completed
- Confidence below `ai.narrative_min_confidence` setting
- Incomplete context (missing calculation or snapshot)

---

## 8. Backward Compatibility

| Scenario | Handling |
|----------|----------|
| Old recommendations without AI data | Shows: *"AI insights are not available for this recommendation."* |
| Legacy `ai_narrative` text with `narrative_status: success` | Mapped to Strategic Recommendation section when structured JSON absent |
| `recommendation_mode` column | Retained in database; always set to `calculation` for new records |
| Old `ai_assisted` source values | Display unchanged in history filters; new records stay `backend_only` |
| Failed/skipped AI | Page renders fully; insights section shows skip reason or unavailable message |

---

## 9. Example Generated Insight

**Market Overview:**  
The market for this product in Saudi Arabia shows stable pricing based on six historical awards. Data quality is moderate with country-level scope, supporting a confidence score of 72%.

**Competition Analysis:**  
Competition appears moderate with four distinct winners recorded in recent awards. Supplier diversity suggests no single dominant bidder, though award concentration has increased slightly in the last two cycles.

**Discount Review:**  
Your 5% bid discount reduces the final recommended bid below the calculated market price, improving competitive positioning. This discount preserves reasonable margin headroom relative to the weighted average award price.

**Risk Commentary:**  
Primary risks include reliance on country-level statistics rather than tender-specific history and a stable-but-unconfirmed trend direction. Limited outlier filtering was required, which adds minor uncertainty.

**Strategic Recommendation:**  
Given moderate competition and stable trends, a balanced bidding approach aligned with the recommended price is appropriate. Monitor aggressive scenario pricing if competitor intensity increases near submission.

---

## Architecture

```
Historical Tender Data + Market Statistics + Pricing Rules
                        ↓
                 Recommendation (price, confidence, risk, scenarios)
                        ↓
              AI Strategic Insights (interpretation only)
```

The AI never changes the recommendation.
