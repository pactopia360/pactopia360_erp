// public/assets/client/js/sat-dashboard.js
// PACTOPIA360 · SAT · Dashboard (Chart + KPIs)
// Requiere: Chart.js + P360_SAT.routes.dashboardStats
// ✅ FIX v2:
// - Destruye cualquier chart previo ligado al canvas (Chart.getChart) para evitar:
//   "Canvas is already in use. Chart with ID '0' must be destroyed..."
// - Si /cliente/sat/dashboard/stats falla (422 "Cuenta no encontrada") -> fallback a CFG.localChart
// - Hook de inputs: #satMovFrom #satMovTo + botón #satMovApply
// - Evita “canvas vacío” en errores

document.addEventListener('DOMContentLoaded', function () {
  if (window.__P360_SAT_DASHBOARD__) return;
  window.__P360_SAT_DASHBOARD__ = true;

  const CFG    = window.P360_SAT || {};
  const ROUTES = CFG.routes || {};

  const canvas = document.getElementById('satMovChart');
  if (!canvas) return;

  // Controles UI rango
  const elFrom  = document.getElementById('satMovFrom');
  const elTo    = document.getElementById('satMovTo');
  const elApply = document.getElementById('satMovApply');

  function toast(msg, kind='info') {
    try {
      if (window.P360 && typeof window.P360.toast === 'function') {
        if (kind === 'error' && window.P360.toast.error) return window.P360.toast.error(msg);
        if (kind === 'success' && window.P360.toast.success) return window.P360.toast.success(msg);
        return window.P360.toast(msg);
      }
    } catch(e) {}
    console.log('[SAT-DASH]', msg);
  }

  function ymd(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  // Targets de KPI (soporta varios IDs / data-kpi)
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
    } catch (e) {
      // no-op
    }
  }

   function buildChart(labels, values) {
    if (typeof Chart === 'undefined') {
      toast('Chart.js no está cargado.', 'error');
      return;
    }

    destroyAnyChartBoundToCanvas();

    const ctx = canvas.getContext('2d');

    // Colores defensivos (evita “invisible” por temas/CSS)
    const css = (name, fb) => {
      try {
        const v = getComputedStyle(document.documentElement).getPropertyValue(name);
        const s = String(v || '').trim();
        return s || fb;
      } catch (e) {
        return fb;
      }
    };

    const stroke = css('--sx-primary', '#2563eb');         // azul
    const fill   = css('--sx-primary-soft', 'rgba(37,99,235,.18)');

    // IMPORTANTE: NO mantener instancia global; Chart.getChart es nuestra fuente de verdad
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: Array.isArray(labels) ? labels : [],
        datasets: [{
          label: 'Descargas',
          data: Array.isArray(values) ? values : [],
          tension: 0.35,
          fill: true,

          // ✅ FIX VISUAL
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
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 }
          }
        }
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

    const res = await fetch(finalUrl, {
      headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }
    });

    const data = await res.json().catch(() => ({}));

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

    // ✅ Serie defensiva:
    // - algunos endpoints devuelven values, otros counts, otros data
    // - si viene vacío, usamos CFG.localChart (que ya tienes poblado)
    const s = p?.serie || p?.series || {};
    const labels =
      (Array.isArray(s.labels) ? s.labels : null)
      || (Array.isArray(s.weeks) ? s.weeks : null)
      || [];

    const values =
      (Array.isArray(s.values) ? s.values : null)
      || (Array.isArray(s.counts) ? s.counts : null)
      || (Array.isArray(s.data) ? s.data : null)
      || [];

    const isEmptySerie = !labels.length || !values.length;

    if (isEmptySerie) {
      if (hasLocalChart()) {
        console.log('[SAT-DASH] Serie vacía en payload. Usando CFG.localChart.');
        drawLocalChart();
        return;
      }

      // Último fallback: no dejar el canvas “sin nada”
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
        // No “asustar” si es 422; solo log
        if (e && e.__httpStatus && Number(e.__httpStatus) !== 422) {
          toast(e?.message || 'Error al cargar dashboard SAT (fallback local).', 'error');
        } else {
          console.log('[SAT-DASH] Endpoint dashboardStats falló (422/otro). Usando CFG.localChart.');
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
});

