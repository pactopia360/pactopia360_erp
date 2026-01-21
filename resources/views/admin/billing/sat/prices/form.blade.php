{{-- resources/views/admin/billing/sat/prices/form.blade.php --}}
@extends('layouts.admin')

@section('title', ($row?->id ? 'SAT · Editar regla' : 'SAT · Nueva regla'))

@section('content')
@php
  $isEdit = (bool)($row?->id);
@endphp

<div class="container-fluid" style="max-width:980px;">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h1 class="mb-0" style="font-weight:900;">
        SAT · {{ $isEdit ? 'Editar regla' : 'Nueva regla' }}
      </h1>
      <div class="text-muted" style="font-size:13px;">
        {{ $isEdit ? 'Actualiza la configuración de la regla.' : 'Crea una regla nueva de precios.' }}
      </div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="{{ route('admin.sat.prices.index') }}">← Volver</a>
    </div>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <div style="font-weight:800;">Corrige los siguientes errores:</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ $isEdit ? route('admin.sat.prices.update',$row->id) : route('admin.sat.prices.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="card mb-3">
      <div class="card-body">

        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-bold">Nombre</label>
            <input class="form-control" name="name" value="{{ old('name',$row->name) }}" required maxlength="120">
          </div>

          <div class="col-md-2">
            <label class="form-label fw-bold">Moneda</label>
            <input class="form-control" name="currency" value="{{ old('currency',$row->currency ?? 'MXN') }}" required maxlength="8">
          </div>

          <div class="col-md-2 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="active" name="active" value="1" {{ old('active',$row->active) ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="active">Activa</label>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-bold">Unidad</label>
            <select class="form-select" name="unit" id="unitSel" required>
              @php $u = old('unit',$row->unit ?? 'range_per_xml'); @endphp
              <option value="range_per_xml" {{ $u==='range_per_xml'?'selected':'' }}>Rango / Precio por XML</option>
              <option value="flat" {{ $u==='flat'?'selected':'' }}>Precio fijo</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-bold">Min XML</label>
            <input class="form-control" type="number" min="0" name="min_xml" value="{{ old('min_xml',$row->min_xml ?? 0) }}" required>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-bold">Max XML (opcional)</label>
            <input class="form-control" type="number" min="0" name="max_xml" value="{{ old('max_xml',$row->max_xml) }}">
          </div>

          <div class="col-md-6" id="wrapPerXml">
            <label class="form-label fw-bold">Precio por XML</label>
            <input class="form-control" type="number" step="0.01" min="0" name="price_per_xml" value="{{ old('price_per_xml',$row->price_per_xml) }}">
            <div class="text-muted mt-1" style="font-size:12px;">Se usa cuando Unidad = Rango/XML.</div>
          </div>

          <div class="col-md-6" id="wrapFlat">
            <label class="form-label fw-bold">Precio fijo</label>
            <input class="form-control" type="number" step="0.01" min="0" name="flat_price" value="{{ old('flat_price',$row->flat_price) }}">
            <div class="text-muted mt-1" style="font-size:12px;">Se usa cuando Unidad = Fijo.</div>
          </div>

          <div class="col-md-3">
            <label class="form-label fw-bold">Sort</label>
            <input class="form-control" type="number" min="0" max="999999" name="sort" value="{{ old('sort',$row->sort ?? 100) }}" required>
          </div>
        </div>

      </div>
      <div class="card-footer d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.sat.prices.index') }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Guardar cambios' : 'Crear regla' }}</button>
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
    const v = sel?.value || 'range_per_xml';
    if(v === 'flat'){
      if (per) per.style.display = 'none';
      if (fla) fla.style.display = '';
    }else{
      if (per) per.style.display = '';
      if (fla) fla.style.display = 'none';
    }
  }
  sel?.addEventListener('change', sync);
  sync();
})();
</script>
@endsection
