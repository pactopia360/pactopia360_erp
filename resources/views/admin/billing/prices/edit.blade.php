<!doctype html><meta charset="utf-8">
<title>Editar precio</title>
<style>body{font-family:system-ui;background:#0b1220;color:#e5e7eb;padding:18px}input,select{width:100%;padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,.12);background:#0b1630;color:#e5e7eb}</style>
<h1>Editar precio #{{ $row->id }}</h1>

<form method="POST" action="{{ route('admin.billing.prices.update',$row->id) }}">
  @csrf @method('PUT')
  <p><label>price_key</label><input name="price_key" value="{{ $row->price_key }}"></p>
  <p><label>name</label><input name="name" value="{{ $row->name }}"></p>
  <p><label>plan</label><input name="plan" value="{{ $row->plan }}"></p>
  <p><label>billing_cycle</label>
    <select name="billing_cycle">
      <option value="mensual" @selected($row->billing_cycle==='mensual')>mensual</option>
      <option value="anual" @selected($row->billing_cycle==='anual')>anual</option>
    </select>
  </p>
  <p><label>stripe_price_id</label><input name="stripe_price_id" value="{{ $row->stripe_price_id }}"></p>
  <p><label>currency</label><input name="currency" value="{{ $row->currency }}"></p>
  <p><label>display_amount</label><input name="display_amount" value="{{ $row->display_amount }}"></p>
  <p><label>Activo</label><select name="is_active"><option value="1" @selected((int)$row->is_active===1)>Sí</option><option value="0" @selected((int)$row->is_active===0)>No</option></select></p>
  <button>Guardar</button>
</form>

<form method="POST" action="{{ route('admin.billing.prices.toggle',$row->id) }}" style="margin-top:10px">
  @csrf
  <button type="submit">{{ (int)$row->is_active===1?'Deshabilitar':'Habilitar' }}</button>
</form>

<p><a href="{{ route('admin.billing.prices.index') }}">Volver</a></p>
