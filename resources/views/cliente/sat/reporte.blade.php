{{-- resources/views/cliente/sat/reporte.blade.php (v4.1) --}}
@extends('layouts.cliente')
@section('title','SAT · Reporte contable')

@push('styles')
<style>
  .rep-wrap{font-family:'Poppins',system-ui,sans-serif;--rose:#E11D48;--rose-dark:#BE123C;--mut:#6b7280;--card:#fff;--border:#f3d5dc;color:#0f172a;display:grid;gap:20px;max-width:1000px;margin:auto;padding:20px}
  html[data-theme="dark"] .rep-wrap{--card:#0b1220;--border:#2b2f36;--mut:#a5adbb;color:#e5e7eb}
  .card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:22px 24px}
  .card .hd{margin-bottom:16px}
  .card .hd h3{margin:0;font-weight:900;font-size:20px;color:var(--rose)}
  .grid{display:grid;gap:14px}
  .grid-2{display:grid;gap:14px}
  @media(min-width:900px){.grid-2{grid-template-columns:1fr 1fr}}
  label{font-weight:700;color:var(--mut);font-size:13px;display:block;margin-bottom:4px}
  input,select{border:1px solid var(--border);border-radius:10px;padding:10px 12px;width:100%;font-weight:700;font-size:14px;background:var(--card)}
  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:10px;font-weight:800;padding:10px 16px;font-size:14px;cursor:pointer}
  .btn.primary{background:linear-gradient(90deg,var(--rose),var(--rose-dark));color:#fff;border:0}
  .btn.ghost{background:#fff0f3;border:1px solid var(--border);color:var(--rose)}
  .note{color:var(--mut);font-size:12px}
  .banner{background:linear-gradient(90deg,#fff0f3,#ffe4e6);border:1px solid var(--border);border-radius:12px;padding:12px 16px;font-weight:700;color:#BE123C}
</style>
@endpush

@section('content')
<div class="rep-wrap" id="satReport">
  <div class="banner">📊 <strong>Exporta tu información contable del SAT</strong> — CSV/Excel listos para tu sistema.</div>

  <div class="card">
    <div class="hd">
      <h3>Reporte contable (SAT)</h3>
      <div class="muted">Periodo mensual, tipo y formato.</div>
    </div>

    <div class="bd">
      <form class="grid grid-2" method="post" action="{{ route('cliente.sat.report.export') }}">
        @csrf
        <div><label for="repPeriodo">Periodo (mensual)</label><input id="repPeriodo" name="periodo" type="month" required></div>
        <div><label for="repTipo">Tipo</label>
          <select id="repTipo" name="tipo"><option value="emitidos">Emitidos</option><option value="recibidos">Recibidos</option></select>
        </div>
        <div><label for="repFmt">Formato</label>
          <select id="repFmt" name="format"><option value="csv">CSV</option><option value="xlsx">Excel</option></select>
        </div>
        <div style="grid-column:1/-1;display:flex;gap:8px;justify-content:flex-end;margin-top:6px;">
          <button type="button" class="btn ghost" id="btnPreview">Vista rápida (CSV)</button>
          <button class="btn primary">Exportar</button>
        </div>
      </form>
      <p class="note" style="margin-top:10px;">Incluye emitidos, recibidos, pagos, cancelados y DIOT.</p>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const btnPreview = document.getElementById('btnPreview');
  const formatSel  = document.getElementById('repFmt');
  if(!btnPreview || !formatSel) return;
  btnPreview.addEventListener('click', ()=>{ formatSel.value = 'csv'; alert('Vista previa CSV generada'); });
})();
</script>
@endpush
