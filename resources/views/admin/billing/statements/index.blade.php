{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\statements\index.blade.php --}}
{{-- UI v6.5 · Estados de cuenta (Admin) — Vault look + KPI + bulk + drawer acciones (sin romper tabla) --}}
@extends('layouts.admin')

@section('title','Facturación · Estados de cuenta')
@section('layout','full')
@section('contentLayout','full')
@section('pageClass','billing-statements-index-page')

@php
  use Illuminate\Support\Facades\Route;

  // ===== Inputs =====
  $q = (string) request('q', '');

  $periodRaw = (string) request('period', now()->format('Y-m'));
  $period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodRaw)
      ? $periodRaw
      : now()->format('Y-m');

  $periodFrom = (string) ($periodFrom ?? request('period_from', ''));
  $periodTo   = (string) ($periodTo ?? request('period_to', ''));

  if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodFrom)) $periodFrom = '';
  if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodTo))   $periodTo   = '';

  if ($periodFrom !== '' && $periodTo !== '' && strcmp($periodFrom, $periodTo) > 0) {
      [$periodFrom, $periodTo] = [$periodTo, $periodFrom];
  }

  $actionPeriod = $periodTo !== ''
      ? $periodTo
      : ($periodFrom !== '' ? $periodFrom : $period);

  $periodLabel = (string) ($periodLabel ?? (
      ($periodFrom !== '' || $periodTo !== '')
          ? (($periodFrom !== '' ? $periodFrom : '—') . ' → ' . ($periodTo !== '' ? $periodTo : '—'))
          : $period
  ));

  $dateFilterDisplay = ($periodFrom !== '' || $periodTo !== '')
      ? (($periodFrom !== '' ? $periodFrom : $actionPeriod) . ' a ' . ($periodTo !== '' ? $periodTo : $actionPeriod))
      : $actionPeriod;

  $isRangeMode = ($periodFrom !== '' || $periodTo !== '');
  $singleManagePeriod = $actionPeriod;

  $accountId  = (string) request('accountId','');

  $status     = (string) request('status','all');
  $status     = $status !== '' ? $status : 'all';

  $perPage    = (int) request('perPage', $perPage ?? 25);
  if ($perPage <= 0) $perPage = 25;

  // ✅ filtro: solo seleccionadas (por checkbox)
  $onlySelected = (bool) (
      ($onlySelected ?? false)
      || request()->boolean('only_selected', false)
      || request()->boolean('onlySelected', false)
      || ((string)request('only_selected','') === '1')
  );

  $idsSelectedReq = request('ids', null);

  // Si el controller nos pasó idsCsv y NO viene ids en query, úsalo
  if (($idsSelectedReq === null || $idsSelectedReq === '') && !empty($idsCsv)) {
      $idsSelectedReq = (string) $idsCsv;
  }

  if (is_string($idsSelectedReq)) {
    $idsSelectedReq = array_values(array_filter(array_map('trim', explode(',', $idsSelectedReq))));
  }
  if (!is_array($idsSelectedReq)) $idsSelectedReq = [];

  // normaliza y limita (defensivo)
  $idsSelectedReq = array_values(array_unique(array_filter(array_map(function($v){
    $s = trim((string)$v);
    if ($s === '') return null;
    if (preg_match('/^\d+$/', $s)) return $s;
    if (preg_match('/^[a-zA-Z0-9\-_]+$/', $s)) return $s;
    return null;
  }, $idsSelectedReq))));

  if (count($idsSelectedReq) > 500) $idsSelectedReq = array_slice($idsSelectedReq, 0, 500);

  // ===== Data =====
  $rows = $rows ?? collect();
  $kpis = $kpis ?? [
    'cargo'    => 0,
    'abono'    => 0,
    'saldo'    => 0,
    'accounts' => 0,
    'paid_edo' => 0,
    'paid_pay' => 0,
    'edocta'   => null,
    'payments' => null,
  ];

    $opsSummary = $opsSummary ?? [
    'total'   => 0,
    'sent'    => 0,
    'blocked' => 0,
    'failed'  => 0,
    'pending' => 0,
  ];

  $fmtMoney = fn($n) => '$' . number_format((float)$n, 2);

  $routeIndex = route('admin.billing.statements.index');

  $hasShow   = Route::has('admin.billing.statements.show');
  $hasPdf    = Route::has('admin.billing.statements.pdf');
  $hasSendLegacy = Route::has('admin.billing.statements.email');

  $hasHub       = Route::has('admin.billing.statements_hub.index');
  $hasBulkSend  = Route::has('admin.billing.statements_hub.bulk_send');
  $hasAccounts  = Route::has('admin.billing.accounts.index');

  // Endpoint update status/pay_method
  $statusEndpoint = Route::has('admin.billing.statements.status')
    ? route('admin.billing.statements.status')
    : url('/admin/billing/statements/status');

  // paginator
  $isPaginator = is_object($rows) && method_exists($rows, 'total') && method_exists($rows, 'links');
  $countRows   = $isPaginator ? (int) $rows->count() : (is_countable($rows) ? count($rows) : 0);
  $totalRows   = $isPaginator ? (int) $rows->total() : $countRows;

  // chips
  $chipBase = [
    'q'           => $q,
    'period'      => $actionPeriod,
    'period_from' => $periodFrom,
    'period_to'   => $periodTo,
    'accountId'   => $accountId,
    'status'      => $status,
    'perPage'     => $perPage,
  ];

  if ($onlySelected && !empty($idsSelectedReq)) {
    $chipBase['only_selected'] = 1;
    $chipBase['ids'] = implode(',', $idsSelectedReq);
  } else {
    unset($chipBase['only_selected'], $chipBase['ids']);
  }

  if (request()->has('includeAnnual')) {
    $chipBase['includeAnnual'] = request('includeAnnual');
  }

  $chipUrl = function(array $over) use ($routeIndex, $chipBase) {
    return $routeIndex . '?' . http_build_query(array_merge($chipBase, $over));
  };

  $normPlan = function(?string $v): string {
    $v = strtolower(trim((string)$v));
    if ($v === '') return '—';
    $map = ['free'=>'FREE','basic'=>'BASIC','pro'=>'PRO','premium'=>'PREMIUM','enterprise'=>'ENTERPRISE'];
    return $map[$v] ?? strtoupper($v);
  };

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

  $tarifaPillClass = function(?string $pill): string {
    $p = strtolower(trim((string)$pill));
    if (str_contains($p, 'vigente') || $p === 'info') return 'sx-ok';
    if (str_contains($p, 'próximo') || str_contains($p, 'proximo') || str_contains($p, 'next')) return 'sx-warn';
    return 'sx-dim';
  };

  $pillStatusClass = function(string $st): string {
    $s = strtolower(trim($st));
    if ($s === 'pagado') return 'sx-ok';
    if ($s === 'vencido') return 'sx-bad';
    if (in_array($s, ['pendiente','parcial'], true)) return 'sx-warn';
    return 'sx-dim';
  };

  $payMethodOptions = [
    ''          => '—',
    'card'      => 'Tarjeta',
    'transfer'  => 'Transferencia',
    'spei'      => 'SPEI',
    'oxxo'      => 'OXXO',
    'cash'      => 'Efectivo',
    'check'     => 'Cheque',
    'stripe'    => 'Stripe',
    'manual'    => 'Manual',
  ];

  $statusOptions = [
    'pendiente' => 'Pendiente',
    'parcial'   => 'Parcial',
    'pagado'    => 'Pagado',
    'vencido'   => 'Vencido',
    'sin_mov'   => 'Sin mov',
  ];

  $fmtDate = function($v): string {
    $s = trim((string)$v);
    if ($s === '') return '';
    return $s;
  };
@endphp

@push('styles')
  @php
    $sxCssPath = public_path('assets/admin/css/billing-statements.css');
    $sxCssVer  = @filemtime($sxCssPath) ?: time();
  @endphp
  <link rel="stylesheet" href="{{ asset('assets/admin/css/billing-statements.css') }}?v={{ $sxCssVer }}">
  <link rel="stylesheet" href="{{ asset('assets/admin/css/p360-pagination.css') }}?v={{ $sxCssVer }}">
@endpush

