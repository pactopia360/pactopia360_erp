<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EnsureAccountIsActive
{
    private const ESTADOS_OK = [
        'activa',
        'active',
        'ok',
        'activa_ok',
        'operando',
        'operando_ok',
    ];

    private const LOOP_GUARD_LIMIT = 2;

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            // no hay sesión cliente -> login cliente
            return redirect()
                ->route('cliente.login')
                ->with('info', 'Debes iniciar sesión.');
        }

        // impersonation admin = dejar pasar todo
        if (session('impersonated_by_admin')) {
            return $next($request);
        }

        // entorno local/dev/testing => NO bloqueamos nada
        if (App::environment(['local', 'development', 'testing'])) {
            return $next($request);
        }

        // checar si el usuario está activo (si existe esa col)
        if ($this->columnExists('mysql_clientes', $user->getTable() ?? 'usuarios_cuenta', 'activo')) {
            if (!(bool) ($user->activo ?? 0)) {
                // usuario inactivo es bloqueo DURO
                return $this->blockHard($request,
                    'Tu usuario está inactivo. Verifica tu cuenta o contacta a soporte.'
                );
            }
        }

        // forzar cambio de contraseña inicial
        if (method_exists($user, 'mustChangePassword') && $user->mustChangePassword()) {
            if (!$this->isFirstPasswordRoute($request)) {
                return redirect()
                    ->route('cliente.password.first')
                    ->with('info', 'Por seguridad, debes actualizar tu contraseña antes de continuar.');
            }
        }

        // info de la cuenta cliente (estado_cuenta, is_blocked, rfc_padre)
        $cuentaInfo = $this->fetchCuentaClienteInfo($user->cuenta_id);
        if (!$cuentaInfo) {
            // esto sí es raro y es bloqueo DURO
            return $this->blockHard($request,
                'No encontramos tu cuenta en el sistema. Contacta a soporte.'
            );
        }

        // si la cuenta está marcada como bloqueada por pago -> bloqueo DURO
        if (!empty($cuentaInfo->is_blocked) && (int) $cuentaInfo->is_blocked === 1) {
            return $this->blockHard($request,
                'Tu cuenta está bloqueada por pago pendiente. Completa el pago o comunícate con soporte.'
            );
        }

        $estadoCuenta = strtolower((string) $cuentaInfo->estado_cuenta);

        // Estados que requieren onboarding/verificación pero NO deben botarte de la sesión.
        $estados_suaves = [
            'bloqueada',
            'bloqueada_pago',
            'suspendida',
            'suspendida_pago',
            'pendiente_pago',
            'inactiva',
            'onboarding',
            'pendiente',
            'awaiting_verification',
            'verificacion',
        ];

        // Si el estado NO está aceptado como OK
        if (!in_array($estadoCuenta, self::ESTADOS_OK, true)) {
            // bloqueo SUAVE -> mandar a verificación SIN cerrar sesión
            return $this->blockSoftToVerify($request,
                'Tu cuenta aún no está activa. Verifica tu correo y teléfono o completa el pago.'
            );
        }

        // verificación global admin (email / phone)
        $requireEmail = filter_var(env('REQUIRE_EMAIL_VERIFIED', true), FILTER_VALIDATE_BOOLEAN);
        $requirePhone = filter_var(env('REQUIRE_PHONE_VERIFIED', true), FILTER_VALIDATE_BOOLEAN);

        if (($requireEmail || $requirePhone) && $cuentaInfo->rfc_padre) {
            $status = $this->checkAdminVerifications($cuentaInfo->rfc_padre, $requireEmail, $requirePhone);

            if ($status !== true) {
                // bloqueo SUAVE -> mandar a verify.* sin cerrar sesión
                return $this->blockSoftToVerify($request, $status);
            }
        }

        // si todo ok, continuar
        return $next($request);
    }

    private function columnExists(string $connection, string $table, string $col): bool
    {
        try {
            return Schema::connection($connection)->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function fetchCuentaClienteInfo(?string $cuentaId): ?object
    {
        if (!$cuentaId) {
            return null;
        }

        try {
            if (!Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                return null;
            }

            return DB::connection('mysql_clientes')
                ->table('cuentas_cliente')
                ->select([
                    'id',
                    'rfc_padre',
                    'estado_cuenta',
                    DB::raw('IFNULL(is_blocked,0) as is_blocked'),
                ])
                ->where('id', $cuentaId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function checkAdminVerifications(string $rfcPadre, bool $requireEmail, bool $requirePhone)
    {
        try {
            if (!Schema::connection('mysql_admin')->hasTable('accounts')) {
                return true;
            }

            $schemaAdmin = Schema::connection('mysql_admin');
            $connAdmin   = DB::connection('mysql_admin');

            $emailCol = $this->resolveAdminEmailCol($schemaAdmin);

            $hasEmailVerifiedAt = $schemaAdmin->hasColumn('accounts', 'email_verified_at');
            $hasPhoneVerifiedAt = $schemaAdmin->hasColumn('accounts', 'phone_verified_at');

            // NOTA: aquí no nos vamos por la más nueva a ciegas, vamos a elegir la "no bloqueada" primero
            $q = $connAdmin->table('accounts')
                ->whereRaw('UPPER(rfc)=?', [strtoupper($rfcPadre)]);

            if ($schemaAdmin->hasColumn('accounts', 'is_blocked')) {
                $q->orderBy('is_blocked','asc'); // preferimos las no bloqueadas
            }
            $q->orderByDesc('id');

            $acc = $q->select([
                    'id',
                    'rfc',
                    $emailCol.' as email',
                    $hasEmailVerifiedAt
                        ? 'email_verified_at'
                        : DB::raw('NULL as email_verified_at'),
                    $hasPhoneVerifiedAt
                        ? 'phone_verified_at'
                        : DB::raw('NULL as phone_verified_at'),
                ])
                ->first();

            if (!$acc) {
                return true;
            }

            // guardamos en sesión info para flujo verify
            session([
                'verify.account_id' => $acc->id,
                'verify.email'      => strtolower((string) $acc->email),
            ]);

            if ($requireEmail && $hasEmailVerifiedAt && empty($acc->email_verified_at)) {
                return 'Debes verificar tu correo electrónico para continuar.';
            }

            if ($requirePhone && $hasPhoneVerifiedAt && empty($acc->phone_verified_at)) {
                return 'Debes verificar tu teléfono antes de continuar.';
            }

            return true;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function resolveAdminEmailCol($schemaAdmin): string
    {
        try {
            if ($schemaAdmin->hasColumn('accounts', 'correo_contacto')) {
                return 'correo_contacto';
            }
            if ($schemaAdmin->hasColumn('accounts', 'email')) {
                return 'email';
            }
        } catch (\Throwable $e) {}
        return 'correo_contacto';
    }

    /**
     * BLOQUE DURO: sí cierra sesión.
     */
    private function blockHard(Request $request, string $msg)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => false,
                'message' => $msg,
            ], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('cliente.login')
            ->with('info', $msg)
            ->with('need_verify', true);
    }

    /**
     * BLOQUE SUAVE: NO cierra sesión. Redirige a verificación manteniendo auth('web').
     */
    private function blockSoftToVerify(Request $request, string $msg)
    {
        // loop guard
        $count = (int) $request->session()->get('block_loop_count', 0);
        if ($count >= self::LOOP_GUARD_LIMIT) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => $msg,
                ], 403);
            }
            return abort(403, $msg);
        }
        $request->session()->put('block_loop_count', $count + 1);

        // decide si es correo o teléfono
        $isPhoneMsg  = Str::contains(Str::lower($msg), ['teléfono', 'telefono', 'whatsapp', 'sms']);
        $isEmailMsg  = Str::contains(Str::lower($msg), ['correo', 'email']);

        if ($isEmailMsg) {
            return redirect()
                ->route('cliente.verify.email.resend')
                ->with('error', $msg)
                ->with('need_verify', true);
        }

        if ($isPhoneMsg) {
            return redirect()
                ->route('cliente.verify.phone')
                ->with('error', $msg)
                ->with('need_verify', true);
        }

        // fallback genérico
        return redirect()
            ->route('cliente.verify.phone')
            ->with('error', $msg)
            ->with('need_verify', true);
    }

    private function isFirstPasswordRoute(Request $request): bool
    {
        $name = $request->route()?->getName();
        return in_array($name, [
            'cliente.password.first',
            'cliente.password.first.store',
        ], true);
    }
}
