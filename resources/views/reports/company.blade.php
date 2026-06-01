@extends('layouts.app')

@section('title', 'TenderAI – Company Performance')

@section('content')
<main class="p-6 min-h-screen">
    <div class="flex gap-6 max-w-7xl mx-auto">
        @include('reports.partials.nav', ['active' => 'company'])

        <div class="flex-1 space-y-5 min-w-0">
            <div>
                <h2 class="text-2xl font-bold text-foreground mb-1">Company Performance</h2>
                <p class="text-sm text-muted-foreground">Award activity from your tender database</p>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs text-muted-foreground mb-1">Companies</p>
                    <p class="text-2xl font-semibold">{{ number_format($summary['total_companies']) }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs text-muted-foreground mb-1">Total Awards</p>
                    <p class="text-2xl font-semibold">{{ number_format($summary['total_awarded_bids']) }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs text-muted-foreground mb-1">Avg Award Price</p>
                    <p class="text-2xl font-semibold">{{ $summary['avg_awarded_price'] ? '$'.number_format($summary['avg_awarded_price'], 2) : '—' }}</p>
                </div>
            </div>

            <div class="rounded-xl border bg-white overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h4 class="font-semibold">Top Companies by Award Count</h4>
                </div>
                @if($topCompanies->isNotEmpty())
                    <table class="w-full text-sm">
                        <thead class="bg-muted/20">
                            <tr class="text-xs text-muted-foreground uppercase">
                                <th class="text-left px-6 py-3">Company</th>
                                <th class="text-right px-6 py-3">Awards</th>
                                <th class="text-right px-6 py-3">Avg Award Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topCompanies as $row)
                                <tr class="border-t border-border/30">
                                    <td class="px-6 py-3 font-medium">{{ $row->company_name }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums">{{ number_format($row->awards_count) }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums">{{ $row->avg_price_usd ? '$'.number_format((float) $row->avg_price_usd, 2) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-muted-foreground py-12 text-center">No company award data available yet.</p>
                @endif
            </div>
        </div>
    </div>
</main>
@endsection
