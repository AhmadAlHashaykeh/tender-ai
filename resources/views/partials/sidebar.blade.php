<aside class="sidebar" id="sidebar">
    <div class="sidebar-decor-top float-decoration"></div>
    <div class="sidebar-decor-bottom float-decoration" style="animation-delay: 3s;"></div>

    <header class="sidebar-header">
        <a href="{{ route('dashboard') }}" class="brand-container brand-home-link" aria-label="Go to dashboard">
            <div class="brand-logo">
                <div class="logo-shine"></div>
                <i data-lucide="zap" class="logo-icon"></i>
            </div>
            <div class="brand-text">
                <h1 class="brand-name">TenderAI</h1>
                <p class="brand-tagline">Pricing Intelligence</p>
            </div>
        </a>
    </header>

    <nav class="sidebar-nav custom-scrollbar">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
            <div class="nav-icon-wrapper"><i data-lucide="layout-dashboard"></i></div>
            <span class="nav-label">Dashboard</span>
        </a>
        <a class="nav-link {{ request()->routeIs('uploads.*') ? 'active' : '' }}" href="{{ route('uploads.index') }}">
            <div class="nav-icon-wrapper"><i data-lucide="upload"></i></div>
            <span class="nav-label">Upload Data</span>
        </a>
        <a class="nav-link {{ request()->routeIs('management.*') ? 'active' : '' }}" href="{{ route('management.index') }}">
            <div class="nav-icon-wrapper"><i data-lucide="database"></i></div>
            <span class="nav-label">Data Management</span>
        </a>
        <a class="nav-link {{ request()->routeIs('companies.*') ? 'active' : '' }}" href="{{ route('companies.index') }}">
            <div class="nav-icon-wrapper"><i data-lucide="building-2"></i></div>
            <span class="nav-label">Companies</span>
        </a>
        <a class="nav-link {{ request()->routeIs('tenders.*') ? 'active' : '' }}" href="{{ route('tenders.index') }}">
            <div class="nav-icon-wrapper"><i data-lucide="file-stack"></i></div>
            <span class="nav-label">Tenders</span>
        </a>
        <a class="nav-link {{ request()->routeIs('drugs.*') ? 'active' : '' }}" href="{{ route('drugs.index') }}">
            <div class="nav-icon-wrapper"><i data-lucide="microscope"></i></div>
            <span class="nav-label">Drug Catalog</span>
        </a>
        <a class="nav-link {{ request()->routeIs('standardization.*') ? 'active' : '' }}" href="{{ route('standardization.index') }}">
            <div class="nav-icon-wrapper"><i data-lucide="pill"></i></div>
            <span class="nav-label">Product Matching</span>
        </a>
        <a class="nav-link ai-link {{ request()->routeIs('ai.recommendations.*') ? 'active' : '' }}" href="{{ route('ai.recommendations.create') }}">
            <div class="nav-icon-wrapper"><i data-lucide="sparkles"></i></div>
            <span class="nav-label">Price Recommendation</span>
        </a>
        <a class="nav-link {{ request()->routeIs('predictions.*') ? 'active' : '' }}" href="{{ route('predictions.index') }}">
            <div class="nav-icon-wrapper"><i data-lucide="history"></i></div>
            <span class="nav-label">Recommendation History</span>
        </a>
        <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
            <div class="nav-icon-wrapper"><i data-lucide="chart-column"></i></div>
            <span class="nav-label">Reports</span>
        </a>
        <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">
            <div class="nav-icon-wrapper"><i data-lucide="settings"></i></div>
            <span class="nav-label">Settings</span>
        </a>
    </nav>

    <footer class="sidebar-footer">
        <div id="profileDropdown" class="profile-dropdown">
            <div class="dropdown-container">
                <a class="dropdown-item" href="{{ route('settings.index') }}">
                    <i data-lucide="user" class="dropdown-icon"></i>
                    <span class="dropdown-label">Profile</span>
                </a>
                <a class="dropdown-item" href="{{ route('settings.index') }}">
                    <i data-lucide="settings" class="dropdown-icon"></i>
                    <span class="dropdown-label">Settings</span>
                </a>
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}" id="logout-form" class="m-0">
                    @csrf
                    <button type="submit" class="dropdown-item logout w-full text-left border-0 bg-transparent cursor-pointer">
                        <i data-lucide="log-out" class="dropdown-icon"></i>
                        <span class="dropdown-label">Logout</span>
                    </button>
                </form>
            </div>
        </div>
        <button id="profileBtn" class="user-profile-btn" type="button">
            <div class="user-avatar">
                <div class="avatar-shine"></div>
                <span class="avatar-text">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}</span>
            </div>
            <div class="user-info">
                <p class="user-name">{{ auth()->user()->name ?? 'User' }}</p>
                <p class="user-role">{{ auth()->user()->email ?? 'Authenticated' }}</p>
            </div>
            <i data-lucide="chevron-up" class="chevron-up"></i>
        </button>
    </footer>
</aside>
