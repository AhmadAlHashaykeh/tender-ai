@props(['status'])

@php
    $classes = match ($status) {
        'completed' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
        'completed_with_errors' => 'bg-amber-50 text-amber-600 border-amber-100',
        'failed' => 'bg-red-50 text-red-600 border-red-100',
        'queued', 'processing', 'parsing', 'validating', 'uploaded', 'awaiting_mapping' => 'bg-blue-50 text-blue-600 border-blue-100',
        default => 'bg-slate-50 text-slate-600 border-slate-100',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold border {$classes}"]) }}>
    {{ str_replace('_', ' ', ucfirst($status)) }}
</span>
