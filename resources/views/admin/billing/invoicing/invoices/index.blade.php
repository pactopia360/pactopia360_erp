{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\invoicing\invoices\index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Facturación · Facturas emitidas')
@section('layout', 'full')
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

@php
    $bsv2CssPath = public_path('assets/admin/css/billing-statements-v2.css');
    $beCssPath   = public_path('assets/admin/css/billing-emisores.css');
    $invxCssPath = public_path('assets/admin/css/invoicing-invoices.css');
    $bsv2JsPath  = public_path('assets/admin/js/billing-statements-v2.js');

    $bsv2CssVer = is_file($bsv2CssPath) ? filemtime($bsv2CssPath) : time();
    $beCssVer   = is_file($beCssPath) ? filemtime($beCssPath) : time();
    $invxCssVer = is_file($invxCssPath) ? filemtime($invxCssPath) : time();
    $bsv2JsVer  = is_file($bsv2JsPath) ? filemtime($bsv2JsPath) : time();
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/billing-statements-v2.css') }}?v={{ $bsv2CssVer }}">
<link rel="stylesheet" href="{{ asset('assets/admin/css/billing-emisores.css') }}?v={{ $beCssVer }}">
<link rel="stylesheet" href="{{ asset('assets/admin/css/invoicing-invoices.css') }}?v={{ $invxCssVer }}">
<style>
.billing-invoices-bsv2-page .bsv2-list-card,
.billing-invoices-bsv2-page .bsv2-list-card__content,
.billing-invoices-bsv2-page .invx-table-card,
.billing-invoices-bsv2-page .invx-table-wrap{
    overflow: visible !important;
}

.billing-invoices-bsv2-page .invx-table-card{
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 16px 34px rgba(15, 23, 42, .06);
}

.billing-invoices-bsv2-page .invx-table-wrap{
    width: 100%;
    max-width: 100%;
}

.billing-invoices-bsv2-page .invx-table{
    width: 100%;
    table-layout: auto;
    border-collapse: separate;
    border-spacing: 0;
}

.billing-invoices-bsv2-page .invx-table th,
.billing-invoices-bsv2-page .invx-table td{
    white-space: nowrap;
    vertical-align: middle;
}

.billing-invoices-bsv2-page .invx-table th:nth-child(3),
.billing-invoices-bsv2-page .invx-table td:nth-child(3),
.billing-invoices-bsv2-page .invx-table th:nth-child(4),
.billing-invoices-bsv2-page .invx-table td:nth-child(4){
    white-space: normal;
    min-width: 220px;
}

.billing-invoices-bsv2-page .invx-table th:last-child,
.billing-invoices-bsv2-page .invx-table td:last-child{
    width: 120px;
    text-align: right;
    position: relative;
    overflow: visible !important;
}

.billing-invoices-bsv2-page [data-floating-label]{
    position: relative;
    overflow: visible !important;
}

.billing-invoices-bsv2-page [data-floating-label]::after{
    content: attr(data-floating-label);
    position: absolute;
    right: 50%;
    bottom: calc(100% + 10px);
    transform: translateX(50%) translateY(4px);
    z-index: 99999;
    opacity: 0;
    pointer-events: none;
    white-space: nowrap;
    background: #0f172a;
    color: #fff;
    font-size: 11px;
    font-weight: 900;
    line-height: 1;
    padding: 8px 10px;
    border-radius: 999px;
    box-shadow: 0 14px 30px rgba(15, 23, 42, .24);
    transition: .16s ease;
}

.billing-invoices-bsv2-page [data-floating-label]::before{
    content: "";
    position: absolute;
    right: 50%;
    bottom: calc(100% + 4px);
    transform: translateX(50%) translateY(4px);
    z-index: 99999;
    opacity: 0;
    pointer-events: none;
    border: 6px solid transparent;
    border-top-color: #0f172a;
    transition: .16s ease;
}

.billing-invoices-bsv2-page [data-floating-label]:hover::after,
.billing-invoices-bsv2-page [data-floating-label]:focus-visible::after,
.billing-invoices-bsv2-page [data-floating-label]:hover::before,
.billing-invoices-bsv2-page [data-floating-label]:focus-visible::before{
    opacity: 1;
    transform: translateX(50%) translateY(0);
}

@media (max-width: 1100px){
    .billing-invoices-bsv2-page .invx-table-wrap{
        overflow-x: auto !important;
        overflow-y: visible !important;
    }

    .billing-invoices-bsv2-page .invx-table{
        min-width: 980px;
    }
}

.billing-invoices-bsv2-page [data-floating-label]::before,
.billing-invoices-bsv2-page [data-floating-label]::after{
    display: none !important;
}

.p360-fixed-tooltip{
    position: fixed;
    z-index: 2147483647;
    left: 0;
    top: 0;
    opacity: 0;
    pointer-events: none;
    transform: translate(-50%, -8px);
    background: #0f172a;
    color: #fff;
    padding: 8px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
    line-height: 1;
    white-space: nowrap;
    box-shadow: 0 16px 34px rgba(15, 23, 42, .28);
    transition: opacity .12s ease, transform .12s ease;
}

.p360-fixed-tooltip.is-visible{
    opacity: 1;
    transform: translate(-50%, -14px);
}
</style>
@endpush

@section('content')
<div class="bsv2-page billing-invoices-bsv2-page" data-bsv2-root id="invxPage">
    <div class="bsv2-wrap">
        <section class="bsv2-header-clean" aria-label="Encabezado de facturas emitidas">
            <div class="bsv2-header-clean__content">
                <div class="bsv2-header-clean__text">
                    <h1 class="bsv2-title">Facturas emitidas</h1>
                    <p class="bsv2-subtitle">
                        Emisión, timbrado, envío, cancelación y seguimiento de CFDI desde administración.
                    </p>
                </div>

                <div class="bsv2-header-clean__meta">
                    <div class="bsv2-kpi">
                        <span class="bsv2-kpi__label">Total</span>
                        <strong class="bsv2-kpi__value">{{ number_format($totalRows) }}</strong>
                    </div>

                    <div class="bsv2-kpi">
                        <span class="bsv2-kpi__label">Visible</span>
                        <strong class="bsv2-kpi__value">{{ $money($totalAmount) }}</strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Resumen de facturas">
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
                        <span class="bsv2-list-card__summary-meta">Estado fiscal, pagos, documentos y operación</span>
                    </span>

                    <span class="bsv2-list-card__summary-action" aria-hidden="true">
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                        <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                    </span>
                </button>

                <div class="bsv2-list-card__content" id="bsv2-kpis-content" hidden>
                    <div class="bsv2-kpi-strip">
                        <article class="bsv2-kpi-card">
                            <span class="bsv2-kpi-card__label">Registros</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($totalRows) }}</strong>
                            <span class="bsv2-kpi-card__meta">Resultado actual</span>
                        </article>

                        <article class="bsv2-kpi-card is-paid">
                            <span class="bsv2-kpi-card__label">Timbradas</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($stampedCount) }}</strong>
                            <span class="bsv2-kpi-card__meta">Con UUID</span>
                        </article>

                        <article class="bsv2-kpi-card is-pending">
                            <span class="bsv2-kpi-card__label">Pendientes</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($pendingCount) }}</strong>
                            <span class="bsv2-kpi-card__meta">Por atender</span>
                        </article>

                        <article class="bsv2-kpi-card is-partial">
                            <span class="bsv2-kpi-card__label">Pagadas</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($paidCount) }}</strong>
                            <span class="bsv2-kpi-card__meta">Cobradas</span>
                        </article>

                        <article class="bsv2-kpi-card is-overdue">
                            <span class="bsv2-kpi-card__label">Canceladas</span>
                            <strong class="bsv2-kpi-card__value">{{ number_format($cancelledCount) }}</strong>
                            <span class="bsv2-kpi-card__meta">Sin efecto</span>
                        </article>

                        <article class="bsv2-kpi-card">
                            <span class="bsv2-kpi-card__label">Monto</span>
                            <strong class="bsv2-kpi-card__value">{{ $money($totalAmount) }}</strong>
                            <span class="bsv2-kpi-card__meta">Visible</span>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Filtros de facturas">
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
                        <span class="bsv2-list-card__summary-meta">Búsqueda, emisión, envío y navegación</span>
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
                                        placeholder="UUID, RFC, cliente, folio, cuenta..."
                                    >
                                </div>
                            </div>

                            <div class="bsv2-filter-item">
                                <label class="bsv2-filter-label" for="status">Estado</label>
                                <select id="status" name="status" class="bsv2-filter-control">
                                    <option value="">Todos</option>
                                    @foreach (['draft','pending','generated','stamped','issued','active','sent','paid','cancelled','error'] as $opt)
                                        <option value="{{ $opt }}" @selected($status === $opt)>{{ $statusLabel($opt) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="bsv2-filter-item">
                                <label class="bsv2-filter-label" for="period">Periodo</label>
                                <input id="period" type="text" name="period" class="bsv2-filter-control" value="{{ $period }}" placeholder="2026-05">
                            </div>
                        </div>

                        <div class="bsv2-bulk-toolbar">
                            <div class="bsv2-bulk-toolbar__group">
                                <div class="bsv2-bulk-chip">
                                    <span class="bsv2-bulk-chip__label">Estado</span>
                                    <strong class="bsv2-bulk-chip__value">{{ $status !== '' ? $statusLabel($status) : 'Todos' }}</strong>
                                </div>

                                <div class="bsv2-bulk-chip">
                                    <span class="bsv2-bulk-chip__label">Periodo</span>
                                    <strong class="bsv2-bulk-chip__value">{{ $period !== '' ? $period : 'Todos' }}</strong>
                                </div>
                            </div>

                            <div class="bsv2-bulk-toolbar__group bsv2-bulk-toolbar__group--actions">
                                <button type="submit" class="bsv2-btn bsv2-btn--primary bsv2-btn--icon-only" data-floating-label="Filtrar" aria-label="Filtrar">
                                    <span class="bsv2-btn__icon" aria-hidden="true">✓</span>
                                </button>

                                <a href="{{ $routeIndex }}" class="bsv2-btn bsv2-btn--ghost bsv2-btn--icon-only" data-floating-label="Limpiar" aria-label="Limpiar">
                                    <span class="bsv2-btn__icon" aria-hidden="true">↻</span>
                                </a>

                                <a href="{{ $routeDashboard }}" class="bsv2-btn bsv2-btn--soft bsv2-btn--icon-only" data-floating-label="Dashboard" aria-label="Dashboard">
                                    <span class="bsv2-btn__icon" aria-hidden="true">▦</span>
                                </a>

                                <a href="{{ $routeRequests }}" class="bsv2-btn bsv2-btn--soft bsv2-btn--icon-only" data-floating-label="Solicitudes" aria-label="Solicitudes">
                                    <span class="bsv2-btn__icon" aria-hidden="true">☰</span>
                                </a>

                                <a href="{{ $routeCreate }}" class="bsv2-btn bsv2-btn--primary bsv2-btn--icon-only" data-floating-label="Nueva factura" aria-label="Nueva factura">
                                    <span class="bsv2-btn__icon" aria-hidden="true">+</span>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
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
<script src="{{ asset('assets/admin/js/billing-statements-v2.js') }}?v={{ $bsv2JsVer }}"></script>
<script src="{{ asset('assets/admin/js/invoicing-invoices.js') }}?v={{ @filemtime(public_path('assets/admin/js/invoicing-invoices.js')) ?: time() }}"></script>
<script>
window.__INVX_AUTO_OPEN_SINGLE__ = false;

document.addEventListener('DOMContentLoaded', function () {
    const tooltip = document.createElement('div');
    tooltip.className = 'p360-fixed-tooltip';
    document.body.appendChild(tooltip);

    let activeEl = null;

    function getLabel(el) {
        return el.getAttribute('data-floating-label') || el.getAttribute('data-invx-tip') || el.getAttribute('aria-label') || '';
    }

    function showTooltip(el) {
        const label = getLabel(el);
        if (!label) return;

        activeEl = el;
        tooltip.textContent = label;

        const rect = el.getBoundingClientRect();
        const x = rect.left + (rect.width / 2);
        const y = rect.top - 8;

        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
        tooltip.classList.add('is-visible');
    }

    function hideTooltip() {
        activeEl = null;
        tooltip.classList.remove('is-visible');
    }

    function refreshTooltip() {
        if (!activeEl) return;
        showTooltip(activeEl);
    }

    document.querySelectorAll('[data-floating-label], [data-invx-tip]').forEach(function (el) {
        el.addEventListener('mouseenter', function () {
            showTooltip(el);
        });

        el.addEventListener('focus', function () {
            showTooltip(el);
        });

        el.addEventListener('mouseleave', hideTooltip);
        el.addEventListener('blur', hideTooltip);
    });

    window.addEventListener('scroll', refreshTooltip, true);
    window.addEventListener('resize', refreshTooltip);
});
</script>

@endpush