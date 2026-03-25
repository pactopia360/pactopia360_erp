<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class EnsureSatVaultV2Access
{
    private const CONN_CLIENTES = 'mysql_clientes';
    private const CONN_ADMIN    = 'mysql_admin';

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return redirect()->route('cliente.login');
        }

        // ✅ En local/desarrollo/testing NO bloqueamos.
        if (app()->environment(['local', 'development', 'testing'])) {
            return $next($request);
        }

        if (session('impersonated_by_admin')) {
            return $next($request);
        }

        $cuentaId = (string) ($user->cuenta_id ?? '');
        if ($cuentaId === '' && isset($user->cuenta)) {
            $raw = $user->cuenta;
            if (is_object($raw)) {
                $cuentaId = (string) ($raw->id ?? $raw->cuenta_id ?? '');
            } elseif (is_array($raw)) {
                $cuentaId = (string) ($raw['id'] ?? $raw['cuenta_id'] ?? '');
            }
        }

        $usuarioId = (string) ($user->id ?? '');

        if ($cuentaId === '' || $usuarioId === '') {
            return $this->deny($request, 'No se pudo resolver la cuenta o el usuario para SAT Bóveda v2.');
        }

        if (!$this->accountHasModule($cuentaId)) {
            return $this->deny($request, 'La cuenta no tiene habilitado el módulo SAT Bóveda v2.');
        }

        if (!$this->userHasAccess($cuentaId, $usuarioId)) {
            return $this->deny($request, 'Tu usuario no tiene acceso a SAT Bóveda v2.');
        }

        return $next($request);
    }

    private function accountHasModule(string $cuentaId): bool
    {
        try {
            if (!Schema::connection(self::CONN_CLIENTES)->hasTable('cuentas_cliente')) {
                return false;
            }

            $cuenta = DB::connection(self::CONN_CLIENTES)
                ->table('cuentas_cliente')
                ->select(['id', 'admin_account_id'])
                ->where('id', $cuentaId)
                ->first();

            if (!$cuenta) {
                return false;
            }

            $adminAccountId = (int) ($cuenta->admin_account_id ?? 0);
            if ($adminAccountId <= 0) {
                return false;
            }

            if (!Schema::connection(self::CONN_ADMIN)->hasTable('accounts')) {
                return false;
            }

            if (!Schema::connection(self::CONN_ADMIN)->hasColumn('accounts', 'meta')) {
                return false;
            }

            $row = DB::connection(self::CONN_ADMIN)
                ->table('accounts')
                ->select(['id', 'meta'])
                ->where('id', $adminAccountId)
                ->first();

            if (!$row) {
                return false;
            }

            $meta = [];
            try {
                $meta = is_array($row->meta)
                    ? $row->meta
                    : (json_decode((string) $row->meta, true) ?: []);
            } catch (\Throwable) {
                $meta = [];
            }

            $state = strtolower((string) data_get($meta, 'modules_state.sat_boveda_v2', ''));
            if ($state !== '') {
                return $state === 'active';
            }

            return (bool) data_get($meta, 'modules.sat_boveda_v2', false);
        } catch (\Throwable) {
            return false;
        }
    }

    private function userHasAccess(string $cuentaId, string $usuarioId): bool
    {
        try {
            if (!Schema::connection(self::CONN_CLIENTES)->hasTable('sat_user_access')) {
                return false;
            }

            $row = DB::connection(self::CONN_CLIENTES)
                ->table('sat_user_access')
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->where('can_access_vault', 1)
                ->first();

            return (bool) $row;
        } catch (\Throwable) {
            return false;
        }
    }

    private function deny(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => false,
                'message' => $message,
            ], 403);
        }

        if (Route::has('cliente.sat.index')) {
            return redirect()
                ->route('cliente.sat.index')
                ->with('error', $message)
                ->with('sat_vault_v2_blocked', true);
        }

        return redirect()
            ->route('cliente.home')
            ->with('error', $message)
            ->with('sat_vault_v2_blocked', true);
    }
}