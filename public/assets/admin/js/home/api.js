// api.js
import { root } from './helpers.js';
import { abortCurrent, setLoadingCtrl } from './state.js';

export function statsUrlBase() {
  return root?.getAttribute('data-stats-url') || '';
}
export function incomeTplBase() {
  return root?.getAttribute('data-income-url') || '';
}

export function buildStatsUrl() {
  const base = statsUrlBase();
  if (!base) return '';
  const u = new URL(base, window.location.origin);
  const fMonths = document.getElementById('fMonths');
  const fPlan   = document.getElementById('fPlan');
  u.searchParams.set('months', (fMonths?.value || 12));
  u.searchParams.set('plan',   (fPlan?.value   || 'all'));
  // dejamos debug=1 para diagnostics del backend
  u.searchParams.set('debug', '1');
  return u.toString();
}

export function queryKeyFromUrl(u) {
  try {
    const url = new URL(u, window.location.origin);
    // clave compacta sólo con months y plan (ignora debug/nocache/etc.)
    return `${url.pathname}?${url.searchParams.get('months')||''}|${(url.searchParams.get('plan')||'').toLowerCase()}`;
  } catch { return u; }
}

export function withPlan(u) {
  const fPlan = document.getElementById('fPlan');
  const url = new URL(u, window.location.origin);
  url.searchParams.set('plan', fPlan?.value || 'all');
  return url.toString();
}

// --- helpers internos ---
function handleAuth(res) {
  if (res.status === 401 || res.status === 419) {
    // sesión expirada → vuelve al login admin
    window.location.href = '/admin/login';
    const e = new Error('unauthorized');
    // marcar como “aborto lógico” para que el llamador no lo trate como error visible
    e.__aborted = true;
    throw e;
  }
}

export async function fetchStats() {
  abortCurrent();
  const ctrl = new AbortController();
  setLoadingCtrl(ctrl);

  const url = buildStatsUrl();
  const res = await fetch(url, {
    signal: ctrl.signal,
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    },
    credentials: 'same-origin',
    cache: 'no-store'
  });

  handleAuth(res);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return await res.json();
}

export async function fetchIncomeMonth(ym) {
  const tpl = incomeTplBase();
  if (!tpl) return { rows: [], totals: { monto: 0, pagos: 0 }, ym };

  const url = withPlan(tpl.replace('__YM__', ym));
  const res = await fetch(url, {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    },
    credentials: 'same-origin',
    cache: 'no-store'
  });

  handleAuth(res);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return await res.json();
}
