<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Client\ModuleVisibilityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class ShareClientModules
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Ajusta el guard si usas otro en cliente
            $user = Auth::guard('web')->user();

            // Intento de resolver cuenta_cliente.id desde usuario (ajusta si tu modelo es distinto)
            $cuentaId = null;

            // 1) si el user tiene cuenta_id
            if (is_object($user) && isset($user->cuenta_id)) {
                $cuentaId = (string) $user->cuenta_id;
            }

            // 2) fallback: si tienes relación por admin_account_id en cuentas_cliente
            //    y el user tiene admin_account_id
            if (!$cuentaId && is_object($user) && isset($user->admin_account_id)) {
                $admId = (int) $user->admin_account_id;
                if ($admId > 0 && Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                    $cuentaId = (string) DB::connection('mysql_clientes')
                        ->table('cuentas_cliente')
                        ->where('admin_account_id', $admId)
                        ->value('id');
                }
            }

            $mods = [];
            if ($cuentaId) {
                $svc  = app(ModuleVisibilityService::class);
                $mods = $svc->getModulesForClientAccountId($cuentaId);
            }

            // Se comparte a todas las vistas
            View::share('p360Modules', $mods);

        } catch (\Throwable $e) {
            Log::warning('[MODULES] ShareClientModules error', ['err' => $e->getMessage()]);
            View::share('p360Modules', []);
        }

        return $next($request);
    }
}
