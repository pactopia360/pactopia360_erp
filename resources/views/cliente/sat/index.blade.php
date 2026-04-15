{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\sat\index.blade.php --}}
@extends('layouts.cliente')

@section('title', 'Portal Descargas SAT')
@section('pageClass', 'page-sat page-sat-clean')

@push('styles')
@php
    $CSS_REL = 'assets/client/css/sat/sat-portal-v1.css';
    $CSS_ABS = public_path($CSS_REL);
    $CSS_V   = is_file($CSS_ABS) ? (string) filemtime($CSS_ABS) : null;

    $CSS_EXTRA_REL = 'assets/client/css/sat/sat-portal-v1-extra.css';
    $CSS_EXTRA_ABS = public_path($CSS_EXTRA_REL);
    $CSS_EXTRA_V   = is_file($CSS_EXTRA_ABS) ? (string) filemtime($CSS_EXTRA_ABS) : null;
@endphp

<link rel="stylesheet" href="{{ asset($CSS_REL) }}{{ $CSS_V ? ('?v='.$CSS_V) : '' }}">
<link rel="stylesheet" href="{{ asset($CSS_EXTRA_REL) }}{{ $CSS_EXTRA_V ? ('?v='.$CSS_EXTRA_V) : '' }}">
@endpush

@push('scripts')
@php
    $JS_REL = 'assets/client/js/sat/sat-portal-v1.js';
    $JS_ABS = public_path($JS_REL);
    $JS_V   = is_file($JS_ABS) ? (string) filemtime($JS_ABS) : null;

    $JS_EXTRA_REL = 'assets/client/js/sat/sat-portal-v1-extra.js';
    $JS_EXTRA_ABS = public_path($JS_EXTRA_REL);
    $JS_EXTRA_V   = is_file($JS_EXTRA_ABS) ? (string) filemtime($JS_EXTRA_ABS) : null;

    $rfcOptionsForJs = collect($rfcs ?? [])->map(function ($item) {
        $meta = $item->meta ?? [];

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($meta)) {
            $meta = [];
        }

        return [
            'id'           => (string) ($item->id ?? ''),
            'rfc'          => strtoupper(trim((string) ($item->rfc ?? ''))),
            'razon_social' => trim((string) ($item->razon_social ?? '')),
            'label'        => trim((string) ($item->razon_social ?? '')) !== ''
                ? strtoupper(trim((string) ($item->rfc ?? ''))) . ' · ' . trim((string) ($item->razon_social ?? ''))
                : strtoupper(trim((string) ($item->rfc ?? ''))),
            'is_active'    => (bool) ($meta['is_active'] ?? true),
        ];
    })->values();
@endphp

<script>
    window.P360_SAT = Object.assign({}, window.P360_SAT || {}, {
        quickCalcUrl: @json(route('cliente.sat.quick.calc')),
        quickPdfUrl: @json(route('cliente.sat.quick.pdf')),
        quoteCalcUrl: @json(route('cliente.sat.quote.calc')),
        externalInviteUrl: @json(route('cliente.sat.external.invite')),
        rfcStoreUrl: @json(route('cliente.sat.rfcs.store')),
        rfcOptions: @json($rfcOptionsForJs),
        vaultV2Url: @json(route('cliente.sat.v2.index')),
        vaultV1Url: @json(route('cliente.sat.vault')),
    });
</script>

<script src="{{ asset($JS_REL) }}{{ $JS_V ? ('?v='.$JS_V) : '' }}"></script>
<script src="{{ asset($JS_EXTRA_REL) }}{{ $JS_EXTRA_V ? ('?v='.$JS_EXTRA_V) : '' }}"></script>
@endpush

