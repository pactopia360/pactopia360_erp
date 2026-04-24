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

    $emisores = collect($emisores ?? []);
    $receptores = collect($receptores ?? []);
    $productos = collect($productos ?? []);

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
                        Emisión manual preparada para CFDI 4.0, PUE/PPD, conceptos, impuestos,
                        vista previa, clonado futuro, REP y validaciones fiscales.
                    </p>
                </div>
            </div>

            <div class="fc360-tags">
                <span>CFDI 4.0</span>
                <span>Ingreso</span>
                <span>PPD / REP</span>
                <span>Validación previa</span>
                <span>Borrador fiscal</span>
            </div>
        </div>

        <aside class="fc360-hero__side">
            <div class="fc360-sidecard">
                <span class="fc360-sidecard__label">Estado</span>
                <strong>Borrador</strong>
                <small>Sin timbrar</small>
            </div>

            <div class="fc360-sidecard">
                <span class="fc360-sidecard__label">Acción</span>
                <strong>Guardar</strong>
                <small>Después conectamos PAC/timbres</small>
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

    <form method="POST" action="{{ $rtStore }}" id="fc360CreateForm" class="fc360-form" autocomplete="off">
        @csrf

        <input type="hidden" name="tipo_comprobante" value="I">
        <input type="hidden" name="version_cfdi" value="4.0">

        <section class="fc360-grid fc360-grid--2">
            <article class="fc360-card">
                <div class="fc360-card__head">
                    <div>
                        <h2>1. Emisor</h2>
                        <p>Empresa que emite el CFDI.</p>
                    </div>
                    <span class="fc360-badge">Requerido</span>
                </div>

                <div class="fc360-field">
                    <label for="cliente_id">Emisor</label>
                    <select id="cliente_id" name="cliente_id" required>
                        <option value="">Selecciona emisor</option>
                        @foreach($emisores as $e)
                            <option value="{{ $e->id }}" @selected((string) old('cliente_id') === (string) $e->id)>
                                {{ $e->nombre_comercial ?: ($e->razon_social ?: ('Emisor #' . $e->id)) }}
                                @if(!empty($e->rfc))
                                    · {{ $e->rfc }}
                                @endif
                            </option>
                        @endforeach
                    </select>

                    @if($emisores->isEmpty())
                        <small class="fc360-help fc360-help--warn">
                            No hay emisores disponibles para esta cuenta.
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
            </article>

            <article class="fc360-card">
                <div class="fc360-card__head">
                    <div>
                        <h2>2. Receptor</h2>
                        <p>Cliente fiscal que recibirá el CFDI.</p>
                    </div>
                    <span class="fc360-badge">SAT</span>
                </div>

                <div class="fc360-field">
                    <label for="receptor_id">Receptor</label>
                    <select id="receptor_id" name="receptor_id" required>
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

                    @if($receptores->isEmpty())
                        <small class="fc360-help fc360-help--warn">
                            No hay receptores cargados. Después conectamos alta rápida desde esta pantalla.
                        </small>
                    @endif
                </div>

                <div class="fc360-grid fc360-grid--2">
                    <div class="fc360-field">
                        <label for="uso_cfdi">Uso CFDI</label>
                        <select id="uso_cfdi" name="uso_cfdi">
                            <option value="G03" @selected(old('uso_cfdi', 'G03') === 'G03')>G03 · Gastos en general</option>
                            <option value="G01" @selected(old('uso_cfdi') === 'G01')>G01 · Adquisición de mercancías</option>
                            <option value="I01" @selected(old('uso_cfdi') === 'I01')>I01 · Construcciones</option>
                            <option value="I04" @selected(old('uso_cfdi') === 'I04')>I04 · Equipo de cómputo</option>
                            <option value="D01" @selected(old('uso_cfdi') === 'D01')>D01 · Honorarios médicos</option>
                            <option value="CP01" @selected(old('uso_cfdi') === 'CP01')>CP01 · Pagos</option>
                            <option value="S01" @selected(old('uso_cfdi') === 'S01')>S01 · Sin efectos fiscales</option>
                        </select>
                    </div>

                    <div class="fc360-field">
                        <label for="regimen_receptor">Régimen receptor</label>
                        <input id="regimen_receptor" name="regimen_receptor" type="text" value="{{ old('regimen_receptor') }}" placeholder="Ej. 601, 612, 626">
                    </div>
                </div>

                <div class="fc360-field">
                    <label for="cp_receptor">Código postal fiscal</label>
                    <input id="cp_receptor" name="cp_receptor" type="text" maxlength="5" value="{{ old('cp_receptor') }}" placeholder="00000">
                </div>
            </article>
        </section>

        <section class="fc360-card">
            <div class="fc360-card__head">
                <div>
                    <h2>3. Datos del comprobante</h2>
                    <p>Define el tipo, método, forma de pago y reglas PUE/PPD.</p>
                </div>
                <span class="fc360-badge fc360-badge--blue">CFDI 4.0</span>
            </div>

            <div class="fc360-grid fc360-grid--4">
                <div class="fc360-field">
                    <label for="tipo_documento">Tipo CFDI</label>
                    <select id="tipo_documento" name="tipo_documento">
                        <option value="I" @selected(old('tipo_documento', 'I') === 'I')>Ingreso</option>
                        <option value="E" @selected(old('tipo_documento') === 'E')>Egreso / Nota de crédito</option>
                        <option value="T" @selected(old('tipo_documento') === 'T')>Traslado</option>
                        <option value="P" @selected(old('tipo_documento') === 'P')>Pago / REP</option>
                    </select>
                </div>

                <div class="fc360-field">
                    <label for="metodo_pago">Método de pago</label>
                    <select id="metodo_pago" name="metodo_pago">
                        <option value="PUE" @selected(old('metodo_pago', 'PUE') === 'PUE')>PUE · Pago en una exhibición</option>
                        <option value="PPD" @selected(old('metodo_pago') === 'PPD')>PPD · Pago en parcialidades/diferido</option>
                    </select>
                </div>

                <div class="fc360-field">
                    <label for="forma_pago">Forma de pago</label>
                    <select id="forma_pago" name="forma_pago">
                        <option value="03" @selected(old('forma_pago', '03') === '03')>03 · Transferencia</option>
                        <option value="01" @selected(old('forma_pago') === '01')>01 · Efectivo</option>
                        <option value="02" @selected(old('forma_pago') === '02')>02 · Cheque nominativo</option>
                        <option value="04" @selected(old('forma_pago') === '04')>04 · Tarjeta de crédito</option>
                        <option value="28" @selected(old('forma_pago') === '28')>28 · Tarjeta de débito</option>
                        <option value="99" @selected(old('forma_pago') === '99')>99 · Por definir</option>
                    </select>
                </div>

                <div class="fc360-field">
                    <label for="condiciones_pago">Condiciones</label>
                    <input id="condiciones_pago" name="condiciones_pago" type="text" value="{{ old('condiciones_pago') }}" placeholder="Contado / 30 días">
                </div>
            </div>

            <div class="fc360-notice" id="fc360PpdNotice">
                <strong>Regla PPD:</strong>
                Si el CFDI queda como PPD, esta factura deberá permitir registrar pagos y generar su Complemento de Pago REP 2.0.
            </div>
        </section>

        <section class="fc360-card">
            <div class="fc360-card__head">
                <div>
                    <h2>4. Conceptos</h2>
                    <p>Productos/servicios, impuestos, subtotales y total del CFDI.</p>
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
                            </td>

                            <td>
                                <textarea name="conceptos[0][descripcion]" rows="2" required placeholder="Descripción del concepto">{{ old('conceptos.0.descripcion') }}</textarea>
                            </td>

                            <td>
                                <input name="conceptos[0][cantidad]" type="number" min="0.0001" step="0.0001" value="{{ old('conceptos.0.cantidad', '1') }}" required data-cantidad>
                            </td>

                            <td>
                                <input name="conceptos[0][precio_unitario]" type="number" min="0" step="0.0001" value="{{ old('conceptos.0.precio_unitario', '0') }}" required data-precio>
                            </td>

                            <td>
                                <select name="conceptos[0][iva_tasa]" data-iva>
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
                        <p>Preparado para sustituciones, notas de crédito y UUID relacionados.</p>
                    </div>
                </div>

                <div class="fc360-field">
                    <label for="tipo_relacion">Tipo relación</label>
                    <select id="tipo_relacion" name="tipo_relacion">
                        <option value="">Sin relación</option>
                        <option value="01" @selected(old('tipo_relacion') === '01')>01 · Nota de crédito</option>
                        <option value="04" @selected(old('tipo_relacion') === '04')>04 · Sustitución CFDI previo</option>
                        <option value="07" @selected(old('tipo_relacion') === '07')>07 · Aplicación anticipo</option>
                    </select>
                </div>

                <div class="fc360-field">
                    <label for="uuid_relacionado">UUID relacionado</label>
                    <input id="uuid_relacionado" name="uuid_relacionado" type="text" value="{{ old('uuid_relacionado') }}" placeholder="UUID origen o sustituido">
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
                    <h2>7. Observaciones e IA fiscal</h2>
                    <p>Base para alertas, explicación de errores SAT/PAC y revisión previa.</p>
                </div>
                <span class="fc360-badge fc360-badge--green">IA fiscal</span>
            </div>

            <div class="fc360-grid fc360-grid--2">
                <div class="fc360-field">
                    <label for="observaciones">Observaciones internas</label>
                    <textarea id="observaciones" name="observaciones" rows="4" placeholder="Notas internas, referencia de venta, pedido, orden de compra...">{{ old('observaciones') }}</textarea>
                </div>

                <div class="fc360-checklist">
                    <div class="fc360-checkitem" data-check-emisor>
                        <span></span>
                        Emisor seleccionado
                    </div>
                    <div class="fc360-checkitem" data-check-receptor>
                        <span></span>
                        Receptor seleccionado
                    </div>
                    <div class="fc360-checkitem" data-check-conceptos>
                        <span></span>
                        Conceptos válidos
                    </div>
                    <div class="fc360-checkitem" data-check-ppd>
                        <span></span>
                        Regla PUE/PPD revisada
                    </div>
                </div>
            </div>
        </section>

        <div class="fc360-actions">
            <a href="{{ $rtIndex }}" class="fc360-btn fc360-btn--ghost">Cancelar</a>

            <button type="button" class="fc360-btn fc360-btn--soft" data-preview-btn>
                Vista previa
            </button>

            <button type="submit" class="fc360-btn fc360-btn--primary">
                Guardar borrador
            </button>
        </div>
    </form>
</div>

<script>
    window.P360_FACTURACION_CREATE = {
        productos: @json($productosJs),
        routes: {
            index: @json($rtIndex),
            store: @json($rtStore)
        }
    };
</script>
@endsection

@push('scripts')
    <script src="{{ asset('assets/client/js/pages/facturacion-create-360.js') }}?v={{ file_exists(public_path('assets/client/js/pages/facturacion-create-360.js')) ? filemtime(public_path('assets/client/js/pages/facturacion-create-360.js')) : time() }}" defer></script>
@endpush