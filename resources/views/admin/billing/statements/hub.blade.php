{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\statements\hub.blade.php --}}
{{-- resources/views/admin/billing/statements/hub.blade.php (v4.0 · HUB moderno + rutas seguras + bulk completo + más datos visibles) --}}
@extends('layouts.admin')

@section('title', 'Facturación · HUB')
@section('layout', 'full')

@php
  use Illuminate\Support\Facades\Route;

  $tab = $tab ?? request('tab','statements');

  // filtros base
  $q = $q ?? request('q','');
  $period = $period ?? request('period', now()->format('Y-m'));
  $accountId = $accountId ?? request('accountId','');

  // filtros pro
  $status = request('status','');           // pagado|pendiente|sin_mov|vencido|parcial
  $saldoMin = request('saldo_min','');      // num
  $saldoMax = request('saldo_max','');      // num
  $plan = request('plan','');               // free|pro|premium|custom etc
  $modoCobro = request('modo','');          // mensual|anual
  $sent = request('sent','');               // never|today|7d|30d
  $from = request('from','');               // YYYY-MM-DD
  $to = request('to','');                   // YYYY-MM-DD
  $perPage = (int) request('per_page', 25);

  // data
  $rows = $rows ?? collect();
  $kpis = $kpis ?? ['cargo'=>0,'abono'=>0,'saldo'=>0,'accounts'=>0];

  $emails = $emails ?? collect();
  $payments = $payments ?? collect();
  $invoiceRequests = $invoiceRequests ?? collect();
  $invoices = $invoices ?? collect();

  $routeIndex = route('admin.billing.statements_hub.index');

  $fmtMoney = fn($n) => '$' . number_format((float)$n, 2);

  // Contadores
  $countStatements = is_countable($rows) ? count($rows) : 0;
  $countEmails = is_countable($emails) ? count($emails) : 0;
  $countPayments = is_countable($payments) ? count($payments) : 0;
  $countIR = is_countable($invoiceRequests) ? count($invoiceRequests) : 0;
  $countInv = is_countable($invoices) ? count($invoices) : 0;

  // rutas opcionales (no romper si no existen)
  $hasBulkSend     = Route::has('admin.billing.statements_hub.bulk_send');
  $hasBulkPayLinks = Route::has('admin.billing.statements_hub.bulk_paylinks');

  $hasSendEmail    = Route::has('admin.billing.statements_hub.send_email');
  $hasSchedule     = Route::has('admin.billing.statements_hub.schedule');
  $hasResend       = Route::has('admin.billing.statements_hub.resend');
  $hasPreview      = Route::has('admin.billing.statements_hub.preview_email');
  $hasPayLink      = Route::has('admin.billing.statements_hub.create_pay_link');
  $hasInvRequest   = Route::has('admin.billing.statements_hub.invoice_request');
  $hasInvStatus    = Route::has('admin.billing.statements_hub.invoice_status');
  $hasSaveInvoice  = Route::has('admin.billing.statements_hub.save_invoice');

  $hasAccountsShow  = Route::has('admin.billing.accounts.show');
  $hasAccountsIndex = Route::has('admin.billing.accounts.index');

  // legacy statements
  $hasLegacyStatementShow = Route::has('admin.billing.statements.show');
  $hasLegacyStatementPdf  = Route::has('admin.billing.statements.pdf');
@endphp

