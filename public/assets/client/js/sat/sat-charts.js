// public/assets/client/js/sat-charts.js
// PACTOPIA360 · SAT · Dashboard (cliente) · Charts (Chart.js)
// Split desde sat-dashboard.js (SOT)

(() => {
  'use strict';

  window.P360_SAT = window.P360_SAT || {};
  const SAT = window.P360_SAT;
  const ROUTES = SAT.routes || {};
  const VAULT = SAT.vault || {};

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

  // ============================================================
  // Gráficas (Chart.js) – robustas
  // ============================================================
  (function initTrendsCharts() {
    const hasHttp = !!ROUTES.charts;
    if (typeof Chart === 'undefined') return;

    function mkChart(canvasId, label) {
      const el = document.getElementById(canvasId);
      if (!el) return null;

      return new Chart(el, {
        type: 'line',
        data: { labels: [], datasets: [{ label, data: [], borderWidth: 2, tension: 0.35, pointRadius: 4, pointHoverRadius: 5 }] },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true } },
          scales: {
            x: { grid: { display: false } },
            y: { grid: { color: 'rgba(148,163,184,.25)' } },
          },
        },
      });
    }

    const chartA = mkChart('chartA', 'Importe total');
    const chartB = mkChart('chartB', '# CFDI');
    if (!chartA || !chartB) return;

    function getFallback() {
      return { labels: ['P1','P2','P3','P4','P5','P6'], counts: [0,0,0,0,0,0], amounts: [0,0,0,0,0,0] };
    }

    function applyToCharts(labels, amounts, counts, meta = {}) {
      chartA.data.labels = labels;
      chartB.data.labels = labels;

      chartA.data.datasets[0].label = meta.label_amount || 'Importe total';
      chartB.data.datasets[0].label = meta.label_count  || '# CFDI';

      chartA.data.datasets[0].data = amounts;
      chartB.data.datasets[0].data = counts;

      chartA.update();
      chartB.update();

      // si existe la chart de rango, la sincronizamos
      if (window._satMovChart) {
        window._satMovChart.data.labels = labels;
        window._satMovChart.data.datasets[0].data = counts;
        window._satMovChart.update();
      }
    }

    async function loadScope(scope) {
      if (!hasHttp) {
        const fb = getFallback();
        applyToCharts(fb.labels, fb.amounts, fb.counts);
        return;
      }

      try {
        const url = String(ROUTES.charts).trim() + '?scope=' + encodeURIComponent(scope || 'emitidos');
        const res = await fetch(url, {
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });

        if (!res.ok) {
          const fb = getFallback();
          applyToCharts(fb.labels, fb.amounts, fb.counts);
          return;
        }

        const j = await safeJson(res);
        if (j && j._non_json) {
          console.error('[SAT-CHARTS] non-json response', { status: res.status, text: j._text });
          const fb = getFallback();
          applyToCharts(fb.labels, fb.amounts, fb.counts);
          return;
        }

        const labels = Array.isArray(j.labels) ? j.labels : [];
        const series = j.series || {};
        const amounts = Array.isArray(series.amounts) ? series.amounts : [];
        const counts  = Array.isArray(series.counts)  ? series.counts  : [];

        if (!labels.length) {
          const fb = getFallback();
          applyToCharts(fb.labels, fb.amounts, fb.counts);
          return;
        }

        applyToCharts(
          labels,
          amounts.length ? amounts : new Array(labels.length).fill(0),
          counts.length  ? counts  : new Array(labels.length).fill(0),
          { label_amount: series.label_amount, label_count: series.label_count }
        );
      } catch (e) {
        console.error('[SAT-CHARTS] error', e);
        const fb = getFallback();
        applyToCharts(fb.labels, fb.amounts, fb.counts);
      }
    }

    loadScope('emitidos');

    document.querySelectorAll('#block-trends .tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('#block-trends .tab').forEach(x => x.classList.remove('is-active'));
        tab.classList.add('is-active');
        loadScope(tab.dataset.scope || 'emitidos');
      });
    });
  })();

  // ============================================================
  // Gráfica "Últimas semanas" con rango de fechas
  // ============================================================
  (function initMovChartRange() {
    const canvas = document.getElementById('satMovChart');
    const inpFrom = document.getElementById('satMovFrom');
    const inpTo = document.getElementById('satMovTo');
    const btnAp = document.getElementById('satMovApply');

    if (!canvas || !inpFrom || !inpTo || !btnAp || typeof Chart === 'undefined') return;

    const hasChartsEndpoint = !!ROUTES.charts;

    const movChart = new Chart(canvas, {
      type: 'line',
      data: { labels: [], datasets: [{ label: 'CFDI descargados', data: [], borderWidth: 2, tension: 0.35, pointRadius: 4, pointHoverRadius: 5 }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: 'rgba(148,163,184,.18)' } },
          y: { grid: { color: 'rgba(148,163,184,.25)' } },
        },
      },
    });

    window._satMovChart = movChart;

    function applyToChart(labels, counts) {
      movChart.data.labels = labels;
      movChart.data.datasets[0].data = counts;
      movChart.update();
    }

    function fallbackData() {
      return { labels: ['P1','P2','P3','P4','P5','P6','P7'], counts: [0,0,0,0,0,0,0] };
    }

    async function loadRange(from, to) {
      const dummy = fallbackData();
      applyToChart(dummy.labels, dummy.counts);

      if (!hasChartsEndpoint) return;

      try {
        const url = String(ROUTES.charts).trim()
          + '?scope=' + encodeURIComponent('emitidos')
          + '&period=' + encodeURIComponent('range')
          + '&from=' + encodeURIComponent(from)
          + '&to=' + encodeURIComponent(to);

        const res = await fetch(url, {
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        if (!res.ok) return;

        const j = await safeJson(res);
        if (j && j._non_json) {
          console.error('[SAT-MOV] non-json response', { status: res.status, text: j._text });
          return;
        }

        const labels = Array.isArray(j.labels) && j.labels.length ? j.labels : dummy.labels;
        const series = j.series || {};
        const counts = Array.isArray(series.counts) && series.counts.length ? series.counts : dummy.counts;

        applyToChart(labels, counts);
      } catch (e) {
        console.error('[SAT-MOV] error', e);
      }
    }

    function fmt(d) {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, '0');
      const a = String(d.getDate()).padStart(2, '0');
      return `${y}-${m}-${a}`;
    }

    (function initDefaultRange() {
      const today = new Date();
      const past = new Date();
      past.setDate(today.getDate() - 30);

      const vTo = fmt(today);
      const vFrom = fmt(past);

      inpFrom.value = vFrom;
      inpTo.value = vTo;

      if (hasChartsEndpoint) loadRange(vFrom, vTo);
      else {
        const d = fallbackData();
        applyToChart(d.labels, d.counts);
      }
    })();

    btnAp.addEventListener('click', () => {
      const from = (inpFrom.value || '').trim();
      const to = (inpTo.value || '').trim();

      if (!from || !to) { satToast('Selecciona fecha inicial y final.', 'error'); return; }
      if (new Date(from) > new Date(to)) { satToast('La fecha inicial no puede ser mayor que la final.', 'error'); return; }

      loadRange(from, to);
    });
  })();

  // ============================================================
  // Donut bóveda fiscal
  // ============================================================
  (function initVaultDonut() {
    const el = document.getElementById('vaultDonut');
    if (!el || typeof Chart === 'undefined' || !VAULT) return;

    const used = Number(VAULT.used || 0);
    const free = Number(VAULT.free || 0);

    new Chart(el, {
      type: 'doughnut',
      data: {
        labels: ['Consumido', 'Disponible'],
        datasets: [{ data: [used, free], borderWidth: 0 }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: { legend: { display: false }, tooltip: { enabled: true } },
      },
    });
  })();

})();
