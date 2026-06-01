@extends('layouts.app')



@section('title', 'TenderAI - Dashboard')



@section('content')

<main class="p-6 min-h-screen">

    <div class="space-y-5 max-w-7xl mx-auto">

        <div>

            <h2 class="text-2xl font-bold text-foreground">Dashboard</h2>

            <p class="text-sm text-muted-foreground mt-0.5">Your pharmaceutical tender pricing intelligence at a glance</p>

        </div>



        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

            <a href="{{ route('tenders.index') }}" class="group p-5 rounded-2xl border border-border/50 bg-white/80 backdrop-blur-sm hover:border-primary/20 hover:shadow-lg transition-all">

                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center mb-4">

                    <i data-lucide="file-text" class="w-5 h-5 text-blue-600"></i>

                </div>

                <p class="text-xs text-muted-foreground mb-1.5 font-medium">Total Tenders</p>

                <p class="text-2xl font-bold text-foreground tabular-nums">{{ number_format($summary['total_tenders']) }}</p>

            </a>

            <a href="{{ route('drugs.index') }}" class="group p-5 rounded-2xl border border-border/50 bg-white/80 backdrop-blur-sm hover:border-primary/20 hover:shadow-lg transition-all">

                <div class="w-11 h-11 rounded-xl bg-indigo-50 flex items-center justify-center mb-4">

                    <i data-lucide="package" class="w-5 h-5 text-indigo-600"></i>

                </div>

                <p class="text-xs text-muted-foreground mb-1.5 font-medium">Products in Catalog</p>

                <p class="text-2xl font-bold text-foreground tabular-nums">{{ number_format($summary['total_drugs']) }}</p>

            </a>

            <a href="{{ route('companies.index') }}" class="group p-5 rounded-2xl border border-border/50 bg-white/80 backdrop-blur-sm hover:border-primary/20 hover:shadow-lg transition-all">

                <div class="w-11 h-11 rounded-xl bg-violet-50 flex items-center justify-center mb-4">

                    <i data-lucide="building-2" class="w-5 h-5 text-violet-600"></i>

                </div>

                <p class="text-xs text-muted-foreground mb-1.5 font-medium">Companies</p>

                <p class="text-2xl font-bold text-foreground tabular-nums">{{ number_format($summary['total_companies']) }}</p>

            </a>

            <a href="{{ route('predictions.index') }}" class="group p-5 rounded-2xl border border-border/50 bg-white/80 backdrop-blur-sm hover:border-primary/20 hover:shadow-lg transition-all">

                <div class="w-11 h-11 rounded-xl bg-sky-50 flex items-center justify-center mb-4">

                    <i data-lucide="sparkles" class="w-5 h-5 text-sky-600"></i>

                </div>

                <p class="text-xs text-muted-foreground mb-1.5 font-medium">Your Recommendations</p>

                <p class="text-2xl font-bold text-foreground tabular-nums">{{ number_format($summary['total_predictions']) }}</p>

            </a>

        </div>



        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            <div class="p-5 rounded-2xl border border-border/50 bg-white/80 backdrop-blur-sm">

                <p class="text-xs text-muted-foreground font-medium mb-3">Recommendation Performance</p>

                @if($summary['completed_predictions'] > 0)

                    <div class="space-y-3">

                        <div class="flex items-center justify-between">

                            <span class="text-sm text-muted-foreground">Completed recommendations</span>

                            <span class="text-sm font-bold">{{ number_format($summary['completed_predictions']) }}</span>

                        </div>

                        <div class="flex items-center justify-between">

                            <span class="text-sm text-muted-foreground">Average data confidence</span>

                            <span class="text-sm font-bold text-primary">{{ $summary['avg_confidence'] }}%</span>

                        </div>

                    </div>

                @else

                    <p class="text-sm text-muted-foreground">No completed recommendations yet. Create your first price recommendation to see performance metrics here.</p>

                    <a href="{{ route('ai.recommendations.create') }}" class="inline-flex items-center gap-2 mt-4 text-sm font-semibold text-primary hover:underline">

                        <i data-lucide="plus" class="w-4 h-4"></i> Create recommendation

                    </a>

                @endif

            </div>



            <div class="p-5 rounded-2xl border border-border/50 bg-white/80 backdrop-blur-sm">

                <p class="text-xs text-muted-foreground font-medium mb-3">Latest Awarded Bid</p>

                @if($recentAwardedBid)

                    <p class="text-sm font-semibold text-foreground truncate">{{ $recentAwardedBid['drug'] }}</p>

                    <p class="text-xs text-muted-foreground mt-1">{{ $recentAwardedBid['country'] }}</p>

                    <div class="flex items-center justify-between mt-3">

                        <span class="text-xs font-medium text-emerald-600">{{ $recentAwardedBid['company'] }}</span>

                        <span class="text-sm font-bold">{{ $recentAwardedBid['price'] }}</span>

                    </div>

                    @if($recentAwardedBid['year'])

                        <p class="text-[10px] text-muted-foreground mt-2">Award year: {{ $recentAwardedBid['year'] }}</p>

                    @endif

                @else

                    <p class="text-sm text-muted-foreground">No awarded bid records yet. Upload and process tender data to populate this section.</p>

                @endif

            </div>



            <div class="p-5 rounded-2xl border border-border/50 bg-white/80 backdrop-blur-sm">

                <p class="text-xs text-muted-foreground font-medium mb-3">Next Upcoming Tender</p>

                @if($upcomingTender)

                    <p class="text-sm font-semibold text-foreground truncate">{{ $upcomingTender['title'] }}</p>

                    <p class="text-xs text-muted-foreground mt-1">{{ $upcomingTender['country'] }}</p>

                    <div class="flex items-center justify-between mt-3">
                        @if(!empty($upcomingTender['year']))
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-50 text-amber-600">Year {{ $upcomingTender['year'] }}</span>
                        @endif
                        <span class="text-xs text-muted-foreground">{{ $upcomingTender['products_count'] }} product(s)</span>
                    </div>

                @else

                    <p class="text-sm text-muted-foreground">No upcoming tenders scheduled. Add upcoming tenders from the Upload Data page.</p>

                @endif

            </div>

        </div>



        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            <div class="rounded-2xl border border-border/50 bg-white/80 backdrop-blur-sm p-5">

                <h3 class="font-semibold text-sm text-foreground">Bid Activity by Country</h3>

                <p class="text-xs text-muted-foreground mt-0.5 mb-4">Based on processed bid records in your database</p>

                @if($tenderVolumeByCountry->isNotEmpty())

                    <div class="space-y-3">

                        @php $maxBids = (int) $tenderVolumeByCountry->max('bid_count'); @endphp

                        @foreach($tenderVolumeByCountry as $row)

                            <div>

                                <div class="flex items-center justify-between text-xs mb-1">

                                    <span class="font-medium text-foreground">{{ $row->country_name }}</span>

                                    <span class="text-muted-foreground tabular-nums">{{ number_format($row->bid_count) }} bids</span>

                                </div>

                                <div class="h-2 rounded-full bg-muted/40 overflow-hidden">

                                    <div class="h-full rounded-full bg-primary" style="width: {{ $maxBids > 0 ? round(($row->bid_count / $maxBids) * 100) : 0 }}%"></div>

                                </div>

                            </div>

                        @endforeach

                    </div>

                @else

                    <p class="text-sm text-muted-foreground py-6 text-center">No bid activity data available yet.</p>

                @endif

            </div>



            <div class="rounded-2xl border border-border/50 bg-white/80 backdrop-blur-sm p-5">

                <h3 class="font-semibold text-sm text-foreground">Awarded Price Trends</h3>

                <p class="text-xs text-muted-foreground mt-0.5 mb-4">Average awarded prices over time from your tender history</p>

                @if(!empty($priceTrendsByCountry))

                    @php $firstCountry = $priceTrendsByCountry[0]; @endphp

                    @if(!empty($firstCountry['drugs']))

                        @php $firstDrug = $firstCountry['drugs'][0]; @endphp

                        <p class="text-xs font-semibold text-foreground mb-1">{{ $firstDrug['name'] }} · {{ $firstCountry['country_name'] }}</p>

                        <div class="space-y-2 mt-3">

                            @foreach($firstDrug['points'] as $point)

                                <div class="flex items-center justify-between text-sm">

                                    <span class="text-muted-foreground">{{ $point['year'] }}</span>

                                    <span class="font-semibold tabular-nums">${{ number_format($point['price'], 2) }}</span>

                                </div>

                            @endforeach

                        </div>

                        @if(count($priceTrendsByCountry) > 1 || count($firstCountry['drugs']) > 1)

                            <p class="text-[10px] text-muted-foreground mt-4">Showing first available trend. View product profiles for detailed pricing intelligence.</p>

                        @endif

                    @endif

                @else

                    <p class="text-sm text-muted-foreground py-6 text-center">Insufficient awarded bid history to display price trends. Need at least two years of awarded prices per product.</p>

                @endif

            </div>

        </div>

    </div>

</main>

@endsection



@push('scripts')

<script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>

@endpush

