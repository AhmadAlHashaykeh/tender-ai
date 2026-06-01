/**
 * Company Detail View Charts & Metrics Interactivity
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Chart Animations
    initChartAnimations();

    // 2. Initialize Leaderboard Chart interactions (tooltip, highlight, dataset toggle)
    initLeaderboardChart();

    // 3. Initialize Tender History filter tabs
    initTenderFilters();
});

/**
 * Animates the chart bars from 0 to their target heights
 */
function initChartAnimations() {
    const bars = document.querySelectorAll('.chart-bar-animate');
    // Small delay to ensure browser has painted the initial state (height=0, y=225)
    setTimeout(() => {
        bars.forEach(bar => {
            const targetY = bar.getAttribute('data-target-y');
            const targetHeight = bar.getAttribute('data-target-height');
            if (targetY && targetHeight) {
                bar.setAttribute('y', targetY);
                bar.setAttribute('height', targetHeight);
            }
        });
    }, 150);
}

/**
 * Handles the Leaderboard Chart interactions: tooltips, hover highlights, and dataset switching
 */
function initLeaderboardChart() {
    const wrapper = document.querySelector('.recharts-wrapper');
    if (!wrapper) return;

    const hoverBg = document.getElementById('leaderboard-hover-bg');
    const tooltip = document.getElementById('leaderboard-tooltip');
    const tooltipName = document.getElementById('leaderboard-tooltip-name');
    const tooltipWins = document.getElementById('leaderboard-tooltip-wins');
    const hoverCols = document.querySelectorAll('.leaderboard-hover-col');
    const bars = document.querySelectorAll('.chart-bar-animate');
    const yAxisTicks = document.querySelectorAll('.yAxis .recharts-cartesian-axis-tick text');

    // State
    let currentTab = 'leaderboard'; // 'leaderboard' or 'valueShare'

    // Data for both states
    const datasets = {
        leaderboard: [
            { name: 'PharmaCorp', value: 245, unit: 'wins', height: 207.3, y: 17.7 },
            { name: 'MediSupply', value: 198, unit: 'wins', height: 167.5, y: 57.5 },
            { name: 'HealthTech', value: 167, unit: 'wins', height: 141.3, y: 83.7 },
            { name: 'Global Pharma', value: 134, unit: 'wins', height: 113.4, y: 111.6 },
            { name: 'ArabPharma', value: 112, unit: 'wins', height: 94.8, y: 130.2 },
            { name: 'Gulf MedSupply', value: 88, unit: 'wins', height: 74.5, y: 150.5 }
        ],
        valueShare: [
            { name: 'PharmaCorp', value: 35, unit: '% share', height: 192.5, y: 32.5 },
            { name: 'MediSupply', value: 28, unit: '% share', height: 154.0, y: 71.0 },
            { name: 'HealthTech', value: 17, unit: '% share', height: 93.5, y: 131.5 },
            { name: 'Global Pharma', value: 12, unit: '% share', height: 66.0, y: 159.0 },
            { name: 'ArabPharma', value: 8, unit: '% share', height: 44.0, y: 181.0 },
            { name: 'Gulf MedSupply', value: 5, unit: '% share', height: 27.5, y: 197.5 }
        ]
    };

    const yAxisLabels = {
        leaderboard: ['0', '65', '130', '195', '260'],
        valueShare: ['0%', '10%', '20%', '30%', '40%']
    };

    // Hover logic
    hoverCols.forEach((col, index) => {
        col.addEventListener('mouseenter', (e) => {
            const data = datasets[currentTab][index];
            const bar = bars[index];

            // 1. Position and show hover background column
            if (hoverBg) {
                hoverBg.setAttribute('x', col.getAttribute('x'));
                hoverBg.setAttribute('width', col.getAttribute('width'));
                hoverBg.style.display = 'block';
            }

            // 2. Set tooltip content
            if (tooltipName) tooltipName.textContent = data.name;
            if (tooltipWins) {
                tooltipWins.textContent = `${data.value}${data.unit === 'wins' ? ' wins' : '%'}`;
                // Highlight color depending on selected bar (MediSupply has purple theme)
                if (data.name === 'MediSupply') {
                    tooltipWins.style.color = '#7C3AED'; // Secondary color
                } else {
                    tooltipWins.style.color = '#0D85E6'; // Primary color
                }
            }

            // 3. Highlight the specific bar (scale up slightly, full opacity for blue bars)
            if (bar) {
                bar.style.transform = 'scaleY(1.02)';
                bar.style.transformOrigin = 'bottom';
                if (data.name !== 'MediSupply') {
                    bar.setAttribute('opacity', '0.75');
                }
            }

            // 4. Highlight the corresponding xAxis label
            const ticks = document.querySelectorAll('.xAxis .recharts-cartesian-axis-tick text');
            if (ticks[index]) {
                ticks[index].setAttribute('fill', '#0f172a');
                ticks[index].style.fontWeight = '600';
            }

            // 5. Position and show tooltip
            updateTooltipPosition(e, col);
            if (tooltip) tooltip.classList.remove('hidden');
        });

        col.addEventListener('mousemove', (e) => {
            updateTooltipPosition(e, col);
        });

        col.addEventListener('mouseleave', () => {
            const data = datasets[currentTab][index];
            const bar = bars[index];

            // Hide hover background column
            if (hoverBg) hoverBg.style.display = 'none';

            // Hide tooltip
            if (tooltip) tooltip.classList.add('hidden');

            // Reset bar styles
            if (bar) {
                bar.style.transform = 'none';
                if (data.name !== 'MediSupply') {
                    bar.setAttribute('opacity', '0.45');
                }
            }

            // Reset xAxis label
            const ticks = document.querySelectorAll('.xAxis .recharts-cartesian-axis-tick text');
            if (ticks[index]) {
                ticks[index].setAttribute('fill', '#64748B');
                ticks[index].style.fontWeight = 'normal';
            }
        });
    });

    function updateTooltipPosition(e, col) {
        if (!tooltip) return;

        const rect = wrapper.getBoundingClientRect();
        const scaleX = rect.width / 757;

        // X: Centered on the hovered column
        const colX = parseFloat(col.getAttribute('x'));
        const colW = parseFloat(col.getAttribute('width'));
        const colCenter = colX + colW / 2;
        const pixelLeft = colCenter * scaleX;

        // Y: Follows mouse cursor inside the column
        const pixelTop = e.clientY - rect.top;

        tooltip.style.left = `${pixelLeft}px`;
        tooltip.style.top = `${pixelTop}px`;
    }

    // Dataset switching logic (Leaderboard vs Value Share tabs)
    const tabButtons = Array.from(document.querySelectorAll('button')).filter(
        btn => btn.textContent.trim() === 'Leaderboard' || btn.textContent.trim() === 'Value Share'
    );

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabName = btn.textContent.trim() === 'Leaderboard' ? 'leaderboard' : 'valueShare';
            if (tabName === currentTab) return;

            currentTab = tabName;

            // Update active state class on buttons
            tabButtons.forEach(b => {
                if (b === btn) {
                    b.className = 'px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 bg-white shadow-sm text-foreground';
                } else {
                    b.className = 'px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 text-muted-foreground hover:text-foreground';
                }
            });

            // Update chart bars heights and target values
            const selectedDataset = datasets[currentTab];
            bars.forEach((bar, index) => {
                const data = selectedDataset[index];
                bar.setAttribute('data-target-y', data.y);
                bar.setAttribute('data-target-height', data.height);
                bar.setAttribute('y', data.y);
                bar.setAttribute('height', data.height);
                
                // Update corresponding hover col attributes just in case
                if (hoverCols[index]) {
                    hoverCols[index].setAttribute('data-wins', data.value);
                    hoverCols[index].setAttribute('data-bar-y', data.y);
                }
            });

            // Update Y-Axis Ticks text
            const labels = yAxisLabels[currentTab];
            yAxisTicks.forEach((tick, index) => {
                if (labels[index] !== undefined) {
                    const tspan = tick.querySelector('tspan');
                    if (tspan) {
                        tspan.textContent = labels[index];
                    } else {
                        tick.textContent = labels[index];
                    }
                }
            });
        });
    });
}

/**
 * Adds filtering functionality to the Tender History list
 */
function initTenderFilters() {
    const filterButtons = document.querySelectorAll('.flex.items-center.gap-1\\.5.flex-wrap button');
    const tenderItems = document.querySelectorAll('.divide-y.divide-border\\/20 > div');

    if (!filterButtons.length || !tenderItems.length) return;

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const filterVal = btn.textContent.trim().toLowerCase();

            // Set active class
            filterButtons.forEach(b => {
                if (b === btn) {
                    b.className = 'px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-gradient-to-r from-primary to-secondary text-white border-transparent shadow-sm';
                } else {
                    b.className = 'px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-white/60 border-border/50 text-foreground/70 hover:border-primary/40 hover:text-primary';
                }
            });

            // Filter items
            tenderItems.forEach(item => {
                const badge = item.querySelector('span.rounded-full');
                if (!badge) return;

                const status = badge.textContent.trim().toLowerCase();

                if (filterVal === 'all' || status === filterVal) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
}
