{{-- OpenAI / AI Configuration --}}
<div class="sc-section">
    <div class="sc-section-header">
        <span class="sc-section-icon"><i data-lucide="cpu" class="icon-sm"></i></span>
        <div>
            <h3 class="sc-section-title">AI Configuration</h3>
            <p class="sc-section-desc">OpenAI settings for AI-Assisted Analysis mode. Price calculations always use your tender data.</p>
        </div>
    </div>

    {{-- Status banner --}}
    <div class="sc-ai-notice">
        <i data-lucide="info" class="icon-sm"></i>
        <span>AI features provide supplementary market insights only. Recommended prices are always calculated from your tender data.</span>
    </div>

    {{-- API Key status card --}}
    <div class="sc-key-card">
        <div class="sc-key-card-left">
            <i data-lucide="key-round" class="sc-key-icon"></i>
            <div>
                <p class="sc-key-label">OpenAI API Key</p>
                @if ($ai['api_key_masked'] ?? null)
                    <code class="sc-key-masked">{{ $ai['api_key_masked'] }}</code>
                @else
                    <p class="sc-key-empty">No key configured</p>
                @endif
                <p class="sc-key-security-note">
                    <i data-lucide="lock" style="width:10px;height:10px;display:inline"></i>
                    Encrypted with Laravel Crypt — never visible in plaintext
                </p>
            </div>
        </div>
        <div class="sc-key-card-right">
            @php
                $aiStatus = 'none';
                if (session('ai_connection_success')) $aiStatus = 'connected';
                elseif (session('ai_connection_failed')) $aiStatus = 'failed';
                elseif ($ai['has_api_key'] ?? false) $aiStatus = 'saved';
            @endphp
            <span class="sc-key-badge sc-key-badge--{{ $aiStatus }}">
                @if ($aiStatus === 'connected')
                    <i data-lucide="check-circle" class="icon-xs"></i> Connected
                @elseif ($aiStatus === 'failed')
                    <i data-lucide="x-circle" class="icon-xs"></i> Connection failed
                @elseif ($aiStatus === 'saved')
                    <i data-lucide="shield-check" class="icon-xs"></i> Key saved
                @else
                    <i data-lucide="minus-circle" class="icon-xs"></i> Not configured
                @endif
            </span>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.ai.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="_settings_tab" value="ai">

        <div class="sc-form-grid">
            <div class="sc-field">
                <label class="sc-label" for="provider">Provider</label>
                <select class="sc-input" id="provider" name="provider" required>
                    <option value="openai" @selected(old('provider', $ai['provider'] ?? 'openai') === 'openai')>OpenAI</option>
                </select>
            </div>

            <div class="sc-field">
                <label class="sc-label" for="default_model">Default Model</label>
                <input type="text" class="sc-input @error('default_model') sc-input--error @enderror"
                    id="default_model" name="default_model"
                    value="{{ old('default_model', $ai['default_model'] ?? 'gpt-4o-mini') }}" required>
                @error('default_model')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field sc-field--wide">
                <label class="sc-label" for="api_key">
                    Update API Key
                    <span class="sc-label-hint">Leave blank to keep current key</span>
                </label>
                <input type="password" class="sc-input @error('api_key') sc-input--error @enderror"
                    id="api_key" name="api_key"
                    placeholder="{{ ($ai['has_api_key'] ?? false) ? '••••••••••••••••' : 'sk-...' }}"
                    autocomplete="new-password">
                @error('api_key')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="temperature">
                    Temperature
                    <span class="sc-label-hint">0 – 2</span>
                </label>
                <input type="number" step="0.1" min="0" max="2" class="sc-input @error('temperature') sc-input--error @enderror"
                    id="temperature" name="temperature"
                    value="{{ old('temperature', $ai['temperature'] ?? 0.2) }}" required>
                <p class="sc-hint">Lower = more deterministic responses.</p>
                @error('temperature')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="max_tokens">Max Tokens</label>
                <input type="number" class="sc-input @error('max_tokens') sc-input--error @enderror"
                    id="max_tokens" name="max_tokens"
                    value="{{ old('max_tokens', $ai['max_tokens'] ?? 800) }}" required>
                <p class="sc-hint">Per-request token ceiling.</p>
                @error('max_tokens')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="timeout_seconds">
                    Timeout
                    <span class="sc-label-hint">seconds</span>
                </label>
                <input type="number" class="sc-input @error('timeout_seconds') sc-input--error @enderror"
                    id="timeout_seconds" name="timeout_seconds"
                    value="{{ old('timeout_seconds', $ai['timeout_seconds'] ?? 60) }}" required>
                @error('timeout_seconds')<p class="sc-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="sc-toggle-row">
            <div class="sc-toggle-info">
                <span class="sc-toggle-label">Enable AI Narrative</span>
                <span class="sc-toggle-desc">Generate explanatory narrative for predictions (Phase 6C)</span>
            </div>
            <input type="hidden" name="enable_narrative" value="0">
            <button type="button" role="switch"
                aria-checked="{{ old('enable_narrative', $ai['enable_narrative'] ?? false) ? 'true' : 'false' }}"
                class="sc-switch" data-checkbox="enable_narrative">
                <span class="sc-switch-thumb"></span>
            </button>
            <input type="checkbox" name="enable_narrative" value="1"
                class="hidden sc-checkbox" id="enable_narrative"
                @checked(old('enable_narrative', $ai['enable_narrative'] ?? false))>
        </div>

        <div class="sc-toggle-row">
            <div class="sc-toggle-info">
                <span class="sc-toggle-label">Enable Standardization Assist</span>
                <span class="sc-toggle-desc">AI-assisted import standardization (Phase 6C)</span>
            </div>
            <input type="hidden" name="enable_standardization_assist" value="0">
            <button type="button" role="switch"
                aria-checked="{{ old('enable_standardization_assist', $ai['enable_standardization_assist'] ?? false) ? 'true' : 'false' }}"
                class="sc-switch" data-checkbox="enable_standardization_assist">
                <span class="sc-switch-thumb"></span>
            </button>
            <input type="checkbox" name="enable_standardization_assist" value="1"
                class="hidden sc-checkbox" id="enable_standardization_assist"
                @checked(old('enable_standardization_assist', $ai['enable_standardization_assist'] ?? false))>
        </div>

        {{-- Advanced AI --}}
        <details class="sc-advanced">
            <summary class="sc-advanced-toggle">
                <i data-lucide="chevron-right" class="sc-advanced-chevron icon-sm"></i>
                Advanced AI Settings
            </summary>
            <div class="sc-advanced-content">
                <div class="sc-form-grid">
                    <div class="sc-field">
                        <label class="sc-label" for="advanced_model">Advanced Model <span class="sc-label-hint">optional</span></label>
                        <input type="text" class="sc-input" id="advanced_model" name="advanced_model"
                            value="{{ old('advanced_model', $ai['advanced_model'] ?? '') }}">
                        <p class="sc-hint">Used for complex escalation scenarios (Phase 6C).</p>
                    </div>
                    <div class="sc-field">
                        <label class="sc-label" for="rate_limit_per_user_per_hour">Rate Limit <span class="sc-label-hint">requests / user / hour</span></label>
                        <input type="number" class="sc-input" id="rate_limit_per_user_per_hour"
                            name="rate_limit_per_user_per_hour"
                            value="{{ old('rate_limit_per_user_per_hour', $ai['rate_limit_per_user_per_hour'] ?? 10) }}" required>
                    </div>
                    <div class="sc-field">
                        <label class="sc-label" for="monthly_token_budget">Monthly Token Budget <span class="sc-label-hint">optional</span></label>
                        <input type="number" class="sc-input" id="monthly_token_budget"
                            name="monthly_token_budget"
                            value="{{ old('monthly_token_budget', $ai['monthly_token_budget'] ?? '') }}">
                        <p class="sc-hint">Leave empty for no cap.</p>
                    </div>
                    <div class="sc-field">
                        <label class="sc-label" for="narrative_min_confidence">Narrative Min Confidence</label>
                        <input type="number" min="0" max="100" class="sc-input" id="narrative_min_confidence"
                            name="narrative_min_confidence"
                            value="{{ old('narrative_min_confidence', $ai['narrative_min_confidence'] ?? 50) }}" required>
                        <p class="sc-hint">Predictions below this confidence skip AI narrative generation.</p>
                    </div>
                    <div class="sc-field">
                        <label class="sc-label" for="system_prompt_version">System Prompt Version</label>
                        <input type="text" class="sc-input" id="system_prompt_version"
                            name="system_prompt_version"
                            value="{{ old('system_prompt_version', $ai['system_prompt_version'] ?? 'v1.0') }}" required>
                    </div>
                </div>
            </div>
        </details>

        <div class="sc-form-actions">
            <button type="submit" class="sc-btn sc-btn--primary">
                <i data-lucide="save" class="icon-sm"></i> Save AI Settings
            </button>
        </div>
    </form>

    {{-- Key actions outside main form --}}
    <div class="sc-key-actions">
        <form method="POST" action="{{ route('settings.ai.test') }}">
            @csrf
            <button type="submit" class="sc-btn sc-btn--ghost">
                <i data-lucide="zap" class="icon-sm"></i> Test Connection
            </button>
        </form>

        <form method="POST" action="{{ route('settings.ai.test-narrative') }}">
            @csrf
            <button type="submit" class="sc-btn sc-btn--ghost">
                <i data-lucide="message-square-text" class="icon-sm"></i> Test Narrative
            </button>
        </form>

        @if ($ai['has_api_key'] ?? false)
            <form method="POST" action="{{ route('settings.ai.api-key.destroy') }}"
                onsubmit="return confirm('Remove the stored API key permanently?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="sc-btn sc-btn--danger-ghost">
                    <i data-lucide="trash-2" class="icon-sm"></i> Remove API Key
                </button>
            </form>
        @endif
    </div>
</div>
