// public/assets/client/js/sat-manual-process.js
// PACTOPIA360 · SAT · Dashboard (cliente) · Descargas manuales (PROCESO → PAGO → LISTADO)

(() => {
  'use strict';

  window.P360_SAT = window.P360_SAT || {};
  const SAT = window.P360_SAT;

  const ROUTES = SAT.routes || {};
  const CSRF = SAT.csrf || '';

  const U = window.P360_SAT_UTILS || {};

  const satToast = U.satToast || U.toast || function (msg) {
    try { console.log('[SAT toast]', msg); } catch (_) {}
  };

  const isJsonResponse = U.isJsonResponse || function (res) {
    const ct = (res?.headers?.get?.('content-type') || '').toLowerCase();
    return ct.includes('application/json') || ct.includes('text/json') || ct.includes('+json');
  };

  async function safeText(res, maxLen = 1200) {
    if (!res) return '';
    try {
      const t = await res.text();
      if (!t) return '';
      return t.length > maxLen ? (t.slice(0, maxLen) + '…') : t;
    } catch (_) {
      return '';
    }
  }

  const safeJson = U.safeJson || async function (res) {
    if (!res) return {};
    if (!isJsonResponse(res)) return { _non_json: true, _text: await safeText(res) };
    try {
      const j = await res.json();
      return (j && typeof j === 'object') ? j : {};
    } catch (_) {
      return { _non_json: true, _text: await safeText(res) };
    }
  };

  const mkHttpError = U.mkHttpError || function (res, data, fallbackMsg) {
    const msg =
      (data && (data.msg || data.message)) ||
      (data && data._non_json && data._text ? 'Respuesta no JSON: ' + data._text : '') ||
      fallbackMsg ||
      'Error en la operación.';
    const err = new Error(msg);
    err.status = res?.status;
    err.data = data;
    return err;
  };

  (function initManualProcess() {
    const modal = document.getElementById('modalManualRequest');
    if (!modal) return;

    const btnOpen  = document.getElementById('btnManualStartProcess');
    const btnPay   = document.getElementById('btnManualSubmitPay');
    const btnToQ   = document.getElementById('btnManualDraftToQuote');

    const elRfc  = document.getElementById('manualRfc');
    const elTipo = document.getElementById('manualTipo');
    const elFrom = document.getElementById('manualFrom');
    const elTo   = document.getElementById('manualTo');

    const elXml  = document.getElementById('manualXmlEstimated');
    const elRef  = document.getElementById('manualRef');
    const elNote = document.getElementById('manualNotes');

    function open()  { modal.style.display = 'flex'; }
    function close() { modal.style.display = 'none'; }

    function isValidRange(a, b) {
      if (!a || !b) return true;
      const da = new Date(a);
      const db = new Date(b);
      if (isNaN(+da) || isNaN(+db)) return false;
      return da <= db;
    }

    async function postManualQuote(url, payload) {
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload || {}),
      });

      const data = await safeJson(res);

      if (data && data._non_json) {
        throw mkHttpError(res, data, 'El servidor devolvió una respuesta no JSON al crear la solicitud manual.');
      }

      if (!res.ok || data.ok === false) {
        throw mkHttpError(res, data, 'No se pudo crear la solicitud manual.');
      }

      return data;
    }

    // Abrir modal
    if (btnOpen) {
      btnOpen.addEventListener('click', (ev) => {
        ev.preventDefault();
        const url = (btnOpen.dataset.url || ROUTES.manualQuote || '').trim();
        if (!url) return satToast('Ruta manualQuote no configurada.', 'error');

        if (elXml && !elXml.value) elXml.value = '1000';
        open();
      });
    }

    // Cerrar modal
    document.addEventListener('click', (ev) => {
      const b = ev.target.closest('[data-close="modal-manual"]');
      if (!b) return;
      ev.preventDefault();
      close();
    }, true);

    modal.addEventListener('click', (ev) => {
      if (ev.target === modal) close();
    });

    // Ir al cotizador
    if (btnToQ) {
      btnToQ.addEventListener('click', (ev) => {
        ev.preventDefault();
        close();
        const q = document.getElementById('btnQuickCalc');
        if (q) q.click();
        else satToast('No se encontró el cotizador (btnQuickCalc).', 'error');
      });
    }

    // Crear solicitud + redirect a pago
    if (btnPay) {
      btnPay.addEventListener('click', async (ev) => {
        ev.preventDefault();

        const url = (ROUTES.manualQuote || btnOpen?.dataset?.url || '').trim();
        if (!url) return satToast('Ruta manualQuote no configurada.', 'error');

        const rfc = String(elRfc?.value || '').trim();
        if (!rfc) return satToast('Selecciona un RFC válido.', 'error');

        const from = String(elFrom?.value || '').trim();
        const to   = String(elTo?.value || '').trim();
        if ((from || to) && !isValidRange(from, to)) {
          return satToast('El rango de fechas es inválido (Desde no puede ser mayor que Hasta).', 'error');
        }

        const xmlEstimated = parseInt(String(elXml?.value || '0'), 10) || 0;
        if (xmlEstimated <= 0) return satToast('Escribe una cantidad estimada de XML válida.', 'error');

        const payload = {
          rfc,
          tipo: String(elTipo?.value || 'ambos'),
          from: from || null,
          to:   to   || null,
          xml_count_estimated: xmlEstimated,
          ref: String(elRef?.value || '').trim() || null,
          notes: String(elNote?.value || '').trim() || null,
        };

        btnPay.disabled = true;
        btnPay.classList.add('is-loading');

        try {
          const data = await postManualQuote(url, payload);

          const checkoutUrl =
            data.checkout_url || data.url || data.session_url || (data.data && (data.data.checkout_url || data.data.url));

          if (!checkoutUrl) {
            satToast('Solicitud creada. Abriendo listado de manuales...', 'ok');
            if (ROUTES.manualIndex) window.location.href = ROUTES.manualIndex;
            else close();
            return;
          }

          window.location.href = checkoutUrl;
        } catch (e) {
          satToast(e?.message || 'No se pudo iniciar el proceso de pago.', 'error');
        } finally {
          btnPay.disabled = false;
          btnPay.classList.remove('is-loading');
        }
      });
    }
  })();

})();
