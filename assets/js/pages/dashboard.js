 // Count up animation
    const counters = document.querySelectorAll('.count-up');
    const speed = 200;

    counters.forEach(counter => {
        const updateCount = () => {
            const target = +counter.getAttribute('data-target');
            const count = +counter.innerText;
            const inc = target / speed;

            if (count < target) {
                counter.innerText = Math.ceil(count + inc);
                setTimeout(updateCount, 1);
            } else {
                counter.innerText = target.toLocaleString();
            }
        };

        updateCount();
    });

    // Chart Tooltips Logic
    const setupChartTooltip = (hitAreas, tooltip, cursor, type) => {
        if (!tooltip) return;

        hitAreas.forEach(el => {
            el.addEventListener('mouseenter', () => {
                const label = el.getAttribute('data-label');
                const value = el.getAttribute('data-value');

                tooltip.innerHTML = `
                    <span class="tooltip-label">${label}</span>
                    <span class="tooltip-value">
                        ${type === 'line' ? 'Price ($) : ' : 'Tenders : '}
                        ${value}
                    </span>
                `;

                tooltip.style.opacity = '1';

                if (cursor) {
                    cursor.style.display = 'block';

                    if (type === 'line') {
                        const cx = el.getAttribute('data-cx');
                        cursor.setAttribute('x1', cx);
                        cursor.setAttribute('x2', cx);
                    } else {
                        const x = el.getAttribute('data-x');
                        const w = el.getAttribute('data-w');

                        cursor.setAttribute('x', x);
                        cursor.setAttribute('width', w);
                    }
                }
            });

            el.addEventListener('mousemove', e => {
                const wrapper = el.closest('.recharts-wrapper');
                if (!wrapper) return;

                const rect = wrapper.getBoundingClientRect();

                let x = e.clientX - rect.left + 15;
                let y = e.clientY - rect.top - 60;

                // Keep tooltip within bounds
                if (x + 150 > rect.width) x -= 160;
                if (y < 0) y += 80;

                tooltip.style.left = `${x}px`;
                tooltip.style.top = `${y}px`;
            });

            el.addEventListener('mouseleave', () => {
                tooltip.style.opacity = '0';

                if (cursor) {
                    cursor.style.display = 'none';
                }
            });
        });
    };

