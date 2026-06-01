/**
 * Drug Pricing Intelligence - Details View Interactions & Animations
 */

document.addEventListener('DOMContentLoaded', () => {
    initDrugDetails();
});

function initDrugDetails() {
    // 1. Data Store
    const marketData = {
        all: {
            name: "All Markets",
            avgPrice: 4.00,
            minPrice: 2.90,
            maxPrice: 5.80,
            qty: 4.7,
            qtyStr: "4.7M",
            tenders: 26,
            value: 18.60,
            change: "+8.5%",
            priceTrend: [3.80, 3.95, 4.05, 4.10, 4.00],
            qtyTrend: [850, 920, 980, 1050, 900],
            insights: [
                "Regional market average price is stable at $4.00 per unit.",
                "Total volume across all procurement regions has reached 4.7M units.",
                "Multinational companies dominate high-value UAE and Saudi tenders.",
                "Egypt and Jordan offer highly competitive pricing with high volumes."
            ]
        },
        saudi: {
            name: "Saudi Arabia",
            avgPrice: 4.20,
            minPrice: 3.80,
            maxPrice: 4.80,
            qty: 1.2,
            qtyStr: "1.2M",
            tenders: 8,
            value: 5.04,
            change: "+10.2%",
            priceTrend: [4.00, 4.15, 4.25, 4.30, 4.20],
            qtyTrend: [200, 230, 250, 270, 250],
            insights: [
                "Saudi Arabia is a premium pricing market with an average of $4.20.",
                "Strong preference for SFDA approved local and multinational brands.",
                "National Unified Procurement Company (NUPCO) drives major tenders.",
                "Awarded prices show low volatility due to strict reference pricing."
            ]
        },
        uae: {
            name: "UAE",
            avgPrice: 5.10,
            minPrice: 4.60,
            maxPrice: 5.80,
            qty: 0.45,
            qtyStr: "450K",
            tenders: 4,
            value: 2.29,
            change: "+6.3%",
            priceTrend: [4.80, 5.00, 5.20, 5.30, 5.10],
            qtyTrend: [80, 90, 100, 105, 75],
            insights: [
                "UAE records the highest average price in the region at $5.10.",
                "Highly decentralized procurement across Dubai Health Authority (DHA) and SEHA.",
                "High percentage of innovative and patented biologics in public tenders.",
                "Premium packaging and strict cold chain requirements add to average unit cost."
            ]
        },
        egypt: {
            name: "Egypt",
            avgPrice: 3.50,
            minPrice: 3.10,
            maxPrice: 4.00,
            qty: 1.8,
            qtyStr: "1.8M",
            tenders: 6,
            value: 6.30,
            change: "+12.9%",
            priceTrend: [3.10, 3.30, 3.45, 3.55, 3.50],
            qtyTrend: [300, 330, 350, 380, 440],
            insights: [
                "Egypt is the most price-competitive market at $3.50 avg — lowest in the region.",
                "Highest annual volume at 1.8M units — large-scale government procurement.",
                "Global Pharma Partners consistently wins in Egypt on aggressive pricing.",
                "Price controls by MOH Egypt keep awarded prices within a narrow range."
            ]
        },
        iraq: {
            name: "Iraq",
            avgPrice: 4.00,
            minPrice: 3.60,
            maxPrice: 4.50,
            qty: 0.9,
            qtyStr: "900K",
            tenders: 5,
            value: 3.60,
            change: "+8.1%",
            priceTrend: [3.70, 3.90, 4.10, 4.20, 4.00],
            qtyTrend: [150, 170, 200, 210, 170],
            insights: [
                "Iraq average pricing stands at $4.00, matching the regional average.",
                "Kimadia (State Company for Marketing Drugs & Medical Appliances) handles all major public tenders.",
                "High demand for essential medicines drives volume stability.",
                "Tender awards are heavily weighted on delivery timeline reliability."
            ]
        },
        jordan: {
            name: "Jordan",
            avgPrice: 3.20,
            minPrice: 2.90,
            maxPrice: 3.60,
            qty: 0.3,
            qtyStr: "300K",
            tenders: 3,
            value: 0.96,
            change: "+10.3%",
            priceTrend: [2.90, 3.10, 3.25, 3.35, 3.20],
            qtyTrend: [50, 60, 65, 70, 55],
            insights: [
                "Jordan demonstrates competitive pricing at $3.20 per unit.",
                "Joint Procurement Department (JPD) consolidates public sector demand.",
                "Strong local generic industry (e.g. Hikma) provides active price competition.",
                "Strict quality compliance standards match European Pharmacopoeia requirements."
            ]
        }
    };

    // Tracking state
    let activeMarket = 'egypt'; // Egypt active by default
    let activeChartMode = 'single'; // 'single' or 'compare'
    
    let currentStats = {
        avgPrice: 3.50,
        minPrice: 3.10,
        maxPrice: 4.00,
        qty: 1.8,
        tenders: 6,
        value: 6.30
    };

    // Elements
    const chipsContainer = document.getElementById('market-context-chips');
    const tableRows = document.querySelectorAll('.market-row');
    const toggleSingleBtn = document.getElementById('toggle-single');
    const toggleCompareBtn = document.getElementById('toggle-compare');
    const chartSingleContainer = document.getElementById('chart-single-container');
    const chartCompareContainer = document.getElementById('chart-compare-container');
    const chartCardTitle = document.getElementById('chart-card-title');
    const chartCardSubtitle = document.getElementById('chart-card-subtitle');
    const insightBadge = document.getElementById('insight-market-badge');
    const insightsList = document.getElementById('insights-list');

    // Stats element text boxes
    const avgPriceText = document.getElementById('stat-avg-price');
    const minPriceText = document.getElementById('stat-min-price');
    const maxPriceText = document.getElementById('stat-max-price');
    const qtyText = document.getElementById('stat-qty');
    const tendersText = document.getElementById('stat-tenders');
    const valueText = document.getElementById('stat-value');

    // 2. Set Up Active State Handler
    function setActiveMarket(marketKey) {
        if (!marketData[marketKey]) return;
        activeMarket = marketKey;

        // Synchronize Chips UI
        const chips = document.querySelectorAll('.market-chip');
        chips.forEach(chip => {
            const m = chip.getAttribute('data-market');
            if (m === marketKey) {
                chip.className = "market-chip px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-gradient-to-r from-primary to-secondary text-white border-transparent shadow-sm";
            } else {
                chip.className = "market-chip px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-white/60 border-border/50 text-foreground/70 hover:border-primary/40 hover:text-primary";
            }
        });

        // Synchronize Table Row Highlight
        tableRows.forEach(row => {
            const m = row.getAttribute('data-market');
            if (m === marketKey) {
                // Highlight row with beautiful primary tint
                row.className = "border-b border-border/20 cursor-pointer transition-all duration-200 bg-primary/5 market-row";
                const pin = row.querySelector('.lucide-map-pin');
                if (pin) {
                    pin.classList.remove('text-muted-foreground');
                    pin.classList.add('text-primary');
                }
                const label = row.querySelector('span');
                if (label) {
                    label.classList.remove('text-foreground');
                    label.classList.add('text-primary');
                }
            } else {
                row.className = "border-b border-border/20 cursor-pointer transition-all duration-200 hover:bg-muted/20 market-row";
                const pin = row.querySelector('.lucide-map-pin');
                if (pin) {
                    pin.classList.add('text-muted-foreground');
                    pin.classList.remove('text-primary');
                }
                const label = row.querySelector('span');
                if (label) {
                    label.classList.add('text-foreground');
                    label.classList.remove('text-primary');
                }
            }
        });

        // Animate Key Metrics
        const targetData = marketData[marketKey];
        animateValue(avgPriceText, currentStats.avgPrice, targetData.avgPrice, 800, 'price');
        animateValue(minPriceText, currentStats.minPrice, targetData.minPrice, 800, 'price');
        animateValue(maxPriceText, currentStats.maxPrice, targetData.maxPrice, 800, 'price');
        
        // For total quantity, All Markets is 4.7, others might have K or M formatting
        const isM = targetData.qtyStr.includes('M');
        animateValue(qtyText, currentStats.qty, targetData.qty, 800, isM ? 'qtyM' : 'qtyK');
        
        animateValue(tendersText, currentStats.tenders, targetData.tenders, 800, 'tenders');
        animateValue(valueText, currentStats.value, targetData.value, 800, 'value');

        // Save current values to state for next animation transition
        currentStats = {
            avgPrice: targetData.avgPrice,
            minPrice: targetData.minPrice,
            maxPrice: targetData.maxPrice,
            qty: targetData.qty,
            tenders: targetData.tenders,
            value: targetData.value
        };

        // Update Insights
        if (insightBadge) {
            insightBadge.innerText = targetData.name;
        }
        if (insightsList) {
            insightsList.style.opacity = '0';
            setTimeout(() => {
                insightsList.innerHTML = targetData.insights.map(insight => `
                    <li class="flex items-start gap-2.5 text-sm text-foreground/80 transition-all duration-300">
                        <div class="w-1.5 h-1.5 rounded-full bg-primary mt-1.5 shrink-0"></div>
                        ${insight}
                    </li>
                `).join('');
                insightsList.style.opacity = '1';
            }, 150);
        }

        // Re-render & Re-animate Charts
        updateCharts(marketKey);
    }

    // 3. Setup Numeric Increment Animation
    function animateValue(element, start, end, duration, format) {
        if (!element) return;
        let startTimestamp = null;
        
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3); // Cubic ease out
            const val = start + ease * (end - start);

            if (format === 'price') {
                element.innerText = `$${val.toFixed(2)}`;
            } else if (format === 'qtyM') {
                element.innerText = `${val.toFixed(1)}M`;
            } else if (format === 'qtyK') {
                element.innerText = `${Math.floor(val * 1000)}K`;
            } else if (format === 'value') {
                element.innerText = `$${val.toFixed(2)}M`;
            } else if (format === 'tenders') {
                element.innerText = Math.floor(val);
            } else {
                element.innerText = val.toFixed(1);
            }

            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }

    // 4. Setup Line Chart Renderer & Draw Animation
    function updateLineChart(marketKey) {
        const data = marketData[marketKey];
        const prices = data.priceTrend;
        const volumes = data.qtyTrend;
        
        // Dynamic scale threshold based on maximum price
        const maxVal = Math.max(...prices);
        let yMax = 4.0;
        if (maxVal > 5.0) yMax = 6.0;
        else if (maxVal > 4.0) yMax = 5.0;
        else if (maxVal > 3.0) yMax = 4.0;
        else yMax = 3.0;

        // Render Y Ticks
        const lineYAxis = document.getElementById('line-chart-y-axis');
        if (lineYAxis) {
            const steps = [0, yMax * 0.25, yMax * 0.5, yMax * 0.75, yMax];
            lineYAxis.innerHTML = steps.map((val, idx) => {
                const y = 225 - (idx * 55); // 225 at bottom, scale up to 5
                return `<text orientation="left" stroke="none" font-size="11" x="41" y="${y}" class="recharts-text recharts-cartesian-axis-tick-value" text-anchor="end" fill="#64748B"><tspan x="41" dy="0.355em">$${val.toFixed(1)}</tspan></text>`;
            }).join('');
        }

        // Draw Line Curve
        const xCoords = [49, 224.75, 400.5, 576.25, 752];
        const points = prices.map((price, idx) => {
            const x = xCoords[idx];
            const y = 225 - (price / yMax) * 220;
            return { x, y, price, year: 2022 + idx, volume: volumes[idx] };
        });

        // Cubic Bezier curve algorithm
        let pathD = `M${points[0].x},${points[0].y}`;
        for (let i = 0; i < points.length - 1; i++) {
            const curr = points[i];
            const next = points[i + 1];
            const cpX1 = curr.x + (next.x - curr.x) / 3;
            const cpY1 = curr.y;
            const cpX2 = curr.x + 2 * (next.x - curr.x) / 3;
            const cpY2 = next.y;
            pathD += ` C${cpX1},${cpY1} ${cpX2},${cpY2} ${next.x},${next.y}`;
        }

        const pathEl = document.getElementById('trend-line-path');

if (pathEl) {
    pathEl.setAttribute('d', pathD);
    
    // We must reset the element to trigger a fresh animation
    const length = pathEl.getTotalLength() || 1000;
    pathEl.style.strokeDasharray = length;
    pathEl.style.strokeDashoffset = length;
    
    pathEl.animate([
        { strokeDashoffset: length },
        { strokeDashoffset: 0 }
    ], {
        duration: 1400,
        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
        fill: 'forwards'
    });
}

        // Render Dots/Markers
        const dotsGroup = document.getElementById('line-chart-dots');
        if (dotsGroup) {
dotsGroup.innerHTML = points.map((p, idx) => `
    <circle 
        r="0"
        stroke="#0D85E6"
        stroke-width="2.5"
        fill="#FFFFFF"
        cx="${p.x}"
        cy="${p.y}"
        class="recharts-dot recharts-line-dot cursor-pointer transition-all duration-300"
        data-idx="${idx}"
    ></circle>
`).join('');

// Animate dots in sequence
setTimeout(() => {
    const dots = dotsGroup.querySelectorAll('.recharts-line-dot');

    dots.forEach((dot, idx) => {
        setTimeout(() => {
            dot.animate(
                [
                    {
                        r: 0,
                        opacity: 0,
                        transform: 'scale(0.4)'
                    },
                    {
                        r: 8,
                        opacity: 1,
                        transform: 'scale(1.15)'
                    },
                    {
                        r: 4.5,
                        opacity: 1,
                        transform: 'scale(1)'
                    }
                ],
                {
                    duration: 500,
                    easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                    fill: 'forwards'
                }
            );

            dot.setAttribute('r', '4.5');
        }, idx * 90);
    });
}, 150);
        }

        // Render Interactive Rects
        const rectsGroup = document.getElementById('line-chart-interactive-rects');
        if (rectsGroup) {
            rectsGroup.innerHTML = points.map((p, idx) => {
                const w = idx === 0 ? 137.875 : (idx === 4 ? 137.875 : 175.75);
                const x = p.x - w / 2;
                return `<rect x="${x}" y="5" width="${w}" height="220" fill="transparent" class="cursor-pointer line-hover-col" data-idx="${idx}"></rect>`;
            }).join('');
        }

        // Save reference coordinates to window object
        window.activePoints = points;
    }

    // 5. Setup Bar Chart Comparison Redraw & Animation
    function updateCompareChart(activeKey) {
        const countriesList = [
            { key: 'uae', name: "UAE", price: 5.10 },
            { key: 'saudi', name: "Saudi Arabia", price: 4.20 },
            { key: 'iraq', name: "Iraq", price: 4.00 },
            { key: 'egypt', name: "Egypt", price: 3.50 },
            { key: 'jordan', name: "Jordan", price: 3.20 }
        ];

        const yMax = 8.0;
        const xCoords = [110, 248.5, 387, 525.5, 664]; // Perfectly centered bar spaces

        const barGroup = document.getElementById('bar-chart-bars-group');
        if (barGroup) {
            barGroup.innerHTML = countriesList.map((m, idx) => {
                const barWidth = 46;
                const x = xCoords[idx] - barWidth / 2;
                const barHeight = (m.price / yMax) * 220;
                const y = 225 - barHeight;
                
                const isActive = m.key === activeKey;
                const fillHex = isActive ? "#7C3AED" : "#93C5FD"; // Sleek dynamic highlight coloring (purple vs light blue)

                return `
                    <g class="bar-group cursor-pointer" data-key="${m.key}">
                        <rect class="chart-bar-animate" x="${x}" y="225" width="${barWidth}" height="0" fill="${fillHex}" rx="6" data-target-y="${y}" data-target-height="${barHeight}"></rect>
                        <text x="${x + barWidth/2}" y="${y - 8}" font-size="12" fill="${isActive ? '#7C3AED' : '#64748B'}" font-weight="${isActive ? '700' : '500'}" text-anchor="middle" class="opacity-0 transition-opacity duration-300 delay-300 bar-label">${m.price.toFixed(2)}</text>
                        <text x="${x + barWidth/2}" y="243" font-size="11" fill="#64748B" font-weight="${isActive ? '600' : '400'}" text-anchor="middle" class="recharts-text">${m.name}</text>
                    </g>
                `;
            }).join('');

            // Trigger visual height transition in next frame
            setTimeout(() => {
                const rects = barGroup.querySelectorAll('.chart-bar-animate');
                rects.forEach(rect => {
                    const targetY = rect.getAttribute('data-target-y');
                    const targetHeight = rect.getAttribute('data-target-height');
                    if (targetY && targetHeight) {
                        rect.setAttribute('y', targetY);
                        rect.setAttribute('height', targetHeight);
                    }
                });

                document.querySelectorAll('.bar-label').forEach(label => label.classList.remove('opacity-0'));
            }, 50);
        }

        // Set legend highlight
        const legendText = document.getElementById('bar-chart-legend-text');
        if (legendText) {
            legendText.innerText = `${marketData[activeKey]?.name || 'Egypt'} highlighted`;
        }
    }

    // 6. Universal Charts Router
    function updateCharts(marketKey) {
        if (activeChartMode === 'single') {
            const data = marketData[marketKey];
            if (chartCardTitle) {
                chartCardTitle.innerText = `Price Trend — ${data.name}`;
            }
            if (chartCardSubtitle) {
                chartCardSubtitle.innerText = `Awarded price per unit · ${data.change} change since 2022`;
            }
            updateLineChart(marketKey);
        } else {
            if (chartCardTitle) {
                chartCardTitle.innerText = "Market Price Comparison";
            }
            if (chartCardSubtitle) {
                chartCardSubtitle.innerText = "Average awarded price across all markets for this drug";
            }
            updateCompareChart(marketKey);
        }
    }

    // 7. Initialize Line Chart Interactive Hover Events (Recharts style)
    document.addEventListener('mouseover', (e) => {
        if (e.target.classList.contains('line-hover-col')) {
            const idx = parseInt(e.target.getAttribute('data-idx'));
            const point = window.activePoints ? window.activePoints[idx] : null;
            if (!point) return;

            // Highlight Vertical Guide Line
            const guideLine = document.getElementById('vertical-guide-line');
            if (guideLine) {
                guideLine.setAttribute('x1', point.x);
                guideLine.setAttribute('x2', point.x);
                guideLine.classList.remove('hidden');
            }

            // Highlight corresponding dot
            const dots = document.querySelectorAll('.recharts-line-dot');
            dots.forEach((dot, dIdx) => {
                if (dIdx === idx) {
                    dot.setAttribute('r', '7.5');
                    dot.setAttribute('fill', '#0D85E6');
                } else {
                    dot.setAttribute('r', '4.5');
                    dot.setAttribute('fill', '#FFFFFF');
                }
            });

            // Display Tooltip
            const tooltip = document.getElementById('chart-tooltip');
            if (tooltip && chartSingleContainer) {
                const rect = chartSingleContainer.getBoundingClientRect();
                const pixelX = (point.x / 757) * rect.width;
                const pixelY = (point.y / 260) * rect.height;

                document.getElementById('tooltip-year').innerText = point.year;
                document.getElementById('tooltip-price').innerText = `$${point.price.toFixed(2)}/unit`;
                const volumeStr = point.volume >= 1000 ? `${(point.volume/1000).toFixed(2)}M units` : `${point.volume}K units`;
                document.getElementById('tooltip-volume').innerText = volumeStr;

                tooltip.classList.remove('opacity-0', 'scale-95');
                tooltip.classList.add('opacity-100', 'scale-100');

                // Positioning logic
                const tRect = tooltip.getBoundingClientRect();
                let left = pixelX - tRect.width / 2;
                let top = pixelY - tRect.height - 15;

                if (left < 10) left = 10;
                if (left + tRect.width > rect.width - 10) left = rect.width - tRect.width - 10;
                if (top < 10) top = pixelY + 15; // below the dot if it overflows top

                tooltip.style.left = `${left}px`;
                tooltip.style.top = `${top}px`;
            }
        }
    });

    document.addEventListener('mouseout', (e) => {
        if (e.target.classList.contains('line-hover-col')) {
            const guideLine = document.getElementById('vertical-guide-line');
            if (guideLine) {
                guideLine.classList.add('hidden');
            }

            const dots = document.querySelectorAll('.recharts-line-dot');
            dots.forEach(dot => {
                dot.setAttribute('r', '4.5');
                dot.setAttribute('fill', '#FFFFFF');
            });

            const tooltip = document.getElementById('chart-tooltip');
            if (tooltip) {
                tooltip.classList.remove('opacity-100', 'scale-100');
                tooltip.classList.add('opacity-0', 'scale-95');
            }
        }
    });

    // 8. Dynamic Click Interactions (Chips & Table Rows)
    if (chipsContainer) {
        chipsContainer.addEventListener('click', (e) => {
            const chip = e.target.closest('.market-chip');
            if (chip) {
                const marketKey = chip.getAttribute('data-market');
                setActiveMarket(marketKey);
            }
        });
    }

    tableRows.forEach(row => {
        row.addEventListener('click', () => {
            const marketKey = row.getAttribute('data-market');
            setActiveMarket(marketKey);
        });
    });

    // 9. View Toggle Actions (Single Country vs Compare Markets)
    if (toggleSingleBtn && toggleCompareBtn) {
        toggleSingleBtn.addEventListener('click', () => {
            if (activeChartMode === 'single') return;
            activeChartMode = 'single';

            toggleSingleBtn.className = "px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 bg-white shadow-sm text-foreground";
            toggleCompareBtn.className = "px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 text-muted-foreground hover:text-foreground";

            // Cross fade transition
            chartCompareContainer.classList.add('opacity-0');
            setTimeout(() => {
                chartCompareContainer.classList.add('hidden');
                chartSingleContainer.classList.remove('hidden');
                setTimeout(() => {
                    chartSingleContainer.classList.remove('opacity-0');
                    updateCharts(activeMarket);
                }, 50);
            }, 250);
        });

        toggleCompareBtn.addEventListener('click', () => {
            if (activeChartMode === 'compare') return;
            activeChartMode = 'compare';

            toggleCompareBtn.className = "px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 bg-white shadow-sm text-foreground";
            toggleSingleBtn.className = "px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 text-muted-foreground hover:text-foreground";

            // Cross fade transition
            chartSingleContainer.classList.add('opacity-0');
            setTimeout(() => {
                chartSingleContainer.classList.add('hidden');
                chartCompareContainer.classList.remove('hidden');
                setTimeout(() => {
                    chartCompareContainer.classList.remove('opacity-0');
                    updateCharts(activeMarket);
                }, 50);
            }, 250);
        });
    }

    // Initialize display with default active index (Egypt)
    setActiveMarket('egypt');
}
