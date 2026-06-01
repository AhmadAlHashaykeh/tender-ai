
        // File Upload Logic
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('file-upload');
        const fileInfoContainer = document.getElementById('fileInfoContainer');
        const fileNameDisplay = document.getElementById('fileName');
        const fileSizeDisplay = document.getElementById('fileSize');
        const removeFileBtn = document.getElementById('removeFile');

        if (dropzone && fileInput) {
            // Click to upload
            dropzone.addEventListener('click', () => {
                fileInput.click();
            });

            // Drag and drop events
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, () => {
                    dropzone.classList.add('highlight');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, () => {
                    dropzone.classList.remove('highlight');
                }, false);
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                handleFiles(files);
            });

            fileInput.addEventListener('change', (e) => {
                handleFiles(e.target.files);
            });

            const handleFiles = (files) => {
                if (files.length > 0) {
                    const file = files[0];
                    fileNameDisplay.innerText = file.name;
                    fileSizeDisplay.innerText = (file.size / 1024).toFixed(1) + ' KB';
                    fileInfoContainer.classList.add('active');

                    // Reveal extra sections
                    const uploadStats = document.getElementById('uploadStats');
                    const previewSection = document.getElementById('previewSection');

                    if (uploadStats && previewSection) {
                        uploadStats.classList.add('active');
                        previewSection.classList.add('active');

                        // Animate all articles
                        const articles = document.querySelectorAll('.upload-view article');
                        articles.forEach((article, index) => {
                            article.classList.remove('animate-card');
                            void article.offsetWidth; // trigger reflow
                            article.style.animationDelay = `${index * 0.1}s`;
                            article.classList.add('animate-card');
                        });
                    }
                }
            };

            if (removeFileBtn) {
                removeFileBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    fileInput.value = '';
                    fileInfoContainer.classList.remove('active');

                    const uploadStats = document.getElementById('uploadStats');
                    const previewSection = document.getElementById('previewSection');
                    if (uploadStats) uploadStats.classList.remove('active');
                    if (previewSection) previewSection.classList.remove('active');
                });
            }
        }

// Card 2: Upcoming Tenders Form Logic
const tenderName = document.getElementById('tenderNameInput');
const tenderCountry = document.getElementById('tenderCountryInput');
const tenderDate = document.getElementById('tenderDateInput');
const tenderDrug = document.getElementById('tenderDrugInput');
const tenderQuantity = document.getElementById('tenderQuantityInput');
const addTenderBtn = document.getElementById('addTenderBtn');

if (tenderName && tenderCountry && tenderDate && tenderDrug && addTenderBtn) {
    const validateTenderForm = () => {
        const isNameFilled = tenderName.value.trim() !== '';
        const isCountryFilled = tenderCountry.value.trim() !== '';
        const isDateFilled = tenderDate.value.trim() !== '';
        const isDrugFilled = tenderDrug.value.trim() !== '';
        
        if (isNameFilled && isCountryFilled && isDateFilled && isDrugFilled) {
            addTenderBtn.removeAttribute('disabled');
        } else {
            addTenderBtn.setAttribute('disabled', 'true');
        }
    };

    [tenderName, tenderCountry, tenderDate, tenderDrug].forEach(input => {
        input.addEventListener('input', validateTenderForm);
        input.addEventListener('change', validateTenderForm);
    });

    addTenderBtn.addEventListener('click', (e) => {
        e.preventDefault();
        Swal.fire({
            title: 'Success!',
            text: 'Upcoming tender record added successfully!',
            icon: 'success',
            confirmButtonColor: '#0D85E6'
        });
        
        // Clear inputs
        tenderName.value = '';
        tenderCountry.value = '';
        tenderDate.value = '';
        tenderDrug.value = '';
        if (tenderQuantity) tenderQuantity.value = '';
        
        if (tenderDate._flatpickr) {
            tenderDate._flatpickr.clear();
        }
        
        addTenderBtn.setAttribute('disabled', 'true');
    });
}

// Card 3: Company History Form Logic
const historyTenderName = document.getElementById('historyTenderNameInput');
const historyDrug = document.getElementById('historyDrugInput');
const historyCountry = document.getElementById('historyCountryInput');
const historyDate = document.getElementById('historyDateInput');
const historyPrice = document.getElementById('historyPriceInput');
const historyWonBtn = document.getElementById('historyWonBtn');
const historyLostBtn = document.getElementById('historyLostBtn');
const addRecordBtn = document.getElementById('addRecordBtn');

if (historyTenderName && historyDrug && historyCountry && historyDate && historyPrice && addRecordBtn) {
    let outcome = 'won'; // Default outcome

    const setOutcome = (newOutcome) => {
        outcome = newOutcome;
        if (outcome === 'won') {
            // Won active state
            historyWonBtn.className = 'flex-1 h-8 rounded-lg text-xs font-semibold border transition-all bg-emerald-500 text-white border-emerald-500';
            // Lost inactive state
            historyLostBtn.className = 'flex-1 h-8 rounded-lg text-xs font-semibold border transition-all bg-slate-50 text-muted-foreground border-border/50 hover:border-primary/30';
        } else {
            // Lost active state (using rose/red matching theme)
            historyLostBtn.className = 'flex-1 h-8 rounded-lg text-xs font-semibold border transition-all bg-rose-500 text-white border-rose-500';
            // Won inactive state
            historyWonBtn.className = 'flex-1 h-8 rounded-lg text-xs font-semibold border transition-all bg-slate-50 text-muted-foreground border-border/50 hover:border-primary/30';
        }
    };

    if (historyWonBtn && historyLostBtn) {
        historyWonBtn.addEventListener('click', (e) => {
            e.preventDefault();
            setOutcome('won');
        });
        historyLostBtn.addEventListener('click', (e) => {
            e.preventDefault();
            setOutcome('lost');
        });
    }

    const validateHistoryForm = () => {
        const isTenderFilled = historyTenderName.value.trim() !== '';
        const isDrugFilled = historyDrug.value.trim() !== '';
        const isCountryFilled = historyCountry.value.trim() !== '';
        const isDateFilled = historyDate.value.trim() !== '';
        const isPriceFilled = historyPrice.value.trim() !== '';

        if (isTenderFilled && isDrugFilled && isCountryFilled && isDateFilled && isPriceFilled) {
            addRecordBtn.removeAttribute('disabled');
        } else {
            addRecordBtn.setAttribute('disabled', 'true');
        }
    };

    [historyTenderName, historyDrug, historyCountry, historyDate, historyPrice].forEach(input => {
        input.addEventListener('input', validateHistoryForm);
        input.addEventListener('change', validateHistoryForm);
    });

    addRecordBtn.addEventListener('click', (e) => {
        e.preventDefault();
        Swal.fire({
            title: 'Success!',
            text: `Tender history record (${outcome === 'won' ? 'Won' : 'Lost'}) added successfully!`,
            icon: 'success',
            confirmButtonColor: '#7C3AED'
        });

        // Clear inputs
        historyTenderName.value = '';
        historyDrug.value = '';
        historyCountry.value = '';
        historyDate.value = '';
        historyPrice.value = '';
        
        if (historyDate._flatpickr) {
            historyDate._flatpickr.clear();
        }

        setOutcome('won');
        addRecordBtn.setAttribute('disabled', 'true');
    });
}
