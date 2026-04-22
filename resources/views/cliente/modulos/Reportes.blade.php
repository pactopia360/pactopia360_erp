@extends('layouts.cliente')

@section('title', 'Reportes')

@section('content')
@php
    $rtCrm        = Route::has('cliente.modulos.crm') ? route('cliente.modulos.crm') : '#';
    $rtInventario = Route::has('cliente.modulos.inventario') ? route('cliente.modulos.inventario') : '#';
    $rtVentas     = Route::has('cliente.modulos.ventas') ? route('cliente.modulos.ventas') : '#';
    $rtFact       = Route::has('cliente.facturacion.index')
        ? route('cliente.facturacion.index')
        : (Route::has('cliente.facturacion') ? route('cliente.facturacion') : '#');
    $rtRh         = Route::has('cliente.modulos.rh') ? route('cliente.modulos.rh') : '#';
    $rtTimbres    = Route::has('cliente.modulos.timbres') ? route('cliente.modulos.timbres') : '#';
    $rtSat        = Route::has('cliente.sat.index') ? route('cliente.sat.index') : '#';

    $kpis = [
        ['label' => 'Reportes activos', 'value' => '0', 'hint' => 'Base analítica inicial'],
        ['label' => 'Módulos conectados', 'value' => '7', 'hint' => 'Visión transversal'],
        ['label' => 'Alertas detectadas', 'value' => '0', 'hint' => 'Riesgos y seguimiento'],
        ['label' => 'Indicadores clave', 'value' => '0', 'hint' => 'KPIs del negocio'],
    ];

    $sections = [
        [
            'title' => 'Dashboard ejecutivo',
            'icon' => 'dashboard',
            'desc' => 'Vista general de métricas, comportamiento comercial, facturación, consumos, alertas y seguimiento operativo.',
            'items' => ['KPIs', 'Tendencias', 'Indicadores', 'Resumen', 'Comparativos', 'Alertas'],
        ],
        [
            'title' => 'Comercial y CRM',
            'icon' => 'groups',
            'desc' => 'Análisis de clientes, oportunidades, conversión, seguimiento y comportamiento comercial conectado al CRM.',
            'items' => ['Clientes', 'Pipeline', 'Conversión', 'Seguimiento', 'Oportunidades', 'Actividad'],
        ],
        [
            'title' => 'Ventas e inventario',
            'icon' => 'bar_chart',
            'desc' => 'Cruce de ventas, tickets, productos, rotación, stock crítico y comportamiento de consumo.',
            'items' => ['Ventas', 'Tickets', 'Productos', 'Rotación', 'Stock', 'Top ventas'],
        ],
        [
            'title' => 'Facturación y CFDI',
            'icon' => 'receipt_long',
            'desc' => 'Visualización de CFDI, comportamiento de emisión, facturas solicitadas, historial y resultados administrativos.',
            'items' => ['CFDI', 'Facturas', 'Receptores', 'Cobranza', 'Historial', 'Tendencias'],
        ],
        [
            'title' => 'RH y nómina',
            'icon' => 'badge',
            'desc' => 'Indicadores de empleados, incidencias, nóminas, CFDI de nómina y comportamiento operativo de RH.',
            'items' => ['Empleados', 'Incidencias', 'Nómina', 'CFDI nómina', 'Finiquitos', 'Seguimiento'],
        ],
        [
            'title' => 'Timbres / Hits y SAT',
            'icon' => 'local_activity',
            'desc' => 'Seguimiento de compra, consumo, timbrado, cancelaciones, SAT y comportamiento documental.',
            'items' => ['Timbres', 'Hits', 'Consumo', 'SAT', 'Bóveda', 'Alertas'],
        ],
    ];

    $aiCards = [
        [
            'title' => 'IA analítica',
            'desc' => 'Resume tendencias, identifica cambios importantes y ayuda a detectar focos rojos operativos.',
        ],
        [
            'title' => 'Radar de oportunidades',
            'desc' => 'Detecta módulos con mayor movimiento y cruza señales comerciales, financieras y operativas.',
        ],
        [
            'title' => 'Auditor inteligente',
            'desc' => 'Señala anomalías en ventas, facturación, timbres, RH o inventario antes de que escalen.',
        ],
    ];
@endphp

