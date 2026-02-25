{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\finance\income\index.blade.php --}}
@extends('layouts.admin')

@section('title','Finanzas · Ingresos (Ventas)')

@php
  $f    = $filters ?? ['year'=>now()->format('Y'),'month'=>'all','origin'=>'all','st'=>'all','invSt'=>'all','vendorId'=>'all','qSearch'=>''];
  $k    = $kpis ?? [];
  $rows = $rows ?? collect();

  $vendorList = collect(data_get($f, 'vendor_list', []));
  $vendorSel  = (string) (data_get($f, 'vendorId', 'all') ?? 'all');

  if (($f['origin'] ?? 'all') === 'no_recurrente') $f['origin'] = 'unico';

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
    return '<span class="p360-pill" style="background:'.$v[0].';color:'.$v[1].'">'.$label.'</span>';
  };

  $badgeEc = function(string $s){
    $s = strtolower(trim($s));
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
  $yearSel  = (int) ($f['year'] ?? now()->year);
  $monthSel = (string) ($f['month'] ?? 'all');

  // Excel-like helpers (UI)
  $monthName = function(string $mm) use ($months){ return $months[$mm] ?? $mm; };
  $periodToYear = function($period){
    $p = (string)($period ?? '');
    return preg_match('/^\d{4}\-\d{2}$/', $p) ? (int)substr($p,0,4) : (int)now()->format('Y');
  };
  $periodToMonth = function($period){
    $p = (string)($period ?? '');
    return preg_match('/^\d{4}\-\d{2}$/', $p) ? substr($p,5,2) : (string)now()->format('m');
  };

  // Totales (como Excel)
  $sumSub = (float) ($rows->sum(fn($x) => (float)($x->subtotal ?? 0)));
  $sumIva = (float) ($rows->sum(fn($x) => (float)($x->iva ?? 0)));
  $sumTot = (float) ($rows->sum(fn($x) => (float)($x->total ?? 0)));

  // Mini cuadro estatus (usamos KPIs ya calculados)
  $amtPending  = (float) data_get($k, 'pending.amount', 0);
  $amtEmitido  = (float) data_get($k, 'emitido.amount', 0);
  $amtPagado   = (float) data_get($k, 'pagado.amount', 0);
  $amtPorPagar = $amtPending + $amtEmitido;

  // Rutas
  $rtSalesIndex  = \Illuminate\Support\Facades\Route::has('admin.finance.sales.index') ? route('admin.finance.sales.index') : null;
  $rtSalesCreate = \Illuminate\Support\Facades\Route::has('admin.finance.sales.create') ? route('admin.finance.sales.create') : null;
  $rtVendors     = \Illuminate\Support\Facades\Route::has('admin.finance.vendors.index') ? route('admin.finance.vendors.index') : null;
  $rtCommissions = \Illuminate\Support\Facades\Route::has('admin.finance.commissions.index') ? route('admin.finance.commissions.index') : null;
  $rtProjections = \Illuminate\Support\Facades\Route::has('admin.finance.projections.index') ? route('admin.finance.projections.index') : null;

  $rtInvoiceReq    = \Illuminate\Support\Facades\Route::has('admin.billing.invoices.requests.index') ? route('admin.billing.invoices.requests.index') : null;
  $rtStatementsHub = \Illuminate\Support\Facades\Route::has('admin.billing.statements_hub.index') ? route('admin.billing.statements_hub.index') : null;

  $rtIncomeUpsert    = \Illuminate\Support\Facades\Route::has('admin.finance.income.row') ? route('admin.finance.income.row') : null;
  $hasToggleInclude  = \Illuminate\Support\Facades\Route::has('admin.finance.sales.toggleInclude');

  // Para el modal: vendor list simplificada
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
@endphp

@section('content')
  {{-- CSS base (si ya existe) --}}
  <link rel="stylesheet" href="{{ asset('assets/admin/css/finance-income.css') }}">
  {{-- CSS Excel-like (nuevo) --}}
  <link rel="stylesheet" href="{{ asset('assets/admin/css/finance-income-excel.css') }}">

  <div class="p360-income-wrap">

    <div class="p360-card p360-card-pad">
      <div class="p360-income-head">
        <div>
          <h1 class="p360-income-title">Ingresos (Ventas)</h1>
          <p class="p360-income-sub">
            Fuente unificada: <strong>Statements</strong>, <strong>Proyecciones</strong> y <strong>Ventas únicas</strong>. Edición y acciones en emergentes.
          </p>
        </div>

        <div class="p360-toolbar">
          @if($rtSalesCreate)<a class="p360-btn p360-btn-primary" href="{{ $rtSalesCreate }}">+ Crear venta</a>@endif
          @if($rtSalesIndex)<a class="p360-btn" href="{{ $rtSalesIndex }}">Ventas</a>@endif
          @if($rtVendors)<a class="p360-btn" href="{{ $rtVendors }}">Vendedores</a>@endif
          @if($rtCommissions)<a class="p360-btn" href="{{ $rtCommissions }}">Comisiones</a>@endif
          @if($rtProjections)<a class="p360-btn" href="{{ $rtProjections }}">Proyecciones</a>@endif
        </div>

        <form method="GET" action="{{ $routeBase }}" class="p360-income-filters">
          <select name="year" class="p360-ctl">
            @for($y = now()->year - 2; $y <= now()->year + 2; $y++)
              <option value="{{ $y }}" @selected((int)$yearSel===$y)>{{ $y }}</option>
            @endfor
          </select>

          <select name="month" class="p360-ctl">
            <option value="all" @selected($monthSel==='all')>Mes (Todos)</option>
            @foreach($months as $mm=>$name)
              <option value="{{ $mm }}" @selected($monthSel===$mm)>{{ $mm }} · {{ $name }}</option>
            @endforeach
          </select>

          <select name="origin" class="p360-ctl">
            <option value="all" @selected(($f['origin'] ?? 'all')==='all')>Origen (Todos)</option>
            <option value="recurrente" @selected(($f['origin'] ?? 'all')==='recurrente')>Recurrente</option>
            <option value="unico" @selected(($f['origin'] ?? 'all')==='unico')>Único</option>
          </select>

          <select name="vendor_id" class="p360-ctl">
            <option value="all" @selected($vendorSel==='all')>Vendedor (Todos)</option>
            @foreach($vendorList as $vv)
              @php $vid=(string)data_get($vv,'id',''); $vnm=(string)data_get($vv,'name',''); @endphp
              @if($vid !== '')
                <option value="{{ $vid }}" @selected($vendorSel===$vid)>{{ $vnm }}</option>
              @endif
            @endforeach
          </select>

          <select name="status" class="p360-ctl">
            <option value="all" @selected(($f['st'] ?? 'all')==='all')Estatus E.Cta (Todos)</option>
            <option value="pending" @selected(($f['st'] ?? 'all')==='pending')>Pending</option>
            <option value="emitido" @selected(($f['st'] ?? 'all')==='emitido')>Emitido</option>
            <option value="pagado" @selected(($f['st'] ?? 'all')==='pagado')>Pagado</option>
            <option value="vencido" @selected(($f['st'] ?? 'all')==='vencido')>Vencido</option>
          </select>

          <select name="invoice_status" class="p360-ctl">
            <option value="all" @selected(($f['invSt'] ?? 'all')==='all')Estatus Factura (Todos)</option>
            <option value="pending" @selected(($f['invSt'] ?? 'all')==='pending')>Pending</option>
            <option value="requested" @selected(($f['invSt'] ?? 'all')==='requested')>Solicitada</option>
            <option value="ready" @selected(($f['invSt'] ?? 'all')==='ready')>En proceso</option>
            <option value="issued" @selected(($f['invSt'] ?? 'all')==='issued')>Facturada</option>
            <option value="cancelled" @selected(($f['invSt'] ?? 'all')==='cancelled')>Cancelada</option>
          </select>

          <input name="q" value="{{ $f['qSearch'] ?? '' }}" placeholder="Buscar (cliente, RFC, UUID, cuenta, vendedor)..." class="p360-ctl"/>

          <button type="submit" class="p360-btn p360-btn-primary">Filtrar</button>
          <a href="{{ $routeBase }}" class="p360-btn p360-btn-ghost">Limpiar</a>
        </form>
      </div>

      {{-- ✅ TOP SUMMARY BAR (nuevo diseño) --}}
      <div class="p360-sumbar">

        {{-- LEFT: Totales --}}
        <div class="p360-sum-left">
          <div class="p360-sum-title">
            <span class="lbl">Resumen</span>
            <span class="meta">Año: <b>{{ $yearSel }}</b> · Mes: <b>{{ $monthSel === 'all' ? 'Todos' : ($months[$monthSel] ?? $monthSel) }}</b></span>
          </div>

          <div class="p360-sum-chips">
            <div class="p360-chip">
              <div class="k">Subtotal</div>
              <div class="v">{{ $money($sumSub) }}</div>
            </div>

            <div class="p360-chip">
              <div class="k">IVA</div>
              <div class="v">{{ $money($sumIva) }}</div>
            </div>

            <div class="p360-chip p360-chip-strong">
              <div class="k">Total</div>
              <div class="v">{{ $money($sumTot) }}</div>
            </div>

            <div class="p360-chip">
              <div class="k">Filas</div>
              <div class="v">{{ (int) $rows->count() }}</div>
            </div>
          </div>

          <div class="p360-sum-tip">
            Tip: “Proyección” usa baseline (items/total_cargo/pagos/plan). “Venta” viene de
            <code class="p360-code">finance_sales</code>. Overrides en
            <code class="p360-code">finance_income_overrides</code>.
          </div>
        </div>

        {{-- RIGHT: Estatus de pago --}}
        <div class="p360-sum-right">
          <div class="p360-pay-head">
            <div class="ttl">Estatus de Pago</div>
            <div class="sub">Acumulado según filtros</div>
          </div>

          <div class="p360-pay-grid">
            <div class="p360-paystat">
              <div class="k">Pagadas</div>
              <div class="v">{{ $money($amtPagado) }}</div>
            </div>

            <div class="p360-paystat">
              <div class="k">Por pagar</div>
              <div class="v">{{ $money($amtPorPagar) }}</div>
            </div>

            <div class="p360-paystat">
              <div class="k">Pending</div>
              <div class="v">{{ $money($amtPending) }}</div>
            </div>

            <div class="p360-paystat">
              <div class="k">Emitida</div>
              <div class="v">{{ $money($amtEmitido) }}</div>
            </div>

            <div class="p360-paystat">
              <div class="k">Pagada</div>
              <div class="v">{{ $money($amtPagado) }}</div>
            </div>

            <div class="p360-paystat">
              <div class="k">Pending</div>
              <div class="v">{{ $money($amtPending) }}</div>
            </div>
          </div>
        </div>

      </div>

    </div>

    <div class="p360-card p360-table-card">
      <div class="p360-table-wrap p360-table-scroll" role="region" aria-label="Tabla de ingresos (scroll horizontal)">
          <table class="p360-table p360-table-excel p360-table-wide">
          <thead>
            <tr>
              <th class="p360-th">Año</th>
              <th class="p360-th">Mes</th>
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
              <th class="p360-th">Ver</th>
            </tr>
          </thead>

          <tbody>
            @forelse($rows as $r)
              @php
                $src = (string) ($r->source ?? '');
                $tipo = $src === 'projection' ? 'Proyección' : ($src === 'sale' ? 'Venta' : 'Statement');
                $tipoTone = $src === 'projection' ? 'info' : ($src === 'sale' ? 'warn' : 'muted');

                $client = (string) ($r->client ?? $r->company ?? '—');
                $desc   = (string) ($r->description ?? '—');
                $vendor = (string) ($r->vendor ?? '—');
                $period = (string) ($r->period ?? '—');

                $y = $periodToYear($period);
                $m = $periodToMonth($period);
                $mName = $monthName($m);

                $origin = strtolower((string)($r->origin ?? ''));
                if ($origin === 'no_recurrente') $origin = 'unico';
                $originTone = $origin === 'recurrente' ? 'ok' : 'warn';

                $perio = strtolower((string)($r->periodicity ?? ''));
                $perioTone = $perio === 'anual' ? 'dark' : ($perio === 'mensual' ? 'info' : 'muted');

                $saleId = (int) ($r->sale_id ?? 0);

                $rowPayload = [
                  'source' => $src,
                  'tipo' => $tipo,
                  'period' => (string)($r->period ?? ''),
                  'account_id' => (string)($r->account_id ?? ''),
                  'client' => (string)($client ?? ''),
                  'rfc_emisor' => (string)($r->rfc_emisor ?? ''),
                  'origin' => (string)($origin ?? ''),
                  'periodicity' => (string)($r->periodicity ?? ''),
                  'vendor' => (string)($r->vendor ?? ''),
                  'vendor_id' => (string)($r->vendor_id ?? ''),
                  'description' => (string)($r->description ?? ''),
                  'subtotal' => (float)($r->subtotal ?? 0),
                  'iva' => (float)($r->iva ?? 0),
                  'total' => (float)($r->total ?? 0),
                  'ec_status' => (string)($r->ec_status ?? ''),
                  'invoice_status' => (string)($r->invoice_status ?? ''),
                  'invoice_status_raw' => (string)($r->invoice_status_raw ?? ''),
                  'rfc_receptor' => (string)($r->rfc_receptor ?? ''),
                  'forma_pago' => (string)($r->forma_pago ?? ''),
                  'f_cta' => (string)($r->f_cta ?? ''),
                  'f_mov' => (string)($r->f_mov ?? ''),
                  'f_factura' => (string)($r->f_factura ?? $r->invoice_date ?? ''),
                  'f_pago' => (string)($r->f_pago ?? $r->paid_at ?? ''),
                  'cfdi_uuid' => (string)($r->cfdi_uuid ?? ''),
                  'sale_id' => $saleId,
                  'include_in_statement' => (int)($r->include_in_statement ?? 0),
                  'statement_period_target' => (string)($r->statement_period_target ?? ''),
                  'notes' => (string)($r->notes ?? ''),
                ];
              @endphp

              <tr>
                <td class="p360-td p360-nowrap">{{ $y }}</td>
                <td class="p360-td p360-nowrap">{{ $mName }}</td>

                <td class="p360-td p360-nowrap">
                  <div class="p360-strong">{{ $vendor }}</div>
                  @if(!empty($r->vendor_id))
                    <div class="p360-small p360-muted">ID: {{ $r->vendor_id }}</div>
                  @endif
                </td>

                <td class="p360-td p360-minw-client">
                  <div class="p360-strong">{{ $client }}</div>
                  <div class="p360-small p360-muted" style="margin-top:2px">
                    Cuenta: <code class="p360-code">{{ $r->account_id ?? '—' }}</code>
                    @if(!empty($r->rfc_emisor)) · RFC: {{ $r->rfc_emisor }} @endif
                  </div>
                  <div class="p360-small" style="margin-top:6px;">
                    {!! $pill($tipo, $tipoTone) !!}
                  </div>
                </td>

                <td class="p360-td p360-minw-desc">
                  <div class="p360-strong">{{ $desc }}</div>
                  <div class="p360-small p360-muted" style="margin-top:4px">SaleID: {{ $saleId ?: '—' }}</div>
                </td>

                <td class="p360-td p360-nowrap">{!! $pill(($origin ?: '—'), $originTone) !!}</td>
                <td class="p360-td p360-nowrap">{!! $pill(($perio ?: '—'), $perioTone) !!}</td>

                <td class="p360-td p360-nowrap p360-strong p360-td-num">{{ $money($r->subtotal ?? 0) }}</td>
                <td class="p360-td p360-nowrap p360-strong p360-td-num">{{ $money($r->iva ?? 0) }}</td>
                <td class="p360-td p360-nowrap p360-strong p360-td-num">{{ $money($r->total ?? 0) }}</td>

                <td class="p360-td p360-nowrap p360-muted">{{ $fmtDate($r->f_cta ?? null) }}</td>
                <td class="p360-td p360-nowrap p360-muted">{{ $fmtDate($r->f_pago ?? ($r->paid_at ?? null)) }}</td>

                <td class="p360-td p360-nowrap">{!! $badgeEc((string)($r->ec_status ?? '')) !!}</td>

                <td class="p360-td p360-nowrap">
                  <button type="button"
                    class="p360-actions-btn"
                    data-income-open="1"
                    data-income='@json($rowPayload)'
                    title="Ver / Editar en emergente"
                  >Ver</button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="14" class="p360-td p360-muted" style="padding:18px">
                  No hay registros con los filtros actuales.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Mobile (se deja, pero sin cambios funcionales) --}}
      <div class="p360-cards">
        @forelse($rows as $r)
          @php
            $src = (string) ($r->source ?? '');
            $tipo = $src === 'projection' ? 'Proyección' : ($src === 'sale' ? 'Venta' : 'Statement');
            $tipoTone = $src === 'projection' ? 'info' : ($src === 'sale' ? 'warn' : 'muted');

            $client = (string) ($r->client ?? $r->company ?? '—');
            $period = (string) ($r->period ?? '—');

            $origin = strtolower((string)($r->origin ?? ''));
            if ($origin === 'no_recurrente') $origin = 'unico';
            $originTone = $origin === 'recurrente' ? 'ok' : 'warn';

            $perio = strtolower((string)($r->periodicity ?? ''));
            $perioTone = $perio === 'anual' ? 'dark' : ($perio === 'mensual' ? 'info' : 'muted');

            $saleId = (int) ($r->sale_id ?? 0);

            $rowPayload = [
              'source' => $src,
              'tipo' => $tipo,
              'period' => (string)($r->period ?? ''),
              'account_id' => (string)($r->account_id ?? ''),
              'client' => (string)($client ?? ''),
              'rfc_emisor' => (string)($r->rfc_emisor ?? ''),
              'origin' => (string)($origin ?? ''),
              'periodicity' => (string)($r->periodicity ?? ''),
              'vendor' => (string)($r->vendor ?? ''),
              'vendor_id' => (string)($r->vendor_id ?? ''),
              'description' => (string)($r->description ?? ''),
              'subtotal' => (float)($r->subtotal ?? 0),
              'iva' => (float)($r->iva ?? 0),
              'total' => (float)($r->total ?? 0),
              'ec_status' => (string)($r->ec_status ?? ''),
              'invoice_status' => (string)($r->invoice_status ?? ''),
              'invoice_status_raw' => (string)($r->invoice_status_raw ?? ''),
              'rfc_receptor' => (string)($r->rfc_receptor ?? ''),
              'forma_pago' => (string)($r->forma_pago ?? ''),
              'notes' => (string)($r->notes ?? ''),
              'f_cta' => (string)($r->f_cta ?? ''),
              'f_mov' => (string)($r->f_mov ?? ''),
              'f_factura' => (string)($r->f_factura ?? $r->invoice_date ?? ''),
              'f_pago' => (string)($r->f_pago ?? $r->paid_at ?? ''),
              'cfdi_uuid' => (string)($r->cfdi_uuid ?? ''),
              'sale_id' => $saleId,
              'include_in_statement' => (int)($r->include_in_statement ?? 0),
              'statement_period_target' => (string)($r->statement_period_target ?? ''),
            ];
          @endphp

          <div class="p360-rowcard">
            <div class="p360-row-top">
              <div>
                <div class="p360-row-client">{{ $client }}</div>
                <div class="p360-row-sub">
                  {{ $period }} · {{ $r->vendor ?? '—' }}
                  @if(!empty($r->rfc_emisor)) · RFC: {{ $r->rfc_emisor }} @endif
                </div>
              </div>
              <button type="button" class="p360-actions-btn" data-income-open="1" data-income='@json($rowPayload)'>Ver</button>
            </div>

            <div class="p360-row-meta">
              {!! $pill($tipo, $tipoTone) !!}
              {!! $pill(($origin ?: '—'), $originTone) !!}
              {!! $pill(($perio ?: '—'), $perioTone) !!}
              {!! $badgeEc((string)($r->ec_status ?? '')) !!}
              {!! $badgeInvoice((string)($r->invoice_status ?? '')) !!}
            </div>

            <div class="p360-amtgrid">
              <div class="p360-amt">
                <div class="lbl">Subtotal</div>
                <div class="val">{{ $money($r->subtotal ?? 0) }}</div>
              </div>
              <div class="p360-amt">
                <div class="lbl">IVA</div>
                <div class="val">{{ $money($r->iva ?? 0) }}</div>
              </div>
              <div class="p360-amt">
                <div class="lbl">Total</div>
                <div class="val">{{ $money($r->total ?? 0) }}</div>
              </div>
            </div>

            <div class="p360-small p360-muted" style="margin-top:10px">
              {{ $r->description ?? '—' }}
              @if(!empty($r->cfdi_uuid))
                <div style="margin-top:6px">UUID: {{ $r->cfdi_uuid }}</div>
              @endif
            </div>
          </div>
        @empty
          <div class="p360-rowcard p360-muted">No hay registros con los filtros actuales.</div>
        @endforelse
      </div>

    </div>

  </div>

  {{-- Modal --}}
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
          {{-- Left: Detail --}}
          <div class="p360-box">
            <h4 class="p360-box-title">Detalle (solo lectura)</h4>
            <div class="p360-grid" id="p360IncomeModalGrid"></div>
          </div>

          {{-- Right: Edit --}}
          <div class="p360-box">
            <h4 class="p360-box-title">Editar (guardar en overrides / sales)</h4>

            <div class="p360-alert" id="p360IncomeAlert"></div>

            {{-- Danger zone (Eliminar) --}}
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

              {{-- Solo ventas --}}
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
                  ⚠️ Falta ruta <code class="p360-code">admin.finance.income.row</code>. En <code class="p360-code">routes/admin.php</code> debe existir:
                  <code class="p360-code">Route::post('finance/income/row', IncomeActionsController::class.'@upsert')->name('finance.income.row');</code>
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

  {{-- Form oculto para toggle include (ruta existente en SalesController) --}}
  @if($hasToggleInclude)
    <form id="p360IncomeToggleIncludeForm" method="POST" style="display:none">
      @csrf
    </form>
  @endif

  {{-- CFG para JS externo --}}
  <script>
    window.P360_FIN_INCOME = {
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
  </script>

  {{-- JS externo --}}
  <script src="{{ asset('assets/admin/js/finance-income.js') }}?v={{ now()->format('YmdHis') }}"></script>
@endsection