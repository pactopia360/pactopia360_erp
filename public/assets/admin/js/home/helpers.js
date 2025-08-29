// helpers.js
export const root = document.querySelector('.page');
export const LOCALE   = (root?.getAttribute('data-locale'))   || 'es-MX';
export const CURRENCY = (root?.getAttribute('data-currency')) || 'MXN';

export const fmtInt = new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 0 });
// Si quieres 2 decimales, cámbialo a maximumFractionDigits: 2
export const fmtCur = new Intl.NumberFormat(LOCALE, { style:'currency', currency:CURRENCY, maximumFractionDigits:0 });
export const fmtPct = new Intl.NumberFormat(LOCALE, { style:'percent',  maximumFractionDigits:1 });

export const $  = (s)=>document.querySelector(s);
export const $$ = (s)=>Array.prototype.slice.call(document.querySelectorAll(s));

export function debounce(fn, wait){ let t; return function(...a){ clearTimeout(t); t=setTimeout(()=>fn.apply(this,a),wait); }; }
export function csv(v){ return `"${String(v??'').replace(/"/g,'""')}"`; }
export function escapeHTML(s){
  return String(s??'').replace(/[&<>"'`=\/]/g, m=>({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'
  })[m]);
}

// Valida YYYY-MM y devuelve etiqueta amigable; si ym es inválido, regresa tal cual
function isValidYM(ym){ return typeof ym==='string' && /^\d{4}-(0[1-9]|1[0-2])$/.test(ym); }
export function prettyMonth(ym){
  if (!isValidYM(ym)) return String(ym ?? '');
  const [y,m] = ym.split('-').map(Number);
  const d = new Date(y, m-1, 1);
  return new Intl.DateTimeFormat(LOCALE, { month:'short', year:'numeric' }).format(d);
}

export const DPI_CAP = Math.min(window.devicePixelRatio || 1, 1.5);
export const REDUCED_MOTION = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

export function tooltipThemeColors(){
  const light = document.body.classList.contains('theme-light');
  return { bg: light?'rgba(255,255,255,.92)':'rgba(17,24,39,.92)', bd: light?'rgba(0,0,0,.08)':'rgba(255,255,255,.1)', txt: light?'#111827':'#e5e7eb' };
}
export function colors(){
  const light = document.body.classList.contains('theme-light');
  return {
    isLight: light, text: light?'#111827':'#e5e7eb', grid: light?'rgba(0,0,0,.06)':'rgba(255,255,255,.08)',
    red:'#e31b23', green: light?'#16a34a':'#22c55e', down: light?'#dc2626':'#ef4444',
    blueHi: light?'rgba(31,77,219,.95)':'rgba(96,165,250,.95)', blueLo: light?'rgba(31,77,219,.35)':'rgba(96,165,250,.35)',
    yellow:'#f59e0b', border: light?'#e5e7eb':'rgba(255,255,255,.12)'
  };
}
export function mkGrad(ctx, area, from, to){
  const g = ctx.createLinearGradient(0, area.top, 0, area.bottom);
  g.addColorStop(0, from); g.addColorStop(1, to);
  return g;
}
export function trendText(cur, prev){
  if (prev==null || prev===0) return '—';
  const diff=(cur-prev)/prev;
  const arrow=diff>0?'▲':(diff<0?'▼':'■');
  return `${arrow} ${fmtPct.format(Math.abs(diff))} vs mes anterior`;
}

export function safeResize(chart){
  if (!chart) return;
  const cv=chart.canvas;
  if (!cv || !cv.ownerDocument || !cv.isConnected) return;
  try{ chart.resize(); }catch{}
}
export function destroyCanvasChartByEl(el){
  if (!el || !window.Chart || !window.Chart.getChart) return;
  const inst=window.Chart.getChart(el);
  if (inst){ try{ inst.destroy(); }catch{} }
}
export function downloadPNG(chart, filename){
  if (!chart) return;
  try{
    const a=document.createElement('a');
    a.href=chart.toBase64Image();
    a.download=filename;
    a.click();
  }catch(e){ console.error('downloadPNG fail', e); }
}

/* ---------- CSV helpers (con BOM para Excel) ---------- */
const CSV_BOM = '\uFEFF';
function saveCSV(lines, filename){
  try{
    const blob = new Blob([CSV_BOM + lines.join('\n')], {type:'text/csv;charset=utf-8;'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download=filename;
    a.click();
    URL.revokeObjectURL(a.href);
  }catch(e){ console.error('saveCSV fail', e); }
}

export function exportIncomeCSV(rows){
  const header = ['Mes','Ingresos','Pagos','Ticket promedio'];
  const lines = [header.join(',')];
  for (const r of (rows||[])) lines.push([csv(r.label), r.total, r.pagos, r.avg].join(','));
  saveCSV(lines, 'ingresos_mensuales.csv');
}
export function exportStampsCSV(labelsUI, timbres){
  const header=['Mes','Timbres']; const lines=[header.join(',')];
  for (let i=0;i<labelsUI.length;i++) lines.push([csv(labelsUI[i]), timbres?.[i] ?? 0].join(','));
  saveCSV(lines, 'timbres_mensuales.csv');
}
export function exportPlansCSV(planesObj){
  const header=['Plan','Clientes']; const lines=[header.join(',')];
  for (const [plan,cnt] of Object.entries(planesObj||{})) lines.push([csv(plan), cnt ?? 0].join(','));
  saveCSV(lines, 'clientes_por_plan.csv');
}

/* ---- NUEVOS EXPORTS ---- */
export function exportIncomePlanCSV(labels, plansDict){
  const planKeys = Object.keys(plansDict||{});
  const header = ['Mes', ...planKeys];
  const lines = [header.join(',')];
  for (let i=0;i<labels.length;i++){
    const row = [csv(prettyMonth(labels[i]))];
    for (const p of planKeys) row.push((plansDict[p]?.[i] ?? 0));
    lines.push(row.join(','));
  }
  saveCSV(lines, 'ingresos_por_plan.csv');
}
export function exportNewClientsCSV(labels, values){
  const header=['Mes','Nuevos']; const lines=[header.join(',')];
  for (let i=0;i<labels.length;i++) lines.push([csv(prettyMonth(labels[i])), values?.[i] ?? 0].join(','));
  saveCSV(lines, 'nuevos_clientes.csv');
}
export function exportTopClientsCSV(labels, values){
  const header=['Cliente','Ingresos']; const lines=[header.join(',')];
  for (let i=0;i<labels.length;i++) lines.push([csv(labels?.[i]||''), values?.[i] ?? 0].join(','));
  saveCSV(lines, 'top_clientes.csv');
}
export function exportScatterCSV(points){
  const header=['Mes','Ingresos','Timbres']; const lines=[header.join(',')];
  for (const p of (points||[])) lines.push([csv(p?.label||''), p?.x ?? 0, p?.y ?? 0].join(','));
  saveCSV(lines, 'correlacion_ingresos_timbres.csv');
}
