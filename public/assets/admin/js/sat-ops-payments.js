// public/assets/admin/js/sat-ops-payments.js
// P360 · Admin · SAT Ops · Pagos · v1.0 nuevo desde cero

(function () {
  'use strict';

  if (window.__P360_SAT_OPS_PAYMENTS_JS__) return;
  window.__P360_SAT_OPS_PAYMENTS_JS__ = true;

  const root = document.getElementById('p360SatOpsPayments');
  if (!root) return;

  async function copyText(text, okMsg) {
    const val = String(text ?? '');
    try {
      await navigator.clipboard.writeText(val);
      toast(okMsg || 'Copiado');
      return true;
    } catch (_) {
      try {
        const ta = document.createElement('textarea');
        ta.value = val;
        ta.setAttribute('readonly', 'readonly');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        if (ok) {
          toast(okMsg || 'Copiado');
          return true;
        }
      } catch (_) {}
      toast('No se pudo copiar', true);
      return false;
    }
  }

  function toast(msg, isError) {
    try {
      if (window.P360 && typeof window.P360.toast === 'function') {
        window.P360.toast(msg, { timeout: isError ? 4200 : 2600 });
        return;
      }
    } catch (_) {}
    if (isError) {
      try { alert(msg); } catch (_) {}
    } else {
      try { console.log(msg); } catch (_) {}
    }
  }

  document.addEventListener('click', function (ev) {
    const btn = ev.target.closest('[data-copy]');
    if (!btn) return;

    const value = btn.getAttribute('data-copy') || '';
    const label = btn.textContent.trim() || 'Dato';
    copyText(value, `${label} copiado`);
  });
})();