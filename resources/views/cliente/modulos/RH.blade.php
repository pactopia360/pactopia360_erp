@extends('layouts.cliente')

@section('title', 'Recursos Humanos')

@section('content')
@php
    $rtRh        = Route::has('cliente.modulos.rh') ? route('cliente.modulos.rh') : '#';
    $rtTimbres   = Route::has('cliente.modulos.timbres') ? route('cliente.modulos.timbres') : '#';
    $rtFact      = Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#';
    $rtSat       = Route::has('cliente.sat.index') ? route('cliente.sat.index') : '#';
    $rtVentas    = Route::has('cliente.modulos.ventas') ? route('cliente.modulos.ventas') : '#';
    $rtReportes  = Route::has('cliente.modulos.reportes') ? route('cliente.modulos.reportes') : '#';

    $kpis = [
        ['label' => 'Empleados activos', 'value' => '0', 'hint' => 'Base inicial RH'],
        ['label' => 'Nóminas por procesar', 'value' => '0', 'hint' => 'Ordinarias y extraordinarias'],
        ['label' => 'CFDI nómina emitidos', 'value' => '0', 'hint' => 'Timbrado desde RH'],
        ['label' => 'Hits disponibles', 'value' => '0', 'hint' => 'Consumo por timbre/cancelación'],
    ];

    $sections = [
        [
            'title' => 'Empleados',
            'icon' => 'people',
            'desc' => 'Administración de expedientes, altas, bajas, reingresos, cambios de salario, puesto, jornada, banco y centro de costo.',
            'items' => ['Altas', 'Bajas', 'Cambios', 'Historial laboral', 'Documentos', 'Estatus fiscal'],
        ],
        [
            'title' => 'Incidencias',
            'icon' => 'event_busy',
            'desc' => 'Control de retardos, faltas, vacaciones, incapacidades, bonos, comisiones, préstamos, descuentos y movimientos del periodo.',
            'items' => ['Asistencia', 'Vacaciones', 'Incapacidades', 'Bonos', 'Descuentos', 'Préstamos'],
        ],
        [
            'title' => 'Nómina',
            'icon' => 'payments',
            'desc' => 'Pre-nómina, cálculo, autorización, cierre y dispersión operativa para nóminas ordinarias y extraordinarias.',
            'items' => ['Ordinaria', 'Extraordinaria', 'Pre-nómina', 'Cálculo', 'Autorización', 'Cierre'],
        ],
        [
            'title' => 'CFDI Nómina',
            'icon' => 'receipt_long',
            'desc' => 'El CFDI de nómina vive dentro de RH. Aquí se controlará timbrado, cancelación, PDF/XML, reenvíos y consumo de hits.',
            'items' => ['Timbrado', 'Cancelación', 'PDF/XML', 'Reenvío', 'Historial SAT', 'Consumo de hits'],
        ],
        [
            'title' => 'Finiquitos y liquidaciones',
            'icon' => 'request_quote',
            'desc' => 'Cálculo guiado de finiquitos, liquidaciones, prestaciones proporcionales, escenarios y salida operativa para el cierre laboral.',
            'items' => ['Finiquito', 'Liquidación', 'Aguinaldo', 'Vacaciones', 'Prima vacacional', 'Prima antigüedad'],
        ],
        [
            'title' => 'Impuestos y obligaciones',
            'icon' => 'account_balance',
            'desc' => 'Vista operativa de ISR nómina, IMSS, INFONAVIT y obligaciones relacionadas para preparación, revisión y seguimiento.',
            'items' => ['ISR', 'IMSS', 'INFONAVIT', 'Obligaciones', 'Exportables', 'Bitácora'],
        ],
    ];

    $aiCards = [
        [
            'title' => 'Asistente IA RH',
            'desc' => 'Ayuda a detectar incidencias anómalas, empleados con datos incompletos, movimientos críticos y pendientes operativos.',
        ],
        [
            'title' => 'Simulador inteligente de nómina',
            'desc' => 'Permite proyectar impactos por bonos, aumentos, incidencias y escenarios antes de calcular o timbrar.',
        ],
        [
            'title' => 'Auditor de timbrado',
            'desc' => 'Detectará CFDI faltantes, cancelaciones, consumo inusual de hits y riesgos operativos antes del cierre.',
        ],
    ];
