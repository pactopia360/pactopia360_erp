{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\invoicing\invoices\index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Facturación · Facturas emitidas')
@section('contentLayout', 'full')
@section('pageClass', 'billing-invoices-index-page billing-invoices-index-page--compact')

@php
    $rows   = $rows ?? collect();
    $error  = $error ?? null;
    $status = (string) ($status ?? '');
    $period = (string) ($period ?? '');
    $q      = (string) ($q ?? '');

    $statusLabel = static function ($value): string {
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'draft'     => 'Borrador',
            'pending'   => 'Pendiente',
            'generated' => 'Generada',
            'stamped'   => 'Timbrada',
            'sent'      => 'Enviada',
            'paid'      => 'Pagada',
            'cancelled', 'canceled' => 'Cancelada',
            'error'     => 'Error',
            'active'    => 'Activa',
            'issued'    => 'Emitida',
            default     => $value !== null && $value !== '' ? ucfirst((string) $value) : '—',
        };
    };

    $statusClass = static function ($value): string {
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'draft'     => 'is-draft',
            'pending'   => 'is-pending',
            'generated' => 'is-generated',
            'stamped'   => 'is-stamped',
            'sent'      => 'is-sent',
            'paid'      => 'is-paid',
            'cancelled', 'canceled' => 'is-cancelled',
            'error'     => 'is-error',
            'active'    => 'is-active',
            'issued'    => 'is-issued',
            default     => 'is-default',
        };
    };

    $money = static function ($value): string {
        if ($value === null || $value === '') {
            return '—';
        }
        return '$' . number_format((float) $value, 2);
    };

    $fmtDate = static function ($value): string {
        if (empty($value)) return '—';

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    };

    $prop = static function ($row, string $key, $default = null) {
        return data_get($row, $key, $default);
    };

    $totalRows = method_exists($rows, 'total')
        ? (int) $rows->total()
        : (int) collect($rows)->count();

    $rowsCollection = collect(method_exists($rows, 'items') ? $rows->items() : $rows);

    $totalAmount = $rowsCollection->sum(function ($row) use ($prop) {
        $total = $prop($row, 'display_total_mxn');
        if ($total === null || $total === '') $total = $prop($row, 'amount_mxn');
        if ($total === null || $total === '') $total = $prop($row, 'monto_mxn');
        if ($total === null || $total === '') $total = $prop($row, 'total');
        if ($total === null || $total === '') $total = $prop($row, 'subtotal');
        if (($total === null || $total === '') && !empty($prop($row, 'amount'))) $total = ((float) $prop($row, 'amount')) / 100;
        if (($total === null || $total === '') && !empty($prop($row, 'amount_cents'))) $total = ((float) $prop($row, 'amount_cents')) / 100;
        return is_numeric($total) ? (float) $total : 0;
    });

    $paidCount = $rowsCollection->filter(fn ($row) => strtolower(trim((string) $prop($row, 'status'))) === 'paid')->count();
    $stampedCount = $rowsCollection->filter(fn ($row) => strtolower(trim((string) $prop($row, 'status'))) === 'stamped')->count();
    $pendingCount = $rowsCollection->filter(fn ($row) => strtolower(trim((string) $prop($row, 'status'))) === 'pending')->count();
    $cancelledCount = $rowsCollection->filter(function ($row) use ($prop) {
        $v = strtolower(trim((string) $prop($row, 'status')));
        return in_array($v, ['cancelled', 'canceled'], true);
    })->count();

    $routeIndex            = route('admin.billing.invoicing.invoices.index');
    $routeDashboard        = route('admin.billing.invoicing.dashboard');
    $routeRequests         = route('admin.billing.invoicing.requests.index');
    $routeStoreBulk        = route('admin.billing.invoicing.invoices.bulk_store_manual');
    $routeBulkSend         = route('admin.billing.invoicing.invoices.bulk_send');
    $routeFormSeed         = route('admin.billing.invoicing.invoices.form_seed');
    $routeSearchEmisores   = route('admin.billing.invoicing.invoices.search_emisores');
    $routeSearchReceptores = route('admin.billing.invoicing.invoices.search_receptores');
    $routeCreate           = route('admin.billing.invoicing.invoices.create');

    $bulkOldAccountId   = old('account_id', []);
    $bulkOldPeriod      = old('period', []);
    $bulkOldCfdiUuid    = old('cfdi_uuid', []);
    $bulkOldSerie       = old('serie', []);
    $bulkOldFolio       = old('folio', []);
    $bulkOldStatus      = old('status', []);
    $bulkOldAmountMxn   = old('amount_mxn', []);
    $bulkOldIssuedAt    = old('issued_at', []);
    $bulkOldIssuedDate  = old('issued_date', []);
    $bulkOldSource      = old('source', []);
    $bulkOldNotes       = old('notes', []);

    $bulkRowsCount = max(
        count(is_array($bulkOldAccountId) ? $bulkOldAccountId : []),
        count(is_array($bulkOldPeriod) ? $bulkOldPeriod : []),
        3
    );

    $currentDateFrom = request('date_from', now()->startOfMonth()->format('Y-m-d'));
    $currentDateTo   = request('date_to', now()->format('Y-m-d'));
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/invoicing-invoices.css') }}?v={{ @filemtime(public_path('assets/admin/css/invoicing-invoices.css')) ?: time() }}">
@endpush

