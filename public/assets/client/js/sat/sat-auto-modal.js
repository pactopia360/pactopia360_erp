// public/assets/client/js/sat-auto-modal.js
// PACTOPIA360 · SAT · Dashboard (cliente) · Modal Automatizar descargas

(() => {
  'use strict';

  (function initAutoModal() {
    const modal = document.getElementById('modalAuto');
    if (!modal) return;

    const closeBtn = modal.querySelector('[data-close="modal-auto"]');
    const open  = () => modal.classList.add('is-open');
    const close = () => modal.classList.remove('is-open');

    document.addEventListener('click', (e) => {
      const openBtn = e.target.closest('.auto-modal-btn');
      if (openBtn) { e.preventDefault(); open(); }
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', (e) => { e.preventDefault(); close(); });
    }

    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
  })();
})();