@endphp

<style>
    .rh-shell{
        --rh-bg:#f8fafc;
        --rh-card:#ffffff;
        --rh-line:#e2e8f0;
        --rh-text:#0f172a;
        --rh-muted:#64748b;
        --rh-primary:#0f172a;
        --rh-soft:#eef2ff;
        --rh-soft-2:#eff6ff;
        --rh-success:#166534;
        --rh-warning:#b45309;
        --rh-danger:#b91c1c;
    }
    .rh-shell{padding:24px 0 30px;}
    .rh-hero{
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
    .rh-hero__eyebrow{
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
    .rh-hero__eyebrow-dot{
        width:10px;height:10px;border-radius:999px;background:#fff;display:inline-block;
        box-shadow:0 0 0 6px rgba(255,255,255,.08);
    }
    .rh-hero h1{
        font-size:clamp(30px, 4vw, 46px);
        line-height:1;
        font-weight:900;
        letter-spacing:-.04em;
        margin:0 0 10px 0;
        color:#fff;
    }
    .rh-hero p{
        max-width:860px;
        color:rgba(255,255,255,.92);
        font-size:15px;
        line-height:1.65;
        margin:0;
    }
    .rh-hero__chips{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        margin-top:18px;
    }
    .rh-chip{
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

    .rh-grid{display:grid;gap:16px;}
    .rh-grid--kpi{
        grid-template-columns:repeat(4,minmax(0,1fr));
        margin-bottom:20px;
    }
    .rh-grid--2{
        grid-template-columns:1.25fr .95fr;
        margin-bottom:20px;
    }
    .rh-grid--modules{
        grid-template-columns:repeat(3,minmax(0,1fr));
        margin-bottom:20px;
    }
    .rh-card{
        background:var(--rh-card);
        border:1px solid var(--rh-line);
        border-radius:24px;
        box-shadow:0 10px 24px rgba(15,23,42,.04);
    }
    .rh-card__body{padding:20px;}
    .rh-card__head{
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:14px;
        margin-bottom:14px;
    }
    .rh-card__title{
        margin:0;
        color:var(--rh-text);
        font-size:20px;
        line-height:1.1;
        font-weight:900;
        letter-spacing:-.03em;
    }
    .rh-card__sub{
        color:var(--rh-muted);
        font-size:14px;
        line-height:1.55;
        margin-top:6px;
    }

    .rh-kpi{
        height:100%;
        background:#fff;
        border:1px solid var(--rh-line);
        border-radius:22px;
        padding:18px;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .rh-kpi__label{font-size:13px;font-weight:800;color:var(--rh-muted);margin-bottom:8px;}
    .rh-kpi__value{font-size:34px;line-height:1;font-weight:900;color:var(--rh-text);margin-bottom:8px;}
    .rh-kpi__hint{font-size:12px;color:var(--rh-muted);}

    .rh-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }
    .rh-action{
        display:flex;
        align-items:flex-start;
        gap:12px;
        text-decoration:none;
        color:inherit;
        border:1px solid var(--rh-line);
        background:#fff;
        border-radius:18px;
        padding:16px;
        transition:.18s ease;
    }
    .rh-action:hover{
        transform:translateY(-1px);
        border-color:#cbd5e1;
        box-shadow:0 10px 18px rgba(15,23,42,.06);
        text-decoration:none;
        color:inherit;
    }
    .rh-action__icon{
        width:44px;height:44px;border-radius:14px;
        display:flex;align-items:center;justify-content:center;
        background:var(--rh-soft-2);color:var(--rh-primary);
        flex:0 0 44px;
    }
    .rh-action__title{font-size:15px;font-weight:900;color:var(--rh-text);margin-bottom:4px;}
    .rh-action__text{font-size:13px;line-height:1.55;color:var(--rh-muted);}

    .rh-module{
        height:100%;
        border:1px solid var(--rh-line);
        border-radius:22px;
        padding:18px;
        background:#fff;
        box-shadow:0 8px 20px rgba(15,23,42,.04);
    }
    .rh-module__icon{
        width:52px;height:52px;border-radius:16px;
        display:flex;align-items:center;justify-content:center;
        background:var(--rh-soft);
        color:var(--rh-primary);
        margin-bottom:14px;
    }
    .rh-module__title{
        font-size:18px;
        font-weight:900;
        color:var(--rh-text);
        line-height:1.15;
        margin-bottom:8px;
    }
    .rh-module__desc{
        font-size:14px;
        line-height:1.6;
        color:var(--rh-muted);
        min-height:88px;
        margin-bottom:14px;
    }
    .rh-tags{display:flex;flex-wrap:wrap;gap:8px;}
    .rh-tag{
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

    .rh-roadmap{
        display:grid;
        gap:12px;
    }
    .rh-step{
        display:flex;
        gap:12px;
        align-items:flex-start;
        padding:14px;
        border:1px solid var(--rh-line);
        border-radius:18px;
        background:#fff;
    }
    .rh-step__num{
        width:34px;height:34px;border-radius:999px;
        display:flex;align-items:center;justify-content:center;
        background:var(--rh-primary);color:#fff;font-weight:900;flex:0 0 34px;
    }
    .rh-step__title{font-size:15px;font-weight:900;color:var(--rh-text);margin-bottom:3px;}
    .rh-step__text{font-size:13px;line-height:1.55;color:var(--rh-muted);}

    .rh-ai-grid{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:16px;
    }
    .rh-ai-card{
        border:1px solid var(--rh-line);
        border-radius:22px;
        padding:18px;
        background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);
    }
    .rh-ai-card__badge{
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
    .rh-ai-card__title{
        font-size:17px;
        font-weight:900;
        color:var(--rh-text);
        line-height:1.2;
        margin-bottom:8px;
    }
    .rh-ai-card__text{
        font-size:14px;
        line-height:1.6;
        color:var(--rh-muted);
    }

    .rh-note{
        margin-top:16px;
        padding:14px 16px;
        border-radius:18px;
        border:1px dashed #cbd5e1;
        background:#f8fafc;
        color:var(--rh-muted);
        font-size:13px;
        line-height:1.6;
    }

    @media (max-width: 1199.98px){
        .rh-grid--kpi{grid-template-columns:repeat(2,minmax(0,1fr));}
        .rh-grid--modules{grid-template-columns:repeat(2,minmax(0,1fr));}
        .rh-ai-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    }
    @media (max-width: 991.98px){
        .rh-grid--2{grid-template-columns:1fr;}
    }
    @media (max-width: 767.98px){
        .rh-shell{padding:18px 0 24px;}
        .rh-hero{padding:22px;}
        .rh-grid--kpi,
        .rh-grid--modules,
        .rh-ai-grid,
        .rh-actions{grid-template-columns:1fr;}
        .rh-module__desc{min-height:auto;}
    }
</style>

<div class="container-fluid rh-shell">
    <section class="rh-hero">
        <div class="rh-hero__eyebrow">
            <span class="rh-hero__eyebrow-dot"></span>
            <span>RH + NÓMINA + CFDI NÓMINA + IA</span>
        </div>

        <h1>Recursos Humanos</h1>

        <p>
            Administra empleados, movimientos, incidencias, nómina ordinaria y extraordinaria,
            finiquitos, liquidaciones y CFDI de nómina desde un solo módulo. Aquí vivirá también
            la operación de timbrado, cancelación y consumo de hits para nómina, con herramientas
            asistidas por IA para detección de anomalías, simulación y control operativo.
        </p>

        <div class="rh-hero__chips">
            <span class="rh-chip">Empleados</span>
            <span class="rh-chip">Altas / bajas / cambios</span>
            <span class="rh-chip">Nómina ordinaria y extraordinaria</span>
            <span class="rh-chip">CFDI de nómina dentro de RH</span>
            <span class="rh-chip">Finiquitos y liquidaciones</span>
            <span class="rh-chip">IA operativa</span>
        </div>
    </section>

    <div class="rh-grid rh-grid--kpi">
        @foreach($kpis as $kpi)
            <div class="rh-kpi">
                <div class="rh-kpi__label">{{ $kpi['label'] }}</div>
                <div class="rh-kpi__value">{{ $kpi['value'] }}</div>
                <div class="rh-kpi__hint">{{ $kpi['hint'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="rh-grid rh-grid--2">
        <div class="rh-card">
            <div class="rh-card__body">
                <div class="rh-card__head">
                    <div>
                        <h2 class="rh-card__title">Centro de operación RH</h2>
                        <div class="rh-card__sub">
                            Primera versión completa y simple para dejar el módulo preparado.
                            Después iremos activando cálculos, timbrado, impuestos, pagos y automatizaciones.
                        </div>
                    </div>
                </div>

                <div class="rh-actions">
                    <a href="{{ $rtRh }}" class="rh-action">
                        <div class="rh-action__icon"><span class="material-symbols-outlined">badge</span></div>
                        <div>
                            <div class="rh-action__title">Expedientes de empleados</div>
                            <div class="rh-action__text">Altas, bajas, reingresos, documentos, bancos, salarios y estatus laboral.</div>
                        </div>
                    </a>

                    <a href="{{ $rtRh }}" class="rh-action">
                        <div class="rh-action__icon"><span class="material-symbols-outlined">payments</span></div>
                        <div>
                            <div class="rh-action__title">Nómina y CFDI nómina</div>
                            <div class="rh-action__text">Pre-nómina, cálculo, timbrado, cancelación, PDF/XML y consumo de hits.</div>
                        </div>
                    </a>

                    <a href="{{ $rtTimbres }}" class="rh-action">
                        <div class="rh-action__icon"><span class="material-symbols-outlined">local_activity</span></div>
                        <div>
                            <div class="rh-action__title">Timbres / Hits</div>
                            <div class="rh-action__text">Compra, saldo, consumo y configuración de timbrado mientras la API queda automatizada.</div>
                        </div>
                    </a>

                    <a href="{{ $rtReportes }}" class="rh-action">
                        <div class="rh-action__icon"><span class="material-symbols-outlined">monitoring</span></div>
                        <div>
                            <div class="rh-action__title">Reportes RH</div>
                            <div class="rh-action__text">Indicadores de empleados, incidencias, nómina, timbrado y consumo operativo.</div>
                        </div>
                    </a>
                </div>

                <div class="rh-note">
                    <strong>Regla del producto:</strong> el CFDI de nómina no vive como módulo aparte.
                    Vive dentro de <strong>Recursos Humanos</strong>, y cada timbrado o cancelación consumirá
                    <strong>1 hit/timbre</strong> del cliente.
                </div>
            </div>
        </div>

        <div class="rh-card">
            <div class="rh-card__body">
                <div class="rh-card__head">
                    <div>
                        <h2 class="rh-card__title">Ruta de implementación</h2>
                        <div class="rh-card__sub">
                            Para no romper nada, el módulo se deja completo en estructura y luego se activa por capas.
                        </div>
                    </div>
                </div>

                <div class="rh-roadmap">
                    <div class="rh-step">
                        <div class="rh-step__num">1</div>
                        <div>
                            <div class="rh-step__title">Base RH</div>
                            <div class="rh-step__text">Dashboard, empleados, incidencias, movimientos y vistas operativas web + móvil.</div>
                        </div>
                    </div>

                    <div class="rh-step">
                        <div class="rh-step__num">2</div>
                        <div>
                            <div class="rh-step__title">Nómina</div>
                            <div class="rh-step__text">Nóminas ordinarias y extraordinarias, pre-nómina, cálculo, autorización y cierre.</div>
                        </div>
                    </div>

                    <div class="rh-step">
                        <div class="rh-step__num">3</div>
                        <div>
                            <div class="rh-step__title">CFDI Nómina</div>
                            <div class="rh-step__text">Timbrado, cancelación, PDF/XML, reenvíos e historial SAT dentro del módulo RH.</div>
                        </div>
                    </div>

                    <div class="rh-step">
                        <div class="rh-step__num">4</div>
                        <div>
                            <div class="rh-step__title">Obligaciones e IA</div>
                            <div class="rh-step__text">Impuestos, simulación, auditoría operativa, incidencias inteligentes y alertas.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="rh-grid rh-grid--modules">
        @foreach($sections as $section)
            <div class="rh-module">
                <div class="rh-module__icon">
                    <span class="material-symbols-outlined">{{ $section['icon'] }}</span>
                </div>

                <div class="rh-module__title">{{ $section['title'] }}</div>

                <div class="rh-module__desc">
                    {{ $section['desc'] }}
                </div>

                <div class="rh-tags">
                    @foreach($section['items'] as $item)
                        <span class="rh-tag">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="rh-card mb-4">
        <div class="rh-card__body">
            <div class="rh-card__head">
                <div>
                    <h2 class="rh-card__title">IA aplicada al módulo RH</h2>
                    <div class="rh-card__sub">
                        El enfoque no será solo “chat”. La IA debe ser operativa, auditable y útil para vender el módulo desde el día uno.
                    </div>
                </div>
            </div>

            <div class="rh-ai-grid">
                @foreach($aiCards as $card)
                    <div class="rh-ai-card">
                        <div class="rh-ai-card__badge">IA Pactopia360</div>
                        <div class="rh-ai-card__title">{{ $card['title'] }}</div>
                        <div class="rh-ai-card__text">{{ $card['desc'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="rh-note">
                <strong>Idea comercial fuerte:</strong> incluir simulaciones, escenarios, advertencias y propuestas operativas antes de timbrar o cerrar nómina.
                Eso hace que RH no sea solo “captura”, sino una herramienta inteligente que ayude a decidir.
            </div>
        </div>
    </div>

    <div class="rh-card">
        <div class="rh-card__body">
            <div class="rh-card__head">
                <div>
                    <h2 class="rh-card__title">Conexión con otros módulos</h2>
                    <div class="rh-card__sub">
                        RH se integrará con facturación, timbres/hits, reportes y operación general del ecosistema.
                    </div>
                </div>
            </div>

            <div class="rh-actions">
                <a href="{{ $rtTimbres }}" class="rh-action">
                    <div class="rh-action__icon"><span class="material-symbols-outlined">local_activity</span></div>
                    <div>
                        <div class="rh-action__title">Ir a Timbres / Hits</div>
                        <div class="rh-action__text">Compra, saldo, dashboard de consumo y futura integración con Facturotopia.</div>
                    </div>
                </a>

                <a href="{{ $rtFact }}" class="rh-action">
                    <div class="rh-action__icon"><span class="material-symbols-outlined">receipt_long</span></div>
                    <div>
                        <div class="rh-action__title">Ir a Facturación</div>
                        <div class="rh-action__text">Separado de RH para CFDI comerciales, pero compartiendo bolsa de hits/timbres.</div>
                    </div>
                </a>

                <a href="{{ $rtVentas }}" class="rh-action">
                    <div class="rh-action__icon"><span class="material-symbols-outlined">point_of_sale</span></div>
                    <div>
                        <div class="rh-action__title">Ir a Ventas</div>
                        <div class="rh-action__text">Ventas y tickets alimentarán el ecosistema comercial mientras RH cubre la parte de personal y nómina.</div>
                    </div>
                </a>

                <a href="{{ $rtSat }}" class="rh-action">
                    <div class="rh-action__icon"><span class="material-symbols-outlined">cloud_download</span></div>
                    <div>
                        <div class="rh-action__title">Ir a SAT Descargas</div>
                        <div class="rh-action__text">SAT queda como módulo aparte con RFC, cotizaciones y bóveda, separado del mundo RH.</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection