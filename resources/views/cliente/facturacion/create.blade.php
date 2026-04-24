{{-- resources/views/cliente/facturacion/create.blade.php --}}
@extends('layouts.cliente')

@section('title', 'Nuevo CFDI · Pactopia360')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/client/css/pages/facturacion-create-360.css') }}?v={{ file_exists(public_path('assets/client/css/pages/facturacion-create-360.css')) ? filemtime(public_path('assets/client/css/pages/facturacion-create-360.css')) : time() }}">
@endpush

@section('content')
@php
    use Illuminate\Support\Facades\Route;

    $rtIndex = Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : url('/cliente/facturacion');
    $rtStore = Route::has('cliente.facturacion.store') ? route('cliente.facturacion.store') : url('/cliente/facturacion');

    $rtReceptorStore = Route::has('cliente.facturacion.receptores.store') ? route('cliente.facturacion.receptores.store') : url('/cliente/facturacion/receptores');
    $rtReceptorShowBase = url('/cliente/facturacion/receptores');
    $rtReceptorUpdateBase = url('/cliente/facturacion/receptores');

    $rtAssistant = Route::has('cliente.facturacion.assistant') ? route('cliente.facturacion.assistant') : url('/cliente/facturacion/assistant');
    $rtCatalogs = Route::has('cliente.facturacion.catalogs') ? route('cliente.facturacion.catalogs') : url('/cliente/facturacion/catalogs');
    $rtPostalCodeBase = url('/cliente/facturacion/postal-code');

    $rtCountries = Route::has('cliente.facturacion.locations.countries') ? route('cliente.facturacion.locations.countries') : url('/cliente/facturacion/locations/countries');
$rtStates = Route::has('cliente.facturacion.locations.states') ? route('cliente.facturacion.locations.states') : url('/cliente/facturacion/locations/states');
$rtMunicipalities = Route::has('cliente.facturacion.locations.municipalities') ? route('cliente.facturacion.locations.municipalities') : url('/cliente/facturacion/locations/municipalities');
$rtColonies = Route::has('cliente.facturacion.locations.colonies') ? route('cliente.facturacion.locations.colonies') : url('/cliente/facturacion/locations/colonies');

    $emisores = collect($emisores ?? []);
    $receptores = collect($receptores ?? []);
    $productos = collect($productos ?? []);

    $catalogs = $fiscalCatalogs ?? [];
    $regimenes = $catalogs['regimenes_fiscales'] ?? [];
    $usosCfdi = $catalogs['usos_cfdi'] ?? [];
    $formasPago = $catalogs['formas_pago'] ?? [];
    $metodosPago = $catalogs['metodos_pago'] ?? [];
    $tiposCfdi = $catalogs['tipos_cfdi'] ?? [];
    $relacionesCfdi = $catalogs['relaciones_cfdi'] ?? [];

    $productosJs = $productos->map(function ($p) {
        $sku = trim((string) ($p->sku ?? ''));
        $descripcion = trim((string) ($p->descripcion ?? ''));

        return [
            'id' => $p->id ?? null,
            'sku' => $sku,
            'label' => trim(($sku !== '' ? $sku . ' - ' : '') . $descripcion),
            'descripcion' => $descripcion,
            'precio_unitario' => (float) ($p->precio_unitario ?? 0),
            'iva_tasa' => is_numeric($p->iva_tasa ?? null) ? (float) $p->iva_tasa : 0.16,
        ];
    })->values();

    $receptoresJs = $receptores->map(function ($r) {
        return [
            'id' => $r->id ?? null,
            'rfc' => (string) ($r->rfc ?? ''),
            'razon_social' => (string) ($r->razon_social ?? ''),
            'nombre_comercial' => (string) ($r->nombre_comercial ?? ''),
            'uso_cfdi' => (string) ($r->uso_cfdi ?? ''),
            'forma_pago' => (string) ($r->forma_pago ?? ''),
            'metodo_pago' => (string) ($r->metodo_pago ?? ''),
            'regimen_fiscal' => (string) ($r->regimen_fiscal ?? ''),
            'codigo_postal' => (string) ($r->codigo_postal ?? ''),
            'pais' => (string) ($r->pais ?? 'MEX'),
            'estado' => (string) ($r->estado ?? ''),
            'municipio' => (string) ($r->municipio ?? ''),
            'colonia' => (string) ($r->colonia ?? ''),
            'calle' => (string) ($r->calle ?? ''),
            'no_ext' => (string) ($r->no_ext ?? ''),
            'no_int' => (string) ($r->no_int ?? ''),
            'email' => (string) ($r->email ?? ''),
            'telefono' => (string) ($r->telefono ?? ''),
            'label' => trim(($r->razon_social ?: ($r->nombre_comercial ?: 'Receptor #' . $r->id)) . (!empty($r->rfc) ? ' · ' . $r->rfc : '')),
        ];
    })->values();

    $today = now()->format('Y-m-d\TH:i');
