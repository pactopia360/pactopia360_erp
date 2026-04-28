{{-- resources/views/cliente/facturacion/index.blade.php --}}
@extends('layouts.cliente')

@section('title', 'Facturación · Pactopia360')
@section('pageClass', 'page-sat page-sat-clean page-facturacion-360')

@push('styles')
@php
    $SAT_CSS_REL = 'assets/client/css/sat/sat-portal-v1.css';
    $SAT_CSS_ABS = public_path($SAT_CSS_REL);
    $SAT_CSS_V   = is_file($SAT_CSS_ABS) ? (string) filemtime($SAT_CSS_ABS) : null;

    $FX360_CSS_REL = 'assets/client/css/pages/facturacion-360.css';
    $FX360_CSS_ABS = public_path($FX360_CSS_REL);
    $FX360_CSS_V   = is_file($FX360_CSS_ABS) ? (string) filemtime($FX360_CSS_ABS) : null;
@endphp

<link rel="stylesheet" href="{{ asset($SAT_CSS_REL) }}{{ $SAT_CSS_V ? ('?v='.$SAT_CSS_V) : '' }}">
<link rel="stylesheet" href="{{ asset($FX360_CSS_REL) }}{{ $FX360_CSS_V ? ('?v='.$FX360_CSS_V) : '' }}">
@endpush

@push('scripts')
@php
    $FX360_JS_REL = 'assets/client/js/pages/facturacion-360.js';
    $FX360_JS_ABS = public_path($FX360_JS_REL);
    $FX360_JS_V   = is_file($FX360_JS_ABS) ? (string) filemtime($FX360_JS_ABS) : null;
@endphp

<script src="{{ asset($FX360_JS_REL) }}{{ $FX360_JS_V ? ('?v='.$FX360_JS_V) : '' }}"></script>
@endpush

@section('content')
@php
    $k = $kpis ?? [
        'total_periodo' => 0,
        'emitidos'      => 0,
        'cancelados'    => 0,
        'delta_total'   => 0,
        'period'        => [],
        'prev_period'   => [],
    ];

    $f = $filters ?? [];
    $q      = $f['q']      ?? request('q');
    $status = $f['status'] ?? request('status');
    $month  = $f['month']  ?? request('month');
    $mes    = $f['mes']    ?? request('mes');
    $anio   = $f['anio']   ?? request('anio');

    $sum      = is_array($summary ?? null) ? $summary : [];
    $features = is_array($accountFeatures ?? null) ? $accountFeatures : [];

    $proPlans = [
        'pro',
        'pro_mensual',
        'pro_anual',
        'premium',
        'premium_mensual',
        'premium_anual',
        'empresa',
        'business',
    ];

    $headerPlanValue = null;

    foreach ([
        'clientePlan',
        'clientePlanKey',
        'clientePlanNombre',
        'currentPlan',
        'currentPlanKey',
        'planActual',
        'planNombre',
        'tipoCuenta',
        'tipo_cuenta',
        'plan'
    ] as $candidateVar) {
        if (isset($$candidateVar) && trim((string) $$candidateVar) !== '') {
            $headerPlanValue = trim((string) $$candidateVar);
            break;
        }
    }

    $sumPlanRaw = strtolower(trim((string) (
        $sum['plan_raw']
        ?? $sum['plan_key']
        ?? $sum['plan_norm']
        ?? $sum['plan']
        ?? $headerPlanValue
        ?? $planKey
        ?? $plan
        ?? 'free'
    )));

    $sumPlanRaw = str_replace([' ', '-'], '_', $sumPlanRaw);
    $sumPlanRaw = preg_replace('/_+/', '_', $sumPlanRaw) ?: 'free';

    $sumIsPro = true;

    $sumPlanNorm = $sumIsPro ? 'pro' : $sumPlanRaw;
    $sumPlan     = $sumIsPro ? 'PRO' : strtoupper($sumPlanNorm ?: 'FREE');
    $sumCycle    = (string) ($sum['cycle'] ?? '');
    $sumTimbres  = (int) ($sum['timbres'] ?? 0);
    $sumEstado   = (string) ($sum['estado'] ?? 'activa');
    $sumBlocked  = (bool) ($sum['blocked'] ?? false);

    $rtCreate = Route::has('cliente.facturacion.create')
    ? route('cliente.facturacion.create')
    : '#';

    $rtEmisores = Route::has('cliente.facturacion.emisores')
        ? route('cliente.facturacion.emisores')
        : null;

    $rtReceptores = Route::has('cliente.facturacion.receptores')
        ? route('cliente.facturacion.receptores')
        : null;

    $rtProductos = Route::has('cliente.facturacion.productos')
        ? route('cliente.facturacion.productos')
        : null;

    $rtNomina = Route::has('cliente.modulos.rh.nomina')
        ? route('cliente.modulos.rh.nomina')
        : null;

    $rtExport = Route::has('cliente.facturacion.export')
        ? route('cliente.facturacion.export', array_filter([
            'q'      => $q,
            'status' => $status,
            'month'  => $month,
            'mes'    => $mes,
            'anio'   => $anio,
        ]))
        : '#';

    $rtIndex = Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#';

    $rtFacturotopiaTest = Route::has('cliente.facturacion.facturotopia.test')
    ? route('cliente.facturacion.facturotopia.test')
    : '#';

    $facturotopiaDefaultEnv = request('facturotopia_env', 'sandbox');
    $facturotopiaDefaultEnv = in_array($facturotopiaDefaultEnv, ['sandbox', 'production'], true)
        ? $facturotopiaDefaultEnv
        : 'sandbox';

    $statusLabelMap = [
        ''          => 'Todos',
        'emitido'   => 'Emitido',
        'cancelado' => 'Cancelado',
        'pendiente' => 'Pendiente',
        'borrador'  => 'Borrador',
    ];

    $status = $status ?? '';
    $month  = $month ?? '';


    $cfdiCollection = collect();

