# Product Matching UX Redesign — Implementation Report

**Date:** May 31, 2026  
**Route:** `/standardization` (sidebar: **Product Matching**)  
**Status:** Implemented

---

## 1. UX Redesign Summary

The Product Matching page was rebuilt from a developer-style data table into a **business review dashboard** optimized for high-volume manual approval.

### Before
- Dense HTML table with equal visual weight for all fields
- Single-row Approve/Reject only (full page reload)
- Hard limit of 50 rows, no pagination
- Minimal filters (batch, status, confidence range)
- No bulk workflow, keyboard shortcuts, or manual correction UI

### After
- **Card-based review layout** with clear information hierarchy
- **Side-by-side comparison** (Original → Suggested)
- **Color-coded confidence bands** with progress bars
- **Bulk selection** with quick confidence filters (≥95%, ≥90%, ≥80%)
- **Bulk actions:** Approve, Reject, Send Back To Review, Mark Manual Review
- **Manual edit modal** with product/company search (including aliases)
- **Review summary panel** with aggregate counters
- **Pagination** (25/50/100 per page) for 500–5000+ items
- **Keyboard workflow:** A = Approve, R = Reject, N = Next, Shift+click multi-select
- **Responsive** card stacking on tablet/mobile

**Goal achieved:** A reviewer can select all high-confidence matches on a page and approve them in one action instead of clicking each row individually.

---

## 2. New Layout Structure

```
┌─────────────────────────────────────────────────────────────┐
│  Page Header — Product Matching + Run Standardization       │
├─────────────────────────────────────────────────────────────┤
│  Review Summary Panel (7 stat cards)                        │
│  Total | High | Medium | Low | Approved Today | Rejected | Pending │
├─────────────────────────────────────────────────────────────┤
│  Search & Filters (product, country, company, tender,       │
│  batch, status, confidence range, date range, per page)     │
├─────────────────────────────────────────────────────────────┤
│  Bulk Toolbar (sticky)                                      │
│  ☐ Select Visible | Select All | Clear | Quick ≥95/90/80%  │
│  Bulk Actions ▼ | Selected: N items                         │
├─────────────────────────────────────────────────────────────┤
│  Review Card List (paginated)                               │
│  ┌─ Card ────────────────────────────────────────────────┐  │
│  │ ☐  Batch · Status · Confidence Badge                  │  │
│  │  Original Product  →  Suggested Match                 │  │
│  │  Reason                                               │  │
│  │  Country | Company | Tender | Last Updated              │  │
│  │  [Approve] [Reject] [Manual Edit] [Expand Details]    │  │
│  └───────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────┤
│  Pagination + results meta                                  │
└─────────────────────────────────────────────────────────────┘

Manual Edit Modal (dialog)
  Tabs: Product | Company
  Search → results list → Save Correction
```

---

## 3. Components Added

| Component | Path | Purpose |
|-----------|------|---------|
| Review card | `resources/views/components/standardization/review-card.blade.php` | Side-by-side match card with actions |
| Confidence badge | `resources/views/components/standardization/confidence-badge.blade.php` | Color band + bar + percentage |
| Review page | `resources/views/standardization/index.blade.php` | Full dashboard layout |
| Page JS | `resources/js/pages/product-matching.js` | Bulk select, AJAX actions, keyboard, modal |
| Page CSS | `resources/css/pages/drug-standardization.css` | Review dashboard styles |
| Review service | `app/Services/Standardization/StandardizationReviewService.php` | Filters, pagination, summary, search |

---

## 4. Bulk Approval Workflow

```
1. User applies filters (e.g. batch + review_required + confidence ≥ 90%)
2. Clicks "≥ 90%" quick select → all visible cards above 90% checked
3. Chooses "Approve Selected" from bulk dropdown → Apply
4. POST /standardization/bulk-action { action: approve, row_ids: [...] }
5. ImportRowStandardizationService::bulkAction() processes in chunks of 100
6. Each row: status → approved, audit log with performed_by
7. Batch metadata counts refreshed
8. Page reloads with success toast
```

**Bulk actions supported:**

| Action | Effect |
|--------|--------|
| `approve` | `review_required` → `approved` |
| `reject` | `review_required` → `rejected` |
| `send_to_review` | `approved` / `auto_approved` / `rejected` → `review_required` |
| `manual_review` | Sets `normalized_data.standardization.manual_review = true`, status → `review_required` |

Single-row Approve/Reject also work via AJAX without full page reload (card animates out).

---

## 5. Performance Considerations

| Concern | Solution |
|---------|----------|
| Loading 5000+ rows | Server-side pagination (default 25, max 100 per page) |
| Summary counters | Aggregate SQL `COUNT()` queries — never load all rows for stats |
| Bulk updates | `chunkById(100)` in service layer |
| Search in modal | Debounced fetch (250ms), limit 15 results |
| N+1 queries | Eager load `importBatch`, `standardizedDrug`, `company` |
| Sticky bulk toolbar | CSS `position: sticky` for actions while scrolling cards |

**Not loaded at once:** Full review queue, all review_items JSON for entire batch, or unfiltered row set.

---

## 6. Screens Affected

| Screen | Change |
|--------|--------|
| `/standardization` | Full redesign (primary deliverable) |
| Import show — "Review Queue" link | Unchanged URL; lands on filtered review queue |
| Sidebar — Product Matching | Same route, new UI |

**Unchanged:** Settings → Standardization thresholds, import pipeline stages, materialization flow.

---

## 7. Database / API Changes

### Database
**No migrations required.** Review state continues on `import_rows`:
- `standardization_status`
- `confidence_score`, entity confidence columns
- `normalized_data.standardization.review_items[]`
- New optional flag: `normalized_data.standardization.manual_review`

Audit trail uses existing `standardization_logs.performed_by` (now populated on manual actions).

### New Routes

| Method | Path | Name |
|--------|------|------|
| POST | `/standardization/bulk-action` | `standardization.bulk-action` |
| PUT | `/standardization/rows/{row}/edit-match` | `standardization.edit-match` |
| GET | `/standardization/search/products?q=` | `standardization.search-products` |
| GET | `/standardization/search/companies?q=` | `standardization.search-companies` |

Existing routes retained: `approve-row`, `reject-row`, `run-batch`.

### Service Methods Added

`ImportRowStandardizationService`:
- `bulkAction()`, `sendBackToReview()`, `markManualReview()`, `editMatch()`
- `performed_by` on all manual audit logs
- `updateBatchCounts()` after manual actions

---

## 8. Confidence Visualization

| Band | Range | Color |
|------|-------|-------|
| High | 95–100% | Green |
| Medium-high | 80–94% | Blue |
| Medium | 60–79% | Amber |
| Low | Below 60% | Red |

Implemented in `StandardizationReviewService::confidenceBand()` and `<x-standardization.confidence-badge>`.

---

## 9. Testing

Feature tests: `tests/Feature/ProductMatchingReviewTest.php`

- Card layout + summary rendering
- Bulk approve/reject
- Manual edit (drug correction)
- Product search API
- Pagination limit
- Confidence filter

Run: `php artisan test --filter=ProductMatchingReview`

---

## 10. Follow-up Opportunities (Optional)

- **Select All matching filter** (not just visible page) via server-side ID export
- **Undo last bulk action** using audit log rollback
- **Entity-level bulk** (approve drug match but reject company) — would need per-entity review rows
- **Livewire/Inertia** for zero-reload pagination
- **WebSocket progress** when bulk processing very large selections server-side async
