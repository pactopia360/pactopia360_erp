// public/assets/admin/js/home/ui.js
import { $, money, num } from './helpers.js';
import { buildStatsUrl } from './api.js';

export function showLoading() {
  const ov = document.getElementById('loadingOverlay');
  if (ov) ov.style.display = 'grid';
  document.querySelector('.page')?.setAttribute('aria-busy','true');
}

export function hideLoading() {
  const ov = document.getElementById('loadingOverlay');
  if (ov) ov.style.display = 'none';
  document.querySelector('.page')?.setAttribute('aria-busy','false');
}

export function showAlert(kind, msg) {
  // si tienes sistema global, úsalo
  try {
    if (window.P360?.toast) {
      if (kind === 'error' && window.P360.toast.error) return window.P360.toast.error(msg);
      if (window.P360.toast.info) return window.P360.toast.info(msg);
    }
  } catch (_) {}
  alert(msg);
}

export function hydrateFilters(data) {
  // si backend devuelve defaults, puedes hidratar aquí.
  // no forzamos nada para no romper.
  return data;
}

export function paintKPIs(data) {
  const series = data?.series || {};
  const ingresos = Array.isArray(series.ingresos) ? series.ingresos
                 : Array.isArray(series.income) ? series.income : [];
  const timbres  = Array.isArray(series.timbres) ? series.timbres
                 : Array.isArray(series.stamps) ? series.stamps : [];
  const clientes = Array.isArray(series.clientes) ? series.clientes
                 : Array.isArray(series.clients) ? series.clients : [];

  const sum = (arr)=> arr.reduce((a,b)=> a + (Number(b)||0), 0);

  const inc = sum(ingresos);
  const st  = sum(timbres);
  const cl  = sum(clientes);
  const arpa = cl > 0 ? (inc / cl) : 0;

  const k1 = $('#kpiIncome');  if (k1) k1.textContent = money(inc);
  const k2 = $('#kpiStamps');  if (k2) k2.textContent = num(st);
  const k3 = $('#kpiClients'); if (k3) k3.textContent = num(cl);
  const k4 = $('#kpiArpa');    if (k4) k4.textContent = money(arpa);
}

export function openMonthFromTable(ym) {
  // ym: "YYYY-MM"
  const page = document.querySelector('.page');
  const tpl  = (page?.dataset?.incomeUrl || '').trim();

  if (tpl && tpl.includes('__YM__')) {
    window.location.href = tpl.replace('__YM__', ym);
    return;
  }

  // fallback: intentar abrir el stats con filtro “solo ese mes”
  const base = buildStatsUrl();
  if (!base) return;

  try {
    const u = new URL(base, window.location.origin);
    u.searchParams.set('from', ym);
    u.searchParams.set('to', ym);
    window.location.href = u.toString();
  } catch (_) {}
}

export function bindToolbar(getCacheFn) {
  // por si quieres “Aplicar / Reiniciar / Cancelar” con la misma UX
  const btnApply = $('#btnApply');
  const btnReset = $('#btnReset');

  btnApply?.addEventListener('click', () => {
    // el index.js ya maneja el load(true) desde bindGlobalEvents
  });

  btnReset?.addEventListener('click', () => {
    const fFrom  = $('#fFrom');
    const fTo    = $('#fTo');
    const fScope = $('#fScope');
    const fGroup = $('#fGroup');

    if (fFrom)  fFrom.value = '';
    if (fTo)    fTo.value = '';
    if (fScope) fScope.value = 'paid';
    if (fGroup) fGroup.value = 'month';

    // opcional: si existe cache, limpiar UI
    try { void getCacheFn?.(); } catch(_) {}
  });
}

export function bindGlobalEvents(onApply, onResize, onAbort) {
  const btnApply = $('#btnApply');
  const btnReset = $('#btnReset');
  const btnAbort = $('#btnAbort');

  btnApply?.addEventListener('click', () => onApply?.());
  btnReset?.addEventListener('click', () => onApply?.());
  btnAbort?.addEventListener('click', () => onAbort?.());

  window.addEventListener('resize', () => onResize?.());
}
