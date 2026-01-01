@extends('layouts.admin')
@section('title','Parámetros · Precios Stripe')

@section('content')
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px">
    <div>
      <h1 style="margin:0;font-size:20px">Precios Stripe</h1>
      <div style="color:var(--muted);font-size:13px;margin-top:4px">Administra los <b>price_*</b> usados por Checkout (sin hardcode).</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn btn-primary" href="{{ route('admin.config.param.stripe_prices.create') }}" style="text-decoration:none">+ Nuevo</a>
    </div>
  </div>

  <form method="GET" action="{{ route('admin.config.param.stripe_prices.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <input name="q" value="{{ $q }}" placeholder="Buscar (key, nombre, price_...)" style="padding:10px 12px;border:1px solid var(--card-border);border-radius:10px;min-width:240px;background:var(--panel-bg);color:var(--text)">

    <select name="plan" style="padding:10px 12px;border:1px solid var(--card-border);border-radius:10px;background:var(--panel-bg);color:var(--text)">
      <option value="">Plan (todos)</option>
      @foreach($plans as $p)
        <option value="{{ $p }}" @selected($plan===$p)>{{ $p }}</option>
      @endforeach
    </select>

    <select name="cycle" style="padding:10px 12px;border:1px solid var(--card-border);border-radius:10px;background:var(--panel-bg);color:var(--text)">
      <option value="">Ciclo (todos)</option>
      <option value="mensual" @selected($cycle==='mensual')>mensual</option>
      <option value="anual"   @selected($cycle==='anual')>anual</option>
    </select>

    <select name="active" style="padding:10px 12px;border:1px solid var(--card-border);border-radius:10px;background:var(--panel-bg);color:var(--text)">
      <option value="">Activo (todos)</option>
      <option value="1" @selected($act==='1')>Activos</option>
      <option value="0" @selected($act==='0')>Inactivos</option>
    </select>

    <button class="btn" type="submit">Filtrar</button>
    <a class="btn" href="{{ route('admin.config.param.stripe_prices.index') }}" style="text-decoration:none">Limpiar</a>
  </form>

  <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:14px;overflow:auto">
    <table style="width:100%;border-collapse:collapse;min-width:980px">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--card-border)">
          <th style="padding:12px">Activo</th>
          <th style="padding:12px">Key</th>
          <th style="padding:12px">Nombre</th>
          <th style="padding:12px">Plan</th>
          <th style="padding:12px">Ciclo</th>
          <th style="padding:12px">Moneda</th>
          <th style="padding:12px">Monto (UI)</th>
          <th style="padding:12px">Stripe Price ID</th>
          <th style="padding:12px;text-align:right">Acciones</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr style="border-bottom:1px solid var(--card-border)">
            <td style="padding:12px">
              <form method="POST" action="{{ route('admin.config.param.stripe_prices.toggle', $r->id) }}">
                @csrf
                <button class="btn" type="submit">{{ $r->is_active ? 'Sí' : 'No' }}</button>
              </form>
            </td>
            <td style="padding:12px"><b>{{ $r->price_key }}</b></td>
            <td style="padding:12px">{{ $r->name }}</td>
            <td style="padding:12px">{{ $r->plan }}</td>
            <td style="padding:12px">{{ $r->billing_cycle }}</td>
            <td style="padding:12px">{{ $r->currency }}</td>
            <td style="padding:12px">{{ $r->display_amount }}</td>
            <td style="padding:12px;font-family:ui-monospace,monospace">{{ $r->stripe_price_id }}</td>
            <td style="padding:12px;text-align:right;display:flex;gap:8px;justify-content:flex-end">
              <a class="btn" href="{{ route('admin.config.param.stripe_prices.edit',$r->id) }}" style="text-decoration:none">Editar</a>

              <form method="POST" action="{{ route('admin.config.param.stripe_prices.delete',$r->id) }}"
                    onsubmit="return confirm('¿Eliminar este precio?');">
                @csrf
                @method('DELETE')
                <button class="btn" type="submit">Eliminar</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" style="padding:14px;color:var(--muted)">Sin registros.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:12px">{{ $rows->links() }}</div>
@endsection
