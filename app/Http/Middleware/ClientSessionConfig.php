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
        // =========================================================
        // ✅ BYPASS ADMIN (CRÍTICO)
        // Este middleware SOLO aplica al portal CLIENTE.
        // Si corre en /admin o rutas admin.*, rompe sesión/cookie/guard y te manda al home.
        // =========================================================
        try {
            $routeName = optional($request->route())->getName();

            // Path admin
            if ($request->is('admin', 'admin/*')) {
                return $next($request);
            }

            // Name admin.*
            if (is_string($routeName) && $routeName !== '' && str_starts_with($routeName, 'admin.')) {
                return $next($request);
            }

            // Si existe guard admin y está autenticado, NO tocar nada de cliente
            if (Auth::guard('admin')->check()) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            // Si falla algo aquí, NO arriesgamos romper admin; dejamos pasar.
            // (mejor un bypass accidental que resetear sesión)
            return $next($request);
        }

        // =========================================================
        // 1) Sesión separada Cliente (solo efectivo ANTES de StartSession)
        // =========================================================
        Config::set('session.cookie', 'p360_client_session');

        // =========================================================
        // 2) BLINDAJE AUTH: forzar modelo UsuarioCuenta para guard web en contexto cliente
        // =========================================================
        Config::set('auth.defaults.guard', 'web');
        Config::set('auth.defaults.passwords', 'clientes');
        Config::set('auth.providers.users.driver', 'eloquent');
        Config::set('auth.providers.users.model', UsuarioCuenta::class);

        try { Auth::shouldUse('web'); } catch (\Throwable $e) {}

        // =========================================================
        // 3) No tocar sesión si no hay store aún (evita warning)
        // =========================================================
        $hasSessionStore = method_exists($request, 'hasSession') ? $request->hasSession() : false;
        if (!$hasSessionStore) {
            if (app()->environment(['local', 'development', 'testing'])) {
                Log::debug('ClientSessionConfig.skip_no_session_store', [
                    'path'  => $request->path(),
                    'route' => optional($request->route())->getName(),
                ]);
            }
            return $next($request);
        }

        // =========================================================
        // 4) Hidratar módulos si hay usuario, con invalidación por "version" del admin o TTL
        // =========================================================
        try {
            $user = Auth::guard('web')->user();

            if (!$user) {
                $this->clearModulesSession($request);
                return $next($request);
            }

            // Asegurar relación cuenta para admin_account_id
            try { if (method_exists($user, 'loadMissing')) $user->loadMissing('cuenta'); } catch (\Throwable $e) {}

            $accountId = $this->resolveAccountId($user);

            /**
             * ✅ FIX CRÍTICO (Billing legacy):
             * Billing y otras piezas aún leen estas keys:
             * - session('account_id'), session('client.account_id'), session('client_account_id')
             * y también algunos lugares leen cuenta_id UUID:
             * - session('cuenta_id'), session('client.cuenta_id'), session('client_cuenta_id')
             *
             * Tu SOT moderno vive en p360.account_id, así que espejamos SIEMPRE,
             * incluso si no hay refresh de módulos (TTL/version).
             */
            $this->mirrorLegacyAccountKeys($request, $accountId, $user);

            // Gate: refrescar si cambió account_id, cambió "modules_updated_at" en admin, o expiró TTL.
            $lastAccountId = (int) $request->session()->get('p360.account_id', 0);

            $ttlSec      = (int) (config('p360.modules_state_ttl', 60)); // 60s default
            $lastHydTs   = (int) $request->session()->get('p360.modules_synced_at', 0);
            $nowTs       = time();
            $ttlExpired  = ($lastHydTs <= 0) || (($nowTs - $lastHydTs) >= $ttlSec);

            // "Version" desde admin: modules_updated_at
            $adminVersion   = $this->readAdminModulesUpdatedAt($accountId); // string|null
            $lastVersion    = (string) $request->session()->get('p360.modules_version', '');
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

                if (app()->environment(['local', 'development', 'testing'])) {
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
        // SOT moderno
        $request->session()->forget('p360.account_id');
        $request->session()->forget('p360.modules_state');
        $request->session()->forget('p360.modules_access');
        $request->session()->forget('p360.modules_visible');
        $request->session()->forget('p360.modules');
        $request->session()->forget('p360.modules_synced_at');
        $request->session()->forget('p360.modules_version');

        // ✅ Legacy (evita basura / “unresolved” posterior)
        $request->session()->forget('account_id');
        $request->session()->forget('client.account_id');
        $request->session()->forget('client_account_id');

        $request->session()->forget('cuenta_id');
        $request->session()->forget('client.cuenta_id');
        $request->session()->forget('client_cuenta_id');
    }

    /**
     * Espeja admin_account_id y cuenta UUID a keys legacy usadas por Billing/otras piezas.
     */
    private function mirrorLegacyAccountKeys(Request $request, int $accountId, object $user): void
    {
        // admin account id (mysql_admin.accounts.id)
        if ($accountId > 0) {
            $request->session()->put('account_id', $accountId);
            $request->session()->put('client.account_id', $accountId);
            $request->session()->put('client_account_id', $accountId);
        } else {
            // Si no se resuelve, limpiamos para evitar que se quede uno viejo
            $request->session()->forget('account_id');
            $request->session()->forget('client.account_id');
            $request->session()->forget('client_account_id');
        }

        // cuenta UUID (mysql_clientes.cuentas_cliente.id)
        $cuentaId = null;

        // 1) Preferimos $user->cuenta_id si existe
        try {
            if (isset($user->cuenta_id) && is_string($user->cuenta_id) && trim($user->cuenta_id) !== '') {
                $cuentaId = trim((string) $user->cuenta_id);
            }
        } catch (\Throwable $e) {
            $cuentaId = null;
        }

        // 2) Fallback: algunos legacy guardaban el “id/uuid” en account_id (pero OJO: puede ser int admin)
        try {
            if (!$cuentaId && isset($user->account_id) && is_string($user->account_id) && trim($user->account_id) !== '') {
                $maybe = trim((string) $user->account_id);

                // Si es numérico, probablemente NO es UUID (pero puede ser cuentas_cliente.id en algunos flujos)
                // Lo aceptamos como cuentaId solo si existe en cuentas_cliente.
                if (ctype_digit($maybe)) {
                    try {
                        $exists = CuentaCliente::on('mysql_clientes')->where('id', (int)$maybe)->exists();
                        if ($exists) $cuentaId = $maybe;
                    } catch (\Throwable $e) {
                        // ignore
                    }
                } else {
                    // No numérico: podría ser UUID, pero tu tabla NO tiene uuid/public_id.
                    // Aun así lo espejamos porque algunos flujos lo usan (sin query).
                    $cuentaId = $maybe;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if ($cuentaId) {
            $request->session()->put('cuenta_id', $cuentaId);
            $request->session()->put('client.cuenta_id', $cuentaId);
            $request->session()->put('client_cuenta_id', $cuentaId);
        } else {
            $request->session()->forget('cuenta_id');
            $request->session()->forget('client.cuenta_id');
            $request->session()->forget('client_cuenta_id');
        }

        // SOT moderno siempre presente
        $request->session()->put('p360.account_id', (int) $accountId);
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
                if (is_numeric($adm) && (int) $adm > 0) return (int) $adm;
            }
        } catch (\Throwable $e) {}

        // 2) Resolver vía mysql_clientes.cuentas_cliente
        try {
            $cuentaId = null;

            if (isset($user->cuenta_id) && is_string($user->cuenta_id) && trim($user->cuenta_id) !== '') {
                $cuentaId = trim((string) $user->cuenta_id);
            } elseif (isset($user->account_id) && is_string($user->account_id) && trim($user->account_id) !== '') {
                $cuentaId = trim((string) $user->account_id);
            }

            // Caso A: id numérico de cuentas_cliente
            if ($cuentaId && ctype_digit($cuentaId)) {
                $row = CuentaCliente::on('mysql_clientes')->find((int) $cuentaId);
                $adm = $row?->admin_account_id ?? null;
                if (is_numeric($adm) && (int) $adm > 0) return (int) $adm;
            }

            // Caso B: como tu cuentas_cliente NO tiene uuid/public_id, resolvemos por RFC/email
            $email = '';
            $rfc   = '';

            if (isset($user->email)) $email = strtolower(trim((string) $user->email));
            if (isset($user->rfc))   $rfc   = strtoupper(trim((string) $user->rfc));

            if ($rfc !== '') {
                $adm = (int) (CuentaCliente::on('mysql_clientes')
                    ->whereRaw('UPPER(rfc)=?', [$rfc])
                    ->value('admin_account_id') ?? 0);
                if ($adm > 0) return $adm;
            }

            if ($email !== '') {
                $adm = (int) (CuentaCliente::on('mysql_clientes')
                    ->whereRaw('LOWER(email)=?', [$email])
                    ->value('admin_account_id') ?? 0);
                if ($adm > 0) return $adm;
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

    /**
     * Lee "modules_updated_at" del meta para invalidar sesión cuando Admin cambie módulos.
     */
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