@section('content')
<div class="sat-clean-shell">
    <div class="sat-clean-container">

        <section class="sat-clean-hero sat-clean-hero--portal sat-clean-hero--portal-simple" aria-label="Portal Descargas SAT">
            <div class="sat-clean-hero__content sat-clean-hero__content--portal sat-clean-hero__content--portal-simple">
                <div class="sat-clean-hero__main sat-clean-hero__main--portal-simple">
                    <span class="sat-clean-hero__eyebrow">PORTAL SAT · DESCARGAS</span>

                    <h1 class="sat-clean-hero__title sat-clean-hero__title--portal">
                        Portal Descargas SAT
                    </h1>

                    <p class="sat-clean-hero__text sat-clean-hero__text--portal">
                        Administra tus RFC, da seguimiento a cotizaciones y consulta el estado general de tu centro SAT desde un solo lugar.
                    </p>

                    <div class="sat-clean-hero__chips">
                        <span class="sat-clean-hero__chip">RFC</span>
                        <span class="sat-clean-hero__chip">Cotizaciones</span>
                        <span class="sat-clean-hero__chip">Centro SAT</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="sat-clean-accordion" aria-label="Administración de RFC">
            <details class="sat-clean-accordion__item">
                <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
                    <div class="sat-clean-accordion__bar-left">
                        <span class="sat-clean-accordion__bar-title">RFC</span>
                        <span class="sat-clean-accordion__bar-text">
                            Administración de accesos SAT
                        </span>
                    </div>

                    <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </span>
                </summary>

                <div class="sat-clean-accordion__content">
                    <div class="sat-clean-rfc-admin sat-clean-rfc-admin--compact">

                        @php
                            $totalRfcs = isset($rfcs) ? count($rfcs) : 0;
                        @endphp

                        <div class="sat-clean-rfc-toolbar-v2">
                            <div class="sat-clean-rfc-toolbar-v2__left">
                                <div class="sat-clean-rfc-toolbar-v2__title-wrap">
                                    <h2 class="sat-clean-rfc-toolbar-v2__title">Listado de RFC</h2>
                                    <span class="sat-clean-rfc-toolbar-v2__count">{{ $totalRfcs }} registro(s)</span>
                                </div>

                                <div class="sat-clean-rfc-toolbar-v2__filters" role="group" aria-label="Filtros de RFC">
                                    <button type="button" class="sat-clean-filter-chip is-active" data-filter="todos">Todos</button>
                                    <button type="button" class="sat-clean-filter-chip" data-filter="activos">Activos</button>
                                    <button type="button" class="sat-clean-filter-chip" data-filter="con-fiel">Con FIEL</button>
                                    <button type="button" class="sat-clean-filter-chip" data-filter="con-csd">Con CSD</button>
                                </div>
                            </div>

                            <div class="sat-clean-rfc-toolbar-v2__right">
                                <button
                                    type="button"
                                    class="sat-clean-btn sat-clean-btn--primary sat-clean-btn--compact"
                                    data-rfc-open-modal="create"
                                    aria-label="Agregar RFC"
                                    title="Agregar RFC"
                                >
                                    + RFC
                                </button>
                            </div>
                        </div>

                        <div class="sat-clean-rfc-table-wrap sat-clean-rfc-table-wrap--minimal">
                            <table class="sat-clean-rfc-table sat-clean-rfc-table--minimal">
                                <thead>
                                    <thead>
                                        <tr>
                                            <th>RFC</th>
                                            <th>Razón social</th>
                                            <th>Origen</th>
                                            <th>Contacto</th>
                                            <th>Estado</th>
                                            <th>FIEL</th>
                                            <th>CSD</th>
                                            <th>Accesos</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                <tbody>
                                    @forelse(($rfcs ?? []) as $item)
                                        @php
                                            $itemId = (string) ($item->id ?? '');
                                            $itemRfc = strtoupper(trim((string) ($item->rfc ?? '')));
                                            $itemRazonSocial = trim((string) ($item->razon_social ?? ''));
                                            $itemMeta = $item->meta ?? [];

                                            if (is_string($itemMeta)) {
                                                $decoded = json_decode($itemMeta, true);
                                                $itemMeta = is_array($decoded) ? $decoded : [];
                                            }

                                            if (!is_array($itemMeta)) {
                                                $itemMeta = [];
                                            }

                                            $isActive = (bool) ($itemMeta['is_active'] ?? true);

                                            $tipoOrigen = trim((string) ($item->tipo_origen ?? ($itemMeta['tipo_origen'] ?? 'interno')));
                                            $contactEmail = trim((string) ($item->contact_email ?? ($itemMeta['contact_email'] ?? '')));
                                            $contactPhone = trim((string) ($item->contact_phone ?? ($itemMeta['contact_phone'] ?? '')));
                                            $contactName = trim((string) ($item->contact_name ?? ($itemMeta['contact_name'] ?? '')));
                                            $sourceLabel = trim((string) ($item->source_label ?? ($itemMeta['source_label'] ?? '')));
                                            $notes = trim((string) ($item->notes ?? ($itemMeta['notes'] ?? '')));

                                            $fielCerPath = (string) ($item->fiel_cer_path ?? $item->cer_path ?? data_get($itemMeta, 'fiel.cer', ''));
                                            $fielKeyPath = (string) ($item->fiel_key_path ?? $item->key_path ?? data_get($itemMeta, 'fiel.key', ''));
                                            $fielPasswordConfigured = !empty($item->fiel_password_enc ?? null) || !empty($item->key_password ?? null);
                                            $hasFielCer = $fielCerPath !== '' || !empty($itemMeta['fiel_cer']) || !empty($itemMeta['fiel']['cer']);
                                            $hasFielKey = $fielKeyPath !== '' || !empty($itemMeta['fiel_key']) || !empty($itemMeta['fiel']['key']);
                                            $hasFiel = $hasFielCer || $hasFielKey || $fielPasswordConfigured;

                                            $csdCerPath = (string) ($item->csd_cer_path ?? data_get($itemMeta, 'csd.cer', ''));
                                            $csdKeyPath = (string) ($item->csd_key_path ?? data_get($itemMeta, 'csd.key', ''));
                                            $csdPasswordConfigured = !empty($item->csd_password_enc ?? null) || !empty($itemMeta['csd_password']) || !empty($itemMeta['csd']['password']);
                                            $hasCsdCer = $csdCerPath !== '' || !empty($itemMeta['csd_cer']) || !empty($itemMeta['csd']['cer']);
                                            $hasCsdKey = $csdKeyPath !== '' || !empty($itemMeta['csd_key']) || !empty($itemMeta['csd']['key']);
                                            $hasCsd = $hasCsdCer || $hasCsdKey || $csdPasswordConfigured;

                                            $fielCerDownloadUrl = $hasFielCer
                                                ? route('cliente.sat.rfcs.asset.download', ['id' => $itemId, 'type' => 'fiel_cer'])
                                                : '';

                                            $fielKeyDownloadUrl = $hasFielKey
                                                ? route('cliente.sat.rfcs.asset.download', ['id' => $itemId, 'type' => 'fiel_key'])
                                                : '';

                                            $csdCerDownloadUrl = $hasCsdCer
                                                ? route('cliente.sat.rfcs.asset.download', ['id' => $itemId, 'type' => 'csd_cer'])
                                                : '';

                                            $csdKeyDownloadUrl = $hasCsdKey
                                                ? route('cliente.sat.rfcs.asset.download', ['id' => $itemId, 'type' => 'csd_key'])
                                                : '';
                                        @endphp

                                        <tr
                                            data-rfc-row="true"
                                            data-filter-active="{{ $isActive ? '1' : '0' }}"
                                            data-filter-fiel="{{ $hasFiel ? '1' : '0' }}"
                                            data-filter-csd="{{ $hasCsd ? '1' : '0' }}"
                                        >
                                            <td>
                                                <div class="sat-clean-rfc-inline-main">
                                                    <span class="sat-clean-rfc-inline-main__rfc">
                                                        {{ $itemRfc !== '' ? $itemRfc : 'RFC sin definir' }}
                                                    </span>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="sat-clean-rfc-inline-text">
                                                    {{ $itemRazonSocial !== '' ? $itemRazonSocial : 'Sin razón social registrada' }}
                                                </div>
                                            </td>

                                            <td>
                                                <div class="sat-clean-rfc-inline-text">
                                                    {{ $tipoOrigen !== '' ? ucfirst($tipoOrigen) : 'Interno' }}
                                                </div>
                                                <div class="sat-clean-rfc-inline-text text-muted">
                                                    {{ $sourceLabel !== '' ? $sourceLabel : 'Sin etiqueta' }}
                                                </div>
                                            </td>

                                            <td>
                                                <div class="sat-clean-rfc-inline-text">
                                                    {{ $contactName !== '' ? $contactName : 'Sin contacto' }}
                                                </div>
                                                <div class="sat-clean-rfc-inline-text text-muted">
                                                    {{ $contactEmail !== '' ? $contactEmail : ($contactPhone !== '' ? $contactPhone : 'Sin datos') }}
                                                </div>
                                            </td>

                                            <td>
                                                <span class="sat-clean-status-badge {{ $isActive ? 'is-success' : 'is-muted' }}">
                                                    {{ $isActive ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </td>

                                            <td>
                                                <div class="sat-clean-inline-status-group">
                                                    <span class="sat-clean-status-dot {{ $hasFiel ? 'is-success' : 'is-warning' }}"></span>
                                                    <span class="sat-clean-inline-status-text">
                                                        {{ $hasFiel ? 'Lista' : 'Pendiente' }}
                                                    </span>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="sat-clean-inline-status-group">
                                                    <span class="sat-clean-status-dot {{ $hasCsd ? 'is-success' : 'is-warning' }}"></span>
                                                    <span class="sat-clean-inline-status-text">
                                                        {{ $hasCsd ? 'Lista' : 'Pendiente' }}
                                                    </span>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="sat-clean-passwords-inline">
                                                    <div class="sat-clean-password-pill">
                                                        <span class="sat-clean-password-pill__label">FIEL</span>

                                                        @if($fielPasswordConfigured)
                                                            <button
                                                                type="button"
                                                                class="sat-clean-password-pill__action"
                                                                title="Ver contraseña FIEL"
                                                                aria-label="Ver contraseña FIEL"
                                                                data-password-reveal-url="{{ route('cliente.sat.rfcs.password.reveal', ['id' => $itemId, 'scope' => 'fiel']) }}"
                                                                data-password-label="FIEL"
                                                                data-rfc="{{ $itemRfc }}"
                                                            >
                                                                ••••••
                                                            </button>
                                                        @else
                                                            <span class="sat-clean-password-pill__empty">—</span>
                                                        @endif
                                                    </div>

                                                    <div class="sat-clean-password-pill">
                                                        <span class="sat-clean-password-pill__label">CSD</span>

                                                        @if($csdPasswordConfigured)
                                                            <button
                                                                type="button"
                                                                class="sat-clean-password-pill__action"
                                                                title="Ver contraseña CSD"
                                                                aria-label="Ver contraseña CSD"
                                                                data-password-reveal-url="{{ route('cliente.sat.rfcs.password.reveal', ['id' => $itemId, 'scope' => 'csd']) }}"
                                                                data-password-label="CSD"
                                                                data-rfc="{{ $itemRfc }}"
                                                            >
                                                                ••••••
                                                            </button>
                                                        @else
                                                            <span class="sat-clean-password-pill__empty">—</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="text-end">
                                                <div class="sat-clean-icon-actions">
                                                    <button
                                                        type="button"
                                                        class="sat-clean-icon-btn"
                                                        data-rfc-open-detail="true"
                                                        data-rfc-id="{{ $itemId }}"
                                                        data-rfc-value="{{ $itemRfc }}"
                                                        data-rfc-razon-social="{{ e($itemRazonSocial) }}"
                                                        data-rfc-tipo-origen="{{ e($tipoOrigen) }}"
                                                        data-rfc-contact-email="{{ e($contactEmail) }}"
                                                        data-rfc-contact-phone="{{ e($contactPhone) }}"
                                                        data-rfc-contact-name="{{ e($contactName) }}"
                                                        data-rfc-source-label="{{ e($sourceLabel) }}"
                                                        data-rfc-notes="{{ e($notes) }}"
                                                        data-rfc-is-active="{{ $isActive ? '1' : '0' }}"
                                                        data-rfc-has-fiel="{{ $hasFiel ? '1' : '0' }}"
                                                        data-rfc-has-csd="{{ $hasCsd ? '1' : '0' }}"
                                                        data-rfc-fiel-cer="{{ e($fielCerPath) }}"
                                                        data-rfc-fiel-key="{{ e($fielKeyPath) }}"
                                                        data-rfc-csd-cer="{{ e($csdCerPath) }}"
                                                        data-rfc-csd-key="{{ e($csdKeyPath) }}"
                                                        data-rfc-fiel-cer-download-url="{{ e($fielCerDownloadUrl) }}"
                                                        data-rfc-fiel-key-download-url="{{ e($fielKeyDownloadUrl) }}"
                                                        data-rfc-csd-cer-download-url="{{ e($csdCerDownloadUrl) }}"
                                                        data-rfc-csd-key-download-url="{{ e($csdKeyDownloadUrl) }}"
                                                        title="Ver detalle RFC"
                                                        aria-label="Ver detalle RFC"
                                                    >
                                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M2.25 12C3.9 8.25 7.38 5.75 12 5.75C16.62 5.75 20.1 8.25 21.75 12C20.1 15.75 16.62 18.25 12 18.25C7.38 18.25 3.9 15.75 2.25 12Z" stroke="currentColor" stroke-width="1.8"/>
                                                            <circle cx="12" cy="12" r="3.25" stroke="currentColor" stroke-width="1.8"/>
                                                        </svg>
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="sat-clean-icon-btn"
                                                        data-rfc-open-modal="edit"
                                                        data-rfc-id="{{ $itemId }}"
                                                        data-rfc-value="{{ $itemRfc }}"
                                                        data-rfc-razon-social="{{ e($itemRazonSocial) }}"
                                                        data-rfc-tipo-origen="{{ e($tipoOrigen) }}"
                                                        data-rfc-contact-email="{{ e($contactEmail) }}"
                                                        data-rfc-contact-phone="{{ e($contactPhone) }}"
                                                        data-rfc-contact-name="{{ e($contactName) }}"
                                                        data-rfc-source-label="{{ e($sourceLabel) }}"
                                                        data-rfc-notes="{{ e($notes) }}"
                                                        title="Editar RFC"
                                                        aria-label="Editar RFC"
                                                    >
                                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5V6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                            <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                        </svg>
                                                    </button>

                                                    <form
                                                        method="POST"
                                                        action="{{ route('cliente.sat.rfcs.delete', ['id' => $itemId]) }}"
                                                        class="sat-clean-inline-form"
                                                        data-rfc-delete-form="true"
                                                    >
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{ $itemId }}">
                                                        <input type="hidden" name="rfc" value="{{ $itemRfc }}">

                                                        <button
                                                            type="submit"
                                                            class="sat-clean-icon-btn sat-clean-icon-btn--danger"
                                                            title="Eliminar RFC"
                                                            aria-label="Eliminar RFC"
                                                        >
                                                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                <path d="M5 7H19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                                <path d="M9 7V5.8C9 5.35817 9.35817 5 9.8 5H14.2C14.6418 5 15 5.35817 15 5.8V7" stroke="currentColor" stroke-width="1.8"/>
                                                                <path d="M8 10V18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                                <path d="M12 10V18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                                <path d="M16 10V18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                                <path d="M6 7L6.7 18.2C6.76429 19.2286 7.61716 20 8.64778 20H15.3522C16.3828 20 17.2357 19.2286 17.3 18.2L18 7" stroke="currentColor" stroke-width="1.8"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">
                                                <div class="sat-clean-empty-state sat-clean-empty-state--compact">
                                                    <div class="sat-clean-empty-state__title">
                                                        No hay RFC registrados todavía
                                                    </div>
                                                    <div class="sat-clean-empty-state__text">
                                                        Agrega un RFC para comenzar a administrar accesos SAT.
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="sat-clean-btn sat-clean-btn--primary sat-clean-btn--compact"
                                                        data-rfc-open-modal="create"
                                                        aria-label="Agregar RFC"
                                                        title="Agregar RFC"
                                                    >
                                                        + RFC
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </details>
        </section>

        <section class="sat-clean-accordion" id="satQuotesSection" aria-label="Cotizaciones SAT">
            <details class="sat-clean-accordion__item">
                <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
                    <div class="sat-clean-accordion__bar-left">
                        <span class="sat-clean-accordion__bar-title">Cotizaciones</span>
                        <span class="sat-clean-accordion__bar-text">
                            Solicitudes de cotización y seguimiento del proceso
                        </span>
                    </div>

                    <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </span>
                </summary>

                <div class="sat-clean-accordion__content">
                    <div class="sat-clean-rfc-admin sat-clean-rfc-admin--compact" id="satQuotesPanel">
                        @php
                            $cotizacionesCollection = collect($cotizaciones ?? []);
                            $totalCotizaciones = $cotizacionesCollection->count();
                        @endphp

                        <div class="sat-clean-quote-toolbar" id="satQuotesToolbar">
                            <div class="sat-clean-quote-toolbar__left">
                                <div class="sat-clean-quote-toolbar__title-wrap">
                                    <h2 class="sat-clean-quote-toolbar__title" id="satQuotesTitle">Listado de cotizaciones</h2>
                                    <span class="sat-clean-quote-toolbar__count" id="satQuotesVisibleBadge">
                                        <span id="satQuoteVisibleCount">{{ $totalCotizaciones }}</span> visibles
                                    </span>
                                </div>

                                <div class="sat-clean-rfc-toolbar-v2__filters" id="satQuoteFilterGroup" role="group" aria-label="Filtros de cotizaciones">
                                    <button type="button" class="sat-clean-filter-chip is-active" data-quote-filter="todos">Todas</button>
                                    <button type="button" class="sat-clean-filter-chip" data-quote-filter="borrador">Borrador</button>
                                    <button type="button" class="sat-clean-filter-chip" data-quote-filter="en_proceso">En proceso</button>
                                    <button type="button" class="sat-clean-filter-chip" data-quote-filter="cotizada">Cotizada</button>
                                    <button type="button" class="sat-clean-filter-chip" data-quote-filter="pagada">Pagada</button>
                                    <button type="button" class="sat-clean-filter-chip" data-quote-filter="completada">Completada</button>
                                    <button type="button" class="sat-clean-filter-chip" data-quote-filter="cancelada">Cancelada</button>
                                </div>
                            </div>

                            <div class="sat-clean-quote-toolbar__right">
                                <div class="sat-clean-quote-search">
                                    <input
                                        type="text"
                                        id="satQuoteSearchInput"
                                        placeholder="Buscar por folio, RFC, razón social o estatus..."
                                        autocomplete="off"
                                    >
                                </div>

                                <button
                                    type="button"
                                    id="satNewQuoteButton"
                                    class="sat-clean-btn sat-clean-btn--primary sat-clean-btn--compact"
                                    title="Nueva cotización"
                                    aria-label="Nueva cotización"
                                >
                                    + Cotización
                                </button>
                            </div>
                        </div>

                        <div
                            class="sat-clean-rfc-table-wrap sat-clean-quote-table-wrap sat-clean-rfc-table-wrap--minimal"
                            id="satQuotesTableWrap"
                        >
                            <table
                                class="sat-clean-rfc-table sat-clean-rfc-table--minimal"
                                id="satQuotesTable"
                            >
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>RFC / Razón social</th>
                                        <th>Concepto</th>
                                        <th>Estatus</th>
                                        <th>Importe estimado</th>
                                        <th>Avance</th>
                                        <th>Actualizado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="satQuotesTableBody">
                                    @forelse($cotizacionesCollection as $cotizacion)
                                        @php
                                            $quote = is_array($cotizacion) ? (object) $cotizacion : $cotizacion;

                                            $quoteId = (string) ($quote->id ?? '');
                                            $quoteMeta = $quote->meta ?? [];

                                            if (is_string($quoteMeta)) {
                                                $decodedQuoteMeta = json_decode($quoteMeta, true);
                                                $quoteMeta = is_array($decodedQuoteMeta) ? $decodedQuoteMeta : [];
                                            }

                                            if (!is_array($quoteMeta)) {
                                                $quoteMeta = [];
                                            }

                                            $quoteFolio = trim((string) ($quote->folio ?? $quote->codigo ?? $quote->quote_no ?? $quoteMeta['folio'] ?? ''));
                                            $quoteRfc = strtoupper(trim((string) ($quote->rfc ?? $quote->customer_rfc ?? $quoteMeta['rfc'] ?? '')));
                                            $quoteRazonSocial = trim((string) ($quote->razon_social ?? $quote->cliente_nombre ?? $quote->customer_name ?? $quoteMeta['razon_social'] ?? ''));
                                            $quoteConcepto = trim((string) ($quote->concepto ?? $quote->descripcion ?? $quote->tipo_descarga ?? $quoteMeta['concepto'] ?? 'Cotización SAT'));
                                            $quoteStatusRaw = trim((string) ($quote->status ?? $quote->estatus ?? $quote->estado ?? $quoteMeta['status'] ?? 'borrador'));
                                            $quoteStatus = strtolower(str_replace([' ', '-'], '_', $quoteStatusRaw));

                                            $quoteAmountRaw = $quote->importe_estimado
                                                ?? $quote->monto_estimado
                                                ?? $quote->total_estimado
                                                ?? $quote->amount
                                                ?? $quoteMeta['importe_estimado']
                                                ?? null;

                                            $quoteAmount = is_numeric($quoteAmountRaw) ? (float) $quoteAmountRaw : null;

                                            $quoteProgressRaw = $quote->progress
                                                ?? $quote->porcentaje
                                                ?? $quote->avance
                                                ?? $quoteMeta['progress']
                                                ?? $quoteMeta['porcentaje']
                                                ?? 0;

                                            $quoteProgress = max(0, min(100, (int) $quoteProgressRaw));

                                            $quoteUpdatedAt = $quote->updated_at
                                                ?? $quote->fecha_actualizacion
                                                ?? $quote->created_at
                                                ?? null;

                                            $quoteUpdatedLabel = 'Sin fecha';
                                            if (!empty($quoteUpdatedAt)) {
                                                try {
                                                    $quoteUpdatedLabel = \Illuminate\Support\Carbon::parse($quoteUpdatedAt)->format('d/m/Y H:i');
                                                } catch (\Throwable $e) {
                                                    $quoteUpdatedLabel = (string) $quoteUpdatedAt;
                                                }
                                            }

                                            $statusLabelMap = [
                                                'borrador'       => 'Borrador',
                                                'simulada'       => 'Simulada',
                                                'en_proceso'     => 'En proceso',
                                                'cotizada'       => 'Cotizada',
                                                'pendiente_pago' => 'Pendiente de pago',
                                                'pagada'         => 'Pagada',
                                                'en_descarga'    => 'En proceso de descarga',
                                                'completada'     => 'Completada',
                                                'cancelada'      => 'Cancelada',
                                            ];

                                            $quoteStatusLabel = $statusLabelMap[$quoteStatus] ?? ucwords(str_replace('_', ' ', $quoteStatus));

                                            $statusBadgeClass = 'is-muted';
                                            if (in_array($quoteStatus, ['completada'], true)) {
                                                $statusBadgeClass = 'is-success';
                                            } elseif (in_array($quoteStatus, ['pagada', 'cotizada', 'en_proceso', 'pendiente_pago', 'en_descarga'], true)) {
                                                $statusBadgeClass = 'is-warning';
                                            } elseif (in_array($quoteStatus, ['cancelada'], true)) {
                                                $statusBadgeClass = 'is-muted';
                                            }

                                            $searchIndex = implode(' ', [
                                                $quoteFolio,
                                                $quoteRfc,
                                                $quoteRazonSocial,
                                                $quoteConcepto,
                                                $quoteStatusLabel,
                                            ]);

                                            $quoteDateFrom = (string) ($quote->date_from ?? $quoteMeta['date_from'] ?? '');
                                            $quoteDateTo = (string) ($quote->date_to ?? $quoteMeta['date_to'] ?? '');
                                            $quoteTipo = (string) ($quote->tipo ?? $quoteMeta['tipo'] ?? $quoteMeta['tipo_solicitud'] ?? '');

                                            /* 🔥 NUEVO - SIEMPRE DEFINIDOS */
                                            $quoteXmlCount = (string) (
                                                $quote->xml_count
                                                ?? $quote->cfdi_count
                                                ?? $quoteMeta['xml_count']
                                                ?? data_get($quoteMeta, 'quote.xml_count')
                                                ?? ''
                                            );

                                            $quoteDiscountCode = (string) (
                                                $quoteMeta['discount_code_applied']
                                                ?? $quoteMeta['discount_code']
                                                ?? data_get($quoteMeta, 'quote.discount_code_applied')
                                                ?? data_get($quoteMeta, 'quote.discount_code')
                                                ?? ''
                                            );

                                            $quoteIvaRate = (string) (
                                                $quoteMeta['iva_rate']
                                                ?? data_get($quoteMeta, 'quote.iva_rate')
                                                ?? '16'
                                            );

                                            $quoteNotes = (string) (
                                                $quoteMeta['notes']
                                                ?? data_get($quoteMeta, 'quote.notes')
                                                ?? ''
                                            );

                                            $quoteIsEditable = in_array($quoteStatus, ['borrador', 'en_proceso'], true);
                                        @endphp

                                        <tr
                                            id="satQuoteRow-{{ $quoteId !== '' ? e($quoteId) : 'row-'.$loop->iteration }}"
                                            data-quote-row="true"
                                            data-quote-id="{{ $quoteId }}"
                                            data-status="{{ $quoteStatus }}"
                                            data-search="{{ e($searchIndex) }}"
                                            data-folio="{{ e($quoteFolio) }}"
                                            data-rfc="{{ e($quoteRfc) }}"
                                            data-razon-social="{{ e($quoteRazonSocial) }}"
                                            data-concepto="{{ e($quoteConcepto) }}"
                                            data-progress="{{ $quoteProgress }}"
                                            data-total="{{ $quoteAmount !== null ? e(number_format($quoteAmount, 2, '.', '')) : '' }}"
                                            data-date-from="{{ e($quoteDateFrom) }}"
                                            data-date-to="{{ e($quoteDateTo) }}"
                                            data-tipo="{{ e($quoteTipo) }}"
                                            data-xml-count="{{ e($quoteXmlCount ?? '') }}"
                                            data-discount-code="{{ e($quoteDiscountCode) }}"
                                            data-iva-rate="{{ e($quoteIvaRate) }}"
                                            data-notes="{{ e($quoteNotes) }}"
                                            data-editable="{{ $quoteIsEditable ? '1' : '0' }}"
                                        >
                                            <td>
                                                <div class="sat-clean-quote-summary">
                                                    <span class="sat-clean-quote-folio" data-quote-field="folio">
                                                        {{ $quoteFolio !== '' ? $quoteFolio : ('COT-' . str_pad((string) ($loop->iteration), 4, '0', STR_PAD_LEFT)) }}
                                                    </span>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="sat-clean-rfc-inline-main">
                                                    <span class="sat-clean-rfc-inline-main__rfc" data-quote-field="rfc">
                                                        {{ $quoteRfc !== '' ? $quoteRfc : 'RFC pendiente' }}
                                                    </span>
                                                </div>
                                                <div class="sat-clean-rfc-inline-text" data-quote-field="razon_social">
                                                    {{ $quoteRazonSocial !== '' ? $quoteRazonSocial : 'Razón social pendiente' }}
                                                </div>
                                            </td>

                                            <td>
                                                <div class="sat-clean-rfc-inline-text" data-quote-field="concepto">
                                                    {{ $quoteConcepto }}
                                                </div>
                                            </td>

                                            <td>
                                                <span
                                                    class="sat-clean-status-badge {{ $statusBadgeClass }}"
                                                    data-quote-field="status_badge"
                                                >
                                                    {{ $quoteStatusLabel }}
                                                </span>
                                            </td>

                                            <td>
                                                <span class="sat-clean-quote-amount" data-quote-field="importe_estimado">
                                                    {{ $quoteAmount !== null ? ('$' . number_format($quoteAmount, 2)) : 'Pendiente' }}
                                                </span>
                                            </td>

                                            <td>
                                                <div class="sat-clean-quote-progress">
                                                    <div class="sat-clean-quote-progress__bar">
                                                        <span
                                                            class="sat-clean-quote-progress__fill"
                                                            data-quote-field="progress_fill"
                                                            style="width: {{ $quoteProgress }}%;"
                                                        ></span>
                                                    </div>
                                                    <span class="sat-clean-quote-progress__text" data-quote-field="progress_text">{{ $quoteProgress }}%</span>
                                                </div>
                                            </td>

                                            <td>
                                                <span class="sat-clean-quote-meta" data-quote-field="updated_at">{{ $quoteUpdatedLabel }}</span>
                                            </td>

                                            <td class="text-end">
                                                                                                <div class="sat-clean-icon-actions">
                                                    <button
                                                        type="button"
                                                        class="sat-clean-icon-btn"
                                                        data-quote-action="view"
                                                        data-quote-id="{{ $quoteId }}"
                                                        title="Ver detalle"
                                                        aria-label="Ver detalle"
                                                    >
                                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M2.25 12C3.9 8.25 7.38 5.75 12 5.75C16.62 5.75 20.1 8.25 21.75 12C20.1 15.75 16.62 18.25 12 18.25C7.38 18.25 3.9 15.75 2.25 12Z" stroke="currentColor" stroke-width="1.8"/>
                                                            <circle cx="12" cy="12" r="3.25" stroke="currentColor" stroke-width="1.8"/>
                                                        </svg>
                                                    </button>

                                                    @if($quoteIsEditable)
                                                        <button
                                                            type="button"
                                                            class="sat-clean-icon-btn"
                                                            data-quote-action="edit"
                                                            data-quote-id="{{ $quoteId }}"
                                                            title="Editar cotización"
                                                            aria-label="Editar cotización"
                                                        >
                                                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5V6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                                <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                            </svg>
                                                        </button>
                                                    @else
                                                        <span class="sat-clean-status-badge is-muted">
                                                            Sin edición
                                                        </span>
                                                    @endif

                                                    @if(in_array($quoteStatus, ['cotizada', 'pendiente_pago'], true))
                                                        <button
                                                            type="button"
                                                            class="sat-clean-btn sat-clean-btn--primary sat-clean-btn--compact"
                                                            data-quote-action="pay"
                                                            data-quote-id="{{ $quoteId }}"
                                                            title="Pagar cotización"
                                                            aria-label="Pagar cotización"
                                                        >
                                                            Pagar
                                                        </button>
                                                    @elseif($quoteStatus === 'pagada')
                                                        <span class="sat-clean-status-badge is-warning">En descarga</span>
                                                    @elseif($quoteStatus === 'completada')
                                                        <a
                                                            href="{{ route('cliente.sat.v2.index') }}"
                                                            class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact"
                                                            title="Abrir bóveda v2"
                                                            aria-label="Abrir bóveda v2"
                                                        >
                                                            Ver entrega
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr id="satQuotesEmptyRow">
                                            <td colspan="8">
                                                <div class="sat-clean-empty-state sat-clean-empty-state--compact" id="satQuotesEmptyState">
                                                    <div class="sat-clean-empty-state__title">
                                                        Aún no hay cotizaciones registradas
                                                    </div>
                                                    <div class="sat-clean-empty-state__text">
                                                        Aquí se mostrarán las solicitudes de cotización, su estatus, importe estimado y avance de procesamiento.
                                                    </div>
                                                    <button
                                                        type="button"
                                                        id="satEmptyNewQuoteButton"
                                                        class="sat-clean-btn sat-clean-btn--primary sat-clean-btn--compact"
                                                        title="Nueva cotización"
                                                        aria-label="Nueva cotización"
                                                    >
                                                        + Cotización
                                                    </button>
                                                </div>

                                                <div class="sat-clean-quote-empty-note">
                                                    Crea una cotización para comenzar el seguimiento, edición de borradores/en proceso y pago cuando la cotización sea confirmada.
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </details>
        </section>

        <section class="sat-clean-accordion" aria-label="Centro SAT">
            <details class="sat-clean-accordion__item">
                <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
                    <div class="sat-clean-accordion__bar-left">
                        <span class="sat-clean-accordion__bar-title">Centro SAT</span>
                        <span class="sat-clean-accordion__bar-text">
                            Vista rápida de almacenamiento, archivos y accesos de bóveda
                        </span>
                    </div>

                    <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </span>
                </summary>

                <div class="sat-clean-accordion__content">
                    @php
                        $vaultUsedPct = max(0, min(100, (float) ($vaultSummary['used_pct'] ?? 0)));
                        $vaultAvailablePct = max(0, 100 - $vaultUsedPct);
                        $vaultUsedDeg = round(($vaultUsedPct / 100) * 360, 2);

                        $vaultFilesCount = (int) ($vaultSummary['files_count'] ?? 0);
                        $vaultUsedGb = (float) ($vaultSummary['used_gb'] ?? 0);
                        $vaultAvailableGb = (float) ($vaultSummary['available_gb'] ?? 0);
                        $vaultQuotaGb = (float) ($vaultSummary['quota_gb'] ?? 0);
                    @endphp

                    <div class="sat-vault-summary sat-vault-summary--pro">
                        <div class="sat-vault-pro-card">
                            <div class="sat-vault-pro-card__top">
                                <div class="sat-vault-pro-card__copy">
                                    <p class="sat-vault-pro-card__text">
                                        Visualiza el estado general de tus archivos, uso de espacio y accesos a bóveda desde un solo panel.
                                    </p>
                                </div>

                                <div class="sat-vault-pro-card__actions">
                                    @if(($vault['enabled'] ?? false))
                                        <a
                                            href="{{ route('cliente.sat.v2.index') }}"
                                            class="sat-vault-pro-iconbtn sat-vault-pro-iconbtn--primary"
                                            title="Abrir Bóveda v2"
                                            aria-label="Abrir Bóveda v2"
                                        >
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                <path d="M8.5 10.5 12 8l3.5 2.5v4L12 17l-3.5-2.5v-4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            </svg>
                                        </a>

                                        <a
                                            href="{{ route('cliente.sat.vault') }}"
                                            class="sat-vault-pro-pillbtn"
                                            title="Abrir bóveda v1"
                                            aria-label="Abrir bóveda v1"
                                        >
                                            BV1
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <div class="sat-vault-pro-grid">
                                <div class="sat-vault-pro-storage">
                                    <div class="sat-vault-pro-storage__chartWrap">
                                        <div
                                            class="sat-vault-pro-donut"
                                            style="--sat-vault-used-deg: {{ $vaultUsedDeg }}deg;"
                                            aria-label="Uso de almacenamiento"
                                        >
                                            <div class="sat-vault-pro-donut__inner sat-vault-pro-donut__inner--stacked">
                                                <strong>{{ number_format($vaultUsedPct, 1) }}%</strong>
                                                <span>utilizado</span>

                                                <div class="sat-vault-pro-donut__legend">
                                                    <div class="sat-vault-pro-donut__legendItem">
                                                        <span class="sat-vault-pro-storage__dot sat-vault-pro-storage__dot--used"></span>
                                                        <small>Usado · {{ number_format($vaultUsedGb, 2) }} GB</small>
                                                    </div>

                                                    <div class="sat-vault-pro-donut__legendItem">
                                                        <span class="sat-vault-pro-storage__dot sat-vault-pro-storage__dot--available"></span>
                                                        <small>Disponible · {{ number_format($vaultAvailableGb, 2) }} GB</small>
                                                    </div>

                                                    <div class="sat-vault-pro-donut__legendItem">
                                                        <span class="sat-vault-pro-storage__dot sat-vault-pro-storage__dot--total"></span>
                                                        <small>Total · {{ number_format($vaultQuotaGb, 2) }} GB</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="sat-vault-pro-stats">
                                    <div class="sat-vault-pro-stat sat-vault-pro-stat--rfc">
                                        <span class="sat-vault-pro-stat__label">RFC registrados</span>
                                        <strong class="sat-vault-pro-stat__value">{{ number_format(count($rfcs ?? [])) }}</strong>
                                        <small class="sat-vault-pro-stat__hint">Credenciales disponibles</small>
                                    </div>

                                    <div class="sat-vault-pro-stat sat-vault-pro-stat--quotes">
                                        <span class="sat-vault-pro-stat__label">Cotizaciones</span>
                                        <strong class="sat-vault-pro-stat__value">{{ number_format($totalCotizaciones ?? 0) }}</strong>
                                        <small class="sat-vault-pro-stat__hint">Solicitudes visibles</small>
                                    </div>

                                    <div class="sat-vault-pro-stat sat-vault-pro-stat--files">
                                        <span class="sat-vault-pro-stat__label">Archivos en bóveda</span>
                                        <strong class="sat-vault-pro-stat__value">{{ number_format($vaultFilesCount) }}</strong>
                                        <small class="sat-vault-pro-stat__hint">Archivos almacenados</small>
                                    </div>

                                    <div class="sat-vault-pro-stat sat-vault-pro-stat--capacity">
                                        <span class="sat-vault-pro-stat__label">Estado de capacidad</span>
                                        <strong class="sat-vault-pro-stat__value">{{ number_format($vaultAvailablePct, 1) }}%</strong>
                                        <small class="sat-vault-pro-stat__hint">Disponible para nuevos archivos</small>
                                    </div>
                                </div>
                            </div>

                            <div class="sat-vault-pro-bottom">
                                <div class="sat-vault-pro-bottom__rail">
                                    <div class="sat-vault-pro-bottom__item">
                                        <span>Usado</span>
                                        <strong>{{ number_format($vaultUsedGb, 2) }} GB</strong>
                                    </div>
                                    <div class="sat-vault-pro-bottom__item">
                                        <span>Disponible</span>
                                        <strong>{{ number_format($vaultAvailableGb, 2) }} GB</strong>
                                    </div>
                                    <div class="sat-vault-pro-bottom__item">
                                        <span>Total</span>
                                        <strong>{{ number_format($vaultQuotaGb, 2) }} GB</strong>
                                    </div>
                                    <div class="sat-vault-pro-bottom__item">
                                        <span>Archivos</span>
                                        <strong>{{ number_format($vaultFilesCount) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </details>
        </section>

               <section class="sat-clean-accordion" aria-label="Descargas SAT">
            <details class="sat-clean-accordion__item">
                <summary class="sat-clean-accordion__summary sat-clean-accordion__summary--bar">
                    <div class="sat-clean-accordion__bar-left">
                        <span class="sat-clean-accordion__bar-title">Descargas</span>
                        <span class="sat-clean-accordion__bar-text">
                            Listado de archivos visibles del RFC activo entre Centro SAT, Bóveda v1 y Bóveda v2
                        </span>
                    </div>

                    <span class="sat-clean-accordion__bar-action" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 5V19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            <path d="M5 12H19" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </span>
                </summary>

                <div class="sat-clean-accordion__content">
                    @php
                        $downloadItemsUnified = collect($unifiedDownloadItems ?? []);
                    @endphp

                    <div class="sat-clean-rfc-admin sat-clean-rfc-admin--compact">
                        <div class="sat-clean-rfc-toolbar-v2">
                            <div class="sat-clean-rfc-toolbar-v2__left">
                                <div class="sat-clean-rfc-toolbar-v2__title-wrap">
                                    <h2 class="sat-clean-rfc-toolbar-v2__title">Archivos visibles</h2>
                                    <span class="sat-clean-rfc-toolbar-v2__count">
                                        {{ number_format($downloadItemsUnified->count()) }} archivo(s)
                                    </span>
                                </div>

                                <div class="sat-clean-rfc-inline-text">
                                    RFC activo:
                                    <strong>{{ $selectedRfc !== '' ? $selectedRfc : 'Sin RFC' }}</strong>
                                </div>
                            </div>

                            <div class="sat-clean-rfc-toolbar-v2__right">
                                <a
                                    href="{{ route('cliente.sat.v2.index', $selectedRfc ? ['rfc' => $selectedRfc] : []) }}"
                                    class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact"
                                >
                                    Abrir SAT Bóveda v2
                                </a>
                            </div>
                        </div>

                        <div class="sat-clean-rfc-table-wrap sat-clean-rfc-table-wrap--minimal">
                            <table class="sat-clean-rfc-table sat-clean-rfc-table--minimal">
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
                                        <th class="text-end">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($downloadItemsUnified as $item)
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

                                            $createdAtLabel = '—';
                                            if (!empty($item['created_at'])) {
                                                try {
                                                    $createdAtLabel = \Illuminate\Support\Carbon::parse($item['created_at'])->format('d/m/Y H:i');
                                                } catch (\Throwable $e) {
                                                    $createdAtLabel = (string) $item['created_at'];
                                                }
                                            }
                                        @endphp

                                        <tr>
                                            <td>
                                                <span class="sat-clean-status-badge {{ $originRaw === 'centro_sat' ? 'is-warning' : ($originRaw === 'boveda_v2' ? 'is-success' : 'is-muted') }}">
                                                    {{ $originLabel }}
                                                </span>
                                            </td>

                                            <td>{{ $kindLabel }}</td>

                                            <td>
                                                <div class="sat-clean-rfc-inline-main">
                                                    <span class="sat-clean-rfc-inline-main__rfc">
                                                        {{ $item['original_name'] ?? 'Archivo' }}
                                                    </span>
                                                </div>
                                                <div class="sat-clean-rfc-inline-text">
                                                    {{ $statusLabel }}
                                                </div>
                                            </td>

                                            <td>{{ $item['rfc_owner'] ?? '—' }}</td>
                                            <td>{{ $directionLabel }}</td>
                                            <td>{{ $item['bytes_human'] ?? '0 B' }}</td>
                                            <td>{{ $item['detail'] ?? 'Archivo' }}</td>
                                            <td>{{ $createdAtLabel }}</td>

                                            <td class="text-end">
                                                <div class="sat-clean-icon-actions">
                                                    @if($viewUrl !== '')
                                                        <a
                                                            href="{{ $viewUrl }}"
                                                            target="_blank"
                                                            class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact"
                                                        >
                                                            Ver
                                                        </a>
                                                    @endif

                                                    @if($downloadUrl !== '')
                                                        <a
                                                            href="{{ $downloadUrl }}"
                                                            class="sat-clean-btn sat-clean-btn--primary sat-clean-btn--compact"
                                                        >
                                                            Descargar
                                                        </a>
                                                    @else
                                                        <a
                                                            href="{{ route('cliente.sat.v2.index', $selectedRfc ? ['rfc' => $selectedRfc] : []) }}"
                                                            class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact"
                                                        >
                                                            Ver módulo
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9">
                                                <div class="sat-clean-empty-state sat-clean-empty-state--compact">
                                                    <div class="sat-clean-empty-state__title">
                                                        Aún no hay archivos visibles para este RFC
                                                    </div>
                                                    <div class="sat-clean-empty-state__text">
                                                        El resumen visual se muestra solo en Centro SAT. Aquí se concentra únicamente el listado de archivos.
                                                    </div>
                                                    <a
                                                        href="{{ route('cliente.sat.v2.index', $selectedRfc ? ['rfc' => $selectedRfc] : []) }}"
                                                        class="sat-clean-btn sat-clean-btn--primary sat-clean-btn--compact"
                                                    >
                                                        Abrir SAT Bóveda v2
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </details>
        </section>

    </div>
</div>

{{-- MODAL: AGREGAR RFC --}}
<div class="sat-clean-modal" id="satRfcCreateModal" aria-hidden="true">
    <div class="sat-clean-modal__backdrop" data-rfc-close-modal></div>
    <div class="sat-clean-modal__dialog sat-clean-modal__dialog--xl" role="dialog" aria-modal="true" aria-labelledby="satRfcCreateTitle">
        <div class="sat-clean-modal__header">
            <div>
                <h2 class="sat-clean-modal__title" id="satRfcCreateTitle">Agregar RFC</h2>
                <p class="sat-clean-modal__subtitle">
                    Registra un RFC completo para operación SAT.
                </p>
            </div>

            <button type="button" class="sat-clean-modal__close" data-rfc-close-modal aria-label="Cerrar">
                ✕
            </button>
        </div>

        <div class="sat-clean-modal__body-scroll">
            <form method="POST" action="{{ route('cliente.sat.rfcs.store') }}" class="sat-clean-modal__form" enctype="multipart/form-data">
                @csrf

                <section class="sat-clean-form-section">
                    <div class="sat-clean-form-section__head">
                        <h3 class="sat-clean-form-section__title">Datos del RFC</h3>
                        <p class="sat-clean-form-section__text">Alta completa del registro.</p>
                    </div>

                    <div class="sat-clean-form-grid sat-clean-form-grid--3">
                        <div class="sat-clean-form-field">
                            <label for="sat_create_rfc">RFC</label>
                            <input
                                type="text"
                                id="sat_create_rfc"
                                name="rfc"
                                maxlength="13"
                                placeholder="Ej. XAXX010101000"
                                required
                            >
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="sat_create_tipo_origen">Tipo de origen</label>
                            <select id="sat_create_tipo_origen" name="tipo_origen" required>
                                <option value="interno">Interno</option>
                                <option value="externo">Externo</option>
                            </select>
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="sat_create_origen_detalle">Origen detalle</label>
                            <input
                                type="text"
                                id="sat_create_origen_detalle"
                                name="origen_detalle"
                                maxlength="60"
                                placeholder="Ej. cliente_interno / cliente_externo"
                            >
                        </div>

                        <div class="sat-clean-form-field sat-clean-form-field--span-2">
                            <label for="sat_create_razon_social">Razón social</label>
                            <input
                                type="text"
                                id="sat_create_razon_social"
                                name="razon_social"
                                maxlength="190"
                                placeholder="Razón social"
                            >
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="sat_create_source_label">Etiqueta origen</label>
                            <input
                                type="text"
                                id="sat_create_source_label"
                                name="source_label"
                                maxlength="120"
                                placeholder="Ej. Registro interno"
                            >
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="sat_create_contact_name">Nombre de contacto</label>
                            <input
                                type="text"
                                id="sat_create_contact_name"
                                name="contact_name"
                                maxlength="190"
                                placeholder="Nombre del responsable"
                            >
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="sat_create_contact_email">Correo de contacto</label>
                            <input
                                type="email"
                                id="sat_create_contact_email"
                                name="contact_email"
                                maxlength="190"
                                placeholder="correo@empresa.com"
                            >
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="sat_create_contact_phone">Teléfono</label>
                            <input
                                type="text"
                                id="sat_create_contact_phone"
                                name="contact_phone"
                                maxlength="40"
                                placeholder="Teléfono"
                            >
                        </div>

                        <div class="sat-clean-form-field sat-clean-form-field--full">
                            <label for="sat_create_notes">Notas internas</label>
                            <textarea
                                id="sat_create_notes"
                                name="notes"
                                rows="3"
                                placeholder="Notas internas del RFC"
                            ></textarea>
                        </div>
                    </div>
                </section>

                <section class="sat-clean-form-section">
                    <div class="sat-clean-form-section__head">
                        <h3 class="sat-clean-form-section__title">FIEL obligatoria</h3>
                    </div>

                    <div class="sat-clean-form-grid sat-clean-form-grid--3">
                        <div class="sat-clean-form-field">
                            <label for="sat_create_fiel_cer">FIEL certificado (.cer)</label>
                            <input type="file" id="sat_create_fiel_cer" name="fiel_cer" accept=".cer" required>
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="sat_create_fiel_key">FIEL llave (.key)</label>
                            <input type="file" id="sat_create_fiel_key" name="fiel_key" accept=".key" required>
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="sat_create_fiel_password">Contraseña FIEL</label>
                            <input
                                type="password"
                                id="sat_create_fiel_password"
                                name="fiel_password"
                                maxlength="190"
                                placeholder="Contraseña FIEL"
                                required
                            >
                        </div>
                    </div>
                </section>

                <section class="sat-clean-form-section">
                    <div class="sat-clean-form-section__head">
                        <h3 class="sat-clean-form-section__title">CSD opcional</h3>
                    </div>

                    <div class="sat-clean-form-grid sat-clean-form-grid--3">
                        <div class="sat-clean-form-field">
                            <label for="sat_create_csd_cer">CSD certificado (.cer)</label>
                            <input type="file" id="sat_create_csd_cer" name="csd_cer" accept=".cer">
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="sat_create_csd_key">CSD llave (.key)</label>
                            <input type="file" id="sat_create_csd_key" name="csd_key" accept=".key">
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="sat_create_csd_password">Contraseña CSD</label>
                            <input
                                type="password"
                                id="sat_create_csd_password"
                                name="csd_password"
                                maxlength="190"
                                placeholder="Contraseña CSD"
                            >
                        </div>
                    </div>
                </section>

                <section class="sat-clean-form-section sat-clean-form-section--soft">
                    <div class="sat-clean-form-section__head">
                        <h3 class="sat-clean-form-section__title">Opciones de invitación</h3>
                        <p class="sat-clean-form-section__text">Usa un emergente separado para enviar invitaciones externas.</p>
                    </div>

                    <div class="sat-clean-modal__actions sat-clean-modal__actions--compact sat-clean-modal__actions--linked">
                        <button
                            type="button"
                            class="sat-clean-btn sat-clean-btn--ghost"
                            data-open-linked-modal="invite-single"
                        >
                            Invitación individual
                        </button>

                        <button
                            type="button"
                            class="sat-clean-btn sat-clean-btn--ghost"
                            data-open-linked-modal="invite-zip"
                        >
                            Invitación ZIP
                        </button>
                    </div>
                </section>

                <div class="sat-clean-modal__actions">
                    <button type="button" class="sat-clean-btn sat-clean-btn--ghost" data-rfc-close-modal>
                        Cancelar
                    </button>

                    <button type="submit" class="sat-clean-btn sat-clean-btn--primary">
                        Guardar RFC
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: INVITACIÓN INDIVIDUAL --}}
<div class="sat-clean-modal" id="satInviteSingleModal" aria-hidden="true">
    <div class="sat-clean-modal__backdrop" data-rfc-close-modal></div>
    <div class="sat-clean-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="satInviteSingleTitle">
        <div class="sat-clean-modal__header">
            <div>
                <h2 class="sat-clean-modal__title" id="satInviteSingleTitle">Invitación individual</h2>
                <p class="sat-clean-modal__subtitle">Envía una liga segura al correo del emisor.</p>
            </div>

            <button type="button" class="sat-clean-modal__close" data-rfc-close-modal aria-label="Cerrar">
                ✕
            </button>
        </div>

        <form method="POST" action="{{ route('cliente.sat.external.invite') }}" class="sat-clean-modal__form">
            @csrf

            <div class="sat-clean-form-grid sat-clean-form-grid--2">
                <div class="sat-clean-form-field">
                    <label for="sat_invite_email">Correo del emisor</label>
                    <input
                        type="email"
                        id="sat_invite_email"
                        name="email"
                        maxlength="190"
                        placeholder="correo@empresa.com"
                        required
                    >
                </div>

                <div class="sat-clean-form-field">
                    <label for="sat_invite_note">Nota</label>
                    <input
                        type="text"
                        id="sat_invite_note"
                        name="note"
                        maxlength="500"
                        placeholder="Ej. Registro para CFDI / descarga SAT"
                    >
                </div>
            </div>

            <div class="sat-clean-modal__actions">
                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" data-back-to-create-modal>
                    Volver
                </button>

                <button type="submit" class="sat-clean-btn sat-clean-btn--primary">
                    Enviar invitación
                </button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL: INVITACIÓN ZIP --}}
