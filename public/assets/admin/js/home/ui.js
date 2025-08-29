// ui.js
import { $, $$, fmtInt, fmtCur, prettyMonth, escapeHTML, debounce,
         exportIncomeCSV, exportStampsCSV, exportPlansCSV,
         exportIncomePlanCSV, exportNewClientsCSV, exportTopClientsCSV, exportScatterCSV } from './helpers.js';
import { chartsRef } from './state.js';
import * as api from './api.js';

const overlay = $('#loadingOverlay');
const alerts  = $('#alerts');

export function showLoading(){
  const root=document.querySelector('.page');
  root?.setAttribute('aria-busy','true');
  if (overlay){ overlay.style.display='flex'; overlay.setAttribute('aria-hidden','false'); }
}
export function hideLoading(){
  const root=document.querySelector('.page');
  root?.setAttribute('aria-busy','false');
  if (overlay){ overlay.style.display='none'; overlay.setAttribute('aria-hidden','true'); }
}
export function showAlert(type,msg){
  if(!alerts) return;
  const el=document.createElement('div');
  el.className=`alert alert-${type}`;
  el.innerHTML=`<span class="alert-msg">${escapeHTML(msg||'')}</span><button class="alert-close" aria-label="Cerrar" type="button">×</button>`;
  alerts.appendChild(el);
  el.querySelector('.alert-close')?.addEventListener('click',()=>el.remove());
  setTimeout(()=>el.remove(),6000);
}

export function hydrateFilters(data){
  const fPlan   = $('#fPlan'); const fMonths = $('#fMonths');
  const opts = (data.filters && data.filters.planOptions) || [];
  if (fPlan && fPlan.children.length <= 1) {
    for (const o of opts){
      const opt=document.createElement('option');
      opt.value=o.value; opt.textContent=o.label;
      fPlan.appendChild(opt);
    }
  }
  if (fPlan && data.filters?.plan)   fPlan.value   = data.filters.plan;
  if (fMonths && data.filters?.months) fMonths.value = String(data.filters.months);
}

export function paintKPIs(data){
  const k = data.kpis || {};
  const setText=(id,val)=>{ const el=document.getElementById(id); if (el) el.textContent = val; };
  setText('kpi_clientes',   fmtInt.format(k.totalClientes||0));
  setText('kpi_activos',    fmtInt.format(k.activos||0));
  setText('kpi_inactivos',  fmtInt.format(k.inactivos||0));
  setText('kpi_nuevos',     fmtInt.format(k.nuevosMes||0));
  setText('kpi_pendientes', fmtInt.format(k.pendientes||0));
  setText('kpi_premium',    fmtInt.format(k.premium||0));
  setText('kpi_timbres',    fmtInt.format(k.timbresUsados||0));
  setText('kpi_ingresos',   fmtCur.format(k.ingresosMes||0));
}

/* Modal ingresos por mes */
const modal=$('#modalIncome'),
      modalMonth=$('#modalIncomeMonth'),
      modalTbody=$('#incomeModalTbody'),
      modalEmpty=$('#incomeModalEmpty'),
      modalTotal=$('#incomeModalTotal'),
      modalSearch=$('#incomeModalSearch'),
      modalExport=$('#incomeModalExport');

let modalRows=[]; let modalSortBy='fecha', modalSortDir='asc';

export async function openIncomeModal(ym){
  try{
    showLoading();
    const data=await api.fetchIncomeMonth(ym);
    modalRows=data?.rows||[];
    if(modalMonth) modalMonth.textContent=ym;
    drawIncomeModal();
    openModal();
  } catch(e){
    console.error('income month error', e);
    showAlert('error','No se pudieron cargar los pagos del mes seleccionado.');
  } finally{
    hideLoading();
  }
}

function drawIncomeModal(){
  if (!modalTbody) return;
  const q=(modalSearch?.value||'').toLowerCase();
  let rows=modalRows.filter(r=>
    !q ||
    (r.cliente||'').toLowerCase().includes(q) ||
    (r.rfc||'').toLowerCase().includes(q) ||
    (r.referencia||'').toLowerCase().includes(q)
  );
  rows.sort((a,b)=>{
    const s=(modalSortDir==='asc')?1:-1;
    if(modalSortBy==='monto') return (a.monto-b.monto)*s;
    return String(a[modalSortBy]||'').localeCompare(String(b[modalSortBy]||''))*s;
  });

  modalTbody.innerHTML='';
  if(!rows.length){
    modalEmpty?.classList.remove('hidden');
  } else {
    modalEmpty?.classList.add('hidden');
    for(const r of rows){
      const tr=document.createElement('tr');
      tr.innerHTML=`<td>${escapeHTML(r.fecha)}</td>
                    <td>${escapeHTML(r.cliente)}</td>
                    <td>${escapeHTML(r.rfc)}</td>
                    <td>${escapeHTML(r.metodo)}</td>
                    <td>${escapeHTML(r.estado)}</td>
                    <td>${fmtCur.format(r.monto||0)}</td>`;
      modalTbody.appendChild(tr);
    }
  }
  const total=rows.reduce((s,r)=>s+(r.monto||0),0);
  if(modalTotal) modalTotal.textContent=fmtCur.format(total);
}
function openModal(){ modal?.classList.add('open');  modal?.setAttribute('aria-hidden','false'); }
function closeModal(){ modal?.classList.remove('open'); modal?.setAttribute('aria-hidden','true'); }

