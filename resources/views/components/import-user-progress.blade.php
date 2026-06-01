@props(['ux', 'batch'])

@php
    $statusStyles = [
        'completed' => 'bg-emerald-500 text-white border-emerald-500',
        'current' => 'bg-primary text-white border-primary ring-4 ring-primary/20',
        'upcoming' => 'bg-white text-muted-foreground border-border/60',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl border border-border/50 shadow-sm p-6']) }}>
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <div>
            @if ($ux['is_preparing'])
                <div class="flex items-center gap-2 text-primary mb-2">
                    <span class="inline-block w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                    <span class="text-xs font-semibold uppercase tracking-wide">Data preparation</span>
                </div>
            @endif
            <h2 class="text-xl font-bold text-foreground">{{ $ux['headline'] }}</h2>
            <p class="text-sm text-muted-foreground mt-1">{{ $ux['subline'] }}</p>
            @if ($ux['show_long_wait_hint'] ?? false)
                <p class="text-sm text-amber-700 mt-2">
                    Still processing. Large files may take longer — you can leave this page and return later.
                </p>
            @endif
        </div>
        @if ($ux['primary_action'])
            @if (($ux['primary_action']['type'] ?? 'link') === 'form')
                <form method="POST" action="{{ $ux['primary_action']['route'] }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="h-10 px-5 rounded-lg bg-primary text-white text-sm font-semibold hover:opacity-90 inline-flex items-center justify-center">
                        {{ $ux['primary_action']['label'] }}
                    </button>
                </form>
            @else
                <a href="{{ $ux['primary_action']['route'] }}" class="h-10 px-5 rounded-lg bg-primary text-white text-sm font-semibold hover:opacity-90 inline-flex items-center justify-center shrink-0">
                    {{ $ux['primary_action']['label'] }}
                </a>
            @endif
        @endif
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        @foreach ($ux['steps'] as $index => $step)
            @php $style = $statusStyles[$step['status']] ?? $statusStyles['upcoming']; @endphp
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold shrink-0 {{ $style }}">
                    @if ($step['status'] === 'completed')
                        ✓
                    @else
                        {{ $index + 1 }}
                    @endif
                </div>
                <span class="text-sm font-medium {{ $step['status'] === 'current' ? 'text-foreground' : 'text-muted-foreground' }}">
                    {{ $step['label'] }}
                </span>
            </div>
        @endforeach
    </div>

    @if ($ux['is_preparing'] ?? false)
        <ul class="mt-6 space-y-2 text-sm text-muted-foreground border-t border-border/30 pt-4">
            <li class="flex items-center gap-2">
                @if (in_array($batch->status, ['completed', 'completed_with_errors'], true))
                    <span class="text-emerald-600">✓</span>
                @elseif (in_array($batch->status, ['queued', 'processing', 'parsing', 'validating'], true))
                    <span class="inline-block w-3 h-3 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                @else
                    <span class="text-emerald-600">✓</span>
                @endif
                Reading file
            </li>
            <li class="flex items-center gap-2">
                @if (($batch->metadata['standardization_status'] ?? '') === 'completed')
                    <span class="text-emerald-600">✓</span>
                @elseif (($batch->metadata['standardization_status'] ?? '') === 'processing')
                    <span class="inline-block w-3 h-3 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                @else
                    <span class="w-3 h-3 rounded-full border border-border/60"></span>
                @endif
                Matching products
            </li>
            <li class="flex items-center gap-2">
                @if (($batch->metadata['materialization_status'] ?? '') === 'completed')
                    <span class="text-emerald-600">✓</span>
                @elseif (in_array($batch->metadata['materialization_status'] ?? '', ['preparing', 'processing'], true))
                    <span class="inline-block w-3 h-3 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                @else
                    <span class="w-3 h-3 rounded-full border border-border/60"></span>
                @endif
                Preparing analytics
            </li>
            <li class="flex items-center gap-2">
                @if ($ux['is_ready'] ?? false)
                    <span class="text-emerald-600">✓</span>
                @elseif (($batch->metadata['statistics_status'] ?? '') === 'processing')
                    <span class="inline-block w-3 h-3 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                @elseif (($batch->metadata['statistics_status'] ?? '') === 'failed')
                    <span class="text-red-600">!</span>
                @else
                    <span class="w-3 h-3 rounded-full border border-border/60"></span>
                @endif
                Building price intelligence
            </li>
        </ul>
    @endif

    @if ($ux['statistics_failed'] ?? false)
        <div class="mt-4 p-4 rounded-xl bg-red-50 border border-red-100 text-sm text-red-800">
            Market analysis preparation failed. Use <strong>Retry Market Statistics</strong> above.
        </div>
    @endif
</div>
