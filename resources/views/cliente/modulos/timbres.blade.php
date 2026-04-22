@extends('layouts.cliente')

@section('title', 'Timbres / Hits')

@section('content')
@php
    $rtFact = Route::has('cliente.facturacion.index')
        ? route('cliente.facturacion.index')
        : (Route::has('cliente.facturacion') ? route('cliente.facturacion') : '#');

    $rtRh = Route::has('cliente.modulos.rh') ? route('cliente.modulos.rh') : '#';
    $rtReportes = Route::has('cliente.modulos.reportes') ? route('cliente.modulos.reportes') : '#';
    $rtSat = Route::has('cliente.sat.index') ? route('cliente.sat.index') : '#';

    $kpis = [
        ['label' => 'Hits disponibles', 'value' => '0', 'hint' => 'Saldo actual de timbrado'],
        ['label' => 'Consumidos', 'value' => '0', 'hint' => 'Uso acumulado'],
        ['label' => 'Compras', 'value' => '0', 'hint' => 'Órdenes registradas'],
        ['label' => 'CFDI emitidos/cancelados', 'value' => '0', 'hint' => 'Cada uno consume 1 hit'],
    ];

    $sections = [
        [
            'title' => 'Saldo y consumo',
            'icon' => 'local_activity',
            'desc' => 'Visualización del saldo actual de hits/timbres, histórico de consumo y comportamiento general del timbrado.',
            'items' => ['Saldo', 'Consumo', 'Histórico', 'Timbrado', 'Cancelaciones', 'Bitácora'],
        ],
        [
            'title' => 'Compra de timbres',
            'icon' => 'shopping_cart',
            'desc' => 'Cotización y compra de paquetes de timbres por rango, contemplando precios base y precios especiales.',
            'items' => ['Paquetes', 'Rangos', 'Cotización', 'Precio especial', 'Pago', 'Seguimiento'],
        ],
        [
            'title' => 'Facturotopia',
            'icon' => 'hub',
            'desc' => 'Configuración futura de conexión con Facturotopia para API key pruebas/producción y estatus del emisor.',
            'items' => ['Sandbox', 'Producción', 'API keys', 'Emisor', 'Configuración', 'Sincronización'],
        ],
        [
            'title' => 'Facturación conectada',
            'icon' => 'receipt_long',
            'desc' => 'Los CFDI de facturación consumirán hits al emitir o cancelar comprobantes desde Pactopia360.',
            'items' => ['CFDI', 'Emisión', 'Cancelación', 'Consumo', 'Relación', 'Control'],
        ],
        [
            'title' => 'RH y nómina conectados',
            'icon' => 'badge',
            'desc' => 'El CFDI de nómina dentro de RH también consumirá hits al timbrar o cancelar recibos.',
            'items' => ['RH', 'Nómina', 'CFDI nómina', 'Timbrado', 'Cancelación', 'Seguimiento'],
        ],
        [
            'title' => 'Dashboard y análisis',
            'icon' => 'bar_chart',
            'desc' => 'Vista de compras, consumos, tendencias, alertas de bajo saldo y comportamiento de uso del cliente.',
            'items' => ['Compras', 'Consumos', 'Tendencias', 'Alertas', 'Comparativos', 'Análisis'],
        ],
    ];

    $aiCards = [
        [
            'title' => 'Auditor IA de consumo',
            'desc' => 'Detecta consumos atípicos, caídas bruscas de saldo y patrones raros de emisión o cancelación.',
        ],
        [
            'title' => 'Proyección de compra',
            'desc' => 'Estima cuándo el cliente podría quedarse sin hits según su ritmo de uso.',
        ],
        [
            'title' => 'Radar de oportunidad',
            'desc' => 'Sugiere el paquete más conveniente según consumo histórico y comportamiento comercial.',
        ],
    ];
@endphp