if (isset($cfdis)) {
    if ($cfdis instanceof \Illuminate\Contracts\Pagination\Paginator) {
        $cfdiCollection = collect($cfdis->items());
    } elseif ($cfdis instanceof \Illuminate\Support\Collection) {
        $cfdiCollection = $cfdis;
    } elseif (is_array($cfdis)) {
        $cfdiCollection = collect($cfdis);
    }
}

$emitidosCount = (int) ($k['emitidos_count'] ?? $k['emitidosCount'] ?? 0);
$ingresosCount = (int) ($k['ingresos_count'] ?? $k['ingresosCount'] ?? 0);
$cancelCount   = (int) ($k['cancelados_count'] ?? $k['cancelCount'] ?? 0);

$borradoresCollection = $cfdiCollection
    ->filter(fn ($row) => strtolower((string) ($row->estatus ?? '')) === 'borrador')
    ->values();

$borradoresCount = $borradoresCollection->count();

if ($cfdiCollection->isNotEmpty()) {
    $emitidosCount = $emitidosCount > 0
        ? $emitidosCount
        : $cfdiCollection->filter(fn ($row) => strtolower((string) ($row->estatus ?? '')) === 'emitido')->count();

    $ingresosCount = $ingresosCount > 0
        ? $ingresosCount
        : $cfdiCollection->filter(fn ($row) => strtolower((string) ($row->tipo_comprobante ?? $row->tipo ?? '')) === 'ingreso')->count();

    $cancelCount = $cancelCount > 0
        ? $cancelCount
        : $cfdiCollection->filter(fn ($row) => strtolower((string) ($row->estatus ?? '')) === 'cancelado')->count();
}
@endphp

