<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

final class ClientSessionConfig
{
    /**
     * Cookie aislada para el portal cliente (debe ser la misma que usas en LoginController).
     */
    public const CLIENT_COOKIE = 'p360_client_session';

    /**
     * Llaves típicas que causan "arrastre" de cuenta/módulos.
     * Mantén aquí TODO lo que el portal usa para resolver account_id / módulos / contexto.
     */
    private const ACCOUNT_KEYS = [
        // ====== DEBUG (__session) / compat ======
        'cliente.cuenta_id',
        'cliente.account_id',
        'client.cuenta_id',
        'client.account_id',
        'cuenta_id',
        'account_id',
        'client_cuenta_id',
        'client_account_id',

        // ====== p360 SOT ======
        'p360.account_id',
        'p360.admin_account_id',

        // SOT módulos (nuevo)
        'p360.modules',
        'p360.modules_state',
        'p360.modules_access',
        'p360.modules_visible',

        // Versionado / timestamps (si existen en tu flujo)
        'p360.modules_version',
        'p360.modules_synced_at',
        'p360.modules_updated_at',
        'p360.modules_updated_by',
        'p360.modules_state_updated_at',
        'p360.modules_state_updated_by',

        // ====== Otros posibles legacy/compat ======
        'client_account_id2',
        'client.account_id2',
        'p360.context',
        'p360.ctx',
        'p360.last_account_id',
        'p360.last_rfc',

        // Legacy sueltos
        'modules',
        'modules_state',
    ];

    /**
     * Llaves de flujos que suelen quedarse pegadas entre cuentas.
     */
    private const FLOW_KEYS = [
        // Verify
        'verify.account_id',
        'verify.rfc',
        'verify.email',

        // Paywall
        'paywall.account_id',
        'paywall.cycle',
        'paywall.email',

        // Post-verify
        'post_verify.user_id',
        'post_verify.remember',

        // Impersonate (cliente)
        'impersonated_by_admin',
    ];

    private function __construct() {}

    public static function applyCookie(): void
    {
        Config::set('session.cookie', self::CLIENT_COOKIE);
        Auth::shouldUse('web');
    }

    /**
     * Borra SOLO lo peligroso: cuenta + módulos + flow keys.
     * No toca: url.intended, CSRF, throttle buckets, etc.
     */
    public static function hardReset(Request $request): void
    {
        self::applyCookie();

        // Cuenta/módulos
        $request->session()->forget(self::ACCOUNT_KEYS);

        // Flujos
        $request->session()->forget(self::FLOW_KEYS);
    }

    /**
     * Borra cuenta+módulos (sin tocar flows).
     * Útil cuando quieres conservar verify/paywall recién seteado en el mismo request.
     */
    public static function forgetAccountAndModulesKeys(Request $request): void
    {
        self::applyCookie();
        $request->session()->forget(self::ACCOUNT_KEYS);
    }

    /**
     * Set uniforme SOLO de adminAccountId (SOT + legacy).
     *
     * IMPORTANTE:
     * - NO escribimos "cuenta_id" aquí porque en portal cliente suele ser UUID (cuentas_cliente.id).
     * - "account_id" aquí representa el admin_account_id (mysql_admin.accounts.id).
     */
    public static function setAdminAccountId(Request $request, int $adminAccountId): void
    {
        self::applyCookie();

        if ($adminAccountId <= 0) {
            // Solo limpiamos llaves de account/módulos, no flows
            $request->session()->forget(self::ACCOUNT_KEYS);
            return;
        }

        // ====== DEBUG (__session) / compat (account_id) ======
        $request->session()->put('cliente.account_id', $adminAccountId);
        $request->session()->put('client.account_id', $adminAccountId);
        $request->session()->put('account_id', $adminAccountId);
        $request->session()->put('client_account_id', $adminAccountId);

        // ====== p360 SOT ======
        $request->session()->put('p360.account_id', $adminAccountId);
        $request->session()->put('p360.admin_account_id', $adminAccountId);

        // ====== compat extra ======
        $request->session()->put('client_account_id2', $adminAccountId);
        $request->session()->put('client.account_id2', $adminAccountId);

        // ⚠️ NO TOCAR cuenta_id aquí (UUID). Lo setea tu flujo real de cliente:
        // - cliente.cuenta_id / cuenta_id (UUID) si aplica
        // - o lo resuelve el middleware de sesión.

        // Importante: fuerza recarga de módulos (si hay cache por account)
        try {
            Cache::forget('p360:mods:acct:' . $adminAccountId);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Backward-compat: si ya tienes llamadas a setAccountId(), no rompas.
     */
    public static function setAccountId(Request $request, int $adminAccountId): void
    {
        self::setAdminAccountId($request, $adminAccountId);
    }
}
