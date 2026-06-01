@props([
    'label',
    'value',
    'icon' => 'activity',
    'tone' => 'primary',
])

<div {{ $attributes->merge(['class' => 'stat-card']) }}>
    <div class="flex items-center gap-2 mb-2">
        <i data-lucide="{{ $icon }}" class="w-4 h-4 text-{{ $tone }}-500/80"></i>
        <span class="text-[10px] uppercase tracking-wide text-muted-foreground font-medium">{{ $label }}</span>
    </div>
    <p class="text-xl font-bold text-foreground">{{ $value }}</p>
</div>
