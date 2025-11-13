@extends('layouts.cliente')
@section('title','SAT · Bóveda Fiscal')

@php
  // Datos base disponibles
  $credList = $credList ?? [];
  // Sugerencia de RFCs para filtros
  $rfcs = collect($credList)->map(fn($c)=>strtoupper($c['rfc'] ?? $c->rfc ?? ''))->filter()->unique()->values()->all();
@endphp

@push('styles')
<style>
/* ===========================================================
   PACTOPIA360 · BÓVEDA FISCAL v2 (limpia, con aire, dark-ready)
   =========================================================== */
.vault{--ink:#0f172a;--mut:#6b7280;--bd:#e5e7eb;--card:#fff;--sub:#fff8fa;--rose:#E11D48}
html[data-theme="dark"] .vault{--ink:#e5e7eb;--mut:#a5adbb;--bd:#2b2f36;--card:#0b1220;--sub:#191e2c}

.vault{display:grid;gap:16px;padding:8px 0}
.v-card{background:var(--card);border:1px solid var(--bd);border-radius:16px;padding:16px 18px;box-shadow:0 3px 12px rgba(0,0,0,.02)}
.v-title{margin:0;font:900 20px/1.1 'Poppins',system-ui;color:var(--ink);display:flex;align-items:center;gap:10px}
.v-title .ico{width:20px;height:20px;color:var(--rose)}
.v-actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:10px;font:800 12px/1 'Poppins';border:1px solid var(--bd);background:var(--card);cursor:pointer}
.btn:hover{filter:brightness(.97)}
.btn.primary{background:linear-gradient(90deg,#E11D48,#BE123C);color:#fff;border:0}

/* Etiquetas flotantes (tooltips ligeros) */
[data-tip]{position:relative}
[data-tip]:hover::after{
  content:attr(data-tip);position:absolute;bottom:120%;left:50%;transform:translateX(-50%);
  background:var(--ink);color:#fff;padding:4px 8px;font-size:11px;border-radius:6px;white-space:nowrap;z-index:10
}

/* Filtros */
.v-filters{display:flex;flex-wrap:wrap;gap:8px}
.inp{border:1px solid var(--bd);border-radius:10px;padding:8px 10px;font-weight:700;background:var(--card);min-width:120px;color:var(--ink)}
.inp[type="number"]{width:120px}
.v-quick{display:flex;gap:6px;align-items:center}

/* Totales */
.v-totals{display:flex;gap:10px;flex-wrap:wrap}
.v-pill{border:1px solid var(--bd);border-radius:999px;padding:6px 10px;font-weight:800;color:var(--ink);display:flex;gap:6px;align-items:center}
.v-pill b{font-weight:900}

/* Tabla */
.table-wrap{overflow:auto;border:1px solid var(--bd);border-radius:14px}
.table{width:100%;border-collapse:collapse;font-size:13px}
.table th{position:sticky;top:0;background:#fff0f3;color:#6b7280;font-weight:900;text-transform:uppercase;padding:10px;text-align:left;z-index:1}
.table td{padding:10px;border-top:1px solid var(--bd)}
.table tr:hover td{background:#fffafc}
.tag{border:1px solid var(--bd);border-radius:999px;padding:3px 8px;font-weight:800;font-size:11px}

/* Paginación */
.v-pag{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;margin-top:10px}
.v-pag .left, .v-pag .right{display:flex;gap:8px;align-items:center}
.v-pag .btn{padding:6px 10px}

/* Cols align */
.t-right{text-align:right}
.mono{font-family:ui-monospace,Menlo,Consolas,monospace}
</style>
@endpush

@section('content')
<div class="vault" id="vaultApp">

  <!-- Header -->
  <div class="v-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
      <h1 class="v-title">
        <svg class="ico" viewBox="0 0 24 24" stroke="currentColor" fill="none"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/></svg>
        Bóveda Fiscal
      </h1>
      <div class="v-actions">
        <button class="btn" id="btnCsv" data-tip="Exportar CSV">📄 CSV</button>
        <button class="btn" id="btnXls" data-tip="Exportar Excel (CSV)">📊 XLSX</button>
        <button class="btn primary" id="btnReload" data-tip="Recargar datos">⟳ Actualizar</button>
      </div>
    </div>
  </div>

  <!-- Filtros -->
  <div class="v-card">
    <div class="v-filters">
      <select class="inp" id="fTipo" aria-label="Tipo">
        <option value="ambos">Ambos</option>
        <option value="emitidos">Emitidos</option>
        <option value="recibidos">Recibidos</option>
      </select>

      <input type="date" class="inp" id="fDesde" aria-label="Desde">
      <input type="date" class="inp" id="fHasta" aria-label="Hasta">

      <select class="inp" id="fRfc" aria-label="RFC">
        <option value="">Todos los RFC</option>
        @foreach($rfcs as $r)
          <option value="{{ $r }}">{{ $r }}</option>
        @endforeach
      </select>

      <input type="text" class="inp" id="fQuery" placeholder="RFC / Razón / UUID" aria-label="Buscar">

      <input type="number" class="inp" id="fMin" placeholder="Mín $" step="0.01" min="0" aria-label="Mínimo">
      <input type="number" class="inp" id="fMax" placeholder="Máx $" step="0.01" min="0" aria-label="Máximo">

      <div class="v-quick">
        <button class="btn" id="btnApply" data-tip="Aplicar filtros">🔎 Filtrar</button>
        <button class="btn" id="btnClear" data-tip="Limpiar">🧹 Limpiar</button>
      </div>
    </div>
  </div>

  <!-- Totales -->
  <div class="v-card">
    <div class="v-totals">
      <div class="v-pill">CFDI <b id="tCnt">0</b></div>
      <div class="v-pill">Subtotal <b id="tSub">$0.00</b></div>
      <div class="v-pill">IVA <b id="tIva">$0.00</b></div>
      <div class="v-pill">Total <b id="tTot">$0.00</b></div>
    </div>
  </div>

  <!-- Tabla -->
  <div class="v-card">
    <div class="table-wrap">
      <table class="table" id="vaultTable" aria-label="CFDI descargados">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>RFC</th>
            <th>Razón</th>
            <th>UUID</th>
            <th class="t-right">Subtotal</th>
            <th class="t-right">IVA</th>
            <th class="t-right">Total</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="vaultRows">
          <tr><td colspan="9" style="text-align:center;color:var(--mut);font-weight:700">Sin datos</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div class="v-pag">
      <div class="left">
        <button class="btn" id="pgPrev">⟵ Anterior</button>
        <button class="btn" id="pgNext">Siguiente ⟶</button>
      </div>
      <div class="right">
        <span id="pgInfo" class="mono" style="color:var(--mut)">0–0 de 0</span>
        <select class="inp" id="pgSize" aria-label="Resultados por página">
          <option>10</option>
          <option selected>25</option>
          <option>50</option>
          <option>100</option>
        </select>
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
/**
 * Bóveda Fiscal – Front pager + filtros + totales + export
 * - Trabaja con window.__VAULT_BOOT (inyectado por el controlador)
 * - Sin rutas nuevas; si después publicas un endpoint, activa fetchVaultData()
 */
(function(){
  const $ = s => document.querySelector(s);
  const on = (el,ev,fn)=> el && el.addEventListener(ev,fn);

  const state = {
    items: [],       // {fecha, tipo: 'emitidos|recibidos', rfc, razon, uuid, subtotal, iva, total}
    filtered: [],
    page: 1,
    size: 25,
  };

  // ====== Helpers ======
  const nf  = new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'});
  const fmt = n => nf.format(+n||0);
  const parseNum = v => { const n = parseFloat((v??'').toString().replace(/[, ]/g,'')); return isNaN(n)?0:n; };
  const esc = s => (s??'').toString().replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));

  // ====== Boot data (si el servidor inyecta window.__VAULT_BOOT) ======
  if (window.__VAULT_BOOT && Array.isArray(window.__VAULT_BOOT)) {
    state.items = window.__VAULT_BOOT.map(normalizeRow);
  } else {
    state.items = []; // sin mock para no confundir al usuario
  }

  function normalizeRow(r){
    return {
      fecha: (r.fecha ?? r.date ?? '').toString().slice(0,10),
      tipo:  (r.tipo  ?? r.type ?? '').toString().toLowerCase(), // emitidos | recibidos
      rfc:   (r.rfc   ?? '').toString().toUpperCase(),
      razon: (r.razon ?? r.razon_social ?? r.name ?? '—').toString(),
      uuid:  (r.uuid  ?? r.folio ?? '—').toString().toUpperCase(),
      subtotal: parseNum(r.subtotal ?? r.sub ?? 0),
      iva:      parseNum(r.iva ?? r.impuestos ?? 0),
      total:    parseNum(r.total ?? (parseNum(r.subtotal ?? 0) + parseNum(r.iva ?? 0))),
    };
  }

  // ====== Referencias UI (todas opcionales) ======
  const fTipo  = $('#fTipo');
  const fDesde = $('#fDesde');
  const fHasta = $('#fHasta');
  const fRfc   = $('#fRfc');
  const fQ     = $('#fQuery');
  const fMin   = $('#fMin');
  const fMax   = $('#fMax');

  const tCnt = $('#tCnt'), tSub = $('#tSub'), tIva = $('#tIva'), tTot = $('#tTot');

  const rowsEl = $('#vaultRows');
  const pgPrev = $('#pgPrev'), pgNext = $('#pgNext'), pgInfo = $('#pgInfo'), pgSize = $('#pgSize');

  const btnApply  = $('#btnApply');
  const btnClear  = $('#btnClear');
  const btnReload = $('#btnReload');
  const btnCsv    = $('#btnCsv');
  const btnXls    = $('#btnXls');

  // ====== Wire-up ======
  on(btnApply,  'click', applyFilters);
  on(btnClear,  'click', clearFilters);
  on(btnReload, 'click', reloadData);
  on(btnCsv,    'click', exportCsv);
  on(btnXls,    'click', exportCsv); // Excel abrirá el CSV

  on(pgPrev, 'click', ()=>{ if(state.page>1){ state.page--; render(); }});
  on(pgNext, 'click', ()=>{
    const pages = Math.max(1, Math.ceil(state.filtered.length / state.size));
    if(state.page < pages){ state.page++; render(); }
  });
  on(pgSize, 'change', ()=>{
    state.size = parseInt(pgSize.value,10)||25;
    state.page = 1;
    render();
  });

  // ====== Core ======
  function applyFilters(){
    const tipo = (fTipo?.value || 'ambos').toLowerCase();
    const d1   = fDesde?.value ? new Date(fDesde.value) : null;
    const d2   = fHasta?.value ? new Date(fHasta.value) : null;
    const rfc  = (fRfc?.value || '').toUpperCase().trim();
    const q    = (fQ?.value   || '').toLowerCase().trim();
    const n1   = parseNum(fMin?.value ?? '');
    const n2   = parseNum(fMax?.value ?? '');

    state.filtered = state.items.filter(it=>{
      if (tipo!=='ambos' && it.tipo!==tipo) return false;
      if (rfc && it.rfc!==rfc) return false;

      if (d1){ const dt = new Date(it.fecha); if (isNaN(+dt) || dt < d1) return false; }
      if (d2){ const dt = new Date(it.fecha); if (isNaN(+dt) || dt > d2) return false; }

      if (q){
        const hay = (it.rfc+' '+it.razon+' '+it.uuid).toLowerCase();
        if (!hay.includes(q)) return false;
      }
      const tot = it.total;
      if (fMin && fMin.value!=='' && tot < n1) return false;
      if (fMax && fMax.value!=='' && tot > n2) return false;

      return true;
    });

    state.page = 1;
    render();
  }

  function clearFilters(){
    if (fTipo)  fTipo.value  = 'ambos';
    if (fDesde) fDesde.value = '';
    if (fHasta) fHasta.value = '';
    if (fRfc)   fRfc.value   = '';
    if (fQ)     fQ.value     = '';
    if (fMin)   fMin.value   = '';
    if (fMax)   fMax.value   = '';

    state.filtered = state.items.slice();
    state.page = 1;
    render();
  }

  function render(){
    // Asegura colección activa
    if (!Array.isArray(state.filtered) || state.filtered.length===0){
      state.filtered = state.items.slice();
    }

    // Totales (sobre filtrados)
    const totCnt = state.filtered.length;
    const totSub = state.filtered.reduce((a,b)=>a+b.subtotal,0);
    const totIva = state.filtered.reduce((a,b)=>a+b.iva,0);
    const totTot = state.filtered.reduce((a,b)=>a+b.total,0);
    if (tCnt) tCnt.textContent = totCnt;
    if (tSub) tSub.textContent = fmt(totSub);
    if (tIva) tIva.textContent = fmt(totIva);
    if (tTot) tTot.textContent = fmt(totTot);

    // Paginación
    const pages = Math.max(1, Math.ceil(totCnt / state.size));
    if (state.page>pages) state.page = pages;
    const start = (state.page-1)*state.size;
    const slice = state.filtered.slice(start, start+state.size);

    // Tabla
    if (rowsEl){
      if (slice.length===0){
        rowsEl.innerHTML = `<tr><td colspan="9" style="text-align:center;color:var(--mut);font-weight:700">Sin datos</td></tr>`;
      } else {
        rowsEl.innerHTML = slice.map(r=>`
          <tr>
            <td>${esc(r.fecha)}</td>
            <td><span class="tag">${r.tipo==='emitidos'?'Emitidos':'Recibidos'}</span></td>
            <td class="mono">${esc(r.rfc)}</td>
            <td>${esc(r.razon)}</td>
            <td class="mono">${esc(r.uuid)}</td>
            <td class="t-right">${fmt(r.subtotal)}</td>
            <td class="t-right">${fmt(r.iva)}</td>
            <td class="t-right">${fmt(r.total)}</td>
            <td>
              <button class="btn" title="XML">🧾</button>
              <button class="btn" title="PDF">📄</button>
              <button class="btn" title="Descargar ZIP">⬇️</button>
            </td>
          </tr>
        `).join('');
      }
    }

    // Info paginación
    if (pgInfo){
      const a = totCnt===0 ? 0 : start+1;
      const b = totCnt===0 ? 0 : Math.min(start+state.size, totCnt);
      pgInfo.textContent = `${a}–${b} de ${totCnt}`;
    }
  }

  // ====== Export CSV del resultado filtrado ======
  function exportCsv(){
    const cols = ['Fecha','Tipo','RFC','Razón','UUID','Subtotal','IVA','Total'];
    const lines = [cols.join(',')];

    const arr = state.filtered && state.filtered.length ? state.filtered : state.items;
    arr.forEach(r=>{
      const row = [
        r.fecha,
        r.tipo,
        r.rfc,
        (r.razon||'').replaceAll(',',' '),
        r.uuid,
        r.subtotal,
        r.iva,
        r.total
      ];
      lines.push(row.join(','));
    });

    const blob = new Blob([lines.join('\n')], {type:'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = `vault_export_${Date.now()}.csv`;
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  }

  // ====== (Opcional) Carga desde endpoint cuando lo publiques ======
  async function fetchVaultData(){
    // Ejemplo:
    // const res = await fetch('{{ Route::has("cliente.sat.vault.api") ? route("cliente.sat.vault.api") : "" }}', {headers:{'X-Requested-With':'XMLHttpRequest'}});
    // if(!res.ok) throw new Error('HTTP '+res.status);
    // const json = await res.json();
    // state.items = Array.isArray(json?.data) ? json.data.map(normalizeRow) : [];
  }

  async function reloadData(){
    try{
      // await fetchVaultData(); // activa cuando tengas endpoint
      applyFilters();
    }catch(e){
      console.error(e);
      alert('No fue posible recargar la bóveda.');
    }
  }

  // ====== Init ======
  state.size = parseInt(pgSize?.value ?? '25',10) || 25;
  render();

})();
</script>
@endpush