@section('content')
<div class="invx-page invx-page--compact" id="invxPage">

    <section class="invx-topbar-card">
        <div class="invx-topbar-card__left">
            <div class="invx-mini-badge">
                <span class="invx-mini-badge__dot"></span>
                Facturación
            </div>

            <div class="invx-title-wrap">
                <h1 class="invx-title invx-title--compact">Facturas emitidas</h1>
            </div>
        </div>

        <div class="invx-topbar-card__right">
            <a href="{{ $routeDashboard }}" class="invx-iconbtn invx-iconbtn--toolbar" data-invx-tip="Panel">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
            </a>

            <a href="{{ $routeRequests }}" class="invx-iconbtn invx-iconbtn--toolbar" data-invx-tip="Solicitudes">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </a>

            <a href="{{ $routeCreate }}" class="invx-btn invx-btn--primary invx-btn--sm" data-invx-tip="Nueva factura">
                <span class="invx-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                Nueva factura
            </a>

            <button type="button" class="invx-btn invx-btn--soft invx-btn--sm" data-invx-drawer-open="bulkDrawer">
                <span class="invx-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
            </button>

            <button type="button" class="invx-btn invx-btn--success invx-btn--sm" data-invx-modal-open="bulkSendModal">
                <span class="invx-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </button>
        </div>
    </section>

    <section class="invx-unified-strip">
        <div class="invx-unified-strip__ai">
            @include('admin.billing.invoicing.invoices.partials._ai_panel')
        </div>

        <div class="invx-unified-strip__kpis">
            <article class="invx-kpi-mini invx-kpi-mini--micro" data-invx-tip="Visibles">
                <span class="invx-kpi-mini__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.8"/>
                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                </span>
                <strong class="invx-kpi-mini__value">{{ number_format($totalRows) }}</strong>
            </article>

            <article class="invx-kpi-mini invx-kpi-mini--micro" data-invx-tip="Monto visible">
                <span class="invx-kpi-mini__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 3v18M16.5 7.5c0-1.9-1.8-3.5-4.5-3.5S7.5 5.1 7.5 7c0 4.5 9 2.5 9 7 0 1.9-1.8 4-4.5 4S7.5 16.4 7.5 14.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                <strong class="invx-kpi-mini__value">{{ $money($totalAmount) }}</strong>
            </article>

            <article class="invx-kpi-mini invx-kpi-mini--micro" data-invx-tip="Timbradas">
                <span class="invx-kpi-mini__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M7 5h10v5H7zM7 13h10v6H7z" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                </span>
                <strong class="invx-kpi-mini__value">{{ number_format($stampedCount) }}</strong>
            </article>

            <article class="invx-kpi-mini invx-kpi-mini--micro" data-invx-tip="Pagadas">
                <span class="invx-kpi-mini__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M4 7h16v10H4z" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M4 11h16" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                </span>
                <strong class="invx-kpi-mini__value">{{ number_format($paidCount) }}</strong>
            </article>

            <article class="invx-kpi-mini invx-kpi-mini--micro" data-invx-tip="Pendientes">
                <span class="invx-kpi-mini__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M12 8v4l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                <strong class="invx-kpi-mini__value">{{ number_format($pendingCount) }}</strong>
            </article>

            <article class="invx-kpi-mini invx-kpi-mini--micro" data-invx-tip="Canceladas">
                <span class="invx-kpi-mini__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M8 8l8 8M16 8l-8 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                <strong class="invx-kpi-mini__value">{{ number_format($cancelledCount) }}</strong>
            </article>
        </div>
    </section>

    <section class="invx-filter-card">
        <div class="invx-filter-card__head">
            <div class="invx-filter-title-wrap">
                <h2 class="invx-section-title">Filtros</h2>

                <div class="invx-inline-chips">
                    <button type="button" class="invx-smart-chip invx-js-set-filter" data-target="status" data-value="stamped">Timbradas</button>
                    <button type="button" class="invx-smart-chip invx-js-set-filter" data-target="status" data-value="pending">Pendientes</button>
                    <button type="button" class="invx-smart-chip invx-js-set-filter" data-target="status" data-value="paid">Pagadas</button>
                    <button type="button" class="invx-smart-chip invx-js-fill-current-period">Actual</button>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ $routeIndex }}" class="invx-filters invx-filters--compact">
            <div class="invx-field invx-field--span-4">
                <div class="invx-floating">
                    <input id="q" type="text" name="q" class="invx-input" value="{{ $q }}" placeholder=" ">
                    <label for="q">Buscar UUID, RFC, cliente, folio...</label>
                    <span class="invx-floating__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </span>
                </div>
            </div>

            <div class="invx-field invx-field--span-2">
                <div class="invx-floating">
                    <select id="status" name="status" class="invx-input invx-input--select invx-has-value">
                        <option value="">Todos</option>
                        @foreach (['draft','pending','generated','stamped','issued','active','sent','paid','cancelled','error'] as $opt)
                            <option value="{{ $opt }}" @selected($status === $opt)>{{ $statusLabel($opt) }}</option>
                        @endforeach
                    </select>
                    <label for="status">Estado</label>
                </div>
            </div>

            <div class="invx-field invx-field--span-2">
                <div class="invx-floating">
                    <input id="period" type="text" name="period" class="invx-input" value="{{ $period }}" placeholder=" ">
                    <label for="period">Periodo</label>
                </div>
            </div>

            <div class="invx-field invx-field--span-2">
                <div class="invx-floating">
                    <input id="date_from" type="date" name="date_from" class="invx-input invx-has-value" value="{{ $currentDateFrom }}">
                    <label for="date_from">Desde</label>
                </div>
            </div>

            <div class="invx-field invx-field--span-2">
                <div class="invx-floating">
                    <input id="date_to" type="date" name="date_to" class="invx-input invx-has-value" value="{{ $currentDateTo }}">
                    <label for="date_to">Hasta</label>
                </div>
            </div>

            <div class="invx-field invx-field--span-12">
                <div class="invx-filter-actions invx-filter-actions--compact">
                    <div class="invx-chipbar">
                        <span class="invx-chip"><b>{{ $status !== '' ? $statusLabel($status) : 'Todos' }}</b></span>
                        <span class="invx-chip"><b>{{ $period !== '' ? $period : 'Todos los periodos' }}</b></span>
                    </div>

                    <div class="invx-btnbar">
                        <button type="submit" class="invx-btn invx-btn--primary invx-btn--sm">Aplicar</button>
                        <a href="{{ $routeIndex }}" class="invx-btn invx-btn--soft invx-btn--sm">Limpiar</a>
                    </div>
                </div>
            </div>
        </form>
    </section>

    @if (session('ok'))
        <div class="invx-alert invx-alert--success">{{ session('ok') }}</div>
    @endif

    @if ($errors->any())
        <div class="invx-alert invx-alert--danger">
            <strong>Se encontraron errores:</strong>
            <ul class="invx-errors">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (!empty($error))
        <div class="invx-alert invx-alert--warning">{{ $error }}</div>
    @endif

    <section class="invx-card invx-card--table">
        <div class="invx-toolbar invx-toolbar--dense">
            <div class="invx-toolbar__left">
                <strong>Facturas</strong>
                <span class="invx-chip"><b>{{ number_format($totalRows) }}</b> resultados</span>
                <span class="invx-chip"><b>{{ $money($totalAmount) }}</b> visible</span>
            </div>

            <div class="invx-toolbar__right">
                <button type="button" class="invx-iconbtn invx-iconbtn--toolbar" id="invxSelectAllBtn" data-invx-tip="Seleccionar visibles">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <button type="button" class="invx-iconbtn invx-iconbtn--toolbar" id="invxClearAllBtn" data-invx-tip="Limpiar selección">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M21 3v6h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <button type="button" class="invx-iconbtn invx-iconbtn--toolbar" data-invx-modal-open="bulkSendModal" data-invx-tip="Enviar seleccionadas">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </div>

        @if (collect($rows)->count() === 0)
            <div class="invx-empty">
                No hay facturas para mostrar con los filtros actuales.
            </div>
        @else
            <div class="invx-mobile-list">
                @foreach ($rows as $row)
                    @php
                        $invoiceId    = $prop($row, 'id');
                        $accountId    = $prop($row, 'account_id');
                        $accountName  = $prop($row, 'account_name');
                        $accountEmail = $prop($row, 'account_email');
                        $accountRfc   = $prop($row, 'account_rfc', $prop($row, 'rfc'));
                        $uuid         = $prop($row, 'cfdi_uuid');
                        $rowStatus    = $prop($row, 'status');
                        $periodRow    = $prop($row, 'period');
                        $rowStatusNormalized = strtolower(trim((string) $rowStatus));

                        $total = $prop($row, 'display_total_mxn');
                        if ($total === null || $total === '') $total = $prop($row, 'amount_mxn');
                        if ($total === null || $total === '') $total = $prop($row, 'monto_mxn');
                        if ($total === null || $total === '') $total = $prop($row, 'total');
                        if ($total === null || $total === '') $total = $prop($row, 'subtotal');
                        if (($total === null || $total === '') && !empty($prop($row, 'amount'))) $total = ((float) $prop($row, 'amount')) / 100;
                        if (($total === null || $total === '') && !empty($prop($row, 'amount_cents'))) $total = ((float) $prop($row, 'amount_cents')) / 100;

                        $createdAt = $prop($row, 'issued_at', $prop($row, 'created_at'));

                        $recipientList = $prop($row, 'recipient_list', []);
                        if (!is_array($recipientList)) $recipientList = [];

                        $defaultTo = old('to_' . $invoiceId);
                        if ($defaultTo === null || $defaultTo === '') {
                            $defaultTo = !empty($recipientList)
                                ? implode(',', $recipientList)
                                : (string) ($accountEmail ?: '');
                        }

                        $hasPdf = !empty($prop($row, 'pdf_path'));
                        $hasXml = !empty($prop($row, 'xml_path'));

                        $showStamp = empty($uuid) && !in_array($rowStatusNormalized, ['cancelled', 'canceled'], true);
                        $showCancel = !in_array($rowStatusNormalized, ['cancelled', 'canceled'], true);

                        $stampUrl = url('/admin/billing/sat/billing/invoicing/invoices/' . $invoiceId . '/stamp');
                    @endphp

                    <article class="invx-mobile-card">
                        <div class="invx-mobile-card__head">
                            <div class="invx-stack invx-stack--tight">
                                <div class="invx-id">#{{ $invoiceId }}</div>
                                <div class="invx-muted">{{ $accountName ?: '—' }}</div>
                            </div>

                            <span class="invx-badge invx-status {{ $statusClass($rowStatus) }}">
                                {{ $statusLabel($rowStatus) }}
                            </span>
                        </div>

                        <div class="invx-mobile-card__grid">
                            <div><b>Cuenta:</b> {{ $accountId ?: '—' }}</div>
                            <div><b>Periodo:</b> {{ $periodRow ?: '—' }}</div>
                            <div><b>Fecha:</b> {{ $fmtDate($createdAt) }}</div>
                            <div><b>Total:</b> {{ $money($total) }}</div>
                            <div><b>RFC:</b> {{ $accountRfc ?: '—' }}</div>
                            <div><b>UUID:</b> {{ $uuid ?: '—' }}</div>
                        </div>

                        <div class="invx-mobile-card__docs">
                            @if($hasPdf)
                                <a href="{{ route('admin.billing.invoicing.invoices.download', [$invoiceId, 'pdf']) }}" class="invx-doc">PDF</a>
                            @endif

                            @if($hasXml)
                                <a href="{{ route('admin.billing.invoicing.invoices.download', [$invoiceId, 'xml']) }}" class="invx-doc">XML</a>
                            @endif

                            @if(!$hasPdf && !$hasXml)
                                <span class="invx-muted">Sin archivos</span>
                            @endif
                        </div>

                        <div class="invx-mobile-card__actions">
                            <a href="{{ route('admin.billing.invoicing.invoices.show', $invoiceId) }}" class="invx-btn invx-btn--soft invx-btn--sm">Ver</a>

                            <button
                                type="button"
                                class="invx-btn invx-btn--soft invx-btn--sm"
                                data-invx-open-send
                                data-mode="send"
                                data-id="{{ $invoiceId }}"
                                data-title="Enviar factura #{{ $invoiceId }}"
                                data-action="{{ route('admin.billing.invoicing.invoices.send', $invoiceId) }}"
                                data-to="{{ e($defaultTo) }}"
                            >
                                Enviar
                            </button>

                            @if($showStamp)
                                <button
                                    type="button"
                                    class="invx-btn invx-btn--info invx-btn--sm"
                                    data-invx-open-confirm
                                    data-kind="stamp"
                                    data-title="Timbrar factura #{{ $invoiceId }}"
                                    data-message="Se intentará timbrar esta factura con el flujo actual."
                                    data-action="{{ $stampUrl }}"
                                    data-confirm-label="Timbrar ahora"
                                >
                                    Timbrar
                                </button>
                            @endif

                            @if($showCancel)
                                <button
                                    type="button"
                                    class="invx-btn invx-btn--danger invx-btn--sm"
                                    data-invx-open-confirm
                                    data-kind="cancel"
                                    data-title="Cancelar factura #{{ $invoiceId }}"
                                    data-message="Esta acción marcará la factura como cancelada en el módulo actual."
                                    data-action="{{ route('admin.billing.invoicing.invoices.cancel', $invoiceId) }}"
                                    data-confirm-label="Cancelar factura"
                                >
                                    Cancelar
                                </button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="invx-table-wrap">
                <table class="invx-table invx-table--compact">
                    <thead>
                        <tr>
                            <th style="width:40px;">
                                <input type="checkbox" class="invx-check" id="invxCheckAllHead">
                            </th>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>RFC / UUID</th>
                            <th>Periodo</th>
                            <th>Estado</th>
                            <th>Total</th>
                            <th>Docs</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $invoiceId    = $prop($row, 'id');
                                $accountId    = $prop($row, 'account_id');
                                $accountName  = $prop($row, 'account_name');
                                $accountEmail = $prop($row, 'account_email');
                                $accountRfc   = $prop($row, 'account_rfc', $prop($row, 'rfc'));
                                $uuid         = $prop($row, 'cfdi_uuid');
                                $rowStatus    = $prop($row, 'status');
                                $periodRow    = $prop($row, 'period');
                                $rowStatusNormalized = strtolower(trim((string) $rowStatus));

                                $total = $prop($row, 'display_total_mxn');
                                if ($total === null || $total === '') $total = $prop($row, 'amount_mxn');
                                if ($total === null || $total === '') $total = $prop($row, 'monto_mxn');
                                if ($total === null || $total === '') $total = $prop($row, 'total');
                                if ($total === null || $total === '') $total = $prop($row, 'subtotal');
                                if (($total === null || $total === '') && !empty($prop($row, 'amount'))) $total = ((float) $prop($row, 'amount')) / 100;
                                if (($total === null || $total === '') && !empty($prop($row, 'amount_cents'))) $total = ((float) $prop($row, 'amount_cents')) / 100;

                                $createdAt = $prop($row, 'issued_at', $prop($row, 'created_at'));

                                $recipientList = $prop($row, 'recipient_list', []);
                                if (!is_array($recipientList)) $recipientList = [];

                                $defaultTo = old('to_' . $invoiceId);
                                if ($defaultTo === null || $defaultTo === '') {
                                    $defaultTo = !empty($recipientList)
                                        ? implode(',', $recipientList)
                                        : (string) ($accountEmail ?: '');
                                }

                                $hasPdf = !empty($prop($row, 'pdf_path'));
                                $hasXml = !empty($prop($row, 'xml_path'));

                                $showStamp = empty($uuid) && !in_array($rowStatusNormalized, ['cancelled', 'canceled'], true);
                                $showCancel = !in_array($rowStatusNormalized, ['cancelled', 'canceled'], true);

                                $stampUrl = url('/admin/billing/sat/billing/invoicing/invoices/' . $invoiceId . '/stamp');
                            @endphp

                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        class="invx-check invx-row-check"
                                        value="{{ $invoiceId }}"
                                        data-invoice-id="{{ $invoiceId }}"
                                    >
                                </td>

                                <td>
                                    <div class="invx-stack invx-stack--tight">
                                        <div class="invx-id">#{{ $invoiceId }}</div>
                                        <div class="invx-muted">{{ $prop($row, 'folio') ?: '—' }}</div>
                                    </div>
                                </td>

                                <td>
                                    <div class="invx-stack invx-stack--tight">
                                        <strong>{{ $fmtDate($createdAt) }}</strong>
                                        <span class="invx-muted">{{ $prop($row, 'source') ?: '—' }}</span>
                                    </div>
                                </td>

                                <td>
                                    <div class="invx-stack invx-stack--tight">
                                        <strong>{{ $accountName ?: '—' }}</strong>
                                        <span class="invx-muted">Cuenta {{ $accountId ?: '—' }}</span>
                                        <span class="invx-muted">{{ $accountEmail ?: '—' }}</span>
                                    </div>
                                </td>

                                <td>
                                    <div class="invx-stack invx-stack--tight">
                                        <span>{{ $accountRfc ?: '—' }}</span>
                                        <span class="invx-muted invx-ellipsis">{{ $uuid ?: '—' }}</span>
                                    </div>
                                </td>

                                <td>
                                    <span class="invx-badge invx-badge--soft">{{ $periodRow ?: '—' }}</span>
                                </td>

                                <td>
                                    <span class="invx-badge invx-status {{ $statusClass($rowStatus) }}">
                                        {{ $statusLabel($rowStatus) }}
                                    </span>
                                </td>

                                <td>
                                    <strong>{{ $money($total) }}</strong>
                                </td>

                                <td>
                                    <div class="invx-docs">
                                        @if($hasPdf)
                                            <a href="{{ route('admin.billing.invoicing.invoices.download', [$invoiceId, 'pdf']) }}" class="invx-doc">PDF</a>
                                        @endif

                                        @if($hasXml)
                                            <a href="{{ route('admin.billing.invoicing.invoices.download', [$invoiceId, 'xml']) }}" class="invx-doc">XML</a>
                                        @endif

                                        @if(!$hasPdf && !$hasXml)
                                            <span class="invx-muted">—</span>
                                        @endif
                                    </div>
                                </td>

                                <td class="invx-actions-cell">
                                    <div class="invx-row-actions invx-row-actions--icon">
                                        <a href="{{ route('admin.billing.invoicing.invoices.show', $invoiceId) }}" class="invx-action-icon" data-invx-tip="Ver detalle">
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.8"/>
                                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                                            </svg>
                                        </a>

                                        <button
                                            type="button"
                                            class="invx-action-icon"
                                            data-invx-tip="Enviar factura"
                                            data-invx-open-send
                                            data-mode="send"
                                            data-id="{{ $invoiceId }}"
                                            data-title="Enviar factura #{{ $invoiceId }}"
                                            data-action="{{ route('admin.billing.invoicing.invoices.send', $invoiceId) }}"
                                            data-to="{{ e($defaultTo) }}"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>

                                        <button
                                            type="button"
                                            class="invx-action-icon invx-action-icon--warn"
                                            data-invx-tip="Reenviar factura"
                                            data-invx-open-send
                                            data-mode="resend"
                                            data-id="{{ $invoiceId }}"
                                            data-title="Reenviar factura #{{ $invoiceId }}"
                                            data-action="{{ route('admin.billing.invoicing.invoices.resend', $invoiceId) }}"
                                            data-to="{{ e($defaultTo) }}"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                <path d="M21 3v6h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>

                                        @if($showStamp)
                                            <button
                                                type="button"
                                                class="invx-action-icon invx-action-icon--info"
                                                data-invx-tip="Timbrar factura"
                                                data-invx-open-confirm
                                                data-kind="stamp"
                                                data-title="Timbrar factura #{{ $invoiceId }}"
                                                data-message="Se intentará timbrar esta factura con el flujo actual."
                                                data-action="{{ $stampUrl }}"
                                                data-confirm-label="Timbrar ahora"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none">
                                                    <path d="M8 8a4 4 0 1 1 8 0c0 2 1.5 3.2 1.5 5.5H6.5C6.5 11.2 8 10 8 8Z" stroke="currentColor" stroke-width="1.8"/>
                                                    <path d="M7 13.5h10v3a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 7 16.5v-3Z" stroke="currentColor" stroke-width="1.8"/>
                                                    <path d="M5 20h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        @endif

                                        @if($showCancel)
                                            <button
                                                type="button"
                                                class="invx-action-icon invx-action-icon--danger"
                                                data-invx-tip="Cancelar factura"
                                                data-invx-open-confirm
                                                data-kind="cancel"
                                                data-title="Cancelar factura #{{ $invoiceId }}"
                                                data-message="Esta acción marcará la factura como cancelada en el módulo actual."
                                                data-action="{{ route('admin.billing.invoicing.invoices.cancel', $invoiceId) }}"
                                                data-confirm-label="Cancelar factura"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none">
                                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                                                    <path d="M5.6 5.6l12.8 12.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if(method_exists($rows, 'links'))
            <div class="invx-pagination">
                {{ $rows->links() }}
            </div>
        @endif
    </section>
