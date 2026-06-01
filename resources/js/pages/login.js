/**
 * login.js — TenderAI Login Page
 *
 * Handles ONLY:
 *  - Password show/hide toggle
 *  - Submit loading state (disable button, show spinner)
 *
 * Does NOT manipulate auth logic, routes, or form submission behavior.
 * Lucide is loaded as a regular <script> in guest.blade.php AFTER this
 * module, so we rely on the DOMContentLoaded callback for icon init.
 */
document.addEventListener('DOMContentLoaded', () => {

  // Re-render Lucide icons (module runs before Lucide CDN script in some
  // timing scenarios; guest.blade.php also calls this, which is idempotent).
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }

  // ── Password visibility toggle ──────────────────────────────────────────
  const passwordInput = document.getElementById('password');
  const toggleBtn     = document.getElementById('lp-pw-toggle');
  const toggleIcon    = document.getElementById('lp-pw-icon');

  if (passwordInput && toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      const hidden = passwordInput.type === 'password';
      passwordInput.type = hidden ? 'text' : 'password';

      if (toggleIcon) {
        toggleIcon.setAttribute('data-lucide', hidden ? 'eye-off' : 'eye');
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }

      // Return focus to input for keyboard accessibility
      passwordInput.focus();
    });
  }

  // ── Submit loading state ────────────────────────────────────────────────
  const form    = document.getElementById('lp-form');
  const btn     = document.getElementById('lp-submit');
  const btnText = btn ? btn.querySelector('.lp-btn-text') : null;
  const btnSpin = btn ? btn.querySelector('.lp-btn-spin') : null;

  if (form && btn) {
    form.addEventListener('submit', () => {
      btn.disabled = true;

      if (btnText) btnText.style.display = 'none';
      if (btnSpin) {
        btnSpin.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }
    });
  }

});
