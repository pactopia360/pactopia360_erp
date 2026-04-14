{{-- resources/views/admin/sat/ops/downloads/index.blade.php --}}
{{-- P360 · Admin · SAT Ops · Descargas · Rediseño mejorado de distribución --}}

@extends('layouts.admin')

@section('title', $title ?? 'SAT · Operación · Descargas')
@section('pageClass','page-admin-sat-ops page-admin-sat-ops-downloads')

@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Carbon;

    $backUrl = Route::has('admin.sat.ops.index')
        ? route('admin.sat.ops.index')
        : url('/admin');

    $items                 = $items ?? [];
    $cfdiItems             = $cfdiItems ?? [];
    $filters               = $filters ?? [];
    $cfdiFilters           = $cfdiFilters ?? [];
    $cfdiCounts            = $cfdiCounts ?? [];
    $metadataRecordItems   = $metadataRecordItems ?? collect();
    $metadataRecordFilters = $metadataRecordFilters ?? [];
    $reportRecordItems     = $reportRecordItems ?? collect();
    $reportRecordFilters   = $reportRecordFilters ?? [];
    $paidQuotesForUpload   = $paidQuotesForUpload ?? collect();

    $paidQuotesForUpload = collect($paidQuotesForUpload)->map(function ($quote) {
        $quote = (array) $quote;

        $quote['search_blob'] = mb_strtolower(trim(implode(' ', array_filter([
            (string) ($quote['folio'] ?? ''),
            (string) ($quote['rfc'] ?? ''),
            (string) ($quote['customer'] ?? ''),
            (string) ($quote['label'] ?? ''),
        ]))));

        return $quote;
    })->values();

    $manualUploadModes = [
        'quote'   => 'Desde cotización pagada',
        'profile' => 'Carga directa al perfil',
        'replace' => 'Reemplazo de carga existente',
    ];

    $totalItems  = count($items);
    $totalCfdi   = count($cfdiItems);
    $countCfdiV1 = (int) ($cfdiCounts['v1'] ?? 0);
    $countCfdiV2 = (int) ($cfdiCounts['v2'] ?? 0);

    $totalMetadataRecords = method_exists($metadataRecordItems, 'total')
        ? (int) $metadataRecordItems->total()
        : count($metadataRecordItems);

    $totalReportRecords = method_exists($reportRecordItems, 'total')
        ? (int) $reportRecordItems->total()
        : count($reportRecordItems);

    $statusOptions = [
        ''                => '—',
        'uploaded'        => 'uploaded',
        'uploading'       => 'uploading',
        'processing'      => 'processing',
        'processed'       => 'processed',
        'processed_empty' => 'processed_empty',
        'subido'          => 'subido',
        'procesado'       => 'procesado',
        'error'           => 'error',
    ];

    $reportTypeOptions = [
        'csv_report'  => 'csv_report',
        'xlsx_report' => 'xlsx_report',
        'xls_report'  => 'xls_report',
        'txt_report'  => 'txt_report',
    ];

    $activeFileFiltersCount = collect([
        $filters['q'] ?? null,
        $filters['type'] ?? null,
    ])->filter(fn($v) => filled($v))->count();

    $activeCfdiFiltersCount = collect([
        $cfdiFilters['source'] ?? null,
        $cfdiFilters['q'] ?? null,
        $cfdiFilters['rfc'] ?? null,
        $cfdiFilters['tipo'] ?? null,
        $cfdiFilters['desde'] ?? null,
        $cfdiFilters['hasta'] ?? null,
        $cfdiFilters['periodo'] ?? null,
        $cfdiFilters['older_than_months'] ?? null,
    ])->filter(function ($v) {
        return !is_null($v) && $v !== '' && $v !== 'all' && $v !== '0';
    })->count();

    $activeMetadataFiltersCount = collect([
        $metadataRecordFilters['q'] ?? null,
        $metadataRecordFilters['rfc'] ?? null,
        $metadataRecordFilters['direction'] ?? null,
        $metadataRecordFilters['desde'] ?? null,
        $metadataRecordFilters['hasta'] ?? null,
    ])->filter(fn($v) => filled($v))->count();

    $activeReportFiltersCount = collect([
        $reportRecordFilters['q'] ?? null,
        $reportRecordFilters['rfc'] ?? null,
        $reportRecordFilters['direction'] ?? null,
        $reportRecordFilters['desde'] ?? null,
        $reportRecordFilters['hasta'] ?? null,
    ])->filter(fn($v) => filled($v))->count();
@endphp

