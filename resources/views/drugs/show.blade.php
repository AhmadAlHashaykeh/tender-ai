@extends('layouts.app')

@section('title', ($drug->display_name ?? $drug->code).' - Drug Intelligence')

@section('content')
<main class="drug-detail-view management-view">
    <div class="content-container-max fade-in-container">
        <div class="detail-header">
            <a href="{{ route('drugs.index') }}" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left w-4 h-4"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
                Back to Drugs
            </a>

            <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-6">
                <div class="detail-title-section flex-wrap">
                    <div class="company-logo-large shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pill"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"></path><path d="m8.5 8.5 7 7"></path></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 flex-wrap mb-2">
                            <h1 class="detail-title">{{ $drug->display_name }}</h1>
                            @php
                                $statusBadge = match ($kpis['activity_status']) {
                                    'active' => 'badge-status-won',
                                    'inactive' => 'badge-status-lost',
                                    default => 'bg-slate-100 text-slate-600 border-slate-200',
                                };
                            @endphp
                            <span class="badge-pill {{ $statusBadge }}">{{ ucfirst($kpis['activity_status']) }}</span>
                            @if ($kpis['has_pricing_statistics'])
                                <span class="badge-pill badge-brand">Pricing stats</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-4 text-sm text-muted-foreground">
                            <span>Code: <strong class="text-foreground font-mono">{{ $drug->code }}</strong></span>
                            <span>INN: <strong class="text-foreground">{{ $drug->inn ?? '—' }}</strong></span>
                            @if ($drug->form)
                                <span>Form: <strong class="text-foreground">{{ $drug->form }}</strong></span>
                            @endif
                            @if ($drug->dosage || $drug->strength)
                                <span>Strength: <strong class="text-foreground">{{ $drug->dosage ?? $drug->strength }}{{ $drug->strength_unit ? ' '.$drug->strength_unit : '' }}</strong></span>
                            @endif
                        </div>
                        @if ($drug->drugAliases->isNotEmpty())
                            <div class="mt-3">
                                <p class="text-xs text-muted-foreground uppercase tracking-wide font-semibold mb-1">Aliases</p>
                                <div class="badge-group">
                                    @foreach ($drug->drugAliases as $alias)
                                        <span class="badge-ghost">{{ $alias->alias_value }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <section class="metrics-grid">
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Bid Records</span></div>
                <p class="metric-value">{{ number_format($kpis['bid_records_count']) }}</p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Awarded</span></div>
                <p class="metric-value">{{ number_format($kpis['awarded_count']) }}</p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Countries</span></div>
                <p class="metric-value">{{ number_format($kpis['countries_count']) }}</p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Companies</span></div>
                <p class="metric-value">{{ number_format($kpis['companies_count']) }}</p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Tenders</span></div>
                <p class="metric-value">{{ number_format($kpis['tenders_count']) }}</p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Avg Price USD</span></div>
                <p class="metric-value">{{ $kpis['avg_price_usd'] > 0 ? '$'.number_format($kpis['avg_price_usd'], 2) : '—' }}</p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Median USD</span></div>
                <p class="metric-value">{{ $kpis['median_price_usd'] ? '$'.number_format((float) $kpis['median_price_usd'], 2) : '—' }}</p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Min / Max USD</span></div>
                <p class="metric-value text-base">
                    {{ $kpis['min_price_usd'] ? '$'.number_format((float) $kpis['min_price_usd'], 2) : '—' }}
                    /
                    {{ $kpis['max_price_usd'] ? '$'.number_format((float) $kpis['max_price_usd'], 2) : '—' }}
                </p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Latest Price USD</span></div>
                <p class="metric-value">{{ $kpis['latest_price_usd'] ? '$'.number_format((float) $kpis['latest_price_usd'], 2) : '—' }}</p>
            </div>
            @if ($kpis['trend_direction'])
                <div class="metric-card">
                    <div class="metric-header"><span class="metric-label">Trend</span></div>
                    <p class="metric-value text-base">
                        {{ ucfirst($kpis['trend_direction']) }}
                        @if ($kpis['trend_pct'] !== null)
                            ({{ number_format((float) $kpis['trend_pct'], 1) }}%)
                        @endif
                    </p>
                </div>
            @endif
        </section>

        @if ($pricingStatistics->isNotEmpty())
            <section class="card-glow management-table-card mb-6">
                <div class="p-5 border-b border-border/40">
                    <h3 class="font-semibold text-foreground">Pricing Statistics</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Pre-calculated aggregates by country, region, or global scope</p>
                </div>
                <div class="table-container-scroll management-table-scroll">
                    <table class="data-table management-data-table">
                        <thead>
                            <tr>
                                <th>Scope</th>
                                <th>Awards</th>
                                <th>Last Price</th>
                                <th>Avg</th>
                                <th>Weighted Avg</th>
                                <th>Median</th>
                                <th>Min</th>
                                <th>Max</th>
                                <th>Trend</th>
                                <th>Top Winner</th>
                                <th>Calculated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pricingStatistics as $stat)
                                <tr>
                                    <td class="font-semibold">{{ $intel->pricingScopeLabel($stat) }}</td>
                                    <td>{{ number_format($stat->award_count) }}</td>
                                    <td>{{ $stat->last_unit_price ? '$'.number_format((float) $stat->last_unit_price, 2) : '—' }}</td>
                                    <td>{{ $stat->avg_unit_price ? '$'.number_format((float) $stat->avg_unit_price, 2) : '—' }}</td>
                                    <td>{{ $stat->weighted_avg_unit_price ? '$'.number_format((float) $stat->weighted_avg_unit_price, 2) : '—' }}</td>
                                    <td>{{ $stat->median_unit_price ? '$'.number_format((float) $stat->median_unit_price, 2) : '—' }}</td>
                                    <td>{{ $stat->min_unit_price ? '$'.number_format((float) $stat->min_unit_price, 2) : '—' }}</td>
                                    <td>{{ $stat->max_unit_price ? '$'.number_format((float) $stat->max_unit_price, 2) : '—' }}</td>
                                    <td>
                                        @if ($stat->trend_direction)
                                            {{ ucfirst($stat->trend_direction) }}
                                            @if ($stat->trend_pct !== null)
                                                ({{ number_format((float) $stat->trend_pct, 1) }}%)
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $stat->topWinnerCompany?->name ?? '—' }}</td>
                                    <td class="text-xs whitespace-nowrap">{{ $stat->calculated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="card-glow management-table-card mb-6">
            <div class="p-5 border-b border-border/40">
                <h3 class="font-semibold text-foreground">Bid History</h3>
                <p class="text-xs text-muted-foreground mt-0.5">All bid records for this standardized drug</p>
            </div>
            <div class="table-container-scroll management-table-scroll">
                <table class="data-table management-data-table">
                    <thead>
                        <tr>
                            <th>Tender #</th>
                            <th>Year</th>
                            <th>Country</th>
                            <th>Company</th>
                            <th>Qty</th>
                            <th>Price USD</th>
                            <th>Awarded Price</th>
                            <th>Tender Value</th>
                            <th>Bid Status</th>
                            <th>Winner</th>
                            <th>Analytics</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bidHistory as $record)
                            <tr>
                                <td class="font-mono text-xs">{{ $record->tender?->tender_number ?? '—' }}</td>
                                <td>{{ $record->award_year ?? $record->tender?->year ?? '—' }}</td>
                                <td>{{ $record->country?->name ?? '—' }}</td>
                                <td>{{ $record->company?->name ?? '—' }}</td>
                                <td>{{ $record->quantity !== null ? number_format((float) $record->quantity, 0) : '—' }}</td>
                                <td>{{ $record->price_usd !== null ? '$'.number_format((float) $record->price_usd, 2) : '—' }}</td>
                                <td>{{ $record->original_awarded_price !== null ? '$'.number_format((float) $record->original_awarded_price, 2) : '—' }}</td>
                                <td>{{ $record->tender_value !== null ? number_format((float) $record->tender_value, 0) : '—' }}</td>
                                <td><x-bid-status-badge :status="$record->bid_status" /></td>
                                <td>
                                    @if ($record->is_winner)
                                        <span class="badge-pill badge-status-won">Yes</span>
                                    @else
                                        <span class="badge-pill badge-status-lost">No</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($record->is_analytics_ready)
                                        <span class="badge-pill badge-status-won">Yes</span>
                                    @else
                                        <span class="badge-pill bg-slate-100 text-slate-600">No</span>
                                    @endif
                                </td>
                                <td class="management-actions-cell text-right">
                                    <a href="{{ route('management.bid-records.show', $record) }}" class="btn-pill btn-ghost btn-xs">Record</a>
                                    @if ($record->company_id)
                                        <a href="{{ route('companies.show', $record->company_id) }}" class="btn-pill btn-ghost btn-xs">Company</a>
                                    @endif
                                    @if ($record->tender_id)
                                        <a href="{{ route('tenders.show', $record->tender_id) }}" class="btn-pill btn-ghost btn-xs">Tender</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted-foreground py-8">No bid records for this drug.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($bidHistory->hasPages())
                <div class="p-4 border-t border-border/40">{{ $bidHistory->links() }}</div>
            @endif
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <section class="card-glow management-table-card">
                <div class="p-5 border-b border-border/40">
                    <h3 class="font-semibold text-foreground">Drug Company Summary</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Grouped by company</p>
                </div>
                <div class="table-container-scroll">
                    <table class="data-table management-data-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Records</th>
                                <th>Awarded</th>
                                <th>Avg USD</th>
                                <th>Awarded Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($companySummary as $row)
                                <tr>
                                    <td>
                                        @if ($row->company_id)
                                            <a href="{{ route('companies.show', $row->company_id) }}" class="text-primary hover:underline">{{ $row->company_name }}</a>
                                        @else
                                            {{ $row->company_name ?? '—' }}
                                        @endif
                                    </td>
                                    <td>{{ number_format($row->records_count) }}</td>
                                    <td>{{ number_format($row->awarded_count) }}</td>
                                    <td>{{ $row->avg_price_usd ? '$'.number_format((float) $row->avg_price_usd, 2) : '—' }}</td>
                                    <td>{{ $intel->formatMoney((float) $row->total_awarded_value) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted-foreground py-6">No company data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card-glow management-table-card">
                <div class="p-5 border-b border-border/40">
                    <h3 class="font-semibold text-foreground">Drug Country Summary</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Grouped by country</p>
                </div>
                <div class="table-container-scroll">
                    <table class="data-table management-data-table">
                        <thead>
                            <tr>
                                <th>Country</th>
                                <th>Records</th>
                                <th>Awarded</th>
                                <th>Avg USD</th>
                                <th>Min</th>
                                <th>Max</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($countrySummary as $row)
                                <tr>
                                    <td>{{ $row->country_name }}</td>
                                    <td>{{ number_format($row->records_count) }}</td>
                                    <td>{{ number_format($row->awarded_count) }}</td>
                                    <td>{{ $row->avg_price_usd ? '$'.number_format((float) $row->avg_price_usd, 2) : '—' }}</td>
                                    <td>{{ $row->min_price_usd ? '$'.number_format((float) $row->min_price_usd, 2) : '—' }}</td>
                                    <td>{{ $row->max_price_usd ? '$'.number_format((float) $row->max_price_usd, 2) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted-foreground py-6">No country data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
