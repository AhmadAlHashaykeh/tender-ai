// Shared app bootstrap: icons, shell interactions, alerts, and date pickers
(function() {
    let tailwindScriptPresent = false;
    const scripts = document.getElementsByTagName('script');
    for (let i = 0; i < scripts.length; i++) {
        if (scripts[i].src && scripts[i].src.includes('tailwindcss.com')) {
            tailwindScriptPresent = true;
            break;
        }
    }

    if (!tailwindScriptPresent) {
        const script = document.createElement('script');
        script.src = 'https://cdn.tailwindcss.com';
        document.head.appendChild(script);
    }

    if (!window.tailwind) {
        window.tailwind = {};
    }
    
    window.tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                },
                colors: {
                    primary: '#0D85E6',
                    secondary: '#7C3AED',
                    cyan: '#06B6D4',
                    foreground: '#0f172a',
                    background: '#ffffff',
                    muted: '#f1f5f9',
                    'muted-foreground': '#64748B',
                    border: '#e2e8f0',
                    card: '#ffffff',
                },
                opacity: {
                    '8': '0.08',
                    '15': '0.15',
                }
            }
        }
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Initialize Flatpickr
    initFlatpickr();
   
    // Initialize Line Chart Tooltips
    const lineCard = document.querySelector('.card-glow:has(.line-tooltip)');

    if (lineCard && typeof setupChartTooltip === 'function') {
        const lineHitAreas = lineCard.querySelectorAll('.hit-area');
        const lineTooltip = lineCard.querySelector('.line-tooltip');
        const lineCursor = lineCard.querySelector('.line-cursor');

        setupChartTooltip(
            lineHitAreas,
            lineTooltip,
            lineCursor,
            'line'
        );
    }

    // Initialize Bar Chart Tooltips
    const barCard = document.querySelector('.card-glow:has(.bar-tooltip)');

    if (barCard && typeof setupChartTooltip === 'function') {
        const barHitAreas = barCard.querySelectorAll('.hit-area');
        const barTooltip = barCard.querySelector('.bar-tooltip');
        const barCursor = barCard.querySelector('.bar-cursor');

        setupChartTooltip(
            barHitAreas,
            barTooltip,
            barCursor,
            'bar'
        );
    }

    // Profile Dropdown Toggle
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', e => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        document.addEventListener('click', e => {
            if (
                !profileDropdown.contains(e.target) &&
                e.target !== profileBtn
            ) {
                profileDropdown.classList.remove('show');
            }
        });
    }

    // Mobile Sidebar Toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (mobileMenuBtn && sidebar && sidebarOverlay) {
        const toggleSidebar = () => {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        };

        mobileMenuBtn.addEventListener('click', e => {
            e.stopPropagation();
            toggleSidebar();
        });

        // Close sidebar when clicking overlay
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', e => {
            if (
                window.innerWidth <= 768 &&
                !sidebar.contains(e.target) &&
                !mobileMenuBtn.contains(e.target) &&
                sidebar.classList.contains('show')
            ) {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            }
        });
    }

    // Logout Functionality
    const logoutBtn = document.querySelector('.dropdown-item.logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', e => {
            e.preventDefault();
            
            Swal.fire({
                title: 'Are you sure?',
                text: "You will be logged out of your session.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--primary)',
                cancelButtonColor: 'var(--destructive)',
                confirmButtonText: 'Yes, logout',
                background: 'var(--bg-card)',
                color: 'var(--foreground)',
                backdrop: `rgba(var(--foreground-rgb), 0.4)`
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Logged Out!',
                        text: 'You have been successfully logged out.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        background: 'var(--bg-card)',
                        color: 'var(--foreground)'
                    }).then(() => {
                        const isSubdir = window.location.pathname.includes('/pages/');
                        window.location.href = isSubdir ? '../index.html' : 'index.html';
                    });
                }
            });
        });
    }

    // Initialize Chart Animations
    initChartAnimations();
});

/**
 * Handles SVG line drawing and bar growth animations
 */
function initChartAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const card = entry.target;
                
                // Animate Paths (Line Charts)
                const paths = card.querySelectorAll('path.animate-draw');
                paths.forEach(path => {
                    const length = path.getTotalLength();
                    path.style.transition = 'none';
                    path.style.strokeDasharray = length;
                    path.style.strokeDashoffset = length;
                    path.style.opacity = '1';
                    
                    // Force reflow
                    path.getBoundingClientRect();
                    
                    path.style.transition = 'stroke-dashoffset 2s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.5s ease';
                    path.style.strokeDashoffset = '0';
                });

                // Animate Dots
                const dots = card.querySelectorAll('.chart-dot');
                dots.forEach((dot, i) => {
                    dot.style.opacity = '0';
                    dot.style.transform = 'scale(0)';
                    dot.style.transition = 'all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
                    dot.style.transitionDelay = `${1 + (i * 0.1)}s`;
                    
                    setTimeout(() => {
                        dot.style.opacity = '1';
                        dot.style.transform = 'scale(1)';
                    }, 50);
                });

                observer.unobserve(card);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.chart-card').forEach(card => {
        observer.observe(card);
    });
}

function initFlatpickr() {
    const datePickers = document.querySelectorAll('.datePicker');
    if (datePickers.length === 0) return;

    // Helper to dynamically load a stylesheet
    function loadCSS(url) {
        if (!document.querySelector(`link[href="${url}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;
            document.head.appendChild(link);
        }
    }

    // Helper to dynamically load a script
    function loadJS(url, callback) {
        if (!document.querySelector(`script[src="${url}"]`)) {
            const script = document.createElement('script');
            script.src = url;
            script.onload = callback;
            document.head.appendChild(script);
        } else {
            if (typeof flatpickr !== 'undefined') {
                callback();
            } else {
                const checkInterval = setInterval(() => {
                    if (typeof flatpickr !== 'undefined') {
                        clearInterval(checkInterval);
                        callback();
                    }
                }, 50);
            }
        }
    }

    // Load flatpickr CDN files dynamically
    loadCSS('https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
    loadJS('https://cdn.jsdelivr.net/npm/flatpickr', () => {
        flatpickr('.datePicker', {
            dateFormat: "Y-m-d",
            allowInput: true,
            onReady: function(selectedDates, dateStr, instance) {
                instance.element.addEventListener('click', () => {
                    instance.toggle();
                });
            }
        });
    });
}
