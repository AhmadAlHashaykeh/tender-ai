@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'chart-card card-glow']) }}>
    <div class="flex items-center justify-between gap-4 mb-4">
        <div>
            <h3 class="font-semibold text-foreground">{{ $title }}</h3>
            @if ($subtitle)
                <p class="text-xs text-muted-foreground mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            {{ $actions }}
        @endisset
    </div>
    {{ $slot }}
</div>
