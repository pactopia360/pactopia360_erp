@extends('layouts.cliente')

@section('title', 'Timbres / Hits')

@section('content')
@php
    $data = $timbresData ?? [];

    $saldo = $data['saldo'] ?? [];
    $facturotopia = $data['facturotopia'] ?? [];
    $periodo = $data['periodo'] ?? [];
    $kpis = $data['kpis'] ?? [];
    $series = $data['series'] ?? ['labels' => [], 'consumo' => [], 'monto' => []];
    $consumoPorRfc = collect($data['consumoPorRfc'] ?? []);
    $ultimosCfdi = collect($data['ultimosCfdi'] ?? []);
    $alertasIa = collect($data['alertasIa'] ?? []);

    $rtFact = Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#';
    $rtExportCfdi = Route::has('cliente.facturacion.export') ? route('cliente.facturacion.export') : '#';

    $env = $facturotopia['env'] ?? 'sandbox';

    $timbresAsignados = (int) ($saldo['timbres_asignados'] ?? 0);
    $timbresConsumidos = (int) ($saldo['timbres_consumidos'] ?? 0);
    $timbresDisponibles = (int) ($saldo['timbres_disponibles'] ?? 0);
    $usoPct = (float) ($saldo['uso_pct'] ?? 0);

    $hitsAsignados = (int) ($saldo['hits_asignados'] ?? 0);
    $hitsConsumidos = (int) ($saldo['hits_consumidos'] ?? 0);
    $hitsDisponibles = (int) ($saldo['hits_disponibles'] ?? 0);

    $sandboxKey = (string) ($facturotopia['sandbox_api_key'] ?? '');
    $productionKey = (string) ($facturotopia['production_api_key'] ?? '');
    $pacPassword = (string) ($facturotopia['password'] ?? '');

    $mask = function (?string $value) {
        $value = trim((string) $value);

        if ($value === '') {
            return 'No configurada';
        }

        if (mb_strlen($value) <= 12) {
            return str_repeat('•', max(6, mb_strlen($value)));
        }

        return mb_substr($value, 0, 6) . str_repeat('•', 18) . mb_substr($value, -6);
    };

    $money = fn ($amount) => '$' . number_format((float) $amount, 2, '.', ',');

    $envLabel = $env === 'production' ? 'Producción' : 'Pruebas';
    $envClass = $env === 'production' ? 'production' : 'sandbox';

    $usageStatus = $timbresDisponibles <= 0 ? 'critical' : ($usoPct >= 80 ? 'warning' : 'ok');

    $sections = [
        [
            'id' => 'overview',
            'title' => 'Resumen operativo',
            'icon' => 'dashboard',
            'open' => true,
        ],
        [
            'id' => 'reports',
            'title' => 'Reportes y filtros',
            'icon' => 'filter_alt',
            'open' => true,
        ],
        [
            'id' => 'pac',
            'title' => 'PAC / Facturotopia',
            'icon' => 'hub',
            'open' => false,
        ],
        [
            'id' => 'ia',
            'title' => 'IA fiscal',
            'icon' => 'psychology',
            'open' => false,
        ],
    ];
@endphp

<div
    class="tim-shell"
    data-tim-series='@json($series)'
    data-tim-env="{{ $env }}"
