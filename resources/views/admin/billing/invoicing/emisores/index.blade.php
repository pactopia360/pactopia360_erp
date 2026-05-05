@extends('layouts.admin')

@section('title', 'Facturación · Emisores')
@section('layout', 'full')
@section('contentLayout', 'full')
@section('pageClass', 'billing-emisores-index-page')

@php
    $cssPath = public_path('assets/admin/css/billing-statements-v2.css');
    $jsPath  = public_path('assets/admin/js/billing-statements-v2.js');

    $cssVer = is_file($cssPath) ? filemtime($cssPath) : time();
    $jsVer  = is_file($jsPath) ? filemtime($jsPath) : time();

    $rows = $rows ?? collect();
    $q = (string) ($q ?? '');

    $totalRows = method_exists($rows, 'total')
        ? (int) $rows->total()
        : (is_countable($rows) ? count($rows) : 0);

    $visibleRows = method_exists($rows, 'count') ? (int) $rows->count() : count($rows);

    $collection = method_exists($rows, 'getCollection') ? $rows->getCollection() : collect($rows);

    $activeRows = $collection->filter(fn ($row) => strtolower((string) ($row->status ?? '')) === 'active')->count();
    $inactiveRows = $collection->filter(fn ($row) => strtolower((string) ($row->status ?? '')) !== 'active')->count();
    $remoteRows = $collection->filter(fn ($row) => filled($row->ext_id ?? null))->count();
    $withoutRemoteRows = max(0, $visibleRows - $remoteRows);

    $withSeriesRows = $collection->filter(function ($row) {
        return !empty($row->series_decoded) && is_array($row->series_decoded) && count($row->series_decoded) > 0;
    })->count();

    $withCertRows = $collection->filter(function ($row) {
        return !empty($row->certificados_decoded) && is_array($row->certificados_decoded);
    })->count();

    $routeIndex = route('admin.billing.invoicing.emisores.index');
    $routeCreate = route('admin.billing.invoicing.emisores.create');
    $routeSync = route('admin.billing.invoicing.emisores.sync_facturotopia');

    $statusUiMap = [
        'active' => ['label' => 'Activo', 'class' => 'is-paid'],
        'activo' => ['label' => 'Activo', 'class' => 'is-paid'],
        'inactive' => ['label' => 'Inactivo', 'class' => 'is-overdue'],
        'inactivo' => ['label' => 'Inactivo', 'class' => 'is-overdue'],
        'pending' => ['label' => 'Pendiente', 'class' => 'is-pending'],
        'pendiente' => ['label' => 'Pendiente', 'class' => 'is-pending'],
    ];
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/billing-statements-v2.css') }}?v={{ $cssVer }}">
<link rel="stylesheet" href="{{ asset('assets/admin/css/billing-emisores.css') }}?v={{ time() }}">
@endpush

