<!doctype html><meta charset="utf-8">
<title>Pagos (admin)</title>
<style>body{font-family:system-ui;background:#0b1220;color:#e5e7eb;padding:18px}table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid rgba(255,255,255,.1);padding:8px}input,select{padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,.12);background:#0b1630;color:#e5e7eb}</style>
<h1>Pagos (admin)</h1>
@if($error) <p style="background:#ef4444;color:#2b0b0b;padding:8px;border-radius:8px">{{$error}}</p> @endif
@if(session('ok')) <p style="background:#10b981;color:#062b1d;padding:8px;border-radius:8px">{{session('ok')}}</p> @endif
@if($errors->any()) <p style="background:#ef4444;color:#2b0b0b;padding:8px;border-radius:8px">{{$errors->first()}}</p> @endif

<form method="GET">
  <input name="q" value="{{ $q }}" placeholder="account_id o stripe_*" />
  <select name="status">
    <option value="">— status —</option>
    @foreach(['pending','paid','failed','canceled'] as $s)
      <option value="{{$s}}" @selected($status===$s)>{{$s}}</option>
    @endforeach
  </select>
  <button>Filtrar</button>
</form>

<h3>Registrar pago manual</h3>
<form method="POST" action="{{ route('admin.billing.payments.manual') }}">
  @csrf
  <p><input name="account_id" placeholder="account_id" required></p>
  <p><input name="amount_pesos" placeholder="monto en pesos (ej 990.00)" required></p>
  <p><input name="period" placeholder="periodo YYYY-MM (opcional)"></p>
  <p><input name="concept" placeholder="concepto (opcional)"></p>
  <label><input type="checkbox" name="also_apply_statement" value="1"> también aplicar al estado de cuenta (estados_cuenta.abono)</label>
  <p><button>Guardar pago manual</button></p>
</form>

<table>
  <thead><tr><th>ID</th><th>Cuenta</th><th>RFC</th><th>Status</th><th>Monto</th><th>paid_at</th><th></th></tr></thead>
  <tbody>
  @foreach($rows as $r)
    <tr>
      <td>{{ $r->id }}</td>
      <td>{{ $r->account_id }}</td>
      <td>{{ $r->account_rfc ?? '—' }}</td>
      <td>{{ $r->status }}</td>
      <td>${{ number_format(((int)$r->amount)/100,2) }} {{ $r->currency }}</td>
      <td class="mut">{{ $r->paid_at ?? '—' }}</td>
      <td>
        <form method="POST" action="{{ route('admin.billing.payments.email', $r->id) }}">
          @csrf
          <input name="to" placeholder="correo (vacío=del cliente)" style="width:220px">
          <button>Reenviar</button>
        </form>
      </td>
    </tr>
  @endforeach
  </tbody>
</table>

@if(method_exists($rows,'links')) {!! $rows->links() !!} @endif
