{{-- System Overview – read-only info panel --}}
<div class="sc-section">
    <div class="sc-section-header">
        <span class="sc-section-icon"><i data-lucide="monitor" class="icon-sm"></i></span>
        <div>
            <h3 class="sc-section-title">System Overview</h3>
            <p class="sc-section-desc">Live environment and usage snapshot. Read-only.</p>
        </div>
    </div>

    <div class="sc-stat-grid">
        <div class="sc-stat-card">
            <span class="sc-stat-label">Environment</span>
            <span class="sc-stat-value">{{ $overview['environment'] }}</span>
        </div>
        <div class="sc-stat-card">
            <span class="sc-stat-label">Laravel</span>
            <span class="sc-stat-value">{{ $overview['laravel_version'] }}</span>
        </div>
        <div class="sc-stat-card">
            <span class="sc-stat-label">PHP</span>
            <span class="sc-stat-value">{{ $overview['php_version'] }}</span>
        </div>
        <div class="sc-stat-card">
            <span class="sc-stat-label">Database</span>
            <span class="sc-stat-value">{{ $overview['db_driver'] }}</span>
        </div>
        <div class="sc-stat-card">
            <span class="sc-stat-label">Queue</span>
            <span class="sc-stat-value">{{ $overview['queue_driver'] }}</span>
        </div>
        <div class="sc-stat-card">
            <span class="sc-stat-label">Pricing Analysis</span>
            <span class="sc-stat-value">{{ $overview['prediction_engine'] }}</span>
        </div>
    </div>

    <div class="sc-divider"></div>

    <div class="sc-stat-grid">
        <div class="sc-stat-card sc-stat-card--accent">
            <span class="sc-stat-label">AI Provider</span>
            <span class="sc-stat-value">{{ $overview['ai_provider'] }}</span>
            <span class="sc-stat-sub">{{ $overview['ai_model'] }}</span>
        </div>
        <div class="sc-stat-card sc-stat-card--accent">
            <span class="sc-stat-label">Market Statistics Refresh</span>
            <span class="sc-stat-value">{{ $overview['stats_last_refresh'] }}</span>
        </div>
        <div class="sc-stat-card sc-stat-card--accent">
            <span class="sc-stat-label">Total Recommendations</span>
            <span class="sc-stat-value">{{ number_format($overview['total_predictions']) }}</span>
        </div>
        <div class="sc-stat-card sc-stat-card--accent">
            <span class="sc-stat-label">Total Imports</span>
            <span class="sc-stat-value">{{ number_format($overview['total_imports']) }}</span>
        </div>
        <div class="sc-stat-card sc-stat-card--accent">
            <span class="sc-stat-label">Users</span>
            <span class="sc-stat-value">{{ $overview['total_users'] }}</span>
        </div>
    </div>
</div>
