<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\Client\SatRfcOptionsService.php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatCredential;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class SatRfcOptionsService
{
    /**
     * Carga credenciales SAT por cuenta.
     *
     * Compatibilidad:
     * - cuenta_id = UUID cliente
     * - account_id = UUID cliente
     * - account_id = admin_account_id numérico (registros legacy / admin)
     */
    public function loadCredentials(string $cuentaId, string $conn = 'mysql_clientes'): Collection
    {
        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') {
            return collect();
        }

        try {
            $adminAccountId = $this->resolveAdminAccountId($cuentaId, $conn);

            $query = SatCredential::on($conn)
                ->where(function ($q) use ($cuentaId, $adminAccountId) {
                    $q->where('cuenta_id', $cuentaId)
                        ->orWhere('account_id', $cuentaId);

                    if ($adminAccountId !== null) {
                        $q->orWhere('account_id', (string) $adminAccountId);
                    }
                })
                ->orderBy('rfc');

            $rows = $query->get();

            return $rows
                ->filter(function ($row) {
                    $meta = $row->meta ?? [];

                    if (is_string($meta)) {
                        $decoded = json_decode($meta, true);
                        $meta = is_array($decoded) ? $decoded : [];
                    }

                    if (!is_array($meta)) {
                        $meta = [];
                    }

                    if (isset($meta['is_active'])) {
                        $isActive = $meta['is_active'];
                        if ($isActive === false || $isActive === 0 || $isActive === '0') {
                            return false;
                        }
                    }

                    if (property_exists($row, 'deleted_at') && !empty($row->deleted_at)) {
                        return false;
                    }

                    $estatusOperativo = strtolower(trim((string) ($row->estatus_operativo ?? '')));
                    if ($estatusOperativo === 'inactive') {
                        return false;
                    }

                    return strtoupper(trim((string) ($row->rfc ?? ''))) !== '';
                })
                ->unique(function ($row) {
                    return strtoupper(trim((string) ($row->rfc ?? '')));
                })
                ->values();
        } catch (\Throwable $e) {
            Log::debug('[SatRfcOptionsService:loadCredentials] fallback empty', [
                'cuenta_id'        => $cuentaId,
                'admin_account_id' => isset($adminAccountId) ? $adminAccountId : null,
                'err'              => $e->getMessage(),
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
            if ($rfc === '') {
                continue;
            }

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
        try {
            $cols = Schema::connection($conn)->getColumnListing($table);
        } catch (\Throwable) {
            $cols = [];
        }

        $has = static fn(string $c): bool => in_array($c, $cols, true);

        $options = [];

        foreach ($credList as $c) {
            $rfc = strtoupper(trim((string) ($c->rfc ?? '')));
            if ($rfc === '') {
                continue;
            }

            $alias = trim((string) ($c->razon_social ?? $c->alias ?? $c->nombre ?? ''));
            if ($alias === '') {
                $alias = '—';
            }

            $estatusRaw = '';
            foreach (['estatus', 'status', 'estado', 'state'] as $k) {
                if ($has($k) && isset($c->{$k})) {
                    $estatusRaw = (string) $c->{$k};
                    break;
                }
                if (isset($c->{$k})) {
                    $estatusRaw = (string) $c->{$k};
                    break;
                }
            }

            $st = strtolower(trim($estatusRaw));
            $validated = false;

            if (!$validated && !empty($c->validado ?? null)) {
                $validated = true;
            }
            if (!$validated && !empty($c->validated ?? null)) {
                $validated = true;
            }
            if (!$validated && !empty($c->validated_at ?? null)) {
                $validated = true;
            }
            if (!$validated && !empty($c->verified_at ?? null)) {
                $validated = true;
            }
            if (!$validated && !empty($c->ok_at ?? null)) {
                $validated = true;
            }

            if (
                !$validated
                && $st !== ''
                && in_array($st, ['ok', 'valido', 'válido', 'validado', 'valid', 'activo', 'active', 'enabled', 'on', 'ready', 'done'], true)
            ) {
                $validated = true;
            }

            foreach (['is_valid', 'is_verified', 'active', 'enabled'] as $k) {
                if ($validated) {
                    break;
                }

                if (isset($c->{$k}) && (string) $c->{$k} !== '') {
                    $v = $c->{$k};
                    if ($v === 1 || $v === true || strtolower((string) $v) === '1' || strtolower((string) $v) === 'true') {
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

        usort($options, fn ($a, $b) => strcmp($a['rfc'], $b['rfc']));

        return $options;
    }

    private function resolveAdminAccountId(string $cuentaId, string $conn): ?int
    {
        try {
            if (!Schema::connection($conn)->hasTable('cuentas_cliente')) {
                return null;
            }

            $query = DB::connection($conn)
                ->table('cuentas_cliente')
                ->where('id', $cuentaId);

            if (Schema::connection($conn)->hasColumn('cuentas_cliente', 'admin_account_id')) {
                $row = $query->select('admin_account_id')->first();

                $value = trim((string) ($row->admin_account_id ?? ''));
                if ($value !== '' && ctype_digit($value) && (int) $value > 0) {
                    return (int) $value;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[SatRfcOptionsService:resolveAdminAccountId] no admin_account_id', [
                'cuenta_id' => $cuentaId,
                'err'       => $e->getMessage(),
            ]);
        }

        return null;
    }
}