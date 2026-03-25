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
                        <div class="sv2Pill">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 7.5C4 5.567 5.567 4 7.5 4h9C18.433 4 20 5.567 20 7.5v9c0 1.933-1.567 3.5-3.5 3.5h-9A3.5 3.5 0 0 1 4 16.5v-9Z" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M8 10h8M8 14h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                            SAT · Bóveda Fiscal · v2
                        </div>

                        <div class="sv2Hero__copy">
                            <h1 class="sv2Hero__title">Bóveda Fiscal</h1>

                            <p class="sv2Hero__subtitle">
                                Resguardo fiscal por RFC con metadata, XML y conciliación.
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
                        </div>
                    </div>
                </div>

                <div class="sv2HeroCard">
                    <div class="sv2HeroCard__top">
                        <div>
                            <div class="sv2HeroCard__eyebrow">Panel de acceso</div>
                            <h2 class="sv2HeroCard__title">RFC de trabajo</h2>
                            <p class="sv2HeroCard__desc">Selecciona el RFC base.</p>
                        </div>
                        <span class="sv2Count">{{ $rfcs->count() }} RFC</span>
                    </div>

                    <form method="GET" action="{{ route('cliente.sat.v2.index') }}" class="sv2Field">
                        <span class="sv2Float">RFC asociado</span>
                        <select name="rfc" class="sv2Select sv2Select--hero">
                            <option value="">Selecciona un RFC</option>
                            @foreach($rfcs as $rfc)
                                <option value="{{ $rfc->rfc }}" {{ $selectedRfc === $rfc->rfc ? 'selected' : '' }}>
                                    {{ $rfc->rfc }} — {{ $rfc->razon_social ?: 'Sin razón social' }}
                                </option>
                            @endforeach
                        </select>

                        <button type="submit" class="sv2Btn sv2Btn--primary">Entrar a la bóveda</button>

                        @if($selectedRfc !== '')
                            <button type="button" class="sv2Btn sv2Btn--ghost">RFC activo: {{ $selectedRfc }}</button>
                        @endif
                    </form>
                </div>
            </div>
        </section>

        <section class="sv2Section">
            <div class="sv2Section__head">
                <div>
                    <h2 class="sv2Section__title">Resumen</h2>
                    <p class="sv2Section__text">Indicadores principales.</p>
                </div>
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
            $step1Progress = 0;

            if ($selectedRfc !== '') {
                $step1Progress = 35;
            }

            if ($selectedRfc !== '' && $metadataUploads->count() > 0) {
                $step1Progress = 75;
            }

            if ($selectedRfc !== '' && (int) $metadataCount > 0) {
                $step1Progress = 100;
            }

            $step2Progress = 0;

            if ($selectedRfc !== '') {
                $step2Progress = 35;
            }

            if ($selectedRfc !== '' && $xmlUploads->count() > 0) {
                $step2Progress = 75;
            }

            if ($selectedRfc !== '' && (int) $cfdiCount > 0) {
                $step2Progress = 100;
            }

            $step3Progress = 0;

            if ($selectedRfc !== '') {
                $step3Progress = 35;
            }

            if ($selectedRfc !== '' && (($reportUploads->count() ?? 0) > 0)) {
                $step3Progress = 100;
            }
        @endphp

                      <section class="sv2Main sv2Main--single">
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
        </section>

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
    const openButtons = document.querySelectorAll('[data-sv2-open]');
    const closeButtons = document.querySelectorAll('[data-sv2-close]');
    const loading = document.getElementById('sv2Loading');
    const uploadForms = document.querySelectorAll('[data-sv2-upload-form]');

    let loadingTimer = null;
    let loadingSeconds = 0;

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sv2-modal-open');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('sv2-modal-open');
    }

    function closeAllModals() {
        document.querySelectorAll('.sv2Modal.is-open').forEach(function (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        });
    }

    function stopLoadingTimer() {
        if (loadingTimer) {
            clearInterval(loadingTimer);
            loadingTimer = null;
        }
    }

    function updateLoadingMeta(type, percentText, stageText, hintText) {
        if (!loading) return;

        const title = loading.querySelector('.sv2Loading__title');
        const text = loading.querySelector('.sv2Loading__text');
        const stage = document.getElementById('sv2LoadingStage');
        const elapsed = document.getElementById('sv2LoadingElapsed');
        const hint = document.getElementById('sv2LoadingHint');

        if (type === 'xml') {
            if (title) title.textContent = 'Cargando XML...';
            if (text) text.textContent = 'Estamos subiendo el XML y asociándolo al RFC y al lote metadata seleccionado.';
        } else if (type === 'report') {
            if (title) title.textContent = 'Cargando reporte...';
            if (text) text.textContent = 'Estamos subiendo el reporte y relacionándolo con el RFC y las cargas seleccionadas.';
        } else {
            if (title) title.textContent = 'Cargando metadata...';
            if (text) text.textContent = 'Estamos subiendo el archivo y registrando el lote para el RFC seleccionado.';
        }

        if (stage && stageText) stage.textContent = stageText;
        if (hint && hintText) hint.textContent = hintText;
        if (elapsed) elapsed.textContent = loadingSeconds + 's';
    }

    function showLoading(type) {
        if (!loading) return;

        loading.classList.add('is-open');
        loading.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sv2-modal-open');

        loadingSeconds = 0;
        updateLoadingMeta(type, '0%', 'Preparando envío', 'Si el archivo es pesado, el proceso puede tardar varios minutos.');

        stopLoadingTimer();
        loadingTimer = setInterval(function () {
            loadingSeconds++;
            const elapsed = document.getElementById('sv2LoadingElapsed');
            const stage = document.getElementById('sv2LoadingStage');
            const hint = document.getElementById('sv2LoadingHint');

            if (elapsed) elapsed.textContent = loadingSeconds + 's';

            if (loadingSeconds < 4) {
                if (stage) stage.textContent = 'Conectando con servidor';
            } else if (loadingSeconds < 10) {
                if (stage) stage.textContent = 'Esperando respuesta del servidor';
            } else if (loadingSeconds < 20) {
                if (stage) stage.textContent = 'Procesando archivo';
            } else {
                if (stage) stage.textContent = 'Seguimos trabajando';
            }

            if (loadingSeconds >= 12 && hint) {
                hint.textContent = 'El servidor sigue procesando la carga. ZIP y archivos grandes pueden tardar más.';
            }
        }, 1000);
    }

    function hideLoading() {
        stopLoadingTimer();

        if (!loading) return;

        loading.classList.remove('is-open');
        loading.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('sv2-modal-open');
    }

    function lockForm(form) {
        form.querySelectorAll('button, input, select, textarea').forEach(function (el) {
            if (el.type === 'hidden') return;
            el.disabled = true;
        });
    }

    function unlockForm(form) {
        form.querySelectorAll('button, input, select, textarea').forEach(function (el) {
            if (el.type === 'hidden') return;
            el.disabled = false;
        });
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
        lockForm(form);

        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.onprogress = function (event) {
            const stage = document.getElementById('sv2LoadingStage');
            const hint = document.getElementById('sv2LoadingHint');

            if (event.lengthComputable) {
                const percent = Math.round((event.loaded / event.total) * 100);
                if (stage) {
                    stage.textContent = 'Subiendo archivo (' + percent + '%)';
                }
                if (hint) {
                    hint.textContent = 'Carga en progreso. Espera a que el servidor termine de procesar el archivo.';
                }
            } else {
                if (stage) {
                    stage.textContent = 'Subiendo archivo';
                }
            }
        };

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;

            hideLoading();
            unlockForm(form);

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

            if (response && response.message) {
                alert(response.message);
            } else if (response && response.errors) {
                const firstKey = Object.keys(response.errors)[0];
                if (firstKey && response.errors[firstKey] && response.errors[firstKey][0]) {
                    alert(response.errors[firstKey][0]);
                } else {
                    alert('Ocurrió un error al cargar el archivo.');
                }
            } else {
                alert('Ocurrió un error al cargar el archivo. Revisa el servidor.');
            }
        };

        xhr.onerror = function () {
            hideLoading();
            unlockForm(form);
            alert('No se pudo completar la carga. Revisa tu conexión o el servidor.');
        };

        xhr.send(formData);
    }

    openButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.getAttribute('data-sv2-open'));
        });
    });

    closeButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            closeModal(btn.getAttribute('data-sv2-close'));
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (loading && loading.classList.contains('is-open')) {
                return;
            }
            closeAllModals();
        }
    });

    uploadForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            submitWithAjax(form);
        });
    });

    @if($errors->any())
        openModal(@json(old('sv2_open_modal', 'metadataModal')));
    @endif
});
</script>
@endpush