<div class="sat-clean-modal" id="satInviteZipModal" aria-hidden="true">
    <div class="sat-clean-modal__backdrop" data-rfc-close-modal></div>
    <div class="sat-clean-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="satInviteZipTitle">
        <div class="sat-clean-modal__header">
            <div>
                <h2 class="sat-clean-modal__title" id="satInviteZipTitle">Invitación ZIP</h2>
                <p class="sat-clean-modal__subtitle">Prepara el flujo para carga externa por ZIP.</p>
            </div>

            <button type="button" class="sat-clean-modal__close" data-rfc-close-modal aria-label="Cerrar">
                ✕
            </button>
        </div>

        <form method="POST" action="{{ route('cliente.sat.external.invite') }}" class="sat-clean-modal__form">
            @csrf

            <div class="sat-clean-form-grid sat-clean-form-grid--2">
                <div class="sat-clean-form-field">
                    <label for="sat_zip_email">Correo para flujo ZIP</label>
                    <input
                        type="email"
                        id="sat_zip_email"
                        name="email"
                        maxlength="190"
                        placeholder="correo@empresa.com"
                        required
                    >
                </div>

                <div class="sat-clean-form-field">
                    <label for="sat_zip_note">Nota para flujo ZIP</label>
                    <input
                        type="text"
                        id="sat_zip_note"
                        name="note"
                        maxlength="500"
                        placeholder="Ej. Carga ZIP FIEL del emisor"
                    >
                </div>
            </div>

            <div class="sat-clean-inline-note">
                Usa esta opción cuando el emisor entregará sus archivos mediante flujo ZIP externo.
            </div>

            <div class="sat-clean-modal__actions">
                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" data-back-to-create-modal>
                    Volver
                </button>

                <button type="submit" class="sat-clean-btn sat-clean-btn--primary">
                    Enviar invitación
                </button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL: EDITAR RFC --}}