<div class="sat-clean-shell fx360-shell">
    <div class="sat-clean-container">

        <section class="sat-clean-hero sat-clean-hero--portal sat-clean-hero--portal-simple" aria-label="Facturación 360">
            <div class="sat-clean-hero__content sat-clean-hero__content--portal sat-clean-hero__content--portal-simple">
                <div
                    class="sat-clean-hero__main sat-clean-hero__main--portal-simple"
                >
                    <div
                        aria-hidden="true"
                        style="
                            position:absolute;
                            inset:0;
                            border-radius:inherit;
                            background:
                                radial-gradient(circle at 18% 22%, rgba(255,255,255,.10), transparent 28%),
                                linear-gradient(90deg, rgba(7,20,54,.22) 0%, rgba(7,20,54,.10) 42%, rgba(255,255,255,0) 76%);
                            pointer-events:none;
                            z-index:1;
                        "
                    ></div>

                    <div style="min-width:0; max-width:100%; position:relative; z-index:2; padding:8px 0;">
                        <div
                            style="
                                display:inline-flex;
                                align-items:center;
                                gap:8px;
                                min-height:30px;
                                padding:0 14px;
                                border-radius:999px;
                                background:rgba(255,255,255,.16);
                                border:1px solid rgba(255,255,255,.18);
                                color:#f8fbff;
                                font-size:11px;
                                font-weight:800;
                                letter-spacing:.05em;
                                margin-bottom:16px;
                                backdrop-filter:blur(4px);
                                box-shadow:0 8px 20px rgba(10,24,64,.12);
                            "
                        >
                            <span
                                style="width:10px; height:10px; border-radius:999px; background:#ffffff; box-shadow:0 0 0 6px rgba(255,255,255,.12); display:inline-block;"
                                aria-hidden="true"
                            ></span>
                            <span>CFDI · FACTURACIÓN 360</span>
                        </div>

                        <div style="display:flex; align-items:center; gap:14px; margin:0 0 14px 0;">
                            <span
                                style="
                                    width:58px;
                                    height:58px;
                                    border-radius:18px;
                                    display:inline-flex;
                                    align-items:center;
                                    justify-content:center;
                                    background:rgba(255,255,255,.16);
                                    border:1px solid rgba(255,255,255,.18);
                                    box-shadow:0 14px 28px rgba(10,24,64,.20);
                                    flex:0 0 58px;
                                    color:#ffffff;
                                "
                                aria-hidden="true"
                            >
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                                    <path d="M4 5h16v14H4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                    <path d="M8 9h8M8 13h8M8 17h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>

                            <h1
                                class="sat-clean-hero__title sat-clean-hero__title--portal"
                                style="
                                    margin:0;
                                    color:#ffffff;
                                    font-size:clamp(36px, 3vw, 56px);
                                    line-height:.98;
                                    letter-spacing:-.05em;
                                    font-weight:900;
                                    text-shadow:0 10px 24px rgba(10,24,64,.24);
                                "
                            >
                                Facturación 360
                            </h1>
                        </div>

                        <p
                            class="sat-clean-hero__text sat-clean-hero__text--portal"
                            style="
                                max-width:760px;
                                margin:0 0 18px 0;
                                color:rgba(255,255,255,.96);
                                font-size:15px;
                                line-height:1.58;
                                font-weight:500;
                                text-shadow:0 6px 18px rgba(10,24,64,.18);
                            "
                        >
                            Emite, consulta y escala CFDI dentro de Pactopia360 con una operación visual más limpia.
                            Toda la facturación masiva, plantillas Excel, lotes y CFDI de alto volumen quedan gobernados por plan PRO.
                        </p>

                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            <span style="display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 13px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.18); color:#ffffff; font-size:12px; font-weight:700;">CFDI</span>
                            <span style="display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 13px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.18); color:#ffffff; font-size:12px; font-weight:700;">Nómina</span>
                            <span style="display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 13px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.18); color:#ffffff; font-size:12px; font-weight:700;">REP</span>
                            <span style="display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 13px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.18); color:#ffffff; font-size:12px; font-weight:700;">Carta Porte</span>
                            <span style="display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 13px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.18); color:#ffffff; font-size:12px; font-weight:700;">Excel PRO</span>
                        </div>
                    </div>

                    <div class="fx360-sidewrap">
                        <div class="fx360-sidecard">
                            <div class="fx360-sidegrid">
                                <div class="fx360-sidebox">
                                    <div class="fx360-sidebox__head">
                                        <div class="fx360-sidebox__label">
                                            TIMBRES
                                        </div>

                                        <span class="fx360-sidebox__icon fx360-sidebox__icon--blue" aria-hidden="true">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                                                <path d="M8 7h8M8 12h8M8 17h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                <path d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </div>

                                    <div class="fx360-sidebox__value">
                                        {{ number_format($sumTimbres) }}
                                    </div>

                                    <div class="fx360-sidebox__sub">
                                        Disponibles
                                    </div>
                                </div>

                                <div class="fx360-sidebox">
                                    <div class="fx360-sidebox__head">
                                        <div class="fx360-sidebox__label">
                                            ESTADO
                                        </div>

                                        <span class="fx360-sidebox__icon fx360-sidebox__icon--green" aria-hidden="true">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                                                <path d="M12 3l7 4v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V7l7-4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                <path d="M9.5 12.5l1.7 1.7 3.5-3.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </div>

                                    <div class="fx360-sidebox__value">
                                        {{ strtoupper($sumEstado ?: 'ACTIVA') }}
                                    </div>

                                    <div class="fx360-sidebox__sub">
                                        {{ $sumBlocked ? 'Bloqueada' : 'Operativa' }}
                                    </div>
                                </div>
                            </div>

                            <div class="fx360-sideactions">
                                <a
                                    href="{{ $rtCreate }}"
                                    class="sat-clean-btn sat-clean-btn--primary fx360-sidebtn"
                                    style="box-shadow:0 14px 26px rgba(15,94,255,.22);"
                                    title="Nuevo CFDI"
                                    aria-label="Nuevo CFDI"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                    <span>Nuevo</span>
                                </a>

                                <a
                                    href="{{ $rtExport }}"
                                    class="sat-clean-btn sat-clean-btn--ghost fx360-sidebtn"
                                    style="
                                        background:rgba(255,255,255,.92);
                                        color:#1f4a9c;
                                        border:1px solid rgba(255,255,255,.24);
                                        box-shadow:0 10px 20px rgba(10,24,64,.10);
                                    "
                                    title="Exportar"
                                    aria-label="Exportar"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 4v10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M8.5 10.5 12 14l3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M5 19h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                    <span>Exportar</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if(session('ok'))
            <div class="fx360-flash fx360-flash--ok">{{ session('ok') }}</div>
        @endif

        @if($errors->any())
            <div class="fx360-flash fx360-flash--err">{{ $errors->first() }}</div>
        @endif

        <section class="fx360-topgrid" aria-label="Indicadores facturación">
            <article class="fx360-kpi">
                <div class="fx360-kpi__label">Total periodo</div>
                <div class="fx360-kpi__value">${{ number_format((float) ($k['total_periodo'] ?? 0), 2) }}</div>
                <div class="fx360-kpi__sub">
                    @if(isset($k['period']['from'], $k['period']['to']))
                        {{ \Carbon\Carbon::parse($k['period']['from'])->format('d/m') }} —
                        {{ \Carbon\Carbon::parse($k['period']['to'])->format('d/m/Y') }}
                    @else
                        Periodo actual
                    @endif
                </div>
            </article>

            <article class="fx360-kpi">
                <div class="fx360-kpi__label">Emitidos</div>
                <div class="fx360-kpi__value">${{ number_format((float) ($k['emitidos'] ?? 0), 2) }}</div>
                <div class="fx360-kpi__sub">Timbrados</div>
            </article>

            <article class="fx360-kpi">
                <div class="fx360-kpi__label">Cancelados</div>
                <div class="fx360-kpi__value">${{ number_format((float) ($k['cancelados'] ?? 0), 2) }}</div>
                <div class="fx360-kpi__sub">Periodo</div>
            </article>

            <article class="fx360-kpi">
                @php $delta = (float) ($k['delta_total'] ?? 0); @endphp
                <div class="fx360-kpi__label">Variación</div>
                <div class="fx360-kpi__value" style="color:{{ $delta >= 0 ? '#16a34a' : '#b91c1c' }}">
                    {{ $delta >= 0 ? '▲' : '▼' }} {{ number_format($delta, 2) }}%
                </div>
                <div class="fx360-kpi__sub">Vs periodo previo</div>
            </article>
        </section>

        <section class="sat-clean-accordion" aria-label="Facturotopia PAC">
    <details class="sat-clean-accordion__item">
        <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
            <div class="sat-clean-accordion__bar-left">
                <span class="sat-clean-accordion__bar-title">Facturotopia PAC</span>
                <span class="sat-clean-accordion__bar-text">Selecciona ambiente para prueba y timbrado</span>
            </div>

            <div style="display:flex; align-items:center; gap:10px;">
                <span class="sat-clean-status-badge is-success" id="fx360PacEnvBadge">
                    {{ $facturotopiaDefaultEnv === 'production' ? 'PRODUCCIÓN' : 'PRUEBAS' }}
                </span>

                <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>
                </span>
            </div>
        </summary>

        <div class="sat-clean-accordion__content">
            <div class="fx360-pac-panel" data-fx360-pac>
                <div class="fx360-pac-env" role="group" aria-label="Ambiente Facturotopia">
                    <button
                        type="button"
                        class="fx360-pac-env__btn {{ $facturotopiaDefaultEnv === 'sandbox' ? 'is-active' : '' }}"
                        data-fx360-env="sandbox"
                    >
                        Pruebas
                    </button>

                    <button
                        type="button"
                        class="fx360-pac-env__btn {{ $facturotopiaDefaultEnv === 'production' ? 'is-active' : '' }}"
                        data-fx360-env="production"
                    >
                        Producción
                    </button>
                </div>

                <div class="fx360-pac-copy">
                    <strong id="fx360PacEnvTitle">
                        {{ $facturotopiaDefaultEnv === 'production' ? 'Producción activa' : 'Pruebas activas' }}
                    </strong>
                    <span id="fx360PacEnvText">
                        {{ $facturotopiaDefaultEnv === 'production'
                            ? 'El timbrado usará API key/base URL de producción y descontará timbres reales.'
                            : 'El timbrado usará API key/base URL sandbox y no debe descontar timbres reales.' }}
                    </span>
                </div>

                <button
                    type="button"
                    class="sat-clean-btn sat-clean-btn--primary sat-clean-btn--compact"
                    id="fx360FacturotopiaTest"
                    data-url="{{ $rtFacturotopiaTest }}"
                    data-csrf="{{ csrf_token() }}"
                >
                    Probar conexión
                </button>
            </div>

            <div class="fx360-pac-result" id="fx360FacturotopiaResult" hidden></div>
        </div>
    </details>
