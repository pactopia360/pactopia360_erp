/* public/assets/admin/js/finance-income.js */
/* Finanzas > Ingresos · Modal editor + delete
   v2026.03.09
   - Respeta origen real: statement / sale / projection
   - Modal fiel para estados de cuenta: cargo / abono / saldo
   - No inventa montos visuales
   - Mejor manejo de respuestas HTTP
   - Soporte parcial / sin_mov
*/

(function(){
  'use strict';

  const CFG = (window.P360_FIN_INCOME && typeof window.P360_FIN_INCOME === 'object') ? window.P360_FIN_INCOME : {};
  const qs  = (s, r=document) => r.querySelector(s);

  const backdrop = qs('#p360IncomeModalBackdrop');
  const modal    = qs('#p360IncomeModal');
  const titleEl  = qs('#p360IncomeModalTitle');
  const subEl    = qs('#p360IncomeModalSub');
  const gridEl   = qs('#p360IncomeModalGrid');
  const leftEl   = qs('#p360IncomeModalLeft');

  const alertEl  = qs('#p360IncomeAlert');
  const formEl   = qs('#p360IncomeEditForm');
  const saveBtn  = qs('#p360IncomeSaveBtn');
  const resetBtn = qs('#p360IncomeResetBtn');

  const dangerEl = qs('#p360IncomeDanger');
  const delBtn   = qs('#p360IncomeDeleteBtn');
  const delYes   = qs('#p360IncomeConfirmDeleteBtn');
  const delNo    = qs('#p360IncomeCancelDeleteBtn');

  let lastPayload = null;
  let lastTriggerEl = null;

  const money = (n) => {
    const x = Number(n || 0);
    return '$' + x.toLocaleString('en-US', { minimumFractionDigits:2, maximumFractionDigits:2 });
  };

  const fmtDate = (v) => {
    if (!v) return '—';
    const s = String(v);
    if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
    return s;
  };

  const escapeHtml = (s) => String(s ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");

  const asNumber = (v, fallback = 0) => {
    const x = Number(v);
    return Number.isFinite(x) ? x : fallback;
  };

  const normalizeSource = (payload) => {
    const src = String(payload?.source || '').trim().toLowerCase();
    if (src === 'sale' || src === 'sale_linked') return 'sale';
    if (src === 'projection') return 'projection';
    return 'statement';
  };

  const resolveAmounts = (payload) => {
    const subtotal = asNumber(payload?.subtotal, 0);
    const iva      = asNumber(payload?.iva, 0);
    const total    = asNumber(payload?.total, 0);

    const abono    = asNumber(payload?.abono, 0);
    const saldo    = asNumber(payload?.saldo, Math.max(0, total - abono));
    const cargoRaw = asNumber(
      payload?.cargo_raw,
      payload?.facturado ?? payload?.total ?? 0
    );

    return {
      subtotal,
      iva,
      total,
      abono,
      saldo,
      cargoRaw,
    };
  };

  function showAlert(kind, msg){
    if (!alertEl) return;
    alertEl.classList.remove('ok','bad');
    alertEl.style.display = 'block';
    alertEl.classList.add(kind === 'ok' ? 'ok' : 'bad');
    alertEl.textContent = msg;
  }

  async function readResponseAny(res){
    const ct = String(res.headers.get('content-type') || '').toLowerCase();
    let text = '';
    let json = null;

    try { text = await res.text(); } catch(e){ text = ''; }

    const looksJson = ct.includes('application/json') || /^\s*\{/.test(text) || /^\s*\[/.test(text);
    if (looksJson) {
      try { json = JSON.parse(text || ''); } catch(e){ json = null; }
    }

    return { text, json, contentType: ct };
  }

  function summarizeHttpError(res, parsed){
    const status = res.status;

    if (status === 419) return 'Sesión/CSRF expirado (419). Refresca la página y vuelve a intentar.';
    if (status === 401) return 'No autenticado (401). Parece que la sesión se perdió. Refresca e inicia sesión.';
    if (status === 403) return 'Acceso denegado (403). Revisa permisos/middleware.';
    if (status >= 500) return 'Error interno del servidor (' + status + '). Revisa storage/logs/laravel.log.';

    const j = parsed?.json;
    if (j && typeof j === 'object') {
      if (j.message) return String(j.message);
      if (j.error) return String(j.error);

      if (j.errors && typeof j.errors === 'object') {
        const lines = [];
        Object.entries(j.errors).forEach(([k, arr]) => {
          if (Array.isArray(arr)) arr.forEach(m => lines.push(`${k}: ${m}`));
          else if (arr) lines.push(`${k}: ${arr}`);
        });
        if (lines.length) return 'Validación:\n' + lines.join('\n');
      }
    }

    const t = String(parsed?.text || '');
    if (t.includes('<!doctype html') || t.includes('<html')) {
      if (t.toLowerCase().includes('login') || t.toLowerCase().includes('iniciar')) {
        return 'Respuesta HTML (posible redirect/login). Confirma sesión activa y middleware.';
      }
      return 'Respuesta HTML inesperada. Abre Network > Response para ver el detalle.';
    }

    return 'Error HTTP ' + status;
  }

  async function requestJson(url, opts){
    const headers = Object.assign({
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': CFG.csrf || '',
    }, (opts && opts.headers) ? opts.headers : {});

    const res = await fetch(url, Object.assign({}, opts || {}, {
      headers,
      credentials: 'same-origin',
    }));

    const parsed = await readResponseAny(res);

    return { res, parsed };
  }

  function hideAlert(){
    if (!alertEl) return;
    alertEl.style.display = 'none';
    alertEl.textContent = '';
    alertEl.classList.remove('ok','bad');
  }

  function showDanger(show){
    if (!dangerEl) return;
    dangerEl.style.display = show ? 'block' : 'none';
  }

  function buildSelectOptions(sel, options, selected){
    if (!sel) return;
    sel.innerHTML = '<option value="">—</option>' + options.map(o => {
      const v = String(o.value ?? o.id ?? '');
      const lbl = String(o.label ?? o.name ?? v);
      const isSel = String(selected ?? '') === v;
      return `<option value="${escapeHtml(v)}" ${isSel ? 'selected' : ''}>${escapeHtml(lbl)}</option>`;
    }).join('');
  }

  function attachAutoCalc(){
    if (!formEl) return;
    const inSub = formEl.querySelector('input[name="subtotal"]');
    const inIva = formEl.querySelector('input[name="iva"]');
    const inTot = formEl.querySelector('input[name="total"]');
    if (!inSub || !inIva || !inTot) return;

    const num = (v) => {
      const x = Number(String(v ?? '').replace(/[^0-9.\-]/g,''));
      return isFinite(x) ? x : 0;
    };

    let lock = false;

    const recalcFromSubtotal = () => {
      if (lock) return;
      lock = true;
      const sub = num(inSub.value);
      const iva = Math.round(sub * 0.16 * 100) / 100;
      const tot = Math.round((sub + iva) * 100) / 100;
      inIva.value = sub ? String(iva.toFixed(2)) : '';
      inTot.value = sub ? String(tot.toFixed(2)) : '';
      lock = false;
    };

    const recalcFromTotal = () => {
      if (lock) return;
      lock = true;
      const tot = num(inTot.value);
      const sub = tot > 0 ? (tot / 1.16) : 0;
      const iva = tot > 0 ? (tot - sub) : 0;
      const s2 = Math.round(sub * 100) / 100;
      const i2 = Math.round(iva * 100) / 100;
      inSub.value = tot ? String(s2.toFixed(2)) : '';
      inIva.value = tot ? String(i2.toFixed(2)) : '';
      lock = false;
    };

    inSub.addEventListener('input', recalcFromSubtotal);
    inTot.addEventListener('input', recalcFromTotal);
  }
  attachAutoCalc();

  function lockScroll(){
    document.documentElement.classList.add('p360-modal-open');
    document.body.classList.add('p360-modal-open');
  }

  function unlockScroll(){
    document.documentElement.classList.remove('p360-modal-open');
    document.body.classList.remove('p360-modal-open');
  }

  function canDelete(payload){
    if (!payload || typeof payload !== 'object') return false;

    const src = normalizeSource(payload);

    if (src === 'sale') return Number(payload.sale_id || 0) > 0;

    if (src === 'projection' || src === 'statement') {
      return !!payload.account_id && !!payload.period;
    }

    return false;
  }

  function setDeleteVisible(payload){
    if (!delBtn) return;
    const ok = !!CFG.destroyUrlTpl && canDelete(payload);
    delBtn.style.display = ok ? 'inline-flex' : 'none';
  }

  function buildDestroyUrl(payload){
    if (!CFG.destroyUrlTpl || !payload) return null;

    const src = normalizeSource(payload);

    if (src === 'sale' && Number(payload.sale_id || 0) > 0) {
      return CFG.destroyUrlTpl.replace('__ID__', String(payload.sale_id));
    }

    if (src === 'projection' || src === 'statement') {
      const base = CFG.destroyUrlTpl.replace('__ID__', '0');

      const period = String(payload.period || '');
      const accountId = String(payload.account_id || '');
      const rowType = src === 'projection' ? 'projection' : 'statement';

      if (!/^\d{4}\-(0[1-9]|1[0-2])$/.test(period) || !accountId) return null;

      const q = new URLSearchParams({
        period: period,
        account_id: accountId,
        row_type: rowType,
      });

      return base + '?' + q.toString();
    }

    return null;
  }

  async function doDelete(payload){
    if (!payload) return;

    if (!CFG.destroyUrlTpl) {
      showAlert('bad', 'No existe la ruta admin.finance.income.row.destroy.');
      return;
    }

    const url = buildDestroyUrl(payload);
    if (!url) {
      showAlert('bad', 'No se pudo construir URL de eliminación (falta ID o key).');
      return;
    }

    if (delBtn) {
      delBtn.disabled = true;
      delBtn.dataset.prevText = delBtn.textContent || '';
      delBtn.textContent = 'Eliminando...';
    }

    try{
      const { res, parsed } = await requestJson(url, {
        method: 'DELETE',
      });

      const json = parsed.json;

      if (res.ok && json && json.ok === true) {
        showAlert('ok', 'Eliminado OK (' + (json.mode || 'ok') + '). Actualizando vista...');
        window.setTimeout(() => window.location.reload(), 520);
        return;
      }

      const msg = summarizeHttpError(res, parsed);
      showAlert('bad', msg);

      try {
        console.warn('[P360_FIN_INCOME] delete failed', {
          url,
          status: res.status,
          contentType: parsed.contentType,
          json: parsed.json,
          textPreview: String(parsed.text || '').slice(0, 500),
        });
      } catch(e){}

    } catch(err){
      showAlert('bad', 'Error de red/JS al eliminar.');
      try { console.error('[P360_FIN_INCOME] delete exception', err); } catch(e){}
    } finally {
      if (delBtn) {
        delBtn.disabled = false;
        delBtn.textContent = delBtn.dataset.prevText || 'Eliminar';
        delete delBtn.dataset.prevText;
      }
      showDanger(false);
    }
  }

  function fillEditForm(payload){
    if (!formEl) return;

    const src = normalizeSource(payload);

    formEl.querySelector('input[name="account_id"]').value = payload.account_id || '';
    formEl.querySelector('input[name="period"]').value = payload.period || '';
    formEl.querySelector('input[name="sale_id"]').value = payload.sale_id ? String(payload.sale_id) : '';
    formEl.querySelector('input[name="is_projection"]').value = (src === 'projection') ? '1' : '0';

    buildSelectOptions(
      formEl.querySelector('select[name="vendor_id"]'),
      (CFG.vendors || []).map(v => ({value:v.id, label:v.name})),
      payload.vendor_id || ''
    );
    buildSelectOptions(formEl.querySelector('select[name="ec_status"]'), CFG.ecOptions || [], payload.ec_status || '');
    buildSelectOptions(formEl.querySelector('select[name="invoice_status"]'), CFG.invOptions || [], payload.invoice_status || '');

    formEl.querySelector('input[name="cfdi_uuid"]').value = payload.cfdi_uuid || '';
    formEl.querySelector('input[name="rfc_receptor"]').value = payload.rfc_receptor || '';
    formEl.querySelector('input[name="forma_pago"]').value = payload.forma_pago || '';
    formEl.querySelector('input[name="subtotal"]').value = (payload.subtotal ?? '') !== '' ? String(payload.subtotal) : '';
    formEl.querySelector('input[name="iva"]').value = (payload.iva ?? '') !== '' ? String(payload.iva) : '';
    formEl.querySelector('input[name="total"]').value = (payload.total ?? '') !== '' ? String(payload.total) : '';
    formEl.querySelector('textarea[name="notes"]').value = payload.notes || '';

    const incSel = formEl.querySelector('select[name="include_in_statement"]');
    const sptInp = formEl.querySelector('input[name="statement_period_target"]');

    const isSale = src === 'sale' && Number(payload.sale_id || 0) > 0;
    if (incSel) incSel.disabled = !isSale;
    if (sptInp) sptInp.disabled = !isSale;

    if (isSale) {
      if (incSel) {
        incSel.value = (payload.include_in_statement === 0 || payload.include_in_statement === 1)
          ? String(payload.include_in_statement)
          : '';
      }
      if (sptInp) sptInp.value = payload.statement_period_target || '';
    } else {
      if (incSel) incSel.value = '';
      if (sptInp) sptInp.value = '';
    }
  }

  function buildDetailFields(payload){
    const src = normalizeSource(payload);
    const amounts = resolveAmounts(payload);

    const common = [
      ['Fuente', src === 'sale' ? 'Venta' : (src === 'projection' ? 'Proyección' : 'Estado de cuenta')],
      ['Periodo', payload.period || '—'],
      ['Cliente', payload.client || '—'],
      ['Cuenta', payload.account_id || '—'],
      ['RFC Emisor', payload.rfc_emisor || '—'],
      ['Origen', payload.origin || '—'],
      ['Periodicidad', payload.periodicity || '—'],
      ['Vendedor', payload.vendor || '—'],
      ['Descripción', payload.description || '—'],
    ];

    const statementFields = [
      ['Cargo', money(amounts.cargoRaw)],
      ['Abono', money(amounts.abono)],
      ['Saldo', money(amounts.saldo)],
      ['Estatus E.Cta', payload.ec_status || '—'],
      ['Factura', payload.invoice_status || '—'],
      ['Forma de pago', payload.forma_pago || '—'],
      ['F Cta', fmtDate(payload.f_cta)],
      ['F Mov', fmtDate(payload.f_mov)],
      ['F Factura', fmtDate(payload.f_factura)],
      ['F Pago', fmtDate(payload.f_pago)],
      ['UUID', payload.cfdi_uuid || '—'],
      ['Notas', payload.notes || '—'],
    ];

    const saleFields = [
      ['Subtotal', money(amounts.subtotal)],
      ['IVA', money(amounts.iva)],
      ['Total', money(amounts.total)],
      ['Estatus E.Cta', payload.ec_status || '—'],
      ['Factura', payload.invoice_status || '—'],
      ['RFC Receptor', payload.rfc_receptor || '—'],
      ['Forma de pago', payload.forma_pago || '—'],
      ['F Cta', fmtDate(payload.f_cta)],
      ['F Mov', fmtDate(payload.f_mov)],
      ['F Factura', fmtDate(payload.f_factura)],
      ['F Pago', fmtDate(payload.f_pago)],
      ['UUID', payload.cfdi_uuid || '—'],
      ['Sale ID', payload.sale_id ? String(payload.sale_id) : '—'],
      ['Incluir en E.Cta', payload.include_in_statement === 1 ? 'Sí' : (payload.include_in_statement === 0 ? 'No' : '—')],
      ['Periodo target (E.Cta)', payload.statement_period_target || '—'],
      ['Notas', payload.notes || '—'],
    ];

    const projectionFields = [
      ['Subtotal', money(amounts.subtotal)],
      ['IVA', money(amounts.iva)],
      ['Total', money(amounts.total)],
      ['Estatus E.Cta', payload.ec_status || '—'],
      ['Factura', payload.invoice_status || '—'],
      ['RFC Receptor', payload.rfc_receptor || '—'],
      ['Forma de pago', payload.forma_pago || '—'],
      ['F Cta', fmtDate(payload.f_cta)],
      ['F Mov', fmtDate(payload.f_mov)],
      ['F Factura', fmtDate(payload.f_factura)],
      ['F Pago', fmtDate(payload.f_pago)],
      ['UUID', payload.cfdi_uuid || '—'],
      ['Notas', payload.notes || '—'],
    ];

    if (src === 'sale') return common.concat(saleFields);
    if (src === 'projection') return common.concat(projectionFields);
    return common.concat(statementFields);
  }

  function buildQuickActions(payload){
    const src = normalizeSource(payload);
    const links = [];

    if (CFG.salesCreate) links.push(`<a class="p360-btn p360-btn-primary" href="${CFG.salesCreate}">+ Crear venta</a>`);
    if (CFG.salesIndex)  links.push(`<a class="p360-btn" href="${CFG.salesIndex}">Ver ventas</a>`);
    if (CFG.invoicesReq) links.push(`<a class="p360-btn" href="${CFG.invoicesReq}">Solicitud de facturas</a>`);
    if (CFG.stHub)       links.push(`<a class="p360-btn" href="${CFG.stHub}">Statements HUB</a>`);

    const isSale = src === 'sale' && Number(payload.sale_id || 0) > 0;
    if (CFG.hasToggleInclude && isSale) {
      const lbl = payload.include_in_statement ? 'Quitar de Estado de Cuenta' : 'Incluir en Estado de Cuenta';
      links.push(`<button type="button" class="p360-btn" id="p360IncomeToggleIncludeBtn">${escapeHtml(lbl)}</button>`);
    }

    return links.join('');
  }

  function openModal(payload, triggerEl){
    if (!payload || typeof payload !== 'object') return;

    lastTriggerEl = triggerEl || null;
    lastPayload = JSON.parse(JSON.stringify(payload || {}));
    hideAlert();

    const src   = normalizeSource(payload);
    const tipo  = payload.tipo || (src === 'sale' ? 'Venta' : (src === 'projection' ? 'Proyección' : 'Estado de cuenta'));
    const per   = payload.period || '—';
    const cli   = payload.client || '—';

    if (titleEl) titleEl.textContent = `${tipo} · ${per}`;
    if (subEl)   subEl.textContent   = `${cli} · Cuenta: ${payload.account_id || '—'}`;

    const fields = buildDetailFields(payload);

    if (gridEl) {
      gridEl.innerHTML = fields.map(([k,v]) => {
        return `
          <div class="p360-field">
            <div class="k">${escapeHtml(k)}</div>
            <div class="v">${escapeHtml(v ?? '—')}</div>
          </div>
        `;
      }).join('');
    }

    if (leftEl) leftEl.innerHTML = buildQuickActions(payload);

    const tbtn = qs('#p360IncomeToggleIncludeBtn');
    if (tbtn) {
      tbtn.addEventListener('click', function(){
        const form = qs('#p360IncomeToggleIncludeForm');
        if (!form) return;
        form.setAttribute('action', CFG.toggleBase + '/' + String(payload.sale_id) + '/toggle-include');
        form.submit();
      }, { once:true });
    }

    fillEditForm(payload);
    setDeleteVisible(payload);
    showDanger(false);

    if (backdrop) backdrop.classList.add('is-open');
    if (modal) modal.setAttribute('aria-hidden', 'false');

    lockScroll();

    const closeBtn = modal ? modal.querySelector('[data-income-close="1"]') : null;
    if (closeBtn) closeBtn.focus({ preventScroll:true });

    document.addEventListener('keydown', onEsc);
  }

  function closeModal(){
    if (modal) modal.setAttribute('aria-hidden', 'true');
    if (backdrop) backdrop.classList.remove('is-open');

    unlockScroll();

    if (gridEl) gridEl.innerHTML = '';
    if (leftEl) leftEl.innerHTML = '';

    hideAlert();
    showDanger(false);

    if (delBtn) delBtn.style.display = 'none';
    lastPayload = null;

    document.removeEventListener('keydown', onEsc);

    if (lastTriggerEl && typeof lastTriggerEl.focus === 'function') {
      try { lastTriggerEl.focus({ preventScroll:true }); } catch(e){}
    }
    lastTriggerEl = null;
  }

  function onEsc(e){
    if (e.key === 'Escape') closeModal();
  }

  async function submitUpsert(){
    if (!CFG.upsertUrl) {
      showAlert('bad', 'No está configurada la ruta admin.finance.income.row.');
      return;
    }
    if (!formEl) {
      showAlert('bad', 'No existe el formulario de edición (p360IncomeEditForm).');
      return;
    }

    const fd = new FormData(formEl);

    try {
      const obj = {};
      fd.forEach((v, k) => { obj[k] = v; });
      console.log('[P360_FIN_INCOME] upsert payload:', obj);
    } catch(e) {}

    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.dataset.prevText = saveBtn.textContent || '';
      saveBtn.textContent = 'Guardando...';
    }
    hideAlert();

    try{
      const res = await fetch(CFG.upsertUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': CFG.csrf,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: fd,
        credentials: 'same-origin',
      });

      const ct = (res.headers.get('content-type') || '').toLowerCase();
      let json = null;
      let text = '';

      if (ct.includes('application/json')) {
        try { json = await res.json(); } catch(e){}
      } else {
        try { text = await res.text(); } catch(e){}
      }

      if (!res.ok || !json || json.ok !== true) {
        const msg = (json && (json.message || json.error))
          ? (json.message || json.error)
          : (text ? ('Respuesta no-JSON (HTTP ' + res.status + '): ' + text.slice(0, 200)) : ('Error HTTP ' + res.status));
        console.warn('[P360_FIN_INCOME] upsert failed:', { status: res.status, ct, json, textPreview: (text || '').slice(0, 800) });
        showAlert('bad', msg);
        return;
      }

      console.log('[P360_FIN_INCOME] upsert ok:', json);
      showAlert('ok', 'Guardado OK (' + (json.mode || 'ok') + '). Actualizando vista...');
      window.setTimeout(() => window.location.reload(), 520);

    } catch(err){
      console.error('[P360_FIN_INCOME] upsert network/js error:', err);
      showAlert('bad', 'Error de red/JS al guardar.');
    } finally {
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.textContent = saveBtn.dataset.prevText || 'Guardar cambios';
        delete saveBtn.dataset.prevText;
      }
    }
  }

  document.addEventListener('click', function(e){
    const btn = e.target.closest('[data-income-open="1"]');
    if (btn) {
      e.preventDefault();
      const raw = btn.getAttribute('data-income');
      if (!raw) return;
      try {
        const payload = JSON.parse(raw);
        openModal(payload, btn);
      } catch(err){}
      return;
    }

    if (e.target.closest('[data-income-close="1"]')) {
      e.preventDefault();
      closeModal();
      return;
    }

    if (backdrop && e.target === backdrop) closeModal();
  });

  if (formEl) {
    formEl.addEventListener('submit', function(e){
      e.preventDefault();
      submitUpsert();
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (!lastPayload) return;
      hideAlert();
      fillEditForm(lastPayload);
      showAlert('ok', 'Campos revertidos (UI). No se guardó nada.');
      window.setTimeout(hideAlert, 900);
      showDanger(false);
    });
  }

  if (delBtn) {
    delBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (!lastPayload) return;
      hideAlert();
      showDanger(true);
    });
  }

  if (delNo) {
    delNo.addEventListener('click', function(e){
      e.preventDefault();
      showDanger(false);
    });
  }

  if (delYes) {
    delYes.addEventListener('click', function(e){
      e.preventDefault();
      if (!lastPayload) return;
      doDelete(lastPayload);
    });
  }

})();