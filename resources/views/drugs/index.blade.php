@extends('layouts.app')



@section('title', 'TenderAI - Drug Catalog')



@section('content')

<main class="drugs-view management-view">

    <div class="content-container-max fade-in-container">

        <header class="management-header">

            <div>

                <h1 class="page-title-gradient">Drug Catalog</h1>

                <p class="page-subtitle">Browse standardized products and tender activity. Open a drug profile for detailed pricing intelligence.</p>

            </div>

            <div class="count-badge">

                <span class="count-text">{{ number_format($drugs->total()) }} drugs</span>

            </div>

        </header>



        <section class="management-stats-grid mb-6">

            <x-stat-card label="Total Drugs" :value="number_format($stats['total_drugs'])" icon="pill" />

            <x-stat-card label="Total Bid Records" :value="number_format($stats['total_bid_records'])" icon="database" />

            <x-stat-card label="Countries" :value="number_format($stats['countries_count'])" icon="globe" />

            <x-stat-card label="Companies" :value="number_format($stats['companies_count'])" icon="building-2" />

            <x-stat-card label="With Pricing Data" :value="number_format($stats['drugs_with_pricing_stats'])" icon="bar-chart-3" tone="violet" />

        </section>



        <section class="filter-card card-glow mb-6">

            <div class="filter-card-header">

                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel icon-sm"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path></svg>

                <h3 class="filter-card-title">Filters &amp; Search</h3>

            </div>

            <form method="GET" action="{{ route('drugs.index') }}" class="management-filter-form">

                <div class="filter-grid">

                    <div class="search-wrapper filter-span-2">

                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search icon-xs search-icon-inside"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>

                        <input type="text" name="search" class="input-pill" placeholder="Code, INN, display name, alias..." value="{{ $filters['search'] ?? '' }}">

                    </div>

                    <select name="country_id" class="select-pill">

                        <option value="">All Countries</option>

                        @foreach ($filterOptions['countries'] as $country)

                            <option value="{{ $country->id }}" @selected(($filters['country_id'] ?? '') == $country->id)>{{ $country->name }}</option>

                        @endforeach

                    </select>

                    <select name="company_id" class="select-pill">

                        <option value="">All Companies</option>

                        @foreach ($filterOptions['companies'] as $company)

                            <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? '') == $company->id)>{{ $company->name }}</option>

                        @endforeach

                    </select>

                    <input type="text" name="tender_number" class="input-pill" placeholder="Tender #" value="{{ $filters['tender_number'] ?? '' }}">

                    <select name="year" class="select-pill">

                        <option value="">All Years</option>

                        @foreach ($filterOptions['years'] as $year)

                            <option value="{{ $year }}" @selected(($filters['year'] ?? '') == $year)>{{ $year }}</option>

                        @endforeach

                    </select>

                    <select name="bid_status" class="select-pill">

                        <option value="">All Bid Status</option>

                        @foreach ($bidStatuses as $status)

                            <option value="{{ $status }}" @selected(($filters['bid_status'] ?? '') === $status)>{{ ucfirst($status) }}</option>

                        @endforeach

                    </select>

                    <select name="has_pricing_statistics" class="select-pill">

                        <option value="all" @selected(($filters['has_pricing_statistics'] ?? 'all') === 'all')>Pricing data: All</option>

                        <option value="yes" @selected(($filters['has_pricing_statistics'] ?? '') === 'yes')>Pricing data: Yes</option>

                        <option value="no" @selected(($filters['has_pricing_statistics'] ?? '') === 'no')>Pricing data: No</option>

                    </select>

                    <input type="number" step="any" name="price_usd_min" class="input-pill" placeholder="Min bid price (filter)" value="{{ $filters['price_usd_min'] ?? '' }}">

                    <input type="number" step="any" name="price_usd_max" class="input-pill" placeholder="Max bid price (filter)" value="{{ $filters['price_usd_max'] ?? '' }}">

                    <select name="per_page" class="select-pill">

                        @foreach ($perPageOptions as $option)

                            <option value="{{ $option }}" @selected(($filters['per_page'] ?? 25) == $option)>{{ $option }} / page</option>

                        @endforeach

                    </select>

                </div>

                <div class="filter-actions-row">

                    <button type="submit" class="btn-pill btn-gradient">Apply Filters</button>

                    <a href="{{ route('drugs.index') }}" class="btn-pill btn-outline">Reset</a>

                </div>

            </form>

            <div class="tender-count-text">

                <span>Showing {{ $drugs->firstItem() ?? 0 }}–{{ $drugs->lastItem() ?? 0 }} of {{ $drugs->total() }} drugs</span>

            </div>

        </section>



        @if ($drugs->isEmpty())

            <section class="card-glow p-8 text-center">

                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center mx-auto mb-4 shadow-lg">

                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pill w-7 h-7 text-white"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"></path><path d="m8.5 8.5 7 7"></path></svg>

                </div>

                <h2 class="text-lg font-semibold text-foreground mb-2">No drugs yet</h2>

                <p class="text-sm text-muted-foreground mb-6 max-w-md mx-auto">Upload and process your tender history to build the drug catalog.</p>

                <div class="flex items-center justify-center gap-3 flex-wrap">

                    <a href="{{ route('uploads.index') }}" class="btn-pill btn-gradient">Upload Data</a>

                    <a href="{{ route('management.index') }}" class="btn-pill btn-outline">View Data Management</a>

                </div>

            </section>

        @else

            <section class="card-glow management-table-card">

                <div class="table-container-scroll management-table-scroll">

                    <table class="data-table management-data-table">

                        <thead>

                            <tr>

                                <th>Code</th>

                                <th>INN</th>

                                <th>Display Name</th>

                                <th>Form</th>

                                <th>Dosage</th>

                                <th>Bids</th>

                                <th>Awarded</th>

                                <th>Countries</th>

                                <th>Companies</th>

                                <th>Last Activity</th>

                                <th>Pricing Data</th>

                                <th class="text-right">Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                            @foreach ($drugs as $drug)

                                @php

                                    $activityStatus = $intel->resolveActivityStatus(

                                        (int) $drug->bid_records_count,

                                        $drug->last_activity_at ? (int) $drug->last_activity_at : null,

                                    );

                                @endphp

                                <tr>

                                    <td class="font-mono text-xs">{{ $drug->code }}</td>

                                    <td>{{ $drug->inn ?? '—' }}</td>

                                    <td class="font-semibold text-foreground">{{ $drug->display_name }}</td>

                                    <td>{{ $drug->form ?? '—' }}</td>

                                    <td>{{ $drug->dosage ?? $drug->strength ?? '—' }}</td>

                                    <td>{{ number_format($drug->bid_records_count) }}</td>

                                    <td>{{ number_format($drug->awarded_count) }}</td>

                                    <td>{{ number_format((int) ($drug->countries_count ?? 0)) }}</td>

                                    <td>{{ number_format((int) ($drug->companies_count ?? 0)) }}</td>

                                    <td class="text-xs">{{ $drug->last_activity_at ?? '—' }}</td>

                                    <td>

                                        @if ($drug->has_pricing_stats)

                                            <span class="badge-pill badge-status-won">Available</span>

                                        @else

                                            <span class="badge-pill bg-slate-100 text-slate-600">None</span>

                                        @endif

                                    </td>

                                    <td class="management-actions-cell text-right">

                                        <a href="{{ route('drugs.show', $drug) }}" class="btn-pill btn-ghost btn-xs">View pricing profile</a>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

                <div class="p-4 border-t border-border/40">

                    {{ $drugs->links() }}

                </div>

            </section>

        @endif

    </div>

</main>

@endsection

