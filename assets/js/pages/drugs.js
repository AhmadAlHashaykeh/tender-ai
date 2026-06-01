/**
 * Drug Pricing Intelligence - Filters & Chart Interaction
 */

document.addEventListener('DOMContentLoaded', () => {
    initDrugsPage();
});

function initDrugsPage() {
    const searchInput = document.getElementById('drug-search');
    const marketFilter = document.getElementById('market-filter');
    const categoryFilter = document.getElementById('category-filter');
    const drugCards = document.querySelectorAll('.drug-card-item');
    const countBadge = document.getElementById('drug-count-badge');

    // Chart elements
    const svg = document.querySelector('.recharts-surface');
    const hoverBg = document.getElementById('chart-hover-bg');
    const tooltip = document.getElementById('chart-tooltip');
    const hitAreas = document.querySelectorAll('.chart-hit-area');
    const barPaths = [
        document.getElementById('bar-rect-0'),
        document.getElementById('bar-rect-1'),
        document.getElementById('bar-rect-2'),
        document.getElementById('bar-rect-3'),
        document.getElementById('bar-rect-4')
    ];

    // Filter states
    let activeFilters = {
        search: '',
        market: 'all',
        category: 'all'
    };

    // 1. Search filter interaction
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            activeFilters.search = e.target.value.toLowerCase().trim();
            applyFilters();
        });
    }

    // 2. Market and category chips interaction
    setupChipFilters(marketFilter, 'market');
    setupChipFilters(categoryFilter, 'category');

    function setupChipFilters(container, type) {
        if (!container) return;
        const buttons = container.querySelectorAll('button');

        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const val = btn.getAttribute('data-value') || btn.textContent.trim();
                activeFilters[type] =
                    (val === 'All Markets' || val === 'All Categories' || val === 'all')
                        ? 'all'
                        : val;

                // Toggle active styling chips
                buttons.forEach(b => {
                    if (b === btn) {
                        b.className =
                            'px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-gradient-to-r from-primary to-secondary text-white border-transparent shadow-sm';
                    } else {
                        b.className =
                            'px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-white/60 border-border/50 text-foreground/70 hover:border-primary/40 hover:text-primary';
                    }
                });

                applyFilters();
            });
        });
    }

    // 3. Apply cumulative filtering
    function applyFilters() {
        let visibleCount = 0;

        drugCards.forEach(card => {
            const name = (card.getAttribute('data-name') || '').toLowerCase();
            const inn = (card.getAttribute('data-inn') || '').toLowerCase();
            const code = (card.getAttribute('data-code') || '').toLowerCase();
            const category = card.getAttribute('data-category') || '';
            const markets = (card.getAttribute('data-markets') || '').split(',');

            // Match Search Query
            const matchesSearch =
                !activeFilters.search ||
                name.includes(activeFilters.search) ||
                inn.includes(activeFilters.search) ||
                code.includes(activeFilters.search) ||
                category.toLowerCase().includes(activeFilters.search);

            // Match Market Chip
            const matchesMarket =
                activeFilters.market === 'all' ||
                markets.includes(activeFilters.market) ||
                (activeFilters.market === 'UAE' &&
                    (markets.includes('UAE') ||
                        markets.includes('United Arab Emirates')));

            // Match Category Chip
            const matchesCategory =
                activeFilters.category === 'all' ||
                category === activeFilters.category;

            // Show/Hide card item
            if (matchesSearch && matchesMarket && matchesCategory) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Update badge count
        if (countBadge) {
            countBadge.innerHTML = `${visibleCount} <span class="font-normal text-muted-foreground">products</span>`;
        }
    }

    /**
     * 4. SVG Price Distribution Chart Animation
     */
    function animateChart() {
        if (!barPaths.length) return;

        barPaths.forEach((path, index) => {
            if (!path) return;

            const x = parseFloat(path.getAttribute('x'));
            const width = parseFloat(path.getAttribute('width'));
            const finalHeight = parseFloat(path.dataset.height);
            const finalY = parseFloat(path.dataset.y);

            // Initial collapsed state
            path.setAttribute(
                'd',
                `M${x},145 L${x + width},145 L${x + width},145 L${x},145 Z`
            );

            // Smooth rendering
            path.style.transformOrigin = 'bottom';
            path.style.willChange = 'transform, opacity';
            path.style.transition = 'all 700ms cubic-bezier(0.22, 1, 0.36, 1)';

            // Stagger animation
            setTimeout(() => {
                const radius = 6;

                // Rounded bar path
                if (finalHeight > radius) {
                    path.setAttribute(
                        'd',
                        `
                        M${x},145
                        L${x + width},145
                        L${x + width},${finalY + radius}
                        Q${x + width},${finalY} ${x + width - radius},${finalY}
                        L${x + radius},${finalY}
                        Q${x},${finalY} ${x},${finalY + radius}
                        Z
                        `
                    );
                } else {
                    path.setAttribute(
                        'd',
                        `
                        M${x},145
                        L${x + width},145
                        L${x + width},${finalY}
                        L${x},${finalY}
                        Z
                        `
                    );
                }

                // Bounce animation
                path.animate(
                    [
                        {
                            transform: 'translateY(10px) scaleY(0.8)',
                            opacity: 0.4
                        },
                        {
                            transform: 'translateY(-4px) scaleY(1.03)',
                            opacity: 1
                        },
                        {
                            transform: 'translateY(0px) scaleY(1)'
                        }
                    ],
                    {
                        duration: 850,
                        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                        fill: 'forwards'
                    }
                );
            }, index * 140);
        });
    }

    animateChart();

    /**
     * 5. SVG Price Distribution Chart Custom Tooltip & Hovers
     */
    const ranges = [
        {
            title: '<$5',
            count: '1 drug',
            list: 'Paracetamol 500mg Tablet',
            barY: 110
        },
        {
            title: '$5–$10',
            count: '3 drugs',
            list: 'Amoxicillin, Ibuprofen, Omeprazole',
            barY: 40
        },
        {
            title: '$10–$20',
            count: '1 drug',
            list: 'Atorvastatin 20mg Tablet',
            barY: 110
        },
        {
            title: '$20–$50',
            count: '1 drug',
            list: 'Insulin Glargine 100U/mL Pen',
            barY: 110
        },
        {
            title: '>$50',
            count: '0 drugs',
            list: 'No products in this range',
            barY: 145
        }
    ];

    hitAreas.forEach(area => {
        const index = parseInt(area.getAttribute('data-index'));

        area.addEventListener('mouseenter', () => {
            showTooltip(index);

            // Active bar glow
            const activeBar = barPaths[index];
            if (activeBar) {
                activeBar.style.filter =
                    'drop-shadow(0 8px 14px rgba(13,133,230,0.35))';
                activeBar.style.fillOpacity = '1';
                activeBar.style.transform = 'translateY(-2px)';
            }
        });

        area.addEventListener('mousemove', () => {
            showTooltip(index);
        });

        area.addEventListener('mouseleave', () => {
            hideTooltip();

            const activeBar = barPaths[index];
            if (activeBar) {
                activeBar.style.filter = 'none';
                activeBar.style.fillOpacity = '0.75';
                activeBar.style.transform = 'translateY(0px)';
            }
        });
    });

    function showTooltip(index) {
        if (!svg || !tooltip || !hoverBg) return;

        const wrapper = svg.closest('.recharts-wrapper');
        if (!wrapper) return;

        const wrapperRect = wrapper.getBoundingClientRect();

        const viewBoxWidth = 757;
        const scaleX = wrapperRect.width / viewBoxWidth;
        const scaleY = wrapperRect.height / 180;

        const columnWidth = 144.6;
        const colX = 29 + (index * columnWidth);

        // Highlight column
        hoverBg.setAttribute('x', colX);
        hoverBg.classList.remove('hidden');

        // Tooltip content
        const data = ranges[index];

        document.getElementById('tooltip-title').textContent = data.title;

        const countTextEl = document.getElementById('tooltip-count');
        countTextEl.textContent = data.count;

        if (data.count === '0 drugs') {
            countTextEl.className =
                'font-semibold text-slate-400 mb-0.5';
        } else {
            countTextEl.className =
                'font-semibold text-primary mb-0.5';
        }

        document.getElementById('tooltip-list').textContent = data.list;

        tooltip.classList.remove('hidden');

        // Position tooltip
        const tooltipWidth = tooltip.offsetWidth;
        const tooltipHeight = tooltip.offsetHeight;

        const leftPos =
            (colX + columnWidth / 2) * scaleX -
            tooltipWidth / 2;

        const topPos =
            data.barY * scaleY -
            tooltipHeight -
            12;

        tooltip.style.left = `${Math.max(
            4,
            Math.min(leftPos, wrapperRect.width - tooltipWidth - 4)
        )}px`;

        tooltip.style.top = `${Math.max(4, topPos)}px`;
    }

    function hideTooltip() {
        if (hoverBg) hoverBg.classList.add('hidden');
        if (tooltip) tooltip.classList.add('hidden');
    }
}