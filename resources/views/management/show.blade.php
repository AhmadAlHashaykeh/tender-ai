@extends('layouts.app')

@section('title', 'Bid Record #' . $bidRecord->id)

@section('content')
<main class="management-view">
    <div class="content-container-max fade-in-container">
        <header class="management-header">
            <div>
                <h1 class="page-title-gradient">Bid Record #{{ $bidRecord->id }}</h1>
                <p class="page-subtitle">Materialized record with source import and domain links</p>
            </div>
            <div class="header-actions">
                <a href="{{ route('management.bid-records.edit', $bidRecord) }}" class="btn-pill btn-gradient">Edit</a>
                <a href="{{ route('management.index') }}" class="btn-pill btn-outline">Back</a>
            </div>
        </header>

        @if (session('success'))
            <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <div class="management-detail-grid">
            <section class="filter-card card-glow">
                <h3 class="filter-card-title mb-4">Bid Record</h3>
                <dl class="management-readonly-grid">
                    <div><dt>Status</dt><dd><x-bid-status-badge :status="$bidRecord->bid_status" /></dd></div>
                    <div><dt>Winner</dt><dd>{{ $bidRecord->is_winner ? 'Yes' : 'No' }}</dd></div>
                    <div><dt>Analytics Ready</dt><dd>{{ $bidRecord->is_analytics_ready ? 'Yes' : 'No' }}</dd></div>
                    <div><dt>Excluded From Stats</dt><dd>{{ $bidRecord->excluded_from_stats ? 'Yes' : 'No' }}</dd></div>
                    @if ($bidRecord->exclusion_reason)
                        <div><dt>Exclusion Reason</dt><dd>{{ $bidRecord->exclusion_reason }}</dd></div>
                    @endif
                    <div><dt>Price USD</dt><dd>{{ $bidRecord->price_usd ?? '—' }}</dd></div>
                    <div><dt>Awarded Price</dt><dd>{{ $bidRecord->original_awarded_price ?? '—' }}</dd></div>
                    <div><dt>Quantity</dt><dd>{{ $bidRecord->quantity ?? '—' }}</dd></div>
                    <div><dt>Tender Value</dt><dd>{{ $bidRecord->tender_value ?? '—' }}</dd></div>
                    <div><dt>Award Year</dt><dd>{{ $bidRecord->award_year ?? '—' }}</dd></div>
                    <div><dt>Row Type</dt><dd>{{ $bidRecord->row_type }}</dd></div>
                    <div><dt>Created</dt><dd>{{ $bidRecord->created_at?->format('Y-m-d H:i') }}</dd></div>
                </dl>
                <form method="POST" action="{{ route('management.bid-records.toggle-exclusion', $bidRecord) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="btn-pill btn-outline">
                        {{ $bidRecord->excluded_from_stats ? 'Include in Statistics' : 'Exclude from Statistics' }}
                    </button>
                </form>
            </section>

            <section class="filter-card card-glow">
                <h3 class="filter-card-title mb-4">Tender &amp; Item</h3>
                <dl class="management-readonly-grid">
                    <div><dt>Tender #</dt><dd>{{ $bidRecord->tender?->tender_number ?? '—' }}</dd></div>
                    <div><dt>Version</dt><dd>{{ $bidRecord->tender?->version ?? '—' }}</dd></div>
                    <div><dt>Year</dt><dd>{{ $bidRecord->tender?->year ?? $bidRecord->award_year ?? '—' }}</dd></div>
                    <div><dt>Country</dt><dd>{{ $bidRecord->country?->name ?? $bidRecord->tender?->country?->name ?? '—' }}</dd></div>
                    <div><dt>Product</dt><dd>{{ $bidRecord->tenderItem?->description ?? '—' }}</dd></div>
                </dl>
            </section>

            <section class="filter-card card-glow">
                <h3 class="filter-card-title mb-4">Drug &amp; Company</h3>
                <dl class="management-readonly-grid">
                    <div><dt>Drug Code</dt><dd>{{ $bidRecord->standardizedDrug?->code ?? '—' }}</dd></div>
                    <div><dt>INN</dt><dd>{{ $bidRecord->standardizedDrug?->inn ?? '—' }}</dd></div>
                    <div><dt>Display Name</dt><dd>{{ $bidRecord->standardizedDrug?->display_name ?? '—' }}</dd></div>
                    <div><dt>Company</dt><dd>{{ $bidRecord->company?->name ?? '—' }}</dd></div>
                </dl>
            </section>

            @if ($bidRecord->sourceImportRow)
                <section class="filter-card card-glow management-detail-span-2">
                    <h3 class="filter-card-title mb-4">Source Import Row (raw)</h3>
                    <dl class="management-readonly-grid">
                        <div><dt>Row #</dt><dd>{{ $bidRecord->sourceImportRow->row_number }}</dd></div>
                        <div><dt>Raw Code</dt><dd>{{ $bidRecord->sourceImportRow->raw_code }}</dd></div>
                        <div><dt>Raw INN</dt><dd>{{ $bidRecord->sourceImportRow->raw_inn }}</dd></div>
                        <div><dt>Raw Product</dt><dd>{{ $bidRecord->sourceImportRow->raw_product_name }}</dd></div>
                        <div><dt>Raw Country</dt><dd>{{ $bidRecord->sourceImportRow->raw_country }}</dd></div>
                        <div><dt>Raw Tender #</dt><dd>{{ $bidRecord->sourceImportRow->raw_tender_number }}</dd></div>
                        <div><dt>Raw Company</dt><dd>{{ $bidRecord->sourceImportRow->raw_company_name }}</dd></div>
                        <div><dt>Raw Winner</dt><dd>{{ $bidRecord->sourceImportRow->raw_winner }}</dd></div>
                        <div><dt>Raw Price USD</dt><dd>{{ $bidRecord->sourceImportRow->raw_price_usd }}</dd></div>
                        <div><dt>Raw Awarded</dt><dd>{{ $bidRecord->sourceImportRow->raw_awarded_price }}</dd></div>
                    </dl>
                    @if ($bidRecord->sourceImportRow->normalized_data)
                        <h4 class="text-sm font-semibold mt-4 mb-2">Normalized Data</h4>
                        <pre class="management-json-preview text-xs">{{ json_encode($bidRecord->sourceImportRow->normalized_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @endif
                </section>
            @endif

            <section class="filter-card card-glow">
                <h3 class="filter-card-title mb-4">Import / Materialization</h3>
                <dl class="management-readonly-grid">
                    <div><dt>Import Batch</dt><dd>
                        @if ($bidRecord->importBatch)
                            <a href="{{ route('imports.show', $bidRecord->importBatch) }}" class="text-primary font-semibold">#{{ $bidRecord->importBatch->id }} — {{ $bidRecord->importBatch->original_filename ?? $bidRecord->importBatch->filename }}</a>
                        @else
                            —
                        @endif
                    </dd></div>
                    <div><dt>Materialization</dt><dd>{{ data_get($bidRecord->sourceImportRow?->normalized_data, 'materialization_status', '—') }}</dd></div>
                </dl>
                @if ($bidRecord->metadata)
                    <h4 class="text-sm font-semibold mt-4 mb-2">Record Metadata</h4>
                    <pre class="management-json-preview text-xs">{{ json_encode($bidRecord->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
            </section>
        </div>
    </div>
</main>
@endsection
