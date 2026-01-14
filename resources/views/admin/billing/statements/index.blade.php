{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\statements\index.blade.php --}}
<<<<<<< HEAD
=======
{{-- UI v6.0 Â· Estados de cuenta (Admin) â€” rediseÃ±o completo: header/filters pro, KPI modernos, bulk actions, tabla premium, responsive --}}
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
@extends('layouts.admin')

@section('title','FacturaciÃ³n Â· Estados de cuenta')
@section('layout','full')

@php
  use Illuminate\Support\Facades\Route;

  // ===== Inputs (alineados con Controller) =====
  $q         = request('q','');
  $period    = request('period', now()->format('Y-m'));
  $accountId = request('accountId','');

  // Controller usa: all|pendiente|pagado|parcial|vencido|sin_mov
  $status    = request('status','all');
  if($status === '') $status = 'all';

  // Controller usa: perPage (no per_page)
  $perPage   = (int) request('perPage', 25);

  // ===== Data =====
  $rows = $rows ?? collect();
  $kpis = $kpis ?? [
    'cargo'    => 0,
    'abono'    => 0,
    'saldo'    => 0,
    'accounts' => 0,
    // Controller: paid_edo / paid_pay
    'paid_edo' => 0,
    'paid_pay' => 0,
  ];

  $fmtMoney = fn($n) => '$' . number_format((float)$n, 2);

  $routeIndex = route('admin.billing.statements.index');

  // rutas (alineadas a routes/admin.php)
<<<<<<< HEAD
  $hasShow   = Route::has('admin.billing.statements.show');
  $hasPdf    = Route::has('admin.billing.statements.pdf');
  $hasSendLegacy = Route::has('admin.billing.statements.email');
=======
  $hasShow   = Route::has('admin.billing.statements.show');   // GET /billing/statements/{accountId}/{period}
  $hasPdf    = Route::has('admin.billing.statements.pdf');    // GET /billing/statements/{accountId}/{period}/pdf

  // âœ… EnvÃ­o legacy (por fila)
  $hasSendLegacy = Route::has('admin.billing.statements.email'); // POST /billing/statements/{accountId}/{period}/email
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)

  // HUB
  $hasHub       = Route::has('admin.billing.statements_hub.index');
  $hasBulkSend  = Route::has('admin.billing.statements_hub.bulk_send');
  $hasAccounts  = Route::has('admin.billing.accounts.index');

  // paginator/collection friendly count
  $countRows = is_countable($rows) ? count($rows) : 0;

  // chips
  $chipBase = [
    'q' => $q,
    'period' => $period,
    'accountId' => $accountId,
    'perPage' => $perPage,
  ];
  $chipUrl = function(array $over) use ($routeIndex, $chipBase) {
    return $routeIndex . '?' . http_build_query(array_merge($chipBase, $over));
  };

  /**
   * Normaliza valores tipo "plan" a una etiqueta consistente.
   */
  $normPlan = function(?string $v): string {
    $v = strtolower(trim((string)$v));
    if ($v === '') return '—';
    $map = [
      'free' => 'FREE',
      'basic' => 'BASIC',
      'pro' => 'PRO',
      'premium' => 'PREMIUM',
      'enterprise' => 'ENTERPRISE',
    ];
    return $map[$v] ?? strtoupper($v);
  };

  /**
   * Normaliza modo de cobro a "MENSUAL" / "ANUAL" cuando aplique.
   * Si el modo viene mal (ej. "free"), se regresa vacío para NO duplicar.
   */
  $normModo = function(?string $modoRaw, ?string $planRaw): string {
    $m = strtolower(trim((string)$modoRaw));
    $p = strtolower(trim((string)$planRaw));

    if ($m === '' || $m === '—' || $m === '-') return '';

    if ($p !== '' && $m === $p) return '';
    if (in_array($m, ['free','basic','pro','premium','enterprise'], true)) return '';

    $mensual = ['mensual','monthly','month','mes','m','mo','1m'];
    $anual   = ['anual','annual','yearly','year','año','y','yr','1y','12m'];

    if (in_array($m, $mensual, true)) return 'MENSUAL';
    if (in_array($m, $anual, true)) return 'ANUAL';

    return strtoupper($m);
  };

  /**
   * Pill class para tarifa (base/ajuste/personalizado).
   */
  $tarifaPillClass = function(?string $pill): string {
    $p = strtolower(trim((string)$pill));
    // Controller típico: 'Base' / 'Ajuste (vigente)' / 'Ajuste (próximo periodo)' o 'info'
    if (str_contains($p, 'vigente') || $p === 'info') return 'sx-ok';
    if (str_contains($p, 'próximo') || str_contains($p, 'proximo') || str_contains($p, 'next')) return 'sx-warn';
    if ($p === 'base' || $p === 'dim' || $p === '') return 'sx-dim';
    return 'sx-dim';
  };
@endphp

