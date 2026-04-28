{{-- resources/views/cliente/facturacion/nuevo.blade.php --}}
@extends('layouts.cliente')
@section('title','Nuevo CFDI · Pactopia360')

@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Carbon;

    $summary = $summary ?? app(\App\Http\Controllers\Cliente\HomeController::class)->buildAccountSummary();

    $planFromSummary = strtoupper((string)($summary['plan'] ?? 'FREE'));
    $isProPlan = (bool)($summary['is_pro'] ?? in_array(strtolower($planFromSummary), ['pro','premium','empresa','business'], true));

    $emisores = collect($emisores ?? []);
    $receptores = collect($receptores ?? []);
    $productos = collect($productos ?? []);

    $today = Carbon::now();
    $monedaDefault = 'MXN';

    $rtBack = Route::has('cliente.facturacion.index')
        ? route('cliente.facturacion.index', request()->only(['q','status','month']))
        : url('/cliente/facturacion');

    $rtStore = Route::has('cliente.facturacion.store')
        ? route('cliente.facturacion.store')
        : url('/cliente/facturacion');

    $rtEmisoresIndex = Route::has('cliente.emisores.index') ? route('cliente.emisores.index') : '#';
    $rtEmisoresCreate = Route::has('cliente.emisores.create') ? route('cliente.emisores.create') : '#';
    $rtReceptoresIdx = Route::has('cliente.receptores.index') ? route('cliente.receptores.index') : '#';
    $rtReceptoresNew = Route::has('cliente.receptores.create') ? route('cliente.receptores.create') : '#';
    $rtRfcsIndex = Route::has('cliente.rfcs.index') ? route('cliente.rfcs.index') : url('/cliente/rfcs');

    $complementosCatalogo = [
        'pagos' => [
            'label' => 'Complemento de pago',
            'icon' => '💳',
            'desc' => 'Para CFDI PPD / REP.'
        ],
        'nomina' => [
            'label' => 'Nómina',
            'icon' => '👥',
            'desc' => 'Recibos de nómina.'
        ],
        'carta_porte' => [
            'label' => 'Carta Porte',
            'icon' => '🚚',
            'desc' => 'Traslado de mercancías.'
        ],
        'retenciones' => [
            'label' => 'Retenciones',
            'icon' => '%',
            'desc' => 'Impuestos retenidos.'
        ],
        'comercio_ext' => [
            'label' => 'Comercio exterior',
            'icon' => '🌎',
            'desc' => 'Operaciones internacionales.'
        ],
    ];

    $productosJs = $productos->map(function ($p) {
        return [
            'id' => $p->id,
            'label' => trim(($p->sku ? $p->sku.' - ' : '').($p->descripcion ?? '')),
            'descripcion' => $p->descripcion ?? '',
            'precio_unitario' => (float)($p->precio_unitario ?? 0),
            'iva_tasa' => (float)($p->iva_tasa ?? 0.16),
        ];
    })->values();
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/pages/facturacion-nuevo.css') }}?v={{ time() }}">
@endpush

