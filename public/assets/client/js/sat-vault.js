// public/assets/client/js/sat-vault.js
// PACTOPIA360 · SAT · Vault UI
// Limpia la vista Blade y mueve toda la lógica pesada al asset externo.

document.addEventListener('DOMContentLoaded', function () {
  if (window.__P360_SAT_VAULT__) return;
  window.__P360_SAT_VAULT__ = true;

  const boot = window.__VAULT_BOOT || { rows: [], totals: {} };
  const ROUTES = (window.P360_SAT && window.P360_SAT.routes) || {};

  const allRowsRaw = Array.isArray(boot.rows) ? boot.rows : [];
  const allRows = allRowsRaw.filter(function (r) {
    const kind = String((r && r.kind) || '').toLowerCase();
    const uuid = String((r && r.uuid) || '').trim();
    return kind === 'cfdi' && uuid !== '';
  });

  const tbody  = document.getElementById('vaultRows');
  if (!tbody) return;

  const tCnt   = document.getElementById('tCnt');
  const tSub   = document.getElementById('tSub');
  const tIva   = document.getElementById('tIva');
  const tTot   = document.getElementById('tTot');

  const pgInfo = document.getElementById('pgInfo');
  const pgPrev = document.getElementById('pgPrev');
  const pgNext = document.getElementById('pgNext');
  const pgSize = document.getElementById('pgSize');

  const fTipo  = document.getElementById('fTipo');
  const fDesde = document.getElementById('fDesde');
  const fHasta = document.getElementById('fHasta');
  const fRfc   = document.getElementById('fRfc');
  const fQuery = document.getElementById('fQuery');
  const fMin   = document.getElementById('fMin');
  const fMax   = document.getElementById('fMax');

  const btnApply      = document.getElementById('btnApply');
  const btnClear      = document.getElementById('btnClear');
  const btnExport     = document.getElementById('btnExportVault');
  const quickDateBtns = document.querySelectorAll('.vault-quick-date');

  const vmTotalCount     = document.getElementById('vmTotalCount');
  const vmTotalEmitidos  = document.getElementById('vmTotalEmitidos');
  const vmTotalRecibidos = document.getElementById('vmTotalRecibidos');
  const vmAvgTotal       = document.getElementById('vmAvgTotal');
  const vmTopRfc         = document.getElementById('vmTopRfc');
  const vmTopRfcTot      = document.getElementById('vmTopRfcTot');
  const vmBarEmitidos    = document.getElementById('vmBarEmitidos');
  const vmBarRecibidos   = document.getElementById('vmBarRecibidos');

  const vmTotGlobal      = document.getElementById('vmTotGlobal');
  const vmTotEmitidos    = document.getElementById('vmTotEmitidos');
  const vmTotRecibidos   = document.getElementById('vmTotRecibidos');

  const vqTopBody        = document.getElementById('vqTopRfcBody');

  const vaultRfcSelect   = document.getElementById('vaultRfcSelect');
  const vaultRfcManual   = document.getElementById('vaultRfcManual');

  function fmtMoney(n) {
    const num = Number(n || 0);
    return num.toLocaleString('es-MX', {
      style: 'currency',
      currency: 'MXN',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function fmtPct(p) {
    const num = Number(p || 0);
    return num.toFixed(2) + '%';
  }

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function parseDate(str) {
    if (!str) return null;
    const s = String(str).slice(0, 10);
    const t = Date.parse(s);
    return isNaN(t) ? null : new Date(t);
  }

  function getRowDate(row) {
    const cands = [row.fecha, row.fecha_emision, row.fecha_cfdi, row.fecha_timbrado, row.created_at];
    for (const v of cands) {
      const d = parseDate(v);
      if (d) return d;
    }
    return null;
  }

  function toNum(v) {
    if (v == null) return 0;
    if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
    const s = String(v).trim();
    if (s === '') return 0;
    const cleaned = s.replace(/[^0-9.\-]/g, '');
    const n = Number(cleaned);
    return Number.isFinite(n) ? n : 0;
  }

  function getRowTotal(row) {
    const disp = row.__display || {};
    if (disp.total != null) return toNum(disp.total);

    const fields = ['total', 'total_mxn', 'total_cfdi', 'importe', 'importe_mxn', 'monto', 'monto_total'];
    for (const f of fields) {
      if (row[f] != null && row[f] !== '') return toNum(row[f]);
    }
    return 0;
  }

  function getRfcAndRazon(row) {
    const rfc =
      row.rfc || row.rfc_emisor || row.rfc_receptor ||
      row.emisor_rfc || row.receptor_rfc || '';

    const razon =
      row.razon || row.razon_social || row.razon_emisor || row.razon_receptor ||
      row.nombre_emisor || row.nombre_receptor || '';

    return {
      rfc: String(rfc || '').toUpperCase(),
      razon: String(razon || '')
    };
  }

  function formatDateInput(d) {
    if (!(d instanceof Date)) return '';
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
  }

  function tdText(text, extraClass) {
    const td = document.createElement('td');
    if (extraClass) td.className = extraClass;
    td.textContent = text;
    return td;
  }

  function tdMoney(amount) {
    const td = document.createElement('td');
    td.className = 't-right';
    const span = document.createElement('span');
    span.className = 'vault-amount';
    span.textContent = fmtMoney(amount);
    td.appendChild(span);
    return td;
  }

  function tdPctCell(pct) {
    const td = document.createElement('td');
    td.className = 't-right';
    const span = document.createElement('span');
    span.className = 'vault-amount';
    span.textContent = fmtPct(pct);
    td.appendChild(span);
    return td;
  }

  const state = {
    page: 1,
    pageSize: Number(pgSize ? pgSize.value : 25) || 25,
    filtered: [],
    totals: { count: 0, sub: 0, iva: 0, tot: 0 }
  };

  function applyFilters() {
    const tipoVal = (fTipo && fTipo.value) ? fTipo.value.toLowerCase() : 'ambos';
    const rfcVal  = (fRfc && fRfc.value || '').trim().toUpperCase();
    const qVal    = (fQuery && fQuery.value || '').trim().toUpperCase();

    const minVal  = (fMin && fMin.value !== '') ? Number(fMin.value) : null;
    const maxVal  = (fMax && fMax.value !== '') ? Number(fMax.value) : null;

    const dDesde  = (fDesde && fDesde.value) ? new Date(fDesde.value) : null;
    const dHasta  = (fHasta && fHasta.value) ? new Date(fHasta.value) : null;
    if (dHasta) dHasta.setDate(dHasta.getDate() + 1);

    let count = 0;
    let sumSub = 0;
    let sumIva = 0;
    let sumTot = 0;
    const out = [];

    for (const r of allRows) {
      const kind = String(r.kind || '').toLowerCase();
      const uuid = String(r.uuid || '').trim();
      if (kind !== 'cfdi' || !uuid) continue;

      const tipoRow = String(r.tipo || '').toLowerCase();
      if (tipoVal !== 'ambos' && tipoRow !== tipoVal) continue;

      const fRow = getRowDate(r);
      if (dDesde && (!fRow || fRow < dDesde)) continue;
      if (dHasta && (!fRow || fRow >= dHasta)) continue;

      const info  = getRfcAndRazon(r);
      const rfc   = info.rfc;
      const razon = info.razon;

      if (rfcVal && rfc !== rfcVal) continue;

      if (qVal) {
        const hay = (rfc + ' ' + razon + ' ' + uuid).toUpperCase();
        if (!hay.includes(qVal)) continue;
      }

      let subtotal = toNum(r.subtotal ?? r.subtotal_mxn ?? 0);
      let iva      = toNum(r.iva ?? r.iva_mxn ?? 0);
      let total    = toNum(r.total ?? r.total_mxn ?? r.total_cfdi ?? r.importe ?? r.monto_total ?? 0);

      if (!Number.isFinite(subtotal) || subtotal < 0) subtotal = 0;
      if (!Number.isFinite(iva) || iva < 0) iva = 0;
      if (!Number.isFinite(total) || total < 0) total = 0;

      if (total <= 0 && (subtotal > 0 || iva > 0)) total = subtotal + iva;
      if (iva <= 0 && subtotal > 0 && total > subtotal) {
        const diff = total - subtotal;
        iva = diff > 0.00001 ? diff : 0;
      }
      if (subtotal <= 0 && total > 0 && iva > 0 && total >= iva) {
        const diff = total - iva;
        subtotal = diff > 0.00001 ? diff : 0;
      }

      let ivaPct = 0;
      if (subtotal > 0 && iva > 0) ivaPct = (iva / subtotal) * 100;

      if (minVal !== null && total < minVal) continue;
      if (maxVal !== null && total > maxVal) continue;

      count++;
      sumSub += subtotal;
      sumIva += iva;
      sumTot += total;

      r.__display = { subtotal, iva, total, rfc, razon, ivapct: ivaPct };
      out.push(r);
    }

    state.totals = { count: count, sub: sumSub, iva: sumIva, tot: sumTot };
    return out;
  }

  let chartTipo = null;
  let chartTopRfc = null;
  let chartFlujo = null;

  function ensureChart(canvas, type, config, prev) {
    if (!canvas || !window.Chart) return prev;
    if (prev && typeof prev.destroy === 'function') prev.destroy();
    return new Chart(canvas, {
      type: type,
      data: config.data,
      options: config.options || {}
    });
  }

  function updateMetricsAndCharts() {
    const rows = state.filtered || [];

    const totalCount = state.totals.count || 0;
    let totalEmitidos = 0;
    let totalRecibidos = 0;
    let countEmitidos = 0;
    let countRecibidos = 0;

    const byRfc = new Map();
    const byFecha = new Map();

    for (const r of rows) {
      const disp = r.__display || {};
      const total = disp.total != null ? toNum(disp.total) : toNum(getRowTotal(r));
      const tipo = String(r.tipo || '').toLowerCase();

      const info = disp.rfc ? { rfc: disp.rfc, razon: disp.razon } : getRfcAndRazon(r);
      const rfc = info.rfc || '—';
      const razon = (info.razon || '').trim() || '—';

      if (tipo === 'emitidos') {
        totalEmitidos += total;
        countEmitidos++;
      } else if (tipo === 'recibidos') {
        totalRecibidos += total;
        countRecibidos++;
      }

      if (rfc && rfc !== '—') {
        const prev = byRfc.get(rfc) || { rfc: rfc, razon: razon, cnt: 0, total: 0 };
        prev.cnt += 1;
        prev.total += total;
        if (!prev.razon && razon) prev.razon = razon;
        byRfc.set(rfc, prev);
      }

      const d = getRowDate(r);
      if (d) {
        const key = d.toISOString().slice(0, 10);
        const prevF = byFecha.get(key) || { fecha: key, total: 0 };
        prevF.total += total;
        byFecha.set(key, prevF);
      }
    }

    if (vmTotalCount) vmTotalCount.textContent = totalCount;
    if (vmTotalEmitidos) vmTotalEmitidos.textContent = countEmitidos;
    if (vmTotalRecibidos) vmTotalRecibidos.textContent = countRecibidos;

    const totGlobal = state.totals.tot || 0;
    if (vmTotGlobal) vmTotGlobal.textContent = fmtMoney(totGlobal);
    if (vmTotEmitidos) vmTotEmitidos.textContent = fmtMoney(totalEmitidos);
    if (vmTotRecibidos) vmTotRecibidos.textContent = fmtMoney(totalRecibidos);

    const avg = totalCount ? (totGlobal / totalCount) : 0;
    if (vmAvgTotal) vmAvgTotal.textContent = fmtMoney(avg);

    let topRfc = '—';
    let topTotal = 0;

    byRfc.forEach(function (v) {
      if (v.total > topTotal) {
        topTotal = v.total;
        topRfc = v.rfc;
      }
    });

    if (vmTopRfc) vmTopRfc.textContent = topRfc || '—';
    if (vmTopRfcTot) vmTopRfcTot.textContent = fmtMoney(topTotal);

    const sumaTipos = totalEmitidos + totalRecibidos;
    const pctEm = sumaTipos > 0 ? (totalEmitidos / sumaTipos) * 100 : 0;
    const pctRec = sumaTipos > 0 ? (totalRecibidos / sumaTipos) * 100 : 0;

    if (vmBarEmitidos) vmBarEmitidos.style.width = pctEm.toFixed(1) + '%';
    if (vmBarRecibidos) vmBarRecibidos.style.width = pctRec.toFixed(1) + '%';

    setText('vqCount', String(totalCount));
    setText('vqTotal', fmtMoney(totGlobal));
    setText('vqSubTotal', fmtMoney(state.totals.sub || 0));
    setText('vqIvaTotal', fmtMoney(state.totals.iva || 0));

    setText('vqEmitCount', String(countEmitidos));
    setText('vqEmitTotal', fmtMoney(totalEmitidos));

    setText('vqRecCount', String(countRecibidos));
    setText('vqRecTotal', fmtMoney(totalRecibidos));

    if (vqTopBody) {
      vqTopBody.innerHTML = '';
      const topList = Array.from(byRfc.values())
        .filter(function (v) { return v.rfc && v.rfc !== '—'; })
        .sort(function (a, b) { return b.total - a.total; })
        .slice(0, 5);

      if (!topList.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="4" class="vq-empty">Sin datos suficientes todavía…</td>';
        vqTopBody.appendChild(tr);
      } else {
        topList.forEach(function (row) {
          const tr = document.createElement('tr');
          tr.innerHTML =
            '<td>' + row.rfc + '</td>' +
            '<td>' + (row.razon || '—') + '</td>' +
            '<td class="t-center">' + String(row.cnt || 0) + '</td>' +
            '<td class="t-right">' + fmtMoney(row.total || 0) + '</td>';
          vqTopBody.appendChild(tr);
        });
      }
    }

    const cTipo = document.getElementById('vaultChartTipo');
    const cTop  = document.getElementById('vaultChartTopRfc');
    const cFlu  = document.getElementById('vaultChartFlujo');

    chartTipo = ensureChart(cTipo, 'doughnut', {
      data: {
        labels: ['Emitidos', 'Recibidos'],
        datasets: [{ data: [totalEmitidos, totalRecibidos] }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) { return fmtMoney(ctx.parsed); }
            }
          }
        },
        cutout: '60%'
      }
    }, chartTipo);

    const rfcArr = Array.from(byRfc.values())
      .filter(function (v) { return v.rfc && v.rfc !== '—'; })
      .sort(function (a, b) { return b.total - a.total; })
      .slice(0, 5);

    chartTopRfc = ensureChart(cTop, 'bar', {
      data: {
        labels: rfcArr.map(function (v) { return v.rfc; }),
        datasets: [{ data: rfcArr.map(function (v) { return v.total; }) }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) { return fmtMoney(ctx.parsed.x); }
            }
          }
        },
        scales: {
          x: {
            ticks: {
              callback: function (v) { return fmtMoney(v); }
            }
          }
        }
      }
    }, chartTopRfc);

    const fechasArr = Array.from(byFecha.values()).sort(function (a, b) {
      return a.fecha < b.fecha ? -1 : 1;
    });

    chartFlujo = ensureChart(cFlu, 'line', {
      data: {
        labels: fechasArr.map(function (v) { return v.fecha; }),
        datasets: [{
          data: fechasArr.map(function (v) { return v.total; }),
          fill: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) { return fmtMoney(ctx.parsed.y); }
            }
          }
        },
        scales: {
          y: {
            ticks: {
              callback: function (v) { return fmtMoney(v); }
            }
          }
        }
      }
    }, chartFlujo);
  }

  function render() {
    state.filtered = applyFilters();

    const totalRows  = state.filtered.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / state.pageSize));
    if (state.page > totalPages) state.page = totalPages;

    const startIndex = (state.page - 1) * state.pageSize;
    const endIndex   = Math.min(startIndex + state.pageSize, totalRows);
    const slice      = state.filtered.slice(startIndex, endIndex);

    tbody.innerHTML = '';

    if (!slice.length) {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="10" class="empty-cell">Sin datos</td>';
      tbody.appendChild(tr);
    } else {
      slice.forEach(function (r) {
        const tr = document.createElement('tr');

        const d = getRowDate(r);
        const fTxt = d ? d.toISOString().slice(0, 10) : (r.fecha || r.fecha_emision || '');
        const tipo = (r.tipo || '').toString().toUpperCase();

        const info = getRfcAndRazon(r);
        const disp = r.__display || {};

        const rfc = (disp.rfc || info.rfc || '—').toString().toUpperCase();
        const razon = (disp.razon || info.razon || '—').toString();

        tr.appendChild(tdText(fTxt || '—'));
        tr.appendChild(tdText(tipo || '—'));
        tr.appendChild(tdText(rfc || '—'));
        tr.appendChild(tdText(razon || '—'));
        tr.appendChild(tdText(r.uuid || '—'));

        let subR = (disp.subtotal != null) ? toNum(disp.subtotal) : toNum(r.subtotal ?? r.subtotal_mxn ?? 0);
        let ivaR = (disp.iva != null) ? toNum(disp.iva) : toNum(r.iva ?? r.iva_mxn ?? 0);
        let totR = (disp.total != null) ? toNum(disp.total) : toNum(getRowTotal(r));

        if (!Number.isFinite(subR) || subR < 0) subR = 0;
        if (!Number.isFinite(ivaR) || ivaR < 0) ivaR = 0;
        if (!Number.isFinite(totR) || totR < 0) totR = 0;

        if (totR <= 0 && (subR > 0 || ivaR > 0)) totR = subR + ivaR;

        if (ivaR <= 0 && subR > 0 && totR > 0 && totR >= subR) {
          const diff = totR - subR;
          ivaR = diff > 0.00001 ? diff : 0;
        }

        if (subR <= 0 && totR > 0 && ivaR > 0 && totR >= ivaR) {
          const diff = totR - ivaR;
          subR = diff > 0.00001 ? diff : 0;
        }

        let pctR = 0;
        if (subR > 0 && ivaR > 0) {
          pctR = (ivaR / subR) * 100;
          if (!Number.isFinite(pctR) || pctR < 0) pctR = 0;
          if (pctR > 200) pctR = 200;
        }

        r.__display = {
          subtotal: subR,
          iva: ivaR,
          total: totR,
          rfc: rfc,
          razon: razon,
          ivapct: pctR
        };

        tr.appendChild(tdMoney(subR));
        tr.appendChild(tdMoney(ivaR));
        tr.appendChild(tdPctCell(pctR));
        tr.appendChild(tdMoney(totR));

        const tdAcc = document.createElement('td');
        tdAcc.className = 't-actions';

        const uuid = r.uuid || '';

        function makeIconBtn(label, title, act) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'icon-btn';
          btn.title = title;
          btn.dataset.act = act;
          btn.dataset.uuid = uuid;
          btn.textContent = label;
          return btn;
        }

        if (uuid) {
          tdAcc.appendChild(makeIconBtn('XML', 'Descargar XML', 'xml'));
          tdAcc.appendChild(makeIconBtn('PDF', 'Descargar PDF', 'pdf'));
          tdAcc.appendChild(makeIconBtn('ZIP', 'Descargar ZIP', 'zip'));
        }

        tr.appendChild(tdAcc);
        tbody.appendChild(tr);
      });
    }

    const totals = state.totals;
    if (tCnt) tCnt.textContent = totals.count || 0;
    if (tSub) tSub.textContent = fmtMoney(totals.sub || 0);
    if (tIva) tIva.textContent = fmtMoney(totals.iva || 0);
    if (tTot) tTot.textContent = fmtMoney(totals.tot || 0);

    if (pgInfo) {
      const from = totalRows ? (startIndex + 1) : 0;
      const to = endIndex;
      const txt = from + '–' + to + ' de ' + totalRows;
      pgInfo.textContent = txt;
      const pgInfoMirror = document.getElementById('pgInfoMirror');
      if (pgInfoMirror) pgInfoMirror.textContent = txt;
    }

    if (pgPrev) pgPrev.disabled = (state.page <= 1);
    if (pgNext) pgNext.disabled = (state.page >= totalPages);

    updateMetricsAndCharts();
  }

  if (pgPrev) {
    pgPrev.addEventListener('click', function () {
      if (state.page > 1) {
        state.page--;
        render();
      }
    });
  }

  if (pgNext) {
    pgNext.addEventListener('click', function () {
      state.page++;
      render();
    });
  }

  if (pgSize) {
    pgSize.addEventListener('change', function () {
      state.pageSize = Number(pgSize.value || 25) || 25;
      state.page = 1;
      render();
    });
  }

  if (btnApply) {
    btnApply.addEventListener('click', function () {
      state.page = 1;
      render();
    });
  }

  if (btnClear) {
    btnClear.addEventListener('click', function () {
      if (fTipo)  fTipo.value  = 'ambos';
      if (fRfc)   fRfc.value   = '';
      if (fQuery) fQuery.value = '';
      if (fDesde) fDesde.value = '';
      if (fHasta) fHasta.value = '';
      if (fMin)   fMin.value   = '';
      if (fMax)   fMax.value   = '';
      state.page = 1;
      render();
    });
  }

  if (quickDateBtns && quickDateBtns.length) {
    quickDateBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        const range = btn.dataset.range;
        const today = new Date();
        let d1 = null;
        let d2 = null;

        if (range === 'today') {
          d1 = new Date(today);
          d2 = new Date(today);
        } else if (range === 'week') {
          const dow = today.getDay() || 7;
          d2 = new Date(today);
          d1 = new Date(today);
          d1.setDate(d1.getDate() - (dow - 1));
        } else if (range === 'month') {
          d1 = new Date(today.getFullYear(), today.getMonth(), 1);
          d2 = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        } else if (range === 'year') {
          d1 = new Date(today.getFullYear(), 0, 1);
          d2 = new Date(today.getFullYear(), 11, 31);
        } else if (range === 'all') {
          d1 = null;
          d2 = null;
        }

        if (fDesde) fDesde.value = d1 ? formatDateInput(d1) : '';
        if (fHasta) fHasta.value = d2 ? formatDateInput(d2) : '';

        state.page = 1;
        render();
      });
    });
  }

  if (btnExport) {
    if (!ROUTES.vaultExport) {
      btnExport.disabled = true;
    } else {
      btnExport.addEventListener('click', function () {
        const params = new URLSearchParams();

        if (fTipo && fTipo.value && fTipo.value !== 'ambos') params.set('tipo', fTipo.value);
        if (fRfc && fRfc.value) params.set('rfc', fRfc.value);
        if (fQuery && fQuery.value) params.set('q', fQuery.value);
        if (fDesde && fDesde.value) params.set('desde', fDesde.value);
        if (fHasta && fHasta.value) params.set('hasta', fHasta.value);
        if (fMin && fMin.value) params.set('min', fMin.value);
        if (fMax && fMax.value) params.set('max', fMax.value);

        const base = ROUTES.vaultExport || '';
        const sep  = base.includes('?') ? '&' : '?';
        window.location.href = base + sep + params.toString();
      });
    }
  }

  tbody.addEventListener('click', function (ev) {
    const btn = ev.target.closest('button.icon-btn');
    if (!btn) return;

    const act = btn.dataset.act;
    const uuid = btn.dataset.uuid;
    if (!uuid || !act) return;

    let base = null;
    if (act === 'xml') base = ROUTES.vaultXml;
    else if (act === 'pdf') base = ROUTES.vaultPdf;
    else if (act === 'zip') base = ROUTES.vaultZip;

    if (!base) return;

    const sep = base.includes('?') ? '&' : '?';
    const url = base + sep + 'uuid=' + encodeURIComponent(uuid);
    window.open(url, '_blank');
  });

  if (vaultRfcSelect && vaultRfcManual) {
    vaultRfcSelect.addEventListener('change', function () {
      if (vaultRfcSelect.value) {
        vaultRfcManual.value = vaultRfcSelect.value;
      }
    });

    vaultRfcManual.addEventListener('input', function () {
      vaultRfcManual.value = String(vaultRfcManual.value || '').toUpperCase().trim();
    });
  }

  render();
});