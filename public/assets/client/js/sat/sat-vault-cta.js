// public/assets/client/js/sat-vault-cta.js
// PACTOPIA360 · SAT · Dashboard (cliente) · CTA "Activar / Ampliar bóveda"

(() => {
  'use strict';

  window.P360_SAT = window.P360_SAT || {};
  const SAT = window.P360_SAT;
  const ROUTES = SAT.routes || {};

  (function initVaultCta() {
    const btn = document.getElementById('btnVaultSummaryCta') || document.querySelector('.btn-vault-cta');
    if (!btn) return;

    const vaultUrl = ROUTES.vaultIndex || btn.getAttribute('data-vault-url') || null;
    if (!vaultUrl || vaultUrl === '#' || vaultUrl === 'null') return;

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      window.location.href = vaultUrl;
    });
  })();

})();
