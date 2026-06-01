// Initialize Lucide icons
    lucide.createIcons();

    // Password visibility toggle
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.querySelector('.password-toggle');
    
    toggleBtn.addEventListener('click', () => {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      
      // Update icon
      const icon = toggleBtn.querySelector('i');
      if (type === 'text') {
        icon.setAttribute('data-lucide', 'eye-off');
      } else {
        icon.setAttribute('data-lucide', 'eye');
      }
      lucide.createIcons();
    });

    // Form submission with SweetAlert
    document.getElementById('loginForm').addEventListener('submit', (e) => {
      e.preventDefault();
      
      Swal.fire({
        title: 'Login Successful!',
        text: 'Welcome back to TenderAI Dashboard',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        background: '#ffffff',
        color: '#0f172a',
        iconColor: '#0D85E6',
        backdrop: `rgba(13, 133, 230, 0.1) blur(4px)`
      }).then(() => {
        window.location.href = 'dashboard.html';
      });
    });

    // Statistics Counter Animation
    const counters = document.querySelectorAll('.counter');
    const speed = 200;

    const animateCounters = () => {
      counters.forEach(counter => {
        const updateCount = () => {
          const target = +counter.getAttribute('data-target');
          const count = +counter.innerText;
          const inc = target / speed;

          if (count < target) {
            counter.innerText = Math.ceil(count + inc);
            setTimeout(updateCount, 1);
          } else {
            counter.innerText = target;
          }
        };
        updateCount();
      });
    };

    // Run counter animation after a short delay for better visual impact
    window.addEventListener('DOMContentLoaded', () => {
      setTimeout(animateCounters, 800);
    });
