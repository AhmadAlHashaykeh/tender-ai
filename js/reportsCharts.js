/**
 * Reports & Analytics – Interactive Charts + Flatpickr Date Range
 * Fully custom SVG charts with smooth entry animations.
 */

/* ============================================================
   UTILS
   ============================================================ */
function svgEl(tag, attrs = {}) {
    const el = document.createElementNS('http://www.w3.org/2000/svg', tag);
    Object.entries(attrs).forEach(([k, v]) => el.setAttribute(k, v));
    return el;
}

function buildTooltip(id) {
    const t = document.createElement('div');
    t.id = id;
    t.style.cssText = [
        'position:absolute',
        'pointer-events:none',
        'opacity:0',
        'background:#fff',
        'border:1px solid #e2e8f0',
        'border-radius:10px',
        'padding:10px 14px',
        'box-shadow:0 4px 20px rgba(0,0,0,.1)',
        'z-index:100',
        'transition:opacity .15s',
        'font-size:12px',
        'line-height:1.6',
        'min-width:140px',
        'white-space:nowrap'
    ].join(';');
    return t;
}

/* ============================================================
   1. DONUT CHART – Market Value by Country
   ============================================================ */
function initDonutChart() {
    const container = document.getElementById('reports-pie-chart-container');
    if (!container) return;

    const data = [
        { name: 'Saudi Arabia', value: 45.2, color: '#0D85E6' },
        { name: 'UAE',          value: 34.1, color: '#6366F1' },
        { name: 'Egypt',        value: 28.5, color: '#10B981' },
        { name: 'Iraq',         value: 20.3, color: '#F59E0B' },
        { name: 'Jordan',       value: 14.9, color: '#8B5CF6' }
    ];

    const total = data.reduce((s, d) => s + d.value, 0);
    const W = container.clientWidth || 340;
    const H = 260;
    const cx = W / 2, cy = H / 2;
    const R = Math.min(W, H) / 2 - 20;   // outer radius
    const r = R * 0.56;                    // inner radius (donut hole)

    const svg = svgEl('svg', { width: '100%', height: H, viewBox: `0 0 ${W} ${H}` });

    // Track cumulative angle
    let startAngle = -Math.PI / 2; // start at top

    const tooltip = buildTooltip('donut-tooltip');
    container.style.position = 'relative';
    container.appendChild(tooltip);

    const arcs = [];

    data.forEach((item, i) => {
        const slice = (item.value / total) * 2 * Math.PI;
        const endAngle = startAngle + slice;

        const x1 = cx + R * Math.cos(startAngle);
        const y1 = cy + R * Math.sin(startAngle);
        const x2 = cx + R * Math.cos(endAngle);
        const y2 = cy + R * Math.sin(endAngle);
        const ix1 = cx + r * Math.cos(endAngle);
        const iy1 = cy + r * Math.sin(endAngle);
        const ix2 = cx + r * Math.cos(startAngle);
        const iy2 = cy + r * Math.sin(startAngle);

        const large = slice > Math.PI ? 1 : 0;

        const d = [
            `M ${x1} ${y1}`,
            `A ${R} ${R} 0 ${large} 1 ${x2} ${y2}`,
            `L ${ix1} ${iy1}`,
            `A ${r} ${r} 0 ${large} 0 ${ix2} ${iy2}`,
            'Z'
        ].join(' ');

        const path = svgEl('path', {
            d,
            fill: item.color,
            stroke: '#fff',
            'stroke-width': '2',
            style: 'cursor:pointer;transform-origin:center;transform:scale(1);transition:transform .2s'
        });

        // Hover interactions
        path.addEventListener('mouseenter', (e) => {
            path.setAttribute('style', 'cursor:pointer;transform-origin:center;transform:scale(1.04);transition:transform .2s;filter:brightness(1.08)');
            tooltip.innerHTML = `<span style="font-weight:700;color:${item.color}">${item.name}</span><br>$${item.value.toFixed(1)}M &nbsp;·&nbsp; ${((item.value/total)*100).toFixed(1)}%`;
            tooltip.style.opacity = '1';
        });
        path.addEventListener('mousemove', (e) => {
            const rect = container.getBoundingClientRect();
            let tx = e.clientX - rect.left + 12;
            let ty = e.clientY - rect.top - 40;
            if (tx + 160 > W) tx = e.clientX - rect.left - 170;
            tooltip.style.left = tx + 'px';
            tooltip.style.top  = ty + 'px';
        });
        path.addEventListener('mouseleave', () => {
            path.setAttribute('style', 'cursor:pointer;transform-origin:center;transform:scale(1);transition:transform .2s');
            tooltip.style.opacity = '0';
        });

        arcs.push({ path, startAngle, slice });
        svg.appendChild(path);
        startAngle = endAngle;
    });

    container.insertBefore(svg, tooltip);

    // Animate: each arc sweeps in from its start angle using stroke-dasharray trick
    arcs.forEach(({ path, startAngle: sa, slice }, i) => {
        const len = path.getTotalLength();
        path.setAttribute('stroke-dasharray', len);
        path.setAttribute('stroke-dashoffset', len);
        path.style.transition = 'none';

        setTimeout(() => {
            path.style.transition = `stroke-dashoffset 0.7s cubic-bezier(0.22,1,0.36,1) ${i * 0.1}s, transform .2s`;
            path.setAttribute('stroke-dashoffset', '0');
        }, 80);
    });
}

