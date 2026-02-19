/* public/assets/client/js/sat/sat-v4.js (v4.2 · MODAL-FIRST · Quote+PDF fixed · Portal+Activity stable) */
(function () {
  'use strict';

  const CFG  = (window.P360_SAT && typeof window.P360_SAT === 'object') ? window.P360_SAT : {};
  const R    = CFG.routes || {};
  const csrf = CFG.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';

  const qs  = (s, r=document) => r.querySelector(s);
  const qsa = (s, r=document) => Array.from(r.querySelectorAll(s));

  // =====================================================
  // External RFC mode (Individual / ZIP) + Individual invite
  // =====================================================
  function setExternalMode(mode){
    const btnInd  = qs('#exModeIndividual');
    const btnZip  = qs('#exModeZip');
    const paneInd = qs('#exPaneIndividual');
    const paneZip = qs('#exPaneZip');

    const m = String(mode || 'zip').toLowerCase() === 'individual' ? 'individual' : 'zip';

    if (paneInd) paneInd.style.display = (m === 'individual') ? 'block' : 'none';
    if (paneZip) paneZip.style.display = (m === 'zip') ? 'block' : 'none';

    // botones visual
    if (btnInd){
      btnInd.classList.toggle('sat4-btn-primary', m === 'individual');
      btnInd.classList.toggle('sat4-btn-ghost', m !== 'individual');
    }
    if (btnZip){
      btnZip.classList.toggle('sat4-btn-primary', m === 'zip');
      btnZip.classList.toggle('sat4-btn-ghost', m !== 'zip');
    }

    try{ window.__SAT4_EXT_MODE__ = m; }catch{}
  }

  function initExternalModeUi(){
    const btnInd = qs('#exModeIndividual');
    const btnZip = qs('#exModeZip');

    if (btnInd) btnInd.addEventListener('click', () => setExternalMode('individual'));
    if (btnZip) btnZip.addEventListener('click', () => setExternalMode('zip'));

    // default
    setExternalMode('zip');
  }


  function money(n){
    const x = Number(n || 0);
    return '$' + x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function openModal(id){
    const m = document.getElementById(id);
    const b = document.getElementById('sat4Backdrop');
    if (!m || !b) return;
    b.style.display = 'block';
    m.style.display = 'flex';
    document.body.classList.add('sat4-lock');
  }

  function closeAll(){
    const b = document.getElementById('sat4Backdrop');
    if (b) b.style.display = 'none';
    qsa('.sat4-modal').forEach(m => m.style.display = 'none');
    document.body.classList.remove('sat4-lock');
  }

  function routeWithId(pattern, id){
    if (!pattern) return '';
    const safe = encodeURIComponent(String(id));

    // ✅ soporta: __ID__ | {id} | :id | %7Bid%7D
    return String(pattern)
      .replace(/__ID__/g, safe)
      .replace(/\{id\}/gi, safe)
      .replace(/:id\b/gi, safe)
      .replace(/%7Bid%7D/gi, safe);
  }

  function parseJsonSafe(txt){
    try{ return JSON.parse(txt); }catch{ return null; }
  }

  async function fetchJson(url, opts){
    const res = await fetch(url, opts);
    const txt = await res.text();
    const data = parseJsonSafe(txt);
    return { res, txt, data };
  }

  // =====================================================
  // Global modal open/close
  // =====================================================
  document.addEventListener('click', (e) => {
    const openBtn = e.target.closest('[data-open]');
    if (openBtn){
      openModal(openBtn.getAttribute('data-open'));
      return;
    }

    if (e.target.closest('[data-close]')){
      closeAll();
      return;
    }

    if (e.target && e.target.id === 'sat4Backdrop'){
      closeAll();
      return;
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAll();
  });

  // =====================================================
  // Refresh (hard)
  // =====================================================
  const btnRef = qs('#sat4Refresh');
  if (btnRef){
    btnRef.addEventListener('click', () => {
      try{
        const u = new URL(window.location.href);
        u.searchParams.set('_ts', String(Date.now()));
        window.location.href = u.toString();
      }catch{
        window.location.reload();
      }
    });
  }

  // =====================================================
  // Mode switch
  // =====================================================
  const btnMode = qs('#sat4Mode');
  if (btnMode && btnMode.dataset.url){
    btnMode.addEventListener('click', async () => {
      try{
        await fetch(btnMode.dataset.url, {
          method:'POST',
          credentials:'same-origin',
          headers: { 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' }
        });
        window.location.reload();
      }catch{
        window.location.reload();
      }
    });
  }

  // =====================================================
  // Multi RFC toggle + hidden select sync
  // =====================================================
  const multiBtn    = qs('#sat4ReqMultiBtn');
  const multiWrap   = qs('#sat4MultiWrap');
  const multiAll    = qs('#sat4MultiAll');
  const rfcsHidden  = qs('#sat4RfcsHidden');

  if (multiBtn && multiWrap){
    multiBtn.addEventListener('click', () => {
      multiWrap.style.display = (multiWrap.style.display === 'none' || !multiWrap.style.display) ? 'block' : 'none';
      // re-eval submit state (handled in RFC UX improvements too)
      try{ setTimeout(() => updateReqSubmitState(), 0); }catch{}
    });
  }

  function syncHiddenRfcs(){
    if (!rfcsHidden) return;

    // ✅ solo RFCs habilitados (validados)
    const checked = qsa('.sat4-rfc-item')
      .filter(x => !x.disabled && x.checked)
      .map(x => x.value);

    qsa('option', rfcsHidden).forEach(o => {
      o.selected = checked.includes(o.value);
    });
  }

  qsa('.sat4-rfc-item').forEach(x => x.addEventListener('change', syncHiddenRfcs));
  if (multiAll){
    multiAll.addEventListener('click', () => {
      qsa('.sat4-rfc-item').forEach(x => { if (!x.disabled) x.checked = true; });
      syncHiddenRfcs();
      try{ updateReqSubmitState(); }catch{}
    });
  }
  syncHiddenRfcs();

  // =====================================================
  // RFC UX improvements (single vs multi submit guard)
  // =====================================================
  let updateReqSubmitState = function(){};

  (function(){
    const single    = qs('#sat4RfcSingle');
    const submitBtn = qs('#sat4ReqForm button[type="submit"]');

    if (!single || !submitBtn) return;

    function countValidOptions(){
      return qsa('#sat4RfcSingle option')
        .filter(o => o.value && !o.disabled).length;
    }

    function firstValidOption(){
      return qsa('#sat4RfcSingle option')
        .find(o => o.value && !o.disabled);
    }

    updateReqSubmitState = function(){
      const usingMulti = multiWrap && multiWrap.style.display === 'block';

      if (usingMulti){
        const anyChecked = qsa('.sat4-rfc-item').some(x => !x.disabled && x.checked);
        submitBtn.disabled = !anyChecked;
        return;
      }

      submitBtn.disabled = !String(single.value || '').trim();
    };

    // Auto-select if only 1 valid RFC
    const validCount = countValidOptions();
    if (validCount === 1){
      const opt = firstValidOption();
      if (opt) single.value = opt.value;
    }

    single.addEventListener('change', updateReqSubmitState);
    qsa('.sat4-rfc-item').forEach(x => x.addEventListener('change', updateReqSubmitState));

    updateReqSubmitState();
  })();

  // En submit: si hay multiWrap visible, ignora rfc_single
  const reqForm = qs('#sat4ReqForm');
  if (reqForm){
    reqForm.addEventListener('submit', () => {
      const single = qs('#sat4RfcSingle');
      if (!single) return;
      const usingMulti = multiWrap && multiWrap.style.display === 'block';

      if (usingMulti){
        single.value = '';
        syncHiddenRfcs();
      }else{
        const val = (single.value || '').trim();
        if (val && rfcsHidden){
          qsa('option', rfcsHidden).forEach(o => o.selected = (o.value === val));
        }
      }
    });
  }

  // =====================================================
  // Verify (server refresh) – re-render UI
  // =====================================================
  const btnVerify = qs('#sat4Verify');
  if (btnVerify && btnVerify.dataset.url){
    btnVerify.addEventListener('click', async () => {
      try{
        await fetch(btnVerify.dataset.url, {
          method:'POST',
          credentials:'same-origin',
          headers: { 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' }
        });
      }catch{}
      try{ renderDownloads(); }catch{}
      try{ renderLatest(); }catch{}
      try{ renderActivity(); }catch{}
      try{ initPortal(); }catch{}
      try{ buildNotifications(); }catch{}
    });
  }

    // ===== Quote (quickCalc/quickPdf) =====
  function sanitizeInt(v){
    const s = String(v ?? '').replace(/[^\d]/g, '');
    const n = parseInt(s || '0', 10);
    return Number.isFinite(n) ? n : 0;
  }

  function setQuoteUiLoading(isLoading){
    const btnCalc = qs('#qCalc');
    const btnPdf  = qs('#qPdf');
    const note    = qs('#qNote');

    if (btnCalc) btnCalc.disabled = !!isLoading;
    if (btnPdf)  btnPdf.disabled  = !!isLoading;

    if (note && isLoading) note.textContent = 'Calculando…';
  }

  function setQuoteUiError(msg){
    const note = qs('#qNote');
    if (note) note.textContent = msg || 'Error';
  }

  function setAppliedCode(code){
    const elA = qs('#qDiscApplied');
    const elL = qs('#qDiscLabel');

    const c = String(code || '').trim();
    const show = c ? c.toUpperCase() : '—';

    if (elA) elA.textContent = show;
    if (elL) elL.textContent = c ? c.toUpperCase() : 'Descuento';
  }

  function setTariffNote(txt){
    const el = qs('#qTariffNote');
    if (el) el.textContent = String(txt || '—');
  }

  function setQuoteUiEmpty(){
    qs('#qBase')  && (qs('#qBase').textContent  = money(0));
    qs('#qDesc')  && (qs('#qDesc').textContent  = '-' + money(0));
    qs('#qIvaV')  && (qs('#qIvaV').textContent  = money(0));
    qs('#qTotal') && (qs('#qTotal').textContent = money(0));
    setQuoteUiError('—');
    setTariffNote('—');
    setAppliedCode('');
    window.__SAT4_QUOTE__ = null;
  }

  function parseDiscountSmart(raw){
    const s = String(raw ?? '').trim();
    if (!s) return { type:'none', value:0, code:'' };

    // ✅ Si trae letras, guión o underscore => es CÓDIGO (ADMIN)
    if (/[a-zA-Z]/.test(s) || /[_-]/.test(s)){
      return { type:'code', value:0, code: s.toUpperCase() };
    }

    const hasPct = s.includes('%');
    const cleaned = s
      .replace(/\$/g,'')
      .replace(/,/g,'')
      .replace(/\s+/g,'')
      .replace('%','');

    const n = Number(cleaned);
    if (!Number.isFinite(n) || n <= 0) return { type:'none', value:0, code:'' };

    if (hasPct) return { type:'pct', value: Math.min(100, n), code:'' };
    if (n > 0 && n <= 1) return { type:'pct', value: Math.min(100, n * 100), code:'' };
    return { type:'amt', value: n, code:'' };
  }

  async function quickCalc(){
  const url = (R.quickCalc || '').trim();
  if (!url){
    setQuoteUiError('Ruta de cálculo no configurada (quickCalc).');
    throw new Error('Ruta quickCalc no configurada');
  }

  // ===== inputs (sin helpers externos) =====
  const xml_count = sanitizeInt(qs('#qXml')?.value || 0);
  const discount_raw = (qs('#qDisc')?.value || '').trim();
  const iva_rate = sanitizeInt(qs('#qIva')?.value || 16);

  if (xml_count <= 0){
    setQuoteUiError('Ingresa un número de XML válido.');
    throw new Error('xml_count inválido');
  }

  const disc = parseDiscountSmart(discount_raw);

  // ===== UI pre-state =====
  setQuoteUiLoading(true);
  setQuoteUiError('—');

  // Reset visual “aplicado” hasta que backend confirme
  setAppliedCode('—');
  const $discLabel = qs('#qDiscLabel');
  if ($discLabel) $discLabel.textContent = 'Descuento';

  let res, txt, data;
  try{
    const fd = new FormData();

    // keys principales
    fd.append('xml_count', String(xml_count));
    fd.append('discount_code', discount_raw);
    fd.append('iva_rate', String(iva_rate));

    // keys compat
    fd.append('qty', String(xml_count));
    fd.append('iva', String(iva_rate));
    fd.append('discount', discount_raw);
    fd.append('discount_type', disc.type);
    fd.append('discount_value', String(disc.value));

    res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
      body: fd
    });

    txt = await res.text();
    try { data = JSON.parse(txt); } catch { data = null; }

    if (!res.ok){
      const msg =
        (res.status === 419) ? 'Sesión expirada (419). Refresca la página.'
        : (data && (data.msg || data.message)) ? (data.msg || data.message)
        : ('Error cotizando (HTTP ' + res.status + ')');

      setQuoteUiError(msg);
      throw new Error(msg);
    }

    if (!data || data.ok !== true){
      const msg =
        (data && (data.msg || data.message)) ? (data.msg || data.message)
        : 'Respuesta inválida del servidor.';
      setQuoteUiError(msg);
      throw new Error(msg);
    }

    const d = data.data || {};

    // ===== montos =====
    const base = Number(d.base ?? d.subtotal ?? d.base_amount ?? 0) || 0;
    const desc = Number(d.discount_amount ?? d.discount ?? d.desc ?? 0) || 0;
    const iva  = Number(d.iva_amount ?? d.iva ?? d.tax ?? 0) || 0;
    const tot  = Number(d.total ?? d.grand_total ?? 0) || 0;

    qs('#qBase')  && (qs('#qBase').textContent  = money(base));
    qs('#qDesc')  && (qs('#qDesc').textContent  = '-' + money(Math.max(0, desc)));
    qs('#qIvaV')  && (qs('#qIvaV').textContent  = money(iva));
    qs('#qTotal') && (qs('#qTotal').textContent = money(tot));

    // ===== descuento aplicado: SOLO si backend lo confirma =====
    const appliedCode = String(
      d.discount_code_applied ??
      d.discount_code_applied_to ??
      ''
    ).trim();

    const appliedLabel = String(
      d.discount_label ??
      d.discount_display ??
      ''
    ).trim();

    // Motivo de no aplicación (backend debe mandarlo)
    const reason = String(
      d.discount_reason ??
      d.reason ??
      ''
    ).trim();

    if (appliedCode !== ''){
      setAppliedCode(appliedCode);

      // El label en la fila: si hay label (ej. "PROMO10"), úsalo. Si no, usa el código aplicado.
      if ($discLabel){
        $discLabel.textContent = (appliedLabel !== '' ? appliedLabel : appliedCode);
      }

      // nota arriba (qNote): “Aplicado …”
      setQuoteUiError(reason !== '' ? reason : ('Código aplicado: ' + appliedCode));
    } else {
      // No aplicado: deja “—” y etiqueta “Descuento”
      setAppliedCode('—');
      if ($discLabel) $discLabel.textContent = 'Descuento';

      // Si el usuario escribió algo, avisar por qué no aplicó
      if (discount_raw !== ''){
        setQuoteUiError(reason !== '' ? reason : 'Código no aplicado.');
      } else {
        setQuoteUiError('—');
      }
    }

    // ===== nota de tarifa =====
    const note = String(d.tariff_note ?? d.note ?? data.note ?? '—').trim() || '—';
    setTariffNote(note);

    // ===== persist para PDF =====
    window.__SAT4_QUOTE__ = {
      xml_count,
      discount_code: discount_raw,
      discount_code_applied: appliedCode,     // confirmado por backend o ''
      discount_label: appliedLabel,           // opcional
      discount_reason: reason,                // opcional
      iva_rate,
      discount_type: disc.type,
      discount_value: disc.value,
      _ok: true,
      _ts: Date.now()
    };

    return true;
  } finally{
    setQuoteUiLoading(false);
  }
}


  // Hook UI (calcular + UX)
  (function initQuoteUi(){
    const btnCalc = qs('#qCalc');
    const btnPdf  = qs('#qPdf');
    const inDisc  = qs('#qDisc');

    if (btnCalc){
      btnCalc.addEventListener('click', async () => {
        try{
          await quickCalc();
        }catch(err){
          // no-op (ya lo pinta en UI)
        }
      });
    }

    // al escribir, solo actualiza label visual (no calcula)
    if (inDisc){
      inDisc.addEventListener('input', () => {
     setAppliedCode('—'); // hasta que el backend confirme
     });
    }

    // PDF (si tu ruta quickPdf existe; aquí solo abre el endpoint)
    if (btnPdf){
      btnPdf.addEventListener('click', async () => {
        const url = (R.quickPdf || '').trim();
        if (!url){ alert('Ruta PDF no configurada.'); return; }

        // si no hay cálculo, intenta calcular primero
        if (!window.__SAT4_QUOTE__?._ok){
          try{ await quickCalc(); }catch{ return; }
        }

        // abre PDF con querystring simple (tu backend puede aceptarlo o ignorarlo)
        const q = window.__SAT4_QUOTE__ || {};
        try{
          const u = new URL(url, window.location.origin);
          u.searchParams.set('xml_count', String(q.xml_count || '0'));
          u.searchParams.set('discount_code', String(q.discount_code || ''));
          u.searchParams.set('iva_rate', String(q.iva_rate || '16'));
          window.open(u.toString(), '_blank', 'noopener');
        }catch{
          window.location.href = url;
        }
      });
    }

    // init limpio
    try{ setAppliedCode((qs('#qDisc')?.value || '').trim()); }catch{}
  })();


  // =====================================================
  // Downloads list
  // =====================================================
  function normalizeRow(r){
    const get = (k, def=null) => (r && (r[k] !== undefined && r[k] !== null)) ? r[k] : def;

    const id     = get('id', get('download_id', ''));
    const rfc    = String(get('rfc', '—'));
    const alias  = String(get('alias', '—'));
    const status = String(get('status', get('sat_status', get('status_sat', get('estado', 'pending'))))).toLowerCase();

    const created_at = get('created_at', get('createdAt', get('fecha', '')));
    const desde      = get('desde','');
    const hasta      = get('hasta','');
    const period     = String(get('period_label', (desde && hasta) ? (desde+' – '+hasta) : '—'));

    const is_paid    = !!(get('is_paid', get('paid', get('pagado', 0))));
    const expires_at = get('expires_at', null);

    let zipUrl = get('zip_url', '');
    if (!zipUrl && id && R.zipPattern) zipUrl = routeWithId(R.zipPattern, id);

    const cost = Number(get('costo', get('amount', get('total_mxn', 0))) || 0);

    let expired = !!get('is_expired', false);
    if (!expired && expires_at){
      try{ expired = (new Date(expires_at).getTime() < Date.now()); }catch{}
    }
    if (is_paid) expired = false;

    return { id, rfc, alias, status, created_at, period, is_paid, expired, zipUrl, cost };
  }

  function statusTag(row){
    if (row.expired) return { cls:'bad',  txt:'Expirada' };
    if (row.is_paid) return { cls:'ok',   txt:'Pagada' };
    if (row.status.includes('done') || row.status.includes('ready') || row.status.includes('list')) return { cls:'warn', txt:'Lista' };
    if (row.status.includes('proc')) return { cls:'warn', txt:'Proceso' };
    return { cls:'warn', txt:'Pendiente' };
  }

  function parseDateSafe(x){
    if (!x) return 0;
    try{ return new Date(x).getTime() || 0; }catch{ return 0; }
  }

  // =====================================================
  // Portal UI (ring + estado + notifs)
  // =====================================================
  function setText(id, txt){
    const el = document.getElementById(id);
    if (el) el.textContent = String(txt ?? '');
  }

  function setRing(pct, label){
    const wrap = document.getElementById('sat4RingWrap');
    const p = Math.max(0, Math.min(100, Number(pct || 0)));
    if (wrap) wrap.style.setProperty('--p', String(p));
    setText('sat4RingP', `${Math.round(p)}%`);
    setText('sat4RingL', label || (p > 0 ? 'Procesando' : 'Sin proceso'));
  }

  function numOr0(v){
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  }

  function applyStatusFromRow(row){
    if (!row){
      setRing(0, 'Sin proceso');
      setText('sat4StatusHint', '—');
      setText('sat4StatSat', 0);
      setText('sat4StatNew', 0);
      setText('sat4StatFail', 0);
      setText('sat4StatReg', 0);
      return;
    }

    const pct =
      numOr0(row.progress ?? row.percent ?? row.pct ?? row.avance ?? row.progress_percent ?? 0);

    const cSat  = numOr0(row.comprobantes_sat ?? row.total_sat ?? row.sat_total ?? row.total ?? 0);
    const cNew  = numOr0(row.comprobantes_nuevos ?? row.nuevos ?? row.new_total ?? row.inserted ?? 0);
    const cFail = numOr0(row.comprobantes_fallidos ?? row.fallidos ?? row.failed ?? row.errors ?? 0);
    const cReg  = numOr0(row.comprobantes_registrados ?? row.registrados ?? row.registered ?? row.saved ?? 0);

    setText('sat4StatSat',  Math.trunc(cSat));
    setText('sat4StatNew',  Math.trunc(cNew));
    setText('sat4StatFail', Math.trunc(cFail));
    setText('sat4StatReg',  Math.trunc(cReg));

    const st = String(row.status || '').toLowerCase();
    let label = 'Sin proceso';
    if (st.includes('proc')) label = 'Descargando…';
    else if (st.includes('pend')) label = 'Pendiente…';
    else if (st.includes('done') || st.includes('ready') || st.includes('list')) label = 'Listo';
    else if (st.includes('paid')) label = 'Pagado';

    setRing(pct, label);

    const hint = row.created_at
      ? `Última actualización: ${String(row.created_at).slice(0,19).replace('T',' ')}`
      : `Estado: ${label}`;
    setText('sat4StatusHint', hint);
  }

  function getLatestRow(){
    const rows = Array.isArray(CFG.downloads) ? CFG.downloads : [];
    if (!rows.length) return null;

    const norm = rows.map(normalizeRow).sort((a,b)=> parseDateSafe(b.created_at) - parseDateSafe(a.created_at));
    const active = norm.find(x => String(x.status||'').includes('proc') || String(x.status||'').includes('pend'));
    return active || norm[0] || null;
  }

  function buildNotifications(){
    const list = document.getElementById('sat4NotifyList');
    const bell = document.getElementById('sat4Bell');
    if (!list) return;

    const rows = Array.isArray(CFG.downloads) ? CFG.downloads : [];
    const norm = rows.map(normalizeRow)
      .sort((a,b)=> parseDateSafe(b.created_at) - parseDateSafe(a.created_at))
      .slice(0, 7);

    if (!norm.length){
      list.innerHTML = `
        <div class="sat4-note">
          <div class="sat4-note-ico">🛰️</div>
          <div class="sat4-note-meta">
            <div class="sat4-note-title">SAT</div>
            <div class="sat4-note-sub">Sin notificaciones aún.</div>
            <div class="sat4-note-time">—</div>
          </div>
        </div>
      `;
      if (bell) bell.classList.remove('has-unread');
      return;
    }

    const items = norm.map(r => {
      const tag  = statusTag(r);
      const when = r.created_at ? String(r.created_at).slice(0,19).replace('T',' ') : '—';

      let title = 'Descarga SAT';
      let sub   = `RFC ${r.rfc} · ${r.period}`;
      let ico   = '🧾';

      if (tag.txt === 'Proceso')  { ico = '⬇️'; sub = `Descargando… · ${r.rfc}`; }
      else if (tag.txt === 'Lista'){ ico = '✅'; sub = `Lista para pagar/descargar · ${r.rfc}`; }
      else if (tag.txt === 'Pagada'){ ico = '📦'; sub = `ZIP disponible · ${r.rfc}`; }
      else if (tag.txt === 'Expirada'){ ico = '⏳'; sub = `Expirada · ${r.rfc}`; }

      return `
        <div class="sat4-note">
          <div class="sat4-note-ico">${ico}</div>
          <div class="sat4-note-meta">
            <div class="sat4-note-title">${title}</div>
            <div class="sat4-note-sub">${sub}</div>
            <div class="sat4-note-time">${when}</div>
          </div>
        </div>
      `;
    }).join('');

    list.innerHTML = items;

    const unread = norm.some(r => {
      const st = String(r.status||'').toLowerCase();
      return st.includes('proc') || st.includes('done') || st.includes('ready') || st.includes('list');
    });

    if (bell){
      if (unread) bell.classList.add('has-unread');
      else bell.classList.remove('has-unread');
    }
  }

  function initNotifyUI(){
    const bell  = document.getElementById('sat4Bell');
    const box   = document.getElementById('sat4Notify');
    const clear = document.getElementById('sat4NotifyClear');
    if (!bell || !box) return;

    function close(){ box.classList.remove('is-open'); }
    function toggle(){ box.classList.toggle('is-open'); }

    bell.addEventListener('click', (e)=>{
      e.preventDefault();
      e.stopPropagation();
      toggle();
    });

    document.addEventListener('click', (e)=>{
      if (!box.contains(e.target) && !bell.contains(e.target)) close();
    });

    if (clear){
      clear.addEventListener('click', ()=>{
        const list = document.getElementById('sat4NotifyList');
        if (list){
          list.innerHTML = `
            <div class="sat4-note">
              <div class="sat4-note-ico">🛰️</div>
              <div class="sat4-note-meta">
                <div class="sat4-note-title">SAT</div>
                <div class="sat4-note-sub">Notificaciones descartadas.</div>
                <div class="sat4-note-time">—</div>
              </div>
            </div>
          `;
        }
        bell.classList.remove('has-unread');
        close();
      });
    }
  }

  function initPortal(){
    const latest = getLatestRow();
    applyStatusFromRow(latest);
    buildNotifications();
  }

  // =====================================================
  // Latest list (optional container)
  // =====================================================
  function renderLatest(){
    const wrap = qs('#sat4Latest');
    if (!wrap) return;

    const rows = Array.isArray(CFG.downloads) ? CFG.downloads : [];
    const norm = rows.map(normalizeRow).sort((a,b) => {
      const ta = a.created_at ? Date.parse(a.created_at) : NaN;
      const tb = b.created_at ? Date.parse(b.created_at) : NaN;
      if (!Number.isNaN(ta) && !Number.isNaN(tb)) return tb - ta;
      return String(b.id).localeCompare(String(a.id));
    });

    const top = norm.slice(0, 5);
    if (!top.length){
      wrap.innerHTML = `<div class="sat4-mini">Aún no hay descargas.</div>`;
      return;
    }

    wrap.innerHTML = top.map(row => {
      const tag = statusTag(row);

      const canDownload = (!row.expired && row.is_paid && row.zipUrl);
      const canPay      = (!row.expired && !row.is_paid && (row.status.includes('done') || row.status.includes('ready') || row.status.includes('list')));

      const btnDownload = canDownload
        ? `<button class="sat4-btn sat4-btn-primary" data-act="dl" data-url="${encodeURIComponent(row.zipUrl)}">ZIP</button>`
        : '';

      const btnPay = canPay
        ? `<button class="sat4-btn" data-act="pay" data-id="${encodeURIComponent(row.id)}">Pagar</button>`
        : '';

      const subtitle = row.alias && row.alias !== '—'
        ? row.alias
        : (row.created_at ? String(row.created_at).slice(0,19).replace('T',' ') : '—');

      return `
        <div class="sat4-list-item">
          <div class="left">
            <div class="sat4-item-top">
              <span class="sat4-tag ${tag.cls}">${tag.txt}</span>
              <span class="sat4-tag">${row.rfc}</span>
            </div>
            <div class="main">${row.period}</div>
            <div class="sub">${subtitle}</div>
          </div>
          <div class="right">
            ${btnPay}
            ${btnDownload}
          </div>
        </div>
      `;
    }).join('');
  }

  // =====================================================
  // Downloads modal render
  // =====================================================
  function renderDownloads(){
    const wrap = qs('#dTable');
    if (!wrap) return;

    const q  = (qs('#dSearch')?.value || '').trim().toLowerCase();
    const st = (qs('#dStatus')?.value || '').trim().toLowerCase();

    const rows = Array.isArray(CFG.downloads) ? CFG.downloads : [];
    const norm = rows.map(normalizeRow);

    const filtered = norm.filter(x => {
      const hay = (String(x.id)+' '+x.rfc+' '+x.alias+' '+x.period+' '+x.status).toLowerCase();
      if (q && !hay.includes(q)) return false;

      if (st){
        if (st === 'expired' && !x.expired) return false;
        if (st === 'paid' && !x.is_paid) return false;
        if (st === 'done' && !(x.status.includes('done') || x.status.includes('ready') || x.status.includes('list'))) return false;
        if (st === 'processing' && !x.status.includes('proc')) return false;
        if (st === 'pending' && !(x.status.includes('pend') || x.status.includes('pending'))) return false;
      }
      return true;
    });

    if (!filtered.length){
      wrap.innerHTML = `<div class="sat4-mini" style="padding:12px;">Sin resultados.</div>`;
      return;
    }

    wrap.innerHTML = filtered.slice(0, 200).map(row => {
      const tag = statusTag(row);

      const canDownload = (!row.expired && row.is_paid && row.zipUrl);
      const canPay      = (!row.expired && !row.is_paid && (row.status.includes('done') || row.status.includes('ready') || row.status.includes('list')));

      const btnDownload = canDownload
        ? `<button class="sat4-btn sat4-btn-primary" data-act="dl" data-url="${encodeURIComponent(row.zipUrl)}">Descargar</button>`
        : '';

      const btnPay = canPay
        ? `<button class="sat4-btn" data-act="pay" data-id="${encodeURIComponent(row.id)}">Carrito</button>`
        : '';

      const btnDel = row.id
        ? `<button class="sat4-btn sat4-btn-ghost" data-act="del" data-id="${encodeURIComponent(row.id)}">Eliminar</button>`
        : '';

      return `
        <div class="sat4-item" data-id="${String(row.id)}">
          <div class="sat4-item-left">
            <div class="sat4-item-top">
              <span class="sat4-tag ${tag.cls}">${tag.txt}</span>
              <span class="sat4-tag">${row.rfc}</span>
            </div>
            <div class="sat4-item-main">${row.period}</div>
            <div class="sat4-item-sub">${row.alias}</div>
          </div>
          <div class="sat4-item-right">
            ${btnPay}
            ${btnDownload}
            ${btnDel}
          </div>
        </div>
      `;
    }).join('');
  }

  // =====================================================
  // Activity table
  // =====================================================
  function renderActivity(){
    const tbody = qs('#sat4ActivityBody');
    if (!tbody) return;

    const rows = Array.isArray(CFG.downloads) ? CFG.downloads : [];
    const norm = rows.map(normalizeRow).sort((a,b) => parseDateSafe(b.created_at) - parseDateSafe(a.created_at));

    const top = norm.slice(0, 6);
    if (!top.length){
      tbody.innerHTML = `<tr><td colspan="4" class="sat4-td-empty">Sin actividad.</td></tr>`;
      return;
    }

    tbody.innerHTML = top.map(row => {
      const tag = statusTag(row);
      const canDownload = (!row.expired && row.is_paid && row.zipUrl);
      const canPay      = (!row.expired && !row.is_paid && (row.status.includes('done') || row.status.includes('ready') || row.status.includes('list')));

      const actBtn = canDownload
        ? `<button class="sat4-btn sat4-btn-primary" data-act="dl" data-url="${encodeURIComponent(row.zipUrl)}">Descargar</button>`
        : (canPay
          ? `<button class="sat4-btn" data-act="pay" data-id="${encodeURIComponent(row.id)}">Carrito</button>`
          : `<span class="sat4-mini">—</span>`);

      return `
        <tr>
          <td class="mono">${row.rfc}</td>
          <td>${row.period}</td>
          <td><span class="sat4-badge ${tag.cls}">${tag.txt}</span></td>
          <td class="sat4-td-act">${actBtn}</td>
        </tr>
      `;
    }).join('');
  }

  const dRender = qs('#dRender');
  if (dRender) dRender.addEventListener('click', renderDownloads);

  // Actions inside lists
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;

    const act = btn.getAttribute('data-act');

    if (act === 'dl'){
      const u = decodeURIComponent(btn.getAttribute('data-url') || '');
      if (u) window.location.href = u;
      return;
    }

    if (act === 'pay'){
      const cart = R.cartIndex || R.cartCheckout || '';
      if (cart) window.location.href = cart;
      else alert('Carrito no configurado.');
      return;
    }

    if (act === 'del'){
      alert('Eliminar desde esta UI minimal está desactivado por seguridad. (Si quieres, lo conecto a tu ruta cancel/delete).');
      return;
    }
  });

  // Al abrir modal descargas => render inmediato
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-open="sat4ModalDownloads"]');
    if (b) setTimeout(renderDownloads, 0);
  });

  // =====================================================
  // External list (RFC externo) · Premium cards
  // =====================================================
  function escHtml(s){
    return String(s ?? '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function fmtDateShort(x){
    if (!x) return '';
    try{
      const d = new Date(x);
      if (Number.isNaN(d.getTime())) return String(x).slice(0,19).replace('T',' ');
      const pad = (n)=> String(n).padStart(2,'0');
      return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }catch{
      return String(x).slice(0,19).replace('T',' ');
    }
  }

  function pickExternalRows(data){
    // Soporta varias formas:
    // - { ok:true, rows:[...] }
    // - { ok:true, data:{ rows:[...] } }
    // - { ok:true, data:[...] }
    if (!data) return [];
    if (Array.isArray(data.rows)) return data.rows;
    if (Array.isArray(data.data?.rows)) return data.data.rows;
    if (Array.isArray(data.data)) return data.data;
    return [];
  }

  function normalizeExtRow(r){
    // campos comunes
    const id = r?.id ?? r?.uuid ?? '';
    const rfc = String(r?.rfc ?? r?.tax_id ?? '—').toUpperCase().trim() || '—';
    const razon = String(r?.razon_social ?? r?.razon ?? r?.alias ?? r?.name ?? '').trim();

    const statusRaw = String(r?.status ?? r?.estado ?? r?.state ?? 'uploaded').toLowerCase();
    const status =
      statusRaw.includes('ok') || statusRaw.includes('done') || statusRaw.includes('valid') ? 'ok'
      : statusRaw.includes('fail') || statusRaw.includes('error') ? 'bad'
      : 'warn';

    const statusTxt =
      status === 'ok' ? (statusRaw || 'ok')
      : status === 'bad' ? (statusRaw || 'error')
      : (statusRaw || 'uploaded');

    const file = String(r?.file_name ?? r?.filename ?? r?.zip_name ?? r?.zip ?? '').trim();
    const ref  = String(r?.reference ?? r?.ref ?? r?.folio ?? '').trim();
    const created = r?.created_at ?? r?.createdAt ?? r?.uploaded_at ?? r?.updated_at ?? '';

    // Si el “razon” viene vacío, usamos algo más útil
    const title =
      razon || (file ? file : (ref ? ('Ref: ' + ref) : 'Registro externo'));

    return {
      id: String(id),
      rfc,
      title,
      file,
      ref,
      created,
      status,
      statusTxt
    };
  }

  function extTagBadge(cls, txt){
    const c = cls === 'ok' ? 'ok' : (cls === 'bad' ? 'bad' : 'warn');
    return `<span class="sat4-badge ${c}">${escHtml(txt)}</span>`;
  }

    async function loadExternalList(){
    const wrap = qs('#exTable');
    if (!wrap) return;

    // ✅ necesario para tu CSS patch (scroll + cards)
    try{ wrap.classList.add('sat4-ext-list'); }catch{}

    const url = (R.fielList || R.externalZipList || '').trim();
    if (!url){
      wrap.innerHTML = `<div class="sat4-mini" style="padding:12px;">Ruta de listado no configurada.</div>`;
      return;
    }

    wrap.innerHTML = `<div class="sat4-mini" style="padding:12px;">Cargando…</div>`;

    let res, data;
    try{
      ({ res, data } = await fetchJson(url, {
        method:'GET',
        credentials:'same-origin',
        headers:{ 'Accept':'application/json', 'X-CSRF-TOKEN': csrf }
      }));
    }catch{
      wrap.innerHTML = `<div class="sat4-mini" style="padding:12px;">Error de red al listar.</div>`;
      return;
    }

    if (!res?.ok || !data || !data.ok){
      const msg = (data && (data.msg || data.message))
        ? (data.msg || data.message)
        : ('Error listando (HTTP ' + (res?.status || '—') + ')');
      wrap.innerHTML = `<div class="sat4-mini" style="padding:12px;">${escHtml(msg)}</div>`;
      return;
    }

    const rowsRaw = pickExternalRows(data);
    const rows = rowsRaw.map(normalizeExtRow);

    if (!rows.length){
      wrap.innerHTML = `<div class="sat4-mini" style="padding:12px;">Aún no hay registros.</div>`;
      return;
    }

    wrap.innerHTML = rows.slice(0, 200).map(x => {
      const id = x.id || '';

      // ✅ routes robustas (ya soporta __ID__ / {id} / :id)
      const dl  = (R.fielDownload && id) ? routeWithId(R.fielDownload, id) : '';
      const pw  = (R.fielPassword && id) ? routeWithId(R.fielPassword, id) : '';
      const del = (R.fielDestroy  && id) ? routeWithId(R.fielDestroy,  id) : '';

      const meta = [
        x.file ? `Archivo: <span class="mono">${escHtml(x.file)}</span>` : '',
        x.ref ? `Ref: <span class="mono">${escHtml(x.ref)}</span>` : '',
        x.created ? `Fecha: <span class="mono">${escHtml(fmtDateShort(x.created))}</span>` : ''
      ].filter(Boolean).join(' · ');

      const btnPw = pw
        ? `<button class="sat4-btn sat4-btn-ghost" data-ex="pw" data-url="${encodeURIComponent(pw)}">Pass</button>`
        : '';

      const btnDl = dl
        ? `<button class="sat4-btn sat4-btn-primary" data-ex="dl" data-url="${encodeURIComponent(dl)}">ZIP</button>`
        : '';

      const btnDel = del
        ? `<button class="sat4-btn is-danger" data-ex="del" data-url="${encodeURIComponent(del)}" data-danger="1">Eliminar</button>`
        : '';

      return `
        <div class="sat4-ex-card">
          <div class="sat4-ex-top">
            <div class="sat4-ex-tags">
              ${extTagBadge(x.status, x.statusTxt)}
              <span class="sat4-badge">${escHtml(x.rfc)}</span>
            </div>
            <div class="sat4-ex-actions">
              ${btnPw}${btnDl}${btnDel}
            </div>
          </div>

          <div class="sat4-ex-title">${escHtml(x.title)}</div>
          ${meta ? `<div class="sat4-ex-meta">${meta}</div>` : ``}
        </div>
      `;
    }).join('');
  }

  const exRefresh = qs('#exRefresh');
  if (exRefresh) exRefresh.addEventListener('click', () => loadExternalList());

  document.addEventListener('click', (e) => {
    const open = e.target.closest('[data-open="sat4ModalExternal"]');
    if (open) setTimeout(loadExternalList, 0);
  });

  document.addEventListener('click', (e) => {
    const open = e.target.closest('[data-open="sat4ModalExternal"]');
    if (open){
      setTimeout(() => {
        try{ initExternalModeUi(); }catch{}
        try{ loadExternalList(); }catch{}
      }, 0);
    }
  });


  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-ex]');
    if (!b) return;

    const act = b.getAttribute('data-ex');
    const url = decodeURIComponent(b.getAttribute('data-url') || '');
    if (!url) return;

    if (act === 'dl'){
      window.location.href = url;
      return;
    }

    if (act === 'pw'){
      try{
        const { res, data } = await fetchJson(url, {
          method:'GET',
          credentials:'same-origin',
          headers:{ 'Accept':'application/json', 'X-CSRF-TOKEN': csrf }
        });

        if (!res.ok || !data || !data.ok) throw new Error((data && (data.msg || data.message)) ? (data.msg || data.message) : 'Error');
        alert('Contraseña FIEL: ' + (data.password || '—'));
      }catch(err){
        alert(err.message || 'Error');
      }
      return;
    }

    if (act === 'del'){
      if (!confirm('¿Eliminar este ZIP?')) return;
      try{
        const { res, data } = await fetchJson(url, {
          method:'DELETE',
          credentials:'same-origin',
          headers:{ 'Accept':'application/json', 'X-CSRF-TOKEN': csrf }
        });

        if (!res.ok || !data || !data.ok) throw new Error((data && (data.msg || data.message)) ? (data.msg || data.message) : 'Error');
        loadExternalList();
      }catch(err){
        alert(err.message || 'Error');
      }
      return;
    }
  });

  // External upload / invite
  const exOpenUpload = qs('#exOpenUpload');
  if (exOpenUpload) exOpenUpload.addEventListener('click', () => openModal('sat4ModalExtUpload'));

  const exOpenInvite = qs('#exOpenInvite');
  if (exOpenInvite) exOpenInvite.addEventListener('click', () => openModal('sat4ModalExtInvite'));

  // Open Individual Invite (from admin-like pane)
  const exOpenInviteIndividual = qs('#exOpenInviteIndividual');
  if (exOpenInviteIndividual) exOpenInviteIndividual.addEventListener('click', () => openModal('sat4ModalExtInviteIndividual'));

  const exSend = qs('#exSend');
  if (exSend){
    exSend.addEventListener('click', async () => {
      const url = R.externalZipRegister || '';
      const out = qs('#exStatus');
      if (!url){ if(out) out.textContent='Ruta no configurada.'; return; }

      const form = qs('#exUploadForm');
      if (!form) return;

      exSend.disabled = true;
      if (out) out.textContent = 'Enviando…';

      try{
  const fd = new FormData(form);
  const { res, data, txt } = await fetchJson(url, {
    method:'POST',
    credentials:'same-origin',
    headers:{ 'Accept':'application/json', 'X-CSRF-TOKEN': csrf },
    body: fd
  });

      // 🔎 Siempre log en consola (para no volar a ciegas)
      try{
          console.log('[SAT externalZipRegister] HTTP', res?.status, 'data=', data, 'raw=', txt);
        }catch{}

        if (!res?.ok || !data || !data.ok){
          const msg = (data && (data.msg || data.message))
            ? (data.msg || data.message)
            : ('Error (HTTP ' + (res?.status || '—') + ')');

          // ✅ Si viene debug, lo pintamos
          if (data && data.debug){
            const dbg = JSON.stringify(data.debug, null, 2);
            if (out) out.textContent = msg + "\n\nDEBUG:\n" + dbg;
          } else {
            if (out) out.textContent = msg;
          }

          throw new Error(msg);
        }

        if (out) out.textContent = '✅ OK';

        closeAll();
        openModal('sat4ModalExternal');
        setTimeout(loadExternalList, 0);

      }catch(err){
        // si el backend mandó debug, ya lo pintamos arriba.
        if (out && (!String(out.textContent || '').includes('DEBUG:'))){
          out.textContent = (err.message || 'Error');
        }
      }finally{
        exSend.disabled = false;
      }

    });
  }

    // =====================================================
  // External Invites (ZIP + RFC campo-por-campo)
  // ✅ Anti-mezcla (handlers separados)
  // ✅ Anti-submit GET (captura + delegado)
  // ✅ Compat IDs viejos (ZIP) y nuevos (ZIP/RFC)
  // ✅ Fallback duro: POST /cliente/sat/external/invite (ZIP)
  // =====================================================
  (function initExternalInvitesDelegated(){

    function stop(e){ try{ e.preventDefault(); e.stopPropagation(); }catch{} }
    function validEmail(email){
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim());
    }
    function normalizeErr(res, data){
      return (res && res.status === 419) ? 'Sesión expirada (419). Refresca la página.'
        : (data && (data.msg || data.message)) ? (data.msg || data.message)
        : ('Error (HTTP ' + (res?.status ?? '—') + ')');
    }

    async function postForm(url, fd){
      const res = await fetch(url, {
        method:'POST',
        credentials:'same-origin',
        headers:{ 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' },
        body: fd
      });
      const txt = await res.text();
      let data = null; try{ data = JSON.parse(txt); }catch{}
      return { res, data, txt };
    }

    async function postJson(url, payload){
      const res = await fetch(url, {
        method:'POST',
        credentials:'same-origin',
        headers:{
          'X-CSRF-TOKEN': csrf,
          'Accept':'application/json',
          'Content-Type':'application/json'
        },
        body: JSON.stringify(payload || {})
      });
      const txt = await res.text();
      let data = null; try{ data = JSON.parse(txt); }catch{}
      return { res, data, txt };
    }

    // -----------------------------------------------------
    // ZIP INVITE (tu UI actual screenshot usa: #invSend/#invEmail/#invRef/#invStatus)
    // UI nueva recomendada: #invZipSend/#invZipEmail/#invZipRef/#invZipStatus (form #invZipForm)
    // Ruta preferida: R.externalZipInvite
    // Fallback DURO:  /cliente/sat/external/invite   ✅
    // -----------------------------------------------------
    function getZipEls(){
      const btn = qs('#invZipSend') || qs('#invSend');
      if (!btn) return null;

      try{ btn.setAttribute('type','button'); }catch{}

      const form  = qs('#invZipForm') || qs('#invForm') || btn.closest('form');
      const email = qs('#invZipEmail') || qs('#invEmail');
      const ref   = qs('#invZipRef')   || qs('#invRef');
      const out   = qs('#invZipStatus')|| qs('#invStatus');

      return { btn, form, email, ref, out };
    }

    function resolveZipUrl(els){
      // ✅ esta ES la ruta correcta (la que exige el backend)
      const u1 = String(R.externalZipInvite || '').trim();
      if (u1) return u1;

      // Si algún día decides envolver el modal en <form>, respeta action
      const fa = els?.form ? String(els.form.getAttribute('action') || '').trim() : '';
      if (fa) return fa;

      // ✅ fallback duro final
      return '/cliente/sat/external/invite';
    }


    async function sendZip(){
      const els = getZipEls();
      if (!els) return;

      const url   = resolveZipUrl(els);
      const email = (els.email?.value || '').trim();
      const ref   = (els.ref?.value || '').trim();

      if (!validEmail(email)){
        if (els.out) els.out.textContent = 'Correo inválido.';
        return;
      }

      els.btn.disabled = true;
      if (els.out) els.out.textContent = 'Enviando invitación ZIP…';

      try{
        const fd = new FormData();
        fd.append('email', email);
        fd.append('reference', ref);

        const { res, data } = await postForm(url, fd);

        if (!res.ok || !data || !data.ok){
          throw new Error(normalizeErr(res, data));
        }

        if (els.out) els.out.textContent = '✅ Invitación ZIP enviada';

        // opcional: refresca lista para “ver movimiento”
        try{ setTimeout(() => loadExternalList(), 250); }catch{}
      }catch(err){
        if (els.out) els.out.textContent = err?.message || 'Error';
      }finally{
        els.btn.disabled = false;
      }
    }

    // 🔒 BLOQUEA SUBMIT NATIVO (CAPTURA) SOLO para el form ZIP
    document.addEventListener('submit', (e) => {
      const els = getZipEls();
      if (!els?.form) return;
      if (e.target !== els.form) return;
      stop(e);
      sendZip();
      return false;
    }, true);

    // Click delegado ZIP (aunque el DOM cambie)
    document.addEventListener('click', (e) => {
      const isZipBtn = e.target?.closest('#invZipSend') || e.target?.closest('#invSend');
      if (isZipBtn){
        stop(e);
        sendZip();
      }
    }, true);

    // Enter en fields ZIP => fetch (no submit)
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;

      const onZip =
        e.target?.closest('#invZipForm') ||
        e.target?.closest('#invForm') ||
        e.target?.id === 'invEmail' || e.target?.id === 'invRef' ||
        e.target?.id === 'invZipEmail' || e.target?.id === 'invZipRef';

      if (onZip){
        stop(e);
        sendZip();
      }
    }, true);

    // -----------------------------------------------------
    // RFC INVITE (campo por campo)
    // btn: #invRfcSend  form: #invRfcForm
    // Ruta preferida: R.externalRfcInvite (NO fallback duro aquí)
    // -----------------------------------------------------
    function getRfcEls(){
      const btn = qs('#invRfcSend');
      if (!btn) return null;

      try{ btn.setAttribute('type','button'); }catch{}

      const form  = qs('#invRfcForm') || btn.closest('form');
      const out   = qs('#invRfcStatus');
      const email = qs('#invRfcEmail');

      return { btn, form, out, email };
    }

    function resolveRfcUrl(els){
      const u1 = String(R.externalRfcInvite || '').trim();
      if (u1) return u1;
      const fa = els?.form ? String(els.form.getAttribute('action') || '').trim() : '';
      return fa || '';
    }

    function buildRfcPayload(){
      const payload = {
        email: (qs('#invRfcEmail')?.value || '').trim(),
        rfc:   (qs('#invRfcRfc')?.value || '').trim().toUpperCase(),
        name:  (qs('#invRfcName')?.value || '').trim(),
        phone: (qs('#invRfcPhone')?.value || '').trim(),
        // agrega aquí los campos reales del modal RFC campo-por-campo
      };
      Object.keys(payload).forEach(k => {
        if (payload[k] === '' || payload[k] == null) delete payload[k];
      });
      return payload;
    }

    async function sendRfc(){
      const els = getRfcEls();
      if (!els) return;

      const url   = resolveRfcUrl(els);
      const email = (els.email?.value || '').trim();

      if (!url){
        if (els.out) els.out.textContent = 'Ruta no configurada (externalRfcInvite).';
        return;
      }
      if (!validEmail(email)){
        if (els.out) els.out.textContent = 'Correo inválido.';
        return;
      }

      els.btn.disabled = true;
      if (els.out) els.out.textContent = 'Enviando invitación RFC…';

      try{
        const payload = buildRfcPayload();
        const { res, data } = await postJson(url, payload);

        if (!res.ok || !data || !data.ok){
          throw new Error(normalizeErr(res, data));
        }

        if (els.out) els.out.textContent = '✅ Invitación RFC enviada';
      }catch(err){
        if (els.out) els.out.textContent = err?.message || 'Error';
      }finally{
        els.btn.disabled = false;
      }
    }

    document.addEventListener('submit', (e) => {
      const els = getRfcEls();
      if (!els?.form) return;
      if (e.target !== els.form) return;
      stop(e);
      sendRfc();
      return false;
    }, true);

    document.addEventListener('click', (e) => {
      const isBtn = e.target?.closest('#invRfcSend');
      if (isBtn){
        stop(e);
        sendRfc();
      }
    }, true);

    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;
      const onRfc = e.target?.closest('#invRfcForm');
      if (onRfc){
        stop(e);
        sendRfc();
      }
    }, true);

  })();

    // =====================================================
  // External Invite (INDIVIDUAL)
  // - Modal: sat4ModalExtInviteIndividual
  // - Inputs: #invEmailInd #invRefInd
  // - Button: #invSendInd
  // - Output: #invStatusInd
  // - Route: R.externalInviteIndividual
  // =====================================================
  (function initExternalInviteIndividual(){
    const btn = qs('#invSendInd');
    if (!btn) return;

    const out = qs('#invStatusInd');
    const inEmail = qs('#invEmailInd');
    const inRef   = qs('#invRefInd');

    function stop(e){ try{ e.preventDefault(); e.stopPropagation(); }catch{} }
    function validEmail(email){
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim());
    }
    function normalizeErr(res, data){
      return (res && res.status === 419) ? 'Sesión expirada (419). Refresca la página.'
        : (data && (data.msg || data.message)) ? (data.msg || data.message)
        : ('Error (HTTP ' + (res?.status ?? '—') + ')');
    }

    async function send(){
      const url = String(R.externalInviteIndividual || '').trim();
      const email = (inEmail?.value || '').trim();
      const ref   = (inRef?.value || '').trim();

      if (!url){
        if (out) out.textContent = 'Ruta no configurada (externalInviteIndividual).';
        return;
      }
      if (!validEmail(email)){
        if (out) out.textContent = 'Correo inválido.';
        return;
      }

      btn.disabled = true;
      if (out) out.textContent = 'Enviando invitación (Individual)…';

      try{
        const fd = new FormData();
        fd.append('email', email);
        fd.append('reference', ref);

        const { res, data } = await fetchJson(url, {
          method:'POST',
          credentials:'same-origin',
          headers:{ 'Accept':'application/json', 'X-CSRF-TOKEN': csrf },
          body: fd
        });

        if (!res?.ok || !data || !data.ok){
          throw new Error(normalizeErr(res, data));
        }

        if (out) out.textContent = '✅ Invitación (Individual) enviada';

        // opcional: cerrar y volver al admin-like
        try{
          closeAll();
          openModal('sat4ModalExternal');
          setTimeout(() => {
            try{ initExternalModeUi(); setExternalMode('individual'); }catch{}
            try{ loadExternalList(); }catch{}
          }, 0);
        }catch{}
      }catch(err){
        if (out) out.textContent = err?.message || 'Error';
      }finally{
        btn.disabled = false;
      }
    }

    // Forzar type=button
    try{ btn.setAttribute('type','button'); }catch{}

    btn.addEventListener('click', (e)=>{ stop(e); send(); }, true);

    // Enter en inputs
    [inEmail, inRef].filter(Boolean).forEach(inp => {
      inp.addEventListener('keydown', (e) => {
        if (e.key === 'Enter'){ stop(e); send(); }
      }, true);
    });
  })();


  // =====================================================
  // Portal: Iniciar descarga (Mes/Año)
  // =====================================================
  const startPortal  = qs('#sat4StartPortal');
  const ringRefresh  = qs('#sat4RingRefresh');

  function setYmDefaults(){
    const y = qs('#sat4YmYear');
    const m = qs('#sat4YmMonth');
    const now = new Date();
    if (y && !y.value) y.value = String(now.getFullYear());
    if (m && !m.value) m.value = String(now.getMonth() + 1);
  }

  function monthRangeISO(year, month){
    const y  = Number(year);
    const mm = Number(month);
    if (!Number.isFinite(y) || !Number.isFinite(mm) || mm < 1 || mm > 12) return null;

    const from = new Date(y, mm - 1, 1);
    const to   = new Date(y, mm, 0);

    const pad = (n)=> String(n).padStart(2,'0');
    const fmt = (d)=> `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

    return { from: fmt(from), to: fmt(to) };
  }

  function openYmModal(){
    setYmDefaults();
    openModal('sat4ModalYm');
  }

  function continueYm(){
    const out   = qs('#sat4YmStatus');
    const year  = (qs('#sat4YmYear')?.value || '').trim();
    const month = (qs('#sat4YmMonth')?.value || '').trim();
    const r = monthRangeISO(year, month);

    if (!r){
      if (out) out.textContent = 'Selecciona un año/mes válido.';
      return;
    }

    closeAll();
    openModal('sat4ModalRequest');

    const inFrom = qs('#sat4ReqForm input[name="from"]');
    const inTo   = qs('#sat4ReqForm input[name="to"]');
    if (inFrom) inFrom.value = r.from;
    if (inTo)   inTo.value   = r.to;

    const tipo = qs('#sat4ReqForm select[name="tipo"]');
    if (tipo && (!tipo.value || String(tipo.value).toLowerCase() === 'emitidos')){
      tipo.value = 'recibidos';
    }

    if (out) out.textContent = '✅ Rango cargado en descarga avanzada.';
  }

  if (startPortal) startPortal.addEventListener('click', openYmModal);

  const ymContinue = qs('#sat4YmContinue');
  if (ymContinue) ymContinue.addEventListener('click', continueYm);

  if (ringRefresh) ringRefresh.addEventListener('click', () => {
    try{ initPortal(); }catch{}
    try{ renderActivity(); }catch{}
  });

  // =====================================================
  // Init (home)
  // =====================================================
  try{ renderLatest(); }catch{}
  try{ renderActivity(); }catch{}
  try{ initNotifyUI(); }catch{}
  try{ initPortal(); }catch{}
})();
