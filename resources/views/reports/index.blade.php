@extends('layouts.app')

@section('title', 'TenderAI – Market Reports')

@section('content')
<main class="p-6 min-h-screen">
    <div class="flex gap-6 max-w-7xl mx-auto">
        @include('reports.partials.nav', ['active' => 'market'])

        <div class="flex-1 space-y-5 min-w-0">
            <div>
                <h2 class="text-2xl font-bold text-foreground mb-1">Market Intelligence</h2>
                <p class="text-sm text-muted-foreground">Overview of your tender data and market activity</p>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs text-muted-foreground mb-1">Total Tenders</p>
                    <p class="text-2xl font-semibold">{{ number_format($summary['total_tenders']) }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs text-muted-foreground mb-1">Products Tracked</p>
                    <p class="text-2xl font-semibold">{{ number_format($summary['total_drugs']) }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs text-muted-foreground mb-1">Awarded Bids</p>
                    <p class="text-2xl font-semibold">{{ number_format($summary['total_awarded_bids']) }}</p>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-xs text-muted-foreground mb-1">Active Markets</p>
                    <p class="text-2xl font-semibold">{{ number_format($summary['countries_active']) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                <div class="rounded-xl border bg-white p-6">
                    <h4 class="font-semibold text-foreground">Top Companies by Awards</h4>
                    <p class="text-xs text-muted-foreground mt-0.5 mb-4">From your processed tender history</p>
                    @if($topCompanies->isNotEmpty())
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-xs text-muted-foreground">
                                    <th class="text-left py-2">Company</th>
                                    <th class="text-right py-2">Awards</th>
                                    <th class="text-right py-2">Avg Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topCompanies as $row)
                                    <tr class="border-b border-border/30">
                                        <td class="py-2.5 font-medium">{{ $row->company_name }}</td>
                                        <td class="py-2.5 text-right tabular-nums">{{ number_format($row->awards_count) }}</td>
                                        <td class="py-2.5 text-right tabular-nums">{{ $row->avg_price_usd ? '$'.number_format((float) $row->avg_price_usd, 2) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-sm text-muted-foreground py-8 text-center">No awarded bid data available yet.</p>
                    @endif
                </div>

                <div class="rounded-xl border bg-white p-6">
                    <h4 class="font-semibold text-foreground">Most Active Products</h4>
                    <p class="text-xs text-muted-foreground mt-0.5 mb-4">By bid record volume in your database</p>
                    @if($topDrugs->isNotEmpty())
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-xs text-muted-foreground">
                                    <th class="text-left py-2">Product</th>
                                    <th class="text-right py-2">Bids</th>
                                    <th class="text-right py-2">Awarded</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topDrugs as $row)
                                    <tr class="border-b border-border/30">
                                        <td class="py-2.5 font-medium">{{ $row->drug_name }}</td>
                                        <td class="py-2.5 text-right tabular-nums">{{ number_format($row->bid_count) }}</td>
                                        <td class="py-2.5 text-right tabular-nums">{{ number_format($row->awarded_count) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-sm text-muted-foreground py-8 text-center">No product activity data available yet.</p>
                    @endif
                </div>
            </div>

            @if($summary['avg_awarded_price'])
                <div class="rounded-xl border bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                    Average awarded price across all records: <strong class="text-foreground">${{ number_format($summary['avg_awarded_price'], 2) }}</strong>
                </div>
            @endif
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>
@endpush
