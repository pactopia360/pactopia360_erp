{{-- resources/views/admin/sat/ops/downloads/index.blade.php --}}
{{-- P360 · Admin · SAT Ops · Descargas (v2.1 limpio y corregido) --}}

@extends('layouts.admin')

@section('title', $title ?? 'SAT · Operación · Descargas')
@section('pageClass','page-admin-sat-ops page-admin-sat-ops-downloads')

@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Carbon;

    $backUrl = Route::has('admin.sat.ops.index')
        ? route('admin.sat.ops.index')
        : url('/admin');

    $items        = $items ?? [];
    $cfdiItems    = $cfdiItems ?? [];
    $filters      = $filters ?? [];
    $cfdiFilters  = $cfdiFilters ?? [];
    $cfdiCounts   = $cfdiCounts ?? [];

    $totalItems    = count($items);
    $countMetadata = collect($items)->where('type', 'metadata')->count();
    $countXml      = collect($items)->where('type', 'xml')->count();
    $countReport   = collect($items)->where('type', 'report')->count();
    $countVault    = collect($items)->where('type', 'vault')->count();
    $countSatDl    = collect($items)->where('type', 'satdownload')->count();

    $totalCfdi   = count($cfdiItems);
    $countCfdiV1 = (int) ($cfdiCounts['v1'] ?? 0);
    $countCfdiV2 = (int) ($cfdiCounts['v2'] ?? 0);

    $typeFilterUi = !empty($filters['type']) ? strtoupper((string) $filters['type']) : 'ALL';
@endphp

@section('page-header')
    <div class="p360-ph">
        <div class="p360-ph-left">
            <div class="p360-ph-kicker">ADMIN · SAT OPS</div>
            <h1 class="p360-ph-title">Descargas</h1>
            <div class="p360-ph-sub">
                Administración centralizada de archivos cargados desde el portal cliente.
            </div>
        </div>

        <div class="p360-ph-right">
            <a class="p360-btn" href="{{ $backUrl }}">← Volver</a>
            <button type="button" class="p360-btn" onclick="location.reload()">Refrescar</button>
        </div>
    </div>
@endsection

