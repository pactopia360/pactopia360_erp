// charts.js
import { colors, tooltipThemeColors, mkGrad, fmtCur, fmtInt, prettyMonth,
         DPI_CAP, REDUCED_MOTION, destroyCanvasChartByEl, trendText } from './helpers.js';
import { chartsRef, currentThemeLight, setCurrentThemeLight } from './state.js';

function maybeRegisterZoom(){
  if (!window.Chart) return false;
  const ChartJS = window.Chart;
  if (ChartJS?.registry?.plugins?.get('zoom')) return true;
  const candidate =
    (window.ChartZoom) ||
    (window.chartjsPluginZoom) ||
    (window.zoomPlugin) ||
    (window['chartjs-plugin-zoom']) ||
    null;
  if (candidate && ChartJS.register){
    try { ChartJS.register(candidate); return true; } catch { return false; }
  }
  return false;
}

function hudLog(msg, type='log'){
  try { window.__hud && window.__hud.log(`[charts] ${msg}`, type); } catch {}
}

/* ===== Plugin: mensaje "Sin datos" (registro idempotente) ===== */
const NoDataPlugin = {
  id: 'p360NoData',
  afterDraw(chart, args, opts){
    if (!opts?.enabled) return;
    const ds = chart?.data?.datasets || [];
    const hasAny = ds.some(d => Array.isArray(d.data) && d.data.some(v => (typeof v === 'number' ? v !== 0 : !!v)));
    if (hasAny) return;
    const {ctx, chartArea} = chart;
    if (!chartArea) return;
    const {left, right, top, bottom} = chartArea;
    const c = colors(); const w = right-left; const h = bottom-top;
    const title = opts.title || 'Sin datos';
    const reason = opts.reason || '';
    ctx.save();
    ctx.fillStyle = c.text; ctx.globalAlpha = .66;
    ctx.textAlign='center'; ctx.textBaseline='middle';
    ctx.font = '700 14px system-ui, -apple-system, Segoe UI, Roboto';
    ctx.fillText(title, left + w/2, top + h/2 - 8);
    if (reason){
      ctx.globalAlpha = .55;
      ctx.font = '400 12px system-ui, -apple-system, Segoe UI, Roboto';
      ctx.fillText(reason, left + w/2, top + h/2 + 12);
    }
    ctx.restore();
  }
};

export function destroyCharts(){
  for (const k of Object.keys(chartsRef)){
    try { chartsRef[k]?.destroy?.(); } catch {}
    chartsRef[k] = null;
  }
  ['chartIncome','chartStamps','chartPlans','chartIncomePlan','chartNewClients','chartTopClients','chartScatter','chartMoM']
    .forEach(id => destroyCanvasChartByEl(document.getElementById(id)));
}
export function resizeCharts(){ for (const k of Object.keys(chartsRef)) try{ chartsRef[k]?.resize?.(); }catch{} }

