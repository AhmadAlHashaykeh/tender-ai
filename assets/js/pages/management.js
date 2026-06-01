
    // Manual Entry Modal Logic
    const manualEntryBtn = document.getElementById('manual-entry-btn');
    const manualEntryModal = document.getElementById('manualEntryModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    const manualEntryForm = document.getElementById('manualEntryForm');

    if (manualEntryBtn && manualEntryModal) {
        const openModal = (mode = 'add') => {
            const title = manualEntryModal.querySelector('.modal-title');
            const submitBtn = manualEntryForm?.querySelector('button[type="submit"]');

            if (mode === 'add') {
                if (title) title.textContent = 'Add New Tender Record';
                if (submitBtn) submitBtn.textContent = 'Save Record';
                manualEntryForm?.reset();
                if (manualEntryForm) delete manualEntryForm.dataset.mode;
            }

            manualEntryModal.classList.add('active');
            const container = manualEntryModal.querySelector('.modal-container');
            if (container) container.classList.add('active');
            document.body.style.overflow = 'hidden';
        };

        manualEntryBtn.addEventListener('click', () => openModal('add'));

        const closeModal = () => {
            manualEntryModal.classList.remove('active');
            const container = manualEntryModal.querySelector('.modal-container');
            if (container) container.classList.remove('active');
            document.body.style.overflow = '';
        };

        closeModalBtn?.addEventListener('click', closeModal);
        cancelModalBtn?.addEventListener('click', closeModal);

        manualEntryModal.addEventListener('click', e => {
            if (e.target === manualEntryModal) closeModal();
        });

        // Edit Tender Logic
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const card = btn.closest('.tender-item-card');
                if (!card) return;

                // Extract data
                const tenderNum = card.querySelector('.badge-id')?.textContent || '';
                const drugName = card.querySelector('h4')?.textContent || '';
                const statusBadge = card.querySelector('[class*="badge-status-"]');
                const status = statusBadge?.textContent.trim().toLowerCase() || '';

                const metaItems = card.querySelectorAll('.meta-item');
                let country = '';
                let company = '';
                metaItems.forEach(item => {
                    if (item.querySelector('.lucide-map-pin')) country = item.querySelector('span').textContent;
                    if (item.querySelector('.lucide-building2')) company = item.querySelector('span').textContent;
                });

                const metaSpans = card.querySelectorAll('.meta-info-row span');
                let year = '';
                let quantity = '';
                metaSpans.forEach(span => {
                    const text = span.textContent;
                    if (text.includes('Year:')) year = text.replace('Year:', '').trim();
                    if (text.includes('Qty:')) quantity = text.replace('Qty:', '').replace(/,/g, '').trim();
                });

                const bidDetailItems = card.querySelectorAll('.bid-detail-item');
                let bidPrice = '';
                let awardedPrice = '';
                let winner = '';
                bidDetailItems.forEach(item => {
                    const label = item.querySelector('p:first-child')?.textContent;
                    const value = item.querySelector('p:last-child')?.textContent;
                    if (label === 'Bid Price') bidPrice = value.replace('$', '').replace(/,/g, '');
                    if (label === 'Awarded Price') awardedPrice = value.replace('$', '').replace(/,/g, '');
                    if (label === 'Winner') winner = item.querySelector('.winner-name')?.textContent || value;
                });

                // Populate Modal
                openModal('edit');
                const title = manualEntryModal.querySelector('.modal-title');
                const submitBtn = manualEntryForm.querySelector('button[type="submit"]');

                if (title) title.textContent = 'Edit Tender Record';
                if (submitBtn) submitBtn.textContent = 'Update Record';

                if (manualEntryForm) {
                    manualEntryForm.querySelector('#tender-number').value = tenderNum;
                    manualEntryForm.querySelector('#status').value = status;
                    manualEntryForm.querySelector('#drug-name').value = drugName;

                    // Simple country mapping
                    const countrySelect = manualEntryForm.querySelector('#country');
                    if (countrySelect) {
                        const options = Array.from(countrySelect.options);
                        const match = options.find(o => o.text.toLowerCase() === country.toLowerCase());
                        if (match) countrySelect.value = match.value;
                    }

                    manualEntryForm.querySelector('#year').value = year;
                    manualEntryForm.querySelector('#quantity').value = quantity;
                    manualEntryForm.querySelector('#company').value = company;
                    manualEntryForm.querySelector('#winner').value = winner;
                    manualEntryForm.querySelector('#bid-price').value = bidPrice;
                    manualEntryForm.querySelector('#awarded-price').value = awardedPrice;

                    manualEntryForm.dataset.mode = 'edit';
                }
            });
        });

        // Form Submit
        manualEntryForm?.addEventListener('submit', e => {
            e.preventDefault();
            const isEdit = manualEntryForm.dataset.mode === 'edit';

            closeModal();

            Swal.fire({
                title: isEdit ? 'Record Updated!' : 'Record Saved!',
                text: isEdit ? 'The tender record has been successfully updated.' : 'A new tender record has been added to your database.',
                icon: 'success',
                confirmButtonColor: 'var(--primary)',
                customClass: {
                    popup: 'swal-custom-popup',
                    title: 'swal-custom-title',
                    confirmButton: 'swal-custom-confirm'
                }
            });

            manualEntryForm.reset();
        });
    }

    // Excel Upload Logic
    const excelFileInput = document.getElementById('excel-file-input');
    const excelInitialState = document.getElementById('excel-initial-state');
    const excelProcessingState = document.getElementById('excel-processing-state');
    const excelResultState = document.getElementById('excel-result-state');
    const excelProgressBar = document.getElementById('excel-progress-bar');
    const excelImportFooter = document.getElementById('excel-import-footer');
    const processingFileName = document.getElementById('processing-file-name');
    const processingFileSize = document.getElementById('processing-file-size');
    const resultFileName = document.getElementById('result-file-name');
    const resultFileSize = document.getElementById('result-file-size');
    const removeFileBtns = document.querySelectorAll('.btn-remove-file');
    const btnCancelImport = document.getElementById('btn-cancel-import');

    if (excelFileInput) {
        excelFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Update file info
                const fileSizeStr = (file.size / 1024).toFixed(2) + ' KB';
                processingFileName.textContent = file.name;
                processingFileSize.textContent = fileSizeStr;
                resultFileName.textContent = file.name;
                resultFileSize.textContent = fileSizeStr;

                // Show processing state
                excelInitialState.style.display = 'none';
                excelProcessingState.style.display = 'block';
                excelResultState.style.display = 'none';
                if (excelImportFooter) excelImportFooter.classList.remove('active');

                // Simulate progress (3 seconds total)
                let progress = 0;
                excelProgressBar.style.width = '0%';

                const totalDuration = 3000;
                const jumpDelay = 400;
                const crawlDuration = totalDuration - jumpDelay;

                // Jump shoot at start
                setTimeout(() => {
                    progress = 60 + Math.random() * 5;
                    excelProgressBar.style.width = progress + '%';

                    const startTime = Date.now();

                    // Controlled crawl to the finish
                    const interval = setInterval(() => {
                        const elapsed = Date.now() - startTime;
                        const ratio = elapsed / crawlDuration;

                        if (ratio >= 1) {
                            progress = 100;
                            excelProgressBar.style.width = '100%';
                            clearInterval(interval);

                            // Switch to result state
                            setTimeout(() => {
                                excelProcessingState.style.display = 'none';
                                excelResultState.style.display = 'block';
                                if (excelImportFooter) excelImportFooter.classList.add('active');
                            }, 400);
                        } else {
                            // Calculate crawl progress (from jump point to 98%)
                            // We use a small easing to keep it feeling natural
                            const currentCrawl = 60 + (ratio * 38);
                            excelProgressBar.style.width = currentCrawl + '%';
                        }
                    }, 50); // High frequency for smooth movement
                }, jumpDelay);
            }
        });
    }

    if (removeFileBtns) {
        removeFileBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                excelInitialState.style.display = 'block';
                excelProcessingState.style.display = 'none';
                excelResultState.style.display = 'none';
                if (excelImportFooter) excelImportFooter.classList.remove('active');
                if (excelFileInput) excelFileInput.value = '';
            });
        });
    }

    if (btnCancelImport) {
        btnCancelImport.addEventListener('click', () => {
            excelInitialState.style.display = 'block';
            excelProcessingState.style.display = 'none';
            excelResultState.style.display = 'none';
            if (excelImportFooter) excelImportFooter.classList.remove('active');
            if (excelFileInput) excelFileInput.value = '';
        });
    }

    // Excel Upload Workspace Modal Logic
    const excelUploadBtn = document.getElementById('excel-upload-btn');
    const excelUploadModal = document.getElementById('excelUploadModal');
    const closeExcelModal = document.getElementById('closeExcelModal');

    if (excelUploadBtn && excelUploadModal) {
        const closeExcel = () => {
            excelUploadModal.classList.remove('active');
            document.body.style.overflow = '';
        };

        excelUploadBtn.addEventListener('click', (e) => {
            e.preventDefault();
            excelUploadModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        closeExcelModal?.addEventListener('click', (e) => {
            e.preventDefault();
            closeExcel();
        });

        // Close on backdrop click
        excelUploadModal.addEventListener('click', (e) => {
            if (e.target === excelUploadModal) {
                closeExcel();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && excelUploadModal.classList.contains('active')) {
                closeExcel();
            }
        });
    }

    // Delete Confirmation Logic
    const deleteBtns = document.querySelectorAll('.btn-delete');
    if (deleteBtns.length > 0) {
        deleteBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const card = btn.closest('.tender-item-card');
                const tenderName = card.querySelector('h4').textContent;

                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to delete "${tenderName}". This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel',
                    padding: '2rem',
                    borderRadius: '1.5rem',
                    customClass: {
                        popup: 'swal-custom-popup',
                        title: 'swal-custom-title',
                        confirmButton: 'swal-custom-confirm'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Visual feedback for deletion
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.95)';
                        card.style.transition = 'all 0.4s ease';

                        setTimeout(() => {
                            card.remove();
                            Swal.fire({
                                title: 'Deleted!',
                                text: 'The tender record has been removed.',
                                icon: 'success',
                                confirmButtonColor: 'var(--primary)'
                            });
                        }, 400);
                    }
                });
            });
        });
    }
