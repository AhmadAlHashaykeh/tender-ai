@props(['status'])

@php
    $normalized = strtolower((string) $status);
    $classes = match ($normalized) {
        'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'failed' => 'bg-red-50 text-red-700 border-red-200',
        'processing' => 'bg-blue-50 text-blue-700 border-blue-200',
        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
        default => 'bg-slate-50 text-slate-600 border-slate-200',
    };
    $label = ucfirst(str_replace('_', ' ', $normalized));
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border {$classes}"]) }}>
    {{ $label }}
</span>
