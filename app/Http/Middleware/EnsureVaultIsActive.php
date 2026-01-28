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
            if (!Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            return $next($request);
        }

        $cuenta = $this->fetchCuentaVaultInfo((string) $cuentaId);

        if (!$cuenta) {
            return $this->deny($request, 'No se pudo validar el estado de tu bóveda.');
        }

        // Tus columnas reales:
        $vaultActive = (int) ($cuenta->vault_active ?? 0) === 1;
        $quotaBytes  = (int) ($cuenta->vault_quota_bytes ?? 0);

        // Regla base (simple y correcta con tu schema):
        // Si está marcada activa o tiene cuota > 0, se permite.
        if ($vaultActive || $quotaBytes > 0) {
            return $next($request);
        }

        /**
         * Opcional: Validación por pago VAULT vigente.
         * Si quieres que la bóveda se “habilite” con un pago aunque vault_active esté en 0,
         * entonces revisamos sat_downloads.
         */
        $hasPaidVault = $this->hasValidPaidVault((string) $cuentaId);

        if ($hasPaidVault) {
            // habilitamos “en caliente” para no bloquear al usuario
            try {
                DB::connection('mysql_clientes')
                    ->table('cuentas_cliente')
                    ->where('id', $cuentaId)
                    ->update(['vault_active' => 1]);
            } catch (\Throwable $e) {
                // si falla el update, igual dejamos pasar por el pago
            }

            return $next($request);
        }

        return $this->deny($request, 'Bóveda no activa. Completa la activación o contacta a soporte.');
    }

    private function fetchCuentaVaultInfo(string $cuentaId): ?object
    {
        try {
            $schema = Schema::connection('mysql_clientes');
            $cols   = $schema->getColumnListing('cuentas_cliente');
            $lc     = array_map('strtolower', $cols);
            $has    = fn(string $c) => in_array(strtolower($c), $lc, true);

            $select = [
                'id',
                $has('vault_active') ? 'vault_active' : DB::raw('0 as vault_active'),
                $has('vault_quota_bytes') ? 'vault_quota_bytes' : DB::raw('0 as vault_quota_bytes'),
                $has('vault_used_bytes') ? 'vault_used_bytes' : DB::raw('0 as vault_used_bytes'),
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

    private function hasValidPaidVault(string $cuentaId): bool
    {
        try {
            if (!Schema::connection('mysql_clientes')->hasTable('sat_downloads')) {
                return false;
            }

            // OJO: en tu tabla sat_downloads:
            // tipo='VAULT', status='PAID', expires_at existe.
            $row = DB::connection('mysql_clientes')
                ->table('sat_downloads')
                ->select(['id'])
                ->where('cuenta_id', $cuentaId)
                ->where('tipo', 'VAULT')
                ->where('status', 'PAID')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>=', now());
                })
                ->orderByDesc('paid_at')
                ->first();

            return (bool) $row;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function deny(Request $request, string $msg)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $msg], 402);
        }

        // ✅ Anti-loop definitivo: si estamos en la ruta de bóveda y se niega,
        // devolvemos un 403 con una vista clara en lugar de redirigir.
        $routeName = optional($request->route())->getName();
        if ($routeName === 'cliente.sat.vault' || str_starts_with((string)$routeName, 'cliente.sat.vault.')) {
            return response()->view('errors.vault_blocked', ['message' => $msg], 403);
        }

        // ✅ Fallback seguro fuera de bóveda (evita loops)
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
