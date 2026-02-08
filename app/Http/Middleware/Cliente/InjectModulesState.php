<?php declare(strict_types=1);

namespace App\Http\Middleware\Cliente;

use App\Support\ClientSessionConfig;
use App\Services\Clientes\ModuleVisibilityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

final class InjectModulesState
{
    public function __construct(
        private readonly ModuleVisibilityService $modules
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $adminAccountId = (int) (ClientSessionConfig::resolveAdminAccountId($request) ?: 0);

        // Default seguro: si no se resuelve cuenta, no bloqueamos UI; se queda vacío.
        $modulesState = [];
        $source = 'default_no_account';

        if ($adminAccountId > 0) {
            try {
                // Fuente única: servicio (ya te está dando lo correcto en tinker)
                $ms = $this->modules->getModulesForAdminAccountId($adminAccountId);

                $modulesState = $this->normalizeStateMap(is_array($ms) ? $ms : []);
                $source = 'ModuleVisibilityService.getModulesForAdminAccountId';

            } catch (\Throwable $e) {
                Log::warning('ClientModules.inject_error', [
                    'admin_account_id' => $adminAccountId,
                    'err' => $e->getMessage(),
                ]);

                $modulesState = [];
                $source = 'error';
            }
        } else {
            Log::warning('ClientModules.injected_no_account', [
                'path' => $request->path(),
            ]);
        }

        // Derivados para UI (lo que tu componente espera)
        $modulesVisible = [];
        $modulesAccess  = [];

        foreach ($modulesState as $k => $st) {
            $modulesVisible[$k] = ($st !== 'hidden');
            $modulesAccess[$k]  = ($st === 'active'); // solo active es accesible
        }

        // 1) SESSION (tu sidebar cliente LEE session('p360.*'))
        try {
            $request->session()->put('p360.modules_state', $modulesState);
            $request->session()->put('p360.modules_visible', $modulesVisible);
            $request->session()->put('p360.modules_access', $modulesAccess);

            $request->session()->put('p360.admin_account_id', $adminAccountId);
            $request->session()->put('p360.modules_source', $source);
        } catch (\Throwable $e) {
            Log::warning('ClientModules.session_put_failed', [
                'admin_account_id' => $adminAccountId,
                'err' => $e->getMessage(),
            ]);
        }

        // 2) VIEW (compat: por si alguna vista vieja usa estas variables)
        View::share('p360_admin_account_id', $adminAccountId);

        View::share('p360_modules_state', $modulesState);
        View::share('p360_modules_visible', $modulesVisible);
        View::share('p360_modules_access', $modulesAccess);

        // Alias por si ya habías usado este nombre en blades:
        View::share('p360Modules', $modulesState);

        Log::info('ClientModules.injected', [
            'admin_account_id' => $adminAccountId,
            'source' => $source,
            'modules_state_sample' => array_slice($modulesState, 0, 8, true),
        ]);

        return $next($request);
    }

    /**
     * @param array<string,mixed> $ms
     * @return array<string,string>
     */
    private function normalizeStateMap(array $ms): array
    {
        $allowed = ['active','inactive','hidden','blocked'];
        $out = [];

        foreach ($ms as $k => $v) {
            $k = (string)$k;
            if ($k === '') continue;

            $s = strtolower(trim((string)$v));
            $out[$k] = in_array($s, $allowed, true) ? $s : 'active';
        }

        return $out;
    }
}
