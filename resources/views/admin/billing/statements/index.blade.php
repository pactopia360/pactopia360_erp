{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\statements\index.blade.php --}}
{{-- UI v6.4 · Estados de cuenta (Admin) — Vault look + info completa + cambio estatus + forma de pago --}}
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

  // ✅ filtro: solo seleccionadas (por checkbox)
  // - Soporta: request only_selected/ids
  // - y/o valores inyectados por controller: $onlySelected, $idsCsv
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
    // admin account_id suele ser numérico; toleramos slug por si cambia
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

  /**
   * ✅ Endpoint update status/pay_method
   * Como tus rutas reales viven en routes/admin.php bajo prefix('billing'),
   * el POST correcto debe existir como:
   *   POST /admin/billing/statements/status
   * y con nombre:
   *   admin.billing.statements.status
   */
  $statusEndpoint = Route::has('admin.billing.statements.status')
    ? route('admin.billing.statements.status')
    : url('/admin/billing/statements/status');

  // ✅ Habilitar UI siempre; si el endpoint no existe, el toast mostrará el error.
  $hasStatusEndpoint = true;

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

  // ✅ Si estamos en "solo seleccionadas", conservarlo en chips/paginación
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
              <option value="{{ $n }}" {{ $perPage===$n?'selected':'' }}>{{ $n }}</option>
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
        <a class="sx-chip {{ $onlySelected ? 'on' : '' }}"
          href="#"
          onclick="event.preventDefault(); sxFilterSelected();">
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
        <div class="sx-mini">Cargos del periodo (o esperado por licencia si aplica).</div>
      </div>

      <div class="sx-kpi">
        <div class="sx-k">Pagado</div>
        <div class="sx-v">{{ $fmtMoney($kpis['abono'] ?? 0) }}</div>
        <div class="sx-mini">
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
        <div class="sx-mini">Saldo pendiente considerando pagos.</div>
      </div>

      <div class="sx-kpi">
        <div class="sx-k">Cuentas</div>
        <div class="sx-v">{{ (int)($kpis['accounts'] ?? 0) }}</div>
        <div class="sx-mini">Total de cuentas en el resultado.</div>
      </div>

      <div class="sx-kpi">
        <div class="sx-k">Operación</div>
        <div class="sx-v" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
          <button class="sx-btn sx-btn-soft" type="button" onclick="sxSelectAll(true)">Todo</button>
          <button class="sx-btn sx-btn-soft" type="button" onclick="sxFilterSelected()">Filtrar seleccionadas</button>
          <button class="sx-btn sx-btn-soft" type="button" onclick="sxSelectAll(false)">Nada</button>
          <button class="sx-btn sx-btn-primary" type="button" onclick="sxBulkSend()">Enviar correo (bulk)</button>
        </div>
        <div class="sx-mini">
          @if($hasBulkSend)
            Envío masivo vía HUB: <span class="sx-mono">billing/statements-hub/bulk/send</span>.
          @else
            Tip: activa el endpoint HUB bulk_send para envío masivo real.
          @endif
        </div>
      </div>
    </div>

    <div class="sx-body">
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

          <form id="sxBulkForm"
                method="POST"
                action="{{ $hasBulkSend ? route('admin.billing.statements_hub.bulk_send') : '' }}"
                style="display:none;">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="hidden" name="account_ids" value="">
          </form>
        </div>

        <div class="sx-table-wrap">
          <table class="sx-table">
            <thead>
              <tr>
                <th class="sx-selcol">
                  <input class="sx-ck" type="checkbox" id="sxCkAll" onclick="sxToggleAll(this)">
                </th>
                <th style="width:120px">Cuenta</th>
                <th style="min-width:380px;">Cliente</th>
                <th style="min-width:340px;">Email / Meta</th>
                <th class="sx-right" style="width:140px">Total</th>
                <th class="sx-right" style="width:170px">Pagado</th>
                <th class="sx-right" style="width:140px">Saldo</th>
                <th style="width:150px">Estatus</th>
                <th class="sx-right" style="width:360px">Acciones</th>
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

                  // =========================
                  // ✅ Override-first (Admin)
                  // =========================
                  $payMethod   = strtolower(trim((string)($r->ov_pay_method ?? $r->pay_method ?? $r->pago_metodo ?? '')));
                  $payProvider = trim((string)($r->ov_pay_provider ?? $r->pay_provider ?? $r->pago_prov ?? ''));
                  $payStatus   = strtolower(trim((string)($r->ov_pay_status ?? $r->pay_status ?? '')));

                  // normaliza status “St:” a etiquetas que sí entendemos
                  if ($payStatus === 'paid' || $payStatus === 'succeeded') $payStatus = 'pagado';
                  if ($payStatus === 'pending') $payStatus = 'pendiente';
                  if ($payStatus === 'unpaid' || $payStatus === 'past_due') $payStatus = 'vencido';

                  $payDue      = $fmtDate($r->pay_due_date ?? $r->vence ?? '');
                  $payLast     = $fmtDate($r->pay_last_paid_at ?? $r->last_paid_at ?? '');

                  $showUrl  = ($hasShow && $aid) ? route('admin.billing.statements.show', ['accountId'=>$aid, 'period'=>$period]) : null;
                  $pdfUrl   = ($hasPdf  && $aid) ? route('admin.billing.statements.pdf',  ['accountId'=>$aid, 'period'=>$period]) : null;
                  $emailUrl = ($hasSendLegacy && $aid) ? route('admin.billing.statements.email', ['accountId'=>$aid, 'period'=>$period]) : null;
                @endphp

                <tr id="sxRow-{{ e($aid) }}">
                  <td onclick="event.stopPropagation();">
                    <input class="sx-ck sx-row"
                      type="checkbox"
                      value="{{ e($aid) }}"
                      onclick="sxSync()"
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
                    <div class="sx-subrow">
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

                  <td class="sx-right">
                    <div class="sx-actions">
                      <div class="sx-actionsBox">
                        <div class="sx-rowctl">
                          <select class="sx-sel" id="sxStatus-{{ e($aid) }}">
                            @foreach($statusOptions as $k => $lbl)
                              <option value="{{ $k }}" {{ $st===$k?'selected':'' }}>{{ $lbl }}</option>
                            @endforeach
                          </select>

                          <select class="sx-sel" id="sxPay-{{ e($aid) }}">
                            @foreach($payMethodOptions as $k => $lbl)
                              <option value="{{ $k }}" {{ (string)$payMethod === (string)$k ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                          </select>

                          <button class="sx-btn sx-btn-primary sx-save"
                                  type="button"
                                  data-sx-save="{{ e($aid) }}">
                            Guardar cambios
                          </button>
                        </div>

                        <div class="sx-actionsRow" style="margin-top:10px;">
                          @if($showUrl)
                            <a class="sx-btn sx-btn-primary" href="{{ $showUrl }}">Ver detalle</a>
                          @else
                            <button class="sx-btn sx-btn-primary" type="button" disabled>Ver detalle</button>
                          @endif

                          @if($pdfUrl)
                            <button class="sx-btn sx-btn-soft"
                                    type="button"
                                    data-sx-pdf-preview="1"
                                    data-url="{{ $pdfUrl }}"
                                    data-account="{{ e($aid) }}"
                                    data-period="{{ e($period) }}">
                              Vista previa
                            </button>

                            <a class="sx-btn sx-btn-soft" target="_blank" href="{{ $pdfUrl }}">PDF</a>
                          @else
                            <button class="sx-btn sx-btn-soft" type="button" disabled>Vista previa</button>
                            <button class="sx-btn sx-btn-soft" type="button" disabled>PDF</button>
                          @endif


                          @if($emailUrl)
                            <form method="POST" action="{{ $emailUrl }}" style="display:inline;">
                              @csrf
                              <input type="hidden" name="to" value="{{ $mail !== '—' ? $mail : '' }}">
                              <button class="sx-btn sx-btn-soft" type="submit">Enviar</button>
                            </form>
                          @else
                            <button class="sx-btn sx-btn-soft" type="button" disabled>Enviar</button>
                          @endif
                        </div>
                      </div>
                    </div>
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
</div>

<div id="sxToast" class="sx-toast"></div>

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
  // ===== Helpers =====
  function $(id){ return document.getElementById(id); }
  function rows(){ return Array.from(document.querySelectorAll('.sx-row')); }
  function selected(){ return rows().filter(x => x.checked).map(x => x.value); }

  // ===== Bulk UI =====
  const bulkbar   = $('sxBulkbar');
  const bulkCount = $('sxBulkCount');
  const ckAll     = $('sxCkAll');

  const bulkForm  = $('sxBulkForm');
  const hasBulkEndpoint = {!! json_encode((bool)($hasBulkSend ?? false)) !!};

  function updateBulk(){
    const ids = selected();

    if (bulkCount) bulkCount.textContent = String(ids.length);

    if (bulkbar){
      if(ids.length > 0) bulkbar.classList.add('on');
      else bulkbar.classList.remove('on');
    }

    if(ckAll){
      const r = rows();
      if(!r.length){
        ckAll.checked = false;
        ckAll.indeterminate = false;
        return;
      }
      const all = r.every(x => x.checked);
      const any = r.some(x => x.checked);
      ckAll.checked = all;
      ckAll.indeterminate = (!all && any);
    }
  }

  window.sxToggleAll = function(master){
    rows().forEach(x => x.checked = !!master.checked);
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

  function bindBulkForm(ids){
    if(!bulkForm) return false;
    const inp = bulkForm.querySelector('input[name="account_ids"]');
    if (inp) inp.value = ids.join(',');
    return true;
  }

    // ===== Filtro: Solo seleccionadas =====
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

      // Oculta filas no incluidas y deja checked las incluidas
      const r = rows();
      r.forEach(ck => {
        const id = String(ck.value || '').trim();
        const tr = ck.closest('tr');
        if(!tr) return;

        if(ids.has(id)){
          ck.checked = true;
          tr.style.display = '';
        }else{
          ck.checked = false;
          tr.style.display = 'none';
        }
      });

      // Desmarca/ajusta master (si aplica)
      if(ckAll){
        ckAll.checked = true;
        ckAll.indeterminate = false;
      }

      updateBulk();
    }catch(e){}
  }

  window.sxFilterSelected = function(){
    const ids = selected();
    if(!ids.length){
      sxToast('Selecciona al menos una cuenta para filtrar.', 'warn');
      return;
    }

    const base = {!! json_encode($routeIndex ?? url('/admin/billing/statements')) !!};

    // ✅ Conserva query actual y solo fuerza only_selected + ids
    const sp = new URLSearchParams(window.location.search || '');
    sp.set('only_selected', '1');
    sp.set('ids', ids.join(','));
    sp.delete('page'); // reset paginación

    window.location.href = base + '?' + sp.toString();
  };

  window.sxBulkSend = function(){
    const ids = selected();
    if(!ids.length){
      sxToast('Selecciona al menos una cuenta.', 'warn');
      return;
    }

    if(hasBulkEndpoint && bulkForm && bulkForm.getAttribute('action')){
      bindBulkForm(ids);
      bulkForm.submit();
      return;
    }

    const payload = { period: {!! json_encode($period ?? '') !!}, account_ids: ids.join(',') };
    try{ navigator.clipboard.writeText(JSON.stringify(payload)); }catch(e){}
    sxToast('Se copió al portapapeles (period + account_ids CSV). Activa el endpoint HUB bulk_send para envío masivo real.', 'info');
  };

  // ===== Toast UI (usa #sxToast, NO alert) =====
  const toastEl = $('sxToast');
  let toastTimer = null;

  function sxToast(msg, type){
    type = type || 'info';
    if (!toastEl) { try{ console.log(msg); }catch(e){}; return; }

    toastEl.textContent = String(msg || '');
    toastEl.classList.add('on');

    // mini color por tipo
    toastEl.style.background =
      (type === 'ok')   ? '#065f46' :
      (type === 'warn') ? '#92400e' :
      (type === 'bad')  ? '#991b1b' :
                          '#111827';

    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('on'), 2200);
  }

  // ===== UI re-paint: Status pill + meta método =====
  function pillClassByStatus(st){
    st = String(st || '').toLowerCase().trim();
    if (st === 'pagado') return 'sx-ok';
    if (st === 'vencido') return 'sx-bad';
    if (st === 'pendiente' || st === 'parcial') return 'sx-warn';
    return 'sx-dim';
  }

  function upsertMetaPay(rowEl, method, provider, payStatus){
    try{
      const metaCell = rowEl.querySelector('td:nth-child(4)'); // Email/Meta
      if(!metaCell) return;

      const sub = metaCell.querySelector('.sx-subrow');
      if(!sub) return;

      const m = String(method || '').trim();
      const p = String(provider || '').trim();
      const s = String(payStatus || '').trim();

      // Tomamos el HTML actual y removemos segmentos Método/Prov/St existentes (con sus separadores)
      let html = sub.innerHTML;

      html = html.replace(/\s*<span style="opacity:\.55;">\s*·\s*<\/span>\s*M[ée]todo:\s*<span class="sx-mono">.*?<\/span>/i, '');
      html = html.replace(/\s*<span style="opacity:\.55;">\s*·\s*<\/span>\s*Prov:\s*<span class="sx-mono">.*?<\/span>/i, '');
      html = html.replace(/\s*<span style="opacity:\.55;">\s*·\s*<\/span>\s*St:\s*<span class="sx-mono">.*?<\/span>/i, '');

      // Insertamos nuevamente después de "Periodo: <span ...>..</span>"
      const insertAfter = /(Periodo:\s*<span class="sx-mono">[^<]*<\/span>)/i;

      let extra = '';
      if(m !== '') extra += ' <span style="opacity:.55;"> · </span> Método: <span class="sx-mono">'+ escapeHtml(m) +'</span>';
      if(p !== '') extra += ' <span style="opacity:.55;"> · </span> Prov: <span class="sx-mono">'+ escapeHtml(p) +'</span>';
      if(s !== '') extra += ' <span style="opacity:.55;"> · </span> St: <span class="sx-mono">'+ escapeHtml(s) +'</span>';

      html = html.replace(insertAfter, '$1' + extra);

      sub.innerHTML = html;
    }catch(e){}
  }


  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
  }

  function repaintRowStatus(accountId, status){
    const id = String(accountId || '');
    const rowEl = $('sxRow-' + id);
    if(!rowEl) return;

    // La columna "Estatus" es la 8va (según tu tabla)
    const statusTd = rowEl.querySelector('td:nth-child(8)');
    if(!statusTd) return;

    const st = String(status || '').toLowerCase().trim() || 'sin_mov';

    // Busca la pill existente
    let pill = statusTd.querySelector('.sx-pill');
    if(!pill){
      pill = document.createElement('span');
      pill.className = 'sx-pill';
      pill.innerHTML = '<span class="dot"></span><span></span>';
      statusTd.innerHTML = '';
      statusTd.appendChild(pill);
    }

    // Ajusta clases (sx-ok|sx-warn|sx-bad|sx-dim)
    pill.classList.remove('sx-ok','sx-warn','sx-bad','sx-dim');
    pill.classList.add(pillClassByStatus(st));

    // Texto
    pill.childNodes.forEach(()=>{});
    // pill tiene: <span class="dot"></span>TEXT o dot+text
    pill.innerHTML = '<span class="dot"></span>' + escapeHtml(st.toUpperCase());
  }

  // ===== Row Save (STATUS + PAY METHOD) =====
  const endpoint = {!! json_encode($statusEndpoint ?? url('/admin/billing/statements/status')) !!};
  const period   = {!! json_encode($period ?? '') !!};
  const csrf     = {!! json_encode(csrf_token()) !!};

  window.sxSaveRow = async function(accountId){
    const id = String(accountId || '').trim();
    if(!id){
      sxToast('Falta accountId.', 'bad');
      return;
    }

    const stSel  = document.getElementById('sxStatus-' + id);
    const paySel = document.getElementById('sxPay-' + id);

    if(!stSel){
      sxToast('No encuentro el select de estatus: sxStatus-' + id, 'bad');
      return;
    }

    const status = String(stSel.value || '').trim();
    const pay    = paySel ? String(paySel.value || '').trim() : '';

    const btn = document.querySelector('[data-sx-save="'+ CSS.escape(id) +'"]');
    const oldBtnText = btn ? btn.textContent : '';
    if(btn){ btn.disabled = true; btn.textContent = 'Guardando...'; }

    try{
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
        const msg = (data && (data.message || data.error)) ? (data.message || data.error) : ('HTTP ' + res.status + ' -> ' + text.slice(0,200));
        sxToast('No se pudo guardar: ' + msg, 'bad');
        return;
      }

      // ✅ RE-PAINT inmediato (sin recargar)
      const effectiveStatus = (data && data.status) ? data.status : status;
      repaintRowStatus(id, effectiveStatus);

      // ✅ si el server regresó montos, repintamos columnas Total/Pagado/Saldo
      if (data && typeof data.total !== 'undefined') {
        const rowEl = document.getElementById('sxRow-' + id);
        if (rowEl) {
          const totalTd = rowEl.querySelector('td:nth-child(5)');
          const paidTd  = rowEl.querySelector('td:nth-child(6)');
          const saldoTd = rowEl.querySelector('td:nth-child(7)');

          const fmt = (n) => '$' + Number(n || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});

          if (totalTd) totalTd.textContent = fmt(data.total);

          if (paidTd) {
            // conserva layout (monto arriba + subrow abajo)
            const top = paidTd.querySelector('.sx-mono');
            if (top) {
              top.textContent = fmt(data.abono);
            } else {
              // Si por alguna razón no existe el nodo, lo creamos sin borrar el subrow
              const div = document.createElement('div');
              div.className = 'sx-mono';
              div.textContent = fmt(data.abono);
              paidTd.insertBefore(div, paidTd.firstChild);
            }
          }

          if (saldoTd) {
            // pill de saldo (tu UI la maneja con sx-pill)
            const pill = saldoTd.querySelector('.sx-pill');
            const saldo = Number(data.saldo || 0);

            if (pill) {
              pill.classList.remove('sx-ok','sx-warn','sx-bad','sx-dim');
              pill.classList.add(saldo > 0 ? 'sx-warn' : 'sx-ok');
              pill.innerHTML = '<span class="dot"></span><span class="sx-mono">' + fmt(saldo) + '</span>';
            } else {
              saldoTd.textContent = fmt(saldo);
            }
          }
        }
      }

      // actualizar meta método (visual)
      const rowEl = $('sxRow-' + id);
      if(rowEl){
      const effectivePay = (data && data.pay_method !== undefined) ? data.pay_method : pay;

      const effectiveProv = (data && data.pay_provider !== undefined) ? data.pay_provider : '';
      const effectiveSt   = (data && data.pay_status !== undefined) ? data.pay_status : '';

        upsertMetaPay(rowEl, effectivePay, effectiveProv, effectiveSt);
      }
      sxToast((data && data.message) ? data.message : 'Guardado.', 'ok');

    }catch(err){
      sxToast('Error de red/JS: ' + (err && err.message ? err.message : String(err)), 'bad');
    }finally{
      if(btn){ btn.disabled = false; btn.textContent = oldBtnText || 'Guardar cambios'; }
    }
  };

  // Delegación global (tu forma correcta)
  document.addEventListener('click', function(ev){
    const el = ev.target.closest('[data-sx-save]');
    if(!el) return;
    ev.preventDefault();
    const id = el.getAttribute('data-sx-save') || '';
    window.sxSaveRow(id);
  });

    // ===== PDF Preview Modal (Vista previa) =====
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
      // fallback simple
      let glue = (url.indexOf('?') >= 0) ? '&' : '?';
      const q = Object.keys(params||{}).map(k => encodeURIComponent(k)+'='+encodeURIComponent(String(params[k]))).join('&');
      return url + glue + q;
    }
  }

  function openPdfModal(opts){
    if(!pdfModal || !pdfFrame) return;

    const url    = String((opts && opts.url) || '').trim();
    const acc    = String((opts && opts.account) || '—');
    const per    = String((opts && opts.period) || '—');

    if(url === ''){
      sxToast('No hay URL de PDF para vista previa.', 'bad');
      return;
    }

    // ✅ Vista previa debe ser inline para iframe
    const previewUrl = withParams(url, { inline: 1, modal: 1 });

    // Labels
    if(pdfAccLbl) pdfAccLbl.textContent = acc || '—';
    if(pdfPerLbl) pdfPerLbl.textContent = per || '—';

    // Links
    if(pdfOpenNew)  pdfOpenNew.setAttribute('href', previewUrl);
    if(pdfDownload) pdfDownload.setAttribute('href', url); // “Descargar” usa el endpoint normal

    // Iframe reload (forzado)
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

  // Close handlers
  if(pdfClose){
    pdfClose.addEventListener('click', function(ev){
      ev.preventDefault();
      closePdfModal();
    });
  }

  if(pdfModal){
    pdfModal.addEventListener('click', function(ev){
      // click fuera del card
      if(ev.target === pdfModal) closePdfModal();
    });
  }

  document.addEventListener('keydown', function(ev){
    if(ev.key === 'Escape'){
      if(pdfModal && pdfModal.classList.contains('on')) closePdfModal();
    }
  });

  // Delegación click: botón Vista previa
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('[data-sx-pdf-preview="1"]');
    if(!btn) return;

    ev.preventDefault();

    const url     = btn.getAttribute('data-url') || '';
    const account = btn.getAttribute('data-account') || '—';
    const period  = btn.getAttribute('data-period') || '—';

    openPdfModal({ url, account, period });
  });


    // init
  applyOnlySelectedFromQuery();
  updateBulk();
  try{ console.log('[P360] statements index scripts loaded'); }catch(e){}
})();

</script>
@endpush

@endsection