/* ============================================================
   2. BAR CHART – Market Value by Year
   ============================================================ */
function initBarChart() {
    const container = document.getElementById('reports-bar-chart-container');
    if (!container) return;

    const data = [
        { year: '2022', value: 98  },
        { year: '2023', value: 112 },
        { year: '2024', value: 126 },
        { year: '2025', value: 139 },
        { year: '2026', value: 143 }
    ];

    const maxVal = 160;
    const W     = container.clientWidth || 400;
    const H     = 260;
    const PAD   = { top: 16, right: 16, bottom: 34, left: 52 };
    const cW    = W - PAD.left - PAD.right;
    const cH    = H - PAD.top  - PAD.bottom;
    const barW  = Math.min((cW / data.length) * 0.55, 52);
    const gap   = (cW - barW * data.length) / (data.length + 1);

    const svg = svgEl('svg', { width: '100%', height: H, viewBox: `0 0 ${W} ${H}` });

    // Grid lines + Y labels
    [0, 40, 80, 120, 160].forEach(tick => {
        const y = PAD.top + cH - (tick / maxVal) * cH;
        const line = svgEl('line', { x1: PAD.left, y1: y, x2: W - PAD.right, y2: y, stroke: '#e2e8f0', 'stroke-width': '1', 'stroke-dasharray': tick === 0 ? '' : '3,3' });
        svg.appendChild(line);
        const text = svgEl('text', { x: PAD.left - 8, y: y + 4, 'font-size': '11', fill: '#64748b', 'text-anchor': 'end' });
        text.textContent = `$${tick}M`;
        svg.appendChild(text);
    });

    // Y axis line
    const yAxis = svgEl('line', { x1: PAD.left, y1: PAD.top, x2: PAD.left, y2: PAD.top + cH, stroke: '#cbd5e1', 'stroke-width': '1.5' });
    svg.appendChild(yAxis);

    const tooltip = buildTooltip('bar-tooltip');
    container.style.position = 'relative';
    container.appendChild(tooltip);

    data.forEach((item, i) => {
        const x  = PAD.left + gap + i * (barW + gap);
        const bH = (item.value / maxVal) * cH;
        const y  = PAD.top + cH - bH;

        // Hover zone (invisible full-height rect)
        const hoverZone = svgEl('rect', { x, y: PAD.top, width: barW, height: cH, fill: 'transparent', style: 'cursor:pointer' });

        // Visible bar (starts at 0 height for animation)
        const bar = svgEl('rect', { x, y: PAD.top + cH, width: barW, height: 0, fill: '#0D85E6', rx: '5', ry: '5', style: 'transition:fill .2s' });

        // X label
        const label = svgEl('text', { x: x + barW / 2, y: H - 8, 'font-size': '11', fill: '#64748b', 'text-anchor': 'middle' });
        label.textContent = item.year;

        svg.appendChild(bar);
        svg.appendChild(label);
        svg.appendChild(hoverZone);

        // Animate bar upward
        setTimeout(() => {
            bar.animate([
                { height: '0px', y: PAD.top + cH },
                { height: bH + 'px', y }
            ], {
                duration: 700,
                delay: i * 90,
                easing: 'cubic-bezier(0.22,1,0.36,1)',
                fill: 'forwards'
            });
        }, 100);

        // Hover tooltip
        hoverZone.addEventListener('mouseenter', () => {
            bar.setAttribute('fill', '#2563eb');
            tooltip.innerHTML = `<span style="color:#64748b;font-size:11px">${item.year}</span><br><strong style="font-size:14px">$${item.value}M</strong>`;
            tooltip.style.opacity = '1';
        });
        hoverZone.addEventListener('mousemove', (e) => {
            const rect = container.getBoundingClientRect();
            let tx = e.clientX - rect.left + 12;
            let ty = e.clientY - rect.top - 55;
            if (tx + 120 > W) tx -= 130;
            if (ty < 0) ty = 5;
            tooltip.style.left = tx + 'px';
            tooltip.style.top  = ty + 'px';
        });
        hoverZone.addEventListener('mouseleave', () => {
            bar.setAttribute('fill', '#0D85E6');
            tooltip.style.opacity = '0';
        });
    });

    container.insertBefore(svg, tooltip);
}

