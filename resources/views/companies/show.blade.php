@extends('layouts.app')

@section('title', $company->name.' - Company Intelligence')

@section('content')
<main class="company-detail-view management-view">
    <div class="content-container-max fade-in-container">
        <div class="detail-header">
            <a href="{{ route('companies.index') }}" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left w-4 h-4"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
                Back to Companies
            </a>

            <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-6">
                <div class="detail-title-section flex-wrap">
                    <div class="company-logo-large shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path><path d="M10 6h4"></path><path d="M10 10h4"></path><path d="M10 14h4"></path><path d="M10 18h4"></path></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 flex-wrap mb-2">
                            <h1 class="detail-title">{{ $company->name }}</h1>
                            @php
                                $statusBadge = match ($kpis['activity_status']) {
                                    'active' => 'badge-status-won',
                                    'inactive' => 'badge-status-lost',
                                    default => 'bg-slate-100 text-slate-600 border-slate-200',
                                };
                            @endphp
                            <span class="badge-pill {{ $statusBadge }}">{{ ucfirst($kpis['activity_status']) }}</span>
                        </div>
                        <div class="flex flex-wrap gap-4 text-sm text-muted-foreground">
                            <span>Country: <strong class="text-foreground">{{ $company->country?->name ?? '—' }}</strong></span>
                            <span>First seen: <strong class="text-foreground">{{ $kpis['first_seen_at'] ? \Illuminate\Support\Carbon::parse($kpis['first_seen_at'])->format('Y-m-d') : '—' }}</strong></span>
                            <span>Last activity: <strong class="text-foreground">{{ $kpis['last_activity_at'] ? \Illuminate\Support\Carbon::parse($kpis['last_activity_at'])->format('Y-m-d') : '—' }}</strong></span>
                        </div>
                        @if ($company->companyAliases->isNotEmpty())
                            <div class="mt-3">
                                <p class="text-xs text-muted-foreground uppercase tracking-wide font-semibold mb-1">Aliases</p>
                                <div class="badge-group">
                                    @foreach ($company->companyAliases as $alias)
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
                <div class="metric-header"><span class="metric-label">Awarded Wins</span></div>
                <p class="metric-value">{{ number_format($kpis['awarded_count']) }}</p>
            </div>
            @if ($kpis['lost_count'] > 0 || $kpis['participated_count'] > 0)
                <div class="metric-card">
                    <div class="metric-header"><span class="metric-label">Lost / Participated</span></div>
                    <p class="metric-value">{{ number_format($kpis['lost_count']) }} / {{ number_format($kpis['participated_count']) }}</p>
                </div>
            @endif
            <!-- <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Win Rate</span></div>
                <p class="metric-value text-base">{{ $kpis['win_rate']['label'] }}</p>
            </div> -->
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Total Awarded Value</span></div>
                <p class="metric-value">{{ $intel->formatMoney($kpis['total_awarded_value']) }}</p>
            </div>
            <!-- <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Avg Awarded Price USD</span></div>
                <p class="metric-value">{{ $kpis['avg_awarded_price_usd'] > 0 ? '$'.number_format($kpis['avg_awarded_price_usd'], 2) : '—' }}</p>
            </div> -->
            <!-- <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Unique Tenders</span></div>
                <p class="metric-value">{{ number_format($kpis['unique_tenders_count']) }}</p>
            </div> -->
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Unique Drugs</span></div>
                <p class="metric-value">{{ number_format($kpis['unique_drugs_count']) }}</p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Countries Involved</span></div>
                <p class="metric-value">{{ number_format($kpis['countries_involved_count']) }}</p>
            </div>
        </section>

        <section class="card-glow management-table-card mb-6">
            <div class="p-5 border-b border-border/40">
                <h3 class="font-semibold text-foreground">Tender / Bid History</h3>
                <p class="text-xs text-muted-foreground mt-0.5">All bid records grouped under this company profile</p>
            </div>
            <div class="table-container-scroll management-table-scroll">
                <table class="data-table management-data-table">
                    <thead>
                        <tr>
                            <th>Tender #</th>
                            <th>Year</th>
                            <th>Country</th>
                            <th>Drug / Product</th>
                            <th>Qty</th>
                            <th>Price USD</th>
                            <th>Tender Value</th>
                            <th>Bid Status</th>
                            <th>Winner</th>
                            <th>Analytics</th>
                            <th>Batch</th>
                            <th>Created</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bidHistory as $record)
                            @php
                                $drugLabel = $record->standardizedDrug?->display_name
                                    ?? $record->standardizedDrug?->inn
                                    ?? $record->tenderItem?->description
                                    ?? '—';
                            @endphp
                            <tr>
                                <td>{{ $record->tender?->tender_number ?? '—' }}</td>
                                <td>{{ $record->award_year ?? $record->tender?->year ?? '—' }}</td>
                                <td>{{ $record->country?->name ?? '—' }}</td>
                                <td class="max-w-[12rem] truncate" title="{{ $drugLabel }}">{{ $drugLabel }}</td>
                                <td>{{ $record->quantity !== null ? number_format((float) $record->quantity, 0) : '—' }}</td>
                                <td>{{ $record->price_usd !== null ? '$'.number_format((float) $record->price_usd, 2) : '—' }}</td>
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
                                <td>{{ $record->import_batch_id ? '#'.$record->import_batch_id : '—' }}</td>
                                <td class="text-xs whitespace-nowrap">{{ $record->created_at?->format('Y-m-d') }}</td>
                                <td class="management-actions-cell text-right">
                                    <a href="{{ route('management.bid-records.show', $record) }}" class="btn-pill btn-ghost btn-xs">Record</a>
                                    @if ($record->tender_id)
                                        <a href="{{ route('management.index', ['tender_number' => $record->tender?->tender_number]) }}" class="btn-pill btn-ghost btn-xs">Tender</a>
                                    @endif
                                    @if ($record->standardized_drug_id)
                                        <a href="{{ route('management.index', ['standardized_drug_id' => $record->standardized_drug_id]) }}" class="btn-pill btn-ghost btn-xs">Drug</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted-foreground py-8">No bid records for this company.</td>
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
                    <h3 class="font-semibold text-foreground">Company Drug Summary</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Grouped by standardized drug</p>
                </div>
                <div class="table-container-scroll">
                    <table class="data-table management-data-table">
                        <thead>
                            <tr>
                                <th>Drug</th>
                                <th>Records</th>
                                <th>Awarded</th>
                                <th>Avg USD</th>
                                <th>Min</th>
                                <th>Max</th>
                                <th>Last Year</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($drugSummary as $row)
                                <tr>
                                    <td>{{ $row->drug_name ?? $row->drug_code ?? '—' }}</td>
                                    <td>{{ number_format($row->records_count) }}</td>
                                    <td>{{ number_format($row->awarded_count) }}</td>
                                    <td>{{ $row->avg_price_usd ? '$'.number_format((float) $row->avg_price_usd, 2) : '—' }}</td>
                                    <td>{{ $row->min_price_usd ? '$'.number_format((float) $row->min_price_usd, 2) : '—' }}</td>
                                    <td>{{ $row->max_price_usd ? '$'.number_format((float) $row->max_price_usd, 2) : '—' }}</td>
                                    <td>{{ $row->last_year ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted-foreground py-6">No drug data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card-glow management-table-card">
                <div class="p-5 border-b border-border/40">
                    <h3 class="font-semibold text-foreground">Company Country Summary</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Grouped by country</p>
                </div>
                <div class="table-container-scroll">
                    <table class="data-table management-data-table">
                        <thead>
                            <tr>
                                <th>Country</th>
                                <th>Records</th>
                                <th>Awarded</th>
                                <th>Awarded Value</th>
                                <th>Unique Tenders</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($countrySummary as $row)
                                <tr>
                                    <td>{{ $row->country_name }}</td>
                                    <td>{{ number_format($row->records_count) }}</td>
                                    <td>{{ number_format($row->awarded_count) }}</td>
                                    <td>{{ $intel->formatMoney((float) $row->total_awarded_value) }}</td>
                                    <td>{{ number_format($row->unique_tenders_count) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted-foreground py-6">No country data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
