@extends('layouts.app')

@section('title', 'Price Recommendation | TenderAI')

@section('content')
<main class="p-6 min-h-screen">
    <div class="space-y-7 max-w-4xl mx-auto">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center shadow-md">
                        <i data-lucide="sparkles" class="w-5 h-5 text-white"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-foreground">Price Recommendation</h1>
                </div>
                <p class="text-sm text-muted-foreground ml-0.5">Generate a tender-specific recommended bid price from historical awards and market statistics</p>
            </div>
            <a href="{{ route('predictions.index') }}" class="text-sm font-semibold text-primary hover:underline inline-flex items-center gap-1">
                <i data-lucide="history" class="w-4 h-4"></i> Recommendation History
            </a>
        </div>

        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                <div>
                    <h2 class="font-semibold text-foreground">Data Readiness</h2>
                    <p class="text-xs text-muted-foreground mt-1">Ensure your tender data has been uploaded and market statistics are up to date.</p>
                </div>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $pricingStatsCount > 0 && $hasDrugs && $hasCountries ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-amber-50 text-amber-800 border border-amber-100' }}">
                    {{ $pricingStatsCount > 0 && $hasDrugs && $hasCountries ? 'Ready' : 'Action needed' }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="p-4 rounded-xl bg-muted/20 border border-border/40">
                    <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-medium">Products</p>
                    <p class="text-xl font-bold text-foreground">{{ $counts['drugs'] }}</p>
                </div>
                <div class="p-4 rounded-xl bg-muted/20 border border-border/40">
                    <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-medium">Countries</p>
                    <p class="text-xl font-bold text-foreground">{{ $counts['countries'] }}</p>
                </div>
                <div class="p-4 rounded-xl bg-muted/20 border border-border/40">
                    <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-medium">Market statistics</p>
                    <p class="text-xl font-bold text-foreground">{{ $counts['pricing_statistics'] }}</p>
                    @if($lastStatsRefresh)
                        <p class="text-[10px] text-muted-foreground mt-1">Updated {{ \Carbon\Carbon::parse($lastStatsRefresh)->diffForHumans() }}</p>
                    @endif
                </div>
                <div class="p-4 rounded-xl bg-muted/20 border border-border/40">
                    <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-medium">AI insights</p>
                    <p class="text-sm font-semibold {{ $canGenerateInsights ? 'text-emerald-700' : 'text-amber-800' }}">{{ $canGenerateInsights ? 'Available' : 'Not configured' }}</p>
                    <p class="text-[10px] text-muted-foreground mt-1">Optional strategic guidance on every recommendation</p>
                </div>
            </div>
        </div>

        @if($pricingStatsCount === 0 && ($availability['message_type'] ?? '') !== 'none')
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                @if(($availability['message_type'] ?? '') === 'no_data')
                    <strong>No market statistics yet.</strong> {{ $availability['message'] }}
                @elseif(($availability['message_type'] ?? '') === 'processing')
                    <strong>Data preparation in progress.</strong> {{ $availability['message'] }}
                @else
                    <strong>{{ ($availability['message_type'] ?? '') === 'failed' ? 'Market analysis issue.' : 'Market statistics pending.' }}</strong>
                    {{ $availability['message'] }}
                @endif
            </div>
        @endif

        @if(!$hasDrugs)
            <div class="rounded-xl border border-border/50 bg-muted/30 px-6 py-10 text-center">
                <i data-lucide="package-x" class="w-10 h-10 text-muted-foreground/50 mx-auto mb-3"></i>
                <p class="font-semibold text-foreground">No products available yet</p>
                <p class="text-sm text-muted-foreground mt-2 max-w-md mx-auto">Complete data upload and product matching, then return here to create a price recommendation.</p>
            </div>
        @elseif(!$hasCountries)
            <div class="rounded-xl border border-border/50 bg-muted/30 px-6 py-10 text-center">
                <p class="font-semibold text-foreground">No countries configured</p>
                <p class="text-sm text-muted-foreground mt-2">Add country coverage through tender data upload and processing.</p>
            </div>
        @elseif(!$hasTenders)
            <div class="rounded-xl border border-border/50 bg-muted/30 px-6 py-10 text-center">
                <i data-lucide="file-x" class="w-10 h-10 text-muted-foreground/50 mx-auto mb-3"></i>
                <p class="font-semibold text-foreground">No tenders available yet</p>
                <p class="text-sm text-muted-foreground mt-2 max-w-md mx-auto">Upload and process tender data, then return here to create a tender-specific recommendation.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('ai.recommendations.store') }}" class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-6 space-y-6 shadow-sm" id="inputFormCard">
            @csrf

            <div>
                <h3 class="font-semibold text-foreground mb-0.5">Tender Recommendation</h3>
                <p class="text-xs text-muted-foreground">
                    Select the tender first — country and region are determined automatically from the tender. Then choose the product and quantity.
                </p>
            </div>

            <div>
                <label for="tender_id" class="text-xs font-semibold text-foreground mb-1.5 block uppercase tracking-wide">Tender <span class="text-primary">*</span></label>
                <input type="search" data-select-filter="tender_id" @disabled(!$canSubmit) class="mb-2 w-full h-10 px-4 rounded-xl border border-border/50 bg-white/70 text-sm disabled:opacity-50" placeholder="Filter tenders by number, country, or year">
                <select name="tender_id" id="tender_id" required @disabled(!$canSubmit) class="w-full h-11 px-4 rounded-xl border border-border/50 bg-white/70 text-sm disabled:opacity-50">
                    <option value="">Select tender...</option>
                    @foreach($tenders as $tender)
                        <option value="{{ $tender->id }}"
                            data-country-id="{{ $tender->country_id }}"
                            data-country-name="{{ $tender->country?->name ?? '' }}"
                            data-region-name="{{ $tender->country?->region?->name ?? '' }}"
                            @selected(old('tender_id') == $tender->id)>
                            [{{ $tender->status === 'upcoming' ? 'Upcoming' : 'Historical' }}]
                            {{ $tender->title ?? 'Tender' }} / {{ $tender->tender_number ?? 'No #' }} /
                            {{ $tender->country?->name ?? 'No country' }} / {{ $tender->year ?? 'No year' }}
                        </option>
                    @endforeach
                </select>
                @error('tender_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div id="tender-geography-panel" class="rounded-xl border border-border/40 bg-muted/15 px-4 py-3 {{ old('tender_id') ? '' : 'hidden' }}">
                <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-medium mb-2">Market geography (from tender)</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Country</p>
                        <p id="tender-country-display" class="font-semibold text-foreground">—</p>
                    </div>
                    <div id="tender-region-wrapper" class="hidden">
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Region</p>
                        <p id="tender-region-display" class="font-semibold text-foreground">—</p>
                    </div>
                </div>
                <input type="hidden" name="country_id" id="country_id" value="{{ old('country_id') }}">
                @error('country_id')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="standardized_drug_id" class="text-xs font-semibold text-foreground mb-1.5 block uppercase tracking-wide">Drug / Product <span class="text-primary">*</span></label>
                <input type="search" data-select-filter="standardized_drug_id" @disabled(!$canSubmit) class="mb-2 w-full h-10 px-4 rounded-xl border border-border/50 bg-white/70 text-sm disabled:opacity-50" placeholder="Filter by code, INN, or name">
                <select name="standardized_drug_id" id="standardized_drug_id" required @disabled(!$canSubmit) class="w-full h-11 px-4 rounded-xl border border-border/50 bg-white/70 text-sm disabled:opacity-50">
                    <option value="">Select drug...</option>
                    @foreach($drugs as $drug)
                        <option value="{{ $drug->id }}" @selected(old('standardized_drug_id') == $drug->id)>
                            {{ $drug->code ?? 'No code' }} — {{ $drug->inn ?? 'No INN' }} — {{ $drug->display_name }}
                        </option>
                    @endforeach
                </select>
                @error('standardized_drug_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="quantity" class="text-xs font-semibold text-foreground mb-1.5 block uppercase tracking-wide">Quantity <span class="text-primary">*</span></label>
                    <input type="number" name="quantity" id="quantity" step="any" min="0.0001" value="{{ old('quantity') }}" required @disabled(!$canSubmit) class="w-full h-11 px-4 rounded-xl border border-border/50 bg-white/70 text-sm disabled:opacity-50" placeholder="e.g. 50000">
                    <p class="text-[10px] text-muted-foreground mt-1">Required tender quantity for context and confidence scoring.</p>
                    @error('quantity')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="quantity_unit" class="text-xs font-semibold text-foreground mb-1.5 block uppercase tracking-wide">Quantity unit <span class="text-primary">*</span></label>
                    <select name="quantity_unit" id="quantity_unit" @disabled(!$canSubmit) class="w-full h-11 px-4 rounded-xl border border-border/50 bg-white/70 text-sm disabled:opacity-50">
                        <option value="units" @selected(old('quantity_unit', 'units') === 'units')>units</option>
                        @foreach($quantityUnits as $unit)
                            @continue($unit === 'units')
                            <option value="{{ $unit }}" @selected(old('quantity_unit') === $unit)>{{ $unit }}</option>
                        @endforeach
                    </select>
                    @error('quantity_unit')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="discount_percentage" class="text-xs font-semibold text-foreground mb-1.5 block uppercase tracking-wide">Bid Discount Percentage <span class="text-primary">*</span></label>
                <input type="number" name="discount_percentage" id="discount_percentage" step="0.01" min="0" max="100" value="{{ old('discount_percentage', '0') }}" required @disabled(!$canSubmit) class="w-full h-11 px-4 rounded-xl border border-border/50 bg-white/70 text-sm disabled:opacity-50" placeholder="e.g. 5">
                <p class="text-[10px] text-muted-foreground mt-1">Enter the discount percentage you would like to apply to the calculated recommendation.</p>
                @error('discount_percentage')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <button type="submit" @disabled(!$canSubmit) class="inline-flex items-center justify-center gap-2 w-full h-12 bg-gradient-to-r from-primary to-secondary text-white border-0 rounded-xl shadow-md text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                <i data-lucide="sparkles" class="w-4 h-4 text-white"></i>
                Generate Tender Recommendation
            </button>
        </form>

        @if($recentPredictions->isNotEmpty())
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-foreground mb-3">Your recent recommendations</h3>
                <ul class="space-y-2">
                    @foreach($recentPredictions as $recent)
                        <li class="flex items-center justify-between text-sm gap-3">
                            <span class="truncate text-foreground/80">{{ $recent->standardizedDrug?->display_name ?? 'Drug' }}</span>
                            <x-prediction-status-badge :status="$recent->status" />
                            <a href="{{ route('ai.recommendations.show', $recent) }}" class="text-primary font-semibold shrink-0 hover:underline">View</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) lucide.createIcons();

    document.querySelectorAll('[data-select-filter]').forEach((input) => {
        const select = document.getElementById(input.dataset.selectFilter);
        if (!select) return;

        const options = Array.from(select.options).map((option) => ({
            option,
            text: option.textContent.toLowerCase(),
        }));

        input.addEventListener('input', () => {
            const query = input.value.trim().toLowerCase();
            options.forEach(({ option, text }, index) => {
                option.hidden = index > 0 && query !== '' && !text.includes(query);
            });
        });
    });

    const form = document.getElementById('inputFormCard');
    const tenderSelect = document.getElementById('tender_id');
    const countryInput = document.getElementById('country_id');
    const geographyPanel = document.getElementById('tender-geography-panel');
    const countryDisplay = document.getElementById('tender-country-display');
    const regionWrapper = document.getElementById('tender-region-wrapper');
    const regionDisplay = document.getElementById('tender-region-display');
    const quantityInput = document.getElementById('quantity');
    const discountInput = document.getElementById('discount_percentage');

    const syncTenderGeography = () => {
        const option = tenderSelect?.selectedOptions[0];
        if (!option || !option.value) {
            geographyPanel?.classList.add('hidden');
            if (countryInput) countryInput.value = '';
            return;
        }

        geographyPanel?.classList.remove('hidden');
        const countryId = option.dataset.countryId || '';
        const countryName = option.dataset.countryName || '—';
        const regionName = option.dataset.regionName || '';

        if (countryInput) countryInput.value = countryId;
        if (countryDisplay) countryDisplay.textContent = countryName;

        if (regionName && regionWrapper && regionDisplay) {
            regionWrapper.classList.remove('hidden');
            regionDisplay.textContent = regionName;
        } else if (regionWrapper) {
            regionWrapper.classList.add('hidden');
        }
    };

    tenderSelect?.addEventListener('change', syncTenderGeography);
    syncTenderGeography();

    form?.addEventListener('submit', (event) => {
        const errors = [];

        if (!tenderSelect?.value) {
            errors.push('Please select the tender this recommendation is for.');
        }

        const qty = parseFloat(quantityInput?.value ?? '');
        if (!quantityInput?.value || Number.isNaN(qty) || qty <= 0) {
            errors.push('Please enter the required tender quantity (must be greater than zero).');
        }

        if (!countryInput?.value) {
            errors.push('Country could not be determined from the selected tender.');
        }

        if (discountInput?.value === '' || discountInput?.value === null) {
            errors.push('Please enter the bid discount percentage.');
        } else {
            const discount = parseFloat(discountInput.value);
            if (Number.isNaN(discount) || discount < 0 || discount > 100) {
                errors.push('Bid discount percentage must be between 0 and 100.');
            }
        }

        if (errors.length > 0) {
            event.preventDefault();
            alert(errors.join('\n'));
        }
    });
});
</script>
@endpush
