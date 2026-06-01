{{-- General Preferences --}}
<div class="sc-section">
    <div class="sc-section-header">
        <span class="sc-section-icon"><i data-lucide="settings-2" class="icon-sm"></i></span>
        <div>
            <h3 class="sc-section-title">General Preferences</h3>
            <p class="sc-section-desc">Organization identity, locale, and display defaults.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.general.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="_settings_tab" value="general">

        <div class="sc-form-grid">
            <div class="sc-field sc-field--wide">
                <label class="sc-label" for="organization_name">Organization Name</label>
                <input type="text" class="sc-input @error('organization_name') sc-input--error @enderror"
                    id="organization_name" name="organization_name"
                    value="{{ old('organization_name', $general['organization_name'] ?? '') }}" required>
                @error('organization_name')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="default_currency">Default Currency</label>
                <select class="sc-input @error('default_currency') sc-input--error @enderror"
                    id="default_currency" name="default_currency" required>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->code }}" @selected(old('default_currency', $general['default_currency'] ?? 'USD') === $currency->code)>
                            {{ $currency->code }} — {{ $currency->name }}
                        </option>
                    @endforeach
                </select>
                @error('default_currency')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="timezone">Timezone</label>
                <select class="sc-input @error('timezone') sc-input--error @enderror"
                    id="timezone" name="timezone" required>
                    @foreach ($timezones as $tz)
                        <option value="{{ $tz }}" @selected(old('timezone', $general['timezone'] ?? 'UTC') === $tz)>{{ $tz }}</option>
                    @endforeach
                </select>
                @error('timezone')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="language">Language</label>
                <select class="sc-input @error('language') sc-input--error @enderror"
                    id="language" name="language" required>
                    <option value="en" @selected(old('language', $general['language'] ?? 'en') === 'en')>English</option>
                    <option value="ar" @selected(old('language', $general['language'] ?? 'en') === 'ar')>Arabic</option>
                </select>
                @error('language')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="rows_per_page">Rows Per Page</label>
                <select class="sc-input @error('rows_per_page') sc-input--error @enderror"
                    id="rows_per_page" name="rows_per_page" required>
                    @foreach ([25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) old('rows_per_page', $general['rows_per_page'] ?? 25) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
                @error('rows_per_page')<p class="sc-error">{{ $message }}</p>@enderror
            </div>

            <div class="sc-field">
                <label class="sc-label" for="date_format">
                    Date Format
                    <span class="sc-label-hint">PHP format (e.g. Y-m-d, d/m/Y)</span>
                </label>
                <input type="text" class="sc-input @error('date_format') sc-input--error @enderror"
                    id="date_format" name="date_format"
                    value="{{ old('date_format', $general['date_format'] ?? 'Y-m-d') }}" required>
                @error('date_format')<p class="sc-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="sc-form-actions">
            <button type="submit" class="sc-btn sc-btn--primary">
                <i data-lucide="save" class="icon-sm"></i> Save General Settings
            </button>
        </div>
    </form>
</div>