@section('content')
    <div class="ops-shell">

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

        <section class="ops-topbar">
            <div>
                <div class="ops-topbar__title">Archivos SAT cargados</div>
                <div class="ops-topbar__sub">
                    Consulta, filtra, descarga y elimina archivos desde un panel compacto.
                </div>

                <div class="ops-pills">
                    <span class="ops-pill">Metadata {{ number_format($countMetadata) }}</span>
                    <span class="ops-pill">XML {{ number_format($countXml) }}</span>
                    <span class="ops-pill">Reportes {{ number_format($countReport) }}</span>
                    <span class="ops-pill">Bóveda v1 {{ number_format($countVault) }}</span>
                    <span class="ops-pill">SAT v1 {{ number_format($countSatDl) }}</span>
                    <span class="ops-pill">CFDI v1 {{ number_format($countCfdiV1) }}</span>
                    <span class="ops-pill">CFDI v2 {{ number_format($countCfdiV2) }}</span>
                </div>
            </div>

            <div class="ops-mini-stats">
                <div class="ops-mini-stat">
                    <span class="ops-mini-stat__label">Archivos</span>
                    <strong class="ops-mini-stat__value">{{ number_format($totalItems) }}</strong>
                </div>

                <div class="ops-mini-stat">
                    <span class="ops-mini-stat__label">CFDI</span>
                    <strong class="ops-mini-stat__value">{{ number_format($totalCfdi) }}</strong>
                </div>

                <div class="ops-mini-stat">
                    <span class="ops-mini-stat__label">Filtro</span>
                    <strong class="ops-mini-stat__value">{{ $typeFilterUi }}</strong>
                </div>
            </div>
        </section>

        <form method="GET" action="{{ route('admin.sat.ops.downloads.index') }}" class="ops-toolbar">
            <div class="ops-field">
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

        <section class="ops-card">
            <div class="ops-card__head">
                <div>
                    <div class="ops-card__title">Listado de archivos</div>
                    <div class="ops-card__sub">Vista consolidada de SAT Bóveda v1 y SAT Bóveda v2.</div>
                </div>

                <div class="ops-card__side">
                    <div class="ops-counter">{{ number_format($totalItems) }} registro(s)</div>

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
            </div>

            <form id="filesBulkForm" method="POST" action="" class="ops-hidden-bulk-form">
                @csrf
                @method('DELETE')
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
                                $statusCss = in_array($status, ['procesado', 'subido', 'error'], true) ? $status : 'default';
                                $createdAt = !empty($item['created_at'])
                                    ? Carbon::parse($item['created_at'])->format('d/m/Y H:i')
                                    : '—';
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
                                    <div class="ops-file">
                                        <div class="ops-file__name">{{ $item['name'] ?: 'Sin nombre' }}</div>
                                        <div class="ops-file__meta">
                                            ID #{{ $item['id'] }}
                                            @if($direction !== '')
                                                · {{ strtoupper($direction) }}
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td>{{ $sizeLabel }}</td>

                                <td>
                                    <span class="ops-status ops-status--{{ $statusCss }}">
                                        {{ $item['status'] ?: '—' }}
                                    </span>
                                </td>

                                <td>{{ $createdAt }}</td>

                                <td class="ta-right">
                                    <div class="ops-row-actions">
                                        <a
                                            class="p360-btn p360-btn--sm"
                                            href="{{ route('admin.sat.ops.downloads.download', [$type, $item['id']]) }}"
                                        >
                                            Descargar
                                        </a>

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
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="ops-empty">
                                        No hay archivos para mostrar con los filtros actuales.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ops-card">
            <div class="ops-card__head">
                <div>
                    <div class="ops-card__title">Retención y limpieza</div>
                    <div class="ops-card__sub">Limpieza compacta de CFDI por filtros específicos.</div>
                </div>

                <div class="ops-card__side">
                    <div class="ops-counter">{{ number_format($totalCfdi) }} CFDI</div>
                </div>
            </div>

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

                <div class="ops-actions-inline">
                    <button
                        type="submit"
                        class="p360-btn p360-btn--solid-danger"
                        onclick="return confirm('¿Seguro que deseas ejecutar la limpieza con estos filtros?');"
                    >
                        Ejecutar limpieza
                    </button>
                </div>
            </form>
        </section>

        <section class="ops-card">
            <div class="ops-card__head">
                <div>
                    <div class="ops-card__title">Datos extraídos / CFDI indexados</div>
                    <div class="ops-card__sub">Administración compacta de registros CFDI.</div>
                </div>

                <div class="ops-card__side">
                    <div class="ops-counter">{{ number_format($totalCfdi) }} registro(s)</div>

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
            </div>

            <form method="GET" action="{{ route('admin.sat.ops.downloads.index') }}" class="ops-filters-grid">
                <div class="ops-field">
                    <label class="ops-label" for="cfdi_source_filter">Origen</label>
                    <select id="cfdi_source_filter" name="cfdi_source" class="ops-select">
                        <option value="all" @selected(($cfdiFilters['source'] ?? 'all') === 'all')>Ambas bóvedas</option>
                        <option value="vault_cfdi" @selected(($cfdiFilters['source'] ?? '') === 'vault_cfdi')>Bóveda v1</option>
                        <option value="user_cfdi" @selected(($cfdiFilters['source'] ?? '') === 'user_cfdi')>Bóveda v2</option>
                    </select>
                </div>

                <div class="ops-field">
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
                                    <span class="ops-status">
                                        {{ $row['direction'] ?: ($row['tipo'] ?: '—') }}
                                    </span>
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
        </section>

    </div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/pages/sat-ops-downloads.css') }}?v={{ filemtime(public_path('assets/admin/css/pages/sat-ops-downloads.css')) }}">
@endpush

@push('scripts')
<script>
window.p360SatOpsDownloads = {
    bulkFilesDeleteUrl: @json(route('admin.sat.ops.downloads.bulk.files.delete')),
    bulkCfdiDeleteUrl: @json(route('admin.sat.ops.downloads.bulk.cfdi.delete'))
};
</script>
<script src="{{ asset('assets/admin/js/pages/sat-ops-downloads.js') }}?v={{ filemtime(public_path('assets/admin/js/pages/sat-ops-downloads.js')) }}"></script>
@endpush