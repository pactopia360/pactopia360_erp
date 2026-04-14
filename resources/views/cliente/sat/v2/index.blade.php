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

                <span class="sv2Pill">SAT V2 · Flujo operativo</span>

                <div class="sv2Hero__copy">
                    <h1 class="sv2Hero__title">Centro SAT</h1>

                    <p class="sv2Hero__subtitle">
                        Administra tus RFC, consulta cotizaciones, da seguimiento a descargas SAT y opera tu bóveda v2 desde un solo lugar.
                    </p>
                </div>

                <div class="sv2Hero__chips">
                    <span class="sv2Chip">RFC y credenciales</span>
                    <span class="sv2Chip">Cotizaciones</span>
                    <span class="sv2Chip">Seguimiento</span>
                    <span class="sv2Chip">Descargas SAT</span>
                    <span class="sv2Chip">Bóveda v2</span>
                </div>
            </div>
        </div>

        <aside class="sv2HeroCard sv2HeroCard--v2">
            <div class="sv2HeroCard__header">
                <div class="sv2HeroCard__titles">
                    <span class="sv2HeroCard__eyebrow">Bloque 1</span>
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
                @else
                    <span class="sv2ActiveRFC" title="Sin RFC activo">
                        Sin RFC activo
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
                    Usar RFC
                </button>
            </form>
        </aside>
    </div>
</section>

<section class="sv2DockWrap" aria-label="Navegación rápida del módulo SAT">
    <div class="sv2Dock" id="sv2SectionDock">
        <div class="sv2Dock__left">
            <div class="sv2Dock__brand">
                <div class="sv2Dock__title">Centro SAT</div>

                <div class="sv2Dock__rfc">
                    <span class="sv2Dock__rfcLabel">RFC activo</span>
                    <strong class="sv2Dock__rfcValue">
                        {{ $selectedRfc ?: 'Sin RFC' }}
                    </strong>
                </div>
            </div>

            <div class="sv2Dock__nav" role="tablist" aria-label="Secciones del módulo SAT">
            <button type="button" class="sv2Dock__link sv2Dock__link--back" data-sv2-jump="quotes">
                Cotizaciones
            </button>

            <button type="button" class="sv2Dock__link is-active" data-sv2-jump="centerSat">
                Centro SAT
            </button>

            <button type="button" class="sv2Dock__link" data-sv2-jump="metadata">
                Metadatos
            </button>

            <button type="button" class="sv2Dock__link" data-sv2-jump="xml">
                XML CFDI
            </button>

            <button type="button" class="sv2Dock__link" data-sv2-jump="report">
                Informes
            </button>

            <button type="button" class="sv2Dock__link" data-sv2-jump="fiscal">
                Resumen fiscal
            </button>

            <button type="button" class="sv2Dock__link" data-sv2-jump="downloads">
                Descargas
            </button>
        </div>
        </div>

        <div class="sv2Dock__actions">
            <button type="button" class="sv2Dock__action" id="sv2ExpandAll">
                Expandir todo
            </button>

            <button type="button" class="sv2Dock__action sv2Dock__action--ghost" id="sv2CollapseAll">
                Contraer todo
            </button>
        </div>
    </div>
</section>

        @php
            $quoteItemsCollection = collect($quoteItems ?? []);
            $quoteStatsData = $quoteStats ?? [
                'total'       => 0,
                'draft'       => 0,
                'requested'   => 0,
                'ready'       => 0,
                'paid'        => 0,
                'in_progress' => 0,
                'done'        => 0,
                'canceled'    => 0,
                'payable'     => 0,
            ];
            $quoteActiveItem = $quoteActive ?? null;
        @endphp

<section class="sv2Section" id="quotesSection">
    <div class="sv2MetaBar">
        <div class="sv2MetaBar__left">
            <span class="sv2MetaBar__title">Cotizaciones</span>
            <span class="sv2MetaBar__sub">
                {{ $selectedRfc !== '' ? 'Seguimiento comercial del RFC '.$selectedRfc : 'Selecciona un RFC para consultar cotizaciones' }}
            </span>
        </div>

        <button
            type="button"
            class="sv2MetaBar__toggle"
            id="toggleQuotes"
            aria-label="Expandir o contraer cotizaciones"
            aria-expanded="true"
        >
            <span class="sv2MetaBar__icon">−</span>
        </button>
    </div>

    <div class="sv2MetaContent" id="quotesBlock">
        @if($selectedRfc !== '')
            <div class="sv2KPIs">
                <article class="sv2Kpi sv2Kpi--meta">
                    <div class="sv2Kpi__top">
                        <div class="sv2Icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M7 4h8l4 4v12H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M15 4v4h4M8 12h8M8 16h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="sv2Kpi__label">Total</div>
                    </div>
                    <div class="sv2Kpi__value">{{ number_format((int) ($quoteStatsData['total'] ?? 0)) }}</div>
                    <div class="sv2Kpi__desc">Solicitudes registradas.</div>
                </article>

                <article class="sv2Kpi sv2Kpi--xml">
                    <div class="sv2Kpi__top">
                        <div class="sv2Icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="sv2Kpi__label">Cotizadas</div>
                    </div>
                    <div class="sv2Kpi__value">{{ number_format((int) ($quoteStatsData['ready'] ?? 0)) }}</div>
                    <div class="sv2Kpi__desc">Listas para revisión o pago.</div>
                </article>

                <article class="sv2Kpi sv2Kpi--batch">
                    <div class="sv2Kpi__top">
                        <div class="sv2Icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="sv2Kpi__label">En descarga</div>
                    </div>
                    <div class="sv2Kpi__value">{{ number_format((int) ($quoteStatsData['in_progress'] ?? 0)) }}</div>
                    <div class="sv2Kpi__desc">Después del pago.</div>
                </article>

                <article class="sv2Kpi sv2Kpi--zip">
                    <div class="sv2Kpi__top">
                        <div class="sv2Icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M20 7 9 18l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="sv2Kpi__label">Completadas</div>
                    </div>
                    <div class="sv2Kpi__value">{{ number_format((int) ($quoteStatsData['done'] ?? 0)) }}</div>
                    <div class="sv2Kpi__desc">Solicitudes finalizadas.</div>
                </article>
            </div>

            <div class="sv2MetaLayout" style="margin-top:14px;">
                <div class="sv2Card sv2MetaTableCard">
                    <div class="sv2DownloadsTableHead">
                        <div>
                            <h3 class="sv2Card__title">Listado de cotizaciones</h3>
                            <p class="sv2Card__text">Seguimiento comercial y operativo del RFC activo dentro de la misma v2.</p>
                        </div>

                        <span class="sv2RfcTotal">
                            {{ number_format($quoteItemsCollection->count()) }} {{ $quoteItemsCollection->count() === 1 ? 'cotización' : 'cotizaciones' }}
                        </span>
                    </div>

                    @if($quoteItemsCollection->count())
                        <div class="sv2MetaTableWrap">
                            <table class="sv2MetaTable">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>Tipo</th>
                                        <th>Estatus</th>
                                        <th>Periodo</th>
                                        <th>XML</th>
                                        <th>Total</th>
                                        <th>Avance</th>
                                        <th>Fecha</th>
                                        <th style="min-width:180px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($quoteItemsCollection as $quote)
                                        @php
                                            $quoteStatusUi = (string) ($quote['status_ui'] ?? 'en_proceso');
                                            $quoteStatusLabel = (string) ($quote['status_label'] ?? 'En proceso');
                                            $quoteProgress = (int) ($quote['progress'] ?? 0);

                                            $quoteTone = match($quoteStatusUi) {
                                                'borrador'    => 'sv2RfcBadge',
                                                'en_proceso'  => 'sv2RfcBadge',
                                                'cotizada'    => 'sv2RfcBadge sv2RfcBadge--active',
                                                'pagada'      => 'sv2MatchBadge sv2MatchBadge--ok',
                                                'en_descarga' => 'sv2MatchBadge sv2MatchBadge--warn',
                                                'completada'  => 'sv2MatchBadge sv2MatchBadge--ok',
                                                'cancelada'   => 'sv2Btn sv2Btn--danger sv2Btn--tiny',
                                                default       => 'sv2RfcBadge',
                                            };

                                            $periodLabel = '—';
                                            if (!empty($quote['date_from']) || !empty($quote['date_to'])) {
                                                $periodLabel = trim((string) ($quote['date_from'] ?? '')) . ' → ' . trim((string) ($quote['date_to'] ?? ''));
                                            }
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $quote['folio'] ?? ('SAT-'.$quote['id']) }}</strong>
                                            </td>

                                            <td>{{ $quote['type_label'] ?? 'Cotización SAT' }}</td>

                                            <td>
                                                @if($quoteStatusUi === 'cancelada')
                                                    <span class="sv2MatchBadge sv2MatchBadge--warn">{{ $quoteStatusLabel }}</span>
                                                @elseif(in_array($quoteStatusUi, ['pagada', 'completada'], true))
                                                    <span class="sv2MatchBadge sv2MatchBadge--ok">{{ $quoteStatusLabel }}</span>
                                                @elseif($quoteStatusUi === 'en_descarga')
                                                    <span class="sv2MatchBadge sv2MatchBadge--warn">{{ $quoteStatusLabel }}</span>
                                                @elseif($quoteStatusUi === 'cotizada')
                                                    <span class="sv2RfcBadge sv2RfcBadge--active">{{ $quoteStatusLabel }}</span>
                                                @else
                                                    <span class="sv2RfcBadge">{{ $quoteStatusLabel }}</span>
                                                @endif
                                            </td>

                                            <td>{{ $periodLabel }}</td>
                                            <td>{{ number_format((int) ($quote['xml_count'] ?? 0)) }}</td>
                                            <td>${{ number_format((float) ($quote['total'] ?? 0), 2) }}</td>

                                            <td style="min-width:160px;">
                                                <div style="display:flex; flex-direction:column; gap:6px;">
                                                    <div style="height:8px; border-radius:999px; background:#e2e8f0; overflow:hidden;">
                                                        <span style="display:block; height:100%; width:{{ $quoteProgress }}%; background:linear-gradient(90deg,#0f766e,#14b8a6);"></span>
                                                    </div>
                                                    <small style="font-size:11px; color:#64748b;">{{ $quoteProgress }}%</small>
                                                </div>
                                            </td>

                                            <td>{{ optional($quote['created_at'] ?? null)->format('Y-m-d H:i') ?: '—' }}</td>

                                                                                        <td>
                                            <div class="sv2RowActions">
                                                    @if((bool) ($quote['can_pay'] ?? false))
                                                        <form
                                                            method="POST"
                                                            action="{{ route('cliente.sat.quote.pay') }}"
                                                            class="sv2InlinePayForm"
                                                        >
                                                            @csrf
                                                            <input type="hidden" name="sat_download_id" value="{{ (int) ($quote['id'] ?? 0) }}">

                                                            <button type="submit" class="sv2Btn sv2Btn--primary sv2Btn--tiny">
                                                                Pagar
                                                            </button>
                                                        </form>
                                                    @elseif(($quote['status_ui'] ?? '') === 'en_descarga')
                                                        <button type="button" class="sv2Btn sv2Btn--secondary sv2Btn--tiny" disabled>
                                                            En descarga
                                                        </button>
                                                    @elseif((bool) ($quote['is_paid'] ?? false))
                                                        <button type="button" class="sv2Btn sv2Btn--secondary sv2Btn--tiny" disabled>
                                                            Pagada
                                                        </button>
                                                    @elseif((bool) ($quote['is_done'] ?? false))
                                                        <button type="button" class="sv2Btn sv2Btn--secondary sv2Btn--tiny" disabled>
                                                            Completa
                                                        </button>
                                                    @elseif(($quote['status_ui'] ?? '') === 'cancelada')
                                                        <button type="button" class="sv2Btn sv2Btn--danger sv2Btn--tiny" disabled>
                                                            Cancelada
                                                        </button>
                                                    @else
                                                        <button type="button" class="sv2Btn sv2Btn--secondary sv2Btn--tiny" disabled>
                                                            Sin acción
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="sv2Alert" style="margin:12px;">
                            No hay cotizaciones registradas todavía para este RFC dentro del flujo comercial SAT.
                        </div>
                    @endif
                </div>
            </div>

            @if($quoteActiveItem)
                <div class="sv2MetaLayout" style="margin-top:14px;">
                    <div class="sv2Card sv2MetaTrendCard">
                        <div class="sv2MetaTrendCard__title">Cotización activa</div>

                        <div class="sv2DownloadsOriginGrid" style="margin-top:12px;">
                            <div class="sv2DownloadsOriginItem sv2DownloadsOriginItem--sat">
                                <span class="sv2DownloadsOriginItem__label">Folio</span>
                                <strong class="sv2DownloadsOriginItem__value">{{ $quoteActiveItem['folio'] ?? '—' }}</strong>
                            </div>

                            <div class="sv2DownloadsOriginItem sv2DownloadsOriginItem--v2">
                                <span class="sv2DownloadsOriginItem__label">Estatus</span>
                                <strong class="sv2DownloadsOriginItem__value">{{ $quoteActiveItem['status_label'] ?? 'En proceso' }}</strong>
                            </div>

                            <div class="sv2DownloadsOriginItem sv2DownloadsOriginItem--v1">
                                <span class="sv2DownloadsOriginItem__label">Total</span>
                                <strong class="sv2DownloadsOriginItem__value">${{ number_format((float) ($quoteActiveItem['total'] ?? 0), 2) }}</strong>
                            </div>
                        </div>

                        <div style="margin-top:16px;">
                            <div style="height:10px; border-radius:999px; background:#e2e8f0; overflow:hidden;">
                                <span style="display:block; height:100%; width:{{ (int) ($quoteActiveItem['progress'] ?? 0) }}%; background:linear-gradient(90deg,#1d4ed8,#22c55e);"></span>
                            </div>
                            <div style="margin-top:8px; font-size:12px; color:#64748b;">
                                Avance actual: {{ (int) ($quoteActiveItem['progress'] ?? 0) }}%
                            </div>
                        </div>

                        @if(!empty($quoteActiveItem['notes']))
                            <div class="sv2Alert" style="margin-top:14px;">
                                {{ $quoteActiveItem['notes'] }}
                            </div>
                        @endif

                        <div class="sv2RowActions" style="margin-top:14px;">
                            @if((bool) ($quoteActiveItem['can_pay'] ?? false))
                                <form
                                    method="POST"
                                    action="{{ route('cliente.sat.quote.pay') }}"
                                    class="sv2InlinePayForm"
                                >
                                    @csrf
                                    <input type="hidden" name="sat_download_id" value="{{ (int) ($quoteActiveItem['id'] ?? 0) }}">

                                    <button type="submit" class="sv2Btn sv2Btn--primary">
                                        Pagar cotización
                                    </button>
                                </form>
                            @elseif(($quoteActiveItem['status_ui'] ?? '') === 'en_descarga')
                                <button type="button" class="sv2Btn sv2Btn--secondary" disabled>
                                    Descarga en proceso
                                </button>
                            @elseif((bool) ($quoteActiveItem['is_paid'] ?? false))
                                <button type="button" class="sv2Btn sv2Btn--secondary" disabled>
                                    Pago confirmado
                                </button>
                            @elseif((bool) ($quoteActiveItem['is_done'] ?? false))
                                <button type="button" class="sv2Btn sv2Btn--secondary" disabled>
                                    Solicitud completada
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        @else
            <div class="sv2Alert" style="margin:12px;">
                Selecciona un RFC para visualizar las cotizaciones dentro de SAT v2.
            </div>
        @endif
    </div>
