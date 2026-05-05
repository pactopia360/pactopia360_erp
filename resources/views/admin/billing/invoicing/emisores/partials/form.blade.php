@php
    $row = $row ?? null;
    $cuentas = $cuentas ?? [];

    $decode = function ($value, $fallback = []) {
        if (is_array($value)) return $value;
        if (is_object($value)) return (array) $value;
        if (!is_string($value) || trim($value) === '') return $fallback;

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    };

    $selectedCuenta = old('cuenta_id', $row->cuenta_id ?? '');

    $direccionArr = $decode(old('direccion_json', $row->direccion ?? null), []);
    $certArr = $decode(old('certificados_json', $row->certificados ?? null), []);
    $seriesArr = $decode(old('series_json', $row->series ?? null), []);

    if (empty($seriesArr)) {
        $seriesArr = [
            ['tipo' => 'I', 'serie' => 'A', 'folio' => 1],
            ['tipo' => 'P', 'serie' => 'P', 'folio' => 1],
        ];
    }

    $direccionJson = old('direccion_json', json_encode($direccionArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $certificadosJson = old('certificados_json', json_encode($certArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $seriesJson = old('series_json', json_encode($seriesArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $statusValue = old('status', $row->status ?? 'active');

    $csdVigencia = old('csd_vigencia_hasta', isset($row->csd_vigencia_hasta) && $row->csd_vigencia_hasta
        ? \Illuminate\Support\Carbon::parse($row->csd_vigencia_hasta)->format('Y-m-d\TH:i')
        : ''
    );

    $regimenesFiscales = [
        '601' => '601 · General de Ley Personas Morales',
        '603' => '603 · Personas Morales con Fines no Lucrativos',
        '605' => '605 · Sueldos y Salarios e Ingresos Asimilados',
        '606' => '606 · Arrendamiento',
        '607' => '607 · Régimen de Enajenación o Adquisición de Bienes',
        '608' => '608 · Demás ingresos',
        '610' => '610 · Residentes en el Extranjero sin EP en México',
        '611' => '611 · Ingresos por Dividendos',
        '612' => '612 · Personas Físicas con Actividades Empresariales y Profesionales',
        '614' => '614 · Ingresos por intereses',
        '615' => '615 · Obtención de premios',
        '616' => '616 · Sin obligaciones fiscales',
        '620' => '620 · Sociedades Cooperativas de Producción',
        '621' => '621 · Incorporación Fiscal',
        '622' => '622 · Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
        '623' => '623 · Opcional para Grupos de Sociedades',
        '624' => '624 · Coordinados',
        '625' => '625 · Plataformas Tecnológicas',
        '626' => '626 · Régimen Simplificado de Confianza',
    ];

    $currentRegimen = old('regimen_fiscal', $row->regimen_fiscal ?? '');

    $bsv2CssPath = public_path('assets/admin/css/billing-statements-v2.css');
    $formCssPath = public_path('assets/admin/css/billing-emisores-form.css');

    $bsv2CssVer = is_file($bsv2CssPath) ? filemtime($bsv2CssPath) : time();
    $formCssVer = is_file($formCssPath) ? filemtime($formCssPath) : time();
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/billing-statements-v2.css') }}?v={{ $bsv2CssVer }}">
<link rel="stylesheet" href="{{ asset('assets/admin/css/billing-emisores-form.css') }}?v={{ $formCssVer }}">
@endpush

<div class="bsv2-page bef-page" data-bef-root>
    <div class="bsv2-wrap">
        <section class="bsv2-header-clean" aria-label="Encabezado del emisor">
            <div class="bsv2-header-clean__content">
                <div class="bsv2-header-clean__text">
                    <h1 class="bsv2-title">{{ $titleText }}</h1>
                    <p class="bsv2-subtitle">
                        Configura datos fiscales, domicilio, CSD/FIEL, series, correo, marca PDF e inteligencia de revisión.
                    </p>
                </div>

                <div class="bsv2-header-clean__meta">
                    <div class="bsv2-kpi">
                        <span class="bsv2-kpi__label">Modo</span>
                        <strong class="bsv2-kpi__value">{{ $mode === 'edit' ? 'Edición' : 'Alta' }}</strong>
                    </div>

                    <div class="bsv2-kpi">
                        <span class="bsv2-kpi__label">Facturotopía</span>
                        <strong class="bsv2-kpi__value">{{ filled(old('ext_id', $row->ext_id ?? '')) ? 'Ligado' : 'Pendiente' }}</strong>
                    </div>
                </div>
            </div>
        </section>

        @if($errors->any())
            <div class="bsv2-email-preview-box bef-error-box">
                <div class="bsv2-email-preview-box__title">Revisar antes de guardar</div>
                <div class="bsv2-email-preview-box__text">{{ $errors->first() }}</div>
            </div>
        @endif

        <form method="POST" action="{{ $actionUrl }}" id="emisorForm" enctype="multipart/form-data" class="bef-form">
            @csrf
            @if($mode === 'edit')
                @method('PUT')
            @endif

            <textarea name="direccion_json" id="direccion_json" class="bef-hidden-json">{{ $direccionJson }}</textarea>
            <textarea name="certificados_json" id="certificados_json" class="bef-hidden-json">{{ $certificadosJson }}</textarea>
            <textarea name="series_json" id="series_json" class="bef-hidden-json">{{ $seriesJson }}</textarea>

            <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Resumen inteligente del emisor">
                <div class="bsv2-list-card__accordion">
                    <button type="button" class="bsv2-list-card__summary" id="bef-summary-toggle" aria-expanded="true" aria-controls="bef-summary-content">
                        <span class="bsv2-list-card__summary-main">
                            <span class="bsv2-list-card__summary-title">Resumen IA</span>
                            <span class="bsv2-list-card__summary-meta">Estado fiscal, datos faltantes y salud de sincronización</span>
                        </span>
                        <span class="bsv2-list-card__summary-action" aria-hidden="true">
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                        </span>
                    </button>

                    <div class="bsv2-list-card__content" id="bef-summary-content">
                        <div class="bsv2-kpi-strip bef-kpi-strip">
                            <article class="bsv2-kpi-card">
                                <span class="bsv2-kpi-card__label">RFC</span>
                                <strong class="bsv2-kpi-card__value" id="summaryRfc">—</strong>
                                <span class="bsv2-kpi-card__meta" id="summaryRfcMeta">Pendiente</span>
                            </article>

                            <article class="bsv2-kpi-card">
                                <span class="bsv2-kpi-card__label">Régimen</span>
                                <strong class="bsv2-kpi-card__value" id="summaryRegimen">—</strong>
                                <span class="bsv2-kpi-card__meta">SAT</span>
                            </article>

                            <article class="bsv2-kpi-card">
                                <span class="bsv2-kpi-card__label">Dirección</span>
                                <strong class="bsv2-kpi-card__value" id="summaryDireccion">—</strong>
                                <span class="bsv2-kpi-card__meta">Fiscal</span>
                            </article>

                            <article class="bsv2-kpi-card">
                                <span class="bsv2-kpi-card__label">CSD</span>
                                <strong class="bsv2-kpi-card__value" id="summaryCsd">—</strong>
                                <span class="bsv2-kpi-card__meta">Certificado</span>
                            </article>

                            <article class="bsv2-kpi-card">
                                <span class="bsv2-kpi-card__label">Series</span>
                                <strong class="bsv2-kpi-card__value" id="summarySeries">—</strong>
                                <span class="bsv2-kpi-card__meta">Folio</span>
                            </article>

                            <article class="bsv2-kpi-card">
                                <span class="bsv2-kpi-card__label">Ext ID</span>
                                <strong class="bsv2-kpi-card__value" id="summaryExtId">—</strong>
                                <span class="bsv2-kpi-card__meta">Facturotopía</span>
                            </article>

                            <article class="bsv2-kpi-card">
                                <span class="bsv2-kpi-card__label">Cuenta</span>
                                <strong class="bsv2-kpi-card__value" id="summaryCuenta">—</strong>
                                <span class="bsv2-kpi-card__meta">Cliente</span>
                            </article>
                        </div>

                        <div class="bsv2-mini-analytics">
                            <article class="bsv2-mini-chart-card">
                                <div class="bsv2-mini-chart-card__head">
                                    <div>
                                        <div class="bsv2-mini-chart-card__title">Copiloto fiscal</div>
                                        <div class="bsv2-mini-chart-card__subtitle">Revisión automática local antes de guardar</div>
                                    </div>
                                    <div class="bsv2-mini-chart-card__badge" id="aiScore">0%</div>
                                </div>

                                <div class="bef-ai-actions">
                                    <button type="button" class="bsv2-btn bsv2-btn--primary" id="btnValidate">Validar con IA</button>
                                    <button type="button" class="bsv2-btn bsv2-btn--soft" id="btnAutofillCuenta">Autollenar cuenta</button>
                                    <button type="button" class="bsv2-btn bsv2-btn--soft" id="btnPactopia">Preset Pactopia</button>
                                    <button type="button" class="bsv2-btn bsv2-btn--ghost" id="btnBuildJson">Reconstruir JSON</button>
                                </div>
                            </article>

                            <article class="bsv2-mini-chart-card">
                                <div class="bsv2-mini-chart-card__head">
                                    <div>
                                        <div class="bsv2-mini-chart-card__title">Checklist IA</div>
                                        <div class="bsv2-mini-chart-card__subtitle">Datos obligatorios y recomendaciones</div>
                                    </div>
                                </div>

                                <div class="bef-ai-list" id="syncStatusList"></div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Datos fiscales">
                <div class="bsv2-list-card__accordion">
                    <button type="button" class="bsv2-list-card__summary" id="bef-fiscal-toggle" aria-expanded="true" aria-controls="bef-fiscal-content">
                        <span class="bsv2-list-card__summary-main">
                            <span class="bsv2-list-card__summary-title">Datos fiscales</span>
                            <span class="bsv2-list-card__summary-meta">RFC, razón social, régimen, cuenta y estado local</span>
                        </span>
                        <span class="bsv2-list-card__summary-action" aria-hidden="true">
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                        </span>
                    </button>

                    <div class="bsv2-list-card__content" id="bef-fiscal-content">
                        <div class="bef-section-body">
                            <div class="bef-grid">
                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="cuenta_id">Cuenta relacionada</label>
                                    <select name="cuenta_id" id="cuenta_id" class="bef-control">
                                        <option value="">Sin cuenta</option>
                                        @foreach($cuentas as $c)
                                            <option value="{{ $c['value'] }}" data-meta='@json($c["meta"] ?? [])' @selected((string) $selectedCuenta === (string) $c['value'])>
                                                {{ $c['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="rfc">RFC *</label>
                                    <input type="text" name="rfc" id="rfc" class="bef-control" value="{{ old('rfc', $row->rfc ?? '') }}" maxlength="13" required autocomplete="off">
                                    <div class="bef-help" id="aiRfcHint">La IA validará longitud, estructura y tipo de persona.</div>
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="razon_social">Razón social *</label>
                                    <input type="text" name="razon_social" id="razon_social" class="bef-control" value="{{ old('razon_social', $row->razon_social ?? '') }}" required>
                                    <div class="bef-help" id="aiRazonHint">Se revisa contra RFC y caracteres sospechosos.</div>
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="nombre_comercial">Nombre comercial</label>
                                    <input type="text" name="nombre_comercial" id="nombre_comercial" class="bef-control" value="{{ old('nombre_comercial', $row->nombre_comercial ?? '') }}">
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="regimen_fiscal">Régimen fiscal</label>
                                    <select name="regimen_fiscal" id="regimen_fiscal" class="bef-control">
                                        <option value="">Selecciona régimen</option>
                                        @foreach($regimenesFiscales as $clave => $label)
                                            <option value="{{ $clave }}" @selected((string) $currentRegimen === (string) $clave)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="bef-help" id="aiRegimenHint">La IA sugiere régimen según RFC moral/física.</div>
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="grupo">Grupo</label>
                                    <input type="text" name="grupo" id="grupo" class="bef-control" value="{{ old('grupo', $row->grupo ?? 'pactopia360') }}" maxlength="50">
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="status">Status local</label>
                                    <select name="status" id="status" class="bef-control">
                                        <option value="active" @selected((string) $statusValue === 'active')>Activo</option>
                                        <option value="inactive" @selected((string) $statusValue === 'inactive')>Inactivo</option>
                                        <option value="pending" @selected((string) $statusValue === 'pending')>Pendiente</option>
                                    </select>
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="ext_id">Ext ID Facturotopía</label>
                                    <input type="text" name="ext_id" id="ext_id" class="bef-control" value="{{ old('ext_id', $row->ext_id ?? '') }}" maxlength="80" placeholder="Automático si está vacío">
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="email">Email fiscal</label>
                                    <input type="email" name="email" id="email" class="bef-control" value="{{ old('email', $row->email ?? '') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Domicilio fiscal">
                <div class="bsv2-list-card__accordion">
                    <button type="button" class="bsv2-list-card__summary" id="bef-address-toggle" aria-expanded="false" aria-controls="bef-address-content">
                        <span class="bsv2-list-card__summary-main">
                            <span class="bsv2-list-card__summary-title">Domicilio fiscal</span>
                            <span class="bsv2-list-card__summary-meta">CP, país, estado, municipio, colonia y calle</span>
                        </span>
                        <span class="bsv2-list-card__summary-action" aria-hidden="true">
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                        </span>
                    </button>

                    <div class="bsv2-list-card__content" id="bef-address-content" hidden>
                        <div class="bef-section-body">
                            <div class="bef-grid">
                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="dir_cp">Código postal</label>
                                    <input type="text" id="dir_cp" class="bef-control" value="{{ $direccionArr['cp'] ?? '' }}" maxlength="10">
                                </div>

                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="dir_pais">País</label>
                                    <input type="text" id="dir_pais" class="bef-control" value="{{ $direccionArr['pais'] ?? 'MEX' }}" maxlength="10">
                                </div>

                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="dir_estado">Estado</label>
                                    <input type="text" id="dir_estado" class="bef-control" value="{{ $direccionArr['estado'] ?? '' }}">
                                </div>

                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="dir_ciudad">Municipio / ciudad</label>
                                    <input type="text" id="dir_ciudad" class="bef-control" value="{{ $direccionArr['ciudad'] ?? ($direccionArr['municipio'] ?? '') }}">
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="dir_colonia">Colonia</label>
                                    <input type="text" id="dir_colonia" class="bef-control" value="{{ $direccionArr['colonia'] ?? '' }}">
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="dir_calle">Calle</label>
                                    <input type="text" id="dir_calle" class="bef-control" value="{{ $direccionArr['calle'] ?? '' }}">
                                </div>

                                <div class="bef-field bef-col-2">
                                    <label class="bef-label" for="dir_no_ext">No. ext</label>
                                    <input type="text" id="dir_no_ext" class="bef-control" value="{{ $direccionArr['no_ext'] ?? '' }}">
                                </div>

                                <div class="bef-field bef-col-2">
                                    <label class="bef-label" for="dir_no_int">No. int</label>
                                    <input type="text" id="dir_no_int" class="bef-control" value="{{ $direccionArr['no_int'] ?? '' }}">
                                </div>

                                <div class="bef-field bef-col-12">
                                    <label class="bef-label" for="dir_direccion">Dirección completa</label>
                                    <input type="text" id="dir_direccion" class="bef-control" value="{{ $direccionArr['direccion'] ?? '' }}" placeholder="Calle, número, colonia, ciudad">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Certificados CSD FIEL">
                <div class="bsv2-list-card__accordion">
                    <button type="button" class="bsv2-list-card__summary" id="bef-certs-toggle" aria-expanded="false" aria-controls="bef-certs-content">
                        <span class="bsv2-list-card__summary-main">
                            <span class="bsv2-list-card__summary-title">CSD / FIEL</span>
                            <span class="bsv2-list-card__summary-meta">Archivos .cer/.key, contraseñas y vigencia</span>
                        </span>
                        <span class="bsv2-list-card__summary-action" aria-hidden="true">
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                        </span>
                    </button>

                    <div class="bsv2-list-card__content" id="bef-certs-content" hidden>
                        <div class="bef-section-body">
                            <div class="bef-grid">
                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="csd_serie">CSD serie</label>
                                    <input type="text" name="csd_serie" id="csd_serie" class="bef-control" value="{{ old('csd_serie', $row->csd_serie ?? '') }}" maxlength="100">
                                </div>

                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="csd_vigencia_hasta">CSD vigencia</label>
                                    <input type="datetime-local" name="csd_vigencia_hasta" id="csd_vigencia_hasta" class="bef-control" value="{{ $csdVigencia }}">
                                </div>

                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="csd_password">Contraseña CSD</label>
                                    <input type="password" id="csd_password" class="bef-control" value="{{ $certArr['csd_password'] ?? '' }}" autocomplete="new-password">
                                </div>

                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="fiel_password">Contraseña FIEL</label>
                                    <input type="password" id="fiel_password" class="bef-control" value="{{ $certArr['fiel_password'] ?? '' }}" autocomplete="new-password">
                                </div>

                                <div class="bef-field bef-col-6">
                                    <label class="bef-label" for="csd_cer_file">CSD .cer</label>
                                    <input type="file" name="csd_cer_file" id="csd_cer_file" class="bef-control" accept=".cer,.txt,.pem">
                                </div>

                                <div class="bef-field bef-col-6">
                                    <label class="bef-label" for="csd_key_file">CSD .key</label>
                                    <input type="file" name="csd_key_file" id="csd_key_file" class="bef-control" accept=".key,.txt,.pem">
                                </div>

                                <div class="bef-field bef-col-6">
                                    <label class="bef-label" for="fiel_cer_file">FIEL .cer</label>
                                    <input type="file" name="fiel_cer_file" id="fiel_cer_file" class="bef-control" accept=".cer,.txt,.pem">
                                </div>

                                <div class="bef-field bef-col-6">
                                    <label class="bef-label" for="fiel_key_file">FIEL .key</label>
                                    <input type="file" name="fiel_key_file" id="fiel_key_file" class="bef-control" accept=".key,.txt,.pem">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Series y correo">
                <div class="bsv2-list-card__accordion">
                    <button type="button" class="bsv2-list-card__summary" id="bef-ops-toggle" aria-expanded="false" aria-controls="bef-ops-content">
                        <span class="bsv2-list-card__summary-main">
                            <span class="bsv2-list-card__summary-title">Operación</span>
                            <span class="bsv2-list-card__summary-meta">Series, folios, marca PDF y correo</span>
                        </span>
                        <span class="bsv2-list-card__summary-action" aria-hidden="true">
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                        </span>
                    </button>

                    <div class="bsv2-list-card__content" id="bef-ops-content" hidden>
                        <div class="bef-section-body">
                            <div class="bef-grid">
                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="serie_i">Serie ingreso</label>
                                    <input type="text" id="serie_i" class="bef-control" value="{{ $seriesArr[0]['serie'] ?? 'A' }}">
                                </div>

                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="folio_i">Folio ingreso</label>
                                    <input type="number" id="folio_i" class="bef-control" value="{{ $seriesArr[0]['folio'] ?? 1 }}" min="1">
                                </div>

                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="serie_p">Serie pagos</label>
                                    <input type="text" id="serie_p" class="bef-control" value="{{ $seriesArr[1]['serie'] ?? 'P' }}">
                                </div>

                                <div class="bef-field bef-col-3">
                                    <label class="bef-label" for="folio_p">Folio pagos</label>
                                    <input type="number" id="folio_p" class="bef-control" value="{{ $seriesArr[1]['folio'] ?? 1 }}" min="1">
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="brand_logo_file">Logo del emisor</label>

                                    <div class="bef-logo-uploader">
                                        <div class="bef-logo-preview" id="brandLogoPreview">
                                            @if(!empty($certArr['brand']['logo_url']))
                                                <img src="{{ $certArr['brand']['logo_url'] }}" alt="Logo emisor">
                                            @else
                                                <span>Logo</span>
                                            @endif
                                        </div>

                                        <div class="bef-logo-controls">
                                            <input type="file" id="brand_logo_file" class="bef-control" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                                            <input type="text" id="brand_logo_url" class="bef-control" value="{{ $certArr['brand']['logo_url'] ?? '' }}" placeholder="URL del logo o se llenará al subir archivo">
                                            <div class="bef-help">Formatos sugeridos: PNG, JPG, WEBP o SVG.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="brand_primary">Color principal</label>

                                    <div class="bef-color-picker">
                                        <input type="color" id="brand_primary_picker" class="bef-color-input" value="{{ $certArr['brand']['primary_color'] ?? '#103a51' }}">
                                        <input type="text" id="brand_primary" class="bef-control" value="{{ $certArr['brand']['primary_color'] ?? '#103a51' }}">
                                    </div>

                                    <div class="bef-color-palette" data-target-color="brand_primary">
                                        <button type="button" class="bef-color-dot" style="--dot:#103a51" data-color="#103a51" aria-label="Azul Pactopia"></button>
                                        <button type="button" class="bef-color-dot" style="--dot:#15356c" data-color="#15356c" aria-label="Azul oscuro"></button>
                                        <button type="button" class="bef-color-dot" style="--dot:#2563eb" data-color="#2563eb" aria-label="Azul brillante"></button>
                                        <button type="button" class="bef-color-dot" style="--dot:#0f766e" data-color="#0f766e" aria-label="Verde teal"></button>
                                        <button type="button" class="bef-color-dot" style="--dot:#111827" data-color="#111827" aria-label="Negro elegante"></button>
                                    </div>
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="brand_accent">Color acento</label>

                                    <div class="bef-color-picker">
                                        <input type="color" id="brand_accent_picker" class="bef-color-input" value="{{ $certArr['brand']['accent_color'] ?? '#2563eb' }}">
                                        <input type="text" id="brand_accent" class="bef-control" value="{{ $certArr['brand']['accent_color'] ?? '#2563eb' }}">
                                    </div>

                                    <div class="bef-color-palette" data-target-color="brand_accent">
                                        <button type="button" class="bef-color-dot" style="--dot:#2563eb" data-color="#2563eb" aria-label="Azul"></button>
                                        <button type="button" class="bef-color-dot" style="--dot:#16a34a" data-color="#16a34a" aria-label="Verde"></button>
                                        <button type="button" class="bef-color-dot" style="--dot:#f59e0b" data-color="#f59e0b" aria-label="Ámbar"></button>
                                        <button type="button" class="bef-color-dot" style="--dot:#dc2626" data-color="#dc2626" aria-label="Rojo"></button>
                                        <button type="button" class="bef-color-dot" style="--dot:#7c3aed" data-color="#7c3aed" aria-label="Morado"></button>
                                    </div>
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="mail_from_name">Remitente</label>
                                    <input type="text" id="mail_from_name" class="bef-control" value="{{ $certArr['mail']['from_name'] ?? 'Pactopia360 Facturación' }}">
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="mail_reply_to">Reply-to</label>
                                    <input type="email" id="mail_reply_to" class="bef-control" value="{{ $certArr['mail']['reply_to'] ?? '' }}">
                                </div>

                                <div class="bef-field bef-col-4">
                                    <label class="bef-label" for="mail_cc">CC</label>
                                    <input type="text" id="mail_cc" class="bef-control" value="{{ is_array($certArr['mail']['cc'] ?? null) ? implode(',', $certArr['mail']['cc']) : ($certArr['mail']['cc'] ?? '') }}" placeholder="correo1@dominio.com, correo2@dominio.com">
                                </div>

                                <div class="bef-field bef-col-12">
                                    <label class="bef-label" for="brand_leyenda">Leyenda PDF</label>
                                    <textarea id="brand_leyenda" class="bef-control bef-textarea" rows="4">{{ $certArr['brand']['leyenda_pdf'] ?? 'Documento generado por Pactopia360.' }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bsv2-list-card bsv2-list-card--accordion" aria-label="Auditoría técnica">
                <div class="bsv2-list-card__accordion">
                    <button type="button" class="bsv2-list-card__summary" id="bef-json-toggle" aria-expanded="false" aria-controls="bef-json-content">
                        <span class="bsv2-list-card__summary-main">
                            <span class="bsv2-list-card__summary-title">JSON técnico</span>
                            <span class="bsv2-list-card__summary-meta">Vista de dirección, certificados, series, marca y correo</span>
                        </span>
                        <span class="bsv2-list-card__summary-action" aria-hidden="true">
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--plus">+</span>
                            <span class="bsv2-list-card__summary-icon bsv2-list-card__summary-icon--minus">−</span>
                        </span>
                    </button>

                    <div class="bsv2-list-card__content" id="bef-json-content" hidden>
                        <div class="bef-section-body">
                            <textarea id="jsonPreview" class="bef-control bef-textarea bef-json-preview" readonly></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <div class="bef-bottom-actions">
                <button type="submit" class="bsv2-btn bsv2-btn--primary">{{ $submitText }}</button>
                <a href="{{ route('admin.billing.invoicing.emisores.index') }}" class="bsv2-btn bsv2-btn--ghost">Volver</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(() => {
    'use strict';

    const form = document.getElementById('emisorForm');
    if (!form) return;

    const $ = (id) => document.getElementById(id);

    const accordionPairs = [
        ['bef-summary-toggle', 'bef-summary-content', true],
        ['bef-fiscal-toggle', 'bef-fiscal-content', true],
        ['bef-address-toggle', 'bef-address-content', false],
        ['bef-certs-toggle', 'bef-certs-content', false],
        ['bef-ops-toggle', 'bef-ops-content', false],
        ['bef-json-toggle', 'bef-json-content', false],
    ];

    const setupAccordion = (buttonId, contentId, defaultExpanded) => {
        const button = $(buttonId);
        const content = $(contentId);
        if (!button || !content) return;

        const setExpanded = (expanded) => {
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            content.hidden = !expanded;
        };

        setExpanded(defaultExpanded);

        button.addEventListener('click', () => {
            setExpanded(button.getAttribute('aria-expanded') !== 'true');
        });
    };

    accordionPairs.forEach(([buttonId, contentId, defaultExpanded]) => setupAccordion(buttonId, contentId, defaultExpanded));

    const el = {
        cuenta: $('cuenta_id'),
        rfc: $('rfc'),
        razon: $('razon_social'),
        nombre: $('nombre_comercial'),
        email: $('email'),
        regimen: $('regimen_fiscal'),
        grupo: $('grupo'),
        status: $('status'),
        extId: $('ext_id'),
        direccionJson: $('direccion_json'),
        certificadosJson: $('certificados_json'),
        seriesJson: $('series_json'),
        syncList: $('syncStatusList'),
        aiScore: $('aiScore'),
        jsonPreview: $('jsonPreview'),
        summaryRfc: $('summaryRfc'),
        summaryRfcMeta: $('summaryRfcMeta'),
        summaryRegimen: $('summaryRegimen'),
        summaryDireccion: $('summaryDireccion'),
        summaryCsd: $('summaryCsd'),
        summarySeries: $('summarySeries'),
        summaryExtId: $('summaryExtId'),
        summaryCuenta: $('summaryCuenta'),
        aiRfcHint: $('aiRfcHint'),
        aiRazonHint: $('aiRazonHint'),
        aiRegimenHint: $('aiRegimenHint'),
    };

    const regimenesMorales = ['601', '603', '620', '622', '623', '624'];
    const regimenesFisicas = ['605', '606', '607', '608', '611', '612', '614', '615', '616', '621', '625', '626'];

    const normalizeRfc = (value) => (value || '').toUpperCase().replace(/\s+/g, '').trim();

    const parseJson = (value, fallback = {}) => {
        try {
            const parsed = JSON.parse(value || '');
            return typeof parsed === 'object' && parsed !== null ? parsed : fallback;
        } catch (e) {
            return fallback;
        }
    };

    const pretty = (value) => JSON.stringify(value, null, 2);

    const isValidRfc = (rfc) => /^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/.test(rfc);
    const rfcType = (rfc) => rfc.length === 12 ? 'moral' : (rfc.length === 13 ? 'fisica' : 'desconocido');

    const selectedCuentaMeta = () => {
        const opt = el.cuenta?.options[el.cuenta.selectedIndex];
        if (!opt) return null;
        const raw = opt.getAttribute('data-meta');
        if (!raw) return null;
        try { return JSON.parse(raw); } catch (e) { return null; }
    };

    const setIfEmpty = (input, value) => {
        if (!input) return;
        const current = (input.value || '').trim();
        const next = (value || '').toString().trim();
        if (current === '' && next !== '') input.value = next;
    };

    const csvToArray = (value) => (value || '')
        .split(/[;,]+/)
        .map(v => v.trim())
        .filter(Boolean);

    const fileToBase64 = (file) => new Promise((resolve, reject) => {
        if (!file) return resolve('');
        const reader = new FileReader();
        reader.onload = () => {
            const raw = String(reader.result || '');
            resolve(raw.includes(',') ? raw.split(',').pop() : raw);
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });

    const fillFromCuenta = () => {
        const meta = selectedCuentaMeta();
        if (!meta) return;

        setIfEmpty(el.rfc, meta.rfc);
        setIfEmpty(el.razon, meta.razon_social);
        setIfEmpty(el.nombre, meta.nombre_comercial);
        setIfEmpty(el.email, meta.email);
        setIfEmpty(el.regimen, meta.regimen_fiscal);

        setIfEmpty($('dir_cp'), meta.cp);
        setIfEmpty($('dir_pais'), meta.pais || 'MEX');
        setIfEmpty($('dir_estado'), meta.estado);
        setIfEmpty($('dir_ciudad'), meta.municipio);
        setIfEmpty($('dir_colonia'), meta.colonia);
        setIfEmpty($('dir_calle'), meta.calle);
        setIfEmpty($('dir_no_ext'), meta.no_ext);
        setIfEmpty($('dir_no_int'), meta.no_int);

        buildJson();
    };

    const fillPactopia = () => {
        el.rfc.value = 'PAC251010CS1';
        el.razon.value = 'PACTOPIA S A P I DE CV';
        el.nombre.value = 'Pactopia360';
        el.regimen.value = el.regimen.value || '601';
        el.grupo.value = 'pactopia360';
        $('dir_pais').value = $('dir_pais').value || 'MEX';
        $('brand_primary').value = '#103a51';
        $('brand_accent').value = '#2563eb';
        buildJson();
    };

    const buildDireccion = () => {
        const calle = ($('dir_calle')?.value || '').trim();
        const noExt = ($('dir_no_ext')?.value || '').trim();
        const noInt = ($('dir_no_int')?.value || '').trim();
        const colonia = ($('dir_colonia')?.value || '').trim();
        const direccionManual = ($('dir_direccion')?.value || '').trim();

        return {
            cp: ($('dir_cp')?.value || '').trim(),
            pais: ($('dir_pais')?.value || 'MEX').trim(),
            estado: ($('dir_estado')?.value || '').trim(),
            ciudad: ($('dir_ciudad')?.value || '').trim(),
            municipio: ($('dir_ciudad')?.value || '').trim(),
            colonia,
            calle,
            no_ext: noExt,
            no_int: noInt,
            direccion: direccionManual || [calle, noExt, noInt, colonia].filter(Boolean).join(', '),
        };
    };

    const buildCertificados = () => {
        const prev = parseJson(el.certificadosJson.value, {});

        return {
            ...prev,
            csd_password: ($('csd_password')?.value || '').trim(),
            fiel_password: ($('fiel_password')?.value || '').trim(),
            brand: {
                logo_url: ($('brand_logo_url')?.value || '').trim(),
                primary_color: ($('brand_primary')?.value || '#103a51').trim(),
                accent_color: ($('brand_accent')?.value || '#2563eb').trim(),
                leyenda_pdf: ($('brand_leyenda')?.value || '').trim(),
            },
            mail: {
                from_name: ($('mail_from_name')?.value || '').trim(),
                reply_to: ($('mail_reply_to')?.value || '').trim(),
                cc: csvToArray($('mail_cc')?.value || ''),
            },
        };
    };

    const buildSeries = () => [
        {
            tipo: 'I',
            serie: ($('serie_i')?.value || 'A').trim(),
            folio: parseInt($('folio_i')?.value || '1', 10) || 1,
        },
        {
            tipo: 'P',
            serie: ($('serie_p')?.value || 'P').trim(),
            folio: parseInt($('folio_p')?.value || '1', 10) || 1,
        },
    ];

    const buildJson = () => {
    el.rfc.value = normalizeRfc(el.rfc.value);

    const direccion = buildDireccion();
    const certificados = buildCertificados();
    const series = buildSeries();

    el.direccionJson.value = pretty(direccion);
    el.certificadosJson.value = pretty(certificados);
    el.seriesJson.value = pretty(series);

    refreshState();
};

    const buildJsonWithFiles = async () => {
        buildJson();

        const cert = parseJson(el.certificadosJson.value, {});
        const fileMap = [
            ['csd_cer_file', 'csd_cer'],
            ['csd_key_file', 'csd_key'],
            ['fiel_cer_file', 'fiel_cer'],
            ['fiel_key_file', 'fiel_key'],
        ];

        for (const [inputId, key] of fileMap) {
            const input = $(inputId);
            const file = input?.files?.[0] || null;
            if (file) cert[key] = await fileToBase64(file);
        }

        const logoInput = $('brand_logo_file');
          const logoFile = logoInput?.files?.[0] || null;

          if (logoFile) {
              const logoBase64 = await fileToBase64(logoFile);
              cert.brand = cert.brand || {};
              cert.brand.logo_base64 = logoBase64;
              cert.brand.logo_mime = logoFile.type || '';
              cert.brand.logo_url = cert.brand.logo_url || '';
          }

        el.certificadosJson.value = pretty(cert);
        refreshState();
    };

    const aiIssues = () => {
        const issues = [];
        const warnings = [];
        const direccion = parseJson(el.direccionJson.value, {});
        const cert = parseJson(el.certificadosJson.value, {});
        const series = parseJson(el.seriesJson.value, []);
        const rfc = normalizeRfc(el.rfc.value);
        const type = rfcType(rfc);
        const razon = (el.razon.value || '').trim();
        const regimen = (el.regimen.value || '').trim();

        if (!rfc) issues.push('Falta RFC.');
        else if (!isValidRfc(rfc)) issues.push('RFC con formato inválido.');

        if (!razon) issues.push('Falta razón social.');
        if (razon && /[^\w\sÑñÁÉÍÓÚÜáéíóúü&.,-]/.test(razon)) warnings.push('Razón social contiene caracteres poco comunes.');

        if (!regimen) issues.push('Falta régimen fiscal.');
        if (type === 'moral' && regimen && !regimenesMorales.includes(regimen)) warnings.push('El RFC parece persona moral; revisa si el régimen seleccionado corresponde.');
        if (type === 'fisica' && regimen && !regimenesFisicas.includes(regimen)) warnings.push('El RFC parece persona física; revisa si el régimen seleccionado corresponde.');

        if (!el.email.value.trim()) issues.push('Falta email fiscal.');
        if (!direccion.cp) issues.push('Falta código postal fiscal.');
        if (direccion.cp && !/^\d{5}$/.test(String(direccion.cp))) warnings.push('CP fiscal debería tener 5 dígitos.');

        if (!cert.csd_cer) issues.push('Falta CSD .cer.');
        if (!cert.csd_key) issues.push('Falta CSD .key.');
        if (!cert.csd_password) issues.push('Falta contraseña CSD.');
        if (!Array.isArray(series) || series.length === 0) warnings.push('No hay series configuradas.');

        return { issues, warnings };
    };

    const refreshState = () => {
        const direccion = parseJson(el.direccionJson.value, {});
        const cert = parseJson(el.certificadosJson.value, {});
        const series = parseJson(el.seriesJson.value, []);
        const rfc = normalizeRfc(el.rfc.value);
        const type = rfcType(rfc);
        const { issues, warnings } = aiIssues();

        const totalChecks = 10;
        const score = Math.max(0, Math.round(((totalChecks - issues.length - (warnings.length * 0.5)) / totalChecks) * 100));

        el.aiScore.textContent = score + '%';
        el.summaryRfc.textContent = rfc || '—';
        el.summaryRfcMeta.textContent = isValidRfc(rfc) ? (type === 'moral' ? 'Moral' : 'Física') : 'Revisar';
        el.summaryRegimen.textContent = el.regimen.value || '—';
        el.summaryDireccion.textContent = [direccion.cp ? 'CP ' + direccion.cp : '', direccion.estado || '', direccion.municipio || direccion.ciudad || ''].filter(Boolean).join(' · ') || '—';
        el.summaryCsd.textContent = cert.csd_cer && cert.csd_key && cert.csd_password ? 'Completo' : 'Pendiente';
        el.summarySeries.textContent = Array.isArray(series) ? String(series.length) : '0';
        el.summaryExtId.textContent = (el.extId.value || '').trim() || 'Auto';
        el.summaryCuenta.textContent = el.cuenta.value ? 'Sí' : 'No';

        el.aiRfcHint.textContent = isValidRfc(rfc) ? 'RFC válido en formato.' : 'RFC pendiente o inválido.';
        el.aiRazonHint.textContent = el.razon.value.trim() ? 'Razón social capturada.' : 'Captura la razón social fiscal exacta.';
        el.aiRegimenHint.textContent = el.regimen.value ? 'Régimen seleccionado.' : 'Selecciona régimen fiscal SAT.';

        const rows = [];
        issues.forEach(item => rows.push(`<div class="bef-ai-item is-danger">✕ ${item}</div>`));
        warnings.forEach(item => rows.push(`<div class="bef-ai-item is-warning">• ${item}</div>`));

        if (rows.length === 0) {
            rows.push('<div class="bef-ai-item is-ok">✓ Emisor listo para guardar y preparar sincronización.</div>');
        }

        el.syncList.innerHTML = rows.join('');

        el.jsonPreview.value = pretty({
            direccion,
            certificados: {
                ...cert,
                csd_cer: cert.csd_cer ? '[base64]' : '',
                csd_key: cert.csd_key ? '[base64]' : '',
                fiel_cer: cert.fiel_cer ? '[base64]' : '',
                fiel_key: cert.fiel_key ? '[base64]' : '',
            },
            series,
        });
    };

    el.cuenta?.addEventListener('change', fillFromCuenta);

    const syncColorPair = (textId, pickerId) => {
    const text = $(textId);
    const picker = $(pickerId);
    if (!text || !picker) return;

    const normalizeColor = (value) => {
        const raw = (value || '').trim();
        return /^#[0-9A-Fa-f]{6}$/.test(raw) ? raw : '#103a51';
    };

    text.addEventListener('input', () => {
        picker.value = normalizeColor(text.value);
        buildJson();
    });

    picker.addEventListener('input', () => {
        text.value = picker.value;
        buildJson();
    });
};

syncColorPair('brand_primary', 'brand_primary_picker');
syncColorPair('brand_accent', 'brand_accent_picker');

document.querySelectorAll('.bef-color-dot').forEach((button) => {
    button.addEventListener('click', () => {
        const palette = button.closest('.bef-color-palette');
        const targetId = palette?.dataset?.targetColor || '';
        const target = $(targetId);
        const picker = $(targetId + '_picker');
        const color = button.dataset.color || '';

        if (target && color) target.value = color;
        if (picker && color) picker.value = color;

        buildJson();
    });
});

$('brand_logo_file')?.addEventListener('change', () => {
    const input = $('brand_logo_file');
    const file = input?.files?.[0] || null;
    const preview = $('brandLogoPreview');

    if (!file || !preview) return;

    const reader = new FileReader();
    reader.onload = () => {
        preview.innerHTML = '<img src="' + String(reader.result || '') + '" alt="Logo emisor">';
    };
    reader.readAsDataURL(file);

    buildJson();
});

$('brand_logo_url')?.addEventListener('input', () => {
    const url = $('brand_logo_url')?.value || '';
    const preview = $('brandLogoPreview');

    if (!preview) return;

    if (url.trim() === '') {
        preview.innerHTML = '<span>Logo</span>';
        buildJson();
        return;
    }

    preview.innerHTML = '<img src="' + url.replace(/"/g, '&quot;') + '" alt="Logo emisor">';
    buildJson();
});

    document.querySelectorAll('input, select, textarea').forEach(input => {
        if (['direccion_json', 'certificados_json', 'series_json', 'jsonPreview'].includes(input.id)) return;
        input.addEventListener('input', buildJson);
        input.addEventListener('change', buildJson);
    });

    $('btnAutofillCuenta')?.addEventListener('click', fillFromCuenta);
    $('btnPactopia')?.addEventListener('click', fillPactopia);
    $('btnBuildJson')?.addEventListener('click', buildJson);
    $('btnValidate')?.addEventListener('click', () => {
        buildJson();
        const { issues, warnings } = aiIssues();
        alert(issues.length || warnings.length
            ? 'Revisión IA:\n' + [...issues, ...warnings].map(item => '- ' + item).join('\n')
            : 'Emisor validado correctamente.'
        );
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        try {
            await buildJsonWithFiles();
            form.submit();
        } catch (error) {
            console.error('Error preparando emisor:', error);
            alert('No se pudo preparar la información del emisor. Revisa consola.');
        }
    });

    buildJson();
})();
</script>
@endpush