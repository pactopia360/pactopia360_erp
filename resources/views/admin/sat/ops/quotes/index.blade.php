{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\sat\ops\quotes\index.blade.php --}}
@extends('layouts.admin')

@section('title', 'SAT · Operación · Cotizaciones')
@section('pageClass', 'page-sat-quotes-admin')
@section('contentLayout', 'full')

@php
    $filters = $filters ?? [];
    $stats = $stats ?? [
        'borrador'    => 0,
        'en_proceso'  => 0,
        'cotizada'    => 0,
        'pagada'      => 0,
        'en_descarga' => 0,
        'cancelada'   => 0,
    ];

    $money = static function ($value) {
        return '$' . number_format((float) $value, 2, '.', ',');
    };

    $last4 = static function ($value) {
        $value = (string) $value;
        return $value !== '' ? substr($value, -4) : '—';
    };

    $shortCode = static function ($value, $prefix = '') {
        $value = (string) $value;
        if ($value === '') {
            return '—';
        }

        $tail = strtoupper(substr($value, -4));

        return $prefix !== ''
            ? strtoupper($prefix) . '-' . $tail
            : $tail;
    };

    $statusLabels = [
        'borrador'    => 'Borrador',
        'en_proceso'  => 'En proceso',
        'cotizada'    => 'Cotizada',
        'pagada'      => 'Pagada',
        'en_descarga' => 'En descarga',
        'completada'  => 'Completada',
        'cancelada'   => 'Cancelada',
    ];

    $cssRel = 'assets/admin/css/sat-ops-quotes.css';
    $cssAbs = public_path($cssRel);
    $cssV   = is_file($cssAbs) ? (string) filemtime($cssAbs) : null;

    $jsRel = 'assets/admin/js/sat-ops-quotes.js';
    $jsAbs = public_path($jsRel);
    $jsV   = is_file($jsAbs) ? (string) filemtime($jsAbs) : null;
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset($cssRel) }}{{ $cssV ? ('?v=' . $cssV) : '' }}">
@endpush

