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
         *    OJO: aquí usamos nombres con prefijo "cliente." porque tu RouteServiceProvider normalmente lo aplica.
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
         * Estado cliente NO OK => verificación (soft)
         */
        if ($estadoCuentaCli !== '' && !in_array($estadoCuentaCli, self::ESTADOS_OK, true)) {
            return $this->blockSoftToVerify(
                $request,
                'Tu cuenta aún no está activa. Verifica tu correo y teléfono o completa el pago.'
            );
        }

        /**
         * Verificación global admin (email / phone)
         */
        $requireEmail = filter_var(env('REQUIRE_EMAIL_VERIFIED', true), FILTER_VALIDATE_BOOLEAN);
        $requirePhone = filter_var(env('REQUIRE_PHONE_VERIFIED', true), FILTER_VALIDATE_BOOLEAN);

        if (($requireEmail || $requirePhone) && !empty($cuentaInfo->rfc_padre)) {
            $status = $this->checkAdminVerifications((string) $cuentaInfo->rfc_padre, $requireEmail, $requirePhone);
            if ($status !== true) {
                return $this->blockSoftToVerify($request, (string) $status);
            }
        }

        return $next($request);
    }

    /**
     * Rutas que deben pasar SIEMPRE para evitar loops:
     * - auth guest endpoints (login/registro)
     * - verificación (email/phone)
     * - password first
     * - ui helpers
     * - stripe callbacks/webhook
     * - pdf/pago públicos firmados
     */
    private function isAlwaysAllowedRoute(?string $name, Request $request): bool
    {
        if (!$name) {
            // si no hay nombre de ruta, no provocamos loops por seguridad
            return false;
        }

        $always = [
            // Root / Auth
            'cliente.root',
            'cliente.login',
            'cliente.login.do',
            'cliente.logout',

            'cliente.registro.free',
            'cliente.registro.free.do',
            'cliente.registro.pro',
            'cliente.registro.pro.do',

            // Verify (guest en tu archivo de rutas; aquí evitamos loops y, si hace falta, hacemos logout al redirigir)
            'cliente.verify.email.token',
            'cliente.verify.email.signed',
            'cliente.verify.email.resend',
            'cliente.verify.email.resend.do',

            'cliente.verify.phone',
            'cliente.verify.phone.update',
            'cliente.verify.phone.send',
            'cliente.verify.phone.check',

            // First password
            'cliente.password.first',
            'cliente.password.first.store',
            'cliente.password.first.do',

            // UI helpers
            'cliente.ui.demo_mode',
            'cliente.ui.demo_mode.get',

            // Stripe callbacks/webhook
            'cliente.checkout.success',
            'cliente.checkout.cancel',
            'cliente.stripe.webhook',

            // Public signed links
            'cliente.billing.publicPdf',
            'cliente.billing.publicPdfInline',
            'cliente.billing.publicPay',
        ];

        if (in_array($name, $always, true)) {
            return true;
        }

        // También permitimos cualquier ruta "billing.public*" por seguridad
        if (Str::startsWith($name, 'cliente.billing.public')) {
            return true;
        }

        return false;
    }

    /**
     * Rutas permitidas aun con paywall (para evitar bloqueos de retorno/pago).
     */
    private function isPaywallAllowedRoute(?string $name, Request $request): bool
    {
        if (!$name) return false;

        $allowed = [
            'cliente.paywall',

            // checkout pro (si existen en tu StripeController)
            'cliente.checkout.pro.monthly',
            'cliente.checkout.pro.annual',

            'cliente.checkout.success',
            'cliente.checkout.cancel',
            'cliente.stripe.webhook',

            // estado de cuenta / pago
            'cliente.estado_cuenta',
            'cliente.billing.pdfInline',
            'cliente.billing.pdf',
            'cliente.billing.pay',
            'cliente.billing.pay.get',
            'cliente.billing.factura.request',
            'cliente.billing.factura.download',

            'cliente.logout',

            // public signed
            'cliente.billing.publicPdf',
            'cliente.billing.publicPdfInline',
            'cliente.billing.publicPay',
        ];

        if (in_array($name, $allowed, true)) {
            return true;
        }

        // si por alguna razón llegan a endpoints de stripe por path
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
     * ✅ FIX LOOP:
     * Tus pantallas de verify están en grupo guest, pero aquí estamos en rutas auth.
     * Si intentamos redirigir a verify.* estando autenticado, guest:web te manda de regreso a home
     * y account.active vuelve a redirigir a verify => LOOP.
     *
     * Solución: cuando hay que “sacar” al usuario a verificación, cerramos sesión de forma controlada
     * y lo mandamos a verify.* ya como invitado.
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

        // ✅ logout controlado para salir del middleware auth y evitar guest-loop
        try {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Throwable $e) {
            // no-op
        }

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

        return redirect()
            ->route('cliente.verify.phone')
            ->with('error', $msg)
            ->with('need_verify', true);
    }

    private function isFirstPasswordRoute(Request $request): bool
    {
        $name = $request->route()?->getName();

        // En tus rutas existen:
        // cliente.password.first
        // cliente.password.first.store
        // cliente.password.first.do
        return in_array($name, [
            'cliente.password.first',
            'cliente.password.first.store',
            'cliente.password.first.do',
        ], true);
    }
}
