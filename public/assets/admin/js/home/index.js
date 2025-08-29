// index.js (sin /home/ytd ni /home/compare; con HUD de logs + manejo de AbortError)
import { $ } from './helpers.js';
import { setCache, cache, setLastQueryKey, lastQueryKey, abortCurrent } from './state.js';
import { buildStatsUrl, queryKeyFromUrl } from './api.js';
import * as api from './api.js';
import * as ui from './ui.js';
import * as charts from './charts.js';
import * as tables from './tables.js';
import { debounce } from './helpers.js';

// Detectar abortos (cancels) para no tratarlos como errores
const isAbort = (e) =>
  e?.name === 'AbortError' ||
  e?.code === 20 ||
  String(e?.message || '').toLowerCase().includes('abort') ||
  e?.__aborted === true;

console.log('[Home] init+');

/* =========================
   HUD de Logs (visible en pantalla)
   ========================= */
function initHud(){
  const hud = document.getElementById('devHud');
  if (!hud) return null;

  const logsEl   = document.getElementById('devHudLogs');
  const toggle   = document.getElementById('devHudToggle');
  const btnCopy  = document.getElementById('hudCopy');
  const btnClear = document.getElementById('hudClear');
  const bChart   = document.getElementById('hudChartJs');
  const bZoom    = document.getElementById('hudZoom');
  const bData    = document.getElementById('hudData');
  const bErr     = document.getElementById('hudErrors');

  let errCount = 0;

  const apiObj = {
    log(msg, type='log'){
      const ts = new Date().toISOString().split('T')[1].replace('Z','');
      const line = `[${ts}] ${msg}`;
      if (logsEl){
        logsEl.textContent += (logsEl.textContent ? '\n' : '') + line;
        logsEl.scrollTop = logsEl.scrollHeight;
      }
      if (type==='error'){ errCount++; if (bErr) bErr.textContent = `Errores: ${errCount}`; }
    },
    setStatus({ chartjs, zoom, dataLen }){
      if (bChart) bChart.textContent = `Chart.js: ${chartjs?'OK':'—'}`;
      if (bZoom)  bZoom.textContent  = `Zoom: ${zoom?'OK':'—'}`;
      if (bData)  bData.textContent  = `Datos: ${typeof dataLen==='number'?dataLen:'—'}`;
    }
  };

  toggle?.addEventListener('click', ()=> hud.setAttribute('data-open','true'));
  btnClear?.addEventListener('click', ()=> { if (logsEl) logsEl.textContent=''; });
  btnCopy?.addEventListener('click', async ()=>{
    try{ await navigator.clipboard.writeText(logsEl?.textContent || ''); apiObj.log('Logs copiados al portapapeles'); }catch{}
  });

  // duplicar consola
  const _log = console.log, _warn = console.warn, _err = console.error;
  console.log = (...args)=>{ try{ apiObj.log(args.join(' '),'log'); }catch{} _log.apply(console,args); };
  console.warn = (...args)=>{ try{ apiObj.log(args.join(' '),'warn'); }catch{} _warn.apply(console,args); };
  console.error = (...args)=>{ try{ apiObj.log(args.join(' '),'error'); }catch{} _err.apply(console,args); };
  window.addEventListener('error', (ev)=>{ apiObj.log(String(ev?.error || ev?.message || ev), 'error'); });

  window.__hud = apiObj; // para otros módulos
  return apiObj;
}
const HUD = initHud();

/* =========================
   App
   ========================= */
function resizeHandler(){ charts.resizeCharts(); }

async function load(force=false){
  const url = buildStatsUrl();
  const qKey = queryKeyFromUrl(url);
  if (!force && qKey === lastQueryKey && cache){ charts.makeCharts(cache); return; }
  setLastQueryKey(qKey);

  try {
    ui.showLoading();
    HUD?.log('Solicitando estadísticas…');
    const data = await api.fetchStats();

    setCache(data);
    ui.hydrateFilters(data);
    ui.paintKPIs(data);

    charts.makeCharts(data, /*force*/true);
    charts.setLabelsYMForIncome(data.series?.labels || []);
    charts.onIncomeClick(ui.openMonthFromTable);

    tables.renderIncomeTable(data, ui.openMonthFromTable);
    tables.renderClientsTable(data);

    ui.bindToolbar(()=> cache);

    // HUD status
    const len = (data?.series?.ingresos || data?.series?.income || []).length;
    HUD?.setStatus({ chartjs: !!window.Chart, zoom: !!window.Chart?.registry?.plugins?.get('zoom'), dataLen: len });
    HUD?.log(`Estadísticas cargadas. Meses: ${len}`);

    // Extras (YTD + diaria) calculados localmente
    renderExtraCharts(data);

  } catch (e){
    if (isAbort(e)) {
      HUD?.log('Solicitud cancelada (reemplazada por otra).');
      return; // NO error, NO alerta
    }
    console.error('stats error', e);
    HUD?.log('Error al cargar estadísticas', 'error');
    ui.showAlert('error', 'No se pudieron cargar las estadísticas. Intenta nuevamente.');
  } finally {
    ui.hideLoading();
  }
}

ui.bindGlobalEvents(()=>load(true), debounce(()=>resizeHandler(),120), ()=>abortCurrent());
if ($('.page')) load();

/* =========================
   Extras (YTD & diaria) sin endpoints nuevos
   ========================= */