</section>

        <section class="sat-clean-accordion" aria-label="Emisión">
            <details class="sat-clean-accordion__item">
                <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
                    <div class="sat-clean-accordion__bar-left">
                        <span class="sat-clean-accordion__bar-title">Emisión</span>
                        <span class="sat-clean-accordion__bar-text">CFDI manual, catálogos y accesos operativos</span>
                    </div>

                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="sat-clean-status-badge {{ $sumIsPro ? 'is-success' : 'is-muted' }}">
                            {{ $sumIsPro ? 'PRO' : 'BASE' }}
                        </span>

                        <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                                <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </div>
                </summary>

                <div class="sat-clean-accordion__content">
                    <div class="fx360-tiles" style="padding-top:12px;">
                        <a href="{{ $rtCreate }}" class="fx360-tile">
                            <div class="fx360-tile__ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 5v14M5 12h14"/>
                                </svg>
                            </div>
                            <div class="fx360-tile__title">Nuevo CFDI</div>
                            <div class="fx360-tile__sub">Alta manual rápida para emisión puntual y control visual del flujo.</div>
                        </a>

                        @if(Route::has('cliente.rfcs.index'))
                            <a href="{{ route('cliente.rfcs.index') }}" class="fx360-tile">
                                <div class="fx360-tile__ico">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16v16H4z"/>
                                        <path d="M8 8h8M8 12h8M8 16h5"/>
                                    </svg>
                                </div>
                                <div class="fx360-tile__title">RFC / Emisores</div>
                                <div class="fx360-tile__sub">Registra, consulta y sincroniza emisores con Facturotopia.</div>
                            </a>
                        @endif

                        @if($rtEmisores)
                            <a href="{{ $rtEmisores }}" class="fx360-tile">
                                <div class="fx360-tile__ico">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 21h18"/>
                                        <path d="M5 21V7l7-4 7 4v14"/>
                                    </svg>
                                </div>
                                <div class="fx360-tile__title">Emisores</div>
                                <div class="fx360-tile__sub">Gestión visual de emisores activos para timbrado.</div>
                            </a>
                        @endif

                        @if($rtReceptores)
                            <a href="{{ $rtReceptores }}" class="fx360-tile">
                                <div class="fx360-tile__ico">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21a8 8 0 0 0-16 0"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                </div>
                                <div class="fx360-tile__title">Receptores</div>
                                <div class="fx360-tile__sub">RFC, razón social y datos fiscales reutilizables.</div>
                            </a>
                        @endif

                        @if($rtProductos)
                            <a href="{{ $rtProductos }}" class="fx360-tile">
                                <div class="fx360-tile__ico">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                    </svg>
                                </div>
                                <div class="fx360-tile__title">Conceptos</div>
                                <div class="fx360-tile__sub">Productos y servicios listos para facturar dentro del flujo CFDI.</div>
                            </a>
                        @endif
                    </div>
                </div>
            </details>
        </section>

        <section class="sat-clean-accordion" aria-label="Borradores">
    <details class="sat-clean-accordion__item">
        <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
            <div class="sat-clean-accordion__bar-left">
                <span class="sat-clean-accordion__bar-title">Borradores</span>
                <span class="sat-clean-accordion__bar-text">CFDI guardados pendientes de revisar, editar o timbrar.</span>
            </div>

            <div style="display:flex; align-items:center; gap:10px;">
                <span class="sat-clean-status-badge is-warning">
                    {{ $borradoresCount }} pendiente{{ $borradoresCount === 1 ? '' : 's' }}
                </span>

                <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>
                </span>
            </div>
        </summary>

        <div class="sat-clean-accordion__content">
            <div class="sat-clean-rfc-table-wrap sat-clean-rfc-table-wrap--minimal fx360-table-card" style="margin-top:12px;">
                <table class="sat-clean-rfc-table sat-clean-rfc-table--minimal">
                    <thead>
                        <tr>
                            <th>UUID temporal</th>
                            <th>Fecha</th>
                            <th>Receptor</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($borradoresCollection as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row->uuid ?: 'Borrador sin UUID' }}</strong>
                                    <div class="sat-clean-rfc-inline-text">
                                        {{ trim(($row->serie ? ($row->serie . '-') : '') . ($row->folio ?? ''), '- ') ?: 'Sin serie / folio' }}
                                    </div>
                                </td>

                                <td>{{ optional($row->fecha)->format('Y-m-d H:i') ?: '—' }}</td>

                                <td>
                                    {{ optional($row->cliente)->razon_social
                                        ?? optional($row->cliente)->nombre_comercial
                                        ?? 'Receptor guardado' }}
                                </td>

                                <td class="text-end">
                                    <strong>${{ number_format((float) ($row->total ?? 0), 2) }}</strong>
                                </td>

                                <td class="text-end">
                                    <div class="sat-clean-icon-actions fx360-actions-icons">
                                        @if(Route::has('cliente.facturacion.show'))
                                            <a class="fx360-action-icon"
                                            href="{{ route('cliente.facturacion.show', $row->id) }}"
                                            data-tip="Ver"
                                            aria-label="Ver borrador">
                                                <svg viewBox="0 0 24 24" fill="none">
                                                    <path d="M2.5 12C4.7 7.8 8 5.7 12 5.7C16 5.7 19.3 7.8 21.5 12C19.3 16.2 16 18.3 12 18.3C8 18.3 4.7 16.2 2.5 12Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                    <path d="M12 15.2C13.7673 15.2 15.2 13.7673 15.2 12C15.2 10.2327 13.7673 8.8 12 8.8C10.2327 8.8 8.8 10.2327 8.8 12C8.8 13.7673 10.2327 15.2 12 15.2Z" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                            </a>
                                        @endif

                                        @if(Route::has('cliente.facturacion.edit'))
                                            <a class="fx360-action-icon"
                                            href="{{ route('cliente.facturacion.edit', $row->id) }}"
                                            data-tip="Editar"
                                            aria-label="Editar borrador">
                                                <svg viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8.6L19.25 9.35C20.05 8.55 20.05 7.25 19.25 6.45L17.55 4.75C16.75 3.95 15.45 3.95 14.65 4.75L4 15.4V20Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                    <path d="M13.5 5.9L18.1 10.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="sat-clean-empty-state sat-clean-empty-state--compact">
                                        <div class="sat-clean-empty-state__title">No hay borradores pendientes</div>
                                        <div class="sat-clean-empty-state__text">Guarda un CFDI como borrador para revisarlo antes de timbrar.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </details>
