@extends('layouts.cliente')

@section('title', 'Ventas')

@section('content')
@php
    $rtInventario = Route::has('cliente.modulos.inventario') ? route('cliente.modulos.inventario') : '#';
    $rtFact       = Route::has('cliente.facturacion.index')
        ? route('cliente.facturacion.index')
        : (Route::has('cliente.facturacion') ? route('cliente.facturacion') : '#');
    $rtNuevoCfdi  = Route::has('cliente.facturacion.create')
        ? route('cliente.facturacion.create')
        : (Route::has('cliente.facturacion.nuevo') ? route('cliente.facturacion.nuevo') : '#');
    $rtCrm        = Route::has('cliente.modulos.crm') ? route('cliente.modulos.crm') : '#';
    $rtReportes   = Route::has('cliente.modulos.reportes') ? route('cliente.modulos.reportes') : '#';

    $kpis = [
        ['label' => 'Ventas registradas', 'value' => '0', 'hint' => 'Base comercial inicial'],
        ['label' => 'Tickets emitidos', 'value' => '0', 'hint' => 'Comprobantes de venta'],
        ['label' => 'Monto vendido', 'value' => '$0', 'hint' => 'Acumulado operativo'],
        ['label' => 'Facturables', 'value' => '0', 'hint' => 'Ventas listas para facturar'],
    ];

    $sections = [
        [
            'title' => 'Registro de ventas',
            'icon' => 'point_of_sale',
            'desc' => 'Captura de ventas, generación de ticket, código de venta, fecha, monto y relación con cliente o vendedor.',
            'items' => ['Venta', 'Ticket', 'Código', 'Fecha', 'Monto', 'Vendedor'],
        ],
        [
            'title' => 'Productos y conceptos',
            'icon' => 'inventory_2',
            'desc' => 'Las ventas se conectarán con inventario para descontar existencias o usar conceptos de servicio cuando no exista stock.',
            'items' => ['Productos', 'Servicios', 'Stock', 'Conceptos', 'Precio', 'Cantidad'],
        ],
        [
            'title' => 'Facturación ligada',
            'icon' => 'receipt_long',
            'desc' => 'Cada venta podrá marcarse como facturable, emitir CFDI manual o servir de base para autofacturación.',
            'items' => ['Facturable', 'CFDI', 'Manual', 'Autofactura', 'Relación venta', 'Historial'],
        ],
        [
            'title' => 'Autofacturación',
            'icon' => 'qr_code_2',
            'desc' => 'La venta guardará código, monto y fecha para que el cliente final pueda autofacturarse después.',
            'items' => ['Código venta', 'Monto', 'Fecha', 'Cliente final', 'Liga pública', 'Seguimiento'],
        ],
        [
            'title' => 'CRM conectado',
            'icon' => 'groups',
            'desc' => 'Ventas se conectará con CRM para ver comportamiento de compra, oportunidades y relación comercial.',
            'items' => ['Cliente', 'Seguimiento', 'Oportunidad', 'Conversión', 'Historial', 'Frecuencia'],
        ],
        [
            'title' => 'Reportes y control',
            'icon' => 'bar_chart',
            'desc' => 'Reporte de ventas, tickets, facturación derivada, productos más vendidos y análisis comercial.',
            'items' => ['KPIs', 'Tickets', 'Top productos', 'Top clientes', 'Facturación', 'Análisis'],
        ],
    ];

    $aiCards = [
        [
            'title' => 'Asistente IA de ventas',
            'desc' => 'Detecta ventas atípicas, tickets con riesgo y oportunidades de facturación pendientes.',
        ],
        [
            'title' => 'Radar comercial',
            'desc' => 'Ayuda a identificar clientes con mayor frecuencia de compra y productos con mejor conversión.',
        ],
        [
            'title' => 'Impulso de autofactura',
            'desc' => 'Detecta ventas listas para autofacturación y mejora la continuidad del flujo comercial.',
        ],
    ];
@endphp

