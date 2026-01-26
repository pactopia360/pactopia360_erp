{{-- resources/views/admin/billing/sat/discounts/form.blade.php --}}
@extends('layouts.admin')

@section('title', ($row?->id ? 'SAT · Editar código' : 'SAT · Nuevo código'))

@section('content')
@php
  $isEdit = (bool)($row?->id);
  $scopeV = old('scope', $row->scope ?? 'global');
  $typeV  = old('type',  $row->type  ?? 'percent');
@endphp

<style>
  /* P360 · Admin · SAT Discounts · Form (v2 UI) */
  .p360-wrap{ max-width:1080px; margin:0 auto; padding:18px 18px 46px; }
  .p360-head{ display:flex; align-items:flex-end; justify-content:space-between; gap:14px; flex-wrap:wrap; }
  .p360-title{ margin:0; font-weight:950; letter-spacing:-.02em; color:var(--sx-ink,#0f172a); font-size:28px; line-height:1.1; }
  .p360-sub{ margin-top:6px; color:var(--sx-mut,#64748b); font-size:13px; font-weight:650; }
  .p360-actions{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
  .p360-btn{
    appearance:none; border:1px solid color-mix(in oklab, var(--sx-ink,#0f172a) 18%, transparent);
    background:transparent; color:var(--sx-ink,#0f172a); padding:10px 12px; border-radius:12px;
    font-weight:900; text-decoration:none; display:inline-flex; align-items:center; gap:8px;
  }
  .p360-btn:hover{ background:color-mix(in oklab, var(--sx-ink,#0f172a) 6%, transparent); }
  .p360-btn.primary{
    background: color-mix(in oklab, var(--sx-brand,#e11d48) 16%, transparent);
    border-color: color-mix(in oklab, var(--sx-brand,#e11d48) 35%, transparent);
  }
  .p360-btn.primary:hover{ background: color-mix(in oklab, var(--sx-brand,#e11d48) 22%, transparent); }

  .p360-card{
    background:var(--card,#fff);
    border:1px solid color-mix(in oklab, var(--sx-ink,#0f172a) 12%, transparent);
    border-radius:18px;
    box-shadow:0 12px 34px rgba(2,6,23,.07);
    overflow:hidden;
  }
  .p360-card + .p360-card{ margin-top:14px; }

  .p360-card-hd{
    padding:14px 16px;
    border-bottom:1px solid color-mix(in oklab, var(--sx-ink,#0f172a) 10%, transparent);
    background: color-mix(in oklab, var(--sx-ink,#0f172a) 3%, transparent);
    display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
  }
  .p360-card-hd .h{ font-weight:950; letter-spacing:-.01em; margin:0; }
  .p360-chip{
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 10px; border-radius:999px; font-size:12px; font-weight:900;
    border:1px solid color-mix(in oklab, var(--sx-ink,#0f172a) 14%, transparent);
    background: color-mix(in oklab, var(--sx-ink,#0f172a) 2%, transparent);
    color:var(--sx-ink,#0f172a);
  }
  .p360-chip.ok{
    background: color-mix(in oklab, #16a34a 12%, transparent);
    border-color: color-mix(in oklab, #16a34a 28%, transparent);
  }
  .p360-chip.info{
    background: color-mix(in oklab, #0ea5e9 12%, transparent);
    border-color: color-mix(in oklab, #0ea5e9 28%, transparent);
  }

  .p360-body{ padding:16px; }
  .p360-grid{ display:grid; gap:12px; grid-template-columns:repeat(12, 1fr); }
  .col-12{ grid-column: span 12; }
  .col-8{ grid-column: span 8; }
  .col-6{ grid-column: span 6; }
  .col-4{ grid-column: span 4; }
  .col-3{ grid-column: span 3; }
  .col-2{ grid-column: span 2; }

  @media (max-width: 980px){
    .col-8,.col-6,.col-4,.col-3,.col-2{ grid-column: span 12; }
    .p360-title{ font-size:24px; }
  }

  .p360-field{ display:flex; flex-direction:column; gap:6px; }
  .p360-label{ font-weight:900; font-size:13px; color:var(--sx-ink,#0f172a); }
  .p360-hint{ font-size:12px; color:var(--sx-mut,#64748b); font-weight:650; }

  .p360-inp, .p360-sel{
    border:1px solid color-mix(in oklab, var(--sx-ink,#0f172a) 14%, transparent);
    background: color-mix(in oklab, #fff 92%, transparent);
    color:var(--sx-ink,#0f172a);
    border-radius:14px;
    padding:11px 12px;
    font-weight:800;
    font-size:13px;
    outline:none;
    min-height:42px;
  }
  .p360-inp:focus, .p360-sel:focus{
    border-color: color-mix(in oklab, var(--sx-brand,#e11d48) 60%, var(--sx-ink,#0f172a));
    box-shadow:0 0 0 3px color-mix(in oklab, var(--sx-brand,#e11d48) 18%, transparent);
  }
  html.theme-dark .p360-inp, html.theme-dark .p360-sel{
    background: color-mix(in oklab, #0b1220 82%, transparent);
  }

  .p360-split{
    display:flex; align-items:center; justify-content:space-between;
    gap:10px; padding:12px 14px; border-radius:16px;
    border:1px dashed color-mix(in oklab, var(--sx-ink,#0f172a) 16%, transparent);
    background: color-mix(in oklab, var(--sx-ink,#0f172a) 2%, transparent);
  }
  .p360-split .k{ font-weight:950; }
  .p360-split .v{ color:var(--sx-mut,#64748b); font-size:12px; font-weight:750; }

  /* Switch */
  .p360-switch{ display:flex; align-items:center; gap:10px; user-select:none; }
  .p360-switch input{ display:none; }
  .p360-toggle{
    width:44px; height:26px; border-radius:999px; position:relative;
    border:1px solid color-mix(in oklab, var(--sx-ink,#0f172a) 18%, transparent);
    background: color-mix(in oklab, var(--sx-ink,#0f172a) 6%, transparent);
    transition:.18s ease;
  }
  .p360-toggle:before{
    content:""; position:absolute; top:3px; left:3px; width:20px; height:20px;
    border-radius:999px; background: var(--card,#fff);
    box-shadow:0 6px 16px rgba(2,6,23,.18);
    transition:.18s ease;
  }
  .p360-switch input:checked + .p360-toggle{
    background: color-mix(in oklab, #16a34a 26%, transparent);
    border-color: color-mix(in oklab, #16a34a 42%, transparent);
  }
  .p360-switch input:checked + .p360-toggle:before{ left:21px; }

  .p360-foot{
    padding:14px 16px;
    border-top:1px solid color-mix(in oklab, var(--sx-ink,#0f172a) 10%, transparent);
    display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;
    background: color-mix(in oklab, var(--sx-ink,#0f172a) 2%, transparent);
  }

  .p360-alert{
    padding:12px 14px; border-radius:16px;
    border:1px solid color-mix(in oklab, #ef4444 34%, transparent);
    background: color-mix(in oklab, #ef4444 10%, transparent);
    color:var(--sx-ink,#0f172a);
  }
  .p360-alert .t{ font-weight:950; margin:0 0 8px; }
  .p360-alert ul{ margin:0; padding-left:18px; }
</style>

<div class="p360-wrap">
  <div class="p360-head">
    <div>
      <h1 class="p360-title">SAT · {{ $isEdit ? 'Editar código' : 'Nuevo código' }}</h1>
      <div class="p360-sub">Configura descuentos por porcentaje o monto fijo. Soporta global, por cuenta o por socio/distribuidor.</div>
    </div>

    <div class="p360-actions">
      <a class="p360-btn" href="{{ route('admin.sat.discounts.index') }}">← Volver</a>
      @if($isEdit)
        <span class="p360-chip ok">#{{ $row->id }} · Editando</span>
      @else
        <span class="p360-chip info">Creación</span>
      @endif
    </div>
  </div>

  @if ($errors->any())
    <div class="p360-card" style="margin-top:14px;">
      <div class="p360-body">
        <div class="p360-alert">
          <div class="t">Corrige los siguientes errores:</div>
          <ul>
            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
          </ul>
        </div>
      </div>
    </div>
  @endif

  <form method="POST" action="{{ $isEdit ? route('admin.sat.discounts.update',$row->id) : route('admin.sat.discounts.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    {{-- Card: Datos base --}}
    <div class="p360-card" style="margin-top:14px;">
      <div class="p360-card-hd">
        <h3 class="h">Datos del código</h3>
        <div class="p360-chip">Se normaliza a MAYÚSCULAS al guardar</div>
      </div>

      <div class="p360-body">
        <div class="p360-grid">
          <div class="p360-field col-6">
            <label class="p360-label">Código</label>
            <input class="p360-inp" name="code" value="{{ old('code',$row->code) }}" maxlength="64" required placeholder="Ej. SAT10, SOCIO-2026, CUENTA50">
            <div class="p360-hint">Evita espacios. Recomendado: A-Z, 0-9, guion.</div>
          </div>

          <div class="p360-field col-6">
            <label class="p360-label">Etiqueta / Descripción</label>
            <input class="p360-inp" name="label" value="{{ old('label',$row->label) }}" maxlength="140" placeholder="Ej. Promoción enero / Convenio socio / Campaña">
            <div class="p360-hint">Opcional. Visible en admin para identificar el cupón.</div>
          </div>

          <div class="p360-field col-4">
            <label class="p360-label">Tipo de descuento</label>
            <select class="p360-sel" name="type" id="typeSel" required>
              <option value="percent" {{ $typeV==='percent'?'selected':'' }}>Porcentaje</option>
              <option value="fixed"   {{ $typeV==='fixed'?'selected':'' }}>Monto fijo (MXN)</option>
            </select>
            <div class="p360-hint">Porcentaje aplica 0–90. Fijo aplica un monto en MXN.</div>
          </div>

          <div class="p360-field col-4" id="wrapPct">
            <label class="p360-label">% (0–90)</label>
            <input class="p360-inp" type="number" min="0" max="90" name="pct" value="{{ old('pct',$row->pct ?? 0) }}">
            <div class="p360-hint">Ej. 10 = 10% de descuento.</div>
          </div>

          <div class="p360-field col-4" id="wrapAmt">
            <label class="p360-label">Monto (MXN)</label>
            <input class="p360-inp" type="number" step="0.01" min="0" name="amount_mxn" value="{{ old('amount_mxn',$row->amount_mxn ?? 0) }}">
            <div class="p360-hint">Ej. 150 = $150.00 MXN.</div>
          </div>

          <div class="p360-field col-4">
            <label class="p360-label">Scope</label>
            <select class="p360-sel" name="scope" id="scopeSel" required>
              <option value="global"  {{ $scopeV==='global'?'selected':'' }}>Global</option>
              <option value="account" {{ $scopeV==='account'?'selected':'' }}>Por cuenta</option>
              <option value="partner" {{ $scopeV==='partner'?'selected':'' }}>Socio / Distribuidor</option>
            </select>
            <div class="p360-hint">Define a quién aplica el cupón.</div>
          </div>

          <div class="p360-field col-8" id="wrapAccount">
            <label class="p360-label">Account ID (si scope=account)</label>
            <input class="p360-inp" name="account_id" value="{{ old('account_id',$row->account_id) }}" maxlength="64" placeholder="UUID / ID de cuenta en admin.accounts">
            <div class="p360-hint">Solo aplica a esa cuenta.</div>
          </div>

          <div class="p360-field col-4" id="wrapPartnerType">
            <label class="p360-label">Tipo partner</label>
            @php $pt = old('partner_type',$row->partner_type ?? 'socio'); @endphp
            <select class="p360-sel" name="partner_type">
              <option value="socio" {{ $pt==='socio'?'selected':'' }}>Socio</option>
              <option value="distribuidor" {{ $pt==='distribuidor'?'selected':'' }}>Distribuidor</option>
            </select>
            <div class="p360-hint">Clasificación interna.</div>
          </div>

          <div class="p360-field col-8" id="wrapPartnerId">
            <label class="p360-label">Partner ID (si scope=partner)</label>
            <input class="p360-inp" name="partner_id" value="{{ old('partner_id',$row->partner_id) }}" maxlength="64" placeholder="Identificador del socio/distribuidor">
            <div class="p360-hint">Solo aplica a ese partner (según tu lógica de negocio).</div>
          </div>
        </div>

        <div class="p360-split" style="margin-top:14px;">
          <div>
            <div class="k">Reglas rápidas</div>
            <div class="v">Si scope=global se limpian account/partner. Si type=fixed se fuerza pct=0.</div>
          </div>
          <div class="p360-switch">
            <input type="checkbox" id="active" name="active" value="1" {{ old('active',$row->active) ? 'checked' : '' }}>
            <span class="p360-toggle" aria-hidden="true"></span>
            <label for="active" style="font-weight:950; margin:0;">Activo</label>
          </div>
        </div>
      </div>

      {{-- Card: Vigencia / límites --}}
      <div class="p360-card-hd" style="border-top:1px solid color-mix(in oklab, var(--sx-ink,#0f172a) 10%, transparent);">
        <h3 class="h">Vigencia y límites</h3>
        <div class="p360-chip">Opcional</div>
      </div>

      <div class="p360-body">
        <div class="p360-grid">
          <div class="p360-field col-4">
            <label class="p360-label">Inicio</label>
            <input class="p360-inp" type="date" name="starts_at" value="{{ old('starts_at', $row->starts_at ? \Illuminate\Support\Carbon::parse($row->starts_at)->format('Y-m-d') : '') }}">
            <div class="p360-hint">Se guardará como 00:00.</div>
          </div>

          <div class="p360-field col-4">
            <label class="p360-label">Fin</label>
            <input class="p360-inp" type="date" name="ends_at" value="{{ old('ends_at', $row->ends_at ? \Illuminate\Support\Carbon::parse($row->ends_at)->format('Y-m-d') : '') }}">
            <div class="p360-hint">Se guardará como 23:59:59.</div>
          </div>

          <div class="p360-field col-4">
            <label class="p360-label">Máx. usos</label>
            <input class="p360-inp" type="number" min="0" max="1000000" name="max_uses" value="{{ old('max_uses',$row->max_uses) }}" placeholder="Ej. 100">
            <div class="p360-hint">0 o vacío = ilimitado (según tu lógica).</div>
          </div>
        </div>
      </div>

      <div class="p360-foot">
        <a class="p360-btn" href="{{ route('admin.sat.discounts.index') }}">Cancelar</a>
        <button class="p360-btn primary" type="submit">{{ $isEdit ? 'Guardar cambios' : 'Crear código' }}</button>
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
    const v = scopeSel?.value || 'global';
    if(v === 'global'){
      if (wrapAccount)     wrapAccount.style.display = 'none';
      if (wrapPartnerType) wrapPartnerType.style.display = 'none';
      if (wrapPartnerId)   wrapPartnerId.style.display = 'none';
    }else if(v === 'account'){
      if (wrapAccount)     wrapAccount.style.display = '';
      if (wrapPartnerType) wrapPartnerType.style.display = 'none';
      if (wrapPartnerId)   wrapPartnerId.style.display = 'none';
    }else{
      if (wrapAccount)     wrapAccount.style.display = 'none';
      if (wrapPartnerType) wrapPartnerType.style.display = '';
      if (wrapPartnerId)   wrapPartnerId.style.display = '';
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

  // NOTE: el switch "Activo" se muestra siempre; la normalización sucede en backend.
  syncScope();
  syncType();
})();
</script>
@endsection
