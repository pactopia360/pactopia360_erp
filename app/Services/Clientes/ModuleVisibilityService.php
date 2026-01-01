<?php

declare(strict_types=1);

namespace App\Services\Clientes;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class ModuleVisibilityService
{
    private string $adm = 'mysql_admin';
    private string $cli = 'mysql_clientes';

    /**
     * Estados soportados:
     * - active  => visible
     * - hidden  => no se muestra
     * - locked  => se muestra pero deshabilitado (candado)
     *
     * Regresa: ['key' => 'active|hidden|locked', ...]
     */
    public function getModulesForClientAccountId(string $cuentaClienteId): array
    {
        $cuentaClienteId = (string) $cuentaClienteId;
        if ($cuentaClienteId === '') return [];

        $cacheKey = 'p360:client:modules:' . $cuentaClienteId;

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($cuentaClienteId) {
            try {
                if (!Schema::connection($this->cli)->hasTable('cuentas_cliente')) return [];

                $adminAccountId = DB::connection($this->cli)
                    ->table('cuentas_cliente')
                    ->where('id', $cuentaClienteId)
                    ->value('admin_account_id');

                $adminAccountId = (int) ($adminAccountId ?: 0);
                if ($adminAccountId <= 0) return [];

                return $this->getModulesForAdminAccountId($adminAccountId);

            } catch (\Throwable $e) {
                Log::warning('[MODULES] getModulesForClientAccountId error', [
                    'cuenta_id' => $cuentaClienteId,
                    'err'       => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    public function getModulesForAdminAccountId(int $adminAccountId): array
    {
        if ($adminAccountId <= 0) return [];

        $cacheKey = 'p360:admin:modules:' . $adminAccountId;

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($adminAccountId) {
            try {
                if (!Schema::connection($this->adm)->hasTable('accounts')) return [];

                $meta = DB::connection($this->adm)
                    ->table('accounts')
                    ->where('id', $adminAccountId)
                    ->value('meta');

                $metaArr = $this->decodeMetaToArray($meta);

                /**
                 * Prioridad:
                 * 1) meta.modules_state  => ya viene en 'active|hidden|locked'
                 * 2) meta.modules        => booleanos (true/false) o strings legacy
                 */
                $modsState = $metaArr['modules_state'] ?? null;
                if (is_array($modsState) && count($modsState)) {
                    return $this->normalizeModules($modsState, true);
                }

                $mods = $metaArr['modules'] ?? null;
                if (!is_array($mods) || !count($mods)) return [];

                return $this->normalizeModules($mods, false);

            } catch (\Throwable $e) {
                Log::warning('[MODULES] getModulesForAdminAccountId error', [
                    'admin_account_id' => $adminAccountId,
                    'err'              => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    /**
     * Normaliza valores:
     * - si $preferState=true => espera strings active/hidden/locked (acepta activo/oculto/bloqueado)
     * - si $preferState=false => acepta booleanos:
     *      true  => active
     *      false => hidden
     *   y también strings legacy.
     *
     * @param array<mixed,mixed> $mods
     * @return array<string,string>
     */
    private function normalizeModules(array $mods, bool $preferState): array
    {
        $out = [];

        foreach ($mods as $k => $v) {
            $key = strtolower(trim((string) $k));
            if ($key === '') continue;

            // 1) Si vienen booleanos (meta.modules típico)
            if (!$preferState && is_bool($v)) {
                $out[$key] = $v ? 'active' : 'hidden';
                continue;
            }

            // 2) Si viene null o vacío
            if ($v === null) continue;

            // 3) Si viene numérico (1/0)
            if (!$preferState && (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v)))) {
                $num = (int) $v;
                $out[$key] = ($num > 0) ? 'active' : 'hidden';
                continue;
            }

            // 4) Strings
            $val = strtolower(trim((string) $v));

            // Alias ES
            if ($val === 'oculto') $val = 'hidden';
            if ($val === 'activo') $val = 'active';
            if ($val === 'bloqueado') $val = 'locked';

            // Alias EN/otros
            if ($val === 'hide') $val = 'hidden';
            if ($val === 'lock') $val = 'locked';

            // Compat: true/false como string
            if (!$preferState && ($val === 'true' || $val === '1')) $val = 'active';
            if (!$preferState && ($val === 'false' || $val === '0' || $val === '')) $val = 'hidden';

            if (!in_array($val, ['active', 'hidden', 'locked'], true)) {
                continue;
            }

            $out[$key] = $val;
        }

        return $out;
    }

    /**
     * @param mixed $meta
     * @return array<string,mixed>
     */
    private function decodeMetaToArray(mixed $meta): array
    {
        if (is_array($meta)) return $meta;

        if (is_string($meta) && $meta !== '') {
            $d = json_decode($meta, true);
            if (is_array($d)) return $d;
        }

        return [];
    }

    public function clearClientCache(string $cuentaClienteId): void
    {
        Cache::forget('p360:client:modules:' . $cuentaClienteId);
    }

    public function clearAdminCache(int $adminAccountId): void
    {
        Cache::forget('p360:admin:modules:' . $adminAccountId);
    }
}