<div class="sat-clean-modal" id="satRfcEditModal" aria-hidden="true">
    <div class="sat-clean-modal__backdrop" data-rfc-close-modal></div>
    <div class="sat-clean-modal__dialog sat-clean-modal__dialog--xl" role="dialog" aria-modal="true" aria-labelledby="satRfcEditTitle">
        <div class="sat-clean-modal__header">
            <div>
                <h2 class="sat-clean-modal__title" id="satRfcEditTitle">Editar RFC</h2>
                <p class="sat-clean-modal__subtitle">Actualiza la información del RFC seleccionado.</p>
            </div>

            <button type="button" class="sat-clean-modal__close" data-rfc-close-modal aria-label="Cerrar">
                ✕
            </button>
        </div>

        <form
            method="POST"
            action=""
            class="sat-clean-modal__form"
            id="satRfcEditForm"
            data-action-template="{{ route('cliente.sat.rfcs.update', ['id' => '__ID__']) }}"
        >
            @csrf

            <input type="hidden" name="id" id="sat_edit_id" value="">

            <div class="sat-clean-form-grid sat-clean-form-grid--3">
                <div class="sat-clean-form-field">
                    <label for="sat_edit_rfc">RFC</label>
                    <input
                        type="text"
                        id="sat_edit_rfc"
                        name="rfc"
                        value=""
                        readonly
                    >
                </div>

                <div class="sat-clean-form-field">
                    <label for="sat_edit_tipo_origen">Tipo de origen</label>
                    <select id="sat_edit_tipo_origen" name="tipo_origen">
                        <option value="interno">Interno</option>
                        <option value="externo">Externo</option>
                    </select>
                </div>

                <div class="sat-clean-form-field sat-clean-form-field--span-2">
                    <label for="sat_edit_razon_social">Razón social</label>
                    <input
                        type="text"
                        id="sat_edit_razon_social"
                        name="razon_social"
                        maxlength="190"
                        placeholder="Razón social"
                    >
                </div>

                <div class="sat-clean-form-field">
                    <label for="sat_edit_contact_name">Nombre de contacto</label>
                    <input
                        type="text"
                        id="sat_edit_contact_name"
                        name="contact_name"
                        maxlength="190"
                        placeholder="Nombre del responsable"
                    >
                </div>

                <div class="sat-clean-form-field">
                    <label for="sat_edit_contact_email">Correo de contacto</label>
                    <input
                        type="email"
                        id="sat_edit_contact_email"
                        name="contact_email"
                        maxlength="190"
                        placeholder="correo@empresa.com"
                    >
                </div>

                <div class="sat-clean-form-field">
                    <label for="sat_edit_contact_phone">Teléfono</label>
                    <input
                        type="text"
                        id="sat_edit_contact_phone"
                        name="contact_phone"
                        maxlength="40"
                        placeholder="Teléfono"
                    >
                </div>

                <div class="sat-clean-form-field">
                    <label for="sat_edit_source_label">Etiqueta origen</label>
                    <input
                        type="text"
                        id="sat_edit_source_label"
                        name="source_label"
                        maxlength="120"
                        placeholder="Etiqueta origen"
                    >
                </div>

                <div class="sat-clean-form-field sat-clean-form-field--full">
                    <label for="sat_edit_notes">Notas internas</label>
                    <textarea
                        id="sat_edit_notes"
                        name="notes"
                        rows="3"
                        placeholder="Notas internas del RFC"
                    ></textarea>
                </div>
            </div>

            <div class="sat-clean-modal__actions">
                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" data-rfc-close-modal>
                    Cancelar
                </button>

                <button type="submit" class="sat-clean-btn sat-clean-btn--primary">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL: DETALLE RFC --}}
