// public/assets/client/js/sat-external-invite-esc.js
// PACTOPIA360 · SAT · Dashboard (cliente) · Modal "Registro externo" – cerrar con ESC

(() => {
  'use strict';

  (function bindExternalInviteEsc() {
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      const modal = document.getElementById('modalExternalRfcInvite');
      if (!modal) return;

      // Si está visible (display:flex), cerrarlo
      const isOpen = (modal.style.display && modal.style.display !== 'none');
      if (!isOpen) return;

      modal.style.display = 'none';
    });
  })();

})();
