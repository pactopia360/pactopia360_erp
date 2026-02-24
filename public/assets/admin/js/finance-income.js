/* public/assets/admin/js/finance-income.js */
/* Finanzas > Ingresos (Ventas) · Modal editor + delete (extraído de Blade) */

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

  function showAlert(kind, msg){
    if (!alertEl) return;
    alertEl.classList.remove('ok','bad');
    alertEl.style.display = 'block';
    alertEl.classList.add(kind === 'ok' ? 'ok' : 'bad');
    alertEl.textContent = msg;
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

  // =========================
  // Autocálculo montos
  // =========================
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

  // =========================
  // DELETE helpers
  // =========================
  function canDelete(payload){
    if (!payload || typeof payload !== 'object') return false;

    if (payload.source === 'sale') return Number(payload.sale_id || 0) > 0;

    if (payload.source === 'projection' || payload.source === 'statement') {
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

    if (payload.source === 'sale' && Number(payload.sale_id || 0) > 0) {
      return CFG.destroyUrlTpl.replace('__ID__', String(payload.sale_id));
    }

    if (payload.source === 'projection' || payload.source === 'statement') {
      const base = CFG.destroyUrlTpl.replace('__ID__', '0');

      const period = String(payload.period || '');
      const accountId = String(payload.account_id || '');
      const rowType = payload.source === 'projection' ? 'projection' : 'statement';

      if (!/^\d{4}\-(0[1-9]|1[0-2])$/.test(period) || !accountId) return null;

      const qs = new URLSearchParams({
        period: period,
        account_id: accountId,
        row_type: rowType,
      });

      return base + '?' + qs.toString();
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
      const res = await fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': CFG.csrf,
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
      });

      let json = null;
      try { json = await res.json(); } catch(e){}

      if (!res.ok || !json || json.ok !== true) {
        const msg = (json && (json.message || json.error))
          ? (json.message || json.error)
          : ('Error HTTP ' + res.status);
        showAlert('bad', msg);
        return;
      }

      showAlert('ok', 'Eliminado OK (' + (json.mode || 'ok') + '). Actualizando vista...');
      window.setTimeout(() => window.location.reload(), 520);

    } catch(err){
      showAlert('bad', 'Error de red/JS al eliminar.');
    } finally {
      if (delBtn) {
        delBtn.disabled = false;
        delBtn.textContent = delBtn.dataset.prevText || 'Eliminar';
        delete delBtn.dataset.prevText;
      }
      showDanger(false);
    }
  }

  // =========================
  // Fill form
  // =========================
  function fillEditForm(payload){
    if (!formEl) return;

    formEl.querySelector('input[name="account_id"]').value = payload.account_id || '';
    formEl.querySelector('input[name="period"]').value = payload.period || '';
    formEl.querySelector('input[name="sale_id"]').value = payload.sale_id ? String(payload.sale_id) : '';
    formEl.querySelector('input[name="is_projection"]').value = (payload.source === 'projection') ? '1' : '0';

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

    const isSale = payload.source === 'sale' && Number(payload.sale_id || 0) > 0;
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

  // =========================
  // Modal open/close
  // =========================
  function openModal(payload){
    if (!payload || typeof payload !== 'object') return;

    lastPayload = JSON.parse(JSON.stringify(payload || {}));
    hideAlert();

    const tipo  = payload.tipo || payload.source || 'Detalle';
    const per   = payload.period || '—';
    const cli   = payload.client || '—';

    if (titleEl) titleEl.textContent = `${tipo} · ${per}`;
    if (subEl)   subEl.textContent   = `${cli} · Cuenta: ${payload.account_id || '—'}`;

    const fields = [
      ['Fuente', payload.source],
      ['Periodo', payload.period],
      ['Cliente', payload.client],
      ['Cuenta', payload.account_id],
      ['RFC Emisor', payload.rfc_emisor],

      ['Origen', payload.origin],
      ['Periodicidad', payload.periodicity],
      ['Vendedor', payload.vendor || '—'],
      ['Descripción', payload.description || '—'],

      ['Subtotal', money(payload.subtotal)],
      ['IVA', money(payload.iva)],
      ['Total', money(payload.total)],
      ['Estatus E.Cta', payload.ec_status || '—'],

      ['RFC Receptor', payload.rfc_receptor || '—'],
      ['Forma de pago', payload.forma_pago || '—'],
      ['F Cta', fmtDate(payload.f_cta)],
      ['F Mov', fmtDate(payload.f_mov)],
      ['F Factura', fmtDate(payload.f_factura)],
      ['F Pago', fmtDate(payload.f_pago)],

      ['Estatus Factura', payload.invoice_status || '—'],
      ['UUID', payload.cfdi_uuid || '—'],

      ['Sale ID', payload.sale_id ? String(payload.sale_id) : '—'],
      ['Incluir en E.Cta', payload.include_in_statement ? 'Sí' : 'No'],
      ['Periodo target (E.Cta)', payload.statement_period_target || '—'],
    ];

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

    // acciones rápidas
    const links = [];
    if (CFG.salesCreate) links.push(`<a class="p360-btn p360-btn-primary" href="${CFG.salesCreate}">+ Crear venta</a>`);
    if (CFG.salesIndex)  links.push(`<a class="p360-btn" href="${CFG.salesIndex}">Ver ventas</a>`);
    if (CFG.invoicesReq) links.push(`<a class="p360-btn" href="${CFG.invoicesReq}">Solicitud de facturas</a>`);
    if (CFG.stHub)       links.push(`<a class="p360-btn" href="${CFG.stHub}">Statements HUB</a>`);

    const isSale = payload.source === 'sale' && Number(payload.sale_id || 0) > 0;
    if (CFG.hasToggleInclude && isSale) {
      const lbl = payload.include_in_statement ? 'Quitar de Estado de Cuenta' : 'Incluir en Estado de Cuenta';
      links.push(`<button type="button" class="p360-btn" id="p360IncomeToggleIncludeBtn">${escapeHtml(lbl)}</button>`);
    }

    if (leftEl) leftEl.innerHTML = links.join('');

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
    document.addEventListener('keydown', onEsc);
  }

  function closeModal(){
    if (modal) modal.setAttribute('aria-hidden', 'true');
    if (backdrop) backdrop.classList.remove('is-open');

    if (gridEl) gridEl.innerHTML = '';
    if (leftEl) leftEl.innerHTML = '';

    hideAlert();
    showDanger(false);

    if (delBtn) delBtn.style.display = 'none';
    lastPayload = null;

    document.removeEventListener('keydown', onEsc);
  }

  function onEsc(e){
    if (e.key === 'Escape') closeModal();
  }

  // =========================
  // Submit upsert
  // =========================
  async function submitUpsert(){
    if (!CFG.upsertUrl) {
      showAlert('bad', 'No está configurada la ruta admin.finance.income.row.');
      return;
    }

    const fd = new FormData(formEl);

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
        },
        body: fd,
        credentials: 'same-origin',
      });

      let json = null;
      try { json = await res.json(); } catch(e){}

      if (!res.ok || !json || json.ok !== true) {
        const msg = (json && (json.message || json.error))
          ? (json.message || json.error)
          : ('Error HTTP ' + res.status);
        showAlert('bad', msg);
        return;
      }

      showAlert('ok', 'Guardado OK (' + (json.mode || 'ok') + '). Actualizando vista...');
      window.setTimeout(() => window.location.reload(), 520);

    } catch(err){
      showAlert('bad', 'Error de red/JS al guardar.');
    } finally {
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.textContent = saveBtn.dataset.prevText || 'Guardar cambios';
        delete saveBtn.dataset.prevText;
      }
    }
  }

  // =========================
  // Delegado: abrir / cerrar
  // =========================
  document.addEventListener('click', function(e){
    const btn = e.target.closest('[data-income-open="1"]');
    if (btn) {
      e.preventDefault();
      const raw = btn.getAttribute('data-income');
      if (!raw) return;
      try {
        const payload = JSON.parse(raw);
        openModal(payload);
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

  // Submit edit
  if (formEl) {
    formEl.addEventListener('submit', function(e){
      e.preventDefault();
      submitUpsert();
    });
  }

  // Reset UI
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

  // Delete flow
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