<div class="sat-clean-modal" id="satRfcDetailModal" aria-hidden="true">
    <div class="sat-clean-modal__backdrop" data-rfc-detail-close></div>
    <div
        class="sat-clean-modal__dialog sat-clean-modal__dialog--xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="satRfcDetailTitle"
    >
        <div class="sat-clean-modal__header">
            <div>
                <h2 class="sat-clean-modal__title" id="satRfcDetailTitle">Detalle RFC</h2>
                <p class="sat-clean-modal__subtitle">
                    Revisión completa del RFC, archivos registrados y accesos configurados.
                </p>
            </div>

            <button
                type="button"
                class="sat-clean-modal__close"
                data-rfc-detail-close
                aria-label="Cerrar"
            >
                ✕
            </button>
        </div>

        <div class="sat-clean-modal__body-scroll">
            <div class="sat-clean-form-section">
                <div class="sat-clean-form-section__head">
                    <h3 class="sat-clean-form-section__title">Información general</h3>
                </div>

                <div class="sat-clean-form-grid sat-clean-form-grid--3">
                    <div class="sat-clean-form-field">
                        <label>RFC</label>
                        <input type="text" id="satRfcDetailRfc" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Tipo de origen</label>
                        <input type="text" id="satRfcDetailTipoOrigen" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Estado</label>
                        <input type="text" id="satRfcDetailEstado" readonly>
                    </div>

                    <div class="sat-clean-form-field sat-clean-form-field--span-2">
                        <label>Razón social</label>
                        <input type="text" id="satRfcDetailRazonSocial" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Etiqueta origen</label>
                        <input type="text" id="satRfcDetailSourceLabel" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Nombre de contacto</label>
                        <input type="text" id="satRfcDetailContactName" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Correo de contacto</label>
                        <input type="text" id="satRfcDetailContactEmail" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Teléfono</label>
                        <input type="text" id="satRfcDetailContactPhone" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>FIEL</label>
                        <input type="text" id="satRfcDetailFielStatus" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>CSD</label>
                        <input type="text" id="satRfcDetailCsdStatus" readonly>
                    </div>

                    <div class="sat-clean-form-field sat-clean-form-field--full">
                        <label>Notas</label>
                        <textarea id="satRfcDetailNotes" rows="3" readonly></textarea>
                    </div>
                </div>
            </div>

            <div class="sat-clean-form-section">
                <div class="sat-clean-form-section__head">
                    <h3 class="sat-clean-form-section__title">Archivos registrados</h3>
                </div>

                <div class="sat-clean-form-grid sat-clean-form-grid--2">
                    <div class="sat-clean-form-field">
                        <label>FIEL .cer</label>
                        <input type="text" id="satRfcDetailFielCer" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>FIEL .key</label>
                        <input type="text" id="satRfcDetailFielKey" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>CSD .cer</label>
                        <input type="text" id="satRfcDetailCsdCer" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>CSD .key</label>
                        <input type="text" id="satRfcDetailCsdKey" readonly>
                    </div>
                </div>

                <div class="sat-clean-modal__actions sat-clean-modal__actions--compact" style="justify-content:flex-start;">
                    <a href="#" id="satRfcDetailFielCerDownload" class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact" target="_blank" style="display:none;">
                        Descargar FIEL .cer
                    </a>
                    <a href="#" id="satRfcDetailFielKeyDownload" class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact" target="_blank" style="display:none;">
                        Descargar FIEL .key
                    </a>
                    <a href="#" id="satRfcDetailCsdCerDownload" class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact" target="_blank" style="display:none;">
                        Descargar CSD .cer
                    </a>
                    <a href="#" id="satRfcDetailCsdKeyDownload" class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact" target="_blank" style="display:none;">
                        Descargar CSD .key
                    </a>
                </div>
            </div>

            <div class="sat-clean-modal__actions">
                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" data-rfc-detail-close>
                    Cerrar
                </button>

                <button type="button" class="sat-clean-btn sat-clean-btn--primary" id="satRfcDetailEditBtn">
                    Editar RFC
                </button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: DETALLE COTIZACIÓN --}}
