@props(['pipeline'])

@php
    $statusStyles = [
        'completed' => [
            'circle' => 'bg-emerald-500 text-white border-emerald-500',
            'label' => 'text-emerald-700',
            'connector' => 'bg-emerald-300',
        ],
        'current' => [
            'circle' => 'bg-primary text-white border-primary ring-4 ring-primary/20',
            'label' => 'text-foreground font-semibold',
            'connector' => 'bg-primary/30',
        ],
        'upcoming' => [
            'circle' => 'bg-white text-muted-foreground border-border/60',
            'label' => 'text-muted-foreground',
            'connector' => 'bg-border/40',
        ],
        'failed' => [
            'circle' => 'bg-red-500 text-white border-red-500',
            'label' => 'text-red-700 font-semibold',
            'connector' => 'bg-red-200',
        ],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl border border-border/50 shadow-sm p-5']) }}>
    <div class="flex items-center justify-between gap-3 mb-4">
        <div>
            <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Import Pipeline</p>
            <p class="text-sm font-semibold text-foreground mt-0.5">
                @if ($pipeline['is_complete'])
                    Pipeline complete
                @else
                    Current stage: {{ collect($pipeline['steps'])->firstWhere('status', 'current')['label'] ?? ucfirst(str_replace('_', ' ', $pipeline['current_stage'])) }}
                @endif
            </p>
        </div>
        @if ($pipeline['is_complete'])
            <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">Complete</span>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        @foreach ($pipeline['steps'] as $index => $step)
            @php
                $styles = $statusStyles[$step['status']] ?? $statusStyles['upcoming'];
            @endphp
            <div class="relative">
                @if ($index < count($pipeline['steps']) - 1)
                    <div class="hidden md:block absolute top-4 left-[calc(50%+1.25rem)] right-0 h-0.5 {{ $styles['connector'] }}"></div>
                @endif
                <div class="relative flex flex-col items-start md:items-center text-left md:text-center gap-2">
                    <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-[11px] font-bold shrink-0 {{ $styles['circle'] }}">
                        @if ($step['status'] === 'completed')
                            ✓
                        @elseif ($step['status'] === 'failed')
                            !
                        @else
                            {{ $index + 1 }}
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs {{ $styles['label'] }}">{{ $step['label'] }}</p>
                        @if (! empty($step['detail']))
                            <p class="text-[10px] text-muted-foreground mt-0.5 leading-snug">{{ $step['detail'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
