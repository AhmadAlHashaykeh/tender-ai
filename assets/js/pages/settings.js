// Settings page interactions: tabs, switches, and save confirmations.

// Tab switching logic
        document.querySelectorAll('.tabs-trigger').forEach(trigger => {
            trigger.addEventListener('click', () => {
                const tabId = trigger.getAttribute('data-tab');
                
                // Update buttons
                document.querySelectorAll('.tabs-trigger').forEach(t => t.classList.remove('active'));
                trigger.classList.add('active');
                
                // Update content
                document.querySelectorAll('.tabs-content').forEach(content => {
                    content.classList.remove('active');
                    if (content.id === tabId) {
                        content.classList.add('active');
                    }
                });
            });
        });

        // Save settings logic
        document.querySelectorAll('.save-settings-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                Swal.fire({
                    title: 'Success!',
                    text: 'Settings have been updated successfully.',
                    icon: 'success',
                    confirmButtonColor: '#0D85E6',
                    background: '#ffffff',
                    customClass: {
                        popup: 'animated fadeInDown'
                    }
                });
            });
        });

        // Switch toggle logic
        document.querySelectorAll('.settings-switch').forEach(sw => {
            sw.addEventListener('click', () => {
                const isChecked = sw.getAttribute('aria-checked') === 'true';
                sw.setAttribute('aria-checked', !isChecked);
            });
        });

        lucide.createIcons();