</section>

        <section class="sat-clean-accordion" aria-label="Emitidos">
            <details class="sat-clean-accordion__item">
                <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
                    <div class="sat-clean-accordion__bar-left">
                        <span class="sat-clean-accordion__bar-title">Emitidos</span>
                        <span class="sat-clean-accordion__bar-text">Consulta, filtros y acciones rápidas sobre CFDI</span>
                    </div>

                    <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </span>
                </summary>

                <div class="sat-clean-accordion__content">
                    <div class="fx360-panel" style="margin-top:12px;">
                        <form method="GET" action="{{ $rtIndex }}">
                            <div class="fx360-filters">
                                <div>
                                    <label class="fx360-label">Buscar</label>
                                    <input class="fx360-input" type="text" name="q" value="{{ $q }}" placeholder="UUID, serie, folio o RFC">
                                </div>

                                <div>
                                    <label class="fx360-label">Estatus</label>
                                    <select class="fx360-input" name="status">
                                        @foreach($statusLabelMap as $statusValue => $statusLabel)
                                            <option value="{{ $statusValue }}" @selected($status === $statusValue)>{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="fx360-label">Mes</label>
                                    <input class="fx360-input" type="month" name="month" value="{{ $month }}">
                                </div>

                                <div class="fx360-inline-actions">
                                    <button type="submit" class="sat-clean-btn sat-clean-btn--primary sat-clean-btn--compact">
                                        Aplicar
                                    </button>

                                    <a href="{{ $rtIndex }}" class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact">
                                        Limpiar
                                    </a>

                                    @if(Route::has('cliente.facturacion.export'))
                                        <a href="{{ $rtExport }}" class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact">
                                            CSV
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="sat-clean-rfc-table-wrap sat-clean-rfc-table-wrap--minimal fx360-table-card">
                        <table class="sat-clean-rfc-table sat-clean-rfc-table--minimal">
                            <thead>
                                <tr>
                                    <th>Serie / Folio</th>
                                    <th>Fecha</th>
                                    <th>Emisor</th>
                                    <th>Estatus</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cfdiCollection->filter(fn ($row) => strtolower((string) ($row->estatus ?? '')) !== 'borrador')->values() as $row)
                                    @php
                                        $serieFolio = trim(($row->serie ? ($row->serie . '-') : '') . ($row->folio ?? ''), '- ') ?: '—';
                                        $st = strtolower((string) ($row->estatus ?? ''));
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fx360-cfdi-id">
                                                <span class="fx360-cfdi-id__serie">
                                                    {{ $serieFolio }}
                                                </span>

                                                <span class="fx360-cfdi-id__uuid {{ $row->uuid ? '' : 'is-empty' }}">
                                                    UUID: {{ $row->uuid ?: 'Pendiente / no timbrado real' }}
                                                </span>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="sat-clean-rfc-inline-text">
                                                {{ optional($row->fecha)->format('Y-m-d H:i') ?: '—' }}
                                            </div>
                                        </td>

                                        <td>
                                            <div class="sat-clean-rfc-inline-text">
                                                {{ optional($row->cliente)->razon_social ?? optional($row->cliente)->nombre_comercial ?? '—' }}
                                            </div>
                                        </td>

                                        <td>
                                            @if(in_array($st, ['emitido', 'timbrado'], true))
                                                <span class="sat-clean-status-badge is-success">Timbrado</span>
                                            @elseif($st === 'cancelado')
                                                <span class="sat-clean-status-badge is-muted">Cancelado</span>
                                            @elseif($st === 'error')
                                                <span class="sat-clean-status-badge is-danger">Error</span>
                                            @else
                                                <span class="sat-clean-status-badge is-warning">{{ strtoupper($st ?: 'N/D') }}</span>
                                            @endif
                                        </td>

                                        <td class="text-end">
                                            ${{ number_format((float) ($row->total ?? 0), 2) }}
                                        </td>

                                        <td class="text-end">
                                            <div class="sat-clean-icon-actions fx360-actions-icons">
                                                @if(Route::has('cliente.facturacion.show'))
                                                    <a class="fx360-action-icon"
                                                    href="{{ route('cliente.facturacion.show', $row->id) }}"
                                                    data-tip="Ver"
                                                    aria-label="Ver borrador">
                                                        <svg viewBox="0 0 24 24" fill="none">
                                                            <path d="M2.5 12C4.7 7.8 8 5.7 12 5.7C16 5.7 19.3 7.8 21.5 12C19.3 16.2 16 18.3 12 18.3C8 18.3 4.7 16.2 2.5 12Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                            <path d="M12 15.2C13.7673 15.2 15.2 13.7673 15.2 12C15.2 10.2327 13.7673 8.8 12 8.8C10.2327 8.8 8.8 10.2327 8.8 12C8.8 13.7673 10.2327 15.2 12 15.2Z" stroke="currentColor" stroke-width="2"/>
                                                        </svg>
                                                    </a>
                                                @endif

                                                @if(in_array($st, ['emitido', 'timbrado'], true))
                                                    @if(Route::has('cliente.facturacion.ver_pdf'))
                                                        <a class="fx360-action-icon fx360-action-icon--pdf"
                                                        href="{{ route('cliente.facturacion.ver_pdf', $row->id) }}"
                                                        target="_blank"
                                                        data-tip="PDF"
                                                        aria-label="Ver PDF">
                                                            PDF
                                                        </a>
                                                    @endif

                                                    @if(Route::has('cliente.facturacion.descargar_xml'))
                                                        <a class="fx360-action-icon fx360-action-icon--xml"
                                                        href="{{ route('cliente.facturacion.descargar_xml', $row->id) }}"
                                                        data-tip="XML"
                                                        aria-label="Descargar XML">
                                                            XML
                                                        </a>
                                                    @endif
                                                @endif

                                                @if(Route::has('cliente.facturacion.edit'))
                                                    <a class="fx360-action-icon"
                                                    href="{{ route('cliente.facturacion.edit', $row->id) }}"
                                                    data-tip="Editar"
                                                    aria-label="Editar borrador">
                                                        <svg viewBox="0 0 24 24" fill="none">
                                                            <path d="M4 20H8.6L19.25 9.35C20.05 8.55 20.05 7.25 19.25 6.45L17.55 4.75C16.75 3.95 15.45 3.95 14.65 4.75L4 15.4V20Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                            <path d="M13.5 5.9L18.1 10.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                        </svg>
                                                    </a>
                                                @endif

                                                @if(Route::has('cliente.facturacion.timbrar'))
                                                    <form method="POST"
                                                        action="{{ route('cliente.facturacion.timbrar', $row->id) }}"
                                                        class="fx360-timbrar-form"
                                                        onsubmit="return confirm('¿Timbrar este CFDI en el ambiente seleccionado?');">
                                                        @csrf
                                                        <input type="hidden" name="facturotopia_env" value="{{ $facturotopiaDefaultEnv }}">
                                                        <button type="submit"
                                                                class="fx360-action-icon fx360-action-icon--success"
                                                                data-tip="Timbrar"
                                                                aria-label="Timbrar CFDI">
                                                            <svg viewBox="0 0 24 24" fill="none">
                                                                <path d="M8 4H16V10L18.5 12.5V20H5.5V12.5L8 10V4Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                                <path d="M8 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                <path d="M8.5 16H15.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif

                                                @if(Route::has('cliente.facturacion.destroy'))
                                                    <form method="POST"
                                                        action="{{ route('cliente.facturacion.destroy', $row->id) }}"
                                                        onsubmit="return confirm('¿Eliminar este borrador? Esta acción no se puede deshacer.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="fx360-action-icon fx360-action-icon--danger"
                                                                data-tip="Eliminar"
                                                                aria-label="Eliminar borrador">
                                                            <svg viewBox="0 0 24 24" fill="none">
                                                                <path d="M4 7H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                <path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                <path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                <path d="M6.5 7L7.3 20H16.7L17.5 7" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                                <path d="M9 7V4H15V7" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <div class="sat-clean-empty-state sat-clean-empty-state--compact">
                                                <div class="sat-clean-empty-state__title">No hay CFDI en el periodo seleccionado</div>
                                                <div class="sat-clean-empty-state__text">Aplica filtros distintos o emite un CFDI nuevo para empezar a operar este módulo.</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div style="padding-top:12px;">
                        {{ $cfdis->onEachSide(1)->links() }}
                    </div>
                </div>
            </details>
        </section>

        <section class="sat-clean-accordion" aria-label="Masivo y plantillas">
            <details class="sat-clean-accordion__item">
                <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
                    <div class="sat-clean-accordion__bar-left">
                        <span class="sat-clean-accordion__bar-title">Masivo y plantillas</span>
                        <span class="sat-clean-accordion__bar-text">Operación por Excel, lotes y reuso de layouts</span>
                    </div>

                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="sat-clean-status-badge {{ $sumIsPro ? 'is-success' : 'is-muted' }}">
                            {{ $sumIsPro ? 'PRO' : 'LOCKED' }}
                        </span>

                        <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                                <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </div>
                </summary>

                <div class="sat-clean-accordion__content">
                    <div class="fx360-note" style="margin-top:12px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8h.01M11 12h1v4h1"/>
                        </svg>
                        <div>
                            En Pactopia360 toda la operación CFDI masiva, importación por Excel, plantillas, lotes y emisión de alto volumen debe quedar habilitada únicamente para cuentas PRO.
                        </div>
                    </div>

                    <div class="fx360-tiles" style="padding-top:12px;">
                        <div class="fx360-tile {{ $sumIsPro ? '' : 'fx360-proveil' }}">
                            @unless($sumIsPro)
                                <span class="fx360-lock" title="Solo PRO">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="5" y="11" width="14" height="10" rx="2"/>
                                        <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
                                    </svg>
                                </span>
                            @endunless
                            <div class="fx360-tile__ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16v16H4z"/>
                                    <path d="M8 8h8M8 12h8M8 16h5"/>
                                </svg>
                            </div>
                            <div class="fx360-tile__title">Importar Excel</div>
                            <div class="fx360-tile__sub">Carga masiva de CFDI desde layout controlado y validado.</div>
                        </div>

                        <div class="fx360-tile {{ $sumIsPro ? '' : 'fx360-proveil' }}">
                            @unless($sumIsPro)
                                <span class="fx360-lock" title="Solo PRO">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="5" y="11" width="14" height="10" rx="2"/>
                                        <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
                                    </svg>
                                </span>
                            @endunless
                            <div class="fx360-tile__ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 3h9l5 5v13H6z"/>
                                    <path d="M14 3v5h5"/>
                                </svg>
                            </div>
                            <div class="fx360-tile__title">Plantillas</div>
                            <div class="fx360-tile__sub">Mapeo reusable por cliente, giro y flujo de timbrado.</div>
                        </div>

                        <div class="fx360-tile {{ $sumIsPro ? '' : 'fx360-proveil' }}">
                            @unless($sumIsPro)
                                <span class="fx360-lock" title="Solo PRO">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="5" y="11" width="14" height="10" rx="2"/>
                                        <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
                                    </svg>
                                </span>
                            @endunless
                            <div class="fx360-tile__ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 12h18"/>
                                    <path d="M12 3v18"/>
                                </svg>
                            </div>
                            <div class="fx360-tile__title">Lotes</div>
                            <div class="fx360-tile__sub">Timbrado por volumen con control de fila, bitácora y errores.</div>
                        </div>

                        <div class="fx360-tile {{ $sumIsPro ? '' : 'fx360-proveil' }}">
                            @unless($sumIsPro)
                                <span class="fx360-lock" title="Solo PRO">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="5" y="11" width="14" height="10" rx="2"/>
                                        <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
                                    </svg>
                                </span>
                            @endunless
                            <div class="fx360-tile__ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 20V10"/>
                                    <path d="M18 20V4"/>
                                    <path d="M6 20v-6"/>
                                </svg>
                            </div>
                            <div class="fx360-tile__title">Nómina / CFDI masivo</div>
                            <div class="fx360-tile__sub">Base visual para RH y emisión de comprobantes de alto volumen.</div>
                        </div>
                    </div>
                </div>
            </details>
        </section>

        <section class="sat-clean-accordion" aria-label="Reporte rápido">
            <details class="sat-clean-accordion__item">
                <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
                    <div class="sat-clean-accordion__bar-left">
                        <span class="sat-clean-accordion__bar-title">Reporte rápido</span>
                        <span class="sat-clean-accordion__bar-text">Lectura inmediata del comportamiento fiscal del periodo</span>
                    </div>

                    <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </span>
                </summary>

                <div class="sat-clean-accordion__content">
                    <div class="fx360-series" style="padding-top:12px;">
                        <div class="fx360-series-card">
                            <div class="fx360-series-card__label">CFDI emitidos</div>
                            <div class="fx360-series-card__value">{{ number_format((float) $emitidosCount) }}</div>
                            <div class="fx360-series-card__sub">Total acumulado en la serie operativa disponible.</div>
                        </div>

                        <div class="fx360-series-card">
                            <div class="fx360-series-card__label">Facturación</div>
                            <div class="fx360-series-card__value">{{ number_format((float) $ingresosCount) }}</div>
                            <div class="fx360-series-card__sub">Lectura rápida del comportamiento de la línea de facturación.</div>
                        </div>

                        <div class="fx360-series-card">
                            <div class="fx360-series-card__label">Cancelaciones</div>
                            <div class="fx360-series-card__value">{{ number_format((float) $cancelCount) }}</div>
                            <div class="fx360-series-card__sub">Señal útil para control operativo y análisis del periodo.</div>
                        </div>
                    </div>
                </div>
            </details>
        </section>

        @if($rtNomina)
            <section class="sat-clean-accordion" aria-label="Nómina">
                <details class="sat-clean-accordion__item">
                    <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
                        <div class="sat-clean-accordion__bar-left">
                            <span class="sat-clean-accordion__bar-title">Nómina</span>
                            <span class="sat-clean-accordion__bar-text">Integración con RH para CFDI de nómina y operación escalable</span>
                        </div>

                        <div style="display:flex; align-items:center; gap:10px;">
                            <span class="sat-clean-status-badge {{ $sumIsPro ? 'is-success' : 'is-muted' }}">
                                {{ $sumIsPro ? 'PRO' : 'BASE' }}
                            </span>

                            <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                                    <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                                </svg>
                            </span>
                        </div>
                    </summary>

                    <div class="sat-clean-accordion__content">
                        <div style="padding-top:12px;">
                            <a href="{{ $rtNomina }}" class="sat-clean-btn sat-clean-btn--primary">
                                Abrir Nómina
                            </a>
                        </div>
                    </div>
                </details>
            </section>
        @endif

    </div>
