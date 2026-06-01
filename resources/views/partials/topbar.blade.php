<header class="top-header">
    <div class="header-inner">
        <div class="header-left">
            <button class="mobile-menu-btn" type="button" aria-label="Toggle Menu">
                <i data-lucide="menu"></i>
            </button>
            <div class="search-container" id="globalSearchRoot">
                <i data-lucide="search" class="search-icon-header"></i>
                <input
                    type="search"
                    id="globalSearchInput"
                    class="search-input"
                    placeholder="Search tenders, drugs, reports..."
                    autocomplete="off"
                    role="combobox"
                    aria-expanded="false"
                    aria-controls="globalSearchDropdown"
                    aria-autocomplete="list"
                    data-global-search-url="{{ url('/global-search') }}">
                <div
                    id="globalSearchDropdown"
                    class="global-search-dropdown"
                    role="listbox"
                    aria-label="Global search results"
                    hidden></div>
            </div>
        </div>
        <div class="header-right">
            <a href="{{ route('ai.recommendations.create') }}" class="no-underline">
                <button type="button" class="btn-ai">
                    <i data-lucide="sparkles" class="icon-xs"></i>
                    New AI Prediction
                </button>
            </a>
        </div>
    </div>
</header>
