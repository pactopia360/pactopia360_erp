<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnsureVaultIsActive
{
    private const CONN = 'mysql_clientes';

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return redirect()->route('cliente.login');
        }

        // Bypass por impersonation
        if (session('impersonated_by_admin')) {
            return $next($request);
        }

        // ✅ Bypass solo en local si se pide explícito
        if (app()->environment(['local', 'development', 'testing']) && config('services.sat.vault.force_enabled', false)) {
            return $next($request);
        }

        // Resolver cuenta_id de forma tolerante
        $cuentaId = $user->cuenta_id ?? null;

        if (!$cuentaId && isset($user->cuenta)) {
            $raw = $user->cuenta;
            if (is_object($raw)) {
                $cuentaId = $raw->id ?? $raw->cuenta_id ?? null;
            } elseif (is_array($raw)) {
                $cuentaId = $raw['id'] ?? $raw['cuenta_id'] ?? null;
            }
        }

        if (!$cuentaId) {
            return $this->deny($request, 'No encontramos tu cuenta para validar la bóveda.');
        }

        // Si no existe la tabla, no bloqueamos duro (compat)
        try {
            if (!Schema::connection(self::CONN)->hasTable('cuentas_cliente')) {
                return $next($request);
            }
        } catch (\Throwable) {
            return $next($request);
        }

        $cuenta = $this->fetchCuentaVaultInfo((string)$cuentaId);

        if (!$cuenta) {
            return $this->deny($request, 'No se pudo validar el estado de tu bóveda.');
        }

        $vaultActive = ((int)($cuenta->vault_active ?? 0)) === 1;
        $quotaBytes  = (int)($cuenta->vault_quota_bytes ?? 0);

        // ✅ Regla 1: flag o cuota
        if ($vaultActive || $quotaBytes > 0) {
            return $next($request);
        }

        // ✅ Regla 2: plan PRO con base_gb_pro > 0 (alineado a VaultController)
        $planRaw   = (string)($cuenta->plan_actual ?? 'FREE');
        $plan      = strtoupper(trim($planRaw));
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        $baseGbPro = (float) config('services.sat.vault.base_gb_pro', 0.0);
        if ($isProPlan && $baseGbPro > 0) {
            // si el plan ya habilita bóveda, dejamos pasar aunque no haya cuota precalculada
            return $next($request);
        }

        // ✅ Regla 3: compra VAULT pagada (y/o sin expires_at)
        if ($this->hasValidPaidVault((string)$cuentaId)) {
            // habilitamos “en caliente” para no bloquear al usuario
            try {
                $upd = ['updated_at' => now()];

                // si existe vault_active lo prendemos
                if (Schema::connection(self::CONN)->hasColumn('cuentas_cliente', 'vault_active')) {
                    $upd['vault_active'] = 1;
                }

                DB::connection(self::CONN)->table('cuentas_cliente')->where('id', $cuentaId)->update($upd);
            } catch (\Throwable) {
                // no-op
            }

            return $next($request);
        }

        return $this->deny($request, 'Bóveda no activa. Completa la activación o contacta a soporte.');
    }

    private function fetchCuentaVaultInfo(string $cuentaId): ?object
    {
        try {
            $schema = Schema::connection(self::CONN);
            $cols   = $schema->getColumnListing('cuentas_cliente');
            $lc     = array_map('strtolower', $cols);
            $has    = fn(string $c) => in_array(strtolower($c), $lc, true);

            $select = [
                'id',
                $has('plan_actual') ? 'plan_actual' : DB::raw("'FREE' as plan_actual"),
                $has('vault_active') ? 'vault_active' : DB::raw('0 as vault_active'),
                $has('vault_quota_bytes') ? 'vault_quota_bytes' : DB::raw('0 as vault_quota_bytes'),
                $has('vault_used_bytes') ? 'vault_used_bytes' : DB::raw('0 as vault_used_bytes'),
            ];

            return DB::connection(self::CONN)
                ->table('cuentas_cliente')
                ->select($select)
                ->where('id', $cuentaId)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    private function hasValidPaidVault(string $cuentaId): bool
    {
        try {
            if (!Schema::connection(self::CONN)->hasTable('sat_downloads')) {
                return false;
            }

            $qb = DB::connection(self::CONN)
                ->table('sat_downloads')
                ->select(['id'])
                ->where('cuenta_id', $cuentaId)
                ->where(function ($q) {
                    $q->where('tipo', 'VAULT')->orWhere('tipo', 'BOVEDA');
                })
                ->where(function ($q) {
                    $q->whereNotNull('paid_at')->orWhereIn('status', ['PAID', 'paid', 'PAGADO', 'pagado']);
                })
                ->where(function ($q) {
                    // si existe expires_at, lo respetamos; si no existe, no filtramos
                    if (Schema::connection(self::CONN)->hasColumn('sat_downloads', 'expires_at')) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                    } else {
                        $q->whereRaw('1=1');
                    }
                })
                ->orderByDesc('paid_at');

            return (bool) $qb->first();
        } catch (\Throwable) {
            return false;
        }
    }

    private function deny(Request $request, string $msg)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $msg], 402);
        }

        $routeName = optional($request->route())->getName();
        if ($routeName === 'cliente.sat.vault' || str_starts_with((string)$routeName, 'cliente.sat.vault.')) {
            return response()->view('errors.vault_blocked', ['message' => $msg], 403);
        }

        if (\Illuminate\Support\Facades\Route::has('cliente.sat.index')) {
            return redirect()
                ->route('cliente.sat.index')
                ->with('vault_blocked', true)
                ->with('error', $msg);
        }

        return redirect()
            ->route('cliente.home')
            ->with('vault_blocked', true)
            ->with('error', $msg);
    }
}
