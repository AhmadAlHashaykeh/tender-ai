// Drug standardization page interactions.
lucide.createIcons();

        // Standardization Simulation
        document.querySelector('.btn-standardize').addEventListener('click', function() {
            const btn = this;
            const originalContent = btn.innerHTML;
            
            // Set loading state
            btn.disabled = true;
            btn.classList.add('processing');
            btn.innerHTML = '<i data-lucide="refresh-cw" class="icon-sm animate-spin"></i> Processing...';
            lucide.createIcons(); // Initialize the new icon

            // Simulate processing
            setTimeout(() => {
                // Restore button state
                btn.disabled = false;
                btn.classList.remove('processing');
                btn.innerHTML = originalContent;
                lucide.createIcons();

                // Show success modal
                Swal.fire({
                    icon: 'success',
                    title: 'Standardization Complete',
                    text: 'All drug names have been successfully normalized.',
                    confirmButtonColor: '#0D85E6'
                });
            }, 2000);
        });
