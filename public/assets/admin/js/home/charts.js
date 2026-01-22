// public/assets/admin/js/home/charts.js
let _incomeLabelsYM = [];
let _incomeClickCb = null;

function destroy(canvas) {
  if (!canvas) return;
  try { canvas.__chart?.destroy?.(); } catch (_) {}
  canvas.__chart = null;
}

function hasChart() {
  return typeof window.Chart !== 'undefined';
}

export function resizeCharts() {
  // Chart.js se redimensiona solo; esto sirve si quieres forzarlo
  ['chartIncome','chartYTD','chartStamps','chartPlans','chartDaily'].forEach(id=>{
    const c = document.getElementById(id);
    try { c?.__chart?.resize?.(); } catch(_) {}
  });
}

export function setLabelsYMForIncome(labelsYM) {
  _incomeLabelsYM = Array.isArray(labelsYM) ? labelsYM.map(String) : [];
}

export function onIncomeClick(cb) {
  _incomeClickCb = typeof cb === 'function' ? cb : null;
}

export function makeCharts(data, force = false) {
  if (!hasChart()) {
    console.log('[charts] Chart.js no presente; se omiten gráficas');
    document.dispatchEvent(new CustomEvent('p360:charts-ready'));
    return;
  }

  const series = data?.series || {};
  const labels = Array.isArray(series.labels) ? series.labels.map(String)
               : Array.isArray(data?.labels) ? data.labels.map(String) : [];

  const ingresos = Array.isArray(series.ingresos) ? series.ingresos
                 : Array.isArray(series.income) ? series.income : [];

  const timbres  = Array.isArray(series.timbres) ? series.timbres
                 : Array.isArray(series.stamps) ? series.stamps : [];

  // INCOME
  const cIncome = document.getElementById('chartIncome');
  if (cIncome) {
    if (force) destroy(cIncome);
    if (!cIncome.__chart) {
      cIncome.__chart = new Chart(cIncome.getContext('2d'), {
        type: 'line',
        data: { labels, datasets: [{ label:'Ingresos', data: ingresos, tension:.3, borderWidth:2, pointRadius:0 }] },
        options: {
          responsive:true, maintainAspectRatio:false,
          plugins: { legend:{display:false} },
          onClick: (_, elements) => {
            if (!_incomeClickCb) return;
            const el = elements?.[0];
            if (!el) return;
            const i = el.index;
            const ym = _incomeLabelsYM?.[i] || labels?.[i];
            if (ym) _incomeClickCb(String(ym));
          }
        }
      });
    }
  }

  // STAMPS
  const cStamps = document.getElementById('chartStamps');
  if (cStamps) {
    if (force) destroy(cStamps);
    if (!cStamps.__chart) {
      cStamps.__chart = new Chart(cStamps.getContext('2d'), {
        type: 'bar',
        data: { labels, datasets: [{ label:'Timbres', data: timbres }] },
        options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false} } }
      });
    }
  }

  // Plans y Daily se calculan en index.js con renderExtraCharts o con datos si vienen.
  document.dispatchEvent(new CustomEvent('p360:charts-ready'));
}