@section('content')
<div class="sx-wrap">
  <div class="sx-card">

    @if(session('ok'))
      <div class="sxAlert sxAlert--ok">{{ session('ok') }}</div>
    @endif

    @if(session('error'))
      <div class="sxAlert sxAlert--error">{{ session('error') }}</div>
    @endif

    @if($errors->any())
      <div class="sxAlert sxAlert--error">
        {{ $errors->first() }}
      </div>
    @endif

    @if(!empty($error))
      <div class="sxAlert sxAlert--error">{{ $error }}</div>
    @endif

    <div class="sx-topSticky">
      <div class="sx-head">
        <div>
          <div class="sx-title">Facturación · Estados de cuenta</div>
          <div class="sx-sub">
            Periodo <span class="sx-mono">{{ $periodLabel }}</span>.
            Aquí ves totales, pagos y saldo; y puedes actualizar <b>estatus</b> y <b>forma de pago</b> por cuenta.
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

      <div class="sx-filters">
        <form method="GET" action="{{ $routeIndex }}" class="sx-filterbar" id="sxFilterForm">
          @if(request()->has('includeAnnual'))
            <input type="hidden" name="includeAnnual" value="{{ request('includeAnnual') }}">
          @endif

          <input type="hidden" name="period" id="sxPeriod" value="{{ $actionPeriod }}">
          <input type="hidden" name="period_from" id="sxPeriodFrom" value="{{ $periodFrom }}">
          <input type="hidden" name="period_to" id="sxPeriodTo" value="{{ $periodTo }}">

          <div class="sx-filterbar__group sx-filterbar__group--search">
            <label>Buscar</label>
            <div class="sx-inputWrap">
              <span class="sx-inputIcon">⌕</span>
              <input
                class="sx-in sx-in--withIcon"
                name="q"
                id="sxFilterQ"
                value="{{ $q }}"
                placeholder="DNI, RFC, correo electrónico o razón social..."
                autocomplete="off">
            </div>
          </div>

          <div class="sx-filterbar__group sx-filterbar__group--date">
            <label>Filtro de fechas</label>

            <div class="sx-dateFilter" id="sxDateFilter">
              <button type="button" class="sx-dateFilter__trigger" id="sxDateTrigger">
                <span class="sx-dateFilter__icon">📅</span>
                <span class="sx-dateFilter__text" id="sxDateFilterText">{{ $dateFilterDisplay }}</span>
                <span class="sx-dateFilter__caret">▾</span>
              </button>

              <div class="sx-dateFilter__panel" id="sxDatePanel" hidden>
                <div class="sx-dateFilter__panelHead">Selecciona rango mensual</div>

                <div class="sx-dateFilter__grid">
                  <div class="sx-dateFilter__field">
                    <label>Desde</label>
                    <input type="month" class="sx-in" id="sxPickerFrom" value="{{ $periodFrom }}">
                  </div>

                  <div class="sx-dateFilter__field">
                    <label>Hasta</label>
                    <input type="month" class="sx-in" id="sxPickerTo" value="{{ $periodTo !== '' ? $periodTo : $actionPeriod }}">
                  </div>
                </div>

                <div class="sx-dateFilter__quick">
                  <button type="button" class="sx-btn sx-btn-soft" data-sx-date-quick="current">Este mes</button>
                  <button type="button" class="sx-btn sx-btn-soft" data-sx-date-quick="last3">Últimos 3 meses</button>
                  <button type="button" class="sx-btn sx-btn-soft" data-sx-date-quick="last6">Últimos 6 meses</button>
                  <button type="button" class="sx-btn sx-btn-ghost" data-sx-date-quick="clear">Limpiar rango</button>
                </div>

                <div class="sx-dateFilter__actions">
                  <button type="button" class="sx-btn sx-btn-ghost" id="sxDateCancel">Cerrar</button>
                  <button type="button" class="sx-btn sx-btn-primary" id="sxDateApply">Aplicar</button>
                </div>
              </div>
            </div>
          </div>

          <div class="sx-filterbar__group sx-filterbar__group--account">
            <label>Cuenta (ID)</label>
            <div class="sx-inputWrap">
              <span class="sx-inputIcon">#</span>
              <input
                class="sx-in sx-in--withIcon"
                name="accountId"
                id="sxFilterAccount"
                value="{{ $accountId }}"
                placeholder="ID exacto"
                autocomplete="off">
            </div>
          </div>

          <div class="sx-filterbar__group sx-filterbar__group--status">
            <label>Estatus</label>
            <select class="sx-sel" name="status" id="sxFilterStatus">
              <option value="all" {{ $status==='all'?'selected':'' }}>Todos</option>
              @foreach($statusOptions as $k => $lbl)
                <option value="{{ $k }}" {{ $status===$k?'selected':'' }}>{{ $lbl }}</option>
              @endforeach
            </select>
          </div>

          <div class="sx-filterbar__group sx-filterbar__group--perpage">
            <label>Por página</label>
            <select class="sx-sel" name="perPage" id="sxFilterPerPage">
              @foreach([10,25,50,100,200,250,500,1000] as $n)
                <option value="{{ $n }}" {{ (int)$perPage===(int)$n?'selected':'' }}>{{ $n }}</option>
              @endforeach
            </select>
          </div>

          <div class="sx-filterbar__actions">
            <button class="sx-btn sx-btn-primary" type="submit">Filtrar</button>
            <button class="sx-btn sx-btn-ghost" type="button" id="sxFilterReset">Limpiar</button>
          </div>
        </form>

        <div class="sx-chips">
          <a class="sx-chip {{ $status==='all'?'on':'' }}" href="{{ $chipUrl(['status'=>'all']) }}"><span class="dot"></span> Todos</a>
          <a class="sx-chip {{ $onlySelected ? 'on' : '' }}" href="#" onclick="event.preventDefault(); sxFilterSelected();">
            <span class="dot"></span> Solo seleccionadas
          </a>
          <a class="sx-chip {{ $status==='pendiente'?'on':'' }}" href="{{ $chipUrl(['status'=>'pendiente']) }}"><span class="dot"></span> Pendientes</a>
          <a class="sx-chip {{ $status==='parcial'?'on':'' }}" href="{{ $chipUrl(['status'=>'parcial']) }}"><span class="dot"></span> Parciales</a>
          <a class="sx-chip {{ $status==='pagado'?'on':'' }}" href="{{ $chipUrl(['status'=>'pagado']) }}"><span class="dot"></span> Pagados</a>
          <a class="sx-chip {{ $status==='vencido'?'on':'' }}" href="{{ $chipUrl(['status'=>'vencido']) }}"><span class="dot"></span> Vencidos</a>
          <a class="sx-chip {{ $status==='sin_mov'?'on':'' }}" href="{{ $chipUrl(['status'=>'sin_mov']) }}"><span class="dot"></span> Sin mov</a>

          <span style="margin-left:auto; color:var(--sx-mut); font-weight:850; font-size:12px;">
            Mostrando: <span class="sx-mono">{{ $countRows }}</span>@if($isPaginator) de <span class="sx-mono">{{ $totalRows }}</span>@endif
          </span>
        </div>
      </div>


            <div class="sx-kpis" style="margin-bottom:14px;">
        <div class="sx-kpi">
          <div class="sx-k">Estados detectados</div>
          <div class="sx-v">{{ (int)($opsSummary['total'] ?? 0) }}</div>
          <div class="sx-mini" title="Statements localizados en billing_statements para el periodo filtrado.">
            Statements localizados para el filtro actual.
          </div>
        </div>

        <div class="sx-kpi">
          <div class="sx-k">Enviados</div>
          <div class="sx-v">{{ (int)($opsSummary['sent'] ?? 0) }}</div>
          <div class="sx-mini" title="Statements con envío exitoso registrado en billing_email_logs.">
            Con log <span class="sx-mono">sent</span>.
          </div>
        </div>

        <div class="sx-kpi">
          <div class="sx-k">Bloqueados por guard</div>
          <div class="sx-v">{{ (int)($opsSummary['blocked'] ?? 0) }}</div>
          <div class="sx-mini" title="Statements frenados por snapshot inválido o statement vacío.">
            Snapshot nulo/vacío o sin importes.
          </div>
        </div>

        <div class="sx-kpi">
          <div class="sx-k">Fallidos</div>
          <div class="sx-v">{{ (int)($opsSummary['failed'] ?? 0) }}</div>
          <div class="sx-mini" title="Statements con intento de envío fallido.">
            Con log <span class="sx-mono">failed</span>.
          </div>
        </div>

        <div class="sx-kpi">
          <div class="sx-k">Pendientes</div>
          <div class="sx-v">{{ (int)($opsSummary['pending'] ?? 0) }}</div>
          <div class="sx-mini" title="Statements detectados sin envío exitoso, sin bloqueo guard y sin fallo registrado.">
            Aún sin cierre operativo.
          </div>
        </div>
      </div>

      
      <div class="sx-kpis">
        <div class="sx-kpi">
          <div class="sx-k">Total</div>
          <div class="sx-v">{{ $fmtMoney($kpis['cargo'] ?? 0) }}</div>
          <div class="sx-mini" title="Cargos del periodo (o esperado por licencia si aplica).">
            Cargos del periodo (o esperado por licencia si aplica).
          </div>
        </div>

        <div class="sx-kpi">
          <div class="sx-k">Pagado</div>
          <div class="sx-v">{{ $fmtMoney($kpis['abono'] ?? 0) }}</div>

          @php
            $paidTip = 'Total abonado acumulado del periodo.';
            if (isset($kpis['edocta']) || isset($kpis['payments'])) $paidTip = 'Desglose: EdoCta y Payments.';
          @endphp

          <div class="sx-mini" title="{{ $paidTip }}">
            @if(isset($kpis['edocta']) || isset($kpis['payments']))
              EdoCta: <span class="sx-mono">{{ $fmtMoney($kpis['edocta'] ?? 0) }}</span>
              · Payments: <span class="sx-mono">{{ $fmtMoney($kpis['payments'] ?? 0) }}</span>
            @else
              Total abonado acumulado del periodo.
            @endif
          </div>
        </div>

        <div class="sx-kpi">
          <div class="sx-k">Saldo</div>
          <div class="sx-v">{{ $fmtMoney($kpis['saldo'] ?? 0) }}</div>
          <div class="sx-mini" title="Saldo pendiente considerando pagos.">Saldo pendiente considerando pagos.</div>
        </div>

        <div class="sx-kpi">
          <div class="sx-k">Pendiente anterior</div>
          <div class="sx-v">{{ $fmtMoney($kpis['prev_pending'] ?? 0) }}</div>
          <div class="sx-mini" title="Suma de adeudos de meses anteriores todavía abiertos.">
            Suma de adeudos de meses anteriores todavía abiertos.
          </div>
        </div>

        <div class="sx-kpi">
          <div class="sx-k">Cuentas</div>
          <div class="sx-v">{{ (int)($kpis['accounts'] ?? 0) }}</div>
          <div class="sx-mini" title="Total de cuentas en el resultado.">Total de cuentas en el resultado.</div>
        </div>

        <div class="sx-kpi sx-kpi-ops">
          <div class="sx-toolbar">
            <div class="sx-toolbarLeft">
              <div class="sx-toolbarTitle">
                <span class="sx-chipLabel"><span class="dot"></span> Operación</span>
                <span class="sx-toolbarHint">
                  @if($hasBulkSend) Envío masivo vía HUB @else Bulk en modo demo (activa bulk_send) @endif
                </span>
              </div>
              <div class="sx-toolbarMeta">
                <span class="sx-miniPill">Periodo <span class="sx-mono">{{ $periodLabel }}</span></span>
                <span class="sx-miniPill sx-muted">Selecciona cuentas y aplica acciones</span>
              </div>
            </div>

            <div class="sx-toolbarRight">
              <div class="sx-btnGroup" role="group" aria-label="Operación selección">
                <button class="sx-btn sx-btn-soft" type="button" onclick="sxSelectAll(true)">Todo</button>
                <button class="sx-btn sx-btn-soft" type="button" onclick="sxSelectAll(false)">Nada</button>
                <button class="sx-btn sx-btn-soft" type="button" onclick="sxFilterSelected()">Solo seleccionadas</button>
              </div>

              <div class="sx-btnGroup" role="group" aria-label="Operación bulk">
                <button class="sx-btn sx-btn-primary" type="button" onclick="sxBulkSend()">Enviar (bulk)</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div> {{-- /.sx-topSticky --}}

    <div class="sx-panel">

      <div id="sxBulkbar" class="sx-bulkbar">
        <div class="sx-bulk-left">
          <div class="sx-badge"><span id="sxBulkCount">0</span> seleccionadas</div>
          <div class="sx-bulk-note">Periodo <span class="sx-mono">{{ $periodLabel }}</span></div>
        </div>

        <div class="sx-actionsRow">
          <button class="sx-btn sx-btn-ghost" type="button" onclick="sxClear()">Limpiar</button>
          <button class="sx-btn sx-btn-primary" type="button" onclick="sxBulkSend()">Enviar correos</button>
        </div>

        <form id="sxBulkForm" method="POST" action="{{ $hasBulkSend ? route('admin.billing.statements_hub.bulk_send') : '' }}" style="display:none;">
          @csrf
          <input type="hidden" name="period" value="{{ $actionPeriod }}">
          <input type="hidden" name="account_ids" value="">
        </form>
      </div>

      <div class="sx-table-wrap">
          <table class="sx-table">
            <colgroup>
              <col style="width:44px">    {{-- checkbox --}}
              <col style="width:120px">   {{-- Cuenta --}}
              <col style="width:270px">   {{-- Cliente --}}
              <col style="width:250px">   {{-- Contacto / Meta --}}
              <col style="width:120px">   {{-- Total periodo --}}
              <col style="width:130px">   {{-- Pagado --}}
              <col style="width:150px">   {{-- Pendiente anterior --}}
              <col style="width:140px">   {{-- Saldo total --}}
              <col style="width:120px">   {{-- Estatus --}}
              <col style="width:130px">   {{-- Acciones --}}
            </colgroup>

            <thead>
              <tr>
                <th class="sx-selcol" title="Seleccionar todo">
                  <input id="sxCkAll" class="sx-ck" type="checkbox" onclick="sxToggleAll(this)">
                </th>
                <th>Cuenta</th>
                <th>Cliente</th>
                <th>Contacto / Meta</th>
                <th class="sx-right">Total periodo</th>
                <th class="sx-right">Pagado</th>
                <th class="sx-right">Pendiente anterior</th>
                <th class="sx-right">Saldo total</th>
                <th>Estatus</th>
                <th>Acciones</th>
              </tr>
            </thead>

            <tbody>
            @forelse($rows as $r)
              @php
                $rowId = (string)($r->id ?? '');
                $aid   = (string)($r->account_id ?? $r->accountId ?? $r->cuenta_id ?? $rowId ?? '');
                $aid   = trim($aid);

                $rfc   = trim((string)($r->rfc ?? $r->codigo ?? ''));
                $name  = trim((string)(($r->razon_social ?? '') ?: ($r->name ?? '') ?: '—'));
                $mail  = trim((string)($r->email ?? $r->correo ?? '—'));

                $planRaw = (string)($r->plan_norm ?? $r->plan_actual ?? $r->plan ?? $r->plan_name ?? '—');
                $plan    = $normPlan($planRaw);

                $modoRaw = (string)($r->modo_cobro ?? $r->billing_cycle ?? $r->billing_mode ?? $r->modo ?? '');
                $modo    = $normModo($modoRaw, $planRaw);

                $tarifaLabel = trim((string)($r->tarifa_label ?? ''));
                $tarifaPill  = trim((string)($r->tarifa_pill ?? ''));
                $tarifaCls   = $tarifaPillClass($tarifaPill);

                $estadoCuenta = strtolower(trim((string)($r->estado_cuenta ?? '')));
                $estadoLabel  = $estadoCuenta !== '' ? strtoupper($estadoCuenta) : '';

                $isBlocked = (int)($r->is_blocked ?? 0) === 1;

                $total       = (float)($r->total_shown ?? 0);
                $abono       = (float)($r->abono ?? 0);
                $saldoPeriodo= (float)($r->saldo_current ?? $r->saldo_shown ?? 0);
                $prevBalance = (float)($r->prev_balance ?? 0);
                $saldoTotal  = (float)($r->total_due ?? ($saldoPeriodo + $prevBalance));

                $abonoEdo = (float)($r->abono_edo ?? 0);
                $abonoPay = (float)($r->abono_pay ?? 0);

                $st = (string)($r->status_override ?? $r->ov_status ?? $r->status_pago ?? $r->status ?? '');
                $st = strtolower(trim($st));
                if ($st === 'paid' || $st === 'succeeded') $st = 'pagado';
                if ($st === 'pending') $st = 'pendiente';
                if ($st === 'unpaid' || $st === 'past_due') $st = 'vencido';
                if ($st === '') $st = 'sin_mov';

                $stPill = $pillStatusClass($st);

                $payMethod   = strtolower(trim((string)($r->ov_pay_method ?? $r->pay_method ?? $r->pago_metodo ?? '')));
                $payProvider = trim((string)($r->ov_pay_provider ?? $r->pay_provider ?? $r->pago_prov ?? ''));
                $payStatus   = strtolower(trim((string)($r->ov_pay_status ?? $r->pay_status ?? '')));
                if ($payStatus === 'paid' || $payStatus === 'succeeded') $payStatus = 'pagado';
                if ($payStatus === 'pending') $payStatus = 'pendiente';
                if ($payStatus === 'unpaid' || $payStatus === 'past_due') $payStatus = 'vencido';

                $payDue  = $fmtDate($r->pay_due_date ?? $r->vence ?? '');
                $payLast = $fmtDate($r->pay_last_paid_at ?? $r->last_paid_at ?? '');

                $rowPeriod = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)($r->period ?? ''))
                    ? (string) $r->period
                    : $actionPeriod;

                $showUrl  = ($hasShow && $aid) ? route('admin.billing.statements.show', ['accountId'=>$aid, 'period'=>$rowPeriod]) : null;
                $pdfUrl   = ($hasPdf  && $aid) ? route('admin.billing.statements.pdf',  ['accountId'=>$aid, 'period'=>$rowPeriod]) : null;
                $emailUrl = ($hasSendLegacy && $aid) ? route('admin.billing.statements.email', ['accountId'=>$aid, 'period'=>$rowPeriod]) : null;
              @endphp

              <tr id="sxRow-{{ e($aid) }}-{{ e(str_replace('-', '', $rowPeriod)) }}">
                <td onclick="event.stopPropagation();">
                  <input class="sx-ck sx-row"
                    type="checkbox"
                    value="{{ e($aid) }}"
                    data-sx-row="1"
                    onclick="sxSync();"
                    {{ ($onlySelected && in_array((string)$aid, $idsSelectedReq, true)) ? 'checked' : '' }}>
                </td>

                <td class="sx-mono">
                  #{{ $aid }}
                  @if($rowId !== '' && $rowId !== $aid)
                    <div class="sx-subrow">RowID: <span class="sx-mono">#{{ $rowId }}</span></div>
                  @endif
                  @if($rfc !== '')
                    <div class="sx-subrow">RFC: <span class="sx-mono">{{ $rfc }}</span></div>
                  @endif
                </td>

                <td>
                  <div class="sx-ellipsis" style="font-weight:950" title="{{ $name }}">{{ $name }}</div>

                  <div class="sx-meta">
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
                  <div class="sx-subrow sx-metaClamp">
                    Periodo: <span class="sx-mono">{{ (string)($r->period ?? $period) }}</span>
                    @if($payMethod !== '')
                      <span style="opacity:.55;"> · </span> Método: <span class="sx-mono">{{ $payMethod }}</span>
                    @endif
                    @if($payProvider !== '')
                      <span style="opacity:.55;"> · </span> Prov: <span class="sx-mono">{{ $payProvider }}</span>
                    @endif
                    @if($payStatus !== '')
                      <span style="opacity:.55;"> · </span> St: <span class="sx-mono">{{ $payStatus }}</span>
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

                <td class="sx-right"
                    data-col="abono"
                    data-abono-edo="{{ (float)$abonoEdo }}"
                    data-abono-pay="{{ (float)$abonoPay }}">
                  <div class="sx-mono" data-role="abono-total">{{ $fmtMoney($abono) }}</div>
                  <div class="sx-subrow">
                    EdoCta: <span class="sx-mono" data-role="abono-edo">{{ $fmtMoney($abonoEdo) }}</span>
                    · Pay: <span class="sx-mono" data-role="abono-pay">{{ $fmtMoney($abonoPay) }}</span>
                  </div>
                </td>

                <td class="sx-right" data-col="prev-balance">
                  <span class="sx-pill {{ $prevBalance > 0 ? 'sx-bad' : 'sx-ok' }}" data-role="prev-pill">
                    <span class="dot"></span><span class="sx-mono" data-role="prev-value">{{ $fmtMoney($prevBalance) }}</span>
                  </span>
                </td>

                <td class="sx-right" data-col="saldo-total">
                  <span class="sx-pill {{ $saldoTotal > 0 ? 'sx-warn' : 'sx-ok' }}" data-role="saldo-pill">
                    <span class="dot"></span><span class="sx-mono" data-role="saldo-total">{{ $fmtMoney($saldoTotal) }}</span>
                  </span>
                  <div class="sx-subrow" data-role="saldo-periodo-wrap" @if(!($saldoPeriodo > 0 && $prevBalance > 0)) style="display:none;" @endif>
                    Periodo: <span class="sx-mono" data-role="saldo-periodo">{{ $fmtMoney($saldoPeriodo) }}</span>
                  </div>
                </td>

                <td>
                  <span class="sx-pill {{ $stPill }}"><span class="dot"></span>{{ strtoupper($st) }}</span>
                </td>

                <td class="sx-right sx-actionsTd">
                  <button class="sx-btn sx-btn-soft sx-actOpen"
                          type="button"
                          data-sx-open-drawer="1"
                          data-account="{{ e($aid) }}"
                          data-name="{{ e($name) }}"
                          data-email="{{ e($mail) }}"
                          data-status="{{ e($st) }}"
                          data-pay="{{ e($payMethod) }}"
                          data-show="{{ e($showUrl ?? '') }}"
                          data-pdf="{{ e($pdfUrl ?? '') }}"
                          data-emailurl="{{ e($emailUrl ?? '') }}"
                          data-period="{{ e($rowPeriod) }}"
                          data-period-label="{{ e((string)($r->period ?? $actionPeriod)) }}"
                          data-manage-period="{{ e($rowPeriod) }}"
                          data-range-mode="0">
                    Gestionar
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" style="padding:16px; color:var(--sx-mut); font-weight:900;">
                  Sin resultados para el filtro actual.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div style="padding:12px 18px; border-top:1px solid var(--sx-line); background: color-mix(in oklab, var(--sx-card) 96%, transparent); display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <div style="color:var(--sx-mut); font-weight:850; font-size:12px;">
          Filtros: periodo <span class="sx-mono">{{ $periodLabel }}</span>,
          estatus <span class="sx-mono">{{ strtoupper($status) }}</span>,
          por página <span class="sx-mono">{{ $perPage }}</span>.
        </div>

        @if($isPaginator)
          <div class="p360-pagerWrap">
            <div class="p360-pagerMeta">
              Mostrando
              <span class="p360-mono">{{ (int)($rows->firstItem() ?? 0) }}</span>
              –
              <span class="p360-mono">{{ (int)($rows->lastItem() ?? 0) }}</span>
              de
              <span class="p360-mono">{{ (int)($rows->total() ?? 0) }}</span>
            </div>

            <div class="p360-pagerLinks">
              {!! $rows->onEachSide(1)->appends(request()->query())->links('vendor.pagination.p360') !!}
            </div>
          </div>
        @endif
      </div>

    </div>
  </div>
