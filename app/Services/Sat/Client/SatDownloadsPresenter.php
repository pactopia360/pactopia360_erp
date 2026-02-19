<?php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatDownload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class SatDownloadsPresenter
{
    public function __construct(
        private readonly SatDownloadMetrics $metrics,
        private readonly SatVaultStorage    $vaultStorage,
    ) {}

    /**
     * Construye resumen de bóveda para Blade + payload para JS.
     * Devuelve: [vaultSummary, vaultForJs]
     */
    public function buildVaultSummaries(string $cuentaId, $cuentaCliente): array
    {
        $cuentaId = trim((string) $cuentaId);

        $vaultSummary = [
            'has_quota'     => false,
            'quota_gb'      => 0.0,
            'quota_bytes'   => 0,
            'used_gb'       => 0.0,
            'used_bytes'    => 0,
            'available_gb'  => 0.0,
            'used_pct'      => 0.0,
            'available_pct' => 0.0,
            'files_count'   => 0,
        ];

        $vaultForJs = [
            'quota_gb'     => 0.0,
            'used_gb'      => 0.0,
            'used'         => 0.0,
            'free_gb'      => 0.0,
            'available_gb' => 0.0,
            'used_pct'     => 0.0,
            'files_count'  => 0,
            'enabled'      => false,
        ];

        if ($cuentaId === '') {
            return [$vaultSummary, $vaultForJs];
        }

        try {
            $storage = $this->vaultStorage->buildVaultStorageSummary($cuentaId, $cuentaCliente ?? (object) []);
            $enabled = ((int) ($storage['quota_bytes'] ?? 0)) > 0;

            // files_count (si existe sat_vault_files)
            $vaultFilesCount = 0;
            try {
                $conn = 'mysql_clientes';
                if (Schema::connection($conn)->hasTable('sat_vault_files')) {
                    $vaultFilesCount = (int) DB::connection($conn)
                        ->table('sat_vault_files')
                        ->where('cuenta_id', $cuentaId)
                        ->count();
                }
            } catch (\Throwable) {
                $vaultFilesCount = 0;
            }

            $vaultSummary = [
                'has_quota'     => $enabled,
                'quota_gb'      => (float) ($storage['quota_gb'] ?? 0),
                'quota_bytes'   => (int) ($storage['quota_bytes'] ?? 0),
                'used_gb'       => (float) ($storage['used_gb'] ?? 0),
                'used_bytes'    => (int) ($storage['used_bytes'] ?? 0),
                'available_gb'  => (float) ($storage['free_gb'] ?? 0),
                'used_pct'      => (float) ($storage['used_pct'] ?? 0),
                'available_pct' => (float) ($storage['free_pct'] ?? 0),
                'files_count'   => $vaultFilesCount,
            ];

            $vaultForJs = [
                'quota_gb'     => (float) ($storage['quota_gb'] ?? 0),
                'used_gb'      => (float) ($storage['used_gb'] ?? 0),
                'used'         => (float) ($storage['used_gb'] ?? 0),
                'free_gb'      => (float) ($storage['free_gb'] ?? 0),
                'available_gb' => (float) ($storage['free_gb'] ?? 0),
                'used_pct'     => (float) ($storage['used_pct'] ?? 0),
                'files_count'  => $vaultFilesCount,
                'enabled'      => $enabled,
            ];
        } catch (\Throwable $e) {
            Log::warning('[SAT:index] Error calculando resumen de bóveda (presenter)', [
                'cuenta_id' => $cuentaId,
                'error'     => $e->getMessage(),
            ]);
        }

        return [$vaultSummary, $vaultForJs];
    }

    /**
     * Transforma un SatDownload para el UI (Blade/JS).
     */
    public function transformDownloadRow(
        SatDownload $d,
        array $credMap,
        array $cartIds,
        Carbon $now
    ): array {
        $rfc   = strtoupper((string) ($d->rfc ?? ''));
        $alias = $credMap[$rfc] ?? (string) ($d->razon_social ?? $d->alias ?? '');

        $from = $d->date_from ?? $d->desde ?? null;
        $to   = $d->date_to   ?? $d->hasta ?? null;

        $fromStr = $from ? substr((string) $from, 0, 10) : null;
        $toStr   = $to   ? substr((string) $to,   0, 10) : null;

        $expRaw = $d->expires_at ?? $d->disponible_hasta ?? null;
        $exp    = null;

        try {
            if ($expRaw instanceof Carbon) $exp = $expRaw->copy();
            elseif ($expRaw) $exp = Carbon::parse((string) $expRaw);
        } catch (\Throwable) {
            $exp = null;
        }

        if (!$exp && $d->paid_at) {
            $exp = $d->paid_at instanceof Carbon
                ? $d->paid_at->copy()->addDays(15)
                : Carbon::parse((string) $d->paid_at)->addDays(15);
        }

        if (!$exp && $d->created_at) {
            $exp = $d->created_at instanceof Carbon
                ? $d->created_at->copy()->addHours(12)
                : Carbon::parse((string) $d->created_at)->addHours(12);
        }

        $secondsLeft = null;
        if ($exp) $secondsLeft = $now->diffInSeconds($exp, false);

        $estadoStr = (string) ($d->estado ?? $d->status ?? $d->sat_status ?? '');
        $estadoLow = strtolower($estadoStr);

        $pagado    = !empty($d->paid_at);
        $isExpired = false;

        if (!$pagado && $secondsLeft !== null && $secondsLeft <= 0) {
            $estadoStr = 'EXPIRADA';
            $estadoLow = 'expirada';
            $isExpired = true;
        }

        if ($pagado) {
            $estadoStr = 'PAID';
            $estadoLow = 'paid';
            $isExpired = false;
        }

        $tipoLow   = strtolower((string) ($d->tipo ?? ''));
        $estadoLow = strtolower((string) $estadoStr);

        $canPay = !$pagado
            && !in_array($tipoLow, ['vault', 'boveda'], true)
            && in_array($estadoLow, ['ready', 'done', 'listo', 'completed', 'finalizado'], true);

        // ✅ Asegura métricas (peso, xml_count, costo, etc.)
        $d = $this->metrics->hydrateDownloadMetrics($d);

        $xmlCount  = (int) ($d->xml_count ?? $d->total_xml ?? 0);
        $sizeGb    = (float) ($d->size_gb ?? 0);
        $sizeMb    = (float) ($d->size_mb ?? 0);
        $sizeBytes = (int) ($d->size_bytes ?? 0);
        $costo     = (float) ($d->costo ?? 0);

        $discount   = $d->discount_pct ?? $d->descuento_pct ?? null;
        $createdStr = $d->created_at ? $d->created_at->format('Y-m-d H:i:s') : null;

        $sizeLabel = (string) ($d->size_label ?? ($sizeMb > 0
            ? number_format($sizeMb, 2) . ' Mb'
            : 'Pendiente'
        ));

        $remainingLabel = '00:00:00';
        if ($secondsLeft !== null && $secondsLeft > 0) {
            $remainingLabel = gmdate('H:i:s', (int) floor($secondsLeft));
        }

        $inCart = in_array((string) $d->id, $cartIds, true);

        $pesoMb    = $sizeMb > 0 ? $sizeMb : (($sizeBytes > 0) ? ($sizeBytes / (1024 * 1024)) : 0.0);
        $pesoLabel = $pesoMb > 0 ? number_format($pesoMb, 2) . ' MB' : 'Pendiente';

        // ✅ Manual flag (columna o meta)
        $isManual = false;
        try {
            $meta = $this->decodeMetaToArray($d->meta ?? null);

            $isManual =
                !empty($d->is_manual ?? null)
                || !empty($d->manual ?? null)
                || !empty($meta['is_manual'] ?? null)
                || !empty($meta['manual'] ?? null);
        } catch (\Throwable) {
            $isManual = false;
        }

        return [
            'id'           => (string) $d->id,
            'dlid'         => (string) $d->id,

            'rfc'          => $rfc,
            'razon'        => $alias,
            'razon_social' => $alias,
            'alias'        => $alias,
            'tipo'         => (string) ($d->tipo ?? ''),

            'is_manual'    => $isManual,
            'manual'       => $isManual,

            'desde'        => $fromStr,
            'hasta'        => $toStr,
            'fecha'        => $createdStr,

            'estado'          => $estadoStr,
            'status'          => $estadoStr,
            'status_sat'      => $estadoStr,
            'status_sat_text' => $estadoStr,

            'pagado'       => $pagado,
            'paid'         => $pagado,
            'paid_at'      => $d->paid_at ? $d->paid_at->toIso8601String() : null,
            'can_pay'      => $canPay,

            'xml_count'    => $xmlCount,
            'total_xml'    => $xmlCount,
            'size_mb'      => $sizeMb,
            'size_gb'      => $sizeGb,
            'size_bytes'   => $sizeBytes,
            'costo'        => $costo,

            'discount_pct' => $discount,

            'expires_at'      => $exp ? $exp->toIso8601String() : null,
            'time_left'       => $secondsLeft,
            'is_expired'      => $isExpired,
            'remaining_label' => $remainingLabel,

            'size_label'   => $sizeLabel,
            'cost_usd'     => $costo, // legacy UI key
            'in_cart'      => $inCart,
            'created_at'   => $d->created_at ? $d->created_at->toIso8601String() : null,

            'peso_mb'      => (float) $pesoMb,
            'peso_label'   => (string) $pesoLabel,
        ];
    }

    private function decodeMetaToArray($meta): array
    {
        if (is_array($meta)) return $meta;
        if (is_string($meta) && $meta !== '') {
            $tmp = json_decode($meta, true);
            if (is_array($tmp)) return $tmp;
        }
        return [];
    }
}
