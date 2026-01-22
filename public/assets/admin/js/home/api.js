// public/assets/admin/js/home/api.js
import { $, } from './helpers.js';
import { newAbortController, getAbortSignal } from './state.js';

function getPage() {
  return document.querySelector('.page');
}

export function buildStatsUrl() {
  const page = getPage();
  const base = (page?.dataset?.statsUrl || '').trim();
  if (!base) return '';

  const from  = $('#fFrom')?.value || '';
  const to    = $('#fTo')?.value || '';
  const scope = $('#fScope')?.value || 'paid';
  const group = $('#fGroup')?.value || 'month';

  const u = new URL(base, window.location.origin);
  if (from)  u.searchParams.set('from', from);
  if (to)    u.searchParams.set('to', to);
  if (scope) u.searchParams.set('scope', scope);
  if (group) u.searchParams.set('group', group);

  return u.toString();
}

export function queryKeyFromUrl(url) {
  try {
    const u = new URL(url, window.location.origin);
    // ordenar params para que el key sea estable
    const params = Array.from(u.searchParams.entries())
      .sort((a,b)=> (a[0]+a[1]).localeCompare(b[0]+b[1]))
      .map(([k,v])=>`${k}=${v}`)
      .join('&');
    return `${u.pathname}?${params}`;
  } catch (_) {
    return String(url || '');
  }
}

export async function fetchStats() {
  const url = buildStatsUrl();
  if (!url) {
    const e = new Error('STATS_URL vacío (data-stats-url no está configurado en .page)');
    e.__aborted = false;
    throw e;
  }

  // crea/renueva controller (cancela la anterior)
  newAbortController();

  const res = await fetch(url, {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    },
    signal: getAbortSignal()
  });

  if (!res.ok) {
    const e = new Error(`HTTP ${res.status} en stats`);
    e.httpStatus = res.status;
    throw e;
  }

  const ct = (res.headers.get('content-type') || '').toLowerCase();
  if (!ct.includes('application/json')) {
    const e = new Error('Stats no devolvió JSON (posible HTML/redirect)');
    e.contentType = ct;
    throw e;
  }

  return await res.json();
}
