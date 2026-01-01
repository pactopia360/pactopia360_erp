<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cliente\SatDownload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SatVaultQuota
{
    /**
     * Calcula los metadatos de bóveda para una cuenta dada.
     *
     * @param  \App\Models\Cuenta|\App\Models\Cliente\Cuenta|object|null  $cuenta
     * @param  array|null $summary  (opcional; puedes pasar el summary que ya usas en Home)
     * @return array{
     *   active: bool,
     *   quota_gb: float,
     *   base_gb: float,
     *   purchased_gb: float,
     *   used_gb: float,
     *   available_gb: float,
     *   used_pct: int,
     *   free_pct: int
     * }
     */
    public static function forCuenta(?object $cuenta, ?array $summary = null): array
    {
        if (!$cuenta) {
            return [
                'active'       => false,
                'quota_gb'     => 0.0,
                'base_gb'      => 0.0,
                'purchased_gb' => 0.0,
                'used_gb'      => 0.0,
                'available_gb' => 0.0,
                'used_pct'     => 0,
                'free_pct'     => 0,
            ];
        }

        $summary = $summary ?? [];

        // 1) Base por plan (si quieres que PRO tenga algo base, si no, queda en 0)
        $isProPlan = (bool)($summary['is_pro'] ?? false);
        $vaultBaseGb = 0.0;

        if ($isProPlan) {
            $vaultBaseGb = (float) config('services.sat.vault.base_gb_pro', 0.0);
        }

        // 2) Cuota guardada directamente en la cuenta
        $vaultQuotaFromAccount = (float) ($cuenta->vault_quota_gb ?? 0.0);

        // 3) Uso actual de la bóveda (desde summary o 0)
        $vaultUsedGb = (float) ($summary['vault_used_gb'] ?? 0.0);
        if ($vaultUsedGb < 0) {
            $vaultUsedGb = 0.0;
        }

        // 4) Compras de almacenamiento (sat_downloads con tipo VAULT/BOVEDA pagadas)
        $vaultQuotaFromVaultRows = 0.0;

        try {
            $cuentaIdForVault = $cuenta->id ?? $cuenta->cuenta_id ?? null;

            if ($cuentaIdForVault) {
                // Debe coincidir con SatCartController::vaultPricing()
                $vaultPricing = [
                    5    => 249.0,
                    10   => 449.0,
                    20   => 799.0,
                    50   => 1499.0,
                    100  => 2499.0,
                    500  => 7999.0,
                    1024 => 12999.0, // 1 TB
                ];

                /** @var \Illuminate\Support\Collection $vaultRowsPaid */
                $vaultRowsPaid = SatDownload::query()
                    ->where('cuenta_id', $cuentaIdForVault)
                    ->where(function ($q) {
                        $q->where('tipo', 'VAULT')
                          ->orWhere('tipo', 'BOVEDA');
                    })
                    ->where(function ($q) {
                        // Sin campo is_paid; usamos paid_at / status
                        $q->whereNotNull('paid_at')
                          ->orWhereIn('status', ['PAID', 'paid', 'PAGADO', 'pagado']);
                    })
                    ->get();

                $totalGbFromVault = 0.0;

                foreach ($vaultRowsPaid as $vr) {
                    // 4.1) GB explícitos
                    $gb = (float) ($vr->vault_gb ?? $vr->gb ?? 0);

                    // 4.2) Inferir desde costo
                    if ($gb <= 0) {
                        $cost = (float) ($vr->costo_mxn ?? $vr->costo ?? 0);
                        if ($cost > 0) {
                            foreach ($vaultPricing as $gbOpt => $priceOpt) {
                                if (abs($priceOpt - $cost) < 0.5) {
                                    $gb = (float) $gbOpt;
                                    break;
                                }
                            }
                        }
                    }

                    // 4.3) Parsear del alias "Bóveda fiscal 10 GB (nube)"
                    if ($gb <= 0) {
                        $source = (string) ($vr->alias ?? $vr->nombre ?? '');
                        if (preg_match('/(\d+)\s*gb/i', $source, $m)) {
                            $gb = (float) $m[1];
                        }
                    }

                    if ($gb > 0) {
                        $totalGbFromVault += $gb;
                    }
                }

                $vaultQuotaFromVaultRows = $totalGbFromVault;

                Log::info('[SatVaultQuota] calc', [
                    'cuenta_id'    => $cuentaIdForVault,
                    'rows'         => $vaultRowsPaid->count(),
                    'gb_from_rows' => $vaultQuotaFromVaultRows,
                    'acct_quota'   => $vaultQuotaFromAccount,
                    'base_gb'      => $vaultBaseGb,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[SatVaultQuota] Error calculando cuota de bóveda desde VAULT', [
                'cuenta_id' => $cuenta->id ?? null,
                'error'     => $e->getMessage(),
            ]);
        }

        // 5) Cuota total
        $vaultQuotaGb = max(
            $vaultBaseGb,
            $vaultQuotaFromAccount,
            $vaultQuotaFromVaultRows
        );

        // 6) Comprado vs base
        $vaultPurchasedGb = max(0.0, $vaultQuotaGb - $vaultBaseGb);

        // 7) Disponibilidad
        $vaultAvailableGb = $vaultQuotaGb > 0
            ? max(0.0, $vaultQuotaGb - $vaultUsedGb)
            : 0.0;

        $vaultUsedPct = $vaultQuotaGb > 0
            ? min(100, (int) round(($vaultUsedGb / $vaultQuotaGb) * 100))
            : 0;

        $vaultFreePct = $vaultQuotaGb > 0
            ? max(0, 100 - $vaultUsedPct)
            : 0;

        $vaultActive = $vaultQuotaGb > 0;

        return [
            'active'       => $vaultActive,
            'quota_gb'     => $vaultQuotaGb,
            'base_gb'      => $vaultBaseGb,
            'purchased_gb' => $vaultPurchasedGb,
            'used_gb'      => $vaultUsedGb,
            'available_gb' => $vaultAvailableGb,
            'used_pct'     => $vaultUsedPct,
            'free_pct'     => $vaultFreePct,
        ];
    }
}