/* ============================================================
   3. MULTI-LINE CHART – Company Wins by Year
   ============================================================ */
function initCompanyLineChart() {
    const container = document.getElementById('company-line-chart');
    if (!container) return;

    const years    = [2022, 2023, 2024, 2025, 2026];
    const datasets = [
        { name: 'PharmaCorp',      color: '#0D85E6', values: [28, 32, 38, 42, 45] },
        { name: 'MedSupply Inc',   color: '#6366F1', values: [22, 24, 28, 30, 31] },
        { name: 'BioMed Solutions',color: '#10B981', values: [19, 21, 22, 25, 27] }
    ];

    const W   = container.clientWidth || 380;
    const H   = 220;
    const PAD = { top: 16, right: 16, bottom: 28, left: 36 };
    const cW  = W - PAD.left - PAD.right;
    const cH  = H - PAD.top  - PAD.bottom;
    const maxVal = 50;

    const svg = svgEl('svg', { width: '100%', height: H, viewBox: `0 0 ${W} ${H}` });

    // Grid
    [0, 10, 20, 30, 40, 50].forEach(tick => {
        const y = PAD.top + cH - (tick / maxVal) * cH;
        svg.appendChild(svgEl('line', { x1: PAD.left, y1: y, x2: W - PAD.right, y2: y, stroke: '#e2e8f0', 'stroke-width': '1', 'stroke-dasharray': tick === 0 ? '' : '3,3' }));
        if (tick % 10 === 0) {
            const t = svgEl('text', { x: PAD.left - 6, y: y + 4, 'font-size': '10', fill: '#94a3b8', 'text-anchor': 'end' });
            t.textContent = tick;
            svg.appendChild(t);
        }
    });

    // X labels
    years.forEach((yr, i) => {
        const x = PAD.left + (i / (years.length - 1)) * cW;
        const t = svgEl('text', { x, y: H - 6, 'font-size': '10', fill: '#94a3b8', 'text-anchor': 'middle' });
        t.textContent = yr;
        svg.appendChild(t);
    });

    const tooltip = buildTooltip('line-tooltip');
    container.style.position = 'relative';
    container.appendChild(tooltip);

    datasets.forEach((ds, di) => {
        const points = ds.values.map((v, i) => ({
            x: PAD.left + (i / (years.length - 1)) * cW,
            y: PAD.top + cH - (v / maxVal) * cH,
            v
        }));

        // Build smooth polyline path
        let d = `M ${points[0].x} ${points[0].y}`;
        for (let i = 1; i < points.length; i++) {
            const cpX = (points[i - 1].x + points[i].x) / 2;
            d += ` C ${cpX} ${points[i-1].y} ${cpX} ${points[i].y} ${points[i].x} ${points[i].y}`;
        }

        const path = svgEl('path', { d, fill: 'none', stroke: ds.color, 'stroke-width': '2.5', 'stroke-linecap': 'round' });
        svg.appendChild(path);

        // Animate line draw
        const len = path.getTotalLength();
        path.setAttribute('stroke-dasharray', len);
        path.setAttribute('stroke-dashoffset', len);
        setTimeout(() => {
            path.animate(
                [{ strokeDashoffset: len }, { strokeDashoffset: 0 }],
                { duration: 1000, delay: di * 200, easing: 'cubic-bezier(0.22,1,0.36,1)', fill: 'forwards' }
            );
        }, 100);

        // Dots
        points.forEach((pt, i) => {
            const dot = svgEl('circle', { cx: pt.x, cy: pt.y, r: '4', fill: '#fff', stroke: ds.color, 'stroke-width': '2.5', style: 'cursor:pointer;opacity:0;transition:opacity .3s' });
            setTimeout(() => { dot.style.opacity = '1'; }, 100 + di * 200 + 800);

            dot.addEventListener('mouseenter', () => {
                tooltip.innerHTML = `<span style="color:${ds.color};font-weight:700">${ds.name}</span><br>${years[i]}: <strong>${pt.v} wins</strong>`;
                tooltip.style.opacity = '1';
            });
            dot.addEventListener('mousemove', (e) => {
                const rect = container.getBoundingClientRect();
                let tx = e.clientX - rect.left + 12;
                let ty = e.clientY - rect.top - 50;
                if (tx + 160 > W) tx -= 170;
                if (ty < 0) ty = 5;
                tooltip.style.left = tx + 'px';
                tooltip.style.top  = ty + 'px';
            });
            dot.addEventListener('mouseleave', () => { tooltip.style.opacity = '0'; });
            svg.appendChild(dot);
        });
    });

    container.insertBefore(svg, tooltip);
}

