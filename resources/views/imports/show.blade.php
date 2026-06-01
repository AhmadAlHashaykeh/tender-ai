@extends('layouts.app')

@section('title', 'TenderAI - Uploaded File')

@section('content')
@php
    $std = $pipeline['standardization'];
    $mat = $materializationProgress;
    $importInProgress = in_array($batch->status, ['queued', 'processing', 'parsing', 'validating'], true);
    $failedChunks = (int) ($batch->metadata['failed_chunks'] ?? 0);
@endphp
<main class="p-6 min-h-screen">
    <div class="space-y-6 max-w-6xl mx-auto">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <a href="{{ route('imports.index') }}" class="text-xs text-muted-foreground hover:text-primary">← Uploaded files</a>
                <h1 class="text-2xl font-bold text-foreground mt-2">{{ $batch->original_filename ?? $batch->filename }}</h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Uploaded {{ $batch->created_at?->format('M j, Y g:i A') }}
                    · <x-import-status-badge :status="$batch->status" class="inline-flex" />
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($ux['primary_action'] ?? null)
                    @if (($ux['primary_action']['type'] ?? 'link') === 'form')
                        <form method="POST" action="{{ $ux['primary_action']['route'] }}">
                            @csrf
                            <button type="submit" class="h-9 px-4 rounded-lg bg-primary text-white text-xs font-semibold hover:opacity-90">
                                {{ $ux['primary_action']['label'] }}
                            </button>
                        </form>
                    @else
                        <a href="{{ $ux['primary_action']['route'] }}" class="h-9 px-4 rounded-lg bg-primary text-white text-xs font-semibold hover:opacity-90 inline-flex items-center">
                            {{ $ux['primary_action']['label'] }}
                        </a>
                    @endif
                @endif
                <a href="{{ route('imports.preview', $batch) }}" class="h-9 px-4 rounded-lg border border-border/50 text-xs font-semibold text-foreground hover:bg-slate-50 inline-flex items-center">View rows</a>
                <form method="POST" action="{{ route('imports.destroy', $batch) }}" onsubmit="return confirm('Delete this uploaded file and all rows?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="h-9 px-4 rounded-lg border border-red-200 text-xs font-semibold text-red-600 hover:bg-red-50">Delete</button>
                </form>
            </div>
        </div>

        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <x-import-user-progress :ux="$ux" :batch="$batch" />

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="p-4 rounded-2xl bg-white border border-border/40">
                <p class="text-[10px] text-muted-foreground uppercase">Total Records</p>
                <p class="text-2xl font-bold text-foreground">{{ number_format($stats['total']) }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-gradient-to-br from-emerald-500/5 to-teal-500/5 border border-emerald-200/40">
                <p class="text-[10px] text-muted-foreground uppercase">Matched Products</p>
                <p class="text-2xl font-bold text-foreground">{{ number_format($ux['matched_products']) }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-gradient-to-br from-amber-500/5 to-orange-500/5 border border-amber-200/40">
                <p class="text-[10px] text-muted-foreground uppercase">Need Review</p>
                <p class="text-2xl font-bold text-foreground">{{ number_format($ux['review_count']) }}</p>
            </div>
            <div class="p-4 rounded-2xl bg-white border border-border/40">
                <p class="text-[10px] text-muted-foreground uppercase">Ignored Records</p>
                <p class="text-2xl font-bold text-foreground">{{ number_format($ux['ignored_records']) }}</p>
                <p class="text-[10px] text-muted-foreground mt-1">Invalid, duplicate, or skipped</p>
            </div>
        </div>

        @if ($batch->status === 'failed')
            <div class="p-4 rounded-xl bg-red-50 border border-red-100 text-sm text-red-700">
                Upload failed: {{ $batch->metadata['failure_reason'] ?? 'Unknown error' }}
            </div>
        @endif

        <x-import-processing-progress :batch="$batch" />

        @if ($showAdvanced && ($queueHealth['should_warn'] ?? false))
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-900" role="alert">
                <strong>Queue:</strong> {{ $queueHealth['message'] }}
                @if (! empty($queueHealth['pending_jobs']))
                    <span class="text-amber-800">({{ $queueHealth['pending_jobs'] }} job(s) waiting.)</span>
                @endif
                <span class="block text-xs text-amber-800 mt-1">Run cron <code class="text-xs">php artisan schedule:run</code> or <code class="text-xs">php artisan queue:work</code> locally.</span>
            </div>
        @endif

        @if ($showAdvanced)
            <details class="bg-white rounded-2xl border border-border/50 shadow-sm" open>
                <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-foreground">Advanced Details</summary>
                <div class="px-5 pb-5 space-y-4 border-t border-border/30 pt-4">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($importInProgress)
                            <span class="text-xs text-muted-foreground">Import running in background</span>
                        @elseif ($failedChunks > 0 && $batch->usesChunkedImport())
                            <form method="POST" action="{{ route('imports.chunks.retry-failed', $batch) }}">
                                @csrf
                                <button type="submit" class="h-8 px-3 rounded-lg bg-amber-500 text-white text-xs font-semibold">Retry Failed Chunks ({{ $failedChunks }})</button>
                            </form>
                        @elseif ($pipeline['can_retry_materialization'] && ($mat['failed_chunks'] ?? 0) > 0)
                            <form method="POST" action="{{ route('imports.materialization.retry-failed', $batch) }}">
                                @csrf
                                <button type="submit" class="h-8 px-3 rounded-lg bg-amber-500 text-white text-xs font-semibold">Retry Failed Prep Chunks ({{ $mat['failed_chunks'] }})</button>
                            </form>
                        @elseif ($pipeline['can_retry_standardization'] && ($std['failed_chunks'] ?? 0) > 0 && $batch->usesChunkedStandardization())
                            <form method="POST" action="{{ route('standardization.retry-failed', $batch) }}">
                                @csrf
                                <button type="submit" class="h-8 px-3 rounded-lg bg-amber-500 text-white text-xs font-semibold">Retry Failed Match Chunks ({{ $std['failed_chunks'] }})</button>
                            </form>
                        @elseif ($pipeline['can_run_standardization'] && ! $importInProgress)
                            <form method="POST" action="{{ route('standardization.run-batch', $batch) }}">
                                @csrf
                                <button type="submit" class="h-8 px-3 rounded-lg border text-xs font-semibold">Run Product Matching (manual)</button>
                            </form>
                        @endif
                        @if ($pipeline['can_materialize'] && ! $importInProgress)
                            <form method="POST" action="{{ route('imports.materialize', $batch) }}">
                                @csrf
                                <button type="submit" class="h-8 px-3 rounded-lg border text-xs font-semibold">Prepare Data (manual)</button>
                            </form>
                        @endif
                    </div>

                    <x-import-standardization-progress :batch="$batch" :standardization="$std" />
                    <x-import-materialization-progress :batch="$batch" :materialization="$mat" />
                    <x-import-pipeline-progress :pipeline="$pipeline" />

                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 text-xs">
                        <div class="p-3 rounded-xl border"><strong>Valid</strong><br>{{ $stats['valid'] }}</div>
                        <div class="p-3 rounded-xl border"><strong>Invalid</strong><br>{{ $stats['invalid'] }}</div>
                        <div class="p-3 rounded-xl border"><strong>Std pending</strong><br>{{ $stats['standardization_pending'] }}</div>
                        <div class="p-3 rounded-xl border"><strong>Materialized</strong><br>{{ $materialization['materialized'] }}</div>
                        <div class="p-3 rounded-xl border"><strong>Mode</strong><br>{{ $batch->metadata['processing_mode'] ?? '—' }}</div>
                        <div class="p-3 rounded-xl border"><strong>Queue</strong><br>{{ config('queue.default') }}</div>
                    </div>
                </div>
            </details>
        @else
            <details class="bg-white rounded-2xl border border-border/50 shadow-sm">
                <summary class="cursor-pointer px-5 py-4 text-sm font-medium text-muted-foreground">Advanced Details</summary>
                <div class="px-5 pb-5 text-xs text-muted-foreground border-t border-border/30 pt-4">
                    Processing mode: {{ $batch->metadata['processing_mode'] ?? 'async' }}.
                    Enable <code class="font-mono bg-muted/40 px-1 rounded">IMPORT_SHOW_ADVANCED_DETAILS=true</code> for chunk, queue, and pipeline diagnostics.
                </div>
            </details>
        @endif

        @if ($ux['is_ready'])
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-sm text-emerald-800">
                <p class="font-semibold">Data ready successfully</p>
                <p class="mt-1">Your upload has been matched, prepared, and statistics have been refreshed.</p>
                <div class="mt-3 flex flex-wrap gap-3 text-xs">
                    <a href="{{ route('predictions.index') }}" class="text-primary font-semibold">Start predictions →</a>
                    <a href="{{ route('tenders.index') }}" class="text-primary font-semibold">View tenders →</a>
                    <a href="{{ route('statistics.pricing.index') }}" class="text-primary font-semibold">Pricing statistics →</a>
                </div>
            </div>
        @endif

        @if ($qualityScore !== null && $showAdvanced)
            @php
                $ratingClass = match ($qualityRating) {
                    'Excellent' => 'from-emerald-500/10 to-teal-500/10 border-emerald-200',
                    'Good' => 'from-blue-500/10 to-indigo-500/10 border-blue-200',
                    'Needs Review' => 'from-amber-500/10 to-orange-500/10 border-amber-200',
                    default => 'from-red-500/10 to-rose-500/10 border-red-200',
                };
            @endphp
            <div class="p-5 rounded-2xl bg-gradient-to-br {{ $ratingClass }} border">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-[10px] text-muted-foreground uppercase">Data Quality Score</p>
                        <p class="text-3xl font-bold text-foreground">{{ number_format($qualityScore, 0) }}<span class="text-lg text-muted-foreground">/100</span></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold bg-white/80 border">{{ $qualityRating }}</span>
                </div>
            </div>
        @endif
    </div>
</main>
@endsection