<style>
    .rep-shell{
        --rep-card:#ffffff;
        --rep-line:#e2e8f0;
        --rep-text:#0f172a;
        --rep-muted:#64748b;
        --rep-primary:#0f172a;
        --rep-soft:#eef2ff;
        --rep-soft-2:#eff6ff;
    }
    .rep-shell{padding:24px 0 30px;}
    .rep-hero{
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
    .rep-hero__eyebrow{
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
    .rep-hero__eyebrow-dot{
        width:10px;height:10px;border-radius:999px;background:#fff;display:inline-block;
        box-shadow:0 0 0 6px rgba(255,255,255,.08);
    }
    .rep-hero h1{
        font-size:clamp(30px, 4vw, 46px);
        line-height:1;
        font-weight:900;
        letter-spacing:-.04em;
        margin:0 0 10px 0;
        color:#fff;
    }
    .rep-hero p{
        max-width:860px;
        color:rgba(255,255,255,.92);
        font-size:15px;
        line-height:1.65;
        margin:0;
    }
    .rep-hero__chips{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        margin-top:18px;
    }
    .rep-chip{
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

    .rep-grid{display:grid;gap:16px;}
    .rep-grid--kpi{
        grid-template-columns:repeat(4,minmax(0,1fr));
        margin-bottom:20px;
    }
    .rep-grid--2{
        grid-template-columns:1.25fr .95fr;
        margin-bottom:20px;
    }
    .rep-grid--modules{
        grid-template-columns:repeat(3,minmax(0,1fr));
        margin-bottom:20px;
    }

    .rep-card{
        background:var(--rep-card);
        border:1px solid var(--rep-line);
        border-radius:24px;
        box-shadow:0 10px 24px rgba(15,23,42,.04);
    }
    .rep-card__body{padding:20px;}
    .rep-card__head{
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:14px;
        margin-bottom:14px;
    }
    .rep-card__title{
        margin:0;
        color:var(--rep-text);
        font-size:20px;
        line-height:1.1;
        font-weight:900;
        letter-spacing:-.03em;
    }
    .rep-card__sub{
        color:var(--rep-muted);
        font-size:14px;
        line-height:1.55;
        margin-top:6px;
    }

    .rep-kpi{
        height:100%;
        background:#fff;
        border:1px solid var(--rep-line);
        border-radius:22px;
        padding:18px;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .rep-kpi__label{font-size:13px;font-weight:800;color:var(--rep-muted);margin-bottom:8px;}
    .rep-kpi__value{font-size:34px;line-height:1;font-weight:900;color:var(--rep-text);margin-bottom:8px;}
    .rep-kpi__hint{font-size:12px;color:var(--rep-muted);}

    .rep-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }
    .rep-action{
        display:flex;
        align-items:flex-start;
        gap:12px;
        text-decoration:none;
        color:inherit;
        border:1px solid var(--rep-line);
        background:#fff;
        border-radius:18px;
        padding:16px;
        transition:.18s ease;
    }
    .rep-action:hover{
        transform:translateY(-1px);
        border-color:#cbd5e1;
        box-shadow:0 10px 18px rgba(15,23,42,.06);
        text-decoration:none;
        color:inherit;
    }
    .rep-action__icon{
        width:44px;height:44px;border-radius:14px;
        display:flex;align-items:center;justify-content:center;
        background:var(--rep-soft-2);color:var(--rep-primary);
        flex:0 0 44px;
    }
    .rep-action__title{font-size:15px;font-weight:900;color:var(--rep-text);margin-bottom:4px;}
    .rep-action__text{font-size:13px;line-height:1.55;color:var(--rep-muted);}

    .rep-module{
        height:100%;
        border:1px solid var(--rep-line);
        border-radius:22px;
        padding:18px;
        background:#fff;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .rep-module__icon{
        width:52px;height:52px;border-radius:16px;
        display:flex;align-items:center;justify-content:center;
        background:var(--rep-soft);
        color:var(--rep-primary);
        margin-bottom:14px;
    }
    .rep-module__title{
        font-size:18px;
        font-weight:900;
        color:var(--rep-text);
        line-height:1.15;
        margin-bottom:8px;
    }
    .rep-module__desc{
        font-size:14px;
        line-height:1.6;
        color:var(--rep-muted);
        min-height:88px;
        margin-bottom:14px;
    }
    .rep-tags{display:flex;flex-wrap:wrap;gap:8px;}
    .rep-tag{
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

    .rep-roadmap{
        display:grid;
        gap:12px;
    }
    .rep-step{
        display:flex;
        gap:12px;
        align-items:flex-start;
        padding:14px;
        border:1px solid var(--rep-line);
        border-radius:18px;
        background:#fff;
    }
    .rep-step__num{
        width:34px;height:34px;border-radius:999px;
        display:flex;align-items:center;justify-content:center;
        background:var(--rep-primary);color:#fff;font-weight:900;flex:0 0 34px;
    }
    .rep-step__title{font-size:15px;font-weight:900;color:var(--rep-text);margin-bottom:3px;}
    .rep-step__text{font-size:13px;line-height:1.55;color:var(--rep-muted);}

    .rep-ai-grid{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:16px;
    }
    .rep-ai-card{
        border:1px solid var(--rep-line);
        border-radius:22px;
        padding:18px;
        background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);
    }
    .rep-ai-card__badge{
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
    .rep-ai-card__title{
        font-size:17px;
        font-weight:900;
        color:var(--rep-text);
        line-height:1.2;
        margin-bottom:8px;
    }
    .rep-ai-card__text{
        font-size:14px;
        line-height:1.6;
        color:var(--rep-muted);
    }

    .rep-note{
        margin-top:16px;
        padding:14px 16px;
        border-radius:18px;
        border:1px dashed #cbd5e1;
        background:#f8fafc;
        color:var(--rep-muted);
        font-size:13px;
        line-height:1.6;
    }

    @media (max-width: 1199.98px){
        .rep-grid--kpi{grid-template-columns:repeat(2,minmax(0,1fr));}
        .rep-grid--modules{grid-template-columns:repeat(2,minmax(0,1fr));}
        .rep-ai-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    }
    @media (max-width: 991.98px){
        .rep-grid--2{grid-template-columns:1fr;}
    }
    @media (max-width: 767.98px){
        .rep-shell{padding:18px 0 24px;}
        .rep-hero{padding:22px;}
        .rep-grid--kpi,
        .rep-grid--modules,
        .rep-ai-grid,
        .rep-actions{grid-template-columns:1fr;}
        .rep-module__desc{min-height:auto;}
    }
</style>

<div class="container-fluid rep-shell">
    <section class="rep-hero">
        <div class="rep-hero__eyebrow">
            <span class="rep-hero__eyebrow-dot"></span>
            <span>REPORTES + KPIs + IA + VISIÓN GLOBAL</span>
        </div>

        <h1>Reportes</h1>

        <p>
            Centraliza los indicadores, métricas y análisis del negocio en un solo módulo.
            Esta base de reportes está pensada para conectarse con CRM, inventario, ventas,
            facturación, RH, timbres y SAT para darte una visión ejecutiva y operativa real,
            simple al inicio y lista para crecer.
        </p>

        <div class="rep-hero__chips">
            <span class="rep-chip">KPIs</span>
            <span class="rep-chip">Dashboards</span>
            <span class="rep-chip">Comparativos</span>
            <span class="rep-chip">Alertas</span>
            <span class="rep-chip">Cruce de módulos</span>
            <span class="rep-chip">IA analítica</span>
        </div>
    </section>

    <div class="rep-grid rep-grid--kpi">
        @foreach($kpis as $kpi)
            <div class="rep-kpi">
                <div class="rep-kpi__label">{{ $kpi['label'] }}</div>
                <div class="rep-kpi__value">{{ $kpi['value'] }}</div>
                <div class="rep-kpi__hint">{{ $kpi['hint'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="rep-grid rep-grid--2">
        <div class="rep-card">
            <div class="rep-card__body">
                <div class="rep-card__head">
                    <div>
                        <h2 class="rep-card__title">Centro de análisis</h2>
                        <div class="rep-card__sub">
                            Primera versión simple pero completa para dejar reportes listo como tablero transversal del ecosistema.
                        </div>
                    </div>
                </div>

                <div class="rep-actions">
                    <a href="{{ $rtCrm }}" class="rep-action">
                        <div class="rep-action__icon"><span class="material-symbols-outlined">groups</span></div>
                        <div>
                            <div class="rep-action__title">Ver CRM</div>
                            <div class="rep-action__text">Cruza clientes, oportunidades, seguimiento y comportamiento comercial.</div>
                        </div>
                    </a>

                    <a href="{{ $rtInventario }}" class="rep-action">
                        <div class="rep-action__icon"><span class="material-symbols-outlined">inventory_2</span></div>
                        <div>
                            <div class="rep-action__title">Ver Inventario</div>
                            <div class="rep-action__text">Consulta rotación, stock crítico, movimiento y desempeño de productos.</div>
                        </div>
                    </a>

                    <a href="{{ $rtVentas }}" class="rep-action">
                        <div class="rep-action__icon"><span class="material-symbols-outlined">point_of_sale</span></div>
                        <div>
                            <div class="rep-action__title">Ver Ventas</div>
                            <div class="rep-action__text">Cruza tickets, montos, frecuencia y comportamiento de compra.</div>
                        </div>
                    </a>

                    <a href="{{ $rtFact }}" class="rep-action">
                        <div class="rep-action__icon"><span class="material-symbols-outlined">receipt_long</span></div>
                        <div>
                            <div class="rep-action__title">Ver Facturación</div>
                            <div class="rep-action__text">Relaciona CFDI, cobranza, receptores y comportamiento fiscal.</div>
                        </div>
                    </a>
                </div>

                <div class="rep-note">
                    <strong>Regla del producto:</strong> este módulo debe ser el punto central de lectura del negocio,
                    cruzando información de <strong>CRM</strong>, <strong>Inventario</strong>, <strong>Ventas</strong>,
                    <strong>Facturación</strong>, <strong>RH</strong>, <strong>Timbres / Hits</strong> y <strong>SAT</strong>.
                </div>
            </div>
        </div>

        <div class="rep-card">
            <div class="rep-card__body">
                <div class="rep-card__head">
                    <div>
                        <h2 class="rep-card__title">Ruta de implementación</h2>
                        <div class="rep-card__sub">
                            Dejamos el módulo completo en estructura para activarlo por capas sin romper tu proyecto.
                        </div>
                    </div>
                </div>

                <div class="rep-roadmap">
                    <div class="rep-step">
                        <div class="rep-step__num">1</div>
                        <div>
                            <div class="rep-step__title">Base de reportes</div>
                            <div class="rep-step__text">KPIs, tarjetas, comparativos y dashboards iniciales en web + móvil.</div>
                        </div>
                    </div>

                    <div class="rep-step">
                        <div class="rep-step__num">2</div>
                        <div>
                            <div class="rep-step__title">Cruce comercial</div>
                            <div class="rep-step__text">CRM, ventas, clientes, productos y facturación en una sola lectura.</div>
                        </div>
                    </div>

                    <div class="rep-step">
                        <div class="rep-step__num">3</div>
                        <div>
                            <div class="rep-step__title">Cruce operativo</div>
                            <div class="rep-step__text">RH, nómina, timbres, SAT, stock y alertas de operación.</div>
                        </div>
                    </div>

                    <div class="rep-step">
                        <div class="rep-step__num">4</div>
                        <div>
                            <div class="rep-step__title">IA analítica</div>
                            <div class="rep-step__text">Tendencias, anomalías, focos rojos y sugerencias de acción.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="rep-grid rep-grid--modules">
        @foreach($sections as $section)
            <div class="rep-module">
                <div class="rep-module__icon">
                    <span class="material-symbols-outlined">{{ $section['icon'] }}</span>
                </div>

                <div class="rep-module__title">{{ $section['title'] }}</div>

                <div class="rep-module__desc">
                    {{ $section['desc'] }}
                </div>

                <div class="rep-tags">
                    @foreach($section['items'] as $item)
                        <span class="rep-tag">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="rep-card mb-4">
        <div class="rep-card__body">
            <div class="rep-card__head">
                <div>
                    <h2 class="rep-card__title">IA aplicada a Reportes</h2>
                    <div class="rep-card__sub">
                        Reportes debe ayudarte a interpretar el negocio, no solo a ver números.
                    </div>
                </div>
            </div>

            <div class="rep-ai-grid">
                @foreach($aiCards as $card)
                    <div class="rep-ai-card">
                        <div class="rep-ai-card__badge">IA Pactopia360</div>
                        <div class="rep-ai-card__title">{{ $card['title'] }}</div>
                        <div class="rep-ai-card__text">{{ $card['desc'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="rep-note">
                <strong>Idea comercial fuerte:</strong> reportes debe detectar tendencias, resumir focos rojos y sugerir dónde actuar primero.
            </div>
        </div>
    </div>

    <div class="rep-card">
        <div class="rep-card__body">
            <div class="rep-card__head">
                <div>
                    <h2 class="rep-card__title">Conexión con otros módulos</h2>
                    <div class="rep-card__sub">
                        Reportes se integrará con todo el ecosistema para formar una lectura ejecutiva y operativa real.
                    </div>
                </div>
            </div>

            <div class="rep-actions">
                <a href="{{ $rtRh }}" class="rep-action">
                    <div class="rep-action__icon"><span class="material-symbols-outlined">badge</span></div>
                    <div>
                        <div class="rep-action__title">Ver Recursos Humanos</div>
                        <div class="rep-action__text">Cruza empleados, incidencias, nómina y CFDI de nómina.</div>
                    </div>
                </a>

                <a href="{{ $rtTimbres }}" class="rep-action">
                    <div class="rep-action__icon"><span class="material-symbols-outlined">local_activity</span></div>
                    <div>
                        <div class="rep-action__title">Ver Timbres / Hits</div>
                        <div class="rep-action__text">Analiza compra, saldo, consumo y comportamiento de timbrado.</div>
                    </div>
                </a>

                <a href="{{ $rtSat }}" class="rep-action">
                    <div class="rep-action__icon"><span class="material-symbols-outlined">cloud_download</span></div>
                    <div>
                        <div class="rep-action__title">Ver SAT</div>
                        <div class="rep-action__text">Integra operación SAT, cotizaciones, bóveda y comportamiento documental.</div>
                    </div>
                </a>

                <a href="{{ $rtVentas }}" class="rep-action">
                    <div class="rep-action__icon"><span class="material-symbols-outlined">point_of_sale</span></div>
                    <div>
                        <div class="rep-action__title">Ver Ventas</div>
                        <div class="rep-action__text">Cruza tickets, montos y ritmo comercial para un análisis más claro.</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection