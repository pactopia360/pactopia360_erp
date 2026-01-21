{{-- resources/views/admin/billing/sat/discounts/form.blade.php --}}
@extends('layouts.admin')

@section('title', ($row?->id ? 'SAT · Editar código' : 'SAT · Nuevo código'))

@section('content')
@php
  $isEdit = (bool)($row?->id);
  $scopeV = old('scope', $row->scope ?? 'partner');
  $typeV  = old('type', $row->type ?? 'percent');
@endphp

<div class="container-fluid" style="max-width:980px;">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h1 class="mb-0" style="font-weight:900;">
        SAT · {{ $isEdit ? 'Editar código' : 'Nuevo código' }}
      </h1>
      <div class="text-muted" style="font-size:13px;">Configura descuentos por porcentaje o monto fijo.</div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="{{ route('admin.sat.discounts.index') }}">← Volver</a>
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

  <form method="POST" action="{{ $isEdit ? route('admin.sat.discounts.update',$row->id) : route('admin.sat.discounts.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-3">

          <div class="col-md-6">
            <label class="form-label fw-bold">Código</label>
            <input class="form-control" name="code" value="{{ old('code',$row->code) }}" maxlength="64" required>
            <div class="text-muted mt-1" style="font-size:12px;">Se normaliza a MAYÚSCULAS al guardar.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-bold">Etiqueta / Descripción (opcional)</label>
            <input class="form-control" name="label" value="{{ old('label',$row->label) }}" maxlength="140">
          </div>

          <div class="col-md-4">
            <label class="form-label fw-bold">Tipo</label>
            <select class="form-select" name="type" id="typeSel" required>
              <option value="percent" {{ $typeV==='percent'?'selected':'' }}>Porcentaje</option>
              <option value="fixed"   {{ $typeV==='fixed'?'selected':'' }}>Monto fijo (MXN)</option>
            </select>
          </div>

          <div class="col-md-4" id="wrapPct">
            <label class="form-label fw-bold">% (0–90)</label>
            <input class="form-control" type="number" min="0" max="90" name="pct" value="{{ old('pct',$row->pct ?? 0) }}">
          </div>

          <div class="col-md-4" id="wrapAmt">
            <label class="form-label fw-bold">Monto MXN</label>
            <input class="form-control" type="number" step="0.01" min="0" name="amount_mxn" value="{{ old('amount_mxn',$row->amount_mxn ?? 0) }}">
          </div>

          <div class="col-md-4">
            <label class="form-label fw-bold">Scope</label>
            <select class="form-select" name="scope" id="scopeSel" required>
              <option value="global"  {{ $scopeV==='global'?'selected':'' }}>Global</option>
              <option value="account" {{ $scopeV==='account'?'selected':'' }}>Por cuenta</option>
              <option value="partner" {{ $scopeV==='partner'?'selected':'' }}>Socio/Distribuidor</option>
            </select>
          </div>

          <div class="col-md-4" id="wrapAccount">
            <label class="form-label fw-bold">Account ID (si scope=account)</label>
            <input class="form-control" name="account_id" value="{{ old('account_id',$row->account_id) }}" maxlength="64">
          </div>

          <div class="col-md-4" id="wrapPartnerType">
            <label class="form-label fw-bold">Tipo partner</label>
            <select class="form-select" name="partner_type">
              @php $pt = old('partner_type',$row->partner_type ?? 'socio'); @endphp
              <option value="socio" {{ $pt==='socio'?'selected':'' }}>Socio</option>
              <option value="distribuidor" {{ $pt==='distribuidor'?'selected':'' }}>Distribuidor</option>
            </select>
          </div>

          <div class="col-md-6" id="wrapPartnerId">
            <label class="form-label fw-bold">Partner ID (si scope=partner)</label>
            <input class="form-control" name="partner_id" value="{{ old('partner_id',$row->partner_id) }}" maxlength="64">
          </div>

          <div class="col-md-3">
            <label class="form-label fw-bold">Inicio (opcional)</label>
            <input class="form-control" type="date" name="starts_at" value="{{ old('starts_at', $row->starts_at ? \Illuminate\Support\Carbon::parse($row->starts_at)->format('Y-m-d') : '') }}">
          </div>

          <div class="col-md-3">
            <label class="form-label fw-bold">Fin (opcional)</label>
            <input class="form-control" type="date" name="ends_at" value="{{ old('ends_at', $row->ends_at ? \Illuminate\Support\Carbon::parse($row->ends_at)->format('Y-m-d') : '') }}">
          </div>

          <div class="col-md-3">
            <label class="form-label fw-bold">Max usos (opcional)</label>
            <input class="form-control" type="number" min="1" max="1000000" name="max_uses" value="{{ old('max_uses',$row->max_uses) }}">
          </div>

          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="active" name="active" value="1" {{ old('active',$row->active) ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="active">Activo</label>
            </div>
          </div>

        </div>
      </div>

      <div class="card-footer d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.sat.discounts.index') }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Guardar cambios' : 'Crear código' }}</button>
      </div>
    </div>
  </form>
</div>

<script>
(function(){
  const scopeSel = document.getElementById('scopeSel');
  const typeSel  = document.getElementById('typeSel');

  const wrapAccount     = document.getElementById('wrapAccount');
  const wrapPartnerType = document.getElementById('wrapPartnerType');
  const wrapPartnerId   = document.getElementById('wrapPartnerId');

  const wrapPct = document.getElementById('wrapPct');
  const wrapAmt = document.getElementById('wrapAmt');

  function syncScope(){
    const v = scopeSel?.value || 'partner';
    if(v === 'global'){
      if (wrapAccount) wrapAccount.style.display = 'none';
      if (wrapPartnerType) wrapPartnerType.style.display = 'none';
      if (wrapPartnerId) wrapPartnerId.style.display = 'none';
    }else if(v === 'account'){
      if (wrapAccount) wrapAccount.style.display = '';
      if (wrapPartnerType) wrapPartnerType.style.display = 'none';
      if (wrapPartnerId) wrapPartnerId.style.display = 'none';
    }else{
      if (wrapAccount) wrapAccount.style.display = 'none';
      if (wrapPartnerType) wrapPartnerType.style.display = '';
      if (wrapPartnerId) wrapPartnerId.style.display = '';
    }
  }

  function syncType(){
    const v = typeSel?.value || 'percent';
    if(v === 'fixed'){
      if (wrapPct) wrapPct.style.display = 'none';
      if (wrapAmt) wrapAmt.style.display = '';
    }else{
      if (wrapPct) wrapPct.style.display = '';
      if (wrapAmt) wrapAmt.style.display = 'none';
    }
  }

  scopeSel?.addEventListener('change', syncScope);
  typeSel?.addEventListener('change', syncType);

  syncScope();
  syncType();
})();
</script>
@endsection
