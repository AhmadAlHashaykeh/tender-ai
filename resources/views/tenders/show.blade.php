@extends('layouts.app')

@section('title', $intel->displayName($tender).' - Tender Intelligence')

@section('content')
<main class="tender-detail-view management-view">
    <div class="content-container-max fade-in-container">
        <div class="detail-header">
            <a href="{{ route('tenders.index') }}" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left w-4 h-4"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
                Back to Tenders
            </a>

            <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-6">
                <div class="detail-title-section flex-wrap">
                    <div class="company-logo-large shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-stack"><path d="M21 7h-3a2 2 0 0 1-2-2V2"></path><path d="M21 6v6.5c0 .8-.7 1.5-1.5 1.5h-7c-.8 0-1.5-.7-1.5-1.5v-9c0-.8.7-1.5 1.5-1.5H17Z"></path><path d="M7 8v8.8c0 .3.2.6.4.8.2.2.5.4.8.4H15"></path><path d="M3 12v8.8c0 .3.2.6.4.8.2.2.5.4.8.4H11"></path></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 flex-wrap mb-2">
                            <h1 class="detail-title">{{ $intel->displayName($tender) }}</h1>
                            @php
                                $statusBadge = match ($kpis['activity_status']) {
                                    'active' => 'badge-status-won',
                                    'inactive' => 'badge-status-lost',
                                    default => 'bg-slate-100 text-slate-600 border-slate-200',
                                };
                            @endphp
                            <span class="badge-pill {{ $statusBadge }}">{{ ucfirst($tender->status ?? $kpis['activity_status']) }}</span>
                        </div>
                        <div class="flex flex-wrap gap-4 text-sm text-muted-foreground">
                            <span>Tender #: <strong class="text-foreground font-mono">{{ $tender->tender_number }}</strong></span>
                            <span>Country: <strong class="text-foreground">{{ $tender->country?->name ?? '—' }}</strong></span>
                            <span>Year: <strong class="text-foreground">{{ $tender->year ?? '—' }}</strong></span>
                            <span>Version: <strong class="text-foreground">{{ $tender->version ?? '—' }}</strong></span>
                            @if ($kpis['import_batch_id'])
                                <span>Import batch: <strong class="text-foreground">#{{ $kpis['import_batch_id'] }}</strong></span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="metrics-grid">
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Total Items</span></div>
                <p class="metric-value">{{ number_format($kpis['items_count']) }}</p>
            </div>
            <!-- <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Bid Records</span></div>
                <p class="metric-value">{{ number_format($kpis['bid_records_count']) }}</p>
            </div> -->
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Awarded Records</span></div>
                <p class="metric-value">{{ number_format($kpis['awarded_count']) }}</p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Companies</span></div>
                <p class="metric-value">{{ number_format($kpis['companies_count']) }}</p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Drugs</span></div>
                <p class="metric-value">{{ number_format($kpis['drugs_count']) }}</p>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Total Awarded Value "تعديل تصير سعراخر سنة"</span></div>
                <p class="metric-value">{{ $intel->formatMoney($kpis['total_awarded_value']) }}</p>
            </div>
            <!-- <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Avg Price USD</span></div>
                <p class="metric-value">{{ $kpis['avg_price_usd'] > 0 ? '$'.number_format($kpis['avg_price_usd'], 2) : '—' }}</p>
            </div> -->
            <!-- <div class="metric-card">
                <div class="metric-header"><span class="metric-label">Min / Max USD</span></div>
                <p class="metric-value text-base">
                    {{ $kpis['min_price_usd'] ? '$'.number_format((float) $kpis['min_price_usd'], 2) : '—' }}
                    /
                    {{ $kpis['max_price_usd'] ? '$'.number_format((float) $kpis['max_price_usd'], 2) : '—' }}
                </p>
            </div> -->
        </section>

        <section class="card-glow management-table-card mb-6">
            <div class="p-5 border-b border-border/40">
                <h3 class="font-semibold text-foreground">Items / Bid Records</h3>
                <p class="text-xs text-muted-foreground mt-0.5">All rows related to this tender</p>
            </div>
            <div class="table-container-scroll management-table-scroll">
                <table class="data-table management-data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>INN</th>
                            <th>Product / Drug</th>
                            <th>Company</th>
                            <th>Qty</th>
                            <th>Price USD</th>
                            <th>Awarded Price</th>
                            <th>Tender Value</th>
                            <th>Bid Status</th>
                            <th>Winner</th>
                            <th>Analytics</th>
                            <th>Import Row</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bidHistory as $record)
                            @php
                                $drug = $record->standardizedDrug;
                                $drugLabel = $drug?->display_name ?? $drug?->inn ?? $record->tenderItem?->description ?? '—';
                            @endphp
                            <tr>
                                <td class="font-mono text-xs">{{ $drug?->code ?? '—' }}</td>
                                <td>{{ $drug?->inn ?? '—' }}</td>
                                <td class="max-w-[10rem] truncate" title="{{ $drugLabel }}">{{ $drugLabel }}</td>
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
                                <td>{{ $record->source_import_row_id ? '#'.$record->source_import_row_id : '—' }}</td>
                                <td class="management-actions-cell text-right">
                                    <a href="{{ route('management.bid-records.show', $record) }}" class="btn-pill btn-ghost btn-xs">Record</a>
                                    @if ($record->company_id)
                                        <a href="{{ route('companies.show', $record->company_id) }}" class="btn-pill btn-ghost btn-xs">Company</a>
                                    @endif
                                    @if ($record->standardized_drug_id)
                                        <a href="{{ route('drugs.show', $record->standardized_drug_id) }}" class="btn-pill btn-ghost btn-xs">Drug</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted-foreground py-8">No bid records for this tender.</td>
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
                    <h3 class="font-semibold text-foreground">Tender Company Summary</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Grouped by company</p>
                </div>
                <div class="table-container-scroll">
                    <table class="data-table management-data-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Records</th>
                                <th>Awarded</th>
                                <th>Awarded Value</th>
                                <th>Avg USD</th>
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
                                    <td>{{ $intel->formatMoney((float) $row->total_awarded_value) }}</td>
                                    <td>{{ $row->avg_price_usd ? '$'.number_format((float) $row->avg_price_usd, 2) : '—' }}</td>
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
                    <h3 class="font-semibold text-foreground">Tender Drug Summary</h3>
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
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($drugSummary as $row)
                                <tr>
                                    <td>
                                        @if ($row->drug_id)
                                            <a href="{{ route('drugs.show', $row->drug_id) }}" class="text-primary hover:underline">{{ $row->drug_name ?? $row->drug_code }}</a>
                                        @else
                                            {{ $row->drug_name ?? '—' }}
                                        @endif
                                    </td>
                                    <td>{{ number_format($row->records_count) }}</td>
                                    <td>{{ number_format($row->awarded_count) }}</td>
                                    <td>{{ $row->avg_price_usd ? '$'.number_format((float) $row->avg_price_usd, 2) : '—' }}</td>
                                    <td>{{ $row->min_price_usd ? '$'.number_format((float) $row->min_price_usd, 2) : '—' }}</td>
                                    <td>{{ $row->max_price_usd ? '$'.number_format((float) $row->max_price_usd, 2) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted-foreground py-6">No drug data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
