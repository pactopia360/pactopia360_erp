<?php

declare(strict_types=1);

namespace App\Http\Middleware\Cliente;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SyncAdminModulesState
{
    /**
     * Sincroniza módulos (SOT Admin) hacia sesión del Cliente.
     *
     * Fuente: mysql_admin.accounts.meta
     *  - meta.modules_state (preferente) => key => active|inactive|hidden|blocked
     *  - meta.modules (legacy)           => key => bool (true=active, false=inactive)
     *
     * Destino sesión:
     *  - p360.modules_state   (array key => state)
     *  - p360.modules_visible (array key => bool)
     *  - p360.modules_access  (array key => bool)
     *  - p360.admin_account_id
     */
    public function handle(Request $request, Closure $next)
    {
        $u = Auth::guard('web')->user();
        if (!$u) return $next($request);

        $cuenta = $u->cuenta;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        if (!Schema::connection($adm)->hasTable('accounts')) {
            return $next($request);
        }

        // TTL corto para no pegarle a DB cada request
        $ttlSec = (int) (config('p360.modules_state_ttl', 60));
        $nowTs  = time();

        $lastSync = (int) $request->session()->get('p360.modules_state_synced_at', 0);
        if ($lastSync > 0 && ($nowTs - $lastSync) < $ttlSec) {
            return $next($request);
        }

        // Resolver admin_account_id (SOT)
        $adminId = $cuenta->admin_account_id ?? null;
        $rfc     = $cuenta->rfc_padre ?? $cuenta->rfc ?? null;

        if (!$adminId && $rfc && Schema::connection($adm)->hasColumn('accounts', 'rfc')) {
            $acc = DB::connection($adm)->table('accounts')
                ->select('id')
                ->where('rfc', strtoupper((string)$rfc))
                ->first();
            if ($acc) $adminId = (int) $acc->id;
        }

        if (!$adminId) {
            $request->session()->put('p360.modules_state_synced_at', $nowTs);
            return $next($request);
        }

        if (!Schema::connection($adm)->hasColumn('accounts', 'meta')) {
            $request->session()->put('p360.modules_state_synced_at', $nowTs);
            return $next($request);
        }

        $row = DB::connection($adm)->table('accounts')
            ->select('id', 'meta', 'updated_at')
            ->where('id', (int)$adminId)
            ->first();

        if (!$row) {
            $request->session()->put('p360.modules_state_synced_at', $nowTs);
            return $next($request);
        }

        $meta = $this->decodeMeta($row->meta ?? null);

        // 1) Preferente: modules_state
        $modsState = $meta['modules_state'] ?? $meta['modulesState'] ?? null;

        // 2) Fallback legacy: modules bool
        $modsLegacy = $meta['modules'] ?? null;

        $outState = [];

        if (is_array($modsState)) {
            foreach ($modsState as $k => $v) {
                $key = is_string($k) ? trim($k) : '';
                if ($key === '') continue;

                $state = strtolower(trim((string)$v));
                if (!in_array($state, ['active','inactive','hidden','blocked'], true)) {
                    $state = 'active';
                }
                $outState[$key] = $state;
            }
        } elseif (is_array($modsLegacy)) {
            foreach ($modsLegacy as $k => $v) {
                $key = is_string($k) ? trim($k) : '';
                if ($key === '') continue;
                $outState[$key] = ((bool)$v) ? 'active' : 'inactive';
            }
        }

        // Derivados para UI (sidebar usa session('p360.modules_*'))
        $visible = [];
        $access  = [];

        foreach ($outState as $k => $st) {
            $visible[$k] = ($st !== 'hidden');
            $access[$k]  = ($st === 'active');
        }

        $request->session()->put('p360.admin_account_id', (int)$adminId);
        $request->session()->put('p360.modules_state', $outState);
        $request->session()->put('p360.modules_visible', $visible);
        $request->session()->put('p360.modules_access', $access);
        $request->session()->put('p360.modules_state_synced_at', $nowTs);

        if (property_exists($row, 'updated_at') && $row->updated_at) {
            $request->session()->put('p360.admin_account_updated_at', (string)$row->updated_at);
        }

        return $next($request);
    }

    private function decodeMeta($meta): array
    {
        if (is_array($meta)) return $meta;
        if (is_object($meta)) return (array) $meta;

        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
        }
        return [];
    }
}