@section('content')
<div class="cfdi-page cfdi-saas">

    @if(session('ok'))
        <div class="p360-alert ok">{{ session('ok') }}</div>
    @endif

    @if($errors->any())
        <div class="p360-alert error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $rtStore }}" id="newForm">
        @csrf

        <input type="hidden" name="accion_cfdi" id="accion_cfdi" value="borrador">

        <div class="cfdi-app">

            <header class="cfdi-command">
                <div class="cfdi-command-left">
                    <a href="{{ $rtBack }}" class="cfdi-back" title="Volver">←</a>

                    <div>
                        <div class="cfdi-kicker">CFDI 4.0 · Facturación 360</div>
                        <h1>Nuevo CFDI</h1>
                    </div>
                </div>

                <div class="cfdi-command-actions">
                    <button type="button" class="cfdi-btn ghost" id="btnPreview">
                        Vista previa
                    </button>

                    <button type="submit" class="cfdi-btn primary" data-cfdi-action="borrador">
                        Guardar borrador
                    </button>

                    <button type="submit" class="cfdi-btn success" id="btnTimbrar" data-cfdi-action="timbrar">
                        Timbrar CFDI
                    </button>
                </div>
            </header>

            <section class="cfdi-focus">
                <div class="cfdi-focus-main">
                    <span class="cfdi-focus-badge">Borrador fiscal</span>

                    <h2>Emite CFDI sin errores y con menos pasos</h2>

                    <p>
                        Flujo fiscal correcto: primero emisor, después receptor, conceptos, pago,
                        complementos, vista previa, timbrado y envío al receptor.
                    </p>
                </div>

                <div class="cfdi-focus-stats">
                    <div>
                        <small>Estado</small>
                        <strong id="heroValidationLabel">Sin timbrar</strong>
                    </div>

                    <div>
                        <small>Total</small>
                        <strong data-cfdi-total>$0.00 MXN</strong>
                    </div>

                    <div>
                        <small>Plan</small>
                        <strong>{{ $isProPlan ? 'PRO' : 'FREE' }}</strong>
                    </div>
                </div>
            </section>

            <nav class="cfdi-stepper" aria-label="Proceso CFDI">
                <button type="button" class="cfdi-step active" data-step-target="emisor">
                    <span>1</span> Emisor
                </button>

                <button type="button" class="cfdi-step" data-step-target="receptor">
                    <span>2</span> Receptor
                </button>

                <button type="button" class="cfdi-step" data-step-target="conceptos">
                    <span>3</span> Conceptos
                </button>

                <button type="button" class="cfdi-step" data-step-target="pago">
                    <span>4</span> Pago
                </button>

                <button type="button" class="cfdi-step" data-step-target="revision">
                    <span>5</span> Revisión
                </button>
            </nav>

            <div class="cfdi-layout">

                <main class="cfdi-builder">

                    <section class="cfdi-card cfdi-card-principal cfdi-emisor-clean" id="emisor">
                        <div class="cfdi-emisor-clean-head">
                            <div class="cfdi-emisor-title-wrap">
                                <div class="cfdi-emisor-icon">▦</div>
                                <div>
                                    <span class="cfdi-section-label">Paso 1</span>
                                    <h2>Emisor fiscal</h2>
                                    <p>Selecciona el RFC emisor. La configuración vive en RFC / Emisores.</p>
                                </div>
                            </div>

                            <a href="{{ $rtRfcsIndex }}" class="cfdi-btn small cfdi-emisor-admin">
                                ⚙ RFC / Emisores
                            </a>
                        </div>

                        <div class="cfdi-emisor-select-shell">
                            <label class="floating-field cfdi-emisor-select">
                                <span>RFC emisor</span>
                                <select name="cliente_id" id="cliente_id" required>
                                    @if($emisores->isEmpty())
                                        <option value="">No hay RFC emisores activos</option>
                                    @else
                                        <option value="">Seleccionar RFC emisor</option>
                                        @foreach($emisores as $e)
                                            <option value="{{ $e->id }}" @selected(old('cliente_id') == $e->id)>
                                                {{ $e->razon_social ?? $e->nombre_comercial ?? ('#'.$e->id) }} — {{ $e->rfc }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </label>
                        </div>

                        <div class="cfdi-emisor-mini-status">
                            <div class="cfdi-mini-chip">
                                <strong>CSD</strong>
                                <span>Desde RFC</span>
                            </div>

                            <div class="cfdi-mini-chip">
                                <strong>FIEL</strong>
                                <span>Desde RFC</span>
                            </div>

                            <div class="cfdi-mini-chip">
                                <strong>Serie</strong>
                                <span>Automática</span>
                            </div>

                            <div class="cfdi-mini-chip">
                                <strong>Folio</strong>
                                <span>Automático</span>
                            </div>

                            <div class="cfdi-mini-chip ok">
                                <strong>Estado</strong>
                                <span>Centralizado</span>
                            </div>
                        </div>

                        <input type="hidden" name="fecha" value="{{ old('fecha', $today->format('Y-m-d\TH:i')) }}">
                        <input type="hidden" name="serie" value="{{ old('serie') }}">
                        <input type="hidden" name="folio" value="{{ old('folio') }}">
                        <input type="hidden" name="moneda" value="{{ old('moneda', 'MXN') }}">
                        <input type="hidden" name="tipo_cambio" value="{{ old('tipo_cambio') }}">

                        @if($emisores->isEmpty())
                            <div class="cfdi-inline-warning compact">
                                No hay RFC activos.
                                <a href="{{ $rtRfcsIndex }}">Abrir RFC / Emisores</a>
                            </div>
                        @else
                            <div class="cfdi-emisor-note">
                                <span>ℹ</span>
                                <p>Al emitir, se usará la configuración fiscal guardada del RFC seleccionado.</p>
                            </div>
                        @endif

                    </section>

                    <section class="cfdi-card cfdi-card-principal" id="receptor">
                        <div class="cfdi-card-head">
                            <div>
                                <span class="cfdi-section-label">Paso 2</span>
                                <h2>Receptor fiscal</h2>
                                <p>
                                    Selecciona el cliente receptor. El régimen, CP y uso CFDI deben coincidir
                                    con la constancia fiscal del receptor.
                                </p>
                            </div>

                            <div class="cfdi-head-actions">
                                @if($rtReceptoresNew !== '#')
                                    <a href="{{ $rtReceptoresNew }}" class="cfdi-btn small">
                                        + Nuevo receptor
                                    </a>
                                @endif

                                @if($rtReceptoresIdx !== '#')
                                    <a href="{{ $rtReceptoresIdx }}" class="cfdi-icon-action" title="Editar receptores">
                                        ✎
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="cfdi-client-picker">
                            <label class="floating-field span-2">
                                <span>Buscar cliente / RFC</span>
                                <select name="receptor_id" id="receptor_id" required>
                                    @if($receptores->isEmpty())
                                        <option value="">No hay receptores registrados</option>
                                    @else
                                        <option value="">Buscar / seleccionar receptor</option>
                                        @foreach($receptores as $r)
                                            <option value="{{ $r->id }}"
                                                    data-rfc="{{ $r->rfc }}"
                                                    data-nombre="{{ $r->razon_social ?? $r->nombre_comercial }}"
                                                    data-cp="{{ $r->codigo_postal ?? $r->cp ?? '' }}"
                                                    data-regimen="{{ $r->regimen_fiscal ?? '' }}"
                                                    @selected(old('receptor_id') == $r->id)>
                                                {{ $r->razon_social ?? $r->nombre_comercial ?? ('#'.$r->id) }} — {{ $r->rfc }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </label>

                            <label class="floating-field">
                                <span>Uso CFDI</span>
                                <select name="uso_cfdi" id="uso_cfdi">
                                    <option value="G03" @selected(old('uso_cfdi','G03') === 'G03')>
                                        G03 · Gastos en general
                                    </option>
                                    <option value="G01" @selected(old('uso_cfdi') === 'G01')>
                                        G01 · Adquisición de mercancías
                                    </option>
                                    <option value="P01" @selected(old('uso_cfdi') === 'P01')>
                                        P01 · Por definir
                                    </option>
                                    <option value="S01" @selected(old('uso_cfdi') === 'S01')>
                                        S01 · Sin efectos fiscales
                                    </option>
                                </select>
                            </label>

                            <label class="floating-field">
                                <span>Régimen fiscal receptor</span>
                                <input type="text" name="regimen_receptor" id="regimen_receptor" value="{{ old('regimen_receptor') }}" placeholder="Autollenado">
                            </label>

                            <label class="floating-field">
                                <span>Código postal fiscal</span>
                                <input type="text" name="cp_receptor" id="cp_receptor" value="{{ old('cp_receptor') }}" maxlength="5" placeholder="00000">
                            </label>
                        </div>

                        <div class="cfdi-smart-card" id="receptorSmartCard">
                            <strong>Ficha fiscal inteligente</strong>
                            <span>
                                Selecciona receptor para validar RFC, régimen y código postal antes de timbrar.
                            </span>
                        </div>
                    </section>

                                        <section class="cfdi-card cfdi-card-principal cfdi-adenda-card" id="adenda">
                        <div class="cfdi-card-head">
                            <div>
                                <span class="cfdi-section-label">Adenda comercial</span>
                                <h2>Adenda / datos comerciales</h2>
                                <p>
                                    Agrega información comercial requerida por cadenas, marketplaces o clientes corporativos.
                                    No sustituye al complemento fiscal; se integra como nodo de adenda.
                                </p>
                            </div>

                            <label class="cfdi-adenda-switch">
                                <input type="checkbox" name="adenda_activa" id="adenda_activa" value="1" @checked(old('adenda_activa'))>
                                <span>Usar adenda</span>
                            </label>
                        </div>

                        <div class="cfdi-adenda-body" id="adendaBody" hidden>
                            <div class="cfdi-grid three">
                                <label class="floating-field">
                                    <span>Tipo de adenda</span>
                                    <select name="adenda_tipo" id="adenda_tipo">
                                        <option value="">Seleccionar</option>
                                        <option value="walmart" @selected(old('adenda_tipo') === 'walmart')>Walmart</option>
                                        <option value="soriana" @selected(old('adenda_tipo') === 'soriana')>Soriana</option>
                                        <option value="liverpool" @selected(old('adenda_tipo') === 'liverpool')>Liverpool</option>
                                        <option value="chedraui" @selected(old('adenda_tipo') === 'chedraui')>Chedraui</option>
                                        <option value="amazon" @selected(old('adenda_tipo') === 'amazon')>Amazon</option>
                                        <option value="mercado_libre" @selected(old('adenda_tipo') === 'mercado_libre')>Mercado Libre</option>
                                        <option value="oxxo_femsa" @selected(old('adenda_tipo') === 'oxxo_femsa')>OXXO / FEMSA</option>
                                        <option value="personalizada" @selected(old('adenda_tipo') === 'personalizada')>Personalizada</option>
                                    </select>
                                </label>

                                <label class="floating-field">
                                    <span>Orden de compra</span>
                                    <input type="text" name="adenda[orden_compra]" id="adenda_orden_compra" value="{{ old('adenda.orden_compra') }}" maxlength="120">
                                </label>

                                <label class="floating-field">
                                    <span>No. proveedor</span>
                                    <input type="text" name="adenda[numero_proveedor]" id="adenda_numero_proveedor" value="{{ old('adenda.numero_proveedor') }}" maxlength="120">
                                </label>

                                <label class="floating-field">
                                    <span>No. tienda / sucursal</span>
                                    <input type="text" name="adenda[numero_tienda]" value="{{ old('adenda.numero_tienda') }}" maxlength="120">
                                </label>

                                <label class="floating-field">
                                    <span>GLN / identificador</span>
                                    <input type="text" name="adenda[gln]" value="{{ old('adenda.gln') }}" maxlength="120">
                                </label>

                                <label class="floating-field">
                                    <span>Referencia de entrega</span>
                                    <input type="text" name="adenda[referencia_entrega]" value="{{ old('adenda.referencia_entrega') }}" maxlength="160">
                                </label>

                                <label class="floating-field">
                                    <span>Contrato</span>
                                    <input type="text" name="adenda[contrato]" value="{{ old('adenda.contrato') }}" maxlength="160">
                                </label>

                                <label class="floating-field">
                                    <span>Centro de costos</span>
                                    <input type="text" name="adenda[centro_costos]" value="{{ old('adenda.centro_costos') }}" maxlength="160">
                                </label>

                                <label class="floating-field">
                                    <span>Fecha entrega</span>
                                    <input type="date" name="adenda[fecha_entrega]" value="{{ old('adenda.fecha_entrega') }}">
                                </label>

                                <label class="floating-field span-3">
                                    <span>Observaciones comerciales</span>
                                    <textarea name="adenda[observaciones]" rows="3" maxlength="1000">{{ old('adenda.observaciones') }}</textarea>
                                </label>
                            </div>

                            <div class="cfdi-adenda-help" id="adendaHelp">
                                Selecciona el tipo de adenda. Si el cliente corporativo la requiere, captura al menos orden de compra y número de proveedor.
                            </div>
                        </div>
                    </section>

                    <section class="cfdi-card cfdi-card-principal cfdi-concepts-clean" id="conceptos">
                        <div class="cfdi-concepts-clean-head">
                            <div class="cfdi-concepts-title">
                                <div class="cfdi-concepts-icon">✦</div>
                                <div>
                                    <span class="cfdi-section-label">Paso 3</span>
                                    <h2>Conceptos CFDI</h2>
                                    <p>Agrega productos o servicios con validación fiscal.</p>
                                </div>
                            </div>

                            <button type="button" class="cfdi-btn small" id="btnAddConcept">
                                + Concepto
                            </button>
                        </div>

                        <div class="cfdi-concepts-ai-clean">
                            <div>
                                <strong>IA fiscal</strong>
                                <span id="conceptsAiSummary">Valida descripción, clave SAT, unidad, IVA y objeto de impuesto.</span>
                            </div>

                            <div class="cfdi-concepts-ai-score">
                                <b id="conceptsAiScore">0</b>
                                <small>/100</small>
                            </div>
                        </div>

                        <div class="cfdi-concept-cards" id="itemsBody"></div>

                        <button type="button" class="cfdi-add-line cfdi-add-line-pro" id="btnAddConceptInline">
                            + Agregar otro concepto
                        </button>

                        <div class="cfdi-concepts-footer">
                            <div class="cfdi-concepts-tip">
                                <span>IA</span>
                                <p>Escribe una descripción clara para sugerir configuración fiscal.</p>
                            </div>

                            <div class="cfdi-mini-total" id="calcPreview">
                                Subtotal: $0.00 · IVA: $0.00 · Total: $0.00
                            </div>
                        </div>
                    </section>

                    <section class="cfdi-card" id="pago">
                        <div class="cfdi-card-head">
                            <div>
                                <span class="cfdi-section-label">Paso 4</span>
                                <h2>Pago y complementos</h2>
                                <p>
                                    Define tipo de CFDI, método, forma de pago y complementos opcionales.
                                </p>
                            </div>
                        </div>

                        <div class="cfdi-grid four">
                            <label class="floating-field">
                                <span>Tipo CFDI</span>
                                <select name="tipo_comprobante" id="tipo_comprobante">
                                    <option value="I" @selected(old('tipo_comprobante','I') === 'I')>
                                        I · Ingreso
                                    </option>
                                    <option value="E" @selected(old('tipo_comprobante') === 'E')>
                                        E · Egreso
                                    </option>
                                    <option value="P" @selected(old('tipo_comprobante') === 'P')>
                                        P · Pago
                                    </option>
                                    <option value="T" @selected(old('tipo_comprobante') === 'T')>
                                        T · Traslado
                                    </option>
                                </select>
                            </label>

                            <label class="floating-field">
                                <span>Método de pago</span>
                                <select name="metodo_pago" id="metodo_pago">
                                    <option value="">Selecciona</option>
                                    <option value="PUE" @selected(old('metodo_pago') === 'PUE')>
                                        PUE · Pago en una exhibición
                                    </option>
                                    <option value="PPD" @selected(old('metodo_pago') === 'PPD')>
                                        PPD · Pago en parcialidades
                                    </option>
                                </select>
                            </label>

                            <label class="floating-field">
                                <span>Forma de pago</span>
                                <select name="forma_pago" id="forma_pago">
                                    <option value="">Selecciona</option>
                                    <option value="01" @selected(old('forma_pago') === '01')>01 · Efectivo</option>
                                    <option value="02" @selected(old('forma_pago') === '02')>02 · Cheque nominativo</option>
                                    <option value="03" @selected(old('forma_pago') === '03')>03 · Transferencia</option>
                                    <option value="04" @selected(old('forma_pago') === '04')>04 · Tarjeta crédito</option>
                                    <option value="28" @selected(old('forma_pago') === '28')>28 · Tarjeta débito</option>
                                    <option value="99" @selected(old('forma_pago') === '99')>99 · Por definir</option>
                                </select>
                            </label>

                            <label class="floating-field">
                                <span>Condiciones</span>
                                <input type="text" name="condiciones_pago" value="{{ old('condiciones_pago') }}" placeholder="Contado / Crédito">
                            </label>
                        </div>

                        <details class="cfdi-accordion">
                            <summary>Complementos y opciones avanzadas</summary>

                            <div class="cfdi-complements-grid" id="complementsGrid">
                                @foreach($complementosCatalogo as $key => $item)
                                    @php $checked = in_array($key, (array)old('complementos', []), true); @endphp

                                    <label class="comp-pill {{ $checked ? 'active' : '' }} {{ !$isProPlan ? 'disabled' : '' }}" title="{{ $item['desc'] }}">
                                        <input type="checkbox"
                                               name="complementos[]"
                                               value="{{ $key }}"
                                               @checked($checked)
                                               @disabled(!$isProPlan)>

                                        <span>{{ $item['icon'] }}</span>
                                        <b>{{ $item['label'] }}</b>

                                        @if(!$isProPlan)
                                            <em>PRO</em>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    </section>

                    <section class="cfdi-card" id="revision">
                        <div class="cfdi-card-head">
                            <div>
                                <span class="cfdi-section-label">Paso 5</span>
                                <h2>Revisión, vista previa y envío</h2>
                                <p>
                                    Valida el CFDI antes de timbrar. Después podrás descargar PDF/XML y enviarlo por correo.
                                </p>
                            </div>

                            <div class="cfdi-head-actions">
                                <button type="button" class="cfdi-btn ghost" id="btnPreviewInline">
                                    Vista previa
                                </button>

                                <button type="submit" class="cfdi-btn primary" data-cfdi-action="borrador">
                                    Guardar borrador
                                </button>

                                <button type="submit" class="cfdi-btn success" data-cfdi-action="timbrar">
                                    Timbrar
                                </button>
                            </div>
                        </div>

                        <div class="cfdi-review-grid">
                            <div class="review-item" data-review="emisor">Emisor pendiente</div>
                            <div class="review-item" data-review="receptor">Receptor pendiente</div>
                            <div class="review-item" data-review="conceptos">Conceptos pendientes</div>
                            <div class="review-item" data-review="pago">Pago pendiente</div>
                        </div>

                        <div class="cfdi-smart-card">
                            <strong>Después del timbrado</strong>
                            <span>
                                El sistema quedará preparado para generar PDF, XML, enviar por correo al receptor
                                y descontar timbres de la bolsa correspondiente cuando el módulo de timbres quede conectado.
                            </span>
                        </div>

                        <div class="cfdi-grid two" style="margin-top:14px;">
                            <label class="floating-field">
                                <span>Correo receptor</span>
                                <input type="email" name="correo_receptor" id="correo_receptor" value="{{ old('correo_receptor') }}" placeholder="cliente@empresa.com">
                            </label>

                            <label class="floating-field">
                                <span>Mensaje opcional</span>
                                <input type="text" name="mensaje_correo" id="mensaje_correo" value="{{ old('mensaje_correo') }}" placeholder="Adjunto CFDI PDF/XML">
                            </label>
                        </div>

                        <label class="comp-pill active" style="margin-top:14px;">
                            <input type="checkbox" name="enviar_correo" id="enviar_correo" value="1" checked>
                            <span>✉</span>
                            <b>Enviar CFDI por correo al receptor después de timbrar</b>
                        </label>
                    </section>

                </main>

                <aside class="cfdi-side-panel">

                    <div class="cfdi-total-card cfdi-total-sticky">
                        <div class="cfdi-total-top">
                            <div>
                                <small>Total CFDI</small>
                                <strong data-cfdi-total>$0.00 MXN</strong>
                            </div>

                            <span id="aiScore">50</span>
                        </div>

                        <div class="total-row">
                            <span>Subtotal</span>
                            <strong data-cfdi-subtotal>$0.00</strong>
                        </div>

                        <div class="total-row">
                            <span>IVA</span>
                            <strong data-cfdi-iva>$0.00</strong>
                        </div>

                        <div class="cfdi-side-actions">
                            <button type="button" class="cfdi-btn ghost full" id="btnPreviewSide">
                                Vista previa CFDI
                            </button>

                            <button type="submit" class="cfdi-btn primary full" data-cfdi-action="borrador">
                                Guardar borrador
                            </button>

                            <button type="submit" class="cfdi-btn success full" id="btnTimbrarSide" data-cfdi-action="timbrar">
                                Timbrar CFDI
                            </button>
                        </div>
                    </div>

                    <div class="cfdi-ai-card">
                        <div class="cfdi-side-title">
                            <strong>Validación fiscal</strong>
                            <small>Checklist en tiempo real</small>
                        </div>

                        <div class="cfdi-ai-list" id="aiList">
                            <div class="ai-item warn">Selecciona emisor.</div>
                            <div class="ai-item warn">Selecciona receptor.</div>
                            <div class="ai-item warn">Agrega conceptos.</div>
                            <div class="ai-item warn">Define método y forma de pago.</div>
                        </div>
                    </div>

                    <div class="cfdi-ai-card compact">
                        <div class="cfdi-side-title">
                            <strong>Funciones profesionales</strong>
                            <small>Mejorado para competir contra ERPs de facturación</small>
                        </div>

                        <ul>
                            <li>Flujo fiscal correcto: emisor → receptor → conceptos → pago.</li>
                            <li>Validación RFC, régimen fiscal y CP antes de timbrar.</li>
                            <li>Conceptos tipo carrito con cálculo automático.</li>
                            <li>Complementos ocultos hasta que el usuario los necesite.</li>
                            <li>Vista previa antes de timbrar.</li>
                            <li>Preparado para PDF, XML, correo y consumo de timbres.</li>
                        </ul>
                    </div>

                </aside>

            </div>
        </div>
    </form>
</div>

<div class="cfdi-product-modal" id="productModal" aria-hidden="true">
    <div class="cfdi-product-modal-backdrop" data-close-product-modal></div>

    <div class="cfdi-product-modal-dialog p360-product-ai-modal">
        <div class="cfdi-product-modal-head p360-product-ai-head">
            <div>
                <span class="p360-ai-pill">IA fiscal SAT</span>
                <h2>Productos y servicios</h2>
                <p>Encuentra la clave SAT correcta, unidad, IVA y guarda conceptos listos para facturar.</p>
            </div>

            <button type="button" data-close-product-modal>×</button>
        </div>

        <div class="cfdi-product-modal-body p360-product-ai-body">
            <div class="p360-product-ai-grid">

                <section class="p360-product-panel p360-product-panel-main">
                    <div class="p360-ai-search-card">
                        <div>
                            <strong>Asistente para identificar clave SAT</strong>
                            <span id="productAiText">Describe qué vendes o qué servicio prestas y te sugerimos la clave más probable.</span>
                        </div>

                        <input type="search"
                               id="product_ai_query"
                               placeholder="Ej. soporte mensual, desarrollo de software, renta, venta de equipo, consultoría...">
                    </div>

                    <div class="p360-sat-suggestions" id="productSatResults"></div>

                    <form id="productForm" class="cfdi-product-form p360-product-form">
                        <input type="hidden" id="product_id">

                        <label>
                            <span>SKU / Código interno</span>
                            <input type="text" id="product_sku" placeholder="Opcional">
                        </label>

                        <label class="span-2">
                            <span>Descripción fiscal</span>
                            <textarea id="product_descripcion" rows="3" required placeholder="Ej. Servicio mensual de soporte y mantenimiento"></textarea>
                        </label>

                        <label>
                            <span>Precio unitario</span>
                            <input type="number" id="product_precio" step="0.0001" min="0" value="0">
                        </label>

                        <label>
                            <span>Clave SAT</span>
                            <input type="text" id="product_clave" value="01010101">
                        </label>

                        <label>
                            <span>Unidad SAT</span>
                            <select id="product_unidad">
                                <option value="E48">E48 · Unidad de servicio</option>
                                <option value="H87">H87 · Pieza</option>
                                <option value="ACT">ACT · Actividad</option>
                                <option value="KGM">KGM · Kilogramo</option>
                                <option value="LTR">LTR · Litro</option>
                                <option value="MTR">MTR · Metro</option>
                            </select>
                        </label>

                        <label>
                            <span>IVA</span>
                            <select id="product_iva">
                                <option value="0.16">16%</option>
                                <option value="0.08">8%</option>
                                <option value="0">0%</option>
                            </select>
                        </label>

                        <label class="check p360-product-active">
                            <input type="checkbox" id="product_activo" checked>
                            <span>Activo</span>
                        </label>

                        <div class="cfdi-product-actions p360-product-actions">
                            <button type="button" class="cfdi-btn ghost" id="btnProductReset">Nuevo</button>
                            <button type="submit" class="cfdi-btn primary">Guardar producto</button>
                        </div>
                    </form>
                </section>

                <aside class="p360-product-panel p360-product-panel-side">
                    <div class="p360-product-help">
                        <strong>Guía rápida</strong>
                        <p id="productHelpText">La clave SAT debe representar lo que realmente vendes. Si no estás seguro, usa una sugerencia y valida antes de timbrar.</p>
                    </div>

                    <div class="p360-product-mini-kpis" id="productSuggestedMeta">
                        <div>
                            <small>Confianza</small>
                            <strong>0%</strong>
                        </div>
                        <div>
                            <small>Objeto impuesto</small>
                            <strong>02</strong>
                        </div>
                    </div>

                    <div class="cfdi-product-list-head p360-product-list-head">
                        <strong>Catálogo guardado</strong>
                        <input type="search" id="productSearch" placeholder="Buscar producto, SKU o clave SAT...">
                    </div>

                    <div class="cfdi-product-list p360-product-list" id="productList"></div>
                </aside>

            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.P360_CFDI_NUEVO = {
        isPro: @json($isProPlan),
        productos: @json($productosJs),
        csrf: @json(csrf_token()),
        productosUrl: @json(route('cliente.productos.index')),
        productosStoreUrl: @json(route('cliente.productos.store')),
        productosUpdateUrl: @json(url('/cliente/productos/__ID__')),
        productosDeleteUrl: @json(url('/cliente/productos/__ID__'))
    };
    document.addEventListener('click', function (event) {
        const actionBtn = event.target.closest('[data-cfdi-action]');
        if (!actionBtn) return;

        const actionInput = document.getElementById('accion_cfdi');
        if (actionInput) {
            actionInput.value = actionBtn.dataset.cfdiAction || 'borrador';
        }
    });
</script>
<script src="{{ asset('assets/client/js/pages/facturacion-nuevo.js') }}?v={{ time() }}"></script>
@endpush