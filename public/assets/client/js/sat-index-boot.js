// public/assets/client/js/sat-index-boot.js
// PACTOPIA360 · SAT · Index BOOT (bulk + quick guides + vault + cotizadores + manual + external invite)
// NOTE: Este archivo reemplaza el mega <script> inline del index.blade.php (evita SyntaxError por tokens extraños)

document.addEventListener('DOMContentLoaded', function () {
  if (window.__P360_SAT_INDEX_BOOT__) return;
  window.__P360_SAT_INDEX_BOOT__ = true;

  const CFG    = window.P360_SAT || {};
  const ROUTES = CFG.routes || {};
  const CSRF   = CFG.csrf || '';

  // =========================================================
  // Bridge global: algunos handlers/partials llaman applyFilters()
  // Asegura que exista y delegue al módulo UI si está disponible.
  // =========================================================
  window.applyFilters = window.applyFilters || function () {
    try {
      if (window.P360_SAT_UI && typeof window.P360_SAT_UI.applyFilters === 'function') {
        return window.P360_SAT_UI.applyFilters();
      }
    } catch (e) {}
    return null;
  };

  const btnQuickCalc = document.getElementById('btnQuickCalc'); // botón ATAJOS SAT

  // Diagnóstico rápido: si el botón existe pero está disabled, casi seguro faltan rutas en Blade ($qcEnabled=false)
  try {
    if (btnQuickCalc && btnQuickCalc.disabled) {
      console.warn('[SAT-BOOT] btnQuickCalc está disabled. Revisa que rtQuickCalc/rtQuickPdf NO sean null y que existan rutas.', {
        quickCalc: ROUTES.quickCalc,
        quickPdf: ROUTES.quickPdf,
        btnCalcUrl: btnQuickCalc.dataset?.calcUrl,
        btnPdfUrl: btnQuickCalc.dataset?.pdfUrl,
      });
    }
  } catch (e) {}


  function toast(msg, kind='info') {
    try {
      if (window.P360 && typeof window.P360.toast === 'function') {
        if (kind === 'error' && window.P360.toast.error) return window.P360.toast.error(msg);
        if (kind === 'success' && window.P360.toast.success) return window.P360.toast.success(msg);
        return window.P360.toast(msg);
      }
    } catch(e) {}
    alert(msg);
  }

  async function postJson(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload || {}),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) throw new Error(data.msg || data.message || 'Solicitud fallida.');
    return data;
  }

  

    // =========================================================
  // POST multipart (ZIP/FIEL) — necesario para enviar archivos
  // ✅ Sin observers / sin loops pesados
  // ✅ Timeout para evitar que se quede "pensando"
  // =========================================================
  async function postMultipart(url, formData) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 45000); // 45s

    let res;
    try {
      res = await fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': CSRF,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
        signal: ctrl.signal,
      });
    } catch (e) {
      clearTimeout(timer);
      const msg = (e && e.name === 'AbortError')
        ? 'Tiempo de espera agotado al subir el ZIP.'
        : 'Error de red al subir el ZIP.';
      throw new Error(msg);
    }

    clearTimeout(timer);

    const ct = String(res.headers.get('content-type') || '').toLowerCase();
    const isJson = ct.includes('application/json');

    let data = {};
    try {
      data = isJson ? await res.json() : { ok: false, msg: (await res.text()).slice(0, 220) };
    } catch (e) {
      data = {};
    }

    if (!res.ok || data.ok === false) {
      const msg = data.msg || data.message || `Solicitud fallida (${res.status})`;
      throw new Error(msg);
    }

    return data;
  }


  // =========================
  // Bulk selection bar
  // =========================
  const bulkBar   = document.getElementById('satDlBulk');
  const bulkCount = document.getElementById('satDlBulkCount');
  const btnAll    = document.getElementById('satCheckAll');

  const checks = () => Array.from(document.querySelectorAll('.sat-dl-check'));

  function getSelectedIds() {
    return checks().filter(c => c.checked).map(c => (c.value || '').trim()).filter(Boolean);
  }

  function updateBulk() {
    const n = getSelectedIds().length;
    if (!bulkBar) return;
    bulkBar.style.display = n > 0 ? 'flex' : 'none';
    if (bulkCount) bulkCount.textContent = String(n);
  }

  // Bind checks actuales
  checks().forEach(cb => cb.addEventListener('change', updateBulk));

  if (btnAll) {
    btnAll.addEventListener('change', function () {
      const check = this.checked;
      checks().forEach(cb => { cb.checked = check; });
      updateBulk();
    });
  }

  // Bulk buttons
  const btnRefresh = document.getElementById('satDlBulkRefresh');
  const btnAdd     = document.getElementById('satDlAddSelected');
  const btnBuy     = document.getElementById('satDlBulkBuy');
  const btnDelete  = document.getElementById('satDlBulkDelete');

  if (btnRefresh) {
    btnRefresh.addEventListener('click', function () {
      const verifyBtn = document.getElementById('btnSatVerify');
      if (verifyBtn) verifyBtn.click();
    });
  }

  async function addToCart(downloadId) {
    if (!ROUTES.cartAdd) throw new Error('Ruta cartAdd no configurada.');
    return await postJson(ROUTES.cartAdd, { download_id: downloadId, id: downloadId });
  }

  async function removeFromCart(downloadId) {
    if (!ROUTES.cartRemove) throw new Error('Ruta cartRemove no configurada.');
    return await postJson(ROUTES.cartRemove, { download_id: downloadId, id: downloadId });
  }

  async function checkout(ids) {
    if (!ROUTES.cartCheckout) throw new Error('Ruta cartCheckout no configurada.');
    const data = await postJson(ROUTES.cartCheckout, { ids, download_ids: ids });
    const url = data.url || data.checkout_url || data.redirect || null;
    if (!url) throw new Error('Checkout generado, pero sin URL.');
    window.location.href = url;
  }

  if (btnAdd) {
    btnAdd.addEventListener('click', async function () {
      const ids = getSelectedIds();
      if (!ids.length) return toast('Selecciona al menos 1 solicitud.');
      btnAdd.disabled = true;
      try {
        for (const id of ids) await addToCart(id);
        toast('Seleccionados agregados al carrito.', 'success');
      } catch (e) {
        toast(e?.message || 'No se pudo agregar al carrito.', 'error');
      } finally {
        btnAdd.disabled = false;
      }
    });
  }

  if (btnBuy) {
    btnBuy.addEventListener('click', async function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();

      const ids = getSelectedIds();
      if (!ids.length) return toast('Selecciona al menos 1 solicitud para comprar.');

      btnBuy.disabled = true;
      try {
        for (const id of ids) await addToCart(id);
        await checkout(ids);
      } catch (e) {
        toast(e?.message || 'No se pudo iniciar la compra.', 'error');
      } finally {
        btnBuy.disabled = false;
      }
    }, true);
  }

  if (btnDelete) {
    btnDelete.addEventListener('click', function () {
      const selected = checks().filter(c => c.checked);
      selected.forEach(cb => {
        const tr = cb.closest('tr');
        const delBtn = tr ? tr.querySelector('.sat-btn-cancel') : null;
        if (delBtn) delBtn.click();
      });
    });
  }

  // Toggle carrito por fila (fallback)
  document.body.addEventListener('click', async function (ev) {
    const btn = ev.target.closest('.sat-btn-cart');
    if (!btn) return;

    ev.preventDefault();

    const id = (btn.dataset.id || '').trim();
    const action = (btn.dataset.action || 'cart-add').trim();
    if (!id) return;

    btn.disabled = true;
    try {
      if (action === 'cart-remove') {
        await removeFromCart(id);
        btn.dataset.action = 'cart-add';
        btn.classList.remove('is-in-cart');
        btn.setAttribute('data-tip', 'Agregar al carrito');
      } else {
        await addToCart(id);
        btn.dataset.action = 'cart-remove';
        btn.classList.add('is-in-cart');
        btn.setAttribute('data-tip', 'Quitar del carrito');
      }
    } catch (e) {
      toast(e?.message || 'No se pudo actualizar el carrito.', 'error');
    } finally {
      btn.disabled = false;
    }
  }, true);

  // =========================
  // Quick guides
  // =========================
  function ymd(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  function setRange(fromDate, toDate, tipo) {
    const f = document.getElementById('reqFrom');
    const t = document.getElementById('reqTo');
    const tp = document.getElementById('reqTipo');
    if (tp && tipo) tp.value = tipo;
    if (f) f.value = ymd(fromDate);
    if (t) t.value = ymd(toDate);
  }

  const btnLast30 = document.getElementById('btnQuickLast30');
  const btnMonth  = document.getElementById('btnQuickThisMonth');
  const btnEmit   = document.getElementById('btnQuickOnlyEmitted');
  const reqForm   = document.getElementById('reqForm');

  if (btnLast30 && reqForm) {
    btnLast30.addEventListener('click', function () {
      const to = new Date();
      const from = new Date();
      from.setDate(from.getDate() - 30);
      setRange(from, to, 'ambos');
      reqForm.requestSubmit ? reqForm.requestSubmit() : reqForm.submit();
    });
  }

  if (btnMonth && reqForm) {
    btnMonth.addEventListener('click', function () {
      const now = new Date();
      const from = new Date(now.getFullYear(), now.getMonth(), 1);
      const to = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      setRange(from, to, 'ambos');
      reqForm.requestSubmit ? reqForm.requestSubmit() : reqForm.submit();
    });
  }

  if (btnEmit && reqForm) {
    btnEmit.addEventListener('click', function () {
      const to = new Date();
      const from = new Date();
      from.setDate(from.getDate() - 30);
      setRange(from, to, 'emitidos');
      reqForm.requestSubmit ? reqForm.requestSubmit() : reqForm.submit();
    });
  }

  // =========================
  // Vault CTA
  // =========================
  const selectVault = document.getElementById('vaultUpgradeSelect');
  const btnVault    = document.getElementById('btnVaultCtaIndex');

  if (selectVault && btnVault) {
    function hasQuota() { return String(btnVault.dataset.hasQuota || '0') === '1'; }
    function syncLabel() {
      const label = btnVault.querySelector('.btn-amazon-label');
      if (!label) return;
      label.textContent = hasQuota() ? 'Ampliar bóveda' : 'Activar bóveda';
    }
    function canProceed() { return !hasQuota() || !!selectVault.value; }
    function syncDisabled() { btnVault.disabled = !canProceed(); }

    function buildFinalUrl(baseUrl, param, gb) {
      const sep = baseUrl.indexOf('?') === -1 ? '?' : '&';
      return baseUrl + sep + encodeURIComponent(param) + '=' + encodeURIComponent(String(gb));
    }

    selectVault.addEventListener('change', syncDisabled);
    syncLabel();
    syncDisabled();

    btnVault.addEventListener('click', function () {
      const baseUrl = (btnVault.dataset.url || '').trim();
      const param   = (btnVault.dataset.param || 'vault_gb').trim();

      if (!baseUrl || baseUrl === '#') {
        return toast('No hay ruta configurada para activar/comprar bóveda (carrito/checkout).', 'error');
      }

      let gb = selectVault.value;
      if (!gb) {
        if (!hasQuota()) gb = btnVault.dataset.defaultGb || '5';
        else return toast('Selecciona primero una ampliación de Gb para continuar.', 'error');
      }

      window.location.href = buildFinalUrl(baseUrl, param, gb);
    });
  }

  // =========================
  // Vault-from-download
  // =========================
  function buildFromDownloadUrl(id) {
    const tpl = ROUTES.vaultFromDownload;
    if (!tpl) return null;
    return String(tpl).replace('__ID__', encodeURIComponent(id));
  }

  async function refreshVaultQuick() {
    if (!ROUTES.vaultQuick) return;
    try {
      const res = await fetch(ROUTES.vaultQuick, { headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }});
      if (!res.ok) return;
      const data = await res.json().catch(() => ({}));
      if (data && data.vault) {
        CFG.vault = data.vault;
        window.P360_SAT = CFG;
        if (window.P360_SAT_UI && typeof window.P360_SAT_UI.redrawVault === 'function') {
          window.P360_SAT_UI.redrawVault(data.vault);
        }
      }
    } catch(e) {}
  }

  document.body.addEventListener('click', async function (ev) {
    const btn = ev.target.closest('.sat-btn-vault');
    if (!btn) return;

    ev.preventDefault();

    const id = (btn.dataset.id || '').trim();
    const url = buildFromDownloadUrl(id);
    if (!id || !url) return toast('No se pudo construir la ruta para guardar en bóveda.', 'error');

    btn.disabled = true;
    btn.classList.add('is-loading');

    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({}),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) {
        return toast(data.message || data.msg || 'No se pudo guardar en bóveda. Revisa el backend.', 'error');
      }

      btn.remove();
      toast('Guardado en Bóveda Fiscal.', 'success');
      await refreshVaultQuick();
    } catch (e) {
      toast('Error de red al guardar en bóveda.', 'error');
    } finally {
      btn.disabled = false;
      btn.classList.remove('is-loading');
    }
  }, true);

  // =========================
  // Calculadora rápida (Guías rápidas) · SIN RFC
  // =========================
  const modalQuickCalc = document.getElementById('modalQuickCalc');

  const qcTipo      = document.getElementById('qcTipo');
  const qcXmlCount  = document.getElementById('qcXmlCount');
  const qcDiscount  = document.getElementById('qcDiscountCode');
  const qcIva       = document.getElementById('qcIva');

  const qcBaseVal     = document.getElementById('qcBaseVal');
  const qcDiscPct     = document.getElementById('qcDiscPct');
  const qcDiscVal     = document.getElementById('qcDiscVal');
  const qcSubtotalVal = document.getElementById('qcSubtotalVal');
  const qcIvaPct      = document.getElementById('qcIvaPct');
  const qcIvaVal      = document.getElementById('qcIvaVal');
  const qcTotalVal    = document.getElementById('qcTotalVal');
  const qcNote        = document.getElementById('qcNote');

  const btnQcRecalc = document.getElementById('btnQcRecalc');
  const btnQcPdf    = document.getElementById('btnQcPdf');

  let qcTimer = null;

  function qcMoney(n) {
    const v = Number(n || 0);
    return '$' + v.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function qcRender(d) {
    if (!d) return;
    if (qcBaseVal)     qcBaseVal.textContent     = qcMoney(d.base);
    if (qcDiscPct)     qcDiscPct.textContent     = String((d.discount_pct ?? 0));
    if (qcDiscVal)     qcDiscVal.textContent     = '-' + qcMoney(d.discount_amount || 0);
    if (qcSubtotalVal) qcSubtotalVal.textContent = qcMoney(d.subtotal);
    if (qcIvaPct)      qcIvaPct.textContent      = String(d.iva_rate ?? (parseInt(qcIva?.value || '16',10)||16));
    if (qcIvaVal)      qcIvaVal.textContent      = qcMoney(d.iva_amount || 0);
    if (qcTotalVal)    qcTotalVal.textContent    = qcMoney(d.total || 0);
    if (qcNote)        qcNote.textContent        = d.note || '—';
  }

  async function qcCalcNow() {
    if (!ROUTES.quickCalc) throw new Error('Ruta quickCalc no configurada (cliente.sat.quick.calc).');

    const payload = {
      tipo: String(qcTipo?.value || 'ambos').trim(),
      xml_count: parseInt((qcXmlCount?.value || '0'), 10) || 0,
      iva: parseInt((qcIva?.value || '16'), 10) || 16,
      discount_code: String(qcDiscount?.value || '').trim(),
    };

    if (payload.xml_count <= 0) throw new Error('Escribe una cantidad válida de XML.');

    const res = await postJson(ROUTES.quickCalc, payload);
    const d = res.data || null;
    if (!d) throw new Error('Respuesta de cálculo inválida.');

    qcRender(d);
    return d;
  }

  function qcSchedule(ms=250) {
    clearTimeout(qcTimer);
    qcTimer = setTimeout(async () => {
      try {
        if (!modalQuickCalc || modalQuickCalc.style.display === 'none') return;
        await qcCalcNow();
      } catch (e) {}
    }, ms);
  }

  function openQuickCalc() {
    if (!modalQuickCalc) return toast('Modal de calculadora rápida no encontrado (modalQuickCalc).', 'error');

    if (!ROUTES.quickCalc && !ROUTES.quickPdf) {
      toast('Calculadora rápida no configurada: faltan rutas quickCalc/quickPdf.', 'error');
      return;
    }

    modalQuickCalc.style.display = 'flex';

    try {
      if (qcXmlCount && !qcXmlCount.value) qcXmlCount.value = '1000';
      if (qcIva && qcIvaPct) qcIvaPct.textContent = qcIva.value || '16';
    } catch (e) {}

    qcSchedule(60);
  }

  function closeQuickCalc() {
    if (!modalQuickCalc) return;
    modalQuickCalc.style.display = 'none';
  }

  // Abrir QuickCalc por ID (compat)
  if (btnQuickCalc) {
    btnQuickCalc.addEventListener('click', function (ev) {
      ev.preventDefault();
      openQuickCalc();
    });
  }

  // Abrir modales por data-open (SOT)
  // Permite que cualquier botón/elemento con data-open funcione sin depender del ID.
  document.body.addEventListener('click', function (ev) {
    const opener = ev.target.closest('[data-open]');
    if (!opener) return;

    const key = String(opener.getAttribute('data-open') || '').trim();
    if (!key) return;

    // Normalizamos: aceptamos camelCase o kebab-case
    // QuickCalc
    const isQuickCalc =
      key === 'modalQuickCalc' || key === 'modal-quick-calc' || key === 'modalQuick' || key === 'quick-calc';

    // Quote
    const isQuote =
      key === 'modalQuote' || key === 'modal-quote' || key === 'quote';

    // Manual
    const isManual =
      key === 'modalManualRequest' || key === 'modal-manual' || key === 'manual';

    if (!isQuickCalc && !isQuote && !isManual) return;

    ev.preventDefault();
    ev.stopPropagation();
    if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();

    try {
      if (isQuickCalc) return openQuickCalc();
      if (isQuote) return openQuote();
      if (isManual) return openManualModal();
    } catch (e) {
      // no revienta
    }
  }, true);


  document.body.addEventListener('click', function (ev) {
    const b = ev.target.closest('[data-close="modal-quick-calc"]');
    if (!b) return;
    ev.preventDefault();
    closeQuickCalc();
  }, true);

  if (modalQuickCalc) {
    modalQuickCalc.addEventListener('click', function (ev) {
      if (ev.target === modalQuickCalc) closeQuickCalc();
    });
  }

  if (qcXmlCount) qcXmlCount.addEventListener('input', () => qcSchedule(250));
  if (qcDiscount) qcDiscount.addEventListener('input', () => qcSchedule(350));
  if (qcTipo) qcTipo.addEventListener('change', () => qcSchedule(120));
  if (qcIva) {
    qcIva.addEventListener('change', () => {
      if (qcIvaPct) qcIvaPct.textContent = qcIva.value || '16';
      qcSchedule(120);
    });
  }

  if (btnQcRecalc) {
    btnQcRecalc.addEventListener('click', async function () {
      btnQcRecalc.disabled = true;
      try {
        await qcCalcNow();
        toast('Cálculo actualizado.', 'success');
      } catch (e) {
        toast(e?.message || 'No se pudo recalcular.', 'error');
      } finally {
        btnQcRecalc.disabled = false;
      }
    });
  }

  function submitPdfForm(url, params) {
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = url;
    f.style.display = 'none';

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = CSRF;
    f.appendChild(token);

    Object.keys(params || {}).forEach((k) => {
      const v = params[k];
      if (v === null || typeof v === 'undefined' || v === '') return;
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = k;
      inp.value = String(v);
      f.appendChild(inp);
    });

    document.body.appendChild(f);
    f.submit();
    setTimeout(() => f.remove(), 3000);
  }

  if (btnQcPdf) {
    btnQcPdf.addEventListener('click', async function () {
      if (!ROUTES.quickPdf) return toast('Ruta quickPdf no configurada (cliente.sat.quick.pdf).', 'error');

      btnQcPdf.disabled = true;
      try {
        await qcCalcNow();

        const p = {
          tipo: String(qcTipo?.value || 'ambos').trim(),
          xml_count: parseInt((qcXmlCount?.value || '0'), 10) || 0,
          iva: parseInt((qcIva?.value || '16'), 10) || 16,
          discount_code: String(qcDiscount?.value || '').trim(),
        };

        submitPdfForm(ROUTES.quickPdf, p);
        toast('Generando PDF…', 'success');
      } catch (e) {
        toast(e?.message || 'No se pudo generar el PDF.', 'error');
      } finally {
        btnQcPdf.disabled = false;
      }
    });
  }

  // =========================
  // Cotizador (Calcular + PDF)  [RFC + periodo]
  // =========================
  const btnQuickQuote  = document.getElementById('btnQuickQuote'); // si existe
  const modalQuote     = document.getElementById('modalQuote');

  const btnQuoteRecalc = document.getElementById('btnQuoteRecalc');
  const btnQuotePdf    = document.getElementById('btnQuotePdf');

  const quoteRfc      = document.getElementById('quoteRfc');
  const quoteTipo     = document.getElementById('quoteTipo');
  const quoteFrom     = document.getElementById('quoteFrom');
  const quoteTo       = document.getElementById('quoteTo');

  const quoteXmlCount = document.getElementById('quoteXmlCount');
  const quoteDiscount = document.getElementById('quoteDiscountCode');
  const quoteIva      = document.getElementById('quoteIva');

  const quoteBaseVal     = document.getElementById('quoteBaseVal');
  const quoteDiscPct     = document.getElementById('quoteDiscPct');
  const quoteDiscVal     = document.getElementById('quoteDiscVal');
  const quoteSubtotalVal = document.getElementById('quoteSubtotalVal');
  const quoteIvaPct      = document.getElementById('quoteIvaPct');
  const quoteIvaVal      = document.getElementById('quoteIvaVal');
  const quoteTotalVal    = document.getElementById('quoteTotalVal');
  const quoteNote        = document.getElementById('quoteNote');

  let quoteTimer = null;

  function money(n) {
    const v = Number(n || 0);
    return '$' + v.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function renderQuote(d) {
    if (!d) return;
    if (quoteBaseVal)     quoteBaseVal.textContent     = money(d.base);
    if (quoteDiscPct)     quoteDiscPct.textContent     = String((d.discount_pct ?? 0));
    if (quoteDiscVal)     quoteDiscVal.textContent     = '-' + money(d.discount_amount || 0);
    if (quoteSubtotalVal) quoteSubtotalVal.textContent = money(d.subtotal);
    if (quoteIvaPct)      quoteIvaPct.textContent      = String(d.iva_rate ?? 16);
    if (quoteIvaVal)      quoteIvaVal.textContent      = money(d.iva_amount || 0);
    if (quoteTotalVal)    quoteTotalVal.textContent    = money(d.total || 0);
    if (quoteNote)        quoteNote.textContent        = d.note || '—';
  }

  async function quoteCalcNow() {
    if (!ROUTES.quoteCalc) throw new Error('Ruta quoteCalc no configurada (cliente.sat.quote.calc).');

    const rfc  = String(quoteRfc?.value || '').trim();
    const tipo = String(quoteTipo?.value || 'ambos').trim();
    const from = String(quoteFrom?.value || '').trim();
    const to   = String(quoteTo?.value || '').trim();

    if (!rfc) throw new Error('Selecciona un RFC válido para cotizar.');
    if (!from || !to) throw new Error('Selecciona un periodo (desde/hasta) para cotizar.');

    const xmlCount = parseInt((quoteXmlCount?.value || '0'), 10) || 0;
    if (xmlCount <= 0) throw new Error('Escribe una cantidad válida de XML.');

    const payload = {
      rfc,
      tipo,
      from,
      to,
      xml_count: xmlCount,
      iva: parseInt((quoteIva?.value || '16'), 10) || 16,
      discount_code: String(quoteDiscount?.value || '').trim(),
    };

    const res = await postJson(ROUTES.quoteCalc, payload);
    const d = res.data || null;
    if (!d) throw new Error('Respuesta de cotización inválida.');

    renderQuote(d);
    return d;
  }

  function scheduleQuoteCalc(ms = 250) {
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(async () => {
      try {
        if (!modalQuote || modalQuote.style.display === 'none') return;
        await quoteCalcNow();
      } catch (e) {}
    }, ms);
  }

  function openQuote() {
    if (!modalQuote) return toast('Modal cotizador no encontrado (modalQuote).', 'error');

    modalQuote.style.display = 'flex';

    try {
      const toD = new Date();
      const fromD = new Date();
      fromD.setDate(fromD.getDate() - 30);

      if (quoteFrom && !quoteFrom.value) quoteFrom.value = ymd(fromD);
      if (quoteTo && !quoteTo.value) quoteTo.value = ymd(toD);
      if (quoteTipo && !quoteTipo.value) quoteTipo.value = 'ambos';

      if (quoteRfc && !quoteRfc.value) {
        const firstOpt = Array.from(quoteRfc.options || []).find(o => o.value && String(o.value).trim() !== '');
        if (firstOpt) quoteRfc.value = firstOpt.value;
      }

      if (quoteXmlCount && !quoteXmlCount.value) quoteXmlCount.value = '1000';
      if (quoteIva && quoteIvaPct) quoteIvaPct.textContent = quoteIva.value || '16';
    } catch (e) {}

    const canCalc = !!ROUTES.quoteCalc;
    const canPdf  = !!ROUTES.quotePdf;

    if (btnQuoteRecalc) btnQuoteRecalc.disabled = !canCalc;
    if (btnQuotePdf)    btnQuotePdf.disabled    = !canPdf;

    if (!canCalc && !canPdf) {
      toast('Cotizador no configurado: faltan rutas quoteCalc/quotePdf.', 'error');
      return;
    }

    if (canCalc) scheduleQuoteCalc(80);
    if (!canCalc) toast('Falta ruta quoteCalc: no se puede recalcular automáticamente.', 'error');
  }

  function closeQuote() {
    if (!modalQuote) return;
    modalQuote.style.display = 'none';
  }

  if (btnQuickQuote) btnQuickQuote.addEventListener('click', openQuote);

  document.body.addEventListener('click', function (ev) {
    const b = ev.target.closest('[data-close="modal-quote"]');
    if (!b) return;
    ev.preventDefault();
    closeQuote();
  }, true);

  if (modalQuote) {
    modalQuote.addEventListener('click', function (ev) {
      if (ev.target === modalQuote) closeQuote();
    });
  }

  if (quoteXmlCount) quoteXmlCount.addEventListener('input', () => scheduleQuoteCalc(250));
  if (quoteDiscount) quoteDiscount.addEventListener('input', () => scheduleQuoteCalc(350));
  if (quoteRfc)  quoteRfc.addEventListener('change', () => scheduleQuoteCalc(120));
  if (quoteTipo) quoteTipo.addEventListener('change', () => scheduleQuoteCalc(120));
  if (quoteFrom) quoteFrom.addEventListener('change', () => scheduleQuoteCalc(120));
  if (quoteTo)   quoteTo.addEventListener('change', () => scheduleQuoteCalc(120));

  if (quoteIva) {
    quoteIva.addEventListener('change', () => {
      if (quoteIvaPct) quoteIvaPct.textContent = quoteIva.value || '16';
      scheduleQuoteCalc(120);
    });
  }

  if (btnQuoteRecalc) {
    btnQuoteRecalc.addEventListener('click', async function () {
      if (!ROUTES.quoteCalc) return toast('Ruta quoteCalc no configurada.', 'error');

      btnQuoteRecalc.disabled = true;
      try {
        await quoteCalcNow();
        toast('Cotización actualizada.', 'success');
      } catch (e) {
        toast(e?.message || 'No se pudo recalcular.', 'error');
      } finally {
        btnQuoteRecalc.disabled = false;
      }
    });
  }

  if (btnQuotePdf) {
    btnQuotePdf.addEventListener('click', async function () {
      if (!ROUTES.quotePdf) return toast('Ruta quotePdf no configurada (cliente.sat.quote.pdf).', 'error');

      btnQuotePdf.disabled = true;
      try {
        if (ROUTES.quoteCalc) await quoteCalcNow();

        const p = {
          rfc:  String(quoteRfc?.value || '').trim(),
          tipo: String(quoteTipo?.value || 'ambos').trim(),
          from: String(quoteFrom?.value || '').trim(),
          to:   String(quoteTo?.value || '').trim(),
          xml_count: parseInt(quoteXmlCount?.value || '0', 10) || 0,
          iva: parseInt(quoteIva?.value || '16', 10) || 16,
          discount_code: String(quoteDiscount?.value || '').trim(),
        };

        if (!p.rfc) throw new Error('Selecciona un RFC válido.');
        if (!p.from || !p.to) throw new Error('Selecciona un periodo (desde/hasta).');
        if (p.xml_count <= 0) throw new Error('Escribe una cantidad estimada válida.');

        submitPdfForm(ROUTES.quotePdf, p);
        toast('Generando PDF…', 'success');
      } catch (e) {
        toast(e?.message || 'No se pudo generar el PDF.', 'error');
      } finally {
        btnQuotePdf.disabled = false;
      }
    });
  }

  // =========================
  // Descargas manuales (FLUJO SEPARADO)
  // =========================
  const btnManualStartProcess = document.getElementById('btnManualStartProcess');
  const btnManualGoIndex      = document.getElementById('btnManualGoIndex');
  const btnManualOpenQuote    = document.getElementById('btnManualOpenQuote');

  const modalManual = document.getElementById('modalManualRequest');

  const manualRfc          = document.getElementById('manualRfc');
  const manualTipo         = document.getElementById('manualTipo');
  const manualFrom         = document.getElementById('manualFrom');
  const manualTo           = document.getElementById('manualTo');
  const manualXmlEstimated = document.getElementById('manualXmlEstimated');
  const manualRef          = document.getElementById('manualRef');
  const manualNotes        = document.getElementById('manualNotes');

  const btnManualDraftToQuote = document.getElementById('btnManualDraftToQuote');
  const btnManualSubmitPay    = document.getElementById('btnManualSubmitPay');

  function openManualModal() {
    if (!modalManual) return toast('Modal de solicitud manual no encontrado (modalManualRequest).', 'error');
    if (!ROUTES.manualQuote) return toast('Ruta manualQuote no configurada (cliente.sat.manual.quote).', 'error');

    modalManual.style.display = 'flex';

    try {
      const to = new Date();
      const from = new Date();
      from.setDate(from.getDate() - 30);

      if (manualFrom && !manualFrom.value) manualFrom.value = ymd(from);
      if (manualTo && !manualTo.value) manualTo.value = ymd(to);
      if (manualTipo && !manualTipo.value) manualTipo.value = 'ambos';
      if (manualXmlEstimated && !manualXmlEstimated.value) manualXmlEstimated.value = '1000';
    } catch(e) {}
  }

  function closeManualModal() {
    if (!modalManual) return;
    modalManual.style.display = 'none';
  }

  if (btnManualStartProcess) {
    btnManualStartProcess.addEventListener('click', function () {
      openManualModal();
    });
  }

  document.body.addEventListener('click', function (ev) {
    const b = ev.target.closest('[data-close="modal-manual"]');
    if (!b) return;
    ev.preventDefault();
    closeManualModal();
  }, true);

  if (modalManual) {
    modalManual.addEventListener('click', function (ev) {
      if (ev.target === modalManual) closeManualModal();
    });
  }

  function goUrl(btn) {
    const url = (btn?.dataset?.url || '').trim();
    if (!url) return toast('Módulo no configurado (ruta faltante).', 'error');
    window.location.href = url;
  }

  if (btnManualGoIndex) {
    btnManualGoIndex.addEventListener('click', function () {
      goUrl(btnManualGoIndex);
    });
  }

  if (btnManualOpenQuote) {
    btnManualOpenQuote.addEventListener('click', function () {
      try {
        const b = document.getElementById('btnQuickCalc');
        if (b) return b.click();

        const mq = document.getElementById('modalQuote');
        if (mq) { mq.style.display = 'flex'; return; }

        return toast('No se encontró el cotizador (btnQuickCalc/modalQuote).', 'error');
      } catch (e) {
        return toast('No se pudo abrir el cotizador.', 'error');
      }
    });
  }

  if (btnManualDraftToQuote) {
    btnManualDraftToQuote.addEventListener('click', function () {
      try {
        if (quoteXmlCount && manualXmlEstimated && manualXmlEstimated.value) {
          quoteXmlCount.value = String(manualXmlEstimated.value || '1000');
        }
        const b = document.getElementById('btnQuickCalc');
        if (b) b.click();
      } catch(e) {
        toast('No se pudo abrir el cotizador.', 'error');
      }
    });
  }

  if (btnManualSubmitPay) {
    btnManualSubmitPay.addEventListener('click', async function () {
      if (!ROUTES.manualQuote) return toast('Ruta manualQuote no configurada.', 'error');

      const payload = {
        rfc: (manualRfc?.value || '').trim(),
        tipo: (manualTipo?.value || 'ambos').trim(),
        from: (manualFrom?.value || '').trim(),
        to: (manualTo?.value || '').trim(),
        xml_estimated: parseInt(manualXmlEstimated?.value || '0', 10) || 0,
        ref: (manualRef?.value || '').trim(),
        notes: (manualNotes?.value || '').trim(),
      };

      if (!payload.rfc) return toast('Selecciona un RFC válido.', 'error');
      if (!payload.from || !payload.to) return toast('Selecciona un periodo (desde/hasta).', 'error');
      if (payload.xml_estimated <= 0) return toast('Escribe una cantidad estimada de XML válida.', 'error');

      btnManualSubmitPay.disabled = true;
      try {
        const res = await postJson(ROUTES.manualQuote, payload);
        const url =
          res.url || res.checkout_url || res.redirect ||
          (res.data && (res.data.url || res.data.checkout_url || res.data.redirect)) ||
          null;

        if (!url) {
          toast(res.msg || res.message || 'Solicitud creada, pero sin URL de pago.', 'success');
          closeManualModal();
          if (ROUTES.manualIndex) window.location.href = ROUTES.manualIndex;
          return;
        }

        window.location.href = url;
      } catch (e) {
        toast(e?.message || 'No se pudo iniciar el pago/manualQuote.', 'error');
      } finally {
        btnManualSubmitPay.disabled = false;
      }
    });
  }

  // =========================
  // Registro externo (invite) — FIX: forzar POST (evita GET 405/419)
  // Soporta botones en partial RFCs sin saber su markup exacto:
  // - #btnExternalInvite
  // - .btn-external-invite
  // - [data-external-invite]
  // - <a href=".../external/invite">
  // =========================
  function isExternalInviteHref(el) {
    try {
      if (!el) return false;
      if (!ROUTES.externalInvite) return false;
      const href = (el.getAttribute && el.getAttribute('href')) ? String(el.getAttribute('href')) : '';
      if (!href) return false;
      return href.includes('/external/invite') || href === String(ROUTES.externalInvite);
    } catch (e) { return false; }
  }

  async function runExternalInvite(triggerEl) {
    if (!ROUTES.externalInvite) {
      toast('Ruta externalInvite no configurada (cliente.sat.external.invite).', 'error');
      return;
    }

    const ds = triggerEl?.dataset || {};
    const rfc    = String(ds.rfc || ds.rf || ds.value || '').trim();
    const id     = String(ds.id || ds.credId || ds.credentialId || '').trim();
    const credId = String(ds.cred_id || ds.credid || '').trim();

    const payload = {
      rfc: rfc || undefined,
      id: id || undefined,
      cred_id: credId || undefined,
    };

    Object.keys(payload).forEach(k => (payload[k] === undefined) && delete payload[k]);

    try {
      const res = await postJson(ROUTES.externalInvite, payload);

      const url =
        res.url || res.redirect || res.checkout_url ||
        (res.data && (res.data.url || res.data.redirect || res.data.checkout_url)) ||
        null;

      toast(res.msg || res.message || 'Invitación externa enviada.', 'success');

      if (url) {
        window.location.href = url;
        return;
      }

      setTimeout(() => window.location.reload(), 700);
    } catch (e) {
      toast(e?.message || 'No se pudo enviar la invitación externa.', 'error');
    }
  }

  document.body.addEventListener('click', function (ev) {
    const btn =
      ev.target.closest('#btnExternalInvite') ||
      ev.target.closest('.btn-external-invite') ||
      ev.target.closest('[data-external-invite]');

    if (btn) {
      ev.preventDefault();
      ev.stopPropagation();
      if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
      runExternalInvite(btn);
      return;
    }

    const a = ev.target.closest('a[href]');
    if (a && isExternalInviteHref(a)) {
      ev.preventDefault();
      ev.stopPropagation();
      if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
      runExternalInvite(a);
      return;
    }
  }, true);

  // =========================
  // Modal RFC (OPEN + Prefill + Scroll to row)
  // - Abre por: data-open="modal-rfc" | #btnAddRfc | #btnExternalPrefillAdd
  // - Prefill RFC por: data-rfc="AAA010101AAA"
  // - Abrir en tabla por: #btnExternalOpenInTable
  // =========================
  const modalRfc = document.getElementById('modalRfc');

  function openRfcModal(prefillRfc) {
    if (!modalRfc) {
      toast('Modal RFC no encontrado (modalRfc).', 'error');
      return false;
    }

    // IMPORTANTE: tu modal trae style="display:none;"
    modalRfc.style.display = 'flex';
    modalRfc.classList.add('show', 'is-open', 'open');
    document.body.classList.add('modal-open');

    try {
      const input = modalRfc.querySelector('input[name="rfc"]');
      if (input && prefillRfc) {
        input.value = String(prefillRfc).trim().toUpperCase();
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (input) {
        setTimeout(() => { try { input.focus(); input.select(); } catch(e){} }, 60);
      }
    } catch (e) {}

    return true;
  }

    // =========================================================
  // SOT Guard: Validar RFC
  // - Si RFC YA tiene CSD (data-has-csd="1"), NO intercepta
  // - Si NO tiene CSD y NO hay .cer/.key seleccionados, bloquea y abre #modalRfc con prefill
  // - Evita POST vacío (405/419) y evita doble-binding
  // =========================================================
  (function bindValidateRfcGuardSot(){
    if (window.__P360_SAT_VALIDATE_GUARD_SOT__) return;
    window.__P360_SAT_VALIDATE_GUARD_SOT__ = true;

    function detectRfcFromText(txt) {
      try {
        const s = String(txt || '').toUpperCase();
        const m = s.match(/\b([A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3})\b/);
        return m ? m[1] : '';
      } catch (_) { return ''; }
    }

    function getHasCsdFromContext(el) {
      try {
        // 1) Fila RFCs v48
        const row = el?.closest?.('tr.rfcs-v48-row');
        if (row?.dataset && typeof row.dataset.hasCsd !== 'undefined') {
          return String(row.dataset.hasCsd) === '1';
        }
        // 2) Form/panel
        const form = el?.closest?.('form.js-csd-form');
        if (form?.dataset && typeof form.dataset.hasCsd !== 'undefined') {
          return String(form.dataset.hasCsd) === '1';
        }
      } catch (_) {}
      return false;
    }

    function modalHasSelectedCsdFiles() {
      try {
        const modal = document.getElementById('modalRfc');
        if (!modal) return false;
        const cer = modal.querySelector('input[type="file"][name="cer"]');
        const key = modal.querySelector('input[type="file"][name="key"]');
        const hasCer = !!(cer && cer.files && cer.files.length > 0);
        const hasKey = !!(key && key.files && key.files.length > 0);
        return hasCer && hasKey;
      } catch (_) { return false; }
    }

    function looksLikeValidateButton(el) {
      if (!el) return false;
      try {
        // no interceptar dentro del modal RFC
        if (el.closest && el.closest('#modalRfc')) return false;

        const clickable = el.closest('button, a, [role="button"]') || el;

        // no tocar verificar descargas
        if (clickable.id === 'btnSatVerify') return false;

        const tip   = String(clickable.getAttribute('data-tip') || '').toLowerCase();
        const title = String(clickable.getAttribute('title') || '').toLowerCase();
        const cls   = String(clickable.className || '').toLowerCase();
        const txt   = String(clickable.textContent || '').toLowerCase();

        const isValidate =
          tip.includes('valid') ||
          title.includes('valid') ||
          txt.includes('valid') ||
          txt.includes('🛡') ||
          cls.includes('valid') ||
          cls.includes('shield');

        const isVerifyDownloads =
          tip.includes('verificar estado de solicitudes') ||
          title.includes('verificar estado de solicitudes');

        return isValidate && !isVerifyDownloads;
      } catch (_) { return false; }
    }

    // capture: ganarle a handlers legacy
    document.addEventListener('click', function (ev) {
      const clickable = ev.target?.closest?.('button, a, [role="button"]');
      if (!clickable) return;

      if (!looksLikeValidateButton(clickable)) return;

      // si YA tiene CSD, NO bloquear (deja validar normal)
      if (getHasCsdFromContext(clickable)) return;

      // si el usuario ya seleccionó .cer/.key en el modal, NO estorbar
      if (modalHasSelectedCsdFiles()) return;

      // bloquear POST vacío y abrir modal con prefill
      ev.preventDefault();
      ev.stopPropagation();
      if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();

      const context =
        clickable.closest('tr') ||
        clickable.closest('.sat-card') ||
        clickable.closest('.rfcs-v48') ||
        clickable.parentElement;

      const rfc = detectRfcFromText(context ? context.textContent : clickable.textContent);

      toast('Para validar SAT primero carga los archivos CSD: .cer y .key (y la contraseña).', 'error');
      openRfcModal(rfc);
    }, true);
  })();


  function scrollToRfcRowAndTryOpenPanel(rfc) {
  rfc = String(rfc || '').trim().toUpperCase();
  if (!rfc) return false;

  // Busca cualquier TR que contenga el RFC
  let row = null;
  try {
    const trs = Array.from(document.querySelectorAll('tr'));
    row = trs.find(tr => ((tr.textContent || '').toUpperCase().includes(rfc))) || null;
  } catch (e) {}

  // Fallback: scroll a conexiones
  if (!row) {
    const block = document.getElementById('block-connections-section') || document.getElementById('block-connections');
    if (block) block.scrollIntoView({ behavior: 'smooth', block: 'start' });
    toast('No se encontró el RFC en la tabla. Te llevé a Conexiones.', 'info');
    return false;
  }

  try { row.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}

  // SOT: el panel RFC v48 se abre SIEMPRE por [data-open-panel]
  let opener = null;
  try {
    opener = row.querySelector('[data-open-panel]'); // 🛡️
    if (!opener) {
      // fallback: por si el botón no está dentro del TR (raro), buscar en el bloque rfcs-v48 cercano
      const wrap = row.closest('.rfcs-v48') || document.querySelector('.rfcs-v48');
      if (wrap) opener = wrap.querySelector('[data-open-panel]');
    }
  } catch (e) {}

  if (!opener) {
    toast('No encontré el botón de panel (data-open-panel) para ese RFC.', 'error');
    return false;
  }

  setTimeout(() => { try { opener.click(); } catch(e){} }, 220);
  return true;
}


  // Delegación SOT para abrir modal RFC / Prefill / Abrir en tabla
  document.body.addEventListener('click', function (ev) {
    // Abrir modal RFC: botones que traen data-open="modal-rfc" o id btnAddRfc
    const openBtn = ev.target.closest('[data-open="modal-rfc"], #btnAddRfc');
    if (openBtn) {
      ev.preventDefault();
      ev.stopPropagation();
      if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();

      const rfc = String(openBtn.getAttribute('data-rfc') || openBtn.dataset?.rfc || '').trim();
      openRfcModal(rfc);
      return;
    }

    // RFC externo → Prefill en modal Agregar RFC
    const prefillBtn = ev.target.closest('#btnExternalPrefillAdd, [data-action="prefill-rfc"]');
    if (prefillBtn) {
      ev.preventDefault();
      ev.stopPropagation();
      if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();

      const rfc = String(prefillBtn.getAttribute('data-rfc') || prefillBtn.dataset?.rfc || '').trim();
      openRfcModal(rfc);
      return;
    }

    // RFC externo → Abrir en tabla
    const openInTableBtn = ev.target.closest('#btnExternalOpenInTable, [data-action="open-rfc-table"]');
    if (openInTableBtn) {
      ev.preventDefault();
      ev.stopPropagation();
      if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();

      const rfc = String(openInTableBtn.getAttribute('data-rfc') || openInTableBtn.dataset?.rfc || '').trim();
      scrollToRfcRowAndTryOpenPanel(rfc);
      return;
    }
  }, true);



  // =========================
  // Modal RFC (solo este modal) · FIX sin romper botones/gráficas
  // - Cierra con X / Cancelar / backdrop / ESC
  // - NO intercepta otros clicks globales
  // =========================

  function isVisible(el) {
    if (!el) return false;
    const st = window.getComputedStyle(el);
    return st.display !== 'none' && st.visibility !== 'hidden' && st.opacity !== '0';
  }

  function closeRfcModal() {
    if (!modalRfc) return;
    modalRfc.style.display = 'none';
    modalRfc.classList.remove('show', 'is-open', 'open');
    document.body.classList.remove('modal-open');
  }

  // 1) Botones de cerrar del modal RFC (X y Cancelar)
  // En tu Blade usas: data-close="modal-rfc"
  document.body.addEventListener('click', function (ev) {
    const btn = ev.target.closest('[data-close="modal-rfc"]');
    if (!btn) return;

    // Importante: NO usamos stopPropagation/stopImmediatePropagation
    // para no romper otros handlers globales.
    ev.preventDefault();
    closeRfcModal();
  }, true);

  // 2) Click en backdrop (solo si clickeas exactamente el overlay)
  if (modalRfc) {
    modalRfc.addEventListener('click', function (ev) {
      if (ev.target === modalRfc) closeRfcModal();
    });
  }

  // 3) ESC cierra solo si modalRfc está visible
  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') return;
    if (modalRfc && isVisible(modalRfc)) closeRfcModal();
  });

    // =========================================================
  // MODAL "Registro externo por ZIP" — botón "Subir FIEL" + submit real
  // ✅ Sin MutationObserver
  // ✅ Solo actúa cuando haces click en el botón dentro del modal
  // =========================================================
  (function bindExternalZipRegisterModalSafe(){
    if (window.__P360_SAT_ZIP_MODAL_SAFE__) return;
    window.__P360_SAT_ZIP_MODAL_SAFE__ = true;

    function findZipModal() {
      const candidates = Array.from(document.querySelectorAll('.modal,[role="dialog"],.p360-modal'));
      // heurística por título/texto visible
      return candidates.find(el => String(el.textContent || '').includes('Registro externo por ZIP')) || null;
    }

    function renameButtonInModal(modal) {
      try {
        const btns = Array.from(modal.querySelectorAll('button'));
        btns.forEach(b => {
          const t = String(b.textContent || '').trim().toLowerCase();
          if (t === 'enviar registro' || (t.includes('enviar') && t.includes('registro'))) {
            b.textContent = 'Subir FIEL';
            b.setAttribute('data-action', 'external-zip-submit');
          }
        });
      } catch(e) {}
    }

    function buildFormData(modal, form) {
      const fd = new FormData(form || undefined);

      const pick = (sel) => modal.querySelector(sel);

      const rfcInp =
        pick('input[name="rfc"]') ||
        pick('input[name="rfc_externo"]') ||
        pick('input[id*="rfc"]');

      const refInp =
        pick('input[name="ref"]') ||
        pick('input[name="referencia"]') ||
        pick('input[id*="ref"]');

      const zipInp =
        pick('input[type="file"][name="zip"]') ||
        pick('input[type="file"][name="archivo_zip"]') ||
        pick('input[type="file"]');

      const passInp =
        pick('input[type="password"][name="fiel_pass"]') ||
        pick('input[type="password"][name="password"]') ||
        pick('input[type="password"]');

      const notesInp =
        pick('textarea[name="notes"]') ||
        pick('textarea[name="nota"]') ||
        pick('textarea');

      const rfc = String(rfcInp?.value || '').trim().toUpperCase();
      const ref = String(refInp?.value || '').trim();
      const pass= String(passInp?.value || '').trim();
      const notes=String(notesInp?.value || '').trim();

      if (rfc && !fd.has('rfc')) fd.set('rfc', rfc);
      if (ref && !fd.has('ref')) fd.set('ref', ref);
      if (notes && !fd.has('notes')) fd.set('notes', notes);

      // El backend puede esperar fiel_pass o password; mandamos fiel_pass si no existe
      if (pass && !fd.has('fiel_pass') && !fd.has('password')) fd.set('fiel_pass', pass);

      let hasZip = false;
      if (zipInp && zipInp.files && zipInp.files.length) {
        const file = zipInp.files[0];
        hasZip = true;
        if (!fd.has('zip') && !fd.has('archivo_zip')) fd.set('zip', file);
      }

      return { fd, rfc, pass, hasZip };
    }

    document.addEventListener('click', async function (ev) {
      const modal = findZipModal();
      if (!modal) return;

      // Renombrar siempre que exista el modal (solo adentro del modal)
      renameButtonInModal(modal);

      const btn = ev.target.closest('button');
      if (!btn || !modal.contains(btn)) return;

      const txt = String(btn.textContent || '').trim().toLowerCase();
      const isTarget =
        btn.getAttribute('data-action') === 'external-zip-submit' ||
        txt === 'enviar registro' ||
        (txt.includes('enviar') && txt.includes('registro')) ||
        txt.includes('subir fiel');

      if (!isTarget) return;

      ev.preventDefault();
      ev.stopPropagation();
      if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();

      const form = btn.closest('form') || modal.querySelector('form') || null;

      // ✅ IMPORTANTE:
      // externalZipRegister = GET (form/pantalla)
      // externalZipRegisterPost = POST real (subida)
      const formAction = (form && String(form.getAttribute('action') || '').trim()) || '';
      const actionPost = String(ROUTES.externalZipRegisterPost || '').trim();

      // Si el form trae action, úsalo SOLO si parece POST real; si no, forzamos el POST route.
      // (Muchos forms apuntan al GET por error y eso rompe la subida)
      let action = actionPost || formAction || '';

      if (!action || action === '#') {
        toast('No hay ruta/action para subir el ZIP (falta ROUTES.externalZipRegisterPost).', 'error');
        return;
      }

      // Si por alguna razón action quedó en el GET, lo corregimos a POST
      if (String(action).includes('/external/zip/register') && actionPost) {
        action = actionPost;
      }


      btn.disabled = true;
      btn.textContent = 'Subiendo…';

      try {
        const { fd, rfc, pass, hasZip } = buildFormData(modal, form);

        // defensivo: si el backend decide por "mode"
        if (!fd.has('mode')) fd.set('mode', 'zip');
        if (!fd.has('format')) fd.set('format', 'json');


        if (!rfc) throw new Error('RFC externo requerido.');
        if (!hasZip) throw new Error('Selecciona el archivo ZIP primero.');
        if (!pass) throw new Error('Contraseña FIEL requerida.');

        const res = await postMultipart(action, fd);
        toast(res.msg || res.message || 'FIEL subida correctamente.', 'success');

        // si hay botón cerrar en modal, lo intentamos
        try {
          const closeBtn =
            modal.querySelector('[data-close]') ||
            modal.querySelector('button[aria-label="Close"]') ||
            modal.querySelector('button[title="Cerrar"]');
          if (closeBtn) closeBtn.click();
        } catch(e) {}

      } catch (e) {
        toast(e?.message || 'No se pudo subir el FIEL (ZIP).', 'error');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Subir FIEL';
      }
    }, true);

  })();

  // init
  updateBulk();
});