>
    <section class="tim-topbar">
        <div>
            <div class="tim-eyebrow">
                <span class="tim-dot tim-dot--{{ $envClass }}"></span>
                TIMBRES / HITS
            </div>

            <h1>Centro de control de timbrado</h1>

            <p>
                Saldo, consumo, cortes, UUID, errores PAC, ambiente y reportes.
            </p>
        </div>

        <div class="tim-toolbar">
            <button type="button" class="tim-icon-btn" id="timCompactToggle" title="Vista compacta">
                <span class="material-symbols-outlined">view_compact</span>
                <span>Compacto</span>
            </button>

            <button type="button" class="tim-icon-btn" data-tim-export="table" title="Exportar tabla CSV">
                <span class="material-symbols-outlined">download</span>
                <span>CSV</span>
            </button>

            <a href="{{ $rtExportCfdi }}" class="tim-icon-btn" title="Exportar desde facturación">
                <span class="material-symbols-outlined">table_view</span>
                <span>Excel</span>
            </a>
        </div>
    </section>

    <section class="tim-summary-strip">
        <article class="tim-metric tim-metric--{{ $usageStatus }}">
            <span class="material-symbols-outlined">confirmation_number</span>
            <div>
                <small>Disponibles</small>
                <strong>{{ number_format($timbresDisponibles) }}</strong>
                <em>{{ number_format($timbresConsumidos) }} consumidos</em>
            </div>
        </article>

        <article class="tim-metric">
            <span class="material-symbols-outlined">speed</span>
            <div>
                <small>Uso bolsa</small>
                <strong>{{ number_format($usoPct, 2) }}%</strong>
                <em>{{ number_format($timbresAsignados) }} asignados</em>
            </div>
        </article>

        <article class="tim-metric">
            <span class="material-symbols-outlined">receipt_long</span>
            <div>
                <small>Timbrados mes</small>
                <strong>{{ number_format((int) ($kpis['emitidos_count'] ?? 0)) }}</strong>
                <em>{{ $periodo['label'] ?? now()->format('m/Y') }}</em>
            </div>
        </article>

        <article class="tim-metric">
            <span class="material-symbols-outlined">error</span>
            <div>
                <small>Errores PAC</small>
                <strong>{{ number_format((int) ($kpis['errores_count'] ?? 0)) }}</strong>
                <em>rechazos / fallos</em>
            </div>
        </article>

        <article class="tim-metric tim-metric--env">
            <span class="material-symbols-outlined">toggle_on</span>
            <div>
                <small>Ambiente</small>
                <strong>{{ $envLabel }}</strong>
                <em>{{ $env === 'production' ? 'Consume saldo real' : 'Sin consumo real' }}</em>
            </div>
        </article>
    </section>

    <section class="tim-env-panel">
        <div class="tim-env-switch" role="group" aria-label="Ambiente de timbrado">
            <button type="button" class="tim-env-btn {{ $env === 'sandbox' ? 'is-active' : '' }}" data-env-target="sandbox">
                <span class="material-symbols-outlined">science</span>
                Pruebas
            </button>
            <button type="button" class="tim-env-btn {{ $env === 'production' ? 'is-active' : '' }}" data-env-target="production">
                <span class="material-symbols-outlined">verified</span>
                Producción
            </button>
        </div>

        <div class="tim-env-note">
            <span class="material-symbols-outlined">info</span>
            <strong id="timEnvLabel">{{ $envLabel }}</strong>
            <small id="timEnvText">{{ $env === 'production' ? 'Los CFDI timbrados descuentan timbres reales.' : 'Modo seguro para pruebas, validación y configuración PAC.' }}</small>
        </div>
    </section>

    <section class="tim-section {{ $sections[0]['open'] ? 'is-open' : '' }}" data-tim-section="overview">
        <button type="button" class="tim-section__header">
            <span class="material-symbols-outlined">{{ $sections[0]['icon'] }}</span>
            <strong>{{ $sections[0]['title'] }}</strong>
            <em>saldo, monto y comportamiento</em>
            <i class="material-symbols-outlined">expand_more</i>
        </button>

        <div class="tim-section__body">
            <div class="tim-grid tim-grid--main">
                <article class="tim-card">
                    <div class="tim-card__head">
                        <div>
                            <h2>Consumo mensual</h2>
                            <p>{{ $periodo['label'] ?? 'Periodo actual' }}</p>
                        </div>
                        <span class="tim-pill">{{ $money($kpis['total_periodo'] ?? 0) }}</span>
                    </div>

                    <div class="tim-chart-card">
                        <canvas id="timConsumoChart" height="120"></canvas>
                    </div>

                    <div class="tim-mini-grid">
                        <div>
                            <span>Total facturado</span>
                            <strong>{{ $money($kpis['total_periodo'] ?? 0) }}</strong>
                        </div>
                        <div>
                            <span>Cancelados</span>
                            <strong>{{ number_format((int) ($kpis['cancelados_count'] ?? 0)) }}</strong>
                        </div>
                        <div>
                            <span>Tiempo PAC</span>
                            <strong>{{ number_format((int) ($kpis['promedio_pac_ms'] ?? 0)) }} ms</strong>
                        </div>
                    </div>
                </article>

                <article class="tim-card">
                    <div class="tim-card__head">
                        <div>
                            <h2>Corte rápido</h2>
                            <p>Lectura de bolsa y consumo operativo.</p>
                        </div>
                    </div>

                    <div class="tim-progress-box">
                        <div class="tim-progress-head">
                            <span>Bolsa usada</span>
                            <strong>{{ number_format($usoPct, 2) }}%</strong>
                        </div>
                        <div class="tim-progress">
                            <div style="width: {{ min(100, max(0, $usoPct)) }}%"></div>
                        </div>
                        <div class="tim-progress-foot">
                            <span>{{ number_format($timbresConsumidos) }} usados</span>
                            <span>{{ number_format($timbresDisponibles) }} disponibles</span>
                        </div>
                    </div>

                    <div class="tim-quick-list">
                        <div>
                            <span class="material-symbols-outlined">bolt</span>
                            <strong>{{ number_format($hitsDisponibles) }}</strong>
                            <small>hits disponibles</small>
                        </div>
                        <div>
                            <span class="material-symbols-outlined">history</span>
                            <strong>{{ $saldo['ultimo_consumo_at'] ?: '—' }}</strong>
                            <small>último consumo</small>
                        </div>
                        <div>
                            <span class="material-symbols-outlined">tag</span>
                            <strong>{{ $saldo['ultimo_uuid'] ?: '—' }}</strong>
                            <small>último UUID</small>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="tim-section {{ $sections[1]['open'] ? 'is-open' : '' }}" data-tim-section="reports">
        <button type="button" class="tim-section__header">
            <span class="material-symbols-outlined">{{ $sections[1]['icon'] }}</span>
            <strong>{{ $sections[1]['title'] }}</strong>
            <em>UUID, RFC, estatus, ambiente y exportables</em>
            <i class="material-symbols-outlined">expand_more</i>
        </button>

        <div class="tim-section__body">
            <div class="tim-filters">
                <label>
                    <span>Buscar</span>
                    <input type="search" id="timTableSearch" placeholder="UUID, RFC, receptor, estatus...">
                </label>

                <label>
                    <span>Estatus</span>
                    <select id="timStatusFilter">
                        <option value="">Todos</option>
                        <option value="timbrado">Timbrado</option>
                        <option value="emitido">Emitido</option>
                        <option value="cancelado">Cancelado</option>
                        <option value="borrador">Borrador</option>
                        <option value="error">Error</option>
                    </select>
                </label>

                <label>
                    <span>Tipo</span>
                    <select id="timTipoFilter">
                        <option value="">Todos</option>
                        <option value="I">Ingreso</option>
                        <option value="E">Egreso</option>
                        <option value="P">Pago / REP</option>
                        <option value="N">Nómina</option>
                        <option value="T">Traslado</option>
                    </select>
                </label>

                <label>
                    <span>Ambiente</span>
                    <select id="timEnvFilter">
                        <option value="">Todos</option>
                        <option value="sandbox">Pruebas</option>
                        <option value="production">Producción</option>
                    </select>
                </label>

                <div class="tim-filter-actions">
                    <button type="button" class="tim-btn tim-btn--light" id="timClearFilters">
                        <span class="material-symbols-outlined">restart_alt</span>
                        Limpiar
                    </button>

                    <button type="button" class="tim-btn tim-btn--primary" data-tim-export="table">
                        <span class="material-symbols-outlined">download</span>
                        Exportar CSV
                    </button>
                </div>
            </div>

            <div class="tim-grid tim-grid--two">
                <article class="tim-card">
                    <div class="tim-card__head">
                        <div>
                            <h2>Consumo por RFC</h2>
                            <p>Top receptores por cantidad y monto.</p>
                        </div>
                    </div>

                    <div class="tim-rfc-list">
                        @forelse($consumoPorRfc as $row)
                            <div class="tim-rfc-row">
                                <div>
                                    <strong>{{ $row->rfc ?? 'SIN RFC' }}</strong>
                                    <span>{{ $row->receptor ?? 'Receptor' }}</span>
                                </div>
                                <div>
                                    <strong>{{ number_format((int) ($row->cantidad ?? 0)) }}</strong>
                                    <span>{{ $money($row->monto ?? 0) }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="tim-empty">Sin consumo por RFC en este periodo.</div>
                        @endforelse
                    </div>
                </article>

                <article class="tim-card">
                    <div class="tim-card__head">
                        <div>
                            <h2>Corte mensual</h2>
                            <p>Resumen para soporte, administración y conciliación.</p>
                        </div>
                    </div>

                    <div class="tim-cut-grid">
                        <div>
                            <span>Inicial</span>
                            <strong>{{ number_format($timbresAsignados) }}</strong>
                        </div>
                        <div>
                            <span>Consumidos</span>
                            <strong>{{ number_format($timbresConsumidos) }}</strong>
                        </div>
                        <div>
                            <span>Final</span>
                            <strong>{{ number_format($timbresDisponibles) }}</strong>
                        </div>
                        <div>
                            <span>Errores</span>
                            <strong>{{ number_format((int) ($kpis['errores_count'] ?? 0)) }}</strong>
                        </div>
                    </div>

                    <div class="tim-export-row">
                        <button type="button" class="tim-btn tim-btn--light" data-tim-export="table">CSV consumo</button>
                        <button type="button" class="tim-btn tim-btn--light" data-tim-export="rfc">CSV RFC</button>
                        <a class="tim-btn tim-btn--light" href="{{ $rtFact }}">Ir a Facturación</a>
                    </div>
                </article>
            </div>

            <article class="tim-card">
                <div class="tim-card__head">
                    <div>
                        <h2>Historial de CFDI procesados</h2>
                        <p>UUID clicable para abrir el comprobante en Facturación 360.</p>
                    </div>
                </div>

                <div class="tim-table-wrap">
                    <table class="tim-table" id="timCfdiTable">
                        <thead>
                            <tr>
                                <th>UUID</th>
                                <th>Fecha</th>
                                <th>RFC</th>
                                <th>Receptor</th>
                                <th>Tipo</th>
                                <th>Total</th>
                                <th>Estatus</th>
                                <th>Ambiente</th>
                                <th>PAC</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ultimosCfdi as $cfdi)
                                @php
                                    $fecha = $cfdi->fecha_timbrado ?? $cfdi->fecha ?? null;
                                    $uuid = (string) ($cfdi->uuid ?? 'SIN UUID');
                                    $estatus = strtolower((string) ($cfdi->estatus ?? ''));
                                    $tipo = $cfdi->tipo_documento ?? $cfdi->tipo_comprobante ?? 'CFDI';
                                    $rowEnv = $cfdi->ambiente ?? $env;
                                    $showUrl = Route::has('cliente.facturacion.show')
                                        ? route('cliente.facturacion.show', $cfdi->id)
                                        : $rtFact;
                                @endphp

                                <tr
                                    data-status="{{ $estatus }}"
                                    data-tipo="{{ $tipo }}"
                                    data-env="{{ $rowEnv }}"
                                >
                                    <td>
                                        <a href="{{ $showUrl }}" class="tim-uuid">
                                            {{ $uuid }}
                                        </a>
                                    </td>
                                    <td>{{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y H:i') : '—' }}</td>
                                    <td>{{ $cfdi->receptor_rfc_resuelto ?? $cfdi->receptor_rfc ?? '—' }}</td>
                                    <td>{{ $cfdi->receptor_nombre_resuelto ?? 'Receptor' }}</td>
                                    <td>{{ $tipo }}</td>
                                    <td>{{ $money($cfdi->total ?? 0) }}</td>
                                    <td><span class="tim-status tim-status--{{ $estatus ?: 'default' }}">{{ strtoupper($estatus ?: 'N/D') }}</span></td>
                                    <td>{{ $rowEnv === 'production' ? 'Producción' : 'Pruebas' }}</td>
                                    <td>{{ isset($cfdi->pac_response_ms) ? number_format((int) $cfdi->pac_response_ms) . ' ms' : '—' }}</td>
                                    <td><a href="{{ $showUrl }}" class="tim-row-link">Abrir</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <div class="tim-empty">Aún no hay CFDI procesados para mostrar.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
    </section>

    <section class="tim-section {{ $sections[2]['open'] ? 'is-open' : '' }}" data-tim-section="pac">
        <button type="button" class="tim-section__header">
            <span class="material-symbols-outlined">{{ $sections[2]['icon'] }}</span>
            <strong>{{ $sections[2]['title'] }}</strong>
            <em>credenciales, API keys, conexión y diagnóstico</em>
            <i class="material-symbols-outlined">expand_more</i>
        </button>

        <div class="tim-section__body">
            <article class="tim-card">
                <div class="tim-cred-grid tim-cred-grid--wide">
                    <div>
                        <span>Estatus</span>
                        <strong>{{ strtoupper((string) ($facturotopia['status'] ?? 'pendiente')) }}</strong>
                    </div>
                    <div>
                        <span>ID cliente</span>
                        <strong>{{ $facturotopia['customer_id'] ?: 'No configurado' }}</strong>
                    </div>
                    <div>
                        <span>Usuario PAC</span>
                        <strong>{{ $facturotopia['user'] ?: 'No configurado' }}</strong>
                    </div>
                    <div>
                        <span>Contraseña PAC</span>
                        <strong>{{ $pacPassword !== '' ? 'Configurada' : 'No configurada' }}</strong>
                    </div>
                    <div>
                        <span>Actualización</span>
                        <strong>{{ $facturotopia['updated_at'] ?: 'Sin fecha' }}</strong>
                    </div>
                </div>

                <div class="tim-key-grid">
                    <div class="tim-key-box">
                        <label>API key pruebas</label>
                        <div>
                            <code data-secret="{{ $sandboxKey }}">{{ $mask($sandboxKey) }}</code>
                            <button type="button" class="tim-secret-toggle">Ver</button>
                        </div>
                    </div>

                    <div class="tim-key-box">
                        <label>API key producción</label>
                        <div>
                            <code data-secret="{{ $productionKey }}">{{ $mask($productionKey) }}</code>
                            <button type="button" class="tim-secret-toggle">Ver</button>
                        </div>
                    </div>

                    <div class="tim-key-box">
                        <label>Contraseña PAC</label>
                        <div>
                            <code data-secret="{{ $pacPassword }}">{{ $mask($pacPassword) }}</code>
                            <button type="button" class="tim-secret-toggle">Ver</button>
                        </div>
                    </div>
                </div>

                <div class="tim-pac-actions">
                    <button
                        type="button"
                        class="tim-btn tim-btn--primary"
                        id="timFacturotopiaTest"
                        data-url="{{ route('cliente.modulos.timbres.facturotopia.test') }}"
                        data-csrf="{{ csrf_token() }}"
                    >
                        <span class="material-symbols-outlined">sync</span>
                        Probar conexión
                    </button>
                </div>

                <div class="tim-connection-result" id="timFacturotopiaResult" hidden></div>
                <div class="tim-note">
                    Este bloque valida credenciales, ambiente, API key, usuario PAC y respuesta de conexión Facturotopia.
                </div>
            </article>
        </div>
    </section>

    <section class="tim-section {{ $sections[3]['open'] ? 'is-open' : '' }}" data-tim-section="ia">
        <button type="button" class="tim-section__header">
            <span class="material-symbols-outlined">{{ $sections[3]['icon'] }}</span>
            <strong>{{ $sections[3]['title'] }}</strong>
            <em>alertas, anomalías, recomendación de compra</em>
            <i class="material-symbols-outlined">expand_more</i>
        </button>

        <div class="tim-section__body">
            <article class="tim-card">
                <div class="tim-alert-list">
                    @forelse($alertasIa as $alerta)
                        <div class="tim-alert tim-alert--{{ $alerta['tipo'] ?? 'info' }}">
                            <span class="material-symbols-outlined">
                                {{ ($alerta['tipo'] ?? '') === 'danger' ? 'warning' : (($alerta['tipo'] ?? '') === 'warning' ? 'notification_important' : 'check_circle') }}
                            </span>
                            <div>
                                <strong>{{ $alerta['titulo'] ?? 'Alerta' }}</strong>
                                <small>{{ $alerta['texto'] ?? '' }}</small>
                            </div>
                        </div>
                    @empty
                        <div class="tim-alert tim-alert--success">
                            <span class="material-symbols-outlined">check_circle</span>
                            <div>
                                <strong>Operación estable</strong>
                                <small>No hay alertas críticas de timbrado en este periodo.</small>
                            </div>
                        </div>
                    @endforelse
                </div>

                <div class="tim-ai-grid">
                    <div>
                        <span class="material-symbols-outlined">trending_up</span>
                        <strong>Proyección de compra</strong>
                        <small>Calcula agotamiento según consumo real.</small>
                    </div>
                    <div>
                        <span class="material-symbols-outlined">rule_settings</span>
                        <strong>Mejora fiscal</strong>
                        <small>Detecta errores por RFC, CP, régimen o uso CFDI.</small>
                    </div>
                    <div>
                        <span class="material-symbols-outlined">monitoring</span>
                        <strong>Anomalías PAC</strong>
                        <small>Identifica intermitencia y tiempos fuera de rango.</small>
                    </div>
                </div>
            </article>
        </div>
    </section>
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400..700,0..1,0">
    <link rel="stylesheet" href="{{ asset('assets/client/css/pages/timbres-hits.css') }}?v={{ file_exists(public_path('assets/client/css/pages/timbres-hits.css')) ? filemtime(public_path('assets/client/css/pages/timbres-hits.css')) : time() }}">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('assets/client/js/pages/timbres-hits.js') }}?v={{ file_exists(public_path('assets/client/js/pages/timbres-hits.js')) ? filemtime(public_path('assets/client/js/pages/timbres-hits.js')) : time() }}"></script>
@endpush