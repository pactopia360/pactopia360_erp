<?php

namespace App\Http\Controllers\Api\Mobile\Sat;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // =========================================
        // Cuenta
        // =========================================
        $account = DB::connection('mysql_clientes')
            ->table('cuentas_cliente')
            ->where('id', $user->cuenta_id)
            ->first();

        if (!$account) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró la cuenta del usuario.',
            ], 404);
        }

        // =========================================
        // Módulos desde sesión (misma lógica sidebar)
        // =========================================
        $modulesState   = session('p360.modules_state', []);
        $modulesAccess  = session('p360.modules_access', []);
        $modulesVisible = session('p360.modules_visible', []);

        // =========================================
        // Catálogo base (alineado al sidebar)
        // =========================================
        $catalog = [
            'facturacion'    => ['name' => 'Facturación', 'icon' => 'receipt'],
            'sat_descargas'  => ['name' => 'Portal SAT', 'icon' => 'cloud'],
            'boveda_fiscal'  => ['name' => 'Centro SAT', 'icon' => 'storage'],
            'crm'            => ['name' => 'CRM', 'icon' => 'people'],
            'nomina'         => ['name' => 'Nómina', 'icon' => 'payments'],
            'pos'            => ['name' => 'Punto de venta', 'icon' => 'point_of_sale'],
            'inventario'     => ['name' => 'Inventario', 'icon' => 'inventory'],
            'reportes'       => ['name' => 'Reportes', 'icon' => 'bar_chart'],
            'integraciones'  => ['name' => 'Integraciones', 'icon' => 'hub'],
            'alertas'        => ['name' => 'Alertas', 'icon' => 'notifications'],
            'chat'           => ['name' => 'Chat', 'icon' => 'chat'],
            'marketplace'    => ['name' => 'Marketplace', 'icon' => 'store'],
        ];

        $modules = [];

        foreach ($catalog as $key => $meta) {
            $state   = $modulesState[$key] ?? 'active';
            $visible = $modulesVisible[$key] ?? true;
            $access  = $modulesAccess[$key] ?? true;

            if (!$visible) {
                continue;
            }

            $modules[] = [
                'key'       => $key,
                'name'      => $meta['name'],
                'icon'      => $meta['icon'],
                'state'     => $state,
                'access'    => (bool) $access,
                'is_active' => $state === 'active',
            ];
        }

        // =========================================
        // Acciones rápidas
        // =========================================
        $quickActions = [
            ['key' => 'pay',      'label' => 'Pagar',             'icon' => 'credit_card'],
            ['key' => 'account',  'label' => 'Estado de cuenta',  'icon' => 'account_balance'],
            ['key' => 'invoices', 'label' => 'Facturas',          'icon' => 'description'],
            ['key' => 'sat',      'label' => 'SAT',               'icon' => 'cloud'],
            ['key' => 'profile',  'label' => 'Mi cuenta',         'icon' => 'person'],
        ];

        // =========================================
        // Fecha de próximo pago segura
        // =========================================
        $nextPayment = null;

        if (!empty($account->next_invoice_date)) {
            try {
                $nextPayment = \Carbon\Carbon::parse($account->next_invoice_date)->format('Y-m-d');
            } catch (\Throwable $e) {
                $nextPayment = (string) $account->next_invoice_date;
            }
        }

        // =========================================
        // HERO
        // =========================================
        $hero = [
            'title'        => $account->nombre_comercial ?? 'PACTOPIA360',
            'subtitle'     => $account->razon_social ?? '',
            'plan'         => strtoupper((string) ($account->plan_actual ?? $account->plan ?? 'FREE')),
            'status'       => $account->estado_cuenta ?? 'activa',
            'next_payment' => $nextPayment,
        ];

        // =========================================
        // Salud de cuenta
        // =========================================
        $isBlocked = (int) ($account->is_blocked ?? 0) === 1;

        $health = [
            'status'  => $isBlocked ? 'blocked' : 'ok',
            'message' => $isBlocked
                ? 'Cuenta bloqueada por pago'
                : 'Cuenta operando correctamente',
        ];

        return response()->json([
            'ok' => true,
            'data' => [
                'hero'          => $hero,
                'health'        => $health,
                'quick_actions' => $quickActions,
                'modules'       => $modules,
            ],
        ]);
    }
}