@push('styles')
<style>
  .p360-page{ padding:0 !important; }

  /* ========= TOKENS ========= */
  :root{
    --sx-ink: var(--text, #0f172a);
    --sx-mut: var(--muted, #64748b);
    --sx-line: color-mix(in oklab, var(--sx-ink) 12%, transparent);
    --sx-line2: color-mix(in oklab, var(--sx-ink) 8%, transparent);
    --sx-card: var(--card-bg, #fff);
    --sx-bg: color-mix(in oklab, var(--sx-card) 88%, #f6f7fb);
    --sx-shadow: var(--shadow-1, 0 18px 40px rgba(15,23,42,.08));
    --sx-radius: 22px;
    --sx-radius2: 16px;
    --sx-accent: #7c3aed;
    --sx-ok: #16a34a;
    --sx-warn: #f59e0b;
    --sx-bad: #ef4444;
    --sx-info: #0ea5e9;
  }

  /* ========= WRAP ========= */
  .sx-wrap{ padding:16px; }
  .sx-card{
    background:var(--sx-card);
    border:1px solid var(--sx-line);
    border-radius:var(--sx-radius);
    box-shadow: var(--sx-shadow);
    overflow:hidden;
  }

  /* ========= HEADER ========= */
  .sx-head{
    padding:18px 18px 14px;
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    border-bottom:1px solid var(--sx-line);
    background:
      radial-gradient(1200px 260px at 10% 0%, color-mix(in oklab, var(--sx-accent) 12%, transparent), transparent 60%),
      linear-gradient(180deg, color-mix(in oklab, var(--sx-card) 94%, transparent), color-mix(in oklab, var(--sx-card) 98%, transparent));
  }
  .sx-title{ margin:0; font-size:18px; font-weight:950; letter-spacing:-.01em; color:var(--sx-ink); }
  .sx-sub{ margin-top:6px; color:var(--sx-mut); font-weight:850; font-size:12px; max-width:980px; line-height:1.35; }
  .sx-head-actions{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }

  /* ========= FILTERS ========= */
  .sx-filters{
    padding:12px 18px 16px;
    border-bottom:1px solid var(--sx-line);
    background: color-mix(in oklab, var(--sx-card) 96%, transparent);
  }

  .sx-grid{
    display:grid;
    grid-template-columns: 1.4fr 160px 170px 180px 170px auto;
    gap:10px;
    align-items:end;
  }
  @media(max-width: 1200px){
    .sx-grid{ grid-template-columns: 1fr 1fr; }
  }

  .sx-ctl label{
    display:block;
    font-size:12px;
    color:var(--sx-mut);
    font-weight:950;
    margin-bottom:6px;
  }

  .sx-in, .sx-sel{
    width:100%;
    padding:10px 12px;
    border-radius:14px;
    border:1px solid var(--sx-line);
    background:transparent;
    color:var(--sx-ink);
    font-weight:900;
    outline:none;
  }
  .sx-in:focus, .sx-sel:focus{
    border-color: color-mix(in oklab, var(--sx-accent) 40%, var(--sx-line));
    box-shadow:0 0 0 3px color-mix(in oklab, var(--sx-accent) 18%, transparent);
  }

  .sx-btn{
    padding:10px 12px;
    border-radius:14px;
    border:1px solid var(--sx-line);
    font-weight:950;
    cursor:pointer;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    white-space:nowrap;
    user-select:none;
  }
  .sx-btn-primary{
    background:var(--sx-ink);
    color:#fff;
    border-color: color-mix(in oklab, var(--sx-ink) 35%, var(--sx-line));
  }
  html[data-theme="dark"] .sx-btn-primary{
    background:#111827;
    border-color: rgba(255,255,255,.14);
  }
  .sx-btn-soft{
    background: color-mix(in oklab, var(--sx-card) 92%, transparent);
    color:var(--sx-ink);
  }
  .sx-btn-ghost{
    background:transparent;
    color:var(--sx-ink);
  }
  .sx-btn:disabled{ opacity:.55; cursor:not-allowed; }

  /* ========= CHIPS ========= */
  .sx-chips{
    margin-top:10px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
  }
  .sx-chip{
    border:1px solid var(--sx-line);
    background: color-mix(in oklab, var(--sx-card) 94%, transparent);
    color:var(--sx-ink);
    font-weight:950;
    font-size:12px;
    padding:7px 10px;
    border-radius:999px;
    text-decoration:none;
    display:inline-flex;
    gap:8px;
    align-items:center;
    cursor:pointer;
    user-select:none;
  }
  .sx-chip .dot{
    width:8px; height:8px; border-radius:999px;
    background: color-mix(in oklab, var(--sx-ink) 25%, transparent);
  }
  .sx-chip.on{
    background:var(--sx-ink);
    color:#fff;
    border-color: color-mix(in oklab, var(--sx-ink) 25%, var(--sx-line));
  }
  .sx-chip.on .dot{ background: rgba(255,255,255,.8); }
  html[data-theme="dark"] .sx-chip.on{ background:#111827; border-color: rgba(255,255,255,.14); }

  /* ========= KPIs ========= */
  .sx-kpis{
    padding:16px 18px;
    display:grid;
    grid-template-columns: repeat(5, minmax(180px, 1fr));
    gap:10px;
    border-bottom:1px solid var(--sx-line);
    background: linear-gradient(180deg, color-mix(in oklab, var(--sx-card) 94%, transparent), transparent);
  }
  @media(max-width: 1200px){
    .sx-kpis{ grid-template-columns: repeat(2, minmax(180px, 1fr)); }
  }
  .sx-kpi{
    border:1px solid var(--sx-line2);
    border-radius:18px;
    padding:12px;
    background:
      radial-gradient(120px 120px at 10% 0%, color-mix(in oklab, var(--sx-accent) 14%, transparent), transparent 60%),
      color-mix(in oklab, var(--sx-card) 92%, transparent);
  }
  .sx-k{ font-size:12px; color:var(--sx-mut); font-weight:950; text-transform:uppercase; letter-spacing:.05em; }
  .sx-v{ margin-top:6px; font-size:16px; font-weight:950; color:var(--sx-ink); }
  .sx-mini{ margin-top:6px; font-size:12px; color:var(--sx-mut); font-weight:850; line-height:1.35; }
  .sx-mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,"Liberation Mono","Courier New", monospace; font-weight:900; }

  /* ========= TABLE / LIST ========= */
  .sx-body{ padding:16px 18px 18px; background: var(--sx-bg); }
  .sx-panel{
    background:var(--sx-card);
    border:1px solid var(--sx-line);
    border-radius:20px;
    overflow:hidden;
  }

  .sx-bulkbar{
    display:none;
    padding:12px 14px;
    border-bottom:1px solid var(--sx-line);
    background:
      linear-gradient(90deg, color-mix(in oklab, var(--sx-accent) 10%, var(--sx-card)), color-mix(in oklab, var(--sx-card) 98%, transparent));
    gap:10px;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
  }
  .sx-bulkbar.on{ display:flex; }
  .sx-bulk-left{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .sx-badge{
    border:1px solid var(--sx-line);
    background: color-mix(in oklab, var(--sx-card) 92%, transparent);
    border-radius:999px;
    padding:7px 10px;
    font-weight:950;
    color:var(--sx-ink);
  }
  .sx-bulk-note{ font-size:12px; color:var(--sx-mut); font-weight:850; }

  .sx-table-wrap{ width:100%; overflow:auto; }
  .sx-table{ width:100%; border-collapse:collapse; min-width: 1120px; }
  .sx-table th{
    padding:10px 12px;
    text-align:left;
    font-size:12px;
    color:var(--sx-mut);
    font-weight:950;
    text-transform:uppercase;
    letter-spacing:.05em;
    border-bottom:1px solid var(--sx-line);
    background: color-mix(in oklab, var(--sx-card) 96%, transparent);
    position:sticky; top:0; z-index:2;
    white-space:nowrap;
  }
  .sx-table td{
    padding:12px 12px;
    border-bottom:1px solid var(--sx-line2);
    color:var(--sx-ink);
    font-weight:850;
    vertical-align:top;
  }
  .sx-table tr:hover td{ background: color-mix(in oklab, var(--sx-card) 94%, transparent); }

  .sx-right{ text-align:right; }
  .sx-selcol{ width:44px; }
  .sx-ck{ width:16px; height:16px; accent-color:#111827; }
  html[data-theme="dark"] .sx-ck{ accent-color:#d1d5db; }

  .sx-name{ font-weight:950; }
  .sx-subrow{ margin-top:6px; font-size:12px; color:var(--sx-mut); font-weight:850; line-height:1.35; }

  .sx-ellipsis{
    max-width: 520px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    display:block;
  }

  .sx-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid transparent;
    font-weight:950;
    font-size:12px;
    white-space:nowrap;
  }
  .sx-pill .dot{ width:8px; height:8px; border-radius:999px; }

  .sx-ok{ background:#dcfce7; color:#166534; border-color:#bbf7d0; }
  .sx-ok .dot{ background:#16a34a; }
  .sx-warn{ background:#fef3c7; color:#92400e; border-color:#fde68a; }
  .sx-warn .dot{ background:#f59e0b; }
  .sx-bad{ background:#fee2e2; color:#991b1b; border-color:#fecaca; }
  .sx-bad .dot{ background:#ef4444; }
  .sx-dim{ background: color-mix(in oklab, var(--sx-ink) 6%, transparent); color:var(--sx-mut); border-color: var(--sx-line); }
  .sx-dim .dot{ background: color-mix(in oklab, var(--sx-ink) 30%, transparent); }

  .sx-actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
  .sx-actions .sx-btn{ padding:8px 10px; border-radius:999px; font-size:12px; }

  @media(max-width: 980px){
    .sx-table{ min-width: 0; }
    .sx-table thead{ display:none; }
    .sx-table, .sx-table tbody, .sx-table tr, .sx-table td{ display:block; width:100%; }
    .sx-table tr{ border-bottom:1px solid var(--sx-line); }
    .sx-table td{ border-bottom:0; }
    .sx-right{ text-align:left; }
    .sx-actions{ justify-content:flex-start; }
    .sx-ellipsis{ max-width: 100%; white-space:normal; }
  }

  .sx-msg{ margin:12px 18px 0; padding:10px 12px; border-radius:14px; font-weight:900; }
  .sx-msg.ok{ border:1px solid #bbf7d0; background:#dcfce7; color:#166534; }
  .sx-msg.err{ border:1px solid #fecaca; background:#fee2e2; color:#991b1b; }
</style>
@endpush

@section('content')
<div class="sx-wrap">
  <div class="sx-card">

    {{-- HEADER --}}
    <div class="sx-head">
      <div>
        <div class="sx-title">FacturaciÃ³n Â· Estados de cuenta</div>
        <div class="sx-sub">
<<<<<<< HEAD
          Periodo <span class="sx-mono">{{ $period }}</span>.
          “Total” = cargo del periodo (si no hay movimientos, se muestra el total esperado por licencia).
          Usa selección masiva para enviar correos o preparar la operación.
=======
          Periodo <span class="sx-mono">{{ $period }}</span>. â€œTotalâ€ = cargo del periodo (si no hay movimientos, se muestra el total esperado por licencia).
          Usa selecciÃ³n masiva para enviar correos o preparar la operaciÃ³n.
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
        </div>
      </div>

      <div class="sx-head-actions">
        @if($hasHub)
          <a class="sx-btn sx-btn-soft" href="{{ route('admin.billing.statements_hub.index') }}">Abrir HUB moderno</a>
        @endif
        @if($hasAccounts)
          <a class="sx-btn sx-btn-soft" href="{{ route('admin.billing.accounts.index') }}">Cuentas (licencias)</a>
        @endif
      </div>
    </div>

    {{-- MENSAJES --}}
    @if(session('ok'))
      <div class="sx-msg ok">{{ session('ok') }}</div>
    @endif
    @if(session('warn'))
      <div class="sx-msg err" style="border-color:#fde68a;background:#fffbeb;color:#92400e;">{{ session('warn') }}</div>
    @endif
    @if($errors->any())
      <div class="sx-msg err">{{ $errors->first() }}</div>
    @endif

    {{-- FILTERS --}}
    <div class="sx-filters">
      <form method="GET" action="{{ $routeIndex }}" class="sx-grid">
        <div class="sx-ctl">
          <label>Buscar</label>
<<<<<<< HEAD
          <input class="sx-in" name="q" value="{{ $q }}" placeholder="ID, RFC, email, razón social...">
=======
          <input class="sx-in" name="q" value="{{ $q }}" placeholder="ID, RFC, email, razÃ³n social, UUID...">
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
        </div>

        <div class="sx-ctl">
          <label>Periodo</label>
          <input class="sx-in" name="period" value="{{ $period }}" placeholder="YYYY-MM">
        </div>

        <div class="sx-ctl">
          <label>Cuenta (ID)</label>
          <input class="sx-in" name="accountId" value="{{ $accountId }}" placeholder="ID exacto">
        </div>

        <div class="sx-ctl">
          <label>Estatus</label>
          <select class="sx-sel" name="status">
            <option value="all" {{ $status==='all'?'selected':'' }}>Todos</option>
            <option value="pagado" {{ $status==='pagado'?'selected':'' }}>Pagado</option>
            <option value="pendiente" {{ $status==='pendiente'?'selected':'' }}>Pendiente</option>
            <option value="parcial" {{ $status==='parcial'?'selected':'' }}>Parcial</option>
            <option value="vencido" {{ $status==='vencido'?'selected':'' }}>Vencido</option>
            <option value="sin_mov" {{ $status==='sin_mov'?'selected':'' }}>Sin mov</option>
          </select>
        </div>

        <div class="sx-ctl">
<<<<<<< HEAD
          <label>Por página</label>
          <select class="sx-sel" name="perPage">
            @foreach([25,50,100,250,500,1000] as $n)
              <option value="{{ $n }}" {{ (int)$perPage===(int)$n?'selected':'' }}>{{ $n }}</option>
=======
          <label>Por pÃ¡gina</label>
          <select class="sx-sel" name="per_page">
            @foreach([10,25,50,100,200] as $n)
              <option value="{{ $n }}" {{ $perPage===$n?'selected':'' }}>{{ $n }}</option>
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
            @endforeach
          </select>
        </div>

        <div class="sx-ctl">
          <label>&nbsp;</label>
          <button class="sx-btn sx-btn-primary" type="submit">Filtrar</button>
        </div>
      </form>

      <div class="sx-chips">
        <a class="sx-chip {{ $status==='all'?'on':'' }}" href="{{ $chipUrl(['status'=>'all']) }}"><span class="dot"></span> Todos</a>
        <a class="sx-chip {{ $status==='pendiente'?'on':'' }}" href="{{ $chipUrl(['status'=>'pendiente']) }}"><span class="dot"></span> Pendientes</a>
        <a class="sx-chip {{ $status==='parcial'?'on':'' }}" href="{{ $chipUrl(['status'=>'parcial']) }}"><span class="dot"></span> Parciales</a>
        <a class="sx-chip {{ $status==='pagado'?'on':'' }}" href="{{ $chipUrl(['status'=>'pagado']) }}"><span class="dot"></span> Pagados</a>
        <a class="sx-chip {{ $status==='vencido'?'on':'' }}" href="{{ $chipUrl(['status'=>'vencido']) }}"><span class="dot"></span> Vencidos</a>
        <a class="sx-chip {{ $status==='sin_mov'?'on':'' }}" href="{{ $chipUrl(['status'=>'sin_mov']) }}"><span class="dot"></span> Sin mov</a>

        <span style="margin-left:auto; color:var(--sx-mut); font-weight:850; font-size:12px;">
          Mostrando: <span class="sx-mono">{{ $countRows }}</span>
        </span>
      </div>
    </div>

    {{-- KPIs --}}
    <div class="sx-kpis">
      <div class="sx-kpi">
        <div class="sx-k">Total</div>
        <div class="sx-v">{{ $fmtMoney($kpis['cargo'] ?? 0) }}</div>
        <div class="sx-mini">Cargos del periodo (o esperado por licencia si aplica).</div>
      </div>

      <div class="sx-kpi">
        <div class="sx-k">Pagado</div>
        <div class="sx-v">{{ $fmtMoney($kpis['abono'] ?? 0) }}</div>
        <div class="sx-mini">
<<<<<<< HEAD
          EdoCta: <span class="sx-mono">{{ $fmtMoney($kpis['paid_edo'] ?? 0) }}</span>
          · Payments: <span class="sx-mono">{{ $fmtMoney($kpis['paid_pay'] ?? 0) }}</span>
=======
          @if(isset($kpis['edocta']) || isset($kpis['payments']))
            EdoCta: <span class="sx-mono">{{ $fmtMoney($kpis['edocta'] ?? 0) }}</span> Â· Payments: <span class="sx-mono">{{ $fmtMoney($kpis['payments'] ?? 0) }}</span>
          @else
            Total abonado acumulado del periodo.
          @endif
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
        </div>
      </div>

      <div class="sx-kpi">
        <div class="sx-k">Saldo</div>
        <div class="sx-v">{{ $fmtMoney($kpis['saldo'] ?? 0) }}</div>
        <div class="sx-mini">Saldo pendiente considerando pagos.</div>
      </div>

      <div class="sx-kpi">
        <div class="sx-k">Cuentas</div>
        <div class="sx-v">{{ (int)($kpis['accounts'] ?? 0) }}</div>
        <div class="sx-mini">Total de cuentas en el resultado.</div>
      </div>

      <div class="sx-kpi">
        <div class="sx-k">OperaciÃ³n</div>
        <div class="sx-v" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
          <button class="sx-btn sx-btn-soft" type="button" onclick="sxSelectAll(true)">Todo</button>
          <button class="sx-btn sx-btn-soft" type="button" onclick="sxSelectAll(false)">Nada</button>
          <button class="sx-btn sx-btn-primary" type="button" onclick="sxBulkSend()">Enviar correo (bulk)</button>
        </div>
        <div class="sx-mini">
          @if($hasBulkSend)
<<<<<<< HEAD
            Envío masivo vía HUB.
=======
            EnvÃ­o masivo vÃ­a HUB: <span class="sx-mono">billing/statements-hub/bulk/send</span>.
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
          @else
            Tip: activa el endpoint HUB para habilitar envÃ­o masivo real.
          @endif
        </div>
      </div>
    </div>

    {{-- BODY --}}
    <div class="sx-body">
      <div class="sx-panel">

        {{-- BULK BAR --}}
        <div id="sxBulkbar" class="sx-bulkbar">
          <div class="sx-bulk-left">
            <div class="sx-badge"><span id="sxBulkCount">0</span> seleccionadas</div>
            <div class="sx-bulk-note">Periodo <span class="sx-mono">{{ $period }}</span></div>
          </div>

          <div class="sx-actions">
            <button class="sx-btn sx-btn-ghost" type="button" onclick="sxClear()">Limpiar</button>
            <button class="sx-btn sx-btn-primary" type="button" onclick="sxBulkSend()">Enviar correos</button>
          </div>

          {{-- Form oculto para bulk real (HUB) --}}
          <form id="sxBulkForm"
                method="POST"
                action="{{ $hasBulkSend ? route('admin.billing.statements_hub.bulk_send') : '' }}"
                style="display:none;">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            {{-- âœ… account_ids se envÃ­a como STRING (CSV) para compatibilidad con validaciÃ³n string en HUB --}}
            <input type="hidden" name="account_ids" value="">
          </form>
        </div>

        {{-- TABLE --}}
<<<<<<< HEAD
        <div class="sx-table-wrap">
          <table class="sx-table">
            <thead>
              <tr>
                <th class="sx-selcol">
                  <input class="sx-ck" type="checkbox" id="sxCkAll" onclick="sxToggleAll(this)">
                </th>
                <th style="width:90px">Cuenta</th>
                <th style="min-width:360px">Cliente</th>
                <th style="width:340px">Contacto / Cobranza</th>
                <th class="sx-right" style="width:130px">Total</th>
                <th class="sx-right" style="width:150px">Pagado</th>
                <th class="sx-right" style="width:130px">Saldo</th>
                <th style="width:150px">Estatus</th>
                <th class="sx-right" style="width:260px">Acciones</th>
=======
        <table class="sx-table">
          <thead>
            <tr>
              <th class="sx-selcol">
                <input class="sx-ck" type="checkbox" id="sxCkAll" onclick="sxToggleAll(this)">
              </th>
              <th style="width:110px">Cuenta</th>
              <th>Cliente</th>
              <th style="width:260px">Email / Meta</th>
              <th class="sx-right" style="width:140px">Total</th>
              <th class="sx-right" style="width:140px">Pagado</th>
              <th class="sx-right" style="width:140px">Saldo</th>
              <th style="width:140px">Estatus</th>
              <th class="sx-right" style="width:280px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $r)
              @php
                $aid   = (string)($r->id ?? $r->account_id ?? '');
                $rfc   = (string)($r->rfc ?? $r->codigo ?? '');
                $name  = trim((string)(($r->razon_social ?? '') ?: ($r->name ?? '') ?: 'â€”'));
                $mail  = (string)($r->email ?? $r->correo ?? 'â€”');

                $plan  = (string)($r->plan_norm ?? $r->plan_actual ?? $r->plan ?? $r->plan_name ?? 'â€”');
                $modo  = (string)($r->modo_cobro ?? $r->billing_mode ?? $r->modo ?? 'â€”');

                $cargo = (float)($r->cargo ?? 0);
                $expected = (float)($r->expected_total ?? 0);
                $total = $cargo > 0 ? $cargo : $expected;

                $abono = (float)($r->abono ?? 0);
                $saldo = max(0, $total - $abono);

                $st = (string)($r->status_pago ?? $r->status ?? '');
                $st = strtolower(trim($st));
                if($st==='paid' || $st==='succeeded') $st = 'pagado';

                $lbl = $st==='pagado' ? 'PAGADO' : ($st==='pendiente' ? 'PENDIENTE' : ($st==='parcial' ? 'PARCIAL' : ($st==='vencido' ? 'VENCIDO' : 'SIN MOV')));

                $pillCls = 'sx-dim';
                if($st==='pagado') $pillCls='sx-ok';
                elseif($st==='vencido') $pillCls='sx-bad';
                elseif(in_array($st, ['pendiente','parcial'], true)) $pillCls='sx-warn';

                $saldoCls = $saldo > 0 ? 'sx-warn' : 'sx-ok';

                $showUrl = ($hasShow && $aid) ? route('admin.billing.statements.show', ['accountId'=>$aid, 'period'=>$period]) : null;
                $pdfUrl  = ($hasPdf  && $aid) ? route('admin.billing.statements.pdf',  ['accountId'=>$aid, 'period'=>$period]) : null;

                $emailUrl = ($hasSendLegacy && $aid) ? route('admin.billing.statements.email', ['accountId'=>$aid, 'period'=>$period]) : null;
              @endphp

              <tr>
                <td onclick="event.stopPropagation();">
                  <input class="sx-ck sx-row" type="checkbox" value="{{ e($aid) }}" onclick="sxSync()">
                </td>

                <td class="sx-mono">
                  #{{ $aid }}
                  @if($rfc)
                    <div class="sx-subrow">RFC: <span class="sx-mono">{{ $rfc }}</span></div>
                  @endif
                </td>

                <td>
                  <div class="sx-name">{{ $name }}</div>
                  <div class="sx-subrow">
                    <span class="sx-pill sx-dim"><span class="dot"></span> {{ strtoupper(trim($plan ?: 'â€”')) }}</span>
                    <span class="sx-pill sx-dim" style="margin-left:6px;"><span class="dot"></span> {{ $modo ?: 'â€”' }}</span>
                  </div>
                </td>

                <td>
                  <div class="sx-mono">{{ $mail }}</div>
                  <div class="sx-subrow">
                    Periodo: <span class="sx-mono">{{ (string)($r->period ?? $period) }}</span>
                    @if(!empty($r->pago_metodo))
                      <span style="opacity:.55;">Â·</span> Pago: <span class="sx-mono">{{ (string)$r->pago_metodo }}</span>
                    @endif
                    @if(!empty($r->last_paid_at))
                      <br>Ãšlt. pago: <span class="sx-mono">{{ (string)$r->last_paid_at }}</span>
                    @endif
                  </div>
                </td>

                <td class="sx-right sx-mono">{{ $fmtMoney($total) }}</td>
                <td class="sx-right sx-mono">{{ $fmtMoney($abono) }}</td>

                <td class="sx-right">
                  <span class="sx-pill {{ $saldoCls }}"><span class="dot"></span><span class="sx-mono">{{ $fmtMoney($saldo) }}</span></span>
                </td>

                <td>
                  <span class="sx-pill {{ $pillCls }}"><span class="dot"></span>{{ $lbl }}</span>
                </td>

                <td class="sx-right">
                  <div class="sx-actions">
                    @if($showUrl)
                      <a class="sx-btn sx-btn-primary" href="{{ $showUrl }}">Ver detalle</a>
                    @else
                      <button class="sx-btn sx-btn-primary" type="button" disabled title="Falta ruta show">Ver detalle</button>
                    @endif

                    @if($pdfUrl)
                      <a class="sx-btn sx-btn-soft" target="_blank" href="{{ $pdfUrl }}">PDF</a>
                    @else
                      <button class="sx-btn sx-btn-soft" type="button" disabled title="Falta ruta pdf">PDF</button>
                    @endif

                    @if($emailUrl)
                      <form method="POST" action="{{ $emailUrl }}">
                        @csrf
                        {{-- opcional: si tu controller admite 'to', aquÃ­ lo mandamos por defecto --}}
                        <input type="hidden" name="to" value="{{ $mail !== 'â€”' ? $mail : '' }}">
                        <button class="sx-btn sx-btn-soft" type="submit">Enviar</button>
                      </form>
                    @else
                      <button class="sx-btn sx-btn-soft" type="button" disabled title="Falta ruta statements.email">Enviar</button>
                    @endif
                  </div>
                </td>
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
              </tr>
            </thead>
            <tbody>
              @forelse($rows as $r)
                @php
                  $aid   = (string)($r->id ?? $r->account_id ?? '');
                  $rfc   = (string)($r->rfc ?? $r->codigo ?? '');
                  $name  = trim((string)(($r->razon_social ?? '') ?: ($r->name ?? '') ?: '—'));
                  $mail  = (string)($r->email ?? $r->correo ?? '—');

                  $planRaw = (string)($r->plan_norm ?? $r->plan_actual ?? $r->plan ?? $r->plan_name ?? '—');
                  $plan    = $normPlan($planRaw);

                  $modoRaw = (string)($r->modo_cobro ?? $r->billing_cycle ?? $r->billing_mode ?? $r->modo ?? '');
                  $modo    = $normModo($modoRaw, $planRaw);

                  $isBlocked = (int)($r->is_blocked ?? 0) === 1;
                  $estadoCuenta = strtolower(trim((string)($r->estado_cuenta ?? '')));
                  $estadoLabel  = $estadoCuenta !== '' ? strtoupper($estadoCuenta) : '';

                  $cargo    = (float)($r->cargo ?? 0);
                  $expected = (float)($r->expected_total ?? 0);
                  $total    = $cargo > 0 ? $cargo : $expected;

                  $abono = (float)($r->abono ?? 0);
                  $saldo = max(0, $total - $abono);

                  $abonoEdo = (float)($r->abono_edo ?? 0);
                  $abonoPay = (float)($r->abono_pay ?? 0);

                  $st = (string)($r->status_pago ?? $r->status ?? '');
                  $st = strtolower(trim($st));
                  if($st==='paid' || $st==='succeeded') $st = 'pagado';

                  $lbl = $st==='pagado' ? 'PAGADO' : ($st==='pendiente' ? 'PENDIENTE' : ($st==='parcial' ? 'PARCIAL' : ($st==='vencido' ? 'VENCIDO' : 'SIN MOV')));

                  $pillCls = 'sx-dim';
                  if($st==='pagado') $pillCls='sx-ok';
                  elseif($st==='vencido') $pillCls='sx-bad';
                  elseif(in_array($st, ['pendiente','parcial'], true)) $pillCls='sx-warn';

                  $saldoCls = $saldo > 0 ? 'sx-warn' : 'sx-ok';

                  // Tarifa
                  $tarifaLabel = (string)($r->tarifa_label ?? '');
                  $tarifaPill  = (string)($r->tarifa_pill ?? '');
                  $tarifaCls   = $tarifaPillClass($tarifaPill);

                  // Pago meta (Controller)
                  $payMethod = (string)($r->pay_method ?? '');
                  $payProvider = (string)($r->pay_provider ?? '');
                  $payStatus = (string)($r->pay_status ?? '');
                  $payDue   = (string)($r->pay_due_date ?? '');
                  $payLast  = (string)($r->pay_last_paid_at ?? '');

                  $showUrl = ($hasShow && $aid) ? route('admin.billing.statements.show', ['accountId'=>$aid, 'period'=>$period]) : null;
                  $pdfUrl  = ($hasPdf  && $aid) ? route('admin.billing.statements.pdf',  ['accountId'=>$aid, 'period'=>$period]) : null;
                  $emailUrl = ($hasSendLegacy && $aid) ? route('admin.billing.statements.email', ['accountId'=>$aid, 'period'=>$period]) : null;
                @endphp

                <tr>
                  <td onclick="event.stopPropagation();">
                    <input class="sx-ck sx-row" type="checkbox" value="{{ e($aid) }}" onclick="sxSync()">
                  </td>

                  <td class="sx-mono">
                    #{{ $aid }}
                    @if($rfc)
                      <div class="sx-subrow">RFC: <span class="sx-mono">{{ $rfc }}</span></div>
                    @endif
                  </td>

                  <td>
                    <div class="sx-name sx-ellipsis" title="{{ $name }}">{{ $name }}</div>

                    <div class="sx-subrow" style="display:flex;gap:6px;flex-wrap:wrap;">
                      <span class="sx-pill sx-dim"><span class="dot"></span> {{ $plan }}</span>

                      @if($modo !== '')
                        <span class="sx-pill sx-dim"><span class="dot"></span> {{ $modo }}</span>
                      @endif

                      @if($tarifaLabel !== '')
                        <span class="sx-pill {{ $tarifaCls }}"><span class="dot"></span> {{ strtoupper($tarifaLabel) }}</span>
                      @endif

                      @if($estadoLabel !== '')
                        <span class="sx-pill sx-dim"><span class="dot"></span> {{ $estadoLabel }}</span>
                      @endif

                      @if($isBlocked)
                        <span class="sx-pill sx-bad"><span class="dot"></span> BLOQUEADA</span>
                      @endif
                    </div>
                  </td>

                  <td>
                    <div class="sx-mono sx-ellipsis" title="{{ $mail }}">{{ $mail }}</div>
                    <div class="sx-subrow">
                      Periodo: <span class="sx-mono">{{ (string)($r->period ?? $period) }}</span>

                      @if($payMethod !== '')
                        <span style="opacity:.55;">·</span> Método: <span class="sx-mono">{{ $payMethod }}</span>
                      @endif

                      @if($payProvider !== '')
                        <span style="opacity:.55;">·</span> Prov: <span class="sx-mono">{{ $payProvider }}</span>
                      @endif

                      @if($payStatus !== '')
                        <span style="opacity:.55;">·</span> St: <span class="sx-mono">{{ $payStatus }}</span>
                      @endif

                      @if($payDue !== '')
                        <br>Vence: <span class="sx-mono">{{ $payDue }}</span>
                      @endif

                      @if($payLast !== '')
                        <br>Últ. pago: <span class="sx-mono">{{ $payLast }}</span>
                      @endif
                    </div>
                  </td>

                  <td class="sx-right sx-mono">{{ $fmtMoney($total) }}</td>

                  <td class="sx-right">
                    <div class="sx-mono">{{ $fmtMoney($abono) }}</div>
                    <div class="sx-subrow">
                      EdoCta: <span class="sx-mono">{{ $fmtMoney($abonoEdo) }}</span>
                      · Pay: <span class="sx-mono">{{ $fmtMoney($abonoPay) }}</span>
                    </div>
                  </td>

                  <td class="sx-right">
                    <span class="sx-pill {{ $saldoCls }}"><span class="dot"></span><span class="sx-mono">{{ $fmtMoney($saldo) }}</span></span>
                  </td>

                  <td>
                    <span class="sx-pill {{ $pillCls }}"><span class="dot"></span>{{ $lbl }}</span>
                  </td>

                  <td class="sx-right">
                    <div class="sx-actions">
                      @if($showUrl)
                        <a class="sx-btn sx-btn-primary" href="{{ $showUrl }}">Ver detalle</a>
                      @else
                        <button class="sx-btn sx-btn-primary" type="button" disabled title="Falta ruta show">Ver detalle</button>
                      @endif

                      @if($pdfUrl)
                        <a class="sx-btn sx-btn-soft" target="_blank" href="{{ $pdfUrl }}">PDF</a>
                      @else
                        <button class="sx-btn sx-btn-soft" type="button" disabled title="Falta ruta pdf">PDF</button>
                      @endif

                      @if($emailUrl)
                        <form method="POST" action="{{ $emailUrl }}">
                          @csrf
                          <input type="hidden" name="to" value="{{ $mail !== '—' ? $mail : '' }}">
                          <button class="sx-btn sx-btn-soft" type="submit">Enviar</button>
                        </form>
                      @else
                        <button class="sx-btn sx-btn-soft" type="button" disabled title="Falta ruta statements.email">Enviar</button>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td colspan="9" style="padding:16px; color:var(--sx-mut); font-weight:900;">Sin resultados para el filtro actual.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

      </div>
    </div>

  </div>
</div>

@push('scripts')
<script>
  (function(){
    const bulkbar   = document.getElementById('sxBulkbar');
    const bulkCount = document.getElementById('sxBulkCount');
    const ckAll     = document.getElementById('sxCkAll');

    const hasBulkEndpoint = @json((bool) $hasBulkSend);
    const bulkForm = document.getElementById('sxBulkForm');

    function rows(){ return Array.from(document.querySelectorAll('.sx-row')); }
    function selected(){ return rows().filter(x => x.checked).map(x => x.value); }

    function update(){
      const ids = selected();
      if (bulkCount) bulkCount.textContent = String(ids.length);
      if (bulkbar){
        if(ids.length > 0) bulkbar.classList.add('on');
        else bulkbar.classList.remove('on');
      }
      if(ckAll){
        const r = rows();
        if(!r.length){ ckAll.checked = false; ckAll.indeterminate = false; return; }
        const all = r.every(x => x.checked);
        const any = r.some(x => x.checked);
        ckAll.checked = all;
        ckAll.indeterminate = (!all && any);
      }
    }

    window.sxToggleAll = function(master){
      rows().forEach(x => x.checked = !!master.checked);
      update();
    };

    window.sxSync = function(){ update(); };

    window.sxSelectAll = function(on){
      rows().forEach(x => x.checked = !!on);
      if(ckAll){ ckAll.checked = !!on; ckAll.indeterminate = false; }
      update();
    };

    window.sxClear = function(){
      rows().forEach(x => x.checked = false);
      if(ckAll){ ckAll.checked = false; ckAll.indeterminate = false; }
      update();
    };

    function bindBulkForm(ids){
      if(!bulkForm) return false;
<<<<<<< HEAD
      bulkForm.querySelectorAll('input[name="account_ids[]"]').forEach(n => n.remove());
      ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'account_ids[]';
        inp.value = String(id);
        bulkForm.appendChild(inp);
      });
=======

      // âœ… HUB espera string -> enviamos CSV en account_ids
      const inp = bulkForm.querySelector('input[name="account_ids"]');
      if (inp) inp.value = ids.join(',');

>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
      return true;
    }

    window.sxBulkSend = function(){
      const ids = selected();
      if(!ids.length){
        alert('Selecciona al menos una cuenta.');
        return;
      }

      if(hasBulkEndpoint && bulkForm && bulkForm.getAttribute('action')){
        bindBulkForm(ids);
        bulkForm.submit();
        return;
      }

<<<<<<< HEAD
      const payload = { period: @json($period), account_ids: ids };
      try{ navigator.clipboard.writeText(JSON.stringify(payload)); }catch(e){}

      alert(
        'Cuentas seleccionadas: ' + ids.length +
        '\\n\\nSe copió al portapapeles (period + account_ids).\\nActiva el endpoint HUB bulk_send para envío masivo real.'
      );
=======
      // Fallback: copiar al portapapeles (sin backend)
      const payload = { period: @json($period), account_ids: ids.join(',') };
      try{ navigator.clipboard.writeText(JSON.stringify(payload)); }catch(e){}

      alert('Cuentas seleccionadas: ' + ids.length + '\n\nSe copiÃ³ al portapapeles (period + account_ids CSV).\nActiva el endpoint HUB bulk_send para envÃ­o masivo real.');
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
    };

    update();
  })();
</script>
@endpush
@endsection
