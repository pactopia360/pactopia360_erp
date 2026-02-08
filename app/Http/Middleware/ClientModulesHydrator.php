<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Cliente\CuentaCliente;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ClientModulesHydrator
{
    private const MOD_ACTIVE   = 'active';
    private const MOD_INACTIVE = 'inactive';
    private const MOD_HIDDEN   = 'hidden';
    private const MOD_BLOCKED  = 'blocked';

    public function handle(Request $request, Closure $next)
    {
        // =========================================================
        // BYPASS ADMIN (misma lógica por seguridad)
        // =========================================================
        try {
            $routeName = optional($request->route())->getName();

            if ($request->is('admin', 'admin/*')) return $next($request);
            if (is_string($routeName) && $routeName !== '' && str_starts_with($routeName, 'admin.')) return $next($request);
            if (Auth::guard('admin')->check()) return $next($request);
        } catch (\Throwable $e) {
            return $next($request);
        }

        // =========================================================
        // Desde aquí YA existe session store (porque este middleware
        // debe correr DESPUÉS de StartSession).
        // =========================================================
        try {
            $user = Auth::guard('web')->user();

            if (!$user) {
                $this->clearModulesSession($request);
                return $next($request);
            }

            // Asegurar relación cuenta para admin_account_id
            try { if (method_exists($user, 'loadMissing')) $user->loadMissing('cuenta'); } catch (\Throwable $e) {}

            $accountId = $this->resolveAdminAccountId($user);

            // Espejo legacy (account_id int) + cuenta_id UUID (si aplica)
            $this->mirrorLegacyAccountKeys($request, $accountId, $user);

            // Gate refresh
            $lastAccountId = (int) $request->session()->get('p360.account_id', 0);

            $ttlSec      = (int) (config('p360.modules_state_ttl', 60));
            $lastHydTs   = (int) $request->session()->get('p360.modules_synced_at', 0);
            $nowTs       = time();
            $ttlExpired  = ($lastHydTs <= 0) || (($nowTs - $lastHydTs) >= $ttlSec);

            $adminVersion   = $this->readAdminModulesUpdatedAt($accountId);
            $lastVersion    = (string) $request->session()->get('p360.modules_version', '');
            $versionChanged = ($adminVersion !== null && $adminVersion !== '' && $adminVersion !== $lastVersion);

            $mustRefresh = ($lastAccountId !== $accountId) || $ttlExpired || $versionChanged;

            if ($mustRefresh) {
                $bundle = $this->loadModulesBundleForAccount($accountId);

                $request->session()->put('p360.account_id', $accountId);
                $request->session()->put('p360.modules_state',   $bundle['state']);
                $request->session()->put('p360.modules_access',  $bundle['access']);
                $request->session()->put('p360.modules_visible', $bundle['visible']);

                // Compat legacy: p360.modules bool = visible
                $legacyModules = [];
                foreach ($bundle['visible'] as $k => $isVisible) {
                    $legacyModules[$k] = (bool) $isVisible;
                }
                $request->session()->put('p360.modules', $legacyModules);

                $request->session()->put('p360.modules_synced_at', $nowTs);
                $request->session()->put('p360.modules_version', (string)($adminVersion ?? ''));

                if (app()->environment(['local', 'development', 'testing'])) {
                    Log::debug('ClientModulesHydrator.bundle', [
                        'account_id'      => $accountId,
                        'ttlExpired'      => $ttlExpired,
                        'versionChanged'  => $versionChanged,
                        'adminVersion'    => $adminVersion,
                        'lastVersion'     => $lastVersion,
                        'state_sample'    => array_slice($bundle['state'], 0, 10, true),
                        'visible_sample'  => array_slice($bundle['visible'], 0, 10, true),
                        'access_sample'   => array_slice($bundle['access'], 0, 10, true),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ClientModulesHydrator: no se pudo cargar módulos', [
                'err'   => $e->getMessage(),
                'path'  => $request->path(),
                'route' => optional($request->route())->getName(),
            ]);
        }

        return $next($request);
    }

    private function clearModulesSession(Request $request): void
    {
        $request->session()->forget([
            'p360.account_id',
            'p360.modules_state',
            'p360.modules_access',
            'p360.modules_visible',
            'p360.modules',
            'p360.modules_synced_at',
            'p360.modules_version',

            'account_id',
            'client.account_id',
            'client_account_id',

            'cuenta_id',
            'client.cuenta_id',
            'client_cuenta_id',
        ]);
    }

    private function mirrorLegacyAccountKeys(Request $request, int $accountId, object $user): void
    {
        // admin account id (int)
        if ($accountId > 0) {
            $request->session()->put('account_id', $accountId);
            $request->session()->put('client.account_id', $accountId);
            $request->session()->put('client_account_id', $accountId);
        } else {
            $request->session()->forget(['account_id','client.account_id','client_account_id']);
        }

        // cuenta UUID (string) desde usuario_cuenta.cuenta_id
        $cuentaId = null;
        try {
            if (isset($user->cuenta_id) && is_string($user->cuenta_id) && trim($user->cuenta_id) !== '') {
                $cuentaId = trim((string) $user->cuenta_id);
            }
        } catch (\Throwable $e) {}

        if ($cuentaId) {
            $request->session()->put('cuenta_id', $cuentaId);
            $request->session()->put('client.cuenta_id', $cuentaId);
            $request->session()->put('client_cuenta_id', $cuentaId);
        } else {
            $request->session()->forget(['cuenta_id','client.cuenta_id','client_cuenta_id']);
        }

        $request->session()->put('p360.account_id', (int) $accountId);
    }

    private function resolveAdminAccountId(object $user): int
    {
        // 1) relación cuenta->admin_account_id
        try {
            if (isset($user->cuenta) && is_object($user->cuenta)) {
                $adm = $user->cuenta->admin_account_id ?? null;
                if (is_numeric($adm) && (int) $adm > 0) return (int) $adm;
            }
        } catch (\Throwable $e) {}

        // 2) por cuentas_cliente.id (uuid) -> admin_account_id
        try {
            $cuentaUuid = null;
            if (isset($user->cuenta_id) && is_string($user->cuenta_id) && trim($user->cuenta_id) !== '') {
                $cuentaUuid = trim((string) $user->cuenta_id);
            }

            if ($cuentaUuid) {
                $adm = (int) (CuentaCliente::on('mysql_clientes')->where('id', $cuentaUuid)->value('admin_account_id') ?? 0);
                if ($adm > 0) return $adm;
            }
        } catch (\Throwable $e) {}

        // 3) prop directa
        if (isset($user->admin_account_id) && is_numeric($user->admin_account_id) && (int)$user->admin_account_id > 0) {
            return (int)$user->admin_account_id;
        }

        return 0;
    }

    private function loadModulesBundleForAccount(int $accountId): array
    {
        $defaultsState = $this->catalogDefaultsState();

        if ($accountId <= 0) {
            return $this->bundleFromState($defaultsState);
        }

        $cacheKey = 'p360:mods:acct:' . $accountId;

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($accountId, $defaultsState) {
            $fromDb = $this->readModulesStateFromAdminAccounts($accountId);
            $state  = array_replace($defaultsState, $fromDb);
            return $this->bundleFromState($state);
        });
    }

    private function bundleFromState(array $state): array
    {
        $access  = [];
        $visible = [];

        foreach ($state as $k => $st) {
            $st = $this->normState($st);
            $visible[$k] = ($st !== self::MOD_HIDDEN);
            $access[$k]  = ($st === self::MOD_ACTIVE);
        }

        return [
            'state'   => $state,
            'access'  => $access,
            'visible' => $visible,
        ];
    }

    private function normState(mixed $s): string
    {
        $s = strtolower(trim((string) $s));
        return in_array($s, [self::MOD_ACTIVE, self::MOD_INACTIVE, self::MOD_HIDDEN, self::MOD_BLOCKED], true)
            ? $s
            : self::MOD_ACTIVE;
    }

    private function catalogDefaultsState(): array
    {
        $keys = [
            'mi_cuenta',
            'estado_cuenta',
            'pagos',
            'facturas',

            'facturacion',
            'sat_descargas',
            'boveda_fiscal',
            'nomina',
            'crm',
            'pos',
            'inventario',
            'reportes',
            'integraciones',
            'chat',
            'alertas',
            'marketplace',
            'configuracion_avanzada',
        ];

        $out = [];
        foreach ($keys as $k) $out[$k] = self::MOD_ACTIVE;
        return $out;
    }

    private function readModulesStateFromAdminAccounts(int $accountId): array
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        $row = DB::connection($adm)->table('accounts')
            ->select(['id', 'meta'])
            ->where('id', $accountId)
            ->first();

        if (!$row || empty($row->meta)) return [];

        $meta = $this->jsonToArray($row->meta);
        if (!$meta) return [];

        $state  = $meta['modules_state'] ?? null;
        $legacy = $meta['modules'] ?? null;

        $out = [];

        if (is_array($state)) {
            foreach ($state as $k => $v) {
                $k = is_string($k) ? trim($k) : '';
                if ($k === '') continue;
                $out[$k] = $this->normState($v);
            }
            return $out;
        }

        if (is_array($legacy)) {
            foreach ($legacy as $k => $v) {
                $k = is_string($k) ? trim($k) : '';
                if ($k === '') continue;
                $out[$k] = ((bool) $v) ? self::MOD_ACTIVE : self::MOD_INACTIVE;
            }
        }

        return $out;
    }

    private function readAdminModulesUpdatedAt(int $accountId): ?string
    {
        if ($accountId <= 0) return null;

        try {
            $adm  = (string) (config('p360.conn.admin') ?: 'mysql_admin');
            $meta = DB::connection($adm)->table('accounts')->where('id', $accountId)->value('meta');
            if (!is_string($meta) || trim($meta) === '') return null;

            $m = json_decode($meta, true);
            if (!is_array($m)) return null;

            $v = $m['modules_updated_at'] ?? null;
            if (!is_string($v) || trim($v) === '') return null;

            return trim($v);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function jsonToArray(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];

        $d = json_decode($value, true);
        return is_array($d) ? $d : [];
    }
}
