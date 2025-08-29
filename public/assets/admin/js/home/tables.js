// tables.js
import { $, fmtInt, fmtCur, escapeHTML, debounce, csv } from './helpers.js';

let incomeRows = [], sortBy = 'mes', sortDir = 'desc';

export function renderIncomeTable(data, openIncomeModal){
  const searchIncome = $('#searchIncome');
  incomeRows = (data?.ingresosTable || []).map(r => ({
    mes: r.label,
    ym: r.ym,
    total: Number(r.total || 0),
    pagos: Number(r.pagos || 0),
    avg: Number(r.avg || 0)
  }));

  const tbody = $('#incomeTbody');
  const empty = $('#incomeEmpty');
  const table = $('#incomeTable');

  function updateAriaSort(){
    if (!table) return;
    table.querySelectorAll('th[data-sort]')?.forEach(th => {
      const col = th.getAttribute('data-sort');
      th.setAttribute('aria-sort',
        col === sortBy ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'
      );
    });
  }

  function draw(){
    if (!tbody) return;

    const q = (searchIncome?.value || '').trim().toLowerCase();
    let rows = incomeRows.filter(r => !q || r.mes.toLowerCase().includes(q));

    rows.sort((a,b) => {
      const s = sortDir === 'asc' ? 1 : -1;
      if (['total','pagos','avg'].includes(sortBy)) return (a[sortBy] - b[sortBy]) * s;
      // por fecha (YYYY-MM) usando ym
      return (a.ym > b.ym ? 1 : -1) * s;
    });

    tbody.innerHTML = '';
    if (!rows.length){
      empty?.classList.remove('hidden');
    } else {
      empty?.classList.add('hidden');
      for (const r of rows){
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>
            <button class="link" type="button" data-ym="${r.ym}" title="Ver pagos del mes ${escapeHTML(r.mes)}">
              ${escapeHTML(r.mes)}
            </button>
          </td>
          <td>${fmtCur.format(r.total)}</td>
          <td>${fmtInt.format(r.pagos)}</td>
          <td>${fmtCur.format(r.avg)}</td>
        `;
        tbody.appendChild(tr);
      }
    }

    // Totales (globales): ticket promedio = SUM(total) / SUM(pagos)
    const sum = rows.reduce((a,r) => { a.total += r.total; a.pagos += r.pagos; return a; }, { total:0, pagos:0 });
    const avgTicket = sum.total / Math.max(1, sum.pagos);

    const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    setText('incomeSum',   fmtCur.format(sum.total));
    setText('incomeCount', fmtInt.format(sum.pagos));
    setText('incomeAvg',   fmtCur.format(avgTicket));

    // Click → modal
    tbody.querySelectorAll('button.link[data-ym]')?.forEach(b => {
      b.addEventListener('click', () => openIncomeModal(b.getAttribute('data-ym')));
    });

    updateAriaSort();
  }

  draw();

  // Sort headers (bind once)
  const ths = table?.querySelectorAll('th[data-sort]');
  ths?.forEach(th => {
    if (th.dataset.bound) return;
    th.dataset.bound = '1';
    th.style.cursor = 'pointer';
    th.addEventListener('click', () => {
      const col = th.getAttribute('data-sort');
      sortDir = (sortBy === col && sortDir === 'desc') ? 'asc' : 'desc';
      sortBy = col;
      draw();
    });
  });

  // Search (bind once, con debounce)
  if (searchIncome && !searchIncome.dataset.bound){
    searchIncome.dataset.bound = '1';
    searchIncome.addEventListener('input', debounce(draw, 120));
  }

  // Export mensual (usa helper con BOM)
  const btnExportIncome = document.getElementById('btnExportIncome');
  if (btnExportIncome && !btnExportIncome.dataset.bound){
    btnExportIncome.dataset.bound = '1';
    btnExportIncome.addEventListener('click', () => {
      import('./helpers.js').then(h => h.exportIncomeCSV(data?.ingresosTable || []));
    });
  }
}

export function renderClientsTable(data){
  const allRows = Array.isArray(data?.clientes) ? data.clientes : [];
  const tbody   = document.getElementById('clientsTbody');
  const empty   = document.getElementById('clientsEmptyRow');
  const inp     = document.getElementById('searchClients');

  if (!tbody) return;

  function paint(rows){
    tbody.innerHTML = '';
    if (!rows.length){ empty?.classList.remove('hidden'); return; }
    empty?.classList.add('hidden');
    let i = 1;
    for (const r of rows){
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${i++}</td>
        <td>${escapeHTML(r.empresa || '')}</td>
        <td>${escapeHTML(r.rfc || '')}</td>
        <td>${fmtInt.format(r.timbres || 0)}</td>
        <td>${escapeHTML(r.ultimo || '')}</td>
        <td>${escapeHTML(r.estado || '')}</td>
      `;
      tbody.appendChild(tr);
    }
  }

  paint(allRows);

  // Búsqueda (bind una vez)
  if (inp && !inp.dataset.bound){
    inp.dataset.bound = '1';
    const doFilter = () => {
      const q = (inp.value || '').toLowerCase();
      const filtered = allRows.filter(r =>
        (r.empresa || '').toLowerCase().includes(q) ||
        (r.rfc || '').toLowerCase().includes(q) ||
        (r.estado || '').toLowerCase().includes(q)
      );
      paint(filtered);
    };
    inp.addEventListener('input', debounce(doFilter, 120));
  }

  // Exportar clientes (CSV con BOM) — bind una vez
  const btnExport = document.getElementById('btnExport');
  if (btnExport && !btnExport.dataset.bound){
    btnExport.dataset.bound = '1';
    btnExport.addEventListener('click', () => {
      const header = ['#','Empresa','RFC','Timbres','Última actividad','Estado'];
      const lines  = [header.join(',')];
      let i = 1;
      for (const r of allRows){
        lines.push([ i++,
          csv(r.empresa || ''), csv(r.rfc || ''), (r.timbres || 0),
          csv(r.ultimo || ''),  csv(r.estado || '')
        ].join(','));
      }
      // CSV con BOM (para Excel)
      const CSV_BOM = '\uFEFF';
      const blob = new Blob([CSV_BOM + lines.join('\n')], { type:'text/csv;charset=utf-8;' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'clientes_activos.csv';
      a.click();
      URL.revokeObjectURL(a.href);
    });
  }
}
