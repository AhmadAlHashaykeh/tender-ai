/**
 * Tenders List Search & Filters Interactivity
 */

document.addEventListener('DOMContentLoaded', () => {
    initTendersFilter();
});

function initTendersFilter() {
    const searchInput = document.getElementById('tender-search');
    const countryFilter = document.getElementById('country-filter');
    const regionFilter = document.getElementById('region-filter');
    const yearFilter = document.getElementById('year-filter');
    const tenderCards = document.querySelectorAll('.tender-card-item');
    const countBadgeText = document.querySelector('#tender-count-badge');

    if (!tenderCards.length) return;

    // Filter states
    let activeFilters = {
        search: '',
        country: 'all',
        region: 'all',
        year: 'all'
    };

    // 1. Search Input Event
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            activeFilters.search = e.target.value.toLowerCase().trim();
            applyFilters();
        });
    }

    // 2. Chip Click Events
    setupChipFilters(countryFilter, 'country');
    setupChipFilters(regionFilter, 'region');
    setupChipFilters(yearFilter, 'year');

    function setupChipFilters(container, type) {
        if (!container) return;
        const buttons = container.querySelectorAll('button');
        
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const val = btn.getAttribute('data-value');
                activeFilters[type] = val;

                // Toggle active style classes
                buttons.forEach(b => {
                    if (b === btn) {
                        b.className = 'px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-gradient-to-r from-primary to-secondary text-white border-transparent shadow-sm';
                    } else {
                        b.className = 'px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-white/60 border-border/50 text-foreground/70 hover:border-primary/40 hover:text-primary';
                    }
                });

                applyFilters();
            });
        });
    }

    // 3. Apply all filters
    function applyFilters() {
        let visibleCount = 0;

        tenderCards.forEach(card => {
            const name = (card.getAttribute('data-name') || '').toLowerCase();
            const code = (card.getAttribute('data-code') || '').toLowerCase();
            const country = card.getAttribute('data-country') || '';
            const region = card.getAttribute('data-region') || '';
            const year = card.getAttribute('data-year') || '';
            const category = (card.getAttribute('data-category') || '').toLowerCase();

            // Match Search (checks name, code, country, or category)
            const matchesSearch = !activeFilters.search ||
                name.includes(activeFilters.search) ||
                code.includes(activeFilters.search) ||
                country.toLowerCase().includes(activeFilters.search) ||
                category.includes(activeFilters.search);

            // Match Country
            const matchesCountry = activeFilters.country === 'all' || country === activeFilters.country;

            // Match Region
            const matchesRegion = activeFilters.region === 'all' || region === activeFilters.region;

            // Match Year
            const matchesYear = activeFilters.year === 'all' || year === activeFilters.year;

            // Show or hide card
            if (matchesSearch && matchesCountry && matchesRegion && matchesYear) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // 4. Update tender count badge text
        if (countBadgeText) {
            countBadgeText.innerHTML = `${visibleCount} <span class="font-normal text-muted-foreground">of ${tenderCards.length} tenders</span>`;
        }
    }
}
