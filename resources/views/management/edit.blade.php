@extends('layouts.app')

@section('title', 'Edit Bid Record #' . $bidRecord->id)

@section('content')
<main class="management-view">
    <div class="content-container-max fade-in-container">
        <header class="management-header">
            <div>
                <h1 class="page-title-gradient">Edit Bid Record #{{ $bidRecord->id }}</h1>
                <p class="page-subtitle">Update materialized analytics fields only — raw import data is not edited here.</p>
            </div>
            <div class="header-actions">
                <a href="{{ route('management.bid-records.show', $bidRecord) }}" class="btn-pill btn-outline">View Details</a>
                <a href="{{ route('management.index') }}" class="btn-pill btn-ghost">Back to Management</a>
            </div>
        </header>

        @if (session('success'))
            <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <div class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-100 text-sm text-amber-800">
            Run <code class="font-mono text-xs">php artisan stats:refresh</code> after editing pricing-related fields (price USD, awarded price, quantity, tender value).
        </div>

        @if ($errors->any())
            <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-100 text-sm text-red-700">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="filter-card card-glow">
            <form method="POST" action="{{ route('management.bid-records.update', $bidRecord) }}" class="management-edit-form">
                @csrf
                @method('PUT')

                <div class="filter-grid">
                    <div>
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Price USD</label>
                        <input type="number" step="any" name="price_usd" class="input-pill w-full" value="{{ old('price_usd', $bidRecord->price_usd) }}">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Awarded Price</label>
                        <input type="number" step="any" name="original_awarded_price" class="input-pill w-full" value="{{ old('original_awarded_price', $bidRecord->original_awarded_price) }}">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Quantity</label>
                        <input type="number" step="any" name="quantity" class="input-pill w-full" value="{{ old('quantity', $bidRecord->quantity) }}">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Tender Value</label>
                        <input type="number" step="any" name="tender_value" class="input-pill w-full" value="{{ old('tender_value', $bidRecord->tender_value) }}">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Bid Status</label>
                        <select name="bid_status" class="select-pill w-full">
                            @foreach ($bidStatuses as $status)
                                <option value="{{ $status }}" @selected(old('bid_status', $bidRecord->bid_status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Is Winner</label>
                        <select name="is_winner" class="select-pill w-full">
                            <option value="1" @selected(old('is_winner', $bidRecord->is_winner ? '1' : '0') == '1')>Yes</option>
                            <option value="0" @selected(old('is_winner', $bidRecord->is_winner ? '1' : '0') == '0')>No</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Analytics Ready</label>
                        <select name="is_analytics_ready" class="select-pill w-full">
                            <option value="1" @selected(old('is_analytics_ready', $bidRecord->is_analytics_ready ? '1' : '0') == '1')>Yes</option>
                            <option value="0" @selected(old('is_analytics_ready', $bidRecord->is_analytics_ready ? '1' : '0') == '0')>No</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Excluded From Stats</label>
                        <select name="excluded_from_stats" class="select-pill w-full">
                            <option value="1" @selected(old('excluded_from_stats', $bidRecord->excluded_from_stats ? '1' : '0') == '1')>Yes</option>
                            <option value="0" @selected(old('excluded_from_stats', $bidRecord->excluded_from_stats ? '1' : '0') == '0')>No</option>
                        </select>
                    </div>
                    <div class="filter-span-2">
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Exclusion Reason</label>
                        <input type="text" name="exclusion_reason" class="input-pill w-full" value="{{ old('exclusion_reason', $bidRecord->exclusion_reason) }}" placeholder="Required when excluding from statistics">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Company</label>
                        <select name="company_id" class="select-pill w-full">
                            <option value="">— None —</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected(old('company_id', $bidRecord->company_id) == $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Standardized Drug</label>
                        <select name="standardized_drug_id" class="select-pill w-full">
                            <option value="">— None —</option>
                            @foreach ($drugs as $drug)
                                <option value="{{ $drug->id }}" @selected(old('standardized_drug_id', $bidRecord->standardized_drug_id) == $drug->id)>{{ $drug->code }} — {{ $drug->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted-foreground block mb-1">Tender</label>
                        <select name="tender_id" class="select-pill w-full">
                            <option value="">— None —</option>
                            @foreach ($tenders as $tender)
                                <option value="{{ $tender->id }}" @selected(old('tender_id', $bidRecord->tender_id) == $tender->id)>{{ $tender->tender_number }} ({{ $tender->year }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="filter-actions-row mt-6">
                    <button type="submit" class="btn-pill btn-gradient">Save Changes</button>
                    <a href="{{ route('management.index') }}" class="btn-pill btn-outline">Cancel</a>
                </div>
            </form>
        </section>

        @if ($bidRecord->sourceImportRow)
            <section class="mt-6 filter-card card-glow opacity-90">
                <h3 class="filter-card-title mb-2">Source Import Row (read-only)</h3>
                <p class="text-xs text-muted-foreground mb-3">Raw import values are preserved as the source of truth.</p>
                <dl class="management-readonly-grid text-sm">
                    <div><dt>Raw Code</dt><dd>{{ $bidRecord->sourceImportRow->raw_code }}</dd></div>
                    <div><dt>Raw INN</dt><dd>{{ $bidRecord->sourceImportRow->raw_inn }}</dd></div>
                    <div><dt>Raw Product</dt><dd>{{ $bidRecord->sourceImportRow->raw_product_name }}</dd></div>
                    <div><dt>Raw Tender #</dt><dd>{{ $bidRecord->sourceImportRow->raw_tender_number }}</dd></div>
                </dl>
            </section>
        @endif
    </div>
</main>
@endsection