function money(v){ return new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN',maximumFractionDigits:0}).format(v||0); }

function makeLineCfg(labels, data, label, stroke='rgba(27,86,255,1)'){
  return {
    type: 'line',
    data: { labels, datasets: [{
      label, data, tension:.3, borderWidth:2, pointRadius:0,
      borderColor: stroke, fill:true,
      backgroundColor:(ctx)=>{
        const {chart} = ctx;
        const h=(chart?.chartArea?.bottom||chart?.height||240)-(chart?.chartArea?.top||0);
        const g=chart.ctx.createLinearGradient(0,0,0,h);
        g.addColorStop(0, stroke.replace('1)', '.18)'));
        g.addColorStop(1, stroke.replace('1)', '0)'));
        return g;
      }
    } ]},
    options:{
      responsive:true, maintainAspectRatio:false,
      scales:{ x:{grid:{display:false}}, y:{grid:{color:'rgba(0,0,0,.06)'}} },
      plugins:{ legend:{display:false}, tooltip:{callbacks:{label:(t)=>`${t.dataset.label}: ${money(t.parsed.y)}`}} }
    }
  };
}

function attach(canvas, cfg){
  if (!canvas || !window.Chart) return;
  try { if (canvas.__chart) canvas.__chart.destroy(); canvas.__chart = new Chart(canvas.getContext('2d'), cfg); }
  catch(e){ console.error('attach chart fail', e); }
}

function renderExtraCharts(data){
  const cYTD   = document.getElementById('chartYTD');
  const cDaily = document.getElementById('chartDaily');
  if (!cYTD && !cDaily) return;
  if (typeof window.Chart === 'undefined') return;

  const now = new Date();
  const y   = now.getFullYear();
  const page = document.querySelector('.page');
  const incomeTpl = page?.dataset?.incomeUrl || ''; // .../incomeMonth?ym=__YM__

  // YTD a partir de series de ingresos
  try {
    if (cYTD){
      const labelsAll = (data?.series?.labels ?? []).map(String);
      const serie = (data?.series?.ingresos && Array.isArray(data.series.ingresos)) ? data.series.ingresos : null;
      if (labelsAll.length && serie && serie.length===labelsAll.length){
        const idx = labelsAll.reduce((acc,lab,i)=>{ if (lab.startsWith(`${y}-`)) acc.push(i); return acc; }, []);
        const labels = idx.map(i=>labelsAll[i].slice(5));
        const vals   = idx.map(i=>serie[i]);
        let run=0; const cumul = vals.map(v=> run += (v||0));
        attach(cYTD, makeLineCfg(labels, cumul, 'Acumulado YTD', 'rgba(27,86,255,1)'));
      } else {
        cYTD?.closest('.chart-card')?.classList.add('hidden');
      }
    }
  } catch(e){ console.error('YTD fallo', e); cYTD?.closest('.chart-card')?.classList.add('hidden'); }

  // Diaria (mes actual) usando incomeMonth
  (async ()=>{
    if (!cDaily) return;
    if (!incomeTpl || !incomeTpl.includes('__YM__')){ cDaily.closest('.chart-card')?.classList.add('hidden'); return; }

    const getMonthDaily = async (ym)=>{
      try{
        const url = incomeTpl.replace('__YM__', ym);
        const r = await fetch(url, { headers:{'X-Requested-With':'fetch'} });
        if (!r.ok) throw new Error('http '+r.status);
        const j = await r.json();

        let rows = Array.isArray(j) ? j :
                   Array.isArray(j?.rows) ? j.rows :
                   Array.isArray(j?.data) ? j.data :
                   Array.isArray(j?.items) ? j.items : [];
        const getDate  = (o)=> o.fecha || o.date || o.created_at || o.paid_at || o.fh || null;
        const getAmt   = (o)=> o.monto ?? o.amount ?? o.total ?? o.importe ?? 0;

        const [yy,mm] = ym.split('-').map(n=>parseInt(n,10));
        const daysInM = new Date(yy, mm, 0).getDate();
        const byDay = Array.from({length:daysInM}, ()=>0);

        rows.forEach(o=>{
          const dStr = getDate(o); if (!dStr) return;
          const d = new Date(dStr); if (isNaN(d)) return;
          const day = d.getDate(); const amt = Number(getAmt(o)) || 0;
          if (day>=1 && day<=daysInM) byDay[day-1] += amt;
        });
        return byDay;
      }catch(e){ console.error('daily fetch fail', e); return null; }
    };

    const ym = `${y}-${String(now.getMonth()+1).padStart(2,'0')}`;
    const ymBack = (offset)=>{ const d = new Date(now.getFullYear(), now.getMonth()-offset, 1); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`; };

    const cur    = await getMonthDaily(ym);
    const prev1  = await getMonthDaily(ymBack(1));
    const prev2  = await getMonthDaily(ymBack(2));

    if (!cur){ cDaily.closest('.chart-card')?.classList.add('hidden'); return; }

    const days = cur.length;
    const labels = Array.from({length:days}, (_,i)=> String(i+1));
    const avg = Array.from({length:days}, (_,i)=>{
      const vals = [prev1?.[i], prev2?.[i]].filter(v=>typeof v==='number');
      if (!vals.length) return 0; return vals.reduce((a,b)=>a+b,0) / vals.length;
    });

    const cfg = makeLineCfg(labels, cur, 'Actual', 'rgba(16,170,74,1)');
    cfg.data.datasets.push({ label:'Promedio', data:avg, tension:.3, borderWidth:2, pointRadius:0, borderDash:[6,4], borderColor:'rgba(99,102,241,1)', fill:false });
    attach(cDaily, cfg);
  })().catch((e)=>{ console.error('daily fallo', e); cDaily?.closest('.chart-card')?.classList.add('hidden'); });
}

// Hook opcional para otros módulos
function afterFirstPaintHook(data){
  try{ renderExtraCharts(data); }catch(e){ console.error('extras fail', e); }
}

// Señal para HUD cuando charts terminan
document.addEventListener('p360:charts-ready', ()=> HUD?.log('Charts listos'));

// Exponer para otros módulos (si se requiere)
window.__p360home = { afterFirstPaintHook };
