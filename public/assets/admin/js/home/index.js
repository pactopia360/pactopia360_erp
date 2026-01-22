// public/assets/admin/js/home/index.js
// P360 Admin · Home/Dashboard · v6.2 (NO module, NO imports)
// FIX:
// - Daily chart: consume data-compare-url (admin.home.compare) y dibuja chartDaily
// - Fallback seguro si no existe endpoint o no hay data
// - Click en barra de ingresos -> recarga Daily para ese mes

(function () {
  'use strict';

  const page = document.querySelector('.page');
  if (!page) return;

  const elDebug   = document.getElementById('homeDebug');
  const elLoading = document.getElementById('loadingOverlay');

  const ROUTES = {
    statsUrl:   page?.dataset?.statsUrl ? String(page.dataset.statsUrl) : '',
    incomeTpl:  page?.dataset?.incomeUrl ? String(page.dataset.incomeUrl) : '',
    compareTpl: page?.dataset?.compareUrl ? String(page.dataset.compareUrl) : '',
  };

  // -------------------------
  // Debug visible
  // -------------------------
  function showDebug(html) {
    if (!elDebug) return;
    elDebug.style.display = 'block';
    elDebug.innerHTML = html;
  }
  function hideDebug() {
    if (!elDebug) return;
    elDebug.style.display = 'none';
    elDebug.innerHTML = '';
  }

  // -------------------------
  // Loading overlay
  // -------------------------
  function showLoading() {
    if (!elLoading) return;
    page.setAttribute('aria-busy', 'true');
  }
  function hideLoading() {
    if (!elLoading) return;
    page.setAttribute('aria-busy', 'false');
  }

  // -------------------------
  // Helpers
  // -------------------------
  function qs(sel, root) { return (root || document).querySelector(sel); }

  const moneyMXN = (v) =>
    new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN',
      maximumFractionDigits: 0
    }).format(Number(v || 0));

  const intMX = (v) =>
    new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 }).format(Number(v || 0));

  function escapeHtml(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  // -------------------------
  // Abortable fetch
  // -------------------------
  let currentAbort = null;

  function abortCurrent() {
    try { currentAbort && currentAbort.abort(); } catch (_) {}
    currentAbort = null;
  }

  function isAbort(e) {
    return (
      e?.name === 'AbortError' ||
      e?.code === 20 ||
      String(e?.message || '').toLowerCase().includes('abort')
    );
  }

  // -------------------------
  // Filters
  // -------------------------
  function readFilters() {
    const from  = qs('#fFrom')?.value || '';
    const to    = qs('#fTo')?.value || '';
    const scope = qs('#fScope')?.value || 'paid';
    const group = qs('#fGroup')?.value || 'month';
    return { from, to, scope, group };
  }

  function buildUrlWithFilters(baseUrl, filters) {
    if (!baseUrl) return '';
    try {
      const u = new URL(baseUrl, window.location.origin);
      Object.entries(filters || {}).forEach(([k, v]) => {
        if (v !== null && v !== undefined && String(v).trim() !== '') {
          u.searchParams.set(k, String(v));
        } else {
          u.searchParams.delete(k);
        }
      });
      return u.toString();
    } catch (e) {
      const qsParts = [];
      Object.entries(filters || {}).forEach(([k, v]) => {
        if (v !== null && v !== undefined && String(v).trim() !== '') {
          qsParts.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(v)));
        }
      });
      return baseUrl + (baseUrl.includes('?') ? '&' : '?') + qsParts.join('&');
    }
  }

  // -------------------------
  // Chart.js helpers
  // -------------------------
  const charts = {
    income: null,
    stamps: null,
    plans: null,
    accum: null,
    daily: null,
  };

  function destroyChart(key) {
    try { charts[key] && charts[key].destroy && charts[key].destroy(); } catch (_) {}
    charts[key] = null;
  }

  function attachChart(key, canvasId, cfg) {
    const c = document.getElementById(canvasId);
    if (!c) return null;
    if (typeof window.Chart === 'undefined') return null;

    destroyChart(key);
    try {
      const ctx = c.getContext('2d');
      charts[key] = new window.Chart(ctx, cfg);
      return charts[key];
    } catch (e) {
      console.error('Chart attach error', canvasId, e);
      return null;
    }
  }

  function barMoneyCfg(labels, data, label, onBarClick) {
    return {
      type: 'bar',
      data: { labels, datasets: [{ label, data, borderWidth: 1 }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (t) => `${label}: ${moneyMXN(t.parsed.y)}` } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: 'rgba(0,0,0,.06)' }, ticks: { callback: (v) => intMX(v) } }
        },
        onClick: (evt, elements, chart) => {
          if (!onBarClick) return;
          if (!elements || !elements.length) return;
          const idx = elements[0].index;
          const ym = chart?.data?.labels?.[idx];
          if (ym) onBarClick(String(ym));
        }
      }
    };
  }

  function lineCfg(labels, datasets, yIsMoney) {
    return {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: yIsMoney
              ? { label: (t) => `${t.dataset.label}: ${moneyMXN(t.parsed.y)}` }
              : { label: (t) => `${t.dataset.label}: ${intMX(t.parsed.y)}` }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: 'rgba(0,0,0,.06)' }, beginAtZero: true }
        }
      }
    };
  }

  // -------------------------
  // KPIs
  // -------------------------
  function paintKPIs(data) {
    const k = data?.kpis || {};
    const s = data?.series || {};

    const ingresosMes = Number(k.ingresosMes ?? 0);
    const timbres = Number(k.timbresUsados ?? (Array.isArray(s.timbres) ? (s.timbres[s.timbres.length - 1] || 0) : 0));
    const totalClientes = Number(k.totalClientes ?? 0);

    const activos = Number(k.activos ?? 0);
    const denom = activos > 0 ? activos : (totalClientes > 0 ? totalClientes : 1);
    const arpa = ingresosMes / denom;

    const elIncome  = document.getElementById('kpiIncome');
    const elStamps  = document.getElementById('kpiStamps');
    const elClients = document.getElementById('kpiClients');
    const elArpa    = document.getElementById('kpiArpa');

    if (elIncome)  elIncome.textContent  = moneyMXN(ingresosMes) + ' MXN';
    if (elStamps)  elStamps.textContent  = intMX(timbres);
    if (elClients) elClients.textContent = intMX(totalClientes);
    if (elArpa)    elArpa.textContent    = moneyMXN(arpa) + ' MXN';
  }

  // -------------------------
  // Tables (mínimo)
  // -------------------------
  function renderIncomeTable(data) {
    const tbl = document.getElementById('tblIncome');
    const tbody = tbl?.querySelector('tbody');
    if (!tbody) return;

    const rows = Array.isArray(data?.ingresosTable) ? data.ingresosTable : [];
    if (!rows.length) {
      tbody.innerHTML = `<tr><td class="empty" colspan="4">Sin datos</td></tr>`;
      return;
    }

    tbody.innerHTML = rows.map(r => {
      const label = String(r.label || r.ym || '—');
      const total = Number(r.total || 0);
      const pagos = Number(r.pagos || 0);
      const avg   = Number(r.avg || 0);
      return `
        <tr>
          <td>${escapeHtml(label)}</td>
          <td>${moneyMXN(total)}</td>
          <td>${intMX(pagos)}</td>
          <td>${moneyMXN(avg)}</td>
        </tr>
      `;
    }).join('');
  }

  function renderClientsTable(data) {
    const tbl = document.getElementById('tblClients');
    const tbody = tbl?.querySelector('tbody');
    if (!tbody) return;

    const rows = Array.isArray(data?.clientes) ? data.clientes : [];
    if (!rows.length) {
      tbody.innerHTML = `<tr><td class="empty" colspan="4">Sin datos</td></tr>`;
      return;
    }

    tbody.innerHTML = rows.map(r => {
      const empresa = String(r.empresa || r.cliente || r.name || '—').trim() || '—';
      const plan = String(r.plan || r.plan_name || r.estado || '—');
      const ingresos = Number(r.ingresos || 0);
      const timbres = Number(r.timbres || 0);

      return `
        <tr>
          <td>${escapeHtml(empresa)}</td>
          <td>${escapeHtml(plan)}</td>
          <td>${moneyMXN(ingresos)}</td>
          <td>${intMX(timbres)}</td>
        </tr>
      `;
    }).join('');
  }

  // -------------------------
  // Charts principales
  // -------------------------
  let lastStats = null;

  function pickDefaultYm(series) {
    const labels = Array.isArray(series?.labels) ? series.labels.map(String) : [];
    const ingresos = Array.isArray(series?.ingresos) ? series.ingresos.map(v => Number(v || 0)) : [];

    if (!labels.length) return '';

    // último mes con ingreso > 0; si no, último label
    for (let i = ingresos.length - 1; i >= 0; i--) {
      if ((ingresos[i] || 0) > 0) return labels[i];
    }
    return labels[labels.length - 1];
  }

  function makeCharts(data) {
    const series = data?.series || {};
    const labels = Array.isArray(series.labels) ? series.labels.map(String) : [];
    const ingresos = Array.isArray(series.ingresos) ? series.ingresos.map(v => Number(v || 0)) : [];
    const timbres = Array.isArray(series.timbres) ? series.timbres.map(v => Number(v || 0)) : [];

    // Ingresos (bar) + click -> daily
    if (labels.length && ingresos.length === labels.length) {
      attachChart('income', 'chartIncome', barMoneyCfg(labels, ingresos, 'Ingresos', (ym) => {
        loadDaily(ym).catch(() => {});
      }));
    }

    // Timbres (line)
    if (labels.length && timbres.length === labels.length) {
      attachChart('stamps', 'chartStamps', lineCfg(labels, [{
        label: 'Timbres',
        data: timbres,
        tension: 0.25,
        borderWidth: 2,
        pointRadius: 0,
        fill: false,
      }], false));
    }

    // Acumulado (running sum)
    if (labels.length && ingresos.length === labels.length) {
      let run = 0;
      const accum = ingresos.map(v => (run += (Number(v) || 0)));
      attachChart('accum', 'chartYTD', lineCfg(labels, [{
        label: 'Acumulado',
        data: accum,
        tension: 0.25,
        borderWidth: 2,
        pointRadius: 0,
        fill: false,
      }], true));
    }

    // Planes (doughnut)
    const planes = series.planes || {};
    const keys = planes && typeof planes === 'object' ? Object.keys(planes) : [];
    if (keys.length) {
      const vals = keys.map(k => Number(planes[k] || 0));
      attachChart('plans', 'chartPlans', {
        type: 'doughnut',
        data: { labels: keys, datasets: [{ data: vals, borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
      });
    }
  }

  // -------------------------
  // DAILY (compare endpoint)
  // -------------------------
  function setDailyEmpty(msg) {
    destroyChart('daily');

    const canvas = document.getElementById('chartDaily');
    if (!canvas) return;

    // “placeholder” sin romper layout: ponemos un texto encima del canvas
    const wrap = canvas.closest('.chart-wrap');
    if (!wrap) return;

    let ph = wrap.querySelector('.daily-empty');
    if (!ph) {
      ph = document.createElement('div');
      ph.className = 'daily-empty';
      ph.style.cssText = [
        'position:absolute','inset:0',
        'display:flex','align-items:center','justify-content:center',
        'color:rgba(100,116,139,.95)',
        'font:700 12px/1.3 system-ui, -apple-system, Segoe UI, Roboto, Arial',
        'padding:14px','text-align:center'
      ].join(';');
      wrap.appendChild(ph);
    }
    ph.textContent = msg || 'Sin datos diarios';
  }

  function clearDailyEmpty() {
    const canvas = document.getElementById('chartDaily');
    const wrap = canvas?.closest('.chart-wrap');
    const ph = wrap?.querySelector('.daily-empty');
    if (ph) ph.remove();
  }

  function normalizeDailyPayload(p) {
    // Acepta múltiples formatos:
    // 1) { labels:[1..n], current:[..], avg_prev2:[..] }
    // 2) { days:[..], series:{ current:[..], avg:[..] } }
    // 3) { labels, actual, promedio }
    const labels =
      Array.isArray(p?.labels) ? p.labels :
      Array.isArray(p?.days) ? p.days :
      Array.isArray(p?.x) ? p.x :
      null;

    const current =
      Array.isArray(p?.current) ? p.current :
      Array.isArray(p?.actual) ? p.actual :
      Array.isArray(p?.series?.current) ? p.series.current :
      Array.isArray(p?.series?.actual) ? p.series.actual :
      null;

    const avg =
      Array.isArray(p?.avg_prev2) ? p.avg_prev2 :
      Array.isArray(p?.avg) ? p.avg :
      Array.isArray(p?.promedio) ? p.promedio :
      Array.isArray(p?.series?.avg_prev2) ? p.series.avg_prev2 :
      Array.isArray(p?.series?.avg) ? p.series.avg :
      Array.isArray(p?.series?.promedio) ? p.series.promedio :
      null;

    if (!labels || !current || !avg) return null;

    const L = labels.map(String);
    const C = current.map(v => Number(v || 0));
    const A = avg.map(v => Number(v || 0));

    if (L.length !== C.length || L.length !== A.length) return null;

    return { labels: L, current: C, avg: A };
  }

  async function loadDaily(ym) {
    if (typeof window.Chart === 'undefined') return;

    const canvas = document.getElementById('chartDaily');
    if (!canvas) return;

    clearDailyEmpty();

    if (!ROUTES.compareTpl || !ROUTES.compareTpl.includes('__YM__')) {
      // Si no hay compare endpoint, no inventamos.
      setDailyEmpty('Sin endpoint de comparación (admin.home.compare).');
      return;
    }

    const filters = readFilters();
    const url = buildUrlWithFilters(ROUTES.compareTpl.replace('__YM__', ym), filters);

    // fetch diario independiente (no aborta el stats principal, pero sí aborta su propia corrida)
    const ac = new AbortController();
    try {
      const r = await fetch(url, {
        method: 'GET',
        headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
        signal: ac.signal
      });

      if (!r.ok) {
        setDailyEmpty('Sin datos diarios para ' + ym + ' (HTTP ' + r.status + ').');
        return;
      }

      const payload = await r.json();
      const norm = normalizeDailyPayload(payload);

      if (!norm) {
        setDailyEmpty('Respuesta diaria sin formato esperado para ' + ym + '.');
        return;
      }

      destroyChart('daily');

      attachChart('daily', 'chartDaily', lineCfg(norm.labels, [
        {
          label: 'Actual (' + ym + ')',
          data: norm.current,
          tension: 0.25,
          borderWidth: 2,
          pointRadius: 0,
          fill: false
        },
        {
          label: 'Promedio 2 meses prev.',
          data: norm.avg,
          tension: 0.25,
          borderWidth: 2,
          pointRadius: 0,
          fill: false
        }
      ], true));

    } catch (e) {
      if (isAbort(e)) return;
      console.error('Daily error', e);
      setDailyEmpty('Error cargando diario: ' + (e.message || String(e)));
    }
  }

  // -------------------------
  // Stats fetch + hydrate
  // -------------------------
  async function fetchStats() {
    if (!ROUTES.statsUrl) {
      showDebug('No existe <code>data-stats-url</code>. Revisa la ruta <code>admin.home.stats</code>.');
      return null;
    }

    const filters = readFilters();
    const url = buildUrlWithFilters(ROUTES.statsUrl, filters);

    abortCurrent();
    currentAbort = new AbortController();

    showLoading();
    hideDebug();

    try {
      const r = await fetch(url, {
        method: 'GET',
        headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
        signal: currentAbort.signal
      });

      if (!r.ok) throw new Error('HTTP ' + r.status);
      return await r.json();

    } finally {
      hideLoading();
    }
  }

  async function loadAll() {
    try {
      const data = await fetchStats();
      if (!data) return;

      lastStats = data;

      if (typeof window.Chart === 'undefined') {
        showDebug('No cargó <code>Chart.js</code>. Revisa el script local/CDN.');
        paintKPIs(data);
        renderIncomeTable(data);
        renderClientsTable(data);
        setDailyEmpty('Chart.js no disponible.');
        return;
      }

      paintKPIs(data);
      makeCharts(data);
      renderIncomeTable(data);
      renderClientsTable(data);

      // ✅ Daily: carga por defecto con el mes más reciente relevante
      const ym = pickDefaultYm(data?.series || {});
      if (ym) await loadDaily(ym);
      else setDailyEmpty('Sin mes para calcular diario.');

    } catch (e) {
      if (isAbort(e)) return;
      console.error('Home stats error', e);
      showDebug('No se pudieron cargar las estadísticas. Error: <code>' + escapeHtml(e.message || String(e)) + '</code>');
      setDailyEmpty('Sin datos diarios.');
    }
  }

  // -------------------------
  // Bind UI
  // -------------------------
  const btnApply = qs('#btnApply');
  const btnReset = qs('#btnReset');
  const btnAbort = qs('#btnAbort');

  btnApply && btnApply.addEventListener('click', () => loadAll());
  btnAbort && btnAbort.addEventListener('click', () => abortCurrent());

  btnReset && btnReset.addEventListener('click', () => {
    const fFrom = qs('#fFrom'); if (fFrom) fFrom.value = '';
    const fTo = qs('#fTo'); if (fTo) fTo.value = '';
    const fScope = qs('#fScope'); if (fScope) fScope.value = 'paid';
    const fGroup = qs('#fGroup'); if (fGroup) fGroup.value = 'month';
    loadAll();
  });

  // Primera carga
  loadAll();

})();
