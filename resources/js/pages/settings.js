/**
 * Settings page – vertical nav tabs + switch-to-checkbox sync + user search.
 * Uses native <details> elements for collapsible advanced sections.
 */

// ── Tab navigation ──────────────────────────────────────────────────────────
function activateTab(tabId) {
    document.querySelectorAll('.sc-nav-item').forEach((btn) => {
        const active = btn.getAttribute('data-tab') === tabId;
        btn.classList.toggle('sc-nav-item--active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    document.querySelectorAll('.sc-panel').forEach((panel) => {
        const active = panel.id === `sc-tab-${tabId}`;
        panel.classList.toggle('sc-panel--active', active);
    });
}

document.querySelectorAll('.sc-nav-item').forEach((btn) => {
    btn.addEventListener('click', () => activateTab(btn.getAttribute('data-tab')));
});

// ── Switch → hidden checkbox sync ───────────────────────────────────────────
document.querySelectorAll('.sc-switch').forEach((sw) => {
    sw.addEventListener('click', () => {
        const checkboxId = sw.getAttribute('data-checkbox');
        const checkbox = checkboxId ? document.getElementById(checkboxId) : null;
        const next = sw.getAttribute('aria-checked') !== 'true';
        sw.setAttribute('aria-checked', next ? 'true' : 'false');
        if (checkbox) checkbox.checked = next;
    });
});

// ── User table search ────────────────────────────────────────────────────────
const userSearch = document.getElementById('userSearch');
if (userSearch) {
    userSearch.addEventListener('input', () => {
        const q = userSearch.value.toLowerCase().trim();
        document.querySelectorAll('.sc-user-row').forEach((row) => {
            const name = row.getAttribute('data-name') || '';
            const email = row.getAttribute('data-email') || '';
            row.classList.toggle('sc-user-row--hidden', q !== '' && !name.includes(q) && !email.includes(q));
        });
    });
}

// ── Lucide icons ─────────────────────────────────────────────────────────────
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
