@extends('layouts.app')

@section('title', 'TenderAI - Upload Data')

@section('content')
@php
    $inputClass = 'file:text-foreground placeholder:text-muted-foreground flex w-full min-w-0 border px-3 py-1.5 transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] h-9 text-xs rounded-lg border-border/50 bg-slate-50/60 focus:bg-white';
    $labelClass = 'text-[10px] font-semibold text-muted-foreground uppercase tracking-wide block mb-1';
@endphp
<main class="p-6 min-h-screen">
    <div class="space-y-6 max-w-6xl mx-auto">
        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="p-4 rounded-xl bg-red-50 border border-red-100 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <div class="flex items-center gap-3 mb-1">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center shadow-md">
                    <i data-lucide="upload" class="w-4 h-4 text-white"></i>
                </div>
                <h1 class="text-2xl font-bold text-foreground">Data Entry Hub</h1>
            </div>
            <p class="text-sm text-muted-foreground ml-0.5">Historical awards and upcoming tenders feed product matching, data processing, market statistics, and price recommendations.</p>
        </div>

        <div class="flex flex-wrap gap-3 px-4 py-3 rounded-xl bg-white border border-border/40 shadow-sm text-xs text-muted-foreground">
            <span><span class="font-semibold text-foreground">Historical data</span> — Excel or manual row → import pipeline</span>
            <span class="text-border">|</span>
            <span><span class="font-semibold text-foreground">Upcoming tenders</span> — future bids, no bid records</span>
        </div>

        {{-- Section 1: Excel Upload --}}
        <section class="bg-white rounded-2xl border-2 border-primary/20 shadow-sm overflow-hidden">
            <div class="h-0.5 bg-gradient-to-r from-primary/60 via-secondary/60 to-primary/60"></div>
            <div class="p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center shadow-md shrink-0">
                        <i data-lucide="file-spreadsheet" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-foreground text-base">1. Upload Historical Excel Data</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">Bulk import winning bids. The first row must include every column below (case/spacing insensitive; common aliases such as &quot;Tender Number&quot; for Tender # are accepted).</p>
                    </div>
                </div>

                <div class="rounded-xl border border-border/40 bg-slate-50/50 p-3">
                    <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wide mb-2">Required column headers</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (['Code', 'INN', 'Product', 'Country', 'Tender #', 'Awarded', 'USD', 'Winner', 'Company', 'Ver', 'Year', 'Qty', 'Value'] as $header)
                            <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-mono font-medium border bg-white border-border/50 text-foreground/80">{{ $header }}</span>
                        @endforeach
                    </div>
                </div>

                <form id="uploadForm" action="{{ route('uploads.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div id="dropzone" class="relative min-h-[100px] rounded-xl border-2 border-dashed flex flex-col items-center justify-center gap-2 transition-all cursor-pointer border-border/50 hover:border-primary/40 hover:bg-primary/3">
                        <input type="file" name="file" id="file-upload" accept=".xlsx,.xls,.csv" class="hidden" required>
                        <i data-lucide="upload" class="w-7 h-7 text-muted-foreground/50"></i>
                        <p class="text-xs font-medium text-muted-foreground">Drop file here or <span class="text-primary font-semibold">browse</span></p>
                        <p class="text-[10px] text-muted-foreground/60">.xlsx · .csv (legacy .xls: save as .xlsx)</p>
                    </div>
                    <div id="fileInfoContainer" class="file-info-container mt-2">
                        <div class="file-item">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4 text-primary"></i>
                            <div class="file-details">
                                <p id="fileName" class="file-name">filename.xlsx</p>
                                <p id="fileSize" class="file-size">0 KB</p>
                            </div>
                            <button class="btn-icon" id="removeFile" type="button" aria-label="Remove file">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    @error('file')
                        <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                    @enderror
                    <button type="submit" id="uploadSubmitBtn" disabled class="mt-3 w-full h-9 rounded-xl bg-gradient-to-r from-primary to-secondary text-white text-xs font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                        Upload &amp; Process
                    </button>
                </form>

                <div class="border-t border-border/30 pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-foreground">Recent import batches</h3>
                        <a href="{{ route('imports.index') }}" class="text-[10px] text-primary font-semibold hover:underline">View all →</a>
                    </div>
                    <div class="overflow-x-auto rounded-xl border border-border/40">
                        <table class="w-full text-[11px]">
                            <thead class="bg-slate-50 border-b border-border/40">
                                <tr>
                                    <th class="text-left px-4 py-2.5 font-semibold text-muted-foreground">Source</th>
                                    <th class="text-left px-4 py-2.5 font-semibold text-muted-foreground">Status</th>
                                    <th class="text-right px-4 py-2.5 font-semibold text-muted-foreground">Total</th>
                                    <th class="text-right px-4 py-2.5 font-semibold text-muted-foreground">Valid</th>
                                    <th class="text-right px-4 py-2.5 font-semibold text-muted-foreground">Invalid</th>
                                    <th class="text-right px-4 py-2.5 font-semibold text-muted-foreground"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentBatches as $batch)
                                    @php $meta = $batch->metadata ?? []; @endphp
                                    <tr class="border-b border-border/30">
                                        <td class="px-4 py-2.5 font-medium text-foreground">
                                            {{ $batch->original_filename ?? $batch->filename }}
                                            @if ($batch->source_type === 'manual')
                                                <span class="text-[9px] text-violet-600 font-semibold ml-1">manual</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5"><x-import-status-badge :status="$batch->status" /></td>
                                        <td class="px-4 py-2.5 text-right">{{ $batch->row_count }}</td>
                                        <td class="px-4 py-2.5 text-right text-emerald-600">{{ $meta['valid_rows'] ?? $batch->success_count }}</td>
                                        <td class="px-4 py-2.5 text-right text-red-500">{{ $meta['invalid_rows'] ?? $batch->error_count }}</td>
                                        <td class="px-4 py-2.5 text-right">
                                            @if ($batch->row_count > 0)
                                                <a href="{{ route('imports.show', $batch) }}" class="text-primary font-semibold">Details</a>
                                            @else
                                                <span class="text-muted-foreground">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-muted-foreground">No imports yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        {{-- Section 2: Manual Historical --}}
        <section class="bg-white rounded-2xl border border-border/50 shadow-sm">
            <div class="p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-md shrink-0">
                        <i data-lucide="pen-line" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-foreground text-base">2. Add Historical Row Manually</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">Same fields as Excel. Creates an <code class="text-[10px] bg-muted/40 px-1 rounded">import_batch</code> + <code class="text-[10px] bg-muted/40 px-1 rounded">import_row</code> — then run standardize → materialize → stats:refresh.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('uploads.manual.store') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div>
                            <label class="{{ $labelClass }}" for="manual_code">Code</label>
                            <input type="text" name="code" id="manual_code" value="{{ old('code') }}" class="{{ $inputClass }}">
                            @error('code')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_inn">INN</label>
                            <input type="text" name="inn" id="manual_inn" value="{{ old('inn') }}" class="{{ $inputClass }}">
                            @error('inn')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_product_name">Product Name</label>
                            <input type="text" name="product_name" id="manual_product_name" value="{{ old('product_name') }}" class="{{ $inputClass }}">
                            @error('product_name')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_country">Country <span class="text-primary">*</span></label>
                            <input type="text" name="country" id="manual_country" value="{{ old('country') }}" list="country-list" required class="{{ $inputClass }}" placeholder="e.g. KSA or Saudi Arabia">
                            @error('country')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_tender_number">Tender #</label>
                            <input type="text" name="tender_number" id="manual_tender_number" value="{{ old('tender_number') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_awarded_price">Awarded price</label>
                            <input type="number" step="any" min="0" name="awarded_price" id="manual_awarded_price" value="{{ old('awarded_price') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_price_usd">Price USD <span class="text-primary">*</span></label>
                            <input type="number" step="any" min="0" name="price_usd" id="manual_price_usd" value="{{ old('price_usd') }}" required class="{{ $inputClass }}">
                            @error('price_usd')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_winner">Winner</label>
                            <input type="text" name="winner" id="manual_winner" value="{{ old('winner') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_company_name">Company Name</label>
                            <input type="text" name="company_name" id="manual_company_name" value="{{ old('company_name') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_version">Version</label>
                            <input type="text" name="version" id="manual_version" value="{{ old('version') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_year">Year <span class="text-primary">*</span></label>
                            <input type="number" name="year" id="manual_year" value="{{ old('year', date('Y')) }}" min="1900" max="2100" required class="{{ $inputClass }}">
                            @error('year')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_qty">Qty</label>
                            <input type="number" step="any" min="0" name="qty" id="manual_qty" value="{{ old('qty') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="manual_tender_value">Tender Value</label>
                            <input type="number" step="any" min="0" name="tender_value" id="manual_tender_value" value="{{ old('tender_value') }}" class="{{ $inputClass }}">
                        </div>
                    </div>
                    <button type="submit" class="w-full sm:w-auto px-6 h-9 rounded-xl bg-gradient-to-r from-violet-500 to-purple-600 text-white text-xs font-semibold">
                        Save historical row
                    </button>
                </form>
            </div>
        </section>

        {{-- Section 3: Upcoming Tenders --}}
        <section class="bg-white rounded-2xl border border-amber-200/60 shadow-sm">
            <div class="p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-md shrink-0">
                        <i data-lucide="calendar-clock" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-foreground text-base">3. Add Upcoming Tender</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Upcoming tenders are used for future predictions and do not create bid records.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('uploads.upcoming-tenders.store') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="{{ $labelClass }}" for="upcoming_tender_name">Tender Name <span class="text-primary">*</span></label>
                            <input type="text" name="tender_name" id="upcoming_tender_name" value="{{ old('tender_name') }}" required class="{{ $inputClass }}">
                            @error('tender_name')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="upcoming_tender_number">Tender # <span class="text-primary">*</span></label>
                            <input type="text" name="tender_number" id="upcoming_tender_number" value="{{ old('tender_number') }}" required class="{{ $inputClass }}">
                            @error('tender_number')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="upcoming_country">Country <span class="text-primary">*</span></label>
                            <input type="text" name="country" id="upcoming_country" value="{{ old('country') }}" list="country-list" required class="{{ $inputClass }}">
                            @error('country')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="upcoming_year">Year <span class="text-primary">*</span></label>
                            <input type="number" name="year" id="upcoming_year" value="{{ old('year', date('Y') + 1) }}" min="1900" max="2100" required class="{{ $inputClass }}">
                            @error('year')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="upcoming_version">Version</label>
                            <input type="text" name="version" id="upcoming_version" value="{{ old('version') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="upcoming_expected_code">Expected Code</label>
                            <input type="text" name="expected_code" id="upcoming_expected_code" value="{{ old('expected_code') }}" class="{{ $inputClass }}">
                            @error('expected_code')<p class="text-[10px] text-red-600 mt-0.5">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="upcoming_expected_inn">Expected INN</label>
                            <input type="text" name="expected_inn" id="upcoming_expected_inn" value="{{ old('expected_inn') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="upcoming_expected_product_name">Expected Drug / Product Name</label>
                            <input type="text" name="expected_product_name" id="upcoming_expected_product_name" value="{{ old('expected_product_name') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="upcoming_expected_qty">Expected Qty</label>
                            <input type="number" step="any" min="0" name="expected_qty" id="upcoming_expected_qty" value="{{ old('expected_qty') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="upcoming_closing_date">Expected closing date</label>
                            <input type="date" name="expected_closing_date" id="upcoming_closing_date" value="{{ old('expected_closing_date') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="upcoming_authority">Authority / buyer</label>
                            <input type="text" name="authority" id="upcoming_authority" value="{{ old('authority') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}" for="upcoming_category">Category</label>
                            <input type="text" name="category" id="upcoming_category" value="{{ old('category') }}" class="{{ $inputClass }}">
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label class="{{ $labelClass }}" for="upcoming_notes">Notes</label>
                            <textarea name="notes" id="upcoming_notes" rows="2" class="{{ $inputClass }} min-h-[4rem]">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                    <button type="submit" class="w-full sm:w-auto px-6 h-9 rounded-xl bg-gradient-to-r from-amber-400 to-orange-500 text-white text-xs font-semibold">
                        Save upcoming tender
                    </button>
                </form>

                @if ($recentUpcomingTenders->isNotEmpty())
                    <div class="border-t border-border/30 pt-3">
                        <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wide mb-2">Recently added upcoming</p>
                        <ul class="space-y-1.5 text-xs">
                            @foreach ($recentUpcomingTenders as $t)
                                <li class="flex justify-between gap-2 text-foreground/80">
                                    <span class="truncate font-medium">{{ $t->title }} — {{ $t->tender_number }}</span>
                                    <span class="shrink-0 text-muted-foreground">{{ $t->country?->name }} · {{ $t->year }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>

        <datalist id="country-list">
            @foreach ($countries as $country)
                <option value="{{ $country->name }}"></option>
                <option value="{{ $country->code }}"></option>
            @endforeach
        </datalist>
    </div>
</main>
@endsection

@push('scripts')
    @vite(['resources/js/pages/upload.js'])
    <script>document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });</script>
@endpush
