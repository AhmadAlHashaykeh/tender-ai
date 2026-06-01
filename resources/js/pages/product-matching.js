/**
 * Product Matching review queue — bulk selection, keyboard workflow, manual edit.
 */
document.addEventListener('DOMContentLoaded', () => {
    const config = window.productMatchingConfig;
    if (!config) return;

    lucide.createIcons();

    const selectedIds = new Set();
    const cards = () => Array.from(document.querySelectorAll('[data-review-card]'));
    const countEl = document.querySelector('[data-selected-count]');
    const applyBulkBtn = document.querySelector('[data-apply-bulk]');
    const bulkSelect = document.querySelector('[data-bulk-action-select]');
    let focusedIndex = 0;
    let lastClickedIndex = null;

    function updateSelectionUI() {
        if (countEl) countEl.textContent = String(selectedIds.size);
        if (applyBulkBtn) applyBulkBtn.disabled = selectedIds.size === 0 || !bulkSelect?.value;

        document.querySelectorAll('[data-row-select]').forEach((cb) => {
            cb.checked = selectedIds.has(Number(cb.value));
        });

        const visibleAll = document.querySelectorAll('[data-row-select]');
        const selectAllVisible = document.getElementById('select-all-visible');
        if (selectAllVisible && visibleAll.length) {
            selectAllVisible.checked = Array.from(visibleAll).every((cb) => selectedIds.has(Number(cb.value)));
        }
    }

    function toggleSelection(id, checked) {
        if (checked) selectedIds.add(id);
        else selectedIds.delete(id);
        updateSelectionUI();
    }

    function selectVisible() {
        document.querySelectorAll('[data-row-select]').forEach((cb) => selectedIds.add(Number(cb.value)));
        updateSelectionUI();
    }

    function clearSelection() {
        selectedIds.clear();
        updateSelectionUI();
    }

    function selectByConfidence(min) {
        cards().forEach((card) => {
            const confidence = parseFloat(card.dataset.confidence || '0');
            if (confidence >= min) {
                selectedIds.add(Number(card.dataset.rowId));
            }
        });
        updateSelectionUI();
    }

    document.querySelector('[data-select-all-visible]')?.addEventListener('change', (e) => {
        if (e.target.checked) selectVisible();
        else clearSelection();
    });

    document.querySelector('[data-select-all]')?.addEventListener('click', selectVisible);
    document.querySelector('[data-clear-selection]')?.addEventListener('click', clearSelection);

    document.querySelectorAll('[data-select-confidence]').forEach((btn) => {
        btn.addEventListener('click', () => selectByConfidence(Number(btn.dataset.selectConfidence)));
    });

    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-row-select]')) {
            toggleSelection(Number(e.target.value), e.target.checked);
        }
    });

    document.addEventListener('click', (e) => {
        const card = e.target.closest('[data-review-card]');
        if (!card || e.target.closest('button, a, input, select, textarea, dialog')) return;

        const cardList = cards();
        const index = cardList.indexOf(card);

        if (e.shiftKey && lastClickedIndex !== null) {
            const start = Math.min(lastClickedIndex, index);
            const end = Math.max(lastClickedIndex, index);
            for (let i = start; i <= end; i++) {
                selectedIds.add(Number(cardList[i].dataset.rowId));
            }
            updateSelectionUI();
        }

        lastClickedIndex = index;
        focusedIndex = index;
        cardList.forEach((c) => c.classList.remove('review-card--focused'));
        card.classList.add('review-card--focused');
    });

    bulkSelect?.addEventListener('change', () => {
        if (applyBulkBtn) applyBulkBtn.disabled = selectedIds.size === 0 || !bulkSelect.value;
    });

    applyBulkBtn?.addEventListener('click', async () => {
        const action = bulkSelect?.value;
        if (!action || selectedIds.size === 0) return;

        applyBulkBtn.disabled = true;
        try {
            const response = await postJson(config.bulkActionUrl, {
                action,
                row_ids: Array.from(selectedIds),
            });
            showToast(response.message || 'Bulk action completed');
            clearSelection();
            setTimeout(() => window.location.reload(), 600);
        } catch (err) {
            showToast(err.message || 'Bulk action failed', 'error');
            applyBulkBtn.disabled = false;
        }
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        const action = btn.dataset.action;
        const rowId = btn.dataset.rowId;

        if (action === 'toggle-details') {
            const card = btn.closest('[data-review-card]');
            const details = card?.querySelector('.review-card__details');
            if (!details) return;
            const expanded = details.hidden;
            details.hidden = !expanded;
            btn.setAttribute('aria-expanded', String(expanded));
            btn.innerHTML = expanded
                ? '<i data-lucide="chevron-up" class="icon-xs"></i> Collapse Details'
                : '<i data-lucide="chevron-down" class="icon-xs"></i> Expand Details';
            lucide.createIcons();
            return;
        }

        if (action === 'edit') {
            openEditModal(rowId, btn.dataset.entity || 'drug');
            return;
        }

        if (action === 'approve' || action === 'reject') {
            const url = (action === 'approve' ? config.approveUrlTemplate : config.rejectUrlTemplate)
                .replace('__ROW__', rowId);
            btn.disabled = true;
            try {
                const response = await postJson(url, {});
                showToast(response.message || 'Action completed');
                btn.closest('[data-review-card]')?.classList.add('review-card--processed');
                setTimeout(() => btn.closest('[data-review-card]')?.remove(), 400);
            } catch (err) {
                showToast(err.message || 'Action failed', 'error');
                btn.disabled = false;
            }
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.target.matches('input, textarea, select') || document.querySelector('.review-modal[open]')) return;

        const cardList = cards();
        if (!cardList.length) return;

        if (e.key === 'n' || e.key === 'N') {
            e.preventDefault();
            focusedIndex = Math.min(focusedIndex + 1, cardList.length - 1);
            focusCard(cardList[focusedIndex]);
        }

        if (e.key === 'a' || e.key === 'A') {
            e.preventDefault();
            cardList[focusedIndex]?.querySelector('[data-action="approve"]')?.click();
        }

        if (e.key === 'r' || e.key === 'R') {
            e.preventDefault();
            cardList[focusedIndex]?.querySelector('[data-action="reject"]')?.click();
        }
    });

    function focusCard(card) {
        cards().forEach((c) => c.classList.remove('review-card--focused'));
        card.classList.add('review-card--focused');
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        card.focus();
    }

    if (cards().length) {
        cards()[0].classList.add('review-card--focused');
    }

    // Manual edit modal
    const modal = document.getElementById('manual-edit-modal');
    const editForm = document.getElementById('manual-edit-form');
    const editSearch = document.getElementById('edit-search');
    const editResults = document.getElementById('edit-search-results');
    const editSelectedId = document.getElementById('edit-selected-id');
    const editSelectedLabel = document.getElementById('edit-selected-label');
    const editSaveBtn = document.getElementById('edit-save-btn');
    let currentEditEntity = 'drug';
    let currentEditRowId = null;
    let searchTimeout = null;

    function openEditModal(rowId, entity) {
        currentEditRowId = rowId;
        currentEditEntity = entity;
        document.getElementById('edit-row-id').value = rowId;
        document.getElementById('edit-entity').value = entity;
        editSelectedId.value = '';
        editSelectedLabel.textContent = 'No match selected';
        editSaveBtn.disabled = true;
        editSearch.value = '';
        editResults.innerHTML = '';
        setEditTab(entity);
        modal?.showModal();
        editSearch?.focus();
    }

    function setEditTab(entity) {
        currentEditEntity = entity;
        document.getElementById('edit-entity').value = entity;
        document.querySelectorAll('[data-edit-tab]').forEach((tab) => {
            tab.classList.toggle('review-modal__tab--active', tab.dataset.editTab === entity);
        });
        editSearch.placeholder = entity === 'company'
            ? 'Search companies or aliases...'
            : 'Search products or aliases...';
        editResults.innerHTML = '';
        editSelectedId.value = '';
        editSelectedLabel.textContent = 'No match selected';
        editSaveBtn.disabled = true;
    }

    document.querySelectorAll('[data-edit-tab]').forEach((tab) => {
        tab.addEventListener('click', () => setEditTab(tab.dataset.editTab));
    });

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => modal?.close());
    });

    editSearch?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(async () => {
            const q = editSearch.value.trim();
            if (q.length < 2) {
                editResults.innerHTML = '';
                return;
            }

            const url = (currentEditEntity === 'company' ? config.searchCompaniesUrl : config.searchProductsUrl)
                + '?q=' + encodeURIComponent(q);

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                renderSearchResults(data.results || []);
            } catch {
                editResults.innerHTML = '<li class="review-search-results__empty">Search failed</li>';
            }
        }, 250);
    });

    function renderSearchResults(results) {
        if (!results.length) {
            editResults.innerHTML = '<li class="review-search-results__empty">No matches found</li>';
            return;
        }

        editResults.innerHTML = results.map((item) => {
            const meta = item.alias
                ? `Alias: ${item.alias}`
                : [item.inn, item.code, item.country].filter(Boolean).join(' · ');
            const typeLabel = item.type === 'alias' ? 'Alias match' : (item.type === 'company' ? 'Company' : 'Product');
            return `<li>
                <button type="button" class="review-search-result" data-select-result data-id="${item.id}" data-label="${escapeHtml(item.label)}">
                    <strong>${escapeHtml(item.label)}</strong>
                    <span>${escapeHtml(meta)}</span>
                    <em>${typeLabel}</em>
                </button>
            </li>`;
        }).join('');
    }

    editResults?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-select-result]');
        if (!btn) return;
        editSelectedId.value = btn.dataset.id;
        editSelectedLabel.textContent = 'Selected: ' + btn.dataset.label;
        editSaveBtn.disabled = false;
        editResults.querySelectorAll('.review-search-result').forEach((el) => el.classList.remove('is-selected'));
        btn.classList.add('is-selected');
    });

    editForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!currentEditRowId || !editSelectedId.value) return;

        const url = config.editMatchUrlTemplate.replace('__ROW__', currentEditRowId);
        const payload = { entity: currentEditEntity };
        if (currentEditEntity === 'drug') payload.standardized_drug_id = Number(editSelectedId.value);
        else payload.company_id = Number(editSelectedId.value);

        editSaveBtn.disabled = true;
        try {
            const response = await postJson(url, payload, 'PUT');
            showToast(response.message || 'Correction saved');
            modal?.close();
            setTimeout(() => window.location.reload(), 600);
        } catch (err) {
            showToast(err.message || 'Save failed', 'error');
            editSaveBtn.disabled = false;
        }
    });

    async function postJson(url, body, method = 'POST') {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': config.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ ...body, ajax: true }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || 'Request failed');
        }
        return data;
    }

    function showToast(message, type = 'success') {
        if (window.Swal) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type === 'error' ? 'error' : 'success',
                title: message,
                showConfirmButton: false,
                timer: 2800,
            });
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    const approveAllBtn = document.getElementById('approve-all-review-btn');
    const approveAllForm = document.getElementById('approve-all-review-form');

    approveAllBtn?.addEventListener('click', () => {
        const count = Number(approveAllBtn.dataset.count || 0);
        const formattedCount = count.toLocaleString();

        if (!window.Swal) {
            if (window.confirm(`Approve all ${formattedCount} review items in this batch?`)) {
                approveAllForm?.submit();
            }
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Approve All Review Items',
            html: `
                <p>You are about to approve all review items in this batch.</p>
                <p>This action will move all <strong>review_required</strong> rows to <strong>approved</strong>.</p>
                <p><strong>${formattedCount}</strong> item(s) will be affected.</p>
                <p class="text-sm text-amber-700 mt-3">Use only when you are confident the review suggestions are acceptable.</p>
            `,
            showCancelButton: true,
            confirmButtonText: 'Approve All',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            focusCancel: true,
        }).then((result) => {
            if (result.isConfirmed) {
                approveAllBtn.disabled = true;
                approveAllBtn.innerHTML = '<i data-lucide="loader" class="icon-sm animate-spin"></i> Approving…';
                lucide.createIcons();
                approveAllForm?.submit();
            }
        });
    });
});
