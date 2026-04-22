@extends('layouts.cliente')

@section('title', 'Inventario')

@section('content')
@php
    $rtVentas = Route::has('cliente.modulos.ventas') ? route('cliente.modulos.ventas') : '#';
    $rtFact   = Route::has('cliente.facturacion.index')
        ? route('cliente.facturacion.index')
        : (Route::has('cliente.facturacion') ? route('cliente.facturacion') : '#');
    $rtNuevoCfdi = Route::has('cliente.facturacion.create')
        ? route('cliente.facturacion.create')
        : (Route::has('cliente.facturacion.nuevo') ? route('cliente.facturacion.nuevo') : '#');
    $rtCrm = Route::has('cliente.modulos.crm') ? route('cliente.modulos.crm') : '#';
    $rtReportes = Route::has('cliente.modulos.reportes') ? route('cliente.modulos.reportes') : '#';

    $kpis = [
        ['label' => 'Productos', 'value' => '0', 'hint' => 'Catálogo inicial'],
        ['label' => 'Stock disponible', 'value' => '0', 'hint' => 'Existencias actuales'],
        ['label' => 'Movimientos', 'value' => '0', 'hint' => 'Entradas y salidas'],
        ['label' => 'Ventas ligadas', 'value' => '0', 'hint' => 'Conexión comercial'],
    ];

    $sections = [
        [
            'title' => 'Catálogo de productos',
            'icon' => 'inventory_2',
            'desc' => 'Registro y administración de productos, servicios, SKU, categorías, unidad de medida, precio base y estado comercial.',
            'items' => ['Productos', 'Servicios', 'SKU', 'Categorías', 'Precio base', 'Unidad'],
        ],
        [
            'title' => 'Stock y existencias',
            'icon' => 'warehouse',
            'desc' => 'Control de existencias, stock mínimo, stock disponible, apartados y visibilidad operativa para ventas.',
            'items' => ['Existencias', 'Stock mínimo', 'Disponible', 'Apartados', 'Alertas', 'Control'],
        ],
        [
            'title' => 'Movimientos',
            'icon' => 'swap_horiz',
            'desc' => 'Entradas, salidas, ajustes, traspasos, devoluciones y bitácora para trazabilidad del inventario.',
            'items' => ['Entradas', 'Salidas', 'Ajustes', 'Traspasos', 'Devoluciones', 'Bitácora'],
        ],
        [
            'title' => 'Ventas conectadas',
            'icon' => 'point_of_sale',
            'desc' => 'El inventario se conectará con ventas para descontar existencias, registrar tickets y alimentar autofacturación.',
            'items' => ['Tickets', 'Ventas', 'Descuento stock', 'Códigos', 'Consumo', 'Conversión'],
        ],
        [
            'title' => 'Facturación conectada',
            'icon' => 'receipt_long',
            'desc' => 'El inventario se conectará con facturación para emitir CFDI a partir de productos vendidos o facturas manuales.',
            'items' => ['CFDI', 'Conceptos', 'Productos facturables', 'Precio', 'Impuestos', 'Historial'],
        ],
        [
            'title' => 'Reportes y análisis',
            'icon' => 'bar_chart',
            'desc' => 'Vista de productos más vendidos, rotación, alertas de stock y comportamiento comercial del inventario.',
            'items' => ['Rotación', 'Más vendidos', 'Stock crítico', 'Alertas', 'Histórico', 'Análisis'],
        ],
    ];

    $aiCards = [
        [
            'title' => 'Asistente IA de stock',
            'desc' => 'Detecta productos sin movimiento, existencias críticas y comportamientos anormales de inventario.',
        ],
        [
            'title' => 'Predicción comercial',
            'desc' => 'Ayuda a identificar productos con mayor probabilidad de venta según historial y comportamiento.',
        ],
        [
            'title' => 'Radar de facturación',
            'desc' => 'Cruza ventas, inventario y facturación para detectar productos listos para facturar o reponer.',
        ],
    ];
@endphp

