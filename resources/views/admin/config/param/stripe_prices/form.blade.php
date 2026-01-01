@extends('layouts.admin')
@section('title', $mode==='create' ? 'Nuevo precio Stripe' : 'Editar precio Stripe')

@section('content')
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px">
    <div>
      <h1 style="margin:0;font-size:20px">{{ $mode==='create' ? 'Nuevo precio Stripe' : 'Editar precio Stripe' }}</h1>
      <div style="color:var(--muted);font-size:13px;margin-top:4px">Estos registros alimentan Checkout del cliente.</div>
    </div>
    <a class="btn" href="{{ route('admin.config.param.stripe_prices.index') }}" style="text-decoration:none">← Volver</a>
  </div>

  <form method="POST"
        action="{{ $mode==='create' ? route('admin.config.param.stripe_prices.store') : route('admin.config.param.stripe_prices.update',$row->id) }}"
        style="max-width:820px;background:var(--card-bg);border:1px solid var(--card-border);border-radius:14px;padding:14px">
    @csrf
    @if($mode!=='create') @method('PUT') @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div>
        <label><b>price_key</b></label>
        <input name="price_key" value="{{ old('price_key',$row->price_key) }}" placeholder="pro_mensual"
               style="width:100%;padding:10px 12px;border:1px solid var(--card-border);border-radius:10px;background:var(--panel-bg);color:var(--text)">
        <div style="color:var(--muted);font-size:12px;margin-top:6px">Clave estable (solo letras/números/_).</div>
      </div>

      <div>
        <label><b>Nombre</b></label>
        <input name="name" value="{{ old('name',$row->name) }}" placeholder="PRO Mensual"
               style="width:100%;padding:10px 12px;border:1px solid var(--card-border);border-radius:10px;background:var(--panel-bg);color:var(--text)">
      </div>

      <div>
        <label><b>Plan</b></label>
        <input name="plan" value="{{ old('plan',$row->plan) }}" placeholder="PRO"
               style="width:100%;padding:10px 12px;border:1px solid var(--card-border);border-radius:10px;background:var(--panel-bg);color:var(--text)">
      </div>

      <div>
        <label><b>Ciclo</b></label>
        <select name="billing_cycle"
                style="width:100%;padding:10px 12px;border:1px solid var(--card-border);border-radius:10px;background:var(--panel-bg);color:var(--text)">
          <option value="mensual" @selected(old('billing_cycle',$row->billing_cycle)==='mensual')>mensual</option>
          <option value="anual"   @selected(old('billing_cycle',$row->billing_cycle)==='anual')>anual</option>
        </select>
      </div>

      <div style="grid-column:1 / -1">
        <label><b>stripe_price_id</b></label>
        <input name="stripe_price_id" value="{{ old('stripe_price_id',$row->stripe_price_id) }}" placeholder="price_..."
               style="width:100%;padding:10px 12px;border:1px solid var(--card-border);border-radius:10px;background:var(--panel-bg);color:var(--text);font-family:ui-monospace,monospace">
      </div>

      <div>
        <label><b>Moneda</b></label>
        <input name="currency" value="{{ old('currency',$row->currency ?? 'MXN') }}" placeholder="MXN"
               style="width:100%;padding:10px 12px;border:1px solid var(--card-border);border-radius:10px;background:var(--panel-bg);color:var(--text)">
      </div>

      <div>
        <label><b>Monto (UI)</b></label>
        <input name="display_amount" value="{{ old('display_amount',$row->display_amount) }}" placeholder="990.00"
               style="width:100%;padding:10px 12px;border:1px solid var(--card-border);border-radius:10px;background:var(--panel-bg);color:var(--text)">
      </div>

      <div style="grid-column:1 / -1;display:flex;gap:10px;align-items:center">
        <label style="display:flex;gap:8px;align-items:center">
          <input type="checkbox" name="is_active" value="1" @checked(old('is_active',$row->is_active))>
          <b>Activo</b>
        </label>
        <span style="color:var(--muted);font-size:12px">Si está inactivo, Checkout no lo podrá usar.</span>
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit">{{ $mode==='create' ? 'Crear' : 'Guardar' }}</button>
      <a class="btn" href="{{ route('admin.config.param.stripe_prices.index') }}" style="text-decoration:none">Cancelar</a>
    </div>
  </form>
@endsection
