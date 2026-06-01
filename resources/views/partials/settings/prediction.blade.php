{{-- Prediction Configuration --}}
<div class="sc-section">
    <div class="sc-section-header">
        <span class="sc-section-icon"><i data-lucide="trending-up" class="icon-sm"></i></span>
        <div>
            <h3 class="sc-section-title">Pricing Configuration</h3>
            <p class="sc-section-desc">Parameters for business calculation pricing formulas.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.prediction.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="_settings_tab" value="prediction">

        {{-- Primary fields --}}
        <div class="sc-form-grid">
            <div class="sc-field">
                <label class="sc-label" for="backend_only_confidence_threshold">
                    Minimum Data Confidence
                    <span class="sc-label-hint">0–100 %</span>
                </label>
                <input type="number" class="sc-input @error('backend_only_confidence_threshold') sc-input--error @enderror"
                    id="backend_only_confidence_threshold" name="backend_only_confidence_threshold"
                    min="0" max="100"
                    value="{{ old('backend_only_confidence_threshold', $prediction['backend_only_confidence_threshold'] ?? 80) }}" required>
                <p class="sc-hint">Minimum data confidence before optional AI market insights are generated.</p>
                @error('backend_only_confidence_threshold')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="trend_adjustment_cap">
                    Trend Adjustment Cap
                    <span class="sc-label-hint">%</span>
                </label>
                <input type="number" step="0.1" class="sc-input @error('trend_adjustment_cap') sc-input--error @enderror"
                    id="trend_adjustment_cap" name="trend_adjustment_cap"
                    value="{{ old('trend_adjustment_cap', $prediction['trend_adjustment_cap'] ?? 7) }}" required>
                <p class="sc-hint">Maximum price trend applied to base prediction.</p>
                @error('trend_adjustment_cap')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="aggressive_discount_percent">
                    Aggressive Scenario
                    <span class="sc-label-hint">discount %</span>
                </label>
                <input type="number" step="0.1" class="sc-input @error('aggressive_discount_percent') sc-input--error @enderror"
                    id="aggressive_discount_percent" name="aggressive_discount_percent"
                    value="{{ old('aggressive_discount_percent', $prediction['aggressive_discount_percent'] ?? 3) }}" required>
                @error('aggressive_discount_percent')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="conservative_premium_percent">
                    Conservative Scenario
                    <span class="sc-label-hint">premium %</span>
                </label>
                <input type="number" step="0.1" class="sc-input @error('conservative_premium_percent') sc-input--error @enderror"
                    id="conservative_premium_percent" name="conservative_premium_percent"
                    value="{{ old('conservative_premium_percent', $prediction['conservative_premium_percent'] ?? 3) }}" required>
                @error('conservative_premium_percent')<p class="sc-error">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Advanced quantity settings --}}
        <details class="sc-advanced">
            <summary class="sc-advanced-toggle">
                <i data-lucide="chevron-right" class="sc-advanced-chevron icon-sm"></i>
                Advanced Quantity Sensitivity
            </summary>
            <div class="sc-advanced-content">
                <div class="sc-form-grid">
                    <div class="sc-field">
                        <label class="sc-label" for="large_quantity_multiplier">
                            Large Qty Threshold
                            <span class="sc-label-hint">× median</span>
                        </label>
                        <input type="number" step="0.01" class="sc-input" id="large_quantity_multiplier"
                            name="large_quantity_multiplier"
                            value="{{ old('large_quantity_multiplier', $prediction['large_quantity_multiplier'] ?? 2) }}" required>
                        <p class="sc-hint">Multiplier vs historical median to classify as "large".</p>
                    </div>
                    <div class="sc-field">
                        <label class="sc-label" for="large_quantity_discount_percent">
                            Large Qty Discount
                            <span class="sc-label-hint">%</span>
                        </label>
                        <input type="number" step="0.1" class="sc-input" id="large_quantity_discount_percent"
                            name="large_quantity_discount_percent"
                            value="{{ old('large_quantity_discount_percent', $prediction['large_quantity_discount_percent'] ?? 3) }}" required>
                    </div>
                    <div class="sc-field">
                        <label class="sc-label" for="small_quantity_multiplier">
                            Small Qty Threshold
                            <span class="sc-label-hint">× median</span>
                        </label>
                        <input type="number" step="0.01" class="sc-input" id="small_quantity_multiplier"
                            name="small_quantity_multiplier"
                            value="{{ old('small_quantity_multiplier', $prediction['small_quantity_multiplier'] ?? 0.5) }}" required>
                    </div>
                    <div class="sc-field">
                        <label class="sc-label" for="small_quantity_premium_percent">
                            Small Qty Premium
                            <span class="sc-label-hint">%</span>
                        </label>
                        <input type="number" step="0.1" class="sc-input" id="small_quantity_premium_percent"
                            name="small_quantity_premium_percent"
                            value="{{ old('small_quantity_premium_percent', $prediction['small_quantity_premium_percent'] ?? 3) }}" required>
                    </div>
                </div>

                {{-- Developer-only: model version --}}
                <details class="sc-advanced sc-advanced--nested">
                    <summary class="sc-advanced-toggle">
                        <i data-lucide="chevron-right" class="sc-advanced-chevron icon-sm"></i>
                        Developer Options
                    </summary>
                    <div class="sc-advanced-content">
                        <div class="sc-field" style="max-width:20rem">
                            <label class="sc-label" for="calculation_model_version">Engine Version</label>
                            <input type="text" class="sc-input" id="calculation_model_version"
                                name="calculation_model_version"
                                value="{{ old('calculation_model_version', $prediction['calculation_model_version'] ?? 'v1.0') }}" required>
                        </div>
                    </div>
                </details>
            </div>
        </details>

        <div class="sc-form-actions">
            <button type="submit" class="sc-btn sc-btn--primary">
                <i data-lucide="save" class="icon-sm"></i> Save Prediction Settings
            </button>
        </div>
    </form>
</div>