@push('styles')
<style>
  .p360-page{ padding:0 !important; }

  .hub{ padding:16px; }
  .card{
    background:var(--card-bg);
    border:1px solid var(--card-border);
    border-radius:22px;
    box-shadow: var(--shadow-1);
    overflow:hidden;
  }

  /* header */
  .head{
    padding:18px 18px 14px;
    display:flex;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    align-items:flex-end;
    border-bottom:1px solid rgba(15,23,42,.08);
    background: linear-gradient(180deg,
      color-mix(in oklab, var(--card-bg) 94%, transparent),
      color-mix(in oklab, var(--card-bg) 98%, transparent)
    );
  }
  .ttl{ margin:0;font-size:18px;font-weight:950;color:var(--text); letter-spacing:-.01em; }
  .sub{ margin-top:6px;color:var(--muted);font-weight:850;font-size:12px; max-width:980px; line-height:1.35; }

  /* layout blocks */
  .topbar{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    justify-content:space-between;
    padding:12px 18px;
    border-bottom:1px solid rgba(15,23,42,.08);
    background: color-mix(in oklab, var(--card-bg) 97%, transparent);
  }

  .filters{
    display:grid;
    grid-template-columns: 1fr 160px 160px 160px auto;
    gap:10px;
    align-items:end;
    width:100%;
    max-width: 1180px;
  }
  @media(max-width: 1100px){
    .filters{ grid-template-columns:1fr 1fr; max-width:none; }
  }

  .filters2{
    display:grid;
    grid-template-columns: repeat(8, minmax(140px, 1fr));
    gap:10px;
    align-items:end;
    width:100%;
    padding:12px 18px 16px;
    border-bottom:1px solid rgba(15,23,42,.08);
  }
  @media(max-width: 1100px){
    .filters2{ grid-template-columns:1fr 1fr; }
  }

  .ctl label{ display:block; font-size:12px; color:var(--muted); font-weight:900; margin-bottom:6px; }
  .in{
    width:100%;
    padding:10px 12px;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background:transparent;
    color:var(--text);
    font-weight:850;
    outline:none;
  }
  .in:focus{ box-shadow:0 0 0 2px rgba(124,58,237,.18); border-color: rgba(124,58,237,.40); }

  .btn{
    padding:10px 12px;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.14);
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
  .btn-dark{ background:var(--text); color:#fff; }
  html[data-theme="dark"] .btn-dark{ background:#111827; border-color:rgba(255,255,255,.12); }
  .btn-light{ background: color-mix(in oklab, var(--card-bg) 92%, transparent); color:var(--text); }
  .btn-ghost{ background:transparent; color:var(--text); }
  .btn:disabled{ opacity:.55; cursor:not-allowed; }

  /* tabs */
  .tabs{
    padding:12px 18px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    border-bottom:1px solid rgba(15,23,42,.08);
    background: color-mix(in oklab, var(--card-bg) 96%, transparent);
  }
  .tab{
    padding:9px 12px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    font-weight:950;
    color:var(--text);
    text-decoration:none;
    background:transparent;
    display:inline-flex;
    align-items:center;
    gap:8px;
  }
  .tab .cnt{
    font-size:12px;
    font-weight:950;
    padding:3px 8px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: color-mix(in oklab, var(--card-bg) 88%, transparent);
    color:var(--muted);
  }
  .tab.on{
    background:var(--text);
    color:#fff;
    border-color:rgba(15,23,42,.22);
  }
  .tab.on .cnt{
    border-color:rgba(255,255,255,.22);
    background:rgba(255,255,255,.14);
    color:#fff;
  }
  html[data-theme="dark"] .tab.on{ background:#111827; border-color:rgba(255,255,255,.12); }

  /* kpis */
  .kpis{
    padding:16px 18px;
    display:grid;
    grid-template-columns: repeat(5, minmax(160px, 1fr));
    gap:10px;
    border-bottom:1px solid rgba(15,23,42,.08);
  }
  @media(max-width: 1100px){
    .kpis{ grid-template-columns: repeat(2, minmax(160px, 1fr)); }
  }
  .kpi{
    border:1px solid rgba(15,23,42,.10);
    border-radius:16px;
    padding:12px;
    background: color-mix(in oklab, var(--card-bg) 92%, transparent);
  }
  .k{ font-size:12px;color:var(--muted);font-weight:950;text-transform:uppercase;letter-spacing:.04em; }
  .v{ font-size:16px;font-weight:950;color:var(--text); margin-top:6px; }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,"Liberation Mono","Courier New", monospace; font-weight:900; }
  .mut{ color:var(--muted); font-weight:850; font-size:12px; }
  .mut a{ color:inherit; text-decoration:underline; text-decoration-color: rgba(15,23,42,.18); }

  .msg{ margin:12px 18px 0; padding:10px 12px; border-radius:12px; font-weight:900; }
  .msg.ok{ border:1px solid #bbf7d0; background:#dcfce7; color:#166534; }
  .msg.err{ border:1px solid #fecaca; background:#fee2e2; color:#991b1b; }

  /* chips */
  .chips{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
  }
  .chip{
    border:1px solid rgba(15,23,42,.12);
    background: color-mix(in oklab, var(--card-bg) 92%, transparent);
    color:var(--text);
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
  .chip .dot{
    width:8px; height:8px; border-radius:999px;
    background: rgba(15,23,42,.25);
  }
  .chip.on{
    background: var(--text);
    color:#fff;
    border-color: rgba(15,23,42,.22);
  }
  .chip.on .dot{ background: rgba(255,255,255,.80); }
  html[data-theme="dark"] .chip.on{ background:#111827; border-color:rgba(255,255,255,.12); }

  /* table */
  .table{ padding:16px 18px 18px; }
  table.t{ width:100%; border-collapse:collapse; }
  .t th{
    text-align:left; padding:10px 12px; font-size:12px; color:var(--muted); font-weight:950;
    text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid rgba(15,23,42,.10);
    position:sticky; top:0; background: color-mix(in oklab, var(--card-bg) 96%, transparent); z-index:2;
  }
  .t td{ padding:12px; border-bottom:1px solid rgba(15,23,42,.07); vertical-align:top; color:var(--text); font-weight:800; }
  .t tr:hover td{ background: color-mix(in oklab, var(--card-bg) 90%, transparent); }
  .tright{ text-align:right; }
  .t .selcol{ width:42px; }
  .ck{ width:16px; height:16px; accent-color: #111827; }
  html[data-theme="dark"] .ck{ accent-color: #d1d5db; }

  .pill{
    display:inline-block; padding:6px 10px; border-radius:999px; font-weight:950; font-size:12px;
    border:1px solid transparent; white-space:nowrap;
  }
  .pill-ok{ background:#dcfce7;color:#166534;border-color:#bbf7d0; }
  .pill-warn{ background:#fef3c7;color:#92400e;border-color:#fde68a; }
  .pill-bad{ background:#fee2e2;color:#991b1b;border-color:#fecaca; }
  .pill-dim{ background:rgba(15,23,42,.06);color:var(--muted);border-color:rgba(15,23,42,.10); }
  .pill-info{ background:#dbeafe;color:#1d4ed8;border-color:#bfdbfe; }

  /* layout inside statements tab */
  .grid2{
    display:grid;
    grid-template-columns: 1fr 420px;
    gap:14px;
  }
  @media(max-width: 1100px){ .grid2{ grid-template-columns:1fr; } }

  .box{
    border:1px solid rgba(15,23,42,.10);
    border-radius:18px;
    background: color-mix(in oklab, var(--card-bg) 92%, transparent);
    padding:14px;
  }

  .actions{ display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }
  .row-actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
  .row-actions .btn{ padding:8px 10px; border-radius:999px; font-size:12px; }

  /* bulk bar */
  .bulkbar{
    display:none;
    padding:12px 18px;
    border-bottom:1px solid rgba(15,23,42,.08);
    background: linear-gradient(90deg,
      color-mix(in oklab, var(--card-bg) 88%, transparent),
      color-mix(in oklab, var(--card-bg) 96%, transparent)
    );
    align-items:center;
    justify-content:space-between;
    gap:10px;
    flex-wrap:wrap;
  }
  .bulkbar.on{ display:flex; }
  .bulk-left{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .bulk-count{
    font-weight:950;
    border:1px solid rgba(15,23,42,.12);
    padding:7px 10px;
    border-radius:999px;
    background: color-mix(in oklab, var(--card-bg) 92%, transparent);
  }
  .bulk-note{ font-size:12px; color:var(--muted); font-weight:850; }
  .clickrow{ cursor:pointer; }
  .clickrow:hover td{ background: color-mix(in oklab, var(--card-bg) 88%, transparent); }

  .bulk-ctl{
    display:flex; gap:8px; flex-wrap:wrap; align-items:center;
  }
  .mini{
    padding:8px 10px;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background:transparent;
    color:var(--text);
    font-weight:850;
    outline:none;
    min-width: 220px;
  }
  .mini:focus{ box-shadow:0 0 0 2px rgba(124,58,237,.18); border-color: rgba(124,58,237,.40); }
  .miniSel{
    padding:8px 10px;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background:transparent;
    color:var(--text);
    font-weight:900;
    outline:none;
  }
</style>
@endpush

@section('content') 

<div style="position:fixed;bottom:14px;left:14px;z-index:99999;background:#111827;color:#fff;padding:8px 10px;border-radius:10px;font-weight:900;">
  HUB BLADE NUEVO · {{ now() }}
</div>


<div class="hub">
  <div class="card">

    {{-- HEAD --}}
    <div class="head">
      <div>
        <div class="ttl">Facturación · HUB</div>
        <div class="sub">
          Panel operativo: estados de cuenta, pagos, correos (open/click + reenvío), solicitudes de factura y facturas emitidas.
        </div>
      </div>

      {{-- filtros base --}}
      <form method="GET" action="{{ $routeIndex }}" class="filters">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="ctl">
          <label>Buscar</label>
          <input class="in" name="q" value="{{ $q }}" placeholder="ID, RFC, email, UUID, status, etc.">
        </div>

        <div class="ctl">
          <label>Periodo</label>
          <input class="in" name="period" value="{{ $period }}" placeholder="YYYY-MM">
        </div>

        <div class="ctl">
          <label>Cuenta (ID)</label>
          <input class="in" id="topAccountId" name="accountId" value="{{ $accountId }}" placeholder="ID exacto">
        </div>

        <div class="ctl">
          <label>Por página</label>
          <select class="in" name="per_page">
            @foreach([10,25,50,100,200] as $n)
              <option value="{{ $n }}" {{ $perPage===$n?'selected':'' }}>{{ $n }}</option>
            @endforeach
          </select>
        </div>

        <div class="ctl">
          <label>&nbsp;</label>
          <button class="btn btn-dark" type="submit">Filtrar</button>
        </div>
      </form>
    </div>

    {{-- TABS --}}
    <div class="tabs">
      <a class="tab {{ $tab==='statements'?'on':'' }}"
         href="{{ $routeIndex }}?tab=statements&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">
        Estados <span class="cnt">{{ $countStatements }}</span>
      </a>

      <a class="tab {{ $tab==='emails'?'on':'' }}"
         href="{{ $routeIndex }}?tab=emails&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">
        Correos <span class="cnt">{{ $countEmails }}</span>
      </a>

      <a class="tab {{ $tab==='payments'?'on':'' }}"
         href="{{ $routeIndex }}?tab=payments&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">
        Pagos <span class="cnt">{{ $countPayments }}</span>
      </a>

      <a class="tab {{ $tab==='invoice_requests'?'on':'' }}"
         href="{{ $routeIndex }}?tab=invoice_requests&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">
        Solicitudes de factura <span class="cnt">{{ $countIR }}</span>
      </a>

      <a class="tab {{ $tab==='invoices'?'on':'' }}"
         href="{{ $routeIndex }}?tab=invoices&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">
        Facturas emitidas <span class="cnt">{{ $countInv }}</span>
      </a>
    </div>

    {{-- mensajes --}}
    @if(session('ok'))
      <div class="msg ok">{{ session('ok') }}</div>
    @endif
    @if(session('warn'))
      <div class="msg err" style="border-color:#fde68a;background:#fffbeb;color:#92400e;">{{ session('warn') }}</div>
    @endif

    @if(($errors ?? null) && $errors->any())
      <div class="msg err">{{ $errors->first() }}</div>
    @endif

    {{-- STATEMENTS TAB --}}
    @if($tab==='statements')

      {{-- filtros pro + chips --}}
      <div class="topbar">
        @php
          $baseQuery = [
            'tab' => 'statements',
            'q' => $q,
            'period' => $period,
            'accountId' => $accountId,
            'per_page' => $perPage,
            'saldo_min' => $saldoMin,
            'saldo_max' => $saldoMax,
            'plan' => $plan,
            'modo' => $modoCobro,
            'sent' => $sent,
            'from' => $from,
            'to' => $to,
          ];

          $chipUrl = function(array $over) use ($routeIndex, $baseQuery){
            return $routeIndex . '?' . http_build_query(array_merge($baseQuery, $over));
          };

          $chipOn = fn($key,$val) => ((string)request($key,'')) === (string)$val;
        @endphp

        <div class="chips">
          <a class="chip {{ $status===''?'on':'' }}" href="{{ $chipUrl(['status'=>'']) }}">
            <span class="dot"></span> Todos
          </a>
          <a class="chip {{ $chipOn('status','pendiente')?'on':'' }}" href="{{ $chipUrl(['status'=>'pendiente']) }}">
            <span class="dot"></span> Pendientes
          </a>
          <a class="chip {{ $chipOn('status','parcial')?'on':'' }}" href="{{ $chipUrl(['status'=>'parcial']) }}">
            <span class="dot"></span> Parciales
          </a>
          <a class="chip {{ $chipOn('status','pagado')?'on':'' }}" href="{{ $chipUrl(['status'=>'pagado']) }}">
            <span class="dot"></span> Pagados
          </a>
          <a class="chip {{ $chipOn('status','vencido')?'on':'' }}" href="{{ $chipUrl(['status'=>'vencido']) }}">
            <span class="dot"></span> Vencidos
          </a>
          <a class="chip {{ $chipOn('sent','never')?'on':'' }}" href="{{ $chipUrl(['sent'=>'never']) }}">
            <span class="dot"></span> No enviados
          </a>
        </div>

        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <a class="btn btn-light" href="{{ $routeIndex }}?tab=emails&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">Ver correos</a>
          <a class="btn btn-light" href="{{ $routeIndex }}?tab=payments&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">Ver pagos</a>
          @if($hasAccountsIndex)
            <a class="btn btn-light" href="{{ route('admin.billing.accounts.index') }}">Cuentas</a>
          @endif
          <a class="btn btn-ghost" href="{{ $routeIndex }}?tab=statements&period={{ urlencode($period) }}">Limpiar filtros</a>
        </div>
      </div>

      <form method="GET" action="{{ $routeIndex }}" class="filters2">
        <input type="hidden" name="tab" value="statements">
        <input type="hidden" name="q" value="{{ $q }}">
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="accountId" value="{{ $accountId }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">

        <div class="ctl">
          <label>Estatus</label>
          <select class="in" name="status">
            <option value="" {{ $status===''?'selected':'' }}>Todos</option>
            <option value="pendiente" {{ $status==='pendiente'?'selected':'' }}>Pendiente</option>
            <option value="parcial" {{ $status==='parcial'?'selected':'' }}>Parcial</option>
            <option value="pagado" {{ $status==='pagado'?'selected':'' }}>Pagado</option>
            <option value="vencido" {{ $status==='vencido'?'selected':'' }}>Vencido</option>
            <option value="sin_mov" {{ $status==='sin_mov'?'selected':'' }}>Sin mov</option>
          </select>
        </div>

        <div class="ctl">
          <label>Saldo mín</label>
          <input class="in" name="saldo_min" value="{{ $saldoMin }}" placeholder="ej. 1">
        </div>

        <div class="ctl">
          <label>Saldo máx</label>
          <input class="in" name="saldo_max" value="{{ $saldoMax }}" placeholder="ej. 5000">
        </div>

        <div class="ctl">
          <label>Plan</label>
          <input class="in" name="plan" value="{{ $plan }}" placeholder="FREE, PRO, PREMIUM, etc.">
        </div>

        <div class="ctl">
          <label>Modo cobro</label>
          <select class="in" name="modo">
            <option value="" {{ $modoCobro===''?'selected':'' }}>Todos</option>
            <option value="mensual" {{ $modoCobro==='mensual'?'selected':'' }}>Mensual</option>
            <option value="anual" {{ $modoCobro==='anual'?'selected':'' }}>Anual</option>
          </select>
        </div>

        <div class="ctl">
          <label>Envío</label>
          <select class="in" name="sent">
            <option value="" {{ $sent===''?'selected':'' }}>Cualquiera</option>
            <option value="never" {{ $sent==='never'?'selected':'' }}>Nunca</option>
            <option value="today" {{ $sent==='today'?'selected':'' }}>Hoy</option>
            <option value="7d" {{ $sent==='7d'?'selected':'' }}>šltimos 7 días</option>
            <option value="30d" {{ $sent==='30d'?'selected':'' }}>šltimos 30 días</option>
          </select>
        </div>

        <div class="ctl">
          <label>Desde</label>
          <input class="in" type="date" name="from" value="{{ $from }}">
        </div>

        <div class="ctl">
          <label>Hasta</label>
          <input class="in" type="date" name="to" value="{{ $to }}">
        </div>

        <div class="ctl">
          <label>&nbsp;</label>
          <button class="btn btn-dark" type="submit">Aplicar</button>
        </div>
      </form>

      {{-- KPIs --}}
      <div class="kpis">
        <div class="kpi"><div class="k">Total</div><div class="v">{{ $fmtMoney($kpis['cargo'] ?? 0) }}</div></div>
        <div class="kpi"><div class="k">Pagado</div><div class="v">{{ $fmtMoney($kpis['abono'] ?? 0) }}</div></div>
        <div class="kpi"><div class="k">Saldo</div><div class="v">{{ $fmtMoney($kpis['saldo'] ?? 0) }}</div></div>
        <div class="kpi"><div class="k">Cuentas</div><div class="v">{{ (int)($kpis['accounts'] ?? 0) }}</div></div>

        <div class="kpi">
          <div class="k">Operación</div>
          <div class="v" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
            <a class="btn btn-light" href="{{ $routeIndex }}?tab=emails&period={{ urlencode($period) }}&accountId={{ urlencode($accountId) }}">Logs correos</a>
            <a class="btn btn-light" href="{{ $routeIndex }}?tab=payments&period={{ urlencode($period) }}&accountId={{ urlencode($accountId) }}">Pagos</a>
          </div>
          <div class="mut" style="margin-top:8px;">Tip: haz click en una fila para precargar <span class="mono">account_id</span> en el panel derecho.</div>
        </div>
      </div>

      {{-- BULK BAR --}}
      <div id="bulkbar" class="bulkbar">
        <div class="bulk-left">
          <div class="bulk-count"><span id="bulkCount">0</span> seleccionadas</div>
          <div class="bulk-note">Acciones sobre cuentas del periodo <span class="mono">{{ $period }}</span></div>

          <div class="bulk-ctl" style="margin-left:6px;">
            <input class="mini" id="bulkTo" type="text" placeholder="to (opcional) ej: a@x.com,b@y.com">
            <select class="miniSel" id="bulkMode" title="Modo de envío (bulk)">
              <option value="now" selected>Enviar ahora</option>
              <option value="queue">Solo encolar</option>
            </select>
          </div>
        </div>

        <div class="actions">
          @if($hasBulkSend)
            <button class="btn btn-dark" type="button" onclick="p360Bulk('send')">Enviar estados</button>
          @else
            <button class="btn btn-dark" type="button" disabled title="Falta implementar ruta bulk_send">Enviar estados</button>
          @endif

          @if($hasBulkPayLinks)
            <button class="btn btn-light" type="button" onclick="p360Bulk('paylinks')">Generar ligas</button>
          @else
            <button class="btn btn-light" type="button" disabled title="Falta implementar ruta bulk_paylinks">Generar ligas</button>
          @endif

          <button class="btn btn-ghost" type="button" onclick="p360ClearSel()">Limpiar</button>
        </div>

        {{-- form oculto para bulk (solo si existen rutas) --}}
        @if($hasBulkSend || $hasBulkPayLinks)
          <form id="bulkForm" method="POST" action="" style="display:none;">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="hidden" name="account_ids" value="">
            {{-- solo bulk_send usa estos --}}
            <input type="hidden" name="to" value="">
            <input type="hidden" name="mode" value="now">
          </form>
        @endif
      </div>

      {{-- TABLE + OPS --}}
      <div class="table">
        <div class="grid2">

          {{-- LISTADO --}}
          <div class="box">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
              <div style="font-weight:950;">Estados de cuenta (resumen)</div>
              <div class="mut">Periodo: <span class="mono">{{ $period }}</span></div>
            </div>

            <div style="height:10px;"></div>

            <table class="t">
              <thead>
                <tr>
                  <th class="selcol">
                    <input class="ck" type="checkbox" id="ckAll" onclick="p360ToggleAll(this)">
                  </th>
                  <th style="width:110px">Cuenta</th>
                  <th>Cliente</th>
                  <th style="width:160px">Plan / Cobro</th>
                  <th>Email</th>
                  <th style="width:170px">Tracking</th>
                  <th class="tright" style="width:140px">Total</th>

                  <th class="tright" style="width:140px">Pagado</th>
                  <th class="tright" style="width:140px">Saldo</th>
                  <th style="width:140px">Estatus</th>
                  <th style="width:280px" class="tright">Acciones</th>
                </tr>
              </thead>

              <tbody>

              @php
                $p360RouteTry = function(string $name, $id, array $extra = []) {
                  if (!\Illuminate\Support\Facades\Route::has($name)) return null;

                  $id = (string) $id;
                  if ($id === '' || !preg_match('/^\d+$/', $id)) return null;

                  // 1) intentos comunes
                  $keys = ['id','accountId','account_id','account'];

                  foreach ($keys as $k) {
                    try {
                      return route($name, array_merge([$k => $id], $extra));
                    } catch (\Throwable $e) {
                      // sigue
                    }
                  }

                  // 2) intento inteligente: extraer el nombre del parámetro faltante desde la excepción
                  // Mensaje típico:
                  // "Missing required parameter for [Route: X] [URI: .../{param}] [Missing parameter: param]."
                  foreach ($keys as $k) {
                    // (noop) mantiene compat
                  }

                  try {
                    return route($name, array_merge(['id' => $id], $extra));
                  } catch (\Throwable $e) {
                    $msg = (string) $e->getMessage();

                    // extrae 1 parámetro faltante
                    if (preg_match('/Missing parameter:\s*([A-Za-z0-9_]+)/i', $msg, $m)) {
                      $missing = (string) ($m[1] ?? '');
                      if ($missing !== '') {
                        try {
                          return route($name, array_merge([$missing => $id], $extra));
                        } catch (\Throwable $e2) {
                          // sigue
                        }
                      }
                    }

                    // extrae por si viene en URI {.../{param}} (más defensivo)
                    if (preg_match('/\{([A-Za-z0-9_]+)\}/', $msg, $m2)) {
                      $missing2 = (string) ($m2[1] ?? '');
                      if ($missing2 !== '') {
                        try {
                          return route($name, array_merge([$missing2 => $id], $extra));
                        } catch (\Throwable $e3) {
                          // sigue
                        }
                      }
                    }

                    return null;
                  }
                };
              @endphp


                @forelse($rows as $r)
                    @php
                      // =========================================================
                      // ✅ AID (Admin Account ID) — evitar "abro una y abre otra"
                      // Prioridad: admin_account_id > account_id > aid > id
                      // Si el valor no es numérico > 0, NO generamos links.
                      // =========================================================
                      $aidSrc = 'unresolved';
                      $aidInt = 0;

                      $pickAid = function ($v) {
                        if ($v === null) return 0;
                        if (is_int($v)) return $v > 0 ? $v : 0;
                        $s = trim((string)$v);
                        if ($s === '') return 0;
                        if (!preg_match('/^[0-9]+$/', $s)) return 0;
                        $i = (int)$s;
                        return $i > 0 ? $i : 0;
                      };

                      foreach ([
                        ['admin_account_id', $r->admin_account_id ?? null],
                        ['account_id',       $r->account_id ?? null],
                        ['aid',              $r->aid ?? null],
                        ['id',               $r->id ?? null],
                      ] as $it) {
                        [$k,$v] = $it;
                        $i = $pickAid($v);
                        if ($i > 0) { $aidInt = $i; $aidSrc = 'row.'.$k; break; }
                      }

                      $aid  = $aidInt > 0 ? (string)$aidInt : '';
                      $mail = (string)($r->email ?? '—');
                      $rfc  = (string)($r->rfc ?? $r->codigo ?? '');
                      $name = trim((string)(($r->razon_social ?? '') ?: ($r->name ?? '') ?: ($mail ?: '—')));

                      // Plan / cobro
                      $planLbl = (string)($r->plan_norm ?? $r->plan_actual ?? $r->plan ?? $r->plan_name ?? $r->license_plan ?? '—');
                      $modoLbl = (string)($r->billing_mode ?? $r->modo_cobro ?? $r->modo ?? '—');

                      // licencia visible (si existiera)
                      $licMonto = (float)($r->license_amount_mxn ?? $r->license_amount ?? $r->licencia_monto ?? $r->price_mxn ?? 0);
                      $licShown = $licMonto > 0 ? $fmtMoney($licMonto) : null;

                      // totales
                      $cargoReal = (float)($r->cargo ?? 0);
                      $paid      = (float)($r->abono ?? 0);
                      $expected  = (float)($r->expected_total ?? 0);

                      $totalShown = $cargoReal > 0 ? $cargoReal : $expected;
                      $saldo      = max(0, $totalShown - $paid);

                      $st = (string)($r->status_pago ?? $r->status ?? '');
                      $lbl = $st==='pagado' ? 'PAGADO' : ($st==='pendiente' ? 'PENDIENTE' : ($st==='parcial' ? 'PARCIAL' : ($st==='vencido' ? 'VENCIDO' : 'SIN MOV')));

                      if($st==='pagado'){ $pill='pill-ok'; }
                      elseif($st==='vencido'){ $pill='pill-bad'; }
                      elseif($st==='pendiente' || $st==='parcial'){ $pill='pill-warn'; }
                      else{ $pill='pill-dim'; }

                      $tarPill = (string)($r->tarifa_pill ?? 'pill-dim');
                      $tarLbl  = (string)($r->tarifa_label ?? ($licShown ? ('Licencia ' . $licShown) : '—'));

                      // ✅ Rutas SIEMPRE con AID válido
                      $rowAccountUrl = $p360RouteTry('admin.billing.accounts.show', $aid);

                      $legacyShowUrl = $p360RouteTry('admin.billing.statements.show', $aid, ['period'=>$period]);
                      $legacyPdfUrl  = $p360RouteTry('admin.billing.statements.pdf',  $aid, ['period'=>$period]);

                    @endphp


                  <tr class="clickrow" data-aid="{{ e($aid) }}" data-aid-src="{{ e($aidSrc) }}" data-mail="{{ e($mail) }}">

                    <td onclick="event.stopPropagation();">
                      <input class="ck ckRow" type="checkbox" value="{{ e($aid) }}" onclick="p360SyncSel()">
                    </td>

                    <td class="mono">
                      #{{ $aid }}
                      @if($rowAccountUrl)
                        <div class="mut" style="margin-top:6px;">
                          <a href="{{ $rowAccountUrl }}">Abrir</a>
                        </div>
                      @endif
                    </td>

                    <td>
                      <div style="font-weight:950;">{{ $name }}</div>
                      <div class="mut" style="margin-top:6px;">
                        @if($rfc)
                          RFC: <span class="mono">{{ $rfc }}</span>
                          <span style="opacity:.55;">·</span>
                        @endif
                        Tarifa: <span class="pill {{ $tarPill }}">{{ $tarLbl }}</span>
                      </div>
                    </td>

                    <td>
                      <div style="font-weight:950;">{{ strtoupper(trim($planLbl ?: '—')) }}</div>
                      <div class="mut" style="margin-top:6px;">
                        Cobro: <span class="mono">{{ $modoLbl ?: '—' }}</span>
                        @if($licShown)
                          <span style="opacity:.55;">·</span> <span class="mono">{{ $licShown }}</span>
                        @endif
                      </div>
                    </td>

                    <td class="mono">{{ $mail }}</td>

                      @php
                            $oc = (int)($r->open_count ?? 0);
                            $cc = (int)($r->click_count ?? 0);
                            $lo = !empty($r->last_open_at)  ? (string)$r->last_open_at  : '';
                            $lc = !empty($r->last_click_at) ? (string)$r->last_click_at : '';
                          @endphp

                          <td>
                            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                              <span class="pill {{ $oc>0 ? 'pill-info' : 'pill-dim' }}">Open: <span class="mono">{{ $oc }}</span></span>
                              <span class="pill {{ $cc>0 ? 'pill-info' : 'pill-dim' }}">Click: <span class="mono">{{ $cc }}</span></span>
                            </div>

                            @if($lo || $lc)
                              <div class="mut" style="margin-top:6px; line-height:1.35;">
                                @if($lo)
                                  last open: <span class="mono">{{ $lo }}</span>
                                @endif
                                @if($lc)
                                  @if($lo) <span style="opacity:.55;">·</span> @endif
                                  last click: <span class="mono">{{ $lc }}</span>
                                @endif
                              </div>
                            @else
                              <div class="mut" style="margin-top:6px;">sin interacci�n</div>
                            @endif
                          </td>


                      <td class="tright mono">{{ $fmtMoney($totalShown) }}</td>
                      <td class="tright mono">{{ $fmtMoney($paid) }}</td>

                    <td class="tright">
                      <span class="pill {{ $saldo>0 ? 'pill-warn' : 'pill-ok' }} mono">{{ $fmtMoney($saldo) }}</span>
                    </td>

                    <td>
                      <span class="pill {{ $pill }}">{{ $lbl }}</span>
                      @if(!empty($r->last_sent_at))
                        <div class="mut" style="margin-top:6px;">sent: <span class="mono">{{ (string)$r->last_sent_at }}</span></div>
                      @endif
                    </td>

                    <td class="tright" onclick="event.stopPropagation();">
                      <div class="row-actions">

                        {{-- Enviar unitario --}}
                        @if($hasSendEmail)
                          <form method="POST" action="{{ route('admin.billing.statements_hub.send_email') }}">
                            @csrf
                            <input type="hidden" name="account_id" value="{{ $aid }}">
                            <input type="hidden" name="period" value="{{ $period }}">
                            <button class="btn btn-dark" type="submit">Enviar</button>
                          </form>
                        @else
                          <button class="btn btn-dark" type="button" disabled title="Falta ruta send_email">Enviar</button>
                        @endif



                        {{-- Liga Stripe --}}
                        @if($hasPayLink)
                          <form method="POST" action="{{ route('admin.billing.statements_hub.create_pay_link') }}">
                            @csrf
                            <input type="hidden" name="account_id" value="{{ $aid }}">
                            <input type="hidden" name="period" value="{{ $period }}">
                            <button class="btn btn-light" type="submit">Liga</button>
                          </form>
                        @else
                          <button class="btn btn-light" type="button" disabled title="Falta ruta create_pay_link">Liga</button>
                        @endif

                        {{-- Preview --}}
                        @if($hasPreview)
                          <a class="btn btn-light" target="_blank"
                             href="{{ route('admin.billing.statements_hub.preview_email',['account_id'=>$aid,'period'=>$period]) }}">Preview</a>
                        @else
                          <button class="btn btn-light" type="button" disabled title="Falta ruta preview_email">Preview</button>
                        @endif

                        {{-- Legacy --}}
                        @if($legacyPdfUrl)
                          <a class="btn btn-light" target="_blank" href="{{ $legacyPdfUrl }}">PDF</a>
                        @endif
                        @if($legacyShowUrl)
                          <a class="btn btn-light" target="_blank" href="{{ $legacyShowUrl }}">Detalle</a>
                        @endif
                      </div>
                    </td>
                  </tr>

                @empty
                  <tr><td colspan="11" class="mut" style="padding:14px;">Sin cuentas o faltan tablas/datos para el filtro.</td></tr>
                @endforelse
              </tbody>
            </table>

            <div class="mut" style="margin-top:12px;">
              Selección masiva: marca filas y usa la barra superior (bulk). El envío masivo y ligas se habilitan cuando existan rutas bulk.
            </div>
          </div>

          {{-- OPERACIONES (panel derecho) --}}
          <div class="box">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;">
              <div>
                <div style="font-weight:950;">Operaciones (correo / programación / liga / factura)</div>
                <div class="mut" style="margin-top:4px;">
                  Trabaja por <b>account_id + periodo</b>. “to” acepta múltiples correos separados por coma.
                </div>
              </div>
              <button class="btn btn-ghost" type="button" onclick="p360ClearOps()">Limpiar</button>
            </div>

            <div style="height:12px;"></div>

            {{-- Enviar ahora --}}
            @if($hasSendEmail)
              <form method="POST" action="{{ route('admin.billing.statements_hub.send_email') }}" style="display:grid; gap:8px;">
                @csrf
                <input class="in" id="opsAccountId1" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
                <input class="in" id="opsPeriod1" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>
                <input class="in" id="opsTo1" name="to" value="" placeholder="to (opcional) ej: a@x.com,b@y.com">
                <button class="btn btn-dark" type="submit">Enviar correo ahora</button>
              </form>
            @else
              <div class="mut">Falta ruta: <span class="mono">admin.billing.statements_hub.send_email</span></div>
            @endif

            <div style="height:14px;border-top:1px solid rgba(15,23,42,.08);"></div>

            {{-- Programar --}}
            @if($hasSchedule)
              <form method="POST" action="{{ route('admin.billing.statements_hub.schedule') }}" style="display:grid; gap:8px; margin-top:14px;">
                @csrf
                <input class="in" id="opsAccountId2" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
                <input class="in" id="opsPeriod2" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>
                <input class="in" id="opsTo2" name="to" value="" placeholder="to (opcional) multi">
                <input class="in" name="queued_at" value="{{ now()->addMinutes(10)->format('Y-m-d H:i:s') }}" placeholder="YYYY-MM-DD HH:MM:SS">
                <button class="btn btn-dark" type="submit">Programar correo</button>
                <div class="mut">Cron: <span class="mono">php artisan p360:billing:process-scheduled-emails</span></div>
              </form>
            @else
              <div class="mut" style="margin-top:12px;">Falta ruta: <span class="mono">admin.billing.statements_hub.schedule</span></div>
            @endif

            <div style="height:14px;border-top:1px solid rgba(15,23,42,.08);"></div>

            {{-- Preview --}}
            @if($hasPreview)
              <form method="GET" action="{{ route('admin.billing.statements_hub.preview_email') }}" target="_blank" style="display:grid; gap:8px; margin-top:14px;">
                <input class="in" id="opsAccountId3" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
                <input class="in" id="opsPeriod3" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>
                <button class="btn btn-light" type="submit">Vista previa del correo</button>
              </form>
            @else
              <div class="mut" style="margin-top:12px;">Falta ruta: <span class="mono">admin.billing.statements_hub.preview_email</span></div>
            @endif

            <div style="height:14px;border-top:1px solid rgba(15,23,42,.08);"></div>

            {{-- Liga --}}
            @if($hasPayLink)
              <form method="POST" action="{{ route('admin.billing.statements_hub.create_pay_link') }}" style="display:grid; gap:8px; margin-top:14px;">
                @csrf
                <input class="in" id="opsAccountId4" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
                <input class="in" id="opsPeriod4" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>
                <button class="btn btn-dark" type="submit">Generar liga de pago (Stripe)</button>
              </form>
            @else
              <div class="mut" style="margin-top:12px;">Falta ruta: <span class="mono">admin.billing.statements_hub.create_pay_link</span></div>
            @endif

            <div style="height:14px;border-top:1px solid rgba(15,23,42,.08);"></div>

            {{-- Solicitud de factura --}}
            @if($hasInvRequest)
              <form method="POST" action="{{ route('admin.billing.statements_hub.invoice_request') }}" style="display:grid; gap:8px; margin-top:14px;">
                @csrf
                <input class="in" id="opsAccountId5" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
                <input class="in" id="opsPeriod5" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>
                <textarea class="in" name="notes" rows="3" placeholder="Notas (opcional)" style="resize:vertical;"></textarea>
                <button class="btn btn-dark" type="submit">Crear/actualizar solicitud de factura</button>
              </form>
            @else
              <div class="mut" style="margin-top:12px;">Falta ruta: <span class="mono">admin.billing.statements_hub.invoice_request</span></div>
            @endif

            @if(!$hasBulkSend && !$hasBulkPayLinks)
              <div class="mut" style="margin-top:14px;">
                Nota: el HUB ya está listo para selección y acciones masivas, pero faltan rutas:
                <span class="mono">admin.billing.statements_hub.bulk_send</span> /
                <span class="mono">admin.billing.statements_hub.bulk_paylinks</span>.
              </div>
            @endif
          </div>

        </div>
      </div>

      @push('scripts')
      <script>
        (function(){
          const bulkbar = document.getElementById('bulkbar');
          const bulkCount = document.getElementById('bulkCount');
          const ckAll = document.getElementById('ckAll');

          const topAccountId = document.getElementById('topAccountId');

          const bulkTo  = document.getElementById('bulkTo');
          const bulkMode= document.getElementById('bulkMode');

          const opsIds = [
            document.getElementById('opsAccountId1'),
            document.getElementById('opsAccountId2'),
            document.getElementById('opsAccountId3'),
            document.getElementById('opsAccountId4'),
            document.getElementById('opsAccountId5')
          ].filter(Boolean);

          const opsTo = [
            document.getElementById('opsTo1'),
            document.getElementById('opsTo2')
          ].filter(Boolean);

          function getRows(){
            return Array.from(document.querySelectorAll('.ckRow'));
          }
          function selectedIds(){
            return getRows().filter(x => x.checked).map(x => x.value);
          }
          function updateBulk(){
            const ids = selectedIds();
            if (bulkCount) bulkCount.textContent = String(ids.length);
            if (bulkbar){
              if(ids.length > 0) bulkbar.classList.add('on');
              else bulkbar.classList.remove('on');
            }

            const rows = getRows();
            if(ckAll && rows.length){
              const allChecked = rows.every(x => x.checked);
              const anyChecked = rows.some(x => x.checked);
              ckAll.checked = allChecked;
              ckAll.indeterminate = (!allChecked && anyChecked);
            }
          }

          window.p360ToggleAll = function(master){
            getRows().forEach(x => x.checked = !!master.checked);
            updateBulk();
          }

          window.p360SyncSel = function(){
            updateBulk();
          }

          window.p360ClearSel = function(){
            getRows().forEach(x => x.checked = false);
            if(ckAll){ ckAll.checked = false; ckAll.indeterminate = false; }
            updateBulk();
          }

          window.p360Bulk = function(action){
            const ids = selectedIds();
            if(!ids.length) return;

            const form = document.getElementById('bulkForm');
            if(!form) return;

            const idsInput  = form.querySelector('input[name="account_ids"]');
            const toInput   = form.querySelector('input[name="to"]');
            const modeInput = form.querySelector('input[name="mode"]');

            if(idsInput) idsInput.value = ids.join(',');

            // solo bulk_send usa to/mode
            if(toInput && bulkTo) toInput.value = (bulkTo.value || '').trim();
            if(modeInput && bulkMode) modeInput.value = (bulkMode.value || 'now');

            @if($hasBulkSend)
              if(action === 'send'){
                form.action = @json(route('admin.billing.statements_hub.bulk_send'));
                form.submit();
                return;
              }
            @endif
            @if($hasBulkPayLinks)
              if(action === 'paylinks'){
                form.action = @json(route('admin.billing.statements_hub.bulk_paylinks'));
                form.submit();
                return;
              }
            @endif
          }

          window.p360ClearOps = function(){
            opsIds.forEach(i => i.value = '');
            opsTo.forEach(i => i.value = '');
            if (topAccountId) topAccountId.value = '';
          }

          function onRowClick(e){
            const tr = e.currentTarget;
            const aid = tr.getAttribute('data-aid') || '';
            const mail = tr.getAttribute('data-mail') || '';

            if (topAccountId) topAccountId.value = aid;
            opsIds.forEach(i => i.value = aid);

            opsTo.forEach(i => {
              if (!i.value && mail && mail !== '—') i.value = mail;
            });
          }

          document.querySelectorAll('tr.clickrow[data-aid]').forEach(tr => {
            tr.addEventListener('click', onRowClick);
          });

          updateBulk();
        })();
      </script>
      @endpush

    {{-- EMAILS TAB --}}
    @elseif($tab==='emails')
      <div class="table">
        <div class="box" style="margin:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <div style="font-weight:950;">Correos (billing_email_logs)</div>
            <div class="mut">Tracking open/click + reenvío</div>
          </div>

          <div style="height:10px;"></div>
          <table class="t">
            <thead>
              <tr>
                <th style="width:80px">ID</th>
                <th>Destinos</th>
                <th style="width:100px">Periodo</th>
                <th style="width:120px">Status</th>
                <th style="width:110px">Opens</th>
                <th style="width:110px">Clicks</th>
                <th style="width:190px">Fechas</th>
                <th style="width:220px" class="tright">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @forelse($emails as $e)
                @php
                  $st = (string)($e->status ?? 'queued');
                  $pill = $st==='sent' ? 'pill-ok' : ($st==='failed' ? 'pill-bad' : 'pill-dim');
                  $aid = (string)($e->account_id ?? '');
                  $p = (string)($e->period ?? '');
                @endphp
                <tr>
                  <td class="mono">#{{ (int)($e->id ?? 0) }}</td>
                  <td>
                    <div style="font-weight:950;">
                      {{ (string)($e->to_list ?? $e->email ?? '—') }}
                    </div>
                    <div class="mut">account: <span class="mono">{{ $aid ?: '—' }}</span> · email_id: <span class="mono">{{ (string)($e->email_id ?? '—') }}</span></div>
                    <div class="mut">subject: {{ (string)($e->subject ?? '—') }}</div>
                  </td>
                  <td class="mono">{{ $p ?: '—' }}</td>
                  <td><span class="pill {{ $pill }}">{{ strtoupper($st) }}</span></td>
                  <td class="mono">{{ (int)($e->open_count ?? 0) }}</td>
                  <td class="mono">{{ (int)($e->click_count ?? 0) }}</td>
                  <td class="mut">
                    queued: {{ $e->queued_at ?? '—' }}<br>
                    sent: {{ $e->sent_at ?? '—' }}<br>
                    failed: {{ $e->failed_at ?? '—' }}
                  </td>
                  <td class="tright">
                    <div class="actions">
                      @if($hasPreview && $aid && $p)
                        <a class="btn btn-light" target="_blank"
                           href="{{ route('admin.billing.statements_hub.preview_email',['account_id'=>$aid,'period'=>$p]) }}">Preview</a>
                      @else
                        <button class="btn btn-light" type="button" disabled title="Falta ruta preview_email o faltan datos">Preview</button>
                      @endif

                      @if($hasResend)
                        <form method="POST" action="{{ route('admin.billing.statements_hub.resend', ['id'=>(int)($e->id ?? 0)]) }}">
                          @csrf
                          <button class="btn btn-dark" type="submit">Reenviar</button>
                        </form>
                      @else
                        <button class="btn btn-dark" type="button" disabled title="Falta ruta resend">Reenviar</button>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td colspan="8" class="mut" style="padding:14px;">Sin logs para el filtro.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

    {{-- PAYMENTS TAB --}}
    @elseif($tab==='payments')
      <div class="table">
        <div class="box" style="margin:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <div style="font-weight:950;">Pagos (payments)</div>
            <div class="mut">Incluye pending/paid/succeeded. Filtra por account_id y period si existe.</div>
          </div>

          <div style="height:10px;"></div>
          <table class="t">
            <thead>
              <tr>
                <th style="width:80px">ID</th>
                <th>Cuenta</th>
                <th style="width:110px">Periodo</th>
                <th style="width:160px">Amount</th>
                <th style="width:130px">Status</th>
                <th>Referencia</th>
              </tr>
            </thead>
            <tbody>
              @forelse($payments as $p)
                @php
                  $stRaw = strtolower((string)($p->status ?? '—'));
                  $st = strtoupper($stRaw);
                  $pill = in_array($stRaw, ['paid','succeeded','success','completed'], true) ? 'pill-ok'
                        : ($stRaw==='pending' ? 'pill-warn' : ($stRaw==='failed' ? 'pill-bad' : 'pill-dim'));
                  $amount = (int)($p->amount ?? 0);
                  $amountMxn = null;

                  if (isset($p->amount_mxn) && is_numeric($p->amount_mxn)) {
                    $amountMxn = (float)$p->amount_mxn;
                  } else {
                    $amountMxn = $amount > 0 ? ($amount/100) : 0;
                  }
                @endphp
                <tr>
                  <td class="mono">#{{ (int)($p->id ?? 0) }}</td>
                  <td class="mono">{{ (string)($p->account_id ?? '—') }}</td>
                  <td class="mono">{{ (string)($p->period ?? '—') }}</td>
                  <td class="mono">{{ $fmtMoney($amountMxn) }} MXN</td>
                  <td><span class="pill {{ $pill }}">{{ $st }}</span></td>
                  <td class="mut">
                    provider: <span class="mono">{{ (string)($p->provider ?? '—') }}</span><br>
                    ref: <span class="mono">{{ (string)($p->reference ?? $p->stripe_session_id ?? '—') }}</span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="mut" style="padding:14px;">Sin pagos.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

    {{-- INVOICE REQUESTS TAB --}}
    @elseif($tab==='invoice_requests')
      <div class="table">
        <div class="box" style="margin:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <div style="font-weight:950;">Solicitudes de factura (billing_invoice_requests)</div>
            <div class="mut">requested / issued / rejected</div>
          </div>

          <div style="height:10px;"></div>
          <table class="t">
            <thead>
              <tr>
                <th style="width:80px">ID</th>
                <th>Cuenta/Periodo</th>
                <th style="width:140px">Estatus</th>
                <th>UUID / Notas</th>
                <th style="width:320px" class="tright">Actualizar</th>
              </tr>
            </thead>
            <tbody>
              @forelse($invoiceRequests as $ir)
                @php
                  $s = strtolower((string)($ir->status ?? 'requested'));
                  $pill = $s==='issued' ? 'pill-ok' : ($s==='rejected' ? 'pill-bad' : 'pill-dim');
                @endphp
                <tr>
                  <td class="mono">#{{ (int)($ir->id ?? 0) }}</td>
                  <td class="mut">
                    <div>account: <span class="mono">{{ (string)($ir->account_id ?? '—') }}</span></div>
                    <div>period: <span class="mono">{{ (string)($ir->period ?? '—') }}</span></div>
                  </td>
                  <td><span class="pill {{ $pill }}">{{ strtoupper((string)($ir->status ?? 'REQUESTED')) }}</span></td>
                  <td class="mut">
                    uuid: <span class="mono">{{ (string)($ir->cfdi_uuid ?? '—') }}</span><br>
                    {{ (string)($ir->notes ?? '') }}
                  </td>
                  <td class="tright">
                    @if($hasInvStatus)
                      <form method="POST" action="{{ route('admin.billing.statements_hub.invoice_status') }}" style="display:grid; gap:8px;">
                        @csrf
                        <input type="hidden" name="id" value="{{ (int)($ir->id ?? 0) }}">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                          <input class="in" name="status" value="{{ (string)($ir->status ?? 'requested') }}" placeholder="requested|issued|rejected">
                          <input class="in" name="cfdi_uuid" value="{{ (string)($ir->cfdi_uuid ?? '') }}" placeholder="UUID (opcional)">
                        </div>
                        <input class="in" name="notes" value="{{ (string)($ir->notes ?? '') }}" placeholder="Notas (opcional)">
                        <button class="btn btn-dark" type="submit">Guardar</button>
                      </form>
                    @else
                      <div class="mut">Falta ruta: <span class="mono">admin.billing.statements_hub.invoice_status</span></div>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="mut" style="padding:14px;">Sin solicitudes.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

    {{-- INVOICES TAB --}}
    @elseif($tab==='invoices')
      <div class="table">
        <div class="grid2" style="padding:16px;">
          <div class="box">
            <div style="font-weight:950;">Facturas emitidas (billing_invoices)</div>
            <div class="mut" style="margin-top:4px;">Registro administrativo de facturas por account_id + periodo.</div>

            <div style="height:10px;"></div>
            <table class="t">
              <thead>
                <tr>
                  <th style="width:80px">ID</th>
                  <th>Cuenta/Periodo</th>
                  <th>Serie/Folio</th>
                  <th>UUID</th>
                  <th style="width:170px">Fecha</th>
                  <th class="tright" style="width:160px">Monto</th>
                </tr>
              </thead>
              <tbody>
                @forelse($invoices as $inv)
                  @php
                    $amtC = (int)($inv->amount_cents ?? 0);
                    $amtM = isset($inv->amount_mxn) && is_numeric($inv->amount_mxn) ? (float)$inv->amount_mxn : ($amtC/100);
                  @endphp
                  <tr>
                    <td class="mono">#{{ (int)($inv->id ?? 0) }}</td>
                    <td class="mut">
                      <div>account: <span class="mono">{{ (string)($inv->account_id ?? '—') }}</span></div>
                      <div>period: <span class="mono">{{ (string)($inv->period ?? '—') }}</span></div>
                    </td>
                    <td class="mono">{{ (string)($inv->serie ?? '') }}{{ (string)($inv->folio ?? '') ? '-'.(string)$inv->folio : '' }}</td>
                    <td class="mono">{{ (string)($inv->cfdi_uuid ?? '—') }}</td>
                    <td class="mono">{{ (string)($inv->issued_date ?? '—') }}</td>
                    <td class="tright mono">{{ $fmtMoney($amtM) }} MXN</td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="mut" style="padding:14px;">Sin facturas registradas.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="box">
            <div style="font-weight:950;">Registrar / actualizar factura</div>
            <div class="mut" style="margin-top:4px;">Si existe solicitud, al guardar se marca como <b>issued</b>.</div>

            <div style="height:12px;"></div>

            @if($hasSaveInvoice)
              <form method="POST" action="{{ route('admin.billing.statements_hub.save_invoice') }}" style="display:grid; gap:8px;">
                @csrf
                <input class="in" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
                <input class="in" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                  <input class="in" name="serie" placeholder="Serie (opcional)">
                  <input class="in" name="folio" placeholder="Folio (opcional)">
                </div>

                <input class="in" name="cfdi_uuid" placeholder="UUID (opcional)">
                <input class="in" name="issued_date" placeholder="Fecha emisión (YYYY-MM-DD)">

                <input class="in" name="amount_mxn" placeholder="Monto MXN (ej. 999.00)">
                <textarea class="in" name="notes" rows="3" placeholder="Notas (opcional)" style="resize:vertical;"></textarea>

                <button class="btn btn-dark" type="submit">Guardar factura</button>
              </form>
            @else
              <div class="mut">Falta ruta: <span class="mono">admin.billing.statements_hub.save_invoice</span></div>
            @endif
          </div>
        </div>
      </div>
    @endif

  </div>
</div>
@endsection
