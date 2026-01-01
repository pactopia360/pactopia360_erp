<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Administrar licencia · {{ $account->rfc }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:#0b1220; color:#e5e7eb; margin:0}
    .wrap{max-width:1200px;margin:0 auto;padding:22px}
    .card{background:#0f1b33;border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px;margin-bottom:14px}
    label{display:block;margin:10px 0 6px;color:#cbd5e1;font-size:13px}
    input,select,textarea{background:#0b1630;border:1px solid rgba(255,255,255,.12);color:#e5e7eb;border-radius:10px;padding:10px 12px;width:100%}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    .col{flex:1;min-width:260px}
    a.btn,button{background:#2563eb;color:white;border:0;border-radius:10px;padding:10px 12px;text-decoration:none;cursor:pointer}
    .mut{color:#94a3b8}
    .ok{background:#10b981;color:#062b1d;padding:10px 12px;border-radius:10px;margin:0 0 10px}
    .err{background:#ef4444;color:#2b0b0b;padding:10px 12px;border-radius:10px;margin:0 0 10px}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;background:rgba(255,255,255,.08);font-size:12px}
    .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    @media(max-width:900px){.grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media(max-width:560px){.grid{grid-template-columns:repeat(1,minmax(0,1fr));}}
    .chk{display:flex;gap:10px;align-items:center;background:rgba(255,255,255,.05);padding:10px;border-radius:12px;border:1px solid rgba(255,255,255,.08)}
    .chk input{width:auto}
  </style>
</head>
<body>
<div class="wrap">
  <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap">
    <div>
      <h1 style="margin:0">Administrar licencia</h1>
      <p class="mut" style="margin:6px 0 0">
        <span class="pill">ID {{ $account->id }}</span>
        <span class="pill">{{ $account->rfc }}</span>
        <span class="pill">{{ $account->razon_social ?? $account->name }}</span>
      </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="{{ route('admin.billing.accounts.index') }}">Volver</a>
      <a class="btn" href="{{ route('admin.billing.statements.index') }}">Estados de cuenta</a>
      <a class="btn" href="{{ route('admin.billing.payments.index') }}">Pagos</a>
    </div>
  </div>

  @if(session('ok')) <div class="ok">{{ session('ok') }}</div> @endif
  @if($errors->any()) <div class="err">{{ $errors->first() }}</div> @endif

  <div class="card">
    <h2 style="margin:0 0 8px">Precio asignado</h2>
    <p class="mut" style="margin:0 0 12px">Se guarda en <code>accounts.meta.billing</code>. El cliente lo ve en su portal.</p>

    <form method="POST" action="{{ route('admin.billing.accounts.assignPrice', $account->id) }}">
      @csrf
      <div class="row">
        <div class="col">
          <label>Precio (price_key)</label>
          <select name="price_key">
            <option value="">— Sin asignar —</option>
            @foreach($prices as $p)
              <option value="{{ $p->price_key }}" @selected($assigned['price_key']===$p->price_key)>
                {{ $p->plan }} · {{ $p->billing_cycle }} · {{ $p->price_key }} · ${{ number_format((float)($p->display_amount ?? 0),2) }} · {{ (int)$p->is_active===1?'ACTIVO':'INACTIVO' }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col">
          <label>Ciclo (override opcional)</label>
          <select name="billing_cycle">
            <option value="">— Auto del precio —</option>
            <option value="mensual" @selected($assigned['billing_cycle']==='mensual')>mensual</option>
            <option value="anual"   @selected($assigned['billing_cycle']==='anual')>anual</option>
          </select>
        </div>
      </div>

      <label>Concepto / Descripción (opcional por cliente)</label>
      <input name="concept" value="{{ $assigned['concept'] ?? '' }}" placeholder="Ej. Servicio Pactopia360 · Licencia PRO personalizada">

      <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
        <button type="submit">Guardar licencia</button>
      </div>
    </form>

    <div style="margin-top:14px" class="mut">
      <strong>Actual:</strong>
      @if($priceRow)
        <span class="pill">{{ $priceRow->plan }}</span>
        <span class="pill">{{ $priceRow->billing_cycle }}</span>
        <span class="pill">${{ number_format((float)($priceRow->display_amount ?? 0),2) }} {{ $priceRow->currency }}</span>
        <span class="pill">{{ $priceRow->price_key }}</span>
      @else
        <span class="pill">Sin precio resuelto</span>
      @endif
    </div>
  </div>

  <div class="card">
    <h2 style="margin:0 0 8px">Módulos del cliente</h2>
    <p class="mut" style="margin:0 0 12px">Se guarda en <code>accounts.meta.modules</code> como mapa booleano.</p>

    <form method="POST" action="{{ route('admin.billing.accounts.modules.save', $account->id) }}">
      @csrf

      @php
        $mods = $assigned['modules'] ?? [];
        $catalog = [
          'billing.statement' => 'Estado de cuenta (ver/pagar)',
          'sat.downloads' => 'SAT Descargas',
          'vault' => 'Bóveda Fiscal',
          'facturacion' => 'Facturación (CFDI)',
          'soporte.chat' => 'Soporte (Chat)',
          'marketplace' => 'Marketplace',
        ];
      @endphp

      <div class="grid">
        @foreach($catalog as $k=>$label)
          <div class="chk">
            <input type="checkbox" name="modules[{{ $k }}]" value="1" @checked((bool)($mods[$k] ?? false))>
            <div>
              <div style="font-weight:600">{{ $label }}</div>
              <div class="mut" style="font-size:12px">{{ $k }}</div>
            </div>
          </div>
        @endforeach
      </div>

      <div style="margin-top:12px">
        <button type="submit">Guardar módulos</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2 style="margin:0 0 8px">Correo al cliente</h2>
    <p class="mut" style="margin:0 0 12px">Envía resumen de licencia/módulos al correo registrado (o uno específico).</p>

    <form method="POST" action="{{ route('admin.billing.accounts.email.license', $account->id) }}">
      @csrf
      <label>Enviar a (opcional)</label>
      <input name="to" placeholder="Si lo dejas vacío usa: {{ $account->email ?? 'SIN EMAIL' }}">
      <div style="margin-top:12px">
        <button type="submit">Enviar correo</button>
      </div>
    </form>
  </div>

</div>
</body>
</html>
