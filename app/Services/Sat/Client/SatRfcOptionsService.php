<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\Client\SatRfcOptionsService.php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatCredential;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class SatRfcOptionsService
{
    /**
     * Carga credenciales SAT por cuenta (compat: account_id OR cuenta_id).
     */
    public function loadCredentials(string $cuentaId, string $conn = 'mysql_clientes'): Collection
    {
        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') return collect();

        try {
            return SatCredential::on($conn)
                ->where(function ($q) use ($cuentaId) {
                    $q->where('account_id', $cuentaId)
                      ->orWhere('cuenta_id', $cuentaId);
                })
                ->orderBy('rfc')
                ->get();
        } catch (\Throwable $e) {
            Log::debug('[SatRfcOptionsService:loadCredentials] fallback empty', [
                'cuenta_id' => $cuentaId,
                'err' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Mapa RFC => alias/razón (para el presenter / UI).
     */
    public function buildCredMap(iterable $credList): array
    {
        $map = [];
        foreach ($credList as $c) {
            $rfc = strtoupper(trim((string) ($c->rfc ?? '')));
            if ($rfc === '') continue;

            $map[$rfc] = (string) ($c->razon_social ?? $c->alias ?? $c->nombre ?? '');
        }
        return $map;
    }

    /**
     * Opciones RFC para UI (smart):
     * - Siempre lista RFCs dados de alta
     * - Marca validated=true con heurística robusta (sin depender de un solo campo)
     */
    public function buildRfcOptionsSmart(iterable $credList, string $conn = 'mysql_clientes'): array
    {
        $table = (new SatCredential())->getTable();

        $cols = [];
        try { $cols = Schema::connection($conn)->getColumnListing($table); } catch (\Throwable) { $cols = []; }
        $has = static fn(string $c): bool => in_array($c, $cols, true);

        $options = [];

        foreach ($credList as $c) {
            $rfc = strtoupper(trim((string) ($c->rfc ?? '')));
            if ($rfc === '') continue;

            $alias = trim((string) ($c->razon_social ?? $c->alias ?? $c->nombre ?? ''));
            if ($alias === '') $alias = '—';

            // ---- Heurística validación ----
            $estatusRaw = '';
            foreach (['estatus','status','estado','state'] as $k) {
                if ($has($k) && isset($c->{$k})) { $estatusRaw = (string) $c->{$k}; break; }
                if (isset($c->{$k})) { $estatusRaw = (string) $c->{$k}; break; }
            }
            $st = strtolower(trim($estatusRaw));

            $validated = false;

            // Flags/fechas típicas
            if (!$validated && !empty($c->validado ?? null)) $validated = true;
            if (!$validated && !empty($c->validated ?? null)) $validated = true;
            if (!$validated && !empty($c->validated_at ?? null)) $validated = true;
            if (!$validated && !empty($c->verified_at ?? null)) $validated = true;
            if (!$validated && !empty($c->ok_at ?? null)) $validated = true;

            // Estados típicos
            if (!$validated && $st !== '') {
                if (in_array($st, ['ok','valido','válido','validado','valid','activo','active','enabled','on','ready','done'], true)) {
                    $validated = true;
                }
            }

            // Booleanos típicos
            foreach (['is_valid','is_verified','active','enabled'] as $k) {
                if ($validated) break;
                if (isset($c->{$k}) && (string)$c->{$k} !== '') {
                    $v = $c->{$k};
                    if ($v === 1 || $v === true || strtolower((string)$v) === '1' || strtolower((string)$v) === 'true') {
                        $validated = true;
                    }
                }
            }

            $options[] = [
                'rfc'       => $rfc,
                'alias'     => $alias,
                'validated' => $validated,
            ];
        }

        usort($options, fn($a,$b)=> strcmp($a['rfc'], $b['rfc']));
        return $options;
    }
}
