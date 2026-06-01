@extends('layouts.app')

@section('title', 'Pricing Statistics | TenderAI')

@section('content')
<main class="p-6 min-h-screen">
    <div class="space-y-6 max-w-6xl mx-auto">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-foreground">Pricing Statistics</h1>
                <p class="text-sm text-muted-foreground mt-1">Aggregated unit pricing per standardized drug and country (Phase 5)</p>
            </div>
            <a href="{{ route('imports.index') }}" class="text-xs text-primary font-semibold">← Imports</a>
        </div>

        <div class="bg-white rounded-2xl border border-border/50 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 border-b border-border/40">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold text-muted-foreground">Drug</th>
                            <th class="text-left px-4 py-3 font-semibold text-muted-foreground">Country</th>
                            <th class="text-right px-4 py-3 font-semibold text-muted-foreground">Awards</th>
                            <th class="text-right px-4 py-3 font-semibold text-muted-foreground">Avg</th>
                            <th class="text-right px-4 py-3 font-semibold text-muted-foreground">Median</th>
                            <th class="text-right px-4 py-3 font-semibold text-muted-foreground">Min</th>
                            <th class="text-right px-4 py-3 font-semibold text-muted-foreground">Max</th>
                            <th class="text-right px-4 py-3 font-semibold text-muted-foreground">Last</th>
                            <th class="text-left px-4 py-3 font-semibold text-muted-foreground">Trend</th>
                            <th class="text-left px-4 py-3 font-semibold text-muted-foreground">Top Winner</th>
                            <th class="text-left px-4 py-3 font-semibold text-muted-foreground">Calculated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($statistics as $stat)
                            <tr class="border-b border-border/30 hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-medium text-foreground">
                                    {{ $stat->standardizedDrug?->display_name ?? $stat->standardizedDrug?->code ?? '—' }}
                                </td>
                                <td class="px-4 py-3">{{ $stat->country?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $stat->award_count }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">${{ number_format((float) $stat->avg_unit_price, 2) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">${{ number_format((float) $stat->median_unit_price, 2) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">${{ number_format((float) $stat->min_unit_price, 2) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">${{ number_format((float) $stat->max_unit_price, 2) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">${{ number_format((float) $stat->last_unit_price, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="capitalize">{{ $stat->trend_direction ?? 'unknown' }}</span>
                                    @if ($stat->trend_pct !== null)
                                        <span class="text-muted-foreground">({{ number_format((float) $stat->trend_pct, 1) }}%)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $stat->topWinnerCompany?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ $stat->calculated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-8 text-center text-muted-foreground">
                                    No market statistics yet. Process your import batches, then refresh market statistics from Settings or your administrator.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($statistics->hasPages())
                <div class="px-4 py-3 border-t border-border/30">
                    {{ $statistics->links() }}
                </div>
            @endif
        </div>
    </div>
</main>
@endsection
