@props(['status'])

@php
    $classes = match ($status) {
        'awarded' => 'badge-status-won',
        'lost' => 'badge-status-lost',
        'participated' => 'bg-blue-50 text-blue-700 border-blue-100',
        'disqualified' => 'bg-orange-50 text-orange-700 border-orange-100',
        'cancelled' => 'bg-slate-100 text-slate-600 border-slate-200',
        default => 'bg-slate-50 text-slate-600 border-slate-100',
    };
    $isTailwind = in_array($status, ['participated', 'disqualified', 'cancelled', 'unknown'], true);
@endphp

@if ($isTailwind)
    <span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {$classes}"]) }}>
        {{ ucfirst(str_replace('_', ' ', $status)) }}
    </span>
@else
    <span {{ $attributes->merge(['class' => "badge-pill {$classes}"]) }}>
        {{ ucfirst($status) }}
    </span>
@endif
