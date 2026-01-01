<?php

declare(strict_types=1);

namespace App\Services\Sat;

use App\Models\Cliente\Cuenta;

class VaultAccountSummaryService
{
    /**
     * Construye un resumen de bóveda fiscal para una cuenta.
     *
     * Devuelve siempre el MISMO formato para que lo use
     * tanto el portal SAT como el portal de la bóveda.
     */
    public function buildForCuenta(?Cuenta $cuenta): array
    {
        if (!$cuenta) {
            return $this->emptySummary();
        }

        // Campos que agregaste en la migración:
        // vault_quota_gb, vault_quota_bytes, vault_used_bytes, etc.
        $quotaGb       = (float) ($cuenta->vault_quota_gb ?? 0);
        $quotaBytes    = (int)   ($cuenta->vault_quota_bytes ?? 0);
        $usedBytes     = (int)   ($cuenta->vault_used_bytes ?? 0);
        $filesCount    = (int)   ($cuenta->vault_files_count ?? 0);

        // Si no tienes vault_used_bytes, aquí podrías calcular desde otra tabla.
        // Por ahora asumimos que un job ya los mantiene actualizados.

        $gbFromBytes = function (int $bytes): float {
            // 1 GB = 1024^3 bytes
            $base = 1024 * 1024 * 1024;
            if ($base <= 0) {
                return 0.0;
            }
            return $bytes / $base;
        };

        if ($quotaGb <= 0 && $quotaBytes > 0) {
            $quotaGb = $gbFromBytes($quotaBytes);
        }

        $usedGb       = $gbFromBytes($usedBytes);
        $availableGb  = max(0, $quotaGb - $usedGb);
        $usedPct      = $quotaGb > 0 ? round(($usedGb / $quotaGb) * 100, 1) : 0.0;
        $availablePct = $quotaGb > 0 ? max(0, 100 - $usedPct) : 0.0;

        return [
            'has_quota'       => $quotaGb > 0,
            'quota_gb'        => $quotaGb,
            'quota_bytes'     => $quotaBytes,
            'used_gb'         => $usedGb,
            'used_bytes'      => $usedBytes,
            'available_gb'    => $availableGb,
            'used_pct'        => $usedPct,
            'available_pct'   => $availablePct,
            'files_count'     => $filesCount,
        ];
    }

    /**
     * Resumen vacío por default (sin bóveda).
     */
    private function emptySummary(): array
    {
        return [
            'has_quota'       => false,
            'quota_gb'        => 0.0,
            'quota_bytes'     => 0,
            'used_gb'         => 0.0,
            'used_bytes'      => 0,
            'available_gb'    => 0.0,
            'used_pct'        => 0.0,
            'available_pct'   => 0.0,
            'files_count'     => 0,
        ];
    }
}
