@extends('layouts.app')

@section('title', 'TenderAI - Company Intelligence')

@section('content')
<main class="companies-view management-view">
    <div class="content-container-max fade-in-container">
        <header class="management-header">
            <div>
                <h1 class="page-title-gradient">Company Intelligence</h1>
                <p class="page-subtitle">Company registry grouped by tender bid activity</p>
            </div>
            <div class="count-badge">
                <span class="count-text">{{ number_format($companies->total()) }} companies</span>
            </div>
        </header>

        <section class="management-stats-grid mb-6">
            <x-stat-card label="Total Companies" :value="number_format($stats['total_companies'])" icon="building-2" />
            <x-stat-card label="With Awarded Records" :value="number_format($stats['companies_with_awarded'])" icon="trophy" tone="emerald" />
            <x-stat-card label="Total Bid Records" :value="number_format($stats['total_bid_records'])" icon="database" />
            <x-stat-card label="Total Awarded Value" :value="$intel->formatMoney($stats['total_awarded_value'])" icon="dollar-sign" tone="violet" />
            <x-stat-card label="Avg Records / Company" :value="number_format($stats['avg_bid_records_per_company'], 1)" icon="bar-chart-3" />
            @if ($stats['top_country_name'])
                <x-stat-card label="Top Country" :value="$stats['top_country_name'].' ('.number_format($stats['top_country_count']).')'" icon="globe" />
            @endif
        </section>

        <section class="filter-card card-glow mb-6">
            <div class="filter-card-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel icon-sm"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path></svg>
                <h3 class="filter-card-title">Filters &amp; Search</h3>
            </div>
            <form method="GET" action="{{ route('companies.index') }}" class="management-filter-form">
                <div class="filter-grid">
                    <div class="search-wrapper filter-span-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search icon-xs search-icon-inside"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                        <input type="text" name="search" class="input-pill" placeholder="Company name or alias..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <select name="country_id" class="select-pill">
                        <option value="">All Countries</option>
                        @foreach ($filterOptions['countries'] as $country)
                            <option value="{{ $country->id }}" @selected(($filters['country_id'] ?? '') == $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                    <select name="bid_status" class="select-pill">
                        <option value="">All Bid Status</option>
                        @foreach ($bidStatuses as $status)
                            <option value="{{ $status }}" @selected(($filters['bid_status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <select name="year" class="select-pill">
                        <option value="">All Years</option>
                        @foreach ($filterOptions['years'] as $year)
                            <option value="{{ $year }}" @selected(($filters['year'] ?? '') == $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="tender_number" class="input-pill" placeholder="Tender #" value="{{ $filters['tender_number'] ?? '' }}">
                    <select name="standardized_drug_id" class="select-pill">
                        <option value="">All Drugs</option>
                        @foreach ($filterOptions['drugs'] as $drug)
                            <option value="{{ $drug->id }}" @selected(($filters['standardized_drug_id'] ?? '') == $drug->id)>{{ $drug->code }} — {{ $drug->display_name ?? $drug->inn }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="any" name="awarded_value_min" class="input-pill" placeholder="Min awarded value" value="{{ $filters['awarded_value_min'] ?? '' }}">
                    <input type="number" step="any" name="awarded_value_max" class="input-pill" placeholder="Max awarded value" value="{{ $filters['awarded_value_max'] ?? '' }}">
                    <select name="has_awarded" class="select-pill">
                        <option value="all" @selected(($filters['has_awarded'] ?? 'all') === 'all')>Has awarded: All</option>
                        <option value="yes" @selected(($filters['has_awarded'] ?? '') === 'yes')>Has awarded: Yes</option>
                        <option value="no" @selected(($filters['has_awarded'] ?? '') === 'no')>Has awarded: No</option>
                    </select>
                    <select name="per_page" class="select-pill">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected(($filters['per_page'] ?? 25) == $option)>{{ $option }} / page</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-actions-row">
                    <button type="submit" class="btn-pill btn-gradient">Apply Filters</button>
                    <a href="{{ route('companies.index') }}" class="btn-pill btn-outline">Reset</a>
                </div>
            </form>
            <div class="tender-count-text">
                <span>Showing {{ $companies->firstItem() ?? 0 }}–{{ $companies->lastItem() ?? 0 }} of {{ $companies->total() }} companies</span>
            </div>
        </section>

        @if ($companies->isEmpty())
            <section class="card-glow p-8 text-center">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 w-7 h-7 text-white"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path><path d="M10 6h4"></path><path d="M10 10h4"></path><path d="M10 14h4"></path><path d="M10 18h4"></path></svg>
                </div>
                <h2 class="text-lg font-semibold text-foreground mb-2">No companies yet</h2>
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
                                <th>Company</th>
                                <th>Country</th>
                                <th>Bid Records</th>
                                <th>Awarded Wins</th>
                                <th>Win Rate</th>
                                <th>Total Awarded Value</th>
                                <th>Avg Price USD</th>
                                <th>Countries</th>
                                <th>Drugs</th>
                                <th>Last Activity</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($companies as $company)
                                @php
                                    $winRate = $intel->winRatePresentation(
                                        (int) $company->awarded_count,
                                        (int) $company->lost_count,
                                        (int) $company->participated_count,
                                    );
                                    $activityStatus = $intel->resolveActivityStatus(
                                        (int) $company->bid_records_count,
                                        $company->last_activity_at ? (int) $company->last_activity_at : null,
                                    );
                                    $statusBadge = match ($activityStatus) {
                                        'active' => 'badge-status-won',
                                        'inactive' => 'badge-status-lost',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-semibold text-foreground">{{ $company->name }}</span>
                                            <span class="badge-pill {{ $statusBadge }} text-[10px]">{{ ucfirst($activityStatus) }}</span>
                                        </div>
                                        @if ($company->last_drug_name)
                                            <div class="text-xs text-muted-foreground mt-0.5">Last drug: {{ $company->last_drug_name }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $company->country?->name ?? '—' }}</td>
                                    <td>{{ number_format($company->bid_records_count) }}</td>
                                    <td>{{ number_format($company->awarded_count) }}</td>
                                    <td>
                                        <span class="badge-pill {{ $winRate['tone'] === 'amber' ? 'badge-status-won' : ($winRate['tone'] === 'primary' ? 'badge-brand' : 'bg-slate-100 text-slate-600') }}">
                                            {{ $winRate['label'] }}
                                        </span>
                                    </td>
                                    <td>{{ $intel->formatMoney((float) ($company->total_awarded_value_sum ?? 0)) }}</td>
                                    <td>{{ $company->avg_awarded_price_usd ? '$'.number_format((float) $company->avg_awarded_price_usd, 2) : '—' }}</td>
                                    <td>{{ number_format((int) ($company->countries_involved_count ?? 0)) }}</td>
                                    <td>{{ number_format((int) ($company->unique_drugs_count ?? 0)) }}</td>
                                    <td class="text-xs whitespace-nowrap">
                                        @if ($company->last_activity_at)
                                            {{ $company->last_activity_at }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="management-actions-cell text-right">
                                        <a href="{{ route('companies.show', $company) }}" class="btn-pill btn-ghost btn-xs">View profile</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-border/40">
                    {{ $companies->links() }}
                </div>
            </section>
        @endif
    </div>
</main>
@endsection
