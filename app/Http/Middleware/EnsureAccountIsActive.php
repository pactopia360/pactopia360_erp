<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class EnsureAccountIsActive
{
    /**
     * Estados que SÍ se consideran OK en clientes.
     */
    private const ESTADOS_OK = [
        'activa',
        'activo',
        'active',
        'ok',
        'activa_ok',
        'operando',
        'operando_ok',
        'free',
        'gratis',
        'trial',
        'demo',
    ];

    /**
     * Estados típicos que implican bloqueo por pago (PRO).
     */
    private const ESTADOS_PAGO = [
        'bloqueada_pago',
        'pendiente_pago',
        'pago_pendiente',
        'suspendida_pago',
        'suspendida',
        'bloqueada',
        'past_due',
        'unpaid',
    ];

    /**
     * Evita loops infinitos si algo de verificación/pago está mal configurado.
     */
    private const LOOP_GUARD_LIMIT = 2;

    public function handle(Request $request, Closure $next)
    {
        /**
         * 0) Rutas que SIEMPRE deben pasar (evita loops de login/verify/stripe/public pdf)
         */
        $routeName = $request->route()?->getName();

        if ($this->isAlwaysAllowedRoute($routeName, $request)) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();

        if (!$user) {
            return redirect()
                ->route('cliente.login')
                ->with('info', 'Debes iniciar sesión.');
        }

        // impersonation admin = dejar pasar todo
        if (session('impersonated_by_admin')) {
            return $next($request);
        }

        /**
         * CONTROL LOCAL: por defecto NO bloquea en local salvo que se fuerce.
         */
        $enforceLocal = filter_var(env('P360_ENFORCE_ACCOUNT_ACTIVE_LOCAL', false), FILTER_VALIDATE_BOOLEAN);
        if (App::environment(['local', 'development', 'testing']) && !$enforceLocal) {
            return $next($request);
        }

        // Usuario inactivo (si existe esa col en el user table)
        if ($this->columnExists('mysql_clientes', $user->getTable() ?? 'usuarios_cuenta', 'activo')) {
            if (!(bool) ($user->activo ?? 0)) {
                return $this->blockSoftToVerify(
                    $request,
                    'Tu usuario está inactivo. Verifica tu cuenta o contacta a soporte.'
                );
            }
        }

        // Forzar cambio de contraseña inicial
        if (method_exists($user, 'mustChangePassword') && $user->mustChangePassword()) {
            if (!$this->isFirstPasswordRoute($request)) {
                return redirect()
                    ->route('cliente.password.first')
                    ->with('info', 'Por seguridad, debes actualizar tu contraseña antes de continuar.');
            }
        }

        // Resolver cuenta_id
        $cuentaId = $user->cuenta_id ?? null;

        if (!$cuentaId && isset($user->cuenta)) {
            $rawCuenta = $user->cuenta;
            if (is_object($rawCuenta)) {
                $cuentaId = $rawCuenta->id ?? $rawCuenta->cuenta_id ?? null;
            } elseif (is_array($rawCuenta)) {
                $cuentaId = $rawCuenta['id'] ?? $rawCuenta['cuenta_id'] ?? null;
            }
        }

        if (!$cuentaId) {
            return $this->blockHard($request, 'No encontramos tu cuenta en el sistema. Contacta a soporte.');
        }

        // Si no existe cuentas_cliente -> compat
        try {
            if (!Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            return $next($request);
        }

        $cuentaInfo = $this->fetchCuentaClienteInfo((string) $cuentaId);
        if (!$cuentaInfo) {
            return $this->blockHard($request, 'No encontramos tu cuenta en el sistema. Contacta a soporte.');
        }

        $estadoCuentaCli = strtolower((string) ($cuentaInfo->estado_cuenta ?? ''));

        /**
         * 1) SOT ADMIN por RFC_PADRE (si existe)
         */
        $accAdmin = null;
        if (!empty($cuentaInfo->rfc_padre)) {
            $accAdmin = $this->fetchAdminAccountByRfcPadre((string) $cuentaInfo->rfc_padre);
        }

        $isBlockedAdmin    = $accAdmin ? ((int) ($accAdmin->is_blocked ?? 0) === 1) : false;
        $estadoCuentaAdmin = strtolower((string) ($accAdmin->estado_cuenta ?? ''));

        /**
         * Detectar FREE con TUS columnas reales.
         */
        $isFree = $this->isFreeAccount($cuentaInfo, $accAdmin, $estadoCuentaCli, $estadoCuentaAdmin);

        /**
         * Paywall PRO (solo si NO es FREE)
         */
        $blockedByPayment =
            $isBlockedAdmin
            || in_array($estadoCuentaAdmin, self::ESTADOS_PAGO, true)
            || in_array($estadoCuentaCli, self::ESTADOS_PAGO, true)
            || ((int) ($cuentaInfo->is_blocked ?? 0) === 1);

        if (!$isFree && $blockedByPayment) {
            // ✅ rutas permitidas aun bloqueado (pago, retorno, webhooks, pdf públicos, logout)
            if ($this->isPaywallAllowedRoute($routeName, $request)) {
                return $next($request);
            }

            // Ciclo: usa admin.modo_cobro si existe, sino clientes.modo_cobro
            $cycle = strtolower((string) (($accAdmin->modo_cobro ?? null) ?: ($cuentaInfo->modo_cobro ?? 'mensual')));
            $cycle = ($cycle === 'anual' || $cycle === 'annual' || $cycle === 'yearly') ? 'anual' : 'mensual';

            session([
                'paywall.account_id' => (int) ($accAdmin->id ?? ($cuentaInfo->admin_account_id ?? 0)),
                'paywall.cycle'      => $cycle,
                'paywall.email'      => (string) ($user->email ?? ($cuentaInfo->email ?? '')),
            ]);

            // ✅ Regla PRO: redirección silenciosa a paywall
            return redirect()->route('cliente.paywall');
        }

        /**
         * ✅ IMPORTANTE:
         * Primero validamos verificación admin (email / phone). Si falta, mandamos a verify.
         * Así evitamos que 'cuentas_cliente.estado_cuenta=pendiente' bloquee aunque ya esté verificado.
         */
        $requireEmail = filter_var(env('REQUIRE_EMAIL_VERIFIED', true), FILTER_VALIDATE_BOOLEAN);
        $requirePhone = filter_var(env('REQUIRE_PHONE_VERIFIED', true), FILTER_VALIDATE_BOOLEAN);

        if (($requireEmail || $requirePhone) && !empty($cuentaInfo->rfc_padre)) {
            $status = $this->checkAdminVerifications((string) $cuentaInfo->rfc_padre, $requireEmail, $requirePhone);
            if ($status !== true) {
                return $this->blockSoftToVerify($request, (string) $status);
            }
        }

        /**
         * ✅ Auto-heal de estado cliente:
         * Si NO hay paywall y ya pasó verificación, pero 'estado_cuenta' viene raro (pendiente/etc),
         * lo normalizamos y dejamos pasar.
         */
        if ($estadoCuentaCli !== '' && !in_array($estadoCuentaCli, self::ESTADOS_OK, true)) {
            $healed = $this->tryHealCuentaClienteEstado((string) $cuentaId, $estadoCuentaCli);

            if ($healed) {
                // si ya sanó, continuamos
                return $next($request);
            }

            // si no se pudo sanar, mandamos a verify (soft)
            return $this->blockSoftToVerify(
                $request,
                'Tu cuenta aún no está activa. Verifica tu correo y teléfono o completa el pago.'
            );
        }

        return $next($request);
    }

    /**
     * Rutas que deben pasar SIEMPRE para evitar loops.
     */
    private function isAlwaysAllowedRoute(?string $name, Request $request): bool
    {
        if (!$name) {
            return false;
        }

        $always = [
            'cliente.root',
            'cliente.login',
            'cliente.login.do',
            'cliente.logout',

            'cliente.registro.free',
            'cliente.registro.free.do',
            'cliente.registro.pro',
            'cliente.registro.pro.do',

            'cliente.verify.email.token',
            'cliente.verify.email.signed',
            'cliente.verify.email.resend',
            'cliente.verify.email.resend.do',

            'cliente.verify.phone',
            'cliente.verify.phone.update',
            'cliente.verify.phone.send',
            'cliente.verify.phone.check',

            'cliente.password.first',
            'cliente.password.first.store',
            'cliente.password.first.do',

            'cliente.ui.demo_mode',
            'cliente.ui.demo_mode.get',

            'cliente.checkout.success',
            'cliente.checkout.cancel',
            'cliente.stripe.webhook',

            'cliente.billing.publicPdf',
            'cliente.billing.publicPdfInline',
            'cliente.billing.publicPay',
        ];

        if (in_array($name, $always, true)) {
            return true;
        }

        if (Str::startsWith($name, 'cliente.billing.public')) {
            return true;
        }

        return false;
    }

    /**
     * ✅ FIX: método faltante
     * Permite pasar SOLO las rutas del flujo "first password" / reset / forgot
     * para evitar loops cuando mustChangePassword() es true.
     */
    private function isFirstPasswordRoute(Request $request): bool
    {
        try {
            $route = $request->route();
            $name  = is_object($route) ? (string) ($route->getName() ?? '') : '';
            $path  = ltrim((string) $request->path(), '/'); // ej: "cliente/password/first"
            $act   = is_object($route) ? (string) ($route->getActionName() ?? '') : '';

            // 1) Por nombre de ruta (ideal)
            if ($name !== '') {
                // Tus rutas explícitas
                if (Str::startsWith($name, 'cliente.password.first')) return true;

                // Forgot/reset (compat si existieran)
                if (Str::contains($name, ['password', 'forgot', 'reset'])) return true;

                // Variantes comunes
                if (Str::contains($name, 'first') && Str::contains($name, ['password', 'pass'])) return true;
            }

            // 2) Por path (fallback)
            if (
                Str::startsWith($path, 'cliente/password') ||
                Str::startsWith($path, 'cliente/forgot') ||
                Str::startsWith($path, 'cliente/reset') ||
                Str::startsWith($path, 'cliente/first-password') ||
                Str::startsWith($path, 'cliente/first_password') ||
                Str::startsWith($path, 'cliente/firstpass') ||
                Str::startsWith($path, 'cliente/first-pass')
            ) {
                return true;
            }

            // 3) Por action (fallback extra)
            if ($act !== '') {
                if (
                    Str::contains($act, 'PasswordController') &&
                    (Str::contains($act, ['first', 'forgot', 'reset']))
                ) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // ✅ FAIL-CLOSED: si truena el helper, NO asumimos que es ruta permitida
            return false;
        }

        return false;
    }

    /**
     * Rutas permitidas aun con paywall.
     */
    private function isPaywallAllowedRoute(?string $name, Request $request): bool
    {
        if (!$name) return false;

        $allowed = [
            'cliente.paywall',

            'cliente.checkout.pro.monthly',
            'cliente.checkout.pro.annual',

            'cliente.checkout.success',
            'cliente.checkout.cancel',
            'cliente.stripe.webhook',

            'cliente.estado_cuenta',
            'cliente.billing.pdfInline',
            'cliente.billing.pdf',
            'cliente.billing.pay',
            'cliente.billing.pay.get',
            'cliente.billing.factura.request',
            'cliente.billing.factura.download',

            'cliente.logout',

            'cliente.billing.publicPdf',
            'cliente.billing.publicPdfInline',
            'cliente.billing.publicPay',
        ];

        if (in_array($name, $allowed, true)) {
            return true;
        }

        $p = '/' . ltrim($request->path(), '/');
        if (Str::contains($p, ['/stripe/webhook', '/checkout/success', '/checkout/cancel'])) {
            return true;
        }

        return false;
    }

    private function isFreeAccount(object $cuentaInfo, ?object $accAdmin, string $estadoCli, string $estadoAdmin): bool
    {
        $signals = ['free', 'gratis', 'trial', 'demo'];

        if (in_array($estadoCli, $signals, true) || in_array($estadoAdmin, $signals, true)) {
            return true;
        }

        $planCli = strtolower((string) ($cuentaInfo->plan_actual ?? ''));
        if ($planCli !== '' && in_array($planCli, $signals, true)) {
            return true;
        }

        if ($accAdmin) {
            foreach (['plan', 'tipo_plan', 'plan_codigo', 'plan_code', 'account_plan'] as $col) {
                if (property_exists($accAdmin, $col)) {
                    $v = strtolower((string) ($accAdmin->{$col} ?? ''));
                    if ($v !== '' && in_array($v, $signals, true)) return true;
                }
            }
        }

        return false;
    }

    private function columnExists(string $connection, string $table, string $col): bool
    {
        try {
            return Schema::connection($connection)->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function fetchCuentaClienteInfo(string $cuentaId): ?object
    {
        try {
            $schema = Schema::connection('mysql_clientes');
            $cols   = $schema->getColumnListing('cuentas_cliente');
            $lc     = array_map('strtolower', $cols);
            $has    = fn(string $c) => in_array(strtolower($c), $lc, true);

            $select = [
                'id',
                $has('rfc_padre') ? 'rfc_padre' : DB::raw('NULL as rfc_padre'),
                $has('estado_cuenta') ? 'estado_cuenta' : DB::raw("'' as estado_cuenta"),
                $has('modo_cobro') ? 'modo_cobro' : DB::raw("'' as modo_cobro"),
                $has('admin_account_id') ? 'admin_account_id' : DB::raw('NULL as admin_account_id'),
                $has('is_blocked') ? 'is_blocked' : DB::raw('0 as is_blocked'),
                $has('plan_actual') ? 'plan_actual' : DB::raw("'' as plan_actual"),
                $has('email') ? 'email' : DB::raw("'' as email"),
            ];

            return DB::connection('mysql_clientes')
                ->table('cuentas_cliente')
                ->select($select)
                ->where('id', $cuentaId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchAdminAccountByRfcPadre(string $rfcPadre): ?object
    {
        $rfcPadre = strtoupper(trim($rfcPadre));
        if ($rfcPadre === '') return null;

        try {
            if (!Schema::connection('mysql_admin')->hasTable('accounts')) {
                return null;
            }

            $schema = Schema::connection('mysql_admin');
            $cols   = $schema->getColumnListing('accounts');
            $lc     = array_map('strtolower', $cols);
            $has    = fn(string $c) => in_array(strtolower($c), $lc, true);

            $select = [
                'id',
                $has('rfc') ? 'rfc' : DB::raw('NULL as rfc'),
                $has('is_blocked') ? 'is_blocked' : DB::raw('0 as is_blocked'),
                $has('estado_cuenta') ? 'estado_cuenta' : DB::raw("'' as estado_cuenta"),
                $has('modo_cobro') ? 'modo_cobro' : DB::raw("'' as modo_cobro"),
                $has('email') ? 'email' : DB::raw("'' as email"),
                $has('plan') ? 'plan' : DB::raw("'' as plan"),
            ];

            return DB::connection('mysql_admin')
                ->table('accounts')
                ->select($select)
                ->whereRaw('UPPER(rfc)=?', [$rfcPadre])
                ->orderByDesc('id')
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

            $acc = $connAdmin->table('accounts')
                ->whereRaw('UPPER(rfc)=?', [strtoupper($rfcPadre)])
                ->orderByDesc('id')
                ->select([
                    'id',
                    'rfc',
                    $emailCol . ' as email',
                    $hasEmailVerifiedAt ? 'email_verified_at' : DB::raw('NULL as email_verified_at'),
                    $hasPhoneVerifiedAt ? 'phone_verified_at' : DB::raw('NULL as phone_verified_at'),
                ])
                ->first();

            if (!$acc) return true;

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
            if ($schemaAdmin->hasColumn('accounts', 'correo_contacto')) return 'correo_contacto';
            if ($schemaAdmin->hasColumn('accounts', 'email')) return 'email';
        } catch (\Throwable $e) {
        }
        return 'email';
    }

    private function tryHealCuentaClienteEstado(string $cuentaId, string $estadoActual): bool
    {
        // Si viene vacío, no hacemos nada aquí (porque podría ser compat)
        if ($estadoActual === '') return false;

        // Solo sanamos si la tabla/col existen
        try {
            if (!Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) return false;
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'estado_cuenta')) return false;
        } catch (\Throwable) {
            return false;
        }

        // Normalización simple: lo ponemos en "operando" (ya está en ESTADOS_OK).
        // Si prefieres "activa", cambia a 'activa'.
        try {
            DB::connection('mysql_clientes')
                ->table('cuentas_cliente')
                ->where('id', $cuentaId)
                ->update([
                    'estado_cuenta' => 'operando',
                    'updated_at'    => now(),
                ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function blockHard(Request $request, string $msg)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $msg], 403);
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
     * ✅ FIX LOOP + ✅ FIX PROD:
     * Cuando hay que “sacar” al usuario a verificación, cerramos sesión para salir del middleware auth.
     * Pero NO debemos perder verify.account_id / verify.email, porque verify.phone es guest y necesita resolver account_id.
     */
    private function blockSoftToVerify(Request $request, string $msg)
    {
        $count = (int) $request->session()->get('block_loop_count', 0);
        if ($count >= self::LOOP_GUARD_LIMIT) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 403);
            }
            return abort(403, $msg);
        }
        $request->session()->put('block_loop_count', $count + 1);

        $isPhoneMsg = Str::contains(Str::lower($msg), ['teléfono', 'telefono', 'whatsapp', 'sms']);
        $isEmailMsg = Str::contains(Str::lower($msg), ['correo', 'email']);

        // ✅ PRESERVAR datos críticos ANTES de invalidar
        $keep = [
            'verify.account_id' => (int) $request->session()->get('verify.account_id', 0),
            'verify.email'      => (string) $request->session()->get('verify.email', ''),
        ];

        // ✅ logout controlado para salir del middleware auth y evitar guest-loop
        try {
            Auth::guard('web')->logout();

            // invalidamos para evitar sesión sucia, pero luego restauramos verify.*
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // ✅ RESTAURAR verify.* después del invalidate
            if (!empty($keep['verify.account_id'])) {
                $request->session()->put('verify.account_id', (int) $keep['verify.account_id']);
            }
            if (!empty($keep['verify.email'])) {
                $request->session()->put('verify.email', (string) $keep['verify.email']);
            }

            // best-effort
            try { $request->session()->save(); } catch (\Throwable) {}
        } catch (\Throwable $e) {
            // no-op
        }

        $aid = (int) ($keep['verify.account_id'] ?? 0);

        if ($isEmailMsg) {
            return redirect()
                ->route('cliente.verify.email.resend', $aid > 0 ? ['account_id' => $aid] : [])
                ->with('error', $msg)
                ->with('need_verify', true);
        }

        if ($isPhoneMsg) {
            return redirect()
                ->route('cliente.verify.phone', $aid > 0 ? ['account_id' => $aid] : [])
                ->with('error', $msg)
                ->with('need_verify', true);
        }

        return redirect()
            ->route('cliente.verify.phone', $aid > 0 ? ['account_id' => $aid] : [])
            ->with('error', $msg)
            ->with('need_verify', true);
    }
}
