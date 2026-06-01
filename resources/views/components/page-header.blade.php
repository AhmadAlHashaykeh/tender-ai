@props([
    'title',
    'subtitle' => null,
    'icon' => 'layout-dashboard',
])

<div {{ $attributes->merge(['class' => 'flex items-start justify-between gap-4 flex-wrap mb-1']) }}>
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center shadow-md">
            <i data-lucide="{{ $icon }}" class="w-5 h-5 text-white"></i>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-foreground">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-sm text-muted-foreground ml-0.5">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="flex items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
