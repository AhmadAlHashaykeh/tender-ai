@props([
    'score',
    'showBar' => true,
])

@php
    $score = (float) $score;
    $band = \App\Services\Standardization\StandardizationReviewService::confidenceBand($score);
    $labels = [
        'high' => '95–100%',
        'medium-high' => '80–94%',
        'medium' => '60–79%',
        'low' => 'Below 60%',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'confidence-badge confidence-badge--'.$band]) }}>
    @if ($showBar)
        <div class="confidence-badge__bar" aria-hidden="true">
            <span class="confidence-badge__fill" style="width: {{ min(100, max(0, $score)) }}%;"></span>
        </div>
    @endif
    <span class="confidence-badge__label">{{ $labels[$band] ?? '' }}</span>
    <span class="confidence-badge__value">{{ number_format($score, 0) }}%</span>
</div>
