<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Cliente\CuentaCliente;
use App\Models\Cliente\UsuarioCuenta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ClientSessionConfig
{
    private const MOD_ACTIVE   = 'active';
    private const MOD_INACTIVE = 'inactive';
    private const MOD_HIDDEN   = 'hidden';
    private const MOD_BLOCKED  = 'blocked';

    public function handle(Request $request, Closure $next)
    {
        // 1) Sesión separada Cliente (solo efectivo ANTES de StartSession)
        Config::set('session.cookie', 'p360_client_session');

        // 2) BLINDAJE AUTH: forzar modelo UsuarioCuenta para guard web en contexto cliente
        Config::set('auth.defaults.guard', 'web');
        Config::set('auth.defaults.passwords', 'clientes');
        Config::set('auth.providers.users.driver', 'eloquent');
        Config::set('auth.providers.users.model', UsuarioCuenta::class);

        try { Auth::shouldUse('web'); } catch (\Throwable $e) {}

        // 3) No tocar sesión si no hay store aún (evita warning)
        $hasSessionStore = method_exists($request, 'hasSession') ? $request->hasSession() : false;
        if (!$hasSessionStore) {
            if (app()->environment(['local','development','testing'])) {
                Log::debug('ClientSessionConfig.skip_no_session_store', [
                    'path'  => $request->path(),
                    'route' => optional($request->route())->getName(),
                ]);
            }
            return $next($request);
        }

        // 4) Hidratar módulos si hay usuario, con invalidación por "version" del admin o TTL
        try {
            $user = Auth::guard('web')->user();

            if (!$user) {
                $this->clearModulesSession($request);
                return $next($request);
            }

            // Asegurar relación cuenta para admin_account_id
            try { if (method_exists($user, 'loadMissing')) $user->loadMissing('cuenta'); } catch (\Throwable $e) {}

            $accountId = $this->resolveAccountId($user);

            // Si no hay vínculo, usamos defaults (pero igual escribimos sesión para que sidebar funcione)
            // Gate: refrescar si cambió account_id, cambió "modules_updated_at" en admin, o expiró TTL.
            $lastAccountId = (int) $request->session()->get('p360.account_id', 0);

            $ttlSec    = (int) (config('p360.modules_state_ttl', 60)); // 60s default
            $lastHydTs = (int) $request->session()->get('p360.modules_synced_at', 0);
            $nowTs     = time();
            $ttlExpired = ($lastHydTs <= 0) || (($nowTs - $lastHydTs) >= $ttlSec);

            // "Version" desde admin: modules_updated_at
            $adminVersion = $this->readAdminModulesUpdatedAt($accountId); // string|null
            $lastVersion  = (string) $request->session()->get('p360.modules_version', '');

            $versionChanged = false;
            if ($adminVersion !== null && $adminVersion !== '' && $adminVersion !== $lastVersion) {
                $versionChanged = true;
            }

            $mustRefresh = ($lastAccountId !== $accountId) || $ttlExpired || $versionChanged;

            if ($mustRefresh) {
                $bundle = $this->loadModulesBundleForAccount($accountId);

                $request->session()->put('p360.account_id', $accountId);
                $request->session()->put('p360.modules_state',   $bundle['state']);
                $request->session()->put('p360.modules_access',  $bundle['access']);
                $request->session()->put('p360.modules_visible', $bundle['visible']);

                // Compat v3.x: bool visible
                $legacyModules = [];
                foreach ($bundle['visible'] as $k => $isVisible) {
                    $legacyModules[$k] = (bool) $isVisible;
                }
                $request->session()->put('p360.modules', $legacyModules);

                $request->session()->put('p360.modules_synced_at', $nowTs);
                $request->session()->put('p360.modules_version', (string)($adminVersion ?? ''));

                if (app()->environment(['local','development','testing'])) {
                    Log::debug('ClientSessionConfig.modules_bundle', [
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
            Log::warning('ClientSessionConfig: no se pudo cargar módulos', [
                'err'   => $e->getMessage(),
                'path'  => $request->path(),
                'route' => optional($request->route())->getName(),
            ]);
        }

        return $next($request);
    }

    private function clearModulesSession(Request $request): void
    {
        $request->session()->forget('p360.account_id');
        $request->session()->forget('p360.modules_state');
        $request->session()->forget('p360.modules_access');
        $request->session()->forget('p360.modules_visible');
        $request->session()->forget('p360.modules');
        $request->session()->forget('p360.modules_synced_at');
        $request->session()->forget('p360.modules_version');
    }

    /**
     * Resuelve el accountId REAL (mysql_admin.accounts.id).
     */
    private function resolveAccountId(object $user): int
    {
        // 1) Relación cuenta->admin_account_id
        try {
            if (isset($user->cuenta) && is_object($user->cuenta)) {
                $adm = $user->cuenta->admin_account_id ?? null;
                if (is_numeric($adm) && (int)$adm > 0) return (int)$adm;
            }
        } catch (\Throwable $e) {}

        // 2) cuenta_id UUID -> cuentas_cliente.admin_account_id
        try {
            $cuentaId = null;

            if (isset($user->cuenta_id) && is_string($user->cuenta_id) && trim($user->cuenta_id) !== '') {
                $cuentaId = trim((string)$user->cuenta_id);
            }

            if (!$cuentaId && isset($user->account_id) && is_string($user->account_id) && trim($user->account_id) !== '') {
                $cuentaId = trim((string)$user->account_id);
            }

            if ($cuentaId) {
                $row = CuentaCliente::on('mysql_clientes')->find($cuentaId);
                $adm = $row?->admin_account_id ?? null;
                if (is_numeric($adm) && (int)$adm > 0) return (int)$adm;
            }
        } catch (\Throwable $e) {}

        // 3) Prop directa
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
        $s = strtolower(trim((string)$s));
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
        $adm = (string)(config('p360.conn.admin') ?: 'mysql_admin');

        $row = DB::connection($adm)->table('accounts')
            ->select(['id','meta'])
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
                $out[$k] = ((bool)$v) ? self::MOD_ACTIVE : self::MOD_INACTIVE;
            }
        }

        return $out;
    }

    /**
     * Lee "modules_updated_at" del meta para invalidar sesión cuando Admin cambie módulos.
     */
    private function readAdminModulesUpdatedAt(int $accountId): ?string
    {
        if ($accountId <= 0) return null;

        try {
            $adm = (string)(config('p360.conn.admin') ?: 'mysql_admin');
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