</section>

<section class="sv2Section" id="centerSatSection">
    <div class="sv2MetaBar">
        <div class="sv2MetaBar__left">
            <span class="sv2MetaBar__title">Centro SAT</span>
            <span class="sv2MetaBar__sub">
                {{ $selectedRfc !== '' ? 'RFC activo: '.$selectedRfc : 'Selecciona un RFC para comenzar a operar' }}
            </span>
        </div>

        <button
            type="button"
            class="sv2MetaBar__toggle"
            id="toggleCenterSat"
            aria-label="Expandir o contraer Centro SAT"
            aria-expanded="true"
        >
            <span class="sv2MetaBar__icon">−</span>
        </button>
    </div>

    <div class="sv2MetaContent" id="centerSatBlock">
        <div class="sv2KPIs">
            <article class="sv2Kpi sv2Kpi--meta">
                <div class="sv2Kpi__top">
                    <div class="sv2Icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M4 7a3 3 0 0 1 3-3h4M20 7a3 3 0 0 0-3-3h-4M4 17a3 3 0 0 0 3 3h4M20 17a3 3 0 0 1-3 3h-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M8 12h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="sv2Kpi__label">RFC registrados</div>
                </div>
                <div class="sv2Kpi__value">{{ number_format($rfcs->count()) }}</div>
                <div class="sv2Kpi__desc">Disponibles para operar.</div>
            </article>

            <article class="sv2Kpi sv2Kpi--xml">
                <div class="sv2Kpi__top">
                    <div class="sv2Icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="sv2Kpi__label">RFC activo</div>
                </div>
                <div class="sv2Kpi__value">{{ $selectedRfc ?: '—' }}</div>
                <div class="sv2Kpi__desc">{{ $selectedRfc ? 'Listo para operar.' : 'Selecciona un RFC.' }}</div>
            </article>

            <article class="sv2Kpi sv2Kpi--batch">
                <div class="sv2Kpi__top">
                    <div class="sv2Icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M7 4h8l4 4v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M15 4v4h4" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                    </div>
                    <div class="sv2Kpi__label">Metadata</div>
                </div>
                <div class="sv2Kpi__value">{{ number_format($metadataCount) }}</div>
                <div class="sv2Kpi__desc">Registros cargados.</div>
            </article>

            <article class="sv2Kpi sv2Kpi--zip">
                <div class="sv2Kpi__top">
                    <div class="sv2Icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M8 7 4 12l4 5M16 7l4 5-4 5M14 5l-4 14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="sv2Kpi__label">XML CFDI</div>
                </div>
                <div class="sv2Kpi__value">{{ number_format($cfdiCount) }}</div>
                <div class="sv2Kpi__desc">CFDI procesados.</div>
            </article>
        </div>
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

                        $fiscalRowsSource = collect();

            if (($xmlItemsFiltered ?? collect())->count() > 0) {
                $fiscalRowsSource = collect($xmlItemsFiltered);
            } elseif (($reportItemsFiltered ?? collect())->count() > 0) {
                $fiscalRowsSource = collect($reportItemsFiltered);
            }

            $fiscalSummary = [
                'ingresos_subtotal'        => 0.0,
                'ingresos_iva'             => 0.0,
                'ingresos_total'           => 0.0,
                'egresos_subtotal'         => 0.0,
                'egresos_iva'              => 0.0,
                'egresos_total'            => 0.0,
                'pagos_total'              => 0.0,
                'nomina_total'             => 0.0,
                'traslados_total'          => 0.0,
                'retenciones_total'        => 0.0,
                'emitidos_count'           => 0,
                'recibidos_count'          => 0,
                'sin_match_count'          => 0,
                'con_match_count'          => 0,
                'meses_activos'            => 0,
                'fuente'                   => 'Sin datos',
                'cobertura'                => 0,
                'ticket_ingreso'           => 0.0,
                'ticket_egreso'            => 0.0,
                'iva_cero_ingresos_count'  => 0,
                'iva_cero_egresos_count'   => 0,
                'top_cliente_rfc'          => '—',
                'top_cliente_nombre'       => '—',
                'top_cliente_total'        => 0.0,
                'top_proveedor_rfc'        => '—',
                'top_proveedor_nombre'     => '—',
                'top_proveedor_total'      => 0.0,
            ];

            $fiscalMonths = [];
            $fiscalInsights = collect();
            $fiscalHealthFlags = collect();

            if ($fiscalRowsSource->count() > 0) {
                $fiscalSummary['fuente'] = ($xmlItemsFiltered ?? collect())->count() > 0
                    ? 'XML CFDI'
                    : 'Reportes';

                $normalizeTipo = function ($value) {
                    $raw = strtoupper(trim((string) $value));

                    if ($raw === '') return '';
                    if (in_array($raw, ['I', 'INGRESO', 'INGRESOS'], true)) return 'I';
                    if (in_array($raw, ['E', 'EGRESO', 'EGRESOS'], true)) return 'E';
                    if (in_array($raw, ['P', 'PAGO', 'PAGOS'], true)) return 'P';
                    if (in_array($raw, ['N', 'NOMINA', 'NÓMINA'], true)) return 'N';
                    if (in_array($raw, ['T', 'TRASLADO', 'TRASLADOS'], true)) return 'T';

                    return $raw;
                };

                $fiscalRowsNormalized = $fiscalRowsSource
                    ->filter(fn ($row) => !empty($row->fecha_emision))
                    ->map(function ($row) use ($normalizeTipo) {
                        $isXml = isset($row->version_cfdi);

                        $subtotal = $isXml ? (float) ($row->subtotal ?? 0) : (float) ($row->total ?? 0);
                        $iva      = $isXml ? (float) ($row->iva ?? 0) : 0.0;
                        $total    = (float) ($row->total ?? ($subtotal + $iva));

                        $tipo = $normalizeTipo(
                            $row->tipo_comprobante
                            ?? $row->tipo
                            ?? data_get($row, 'meta.tipo_comprobante')
                            ?? ''
                        );

                        $direction = strtolower((string) ($row->direction ?? ''));
                        $hasMatch  = (int) data_get($row->meta, 'matched_metadata_item_id', 0) > 0;
                        $retenciones = (float) (
                            data_get($row, 'meta.impuestos.retenciones_total')
                            ?? data_get($row, 'meta.retenciones_total')
                            ?? 0
                        );

                        $counterpartyRfc = '';
                        $counterpartyName = '';

                        if ($tipo === 'I') {
                            $counterpartyRfc  = (string) ($row->rfc_receptor ?? $row->receptor_rfc ?? '');
                            $counterpartyName = (string) ($row->nombre_receptor ?? $row->receptor_nombre ?? '');
                        } elseif ($tipo === 'E' || $tipo === 'P' || $tipo === 'N' || $tipo === 'T') {
                            $counterpartyRfc  = (string) ($row->rfc_emisor ?? $row->emisor_rfc ?? '');
                            $counterpartyName = (string) ($row->nombre_emisor ?? $row->emisor_nombre ?? '');
                        }

                        return (object) [
                            'ym'               => optional($row->fecha_emision)?->format('Y-m'),
                            'fecha'            => optional($row->fecha_emision)?->format('Y-m-d'),
                            'subtotal'         => $subtotal,
                            'iva'              => $iva,
                            'total'            => $total,
                            'tipo'             => $tipo,
                            'direction'        => $direction,
                            'has_match'        => $hasMatch,
                            'retenciones'      => $retenciones,
                            'counterparty_rfc' => $counterpartyRfc,
                            'counterparty_name'=> $counterpartyName,
                        ];
                    })
                    ->values();

                $fiscalSummary['emitidos_count']  = $fiscalRowsNormalized->where('direction', 'emitidos')->count();
                $fiscalSummary['recibidos_count'] = $fiscalRowsNormalized->where('direction', 'recibidos')->count();
                $fiscalSummary['con_match_count'] = $fiscalRowsNormalized->where('has_match', true)->count();
                $fiscalSummary['sin_match_count'] = $fiscalRowsNormalized->where('has_match', false)->count();
                $fiscalSummary['meses_activos']   = $fiscalRowsNormalized->pluck('ym')->filter()->unique()->count();

                $ingresosRows = $fiscalRowsNormalized->filter(fn ($r) => $r->tipo === 'I');
                $egresosRows  = $fiscalRowsNormalized->filter(fn ($r) => $r->tipo === 'E');
                $pagosRows    = $fiscalRowsNormalized->filter(fn ($r) => $r->tipo === 'P');
                $nominaRows   = $fiscalRowsNormalized->filter(fn ($r) => $r->tipo === 'N');
                $trasladoRows = $fiscalRowsNormalized->filter(fn ($r) => $r->tipo === 'T');

                $fiscalSummary['ingresos_subtotal'] = (float) $ingresosRows->sum('subtotal');
                $fiscalSummary['ingresos_iva']      = (float) $ingresosRows->sum('iva');
                $fiscalSummary['ingresos_total']    = (float) $ingresosRows->sum('total');

                $fiscalSummary['egresos_subtotal']  = (float) $egresosRows->sum('subtotal');
                $fiscalSummary['egresos_iva']       = (float) $egresosRows->sum('iva');
                $fiscalSummary['egresos_total']     = (float) $egresosRows->sum('total');

                $fiscalSummary['pagos_total']       = (float) $pagosRows->sum('total');
                $fiscalSummary['nomina_total']      = (float) $nominaRows->sum('total');
                $fiscalSummary['traslados_total']   = (float) $trasladoRows->sum('total');
                $fiscalSummary['retenciones_total'] = (float) $fiscalRowsNormalized->sum('retenciones');

                $fiscalSummary['ticket_ingreso'] = $ingresosRows->count() > 0
                    ? round($fiscalSummary['ingresos_total'] / $ingresosRows->count(), 2)
                    : 0.0;

                $fiscalSummary['ticket_egreso'] = $egresosRows->count() > 0
                    ? round($fiscalSummary['egresos_total'] / $egresosRows->count(), 2)
                    : 0.0;

                $fiscalSummary['iva_cero_ingresos_count'] = $ingresosRows
                    ->filter(fn ($r) => $r->subtotal > 0 && $r->iva <= 0)
                    ->count();

                $fiscalSummary['iva_cero_egresos_count'] = $egresosRows
                    ->filter(fn ($r) => $r->subtotal > 0 && $r->iva <= 0)
                    ->count();

                $baseCobertura = max(
                    1,
                    (int) ($metadataItems->total() ?? 0),
                    (int) ($metadataCount ?? 0),
                    (int) $fiscalRowsNormalized->count()
                );

                $fiscalSummary['cobertura'] = (int) round(($fiscalRowsNormalized->count() / $baseCobertura) * 100);

                $topCliente = $ingresosRows
                    ->groupBy('counterparty_rfc')
                    ->map(function ($rows, $rfc) {
                        $rows = collect($rows);
                        return [
                            'rfc'   => $rfc ?: '—',
                            'name'  => (string) ($rows->first()->counterparty_name ?? '—'),
                            'total' => (float) $rows->sum('total'),
                        ];
                    })
                    ->sortByDesc('total')
                    ->first();

                if ($topCliente) {
                    $fiscalSummary['top_cliente_rfc']    = $topCliente['rfc'];
                    $fiscalSummary['top_cliente_nombre'] = $topCliente['name'] ?: $topCliente['rfc'];
                    $fiscalSummary['top_cliente_total']  = (float) $topCliente['total'];
                }

                $topProveedor = $egresosRows
                    ->groupBy('counterparty_rfc')
                    ->map(function ($rows, $rfc) {
                        $rows = collect($rows);
                        return [
                            'rfc'   => $rfc ?: '—',
                            'name'  => (string) ($rows->first()->counterparty_name ?? '—'),
                            'total' => (float) $rows->sum('total'),
                        ];
                    })
                    ->sortByDesc('total')
                    ->first();

                if ($topProveedor) {
                    $fiscalSummary['top_proveedor_rfc']    = $topProveedor['rfc'];
                    $fiscalSummary['top_proveedor_nombre'] = $topProveedor['name'] ?: $topProveedor['rfc'];
                    $fiscalSummary['top_proveedor_total']  = (float) $topProveedor['total'];
                }

                $fiscalMonths = $fiscalRowsNormalized
                    ->groupBy('ym')
                    ->map(function ($rows, $ym) {
                        $rows = collect($rows);

                        $ingresos = $rows->filter(fn ($r) => $r->tipo === 'I');
                        $egresos  = $rows->filter(fn ($r) => $r->tipo === 'E');
                        $pagos    = $rows->filter(fn ($r) => $r->tipo === 'P');

                        $ingresosSubtotal = (float) $ingresos->sum('subtotal');
                        $ingresosIva      = (float) $ingresos->sum('iva');
                        $ingresosTotal    = (float) $ingresos->sum('total');

                        $egresosSubtotal  = (float) $egresos->sum('subtotal');
                        $egresosIva       = (float) $egresos->sum('iva');
                        $egresosTotal     = (float) $egresos->sum('total');

                        $pagosTotal       = (float) $pagos->sum('total');
                        $ivaNeto          = $ingresosIva - $egresosIva;
                        $flujoNeto        = $ingresosTotal - $egresosTotal;
                        $matchPct         = $rows->count() > 0
                            ? (int) round(($rows->where('has_match', true)->count() / $rows->count()) * 100)
                            : 0;

                        return [
                            'ym'                 => (string) $ym,
                            'ingresos_subtotal'  => $ingresosSubtotal,
                            'ingresos_iva'       => $ingresosIva,
                            'ingresos_total'     => $ingresosTotal,
                            'egresos_subtotal'   => $egresosSubtotal,
                            'egresos_iva'        => $egresosIva,
                            'egresos_total'      => $egresosTotal,
                            'pagos_total'        => $pagosTotal,
                            'iva_neto'           => $ivaNeto,
                            'flujo_neto'         => $flujoNeto,
                            'match_pct'          => $matchPct,
                            'health'             => $matchPct >= 80 ? 'Alta' : ($matchPct >= 50 ? 'Media' : 'Baja'),
                        ];
                    })
                    ->sortBy('ym')
                    ->values();

                $fiscalIvaNeto = (float) $fiscalSummary['ingresos_iva'] - (float) $fiscalSummary['egresos_iva'];
                $fiscalFlujoNeto = (float) $fiscalSummary['ingresos_total'] - (float) $fiscalSummary['egresos_total'];

                $peakMonth = $fiscalMonths->sortByDesc('ingresos_total')->first();
                $lowCoverageMonth = $fiscalMonths->sortBy('match_pct')->first();

                $fiscalInsights = collect();

                if ($peakMonth) {
                    $fiscalInsights->push('Mayor facturación en ' . $peakMonth['ym'] . ' por $' . number_format((float) $peakMonth['ingresos_total'], 2) . '.');
                }

                if ($fiscalSummary['iva_cero_ingresos_count'] > 0) {
                    $fiscalInsights->push(number_format((int) $fiscalSummary['iva_cero_ingresos_count']) . ' CFDI de ingreso tienen subtotal con IVA en cero; conviene validar si son exentos o si falta revisar impuestos.');
                }

                if ($fiscalSummary['sin_match_count'] > 0) {
                    $fiscalInsights->push(number_format((int) $fiscalSummary['sin_match_count']) . ' CFDI siguen sin match contra metadata.');
                }

                if ($fiscalSummary['top_cliente_total'] > 0) {
                    $fiscalInsights->push('Cliente principal: ' . $fiscalSummary['top_cliente_rfc'] . ' con $' . number_format((float) $fiscalSummary['top_cliente_total'], 2) . '.');
                }

                if ($fiscalSummary['top_proveedor_total'] > 0) {
                    $fiscalInsights->push('Proveedor principal: ' . $fiscalSummary['top_proveedor_rfc'] . ' con $' . number_format((float) $fiscalSummary['top_proveedor_total'], 2) . '.');
                }

                if ($lowCoverageMonth) {
                    $fiscalInsights->push('Mes con menor cobertura documental: ' . $lowCoverageMonth['ym'] . ' con ' . $lowCoverageMonth['match_pct'] . '%.');
                }

                $fiscalInsights = $fiscalInsights->filter()->unique()->take(5)->values();

                $fiscalHealthFlags = collect([
                    [
                        'label' => 'Cobertura documental',
                        'value' => $fiscalSummary['cobertura'] . '%',
                        'tone'  => $fiscalSummary['cobertura'] >= 80 ? 'ok' : ($fiscalSummary['cobertura'] >= 50 ? 'warn' : 'danger'),
                    ],
                    [
                        'label' => 'IVA neto',
                        'value' => '$' . number_format((float) $fiscalIvaNeto, 2),
                        'tone'  => $fiscalIvaNeto > 0 ? 'warn' : 'ok',
                    ],
                    [
                        'label' => 'Sin match',
                        'value' => number_format((int) $fiscalSummary['sin_match_count']),
                        'tone'  => $fiscalSummary['sin_match_count'] > 0 ? 'warn' : 'ok',
                    ],
                ]);
            } else {
                $fiscalIvaNeto = 0.0;
                $fiscalFlujoNeto = 0.0;
            }

                        $fiscalRail = [
                [
                    'label' => 'Facturación',
                    'value' => '$' . number_format((float) $fiscalSummary['ingresos_total'], 2),
                    'tone'  => 'brand',
                ],
                [
                    'label' => 'Gasto',
                    'value' => '$' . number_format((float) $fiscalSummary['egresos_total'], 2),
                    'tone'  => 'slate',
                ],
                [
                    'label' => 'IVA trasladado',
                    'value' => '$' . number_format((float) $fiscalSummary['ingresos_iva'], 2),
                    'tone'  => 'cyan',
                ],
                [
                    'label' => 'IVA acreditable',
                    'value' => '$' . number_format((float) $fiscalSummary['egresos_iva'], 2),
                    'tone'  => 'emerald',
                ],
                [
                    'label' => 'IVA neto',
                    'value' => '$' . number_format((float) $fiscalIvaNeto, 2),
                    'tone'  => $fiscalIvaNeto >= 0 ? 'amber' : 'emerald',
                ],
                [
                    'label' => 'Pagos',
                    'value' => '$' . number_format((float) $fiscalSummary['pagos_total'], 2),
                    'tone'  => 'violet',
                ],
            ];

            $fiscalChartRows = collect($fiscalMonths ?? [])->values();

            $fiscalChartMax = max(
                1,
                (float) $fiscalChartRows->max('ingresos_total'),
                (float) $fiscalChartRows->max('egresos_total'),
                (float) $fiscalChartRows->max('pagos_total')
            );

            $chartWidth = 760;
            $chartHeight = 220;
            $chartPaddingX = 18;
            $chartPaddingTop = 20;
            $chartPaddingBottom = 34;
            $plotWidth = $chartWidth - ($chartPaddingX * 2);
            $plotHeight = $chartHeight - $chartPaddingTop - $chartPaddingBottom;

            $buildSeriesPoints = function ($rows, string $key) use ($plotWidth, $plotHeight, $chartPaddingX, $chartPaddingTop, $fiscalChartMax) {
                $rows = collect($rows)->values();
                $count = max(1, $rows->count() - 1);

                return $rows->map(function ($row, $index) use ($key, $count, $plotWidth, $plotHeight, $chartPaddingX, $chartPaddingTop, $fiscalChartMax) {
                    $x = $chartPaddingX + (($plotWidth / max(1, $count)) * $index);
                    $value = (float) ($row[$key] ?? 0);
                    $y = $chartPaddingTop + ($plotHeight - (($value / max(1, $fiscalChartMax)) * $plotHeight));
                    return round($x, 2) . ',' . round($y, 2);
                })->implode(' ');
            };

            $fiscalIngresosPoints = $buildSeriesPoints($fiscalChartRows, 'ingresos_total');
            $fiscalEgresosPoints  = $buildSeriesPoints($fiscalChartRows, 'egresos_total');
            $fiscalPagosPoints    = $buildSeriesPoints($fiscalChartRows, 'pagos_total');

            $fiscalChartBars = $fiscalChartRows->map(function ($row) use ($fiscalChartMax) {
                $ivaValue = abs((float) ($row['iva_neto'] ?? 0));
                $height = $fiscalChartMax > 0
                    ? max(6, (int) round(($ivaValue / $fiscalChartMax) * 44))
                    : 6;

                return [
                    'ym'      => (string) ($row['ym'] ?? ''),
                    'height'  => $height,
                    'iva_neto'=> (float) ($row['iva_neto'] ?? 0),
                ];
            });

            $fiscalPopupSummary = [
                'insights_count'      => (int) collect($fiscalInsights ?? [])->count(),
                'top_cliente_rfc'     => (string) ($fiscalSummary['top_cliente_rfc'] ?? '—'),
                'top_proveedor_rfc'   => (string) ($fiscalSummary['top_proveedor_rfc'] ?? '—'),
                'sin_match_count'     => (int) ($fiscalSummary['sin_match_count'] ?? 0),
                'iva_cero_ingresos'   => (int) ($fiscalSummary['iva_cero_ingresos_count'] ?? 0),
            ];

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

        <section class="sv2Section sv2Section--metadata sv2Section--collapsed" id="metadataBlock">
            <div class="sv2MetaBar">
                <div class="sv2MetaBar__left">
                    <span class="sv2MetaBar__title">Metadata</span>
                    <span class="sv2MetaBar__sub">
                        {{ $selectedRfc !== '' ? number_format($metadataItems->total() ?? 0) . ' registros · RFC ' . $selectedRfc : 'Selecciona un RFC para visualizar la metadata' }}
                    </span>
                </div>

                <button type="button" class="sv2MetaBar__toggle" id="toggleMetadata" aria-label="Expandir o contraer metadata">
                    <span class="sv2MetaBar__icon">−</span>
                </button>
            </div>

            <div class="sv2MetaContent">
                @if($selectedRfc !== '')
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
                                                    <td>{{ optional($item->fecha_emision)->format('Y-m-d') ?: '—' }}</td>
                                                    <td>{{ $item->uuid ?: '—' }}</td>
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
                                                    <td>${{ number_format((float) $item->monto, 2) }}</td>
                                                    <td>{{ $item->estatus ?: '—' }}</td>
                                                    <td>{{ $item->direction === 'emitidos' ? 'E' : ($item->direction === 'recibidos' ? 'R' : '—') }}</td>
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
                @else
                    <div class="sv2Alert" style="margin:12px;">
                        Selecciona un RFC para visualizar la metadata.
                    </div>
                @endif
            </div>
        </section>

        <section class="sv2Section sv2Section--xml sv2Section--collapsed" id="xmlBlock">
            <div class="sv2MetaBar">
                <div class="sv2MetaBar__left">
                    <span class="sv2MetaBar__title">XML CFDI</span>
                    <span class="sv2MetaBar__sub">
                        {{ $selectedRfc !== '' ? number_format($xmlItemsFiltered->count()) . ' registros · RFC ' . $selectedRfc : 'Selecciona un RFC para visualizar XML CFDI' }}
                    </span>
                </div>

                <button type="button" class="sv2MetaBar__toggle" id="toggleXml" aria-label="Expandir o contraer XML">
                    <span class="sv2MetaBar__icon">−</span>
                </button>
            </div>

            <div class="sv2MetaContent">
                @if($selectedRfc !== '')
                    <div class="sv2MetaLayout sv2MetaLayout--top">
                        <div class="sv2Card sv2MetaTrendCard">
                            <div class="sv2MetaTrendCard__title">Tendencia XML</div>

                            @if(count($xmlMonthsChart ?? []))
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
                                    @foreach($xmlMonthsChart as $row)
                                        @php
                                            $emitidosHeight = $xmlMaxChartTotal > 0
                                                ? max(12, (int) (($row['emitidos_total'] / $xmlMaxChartTotal) * 140))
                                                : 12;

                                            $recibidosHeight = $xmlMaxChartTotal > 0
                                                ? max(12, (int) (($row['recibidos_total'] / $xmlMaxChartTotal) * 140))
                                                : 12;
                                        @endphp

                                        <div class="sv2XmlCompareChart__col">
                                            <div class="sv2XmlCompareChart__top">
                                                <div class="sv2XmlCompareChart__amount sv2XmlCompareChart__amount--emitidos">
                                                    E: ${{ number_format($row['emitidos_total'], 2) }}
                                                </div>
                                                <div class="sv2XmlCompareChart__amount sv2XmlCompareChart__amount--recibidos">
                                                    R: ${{ number_format($row['recibidos_total'], 2) }}
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
                                                {{ $row['ym'] }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div style="font-size:12px;color:#64748b;">
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
                                                    <td>{{ optional($xml->fecha_emision)->format('Y-m-d') ?: '—' }}</td>
                                                    <td>
                                                        <div>{{ $xml->uuid ?: '—' }}</div>
                                                        @if($zipEntry !== '')
                                                            <div class="sv2MetaSubtext">{{ $zipEntry }}</div>
                                                        @endif
                                                    </td>
                                                    <td>{{ $xml->version_cfdi ?: '—' }}</td>
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
                                                    <td>${{ number_format((float) $xml->total, 2) }}</td>
                                                    <td>{{ $xml->direction === 'emitidos' ? 'E' : ($xml->direction === 'recibidos' ? 'R' : '—') }}</td>
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
                @else
                    <div class="sv2Alert" style="margin:12px;">
                        Selecciona un RFC para visualizar los XML CFDI.
                    </div>
                @endif
            </div>
        </section>

        <section class="sv2Section sv2Section--report sv2Section--collapsed" id="reportBlock">
            <div class="sv2MetaBar">
                <div class="sv2MetaBar__left">
                    <span class="sv2MetaBar__title">Reportes</span>
                    <span class="sv2MetaBar__sub">
                        {{ $selectedRfc !== '' ? number_format(($reportItemsFiltered ?? collect())->count()) . ' registros · RFC ' . $selectedRfc : 'Selecciona un RFC para visualizar los reportes' }}
                    </span>
                </div>

                <button type="button" class="sv2MetaBar__toggle" id="toggleReport">
                    <span class="sv2MetaBar__icon">+</span>
                </button>
            </div>

            <div class="sv2MetaContent">
                @if($selectedRfc !== '')
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
                                                    <div class="sv2XmlCompareChart__bar sv2XmlCompareChart__bar--emitidos" style="height:{{ $emitidosHeight }}px;"></div>
                                                </div>

                                                <div class="sv2XmlCompareChart__track">
                                                    <div class="sv2XmlCompareChart__bar sv2XmlCompareChart__bar--recibidos" style="height:{{ $recibidosHeight }}px;"></div>
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
                @else
                    <div class="sv2Alert" style="margin:12px;">
                        Selecciona un RFC para visualizar los reportes.
                    </div>
                @endif
            </div>
        </section>

        <section class="sv2Section sv2Section--fiscal sv2Section--collapsed" id="fiscalBlock">
            <div class="sv2MetaBar">
                <div class="sv2MetaBar__left">
                    <span class="sv2MetaBar__title">Resumen fiscal</span>
                    <span class="sv2MetaBar__sub">
                        {{ $selectedRfc !== '' ? 'Mesa fiscal · RFC ' . $selectedRfc . ' · Fuente ' . $fiscalSummary['fuente'] : 'Selecciona un RFC para visualizar el resumen fiscal' }}
                    </span>
                </div>

                <button type="button" class="sv2MetaBar__toggle" id="toggleFiscal" aria-label="Expandir o contraer resumen fiscal">
                    <span class="sv2MetaBar__icon">+</span>
                </button>
            </div>

            <div class="sv2MetaContent">
                @if($selectedRfc !== '')
                    <div class="sv2FiscalShell">

                        <div class="sv2Card sv2FiscalHeroCard">
                            <div class="sv2FiscalHeroCard__head">
                                <div class="sv2FiscalHeroCard__copy">
                                    <h3 class="sv2FiscalHeroCard__title">Vista ejecutiva para contador y fiscalista</h3>
                                    <p class="sv2FiscalHeroCard__text">
                                        Lectura compacta de facturación, gasto, IVA, pagos y cobertura documental.
                                    </p>
                                </div>

                                <div class="sv2FiscalHeroCard__tags">
                                    <span class="sv2Tag">Fuente {{ $fiscalSummary['fuente'] }}</span>
                                    <span class="sv2Tag">Cobertura {{ $fiscalSummary['cobertura'] }}%</span>
                                    <span class="sv2Tag">{{ number_format((int) $fiscalSummary['meses_activos']) }} meses</span>

                                    <button type="button" class="sv2FiscalReprocessBtn" data-sv2-reprocess-smart data-rfc="{{ $selectedRfc }}">
                                        Releer faltantes
                                    </button>

                                    <button type="button" class="sv2FiscalReprocessBtn sv2FiscalReprocessBtn--ghost" data-sv2-open="fiscalReprocessModal" data-rfc="{{ $selectedRfc }}">
                                        Relectura avanzada
                                    </button>
                                </div>
                            </div>

                            <div class="sv2FiscalRail">
                                @foreach($fiscalRail as $railItem)
                                    <div class="sv2FiscalRail__item sv2FiscalRail__item--{{ $railItem['tone'] }}">
                                        <span class="sv2FiscalRail__label">{{ $railItem['label'] }}</span>
                                        <strong class="sv2FiscalRail__value">{{ $railItem['value'] }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="sv2FiscalToolbarCard sv2Card">
                            <form method="GET" action="{{ route('cliente.sat.v2.index') }}" class="sv2FiscalToolbar">
                                <input type="hidden" name="rfc" value="{{ $selectedRfc }}">

                                <div class="sv2FiscalToolbar__group">
                                    <span class="sv2FiscalToolbar__label">Vista</span>
                                    <div class="sv2FiscalPills">
                                        @php
                                            $fiscalView = trim((string) request()->query('fiscal_view', 'mensual'));
                                        @endphp

                                        <label class="sv2FiscalPill">
                                            <input type="radio" name="fiscal_view" value="mensual" {{ $fiscalView === 'mensual' ? 'checked' : '' }}>
                                            <span>Mensual</span>
                                        </label>

                                        <label class="sv2FiscalPill">
                                            <input type="radio" name="fiscal_view" value="trimestral" {{ $fiscalView === 'trimestral' ? 'checked' : '' }}>
                                            <span>Trimestral</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="sv2FiscalToolbar__group">
                                    <span class="sv2FiscalToolbar__label">Dirección</span>
                                    <select name="fiscal_direction" class="sv2Select">
                                        @php $fiscalDirection = trim((string) request()->query('fiscal_direction', '')); @endphp
                                        <option value="">Todas</option>
                                        <option value="emitidos" {{ $fiscalDirection === 'emitidos' ? 'selected' : '' }}>Emitidos</option>
                                        <option value="recibidos" {{ $fiscalDirection === 'recibidos' ? 'selected' : '' }}>Recibidos</option>
                                    </select>
                                </div>

                                <div class="sv2FiscalToolbar__group">
                                    <span class="sv2FiscalToolbar__label">Tipo</span>
                                    <select name="fiscal_tipo" class="sv2Select">
                                        @php $fiscalTipo = trim((string) request()->query('fiscal_tipo', '')); @endphp
                                        <option value="">Todos</option>
                                        <option value="I" {{ $fiscalTipo === 'I' ? 'selected' : '' }}>Ingreso</option>
                                        <option value="E" {{ $fiscalTipo === 'E' ? 'selected' : '' }}>Egreso</option>
                                        <option value="P" {{ $fiscalTipo === 'P' ? 'selected' : '' }}>Pago</option>
                                        <option value="N" {{ $fiscalTipo === 'N' ? 'selected' : '' }}>Nómina</option>
                                        <option value="T" {{ $fiscalTipo === 'T' ? 'selected' : '' }}>Traslado</option>
                                    </select>
                                </div>

                                <div class="sv2FiscalToolbar__group">
                                    <span class="sv2FiscalToolbar__label">Desde</span>
                                    <input type="date" name="fiscal_desde" class="sv2Input" value="{{ request()->query('fiscal_desde', '') }}">
                                </div>

                                <div class="sv2FiscalToolbar__group">
                                    <span class="sv2FiscalToolbar__label">Hasta</span>
                                    <input type="date" name="fiscal_hasta" class="sv2Input" value="{{ request()->query('fiscal_hasta', '') }}">
                                </div>

                                <div class="sv2FiscalToolbar__actions">
                                    <button type="submit" class="sv2Btn sv2Btn--primary sv2Btn--tiny">Aplicar</button>
                                    <a href="{{ route('cliente.sat.v2.index', ['rfc' => $selectedRfc]) }}#fiscalBlock" class="sv2Btn sv2Btn--secondary sv2Btn--tiny">Limpiar</a>
                                </div>
                            </form>
                        </div>

                        <div class="sv2FiscalTopGrid sv2FiscalTopGrid--enhanced">
                            <div class="sv2Card sv2FiscalChartPanel sv2FiscalChartPanel--xl">
                                <div class="sv2FiscalChartPanel__head">
                                    <div>
                                        <h3 class="sv2Card__title">Tendencia fiscal</h3>
                                        <p class="sv2Card__text">Comparativa de ingresos, egresos, pagos e IVA neto por periodo visible.</p>
                                    </div>

                                    <div class="sv2FiscalChartLegend">
                                        <span class="sv2FiscalChartLegend__item">
                                            <span class="sv2FiscalChartLegend__dot sv2FiscalChartLegend__dot--ingresos"></span>
                                            Ingresos
                                        </span>
                                        <span class="sv2FiscalChartLegend__item">
                                            <span class="sv2FiscalChartLegend__dot sv2FiscalChartLegend__dot--egresos"></span>
                                            Egresos
                                        </span>
                                        <span class="sv2FiscalChartLegend__item">
                                            <span class="sv2FiscalChartLegend__dot sv2FiscalChartLegend__dot--pagos"></span>
                                            Pagos
                                        </span>
                                        <span class="sv2FiscalChartLegend__item">
                                            <span class="sv2FiscalChartLegend__dot sv2FiscalChartLegend__dot--iva"></span>
                                            IVA neto
                                        </span>
                                    </div>
                                </div>

                                @if($fiscalChartRows->count())
                                    <div class="sv2FiscalChartCanvasWrap">
                                        <div id="sv2FiscalTrendChart" class="sv2FiscalChartCanvas"></div>
                                    </div>
                                @else
                                    <div class="sv2Alert sv2FiscalAlert">
                                        Sin periodos suficientes para construir la tendencia fiscal.
                                    </div>
                                @endif
                            </div>

                            <div class="sv2Card sv2FiscalChartPanel sv2FiscalChartPanel--compact">
                                <div class="sv2FiscalChartPanel__head">
                                    <div>
                                        <h3 class="sv2Card__title">Mix fiscal</h3>
                                        <p class="sv2Card__text">Composición general del corte visible.</p>
                                    </div>
                                </div>

                                @if($fiscalChartRows->count())
                                    <div class="sv2FiscalChartCanvasWrap sv2FiscalChartCanvasWrap--compact">
                                        <div id="sv2FiscalMixChart" class="sv2FiscalChartCanvas sv2FiscalChartCanvas--donut"></div>
                                    </div>
                                @else
                                    <div class="sv2Alert sv2FiscalAlert">
                                        Sin datos para gráfica de composición.
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="sv2FiscalBottomStrip">
                            <button type="button" class="sv2Card sv2FiscalActionCard sv2FiscalActionCard--horizontal" data-sv2-open="fiscalInsightsModal">
                                <div class="sv2FiscalActionCard__main">
                                    <div class="sv2FiscalActionCard__eyebrow">Radar IA</div>
                                    <div class="sv2FiscalActionCard__title">Hallazgos automáticos</div>
                                    <div class="sv2FiscalActionCard__text">Riesgos, cobertura y observaciones del corte visible.</div>
                                </div>
                                <div class="sv2FiscalActionCard__aside">
                                    <div class="sv2FiscalActionCard__value">{{ number_format(collect($fiscalInsights ?? [])->count()) }}</div>
                                </div>
                            </button>

                            <button type="button" class="sv2Card sv2FiscalActionCard sv2FiscalActionCard--horizontal" data-sv2-open="fiscalConcentrationModal">
                                <div class="sv2FiscalActionCard__main">
                                    <div class="sv2FiscalActionCard__eyebrow">Concentración</div>
                                    <div class="sv2FiscalActionCard__title">Clientes y proveedores</div>
                                    <div class="sv2FiscalActionCard__text">
                                        {{ $fiscalSummary['top_cliente_rfc'] }} · {{ $fiscalSummary['top_proveedor_rfc'] }}
                                    </div>
                                </div>
                                <div class="sv2FiscalActionCard__aside">
                                    <div class="sv2FiscalActionCard__value">TOP</div>
                                </div>
                            </button>

                            <div class="sv2Card sv2FiscalHealthStrip sv2FiscalHealthStrip--horizontal">
                                @foreach(($fiscalHealthFlags ?? collect()) as $flag)
                                    <div class="sv2FiscalHealthStrip__row">
                                        <span class="sv2FiscalHealthStrip__label">{{ $flag['label'] }}</span>
                                        <span class="sv2FiscalHealthStrip__value sv2FiscalHealthStrip__value--{{ $flag['tone'] }}">
                                            {{ $flag['value'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="sv2Card sv2FiscalMatrixCard">
                            <div class="sv2Card__head">
                                <div>
                                    <h3 class="sv2Card__title">Matriz mensual fiscal</h3>
                                    <p class="sv2Card__text">Base, IVA, total, pagos, flujo neto y cobertura por mes.</p>
                                </div>
                            </div>

                            @if(!empty($fiscalMonths) && count($fiscalMonths) > 0)
                                <div class="sv2MetaTableWrap">
                                    <table class="sv2MetaTable sv2FiscalMatrixTable">
                                        <thead>
                                            <tr>
                                                <th>Periodo</th>
                                                <th>Base ingreso</th>
                                                <th>IVA trasl.</th>
                                                <th>Total ingreso</th>
                                                <th>Base egreso</th>
                                                <th>IVA acred.</th>
                                                <th>Total egreso</th>
                                                <th>Pagos</th>
                                                <th>Flujo neto</th>
                                                <th>IVA neto</th>
                                                <th>Cobertura</th>
                                                <th>Lectura</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($fiscalMonths as $row)
                                                <tr>
                                                    <td>{{ $row['ym'] }}</td>
                                                    <td>${{ number_format((float) $row['ingresos_subtotal'], 2) }}</td>
                                                    <td>${{ number_format((float) $row['ingresos_iva'], 2) }}</td>
                                                    <td>${{ number_format((float) $row['ingresos_total'], 2) }}</td>
                                                    <td>${{ number_format((float) $row['egresos_subtotal'], 2) }}</td>
                                                    <td>${{ number_format((float) $row['egresos_iva'], 2) }}</td>
                                                    <td>${{ number_format((float) $row['egresos_total'], 2) }}</td>
                                                    <td>${{ number_format((float) $row['pagos_total'], 2) }}</td>
                                                    <td class="{{ ((float) $row['flujo_neto']) >= 0 ? 'sv2FiscalTone sv2FiscalTone--up' : 'sv2FiscalTone sv2FiscalTone--down' }}">
                                                        ${{ number_format((float) $row['flujo_neto'], 2) }}
                                                    </td>
                                                    <td class="{{ ((float) $row['iva_neto']) >= 0 ? 'sv2FiscalTone sv2FiscalTone--warn' : 'sv2FiscalTone sv2FiscalTone--up' }}">
                                                        ${{ number_format((float) $row['iva_neto'], 2) }}
                                                    </td>
                                                    <td>{{ $row['match_pct'] }}%</td>
                                                    <td>{{ $row['health'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="sv2Alert sv2FiscalAlert">
                                    Sin periodos suficientes para construir la matriz fiscal.
                                </div>
                            @endif
                        </div>

                    </div>
                @else
                    <div class="sv2Alert" style="margin:12px;">
                        Selecciona un RFC para visualizar el resumen fiscal.
                    </div>
                @endif
            </div>
        </section>

        <section class="sv2Section sv2Section--downloads sv2Section--collapsed" id="downloadsBlock">
            <div class="sv2MetaBar">
                <div class="sv2MetaBar__left">
                    <span class="sv2MetaBar__title">Descargas</span>
                    <span class="sv2MetaBar__sub">
                        {{ $selectedRfc !== '' ? number_format(($unifiedDownloadItems ?? collect())->count()) . ' archivo(s) visibles · RFC ' . $selectedRfc : 'Selecciona un RFC para visualizar archivos de Centro SAT, Bóveda v1 y Bóveda v2' }}
                    </span>
                </div>

                <button type="button" class="sv2MetaBar__toggle" id="toggleDownloads" aria-label="Expandir o contraer descargas">
                    <span class="sv2MetaBar__icon">+</span>
                </button>
            </div>

            <div class="sv2MetaContent">
                @if($selectedRfc !== '')
                    @php
                        $downloadItemsUnified = collect($unifiedDownloadItems ?? []);
                        $downloadsStorage = $storageBreakdown ?? [
                            'used_gb' => 0,
                            'available_gb' => 0,
                            'quota_gb' => 0,
                            'used_pct' => 0,
                            'available_pct' => 0,
                        ];

                        $usedPct = max(0, min(100, (float) ($downloadsStorage['used_pct'] ?? 0)));
                        $availablePct = max(0, min(100, (float) ($downloadsStorage['available_pct'] ?? 0)));
                        $donutDegrees = round(($usedPct / 100) * 360, 2);

                        $downloadsByOrigin = [
                            'centro_sat' => (int) (($downloadSources['centro_sat'] ?? 0)),
                            'boveda_v1'  => (int) (($downloadSources['boveda_v1'] ?? 0)),
                            'boveda_v2'  => (int) (($downloadSources['boveda_v2'] ?? 0)),
                        ];
                    @endphp

                    <div class="sv2DownloadsShell">
                        <div class="sv2DownloadsTop">
                            <div class="sv2Card sv2DownloadsStorageCard">
                                <div class="sv2DownloadsStorageCard__head">
                                    <div>
                                        <h3 class="sv2Card__title">Almacenamiento del RFC</h3>
                                        <p class="sv2Card__text">Capacidad total visible entre Centro SAT, Bóveda v1 y Bóveda v2.</p>
                                    </div>

                                    <span class="sv2Tag">RFC {{ $selectedRfc }}</span>
                                </div>

                                <div class="sv2DownloadsStorageCard__body">
                                    <div class="sv2DownloadsDonutWrap">
                                        <div
                                            class="sv2DownloadsDonut"
                                            style="--sv2-donut-deg: {{ $donutDegrees }}deg;"
                                            aria-label="Gráfica de almacenamiento"
                                        >
                                            <div class="sv2DownloadsDonut__inner">
                                                <strong>{{ number_format($usedPct, 1) }}%</strong>
                                                <span>utilizado</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="sv2DownloadsMetrics">
                                        <div class="sv2DownloadsMetric sv2DownloadsMetric--used">
                                            <span class="sv2DownloadsMetric__label">Espacio utilizado</span>
                                            <strong class="sv2DownloadsMetric__value">{{ number_format((float) ($downloadsStorage['used_gb'] ?? 0), 2) }} GB</strong>
                                        </div>

                                        <div class="sv2DownloadsMetric sv2DownloadsMetric--available">
                                            <span class="sv2DownloadsMetric__label">Espacio disponible</span>
                                            <strong class="sv2DownloadsMetric__value">{{ number_format((float) ($downloadsStorage['available_gb'] ?? 0), 2) }} GB</strong>
                                        </div>

                                        <div class="sv2DownloadsMetric sv2DownloadsMetric--total">
                                            <span class="sv2DownloadsMetric__label">Capacidad total</span>
                                            <strong class="sv2DownloadsMetric__value">{{ number_format((float) ($downloadsStorage['quota_gb'] ?? 0), 2) }} GB</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="sv2Card sv2DownloadsOriginCard">
                                <div class="sv2DownloadsOriginCard__head">
                                    <div>
                                        <h3 class="sv2Card__title">Origen de archivos</h3>
                                        <p class="sv2Card__text">Solo archivos asociados al usuario actual y al RFC seleccionado.</p>
                                    </div>
                                </div>

                                <div class="sv2DownloadsOriginGrid">
                                    <div class="sv2DownloadsOriginItem sv2DownloadsOriginItem--sat">
                                        <span class="sv2DownloadsOriginItem__label">Centro SAT</span>
                                        <strong class="sv2DownloadsOriginItem__value">{{ number_format($downloadsByOrigin['centro_sat']) }}</strong>
                                    </div>

                                    <div class="sv2DownloadsOriginItem sv2DownloadsOriginItem--v1">
                                        <span class="sv2DownloadsOriginItem__label">Bóveda v1</span>
                                        <strong class="sv2DownloadsOriginItem__value">{{ number_format($downloadsByOrigin['boveda_v1']) }}</strong>
                                    </div>

                                    <div class="sv2DownloadsOriginItem sv2DownloadsOriginItem--v2">
                                        <span class="sv2DownloadsOriginItem__label">Bóveda v2</span>
                                        <strong class="sv2DownloadsOriginItem__value">{{ number_format($downloadsByOrigin['boveda_v2']) }}</strong>
                                    </div>
                                </div>

                                <div class="sv2DownloadsOriginFoot">
                                    <span class="sv2DownloadsOriginFoot__item">Usado: {{ number_format((float) ($downloadsStorage['used_gb'] ?? 0), 2) }} GB</span>
                                    <span class="sv2DownloadsOriginFoot__item">Disponible: {{ number_format((float) ($downloadsStorage['available_gb'] ?? 0), 2) }} GB</span>
                                </div>
                            </div>
                        </div>

                        <div class="sv2MetaLayout">
                            <div class="sv2Card sv2MetaTableCard">
                                <div class="sv2DownloadsTableHead">
                                    <div>
                                        <h3 class="sv2Card__title">Archivos visibles del usuario</h3>
                                        <p class="sv2Card__text">Listado unificado de archivos disponibles para este RFC.</p>
                                    </div>

                                    <span class="sv2RfcTotal">
                                        {{ number_format($downloadItemsUnified->count()) }} {{ $downloadItemsUnified->count() === 1 ? 'archivo' : 'archivos' }}
                                    </span>
                                </div>

                                @if($downloadItemsUnified->count())
                                    <div class="sv2MetaTableWrap">
                                        <table class="sv2MetaTable sv2DownloadsTable">
                                            <thead>
                                                <tr>
                                                    <th>Origen</th>
                                                    <th>Tipo</th>
                                                    <th>Archivo</th>
                                                    <th>RFC</th>
                                                    <th>Dirección</th>
                                                    <th>Tamaño</th>
                                                    <th>Detalle</th>
                                                    <th>Fecha</th>
                                                    <th style="min-width:180px;">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($downloadItemsUnified as $item)
                                                    @php
                                                        $kindRaw = strtolower((string) ($item['kind'] ?? 'archivo'));

                                                        $kindLabel = match($kindRaw) {
                                                            'metadata' => 'Metadata',
                                                            'xml'      => 'XML',
                                                            'report'   => 'Reporte',
                                                            'zip'      => 'ZIP',
                                                            'csv'      => 'CSV',
                                                            'pdf'      => 'PDF',
                                                            default    => strtoupper($kindRaw),
                                                        };

                                                        $originRaw = strtolower((string) ($item['origin'] ?? ''));
                                                        $originLabel = (string) ($item['origin_label'] ?? 'Origen');
                                                        $directionLabel = trim((string) ($item['direction'] ?? '')) !== ''
                                                            ? ucfirst(str_replace('_', ' ', (string) $item['direction']))
                                                            : '—';

                                                        $statusLabel = trim((string) ($item['status'] ?? '')) !== ''
                                                            ? ucfirst(str_replace('_', ' ', (string) $item['status']))
                                                            : 'Disponible';

                                                        $downloadUrl = (string) ($item['download_url'] ?? '');
                                                        $viewUrl = (string) ($item['view_url'] ?? '');
                                                    @endphp

                                                    <tr>
                                                        <td>
                                                            <span class="sv2DownloadsOriginBadge sv2DownloadsOriginBadge--{{ $originRaw }}">
                                                                {{ $originLabel }}
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <span class="sv2UploadBadge
                                                                {{ $kindRaw === 'xml' ? 'sv2UploadBadge--xml' : '' }}
                                                                {{ $kindRaw === 'report' ? 'sv2UploadBadge--report' : '' }}
                                                            ">
                                                                {{ $kindLabel }}
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <div class="sv2DownloadsFileCell">
                                                                <strong class="sv2DownloadsFileCell__name">{{ $item['original_name'] ?? 'Archivo' }}</strong>
                                                                <span class="sv2DownloadsFileCell__status">{{ $statusLabel }}</span>
                                                            </div>
                                                        </td>

                                                        <td>{{ $item['rfc_owner'] ?? '—' }}</td>
                                                        <td>{{ $directionLabel }}</td>
                                                        <td>{{ $item['bytes_human'] ?? '0 B' }}</td>
                                                        <td>{{ $item['detail'] ?? 'Archivo' }}</td>
                                                        <td>{{ optional($item['created_at'] ?? null)->format('Y-m-d H:i') ?: '—' }}</td>
                                                        <td>
                                                            <div class="sv2RowActions">
                                                                @if($viewUrl !== '')
                                                                    <a
                                                                        href="{{ $viewUrl }}"
                                                                        target="_blank"
                                                                        class="sv2Btn sv2Btn--secondary sv2Btn--tiny"
                                                                    >
                                                                        Ver
                                                                    </a>
                                                                @endif

                                                                @if($downloadUrl !== '')
                                                                    <a
                                                                        href="{{ $downloadUrl }}"
                                                                        class="sv2Btn sv2Btn--primary sv2Btn--tiny"
                                                                    >
                                                                        Descargar
                                                                    </a>
                                                                @else
                                                                    <button type="button" class="sv2Btn sv2Btn--secondary sv2Btn--tiny" disabled>
                                                                        Sin ruta
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="sv2Alert" style="margin:12px;">
                                        Aún no hay archivos visibles para este RFC en Centro SAT, Bóveda v1 o Bóveda v2.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="sv2Alert" style="margin:12px;">
                        Selecciona un RFC para visualizar almacenamiento y archivos del usuario actual.
                    </div>
                @endif
            </div>
        </section>

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

            <div class="sv2Modal__dialog sv2Modal__dialog--metadata sv2Modal__dialog--reportClean" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
                <div class="sv2Modal__head sv2Modal__head--metadata sv2Modal__head--reportClean">
                    <div class="sv2ModalHero">
                        <div class="sv2ModalHero__icon sv2ModalHero__icon--report" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M7 4h8l4 4v12H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M15 4v4h4M8 12h8M8 16h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>

                        <div class="sv2ModalHero__copy">
                            <div class="sv2StepEyebrow">Carga de reporte</div>
                            <h3 class="sv2Modal__title" id="reportModalTitle">Asociar reporte a un RFC</h3>
                            <p class="sv2ModalHero__text">
                                Sube tu reporte y relaciónalo con metadata y XML para futuras conciliaciones.
                            </p>
                        </div>
                    </div>

                    <button type="button" class="sv2Modal__close" data-sv2-close="reportModal" aria-label="Cerrar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('cliente.sat.v2.report.upload') }}" enctype="multipart/form-data" class="sv2Modal__body sv2Modal__body--metadata sv2Modal__body--reportClean" data-sv2-upload-form="report">
                    @csrf

                    <input type="hidden" name="rfc_owner" value="{{ $selectedRfc }}">
                    <input type="hidden" name="sv2_open_modal" value="reportModal">

                    <div class="sv2ModalBlock sv2ModalBlock--reportAccent">
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
                        <div class="sv2ModalBlock__title">Tipo de reporte</div>

                        <div class="sv2Field sv2Field--static">
                            <span class="sv2Float">Tipo</span>
                            <select name="report_type" class="sv2Select">
                                <option value="csv_report" {{ old('report_type', 'csv_report') === 'csv_report' ? 'selected' : '' }}>Reporte CSV</option>
                                <option value="xlsx_report" {{ old('report_type') === 'xlsx_report' ? 'selected' : '' }}>Reporte XLSX</option>
                                <option value="xls_report" {{ old('report_type') === 'xls_report' ? 'selected' : '' }}>Reporte XLS</option>
                                <option value="txt_report" {{ old('report_type') === 'txt_report' ? 'selected' : '' }}>Reporte TXT</option>
                            </select>
                        </div>
                    </div>

                    <div class="sv2ModalBlock">
                        <div class="sv2ModalBlock__title">Dirección del reporte</div>

                        <div class="sv2DirectionPicker sv2DirectionPicker--report">
                            <label class="sv2RadioCard sv2RadioCard--report">
                                <input type="radio" name="report_direction" value="emitidos" {{ old('report_direction', 'emitidos') === 'emitidos' ? 'checked' : '' }}>
                                <span class="sv2RadioCard__box">
                                    <span class="sv2RadioCard__icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <path d="M12 5v14M12 5l-4 4M12 5l4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <span>
                                        <span class="sv2RadioCard__title">Emitidos</span>
                                        <span class="sv2RadioCard__text">Reporte de comprobantes emitidos por el RFC seleccionado.</span>
                                    </span>
                                </span>
                            </label>

                            <label class="sv2RadioCard sv2RadioCard--report">
                                <input type="radio" name="report_direction" value="recibidos" {{ old('report_direction') === 'recibidos' ? 'checked' : '' }}>
                                <span class="sv2RadioCard__box">
                                    <span class="sv2RadioCard__icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                            <path d="M12 19V5M12 19l-4-4M12 19l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <span>
                                        <span class="sv2RadioCard__title">Recibidos</span>
                                        <span class="sv2RadioCard__text">Reporte de comprobantes recibidos por el RFC seleccionado.</span>
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
                                        <option value="{{ $upload->id }}" {{ (string) old('linked_metadata_upload_id') === (string) $upload->id ? 'selected' : '' }}>
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
                                        <option value="{{ $upload->id }}" {{ (string) old('linked_xml_upload_id') === (string) $upload->id ? 'selected' : '' }}>
                                            #{{ $upload->id }} — {{ $upload->original_name }} — {{ number_format((int) $upload->files_count) }} archivo(s)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="sv2ModalBlock sv2ModalBlock--upload">
                        <div class="sv2ModalBlock__title">Archivo reporte</div>

                        <div class="sv2Field sv2Field--static">
                            <span class="sv2Float">Archivo reporte</span>
                            <input type="file" name="archivo_reporte" class="sv2File sv2File--report" accept=".csv,.xlsx,.xls,.txt" required>
                        </div>

                        <div class="sv2UploadInlineHint">
                            Formatos permitidos: CSV, XLSX, XLS y TXT.
                        </div>
                    </div>

                    <div class="sv2Modal__actions sv2Modal__actions--metadata sv2Modal__actions--reportClean">
                        <button type="button" class="sv2Btn sv2Btn--secondary" data-sv2-close="reportModal">
                            Cancelar
                        </button>

                        <button type="submit" class="sv2Btn sv2Btn--primary sv2Btn--reportSubmit" data-sv2-submit-upload>
                            Guardar y subir reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="sv2Modal" id="fiscalInsightsModal" aria-hidden="true">
            <div class="sv2Modal__backdrop" data-sv2-close="fiscalInsightsModal"></div>

            <div class="sv2Modal__dialog sv2Modal__dialog--metadata" role="dialog" aria-modal="true" aria-labelledby="fiscalInsightsModalTitle">
                <div class="sv2Modal__head sv2Modal__head--metadata">
                    <div class="sv2ModalHero">
                        <div class="sv2ModalHero__icon" aria-hidden="true">IA</div>
                        <div class="sv2ModalHero__copy">
                            <div class="sv2StepEyebrow">Radar IA fiscal</div>
                            <h3 class="sv2Modal__title" id="fiscalInsightsModalTitle">Hallazgos del corte visible</h3>
                            <p class="sv2ModalHero__text">Observaciones cortas, accionables y comerciales para contador y fiscalista.</p>
                        </div>
                    </div>

                    <button type="button" class="sv2Modal__close" data-sv2-close="fiscalInsightsModal" aria-label="Cerrar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="sv2Modal__body sv2Modal__body--metadata">
                    <div class="sv2FiscalPopupList">
                        @forelse(($fiscalInsights ?? collect()) as $insight)
                            <div class="sv2FiscalPopupItem">{{ $insight }}</div>
                        @empty
                            <div class="sv2FiscalPopupItem sv2FiscalPopupItem--muted">
                                Aún no hay suficiente información para generar observaciones automáticas.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="sv2Modal" id="fiscalConcentrationModal" aria-hidden="true">
            <div class="sv2Modal__backdrop" data-sv2-close="fiscalConcentrationModal"></div>

            <div class="sv2Modal__dialog sv2Modal__dialog--metadata" role="dialog" aria-modal="true" aria-labelledby="fiscalConcentrationModalTitle">
                <div class="sv2Modal__head sv2Modal__head--metadata">
                    <div class="sv2ModalHero">
                        <div class="sv2ModalHero__icon" aria-hidden="true">TOP</div>
                        <div class="sv2ModalHero__copy">
                            <div class="sv2StepEyebrow">Concentración operativa</div>
                            <h3 class="sv2Modal__title" id="fiscalConcentrationModalTitle">Principales contrapartes</h3>
                            <p class="sv2ModalHero__text">Cliente y proveedor principal del periodo visible.</p>
                        </div>
                    </div>

                    <button type="button" class="sv2Modal__close" data-sv2-close="fiscalConcentrationModal" aria-label="Cerrar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="sv2Modal__body sv2Modal__body--metadata">
                    <div class="sv2FiscalPopupGrid">
                        <div class="sv2FiscalPopupEntity">
                            <div class="sv2FiscalPopupEntity__label">Cliente principal</div>
                            <div class="sv2FiscalPopupEntity__rfc">{{ $fiscalSummary['top_cliente_rfc'] }}</div>
                            <div class="sv2FiscalPopupEntity__name">{{ $fiscalSummary['top_cliente_nombre'] }}</div>
                            <div class="sv2FiscalPopupEntity__amount">${{ number_format((float) $fiscalSummary['top_cliente_total'], 2) }}</div>
                        </div>

                        <div class="sv2FiscalPopupEntity">
                            <div class="sv2FiscalPopupEntity__label">Proveedor principal</div>
                            <div class="sv2FiscalPopupEntity__rfc">{{ $fiscalSummary['top_proveedor_rfc'] }}</div>
                            <div class="sv2FiscalPopupEntity__name">{{ $fiscalSummary['top_proveedor_nombre'] }}</div>
                            <div class="sv2FiscalPopupEntity__amount">${{ number_format((float) $fiscalSummary['top_proveedor_total'], 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sv2Modal" id="fiscalReprocessModal" aria-hidden="true">
            <div class="sv2Modal__backdrop" data-sv2-close="fiscalReprocessModal"></div>

            <div class="sv2Modal__dialog sv2Modal__dialog--metadata" role="dialog" aria-modal="true" aria-labelledby="fiscalReprocessModalTitle">
                <div class="sv2Modal__head sv2Modal__head--metadata">
                    <div class="sv2ModalHero">
                        <div class="sv2ModalHero__icon" aria-hidden="true">↻</div>
                        <div class="sv2ModalHero__copy">
                            <div class="sv2StepEyebrow">Relectura inteligente</div>
                            <h3 class="sv2Modal__title" id="fiscalReprocessModalTitle">Reprocesar XML históricos</h3>
                            <p class="sv2ModalHero__text">Primero se calcula el alcance y luego se ejecuta por bloque seguro.</p>
                        </div>
                    </div>

                    <button type="button" class="sv2Modal__close" data-sv2-close="fiscalReprocessModal" aria-label="Cerrar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="sv2Modal__body sv2Modal__body--metadata">
                    <form class="sv2ReprocessForm" id="sv2ReprocessAdvancedForm" data-sv2-reprocess-advanced-form>
                        @csrf
                        <input type="hidden" name="rfc_owner" value="{{ $selectedRfc }}">

                        <div class="sv2ModalBlock">
                            <div class="sv2ModalBlock__title">Alcance</div>

                            <div class="sv2DirectionPicker sv2DirectionPicker--stack">
                                <label class="sv2RadioCard">
                                    <input type="radio" name="scope" value="smart" checked>
                                    <span class="sv2RadioCard__box">
                                        <span>
                                            <span class="sv2RadioCard__title">Inteligente</span>
                                            <span class="sv2RadioCard__text">Detecta el bloque más seguro con más faltantes.</span>
                                        </span>
                                    </span>
                                </label>

                                <label class="sv2RadioCard">
                                    <input type="radio" name="scope" value="missing">
                                    <span class="sv2RadioCard__box">
                                        <span>
                                            <span class="sv2RadioCard__title">Solo faltantes</span>
                                            <span class="sv2RadioCard__text">CFDI con impuestos o subtotal incompleto.</span>
                                        </span>
                                    </span>
                                </label>

                                <label class="sv2RadioCard">
                                    <input type="radio" name="scope" value="month">
                                    <span class="sv2RadioCard__box">
                                        <span>
                                            <span class="sv2RadioCard__title">Bloque mensual</span>
                                            <span class="sv2RadioCard__text">Releer un solo mes del RFC.</span>
                                        </span>
                                    </span>
                                </label>

                                <label class="sv2RadioCard">
                                    <input type="radio" name="scope" value="direction">
                                    <span class="sv2RadioCard__box">
                                        <span>
                                            <span class="sv2RadioCard__title">Por dirección</span>
                                            <span class="sv2RadioCard__text">Solo emitidos o solo recibidos.</span>
                                        </span>
                                    </span>
                                </label>

                                <label class="sv2RadioCard">
                                    <input type="radio" name="scope" value="all">
                                    <span class="sv2RadioCard__box">
                                        <span>
                                            <span class="sv2RadioCard__title">Todo el RFC</span>
                                            <span class="sv2RadioCard__text">Usar solo si el preview lo permite.</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="sv2ModalBlock">
                            <div class="sv2ModalBlock__title">Parámetros</div>

                            <div class="sv2ModalGrid">
                                <div class="sv2Field sv2Field--static">
                                    <span class="sv2Float">Periodo YYYY-MM</span>
                                    <input type="text" name="period_ym" class="sv2Input" placeholder="2024-10">
                                </div>

                                <div class="sv2Field sv2Field--static">
                                    <span class="sv2Float">Dirección</span>
                                    <select name="direction" class="sv2Select">
                                        <option value="">Todas</option>
                                        <option value="emitidos">Emitidos</option>
                                        <option value="recibidos">Recibidos</option>
                                    </select>
                                </div>

                                <div class="sv2Field sv2Field--static">
                                    <span class="sv2Float">Límite</span>
                                    <input type="number" name="limit" class="sv2Input" min="1" max="2000" value="300">
                                </div>

                                <div class="sv2Field sv2Field--static">
                                    <span class="sv2Float">Lote</span>
                                    <input type="number" name="chunk_size" class="sv2Input" min="25" max="500" value="200">
                                </div>
                            </div>
                        </div>

                        <div class="sv2ReprocessPreview" id="sv2ReprocessPreview">
                            <div class="sv2ReprocessPreview__empty">
                                Ejecuta el preview para calcular el alcance seguro.
                            </div>
                        </div>

                        <div class="sv2Modal__actions sv2Modal__actions--metadata">
                            <button type="button" class="sv2Btn sv2Btn--secondary" data-sv2-preview-reprocess>
                                Calcular preview
                            </button>

                            <button type="button" class="sv2Btn sv2Btn--primary" data-sv2-run-reprocess>
                                Ejecutar relectura
                            </button>
                        </div>
                    </form>
                </div>
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

@php
    $sv2FiscalChartPayload = [
        'labels'   => collect($fiscalChartRows ?? [])->pluck('ym')->values()->all(),
        'ingresos' => collect($fiscalChartRows ?? [])->pluck('ingresos_total')->map(fn ($v) => round((float) $v, 2))->values()->all(),
        'egresos'  => collect($fiscalChartRows ?? [])->pluck('egresos_total')->map(fn ($v) => round((float) $v, 2))->values()->all(),
        'pagos'    => collect($fiscalChartRows ?? [])->pluck('pagos_total')->map(fn ($v) => round((float) $v, 2))->values()->all(),
        'iva_neto' => collect($fiscalChartRows ?? [])->pluck('iva_neto')->map(fn ($v) => round((float) $v, 2))->values()->all(),
        'mix'      => [
            round((float) ($fiscalSummary['ingresos_total'] ?? 0), 2),
            round((float) ($fiscalSummary['egresos_total'] ?? 0), 2),
            round((float) ($fiscalSummary['pagos_total'] ?? 0), 2),
            round((float) abs($fiscalIvaNeto ?? 0), 2),
        ],
        'mix_labels' => ['Ingresos', 'Egresos', 'Pagos', 'IVA neto'],
        'summary'  => [
            'fuente'    => (string) ($fiscalSummary['fuente'] ?? 'Sin datos'),
            'cobertura' => (int) ($fiscalSummary['cobertura'] ?? 0),
            'meses'     => (int) ($fiscalSummary['meses_activos'] ?? 0),
            'rfc'       => (string) $selectedRfc,
        ],
        'routes' => [
            'preview' => route('cliente.sat.v2.reprocess_xml.preview'),
            'run'     => route('cliente.sat.v2.reprocess_xml.run'),
        ],
    ];
@endphp

@push('scripts')
<script>
    window.sv2Config = {
        hasErrors: @json($errors->any()),
        openModalOnLoad: @json(old('sv2_open_modal', 'metadataModal')),
        fiscalCharts: @json($sv2FiscalChartPayload),
        selectedRfc: @json($selectedRfc),
        csrf: @json(csrf_token())
    };
</script>
<script src="{{ asset('assets/client/js/sat-v2.js') }}?v={{ filemtime(public_path('assets/client/js/sat-v2.js')) }}"></script>
@endpush
