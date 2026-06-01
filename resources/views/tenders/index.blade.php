@extends('layouts.app')

@section('title', 'TenderAI - Tender Intelligence')

@section('content')
<main class="tenders-view management-view">
    <div class="content-container-max fade-in-container">
        <header class="management-header">
            <div>
                <h1 class="page-title-gradient">Tender Intelligence</h1>
                <p class="page-subtitle">Tender registry grouped by materialized procurement records</p>
            </div>
            <div class="count-badge">
                <span class="count-text">{{ number_format($tenders->total()) }} tenders</span>
            </div>
        </header>

        <section class="management-stats-grid mb-6">
            <x-stat-card label="Total Tenders" :value="number_format($stats['total_tenders'])" icon="file-stack" />
            <x-stat-card label="Tender Items" :value="number_format($stats['total_tender_items'])" icon="layers" />
            <x-stat-card label="Bid Records" :value="number_format($stats['total_bid_records'])" icon="database" />
            <x-stat-card label="Awarded Records" :value="number_format($stats['total_awarded_records'])" icon="trophy" tone="emerald" />
            <x-stat-card label="Total Awarded Value" :value="$intel->formatMoney($stats['total_awarded_value'])" icon="dollar-sign" tone="violet" />
            <x-stat-card label="Countries" :value="number_format($stats['countries_count'])" icon="globe" />
            <x-stat-card label="Drugs" :value="number_format($stats['drugs_count'])" icon="pill" />
            <x-stat-card label="Companies" :value="number_format($stats['companies_count'])" icon="building-2" />
        </section>

        <section class="filter-card card-glow mb-6">
            <div class="filter-card-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel icon-sm"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path></svg>
                <h3 class="filter-card-title">Filters &amp; Search</h3>
            </div>
            <form method="GET" action="{{ route('tenders.index') }}" class="management-filter-form">
                <div class="filter-grid">
                    <div class="search-wrapper filter-span-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search icon-xs search-icon-inside"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                        <input type="text" name="search" class="input-pill" placeholder="Tender #, name, company, drug..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <select name="country_id" class="select-pill">
                        <option value="">All Countries</option>
                        @foreach ($filterOptions['countries'] as $country)
                            <option value="{{ $country->id }}" @selected(($filters['country_id'] ?? '') == $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                    <select name="year" class="select-pill">
                        <option value="">All Years</option>
                        @foreach ($filterOptions['years'] as $year)
                            <option value="{{ $year }}" @selected(($filters['year'] ?? '') == $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="version" class="select-pill">
                        <option value="">All Versions</option>
                        @foreach ($filterOptions['versions'] as $version)
                            <option value="{{ $version }}" @selected(($filters['version'] ?? '') === $version)>{{ $version }}</option>
                        @endforeach
                    </select>
                    <select name="company_id" class="select-pill">
                        <option value="">All Companies</option>
                        @foreach ($filterOptions['companies'] as $company)
                            <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? '') == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                    <select name="standardized_drug_id" class="select-pill">
                        <option value="">All Drugs</option>
                        @foreach ($filterOptions['drugs'] as $drug)
                            <option value="{{ $drug->id }}" @selected(($filters['standardized_drug_id'] ?? '') == $drug->id)>{{ $drug->code }} — {{ $drug->display_name ?? $drug->inn }}</option>
                        @endforeach
                    </select>
                    <select name="bid_status" class="select-pill">
                        <option value="">All Bid Status</option>
                        @foreach ($bidStatuses as $status)
                            <option value="{{ $status }}" @selected(($filters['bid_status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <select name="winner" class="select-pill">
                        <option value="all" @selected(($filters['winner'] ?? 'all') === 'all')>Winner: All</option>
                        <option value="yes" @selected(($filters['winner'] ?? '') === 'yes')>Winner only</option>
                        <option value="no" @selected(($filters['winner'] ?? '') === 'no')>Non-winner</option>
                    </select>
                    <select name="analytics_ready" class="select-pill">
                        <option value="all" @selected(($filters['analytics_ready'] ?? 'all') === 'all')>Analytics: All</option>
                        <option value="yes" @selected(($filters['analytics_ready'] ?? '') === 'yes')>Analytics: Yes</option>
                        <option value="no" @selected(($filters['analytics_ready'] ?? '') === 'no')>Analytics: No</option>
                    </select>
                    <input type="number" step="any" name="price_usd_min" class="input-pill" placeholder="Min price USD" value="{{ $filters['price_usd_min'] ?? '' }}">
                    <input type="number" step="any" name="price_usd_max" class="input-pill" placeholder="Max price USD" value="{{ $filters['price_usd_max'] ?? '' }}">
                    <input type="number" step="any" name="tender_value_min" class="input-pill" placeholder="Min tender value" value="{{ $filters['tender_value_min'] ?? '' }}">
                    <input type="number" step="any" name="tender_value_max" class="input-pill" placeholder="Max tender value" value="{{ $filters['tender_value_max'] ?? '' }}">
                    <select name="per_page" class="select-pill">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected(($filters['per_page'] ?? 25) == $option)>{{ $option }} / page</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-actions-row">
                    <button type="submit" class="btn-pill btn-gradient">Apply Filters</button>
                    <a href="{{ route('tenders.index') }}" class="btn-pill btn-outline">Reset</a>
                </div>
            </form>
            <div class="tender-count-text">
                <span>Showing {{ $tenders->firstItem() ?? 0 }}–{{ $tenders->lastItem() ?? 0 }} of {{ $tenders->total() }} tenders</span>
            </div>
        </section>

        @if ($tenders->isEmpty())
            <section class="card-glow p-8 text-center">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-stack w-7 h-7 text-white"><path d="M21 7h-3a2 2 0 0 1-2-2V2"></path><path d="M21 6v6.5c0 .8-.7 1.5-1.5 1.5h-7c-.8 0-1.5-.7-1.5-1.5v-9c0-.8.7-1.5 1.5-1.5H17Z"></path><path d="M7 8v8.8c0 .3.2.6.4.8.2.2.5.4.8.4H15"></path><path d="M3 12v8.8c0 .3.2.6.4.8.2.2.5.4.8.4H11"></path></svg>
                </div>
                <h2 class="text-lg font-semibold text-foreground mb-2">No tenders yet</h2>
                <p class="text-sm text-muted-foreground mb-6 max-w-md mx-auto">Upload and materialize historical data first.</p>
                <div class="flex items-center justify-center gap-3 flex-wrap">
                    <a href="{{ route('uploads.index') }}" class="btn-pill btn-gradient">Upload Data</a>
                    <a href="{{ route('management.index') }}" class="btn-pill btn-outline">View Management</a>
                </div>
            </section>
        @else
            <section class="card-glow management-table-card">
                <div class="table-container-scroll management-table-scroll">
                    <table class="data-table management-data-table">
                        <thead>
                            <tr>
                                <th>Tender #</th>
                                <th>Name</th>
                                <th>Country</th>
                                <th>Year</th>
                                <th>Version</th>
                                <th>Items</th>
                                <th>Bids</th>
                                <th>Awarded</th>
                                <th>Companies</th>
                                <th>Drugs</th>
                                <th>Awarded Value</th>
                                <th>Avg USD</th>
                                <th>Last Activity</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tenders as $tender)
                                @php
                                    $activityStatus = $intel->resolveActivityStatus(
                                        (int) $tender->bid_records_count,
                                        $tender->last_activity_at ? (int) $tender->last_activity_at : ($tender->year ?: null),
                                    );
                                    $statusBadge = match ($activityStatus) {
                                        'active' => 'badge-status-won',
                                        'inactive' => 'badge-status-lost',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <tr>
                                    <td class="font-mono text-xs">{{ $tender->tender_number }}</td>
                                    <td>
                                        <span class="font-semibold text-foreground">{{ $intel->displayName($tender) }}</span>
                                    </td>
                                    <td>{{ $tender->country?->name ?? '—' }}</td>
                                    <td>{{ $tender->year ?? '—' }}</td>
                                    <td>{{ $tender->version ?? '—' }}</td>
                                    <td>{{ number_format($tender->tender_items_count) }}</td>
                                    <td>{{ number_format($tender->bid_records_count) }}</td>
                                    <td>{{ number_format($tender->awarded_count) }}</td>
                                    <td>{{ number_format((int) ($tender->companies_count ?? 0)) }}</td>
                                    <td>{{ number_format((int) ($tender->drugs_count ?? 0)) }}</td>
                                    <td>{{ $intel->formatMoney((float) ($tender->total_awarded_value_sum ?? 0)) }}</td>
                                    <td>{{ $tender->avg_price_usd ? '$'.number_format((float) $tender->avg_price_usd, 2) : '—' }}</td>
                                    <td class="text-xs">{{ $tender->last_activity_at ?? $tender->year ?? '—' }}</td>
                                    <td>
                                        <span class="badge-pill {{ $statusBadge }} text-[10px]">{{ ucfirst($tender->status ?? $activityStatus) }}</span>
                                    </td>
                                    <td class="management-actions-cell text-right">
                                        <a href="{{ route('tenders.show', $tender) }}" class="btn-pill btn-ghost btn-xs">View profile</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-border/40">
                    {{ $tenders->links() }}
                </div>
            </section>
        @endif
    </div>
</main>
@endsection
