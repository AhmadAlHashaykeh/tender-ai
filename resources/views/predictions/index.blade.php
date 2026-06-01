@extends('layouts.app')



@section('title', 'TenderAI - Recommendation History')



@section('content')

<main class="p-6 min-h-screen">

    <div class="space-y-7 max-w-7xl mx-auto">

        <div class="flex items-start justify-between flex-wrap gap-4">

            <div>

                <div class="flex items-center gap-3 mb-1">

                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center shadow-md">

                        <i data-lucide="history" class="w-5 h-5 text-white"></i>

                    </div>

                    <h1 class="text-2xl font-bold text-foreground">Recommendation History</h1>

                </div>

                <p class="text-sm text-muted-foreground ml-0.5">Review your past price recommendations and analysis results</p>

            </div>

            <div class="flex items-center gap-3">

                <div class="px-4 py-2.5 rounded-xl bg-gradient-to-br from-primary/8 to-secondary/8 border border-primary/15">

                    <p class="text-sm font-semibold text-primary">

                        {{ $predictions->total() }}

                        <span class="font-normal text-muted-foreground">record(s)</span>

                    </p>

                </div>

                <a href="{{ route('ai.recommendations.create') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-xl bg-gradient-to-r from-primary to-secondary text-white text-sm font-semibold shadow-sm">

                    <i data-lucide="plus" class="w-4 h-4"></i> New Recommendation

                </a>

            </div>

        </div>



        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">

            <div class="p-4 rounded-2xl bg-gradient-to-br from-primary/5 to-secondary/5 border-primary/10 border">

                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-2">Total</p>

                <p class="text-2xl font-bold text-foreground">{{ $stats['total'] }}</p>

            </div>

            <div class="p-4 rounded-2xl bg-gradient-to-br from-emerald-500/5 to-teal-500/5 border-emerald-200/40 border">

                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-2">Completed</p>

                <p class="text-2xl font-bold text-foreground">{{ $stats['completed'] }}</p>

            </div>

            <div class="p-4 rounded-2xl bg-gradient-to-br from-red-500/5 to-rose-500/5 border-red-200/40 border">

                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-2">Failed</p>

                <p class="text-2xl font-bold text-foreground">{{ $stats['failed'] }}</p>

            </div>

            <div class="p-4 rounded-2xl bg-gradient-to-br from-violet-500/5 to-purple-500/5 border-violet-200/40 border">

                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-2">In progress</p>

                <p class="text-2xl font-bold text-foreground">{{ $stats['processing'] }}</p>

            </div>

            <div class="p-4 rounded-2xl bg-gradient-to-br from-amber-500/5 to-orange-500/5 border-amber-200/40 border">

                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-2">Avg data confidence</p>

                <p class="text-2xl font-bold text-foreground">{{ $stats['avg_confidence'] }}%</p>

            </div>

        </div>



        <form method="GET" action="{{ route('predictions.index') }}" class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-5 space-y-4">

            <div class="relative">

                <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground"></i>

                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="w-full h-10 pl-10 pr-3 rounded-xl border border-border/50 bg-white/70 text-sm" placeholder="Search by drug name or recommendation ID...">

            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">

                <select name="status" class="h-10 px-3 rounded-xl border border-border/50 bg-white/70 text-sm">

                    <option value="">All statuses</option>

                    @foreach(['completed', 'failed', 'processing', 'pending'] as $statusOption)

                        <option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>{{ ucfirst($statusOption) }}</option>

                    @endforeach

                </select>

                <select name="risk_level" class="h-10 px-3 rounded-xl border border-border/50 bg-white/70 text-sm">

                    <option value="">All risk levels</option>

                    @foreach(['low', 'medium', 'high'] as $risk)

                        <option value="{{ $risk }}" @selected(($filters['risk_level'] ?? '') === $risk)>{{ ucfirst($risk) }}</option>

                    @endforeach

                </select>

                <select name="source" class="h-10 px-3 rounded-xl border border-border/50 bg-white/70 text-sm">

                    <option value="">All analysis types</option>

                    <option value="backend_only" @selected(($filters['source'] ?? '') === 'backend_only')>Calculation only</option>

                    <option value="ai_assisted" @selected(($filters['source'] ?? '') === 'ai_assisted')>AI insights included</option>

                </select>

                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="h-10 px-3 rounded-xl border border-border/50 bg-white/70 text-sm" placeholder="From">

                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="h-10 px-3 rounded-xl border border-border/50 bg-white/70 text-sm" placeholder="To">

            </div>

            <div class="flex gap-2">

                <button type="submit" class="h-9 px-4 rounded-lg bg-primary text-white text-sm font-semibold">Apply filters</button>

                <a href="{{ route('predictions.index') }}" class="h-9 px-4 rounded-lg border border-border/50 text-sm font-medium inline-flex items-center">Clear</a>

            </div>

        </form>



        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 overflow-hidden">

            @if($predictions->isEmpty())

                <div class="py-16 text-center px-6">

                    <i data-lucide="inbox" class="w-12 h-12 text-muted-foreground/40 mx-auto mb-4"></i>

                    <p class="font-semibold text-foreground text-lg">No recommendations yet</p>

                    <p class="text-sm text-muted-foreground mt-2 max-w-md mx-auto">

                        Create your first price recommendation after uploading tender data and refreshing market statistics.

                    </p>

                    <a href="{{ route('ai.recommendations.create') }}" class="inline-flex items-center gap-2 mt-6 h-10 px-5 rounded-xl bg-gradient-to-r from-primary to-secondary text-white text-sm font-semibold">

                        <i data-lucide="sparkles" class="w-4 h-4"></i> Create recommendation

                    </a>

                </div>

            @else

                <div class="overflow-x-auto">

                    <table class="w-full text-sm">

                        <thead>

                            <tr class="border-b border-border/40 bg-muted/20">

                                <th class="text-left px-5 py-3.5 text-xs font-semibold text-muted-foreground uppercase">Date</th>

                                <th class="text-left px-4 py-3.5 text-xs font-semibold text-muted-foreground uppercase">ID</th>

                                <th class="text-left px-4 py-3.5 text-xs font-semibold text-muted-foreground uppercase">Drug</th>

                                <th class="text-left px-4 py-3.5 text-xs font-semibold text-muted-foreground uppercase">Tender</th>

                                <th class="text-right px-4 py-3.5 text-xs font-semibold text-muted-foreground uppercase">Qty</th>

                                <th class="text-right px-4 py-3.5 text-xs font-semibold text-muted-foreground uppercase">Price</th>

                                <th class="text-center px-4 py-3.5 text-xs font-semibold text-muted-foreground uppercase">Risk</th>

                                <th class="text-center px-4 py-3.5 text-xs font-semibold text-muted-foreground uppercase">Confidence</th>

                                <th class="text-center px-4 py-3.5 text-xs font-semibold text-muted-foreground uppercase">Analysis</th>

                                <th class="text-center px-4 py-3.5 text-xs font-semibold text-muted-foreground uppercase">Status</th>

                                <th class="px-4 py-3.5"></th>

                            </tr>

                        </thead>

                        <tbody>

                            @foreach($predictions as $prediction)

                                <tr class="border-b border-border/20 hover:bg-primary/4 transition-colors">

                                    <td class="px-5 py-3.5">

                                        <p class="text-xs font-semibold text-foreground">{{ $prediction->created_at->format('Y-m-d') }}</p>

                                        <p class="text-[10px] text-muted-foreground">{{ $prediction->created_at->format('H:i') }}</p>

                                    </td>

                                    <td class="px-4 py-3.5 font-mono text-[10px] text-muted-foreground">{{ Str::limit($prediction->uuid, 8, '') }}</td>

                                    <td class="px-4 py-3.5">

                                        <span class="text-xs font-medium text-foreground">{{ $prediction->standardizedDrug?->display_name ?? '—' }}</span>

                                    </td>

                                    <td class="px-4 py-3.5 max-w-[180px]">

                                        <p class="text-xs truncate text-foreground/80">{{ $prediction->tender?->title ?? $prediction->tender?->tender_number ?? '—' }}</p>

                                    </td>

                                    <td class="px-4 py-3.5 text-right text-xs tabular-nums">

                                        @if($prediction->quantity)

                                            {{ number_format((float) $prediction->quantity, 0) }}

                                        @else

                                            —

                                        @endif

                                    </td>

                                    <td class="px-4 py-3.5 text-right">

                                        @if($prediction->backend_recommended_price)

                                            <span class="text-sm font-bold tabular-nums">{{ \App\Support\RecommendationCurrency::format($prediction->backend_recommended_price) }}</span>

                                        @else

                                            —

                                        @endif

                                    </td>

                                    <td class="px-4 py-3.5 text-center">

                                        <x-prediction-risk-badge :risk="$prediction->risk_level" />

                                    </td>

                                    <td class="px-4 py-3.5 text-center text-xs font-semibold tabular-nums">

                                        {{ $prediction->confidence_score !== null ? number_format((float) $prediction->confidence_score, 0).'%' : '—' }}

                                    </td>

                                    <td class="px-4 py-3.5 text-center">

                                        <x-prediction-source-badge :source="$prediction->source" />

                                    </td>

                                    <td class="px-4 py-3.5 text-center">

                                        <x-prediction-status-badge :status="$prediction->status" />

                                    </td>

                                    <td class="px-4 py-3.5 text-right">

                                        <a href="{{ route('predictions.show', $prediction) }}" class="text-xs font-semibold text-primary hover:underline">View</a>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

                @if($predictions->hasPages())

                    <div class="p-4 border-t border-border/40">

                        {{ $predictions->links() }}

                    </div>

                @endif

            @endif

        </div>

    </div>

</main>

@endsection



@push('scripts')

<script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>

@endpush

