@extends('layouts.app')

@section('title', 'TenderAI – Recommendation History Report')

@section('content')
<main class="p-6 min-h-screen">
    <div class="flex gap-6 max-w-7xl mx-auto">
        @include('reports.partials.nav', ['active' => 'history'])

        <div class="flex-1 space-y-5 min-w-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-foreground mb-1">Recommendation History</h2>
                    <p class="text-sm text-muted-foreground">Your price recommendation activity and data confidence</p>
                </div>
                <a href="{{ route('predictions.index') }}" class="text-sm font-semibold text-primary hover:underline">View full history</a>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs text-muted-foreground mb-1">Total</p>
                    <p class="text-2xl font-semibold">{{ number_format($performance['total']) }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs text-muted-foreground mb-1">Completed</p>
                    <p class="text-2xl font-semibold">{{ number_format($performance['completed']) }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs text-muted-foreground mb-1">Failed</p>
                    <p class="text-2xl font-semibold">{{ number_format($performance['failed']) }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs text-muted-foreground mb-1">Avg Data Confidence</p>
                    <p class="text-2xl font-semibold">{{ $performance['avg_confidence'] }}%</p>
                </div>
            </div>

            <div class="rounded-xl border bg-white overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h4 class="font-semibold">Recent Recommendations</h4>
                </div>
                @if($recentPredictions->isNotEmpty())
                    <table class="w-full text-sm">
                        <thead class="bg-muted/20">
                            <tr class="text-xs text-muted-foreground uppercase">
                                <th class="text-left px-6 py-3">Date</th>
                                <th class="text-left px-6 py-3">Product</th>
                                <th class="text-right px-6 py-3">Price</th>
                                <th class="text-center px-6 py-3">Confidence</th>
                                <th class="text-center px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentPredictions as $prediction)
                                <tr class="border-t border-border/30">
                                    <td class="px-6 py-3 text-xs">{{ $prediction->created_at->format('Y-m-d') }}</td>
                                    <td class="px-6 py-3 font-medium">{{ $prediction->standardizedDrug?->display_name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums">
                                        @if($prediction->backend_recommended_price)
                                            {{ \App\Support\RecommendationCurrency::format($prediction->backend_recommended_price) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-center tabular-nums">{{ $prediction->confidence_score !== null ? number_format((float) $prediction->confidence_score, 0).'%' : '—' }}</td>
                                    <td class="px-6 py-3 text-center"><x-prediction-status-badge :status="$prediction->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-muted-foreground py-12 text-center">No recommendations created yet.</p>
                @endif
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>
@endpush