/* ============================================================
   4. HORIZONTAL BAR CHART – Geographic Distribution
   ============================================================ */
function initGeoBarChart() {
    const container = document.getElementById('geo-bar-chart');
    if (!container) return;

    const data = [
        { name: 'Saudi Arabia', wins: 157, color: '#0D85E6' },
        { name: 'UAE',          wins: 123, color: '#6366F1' },
        { name: 'Egypt',        wins: 105, color: '#10B981' },
        { name: 'Iraq',         wins:  89, color: '#F59E0B' },
        { name: 'Jordan',       wins:  76, color: '#8B5CF6' }
    ];

    const maxVal = 180;
    const W   = container.clientWidth || 380;
    const H   = 220;
    const PAD = { top: 10, right: 16, bottom: 10, left: 90 };
    const cW  = W - PAD.left - PAD.right;
    const cH  = H - PAD.top  - PAD.bottom;
    const barH = Math.floor(cH / data.length);
    const barPad = 8;

    const svg = svgEl('svg', { width: '100%', height: H, viewBox: `0 0 ${W} ${H}` });

    const tooltip = buildTooltip('geo-tooltip');
    container.style.position = 'relative';
    container.appendChild(tooltip);

    data.forEach((item, i) => {
        const y     = PAD.top + i * barH + barPad / 2;
        const bH    = barH - barPad;
        const bW    = (item.wins / maxVal) * cW;
        const cy    = y + bH / 2;

        // Y label
        const label = svgEl('text', { x: PAD.left - 8, y: cy + 4, 'font-size': '12', fill: '#334155', 'text-anchor': 'end' });
        label.textContent = item.name;
        svg.appendChild(label);

        // Background track
        const track = svgEl('rect', { x: PAD.left, y, width: cW, height: bH, rx: 4, fill: '#f1f5f9' });
        svg.appendChild(track);

        // Colored bar (width=0 for animation)
        const bar = svgEl('rect', { x: PAD.left, y, width: 0, height: bH, rx: 4, fill: item.color, style: 'cursor:pointer;transition:filter .2s' });
        svg.appendChild(bar);

        // Value label (appears after animation)
        const valLabel = svgEl('text', { x: PAD.left + 6, y: cy + 4.5, 'font-size': '11', fill: '#fff', 'font-weight': '600', style: 'opacity:0;transition:opacity .3s' });
        valLabel.textContent = item.wins + ' wins';
        svg.appendChild(valLabel);

        // Animate bar width
        setTimeout(() => {
            bar.animate(
                [{ width: '0px' }, { width: bW + 'px' }],
                { duration: 700, delay: i * 100, easing: 'cubic-bezier(0.22,1,0.36,1)', fill: 'forwards' }
            );
            setTimeout(() => { valLabel.style.opacity = '1'; }, 700 + i * 100);
        }, 150);

        // Hover
        bar.addEventListener('mouseenter', () => {
            bar.style.filter = 'brightness(1.12)';
            tooltip.innerHTML = `<span style="color:${item.color};font-weight:700">${item.name}</span><br>Wins: <strong>${item.wins}</strong>`;
            tooltip.style.opacity = '1';
        });
        bar.addEventListener('mousemove', (e) => {
            const rect = container.getBoundingClientRect();
            let tx = e.clientX - rect.left + 12;
            let ty = e.clientY - rect.top - 50;
            if (tx + 160 > W) tx -= 170;
            if (ty < 0) ty = 5;
            tooltip.style.left = tx + 'px';
            tooltip.style.top  = ty + 'px';
        });
        bar.addEventListener('mouseleave', () => {
            bar.style.filter = '';
            tooltip.style.opacity = '0';
        });
    });

    container.insertBefore(svg, tooltip);
}