<style>
    .tim-shell{
        --tim-card:#ffffff;
        --tim-line:#e2e8f0;
        --tim-text:#0f172a;
        --tim-muted:#64748b;
        --tim-primary:#0f172a;
        --tim-soft:#eef2ff;
        --tim-soft-2:#eff6ff;
    }
    .tim-shell{padding:24px 0 30px;}
    .tim-hero{
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
    .tim-hero__eyebrow{
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
    .tim-hero__eyebrow-dot{
        width:10px;height:10px;border-radius:999px;background:#fff;display:inline-block;
        box-shadow:0 0 0 6px rgba(255,255,255,.08);
    }
    .tim-hero h1{
        font-size:clamp(30px, 4vw, 46px);
        line-height:1;
        font-weight:900;
        letter-spacing:-.04em;
        margin:0 0 10px 0;
        color:#fff;
    }
    .tim-hero p{
        max-width:860px;
        color:rgba(255,255,255,.92);
        font-size:15px;
        line-height:1.65;
        margin:0;
    }
    .tim-hero__chips{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        margin-top:18px;
    }
    .tim-chip{
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

    .tim-grid{display:grid;gap:16px;}
    .tim-grid--kpi{
        grid-template-columns:repeat(4,minmax(0,1fr));
        margin-bottom:20px;
    }
    .tim-grid--2{
        grid-template-columns:1.25fr .95fr;
        margin-bottom:20px;
    }
    .tim-grid--modules{
        grid-template-columns:repeat(3,minmax(0,1fr));
        margin-bottom:20px;
    }

    .tim-card{
        background:var(--tim-card);
        border:1px solid var(--tim-line);
        border-radius:24px;
        box-shadow:0 10px 24px rgba(15,23,42,.04);
    }
    .tim-card__body{padding:20px;}
    .tim-card__head{
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:14px;
        margin-bottom:14px;
    }
    .tim-card__title{
        margin:0;
        color:var(--tim-text);
        font-size:20px;
        line-height:1.1;
        font-weight:900;
        letter-spacing:-.03em;
    }
    .tim-card__sub{
        color:var(--tim-muted);
        font-size:14px;
        line-height:1.55;
        margin-top:6px;
    }

    .tim-kpi{
        height:100%;
        background:#fff;
        border:1px solid var(--tim-line);
        border-radius:22px;
        padding:18px;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .tim-kpi__label{font-size:13px;font-weight:800;color:var(--tim-muted);margin-bottom:8px;}
    .tim-kpi__value{font-size:34px;line-height:1;font-weight:900;color:var(--tim-text);margin-bottom:8px;}
    .tim-kpi__hint{font-size:12px;color:var(--tim-muted);}

    .tim-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }
    .tim-action{
        display:flex;
        align-items:flex-start;
        gap:12px;
        text-decoration:none;
        color:inherit;
        border:1px solid var(--tim-line);
        background:#fff;
        border-radius:18px;
        padding:16px;
        transition:.18s ease;
    }
    .tim-action:hover{
        transform:translateY(-1px);
        border-color:#cbd5e1;
        box-shadow:0 10px 18px rgba(15,23,42,.06);
        text-decoration:none;
        color:inherit;
    }
    .tim-action__icon{
        width:44px;height:44px;border-radius:14px;
        display:flex;align-items:center;justify-content:center;
        background:var(--tim-soft-2);color:var(--tim-primary);
        flex:0 0 44px;
    }
    .tim-action__title{font-size:15px;font-weight:900;color:var(--tim-text);margin-bottom:4px;}
    .tim-action__text{font-size:13px;line-height:1.55;color:var(--tim-muted);}

    .tim-module{
        height:100%;
        border:1px solid var(--tim-line);
        border-radius:22px;
        padding:18px;
        background:#fff;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .tim-module__icon{
        width:52px;height:52px;border-radius:16px;
        display:flex;align-items:center;justify-content:center;
        background:var(--tim-soft);
        color:var(--tim-primary);
        margin-bottom:14px;
    }
    .tim-module__title{
        font-size:18px;
        font-weight:900;
        color:var(--tim-text);
        line-height:1.15;
        margin-bottom:8px;
    }
    .tim-module__desc{
        font-size:14px;
        line-height:1.6;
        color:var(--tim-muted);
        min-height:88px;
        margin-bottom:14px;
    }
    .tim-tags{display:flex;flex-wrap:wrap;gap:8px;}
    .tim-tag{
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

    .tim-roadmap{
        display:grid;
        gap:12px;
    }
    .tim-step{
        display:flex;
        gap:12px;
        align-items:flex-start;
        padding:14px;
        border:1px solid var(--tim-line);
        border-radius:18px;
        background:#fff;
    }
    .tim-step__num{
        width:34px;height:34px;border-radius:999px;
        display:flex;align-items:center;justify-content:center;
        background:var(--tim-primary);color:#fff;font-weight:900;flex:0 0 34px;
    }
    .tim-step__title{font-size:15px;font-weight:900;color:var(--tim-text);margin-bottom:3px;}
    .tim-step__text{font-size:13px;line-height:1.55;color:var(--tim-muted);}

    .tim-ai-grid{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:16px;
    }
    .tim-ai-card{
        border:1px solid var(--tim-line);
        border-radius:22px;
        padding:18px;
        background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);
    }
    .tim-ai-card__badge{
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
    .tim-ai-card__title{
        font-size:17px;
        font-weight:900;
        color:var(--tim-text);
        line-height:1.2;
        margin-bottom:8px;
    }
    .tim-ai-card__text{
        font-size:14px;
        line-height:1.6;
        color:var(--tim-muted);
    }

    .tim-note{
        margin-top:16px;
        padding:14px 16px;
        border-radius:18px;
        border:1px dashed #cbd5e1;
        background:#f8fafc;
        color:var(--tim-muted);
        font-size:13px;
        line-height:1.6;
    }

    @media (max-width: 1199.98px){
        .tim-grid--kpi{grid-template-columns:repeat(2,minmax(0,1fr));}
        .tim-grid--modules{grid-template-columns:repeat(2,minmax(0,1fr));}
        .tim-ai-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    }
    @media (max-width: 991.98px){
        .tim-grid--2{grid-template-columns:1fr;}
    }
    @media (max-width: 767.98px){
        .tim-shell{padding:18px 0 24px;}
        .tim-hero{padding:22px;}
        .tim-grid--kpi,
        .tim-grid--modules,
        .tim-ai-grid,
        .tim-actions{grid-template-columns:1fr;}
        .tim-module__desc{min-height:auto;}
    }
</style>

<div class="container-fluid tim-shell">
    <section class="tim-hero">
        <div class="tim-hero__eyebrow">
            <span class="tim-hero__eyebrow-dot"></span>
            <span>TIMBRES / HITS + FACTUROTOPIA + IA</span>
        </div>

        <h1>Timbres / Hits</h1>

        <p>
            Administra el saldo, consumo, compras y comportamiento de timbrado desde un solo módulo.
            Esta base está pensada para conectar Pactopia360 con Facturotopia, primero con operación asistida
            y correo a soporte, y después con automatización completa por API.
        </p>

        <div class="tim-hero__chips">
            <span class="tim-chip">Saldo</span>
            <span class="tim-chip">Consumo</span>
            <span class="tim-chip">Compra</span>
            <span class="tim-chip">Facturotopia</span>
            <span class="tim-chip">CFDI</span>
            <span class="tim-chip">IA operativa</span>
        </div>
    </section>

    <div class="tim-grid tim-grid--kpi">
        @foreach($kpis as $kpi)
            <div class="tim-kpi">
                <div class="tim-kpi__label">{{ $kpi['label'] }}</div>
                <div class="tim-kpi__value">{{ $kpi['value'] }}</div>
                <div class="tim-kpi__hint">{{ $kpi['hint'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="tim-grid tim-grid--2">
        <div class="tim-card">
            <div class="tim-card__body">
                <div class="tim-card__head">
                    <div>
                        <h2 class="tim-card__title">Centro de operación Timbres / Hits</h2>
                        <div class="tim-card__sub">
                            Primera versión simple pero completa para dejar el módulo listo para cotizar, registrar compras y controlar consumo.
                        </div>
                    </div>
                </div>

                <div class="tim-actions">
                    <a href="{{ $rtFact }}" class="tim-action">
                        <div class="tim-action__icon"><span class="material-symbols-outlined">receipt_long</span></div>
                        <div>
                            <div class="tim-action__title">Ir a Facturación</div>
                            <div class="tim-action__text">Conecta emisión y cancelación CFDI con consumo real de hits.</div>
                        </div>
                    </a>

                    <a href="{{ $rtRh }}" class="tim-action">
                        <div class="tim-action__icon"><span class="material-symbols-outlined">badge</span></div>
                        <div>
                            <div class="tim-action__title">Ir a Recursos Humanos</div>
                            <div class="tim-action__text">Relaciona el timbrado de nómina con RH y CFDI nómina dentro del mismo ecosistema.</div>
                        </div>
                    </a>

                    <a href="{{ $rtReportes }}" class="tim-action">
                        <div class="tim-action__icon"><span class="material-symbols-outlined">monitoring</span></div>
                        <div>
                            <div class="tim-action__title">Ir a Reportes</div>
                            <div class="tim-action__text">Cruza compras, consumos, alertas y comportamiento de timbrado.</div>
                        </div>
                    </a>

                    <a href="{{ $rtSat }}" class="tim-action">
                        <div class="tim-action__icon"><span class="material-symbols-outlined">cloud_download</span></div>
                        <div>
                            <div class="tim-action__title">Ir a SAT</div>
                            <div class="tim-action__text">Relaciona operación SAT, documentación y servicios fiscales con la cuenta.</div>
                        </div>
                    </a>
                </div>

                <div class="tim-note">
                    <strong>Regla del producto:</strong> cada <strong>CFDI emitido</strong> o <strong>cancelación</strong> consume
                    <strong>1 hit/timbre</strong>. Los pagos y cobros se controlan en <strong>Pactopia360</strong>, mientras la
                    asignación inicial puede operarse por soporte hasta automatizar la API.
                </div>
            </div>
        </div>

        <div class="tim-card">
            <div class="tim-card__body">
                <div class="tim-card__head">
                    <div>
                        <h2 class="tim-card__title">Ruta de implementación</h2>
                        <div class="tim-card__sub">
                            Dejamos el módulo completo en estructura para activarlo por capas sin romper tu proyecto.
                        </div>
                    </div>
                </div>

                <div class="tim-roadmap">
                    <div class="tim-step">
                        <div class="tim-step__num">1</div>
                        <div>
                            <div class="tim-step__title">Base de saldo y consumo</div>
                            <div class="tim-step__text">Dashboard, historial, alertas y comportamiento de timbrado en web + móvil.</div>
                        </div>
                    </div>

                    <div class="tim-step">
                        <div class="tim-step__num">2</div>
                        <div>
                            <div class="tim-step__title">Compra de paquetes</div>
                            <div class="tim-step__text">Cotización, pago y notificación a soporte con precios por rango y precio especial.</div>
                        </div>
                    </div>

                    <div class="tim-step">
                        <div class="tim-step__num">3</div>
                        <div>
                            <div class="tim-step__title">Conexión Facturotopia</div>
                            <div class="tim-step__text">Configurar API keys sandbox/producción y sincronización de emisor.</div>
                        </div>
                    </div>

                    <div class="tim-step">
                        <div class="tim-step__num">4</div>
                        <div>
                            <div class="tim-step__title">Automatización</div>
                            <div class="tim-step__text">Asignación automática, consumo en tiempo real y control integral por API.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tim-grid tim-grid--modules">
        @foreach($sections as $section)
            <div class="tim-module">
                <div class="tim-module__icon">
                    <span class="material-symbols-outlined">{{ $section['icon'] }}</span>
                </div>

                <div class="tim-module__title">{{ $section['title'] }}</div>

                <div class="tim-module__desc">
                    {{ $section['desc'] }}
                </div>

                <div class="tim-tags">
                    @foreach($section['items'] as $item)
                        <span class="tim-tag">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="tim-card mb-4">
        <div class="tim-card__body">
            <div class="tim-card__head">
                <div>
                    <h2 class="tim-card__title">IA aplicada a Timbres / Hits</h2>
                    <div class="tim-card__sub">
                        Este módulo debe ayudarte a prevenir faltantes, detectar anomalías y recomendar compras.
                    </div>
                </div>
            </div>

            <div class="tim-ai-grid">
                @foreach($aiCards as $card)
                    <div class="tim-ai-card">
                        <div class="tim-ai-card__badge">IA Pactopia360</div>
                        <div class="tim-ai-card__title">{{ $card['title'] }}</div>
                        <div class="tim-ai-card__text">{{ $card['desc'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="tim-note">
                <strong>Idea comercial fuerte:</strong> el módulo debe anticipar cuándo comprar, detectar consumos raros y sugerir el paquete más conveniente según uso real.
            </div>
        </div>
    </div>
</div>
@endsection