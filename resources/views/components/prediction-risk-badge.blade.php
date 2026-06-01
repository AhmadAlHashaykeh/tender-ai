@props(['risk'])

@php
    $normalized = strtolower((string) $risk);
    $classes = match ($normalized) {
        'low' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'high' => 'bg-red-50 text-red-700 border-red-200',
        'medium' => 'bg-amber-50 text-amber-700 border-amber-200',
        default => 'bg-slate-50 text-slate-600 border-slate-200',
    };
@endphp

<span {{ $attributes->merge(['class' => "px-2 py-0.5 rounded-full text-[10px] font-semibold border capitalize {$classes}"]) }}>
    {{ $normalized ?: '—' }}
</span>
