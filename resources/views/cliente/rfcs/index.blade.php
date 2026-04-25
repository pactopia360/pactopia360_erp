{{-- resources/views/cliente/rfcs/index.blade.php --}}
@extends('layouts.cliente')

@section('title', 'RFC / Emisores')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/pages/rfcs.css') }}?v={{ time() }}">
@endpush

@section('content')
@php
    $emisores = collect($emisores ?? []);

    $stats = $stats ?? [
        'total' => $emisores->count(),
        'activos' => 0,
        'con_csd' => 0,
        'con_series' => 0,
    ];

    $rtNuevoCfdi = Route::has('cliente.facturacion.create')
        ? route('cliente.facturacion.create')
        : url('/cliente/facturacion/nuevo');
@endphp

<div class="rfcs-page">

    @if(session('ok'))
        <div class="rfcs-alert ok">{{ session('ok') }}</div>
    @endif

    @if($errors->any())
        <div class="rfcs-alert error">{{ $errors->first() }}</div>
    @endif

    <section class="rfcs-hero">
        <div>
            <span class="rfcs-kicker">Configuración fiscal</span>
            <h1>RFC / Emisores</h1>
            <p>
                Centraliza alta, baja, cambios, CSD, FIEL, series, folios, personalización PDF,
                correo y estado de timbrado de todos tus RFC emisores.
            </p>
        </div>

        <div class="rfcs-hero-actions">
            <a href="{{ $rtNuevoCfdi }}" class="rfcs-btn ghost">Nuevo CFDI</a>
            <button type="button" class="rfcs-btn primary" data-open-rfc-modal="create">+ Agregar RFC</button>
        </div>
    </section>

    <section class="rfcs-kpis">
        <div class="rfcs-kpi">
            <small>Total RFC</small>
            <strong>{{ number_format($stats['total'] ?? 0) }}</strong>
            <span>Registrados</span>
        </div>

        <div class="rfcs-kpi">
            <small>Activos</small>
            <strong>{{ number_format($stats['activos'] ?? 0) }}</strong>
            <span>Disponibles para CFDI</span>
        </div>

        <div class="rfcs-kpi">
            <small>Con CSD</small>
            <strong>{{ number_format($stats['con_csd'] ?? 0) }}</strong>
            <span>Listos para timbrar</span>
        </div>

        <div class="rfcs-kpi">
            <small>Series</small>
            <strong>{{ number_format($stats['con_series'] ?? 0) }}</strong>
            <span>Con folios configurados</span>
        </div>
    </section>

    <section class="rfcs-card">
        <div class="rfcs-card-head">
            <div>
                <h2>Emisores fiscales</h2>
                <p>Administra los RFC que podrán emitir CFDI dentro de Pactopia360.</p>
            </div>

            <div class="rfcs-tools">
                <input type="search" id="rfcsSearch" placeholder="Buscar RFC / razón social...">

                <select id="rfcsStatusFilter">
                    <option value="">Todos</option>
                    <option value="activo">Activos</option>
                    <option value="inactivo">Inactivos</option>
                    <option value="sin_csd">Sin CSD</option>
                    <option value="sin_series">Sin series</option>
                </select>
            </div>
        </div>

        <div class="rfcs-table-wrap">
            <table class="rfcs-table">
                <thead>
                    <tr>
                        <th>RFC</th>
                        <th>Razón social</th>
                        <th>Salud</th>
                        <th>Régimen / CP</th>
                        <th>FIEL</th>
                        <th>CSD</th>
                        <th>Serie</th>
                        <th>Logo</th>
                        <th>Correo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($emisores as $emisor)
                        @php
                            $meta = is_array($emisor->meta ?? null) ? $emisor->meta : [];

                            $config = (array) data_get($meta, 'config_fiscal', []);
                            $direccion = (array) data_get($meta, 'direccion', []);
                            $branding = (array) data_get($meta, 'branding', []);
                            $emailCfg = (array) data_get($meta, 'email', []);
                            $series = array_values((array) data_get($meta, 'series', []));
                            $complementos = array_values((array) data_get($meta, 'complementos', []));

                            $status = (data_get($meta, 'is_active', true) === false || data_get($meta, 'is_active') === '0')
                                ? 'inactivo'
                                : 'activo';

                            $rfc = strtoupper((string)($emisor->rfc ?? ''));
                            $razonSocial = trim((string)($emisor->razon_social ?? ''));
                            $nombreComercial = trim((string)(data_get($config, 'nombre_comercial') ?? ''));
                            $emailPrincipal = trim((string)(data_get($config, 'email') ?? ''));
                            $telefono = trim((string)(data_get($config, 'telefono') ?? ''));
                            $sitioWeb = trim((string)(data_get($config, 'sitio_web') ?? ''));

                            $regimenFiscal = trim((string)(data_get($config, 'regimen_fiscal') ?? ''));
                            $codigoPostal = trim((string)(data_get($direccion, 'codigo_postal') ?? ''));

                            $hasFiel = !empty($emisor->fiel_cer_path ?? null)
                                || !empty($emisor->fiel_key_path ?? null)
                                || !empty($emisor->cer_path ?? null)
                                || !empty($emisor->key_path ?? null)
                                || !empty(data_get($meta, 'fiel'));

                            $hasCsd = (!empty($emisor->csd_cer_path ?? null) && !empty($emisor->csd_key_path ?? null))
                                || !empty(data_get($meta, 'csd'));

                            $hasLogo = !empty(data_get($branding, 'logo_path'));
                            $hasCorreo = !empty(data_get($emailCfg, 'correo_facturacion')) || !empty($emailPrincipal);

                            $serieDefault = collect($series)->firstWhere('is_default', true) ?: ($series[0] ?? null);
                            $serieLabel = $serieDefault
                                ? (($serieDefault['serie'] ?? 'S/S') . ' · Folio ' . ($serieDefault['folio_actual'] ?? 0))
                                : 'Sin serie';

                            $csdVigencia = data_get($meta, 'csd_vigencia_hasta') ?: null;

                            $healthScore = 0;
                            $healthScore += $status === 'activo' ? 15 : 0;
                            $healthScore += $razonSocial !== '' ? 12 : 0;
                            $healthScore += $regimenFiscal !== '' ? 13 : 0;
                            $healthScore += strlen($codigoPostal) === 5 ? 13 : 0;
                            $healthScore += $hasFiel ? 10 : 0;
                            $healthScore += $hasCsd ? 20 : 0;
                            $healthScore += count($series) > 0 ? 10 : 0;
                            $healthScore += $hasCorreo ? 4 : 0;
                            $healthScore += $hasLogo ? 3 : 0;

                            $healthClass = $healthScore >= 80 ? 'ok' : ($healthScore >= 50 ? 'warn' : 'muted');
                            $readyToStamp = $status === 'activo'
                                && $razonSocial !== ''
                                && $regimenFiscal !== ''
                                && strlen($codigoPostal) === 5
                                && $hasCsd
                                && count($series) > 0;

                            $payload = [
                                'id' => $emisor->id,
                                'rfc' => $rfc,
                                'razon_social' => $razonSocial,
                                'nombre_comercial' => $nombreComercial,
                                'email' => $emailPrincipal,
                                'telefono' => $telefono,
                                'sitio_web' => $sitioWeb,
                                'regimen_fiscal' => $regimenFiscal,
                                'tipo_origen' => data_get($config, 'tipo_origen', $emisor->tipo_origen ?? 'interno'),
                                'source_label' => data_get($config, 'source_label', $emisor->source_label ?? ''),
                                'status' => $status,
                                'meta' => $meta,
                                'direccion' => $direccion,
                                'certificados' => [
                                    'fiel' => [
                                        'cer_path' => $emisor->fiel_cer_path ?? $emisor->cer_path ?? data_get($meta, 'fiel.cer'),
                                        'key_path' => $emisor->fiel_key_path ?? $emisor->key_path ?? data_get($meta, 'fiel.key'),
                                    ],
                                    'csd' => [
                                        'cer_path' => $emisor->csd_cer_path ?? data_get($meta, 'csd.cer'),
                                        'key_path' => $emisor->csd_key_path ?? data_get($meta, 'csd.key'),
                                    ],
                                ],
                                'series' => $series,
                                'csd_serie' => data_get($meta, 'csd_serie'),
                                'csd_vigencia_hasta' => $csdVigencia,
                                'csd_cer_path' => $emisor->csd_cer_path ?? null,
                                'csd_key_path' => $emisor->csd_key_path ?? null,
                                'fiel_cer_path' => $emisor->fiel_cer_path ?? $emisor->cer_path ?? null,
                                'fiel_key_path' => $emisor->fiel_key_path ?? $emisor->key_path ?? null,
                            ];

                            $searchText = strtolower(implode(' ', [
                                $rfc,
                                $razonSocial,
                                $nombreComercial,
                                $emailPrincipal,
                                $regimenFiscal,
                                $codigoPostal,
                                $serieLabel,
                            ]));
                        @endphp

                        <tr
                            data-rfc-row
                            data-search="{{ e($searchText) }}"
                            data-status="{{ $status }}"
                            data-has-csd="{{ $hasCsd ? '1' : '0' }}"
                            data-has-series="{{ count($series) > 0 ? '1' : '0' }}"
                        >
                            <td>
                                <strong class="rfcs-rfc">{{ $rfc }}</strong>
                                <span class="rfcs-muted">{{ $nombreComercial ?: 'Sin nombre comercial' }}</span>
                            </td>

                            <td>
                                <strong>{{ $razonSocial ?: 'Sin razón social registrada' }}</strong>
                                <span class="rfcs-muted">{{ $emailPrincipal ?: 'Sin correo principal' }}</span>
                            </td>

                            <td>
                                <span class="rfcs-badge {{ $readyToStamp ? 'ok' : $healthClass }}">
                                    {{ $readyToStamp ? 'Listo' : ($healthScore . '%') }}
                                </span>
                            </td>

                            <td>
                                <strong>{{ $regimenFiscal ?: 'Pendiente' }}</strong>
                                <span class="rfcs-muted">CP: {{ $codigoPostal ?: 'Pendiente' }}</span>
                            </td>

                            <td>
                                <span class="rfcs-badge {{ $hasFiel ? 'ok' : 'warn' }}">
                                    {{ $hasFiel ? 'Cargada' : 'Pendiente' }}
                                </span>
                            </td>

                            <td>
                                <span class="rfcs-badge {{ $hasCsd ? 'ok' : 'warn' }}">
                                    {{ $hasCsd ? 'Listo' : 'Pendiente' }}
                                </span>
                                <span class="rfcs-muted">{{ $csdVigencia ? ('Vence: '.$csdVigencia) : 'Sin vigencia' }}</span>
                            </td>

                            <td>
                                <strong>{{ $serieLabel }}</strong>
                                <span class="rfcs-muted">{{ count($series) }} serie(s)</span>
                            </td>

                            <td>
                                <span class="rfcs-badge {{ $hasLogo ? 'ok' : 'muted' }}">
                                    {{ $hasLogo ? 'Logo' : 'Sin logo' }}
                                </span>
                            </td>

                            <td>
                                <span class="rfcs-badge {{ $hasCorreo ? 'ok' : 'warn' }}">
                                    {{ $hasCorreo ? 'Configurado' : 'Pendiente' }}
                                </span>
                            </td>

                            <td class="text-end">
                                <div class="rfcs-actions">
                                    <button type="button"
                                            class="rfcs-icon-btn"
                                            data-open-rfc-modal="edit"
                                            data-rfc='@json($payload)'>
                                        Editar
                                    </button>

                                    <button type="button"
                                            class="rfcs-icon-btn"
                                            data-open-rfc-modal="certs"
                                            data-rfc='@json($payload)'>
                                        CSD/FIEL
                                    </button>

                                    <button type="button"
                                            class="rfcs-icon-btn"
                                            data-open-rfc-modal="series"
                                            data-rfc='@json($payload)'>
                                        Series
                                    </button>

                                    <form method="POST" action="{{ route('cliente.rfcs.toggle', $emisor->id) }}">
                                        @csrf
                                        <button type="submit" class="rfcs-icon-btn">
                                            {{ $status === 'activo' ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="rfcs-empty">
                                    <strong>No hay RFC emisores registrados</strong>
                                    <span>Agrega tu primer RFC para poder emitir CFDI desde Pactopia360.</span>
                                    <button type="button" class="rfcs-btn primary" data-open-rfc-modal="create">
                                        + Agregar RFC
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@include('cliente.rfcs.partials.modals')
@endsection

@push('scripts')
<script src="{{ asset('assets/client/js/pages/rfcs.js') }}?v={{ time() }}"></script>
@endpush