<style>
    .inv-shell{
        --inv-card:#ffffff;
        --inv-line:#e2e8f0;
        --inv-text:#0f172a;
        --inv-muted:#64748b;
        --inv-primary:#0f172a;
        --inv-soft:#eef2ff;
        --inv-soft-2:#eff6ff;
    }
    .inv-shell{padding:24px 0 30px;}
    .inv-hero{
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
    .inv-hero__eyebrow{
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
    .inv-hero__eyebrow-dot{
        width:10px;height:10px;border-radius:999px;background:#fff;display:inline-block;
        box-shadow:0 0 0 6px rgba(255,255,255,.08);
    }
    .inv-hero h1{
        font-size:clamp(30px, 4vw, 46px);
        line-height:1;
        font-weight:900;
        letter-spacing:-.04em;
        margin:0 0 10px 0;
        color:#fff;
    }
    .inv-hero p{
        max-width:860px;
        color:rgba(255,255,255,.92);
        font-size:15px;
        line-height:1.65;
        margin:0;
    }
    .inv-hero__chips{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        margin-top:18px;
    }
    .inv-chip{
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

    .inv-grid{display:grid;gap:16px;}
    .inv-grid--kpi{
        grid-template-columns:repeat(4,minmax(0,1fr));
        margin-bottom:20px;
    }
    .inv-grid--2{
        grid-template-columns:1.25fr .95fr;
        margin-bottom:20px;
    }
    .inv-grid--modules{
        grid-template-columns:repeat(3,minmax(0,1fr));
        margin-bottom:20px;
    }

    .inv-card{
        background:var(--inv-card);
        border:1px solid var(--inv-line);
        border-radius:24px;
        box-shadow:0 10px 24px rgba(15,23,42,.04);
    }
    .inv-card__body{padding:20px;}
    .inv-card__head{
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:14px;
        margin-bottom:14px;
    }
    .inv-card__title{
        margin:0;
        color:var(--inv-text);
        font-size:20px;
        line-height:1.1;
        font-weight:900;
        letter-spacing:-.03em;
    }
    .inv-card__sub{
        color:var(--inv-muted);
        font-size:14px;
        line-height:1.55;
        margin-top:6px;
    }

    .inv-kpi{
        height:100%;
        background:#fff;
        border:1px solid var(--inv-line);
        border-radius:22px;
        padding:18px;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .inv-kpi__label{font-size:13px;font-weight:800;color:var(--inv-muted);margin-bottom:8px;}
    .inv-kpi__value{font-size:34px;line-height:1;font-weight:900;color:var(--inv-text);margin-bottom:8px;}
    .inv-kpi__hint{font-size:12px;color:var(--inv-muted);}

    .inv-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }
    .inv-action{
        display:flex;
        align-items:flex-start;
        gap:12px;
        text-decoration:none;
        color:inherit;
        border:1px solid var(--inv-line);
        background:#fff;
        border-radius:18px;
        padding:16px;
        transition:.18s ease;
    }
    .inv-action:hover{
        transform:translateY(-1px);
        border-color:#cbd5e1;
        box-shadow:0 10px 18px rgba(15,23,42,.06);
        text-decoration:none;
        color:inherit;
    }
    .inv-action__icon{
        width:44px;height:44px;border-radius:14px;
        display:flex;align-items:center;justify-content:center;
        background:var(--inv-soft-2);color:var(--inv-primary);
        flex:0 0 44px;
    }
    .inv-action__title{font-size:15px;font-weight:900;color:var(--inv-text);margin-bottom:4px;}
    .inv-action__text{font-size:13px;line-height:1.55;color:var(--inv-muted);}

    .inv-module{
        height:100%;
        border:1px solid var(--inv-line);
        border-radius:22px;
        padding:18px;
        background:#fff;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .inv-module__icon{
        width:52px;height:52px;border-radius:16px;
        display:flex;align-items:center;justify-content:center;
        background:var(--inv-soft);
        color:var(--inv-primary);
        margin-bottom:14px;
    }
    .inv-module__title{
        font-size:18px;
        font-weight:900;
        color:var(--inv-text);
        line-height:1.15;
        margin-bottom:8px;
    }
    .inv-module__desc{
        font-size:14px;
        line-height:1.6;
        color:var(--inv-muted);
        min-height:88px;
        margin-bottom:14px;
    }
    .inv-tags{display:flex;flex-wrap:wrap;gap:8px;}
    .inv-tag{
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

    .inv-roadmap{
        display:grid;
        gap:12px;
    }
    .inv-step{
        display:flex;
        gap:12px;
        align-items:flex-start;
        padding:14px;
        border:1px solid var(--inv-line);
        border-radius:18px;
        background:#fff;
    }
    .inv-step__num{
        width:34px;height:34px;border-radius:999px;
        display:flex;align-items:center;justify-content:center;
        background:var(--inv-primary);color:#fff;font-weight:900;flex:0 0 34px;
    }
    .inv-step__title{font-size:15px;font-weight:900;color:var(--inv-text);margin-bottom:3px;}
    .inv-step__text{font-size:13px;line-height:1.55;color:var(--inv-muted);}

    .inv-ai-grid{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:16px;
    }
    .inv-ai-card{
        border:1px solid var(--inv-line);
        border-radius:22px;
        padding:18px;
        background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);
    }
    .inv-ai-card__badge{
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
    .inv-ai-card__title{
        font-size:17px;
        font-weight:900;
        color:var(--inv-text);
        line-height:1.2;
        margin-bottom:8px;
    }
    .inv-ai-card__text{
        font-size:14px;
        line-height:1.6;
        color:var(--inv-muted);
    }

    .inv-note{
        margin-top:16px;
        padding:14px 16px;
        border-radius:18px;
        border:1px dashed #cbd5e1;
        background:#f8fafc;
        color:var(--inv-muted);
        font-size:13px;
        line-height:1.6;
    }

    @media (max-width: 1199.98px){
        .inv-grid--kpi{grid-template-columns:repeat(2,minmax(0,1fr));}
        .inv-grid--modules{grid-template-columns:repeat(2,minmax(0,1fr));}
        .inv-ai-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    }
    @media (max-width: 991.98px){
        .inv-grid--2{grid-template-columns:1fr;}
    }
    @media (max-width: 767.98px){
        .inv-shell{padding:18px 0 24px;}
        .inv-hero{padding:22px;}
        .inv-grid--kpi,
        .inv-grid--modules,
        .inv-ai-grid,
        .inv-actions{grid-template-columns:1fr;}
        .inv-module__desc{min-height:auto;}
    }
</style>

<div class="container-fluid inv-shell">
    <section class="inv-hero">
        <div class="inv-hero__eyebrow">
            <span class="inv-hero__eyebrow-dot"></span>
            <span>INVENTARIO + VENTAS + FACTURACIÓN + IA</span>
        </div>

        <h1>Inventario</h1>

        <p>
            Administra productos, stock, movimientos y trazabilidad operativa desde un solo módulo.
            Esta base de inventario está pensada para conectarse con ventas, tickets, conceptos facturables
            y reportes, permitiendo que Pactopia360 tenga un flujo comercial real, simple al inicio y listo para crecer.
        </p>

        <div class="inv-hero__chips">
            <span class="inv-chip">Productos</span>
            <span class="inv-chip">Stock</span>
            <span class="inv-chip">Movimientos</span>
            <span class="inv-chip">Ventas conectadas</span>
            <span class="inv-chip">Facturación conectada</span>
            <span class="inv-chip">IA operativa</span>
        </div>
    </section>

    <div class="inv-grid inv-grid--kpi">
        @foreach($kpis as $kpi)
            <div class="inv-kpi">
                <div class="inv-kpi__label">{{ $kpi['label'] }}</div>
                <div class="inv-kpi__value">{{ $kpi['value'] }}</div>
                <div class="inv-kpi__hint">{{ $kpi['hint'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="inv-grid inv-grid--2">
        <div class="inv-card">
            <div class="inv-card__body">
                <div class="inv-card__head">
                    <div>
                        <h2 class="inv-card__title">Centro de operación Inventario</h2>
                        <div class="inv-card__sub">
                            Primera versión simple pero completa para dejar inventario listo para operar y crecer por capas.
                        </div>
                    </div>
                </div>

                <div class="inv-actions">
                    <a href="{{ $rtVentas }}" class="inv-action">
                        <div class="inv-action__icon"><span class="material-symbols-outlined">point_of_sale</span></div>
                        <div>
                            <div class="inv-action__title">Ir a Ventas</div>
                            <div class="inv-action__text">Relaciona productos, tickets, códigos de venta y consumo real de existencias.</div>
                        </div>
                    </a>

                    <a href="{{ $rtFact }}" class="inv-action">
                        <div class="inv-action__icon"><span class="material-symbols-outlined">receipt_long</span></div>
                        <div>
                            <div class="inv-action__title">Ir a Facturación</div>
                            <div class="inv-action__text">Conecta productos y conceptos vendibles con CFDI y administración comercial.</div>
                        </div>
                    </a>

                    <a href="{{ $rtNuevoCfdi }}" class="inv-action">
                        <div class="inv-action__icon"><span class="material-symbols-outlined">add_circle</span></div>
                        <div>
                            <div class="inv-action__title">Nuevo CFDI</div>
                            <div class="inv-action__text">Acceso corto para facturar conceptos o productos desde el flujo comercial.</div>
                        </div>
                    </a>

                    <a href="{{ $rtCrm }}" class="inv-action">
                        <div class="inv-action__icon"><span class="material-symbols-outlined">groups</span></div>
                        <div>
                            <div class="inv-action__title">Ir a CRM</div>
                            <div class="inv-action__text">Relaciona clientes, oportunidades y comportamiento de compra con inventario.</div>
                        </div>
                    </a>
                </div>

                <div class="inv-note">
                    <strong>Regla del producto:</strong> inventario será la base de <strong>Ventas</strong> y se conectará con
                    <strong>Facturación</strong> para evitar doble captura, mejorar control y preparar autofacturación.
                </div>
            </div>
        </div>

        <div class="inv-card">
            <div class="inv-card__body">
                <div class="inv-card__head">
                    <div>
                        <h2 class="inv-card__title">Ruta de implementación</h2>
                        <div class="inv-card__sub">
                            Dejamos el módulo completo en estructura para activarlo por capas sin romper tu proyecto.
                        </div>
                    </div>
                </div>

                <div class="inv-roadmap">
                    <div class="inv-step">
                        <div class="inv-step__num">1</div>
                        <div>
                            <div class="inv-step__title">Base de inventario</div>
                            <div class="inv-step__text">Productos, servicios, existencias, categorías y vistas operativas web + móvil.</div>
                        </div>
                    </div>

                    <div class="inv-step">
                        <div class="inv-step__num">2</div>
                        <div>
                            <div class="inv-step__title">Conexión con Ventas</div>
                            <div class="inv-step__text">Descontar stock, relacionar tickets y alimentar códigos de venta.</div>
                        </div>
                    </div>

                    <div class="inv-step">
                        <div class="inv-step__num">3</div>
                        <div>
                            <div class="inv-step__title">Conexión con Facturación</div>
                            <div class="inv-step__text">Usar productos/conceptos para emitir CFDI de forma manual o desde venta.</div>
                        </div>
                    </div>

                    <div class="inv-step">
                        <div class="inv-step__num">4</div>
                        <div>
                            <div class="inv-step__title">IA operativa</div>
                            <div class="inv-step__text">Alertas de stock, rotación, predicción y productos listos para mover o reponer.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="inv-grid inv-grid--modules">
        @foreach($sections as $section)
            <div class="inv-module">
                <div class="inv-module__icon">
                    <span class="material-symbols-outlined">{{ $section['icon'] }}</span>
                </div>

                <div class="inv-module__title">{{ $section['title'] }}</div>

                <div class="inv-module__desc">
                    {{ $section['desc'] }}
                </div>

                <div class="inv-tags">
                    @foreach($section['items'] as $item)
                        <span class="inv-tag">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="inv-card mb-4">
        <div class="inv-card__body">
            <div class="inv-card__head">
                <div>
                    <h2 class="inv-card__title">IA aplicada a Inventario</h2>
                    <div class="inv-card__sub">
                        Inventario debe ayudarte a decidir mejor, no solo a capturar productos.
                    </div>
                </div>
            </div>

            <div class="inv-ai-grid">
                @foreach($aiCards as $card)
                    <div class="inv-ai-card">
                        <div class="inv-ai-card__badge">IA Pactopia360</div>
                        <div class="inv-ai-card__title">{{ $card['title'] }}</div>
                        <div class="inv-ai-card__text">{{ $card['desc'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="inv-note">
                <strong>Idea comercial fuerte:</strong> inventario debe detectar productos con poco movimiento, alertar faltantes y sugerir prioridades de venta o reposición.
            </div>
        </div>
    </div>

    <div class="inv-card">
        <div class="inv-card__body">
            <div class="inv-card__head">
                <div>
                    <h2 class="inv-card__title">Conexión con otros módulos</h2>
                    <div class="inv-card__sub">
                        Inventario se integrará con ventas, CRM, facturación y reportes para formar un flujo comercial real.
                    </div>
                </div>
            </div>

            <div class="inv-actions">
                <a href="{{ $rtVentas }}" class="inv-action">
                    <div class="inv-action__icon"><span class="material-symbols-outlined">point_of_sale</span></div>
                    <div>
                        <div class="inv-action__title">Ir a Ventas</div>
                        <div class="inv-action__text">Ventas consumirá inventario y registrará tickets/códigos comerciales.</div>
                    </div>
                </a>

                <a href="{{ $rtFact }}" class="inv-action">
                    <div class="inv-action__icon"><span class="material-symbols-outlined">receipt_long</span></div>
                    <div>
                        <div class="inv-action__title">Ir a Facturación</div>
                        <div class="inv-action__text">Facturación reutilizará productos y conceptos dentro del flujo CFDI.</div>
                    </div>
                </a>

                <a href="{{ $rtCrm }}" class="inv-action">
                    <div class="inv-action__icon"><span class="material-symbols-outlined">groups</span></div>
                    <div>
                        <div class="inv-action__title">Ir a CRM</div>
                        <div class="inv-action__text">CRM visualizará comportamiento de compra y oportunidades ligadas a productos.</div>
                    </div>
                </a>

                <a href="{{ $rtReportes }}" class="inv-action">
                    <div class="inv-action__icon"><span class="material-symbols-outlined">monitoring</span></div>
                    <div>
                        <div class="inv-action__title">Ir a Reportes</div>
                        <div class="inv-action__text">Reportes cruzará rotación, ventas, facturación y alertas del inventario.</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection