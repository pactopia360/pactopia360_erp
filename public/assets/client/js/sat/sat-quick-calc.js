// public/assets/client/js/sat-quick-calc.js
// PACTOPIA360 · SAT · Dashboard (cliente) · COTIZADOR SAT (Quick Calc) – Recalcular + PDF

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

  (function initQuickCalc() {
    const btnOpen = document.getElementById('btnQuickCalc');
    const modal   = document.getElementById('modalQuickCalc');
    if (!btnOpen || !modal) return;

    const btnClose  = modal.querySelector('[data-close="quick-calc"], #btnQuickCalcClose');
    const btnRecalc  = modal.querySelector('#btnQuickCalcRecalc');
    const btnPdf     = modal.querySelector('#btnQuickCalcPdf');

    const elRfc   = modal.querySelector('#qcRfc');
    const elTipo  = modal.querySelector('#qcTipo');
    const elFrom  = modal.querySelector('#qcFrom');
    const elTo    = modal.querySelector('#qcTo');
    const elXml   = modal.querySelector('#qcXml');
    const elDisc  = modal.querySelector('#qcDiscount');
    const elIva   = modal.querySelector('#qcIva');
    const elNotes = modal.querySelector('#qcNotes');

    const outBase     = modal.querySelector('#qcBase');
    const outDiscAmt  = modal.querySelector('#qcDiscAmt');
    const outSubtotal = modal.querySelector('#qcSubtotal');
    const outIvaAmt   = modal.querySelector('#qcIvaAmt');
    const outTotal    = modal.querySelector('#qcTotal');
    const outMsg      = modal.querySelector('#qcMsg');

    function open()  { modal.classList.add('is-open'); }
    function close() { modal.classList.remove('is-open'); }

    function money(n) {
      const x = Number(n || 0);
      return '$' + x.toFixed(2);
    }

    function setMsg(text, kind = 'info') {
      if (!outMsg) return;
      outMsg.textContent = String(text || '');
      outMsg.className = 'qc-msg qc-msg-' + (kind || 'info');
    }

    function setTotals(t) {
      const base = Number(t.base ?? t.amount_base ?? 0) || 0;
      const disc = Number(t.discount_amount ?? t.discount ?? 0) || 0;
      const sub  = Number(t.subtotal ?? (base - disc) ?? 0) || 0;
      const iva  = Number(t.iva_amount ?? t.iva ?? 0) || 0;
      const tot  = Number(t.total ?? (sub + iva) ?? 0) || 0;

      if (outBase)     outBase.textContent     = money(base);
      if (outDiscAmt)  outDiscAmt.textContent  = '-' + money(Math.abs(disc));
      if (outSubtotal) outSubtotal.textContent = money(sub);
      if (outIvaAmt)   outIvaAmt.textContent   = money(iva);
      if (outTotal)    outTotal.textContent    = money(tot);
    }

    function getPayload() {
      const rfc = String(elRfc?.value || '').trim();
      const tipo = String(elTipo?.value || 'ambos').trim();
      const from = String(elFrom?.value || '').trim();
      const to   = String(elTo?.value || '').trim();

      const xmlEstimated = parseInt(String(elXml?.value || '0'), 10) || 0;
      const discountCode = String(elDisc?.value || '').trim() || null;

      let ivaRate = String(elIva?.value || '0.16').trim();
      let ivaNum = parseFloat(ivaRate);
      if (!isNaN(ivaNum) && ivaNum > 1) ivaNum = ivaNum / 100;

      const notes = String(elNotes?.value || '').trim() || null;

      return {
        rfc,
        tipo,
        from: from || null,
        to: to || null,
        xml_count_estimated: xmlEstimated,
        discount_code: discountCode,
        iva_rate: isNaN(ivaNum) ? 0.16 : ivaNum,
        notes
      };
    }

    function validate(p) {
      if (!p.rfc) return 'Selecciona un RFC válido.';
      if (!p.xml_count_estimated || p.xml_count_estimated <= 0) return 'Escribe una cantidad estimada de XML válida.';
      if (p.from && p.to) {
        const a = new Date(p.from);
        const b = new Date(p.to);
        if (isNaN(+a) || isNaN(+b)) return 'Fechas inválidas.';
        if (a > b) return 'La fecha inicial no puede ser mayor que la final.';
      }
      return '';
    }

    async function doQuote() {
      const url = (ROUTES.quote || '').trim();
      if (!url) {
        satToast('Ruta de cotización (quote) no configurada.', 'error');
        return null;
      }

      const payload = getPayload();
      const err = validate(payload);
      if (err) {
        satToast(err, 'error');
        return null;
      }

      if (btnRecalc) btnRecalc.disabled = true;
      if (btnPdf) btnPdf.disabled = true;
      setMsg('Calculando...', 'info');

      try {
        const res = await fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify(payload),
        });

        const data = await safeJson(res);

        if (data && data._non_json) {
          console.error('[SAT-QUOTE] non-json', { status: res.status, text: data._text });
          throw mkHttpError(res, data, 'Respuesta no JSON al cotizar. Revisa sesión/CSRF.');
        }

        if (!res.ok || data.ok === false) {
          throw mkHttpError(res, data, 'No se pudo cotizar la descarga.');
        }

        const totals = data.totals || data.data?.totals || data.quote || data;
        setTotals(totals);

        setMsg(data.msg || 'Cotización actualizada.', 'ok');
        return { payload, data };
      } catch (e) {
        console.error('[SAT-QUOTE] error', e);
        setMsg(e.message || 'Error al cotizar.', 'error');
        satToast(e.message || 'Error al cotizar.', 'error');
        return null;
      } finally {
        if (btnRecalc) btnRecalc.disabled = false;
        if (btnPdf) btnPdf.disabled = false;
      }
    }

    async function doPdf() {
      const url = (ROUTES.quotePdf || '').trim();
      if (!url) return satToast('Ruta quotePdf no configurada.', 'error');

      const payload = getPayload();
      const err = validate(payload);
      if (err) return satToast(err, 'error');

      try {
        if (btnPdf) btnPdf.disabled = true;

        const res = await fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify(payload),
        });

        if (!isJsonResponse(res)) {
          window.location.href = url;
          return;
        }

        const data = await safeJson(res);
        if (!res.ok || data.ok === false) throw mkHttpError(res, data, 'No se pudo generar el PDF.');

        const pdfUrl = data.pdf_url || data.url || data.download_url;
        if (!pdfUrl) {
          satToast('PDF generado pero no se recibió pdf_url.', 'error');
          return;
        }

        window.open(pdfUrl, '_blank');
      } catch (e) {
        console.error('[SAT-PDF] error', e);
        satToast(e.message || 'No se pudo generar el PDF.', 'error');
      } finally {
        if (btnPdf) btnPdf.disabled = false;
      }
    }

    // Abrir / cerrar modal
    btnOpen.addEventListener('click', (e) => { e.preventDefault(); open(); });

    if (btnClose) btnClose.addEventListener('click', (e) => { e.preventDefault(); close(); });
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

    // Acciones
    if (btnRecalc) btnRecalc.addEventListener('click', (e) => { e.preventDefault(); doQuote(); });
    if (btnPdf)    btnPdf.addEventListener('click', (e) => { e.preventDefault(); doPdf(); });

    // Auto recalc (opcional)
    [elRfc, elTipo, elFrom, elTo, elXml, elDisc, elIva].forEach(el => {
      if (!el) return;
      el.addEventListener('change', () => { /* doQuote(); */ });
    });
  })();

})();
