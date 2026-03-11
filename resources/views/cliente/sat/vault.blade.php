{{-- resources/views/cliente/sat/vault.blade.php --}}
@extends('layouts.cliente')
@section('title','Bóveda Fiscal · SAT')
@section('pageClass','page-sat page-sat-vault')

@php
    use App\Models\Cliente\SatDownload;
    use App\Models\Cliente\SatCredential;
    use Illuminate\Support\Str;

    $user   = $user   ?? auth('web')->user();
    $cuenta = $cuenta ?? ($user?->cuenta ?? null);

    $summary = $summary ?? app(\App\Http\Controllers\Cliente\HomeController::class)->buildAccountSummary();

    $planFromSummary = strtoupper((string)($summary['plan'] ?? 'FREE'));
    $isProSummary    = (bool)($summary['is_pro'] ?? in_array(
        strtolower($planFromSummary),
        ['pro', 'premium', 'empresa', 'business'],
        true
    ));

    $plan      = $planFromSummary;
    $isProPlan = $isProSummary;
    $isPro     = $isProPlan;

    $credCollection = collect($credList ?? []);

    if ($credCollection->isEmpty() && $cuenta) {
        try {
            $cuentaIdForCred = $cuenta->id ?? $cuenta->cuenta_id ?? null;

            if ($cuentaIdForCred) {
                $credCollection = SatCredential::query()
                    ->where('cuenta_id', $cuentaIdForCred)
                    ->orderBy('rfc')
                    ->get();
            }
        } catch (\Throwable $e) {
            \Log::warning('[SAT:vault] No se pudieron cargar credenciales para RFC', [
                'cuenta_id' => $cuenta->id ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    $rfcOptionsForVault = [];

    foreach ($credCollection as $c) {
        $rfc = strtoupper((string) data_get($c, 'rfc', ''));
        if (!$rfc) {
            continue;
        }

        $nombre = trim((string) (
            data_get($c, 'razon_social')
            ?: data_get($c, 'denominacion')
            ?: data_get($c, 'nombre')
            ?: data_get($c, 'alias')
            ?: ''
        ));

        $estatusRaw = strtolower((string) data_get($c, 'estatus', ''));

        $okFlag = false;

        if (
            data_get($c, 'validado')
            || data_get($c, 'validated_at')
            || data_get($c, 'has_files')
            || data_get($c, 'has_csd')
            || data_get($c, 'cer_path')
            || data_get($c, 'key_path')
        ) {
            $okFlag = true;
        }

        if (in_array($estatusRaw, ['ok', 'valido', 'válido', 'validado', 'activo', 'active'], true)) {
            $okFlag = true;
        }

        if (!$okFlag) {
            continue;
        }

        $rfcOptionsForVault[] = [
            'rfc'    => $rfc,
            'nombre' => $nombre,
        ];
    }

    $rfcs = collect($rfcOptionsForVault)
        ->unique('rfc')
        ->sortBy('rfc')
        ->values()
        ->all();

    $vaultFromCtrl = $vault ?? [];

    $vaultBaseGb = 0.0;
    if ($isProPlan) {
        $vaultBaseGb = (float) config('services.sat.vault.base_gb_pro', 0.0);
    }

    $vaultQuotaFromAccount = (float) ($cuenta->vault_quota_gb ?? 0.0);
    $vaultQuotaFromStorage = (float) ($storage['quota_gb'] ?? (float) ($vaultFromCtrl['quota_gb'] ?? 0.0));

    $vaultUsedGb = (float) ($storage['used_gb'] ?? ($summary['vault_used_gb'] ?? ($vaultFromCtrl['used_gb'] ?? 0.0)));
    if ($vaultUsedGb < 0) {
        $vaultUsedGb = 0.0;
    }

    $vaultQuotaFromVaultRows = 0.0;

    try {
        $cuentaIdForVault = $cuenta->id ?? $cuenta->cuenta_id ?? null;

        if ($cuentaIdForVault) {
            $vaultPricing = [
                5    => 249.0,
                10   => 449.0,
                20   => 799.0,
                50   => 1499.0,
                100  => 2499.0,
                500  => 7999.0,
                1024 => 12999.0,
            ];

            $vaultRowsPaid = SatDownload::query()
                ->where('cuenta_id', $cuentaIdForVault)
                ->where(function ($q) {
                    $q->where('tipo', 'VAULT')
                      ->orWhere('tipo', 'BOVEDA');
                })
                ->where(function ($q) {
                    $q->whereNotNull('paid_at')
                      ->orWhereIn('status', ['PAID', 'paid', 'PAGADO', 'pagado']);
                })
                ->get();

            $totalGbFromVault = 0.0;

            foreach ($vaultRowsPaid as $vr) {
                $gb = (float) ($vr->vault_gb ?? $vr->gb ?? 0);

                if ($gb <= 0) {
                    $cost = (float) ($vr->costo_mxn ?? $vr->costo ?? 0);
                    if ($cost > 0) {
                        foreach ($vaultPricing as $gbOpt => $priceOpt) {
                            if (abs($priceOpt - $cost) < 0.5) {
                                $gb = (float) $gbOpt;
                                break;
                            }
                        }
                    }
                }

                if ($gb <= 0) {
                    $source = (string) ($vr->alias ?? $vr->nombre ?? '');
                    if (preg_match('/(\d+)\s*gb/i', $source, $m)) {
                        $gb = (float) $m[1];
                    }
                }

                if ($gb > 0) {
                    $totalGbFromVault += $gb;
                }
            }

            $vaultQuotaFromVaultRows = $totalGbFromVault;
        }
    } catch (\Throwable $e) {
        \Log::warning('[SAT:vault] Error calculando cuota de bóveda desde VAULT', [
            'cuenta_id' => $cuenta->id ?? null,
            'error'     => $e->getMessage(),
        ]);
    }

    $vaultQuotaGb = max(
        $vaultBaseGb,
        $vaultQuotaFromAccount,
        $vaultQuotaFromVaultRows,
        $vaultQuotaFromStorage
    );

    $vaultPurchasedGb = max(0.0, $vaultQuotaGb - $vaultBaseGb);

    $vaultAvailableGb = $vaultQuotaGb > 0
        ? max(0.0, $vaultQuotaGb - $vaultUsedGb)
        : 0.0;

    $vaultFreeGb = $vaultAvailableGb;

    $vaultUsedPct = $vaultQuotaGb > 0
        ? min(100, round(($vaultUsedGb / $vaultQuotaGb) * 100))
        : 0;

    $vaultActive = $vaultQuotaGb > 0;

    $vaultForJs = [
        'active'        => $vaultActive,
        'quota_gb'      => $vaultQuotaGb,
        'base_gb'       => $vaultBaseGb,
        'purchased_gb'  => $vaultPurchasedGb,
        'used_gb'       => $vaultUsedGb,
        'available_gb'  => $vaultAvailableGb,
        'used'          => $vaultUsedGb,
        'free'          => $vaultAvailableGb,
        'used_pct'      => $vaultUsedPct,
    ];

    $vaultBtnLabel = $vaultActive ? 'Bóveda activa' : 'Activar bóveda';

    $bootData = $bootData ?? [];
    if ($bootData instanceof \Illuminate\Support\Collection) {
        $bootData = $bootData->all();
    }

    $bootRowsRaw = is_array($bootData) ? $bootData : (array) $bootData;

    $bootRows = collect($bootRowsRaw)
        ->map(function ($row) {
            return is_object($row) ? (array) $row : (array) $row;
        })
        ->filter(function ($row) {
            $kind = strtolower((string) ($row['kind'] ?? ''));
            $uuid = trim((string) ($row['uuid'] ?? ''));
            return $kind === 'cfdi' && $uuid !== '';
        })
        ->values()
        ->all();

    $bootTotals = [
        'count' => count($bootRows),
        'sub'   => collect($bootRows)->sum('subtotal'),
        'iva'   => collect($bootRows)->sum('iva'),
        'tot'   => collect($bootRows)->sum('total'),
    ];

    try {
        $rfcsFromCfdis = collect($bootRows ?? [])
            ->map(function ($row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }

                $rfc = strtoupper((string) (
                    $row['rfc'] ?? $row['rfc_emisor'] ?? $row['rfc_receptor'] ?? ''
                ));

                if (!$rfc) {
                    return null;
                }

                $nombre = trim((string) (
                    $row['razon'] ??
                    $row['razon_social'] ??
                    $row['razon_emisor'] ??
                    $row['razon_receptor'] ??
                    ''
                ));

                return [
                    'rfc'    => $rfc,
                    'nombre' => $nombre,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $rfcs = collect($rfcs ?? [])
            ->merge($rfcsFromCfdis)
            ->unique('rfc')
            ->sortBy('rfc')
            ->values()
            ->all();
    } catch (\Throwable $e) {
        \Log::warning('[SAT:vault] No se pudieron derivar RFCs desde CFDI de bóveda', [
            'cuenta_id' => $cuenta->id ?? null,
            'error'     => $e->getMessage(),
        ]);
    }

    $rtCartIndex  = \Route::has('cliente.sat.cart.index') ? route('cliente.sat.cart.index') : null;
    $rtCartList   = \Route::has('cliente.sat.cart.list') ? route('cliente.sat.cart.list') : $rtCartIndex;
    $rtCartAdd    = \Route::has('cliente.sat.cart.add') ? route('cliente.sat.cart.add') : null;
    $rtCartRemove = \Route::has('cliente.sat.cart.remove') ? route('cliente.sat.cart.remove') : null;
    $rtCartPay    = \Route::has('cliente.sat.cart.checkout') ? route('cliente.sat.cart.checkout') : null;

    $rtVaultExport = \Route::has('cliente.sat.vault.export') ? route('cliente.sat.vault.export') : null;
    $rtVaultXml    = \Route::has('cliente.sat.vault.xml') ? route('cliente.sat.vault.xml') : null;
    $rtVaultPdf    = \Route::has('cliente.sat.vault.pdf') ? route('cliente.sat.vault.pdf') : null;
    $rtVaultZip    = \Route::has('cliente.sat.vault.zip') ? route('cliente.sat.vault.zip') : null;

    $vaultFileRows = $vaultFileRows ?? [];

    $rtVaultImportStore = \Route::has('cliente.sat.vault.import.store')
        ? route('cliente.sat.vault.import.store')
        : null;

    $vaultBootJson = json_encode([
        'rows'   => $bootRows,
        'totals' => $bootTotals,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $vaultConfigJson = json_encode([
        'csrf'      => csrf_token(),
        'isProPlan' => (bool) ($isPro ?? false),
        'downloads' => [],
        'routes'    => [
            'cartIndex'    => $rtCartIndex,
            'cartList'     => $rtCartList,
            'cartAdd'      => $rtCartAdd,
            'cartRemove'   => $rtCartRemove,
            'cartCheckout' => $rtCartPay,
            'cartPay'      => $rtCartPay,
            'vaultIndex'   => \Route::has('cliente.sat.vault') ? route('cliente.sat.vault') : null,
            'vaultFromDownload' => \Route::has('cliente.sat.vault.fromDownload')
                ? route('cliente.sat.vault.fromDownload', ['download' => '__ID__'])
                : null,
            'vaultExport'  => $rtVaultExport,
            'vaultXml'     => $rtVaultXml,
            'vaultPdf'     => $rtVaultPdf,
            'vaultZip'     => $rtVaultZip,
            'externalRfcInvite' => \Route::has('cliente.sat.rfcs.external.invite')
                ? route('cliente.sat.rfcs.external.invite')
                : (\Route::has('cliente.sat.external.invite')
                    ? route('cliente.sat.external.invite')
                    : null),
            'externalRfcRegisterForm' => \Route::has('sat.external.register')
                ? route('sat.external.register')
                : null,
        ],
        'vault' => $vaultForJs,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/client/css/sat-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/client/css/sat-vault.css') }}">
@endpush

@section('content')
<div class="sat-ui" id="satVaultApp">
    <div class="vault-page">

        @if(session('success'))
            <div class="vault-alert vault-alert--success">
                <div class="vault-alert__title">Proceso completado</div>
                <div class="vault-alert__text">{{ session('success') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="vault-alert vault-alert--error">
                <div class="vault-alert__title">Ocurrió un problema</div>
                <div class="vault-alert__text">{{ session('error') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="vault-alert vault-alert--error">
                <div class="vault-alert__title">Hay datos pendientes por corregir</div>
                <div class="vault-alert__text">{{ collect($errors->all())->implode(' ') }}</div>
            </div>
        @endif

        <div class="sat-card vault-hero-card">
            <div class="vault-hero-main">
                <div>
                    <div class="vault-hero-title">Bóveda fiscal</div>
                    <div class="vault-hero-sub">
                        Centraliza CFDI, ZIP y documentos indexados sin repetir información innecesaria.
                    </div>
                </div>

                <div class="vault-hero-actions">
                    <div class="sat-cart-compact" id="satCartWidget">
                        <span class="sat-cart-label">Carrito SAT</span>
                        <span class="sat-cart-pill">
                            <span id="satCartCount">0</span> elementos ·
                            <span id="satCartTotal">$0.00</span> ·
                            <span id="satCartWeight">0.00 MB</span>
                        </span>
                    </div>

                    <button
                        type="button"
                        class="btn btn-amazon{{ $vaultActive ? ' btn-amazon--active' : '' }}"
                        id="btnVaultCta"
                        @if($vaultActive) disabled aria-disabled="true" @endif
                    >
                        <span class="btn-amazon-icon" aria-hidden="true">💾</span>
                        <span class="btn-amazon-label">{{ $vaultBtnLabel }}</span>
                    </button>
                </div>
            </div>

            <div class="vault-hero-storage">
                <div class="vault-summary-label">Capacidad de bóveda</div>

                @if($vaultActive)
                    <div class="vault-summary-pill">
                        <span>{{ number_format($vaultUsedGb, 2) }} GB usados</span>
                        <span class="vault-summary-dot">·</span>
                        <span>{{ number_format($vaultFreeGb, 2) }} GB libres</span>
                        <span class="vault-summary-dot">·</span>
                        <span>Cuota {{ number_format($vaultQuotaGb, 2) }} GB</span>
                    </div>

                    <div class="vault-summary-bar">
                        <div class="vault-summary-bar-bg">
                            <div class="vault-summary-bar-fill" style="width: {{ $vaultUsedPct }}%;"></div>
                        </div>
                        <div class="vault-summary-bar-info">{{ $vaultUsedPct }}% en uso</div>
                    </div>
                @else
                    <div class="vault-summary-pill vault-summary-pill--off">
                        <span>Bóveda desactivada</span>
                    </div>
                    <div class="vault-summary-bar-info vault-summary-bar-info--hint">
                        Activa tu bóveda para resguardar tu histórico SAT.
                    </div>
                @endif
            </div>
        </div>

        <div class="sat-card vault-upload-card">
            <div class="vault-upload-card__head">
                <div>
                    <div class="vault-upload-card__title">Carga rápida de archivos</div>
                    <div class="vault-upload-card__sub">
                        Sube ZIP, XML o CSV y asígnalo a un RFC real de tu cuenta.
                    </div>
                </div>

                <div class="vault-upload-card__badges">
                    <span class="vault-upload-badge">ZIP CFDI</span>
                    <span class="vault-upload-badge">XML individual</span>
                    <span class="vault-upload-badge">CSV SAT</span>
                </div>
            </div>

            @if($rtVaultImportStore)
                <form
                    method="POST"
                    action="{{ $rtVaultImportStore }}"
                    enctype="multipart/form-data"
                    class="vault-upload-form"
                >
                    @csrf

                    <div class="vault-upload-grid">
                        <div class="vault-upload-field vault-upload-field--file">
                            <label class="vault-upload-label">Archivo</label>
                            <input
                                type="file"
                                name="archivos[]"
                                id="vaultArchivosInput"
                                class="input vault-upload-input-file"
                                accept=".zip,.xml,.csv,application/zip,application/xml,text/xml,text/csv"
                                multiple
                                required
                            >
                            <div class="vault-upload-help" id="vaultFilesHelp">
                                Puedes subir uno o varios archivos a la vez. Ejemplos válidos: ZIP emitidos/recibidos SAT, XML CFDI 4.0, CSV reporte SAT.
                            </div>
                        </div>

                        <div class="vault-upload-field">
                            <label class="vault-upload-label">RFC asociado</label>

                            <select name="rfc_select" class="select" id="vaultRfcSelect">
                                <option value="">Seleccionar RFC</option>
                                @foreach($rfcs as $opt)
                                    @php
                                        $rfcOpt    = is_array($opt) ? ($opt['rfc'] ?? '') : (string) $opt;
                                        $nombreOpt = is_array($opt) ? ($opt['nombre'] ?? '') : '';
                                    @endphp
                                    @if($rfcOpt)
                                        <option value="{{ $rfcOpt }}">
                                            {{ $rfcOpt }}@if($nombreOpt) — {{ Str::limit($nombreOpt, 32) }}@endif
                                        </option>
                                    @endif
                                @endforeach
                            </select>

                            <input
                                type="text"
                                name="rfc"
                                id="vaultRfcManual"
                                class="input"
                                placeholder="Escribe RFC manual si no aparece en la lista"
                                maxlength="20"
                            >

                            <div class="vault-upload-help">
                                Selecciona un RFC existente o escríbelo manualmente.
                            </div>
                        </div>

                        <div class="vault-upload-field vault-upload-field--stats">
                            <div class="vault-upload-mini">
                                <div class="vault-upload-mini__item">
                                    <span class="vault-upload-mini__label">CFDI indexados</span>
                                    <span class="vault-upload-mini__value">{{ number_format((int)($bootTotals['count'] ?? 0)) }}</span>
                                </div>
                                <div class="vault-upload-mini__item">
                                    <span class="vault-upload-mini__label">Archivos guardados</span>
                                    <span class="vault-upload-mini__value">{{ is_countable($vaultFileRows ?? []) ? count($vaultFileRows) : 0 }}</span>
                                </div>
                                <div class="vault-upload-mini__item">
                                    <span class="vault-upload-mini__label">Libre</span>
                                    <span class="vault-upload-mini__value">{{ number_format((float)($vaultFreeGb ?? 0), 2) }} GB</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="vault-upload-actions">
                        <button type="submit" class="btn btn-primary vault-upload-submit">
                            <span aria-hidden="true">📤</span>
                            <span>Subir e indexar</span>
                        </button>

                        <div class="vault-upload-note">
                            ZIP/XML indexan CFDI. CSV crea índice rápido desde columnas compatibles.
                        </div>
                    </div>
                </form>
            @else
                <div class="vault-alert vault-alert--error" style="margin-top:10px;">
                    <div class="vault-alert__title">Ruta de importación no disponible</div>
                    <div class="vault-alert__text">
                        No está registrada la ruta <code>cliente.sat.vault.import.store</code>.
                    </div>
                </div>
            @endif
        </div>

        <div class="sat-card vault-quick-card">
            <div class="vqs-header">
                <div>
                    <h2 class="vqs-title">Resumen rápido CFDI</h2>
                    <p class="vqs-subtitle">Vista ejecutiva de documentos ya indexados en la bóveda.</p>
                </div>
                <div class="vqs-period-chip">Últimos 6 meses</div>
            </div>

            <div class="vqs-main">
                <div class="vqs-kpi-grid">
                    <article class="vqs-kpi">
                        <div class="vqs-kpi-label">Total CFDI</div>
                        <div class="vqs-kpi-main">
                            <span id="vqCount" class="vqs-kpi-number">0</span>
                            <span class="vqs-kpi-tag">documentos</span>
                        </div>
                        <div class="vqs-kpi-footer">
                            Monto total: <strong id="vqTotal" class="vqs-kpi-money">$0.00</strong>
                        </div>
                    </article>

                    <article class="vqs-kpi">
                        <div class="vqs-kpi-label">
                            Emitidos <span class="vqs-dot vqs-dot--em"></span>
                        </div>
                        <div class="vqs-kpi-main">
                            <span id="vqEmitCount" class="vqs-kpi-number">0</span>
                            <span class="vqs-kpi-tag">CFDI</span>
                        </div>
                        <div class="vqs-kpi-footer">
                            Total: <strong id="vqEmitTotal" class="vqs-kpi-money">$0.00</strong>
                        </div>
                    </article>

                    <article class="vqs-kpi">
                        <div class="vqs-kpi-label">
                            Recibidos <span class="vqs-dot vqs-dot--rec"></span>
                        </div>
                        <div class="vqs-kpi-main">
                            <span id="vqRecCount" class="vqs-kpi-number">0</span>
                            <span class="vqs-kpi-tag">CFDI</span>
                        </div>
                        <div class="vqs-kpi-footer">
                            Total: <strong id="vqRecTotal" class="vqs-kpi-money">$0.00</strong>
                        </div>
                    </article>

                    <article class="vqs-kpi">
                        <div class="vqs-kpi-label">IVA estimado</div>
                        <div class="vqs-kpi-main">
                            <span id="vqIvaTotal" class="vqs-kpi-number">$0.00</span>
                        </div>
                        <div class="vqs-kpi-footer">
                            Subtotal: <strong id="vqSubTotal" class="vqs-kpi-money">$0.00</strong>
                        </div>
                    </article>
                </div>

                <div class="vqs-top-card">
                    <div class="vqs-top-header">
                        <div>
                            <div class="vqs-top-title">Top 5 RFC / Razón social por monto</div>
                            <div class="vqs-top-subtitle">Contribuyentes con mayor importe acumulado en el periodo.</div>
                        </div>
                    </div>

                    <div class="vq-table-wrapper">
                        <table class="vq-table">
                            <thead>
                                <tr>
                                    <th>RFC</th>
                                    <th>Razón social CFDI</th>
                                    <th class="t-center">CFDI</th>
                                    <th class="t-right">Total</th>
                                </tr>
                            </thead>
                            <tbody id="vqTopRfcBody">
                                <tr>
                                    <td colspan="4" class="vq-empty">Sin datos suficientes todavía…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="sat-card vault-metrics-card">
            <div class="vm-col">
                <div>
                    <div class="vm-label">CFDI filtrados</div>
                    <div class="vm-main" id="vmTotalCount">0</div>
                    <div class="vm-sub">
                        Emitidos: <b id="vmTotalEmitidos">0</b> ·
                        Recibidos: <b id="vmTotalRecibidos">0</b>
                    </div>
                </div>

                <div class="vm-bar-wrapper">
                    <div class="vm-bar-bg">
                        <div class="vm-bar-fill vm-bar-emitidos" id="vmBarEmitidos"></div>
                    </div>
                    <div class="vm-bar-bg">
                        <div class="vm-bar-fill vm-bar-recibidos" id="vmBarRecibidos"></div>
                    </div>
                    <div class="vm-sub">
                        <span class="vm-tag"><span class="vm-tag-dot"></span> Emitidos</span>
                        <span class="vm-tag"><span class="vm-tag-dot vm-tag-dot--blue"></span> Recibidos</span>
                    </div>
                </div>
            </div>

            <div class="vm-col">
                <div>
                    <div class="vm-label">Total filtrado (MXN)</div>
                    <div class="vm-main" id="vmTotGlobal">$0.00</div>
                    <div class="vm-sub">
                        Emitidos: <b id="vmTotEmitidos">$0.00</b> ·
                        Recibidos: <b id="vmTotRecibidos">$0.00</b>
                    </div>
                </div>
            </div>

            <div class="vm-col">
                <div>
                    <div class="vm-label">Ticket promedio / RFC top</div>
                    <div class="vm-main" id="vmAvgTotal">$0.00</div>
                    <div class="vm-sub">
                        RFC con mayor monto: <b id="vmTopRfc">—</b><br>
                        Monto acumulado: <b id="vmTopRfcTot">$0.00</b>
                    </div>
                </div>
            </div>
        </div>

        <div class="sat-card vault-charts-card">
            <div class="vault-chart-box">
                <div class="vault-chart-header">
                    <div>
                        <div class="vault-chart-title">Distribución por tipo</div>
                        <div class="vault-chart-caption">CFDI emitidos vs recibidos</div>
                    </div>
                </div>
                <div class="vault-chart-canvas-wrap">
                    <canvas id="vaultChartTipo"></canvas>
                </div>
                <div class="vault-chart-footer">
                    <div class="vault-chart-legend">
                        <span class="legend-item"><span class="legend-dot legend-dot--em"></span><span>Emitidos</span></span>
                        <span class="legend-item"><span class="legend-dot legend-dot--rec"></span><span>Recibidos</span></span>
                    </div>
                    <div class="vault-chart-hint">Tooltip para ver detalle.</div>
                </div>
            </div>

            <div class="vault-chart-box">
                <div class="vault-chart-header">
                    <div>
                        <div class="vault-chart-title">Top RFC por monto</div>
                        <div class="vault-chart-caption">Top 5 por total acumulado</div>
                    </div>
                </div>
                <div class="vault-chart-canvas-wrap">
                    <canvas id="vaultChartTopRfc"></canvas>
                </div>
                <div class="vault-chart-footer">
                    <div class="vault-chart-hint">Barras ordenadas por importe.</div>
                </div>
            </div>

            <div class="vault-chart-box">
                <div class="vault-chart-header">
                    <div>
                        <div class="vault-chart-title">Flujo diario</div>
                        <div class="vault-chart-caption">Total por fecha</div>
                    </div>
                </div>
                <div class="vault-chart-canvas-wrap">
                    <canvas id="vaultChartFlujo"></canvas>
                </div>
                <div class="vault-chart-footer">
                    <div class="vault-chart-hint">Escala automática.</div>
                </div>
            </div>
        </div>

        <div class="sat-card vault-filters-card">
            <div class="vault-filters-grid">
                <div class="vf-field">
                    <label class="vf-label">Tipo de CFDI</label>
                    <select class="select" id="fTipo" aria-label="Tipo CFDI">
                        <option value="ambos">Ambos</option>
                        <option value="emitidos">Emitidos</option>
                        <option value="recibidos">Recibidos</option>
                    </select>
                </div>

                <div class="vf-field">
                    <label class="vf-label">RFC</label>
                    <select class="select" id="fRfc" aria-label="RFC">
                        <option value="">Todos los RFC</option>
                        @foreach($rfcs as $opt)
                            @php
                                $rfcOpt2    = is_array($opt) ? ($opt['rfc'] ?? '') : (string) $opt;
                                $nombreOpt2 = is_array($opt) ? ($opt['nombre'] ?? '') : '';
                            @endphp
                            @if($rfcOpt2)
                                <option value="{{ $rfcOpt2 }}">
                                    {{ $rfcOpt2 }}@if($nombreOpt2) — {{ Str::limit($nombreOpt2, 40) }}@endif
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div class="vf-field vf-field--span2">
                    <label class="vf-label">Búsqueda libre</label>
                    <input type="text" class="input" id="fQuery" placeholder="RFC, razón social o UUID" aria-label="Búsqueda libre">
                </div>

                <div class="vf-field">
                    <label class="vf-label">Desde</label>
                    <input type="date" class="input" id="fDesde" aria-label="Desde">
                </div>

                <div class="vf-field">
                    <label class="vf-label">Hasta</label>
                    <input type="date" class="input" id="fHasta" aria-label="Hasta">
                </div>

                <div class="vf-field">
                    <label class="vf-label">Mínimo (MXN)</label>
                    <input type="number" class="input" id="fMin" placeholder="0.00" step="0.01" min="0" aria-label="Mínimo">
                </div>

                <div class="vf-field">
                    <label class="vf-label">Máximo (MXN)</label>
                    <input type="number" class="input" id="fMax" placeholder="0.00" step="0.01" min="0" aria-label="Máximo">
                </div>

                <div class="vf-field vf-field--chips">
                    <span class="vf-label vf-label--inline">Rangos rápidos</span>
                    <div class="vault-filters-chips">
                        <button type="button" class="btn soft btn-chip vault-quick-date" data-range="today">Hoy</button>
                        <button type="button" class="btn soft btn-chip vault-quick-date" data-range="week">Esta semana</button>
                        <button type="button" class="btn soft btn-chip vault-quick-date" data-range="month">Este mes</button>
                        <button type="button" class="btn soft btn-chip vault-quick-date" data-range="year">Año actual</button>
                        <button type="button" class="btn soft btn-chip vault-quick-date" data-range="all">Todo</button>
                    </div>
                </div>

                <div class="vf-actions">
                    <button type="button" class="btn soft" id="btnApply"><span aria-hidden="true">🔍</span><span>Filtrar</span></button>
                    <button type="button" class="btn soft" id="btnClear"><span aria-hidden="true">🧹</span><span>Limpiar</span></button>
                </div>
            </div>
        </div>

        <div class="sat-card vault-table-card">
            <div class="vault-table-summary">
                <div class="vts-box vts-box--count">
                    <span class="vts-label">CFDI filtrados</span>
                    <span class="vts-value vts-value-count" id="tCnt">0</span>
                </div>

                <div class="vts-box">
                    <span class="vts-label">Subtotal</span>
                    <span class="vts-value" id="tSub">$0.00</span>
                </div>

                <div class="vts-box">
                    <span class="vts-label">IVA</span>
                    <span class="vts-value" id="tIva">$0.00</span>
                </div>

                <div class="vts-box">
                    <span class="vts-label">Total</span>
                    <span class="vts-value vts-value-strong" id="tTot">$0.00</span>
                </div>

                <div class="vts-box vts-box--action">
                    <button type="button" class="btn btn-primary btn-report vts-export-btn" id="btnExportVault">
                        <span aria-hidden="true" class="vts-export-icon">📊</span>
                        <span class="vts-export-label">Exportar Excel</span>
                    </button>
                    <span class="vts-hint">Exporta según filtros actuales.</span>
                </div>
            </div>

            <div class="vault-table-shell">
                <div class="vault-table-top">
                    <div class="vault-table-title">
                        <div class="vault-table-title-main">CFDI en bóveda</div>
                        <div class="vault-table-title-sub">
                            Consulta y descarga por UUID.
                        </div>
                    </div>

                    <div class="vault-table-tools">
                        <div class="vault-table-chip">
                            <span class="vault-table-chip-label">Mostrando</span>
                            <span class="vault-table-chip-value" id="pgInfo">0–0 de 0</span>
                        </div>

                        <div class="vault-table-size">
                            <span class="vault-table-size-label">Por página</span>
                            <select class="select" id="pgSize" aria-label="Resultados por página">
                                <option>10</option>
                                <option selected>25</option>
                                <option>50</option>
                                <option>100</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="vault-table-scroll" role="region" aria-label="Tabla CFDI" tabindex="0">
                    <table class="vault-table-pro" id="vaultTable" aria-label="CFDI almacenados">
                        <thead>
                            <tr>
                                <th class="th-date">Fecha</th>
                                <th class="th-tipo">Tipo</th>
                                <th class="th-rfc">RFC</th>
                                <th class="th-razon">Razón social</th>
                                <th class="th-uuid">UUID</th>
                                <th class="th-money t-right">Subtotal</th>
                                <th class="th-money t-right">IVA</th>
                                <th class="th-money t-right">% IVA</th>
                                <th class="th-money t-right">Total</th>
                                <th class="th-actions t-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="vaultRows">
                            <tr><td colspan="10" class="empty-cell">Sin datos</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="vault-table-bottom">
                    <div class="vault-pag-left">
                        <button class="btn soft" id="pgPrev">← Anterior</button>
                        <button class="btn soft" id="pgNext">Siguiente →</button>
                    </div>
                    <div class="vault-pag-right">
                        <span class="vault-page-info mono" id="pgInfoMirror">0–0 de 0</span>
                    </div>
                </div>
            </div>
        </div>

                <div class="sat-card vault-table-card" style="margin-bottom:16px;">
            <div class="vault-table-top">
                <div class="vault-table-title">
                    <div class="vault-table-title-main">Archivos subidos a la bóveda</div>
                    <div class="vault-table-title-sub">
                        ZIP, XML y CSV resguardados y disponibles para descarga.
                    </div>
                </div>
            </div>

            <div class="vault-table-shell">
                <div class="vault-table-scroll" role="region" aria-label="Tabla archivos bóveda" tabindex="0">
                    <table class="vault-table-pro" aria-label="Archivos en bóveda">
                        <thead>
                            <tr>
                                <th class="th-date">Fecha</th>
                                <th class="th-rfc">RFC</th>
                                <th class="th-tipo">Tipo</th>
                                <th class="th-razon">Archivo</th>
                                <th class="th-money t-right">Tamaño</th>
                                <th class="th-actions t-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(empty($vaultFileRows))
                                <tr>
                                    <td colspan="6" class="empty-cell">Aún no hay archivos guardados en bóveda.</td>
                                </tr>
                            @else
                                @foreach($vaultFileRows as $f)
                                    @php
                                        $bytes = (int)($f['bytes'] ?? 0);
                                        $sizeLabel = $bytes >= 1073741824
                                            ? number_format($bytes / 1073741824, 2) . ' GB'
                                            : ($bytes >= 1048576
                                                ? number_format($bytes / 1048576, 2) . ' MB'
                                                : number_format($bytes / 1024, 2) . ' KB');
                                    @endphp
                                    <tr>
                                        <td>{{ $f['fecha'] ?? '—' }}</td>
                                        <td>{{ $f['rfc'] ?? '—' }}</td>
                                        <td>{{ $f['tipo_archivo'] ?? '—' }}</td>
                                        <td>{{ $f['filename'] ?? '—' }}</td>
                                        <td class="t-right">{{ $sizeLabel }}</td>
                                        <td class="t-center">
                                            @if(\Route::has('cliente.sat.vault.file'))
                                                <a class="btn soft" href="{{ route('cliente.sat.vault.file', ['id' => $f['id']]) }}">
                                                    Descargar
                                                </a>
                                            @else
                                                <span class="mono">Ruta no configurada</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="vaultConfirm" class="vault-modal" aria-hidden="true">
            <div class="vault-modal__backdrop"></div>

            <div class="vault-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="vaultConfirmTitle">
                <div class="vault-modal__icon"><span>🗄️</span></div>

                <h2 id="vaultConfirmTitle" class="vault-modal__title">Activar bóveda fiscal</h2>

                <p class="vault-modal__lead">Se agregará el concepto al carrito SAT.</p>

                <p class="vault-modal__text">
                    Podrás revisar el detalle antes de confirmar pago.
                </p>

                <div class="vault-modal__actions">
                    <button type="button" class="btn btn-secondary" data-vault-dismiss>Cancelar</button>
                    <button type="button" class="btn btn-primary" data-vault-accept>Sí, agregar al carrito</button>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <script id="sat-vault-boot-data" type="application/json">{!! $vaultBootJson ?: '{"rows":[],"totals":{"count":0,"sub":0,"iva":0,"tot":0}}' !!}</script>
    <script id="sat-vault-config" type="application/json">{!! $vaultConfigJson ?: '{"csrf":"","isProPlan":false,"downloads":[],"routes":{},"vault":{}}' !!}</script>

    <script>
        window.P360_SAT = JSON.parse(
            document.getElementById('sat-vault-config')?.textContent || '{"csrf":"","isProPlan":false,"downloads":[],"routes":{},"vault":{}}'
        );

        window.__VAULT_BOOT = JSON.parse(
            document.getElementById('sat-vault-boot-data')?.textContent || '{"rows":[],"totals":{"count":0,"sub":0,"iva":0,"tot":0}}'
        );
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rfcSelect  = document.getElementById('vaultRfcSelect');
            const rfcManual  = document.getElementById('vaultRfcManual');
            const filesInput = document.getElementById('vaultArchivosInput');
            const filesHelp  = document.getElementById('vaultFilesHelp');

            if (rfcSelect && rfcManual) {
                rfcSelect.addEventListener('change', function () {
                    const value = String(this.value || '').trim();
                    if (value !== '') {
                        rfcManual.value = value;
                    }
                });
            }

            if (filesInput && filesHelp) {
                filesInput.addEventListener('change', function () {
                    const total = this.files ? this.files.length : 0;

                    if (!total) {
                        filesHelp.textContent = 'Puedes subir uno o varios archivos a la vez. Ejemplos válidos: ZIP emitidos/recibidos SAT, XML CFDI 4.0, CSV reporte SAT.';
                        return;
                    }

                    if (total === 1) {
                        filesHelp.textContent = '1 archivo seleccionado: ' + this.files[0].name;
                        return;
                    }

                    filesHelp.textContent = total + ' archivos seleccionados para subir e indexar.';
                });
            }
        });
    </script>

    <script src="{{ asset('assets/client/js/sat-dashboard.js') }}" defer></script>
    <script src="{{ asset('assets/client/js/sat-vault.js') }}" defer></script>
@endpush