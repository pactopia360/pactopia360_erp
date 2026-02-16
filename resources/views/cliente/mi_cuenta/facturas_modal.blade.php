{{-- resources/views/cliente/mi_cuenta/facturas/modal.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Facturas</title>
  <style>
    :root{
      --bg:#0b1220;
      --card:rgba(255,255,255,.06);
      --card2:rgba(255,255,255,.04);
      --line:rgba(255,255,255,.10);
      --ink:#e5e7eb;
      --mut:#94a3b8;
      --ok:#10b981;
      --warn:#f59e0b;
      --bad:#ef4444;
      --accent:#e11d48;
      --shadow: 0 20px 60px rgba(0,0,0,.45);
      --radius:16px;
      --radius2:12px;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial;
      background: radial-gradient(1200px 600px at 12% 10%, rgba(225,29,72,.18), transparent 60%),
                  radial-gradient(900px 500px at 85% 30%, rgba(59,130,246,.14), transparent 55%),
                  var(--bg);
      color:var(--ink);
      padding:16px;
    }
    .wrap{max-width:1100px;margin:0 auto}
    .top{
      display:flex; gap:12px; align-items:flex-start; justify-content:space-between;
      padding:14px 14px 10px;
      border:1px solid var(--line);
      background:linear-gradient(180deg,var(--card),var(--card2));
      border-radius:var(--radius);
      box-shadow: var(--shadow);
    }
    .title{min-width:0}
    h1{margin:0;font-size:18px;letter-spacing:.2px}
    .sub{margin-top:6px;color:var(--mut);font-size:13px}
    .pillrow{display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end}
    .pill{
      display:inline-flex; align-items:center; gap:8px;
      padding:7px 10px; border-radius:999px;
      background:rgba(255,255,255,.06);
      border:1px solid var(--line);
      color:var(--ink); font-weight:800; font-size:12px;
      white-space:nowrap;
    }
    .dot{width:8px;height:8px;border-radius:999px;background:var(--mut)}
    .dot.ok{background:var(--ok)}
    .dot.warn{background:var(--warn)}
    .dot.bad{background:var(--bad)}
    .alerts{margin:12px 0; display:grid; gap:8px}
    .alert{
      padding:10px 12px;
      border-radius:14px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.05);
      font-weight:800;
      font-size:13px;
    }
    .alert.ok{border-color:rgba(16,185,129,.35); background:rgba(16,185,129,.10)}
    .alert.warn{border-color:rgba(245,158,11,.35); background:rgba(245,158,11,.12); color:#fff}
    .alert.bad{border-color:rgba(239,68,68,.35); background:rgba(239,68,68,.12)}
    .grid{
      margin-top:12px;
      display:grid;
      grid-template-columns: 1.2fr .8fr .7fr auto;
      gap:10px;
      padding:12px;
      border:1px solid var(--line);
      border-radius:var(--radius);
      background:rgba(255,255,255,.04);
    }
    @media (max-width: 860px){
      .grid{grid-template-columns:1fr 1fr; }
      .grid .full{grid-column:1 / -1}
      .pillrow{justify-content:flex-start}
      .top{flex-direction:column}
    }
    label{display:block;color:var(--mut);font-size:12px;font-weight:800;margin:0 0 6px}
    input,select,button{
      width:100%;
      padding:10px 12px;
      border-radius:12px;
      border:1px solid var(--line);
      background:rgba(2,6,23,.35);
      color:var(--ink);
      outline:none;
    }
    input::placeholder{color:rgba(148,163,184,.75)}
    button{
      cursor:pointer;
      font-weight:900;
      border-color:rgba(225,29,72,.35);
      background:linear-gradient(180deg, rgba(225,29,72,.22), rgba(225,29,72,.10));
    }
    button:hover{filter:brightness(1.05)}
    .btn2{
      border-color:rgba(255,255,255,.12);
      background:rgba(255,255,255,.06);
    }
    .card{
      margin-top:12px;
      border:1px solid var(--line);
      border-radius:var(--radius);
      background:linear-gradient(180deg,var(--card),var(--card2));
      box-shadow: var(--shadow);
      overflow:hidden;
    }
    table{width:100%; border-collapse:collapse}
    thead th{
      text-align:left;
      font-size:12px;
      color:var(--mut);
      padding:12px;
      border-bottom:1px solid var(--line);
      background:rgba(2,6,23,.25);
      font-weight:900;
      letter-spacing:.2px;
    }
    tbody td{
      padding:12px;
      border-bottom:1px solid rgba(255,255,255,.08);
      vertical-align:middle;
      font-size:13px;
    }
    tbody tr:hover{background:rgba(255,255,255,.04)}
    .k{color:var(--mut); font-size:12px; font-weight:900}
    .v{font-weight:900}
    .status{
      display:inline-flex; align-items:center; gap:8px;
      padding:6px 10px; border-radius:999px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.06);
      font-weight:900; font-size:12px;
      white-space:nowrap;
    }
    .status.ok{border-color:rgba(16,185,129,.35); background:rgba(16,185,129,.10)}
    .status.warn{border-color:rgba(245,158,11,.35); background:rgba(245,158,11,.12)}
    .status.bad{border-color:rgba(239,68,68,.35); background:rgba(239,68,68,.12)}
    .actions{display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap}
    .a{
      display:inline-flex; align-items:center; justify-content:center;
      padding:8px 10px; border-radius:12px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.06);
      color:var(--ink); text-decoration:none;
      font-weight:900; font-size:12px;
      white-space:nowrap;
    }
    .a.primary{border-color:rgba(225,29,72,.35); background:rgba(225,29,72,.14)}
    .a[aria-disabled="true"]{opacity:.45; pointer-events:none}
    .foot{
      padding:10px 12px;
      display:flex; align-items:center; justify-content:space-between;
      border-top:1px solid var(--line);
      color:var(--mut); font-size:12px;
    }
    .empty{
      padding:16px;
      color:var(--mut);
      font-weight:800;
    }
    .pager{padding:12px}
    .pager :is(a,span){
      display:inline-flex; align-items:center; justify-content:center;
      padding:8px 10px; border-radius:10px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.05);
      color:var(--ink);
      text-decoration:none;
      font-weight:900; font-size:12px;
      margin-right:6px;
    }
    .pager span[aria-current="page"]{border-color:rgba(225,29,72,.35); background:rgba(225,29,72,.14)}
    .mono{font-family:ui-monospace,Menlo,Consolas,monospace}
  </style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <div class="title">
      <h1>Facturas</h1>
      <div class="sub">Solicitudes de factura (estado de cuenta) y archivos ZIP generados.</div>
    </div>

    <div class="pillrow">
      <div class="pill"><span class="dot ok"></span><span class="mono">Cuenta:</span> {{ (int)($accountId ?? 0) }}</div>
      <div class="pill"><span class="dot"></span><span class="mono">Fuente:</span> {{ (string)($source ?? '—') }}</div>
    </div>
  </div>

  <div class="alerts">
    @if(!empty($error))
      <div class="alert warn">{{ $error }}</div>
    @endif
    @if(session('ok'))
      <div class="alert ok">{{ session('ok') }}</div>
    @endif
    @if($errors->any())
      <div class="alert bad">{{ $errors->first() }}</div>
    @endif
  </div>

  {{-- Filtros + Solicitar --}}
  <div class="grid">
    <div class="full">
      <label>Buscar (periodo o id)</label>
      <form method="GET" action="{{ request()->url() }}" style="display:grid;grid-template-columns:1fr;gap:10px;margin:0">
        <input name="q" value="{{ (string)($q ?? '') }}" placeholder="Ej. 2025-12 o 123" autocomplete="off">
        <input type="hidden" name="embed" value="1">
      </form>
    </div>

    <div>
      <label>Estatus</label>
      <form method="GET" action="{{ request()->url() }}" style="margin:0">
        <select name="status" onchange="this.form.submit()">
          @php $st = (string)($status ?? '') @endphp
          <option value="" @selected($st==='')>Todos</option>
          <option value="requested"   @selected($st==='requested')>Solicitada</option>
          <option value="in_progress" @selected($st==='in_progress')>En proceso</option>
          <option value="done"        @selected($st==='done')>Lista</option>
        </select>
        <input type="hidden" name="q" value="{{ (string)($q ?? '') }}">
        <input type="hidden" name="embed" value="1">
      </form>
    </div>

    <div>
      <label>Por página</label>
      <form method="GET" action="{{ request()->url() }}" style="margin:0">
        @php $pp = (int)($perPage ?? 10) @endphp
        <select name="per_page" onchange="this.form.submit()">
          @foreach([10,15,20,30,50] as $n)
            <option value="{{ $n }}" @selected($pp===$n)>{{ $n }}</option>
          @endforeach
        </select>
        <input type="hidden" name="q" value="{{ (string)($q ?? '') }}">
        <input type="hidden" name="status" value="{{ (string)($status ?? '') }}">
        <input type="hidden" name="embed" value="1">
      </form>
    </div>

    <div class="full">
      <label>Solicitar factura por periodo</label>
      <form method="POST"
            action="{{ route('cliente.mi_cuenta.facturas.store', ['embed' => 1, 'q' => (string)($q ?? ''), 'status' => (string)($status ?? ''), 'per_page' => (int)($perPage ?? 10)]) }}"
            style="display:grid;grid-template-columns:1fr 1.2fr auto;gap:10px;margin:0">
        @csrf
        <input type="hidden" name="embed" value="1">
        <input type="hidden" name="q" value="{{ (string)($q ?? '') }}">
        <input type="hidden" name="status" value="{{ (string)($status ?? '') }}">
        <input type="hidden" name="per_page" value="{{ (int)($perPage ?? 10) }}">
        @csrf
        <input name="period" value="{{ old('period', now()->format('Y-m')) }}" placeholder="YYYY-MM" class="mono" required>
        <input name="notes" value="{{ old('notes') }}" placeholder="Notas (opcional)">
        <button type="submit">Solicitar</button>
      </form>
      <div class="sub" style="margin-top:8px">Al solicitar, se genera el ZIP de la factura/estado de cuenta cuando esté listo.</div>
    </div>
  </div>

  <div class="card">
    @php
      $iter = $rows instanceof \Illuminate\Pagination\LengthAwarePaginator
        ? $rows
        : null;

      $items = $iter ? $iter->items() : (is_iterable($rows) ? $rows : []);
      $items = is_array($items) ? $items : iterator_to_array($items);
    @endphp

    @if(empty($items))
      <div class="empty">Aún no hay solicitudes registradas.</div>
    @else
      <table>
        <thead>
          <tr>
            <th style="width:90px">ID</th>
            <th style="width:140px">Periodo</th>
            <th style="width:160px">Estatus</th>
            <th>Notas</th>
            <th style="width:220px; text-align:right">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @foreach($items as $r)
          @php
            $sid = (int)($r->id ?? 0);
            $period = (string)($r->period ?? '—');
            $st = (string)($r->status ?? '—');

            $cls = 'warn';
            if (in_array($st, ['done','completed','invoiced'], true)) $cls='ok';
            if (in_array($st, ['requested','pending','solicitada'], true)) $cls='warn';
            if (in_array($st, ['failed','error','cancelled','canceled'], true)) $cls='bad';

            $notes = (string)($r->notes ?? '');
            $hasZip = (bool)($r->has_zip ?? false);

            $dl = route('cliente.mi_cuenta.facturas.download', ['id' => $sid, 'embed' => 1]);
            $show = route('cliente.mi_cuenta.facturas.show', ['id' => $sid, 'embed' => 1]);
          @endphp
          <tr>
            <td class="mono"><span class="v">#{{ $sid }}</span></td>
            <td class="mono">{{ $period }}</td>
            <td>
              <span class="status {{ $cls }}">
                <span class="dot {{ $cls==='ok'?'ok':($cls==='bad'?'bad':'warn') }}"></span>
                {{ strtoupper($st) }}
              </span>
            </td>
            <td style="color:rgba(229,231,235,.92)">{{ $notes !== '' ? $notes : '—' }}</td>
            <td>
              <div class="actions">
                <a class="a btn2" href="{{ $show }}" target="_blank" rel="noopener">Ver</a>
                <a class="a primary" href="{{ $dl }}" target="_blank" rel="noopener"
                   aria-disabled="{{ $hasZip ? 'false' : 'true' }}">
                   Descargar ZIP
                </a>
              </div>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>

      @if($iter)
        <div class="pager">
          {!! $iter->withQueryString()->links() !!}
        </div>
      @endif
    @endif

    <div class="foot">
      <div>Mostrando: <strong>{{ $iter ? $iter->count() : (is_countable($items) ? count($items) : 0) }}</strong></div>
      <div>Total: <strong>{{ $iter ? $iter->total() : (is_countable($items) ? count($items) : 0) }}</strong></div>
    </div>
  </div>

</div>
</body>
</html>
