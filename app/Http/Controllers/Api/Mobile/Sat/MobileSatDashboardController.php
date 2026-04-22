<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatDownload;
use App\Models\Cliente\SatUserMetadataUpload;
use App\Models\Cliente\SatUserReportUpload;
use App\Models\Cliente\SatUserXmlUpload;
use App\Services\Sat\Client\SatDownloadsPresenter;
use App\Services\Sat\Client\SatRfcOptionsService;
use App\Services\Sat\Client\SatVaultStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MobileSatDashboardController extends Controller
{
    public function __construct(
        private readonly SatVaultStorage $vaultStorage,
        private readonly SatRfcOptionsService $rfcOptionsSvc,
        private readonly SatDownloadsPresenter $presenter,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok'   => false,
                'msg'  => 'No autenticado.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        $cuentaId = trim((string) ($user->cuenta_id ?? ''));
        if ($cuentaId === '') {
            return response()->json([
                'ok'   => false,
                'msg'  => 'Cuenta inválida.',
                'code' => 'ACCOUNT_INVALID',
            ], 422);
        }

        $cuentaCliente = $this->vaultStorage->fetchCuentaCliente($cuentaId);

        if (!$cuentaCliente) {
            return response()->json([
                'ok'   => false,
                'msg'  => 'No se encontró la cuenta cliente.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 404);
        }

        $planRaw   = (string) ($cuentaCliente->plan_actual ?? $cuentaCliente->plan ?? 'FREE');
        $plan      = strtoupper(trim($planRaw));
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        [$vaultSummary, $vaultForJs] = $this->presenter->buildVaultSummaries($cuentaId, $cuentaCliente);

        $credList = collect();
        $cotizaciones = collect();
        $selectedRfc = strtoupper(trim((string) $request->query('rfc', '')));
        $unifiedDownloadItems = collect();
        $downloadSources = [
            'centro_sat' => 0,
            'boveda_v1'  => 0,
            'boveda_v2'  => 0,
        ];
        $storageBreakdown = [
            'used_bytes'      => 0,
            'available_bytes' => 0,
            'quota_bytes'     => 0,
            'used_gb'         => 0.0,
            'available_gb'    => 0.0,
            'quota_gb'        => 0.0,
            'used_pct'        => 0.0,
            'available_pct'   => 0.0,
            'chart'           => [
                'series' => [0, 0],
                'labels' => ['Usado', 'Disponible'],
            ],
        ];

        try {
            $credList = $this->rfcOptionsSvc->loadCredentials($cuentaId, 'mysql_clientes');

            if ($selectedRfc === '') {
                $activeCred = $credList->first(function ($item) {
                    $meta = $item->meta ?? [];

                    if (is_string($meta)) {
                        $decoded = json_decode($meta, true);
                        $meta = is_array($decoded) ? $decoded : [];
                    }

                    if (!is_array($meta)) {
                        $meta = [];
                    }

                    return (bool) ($meta['is_active'] ?? true) === true
                        && trim((string) ($item->rfc ?? '')) !== '';
                });

                if (!$activeCred) {
                    $activeCred = $credList->first(function ($item) {
                        return trim((string) ($item->rfc ?? '')) !== '';
                    });
                }

                $selectedRfc = strtoupper(trim((string) ($activeCred->rfc ?? '')));
            }

            $cotizaciones = SatDownload::on('mysql_clientes')
                ->where('cuenta_id', $cuentaId)
                ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->filter(fn (SatDownload $d) => $this->isCotizacionLikeDownload($d))
                ->map(fn (SatDownload $d) => $this->transformCotizacionRow($d, $credList))
                ->values();

            $usuarioId = (string) ($user->id ?? '');

            $v2Items = $this->buildSatV2LikeItems(
                cuentaId: $cuentaId,
                usuarioId: $usuarioId,
                selectedRfc: $selectedRfc
            );

            $unifiedDownloadItems = $this->buildUnifiedDownloadItemsForMobile(
                cuentaId: $cuentaId,
                usuarioId: $usuarioId,
                selectedRfc: $selectedRfc,
                v2Items: $v2Items
            );

            $downloadSources = [
                'centro_sat' => (int) $unifiedDownloadItems->where('origin', 'centro_sat')->count(),
                'boveda_v1'  => (int) $unifiedDownloadItems->where('origin', 'boveda_v1')->count(),
                'boveda_v2'  => (int) $unifiedDownloadItems->where('origin', 'boveda_v2')->count(),
            ];

            $storageBreakdown = $this->buildUnifiedStorageBreakdownForMobile(
                cuentaId: $cuentaId,
                unifiedItems: $unifiedDownloadItems
            );
        } catch (\Throwable $e) {
            return response()->json([
                'ok'   => false,
                'msg'  => 'No se pudo cargar el dashboard SAT móvil.',
                'code' => 'SAT_DASHBOARD_ERROR',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }

        $estadoCuenta = strtolower(trim((string) ($cuentaCliente->estado_cuenta ?? 'activa')));
        $isBlocked = (int) ($cuentaCliente->is_blocked ?? 0) === 1;

        $adminModulesState = $this->resolveAdminModulesState($cuentaCliente);

        $activeModules = $this->buildModules(
            isProPlan: $isProPlan,
            estadoCuenta: $estadoCuenta,
            isBlocked: $isBlocked,
            adminModulesState: $adminModulesState
        );
        $activeModulesCount = collect($activeModules)
            ->where('state', 'active')
            ->where('access', true)
            ->count();

        $blockedModulesCount = collect($activeModules)
            ->filter(fn (array $module) => ($module['state'] ?? '') === 'blocked' || ($module['access'] ?? false) !== true)
            ->count();

        $health = $this->buildHealth(
            estadoCuenta: $estadoCuenta,
            isBlocked: $isBlocked,
            selectedRfc: $selectedRfc,
            rfcCount: (int) $credList->count()
        );

        return response()->json([
            'ok'   => true,
            'msg'  => 'Dashboard SAT móvil cargado correctamente.',
            'data' => [
                'hero' => [
                    'title'        => trim((string) ($user->nombre ?? $cuentaCliente->nombre_comercial ?? 'PACTOPIA360')),
                    'subtitle'     => trim((string) ($cuentaCliente->nombre_comercial ?? $cuentaCliente->razon_social ?? '')),
                    'plan'         => $plan,
                    'status'       => $estadoCuenta !== '' ? $estadoCuenta : 'activa',
                    'next_payment' => !empty($cuentaCliente->next_invoice_date)
                        ? Carbon::parse($cuentaCliente->next_invoice_date)->format('Y-m-d')
                        : '',
                ],

                'health' => $health,

                    'quick_actions' => [
                    [
                        'key'         => 'sat',
                        'label'       => 'SAT',
                        'icon'        => 'receipt',
                        'description' => 'Cotizaciones, pagos y seguimiento SAT.',
                    ],
                    [
                        'key'         => 'account',
                        'label'       => 'Mi cuenta',
                        'icon'        => 'person',
                        'description' => 'Perfil, datos de cuenta y plan.',
                    ],
                    [
                        'key'         => 'pay',
                        'label'       => 'Estado de cuenta',
                        'icon'        => 'account_balance',
                        'description' => 'Cargos, pagos y periodos facturables.',
                    ],
                    [
                        'key'         => 'invoices',
                        'label'       => 'Facturas',
                        'icon'        => 'description',
                        'description' => 'Descarga de facturas y ZIP disponibles.',
                    ],
                ],

                'modules' => $activeModules,

                'account' => [
                    'id'               => (string) ($cuentaCliente->id ?? ''),
                    'rfc_padre'        => (string) ($cuentaCliente->rfc_padre ?? ''),
                    'razon_social'     => (string) ($cuentaCliente->razon_social ?? ''),
                    'nombre_comercial' => (string) ($cuentaCliente->nombre_comercial ?? ''),
                    'email'            => (string) ($cuentaCliente->email ?? ''),
                    'plan'             => $plan,
                    'is_pro_plan'      => $isProPlan,
                    'modo_cobro'       => (string) ($cuentaCliente->modo_cobro ?? ''),
                    'estado_cuenta'    => (string) ($cuentaCliente->estado_cuenta ?? ''),
                    'is_blocked'       => $isBlocked,
                    'admin_account_id' => !empty($cuentaCliente->admin_account_id)
                        ? (int) $cuentaCliente->admin_account_id
                        : null,
                ],

                'selected_rfc' => $selectedRfc,

                'rfcs' => $credList->map(function ($item) {
                    $meta = $item->meta ?? [];

                    if (is_string($meta)) {
                        $decoded = json_decode($meta, true);
                        $meta = is_array($decoded) ? $decoded : [];
                    }

                    if (!is_array($meta)) {
                        $meta = [];
                    }

                    return [
                        'id'           => (string) ($item->id ?? ''),
                        'rfc'          => strtoupper(trim((string) ($item->rfc ?? ''))),
                        'razon_social' => trim((string) ($item->razon_social ?? '')),
                        'is_active'    => (bool) ($meta['is_active'] ?? true),
                    ];
                })->values(),

                'quotes' => $cotizaciones->values(),
                'vault_summary' => $vaultSummary,
                'vault_flags' => $vaultForJs,
                'download_sources' => $downloadSources,
                'storage_breakdown' => $storageBreakdown,
                'recent_files' => $unifiedDownloadItems->take(20)->values(),

                'totals' => [
                    'rfcs'            => (int) $credList->count(),
                    'quotes'          => (int) $cotizaciones->count(),
                    'recent_files'    => (int) $unifiedDownloadItems->count(),
                    'modules_active'  => (int) $activeModulesCount,
                    'modules_blocked' => (int) $blockedModulesCount,
                ],
            ],
        ], 200);
    }

    private function buildHealth(
        string $estadoCuenta,
        bool $isBlocked,
        string $selectedRfc,
        int $rfcCount
    ): array {
        if ($isBlocked) {
            return [
                'status'  => 'error',
                'message' => 'Tu cuenta está bloqueada temporalmente.',
            ];
        }

        if (in_array($estadoCuenta, ['suspendida', 'bloqueada', 'bloqueada_pago', 'pago_pendiente'], true)) {
            return [
                'status'  => 'warning',
                'message' => 'Tu cuenta requiere atención para operar correctamente.',
            ];
        }

        if ($rfcCount <= 0) {
            return [
                'status'  => 'warning',
                'message' => 'Aún no tienes RFC activos vinculados en SAT.',
            ];
        }

        if ($selectedRfc === '') {
            return [
                'status'  => 'warning',
                'message' => 'No se detectó RFC activo para el panel SAT.',
            ];
        }

        return [
            'status'  => 'ok',
            'message' => 'Cuenta operando correctamente',
        ];
    }


       private function mobileModulesCatalog(): array
    {
        return [
            'sat_descargas' => [
                'name'                => 'SAT Descargas',
                'icon'                => 'sat_descargas',
                'requires_account_ok' => true,
                'default_state'       => 'active',
                'default_access'      => true,
                'headline'            => 'SAT Descargas + Cotizaciones + Bóveda',
                'summary'             => 'Integra RFC, cotizaciones SAT, pagos, seguimiento operativo y bóveda SAT dentro del mismo ecosistema.',
                'chips'               => ['RFC', 'Cotizaciones', 'Pagos', 'Seguimiento', 'Bóveda', 'Descargas'],
                'kpis'                => [
                    ['label' => 'RFCs', 'value' => '0'],
                    ['label' => 'Cotizaciones', 'value' => '0'],
                    ['label' => 'Fuentes', 'value' => '0'],
                    ['label' => 'XML', 'value' => '0'],
                ],
            ],

            'boveda_fiscal' => [
                'name'                => 'Bóveda Fiscal',
                'icon'                => 'boveda_fiscal',
                'requires_account_ok' => true,
                'default_state'       => 'active',
                'default_access'      => true,
                'headline'            => 'Bóveda Fiscal SAT',
                'summary'             => 'Consulta, almacenamiento y visualización documental ligada al ecosistema SAT.',
                'chips'               => ['Bóveda', 'Archivos', 'SAT', 'Espacio'],
            ],

            'mi_cuenta' => [
                'name'           => 'Mi cuenta',
                'icon'           => 'mi_cuenta',
                'default_state'  => 'active',
                'default_access' => true,
                'headline'       => 'Mi cuenta',
                'summary'        => 'Perfil, plan, estado y configuración principal de la cuenta.',
                'chips'          => ['Perfil', 'Plan', 'Cuenta'],
            ],

            'pagos' => [
                'name'           => 'Pagos',
                'icon'           => 'pagos',
                'default_state'  => 'active',
                'default_access' => true,
                'headline'       => 'Pagos',
                'summary'        => 'Historial y control de pagos realizados por la cuenta.',
                'chips'          => ['Pagos', 'Historial', 'Control'],
            ],

            'facturas' => [
                'name'           => 'Facturas',
                'icon'           => 'facturas',
                'default_state'  => 'active',
                'default_access' => true,
                'headline'       => 'Facturas',
                'summary'        => 'Consulta y descarga de facturas disponibles.',
                'chips'          => ['Facturas', 'ZIP', 'Descargas'],
            ],

            'estado_cuenta' => [
                'name'           => 'Estado de cuenta',
                'icon'           => 'estado_cuenta',
                'default_state'  => 'active',
                'default_access' => true,
                'headline'       => 'Estado de cuenta',
                'summary'        => 'Consulta de cargos, pagos, saldos y periodos de cobro.',
                'chips'          => ['Cargos', 'Pagos', 'Saldo', 'Periodos'],
            ],

            'facturacion' => [
                'name'           => 'Facturación',
                'icon'           => 'facturacion',
                'default_state'  => 'active',
                'default_access' => true,
                'headline'       => 'Facturación + CFDI + Ventas + Timbres',
                'summary'        => 'Emisión, administración y control de CFDI comerciales con consumo de timbres e hits.',
                'chips'          => ['CFDI', 'Nuevo CFDI', 'Ventas', 'Receptores', 'Conceptos', 'Timbres'],
                'kpis'           => [
                    ['label' => 'CFDI', 'value' => '0'],
                    ['label' => 'Borradores', 'value' => '0'],
                    ['label' => 'Receptores', 'value' => '0'],
                    ['label' => 'Hits', 'value' => '0'],
                ],
            ],

            'crm' => [
                'name'           => 'CRM',
                'icon'           => 'crm',
                'requires_pro'   => true,
                'default_state'  => 'active',
                'default_access' => true,
                'headline'       => 'CRM + Ventas + Facturación + IA',
                'summary'        => 'Seguimiento comercial, clientes, contactos y oportunidades conectado con ventas y facturación.',
                'chips'          => ['Clientes', 'Contactos', 'Oportunidades', 'Seguimiento', 'Ventas', 'IA'],
                'kpis'           => [
                    ['label' => 'Clientes', 'value' => '0'],
                    ['label' => 'Contactos', 'value' => '0'],
                    ['label' => 'Oportunidades', 'value' => '0'],
                    ['label' => 'Seguimientos', 'value' => '0'],
                ],
            ],

            'inventario' => [
                'name'           => 'Inventario',
                'icon'           => 'inventario',
                'requires_pro'   => true,
                'default_state'  => 'active',
                'default_access' => true,
                'headline'       => 'Inventario + Ventas + Facturación + IA',
                'summary'        => 'Productos, existencias, movimientos y base operativa para ventas y facturación.',
                'chips'          => ['Productos', 'Stock', 'Movimientos', 'Ventas', 'Facturación', 'IA'],
                'kpis'           => [
                    ['label' => 'Productos', 'value' => '0'],
                    ['label' => 'Stock', 'value' => '0'],
                    ['label' => 'Movimientos', 'value' => '0'],
                    ['label' => 'Alertas', 'value' => '0'],
                ],
            ],

            'ventas' => [
                'name'           => 'Ventas',
                'icon'           => 'ventas',
                'requires_pro'   => true,
                'default_state'  => 'active',
                'default_access' => true,
                'headline'       => 'Ventas + Inventario + Facturación + Autofactura',
                'summary'        => 'Registro de ventas, tickets, códigos de venta y base para autofacturación.',
                'chips'          => ['Tickets', 'Código de venta', 'Monto', 'Facturación', 'Autofactura', 'IA'],
                'kpis'           => [
                    ['label' => 'Ventas', 'value' => '0'],
                    ['label' => 'Tickets', 'value' => '0'],
                    ['label' => 'Facturables', 'value' => '0'],
                    ['label' => 'Monto', 'value' => '$0'],
                ],
            ],

            'reportes' => [
                'name'           => 'Reportes',
                'icon'           => 'reportes',
                'default_state'  => 'active',
                'default_access' => true,
                'headline'       => 'Reportes + KPIs + IA + Visión global',
                'summary'        => 'Indicadores, métricas operativas, análisis y tablero general de la cuenta.',
                'chips'          => ['KPIs', 'Dashboards', 'Comparativos', 'Alertas', 'Cruces', 'IA'],
                'kpis'           => [
                    ['label' => 'Indicadores', 'value' => '0'],
                    ['label' => 'Alertas', 'value' => '0'],
                    ['label' => 'Cruces', 'value' => '0'],
                    ['label' => 'Módulos', 'value' => '7'],
                ],
            ],

            'recursos_humanos' => [
                'name'           => 'Recursos Humanos',
                'icon'           => 'recursos_humanos',
                'requires_pro'   => true,
                'default_state'  => 'active',
                'default_access' => true,
                'headline'       => 'RH + Nómina + CFDI Nómina + IA',
                'summary'        => 'Empleados, incidencias, nómina y CFDI de nómina dentro del mismo módulo.',
                'chips'          => ['Empleados', 'Incidencias', 'Nómina', 'CFDI nómina', 'Finiquitos', 'IA'],
                'kpis'           => [
                    ['label' => 'Empleados', 'value' => '0'],
                    ['label' => 'Nóminas', 'value' => '0'],
                    ['label' => 'CFDI nómina', 'value' => '0'],
                    ['label' => 'Hits', 'value' => '0'],
                ],
            ],

            'timbres_hits' => [
                'name'           => 'Timbres / Hits',
                'icon'           => 'timbres_hits',
                'default_state'  => 'active',
                'default_access' => true,
                'headline'       => 'Timbres / Hits + Facturotopia + IA',
                'summary'        => 'Compra, saldo, consumo y configuración de timbrado con Facturotopia.',
                'chips'          => ['Saldo', 'Consumo', 'Compra', 'Cotización', 'Facturotopia', 'IA'],
                'kpis'           => [
                    ['label' => 'Saldo', 'value' => '0'],
                    ['label' => 'Consumo', 'value' => '0'],
                    ['label' => 'Compras', 'value' => '0'],
                    ['label' => 'Alertas', 'value' => '0'],
                ],
            ],
        ];
    }

    private function resolveAdminModulesState(object $cuentaCliente): array
    {
        $adminAccountId = (string) ($cuentaCliente->admin_account_id ?? '');

        if ($adminAccountId === '') {
            return [];
        }

        $conn = (string) (env('P360_BILLING_SOT_CONN') ?: 'mysql_admin');
        $table = (string) (env('P360_BILLING_SOT_TABLE') ?: 'accounts');
        $metaCol = (string) (env('P360_BILLING_META_COL') ?: 'meta');

        try {
            if (!Schema::connection($conn)->hasTable($table)) {
                return [];
            }

            if (!Schema::connection($conn)->hasColumn($table, $metaCol)) {
                return [];
            }

            $account = DB::connection($conn)
                ->table($table)
                ->select(['id', $metaCol])
                ->where('id', $adminAccountId)
                ->first();

            if (!$account) {
                return [];
            }

            $metaRaw = $account->{$metaCol} ?? null;
            $meta = [];

            if (is_array($metaRaw)) {
                $meta = $metaRaw;
            } elseif (is_string($metaRaw) && trim($metaRaw) !== '') {
                $decoded = json_decode($metaRaw, true);
                $meta = is_array($decoded) ? $decoded : [];
            }

            $state = data_get($meta, 'modules_state', []);
            if (!is_array($state)) {
                $state = data_get($meta, 'modules', []);
            }

            if (!is_array($state)) {
                return [];
            }

            $normalized = [];
            foreach ($state as $key => $value) {
                $normalized[(string) $key] = $this->normalizeModuleStateValue($value);
            }

            return $normalized;
        } catch (\Throwable) {
            return [];
        }
    }

    private function normalizeModuleStateValue(mixed $value): string
    {
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['active', 'inactive', 'hidden', 'blocked'], true)) {
                return $v;
            }

            if (in_array($v, ['1', 'true', 'yes', 'on', 'enabled', 'visible'], true)) {
                return 'active';
            }

            if (in_array($v, ['0', 'false', 'no', 'off', 'disabled'], true)) {
                return 'inactive';
            }
        }

        if (is_bool($value)) {
            return $value ? 'active' : 'inactive';
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1 ? 'active' : 'inactive';
        }

        if (is_array($value)) {
            if ((bool) ($value['hidden'] ?? false) === true) {
                return 'hidden';
            }

            if (array_key_exists('status', $value) && is_string($value['status'])) {
                return $this->normalizeModuleStateValue((string) $value['status']);
            }

            if (array_key_exists('enabled', $value)) {
                return (bool) $value['enabled'] ? 'active' : 'inactive';
            }

            if (array_key_exists('visible', $value)) {
                return (bool) $value['visible'] ? 'active' : 'hidden';
            }

            if (array_key_exists('access', $value)) {
                return (bool) $value['access'] ? 'active' : 'blocked';
            }
        }

        return 'active';
    }

        private function buildModules(
        bool $isProPlan,
        string $estadoCuenta,
        bool $isBlocked,
        array $adminModulesState = []
    ): array {
        $catalog = $this->mobileModulesCatalog();

        $canUseAccount = !$isBlocked
            && !in_array($estadoCuenta, ['suspendida', 'bloqueada', 'bloqueada_pago'], true);

        $modules = [];

        foreach ($catalog as $key => $cfg) {
            $baseState = (string) ($cfg['default_state'] ?? 'active');
            $baseAccess = (bool) ($cfg['default_access'] ?? true);

            if (($cfg['requires_account_ok'] ?? false) === true && !$canUseAccount) {
                $baseState = 'blocked';
                $baseAccess = false;
            }

            if (($cfg['requires_pro'] ?? false) === true && !$isProPlan) {
                $baseState = 'inactive';
                $baseAccess = false;
            }

            $adminState = strtolower(trim((string) ($adminModulesState[$key] ?? '')));

            if ($adminState !== '') {
                switch ($adminState) {
                    case 'active':
                        $baseState = 'active';
                        $baseAccess = true;
                        break;

                    case 'inactive':
                        $baseState = 'inactive';
                        $baseAccess = false;
                        break;

                    case 'hidden':
                        $baseState = 'hidden';
                        $baseAccess = false;
                        break;

                    case 'blocked':
                        $baseState = 'blocked';
                        $baseAccess = false;
                        break;
                }
            }

            $modules[] = [
                'key'         => $key,
                'name'        => (string) ($cfg['name'] ?? $key),
                'icon'        => (string) ($cfg['icon'] ?? 'hub'),
                'state'       => $baseState,
                'access'      => $baseAccess,
                'visible'     => $baseState !== 'hidden',
                'enabled'     => $baseAccess && $baseState === 'active',
                'headline'    => (string) ($cfg['headline'] ?? ($cfg['name'] ?? $key)),
                'summary'     => (string) ($cfg['summary'] ?? ''),
                'chips'       => array_values((array) ($cfg['chips'] ?? [])),
                'kpis'        => array_values((array) ($cfg['kpis'] ?? [])),
            ];
        }

        return $modules;
    }

    private function isCotizacionLikeDownload(SatDownload $download): bool
    {
        $tipo = strtolower(trim((string) ($download->tipo ?? '')));
        $status = $download->statusNormalized();
        $meta = is_array($download->meta ?? null) ? $download->meta : [];

        $modeMeta = strtolower(trim((string) data_get($meta, 'mode', '')));
        $folioMeta = trim((string) (
            data_get($meta, 'folio')
            ?: data_get($meta, 'quote_no')
            ?: ''
        ));

        $isRequest = (bool) data_get($download, 'is_request', false);
        $esSolicitud = (bool) data_get($download, 'es_solicitud', false);
        $isDraft = (bool) data_get($download, 'is_draft', false);

        if ($isRequest || $esSolicitud || $isDraft) {
            return true;
        }

        if (in_array($tipo, [
            'solicitud',
            'request',
            'peticion',
            'cotizacion',
            'cotización',
            'quote',
            'quick_quote',
            'simulacion',
            'simulación',
            'simulada',
        ], true)) {
            return true;
        }

        if (in_array($modeMeta, ['quote', 'quote_draft', 'quick', 'quick_quote', 'simulation', 'simulada'], true)) {
            return true;
        }

        if ($folioMeta !== '') {
            return true;
        }

        if (
            in_array($status, ['paid', 'ready', 'done', 'processing', 'requested', 'pending'], true)
            && (
                (float) ($download->total ?? 0) > 0
                || (float) ($download->subtotal ?? 0) > 0
                || (float) ($download->costo ?? 0) > 0
            )
        ) {
            return true;
        }

        return false;
    }

    private function transformCotizacionRow(SatDownload $download, Collection $credList): array
    {
        $meta = $download->meta ?? [];

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($meta)) {
            $meta = [];
        }

        $rfc = strtoupper(trim((string) ($download->rfc ?: data_get($meta, 'rfc', ''))));

        $razonSocial = trim((string) data_get($meta, 'razon_social', ''));
        if ($razonSocial === '') {
            $razonSocial = trim((string) data_get($meta, 'empresa', ''));
        }

        if ($razonSocial === '' && $rfc !== '') {
            $cred = $credList->first(function ($item) use ($rfc) {
                return strtoupper(trim((string) ($item->rfc ?? ''))) === $rfc;
            });

            if ($cred) {
                $razonSocial = trim((string) ($cred->razon_social ?? ''));
            }
        }

        $folio = trim((string) data_get($meta, 'folio', ''));
        if ($folio === '') {
            $folio = trim((string) data_get($meta, 'quote_no', ''));
        }

        if ($folio === '') {
            $rawId = (string) ($download->id ?? '');
            $folio = 'COT-' . str_pad(
                substr((string) preg_replace('/[^A-Za-z0-9]/', '', $rawId), -6),
                6,
                '0',
                STR_PAD_LEFT
            );
        }

        $concepto = trim((string) data_get($meta, 'concepto', ''));
        if ($concepto === '') {
            $concepto = trim((string) data_get($meta, 'note', 'Cotización SAT'));
        }

        $statusUi = $this->normalizeCotizacionStatusForUi($download);
        $progress = $this->resolveCotizacionProgress($download, $statusUi);

        $importe = null;
        foreach ([
            $download->total ?? null,
            data_get($meta, 'total'),
            $download->subtotal ?? null,
            $download->costo ?? null,
        ] as $candidate) {
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate)) {
                $importe = (float) $candidate;
                break;
            }
        }

        $updatedAt = $download->updated_at ?? $download->created_at ?? null;
        $updatedAtIso = null;

        if ($updatedAt instanceof \Carbon\CarbonInterface) {
            $updatedAtIso = $updatedAt->toIso8601String();
        } elseif (!empty($updatedAt)) {
            try {
                $updatedAtIso = \Illuminate\Support\Carbon::parse($updatedAt)->toIso8601String();
            } catch (\Throwable $e) {
                $updatedAtIso = null;
            }
        }

        return [
            'id'               => (string) ($download->id ?? ''),
            'folio'            => $folio,
            'rfc'              => $rfc,
            'razon_social'     => $razonSocial,
            'concepto'         => $concepto,
            'status'           => $statusUi,
            'importe_estimado' => $importe,
            'progress'         => $progress,
            'updated_at'       => $updatedAtIso,
            'meta'             => $meta,
        ];
    }

    private function normalizeCotizacionStatusForUi(SatDownload $download): string
    {
        $status = $download->statusNormalized();
        $meta = is_array($download->meta ?? null) ? $download->meta : [];

        $statusUiMeta = strtolower(trim((string) data_get($meta, 'status_ui', '')));
        $customerAction = strtolower(trim((string) data_get($meta, 'customer_action', '')));
        $transferReviewStatus = strtolower(trim((string) data_get($meta, 'transfer_review.review_status', '')));

        if ($transferReviewStatus === 'pending') {
            return 'en_proceso';
        }

        if (
            $status === 'paid'
            && in_array($customerAction, ['download_in_progress', 'processing_download', 'download_started'], true)
        ) {
            return 'en_descarga';
        }

        if (in_array($statusUiMeta, [
            'borrador',
            'en_proceso',
            'cotizada',
            'pagada',
            'en_descarga',
            'completada',
            'cancelada',
        ], true)) {
            return $statusUiMeta;
        }

        if (in_array($status, ['downloaded', 'done'], true)) {
            return 'completada';
        }

        if ($status === 'paid') {
            return 'pagada';
        }

        return match ($status) {
            'pending', 'created'      => 'borrador',
            'requested', 'processing' => 'en_proceso',
            'ready'                   => 'cotizada',
            'canceled', 'expired', 'error' => 'cancelada',
            default                   => 'borrador',
        };
    }

    private function resolveCotizacionProgress(SatDownload $download, string $statusUi): int
    {
        $meta = is_array($download->meta ?? null) ? $download->meta : [];

        $raw = data_get($meta, 'progress', data_get($meta, 'avance', data_get($meta, 'porcentaje')));
        if (is_numeric($raw)) {
            return max(0, min(100, (int) $raw));
        }

        return match ($statusUi) {
            'borrador'   => 10,
            'en_proceso' => 35,
            'cotizada'   => 65,
            'pagada'     => 82,
            'en_descarga'=> 92,
            'completada' => 100,
            'cancelada'  => 0,
            default      => 0,
        };
    }

    private function buildSatV2LikeItems(string $cuentaId, string $usuarioId, string $selectedRfc): Collection
    {
        $metadataUploads = SatUserMetadataUpload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->when($selectedRfc !== '', fn ($q) => $q->where('rfc_owner', $selectedRfc))
            ->latest('id')
            ->limit(100)
            ->get();

        $xmlUploads = SatUserXmlUpload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->when($selectedRfc !== '', fn ($q) => $q->where('rfc_owner', $selectedRfc))
            ->latest('id')
            ->limit(100)
            ->get();

        $reportUploads = SatUserReportUpload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->when($selectedRfc !== '', fn ($q) => $q->where('rfc_owner', $selectedRfc))
            ->latest('id')
            ->limit(100)
            ->get();

        return collect()
            ->merge($metadataUploads->map(fn ($upload) => [
                'kind'          => 'metadata',
                'id'            => (int) $upload->id,
                'rfc_owner'     => (string) $upload->rfc_owner,
                'direction'     => (string) ($upload->direction_detected ?? ''),
                'source_type'   => (string) ($upload->source_type ?? 'metadata'),
                'original_name' => (string) ($upload->original_name ?? $upload->stored_name ?? ('metadata_' . $upload->id)),
                'stored_name'   => (string) ($upload->stored_name ?? ''),
                'mime'          => (string) ($upload->mime ?? 'application/octet-stream'),
                'bytes'         => (int) ($upload->bytes ?? 0),
                'rows_count'    => (int) ($upload->rows_count ?? 0),
                'files_count'   => 0,
                'status'        => (string) ($upload->status ?? ''),
                'created_at'    => $upload->created_at,
            ]))
            ->merge($xmlUploads->map(fn ($upload) => [
                'kind'          => 'xml',
                'id'            => (int) $upload->id,
                'rfc_owner'     => (string) $upload->rfc_owner,
                'direction'     => (string) ($upload->direction_detected ?? ''),
                'source_type'   => (string) ($upload->source_type ?? 'xml'),
                'original_name' => (string) ($upload->original_name ?? $upload->stored_name ?? ('xml_' . $upload->id)),
                'stored_name'   => (string) ($upload->stored_name ?? ''),
                'mime'          => (string) ($upload->mime ?? 'application/octet-stream'),
                'bytes'         => (int) ($upload->bytes ?? 0),
                'rows_count'    => 0,
                'files_count'   => (int) ($upload->files_count ?? 0),
                'status'        => (string) ($upload->status ?? ''),
                'created_at'    => $upload->created_at,
            ]))
            ->merge($reportUploads->map(fn ($upload) => [
                'kind'          => 'report',
                'id'            => (int) $upload->id,
                'rfc_owner'     => (string) $upload->rfc_owner,
                'direction'     => (string) data_get($upload->meta, 'report_direction', ''),
                'source_type'   => (string) ($upload->report_type ?? 'report'),
                'original_name' => (string) ($upload->original_name ?? $upload->stored_name ?? ('reporte_' . $upload->id)),
                'stored_name'   => (string) ($upload->stored_name ?? ''),
                'mime'          => (string) ($upload->mime ?? 'application/octet-stream'),
                'bytes'         => (int) ($upload->bytes ?? 0),
                'rows_count'    => (int) ($upload->rows_count ?? 0),
                'files_count'   => 0,
                'status'        => (string) ($upload->status ?? ''),
                'created_at'    => $upload->created_at,
            ]))
            ->sortByDesc(fn (array $row) => optional($row['created_at'] ?? null)?->timestamp ?? 0)
            ->values();
    }

    private function buildUnifiedDownloadItemsForMobile(
        string $cuentaId,
        string $usuarioId,
        string $selectedRfc,
        Collection $v2Items
    ): Collection {
        $items = collect();

        $items = $items->merge(
            $v2Items->map(function (array $item) use ($selectedRfc) {
                $bytes = (int) ($item['bytes'] ?? 0);

                return [
                    'origin'        => 'boveda_v2',
                    'origin_label'  => 'Bóveda v2',
                    'kind'          => (string) ($item['kind'] ?? 'archivo'),
                    'id'            => (string) ($item['id'] ?? ''),
                    'rfc_owner'     => strtoupper((string) ($item['rfc_owner'] ?? $selectedRfc)),
                    'direction'     => (string) ($item['direction'] ?? ''),
                    'original_name' => (string) ($item['original_name'] ?? 'Archivo'),
                    'mime'          => (string) ($item['mime'] ?? 'application/octet-stream'),
                    'bytes'         => $bytes,
                    'bytes_human'   => $this->formatBytesHuman($bytes),
                    'detail'        => $this->resolveV2DetailLabel($item),
                    'status'        => (string) ($item['status'] ?? ''),
                    'created_at'    => optional($item['created_at'] ?? null)?->toIso8601String(),
                ];
            })
        );

        if (Schema::connection('mysql_clientes')->hasTable('sat_downloads')) {
            $satDownloadsQuery = DB::connection('mysql_clientes')
                ->table('sat_downloads')
                ->where('cuenta_id', $cuentaId)
                ->whereNotNull('zip_path')
                ->where('zip_path', '<>', '')
                ->orderByDesc('created_at');

            if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'usuario_id')) {
                $satDownloadsQuery->where('usuario_id', $usuarioId);
            }

            if ($selectedRfc !== '' && Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'rfc')) {
                $satDownloadsQuery->where('rfc', $selectedRfc);
            }

            $satDownloads = $satDownloadsQuery->limit(100)->get();

            $items = $items->merge(
                collect($satDownloads)->map(function ($row) {
                    $bytes = isset($row->bytes) && is_numeric($row->bytes) ? (int) $row->bytes : 0;

                    $filename = trim((string) ($row->zip_filename ?? $row->filename ?? ''));
                    if ($filename === '') {
                        $filename = basename((string) ($row->zip_path ?? 'descarga.zip'));
                    }

                    return [
                        'origin'        => 'centro_sat',
                        'origin_label'  => 'Centro SAT',
                        'kind'          => 'zip',
                        'id'            => (string) ($row->id ?? ''),
                        'rfc_owner'     => strtoupper((string) ($row->rfc ?? '')),
                        'direction'     => strtolower((string) ($row->tipo ?? '')),
                        'original_name' => $filename,
                        'mime'          => 'application/zip',
                        'bytes'         => $bytes,
                        'bytes_human'   => $this->formatBytesHuman($bytes),
                        'detail'        => 'Paquete SAT',
                        'status'        => (string) ($row->status ?? 'disponible'),
                        'created_at'    => !empty($row->created_at)
                            ? Carbon::parse($row->created_at)->toIso8601String()
                            : null,
                    ];
                })
            );
        }

        return $items
            ->filter(fn (array $item) => trim((string) ($item['original_name'] ?? '')) !== '')
            ->sortByDesc(function (array $item) {
                if (empty($item['created_at'])) {
                    return 0;
                }

                try {
                    return Carbon::parse((string) $item['created_at'])->timestamp;
                } catch (\Throwable) {
                    return 0;
                }
            })
            ->values();
    }

    private function buildUnifiedStorageBreakdownForMobile(string $cuentaId, Collection $unifiedItems): array
    {
        $usedBytes = (int) $unifiedItems->sum(fn (array $item) => (int) ($item['bytes'] ?? 0));
        $quotaBytes = 0;

        if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
            $cuenta = DB::connection('mysql_clientes')
                ->table('cuentas_cliente')
                ->where('id', $cuentaId)
                ->first();

            if ($cuenta) {
                if (property_exists($cuenta, 'vault_quota_bytes') && is_numeric($cuenta->vault_quota_bytes)) {
                    $quotaBytes = (int) $cuenta->vault_quota_bytes;
                } elseif (property_exists($cuenta, 'espacio_asignado_mb') && is_numeric($cuenta->espacio_asignado_mb)) {
                    $quotaBytes = (int) round(((float) $cuenta->espacio_asignado_mb) * 1024 * 1024);
                }
            }
        }

        if ($quotaBytes < $usedBytes) {
            $quotaBytes = $usedBytes;
        }

        $availableBytes = max(0, $quotaBytes - $usedBytes);

        $usedGb = $usedBytes / 1073741824;
        $availableGb = $availableBytes / 1073741824;
        $quotaGb = $quotaBytes / 1073741824;

        $usedPct = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 2) : 0.0;
        $availablePct = $quotaBytes > 0 ? round(($availableBytes / $quotaBytes) * 100, 2) : 0.0;

        return [
            'used_bytes'      => $usedBytes,
            'available_bytes' => $availableBytes,
            'quota_bytes'     => $quotaBytes,
            'used_gb'         => round($usedGb, 2),
            'available_gb'    => round($availableGb, 2),
            'quota_gb'        => round($quotaGb, 2),
            'used_pct'        => $usedPct,
            'available_pct'   => $availablePct,
            'chart'           => [
                'series' => [round($usedGb, 2), round($availableGb, 2)],
                'labels' => ['Usado', 'Disponible'],
            ],
        ];
    }

    private function resolveV2DetailLabel(array $item): string
    {
        $kind = strtolower((string) ($item['kind'] ?? ''));

        return match ($kind) {
            'metadata' => number_format((int) ($item['rows_count'] ?? 0)) . ' registros',
            'xml'      => number_format((int) ($item['files_count'] ?? 0)) . ' archivo(s)',
            'report'   => number_format((int) ($item['rows_count'] ?? 0)) . ' filas',
            default    => 'Archivo',
        };
    }

    private function formatBytesHuman(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 2) . ' ' . $units[$power];
    }
}