</div>

<div id="sxToast" class="sx-toast"></div>

<!-- ===== Drawer: Acciones por cuenta (PRO) ===== -->
<div id="sxDrawer" class="sx-drawer" aria-hidden="true">
  <div class="sx-drawer-backdrop" data-sx-drawer-close="1"></div>

  <aside class="sx-drawer-panel" role="dialog" aria-modal="true" aria-label="Acciones de cuenta">
    <div class="sx-drawer-head">
      <div class="sx-drawer-title">
        <div class="sx-drawer-kicker">Acciones</div>

        <div class="sx-drawer-main">
          <span id="sxDAccount" class="sx-pill sx-dim"><span class="dot"></span>#—</span>
          <span id="sxDStatusPill" class="sx-pill sx-dim"><span class="dot"></span>—</span>
        </div>

        <div id="sxDName" class="sx-drawer-sub">—</div>
        <div id="sxDEmail" class="sx-drawer-sub sx-mono">—</div>
      </div>

      <button class="sx-btn sx-btn-ghost" type="button" data-sx-drawer-close="1">Cerrar</button>
    </div>

    <div class="sx-drawer-body">
      <div class="sx-drawer-block">
        <div class="sx-drawer-label">Estatus</div>
        <select class="sx-sel" id="sxDStatus">
          @foreach($statusOptions as $k => $lbl)
            <option value="{{ $k }}">{{ $lbl }}</option>
          @endforeach
        </select>
      </div>

      <div class="sx-drawer-block">
        <div class="sx-drawer-label">Forma de pago</div>
        <select class="sx-sel" id="sxDPay">
          @foreach($payMethodOptions as $k => $lbl)
            <option value="{{ $k }}">{{ $lbl }}</option>
          @endforeach
        </select>
      </div>

      {{-- ✅ Fecha de pago (solo cuando Estatus = Pagado) --}}
      <div class="sx-drawer-block" id="sxDPaidAtWrap" style="display:none;">
        <div class="sx-drawer-label">Fecha de pago</div>
        <input class="sx-in" id="sxDPaidAt" type="datetime-local" value="">
        <div class="sx-drawer-footnote" style="margin-top:6px;">
          Requerido para marcar como <b>Pagado</b>. Si lo dejas vacío, se usará la hora actual.
        </div>
      </div>

      <div class="sx-drawer-row">
        <button id="sxDSave" class="sx-btn sx-btn-primary" type="button">Guardar cambios</button>
      </div>

      <div class="sx-drawer-sep"></div>

      <div class="sx-drawer-row sx-drawer-links">
        <a id="sxDShow" class="sx-btn sx-btn-primary" href="#" style="display:none;">Ver</a>

        <button id="sxDPreview" class="sx-btn sx-btn-soft" type="button" style="display:none;"
                data-sx-pdf-preview="1"
                data-url=""
                data-account=""
                data-period="{{ e($periodLabel) }}">
          Preview
        </button>

        <a id="sxDPdf" class="sx-btn sx-btn-soft" target="_blank" rel="noopener" href="#" style="display:none;">PDF</a>

        <form id="sxDEmailForm" method="POST" action="" style="display:none; margin:0;">
          @csrf
          <input type="hidden" name="to" value="">
          <button class="sx-btn sx-btn-soft" type="submit">Enviar</button>
        </form>
      </div>

      <div class="sx-drawer-footnote">
        Se abre como drawer para no romper la tabla.
      </div>
    </div>
  </aside>