export function makeCharts(data, force=false){
  if (!window.Chart) { hudLog('Chart.js no cargado','warn'); return; }
  const themeLight = document.body.classList.contains('theme-light');
  if (!force && chartsRef.income && chartsRef.stamps && chartsRef.plans && currentThemeLight === themeLight) return;

  setCurrentThemeLight(themeLight);
  destroyCharts();
  const zoomEnabled = !!maybeRegisterZoom();
  try {
    if (!window.Chart.registry.plugins.get('p360NoData')) {
      window.Chart.register(NoDataPlugin);
    }
  } catch {}

  const c  = colors();
  const tt = tooltipThemeColors();

  const labelsYM = data?.series?.labels || [];
  const labelsUI = labelsYM.map(prettyMonth);
  const ingresos = data?.series?.ingresos || [];
  const timbres  = data?.series?.timbres  || [];
  const planesObj= data?.series?.planes   || {};

  const extra = data?.extra || {};
  const nuevosLabels = extra?.nuevosClientes?.labels || [];
  const nuevosValues = extra?.nuevosClientes?.values || [];
  const byPlan = extra?.ingresosPorPlan || { labels:[], plans:{} };
  const top    = extra?.topClientes || { labels:[], values:[] };
  const scatter= extra?.scatterIncomeStamps || [];

  // Si el backend mandó diagnostics, lo reflejamos en HUD
  const diag = data?.diagnostics || {};
  if (diag?.warnings?.length) diag.warnings.forEach(w => hudLog(`diag: ${w}`, 'warn'));

  const common = {
    responsive:true, maintainAspectRatio:false, devicePixelRatio:DPI_CAP,
    animation: REDUCED_MOTION ? false : { duration:260, easing:'easeOutCubic' },
    plugins: {
      legend:{ position:'bottom', labels:{ color:c.text, usePointStyle:true, boxWidth:10, boxHeight:10, padding:16 } },
      tooltip:{
        backgroundColor:tt.bg, borderColor:tt.bd, borderWidth:1, titleColor:tt.txt, bodyColor:tt.txt, padding:10, displayColors:false,
        callbacks:{
          title:(items)=> items.length ? (items[0].label||'') : '',
          label:(ctx)=>{
            const i=ctx.dataIndex; const cur=ctx.parsed.y ?? ctx.parsed ?? 0;
            const prev = i>0 ? (ctx.dataset.data[i-1] ?? null) : null;
            const isMoney = (ctx.dataset.label||'').toLowerCase().includes('ingreso');
            const main = isMoney ? fmtCur.format(cur) : fmtInt.format(cur);
            const delta = trendText(cur, prev);
            return ` ${main}   ${delta}`;
          }
        }
      },
      ...(zoomEnabled ? { zoom:{ pan:{enabled:true,mode:'x'}, zoom:{wheel:{enabled:true},pinch:{enabled:true},mode:'x'} } } : {})
    },
    scales: { x:{ ticks:{color:c.text}, grid:{color:c.grid, borderDash:[4,4]} }, y:{ ticks:{color:c.text}, grid:{color:c.grid, borderDash:[4,4]} } }
  };

  // Badge último punto (ingresos) — evita división entre 0
  const lastPointBadge = { id:'lastPointBadge', afterDatasetsDraw(chart,args,opts){
    if (!opts?.enabled) return;
    const ds=chart.data.datasets?.[0]; if (!ds || !ds.data?.length) return;
    const i=ds.data.length-1, meta=chart.getDatasetMeta(0), pt=meta?.data?.[i]; if (!pt) return;
    const cur=+ds.data[i]||0; const prev=i>0?(+ds.data[i-1]||0):null;
    const {ctx}=chart;
    let label='—', up=null;
    if (prev !== null && prev !== undefined){
      if (prev === 0){
        if (cur === 0){ label = '0%'; up = null; }
        else { label = '▲ n/a'; up = true; } // sin base para % (prev=0)
      } else {
        const pct = ((cur - prev) / prev) * 100;
        up = pct >= 0;
        label = (up ? '▲ ' : '▼ ') + Math.abs(pct).toFixed(1) + '%';
      }
    }
    const padX=8, x=pt.x+10, y=pt.y-18;
    ctx.save(); ctx.font='bold 12px system-ui, -apple-system, Segoe UI, Roboto';
    const w=ctx.measureText(label).width; ctx.fillStyle=up===null?'rgba(107,114,128,.9)':(up?'rgba(34,197,94,.9)':'rgba(239,68,68,.9)');
    ctx.beginPath(); const r=8,h=24; ctx.moveTo(x+r,y); ctx.arcTo(x+w+padX*2,y,x+w+padX*2,y+h,r);
    ctx.arcTo(x+w+padX*2,y+h,x,y+h,r); ctx.arcTo(x,y+h,x,y,r); ctx.arcTo(x,y,x+w+padX*2,y,r); ctx.closePath(); ctx.fill();
    ctx.fillStyle='#fff'; ctx.fillText(label, x+padX, y+16); ctx.restore();
  }};

  // Ingresos (line)
  const el1=document.getElementById('chartIncome');
  if (el1){
    destroyCanvasChartByEl(el1);
    chartsRef.income=new Chart(el1,{ type:'line',
      data:{ labels:labelsUI, datasets:[{ label:'Ingresos', data:ingresos, tension:.35, borderColor:colors().green,
        pointRadius:2, pointHoverRadius:5, pointHitRadius:10, borderWidth:2, fill:true,
        backgroundColor:(ctx)=>{ const area=ctx.chart.chartArea; if(!area) return 'transparent'; return mkGrad(ctx.chart.ctx, area, 'rgba(34,197,94,.28)','rgba(34,197,94,.04)'); },
        segment:{ borderColor:ctx=>(ctx.p1.parsed.y>=ctx.p0.parsed.y)?colors().green:colors().down },
        pointBackgroundColor:(ctx)=>{ const i=ctx.dataIndex; if(i===0) return colors().green; const up=ctx.dataset.data[i]>=ctx.dataset.data[i-1]; return up?colors().green:colors().down; } }]},
      options:{ ...common, plugins:{ ...common.plugins, legend:{display:false}, lastPointBadge:{enabled:true}, p360NoData:{enabled:true, title:'Sin datos', reason:'Revisa columnas de pagos (monto/fecha) o el rango'} } },
      plugins:[lastPointBadge]
    });
    chartsRef.income.data.__labelsYM = labelsYM.slice();
  }

  // Timbres (bar)
  const el2=document.getElementById('chartStamps');
  if (el2){
    destroyCanvasChartByEl(el2);
    chartsRef.stamps=new Chart(el2,{ type:'bar',
      data:{ labels:labelsUI, datasets:[{ label:'Timbres', data:timbres, borderWidth:0, borderRadius:8, barPercentage:.7, categoryPercentage:.5, maxBarThickness:42,
        backgroundColor:(ctx)=>{ const area=ctx.chart.chartArea; if(!area) return colors().blueHi; return mkGrad(ctx.chart.ctx, area, colors().blueHi, colors().blueLo); } }]},
      options:{ ...common, plugins:{ ...common.plugins, legend:{display:false}, p360NoData:{enabled:true, title:'Sin datos', reason:'No se encontraron CFDI en el periodo'} }, scales:{ x:{...common.scales.x}, y:{...common.scales.y, beginAtZero:true} } }
    });
  }

  // Planes (doughnut)
  const el3=document.getElementById('chartPlans');
  if (el3){
    destroyCanvasChartByEl(el3);
    const labels=Object.keys(planesObj); const vals=Object.values(planesObj); const total=vals.reduce((a,b)=>a+b,0)||1;
    const centerTotal={ id:'centerTotal', afterDraw(chart,args,opts){ if(!opts?.enabled) return; const meta=chart.getDatasetMeta(0); if(!meta?.data?.length)return;
      const {ctx}=chart,{x,y}=meta.data[0]; const txt=opts.text||`Total ${new Intl.NumberFormat().format(total)}`;
      ctx.save(); ctx.font='600 14px system-ui, -apple-system, Segoe UI, Roboto'; ctx.fillStyle=colors().text; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText(txt,x,y); ctx.restore(); } };
    chartsRef.plans=new Chart(el3,{ type:'doughnut',
      data:{ labels, datasets:[{ data:vals, borderWidth:2, borderColor:colors().border, hoverOffset:8, backgroundColor:['#e31b23','#1f4ddb','#16a34a','#f59e0b','#8b5cf6','#06b6d4'] }]},
      options:{ responsive:true, maintainAspectRatio:false, devicePixelRatio:DPI_CAP, cutout:'64%', spacing:3,
        plugins:{ legend:{ position:'bottom', labels:{ color:colors().text, usePointStyle:true, boxWidth:10, boxHeight:10, padding:16 } },
          tooltip:{ backgroundColor:tt.bg, borderColor:tt.bd, borderWidth:1, titleColor:tt.txt, bodyColor:tt.txt,
            callbacks:{ label:(ctx)=>` ${ctx.label}: ${ctx.parsed} (${(ctx.parsed/(total||1)*100).toFixed(1)}%)` } },
          centerTotal:{enabled:true}, p360NoData:{enabled:true, title:'Sin datos', reason:'No hay clientes por plan'} } },
      plugins:[centerTotal]
    });
  }

  // Ingresos por plan (stacked bar) + placeholder
  const el4=document.getElementById('chartIncomePlan');
  if (el4){
    destroyCanvasChartByEl(el4);
    const planKeys=Object.keys(byPlan.plans||{});
    const datasets=planKeys.map((p,i)=>({
      label:p, data:(byPlan.plans[p]||[]), borderWidth:0, borderRadius:6,
      backgroundColor:['#1f4ddb','#16a34a','#f59e0b','#8b5cf6','#06b6d4','#ef4444','#10b981'][i%7]
    }));
    const reason = (diag?.columns?.pagos && diag.columns.pagos && diag.columns.pagos.cliente_id === false)
      ? 'pagos.cliente_id no existe → usando fallback'
      : 'No hay montos por plan en el periodo';
    chartsRef.incomePlan=new Chart(el4,{ type:'bar',
      data:{ labels: (byPlan.labels?.length? byPlan.labels : labelsYM).map(prettyMonth), datasets },
      options:{ ...common, scales:{ x:{...common.scales.x, stacked:true}, y:{...common.scales.y, beginAtZero:true, stacked:true} },
        plugins:{ ...common.plugins, p360NoData:{enabled:true, title:'Sin datos', reason} } }
    });
  }

  // Nuevos clientes (line)
  const el5=document.getElementById('chartNewClients');
  if (el5){
    destroyCanvasChartByEl(el5);
    chartsRef.newClients=new Chart(el5,{ type:'line',
      data:{ labels: nuevosLabels.map(prettyMonth), datasets:[{ label:'Altas', data:nuevosValues, tension:.35, borderColor:'#1f4ddb', borderWidth:2, pointRadius:2, fill:true,
        backgroundColor:(ctx)=>{ const area=ctx.chart.chartArea; if(!area) return 'transparent'; return mkGrad(ctx.chart.ctx, area, 'rgba(31,77,219,.25)','rgba(31,77,219,.04)'); } }]},
      options:{ ...common, plugins:{ ...common.plugins, legend:{display:false}, p360NoData:{enabled:true, title:'Sin datos', reason:'Sin altas en el periodo'} },
        scales:{ x:{...common.scales.x}, y:{...common.scales.y, beginAtZero:true, ticks:{ precision:0, color:colors().text } } } }
    });
  }

  // Top clientes (horizontal bar) + placeholder
  const el6=document.getElementById('chartTopClients');
  if (el6){
    destroyCanvasChartByEl(el6);
    const reason = (diag?.columns?.pagos && diag.columns.pagos && diag.columns.pagos.cliente_id === false)
      ? 'pagos.cliente_id no existe → no es posible agrupar por cliente'
      : 'No hubo pagos en el periodo';
    chartsRef.topClients=new Chart(el6,{ type:'bar',
      data:{ labels: top.labels||[], datasets:[{ label:'Ingresos', data: top.values||[], borderWidth:0, borderRadius:6, backgroundColor:'#06b6d4' }] },
      options:{ ...common, indexAxis:'y', scales:{ x:{...common.scales.x, beginAtZero:true}, y:{...common.scales.y} },
        plugins:{ ...common.plugins, legend:{display:false},
          tooltip:{ ...common.plugins.tooltip, callbacks:{ label:(ctx)=>` ${fmtCur.format(ctx.parsed.x||0)}` } },
          p360NoData:{enabled:true, title:'Sin datos', reason} } }
    });
  }

  // Scatter ingresos vs timbres
  const el7=document.getElementById('chartScatter');
  if (el7){
    destroyCanvasChartByEl(el7);
    chartsRef.scatter=new Chart(el7,{ type:'scatter',
      data:{ datasets:[{ label:'Meses', data: scatter.map(p=>({x:p.x,y:p.y,label:p.label})), backgroundColor:'#8b5cf6' }] },
      options:{ responsive:true, maintainAspectRatio:false, devicePixelRatio:DPI_CAP,
        plugins:{ legend:{display:false}, tooltip:{ backgroundColor:tt.bg, borderColor:tt.bd, borderWidth:1, titleColor:tt.txt, bodyColor:tt.txt,
          callbacks:{ label:(ctx)=>{ const p=ctx.raw||{}; return ` ${prettyMonth(p.label||'')}: ${fmtCur.format(p.x||0)} vs ${fmtInt.format(p.y||0)} timbres`; } } },
          p360NoData:{enabled:true, title:'Sin datos', reason:'No hay correlación disponible'} },
        scales:{ x:{ title:{display:true,text:'Ingresos'}, ticks:{ color:colors().text }, grid:{ color:c.grid, borderDash:[4,4] } },
                 y:{ title:{display:true,text:'Timbres'},  ticks:{ color:colors().text }, grid:{ color:c.grid, borderDash:[4,4] } } } }
    });
  }

  // Variación mensual (%)
  const elMoM = document.getElementById('chartMoM');
  if (elMoM){
    const mom = (ingresos||[]).map((v,i)=>{
      if (i===0) return 0;
      const prev = Number(ingresos[i-1])||0;
      return prev===0 ? 0 : ((Number(v)-prev)/prev*100);
    });
    destroyCanvasChartByEl(elMoM);
    chartsRef.mom = new Chart(elMoM, {
      type:'bar',
      data:{ labels: labelsUI, datasets:[{ label:'% MoM', data:mom, borderWidth:1.5, borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,.25)' }] },
      options:{ ...common, plugins:{ ...common.plugins, legend:{display:false}, tooltip:{ ...common.plugins.tooltip,
        callbacks:{ label:(ctx)=>` ${ctx.parsed.y.toFixed(1)}%` } },
        p360NoData:{enabled:true, title:'Sin datos', reason:'Se requiere al menos 2 meses con ingresos > 0'} },
        scales:{ y:{ ...common.scales.y, ticks:{ callback:(v)=>v+'%' } }, x:{ ...common.scales.x, grid:{ display:false } } }
      }
    });
  }

  hudLog(`charts pintados: ${Object.keys(chartsRef).filter(k=>chartsRef[k]).length}`);
}

export function onIncomeClick(handler){
  const el1 = document.getElementById('chartIncome');
  if (!el1 || !chartsRef.income) return;
  el1.onclick = (evt)=>{
    const pts = chartsRef.income.getElementsAtEventForMode(evt,'nearest',{intersect:false},true);
    if (!pts.length) return;
    const idx = pts[0].index;
    const ym = (chartsRef.income.data.__labelsYM || [])[idx];
    if (ym) handler(ym);
  };
}
export function setLabelsYMForIncome(labelsYM){
  if (chartsRef.income){ chartsRef.income.data.__labelsYM = labelsYM.slice(); }
}
export function resetAllZoom(){ chartsRef.income?.resetZoom?.(); chartsRef.stamps?.resetZoom?.(); }
