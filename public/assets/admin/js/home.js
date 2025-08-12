(function(){
  const root = document.querySelector('.page');
  if (!root) return;

  const statsUrl  = root.getAttribute('data-stats-url');
  const incomeTpl = root.getAttribute('data-income-url'); // contiene "__YM__"

  const $ = (s)=>document.querySelector(s);
  const fmtInt = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 });
  const fmtCur = new Intl.NumberFormat('es-MX', { style:'currency', currency:'MXN', maximumFractionDigits:0 });

  // Filtros
  const fMonths = $('#fMonths');
  const fPlan   = $('#fPlan');
  const btnApply= $('#btnApply');

  // Charts
  let chartIncome, chartStamps, chartPlans;
  let cache = null;

  // Util
  function colors(){
    const light = document.body.classList.contains('theme-light');
    return {
      text: light ? '#111827' : '#e5e7eb',
      grid: light ? 'rgba(0,0,0,.08)' : 'rgba(255,255,255,.08)',
      red:  '#e31b23',
      blue: light ? '#1f4ddb' : '#60a5fa',
      green: light ? '#16a34a' : '#22c55e',
      yellow: '#f59e0b',
      border: light ? '#e5e7eb' : 'rgba(255,255,255,.12)',
      areaRed: 'rgba(227,27,35,.15)'
    };
  }
  function buildStatsUrl(){
    const u = new URL(statsUrl, window.location.origin);
    if (fMonths) u.searchParams.set('months', fMonths.value || 12);
    if (fPlan)   u.searchParams.set('plan', fPlan.value || 'all');
    return u.toString();
  }
  function withPlan(u){
    if (!fPlan) return u;
    const url = new URL(u, window.location.origin);
    url.searchParams.set('plan', fPlan.value || 'all');
    return url.toString();
  }
  function setText(id, val){ const el = document.getElementById(id); if (el) el.textContent = val; }

  // ====== Load stats ======
  function load(){
    fetch(buildStatsUrl(), { headers: { 'X-Requested-With':'XMLHttpRequest' }})
      .then(r => r.json())
      .then(data => {
        cache = data;
        hydrateFilters(data);
        paintKPIs(data);
        makeCharts(data);
        renderIncomeTable(data);
        renderClientsTable(data);
      })
      .catch(e => console.error('stats error', e));
  }
  function hydrateFilters(data){
    const opts = (data.filters && data.filters.planOptions) || [];
    if (fPlan && fPlan.children.length <= 1) {
      for (const o of opts) {
        const opt = document.createElement('option');
        opt.value = o.value;
        opt.textContent = o.label;
        fPlan.appendChild(opt);
      }
    }
    if (fPlan && data.filters?.plan) fPlan.value = data.filters.plan;
    if (fMonths && data.filters?.months) fMonths.value = String(data.filters.months);
  }
  function paintKPIs(data){
    const k = data.kpis || {};
    setText('kpi_clientes',  fmtInt.format(k.totalClientes || 0));
    setText('kpi_activos',   fmtInt.format(k.activos || 0));
    setText('kpi_inactivos', fmtInt.format(k.inactivos || 0));
    setText('kpi_nuevos',    fmtInt.format(k.nuevosMes || 0));
    setText('kpi_pendientes',fmtInt.format(k.pendientes || 0));
    setText('kpi_premium',   fmtInt.format(k.premium || 0));
    setText('kpi_timbres',   fmtInt.format(k.timbresUsados || 0));
    setText('kpi_ingresos',  fmtCur.format(k.ingresosMes || 0));
  }

  // ====== Charts ======
  let chartZoomPluginReady = false;
  function makeCharts(data){
    const c = colors();
    const labels   = data.series?.labels || [];
    const ingresos = data.series?.ingresos || [];
    const timbres  = data.series?.timbres || [];
    const planes   = data.series?.planes  || {};

    const common = {
      responsive: true,
      plugins: {
        legend: { labels: { color: c.text }},
        tooltip: { callbacks: { label: (ctx)=>{
          const v = ctx.parsed.y ?? ctx.parsed;
          const isMoney = ctx.dataset.label?.toLowerCase().includes('ingreso');
          return ' ' + (isMoney ? fmtCur.format(v) : fmtInt.format(v));
        }}},
        zoom: {
          pan: { enabled: true, mode: 'x' },
          zoom:{ wheel: {enabled:true}, pinch:{enabled:true}, mode:'x' }
        }
      },
      scales: {
        x: { ticks:{ color:c.text }, grid:{ color:c.grid }},
        y: { ticks:{ color:c.text }, grid:{ color:c.grid }}
      }
    };

    // Income line
    const ctx1 = document.getElementById('chartIncome');
    if (ctx1){
      chartIncome?.destroy();
      chartIncome = new Chart(ctx1, {
        type:'line',
        data: {
          labels,
          datasets: [{
            label:'Ingresos',
            data: ingresos,
            tension:.35,
            borderWidth:2,
            borderColor:c.red,
            pointRadius:3,
            pointHoverRadius:6,
            fill:true,
            backgroundColor: c.areaRed
          }]
        },
        options: { ...common, plugins:{ ...common.plugins, legend:{ display:false } } }
      });

      // click → modal mes
      ctx1.onclick = (evt)=>{
        const points = chartIncome.getElementsAtEventForMode(evt, 'nearest', {intersect:false}, true);
        if (!points.length) return;
        const idx = points[0].index;
        const ym  = labels[idx]; // 'YYYY-MM'
        if (!ym) return;
        openIncomeModal(ym);
      };
    }

    // Timbres bar
    const ctx2 = document.getElementById('chartStamps');
    if (ctx2){
      chartStamps?.destroy();
      chartStamps = new Chart(ctx2, {
        type:'bar',
        data: {
          labels,
          datasets: [{
            label:'Timbres',
            data: timbres,
            borderWidth:1,
            borderColor:c.blue,
            backgroundColor:c.blue
          }]
        },
        options: { ...common, plugins: { ...common.plugins, legend:{ display:false } } }
      });
    }

    // Planes doughnut
    const ctx3 = document.getElementById('chartPlans');
    if (ctx3){
      chartPlans?.destroy();
      const lab = Object.keys(planes);
      const val = Object.values(planes);
      chartPlans = new Chart(ctx3, {
        type:'doughnut',
        data: {
          labels: lab,
          datasets: [{
            data: val,
            borderWidth:1,
            borderColor:c.border,
            backgroundColor:[c.red,c.blue,c.green,c.yellow]
          }]
        },
        options: {
          responsive:true,
          plugins: { legend:{ position:'bottom', labels:{ color:c.text } } }
        }
      });
    }
  }

  // Reset zoom & downloads ya definidos en versión anterior (si los tienes)
  $('#btnResetZoom')?.addEventListener('click', ()=>{
    chartIncome?.resetZoom(); chartStamps?.resetZoom();
  });
  $('#btnDownloadIncomePNG')?.addEventListener('click', ()=>{
    if (!chartIncome) return;
    const a = document.createElement('a');
    a.href = chartIncome.toBase64Image();
    a.download = 'ingresos_mensuales.png'; a.click();
  });
  $('#btnDownloadIncomeCSV')?.addEventListener('click', ()=>{
    if (!cache) return;
    exportIncomeCSV(cache.ingresosTable || []);
  });

  // ====== Tabla ingresos mensual resumida (home) ======
  let incomeRows = [], sortBy = 'mes', sortDir = 'desc';
  function renderIncomeTable(data){
    const searchIncome = $('#searchIncome');
    incomeRows = (data.ingresosTable || []).map(r => ({
      mes: r.label, ym: r.ym, total: Number(r.total||0), pagos: Number(r.pagos||0), avg: Number(r.avg||0)
    }));
    function draw(){
      const tbody = $('#incomeTbody'), empty = $('#incomeEmpty');
      if (!tbody) return;
      const q = (searchIncome?.value||'').trim().toLowerCase();
      let rows = incomeRows.filter(r => !q || r.mes.toLowerCase().includes(q));
      rows.sort((a,b)=>{
        const s = sortDir === 'asc' ? 1 : -1;
        if (['total','pagos','avg'].includes(sortBy)) return (a[sortBy]-b[sortBy]) * s;
        return (a.ym > b.ym ? 1 : -1) * s;
      });
      tbody.innerHTML='';
      if (!rows.length){ empty?.classList.remove('hidden'); }
      else {
        empty?.classList.add('hidden');
        for (const r of rows){
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td><button class="link" data-ym="${r.ym}" title="Ver pagos">${escape(r.mes)}</button></td>
            <td>${fmtCur.format(r.total)}</td>
            <td>${fmtInt.format(r.pagos)}</td>
            <td>${fmtCur.format(r.avg)}</td>
          `;
          tbody.appendChild(tr);
        }
      }
      // totals
      const sum = rows.reduce((a,r)=>{ a.total+=r.total; a.pagos+=r.pagos; return a; }, {total:0,pagos:0});
      const avg = rows.length ? (sum.total / (rows.reduce((a,r)=>a+(r.pagos>0?1:0),0) || rows.length)) : 0;
      setText('incomeSum', fmtCur.format(sum.total));
      setText('incomeCount', fmtInt.format(sum.pagos));
      setText('incomeAvg', fmtCur.format(avg));

      // bind row clicks → modal
      tbody.querySelectorAll('button.link[data-ym]').forEach(b=>{
        b.addEventListener('click', ()=> openIncomeModal(b.getAttribute('data-ym')));
      });
    }
    draw();
    $('#incomeTable')?.querySelectorAll('th[data-sort]').forEach(th=>{
      th.style.cursor='pointer';
      th.onclick = ()=>{ const col = th.getAttribute('data-sort'); sortDir = (sortBy===col && sortDir==='desc')?'asc':'desc'; sortBy=col; draw(); };
    });
    searchIncome?.addEventListener('input', draw);
    $('#btnExportIncome')?.addEventListener('click', ()=> exportIncomeCSV(cache?.ingresosTable || []));
  }
  function exportIncomeCSV(rows){
    const header = ['Mes','Ingresos','Pagos','Ticket promedio'];
    const lines = [header.join(',')];
    for (const r of rows){ lines.push([csv(r.label), r.total, r.pagos, r.avg].join(',')); }
    const blob = new Blob([lines.join('\n')], {type:'text/csv;charset=utf-8;'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'ingresos_mensuales.csv'; a.click(); URL.revokeObjectURL(a.href);
  }

  // ====== Tabla clientes (como tenías) ======
  function renderClientsTable(data){
    const rows = data.clientes || [];
    const tbody = document.getElementById('clientsTbody');
    const empty = document.getElementById('clientsEmptyRow');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!rows.length){ empty?.classList.remove('hidden'); return; }
    empty?.classList.add('hidden');
    let i=1;
    for (const r of rows){
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${i++}</td>
        <td>${escape(r.empresa||'')}</td>
        <td>${escape(r.rfc||'')}</td>
        <td>${fmtInt.format(r.timbres||0)}</td>
        <td>${escape(r.ultimo||'')}</td>
        <td>${escape(r.estado||'')}</td>
      `;
      tbody.appendChild(tr);
    }
    const inp = document.getElementById('searchClients');
    inp && inp.addEventListener('input', ()=>{
      const q = (inp.value||'').toLowerCase();
      const filtered = rows.filter(r =>
        (r.empresa||'').toLowerCase().includes(q) ||
        (r.rfc||'').toLowerCase().includes(q) ||
        (r.estado||'').toLowerCase().includes(q)
      );
      let i=1; tbody.innerHTML='';
      for (const r of filtered){
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${i++}</td>
          <td>${escape(r.empresa||'')}</td>
          <td>${escape(r.rfc||'')}</td>
          <td>${fmtInt.format(r.timbres||0)}</td>
          <td>${escape(r.ultimo||'')}</td>
          <td>${escape(r.estado||'')}</td>
        `;
        tbody.appendChild(tr);
      }
    });
  }

  // ====== Modal ingreso por mes ======
  const modal = $('#modalIncome');
  const modalMonth = $('#modalIncomeMonth');
  const modalTbody = $('#incomeModalTbody');
  const modalEmpty = $('#incomeModalEmpty');
  const modalTotal = $('#incomeModalTotal');
  const modalSearch= $('#incomeModalSearch');
  const modalExport= $('#incomeModalExport');
  let modalRows = [];
  let modalSortBy = 'fecha', modalSortDir = 'asc';

  function openIncomeModal(ym){
    if (!incomeTpl) return;
    const url = withPlan(incomeTpl.replace('__YM__', ym));
    fetch(url, { headers:{ 'X-Requested-With':'XMLHttpRequest' }})
      .then(r => r.json())
      .then(data => {
        modalRows = data.rows || [];
        modalMonth.textContent = ym;
        drawIncomeModal();
        openModal();
      })
      .catch(err => console.error('income month error', err));
  }
  function drawIncomeModal(){
    const q = (modalSearch?.value||'').toLowerCase();
    let rows = modalRows.filter(r => !q || (r.cliente||'').toLowerCase().includes(q) || (r.rfc||'').toLowerCase().includes(q) || (r.referencia||'').toLowerCase().includes(q));
    rows.sort((a,b)=>{
      const s = (modalSortDir==='asc')?1:-1;
      if (modalSortBy === 'monto') return (a.monto-b.monto)*s;
      return String(a[modalSortBy]||'').localeCompare(String(b[modalSortBy]||'')) * s;
    });

    modalTbody.innerHTML='';
    if (!rows.length){ modalEmpty?.classList.remove('hidden'); }
    else {
      modalEmpty?.classList.add('hidden');
      for (const r of rows){
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${escape(r.fecha)}</td>
          <td>${escape(r.cliente)}</td>
          <td>${escape(r.rfc)}</td>
          <td>${escape(r.metodo)}</td>
          <td>${escape(r.estado)}</td>
          <td>${fmtCur.format(r.monto||0)}</td>
        `;
        modalTbody.appendChild(tr);
      }
    }
    const total = rows.reduce((s,r)=>s+(r.monto||0),0);
    modalTotal.textContent = fmtCur.format(total);
  }
  function openModal(){ modal?.classList.add('open'); modal?.setAttribute('aria-hidden','false'); }
  function closeModal(){ modal?.classList.remove('open'); modal?.setAttribute('aria-hidden','true'); }

  modal?.addEventListener('click', (e)=>{ if (e.target.matches('[data-close], .modal-backdrop')) closeModal(); });
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape' && modal?.classList.contains('open')) closeModal(); });

  // sort headers modal
  document.querySelectorAll('#incomeModalTable th[data-sort]')?.forEach(th=>{
    th.addEventListener('click', ()=>{
      const col = th.getAttribute('data-sort');
      if (modalSortBy === col) modalSortDir = (modalSortDir==='asc'?'desc':'asc');
      else { modalSortBy = col; modalSortDir = (col==='fecha'?'asc':'desc'); }
      drawIncomeModal();
    });
  });
  modalSearch?.addEventListener('input', drawIncomeModal);
  modalExport?.addEventListener('click', ()=>{
    const header = ['Fecha','Cliente','RFC','Método','Estado','Monto'];
    const lines = [header.join(',')];
    for (const r of modalRows){
      lines.push([csv(r.fecha), csv(r.cliente), csv(r.rfc), csv(r.metodo), csv(r.estado), r.monto].join(','));
    }
    const blob = new Blob([lines.join('\n')], {type:'text/csv;charset=utf-8;'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `pagos_${(modalMonth?.textContent||'mes')}.csv`; a.click(); URL.revokeObjectURL(a.href);
  });

  // helpers
  function escape(s){ return String(s??'').replace(/[&<>"'`=\/]/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'})[m]); }
  function csv(v){ return `"${String(v??'').replace(/"/g,'""')}"`; }

  // Filtros
  btnApply?.addEventListener('click', load);

  // Tema → recolor charts
  window.addEventListener('p360:theme', ()=>{ if (cache) makeCharts(cache); });

  // Start
  load();
})();