@section('content')
    <div class="ops-shell ops-shell--redesign">

        @if(session('success'))
            <div class="ops-alert">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="ops-alert ops-alert--error">
                {{ session('error') }}
            </div>
        @endif

       {{-- CABECERA LIMPIA DEL MÓDULO --}}
       <section class="ops-module-strip">
            <div class="ops-module-strip__main">
                <div class="ops-module-strip__eyebrow">Centro de administración SAT</div>
                <h2 class="ops-module-strip__title">Operación de descargas</h2>
                <p class="ops-module-strip__text">
                    Administra archivos SAT, CFDI indexados, metadata y reportes desde una sola vista, con acceso rápido a cada bloque operativo.
                </p>
            </div>

            <div class="ops-module-strip__stats">
                <div class="ops-module-stat">
                    <span class="ops-module-stat__label">Archivos</span>
                    <strong class="ops-module-stat__value">{{ number_format($totalItems) }}</strong>
                </div>

                <div class="ops-module-stat">
                    <span class="ops-module-stat__label">CFDI</span>
                    <strong class="ops-module-stat__value">{{ number_format($totalCfdi) }}</strong>
                </div>

                <div class="ops-module-stat">
                    <span class="ops-module-stat__label">Metadata</span>
                    <strong class="ops-module-stat__value">{{ number_format($totalMetadataRecords) }}</strong>
                </div>

                <div class="ops-module-stat">
                    <span class="ops-module-stat__label">Reportes</span>
                    <strong class="ops-module-stat__value">{{ number_format($totalReportRecords) }}</strong>
                </div>
            </div>

            <div class="ops-module-strip__actions">
                <a class="p360-btn" href="{{ $backUrl }}">← Volver</a>
                <button type="button" class="p360-btn" onclick="location.reload()">Refrescar</button>
            </div>
        </section>

        {{-- NAVEGACIÓN LIMPIA --}}
        <section class="ops-quick-nav ops-quick-nav--compact">
            <a href="#panel-upload-from-quote" class="ops-quick-nav__item">
                <span class="ops-quick-nav__title">Carga</span>
            </a>

            <a href="#panel-files" class="ops-quick-nav__item">
                <span class="ops-quick-nav__title">Archivos</span>
            </a>

            <a href="#panel-purge" class="ops-quick-nav__item">
                <span class="ops-quick-nav__title">Limpieza</span>
            </a>

            <a href="#panel-cfdi" class="ops-quick-nav__item">
                <span class="ops-quick-nav__title">CFDI</span>
            </a>

            <a href="#panel-metadata" class="ops-quick-nav__item">
                <span class="ops-quick-nav__title">Metadata</span>
            </a>

            <a href="#panel-reports" class="ops-quick-nav__item">
                <span class="ops-quick-nav__title">Reportes</span>
            </a>

            <button type="button" class="ops-quick-nav__item ops-quick-nav__item--button" data-expand-all>
                Expandir todo
            </button>

            <button type="button" class="ops-quick-nav__item ops-quick-nav__item--button" data-collapse-all>
                Contraer todo
            </button>
        </section>

                {{-- CENTRO DE CARGA ADMINISTRATIVA --}}
        <details class="ops-accordion ops-card ops-card--upload-quote" id="panel-upload-from-quote" open>
            <summary class="ops-accordion__summary">
                <div class="ops-accordion__head">
                    <div class="ops-accordion__intro">
                        <div class="ops-card__eyebrow">Centro de carga</div>
                        <div class="ops-card__title">Administración de cargas SAT</div>
                        <div class="ops-card__sub">
                            Registra archivos por cotización, por perfil o como reemplazo administrativo desde una sola zona operativa.
                        </div>
                    </div>

                    <div class="ops-card__side">
                        <div class="ops-counter">{{ number_format(count($paidQuotesForUpload)) }} cotización(es)</div>
                        <div class="ops-counter ops-counter--soft">XML · Metadata · Reporte</div>
                        <div class="ops-accordion__toggle">Expandir / contraer</div>
                    </div>
                </div>
            </summary>

            <div class="ops-accordion__body">
                <div class="ops-upload-admin">
                    <div class="ops-upload-admin__top">
                        <div class="ops-upload-admin__modes">
                            @foreach($manualUploadModes as $modeKey => $modeLabel)
                                <button
                                    type="button"
                                    class="ops-upload-mode {{ $modeKey === 'quote' ? 'is-active' : '' }}"
                                    data-upload-mode="{{ $modeKey }}"
                                >
                                    {{ $modeLabel }}
                                </button>
                            @endforeach
                        </div>

                        <div class="ops-upload-admin__help">
                            <strong>Uso recomendado:</strong>
                            usa cotización cuando exista pago confirmado; usa perfil para cargas manuales sin cotización; usa reemplazo cuando el archivo anterior ya no deba ser el vigente.
                        </div>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('admin.sat.ops.downloads.upload_from_quote') }}"
                        data-action-quote="{{ route('admin.sat.ops.downloads.upload_from_quote') }}"
                        data-action-profile="{{ route('admin.sat.ops.downloads.upload_from_profile') }}"
                        data-action-replace="{{ route('admin.sat.ops.downloads.replace_upload') }}"
                        enctype="multipart/form-data"
                        class="ops-upload-admin__form"
                        id="paidQuoteUploadForm"
                    >
                        @csrf

                        <input type="hidden" name="manual_mode" id="manual_mode" value="quote">

                                                <div class="ops-upload-admin__section">
                            <div class="ops-upload-admin__section-head">
                                <div class="ops-upload-admin__section-title">1. Origen de la carga</div>
                                <div class="ops-upload-admin__section-sub">
                                    Define si esta carga pertenece a una cotización pagada, a una carga directa al perfil o a un reemplazo administrativo.
                                </div>
                            </div>

                            <div class="ops-upload-admin__grid ops-upload-admin__grid--origin">
                                <div class="ops-upload-admin__field ops-upload-admin__field--grow" data-mode-scope="quote">
                                    <label class="ops-label" for="quote_search">Buscar cotización</label>
                                    <input
                                        id="quote_search"
                                        type="search"
                                        class="ops-input"
                                        placeholder="Buscar por folio, RFC o cliente"
                                        autocomplete="off"
                                    >
                                </div>

                                <div class="ops-upload-admin__field ops-upload-admin__field--grow" data-mode-scope="quote">
                                    <label class="ops-label" for="quote_id">Cotización pagada</label>
                                    <select id="quote_id" name="quote_id" class="ops-select" required>
                                        <option value="">Seleccionar cotización pagada</option>
                                        @foreach($paidQuotesForUpload as $quote)
                                            <option
                                                value="{{ $quote['id'] ?? '' }}"
                                                data-rfc="{{ $quote['rfc'] ?? '' }}"
                                                data-folio="{{ $quote['folio'] ?? '' }}"
                                                data-customer="{{ $quote['customer'] ?? '' }}"
                                                data-source-table="{{ $quote['source_table'] ?? '' }}"
                                                data-search="{{ $quote['search_blob'] ?? '' }}"
                                            >
                                                {{ $quote['label'] ?? (($quote['folio'] ?? 'Sin folio') . ' · ' . ($quote['rfc'] ?? 'Sin RFC')) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="ops-upload-admin__field" data-mode-scope="quote">
                                    <label class="ops-label">Origen admin</label>
                                    <div class="ops-upload-admin__readonly" id="manualOriginReadonly">
                                        Cotización pagada
                                    </div>
                                </div>

                                <div class="ops-upload-admin__field ops-upload-admin__field--grow ops-upload-admin__is-hidden" data-mode-scope="profile">
                                    <label class="ops-label" for="profile_reference">Perfil / referencia</label>
                                    <input
                                        id="profile_reference"
                                        class="ops-input"
                                        type="text"
                                        name="profile_reference"
                                        value=""
                                        placeholder="RFC, razón social o referencia interna"
                                    >
                                </div>

                                <div class="ops-upload-admin__field ops-upload-admin__is-hidden" data-mode-scope="profile">
                                    <label class="ops-label" for="admin_notes">Nota admin</label>
                                    <input
                                        id="admin_notes"
                                        class="ops-input"
                                        type="text"
                                        name="admin_notes"
                                        value=""
                                        placeholder="Motivo de carga directa"
                                    >
                                </div>

                                <div class="ops-upload-admin__field ops-upload-admin__is-hidden" data-mode-scope="replace">
                                    <label class="ops-label" for="replace_type">Tipo a reemplazar</label>
                                    <select id="replace_type" name="replace_type" class="ops-select">
                                        <option value="">Seleccionar tipo</option>
                                        <option value="metadata">Metadata</option>
                                        <option value="xml">XML</option>
                                        <option value="report">Reporte</option>
                                        <option value="vault">Bóveda v1</option>
                                        <option value="satdownload">Descarga SAT v1</option>
                                    </select>
                                </div>

                                <div class="ops-upload-admin__field ops-upload-admin__field--grow ops-upload-admin__is-hidden" data-mode-scope="replace">
                                    <label class="ops-label" for="replace_id">ID a reemplazar</label>
                                    <input
                                        id="replace_id"
                                        class="ops-input"
                                        type="text"
                                        name="replace_id"
                                        value=""
                                        placeholder="ID del registro existente"
                                    >
                                </div>

                                <div class="ops-upload-admin__field ops-upload-admin__field--grow ops-upload-admin__is-hidden" data-mode-scope="replace">
                                    <label class="ops-label" for="replacement_reason">Motivo del reemplazo</label>
                                    <input
                                        id="replacement_reason"
                                        class="ops-input"
                                        type="text"
                                        name="replacement_reason"
                                        value=""
                                        placeholder="Describe por qué reemplazas esta carga"
                                    >
                                </div>
                            </div>

                            <div class="ops-upload-admin__notice" id="manualModeNotice">
                                Modo cotización: selecciona una cotización pagada válida antes de registrar la carga.
                            </div>
                        </div>

                        <div class="ops-upload-admin__section">
                            <div class="ops-upload-admin__section-head">
                                <div class="ops-upload-admin__section-title">2. Destino y tipo</div>
                                <div class="ops-upload-admin__section-sub">
                                    Define qué vas a cargar y hacia qué bóveda se va a registrar.
                                </div>
                            </div>

                            <div class="ops-upload-admin__grid ops-upload-admin__grid--config">
                                <div class="ops-upload-admin__field">
                                    <label class="ops-label" for="upload_type">Tipo</label>
                                    <select id="upload_type" name="upload_type" class="ops-select" required>
                                        <option value="">Tipo</option>
                                        <option value="xml">XML</option>
                                        <option value="metadata">Metadata</option>
                                        <option value="report">Reporte</option>
                                    </select>
                                </div>

                                <div class="ops-upload-admin__field">
                                    <label class="ops-label" for="target_vault">Bóveda</label>
                                    <select id="target_vault" name="target_vault" class="ops-select" required>
                                        <option value="">Destino</option>
                                        <option value="v1">V1</option>
                                        <option value="v2">V2</option>
                                    </select>
                                </div>

                                <div class="ops-upload-admin__field">
                                    <label class="ops-label" for="direction">Dirección</label>
                                    <select id="direction" name="direction" class="ops-select">
                                        <option value="">Sin definir</option>
                                        <option value="emitidos">Emitidos</option>
                                        <option value="recibidos">Recibidos</option>
                                    </select>
                                </div>

                                <div class="ops-upload-admin__field">
                                    <label class="ops-label" for="customer_rfc">RFC destino</label>
                                    <input
                                        id="customer_rfc"
                                        class="ops-input"
                                        type="text"
                                        name="customer_rfc"
                                        value=""
                                        placeholder="Auto / manual"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="ops-upload-admin__section">
                            <div class="ops-upload-admin__section-head">
                                <div class="ops-upload-admin__section-title">3. Archivos y confirmación</div>
                                <div class="ops-upload-admin__section-sub">
                                    Sube uno o varios archivos y valida el resumen antes de registrar.
                                </div>
                            </div>

                            <div class="ops-upload-admin__grid ops-upload-admin__grid--files">
                                <div class="ops-upload-admin__field ops-upload-admin__field--grow">
                                    <label class="ops-label" for="files">Archivos</label>
                                    <input
                                        id="files"
                                        class="ops-input"
                                        type="file"
                                        name="files[]"
                                        multiple
                                        required
                                    >
                                </div>

                                <button
                                        type="submit"
                                        class="p360-btn p360-btn--primary"
                                        id="manualUploadSubmit"
                                        onclick="return confirm('¿Confirmas registrar esta carga administrativa con la configuración seleccionada?');"
                                    >
                                        Registrar carga
                                </button>
                            </div>

                            <div id="quoteUploadSummary" class="ops-upload-admin__summary">
                                Selecciona una cotización pagada para ver el resumen.
                            </div>
                        </div>

                                                <div class="ops-upload-admin__footer">
                            <div class="ops-upload-admin__footer-card">
                                <div class="ops-upload-admin__footer-title">Flujos disponibles</div>
                                <div class="ops-upload-admin__footer-text">
                                    Cotización pagada, carga directa al perfil y reemplazo administrativo ya quedan amarrados a rutas separadas.
                                </div>
                            </div>

                            <div class="ops-upload-admin__footer-card">
                                <div class="ops-upload-admin__footer-title">Cierre de entrega</div>
                                <div class="ops-upload-admin__footer-text">
                                    Cuando la carga ya esté completa en metadata, XML y/o reportes, aquí debe cerrarse la solicitud como completada para reflejarlo en cliente v2.
                                </div>

                                <div class="ops-actions-inline" style="margin-top:10px;">
                                    <select id="complete_quote_id" class="ops-select">
                                        <option value="">Seleccionar cotización a finalizar</option>
                                        @foreach($paidQuotesForUpload as $quote)
                                            <option value="{{ $quote['id'] ?? '' }}">
                                                {{ $quote['label'] ?? (($quote['folio'] ?? 'Sin folio') . ' · ' . ($quote['rfc'] ?? 'Sin RFC')) }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <button
                                        type="button"
                                        class="p360-btn p360-btn--primary"
                                        id="markQuoteCompletedBtn"
                                        disabled
                                    >
                                        Finalizar entrega
                                    </button>
                                </div>

                                <div class="ops-upload-admin__footer-text" style="margin-top:8px;">
                                    Este botón queda listo para conectarse al endpoint que marcará la cotización como <strong>completada</strong>.
                                </div>
                            </div>

                            <div class="ops-upload-admin__footer-card">
                                <div class="ops-upload-admin__footer-title">Siguiente mejora útil</div>
                                <div class="ops-upload-admin__footer-text">
                                    Lo siguiente recomendable es agregar una tabla de cargas recientes con editar, reemplazar y eliminar lote completo desde este mismo panel.
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </details>

        {{-- ARCHIVOS --}}
        <details class="ops-accordion ops-card ops-card--files" id="panel-files" open>
            <summary class="ops-accordion__summary">
                <div class="ops-accordion__head">
                    <div class="ops-accordion__intro">
                        <div class="ops-card__eyebrow">Módulo 01</div>
                        <div class="ops-card__title">Archivos SAT cargados</div>
                        <div class="ops-card__sub">
                            Administración central de metadata, XML, reportes, bóveda v1 y descargas SAT v1.
                        </div>
                    </div>

                    <div class="ops-card__side">
                        <div class="ops-counter">{{ number_format($totalItems) }} registro(s)</div>
                        <div class="ops-counter ops-counter--soft">{{ $activeFileFiltersCount }} filtro(s)</div>
                        <div class="ops-accordion__toggle">Expandir / contraer</div>
                    </div>
                </div>
            </summary>

            <div class="ops-accordion__body">
                <div class="ops-section-toolbar">
                    <form method="GET" action="{{ route('admin.sat.ops.downloads.index') }}" class="ops-toolbar ops-toolbar--files">
                        <div class="ops-field ops-field--grow">
                            <label class="ops-label" for="q">Buscar</label>
                            <input
                                id="q"
                                class="ops-input"
                                type="search"
                                name="q"
                                value="{{ $filters['q'] ?? '' }}"
                                placeholder="RFC, archivo, estatus..."
                                autocomplete="off"
                            >
                        </div>

                        <div class="ops-field">
                            <label class="ops-label" for="type">Tipo</label>
                            <select id="type" class="ops-select" name="type">
                                <option value="">Todos</option>
                                <option value="metadata" @selected(($filters['type'] ?? '') === 'metadata')>Metadata</option>
                                <option value="xml" @selected(($filters['type'] ?? '') === 'xml')>XML</option>
                                <option value="report" @selected(($filters['type'] ?? '') === 'report')>Reporte</option>
                                <option value="vault" @selected(($filters['type'] ?? '') === 'vault')>Bóveda v1</option>
                                <option value="satdownload" @selected(($filters['type'] ?? '') === 'satdownload')>Descarga SAT v1</option>
                            </select>
                        </div>

                        <div class="ops-toolbar__actions">
                            <button type="submit" class="p360-btn p360-btn--primary">Filtrar</button>
                            <a class="p360-btn" href="{{ route('admin.sat.ops.downloads.index') }}">Limpiar</a>
                        </div>
                    </form>
                </div>

                <div class="ops-card__head ops-card__head--sub">
                    <div>
                        <div class="ops-card__title">Acciones masivas de archivos</div>
                        <div class="ops-card__sub">Selecciona varios registros y ejecuta la limpieza desde una barra separada.</div>
                    </div>

                    <div class="ops-bulkbar" id="filesBulkBar">
                        <div class="ops-bulkbar__info">
                            Seleccionados: <strong data-selected-files>0</strong>
                        </div>

                        <div class="ops-actions-inline">
                            <select id="file_bulk_action" class="ops-select ops-select--sm">
                                <option value="delete">Eliminar seleccionados</option>
                            </select>

                            <button type="button" class="p360-btn p360-btn--danger p360-btn--sm" id="runFilesBulkAction">
                                Aplicar
                            </button>
                        </div>
                    </div>
                </div>

                <form id="filesBulkForm" method="POST" action="" class="ops-hidden-bulk-form">
                    @csrf
                </form>

                <div class="ops-table-wrap">
                    <table class="ops-table">
                        <thead>
                            <tr>
                                <th class="ops-check-col">
                                    <label class="ops-check">
                                        <input type="checkbox" id="checkAllFiles">
                                        <span></span>
                                    </label>
                                </th>
                                <th>Tipo</th>
                                <th>RFC</th>
                                <th>Archivo</th>
                                <th>Tamaño</th>
                                <th>Extra</th>
                                <th>Estatus</th>
                                <th>Fecha</th>
                                <th class="ta-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                                @php
                                    $bytes = (int) ($item['bytes'] ?? 0);

                                    $sizeLabel = $bytes > 0
                                        ? ($bytes >= 1048576
                                            ? number_format($bytes / 1048576, 2) . ' MB'
                                            : number_format($bytes / 1024, 2) . ' KB')
                                        : '—';

                                    $type      = strtolower((string) ($item['type'] ?? ''));
                                    $status    = strtolower((string) ($item['status'] ?? ''));
                                    $direction = strtolower((string) ($item['direction'] ?? ''));
                                    $statusCss = in_array($status, ['procesado', 'processed', 'subido', 'uploaded', 'error', 'processing', 'processed_empty'], true) ? $status : 'default';
                                    $createdAt = !empty($item['created_at'])
                                        ? Carbon::parse($item['created_at'])->format('d/m/Y H:i')
                                        : '—';

                                    $isMetadata    = $type === 'metadata';
                                    $isXml         = $type === 'xml';
                                    $isReport      = $type === 'report';
                                    $isSatDownload = $type === 'satdownload';

                                    $canDownload   = (bool) ($item['can_download'] ?? true);
                                    $downloadIssue = (string) ($item['download_issue'] ?? '');

                                    $counterLabel = '—';
                                    if ($isMetadata) {
                                        $counterLabel = 'rows_count';
                                    } elseif ($isXml) {
                                        $counterLabel = 'files_count';
                                    } elseif ($isReport) {
                                        $counterLabel = 'rows_count';
                                    }
                                @endphp

                                <tr>
                                    <td class="ops-check-col">
                                        <label class="ops-check">
                                            <input
                                                type="checkbox"
                                                class="file-row-check"
                                                value="{{ $type }}::{{ $item['id'] }}"
                                            >
                                            <span></span>
                                        </label>
                                    </td>

                                    <td>
                                        <span class="ops-chip ops-chip--{{ $type !== '' ? $type : 'metadata' }}">
                                            {{ strtoupper($type !== '' ? $type : '—') }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="ops-rfc">{{ $item['rfc'] ?: '—' }}</div>
                                    </td>

                                    <td>
                                        <div class="ops-file__meta">
                                                ID #{{ $item['id'] }}
                                                @if($direction !== '')
                                                    · {{ strtoupper($direction) }}
                                                @endif
                                                @if($isSatDownload && !$canDownload)
                                                    ·
                                                    @switch($downloadIssue)
                                                        @case('not_ready')
                                                            aún no lista
                                                            @break
                                                        @case('missing_path')
                                                            sin ruta
                                                            @break
                                                        @case('missing_disk')
                                                            sin disco
                                                            @break
                                                        @case('missing_file')
                                                            archivo faltante
                                                            @break
                                                        @case('invalid_disk')
                                                            disco inválido
                                                            @break
                                                        @default
                                                            no disponible
                                                    @endswitch
                                                @endif
                                        </div>
                                    </td>

                                    <td>{{ $sizeLabel }}</td>

                                    <td>
                                        <span class="ops-status">
                                            {{ $counterLabel }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="ops-status ops-status--{{ $statusCss }}">
                                            {{ $item['status'] ?: '—' }}
                                        </span>
                                    </td>

                                    <td>{{ $createdAt }}</td>

                                    <td class="ta-right">
                                        <div class="ops-row-actions">
                                            @if(!$isSatDownload || $canDownload)
                                                <a
                                                    class="p360-btn p360-btn--sm"
                                                    href="{{ route('admin.sat.ops.downloads.download', [$type, $item['id']]) }}"
                                                >
                                                    Descargar
                                                </a>
                                            @else
                                                <span class="p360-btn p360-btn--sm is-disabled" aria-disabled="true" title="Esta descarga aún no está disponible">
                                                    No disponible
                                                </span>
                                            @endif

                                            @if($isMetadata)
                                                <details class="ops-inline-editor">
                                                    <summary class="p360-btn p360-btn--sm">Editar</summary>
                                                    <div class="ops-inline-editor__panel">
                                                        <form method="POST" action="{{ route('admin.sat.ops.downloads.metadata.update', $item['id']) }}">
                                                            @csrf
                                                            @method('PATCH')

                                                            <div class="ops-inline-grid">
                                                                <div class="ops-field">
                                                                    <label class="ops-label">RFC</label>
                                                                    <input class="ops-input" type="text" name="rfc_owner" value="{{ $item['rfc'] ?? '' }}">
                                                                </div>

                                                                <div class="ops-field">
                                                                    <label class="ops-label">Dirección</label>
                                                                    <select class="ops-select" name="direction_detected">
                                                                        <option value="">—</option>
                                                                        <option value="emitidos" @selected(($direction ?? '') === 'emitidos')>emitidos</option>
                                                                        <option value="recibidos" @selected(($direction ?? '') === 'recibidos')>recibidos</option>
                                                                    </select>
                                                                </div>

                                                                <div class="ops-field">
                                                                    <label class="ops-label">Estatus</label>
                                                                    <select class="ops-select" name="status">
                                                                        @foreach($statusOptions as $k => $v)
                                                                            <option value="{{ $k }}" @selected(($item['status'] ?? '') === $k)>{{ $v }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>

                                                                <div class="ops-field ops-field--full">
                                                                    <label class="ops-label">Nombre</label>
                                                                    <input class="ops-input" type="text" name="original_name" value="{{ $item['name'] ?? '' }}">
                                                                </div>
                                                            </div>

                                                            <div class="ops-actions-inline">
                                                                <button type="submit" class="p360-btn p360-btn--primary p360-btn--sm">Guardar</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </details>

                                                <form method="POST" action="{{ route('admin.sat.ops.downloads.metadata.reset_count', $item['id']) }}" onsubmit="return confirm('¿Reiniciar rows_count a 0?');">
                                                    @csrf
                                                    <button type="submit" class="p360-btn p360-btn--sm">Reset</button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.sat.ops.downloads.metadata.recount', $item['id']) }}" onsubmit="return confirm('¿Recalcular rows_count desde metadata items?');">
                                                    @csrf
                                                    <button type="submit" class="p360-btn p360-btn--sm">Recalcular</button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.sat.ops.downloads.metadata.purge_items', $item['id']) }}" onsubmit="return confirm('¿Eliminar metadata items derivados y dejar contador en cero?');">
                                                    @csrf
                                                    <button type="submit" class="p360-btn p360-btn--sm">Limpiar items</button>
                                                </form>

                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.sat.ops.downloads.metadata.destroy_full', $item['id']) }}"
                                                    onsubmit="return confirm('¿Eliminar metadata, items derivados y archivo físico?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                                        Eliminar full
                                                    </button>
                                                </form>
                                            @elseif($isXml)
                                                <details class="ops-inline-editor">
                                                    <summary class="p360-btn p360-btn--sm">Editar</summary>
                                                    <div class="ops-inline-editor__panel">
                                                        <form method="POST" action="{{ route('admin.sat.ops.downloads.xml.update', $item['id']) }}">
                                                            @csrf
                                                            @method('PATCH')

                                                            <div class="ops-inline-grid">
                                                                <div class="ops-field">
                                                                    <label class="ops-label">RFC</label>
                                                                    <input class="ops-input" type="text" name="rfc_owner" value="{{ $item['rfc'] ?? '' }}">
                                                                </div>

                                                                <div class="ops-field">
                                                                    <label class="ops-label">Dirección</label>
                                                                    <select class="ops-select" name="direction_detected">
                                                                        <option value="">—</option>
                                                                        <option value="emitidos" @selected(($direction ?? '') === 'emitidos')>emitidos</option>
                                                                        <option value="recibidos" @selected(($direction ?? '') === 'recibidos')>recibidos</option>
                                                                    </select>
                                                                </div>

                                                                <div class="ops-field">
                                                                    <label class="ops-label">Estatus</label>
                                                                    <select class="ops-select" name="status">
                                                                        @foreach($statusOptions as $k => $v)
                                                                            <option value="{{ $k }}" @selected(($item['status'] ?? '') === $k)>{{ $v }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>

                                                                <div class="ops-field ops-field--full">
                                                                    <label class="ops-label">Nombre</label>
                                                                    <input class="ops-input" type="text" name="original_name" value="{{ $item['name'] ?? '' }}">
                                                                </div>
                                                            </div>

                                                            <div class="ops-actions-inline">
                                                                <button type="submit" class="p360-btn p360-btn--primary p360-btn--sm">Guardar</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </details>

                                                <form method="POST" action="{{ route('admin.sat.ops.downloads.xml.reset_count', $item['id']) }}" onsubmit="return confirm('¿Reiniciar files_count a 0?');">
                                                    @csrf
                                                    <button type="submit" class="p360-btn p360-btn--sm">Reset</button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.sat.ops.downloads.xml.recount', $item['id']) }}" onsubmit="return confirm('¿Recalcular files_count desde CFDI?');">
                                                    @csrf
                                                    <button type="submit" class="p360-btn p360-btn--sm">Recalcular</button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.sat.ops.downloads.xml.purge_cfdi', $item['id']) }}" onsubmit="return confirm('¿Eliminar CFDI ligados a este XML y dejar files_count en cero?');">
                                                    @csrf
                                                    <button type="submit" class="p360-btn p360-btn--sm">Limpiar CFDI</button>
                                                </form>

                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.sat.ops.downloads.xml.destroy_full', $item['id']) }}"
                                                    onsubmit="return confirm('¿Eliminar XML, CFDI ligados y archivo físico?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                                        Eliminar full
                                                    </button>
                                                </form>
                                            @elseif($isReport)
                                                <details class="ops-inline-editor">
                                                    <summary class="p360-btn p360-btn--sm">Editar</summary>
                                                    <div class="ops-inline-editor__panel">
                                                        <form method="POST" action="{{ route('admin.sat.ops.downloads.report.update', $item['id']) }}">
                                                            @csrf
                                                            @method('PATCH')

                                                            <div class="ops-inline-grid">
                                                                <div class="ops-field">
                                                                    <label class="ops-label">RFC</label>
                                                                    <input class="ops-input" type="text" name="rfc_owner" value="{{ $item['rfc'] ?? '' }}">
                                                                </div>

                                                                <div class="ops-field">
                                                                    <label class="ops-label">Tipo reporte</label>
                                                                    <select class="ops-select" name="report_type">
                                                                        @foreach($reportTypeOptions as $k => $v)
                                                                            <option value="{{ $k }}" @selected(($item['report_type'] ?? '') === $k)>{{ $v }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>

                                                                <div class="ops-field">
                                                                    <label class="ops-label">Estatus</label>
                                                                    <select class="ops-select" name="status">
                                                                        @foreach($statusOptions as $k => $v)
                                                                            <option value="{{ $k }}" @selected(($item['status'] ?? '') === $k)>{{ $v }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>

                                                                <div class="ops-field">
                                                                    <label class="ops-label">linked_metadata_upload_id</label>
                                                                    <input class="ops-input" type="number" name="linked_metadata_upload_id" value="{{ $item['linked_metadata_upload_id'] ?? '' }}">
                                                                </div>

                                                                <div class="ops-field">
                                                                    <label class="ops-label">linked_xml_upload_id</label>
                                                                    <input class="ops-input" type="number" name="linked_xml_upload_id" value="{{ $item['linked_xml_upload_id'] ?? '' }}">
                                                                </div>

                                                                <div class="ops-field ops-field--full">
                                                                    <label class="ops-label">Nombre</label>
                                                                    <input class="ops-input" type="text" name="original_name" value="{{ $item['name'] ?? '' }}">
                                                                </div>
                                                            </div>

                                                            <div class="ops-actions-inline">
                                                                <button type="submit" class="p360-btn p360-btn--primary p360-btn--sm">Guardar</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </details>

                                                <form method="POST" action="{{ route('admin.sat.ops.downloads.report.reset_count', $item['id']) }}" onsubmit="return confirm('¿Reiniciar rows_count a 0?');">
                                                    @csrf
                                                    <button type="submit" class="p360-btn p360-btn--sm">Reset</button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.sat.ops.downloads.report.recount', $item['id']) }}" onsubmit="return confirm('¿Recalcular rows_count desde report items?');">
                                                    @csrf
                                                    <button type="submit" class="p360-btn p360-btn--sm">Recalcular</button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.sat.ops.downloads.report.purge_items', $item['id']) }}" onsubmit="return confirm('¿Eliminar report items derivados y dejar contador en cero?');">
                                                    @csrf
                                                    <button type="submit" class="p360-btn p360-btn--sm">Limpiar items</button>
                                                </form>

                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.sat.ops.downloads.report.destroy_full', $item['id']) }}"
                                                    onsubmit="return confirm('¿Eliminar reporte, items derivados y archivo físico?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                                        Eliminar full
                                                    </button>
                                                </form>
                                            @else
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.sat.ops.downloads.delete', [$type, $item['id']]) }}"
                                                    onsubmit="return confirm('¿Seguro que deseas eliminar este archivo? Esta acción también elimina el archivo físico si existe.');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="ops-empty">
                                            No hay archivos para mostrar con los filtros actuales.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        {{-- LIMPIEZA --}}
        <details class="ops-accordion ops-card ops-card--purge" id="panel-purge">
            <summary class="ops-accordion__summary">
                <div class="ops-accordion__head">
                    <div class="ops-accordion__intro">
                        <div class="ops-card__eyebrow">Módulo 02</div>
                        <div class="ops-card__title">Retención y limpieza avanzada</div>
                        <div class="ops-card__sub">
                            Zona separada para ejecutar purgas con filtros controlados y reducir errores en operaciones masivas.
                        </div>
                    </div>

                    <div class="ops-card__side">
                        <div class="ops-counter">{{ number_format($totalCfdi) }} CFDI</div>
                        <div class="ops-counter ops-counter--soft">{{ $activeCfdiFiltersCount }} filtro(s)</div>
                        <div class="ops-accordion__toggle">Expandir / contraer</div>
                    </div>
                </div>
            </summary>

            <div class="ops-accordion__body">
                <form method="POST" action="{{ route('admin.sat.ops.downloads.cfdi.purge') }}" class="ops-filters-grid ops-filters-grid--purge">
                    @csrf

                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_source">Origen</label>
                        <select id="cfdi_source" name="cfdi_source" class="ops-select">
                            <option value="all" @selected(($cfdiFilters['source'] ?? 'all') === 'all')>Ambas bóvedas</option>
                            <option value="vault_cfdi" @selected(($cfdiFilters['source'] ?? '') === 'vault_cfdi')>Bóveda v1</option>
                            <option value="user_cfdi" @selected(($cfdiFilters['source'] ?? '') === 'user_cfdi')>Bóveda v2</option>
                        </select>
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_periodo">Periodo</label>
                        <input
                            id="cfdi_periodo"
                            type="month"
                            name="cfdi_periodo"
                            class="ops-input"
                            value="{{ $cfdiFilters['periodo'] ?? '' }}"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_desde">Desde</label>
                        <input
                            id="cfdi_desde"
                            type="date"
                            name="cfdi_desde"
                            class="ops-input"
                            value="{{ $cfdiFilters['desde'] ?? '' }}"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_hasta">Hasta</label>
                        <input
                            id="cfdi_hasta"
                            type="date"
                            name="cfdi_hasta"
                            class="ops-input"
                            value="{{ $cfdiFilters['hasta'] ?? '' }}"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="older_than_months">Antigüedad</label>
                        <select id="older_than_months" name="older_than_months" class="ops-select">
                            <option value="0" @selected((int) ($cfdiFilters['older_than_months'] ?? 0) === 0)>Sin regla</option>
                            <option value="3" @selected((int) ($cfdiFilters['older_than_months'] ?? 0) === 3)>3 meses</option>
                            <option value="6" @selected((int) ($cfdiFilters['older_than_months'] ?? 0) === 6)>6 meses</option>
                            <option value="12" @selected((int) ($cfdiFilters['older_than_months'] ?? 0) === 12)>12 meses</option>
                        </select>
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_q">Buscar CFDI</label>
                        <input
                            id="cfdi_q"
                            type="search"
                            name="cfdi_q"
                            class="ops-input"
                            value="{{ $cfdiFilters['q'] ?? '' }}"
                            placeholder="UUID, RFC, emisor..."
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_rfc">RFC</label>
                        <input
                            id="cfdi_rfc"
                            type="text"
                            name="cfdi_rfc"
                            class="ops-input"
                            value="{{ $cfdiFilters['rfc'] ?? '' }}"
                            placeholder="AAA010101AAA"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_tipo">Dirección</label>
                        <select id="cfdi_tipo" name="cfdi_tipo" class="ops-select">
                            <option value="" @selected(($cfdiFilters['tipo'] ?? '') === '')>Todos</option>
                            <option value="emitidos" @selected(($cfdiFilters['tipo'] ?? '') === 'emitidos')>Emitidos</option>
                            <option value="recibidos" @selected(($cfdiFilters['tipo'] ?? '') === 'recibidos')>Recibidos</option>
                        </select>
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="mode">Modo</label>
                        <select id="mode" name="mode" class="ops-select">
                            <option value="index_only">Solo índices</option>
                            <option value="with_files">Índices + archivos</option>
                        </select>
                    </div>

                    <div class="ops-actions-inline ops-actions-inline--end">
                        <button
                            type="submit"
                            class="p360-btn p360-btn--solid-danger"
                            onclick="return confirm('¿Seguro que deseas ejecutar la limpieza con estos filtros?');"
                        >
                            Ejecutar limpieza
                        </button>
                    </div>
                </form>
            </div>
        </details>

        {{-- CFDI --}}
        <details class="ops-accordion ops-card ops-card--cfdi" id="panel-cfdi" open>
            <summary class="ops-accordion__summary">
                <div class="ops-accordion__head">
                    <div class="ops-accordion__intro">
                        <div class="ops-card__eyebrow">Módulo 03</div>
                        <div class="ops-card__title">Datos extraídos / CFDI indexados</div>
                        <div class="ops-card__sub">
                            Vista enfocada a búsqueda, revisión y borrado controlado de índices CFDI por origen y dirección.
                        </div>
                    </div>

                    <div class="ops-card__side">
                        <div class="ops-counter">{{ number_format($totalCfdi) }} registro(s)</div>
                        <div class="ops-counter ops-counter--soft">índices listos</div>
                        <div class="ops-accordion__toggle">Expandir / contraer</div>
                    </div>
                </div>
            </summary>

            <div class="ops-accordion__body">
                <form method="GET" action="{{ route('admin.sat.ops.downloads.index') }}" class="ops-filters-grid">
                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_source_filter">Origen</label>
                        <select id="cfdi_source_filter" name="cfdi_source" class="ops-select">
                            <option value="all" @selected(($cfdiFilters['source'] ?? 'all') === 'all')>Ambas bóvedas</option>
                            <option value="vault_cfdi" @selected(($cfdiFilters['source'] ?? '') === 'vault_cfdi')>Bóveda v1</option>
                            <option value="user_cfdi" @selected(($cfdiFilters['source'] ?? '') === 'user_cfdi')>Bóveda v2</option>
                        </select>
                    </div>

                    <div class="ops-field ops-field--grow">
                        <label class="ops-label" for="cfdi_q_filter">Buscar CFDI</label>
                        <input
                            id="cfdi_q_filter"
                            class="ops-input"
                            type="search"
                            name="cfdi_q"
                            value="{{ $cfdiFilters['q'] ?? '' }}"
                            placeholder="UUID, RFC, emisor, receptor..."
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_rfc_filter">RFC</label>
                        <input
                            id="cfdi_rfc_filter"
                            class="ops-input"
                            type="text"
                            name="cfdi_rfc"
                            value="{{ $cfdiFilters['rfc'] ?? '' }}"
                            placeholder="AAA010101AAA"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_tipo_filter">Dirección</label>
                        <select id="cfdi_tipo_filter" class="ops-select" name="cfdi_tipo">
                            <option value="" @selected(($cfdiFilters['tipo'] ?? '') === '')>Todos</option>
                            <option value="emitidos" @selected(($cfdiFilters['tipo'] ?? '') === 'emitidos')>Emitidos</option>
                            <option value="recibidos" @selected(($cfdiFilters['tipo'] ?? '') === 'recibidos')>Recibidos</option>
                        </select>
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_desde_filter">Desde</label>
                        <input
                            id="cfdi_desde_filter"
                            class="ops-input"
                            type="date"
                            name="cfdi_desde"
                            value="{{ $cfdiFilters['desde'] ?? '' }}"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="cfdi_hasta_filter">Hasta</label>
                        <input
                            id="cfdi_hasta_filter"
                            class="ops-input"
                            type="date"
                            name="cfdi_hasta"
                            value="{{ $cfdiFilters['hasta'] ?? '' }}"
                        >
                    </div>

                    <div class="ops-actions-inline">
                        <button type="submit" class="p360-btn p360-btn--primary">Filtrar CFDI</button>
                        <a class="p360-btn" href="{{ route('admin.sat.ops.downloads.index') }}">Limpiar</a>
                    </div>
                </form>

                <div class="ops-card__head ops-card__head--sub">
                    <div>
                        <div class="ops-card__title">Acciones masivas CFDI</div>
                        <div class="ops-card__sub">Borra solo índices o índices + archivos relacionados cuando aplique.</div>
                    </div>

                    <div class="ops-bulkbar" id="cfdiBulkBar">
                        <div class="ops-bulkbar__info">
                            Seleccionados: <strong data-selected-cfdi>0</strong>
                        </div>

                        <div class="ops-actions-inline">
                            <select id="cfdi_bulk_mode" class="ops-select ops-select--sm">
                                <option value="index_only">Borrar solo índices</option>
                                <option value="with_files">Borrar índices + archivos</option>
                            </select>

                            <button type="button" class="p360-btn p360-btn--danger p360-btn--sm" id="runCfdiBulkAction">
                                Aplicar
                            </button>
                        </div>
                    </div>
                </div>

                <form id="cfdiBulkForm" method="POST" action="" class="ops-hidden-bulk-form">
                    @csrf
                </form>

                <div class="ops-table-wrap">
                    <table class="ops-table ops-table--cfdi">
                        <thead>
                            <tr>
                                <th class="ops-check-col">
                                    <label class="ops-check">
                                        <input type="checkbox" id="checkAllCfdi">
                                        <span></span>
                                    </label>
                                </th>
                                <th>Origen</th>
                                <th>UUID</th>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Emisor</th>
                                <th>Receptor</th>
                                <th>Total</th>
                                <th class="ta-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cfdiItems as $row)
                                <tr>
                                    <td class="ops-check-col">
                                        <label class="ops-check">
                                            <input
                                                type="checkbox"
                                                class="cfdi-row-check"
                                                value="{{ $row['source'] }}::{{ $row['id'] }}"
                                            >
                                            <span></span>
                                        </label>
                                    </td>

                                    <td>
                                        <span class="ops-chip {{ ($row['source'] ?? '') === 'vault_cfdi' ? 'ops-chip--vault' : 'ops-chip--xml' }}">
                                            {{ $row['source_ui'] ?? '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="ops-file">
                                            <div class="ops-file__name">{{ $row['uuid'] ?: '—' }}</div>
                                            <div class="ops-file__meta">Cuenta {{ $row['cuenta_id'] ?: '—' }}</div>
                                        </div>
                                    </td>

                                    <td>{{ $row['fecha'] ?: '—' }}</td>

                                    <td>
                                        @if($isSatDownload && !$canDownload)
                                            <span class="ops-status ops-status--warning">
                                                @switch($downloadIssue)
                                                    @case('not_ready')
                                                        Pendiente
                                                        @break

                                                    @case('missing_path')
                                                        Sin archivo
                                                        @break

                                                    @case('missing_disk')
                                                        Sin disco
                                                        @break

                                                    @case('missing_file')
                                                        Archivo faltante
                                                        @break

                                                    @case('invalid_disk')
                                                        Disco inválido
                                                        @break

                                                    @default
                                                        No disponible
                                                @endswitch
                                            </span>
                                        @else
                                            <span class="ops-status">
                                                {{ $counterLabel }}
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="ops-file">
                                            <div class="ops-file__name">{{ $row['rfc_emisor'] ?: '—' }}</div>
                                            <div class="ops-file__meta">{{ $row['nombre_emisor'] ?: '—' }}</div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="ops-file">
                                            <div class="ops-file__name">{{ $row['rfc_receptor'] ?: '—' }}</div>
                                            <div class="ops-file__meta">{{ $row['nombre_receptor'] ?: '—' }}</div>
                                        </div>
                                    </td>

                                    <td>${{ number_format((float) ($row['total'] ?? 0), 2) }}</td>

                                    <td class="ta-right">
                                        <div class="ops-row-actions">
                                            <form
                                                method="POST"
                                                action="{{ route('admin.sat.ops.downloads.cfdi.delete', [$row['source'], $row['id']]) }}"
                                                onsubmit="return confirm('¿Eliminar este registro CFDI?');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="mode" value="index_only">
                                                <button type="submit" class="p360-btn p360-btn--sm">
                                                    Borrar índice
                                                </button>
                                            </form>

                                            <form
                                                method="POST"
                                                action="{{ route('admin.sat.ops.downloads.cfdi.delete', [$row['source'], $row['id']]) }}"
                                                onsubmit="return confirm('¿Eliminar este CFDI y sus archivos relacionados cuando aplique?');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="mode" value="with_files">
                                                <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                                    Índice + archivo
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="ops-empty">
                                            No hay CFDI indexados para mostrar con los filtros actuales.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        {{-- METADATA PORTAL --}}
        <details class="ops-accordion ops-card ops-card--metadata-records" id="panel-metadata">
            <summary class="ops-accordion__summary">
                <div class="ops-accordion__head">
                    <div class="ops-accordion__intro">
                        <div class="ops-card__eyebrow">Módulo 04</div>
                        <div class="ops-card__title">Registros metadata del portal</div>
                        <div class="ops-card__sub">
                            Registros que alimentan la sección de Metadata del portal cliente, con filtros y borrado por lote.
                        </div>
                    </div>

                    <div class="ops-card__side">
                        <div class="ops-counter">{{ number_format($totalMetadataRecords) }} registro(s)</div>
                        <div class="ops-counter ops-counter--soft">{{ $activeMetadataFiltersCount }} filtro(s)</div>
                        <div class="ops-accordion__toggle">Expandir / contraer</div>
                    </div>
                </div>
            </summary>

            <div class="ops-accordion__body">
                <form method="GET" action="{{ route('admin.sat.ops.downloads.index') }}" class="ops-filters-grid">
                    <div class="ops-field ops-field--grow">
                        <label class="ops-label" for="mr_q">Buscar</label>
                        <input
                            id="mr_q"
                            class="ops-input"
                            type="search"
                            name="mr_q"
                            value="{{ $metadataRecordFilters['q'] ?? '' }}"
                            placeholder="UUID, RFC, emisor, receptor..."
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="mr_rfc">RFC</label>
                        <input
                            id="mr_rfc"
                            class="ops-input"
                            type="text"
                            name="mr_rfc"
                            value="{{ $metadataRecordFilters['rfc'] ?? '' }}"
                            placeholder="AAA010101AAA"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="mr_direction">Dirección</label>
                        <select id="mr_direction" class="ops-select" name="mr_direction">
                            <option value="" @selected(($metadataRecordFilters['direction'] ?? '') === '')>Todos</option>
                            <option value="emitidos" @selected(($metadataRecordFilters['direction'] ?? '') === 'emitidos')>Emitidos</option>
                            <option value="recibidos" @selected(($metadataRecordFilters['direction'] ?? '') === 'recibidos')>Recibidos</option>
                        </select>
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="mr_desde">Desde</label>
                        <input
                            id="mr_desde"
                            class="ops-input"
                            type="date"
                            name="mr_desde"
                            value="{{ $metadataRecordFilters['desde'] ?? '' }}"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="mr_hasta">Hasta</label>
                        <input
                            id="mr_hasta"
                            class="ops-input"
                            type="date"
                            name="mr_hasta"
                            value="{{ $metadataRecordFilters['hasta'] ?? '' }}"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="mr_per_page">Filas</label>
                        <select id="mr_per_page" class="ops-select" name="mr_per_page">
                            @foreach([25,50,100,200] as $pp)
                                <option value="{{ $pp }}" @selected((int) ($metadataRecordFilters['per_page'] ?? 50) === $pp)>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ops-actions-inline">
                        <button type="submit" class="p360-btn p360-btn--primary">Filtrar metadata</button>
                        <a class="p360-btn" href="{{ route('admin.sat.ops.downloads.index') }}">Limpiar</a>
                    </div>
                </form>

                <div class="ops-card__head ops-card__head--sub">
                    <div>
                        <div class="ops-card__title">Acciones masivas metadata</div>
                        <div class="ops-card__sub">Selecciona registros, elimina todos los filtrados o elimina un lote completo.</div>
                    </div>

                    <div class="ops-card__side">
                        <div class="ops-bulkbar" id="metadataRecordsBulkBar">
                            <div class="ops-bulkbar__info">
                                Seleccionados: <strong data-selected-metadata-records>0</strong>
                            </div>

                            <div class="ops-actions-inline">
                                <button type="button" class="p360-btn p360-btn--danger p360-btn--sm" id="runMetadataRecordsBulkDelete">
                                    Eliminar seleccionados
                                </button>

                                <form
                                    method="POST"
                                    action="{{ route('admin.sat.ops.downloads.metadata.records.purge_filtered') }}"
                                    onsubmit="return confirm('¿Seguro que deseas eliminar TODOS los registros metadata que cumplan el filtro actual? Esta acción puede borrar miles de registros.');"
                                >
                                    @csrf
                                    <input type="hidden" name="mr_q" value="{{ $metadataRecordFilters['q'] ?? '' }}">
                                    <input type="hidden" name="mr_rfc" value="{{ $metadataRecordFilters['rfc'] ?? '' }}">
                                    <input type="hidden" name="mr_direction" value="{{ $metadataRecordFilters['direction'] ?? '' }}">
                                    <input type="hidden" name="mr_desde" value="{{ $metadataRecordFilters['desde'] ?? '' }}">
                                    <input type="hidden" name="mr_hasta" value="{{ $metadataRecordFilters['hasta'] ?? '' }}">
                                    <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                        Eliminar todos los filtrados
                                    </button>
                                </form>

                                @php
                                    $visibleMetadataBatchId = null;
                                    if (count($metadataRecordItems) > 0) {
                                        $firstMetaRow = null;
                                        foreach ($metadataRecordItems as $metaRowTmp) {
                                            if ((int) ($metaRowTmp->metadata_upload_id ?? 0) > 0) {
                                                $firstMetaRow = $metaRowTmp;
                                                break;
                                            }
                                        }
                                        $visibleMetadataBatchId = $firstMetaRow ? (int) ($firstMetaRow->metadata_upload_id ?? 0) : null;
                                    }
                                @endphp

                                @if((int) $visibleMetadataBatchId > 0)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.sat.ops.downloads.metadata.batch.destroy', $visibleMetadataBatchId) }}"
                                        onsubmit="return confirm('¿Seguro que deseas eliminar el lote completo de metadata #{{ $visibleMetadataBatchId }}? Esto borrará todos los registros del lote, el upload padre y el archivo físico.');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                            Eliminar lote #{{ $visibleMetadataBatchId }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <form id="metadataRecordsBulkForm" method="POST" action="{{ route('admin.sat.ops.downloads.metadata.records.bulk_delete') }}" class="ops-hidden-bulk-form">
                    @csrf
                </form>

                <div class="ops-table-wrap">
                    <table class="ops-table ops-table--cfdi">
                        <thead>
                            <tr>
                                <th class="ops-check-col">
                                    <label class="ops-check">
                                        <input type="checkbox" id="checkAllMetadataRecords">
                                        <span></span>
                                    </label>
                                </th>
                                <th>ID</th>
                                <th>UUID</th>
                                <th>Fecha</th>
                                <th>Dirección</th>
                                <th>Emisor</th>
                                <th>Receptor</th>
                                <th>Monto</th>
                                <th>Estatus</th>
                                <th>Lote</th>
                                <th class="ta-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($metadataRecordItems as $row)
                                <tr>
                                    <td class="ops-check-col">
                                        <label class="ops-check">
                                            <input
                                                type="checkbox"
                                                class="metadata-record-row-check"
                                                value="{{ $row->id }}"
                                            >
                                            <span></span>
                                        </label>
                                    </td>

                                    <td>#{{ $row->id }}</td>

                                    <td>
                                        <div class="ops-file">
                                            <div class="ops-file__name">{{ $row->uuid ?: '—' }}</div>
                                            <div class="ops-file__meta">RFC owner {{ $row->rfc_owner ?: '—' }}</div>
                                        </div>
                                    </td>

                                    <td>{{ optional($row->fecha_emision)->format('Y-m-d H:i:s') ?: '—' }}</td>

                                    <td>
                                        <span class="ops-status">{{ $row->direction ?: '—' }}</span>
                                    </td>

                                    <td>
                                        <div class="ops-file">
                                            <div class="ops-file__name">{{ $row->rfc_emisor ?: '—' }}</div>
                                            <div class="ops-file__meta">{{ $row->nombre_emisor ?: '—' }}</div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="ops-file">
                                            <div class="ops-file__name">{{ $row->rfc_receptor ?: '—' }}</div>
                                            <div class="ops-file__meta">{{ $row->nombre_receptor ?: '—' }}</div>
                                        </div>
                                    </td>

                                    <td>${{ number_format((float) ($row->monto ?? 0), 2) }}</td>
                                    <td>{{ $row->estatus ?: '—' }}</td>
                                    <td>#{{ $row->metadata_upload_id ?: '—' }}</td>

                                    <td class="ta-right">
                                        <div class="ops-row-actions">
                                            <form
                                                method="POST"
                                                action="{{ route('admin.sat.ops.downloads.metadata.records.delete', $row->id) }}"
                                                onsubmit="return confirm('¿Eliminar este registro de metadata del portal?');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                                    Eliminar
                                                </button>
                                            </form>

                                            @if((int) ($row->metadata_upload_id ?? 0) > 0)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.sat.ops.downloads.metadata.batch.destroy', $row->metadata_upload_id) }}"
                                                    onsubmit="return confirm('¿Eliminar el lote completo de metadata #{{ (int) $row->metadata_upload_id }}? Esto borrará todos los registros del lote, el upload padre y el archivo físico.');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                                        Eliminar lote
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11">
                                        <div class="ops-empty">
                                            No hay registros metadata para mostrar con los filtros actuales.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(method_exists($metadataRecordItems, 'links'))
                    <div class="ops-card__foot">
                        {{ $metadataRecordItems->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </details>

        {{-- REPORTES PORTAL --}}
        <details class="ops-accordion ops-card ops-card--report-records" id="panel-reports">
            <summary class="ops-accordion__summary">
                <div class="ops-accordion__head">
                    <div class="ops-accordion__intro">
                        <div class="ops-card__eyebrow">Módulo 05</div>
                        <div class="ops-card__title">Registros reportes del portal</div>
                        <div class="ops-card__sub">
                            Registros usados por la sección de reportes y resumen fiscal del portal cliente.
                        </div>
                    </div>

                    <div class="ops-card__side">
                        <div class="ops-counter">{{ number_format($totalReportRecords) }} registro(s)</div>
                        <div class="ops-counter ops-counter--soft">{{ $activeReportFiltersCount }} filtro(s)</div>
                        <div class="ops-accordion__toggle">Expandir / contraer</div>
                    </div>
                </div>
            </summary>

            <div class="ops-accordion__body">
                <form method="GET" action="{{ route('admin.sat.ops.downloads.index') }}" class="ops-filters-grid">
                    <div class="ops-field ops-field--grow">
                        <label class="ops-label" for="rr_q">Buscar</label>
                        <input
                            id="rr_q"
                            class="ops-input"
                            type="search"
                            name="rr_q"
                            value="{{ $reportRecordFilters['q'] ?? '' }}"
                            placeholder="UUID, RFC, emisor, receptor..."
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="rr_rfc">RFC</label>
                        <input
                            id="rr_rfc"
                            class="ops-input"
                            type="text"
                            name="rr_rfc"
                            value="{{ $reportRecordFilters['rfc'] ?? '' }}"
                            placeholder="AAA010101AAA"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="rr_direction">Dirección</label>
                        <select id="rr_direction" class="ops-select" name="rr_direction">
                            <option value="" @selected(($reportRecordFilters['direction'] ?? '') === '')>Todos</option>
                            <option value="emitidos" @selected(($reportRecordFilters['direction'] ?? '') === 'emitidos')>Emitidos</option>
                            <option value="recibidos" @selected(($reportRecordFilters['direction'] ?? '') === 'recibidos')>Recibidos</option>
                        </select>
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="rr_desde">Desde</label>
                        <input
                            id="rr_desde"
                            class="ops-input"
                            type="date"
                            name="rr_desde"
                            value="{{ $reportRecordFilters['desde'] ?? '' }}"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="rr_hasta">Hasta</label>
                        <input
                            id="rr_hasta"
                            class="ops-input"
                            type="date"
                            name="rr_hasta"
                            value="{{ $reportRecordFilters['hasta'] ?? '' }}"
                        >
                    </div>

                    <div class="ops-field">
                        <label class="ops-label" for="rr_per_page">Filas</label>
                        <select id="rr_per_page" class="ops-select" name="rr_per_page">
                            @foreach([25,50,100,200] as $pp)
                                <option value="{{ $pp }}" @selected((int) ($reportRecordFilters['per_page'] ?? 50) === $pp)>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ops-actions-inline">
                        <button type="submit" class="p360-btn p360-btn--primary">Filtrar reportes</button>
                        <a class="p360-btn" href="{{ route('admin.sat.ops.downloads.index') }}">Limpiar</a>
                    </div>
                </form>

                <div class="ops-card__head ops-card__head--sub">
                    <div>
                        <div class="ops-card__title">Acciones masivas reportes</div>
                        <div class="ops-card__sub">Elimina seleccionados o todos los registros que cumplan el filtro actual.</div>
                    </div>

                    <div class="ops-card__side">
                        <div class="ops-bulkbar" id="reportRecordsBulkBar">
                            <div class="ops-bulkbar__info">
                                Seleccionados: <strong data-selected-report-records>0</strong>
                            </div>

                            <div class="ops-actions-inline">
                                <button type="button" class="p360-btn p360-btn--danger p360-btn--sm" id="runReportRecordsBulkDelete">
                                    Eliminar seleccionados
                                </button>

                                <form
                                    method="POST"
                                    action="{{ route('admin.sat.ops.downloads.report.records.purge_filtered') }}"
                                    onsubmit="return confirm('¿Seguro que deseas eliminar TODOS los registros de reporte que cumplan el filtro actual? Esta acción puede borrar miles de registros.');"
                                >
                                    @csrf
                                    <input type="hidden" name="rr_q" value="{{ $reportRecordFilters['q'] ?? '' }}">
                                    <input type="hidden" name="rr_rfc" value="{{ $reportRecordFilters['rfc'] ?? '' }}">
                                    <input type="hidden" name="rr_direction" value="{{ $reportRecordFilters['direction'] ?? '' }}">
                                    <input type="hidden" name="rr_desde" value="{{ $reportRecordFilters['desde'] ?? '' }}">
                                    <input type="hidden" name="rr_hasta" value="{{ $reportRecordFilters['hasta'] ?? '' }}">
                                    <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                        Eliminar todos los filtrados
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="reportRecordsBulkForm" method="POST" action="{{ route('admin.sat.ops.downloads.report.records.bulk_delete') }}" class="ops-hidden-bulk-form">
                    @csrf
                </form>

                <div class="ops-table-wrap">
                    <table class="ops-table ops-table--cfdi">
                        <thead>
                            <tr>
                                <th class="ops-check-col">
                                    <label class="ops-check">
                                        <input type="checkbox" id="checkAllReportRecords">
                                        <span></span>
                                    </label>
                                </th>
                                <th>ID</th>
                                <th>UUID</th>
                                <th>Fecha</th>
                                <th>Dirección</th>
                                <th>Emisor</th>
                                <th>Receptor</th>
                                <th>Tipo</th>
                                <th>Total</th>
                                <th>Lote</th>
                                <th class="ta-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportRecordItems as $row)
                                <tr>
                                    <td class="ops-check-col">
                                        <label class="ops-check">
                                            <input
                                                type="checkbox"
                                                class="report-record-row-check"
                                                value="{{ $row->id }}"
                                            >
                                            <span></span>
                                        </label>
                                    </td>

                                    <td>#{{ $row->id }}</td>

                                    <td>
                                        <div class="ops-file">
                                            <div class="ops-file__name">{{ $row->uuid ?: '—' }}</div>
                                            <div class="ops-file__meta">RFC owner {{ $row->rfc_owner ?: '—' }}</div>
                                        </div>
                                    </td>

                                    <td>{{ optional($row->fecha_emision)->format('Y-m-d H:i:s') ?: '—' }}</td>
                                    <td><span class="ops-status">{{ $row->direction ?: '—' }}</span></td>

                                    <td>
                                        <div class="ops-file">
                                            <div class="ops-file__name">{{ $row->emisor_rfc ?: '—' }}</div>
                                            <div class="ops-file__meta">{{ $row->emisor_nombre ?: '—' }}</div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="ops-file">
                                            <div class="ops-file__name">{{ $row->receptor_rfc ?: '—' }}</div>
                                            <div class="ops-file__meta">{{ $row->receptor_nombre ?: '—' }}</div>
                                        </div>
                                    </td>

                                    <td>{{ $row->tipo_comprobante ?: '—' }}</td>
                                    <td>${{ number_format((float) ($row->total ?? 0), 2) }}</td>
                                    <td>#{{ $row->report_upload_id ?: '—' }}</td>

                                    <td class="ta-right">
                                        <div class="ops-row-actions">
                                            <form
                                                method="POST"
                                                action="{{ route('admin.sat.ops.downloads.report.records.delete', $row->id) }}"
                                                onsubmit="return confirm('¿Eliminar este registro de reporte del portal?');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                                    Eliminar
                                                </button>
                                            </form>

                                            @if((int) ($row->report_upload_id ?? 0) > 0)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.sat.ops.downloads.report.batch.destroy', $row->report_upload_id) }}"
                                                    onsubmit="return confirm('¿Eliminar el lote completo de reporte #{{ (int) $row->report_upload_id }}? Esto borrará todos los registros del lote, el upload padre y el archivo físico.');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="p360-btn p360-btn--danger p360-btn--sm">
                                                        Eliminar lote
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11">
                                        <div class="ops-empty">
                                            No hay registros de reporte para mostrar con los filtros actuales.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(method_exists($reportRecordItems, 'links'))
                    <div class="ops-card__foot">
                        {{ $reportRecordItems->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </details>

    </div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/pages/sat-ops-downloads.css') }}?v={{ filemtime(public_path('assets/admin/css/pages/sat-ops-downloads.css')) }}">
@endpush

@push('scripts')
<script>
window.p360SatOpsDownloads = {
    bulkFilesDeleteUrl: @json(route('admin.sat.ops.downloads.bulk.files.delete')),
    bulkCfdiDeleteUrl: @json(route('admin.sat.ops.downloads.bulk.cfdi.delete')),
    bulkMetadataRecordsDeleteUrl: @json(route('admin.sat.ops.downloads.metadata.records.bulk_delete')),
    bulkReportRecordsDeleteUrl: @json(route('admin.sat.ops.downloads.report.records.bulk_delete')),
    uploadFromQuoteUrl: @json(route('admin.sat.ops.downloads.upload_from_quote')),
    uploadFromProfileUrl: @json(route('admin.sat.ops.downloads.upload_from_profile')),
    replaceUploadUrl: @json(route('admin.sat.ops.downloads.replace_upload')),
    paidQuotes: @json(collect($paidQuotesForUpload ?? [])->values())
};
</script>
<script src="{{ asset('assets/admin/js/pages/sat-ops-downloads.js') }}?v={{ filemtime(public_path('assets/admin/js/pages/sat-ops-downloads.js')) }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const completeSelect = document.getElementById('complete_quote_id');
    const completeBtn = document.getElementById('markQuoteCompletedBtn');

    if (!completeSelect || !completeBtn) {
        return;
    }

    const syncCompleteState = () => {
        completeBtn.disabled = String(completeSelect.value || '').trim() === '';
    };

    completeSelect.addEventListener('change', syncCompleteState);
    syncCompleteState();
});
</script>
@endpush