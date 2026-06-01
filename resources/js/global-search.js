/**
 * Smart global search for the topbar (live AJAX, keyboard navigation).
 */
export function initGlobalSearch() {
    const root = document.getElementById('globalSearchRoot');
    const input = document.getElementById('globalSearchInput');
    const dropdown = document.getElementById('globalSearchDropdown');

    if (!root) {
        console.warn('Global search root (#globalSearchRoot) not found');
        return;
    }

    if (!input) {
        console.warn('Global search input (#globalSearchInput) not found');
        return;
    }

    if (!dropdown) {
        console.warn('Global search dropdown (#globalSearchDropdown) not found');
        return;
    }

    const searchEndpoint = resolveSearchEndpoint(input);
    if (!searchEndpoint) {
        console.warn('Global search endpoint URL is missing');
        return;
    }

    if (import.meta.env?.DEV) {
        console.log('Global search initialized', { searchEndpoint });
    }

    const MIN_CHARS = 2;
    const DEBOUNCE_MS = 300;
    const SECTIONS = [
        { key: 'tenders', label: 'Tenders' },
        { key: 'drugs', label: 'Drugs / Products' },
        { key: 'companies', label: 'Companies' },
        { key: 'predictions', label: 'Predictions / Reports' },
    ];

    let debounceTimer = null;
    let activeRequest = null;
    let activeIndex = -1;
    let flatItems = [];
    let lastQuery = '';

    input.addEventListener('input', () => {
        const query = input.value.trim();
        window.clearTimeout(debounceTimer);

        if (query.length < MIN_CHARS) {
            cancelRequest();
            hideDropdown();
            lastQuery = '';
            return;
        }

        debounceTimer = window.setTimeout(() => {
            if (query !== input.value.trim()) {
                return;
            }
            performSearch(query);
        }, DEBOUNCE_MS);
    });

    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            hideDropdown();
            return;
        }

        if (!isDropdownOpen()) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            moveActive(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            moveActive(-1);
        } else if (event.key === 'Enter') {
            if (activeIndex >= 0 && flatItems[activeIndex]?.url) {
                event.preventDefault();
                window.location.href = flatItems[activeIndex].url;
            }
        }
    });

    input.addEventListener('focus', () => {
        const query = input.value.trim();
        if (query.length >= MIN_CHARS && lastQuery === query && flatItems.length > 0) {
            showDropdown();
        }
    });

    document.addEventListener('click', event => {
        if (!root.contains(event.target)) {
            hideDropdown();
        }
    });

    dropdown.addEventListener('click', event => {
        const item = event.target.closest('[data-global-search-item]');
        if (!item) {
            return;
        }

        const url = item.getAttribute('data-url');
        if (url) {
            window.location.href = url;
        }
    });

    dropdown.addEventListener('mouseover', event => {
        const item = event.target.closest('[data-global-search-item]');
        if (!item) {
            return;
        }

        const index = Number.parseInt(item.getAttribute('data-index') ?? '-1', 10);
        if (index >= 0) {
            setActiveIndex(index);
        }
    });

    function performSearch(query) {
        cancelRequest();
        lastQuery = query;
        setLoading(true);

        const controller = new AbortController();
        activeRequest = controller;

        const url = `${searchEndpoint}?${new URLSearchParams({ q: query }).toString()}`;

        fetch(url, {
            method: 'GET',
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async response => {
                const contentType = response.headers.get('content-type') ?? '';

                if (!response.ok) {
                    if (response.status === 401 || response.status === 419) {
                        throw new Error('Session expired. Please refresh and sign in again.');
                    }

                    if (response.status === 422 && contentType.includes('application/json')) {
                        const payload = await response.json();
                        const message = payload?.message ?? 'Invalid search query.';
                        throw new Error(message);
                    }

                    throw new Error(`Search request failed (${response.status})`);
                }

                if (!contentType.includes('application/json')) {
                    throw new Error('Unexpected response from server. Check APP_URL and authentication.');
                }

                return response.json();
            })
            .then(data => {
                if (controller.signal.aborted) {
                    return;
                }

                renderResults(data);
            })
            .catch(error => {
                if (error.name === 'AbortError') {
                    return;
                }

                renderMessage(error.message || 'Unable to load results. Please try again.');
            })
            .finally(() => {
                if (activeRequest === controller) {
                    activeRequest = null;
                }
            });
    }

    function renderResults(data) {
        flatItems = [];
        const fragments = [];

        SECTIONS.forEach(section => {
            const items = Array.isArray(data?.[section.key]) ? data[section.key] : [];
            if (items.length === 0) {
                return;
            }

            fragments.push(`<div class="global-search-section">
                <p class="global-search-section-title">${escapeHtml(section.label)}</p>
                <div class="global-search-section-body">`);

            items.forEach(item => {
                if (!item?.url) {
                    return;
                }

                const index = flatItems.length;
                flatItems.push(item);
                fragments.push(
                    `<button type="button" class="global-search-item" data-global-search-item data-url="${escapeAttr(item.url)}" data-index="${index}" role="option" aria-selected="false">
                        <span class="global-search-item-title">${escapeHtml(item.title ?? '')}</span>
                        <span class="global-search-item-subtitle">${escapeHtml(item.subtitle ?? '')}</span>
                    </button>`,
                );
            });

            fragments.push('</div></div>');
        });

        if (flatItems.length === 0) {
            renderMessage('No results found');
            return;
        }

        dropdown.innerHTML = fragments.join('');
        activeIndex = -1;
        setLoading(false);
        showDropdown();
    }

    function renderMessage(message) {
        flatItems = [];
        activeIndex = -1;
        dropdown.innerHTML = `<div class="global-search-message">${escapeHtml(message)}</div>`;
        setLoading(false);
        showDropdown();
    }

    function setLoading(isLoading) {
        dropdown.classList.toggle('is-loading', isLoading);

        if (isLoading) {
            dropdown.innerHTML = '<div class="global-search-message">Searching…</div>';
            showDropdown();
        }
    }

    function isDropdownOpen() {
        return dropdown.classList.contains('is-open');
    }

    function showDropdown() {
        dropdown.hidden = false;
        dropdown.classList.add('is-open');
        dropdown.setAttribute('data-open', 'true');
        input.setAttribute('aria-expanded', 'true');
    }

    function hideDropdown() {
        dropdown.hidden = true;
        dropdown.classList.remove('is-open');
        dropdown.removeAttribute('data-open');
        dropdown.classList.remove('is-loading');
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
        updateActiveHighlight();
    }

    function cancelRequest() {
        if (activeRequest) {
            activeRequest.abort();
            activeRequest = null;
        }
    }

    function moveActive(delta) {
        if (flatItems.length === 0) {
            return;
        }

        if (activeIndex < 0 && delta > 0) {
            setActiveIndex(0);
            return;
        }

        let next = activeIndex + delta;
        if (next < 0) {
            next = flatItems.length - 1;
        } else if (next >= flatItems.length) {
            next = 0;
        }

        setActiveIndex(next);
    }

    function setActiveIndex(index) {
        activeIndex = index;
        updateActiveHighlight();

        const activeEl = dropdown.querySelector(`[data-index="${index}"]`);
        if (activeEl && typeof activeEl.scrollIntoView === 'function') {
            activeEl.scrollIntoView({ block: 'nearest' });
        }
    }

    function updateActiveHighlight() {
        dropdown.querySelectorAll('[data-global-search-item]').forEach(el => {
            const index = Number.parseInt(el.getAttribute('data-index') ?? '-1', 10);
            const isActive = index === activeIndex;
            el.classList.toggle('is-active', isActive);
            el.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/`/g, '&#96;');
    }
}

function resolveSearchEndpoint(input) {
    const fromData = (input.dataset.globalSearchUrl || '').trim();
    const appBase = document.querySelector('meta[name="app-url"]')?.getAttribute('content')?.trim();

    if (fromData) {
        try {
            if (fromData.startsWith('http://') || fromData.startsWith('https://')) {
                const parsed = new URL(fromData);
                return `${parsed.origin}${parsed.pathname}`.replace(/\/$/, '') || fromData;
            }

            if (fromData.startsWith('/')) {
                return `${window.location.origin}${fromData}`.replace(/\/$/, '');
            }
        } catch {
            // fall through to app base
        }
    }

    if (appBase) {
        const base = appBase.endsWith('/') ? appBase.slice(0, -1) : appBase;
        return `${base}/global-search`;
    }

    return fromData || `${window.location.origin}/global-search`;
}
