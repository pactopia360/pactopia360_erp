{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\finance\income\index.blade.php --}}
@extends('layouts.admin')

@section('title','Finanzas · Ingresos (Ventas)')

@php
  // ==========================================================
  // Inputs (tolerante a distintas llaves de servicios viejos/nuevos)
  // ==========================================================
  $f    = $filters ?? [];
  $k    = $kpis    ?? ($kpi ?? []); // soporta $kpi si algún servicio lo manda así
  $rows = $rows    ?? collect();

  // Defaults de filtros (tolerante)
  $yearSel  = (int) (data_get($f, 'year', now()->year) ?? now()->year);
  $monthSel = (string) (data_get($f, 'month', 'all') ?? 'all');

  // Normalización origin legacy
  $originSel = (string) (data_get($f, 'origin', 'all') ?? 'all');
  if ($originSel === 'no_recurrente') $originSel = 'unico';

  // En esta vista se usa "st" en memoria (legacy) pero el form usa "status"
  $stSel     = (string) (data_get($f, 'st', data_get($f, 'status', 'all')) ?? 'all');
  $invSel    = (string) (data_get($f, 'invSt', data_get($f, 'invoice_status', 'all')) ?? 'all');
  $vendorSel = (string) (data_get($f, 'vendorId', data_get($f, 'vendor_id', 'all')) ?? 'all');
  $qSearch   = (string) (data_get($f, 'qSearch', data_get($f, 'q', '')) ?? '');

  // Vendor list (tolerante: vendor_list/venders)
  $vendorList = collect(data_get($f, 'vendor_list', data_get($f, 'vendors', [])));

  $money = function($n){
    $x = (float) ($n ?? 0);
    return '$' . number_format($x, 2);
  };

  $fmtDate = function($d){
    if (empty($d)) return '—';
    try { return \Illuminate\Support\Carbon::parse($d)->format('Y-m-d'); }
    catch (\Throwable $e) { return '—'; }
  };

  $pill = function(string $label, string $tone='muted'){
    $tone = strtolower($tone);
    $map = [
      'muted' => ['#f1f5f9','#0f172a'],
      'info'  => ['#e0f2fe','#075985'],
      'ok'    => ['#dcfce7','#166534'],
      'warn'  => ['#fff7ed','#9a3412'],
      'bad'   => ['#fee2e2','#991b1b'],
      'dark'  => ['#0f172a','#ffffff'],
    ];
    $v = $map[$tone] ?? $map['muted'];
    $lab = e($label);
    return '<span class="p360-pill" style="background:'.$v[0].';color:'.$v[1].'">'.$lab.'</span>';
  };

  $badgeEc = function(string $sRaw){
    $s = strtolower(trim((string)$sRaw));
    $map = [
      'pagado'  => ['#dcfce7','#166534','Pagado'],
      'emitido' => ['#e0f2fe','#075985','Emitido'],
      'pending' => ['#fff7ed','#9a3412','Pending'],
      'vencido' => ['#fee2e2','#991b1b','Vencido'],
    ];
    $v = $map[$s] ?? ['#f1f5f9','#334155', strtoupper($s ?: '—')];
    return '<span class="p360-badge" style="background:'.$v[0].';color:'.$v[1].'">'.$v[2].'</span>';
  };

  $badgeInvoice = function(?string $sRaw){
    $s = strtolower(trim((string)($sRaw ?? '')));
    $map = [
      'issued'    => ['#dcfce7','#166534','Facturada'],
      'ready'     => ['#e0f2fe','#075985','En proceso'],
      'requested' => ['#fff7ed','#9a3412','Solicitada'],
      'cancelled' => ['#fee2e2','#991b1b','Cancelada'],
      'pending'   => ['#f1f5f9','#334155','Pending'],
      'sin_solicitud' => ['#f1f5f9','#334155','Sin solicitud'],
      ''          => ['#f1f5f9','#334155','—'],
    ];
    $v = $map[$s] ?? ['#f1f5f9','#334155', strtoupper($s ?: '—')];
    return '<span class="p360-badge" style="background:'.$v[0].';color:'.$v[1].'">'.$v[2].'</span>';
  };

  $months = [
    '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
    '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'
  ];

  $routeBase = route('admin.finance.income.index');

  $monthName = function(string $mm) use ($months){ return $months[$mm] ?? $mm; };
  $periodToYear = function($period){
    $p = (string)($period ?? '');
    return preg_match('/^\d{4}\-\d{2}$/', $p) ? (int)substr($p,0,4) : (int)now()->format('Y');
  };
  $periodToMonth = function($period){
    $p = (string)($period ?? '');
    return preg_match('/^\d{4}\-\d{2}$/', $p) ? substr($p,5,2) : (string)now()->format('m');
  };

  // ==========================================================
  // Totales del grid
  // ==========================================================
  $sumSub = (float) ($rows->sum(fn($x) => (float)($x->subtotal ?? 0)));
  $sumIva = (float) ($rows->sum(fn($x) => (float)($x->iva ?? 0)));
  $sumTot = (float) ($rows->sum(fn($x) => (float)($x->total ?? 0)));

  // ==========================================================
  // KPIs cobranza por periodo (tolerante: amount/total)
  // ==========================================================
  $amtPending  = (float) (data_get($k, 'pending.amount', data_get($k,'pending.total',0)));
  $amtEmitido  = (float) (data_get($k, 'emitido.amount', data_get($k,'emitido.total',0)));
  $amtPagado   = (float) (data_get($k, 'pagado.amount', data_get($k,'pagado.total',0)));
  $amtVencido  = (float) (data_get($k, 'vencido.amount', data_get($k,'vencido.total',0)));
  $amtPorPagar = $amtPending + $amtEmitido + $amtVencido;

  // ==========================================================
  // Caja por fecha real de pago (paid_at/f_pago)
  // - Se calcula con $rows (no depende del periodo contable)
  // ==========================================================
  $selectedYear = (int) $yearSel;
  $selectedYm   = ($monthSel !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/',$monthSel))
    ? sprintf('%04d-%s', $selectedYear, $monthSel)
    : null;

  $paidCashTotal = 0.0;
  $paidCashCount = 0;
  $paidCashTotalAllYear = 0.0;
  $paidCashCountAllYear = 0;

  foreach ($rows as $rx) {
    // estatus pagado tolerante: ec_status/statement_status
    $ec = strtolower(trim((string) (data_get($rx,'ec_status') ?? data_get($rx,'statement_status') ?? '')));
    if ($ec !== 'pagado') continue;

    $dtRaw = (string) (data_get($rx, 'f_pago') ?: data_get($rx, 'paid_at') ?: data_get($rx,'paid_date') ?: '');
    if ($dtRaw === '') continue;

    try { $dt = \Illuminate\Support\Carbon::parse($dtRaw); }
    catch (\Throwable $e) { continue; }

    $ym = $dt->format('Y-m');
    $yy = (int) $dt->format('Y');

    $val = (float) (data_get($rx, 'total') ?? 0);

    if ($yy === $selectedYear) {
      $paidCashTotalAllYear += $val;
      $paidCashCountAllYear++;
    }

    if ($selectedYm && $ym === $selectedYm) {
      $paidCashTotal += $val;
      $paidCashCount++;
    }
  }

  if (!$selectedYm) {
    $paidCashTotal = $paidCashTotalAllYear;
    $paidCashCount = $paidCashCountAllYear;
  }

  // ==========================================================
  // Rutas
  // ==========================================================
  $rtSalesIndex  = \Illuminate\Support\Facades\Route::has('admin.finance.sales.index') ? route('admin.finance.sales.index') : null;
  $rtSalesCreate = \Illuminate\Support\Facades\Route::has('admin.finance.sales.create') ? route('admin.finance.sales.create') : null;
  $rtVendors     = \Illuminate\Support\Facades\Route::has('admin.finance.vendors.index') ? route('admin.finance.vendors.index') : null;
  $rtCommissions = \Illuminate\Support\Facades\Route::has('admin.finance.commissions.index') ? route('admin.finance.commissions.index') : null;
  $rtProjections = \Illuminate\Support\Facades\Route::has('admin.finance.projections.index') ? route('admin.finance.projections.index') : null;

  $rtInvoiceReq    = \Illuminate\Support\Facades\Route::has('admin.billing.invoices.requests.index') ? route('admin.billing.invoices.requests.index') : null;
  $rtStatementsHub = \Illuminate\Support\Facades\Route::has('admin.billing.statements_hub.index') ? route('admin.billing.statements_hub.index') : null;

 // ✅ Canon: routes/admin.php => admin.finance.income.row
  $rtIncomeUpsert   = \Illuminate\Support\Facades\Route::has('admin.finance.income.row')
    ? route('admin.finance.income.row')
    : null;

  $hasToggleInclude = \Illuminate\Support\Facades\Route::has('admin.finance.sales.toggleInclude');

  // ==========================================================
  // Opciones para modal
  // ==========================================================
  $vendorOptions = $vendorList->map(function($vv){
    return ['id'=>(string)data_get($vv,'id',''), 'name'=>(string)data_get($vv,'name','')];
  })->values()->all();

  $ecOptions = [
    ['value'=>'pending','label'=>'Pending'],
    ['value'=>'emitido','label'=>'Emitido'],
    ['value'=>'pagado','label'=>'Pagado'],
    ['value'=>'vencido','label'=>'Vencido'],
  ];

  $invOptions = [
    ['value'=>'pending','label'=>'Pending'],
    ['value'=>'requested','label'=>'Solicitada'],
    ['value'=>'ready','label'=>'En proceso'],
    ['value'=>'issued','label'=>'Facturada'],
    ['value'=>'cancelled','label'=>'Cancelada'],
  ];

  // ==========================================================
  // Cliente label tolerante
  // ==========================================================
  $clientLabel = function($r){
    $candidates = [
      data_get($r, 'client'),
      data_get($r, 'company'),
      data_get($r, 'client_name'),
      data_get($r, 'account_name'),
      data_get($r, 'account_company'),
      data_get($r, 'razon_social'),
      data_get($r, 'nombre_comercial'),
      data_get($r, 'nombre'),
      data_get($r, 'name'),
    ];

    foreach ($candidates as $v) {
      $v = trim((string) $v);
      if ($v !== '' && $v !== '—' && $v !== '-') return $v;
    }

    $aid = trim((string) data_get($r, 'account_id', ''));
    if ($aid !== '') return 'Cuenta: '.$aid;

    return '—';
  };

  // ==========================================================
  // Helpers de row: source/status
  // ==========================================================
  $rowSource = function($r){
    $src = (string) (data_get($r,'source') ?? data_get($r,'row_type') ?? '');
    if ($src !== '') return $src;
    // fallback heurístico
    if (!empty(data_get($r,'sale_id')) || (string)data_get($r,'row_type')==='venta') return 'sale';
    return 'statement';
  };

  $rowEcStatus = function($r){
    return (string) (data_get($r,'ec_status') ?? data_get($r,'statement_status') ?? 'pending');
  };

  $rowInvStatus = function($r){
    return (string) (data_get($r,'invoice_status') ?? 'sin_solicitud');
  };

  // ==========================================================
  // UI label para tipo
  // ==========================================================
  $tipoLabel = function(string $src){
    return $src === 'projection' ? 'Proyección' : ($src === 'sale' || $src === 'venta' ? 'Venta' : 'Statement');
  };

  $tipoTone = function(string $src){
    return $src === 'projection' ? 'info' : ($src === 'sale' || $src === 'venta' ? 'warn' : 'muted');
  };
@endphp

@section('content')
  {{-- Base existente (modal + básicos); vNext domina look & layout --}}
  <link rel="stylesheet" href="{{ asset('assets/admin/css/finance-income.css') }}?v={{ @filemtime(public_path('assets/admin/css/finance-income.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/admin/css/finance-income.vnext.css') }}?v={{ @filemtime(public_path('assets/admin/css/finance-income.vnext.css')) }}">

  <div class="p360-income-vnext">

    {{-- =========================
       TOPBAR (1 solo encabezado, limpio)
       - Conserva funciones: links secundarios van a "Más"
       ========================= --}}
    <div class="p360-card p360-card-pad p360-topbar">
      <div class="p360-topbar-left">
        <h1 class="p360-topbar-title">Ingresos</h1>
        <div class="p360-topbar-sub">Ventas · Statements · Proyecciones</div>
      </div>

      <div class="p360-topbar-right">
        @if($rtSalesCreate)
          <a class="p360-btn p360-btn-primary" href="{{ $rtSalesCreate }}">+ Crear venta</a>
        @endif

        <details class="p360-more">
          <summary class="p360-btn p360-btn-soft" role="button" aria-haspopup="menu">
            Más
            <span class="p360-caret" aria-hidden="true">▾</span>
          </summary>

          <div class="p360-more-menu" role="menu">
            @if($rtSalesIndex)
              <a class="p360-more-item" role="menuitem" href="{{ $rtSalesIndex }}">Ventas</a>
            @endif
            @if($rtVendors)
              <a class="p360-more-item" role="menuitem" href="{{ $rtVendors }}">Vendedores</a>
            @endif
            @if($rtCommissions)
              <a class="p360-more-item" role="menuitem" href="{{ $rtCommissions }}">Comisiones</a>
            @endif
            @if($rtProjections)
              <a class="p360-more-item" role="menuitem" href="{{ $rtProjections }}">Proyecciones</a>
            @endif

            @if($rtStatementsHub || $rtInvoiceReq)
              <div class="p360-more-sep"></div>
            @endif

            @if($rtStatementsHub)
              <a class="p360-more-item" role="menuitem" href="{{ $rtStatementsHub }}">Statements</a>
            @endif
            @if($rtInvoiceReq)
              <a class="p360-more-item" role="menuitem" href="{{ $rtInvoiceReq }}">Solicitudes CFDI</a>
            @endif
          </div>
        </details>
      </div>
    </div>

    {{-- =========================
       FILTERS
       ========================= --}}
    <div class="p360-card p360-card-pad p360-filters-card">
      <form method="GET" action="{{ $routeBase }}" class="p360-filters-form">

        <div class="p360-filters-row">
          <div class="fi">
            <label>Año</label>
            <select name="year" class="p360-ctl">
              @for($y = now()->year - 2; $y <= now()->year + 2; $y++)
                <option value="{{ $y }}" @selected((int)$yearSel===$y)>{{ $y }}</option>
              @endfor
            </select>
          </div>

          <div class="fi">
            <label>Mes</label>
            <select name="month" class="p360-ctl">
              <option value="all" @selected($monthSel==='all')>Todos</option>
              @foreach($months as $mm=>$name)
                <option value="{{ $mm }}" @selected($monthSel===$mm)>{{ $name }}</option>
              @endforeach
            </select>
          </div>

          <div class="fi fi-search">
            <label>Buscar</label>
            <input name="q" value="{{ $qSearch }}" placeholder="Cliente, RFC, UUID, cuenta, vendedor..." class="p360-ctl"/>
          </div>

          <div class="fi fi-actions">
            <label class="sr-only">Acciones</label>
            <div class="btns">
              <button type="submit" class="p360-btn p360-btn-primary">Filtrar</button>
              <a href="{{ $routeBase }}" class="p360-btn p360-btn-ghost">Limpiar</a>
            </div>
          </div>
        </div>

        @php
          $advOpen = (
            ($originSel ?? 'all') !== 'all'
            || ($vendorSel ?? 'all') !== 'all'
            || ($stSel ?? 'all') !== 'all'
            || ($invSel ?? 'all') !== 'all'
          );
        @endphp

        <details class="p360-advanced" @if($advOpen) open @endif>
          <summary>
            <span>Filtros avanzados</span>
            <span class="hint">Origen · Vendedor · Estatus</span>
          </summary>

          <div class="p360-advanced-grid">
            <div class="fi">
              <label>Origen</label>
              <select name="origin" class="p360-ctl">
                <option value="all" @selected($originSel==='all')>Todos</option>
                <option value="recurrente" @selected($originSel==='recurrente')>Recurrente</option>
                <option value="unico" @selected($originSel==='unico')>Único</option>
              </select>
            </div>

            <div class="fi">
              <label>Vendedor</label>
              <select name="vendor_id" class="p360-ctl">
                <option value="all" @selected($vendorSel==='all')>Todos</option>
                @foreach($vendorList as $vv)
                  @php $vid=(string)data_get($vv,'id',''); $vnm=(string)data_get($vv,'name',''); @endphp
                  @if($vid !== '')
                    <option value="{{ $vid }}" @selected($vendorSel===$vid)>{{ $vnm }}</option>
                  @endif
                @endforeach
              </select>
            </div>

            <div class="fi">
              <label>Estatus E.Cta</label>
              <select name="status" class="p360-ctl">
                <option value="all" @selected($stSel==='all')>Todos</option>
                <option value="pending" @selected($stSel==='pending')>Pending</option>
                <option value="emitido" @selected($stSel==='emitido')>Emitido</option>
                <option value="pagado" @selected($stSel==='pagado')>Pagado</option>
                <option value="vencido" @selected($stSel==='vencido')>Vencido</option>
              </select>
            </div>

            <div class="fi">
              <label>Estatus Factura</label>
              <select name="invoice_status" class="p360-ctl">
                <option value="all" @selected($invSel==='all')>Todos</option>
                <option value="pending" @selected($invSel==='pending')>Pending</option>
                <option value="requested" @selected($invSel==='requested')>Solicitada</option>
                <option value="ready" @selected($invSel==='ready')>En proceso</option>
                <option value="issued" @selected($invSel==='issued')>Facturada</option>
                <option value="cancelled" @selected($invSel==='cancelled')>Cancelada</option>
              </select>
            </div>
          </div>
        </details>

      </form>
    </div>

    {{-- =========================
       KPIs (PRO, sin encimar)
       ========================= --}}
    <div class="p360-kpis-wrap">

      <div class="p360-kpis-main">
        <div class="p360-kpi-card">
          <div class="k">Subtotal</div>
          <div class="v">{{ $money($sumSub) }}</div>
        </div>

        <div class="p360-kpi-card">
          <div class="k">IVA</div>
          <div class="v">{{ $money($sumIva) }}</div>
        </div>

        <div class="p360-kpi-card strong">
          <div class="k">Total</div>
          <div class="v">{{ $money($sumTot) }}</div>
        </div>

        <div class="p360-kpi-card">
          <div class="k">Filas</div>
          <div class="v">{{ (int) $rows->count() }}</div>
        </div>
      </div>

      <div class="p360-kpis-secondary">

        <div class="p360-pay-card" title="Cobranza por periodo (lo aplicado al mes/periodo del grid, aunque el pago se haya realizado en otra fecha).">
          <div class="p360-pay-head">
            <div class="lbl">Cobranza (Periodo)</div>
            <div class="hint">
              {{ $monthSel === 'all' ? 'Año '.$yearSel : ($months[$monthSel] ?? $monthSel).' '.$yearSel }}
            </div>
          </div>

          <div class="p360-pay-cells">
            <div class="c">
              <div class="k">Pagado</div>
              <div class="v">{{ $money($amtPagado) }}</div>
            </div>
            <div class="c">
              <div class="k">Por pagar</div>
              <div class="v">{{ $money($amtPorPagar) }}</div>
            </div>
            <div class="c">
              <div class="k">Pending</div>
              <div class="v">{{ $money($amtPending) }}</div>
            </div>
            <div class="c">
              <div class="k">Emitido</div>
              <div class="v">{{ $money($amtEmitido) }}</div>
            </div>
          </div>
        </div>

        <div class="p360-pay-card" title="Caja por fecha real de pago (f_pago/paid_at). Útil cuando payments.period no coincide con el mes de paid_at.">
          <div class="p360-pay-head">
            <div class="lbl">Caja (Fecha pago)</div>
            <div class="hint">
              {{ $monthSel === 'all' ? 'Año '.$yearSel : ($months[$monthSel] ?? $monthSel).' '.$yearSel }}
            </div>
          </div>

          <div class="p360-pay-cells">
            <div class="c">
              <div class="k">Pagado</div>
              <div class="v">{{ $money($paidCashTotal) }}</div>
            </div>
            <div class="c">
              <div class="k">Pagos</div>
              <div class="v">{{ (int) $paidCashCount }}</div>
            </div>
            <div class="c">
              <div class="k">Base</div>
              <div class="v" style="font-size:12px;opacity:.8">f_pago / paid_at</div>
            </div>
            <div class="c">
              <div class="k">Impacto</div>
              <div class="v" style="font-size:12px;opacity:.8">no altera periodos</div>
            </div>
          </div>
        </div>

      </div>
    </div>

    {{-- =========================
       TABLE (pro)
       ========================= --}}
    <div class="p360-card p360-table-card p360-table-card-vnext">
      <div class="p360-table-head p360-table-head-min">
        <div class="meta">
          <span class="lbl">Año</span> <b>{{ $yearSel }}</b>
          <span class="dot">·</span>
          <span class="lbl">Mes</span> <b>{{ $monthSel === 'all' ? 'Todos' : ($months[$monthSel] ?? $monthSel) }}</b>
        </div>
      </div>

      <div class="p360-table-wrap p360-table-scroll" role="region" aria-label="Tabla de ingresos (scroll horizontal)">
        <table class="p360-table p360-table-excel p360-income-table">
          <thead>
            <tr>
              <th class="p360-th p360-sticky-col p360-sticky-1">Año</th>
              <th class="p360-th p360-sticky-col p360-sticky-2">Mes</th>
              <th class="p360-th">Vendedor</th>
              <th class="p360-th">Cliente</th>
              <th class="p360-th">Descripción</th>
              <th class="p360-th">Origen</th>
              <th class="p360-th">Periodicidad</th>
              <th class="p360-th p360-th-num">Subtotal</th>
              <th class="p360-th p360-th-num">IVA</th>
              <th class="p360-th p360-th-num">Total</th>
              <th class="p360-th">F Cta</th>
              <th class="p360-th">F Pago</th>
              <th class="p360-th">Estatus</th>
              <th class="p360-th">Factura</th>
              <th class="p360-th p360-th-actions">Acción</th>
            </tr>
          </thead>

          <tbody>
            @forelse($rows as $r)
              @php
                $src  = (string) $rowSource($r);
                $tipo = (string) $tipoLabel($src);
                $tTon = (string) $tipoTone($src);

                $client = (string) $clientLabel($r);
                $desc   = (string) (data_get($r,'description') ?? '—');
                $vendor = (string) (data_get($r,'vendor') ?? data_get($r,'vendor_name') ?? '—');
                $period = (string) (data_get($r,'period') ?? '—');

                $y = $periodToYear($period);
                $m = $periodToMonth($period);
                $mName = $monthName($m);

                $origin = strtolower((string)(data_get($r,'origin') ?? ''));
                if ($origin === 'no_recurrente') $origin = 'unico';
                $originTone = $origin === 'recurrente' ? 'ok' : 'warn';

                $perio = strtolower((string)(data_get($r,'periodicity') ?? ''));
                $perioTone = $perio === 'anual' ? 'dark' : ($perio === 'mensual' ? 'info' : 'muted');

                $saleId = (int) (data_get($r,'sale_id') ?? data_get($r,'row_id') ?? 0);

                $ecSt = (string) $rowEcStatus($r);
                $invSt = (string) $rowInvStatus($r);

                // Fechas tolerantes
                $fCta  = data_get($r,'f_cta') ?: data_get($r,'sent_at') ?: null;
                $fPago = data_get($r,'f_pago') ?: data_get($r,'paid_at') ?: data_get($r,'paid_date') ?: null;
                $fFac  = data_get($r,'f_factura') ?: data_get($r,'invoice_date') ?: data_get($r,'issued_at') ?: null;

                $rowPayload = [
                  'source' => $src,
                  'tipo' => $tipo,
                  'period' => (string)($period ?? ''),
                  'account_id' => (string)(data_get($r,'account_id') ?? ''),
                  'client' => (string)($client ?? ''),
                  'rfc_emisor' => (string)(data_get($r,'rfc_emisor') ?? data_get($r,'sender_rfc') ?? ''),
                  'origin' => (string)($origin ?? ''),
                  'periodicity' => (string)($perio ?? ''),
                  'vendor' => (string)($vendor ?? ''),
                  'vendor_id' => (string)(data_get($r,'vendor_id') ?? ''),
                  'description' => (string)($desc ?? ''),
                  'subtotal' => (float)(data_get($r,'subtotal') ?? 0),
                  'iva' => (float)(data_get($r,'iva') ?? 0),
                  'total' => (float)(data_get($r,'total') ?? 0),
                  'ec_status' => (string)($ecSt ?? ''),
                  'invoice_status' => (string)($invSt ?? ''),
                  'invoice_status_raw' => (string)(data_get($r,'invoice_status_raw') ?? ''),
                  'rfc_receptor' => (string)(data_get($r,'rfc_receptor') ?? data_get($r,'receiver_rfc') ?? ''),
                  'forma_pago' => (string)(data_get($r,'forma_pago') ?? data_get($r,'pay_method') ?? ''),
                  'f_cta' => (string)($fCta ?? ''),
                  'f_mov' => (string)(data_get($r,'f_mov') ?? ''),
                  'f_factura' => (string)($fFac ?? ''),
                  'f_pago' => (string)($fPago ?? ''),
                  'cfdi_uuid' => (string)(data_get($r,'cfdi_uuid') ?? data_get($r,'invoice_uuid') ?? data_get($r,'invoice_uuid') ?? ''),
                  'sale_id' => $saleId,
                  'include_in_statement' => (int)(data_get($r,'include_in_statement') ?? 0),
                  'statement_period_target' => (string)(data_get($r,'statement_period_target') ?? ''),
                  'notes' => (string)(data_get($r,'notes') ?? ''),
                ];
              @endphp

              <tr class="p360-row">
                <td class="p360-td p360-sticky-col p360-sticky-1 p360-col-year">{{ $y }}</td>
                <td class="p360-td p360-sticky-col p360-sticky-2 p360-col-month">{{ $mName }}</td>

                <td class="p360-td p360-col-vendor">
                  <div class="p360-cell-main">{{ $vendor }}</div>
                  @if(!empty(data_get($r,'vendor_id')))
                    <div class="p360-cell-sub">ID: {{ data_get($r,'vendor_id') }}</div>
                  @endif
                </td>

                <td class="p360-td p360-col-client">
                  <div class="p360-cell-main clamp2" title="{{ $client }}">{{ $client }}</div>
                  <div class="p360-cell-sub clamp1">
                    Cuenta: <code class="p360-code">{{ data_get($r,'account_id') ?? '—' }}</code>
                    @php $rfcEm = (string)(data_get($r,'rfc_emisor') ?? data_get($r,'sender_rfc') ?? ''); @endphp
                    @if($rfcEm !== '') · RFC: {{ $rfcEm }} @endif
                  </div>
                  <div class="p360-cell-meta">
                    {!! $pill($tipo, $tTon) !!}
                  </div>
                </td>

                <td class="p360-td p360-col-desc">
                  <div class="p360-cell-main clamp2">{{ $desc }}</div>
                  <div class="p360-cell-sub">SaleID: {{ $saleId ?: '—' }}</div>
                </td>

                <td class="p360-td p360-col-origin">{!! $pill(($origin ?: '—'), $originTone) !!}</td>
                <td class="p360-td p360-col-perio">{!! $pill(($perio ?: '—'), $perioTone) !!}</td>

                <td class="p360-td p360-td-num p360-col-money" data-col="subtotal">{{ $money(data_get($r,'subtotal') ?? 0) }}</td>
                <td class="p360-td p360-td-num p360-col-money" data-col="iva">{{ $money(data_get($r,'iva') ?? 0) }}</td>
                <td class="p360-td p360-td-num p360-col-money p360-col-total" data-col="total">{{ $money(data_get($r,'total') ?? 0) }}</td>

                <td class="p360-td p360-col-date">{{ $fmtDate($fCta) }}</td>
                <td class="p360-td p360-col-date">{{ $fmtDate($fPago) }}</td>

                <td class="p360-td p360-col-status">{!! $badgeEc($ecSt) !!}</td>
                <td class="p360-td p360-col-status">{!! $badgeInvoice($invSt) !!}</td>

                <td class="p360-td p360-col-actions">
                  <button type="button"
                    class="p360-actions-btn p360-actions-btn-sm"
                    data-income-open="1"
                    data-income='@json($rowPayload)'
                    title="Ver / Editar"
                  >Ver</button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="15" class="p360-td p360-muted" style="padding:18px">
                  No hay registros con los filtros actuales.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Mobile cards --}}
      <div class="p360-cards">
        @forelse($rows as $r)
          @php
            $src  = (string) $rowSource($r);
            $tipo = (string) $tipoLabel($src);
            $tTon = (string) $tipoTone($src);

            $client = (string) $clientLabel($r);
            $period = (string) (data_get($r,'period') ?? '—');

            $origin = strtolower((string)(data_get($r,'origin') ?? ''));
            if ($origin === 'no_recurrente') $origin = 'unico';
            $originTone = $origin === 'recurrente' ? 'ok' : 'warn';

            $perio = strtolower((string)(data_get($r,'periodicity') ?? ''));
            $perioTone = $perio === 'anual' ? 'dark' : ($perio === 'mensual' ? 'info' : 'muted');

            $saleId = (int) (data_get($r,'sale_id') ?? data_get($r,'row_id') ?? 0);

            $ecSt = (string) $rowEcStatus($r);
            $invSt = (string) $rowInvStatus($r);

            $rowPayload = [
              'source' => $src,
              'tipo' => $tipo,
              'period' => (string)($period ?? ''),
              'account_id' => (string)(data_get($r,'account_id') ?? ''),
              'client' => (string)($client ?? ''),
              'rfc_emisor' => (string)(data_get($r,'rfc_emisor') ?? data_get($r,'sender_rfc') ?? ''),
              'origin' => (string)($origin ?? ''),
              'periodicity' => (string)($perio ?? ''),
              'vendor' => (string)(data_get($r,'vendor') ?? data_get($r,'vendor_name') ?? ''),
              'vendor_id' => (string)(data_get($r,'vendor_id') ?? ''),
              'description' => (string)(data_get($r,'description') ?? ''),
              'subtotal' => (float)(data_get($r,'subtotal') ?? 0),
              'iva' => (float)(data_get($r,'iva') ?? 0),
              'total' => (float)(data_get($r,'total') ?? 0),
              'ec_status' => (string)($ecSt ?? ''),
              'invoice_status' => (string)($invSt ?? ''),
              'invoice_status_raw' => (string)(data_get($r,'invoice_status_raw') ?? ''),
              'rfc_receptor' => (string)(data_get($r,'rfc_receptor') ?? data_get($r,'receiver_rfc') ?? ''),
              'forma_pago' => (string)(data_get($r,'forma_pago') ?? data_get($r,'pay_method') ?? ''),
              'notes' => (string)(data_get($r,'notes') ?? ''),
              'f_cta' => (string)(data_get($r,'f_cta') ?? data_get($r,'sent_at') ?? ''),
              'f_mov' => (string)(data_get($r,'f_mov') ?? ''),
              'f_factura' => (string)(data_get($r,'f_factura') ?? data_get($r,'invoice_date') ?? ''),
              'f_pago' => (string)(data_get($r,'f_pago') ?? data_get($r,'paid_at') ?? data_get($r,'paid_date') ?? ''),
              'cfdi_uuid' => (string)(data_get($r,'cfdi_uuid') ?? data_get($r,'invoice_uuid') ?? ''),
              'sale_id' => $saleId,
              'include_in_statement' => (int)(data_get($r,'include_in_statement') ?? 0),
              'statement_period_target' => (string)(data_get($r,'statement_period_target') ?? ''),
            ];
          @endphp

          <div class="p360-rowcard">
            <div class="p360-row-top">
              <div>
                <div class="p360-row-client" title="{{ $client }}">{{ $client }}</div>
                <div class="p360-row-sub">
                  {{ $period }} · {{ data_get($r,'vendor') ?? data_get($r,'vendor_name') ?? '—' }}
                  @php $rfcEm = (string)(data_get($r,'rfc_emisor') ?? data_get($r,'sender_rfc') ?? ''); @endphp
                  @if($rfcEm !== '') · RFC: {{ $rfcEm }} @endif
                </div>
              </div>
              <button type="button" class="p360-actions-btn" data-income-open="1" data-income='@json($rowPayload)'>Ver</button>
            </div>

            <div class="p360-row-meta">
              {!! $pill($tipo, $tTon) !!}
              {!! $pill(($origin ?: '—'), $originTone) !!}
              {!! $pill(($perio ?: '—'), $perioTone) !!}
              {!! $badgeEc($ecSt) !!}
              {!! $badgeInvoice($invSt) !!}
            </div>

            <div class="p360-amtgrid">
              <div class="p360-amt">
                <div class="lbl">Subtotal</div>
                <div class="val">{{ $money(data_get($r,'subtotal') ?? 0) }}</div>
              </div>
              <div class="p360-amt">
                <div class="lbl">IVA</div>
                <div class="val">{{ $money(data_get($r,'iva') ?? 0) }}</div>
              </div>
              <div class="p360-amt">
                <div class="lbl">Total</div>
                <div class="val">{{ $money(data_get($r,'total') ?? 0) }}</div>
              </div>
            </div>

            <div class="p360-small p360-muted" style="margin-top:10px">
              {{ data_get($r,'description') ?? '—' }}
              @php $uuid = (string)(data_get($r,'cfdi_uuid') ?? data_get($r,'invoice_uuid') ?? ''); @endphp
              @if($uuid !== '')
                <div style="margin-top:6px">UUID: {{ $uuid }}</div>
              @endif
            </div>
          </div>
        @empty
          <div class="p360-rowcard p360-muted">No hay registros con los filtros actuales.</div>
        @endforelse
      </div>
    </div>

  </div>

  {{-- Modal (se conserva tal cual) --}}
  <div class="p360-modal-backdrop" id="p360IncomeModalBackdrop"></div>

  <div class="p360-modal" id="p360IncomeModal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="p360-modal-panel">
      <div class="p360-modal-head">
        <div>
          <h3 class="p360-modal-title" id="p360IncomeModalTitle">Detalle</h3>
          <div class="p360-modal-sub" id="p360IncomeModalSub">—</div>
        </div>
        <button type="button" class="p360-modal-close" data-income-close="1">Cerrar</button>
      </div>

      <div class="p360-modal-body">
        <div class="p360-split">
          <div class="p360-box">
            <h4 class="p360-box-title">Detalle (solo lectura)</h4>
            <div class="p360-grid" id="p360IncomeModalGrid"></div>
          </div>

          <div class="p360-box">
            <h4 class="p360-box-title">Editar (guardar en overrides / sales)</h4>

            <div class="p360-alert" id="p360IncomeAlert"></div>

            <div class="p360-alert bad" id="p360IncomeDanger" style="display:none; margin-top:10px;">
              <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                <div style="min-width:260px; flex:1;">
                  <div style="font-weight:950; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <span id="p360IncomeDangerTitle">Eliminar</span>
                    <span class="p360-pill" id="p360IncomeDangerPill" style="background:#fff1f2;color:#991b1b;">Acción</span>
                  </div>

                  <div style="margin-top:6px; font-weight:800;" id="p360IncomeDangerText">—</div>
                  <div class="p360-help" style="margin-top:8px;" id="p360IncomeDangerHelp">—</div>
                </div>

                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                  <button type="button" class="p360-btn p360-btn-danger" id="p360IncomeConfirmDeleteBtn">Sí, eliminar</button>
                  <button type="button" class="p360-btn" id="p360IncomeCancelDeleteBtn">Cancelar</button>
                </div>
              </div>
            </div>

            <form class="p360-form" id="p360IncomeEditForm">
              <input type="hidden" name="account_id" value="">
              <input type="hidden" name="period" value="">
              <input type="hidden" name="sale_id" value="">
              <input type="hidden" name="is_projection" value="0">

              <div class="fi">
                <label>Vendedor</label>
                <select name="vendor_id"><option value="">—</option></select>
              </div>

              <div class="fi">
                <label>Estatus E.Cta</label>
                <select name="ec_status"><option value="">—</option></select>
              </div>

              <div class="fi">
                <label>Estatus Factura</label>
                <select name="invoice_status"><option value="">—</option></select>
              </div>

              <div class="fi">
                <label>UUID</label>
                <input name="cfdi_uuid" placeholder="UUID CFDI (opcional)" />
              </div>

              <div class="fi">
                <label>RFC receptor</label>
                <input name="rfc_receptor" placeholder="RFC receptor (opcional)" />
              </div>

              <div class="fi">
                <label>Forma de pago</label>
                <input name="forma_pago" placeholder="Forma de pago (opcional)" />
              </div>

              <div class="fi">
                <label>Subtotal</label>
                <input name="subtotal" inputmode="decimal" placeholder="0.00" />
              </div>

              <div class="fi">
                <label>IVA</label>
                <input name="iva" inputmode="decimal" placeholder="0.00" />
              </div>

              <div class="fi">
                <label>Total</label>
                <input name="total" inputmode="decimal" placeholder="0.00" />
              </div>

              <div class="fi">
                <label>Incluir en Estado de Cuenta</label>
                <select name="include_in_statement">
                  <option value="">—</option>
                  <option value="1">Sí</option>
                  <option value="0">No</option>
                </select>
                <div class="p360-help">Solo aplica a ventas (source=sale).</div>
              </div>

              <div class="fi">
                <label>Periodo target (E.Cta)</label>
                <input name="statement_period_target" placeholder="YYYY-MM (opcional)" />
                <div class="p360-help">Ej: 2026-02</div>
              </div>

              <div class="fi-12">
                <label>Notas</label>
                <textarea name="notes" placeholder="Notas internas (opcional)"></textarea>
              </div>

              <div class="fi-12" style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="submit" class="p360-btn p360-btn-primary" id="p360IncomeSaveBtn" @if(!$rtIncomeUpsert) disabled @endif>
                  Guardar cambios
                </button>

                @if($rtIncomeUpsert)
                  <button type="button" class="p360-btn p360-btn-ghost" id="p360IncomeResetBtn">Revertir UI</button>
                @endif
              </div>

              @if(!$rtIncomeUpsert)
                <div class="p360-help" style="margin-top:6px">
                  ⚠️ Falta ruta <code class="p360-code">finance.income.row</code>.
                  En <code class="p360-code">routes/admin.php</code> debe existir algo como:
                  <code class="p360-code">Route::post('finance/income/row', [IncomeActionsController::class,'upsert'])->name('finance.income.row');</code>
                </div>
              @endif
            </form>
          </div>
        </div>
      </div>

      <div class="p360-modal-actions">
        <div class="p360-actions-left" id="p360IncomeModalLeft"></div>
        <div class="p360-actions-right">
          <button type="button" class="p360-btn p360-btn-danger" id="p360IncomeDeleteBtn" style="display:none;">Eliminar</button>
          <button type="button" class="p360-btn" data-income-close="1">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  @if($hasToggleInclude)
    <form id="p360IncomeToggleIncludeForm" method="POST" style="display:none">
      @csrf
    </form>
  @endif

  <script>
  (function(){
    const cfg = {
      upsertUrl: @json($rtIncomeUpsert),
      destroyUrlTpl: @json(\Illuminate\Support\Facades\Route::has('admin.finance.income.row.destroy')
        ? route('admin.finance.income.row.destroy', ['id' => '__ID__'])
        : null
      ),
      hasToggleInclude: @json($hasToggleInclude),
      toggleBase: @json(url('/admin/finance/sales')),
      salesCreate: @json($rtSalesCreate),
      salesIndex: @json($rtSalesIndex),
      invoicesReq: @json($rtInvoiceReq),
      stHub: @json($rtStatementsHub),
      vendors: @json($vendorOptions),
      ecOptions: @json($ecOptions),
      invOptions: @json($invOptions),
      csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
    };

    window.P360_FIN_INCOME = cfg;

    try {
      console.log('[P360_FIN_INCOME] ready:', {
        upsertUrl: cfg.upsertUrl,
        destroyUrlTpl: cfg.destroyUrlTpl,
        vendors: (cfg.vendors || []).length,
        ecOptions: (cfg.ecOptions || []).length,
        invOptions: (cfg.invOptions || []).length,
        hasCsrf: !!cfg.csrf,
      });
    } catch (e) {}
  })();
  </script>

  <script src="{{ asset('assets/admin/js/finance-income.js') }}?v={{ @filemtime(public_path('assets/admin/js/finance-income.js')) }}"></script>
@endsection