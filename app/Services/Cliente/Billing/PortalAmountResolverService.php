<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Cliente\Billing\PortalAmountResolverService.php

declare(strict_types=1);

namespace App\Services\Cliente\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class PortalAmountResolverService
{
    private string $adm;
    private string $cli;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $this->cli = (string) (
            config('p360.conn.clientes')
            ?: config('p360.conn.clients')
            ?: 'mysql_clientes'
        );
    }

    private function toFloat(mixed $v): ?float
    {
        if ($v === null) return null;
        if (is_float($v) || is_int($v)) return (float) $v;
        if (is_numeric($v)) return (float) $v;

        if (is_string($v)) {
            $s = trim($v);
            if ($s === '') return null;
            $s = str_replace(['$', ',', 'MXN', 'mxn', ' '], '', $s);
            return is_numeric($s) ? (float) $s : null;
        }

        return null;
    }

    public function resolveMonthlyCentsForPeriodFromAdminAccount(
        int $accountId,
        string $period,
        ?string $lastPaid,
        ?string $payAllowed
    ): int {
        $period = trim($period);
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return 0;
        }

        $payAllowedUi = trim((string) $payAllowed);
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $payAllowedUi)) {
            $payAllowedUi = $period;
        }

        try {
            if (!Schema::connection($this->adm)->hasTable('accounts')) {
                return 0;
            }

            $cols = Schema::connection($this->adm)->getColumnListing('accounts');
            $lc   = array_map('strtolower', $cols);
            $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

            $select = ['id'];
            foreach (['meta'] as $c) {
                if ($has($c)) $select[] = $c;
            }

            $acc = DB::connection($this->adm)->table('accounts')
                ->where('id', $accountId)
                ->first($select);

            if (!$acc) {
                return 0;
            }

            $meta = [];
            if (isset($acc->meta)) {
                if (is_array($acc->meta)) {
                    $meta = $acc->meta;
                } elseif (is_string($acc->meta) && trim($acc->meta) !== '') {
                    $tmp = json_decode($acc->meta, true);
                    if (is_array($tmp)) $meta = $tmp;
                }
            }

            $billing  = is_array($meta['billing'] ?? null) ? $meta['billing'] : [];
            $override = is_array($billing['override'] ?? null) ? $billing['override'] : [];

            $baseMxn = $this->toFloat(
                $billing['amount_mxn']
                ?? $billing['monthly_amount_mxn']
                ?? $billing['price_mxn']
                ?? $billing['mensualidad_mxn']
                ?? null
            );

            $overrideMxn = $this->toFloat(
                $override['amount_mxn']
                ?? $billing['override_amount_mxn']
                ?? null
            );

            $overrideEffective = strtolower(trim((string) (
                $override['effective']
                ?? $billing['override_effective']
                ?? ''
            )));

            if (!in_array($overrideEffective, ['now', 'next'], true)) {
                $overrideEffective = '';
            }

            $resolvedMetaMxn = null;

            if ($overrideMxn !== null && $overrideMxn > 0.00001) {
                if ($overrideEffective === 'now') {
                    $resolvedMetaMxn = $overrideMxn;
                } elseif ($overrideEffective === 'next') {
                    $resolvedMetaMxn = ($period >= $payAllowedUi)
                        ? $overrideMxn
                        : ($baseMxn ?? null);
                } else {
                    $resolvedMetaMxn = $overrideMxn;
                }
            } elseif ($baseMxn !== null && $baseMxn > 0.00001) {
                $resolvedMetaMxn = $baseMxn;
            }

            if ($resolvedMetaMxn !== null && $resolvedMetaMxn > 0.00001) {
                return max(0, (int) round($resolvedMetaMxn * 100));
            }

            $explicitFallbacks = [
                data_get($meta, 'billing.custom.amount_mxn'),
                data_get($meta, 'billing.custom_mxn'),
                data_get($meta, 'custom.amount_mxn'),
                data_get($meta, 'custom_mxn'),
            ];

            foreach ($explicitFallbacks as $v) {
                $mxn = $this->toFloat($v);
                if ($mxn !== null && $mxn > 0.00001) {
                    return max(0, (int) round($mxn * 100));
                }
            }

            // ✅ NO usar legacy global fields:
            // billing_amount_mxn / amount_mxn / precio_mxn / monto_mxn
            // porque son la fuente de contaminación tipo 1209.30

            return 0;
        } catch (\Throwable $e) {
            Log::warning('[CLIENTE][BILLING] resolveMonthlyCentsForPeriodFromAdminAccount failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
            return 0;
        }
    }

    public function resolveMonthlyCentsFromPlanesCatalog(int $accountId): int
    {
        if (!Schema::connection($this->adm)->hasTable('accounts')) return 0;
        if (!Schema::connection($this->adm)->hasTable('planes')) return 0;

        try {
            $acc = DB::connection($this->adm)->table('accounts')
                ->select(['id', 'plan', 'plan_actual', 'modo_cobro'])
                ->where('id', $accountId)
                ->first();

            if (!$acc) return 0;

            $planKey = trim((string) ($acc->plan_actual ?: $acc->plan));
            if ($planKey === '') return 0;

            $cycle = strtolower(trim((string) ($acc->modo_cobro ?: 'mensual')));
            $cycle = in_array($cycle, ['anual', 'annual', 'year', 'yearly'], true) ? 'anual' : 'mensual';

            $plan = DB::connection($this->adm)->table('planes')
                ->select(['clave', 'precio_mensual', 'precio_anual', 'activo'])
                ->where('clave', $planKey)
                ->first();

            if (!$plan) return 0;
            if (isset($plan->activo) && (int) $plan->activo !== 1) return 0;

            $monthly = (float) ($plan->precio_mensual ?? 0);
            $annual  = (float) ($plan->precio_anual ?? 0);

            if ($cycle === 'anual' && $annual > 0) {
                $monthly = round($annual / 12.0, 2);
            }

            return $monthly > 0 ? (int) round($monthly * 100) : 0;
        } catch (\Throwable $e) {
            Log::warning('[CLIENTE][BILLING] resolveMonthlyCentsFromPlanesCatalog failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
            return 0;
        }
    }

    public function resolvePortalMonthlyMxnForPeriod(
        int $accountId,
        string $period,
        ?string $lastPaid,
        ?string $payAllowed,
        array $chargesByPeriod = []
    ): float {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return 0.0;
        }

        if (isset($chargesByPeriod[$period]) && is_numeric($chargesByPeriod[$period])) {
            $mxn = round((float) $chargesByPeriod[$period], 2);
            if ($mxn > 0.0001) {
                return $mxn;
            }
        }

        $cents = $this->resolveMonthlyCentsForPeriodFromAdminAccount($accountId, $period, $lastPaid, $payAllowed);
        if ($cents <= 0) {
            $cents = $this->resolveMonthlyCentsFromPlanesCatalog($accountId);
        }

        return $cents > 0 ? round($cents / 100, 2) : 0.0;
    }
}