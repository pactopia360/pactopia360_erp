{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\finance\income\index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Finanzas · Ingresos')

@php
    $f          = $filters ?? [];
    $k          = $kpis ?? [];
    $charts     = $charts ?? [];
    $highlights = $highlights ?? [];
    $rows       = $rows ?? collect();
    $meta       = $meta ?? [];

    $yearSel  = (int) data_get($f, 'year', now()->year);
    $monthSel = (string) data_get($f, 'month', 'all');

    $sourceSel = (string) data_get($f, 'source', 'all');
    if (!in_array($sourceSel, ['all', 'sales', 'statements'], true)) {
        $sourceSel = 'all';
    }

    $isStatementsOnly = $sourceSel === 'statements';
    $isSalesOnly      = $sourceSel === 'sales';
    $isAllSources     = $sourceSel === 'all';

    $originSel = (string) data_get($f, 'origin', 'all');
    if ($originSel === 'no_recurrente') $originSel = 'unico';

    $stSel     = (string) data_get($f, 'status', data_get($f, 'st', 'all'));
    $invSel    = (string) data_get($f, 'invoice_status', data_get($f, 'invSt', 'all'));
    $vendorSel = (string) data_get($f, 'vendor_id', data_get($f, 'vendorId', 'all'));
    $qSearch   = (string) data_get($f, 'q', data_get($f, 'qSearch', ''));

    $vendorList = collect(data_get($f, 'vendor_list', []));

    $money = function ($n) {
        return '$' . number_format((float) ($n ?? 0), 2);
    };

    $fmtDate = function ($d) {
        if (empty($d)) return '—';
        try {
            return \Illuminate\Support\Carbon::parse($d)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '—';
        }
    };

    $months = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo',  '06' => 'Junio',   '07' => 'Julio',  '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
    ];

    $monthName = function (string $mm) use ($months) {
        return $months[$mm] ?? $mm;
    };

    $periodToYear = function ($period) {
        $p = (string) ($period ?? '');
        return preg_match('/^\d{4}\-\d{2}$/', $p) ? (int) substr($p, 0, 4) : (int) now()->format('Y');
    };

    $periodToMonth = function ($period) {
        $p = (string) ($period ?? '');
        return preg_match('/^\d{4}\-\d{2}$/', $p) ? substr($p, 5, 2) : (string) now()->format('m');
    };

    $selectedLabel = $monthSel === 'all'
        ? 'Año ' . $yearSel
        : (($months[$monthSel] ?? $monthSel) . ' ' . $yearSel);

    $routeBase = route('admin.finance.income.index');

    $pill = function (string $label, string $tone = 'muted') {
        $tone = strtolower($tone);
        $map = [
            'muted'  => ['#f1f5f9', '#0f172a'],
            'info'   => ['#e0f2fe', '#075985'],
            'ok'     => ['#dcfce7', '#166534'],
            'warn'   => ['#fff7ed', '#9a3412'],
            'bad'    => ['#fee2e2', '#991b1b'],
            'dark'   => ['#0f172a', '#ffffff'],
            'violet' => ['#ede9fe', '#5b21b6'],
        ];
        $v = $map[$tone] ?? $map['muted'];
        return '<span class="p360-pill" style="background:' . $v[0] . ';color:' . $v[1] . '">' . e($label) . '</span>';
    };

    $badgeEc = function (?string $sRaw) {
        $s = strtolower(trim((string) ($sRaw ?? '')));
        $map = [
            'pagado'  => ['#dcfce7','#166534','Pagado'],
            'emitido' => ['#e0f2fe','#075985','Emitido'],
            'pending' => ['#fff7ed','#9a3412','Pending'],
            'parcial' => ['#fff7ed','#9a3412','Parcial'],
            'vencido' => ['#fee2e2','#991b1b','Vencido'],
            'sin_mov' => ['#f1f5f9','#334155','Sin mov.'],
        ];
        $v = $map[$s] ?? ['#f1f5f9','#334155', strtoupper($s ?: '—')];
        return '<span class="p360-badge" style="background:' . $v[0] . ';color:' . $v[1] . '">' . $v[2] . '</span>';
    };

    $badgeInvoice = function (?string $sRaw) {
        $s = strtolower(trim((string) ($sRaw ?? '')));
        $map = [
            'issued'         => ['#dcfce7','#166534','Facturada'],
            'ready'          => ['#e0f2fe','#075985','En proceso'],
            'requested'      => ['#fff7ed','#9a3412','Solicitada'],
            'cancelled'      => ['#fee2e2','#991b1b','Cancelada'],
            'sin_solicitud'  => ['#f1f5f9','#334155','Sin solicitud'],
            ''               => ['#f1f5f9','#334155','—'],
        ];
        $v = $map[$s] ?? ['#f1f5f9','#334155', strtoupper($s ?: '—')];
        return '<span class="p360-badge" style="background:' . $v[0] . ';color:' . $v[1] . '">' . $v[2] . '</span>';
    };

    $rtSalesIndex  = \Illuminate\Support\Facades\Route::has('admin.finance.sales.index') ? route('admin.finance.sales.index') : null;
    $rtSalesCreate = \Illuminate\Support\Facades\Route::has('admin.finance.sales.create') ? route('admin.finance.sales.create') : null;
    $rtVendors     = \Illuminate\Support\Facades\Route::has('admin.finance.vendors.index') ? route('admin.finance.vendors.index') : null;
    $rtCommissions = \Illuminate\Support\Facades\Route::has('admin.finance.commissions.index') ? route('admin.finance.commissions.index') : null;
    $rtProjections = \Illuminate\Support\Facades\Route::has('admin.finance.projections.index') ? route('admin.finance.projections.index') : null;
    $rtInvoiceReq  = \Illuminate\Support\Facades\Route::has('admin.billing.invoices.requests.index') ? route('admin.billing.invoices.requests.index') : null;
    $rtStatementsHub = \Illuminate\Support\Facades\Route::has('admin.billing.statements_hub.index') ? route('admin.billing.statements_hub.index') : null;

    $rtIncomeUpsert = \Illuminate\Support\Facades\Route::has('admin.finance.income.row')
        ? route('admin.finance.income.row')
        : null;

    $hasToggleInclude = \Illuminate\Support\Facades\Route::has('admin.finance.sales.toggleInclude');

    $vendorOptions = $vendorList->map(fn ($vv) => [
        'id'   => (string) data_get($vv, 'id', ''),
        'name' => (string) data_get($vv, 'name', ''),
    ])->values()->all();

    $ecOptions = [
        ['value' => 'pending', 'label' => 'Pending'],
        ['value' => 'emitido', 'label' => 'Emitido'],
        ['value' => 'pagado',  'label' => 'Pagado'],
        ['value' => 'parcial', 'label' => 'Parcial'],
        ['value' => 'vencido', 'label' => 'Vencido'],
        ['value' => 'sin_mov', 'label' => 'Sin mov.'],
    ];

    $invOptions = [
        ['value' => 'requested', 'label' => 'Solicitada'],
        ['value' => 'ready',     'label' => 'En proceso'],
        ['value' => 'issued',    'label' => 'Facturada'],
        ['value' => 'cancelled', 'label' => 'Cancelada'],
        ['value' => 'sin_solicitud', 'label' => 'Sin solicitud'],
    ];

    $totalAmount      = (float) data_get($k, 'total.amount', 0);
    $pagadoAmount     = (float) data_get($k, 'pagado.amount', 0);
    $receivableAmount = (float) data_get($k, 'receivable.amount', 0);
    $projectedAmount  = (float) data_get($k, 'projected.amount', 0);
    $goalAmount       = (float) data_get($k, 'goal.amount', 0);
    $goalProgress     = max(0, min(100, (float) data_get($k, 'goal.progress', 0)));

    $mixRecurrente = (float) data_get($k, 'mix.recurrente', 0);
    $mixUnico      = (float) data_get($k, 'mix.unico', 0);
    $mixBase       = max(0.01, $mixRecurrente + $mixUnico);

    $statementAmount = (float) data_get($k, 'sources.statement', 0);
    $salesAmount     = (float) data_get($k, 'sources.sale', 0);
    $sourceBase      = max(0.01, $statementAmount + $salesAmount);

    $monthlyChart = collect(data_get($charts, 'monthly', []));
    $vendorTop    = collect(data_get($charts, 'vendorTop', []));
    $bestMonth    = data_get($highlights, 'best_month', []);
    $topVendor    = data_get($highlights, 'top_vendor', []);
    $criticalPending = data_get($highlights, 'critical_pending', []);

    $maxMonthly = (float) $monthlyChart->map(fn ($x) => max(
        (float) data_get($x, 'real', 0),
        (float) data_get($x, 'projected', 0),
        (float) data_get($x, 'collected', 0)
    ))->max();
    if ($maxMonthly <= 0) $maxMonthly = 1;

    $maxVendor = (float) $vendorTop->max('value');
    if ($maxVendor <= 0) $maxVendor = 1;

    $clientLabel = function ($r) {
        foreach ([
            data_get($r, 'client'),
            data_get($r, 'company'),
            data_get($r, 'client_name'),
            data_get($r, 'account_name'),
            data_get($r, 'razon_social'),
            data_get($r, 'nombre_comercial'),
            data_get($r, 'name'),
        ] as $v) {
            $v = trim((string) $v);
            if ($v !== '' && $v !== '—' && $v !== '-') return $v;
        }
        $aid = trim((string) data_get($r, 'account_id', ''));
        return $aid !== '' ? 'Cuenta: ' . $aid : '—';
    };

    $rowSource = function ($r) {
        $src = (string) data_get($r, 'source', '');
        if ($src !== '') return $src;
        if (!empty(data_get($r, 'sale_id'))) return 'sale';
        return 'statement';
    };

    $tipoLabel = function (string $src) {
        return match ($src) {
            'projection'          => 'Proyección',
            'sale', 'sale_linked' => 'Venta',
            default               => 'Estado de cuenta',
        };
    };

    $tipoTone = function (string $src) {
        return match ($src) {
            'projection'          => 'violet',
            'sale', 'sale_linked' => 'warn',
            default               => 'muted',
        };
    };

    $rowsCount = (int) $rows->count();
    $realRowsCount = (int) $rows->filter(fn ($r) => (int) data_get($r, 'is_projection', 0) !== 1)->count();
    $projectionRowsCount = (int) $rows->filter(fn ($r) => (int) data_get($r, 'is_projection', 0) === 1)->count();

    $heroText = $isStatementsOnly
        ? 'Resumen exacto del módulo de <strong>Estados de cuenta</strong>. Aquí solo se muestran cargos reales existentes en ese módulo, con su abono y saldo correspondiente.'
        : ($isSalesOnly
            ? 'Resumen exacto del módulo de <strong>Ventas</strong>. Aquí solo se muestran registros existentes en ventas, sin recalcular ni inventar cargos.'
            : 'Vista consolidada de <strong>ventas</strong>, <strong>estados de cuenta</strong> y <strong>proyecciones recurrentes esperadas</strong>. Los importes reales se respetan exactamente desde sus módulos origen y las proyecciones se muestran aparte como expectativa.');

    $kpi1Title = $isStatementsOnly ? 'Total estados de cuenta' : ($isSalesOnly ? 'Total ventas' : 'Ingresos reales');
    $kpi2Title = $isStatementsOnly ? 'Abono registrado' : ($isSalesOnly ? 'Cobrado' : 'Cobrado');
    $kpi3Title = $isStatementsOnly ? 'Saldo pendiente' : ($isSalesOnly ? 'Por cobrar' : 'Por cobrar');
    $kpi4Title = $isStatementsOnly ? 'Fuente activa' : ($isSalesOnly ? 'Fuente activa' : 'Proyectado esperado');
    $kpi4Value = $isAllSources ? $money($projectedAmount) : ($isStatementsOnly ? 'E.Cta' : 'Ventas');
    $kpi4Sub   = $isAllSources
        ? ((int) data_get($k, 'projected.count', 0) . ' proyecciones recurrentes')
        : ($isStatementsOnly ? 'Resumen exacto de estados de cuenta' : 'Resumen exacto de ventas');

    $detailHint = $isStatementsOnly
        ? 'Vista alineada a Estados de cuenta: cargo, abono y saldo.'
        : ($isSalesOnly
            ? 'Vista alineada a Ventas: subtotal, IVA y total.'
            : 'Vista consolidada: reales primero y proyecciones esperadas al final.');

    $sourceExplain = function ($src) {
        return match ($src) {
            'projection' => 'Esperado',
            'sale', 'sale_linked' => 'Venta',
            default => 'Estado de cuenta',
        };
    };
