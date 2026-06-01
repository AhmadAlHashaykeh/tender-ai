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
