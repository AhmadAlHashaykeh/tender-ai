@extends('layouts.app')

@section('title', 'Product Matching | TenderAI')

@section('content')
<main class="standardization-view fadeIn" id="product-matching-app">

    <div class="standardization-container">
        <header class="standard-page-header">
            <div class="header-title-group">
                <h2 class="page-title">Product Matching</h2>
                <p class="page-subtitle">Review and approve product matches in bulk — process hundreds of items in minutes</p>
            </div>
            <div class="header-actions">
                @if ($filters['batch'] && ($filters['status'] ?? '') === 'review_required' && $batchReviewRequiredCount > 0)
                    <form
                        method="POST"
                        action="{{ route('standardization.approve-all-review', $filters['batch']) }}"
                        id="approve-all-review-form"
                        class="approve-all-form"
                    >
                        @csrf
                        <button type="button" class="btn-approve-all" id="approve-all-review-btn" data-count="{{ $batchReviewRequiredCount }}">
                            <i data-lucide="check-check" class="icon-sm"></i>
                            Approve All Review Items ({{ number_format($batchReviewRequiredCount) }})
                        </button>
                    </form>
                @endif
                @if ($filters['batch'])
                    <form method="POST" action="{{ route('standardization.run-batch', $filters['batch']) }}">
                        @csrf
                        <button type="submit" class="btn-standardize">
                            <i data-lucide="play" class="icon-sm"></i>
                            Run Standardization
                        </button>
                    </form>
                @endif
            </div>
        </header>

        @if (session('success'))
            <div class="review-alert review-alert--success" role="status">{{ session('success') }}</div>
        @endif

        {{-- Review Summary Panel --}}
        <section class="review-summary-grid" aria-label="Review summary">
            <x-stat-card label="Total Items" :value="number_format($summary['total'])" icon="layers" />
            <x-stat-card label="High Confidence" :value="number_format($summary['high_confidence'])" icon="trending-up" tone="emerald" />
            <x-stat-card label="Medium Confidence" :value="number_format($summary['medium_confidence'])" icon="minus" tone="cyan" />
            <x-stat-card label="Low Confidence" :value="number_format($summary['low_confidence'])" icon="alert-triangle" tone="amber" />
            <x-stat-card label="Approved Today" :value="number_format($summary['approved_today'])" icon="check-circle" tone="emerald" />
            <x-stat-card label="Rejected Today" :value="number_format($summary['rejected_today'])" icon="x-circle" tone="red" />
            <x-stat-card label="Pending Review" :value="number_format($summary['pending_review'])" icon="clock" tone="violet" />
        </section>

        {{-- Filters --}}
        <section class="standard-card review-filters-card">
            <div class="standard-card-header">
                <h4 class="standard-card-title">Search &amp; Filters</h4>
                <p class="standard-card-subtitle">Narrow the queue before bulk approval</p>
            </div>
            <div class="standard-card-content">
                <form method="GET" action="{{ route('standardization.index') }}" class="review-filter-form">
                    <div class="review-filter-grid">
                        <div class="review-filter-field review-filter-field--wide">
                            <label for="filter-product">Product Name</label>
                            <input type="text" id="filter-product" name="product" value="{{ $filters['product'] }}" placeholder="Product, INN, or code">
                        </div>
                        <div class="review-filter-field">
                            <label for="filter-country">Country</label>
                            <input type="text" id="filter-country" name="country" value="{{ $filters['country'] }}" placeholder="Country">
                        </div>
                        <div class="review-filter-field">
                            <label for="filter-company">Company</label>
                            <input type="text" id="filter-company" name="company" value="{{ $filters['company'] }}" placeholder="Company or winner">
                        </div>
                        <div class="review-filter-field">
                            <label for="filter-tender">Tender</label>
                            <input type="text" id="filter-tender" name="tender" value="{{ $filters['tender'] }}" placeholder="Tender number">
                        </div>
                        <div class="review-filter-field">
                            <label for="filter-batch">Batch</label>
                            <select id="filter-batch" name="batch">
                                <option value="">All batches</option>
                                @foreach ($batches as $b)
                                    <option value="{{ $b->id }}" @selected($filters['batch'] == $b->id)>#{{ $b->id }} — {{ Str::limit($b->original_filename, 40) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="review-filter-field">
                            <label for="filter-status">Review Status</label>
                            <select id="filter-status" name="status">
                                @foreach (['review_required', 'approved', 'auto_approved', 'rejected', 'pending', 'skipped'] as $s)
                                    <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ str_replace('_', ' ', ucfirst($s)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="review-filter-field">
                            <label for="filter-confidence-min">Confidence Min</label>
                            <input type="number" id="filter-confidence-min" name="confidence_min" min="0" max="100" value="{{ $filters['confidence_min'] }}" placeholder="0">
                        </div>
                        <div class="review-filter-field">
                            <label for="filter-confidence-max">Confidence Max</label>
                            <input type="number" id="filter-confidence-max" name="confidence_max" min="0" max="100" value="{{ $filters['confidence_max'] }}" placeholder="100">
                        </div>
                        <div class="review-filter-field">
                            <label for="filter-date-from">Date From</label>
                            <input type="date" id="filter-date-from" name="date_from" value="{{ $filters['date_from'] }}">
                        </div>
                        <div class="review-filter-field">
                            <label for="filter-date-to">Date To</label>
                            <input type="date" id="filter-date-to" name="date_to" value="{{ $filters['date_to'] }}">
                        </div>
                        <div class="review-filter-field">
                            <label for="filter-per-page">Per Page</label>
                            <select id="filter-per-page" name="per_page">
                                @foreach ($perPageOptions as $option)
                                    <option value="{{ $option }}" @selected($filters['per_page'] == $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="review-filter-field review-filter-field--checkbox">
                            <label class="review-checkbox-label">
                                <input type="checkbox" name="manual_only" value="1" @checked($filters['manual_only'])>
                                Manual review only
                            </label>
                        </div>
                    </div>
                    <div class="review-filter-actions">
                        <button type="submit" class="btn-standardize">Apply Filters</button>
                        <a href="{{ route('standardization.index') }}" class="review-btn review-btn--ghost">Reset</a>
                    </div>
                </form>
            </div>
        </section>

        {{-- Bulk Toolbar --}}
        <section class="review-bulk-toolbar" aria-label="Bulk actions">
            <div class="review-bulk-toolbar__selection">
                <label class="review-checkbox-label">
                    <input type="checkbox" id="select-all-visible" data-select-all-visible>
                    Select Visible
                </label>
                <button type="button" class="review-btn review-btn--ghost review-btn--sm" data-select-all>Select All (page)</button>
                <button type="button" class="review-btn review-btn--ghost review-btn--sm" data-clear-selection>Clear Selection</button>
                <span class="review-selection-count">Selected: <strong data-selected-count>0</strong> items</span>
            </div>

            <div class="review-bulk-toolbar__confidence">
                <span class="review-bulk-toolbar__label">Quick select:</span>
                <button type="button" class="review-confidence-chip review-confidence-chip--high" data-select-confidence="95">≥ 95%</button>
                <button type="button" class="review-confidence-chip review-confidence-chip--medium-high" data-select-confidence="90">≥ 90%</button>
                <button type="button" class="review-confidence-chip review-confidence-chip--medium" data-select-confidence="80">≥ 80%</button>
            </div>

            <div class="review-bulk-toolbar__actions">
                <select id="bulk-action-select" class="review-bulk-select" data-bulk-action-select>
                    <option value="">Bulk Actions</option>
                    <option value="approve">Approve Selected</option>
                    <option value="reject">Reject Selected</option>
                    <option value="send_to_review">Send Selected Back To Review</option>
                    <option value="manual_review">Mark As Manual Review</option>
                </select>
                <button type="button" class="btn-standardize review-btn--sm" data-apply-bulk disabled>Apply</button>
            </div>
        </section>

        <p class="review-keyboard-hint">
            Keyboard: <kbd>A</kbd> Approve focused · <kbd>R</kbd> Reject · <kbd>N</kbd> Next · Shift+click to multi-select
        </p>

        {{-- Review Cards --}}
        <section class="review-cards-list" aria-label="Review queue" data-review-list>
            @forelse ($rows as $row)
                <x-standardization.review-card :row="$row" :review-service="$reviewService" />
            @empty
                <div class="review-empty-state">
                    <i data-lucide="inbox" class="review-empty-state__icon"></i>
                    <h3>No items match your filters</h3>
                    <p>Adjust filters or run standardization on an import batch to populate the review queue.</p>
                </div>
            @endforelse
        </section>

        @if ($rows->hasPages())
            <nav class="review-pagination" aria-label="Review queue pagination">
                {{ $rows->links() }}
            </nav>
        @endif

        <p class="review-results-meta">
            Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }} items
        </p>
    </div>

    {{-- Manual Edit Modal --}}
    <dialog class="review-modal" id="manual-edit-modal">
        <form method="dialog" class="review-modal__inner" id="manual-edit-form">
            <header class="review-modal__header">
                <h3>Manual Match Correction</h3>
                <button type="button" class="review-modal__close" data-close-modal aria-label="Close">
                    <i data-lucide="x" class="icon-sm"></i>
                </button>
            </header>
            <div class="review-modal__body">
                <input type="hidden" name="row_id" id="edit-row-id">
                <input type="hidden" name="entity" id="edit-entity" value="drug">

                <div class="review-modal__tabs">
                    <button type="button" class="review-modal__tab review-modal__tab--active" data-edit-tab="drug">Product</button>
                    <button type="button" class="review-modal__tab" data-edit-tab="company">Company</button>
                </div>

                <div class="review-filter-field">
                    <label for="edit-search">Search products or aliases</label>
                    <input type="search" id="edit-search" placeholder="Type to search..." autocomplete="off">
                </div>

                <ul class="review-search-results" id="edit-search-results" role="listbox"></ul>

                <input type="hidden" id="edit-selected-id" name="selected_id">
                <p class="review-modal__selection" id="edit-selected-label">No match selected</p>
            </div>
            <footer class="review-modal__footer">
                <button type="button" class="review-btn review-btn--ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn-standardize" id="edit-save-btn" disabled>Save Correction</button>
            </footer>
        </form>
    </dialog>
</main>
@endsection

@push('scripts')
    <script>
        window.productMatchingConfig = {
            bulkActionUrl: @json(route('standardization.bulk-action')),
            approveUrlTemplate: @json(route('standardization.approve-row', ['row' => '__ROW__'])),
            rejectUrlTemplate: @json(route('standardization.reject-row', ['row' => '__ROW__'])),
            editMatchUrlTemplate: @json(route('standardization.edit-match', ['row' => '__ROW__'])),
            searchProductsUrl: @json(route('standardization.search-products')),
            searchCompaniesUrl: @json(route('standardization.search-companies')),
            csrfToken: @json(csrf_token()),
        };
    </script>
    @vite(['resources/js/pages/product-matching.js'])
@endpush
