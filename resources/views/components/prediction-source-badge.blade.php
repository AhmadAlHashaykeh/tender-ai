@props(['source'])

@php
    $normalized = strtolower((string) $source);
    $classes = match ($normalized) {
        'backend_only' => 'bg-slate-100 text-slate-700 border-slate-200',
        'ai_assisted' => 'bg-violet-50 text-violet-700 border-violet-200',
        'cached' => 'bg-blue-50 text-blue-700 border-blue-200',
        'backend', 'backend_template' => 'bg-slate-100 text-slate-700 border-slate-200',
        default => 'bg-slate-50 text-slate-600 border-slate-200',
    };
    $label = match ($normalized) {
        'backend_only' => 'Calculation only',
        'ai_assisted' => 'AI insights included',
        'cached' => 'Cached',
        'backend_template' => 'Calculated scenario',
        default => ucfirst(str_replace('_', ' ', $normalized)),
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {$classes}"]) }}>
    {{ $label }}
</span>