</div>

<style>
    .fx360-pac-panel{
        display:grid;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:14px;
        align-items:center;
        margin-top:12px;
        padding:14px;
        border:1px solid rgba(219,231,245,.95);
        border-radius:20px;
        background:#f8fbff;
    }

    .fx360-pac-env{
        display:flex;
        gap:8px;
        padding:5px;
        border-radius:16px;
        background:#eef4ff;
    }

    .fx360-pac-env__btn{
        min-height:38px;
        padding:0 14px;
        border:0;
        border-radius:13px;
        background:transparent;
        color:#315784;
        font-size:12px;
        font-weight:900;
        cursor:pointer;
    }

    .fx360-pac-env__btn.is-active{
        background:#0f5eff;
        color:#fff;
        box-shadow:0 10px 22px rgba(15,94,255,.18);
    }

    .fx360-pac-copy strong,
    .fx360-pac-copy span{
        display:block;
    }

    .fx360-pac-copy strong{
        color:#10213f;
        font-size:14px;
        font-weight:900;
        margin-bottom:3px;
    }

    .fx360-pac-copy span{
        color:#64748b;
        font-size:12px;
        line-height:1.45;
        font-weight:700;
    }

    .fx360-pac-result{
        margin-top:12px;
        border-radius:16px;
        padding:13px 14px;
        font-size:12px;
        font-weight:800;
        line-height:1.5;
    }

    .fx360-pac-result strong,
    .fx360-pac-result span{
        display:block;
    }

    .fx360-pac-result.is-info{
        background:#eff6ff;
        border:1px solid #bfdbfe;
        color:#1e40af;
    }

    .fx360-pac-result.is-success{
        background:#f0fdf4;
        border:1px solid #bbf7d0;
        color:#166534;
    }

    .fx360-pac-result.is-danger{
        background:#fef2f2;
        border:1px solid #fecaca;
        color:#991b1b;
    }

    @media (max-width: 991.98px){
        .fx360-pac-panel{
            grid-template-columns:1fr;
        }
    }

    .fx360-cfdi-id{
    display:grid;
    gap:4px;
    min-width:0;
}

