<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Cliente\UsuarioCuenta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PortalSessionBootstrap
{
    public function handle(Request $request, Closure $next)
    {
        // Normaliza el path (sin dominio)
        $path = ltrim($request->path(), '/');

        /*
        |--------------------------------------------------------------------------
        | ADMIN CONTEXT
        |--------------------------------------------------------------------------
        */
        if ($path === 'admin' || str_starts_with($path, 'admin/')) {

            Config::set('session.cookie', 'p360_admin_session');
            Config::set('auth.defaults.guard', 'admin');
            Config::set('auth.defaults.passwords', 'admins');

            try { Auth::shouldUse('admin'); } catch (\Throwable $e) {}

            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | CLIENTE CONTEXT
        |--------------------------------------------------------------------------
        */
        if ($path === 'cliente' || str_starts_with($path, 'cliente/')) {

            Config::set('session.cookie', 'p360_client_session');

            // ✅ Guard correcto para portal cliente
            Config::set('auth.defaults.guard', 'web');
            Config::set('auth.defaults.passwords', 'clientes');

            // ✅ Blindaje del provider/model REAL usado por el portal cliente (usuarios_cuenta en mysql_clientes)
            // (Aunque venga de config/auth.php, lo dejamos explícito para evitar regresiones por cache/env.)
            Config::set('auth.providers.clientes.driver', 'eloquent');
            Config::set('auth.providers.clientes.model', UsuarioCuenta::class);

            try { Auth::shouldUse('web'); } catch (\Throwable $e) {}

            /*
            |--------------------------------------------------------------------------
            | AUTO-HIDRATAR CONTEXTO DE CUENTA PARA MÓDULOS (Billing/Home/Estado de cuenta)
            |
            | Objetivos:
            | - Guardar cuenta cliente (UUID) en session: cliente.cuenta_id (+ aliases)
            | - Resolver admin_account_id (mysql_admin.accounts.id) desde rfc_padre y guardarlo en session:
            |   cliente.account_id / client.account_id / account_id (compatibilidad)
            |--------------------------------------------------------------------------
            */
            if (Auth::guard('web')->check()) {

                /** @var \App\Models\Cliente\UsuarioCuenta $user */
                $user = Auth::guard('web')->user();

                // 1) Resolver cuenta_id (UUID) desde usuarios_cuenta.cuenta_id
                $cuentaId = (string)($user->cuenta_id ?? '');
                if ($cuentaId !== '' && !session()->has('cliente.cuenta_id')) {

                    session()->put([
                        // Namespace "cliente"
                        'cliente.cuenta_id' => $cuentaId,

                        // Aliases que ya viste en logs (compatibilidad)
                        'client.cuenta_id'  => $cuentaId,
                        'cuenta_id'         => $cuentaId,
                    ]);
                }

                // 2) Cargar objeto de cuenta cliente (cuentas_cliente o fallback)
                //    (Esto es útil para UI y para resolver rfc_padre si hace falta.)
                if ($cuentaId !== '' && !session()->has('cliente.cuenta')) {

                    $cuenta = null;

                    try {
                        if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                            $cuenta = DB::connection('mysql_clientes')
                                ->table('cuentas_cliente')
                                ->where('id', $cuentaId)
                                ->first();
                        }

                        if (!$cuenta && Schema::connection('mysql_clientes')->hasTable('cuentas')) {
                            $cuenta = DB::connection('mysql_clientes')
                                ->table('cuentas')
                                ->where('id', $cuentaId)
                                ->first();
                        }
                    } catch (\Throwable $e) {
                        $cuenta = null;
                    }

                    if ($cuenta) {
                        session()->put([
                            'cliente.cuenta' => (object)$cuenta,
                            'client.cuenta'  => (object)$cuenta,
                        ]);
                    }
                }

                // 3) Resolver admin_account_id para Billing/Home/Estado de cuenta (mysql_admin.accounts.id)
                //    Si ya existe, no lo recalculamos.
                if (!session()->has('cliente.account_id') && !session()->has('client.account_id') && !session()->has('account_id')) {

                    $adminId = 0;

                    // Intento A: si el usuario trae account_id (algunos esquemas legacy)
                    $maybeAccountId = $user->account_id ?? null;
                    if (is_numeric($maybeAccountId)) {
                        $adminId = (int)$maybeAccountId;
                    }

                    // Intento B: mapear por RFC desde cuentas_cliente.rfc_padre/rfc hacia mysql_admin.accounts
                    if ($adminId <= 0) {

                        $cuentaObj = session('cliente.cuenta') ?: session('client.cuenta');

                        $rfcPadre = '';
                        if (is_object($cuentaObj)) {
                            $rfcPadre = (string)($cuentaObj->rfc_padre ?? $cuentaObj->rfc ?? '');
                        }

                        $rfcPadre = Str::upper(trim($rfcPadre));

                        if ($rfcPadre !== '') {
                            try {
                                $acc = DB::connection('mysql_admin')
                                    ->table('accounts')
                                    ->whereRaw('UPPER(COALESCE(rfc, "")) = ?', [$rfcPadre])
                                    ->first();

                                if ($acc && isset($acc->id)) {
                                    $adminId = (int)$acc->id;
                                }
                            } catch (\Throwable $e) {
                                $adminId = 0;
                            }
                        }
                    }

                    if ($adminId > 0) {
                        session()->put([
                            // Namespace "cliente"
                            'cliente.account_id' => $adminId,

                            // Aliases que ya viste en logs / módulos
                            'client.account_id'  => $adminId,
                            'account_id'         => $adminId,
                        ]);
                    }
                }
            }

            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | RESTO DEL SITIO
        |--------------------------------------------------------------------------
        */
        return $next($request);
    }
}
