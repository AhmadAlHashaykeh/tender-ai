@props(['row', 'reviewService'])

@php
    $std = $row->normalized_data['standardization'] ?? [];
    $reviewItems = $std['review_items'] ?? [];
    $primary = $reviewService::primaryReviewItem($row);
    $overall = (float) ($row->confidence_score ?? 0);
    $drugStd = $std['drug'] ?? [];
    $companyStd = $std['company'] ?? [];
    $countryStd = $std['country'] ?? [];

    $originalProduct = $primary['original'] ?? $row->raw_product_name ?? $row->raw_inn ?? '—';
    $suggestedProduct = $primary['suggested']
        ?? $drugStd['display_name']
        ?? ($row->standardizedDrug?->display_name)
        ?? '—';
    $primaryConfidence = (float) ($primary['confidence'] ?? $row->drug_confidence ?? $overall);
    $primaryReason = $primary['reason'] ?? 'Match requires manual review';

    $companyLabel = $companyStd['canonical_name'] ?? $row->company?->name ?? $row->raw_company_name ?? $row->raw_winner ?? '—';
    $countryLabel = $countryStd['canonical_name'] ?? $countryStd['normalized_name'] ?? $row->raw_country ?? '—';
    $tenderLabel = $row->raw_tender_number ?? '—';
    $isManual = ! empty($std['manual_review']);
    $canAct = $row->standardization_status === 'review_required';
    $detailsId = 'review-details-'.$row->id;
@endphp

<article
    class="review-card"
    data-review-card
    data-row-id="{{ $row->id }}"
    data-confidence="{{ $overall }}"
    data-status="{{ $row->standardization_status }}"
    tabindex="0"
>
    <div class="review-card__select">
        <input
            type="checkbox"
            class="review-card__checkbox"
            data-row-select
            value="{{ $row->id }}"
            aria-label="Select row {{ $row->row_number }}"
        >
    </div>

    <div class="review-card__body">
        <header class="review-card__header">
            <div class="review-card__header-main">
                <span class="review-card__batch">Batch #{{ $row->import_batch_id }} · Row {{ $row->row_number }}</span>
                @if ($isManual)
                    <span class="review-card__flag">Manual review</span>
                @endif
                <span class="review-card__status review-card__status--{{ str_replace('_', '-', $row->standardization_status) }}">
                    {{ str_replace('_', ' ', ucfirst($row->standardization_status)) }}
                </span>
            </div>
            <x-standardization.confidence-badge :score="$overall" class="review-card__confidence" />
        </header>

        <div class="review-card__comparison">
            <div class="review-card__column">
                <span class="review-card__column-label">Original Product</span>
                <p class="review-card__value review-card__value--original">{{ $originalProduct }}</p>
                @if ($row->raw_inn || $row->raw_code)
                    <p class="review-card__meta">INN: {{ $row->raw_inn ?? '—' }} · Code: {{ $row->raw_code ?? '—' }}</p>
                @endif
            </div>

            <div class="review-card__arrow" aria-hidden="true">
                <i data-lucide="arrow-right" class="icon-sm"></i>
            </div>

            <div class="review-card__column">
                <span class="review-card__column-label">Suggested Match</span>
                <p class="review-card__value review-card__value--suggested">{{ $suggestedProduct }}</p>
                <x-standardization.confidence-badge :score="$primaryConfidence" :show-bar="false" class="review-card__item-confidence" />
            </div>
        </div>

        <div class="review-card__reason">
            <span class="review-card__reason-label">Reason</span>
            <p>{{ $primaryReason }}</p>
        </div>

        <div class="review-card__context">
            <div class="review-card__context-item">
                <span class="review-card__context-label">Country</span>
                <span>{{ $countryLabel }}</span>
            </div>
            <div class="review-card__context-item">
                <span class="review-card__context-label">Company</span>
                <span>{{ $companyLabel }}</span>
            </div>
            <div class="review-card__context-item">
                <span class="review-card__context-label">Tender</span>
                <span>{{ $tenderLabel }}</span>
            </div>
            <div class="review-card__context-item">
                <span class="review-card__context-label">Last Updated</span>
                <span>{{ $row->updated_at?->format('M j, Y g:i A') ?? '—' }}</span>
            </div>
        </div>

        <div class="review-card__details" id="{{ $detailsId }}" hidden>
            <h4 class="review-card__details-title">All match details</h4>
            @forelse ($reviewItems as $item)
                <div class="review-card__detail-row">
                    <span class="review-card__detail-entity">{{ ucfirst($item['entity'] ?? 'item') }}</span>
                    <div class="review-card__detail-comparison">
                        <span>{{ $item['original'] ?? '—' }}</span>
                        <i data-lucide="arrow-right" class="icon-xs"></i>
                        <strong>{{ $item['suggested'] ?? '—' }}</strong>
                    </div>
                    <x-standardization.confidence-badge :score="(float) ($item['confidence'] ?? 0)" :show-bar="false" />
                    @if (! empty($item['reason']))
                        <p class="review-card__detail-reason">{{ $item['reason'] }}</p>
                    @endif
                </div>
            @empty
                <p class="text-muted text-sm">No entity-level review flags. Overall confidence triggered review.</p>
            @endforelse

            <div class="review-card__scores">
                <span>Drug {{ number_format((float) $row->drug_confidence, 0) }}%</span>
                <span>Company {{ number_format((float) $row->company_confidence, 0) }}%</span>
                <span>Tender {{ number_format((float) $row->tender_confidence, 0) }}%</span>
            </div>
        </div>

        <footer class="review-card__actions">
            @if ($canAct)
                <button type="button" class="review-btn review-btn--approve" data-action="approve" data-row-id="{{ $row->id }}">
                    <i data-lucide="check" class="icon-xs"></i> Approve
                </button>
                <button type="button" class="review-btn review-btn--reject" data-action="reject" data-row-id="{{ $row->id }}">
                    <i data-lucide="x" class="icon-xs"></i> Reject
                </button>
                <button type="button" class="review-btn review-btn--edit" data-action="edit" data-row-id="{{ $row->id }}" data-entity="drug">
                    <i data-lucide="pencil" class="icon-xs"></i> Manual Edit
                </button>
            @endif
            <button type="button" class="review-btn review-btn--ghost" data-action="toggle-details" aria-expanded="false" aria-controls="{{ $detailsId }}">
                <i data-lucide="chevron-down" class="icon-xs"></i> Expand Details
            </button>
        </footer>
    </div>
</article>
