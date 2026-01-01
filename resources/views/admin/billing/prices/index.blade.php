<!doctype html><meta charset="utf-8">
<title>Catálogo de precios</title>
<style>body{font-family:system-ui;background:#0b1220;color:#e5e7eb;padding:18px}a{color:#93c5fd}table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid rgba(255,255,255,.1);padding:8px}</style>
<h1>Catálogo de precios</h1>
@if(session('ok')) <p style="background:#10b981;color:#062b1d;padding:8px;border-radius:8px">{{session('ok')}}</p> @endif
@if($error) <p style="background:#ef4444;color:#2b0b0b;padding:8px;border-radius:8px">{{$error}}</p> @endif

<form method="GET">
  <input name="q" value="{{ $q }}" placeholder="buscar..." />
  <label><input type="checkbox" name="active" value="1" @checked((int)$active===1)> sólo activos</label>
  <button>Filtrar</button>
</form>

<table>
  <thead><tr><th>ID</th><th>price_key</th><th>Plan</th><th>Ciclo</th><th>Monto</th><th>Activo</th><th></th></tr></thead>
  <tbody>
  @foreach($rows as $r)
    <tr>
      <td>{{ $r->id }}</td>
      <td>{{ $r->price_key }}</td>
      <td>{{ $r->plan }}</td>
      <td>{{ $r->billing_cycle }}</td>
      <td>${{ number_format((float)($r->display_amount ?? 0),2) }} {{ $r->currency }}</td>
      <td>{{ (int)$r->is_active===1?'sí':'no' }}</td>
      <td><a href="{{ route('admin.billing.prices.edit',$r->id) }}">Editar</a></td>
    </tr>
  @endforeach
  </tbody>
</table>

@if(method_exists($rows,'links')) {!! $rows->links() !!} @endif
