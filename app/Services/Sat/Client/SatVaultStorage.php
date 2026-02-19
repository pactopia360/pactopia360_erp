<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\Client\SatVaultStorage.php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatDownload;
use App\Services\Sat\VaultAccountSummaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SatVaultStorage
{
    public function __construct(
        private readonly VaultAccountSummaryService $vaultSummaryService,
    ) {}

    public function buildVaultStorageSummary(string $cuentaId, $cuentaObj): array
    {
        // 0) Si el servicio existe y devuelve algo usable, úsalo (SOT)
        try {
            if (method_exists($this->vaultSummaryService, 'buildStorageSummary')) {
                $res = $this->vaultSummaryService->buildStorageSummary($cuentaId, $cuentaObj);
                if (is_array($res) && isset($res['quota_bytes'])) {
                    return array_merge([
                        'quota_gb'    => isset($res['quota_gb']) ? (float) $res['quota_gb'] : round(((float) $res['quota_bytes']) / 1024 / 1024 / 1024, 2),
                        'quota_bytes' => (int) ($res['quota_bytes'] ?? 0),
                        'used_gb'     => isset($res['used_gb']) ? (float) $res['used_gb'] : round(((float) ($res['used_bytes'] ?? 0)) / 1024 / 1024 / 1024, 2),
                        'used_bytes'  => (int) ($res['used_bytes'] ?? 0),
                        'free_gb'     => (float) ($res['free_gb'] ?? 0),
                        'used_pct'    => (float) ($res['used_pct'] ?? 0),
                        'free_pct'    => (float) ($res['free_pct'] ?? 0),
                    ], $res);
                }
            }
        } catch (\Throwable) {
            // fallback
        }

        $conn = 'mysql_clientes';
        $cuentaId = trim((string) $cuentaId);

        $schema = Schema::connection($conn);

        $hasTable = static function (string $t) use ($schema): bool {
            try { return $schema->hasTable($t); } catch (\Throwable) { return false; }
        };
        $hasCol = static function (string $t, string $c) use ($schema): bool {
            try { return $schema->hasTable($t) && $schema->hasColumn($t, $c); } catch (\Throwable) { return false; }
        };

        // 1) Plan → base GB
        $planRaw = '';
        try { $planRaw = (string) data_get($cuentaObj, 'plan_actual', 'FREE'); } catch (\Throwable) { $planRaw = 'FREE'; }

        $plan = strtoupper(trim($planRaw));
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        $vaultBaseGb = $isProPlan
            ? (float) config('services.sat.vault.base_gb_pro', 0.0)
            : (float) config('services.sat.vault.base_gb_free', 0.0);

        $vaultBaseGb = max(0.0, $vaultBaseGb);

        // 2) Quota desde cuenta (si existe)
        $quotaBytesFromAccount = 0;

        foreach (['vault_quota_bytes', 'vault_quota_gb'] as $k) {
            $v = null;
            try { $v = data_get($cuentaObj, $k); } catch (\Throwable) { $v = null; }

            if ($k === 'vault_quota_bytes' && is_numeric($v) && (int)$v > 0) { $quotaBytesFromAccount = (int) $v; break; }
            if ($k === 'vault_quota_gb' && is_numeric($v) && (float)$v > 0) { $quotaBytesFromAccount = (int) round(((float)$v) * 1024 * 1024 * 1024); break; }
        }

        if ($quotaBytesFromAccount > 0 && $hasTable('cuentas_cliente')) {
            if (!$hasCol('cuentas_cliente', 'vault_quota_bytes') && !$hasCol('cuentas_cliente', 'vault_quota_gb')) {
                $quotaBytesFromAccount = 0;
            }
        }

        $quotaGbFromAccount = $quotaBytesFromAccount > 0 ? ($quotaBytesFromAccount / 1024 / 1024 / 1024) : 0.0;

        // 3) Quota comprada por rows sat_downloads tipo vault/boveda pagadas
        $quotaGbFromVaultRows = 0.0;

        if ($hasTable('sat_downloads')) {
            try {
                $t = (new SatDownload())->getTable();
                $cols = [];
                try { $cols = $schema->getColumnListing($t); } catch (\Throwable) { $cols = []; }
                $hasDl = static function (string $c) use ($cols): bool { return in_array($c, $cols, true); };

                $colCuenta = $hasDl('cuenta_id') ? 'cuenta_id' : ($hasDl('account_id') ? 'account_id' : null);
                $colTipo   = $hasDl('tipo') ? 'tipo' : null;
                $colPaidAt = $hasDl('paid_at') ? 'paid_at' : null;
                $colStatus = $hasDl('status') ? 'status' : null;

                if ($colCuenta && $colTipo) {
                    $q = DB::connection($conn)->table($t)->where($colCuenta, $cuentaId);
                    $q->whereRaw('LOWER(COALESCE(' . $colTipo . ',"")) IN ("vault","boveda")');

                    $q->where(function ($qq) use ($colPaidAt, $colStatus) {
                        if ($colPaidAt) $qq->whereNotNull($colPaidAt);
                        if ($colStatus) $qq->orWhereRaw('LOWER(COALESCE(' . $colStatus . ',"")) IN ("paid","pagado","pagada")');
                    });

                    $sel = [];
                    foreach (['vault_gb','gb','alias','nombre','meta'] as $c) {
                        if ($hasDl($c)) $sel[] = $c;
                    }
                    if (empty($sel)) $sel = [$colCuenta];

                    $rows = $q->select($sel)->get();

                    $totalGb = 0.0;
                    foreach ($rows as $row) {
                        $gb = 0.0;

                        foreach (['vault_gb','gb'] as $c) {
                            if (isset($row->{$c}) && is_numeric($row->{$c}) && (float)$row->{$c} > 0) {
                                $gb = (float) $row->{$c};
                                break;
                            }
                        }

                        if ($gb <= 0 && isset($row->meta) && !empty($row->meta)) {
                            $m = [];
                            try {
                                if (is_array($row->meta)) $m = $row->meta;
                                elseif (is_string($row->meta) && trim($row->meta) !== '') {
                                    $tmp = json_decode($row->meta, true);
                                    if (is_array($tmp)) $m = $tmp;
                                }
                            } catch (\Throwable) { $m = []; }

                            foreach (['vault_gb','gb','quota_gb'] as $k) {
                                if (isset($m[$k]) && is_numeric($m[$k]) && (float)$m[$k] > 0) {
                                    $gb = (float) $m[$k];
                                    break;
                                }
                            }
                        }

                        if ($gb <= 0) {
                            $alias = '';
                            foreach (['alias','nombre'] as $c) {
                                if (isset($row->{$c}) && trim((string)$row->{$c}) !== '') {
                                    $alias = (string) $row->{$c};
                                    break;
                                }
                            }
                            if ($alias !== '' && preg_match('/(\d+(?:\.\d+)?)\s*gb/i', $alias, $mm)) {
                                $gb = (float) $mm[1];
                            }
                        }

                        if ($gb > 0) $totalGb += $gb;
                    }

                    $quotaGbFromVaultRows = max(0.0, $totalGb);
                }
            } catch (\Throwable) {
                $quotaGbFromVaultRows = 0.0;
            }
        }

        // 4) Final quota
        $quotaGbComputed = max(0.0, $vaultBaseGb + $quotaGbFromVaultRows);
        $quotaGbFinal    = max($quotaGbComputed, $quotaGbFromAccount);
        $quotaBytesFinal = (int) round($quotaGbFinal * 1024 * 1024 * 1024);

        // 5) Used bytes
        $usedBytes = 0;

        if ($hasTable('cuentas_cliente') && $hasCol('cuentas_cliente', 'vault_used_bytes')) {
            try {
                $v = data_get($cuentaObj, 'vault_used_bytes');
                if (is_numeric($v) && (int)$v > 0) $usedBytes = (int) $v;
            } catch (\Throwable) {}
        }

        if ($usedBytes <= 0 && $hasTable('sat_vault_files') && $hasCol('sat_vault_files', 'bytes')) {
            try {
                $cols = [];
                try { $cols = $schema->getColumnListing('sat_vault_files'); } catch (\Throwable) { $cols = []; }
                $hv = static function (string $c) use ($cols): bool { return in_array($c, $cols, true); };
                $colCuentaVF = $hv('cuenta_id') ? 'cuenta_id' : ($hv('account_id') ? 'account_id' : null);

                if ($colCuentaVF) {
                    $usedBytes = (int) DB::connection($conn)
                        ->table('sat_vault_files')
                        ->where($colCuentaVF, $cuentaId)
                        ->sum('bytes');
                }
            } catch (\Throwable) { $usedBytes = 0; }
        }

        $usedBytes = max(0, (int) $usedBytes);
        $quotaBytes = max((int) $quotaBytesFinal, $usedBytes);

        $usedGb  = $usedBytes / 1024 / 1024 / 1024;
        $quotaGb = $quotaBytes / 1024 / 1024 / 1024;
        $freeGb  = max(0.0, $quotaGb - $usedGb);

        $usedPct = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 2) : 0.0;
        $freePct = max(0.0, round(100.0 - $usedPct, 2));

        return [
            'quota_gb'    => round($quotaGb, 2),
            'quota_bytes' => (int) $quotaBytes,
            'used_gb'     => round($usedGb, 2),
            'used_bytes'  => (int) $usedBytes,
            'free_gb'     => round($freeGb, 2),
            'used_pct'    => $usedPct,
            'free_pct'    => $freePct,

            'base_gb'        => round($vaultBaseGb, 2),
            'purchased_gb'   => round($quotaGbFromVaultRows, 2),
            'account_quota'  => round($quotaGbFromAccount, 2),
            'plan'           => $plan,
            'is_pro_plan'    => $isProPlan,
        ];
    }

    public function hasActiveVault(string $cuentaId, $cuentaObj = null): bool
    {
        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') return false;

        try {
            $plan = strtoupper(trim((string) data_get($cuentaObj, 'plan_actual', '')));
            if ($plan !== '' && in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true)) {
                $base = (float) config('services.sat.vault.base_gb_pro', 0.0);
                if ($base > 0.0) return true;
            }
        } catch (\Throwable) {}

        try {
            $summary = $this->buildVaultStorageSummary($cuentaId, $cuentaObj ?? (object) []);
            return ((int) ($summary['quota_bytes'] ?? 0)) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function vaultIsActiveForAccount($cuenta): bool
    {
        if (!$cuenta) return false;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $cuentaId = '';
        try {
            $cuentaId = trim((string) (data_get($cuenta, 'id') ?? data_get($cuenta, 'cuenta_id') ?? data_get($cuenta, 'account_id') ?? ''));
        } catch (\Throwable) {
            $cuentaId = trim((string) (($cuenta->id ?? $cuenta->cuenta_id ?? $cuenta->account_id ?? '') ?: ''));
        }

        if ($cuentaId === '') return false;

        return $this->hasActiveVault($cuentaId, $cuenta);
    }

    public function fetchCuentaObjForVault(string $cuentaId, ?object $currentUser = null): object
    {
        $cuentaId = trim((string) $cuentaId);

        $fallback = static function () use ($cuentaId): object {
            return (object) ['id' => $cuentaId, 'cuenta_id' => $cuentaId];
        };

        if ($cuentaId === '') return (object) ['id' => '', 'cuenta_id' => ''];

        try {
            $conn   = 'mysql_clientes';
            $schema = Schema::connection($conn);

            if ($schema->hasTable('cuentas_cliente')) {
                $q = DB::connection($conn)->table('cuentas_cliente');

                $row = $q->where('id', $cuentaId)->first();

                if (!$row && $schema->hasColumn('cuentas_cliente', 'cuenta_id')) {
                    $row = DB::connection($conn)->table('cuentas_cliente')->where('cuenta_id', $cuentaId)->first();
                }

                if ($row) {
                    $obj = (object) $row;
                    if (empty($obj->id) && !empty($obj->cuenta_id)) $obj->id = (string) $obj->cuenta_id;
                    if (empty($obj->cuenta_id) && !empty($obj->id)) $obj->cuenta_id = (string) $obj->id;
                    return $obj;
                }
            }
        } catch (\Throwable) {}

        try {
            $u = $currentUser;
            $c = $u?->cuenta ?? null;

            if (is_array($c)) $c = (object) $c;

            if (is_object($c)) {
                if (empty($c->id) && !empty($c->cuenta_id)) $c->id = (string) $c->cuenta_id;
                if (empty($c->cuenta_id) && !empty($c->id)) $c->cuenta_id = (string) $c->id;

                if (empty($c->id) || (string)$c->id === '') $c->id = $cuentaId;
                if (empty($c->cuenta_id) || (string)$c->cuenta_id === '') $c->cuenta_id = $cuentaId;

                return $c;
            }
        } catch (\Throwable) {}

        return $fallback();
    }

    public function fetchCuentaCliente(string $cuentaId): ?object
    {
        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') return null;

        $conn = 'mysql_clientes';

        try {
            $schema = Schema::connection($conn);

            if (!$schema->hasTable('cuentas_cliente')) return null;

            $cols = [];
            try { $cols = $schema->getColumnListing('cuentas_cliente'); } catch (\Throwable) { $cols = []; }

            $has = static function (string $c) use ($cols): bool { return in_array($c, $cols, true); };

            $q = DB::connection($conn)->table('cuentas_cliente');

            $row = $has('id') ? $q->where('id', $cuentaId)->first() : null;

            if (!$row && $has('cuenta_id')) {
                $row = DB::connection($conn)->table('cuentas_cliente')->where('cuenta_id', $cuentaId)->first();
            }

            if (!$row && $has('account_id')) {
                $row = DB::connection($conn)->table('cuentas_cliente')->where('account_id', $cuentaId)->first();
            }

            if (!$row) return null;

            $obj = (object) $row;

            if (empty($obj->id) && !empty($obj->cuenta_id)) $obj->id = (string) $obj->cuenta_id;
            if (empty($obj->id) && !empty($obj->account_id)) $obj->id = (string) $obj->account_id;

            if (empty($obj->cuenta_id) && !empty($obj->id)) $obj->cuenta_id = (string) $obj->id;

            return $obj;
        } catch (\Throwable) {
            return null;
        }
    }
}