// ============================================================
// main.js — MidnightBuilds light JS enhancements
// No framework. Progressive enhancement only.
// PHP handles all validation; JS adds convenience only.
// ============================================================

/* ---- Mobile navigation hamburger toggle --------------- */
(function () {
    const hamburger = document.getElementById('hamburger');
    const nav       = document.getElementById('main-nav');
    if (!hamburger || !nav) return;

    hamburger.addEventListener('click', () => {
        const isOpen = nav.classList.toggle('is-open');
        hamburger.setAttribute('aria-expanded', isOpen);
    });

    // Close nav when a link inside is clicked (SPA-like feel)
    nav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => nav.classList.remove('is-open'));
    });
})();

/* ---- Description character counter ------------------- */
(function () {
    const textarea = document.getElementById('description');
    const counter  = document.getElementById('desc-counter');
    if (!textarea || !counter) return;

    const MAX = parseInt(textarea.getAttribute('maxlength'), 10) || 2000;

    function update() {
        const remaining = MAX - textarea.value.length;
        counter.textContent = remaining + ' chars remaining';
        counter.style.color = remaining < 100
            ? 'var(--clr-error)'
            : 'var(--clr-text-dim)';
    }

    textarea.addEventListener('input', update);
    update(); // initialise on page load (handles repopulated forms)
})();

/* ---- Upvote button feedback (optimistic UI) ----------- */
(function () {
    const form = document.querySelector('.upvote-section form');
    if (!form) return;

    form.addEventListener('submit', () => {
        const btn = form.querySelector('.btn-upvote');
        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.7';
        }
    });
})();

/* ---- Auto-dismiss flash alerts after 5 s -------------- */
(function () {
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity    = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
})();
