{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\statements\index.blade.php --}}
{{-- UI v6.5 · Estados de cuenta (Admin) — Vault look + KPI + bulk + drawer acciones (sin romper tabla) --}}
@extends('layouts.admin')

@section('title','Facturación · Estados de cuenta')
@section('layout','full')
@section('contentLayout','full')

@php
  use Illuminate\Support\Facades\Route;

  // ===== Inputs =====
  $q         = (string) request('q','');
  $period    = (string) request('period', now()->format('Y-m'));
  $accountId = (string) request('accountId','');

  $status    = (string) request('status','all');
  $status    = $status !== '' ? $status : 'all';

  $perPage   = (int) request('perPage', $perPage ?? 25);
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
    'q'        => $q,
    'period'   => $period,
    'accountId'=> $accountId,
    'status'   => $status,
    'perPage'  => $perPage,
  ];

  if ($onlySelected && !empty($idsSelectedReq)) {
    $chipBase['only_selected'] = 1;
    $chipBase['ids'] = implode(',', $idsSelectedReq);
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
@endpush

@section('content')
<div class="sx-wrap">
  <div class="sx-card">

    <div class="sx-topSticky">
      <div class="sx-head">
        <div>
          <div class="sx-title">Facturación · Estados de cuenta</div>
          <div class="sx-sub">
            Periodo <span class="sx-mono">{{ $period }}</span>.
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
        <form method="GET" action="{{ $routeIndex }}" class="sx-grid">
          <div class="sx-ctl">
            <label>Buscar</label>
            <input class="sx-in" name="q" value="{{ $q }}" placeholder="ID, RFC, email, razón social, UUID...">
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
              @foreach($statusOptions as $k => $lbl)
                <option value="{{ $k }}" {{ $status===$k?'selected':'' }}>{{ $lbl }}</option>
              @endforeach
            </select>
          </div>

          <div class="sx-ctl">
            <label>Por página</label>
            <select class="sx-sel" name="perPage">
              @foreach([10,25,50,100,200] as $n)
                <option value="{{ $n }}" {{ (int)$perPage===(int)$n?'selected':'' }}>{{ $n }}</option>
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
                <span class="sx-miniPill">Periodo <span class="sx-mono">{{ $period }}</span></span>
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
          <div class="sx-bulk-note">Periodo <span class="sx-mono">{{ $period }}</span></div>
        </div>

        <div class="sx-actionsRow">
          <button class="sx-btn sx-btn-ghost" type="button" onclick="sxClear()">Limpiar</button>
          <button class="sx-btn sx-btn-primary" type="button" onclick="sxBulkSend()">Enviar correos</button>
        </div>

        <form id="sxBulkForm" method="POST" action="{{ $hasBulkSend ? route('admin.billing.statements_hub.bulk_send') : '' }}" style="display:none;">
          @csrf
          <input type="hidden" name="period" value="{{ $period }}">
          <input type="hidden" name="account_ids" value="">
        </form>
      </div>

      <div class="sx-table-wrap">
          <table class="sx-table">
            <colgroup>
              <col style="width:44px">   {{-- checkbox --}}
              <col style="width:120px">  {{-- Cuenta --}}
              <col style="width:30%">    {{-- Cliente --}}
              <col style="width:32%">    {{-- Email/Meta --}}
              <col style="width:130px">  {{-- Total --}}
              <col style="width:150px">  {{-- Pagado --}}
              <col style="width:140px">  {{-- Saldo --}}
              <col style="width:130px">  {{-- Estatus --}}
              <col style="width:160px">  {{-- Acciones --}}
            </colgroup>

            <thead>
              <tr>
                <th class="sx-selcol" title="Seleccionar todo">
                  <input id="sxCkAll" class="sx-ck" type="checkbox" onclick="sxToggleAll(this)">
                </th>
                <th>Cuenta</th>
                <th>Cliente</th>
                <th>Contacto / Meta</th>
                <th class="sx-right">Total</th>
                <th class="sx-right">Pagado</th>
                <th>Saldo</th>
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

                $cargo    = (float)($r->cargo ?? 0);
                $expected = (float)($r->expected_total ?? 0);
                $total    = $cargo > 0 ? $cargo : $expected;

                $abono    = (float)($r->abono ?? 0);
                $saldo    = max(0, $total - $abono);

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

                $showUrl  = ($hasShow && $aid) ? route('admin.billing.statements.show', ['accountId'=>$aid, 'period'=>$period]) : null;
                $pdfUrl   = ($hasPdf  && $aid) ? route('admin.billing.statements.pdf',  ['accountId'=>$aid, 'period'=>$period]) : null;
                $emailUrl = ($hasSendLegacy && $aid) ? route('admin.billing.statements.email', ['accountId'=>$aid, 'period'=>$period]) : null;
              @endphp

              <tr id="sxRow-{{ e($aid) }}">
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

                <td class="sx-right">
                  <div class="sx-mono">{{ $fmtMoney($abono) }}</div>
                  <div class="sx-subrow">
                    EdoCta: <span class="sx-mono">{{ $fmtMoney($abonoEdo) }}</span>
                    · Pay: <span class="sx-mono">{{ $fmtMoney($abonoPay) }}</span>
                  </div>
                </td>

                <td class="sx-right">
                  <span class="sx-pill {{ $saldo > 0 ? 'sx-warn' : 'sx-ok' }}">
                    <span class="dot"></span><span class="sx-mono">{{ $fmtMoney($saldo) }}</span>
                  </span>
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
                          data-emailurl="{{ e($emailUrl ?? '') }}">
                    Gestionar
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" style="padding:16px; color:var(--sx-mut); font-weight:900;">
                  Sin resultados para el filtro actual.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div style="padding:12px 18px; border-top:1px solid var(--sx-line); background: color-mix(in oklab, var(--sx-card) 96%, transparent); display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <div style="color:var(--sx-mut); font-weight:850; font-size:12px;">
          Filtros: periodo <span class="sx-mono">{{ $period }}</span>,
          estatus <span class="sx-mono">{{ strtoupper($status) }}</span>,
          por página <span class="sx-mono">{{ $perPage }}</span>.
        </div>

        @if($isPaginator)
          <div>{!! $rows->appends(request()->query())->links() !!}</div>
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
                data-period="{{ e($period) }}">
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

  function $(id){ return document.getElementById(id); }

  // ==========================================================
  // Helpers
  // ==========================================================
  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
  }

  function pillClassByStatus(st){
    st = String(st || '').toLowerCase().trim();
    if (st === 'pagado') return 'sx-ok';
    if (st === 'vencido') return 'sx-bad';
    if (st === 'pendiente' || st === 'parcial') return 'sx-warn';
    return 'sx-dim';
  }

  // ==========================================================
  // TOAST PRO (cola + progress + actions + close)
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

  function renderToast(opts){
    if(!toastEl) return;

    const msg = String((opts && opts.msg) || '');
    const type = String((opts && opts.type) || 'info');
    const ms = Math.max(800, Number((opts && opts.ms) || 2200));
    const actions = (opts && Array.isArray(opts.actions)) ? opts.actions : null;

    toastEl.setAttribute('data-type', type);

    const actionsHtml = (actions && actions.length)
      ? `<div class="sx-toast-actions">${
          actions.map((a,i)=>{
            const label = String(a && a.label ? a.label : 'Acción');
            const kind  = String(a && a.kind  ? a.kind  : 'button'); // button|link
            const href  = String(a && a.href  ? a.href  : '#');
            if(kind === 'link'){
              return `<a class="sx-toast-btn" data-sx-toast-act="${i}" href="${href}">${escapeHtml(label)}</a>`;
            }
            return `<button class="sx-toast-btn" data-sx-toast-act="${i}" type="button">${escapeHtml(label)}</button>`;
          }).join('')
        }</div>`
      : '';

    toastEl.innerHTML = `
      <div class="sx-toast-inner">
        <div class="sx-toast-ic">${escapeHtml(toastIcon(type))}</div>
        <div class="sx-toast-body">
          <div class="sx-toast-title">${escapeHtml(toastTitle(type))}</div>
          <div class="sx-toast-msg">${escapeHtml(msg)}</div>
          ${actionsHtml}
        </div>
        <button class="sx-toast-x" type="button" aria-label="Cerrar">×</button>
      </div>
      <div class="sx-toast-bar"><i></i></div>
    `;

    // close
    const x = toastEl.querySelector('.sx-toast-x');
    if(x) x.addEventListener('click', () => hideToast(true), { once:true });

    // actions
    if(actions && actions.length){
      const btns = Array.from(toastEl.querySelectorAll('[data-sx-toast-act]'));
      btns.forEach(b=>{
        b.addEventListener('click', (ev)=>{
          const idx = parseInt(b.getAttribute('data-sx-toast-act') || '-1', 10);
          const act = actions[idx];
          if(!act) return;

          const kind = String(act.kind || 'button').toLowerCase();
          if(kind === 'link'){
            // deja navegar, pero cierra toast
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

    // progress
    const bar = toastEl.querySelector('.sx-toast-bar > i');
    if(bar){
      bar.style.transition = 'none';
      bar.style.transform = 'scaleX(1)';
      void bar.offsetWidth;
      bar.style.transition = `transform ${ms}ms linear`;
      bar.style.transform = 'scaleX(0)';
    }

    // auto-hide
    if(toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => hideToast(false), ms);
  }

  function showToastNow(payload){
    if(!toastEl) return;

    if(toastTimer) clearTimeout(toastTimer);
    if(toastAnimTimer) clearTimeout(toastAnimTimer);

    toastEl.classList.remove('hide');
    toastEl.classList.add('on');
    renderToast(payload);

    toastAnimTimer = setTimeout(() => toastEl.classList.add('show'), 10);
    toastShowing = true;
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

    if(toastTimer) { clearTimeout(toastTimer); toastTimer = null; }
    if(toastAnimTimer) { clearTimeout(toastAnimTimer); toastAnimTimer = null; }

    toastEl.classList.remove('show');
    toastEl.classList.add('hide');

    const done = () => {
      toastEl.classList.remove('on','hide');
      toastEl.innerHTML = '';
      nextToast();
    };

    if(forceImmediate) return done();
    setTimeout(done, 160);
  }

  function sxToast(a, b){
    let payload;

    if(typeof a === 'object' && a){
      payload = {
        msg: String(a.msg || ''),
        type: String(a.type || 'info'),
        ms: Number(a.ms || 2200),
        actions: Array.isArray(a.actions) ? a.actions : null
      };
    }else{
      payload = { msg: String(a || ''), type: String(b || 'info'), ms: 2200, actions: null };
    }

    if(toastShowing){
      toastQueue.push(payload);
      return;
    }
    showToastNow(payload);
  }
  window.sxToast = sxToast;

  // Escape cierra toast (si visible)
  document.addEventListener('keydown', function(ev){
    if(ev.key === 'Escape' && toastEl && toastEl.classList.contains('on')){
      hideToast(true);
    }
  });

  // ==========================================================
  // Bulk / Selection (NECESARIO para tus botones)
  // ==========================================================
  function rows(){ return Array.from(document.querySelectorAll('.sx-row')); }
  function selected(){ return rows().filter(x => x.checked).map(x => x.value); }

  const bulkbar   = $('sxBulkbar');
  const bulkCount = $('sxBulkCount');
  const ckAll     = $('sxCkAll');     // si no existe, no pasa nada
  const bulkForm  = $('sxBulkForm');
  const hasBulkEndpoint = {!! json_encode((bool)($hasBulkSend ?? false)) !!};

  function repaintSelectedRows(){
    rows().forEach(ck => {
      const tr = ck.closest('tr');
      if(!tr) return;
      if(ck.checked) tr.classList.add('is-selected');
      else tr.classList.remove('is-selected');
    });
  }

  function updateBulk(){
    const ids = selected();

    if (bulkCount) bulkCount.textContent = String(ids.length);
    if (bulkbar){
      if(ids.length > 0) bulkbar.classList.add('on');
      else bulkbar.classList.remove('on');
    }

    if(ckAll){
      const r = rows();
      if(!r.length){ ckAll.checked = false; ckAll.indeterminate = false; }
      else{
        const all = r.every(x => x.checked);
        const any = r.some(x => x.checked);
        ckAll.checked = all;
        ckAll.indeterminate = (!all && any);
      }
    }

    repaintSelectedRows();
  }

  function bindBulkForm(ids){
    if(!bulkForm) return false;
    const inp = bulkForm.querySelector('input[name="account_ids"]');
    if (inp) inp.value = ids.join(',');
    return true;
  }

  // expón funciones globales (tus botones las llaman)
  window.sxToggleAll = function(master){
    rows().forEach(x => x.checked = !!(master && master.checked));
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

  // ==========================================================
  // Filtro: Solo seleccionadas (NECESARIO)
  // ==========================================================
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

      // check los visibles que estén en ids
      rows().forEach(ck => {
        if(ids.has(String(ck.value))) ck.checked = true;
      });

      updateBulk();
    }catch(e){}
  }

  window.sxFilterSelected = function(){
    const ids = selected();
    if(!ids.length){ sxToast('Selecciona al menos una cuenta para filtrar.', 'warn'); return; }

    const base = {!! json_encode($routeIndex ?? url('/admin/billing/statements')) !!};
    const sp = new URLSearchParams(window.location.search || '');
    sp.set('only_selected', '1');
    sp.set('ids', ids.join(','));
    sp.delete('page');
    window.location.href = base + '?' + sp.toString();
  };

  window.sxBulkSend = function(){
    const ids = selected();
    if(!ids.length){ sxToast('Selecciona al menos una cuenta.', 'warn'); return; }

    if(hasBulkEndpoint && bulkForm && bulkForm.getAttribute('action')){
      bindBulkForm(ids);
      bulkForm.submit();
      return;
    }

    const payload = { period: {!! json_encode($period ?? '') !!}, account_ids: ids.join(',') };
    try{ navigator.clipboard.writeText(JSON.stringify(payload)); }catch(e){}
    sxToast('Se copió al portapapeles (period + account_ids CSV). Activa el endpoint HUB bulk_send para envío masivo real.', 'info');
  };

  // ==========================================================
  // Save endpoint (estatus / forma de pago)
  // ==========================================================
  const endpoint = {!! json_encode($statusEndpoint ?? url('/admin/billing/statements/status')) !!};
  const period   = {!! json_encode($period ?? '') !!};
  const csrf     = {!! json_encode(csrf_token()) !!};

  function repaintRowStatus(accountId, status){
    const id = String(accountId || '').trim();
    const rowEl = $('sxRow-' + id);
    if(!rowEl) return;

    const statusTd = rowEl.querySelector('td:nth-child(8)');
    if(!statusTd) return;

    const st = String(status || '').toLowerCase().trim() || 'sin_mov';

    let pill = statusTd.querySelector('.sx-pill');
    if(!pill){
      pill = document.createElement('span');
      pill.className = 'sx-pill';
      statusTd.innerHTML = '';
      statusTd.appendChild(pill);
    }

    pill.classList.remove('sx-ok','sx-warn','sx-bad','sx-dim');
    pill.classList.add(pillClassByStatus(st));
    pill.innerHTML = '<span class="dot"></span>' + escapeHtml(st.toUpperCase());
  }

  function upsertMetaPay(rowEl, method, provider, payStatus){
    try{
      const metaCell = rowEl.querySelector('td:nth-child(4)');
      if(!metaCell) return;

      const sub = metaCell.querySelector('.sx-subrow');
      if(!sub) return;

      const m = String(method || '').trim();
      const p = String(provider || '').trim();
      const s = String(payStatus || '').trim();

      let html = sub.innerHTML;

      // limpia bloque previo (si existe)
      html = html.replace(/\s*<span style="opacity:\.55;">\s*·\s*<\/span>\s*M[ée]todo:\s*<span class="sx-mono">.*?<\/span>/i, '');
      html = html.replace(/\s*<span style="opacity:\.55;">\s*·\s*<\/span>\s*Prov:\s*<span class="sx-mono">.*?<\/span>/i, '');
      html = html.replace(/\s*<span style="opacity:\.55;">\s*·\s*<\/span>\s*St:\s*<span class="sx-mono">.*?<\/span>/i, '');

      const insertAfter = /(Periodo:\s*<span class="sx-mono">[^<]*<\/span>)/i;

      let extra = '';
      if(m !== '') extra += ' <span style="opacity:.55;"> · </span> Método: <span class="sx-mono">'+ escapeHtml(m) +'</span>';
      if(p !== '') extra += ' <span style="opacity:.55;"> · </span> Prov: <span class="sx-mono">'+ escapeHtml(p) +'</span>';
      if(s !== '') extra += ' <span style="opacity:.55;"> · </span> St: <span class="sx-mono">'+ escapeHtml(s) +'</span>';

      html = html.replace(insertAfter, '$1' + extra);
      sub.innerHTML = html;
    }catch(e){}
  }

  async function saveRow(accountId, status, pay){
    const id = String(accountId || '').trim();
    if(!id){ sxToast('Falta accountId.', 'bad'); return {ok:false}; }

    const res = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        account_id: id,
        period: period,
        status: status,
        pay_method: pay
      })
    });

    const text = await res.text();
    let data = null;
    try{ data = JSON.parse(text); }catch(e){ data = null; }

    if(!res.ok){
      const msg = (data && (data.message || data.error))
        ? (data.message || data.error)
        : ('HTTP ' + res.status + ' -> ' + text.slice(0,200));
      sxToast('No se pudo guardar: ' + msg, 'bad');
      return {ok:false, data};
    }

    const effectiveStatus = (data && data.status) ? data.status : status;
    repaintRowStatus(id, effectiveStatus);

    // si backend devuelve números, refresca celdas
    if (data && typeof data.total !== 'undefined') {
      const rowEl = $('sxRow-' + id);
      if(rowEl){
        const totalTd = rowEl.querySelector('td:nth-child(5)');
        const paidTd  = rowEl.querySelector('td:nth-child(6)');
        const saldoTd = rowEl.querySelector('td:nth-child(7)');

        const fmt = (n) => '$' + Number(n || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});

        if (totalTd) totalTd.textContent = fmt(data.total);

        if (paidTd) {
          const top = paidTd.querySelector('.sx-mono');
          if (top) top.textContent = fmt(data.abono);
        }

        if (saldoTd) {
          const pill = saldoTd.querySelector('.sx-pill');
          const saldo = Number(data.saldo || 0);
          if (pill) {
            pill.classList.remove('sx-ok','sx-warn','sx-bad','sx-dim');
            pill.classList.add(saldo > 0 ? 'sx-warn' : 'sx-ok');
            pill.innerHTML = '<span class="dot"></span><span class="sx-mono">' + fmt(saldo) + '</span>';
          }
        }
      }
    }

    // actualizar meta de pago en UI
    const rowEl = $('sxRow-' + id);
    if(rowEl){
      const effectivePay  = (data && data.pay_method !== undefined) ? data.pay_method : pay;
      const effectiveProv = (data && data.pay_provider !== undefined) ? data.pay_provider : '';
      const effectiveSt   = (data && data.pay_status !== undefined) ? data.pay_status : '';
      upsertMetaPay(rowEl, effectivePay, effectiveProv, effectiveSt);
    }

    sxToast((data && data.message) ? data.message : 'Guardado.', 'ok');
    return {ok:true, data};
  }

  // ==========================================================
  // PDF Preview Modal
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
      let glue = (url.indexOf('?') >= 0) ? '&' : '?';
      const q = Object.keys(params||{}).map(k => encodeURIComponent(k)+'='+encodeURIComponent(String(params[k]))).join('&');
      return url + glue + q;
    }
  }

  function openPdfModal(opts){
    if(!pdfModal || !pdfFrame) return;

    const url = String((opts && opts.url) || '').trim();
    const acc = String((opts && opts.account) || '—');
    const per = String((opts && opts.period) || '—');

    if(url === ''){ sxToast('No hay URL de PDF para vista previa.', 'bad'); return; }

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
    pdfClose.addEventListener('click', function(ev){ ev.preventDefault(); closePdfModal(); });
  }
  if(pdfModal){
    pdfModal.addEventListener('mousedown', function(ev){
      if(ev.target === pdfModal) closePdfModal();
    });
  }

  // ==========================================================
  // Drawer
  // ==========================================================
  const drawer = $('sxDrawer');
  const dAccount = $('sxDAccount');
  const dStatusPill = $('sxDStatusPill');
  const dName = $('sxDName');
  const dEmail = $('sxDEmail');

  const dStatus = $('sxDStatus');
  const dPay = $('sxDPay');
  const dSave = $('sxDSave');

  const dShow = $('sxDShow');
  const dPreview = $('sxDPreview');
  const dPdf = $('sxDPdf');
  const dEmailForm = $('sxDEmailForm');

  let drawerState = { account:'', name:'', email:'', status:'', pay:'', show:'', pdf:'', emailurl:'' };

  function openDrawer(payload){
    if(!drawer) return;
    drawerState = payload || drawerState;

    const acc = String(drawerState.account || '').trim();
    const nm  = String(drawerState.name || '—');
    const em  = String(drawerState.email || '—');
    const st  = String(drawerState.status || 'sin_mov').toLowerCase().trim();
    const pm  = String(drawerState.pay || '').toLowerCase().trim();

    if(dAccount) dAccount.innerHTML = '<span class="dot"></span>#' + escapeHtml(acc || '—');
    if(dName) dName.textContent = nm;
    if(dEmail) dEmail.textContent = em;

    if(dStatus) dStatus.value = st;
    if(dPay) dPay.value = pm;

    if(dStatusPill){
      dStatusPill.classList.remove('sx-ok','sx-warn','sx-bad','sx-dim');
      dStatusPill.classList.add(pillClassByStatus(st));
      dStatusPill.innerHTML = '<span class="dot"></span>' + escapeHtml(st.toUpperCase());
    }

    if(dShow){
      if(drawerState.show){ dShow.style.display = ''; dShow.setAttribute('href', drawerState.show); }
      else dShow.style.display = 'none';
    }

    if(dPdf){
      if(drawerState.pdf){ dPdf.style.display = ''; dPdf.setAttribute('href', drawerState.pdf); }
      else dPdf.style.display = 'none';
    }

    if(dPreview){
      if(drawerState.pdf){
        dPreview.style.display = '';
        dPreview.setAttribute('data-url', drawerState.pdf);
        dPreview.setAttribute('data-account', acc || '—');
        dPreview.setAttribute('data-period', {!! json_encode($period ?? '') !!});
      }else dPreview.style.display = 'none';
    }

    if(dEmailForm){
      if(drawerState.emailurl){
        dEmailForm.style.display = '';
        dEmailForm.setAttribute('action', drawerState.emailurl);
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

  function closeDrawer(){
    if(!drawer) return;
    drawer.classList.remove('on');
    drawer.setAttribute('aria-hidden','true');
    document.body.classList.remove('sx-drawer-open');
  }

  // ==========================================================
  // Delegación de clicks (esto hace que TODOS los botones funcionen)
  // ==========================================================
  document.addEventListener('click', function(ev){

    // 1) Abrir drawer con botón Gestionar
    const openBtn = ev.target.closest('[data-sx-open-drawer="1"]');
    if(openBtn){
      ev.preventDefault();
      openDrawer({
        account: openBtn.getAttribute('data-account') || '',
        name: openBtn.getAttribute('data-name') || '—',
        email: openBtn.getAttribute('data-email') || '—',
        status: openBtn.getAttribute('data-status') || 'sin_mov',
        pay: openBtn.getAttribute('data-pay') || '',
        show: openBtn.getAttribute('data-show') || '',
        pdf: openBtn.getAttribute('data-pdf') || '',
        emailurl: openBtn.getAttribute('data-emailurl') || ''
      });
      return;
    }

    // 2) Cerrar drawer
    if(ev.target.closest('[data-sx-drawer-close="1"]')){
      ev.preventDefault();
      closeDrawer();
      return;
    }

    // 3) Abrir PDF Preview desde drawer
    const btnPrev = ev.target.closest('[data-sx-pdf-preview="1"]');
    if(btnPrev){
      ev.preventDefault();
      openPdfModal({
        url: btnPrev.getAttribute('data-url') || '',
        account: btnPrev.getAttribute('data-account') || '—',
        period: btnPrev.getAttribute('data-period') || {!! json_encode($period ?? '') !!}
      });
      return;
    }
  });

  // Escape para modal/drawer (y toast ya se maneja arriba)
  document.addEventListener('keydown', function(ev){
    if(ev.key !== 'Escape') return;
    if(pdfModal && pdfModal.classList.contains('on')) closePdfModal();
    if(drawer && drawer.classList.contains('on')) closeDrawer();
  });

  // Guardar desde drawer
  if(dSave){
    dSave.addEventListener('click', async function(){
      const id = String(drawerState.account || '').trim();
      if(!id){ sxToast('Falta accountId.', 'bad'); return; }

      const status = dStatus ? String(dStatus.value || '').trim() : 'sin_mov';
      const pay    = dPay ? String(dPay.value || '').trim() : '';

      const oldTxt = dSave.textContent;
      dSave.disabled = true;
      dSave.textContent = 'Guardando...';

      try{
        const r = await saveRow(id, status, pay);

        if(r.ok && dStatusPill){
          const eff = (r.data && r.data.status) ? r.data.status : status;
          dStatusPill.classList.remove('sx-ok','sx-warn','sx-bad','sx-dim');
          dStatusPill.classList.add(pillClassByStatus(eff));
          dStatusPill.innerHTML = '<span class="dot"></span>' + escapeHtml(String(eff).toUpperCase());
        }
      }catch(e){
        sxToast('Error: ' + (e && e.message ? e.message : String(e)), 'bad');
      }finally{
        dSave.disabled = false;
        dSave.textContent = oldTxt;
      }
    });
  }

  // ==========================================================
  // INIT
  // ==========================================================
  applyOnlySelectedFromQuery();
  updateBulk();

})();
</script>
@endpush

@endsection