@extends('layouts.cliente')

@section('title', 'SAT Bóveda v2 · Pactopia360')
@section('pageClass', 'page-sat-vault-v2')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/sat-v2.css') }}?v={{ filemtime(public_path('assets/client/css/sat-v2.css')) }}">
@endpush

@section('content')
<div class="sv2">
    <div class="sv2__wrap">

        @if(session('success'))
            <div class="sv2Alert sv2Alert--ok">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="sv2Alert sv2Alert--error">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="sv2Alert sv2Alert--error">
                {{ $errors->first() }}
            </div>
        @endif

          <section class="sv2Hero">
            <div class="sv2Hero__grid">
                <div class="sv2Hero__left">
                    <div class="sv2Hero__content">

                        <div class="sv2Hero__copy">
                            <h1 class="sv2Hero__title">Portal de descargas</h1>

                            <p class="sv2Hero__subtitle">
                                Consulta, resguarda y descarga archivos de metadata, XML CFDI y reportes asociados a tu RFC de trabajo.
                            </p>
                        </div>

                        <div class="sv2Hero__chips">
                            <span class="sv2Chip">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M7 4h8l4 4v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                                    <path d="M15 4v4h4" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                                Metadata
                            </span>

                            <span class="sv2Chip">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M8 7 4 12l4 5M16 7l4 5-4 5M14 5l-4 14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                XML CFDI
                            </span>

                            <span class="sv2Chip">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M7 12h4l2-2 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="7" cy="12" r="2" stroke="currentColor" stroke-width="1.8"/>
                                    <circle cx="17" cy="12" r="2" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                                Conciliación
                            </span>

                            <span class="sv2Chip">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 16V8M12 16l-4-4M12 16l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M5 19h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                                Descargas
                            </span>
                        </div>
                    </div>
                </div>

                <aside class="sv2HeroCard sv2HeroCard--v2">
                    <div class="sv2HeroCard__header">
                        <div class="sv2HeroCard__titles">
                            <span class="sv2HeroCard__eyebrow">Acceso</span>
                            <h3 class="sv2HeroCard__title">RFC de trabajo</h3>
                        </div>

                        <button
                            type="button"
                            class="sv2GearBtn"
                            data-sv2-open="rfcManagerModal"
                            title="Gestionar RFC"
                        >
                            ⚙️
                        </button>
                    </div>

                    <div class="sv2HeroCard__meta">
                        <span class="sv2Count">{{ $rfcs->count() }} RFC</span>

                        @if($selectedRfc)
                            <span class="sv2ActiveRFC" title="{{ $selectedRfc }}">
                                {{ $selectedRfc }}
                            </span>
                        @endif
                    </div>

                    <form method="GET" action="{{ route('cliente.sat.v2.index') }}" class="sv2HeroCard__form">
                        <select name="rfc" class="sv2Select sv2Select--v2">
                            <option value="">Selecciona un RFC</option>
                            @foreach($rfcs as $rfc)
                                <option value="{{ $rfc->rfc }}" {{ $selectedRfc === $rfc->rfc ? 'selected' : '' }}>
                                    {{ $rfc->rfc }}{{ $rfc->razon_social ? ' — '.$rfc->razon_social : '' }}
                                </option>
                            @endforeach
                        </select>

                        <button type="submit" class="sv2Btn sv2Btn--v2">
                            Entrar
                        </button>
                    </form>
                </aside>
            </div>
        </section>

        <section class="sv2Section">
            <div class="sv2Section__head">
            </div>

            <div class="sv2KPIs">
                <article class="sv2Kpi sv2Kpi--meta">
                    <div class="sv2Kpi__top">
                        <div class="sv2Icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M7 4h8l4 4v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M15 4v4h4M8 12h8M8 16h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="sv2Kpi__label">Metadata</div>
                    </div>
                    <div class="sv2Kpi__value">{{ number_format($metadataCount) }}</div>
                    <div class="sv2Kpi__desc">Base SAT cargada.</div>
                </article>

                <article class="sv2Kpi sv2Kpi--xml">
                    <div class="sv2Kpi__top">
                        <div class="sv2Icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M8 7 4 12l4 5M16 7l4 5-4 5M14 5l-4 14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="sv2Kpi__label">XML CFDI</div>
                    </div>
                    <div class="sv2Kpi__value">{{ number_format($cfdiCount) }}</div>
                    <div class="sv2Kpi__desc">XML vinculados.</div>
                </article>

                <article class="sv2Kpi sv2Kpi--batch">
                    <div class="sv2Kpi__top">
                        <div class="sv2Icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <rect x="4" y="5" width="16" height="4" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
                                <rect x="4" y="10" width="16" height="4" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
                                <rect x="4" y="15" width="16" height="4" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
                            </svg>
                        </div>
                        <div class="sv2Kpi__label">Lotes metadata</div>
                    </div>
                    <div class="sv2Kpi__value">{{ number_format($metadataUploads->count()) }}</div>
                    <div class="sv2Kpi__desc">Lotes cargados.</div>
                </article>

                <article class="sv2Kpi sv2Kpi--zip">
                    <div class="sv2Kpi__top">
                        <div class="sv2Icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M8 4h8l4 4v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M12 9v6M10 13l2 2 2-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="sv2Kpi__label">Lotes XML</div>
                    </div>
                    <div class="sv2Kpi__value">{{ number_format($xmlUploads->count()) }}</div>
                    <div class="sv2Kpi__desc">Paquetes XML.</div>
                </article>
            </div>
        </section>

        @php
            $resolveProgress = function (bool $hasRfc, int $uploadsCount, int $itemsCount): int {
                if (!$hasRfc) {
                    return 0;
                }

                if ($itemsCount > 0) {
                    return 100;
                }

                if ($uploadsCount > 0) {
                    return 75;
                }

                return 35;
            };

            $hasSelectedRfc = $selectedRfc !== '';

            $step1Progress = $resolveProgress(
                $hasSelectedRfc,
                (int) $metadataUploads->count(),
                (int) $metadataCount
            );

            $step2Progress = $resolveProgress(
                $hasSelectedRfc,
                (int) $xmlUploads->count(),
                (int) $cfdiCount
            );

            $step3Progress = $hasSelectedRfc
                ? (((int) ($reportUploads->count() ?? 0)) > 0 ? 100 : 35)
                : 0;

            $monthsChart = collect($metadataSummary['meses'] ?? [])
                ->map(function ($row) {
                    $emitidosTotal = (float) ($row['emitidos_total'] ?? $row['emitidos'] ?? 0);
                    $recibidosTotal = (float) ($row['recibidos_total'] ?? $row['recibidos'] ?? 0);
                    $fallbackTotal = (float) ($row['total'] ?? 0);

                    if ($emitidosTotal <= 0 && $recibidosTotal <= 0 && $fallbackTotal > 0) {
                        $emitidosTotal = $fallbackTotal;
                    }

                    return [
                        'ym'              => (string) ($row['ym'] ?? ''),
                        'emitidos_total'  => $emitidosTotal,
                        'recibidos_total' => $recibidosTotal,
                        'max_total'       => max($emitidosTotal, $recibidosTotal),
                    ];
                })
                ->filter(fn ($row) => $row['ym'] !== '')
                ->values()
                ->all();

            $maxChartTotal = 1;

            foreach ($monthsChart as $chartRow) {
                $maxChartTotal = max(
                    $maxChartTotal,
                    (float) ($chartRow['emitidos_total'] ?? 0),
                    (float) ($chartRow['recibidos_total'] ?? 0)
                );
            }

            $xmlItems = collect();
            $xmlItemsFiltered = collect();
            $xmlMonthsChart = [];
            $xmlMaxChartTotal = 1;

            $xmlFilters = [
                'xml_q'         => trim((string) request()->query('xml_q', '')),
                'xml_direction' => strtolower(trim((string) request()->query('xml_direction', ''))),
                'xml_version'   => trim((string) request()->query('xml_version', '')),
                'xml_match'     => trim((string) request()->query('xml_match', '')),
                'xml_desde'     => trim((string) request()->query('xml_desde', '')),
                'xml_hasta'     => trim((string) request()->query('xml_hasta', '')),
            ];

            $xmlVersions = collect();

            if ($selectedRfc !== '') {
                $xmlItems = \App\Models\Cliente\SatUserCfdi::query()
                    ->where('cuenta_id', (string) (auth('web')->user()->cuenta_id ?? (auth('web')->user()->cuenta->id ?? '')))
                    ->where('usuario_id', (string) (auth('web')->id() ?? ''))
                    ->where('rfc_owner', $selectedRfc)
                    ->orderByDesc('fecha_emision')
                    ->orderByDesc('id')
                    ->limit(300)
                    ->get();

                $xmlVersions = $xmlItems
                    ->map(fn ($item) => (string) ($item->version_cfdi ?: 'Sin versión'))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                $xmlItemsFiltered = $xmlItems->filter(function ($item) use ($xmlFilters) {
                    $q = mb_strtolower($xmlFilters['xml_q']);
                    $version = (string) ($item->version_cfdi ?: 'Sin versión');
                    $matchId = (int) data_get($item->meta, 'matched_metadata_item_id', 0);
                    $itemDirection = strtolower((string) ($item->direction ?? ''));
                    $fecha = optional($item->fecha_emision)?->format('Y-m-d');

                    if ($q !== '') {
                        $hay = collect([
                            (string) $item->uuid,
                            (string) $item->rfc_emisor,
                            (string) $item->nombre_emisor,
                            (string) $item->rfc_receptor,
                            (string) $item->nombre_receptor,
                            (string) $item->total,
                            (string) $version,
                        ])->implode(' | ');

                        if (!str_contains(mb_strtolower($hay), $q)) {
                            return false;
                        }
                    }

                    if (in_array($xmlFilters['xml_direction'], ['emitidos', 'recibidos'], true) && $itemDirection !== $xmlFilters['xml_direction']) {
                        return false;
                    }

                    if ($xmlFilters['xml_version'] !== '' && $version !== $xmlFilters['xml_version']) {
                        return false;
                    }

                    if ($xmlFilters['xml_match'] === 'con_match' && $matchId <= 0) {
                        return false;
                    }

                    if ($xmlFilters['xml_match'] === 'sin_match' && $matchId > 0) {
                        return false;
                    }

                    if ($xmlFilters['xml_desde'] !== '' && $fecha !== null && $fecha < $xmlFilters['xml_desde']) {
                        return false;
                    }

                    if ($xmlFilters['xml_hasta'] !== '' && $fecha !== null && $fecha > $xmlFilters['xml_hasta']) {
                        return false;
                    }

                    return true;
                })->values();

                $reportItems = collect();
                $reportItemsFiltered = collect();
                $reportMonthsChart = [];
                $reportMaxChartTotal = 1;

                $reportFilters = [
                    'report_q'         => trim((string) request()->query('report_q', '')),
                    'report_direction' => strtolower(trim((string) request()->query('report_direction', ''))),
                    'report_desde'     => trim((string) request()->query('report_desde', '')),
                    'report_hasta'     => trim((string) request()->query('report_hasta', '')),
                ];

                if ($selectedRfc !== '') {
                    $reportItems = \App\Models\Cliente\SatUserReportItem::query()
                        ->where('cuenta_id', (string) (auth('web')->user()->cuenta_id ?? (auth('web')->user()->cuenta->id ?? '')))
                        ->where('usuario_id', (string) (auth('web')->id() ?? ''))
                        ->where('rfc_owner', $selectedRfc)
                        ->orderByDesc('fecha_emision')
                        ->orderByDesc('id')
                        ->limit(1000)
                        ->get();

                    $reportItemsFiltered = $reportItems->filter(function ($item) use ($reportFilters) {
                        $q = mb_strtolower($reportFilters['report_q']);
                        $itemDirection = strtolower((string) ($item->direction ?? ''));
                        $fecha = optional($item->fecha_emision)?->format('Y-m-d');

                        if ($q !== '') {
                            $hay = collect([
                                (string) $item->uuid,
                                (string) $item->emisor_rfc,
                                (string) $item->emisor_nombre,
                                (string) $item->receptor_rfc,
                                (string) $item->receptor_nombre,
                                (string) $item->total,
                                (string) $item->tipo_comprobante,
                            ])->implode(' | ');

                            if (!str_contains(mb_strtolower($hay), $q)) {
                                return false;
                            }
                        }

                        if (in_array($reportFilters['report_direction'], ['emitidos', 'recibidos'], true) && $itemDirection !== $reportFilters['report_direction']) {
                            return false;
                        }

                        if ($reportFilters['report_desde'] !== '' && $fecha !== null && $fecha < $reportFilters['report_desde']) {
                            return false;
                        }

                        if ($reportFilters['report_hasta'] !== '' && $fecha !== null && $fecha > $reportFilters['report_hasta']) {
                            return false;
                        }

                        return true;
                    })->values();

                    $reportMonthsChart = $reportItemsFiltered
                        ->filter(fn ($item) => !empty($item->fecha_emision))
                        ->groupBy(fn ($item) => optional($item->fecha_emision)?->format('Y-m'))
                        ->map(function ($rows, $ym) {
                            $emitidosRows = $rows->filter(fn ($row) => strtolower((string) ($row->direction ?? '')) === 'emitidos');
                            $recibidosRows = $rows->filter(fn ($row) => strtolower((string) ($row->direction ?? '')) === 'recibidos');

                            $emitidosMonto = (float) $emitidosRows->sum(fn ($row) => (float) $row->total);
                            $recibidosMonto = (float) $recibidosRows->sum(fn ($row) => (float) $row->total);

                            return [
                                'ym'              => (string) $ym,
                                'emitidos_total'  => $emitidosMonto,
                                'recibidos_total' => $recibidosMonto,
                                'max_total'       => max($emitidosMonto, $recibidosMonto),
                            ];
                        })
                        ->sortBy('ym')
                        ->values()
                        ->all();

                    foreach ($reportMonthsChart as $chartRow) {
                        $reportMaxChartTotal = max(
                            $reportMaxChartTotal,
                            (float) ($chartRow['emitidos_total'] ?? 0),
                            (float) ($chartRow['recibidos_total'] ?? 0)
                        );
                    }
                }

                $xmlMonthsChart = $xmlItemsFiltered
                    ->filter(fn ($item) => !empty($item->fecha_emision))
                    ->groupBy(fn ($item) => optional($item->fecha_emision)?->format('Y-m'))
                    ->map(function ($rows, $ym) {
                        $emitidosRows = $rows->filter(fn ($row) => strtolower((string) ($row->direction ?? '')) === 'emitidos');
                        $recibidosRows = $rows->filter(fn ($row) => strtolower((string) ($row->direction ?? '')) === 'recibidos');

                        $emitidosMonto = (float) $emitidosRows->sum(fn ($row) => (float) $row->total);
                        $recibidosMonto = (float) $recibidosRows->sum(fn ($row) => (float) $row->total);

                        return [
                            'ym'              => (string) $ym,
                            'emitidos_total'  => $emitidosMonto,
                            'recibidos_total' => $recibidosMonto,
                            'max_total'       => max($emitidosMonto, $recibidosMonto),
                        ];
                    })
                    ->sortBy('ym')
                    ->values()
                    ->all();

                foreach ($xmlMonthsChart as $chartRow) {
                    $xmlMaxChartTotal = max(
                        $xmlMaxChartTotal,
                        (float) ($chartRow['emitidos_total'] ?? 0),
                        (float) ($chartRow['recibidos_total'] ?? 0)
                    );
                }
            }

        @endphp

        <section class="sv2Section" id="dataLoadSection">
            <div class="sv2MetaBar">
                <div class="sv2MetaBar__left">
                    <span class="sv2MetaBar__title">Carga de datos</span>
                    <span class="sv2MetaBar__sub">
                        Metadata, XML y reportes asociados al RFC {{ $selectedRfc !== '' ? $selectedRfc : 'sin seleccionar' }}
                    </span>
                </div>

                <button
                    type="button"
                    class="sv2MetaBar__toggle"
                    id="toggleDataLoad"
                    aria-label="Expandir o contraer carga de datos"
                    aria-expanded="true"
                >
                    <span class="sv2MetaBar__icon">−</span>
                </button>
            </div>

            <div class="sv2MetaContent" id="dataLoadBlock">
                <div class="sv2Main sv2Main--single">
                    <div class="sv2Stack">
                        <div class="sv2UploadsRow">

                            <div class="sv2Card sv2UploadCard sv2UploadCard--meta">
                                <div class="sv2UploadCard__topbar">
                                    <div class="sv2UploadCard__left">
                                        <div class="sv2UploadCard__icon" aria-hidden="true">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                <path d="M7 4h8l4 4v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                                                <path d="M15 4v4h4M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            </svg>
                                        </div>

                                        <div class="sv2UploadCard__main">
                                            <div class="sv2UploadCard__pills">
                                                <span class="sv2UploadBadge">Metadata</span>
                                                <span class="sv2UploadChip">TXT · CSV · ZIP</span>
                                            </div>

                                            <h3 class="sv2UploadCard__title">Carga de metadata</h3>
                                        </div>
                                    </div>

                                    <div class="sv2UploadCard__percent">{{ $step1Progress }}</div>
                                </div>

                                <div class="sv2UploadCard__progress" aria-label="Avance de metadata">
                                    <span class="sv2UploadCard__progressFill" style="width: {{ $step1Progress }}%"></span>
                                </div>

                                <div class="sv2UploadCard__stats">
                                    <div class="sv2UploadStat2">
                                        <span class="sv2UploadStat2__label">RFC</span>
                                        <strong class="sv2UploadStat2__value">{{ $selectedRfc !== '' ? $selectedRfc : 'Sin seleccionar' }}</strong>
                                    </div>

                                    <div class="sv2UploadStat2">
                                        <span class="sv2UploadStat2__label">Registros</span>
                                        <strong class="sv2UploadStat2__value">{{ number_format($metadataCount) }}</strong>
                                    </div>

                                    <div class="sv2UploadStat2">
                                        <span class="sv2UploadStat2__label">Lotes</span>
                                        <strong class="sv2UploadStat2__value">{{ number_format($metadataUploads->count()) }}</strong>
                                    </div>
                                </div>

                                <div class="sv2UploadCard__footer">
                                    <button
                                        type="button"
                                        class="sv2Btn sv2Btn--primary sv2Btn--tiny"
                                        data-sv2-open="metadataModal"
                                    >
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        Cargar
                                    </button>

                                    <span class="sv2UploadCard__note">
                                        {{ $selectedRfc !== '' ? 'RFC listo' : 'Selecciona o registra RFC' }}
                                    </span>
                                </div>
                            </div>

                            <div class="sv2Card sv2UploadCard sv2UploadCard--xml">
                                <div class="sv2UploadCard__topbar">
                                    <div class="sv2UploadCard__left">
                                        <div class="sv2UploadCard__icon" aria-hidden="true">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                <path d="M8 7 4 12l4 5M16 7l4 5-4 5M14 5l-4 14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>

                                        <div class="sv2UploadCard__main">
                                            <div class="sv2UploadCard__pills">
                                                <span class="sv2UploadBadge sv2UploadBadge--xml">XML</span>
                                                <span class="sv2UploadChip">XML · ZIP</span>
                                            </div>

                                            <h3 class="sv2UploadCard__title">Carga de XML</h3>
                                        </div>
                                    </div>

                                    <div class="sv2UploadCard__percent">{{ $step2Progress }}</div>
                                </div>

                                <div class="sv2UploadCard__progress" aria-label="Avance de XML">
                                    <span class="sv2UploadCard__progressFill sv2UploadCard__progressFill--xml" style="width: {{ $step2Progress }}%"></span>
                                </div>

                                <div class="sv2UploadCard__stats">
                                    <div class="sv2UploadStat2">
                                        <span class="sv2UploadStat2__label">RFC</span>
                                        <strong class="sv2UploadStat2__value">{{ $selectedRfc !== '' ? $selectedRfc : 'Sin seleccionar' }}</strong>
                                    </div>

                                    <div class="sv2UploadStat2">
                                        <span class="sv2UploadStat2__label">CFDI</span>
                                        <strong class="sv2UploadStat2__value">{{ number_format($cfdiCount) }}</strong>
                                    </div>

                                    <div class="sv2UploadStat2">
                                        <span class="sv2UploadStat2__label">Lotes</span>
                                        <strong class="sv2UploadStat2__value">{{ number_format($xmlUploads->count()) }}</strong>
                                    </div>
                                </div>

                                <div class="sv2UploadCard__footer">
                                    <button
                                        type="button"
                                        class="sv2Btn sv2Btn--primary sv2Btn--tiny"
                                        data-sv2-open="xmlModal"
                                    >
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        Cargar
                                    </button>

                                    <span class="sv2UploadCard__note">
                                        {{ $selectedRfc !== '' ? 'Asocia XML a metadata' : 'Selecciona o registra RFC' }}
                                    </span>
                                </div>
                            </div>

                            <div class="sv2Card sv2UploadCard sv2UploadCard--report">
                                <div class="sv2UploadCard__topbar">
                                    <div class="sv2UploadCard__left">
                                        <div class="sv2UploadCard__icon" aria-hidden="true">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                <path d="M7 4h8l4 4v12H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                                                <path d="M15 4v4h4M8 12h8M8 16h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            </svg>
                                        </div>

                                        <div class="sv2UploadCard__main">
                                            <div class="sv2UploadCard__pills">
                                                <span class="sv2UploadBadge sv2UploadBadge--report">Reporte</span>
                                                <span class="sv2UploadChip">CSV · XLSX · XLS · TXT</span>
                                            </div>

                                            <h3 class="sv2UploadCard__title">Carga de reporte</h3>
                                        </div>
                                    </div>

                                    <div class="sv2UploadCard__percent">{{ $step3Progress }}</div>
                                </div>

                                <div class="sv2UploadCard__progress" aria-label="Avance de reporte">
                                    <span class="sv2UploadCard__progressFill sv2UploadCard__progressFill--report" style="width: {{ $step3Progress }}%"></span>
                                </div>

                                <div class="sv2UploadCard__stats">
                                    <div class="sv2UploadStat2">
                                        <span class="sv2UploadStat2__label">RFC</span>
                                        <strong class="sv2UploadStat2__value">{{ $selectedRfc !== '' ? $selectedRfc : 'Sin seleccionar' }}</strong>
                                    </div>

                                    <div class="sv2UploadStat2">
                                        <span class="sv2UploadStat2__label">Reportes</span>
                                        <strong class="sv2UploadStat2__value">{{ number_format($reportCount ?? 0) }}</strong>
                                    </div>

                                    <div class="sv2UploadStat2">
                                        <span class="sv2UploadStat2__label">Asociación</span>
                                        <strong class="sv2UploadStat2__value">{{ $selectedRfc !== '' ? 'RFC / Metadata / XML' : 'Pendiente' }}</strong>
                                    </div>
                                </div>

                                <div class="sv2UploadCard__footer">
                                    <button
                                        type="button"
                                        class="sv2Btn sv2Btn--primary sv2Btn--tiny"
                                        data-sv2-open="reportModal"
                                    >
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        Cargar
                                    </button>

                                    <span class="sv2UploadCard__note">
                                        {{ $selectedRfc !== '' ? 'Asocia reporte a metadata y XML' : 'Selecciona o registra RFC' }}
                                    </span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if($selectedRfc !== '')
            <section class="sv2Section sv2Section--metadata sv2Section--collapsed" id="metadataBlock">

                <div class="sv2MetaBar">
                    <div class="sv2MetaBar__left">
                        <span class="sv2MetaBar__title">Metadata</span>
                        <span class="sv2MetaBar__sub">
                            {{ number_format($metadataItems->total() ?? 0) }} registros · RFC {{ $selectedRfc }}
                        </span>
                    </div>

                    <button type="button" class="sv2MetaBar__toggle" id="toggleMetadata" aria-label="Expandir o contraer metadata">
                        <span class="sv2MetaBar__icon">−</span>
                    </button>
                </div>

                <div class="sv2MetaContent">
                    <div class="sv2MetaLayout sv2MetaLayout--top">
                        <div class="sv2Card sv2MetaTrendCard">
                            <div class="sv2MetaTrendCard__title">Comparación metadata</div>

                            @if(count($monthsChart))
                                <div class="sv2XmlChartLegend">
                                    <span class="sv2XmlChartLegend__item">
                                        <span class="sv2XmlChartLegend__dot sv2XmlChartLegend__dot--emitidos"></span>
                                        Emitidos
                                    </span>
                                    <span class="sv2XmlChartLegend__item">
                                        <span class="sv2XmlChartLegend__dot sv2XmlChartLegend__dot--recibidos"></span>
                                        Recibidos
                                    </span>
                                </div>

                                <div class="sv2XmlCompareChart">
                                    @foreach($monthsChart as $chartRow)
                                        @php
                                            $emitidosHeight = $maxChartTotal > 0
                                                ? max(12, (int) round(((float) $chartRow['emitidos_total'] / $maxChartTotal) * 140))
                                                : 12;

                                            $recibidosHeight = $maxChartTotal > 0
                                                ? max(12, (int) round(((float) $chartRow['recibidos_total'] / $maxChartTotal) * 140))
                                                : 12;
                                        @endphp

                                        <div class="sv2XmlCompareChart__col">
                                            <div class="sv2XmlCompareChart__top">
                                                <div class="sv2XmlCompareChart__amount sv2XmlCompareChart__amount--emitidos">
                                                    E: ${{ number_format((float) $chartRow['emitidos_total'], 2) }}
                                                </div>
                                                <div class="sv2XmlCompareChart__amount sv2XmlCompareChart__amount--recibidos">
                                                    R: ${{ number_format((float) $chartRow['recibidos_total'], 2) }}
                                                </div>
                                            </div>

                                            <div class="sv2XmlCompareChart__bars">
                                                <div class="sv2XmlCompareChart__track">
                                                    <div
                                                        class="sv2XmlCompareChart__bar sv2XmlCompareChart__bar--emitidos"
                                                        style="height:{{ $emitidosHeight }}px;"
                                                    ></div>
                                                </div>

                                                <div class="sv2XmlCompareChart__track">
                                                    <div
                                                        class="sv2XmlCompareChart__bar sv2XmlCompareChart__bar--recibidos"
                                                        style="height:{{ $recibidosHeight }}px;"
                                                    ></div>
                                                </div>
                                            </div>

                                            <div class="sv2XmlCompareChart__label">
                                                {{ $chartRow['ym'] }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div style="font-size:12px; color:#64748b;">
                                    Sin datos para gráfica de metadata.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="sv2Card sv2MetaFiltersCard">
                        <form method="GET" action="{{ route('cliente.sat.v2.index') }}">
                            <input type="hidden" name="rfc" value="{{ $selectedRfc }}">

                            <div class="sv2MetaFiltersGrid">
                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Buscar</span>
                                    <input
                                        type="text"
                                        name="q"
                                        class="sv2Input"
                                        value="{{ $metadataFilters['q'] ?? '' }}"
                                        placeholder="UUID, RFC, nombre..."
                                    >
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Dirección</span>
                                    <select name="direction" class="sv2Select">
                                        <option value="">Todas</option>
                                        <option value="emitidos" {{ ($metadataFilters['direction'] ?? '') === 'emitidos' ? 'selected' : '' }}>Emitidos</option>
                                        <option value="recibidos" {{ ($metadataFilters['direction'] ?? '') === 'recibidos' ? 'selected' : '' }}>Recibidos</option>
                                    </select>
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Estatus</span>
                                    <select name="estatus" class="sv2Select">
                                        <option value="">Todos</option>
                                        @foreach(($metadataStatuses ?? collect()) as $estatusOption)
                                            <option value="{{ $estatusOption }}" {{ ($metadataFilters['estatus'] ?? '') === $estatusOption ? 'selected' : '' }}>
                                                {{ $estatusOption }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Desde</span>
                                    <input type="date" name="desde" class="sv2Input" value="{{ $metadataFilters['desde'] ?? '' }}">
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Hasta</span>
                                    <input type="date" name="hasta" class="sv2Input" value="{{ $metadataFilters['hasta'] ?? '' }}">
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Filas</span>
                                    <select name="page_size" class="sv2Select">
                                        @foreach([10,25,50,100,200] as $size)
                                            <option value="{{ $size }}" {{ (int) ($metadataFilters['page_size'] ?? 25) === $size ? 'selected' : '' }}>
                                                {{ $size }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="sv2MetaFiltersActions">
                                    <button type="submit" class="sv2Btn sv2Btn--primary sv2Btn--tiny">Filtrar</button>
                                    <a href="{{ route('cliente.sat.v2.index', ['rfc' => $selectedRfc]) }}" class="sv2Btn sv2Btn--secondary sv2Btn--tiny">Limpiar</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="sv2MetaLayout">
                        <div class="sv2Card sv2MetaTableCard">
                            @if(($metadataItems ?? null) && $metadataItems->count())
                                <div class="sv2MetaTableWrap">
                                    <table class="sv2MetaTable">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>UUID</th>
                                                <th>Emisor</th>
                                                <th>Receptor</th>
                                                <th>Monto</th>
                                                <th>Estatus</th>
                                                <th>Dir.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($metadataItems as $item)
                                                <tr>
                                                    <td>
                                                        {{ optional($item->fecha_emision)->format('Y-m-d') ?: '—' }}
                                                    </td>
                                                    <td>
                                                        {{ $item->uuid ?: '—' }}
                                                    </td>
                                                    <td>
                                                        <div>{{ $item->rfc_emisor ?: '—' }}</div>
                                                        @if($item->nombre_emisor)
                                                            <div class="sv2MetaSubtext">{{ $item->nombre_emisor }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div>{{ $item->rfc_receptor ?: '—' }}</div>
                                                        @if($item->nombre_receptor)
                                                            <div class="sv2MetaSubtext">{{ $item->nombre_receptor }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        ${{ number_format((float) $item->monto, 2) }}
                                                    </td>
                                                    <td>
                                                        {{ $item->estatus ?: '—' }}
                                                    </td>
                                                    <td>
                                                        {{ $item->direction === 'emitidos' ? 'E' : ($item->direction === 'recibidos' ? 'R' : '—') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="sv2MetaPagination">
                                    {{ $metadataItems->links() }}
                                </div>
                            @else
                                <div class="sv2Alert" style="margin:12px;">Sin registros para mostrar.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <section class="sv2Section sv2Section--xml sv2Section--collapsed" id="xmlBlock">

                <div class="sv2MetaBar">
                    <div class="sv2MetaBar__left">
                        <span class="sv2MetaBar__title">XML CFDI</span>
                        <span class="sv2MetaBar__sub">
                            {{ number_format($xmlItemsFiltered->count()) }} registros · RFC {{ $selectedRfc }}
                        </span>
                    </div>

                    <button type="button" class="sv2MetaBar__toggle" id="toggleXml" aria-label="Expandir o contraer XML">
                        <span class="sv2MetaBar__icon">−</span>
                    </button>
                </div>

                <div class="sv2MetaContent">
                    <div class="sv2MetaLayout sv2MetaLayout--top">
                        <div class="sv2Card sv2MetaTrendCard">
                            <div class="sv2MetaTrendCard__title">Tendencia XML</div>

                            @if(count($xmlMonthsChart))
                                <div class="sv2XmlChartLegend">
                                    <span class="sv2XmlChartLegend__item">
                                        <span class="sv2XmlChartLegend__dot sv2XmlChartLegend__dot--emitidos"></span>
                                        Emitidos
                                    </span>
                                    <span class="sv2XmlChartLegend__item">
                                        <span class="sv2XmlChartLegend__dot sv2XmlChartLegend__dot--recibidos"></span>
                                        Recibidos
                                    </span>
                                </div>

                                <div class="sv2XmlCompareChart">
                                    @foreach($xmlMonthsChart as $chartRow)
                                        @php
                                            $emitidosHeight = $xmlMaxChartTotal > 0
                                                ? max(12, (int) round(((float) $chartRow['emitidos_total'] / $xmlMaxChartTotal) * 140))
                                                : 12;

                                            $recibidosHeight = $xmlMaxChartTotal > 0
                                                ? max(12, (int) round(((float) $chartRow['recibidos_total'] / $xmlMaxChartTotal) * 140))
                                                : 12;
                                        @endphp

                                        <div class="sv2XmlCompareChart__col">
                                            <div class="sv2XmlCompareChart__top">
                                                <div class="sv2XmlCompareChart__amount sv2XmlCompareChart__amount--emitidos">
                                                    E: ${{ number_format((float) $chartRow['emitidos_total'], 2) }}
                                                </div>
                                                <div class="sv2XmlCompareChart__amount sv2XmlCompareChart__amount--recibidos">
                                                    R: ${{ number_format((float) $chartRow['recibidos_total'], 2) }}
                                                </div>
                                            </div>

                                            <div class="sv2XmlCompareChart__bars">
                                                <div class="sv2XmlCompareChart__track">
                                                    <div class="sv2XmlCompareChart__bar sv2XmlCompareChart__bar--emitidos" style="height:{{ $emitidosHeight }}px;"></div>
                                                </div>

                                                <div class="sv2XmlCompareChart__track">
                                                    <div class="sv2XmlCompareChart__bar sv2XmlCompareChart__bar--recibidos" style="height:{{ $recibidosHeight }}px;"></div>
                                                </div>
                                            </div>

                                            <div class="sv2XmlCompareChart__label">
                                                {{ $chartRow['ym'] }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div style="font-size:12px; color:#64748b;">
                                    Sin datos para gráfica XML.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="sv2Card sv2MetaFiltersCard">
                        <form method="GET" action="{{ route('cliente.sat.v2.index') }}">
                            <input type="hidden" name="rfc" value="{{ $selectedRfc }}">

                            <div class="sv2MetaFiltersGrid">
                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Buscar XML</span>
                                    <input
                                        type="text"
                                        name="xml_q"
                                        class="sv2Input"
                                        value="{{ $xmlFilters['xml_q'] ?? '' }}"
                                        placeholder="UUID, RFC, nombre, total..."
                                    >
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Dirección</span>
                                    <select name="xml_direction" class="sv2Select">
                                        <option value="">Todas</option>
                                        <option value="emitidos" {{ ($xmlFilters['xml_direction'] ?? '') === 'emitidos' ? 'selected' : '' }}>Emitidos</option>
                                        <option value="recibidos" {{ ($xmlFilters['xml_direction'] ?? '') === 'recibidos' ? 'selected' : '' }}>Recibidos</option>
                                    </select>
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Versión</span>
                                    <select name="xml_version" class="sv2Select">
                                        <option value="">Todas</option>
                                        @foreach($xmlVersions as $xmlVersion)
                                            <option value="{{ $xmlVersion }}" {{ ($xmlFilters['xml_version'] ?? '') === $xmlVersion ? 'selected' : '' }}>
                                                {{ $xmlVersion }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Match</span>
                                    <select name="xml_match" class="sv2Select">
                                        <option value="">Todos</option>
                                        <option value="con_match" {{ ($xmlFilters['xml_match'] ?? '') === 'con_match' ? 'selected' : '' }}>Con match</option>
                                        <option value="sin_match" {{ ($xmlFilters['xml_match'] ?? '') === 'sin_match' ? 'selected' : '' }}>Sin match</option>
                                    </select>
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Desde</span>
                                    <input type="date" name="xml_desde" class="sv2Input" value="{{ $xmlFilters['xml_desde'] ?? '' }}">
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Hasta</span>
                                    <input type="date" name="xml_hasta" class="sv2Input" value="{{ $xmlFilters['xml_hasta'] ?? '' }}">
                                </div>

                                <div class="sv2MetaFiltersActions">
                                    <button type="submit" class="sv2Btn sv2Btn--primary sv2Btn--tiny">Filtrar</button>
                                    <a href="{{ route('cliente.sat.v2.index', ['rfc' => $selectedRfc]) }}#xmlBlock" class="sv2Btn sv2Btn--secondary sv2Btn--tiny">Limpiar</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="sv2MetaLayout">
                        <div class="sv2Card sv2MetaTableCard">
                            @if($xmlItemsFiltered->count())
                                <div class="sv2MetaTableWrap">
                                    <table class="sv2MetaTable sv2XmlTable">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>UUID</th>
                                                <th>Versión</th>
                                                <th>Emisor</th>
                                                <th>Receptor</th>
                                                <th>Total</th>
                                                <th>Dir.</th>
                                                <th>Match</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($xmlItemsFiltered as $xml)
                                                @php
                                                    $matchedMetadataId = (int) data_get($xml->meta, 'matched_metadata_item_id', 0);
                                                    $zipEntry = (string) data_get($xml->meta, 'zip_entry', '');
                                                    $hasMatch = $matchedMetadataId > 0;
                                                @endphp
                                                <tr>
                                                    <td>
                                                        {{ optional($xml->fecha_emision)->format('Y-m-d') ?: '—' }}
                                                    </td>
                                                    <td>
                                                        <div>{{ $xml->uuid ?: '—' }}</div>
                                                        @if($zipEntry !== '')
                                                            <div class="sv2MetaSubtext">{{ $zipEntry }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        {{ $xml->version_cfdi ?: '—' }}
                                                    </td>
                                                    <td>
                                                        <div>{{ $xml->rfc_emisor ?: '—' }}</div>
                                                        @if($xml->nombre_emisor)
                                                            <div class="sv2MetaSubtext">{{ $xml->nombre_emisor }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div>{{ $xml->rfc_receptor ?: '—' }}</div>
                                                        @if($xml->nombre_receptor)
                                                            <div class="sv2MetaSubtext">{{ $xml->nombre_receptor }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        ${{ number_format((float) $xml->total, 2) }}
                                                    </td>
                                                    <td>
                                                        {{ $xml->direction === 'emitidos' ? 'E' : ($xml->direction === 'recibidos' ? 'R' : '—') }}
                                                    </td>
                                                    <td>
                                                        @if($hasMatch)
                                                            <span class="sv2MatchBadge sv2MatchBadge--ok">Match</span>
                                                        @else
                                                            <span class="sv2MatchBadge sv2MatchBadge--warn">Sin match</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="sv2RowActions">
                                                            <button type="button" class="sv2Btn sv2Btn--secondary sv2Btn--tiny" disabled>XML</button>
                                                            <button type="button" class="sv2Btn sv2Btn--secondary sv2Btn--tiny" disabled>PDF</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="sv2Alert" style="margin:12px;">Sin XML procesados para mostrar.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <section class="sv2Section sv2Section--report sv2Section--collapsed" id="reportBlock">

                <div class="sv2MetaBar">
                    <div class="sv2MetaBar__left">
                        <span class="sv2MetaBar__title">Reportes</span>
                        <span class="sv2MetaBar__sub">
                            {{ number_format(($reportItemsFiltered ?? collect())->count()) }} registros · RFC {{ $selectedRfc }}
                        </span>
                    </div>

                    <button type="button" class="sv2MetaBar__toggle" id="toggleReport">
                        <span class="sv2MetaBar__icon">+</span>
                    </button>
                </div>

                <div class="sv2MetaContent">

                    {{-- ================= GRAFICA ================= --}}
                    <div class="sv2MetaLayout sv2MetaLayout--top">
                        <div class="sv2Card sv2MetaTrendCard">
                            <div class="sv2MetaTrendCard__title">Tendencia reportes</div>

                            @if(count($reportMonthsChart ?? []))
                                <div class="sv2XmlChartLegend">
                                    <span class="sv2XmlChartLegend__item">
                                        <span class="sv2XmlChartLegend__dot sv2XmlChartLegend__dot--emitidos"></span>
                                        Emitidos
                                    </span>
                                    <span class="sv2XmlChartLegend__item">
                                        <span class="sv2XmlChartLegend__dot sv2XmlChartLegend__dot--recibidos"></span>
                                        Recibidos
                                    </span>
                                </div>

                                <div class="sv2XmlCompareChart">
                                    @foreach($reportMonthsChart as $row)
                                        @php
                                            $emitidosHeight = $reportMaxChartTotal > 0
                                                ? max(12, (int) (($row['emitidos_total'] / $reportMaxChartTotal) * 140))
                                                : 12;

                                            $recibidosHeight = $reportMaxChartTotal > 0
                                                ? max(12, (int) (($row['recibidos_total'] / $reportMaxChartTotal) * 140))
                                                : 12;
                                        @endphp

                                        <div class="sv2XmlCompareChart__col">

                                            <div class="sv2XmlCompareChart__top">
                                                <div class="sv2XmlCompareChart__amount sv2XmlCompareChart__amount--emitidos">
                                                    E: ${{ number_format($row['emitidos_total'],2) }}
                                                </div>
                                                <div class="sv2XmlCompareChart__amount sv2XmlCompareChart__amount--recibidos">
                                                    R: ${{ number_format($row['recibidos_total'],2) }}
                                                </div>
                                            </div>

                                            <div class="sv2XmlCompareChart__bars">
                                                <div class="sv2XmlCompareChart__track">
                                                    <div class="sv2XmlCompareChart__bar sv2XmlCompareChart__bar--emitidos"
                                                        style="height:{{ $emitidosHeight }}px;"></div>
                                                </div>

                                                <div class="sv2XmlCompareChart__track">
                                                    <div class="sv2XmlCompareChart__bar sv2XmlCompareChart__bar--recibidos"
                                                        style="height:{{ $recibidosHeight }}px;"></div>
                                                </div>
                                            </div>

                                            <div class="sv2XmlCompareChart__label">
                                                {{ $row['ym'] }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div style="font-size:12px;color:#64748b;">
                                    Sin datos para gráfica de reportes.
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ================= FILTROS ================= --}}
                    <div class="sv2Card sv2MetaFiltersCard">
                        <form method="GET" action="{{ route('cliente.sat.v2.index') }}">
                            <input type="hidden" name="rfc" value="{{ $selectedRfc }}">

                            <div class="sv2MetaFiltersGrid">
                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Buscar</span>
                                    <input
                                        type="text"
                                        name="report_q"
                                        class="sv2Input"
                                        value="{{ $reportFilters['report_q'] ?? '' }}"
                                        placeholder="UUID, RFC, total..."
                                    >
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Dirección</span>
                                    <select name="report_direction" class="sv2Select">
                                        <option value="">Todas</option>
                                        <option value="emitidos" {{ ($reportFilters['report_direction'] ?? '') === 'emitidos' ? 'selected' : '' }}>Emitidos</option>
                                        <option value="recibidos" {{ ($reportFilters['report_direction'] ?? '') === 'recibidos' ? 'selected' : '' }}>Recibidos</option>
                                    </select>
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Desde</span>
                                    <input type="date" name="report_desde" class="sv2Input" value="{{ $reportFilters['report_desde'] ?? '' }}">
                                </div>

                                <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                    <span class="sv2Float">Hasta</span>
                                    <input type="date" name="report_hasta" class="sv2Input" value="{{ $reportFilters['report_hasta'] ?? '' }}">
                                </div>

                                <div class="sv2MetaFiltersActions">
                                    <button type="submit" class="sv2Btn sv2Btn--primary sv2Btn--tiny">Filtrar</button>
                                    <a href="{{ route('cliente.sat.v2.index', ['rfc' => $selectedRfc]) }}#reportBlock" class="sv2Btn sv2Btn--secondary sv2Btn--tiny">Limpiar</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- ================= TABLA ================= --}}
                    <div class="sv2MetaLayout">
                        <div class="sv2Card sv2MetaTableCard">

                            @if(isset($reportItemsFiltered) && $reportItemsFiltered->count())
                                <div class="sv2MetaTableWrap">
                                    <table class="sv2MetaTable">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>UUID</th>
                                                <th>Emisor</th>
                                                <th>Receptor</th>
                                                <th>Tipo</th>
                                                <th>Total</th>
                                                <th>Dir.</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach($reportItemsFiltered as $r)
                                                <tr>
                                                    <td>{{ optional($r->fecha_emision)->format('Y-m-d') ?: '—' }}</td>
                                                    <td>{{ $r->uuid ?: '—' }}</td>
                                                    <td>
                                                        <div>{{ $r->emisor_rfc ?: '—' }}</div>
                                                        @if($r->emisor_nombre)
                                                            <div class="sv2MetaSubtext">{{ $r->emisor_nombre }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div>{{ $r->receptor_rfc ?: '—' }}</div>
                                                        @if($r->receptor_nombre)
                                                            <div class="sv2MetaSubtext">{{ $r->receptor_nombre }}</div>
                                                        @endif
                                                    </td>
                                                    <td>{{ $r->tipo_comprobante ?: '—' }}</td>
                                                    <td>${{ number_format((float) $r->total, 2) }}</td>
                                                    <td>{{ $r->direction === 'emitidos' ? 'E' : ($r->direction === 'recibidos' ? 'R' : '—') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="sv2Alert" style="margin:12px;">
                                    Sin reportes procesados.
                                </div>
                            @endif

                        </div>
                    </div>

                </div>
            </section>
            @endif

                    @if($selectedRfc !== '')
            <section class="sv2Section sv2Section--downloads sv2Section--collapsed" id="downloadsBlock">
                <div class="sv2MetaBar">
                    <div class="sv2MetaBar__left">
                        <span class="sv2MetaBar__title">Descargas</span>
                        <span class="sv2MetaBar__sub">
                            {{ number_format(($downloadItems ?? collect())->count()) }} archivo(s) subidos · RFC {{ $selectedRfc }}
                        </span>
                    </div>

                    <button type="button" class="sv2MetaBar__toggle" id="toggleDownloads" aria-label="Expandir o contraer descargas">
                        <span class="sv2MetaBar__icon">+</span>
                    </button>
                </div>

                <div class="sv2MetaContent">
                    <div class="sv2MetaLayout">
                        <div class="sv2Card sv2MetaTableCard">
                            @if(($downloadItems ?? collect())->count())
                                <div class="sv2MetaTableWrap">
                                    <table class="sv2MetaTable">
                                        <thead>
                                            <tr>
                                                <th>Tipo</th>
                                                <th>Archivo</th>
                                                <th>Dirección</th>
                                                <th>Tamaño</th>
                                                <th>Detalle</th>
                                                <th>Fecha</th>
                                                <th style="min-width:180px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($downloadItems as $item)
                                                @php
                                                    $kindLabel = match($item['kind']) {
                                                        'metadata' => 'Metadata',
                                                        'xml'      => 'XML',
                                                        'report'   => 'Reporte',
                                                        default    => ucfirst((string) $item['kind']),
                                                    };

                                                    $detailLabel = '-';
                                                    if (($item['kind'] ?? '') === 'metadata') {
                                                        $detailLabel = number_format((int) ($item['rows_count'] ?? 0)) . ' registros';
                                                    } elseif (($item['kind'] ?? '') === 'xml') {
                                                        $detailLabel = number_format((int) ($item['files_count'] ?? 0)) . ' archivo(s)';
                                                    } elseif (($item['kind'] ?? '') === 'report') {
                                                        $detailLabel = number_format((int) ($item['rows_count'] ?? 0)) . ' filas';
                                                    }

                                                    $directionLabel = trim((string) ($item['direction'] ?? '')) !== ''
                                                        ? ucfirst((string) $item['direction'])
                                                        : '-';

                                                    $sizeLabel = (int) ($item['bytes'] ?? 0) > 0
                                                        ? number_format(((int) $item['bytes']) / 1024 / 1024, 2) . ' MB'
                                                        : '-';

                                                    $statusLabel = trim((string) ($item['status'] ?? '')) !== ''
                                                        ? ucfirst(str_replace('_', ' ', (string) $item['status']))
                                                        : 'Sin estatus';
                                                @endphp

                                                <tr>
                                                    <td>
                                                        <span class="sv2UploadBadge
                                                            {{ ($item['kind'] ?? '') === 'xml' ? 'sv2UploadBadge--xml' : '' }}
                                                            {{ ($item['kind'] ?? '') === 'report' ? 'sv2UploadBadge--report' : '' }}
                                                        ">
                                                            {{ $kindLabel }}
                                                        </span>
                                                    </td>

                                                    <td>
                                                        <div style="display:flex; flex-direction:column; gap:4px;">
                                                            <strong style="font-size:13px; color:#0f172a;">
                                                                {{ $item['original_name'] }}
                                                            </strong>
                                                            <span style="font-size:11px; color:#64748b;">
                                                                {{ $statusLabel }}
                                                            </span>
                                                        </div>
                                                    </td>

                                                    <td>{{ $directionLabel }}</td>
                                                    <td>{{ $sizeLabel }}</td>
                                                    <td>{{ $detailLabel }}</td>
                                                    <td>{{ optional($item['created_at'])->format('Y-m-d H:i') ?: '-' }}</td>
                                                    <td>
                                                        <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                                            <a
                                                                href="{{ route('cliente.sat.v2.download', ['type' => $item['kind'], 'id' => $item['id'], 'rfc' => $selectedRfc, 'view' => 1]) }}"
                                                                target="_blank"
                                                                class="sv2Btn sv2Btn--secondary sv2Btn--tiny"
                                                            >
                                                                Ver
                                                            </a>

                                                            <a
                                                                href="{{ route('cliente.sat.v2.download', ['type' => $item['kind'], 'id' => $item['id'], 'rfc' => $selectedRfc]) }}"
                                                                class="sv2Btn sv2Btn--primary sv2Btn--tiny"
                                                            >
                                                                Descargar
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="sv2Alert" style="margin:12px;">
                                    Aún no hay archivos subidos para este RFC en metadata, XML o reportes.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        @endif

           <div class="sv2Modal" id="rfcManagerModal" aria-hidden="true">
                <div class="sv2Modal__backdrop" data-sv2-close="rfcManagerModal"></div>

                <div class="sv2Modal__dialog sv2Modal__dialog--rfc" role="dialog" aria-modal="true" aria-labelledby="rfcManagerModalTitle">
                    <div class="sv2Modal__head sv2Modal__head--metadata sv2Modal__head--rfcManager">
                        <div class="sv2ModalHero">
                            <div class="sv2ModalHero__icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 8.75a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5Z" stroke="currentColor" stroke-width="1.8"/>
                                    <path d="M19.4 15a1 1 0 0 0 .2 1.1l.04.04a1.9 1.9 0 0 1 0 2.68 1.9 1.9 0 0 1-2.68 0l-.04-.04a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.92V19.7A1.9 1.9 0 0 1 13.3 21.6h-2.6a1.9 1.9 0 0 1-1.9-1.9v-.06a1 1 0 0 0-.6-.92 1 1 0 0 0-1.1.2l-.04.04a1.9 1.9 0 0 1-2.68 0 1.9 1.9 0 0 1 0-2.68l.04-.04a1 1 0 0 0 .2-1.1 1 1 0 0 0-.92-.6H3.7A1.9 1.9 0 0 1 1.8 13.3v-2.6a1.9 1.9 0 0 1 1.9-1.9h.06a1 1 0 0 0 .92-.6 1 1 0 0 0-.2-1.1l-.04-.04a1.9 1.9 0 0 1 0-2.68 1.9 1.9 0 0 1 2.68 0l.04.04a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.92V4.3A1.9 1.9 0 0 1 10.7 2.4h2.6a1.9 1.9 0 0 1 1.9 1.9v.06a1 1 0 0 0 .6.92 1 1 0 0 0 1.1-.2l.04-.04a1.9 1.9 0 0 1 2.68 0 1.9 1.9 0 0 1 0 2.68l-.04.04a1 1 0 0 0-.2 1.1 1 1 0 0 0 .92.6h.06a1.9 1.9 0 0 1 1.9 1.9v2.6a1.9 1.9 0 0 1-1.9 1.9h-.06a1 1 0 0 0-.92.6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                </svg>
                            </div>

                            <div class="sv2ModalHero__copy">
                                <div class="sv2StepEyebrow">Gestión RFC</div>
                                <h3 class="sv2Modal__title" id="rfcManagerModalTitle">Administrar RFC</h3>
                                <p class="sv2ModalHero__text">Alta, edición y baja lógica de tus RFC de trabajo.</p>
                            </div>
                        </div>

                        <button type="button" class="sv2Modal__close" data-sv2-close="rfcManagerModal" aria-label="Cerrar">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>

                    <div class="sv2Modal__body sv2Modal__body--rfcManager">
                        <div class="sv2RfcManagerLayout">
                            <aside class="sv2RfcPanel sv2RfcPanel--form">
                                <div class="sv2RfcPanel__head">
                                    <h4 class="sv2RfcPanel__title">Nuevo RFC</h4>
                                    <p class="sv2RfcPanel__text">Captura el RFC y opcionalmente su razón social.</p>
                                </div>

                                <form method="POST" action="{{ route('cliente.sat.v2.rfc.store', ['rfc' => $selectedRfc]) }}" class="sv2RfcCreateForm">
                                    @csrf

                                    <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                        <span class="sv2Float">RFC</span>
                                        <input
                                            type="text"
                                            name="rfc"
                                            class="sv2Input"
                                            maxlength="20"
                                            placeholder="Ej. XAXX010101000"
                                            style="text-transform:uppercase;"
                                            required
                                        >
                                    </div>

                                    <div class="sv2Field sv2Field--static sv2Field--noMargin">
                                        <span class="sv2Float">Razón social</span>
                                        <input
                                            type="text"
                                            name="razon_social"
                                            class="sv2Input"
                                            maxlength="255"
                                            placeholder="Nombre o razón social"
                                        >
                                    </div>

                                    <button type="submit" class="sv2Btn sv2Btn--primary sv2Btn--block">
                                        Guardar RFC
                                    </button>
                                </form>
                            </aside>

                            <section class="sv2RfcPanel sv2RfcPanel--list">
                                <div class="sv2RfcPanel__head sv2RfcPanel__head--row">
                                    <div>
                                        <h4 class="sv2RfcPanel__title">RFC registrados</h4>
                                        <p class="sv2RfcPanel__text">Selecciona, edita o da de baja un RFC existente.</p>
                                    </div>

                                    <span class="sv2RfcTotal">{{ $rfcs->count() }} {{ $rfcs->count() === 1 ? 'registro' : 'registros' }}</span>
                                </div>

                                <div class="sv2RfcTableWrap">
                                    <table class="sv2RfcTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 22%;">RFC</th>
                                                <th>Razón social</th>
                                                <th style="width: 120px;">Estado</th>
                                                <th style="width: 250px; text-align:right;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($rfcs as $rfc)
                                                <tr class="{{ $selectedRfc === $rfc->rfc ? 'is-active' : '' }}">
                                                    <td>
                                                        <div class="sv2RfcTable__rfc">{{ $rfc->rfc }}</div>
                                                    </td>

                                                    <td>
                                                        <form
                                                            method="POST"
                                                            action="{{ route('cliente.sat.v2.rfc.update', ['id' => $rfc->id, 'rfc' => $selectedRfc ?: $rfc->rfc]) }}"
                                                            class="sv2RfcTableForm"
                                                        >
                                                            @csrf
                                                            <input
                                                                type="text"
                                                                name="razon_social"
                                                                class="sv2Input sv2Input--table"
                                                                maxlength="255"
                                                                value="{{ $rfc->razon_social }}"
                                                                placeholder="Sin razón social"
                                                            >

                                                            <div class="sv2RfcTable__actions sv2RfcTable__actions--mobile">
                                                                <a href="{{ route('cliente.sat.v2.index', ['rfc' => $rfc->rfc]) }}" class="sv2Btn sv2Btn--secondary sv2Btn--tiny">
                                                                    Usar
                                                                </a>

                                                                <button type="submit" class="sv2Btn sv2Btn--secondary sv2Btn--tiny">
                                                                    Guardar
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </td>

                                                    <td>
                                                        @if($selectedRfc === $rfc->rfc)
                                                            <span class="sv2RfcBadge sv2RfcBadge--active">Activo</span>
                                                        @else
                                                            <span class="sv2RfcBadge">Disponible</span>
                                                        @endif
                                                    </td>

                                                    <td style="text-align:right;">
                                                        <div class="sv2RfcTable__actions">
                                                            <a href="{{ route('cliente.sat.v2.index', ['rfc' => $rfc->rfc]) }}" class="sv2Btn sv2Btn--secondary sv2Btn--tiny">
                                                                Usar
                                                            </a>

                                                            <form
                                                                method="POST"
                                                                action="{{ route('cliente.sat.v2.rfc.update', ['id' => $rfc->id, 'rfc' => $selectedRfc ?: $rfc->rfc]) }}"
                                                                class="sv2RfcTableSaveForm"
                                                            >
                                                                @csrf
                                                                <input type="hidden" name="razon_social" value="{{ $rfc->razon_social }}">
                                                                <button type="submit" class="sv2Btn sv2Btn--secondary sv2Btn--tiny sv2RfcSaveMirrorBtn">
                                                                    Guardar
                                                                </button>
                                                            </form>

                                                            <form
                                                                method="POST"
                                                                action="{{ route('cliente.sat.v2.rfc.delete', ['id' => $rfc->id, 'rfc' => $selectedRfc]) }}"
                                                                onsubmit="return confirm('¿Deseas dar de baja este RFC?');"
                                                                class="sv2RfcTableDeleteForm"
                                                            >
                                                                @csrf
                                                                <button type="submit" class="sv2Btn sv2Btn--danger sv2Btn--tiny">
                                                                    Baja
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4">
                                                        <div class="sv2RfcEmpty">
                                                            <strong>No hay RFC registrados.</strong>
                                                            <span>Captura el primero desde el panel izquierdo.</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>

                        <div class="sv2Modal__actions sv2Modal__actions--rfcManager">
                            <button type="button" class="sv2Btn sv2Btn--secondary" data-sv2-close="rfcManagerModal">
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        <div class="sv2Modal" id="metadataModal" aria-hidden="true">
            <div class="sv2Modal__backdrop" data-sv2-close="metadataModal"></div>

            <div class="sv2Modal__dialog sv2Modal__dialog--metadata" role="dialog" aria-modal="true" aria-labelledby="metadataModalTitle">
                <div class="sv2Modal__head sv2Modal__head--metadata">
                    <div class="sv2ModalHero">
                        <div class="sv2ModalHero__icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M7 4h8l4 4v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M15 4v4h4M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>

                        <div class="sv2ModalHero__copy">
                            <div class="sv2StepEyebrow">Carga de metadata</div>
                            <h3 class="sv2Modal__title" id="metadataModalTitle">Asociar archivo a un RFC</h3>
                            <p class="sv2ModalHero__text">Selecciona un RFC existente o registra uno nuevo antes de subir el archivo.</p>
                        </div>
                    </div>

                    <button type="button" class="sv2Modal__close" data-sv2-close="metadataModal" aria-label="Cerrar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('cliente.sat.v2.metadata.upload') }}" enctype="multipart/form-data" class="sv2Modal__body sv2Modal__body--metadata" data-sv2-upload-form="metadata">
                    @csrf

                    <input type="hidden" name="rfc_owner" value="{{ $selectedRfc }}">
                    <input type="hidden" name="sv2_open_modal" value="metadataModal">

                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">RFC y asociación</div>

                        <div class="sv2ModalGrid">
                            <div class="sv2Field sv2Field--static">
                                <span class="sv2Float">RFC de trabajo actual</span>
                                <input
                                    type="text"
                                    class="sv2Input"
                                    value="{{ $selectedRfc !== '' ? $selectedRfc : 'Sin RFC seleccionado' }}"
                                    disabled
                                >
                            </div>

                            <div class="sv2Field sv2Field--static">
                                <span class="sv2Float">Usar RFC existente</span>
                                <select name="rfc_existing" class="sv2Select">
                                    <option value="">Selecciona un RFC existente</option>
                                    @foreach($rfcs as $rfc)
                                        <option value="{{ $rfc->rfc }}" {{ $selectedRfc === $rfc->rfc ? 'selected' : '' }}>
                                            {{ $rfc->rfc }} — {{ $rfc->razon_social ?: 'Sin razón social' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="sv2Field sv2Field--static">
                                <span class="sv2Float">Capturar RFC nuevo</span>
                                <input
                                    type="text"
                                    name="rfc_new"
                                    class="sv2Input"
                                    maxlength="20"
                                    placeholder="Ej. XAXX010101000"
                                    value="{{ old('rfc_new') }}"
                                    style="text-transform:uppercase;"
                                >
                            </div>

                            <div class="sv2Field sv2Field--static">
                                <span class="sv2Float">Razón social</span>
                                <input
                                    type="text"
                                    name="razon_social"
                                    class="sv2Input"
                                    maxlength="255"
                                    placeholder="Opcional si capturas RFC nuevo"
                                    value="{{ old('razon_social') }}"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">Tipo de metadata</div>

                        <div class="sv2DirectionPicker">
                            <label class="sv2RadioCard">
                                <input type="radio" name="metadata_direction" value="emitidos" {{ old('metadata_direction', 'emitidos') === 'emitidos' ? 'checked' : '' }}>
                                <span class="sv2RadioCard__box">
                                    <span class="sv2RadioCard__icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <path d="M12 5v14M12 5l-4 4M12 5l4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <span class="sv2RadioCard__title">Emitidos</span>
                                    <span class="sv2RadioCard__text">Comprobantes emitidos por el RFC seleccionado.</span>
                                </span>
                            </label>

                            <label class="sv2RadioCard">
                                <input type="radio" name="metadata_direction" value="recibidos" {{ old('metadata_direction') === 'recibidos' ? 'checked' : '' }}>
                                <span class="sv2RadioCard__box">
                                    <span class="sv2RadioCard__icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <path d="M12 19V5M12 19l-4-4M12 19l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <span class="sv2RadioCard__title">Recibidos</span>
                                    <span class="sv2RadioCard__text">Comprobantes recibidos por el RFC seleccionado.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">Archivo</div>

                        <div class="sv2Field sv2Field--static">
                            <span class="sv2Float">Archivo metadata</span>
                            <input type="file" name="archivo" class="sv2File" accept=".txt,.csv,.zip" required>
                        </div>
                    </div>

                    <div class="sv2Modal__actions sv2Modal__actions--metadata">
                        <button type="button" class="sv2Btn sv2Btn--secondary" data-sv2-close="metadataModal">
                            Cancelar
                        </button>

                        <button type="submit" class="sv2Btn sv2Btn--primary" data-sv2-submit-upload>
                            Guardar y subir metadata
                        </button>
                    </div>
                </form>
            </div>
        </div>

        

        <div class="sv2Modal" id="xmlModal" aria-hidden="true">
            <div class="sv2Modal__backdrop" data-sv2-close="xmlModal"></div>

            <div class="sv2Modal__dialog sv2Modal__dialog--metadata" role="dialog" aria-modal="true" aria-labelledby="xmlModalTitle">
                <div class="sv2Modal__head sv2Modal__head--metadata">
                    <div class="sv2ModalHero">
                        <div class="sv2ModalHero__icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M8 7 4 12l4 5M16 7l4 5-4 5M14 5l-4 14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>

                        <div class="sv2ModalHero__copy">
                            <div class="sv2StepEyebrow">Carga de XML</div>
                            <h3 class="sv2Modal__title" id="xmlModalTitle">Asociar XML a un RFC</h3>
                            <p class="sv2ModalHero__text">Selecciona RFC, define si son emitidos o recibidos y opcionalmente asócialos a un lote metadata.</p>
                        </div>
                    </div>

                    <button type="button" class="sv2Modal__close" data-sv2-close="xmlModal" aria-label="Cerrar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('cliente.sat.v2.xml.upload') }}" enctype="multipart/form-data" class="sv2Modal__body sv2Modal__body--metadata" data-sv2-upload-form="xml">
                    @csrf

                    <input type="hidden" name="rfc_owner" value="{{ $selectedRfc }}">
                    <input type="hidden" name="sv2_open_modal" value="xmlModal">

                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">RFC y asociación</div>

                        <div class="sv2ModalGrid">
                            <div class="sv2Field sv2Field--static">
                                <span class="sv2Float">RFC de trabajo actual</span>
                                <input
                                    type="text"
                                    class="sv2Input"
                                    value="{{ $selectedRfc !== '' ? $selectedRfc : 'Sin RFC seleccionado' }}"
                                    disabled
                                >
                            </div>

                            <div class="sv2Field sv2Field--static">
                                <span class="sv2Float">Usar RFC existente</span>
                                <select name="rfc_existing" class="sv2Select">
                                    <option value="">Selecciona un RFC existente</option>
                                    @foreach($rfcs as $rfc)
                                        <option value="{{ $rfc->rfc }}" {{ $selectedRfc === $rfc->rfc ? 'selected' : '' }}>
                                            {{ $rfc->rfc }} — {{ $rfc->razon_social ?: 'Sin razón social' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="sv2Field sv2Field--static">
                                <span class="sv2Float">Capturar RFC nuevo</span>
                                <input
                                    type="text"
                                    name="rfc_new"
                                    class="sv2Input"
                                    maxlength="20"
                                    placeholder="Ej. XAXX010101000"
                                    value="{{ old('rfc_new') }}"
                                    style="text-transform:uppercase;"
                                >
                            </div>

                            <div class="sv2Field sv2Field--static">
                                <span class="sv2Float">Razón social</span>
                                <input
                                    type="text"
                                    name="razon_social"
                                    class="sv2Input"
                                    maxlength="255"
                                    placeholder="Opcional si capturas RFC nuevo"
                                    value="{{ old('razon_social') }}"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">Tipo de XML</div>

                        <div class="sv2DirectionPicker">
                            <label class="sv2RadioCard">
                                <input type="radio" name="xml_direction" value="emitidos" {{ old('xml_direction', 'emitidos') === 'emitidos' ? 'checked' : '' }}>
                                <span class="sv2RadioCard__box">
                                    <span class="sv2RadioCard__icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <path d="M12 5v14M12 5l-4 4M12 5l4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <span>
                                        <span class="sv2RadioCard__title">Emitidos</span>
                                        <span class="sv2RadioCard__text">XML de comprobantes emitidos.</span>
                                    </span>
                                </span>
                            </label>

                            <label class="sv2RadioCard">
                                <input type="radio" name="xml_direction" value="recibidos" {{ old('xml_direction') === 'recibidos' ? 'checked' : '' }}>
                                <span class="sv2RadioCard__box">
                                    <span class="sv2RadioCard__icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <path d="M12 19V5M12 19l-4-4M12 19l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <span>
                                        <span class="sv2RadioCard__title">Recibidos</span>
                                        <span class="sv2RadioCard__text">XML de comprobantes recibidos.</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">Asociar a metadata</div>

                        <div class="sv2Field sv2Field--static">
                            <span class="sv2Float">Lote metadata</span>
                            <select name="linked_metadata_upload_id" class="sv2Select">
                                <option value="">Sin asociación por ahora</option>
                                @foreach($metadataUploads as $upload)
                                    <option value="{{ $upload->id }}">
                                        #{{ $upload->id }} — {{ $upload->original_name }} — {{ number_format((int) $upload->rows_count) }} registros
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">Archivo XML</div>

                        <div class="sv2Field sv2Field--static">
                            <span class="sv2Float">Archivo XML / ZIP</span>
                            <input type="file" name="archivo_xml" class="sv2File" accept=".xml,.zip" required>
                        </div>
                    </div>

                    <div class="sv2Modal__actions sv2Modal__actions--metadata">
                        <button type="button" class="sv2Btn sv2Btn--secondary" data-sv2-close="xmlModal">
                            Cancelar
                        </button>

                        <button type="submit" class="sv2Btn sv2Btn--primary" data-sv2-submit-upload>
                            Guardar y subir XML
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="sv2Modal" id="reportModal" aria-hidden="true">
            <div class="sv2Modal__backdrop" data-sv2-close="reportModal"></div>

            <div class="sv2Modal__dialog sv2Modal__dialog--metadata" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
                <div class="sv2Modal__head sv2Modal__head--metadata">
                    <div class="sv2ModalHero">
                        <div class="sv2ModalHero__icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M7 4h8l4 4v12H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M15 4v4h4M8 12h8M8 16h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>

                        <div class="sv2ModalHero__copy">
                            <div class="sv2StepEyebrow">Carga de reporte</div>
                            <h3 class="sv2Modal__title" id="reportModalTitle">Asociar reporte a un RFC</h3>
                            <p class="sv2ModalHero__text">Sube tu reporte y opcionalmente relaciónalo con metadata y XML para futuras conciliaciones.</p>
                        </div>
                    </div>

                    <button type="button" class="sv2Modal__close" data-sv2-close="reportModal" aria-label="Cerrar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('cliente.sat.v2.report.upload') }}" enctype="multipart/form-data" class="sv2Modal__body sv2Modal__body--metadata" data-sv2-upload-form="report">
                    @csrf

                    <input type="hidden" name="rfc_owner" value="{{ $selectedRfc }}">
                    <input type="hidden" name="sv2_open_modal" value="reportModal">

                    <div class="sv2HeroCard sv2HeroCard--v3">
                        <div class="sv2HeroCard__header sv2HeroCard__header--v3">
                            <div class="sv2HeroCard__titles">
                                <span class="sv2HeroCard__eyebrow">Acceso</span>
                                <h3 class="sv2HeroCard__title sv2HeroCard__title--v3">RFC de trabajo</h3>
                            </div>

                            <button type="button" class="sv2GearBtn" data-sv2-open="rfcManagerModal" aria-label="Administrar RFC">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 8.75a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5Z" stroke="currentColor" stroke-width="1.8"/>
                                    <path d="M19.4 15a1 1 0 0 0 .2 1.1l.04.04a1.9 1.9 0 0 1 0 2.68 1.9 1.9 0 0 1-2.68 0l-.04-.04a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.92V19.7A1.9 1.9 0 0 1 13.3 21.6h-2.6a1.9 1.9 0 0 1-1.9-1.9v-.06a1 1 0 0 0-.6-.92 1 1 0 0 0-1.1.2l-.04.04a1.9 1.9 0 0 1-2.68 0 1.9 1.9 0 0 1 0-2.68l.04-.04a1 1 0 0 0 .2-1.1 1 1 0 0 0-.92-.6H3.7A1.9 1.9 0 0 1 1.8 13.3v-2.6a1.9 1.9 0 0 1 1.9-1.9h.06a1 1 0 0 0 .92-.6 1 1 0 0 0-.2-1.1l-.04-.04a1.9 1.9 0 0 1 0-2.68 1.9 1.9 0 0 1 2.68 0l.04.04a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.92V4.3A1.9 1.9 0 0 1 10.7 2.4h2.6a1.9 1.9 0 0 1 1.9 1.9v.06a1 1 0 0 0 .6.92 1 1 0 0 0 1.1-.2l.04-.04a1.9 1.9 0 0 1 2.68 0 1.9 1.9 0 0 1 0 2.68l-.04.04a1 1 0 0 0-.2 1.1 1 1 0 0 0 .92.6h.06a1.9 1.9 0 0 1 1.9 1.9v2.6a1.9 1.9 0 0 1-1.9 1.9h-.06a1 1 0 0 0-.92.6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>

                        <div class="sv2HeroCard__meta sv2HeroCard__meta--v3">
                            <span class="sv2Count">{{ $rfcs->count() }} RFC</span>

                            @if($selectedRfc !== '')
                                @php
                                    $selectedRfcRow = $rfcs->firstWhere('rfc', $selectedRfc);
                                    $selectedReason  = trim((string) ($selectedRfcRow->razon_social ?? ''));
                                @endphp

                                <span class="sv2ActiveRFC" title="{{ $selectedRfc }}">
                                    {{ $selectedRfc }}
                                </span>
                            @endif
                        </div>

                        <form method="GET" action="{{ route('cliente.sat.v2.index') }}" class="sv2HeroSearchForm" id="sv2RfcChooserForm" autocomplete="off">
                            <input type="hidden" name="rfc" id="sv2RfcHiddenInput" value="{{ $selectedRfc }}">

                            <div class="sv2RfcChooser" id="sv2RfcChooser">
                                <button type="button" class="sv2RfcChooser__control" id="sv2RfcChooserControl" aria-expanded="false" aria-haspopup="listbox">
                                    <div class="sv2RfcChooser__controlText">
                                        <span class="sv2RfcChooser__label">RFC seleccionado</span>
                                        <strong class="sv2RfcChooser__value" id="sv2RfcChooserValue">
                                            {{ $selectedReason !== '' ? $selectedReason : ($selectedRfc !== '' ? $selectedRfc : 'Selecciona un RFC de trabajo') }}
                                        </strong>
                                    </div>

                                    <span class="sv2RfcChooser__chevron" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </button>

                                <div class="sv2RfcChooser__menu" id="sv2RfcChooserMenu" hidden>
                                    <div class="sv2RfcChooser__searchWrap">
                                        <input
                                            type="text"
                                            class="sv2RfcChooser__search"
                                            id="sv2RfcChooserSearch"
                                            placeholder="Buscar RFC o razón social..."
                                        >
                                    </div>

                                    <div class="sv2RfcChooser__list" role="listbox" id="sv2RfcChooserList">
                                        @forelse($rfcs as $rfc)
                                            @php
                                                $razon = trim((string) ($rfc->razon_social ?? ''));
                                                $visibleName = $razon !== '' ? $razon : 'Sin razón social';
                                            @endphp

                                            <button
                                                type="button"
                                                class="sv2RfcOption {{ $selectedRfc === $rfc->rfc ? 'is-active' : '' }}"
                                                data-rfc="{{ $rfc->rfc }}"
                                                data-name="{{ e($visibleName) }}"
                                                data-search="{{ strtolower($rfc->rfc . ' ' . $visibleName) }}"
                                                role="option"
                                                aria-selected="{{ $selectedRfc === $rfc->rfc ? 'true' : 'false' }}"
                                            >
                                                <span class="sv2RfcOption__main">
                                                    <strong class="sv2RfcOption__name">{{ $visibleName }}</strong>
                                                    <span class="sv2RfcOption__meta">{{ $rfc->rfc }}</span>
                                                </span>

                                                @if($selectedRfc === $rfc->rfc)
                                                    <span class="sv2RfcOption__badge">Activo</span>
                                                @endif
                                            </button>
                                        @empty
                                            <div class="sv2RfcChooser__empty">
                                                No hay RFC registrados.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="sv2Btn sv2Btn--primary sv2Btn--heroWide">
                                Entrar
                            </button>
                        </form>
                    </div>
                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">Tipo de reporte</div>

                        <div class="sv2Field sv2Field--static">
                            <span class="sv2Float">Tipo</span>
                            <select name="report_type" class="sv2Select">
                                <option value="csv_report">Reporte CSV</option>
                                <option value="xlsx_report">Reporte XLSX</option>
                                <option value="xls_report">Reporte XLS</option>
                                <option value="txt_report">Reporte TXT</option>
                            </select>
                        </div>
                    </div>

                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">Dirección del reporte</div>

                        <div class="sv2DirectionPicker">
                            <label class="sv2RadioCard">
                                <input type="radio" name="report_direction" value="emitidos" {{ old('report_direction', 'emitidos') === 'emitidos' ? 'checked' : '' }}>
                                <span class="sv2RadioCard__box">
                                    <span class="sv2RadioCard__icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <path d="M12 5v14M12 5l-4 4M12 5l4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <span>
                                        <span class="sv2RadioCard__title">Emitidos</span>
                                        <span class="sv2RadioCard__text">Reporte de comprobantes emitidos.</span>
                                    </span>
                                </span>
                            </label>

                            <label class="sv2RadioCard">
                                <input type="radio" name="report_direction" value="recibidos" {{ old('report_direction') === 'recibidos' ? 'checked' : '' }}>
                                <span class="sv2RadioCard__box">
                                    <span class="sv2RadioCard__icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <path d="M12 19V5M12 19l-4-4M12 19l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <span>
                                        <span class="sv2RadioCard__title">Recibidos</span>
                                        <span class="sv2RadioCard__text">Reporte de comprobantes recibidos.</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">Asociar a metadata y XML</div>

                        <div class="sv2ModalGrid">
                            <div class="sv2Field sv2Field--static">
                                <span class="sv2Float">Lote metadata</span>
                                <select name="linked_metadata_upload_id" class="sv2Select">
                                    <option value="">Sin asociación por ahora</option>
                                    @foreach($metadataUploads as $upload)
                                        <option value="{{ $upload->id }}">
                                            #{{ $upload->id }} — {{ $upload->original_name }} — {{ number_format((int) $upload->rows_count) }} registros
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="sv2Field sv2Field--static">
                                <span class="sv2Float">Lote XML</span>
                                <select name="linked_xml_upload_id" class="sv2Select">
                                    <option value="">Sin asociación por ahora</option>
                                    @foreach($xmlUploads as $upload)
                                        <option value="{{ $upload->id }}">
                                            #{{ $upload->id }} — {{ $upload->original_name }} — {{ number_format((int) $upload->files_count) }} archivo(s)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">Archivo reporte</div>

                        <div class="sv2Field sv2Field--static">
                            <span class="sv2Float">Archivo reporte</span>
                            <input type="file" name="archivo_reporte" class="sv2File" accept=".csv,.xlsx,.xls,.txt" required>
                        </div>
                    </div>

                    <div class="sv2Modal__actions sv2Modal__actions--metadata">
                        <button type="button" class="sv2Btn sv2Btn--secondary" data-sv2-close="reportModal">
                            Cancelar
                        </button>

                        <button type="submit" class="sv2Btn sv2Btn--primary" data-sv2-submit-upload>
                            Guardar y subir reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>

                <div class="sv2Loading" id="sv2Loading" aria-hidden="true">
            <div class="sv2Loading__backdrop"></div>

            <div class="sv2Loading__dialog" role="status" aria-live="polite" aria-busy="true">
                <div class="sv2Loading__spinner" aria-hidden="true"></div>
                <div class="sv2Loading__title">Preparando carga...</div>
                <div class="sv2Loading__text">Estamos enviando el archivo al servidor. No cierres esta ventana.</div>

                <div class="sv2Loading__meta">
                    <div class="sv2Loading__line">
                        <span class="sv2Loading__label">Estado</span>
                        <strong class="sv2Loading__value" id="sv2LoadingStage">Iniciando envío</strong>
                    </div>

                    <div class="sv2Loading__line">
                        <span class="sv2Loading__label">Tiempo transcurrido</span>
                        <strong class="sv2Loading__value" id="sv2LoadingElapsed">0s</strong>
                    </div>
                </div>

                <div class="sv2Loading__hint" id="sv2LoadingHint">
                    Si el archivo es pesado, el proceso puede tardar varios minutos.
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const loading = $('#sv2Loading');
    const toggleBtn = $('#toggleMetadata');
    const metadataBlock = $('#metadataBlock');
    const toggleXmlBtn = $('#toggleXml');
    const xmlBlock = $('#xmlBlock');
    const toggleReportBtn = $('#toggleReport');
    const reportBlock = $('#reportBlock');
    const toggleDownloadsBtn = $('#toggleDownloads');
    const downloadsBlock = $('#downloadsBlock');
    const uploadForms = $$('[data-sv2-upload-form]');
    const toggleDataLoadBtn = $('#toggleDataLoad');
    const dataLoadBlock = $('#dataLoadBlock');
  
    let loadingTimer = null;
    let loadingSeconds = 0;

    function syncCollapsedIcon(button, block) {
        if (!button || !block) return;
        const icon = $('.sv2MetaBar__icon', button);
        if (!icon) return;
        icon.textContent = block.classList.contains('sv2Section--collapsed') ? '+' : '−';
    }

    syncCollapsedIcon(toggleBtn, metadataBlock);
    syncCollapsedIcon(toggleXmlBtn, xmlBlock);
    syncCollapsedIcon(toggleReportBtn, reportBlock);
    syncCollapsedIcon(toggleDownloadsBtn, downloadsBlock);
    syncCollapsedIcon(toggleDataLoadBtn, dataLoadBlock);

    function setBodyModalState(isOpen) {
        document.body.classList.toggle('sv2-modal-open', isOpen);
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setBodyModalState(true);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.sv2Modal.is-open') && !(loading && loading.classList.contains('is-open'))) {
            setBodyModalState(false);
        }
    }

    function closeAllModals() {
        $$('.sv2Modal.is-open').forEach(function (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        });

        if (!(loading && loading.classList.contains('is-open'))) {
            setBodyModalState(false);
        }
    }

    function stopLoadingTimer() {
        if (loadingTimer) {
            clearInterval(loadingTimer);
            loadingTimer = null;
        }
    }

    function setLoadingTexts(type) {
        if (!loading) return;

        const title = $('.sv2Loading__title', loading);
        const text = $('.sv2Loading__text', loading);

        if (type === 'xml') {
            if (title) title.textContent = 'Cargando XML...';
            if (text) text.textContent = 'Estamos subiendo el XML y asociándolo al RFC y al lote metadata seleccionado.';
            return;
        }

        if (type === 'report') {
            if (title) title.textContent = 'Cargando reporte...';
            if (text) text.textContent = 'Estamos subiendo el reporte y relacionándolo con el RFC y las cargas seleccionadas.';
            return;
        }

        if (title) title.textContent = 'Cargando metadata...';
        if (text) text.textContent = 'Estamos subiendo el archivo y registrando el lote para el RFC seleccionado.';
    }

    function updateLoadingStatus(stageText, hintText) {
        const stage = $('#sv2LoadingStage');
        const elapsed = $('#sv2LoadingElapsed');
        const hint = $('#sv2LoadingHint');

        if (stage && stageText) stage.textContent = stageText;
        if (hint && hintText) hint.textContent = hintText;
        if (elapsed) elapsed.textContent = loadingSeconds + 's';
    }

    function showLoading(type) {
        if (!loading) return;

        loading.classList.add('is-open');
        loading.setAttribute('aria-hidden', 'false');
        setBodyModalState(true);

        loadingSeconds = 0;
        setLoadingTexts(type);
        updateLoadingStatus('Preparando envío', 'Si el archivo es pesado, el proceso puede tardar varios minutos.');

        stopLoadingTimer();
        loadingTimer = setInterval(function () {
            loadingSeconds++;

            let stageText = 'Seguimos trabajando';
            if (loadingSeconds < 4) {
                stageText = 'Conectando con servidor';
            } else if (loadingSeconds < 10) {
                stageText = 'Esperando respuesta del servidor';
            } else if (loadingSeconds < 20) {
                stageText = 'Procesando archivo';
            }

            const hintText = loadingSeconds >= 12
                ? 'El servidor sigue procesando la carga. ZIP y archivos grandes pueden tardar más.'
                : null;

            updateLoadingStatus(stageText, hintText);
        }, 1000);
    }

    function hideLoading() {
        stopLoadingTimer();

        if (!loading) return;

        loading.classList.remove('is-open');
        loading.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.sv2Modal.is-open')) {
            setBodyModalState(false);
        }
    }

    function setFormDisabled(form, disabled) {
        $$('button, input, select, textarea', form).forEach(function (el) {
            if (el.type === 'hidden') return;
            el.disabled = disabled;
        });
    }

    function showAjaxError(xhr, response) {
        if (response && response.message) {
            alert(response.message);
            return;
        }

        if (response && response.errors) {
            const firstKey = Object.keys(response.errors)[0];
            if (firstKey && response.errors[firstKey] && response.errors[firstKey][0]) {
                alert(response.errors[firstKey][0]);
                return;
            }
        }

        let raw = (xhr.responseText || '').trim();
        if (raw.length > 500) {
            raw = raw.substring(0, 500) + '...';
        }

        alert('Ocurrió un error al cargar el archivo.\n\n' + (raw || 'Revisa el log del servidor.'));
    }

    function submitWithAjax(form) {
        const type = form.getAttribute('data-sv2-upload-form') || 'metadata';

        if (!form.reportValidity()) {
            return;
        }

        const xhr = new XMLHttpRequest();
        const formData = new FormData(form);

        closeAllModals();
        showLoading(type);
        setFormDisabled(form, true);

        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.onprogress = function (event) {
            const stage = $('#sv2LoadingStage');
            const hint = $('#sv2LoadingHint');

            if (!stage) return;

            if (event.lengthComputable) {
                const percent = Math.round((event.loaded / event.total) * 100);
                stage.textContent = 'Subiendo archivo (' + percent + '%)';
                if (hint) {
                    hint.textContent = 'Carga en progreso. Espera a que el servidor termine de procesar el archivo.';
                }
                return;
            }

            stage.textContent = 'Subiendo archivo';
        };

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;

            hideLoading();
            setFormDisabled(form, false);

            let response = null;

            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                response = null;
            }

            if (xhr.status >= 200 && xhr.status < 300 && response && response.ok) {
                window.location.href = response.redirect_url || window.location.href;
                return;
            }

            showAjaxError(xhr, response);
        };

        xhr.onerror = function () {
            hideLoading();
            setFormDisabled(form, false);
            alert('No se pudo completar la carga. Revisa tu conexión, la ruta o el log del servidor.');
        };

        xhr.send(formData);
    }

    $$('[data-sv2-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.getAttribute('data-sv2-open'));
        });
    });

    $$('[data-sv2-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            closeModal(btn.getAttribute('data-sv2-close'));
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (loading && loading.classList.contains('is-open')) return;
        closeAllModals();
    });

    uploadForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            submitWithAjax(form);
        });
    });

    if (toggleBtn && metadataBlock) {
        toggleBtn.addEventListener('click', function () {
            const collapsed = metadataBlock.classList.toggle('sv2Section--collapsed');
            const icon = $('.sv2MetaBar__icon', toggleBtn);

            if (icon) {
                icon.textContent = collapsed ? '+' : '−';
            }
        });
    }

    if (toggleXmlBtn && xmlBlock) {
        toggleXmlBtn.addEventListener('click', function () {
            const collapsed = xmlBlock.classList.toggle('sv2Section--collapsed');
            const icon = $('.sv2MetaBar__icon', toggleXmlBtn);

            if (icon) {
                icon.textContent = collapsed ? '+' : '−';
            }
        });
    }

    if (toggleReportBtn && reportBlock) {
        toggleReportBtn.addEventListener('click', function () {
            const collapsed = reportBlock.classList.toggle('sv2Section--collapsed');
            const icon = $('.sv2MetaBar__icon', toggleReportBtn);

            if (icon) {
                icon.textContent = collapsed ? '+' : '−';
            }
        });
    }

    if (toggleDownloadsBtn && downloadsBlock) {
        toggleDownloadsBtn.addEventListener('click', function () {
            downloadsBlock.classList.toggle('sv2Section--collapsed');
            syncCollapsedIcon(toggleDownloadsBtn, downloadsBlock);
        });
    }

    const sv2HasErrors = @json($errors->any());
    const sv2OpenModalOnLoad = @json(old('sv2_open_modal', 'metadataModal'));

    if (sv2HasErrors) {
        openModal(sv2OpenModalOnLoad || 'metadataModal');
    }

    if (toggleDataLoadBtn && dataLoadBlock) {
        const icon = $('.sv2MetaBar__icon', toggleDataLoadBtn);

        const syncDataLoadState = function () {
            const isHidden = dataLoadBlock.hasAttribute('hidden');
            if (icon) {
                icon.textContent = isHidden ? '+' : '−';
            }
            toggleDataLoadBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        };

        syncDataLoadState();

        toggleDataLoadBtn.addEventListener('click', function () {
            if (dataLoadBlock.hasAttribute('hidden')) {
                dataLoadBlock.removeAttribute('hidden');
            } else {
                dataLoadBlock.setAttribute('hidden', 'hidden');
            }

            syncDataLoadState();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.sv2RfcTable tbody tr').forEach(function (row) {
            const input = row.querySelector('.sv2RfcTableForm input[name="razon_social"]');
            const mirror = row.querySelector('.sv2RfcSaveMirrorBtn')?.closest('form')?.querySelector('input[name="razon_social"]');

            if (!input || !mirror) return;

            const sync = function () {
                mirror.value = input.value;
            };

            input.addEventListener('input', sync);
            input.addEventListener('change', sync);
            sync();
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
    const chooser = document.getElementById('sv2RfcChooser');
    const control = document.getElementById('sv2RfcChooserControl');
    const menu = document.getElementById('sv2RfcChooserMenu');
    const search = document.getElementById('sv2RfcChooserSearch');
    const list = document.getElementById('sv2RfcChooserList');
    const value = document.getElementById('sv2RfcChooserValue');
    const hidden = document.getElementById('sv2RfcHiddenInput');

    if (!chooser || !control || !menu || !search || !list || !value || !hidden) {
        return;
    }

    const options = Array.from(list.querySelectorAll('.sv2RfcOption'));

    const openMenu = function () {
        chooser.classList.add('is-open');
        menu.hidden = false;
        control.setAttribute('aria-expanded', 'true');
        setTimeout(function () {
            search.focus();
            search.select();
        }, 20);
    };

    const closeMenu = function () {
        chooser.classList.remove('is-open');
        menu.hidden = true;
        control.setAttribute('aria-expanded', 'false');
    };

    const normalize = function (text) {
        return (text || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    };

    const filterOptions = function () {
        const term = normalize(search.value);

        options.forEach(function (option) {
            const haystack = normalize(option.getAttribute('data-search') || '');
            const show = term === '' || haystack.includes(term);
            option.hidden = !show;
        });
    };

    const setSelected = function (option) {
        const selectedRfc = option.getAttribute('data-rfc') || '';
        const selectedName = option.getAttribute('data-name') || selectedRfc || 'Selecciona un RFC de trabajo';

        hidden.value = selectedRfc;
        value.textContent = selectedName;

        options.forEach(function (item) {
            item.classList.remove('is-active');
            item.setAttribute('aria-selected', 'false');

            const badge = item.querySelector('.sv2RfcOption__badge');
            if (badge) badge.remove();
        });

        option.classList.add('is-active');
        option.setAttribute('aria-selected', 'true');

        const main = option.querySelector('.sv2RfcOption__main');
        if (main && !option.querySelector('.sv2RfcOption__badge')) {
            const badge = document.createElement('span');
            badge.className = 'sv2RfcOption__badge';
            badge.textContent = 'Activo';
            option.appendChild(badge);
        }

        closeMenu();
    };

    control.addEventListener('click', function () {
        if (chooser.classList.contains('is-open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    search.addEventListener('input', filterOptions);

    options.forEach(function (option) {
        option.addEventListener('click', function () {
            setSelected(option);
        });
    });

    document.addEventListener('click', function (event) {
        if (!chooser.contains(event.target)) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
});

});
</script>
@endpush