{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\statements_v2\index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Facturación · Estados de cuenta')
@section('layout', 'full')
@section('contentLayout', 'full')
@section('pageClass', 'billing-statements-v2-page')

@php
    $cssPath = public_path('assets/admin/css/billing-statements-v2.css');
    $jsPath  = public_path('assets/admin/js/billing-statements-v2.js');

    $cssVer = is_file($cssPath) ? filemtime($cssPath) : time();
    $jsVer  = is_file($jsPath) ? filemtime($jsPath) : time();

    $statements = $statements ?? collect();

    $routeShowPreview = \Illuminate\Support\Facades\Route::has('admin.billing.statements_v2.preview')
        ? 'admin.billing.statements_v2.preview'
        : null;

    $routeStatusUpdate = \Illuminate\Support\Facades\Route::has('admin.billing.statements_v2.status.update')
        ? 'admin.billing.statements_v2.status.update'
        : null;

    $routeSendPreview = \Illuminate\Support\Facades\Route::has('admin.billing.statements_v2.email.preview')
        ? 'admin.billing.statements_v2.email.preview'
        : null;

    $routeSendStore = \Illuminate\Support\Facades\Route::has('admin.billing.statements_v2.email.send')
        ? 'admin.billing.statements_v2.email.send'
        : null;

    $routeDownload = \Illuminate\Support\Facades\Route::has('admin.billing.statements_v2.download')
        ? 'admin.billing.statements_v2.download'
        : null;

    $routeBulkSend = \Illuminate\Support\Facades\Route::has('admin.billing.statements_v2.bulk.send')
        ? route('admin.billing.statements_v2.bulk.send')
        : '';

    $routeAdvancePayments = \Illuminate\Support\Facades\Route::has('admin.billing.statements_v2.payments.advance')
        ? route('admin.billing.statements_v2.payments.advance')
        : '';

    $routeBulkPayments = \Illuminate\Support\Facades\Route::has('admin.billing.statements_v2.payments.bulk')
        ? route('admin.billing.statements_v2.payments.bulk')
        : '';

    $routeGenerateCutoff = \Illuminate\Support\Facades\Route::has('admin.billing.statements_v2.generate_cutoff')
    ? route('admin.billing.statements_v2.generate_cutoff')
    : '';

    $routeCommercialAgreementSave = \Illuminate\Support\Facades\Route::has('admin.billing.statements_v2.commercial_agreement.save')
    ? 'admin.billing.statements_v2.commercial_agreement.save'
    : null;

       $statusUiMap = [
        'paid'      => ['label' => 'Pagado',       'class' => 'is-paid'],
        'pagado'    => ['label' => 'Pagado',       'class' => 'is-paid'],
        'pending'   => ['label' => 'Pendiente',    'class' => 'is-pending'],
        'pendiente' => ['label' => 'Pendiente',    'class' => 'is-pending'],
        'partial'   => ['label' => 'Parcial',      'class' => 'is-partial'],
        'parcial'   => ['label' => 'Parcial',      'class' => 'is-partial'],
        'overdue'   => ['label' => 'Vencido',      'class' => 'is-overdue'],
        'vencido'   => ['label' => 'Vencido',      'class' => 'is-overdue'],
        'late'      => ['label' => 'Vencido',      'class' => 'is-overdue'],
        'sin_mov'   => ['label' => 'Sin movimiento','class' => 'is-info'],
    ];

        $selectedStatementIds = collect((array) request()->input('selected_ids', []))
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->unique()
        ->values()
        ->all();

    $kpiTotalStatements = $statements->count();

    $kpiPaidCount = $statements->filter(function ($statement) {
        $status = strtolower(trim((string) (data_get($statement, 'status') ?? '')));
        return in_array($status, ['paid', 'pagado'], true);
    })->count();

    $kpiPendingCount = $statements->filter(function ($statement) {
        $status = strtolower(trim((string) (data_get($statement, 'status') ?? '')));
        return in_array($status, ['pending', 'pendiente'], true);
    })->count();

    $kpiOverdueCount = $statements->filter(function ($statement) {
        $status = strtolower(trim((string) (data_get($statement, 'status') ?? '')));
        return in_array($status, ['overdue', 'vencido', 'late'], true);
    })->count();

    $kpiPartialCount = $statements->filter(function ($statement) {
        $status = strtolower(trim((string) (data_get($statement, 'status') ?? '')));
        return in_array($status, ['partial', 'parcial'], true);
    })->count();

    $kpiNoMovementCount = $statements->filter(function ($statement) {
        $status = strtolower(trim((string) (data_get($statement, 'status') ?? '')));
        return in_array($status, ['sin_mov', 'sin movimiento', 'no_movement'], true);
    })->count();

    $kpiSaldoTotal = round((float) $statements->sum(function ($statement) {
        return (float) (
            data_get($statement, 'saldo_total')
            ?? data_get($statement, 'total_balance')
            ?? 0
        );
    }), 2);

    $kpiTotalPeriodo = round((float) $statements->sum(function ($statement) {
        return (float) (
            data_get($statement, 'total_periodo')
            ?? data_get($statement, 'period_total')
            ?? data_get($statement, 'total_cargo')
            ?? 0
        );
    }), 2);

    $kpiTotalAbonado = round((float) $statements->sum(function ($statement) {
        return (float) (
            data_get($statement, 'total_abono')
            ?? 0
        );
    }), 2);

    $kpiCobranzaRate = $kpiTotalPeriodo > 0
        ? round(($kpiTotalAbonado / $kpiTotalPeriodo) * 100, 1)
        : 0.0;

    $kpiPaidPercent = $kpiTotalStatements > 0 ? round(($kpiPaidCount / $kpiTotalStatements) * 100, 1) : 0;
    $kpiPendingPercent = $kpiTotalStatements > 0 ? round(($kpiPendingCount / $kpiTotalStatements) * 100, 1) : 0;
    $kpiOverduePercent = $kpiTotalStatements > 0 ? round(($kpiOverdueCount / $kpiTotalStatements) * 100, 1) : 0;
    $kpiPartialPercent = $kpiTotalStatements > 0 ? round(($kpiPartialCount / $kpiTotalStatements) * 100, 1) : 0;

    $kpiMaxStatusCount = max(1, $kpiPaidCount, $kpiPendingCount, $kpiOverdueCount, $kpiPartialCount);

    $kpiPaidBar = max(10, round(($kpiPaidCount / $kpiMaxStatusCount) * 100));
    $kpiPendingBar = max(10, round(($kpiPendingCount / $kpiMaxStatusCount) * 100));
    $kpiOverdueBar = max(10, round(($kpiOverdueCount / $kpiMaxStatusCount) * 100));
    $kpiPartialBar = max(10, round(($kpiPartialCount / $kpiMaxStatusCount) * 100));

    $kpiChargeVsPaidPercent = $kpiTotalPeriodo > 0
        ? min(100, round(($kpiTotalAbonado / $kpiTotalPeriodo) * 100))
        : 0;

    $kpiPendingVsTotalPercent = $kpiTotalPeriodo > 0
        ? min(100, round(($kpiSaldoTotal / $kpiTotalPeriodo) * 100))
        : 0;
@endphp


@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/billing-statements-v2.css') }}?v={{ $cssVer }}">
@endpush

@section('content')
<div
    class="bsv2-page"
    data-bsv2-root
    data-bsv2-preview-route-name="{{ $routeShowPreview ?? '' }}"
    data-bsv2-status-route-name="{{ $routeStatusUpdate ?? '' }}"
    data-bsv2-email-preview-route-name="{{ $routeSendPreview ?? '' }}"
    data-bsv2-email-send-route-name="{{ $routeSendStore ?? '' }}"
    data-bsv2-download-route-name="{{ $routeDownload ?? '' }}"
    data-bsv2-commercial-agreement-route-name="{{ $routeCommercialAgreementSave ?? '' }}"
    data-bsv2-bulk-send-url="{{ $routeBulkSend }}"
    data-bsv2-advance-payments-url="{{ $routeAdvancePayments }}"
    data-bsv2-bulk-payments-url="{{ $routeBulkPayments }}"
>
    <div class="bsv2-wrap">
        <section class="bsv2-header-clean" aria-label="Encabezado de estados de cuenta">
            <div class="bsv2-header-clean__content">
                <div class="bsv2-header-clean__text">
                    <h1 class="bsv2-title">Estados de cuenta</h1>
                    <p class="bsv2-subtitle">
                        Control centralizado de saldos, cargos, envíos y seguimiento por cliente.
                    </p>
                </div>
            </div>
        </section>

                <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Resumen ejecutivo de estados de cuenta">
            <div class="bsv2-list-card__accordion">
                <button
                    type="button"
                    class="bsv2-list-card__summary"
                    id="bsv2-kpis-toggle"
                    aria-expanded="false"
                    aria-controls="bsv2-kpis-content"
                >
                    <span class="bsv2-list-card__summary-main">
                        <span class="bsv2-list-card__summary-title">Resumen</span>
                        <span class="bsv2-list-card__summary-meta">
                            KPIs y gráficas rápidas del estado actual de cobranza
                        </span>
                    </span>

                    <span class="bsv2-list-card__summary-action" aria-hidden="true">
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                    </span>
                </button>

                <div class="bsv2-list-card__content" id="bsv2-kpis-content" hidden>
                    <div class="bsv2-kpi-strip">
                        <article class="bsv2-kpi-card">
                            <span class="bsv2-kpi-card__label">Estados</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($kpiTotalStatements) }}</strong>
                            <span class="bsv2-kpi-card__meta">Total visible</span>
                        </article>

                        <article class="bsv2-kpi-card is-paid">
                            <span class="bsv2-kpi-card__label">Pagados</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($kpiPaidCount) }}</strong>
                            <span class="bsv2-kpi-card__meta">{{ number_format($kpiPaidPercent, 1) }}%</span>
                        </article>

                        <article class="bsv2-kpi-card is-pending">
                            <span class="bsv2-kpi-card__label">Pendientes</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($kpiPendingCount) }}</strong>
                            <span class="bsv2-kpi-card__meta">{{ number_format($kpiPendingPercent, 1) }}%</span>
                        </article>

                        <article class="bsv2-kpi-card is-overdue">
                            <span class="bsv2-kpi-card__label">Vencidos</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($kpiOverdueCount) }}</strong>
                            <span class="bsv2-kpi-card__meta">{{ number_format($kpiOverduePercent, 1) }}%</span>
                        </article>

                        <article class="bsv2-kpi-card is-partial">
                            <span class="bsv2-kpi-card__label">Parciales</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($kpiPartialCount) }}</strong>
                            <span class="bsv2-kpi-card__meta">{{ number_format($kpiPartialPercent, 1) }}%</span>
                        </article>

                        <article class="bsv2-kpi-card">
                            <span class="bsv2-kpi-card__label">Saldo total</span>
                            <strong class="bsv2-kpi-card__value">${{ number_format($kpiSaldoTotal, 2) }}</strong>
                            <span class="bsv2-kpi-card__meta">Pendiente acumulado</span>
                        </article>

                        <article class="bsv2-kpi-card">
                            <span class="bsv2-kpi-card__label">Cobrado</span>
                            <strong class="bsv2-kpi-card__value">${{ number_format($kpiTotalAbonado, 2) }}</strong>
                            <span class="bsv2-kpi-card__meta">{{ number_format($kpiCobranzaRate, 1) }}% del período</span>
                        </article>
                    </div>

                    <div class="bsv2-mini-analytics">
                        <article class="bsv2-mini-chart-card">
                            <div class="bsv2-mini-chart-card__head">
                                <div>
                                    <div class="bsv2-mini-chart-card__title">Distribución por estatus</div>
                                    <div class="bsv2-mini-chart-card__subtitle">Lectura rápida del listado actual</div>
                                </div>
                            </div>

                            <div class="bsv2-mini-bars">
                                <div class="bsv2-mini-bars__row">
                                    <span class="bsv2-mini-bars__label">Pagados</span>
                                    <div class="bsv2-mini-bars__track">
                                        <span class="bsv2-mini-bars__fill is-paid" style="width: {{ $kpiPaidBar }}%"></span>
                                    </div>
                                    <span class="bsv2-mini-bars__value">{{ number_format($kpiPaidCount) }}</span>
                                </div>

                                <div class="bsv2-mini-bars__row">
                                    <span class="bsv2-mini-bars__label">Pendientes</span>
                                    <div class="bsv2-mini-bars__track">
                                        <span class="bsv2-mini-bars__fill is-pending" style="width: {{ $kpiPendingBar }}%"></span>
                                    </div>
                                    <span class="bsv2-mini-bars__value">{{ number_format($kpiPendingCount) }}</span>
                                </div>

                                <div class="bsv2-mini-bars__row">
                                    <span class="bsv2-mini-bars__label">Vencidos</span>
                                    <div class="bsv2-mini-bars__track">
                                        <span class="bsv2-mini-bars__fill is-overdue" style="width: {{ $kpiOverdueBar }}%"></span>
                                    </div>
                                    <span class="bsv2-mini-bars__value">{{ number_format($kpiOverdueCount) }}</span>
                                </div>

                                <div class="bsv2-mini-bars__row">
                                    <span class="bsv2-mini-bars__label">Parciales</span>
                                    <div class="bsv2-mini-bars__track">
                                        <span class="bsv2-mini-bars__fill is-partial" style="width: {{ $kpiPartialBar }}%"></span>
                                    </div>
                                    <span class="bsv2-mini-bars__value">{{ number_format($kpiPartialCount) }}</span>
                                </div>
                            </div>
                        </article>

                        <article class="bsv2-mini-chart-card">
                            <div class="bsv2-mini-chart-card__head">
                                <div>
                                    <div class="bsv2-mini-chart-card__title">Cobranza del período</div>
                                    <div class="bsv2-mini-chart-card__subtitle">Abonado vs total facturado visible</div>
                                </div>
                                <div class="bsv2-mini-chart-card__badge">
                                    {{ number_format($kpiCobranzaRate, 1) }}%
                                </div>
                            </div>

                            <div class="bsv2-progress-dual">
                                <div class="bsv2-progress-dual__item">
                                    <div class="bsv2-progress-dual__meta">
                                        <span>Cobrado</span>
                                        <strong>${{ number_format($kpiTotalAbonado, 2) }}</strong>
                                    </div>
                                    <div class="bsv2-progress-dual__track">
                                        <span class="bsv2-progress-dual__fill is-paid" style="width: {{ $kpiChargeVsPaidPercent }}%"></span>
                                    </div>
                                </div>

                                <div class="bsv2-progress-dual__item">
                                    <div class="bsv2-progress-dual__meta">
                                        <span>Saldo pendiente</span>
                                        <strong>${{ number_format($kpiSaldoTotal, 2) }}</strong>
                                    </div>
                                    <div class="bsv2-progress-dual__track">
                                        <span class="bsv2-progress-dual__fill is-pending" style="width: {{ $kpiPendingVsTotalPercent }}%"></span>
                                    </div>
                                </div>

                                <div class="bsv2-progress-dual__foot">
                                    <span>Total del período visible</span>
                                    <strong>${{ number_format($kpiTotalPeriodo, 2) }}</strong>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Filtros y acciones masivas de estados de cuenta">
            <div class="bsv2-list-card__accordion">
                <button
                    type="button"
                    class="bsv2-list-card__summary"
                    id="bsv2-filters-toggle"
                    aria-expanded="false"
                    aria-controls="bsv2-filters-content"
                >
                    <span class="bsv2-list-card__summary-main">
                        <span class="bsv2-list-card__summary-title">Filtros</span>
                        <span class="bsv2-list-card__summary-meta">
                            Búsqueda, rango de fechas, estatus, selección y envíos masivos
                        </span>
                    </span>

                    <span class="bsv2-list-card__summary-action" aria-hidden="true">
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                    </span>
                </button>

                <div class="bsv2-list-card__content" id="bsv2-filters-content" hidden>
                    <form method="GET" action="{{ url()->current() }}" class="bsv2-filters-form" id="bsv2-filters-form">
                        <input type="hidden" name="period" value="{{ $currentPeriod ?? now()->format('Y-m') }}">
                        <div id="bsv2-selected-hidden-inputs">
                            @foreach($selectedStatementIds as $selectedStatementId)
                                <input type="hidden" name="selected_ids[]" value="{{ $selectedStatementId }}">
                            @endforeach
                        </div>
                        <div class="bsv2-filters-grid">
                            <div class="bsv2-filter-item bsv2-filter-item--search">
                                <label class="bsv2-filter-label" for="filter_search">Buscar</label>
                                <div class="bsv2-filter-control-wrap">
                                    <span class="bsv2-filter-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="1.8"/>
                                            <path d="M20 20l-4.2-4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    <input
                                        type="text"
                                        name="search"
                                        id="filter_search"
                                        class="bsv2-filter-control bsv2-filter-control--with-icon"
                                        value="{{ request('search') }}"
                                        placeholder="Nombre, RFC, correo, ID o razón social..."
                                    >
                                </div>
                            </div>

                            <div class="bsv2-filter-item">
                                <label class="bsv2-filter-label" for="filter_date_from">Desde</label>
                                <input
                                    type="date"
                                    name="date_from"
                                    id="filter_date_from"
                                    class="bsv2-filter-control"
                                    value="{{ request('date_from') }}"
                                >
                            </div>

                            <div class="bsv2-filter-item">
                                <label class="bsv2-filter-label" for="filter_date_to">Hasta</label>
                                <input
                                    type="date"
                                    name="date_to"
                                    id="filter_date_to"
                                    class="bsv2-filter-control"
                                    value="{{ request('date_to') }}"
                                >
                            </div>

                            <div class="bsv2-filter-item">
                                <label class="bsv2-filter-label" for="filter_status">Estatus</label>
                                <select name="status" id="filter_status" class="bsv2-filter-control">
                                    <option value="">Todos</option>
                                    <option value="pendiente" @selected(request('status') === 'pendiente')>Pendiente</option>
                                    <option value="pagado" @selected(request('status') === 'pagado')>Pagado</option>
                                    <option value="vencido" @selected(request('status') === 'vencido')>Vencido</option>
                                    <option value="sin_mov" @selected(request('status') === 'sin_mov')>Sin movimiento</option>
                                    <option value="parcial" @selected(request('status') === 'parcial')>Parcial</option>
                                </select>
                            </div>

                            <div class="bsv2-filter-item">
                                <label class="bsv2-filter-label" for="filter_scope">Mostrar</label>
                                <select name="scope" id="filter_scope" class="bsv2-filter-control">
                                    <option value="">Todos</option>
                                    <option value="selected" @selected(request('scope') === 'selected')>Solo seleccionados</option>
                                    <option value="unselected" @selected(request('scope') === 'unselected')>No seleccionados</option>
                                </select>
                            </div>

                            <div class="bsv2-filter-item">
                                <label class="bsv2-filter-label" for="filter_per_page">Por página</label>
                                <select name="per_page" id="filter_per_page" class="bsv2-filter-control">
                                    <option value="25" @selected((string) request('per_page', '25') === '25')>25</option>
                                    <option value="50" @selected((string) request('per_page') === '50')>50</option>
                                    <option value="100" @selected((string) request('per_page') === '100')>100</option>
                                    <option value="250" @selected((string) request('per_page') === '250')>250</option>
                                    <option value="500" @selected((string) request('per_page') === '500')>500</option>
                                    <option value="1000" @selected((string) request('per_page') === '1000')>1000</option>
                                </select>
                            </div>
                        </div>

                        <div class="bsv2-bulk-toolbar">
                            <div class="bsv2-bulk-toolbar__group">
                                <div class="bsv2-bulk-chip">
                                    <span class="bsv2-bulk-chip__label">Seleccionados</span>
                                    <strong class="bsv2-bulk-chip__value" id="bsv2-selected-count">{{ count($selectedStatementIds) }}</strong>
                                </div>
                            </div>

                            <div class="bsv2-bulk-toolbar__group bsv2-bulk-toolbar__group--actions">
                                <button type="submit" class="bsv2-btn bsv2-btn--primary bsv2-btn--icon-only" data-floating-label="Filtrar" aria-label="Filtrar">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                </button>

                                <a href="{{ route('admin.billing.statements_v2.index', ['period' => $currentPeriod ?? now()->format('Y-m')]) }}" class="bsv2-btn bsv2-btn--ghost bsv2-btn--icon-only" data-floating-label="Limpiar" aria-label="Limpiar">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </a>

                                <button type="button" class="bsv2-btn bsv2-btn--soft bsv2-btn--icon-only" id="bsv2-select-all-visible" data-floating-label="Seleccionar visibles" aria-label="Seleccionar visibles">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <rect x="4" y="4" width="16" height="16" rx="3" stroke="currentColor" stroke-width="1.8"/>
                                            <path d="M8 12l2.5 2.5L16 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </button>

                                <button type="button" class="bsv2-btn bsv2-btn--soft bsv2-btn--icon-only" id="bsv2-clear-selected" data-floating-label="Quitar selección" aria-label="Quitar selección">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M6 6 18 18M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                </button>

                                <button type="button" class="bsv2-btn bsv2-btn--soft bsv2-btn--icon-only" id="bsv2-open-advance-modal" data-floating-label="Adelantar pagos" aria-label="Adelantar pagos">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M16 8h3a1 1 0 0 1 1 1v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                </button>

                                <button type="button" class="bsv2-btn bsv2-btn--soft bsv2-btn--icon-only" id="bsv2-open-bulk-payments-modal" data-floating-label="Pagos masivos" aria-label="Pagos masivos">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M18 16v5m-2.5-2.5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                </button>

                                @if($routeGenerateCutoff !== '')
                                    <form method="POST" action="{{ $routeGenerateCutoff }}" style="display:inline-flex;margin:0;">
                                        @csrf
                                        <input type="hidden" name="period" value="{{ $currentPeriod ?? now()->format('Y-m') }}">

                                        <button
                                            type="submit"
                                            class="bsv2-btn bsv2-btn--primary bsv2-btn--icon-only"
                                            data-floating-label="Generar corte"
                                            aria-label="Generar corte"
                                            onclick="return confirm('¿Generar corte para el período {{ $currentPeriod ?? now()->format('Y-m') }}? Solo se crearán estados faltantes.')"
                                        >
                                            <span class="bsv2-btn__icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 5h16M4 12h16M4 19h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                    <path d="M17 16v6m-3-3h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                </svg>
                                            </span>
                                        </button>
                                    </form>
                                @endif

                                <button type="button" class="bsv2-btn bsv2-btn--primary bsv2-btn--icon-only" id="bsv2-send-all-email" data-floating-label="Enviar todos" aria-label="Enviar todos">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M22 2 11 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M22 2 15 22l-4-9-9-4L22 2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </button>

                                <button type="button" class="bsv2-btn bsv2-btn--primary bsv2-btn--icon-only" id="bsv2-send-selected-email" data-floating-label="Enviar seleccionados" aria-label="Enviar seleccionados">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <rect x="3" y="5" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="1.8"/>
                                            <path d="m6 8 6 5 6-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Listado de estados de cuenta">
                @php
                    $periodAccordionLabel = \Illuminate\Support\Carbon::createFromFormat(
                        'Y-m',
                        $currentPeriod ?? now()->format('Y-m')
                    )->translatedFormat('F Y');
                @endphp

                <div class="bsv2-list-card__accordion">
                    <button
                        type="button"
                        class="bsv2-list-card__summary"
                        id="bsv2-list-toggle"
                        aria-expanded="true"
                        aria-controls="bsv2-list-content"
                    >
                        <span class="bsv2-list-card__summary-main">
                            <span class="bsv2-list-card__summary-title">Listado</span>
                            <span class="bsv2-list-card__summary-meta">
                                {{ $totalFiltered ?? $statements->count() }} registros · Mostrando {{ $statements->count() }} · Período {{ $periodAccordionLabel }}
                            </span>
                        </span>

                        <span class="bsv2-list-card__summary-action" aria-hidden="true">
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                        </span>
                    </button>

                    <div
                        class="bsv2-list-card__content"
                        id="bsv2-list-content"
                    >
                        <div class="bsv2-table-wrap">
                            <table class="bsv2-table bsv2-table--compact">
                                <thead>
                                    <tr>
                                        <th class="bsv2-col-select">
                                            <input type="checkbox" id="bsv2-master-checkbox">
                                        </th>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Saldo anterior</th>
                                        <th>Total del período</th>
                                        <th>Saldo total</th>
                                        <th>Estatus</th>
                                        <th class="bsv2-col-actions"></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse($statements as $statement)
                                        @php
                                            $clientSequence = data_get($statement, 'client_sequence')
                                                ?? data_get($statement, 'customer_no')
                                                ?? data_get($statement, 'codigo_cliente')
                                                ?? null;

                                            $clientId = filled($clientSequence) ? $clientSequence : '—';

                                            $clientName = data_get($statement, 'client_name')
                                                ?? data_get($statement, 'razon_social')
                                                ?? 'Cliente sin nombre';

                                            $clientRfc = data_get($statement, 'client_rfc')
                                                ?? data_get($statement, 'rfc')
                                                ?? 'Sin RFC';

                                            $clientEmail = data_get($statement, 'client_email')
                                                ?? data_get($statement, 'email')
                                                ?? 'Sin correo';

                                            $licenseType = data_get($statement, 'license_type') ?? 'Sin licencia';
                                            $billingMode = data_get($statement, 'billing_mode') ?? 'Sin definir';
                                            $statementPeriod = (string) (data_get($statement, 'period') ?? '');
                                            $periodLabel = (string) (data_get($statement, 'period_label') ?? $statementPeriod);

                                            $previousBalance = (float) (
                                                data_get($statement, 'saldo_anterior')
                                                ?? data_get($statement, 'previous_balance')
                                                ?? 0
                                            );

                                            $periodTotal = (float) (
                                                data_get($statement, 'total_periodo')
                                                ?? data_get($statement, 'period_total')
                                                ?? data_get($statement, 'total_cargo')
                                                ?? 0
                                            );

                                            $totalBalance = (float) (
                                                data_get($statement, 'saldo_total')
                                                ?? data_get($statement, 'total_balance')
                                                ?? 0
                                            );

                                            $lastPaymentDate = data_get($statement, 'last_payment_date')
                                                ?? data_get($statement, 'paid_at')
                                                ?? null;

                                            $lastPaymentLabel = $lastPaymentDate
                                                ? \Illuminate\Support\Carbon::parse($lastPaymentDate)->format('d/m/Y')
                                                : '—';

                                            $rawStatus = strtolower((string) (data_get($statement, 'status') ?? 'pending'));

                                            $statusChangedAt = data_get($statement, 'status_override_updated_at')
                                                ?? data_get($statement, 'updated_at')
                                                ?? data_get($statement, 'source_statement.updated_at')
                                                ?? null;

                                            $statusChangedLabel = $statusChangedAt
                                                ? \Illuminate\Support\Carbon::parse($statusChangedAt)->format('d/m/Y')
                                                : '—';

                                            $statusDatePrefix = in_array($rawStatus, ['paid', 'pagado'], true) && $lastPaymentDate
                                                ? 'Pago'
                                                : 'Actualizado';

                                            $statusDateLabel = in_array($rawStatus, ['paid', 'pagado'], true) && $lastPaymentDate
                                                ? $lastPaymentLabel
                                                : $statusChangedLabel;
                                            $statusData = $statusUiMap[$rawStatus] ?? ['label' => 'Pendiente', 'class' => 'is-pending'];
                                            $statusLabel = $statusData['label'];
                                            $statusClass = $statusData['class'];

                                            $statementAccountId = (string) (data_get($statement, 'account_id') ?? '');
                                            $statementId = (string) (data_get($statement, 'statement_id') ?? data_get($statement, 'id') ?? '');

                                            $previewUrl = $routeShowPreview
                                                ? route($routeShowPreview, ['accountId' => $statementAccountId, 'period' => $statementPeriod])
                                                : null;

                                            $downloadUrl = $routeDownload
                                                ? route($routeDownload, ['accountId' => $statementAccountId, 'period' => $statementPeriod])
                                                : null;

                                            $statusUpdateUrl = $routeStatusUpdate
                                                ? route($routeStatusUpdate, ['accountId' => $statementAccountId, 'period' => $statementPeriod])
                                                : null;

                                            $emailPreviewUrl = $routeSendPreview
                                                ? route($routeSendPreview, ['accountId' => $statementAccountId, 'period' => $statementPeriod])
                                                : null;

                                            $emailSendUrl = $routeSendStore
                                                ? route($routeSendStore, ['accountId' => $statementAccountId, 'period' => $statementPeriod])
                                                : null;

                                            $menuId = 'statement-actions-' . ($loop->index + 1);

                                            $defaultSubject = 'Pactopia360 · Estado de cuenta ' . $periodLabel . ' · ' . $clientName;
                                            $defaultMessage = "Hola {$clientName},\n\nTu estado de cuenta correspondiente a {$periodLabel} ya está listo para revisión y pago.\n\nAdjuntamos la información del estado de cuenta para tu seguimiento.\n\nSaludos,\nEquipo Pactopia360";

                                            $defaultRecipients = trim((string) $clientEmail);
                                        @endphp

                                        <tr
                                            class="bsv2-row"
                                            data-statement-row
                                            data-statement-id="{{ $statementId }}"
                                            data-account-id="{{ $statementAccountId }}"
                                            data-period="{{ $statementPeriod }}"
                                            data-client-name="{{ e($clientName) }}"
                                            data-email="{{ e($clientEmail) }}"
                                        >
                                            <td class="bsv2-cell-select">
                                                <label class="bsv2-check">
                                                    <input
                                                        type="checkbox"
                                                        class="bsv2-row-checkbox"
                                                        value="{{ $statementId }}"
                                                        data-account-id="{{ $statementAccountId }}"
                                                        data-period="{{ $statementPeriod }}"
                                                        data-client-name="{{ e($clientName) }}"
                                                        data-email="{{ e($clientEmail) }}"
                                                        @checked(in_array($statementId, $selectedStatementIds, true))
                                                    >
                                                    <span></span>
                                                </label>
                                            </td>

                                            <td>
                                                <div class="bsv2-id-cell">
                                                    <span class="bsv2-id-pill">{{ $clientId }}</span>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="bsv2-client-cell">
                                                    <div class="bsv2-client-name">{{ $clientName }}</div>

                                                    <div class="bsv2-client-grid">
                                                        <div class="bsv2-client-meta">
                                                            <span>RFC</span>
                                                            {{ $clientRfc }}
                                                        </div>

                                                        <div class="bsv2-client-meta">
                                                            <span>Correo</span>
                                                            {{ $clientEmail }}
                                                        </div>

                                                        <div class="bsv2-client-meta">
                                                            <span>Licencia</span>
                                                            {{ $licenseType }}
                                                        </div>

                                                        <div class="bsv2-client-meta">
                                                            <span>Cobro</span>
                                                            {{ $billingMode }}
                                                        </div>

                                                        <div class="bsv2-client-meta">
                                                            <span>Período</span>
                                                            {{ $periodLabel }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="bsv2-money-cell">
                                                    <div class="bsv2-money-main">${{ number_format($previousBalance, 2) }}</div>
                                                    <div class="bsv2-money-sub">Último pago: {{ $lastPaymentLabel }}</div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="bsv2-money-cell">
                                                    <div class="bsv2-money-main">${{ number_format($periodTotal, 2) }}</div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="bsv2-money-cell">
                                                    <div class="bsv2-money-main bsv2-money-main--total">${{ number_format($totalBalance, 2) }}</div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="bsv2-status-cell">
                                                    <span class="bsv2-status {{ $statusClass }}">
                                                        {{ $statusLabel }}
                                                    </span>

                                                    <div class="bsv2-status-sub">
                                                        {{ $statusDatePrefix }}: {{ $statusDateLabel }}
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="bsv2-actions bsv2-actions--pro">
                                                    <div class="bsv2-actions-dropdown">
                                                        <button
                                                            type="button"
                                                            class="bsv2-btn bsv2-btn--primary bsv2-btn--actions bsv2-btn--icon-only"
                                                            data-bsv2-toggle-actions
                                                            data-bsv2-actions-target="{{ $menuId }}"
                                                            aria-expanded="false"
                                                            aria-controls="{{ $menuId }}"
                                                            title="Más opciones"
                                                            aria-label="Más opciones"
                                                        >
                                                            <span class="bsv2-btn__icon" aria-hidden="true">
                                                                <svg viewBox="0 0 24 24" fill="none">
                                                                    <circle cx="5" cy="12" r="1.8" fill="currentColor"/>
                                                                    <circle cx="12" cy="12" r="1.8" fill="currentColor"/>
                                                                    <circle cx="19" cy="12" r="1.8" fill="currentColor"/>
                                                                </svg>
                                                            </span>
                                                        </button>

                                                        <div class="bsv2-actions-menu bsv2-actions-menu--icons" id="{{ $menuId }}">
                                                            <button
                                                                type="button"
                                                                class="bsv2-actions-menu__item bsv2-actions-menu__item--icon {{ $previewUrl ? '' : 'is-disabled' }}"
                                                                @if($previewUrl)
                                                                    data-bsv2-open-preview
                                                                    data-preview-url="{{ $previewUrl }}"
                                                                    data-preview-title="Vista previa · {{ e($clientName) }}"
                                                                    data-preview-period="{{ e($periodLabel) }}"
                                                                @endif
                                                            >
                                                                <span class="bsv2-actions-menu__icon" aria-hidden="true">
                                                                    <svg viewBox="0 0 24 24" fill="none">
                                                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" stroke="currentColor" stroke-width="1.8"/>
                                                                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                                                                    </svg>
                                                                </span>
                                                                <span>Ver</span>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                class="bsv2-actions-menu__item bsv2-actions-menu__item--icon {{ $statusUpdateUrl ? '' : 'is-disabled' }}"
                                                                @if($statusUpdateUrl)
                                                                    data-bsv2-open-edit
                                                                    data-edit-url="{{ $statusUpdateUrl }}"
                                                                    data-statement-id="{{ $statementId }}"
                                                                    data-account-id="{{ $statementAccountId }}"
                                                                    data-period="{{ $statementPeriod }}"
                                                                    data-period-label="{{ e($periodLabel) }}"
                                                                    data-client-name="{{ e($clientName) }}"
                                                                    data-status="{{ $rawStatus }}"
                                                                    data-total="{{ number_format($totalBalance, 2, '.', '') }}"
                                                                    data-last-payment="{{ $lastPaymentDate ? \Illuminate\Support\Carbon::parse($lastPaymentDate)->format('Y-m-d\TH:i') : '' }}"
                                                                    data-payment-method="manual"
                                                                    data-payment-reference=""
                                                                    data-payment-notes=""
                                                                @endif
                                                            >
                                                                <span class="bsv2-actions-menu__icon" aria-hidden="true">
                                                                    <svg viewBox="0 0 24 24" fill="none">
                                                                        <path d="M4 20h4l10-10a2.12 2.12 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                                        <path d="m13.5 6.5 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                                    </svg>
                                                                </span>
                                                                <span>Editar</span>
                                                            </button>

                                                            @if($downloadUrl)
                                                                <a
                                                                    href="{{ $downloadUrl }}"
                                                                    class="bsv2-actions-menu__item bsv2-actions-menu__item--icon"
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                >
                                                                    <span class="bsv2-actions-menu__icon" aria-hidden="true">
                                                                        <svg viewBox="0 0 24 24" fill="none">
                                                                            <path d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                                        </svg>
                                                                    </span>
                                                                    <span>Descargar</span>
                                                                </a>
                                                            @else
                                                                <span class="bsv2-actions-menu__item bsv2-actions-menu__item--icon is-disabled">
                                                                    <span class="bsv2-actions-menu__icon" aria-hidden="true">
                                                                        <svg viewBox="0 0 24 24" fill="none">
                                                                            <path d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                                        </svg>
                                                                    </span>
                                                                    <span>Descargar</span>
                                                                </span>
                                                            @endif

                                                            <button
                                                                type="button"
                                                                class="bsv2-actions-menu__item bsv2-actions-menu__item--icon {{ $emailSendUrl ? '' : 'is-disabled' }}"
                                                                @if($emailSendUrl)
                                                                    data-bsv2-open-email
                                                                    data-email-send-url="{{ $emailSendUrl }}"
                                                                    data-email-preview-url="{{ $emailPreviewUrl ?? '' }}"
                                                                    data-account-id="{{ $statementAccountId }}"
                                                                    data-period="{{ $statementPeriod }}"
                                                                    data-period-label="{{ e($periodLabel) }}"
                                                                    data-client-name="{{ e($clientName) }}"
                                                                    data-email-to="{{ e($defaultRecipients) }}"
                                                                    data-email-subject="{{ e($defaultSubject) }}"
                                                                    data-email-message="{{ e($defaultMessage) }}"
                                                                @endif
                                                            >
                                                                <span class="bsv2-actions-menu__icon" aria-hidden="true">
                                                                    <svg viewBox="0 0 24 24" fill="none">
                                                                        <path d="M22 2 11 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                                        <path d="M22 2 15 22l-4-9-9-4L22 2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                                    </svg>
                                                                </span>
                                                                <span>Enviar</span>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                class="bsv2-actions-menu__item bsv2-actions-menu__item--icon {{ $routeCommercialAgreementSave ? '' : 'is-disabled' }}"
                                                                @if($routeCommercialAgreementSave)
                                                                    data-bsv2-open-commercial-agreement
                                                                    data-account-id="{{ $statementAccountId }}"
                                                                    data-client-name="{{ e($clientName) }}"
                                                                    data-client-rfc="{{ e($clientRfc) }}"
                                                                    data-client-email="{{ e($clientEmail) }}"
                                                                    data-commercial-agreement-url="{{ route($routeCommercialAgreementSave, ['accountId' => $statementAccountId]) }}"
                                                                    data-due-date="{{ data_get($statement, 'due_date') ? \Illuminate\Support\Carbon::parse(data_get($statement, 'due_date'))->format('Y-m-d') : '' }}"
                                                                    data-agreed-due-day="{{ data_get($statement, 'commercial_agreement.agreed_due_day', '') }}"
                                                                    data-reminders-enabled="{{ data_get($statement, 'commercial_agreement.reminders_enabled', 1) ? '1' : '0' }}"
                                                                    data-grace-days="{{ data_get($statement, 'commercial_agreement.grace_days', 0) }}"
                                                                    data-effective-from="{{ data_get($statement, 'commercial_agreement.effective_from', '') }}"
                                                                    data-effective-until="{{ data_get($statement, 'commercial_agreement.effective_until', '') }}"
                                                                    data-apply-forward-indefinitely="{{ data_get($statement, 'commercial_agreement.apply_forward_indefinitely', 0) ? '1' : '0' }}"
                                                                    data-commercial-agreement-status="{{ data_get($statement, 'commercial_agreement.status', 'active') }}"
                                                                    data-commercial-agreement-notes="{{ e((string) data_get($statement, 'commercial_agreement.notes', '')) }}"
                                                                @endif
                                                            >
                                                                <span class="bsv2-actions-menu__icon" aria-hidden="true">
                                                                    <svg viewBox="0 0 24 24" fill="none">
                                                                        <path d="M7 4h8l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                                        <path d="M15 4v4h4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                                        <path d="M8 13h8M8 17h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                                    </svg>
                                                                </span>
                                                                <span>Acuerdo comercial</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                    
                                        <tr>
                                            <td colspan="8">
                                                <div class="bsv2-empty">
                                                    <div class="bsv2-empty__title">Sin registros</div>
                                                    <div class="bsv2-empty__text">
                                                        No hay estados de cuenta disponibles.
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
         </section>
    </div>

        {{-- MODAL · VISTA PREVIA --}}
    <div class="bsv2-modal" id="bsv2-preview-modal" aria-hidden="true">
        <div class="bsv2-modal__backdrop" data-bsv2-close-modal></div>

        <div class="bsv2-modal__dialog bsv2-modal__dialog--preview" role="dialog" aria-modal="true" aria-labelledby="bsv2-preview-title">
            <div class="bsv2-modal__head bsv2-modal__head--preview">
                <div class="bsv2-modal__head-main">
                    <div class="bsv2-modal__eyebrow">Estado de cuenta · Vista previa</div>

                    <h3 class="bsv2-modal__title" id="bsv2-preview-title">
                        Vista previa del estado de cuenta
                    </h3>

                    <p class="bsv2-modal__subtitle" id="bsv2-preview-subtitle">
                        Revisión rápida antes de descargar o enviar.
                    </p>
                </div>

                <div class="bsv2-modal__head-actions">
                    <a
                        href="#"
                        class="bsv2-btn bsv2-btn--soft bsv2-btn--preview-top"
                        id="bsv2-preview-open-tab"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <span class="bsv2-btn__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M14 5h5v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M10 14 19 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M19 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="bsv2-btn__text">Abrir en pestaña</span>
                    </a>

                    <button type="button" class="bsv2-modal__close" data-bsv2-close-modal aria-label="Cerrar">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6 6 18 18M18 6 6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="bsv2-modal__body bsv2-modal__body--preview">
                <div class="bsv2-preview-shell">
                    <div class="bsv2-preview-shell__top">
                        <div class="bsv2-preview-shell__badge">
                            Documento PDF
                        </div>

                        <div class="bsv2-preview-shell__meta">
                            Vista embebida del estado de cuenta generado
                        </div>
                    </div>

                    <div class="bsv2-preview">
                        <div class="bsv2-preview__canvas bsv2-preview__canvas--iframe">
                            <iframe
                                id="bsv2-preview-iframe"
                                class="bsv2-preview__iframe"
                                title="Vista previa PDF estado de cuenta"
                                src="about:blank"
                            ></iframe>

                            <div class="bsv2-preview__placeholder" id="bsv2-preview-placeholder">
                                Selecciona un estado de cuenta para ver su vista previa.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL · EDITAR ESTATUS --}}
    <div class="bsv2-modal" id="bsv2-edit-modal" aria-hidden="true">
        <div class="bsv2-modal__backdrop" data-bsv2-close-modal></div>

        <div class="bsv2-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bsv2-edit-title">
            <div class="bsv2-modal__head">
                <div>
                    <h3 class="bsv2-modal__title" id="bsv2-edit-title">Editar estado de cuenta</h3>
                    <p class="bsv2-modal__subtitle" id="bsv2-edit-subtitle">Actualiza estatus, datos de pago y control administrativo.</p>
                </div>

                <button type="button" class="bsv2-modal__close" data-bsv2-close-modal aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M6 6 18 18M18 6 6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="bsv2-modal__body">
                <form method="POST" action="#" id="bsv2-edit-form" class="bsv2-form">
                    @csrf

                    <input type="hidden" name="account_id" id="bsv2-edit-account-id" value="">
                    <input type="hidden" name="period" id="bsv2-edit-period" value="">

                    <div class="bsv2-form__grid bsv2-form__grid--summary">
                        <div class="bsv2-summary-card">
                            <span class="bsv2-summary-card__label">Cliente</span>
                            <strong class="bsv2-summary-card__value" id="bsv2-edit-client-name">—</strong>
                        </div>

                        <div class="bsv2-summary-card">
                            <span class="bsv2-summary-card__label">Período</span>
                            <strong class="bsv2-summary-card__value" id="bsv2-edit-period-label">—</strong>
                        </div>

                        <div class="bsv2-summary-card">
                            <span class="bsv2-summary-card__label">Saldo total</span>
                            <strong class="bsv2-summary-card__value" id="bsv2-edit-total">$0.00</strong>
                        </div>
                    </div>

                    <div class="bsv2-form__grid">
                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-edit-status">Estatus</label>
                            <select name="status" id="bsv2-edit-status" class="bsv2-control">
                                <option value="pendiente">Pendiente</option>
                                <option value="pagado">Pagado</option>
                                <option value="sin_mov">Sin movimiento</option>
                                <option value="vencido">Vencido</option>
                            </select>
                            <p class="bsv2-help">
                                Vencido aplica cuando el estado de cuenta ya pasó el límite de pago. Sin movimiento evita arrastre de saldo.
                            </p>
                        </div>

                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-edit-payment-method">Método de pago</label>
                            <select name="pay_method" id="bsv2-edit-payment-method" class="bsv2-control">
                                <option value="manual">Manual</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="stripe">Stripe</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="deposito">Depósito</option>
                            </select>
                        </div>

                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-edit-paid-at">Fecha y hora de pago</label>
                            <input type="datetime-local" name="paid_at" id="bsv2-edit-paid-at" class="bsv2-control">
                        </div>

                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-edit-payment-reference">Referencia de pago</label>
                            <input type="text" name="payment_reference" id="bsv2-edit-payment-reference" class="bsv2-control" maxlength="120" placeholder="Ej. transferencia, folio, autorización">
                        </div>
                    </div>

                    <div class="bsv2-field">
                        <label class="bsv2-label" for="bsv2-edit-payment-notes">Notas administrativas</label>
                        <textarea name="payment_notes" id="bsv2-edit-payment-notes" class="bsv2-control bsv2-control--textarea" rows="4" placeholder="Detalle del pago, aclaraciones o motivo del cambio de estatus"></textarea>
                    </div>

                    <div class="bsv2-form__actions">
                        <button type="button" class="bsv2-btn bsv2-btn--ghost" data-bsv2-close-modal>Cancelar</button>
                        <button type="submit" class="bsv2-btn bsv2-btn--primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL · ENVIAR CORREO --}}
    <div class="bsv2-modal" id="bsv2-email-modal" aria-hidden="true">
        <div class="bsv2-modal__backdrop" data-bsv2-close-modal></div>

        <div class="bsv2-modal__dialog bsv2-modal__dialog--email" role="dialog" aria-modal="true" aria-labelledby="bsv2-email-title">
            <div class="bsv2-modal__head">
                <div>
                    <h3 class="bsv2-modal__title" id="bsv2-email-title">Enviar estado de cuenta</h3>
                    <p class="bsv2-modal__subtitle" id="bsv2-email-subtitle">Configura destinatarios, asunto y mensaje antes de enviar.</p>
                </div>

                <button type="button" class="bsv2-modal__close" data-bsv2-close-modal aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M6 6 18 18M18 6 6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="bsv2-modal__body">
                <form method="POST" action="#" id="bsv2-email-form" class="bsv2-form">
                    @csrf

                    <input type="hidden" name="account_id" id="bsv2-email-account-id" value="">
                    <input type="hidden" name="period" id="bsv2-email-period" value="">

                    <div class="bsv2-form__grid bsv2-form__grid--summary">
                        <div class="bsv2-summary-card">
                            <span class="bsv2-summary-card__label">Cliente</span>
                            <strong class="bsv2-summary-card__value" id="bsv2-email-client-name">—</strong>
                        </div>

                        <div class="bsv2-summary-card">
                            <span class="bsv2-summary-card__label">Período</span>
                            <strong class="bsv2-summary-card__value" id="bsv2-email-period-label">—</strong>
                        </div>
                    </div>

                    <div class="bsv2-field">
                        <label class="bsv2-label" for="bsv2-email-to">Correos destinatarios</label>
                        <textarea
                            name="to"
                            id="bsv2-email-to"
                            class="bsv2-control bsv2-control--textarea bsv2-control--compact"
                            rows="3"
                            placeholder="correo1@empresa.com, correo2@empresa.com"
                        ></textarea>
                        <p class="bsv2-help">Puedes editar y agregar varios correos separados por coma.</p>
                    </div>

                    <div class="bsv2-field">
                        <label class="bsv2-label" for="bsv2-email-subject">Asunto</label>
                        <input type="text" name="subject" id="bsv2-email-subject" class="bsv2-control" maxlength="180">
                    </div>

                    <div class="bsv2-field">
                        <label class="bsv2-label" for="bsv2-email-message">Mensaje</label>
                        <textarea
                            name="message"
                            id="bsv2-email-message"
                            class="bsv2-control bsv2-control--textarea"
                            rows="8"
                            placeholder="Escribe el cuerpo del correo"
                        ></textarea>
                    </div>

                    <div class="bsv2-email-preview-box">
                        <div class="bsv2-email-preview-box__title">Vista rápida del envío</div>
                        <div class="bsv2-email-preview-box__text">
                            El correo se enviará con el estado de cuenta del período seleccionado y podrá incluir el PDF del estado de cuenta.
                        </div>
                    </div>

                    <div class="bsv2-form__actions">
                        <button type="button" class="bsv2-btn bsv2-btn--ghost" data-bsv2-close-modal>Cancelar</button>
                        <button type="submit" class="bsv2-btn bsv2-btn--primary">Enviar estado de cuenta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
        {{-- MODAL · ADELANTAR PAGOS --}}
    <div class="bsv2-modal" id="bsv2-advance-modal" aria-hidden="true">
        <div class="bsv2-modal__backdrop" data-bsv2-close-modal></div>

        <div class="bsv2-modal__dialog bsv2-modal__dialog--xl" role="dialog" aria-modal="true" aria-labelledby="bsv2-advance-title">
            <div class="bsv2-modal__head">
                <div>
                    <h3 class="bsv2-modal__title" id="bsv2-advance-title">Adelantar pagos / aplicar pagos por varios meses</h3>
                    <p class="bsv2-modal__subtitle">Selecciona cliente, agrega meses con el botón + y define si el pago es completo o parcial por período.</p>
                </div>

                <button type="button" class="bsv2-modal__close" data-bsv2-close-modal aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M6 6 18 18M18 6 6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="bsv2-modal__body">
                <form id="bsv2-advance-form" class="bsv2-form">
                    <div class="bsv2-form__grid">
                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-advance-client">Cliente / cuenta</label>
                            <input type="text" id="bsv2-advance-client" class="bsv2-control" placeholder="Buscar cliente, ID o RFC">
                        </div>

                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-advance-payment-date">Fecha de pago</label>
                            <input type="date" id="bsv2-advance-payment-date" class="bsv2-control" value="{{ now()->toDateString() }}">
                        </div>

                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-advance-method">Método de pago</label>
                            <select id="bsv2-advance-method" class="bsv2-control">
                                <option value="transferencia">Transferencia</option>
                                <option value="stripe">Stripe</option>
                                <option value="deposito">Depósito</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>

                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-advance-reference">Referencia</label>
                            <input type="text" id="bsv2-advance-reference" class="bsv2-control" placeholder="Folio, transferencia, autorización">
                        </div>
                    </div>

                    <div class="bsv2-pay-builder">
                        <div class="bsv2-pay-builder__head">
                            <div>
                                <div class="bsv2-pay-builder__title">Períodos a aplicar</div>
                                <div class="bsv2-pay-builder__text">Puedes registrar meses completos o montos parciales por período.</div>
                            </div>

                            <button type="button" class="bsv2-btn bsv2-btn--primary" id="bsv2-add-advance-row">
                                <span class="bsv2-btn__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    </svg>
                                </span>
                                <span class="bsv2-btn__text">Agregar mes</span>
                            </button>
                        </div>

                        <div class="bsv2-pay-builder__rows" id="bsv2-advance-rows">
                            <div class="bsv2-pay-line">
                                <div class="bsv2-pay-line__grid">
                                    <div class="bsv2-field">
                                        <label class="bsv2-label">Período</label>
                                        <input type="month" class="bsv2-control" name="advance_period[]">
                                    </div>

                                    <div class="bsv2-field">
                                        <label class="bsv2-label">Tipo</label>
                                        <select class="bsv2-control" name="advance_type[]">
                                            <option value="full">Pago completo</option>
                                            <option value="partial">Pago parcial</option>
                                        </select>
                                    </div>

                                    <div class="bsv2-field">
                                        <label class="bsv2-label">Monto aplicado</label>
                                        <input type="number" step="0.01" min="0" class="bsv2-control" name="advance_amount[]" placeholder="0.00">
                                    </div>

                                    <div class="bsv2-field">
                                        <label class="bsv2-label">Notas</label>
                                        <input type="text" class="bsv2-control" name="advance_notes[]" placeholder="Detalle del pago">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bsv2-history-card">
                        <div class="bsv2-history-card__title">Historial de pagos del cliente</div>
                        <div class="bsv2-history-card__text">Aquí cargaremos el historial real y los períodos ya cubiertos para evitar duplicados.</div>
                    </div>

                    <div class="bsv2-form__actions">
                        <button type="button" class="bsv2-btn bsv2-btn--ghost" data-bsv2-close-modal>Cancelar</button>
                        <button type="button" class="bsv2-btn bsv2-btn--primary" id="bsv2-confirm-advance-payment">Confirmar registro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL · PAGOS MASIVOS --}}
    <div class="bsv2-modal" id="bsv2-bulk-payments-modal" aria-hidden="true">
        <div class="bsv2-modal__backdrop" data-bsv2-close-modal></div>

        <div class="bsv2-modal__dialog bsv2-modal__dialog--xl" role="dialog" aria-modal="true" aria-labelledby="bsv2-bulk-payments-title">
            <div class="bsv2-modal__head">
                <div>
                    <h3 class="bsv2-modal__title" id="bsv2-bulk-payments-title">Registrar pagos masivos</h3>
                    <p class="bsv2-modal__subtitle">Captura varios pagos manuales de distintos clientes sin abrir uno por uno.</p>
                </div>

                <button type="button" class="bsv2-modal__close" data-bsv2-close-modal aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M6 6 18 18M18 6 6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="bsv2-modal__body">
                <form id="bsv2-bulk-payments-form" class="bsv2-form">
                    <div class="bsv2-pay-builder">
                        <div class="bsv2-pay-builder__head">
                            <div>
                                <div class="bsv2-pay-builder__title">Pagos a registrar</div>
                                <div class="bsv2-pay-builder__text">Agrega filas por cliente. Después se validará y confirmará el registro.</div>
                            </div>

                            <button type="button" class="bsv2-btn bsv2-btn--primary" id="bsv2-add-bulk-payment-row">
                                <span class="bsv2-btn__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    </svg>
                                </span>
                                <span class="bsv2-btn__text">Agregar fila</span>
                            </button>
                        </div>

                        <div class="bsv2-pay-builder__rows" id="bsv2-bulk-payments-rows">
                            <div class="bsv2-pay-line">
                                <div class="bsv2-pay-line__grid bsv2-pay-line__grid--bulk">
                                    <div class="bsv2-field">
                                        <label class="bsv2-label">Cliente / ID</label>
                                        <input type="text" class="bsv2-control" name="bulk_client[]" placeholder="Cuenta, RFC o nombre">
                                    </div>

                                    <div class="bsv2-field">
                                        <label class="bsv2-label">Período</label>
                                        <input type="month" class="bsv2-control" name="bulk_period[]">
                                    </div>

                                    <div class="bsv2-field">
                                        <label class="bsv2-label">Monto</label>
                                        <input type="number" step="0.01" min="0" class="bsv2-control" name="bulk_amount[]" placeholder="0.00">
                                    </div>

                                    <div class="bsv2-field">
                                        <label class="bsv2-label">Método</label>
                                        <select class="bsv2-control" name="bulk_method[]">
                                            <option value="transferencia">Transferencia</option>
                                            <option value="deposito">Depósito</option>
                                            <option value="efectivo">Efectivo</option>
                                            <option value="stripe">Stripe</option>
                                            <option value="manual">Manual</option>
                                        </select>
                                    </div>

                                    <div class="bsv2-field">
                                        <label class="bsv2-label">Referencia</label>
                                        <input type="text" class="bsv2-control" name="bulk_reference[]" placeholder="Folio o referencia">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bsv2-confirm-card">
                        <div class="bsv2-confirm-card__title">Confirmación</div>
                        <div class="bsv2-confirm-card__text">Antes de guardar, debe validarse que cada pago corresponda al cliente y período correcto.</div>
                    </div>

                    <div class="bsv2-form__actions">
                        <button type="button" class="bsv2-btn bsv2-btn--ghost" data-bsv2-close-modal>Cancelar</button>
                        <button type="button" class="bsv2-btn bsv2-btn--primary" id="bsv2-confirm-bulk-payments">Confirmar pagos</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
            
                                                {{-- MODAL · ACUERDO COMERCIAL --}}
    <div class="bsv2-modal" id="bsv2-commercial-agreement-modal" aria-hidden="true">
        <div class="bsv2-modal__backdrop" data-bsv2-close-modal></div>

        <div class="bsv2-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bsv2-commercial-agreement-title">
            <div class="bsv2-modal__head">
                <div>
                    <h3 class="bsv2-modal__title" id="bsv2-commercial-agreement-title">Acuerdo comercial</h3>
                    <p class="bsv2-modal__subtitle" id="bsv2-commercial-agreement-subtitle">
                        Configura una fecha de pago acordada para este cliente sin cambiar la fecha general de envío.
                    </p>
                </div>

                <button type="button" class="bsv2-modal__close" data-bsv2-close-modal aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M6 6 18 18M18 6 6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="bsv2-modal__body">
                <form method="POST" action="#" id="bsv2-commercial-agreement-form" class="bsv2-form">
                    @csrf

                    <input type="hidden" name="account_id" id="bsv2-commercial-account-id" value="">

                    <div class="bsv2-form__grid bsv2-form__grid--summary">
                        <div class="bsv2-summary-card">
                            <span class="bsv2-summary-card__label">Cliente</span>
                            <strong class="bsv2-summary-card__value" id="bsv2-commercial-client-name">—</strong>
                        </div>

                        <div class="bsv2-summary-card">
                            <span class="bsv2-summary-card__label">RFC</span>
                            <strong class="bsv2-summary-card__value" id="bsv2-commercial-client-rfc">—</strong>
                        </div>

                        <div class="bsv2-summary-card">
                            <span class="bsv2-summary-card__label">Vencimiento actual</span>
                            <strong class="bsv2-summary-card__value" id="bsv2-commercial-current-due-date">—</strong>
                        </div>
                    </div>

                    <div class="bsv2-form__grid">
                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-commercial-agreed-due-day">Día acordado de pago</label>
                            <input
                                type="number"
                                min="1"
                                max="31"
                                name="agreed_due_day"
                                id="bsv2-commercial-agreed-due-day"
                                class="bsv2-control"
                                placeholder="Ej. 28"
                            >
                            <p class="bsv2-help">
                                Ejemplo: si el envío sigue el día 5 pero el cliente paga el 28, aquí defines el 28.
                            </p>
                        </div>

                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-commercial-grace-days">Días de gracia</label>
                            <input
                                type="number"
                                min="0"
                                max="31"
                                name="grace_days"
                                id="bsv2-commercial-grace-days"
                                class="bsv2-control"
                                value="0"
                            >
                        </div>

                        <div class="bsv2-field bsv2-field--full">
                            <label class="bsv2-label" for="bsv2-commercial-apply-forward-indefinitely">Aplicación del acuerdo</label>

                            <label class="bsv2-check bsv2-check--stack">
                                <input
                                    type="checkbox"
                                    name="apply_forward_indefinitely"
                                    id="bsv2-commercial-apply-forward-indefinitely"
                                    value="1"
                                >
                                <span class="bsv2-check__box"></span>

                                <div class="bsv2-check__content">
                                    <div class="bsv2-check__title">Aplicar desde hoy en adelante todos los meses</div>
                                    <div class="bsv2-check__text">
                                        Al activar esta opción, el acuerdo comenzará hoy y quedará vigente sin fecha final hasta que lo edites o desactives.
                                    </div>
                                </div>
                            </label>
                        </div>

                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-commercial-effective-from">Vigente desde</label>
                            <input
                                type="date"
                                name="effective_from"
                                id="bsv2-commercial-effective-from"
                                class="bsv2-control"
                            >
                        </div>

                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-commercial-effective-until">Vigente hasta</label>
                            <input
                                type="date"
                                name="effective_until"
                                id="bsv2-commercial-effective-until"
                                class="bsv2-control"
                            >
                            <p class="bsv2-help">
                                Si activas la opción de aplicar hacia adelante, este campo quedará sin fecha final.
                            </p>
                        </div>

                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-commercial-status">Estatus del acuerdo</label>
                            <select name="status" id="bsv2-commercial-status" class="bsv2-control">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>

                        <div class="bsv2-field">
                            <label class="bsv2-label" for="bsv2-commercial-reminders-enabled">Recordatorios</label>
                            <select name="reminders_enabled" id="bsv2-commercial-reminders-enabled" class="bsv2-control">
                                <option value="1">Sí, respetando fecha acordada</option>
                                <option value="0">No enviar recordatorios automáticos</option>
                            </select>
                        </div>
                    </div>

                    <div class="bsv2-field">
                        <label class="bsv2-label" for="bsv2-commercial-notes">Notas del acuerdo</label>
                        <textarea
                            name="notes"
                            id="bsv2-commercial-notes"
                            class="bsv2-control bsv2-control--textarea"
                            rows="5"
                            placeholder="Detalle del acuerdo comercial con el cliente"
                        ></textarea>
                    </div>

                    <div class="bsv2-email-preview-box">
                        <div class="bsv2-email-preview-box__title">Importante</div>
                        <div class="bsv2-email-preview-box__text">
                            El estado de cuenta puede seguir enviándose en la fecha general, pero no deberá marcarse como vencido ni generar recordatorios antes de la fecha acordada aquí.
                        </div>
                    </div>

                    <div class="bsv2-form__actions">
                        <button type="button" class="bsv2-btn bsv2-btn--ghost" data-bsv2-close-modal>Cancelar</button>
                        <button type="submit" class="bsv2-btn bsv2-btn--primary">Guardar acuerdo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/billing-statements-v2.js') }}?v={{ $jsVer }}"></script>
@endpush