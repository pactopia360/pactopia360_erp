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
    $empleadosNomina = collect($empleadosNomina ?? []);
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

    $empleadosNominaJs = $empleadosNomina->map(function ($e) {
        return [
            'id' => $e->id,
            'numero_empleado' => $e->numero_empleado ?? '',
            'rfc' => $e->rfc ?? '',
            'curp' => $e->curp ?? '',
            'nombre_completo' => $e->nombre_completo ?? '',
            'codigo_postal' => $e->codigo_postal ?? '',
            'regimen_fiscal' => $e->regimen_fiscal ?? '605',
            'uso_cfdi' => $e->uso_cfdi ?? 'CN01',
            'departamento' => $e->departamento ?? '',
            'puesto' => $e->puesto ?? '',
        ];
    })->values();

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
<link rel="stylesheet" href="{{ asset('assets/client/css/sat/sat-portal-v1.css') }}?v={{ time() }}">
<link rel="stylesheet" href="{{ asset('assets/client/css/pages/facturacion-360.css') }}?v={{ time() }}">
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

                <section class="sat-clean-hero sat-clean-hero--portal sat-clean-hero--portal-simple" aria-label="Nuevo CFDI">
    <div class="sat-clean-hero__content sat-clean-hero__content--portal sat-clean-hero__content--portal-simple">
        <div class="sat-clean-hero__main sat-clean-hero__main--portal-simple">
            <div
                aria-hidden="true"
                style="
                    position:absolute;
                    inset:0;
                    border-radius:inherit;
                    background:
                        radial-gradient(circle at 18% 22%, rgba(255,255,255,.10), transparent 28%),
                        linear-gradient(90deg, rgba(7,20,54,.22) 0%, rgba(7,20,54,.10) 42%, rgba(255,255,255,0) 76%);
                    pointer-events:none;
                    z-index:1;
                "
            ></div>

            <div style="min-width:0; max-width:100%; position:relative; z-index:2; padding:8px 0;">
                <div
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:8px;
                        min-height:30px;
                        padding:0 14px;
                        border-radius:999px;
                        background:rgba(255,255,255,.16);
                        border:1px solid rgba(255,255,255,.18);
                        color:#f8fbff;
                        font-size:11px;
                        font-weight:800;
                        letter-spacing:.05em;
                        margin-bottom:16px;
                        backdrop-filter:blur(4px);
                        box-shadow:0 8px 20px rgba(10,24,64,.12);
                    "
                >
                    <span
                        style="width:10px; height:10px; border-radius:999px; background:#ffffff; box-shadow:0 0 0 6px rgba(255,255,255,.12); display:inline-block;"
                        aria-hidden="true"
                    ></span>
                    <span>CFDI · FACTURACIÓN 360</span>
                </div>

               <div class="cfdi-title-row" style="display:flex; align-items:center; gap:14px; margin:0 0 14px 0;">
                    <span
                        style="
                            width:58px;
                            height:58px;
                            border-radius:18px;
                            display:inline-flex;
                            align-items:center;
                            justify-content:center;
                            background:rgba(255,255,255,.16);
                            border:1px solid rgba(255,255,255,.18);
                            box-shadow:0 14px 28px rgba(10,24,64,.20);
                            flex:0 0 58px;
                            color:#ffffff;
                        "
                        aria-hidden="true"
                    >
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                            <path d="M4 5h16v14H4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <path d="M8 9h8M8 13h8M8 17h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </span>

                    <h1
                        class="sat-clean-hero__title sat-clean-hero__title--portal"
                        style="
                            margin:0;
                            color:#ffffff;
                            font-size:clamp(36px, 3vw, 56px);
                            line-height:.98;
                            letter-spacing:-.05em;
                            font-weight:900;
                            text-shadow:0 10px 24px rgba(10,24,64,.24);
                        "
                    >
                        Nuevo CFDI
                    </h1>
                </div>

                <p
                    class="sat-clean-hero__text sat-clean-hero__text--portal"
                    style="
                        max-width:760px;
                        margin:0 0 18px 0;
                        color:rgba(255,255,255,.96);
                        font-size:15px;
                        line-height:1.58;
                        font-weight:500;
                        text-shadow:0 6px 18px rgba(10,24,64,.18);
                    "
                >
                    Crea, valida y timbra tu comprobante en Pactopia360 con un flujo fiscal guiado según el tipo de CFDI.
                </p>

                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <span style="display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 13px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.18); color:#ffffff; font-size:12px; font-weight:700;">CFDI</span>
                    <span style="display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 13px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.18); color:#ffffff; font-size:12px; font-weight:700;">Nómina</span>
                    <span style="display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 13px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.18); color:#ffffff; font-size:12px; font-weight:700;">REP</span>
                    <span style="display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 13px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.18); color:#ffffff; font-size:12px; font-weight:700;">Carta Porte</span>
                    <span style="display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 13px; border-radius:999px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.18); color:#ffffff; font-size:12px; font-weight:700;">Complementos</span>
                </div>
            </div>

            <div class="cfdi-hero-status">
                    <div class="cfdi-hero-status__item">
                        <span>Estado</span>
                        <strong id="heroValidationLabel">Pendiente</strong>
                        <small>Validación CFDI</small>
                    </div>

                    <div class="cfdi-hero-status__item">
                        <span>Total</span>
                        <strong data-cfdi-total>$0.00 MXN</strong>
                        <small>Comprobante</small>
                    </div>
                </div>
                        </div>
                    </div>
                </section>

                <nav class="cfdi-stepper" aria-label="Proceso CFDI">
                    <button type="button" class="cfdi-step active" data-step-target="tipo_cfdi">
                        <span>1</span> Tipo CFDI
                    </button>

                    <button type="button" class="cfdi-step" data-step-target="emisor">
                        <span>2</span> Emisor
                    </button>

                    <button type="button" class="cfdi-step" data-step-target="receptor">
                        <span>3</span> Receptor
                    </button>

                    <button type="button" class="cfdi-step" data-step-target="conceptos">
                        <span>4</span> Conceptos
                    </button>

                    <button type="button" class="cfdi-step" data-step-target="revision">
                        <span>5</span> Timbrar
                    </button>
                </nav>

                <div class="cfdi-layout">

                    <main class="cfdi-builder">

                    <details class="cfdi-card cfdi-card-principal" id="tipo_cfdi" open>
            <summary class="cfdi-card-head">
                <div>
                    <span class="cfdi-section-label">Paso 1</span>
                    <h2>Tipo de CFDI</h2>
                    <p>Primero define el comprobante: ingreso, egreso, pago, traslado o nómina.</p>
                </div>
            </summary>

            <div class="cfdi-grid four">
                <label class="floating-field">
                    <span>Tipo</span>
                    <select name="tipo_comprobante" id="tipo_comprobante">
                        <option value="I" @selected(old('tipo_comprobante','I') === 'I')>I · Ingreso</option>
                        <option value="E" @selected(old('tipo_comprobante') === 'E')>E · Egreso</option>
                        <option value="P" @selected(old('tipo_comprobante') === 'P')>P · Complemento de pago REP</option>
                        <option value="T" @selected(old('tipo_comprobante') === 'T')>T · Traslado / Carta Porte</option>
                        <option value="N" @selected(old('tipo_comprobante') === 'N')>N · Nómina</option>
                    </select>
                </label>

                <label class="floating-field">
                    <span>Método</span>
                    <select name="metodo_pago" id="metodo_pago">
                        <option value="">Selecciona</option>
                        <option value="PUE" @selected(old('metodo_pago') === 'PUE')>PUE · Una exhibición</option>
                        <option value="PPD" @selected(old('metodo_pago') === 'PPD')>PPD · Parcialidades</option>
                    </select>
                </label>

                <label class="floating-field">
                    <span>Forma</span>
                    <select name="forma_pago" id="forma_pago">
                        <option value="">Selecciona</option>
                        <option value="01" @selected(old('forma_pago') === '01')>01 · Efectivo</option>
                        <option value="02" @selected(old('forma_pago') === '02')>02 · Cheque</option>
                        <option value="03" @selected(old('forma_pago') === '03')>03 · Transferencia</option>
                        <option value="04" @selected(old('forma_pago') === '04')>04 · Crédito</option>
                        <option value="28" @selected(old('forma_pago') === '28')>28 · Débito</option>
                        <option value="99" @selected(old('forma_pago') === '99')>99 · Por definir</option>
                    </select>
                </label>

                <label class="floating-field">
                    <span>Condiciones</span>
                    <input type="text" name="condiciones_pago" value="{{ old('condiciones_pago') }}" placeholder="Contado / Crédito">
                </label>
            </div>

            <div class="cfdi-smart-card" id="tipoCfdiSmartCard">
                <strong>Factura de ingreso</strong>
                <span>Flujo normal: emisor, receptor fiscal, conceptos, pago, adenda opcional y timbrado.</span>
            </div>

            <details class="cfdi-accordion" id="complementosAccordion">
                <summary>Complementos disponibles</summary>

                <div class="cfdi-complements-grid" id="complementsGrid">
                    @foreach($complementosCatalogo as $key => $item)
                        @php
                            $checked = in_array($key, (array) old('complementos', []), true);
                        @endphp

                        <label class="comp-pill {{ $checked ? 'active' : '' }}" data-complemento="{{ $key }}" title="{{ $item['desc'] }}">
                            <input type="checkbox"
                                name="complementos[]"
                                value="{{ $key }}"
                                @checked($checked)>

                            <span>{{ $item['icon'] }}</span>
                            <b>{{ $item['label'] }}</b>
                        </label>
                    @endforeach
                </div>
            </details>
        </details>

                <details class="cfdi-card cfdi-card-principal cfdi-emisor-clean" id="emisor">
                    <summary class="cfdi-card-head">
                        <div class="cfdi-emisor-title-wrap">
                            <div class="cfdi-emisor-icon">▦</div>
                            <div>
                               <span class="cfdi-section-label">Paso 2</span>
                                <h2>Emisor</h2>
                                <p>RFC que emite.</p>
                            </div>
                        </div>

                        <a href="{{ $rtRfcsIndex }}" class="cfdi-btn small cfdi-emisor-admin" onclick="event.stopPropagation();">
                            RFC / Emisores
                        </a>
                    </summary>

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
                            <span>RFC</span>
                        </div>

                        <div class="cfdi-mini-chip">
                            <strong>Serie</strong>
                            <span>Auto</span>
                        </div>

                        <div class="cfdi-mini-chip">
                            <strong>Folio</strong>
                            <span>Auto</span>
                        </div>

                        <div class="cfdi-mini-chip ok">
                            <strong>Estado</strong>
                            <span>Listo</span>
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
                    @endif
                </details>

                <details class="cfdi-card cfdi-card-principal" id="receptor">
                    <summary class="cfdi-card-head">
                        <div>
                            <span class="cfdi-section-label">Paso 3</span>
                            <h2>Receptor</h2>
                            <p>Receptor del CFDI.</p>
                        </div>

                        <div class="cfdi-head-actions" data-receptor-actions-clientes>
                            @if($rtReceptoresNew !== '#')
                                <a href="{{ $rtReceptoresNew }}" class="cfdi-btn small" onclick="event.stopPropagation();">
                                    + Nuevo
                                </a>
                            @endif

                            @if($rtReceptoresIdx !== '#')
                                <a href="{{ $rtReceptoresIdx }}" class="cfdi-icon-action" title="Editar receptores" onclick="event.stopPropagation();">
                                    ✎
                                </a>
                            @endif
                        </div>
                    </summary>

                    <div class="cfdi-receptor-mode-card">
                        <strong id="receptorModeTitle">Cliente fiscal</strong>
                        <span id="receptorModeText">Para ingreso, egreso, traslado y pagos se usa el catálogo normal de receptores.</span>
                    </div>

                    <div class="cfdi-client-picker" id="receptorClientePanel">
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
                                <option value="G03" @selected(old('uso_cfdi','G03') === 'G03')>G03 · Gastos</option>
                                <option value="G01" @selected(old('uso_cfdi') === 'G01')>G01 · Mercancías</option>
                                <option value="CP01" @selected(old('uso_cfdi') === 'CP01')>CP01 · Pagos</option>
                                <option value="CN01" @selected(old('uso_cfdi') === 'CN01')>CN01 · Nómina</option>
                                <option value="S01" @selected(old('uso_cfdi') === 'S01')>S01 · Sin efectos</option>
                            </select>
                        </label>

                        <label class="floating-field">
                            <span>Régimen</span>
                            <input type="text" name="regimen_receptor" id="regimen_receptor" value="{{ old('regimen_receptor') }}" placeholder="Autollenado">
                        </label>

                        <label class="floating-field">
                            <span>CP fiscal</span>
                            <input type="text" name="cp_receptor" id="cp_receptor" value="{{ old('cp_receptor') }}" maxlength="5" placeholder="00000">
                        </label>
                    </div>

                    <div class="cfdi-client-picker cfdi-nomina-picker is-hidden" id="receptorNominaPanel" hidden>
                        <label class="floating-field span-2">
                            <span>Empleado de nómina</span>
                            <select name="empleado_nomina_id" id="empleado_nomina_id">
                                @if($empleadosNomina->isEmpty())
                                    <option value="">No hay empleados de nómina registrados</option>
                                @else
                                    <option value="">Seleccionar empleado</option>
                                    @foreach($empleadosNomina as $e)
                                        <option value="{{ $e->id }}"
                                            data-rfc="{{ $e->rfc }}"
                                            data-curp="{{ $e->curp }}"
                                            data-nombre="{{ $e->nombre_completo }}"
                                            data-cp="{{ $e->codigo_postal }}"
                                            data-regimen="{{ $e->regimen_fiscal ?? '605' }}"
                                            data-uso="{{ $e->uso_cfdi ?? 'CN01' }}"
                                            data-puesto="{{ $e->puesto }}"
                                            data-departamento="{{ $e->departamento }}"
                                            @selected(old('empleado_nomina_id') == $e->id)>
                                            {{ $e->nombre_completo }} — {{ $e->rfc }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </label>

                        <label class="floating-field">
                            <span>Uso CFDI</span>
                            <input type="text" value="CN01 · Nómina" readonly>
                        </label>

                        <label class="floating-field">
                            <span>Régimen empleado</span>
                            <input type="text" id="nomina_regimen_preview" value="" placeholder="605" readonly>
                        </label>

                        <label class="floating-field">
                            <span>CP fiscal empleado</span>
                            <input type="text" id="nomina_cp_preview" value="" placeholder="00000" readonly>
                        </label>
                    </div>

                    <div class="cfdi-smart-card" id="receptorSmartCard">
                        <strong>IA fiscal</strong>
                        <span>Selecciona cliente para validar RFC, régimen y CP.</span>
                    </div>
                </details>
                

                <details class="cfdi-card cfdi-card-principal cfdi-concepts-clean" id="conceptos" open>
                    <summary class="cfdi-concepts-clean-head">
                        <div class="cfdi-concepts-title">
                            <div class="cfdi-concepts-icon">✦</div>
                            <div>
                                <span class="cfdi-section-label">Paso 4</span>
                                <h2>Conceptos</h2>
                                <p>Productos o servicios.</p>
                            </div>
                        </div>

                        <button type="button" class="cfdi-btn small" id="btnAddConcept" onclick="event.stopPropagation();">
                            + Concepto
                        </button>
                    </summary>

                    <div class="cfdi-concepts-ai-clean">
                        <div>
                            <strong>IA de llenado + IA fiscal</strong>
                            <span id="conceptsAiSummary">Valida clave SAT, unidad, IVA, objeto impuesto y riesgos antes de timbrar.</span>
                        </div>

                        <div class="cfdi-head-actions">
                            <button type="button" class="cfdi-btn small ghost" data-ai-fill>
                                IA llenar concepto
                            </button>

                            <button type="button" class="cfdi-btn small primary" data-ai-fiscal>
                                Validar fiscalmente
                            </button>
                        </div>

                        <div class="cfdi-concepts-ai-score">
                            <b id="conceptsAiScore">0</b>
                            <small>/100</small>
                        </div>
                    </div>

                    <div class="cfdi-smart-card" id="p360FlowHelpPanel">
                        <strong>Ayuda inteligente por tipo CFDI</strong>
                        <span>
                            Selecciona el tipo de comprobante y Pactopia360 ajustará receptor, conceptos,
                            complementos, adendas, nómina, pagos REP o Carta Porte.
                        </span>
                    </div>

                    <div class="cfdi-smart-card is-hidden" id="p360RepPanel" hidden></div>
                    <div class="cfdi-smart-card is-hidden" id="p360NominaPanel" hidden></div>
                    <div class="cfdi-smart-card is-hidden" id="p360CartaPortePanel" hidden></div>
                    <div class="cfdi-smart-card" id="p360ExcelAssistPanel"></div>

                    <div class="cfdi-concept-cards" id="itemsBody"></div>

                    <button type="button" class="cfdi-add-line cfdi-add-line-pro" id="btnAddConceptInline">
                        + Agregar otro concepto
                    </button>

                    <div class="cfdi-concepts-footer">
                        <div class="cfdi-concepts-tip">
                            <span>IA</span>
                            <p>Describe claramente lo que vendes.</p>
                        </div>

                        <div class="cfdi-mini-total" id="calcPreview">
                            Subtotal: $0.00 · IVA: $0.00 · Total: $0.00
                        </div>
                    </div>
                </details>

                <details class="cfdi-card cfdi-card-principal cfdi-adenda-card" id="adenda">
                    <summary class="cfdi-card-head">
                        <div>
                            <span class="cfdi-section-label">Opcional</span>
                            <h2>Adenda</h2>
                            <p>Datos comerciales.</p>
                        </div>

                        <label class="cfdi-adenda-switch" onclick="event.stopPropagation();">
                            <input type="checkbox" name="adenda_activa" id="adenda_activa" value="1" @checked(old('adenda_activa'))>
                            <span>Usar adenda</span>
                        </label>
                    </summary>

                    <div class="cfdi-adenda-body" id="adendaBody" hidden>
                        <div class="cfdi-grid three">
                            <label class="floating-field">
                                <span>Tipo</span>
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
                                <span>Orden compra</span>
                                <input type="text" name="adenda[orden_compra]" id="adenda_orden_compra" value="{{ old('adenda.orden_compra') }}" maxlength="120">
                            </label>

                            <label class="floating-field">
                                <span>No. proveedor</span>
                                <input type="text" name="adenda[numero_proveedor]" id="adenda_numero_proveedor" value="{{ old('adenda.numero_proveedor') }}" maxlength="120">
                            </label>

                            <label class="floating-field">
                                <span>No. tienda</span>
                                <input type="text" name="adenda[numero_tienda]" value="{{ old('adenda.numero_tienda') }}" maxlength="120">
                            </label>

                            <label class="floating-field">
                                <span>GLN</span>
                                <input type="text" name="adenda[gln]" value="{{ old('adenda.gln') }}" maxlength="120">
                            </label>

                            <label class="floating-field">
                                <span>Referencia</span>
                                <input type="text" name="adenda[referencia_entrega]" value="{{ old('adenda.referencia_entrega') }}" maxlength="160">
                            </label>

                            <label class="floating-field">
                                <span>Contrato</span>
                                <input type="text" name="adenda[contrato]" value="{{ old('adenda.contrato') }}" maxlength="160">
                            </label>

                            <label class="floating-field">
                                <span>Centro costos</span>
                                <input type="text" name="adenda[centro_costos]" value="{{ old('adenda.centro_costos') }}" maxlength="160">
                            </label>

                            <label class="floating-field">
                                <span>Fecha entrega</span>
                                <input type="date" name="adenda[fecha_entrega]" value="{{ old('adenda.fecha_entrega') }}">
                            </label>

                            <label class="floating-field span-3">
                                <span>Notas</span>
                                <textarea name="adenda[observaciones]" rows="3" maxlength="1000">{{ old('adenda.observaciones') }}</textarea>
                            </label>
                        </div>
                    </div>
                </details>

                <details class="cfdi-card" id="revision" >
                    <summary class="cfdi-card-head">
                        <div>
                            <span class="cfdi-section-label">Paso 5</span>
                            <h2>Revisar y timbrar</h2>
                            <p>Última validación.</p>
                        </div>

                        <div class="cfdi-head-actions">
                            <span class="cfdi-review-badge">Validación activa</span>
                        </div>
                    </summary>

                    <div class="cfdi-review-grid">
                        <div class="review-item" data-review="emisor">Emisor pendiente</div>
                        <div class="review-item" data-review="receptor">Cliente pendiente</div>
                        <div class="review-item" data-review="conceptos">Conceptos pendientes</div>
                        <div class="review-item" data-review="pago">Tipo / pago pendiente</div>
                    </div>

                    <div class="cfdi-smart-card">
                        <strong>Al timbrar</strong>
                        <span>Genera XML/PDF, envía correo y descuenta 1 timbre.</span>
                    </div>

                    <div class="cfdi-grid two" style="margin-top:14px;">
                        <label class="floating-field">
                            <span>Correo</span>
                            <input type="email" name="correo_receptor" id="correo_receptor" value="{{ old('correo_receptor') }}" placeholder="cliente@empresa.com">
                        </label>

                        <label class="floating-field">
                            <span>Mensaje</span>
                            <input type="text" name="mensaje_correo" id="mensaje_correo" value="{{ old('mensaje_correo') }}" placeholder="Adjunto CFDI PDF/XML">
                        </label>
                    </div>

                    <label class="comp-pill active" style="margin-top:14px;">
                        <input type="checkbox" name="enviar_correo" id="enviar_correo" value="1" checked>
                        <span>✉</span>
                        <b>Enviar por correo después de timbrar</b>
                    </label>
                </details>

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
                            Vista previa
                        </button>

                        <button type="submit"
                                name="accion_cfdi"
                                value="borrador"
                                class="cfdi-btn primary full"
                                data-cfdi-action="borrador">
                            Guardar borrador
                        </button>

                        <button type="submit"
                                name="accion_cfdi"
                                value="timbrar"
                                class="cfdi-btn success full"
                                id="btnTimbrarSide"
                                data-cfdi-action="timbrar">
                            Timbrar CFDI
                        </button>
                    </div>
                </div>

                <div class="cfdi-ai-card">
                    <div class="cfdi-side-title">
                        <strong>IA fiscal</strong>
                        <small>Qué falta para timbrar</small>
                    </div>

                    <div class="cfdi-side-actions" style="margin-bottom:12px;">
                        <button type="button" class="cfdi-btn ghost full" data-ai-fill>
                            IA llenar concepto
                        </button>

                        <button type="button" class="cfdi-btn primary full" data-ai-fiscal>
                            Validar CFDI
                        </button>
                    </div>

                    <div class="cfdi-ai-list" id="aiList">
                        <div class="ai-item warn">Selecciona emisor.</div>
                        <div class="ai-item warn">Selecciona cliente.</div>
                        <div class="ai-item warn">Agrega conceptos.</div>
                        <div class="ai-item warn">Define tipo de CFDI.</div>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</form>
</div>

<div class="cfdi-product-modal" id="productModal" aria-hidden="true">
    <div class="cfdi-product-modal-backdrop" data-close-product-modal></div>

    <div class="cfdi-product-modal-dialog">
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
        empleadosNomina: @json($empleadosNominaJs),
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