@endphp

@section('content')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/finance-income.css') }}?v={{ @filemtime(public_path('assets/admin/css/finance-income.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/finance-income.vnext.css') }}?v={{ @filemtime(public_path('assets/admin/css/finance-income.vnext.css')) }}">

    <style>
        .fi360-page{
            display:grid;
            gap:18px;
            padding-bottom:18px;
        }

        .fi360-shell{
            display:grid;
            gap:18px;
        }

        .fi360-card{
            background:#fff;
            border:1px solid #e8edf3;
            border-radius:22px;
            box-shadow:0 10px 30px rgba(15,23,42,.05);
            overflow:hidden;
        }

        .fi360-pad{padding:20px}

        .fi360-hero{
            position:relative;
            overflow:hidden;
            background:
                radial-gradient(circle at top right, rgba(59,130,246,.08), transparent 26%),
                radial-gradient(circle at left center, rgba(139,92,246,.08), transparent 24%),
                linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .fi360-hero::after{
            content:"";
            position:absolute;
            inset:0;
            pointer-events:none;
            background:linear-gradient(135deg, rgba(255,255,255,.65), rgba(255,255,255,0));
        }

        .fi360-top{
            position:relative;
            z-index:1;
            display:flex;
            justify-content:space-between;
            gap:16px;
            align-items:flex-start;
            flex-wrap:wrap;
        }

        .fi360-top h1{
            margin:0;
            font-size:30px;
            line-height:1.05;
            font-weight:950;
            color:#0f172a;
            letter-spacing:-.02em;
        }

        .fi360-sub{
            margin-top:8px;
            color:#64748b;
            font-size:14px;
            line-height:1.5;
            max-width:860px;
        }

        .fi360-mini-meta{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            margin-top:14px;
        }

        .fi360-mini-chip{
            display:inline-flex;
            align-items:center;
            gap:8px;
            min-height:34px;
            padding:0 12px;
            border-radius:999px;
            background:#ffffff;
            border:1px solid #e5e7eb;
            color:#334155;
            font-size:12px;
            font-weight:800;
            box-shadow:0 4px 12px rgba(15,23,42,.04);
        }

        .fi360-actions{
            display:flex;
            gap:10px;
            align-items:center;
            flex-wrap:wrap;
        }

        .fi360-filters{
            display:grid;
            gap:14px;
        }

        .fi360-filter-grid{
            display:grid;
            grid-template-columns:150px 170px minmax(260px,1fr) auto;
            gap:12px;
            align-items:end;
        }

        .fi360-adv-grid{
            display:grid;
            grid-template-columns:repeat(5,minmax(150px,1fr));
            gap:12px;
        }

        .fi360-field label{
            display:block;
            font-size:12px;
            font-weight:900;
            color:#475569;
            margin-bottom:6px;
            letter-spacing:.02em;
        }

        .fi360-ctl{
            width:100%;
            min-height:46px;
            border-radius:14px;
            border:1px solid #dbe2ea;
            background:#fff;
            padding:0 14px;
            color:#0f172a;
            outline:none;
            transition:border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .fi360-ctl:focus{
            border-color:#94a3b8;
            box-shadow:0 0 0 4px rgba(148,163,184,.16);
        }

        .fi360-ctl[type="text"]{
            padding-top:0;
            padding-bottom:0;
        }

        .fi360-filter-actions{
            display:flex;
            gap:10px;
            align-items:end;
            flex-wrap:wrap;
        }

        .fi360-kpis{
            display:grid;
            grid-template-columns:repeat(5,minmax(0,1fr));
            gap:14px;
        }

        .fi360-kpi{
            position:relative;
            padding:18px;
            border-radius:20px;
            background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);
            border:1px solid #e8edf3;
            min-height:132px;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
        }

        .fi360-kpi::before{
            content:"";
            position:absolute;
            inset:auto auto 0 0;
            width:100%;
            height:3px;
            background:linear-gradient(90deg,#cbd5e1,#e2e8f0);
        }

        .fi360-kpi .k{
            font-size:11px;
            font-weight:900;
            color:#64748b;
            text-transform:uppercase;
            letter-spacing:.08em;
        }

        .fi360-kpi .v{
            margin-top:10px;
            font-size:30px;
            font-weight:950;
            line-height:1.05;
            color:#0f172a;
            letter-spacing:-.02em;
        }

        .fi360-kpi .s{
            margin-top:8px;
            font-size:12px;
            color:#64748b;
            line-height:1.45;
        }

        .fi360-kpi.is-strong{
            background:linear-gradient(180deg,#0f172a 0%,#1e293b 100%);
            border-color:#0f172a;
        }

        .fi360-kpi.is-strong::before{
            background:linear-gradient(90deg,#60a5fa,#a78bfa);
        }

        .fi360-kpi.is-strong .k,
        .fi360-kpi.is-strong .v,
        .fi360-kpi.is-strong .s{
            color:#fff;
        }

        .fi360-grid-2{
            display:grid;
            grid-template-columns:minmax(0,1.3fr) minmax(320px,.9fr);
            gap:18px;
        }

        .fi360-chart-head{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:12px;
            margin-bottom:16px;
            flex-wrap:wrap;
        }

        .fi360-title{
            font-size:15px;
            font-weight:950;
            color:#0f172a;
            letter-spacing:.01em;
        }

        .fi360-hint{
            font-size:12px;
            color:#64748b;
            line-height:1.45;
        }

        .fi360-bars{
            display:grid;
            gap:12px;
        }

        .fi360-bar-row{
            display:grid;
            grid-template-columns:96px 1fr;
            gap:12px;
            align-items:center;
        }

        .fi360-bar-label{
            font-size:12px;
            font-weight:900;
            color:#334155;
        }

        .fi360-bar-stack{
            display:grid;
            gap:7px;
        }

        .fi360-bar-track{
            height:10px;
            border-radius:999px;
            background:#eef2f7;
            overflow:hidden;
        }

        .fi360-bar-fill{
            height:100%;
            border-radius:999px;
        }

        .fi360-bar-fill.real{background:#0f172a}
        .fi360-bar-fill.projected{background:#8b5cf6}
        .fi360-bar-fill.collected{background:#22c55e}

        .fi360-bar-legend{
            display:flex;
            gap:14px;
            flex-wrap:wrap;
            margin-top:14px;
        }

        .fi360-dot{
            display:inline-block;
            width:10px;
            height:10px;
            border-radius:999px;
            margin-right:6px;
            vertical-align:middle;
        }

        .fi360-split-list{
            display:grid;
            gap:14px;
        }

        .fi360-metric{
            display:grid;
            gap:8px;
        }

        .fi360-metric-top{
            display:flex;
            justify-content:space-between;
            gap:12px;
            align-items:center;
        }

        .fi360-metric-name{
            font-size:13px;
            font-weight:900;
            color:#334155;
        }

        .fi360-progress{
            height:12px;
            background:#eef2f7;
            border-radius:999px;
            overflow:hidden;
        }

        .fi360-progress > span{
            display:block;
            height:100%;
            border-radius:999px;
        }

        .fi360-progress.green > span{background:#22c55e}
        .fi360-progress.slate > span{background:#0f172a}
        .fi360-progress.violet > span{background:#8b5cf6}
        .fi360-progress.amber > span{background:#f59e0b}

        .fi360-highlights{
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:14px;
        }

        .fi360-highlight{
            padding:18px;
            border-radius:20px;
            background:#fff;
            border:1px solid #e8edf3;
            min-height:120px;
        }

        .fi360-highlight .eyebrow{
            font-size:11px;
            font-weight:900;
            color:#64748b;
            text-transform:uppercase;
            letter-spacing:.08em;
        }

        .fi360-highlight .big{
            margin-top:10px;
            font-size:22px;
            font-weight:950;
            color:#0f172a;
            line-height:1.15;
            letter-spacing:-.02em;
        }

        .fi360-highlight .muted{
            margin-top:8px;
            font-size:12px;
            color:#64748b;
            line-height:1.45;
        }

        .fi360-table-card .head{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:12px;
            flex-wrap:wrap;
            padding:18px 20px;
            border-bottom:1px solid #eef2f7;
            background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%);
        }

        .fi360-table-wrap{
            width:100%;
            overflow:auto;
        }

        .fi360-table{
            width:100%;
            min-width:1320px;
            border-collapse:separate;
            border-spacing:0;
        }

        .fi360-table th{
            position:sticky;
            top:0;
            z-index:1;
            background:#f8fafc;
            border-bottom:1px solid #e5e7eb;
            padding:12px;
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:.07em;
            color:#64748b;
            text-align:left;
            white-space:nowrap;
            font-weight:900;
        }

        .fi360-table td{
            padding:14px 12px;
            border-bottom:1px solid #eef2f7;
            vertical-align:top;
            color:#0f172a;
            background:#fff;
        }

        .fi360-table tbody tr:hover td{
            background:#fafcff;
        }

        .fi360-num{
            text-align:right;
            white-space:nowrap;
        }

        .fi360-muted{color:#64748b}

        .fi360-code{
            display:inline-flex;
            align-items:center;
            min-height:24px;
            font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
            font-size:11px;
            background:#f8fafc;
            border:1px solid #e5e7eb;
            border-radius:8px;
            padding:2px 8px;
            color:#334155;
        }

        .fi360-row-main{
            font-weight:900;
            color:#0f172a;
            line-height:1.35;
        }

        .fi360-row-sub{
            font-size:12px;
            color:#64748b;
            margin-top:4px;
            line-height:1.45;
        }

        .fi360-source-strip{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            margin-top:12px;
        }

        .fi360-cards{
            display:none;
            gap:12px;
            padding:14px;
        }

        .fi360-rowcard{
            border:1px solid #e5e7eb;
            border-radius:20px;
            padding:15px;
            background:#fff;
            box-shadow:0 6px 16px rgba(15,23,42,.04);
        }

        .fi360-rowcard-top{
            display:flex;
            justify-content:space-between;
            gap:10px;
            align-items:flex-start;
        }

        .fi360-rowcard-name{
            font-size:15px;
            font-weight:950;
            color:#0f172a;
            line-height:1.25;
        }

        .fi360-rowcard-sub{
            margin-top:4px;
            font-size:12px;
            color:#64748b;
            line-height:1.45;
        }

        .fi360-chip-row{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            margin-top:12px;
        }

        .fi360-amt-grid{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:10px;
            margin-top:14px;
        }

        .fi360-amt-box{
            border:1px solid #eef2f7;
            background:#f8fafc;
            border-radius:14px;
            padding:10px;
        }

        .fi360-amt-box .k{
            font-size:11px;
            font-weight:900;
            color:#64748b;
            text-transform:uppercase;
            letter-spacing:.04em;
        }

        .fi360-amt-box .v{
            margin-top:5px;
            font-size:15px;
            font-weight:950;
            color:#0f172a;
            line-height:1.2;
        }

        .fi360-empty{
            padding:26px;
            text-align:center;
            color:#64748b;
            font-size:13px;
        }

        .fi360-mobile-meta{
            margin-top:12px;
            display:grid;
            gap:8px;
        }

        .fi360-mobile-meta-line{
            font-size:12px;
            color:#475569;
            line-height:1.45;
        }

        @media (max-width: 1380px){
            .fi360-kpis{
                grid-template-columns:repeat(3,minmax(0,1fr));
            }
            .fi360-adv-grid{
                grid-template-columns:repeat(3,minmax(150px,1fr));
            }
        }

        @media (max-width: 1120px){
            .fi360-filter-grid{
                grid-template-columns:repeat(2,minmax(0,1fr));
            }
            .fi360-grid-2,
            .fi360-highlights{
                grid-template-columns:1fr;
            }
        }

        @media (max-width: 900px){
            .fi360-filter-grid,
            .fi360-adv-grid{
                grid-template-columns:1fr;
            }

            .fi360-kpis{
                grid-template-columns:repeat(2,minmax(0,1fr));
            }

            .fi360-table-wrap{
                display:none;
            }

            .fi360-cards{
                display:grid;
            }

            .fi360-top h1{
                font-size:26px;
            }
        }

        @media (max-width: 640px){
            .fi360-pad{
                padding:16px;
            }

            .fi360-kpis{
                grid-template-columns:1fr;
            }

            .fi360-amt-grid{
                grid-template-columns:1fr;
            }

            .fi360-filter-actions{
                width:100%;
            }

            .fi360-filter-actions .p360-btn{
                flex:1 1 auto;
                justify-content:center;
            }

            .fi360-mini-meta{
                display:grid;
                grid-template-columns:1fr;
            }

            .fi360-rowcard-top{
                flex-direction:column;
                align-items:stretch;
            }
        }
    </style>

    <div class="fi360-page">
        <div class="fi360-shell">

            {{-- HERO --}}
            <section class="fi360-card fi360-hero fi360-pad">
                <div class="fi360-top">
                    <div>
                        <h1>Ingresos</h1>
                        <div class="fi360-sub">{!! $heroText !!}</div>

                        <div class="fi360-mini-meta">
                            <span class="fi360-mini-chip">Periodo: {{ $selectedLabel }}</span>

                            <span class="fi360-mini-chip">
                                Fuente:
                                @if($sourceSel === 'sales')
                                    Ventas
                                @elseif($sourceSel === 'statements')
                                    Estados de cuenta
                                @else
                                    Consolidado
                                @endif
                            </span>

                            <span class="fi360-mini-chip">{{ $rowsCount }} registros visibles</span>

                            @if($isStatementsOnly)
                                <span class="fi360-mini-chip">Vista fiel: Cargo / Abono / Saldo</span>
                            @elseif($isSalesOnly)
                                <span class="fi360-mini-chip">Vista fiel: Subtotal / IVA / Total</span>
                            @else
                                <span class="fi360-mini-chip">Reales: {{ $realRowsCount }} · Proyecciones: {{ $projectionRowsCount }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="fi360-actions">
                        @if($rtSalesCreate)
                            <a href="{{ $rtSalesCreate }}" class="p360-btn p360-btn-primary">+ Crear venta</a>
                        @endif

                        <details class="p360-more">
                            <summary class="p360-btn p360-btn-soft" role="button">
                                Más <span class="p360-caret" aria-hidden="true">▾</span>
                            </summary>
                            <div class="p360-more-menu" role="menu">
                                @if($rtSalesIndex)
                                    <a class="p360-more-item" href="{{ $rtSalesIndex }}">Ventas</a>
                                @endif
                                @if($rtVendors)
                                    <a class="p360-more-item" href="{{ $rtVendors }}">Vendedores</a>
                                @endif
                                @if($rtCommissions)
                                    <a class="p360-more-item" href="{{ $rtCommissions }}">Comisiones</a>
                                @endif
                                @if($rtProjections)
                                    <a class="p360-more-item" href="{{ $rtProjections }}">Proyecciones</a>
                                @endif
                                @if($rtStatementsHub || $rtInvoiceReq)
                                    <div class="p360-more-sep"></div>
                                @endif
                                @if($rtStatementsHub)
                                    <a class="p360-more-item" href="{{ $rtStatementsHub }}">Statements</a>
                                @endif
                                @if($rtInvoiceReq)
                                    <a class="p360-more-item" href="{{ $rtInvoiceReq }}">Solicitudes CFDI</a>
                                @endif
                            </div>
                        </details>
                    </div>
                </div>
            </section>

            {{-- FILTROS --}}
            <section class="fi360-card fi360-pad">
                <form method="GET" action="{{ $routeBase }}" class="fi360-filters">
                    <div class="fi360-filter-grid">
                        <div class="fi360-field">
                            <label>Año</label>
                            <select name="year" class="fi360-ctl">
                                @for($y = now()->year - 2; $y <= now()->year + 2; $y++)
                                    <option value="{{ $y }}" @selected($yearSel === $y)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="fi360-field">
                            <label>Mes</label>
                            <select name="month" class="fi360-ctl">
                                <option value="all" @selected($monthSel === 'all')>Todos</option>
                                @foreach($months as $mm => $name)
                                    <option value="{{ $mm }}" @selected($monthSel === $mm)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="fi360-field">
                            <label>Buscar</label>
                            <input
                                class="fi360-ctl"
                                type="text"
                                name="q"
                                value="{{ $qSearch }}"
                                placeholder="Cliente, RFC, UUID, cuenta, vendedor..."
                            >
                        </div>

                        <div class="fi360-filter-actions">
                            <button type="submit" class="p360-btn p360-btn-primary">Filtrar</button>
                            <a href="{{ $routeBase }}" class="p360-btn p360-btn-ghost">Limpiar</a>
                        </div>
                    </div>

                    @php
                        $advOpen = $sourceSel !== 'all'
                            || $originSel !== 'all'
                            || $vendorSel !== 'all'
                            || $stSel !== 'all'
                            || $invSel !== 'all';
                    @endphp

                    <details class="p360-advanced" @if($advOpen) open @endif>
                        <summary>
                            <span>Filtros avanzados</span>
                            <span class="hint">Fuente · Origen · Vendedor · Estatus</span>
                        </summary>

                        <div class="fi360-adv-grid" style="margin-top:12px">
                            <div class="fi360-field">
                                <label>Fuente</label>
                                <select name="source" class="fi360-ctl">
                                    <option value="all" @selected($sourceSel === 'all')>Todos</option>
                                    <option value="sales" @selected($sourceSel === 'sales')>Ventas</option>
                                    <option value="statements" @selected($sourceSel === 'statements')>Estados de cuenta</option>
                                </select>
                            </div>

                            <div class="fi360-field">
                                <label>Origen</label>
                                <select name="origin" class="fi360-ctl">
                                    <option value="all" @selected($originSel === 'all')>Todos</option>
                                    <option value="recurrente" @selected($originSel === 'recurrente')>Recurrente</option>
                                    <option value="unico" @selected($originSel === 'unico')>Único</option>
                                </select>
                            </div>

                            <div class="fi360-field">
                                <label>Vendedor</label>
                                <select name="vendor_id" class="fi360-ctl">
                                    <option value="all" @selected($vendorSel === 'all')>Todos</option>
                                    @foreach($vendorList as $vv)
                                        @php
                                            $vid = (string) data_get($vv, 'id', '');
                                            $vnm = (string) data_get($vv, 'name', '');
                                        @endphp
                                        @if($vid !== '')
                                            <option value="{{ $vid }}" @selected($vendorSel === $vid)>{{ $vnm }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <div class="fi360-field">
                                <label>Estatus E.Cta</label>
                                <select name="status" class="fi360-ctl">
                                    <option value="all" @selected($stSel === 'all')>Todos</option>
                                    <option value="pending" @selected($stSel === 'pending')>Pending</option>
                                    <option value="emitido" @selected($stSel === 'emitido')>Emitido</option>
                                    <option value="pagado" @selected($stSel === 'pagado')>Pagado</option>
                                    <option value="parcial" @selected($stSel === 'parcial')>Parcial</option>
                                    <option value="vencido" @selected($stSel === 'vencido')>Vencido</option>
                                    <option value="sin_mov" @selected($stSel === 'sin_mov')>Sin mov.</option>
                                </select>
                            </div>

                            <div class="fi360-field">
                                <label>Estatus Factura</label>
                                <select name="invoice_status" class="fi360-ctl">
                                    <option value="all" @selected($invSel === 'all')>Todos</option>
                                    <option value="requested" @selected($invSel === 'requested')>Solicitada</option>
                                    <option value="ready" @selected($invSel === 'ready')>En proceso</option>
                                    <option value="issued" @selected($invSel === 'issued')>Facturada</option>
                                    <option value="cancelled" @selected($invSel === 'cancelled')>Cancelada</option>
                                    <option value="sin_solicitud" @selected($invSel === 'sin_solicitud')>Sin solicitud</option>
                                </select>
                            </div>
                        </div>
                    </details>
                </form>
            </section>

            {{-- KPIS --}}
            <section class="fi360-kpis">
                <div class="fi360-kpi is-strong fi360-card">
                    <div class="k">{{ $kpi1Title }}</div>
                    <div class="v">{{ $money($totalAmount) }}</div>
                    <div class="s">{{ $selectedLabel }}</div>
                </div>

                <div class="fi360-kpi fi360-card">
                    <div class="k">{{ $kpi2Title }}</div>
                    <div class="v">{{ $money($pagadoAmount) }}</div>
                    <div class="s">{{ (int) data_get($k, 'pagado.count', 0) }} registros pagados</div>
                </div>

                <div class="fi360-kpi fi360-card">
                    <div class="k">{{ $kpi3Title }}</div>
                    <div class="v">{{ $money($receivableAmount) }}</div>
                    <div class="s">
                        @if($isStatementsOnly)
                            Cargo menos abono registrado
                        @elseif($isSalesOnly)
                            Total pendiente de ventas
                        @else
                            Solo saldo real por cobrar
                        @endif
                    </div>
                </div>

                <div class="fi360-kpi fi360-card">
                    <div class="k">{{ $kpi4Title }}</div>
                    <div class="v">{{ $kpi4Value }}</div>
                    <div class="s">{{ $kpi4Sub }}</div>
                </div>

                <div class="fi360-kpi fi360-card">
                    <div class="k">Cumplimiento</div>
                    <div class="v">{{ number_format($goalProgress, 1) }}%</div>
                    <div class="s">
                        @if($isAllSources)
                            Cobrado real vs real + proyección
                        @else
                            Relación cobrado / total visible
                        @endif
                    </div>
                </div>
            </section>

            {{-- CHARTS --}}
            <section class="fi360-grid-2">
                <div class="fi360-card fi360-pad">
                    <div class="fi360-chart-head">
                        <div>
                            <div class="fi360-title">
                                @if($isStatementsOnly)
                                    Tendencia de estados de cuenta
                                @elseif($isSalesOnly)
                                    Tendencia de ventas
                                @else
                                    Tendencia mensual consolidada
                                @endif
                            </div>
                            <div class="fi360-hint">
                                @if($isStatementsOnly)
                                    Comparativo del total visible vs cobrado registrado en estados de cuenta.
                                @elseif($isSalesOnly)
                                    Comparativo del total visible vs cobrado registrado en ventas.
                                @else
                                    Comparativo de ingreso real, cobrado y proyección recurrente esperada por periodo.
                                @endif
                            </div>
                        </div>
                        <div class="fi360-hint">{{ $selectedLabel }}</div>
                    </div>

                    <div class="fi360-bars">
                        @forelse($monthlyChart as $m)
                            @php
                                $real      = (float) data_get($m, 'real', 0);
                                $projected = (float) data_get($m, 'projected', 0);
                                $collected = (float) data_get($m, 'collected', 0);

                                $realW      = max(0, min(100, ($real / $maxMonthly) * 100));
                                $projectedW = max(0, min(100, ($projected / $maxMonthly) * 100));
                                $collectedW = max(0, min(100, ($collected / $maxMonthly) * 100));
                            @endphp

                            <div class="fi360-bar-row">
                                <div class="fi360-bar-label">{{ data_get($m, 'label', data_get($m, 'period')) }}</div>
                                <div class="fi360-bar-stack">
                                    <div class="fi360-bar-track">
                                        <div class="fi360-bar-fill real" style="width:{{ $realW }}%"></div>
                                    </div>
                                    <div class="fi360-bar-track">
                                        <div class="fi360-bar-fill collected" style="width:{{ $collectedW }}%"></div>
                                    </div>
                                    <div class="fi360-bar-track">
                                        <div class="fi360-bar-fill projected" style="width:{{ $projectedW }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="fi360-empty">No hay datos para construir la tendencia.</div>
                        @endforelse
                    </div>

                    <div class="fi360-bar-legend">
                        <span class="fi360-hint"><span class="fi360-dot" style="background:#0f172a"></span>Real</span>
                        <span class="fi360-hint"><span class="fi360-dot" style="background:#22c55e"></span>Cobrado</span>
                        <span class="fi360-hint"><span class="fi360-dot" style="background:#8b5cf6"></span>Proyectado</span>
                    </div>
                </div>

                <div class="fi360-card fi360-pad">
                    <div class="fi360-chart-head">
                        <div>
                            <div class="fi360-title">Composición</div>
                            <div class="fi360-hint">Distribución por origen, fuente y cumplimiento de objetivo.</div>
                        </div>
                    </div>

                    <div class="fi360-split-list">
                        <div class="fi360-metric">
                            <div class="fi360-metric-top">
                                <div class="fi360-metric-name">Recurrente</div>
                                <div class="fi360-hint">{{ $money($mixRecurrente) }}</div>
                            </div>
                            <div class="fi360-progress green">
                                <span style="width:{{ max(0,min(100,($mixRecurrente / $mixBase)*100)) }}%"></span>
                            </div>
                        </div>

                        <div class="fi360-metric">
                            <div class="fi360-metric-top">
                                <div class="fi360-metric-name">Único</div>
                                <div class="fi360-hint">{{ $money($mixUnico) }}</div>
                            </div>
                            <div class="fi360-progress amber">
                                <span style="width:{{ max(0,min(100,($mixUnico / $mixBase)*100)) }}%"></span>
                            </div>
                        </div>

                        <div class="fi360-metric">
                            <div class="fi360-metric-top">
                                <div class="fi360-metric-name">Estados de cuenta</div>
                                <div class="fi360-hint">{{ $money($statementAmount) }}</div>
                            </div>
                            <div class="fi360-progress slate">
                                <span style="width:{{ max(0,min(100,($statementAmount / $sourceBase)*100)) }}%"></span>
                            </div>
                        </div>

                        <div class="fi360-metric">
                            <div class="fi360-metric-top">
                                <div class="fi360-metric-name">Ventas</div>
                                <div class="fi360-hint">{{ $money($salesAmount) }}</div>
                            </div>
                            <div class="fi360-progress violet">
                                <span style="width:{{ max(0,min(100,($salesAmount / $sourceBase)*100)) }}%"></span>
                            </div>
                        </div>

                        <div class="fi360-metric">
                            <div class="fi360-metric-top">
                                <div class="fi360-metric-name">Cumplimiento</div>
                                <div class="fi360-hint">{{ number_format($goalProgress, 1) }}%</div>
                            </div>
                            <div class="fi360-progress green">
                                <span style="width:{{ $goalProgress }}%"></span>
                            </div>
                        </div>
                    </div>

                    @if($vendorTop->isNotEmpty())
                        <div style="margin-top:18px">
                            <div class="fi360-title" style="font-size:14px;margin-bottom:10px">Top vendedores</div>
                            <div class="fi360-split-list">
                                @foreach($vendorTop as $v)
                                    @php
                                        $vValue = (float) data_get($v, 'value', 0);
                                        $vWidth = max(0, min(100, ($vValue / $maxVendor) * 100));
                                    @endphp
                                    <div class="fi360-metric">
                                        <div class="fi360-metric-top">
                                            <div class="fi360-metric-name">{{ data_get($v, 'label', '—') }}</div>
                                            <div class="fi360-hint">{{ $money($vValue) }}</div>
                                        </div>
                                        <div class="fi360-progress slate">
                                            <span style="width:{{ $vWidth }}%"></span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            {{-- HIGHLIGHTS --}}
            <section class="fi360-highlights">
                <div class="fi360-highlight fi360-card">
                    <div class="eyebrow">Mejor mes</div>
                    <div class="big">{{ data_get($bestMonth, 'label', '—') }}</div>
                    <div class="muted">Total: {{ $money(data_get($bestMonth, 'total', 0)) }}</div>
                </div>

                <div class="fi360-highlight fi360-card">
                    <div class="eyebrow">Top vendedor</div>
                    <div class="big">{{ data_get($topVendor, 'vendor', '—') }}</div>
                    <div class="muted">Total: {{ $money(data_get($topVendor, 'total', 0)) }}</div>
                </div>

                <div class="fi360-highlight fi360-card">
                    <div class="eyebrow">Pendiente crítico</div>
                    <div class="big">{{ data_get($criticalPending, 'client', '—') }}</div>
                    <div class="muted">
                        {{ data_get($criticalPending, 'period', '') !== '' ? data_get($criticalPending, 'period') . ' · ' : '' }}
                        {{ $money(data_get($criticalPending, 'total', 0)) }}
                    </div>
                </div>
            </section>

            {{-- DETALLE --}}
            <section class="fi360-card fi360-table-card">
                <div class="head">
                    <div>
                        <div class="fi360-title">Detalle de ingresos</div>
                        <div class="fi360-hint">{{ $selectedLabel }} · {{ $rowsCount }} registros</div>

                        <div class="fi360-source-strip">
                            {!! $pill(
                                $sourceSel === 'sales'
                                    ? 'Fuente: Ventas'
                                    : ($sourceSel === 'statements' ? 'Fuente: Estados de cuenta' : 'Fuente: Todas'),
                                $sourceSel === 'sales'
                                    ? 'warn'
                                    : ($sourceSel === 'statements' ? 'info' : 'muted')
                            ) !!}
                            {!! $pill('Statements: ' . (int) data_get($meta, 'counts.statements', 0), 'info') !!}
                            {!! $pill('Ventas: ' . (int) data_get($meta, 'counts.sales', 0), 'warn') !!}
                            {!! $pill('Proyecciones: ' . (int) data_get($meta, 'counts.projections', 0), 'violet') !!}
                        </div>
                    </div>

                    <div class="fi360-hint">{{ $detailHint }}</div>
                </div>

                <div class="fi360-table-wrap" role="region" aria-label="Tabla de ingresos">
                    <table class="fi360-table">
                        <thead>
                            <tr>
                                <th>Periodo</th>
                                <th>Tipo</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th>Descripción</th>
                                <th>Origen</th>
                                <th>Periodicidad</th>

                                @if($isStatementsOnly)
                                    <th class="fi360-num">Cargo</th>
                                    <th class="fi360-num">Abono</th>
                                    <th class="fi360-num">Saldo</th>
                                @elseif($isSalesOnly)
                                    <th class="fi360-num">Subtotal</th>
                                    <th class="fi360-num">IVA</th>
                                    <th class="fi360-num">Total</th>
                                @else
                                    <th class="fi360-num">Total</th>
                                    <th class="fi360-num">Cobrado</th>
                                    <th class="fi360-num">Saldo</th>
                                @endif

                                <th>Estatus</th>
                                <th>Factura</th>
                                <th>Pago</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $r)
                                @php
                                    $srcRaw = (string) $rowSource($r);
                                    $srcAction = str_starts_with($srcRaw, 'sale') ? 'sale' : $srcRaw;

                                    $tipo = $tipoLabel($srcRaw);
                                    $tipoToneValue = $tipoTone($srcRaw);

                                    $client = $clientLabel($r);
                                    $vendor = (string) (data_get($r, 'vendor') ?? '—');
                                    $desc   = (string) (data_get($r, 'description') ?? '—');
                                    $period = (string) (data_get($r, 'period') ?? '—');

                                    $origin = strtolower((string) data_get($r, 'origin', ''));
                                    if ($origin === 'no_recurrente') $origin = 'unico';
                                    $originTone = $origin === 'recurrente' ? 'ok' : 'warn';

                                    $perio = strtolower((string) data_get($r, 'periodicity', ''));
                                    $perioTone = $perio === 'anual' ? 'dark' : ($perio === 'mensual' ? 'info' : 'muted');

                                    $ecSt  = (string) data_get($r, 'ec_status', 'pending');
                                    $invSt = (string) data_get($r, 'invoice_status', 'sin_solicitud');

                                    $fPago = data_get($r, 'f_pago') ?: data_get($r, 'paid_at') ?: data_get($r, 'paid_date') ?: null;

                                    $subtotalRow = (float) data_get($r, 'subtotal', 0);
                                    $ivaRow      = (float) data_get($r, 'iva', 0);
                                    $totalRow    = (float) data_get($r, 'total', 0);
                                    $abonoRow    = (float) data_get($r, 'abono', 0);
                                    $saldoRow    = (float) data_get($r, 'saldo', max(0, $totalRow - $abonoRow));
                                    $cargoRow    = (float) data_get($r, 'cargo_raw', $totalRow);
                                    $isProjectionRow = (int) data_get($r, 'is_projection', 0) === 1;

                                    $rowPayload = [
                                        'source' => $srcAction,
                                        'tipo' => $tipo,
                                        'period' => $period,
                                        'account_id' => (string) data_get($r, 'account_id', ''),
                                        'client' => $client,
                                        'rfc_emisor' => (string) data_get($r, 'rfc_emisor', ''),
                                        'origin' => $origin,
                                        'periodicity' => $perio,
                                        'vendor' => $vendor,
                                        'vendor_id' => (string) data_get($r, 'vendor_id', ''),
                                        'description' => $desc,
                                        'subtotal' => $subtotalRow,
                                        'iva' => $ivaRow,
                                        'total' => $totalRow,
                                        'abono' => $abonoRow,
                                        'saldo' => $saldoRow,
                                        'cargo_raw' => $cargoRow,
                                        'ec_status' => $ecSt,
                                        'invoice_status' => $invSt,
                                        'invoice_status_raw' => (string) data_get($r, 'invoice_status_raw', ''),
                                        'rfc_receptor' => (string) data_get($r, 'rfc_receptor', ''),
                                        'forma_pago' => (string) data_get($r, 'forma_pago', ''),
                                        'f_cta' => (string) (data_get($r, 'f_cta') ?? ''),
                                        'f_mov' => (string) (data_get($r, 'f_mov') ?? ''),
                                        'f_factura' => (string) (data_get($r, 'f_factura') ?? ''),
                                        'f_pago' => (string) ($fPago ?? ''),
                                        'cfdi_uuid' => (string) data_get($r, 'cfdi_uuid', ''),
                                        'sale_id' => (int) data_get($r, 'sale_id', 0),
                                        'include_in_statement' => (int) data_get($r, 'include_in_statement', 0),
                                        'statement_period_target' => (string) data_get($r, 'statement_period_target', ''),
                                        'notes' => (string) data_get($r, 'notes', ''),
                                        'is_projection' => $isProjectionRow ? 1 : 0,
                                    ];
                                @endphp

                                <tr>
                                    <td>
                                        <div class="fi360-row-main">{{ $period }}</div>
                                        <div class="fi360-row-sub">{{ $monthName($periodToMonth($period)) }} {{ $periodToYear($period) }}</div>
                                    </td>

                                    <td>
                                        {!! $pill($tipo, $tipoToneValue) !!}
                                        @if($isAllSources)
                                            <div class="fi360-row-sub">{{ $sourceExplain($srcRaw) }}</div>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="fi360-row-main">{{ $client }}</div>
                                        <div class="fi360-row-sub">
                                            Cuenta: <code class="fi360-code">{{ data_get($r, 'account_id') ?: '—' }}</code>
                                            @if((string) data_get($r, 'rfc_emisor', '') !== '')
                                                · RFC: {{ data_get($r, 'rfc_emisor') }}
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        <div class="fi360-row-main">{{ $vendor }}</div>
                                        @if((string) data_get($r, 'vendor_id', '') !== '')
                                            <div class="fi360-row-sub">ID: {{ data_get($r, 'vendor_id') }}</div>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="fi360-row-main">{{ $desc }}</div>
                                        <div class="fi360-row-sub">
                                            @if((int) data_get($r, 'sale_id', 0) > 0)
                                                SaleID: {{ (int) data_get($r, 'sale_id') }}
                                            @elseif((int) data_get($r, 'statement_id', 0) > 0)
                                                StatementID: {{ (int) data_get($r, 'statement_id') }}
                                            @else
                                                —
                                            @endif
                                        </div>
                                    </td>

                                    <td>{!! $pill($origin ?: '—', $originTone) !!}</td>
                                    <td>{!! $pill($perio ?: '—', $perioTone) !!}</td>

                                    @if($isStatementsOnly)
                                        <td class="fi360-num">
                                            <div class="fi360-row-main">{{ $money($cargoRow) }}</div>
                                            <div class="fi360-row-sub">E.Cta</div>
                                        </td>
                                        <td class="fi360-num">
                                            <div class="fi360-row-main">{{ $money($abonoRow) }}</div>
                                            <div class="fi360-row-sub">Aplicado</div>
                                        </td>
                                        <td class="fi360-num">
                                            <div class="fi360-row-main">{{ $money($saldoRow) }}</div>
                                            <div class="fi360-row-sub">Pendiente</div>
                                        </td>
                                    @elseif($isSalesOnly)
                                        <td class="fi360-num">
                                            <div class="fi360-row-main">{{ $money($subtotalRow) }}</div>
                                        </td>
                                        <td class="fi360-num">
                                            <div class="fi360-row-main">{{ $money($ivaRow) }}</div>
                                        </td>
                                        <td class="fi360-num">
                                            <div class="fi360-row-main">{{ $money($totalRow) }}</div>
                                        </td>
                                    @else
                                        <td class="fi360-num">
                                            <div class="fi360-row-main">{{ $money($totalRow) }}</div>
                                            <div class="fi360-row-sub">{{ $isProjectionRow ? 'Esperado' : 'Real' }}</div>
                                        </td>
                                        <td class="fi360-num">
                                            <div class="fi360-row-main">{{ $money($abonoRow) }}</div>
                                            <div class="fi360-row-sub">{{ $isProjectionRow ? 'Sin cobro' : 'Cobrado' }}</div>
                                        </td>
                                        <td class="fi360-num">
                                            <div class="fi360-row-main">{{ $money($saldoRow) }}</div>
                                            <div class="fi360-row-sub">{{ $isProjectionRow ? 'Esperado' : 'Saldo' }}</div>
                                        </td>
                                    @endif

                                    <td>{!! $badgeEc($ecSt) !!}</td>
                                    <td>{!! $badgeInvoice($invSt) !!}</td>
                                    <td>{{ $fmtDate($fPago) }}</td>

                                    <td>
                                        <button
                                            type="button"
                                            class="p360-actions-btn p360-actions-btn-sm"
                                            data-income-open="1"
                                            data-income='@json($rowPayload)'
                                        >Ver</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="16" class="fi360-empty">No hay registros con los filtros actuales.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- MOBILE --}}
                <div class="fi360-cards">
                    @forelse($rows as $r)
                        @php
                            $srcRaw = (string) $rowSource($r);
                            $srcAction = str_starts_with($srcRaw, 'sale') ? 'sale' : $srcRaw;

                            $tipo = $tipoLabel($srcRaw);
                            $tipoToneValue = $tipoTone($srcRaw);

                            $client = $clientLabel($r);
                            $vendor = (string) (data_get($r, 'vendor') ?? '—');
                            $period = (string) (data_get($r, 'period') ?? '—');

                            $origin = strtolower((string) data_get($r, 'origin', ''));
                            if ($origin === 'no_recurrente') $origin = 'unico';
                            $originTone = $origin === 'recurrente' ? 'ok' : 'warn';

                            $perio = strtolower((string) data_get($r, 'periodicity', ''));
                            $perioTone = $perio === 'anual' ? 'dark' : ($perio === 'mensual' ? 'info' : 'muted');

                            $ecSt  = (string) data_get($r, 'ec_status', 'pending');
                            $invSt = (string) data_get($r, 'invoice_status', 'sin_solicitud');

                            $subtotalRow = (float) data_get($r, 'subtotal', 0);
                            $ivaRow      = (float) data_get($r, 'iva', 0);
                            $totalRow    = (float) data_get($r, 'total', 0);
                            $abonoRow    = (float) data_get($r, 'abono', 0);
                            $saldoRow    = (float) data_get($r, 'saldo', max(0, $totalRow - $abonoRow));
                            $cargoRow    = (float) data_get($r, 'cargo_raw', $totalRow);
                            $isProjectionRow = (int) data_get($r, 'is_projection', 0) === 1;

                            $rowPayload = [
                                'source' => $srcAction,
                                'tipo' => $tipo,
                                'period' => $period,
                                'account_id' => (string) data_get($r, 'account_id', ''),
                                'client' => $client,
                                'rfc_emisor' => (string) data_get($r, 'rfc_emisor', ''),
                                'origin' => $origin,
                                'periodicity' => $perio,
                                'vendor' => $vendor,
                                'vendor_id' => (string) data_get($r, 'vendor_id', ''),
                                'description' => (string) data_get($r, 'description', ''),
                                'subtotal' => $subtotalRow,
                                'iva' => $ivaRow,
                                'total' => $totalRow,
                                'abono' => $abonoRow,
                                'saldo' => $saldoRow,
                                'cargo_raw' => $cargoRow,
                                'ec_status' => $ecSt,
                                'invoice_status' => $invSt,
                                'invoice_status_raw' => (string) data_get($r, 'invoice_status_raw', ''),
                                'rfc_receptor' => (string) data_get($r, 'rfc_receptor', ''),
                                'forma_pago' => (string) data_get($r, 'forma_pago', ''),
                                'f_cta' => (string) data_get($r, 'f_cta', ''),
                                'f_mov' => (string) data_get($r, 'f_mov', ''),
                                'f_factura' => (string) data_get($r, 'f_factura', ''),
                                'f_pago' => (string) (data_get($r, 'f_pago') ?? ''),
                                'cfdi_uuid' => (string) data_get($r, 'cfdi_uuid', ''),
                                'sale_id' => (int) data_get($r, 'sale_id', 0),
                                'include_in_statement' => (int) data_get($r, 'include_in_statement', 0),
                                'statement_period_target' => (string) data_get($r, 'statement_period_target', ''),
                                'notes' => (string) data_get($r, 'notes', ''),
                                'is_projection' => $isProjectionRow ? 1 : 0,
                            ];
                        @endphp

                        <div class="fi360-rowcard">
                            <div class="fi360-rowcard-top">
                                <div>
                                    <div class="fi360-rowcard-name">{{ $client }}</div>
                                    <div class="fi360-rowcard-sub">
                                        {{ $period }} · {{ $vendor }}
                                        @if($isAllSources)
                                            · {{ $sourceExplain($srcRaw) }}
                                        @endif
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="p360-actions-btn"
                                    data-income-open="1"
                                    data-income='@json($rowPayload)'
                                >Ver</button>
                            </div>

                            <div class="fi360-chip-row">
                                {!! $pill($tipo, $tipoToneValue) !!}
                                {!! $pill($origin ?: '—', $originTone) !!}
                                {!! $pill($perio ?: '—', $perioTone) !!}
                                {!! $badgeEc($ecSt) !!}
                                {!! $badgeInvoice($invSt) !!}
                            </div>

                            <div class="fi360-amt-grid">
                                @if($isStatementsOnly)
                                    <div class="fi360-amt-box">
                                        <div class="k">Cargo</div>
                                        <div class="v">{{ $money($cargoRow) }}</div>
                                    </div>
                                    <div class="fi360-amt-box">
                                        <div class="k">Abono</div>
                                        <div class="v">{{ $money($abonoRow) }}</div>
                                    </div>
                                    <div class="fi360-amt-box">
                                        <div class="k">Saldo</div>
                                        <div class="v">{{ $money($saldoRow) }}</div>
                                    </div>
                                @elseif($isSalesOnly)
                                    <div class="fi360-amt-box">
                                        <div class="k">Subtotal</div>
                                        <div class="v">{{ $money($subtotalRow) }}</div>
                                    </div>
                                    <div class="fi360-amt-box">
                                        <div class="k">IVA</div>
                                        <div class="v">{{ $money($ivaRow) }}</div>
                                    </div>
                                    <div class="fi360-amt-box">
                                        <div class="k">Total</div>
                                        <div class="v">{{ $money($totalRow) }}</div>
                                    </div>
                                @else
                                    <div class="fi360-amt-box">
                                        <div class="k">{{ $isProjectionRow ? 'Esperado' : 'Total' }}</div>
                                        <div class="v">{{ $money($totalRow) }}</div>
                                    </div>
                                    <div class="fi360-amt-box">
                                        <div class="k">{{ $isProjectionRow ? 'Cobrado' : 'Cobrado' }}</div>
                                        <div class="v">{{ $money($abonoRow) }}</div>
                                    </div>
                                    <div class="fi360-amt-box">
                                        <div class="k">{{ $isProjectionRow ? 'Pendiente esperado' : 'Saldo' }}</div>
                                        <div class="v">{{ $money($saldoRow) }}</div>
                                    </div>
                                @endif
                            </div>

                            <div class="fi360-mobile-meta">
                                <div class="fi360-mobile-meta-line">
                                    <strong>Descripción:</strong> {{ data_get($r, 'description', '—') }}
                                </div>
                                <div class="fi360-mobile-meta-line">
                                    <strong>Cuenta:</strong> {{ data_get($r, 'account_id', '—') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="fi360-rowcard fi360-muted">No hay registros con los filtros actuales.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    {{-- MODAL --}}
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
                        <h4 class="p360-box-title">Detalle</h4>
                        <div class="p360-grid" id="p360IncomeModalGrid"></div>
                    </div>

                    <div class="p360-box">
                        <h4 class="p360-box-title">Editar</h4>

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
                                <input name="cfdi_uuid" placeholder="UUID CFDI (opcional)">
                            </div>

                            <div class="fi">
                                <label>RFC receptor</label>
                                <input name="rfc_receptor" placeholder="RFC receptor (opcional)">
                            </div>

                            <div class="fi">
                                <label>Forma de pago</label>
                                <input name="forma_pago" placeholder="Forma de pago (opcional)">
                            </div>

                            <div class="fi">
                                <label>Subtotal</label>
                                <input name="subtotal" inputmode="decimal" placeholder="0.00">
                            </div>

                            <div class="fi">
                                <label>IVA</label>
                                <input name="iva" inputmode="decimal" placeholder="0.00">
                            </div>

                            <div class="fi">
                                <label>Total</label>
                                <input name="total" inputmode="decimal" placeholder="0.00">
                            </div>

                            <div class="fi">
                                <label>Incluir en Estado de Cuenta</label>
                                <select name="include_in_statement">
                                    <option value="">—</option>
                                    <option value="1">Sí</option>
                                    <option value="0">No</option>
                                </select>
                                <div class="p360-help">Solo aplica a ventas.</div>
                            </div>

                            <div class="fi">
                                <label>Periodo target (E.Cta)</label>
                                <input name="statement_period_target" placeholder="YYYY-MM (opcional)">
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
        (function () {
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
        })();
    </script>

    <script src="{{ asset('assets/admin/js/finance-income.js') }}?v={{ @filemtime(public_path('assets/admin/js/finance-income.js')) }}"></script>
@endsection