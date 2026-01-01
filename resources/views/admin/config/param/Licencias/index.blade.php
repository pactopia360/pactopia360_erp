<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Licencias · Clientes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:#0b1220; color:#e5e7eb; margin:0}
    .wrap{max-width:1200px;margin:0 auto;padding:22px}
    .card{background:#0f1b33;border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px}
    .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between}
    input{background:#0b1630;border:1px solid rgba(255,255,255,.12);color:#e5e7eb;border-radius:10px;padding:10px 12px}
    table{width:100%;border-collapse:collapse;margin-top:14px}
    th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left;font-size:14px}
    a.btn,button{background:#2563eb;color:white;border:0;border-radius:10px;padding:10px 12px;text-decoration:none;cursor:pointer}
    .mut{color:#94a3b8}
    .ok{background:#10b981;color:#062b1d;padding:10px 12px;border-radius:10px;margin:0 0 10px}
    .err{background:#ef4444;color:#2b0b0b;padding:10px 12px;border-radius:10px;margin:0 0 10px}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;background:rgba(255,255,255,.08);font-size:12px}
  </style>
</head>
<body>
<div class="wrap">
  <h1 style="margin:0 0 10px">Licencias · Clientes</h1>
  <p class="mut" style="margin:0 0 16px">Asigna precio/licencia y módulos por cliente vía <code>accounts.meta</code>.</p>

  @if(session('ok')) <div class="ok">{{ session('ok') }}</div> @endif
  @if($error) <div class="err">{{ $error }}</div> @endif
  @if($errors->any()) <div class="err">{{ $errors->first() }}</div> @endif

  <div class="card">
    <form method="GET" action="{{ route('admin.billing.accounts.index') }}">
      <div class="row">
        <div style="flex:1;min-width:260px">
          <input name="q" value="{{ $q }}" placeholder="Buscar: RFC, razón social, email, nombre" style="width:100%">
        </div>
        <div>
          <button type="submit">Buscar</button>
        </div>
        <div>
          <a class="btn" href="{{ route('admin.billing.prices.index') }}">Ver Catálogo de Precios</a>
        </div>
      </div>
    </form>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>RFC</th>
          <th>Razón social</th>
          <th>Email</th>
          <th>Plan</th>
          <th>Bloqueado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r->id }}</td>
          <td><span class="pill">{{ $r->rfc }}</span></td>
          <td>{{ $r->razon_social ?? $r->name }}</td>
          <td class="mut">{{ $r->email ?? '—' }}</td>
          <td class="mut">{{ $r->plan_actual ?? '—' }} / {{ $r->modo_cobro ?? '—' }}</td>
          <td>{{ (int)$r->is_blocked === 1 ? 'Sí' : 'No' }}</td>
          <td>
            <a class="btn" href="{{ route('admin.billing.accounts.show', $r->id) }}">Administrar</a>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="mut">Sin resultados.</td></tr>
      @endforelse
      </tbody>
    </table>

    <div style="margin-top:14px">
      @if(method_exists($rows,'links')) {!! $rows->links() !!} @endif
    </div>
  </div>
</div>
</body>
</html>