modal?.addEventListener('click',(e)=>{
  if (e.target.matches('[data-close], .modal-backdrop')) closeModal();
});
document.addEventListener('keydown',(e)=>{
  if(e.key==='Escape' && modal?.classList.contains('open')) closeModal();
});
$('#incomeModalTable')?.querySelectorAll('th[data-sort]')?.forEach(th=>{
  th.addEventListener('click',()=>{
    const col=th.getAttribute('data-sort');
    if(modalSortBy===col) modalSortDir=(modalSortDir==='asc'?'desc':'asc');
    else { modalSortBy=col; modalSortDir=(col==='fecha'?'asc':'desc'); }
    drawIncomeModal();
  });
});
modalSearch?.addEventListener('input', debounce(drawIncomeModal, 120));
modalExport?.addEventListener('click', ()=>{
  const header=['Fecha','Cliente','RFC','Método','Estado','Monto'];
  const lines=[header.join(',')];
  for (const r of modalRows){
    lines.push([`"${r.fecha}"`,`"${r.cliente||''}"`,`"${r.rfc||''}"`,`"${r.metodo||''}"`,`"${r.estado||''}"`,r.monto].join(','));
  }
  const blob=new Blob([lines.join('\n')],{type:'text/csv;charset=utf-8;'});
  const a=document.createElement('a');
  a.href=URL.createObjectURL(blob);
  a.download=`pagos_${(modalMonth?.textContent||'mes')}.csv`;
  a.click();
  URL.revokeObjectURL(a.href);
});

/* Toolbar (usa getCache en el click para datos frescos) */
export function bindToolbar(getCache){
  const once=(id,fn)=>{
    const el=document.getElementById(id);
    if(!el || el.dataset.bound) return;
    el.addEventListener('click',fn);
    el.dataset.bound='1';
  };

  // existentes
  once('btnIncomeResetZoom', ()=> chartsRef.income?.resetZoom?.());
  once('btnIncomePNG',       ()=> import('./helpers.js').then(h=> h.downloadPNG(chartsRef.income, 'ingresos_mensuales.png')));
  once('btnIncomeCSV',       ()=> {
    const c = getCache();
    exportIncomeCSV(c?.ingresosTable || []);
  });

  once('btnStampsResetZoom', ()=> chartsRef.stamps?.resetZoom?.());
  once('btnStampsPNG',       ()=> import('./helpers.js').then(h=> h.downloadPNG(chartsRef.stamps, 'timbres_mensuales.png')));
  once('btnStampsCSV',       ()=> {
    const c = getCache();
    const labelsYM=c?.series?.labels||[];
    const labelsUI=labelsYM.map(prettyMonth);
    exportStampsCSV(labelsUI, c?.series?.timbres||[]);
  });

  once('btnPlansPNG',        ()=> import('./helpers.js').then(h=> h.downloadPNG(chartsRef.plans, 'clientes_por_plan.png')));
  once('btnPlansCSV',        ()=> {
    const c = getCache();
    exportPlansCSV(c?.series?.planes || {});
  });

  once('btnResetZoom',       ()=> { chartsRef.income?.resetZoom?.(); chartsRef.stamps?.resetZoom?.(); });
  once('btnDownloadIncomePNG',()=> import('./helpers.js').then(h=> h.downloadPNG(chartsRef.income, 'ingresos_mensuales.png')));
  once('btnDownloadIncomeCSV',()=> {
    const c = getCache();
    exportIncomeCSV(c?.ingresosTable || []);
  });

  // nuevas
  once('btnIncomePlanPNG', ()=> import('./helpers.js').then(h=> h.downloadPNG(chartsRef.incomePlan, 'ingresos_por_plan.png')));
  once('btnIncomePlanCSV', ()=> {
    const c = getCache();
    exportIncomePlanCSV(c?.extra?.ingresosPorPlan?.labels||[], c?.extra?.ingresosPorPlan?.plans||{});
  });

  once('btnNewClientsPNG', ()=> import('./helpers.js').then(h=> h.downloadPNG(chartsRef.newClients, 'nuevos_clientes.png')));
  once('btnNewClientsCSV', ()=> {
    const c = getCache();
    exportNewClientsCSV(c?.extra?.nuevosClientes?.labels||[], c?.extra?.nuevosClientes?.values||[]);
  });

  once('btnTopClientsPNG', ()=> import('./helpers.js').then(h=> h.downloadPNG(chartsRef.topClients, 'top_clientes.png')));
  once('btnTopClientsCSV', ()=> {
    const c = getCache();
    exportTopClientsCSV(c?.extra?.topClientes?.labels||[], c?.extra?.topClientes?.values||[]);
  });

  once('btnScatterPNG',    ()=> import('./helpers.js').then(h=> h.downloadPNG(chartsRef.scatter, 'correlacion_ingresos_timbres.png')));
  once('btnScatterCSV',    ()=> {
    const c = getCache();
    exportScatterCSV(c?.extra?.scatterIncomeStamps||[]);
  });
}

export function bindGlobalEvents(onReload, resizeHandler, abortApi){
  const btnApply = $('#btnApply');
  btnApply?.addEventListener('click', ()=> onReload());
  window.addEventListener('p360:theme', ()=> onReload(true));
  window.addEventListener('resize', resizeHandler);

  const ro=new ResizeObserver(()=> resizeHandler());
  $$('.chart-card .chart-wrap').forEach(el=> ro.observe(el));

  window.addEventListener('beforeunload', ()=> abortApi());
}
export function openMonthFromTable(ym){ return openIncomeModal(ym); }