<style>
    .sales-shell{
        --sales-card:#ffffff;
        --sales-line:#e2e8f0;
        --sales-text:#0f172a;
        --sales-muted:#64748b;
        --sales-primary:#0f172a;
        --sales-soft:#eef2ff;
        --sales-soft-2:#eff6ff;
    }
    .sales-shell{padding:24px 0 30px;}
    .sales-hero{
        position:relative;
        overflow:hidden;
        border:1px solid rgba(255,255,255,.12);
        border-radius:28px;
        padding:28px;
        background:
            radial-gradient(circle at top right, rgba(255,255,255,.10), transparent 28%),
            linear-gradient(135deg, #0f172a 0%, #1e293b 52%, #334155 100%);
        box-shadow:0 18px 40px rgba(15,23,42,.18);
        color:#fff;
        margin-bottom:20px;
    }
    .sales-hero__eyebrow{
        display:inline-flex;
        align-items:center;
        gap:8px;
        min-height:32px;
        padding:0 14px;
        border-radius:999px;
        background:rgba(255,255,255,.12);
        border:1px solid rgba(255,255,255,.12);
        font-size:12px;
        font-weight:800;
        letter-spacing:.04em;
        margin-bottom:14px;
    }
    .sales-hero__eyebrow-dot{
        width:10px;height:10px;border-radius:999px;background:#fff;display:inline-block;
        box-shadow:0 0 0 6px rgba(255,255,255,.08);
    }
    .sales-hero h1{
        font-size:clamp(30px, 4vw, 46px);
        line-height:1;
        font-weight:900;
        letter-spacing:-.04em;
        margin:0 0 10px 0;
        color:#fff;
    }
    .sales-hero p{
        max-width:860px;
        color:rgba(255,255,255,.92);
        font-size:15px;
        line-height:1.65;
        margin:0;
    }
    .sales-hero__chips{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        margin-top:18px;
    }
    .sales-chip{
        display:inline-flex;
        align-items:center;
        gap:8px;
        min-height:34px;
        padding:0 14px;
        border-radius:999px;
        border:1px solid rgba(255,255,255,.14);
        background:rgba(255,255,255,.10);
        color:#fff;
        font-size:12px;
        font-weight:700;
    }

    .sales-grid{display:grid;gap:16px;}
    .sales-grid--kpi{
        grid-template-columns:repeat(4,minmax(0,1fr));
        margin-bottom:20px;
    }
    .sales-grid--2{
        grid-template-columns:1.25fr .95fr;
        margin-bottom:20px;
    }
    .sales-grid--modules{
        grid-template-columns:repeat(3,minmax(0,1fr));
        margin-bottom:20px;
    }

    .sales-card{
        background:var(--sales-card);
        border:1px solid var(--sales-line);
        border-radius:24px;
        box-shadow:0 10px 24px rgba(15,23,42,.04);
    }
    .sales-card__body{padding:20px;}
    .sales-card__head{
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:14px;
        margin-bottom:14px;
    }
    .sales-card__title{
        margin:0;
        color:var(--sales-text);
        font-size:20px;
        line-height:1.1;
        font-weight:900;
        letter-spacing:-.03em;
    }
    .sales-card__sub{
        color:var(--sales-muted);
        font-size:14px;
        line-height:1.55;
        margin-top:6px;
    }

    .sales-kpi{
        height:100%;
        background:#fff;
        border:1px solid var(--sales-line);
        border-radius:22px;
        padding:18px;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .sales-kpi__label{font-size:13px;font-weight:800;color:var(--sales-muted);margin-bottom:8px;}
    .sales-kpi__value{font-size:34px;line-height:1;font-weight:900;color:var(--sales-text);margin-bottom:8px;}
    .sales-kpi__hint{font-size:12px;color:var(--sales-muted);}

    .sales-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }
    .sales-action{
        display:flex;
        align-items:flex-start;
        gap:12px;
        text-decoration:none;
        color:inherit;
        border:1px solid var(--sales-line);
        background:#fff;
        border-radius:18px;
        padding:16px;
        transition:.18s ease;
    }
    .sales-action:hover{
        transform:translateY(-1px);
        border-color:#cbd5e1;
        box-shadow:0 10px 18px rgba(15,23,42,.06);
        text-decoration:none;
        color:inherit;
    }
    .sales-action__icon{
        width:44px;height:44px;border-radius:14px;
        display:flex;align-items:center;justify-content:center;
        background:var(--sales-soft-2);color:var(--sales-primary);
        flex:0 0 44px;
    }
    .sales-action__title{font-size:15px;font-weight:900;color:var(--sales-text);margin-bottom:4px;}
    .sales-action__text{font-size:13px;line-height:1.55;color:var(--sales-muted);}

    .sales-module{
        height:100%;
        border:1px solid var(--sales-line);
        border-radius:22px;
        padding:18px;
        background:#fff;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .sales-module__icon{
        width:52px;height:52px;border-radius:16px;
        display:flex;align-items:center;justify-content:center;
        background:var(--sales-soft);
        color:var(--sales-primary);
        margin-bottom:14px;
    }
    .sales-module__title{
        font-size:18px;
        font-weight:900;
        color:var(--sales-text);
        line-height:1.15;
        margin-bottom:8px;
    }
    .sales-module__desc{
        font-size:14px;
        line-height:1.6;
        color:var(--sales-muted);
        min-height:88px;
        margin-bottom:14px;
    }
    .sales-tags{display:flex;flex-wrap:wrap;gap:8px;}
    .sales-tag{
        display:inline-flex;
        align-items:center;
        min-height:30px;
        padding:0 12px;
        border-radius:999px;
        border:1px solid #dbeafe;
        background:#f8fbff;
        color:#1e3a8a;
        font-size:12px;
        font-weight:700;
    }

    .sales-roadmap{
        display:grid;
        gap:12px;
    }
    .sales-step{
        display:flex;
        gap:12px;
        align-items:flex-start;
        padding:14px;
        border:1px solid var(--sales-line);
        border-radius:18px;
        background:#fff;
    }
    .sales-step__num{
        width:34px;height:34px;border-radius:999px;
        display:flex;align-items:center;justify-content:center;
        background:var(--sales-primary);color:#fff;font-weight:900;flex:0 0 34px;
    }
    .sales-step__title{font-size:15px;font-weight:900;color:var(--sales-text);margin-bottom:3px;}
    .sales-step__text{font-size:13px;line-height:1.55;color:var(--sales-muted);}

    .sales-ai-grid{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:16px;
    }
    .sales-ai-card{
        border:1px solid var(--sales-line);
        border-radius:22px;
        padding:18px;
        background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);
    }
    .sales-ai-card__badge{
        display:inline-flex;
        align-items:center;
        min-height:28px;
        padding:0 10px;
        border-radius:999px;
        background:#eef2ff;
        color:#3730a3;
        font-size:11px;
        font-weight:900;
        margin-bottom:12px;
    }
    .sales-ai-card__title{
        font-size:17px;
        font-weight:900;
        color:var(--sales-text);
        line-height:1.2;
        margin-bottom:8px;
    }
    .sales-ai-card__text{
        font-size:14px;
        line-height:1.6;
        color:var(--sales-muted);
    }

    .sales-note{
        margin-top:16px;
        padding:14px 16px;
        border-radius:18px;
        border:1px dashed #cbd5e1;
        background:#f8fafc;
        color:var(--sales-muted);
        font-size:13px;
        line-height:1.6;
    }

    @media (max-width: 1199.98px){
        .sales-grid--kpi{grid-template-columns:repeat(2,minmax(0,1fr));}
        .sales-grid--modules{grid-template-columns:repeat(2,minmax(0,1fr));}
        .sales-ai-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    }
    @media (max-width: 991.98px){
        .sales-grid--2{grid-template-columns:1fr;}
    }
    @media (max-width: 767.98px){
        .sales-shell{padding:18px 0 24px;}
        .sales-hero{padding:22px;}
        .sales-grid--kpi,
        .sales-grid--modules,
        .sales-ai-grid,
        .sales-actions{grid-template-columns:1fr;}
        .sales-module__desc{min-height:auto;}
    }
