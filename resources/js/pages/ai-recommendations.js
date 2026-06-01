// AI recommendation page interactions.
// DOM Elements
        const inputFormCard = document.getElementById('inputFormCard');
        
        // Tender Elements
        const tenderDropdownWrapper = document.getElementById('tender-dropdown-wrapper');
        const tenderSelectBtn = document.getElementById('tender-select-btn');
        const tenderDropdownPanel = document.getElementById('tender-dropdown-panel');
        const tenderSearchInput = document.getElementById('tender-search-input');
        const tenderOptionsList = document.getElementById('tender-options-list');
        const tenderOptions = document.querySelectorAll('.tender-option');
        const tenderEmptyState = document.getElementById('tender-empty-state');
        const selectedTenderText = document.getElementById('selected-tender-text');
        const clearTenderBtn = document.getElementById('clear-tender-btn');
        const tenderChevron = document.getElementById('tender-chevron');
        const tenderDetailsPanel = document.getElementById('tender-details-panel');
        
        // Drug Elements
        const drugDropdownWrapper = document.getElementById('drug-dropdown-wrapper');
        const drugSelectBtn = document.getElementById('drug-select-btn');
        const drugDropdownPanel = document.getElementById('drug-dropdown-panel');
        const drugSearchInput = document.getElementById('drug-search-input');
        const drugOptionsList = document.getElementById('drug-options-list');
        const drugOptions = document.querySelectorAll('.drug-option');
        const drugEmptyState = document.getElementById('drug-empty-state');
        const selectedDrugText = document.getElementById('selected-drug-text');
        const clearDrugBtn = document.getElementById('clear-drug-btn');
        const drugChevron = document.getElementById('drug-chevron');
        
        // Form & Results Elements
        const quantityInput = document.getElementById('quantity-input');
        const generateRecBtn = document.getElementById('generate-rec-btn');
        const analyzingState = document.getElementById('analyzingState');
        const aiResultsWrapper = document.getElementById('ai-results-wrapper');
        
        const summaryTenderTitle = document.getElementById('summary-tender-title');
        const summaryDrugTitle = document.getElementById('summary-drug-title');
        const summaryQtyUnits = document.getElementById('summary-qty-units');
        
        const finalBidCardPrice = document.getElementById('final-bid-card-price');
        const finalBidCardProb = document.getElementById('final-bid-card-prob');
        const resultsProgressBar = document.getElementById('results-progress-bar');
        const scenarioCards = document.querySelectorAll('.scenario-card');

        // Form state
        let isTenderSelected = false;
        let isDrugSelected = false;

        // --- Dropdown Toggle Logic ---
        tenderSelectBtn.addEventListener('click', (e) => {
            if (e.target.closest('#clear-tender-btn')) return;
            tenderDropdownPanel.classList.toggle('hidden');
            drugDropdownPanel.classList.add('hidden'); // Close other
            if (!tenderDropdownPanel.classList.contains('hidden')) {
                tenderSearchInput.focus();
            }
        });

        drugSelectBtn.addEventListener('click', (e) => {
            if (e.target.closest('#clear-drug-btn')) return;
            drugDropdownPanel.classList.toggle('hidden');
            tenderDropdownPanel.classList.add('hidden'); // Close other
            if (!drugDropdownPanel.classList.contains('hidden')) {
                drugSearchInput.focus();
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#tender-dropdown-wrapper')) {
                tenderDropdownPanel.classList.add('hidden');
            }
            if (!e.target.closest('#drug-dropdown-wrapper')) {
                drugDropdownPanel.classList.add('hidden');
            }
        });

        // --- Search Logic ---
        tenderSearchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            let hasMatch = false;
            tenderOptions.forEach(opt => {
                const text = opt.innerText.toLowerCase();
                if (text.includes(query)) {
                    opt.style.display = 'block';
                    hasMatch = true;
                } else {
                    opt.style.display = 'none';
                }
            });
            if (hasMatch) tenderEmptyState.classList.add('hidden');
            else tenderEmptyState.classList.remove('hidden');
        });

        drugSearchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            let hasMatch = false;
            drugOptions.forEach(opt => {
                const text = opt.innerText.toLowerCase();
                if (text.includes(query)) {
                    opt.style.display = 'flex';
                    hasMatch = true;
                } else {
                    opt.style.display = 'none';
                }
            });
            if (hasMatch) drugEmptyState.classList.add('hidden');
            else drugEmptyState.classList.remove('hidden');
        });

        // --- Selection Logic ---
        tenderOptions.forEach(opt => {
            opt.addEventListener('click', () => {
                const name = opt.querySelector('.tender-name').innerText;
                isTenderSelected = true;
                selectedTenderText.innerText = name;
                selectedTenderText.className = "flex-1 text-sm font-medium text-foreground truncate";
                clearTenderBtn.classList.remove('hidden');
                tenderChevron.classList.add('rotate-180');
                
                tenderDropdownPanel.classList.add('hidden');

                // Slide down detail badges
                tenderDetailsPanel.style.height = "auto";
                const h = tenderDetailsPanel.offsetHeight;
                tenderDetailsPanel.style.height = "0px";
                tenderDetailsPanel.getBoundingClientRect(); // reflow
                tenderDetailsPanel.style.height = `${h + 12}px`;
                tenderDetailsPanel.style.opacity = "1";

                validateForm();
            });
        });

        drugOptions.forEach(opt => {
            opt.addEventListener('click', () => {
                const name = opt.querySelector('.drug-name').innerText;
                isDrugSelected = true;
                selectedDrugText.innerText = name;
                selectedDrugText.className = "flex-1 text-sm font-medium text-foreground truncate";
                clearDrugBtn.classList.remove('hidden');
                drugChevron.classList.add('rotate-180');
                
                drugDropdownPanel.classList.add('hidden');

                validateForm();
            });
        });

        // --- Clear Selection Logic ---
        clearTenderBtn.addEventListener('click', () => {
            isTenderSelected = false;
            selectedTenderText.innerText = "Select tender...";
            selectedTenderText.className = "flex-1 text-sm text-muted-foreground truncate";
            clearTenderBtn.classList.add('hidden');
            tenderChevron.classList.remove('rotate-180');

            // Slide up badges
            tenderDetailsPanel.style.height = "0px";
            tenderDetailsPanel.style.opacity = "0";

            // Reset search
            tenderSearchInput.value = '';
            tenderSearchInput.dispatchEvent(new Event('input'));

            validateForm();
        });

        clearDrugBtn.addEventListener('click', () => {
            isDrugSelected = false;
            selectedDrugText.innerText = "Search standardized drug name...";
            selectedDrugText.className = "flex-1 text-sm text-muted-foreground truncate";
            clearDrugBtn.classList.add('hidden');
            drugChevron.classList.remove('rotate-180');

            // Reset search
            drugSearchInput.value = '';
            drugSearchInput.dispatchEvent(new Event('input'));

            validateForm();
        });

        // --- Form Validation ---
        function validateForm() {
            const qtyVal = quantityInput.value.trim();
            if (isTenderSelected && isDrugSelected && qtyVal.length > 0) {
                generateRecBtn.disabled = false;
                generateRecBtn.classList.remove('cursor-not-allowed');
                generateRecBtn.className = "inline-flex items-center justify-center gap-2 whitespace-nowrap transition-all px-4 py-2 w-full h-12 bg-gradient-to-r from-primary to-secondary text-white border-0 rounded-xl shadow-md text-sm font-semibold hover:bg-primary/90 hover:scale-[1.01]";
            } else {
                generateRecBtn.disabled = true;
                generateRecBtn.classList.add('cursor-not-allowed');
                generateRecBtn.className = "inline-flex items-center justify-center gap-2 whitespace-nowrap transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] px-4 py-2 w-full h-12 bg-gradient-to-r from-primary to-secondary text-white border-0 rounded-xl shadow-md text-sm font-semibold cursor-not-allowed";
            }
        }

        quantityInput.addEventListener('input', validateForm);

        // Prepopulate on copy paste or quick select values
        quantityInput.addEventListener('focus', () => {
            if (quantityInput.value === '') {
                quantityInput.value = "500000";
                validateForm();
            }
        });

        // --- Generate AI Recommendation ---
        generateRecBtn.addEventListener('click', () => {
            summaryTenderTitle.innerText = selectedTenderText.innerText;
            summaryDrugTitle.innerText = selectedDrugText.innerText;
            
            const rawVal = parseFloat(quantityInput.value.replace(/,/g, ''));
            summaryQtyUnits.innerText = isNaN(rawVal) ? quantityInput.value : rawVal.toLocaleString() + ' units';

            aiResultsWrapper.classList.add('hidden');
            aiResultsWrapper.classList.add('opacity-0');
            aiResultsWrapper.classList.add('scale-98');

            analyzingState.classList.remove('hidden');
            analyzingState.classList.add('flex');
            
            analyzingState.scrollIntoView({ behavior: 'smooth', block: 'center' });

            setTimeout(() => {
                analyzingState.classList.remove('flex');
                analyzingState.classList.add('hidden');

                Swal.fire({
                    icon: 'success',
                    title: 'Recommendation Generated',
                    text: 'AI completed analyzing and has returned your winning pricing strategy.',
                    confirmButtonColor: '#0D85E6',
                    timer: 2000
                });

                aiResultsWrapper.classList.remove('hidden');
                setTimeout(() => {
                    aiResultsWrapper.classList.remove('opacity-0', 'scale-98');
                    aiResultsWrapper.classList.add('opacity-100', 'scale-100');
                    
                    resultsProgressBar.style.width = document.getElementById('final-bid-card-prob').innerText || "91%";

                    aiResultsWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);

            }, 1800);
        });

        // --- Scenario Strategies Click Highlights ---
        let recommendedBadge = document.querySelector('#balanced-card .absolute');
        if (recommendedBadge) {
            recommendedBadge.classList.add('pointer-events-none'); // make unclickable
        }

        scenarioCards.forEach(card => {
            card.addEventListener('click', () => {
                // Reset all cards to default style
                scenarioCards.forEach(c => {
                    c.className = "p-4 rounded-xl border border-border/50 bg-white/60 hover:border-blue-200 hover:bg-blue-50/30 cursor-pointer transition-all duration-200 mt-4 scenario-card";
                });

                // Highlight clicked card
                card.className = "p-4 rounded-xl border-2 border-primary bg-primary/4 relative cursor-pointer transition-all duration-200 mt-4 scenario-card";

                // Pass the Recommended badge to the clicked card
                if (recommendedBadge) {
                    card.appendChild(recommendedBadge);
                    recommendedBadge.classList.remove('hidden');
                    // Ensure the text matches for UI consistency
                    if (card.id === 'balanced-card') {
                        recommendedBadge.innerText = 'Recommended Strategy';
                        recommendedBadge.className = "absolute -top-2.5 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-primary text-white text-[10px] font-semibold shadow-sm whitespace-nowrap pointer-events-none";
                    } else {
                        recommendedBadge.innerText = 'Selected Strategy';
                        recommendedBadge.className = "absolute -top-2.5 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-secondary text-white text-[10px] font-semibold shadow-sm whitespace-nowrap pointer-events-none";
                    }
                }

                // Smoothly update recommended stats card top pricing numbers
                const newPrice = card.getAttribute('data-price');
                const newProb = card.getAttribute('data-prob');
                
                finalBidCardPrice.innerText = `$${newPrice}`;
                finalBidCardProb.innerText = newProb;
                resultsProgressBar.style.width = newProb;
            });
        });

        // Create Lucide Icons
        lucide.createIcons();
