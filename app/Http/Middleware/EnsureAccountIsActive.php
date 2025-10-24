<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EnsureAccountIsActive
{
    /**
     * Reglas:
     * - En rutas /admin/* solo verificamos que haya sesión admin (ya lo cubre auth:admin y tu LoginController).
     * - En rutas del FRONT (guard web):
     *   * Usuario debe estar activo (si existe columna 'activo').
     *   * Cuenta espejo (mysql_clientes.cuentas_cliente) no debe estar bloqueada/suspendida.
     *   * Verificación de email/teléfono (mysql_admin.accounts) si está habilitado por .env.
     *   * Se permite bypass en pantallas de verificación/password/logout para evitar loops.
     *   * Si es sesión “impersonate” (hecha desde admin), permitimos pasar sin forzar verificaciones.
     */
    public function handle(Request $request, Closure $next)
    {
        // ==== BYPASS de rutas de verificación / auth para evitar bucles ====
        $path = ltrim((string)$request->path(), '/');
        $bypassPrefixes = [
            'cliente/verificar',         // email/phone verify
            'cliente/password',          // reset/first login
            'cliente/terminos',          // términos
            'cliente/_qa',               // QA panel
        ];
        $bypassExact = [
            'cliente/logout',
            'cliente/login',
            'cliente/registro',
            'cliente/registro/pro',
            'cliente/checkout/success',
            'cliente/checkout/cancel',
        ];
        foreach ($bypassPrefixes as $pfx) {
            if (Str::startsWith($path, $pfx)) {
                return $next($request);
            }
        }
        if (in_array($path, $bypassExact, true)) {
            return $next($request);
        }

        // ==== Si es sesión ADMIN, dejamos pasar (auth:admin ya validó) ====
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        // ==== Guard WEB (clientes) ====
        if (!Auth::guard('web')->check()) {
            // Si alguien añadió este middleware sin auth previo, redirige a login cliente.
            return redirect()->route('cliente.login');
        }

        $user = Auth::guard('web')->user();

        // Impersonate: si viene desde admin, no bloqueamos por verificaciones/bloqueos.
        $isImpersonated = (bool) session('impersonated_by_admin');
        if ($isImpersonated) {
            return $next($request);
        }

        // 1) Usuario activo (si existe la columna)
        try {
            $tblUsers = (method_exists($user, 'getTable') ? $user->getTable() : 'usuarios_cuenta');
            $hasActivoCol = Schema::connection('mysql_clientes')->hasColumn($tblUsers, 'activo');
            if ($hasActivoCol && (int)($user->activo ?? 0) !== 1) {
                return $this->denyToEstadoCuenta('Tu usuario está inactivo. Contacta a soporte.');
            }
        } catch (\Throwable $e) {
            // No romper si falla introspección de esquema
        }

        // 2) Cuenta espejo (mysql_clientes.cuentas_cliente)
        $cuentaId = (string) ($user->cuenta_id ?? '');
        if ($cuentaId !== '') {
            try {
                $conn = DB::connection('mysql_clientes');
                $tblCuentas = 'cuentas_cliente';

                if (Schema::connection('mysql_clientes')->hasTable($tblCuentas)) {
                    $select = ['id', 'rfc_padre'];
                    $hasEstado   = Schema::connection('mysql_clientes')->hasColumn($tblCuentas, 'estado_cuenta');
                    $hasBlocked  = Schema::connection('mysql_clientes')->hasColumn($tblCuentas, 'is_blocked');

                    if ($hasEstado)  $select[] = 'estado_cuenta';
                    if ($hasBlocked) $select[] = 'is_blocked';

                    $cuenta = $conn->table($tblCuentas)->where('id', $cuentaId)->select($select)->first();

                    if ($cuenta) {
                        // 2a) Bloqueos por flags/estado
                        if ($hasBlocked && (int)($cuenta->is_blocked ?? 0) === 1) {
                            return $this->denyToEstadoCuenta('Tu cuenta está bloqueada. Revisa tu estado y métodos de pago.');
                        }

                        if ($hasEstado) {
                            $estado = strtolower((string) $cuenta->estado_cuenta);
                            $bloqueados = ['bloqueada', 'bloqueada_pago', 'suspendida', 'inactiva', 'pendiente_pago'];
                            if (in_array($estado, $bloqueados, true)) {
                                // redirige a estado de cuenta (pantalla informativa)
                                return $this->denyToEstadoCuenta('Tu cuenta no está activa. Revisa tu estado para continuar.');
                            }
                        }

                        // 3) Verificación email/teléfono en mysql_admin.accounts (si se requiere)
                        $requireEmail = filter_var(env('REQUIRE_EMAIL_VERIFIED', true), FILTER_VALIDATE_BOOLEAN);
                        $requirePhone = filter_var(env('REQUIRE_PHONE_VERIFIED', true), FILTER_VALIDATE_BOOLEAN);

                        if (($requireEmail || $requirePhone) && !empty($cuenta->rfc_padre)) {
                            $adminOk = $this->checkAdminVerifications((string) $cuenta->rfc_padre, $requireEmail, $requirePhone);
                            if ($adminOk !== true) {
                                // adminOk es un string con el motivo
                                return $this->denyToVerify($adminOk);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silencioso: si algo falla, no bloqueamos el flujo del usuario
            }
        }

        return $next($request);
    }

    /**
     * Verifica en mysql_admin.accounts si el RFC tiene email/phone verificados.
     * Devuelve true si todo OK, o un string con el motivo para mostrar/usar en redirect.
     */
    private function checkAdminVerifications(string $rfc, bool $requireEmail, bool $requirePhone)
    {
        try {
            if (!Schema::connection('mysql_admin')->hasTable('accounts')) {
                return true; // no forzar si no existe la tabla
            }

            $emailCol = $this->colAdminEmail();
            $select = ['id', $emailCol.' as email'];

            $hasEmailAt = Schema::connection('mysql_admin')->hasColumn('accounts', 'email_verified_at');
            $hasPhoneAt = Schema::connection('mysql_admin')->hasColumn('accounts', 'phone_verified_at');

            if ($hasEmailAt) $select[] = 'email_verified_at';
            if ($hasPhoneAt) $select[] = 'phone_verified_at';

            $acc = DB::connection('mysql_admin')->table('accounts')->where('id', strtoupper(trim($rfc)))->select($select)->first();
            if (!$acc) return true; // si no lo encontramos, no bloqueamos

            // Guarda en sesión para facilitar el flujo de verificación
            if (!empty($acc->email)) {
                session(['verify.account_id' => $acc->id, 'verify.email' => strtolower($acc->email)]);
            }

            if ($requireEmail && $hasEmailAt && empty($acc->email_verified_at)) {
                return 'Debes verificar tu correo electrónico para continuar.';
            }
            if ($requirePhone && $hasPhoneAt && empty($acc->phone_verified_at)) {
                return 'Debes verificar tu teléfono para continuar.';
            }

            return true;
        } catch (\Throwable $e) {
            return true; // ante errores, no bloqueamos
        }
    }

    private function colAdminEmail(): string
    {
        try {
            if (Schema::connection('mysql_admin')->hasColumn('accounts', 'correo_contacto')) return 'correo_contacto';
            if (Schema::connection('mysql_admin')->hasColumn('accounts', 'email')) return 'email';
        } catch (\Throwable $e) {}
        return 'email';
    }

    /** Redirige a la pantalla de "estado de cuenta" del cliente con mensaje */
    private function denyToEstadoCuenta(string $msg)
    {
        try {
            return redirect()->route('cliente.estado_cuenta')->with('error', $msg);
        } catch (\Throwable $e) {
            // si la ruta no existe, regresa a home
            return redirect()->route('cliente.home')->with('error', $msg);
        }
    }

    /** Redirige al flujo de verificación correspondiente con mensaje */
    private function denyToVerify(string $msg)
    {
        // Si falta email → pantalla para reenviar link
        if (Str::contains(Str::lower($msg), 'correo')) {
            try {
                return redirect()->route('cliente.verify.email.resend')->with('error', $msg);
            } catch (\Throwable $e) {
                // fallback al home
            }
        }
        // Si falta teléfono → pantalla OTP
        if (Str::contains(Str::lower($msg), 'teléfono')) {
            try {
                return redirect()->route('cliente.verify.phone')->with('error', $msg);
            } catch (\Throwable $e) {
                // fallback al home
            }
        }

        // Fallback genérico
        return redirect()->route('cliente.home')->with('error', $msg);
    }
}