<div class="sat-clean-modal" id="satQuoteDetailModal" aria-hidden="true">
    <div class="sat-clean-modal__backdrop" data-quote-detail-close></div>
    <div
        class="sat-clean-modal__dialog sat-clean-modal__dialog--xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="satQuoteDetailTitle"
    >
        <div class="sat-clean-modal__header">
            <div>
                <h2 class="sat-clean-modal__title" id="satQuoteDetailTitle">Detalle de cotización</h2>
                <p class="sat-clean-modal__subtitle">
                    Revisión completa de la cotización seleccionada.
                </p>
            </div>

            <button
                type="button"
                class="sat-clean-modal__close"
                data-quote-detail-close
                aria-label="Cerrar"
            >
                ✕
            </button>
        </div>

        <div class="sat-clean-modal__body-scroll">
            <div class="sat-clean-form-section">
                <div class="sat-clean-form-section__head">
                    <h3 class="sat-clean-form-section__title">Información general</h3>
                </div>

                <div class="sat-clean-form-grid sat-clean-form-grid--3">
                    <div class="sat-clean-form-field">
                        <label>Folio</label>
                        <input type="text" id="satQuoteDetailFolio" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>RFC</label>
                        <input type="text" id="satQuoteDetailRfc" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Estatus</label>
                        <input type="text" id="satQuoteDetailStatus" readonly>
                    </div>

                    <div class="sat-clean-form-field sat-clean-form-field--span-2">
                        <label>Razón social</label>
                        <input type="text" id="satQuoteDetailRazonSocial" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Tipo</label>
                        <input type="text" id="satQuoteDetailTipo" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Fecha inicial</label>
                        <input type="text" id="satQuoteDetailDateFrom" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Fecha final</label>
                        <input type="text" id="satQuoteDetailDateTo" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Avance</label>
                        <input type="text" id="satQuoteDetailProgress" readonly>
                    </div>

                    <div class="sat-clean-form-field">
                        <label>Importe estimado</label>
                        <input type="text" id="satQuoteDetailTotal" readonly>
                    </div>

                    <div class="sat-clean-form-field sat-clean-form-field--full">
                        <label>Concepto</label>
                        <textarea id="satQuoteDetailConcepto" rows="3" readonly></textarea>
                    </div>
                </div>
            </div>

            <div class="sat-clean-modal__actions">
                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" data-quote-detail-close>
                    Cerrar
                </button>

                <button type="button" class="sat-clean-btn sat-clean-btn--primary" id="satQuoteDetailEditBtn">
                    Editar cotización
                </button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: EDICIÓN RÁPIDA COTIZACIÓN --}}