</div>

@include('admin.billing.invoicing.invoices.partials._bulk_drawer', [
    'routeStoreBulk' => $routeStoreBulk,
    'bulkRowsCount' => $bulkRowsCount,
    'bulkOldAccountId' => $bulkOldAccountId,
    'bulkOldPeriod' => $bulkOldPeriod,
    'bulkOldCfdiUuid' => $bulkOldCfdiUuid,
    'bulkOldSerie' => $bulkOldSerie,
    'bulkOldFolio' => $bulkOldFolio,
    'bulkOldStatus' => $bulkOldStatus,
    'bulkOldAmountMxn' => $bulkOldAmountMxn,
    'bulkOldIssuedAt' => $bulkOldIssuedAt,
    'bulkOldIssuedDate' => $bulkOldIssuedDate,
    'bulkOldSource' => $bulkOldSource,
    'bulkOldNotes' => $bulkOldNotes,
])

@include('admin.billing.invoicing.invoices.partials._bulk_send_modal', [
    'routeBulkSend' => $routeBulkSend,
])

@include('admin.billing.invoicing.invoices.partials._send_modal')
@include('admin.billing.invoicing.invoices.partials._confirm_modal')

<script>
window.__INVX_FORM_SEED__ = @json($routeFormSeed);
window.__INVX_SEARCH_EMISORES__ = @json($routeSearchEmisores);
window.__INVX_SEARCH_RECEPTORES__ = @json($routeSearchReceptores);
</script>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/invoicing-invoices.js') }}?v={{ @filemtime(public_path('assets/admin/js/invoicing-invoices.js')) ?: time() }}"></script>
<script>
window.__INVX_AUTO_OPEN_SINGLE__ = false;
</script>
@endpush