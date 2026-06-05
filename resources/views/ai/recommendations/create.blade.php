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
                <p class="text-sm text-muted-foreground ml-0.5">Generate a tender-program recommendation from grouped historical awards and market statistics</p>
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
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $pricingStatsCount > 0 && $hasCountries && $hasTenderGroups ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-amber-50 text-amber-800 border border-amber-100' }}">
                    {{ $pricingStatsCount > 0 && $hasCountries && $hasTenderGroups ? 'Ready' : 'Action needed' }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="p-4 rounded-xl bg-muted/20 border border-border/40">
                    <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-medium">Tender programs</p>
                    <p class="text-xl font-bold text-foreground">{{ $counts['tender_programs'] }}</p>
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
                @elseif(($availability['message_type'] ?? '') === 'no_analytics_bids')
                    <strong>Market data not ready.</strong> {{ $availability['message'] }}
                @else
                    <strong>{{ ($availability['message_type'] ?? '') === 'failed' ? 'Market analysis issue.' : 'Market statistics pending.' }}</strong>
                    {{ $availability['message'] }}
                @endif
            </div>
        @endif

        @if(!$hasCountries)
            <div class="rounded-xl border border-border/50 bg-muted/30 px-6 py-10 text-center">
                <p class="font-semibold text-foreground">No countries configured</p>
                <p class="text-sm text-muted-foreground mt-2">Add country coverage through tender data upload and processing.</p>
            </div>
        @elseif(!$hasTenderGroups)
            <div class="rounded-xl border border-border/50 bg-muted/30 px-6 py-10 text-center">
                <i data-lucide="file-x" class="w-10 h-10 text-muted-foreground/50 mx-auto mb-3"></i>
                <p class="font-semibold text-foreground">No tender programs available yet</p>
                <p class="text-sm text-muted-foreground mt-2 max-w-md mx-auto">Upload and process tender data, then return here to create a program-based recommendation.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('ai.recommendations.store') }}" class="bg-white/80 backdrop-blur-sm rounded-2xl border border-border/50 p-6 space-y-6 shadow-sm" id="inputFormCard">
            @csrf

            <div>
                <h3 class="font-semibold text-foreground mb-0.5">Tender Recommendation</h3>
                <p class="text-xs text-muted-foreground">
                    Select the tender program first — country and market context are determined automatically. Then choose a product that exists in that program's history.
                </p>
            </div>

            <div>
                <label for="tender_group_key" class="text-xs font-semibold text-foreground mb-1.5 block uppercase tracking-wide">Tender / Program <span class="text-primary">*</span></label>
                <input type="search" data-select-filter="tender_group_key" @disabled(!$canSubmit) class="mb-2 w-full h-10 px-4 rounded-xl border border-border/50 bg-white/70 text-sm disabled:opacity-50" placeholder="Filter tender programs by name, country, or year">
                <select name="tender_group_key" id="tender_group_key" required @disabled(!$canSubmit) class="w-full h-11 px-4 rounded-xl border border-border/50 bg-white/70 text-sm disabled:opacity-50">
                    <option value="">Select tender program...</option>
                    @foreach($tenderGroups as $group)
                        <option value="{{ $group['group_key'] }}"
                            data-country-id="{{ $group['country_id'] }}"
                            data-country-name="{{ $group['country_name'] ?? '' }}"
                            data-region-name="{{ $group['region_name'] ?? '' }}"
                            data-display-name="{{ $group['display_name'] }}"
                            data-tender-count="{{ $group['tender_count'] }}"
                            data-years-label="{{ $group['years_label'] }}"
                            data-product-count="{{ $group['product_count'] }}"
                            data-summary="{{ $group['country_name'] ?? 'Unknown market' }} • {{ $group['tender_count'] }} historical tenders • {{ $group['years_label'] }} • {{ $group['product_count'] }} products"
                            @selected(old('tender_group_key') == $group['group_key'])>
                            {{ $group['display_name'] }} — {{ $group['country_name'] ?? 'Unknown market' }} • {{ $group['tender_count'] }} historical tenders • {{ $group['years_label'] }} • {{ $group['product_count'] }} products
                        </option>
                    @endforeach
                </select>
                @error('tender_group_key')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div id="tender-group-context-panel" class="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 hidden">
                <p class="text-[10px] uppercase tracking-wide text-primary font-medium mb-2">Selected Tender Program</p>
                <p id="tender-group-display-name" class="text-lg font-bold text-foreground">—</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm mt-3">
                    <div>
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Market</p>
                        <p id="tender-group-market-display" class="font-semibold text-foreground">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Historical tenders</p>
                        <p id="tender-group-count-display" class="font-semibold text-foreground">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Years</p>
                        <p id="tender-group-years-display" class="font-semibold text-foreground">—</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Available products</p>
                        <p id="tender-group-products-display" class="font-semibold text-foreground">—</p>
                    </div>
                </div>
                <input type="hidden" name="country_id" id="country_id" value="{{ old('country_id') }}">
                @error('country_id')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="standardized_drug_id" class="text-xs font-semibold text-foreground mb-1.5 block uppercase tracking-wide">Drug / Product <span class="text-primary">*</span></label>
                <input type="search" data-select-filter="standardized_drug_id" id="drug-filter-input" disabled class="mb-2 w-full h-10 px-4 rounded-xl border border-border/50 bg-white/70 text-sm disabled:opacity-50" placeholder="Filter by code, INN, or name">
                <select name="standardized_drug_id" id="standardized_drug_id" required disabled class="w-full h-11 px-4 rounded-xl border border-border/50 bg-white/70 text-sm disabled:opacity-50">
                    <option value="">Select a tender program first</option>
                </select>
                <p id="drug-loading-message" class="text-[10px] text-muted-foreground mt-1 hidden">Loading products for selected tender program...</p>
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

    const drugsEndpointTemplate = @json(route('ai.recommendations.tender-groups.drugs', ['groupKey' => '__GROUP__']));
    const oldDrugId = @json(old('standardized_drug_id'));
    const canSubmit = @json($canSubmit);

    const form = document.getElementById('inputFormCard');
    const groupSelect = document.getElementById('tender_group_key');
    const drugSelect = document.getElementById('standardized_drug_id');
    const drugFilterInput = document.getElementById('drug-filter-input');
    const drugLoadingMessage = document.getElementById('drug-loading-message');
    const countryInput = document.getElementById('country_id');
    const contextPanel = document.getElementById('tender-group-context-panel');
    const quantityInput = document.getElementById('quantity');
    const discountInput = document.getElementById('discount_percentage');

    let drugFilterOptions = [];

    const setupSelectFilter = (input) => {
        const select = document.getElementById(input.dataset.selectFilter);
        if (!select) return;

        const refreshOptions = () => Array.from(select.options).map((option) => ({
            option,
            text: option.textContent.toLowerCase(),
        }));

        let options = refreshOptions();

        input.addEventListener('input', () => {
            const query = input.value.trim().toLowerCase();
            options.forEach(({ option, text }, index) => {
                option.hidden = index > 0 && query !== '' && !text.includes(query);
            });
        });

        select.addEventListener('change', () => {
            if (input.dataset.selectFilter === 'standardized_drug_id') {
                options = refreshOptions();
            }
        });
    };

    document.querySelectorAll('[data-select-filter]').forEach(setupSelectFilter);

    const resetDrugSelect = (message = 'Select a tender program first') => {
        drugSelect.innerHTML = `<option value="">${message}</option>`;
        drugSelect.value = '';
        drugSelect.disabled = true;
        drugFilterInput.disabled = true;
        drugFilterInput.value = '';
        drugFilterOptions = [];
    };

    const populateDrugSelect = (drugs) => {
        drugSelect.innerHTML = '<option value="">Select drug...</option>';

        drugs.forEach((drug) => {
            const option = document.createElement('option');
            option.value = drug.drug_id;
            const code = drug.code ?? 'No code';
            const inn = drug.inn ?? 'No INN';
            option.textContent = `${code} — ${inn} — ${drug.display_name}`;
            if (String(oldDrugId) === String(drug.drug_id)) {
                option.selected = true;
            }
            drugSelect.appendChild(option);
        });

        drugSelect.disabled = drugs.length === 0;
        drugFilterInput.disabled = drugs.length === 0;
        drugFilterOptions = Array.from(drugSelect.options).map((option) => ({
            option,
            text: option.textContent.toLowerCase(),
        }));
    };

    const syncTenderGroupContext = async () => {
        const option = groupSelect?.selectedOptions[0];
        if (!option || !option.value) {
            contextPanel?.classList.add('hidden');
            if (countryInput) countryInput.value = '';
            resetDrugSelect();
            return;
        }

        contextPanel?.classList.remove('hidden');
        document.getElementById('tender-group-display-name').textContent = option.dataset.displayName || '—';
        document.getElementById('tender-group-market-display').textContent = option.dataset.countryName || '—';
        document.getElementById('tender-group-count-display').textContent = option.dataset.tenderCount || '—';
        document.getElementById('tender-group-years-display').textContent = option.dataset.yearsLabel || '—';
        document.getElementById('tender-group-products-display').textContent = option.dataset.productCount || '—';

        if (countryInput) {
            countryInput.value = option.dataset.countryId || '';
        }

        resetDrugSelect('Loading products...');
        drugLoadingMessage?.classList.remove('hidden');

        try {
            const endpoint = drugsEndpointTemplate.replace('__GROUP__', encodeURIComponent(option.value));
            const response = await fetch(endpoint, {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Unable to load products for this tender program.');
            }

            const payload = await response.json();
            populateDrugSelect(payload.drugs ?? []);

            if ((payload.drugs ?? []).length === 0) {
                resetDrugSelect('No products found in this tender program');
            }
        } catch (error) {
            resetDrugSelect('Unable to load products');
            console.error(error);
        } finally {
            drugLoadingMessage?.classList.add('hidden');
        }
    };

    drugFilterInput?.addEventListener('input', () => {
        const query = drugFilterInput.value.trim().toLowerCase();
        drugFilterOptions.forEach(({ option, text }, index) => {
            option.hidden = index > 0 && query !== '' && !text.includes(query);
        });
    });

    groupSelect?.addEventListener('change', syncTenderGroupContext);

    if (canSubmit && groupSelect?.value) {
        syncTenderGroupContext();
    }

    form?.addEventListener('submit', (event) => {
        const errors = [];

        if (!groupSelect?.value) {
            errors.push('Please select the tender program this recommendation is for.');
        }

        if (!drugSelect?.value) {
            errors.push('Please select the drug or product for this tender program.');
        }

        const qty = parseFloat(quantityInput?.value ?? '');
        if (!quantityInput?.value || Number.isNaN(qty) || qty <= 0) {
            errors.push('Please enter the required tender quantity (must be greater than zero).');
        }

        if (!countryInput?.value) {
            errors.push('Country could not be determined from the selected tender program.');
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