<div class="sat-clean-modal" id="satQuoteEditModal" aria-hidden="true">
    <div class="sat-clean-modal__backdrop" data-quote-edit-close></div>
    <div
        class="sat-clean-modal__dialog sat-clean-modal__dialog--xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="satQuoteEditTitle"
    >
        <div class="sat-clean-modal__header">
            <div>
                <h2 class="sat-clean-modal__title" id="satQuoteEditTitle">Editar cotización</h2>
                <p class="sat-clean-modal__subtitle">
                    Actualiza los datos del borrador o de la cotización seleccionada.
                </p>
            </div>

            <button
                type="button"
                class="sat-clean-modal__close"
                data-quote-edit-close
                aria-label="Cerrar"
            >
                ✕
            </button>
        </div>

        <div class="sat-clean-modal__body-scroll">
            <form id="satQuoteEditForm" class="sat-clean-modal__form" autocomplete="off">
                <input type="hidden" id="satQuoteEditId" value="">
                <input type="hidden" id="satQuoteEditDraftId" value="">

                <section class="sat-clean-form-section">
                    <div class="sat-clean-form-section__head">
                        <h3 class="sat-clean-form-section__title">Datos editables</h3>
                    </div>

                    <div class="sat-clean-form-grid sat-clean-form-grid--3">
                        <div class="sat-clean-form-field">
                            <label for="satQuoteEditFolio">Folio</label>
                            <input type="text" id="satQuoteEditFolio" readonly>
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="satQuoteEditRfc">RFC</label>
                            <input type="text" id="satQuoteEditRfc" readonly>
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="satQuoteEditTipo">Tipo</label>
                            <select id="satQuoteEditTipo">
                                <option value="emitidos">Emitidos</option>
                                <option value="recibidos">Recibidos</option>
                                <option value="ambos">Ambos</option>
                            </select>
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="satQuoteEditDateFrom">Fecha inicial</label>
                            <input type="date" id="satQuoteEditDateFrom">
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="satQuoteEditDateTo">Fecha final</label>
                            <input type="date" id="satQuoteEditDateTo">
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="satQuoteEditTotal">Importe estimado</label>
                            <input type="text" id="satQuoteEditTotal" readonly>
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="satQuoteEditProgress">Avance</label>
                            <input type="text" id="satQuoteEditProgress" readonly>
                        </div>

                        <div class="sat-clean-form-field">
                            <label for="satQuoteEditStatus">Estatus</label>
                            <input type="text" id="satQuoteEditStatus" readonly>
                        </div>

                        <div class="sat-clean-form-field sat-clean-form-field--full">
                            <label for="satQuoteEditConcepto">Concepto</label>
                            <textarea id="satQuoteEditConcepto" rows="3"></textarea>
                        </div>
                    </div>
                </section>

                <div class="sat-clean-modal__actions">
                    <button type="button" class="sat-clean-btn sat-clean-btn--ghost" data-quote-edit-close>
                        Cancelar
                    </button>

                    <button type="button" class="sat-clean-btn sat-clean-btn--primary" id="satQuoteEditLoadMainModalBtn">
                        Cargar al cotizador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const detailModal = document.getElementById('satRfcDetailModal');
    if (!detailModal) return;

    const closeButtons = detailModal.querySelectorAll('[data-rfc-detail-close]');
    const editBtn = document.getElementById('satRfcDetailEditBtn');

    const fields = {
        rfc: document.getElementById('satRfcDetailRfc'),
        razon: document.getElementById('satRfcDetailRazonSocial'),
        tipo: document.getElementById('satRfcDetailTipoOrigen'),
        estado: document.getElementById('satRfcDetailEstado'),
        source: document.getElementById('satRfcDetailSourceLabel'),
        contactName: document.getElementById('satRfcDetailContactName'),
        contactEmail: document.getElementById('satRfcDetailContactEmail'),
        contactPhone: document.getElementById('satRfcDetailContactPhone'),
        fiel: document.getElementById('satRfcDetailFielStatus'),
        csd: document.getElementById('satRfcDetailCsdStatus'),
        notes: document.getElementById('satRfcDetailNotes'),
        fielCer: document.getElementById('satRfcDetailFielCer'),
        fielKey: document.getElementById('satRfcDetailFielKey'),
        csdCer: document.getElementById('satRfcDetailCsdCer'),
        csdKey: document.getElementById('satRfcDetailCsdKey'),
    };

    const links = {
        fielCer: document.getElementById('satRfcDetailFielCerDownload'),
        fielKey: document.getElementById('satRfcDetailFielKeyDownload'),
        csdCer: document.getElementById('satRfcDetailCsdCerDownload'),
        csdKey: document.getElementById('satRfcDetailCsdKeyDownload'),
    };

    const openModal = () => {
        detailModal.classList.add('is-visible');
        detailModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sat-clean-modal-open');
    };

    const closeModal = () => {
        detailModal.classList.remove('is-visible');
        detailModal.setAttribute('aria-hidden', 'true');

        const hasVisibleModal = document.querySelector('.sat-clean-modal.is-visible');
        const passwordDialogVisible = document.getElementById('satPasswordDialog')?.classList.contains('is-visible');

        if (!hasVisibleModal && !passwordDialogVisible) {
            document.body.classList.remove('sat-clean-modal-open');
        }
    };

    const syncLink = (el, url) => {
        if (!el) return;
        if (url && url !== '') {
            el.href = url;
            el.style.display = '';
        } else {
            el.href = '#';
            el.style.display = 'none';
        }
    };

    closeButtons.forEach(btn => {
        btn.addEventListener('click', closeModal);
    });

    document.querySelectorAll('[data-rfc-open-detail="true"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const ds = this.dataset;

            fields.rfc.value = ds.rfcValue || '';
            fields.razon.value = ds.rfcRazonSocial || '';
            fields.tipo.value = ds.rfcTipoOrigen || '';
            fields.estado.value = ds.rfcIsActive === '1' ? 'Activo' : 'Inactivo';
            fields.source.value = ds.rfcSourceLabel || '';
            fields.contactName.value = ds.rfcContactName || '';
            fields.contactEmail.value = ds.rfcContactEmail || '';
            fields.contactPhone.value = ds.rfcContactPhone || '';
            fields.fiel.value = ds.rfcHasFiel === '1' ? 'Configurada' : 'Pendiente';
            fields.csd.value = ds.rfcHasCsd === '1' ? 'Configurada' : 'Pendiente';
            fields.notes.value = ds.rfcNotes || '';
            fields.fielCer.value = ds.rfcFielCer || 'No registrado';
            fields.fielKey.value = ds.rfcFielKey || 'No registrado';
            fields.csdCer.value = ds.rfcCsdCer || 'No registrado';
            fields.csdKey.value = ds.rfcCsdKey || 'No registrado';

            syncLink(links.fielCer, ds.rfcFielCerDownloadUrl || '');
            syncLink(links.fielKey, ds.rfcFielKeyDownloadUrl || '');
            syncLink(links.csdCer, ds.rfcCsdCerDownloadUrl || '');
            syncLink(links.csdKey, ds.rfcCsdKeyDownloadUrl || '');

            if (editBtn) {
                editBtn.setAttribute('data-rfc-id', ds.rfcId || '');
                editBtn.setAttribute('data-rfc-value', ds.rfcValue || '');
                editBtn.setAttribute('data-rfc-razon-social', ds.rfcRazonSocial || '');
                editBtn.setAttribute('data-rfc-tipo-origen', ds.rfcTipoOrigen || '');
                editBtn.setAttribute('data-rfc-contact-email', ds.rfcContactEmail || '');
                editBtn.setAttribute('data-rfc-contact-phone', ds.rfcContactPhone || '');
                editBtn.setAttribute('data-rfc-contact-name', ds.rfcContactName || '');
                editBtn.setAttribute('data-rfc-source-label', ds.rfcSourceLabel || '');
                editBtn.setAttribute('data-rfc-notes', ds.rfcNotes || '');
            }

            openModal();
        });
    });

    if (editBtn) {
        editBtn.addEventListener('click', function () {
            closeModal();

            const editModal = document.getElementById('satRfcEditModal');
            const editForm = document.getElementById('satRfcEditForm');
            const actionTemplate = editForm ? editForm.dataset.actionTemplate || '' : '';

            const id = this.getAttribute('data-rfc-id') || '';
            const rfc = this.getAttribute('data-rfc-value') || '';
            const razon = this.getAttribute('data-rfc-razon-social') || '';
            const tipo = this.getAttribute('data-rfc-tipo-origen') || 'interno';
            const email = this.getAttribute('data-rfc-contact-email') || '';
            const phone = this.getAttribute('data-rfc-contact-phone') || '';
            const name = this.getAttribute('data-rfc-contact-name') || '';
            const source = this.getAttribute('data-rfc-source-label') || '';
            const notes = this.getAttribute('data-rfc-notes') || '';

            document.getElementById('sat_edit_id').value = id;
            document.getElementById('sat_edit_rfc').value = rfc;
            document.getElementById('sat_edit_razon_social').value = razon;
            document.getElementById('sat_edit_tipo_origen').value = tipo;
            document.getElementById('sat_edit_contact_email').value = email;
            document.getElementById('sat_edit_contact_phone').value = phone;
            document.getElementById('sat_edit_contact_name').value = name;
            document.getElementById('sat_edit_source_label').value = source;
            document.getElementById('sat_edit_notes').value = notes;

            if (editForm && actionTemplate !== '') {
                editForm.action = actionTemplate.replace('__ID__', id);
            }

            if (editModal) {
                editModal.classList.add('is-visible');
                editModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('sat-clean-modal-open');
            }
        });
    }
});
</script>
@endsection