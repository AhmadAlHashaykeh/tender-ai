@props([
    'variant' => 'default',
])

@php
    $classes = match ($variant) {
        'success' => 'badge badge-success',
        'warning' => 'badge badge-warning',
        'danger' => 'badge badge-danger',
        'info' => 'badge badge-info',
        default => 'badge',
    };
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