@section('content')
<div class="bsv2-page" data-bsv2-root>
    <div class="bsv2-wrap">
        <section class="bsv2-header-clean" aria-label="Encabezado de emisores">
            <div class="bsv2-header-clean__content">
                <div class="bsv2-header-clean__text">
                    <h1 class="bsv2-title">Emisores</h1>
                    <p class="bsv2-subtitle">
                        Alta, edición, validación y sincronización de emisores fiscales conectados a Facturotopía.
                    </p>
                </div>

                <div class="bsv2-header-clean__meta">
                    <div class="bsv2-kpi">
                        <span class="bsv2-kpi__label">Total</span>
                        <strong class="bsv2-kpi__value">{{ number_format($totalRows) }}</strong>
                    </div>

                    <div class="bsv2-kpi">
                        <span class="bsv2-kpi__label">Facturotopía</span>
                        <strong class="bsv2-kpi__value">{{ number_format($remoteRows) }}</strong>
                    </div>
                </div>
            </div>
        </section>

        @if(session('ok') || session('warning') || $errors->any())
            <section class="bsv2-list-card bsv2-list-card--accordion be-alert-card">
                <div class="bsv2-list-card__accordion">
                    <div class="be-alert-stack">
                        @if(session('ok'))
                            <div class="be-alert be-alert--success">
                                <strong>Listo</strong>
                                <span>{{ session('ok') }}</span>
                            </div>
                        @endif

                        @if(session('warning'))
                            <div class="be-alert be-alert--warning">
                                <strong>Atención</strong>
                                <span>{{ session('warning') }}</span>
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="be-alert be-alert--danger">
                                <strong>Revisar</strong>
                                <span>{{ $errors->first() }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif
        
        <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Resumen de emisores">
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
                        <span class="bsv2-list-card__summary-meta">Salud fiscal, conexión remota y datos operativos</span>
                    </span>

                    <span class="bsv2-list-card__summary-action" aria-hidden="true">
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                    </span>
                </button>

                <div class="bsv2-list-card__content" id="bsv2-kpis-content" hidden>
                    <div class="bsv2-kpi-strip">
                        <article class="bsv2-kpi-card">
                            <span class="bsv2-kpi-card__label">Visibles</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($visibleRows) }}</strong>
                            <span class="bsv2-kpi-card__meta">Página actual</span>
                        </article>

                        <article class="bsv2-kpi-card is-paid">
                            <span class="bsv2-kpi-card__label">Activos</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($activeRows) }}</strong>
                            <span class="bsv2-kpi-card__meta">Listos para facturar</span>
                        </article>

                        <article class="bsv2-kpi-card is-overdue">
                            <span class="bsv2-kpi-card__label">Inactivos</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($inactiveRows) }}</strong>
                            <span class="bsv2-kpi-card__meta">Requieren revisión</span>
                        </article>

                        <article class="bsv2-kpi-card is-partial">
                            <span class="bsv2-kpi-card__label">Con ext_id</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($remoteRows) }}</strong>
                            <span class="bsv2-kpi-card__meta">Conectados</span>
                        </article>

                        <article class="bsv2-kpi-card is-pending">
                            <span class="bsv2-kpi-card__label">Sin ext_id</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($withoutRemoteRows) }}</strong>
                            <span class="bsv2-kpi-card__meta">Pendientes remoto</span>
                        </article>

                        <article class="bsv2-kpi-card">
                            <span class="bsv2-kpi-card__label">Series</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($withSeriesRows) }}</strong>
                            <span class="bsv2-kpi-card__meta">Configuradas</span>
                        </article>

                        <article class="bsv2-kpi-card">
                            <span class="bsv2-kpi-card__label">Certificados</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($withCertRows) }}</strong>
                            <span class="bsv2-kpi-card__meta">Registrados</span>
                        </article>
                    </div>

                    <div class="bsv2-mini-analytics">
                        <article class="bsv2-mini-chart-card">
                            <div class="bsv2-mini-chart-card__head">
                                <div>
                                    <div class="bsv2-mini-chart-card__title">IA · Revisión rápida</div>
                                    <div class="bsv2-mini-chart-card__subtitle">Checklist para detectar riesgos antes de timbrar</div>
                                </div>
                            </div>

                            <div class="bsv2-email-preview-box">
                                <div class="bsv2-email-preview-box__title">Sugerencias</div>
                                <div class="bsv2-email-preview-box__text">
                                    Prioriza emisores sin ext_id, sin certificados, sin series o inactivos. Después agregamos endpoint IA para validar RFC, régimen, CP fiscal y consistencia SAT.
                                </div>
                            </div>
                        </article>

                        <article class="bsv2-mini-chart-card">
                            <div class="bsv2-mini-chart-card__head">
                                <div>
                                    <div class="bsv2-mini-chart-card__title">Facturotopía</div>
                                    <div class="bsv2-mini-chart-card__subtitle">Sincronización local/remota</div>
                                </div>
                                <div class="bsv2-mini-chart-card__badge">{{ number_format($remoteRows) }}</div>
                            </div>

                            <div class="bsv2-progress-dual">
                                <div class="bsv2-progress-dual__item">
                                    <div class="bsv2-progress-dual__meta">
                                        <span>Conectados</span>
                                        <strong>{{ number_format($remoteRows) }}</strong>
                                    </div>
                                    <div class="bsv2-progress-dual__track">
                                        <span class="bsv2-progress-dual__fill is-paid" style="width: {{ $visibleRows > 0 ? min(100, round(($remoteRows / $visibleRows) * 100)) : 0 }}%"></span>
                                    </div>
                                </div>

                                <div class="bsv2-progress-dual__item">
                                    <div class="bsv2-progress-dual__meta">
                                        <span>Pendientes</span>
                                        <strong>{{ number_format($withoutRemoteRows) }}</strong>
                                    </div>
                                    <div class="bsv2-progress-dual__track">
                                        <span class="bsv2-progress-dual__fill is-pending" style="width: {{ $visibleRows > 0 ? min(100, round(($withoutRemoteRows / $visibleRows) * 100)) : 0 }}%"></span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Filtros de emisores">
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
                        <span class="bsv2-list-card__summary-meta">Búsqueda, alta, navegación y sincronización</span>
                    </span>

                    <span class="bsv2-list-card__summary-action" aria-hidden="true">
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                    </span>
                </button>

                <div class="bsv2-list-card__content" id="bsv2-filters-content" hidden>
                    <form method="GET" action="{{ $routeIndex }}" class="bsv2-filters-form" id="bsv2-filters-form">
                        <div class="bsv2-filters-grid">
                            <div class="bsv2-filter-item bsv2-filter-item--search">
                                <label class="bsv2-filter-label" for="q">Buscar</label>
                                <div class="bsv2-filter-control-wrap">
                                    <span class="bsv2-filter-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="1.8"/>
                                            <path d="M20 20l-4.2-4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    <input
                                        type="text"
                                        name="q"
                                        id="q"
                                        class="bsv2-filter-control bsv2-filter-control--with-icon"
                                        value="{{ $q }}"
                                        placeholder="RFC, razón social, correo, grupo, status, cuenta o ext_id..."
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="bsv2-bulk-toolbar">
                            <div class="bsv2-bulk-toolbar__group">
                                <div class="bsv2-bulk-chip">
                                    <span class="bsv2-bulk-chip__label">Filtro</span>
                                    <strong class="bsv2-bulk-chip__value">{{ $q !== '' ? $q : 'Todos' }}</strong>
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

                                <a href="{{ $routeIndex }}" class="bsv2-btn bsv2-btn--ghost bsv2-btn--icon-only" data-floating-label="Limpiar" aria-label="Limpiar">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </a>

                                <a href="{{ route('admin.billing.invoicing.dashboard') }}" class="bsv2-btn bsv2-btn--soft bsv2-btn--icon-only" data-floating-label="Dashboard" aria-label="Dashboard">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M4 13h7V4H4v9Zm9 7h7V4h-7v16ZM4 20h7v-5H4v5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </a>

                                <a href="{{ route('admin.billing.invoicing.receptores.index') }}" class="bsv2-btn bsv2-btn--soft bsv2-btn--icon-only" data-floating-label="Receptores" aria-label="Receptores">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M16 11a4 4 0 1 0-8 0" stroke="currentColor" stroke-width="1.8"/>
                                            <path d="M4 21a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                </a>

                                <a href="{{ route('admin.billing.invoicing.settings.index') }}" class="bsv2-btn bsv2-btn--soft bsv2-btn--icon-only" data-floating-label="Configuración" aria-label="Configuración">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z" stroke="currentColor" stroke-width="1.8"/>
                                            <path d="M19.4 15a1.8 1.8 0 0 0 .36 1.98l.05.05a2.2 2.2 0 0 1-3.11 3.11l-.05-.05a1.8 1.8 0 0 0-1.98-.36 1.8 1.8 0 0 0-1.09 1.65V21a2.2 2.2 0 0 1-4.4 0v-.08A1.8 1.8 0 0 0 8.1 19.27a1.8 1.8 0 0 0-1.98.36l-.05.05a2.2 2.2 0 0 1-3.11-3.11l.05-.05A1.8 1.8 0 0 0 3.37 15a1.8 1.8 0 0 0-1.65-1.09H1.6a2.2 2.2 0 0 1 0-4.4h.08A1.8 1.8 0 0 0 3.37 8a1.8 1.8 0 0 0-.36-1.98l-.05-.05a2.2 2.2 0 0 1 3.11-3.11l.05.05A1.8 1.8 0 0 0 8.1 3.27h.01A1.8 1.8 0 0 0 9.2 1.6V1.5a2.2 2.2 0 0 1 4.4 0v.08a1.8 1.8 0 0 0 1.09 1.65 1.8 1.8 0 0 0 1.98-.36l.05-.05a2.2 2.2 0 0 1 3.11 3.11l-.05.05A1.8 1.8 0 0 0 19.4 8v.01a1.8 1.8 0 0 0 1.65 1.09h.08a2.2 2.2 0 0 1 0 4.4h-.08A1.8 1.8 0 0 0 19.4 15Z" stroke="currentColor" stroke-width="1.2"/>
                                        </svg>
                                    </span>
                                </a>

                                <a href="{{ $routeCreate }}" class="bsv2-btn bsv2-btn--primary bsv2-btn--icon-only" data-floating-label="Nuevo emisor" aria-label="Nuevo emisor">
                                    <span class="bsv2-btn__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                </a>

                                <form method="POST" action="{{ $routeSync }}" style="display:inline-flex;margin:0;">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="bsv2-btn bsv2-btn--primary bsv2-btn--icon-only"
                                        data-floating-label="Sincronizar"
                                        aria-label="Sincronizar Facturotopía"
                                        onclick="return confirm('¿Sincronizar emisores con Facturotopía?')"
                                    >
                                        <span class="bsv2-btn__icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="M20 11a8 8 0 0 0-14.6-4.5L4 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M4 4v4h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M4 13a8 8 0 0 0 14.6 4.5L20 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M20 20v-4h-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Listado de emisores">
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
                            {{ number_format($totalRows) }} registros · {{ number_format($visibleRows) }} visibles
                        </span>
                    </span>

                    <span class="bsv2-list-card__summary-action" aria-hidden="true">
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                    </span>
                </button>

                <div class="bsv2-list-card__content" id="bsv2-list-content">
                    <div class="bsv2-table-wrap">
                        <table class="bsv2-table bsv2-table--compact">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Emisor</th>
                                    <th>Cuenta</th>
                                    <th>Fiscal</th>
                                    <th>Facturotopía</th>
                                    <th>Operación</th>
                                    <th>Estatus</th>
                                    <th class="bsv2-col-actions"></th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($rows as $row)
                                    @php
                                        $statusRaw = strtolower(trim((string) ($row->status ?? 'inactive')));
                                        $statusData = $statusUiMap[$statusRaw] ?? [
                                            'label' => filled($row->status ?? null) ? (string) $row->status : 'Sin status',
                                            'class' => 'is-info',
                                        ];

                                        $direccion = is_array($row->direccion_decoded ?? null) ? $row->direccion_decoded : [];
                                        $series = is_array($row->series_decoded ?? null) ? $row->series_decoded : [];
                                        $certificados = is_array($row->certificados_decoded ?? null) ? $row->certificados_decoded : [];

                                        $menuId = 'emisor-actions-' . (int) $row->id;
                                    @endphp

                                    <tr class="bsv2-row">
                                        <td>
                                            <div class="bsv2-id-cell">
                                                <span class="bsv2-id-pill">#{{ (int) $row->id }}</span>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="bsv2-client-cell">
                                                <div class="bsv2-client-name">
                                                    {{ $row->razon_social ?? $row->nombre_comercial ?? 'Emisor sin nombre' }}
                                                </div>

                                                <div class="bsv2-client-grid">
                                                    <div class="bsv2-client-meta">
                                                        <span>RFC</span>{{ $row->rfc ?? '—' }}
                                                    </div>
                                                    <div class="bsv2-client-meta">
                                                        <span>Correo</span>{{ $row->email ?? '—' }}
                                                    </div>
                                                    <div class="bsv2-client-meta">
                                                        <span>Comercial</span>{{ $row->nombre_comercial ?? '—' }}
                                                    </div>
                                                    <div class="bsv2-client-meta">
                                                        <span>Grupo</span>{{ $row->grupo ?? '—' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="bsv2-client-cell">
                                                <div class="bsv2-client-name">{{ $row->cuenta_label ?? 'Sin cuenta' }}</div>
                                                <div class="bsv2-client-grid">
                                                    <div class="bsv2-client-meta">
                                                        <span>ID</span>{{ $row->cuenta_id ?? '—' }}
                                                    </div>
                                                    <div class="bsv2-client-meta">
                                                        <span>Origen</span>Local
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="bsv2-client-cell">
                                                <div class="bsv2-client-grid">
                                                    <div class="bsv2-client-meta">
                                                        <span>Régimen</span>{{ $row->regimen_fiscal ?? '—' }}
                                                    </div>
                                                    <div class="bsv2-client-meta">
                                                        <span>CP</span>{{ $direccion['cp'] ?? '—' }}
                                                    </div>
                                                    <div class="bsv2-client-meta">
                                                        <span>Estado</span>{{ $direccion['estado'] ?? '—' }}
                                                    </div>
                                                    <div class="bsv2-client-meta">
                                                        <span>Municipio</span>{{ $direccion['municipio'] ?? '—' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="bsv2-client-cell">
                                                <div class="bsv2-client-grid">
                                                    <div class="bsv2-client-meta">
                                                        <span>ext_id</span>{{ $row->ext_id ?? '—' }}
                                                    </div>
                                                    <div class="bsv2-client-meta">
                                                        <span>Sync</span>{{ filled($row->ext_id ?? null) ? 'Conectado' : 'Pendiente' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="bsv2-client-cell">
                                                <div class="bsv2-client-grid">
                                                    <div class="bsv2-client-meta">
                                                        <span>Series</span>{{ count($series) > 0 ? count($series) . ' serie(s)' : '—' }}
                                                    </div>
                                                    <div class="bsv2-client-meta">
                                                        <span>Cert.</span>{{ count($certificados) > 0 ? 'Sí' : '—' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="bsv2-status-cell">
                                                <span class="bsv2-status {{ $statusData['class'] }}">
                                                    {{ $statusData['label'] }}
                                                </span>
                                                <div class="bsv2-status-sub">
                                                    {{ filled($row->updated_at ?? null) ? \Illuminate\Support\Carbon::parse($row->updated_at)->format('d/m/Y') : 'Sin fecha' }}
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
                                                        <a href="{{ route('admin.billing.invoicing.emisores.edit', (int) $row->id) }}" class="bsv2-actions-menu__item bsv2-actions-menu__item--icon">
                                                            <span class="bsv2-actions-menu__icon" aria-hidden="true">
                                                                <svg viewBox="0 0 24 24" fill="none">
                                                                    <path d="M4 20h4l10-10a2.12 2.12 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                                    <path d="m13.5 6.5 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                                </svg>
                                                            </span>
                                                            <span>Editar</span>
                                                        </a>

                                                        <button
                                                            type="button"
                                                            class="bsv2-actions-menu__item bsv2-actions-menu__item--icon"
                                                            onclick="alert('IA: siguiente paso. Aquí validaremos RFC, régimen, CP fiscal, certificados, series y conexión Facturotopía.')"
                                                        >
                                                            <span class="bsv2-actions-menu__icon" aria-hidden="true">
                                                                <svg viewBox="0 0 24 24" fill="none">
                                                                    <path d="M12 3v3M12 18v3M4.64 4.64l2.12 2.12M17.24 17.24l2.12 2.12M3 12h3M18 12h3M4.64 19.36l2.12-2.12M17.24 6.76l2.12-2.12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                                    <path d="M9 12a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z" stroke="currentColor" stroke-width="1.8"/>
                                                                </svg>
                                                            </span>
                                                            <span>IA fiscal</span>
                                                        </button>

                                                        <form
                                                            method="POST"
                                                            action="{{ route('admin.billing.invoicing.emisores.destroy', (int) $row->id) }}"
                                                            class="bsv2-actions-menu__form"
                                                            onsubmit="return confirm('¿Eliminar este emisor? Solo se dará baja local.');"
                                                        >
                                                            @csrf
                                                            @method('DELETE')

                                                            <button type="submit" class="bsv2-actions-menu__item bsv2-actions-menu__item--icon bsv2-actions-menu__button">
                                                                <span class="bsv2-actions-menu__icon" aria-hidden="true">
                                                                    <svg viewBox="0 0 24 24" fill="none">
                                                                        <path d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                                    </svg>
                                                                </span>
                                                                <span>Eliminar</span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8">
                                            <div class="bsv2-empty">
                                                <div class="bsv2-empty__title">Sin emisores</div>
                                                <div class="bsv2-empty__text">
                                                    Aún no hay emisores registrados o el filtro no encontró resultados.
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

        @if(method_exists($rows, 'links'))
            <div class="be-pagination">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/billing-statements-v2.js') }}?v={{ $jsVer }}"></script>
@endpush