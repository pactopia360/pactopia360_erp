// public/assets/client/js/sat-dashboard.js
// PACTOPIA360 · SAT · Dashboard (Chart + KPIs + External ZIP/FIEL table)
//
// Requiere: Chart.js + window.P360_SAT (inyección Blade)
// - Usa P360_SAT.routes.dashboardStats para gráfica/kpis
// - Usa P360_SAT.routes.fielList (preferente) para listado ZIPs cargados
//
// ✅ Robust v6 (2026-01-31):
// - satFetchJson(): CSRF + JSON/HTML safe + errores normalizados
// - pickRows(): soporta muchísimas formas de respuesta (paginador, wrapper data/data, payload, etc.)
// - Debug opcional: si window.P360_SAT.debug === true loguea rutas/respuestas
// - External ZIP list: prioriza externalZipList si existe, si no fielList/fielExternalList
// - Render defensivo: aunque cambien keys, intenta mapear id/rfc/nombre/tamaño/status/password
// - Columna contraseña + reveal (si hay ruta), botón editar (si hay modal/form)
// - No rompe si no existe tabla o no existe chart

document.addEventListener('DOMContentLoaded', function () {
  if (window.__P360_SAT_DASHBOARD__) return;
  window.__P360_SAT_DASHBOARD__ = true;

  const CFG    = window.P360_SAT || {};
  const ROUTES = CFG.routes || {};
  const DEBUG  = !!CFG.debug;

  // =====================================================
  // Constantes UI
  // =====================================================
  const ZIP_TABLE_COLS = 7; // ZIP | RFC | RAZÓN | PASSWORD | TAMAÑO | ESTADO | ACCIONES

  // =====================================================
  // Helpers
  // =====================================================
  function log() {
    if (!DEBUG) return;
    try { console.log.apply(console, ['[SAT-DASH]'].concat([].slice.call(arguments))); } catch (e) {}
  }

  function toast(msg, kind = 'info') {
    try {
      if (window.P360 && typeof window.P360.toast === 'function') {
        if (kind === 'error' && window.P360.toast.error) return window.P360.toast.error(msg);
        if (kind === 'success' && window.P360.toast.success) return window.P360.toast.success(msg);
        return window.P360.toast(msg);
      }
    } catch (e) {}
    console.log('[SAT-DASH]', msg);
  }

  function ymd(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  function escapeHtml(s) {
    return String(s ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function fmtBytes(n) {
    const x = Number(n || 0);
    if (!isFinite(x) || x <= 0) return '—';
    if (x < 1024) return `${x} B`;
    if (x < 1024*1024) return `${(x/1024).toFixed(1)} KB`;
    if (x < 1024*1024*1024) return `${(x/1024/1024).toFixed(2)} MB`;
    return `${(x/1024/1024/1024).toFixed(3)} GB`;
  }

  function getCsrf() {
    return String(
      CFG.csrf
      || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      || ''
    );
  }

  async function safeReadJson(res) {
    try { return await res.json(); } catch (e) { return null; }
  }

  async function safeReadText(res) {
    try { return await res.text(); } catch (e) { return ''; }
  }

  function cssEscape(v) {
    const s = String(v ?? '');
    try {
      if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(s);
    } catch (e) {}
    // fallback sencillo para selector attribute
    return s.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  // =====================================================
  // satFetchJson: robusto (JSON + HTML + 501 friendly)
  // =====================================================
  async function satFetchJson(url, opts = {}) {
    const csrf = getCsrf();

    const headers = Object.assign(
      { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      (csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
      (opts.headers || {})
    );

    let res = null;
    try {
      res = await fetch(url, Object.assign({}, opts, {
        credentials: opts.credentials || 'same-origin',
        headers
      }));
    } catch (e) {
      return { ok:false, status:0, data:{ ok:false, msg:'No se pudo conectar con el servidor.' } };
    }

    // 501: en implementación
    if (res.status === 501) {
      const data501 = await safeReadJson(res);
      return {
        ok: false,
        status: 501,
        notImplemented: true,
        data: data501 || { ok:false, msg:'Funcionalidad en implementación.' }
      };
    }

    // Intentar JSON primero
    const data = await safeReadJson(res);

    // Si NO es JSON (HTML 404 / error view / login)
    if (!data) {
      const txt = await safeReadText(res);
      log('Non-JSON response', { url, status: res.status, sample: (txt || '').slice(0, 500) });

      if (!res.ok) {
        return {
          ok: false,
          status: res.status,
          data: { ok:false, msg:`Error HTTP ${res.status}.`, raw:(txt || '').slice(0, 500) }
        };
      }

      // OK pero no JSON => error lógico para AJAX
      return {
        ok: false,
        status: res.status,
        nonJson: true,
        data: {
          ok: false,
          msg: 'Respuesta no-JSON (200). El endpoint devolvió HTML; debe responder JSON para AJAX.',
          raw: (txt || '').slice(0, 500)
        }
      };
    }

    // Si HTTP falla o payload declara ok:false
    if (!res.ok || data.ok === false) {
      return { ok:false, status: res.status, data: data || { ok:false, msg:'Error inesperado.' } };
    }

    // ✅ ÉXITO REAL
    return { ok:true, status: res.status, data };
  }

  // =====================================================
  // API estable
  // =====================================================
  if (!window.P360_SAT_API || typeof window.P360_SAT_API !== 'object') window.P360_SAT_API = {};

  function pickRouteFirst(/* keys */) {
    const routes = (window.P360_SAT && window.P360_SAT.routes) ? window.P360_SAT.routes : {};
    for (let i = 0; i < arguments.length; i++) {
      const k = arguments[i];
      const v = String(routes[k] || '').trim();
      if (v) return v;
    }
    return '';
  }

  // =====================================================
  // API: listado ZIPs (FIEL preferente)
  // =====================================================
  // =====================================================
// API: listado ZIPs (FIEL preferente)
// =====================================================
window.P360_SAT_API.externalZipList = async function (params = {}) {
  const routes = (window.P360_SAT && window.P360_SAT.routes) ? window.P360_SAT.routes : {};

  // 1) Preferencia dura: externalZipList
  const direct = String(routes.externalZipList || '').trim();

  // 2) Fallback (solo si no existe externalZipList)
  const fallback =
    String(routes.fielList || '').trim() ||
    String(routes.fielExternalList || '').trim() ||
    '';

  const url = direct || fallback;

  if (!url) {
    return {
      ok: false,
      status: 0,
      data: { ok: false, msg: 'Ruta de listado no configurada.', rows: [], count: 0, data: { rows: [], count: 0 } }
    };
  }

  // =====================================================
  // ✅ Defaults defensivos para evitar 422
  // Muchos backends validan offset/limit aunque sean GET
  // =====================================================
  const safe = Object.assign(
    { limit: 50, offset: 0, status: '', q: '' },
    (params && typeof params === 'object') ? params : {}
  );

  // Normaliza tipos
  safe.limit  = Number.isFinite(Number(safe.limit))  ? String(Number(safe.limit))  : '50';
  safe.offset = Number.isFinite(Number(safe.offset)) ? String(Number(safe.offset)) : '0';

  if (safe.status === null || safe.status === undefined) safe.status = '';
  if (safe.q === null || safe.q === undefined) safe.q = '';

  // QS: params planos (incluye strings vacíos a propósito)
  const qs = new URLSearchParams();
  Object.keys(safe).forEach((k) => {
    const v = safe[k];
    if (v === undefined || v === null) return;
    if (typeof v === 'object') return;
    qs.set(String(k), String(v));
  });

  const finalUrl = qs.toString()
    ? (url + (url.includes('?') ? '&' : '?') + qs.toString())
    : url;

  log('externalZipList finalUrl:', finalUrl);

  let resp = await satFetchJson(finalUrl, { method: 'GET' });

  // =====================================================
  // ✅ Auto-fallback:
  // Si usamos externalZipList y responde 422/501/Non-JSON/ok:false,
  // reintentamos con fielList/fielExternalList.
  // =====================================================
  const shouldFallback =
    !!direct && !!fallback && (
      resp?.status === 422 ||
      resp?.status === 501 ||
      resp?.nonJson === true ||
      resp?.ok === false
    );

  if (shouldFallback) {
    log('externalZipList failed, trying fallback route:', { status: resp?.status, fallback });

    const fallbackUrl = qs.toString()
      ? (fallback + (fallback.includes('?') ? '&' : '?') + qs.toString())
      : fallback;

    resp = await satFetchJson(fallbackUrl, { method: 'GET' });
  }

  // Normalización
  const isObj = (x) => x && typeof x === 'object' && !Array.isArray(x);

  function pickPayload(root) {
    if (Array.isArray(root)) return root;
    if (!isObj(root)) return root;

    const hasRows = Array.isArray(root.rows) || Array.isArray(root?.data?.rows);
    const hasCount = typeof root.count === 'number' || typeof root?.data?.count === 'number';

    if (isObj(root.data)) {
      const d = root.data;
      const dHasRows = Array.isArray(d.rows) || Array.isArray(d?.data?.rows);
      const dHasCount = typeof d.count === 'number' || typeof d?.data?.count === 'number';
      if (dHasRows || dHasCount) return d;
    }

    if (hasRows || hasCount) return root;
    return root;
  }

  const root = (resp && typeof resp === 'object') ? (resp.data ?? null) : null;
  const payload = pickPayload(root);

  let rows = [];
  let count = 0;

  try {
    if (Array.isArray(payload)) {
      rows = payload;
      count = payload.length;
    } else if (isObj(payload)) {
      const r1 = payload.rows;
      const r2 = payload?.data?.rows;
      const r3 = payload.data;

      if (Array.isArray(r1)) rows = r1;
      else if (Array.isArray(r2)) rows = r2;
      else if (Array.isArray(r3)) rows = r3;
      else rows = [];

      const c1 = payload.count;
      const c2 = payload?.data?.count;

      if (typeof c1 === 'number' && Number.isFinite(c1)) count = c1;
      else if (typeof c2 === 'number' && Number.isFinite(c2)) count = c2;
      else count = rows.length;
    } else {
      rows = [];
      count = 0;
    }
  } catch (e) {
    rows = [];
    count = 0;
  }

  const normalized = isObj(payload) ? Object.assign({}, payload) : {};
  normalized.rows = rows;
  normalized.count = count;

  if (isObj(normalized.data)) {
    if (!Array.isArray(normalized.data.rows)) normalized.data.rows = rows;
    if (typeof normalized.data.count !== 'number' || !Number.isFinite(normalized.data.count)) normalized.data.count = count;
  } else {
    normalized.data = { rows, count };
  }

  if (typeof normalized.ok !== 'boolean') {
    normalized.ok = (typeof resp?.ok === 'boolean') ? resp.ok : (resp?.status === 200);
  }

  const out = Object.assign({}, resp, { data: normalized });

  log('externalZipList normalized:', {
    status: out?.status,
    count: out?.data?.count,
    rowsLen: (out?.data?.rows || []).length,
    using: direct ? 'externalZipList' : (fallback ? 'fielList/fallback' : 'none')
  });

  return out;
};


  // =====================================================
  // UI tabla ZIPs: selects
  // =====================================================
  function findExternalZipTbody() {
    return (
      document.querySelector('#fielZipTbody') ||
      document.querySelector('#externalZipTbody') ||
      document.querySelector('#externalFielTbody') ||
      document.querySelector('[data-p360-external-zip-tbody]') ||
      document.querySelector('[data-p360-external-fiel-tbody]')
    );
  }

  function findExternalZipRefreshBtn() {
    return (
      document.querySelector('#btnFielRefresh') ||
      document.querySelector('#externalZipRefresh') ||
      document.querySelector('#externalFielRefresh') ||
      document.querySelector('[data-p360-external-zip-refresh]') ||
      document.querySelector('[data-p360-external-fiel-refresh]')
    );
  }

  // =====================================================
  // pickRows: ultra robust
  // =====================================================
  function pickRows(payload) {
    if (!payload) return [];
    if (Array.isArray(payload)) return payload;

    const directKeys = ['rows', 'items', 'list', 'result', 'results'];
    for (const k of directKeys) {
      if (Array.isArray(payload[k])) return payload[k];
    }

    if (Array.isArray(payload.data)) return payload.data;

    if (payload.data && typeof payload.data === 'object') {
      if (Array.isArray(payload.data.data)) return payload.data.data;
      if (Array.isArray(payload.data.items)) return payload.data.items;
      if (Array.isArray(payload.data.rows)) return payload.data.rows;
    }

    const wrappers = ['payload', 'response', 'meta', 'body'];
    for (const w of wrappers) {
      if (payload[w]) {
        const got = pickRows(payload[w]);
        if (Array.isArray(got) && got.length) return got;
      }
    }

    return [];
  }

  // =====================================================
  // Render tabla
  // =====================================================
  function renderExternalZipRows(rows, rawPayloadForDebug) {
    const tbody = findExternalZipTbody();
    if (!tbody) return;

    // =========================
    // Normalizador robusto
    // =========================
    function normalizeRows(inputRows, raw) {
      try {
        if (Array.isArray(inputRows) && inputRows.length) return inputRows;

        const p = raw;

        if (Array.isArray(p)) return p;

        const cand =
          (p && Array.isArray(p.rows) ? p.rows : null) ||
          (p && p.data && Array.isArray(p.data.rows) ? p.data.rows : null) ||
          (p && p.data && p.data.data && Array.isArray(p.data.data.rows) ? p.data.data.rows : null) ||
          (p && p.data && Array.isArray(p.data.data) ? p.data.data : null) ||
          (p && Array.isArray(p.data) ? p.data : null) ||
          (p && p.data && Array.isArray(p.data.items) ? p.data.items : null) ||
          (p && Array.isArray(p.items) ? p.items : null) ||
          null;

        return Array.isArray(cand) ? cand : [];
      } catch (_e) {
        return [];
      }
    }

    const arr = normalizeRows(rows, rawPayloadForDebug);

    // OJO: esta tabla ahora tiene 7 columnas (agregamos contraseña)
    const COLS = 7;

    if (!arr.length) {
      log('No rows to render. Raw payload:', rawPayloadForDebug);
      tbody.innerHTML = `
        <tr>
          <td colspan="${COLS}" class="t-center text-muted" style="padding:14px;">
            Sin registros.
          </td>
        </tr>
      `.trim();
      return;
    }

    const routes = (window.P360_SAT && window.P360_SAT.routes) ? window.P360_SAT.routes : {};
    const rtDownload = String(routes.fielDownload || '');
    const rtUpdate   = String(routes.fielUpdate   || '');
    const rtDestroy  = String(routes.fielDestroy  || '');
    const rtPass     = String(routes.fielPassword || routes.fielRevealPassword || '');

    function pickId(r) {
      const cand = [
        r.id, r.zip_id, r.fiel_id, r.external_id, r.external_zip_id,
        r.file_id, r.record_id, r.uuid
      ].find(v => v !== undefined && v !== null && String(v).trim() !== '');
      return cand ? String(cand).trim() : '';
    }

    function buildUrl(pattern, id) {
      if (!pattern || !id) return '';
      if (pattern.includes('__ID__')) return pattern.replaceAll('__ID__', encodeURIComponent(id));
      if (pattern.includes('{id}'))   return pattern.replaceAll('{id}', encodeURIComponent(id));
      return pattern.replace(/\/$/, '') + '/' + encodeURIComponent(id);
    }

    function badge(stRaw) {
      const st = String(stRaw || '').trim();
      if (!st) return `<span class="badge" style="opacity:.75">—</span>`;
      const lower = st.toLowerCase();
      let cls = 'badge';
      if (lower.includes('ok') || lower.includes('listo') || lower.includes('ready') || lower.includes('done')) cls = 'badge badge-success';
      else if (lower.includes('pend') || lower.includes('proc') || lower.includes('queue')) cls = 'badge badge-soft';
      else if (lower.includes('error') || lower.includes('fail') || lower.includes('rech')) cls = 'badge badge-danger';
      return `<span class="${cls}" style="opacity:.95">${escapeHtml(st)}</span>`;
    }

    function pickRfc(r) {
      return String(r.rfc || r.RFC || '').trim();
    }

    function pickRazon(r) {
      return String(r.razon_social || r.razonSocial || r.nombre || r.company || '').trim();
    }

    function pickName(r) {
      return String(r.file_name || r.fileName || r.name || r.zip_name || r.filename || 'ZIP').trim();
    }

    function pickSize(r) {
      const v = (r.file_size ?? r.size_bytes ?? r.zip_bytes ?? r.bytes ?? r.size ?? 0);
      const n = Number(v);
      return Number.isFinite(n) ? n : 0;
    }

    function pickStatus(r) {
      return String(r.status || r.estado || r.state || r.sat_status || r.estatus || '').trim();
    }

    function pickHasPassword(r) {
      const v = r.has_password ?? r.hasPass ?? r.password_set ?? r.passwordSet ?? r.has_fiel_password ?? null;
      if (v === true || v === 1 || v === '1') return true;
      if (v === false || v === 0 || v === '0') return false;

      const m = String(r.password_mask ?? r.fiel_password_mask ?? '').trim();
      if (m) return true;

      return false;
    }

    function pickPasswordMask(r) {
      const m = String(r.password_mask ?? r.fiel_password_mask ?? '').trim();
      if (m) return m;
      return pickHasPassword(r) ? '••••••••' : '—';
    }

    tbody.innerHTML = arr.map((r) => {
      const id = pickId(r);

      const rfc   = escapeHtml(pickRfc(r));
      const razon = escapeHtml(pickRazon(r));
      const name  = escapeHtml(pickName(r));
      const size  = fmtBytes(pickSize(r));
      const stRaw = pickStatus(r);

      const urlDownload = buildUrl(rtDownload, id);
      const urlDestroy  = buildUrl(rtDestroy, id);
      const urlUpdate   = buildUrl(rtUpdate, id);

      const hasPass  = pickHasPassword(r);
      const passMask = escapeHtml(pickPasswordMask(r));
      const urlPass  = buildUrl(rtPass, id);

      const btnEdit = (id && urlUpdate)
        ? `<button type="button"
              class="btn icon-only"
              data-action="fiel-edit"
              data-id="${escapeHtml(id)}"
              data-rfc="${escapeHtml(pickRfc(r))}"
              data-razon="${escapeHtml(pickRazon(r))}"
              data-url-update="${escapeHtml(urlUpdate)}"
              data-tip="Editar">✏️</button>`
        : `<button type="button" class="btn icon-only" disabled data-tip="${!id ? 'Sin ID' : 'Ruta update no configurada'}">✏️</button>`;

      const btnDownload = (id && urlDownload)
        ? `<button type="button" class="btn icon-only" data-action="fiel-download" data-url="${escapeHtml(urlDownload)}" data-id="${escapeHtml(id)}" data-tip="Descargar">⬇️</button>`
        : `<button type="button" class="btn icon-only" disabled data-tip="${!id ? 'Sin ID' : 'Ruta download no configurada'}">⬇️</button>`;

      const btnDelete = (id && urlDestroy)
        ? `<button type="button" class="btn icon-only" data-action="fiel-destroy" data-url="${escapeHtml(urlDestroy)}" data-id="${escapeHtml(id)}" data-rfc="${escapeHtml(pickRfc(r))}" data-tip="Eliminar">🗑️</button>`
        : `<button type="button" class="btn icon-only" disabled data-tip="${!id ? 'Sin ID' : 'Ruta destroy no configurada'}">🗑️</button>`;

      const btnPass = (id && hasPass && urlPass)
        ? `<button type="button"
              class="btn icon-only"
              data-action="fiel-pass-toggle"
              data-id="${escapeHtml(id)}"
              data-url="${escapeHtml(urlPass)}"
              data-tip="Ver contraseña">👁</button>`
        : `<button type="button" class="btn icon-only" disabled data-tip="${!hasPass ? 'Sin contraseña' : (!urlPass ? 'Ruta password no configurada' : 'No disponible')}">👁</button>`;

      return `
        <tr data-id="${escapeHtml(id)}">
          <td>${name || 'ZIP'}</td>
          <td class="t-center mono">${rfc || '—'}</td>
          <td>${razon || '—'}</td>

          <td class="t-center">
            <div style="display:flex; gap:8px; justify-content:center; align-items:center;">
              <span class="mono" data-pass-mask="${escapeHtml(id)}">${passMask}</span>
              ${btnPass}
            </div>
          </td>

          <td class="t-right">${size}</td>
          <td class="t-center">${badge(stRaw)}</td>
          <td class="t-center">
            <div class="sat-row-actions" style="display:flex; gap:6px; justify-content:center; align-items:center;">
              ${btnEdit}
              ${btnDownload}
              ${btnDelete}
            </div>
          </td>
        </tr>
      `.trim();
    }).join('');
  }


  // =====================================================
  // Actions
  // =====================================================
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-action="fiel-download"]');
    if (!btn) return;
    const url = btn.getAttribute('data-url') || '';
    if (!url) return;
    window.location.href = url;
  });

  // Edit modal opener (si existe modal)
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-action="fiel-edit"]');
    if (!btn) return;

    const id  = btn.getAttribute('data-id') || '';
    const rfc = btn.getAttribute('data-rfc') || '';
    const razon = btn.getAttribute('data-razon') || '';
    const urlUpdate = btn.getAttribute('data-url-update') || '';

    const modal = document.getElementById('modalEditZip');
    const form  = document.getElementById('formEditZip');

    if (!modal || !form) {
      toast('Modal de edición no está disponible en la vista.', 'info');
      return;
    }

    const rfcEl  = document.getElementById('editZipRfc');
    const razEl  = document.getElementById('editZipRazon');
    const idEl   = document.getElementById('editZipId');
    const passEl = document.getElementById('editZipPass');

    if (rfcEl) rfcEl.value = rfc;
    if (razEl) razEl.value = razon;
    if (idEl)  idEl.value  = id;
    if (passEl) passEl.value = '';

    if (urlUpdate) form.setAttribute('action', urlUpdate);

    modal.style.display = 'flex';
    document.body.classList.add('sat-modal-open');
  });

  // Reveal password toggle
  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('[data-action="fiel-pass-toggle"]');
    if (!btn) return;

    const id = btn.getAttribute('data-id') || '';
    const url = btn.getAttribute('data-url') || '';
    if (!id || !url) return;

    const span = document.querySelector(`[data-pass-mask="${cssEscape(id)}"]`);
    if (!span) return;

    const current = String(span.textContent || '').trim();
    const isMasked = (current === '••••••••' || current === '—' || current.includes('•'));

    // si ya está revelado => ocultar
    if (!isMasked) {
      span.textContent = '••••••••';
      return;
    }

    btn.disabled = true;
    const old = btn.textContent;
    btn.textContent = '…';

    try {
      const resp = await satFetchJson(url, { method: 'GET' });
      if (!resp || resp.ok !== true) {
        toast(resp?.data?.msg || 'No se pudo obtener la contraseña.', 'error');
        return;
      }

      const pass = String(resp?.data?.password || resp?.data?.data?.password || '').trim();
      if (!pass) {
        toast('No hay contraseña guardada para este ZIP.', 'info');
        span.textContent = '—';
        return;
      }

      span.textContent = pass;

      // auto-ocultar
      setTimeout(() => {
        try { span.textContent = '••••••••'; } catch (e) {}
      }, 12000);

    } catch (err) {
      toast(err?.message || 'Error al obtener contraseña.', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = old || '👁';
    }
  });

  // Submit modal edit (AJAX)
  document.addEventListener('submit', async function (e) {
    const form = e.target && e.target.closest ? e.target.closest('#formEditZip') : null;
    if (!form) return;

    e.preventDefault();

    const action = form.getAttribute('action') || '';
    if (!action) { toast('Ruta de actualización no configurada.', 'error'); return; }

    const fd = new FormData(form);

    const resp = await satFetchJson(action, {
      method: 'POST',
      body: fd
    });

    if (!resp || resp.ok !== true) {
      toast(resp?.data?.msg || 'No se pudo guardar.', 'error');
      return;
    }

    toast(resp?.data?.msg || 'Actualizado.', 'success');

    const modal = document.getElementById('modalEditZip');
    if (modal) modal.style.display = 'none';
    document.body.classList.remove('sat-modal-open');

    loadExternalZipList().catch(()=>{});
  });

  // Destroy
  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('[data-action="fiel-destroy"]');
    if (!btn) return;

    const url = btn.getAttribute('data-url') || '';
    const id  = btn.getAttribute('data-id') || '';
    const rfc = btn.getAttribute('data-rfc') || '';
    if (!url || !id) return;

    const ok = confirm(`¿Eliminar ZIP${rfc ? ' de ' + rfc : ''}? Esta acción no se puede deshacer.`);
    if (!ok) return;

    const resp = await satFetchJson(url, { method:'DELETE' });
    if (!resp || resp.ok !== true) {
      toast(resp?.data?.msg || 'No se pudo eliminar.', 'error');
      return;
    }

    toast(resp?.data?.msg || 'Eliminado.', 'success');
    loadExternalZipList().catch(()=>{});
  });

  // =====================================================
  // Loader listado ZIPs
  // =====================================================
  async function loadExternalZipList() {
    const tbody = findExternalZipTbody();
    if (!tbody) return;

    tbody.innerHTML = `
      <tr><td colspan="${ZIP_TABLE_COLS}" class="t-center text-muted" style="padding:14px;">Cargando…</td></tr>
    `.trim();

    const resp = await window.P360_SAT_API.externalZipList({ limit: 50, offset: 0, status: '', q: '' });

    log('externalZipList response:', resp);

    if (!resp || resp.ok !== true) {
      tbody.innerHTML = `
        <tr><td colspan="${ZIP_TABLE_COLS}" class="t-center text-muted" style="padding:14px;">
          ${escapeHtml(resp?.data?.msg || 'No se pudo cargar el listado.')}
        </td></tr>
      `.trim();
      return;
    }

    const data = resp.data || {};
    const rows = pickRows(data);

    renderExternalZipRows(rows, data);
  }

  // Refresh hook
  (function hookExternalZipRefresh(){
    const btn = findExternalZipRefreshBtn();
    if (!btn) return;
    btn.addEventListener('click', function(){
      loadExternalZipList().catch(()=>{});
    });
  })();

  // initial
  loadExternalZipList().catch(()=>{});

  // =====================================================
  // Dashboard Chart + KPIs
  // =====================================================
  const canvas = document.getElementById('satMovChart');
  if (!canvas) return;

  const elFrom  = document.getElementById('satMovFrom');
  const elTo    = document.getElementById('satMovTo');
  const elApply = document.getElementById('satMovApply');

  const elStart      = document.querySelector('[data-kpi="start"], #kpiStart, #satKpiStart');
  const elCreated    = document.querySelector('[data-kpi="created"], #kpiCreated, #satKpiCreated');
  const elAvailable  = document.querySelector('[data-kpi="available"], #kpiAvailable, #satKpiAvailable');
  const elDownloaded = document.querySelector('[data-kpi="downloaded"], #kpiDownloaded, #satKpiDownloaded');

  function setText(el, v) { if (el) el.textContent = String(v ?? 0); }

  function destroyAnyChartBoundToCanvas() {
    try {
      if (typeof Chart !== 'undefined' && Chart.getChart) {
        const existing = Chart.getChart(canvas);
        if (existing) existing.destroy();
      }
    } catch (e) {}
  }

  function buildChart(labels, values) {
    if (typeof Chart === 'undefined') {
      toast('Chart.js no está cargado.', 'error');
      return;
    }

    destroyAnyChartBoundToCanvas();
    const ctx = canvas.getContext('2d');

    const css = (name, fb) => {
      try {
        const v = getComputedStyle(document.documentElement).getPropertyValue(name);
        const s = String(v || '').trim();
        return s || fb;
      } catch (e) { return fb; }
    };

    const stroke = css('--sx-primary', '#2563eb');
    const fill   = css('--sx-primary-soft', 'rgba(37,99,235,.18)');

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: Array.isArray(labels) ? labels : [],
        datasets: [{
          label: 'Descargas',
          data: Array.isArray(values) ? values : [],
          tension: 0.35,
          fill: true,
          borderColor: stroke,
          backgroundColor: fill,
          pointBackgroundColor: stroke,
          pointBorderColor: stroke,
          pointRadius: 4,
          pointHoverRadius: 6,
          borderWidth: 2,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  function hasLocalChart() {
    const lc = CFG.localChart || {};
    return Array.isArray(lc.labels) && lc.labels.length && Array.isArray(lc.counts) && lc.counts.length;
  }

  function drawLocalChart() {
    const lc = CFG.localChart || {};
    const labels = Array.isArray(lc.labels) ? lc.labels : [];
    const counts = Array.isArray(lc.counts) ? lc.counts : [];
    const safeLabels = labels.length ? labels : ['—','—','—','—','—','—','—','—'];
    const safeCounts = counts.length ? counts : new Array(safeLabels.length).fill(0);
    buildChart(safeLabels, safeCounts);
  }

  async function fetchStats(from, to) {
    const url = ROUTES.dashboardStats;
    if (!url) throw new Error('Falta P360_SAT.routes.dashboardStats (cliente.sat.dashboard.stats).');

    const qs = new URLSearchParams();
    if (from) qs.set('from', String(from));
    if (to)   qs.set('to', String(to));

    const finalUrl = url + (url.includes('?') ? '&' : '?') + qs.toString();

    const res = await fetch(finalUrl, { headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' } });
    const data = await safeReadJson(res) || {};

    if (!res.ok || data.ok === false) {
      const msg = data.msg || data.message || 'No se pudo cargar dashboard.';
      const err = new Error(msg);
      err.__httpStatus = res.status;
      err.__payload = data;
      throw err;
    }

    return data;
  }

  function applyPayload(p) {
    const k = p?.kpi || {};
    setText(elStart,      k.start);
    setText(elCreated,    k.created);
    setText(elAvailable,  k.available);
    setText(elDownloaded, k.downloaded);

    function normalizeSerie(raw) {
      let labels = [];
      let values = [];

      if (Array.isArray(raw)) {
        try {
          const arr = raw.filter(x => x && typeof x === 'object');
          labels = arr.map(x => String(x.date ?? x.label ?? x.day ?? '').trim()).filter(Boolean);
          values = arr.map(x => {
            const v = Number(x.count ?? x.value ?? x.total ?? 0);
            return Number.isFinite(v) ? v : 0;
          });
          if (!labels.length && values.length) labels = values.map((_, i) => String(i + 1));
          if (labels.length && values.length && labels.length !== values.length) {
            const n = Math.min(labels.length, values.length);
            labels = labels.slice(0, n);
            values = values.slice(0, n);
          }
          return { labels, values };
        } catch (e) { return { labels: [], values: [] }; }
      }

      const s = raw || {};
      labels =
        (Array.isArray(s.labels) ? s.labels : null) ||
        (Array.isArray(s.weeks)  ? s.weeks  : null) ||
        (Array.isArray(s.dates)  ? s.dates  : null) ||
        (Array.isArray(s.x)      ? s.x      : null) ||
        [];

      values =
        (Array.isArray(s.values) ? s.values : null) ||
        (Array.isArray(s.counts) ? s.counts : null) ||
        (Array.isArray(s.data)   ? s.data   : null) ||
        (Array.isArray(s.y)      ? s.y      : null) ||
        [];

      if ((!labels.length || !values.length) && s && typeof s === 'object' && !Array.isArray(s)) {
        try {
          const keys = Object.keys(s);
          const looksLikeMap =
            keys.length > 0 &&
            !keys.includes('labels') && !keys.includes('values') && !keys.includes('counts') &&
            !keys.includes('weeks') && !keys.includes('dates') && !keys.includes('data') &&
            !keys.includes('x') && !keys.includes('y');

          if (looksLikeMap) {
            const sorted = keys.slice().sort();
            labels = sorted;
            values = sorted.map(k => {
              const v = Number(s[k] ?? 0);
              return Number.isFinite(v) ? v : 0;
            });
          }
        } catch (e) {}
      }

      labels = Array.isArray(labels) ? labels : [];
      values = Array.isArray(values) ? values.map(v => (Number.isFinite(Number(v)) ? Number(v) : 0)) : [];

      if (labels.length && values.length && labels.length !== values.length) {
        const n = Math.min(labels.length, values.length);
        labels = labels.slice(0, n);
        values = values.slice(0, n);
      }

      return { labels, values };
    }

    const s1 = normalizeSerie(p?.serie);
    const s2 = (!s1.labels.length || !s1.values.length) ? normalizeSerie(p?.series) : { labels: [], values: [] };
    const s3 = ((!s1.labels.length || !s1.values.length) && (!s2.labels.length || !s2.values.length))
      ? normalizeSerie(p?.breakdown)
      : { labels: [], values: [] };

    let labels = (s1.labels.length && s1.values.length) ? s1.labels
               : (s2.labels.length && s2.values.length) ? s2.labels
               : s3.labels;

    let values = (s1.labels.length && s1.values.length) ? s1.values
               : (s2.labels.length && s2.values.length) ? s2.values
               : s3.values;

    if (!labels.length || !values.length) {
      if (hasLocalChart()) {
        log('Serie vacía en payload. Usando CFG.localChart.');
        drawLocalChart();
        return;
      }
      buildChart(['—','—','—','—','—','—','—','—'], [0,0,0,0,0,0,0,0]);
      return;
    }

    buildChart(labels, values);
  }

  function defaultRange() {
    const toD = new Date();
    const fromD = new Date();
    fromD.setDate(fromD.getDate() - 30);
    return { from: ymd(fromD), to: ymd(toD) };
  }

  function syncInputsDefault() {
    const r = defaultRange();
    if (elFrom && !elFrom.value) elFrom.value = r.from;
    if (elTo && !elTo.value) elTo.value = r.to;
  }

  async function reload(from, to) {
    syncInputsDefault();
    const fromVal = from || (elFrom ? elFrom.value : '') || defaultRange().from;
    const toVal   = to   || (elTo ? elTo.value : '')   || defaultRange().to;

    try {
      const payload = await fetchStats(fromVal, toVal);
      applyPayload(payload);
      return;
    } catch (e) {
      if (hasLocalChart()) {
        if (e && e.__httpStatus && Number(e.__httpStatus) !== 422) {
          toast(e?.message || 'Error al cargar dashboard SAT (fallback local).', 'error');
        } else {
          log('dashboardStats falló (422/otro). Usando CFG.localChart.');
        }
        drawLocalChart();
        return;
      }
      toast(e?.message || 'Error al cargar dashboard SAT.', 'error');
    }
  }

  if (elApply) {
    elApply.addEventListener('click', function () {
      const f = elFrom ? elFrom.value : '';
      const t = elTo ? elTo.value : '';
      reload(f, t);
    });
  }

  syncInputsDefault();
  reload();

  // Debug routes
  log('CFG.routes snapshot:', ROUTES);
});
