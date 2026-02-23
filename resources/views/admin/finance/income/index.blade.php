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

  $canon = fn($s) => strtolower(trim((string)$s));

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

  $months = [
    '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
    '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'
  ];

  $routeBase = route('admin.finance.income.index');
  $yearSel = (int) ($f['year'] ?? now()->year);
  $monthSel = (string) ($f['month'] ?? 'all');

  // Rutas
  $rtSalesIndex  = \Illuminate\Support\Facades\Route::has('admin.finance.sales.index') ? route('admin.finance.sales.index') : null;
  $rtSalesCreate = \Illuminate\Support\Facades\Route::has('admin.finance.sales.create') ? route('admin.finance.sales.create') : null;
  $rtVendors     = \Illuminate\Support\Facades\Route::has('admin.finance.vendors.index') ? route('admin.finance.vendors.index') : null;
  $rtCommissions = \Illuminate\Support\Facades\Route::has('admin.finance.commissions.index') ? route('admin.finance.commissions.index') : null;
  $rtProjections = \Illuminate\Support\Facades\Route::has('admin.finance.projections.index') ? route('admin.finance.projections.index') : null;

  $rtInvoiceReq  = \Illuminate\Support\Facades\Route::has('admin.billing.invoices.requests.index') ? route('admin.billing.invoices.requests.index') : null;
  $rtStatementsHub = \Illuminate\Support\Facades\Route::has('admin.billing.statements_hub.index') ? route('admin.billing.statements_hub.index') : null;

  $rtIncomeUpsert = \Illuminate\Support\Facades\Route::has('admin.finance.income.row') ? route('admin.finance.income.row') : null;
  $hasToggleInclude = \Illuminate\Support\Facades\Route::has('admin.finance.sales.toggleInclude');

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
  <link rel="stylesheet" href="{{ asset('assets/admin/css/finance-income.css') }}">

  <style>
    .p360-income-wrap{ display:flex; flex-direction:column; gap:14px; }
    .p360-card{ background:#fff; border:1px solid rgba(15,23,42,.10); border-radius:16px; box-shadow:0 1px 0 rgba(15,23,42,.04); }
    .p360-card-pad{ padding:16px; }
    .p360-income-head{ display:flex; gap:14px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; }
    .p360-income-title{ margin:0; font-size:18px; font-weight:900; letter-spacing:-.2px; color:#0f172a; }
    .p360-income-sub{ margin:4px 0 0; color:#64748b; font-size:13px; }
    .p360-toolbar{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }
    .p360-btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:12px; padding:10px 12px; font-weight:800; border:1px solid rgba(15,23,42,.10); background:#fff; color:#0f172a; text-decoration:none; cursor:pointer; }
    .p360-btn:hover{ box-shadow:0 1px 0 rgba(15,23,42,.06); }
    .p360-btn-primary{ background:#0f172a; color:#fff; border-color:#0f172a; }
    .p360-btn-danger{ background:#991b1b; color:#fff; border-color:#991b1b; }
    .p360-btn-ghost{ background:transparent; }
    .p360-btn[disabled]{ opacity:.6; cursor:not-allowed; }
    .p360-ctl{ border:1px solid rgba(15,23,42,.12); border-radius:12px; padding:10px 10px; background:#fff; color:#0f172a; font-weight:700; font-size:13px; outline:none; }
    .p360-ctl:focus{ box-shadow:0 0 0 3px rgba(56,189,248,.18); border-color:rgba(56,189,248,.55); }
    .p360-income-filters{ display:grid; grid-template-columns:repeat(12, minmax(0,1fr)); gap:8px; width:100%; max-width:980px; }
    .p360-income-filters .p360-ctl, .p360-income-filters button, .p360-income-filters a{ grid-column:span 2; }
    .p360-income-filters input[name="q"]{ grid-column:span 4; }
    @media(max-width:1100px){
      .p360-income-filters{ grid-template-columns:repeat(6, minmax(0,1fr)); }
      .p360-income-filters .p360-ctl, .p360-income-filters button, .p360-income-filters a{ grid-column:span 3; }
      .p360-income-filters input[name="q"]{ grid-column:span 6; }
    }
    @media(max-width:640px){
      .p360-income-filters{ grid-template-columns:repeat(2, minmax(0,1fr)); }
      .p360-income-filters .p360-ctl, .p360-income-filters button, .p360-income-filters a{ grid-column:span 2; }
      .p360-income-filters input[name="q"]{ grid-column:span 2; }
    }

    .p360-kpis{ display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; margin-top:12px; }
    @media(max-width:1100px){ .p360-kpis{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
    .p360-kpi{ border:1px solid rgba(15,23,42,.10); border-radius:14px; padding:12px; background:#fff; }
    .p360-kpi-top{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .p360-kpi-label{ font-size:12px; color:#64748b; font-weight:800; text-transform:uppercase; letter-spacing:.6px; }
    .p360-kpi-amt{ margin-top:6px; font-size:18px; color:#0f172a; font-weight:950; letter-spacing:-.2px; }
    .p360-kpi-count{ border-radius:999px; padding:6px 10px; font-weight:900; background:#f1f5f9; color:#0f172a; }

    .p360-table-card{ overflow:hidden; }
    .p360-table-wrap{ overflow:auto; border-top:1px solid rgba(15,23,42,.06); }
    .p360-table{ width:100%; border-collapse:separate; border-spacing:0; min-width:1440px; }
    .p360-th{ position:sticky; top:0; z-index:2; background:#ffffff; border-bottom:1px solid rgba(15,23,42,.10); padding:12px 10px; text-align:left; font-size:12px; color:#475569; font-weight:950; text-transform:uppercase; letter-spacing:.55px; white-space:nowrap; }
    .p360-td{ border-bottom:1px solid rgba(15,23,42,.06); padding:12px 10px; vertical-align:top; font-size:13px; color:#0f172a; }
    .p360-nowrap{ white-space:nowrap; }
    .p360-muted{ color:#64748b; }
    .p360-strong{ font-weight:900; }
    .p360-small{ font-size:12px; }
    .p360-minw-client{ min-width:240px; }
    .p360-minw-desc{ min-width:340px; }
    .p360-pill{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:900; white-space:nowrap; }
    .p360-badge{ display:inline-flex; align-items:center; justify-content:center; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:950; letter-spacing:.2px; white-space:nowrap; }
    .p360-actions-btn{ border:1px solid rgba(15,23,42,.12); background:#fff; border-radius:12px; padding:8px 10px; font-weight:900; cursor:pointer; }
    .p360-actions-btn:hover{ box-shadow:0 1px 0 rgba(15,23,42,.06); }
    .p360-foot{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 14px; border-top:1px solid rgba(15,23,42,.06); }
    .p360-cards{ display:none; padding:12px; gap:10px; }
    @media(max-width:980px){
      .p360-table-wrap{ display:none; }
      .p360-cards{ display:grid; }
      .p360-table{ min-width:0; }
    }
    .p360-rowcard{ border:1px solid rgba(15,23,42,.10); background:#fff; border-radius:16px; padding:12px; }
    .p360-row-top{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .p360-row-client{ font-weight:950; color:#0f172a; }
    .p360-row-sub{ margin-top:4px; color:#64748b; font-size:12px; }
    .p360-row-meta{ display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; align-items:center; }
    .p360-amtgrid{ display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:10px; margin-top:10px; }
    .p360-amt{ border:1px solid rgba(15,23,42,.08); border-radius:14px; padding:10px; }
    .p360-amt .lbl{ font-size:12px; color:#64748b; font-weight:800; }
    .p360-amt .val{ margin-top:4px; font-size:14px; font-weight:950; color:#0f172a; }

    /* =========================
       Modales (emergentes)
       ========================= */
    .p360-modal-backdrop{
      position:fixed; inset:0; background:rgba(15,23,42,.55);
      display:none; z-index:9998;
    }
    .p360-modal{
      position:fixed; inset:0; display:none; z-index:9999;
      align-items:center; justify-content:center; padding:16px;
    }
    .p360-modal[aria-hidden="false"]{ display:flex; }
    .p360-modal-backdrop.is-open{ display:block; }
    .p360-modal-panel{
      width:100%; max-width:1040px; background:#fff; border-radius:18px;
      border:1px solid rgba(15,23,42,.12);
      box-shadow:0 20px 60px rgba(0,0,0,.20);
      overflow:hidden;
    }
    .p360-modal-head{
      padding:14px 16px; border-bottom:1px solid rgba(15,23,42,.08);
      display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
    }
    .p360-modal-title{ margin:0; font-size:16px; font-weight:950; color:#0f172a; }
    .p360-modal-sub{ margin:3px 0 0; color:#64748b; font-size:12px; }
    .p360-modal-close{
      border:1px solid rgba(15,23,42,.12); background:#fff; border-radius:12px;
      padding:8px 10px; font-weight:950; cursor:pointer;
    }
    .p360-modal-body{ padding:16px; }
    .p360-split{
      display:grid; grid-template-columns:1fr 420px; gap:12px;
      align-items:start;
    }
    @media(max-width:980px){ .p360-split{ grid-template-columns:1fr; } }
    .p360-box{
      border:1px solid rgba(15,23,42,.10);
      border-radius:16px;
      padding:12px;
      background:#fff;
    }
    .p360-box-title{
      margin:0 0 10px;
      font-size:13px;
      font-weight:950;
      color:#0f172a;
      letter-spacing:.2px;
    }
    .p360-grid{
      display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:10px;
    }
    .p360-field{
      grid-column:span 4;
      border:1px solid rgba(15,23,42,.10); border-radius:14px; padding:10px;
      background:#fff;
    }
    .p360-field .k{ font-size:12px; color:#64748b; font-weight:900; text-transform:uppercase; letter-spacing:.5px; }
    .p360-field .v{ margin-top:6px; font-size:13px; color:#0f172a; font-weight:800; word-break:break-word; }
    @media(max-width:900px){ .p360-field{ grid-column:span 6; } }
    @media(max-width:560px){ .p360-field{ grid-column:span 12; } }

    .p360-form{
      display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:10px;
    }
    .p360-form .fi{ grid-column:span 6; }
    .p360-form .fi-12{ grid-column:span 12; }
    .p360-form label{ display:block; font-size:12px; color:#64748b; font-weight:900; margin:0 0 6px; text-transform:uppercase; letter-spacing:.45px; }
    .p360-form input, .p360-form select, .p360-form textarea{
      width:100%;
      border:1px solid rgba(15,23,42,.12);
      border-radius:12px;
      padding:10px 10px;
      font-weight:800;
      font-size:13px;
      outline:none;
      background:#fff;
      color:#0f172a;
    }
    .p360-form textarea{ min-height:92px; resize:vertical; }
    .p360-form input:focus, .p360-form select:focus, .p360-form textarea:focus{
      box-shadow:0 0 0 3px rgba(56,189,248,.18);
      border-color:rgba(56,189,248,.55);
    }
    .p360-help{ margin-top:6px; font-size:12px; color:#64748b; font-weight:700; }
    .p360-alert{
      border-radius:14px; padding:10px 12px; border:1px solid rgba(15,23,42,.10);
      background:#f8fafc; color:#0f172a; font-weight:800; font-size:13px;
      display:none;
    }
    .p360-alert.ok{ background:#ecfeff; border-color:rgba(6,182,212,.25); }
    .p360-alert.bad{ background:#fff1f2; border-color:rgba(244,63,94,.25); }
    .p360-modal-actions{
      padding:14px 16px; border-top:1px solid rgba(15,23,42,.08);
      display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
    }
    .p360-actions-left, .p360-actions-right{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    code.p360-code{ background:#f1f5f9; padding:2px 6px; border-radius:8px; font-weight:900; }
  </style>

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

      <div class="p360-kpis">
        @php
          $cards = [
            ['Total','total'],
            ['Pending','pending'],
            ['Emitido','emitido'],
            ['Pagado','pagado'],
            ['Vencido','vencido'],
          ];
        @endphp
        @foreach($cards as [$label,$key])
          <div class="p360-kpi">
            <div class="p360-kpi-top">
              <div>
                <div class="p360-kpi-label">{{ $label }}</div>
                <div class="p360-kpi-amt">{{ $money(data_get($k, $key.'.amount', 0)) }}</div>
              </div>
              <div class="p360-kpi-count">{{ (int) data_get($k, $key.'.count', 0) }}</div>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    <div class="p360-card p360-table-card">
      <div class="p360-table-wrap">
        <table class="p360-table">
          <thead>
            <tr>
              <th class="p360-th">Acc</th>
              <th class="p360-th">Fuente</th>
              <th class="p360-th">Periodo</th>
              <th class="p360-th">Cliente</th>
              <th class="p360-th">Origen</th>
              <th class="p360-th">Periodicidad</th>
              <th class="p360-th">Vendedor</th>
              <th class="p360-th">Descripción</th>
              <th class="p360-th">Subtotal</th>
              <th class="p360-th">IVA</th>
              <th class="p360-th">Total</th>
              <th class="p360-th">Estatus E.Cta</th>
              <th class="p360-th">RFC Receptor</th>
              <th class="p360-th">Forma Pago</th>
              <th class="p360-th">F Cta</th>
              <th class="p360-th">F Mov</th>
              <th class="p360-th">F Factura</th>
              <th class="p360-th">F Pago</th>
              <th class="p360-th">Estatus Factura</th>
              <th class="p360-th">UUID</th>
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
                ];
              @endphp

              <tr>
                <td class="p360-td p360-nowrap">
                  <button type="button"
                    class="p360-actions-btn"
                    data-income-open="1"
                    data-income='@json($rowPayload)'
                    title="Ver / Editar en emergente"
                  >Ver</button>
                </td>

                <td class="p360-td p360-nowrap">{!! $pill($tipo, $tipoTone) !!}</td>
                <td class="p360-td p360-nowrap p360-strong">{{ $period }}</td>

                <td class="p360-td p360-minw-client">
                  <div class="p360-strong">{{ $client }}</div>
                  <div class="p360-small p360-muted" style="margin-top:2px">
                    Cuenta: <code class="p360-code">{{ $r->account_id ?? '—' }}</code>
                    @if(!empty($r->rfc_emisor))
                      · RFC: {{ $r->rfc_emisor }}
                    @endif
                  </div>
                </td>

                <td class="p360-td p360-nowrap">{!! $pill(($origin ?: '—'), $originTone) !!}</td>
                <td class="p360-td p360-nowrap">{!! $pill(($perio ?: '—'), $perioTone) !!}</td>

                <td class="p360-td p360-nowrap">
                  <div class="p360-strong">{{ $vendor }}</div>
                  @if(!empty($r->vendor_id))
                    <div class="p360-small p360-muted">ID: {{ $r->vendor_id }}</div>
                  @endif
                </td>

                <td class="p360-td p360-minw-desc">
                  <div class="p360-strong">{{ $desc }}</div>
                  <div class="p360-small p360-muted" style="margin-top:4px">
                    SaleID: {{ $saleId ?: '—' }}
                  </div>
                </td>

                <td class="p360-td p360-nowrap p360-strong">{{ $money($r->subtotal ?? 0) }}</td>
                <td class="p360-td p360-nowrap p360-strong">{{ $money($r->iva ?? 0) }}</td>
                <td class="p360-td p360-nowrap p360-strong">{{ $money($r->total ?? 0) }}</td>

                <td class="p360-td p360-nowrap">{!! $badgeEc((string)($r->ec_status ?? '')) !!}</td>

                <td class="p360-td p360-nowrap p360-muted">{{ $r->rfc_receptor ?: '—' }}</td>
                <td class="p360-td p360-nowrap p360-muted">{{ $r->forma_pago ?: '—' }}</td>
                <td class="p360-td p360-nowrap p360-muted">{{ $fmtDate($r->f_cta ?? null) }}</td>
                <td class="p360-td p360-nowrap p360-muted">{{ $fmtDate($r->f_mov ?? null) }}</td>
                <td class="p360-td p360-nowrap p360-muted">{{ $fmtDate($r->f_factura ?? ($r->invoice_date ?? null)) }}</td>
                <td class="p360-td p360-nowrap p360-muted">{{ $fmtDate($r->f_pago ?? ($r->paid_at ?? null)) }}</td>
                <td class="p360-td p360-nowrap">{!! $badgeInvoice($r->invoice_status ?? null) !!}</td>
                <td class="p360-td p360-nowrap p360-muted">{{ $r->cfdi_uuid ?: '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="20" class="p360-td p360-muted" style="padding:18px">
                  No hay registros con los filtros actuales.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Mobile --}}
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

      <div class="p360-foot">
        <div class="p360-muted p360-strong">
          Filas: <span style="color:#0f172a">{{ $rows->count() }}</span>
        </div>
        <div class="p360-muted p360-small">
          Tip: “Proyección” usa baseline (items/total_cargo/pagos/plan). “Venta” viene de <code class="p360-code">finance_sales</code>. Overrides se guardan en <code class="p360-code">finance_income_overrides</code>.
        </div>
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
                <div>
                  <div style="font-weight:950;">Eliminar registro</div>
                  <div style="margin-top:4px; font-weight:800;">
                    Esto eliminará permanentemente el registro editable (venta / override) asociado a esta fila.
                  </div>
                  <div class="p360-help" style="margin-top:6px;">
                    No afecta a “Statements” históricos (source=statement). Para statements solo puedes hacer overrides.
                  </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
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
                <select name="vendor_id">
                  <option value="">—</option>
                </select>
              </div>

              <div class="fi">
                <label>Estatus E.Cta</label>
                <select name="ec_status">
                  <option value="">—</option>
                </select>
              </div>

              <div class="fi">
                <label>Estatus Factura</label>
                <select name="invoice_status">
                  <option value="">—</option>
                </select>
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
                <button type="submit" class="p360-btn p360-btn-primary" id="p360IncomeSaveBtn"
                  @if(!$rtIncomeUpsert) disabled @endif
                >
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

  <script>
    (function(){
      'use strict';

      const CFG = {
        upsertUrl: @json($rtIncomeUpsert),
        // plantilla: reemplazamos __ID__ por el id real
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

      const backdrop = document.getElementById('p360IncomeModalBackdrop');
      const modal    = document.getElementById('p360IncomeModal');
      const titleEl  = document.getElementById('p360IncomeModalTitle');
      const subEl    = document.getElementById('p360IncomeModalSub');
      const gridEl   = document.getElementById('p360IncomeModalGrid');
      const leftEl   = document.getElementById('p360IncomeModalLeft');

      const alertEl  = document.getElementById('p360IncomeAlert');
      const formEl   = document.getElementById('p360IncomeEditForm');
      const saveBtn  = document.getElementById('p360IncomeSaveBtn');
      const resetBtn = document.getElementById('p360IncomeResetBtn');

      const dangerEl = document.getElementById('p360IncomeDanger');
      const delBtn   = document.getElementById('p360IncomeDeleteBtn');
      const delYes   = document.getElementById('p360IncomeConfirmDeleteBtn');
      const delNo    = document.getElementById('p360IncomeCancelDeleteBtn');

     function canDelete(payload){
        if (!payload || typeof payload !== 'object') return false;
        // statements no se eliminan desde aquí
       if (payload.source === 'statement') return false;

        // ventas: requiere sale_id
        if (payload.source === 'sale') return Number(payload.sale_id || 0) > 0;

        // proyección/override: puede ser por (account_id + period)
        // lo borrará tu controller según su lógica.
        if (payload.source === 'projection') return true;

        // cualquier otro source: por seguridad no
        return false;
      }

      function showDanger(show){
        if (!dangerEl) return;
        dangerEl.style.display = show ? 'block' : 'none';
      }

      function setDeleteVisible(payload){
        if (!delBtn) return;
        const ok = canDelete(payload) && !!CFG.destroyUrlTpl;
        delBtn.style.display = ok ? 'inline-flex' : 'none';
      }

      function buildDestroyUrl(payload){
        if (!CFG.destroyUrlTpl) return null;
        // Si es venta: usamos sale_id como {id}
        if (payload && payload.source === 'sale' && Number(payload.sale_id || 0) > 0) {
          return CFG.destroyUrlTpl.replace('__ID__', String(payload.sale_id));
        }
        // Si es proyección/override: necesitamos algún id.
        // Si tu backend maneja delete por id numérico, aquí no habrá id.
        // Por eso mandaremos delete "virtual" con id=0 y el backend debe resolver por account_id+period
        // (si tu IncomeActionsController lo soporta). Si no lo soporta, te digo cómo ajustarlo.
        // Para no romper, solo habilitamos delete real cuando hay id.
        return null;
      }

      async function doDelete(payload){
        if (!payload) return;
        if (!CFG.destroyUrlTpl) {
          showAlert('bad', 'No existe la ruta admin.finance.income.row.destroy.');
          return;
        }

        const url = buildDestroyUrl(payload);
        if (!url) {
          showAlert('bad', 'Este registro no tiene ID eliminable (solo ventas con sale_id).');
          return;
        }

        delBtn.disabled = true;
        const prev = delBtn.textContent;
        delBtn.textContent = 'Eliminando...';

        try{
          const res = await fetch(url, {
            method: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': CFG.csrf,
              'Accept': 'application/json',
            },
            credentials: 'same-origin',
          });

          let json = null;
          try { json = await res.json(); } catch(e){}

          if (!res.ok || !json || json.ok !== true) {
            const msg = (json && (json.message || json.error))
              ? (json.message || json.error)
              : ('Error HTTP ' + res.status);
            showAlert('bad', msg);
            return;
          }

         showAlert('ok', 'Eliminado OK. Actualizando vista...');
          setTimeout(() => window.location.reload(), 520);

        } catch(err){
          showAlert('bad', 'Error de red/JS al eliminar.');
        } finally {
         delBtn.disabled = false;
         delBtn.textContent = prev;
          showDanger(false);
       }
      }

      // Autocálculo montos (si el usuario toca subtotal/iva/total)
      function attachAutoCalc(){
        if (!formEl) return;
        const inSub = formEl.querySelector('input[name="subtotal"]');
        const inIva = formEl.querySelector('input[name="iva"]');
        const inTot = formEl.querySelector('input[name="total"]');
        if (!inSub || !inIva || !inTot) return;

        const num = (v) => {
         const x = Number(String(v ?? '').replace(/[^0-9.\-]/g,''));
          return isFinite(x) ? x : 0;
        };

        let lock = false;
        const recalcFromSubtotal = () => {
          if (lock) return;
          lock = true;
          const sub = num(inSub.value);
          const iva = Math.round(sub * 0.16 * 100) / 100;
          const tot = Math.round((sub + iva) * 100) / 100;
          inIva.value = iva ? String(iva.toFixed(2)) : '';
          inTot.value = tot ? String(tot.toFixed(2)) : '';
          lock = false;
        };

        const recalcFromTotal = () => {
          if (lock) return;
          lock = true;
          const tot = num(inTot.value);
          const sub = tot > 0 ? (tot / 1.16) : 0;
          const iva = tot > 0 ? (tot - sub) : 0;
          const s2 = Math.round(sub * 100) / 100;
          const i2 = Math.round(iva * 100) / 100;
          inSub.value = s2 ? String(s2.toFixed(2)) : '';
          inIva.value = i2 ? String(i2.toFixed(2)) : '';
          lock = false;
        };

        inSub.addEventListener('input', recalcFromSubtotal);
        inTot.addEventListener('input', recalcFromTotal);
      }

      attachAutoCalc();

      let lastPayload = null;

      const money = (n) => {
        const x = Number(n || 0);
        return '$' + x.toLocaleString('en-US', { minimumFractionDigits:2, maximumFractionDigits:2 });
      };

      const fmtDate = (v) => {
        if (!v) return '—';
        const s = String(v);
        if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
        return s;
      };

      const escapeHtml = (s) => String(s ?? '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");

      function showAlert(kind, msg){
        if (!alertEl) return;
        alertEl.classList.remove('ok','bad');
        alertEl.style.display = 'block';
        alertEl.classList.add(kind === 'ok' ? 'ok' : 'bad');
        alertEl.textContent = msg;
      }

      function hideAlert(){
        if (!alertEl) return;
        alertEl.style.display = 'none';
        alertEl.textContent = '';
        alertEl.classList.remove('ok','bad');
      }

      function buildSelectOptions(sel, options, selected){
        sel.innerHTML = '<option value="">—</option>' + options.map(o => {
          const v = String(o.value ?? o.id ?? '');
          const lbl = String(o.label ?? o.name ?? v);
          const isSel = String(selected ?? '') === v;
          return `<option value="${escapeHtml(v)}" ${isSel ? 'selected' : ''}>${escapeHtml(lbl)}</option>`;
        }).join('');
      }

      function fillEditForm(payload){
        if (!formEl) return;

        // Hidden
        formEl.querySelector('input[name="account_id"]').value = payload.account_id || '';
        formEl.querySelector('input[name="period"]').value = payload.period || '';
        formEl.querySelector('input[name="sale_id"]').value = payload.sale_id ? String(payload.sale_id) : '';
        formEl.querySelector('input[name="is_projection"]').value = (payload.source === 'projection') ? '1' : '0';

        // Selects
        buildSelectOptions(formEl.querySelector('select[name="vendor_id"]'), CFG.vendors.map(v => ({value:v.id, label:v.name})), payload.vendor_id || '');
        buildSelectOptions(formEl.querySelector('select[name="ec_status"]'), CFG.ecOptions, payload.ec_status || '');
        buildSelectOptions(formEl.querySelector('select[name="invoice_status"]'), CFG.invOptions, payload.invoice_status || '');

        // Inputs
        formEl.querySelector('input[name="cfdi_uuid"]').value = payload.cfdi_uuid || '';
        formEl.querySelector('input[name="rfc_receptor"]').value = payload.rfc_receptor || '';
        formEl.querySelector('input[name="forma_pago"]').value = payload.forma_pago || '';
        formEl.querySelector('input[name="subtotal"]').value = (payload.subtotal ?? '') !== '' ? String(payload.subtotal) : '';
        formEl.querySelector('input[name="iva"]').value = (payload.iva ?? '') !== '' ? String(payload.iva) : '';
        formEl.querySelector('input[name="total"]').value = (payload.total ?? '') !== '' ? String(payload.total) : '';
        formEl.querySelector('textarea[name="notes"]').value = payload.notes || '';

        // Sales-only
        const incSel = formEl.querySelector('select[name="include_in_statement"]');
        const sptInp = formEl.querySelector('input[name="statement_period_target"]');

        const isSale = payload.source === 'sale' && Number(payload.sale_id || 0) > 0;
        incSel.disabled = !isSale;
        sptInp.disabled = !isSale;

        if (isSale) {
          incSel.value = (payload.include_in_statement === 0 || payload.include_in_statement === 1) ? String(payload.include_in_statement) : '';
          sptInp.value = payload.statement_period_target || '';
        } else {
          incSel.value = '';
          sptInp.value = '';
        }
      }

      function openModal(payload){
        if (!payload || typeof payload !== 'object') return;

        lastPayload = JSON.parse(JSON.stringify(payload || {}));
        hideAlert();

        const tipo  = payload.tipo || payload.source || 'Detalle';
        const per   = payload.period || '—';
        const cli   = payload.client || '—';

        titleEl.textContent = `${tipo} · ${per}`;
        subEl.textContent   = `${cli} · Cuenta: ${payload.account_id || '—'}`;

        const fields = [
          ['Fuente', payload.source],
          ['Periodo', payload.period],
          ['Cliente', payload.client],
          ['Cuenta', payload.account_id],
          ['RFC Emisor', payload.rfc_emisor],

          ['Origen', payload.origin],
          ['Periodicidad', payload.periodicity],
          ['Vendedor', payload.vendor || '—'],
          ['Descripción', payload.description || '—'],

          ['Subtotal', money(payload.subtotal)],
          ['IVA', money(payload.iva)],
          ['Total', money(payload.total)],
          ['Estatus E.Cta', payload.ec_status || '—'],

          ['RFC Receptor', payload.rfc_receptor || '—'],
          ['Forma de pago', payload.forma_pago || '—'],
          ['F Cta', fmtDate(payload.f_cta)],
          ['F Mov', fmtDate(payload.f_mov)],
          ['F Factura', fmtDate(payload.f_factura)],
          ['F Pago', fmtDate(payload.f_pago)],

          ['Estatus Factura', payload.invoice_status || '—'],
          ['UUID', payload.cfdi_uuid || '—'],

          ['Sale ID', payload.sale_id ? String(payload.sale_id) : '—'],
          ['Incluir en E.Cta', (payload.include_in_statement ? 'Sí' : 'No')],
          ['Periodo target (E.Cta)', payload.statement_period_target || '—'],
        ];

        gridEl.innerHTML = fields.map(([k,v]) => {
          return `
            <div class="p360-field">
              <div class="k">${escapeHtml(k)}</div>
              <div class="v">${escapeHtml(v ?? '—')}</div>
            </div>
          `;
        }).join('');

        // acciones rápidas
        const links = [];
        if (CFG.salesCreate) links.push(`<a class="p360-btn p360-btn-primary" href="${CFG.salesCreate}">+ Crear venta</a>`);
        if (CFG.salesIndex)  links.push(`<a class="p360-btn" href="${CFG.salesIndex}">Ver ventas</a>`);
        if (CFG.invoicesReq) links.push(`<a class="p360-btn" href="${CFG.invoicesReq}">Solicitud de facturas</a>`);
        if (CFG.stHub)       links.push(`<a class="p360-btn" href="${CFG.stHub}">Statements HUB</a>`);

        // toggle include (ruta real del SalesController)
        const isSale = payload.source === 'sale' && Number(payload.sale_id || 0) > 0;
        if (CFG.hasToggleInclude && isSale) {
          const lbl = payload.include_in_statement ? 'Quitar de Estado de Cuenta' : 'Incluir en Estado de Cuenta';
          links.push(`<button type="button" class="p360-btn" id="p360IncomeToggleIncludeBtn">${escapeHtml(lbl)}</button>`);
        }

        leftEl.innerHTML = links.join('');

        const tbtn = document.getElementById('p360IncomeToggleIncludeBtn');
        if (tbtn) {
          tbtn.addEventListener('click', function(){
            const form = document.getElementById('p360IncomeToggleIncludeForm');
            if (!form) return;
            form.setAttribute('action', CFG.toggleBase + '/' + String(payload.sale_id) + '/toggle-include');
            form.submit();
          }, { once:true });
        }

        // llenar formulario edit
        fillEditForm(payload);

        // delete visibility + reset danger UI
        setDeleteVisible(payload);
        showDanger(false);

        // abrir
        backdrop.classList.add('is-open');

        // abrir
        backdrop.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.addEventListener('keydown', onEsc);
      }

      function closeModal(){
        modal.setAttribute('aria-hidden', 'true');
        backdrop.classList.remove('is-open');
        gridEl.innerHTML = '';
        leftEl.innerHTML = '';
        hideAlert();
        showDanger(false);
        if (delBtn) delBtn.style.display = 'none';
        document.removeEventListener('keydown', onEsc);
      }

      function onEsc(e){
        if (e.key === 'Escape') closeModal();
      }

      async function submitUpsert(){
        if (!CFG.upsertUrl) {
          showAlert('bad', 'No está configurada la ruta admin.finance.income.row.');
          return;
        }

        const fd = new FormData(formEl);

        // Limpia campos vacíos para que el controller no escriba null involuntario en sales (pero sí en overrides)
        // En tu controller: sales solo actualiza si viene la key; overrides sí guarda null si viene la key.
        // Aquí mandamos keys solo si el usuario tocó algo o si hay valor: mantenemos simple, enviamos lo que hay.
        // (Si luego quieres “no tocar montos”, podemos agregar checkboxes "editar montos".)

        saveBtn.disabled = true;
        const prevText = saveBtn.textContent;
        saveBtn.textContent = 'Guardando...';
        hideAlert();

        try{
          const res = await fetch(CFG.upsertUrl, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': CFG.csrf,
              'Accept': 'application/json',
            },
            body: fd,
            credentials: 'same-origin',
          });

          let json = null;
          try { json = await res.json(); } catch(e){}

          if (!res.ok || !json || json.ok !== true) {
            const msg = (json && (json.message || json.error)) ? (json.message || json.error) : ('Error HTTP ' + res.status);
            showAlert('bad', msg);
            return;
          }

          const mode = json.mode || 'ok';
          showAlert('ok', 'Guardado OK (' + mode + '). Actualizando vista...');

          // Refresh preservando filtros actuales
          setTimeout(() => {
            window.location.reload();
          }, 520);

        } catch(err){
          showAlert('bad', 'Error de red/JS al guardar.');
        } finally {
          saveBtn.disabled = false;
          saveBtn.textContent = prevText;
        }
      }

      // Delegado: abrir / cerrar
      document.addEventListener('click', function(e){
        const btn = e.target.closest('[data-income-open="1"]');
        if (btn) {
          e.preventDefault();
          const raw = btn.getAttribute('data-income');
          if (!raw) return;
          try {
            const payload = JSON.parse(raw);
            openModal(payload);
          } catch(err){}
          return;
        }

        if (e.target.closest('[data-income-close="1"]')) {
          e.preventDefault();
          closeModal();
          return;
        }

        if (e.target === backdrop) closeModal();
      });

      // Submit edit
      if (formEl) {
        formEl.addEventListener('submit', function(e){
          e.preventDefault();
          submitUpsert();
        });
      }

      // Reset UI
      if (resetBtn) {
        resetBtn.addEventListener('click', function(){
          if (!lastPayload) return;
          hideAlert();
          fillEditForm(lastPayload);
          showAlert('ok', 'Campos revertidos (UI). No se guardó nada.');
          setTimeout(hideAlert, 900);
        });
      }

        // llenar formulario edit
        fillEditForm(payload);

        // delete visibility + reset danger UI
        setDeleteVisible(payload);
        showDanger(false);

        // abrir
        backdrop.classList.add('is-open');

    })();
  </script>
@endsection