</div>

<!-- ===== Modal: Vista previa PDF ===== -->
<div id="sxPdfModal" class="sx-modal" aria-hidden="true">
  <div class="sx-modal-card" role="dialog" aria-modal="true" aria-label="Vista previa PDF">
    <div class="sx-modal-head">
      <div>
        <div class="sx-modal-title">
          Vista previa · Estado de cuenta
          <span class="sx-modal-sub">Cuenta <span id="sxPdfAccount" class="sx-mono">—</span> · Periodo <span id="sxPdfPeriod" class="sx-mono">—</span></span>
        </div>
      </div>

      <div class="sx-modal-actions">
        <a id="sxPdfOpenNew" class="sx-btn sx-btn-soft" target="_blank" href="#" rel="noopener">Abrir en pestaña</a>
        <a id="sxPdfDownload" class="sx-btn sx-btn-primary" target="_blank" href="#" rel="noopener">Descargar</a>
        <button id="sxPdfClose" class="sx-btn sx-btn-ghost" type="button">Cerrar</button>
      </div>
    </div>

    <div class="sx-modal-body">
      <iframe id="sxPdfFrame" class="sx-modal-iframe" src="about:blank"></iframe>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  'use strict';

  // ==========================================================
  // Helpers
  // ==========================================================
  const $  = (id) => document.getElementById(id);
  const qs = (sel, root) => (root || document).querySelector(sel);
  const qsa= (sel, root) => Array.from((root || document).querySelectorAll(sel));

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
  }

  function fmtMoney(n){
    const v = Number(n || 0);
    return '$' + v.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  }

    function setPillState(el, stateType){
    if(!el) return;
    el.classList.remove('sx-ok','sx-warn','sx-bad','sx-dim');

    if(stateType === 'ok') el.classList.add('sx-ok');
    else if(stateType === 'warn') el.classList.add('sx-warn');
    else if(stateType === 'bad') el.classList.add('sx-bad');
    else el.classList.add('sx-dim');
  }

  function updateRowFinancials(tr, payload, selectedStatus){
    if(!tr || !payload) return;

    const totalShown   = Number(payload.total || 0);
    const abonoTotal   = Number(payload.abono || 0);
    const saldoCurrent = Number(
      typeof payload.saldo_current !== 'undefined'
        ? payload.saldo_current
        : (payload.saldo || 0)
    );
    const totalDue     = Number(
      typeof payload.total_due !== 'undefined'
        ? payload.total_due
        : (payload.saldo || 0)
    );

    const prevBalance = Math.max(0, Number(totalDue - saldoCurrent));

    const abonoCell = tr.querySelector('[data-col="abono"]');
    if(abonoCell){
      const totalNode = abonoCell.querySelector('[data-role="abono-total"]');
      if(totalNode) totalNode.textContent = fmtMoney(abonoTotal);

      const edoNode = abonoCell.querySelector('[data-role="abono-edo"]');
      const payNode = abonoCell.querySelector('[data-role="abono-pay"]');

      const currentEdo = Number(abonoCell.getAttribute('data-abono-edo') || 0);
      let currentPay   = Number(abonoCell.getAttribute('data-abono-pay') || 0);

      if(String(selectedStatus).toLowerCase() === 'pagado'){
        currentPay = Math.max(currentPay, abonoTotal);
        abonoCell.setAttribute('data-abono-pay', String(currentPay));
      }

      if(edoNode) edoNode.textContent = fmtMoney(currentEdo);
      if(payNode) payNode.textContent = fmtMoney(currentPay);
    }

    const prevCell = tr.querySelector('[data-col="prev-balance"]');
    if(prevCell){
      const prevVal  = prevCell.querySelector('[data-role="prev-value"]');
      const prevPill = prevCell.querySelector('[data-role="prev-pill"]');

      if(prevVal) prevVal.textContent = fmtMoney(prevBalance);
      setPillState(prevPill, prevBalance > 0 ? 'bad' : 'ok');
    }

    const saldoCell = tr.querySelector('[data-col="saldo-total"]');
    if(saldoCell){
      const saldoVal   = saldoCell.querySelector('[data-role="saldo-total"]');
      const saldoPill  = saldoCell.querySelector('[data-role="saldo-pill"]');
      const saldoWrap  = saldoCell.querySelector('[data-role="saldo-periodo-wrap"]');
      const saldoPer   = saldoCell.querySelector('[data-role="saldo-periodo"]');

      if(saldoVal) saldoVal.textContent = fmtMoney(totalDue);
      setPillState(saldoPill, totalDue > 0 ? 'warn' : 'ok');

      if(saldoPer) saldoPer.textContent = fmtMoney(saldoCurrent);

      if(saldoWrap){
        if(saldoCurrent > 0 && prevBalance > 0){
          saldoWrap.style.display = '';
        } else {
          saldoWrap.style.display = 'none';
        }
      }
    }

    const totalCell = tr.querySelector('td:nth-child(5) .sx-mono');
    if(totalCell){
      totalCell.textContent = fmtMoney(totalShown);
    }
  }

  // ==========================================================
  // TOAST PRO (cola + progress + actions + close)
  // Requiere un contenedor: <div id="sxToast"></div>
  // ==========================================================
  const toastEl = $('sxToast');
  let toastTimer = null;
  let toastAnimTimer = null;
  const toastQueue = [];
  let toastShowing = false;

  function toastIcon(type){
    if(type === 'ok') return '✓';
    if(type === 'warn') return '!';
    if(type === 'bad') return '✕';
    return 'i';
  }
  function toastTitle(type){
    if(type === 'ok') return 'Listo';
    if(type === 'warn') return 'Atención';
    if(type === 'bad') return 'Error';
    return 'Info';
  }

  function renderToast(payload){
    if(!toastEl) return;

    const msg = String(payload && payload.msg ? payload.msg : '');
    const type = String(payload && payload.type ? payload.type : 'info');
    const ms = Math.max(800, Number(payload && payload.ms ? payload.ms : 2200));
    const actions = (payload && Array.isArray(payload.actions)) ? payload.actions : null;

    toastEl.setAttribute('data-type', type);

    const ic = toastIcon(type);
    const tt = toastTitle(type);

    const actionsHtml = (actions && actions.length)
      ? `<div class="sx-toast-actions">${
          actions.map((a,i)=>{
            const label = String(a && a.label ? a.label : 'Acción');
            const kind  = String(a && a.kind  ? a.kind  : 'button'); // button|link
            const href  = String(a && a.href  ? a.href  : '#');
            if(kind === 'link'){
              return `<a class="sx-toast-btn" data-sx-toast-act="${i}" href="${escapeHtml(href)}">${escapeHtml(label)}</a>`;
            }
            return `<button class="sx-toast-btn" data-sx-toast-act="${i}" type="button">${escapeHtml(label)}</button>`;
          }).join('')
        }</div>`
      : '';

    toastEl.innerHTML = `
      <div class="sx-toast-inner" role="status" aria-live="polite">
        <div class="sx-toast-ic">${escapeHtml(ic)}</div>
        <div class="sx-toast-body">
          <div class="sx-toast-title">${escapeHtml(tt)}</div>
          <div class="sx-toast-msg">${escapeHtml(msg)}</div>
          ${actionsHtml}
        </div>
        <button class="sx-toast-x" type="button" aria-label="Cerrar">×</button>
      </div>
      <div class="sx-toast-bar" aria-hidden="true"><i></i></div>
    `;

    // close
    const x = toastEl.querySelector('.sx-toast-x');
    if(x){
      x.addEventListener('click', function(){ hideToast(true); }, { once:true });
    }

    // actions
    if(actions && actions.length){
      qsa('[data-sx-toast-act]', toastEl).forEach((node)=>{
        node.addEventListener('click', function(ev){
          const idx = parseInt(node.getAttribute('data-sx-toast-act') || '-1', 10);
          const act = actions[idx];
          if(!act) return;

          const kind = String(act.kind || '').toLowerCase();
          if(kind === 'link'){
            // deja navegar; cerramos toast para UX limpia
            hideToast(true);
            return;
          }

          ev.preventDefault();
          try{
            if(typeof act.onClick === 'function') act.onClick();
          }catch(e){}
          hideToast(true);
        });
      });
    }

    // progress bar
    const bar = toastEl.querySelector('.sx-toast-bar > i');
    if(bar){
      bar.style.transition = 'none';
      bar.style.transform = 'scaleX(1)';
      void bar.offsetWidth; // reflow
      bar.style.transition = `transform ${ms}ms linear`;
      bar.style.transform = 'scaleX(0)';
    }
  }

  function showToastNow(payload){
    if(!toastEl) { try{ console.log(payload); }catch(e){}; return; }

    // clear timers
    if(toastTimer) clearTimeout(toastTimer);
    if(toastAnimTimer) clearTimeout(toastAnimTimer);

    toastEl.classList.remove('hide');
    toastEl.classList.add('on');

    renderToast(payload);

    toastAnimTimer = setTimeout(() => toastEl.classList.add('show'), 10);
    toastShowing = true;

    const ms = Math.max(800, Number(payload && payload.ms ? payload.ms : 2200));
    toastTimer = setTimeout(() => hideToast(false), ms);
  }

  function nextToast(){
    if(toastQueue.length === 0){
      toastShowing = false;
      return;
    }
    showToastNow(toastQueue.shift());
  }

  function hideToast(forceImmediate){
    if(!toastEl) return;

    if(toastTimer){ clearTimeout(toastTimer); toastTimer = null; }
    if(toastAnimTimer){ clearTimeout(toastAnimTimer); toastAnimTimer = null; }

    toastEl.classList.remove('show');
    toastEl.classList.add('hide');

    const done = function(){
      toastEl.classList.remove('on','hide');
      toastEl.innerHTML = '';
      nextToast();
    };

    if(forceImmediate) return done();
    setTimeout(done, 160);
  }

  /**
   * API:
   * sxToast('mensaje', 'ok|warn|bad|info')
   * sxToast({ msg, type, ms, actions:[{label, kind:'button'|'link', href?, onClick?}] })
   */
  function sxToast(a, b){
    let payload = null;

    if(typeof a === 'object' && a){
      payload = {
        msg: String(a.msg || ''),
        type: String(a.type || 'info'),
        ms: Number(a.ms || 2200),
        actions: Array.isArray(a.actions) ? a.actions : null
      };
    }else{
      payload = {
        msg: String(a || ''),
        type: String(b || 'info'),
        ms: 2200,
        actions: null
      };
    }

    if(toastShowing){
      toastQueue.push(payload);
      return;
    }
    showToastNow(payload);
  }
  window.sxToast = sxToast;

  // Escape cierra toast si está visible
  document.addEventListener('keydown', function(ev){
    if(ev.key === 'Escape' && toastEl && toastEl.classList.contains('on')){
      hideToast(true);
    }
  });

  // ==========================================================
  // BULK UI (checkboxes + barra + filtro)
  // Requiere:
  // - checkboxes row: .sx-row (type=checkbox, value=account_id)
  // - master checkbox: #sxCkAll
  // - bulkbar: #sxBulkbar
  // - count: #sxBulkCount
  // - bulk form: #sxBulkForm  (input name="account_ids" + hidden period si aplica)
  // ==========================================================
  const bulkbar   = $('sxBulkbar');
  const bulkCount = $('sxBulkCount');
  const ckAll     = $('sxCkAll');
  const bulkForm  = $('sxBulkForm');

  function rows(){ return qsa('.sx-row'); }
  function selectedIds(){ return rows().filter(x => x && x.checked).map(x => String(x.value || '').trim()).filter(Boolean); }

  function paintSelectedRows(){
    rows().forEach(ck => {
      const tr = ck.closest('tr');
      if(!tr) return;
      if(ck.checked) tr.classList.add('is-selected');
      else tr.classList.remove('is-selected');
    });
  }

  function updateBulk(){
    const ids = selectedIds();

    if(bulkCount) bulkCount.textContent = String(ids.length);

    if(bulkbar){
      if(ids.length > 0) bulkbar.classList.add('on');
      else bulkbar.classList.remove('on');
    }

    if(ckAll){
      const r = rows();
      if(!r.length){
        ckAll.checked = false;
        ckAll.indeterminate = false;
      }else{
        const all = r.every(x => x.checked);
        const any = r.some(x => x.checked);
        ckAll.checked = all;
        ckAll.indeterminate = (!all && any);
      }
    }

    paintSelectedRows();
  }

  function bindBulkForm(ids){
    if(!bulkForm) return false;
    const inp = bulkForm.querySelector('input[name="account_ids"]');
    if(inp) inp.value = ids.join(',');
    return true;
  }

  // API pública (por si tus botones llaman onclick="...")
  window.sxToggleAll = function(master){
    const on = !!(master && master.checked);
    rows().forEach(x => x.checked = on);
    updateBulk();
  };
  window.sxSync = function(){ updateBulk(); };
  window.sxSelectAll = function(on){
    rows().forEach(x => x.checked = !!on);
    if(ckAll){ ckAll.checked = !!on; ckAll.indeterminate = false; }
    updateBulk();
  };
  window.sxClear = function(){
    rows().forEach(x => x.checked = false);
    if(ckAll){ ckAll.checked = false; ckAll.indeterminate = false; }
    updateBulk();
  };

  // Filtro “solo seleccionadas” (mantén tus parámetros)
  function parseIdsParam(v){
    v = (v == null) ? '' : String(v);
    return v.split(',').map(s => s.trim()).filter(Boolean);
  }

  function applyOnlySelectedFromQuery(){
    try{
      const sp = new URLSearchParams(window.location.search || '');
      const on = sp.get('only_selected') === '1' || sp.get('only_selected') === 'true';
      if(!on) return;

      const ids = new Set(parseIdsParam(sp.get('ids')));
      if(!ids.size) return;

      rows().forEach(ck => {
        const id = String(ck.value || '').trim();
        ck.checked = ids.has(id);
      });

      // master: si filtraste, normalmente quieres "todo lo visible" marcado
      if(ckAll){ ckAll.checked = true; ckAll.indeterminate = false; }
      updateBulk();
    }catch(e){}
  }

  // IMPORTANTE: base URL desde backend si existe; si no, cae a current pathname
  const routeIndex = (function(){
    try{
      return {!! json_encode($routeIndex ?? null) !!} || (window.location.origin + window.location.pathname);
    }catch(e){
      return (window.location.origin + window.location.pathname);
    }
  })();

  window.sxFilterSelected = function(){
    const ids = selectedIds();
    if(!ids.length){
      sxToast('Selecciona al menos una cuenta para filtrar.', 'warn');
      return;
    }
    const sp = new URLSearchParams(window.location.search || '');
    sp.set('only_selected','1');
    sp.set('ids', ids.join(','));
    sp.delete('page');
    window.location.href = String(routeIndex) + '?' + sp.toString();
  };

  // Envío masivo (usa el bulkForm si existe action; si no, solo copia payload)
  window.sxBulkSend = function(){
    const ids = selectedIds();
    if(!ids.length){
      sxToast('Selecciona al menos una cuenta.', 'warn');
      return;
    }

    // Si existe form real -> submit
    if(bulkForm && String(bulkForm.getAttribute('action') || '').trim() !== ''){
      bindBulkForm(ids);

      // Confirmación elegante
      sxToast({
        type: 'warn',
        msg: `¿Enviar estado de cuenta a ${ids.length} cuenta(s)?`,
        ms: 6500,
        actions: [
          { label:'Cancelar', kind:'button', onClick: function(){} },
          { label:'Enviar', kind:'button', onClick: function(){ bulkForm.submit(); } }
        ]
      });
      return;
    }

    // Fallback: copia payload
     const payload = { period: defaultManagePeriod, account_ids: ids.join(',') };
    try{ navigator.clipboard.writeText(JSON.stringify(payload)); }catch(e){}
    sxToast('Se copió al portapapeles (period + account_ids CSV). Falta configurar bulkForm/action.', 'info');
  };

  // Eventos: si cambias un checkbox, actualiza
  document.addEventListener('change', function(ev){
    const t = ev.target;

    // master
    if(t && t === ckAll){
      window.sxToggleAll(ckAll);
      return;
    }

    // row checkbox
    if(t && t.classList && t.classList.contains('sx-row')){
      updateBulk();
      return;
    }
  });

  // ==========================================================
  // PDF Preview Modal
  // Requiere:
  // - #sxPdfModal, #sxPdfFrame
  // - opcionales: #sxPdfClose #sxPdfOpenNew #sxPdfDownload #sxPdfAccount #sxPdfPeriod
  // Botón: [data-sx-pdf-preview="1"] con data-url,data-account,data-period
  // ==========================================================
  const pdfModal    = $('sxPdfModal');
  const pdfFrame    = $('sxPdfFrame');
  const pdfClose    = $('sxPdfClose');
  const pdfOpenNew  = $('sxPdfOpenNew');
  const pdfDownload = $('sxPdfDownload');
  const pdfAccLbl   = $('sxPdfAccount');
  const pdfPerLbl   = $('sxPdfPeriod');

  function withParams(url, params){
    try{
      const u = new URL(url, window.location.origin);
      Object.keys(params || {}).forEach(k => {
        const v = params[k];
        if (v === null || typeof v === 'undefined') return;
        u.searchParams.set(k, String(v));
      });
      return u.toString();
    }catch(e){
      const glue = (url.indexOf('?') >= 0) ? '&' : '?';
      const q = Object.keys(params||{}).map(k => encodeURIComponent(k)+'='+encodeURIComponent(String(params[k]))).join('&');
      return url + glue + q;
    }
  }

  function openPdfModal(opts){
    if(!pdfModal || !pdfFrame) return;

    const url = String((opts && opts.url) || '').trim();
    const acc = String((opts && opts.account) || '—');
    const per = String((opts && opts.period) || '—');

    if(url === ''){
      sxToast('No hay URL de PDF para vista previa.', 'bad');
      return;
    }

    const previewUrl = withParams(url, { inline: 1, modal: 1 });

    if(pdfAccLbl) pdfAccLbl.textContent = acc || '—';
    if(pdfPerLbl) pdfPerLbl.textContent = per || '—';

    if(pdfOpenNew)  pdfOpenNew.setAttribute('href', previewUrl);
    if(pdfDownload) pdfDownload.setAttribute('href', url);

    pdfFrame.setAttribute('src', 'about:blank');
    setTimeout(() => pdfFrame.setAttribute('src', previewUrl), 30);

    pdfModal.classList.add('on');
    pdfModal.setAttribute('aria-hidden','false');
  }

  function closePdfModal(){
    if(!pdfModal) return;
    pdfModal.classList.remove('on');
    pdfModal.setAttribute('aria-hidden','true');
    if(pdfFrame) pdfFrame.setAttribute('src', 'about:blank');
  }

  if(pdfClose){
    pdfClose.addEventListener('click', function(ev){
      ev.preventDefault();
      closePdfModal();
    });
  }
  if(pdfModal){
    pdfModal.addEventListener('mousedown', function(ev){
      if(ev.target === pdfModal) closePdfModal();
    });
  }

  // Delegación click para abrir preview
  document.addEventListener('click', function(ev){
    const btn = ev.target && ev.target.closest ? ev.target.closest('[data-sx-pdf-preview="1"]') : null;
    if(!btn) return;

    ev.preventDefault();
    openPdfModal({
      url: btn.getAttribute('data-url') || '',
      account: btn.getAttribute('data-account') || '—',
      period: btn.getAttribute('data-period') || '—',
    });
  });

  // ==========================================================
  // Drawer (abre/cierra)
  // Requiere:
  // - #sxDrawer
  // - botón abre: [data-sx-open-drawer="1"] con data-account,data-name,data-email,...
  // - botón cierra: [data-sx-drawer-close="1"]
  // ==========================================================
  const drawer = $('sxDrawer');

    // ===== Drawer: form controls
  const dStatus   = $('sxDStatus');
  const dPay      = $('sxDPay');
  const dPaidWrap = $('sxDPaidAtWrap');
  const dPaidAt   = $('sxDPaidAt');
  const dSave     = $('sxDSave');

  // Backend endpoints/CSRF
  const statusEndpoint = (function(){
    try { return {!! json_encode($statusEndpoint ?? '') !!}; } catch(e){ return ''; }
  })();

   const defaultManagePeriod = (function(){
    try { return {!! json_encode($singleManagePeriod ?? '') !!}; } catch(e){ return ''; }
  })();

  function csrfToken(){
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? String(m.getAttribute('content')||'') : '';
  }

  function nowLocalDatetimeValue(){
    const d = new Date();
    const pad = (n)=> String(n).padStart(2,'0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function setPaidAtVisibility(){
    const st = String(dStatus && dStatus.value ? dStatus.value : '').toLowerCase().trim();
    const on = (st === 'pagado');
    if(dPaidWrap) dPaidWrap.style.display = on ? '' : 'none';
    if(on && dPaidAt && !String(dPaidAt.value||'').trim()){
      dPaidAt.value = nowLocalDatetimeValue();
    }
    if(!on && dPaidAt){
      dPaidAt.value = '';
    }
  }

  // Estado actual del drawer
  let currentDrawer = {
    account: '',
    email: '',
    name: '',
    rowEl: null,
    managePeriod: '',
    periodLabel: '',
    period: '',
    isRangeMode: false,
  };

  function closeDrawer(){
    if(!drawer) return;
    drawer.classList.remove('on');
    drawer.setAttribute('aria-hidden','true');
    document.body.classList.remove('sx-drawer-open');
  }

  function openDrawer(payload){
    if(!drawer) return;

    // Si tu drawer tiene campos, aquí solo los llenas si existen
    const dAccount = $('sxDAccount');
    const dName    = $('sxDName');
    const dEmail   = $('sxDEmail');

    const acc = String(payload && payload.account ? payload.account : '').trim();
    const nm  = String(payload && payload.name ? payload.name : '—');
    const em  = String(payload && payload.email ? payload.email : '—');

    if(dAccount) dAccount.innerHTML = '<span class="dot"></span>#' + escapeHtml(acc || '—');
    if(dName) dName.textContent = nm;
    if(dEmail) dEmail.textContent = em;

        // ✅ pill estatus en drawer
    const dStatusPill = $('sxDStatusPill');
    if(dStatusPill){
      const stNow = String(payload && payload.status ? payload.status : (dStatus ? dStatus.value : '')).toLowerCase().trim();
      dStatusPill.innerHTML = '<span class="dot"></span>' + (stNow ? stNow.toUpperCase() : '—');
      dStatusPill.classList.remove('sx-ok','sx-warn','sx-bad','sx-dim');
      if(stNow === 'pagado') dStatusPill.classList.add('sx-ok');
      else if(stNow === 'vencido') dStatusPill.classList.add('sx-bad');
      else if(stNow === 'pendiente' || stNow === 'parcial') dStatusPill.classList.add('sx-warn');
      else dStatusPill.classList.add('sx-dim');
    }

    // set selects
    if(dStatus){
      const st = String(payload && payload.status ? payload.status : '').toLowerCase().trim();
      dStatus.value = ['pendiente','parcial','pagado','vencido','sin_mov'].includes(st) ? st : 'pendiente';
    }
    if(dPay){
      const pm = String(payload && payload.pay ? payload.pay : '').toLowerCase().trim();
      dPay.value = pm || '';
    }

    // store current
    const exactPeriod = String(payload && payload.period ? payload.period : '').trim();
    const managePeriod = String(payload && payload.managePeriod ? payload.managePeriod : (exactPeriod || defaultManagePeriod || '')).trim();
    const rowKey = (acc && managePeriod) ? ('sxRow-' + acc + '-' + String(managePeriod).replace(/-/g, '')) : '';

    currentDrawer.account = acc || '';
    currentDrawer.email = em || '';
    currentDrawer.name = nm || '';
    currentDrawer.period = exactPeriod || managePeriod || '';
    currentDrawer.managePeriod = managePeriod || '';
    currentDrawer.periodLabel = String(payload && payload.periodLabel ? payload.periodLabel : (managePeriod || '—')).trim();
    currentDrawer.rowEl = rowKey ? document.getElementById(rowKey) : null;
    currentDrawer.isRangeMode = String(payload && payload.isRangeMode ? payload.isRangeMode : '0') === '1';

    setPaidAtVisibility();

    // links opcionales
    const dShow     = $('sxDShow');
    const dPdf      = $('sxDPdf');
    const dPreview  = $('sxDPreview');
    const dEmailForm= $('sxDEmailForm');

    if(dShow){
      const href = String(payload && payload.show ? payload.show : '');
      if(href){ dShow.style.display = ''; dShow.setAttribute('href', href); }
      else dShow.style.display = 'none';
    }

    if(dPdf){
      const href = String(payload && payload.pdf ? payload.pdf : '');
      if(href){ dPdf.style.display = ''; dPdf.setAttribute('href', href); }
      else dPdf.style.display = 'none';
    }

    if(dPreview){
      const href = String(payload && payload.pdf ? payload.pdf : '');
      if(href){
        dPreview.style.display = '';
        dPreview.setAttribute('data-sx-pdf-preview','1');
        dPreview.setAttribute('data-url', href);
        dPreview.setAttribute('data-account', acc || '—');
        dPreview.setAttribute('data-period', String(payload && payload.period ? payload.period : '—'));
      }else{
        dPreview.style.display = 'none';
      }
    }

    if(dEmailForm){
      const action = String(payload && payload.emailurl ? payload.emailurl : '');
      if(action){
        dEmailForm.style.display = '';
        dEmailForm.setAttribute('action', action);
        const toInp = dEmailForm.querySelector('input[name="to"]');
        if(toInp) toInp.value = (em && em !== '—') ? em : '';
      }else{
        dEmailForm.style.display = 'none';
        dEmailForm.setAttribute('action','');
      }
    }

    drawer.classList.add('on');
    drawer.setAttribute('aria-hidden','false');
    document.body.classList.add('sx-drawer-open');
  }

  // Delegación clicks drawer open/close
  document.addEventListener('click', function(ev){
    const openBtn = ev.target && ev.target.closest ? ev.target.closest('[data-sx-open-drawer="1"]') : null;
    if(openBtn){
      ev.preventDefault();
      openDrawer({
        account: openBtn.getAttribute('data-account') || '',
        name: openBtn.getAttribute('data-name') || '—',
        email: openBtn.getAttribute('data-email') || '—',
        status: openBtn.getAttribute('data-status') || '',
        pay: openBtn.getAttribute('data-pay') || '',
        show: openBtn.getAttribute('data-show') || '',
        pdf: openBtn.getAttribute('data-pdf') || '',
        emailurl: openBtn.getAttribute('data-emailurl') || '',
        period: openBtn.getAttribute('data-period') || '',
        periodLabel: openBtn.getAttribute('data-period-label') || '—',
        managePeriod: openBtn.getAttribute('data-manage-period') || '',
        isRangeMode: openBtn.getAttribute('data-range-mode') || '0',
      });
      return;
    }

    const closeBtn = ev.target && ev.target.closest ? ev.target.closest('[data-sx-drawer-close="1"]') : null;
    if(closeBtn){
      ev.preventDefault();
      closeDrawer();
      return;
    }
  });

  // Escape: cierra modal/drawer
  document.addEventListener('keydown', function(ev){
    if(ev.key !== 'Escape') return;
    if(pdfModal && pdfModal.classList.contains('on')) closePdfModal();
    if(drawer && drawer.classList.contains('on')) closeDrawer();
  });

    // ==========================================================
  // Drawer SAVE (POST) + visibilidad paid_at
  // ==========================================================

  // Mostrar/ocultar fecha de pago al cambiar estatus
  if(dStatus){
    dStatus.addEventListener('change', function(){
      setPaidAtVisibility();
      // (opcional) actualiza pill en drawer
      const pill = $('sxDStatusPill');
      if(pill){
        const st = String(dStatus.value||'').trim().toLowerCase();
        pill.innerHTML = '<span class="dot"></span>' + (st ? st.toUpperCase() : '—');
        pill.classList.remove('sx-ok','sx-warn','sx-bad','sx-dim');
        if(st === 'pagado') pill.classList.add('sx-ok');
        else if(st === 'vencido') pill.classList.add('sx-bad');
        else if(st === 'pendiente' || st === 'parcial') pill.classList.add('sx-warn');
        else pill.classList.add('sx-dim');
      }
    });
  }

  async function sxSaveDrawer(){
    
    if(!statusEndpoint){
      sxToast('No está configurado el endpoint para guardar estatus (admin.billing.statements.status).', 'bad');
      return;
    }

    const st  = String(dStatus ? (dStatus.options[dStatus.selectedIndex]?.value || dStatus.value || '') : '').trim().toLowerCase();
    const pay = String(dPay ? (dPay.options[dPay.selectedIndex]?.value || dPay.value || '') : '').trim().toLowerCase();

    const allowedStatuses = ['pendiente','parcial','pagado','vencido','sin_mov'];
    if(!allowedStatuses.includes(st)){
      sxToast('Estatus inválido en el drawer. Vuelve a seleccionar el estatus.', 'bad');
      return;
    }

    let paidAt = '';
    if(String(st).toLowerCase() === 'pagado'){
      paidAt = String(dPaidAt && dPaidAt.value ? dPaidAt.value : '').trim();
      if(!paidAt) paidAt = nowLocalDatetimeValue();
    }

     const body = {
      account_id: currentDrawer.account,
      period: currentDrawer.managePeriod || currentDrawer.period || defaultManagePeriod,
      status: st,
      pay_method: pay,
      paid_at: paidAt || null,
    };

    // UI lock
    if(dSave){
      dSave.disabled = true;
      dSave.setAttribute('data-loading','1');
      dSave.textContent = 'Guardando...';
    }

    console.log('[BILLING][SAVE]', {
      account_id: currentDrawer.account,
      period: currentDrawer.managePeriod || defaultManagePeriod,
      status: st,
      pay_method: pay,
      paid_at: paidAt || null,
    });

    try{
      const res = await fetch(statusEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
        credentials: 'same-origin',
      });

      const isJson = (res.headers.get('content-type') || '').includes('application/json');
      const data = isJson ? await res.json().catch(()=>null) : await res.text().catch(()=>'');

      if(!res.ok){
        let msg = `Falló (${res.status}) al guardar.`;
        if(res.status === 419) msg = 'Sesión/CSRF expirada (419). Refresca la página y reintenta.';
        if(isJson && data){
          if(data.message) msg = String(data.message);
          else if(data.errors){
            const firstKey = Object.keys(data.errors)[0];
            if(firstKey) msg = String(data.errors[firstKey][0] || msg);
          }
        }
        sxToast(msg, 'bad');
        return;
      }

      sxToast('Cambios guardados.', 'ok');

            try{
        const tr = currentDrawer.rowEl;
        if(tr){
          const finalStatus = String((data && (data.status || data.status_auto)) || st).toLowerCase().trim();

          // Pill estatus
          const pill = tr.querySelector('td:nth-child(9) .sx-pill');
          if(pill){
            pill.innerHTML = '<span class="dot"></span>' + String(finalStatus || st).toUpperCase();
            pill.classList.remove('sx-ok','sx-warn','sx-bad','sx-dim');

            if(finalStatus === 'pagado') pill.classList.add('sx-ok');
            else if(finalStatus === 'vencido') pill.classList.add('sx-bad');
            else if(finalStatus === 'pendiente' || finalStatus === 'parcial') pill.classList.add('sx-warn');
            else pill.classList.add('sx-dim');
          }

          // Finanzas visibles
          updateRowFinancials(tr, data, finalStatus || st);

          // Contacto / Meta
          const meta = tr.querySelector('td:nth-child(4) .sx-subrow');
          if(meta){
            let html = meta.innerHTML;

            const finalPayMethod = String((data && data.pay_method) || pay || '—');
            const finalPaidAt = String((data && data.paid_at) || (String(finalStatus).toLowerCase() === 'pagado' ? (paidAt || nowLocalDatetimeValue()) : ''));

            if(html.includes('Método:')){
              html = html.replace(/Método:\s*<span class="sx-mono">.*?<\/span>/, 'Método: <span class="sx-mono">'+escapeHtml(finalPayMethod)+'</span>');
            }else{
              html += '<span style="opacity:.55;"> · </span> Método: <span class="sx-mono">'+escapeHtml(finalPayMethod)+'</span>';
            }

            if(String(finalStatus).toLowerCase() === 'pagado'){
              if(html.includes('Últ. pago:')){
                html = html.replace(/Últ\.\s*pago:\s*<span class="sx-mono">.*?<\/span>/, 'Últ. pago: <span class="sx-mono">'+escapeHtml(finalPaidAt)+'</span>');
              }else{
                html += '<br>Últ. pago: <span class="sx-mono">'+escapeHtml(finalPaidAt)+'</span>';
              }
            }

            meta.innerHTML = html;
          }

          // También sincroniza atributos del botón Gestionar para siguiente apertura
          const openBtn = tr.querySelector('[data-sx-open-drawer="1"]');
          if(openBtn){
            openBtn.setAttribute('data-status', finalStatus || st);
            openBtn.setAttribute('data-pay', String((data && data.pay_method) || pay || ''));
          }
        }
      }catch(e){
        console.error(e);
      }

      closeDrawer();
      window.location.reload();

    }catch(err){
      sxToast('Error de red al guardar. Revisa consola/network.', 'bad');
    }finally{
      if(dSave){
        dSave.disabled = false;
        dSave.removeAttribute('data-loading');
        dSave.textContent = 'Guardar cambios';
      }
    }
  }

  if(dSave){
    dSave.addEventListener('click', function(ev){
      ev.preventDefault();
      sxSaveDrawer();
    });
  }

    // ==========================================================
  // FILTERS PRO
  // ==========================================================
  const sxFilterForm    = $('sxFilterForm');
  const sxFilterQ       = $('sxFilterQ');
  const sxFilterAccount = $('sxFilterAccount');
  const sxFilterStatus  = $('sxFilterStatus');
  const sxFilterPerPage = $('sxFilterPerPage');
  const sxFilterReset   = $('sxFilterReset');

  const sxDateRoot    = $('sxDateFilter');
  const sxDateTrigger = $('sxDateTrigger');
  const sxDatePanel   = $('sxDatePanel');
  const sxDateText    = $('sxDateFilterText');

  const sxPeriodInp   = $('sxPeriod');
  const sxFromInp     = $('sxPeriodFrom');
  const sxToInp       = $('sxPeriodTo');

  const sxPickerFrom  = $('sxPickerFrom');
  const sxPickerTo    = $('sxPickerTo');

  const sxDateApply   = $('sxDateApply');
  const sxDateCancel  = $('sxDateCancel');

  function sxIsYm(v){
    return /^\d{4}-(0[1-9]|1[0-2])$/.test(String(v || '').trim());
  }

  function sxCurrentYm(){
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    return `${y}-${m}`;
  }

  function sxSubMonths(ym, qty){
    if(!sxIsYm(ym)) return sxCurrentYm();
    const [y, m] = ym.split('-').map(Number);
    const d = new Date(y, m - 1, 1);
    d.setMonth(d.getMonth() - qty);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  }

  function sxNormalizeRange(from, to){
    from = sxIsYm(from) ? from : '';
    to   = sxIsYm(to) ? to : '';

    if(from !== '' && to !== '' && from > to){
      const tmp = from;
      from = to;
      to = tmp;
    }

    return { from, to };
  }

  function sxRemoveParam(name){
    if(!sxFilterForm) return;
    qsa(`input[name="${name}"]`, sxFilterForm).forEach(function(node){
      if(node && !['sxPeriod','sxPeriodFrom','sxPeriodTo'].includes(node.id || '')){
        node.remove();
      }
    });
  }

  function sxClearTransientQuery(){
    sxRemoveParam('page');
    sxRemoveParam('only_selected');
    sxRemoveParam('onlySelected');
    sxRemoveParam('ids');
  }

  function sxSyncDateText(){
    const p  = sxIsYm(sxPeriodInp && sxPeriodInp.value ? sxPeriodInp.value : '') ? sxPeriodInp.value : sxCurrentYm();
    const pf = sxIsYm(sxFromInp && sxFromInp.value ? sxFromInp.value : '') ? sxFromInp.value : '';
    const pt = sxIsYm(sxToInp && sxToInp.value ? sxToInp.value : '') ? sxToInp.value : '';

    let txt = p;
    if(pf !== '' || pt !== ''){
      txt = `${pf !== '' ? pf : p} a ${pt !== '' ? pt : p}`;
    }

    if(sxDateText) sxDateText.textContent = txt;
  }

  function sxSubmitFilters(){
    if(!sxFilterForm) return;
    sxClearTransientQuery();
    sxFilterForm.submit();
  }

  function sxApplyDateFilter(autoSubmit = true){
    let from = sxPickerFrom ? String(sxPickerFrom.value || '').trim() : '';
    let to   = sxPickerTo ? String(sxPickerTo.value || '').trim() : '';

    const norm = sxNormalizeRange(from, to);
    from = norm.from;
    to   = norm.to;

    const opPeriod = to || from || sxCurrentYm();

    if(sxFromInp) sxFromInp.value = from;
    if(sxToInp)   sxToInp.value   = to;
    if(sxPeriodInp) sxPeriodInp.value = opPeriod;

    sxSyncDateText();
    sxCloseDatePanel();

    if(autoSubmit){
      sxSubmitFilters();
    }
  }

  function sxOpenDatePanel(){
    if(!sxDatePanel) return;
    sxDatePanel.hidden = false;
    sxDateRoot && sxDateRoot.classList.add('is-open');
  }

  function sxCloseDatePanel(){
    if(!sxDatePanel) return;
    sxDatePanel.hidden = true;
    sxDateRoot && sxDateRoot.classList.remove('is-open');
  }

  function sxResetFilters(){
    if(sxFilterQ) sxFilterQ.value = '';
    if(sxFilterAccount) sxFilterAccount.value = '';
    if(sxFilterStatus) sxFilterStatus.value = 'all';
    if(sxFilterPerPage) sxFilterPerPage.value = '25';

    if(sxPickerFrom) sxPickerFrom.value = '';
    if(sxPickerTo)   sxPickerTo.value   = '';

    if(sxFromInp) sxFromInp.value = '';
    if(sxToInp)   sxToInp.value   = '';
    if(sxPeriodInp) sxPeriodInp.value = sxCurrentYm();

    sxSyncDateText();
    sxClearTransientQuery();
    if(sxFilterForm) sxFilterForm.submit();
  }

  if(sxDateTrigger){
    sxDateTrigger.addEventListener('click', function(ev){
      ev.preventDefault();
      if(!sxDatePanel) return;
      if(sxDatePanel.hidden) sxOpenDatePanel();
      else sxCloseDatePanel();
    });
  }

  if(sxDateApply){
    sxDateApply.addEventListener('click', function(ev){
      ev.preventDefault();
      sxApplyDateFilter(true);
    });
  }

  if(sxDateCancel){
    sxDateCancel.addEventListener('click', function(ev){
      ev.preventDefault();
      sxCloseDatePanel();
    });
  }

  qsa('[data-sx-date-quick]').forEach(function(btn){
    btn.addEventListener('click', function(ev){
      ev.preventDefault();
      const mode = String(btn.getAttribute('data-sx-date-quick') || '').trim();
      const nowYm = sxCurrentYm();

      if(mode === 'current'){
        if(sxPickerFrom) sxPickerFrom.value = '';
        if(sxPickerTo)   sxPickerTo.value   = nowYm;
        sxApplyDateFilter(true);
        return;
      }

      if(mode === 'last3'){
        if(sxPickerFrom) sxPickerFrom.value = sxSubMonths(nowYm, 2);
        if(sxPickerTo)   sxPickerTo.value   = nowYm;
        sxApplyDateFilter(true);
        return;
      }

      if(mode === 'last6'){
        if(sxPickerFrom) sxPickerFrom.value = sxSubMonths(nowYm, 5);
        if(sxPickerTo)   sxPickerTo.value   = nowYm;
        sxApplyDateFilter(true);
        return;
      }

      if(mode === 'clear'){
        if(sxPickerFrom) sxPickerFrom.value = '';
        if(sxPickerTo)   sxPickerTo.value   = '';
        if(sxFromInp) sxFromInp.value = '';
        if(sxToInp)   sxToInp.value   = '';
        if(sxPeriodInp) sxPeriodInp.value = sxCurrentYm();
        sxSyncDateText();
        sxCloseDatePanel();
        sxSubmitFilters();
      }
    });
  });

  document.addEventListener('click', function(ev){
    if(!sxDateRoot || !sxDatePanel || sxDatePanel.hidden) return;
    if(sxDateRoot.contains(ev.target)) return;
    sxCloseDatePanel();
  });

  if(sxFilterReset){
    sxFilterReset.addEventListener('click', function(ev){
      ev.preventDefault();
      sxResetFilters();
    });
  }

  if(sxFilterStatus){
    sxFilterStatus.addEventListener('change', function(){
      sxSubmitFilters();
    });
  }

  if(sxFilterPerPage){
    sxFilterPerPage.addEventListener('change', function(){
      sxSubmitFilters();
    });
  }

  if(sxFilterQ){
    sxFilterQ.addEventListener('keydown', function(ev){
      if(ev.key === 'Enter'){
        ev.preventDefault();
        sxSubmitFilters();
      }
    });
  }

  if(sxFilterAccount){
    sxFilterAccount.addEventListener('keydown', function(ev){
      if(ev.key === 'Enter'){
        ev.preventDefault();
        sxSubmitFilters();
      }
    });
  }

    // ==========================================================
  // INIT
  // ==========================================================
  applyOnlySelectedFromQuery();
  updateBulk();
  setPaidAtVisibility();
  sxSyncDateText();

})();
</script>
@endpush

@endsection