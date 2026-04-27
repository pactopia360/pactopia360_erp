{{-- resources/views/cliente/facturacion/show.blade.php --}}
@extends('layouts.cliente')

@section('title', 'Detalle CFDI · Pactopia360')

@push('styles')
<style>
    .fx360-detail-page{
        display:flex;
        flex-direction:column;
        gap:14px;
    }

    .fx360-detail-hero{
        border-radius:22px;
        padding:22px;
        background:
            radial-gradient(circle at 88% 16%, rgba(104,154,255,.45), transparent 32%),
            linear-gradient(135deg,#173b78 0%,#1c4ca3 48%,#5f8df0 100%);
        color:#fff;
        box-shadow:0 18px 38px rgba(20,55,120,.20);
        border:1px solid rgba(255,255,255,.16);
    }

    .fx360-detail-hero__top{
        display:flex;
        justify-content:space-between;
        gap:16px;
        flex-wrap:wrap;
        align-items:flex-start;
    }

    .fx360-detail-kicker{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:7px 11px;
        border-radius:999px;
        background:rgba(255,255,255,.14);
        border:1px solid rgba(255,255,255,.18);
        font-size:11px;
        font-weight:950;
        text-transform:uppercase;
        letter-spacing:.08em;
    }

    .fx360-detail-title{
        margin:14px 0 8px;
        font-size:36px;
        line-height:1;
        font-weight:950;
        letter-spacing:-.05em;
    }

    .fx360-detail-subtitle{
        max-width:760px;
        margin:0;
        color:rgba(255,255,255,.86);
        font-size:13px;
        font-weight:750;
        line-height:1.6;
    }

    .fx360-detail-status{
        min-width:260px;
        border-radius:18px;
        padding:14px;
        background:rgba(255,255,255,.13);
        border:1px solid rgba(255,255,255,.20);
        box-shadow:0 18px 34px rgba(10,24,64,.16);
    }

    .fx360-detail-status__label{
        font-size:10px;
        font-weight:950;
        text-transform:uppercase;
        letter-spacing:.12em;
        color:rgba(255,255,255,.72);
    }

    .fx360-detail-status__value{
        margin-top:7px;
        font-size:18px;
        font-weight:950;
        color:#fff;
    }

    .fx360-detail-status__uuid{
        margin-top:8px;
        font-size:11px;
        line-height:1.45;
        color:rgba(255,255,255,.78);
        word-break:break-all;
        font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
    }

    .fx360-detail-actions{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        flex-wrap:wrap;
        padding:14px;
        border-radius:18px;
        background:#fff;
        border:1px solid #e3ebf7;
        box-shadow:0 10px 24px rgba(15,34,64,.045);
    }

    .fx360-btn{
        height:38px;
        padding:0 13px;
        border-radius:13px;
        border:1px solid #d8e7ff;
        background:linear-gradient(180deg,#fff 0%,#f7fbff 100%);
        color:#2458cf;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        font-size:12px;
        font-weight:950;
        text-decoration:none;
        cursor:pointer;
        transition:.16s ease;
    }

    .fx360-btn:hover{
        transform:translateY(-1px);
        border-color:#9fc1ff;
        background:#eef5ff;
        box-shadow:0 10px 20px rgba(37,88,207,.12);
    }

    .fx360-btn--primary{
        border:0;
        color:#fff;
        background:linear-gradient(135deg,#2458cf,#2f6bff);
        box-shadow:0 10px 22px rgba(37,88,207,.18);
    }

    .fx360-btn--success{
        color:#0f8a4b;
        border-color:#c7f0db;
        background:linear-gradient(180deg,#fff 0%,#f2fff8 100%);
    }

    .fx360-btn--danger{
        color:#dc2626;
        border-color:#ffd6d6;
        background:linear-gradient(180deg,#fff 0%,#fff6f6 100%);
    }

    .fx360-btn--disabled{
        opacity:.45;
        pointer-events:none;
        filter:grayscale(.35);
    }

    .fx360-btn svg{
        width:16px;
        height:16px;
    }

    .fx360-grid-4{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
    }

    .fx360-grid-2{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }

    .fx360-card{
        border-radius:18px;
        border:1px solid #e3ebf7;
        background:linear-gradient(180deg,#fff 0%,#fbfdff 100%);
        box-shadow:0 10px 24px rgba(15,34,64,.045);
        padding:16px;
    }

    .fx360-card__head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        margin-bottom:14px;
    }

    .fx360-card__title{
        margin:0;
        color:#10233f;
        font-size:14px;
        font-weight:950;
        letter-spacing:-.02em;
    }

    .fx360-card__tag{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:5px 9px;
        border-radius:999px;
        background:#eef5ff;
        color:#2458cf;
        border:1px solid #d8e7ff;
        font-size:10px;
        font-weight:950;
        text-transform:uppercase;
        letter-spacing:.08em;
    }

    .fx360-field{
        display:grid;
        gap:4px;
        margin-bottom:11px;
    }

    .fx360-label{
        color:#71829a;
        font-size:10px;
        font-weight:950;
        line-height:1.2;
        letter-spacing:.12em;
        text-transform:uppercase;
    }

    .fx360-value{
        color:#10233f;
        font-size:13px;
        font-weight:850;
        line-height:1.35;
    }

    .fx360-value--big{
        font-size:22px;
        font-weight:950;
        letter-spacing:-.04em;
    }

    .fx360-value--ok{
        color:#0f8a4b;
    }

    .fx360-value--warn{
        color:#b77900;
    }

    .fx360-value--blue{
        color:#2458cf;
    }

    .fx360-kpi{
        min-height:106px;
        position:relative;
        overflow:hidden;
    }

    .fx360-kpi::after{
        content:"";
        position:absolute;
        left:12px;
        right:12px;
        bottom:0;
        height:3px;
        border-radius:999px;
        background:linear-gradient(90deg,#2563eb,#38bdf8);
    }

    .fx360-table-wrap{
        overflow:auto;
        border-radius:16px;
        border:1px solid #e3ebf7;
        background:#fff;
    }

    .fx360-table{
        width:100%;
        min-width:900px;
        border-collapse:collapse;
    }

    .fx360-table th{
        background:#f5f8fd;
        color:#58708f;
        font-size:10px;
        font-weight:950;
        text-transform:uppercase;
        letter-spacing:.08em;
        text-align:left;
        padding:12px;
        border-bottom:1px solid #e3ebf7;
    }

    .fx360-table td{
        color:#10233f;
        font-size:12px;
        font-weight:750;
        padding:13px 12px;
        border-bottom:1px solid #eef3fa;
        vertical-align:top;
    }

    .fx360-table tr:last-child td{
        border-bottom:0;
    }

    .fx360-right{
        text-align:right !important;
    }

    .fx360-totals{
        display:flex;
        justify-content:flex-end;
        gap:14px;
        flex-wrap:wrap;
        margin-top:12px;
        font-size:12px;
        font-weight:950;
        color:#10233f;
    }

    .fx360-totals strong{
        color:#2458cf;
    }

    .fx360-ai-grid{
        display:grid;
        grid-template-columns:1.25fr .75fr;
        gap:12px;
    }

    .fx360-ai-box{
        border-radius:16px;
        padding:14px;
        background:linear-gradient(135deg,#f7fbff,#eef5ff);
        border:1px solid #d8e7ff;
    }

    .fx360-ai-title{
        margin:0 0 8px;
        font-size:13px;
        font-weight:950;
        color:#10233f;
    }

    .fx360-ai-text{
        margin:0;
        color:#58708f;
        font-size:12px;
        font-weight:750;
        line-height:1.55;
    }

    .fx360-checklist{
        display:grid;
        gap:9px;
    }

    .fx360-check{
        display:flex;
        align-items:flex-start;
        gap:8px;
        color:#10233f;
        font-size:12px;
        font-weight:800;
        line-height:1.4;
    }

    .fx360-check span{
        width:18px;
        height:18px;
        border-radius:999px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        flex:0 0 auto;
        margin-top:1px;
        background:#ecfdf5;
        color:#0f8a4b;
        font-size:11px;
        font-weight:950;
    }

    .fx360-alert{
        border-radius:16px;
        padding:13px 14px;
        font-size:12px;
        font-weight:850;
        line-height:1.5;
        border:1px solid #d8e7ff;
        background:#f7fbff;
        color:#334861;
    }

    .fx360-alert--ok{
        background:#ecfdf5;
        border-color:#86efac;
        color:#047857;
    }

    .fx360-alert--err{
        background:#fef2f2;
        border-color:#fecaca;
        color:#b91c1c;
    }

    @media (max-width:1180px){
        .fx360-grid-4{
            grid-template-columns:repeat(2,minmax(0,1fr));
        }

        .fx360-ai-grid,
        .fx360-grid-2{
            grid-template-columns:1fr;
        }
    }

    @media (max-width:680px){
        .fx360-detail-title{
            font-size:29px;
        }

        .fx360-grid-4{
            grid-template-columns:1fr;
        }

        .fx360-detail-actions{
            align-items:stretch;
        }

        .fx360-detail-actions > div{
            width:100%;
        }

        .fx360-btn{
            width:100%;
        }
    }
</style>
@endpush

@section('content')
@php
    $user = auth('web')->user();
    $cuentaActual = $cuenta ?? $user?->cuenta ?? null;

    $plan = strtoupper((string) (
        $cuentaActual->plan_actual
        ?? $cuentaActual->tipo_cuenta
        ?? $cuentaActual->plan
        ?? 'FREE'
    ));

    $estatus = strtolower((string) ($cfdi->estatus ?? 'borrador'));

    $isDraft = in_array($estatus, ['borrador', 'pendiente', 'nuevo'], true);
    $isIssued = in_array($estatus, ['emitido', 'timbrado', 'pagado', 'pagada'], true);
    $isVoid = in_array($estatus, ['cancelado', 'cancelada'], true);

    $serieFolio = trim(
        (($cfdi->serie ?? '') ? ($cfdi->serie . '-') : '') . ($cfdi->folio ?? ''),
        '- '
    ) ?: 'Sin serie / folio';

    $uuid = (string) ($cfdi->uuid ?? '');
    $subtotal = (float) ($cfdi->subtotal ?? 0);
    $iva = (float) ($cfdi->iva ?? $cfdi->impuestos_trasladados ?? 0);
    $total = (float) ($cfdi->total ?? 0);

    $moneda = $cfdi->moneda ?? 'MXN';
    $formaPago = $cfdi->forma_pago ?? '—';
    $metodoPago = $cfdi->metodo_pago ?? '—';
    $fechaCfdi = optional($cfdi->fecha)->format('Y-m-d H:i') ?? '—';

    $emisor = $cfdi->emisor ?? null;
    $receptor = $cfdi->receptor ?? null;

    $receptorRfc = $receptor->rfc ?? $cfdi->receptor_rfc ?? '—';
    $receptorNombre = $receptor->razon_social ?? $cfdi->receptor_nombre ?? $cfdi->receptor_razon_social ?? '—';
    $usoCfdi = $receptor->uso_cfdi ?? $cfdi->uso_cfdi ?? 'G03';

    $emisorRfc = $emisor->rfc ?? $cfdi->emisor_rfc ?? '—';
    $emisorNombre = $emisor->razon_social ?? $emisor->nombre_comercial ?? $cfdi->emisor_nombre ?? '—';

    $ivaRateDefault = 0.16;

    $fiscalScore = 0;
    $fiscalScore += $emisorRfc !== '—' ? 20 : 0;
    $fiscalScore += $receptorRfc !== '—' ? 20 : 0;
    $fiscalScore += $usoCfdi !== '—' ? 15 : 0;
    $fiscalScore += $formaPago !== '—' ? 15 : 0;
    $fiscalScore += $metodoPago !== '—' ? 15 : 0;
    $fiscalScore += $total > 0 ? 15 : 0;

    $estadoLabel = $isDraft ? 'Borrador pendiente de timbrar' : ($isIssued ? 'CFDI timbrado' : ($isVoid ? 'CFDI cancelado' : strtoupper($estatus)));
    $estadoTag = $isDraft ? 'BORRADOR' : ($isIssued ? 'TIMBRADO' : ($isVoid ? 'CANCELADO' : strtoupper($estatus)));
@endphp

<div class="fx360-detail-page">

    <section class="fx360-detail-hero">
        <div class="fx360-detail-hero__top">
            <div>
                <div class="fx360-detail-kicker">
                    <span>CFDI · Facturación 360</span>
                </div>

                <h1 class="fx360-detail-title">Detalle CFDI</h1>

                <p class="fx360-detail-subtitle">
                    Consulta la información fiscal, contable y operativa del comprobante.
                    Este diseño prepara el flujo para validaciones IA, timbrado, descarga XML/PDF y revisión fiscal.
                </p>
            </div>

            <div class="fx360-detail-status">
                <div class="fx360-detail-status__label">Estado del documento</div>
                <div class="fx360-detail-status__value">{{ $estadoLabel }}</div>
                <div class="fx360-detail-status__uuid">
                    UUID: {{ $uuid ?: 'Pendiente de timbrar' }}
                </div>
            </div>
        </div>
    </section>

    <section class="fx360-detail-actions">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('cliente.facturacion.index', request()->only(['q','status','mes','anio','month'])) }}" class="fx360-btn">
                ← Volver
            </a>

            @if(Route::has('cliente.facturacion.edit') && $isDraft)
                <a href="{{ route('cliente.facturacion.edit', $cfdi->id) }}" class="fx360-btn">
                    Editar
                </a>
            @endif
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
            <a class="fx360-btn {{ $isIssued && Route::has('cliente.facturacion.ver_pdf') ? '' : 'fx360-btn--disabled' }}"
               href="{{ $isIssued && Route::has('cliente.facturacion.ver_pdf') ? route('cliente.facturacion.ver_pdf', $cfdi->id) : '#' }}"
               target="{{ $isIssued ? '_blank' : '_self' }}">
                Ver PDF
            </a>

            <a class="fx360-btn {{ $isIssued && Route::has('cliente.facturacion.descargar_xml') ? '' : 'fx360-btn--disabled' }}"
               href="{{ $isIssued && Route::has('cliente.facturacion.descargar_xml') ? route('cliente.facturacion.descargar_xml', $cfdi->id) : '#' }}">
                XML
            </a>

            @if(Route::has('cliente.facturacion.duplicar'))
                @if($plan === 'PRO')
                    <form method="POST" action="{{ route('cliente.facturacion.duplicar', $cfdi->id) }}">
                        @csrf
                        <button type="submit" class="fx360-btn">Duplicar</button>
                    </form>
                @else
                    <button type="button" class="fx360-btn fx360-btn--disabled">Duplicar</button>
                @endif
            @endif

            @if($isDraft && Route::has('cliente.facturacion.timbrar'))
                @if($plan === 'PRO')
                    <form method="POST" action="{{ route('cliente.facturacion.timbrar', $cfdi->id) }}">
                        @csrf
                        <button type="submit" class="fx360-btn fx360-btn--primary">
                            Timbrar
                        </button>
                    </form>
                @else
                    <button type="button" class="fx360-btn fx360-btn--disabled">Timbrar</button>
                @endif
            @endif

            @if($isIssued && !$isVoid && Route::has('cliente.facturacion.cancelar'))
                <form method="POST"
                      action="{{ route('cliente.facturacion.cancelar', $cfdi->id) }}"
                      onsubmit="return confirm('¿Cancelar este CFDI?');">
                    @csrf
                    <button type="submit" class="fx360-btn fx360-btn--danger">Cancelar</button>
                </form>
            @endif
        </div>
    </section>

    @if(session('ok'))
        <div class="fx360-alert fx360-alert--ok">{{ session('ok') }}</div>
    @endif

    @if($errors->any())
        <div class="fx360-alert fx360-alert--err">{{ $errors->first() }}</div>
    @endif

    <section class="fx360-grid-4">
        <div class="fx360-card fx360-kpi">
            <div class="fx360-label">Total CFDI</div>
            <div class="fx360-value fx360-value--big fx360-value--blue">${{ number_format($total, 2) }}</div>
            <div class="fx360-value" style="color:#71829a;margin-top:7px;">{{ $moneda }}</div>
        </div>

        <div class="fx360-card fx360-kpi">
            <div class="fx360-label">Estatus</div>
            <div class="fx360-value fx360-value--big {{ $isDraft ? 'fx360-value--warn' : ($isIssued ? 'fx360-value--ok' : '') }}">
                {{ $estadoTag }}
            </div>
            <div class="fx360-value" style="color:#71829a;margin-top:7px;">{{ $fechaCfdi }}</div>
        </div>

        <div class="fx360-card fx360-kpi">
            <div class="fx360-label">Subtotal</div>
            <div class="fx360-value fx360-value--big">${{ number_format($subtotal, 2) }}</div>
            <div class="fx360-value" style="color:#71829a;margin-top:7px;">Antes de impuestos</div>
        </div>

        <div class="fx360-card fx360-kpi">
            <div class="fx360-label">IVA</div>
            <div class="fx360-value fx360-value--big">${{ number_format($iva, 2) }}</div>
            <div class="fx360-value" style="color:#71829a;margin-top:7px;">Trasladado</div>
        </div>
    </section>

    <section class="fx360-ai-grid">
        <div class="fx360-card">
            <div class="fx360-card__head">
                <h2 class="fx360-card__title">Asistente IA fiscal-contable</h2>
                <span class="fx360-card__tag">IA</span>
            </div>

            <div class="fx360-ai-box">
                <h3 class="fx360-ai-title">Diagnóstico rápido del CFDI</h3>
                <p class="fx360-ai-text">
                    Este comprobante tiene un avance fiscal estimado de <strong>{{ $fiscalScore }}%</strong>.
                    Antes de timbrar, valida RFC, régimen fiscal, uso CFDI, forma/método de pago, conceptos,
                    impuestos y total. Este bloque queda listo para conectar recomendaciones IA reales.
                </p>
            </div>
        </div>

        <div class="fx360-card">
            <div class="fx360-card__head">
                <h2 class="fx360-card__title">Checklist SAT</h2>
                <span class="fx360-card__tag">Fiscal</span>
            </div>

            <div class="fx360-checklist">
                <div class="fx360-check"><span>✓</span> RFC emisor: {{ $emisorRfc !== '—' ? 'capturado' : 'pendiente' }}</div>
                <div class="fx360-check"><span>✓</span> RFC receptor: {{ $receptorRfc !== '—' ? 'capturado' : 'pendiente' }}</div>
                <div class="fx360-check"><span>✓</span> Uso CFDI: {{ $usoCfdi }}</div>
                <div class="fx360-check"><span>✓</span> Impuestos calculados: ${{ number_format($iva, 2) }}</div>
            </div>
        </div>
    </section>

    <section class="fx360-grid-2">
        <div class="fx360-card">
            <div class="fx360-card__head">
                <h2 class="fx360-card__title">Emisor</h2>
                <span class="fx360-card__tag">Empresa</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">Nombre / Razón social</span>
                <span class="fx360-value">{{ $emisorNombre }}</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">RFC</span>
                <span class="fx360-value">{{ $emisorRfc }}</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">Serie / Folio</span>
                <span class="fx360-value">{{ $serieFolio }}</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">Fecha</span>
                <span class="fx360-value">{{ $fechaCfdi }}</span>
            </div>
        </div>

        <div class="fx360-card">
            <div class="fx360-card__head">
                <h2 class="fx360-card__title">Receptor</h2>
                <span class="fx360-card__tag">Cliente</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">Razón social</span>
                <span class="fx360-value">{{ $receptorNombre }}</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">RFC</span>
                <span class="fx360-value">{{ $receptorRfc }}</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">Uso CFDI</span>
                <span class="fx360-value">{{ $usoCfdi }}</span>
            </div>
        </div>
    </section>

    <section class="fx360-grid-2">
        <div class="fx360-card">
            <div class="fx360-card__head">
                <h2 class="fx360-card__title">Pago</h2>
                <span class="fx360-card__tag">Cobranza</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">Moneda</span>
                <span class="fx360-value">{{ $moneda }}</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">Forma de pago</span>
                <span class="fx360-value">{{ $formaPago }}</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">Método de pago</span>
                <span class="fx360-value">{{ $metodoPago }}</span>
            </div>
        </div>

        <div class="fx360-card">
            <div class="fx360-card__head">
                <h2 class="fx360-card__title">Contabilidad</h2>
                <span class="fx360-card__tag">Fiscal contable</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">Subtotal contable</span>
                <span class="fx360-value">${{ number_format($subtotal, 2) }}</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">IVA trasladado</span>
                <span class="fx360-value">${{ number_format($iva, 2) }}</span>
            </div>

            <div class="fx360-field">
                <span class="fx360-label">Total fiscal</span>
                <span class="fx360-value fx360-value--big fx360-value--blue">${{ number_format($total, 2) }}</span>
            </div>
        </div>
    </section>

    <section class="fx360-card">
        <div class="fx360-card__head">
            <h2 class="fx360-card__title">Conceptos</h2>
            <span class="fx360-card__tag">Partidas CFDI</span>
        </div>

        <div class="fx360-table-wrap">
            <table class="fx360-table">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th class="fx360-right">Cantidad</th>
                        <th class="fx360-right">Precio</th>
                        <th class="fx360-right">IVA</th>
                        <th class="fx360-right">Subtotal</th>
                        <th class="fx360-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($cfdi->conceptos ?? []) as $it)
                        @php
                            $cant = (float) ($it->cantidad ?? 0);
                            $precio = (float) ($it->precio_unitario ?? $it->valor_unitario ?? 0);
                            $sub = (float) ($it->subtotal ?? $it->importe ?? ($cant * $precio));
                            $tasaIva = (float) ($it->iva_tasa ?? $ivaRateDefault);
                            $ivaRow = (float) ($it->iva ?? $it->impuestos_trasladados ?? ($sub * $tasaIva));
                            $totalRow = (float) ($it->total ?? ($sub + $ivaRow));
                        @endphp
                        <tr>
                            <td>{{ $it->descripcion ?? '—' }}</td>
                            <td class="fx360-right">{{ rtrim(rtrim(number_format($cant, 4), '0'), '.') }}</td>
                            <td class="fx360-right">${{ number_format($precio, 2) }}</td>
                            <td class="fx360-right">{{ number_format($tasaIva * 100, 2) }}%</td>
                            <td class="fx360-right">${{ number_format($sub, 2) }}</td>
                            <td class="fx360-right">${{ number_format($totalRow, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Sin conceptos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="fx360-totals">
            <div>Subtotal: <strong>${{ number_format($subtotal, 2) }}</strong></div>
            <div>IVA: <strong>${{ number_format($iva, 2) }}</strong></div>
            <div>Total: <strong>${{ number_format($total, 2) }}</strong></div>
        </div>
    </section>

    @if($isDraft && $plan !== 'PRO')
        <section class="fx360-alert">
            <strong>Timbrado y duplicado disponibles en PRO.</strong>
            Actualiza tu cuenta para terminar el flujo fiscal completo del CFDI.
        </section>
    @endif

</div>
@endsection