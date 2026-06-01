@extends('layouts.app')

@section('title', 'TenderAI – Market Opportunities')

@section('content')
<main class="p-6 min-h-screen">
    <div class="flex gap-6 max-w-7xl mx-auto">
        @include('reports.partials.nav', ['active' => 'opportunity'])

        <div class="flex-1 space-y-5 min-w-0">
            <div>
                <h2 class="text-2xl font-bold text-foreground mb-1">Market Opportunities</h2>
                <p class="text-sm text-muted-foreground">Countries with the highest tender activity in your data</p>
            </div>

            <div class="rounded-xl border bg-white overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h4 class="font-semibold">Country Activity Summary</h4>
                </div>
                @if($countryOpportunities->isNotEmpty())
                    <table class="w-full text-sm">
                        <thead class="bg-muted/20">
                            <tr class="text-xs text-muted-foreground uppercase">
                                <th class="text-left px-6 py-3">Country</th>
                                <th class="text-right px-6 py-3">Bid Records</th>
                                <th class="text-right px-6 py-3">Awards</th>
                                <th class="text-right px-6 py-3">Products</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($countryOpportunities as $row)
                                <tr class="border-t border-border/30">
                                    <td class="px-6 py-3 font-medium">{{ $row->country_name }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums">{{ number_format($row->bid_count) }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums">{{ number_format($row->awarded_count) }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums">{{ number_format($row->drug_count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-muted-foreground py-12 text-center">No country-level activity data available yet.</p>
                @endif
            </div>
        </div>
    </div>
</main>
@endsection