.fx360-cfdi-id__serie{
    display:inline-flex;
    width:max-content;
    max-width:100%;
    padding:4px 8px;
    border-radius:999px;
    background:#eef5ff;
    color:#1d4ed8;
    border:1px solid #d8e7ff;
    font-size:11px;
    line-height:1.1;
    font-weight:950;
    letter-spacing:.01em;
}

.fx360-cfdi-id__uuid{
    display:block;
    max-width:360px;
    color:#64748b;
    font-size:10.5px;
    line-height:1.35;
    font-weight:800;
    font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
    word-break:break-all;
}

.fx360-cfdi-id__uuid.is-empty{
    color:#b45309;
}

.fx360-action-icon--pdf,
.fx360-action-icon--xml{
    width:auto !important;
    min-width:38px;
    padding:0 9px;
    font-size:10px;
    font-weight:950;
    text-decoration:none;
}

.fx360-action-icon--pdf{
    color:#b91c1c;
    background:#fff5f5;
    border-color:#fecaca;
}

.fx360-action-icon--xml{
    color:#0f766e;
    background:#f0fdfa;
    border-color:#99f6e4;
}
</style>

<script>
(function(){
    const root = document.querySelector('[data-fx360-pac]');
    if (!root) return;

    const buttons = document.querySelectorAll('[data-fx360-env]');
    const title = document.getElementById('fx360PacEnvTitle');
    const text = document.getElementById('fx360PacEnvText');
    const badge = document.getElementById('fx360PacEnvBadge');
    const testButton = document.getElementById('fx360FacturotopiaTest');
    const resultBox = document.getElementById('fx360FacturotopiaResult');

    function currentEnv(){
        const active = document.querySelector('[data-fx360-env].is-active');
        return active ? active.dataset.fx360Env : 'sandbox';
    }

    function setEnv(env){
        env = env === 'production' ? 'production' : 'sandbox';

        buttons.forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.fx360Env === env);
        });

        document.querySelectorAll('.fx360-timbrar-form input[name="facturotopia_env"]').forEach((input) => {
            input.value = env;
        });

        if (badge) badge.textContent = env === 'production' ? 'PRODUCCIÓN' : 'PRUEBAS';
        if (title) title.textContent = env === 'production' ? 'Producción activa' : 'Pruebas activas';
        if (text) {
            text.textContent = env === 'production'
                ? 'El timbrado usará API key/base URL de producción y descontará timbres reales.'
                : 'El timbrado usará API key/base URL sandbox y no debe descontar timbres reales.';
        }
    }

    buttons.forEach((btn) => {
        btn.addEventListener('click', function(){
            setEnv(this.dataset.fx360Env || 'sandbox');
        });
    });

    if (testButton && resultBox) {
        testButton.addEventListener('click', async function(){
            const url = this.dataset.url || '';
            const csrf = this.dataset.csrf || '';
            const env = currentEnv();

            if (!url || url === '#') return;

            this.disabled = true;
            resultBox.hidden = false;
            resultBox.className = 'fx360-pac-result is-info';
            resultBox.textContent = 'Probando conexión Facturotopia...';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({ env })
                });

                const json = await response.json();

                resultBox.className = json.ok
                    ? 'fx360-pac-result is-success'
                    : 'fx360-pac-result is-danger';

                resultBox.innerHTML = `
                    <strong>${json.message || 'Resultado Facturotopia'}</strong>
                    <span>Ambiente: ${json.env || env}</span>
                    <span>Base URL: ${json.base_url || 'No configurada'}</span>
                    <span>HTTP: ${json.status || 'N/D'} · Tiempo: ${json.response_ms || 0} ms</span>
                    <span>API key: ${json.has_api_key ? 'OK' : 'Falta'} · Usuario: ${json.has_user ? 'OK' : 'Falta'} · Contraseña: ${json.has_password ? 'OK' : 'Falta'}</span>
                `;
            } catch (error) {
                resultBox.className = 'fx360-pac-result is-danger';
                resultBox.textContent = 'Error al probar conexión: ' + error.message;
            } finally {
                this.disabled = false;
            }
        });
    }

    setEnv(currentEnv());
})();
</script>
@endsection