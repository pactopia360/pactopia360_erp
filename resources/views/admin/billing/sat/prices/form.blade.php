{{-- resources/views/admin/billing/sat/prices/form.blade.php --}}
@extends('layouts.admin')

@section('title', ($row?->id ? 'SAT · Editar regla' : 'SAT · Nueva regla'))

@section('content')
@php
  $row    = $row ?? (object)[
    'id'           => null,
    'name'         => '',
    'currency'     => 'MXN',
    'active'       => 0,
    'unit'         => 'range_per_xml',
    'min_xml'      => 0,
    'max_xml'      => null,
    'price_per_xml'=> 0,
    'flat_price'   => 0,
    'sort'         => 100,
  ];

  $isEdit = (bool)($row?->id);
  $u      = old('unit', $row->unit ?? 'range_per_xml');
@endphp

<style>
  .sat-wrap{ max-width:980px; margin:0 auto; padding: 18px 18px 40px; }
  .sat-head{ display:flex; gap:14px; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; margin-bottom:14px; }
  .sat-title{ margin:0; font-weight:950; letter-spacing:-.02em; color:var(--sx-ink, #0f172a); font-size:26px; }
  .sat-sub{ margin-top:6px; color:var(--sx-mut, #64748b); font-size:13px; }
  .sat-actions{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }

  .sat-btn{ appearance:none; border:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 18%, transparent);
    background:transparent; color:var(--sx-ink, #0f172a); padding:10px 12px; border-radius:12px; font-weight:850;
    text-decoration:none; display:inline-flex; gap:8px; align-items:center; }
  .sat-btn:hover{ background: color-mix(in oklab, var(--sx-ink, #0f172a) 6%, transparent); }
  .sat-btn.primary{ background: color-mix(in oklab, var(--sx-brand, #e11d48) 16%, transparent);
    border-color: color-mix(in oklab, var(--sx-brand, #e11d48) 35%, transparent); }
  .sat-btn.primary:hover{ background: color-mix(in oklab, var(--sx-brand, #e11d48) 22%, transparent); }

  .sat-card{ background: var(--card, #fff);
    border:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 12%, transparent);
    border-radius:16px;
    box-shadow: 0 10px 28px rgba(2,6,23,.06);
    overflow:hidden;
  }
  .sat-card-hd{ padding:14px 14px 12px; border-bottom:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 10%, transparent); }
  .sat-card-hd .t{ font-weight:950; color:var(--sx-ink,#0f172a); }
  .sat-card-hd .d{ margin-top:4px; color:var(--sx-mut,#64748b); font-size:13px; font-weight:700; }
  .sat-card-bd{ padding:14px; }
  .sat-card-ft{ padding:12px 14px; border-top:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 10%, transparent);
    display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; }

  .sat-alert{ margin:0 0 14px; padding:12px 14px; border-radius:14px; border:1px solid transparent; font-weight:800; }
  .sat-alert.err{ background: color-mix(in oklab, #ef4444 10%, transparent); border-color: color-mix(in oklab, #ef4444 34%, transparent); color: #7f1d1d; }
  .sat-alert.err ul{ margin:8px 0 0; padding-left:18px; font-weight:700; }
  .sat-alert.err li{ margin:3px 0; }

  .sat-grid{ display:grid; grid-template-columns: repeat(12, 1fr); gap:12px; }
  .sat-col-12{ grid-column: span 12; }
  .sat-col-8{ grid-column: span 12; }
  .sat-col-6{ grid-column: span 12; }
  .sat-col-4{ grid-column: span 12; }
  .sat-col-3{ grid-column: span 12; }
  .sat-col-2{ grid-column: span 12; }

  @media (min-width: 860px){
    .sat-col-8{ grid-column: span 8; }
    .sat-col-6{ grid-column: span 6; }
    .sat-col-4{ grid-column: span 4; }
    .sat-col-3{ grid-column: span 3; }
    .sat-col-2{ grid-column: span 2; }
  }

  .sat-field label{ display:block; font-size:12px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; color:var(--sx-mut,#64748b); margin:0 0 6px; }
  .sat-input, .sat-select{
    width:100%;
    height:42px;
    border-radius:12px;
    border:1px solid color-mix(in oklab, var(--sx-ink,#0f172a) 14%, transparent);
    background: color-mix(in oklab, var(--sx-ink,#0f172a) 2%, transparent);
    color: var(--sx-ink,#0f172a);
    padding:0 12px;
    outline:none;
    font-weight:800;
  }
  .sat-input:focus, .sat-select:focus{
    border-color: color-mix(in oklab, var(--sx-brand,#e11d48) 45%, transparent);
    box-shadow: 0 0 0 3px color-mix(in oklab, var(--sx-brand,#e11d48) 18%, transparent);
  }
  .sat-help{ margin-top:6px; color:var(--sx-mut,#64748b); font-size:12px; font-weight:700; }

  .sat-checkline{
    display:flex; align-items:center; gap:10px;
    height:42px;
    padding:0 12px;
    border-radius:12px;
    border:1px solid color-mix(in oklab, var(--sx-ink,#0f172a) 14%, transparent);
    background: color-mix(in oklab, var(--sx-ink,#0f172a) 2%, transparent);
    color: var(--sx-ink,#0f172a);
    font-weight:900;
    user-select:none;
  }
  .sat-checkline input{ width:18px; height:18px; }

  .sat-split{
    display:grid; gap:12px; grid-template-columns: 1fr;
  }
  @media (min-width: 860px){
    .sat-split{ grid-template-columns: 1fr 1fr; }
  }

  .sat-note{
    padding:12px 12px;
    border-radius:14px;
    border:1px dashed color-mix(in oklab, var(--sx-ink,#0f172a) 18%, transparent);
    background: color-mix(in oklab, var(--sx-ink,#0f172a) 2%, transparent);
    color: var(--sx-mut,#64748b);
    font-weight:800;
    font-size:12px;
  }
</style>

<div class="sat-wrap">
  <div class="sat-head">
    <div>
      <h1 class="sat-title">SAT · {{ $isEdit ? 'Editar regla' : 'Nueva regla' }}</h1>
      <div class="sat-sub">
        {{ $isEdit ? 'Actualiza la configuración de la regla.' : 'Crea una regla nueva de precios.' }}
      </div>
    </div>

    <div class="sat-actions">
      <a class="sat-btn" href="{{ route('admin.sat.prices.index') }}">← Volver</a>
      <a class="sat-btn" href="{{ route('admin.sat.discounts.index') }}">🎟️ Descuentos</a>
    </div>
  </div>

  @if ($errors->any())
    <div class="sat-alert err">
      Corrige los siguientes errores:
      <ul>
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ $isEdit ? route('admin.sat.prices.update', $row->id) : route('admin.sat.prices.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="sat-card">
      <div class="sat-card-hd">
        <div class="t">Configuración</div>
        <div class="d">Define volumen, unidad y monto. El sistema normaliza campos según la unidad seleccionada.</div>
      </div>

      <div class="sat-card-bd">
        <div class="sat-grid">

          <div class="sat-field sat-col-8">
            <label>Nombre</label>
            <input
              class="sat-input"
              name="name"
              value="{{ old('name', $row->name) }}"
              required
              maxlength="120"
              placeholder="Ej. Volumen 1 a 500 XML"
              autocomplete="off"
            >
          </div>

          <div class="sat-field sat-col-2">
            <label>Moneda</label>
            <input
              class="sat-input"
              name="currency"
              value="{{ old('currency', $row->currency ?? 'MXN') }}"
              required
              maxlength="8"
              placeholder="MXN"
              autocomplete="off"
            >
          </div>

          <div class="sat-field sat-col-2">
            <label>Activa</label>
            <div class="sat-checkline">
              {{-- Si no se marca, enviar 0 --}}
              <input type="hidden" name="active" value="0">
              <input
                type="checkbox"
                id="active"
                name="active"
                value="1"
                {{ old('active', (int)($row->active ?? 0)) ? 'checked' : '' }}
              >
              <label for="active" style="margin:0;font-weight:950;cursor:pointer;">Habilitada</label>
            </div>
          </div>

          <div class="sat-field sat-col-4">
            <label>Unidad</label>
            <select class="sat-select" name="unit" id="unitSel" required>
              <option value="range_per_xml" {{ $u==='range_per_xml'?'selected':'' }}>Rango / Precio por XML</option>
              <option value="flat" {{ $u==='flat'?'selected':'' }}>Precio fijo (volumen)</option>
            </select>
            <div class="sat-help">Define si se cobra por XML o un precio total por volumen.</div>
          </div>

          <div class="sat-field sat-col-4">
            <label>Min XML</label>
            <input
              class="sat-input"
              type="number"
              min="0"
              step="1"
              inputmode="numeric"
              name="min_xml"
              value="{{ old('min_xml', $row->min_xml ?? 0) }}"
              required
              placeholder="0"
            >
          </div>

          <div class="sat-field sat-col-4">
            <label>Max XML (opcional)</label>
            <input
              class="sat-input"
              type="number"
              min="0"
              step="1"
              inputmode="numeric"
              name="max_xml"
              value="{{ old('max_xml', $row->max_xml) }}"
              placeholder="Ej. 500"
            >
          </div>

          <div class="sat-col-12">
            <div class="sat-split">
              <div class="sat-field" id="wrapPerXml" style="{{ $u === 'flat' ? 'display:none;' : '' }}">
                <label>Precio por XML</label>
                <input
                  class="sat-input"
                  type="number"
                  step="0.01"
                  min="0"
                  inputmode="decimal"
                  name="price_per_xml"
                  value="{{ old('price_per_xml', $row->price_per_xml) }}"
                  placeholder="0.00"
                >
                <div class="sat-help">Se usa cuando Unidad = Rango/XML.</div>
              </div>

              <div class="sat-field" id="wrapFlat" style="{{ $u === 'flat' ? '' : 'display:none;' }}">
                <label>Precio fijo</label>
                <input
                  class="sat-input"
                  type="number"
                  step="0.01"
                  min="0"
                  inputmode="decimal"
                  name="flat_price"
                  value="{{ old('flat_price', $row->flat_price) }}"
                  placeholder="0.00"
                >
                <div class="sat-help">Se usa cuando Unidad = Precio fijo.</div>
              </div>
            </div>
          </div>

          <div class="sat-field sat-col-3">
            <label>Sort</label>
            <input
              class="sat-input"
              type="number"
              min="0"
              max="999999"
              step="1"
              inputmode="numeric"
              name="sort"
              value="{{ old('sort', $row->sort ?? 100) }}"
              required
              placeholder="100"
            >
            <div class="sat-help">Orden de aplicación (menor = primero).</div>
          </div>

          <div class="sat-col-9">
            <div class="sat-note">
              Recomendación: usa nombres claros por volumen (p. ej. “1–500 XML”), y mantén Sort incremental (10, 20, 30…).
              Si “Max XML” está vacío, la regla se interpreta como “sin tope”.
            </div>
          </div>

        </div>
      </div>

      <div class="sat-card-ft">
        <a class="sat-btn" href="{{ route('admin.sat.prices.index') }}">Cancelar</a>
        <button class="sat-btn primary" type="submit">{{ $isEdit ? 'Guardar cambios' : 'Crear regla' }}</button>
      </div>
    </div>
  </form>
</div>

<script>
(function(){
  const sel = document.getElementById('unitSel');
  const per = document.getElementById('wrapPerXml');
  const fla = document.getElementById('wrapFlat');

  function sync(){
    const v = (sel && sel.value) ? sel.value : 'range_per_xml';
    if(v === 'flat'){
      if (per) per.style.display = 'none';
      if (fla) fla.style.display = '';
    }else{
      if (per) per.style.display = '';
      if (fla) fla.style.display = 'none';
    }
  }

  if (sel) sel.addEventListener('change', sync);
  sync();
})();
</script>
@endsection
