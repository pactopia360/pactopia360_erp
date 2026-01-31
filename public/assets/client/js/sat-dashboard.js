// public/assets/client/js/sat-dashboard.js
// PACTOPIA360 · SAT · Dashboard (Chart + KPIs + External ZIP/FIEL table)
//
// Requiere: Chart.js + window.P360_SAT (inyección Blade)
// - Usa P360_SAT.routes.dashboardStats para gráfica/kpis
// - Usa P360_SAT.routes.fielList (preferente) para listado ZIPs cargados
//
// ✅ Robust v5 (2026-01-30):
// - satFetchJson(): CSRF + JSON/HTML safe + errores normalizados
// - pickRows(): soporta muchísimas formas de respuesta (paginador, wrapper data/data, payload, etc.)
// - Debug opcional: si window.P360_SAT.debug === true loguea rutas/respuestas
// - External ZIP list: prioriza FIEL routes (cliente/sat/fiel/external/list) y cae a externalZipList si existe
// - Render defensivo: aunque cambien keys, intenta mapear id/rfc/nombre/tamaño/status
// - No rompe si no existe tabla o no existe chart

document.addEventListener('DOMContentLoaded', function () {
  if (window.__P360_SAT_DASHBOARD__) return;
  window.__P360_SAT_DASHBOARD__ = true;

  const CFG    = window.P360_SAT || {};
  const ROUTES = CFG.routes || {};
  const DEBUG  = !!CFG.debug;

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

  // =====================================================
  // satFetchJson: robusto (JSON + HTML + 501 friendly)
  // ✅ FIX CRÍTICO: antes siempre devolvía ok:false (y usaba txt no definido)
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
    return {
      ok: true,
      status: res.status,
      data
    };
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
  window.P360_SAT_API.externalZipList = async function (params = {}) {
  // =========================================================
  // External ZIP list (FIEL ZIP)
  // Objetivo:
  // - Preferir SIEMPRE ROUTES.externalZipList si existe
  // - Fallback a fielList solo si externalZipList NO existe
  // - Normalizar respuesta para que SIEMPRE haya:
  //     resp.data.rows (Array)
  //     resp.data.count (Number)
  // =========================================================

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
    return { ok: false, status: 0, data: { ok: false, msg: 'Ruta de listado no configurada.', rows: [], count: 0 } };
  }

  // QS
  const qs = new URLSearchParams();
  if (params && typeof params === 'object') {
    if (params.limit != null)  qs.set('limit', String(params.limit));
    if (params.offset != null) qs.set('offset', String(params.offset));
    if (params.status)         qs.set('status', String(params.status));
    if (params.tipo)           qs.set('tipo', String(params.tipo));
    if (params.q)              qs.set('q', String(params.q));
  }

  const finalUrl = qs.toString()
    ? (url + (url.includes('?') ? '&' : '?') + qs.toString())
    : url;

  log('externalZipList finalUrl:', finalUrl);

  // Fetch
  const resp = await satFetchJson(finalUrl, { method: 'GET' });

  // -----------------------------
  // Normalización de salida
  // -----------------------------
  // satFetchJson regresa { ok, status, data }
  // data puede venir:
  // - { ok:true, rows:[...], data:{ rows:[...], count:N } }
  // - { ok:true, rows:[...] }
  // - { rows:[...] }
  // - { data:{ rows:[...], count:N } }
  // - incluso [] (muy raro)
  const payload = resp && typeof resp === 'object' ? (resp.data ?? null) : null;

  let rows = [];
  let count = 0;

  try {
    // Caso: payload es array
    if (Array.isArray(payload)) {
      rows = payload;
      count = payload.length;
    } else if (payload && typeof payload === 'object') {
      const r1 = payload?.data?.rows;
      const r2 = payload?.rows;
      const r3 = payload?.data; // a veces data puede ser el array directo

      if (Array.isArray(r1)) rows = r1;
      else if (Array.isArray(r2)) rows = r2;
      else if (Array.isArray(r3)) rows = r3;
      else rows = [];

      const c1 = payload?.data?.count;
      const c2 = payload?.count;

      if (typeof c1 === 'number') count = c1;
      else if (typeof c2 === 'number') count = c2;
      else count = rows.length;
    } else {
      rows = [];
      count = 0;
    }
  } catch (e) {
    rows = [];
    count = 0;
  }

  // Asegurar estructura esperada por el front: resp.data.rows / resp.data.count
  const normalized = (payload && typeof payload === 'object') ? payload : {};
  normalized.rows = rows;
  normalized.count = count;

  // Mantener compatibilidad: también poner data.rows / data.count si existía data object
  if (normalized.data && typeof normalized.data === 'object') {
    if (!Array.isArray(normalized.data.rows)) normalized.data.rows = rows;
    if (typeof normalized.data.count !== 'number') normalized.data.count = count;
  } else {
    // si no existe normalized.data, crearla para compatibilidad
    normalized.data = { rows, count };
  }

  const out = Object.assign({}, resp, { data: normalized });

  log('externalZipList normalized:', { status: out?.status, count: out?.data?.count, rowsLen: (out?.data?.rows || []).length });

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

    // Si ya es array
    if (Array.isArray(payload)) return payload;

    // Intentos directos
    const directKeys = ['rows', 'items', 'list', 'result', 'results'];
    for (const k of directKeys) {
      if (Array.isArray(payload[k])) return payload[k];
    }

    // data puede ser array o paginator object
    if (Array.isArray(payload.data)) return payload.data;

    // paginator style: { data: { data: [...] } }
    if (payload.data && typeof payload.data === 'object') {
      if (Array.isArray(payload.data.data)) return payload.data.data;
      if (Array.isArray(payload.data.items)) return payload.data.items;
      if (Array.isArray(payload.data.rows)) return payload.data.rows;
    }

    // wrappers comunes
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

    const arr = Array.isArray(rows) ? rows : [];
    if (!arr.length) {
      log('No rows to render. Raw payload:', rawPayloadForDebug);
      tbody.innerHTML = `
        <tr>
          <td colspan="6" class="t-center text-muted" style="padding:14px;">
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

    tbody.innerHTML = arr.map((r) => {
      const id = pickId(r);

      const rfc   = escapeHtml(r.rfc || r.RFC || '');
      const razon = escapeHtml(r.razon_social || r.razonSocial || r.nombre || r.company || '');
      const name  = escapeHtml(r.file_name || r.fileName || r.name || r.zip_name || 'ZIP');
      const size  = fmtBytes(r.file_size || r.size_bytes || r.zip_bytes || r.bytes || r.size || 0);
      const stRaw = (r.status || r.estado || r.state || r.sat_status || r.estatus || '');

      const urlDownload = buildUrl(rtDownload, id);
      const urlDestroy  = buildUrl(rtDestroy, id);

      const btnDownload = (id && urlDownload)
        ? `<button type="button" class="btn icon-only" data-action="fiel-download" data-url="${escapeHtml(urlDownload)}" data-id="${escapeHtml(id)}" data-tip="Descargar">⬇️</button>`
        : `<button type="button" class="btn icon-only" disabled data-tip="${!id ? 'Sin ID' : 'Ruta download no configurada'}">⬇️</button>`;

      const btnDelete = (id && urlDestroy)
        ? `<button type="button" class="btn icon-only" data-action="fiel-destroy" data-url="${escapeHtml(urlDestroy)}" data-id="${escapeHtml(id)}" data-rfc="${escapeHtml(r.rfc || '')}" data-tip="Eliminar">🗑️</button>`
        : `<button type="button" class="btn icon-only" disabled data-tip="${!id ? 'Sin ID' : 'Ruta destroy no configurada'}">🗑️</button>`;

      const actions = `
        <div class="sat-row-actions" style="display:flex; gap:6px; justify-content:center; align-items:center;">
          ${btnDownload}
          ${btnDelete}
        </div>
      `.trim();

      return `
        <tr data-id="${escapeHtml(id)}">
          <td>${name}</td>
          <td class="t-center mono">${rfc || '—'}</td>
          <td>${razon || '—'}</td>
          <td class="t-right">${size}</td>
          <td class="t-center">${badge(stRaw)}</td>
          <td class="t-center">${actions}</td>
        </tr>
      `.trim();
    }).join('');
  }

  // Actions
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-action="fiel-download"]');
    if (!btn) return;
    const url = btn.getAttribute('data-url') || '';
    if (!url) return;
    window.location.href = url;
  });

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

  // Loader
  async function loadExternalZipList() {
    const tbody = findExternalZipTbody();
    if (!tbody) return;

    tbody.innerHTML = `
      <tr><td colspan="6" class="t-center text-muted" style="padding:14px;">Cargando…</td></tr>
    `.trim();

    const resp = await window.P360_SAT_API.externalZipList({ limit: 50 });

    log('externalZipList response:', resp);

    if (!resp || resp.ok !== true) {
      tbody.innerHTML = `
        <tr><td colspan="6" class="t-center text-muted" style="padding:14px;">
          ${escapeHtml(resp?.data?.msg || 'No se pudo cargar el listado.')}
        </td></tr>
      `.trim();
      return;
    }

    const data = resp.data || {};
    // si el backend NO usa "ok", no lo penalizamos
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

    // serie defensiva
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
