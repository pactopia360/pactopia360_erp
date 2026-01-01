{{-- resources/views/cliente/mi_cuenta/facturas/modal.blade.php --}}
<!doctype html>
<html lang="es" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Facturas</title>

  <style>
    /* ==========================================================
      FACTURAS (iframe) — FORZADO MODO CLARO (v2.0)
      - SIN tokens dark
      - SIN fondos oscuros
      - Look: blanco, limpio, “Mi cuenta”
    ========================================================== */

    :root{
      --bg: #f6f7fb;
      --surface: #ffffff;

      --card: rgba(255,255,255,.98);
      --card2: rgba(255,255,255,.92);

      --line: rgba(15,23,42,.10);
      --line2: rgba(15,23,42,.08);

      --ink: #0f172a;
      --mut: #64748b;

      --ok: #10b981;
      --warn: #f59e0b;
      --bad: #ef4444;

      --accent: #e11d48;
      --accent2: rgba(225,29,72,.10);

      --shadow: 0 18px 55px rgba(15,23,42,.10);
      --shadow2: 0 10px 28px rgba(15,23,42,.08);

      --radius: 16px;
      --radius2: 12px;

      --input-bg: #ffffff;
      --input-ink: #0f172a;
      --input-ph: rgba(100,116,139,.75);
      --input-line: rgba(15,23,42,.14);

      --table-head: rgba(15,23,42,.03);
      --hover: rgba(15,23,42,.03);
    }

    *{ box-sizing:border-box }
    html,body{ height:100% }

    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
      color: var(--ink);
      background:
        radial-gradient(900px 520px at 12% -10%, var(--accent2), transparent 60%),
        radial-gradient(900px 520px at 88% 0%, rgba(37,99,235,.08), transparent 60%),
        linear-gradient(180deg, #ffffff 0%, var(--bg) 100%);
      padding:16px;
    }

    .wrap{ max-width:1100px; margin:0 auto; }

    /* ---------- Header card ---------- */
    .top{
      display:flex; gap:12px; align-items:flex-start; justify-content:space-between;
      padding:14px 14px 12px;
      border:1px solid var(--line);
      background: linear-gradient(180deg, var(--card), var(--card2));
      border-radius:var(--radius);
      box-shadow: var(--shadow);
    }

    .title{ min-width:0; }
    h1{ margin:0; font-size:18px; letter-spacing:.2px; }
    .sub{ margin-top:6px; color:var(--mut); font-size:13px; }

    .pillrow{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
    .pill{
      display:inline-flex; align-items:center; gap:8px;
      padding:7px 10px; border-radius:999px;
      background: rgba(15,23,42,.04);
      border:1px solid var(--line2);
      color:var(--ink);
      font-weight:950; font-size:12px;
      white-space:nowrap;
    }

    .dot{ width:8px; height:8px; border-radius:999px; background:var(--mut); }
    .dot.ok{ background:var(--ok); }
    .dot.warn{ background:var(--warn); }
    .dot.bad{ background:var(--bad); }
    .mono{ font-family: ui-monospace, Menlo, Consolas, monospace; }

    /* ---------- Alerts ---------- */
    .alerts{ margin:12px 0; display:grid; gap:8px; }
    .alert{
      padding:10px 12px;
      border-radius:14px;
      border:1px solid var(--line);
      background: rgba(15,23,42,.03);
      font-weight:950;
      font-size:13px;
    }
    .alert.ok{ border-color: rgba(16,185,129,.35); background: rgba(16,185,129,.10); }
    .alert.warn{ border-color: rgba(245,158,11,.35); background: rgba(245,158,11,.12); }
    .alert.bad{ border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.12); }

    /* ---------- Filters card ---------- */
    .grid{
      margin-top:12px;
      display:grid;
      grid-template-columns: 1.2fr .8fr .7fr auto;
      gap:10px;
      padding:12px;
      border:1px solid var(--line);
      border-radius:var(--radius);
      background: var(--surface);
      box-shadow: var(--shadow2);
    }

    @media (max-width: 860px){
      .grid{ grid-template-columns:1fr 1fr; }
      .grid .full{ grid-column:1 / -1; }
      .pillrow{ justify-content:flex-start; }
      .top{ flex-direction:column; }
    }

    label{
      display:block;
      color:var(--mut);
      font-size:12px;
      font-weight:950;
      margin:0 0 6px;
    }

    input,select,button{
      width:100%;
      padding:10px 12px;
      border-radius:12px;
      border:1px solid var(--input-line);
      background: var(--input-bg);
      color: var(--input-ink);
      outline:none;
      transition: box-shadow .15s ease, border-color .15s ease, transform .12s ease, filter .15s ease;
    }
    input::placeholder{ color: var(--input-ph); }

    input:focus,select:focus{
      border-color: rgba(37,99,235,.55);
      box-shadow: 0 0 0 4px rgba(37,99,235,.14);
    }

    /* Botón claro (no oscuro) */
    button{
      cursor:pointer;
      font-weight:950;
      border-color: rgba(225,29,72,.28);
      background: linear-gradient(135deg, rgba(225,29,72,.18), rgba(225,29,72,.08));
      color: var(--ink);
    }
    button:hover{ filter:brightness(1.03); transform: translateY(-1px); }
    button:active{ transform: translateY(0); }

    /* ---------- Table card ---------- */
    .card{
      margin-top:12px;
      border:1px solid var(--line);
      border-radius:var(--radius);
      background: var(--surface);
      box-shadow: var(--shadow);
      overflow:hidden;
    }

    table{ width:100%; border-collapse:collapse; }
    thead th{
      text-align:left;
      font-size:12px;
      color:var(--mut);
      padding:12px;
      border-bottom:1px solid var(--line);
      background: var(--table-head);
      font-weight:950;
      letter-spacing:.2px;
    }
    tbody td{
      padding:12px;
      border-bottom:1px solid var(--line2);
      vertical-align:middle;
      font-size:13px;
    }
    tbody tr:hover{ background: var(--hover); }

    .status{
      display:inline-flex; align-items:center; gap:8px;
      padding:6px 10px; border-radius:999px;
      border:1px solid var(--line);
      background: rgba(15,23,42,.04);
      font-weight:950; font-size:12px;
      white-space:nowrap;
    }
    .status.ok{ border-color: rgba(16,185,129,.35); background: rgba(16,185,129,.10); }
    .status.warn{ border-color: rgba(245,158,11,.35); background: rgba(245,158,11,.12); }
    .status.bad{ border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.12); }

    .actions{ display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }

    .a{
      display:inline-flex; align-items:center; justify-content:center;
      padding:8px 10px; border-radius:12px;
      border:1px solid var(--line);
      background: rgba(15,23,42,.04);
      color: var(--ink);
      text-decoration:none;
      font-weight:950; font-size:12px;
      white-space:nowrap;
      transition: transform .12s ease, filter .15s ease;
    }
    .a:hover{ filter:brightness(1.02); transform: translateY(-1px); }
    .a:active{ transform: translateY(0); }

    .a.primary{
      border-color: rgba(225,29,72,.28);
      background: rgba(225,29,72,.10);
    }
    .a[aria-disabled="true"]{ opacity:.45; pointer-events:none; }

    .empty{ padding:16px; color:var(--mut); font-weight:950; }

    .foot{
      padding:10px 12px;
      display:flex; align-items:center; justify-content:space-between;
      border-top:1px solid var(--line);
      color:var(--mut); font-size:12px;
      background: var(--surface);
    }

    .pager{ padding:12px; }
    .pager :is(a,span){
      display:inline-flex; align-items:center; justify-content:center;
      padding:8px 10px; border-radius:10px;
      border:1px solid var(--line);
      background: rgba(15,23,42,.04);
      color: var(--ink);
      text-decoration:none;
      font-weight:950; font-size:12px;
      margin-right:6px;
    }
    .pager span[aria-current="page"]{
      border-color: rgba(225,29,72,.28);
      background: rgba(225,29,72,.12);
    }
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

  @php
    /** @var \Illuminate\Pagination\LengthAwarePaginator|null $p */
    $p = ($rows instanceof \Illuminate\Pagination\LengthAwarePaginator) ? $rows : null;
    $items = $p ? $p->items() : (is_iterable($rows) ? $rows : []);
    $items = is_array($items) ? $items : iterator_to_array($items);

    $st = (string)($status ?? '');
    $pp = (int)($perPage ?? 10);
  @endphp

  <div class="grid">
    <div class="full">
      <label>Buscar (periodo o id)</label>
      <form method="GET" action="{{ request()->url() }}" style="display:grid;gap:10px;margin:0">
        <input name="q" value="{{ (string)($q ?? '') }}" placeholder="Ej. 2025-12 o 123" autocomplete="off">
        <input type="hidden" name="status" value="{{ $st }}">
        <input type="hidden" name="per_page" value="{{ $pp }}">
        <input type="hidden" name="embed" value="1">
        <input type="hidden" name="theme" value="light">
      </form>
    </div>

    <div>
      <label>Estatus</label>
      <form method="GET" action="{{ request()->url() }}" style="margin:0">
        <select name="status" onchange="this.form.submit()">
          <option value="" @selected($st==='')>Todos</option>
          <option value="requested"   @selected($st==='requested')>Solicitada</option>
          <option value="in_progress" @selected($st==='in_progress')>En proceso</option>
          <option value="done"        @selected($st==='done')>Lista</option>
        </select>
        <input type="hidden" name="q" value="{{ (string)($q ?? '') }}">
        <input type="hidden" name="per_page" value="{{ $pp }}">
        <input type="hidden" name="embed" value="1">
        <input type="hidden" name="theme" value="light">
      </form>
    </div>

    <div>
      <label>Por página</label>
      <form method="GET" action="{{ request()->url() }}" style="margin:0">
        <select name="per_page" onchange="this.form.submit()">
          @foreach([10,15,20,30,50] as $n)
            <option value="{{ $n }}" @selected($pp===$n)>{{ $n }}</option>
          @endforeach
        </select>
        <input type="hidden" name="q" value="{{ (string)($q ?? '') }}">
        <input type="hidden" name="status" value="{{ $st }}">
        <input type="hidden" name="embed" value="1">
        <input type="hidden" name="theme" value="light">
      </form>
    </div>

    <div class="full">
      <label>Solicitar factura por periodo</label>
      <form method="POST" action="{{ route('cliente.mi_cuenta.facturas.store') }}" style="display:grid;grid-template-columns:1fr 1.2fr auto;gap:10px;margin:0">
        @csrf
        <input name="period" value="{{ old('period', now()->format('Y-m')) }}" placeholder="YYYY-MM" class="mono" required>
        <input name="notes" value="{{ old('notes') }}" placeholder="Notas (opcional)">
        <button type="submit">Solicitar</button>
      </form>
      <div class="sub" style="margin-top:8px">Cuando esté lista, podrás descargar el ZIP desde la fila correspondiente.</div>
    </div>
  </div>

  <div class="card">
    @if(empty($items))
      <div class="empty">Aún no hay solicitudes registradas.</div>
    @else
      <table>
        <thead>
          <tr>
            <th style="width:90px">ID</th>
            <th style="width:140px">Periodo</th>
            <th style="width:170px">Estatus</th>
            <th>Notas</th>
            <th style="width:230px; text-align:right">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @foreach($items as $r)
          @php
            $sid = (int)($r->id ?? 0);
            $period = (string)($r->period ?? '—');
            $s = (string)($r->status ?? 'requested');

            $cls = 'warn';
            if (in_array($s, ['done','completed','invoiced'], true)) $cls='ok';
            if (in_array($s, ['requested','pending','solicitada'], true)) $cls='warn';
            if (in_array($s, ['failed','error','cancelled','canceled'], true)) $cls='bad';

            $notes = (string)($r->notes ?? '');
            $hasZip = (bool)($r->has_zip ?? false);

            $dl = route('cliente.mi_cuenta.facturas.download', ['id' => $sid, 'embed' => 1, 'theme' => 'light']);
            $show = route('cliente.mi_cuenta.facturas.show', ['id' => $sid, 'embed' => 1, 'theme' => 'light']);
          @endphp
          <tr>
            <td class="mono"><strong>#{{ $sid }}</strong></td>
            <td class="mono">{{ $period }}</td>
            <td>
              <span class="status {{ $cls }}"><span class="dot {{ $cls }}"></span>{{ strtoupper($s) }}</span>
            </td>
            <td>{{ $notes !== '' ? $notes : '—' }}</td>
            <td>
              <div class="actions">
                <a class="a" href="{{ $show }}" target="_blank" rel="noopener">Ver</a>
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

      @if($p)
        <div class="pager">
          {!! $p->withQueryString()->links() !!}
        </div>
      @endif
    @endif

    <div class="foot">
      <div>Mostrando: <strong>{{ $p ? $p->count() : (is_countable($items) ? count($items) : 0) }}</strong></div>
      <div>Total: <strong>{{ $p ? $p->total() : (is_countable($items) ? count($items) : 0) }}</strong></div>
    </div>
  </div>

</div>

<script>
  // Forzar claro siempre (aunque llegue theme=dark por accidente)
  (function(){
    document.documentElement.setAttribute('data-theme','light');
    document.documentElement.style.colorScheme = 'light';
  })();
</script>

</body>
</html>