/* ============================================================
   TAB SWITCHING
   ============================================================ */
const TAB_META = {
    market:      { title: 'Market Intelligence',   sub: 'Comprehensive market overview and tender analytics' },
    company:     { title: 'Company Performance',   sub: 'Strategic company intelligence and competitive analysis' },
    opportunity: { title: 'Opportunity Analysis',  sub: 'Identify and track high-value opportunities' },
    history:     { title: 'Prediction History',    sub: 'Past AI predictions and accuracy metrics' }
};

function switchTab(tabKey) {
    // Update nav buttons
    document.querySelectorAll('.report-nav-btn').forEach(btn => {
        const isActive = btn.dataset.tab === tabKey;
        btn.className = isActive
            ? 'report-nav-btn active-report-tab w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm transition-colors'
            : 'report-nav-btn w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm transition-colors text-muted-foreground hover:bg-muted hover:text-foreground';
    });

    // Show/hide tab panes
    document.querySelectorAll('.report-tab').forEach(pane => {
        pane.classList.toggle('hidden', pane.id !== 'tab-' + tabKey);
    });

    // Update header text
    const meta = TAB_META[tabKey];
    if (meta) {
        const titleEl = document.getElementById('reports-title');
        const subEl   = document.getElementById('reports-subtitle');
        if (titleEl) titleEl.textContent = meta.title;
        if (subEl)   subEl.textContent   = meta.sub;
    }

    // Render charts only when their tab is visible
    if (tabKey === 'market') {
        renderMarketCharts();
    } else if (tabKey === 'company') {
        renderCompanyCharts();
    }
}

/* ============================================================
   CHART RENDERING GUARDS (avoid double-render)
   ============================================================ */
let marketChartsRendered  = false;
let companyChartsRendered = false;

function renderMarketCharts() {
    if (marketChartsRendered) return;
    marketChartsRendered = true;
    initDonutChart();
    initBarChart();
}

function renderCompanyCharts() {
    if (companyChartsRendered) return;
    companyChartsRendered = true;
    initCompanyLineChart();
    initGeoBarChart();
}

/* ============================================================
   FLATPICKR DATE RANGE
   ============================================================ */
function initDateRange() {
    const input = document.getElementById('date-range-input');
    const btn   = document.getElementById('date-range-btn');
    const label = document.getElementById('date-range-label');
    if (!input || !btn || typeof flatpickr === 'undefined') return;

    const fp = flatpickr(input, {
        mode: 'range',
        dateFormat: 'M j, Y',
        onClose(selectedDates, dateStr) {
            if (selectedDates.length === 2) {
                label.textContent = dateStr;
                btn.classList.add('text-primary', 'border-primary/40', 'bg-primary/5');
            } else if (selectedDates.length === 0) {
                label.textContent = 'Date Range';
                btn.classList.remove('text-primary', 'border-primary/40', 'bg-primary/5');
            }
        }
    });

    btn.addEventListener('click', () => fp.open());
}

/* ============================================================
   ACTIVE TAB STYLE INJECTION
   ============================================================ */
function injectNavStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .active-report-tab {
            background: rgb(13 133 230 / .1);
            color: #0D85E6;
            font-weight: 600;
        }
        .report-nav-btn.text-muted-foreground { color: #64748b; }
        .report-nav-btn.text-muted-foreground:hover { background: #f1f5f9; color: #0f172a; }
    `;
    document.head.appendChild(style);
}

/* ============================================================
   INIT
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    injectNavStyles();
    initDateRange();

    const navButtons = document.querySelectorAll('.report-nav-btn');

    // Legacy/tabbed reports page behavior
    if (navButtons.length) {
        navButtons.forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });

        // Default tab is Market on the tabbed page
        renderMarketCharts();
        return;
    }

    // Standalone report pages: render whatever chart containers exist
    renderMarketCharts();
    renderCompanyCharts();
});