</style>

<div class="container-fluid sales-shell">
    <section class="sales-hero">
        <div class="sales-hero__eyebrow">
            <span class="sales-hero__eyebrow-dot"></span>
            <span>VENTAS + INVENTARIO + FACTURACIÓN + CRM + IA</span>
        </div>

        <h1>Ventas</h1>

        <p>
            Registra ventas, tickets, códigos de venta y comprobantes desde un solo módulo.
            Esta base de ventas está pensada para conectarse con inventario, CRM, facturación y
            autofacturación, permitiendo que Pactopia360 tenga un flujo comercial real, simple al inicio
            y listo para crecer.
        </p>

        <div class="sales-hero__chips">
            <span class="sales-chip">Ventas</span>
            <span class="sales-chip">Tickets</span>
            <span class="sales-chip">Código de venta</span>
            <span class="sales-chip">Facturación ligada</span>
            <span class="sales-chip">Autofacturación</span>
            <span class="sales-chip">IA comercial</span>
        </div>
    </section>

    <div class="sales-grid sales-grid--kpi">
        @foreach($kpis as $kpi)
            <div class="sales-kpi">
                <div class="sales-kpi__label">{{ $kpi['label'] }}</div>
                <div class="sales-kpi__value">{{ $kpi['value'] }}</div>
                <div class="sales-kpi__hint">{{ $kpi['hint'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="sales-grid sales-grid--2">
        <div class="sales-card">
            <div class="sales-card__body">
                <div class="sales-card__head">
                    <div>
                        <h2 class="sales-card__title">Centro de operación Ventas</h2>
                        <div class="sales-card__sub">
                            Primera versión simple pero completa para dejar ventas lista para operar y crecer por capas.
                        </div>
                    </div>
                </div>

                <div class="sales-actions">
                    <a href="{{ $rtInventario }}" class="sales-action">
                        <div class="sales-action__icon"><span class="material-symbols-outlined">inventory_2</span></div>
                        <div>
                            <div class="sales-action__title">Ir a Inventario</div>
                            <div class="sales-action__text">Relaciona productos, existencias y consumo real por ticket o venta.</div>
                        </div>
                    </a>

                    <a href="{{ $rtFact }}" class="sales-action">
                        <div class="sales-action__icon"><span class="material-symbols-outlined">receipt_long</span></div>
                        <div>
                            <div class="sales-action__title">Ir a Facturación</div>
                            <div class="sales-action__text">Conecta la venta con CFDI manual o comprobante fiscal cuando aplique.</div>
                        </div>
                    </a>

                    <a href="{{ $rtNuevoCfdi }}" class="sales-action">
                        <div class="sales-action__icon"><span class="material-symbols-outlined">add_circle</span></div>
                        <div>
                            <div class="sales-action__title">Nuevo CFDI</div>
                            <div class="sales-action__text">Acceso corto para facturar una venta o emitir un CFDI manual.</div>
                        </div>
                    </a>

                    <a href="{{ $rtCrm }}" class="sales-action">
                        <div class="sales-action__icon"><span class="material-symbols-outlined">groups</span></div>
                        <div>
                            <div class="sales-action__title">Ir a CRM</div>
                            <div class="sales-action__text">Relaciona clientes, oportunidades y comportamiento de compra con cada venta.</div>
                        </div>
                    </a>
                </div>

                <div class="sales-note">
                    <strong>Regla del producto:</strong> ventas será el puente entre <strong>Inventario</strong>,
                    <strong>Facturación</strong>, <strong>CRM</strong> y la futura <strong>Autofacturación</strong>.
                </div>
            </div>
        </div>

        <div class="sales-card">
            <div class="sales-card__body">
                <div class="sales-card__head">
                    <div>
                        <h2 class="sales-card__title">Ruta de implementación</h2>
                        <div class="sales-card__sub">
                            Dejamos el módulo completo en estructura para activarlo por capas sin romper tu proyecto.
                        </div>
                    </div>
                </div>

                <div class="sales-roadmap">
                    <div class="sales-step">
                        <div class="sales-step__num">1</div>
                        <div>
                            <div class="sales-step__title">Base de ventas</div>
                            <div class="sales-step__text">Registro de venta, ticket, monto, fecha, vendedor y cliente en web + móvil.</div>
                        </div>
                    </div>

                    <div class="sales-step">
                        <div class="sales-step__num">2</div>
                        <div>
                            <div class="sales-step__title">Conexión con Inventario</div>
                            <div class="sales-step__text">Descontar stock, tomar productos y registrar consumo comercial.</div>
                        </div>
                    </div>

                    <div class="sales-step">
                        <div class="sales-step__num">3</div>
                        <div>
                            <div class="sales-step__title">Conexión con Facturación</div>
                            <div class="sales-step__text">Emitir CFDI desde la venta o preparar el flujo de autofacturación.</div>
                        </div>
                    </div>

                    <div class="sales-step">
                        <div class="sales-step__num">4</div>
                        <div>
                            <div class="sales-step__title">IA comercial</div>
                            <div class="sales-step__text">Detección de ventas atípicas, ventas listas para facturar y patrones de compra.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sales-grid sales-grid--modules">
        @foreach($sections as $section)
            <div class="sales-module">
                <div class="sales-module__icon">
                    <span class="material-symbols-outlined">{{ $section['icon'] }}</span>
                </div>

                <div class="sales-module__title">{{ $section['title'] }}</div>

                <div class="sales-module__desc">
                    {{ $section['desc'] }}
                </div>

                <div class="sales-tags">
                    @foreach($section['items'] as $item)
                        <span class="sales-tag">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="sales-card mb-4">
        <div class="sales-card__body">
            <div class="sales-card__head">
                <div>
                    <h2 class="sales-card__title">IA aplicada a Ventas</h2>
                    <div class="sales-card__sub">
                        Ventas debe ayudarte a vender mejor, no solo a capturar tickets.
                    </div>
                </div>
            </div>

            <div class="sales-ai-grid">
                @foreach($aiCards as $card)
                    <div class="sales-ai-card">
                        <div class="sales-ai-card__badge">IA Pactopia360</div>
                        <div class="sales-ai-card__title">{{ $card['title'] }}</div>
                        <div class="sales-ai-card__text">{{ $card['desc'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="sales-note">
                <strong>Idea comercial fuerte:</strong> ventas debe detectar tickets listos para facturar, ventas repetidas y oportunidades de recompra o seguimiento.
            </div>
        </div>
    </div>

    <div class="sales-card">
        <div class="sales-card__body">
            <div class="sales-card__head">
                <div>
                    <h2 class="sales-card__title">Conexión con otros módulos</h2>
                    <div class="sales-card__sub">
                        Ventas se integrará con inventario, CRM, facturación y reportes para formar un flujo comercial real.
                    </div>
                </div>
            </div>

            <div class="sales-actions">
                <a href="{{ $rtInventario }}" class="sales-action">
                    <div class="sales-action__icon"><span class="material-symbols-outlined">inventory_2</span></div>
                    <div>
                        <div class="sales-action__title">Ir a Inventario</div>
                        <div class="sales-action__text">Inventario alimentará productos, stock y consumo por venta.</div>
                    </div>
                </a>

                <a href="{{ $rtFact }}" class="sales-action">
                    <div class="sales-action__icon"><span class="material-symbols-outlined">receipt_long</span></div>
                    <div>
                        <div class="sales-action__title">Ir a Facturación</div>
                        <div class="sales-action__text">Facturación reutilizará ventas y conceptos para el flujo CFDI.</div>
                    </div>
                </a>

                <a href="{{ $rtCrm }}" class="sales-action">
                    <div class="sales-action__icon"><span class="material-symbols-outlined">groups</span></div>
                    <div>
                        <div class="sales-action__title">Ir a CRM</div>
                        <div class="sales-action__text">CRM visualizará clientes, oportunidades y comportamiento de compra.</div>
                    </div>
                </a>

                <a href="{{ $rtReportes }}" class="sales-action">
                    <div class="sales-action__icon"><span class="material-symbols-outlined">monitoring</span></div>
                    <div>
                        <div class="sales-action__title">Ir a Reportes</div>
                        <div class="sales-action__text">Reportes cruzará ventas, tickets, facturación y resultados comerciales.</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection