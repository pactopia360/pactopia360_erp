@extends('layouts.cliente')

@section('title', 'CRM')

@section('content')
@php
    $rtVentas = Route::has('cliente.modulos.ventas') ? route('cliente.modulos.ventas') : '#';
    $rtFact   = Route::has('cliente.facturacion.index')
        ? route('cliente.facturacion.index')
        : (Route::has('cliente.facturacion') ? route('cliente.facturacion') : '#');
    $rtNuevoCfdi = Route::has('cliente.facturacion.create')
        ? route('cliente.facturacion.create')
        : (Route::has('cliente.facturacion.nuevo') ? route('cliente.facturacion.nuevo') : '#');
    $rtReportes = Route::has('cliente.modulos.reportes') ? route('cliente.modulos.reportes') : '#';

    $kpis = [
        ['label' => 'Clientes activos', 'value' => '0', 'hint' => 'Base CRM inicial'],
        ['label' => 'Contactos', 'value' => '0', 'hint' => 'Seguimiento comercial'],
        ['label' => 'Oportunidades', 'value' => '0', 'hint' => 'Pipeline abierto'],
        ['label' => 'Ventas ligadas', 'value' => '0', 'hint' => 'Conexión con ventas'],
    ];

    $sections = [
        [
            'title' => 'Clientes',
            'icon' => 'groups',
            'desc' => 'Registro y administración de clientes, cuentas, historial comercial, estatus y relación con ventas y facturación.',
            'items' => ['Alta de clientes', 'Ficha comercial', 'Historial', 'Clasificación', 'Notas', 'Seguimiento'],
        ],
        [
            'title' => 'Contactos',
            'icon' => 'contact_phone',
            'desc' => 'Control de contactos por cliente, puestos, correos, teléfonos, responsables y puntos de decisión.',
            'items' => ['Contactos', 'Puestos', 'Correos', 'Teléfonos', 'Responsables', 'Preferencias'],
        ],
        [
            'title' => 'Oportunidades',
            'icon' => 'trending_up',
            'desc' => 'Embudo comercial con oportunidades, monto estimado, etapa, probabilidad de cierre y próximo movimiento.',
            'items' => ['Pipeline', 'Probabilidad', 'Monto estimado', 'Etapas', 'Fechas', 'Prioridad'],
        ],
        [
            'title' => 'Actividades',
            'icon' => 'event_note',
            'desc' => 'Agenda comercial con llamadas, reuniones, pendientes, recordatorios y tareas por cliente u oportunidad.',
            'items' => ['Llamadas', 'Reuniones', 'Pendientes', 'Recordatorios', 'Seguimiento', 'Bitácora'],
        ],
        [
            'title' => 'Ventas conectadas',
            'icon' => 'point_of_sale',
            'desc' => 'El CRM se conectará con ventas para ver tickets, códigos de venta, montos y comportamiento del cliente.',
            'items' => ['Tickets', 'Ventas', 'Códigos', 'Monto', 'Frecuencia', 'Conversión'],
        ],
        [
            'title' => 'Facturación conectada',
            'icon' => 'receipt_long',
            'desc' => 'El CRM se conectará con facturación para visualizar CFDI emitidos, receptor fiscal y comportamiento de cobro.',
            'items' => ['CFDI', 'Receptores', 'Cobro', 'Historial', 'Última factura', 'Relación comercial'],
        ],
    ];

    $aiCards = [
        [
            'title' => 'Asistente comercial IA',
            'desc' => 'Detecta clientes sin seguimiento, oportunidades frías y movimientos comerciales pendientes.',
        ],
        [
            'title' => 'Prioridad inteligente',
            'desc' => 'Ordena oportunidades por valor potencial, urgencia y probabilidad de cierre.',
        ],
        [
            'title' => 'Radar de facturación',
            'desc' => 'Cruza CRM con ventas y facturación para detectar clientes listos para vender, cobrar o facturar.',
        ],
    ];
@endphp

<style>
    .crm-shell{
        --crm-bg:#f8fafc;
        --crm-card:#ffffff;
        --crm-line:#e2e8f0;
        --crm-text:#0f172a;
        --crm-muted:#64748b;
        --crm-primary:#0f172a;
        --crm-soft:#eef2ff;
        --crm-soft-2:#eff6ff;
    }
    .crm-shell{padding:24px 0 30px;}
    .crm-hero{
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
    .crm-hero__eyebrow{
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
    .crm-hero__eyebrow-dot{
        width:10px;height:10px;border-radius:999px;background:#fff;display:inline-block;
        box-shadow:0 0 0 6px rgba(255,255,255,.08);
    }
    .crm-hero h1{
        font-size:clamp(30px, 4vw, 46px);
        line-height:1;
        font-weight:900;
        letter-spacing:-.04em;
        margin:0 0 10px 0;
        color:#fff;
    }
    .crm-hero p{
        max-width:860px;
        color:rgba(255,255,255,.92);
        font-size:15px;
        line-height:1.65;
        margin:0;
    }
    .crm-hero__chips{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        margin-top:18px;
    }
    .crm-chip{
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

    .crm-grid{display:grid;gap:16px;}
    .crm-grid--kpi{
        grid-template-columns:repeat(4,minmax(0,1fr));
        margin-bottom:20px;
    }
    .crm-grid--2{
        grid-template-columns:1.25fr .95fr;
        margin-bottom:20px;
    }
    .crm-grid--modules{
        grid-template-columns:repeat(3,minmax(0,1fr));
        margin-bottom:20px;
    }

    .crm-card{
        background:var(--crm-card);
        border:1px solid var(--crm-line);
        border-radius:24px;
        box-shadow:0 10px 24px rgba(15,23,42,.04);
    }
    .crm-card__body{padding:20px;}
    .crm-card__head{
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:14px;
        margin-bottom:14px;
    }
    .crm-card__title{
        margin:0;
        color:var(--crm-text);
        font-size:20px;
        line-height:1.1;
        font-weight:900;
        letter-spacing:-.03em;
    }
    .crm-card__sub{
        color:var(--crm-muted);
        font-size:14px;
        line-height:1.55;
        margin-top:6px;
    }

    .crm-kpi{
        height:100%;
        background:#fff;
        border:1px solid var(--crm-line);
        border-radius:22px;
        padding:18px;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .crm-kpi__label{font-size:13px;font-weight:800;color:var(--crm-muted);margin-bottom:8px;}
    .crm-kpi__value{font-size:34px;line-height:1;font-weight:900;color:var(--crm-text);margin-bottom:8px;}
    .crm-kpi__hint{font-size:12px;color:var(--crm-muted);}

    .crm-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }
    .crm-action{
        display:flex;
        align-items:flex-start;
        gap:12px;
        text-decoration:none;
        color:inherit;
        border:1px solid var(--crm-line);
        background:#fff;
        border-radius:18px;
        padding:16px;
        transition:.18s ease;
    }
    .crm-action:hover{
        transform:translateY(-1px);
        border-color:#cbd5e1;
        box-shadow:0 10px 18px rgba(15,23,42,.06);
        text-decoration:none;
        color:inherit;
    }
    .crm-action__icon{
        width:44px;height:44px;border-radius:14px;
        display:flex;align-items:center;justify-content:center;
        background:var(--crm-soft-2);color:var(--crm-primary);
        flex:0 0 44px;
    }
    .crm-action__title{font-size:15px;font-weight:900;color:var(--crm-text);margin-bottom:4px;}
    .crm-action__text{font-size:13px;line-height:1.55;color:var(--crm-muted);}

    .crm-module{
        height:100%;
        border:1px solid var(--crm-line);
        border-radius:22px;
        padding:18px;
        background:#fff;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .crm-module__icon{
        width:52px;height:52px;border-radius:16px;
        display:flex;align-items:center;justify-content:center;
        background:var(--crm-soft);
        color:var(--crm-primary);
        margin-bottom:14px;
    }
    .crm-module__title{
        font-size:18px;
        font-weight:900;
        color:var(--crm-text);
        line-height:1.15;
        margin-bottom:8px;
    }
    .crm-module__desc{
        font-size:14px;
        line-height:1.6;
        color:var(--crm-muted);
        min-height:88px;
        margin-bottom:14px;
    }
    .crm-tags{display:flex;flex-wrap:wrap;gap:8px;}
    .crm-tag{
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

    .crm-roadmap{
        display:grid;
        gap:12px;
    }
    .crm-step{
        display:flex;
        gap:12px;
        align-items:flex-start;
        padding:14px;
        border:1px solid var(--crm-line);
        border-radius:18px;
        background:#fff;
    }
    .crm-step__num{
        width:34px;height:34px;border-radius:999px;
        display:flex;align-items:center;justify-content:center;
        background:var(--crm-primary);color:#fff;font-weight:900;flex:0 0 34px;
    }
    .crm-step__title{font-size:15px;font-weight:900;color:var(--crm-text);margin-bottom:3px;}
    .crm-step__text{font-size:13px;line-height:1.55;color:var(--crm-muted);}

    .crm-ai-grid{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:16px;
    }
    .crm-ai-card{
        border:1px solid var(--crm-line);
        border-radius:22px;
        padding:18px;
        background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);
    }
    .crm-ai-card__badge{
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
    .crm-ai-card__title{
        font-size:17px;
        font-weight:900;
        color:var(--crm-text);
        line-height:1.2;
        margin-bottom:8px;
    }
    .crm-ai-card__text{
        font-size:14px;
        line-height:1.6;
        color:var(--crm-muted);
    }

    .crm-note{
        margin-top:16px;
        padding:14px 16px;
        border-radius:18px;
        border:1px dashed #cbd5e1;
        background:#f8fafc;
        color:var(--crm-muted);
        font-size:13px;
        line-height:1.6;
    }

    @media (max-width: 1199.98px){
        .crm-grid--kpi{grid-template-columns:repeat(2,minmax(0,1fr));}
        .crm-grid--modules{grid-template-columns:repeat(2,minmax(0,1fr));}
        .crm-ai-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    }
    @media (max-width: 991.98px){
        .crm-grid--2{grid-template-columns:1fr;}
    }
    @media (max-width: 767.98px){
        .crm-shell{padding:18px 0 24px;}
        .crm-hero{padding:22px;}
        .crm-grid--kpi,
        .crm-grid--modules,
        .crm-ai-grid,
        .crm-actions{grid-template-columns:1fr;}
        .crm-module__desc{min-height:auto;}
    }
</style>

<div class="container-fluid crm-shell">
    <section class="crm-hero">
        <div class="crm-hero__eyebrow">
            <span class="crm-hero__eyebrow-dot"></span>
            <span>CRM + VENTAS + FACTURACIÓN + IA</span>
        </div>

        <h1>CRM</h1>

        <p>
            Administra clientes, contactos, oportunidades y actividades comerciales desde un solo módulo.
            Esta base de CRM está pensada para conectarse con ventas, tickets, facturación y reportes,
            permitiendo que Pactopia360 tenga un flujo comercial real, simple al inicio y listo para crecer.
        </p>

        <div class="crm-hero__chips">
            <span class="crm-chip">Clientes</span>
            <span class="crm-chip">Contactos</span>
            <span class="crm-chip">Oportunidades</span>
            <span class="crm-chip">Actividades</span>
            <span class="crm-chip">Ventas conectadas</span>
            <span class="crm-chip">Facturación conectada</span>
        </div>
    </section>

    <div class="crm-grid crm-grid--kpi">
        @foreach($kpis as $kpi)
            <div class="crm-kpi">
                <div class="crm-kpi__label">{{ $kpi['label'] }}</div>
                <div class="crm-kpi__value">{{ $kpi['value'] }}</div>
                <div class="crm-kpi__hint">{{ $kpi['hint'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="crm-grid crm-grid--2">
        <div class="crm-card">
            <div class="crm-card__body">
                <div class="crm-card__head">
                    <div>
                        <h2 class="crm-card__title">Centro de operación CRM</h2>
                        <div class="crm-card__sub">
                            Primera versión simple pero completa para dejar el CRM listo para usarse y crecer por capas.
                        </div>
                    </div>
                </div>

                <div class="crm-actions">
                    <a href="{{ $rtVentas }}" class="crm-action">
                        <div class="crm-action__icon"><span class="material-symbols-outlined">point_of_sale</span></div>
                        <div>
                            <div class="crm-action__title">Ir a Ventas</div>
                            <div class="crm-action__text">Relaciona tickets, códigos de venta y seguimiento comercial con tus clientes.</div>
                        </div>
                    </a>

                    <a href="{{ $rtFact }}" class="crm-action">
                        <div class="crm-action__icon"><span class="material-symbols-outlined">receipt_long</span></div>
                        <div>
                            <div class="crm-action__title">Ir a Facturación</div>
                            <div class="crm-action__text">Consulta CFDI, historial comercial y comportamiento de facturación del cliente.</div>
                        </div>
                    </a>

                    <a href="{{ $rtNuevoCfdi }}" class="crm-action">
                        <div class="crm-action__icon"><span class="material-symbols-outlined">add_circle</span></div>
                        <div>
                            <div class="crm-action__title">Nuevo CFDI</div>
                            <div class="crm-action__text">Acceso corto para emitir una nueva factura manual y conectar el flujo comercial.</div>
                        </div>
                    </a>

                    <a href="{{ $rtReportes }}" class="crm-action">
                        <div class="crm-action__icon"><span class="material-symbols-outlined">monitoring</span></div>
                        <div>
                            <div class="crm-action__title">Ir a Reportes</div>
                            <div class="crm-action__text">Cruza métricas comerciales, conversión, ventas y comportamiento de clientes.</div>
                        </div>
                    </a>
                </div>

                <div class="crm-note">
                    <strong>Regla del producto:</strong> este CRM será el centro comercial base de Pactopia360 y se conectará
                    con <strong>Ventas</strong> y <strong>Facturación</strong> para evitar doble captura y mejorar seguimiento.
                </div>
            </div>
        </div>

        <div class="crm-card">
            <div class="crm-card__body">
                <div class="crm-card__head">
                    <div>
                        <h2 class="crm-card__title">Ruta de implementación</h2>
                        <div class="crm-card__sub">
                            Dejamos el módulo completo en estructura para activarlo por capas sin romper tu proyecto.
                        </div>
                    </div>
                </div>

                <div class="crm-roadmap">
                    <div class="crm-step">
                        <div class="crm-step__num">1</div>
                        <div>
                            <div class="crm-step__title">Base CRM</div>
                            <div class="crm-step__text">Clientes, contactos, oportunidades y actividades en web + móvil.</div>
                        </div>
                    </div>

                    <div class="crm-step">
                        <div class="crm-step__num">2</div>
                        <div>
                            <div class="crm-step__title">Conexión con Ventas</div>
                            <div class="crm-step__text">Relacionar cliente, ticket, código de venta y comportamiento comercial.</div>
                        </div>
                    </div>

                    <div class="crm-step">
                        <div class="crm-step__num">3</div>
                        <div>
                            <div class="crm-step__title">Conexión con Facturación</div>
                            <div class="crm-step__text">Visualizar CFDI emitidos, receptores, facturas y actividad administrativa.</div>
                        </div>
                    </div>

                    <div class="crm-step">
                        <div class="crm-step__num">4</div>
                        <div>
                            <div class="crm-step__title">IA comercial</div>
                            <div class="crm-step__text">Prioridad, alertas, oportunidades frías y recomendaciones de seguimiento.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="crm-grid crm-grid--modules">
        @foreach($sections as $section)
            <div class="crm-module">
                <div class="crm-module__icon">
                    <span class="material-symbols-outlined">{{ $section['icon'] }}</span>
                </div>

                <div class="crm-module__title">{{ $section['title'] }}</div>

                <div class="crm-module__desc">
                    {{ $section['desc'] }}
                </div>

                <div class="crm-tags">
                    @foreach($section['items'] as $item)
                        <span class="crm-tag">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="crm-card mb-4">
        <div class="crm-card__body">
            <div class="crm-card__head">
                <div>
                    <h2 class="crm-card__title">IA aplicada al CRM</h2>
                    <div class="crm-card__sub">
                        El CRM debe ayudarte a vender mejor, no solo a capturar información.
                    </div>
                </div>
            </div>

            <div class="crm-ai-grid">
                @foreach($aiCards as $card)
                    <div class="crm-ai-card">
                        <div class="crm-ai-card__badge">IA Pactopia360</div>
                        <div class="crm-ai-card__title">{{ $card['title'] }}</div>
                        <div class="crm-ai-card__text">{{ $card['desc'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="crm-note">
                <strong>Idea comercial fuerte:</strong> este CRM debe priorizar oportunidades, sugerir seguimientos y detectar clientes listos para comprar o facturar.
            </div>
        </div>
    </div>
</div>
@endsection