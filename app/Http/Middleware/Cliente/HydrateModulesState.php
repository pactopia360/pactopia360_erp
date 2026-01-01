<?php

declare(strict_types=1);

namespace App\Http\Middleware\Cliente;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class HydrateModulesState
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();
        if (!$user) return $next($request);

        // Si ya está hidratado, no repetir
        if ($request->session()->get('p360.modules_hydrated') === true) {
            return $next($request);
        }

        $clientId  = $user->cliente_id ?? $user->id_cliente ?? $user->id ?? null;
        $accountId = $user->cuenta_id ?? $user->account_id ?? $user->id_cuenta ?? null;

        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        $payload = $this->fetchFromAdminSot($adm, $clientId, $accountId);

        $state   = is_array($payload['state']   ?? null) ? $payload['state']   : [];
        $visible = is_array($payload['visible'] ?? null) ? $payload['visible'] : [];
        $access  = is_array($payload['access']  ?? null) ? $payload['access']  : [];
        $legacy  = is_array($payload['legacy']  ?? null) ? $payload['legacy']  : [];

        // 1) Si vino legacy (bools), úsalo como base
        //    legacy: ['sat_descargas'=>true, 'facturacion'=>false, ...]
        $modulesBool = [];
        if ($legacy) {
            foreach ($legacy as $k => $on) {
                $modulesBool[(string)$k] = (bool)$on;
            }
        }

        // 2) Si vino state (active/inactive/hidden), deriva access/visible si faltan
        if ($state) {
            foreach ($state as $k => $st) {
                $k  = (string)$k;
                $st = strtolower(trim((string)$st));

                if (!array_key_exists($k, $visible)) {
                    $visible[$k] = ($st !== 'hidden');
                }
                if (!array_key_exists($k, $access)) {
                    $access[$k] = ($st === 'active');
                }

                // Si sidebar legacy no existe, lo derivamos de access:
                if (!array_key_exists($k, $modulesBool)) {
                    $modulesBool[$k] = ($st === 'active');
                }
            }
        }

        // 3) Si NO vino nada del SOT, no rompas: deja session vacía (pero eso hará "default ON" en el sidebar)
        //    Para evitar eso, si está vacío, es preferible setear todo en false? NO: porque te puedes quedar sin menú.
        //    Mantengo el comportamiento actual: si no hay SOT, el sidebar decide su default.
        //    Pero dejamos una marca para debug.
        $request->session()->put('p360.modules_source', ($state || $legacy || $visible || $access) ? 'admin_sot' : 'empty');

        // Guardar en sesión (nuevo esquema)
        $request->session()->put('p360.modules_state', $state);
        $request->session()->put('p360.modules_visible', $visible);
        $request->session()->put('p360.modules_access', $access);

        // ✅ Guardar también el esquema que usa tu sidebar actual v3.2
        //     OJO: esto es lo que realmente hará que el menú respete lo que venga de admin.
        if ($modulesBool) {
            $request->session()->put('p360.modules', $modulesBool);
        }

        $request->session()->put('p360.modules_hydrated', true);

        return $next($request);
    }

    private function fetchFromAdminSot(string $adm, $clientId, $accountId): array
    {
        $candidates = [
            ['billing_accounts', 'client_id',  $clientId,  'meta'],
            ['billing_accounts', 'account_id', $accountId, 'meta'],
            ['cuentas_clientes', 'cliente_id', $clientId,  'meta'],
            ['cuentas_clientes', 'cuenta_id',  $accountId, 'meta'],
            ['accounts',         'client_id',  $clientId,  'meta'],
            ['accounts',         'account_id', $accountId, 'meta'],
        ];

        foreach ($candidates as $row) {
            [$table, $keyCol, $keyVal, $metaCol] = $row;
            if (!$keyVal) continue;

            try {
                $r = DB::connection($adm)->table($table)->where($keyCol, $keyVal)->first();
                if (!$r) continue;

                $meta = null;
                if (isset($r->{$metaCol})) $meta = $r->{$metaCol};
                elseif (isset($r->meta))   $meta = $r->meta;

                $metaArr = $this->toArray($meta);

                $state   = $this->toArray($metaArr['modules_state']   ?? ($metaArr['p360']['modules_state']   ?? null));
                $visible = $this->toArray($metaArr['modules_visible'] ?? ($metaArr['p360']['modules_visible'] ?? null));
                $access  = $this->toArray($metaArr['modules_access']  ?? ($metaArr['p360']['modules_access']  ?? null));

                // legacy bools
                $legacy  = $this->toArray($metaArr['modules']         ?? ($metaArr['p360']['modules']         ?? null));

                if ($state || $visible || $access || $legacy) {
                    return compact('state', 'visible', 'access', 'legacy');
                }
            } catch (\Throwable $e) {
                // seguir intentando
            }
        }

        return ['state' => [], 'visible' => [], 'access' => [], 'legacy' => []];
    }

    private function toArray($v): array
    {
        if (is_array($v)) return $v;
        if (is_object($v)) return (array)$v;
        if (is_string($v)) {
            $t = trim($v);
            if ($t === '') return [];
            $j = json_decode($t, true);
            return is_array($j) ? $j : [];
        }
        return [];
    }
}
