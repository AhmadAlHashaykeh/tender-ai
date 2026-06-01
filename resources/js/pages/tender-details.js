/**
 * Tender Details Interactivity - Drug Explorer & Interactive Table
 */

document.addEventListener('DOMContentLoaded', () => {
    initDrugExplorer();
});

function initDrugExplorer() {
    const itemsData = [
        {
            index: 1,
            name: "Paracetamol 500mg",
            category: "Analgesics",
            categoryClass: "bg-amber-50 text-amber-700 border-amber-200",
            winner: "PharmaCorp International",
            price: "$4.20",
            qty: "500,000",
            value: "$2.10M"
        },
        {
            index: 2,
            name: "Amoxicillin 250mg",
            category: "Antibiotics",
            categoryClass: "bg-blue-50 text-blue-700 border-blue-200",
            winner: "MediSupply Global",
            price: "$8.50",
            qty: "200,000",
            value: "$1.70M"
        },
        {
            index: 3,
            name: "Ibuprofen 400mg",
            category: "Analgesics",
            categoryClass: "bg-amber-50 text-amber-700 border-amber-200",
            winner: "ArabPharma Co.",
            price: "$5.10",
            qty: "300,000",
            value: "$1.53M"
        },
        {
            index: 4,
            name: "Omeprazole 20mg",
            category: "Gastrology",
            categoryClass: "bg-teal-50 text-teal-700 border-teal-200",
            winner: "Gulf MedSupply",
            price: "$7.80",
            qty: "150,000",
            value: "$1.17M"
        },
        {
            index: 5,
            name: "Metformin 500mg",
            category: "Metabolic",
            categoryClass: "bg-violet-50 text-violet-700 border-violet-200",
            winner: "MediSupply Global",
            price: "$3.90",
            qty: "300,000",
            value: "$1.17M"
        },
        {
            index: 6,
            name: "Ceftriaxone 1g",
            category: "Antibiotics",
            categoryClass: "bg-blue-50 text-blue-700 border-blue-200",
            winner: "PharmaCorp International",
            price: "$22.00",
            qty: "80,000",
            value: "$1.76M"
        },
        {
            index: 7,
            name: "Atorvastatin 20mg",
            category: "Cardiology",
            categoryClass: "bg-rose-50 text-rose-700 border-rose-200",
            winner: "ArabPharma Co.",
            price: "$11.50",
            qty: "120,000",
            value: "$1.38M"
        },
        {
            index: 8,
            name: "Bisoprolol 5mg",
            category: "Cardiology",
            categoryClass: "bg-rose-50 text-rose-700 border-rose-200",
            winner: "Global Pharma Partners",
            price: "$8.80",
            qty: "150,000",
            value: "$1.32M"
        }
    ];

    let activeIndex = 1; // Default to Item 2 (Amoxicillin 250mg) which is index 1

    const explorerPrev = document.getElementById('explorer-prev');
    const explorerNext = document.getElementById('explorer-next');
    const explorerBadge = document.getElementById('explorer-badge');
    const activeName = document.getElementById('explorer-active-name');
    const activeBadgeContainer = document.getElementById('explorer-active-badge-container');
    const dropdownTrigger = document.getElementById('explorer-dropdown-trigger');
    const dropdownMenu = document.getElementById('explorer-dropdown-menu');

    const slideContainer = document.getElementById('explorer-details-slide');
    const slideName = document.getElementById('slide-name');
    const slideBadge = document.getElementById('slide-badge');
    const slideWinner = document.getElementById('slide-winner');
    const slidePrice = document.getElementById('slide-price');
    const slideQty = document.getElementById('slide-qty');
    const slideValue = document.getElementById('slide-value');

    const tableRows = document.querySelectorAll('tbody tr');

    // 1. Setup Dropdown List Items with Search Field
    if (dropdownMenu) {
        dropdownMenu.innerHTML = `
            <div class="p-2 border-b border-slate-100 sticky top-0 bg-white z-10">
                <div class="flex items-center gap-2 h-9 px-3 rounded-lg border border-slate-200 bg-slate-50 focus-within:border-primary/50 focus-within:ring-1 focus-within:ring-primary/20 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search text-slate-400 shrink-0"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                    <input type="text" id="explorer-search-input" class="w-full text-xs bg-transparent border-none outline-none text-slate-700 placeholder:text-slate-400" placeholder="Search drugs...">
                </div>
            </div>
            <div id="explorer-dropdown-items-list" class="max-h-48 overflow-y-auto">
                ${itemsData.map((item, idx) => `
                    <div class="px-4 py-2.5 hover:bg-slate-50 cursor-pointer flex items-center justify-between border-b border-slate-100 last:border-b-0 text-sm transition-colors duration-150" data-idx="${idx}">
                        <div class="flex items-center gap-2">
                            <span class="dropdown-check-icon hidden text-primary"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check w-3.5 h-3.5"><path d="M20 6 9 17l-5-5"></path></svg></span>
                            <span class="dropdown-item-name font-medium text-slate-700">${item.name}</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border ${item.categoryClass}">${item.category}</span>
                    </div>
                `).join('')}
            </div>
        `;

        // Trigger toggle
        if (dropdownTrigger) {
            dropdownTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('hidden');
                
                // Focus search input when opening
                if (!dropdownMenu.classList.contains('hidden')) {
                    const searchInput = document.getElementById('explorer-search-input');
                    if (searchInput) {
                        searchInput.value = '';
                        searchInput.focus();
                        // Trigger input event to clear filters
                        searchInput.dispatchEvent(new Event('input'));
                    }
                }
            });
        }

        // Close on click outside
        document.addEventListener('click', () => {
            dropdownMenu.classList.add('hidden');
        });

        // Prevent dropdown close when clicking search input
        const searchInput = document.getElementById('explorer-search-input');
        if (searchInput) {
            searchInput.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase().trim();
                const itemsList = document.getElementById('explorer-dropdown-items-list');
                if (itemsList) {
                    const items = itemsList.querySelectorAll('[data-idx]');
                    items.forEach(item => {
                        const idx = parseInt(item.getAttribute('data-idx'));
                        const data = itemsData[idx];
                        if (data.name.toLowerCase().includes(query) || data.category.toLowerCase().includes(query)) {
                            item.style.setProperty('display', 'flex', 'important');
                        } else {
                            item.style.setProperty('display', 'none', 'important');
                        }
                    });
                }
            });
        }

        // Dropdown Item Select
        dropdownMenu.querySelectorAll('[data-idx]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                const idx = parseInt(item.getAttribute('data-idx'));
                setActiveItem(idx);
                dropdownMenu.classList.add('hidden');
            });
        });
    }

    // 2. Navigation Buttons
    if (explorerPrev) {
        explorerPrev.addEventListener('click', () => {
            let nextIndex = activeIndex - 1;
            if (nextIndex < 0) nextIndex = itemsData.length - 1;
            setActiveItem(nextIndex);
        });
    }

    if (explorerNext) {
        explorerNext.addEventListener('click', () => {
            let nextIndex = activeIndex + 1;
            if (nextIndex >= itemsData.length) nextIndex = 0;
            setActiveItem(nextIndex);
        });
    }

    // 3. Table Rows Interaction
    tableRows.forEach((row, idx) => {
        row.addEventListener('click', () => {
            setActiveItem(idx);
        });
    });

    // 4. Update UI State
    function setActiveItem(idx) {
        if (idx < 0 || idx >= itemsData.length) return;
        activeIndex = idx;
        const item = itemsData[activeIndex];

        // A. Update Explorer Headers
        if (explorerBadge) {
            explorerBadge.textContent = `Item ${item.index} of ${itemsData.length}`;
        }
        if (activeName) {
            activeName.textContent = item.name;
        }
        if (activeBadgeContainer) {
            activeBadgeContainer.innerHTML = `<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border ${item.categoryClass}">${item.category}</span>`;
        }

        // B. Update Dropdown Highlights
        if (dropdownMenu) {
            const dropdownItems = dropdownMenu.querySelectorAll('[data-idx]');
            dropdownItems.forEach(dItem => {
                const dIdx = parseInt(dItem.getAttribute('data-idx'));
                const checkIcon = dItem.querySelector('.dropdown-check-icon');
                const nameSpan = dItem.querySelector('.dropdown-item-name');

                if (dIdx === activeIndex) {
                    dItem.classList.add('bg-primary/5');
                    if (checkIcon) checkIcon.classList.remove('hidden');
                    if (nameSpan) {
                        nameSpan.classList.add('text-primary', 'font-semibold');
                        nameSpan.classList.remove('text-slate-700');
                    }
                } else {
                    dItem.classList.remove('bg-primary/5');
                    if (checkIcon) checkIcon.classList.add('hidden');
                    if (nameSpan) {
                        nameSpan.classList.remove('text-primary', 'font-semibold');
                        nameSpan.classList.add('text-slate-700');
                    }
                }
            });
        }

        // C. Animate and Update Details Slide (Fade up effect)
        if (slideContainer) {
            slideContainer.style.opacity = '0';
            slideContainer.style.transform = 'translateY(12px)';

            setTimeout(() => {
                if (slideName) slideName.textContent = item.name;
                if (slideWinner) slideWinner.textContent = item.winner;
                if (slidePrice) slidePrice.textContent = item.price;
                if (slideQty) slideQty.textContent = item.qty;
                if (slideValue) slideValue.textContent = item.value;
                if (slideBadge) {
                    slideBadge.textContent = item.category;
                    slideBadge.className = `px-2 py-0.5 rounded-full text-[10px] font-semibold border ${item.categoryClass}`;
                }

                slideContainer.style.opacity = '1';
                slideContainer.style.transform = 'translateY(0)';
            }, 120);
        }

        // D. Update Table Selected Row Highlight style
        tableRows.forEach((row, rIdx) => {
            const indexSpan = row.querySelector('td:first-child span');
            const nameSpan = row.querySelector('td:nth-child(2) span');

            if (rIdx === activeIndex) {
                row.className = 'border-b border-border/20 cursor-pointer transition-colors bg-primary/5';
                if (indexSpan) {
                    indexSpan.className = 'text-xs font-bold tabular-nums text-primary';
                }
                if (nameSpan) {
                    nameSpan.className = 'font-semibold text-primary';
                }
            } else {
                row.className = 'border-b border-border/20 cursor-pointer transition-colors hover:bg-muted/20';
                if (indexSpan) {
                    indexSpan.className = 'text-xs font-bold tabular-nums text-muted-foreground';
                }
                if (nameSpan) {
                    nameSpan.className = 'font-semibold text-foreground';
                }
            }
        });
    }

    // Initialize display with default active index (index 1 / item 2)
    setActiveItem(activeIndex);
}
