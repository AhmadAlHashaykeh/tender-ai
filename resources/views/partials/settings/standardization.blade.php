{{-- Standardization Rules --}}
<div class="sc-section">
    <div class="sc-section-header">
        <span class="sc-section-icon"><i data-lucide="check-circle" class="icon-sm"></i></span>
        <div>
            <h3 class="sc-section-title">Standardization Rules</h3>
            <p class="sc-section-desc">Minimum confidence scores (0–100) for automatic approval during import processing.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.standardization.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="_settings_tab" value="standardization">

        <div class="sc-form-grid">
            <div class="sc-field">
                <label class="sc-label" for="drug_auto_approve_min">Drug Auto-Approve</label>
                <div class="sc-input-with-suffix">
                    <input type="number" class="sc-input @error('drug_auto_approve_min') sc-input--error @enderror"
                        id="drug_auto_approve_min" name="drug_auto_approve_min"
                        min="0" max="100"
                        value="{{ old('drug_auto_approve_min', $standardization['drug_auto_approve_min'] ?? 85) }}" required>
                    <span class="sc-input-suffix">%</span>
                </div>
                @error('drug_auto_approve_min')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="company_auto_approve_min">Company Auto-Approve</label>
                <div class="sc-input-with-suffix">
                    <input type="number" class="sc-input @error('company_auto_approve_min') sc-input--error @enderror"
                        id="company_auto_approve_min" name="company_auto_approve_min"
                        min="0" max="100"
                        value="{{ old('company_auto_approve_min', $standardization['company_auto_approve_min'] ?? 85) }}" required>
                    <span class="sc-input-suffix">%</span>
                </div>
                @error('company_auto_approve_min')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="row_auto_approve_min">Row Auto-Approve</label>
                <div class="sc-input-with-suffix">
                    <input type="number" class="sc-input @error('row_auto_approve_min') sc-input--error @enderror"
                        id="row_auto_approve_min" name="row_auto_approve_min"
                        min="0" max="100"
                        value="{{ old('row_auto_approve_min', $standardization['row_auto_approve_min'] ?? 75) }}" required>
                    <span class="sc-input-suffix">%</span>
                </div>
                @error('row_auto_approve_min')<p class="sc-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="sc-toggle-row">
            <div class="sc-toggle-info">
                <span class="sc-toggle-label">Enable AI Assist</span>
                <span class="sc-toggle-desc">AI-assisted standardization (requires Phase 6C active)</span>
            </div>
            <input type="hidden" name="enable_ai_assist" value="0">
            <button type="button" role="switch"
                aria-checked="{{ old('enable_ai_assist', $standardization['enable_ai_assist'] ?? false) ? 'true' : 'false' }}"
                class="sc-switch" data-checkbox="enable_ai_assist">
                <span class="sc-switch-thumb"></span>
            </button>
            <input type="checkbox" name="enable_ai_assist" value="1"
                class="hidden sc-checkbox" id="enable_ai_assist"
                @checked(old('enable_ai_assist', $standardization['enable_ai_assist'] ?? false))>
        </div>

        {{-- Advanced --}}
        <details class="sc-advanced">
            <summary class="sc-advanced-toggle">
                <i data-lucide="chevron-right" class="sc-advanced-chevron icon-sm"></i>
                Advanced Standardization
            </summary>
            <div class="sc-advanced-content">
                <div class="sc-form-grid">
                    <div class="sc-field">
                        <label class="sc-label" for="ai_auto_approve_min">AI Suggestion Auto-Approve</label>
                        <div class="sc-input-with-suffix">
                            <input type="number" class="sc-input @error('ai_auto_approve_min') sc-input--error @enderror"
                                id="ai_auto_approve_min" name="ai_auto_approve_min"
                                min="0" max="100"
                                value="{{ old('ai_auto_approve_min', $standardization['ai_auto_approve_min'] ?? 85) }}" required>
                            <span class="sc-input-suffix">%</span>
                        </div>
                        @error('ai_auto_approve_min')<p class="sc-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="sc-field">
                        <label class="sc-label" for="fuzzy_auto_approve_min">Fuzzy Match Minimum</label>
                        <div class="sc-input-with-suffix">
                            <input type="number" class="sc-input @error('fuzzy_auto_approve_min') sc-input--error @enderror"
                                id="fuzzy_auto_approve_min" name="fuzzy_auto_approve_min"
                                min="0" max="100"
                                value="{{ old('fuzzy_auto_approve_min', $standardization['fuzzy_auto_approve_min'] ?? 80) }}" required>
                            <span class="sc-input-suffix">%</span>
                        </div>
                        @error('fuzzy_auto_approve_min')<p class="sc-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="sc-field">
                        <label class="sc-label" for="max_ai_calls_per_batch">Max AI Calls / Batch</label>
                        <input type="number" class="sc-input" id="max_ai_calls_per_batch"
                            name="max_ai_calls_per_batch" min="1"
                            value="{{ old('max_ai_calls_per_batch', $standardization['max_ai_calls_per_batch'] ?? 50) }}" required>
                        <p class="sc-hint">Limits AI API calls per standardization batch run.</p>
                    </div>
                </div>
            </div>
        </details>

        <div class="sc-form-actions">
            <button type="submit" class="sc-btn sc-btn--primary">
                <i data-lucide="save" class="icon-sm"></i> Save Standardization Rules
            </button>
        </div>
    </form>
</div>
