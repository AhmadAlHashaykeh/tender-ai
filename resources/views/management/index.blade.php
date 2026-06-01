@extends('layouts.app')

@section('title', 'TenderAI - Tender Data Management')

@section('content')
<main class="management-view">
    <div class="content-container-max fade-in-container">
        <header class="management-header">
            <div>
                <h1 class="page-title-gradient">Tender Data Management</h1>
                <p class="page-subtitle">View, filter, search, and edit processed bid records</p>
            </div>
            <div class="header-actions">
                <a href="{{ route('uploads.index') }}" class="btn-pill btn-gradient">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" x2="12" y1="3" y2="15"></line></svg>
                    Upload Data
                </a>
            </div>
        </header>

        @if (session('success'))
            <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <section class="management-stats-grid mb-6">
            <x-stat-card label="Total Records" :value="number_format($stats['total'])" icon="database" />
            <x-stat-card label="Analytics Ready" :value="number_format($stats['analytics_ready'])" icon="check-circle" tone="emerald" />
            <x-stat-card label="Excluded" :value="number_format($stats['excluded_from_stats'])" icon="ban" tone="amber" />
            <x-stat-card label="Countries" :value="number_format($stats['countries'])" icon="globe" />
            <x-stat-card label="Companies" :value="number_format($stats['companies'])" icon="building-2" />
            <x-stat-card label="Drugs" :value="number_format($stats['drugs'])" icon="pill" />
            <x-stat-card label="Awarded" :value="number_format($stats['awarded'])" icon="trophy" tone="violet" />
        </section>

        <section class="filter-card card-glow mb-6">
            <div class="filter-card-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel icon-sm"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path></svg>
                <h3 class="filter-card-title">Filters &amp; Search</h3>
            </div>
            <form method="GET" action="{{ route('management.index') }}" class="management-filter-form">
                <div class="filter-grid">
                    <div class="search-wrapper filter-span-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search icon-xs search-icon-inside"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                        <input type="text" name="search" class="input-pill" placeholder="Code, INN, product, company, tender #..." value="{{ $filters['search'] ?? '' }}">
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
                    <input type="text" name="tender_number" class="input-pill" placeholder="Tender #" value="{{ $filters['tender_number'] ?? '' }}">
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
                    <select name="analytics_ready" class="select-pill">
                        <option value="all" @selected(($filters['analytics_ready'] ?? 'all') === 'all')>Analytics: All</option>
                        <option value="yes" @selected(($filters['analytics_ready'] ?? '') === 'yes')>Analytics: Yes</option>
                        <option value="no" @selected(($filters['analytics_ready'] ?? '') === 'no')>Analytics: No</option>
                    </select>
                    <select name="winner" class="select-pill">
                        <option value="all" @selected(($filters['winner'] ?? 'all') === 'all')>Winner: All</option>
                        <option value="winner" @selected(($filters['winner'] ?? '') === 'winner')>Winner Only</option>
                        <option value="non_winner" @selected(($filters['winner'] ?? '') === 'non_winner')>Non-Winner</option>
                    </select>
                    <select name="excluded" class="select-pill">
                        <option value="all" @selected(($filters['excluded'] ?? 'all') === 'all')>Stats: All</option>
                        <option value="excluded" @selected(($filters['excluded'] ?? '') === 'excluded')>Excluded</option>
                        <option value="included" @selected(($filters['excluded'] ?? '') === 'included')>Included</option>
                    </select>
                    <select name="import_batch_id" class="select-pill">
                        <option value="">All Import Batches</option>
                        @foreach ($filterOptions['import_batches'] as $batch)
                            <option value="{{ $batch->id }}" @selected(($filters['import_batch_id'] ?? '') == $batch->id)>#{{ $batch->id }} — {{ $batch->original_filename ?? $batch->filename }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="any" name="price_min" class="input-pill" placeholder="Min Price USD" value="{{ $filters['price_min'] ?? '' }}">
                    <input type="number" step="any" name="price_max" class="input-pill" placeholder="Max Price USD" value="{{ $filters['price_max'] ?? '' }}">
                    <input type="number" step="any" name="qty_min" class="input-pill" placeholder="Min Qty" value="{{ $filters['qty_min'] ?? '' }}">
                    <input type="number" step="any" name="qty_max" class="input-pill" placeholder="Max Qty" value="{{ $filters['qty_max'] ?? '' }}">
                    <select name="per_page" class="select-pill">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected(($filters['per_page'] ?? 25) == $option)>{{ $option }} / page</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-actions-row">
                    <button type="submit" class="btn-pill btn-gradient">Apply Filters</button>
                    <a href="{{ route('management.index') }}" class="btn-pill btn-outline">Reset</a>
                </div>
            </form>
            <div class="tender-count-text">
                <span>Showing {{ $bidRecords->firstItem() ?? 0 }}–{{ $bidRecords->lastItem() ?? 0 }} of {{ $bidRecords->total() }} records</span>
            </div>
        </section>

        <section class="card-glow management-table-card">
            <div class="table-container-scroll management-table-scroll">
                <table class="data-table management-data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>INN</th>
                            <th>Product</th>
                            <th>Country</th>
                            <th>Tender #</th>
                            <th>Ver.</th>
                            <th>Year</th>
                            <th>Qty</th>
                            <th>Price USD</th>
                            <th>Awarded</th>
                            <th>Tender Val.</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Winner</th>
                            <th>Analytics</th>
                            <th>Batch</th>
                            <th>Created</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bidRecords as $record)
                            @php
                                $code = $record->standardizedDrug?->code ?? $record->sourceImportRow?->raw_code;
                                $inn = $record->standardizedDrug?->inn ?? $record->sourceImportRow?->raw_inn;
                                $product = $record->tenderItem?->description ?? $record->sourceImportRow?->raw_product_name;
                            @endphp
                            <tr>
                                <td class="font-mono text-xs">{{ $record->id }}</td>
                                <td>{{ $code ?? '—' }}</td>
                                <td>{{ $inn ?? '—' }}</td>
                                <td class="max-w-[12rem] truncate" title="{{ $product }}">{{ $product ?? '—' }}</td>
                                <td>{{ $record->country?->name ?? '—' }}</td>
                                <td>{{ $record->tender?->tender_number ?? $record->sourceImportRow?->raw_tender_number ?? '—' }}</td>
                                <td>{{ $record->tender?->version ?? $record->sourceImportRow?->raw_version ?? '—' }}</td>
                                <td>{{ $record->award_year ?? $record->tender?->year ?? $record->sourceImportRow?->raw_year ?? '—' }}</td>
                                <td>{{ $record->quantity !== null ? number_format((float) $record->quantity, 0) : '—' }}</td>
                                <td>{{ $record->price_usd !== null ? number_format((float) $record->price_usd, 2) : '—' }}</td>
                                <td>{{ $record->original_awarded_price !== null ? number_format((float) $record->original_awarded_price, 2) : '—' }}</td>
                                <td>{{ $record->tender_value !== null ? number_format((float) $record->tender_value, 0) : '—' }}</td>
                                <td class="max-w-[10rem] truncate" title="{{ $record->company?->name }}">{{ $record->company?->name ?? $record->sourceImportRow?->raw_company_name ?? '—' }}</td>
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
                                <td>
                                    @if ($record->import_batch_id)
                                        <a href="{{ route('imports.show', $record->import_batch_id) }}" class="text-primary text-xs font-semibold">#{{ $record->import_batch_id }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-xs text-muted-foreground whitespace-nowrap">{{ $record->created_at?->format('Y-m-d') }}</td>
                                <td class="management-actions-cell">
                                    <div class="management-row-actions">
                                        <a href="{{ route('management.bid-records.show', $record) }}" class="btn-pill btn-ghost btn-xs" title="View">View</a>
                                        <a href="{{ route('management.bid-records.edit', $record) }}" class="btn-pill btn-ghost btn-xs" title="Edit">Edit</a>
                                        <form method="POST" action="{{ route('management.bid-records.toggle-exclusion', $record) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn-pill btn-ghost btn-xs" title="{{ $record->excluded_from_stats ? 'Include in stats' : 'Exclude from stats' }}">
                                                {{ $record->excluded_from_stats ? 'Include' : 'Exclude' }}
                                            </button>
                                        </form>
                                    </div>
                                    @if ($record->excluded_from_stats)
                                        <span class="badge-pill badge-status-lost mt-1">Excluded</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="19" class="text-center py-10 text-muted-foreground">
                                    No bid records found. <a href="{{ route('uploads.index') }}" class="text-primary font-semibold">Import data</a> and materialize batches first.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($bidRecords->hasPages())
                <div class="management-pagination px-4 py-3 border-t border-border/30">
                    {{ $bidRecords->links() }}
                </div>
            @endif
        </section>
    </div>
</main>
@endsection