/* Extracted from dashboard.html during frontend structure refactor. */
document.addEventListener('DOMContentLoaded', () => {
    // Lucide Icons initialization
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Core state
    let activeCountry = 'KSA';
    let activeDrugIndex = 1; // Default is Amoxicillin 250mg (index 1)
    let upcomingTenderIndex = 0;
    let drugAutoplayTimer;
    let upcomingAutoplayTimer;
    
    // Database
    const drugList = [
        "Paracetamol 500mg",
        "Amoxicillin 250mg",
        "Ibuprofen 400mg",
        "Omeprazole 20mg",
        "Metformin 500mg"
    ];
    
    const chartData = {
        KSA: {
            "Paracetamol 500mg": [120, 130, 145, 160, 180, 210],
            "Amoxicillin 250mg": [265, 275, 290, 305, 315, 330],
            "Ibuprofen 400mg": [80, 95, 110, 130, 155, 180],
            "Omeprazole 20mg": [310, 325, 340, 330, 320, 345],
            "Metformin 500mg": [150, 170, 190, 210, 240, 270]
        },
        UAE: {
            "Paracetamol 500mg": [140, 155, 170, 190, 210, 240],
            "Amoxicillin 250mg": [290, 300, 315, 330, 340, 360],
            "Ibuprofen 400mg": [100, 115, 130, 150, 180, 210],
            "Omeprazole 20mg": [340, 350, 365, 355, 340, 370],
            "Metformin 500mg": [180, 200, 220, 240, 270, 300]
        },
        EGY: {
            "Paracetamol 500mg": [40, 48, 55, 65, 78, 92],
            "Amoxicillin 250mg": [110, 125, 140, 160, 185, 210],
            "Ibuprofen 400mg": [30, 38, 45, 55, 68, 82],
            "Omeprazole 20mg": [130, 145, 160, 180, 205, 235],
            "Metformin 500mg": [60, 72, 85, 100, 120, 145]
        },
        IRQ: {
            "Paracetamol 500mg": [70, 78, 90, 105, 125, 150],
            "Amoxicillin 250mg": [160, 175, 195, 220, 250, 285],
            "Ibuprofen 400mg": [55, 65, 78, 95, 115, 140],
            "Omeprazole 20mg": [200, 215, 235, 260, 290, 325],
            "Metformin 500mg": [95, 110, 130, 155, 185, 220]
        },
        JOR: {
            "Paracetamol 500mg": [80, 90, 105, 120, 140, 165],
            "Amoxicillin 250mg": [180, 195, 215, 240, 270, 305],
            "Ibuprofen 400mg": [65, 75, 90, 110, 130, 155],
            "Omeprazole 20mg": [220, 235, 255, 280, 310, 345],
            "Metformin 500mg": [110, 125, 145, 170, 200, 235]
        }
    };
    
    const countryStats = {
        KSA: { tenders: 1247, drugs: 856, companies: 142, predictions: 1843 },
        UAE: { tenders: 932, drugs: 620, companies: 98, predictions: 1245 },
        EGY: { tenders: 1480, drugs: 710, companies: 115, predictions: 1692 },
        IRQ: { tenders: 560, drugs: 430, companies: 64, predictions: 820 },
        JOR: { tenders: 410, drugs: 380, companies: 52, predictions: 610 }
    };
    
    const barVolumeData = { KSA: 450, UAE: 320, EGY: 280, IRQ: 200, JOR: 180 };
    const countryNames = { KSA: "Saudi Arabia", UAE: "UAE", EGY: "Egypt", IRQ: "Iraq", JOR: "Jordan" };
    
    const upcomingTenders = [
        { title: "Iraq MOH Q2 Tender", country: "Iraq", badge: "61d left", products: "4 products", countryCode: "IRQ" },
        { title: "Egypt MOH Pharmaceutical Bid", country: "Egypt", badge: "44d left", products: "2 products", countryCode: "EGY" },
        { title: "Saudi Arabia NUPCO Tender", country: "Saudi Arabia", badge: "12d left", products: "15 products", countryCode: "KSA" },
        { title: "UAE DHA Hospital Supply", country: "UAE", badge: "18d left", products: "6 products", countryCode: "UAE" },
        { title: "Jordan MOH Vaccine Tender", country: "Jordan", badge: "28d left", products: "8 products", countryCode: "JOR" }
    ];
    
    const recentTendersData = {
        KSA: { drug: "Paracetamol 500mg", country: "Saudi Arabia", company: "PharmaCo Ltd", price: "$442" },
        UAE: { drug: "Amoxicillin 250mg", country: "UAE", company: "GulfPharma", price: "$512" },
        EGY: { drug: "Ibuprofen 400mg", country: "Egypt", company: "EGYPharma", price: "$185" },
        IRQ: { drug: "Omeprazole 20mg", country: "Iraq", company: "Baghdad Med", price: "$320" },
        JOR: { drug: "Metformin 500mg", country: "Jordan", company: "Amman Pharma", price: "$275" }
    };
    
    const companyHistoryData = {
        KSA: { drug: "Paracetamol 500mg", country: "Saudi Arabia", status: "Won", date: "2026-04-20" },
        UAE: { drug: "Amoxicillin 250mg", country: "UAE", status: "Won", date: "2026-05-10" },
        EGY: { drug: "Ibuprofen 400mg", country: "Egypt", status: "Won", date: "2026-03-15" },
        IRQ: { drug: "Omeprazole 20mg", country: "Iraq", status: "Won", date: "2026-05-01" },
        JOR: { drug: "Metformin 500mg", country: "Jordan", status: "Won", date: "2026-04-18" }
    };
    
    // Animation Functions
    function animateCountUp(elementId, targetVal) {
        const el = document.getElementById(elementId);
        if (!el) return;
        const current = parseInt(el.textContent.replace(/,/g, '')) || 0;
        const duration = 800; // ms
        const startTime = performance.now();
        
        function update(now) {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const ease = progress * (2 - progress); // easeOutQuad
            const val = Math.ceil(current + (targetVal - current) * ease);
            el.textContent = val.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                el.textContent = targetVal.toLocaleString();
            }
        }
        requestAnimationFrame(update);
    }
    
    // Line Chart Renderer
    function drawLineChart() {
        const svg = document.getElementById('price-trends-svg');
        const path = document.getElementById('trend-line');
        const areaPath = document.getElementById('line-area-path');
        const pointsGroup = document.getElementById('line-points');
        if (!svg || !path) return;
        
        const activeDrug = drugList[activeDrugIndex];
        const prices = chartData[activeCountry][activeDrug];
        
        const xCoords = [52, 153.6, 255.2, 356.8, 458.4, 560];
        const points = [];
        
        // Calculate points
        for (let i = 0; i < prices.length; i++) {
            const price = prices[i];
            const x = xCoords[i];
            const y = 170 - (price / 380) * 165;
            points.push({ x, y, val: price });
        }
        
        // Build line path
        let d = `M ${points[0].x},${points[0].y}`;
        for (let i = 1; i < points.length; i++) {
            d += ` L ${points[i].x},${points[i].y}`;
        }
        
        // Build area path
        const dArea = `${d} L ${points[points.length - 1].x},170 L ${points[0].x},170 Z`;
        
        // Render and Animate drawing
        path.setAttribute('d', d);
        const pathLength = path.getTotalLength();
        
        path.style.transition = 'none';
        path.style.strokeDasharray = pathLength;
        path.style.strokeDashoffset = pathLength;
        
        // force reflow
        path.getBoundingClientRect();
        
        path.style.transition = 'stroke-dashoffset 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
        path.style.strokeDashoffset = '0';
        
        // Render Area
        areaPath.setAttribute('d', dArea);
        areaPath.style.opacity = '0';
        setTimeout(() => {
            areaPath.style.opacity = '1';
        }, 100);
        
        // Render Dots
        pointsGroup.innerHTML = '';
        const years = [2020, 2021, 2022, 2023, 2024, 2025];
        points.forEach((pt, index) => {
            const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle.setAttribute('cx', pt.x);
            circle.setAttribute('cy', pt.y);
            circle.setAttribute('r', '0');
            circle.setAttribute('fill', '#ffffff');
            circle.setAttribute('stroke', '#0D85E6');
            circle.setAttribute('stroke-width', '2');
            circle.setAttribute('class', 'price-trend-point cursor-pointer');
            circle.style.transition = 'r 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), fill 0.2s, stroke 0.2s';
            circle.style.transitionDelay = `${index * 0.05}s`;
            pointsGroup.appendChild(circle);
            
            setTimeout(() => {
                circle.setAttribute('r', '4.5');
            }, 50);
        });
    }
    
    // Bar Chart Renderer
    function drawBarChart() {
        const svg = document.getElementById('volume-bar-svg');
        if (!svg) return;
        
        const bars = svg.querySelectorAll('.volume-bar');
        bars.forEach(bar => {
            const country = bar.getAttribute('data-country');
            const val = barVolumeData[country];
            const maxVal = 600;
            const fullChartWidth = 228; // width corresponding to 600 value
            const targetWidth = (val / maxVal) * fullChartWidth;
            
            // Set target width
            bar.setAttribute('width', targetWidth);
            
            // Highlight matching country
            if (country === activeCountry) {
                bar.style.opacity = '1';
                bar.setAttribute('fill', '#0D85E6');
            } else {
                bar.style.opacity = '0.7';
                bar.setAttribute('fill', '#0D85E6');
            }
        });
    }
    
    // UI Update logic (Reactive)
    function changeCountryState(newCountry) {
        if (newCountry === activeCountry) return;
        activeCountry = newCountry;
        
        // 1. Update Country buttons
        const btns = document.querySelectorAll('.country-btn');
        btns.forEach(btn => {
            const code = btn.getAttribute('data-country');
            if (code === activeCountry) {
                btn.className = "country-btn px-3 py-1 rounded-full text-xs font-medium bg-primary text-white shadow-sm shadow-primary/20";
            } else {
                btn.className = "country-btn px-3 py-1 rounded-full text-xs font-medium bg-muted/60 text-muted-foreground hover:bg-primary/10 hover:text-primary";
            }
        });
        
        // 2. Count-up statistics
        const stats = countryStats[activeCountry];
        animateCountUp('stat-total-tenders', stats.tenders);
        animateCountUp('stat-total-drugs', stats.drugs);
        animateCountUp('stat-total-companies', stats.companies);
        animateCountUp('stat-total-predictions', stats.predictions);
        
        // 3. Update Recent Tenders card with animation
        const recentCardContent = document.getElementById('recent-tender-content');
        if (recentCardContent) {
            recentCardContent.style.opacity = '0';
            recentCardContent.style.transform = 'translateY(5px)';
            setTimeout(() => {
                const rt = recentTendersData[activeCountry];
                document.getElementById('recent-tender-drug').textContent = rt.drug;
                document.getElementById('recent-tender-country').textContent = rt.country;
                document.getElementById('recent-tender-company').textContent = rt.company;
                document.getElementById('recent-tender-price').textContent = rt.price;
                
                recentCardContent.style.opacity = '1';
                recentCardContent.style.transform = 'translateY(0)';
            }, 250);
        }
        
        // 4. Update Company History card with animation
        const historyCardContent = document.getElementById('company-history-content');
        if (historyCardContent) {
            historyCardContent.style.opacity = '0';
            historyCardContent.style.transform = 'translateY(5px)';
            setTimeout(() => {
                const ch = companyHistoryData[activeCountry];
                document.getElementById('history-tender-drug').textContent = ch.drug;
                document.getElementById('history-tender-country').textContent = ch.country;
                document.getElementById('history-tender-date').textContent = ch.date;
                
                const statusSpan = document.getElementById('history-tender-status');
                if (statusSpan) {
                    statusSpan.querySelector('span').textContent = ch.status;
                }
                
                historyCardContent.style.opacity = '1';
                historyCardContent.style.transform = 'translateY(0)';
            }, 250);
        }
        
        // 5. Instantly change upcoming tenders to match active country if available
        const matchingTenderIndex = upcomingTenders.findIndex(t => t.countryCode === activeCountry);
        if (matchingTenderIndex !== -1) {
            upcomingTenderIndex = matchingTenderIndex;
            updateUpcomingTendersUI();
        }
        
        // 6. Redraw line and bar charts
        drawLineChart();
        drawBarChart();
    }
    
    function changeDrugState(newIndex) {
        if (newIndex === activeDrugIndex) return;
        activeDrugIndex = newIndex;
        
        // Update bullets UI
        const bullets = document.querySelectorAll('.drug-bullet');
        bullets.forEach((b, idx) => {
            if (idx === activeDrugIndex) {
                b.className = "drug-bullet w-5 h-1.5 rounded-full bg-primary";
            } else {
                b.className = "drug-bullet w-1.5 h-1.5 rounded-full bg-border hover:bg-primary/40";
            }
        });
        
        // Update drug name label
        const nameLabel = document.getElementById('active-drug-name');
        if (nameLabel) {
            nameLabel.style.opacity = '0';
            setTimeout(() => {
                nameLabel.textContent = drugList[activeDrugIndex];
                nameLabel.style.opacity = '1';
            }, 150);
        }
        
        // Redraw Line Chart
        drawLineChart();
    }
    
    function updateUpcomingTendersUI() {
        const wrapper = document.getElementById('upcoming-tender-content');
        if (!wrapper) return;
        
        wrapper.style.opacity = '0';
        wrapper.style.transform = 'translateY(5px)';
        
        setTimeout(() => {
            const ut = upcomingTenders[upcomingTenderIndex];
            document.getElementById('upcoming-tender-title').textContent = ut.title;
            document.getElementById('upcoming-tender-country').textContent = ut.country;
            document.getElementById('upcoming-tender-badge').textContent = ut.badge;
            document.getElementById('upcoming-tender-products').textContent = ut.products;
            
            wrapper.style.opacity = '1';
            wrapper.style.transform = 'translateY(0)';
        }, 300);
    }
    
    // Autoplay loops
    function startDrugAutoplay() {
        if (drugAutoplayTimer) clearInterval(drugAutoplayTimer);
        drugAutoplayTimer = setInterval(() => {
            const nextIndex = (activeDrugIndex + 1) % drugList.length;
            changeDrugState(nextIndex);
        }, 4000);
    }
    
    function stopDrugAutoplay() {
        if (drugAutoplayTimer) clearInterval(drugAutoplayTimer);
    }
    
    function startUpcomingAutoplay() {
        if (upcomingAutoplayTimer) clearInterval(upcomingAutoplayTimer);
        upcomingAutoplayTimer = setInterval(() => {
            upcomingTenderIndex = (upcomingTenderIndex + 1) % upcomingTenders.length;
            updateUpcomingTendersUI();
        }, 3500);
    }
    
    function stopUpcomingAutoplay() {
        if (upcomingAutoplayTimer) clearInterval(upcomingAutoplayTimer);
    }
    
    // Bind click events on Country Buttons
    const btns = document.querySelectorAll('.country-btn');
    btns.forEach(btn => {
        btn.addEventListener('click', () => {
            const code = btn.getAttribute('data-country');
            changeCountryState(code);
        });
    });
    
    // Bind click events on Drug Bullets
    const bullets = document.querySelectorAll('.drug-bullet');
    bullets.forEach(bullet => {
        bullet.addEventListener('click', () => {
            const idx = parseInt(bullet.getAttribute('data-index'));
            changeDrugState(idx);
        });
    });
    
    // Bind hovering pauses on Price Trends card
    const priceTrendsCard = document.getElementById('price-trends-card');
    if (priceTrendsCard) {
        priceTrendsCard.addEventListener('mouseenter', stopDrugAutoplay);
        priceTrendsCard.addEventListener('mouseleave', startDrugAutoplay);
    }
    
    // Bind hovering pauses on Upcoming Tenders card
    const upcomingCard = document.getElementById('card-upcoming-tenders');
    if (upcomingCard) {
        upcomingCard.addEventListener('mouseenter', stopUpcomingAutoplay);
        upcomingCard.addEventListener('mouseleave', startUpcomingAutoplay);
    }
    
    // Line Chart Tooltip and Cursor interactions
    const lineSvg = document.getElementById('price-trends-svg');
    const lineTooltip = document.getElementById('line-tooltip');
    const lineCursor = document.getElementById('hover-cursor-line');
    
    if (lineSvg && lineTooltip && lineCursor) {
        lineSvg.addEventListener('mousemove', (e) => {
            const rect = lineSvg.getBoundingClientRect();
            // Map coordinates relative to viewBox width = 600
            const x = (e.clientX - rect.left) * (600 / rect.width);
            
            const xCoords = [52, 153.6, 255.2, 356.8, 458.4, 560];
            const years = [2020, 2021, 2022, 2023, 2024, 2025];
            
            let closestIndex = 0;
            let minDiff = Infinity;
            
            for (let i = 0; i < xCoords.length; i++) {
                const diff = Math.abs(xCoords[i] - x);
                if (diff < minDiff) {
                    minDiff = diff;
                    closestIndex = i;
                }
            }
            
            const targetX = xCoords[closestIndex];
            const targetYear = years[closestIndex];
            const activeDrugName = drugList[activeDrugIndex];
            const prices = chartData[activeCountry][activeDrugName];
            const priceVal = prices[closestIndex];
            
            // Set vertical hover cursor coordinates
            lineCursor.setAttribute('x1', targetX);
            lineCursor.setAttribute('x2', targetX);
            lineCursor.style.display = 'block';
            
            // Highlight dot
            const circles = lineSvg.querySelectorAll('.price-trend-point');
            circles.forEach((c, idx) => {
                if (idx === closestIndex) {
                    c.setAttribute('r', '7.5');
                    c.setAttribute('fill', '#0D85E6');
                    c.setAttribute('stroke', '#ffffff');
                    c.setAttribute('stroke-width', '2.5');
                } else {
                    c.setAttribute('r', '4.5');
                    c.setAttribute('fill', '#ffffff');
                    c.setAttribute('stroke', '#0D85E6');
                    c.setAttribute('stroke-width', '2');
                }
            });
            
            // Tooltip rendering
            lineTooltip.innerHTML = `
                <div class="font-bold text-slate-800">${targetYear}</div>
                <div class="text-[10px] text-slate-500 font-medium">${activeDrugName}</div>
                <div class="text-sm font-extrabold text-primary mt-0.5">$${priceVal}</div>
            `;
            lineTooltip.style.opacity = '1';
            
            // Tooltip coordinates relative to line chart wrapper
            const parentRect = lineSvg.parentElement.getBoundingClientRect();
            const tooltipX = (targetX / 600) * parentRect.width;
            const targetY = 170 - (priceVal / 380) * 165;
            const tooltipY = (targetY / 200) * parentRect.height;
            
            lineTooltip.style.left = `${tooltipX + 15}px`;
            lineTooltip.style.top = `${tooltipY - 45}px`;
        });
        
        lineSvg.addEventListener('mouseleave', () => {
            lineCursor.style.display = 'none';
            lineTooltip.style.opacity = '0';
            
            // Reset all circles
            const circles = lineSvg.querySelectorAll('.price-trend-point');
            circles.forEach(c => {
                c.setAttribute('r', '4.5');
                c.setAttribute('fill', '#ffffff');
                c.setAttribute('stroke', '#0D85E6');
                c.setAttribute('stroke-width', '2');
            });
        });
    }
    
    // Bar Chart mouse hover tooltip & country select triggers
    const bars = document.querySelectorAll('.volume-bar');
    const barLabels = document.querySelectorAll('.bar-y-label');
    const barTooltip = document.getElementById('bar-tooltip');
    
    function setupBarInteractions(el) {
        el.addEventListener('mousemove', (e) => {
            const country = el.getAttribute('data-country');
            const val = barVolumeData[country];
            const countryFull = countryNames[country];
            
            barTooltip.innerHTML = `
                <div class="font-bold text-slate-800">${countryFull}</div>
                <div class="text-[10px] text-slate-500 font-medium">Tender Volume: <span class="font-bold text-primary">${val}</span></div>
            `;
            barTooltip.style.opacity = '1';
            
            const parentRect = el.parentElement.parentElement.getBoundingClientRect();
            const x = e.clientX - parentRect.left + 15;
            const y = e.clientY - parentRect.top - 40;
            
            barTooltip.style.left = `${x}px`;
            barTooltip.style.top = `${y}px`;
        });
        
        el.addEventListener('mouseleave', () => {
            barTooltip.style.opacity = '0';
        });
        
        el.addEventListener('click', () => {
            const country = el.getAttribute('data-country');
            changeCountryState(country);
        });
    }
    
    bars.forEach(setupBarInteractions);
    barLabels.forEach(setupBarInteractions);
    
    // Initialize Dashboard rendering
    drawLineChart();
    drawBarChart();
    startDrugAutoplay();
    startUpcomingAutoplay();
});