@endphp

<div class="fc360-create" data-fc360-create>
    <section class="fc360-hero">
        <div class="fc360-hero__main">
            <div class="fc360-pill">
                <span class="fc360-pill__dot"></span>
                CFDI · FACTURACIÓN 360
            </div>

            <div class="fc360-hero__titlewrap">
                <div class="fc360-hero__icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M7 3h10a2 2 0 0 1 2 2v16l-3-2-3 2-3-2-3 2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
                        <path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                    </svg>
                </div>

                <div>
                    <h1>Nuevo CFDI</h1>
                    <p>
                        Asistente fiscal inteligente para generar CFDI 4.0 con validaciones, sugerencias,
                        autollenado, reglas PUE/PPD, preparación REP y prevención de errores SAT/PAC.
                    </p>
                </div>
            </div>

            <div class="fc360-tags">
                <span>CFDI 4.0</span>
                <span>IA fiscal</span>
                <span>Autollenado SAT</span>
                <span>PPD / REP</span>
                <span>Checklist inteligente</span>
            </div>
        </div>

        <aside class="fc360-hero__side">
            <div class="fc360-sidecard">
                <span class="fc360-sidecard__label">Estado</span>
                <strong>Borrador</strong>
                <small>Sin timbrar</small>
            </div>

            <div class="fc360-sidecard">
                <span class="fc360-sidecard__label">Asistente</span>
                <strong id="fc360AiHeroStatus">Listo</strong>
                <small id="fc360AiHeroText">Revisando datos fiscales</small>
            </div>

            <a href="{{ $rtIndex }}" class="fc360-btn fc360-btn--ghost">
                ← Volver
            </a>
        </aside>
    </section>

    @if ($errors->any())
        <div class="fc360-alert fc360-alert--danger">
            <strong>Revisa la información:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="fc360-ai-strip" data-ai-panel>
        <div class="fc360-ai-strip__icon">IA</div>
        <div class="fc360-ai-strip__body">
            <strong id="fc360AiTitle">Asistente fiscal activo</strong>
            <span id="fc360AiMessage">
                Te ayudará a evitar errores de RFC, régimen fiscal, código postal, PUE/PPD, uso CFDI y complemento de pago.
            </span>
        </div>
        <div class="fc360-ai-strip__score">
            <span id="fc360AiScore">100</span>
            <small>score fiscal</small>
        </div>
    </section>

    <form method="POST" action="{{ $rtStore }}" id="fc360CreateForm" class="fc360-form" autocomplete="off">
        @csrf

        <input type="hidden" name="version_cfdi" value="4.0">

        <section class="fc360-grid fc360-grid--2">
            <article class="fc360-card">
                <div class="fc360-card__head">
                    <div>
                        <h2>1. Emisor</h2>
                        <p>Selecciona el RFC emisor centralizado desde el portal SAT/RFC.</p>
                    </div>
                    <span class="fc360-badge">Centralizado</span>
                </div>

                <div class="fc360-field">
                    <label for="cliente_id">Emisor RFC</label>
                    <select id="cliente_id" name="cliente_id" required>
                        <option value="">Selecciona emisor</option>
                        @foreach($emisores as $e)
                            <option value="{{ $e->id }}" @selected((string) old('cliente_id') === (string) $e->id)>
                                {{ $e->nombre_comercial ?: ($e->razon_social ?: ($e->rfc ?: ('Emisor #' . $e->id))) }}
                                @if(!empty($e->rfc))
                                    · {{ $e->rfc }}
                                @endif
                            </option>
                        @endforeach
                    </select>

                    @if($emisores->isEmpty())
                        <small class="fc360-help fc360-help--warn">
                            No hay emisores activos. Registra o activa tus RFC en el portal RFC/SAT.
                        </small>
                    @else
                        <small class="fc360-help">
                            Los emisores no se duplican aquí; se toman del catálogo RFC central.
                        </small>
                    @endif
                </div>

                <div class="fc360-grid fc360-grid--2">
                    <div class="fc360-field">
                        <label for="serie">Serie</label>
                        <input id="serie" name="serie" type="text" maxlength="10" value="{{ old('serie', 'A') }}" placeholder="A">
                    </div>

                    <div class="fc360-field">
                        <label for="folio">Folio</label>
                        <input id="folio" name="folio" type="text" maxlength="20" value="{{ old('folio') }}" placeholder="Automático / 000001">
                    </div>
                </div>

                <div class="fc360-grid fc360-grid--2">
                    <div class="fc360-field">
                        <label for="fecha">Fecha</label>
                        <input id="fecha" name="fecha" type="datetime-local" value="{{ old('fecha', $today) }}">
                    </div>

                    <div class="fc360-field">
                        <label for="moneda">Moneda</label>
                        <select id="moneda" name="moneda">
                            <option value="MXN" @selected(old('moneda', 'MXN') === 'MXN')>MXN · Peso mexicano</option>
                            <option value="USD" @selected(old('moneda') === 'USD')>USD · Dólar americano</option>
                            <option value="EUR" @selected(old('moneda') === 'EUR')>EUR · Euro</option>
                        </select>
                    </div>
                </div>

                <div class="fc360-field">
                    <label for="tipo_cambio">Tipo de cambio</label>
                    <input id="tipo_cambio" name="tipo_cambio" type="number" min="0" step="0.000001" value="{{ old('tipo_cambio') }}" placeholder="Solo si no es MXN">
                </div>
            </article>

            <article class="fc360-card">
                <div class="fc360-card__head">
                    <div>
                        <h2>2. Receptor</h2>
                        <p>Selecciona, agrega o edita el cliente fiscal sin salir del CFDI.</p>
                    </div>

                    <div class="fc360-head-actions">
                        <button type="button" class="fc360-btn fc360-btn--soft" data-receptor-new>
                            + Agregar
                        </button>

                        <button type="button" class="fc360-btn fc360-btn--ghost" data-receptor-edit>
                            Editar
                        </button>
                    </div>
                </div>

                <div class="fc360-field">
                    <label for="receptor_id">Receptor</label>
                    <select id="receptor_id" name="receptor_id" required data-receptor-select>
                        <option value="">Selecciona receptor</option>
                        @foreach($receptores as $r)
                            <option value="{{ $r->id }}" @selected((string) old('receptor_id') === (string) $r->id)>
                                {{ $r->razon_social ?: ($r->nombre_comercial ?: ('Receptor #' . $r->id)) }}
                                @if(!empty($r->rfc))
                                    · {{ $r->rfc }}
                                @endif
                            </option>
                        @endforeach
                    </select>

                    <small class="fc360-help">
                        Al seleccionar receptor, Pactopia360 llenará uso CFDI, régimen, CP, método y forma frecuentes.
                    </small>
                </div>

                <div class="fc360-grid fc360-grid--2">
                    <div class="fc360-field">
                        <label for="uso_cfdi">Uso CFDI</label>
                        <select id="uso_cfdi" name="uso_cfdi" data-ai-watch>
                            <option value="">Selecciona uso CFDI</option>
                            @foreach($usosCfdi as $code => $label)
                                <option value="{{ $code }}" @selected(old('uso_cfdi', 'G03') === $code)>
                                    {{ $code }} · {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fc360-field">
                        <label for="regimen_receptor">Régimen receptor</label>
                        <select id="regimen_receptor" name="regimen_receptor" data-ai-watch>
                            <option value="">Selecciona régimen</option>
                            @foreach($regimenes as $code => $label)
                                <option value="{{ $code }}" @selected(old('regimen_receptor') === $code)>
                                    {{ $code }} · {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="fc360-grid fc360-grid--2">
                    <div class="fc360-field">
                        <label for="cp_receptor">Código postal fiscal</label>
                        <input id="cp_receptor" name="cp_receptor" type="text" maxlength="5" value="{{ old('cp_receptor') }}" placeholder="00000" data-ai-watch>
                    </div>

                    <div class="fc360-field">
                        <label>Ficha fiscal inteligente</label>
                        <div class="fc360-receptor-card" data-receptor-card>
                            <strong>Sin receptor seleccionado</strong>
                            <span>Selecciona o agrega un receptor para validar datos fiscales.</span>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="fc360-card">
            <div class="fc360-card__head">
                <div>
                    <h2>3. Datos del comprobante</h2>
                    <p>Define tipo CFDI, método, forma de pago y reglas inteligentes PUE/PPD.</p>
                </div>
                <span class="fc360-badge fc360-badge--blue">CFDI 4.0</span>
            </div>

            <div class="fc360-grid fc360-grid--4">
                <div class="fc360-field">
                    <label for="tipo_documento">Tipo CFDI</label>
                    <select id="tipo_documento" name="tipo_documento" data-ai-watch>
                        @foreach($tiposCfdi as $code => $label)
                            <option value="{{ $code }}" @selected(old('tipo_documento', 'I') === $code)>
                                {{ $code }} · {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="fc360-field">
                    <label for="metodo_pago">Método de pago</label>
                    <select id="metodo_pago" name="metodo_pago" data-ai-watch>
                        @foreach($metodosPago as $code => $label)
                            <option value="{{ $code }}" @selected(old('metodo_pago', 'PUE') === $code)>
                                {{ $code }} · {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="fc360-field">
                    <label for="forma_pago">Forma de pago</label>
                    <select id="forma_pago" name="forma_pago" data-ai-watch>
                        @foreach($formasPago as $code => $label)
                            <option value="{{ $code }}" @selected(old('forma_pago', '03') === $code)>
                                {{ $code }} · {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="fc360-field">
                    <label for="condiciones_pago">Condiciones</label>
                    <input id="condiciones_pago" name="condiciones_pago" type="text" value="{{ old('condiciones_pago') }}" placeholder="Contado / 30 días">
                </div>
            </div>

            <div class="fc360-notice" id="fc360PpdNotice">
                <strong>Regla inteligente PPD:</strong>
                Si el CFDI queda como PPD, el sistema forzará forma 99 y preparará la factura para Complemento de Pago REP 2.0.
            </div>
        </section>

        <section class="fc360-card">
            <div class="fc360-card__head">
                <div>
                    <h2>4. Conceptos</h2>
                    <p>Productos/servicios, claves SAT futuras, objeto impuesto, descuentos, IVA y total.</p>
                </div>

                <button type="button" class="fc360-btn fc360-btn--soft" data-add-concept>
                    + Agregar concepto
                </button>
            </div>

            <div class="fc360-tablewrap">
                <table class="fc360-table" id="fc360ConceptTable">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Descripción</th>
                            <th>Cant.</th>
                            <th>Precio</th>
                            <th>Desc.</th>
                            <th>IVA</th>
                            <th>Subtotal</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody id="fc360ConceptBody">
                        <tr data-concept-row>
                            <td>
                                <select name="conceptos[0][producto_id]" data-product-select>
                                    <option value="">Manual</option>
                                    @foreach($productos as $p)
                                        @php
                                            $sku = trim((string) ($p->sku ?? ''));
                                            $desc = trim((string) ($p->descripcion ?? ''));
                                            $label = trim(($sku !== '' ? $sku . ' - ' : '') . $desc);
                                        @endphp
                                        <option value="{{ $p->id }}">
                                            {{ $label !== '' ? $label : 'Producto #' . $p->id }}
                                        </option>
                                    @endforeach
                                </select>

                                <input type="hidden" name="conceptos[0][clave_producto_sat]" value="">
                                <input type="hidden" name="conceptos[0][clave_unidad_sat]" value="">
                                <input type="hidden" name="conceptos[0][objeto_impuesto]" value="02">
                            </td>

                            <td>
                                <textarea name="conceptos[0][descripcion]" rows="2" required placeholder="Descripción del concepto" data-ai-watch>{{ old('conceptos.0.descripcion') }}</textarea>
                            </td>

                            <td>
                                <input name="conceptos[0][cantidad]" type="number" min="0.0001" step="0.0001" value="{{ old('conceptos.0.cantidad', '1') }}" required data-cantidad data-ai-watch>
                            </td>

                            <td>
                                <input name="conceptos[0][precio_unitario]" type="number" min="0" step="0.0001" value="{{ old('conceptos.0.precio_unitario', '0') }}" required data-precio data-ai-watch>
                            </td>

                            <td>
                                <input name="conceptos[0][descuento]" type="number" min="0" step="0.0001" value="{{ old('conceptos.0.descuento', '0') }}" data-descuento data-ai-watch>
                            </td>

                            <td>
                                <select name="conceptos[0][iva_tasa]" data-iva data-ai-watch>
                                    <option value="0.16" @selected(old('conceptos.0.iva_tasa', '0.16') == '0.16')>16%</option>
                                    <option value="0.08" @selected(old('conceptos.0.iva_tasa') == '0.08')>8%</option>
                                    <option value="0" @selected(old('conceptos.0.iva_tasa') == '0')>0%</option>
                                </select>
                            </td>

                            <td data-subtotal>$0.00</td>
                            <td data-total>$0.00</td>

                            <td>
                                <button type="button" class="fc360-iconbtn" data-remove-concept title="Eliminar concepto">
                                    ×
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if($productos->isEmpty())
                <div class="fc360-notice fc360-notice--muted">
                    Todavía no hay catálogo de productos. Puedes capturar conceptos manualmente.
                </div>
            @endif
        </section>

        <section class="fc360-grid fc360-grid--3">
            <article class="fc360-card fc360-card--compact">
                <div class="fc360-card__head">
                    <div>
                        <h2>5. Relación CFDI</h2>
                        <p>Sustituciones, notas de crédito y UUID relacionados.</p>
                    </div>
                </div>

                <div class="fc360-field">
                    <label for="tipo_relacion">Tipo relación</label>
                    <select id="tipo_relacion" name="tipo_relacion" data-ai-watch>
                        <option value="">Sin relación</option>
                        @foreach($relacionesCfdi as $code => $label)
                            <option value="{{ $code }}" @selected(old('tipo_relacion') === $code)>
                                {{ $code }} · {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="fc360-field">
                    <label for="uuid_relacionado">UUID relacionado</label>
                    <input id="uuid_relacionado" name="uuid_relacionado" type="text" value="{{ old('uuid_relacionado') }}" placeholder="UUID origen o sustituido" data-ai-watch>
                </div>
            </article>

            <article class="fc360-card fc360-card--compact">
                <div class="fc360-card__head">
                    <div>
                        <h2>6. Complemento de pago</h2>
                        <p>Control futuro para facturas PPD y pagos relacionados.</p>
                    </div>
                    <span class="fc360-badge fc360-badge--blue">REP</span>
                </div>

                <div class="fc360-repbox">
                    <strong id="fc360RepStatus">No requerido por ahora</strong>
                    <span id="fc360RepText">
                        Si eliges PPD, este CFDI quedará listo para registrar pagos y generar REP.
                    </span>
                </div>
            </article>

            <article class="fc360-card fc360-card--compact fc360-summary">
                <div class="fc360-card__head">
                    <div>
                        <h2>Resumen</h2>
                        <p>Validación visual antes de guardar.</p>
                    </div>
                </div>

                <div class="fc360-summary__line">
                    <span>Subtotal</span>
                    <strong id="fc360Subtotal">$0.00</strong>
                </div>

                <div class="fc360-summary__line">
                    <span>Descuento</span>
                    <strong id="fc360Descuento">$0.00</strong>
                </div>

                <div class="fc360-summary__line">
                    <span>IVA</span>
                    <strong id="fc360Iva">$0.00</strong>
                </div>

                <div class="fc360-summary__line fc360-summary__line--total">
                    <span>Total</span>
                    <strong id="fc360Total">$0.00</strong>
                </div>
            </article>
        </section>

        <section class="fc360-card">
            <div class="fc360-card__head">
                <div>
                    <h2>7. Checklist fiscal inteligente</h2>
                    <p>El sistema revisa los puntos que normalmente provocan errores fiscales o técnicos.</p>
                </div>
                <span class="fc360-badge fc360-badge--green">IA fiscal</span>
            </div>

            <div class="fc360-grid fc360-grid--2">
                <div class="fc360-field">
                    <label for="observaciones">Observaciones internas</label>
                    <textarea id="observaciones" name="observaciones" rows="4" placeholder="Notas internas, referencia de venta, pedido, orden de compra...">{{ old('observaciones') }}</textarea>
                </div>

                <div class="fc360-checklist">
                    <div class="fc360-checkitem" data-check-emisor><span></span>Emisor seleccionado</div>
                    <div class="fc360-checkitem" data-check-receptor><span></span>Receptor seleccionado</div>
                    <div class="fc360-checkitem" data-check-regimen><span></span>Régimen fiscal válido</div>
                    <div class="fc360-checkitem" data-check-cp><span></span>Código postal fiscal válido</div>
                    <div class="fc360-checkitem" data-check-conceptos><span></span>Conceptos válidos</div>
                    <div class="fc360-checkitem" data-check-ppd><span></span>Regla PUE/PPD revisada</div>
                </div>
            </div>
        </section>

        <div class="fc360-actions">
            <a href="{{ $rtIndex }}" class="fc360-btn fc360-btn--ghost">Cancelar</a>

            <button type="button" class="fc360-btn fc360-btn--soft" data-preview-btn>
                Vista previa inteligente
            </button>

            <button type="submit" class="fc360-btn fc360-btn--primary">
                Guardar borrador
            </button>
        </div>
    </form>

    <div class="fc360-modal" data-receptor-modal aria-hidden="true">
        <div class="fc360-modal__backdrop" data-receptor-close></div>

        <div class="fc360-modal__panel" role="dialog" aria-modal="true" aria-labelledby="fc360ReceptorModalTitle">
            <div class="fc360-modal__head">
                <div>
                    <span class="fc360-pill fc360-pill--modal">Receptor fiscal</span>
                    <h2 id="fc360ReceptorModalTitle">Agregar receptor</h2>
                    <p>El asistente te ayuda con RFC, régimen fiscal, CP, municipio, colonias y datos fiscales frecuentes.</p>
                </div>

                <button type="button" class="fc360-iconbtn" data-receptor-close title="Cerrar">×</button>
            </div>

            <form id="fc360ReceptorForm" class="fc360-modal__body" autocomplete="off">
                <input type="hidden" name="id" id="receptor_modal_id">

                <div class="fc360-smart-help" data-modal-ai-help>
                    <strong>Asistente fiscal</strong>
                    <span>Captura RFC y código postal; Pactopia360 sugerirá régimen, tipo de persona y ubicación.</span>
                </div>

                <div class="fc360-grid fc360-grid--2">
                    <div class="fc360-field">
                        <label for="receptor_modal_rfc">RFC</label>
                        <input id="receptor_modal_rfc" name="rfc" type="text" maxlength="13" required placeholder="RFC receptor" data-modal-ai-watch>
                    </div>

                    <div class="fc360-field">
                        <label for="receptor_modal_razon_social">Razón social</label>
                        <input id="receptor_modal_razon_social" name="razon_social" type="text" maxlength="255" required placeholder="Razón social SAT">
                    </div>
                </div>

                <div class="fc360-grid fc360-grid--2">
                    <div class="fc360-field">
                        <label for="receptor_modal_nombre_comercial">Nombre comercial</label>
                        <input id="receptor_modal_nombre_comercial" name="nombre_comercial" type="text" maxlength="255" placeholder="Opcional">
                    </div>

                    <div class="fc360-field">
                        <label for="receptor_modal_email">Correo</label>
                        <input id="receptor_modal_email" name="email" type="email" maxlength="180" placeholder="correo@cliente.com">
                    </div>
                </div>

                <div class="fc360-grid fc360-grid--3">
                    <div class="fc360-field">
                        <label for="receptor_modal_uso_cfdi">Uso CFDI</label>
                        <select id="receptor_modal_uso_cfdi" name="uso_cfdi" data-modal-ai-watch>
                            @foreach($usosCfdi as $code => $label)
                                <option value="{{ $code }}" @selected($code === 'G03')>
                                    {{ $code }} · {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fc360-field">
                        <label for="receptor_modal_regimen_fiscal">Régimen fiscal</label>
                        <select id="receptor_modal_regimen_fiscal" name="regimen_fiscal" data-modal-ai-watch>
                            <option value="">Selecciona régimen</option>
                            @foreach($regimenes as $code => $label)
                                <option value="{{ $code }}">{{ $code }} · {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fc360-field">
                        <label for="receptor_modal_codigo_postal">Código postal fiscal</label>
                        <input id="receptor_modal_codigo_postal" name="codigo_postal" type="text" maxlength="10" placeholder="00000" data-modal-ai-watch>
                    </div>
                </div>

                <div class="fc360-grid fc360-grid--3">
                    <div class="fc360-field">
                        <label for="receptor_modal_metodo_pago">Método frecuente</label>
                        <select id="receptor_modal_metodo_pago" name="metodo_pago">
                            <option value="">Sin definir</option>
                            @foreach($metodosPago as $code => $label)
                                <option value="{{ $code }}">{{ $code }} · {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fc360-field">
                        <label for="receptor_modal_forma_pago">Forma frecuente</label>
                        <select id="receptor_modal_forma_pago" name="forma_pago">
                            <option value="">Sin definir</option>
                            @foreach($formasPago as $code => $label)
                                <option value="{{ $code }}">{{ $code }} · {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fc360-field">
                        <label for="receptor_modal_telefono">Teléfono</label>
                        <input id="receptor_modal_telefono" name="telefono" type="text" maxlength="40" placeholder="Opcional">
                    </div>
                </div>

                <div class="fc360-grid fc360-grid--3">
                    <div class="fc360-field">
                        <label for="receptor_modal_pais">País</label>
                        <select id="receptor_modal_pais" name="pais" data-location-country>
                            <option value="MEX" selected>MEX · México</option>
                        </select>
                    </div>

                    <div class="fc360-field">
                        <label for="receptor_modal_estado">Estado</label>
                        <select id="receptor_modal_estado" name="estado" data-location-state>
                            <option value="">Selecciona estado</option>
                        </select>
                    </div>

                    <div class="fc360-field">
                        <label for="receptor_modal_municipio">Municipio</label>
                        <select id="receptor_modal_municipio" name="municipio" data-location-municipality>
                            <option value="">Selecciona municipio</option>
                        </select>
                    </div>
                </div>

                <div class="fc360-grid fc360-grid--4">
                    <div class="fc360-field">
                        <label for="receptor_modal_colonia">Colonia</label>
                        <select id="receptor_modal_colonia" name="colonia" data-location-colony>
                            <option value="">Selecciona colonia</option>
                        </select>
                    </div>

                    <div class="fc360-field">
                        <label for="receptor_modal_calle">Calle</label>
                        <input id="receptor_modal_calle" name="calle" type="text" maxlength="180" placeholder="Calle">
                    </div>

                    <div class="fc360-field">
                        <label for="receptor_modal_no_ext">No. ext</label>
                        <input id="receptor_modal_no_ext" name="no_ext" type="text" maxlength="30" placeholder="Exterior">
                    </div>

                    <div class="fc360-field">
                        <label for="receptor_modal_no_int">No. int</label>
                        <input id="receptor_modal_no_int" name="no_int" type="text" maxlength="30" placeholder="Interior">
                    </div>
                </div>

                <div class="fc360-modal__actions">
                    <button type="button" class="fc360-btn fc360-btn--ghost" data-receptor-close>
                        Cancelar
                    </button>

                    <button type="submit" class="fc360-btn fc360-btn--primary" data-receptor-save>
                        Guardar receptor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.P360_FACTURACION_CREATE = {
        csrf: @json(csrf_token()),
        productos: @json($productosJs),
        receptores: @json($receptoresJs),
        catalogs: @json($catalogs),
        routes: {
            index: @json($rtIndex),
            store: @json($rtStore),
            receptorStore: @json($rtReceptorStore),
            receptorShowBase: @json($rtReceptorShowBase),
            receptorUpdateBase: @json($rtReceptorUpdateBase),
            assistant: @json($rtAssistant),
            catalogs: @json($rtCatalogs),
            postalCodeBase: @json($rtPostalCodeBase),
            countries: @json($rtCountries),
            states: @json($rtStates),
            municipalities: @json($rtMunicipalities),
            colonies: @json($rtColonies)
        }
    };
</script>
@endsection

@push('scripts')
    <script src="{{ asset('assets/client/js/pages/facturacion-create-360.js') }}?v={{ file_exists(public_path('assets/client/js/pages/facturacion-create-360.js')) ? filemtime(public_path('assets/client/js/pages/facturacion-create-360.js')) : time() }}" defer></script>
@endpush