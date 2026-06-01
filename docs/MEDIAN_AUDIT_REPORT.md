# Median Contribution Audit — TenderAI Pricing Engine

**Date:** 2026-05-31  
**Scope:** Base price formula in `PredictionCalculationService::computeBasePrice()`

---

## Current Formula

```
Base Price = 40% Weighted Average + 30% Median + 20% Last Award + 10% Average
```

When a component is missing, remaining weights are normalized proportionally.

---

## Component Analysis

| Field | Default Weight | Role | Outlier Sensitivity |
|-------|----------------|------|---------------------|
| **Weighted Average** | 40% | Primary signal; volume/recency-weighted market center | Moderate — weighting reduces but does not eliminate outlier impact |
| **Median** | 30% | Robust central tendency; ignores extreme tails | **Low** — primary outlier protection |
| **Last Award** | 20% | Recency anchor; reflects most recent market outcome | High — single data point |
| **Average** | 10% | Simple mean; secondary reference | High — easily skewed by outliers |

---

## Scenario Comparison (Illustrative)

Using test fixture values: WA=100, Median=80, Last=60, Avg=40

| Method | Calculated Base | Notes |
|--------|-----------------|-------|
| **Full blend (current)** | 80.00 | Balanced; median pulls away from WA/avg spread |
| **Without median (WA 57%, Last 29%, Avg 14%)** | 86.67 | Shifts +8.3% toward weighted average |
| **Median only** | 80.00 | Stable reference point |
| **Last award only** | 60.00 | Most aggressive; ignores broader market |
| **Weighted avg only** | 100.00 | Highest; no outlier dampening |

### Outlier stress test (hypothetical)

If one extreme award skews average to 150 while median stays at 80:

| Method | Approx. Base | vs Median-only |
|--------|--------------|----------------|
| With median (30%) | ~95 | Median anchors result |
| Without median | ~115+ | Average and WA pull price upward |

---

## Contribution Assessment

### What Median contributes today

1. **Outlier protection (30% weight):** Median is the only component explicitly resistant to extreme bids. With outlier detection flagging bad records but not always removing them from stats, median provides a safety net.
2. **Stability:** Reduces volatility when last award diverges from historical center (e.g., one-off low win).
3. **Quantity adjustment synergy:** `QuantityAdjustmentService` also uses median for historical quantity comparison — median is embedded in two pipeline stages.

### What removing/reducing median would do

| Action | Accuracy Impact | Risk |
|--------|-----------------|------|
| Remove median entirely | Likely **decrease** in markets with outlier-prone data | Higher recommended prices in skewed markets |
| Reduce to 15% | Moderate shift toward WA/last | Acceptable if outlier resolution improves |
| Keep at 30% | Maintains current balance | None identified |

---

## Recommendation

**Keep median at 30% weight for now.**

Rationale:
- Median is the primary outlier dampener in a blend that includes last award (high variance) and average (outlier-sensitive).
- Removing it would increase sensitivity to single extreme awards without a replacement robust statistic.
- The 30% weight is material but not dominant — weighted average (40%) remains the lead signal.

### Future optimization (when data quality improves)

1. **Reduce median to 20%** and increase weighted average to 45% *only after* outlier resolution rate exceeds 90% for country-level stats.
2. **Add tender-specific median** when tender-level statistics are materialized (see `TenderRecommendationContextService::tenderStatsAvailability()`).
3. **Log component divergence** in context snapshots to measure when median materially changes the base price vs WA-only.

---

## Implementation Status

No formula weights were changed in this refactor. This report informs future pricing model revisions.
