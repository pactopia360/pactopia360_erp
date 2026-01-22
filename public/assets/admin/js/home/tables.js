// public/assets/admin/js/home/tables.js
import { money, num } from './helpers.js';

export function renderIncomeTable(data, onMonthClick) {
  const tbody = document.querySelector('#tblIncome tbody');
  if (!tbody) return;

  const labels = (data?.series?.labels || []).map(String);
  const ingresos = Array.isArray(data?.series?.ingresos) ? data.series.ingresos
                 : Array.isArray(data?.series?.income) ? data.series.income : [];
  const timbres  = Array.isArray(data?.series?.timbres) ? data.series.timbres
                 : Array.isArray(data?.series?.stamps) ? data.series.stamps : [];
  const clientes = Array.isArray(data?.series?.clientes) ? data.series.clientes
                 : Array.isArray(data?.series?.clients) ? data.series.clients : [];

  const n = Math.max(labels.length, ingresos.length, timbres.length, clientes.length);
  if (!n) {
    tbody.innerHTML = `<tr><td class="empty" colspan="4">Sin datos</td></tr>`;
    return;
  }

  tbody.innerHTML = Array.from({length:n}).map((_,i)=>{
    const ym = labels[i] ?? `#${i+1}`;
    return `
      <tr data-ym="${String(ym)}" style="cursor:pointer">
        <td>${String(ym)}</td>
        <td>${money(ingresos[i] || 0)}</td>
        <td>${num(timbres[i] || 0)}</td>
        <td>${num(clientes[i] || 0)}</td>
      </tr>
    `;
  }).join('');

  if (typeof onMonthClick === 'function') {
    tbody.querySelectorAll('tr[data-ym]').forEach(tr=>{
      tr.addEventListener('click', ()=> onMonthClick(tr.getAttribute('data-ym')));
    });
  }
}

export function renderClientsTable(data) {
  const tbody = document.querySelector('#tblClients tbody');
  if (!tbody) return;

  const list = Array.isArray(data?.top_clients) ? data.top_clients
             : Array.isArray(data?.clients) ? data.clients
             : Array.isArray(data?.rows_clients) ? data.rows_clients : [];

  if (!list.length) {
    tbody.innerHTML = `<tr><td class="empty" colspan="4">Sin datos</td></tr>`;
    return;
  }

  tbody.innerHTML = list.slice(0, 30).map(o=>{
    const name = o?.name || o?.razon_social || o?.cliente || '—';
    const plan = o?.plan || o?.tier || '—';
    const inc  = o?.income ?? o?.ingresos ?? o?.amount ?? 0;
    const st   = o?.stamps ?? o?.timbres ?? o?.cfdis ?? 0;

    return `
      <tr>
        <td>${String(name)}</td>
        <td>${String(plan)}</td>
        <td>${money(inc)}</td>
        <td>${num(st)}</td>
      </tr>
    `;
  }).join('');
}