@section('content')
<div class="satq-page">
    @if(session('success'))
        <div class="satq-alert satq-alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="satq-alert satq-alert-error">{{ session('error') }}</div>
    @endif

    <section class="satq-hero">
        <article class="satq-card satq-hero-main">
            <span class="satq-eyebrow">SAT · Admin · Cotizaciones</span>
            <h1 class="satq-title">Administración de cotizaciones SAT</h1>
            <p class="satq-subtitle">
                Aquí llegan las solicitudes generadas desde portal cliente para revisión operativa y comercial.
                Este módulo consolida la consulta de RFC, estatus, validación del alcance, confirmación,
                control de pagos y seguimiento previo a descarga.
            </p>
        </article>

        <aside class="satq-card satq-hero-side">
            <div>
                <h3>Alcance del módulo</h3>
                <p>
                    Consolida las cotizaciones solicitadas por clientes, permite revisar RFC,
                    periodo, volumen estimado, notas del usuario y actualizar el estatus operativo.
                </p>
            </div>

            <ul class="satq-inline-list">
                <li><span></span>Consulta de solicitudes recibidas desde cliente</li>
                <li><span></span>Visualización de RFC, concepto, periodo y XML estimados</li>
                <li><span></span>Cambio de estatus operativo y comercial</li>
                <li><span></span>Base lista para flujo de confirmación, pago y descarga</li>
            </ul>
        </aside>
    </section>

    <section class="satq-stats">
        <article class="satq-stat">
            <p class="satq-stat-label">Borradores</p>
            <p class="satq-stat-value">{{ number_format((int) ($stats['borrador'] ?? 0)) }}</p>
        </article>

        <article class="satq-stat">
            <p class="satq-stat-label">En proceso</p>
            <p class="satq-stat-value">{{ number_format((int) ($stats['en_proceso'] ?? 0)) }}</p>
        </article>

        <article class="satq-stat">
            <p class="satq-stat-label">Cotizadas</p>
            <p class="satq-stat-value">{{ number_format((int) ($stats['cotizada'] ?? 0)) }}</p>
        </article>

        <article class="satq-stat">
            <p class="satq-stat-label">Pagadas</p>
            <p class="satq-stat-value">{{ number_format((int) ($stats['pagada'] ?? 0)) }}</p>
        </article>

        <article class="satq-stat">
            <p class="satq-stat-label">En descarga</p>
            <p class="satq-stat-value">{{ number_format((int) ($stats['en_descarga'] ?? 0)) }}</p>
        </article>

        <article class="satq-stat">
            <p class="satq-stat-label">Canceladas</p>
            <p class="satq-stat-value">{{ number_format((int) ($stats['cancelada'] ?? 0)) }}</p>
        </article>
    </section>

    <section class="satq-card satq-filters">
        <form method="GET" action="{{ route('admin.sat.ops.quotes.index') }}" class="satq-filter-form">
            <div class="satq-field">
                <label for="q">Buscar</label>
                <input
                    id="q"
                    name="q"
                    type="text"
                    class="satq-input"
                    value="{{ $filters['q'] ?? '' }}"
                    placeholder="Folio, RFC, tipo o estatus"
                >
            </div>

            <div class="satq-field">
                <label for="status">Estatus</label>
                <select id="status" name="status" class="satq-select">
                    <option value="">Todos</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="satq-field">
                <label for="rfc">RFC</label>
                <input
                    id="rfc"
                    name="rfc"
                    type="text"
                    class="satq-input"
                    value="{{ $filters['rfc'] ?? '' }}"
                    placeholder="RFC exacto"
                >
            </div>

            <div class="satq-field">
                <label for="desde">Desde</label>
                <input
                    id="desde"
                    name="desde"
                    type="date"
                    class="satq-input"
                    value="{{ $filters['desde'] ?? '' }}"
                >
            </div>

            <div class="satq-field">
                <label for="hasta">Hasta</label>
                <input
                    id="hasta"
                    name="hasta"
                    type="date"
                    class="satq-input"
                    value="{{ $filters['hasta'] ?? '' }}"
                >
            </div>

            <div class="satq-actions">
                <button type="submit" class="satq-btn satq-btn-primary">Filtrar</button>
                <a href="{{ route('admin.sat.ops.quotes.index') }}" class="satq-btn satq-btn-light">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="satq-card satq-table-card">
        <div class="satq-table-head">
            <div>
                <h3>Solicitudes de cotización</h3>
                <p>Listado espejo de solicitudes SAT recibidas desde cliente para atención interna.</p>
            </div>
        </div>

        @if(($rows ?? null) && $rows->count() > 0)
            <div class="satq-table-wrap">
                <table class="satq-table">
                    <thead>
                        <tr>
                            <th>Folio / ID</th>
                            <th>RFC / Cliente</th>
                            <th>Servicio</th>
                            <th>XML</th>
                            <th>Total</th>
                            <th>Estatus</th>
                            <th>Progreso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php
                                $statusUi = (string) ($row['status_ui'] ?? 'borrador');
                                $progress = (int) ($row['progress'] ?? 0);
                                $rowId = (string) ($row['id'] ?? '');
                                $modalInfoId = 'satq-modal-info-' . $rowId;
                                $modalEditId = 'satq-modal-edit-' . $rowId;
                                $modalConfirmId = 'satq-modal-confirm-' . $rowId;
                                $modalRejectId = 'satq-modal-reject-' . $rowId;
                                $modalSatId = 'satq-modal-sat-' . $rowId;
                                $modalTransferId = 'satq-modal-transfer-' . $rowId;

                                $transferReview = data_get($row, 'meta.transfer_review', []);
                                $hasTransferReview = is_array($transferReview) && !empty($transferReview);

                                $transferReviewStatus = (string) ($transferReview['review_status'] ?? '');
                                $transferAiStatus = (string) ($transferReview['ai_status'] ?? '');
                                $transferRisk = strtolower((string) ($transferReview['risk_level'] ?? 'medium'));
                                $transferFlags = is_array($transferReview['risk_flags'] ?? null) ? $transferReview['risk_flags'] : [];
                            @endphp

                            <tr>
                                <td>
                                    <div class="satq-id">
                                        <div class="satq-folio">
                                            <span class="satq-folio-code">{{ $shortCode($row['folio'] ?? '', 'SATQ') }}</span>
                                        </div>

                                        <div class="satq-id-mini">
                                            <span class="satq-id-badge">ID · {{ $last4($row['id'] ?? '') }}</span>
                                            <span>Creado: {{ optional($row['created_at'] ?? null)->format('Y-m-d H:i') ?? '—' }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="satq-client-main">
                                        <div class="satq-rfc">{{ $row['rfc'] ?? '—' }}</div>
                                        <div class="satq-client-name">{{ $row['razon_social'] ?? 'Sin razón social' }}</div>
                                    </div>
                                </td>

                                <td>
                                    <div class="satq-service-box">
                                        <div class="satq-service-type">{{ ucfirst((string) ($row['tipo_solicitud'] ?? 'emitidos')) }}</div>
                                        <div class="satq-service-period">{{ ($row['date_from'] ?? '—') }} al {{ ($row['date_to'] ?? '—') }}</div>
                                        <div class="satq-meta">{{ $row['concepto'] ?? 'Cotización SAT' }}</div>
                                    </div>
                                </td>

                                <td>
                                    <div class="satq-meta">
                                        <strong class="satq-xml-strong">{{ number_format((int) ($row['xml_count'] ?? 0)) }}</strong><br>
                                        CFDI/XML estimados
                                    </div>
                                </td>

                                <td>
                                    <div class="satq-money-stack">
                                        <div class="satq-money-total">{{ $money($row['total'] ?? 0) }}</div>
                                        <div class="satq-money-breakdown">
                                            Subtotal: {{ $money($row['subtotal'] ?? 0) }}<br>
                                            IVA: {{ $money($row['iva'] ?? 0) }}
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="satq-chip satq-chip-{{ $statusUi }}">
                                        {{ $statusLabels[$statusUi] ?? ucfirst(str_replace('_', ' ', $statusUi)) }}
                                    </span>

                                    <div class="satq-quick-status">
                                        <form method="POST" action="{{ route('admin.sat.ops.quotes.status.update', ['id' => $row['id']]) }}">
                                            @csrf
                                            <input type="hidden" name="status_ui" value="en_proceso">
                                            <button type="submit" class="satq-btn satq-btn-light">Proceso</button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.sat.ops.quotes.status.update', ['id' => $row['id']]) }}">
                                            @csrf
                                            <input type="hidden" name="status_ui" value="pagada">
                                            <button type="submit" class="satq-btn satq-btn-light">Pagada</button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.sat.ops.quotes.status.update', ['id' => $row['id']]) }}">
                                            @csrf
                                            <input type="hidden" name="status_ui" value="en_descarga">
                                            <button type="submit" class="satq-btn satq-btn-warning">En descarga</button>
                                        </form>
                                    </div>

                                    @if($hasTransferReview)
                                        <div class="satq-note-box">
                                            <strong>Transferencia en revisión</strong><br>
                                            Estatus revisión: {{ $transferReviewStatus !== '' ? $transferReviewStatus : 'pending' }} ·
                                            IA: {{ $transferAiStatus !== '' ? $transferAiStatus : 'pending' }}
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <div class="satq-progress">
                                        <div class="satq-progress-bar">
                                            <span style="width: {{ $progress }}%;"></span>
                                        </div>
                                        <div class="satq-progress-text">{{ $progress }}%</div>
                                    </div>
                                </td>

                                <td>
                                    <div class="satq-icon-actions">
                                        <button type="button" class="satq-icon-btn satq-icon-btn-info" data-modal-target="{{ $modalInfoId }}" title="Ver detalle">
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                                <circle cx="12" cy="12" r="9"></circle>
                                                <path d="M12 10v6"></path>
                                                <path d="M12 7h.01"></path>
                                            </svg>
                                        </button>

                                        <button type="button" class="satq-icon-btn satq-icon-btn-edit" data-modal-target="{{ $modalEditId }}" title="Editar cotización">
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                                <path d="M12 20h9"></path>
                                                <path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"></path>
                                            </svg>
                                        </button>

                                        <button type="button" class="satq-icon-btn satq-icon-btn-confirm" data-modal-target="{{ $modalConfirmId }}" title="Confirmar cotización">
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                                <path d="M20 6 9 17l-5-5"></path>
                                            </svg>
                                        </button>

                                        <button type="button" class="satq-icon-btn satq-icon-btn-reject" data-modal-target="{{ $modalRejectId }}" title="Rechazar cotización">
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                                <path d="M18 6 6 18"></path>
                                                <path d="m6 6 12 12"></path>
                                            </svg>
                                        </button>

                                        <button type="button" class="satq-icon-btn satq-icon-btn-sat" data-modal-target="{{ $modalSatId }}" title="Solicitud SAT">
                                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                                <path d="M4 19h16"></path>
                                                <path d="M5 15V8a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7"></path>
                                                <path d="M9 10h6"></path>
                                                <path d="M9 13h4"></path>
                                            </svg>
                                        </button>

                                        @if($hasTransferReview)
                                            <button type="button" class="satq-icon-btn satq-icon-btn-warning" data-modal-target="{{ $modalTransferId }}" title="Revisar transferencia">
                                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                                    <path d="M3 7h18"></path>
                                                    <path d="M6 12h12"></path>
                                                    <path d="M10 17h4"></path>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>

                                    {{-- MODAL DETALLE --}}
                                    <div class="satq-modal" id="{{ $modalInfoId }}" aria-hidden="true">
                                        <div class="satq-modal-backdrop" data-modal-close></div>
                                        <div class="satq-modal-dialog satq-modal-dialog-sm">
                                            <div class="satq-modal-head">
                                                <div>
                                                    <h3>Detalle de cotización</h3>
                                                    <p>Folio {{ $shortCode($row['folio'] ?? '', 'SATQ') }} · ID terminado en {{ $last4($row['id'] ?? '') }}</p>
                                                </div>
                                                <button type="button" class="satq-modal-close" data-modal-close>&times;</button>
                                            </div>

                                            <div class="satq-modal-body">
                                                <div class="satq-summary-grid">
                                                    <div class="satq-summary-card">
                                                        <h4>Cliente</h4>
                                                        <div class="satq-summary-list">
                                                            <div class="satq-summary-item"><strong>RFC:</strong> {{ $row['rfc'] ?? '—' }}</div>
                                                            <div class="satq-summary-item"><strong>Razón social:</strong> {{ $row['razon_social'] ?? 'Sin razón social' }}</div>
                                                            <div class="satq-summary-item"><strong>Tipo:</strong> {{ ucfirst((string) ($row['tipo_solicitud'] ?? 'emitidos')) }}</div>
                                                        </div>
                                                    </div>

                                                    <div class="satq-summary-card">
                                                        <h4>Periodo e importes</h4>
                                                        <div class="satq-summary-list">
                                                            <div class="satq-summary-item"><strong>Periodo:</strong> {{ ($row['date_from'] ?? '—') }} al {{ ($row['date_to'] ?? '—') }}</div>
                                                            <div class="satq-summary-item"><strong>XML estimados:</strong> {{ number_format((int) ($row['xml_count'] ?? 0)) }}</div>
                                                            <div class="satq-summary-item"><strong>Subtotal:</strong> {{ $money($row['subtotal'] ?? 0) }}</div>
                                                            <div class="satq-summary-item"><strong>IVA:</strong> {{ $money($row['iva'] ?? 0) }}</div>
                                                            <div class="satq-summary-item"><strong>Total:</strong> {{ $money($row['total'] ?? 0) }}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="satq-summary-card">
                                                    <h4>Concepto</h4>
                                                    <div class="satq-summary-item">{{ $row['concepto'] ?? 'Cotización SAT' }}</div>
                                                </div>

                                                <div class="satq-summary-grid">
                                                    <div class="satq-summary-card">
                                                        <h4>Notas cliente</h4>
                                                        <div class="satq-summary-item">{{ trim((string) ($row['notes'] ?? '')) !== '' ? $row['notes'] : 'Sin notas del cliente' }}</div>
                                                    </div>

                                                    <div class="satq-summary-card">
                                                        <h4>Notas internas</h4>
                                                        <div class="satq-summary-list">
                                                            <div class="satq-summary-item"><strong>Admin:</strong> {{ trim((string) ($row['admin_notes'] ?? '')) !== '' ? $row['admin_notes'] : 'Sin notas' }}</div>
                                                            <div class="satq-summary-item"><strong>Comercial:</strong> {{ trim((string) ($row['commercial_notes'] ?? '')) !== '' ? $row['commercial_notes'] : 'Sin notas' }}</div>
                                                            <div class="satq-summary-item"><strong>Rechazo:</strong> {{ trim((string) ($row['reject_reason'] ?? '')) !== '' ? $row['reject_reason'] : 'Sin motivo registrado' }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                                                        {{-- MODAL EDITAR --}}
                                    <div class="satq-modal" id="{{ $modalEditId }}" aria-hidden="true">
                                        <div class="satq-modal-backdrop" data-modal-close></div>
                                        <div class="satq-modal-dialog satq-modal-dialog-xl">
                                            <div class="satq-modal-head">
                                                <div>
                                                    <h3>Editar cotización</h3>
                                                    <p>Recotiza la solicitud con un flujo parecido a creación y recalcula antes de guardar.</p>
                                                </div>
                                                <button type="button" class="satq-modal-close" data-modal-close>&times;</button>
                                            </div>

                                            <div class="satq-modal-body">
                                                @php
                                                    $editDiscountCode = (string) data_get($row, 'discount_code', data_get($row, 'meta.discount_code', ''));
                                                    $editIvaRate = (int) data_get($row, 'iva_rate', data_get($row, 'meta.iva_rate', 16));
                                                    $editTipoSolicitud = (string) ($row['tipo_solicitud'] ?? 'emitidos');
                                                    $editNotesCliente = (string) ($row['notes'] ?? '');
                                                @endphp

                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.sat.ops.quotes.update', ['id' => $row['id']]) }}"
                                                    class="satq-quote-form"
                                                    data-admin-quote-edit-form="true"
                                                    data-row-id="{{ $rowId }}"
                                                >
                                                    @csrf

                                                    <input type="hidden" name="subtotal" value="{{ number_format((float) ($row['subtotal'] ?? 0), 2, '.', '') }}">
                                                    <input type="hidden" name="iva" value="{{ number_format((float) ($row['iva'] ?? 0), 2, '.', '') }}">
                                                    <input type="hidden" name="total" value="{{ number_format((float) ($row['total'] ?? 0), 2, '.', '') }}">

                                                    <div class="satq-quote-section">
                                                        <div class="satq-quote-section-head">
                                                            <h4>RFC para cotizar</h4>
                                                            <p>Base informativa de la solicitud recibida desde portal cliente.</p>
                                                        </div>

                                                        <div class="satq-quote-grid satq-quote-grid-3">
                                                            <div class="satq-field">
                                                                <label>Folio</label>
                                                                <input
                                                                    type="text"
                                                                    class="satq-input"
                                                                    value="{{ $row['folio'] ?? '' }}"
                                                                    readonly
                                                                >
                                                            </div>

                                                            <div class="satq-field">
                                                                <label>RFC</label>
                                                                <input
                                                                    type="text"
                                                                    name="rfc"
                                                                    class="satq-input"
                                                                    value="{{ $row['rfc'] ?? '' }}"
                                                                    readonly
                                                                >
                                                            </div>

                                                            <div class="satq-field">
                                                                <label>Razón social</label>
                                                                <input
                                                                    type="text"
                                                                    class="satq-input"
                                                                    value="{{ $row['razon_social'] ?? '' }}"
                                                                    readonly
                                                                >
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="satq-quote-section">
                                                        <div class="satq-quote-section-head">
                                                            <h4>Datos de la solicitud</h4>
                                                            <p>Estos campos deben alimentar la recotización en admin.</p>
                                                        </div>

                                                        <div class="satq-quote-grid satq-quote-grid-3">
                                                            <div class="satq-field">
                                                                <label>Tipo de solicitud</label>
                                                                <select name="tipo_solicitud" class="satq-select" required>
                                                                    <option value="emitidos" @selected($editTipoSolicitud === 'emitidos')>Emitidos</option>
                                                                    <option value="recibidos" @selected($editTipoSolicitud === 'recibidos')>Recibidos</option>
                                                                    <option value="ambos" @selected($editTipoSolicitud === 'ambos')>Ambos</option>
                                                                </select>
                                                            </div>

                                                            <div class="satq-field">
                                                                <label>Fecha inicial</label>
                                                                <input
                                                                    type="date"
                                                                    name="date_from"
                                                                    class="satq-input"
                                                                    value="{{ $row['date_from'] ?? '' }}"
                                                                    required
                                                                >
                                                            </div>

                                                            <div class="satq-field">
                                                                <label>Fecha final</label>
                                                                <input
                                                                    type="date"
                                                                    name="date_to"
                                                                    class="satq-input"
                                                                    value="{{ $row['date_to'] ?? '' }}"
                                                                    required
                                                                >
                                                            </div>

                                                            <div class="satq-field">
                                                                <label>XML estimados</label>
                                                                <input
                                                                    type="number"
                                                                    name="xml_count"
                                                                    min="1"
                                                                    step="1"
                                                                    class="satq-input"
                                                                    value="{{ (int) ($row['xml_count'] ?? 0) }}"
                                                                    required
                                                                >
                                                            </div>

                                                            <div class="satq-field">
                                                                <label>Código de descuento</label>
                                                                <input
                                                                    type="text"
                                                                    name="discount_code"
                                                                    class="satq-input"
                                                                    value="{{ $editDiscountCode }}"
                                                                    placeholder="Opcional"
                                                                >
                                                            </div>

                                                            <div class="satq-field">
                                                                <label>IVA %</label>
                                                                <input
                                                                    type="number"
                                                                    name="iva_rate"
                                                                    min="0"
                                                                    max="100"
                                                                    step="1"
                                                                    class="satq-input"
                                                                    value="{{ $editIvaRate }}"
                                                                    required
                                                                >
                                                            </div>
                                                        </div>

                                                        <div class="satq-field">
                                                            <label>Concepto</label>
                                                            <textarea name="concepto" class="satq-textarea" rows="4">{{ $row['concepto'] ?? '' }}</textarea>
                                                        </div>

                                                        <div class="satq-mini-grid-2">
                                                            <div class="satq-field">
                                                                <label>Notas del cliente</label>
                                                                <textarea class="satq-textarea" rows="5" readonly>{{ $editNotesCliente !== '' ? $editNotesCliente : 'Sin notas del cliente' }}</textarea>
                                                            </div>

                                                            <div class="satq-field">
                                                                <label>Notas admin</label>
                                                                <textarea name="admin_notes" class="satq-textarea" rows="5">{{ $row['admin_notes'] ?? '' }}</textarea>
                                                            </div>
                                                        </div>

                                                        <div class="satq-field">
                                                            <label>Notas comerciales</label>
                                                            <textarea name="commercial_notes" class="satq-textarea" rows="4">{{ $row['commercial_notes'] ?? '' }}</textarea>
                                                        </div>
                                                    </div>

                                                    <div class="satq-quote-section satq-quote-section-soft">
                                                        <div class="satq-quote-section-head">
                                                            <h4>Resumen de recotización</h4>
                                                            <p>Después de editar, este bloque debe recalcular subtotal, IVA y total.</p>
                                                        </div>

                                                        <div class="satq-quote-summary-grid">
                                                            <div class="satq-quote-summary-card">
                                                                <span>RFC</span>
                                                                <strong>{{ $row['rfc'] ?? 'Pendiente' }}</strong>
                                                            </div>

                                                            <div class="satq-quote-summary-card">
                                                                <span>Tipo</span>
                                                                <strong>{{ ucfirst($editTipoSolicitud) }}</strong>
                                                            </div>

                                                            <div class="satq-quote-summary-card">
                                                                <span>Periodo</span>
                                                                <strong>{{ ($row['date_from'] ?? '—') }} al {{ ($row['date_to'] ?? '—') }}</strong>
                                                            </div>

                                                            <div class="satq-quote-summary-card">
                                                                <span>XML estimados</span>
                                                                <strong>{{ number_format((int) ($row['xml_count'] ?? 0)) }}</strong>
                                                            </div>
                                                        </div>

                                                        <div class="satq-quote-money-grid">
                                                            <div class="satq-quote-money-card">
                                                                <span>Subtotal</span>
                                                                <strong>{{ $money($row['subtotal'] ?? 0) }}</strong>
                                                            </div>

                                                            <div class="satq-quote-money-card">
                                                                <span>IVA</span>
                                                                <strong>{{ $money($row['iva'] ?? 0) }}</strong>
                                                            </div>

                                                            <div class="satq-quote-money-card is-total">
                                                                <span>Total</span>
                                                                <strong>{{ $money($row['total'] ?? 0) }}</strong>
                                                            </div>
                                                        </div>

                                                        <div class="satq-note-box">
                                                            <strong>Importante:</strong>
                                                            este modal ya queda preparado para editar con la misma lógica funcional de creación.
                                                            En el siguiente paso conectamos el recálculo real para que admin no capture importes manuales.
                                                        </div>
                                                    </div>

                                                    <div class="satq-inline-actions satq-inline-actions-between">
                                                        <button type="button" class="satq-btn satq-btn-light" data-modal-close>Cancelar</button>
                                                        <div class="satq-inline-actions">
                                                            <button type="button" class="satq-btn satq-btn-warning" data-admin-quote-recalc="true">
                                                                Recalcular cotización
                                                            </button>
                                                            <button type="submit" class="satq-btn satq-btn-soft">
                                                                Guardar cambios
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- MODAL CONFIRMAR --}}
                                    <div class="satq-modal" id="{{ $modalConfirmId }}" aria-hidden="true">
                                        <div class="satq-modal-backdrop" data-modal-close></div>
                                        <div class="satq-modal-dialog">
                                            <div class="satq-modal-head">
                                                <div>
                                                    <h3>Confirmar cotización para cliente</h3>
                                                    <p>Define el importe final y registra el correo para notificación.</p>
                                                </div>
                                                <button type="button" class="satq-modal-close" data-modal-close>&times;</button>
                                            </div>

                                            <div class="satq-modal-body">
                                                <form method="POST" action="{{ route('admin.sat.ops.quotes.confirm', ['id' => $row['id']]) }}" class="satq-mini-form">
                                                    @csrf

                                                    <div class="satq-mini-grid">
                                                        <div class="satq-field">
                                                            <label>Subtotal final</label>
                                                            <input
                                                                type="number"
                                                                name="subtotal"
                                                                step="0.01"
                                                                min="0"
                                                                class="satq-input"
                                                                value="{{ number_format((float) ($row['subtotal'] ?? 0), 2, '.', '') }}"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="satq-field">
                                                            <label>IVA final</label>
                                                            <input
                                                                type="number"
                                                                name="iva"
                                                                step="0.01"
                                                                min="0"
                                                                class="satq-input"
                                                                value="{{ number_format((float) ($row['iva'] ?? 0), 2, '.', '') }}"
                                                                required
                                                            >
                                                        </div>

                                                        <div class="satq-field">
                                                            <label>Total final</label>
                                                            <input
                                                                type="number"
                                                                name="total"
                                                                step="0.01"
                                                                min="0"
                                                                class="satq-input"
                                                                value="{{ number_format((float) ($row['total'] ?? 0), 2, '.', '') }}"
                                                                required
                                                            >
                                                        </div>
                                                    </div>

                                                    <div class="satq-field">
                                                        <label>Correo cliente</label>
                                                        <input
                                                            type="email"
                                                            name="customer_email"
                                                            class="satq-input"
                                                            value=""
                                                            placeholder="cliente@dominio.com"
                                                        >
                                                    </div>

                                                    <div class="satq-mini-grid-2">
                                                        <div class="satq-field">
                                                            <label>Notas admin</label>
                                                            <textarea name="admin_notes" class="satq-textarea">{{ $row['admin_notes'] ?? '' }}</textarea>
                                                        </div>

                                                        <div class="satq-field">
                                                            <label>Notas comerciales / diferencias</label>
                                                            <textarea name="commercial_notes" class="satq-textarea">{{ $row['commercial_notes'] ?? '' }}</textarea>
                                                        </div>
                                                    </div>

                                                    <button type="submit" class="satq-btn satq-btn-success">Confirmar y avisar al cliente</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- MODAL RECHAZAR --}}
                                    <div class="satq-modal" id="{{ $modalRejectId }}" aria-hidden="true">
                                        <div class="satq-modal-backdrop" data-modal-close></div>
                                        <div class="satq-modal-dialog satq-modal-dialog-sm">
                                            <div class="satq-modal-head">
                                                <div>
                                                    <h3>Rechazar cotización</h3>
                                                    <p>Registra el motivo y el correo que recibirá la notificación.</p>
                                                </div>
                                                <button type="button" class="satq-modal-close" data-modal-close>&times;</button>
                                            </div>

                                            <div class="satq-modal-body">
                                                <form method="POST" action="{{ route('admin.sat.ops.quotes.reject', ['id' => $row['id']]) }}" class="satq-mini-form">
                                                    @csrf

                                                    <div class="satq-field">
                                                        <label>Correo cliente</label>
                                                        <input
                                                            type="email"
                                                            name="customer_email"
                                                            class="satq-input"
                                                            value=""
                                                            placeholder="cliente@dominio.com"
                                                        >
                                                    </div>

                                                    <div class="satq-field">
                                                        <label>Motivo / notas de rechazo</label>
                                                        <textarea name="reject_reason" class="satq-textarea" required>{{ $row['reject_reason'] ?? '' }}</textarea>
                                                    </div>

                                                    <button type="submit" class="satq-btn satq-btn-danger">Rechazar y notificar</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- MODAL SAT --}}
                                    <div class="satq-modal" id="{{ $modalSatId }}" aria-hidden="true">
                                        <div class="satq-modal-backdrop" data-modal-close></div>
                                        <div class="satq-modal-dialog satq-modal-dialog-sm">
                                            <div class="satq-modal-head">
                                                <div>
                                                    <h3>Preparar solicitud SAT</h3>
                                                    <p>Deja lista la base operativa para el siguiente paso del flujo.</p>
                                                </div>
                                                <button type="button" class="satq-modal-close" data-modal-close>&times;</button>
                                            </div>

                                            <div class="satq-modal-body">
                                                <form method="POST" action="{{ route('admin.sat.ops.quotes.sat_request', ['id' => $row['id']]) }}" class="satq-mini-form">
                                                    @csrf

                                                    <div class="satq-field">
                                                        <label>Notas de solicitud SAT</label>
                                                        <textarea name="sat_request_notes" class="satq-textarea">{{ data_get($row, 'meta.sat_request.notes', '') }}</textarea>
                                                    </div>

                                                    <div class="satq-summary-card">
                                                        <h4>Resumen operativo</h4>
                                                        <div class="satq-summary-list">
                                                            <div class="satq-summary-item"><strong>Estatus SAT:</strong> {{ $row['sat_request_status'] !== '' ? $row['sat_request_status'] : 'pending_setup' }}</div>
                                                            <div class="satq-summary-item">
                                                                <strong>Consulta operativa:</strong>
                                                                <a href="{{ route('admin.sat.ops.quotes.operational_data', ['id' => $row['id']]) }}" target="_blank">Ver datos operativos JSON</a>
                                                            </div>
                                                            <div class="satq-summary-item"><strong>Base RFC/FIEL:</strong> este registro queda preparado para el siguiente paso SAT.</div>
                                                        </div>
                                                    </div>

                                                    <button type="submit" class="satq-btn satq-btn-warning">Marcar solicitud SAT</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                                                        @if($hasTransferReview)
                                        <div class="satq-modal" id="{{ $modalTransferId }}" aria-hidden="true">
                                            <div class="satq-modal-backdrop" data-modal-close></div>
                                            <div class="satq-modal-dialog">
                                                <div class="satq-modal-head">
                                                    <div>
                                                        <h3>Revisión de transferencia</h3>
                                                        <p>Folio {{ $shortCode($row['folio'] ?? '', 'SATQ') }} · ID terminado en {{ $last4($row['id'] ?? '') }}</p>
                                                    </div>
                                                    <button type="button" class="satq-modal-close" data-modal-close>&times;</button>
                                                </div>

                                                <div class="satq-modal-body">
                                                    <div class="satq-summary-grid">
                                                        <div class="satq-summary-card">
                                                            <h4>Datos del comprobante</h4>
                                                            <div class="satq-summary-list">
                                                                <div class="satq-summary-item"><strong>Banco destino:</strong> {{ $transferReview['bank_name'] ?? '—' }}</div>
                                                                <div class="satq-summary-item"><strong>Beneficiario:</strong> {{ $transferReview['account_holder'] ?? '—' }}</div>
                                                                <div class="satq-summary-item"><strong>Referencia:</strong> {{ $transferReview['reference'] ?? '—' }}</div>
                                                                <div class="satq-summary-item"><strong>Fecha transferencia:</strong> {{ $transferReview['transfer_date'] ?? '—' }}</div>
                                                                <div class="satq-summary-item"><strong>Monto reportado:</strong> {{ $money($transferReview['transfer_amount'] ?? 0) }}</div>
                                                                <div class="satq-summary-item"><strong>Monto esperado:</strong> {{ $money($transferReview['expected_amount'] ?? ($row['total'] ?? 0)) }}</div>
                                                                <div class="satq-summary-item"><strong>Ordenante:</strong> {{ $transferReview['payer_name'] ?? '—' }}</div>
                                                                <div class="satq-summary-item"><strong>Banco ordenante:</strong> {{ $transferReview['payer_bank'] ?? '—' }}</div>
                                                                <div class="satq-summary-item"><strong>Archivo:</strong> {{ $transferReview['proof_name'] ?? '—' }}</div>
                                                            </div>
                                                        </div>

                                                        <div class="satq-summary-card">
                                                            <h4>Resultado preliminar</h4>
                                                            <div class="satq-summary-list">
                                                                <div class="satq-summary-item"><strong>Estatus revisión:</strong> {{ $transferReviewStatus !== '' ? $transferReviewStatus : 'pending' }}</div>
                                                                <div class="satq-summary-item"><strong>Estatus IA:</strong> {{ $transferAiStatus !== '' ? $transferAiStatus : 'pending' }}</div>
                                                                <div class="satq-summary-item">
                                                                    <strong>Riesgo inicial:</strong>
                                                                    <span class="satq-risk satq-risk-{{ in_array($transferRisk, ['low','medium','high'], true) ? $transferRisk : 'medium' }}">
                                                                        {{ strtoupper($transferRisk !== '' ? $transferRisk : 'medium') }}
                                                                    </span>
                                                                </div>
                                                                <div class="satq-summary-item"><strong>Enviado:</strong> {{ $transferReview['submitted_at'] ?? '—' }}</div>
                                                            </div>

                                                            @if(!empty($transferFlags))
                                                                <div class="satq-note-box">
                                                                    <strong>Banderas detectadas:</strong>
                                                                    <div class="satq-flag-list">
                                                                        @foreach($transferFlags as $flag)
                                                                            <span class="satq-flag">{{ $flag }}</span>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            @if(!empty($transferReview['notes']))
                                                                <div class="satq-note-box">
                                                                    <strong>Notas cliente:</strong><br>
                                                                    {{ $transferReview['notes'] }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="satq-summary-grid">
                                                        <div class="satq-summary-card">
                                                            <h4>Aprobar transferencia</h4>
                                                            <form method="POST" action="{{ route('admin.sat.ops.quotes.transfer.approve', ['id' => $row['id']]) }}" class="satq-mini-form">
                                                                @csrf

                                                                <div class="satq-field">
                                                                    <label>Correo cliente</label>
                                                                    <input
                                                                        type="email"
                                                                        name="customer_email"
                                                                        class="satq-input"
                                                                        value=""
                                                                        placeholder="cliente@dominio.com"
                                                                    >
                                                                </div>

                                                                <div class="satq-field">
                                                                    <label>Notas de aprobación</label>
                                                                    <textarea name="approval_notes" class="satq-textarea" placeholder="Notas internas de validación"></textarea>
                                                                </div>

                                                                <div class="satq-field">
                                                                    <label for="move_to_download_{{ $rowId }}">Mover directo a descarga</label>
                                                                    <select id="move_to_download_{{ $rowId }}" name="move_to_download" class="satq-select">
                                                                        <option value="1">Sí, pasar a en descarga</option>
                                                                        <option value="0">No, dejar solo pagada</option>
                                                                    </select>
                                                                </div>

                                                                <button type="submit" class="satq-btn satq-btn-success">Aprobar transferencia</button>
                                                            </form>
                                                        </div>

                                                        <div class="satq-summary-card">
                                                            <h4>Rechazar transferencia</h4>
                                                            <form method="POST" action="{{ route('admin.sat.ops.quotes.transfer.reject', ['id' => $row['id']]) }}" class="satq-mini-form">
                                                                @csrf

                                                                <div class="satq-field">
                                                                    <label>Correo cliente</label>
                                                                    <input
                                                                        type="email"
                                                                        name="customer_email"
                                                                        class="satq-input"
                                                                        value=""
                                                                        placeholder="cliente@dominio.com"
                                                                    >
                                                                </div>

                                                                <div class="satq-field">
                                                                    <label>Motivo de rechazo</label>
                                                                    <textarea name="reject_reason" class="satq-textarea" required placeholder="Explica por qué se rechaza el comprobante"></textarea>
                                                                </div>

                                                                <button type="submit" class="satq-btn satq-btn-danger">Rechazar transferencia</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(method_exists($rows, 'links'))
                <div class="satq-pagination">
                    {{ $rows->withQueryString()->links() }}
                </div>
            @endif
        @else
            <div class="satq-empty">
                <strong>No hay cotizaciones registradas.</strong>
                Cuando los clientes comiencen a solicitar cotizaciones SAT, aparecerán aquí para su revisión y seguimiento.
            </div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
    window.SATQ_ADMIN_QUOTES = {
        pageClass: 'page-sat-quotes-admin'
    };
</script>
<script src="{{ asset($jsRel) }}{{ $jsV ? ('?v=' . $jsV) : '' }}"></script>
@endpush