<?php
// app/Services/Admin/Billing/AccountBillingStateService.php

declare(strict_types=1);

namespace App\Services\Admin\Billing;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class AccountBillingStateService
{
    /**
     * Sincroniza accounts.estado_cuenta + accounts.billing_status
     * usando cobertura financiera acumulada real:
     *
     * REGRA NUEVA:
     * - Suma cargos exigibles acumulados hasta el periodo actual
     * - Suma pagos reales válidos acumulados en payments
     * - Si pagos acumulados >= cargos acumulados exigibles => activa
     * - Si pagos acumulados < cargos acumulados exigibles => pendiente
     *
     * NOTAS:
     * - NO depende principalmente de overrides visuales
     * - Sí respeta overrides "pagado" como excepción operativa por periodo
     * - Sí respeta subscriptions activas/anuales con current_period_end futuro
     * - NO toca is_blocked
     * - NO inventa ingresos
     */
    public static function sync(int|string $accountId, ?string $reason = null): void
    {
        $adm    = (string) config('p360.conn.admin', 'mysql_admin');
        $aidStr = trim((string) $accountId);
        $aidInt = is_numeric($accountId) ? (int) $accountId : 0;

        if ($aidStr === '') {
            return;
        }

        try {
            if (!Schema::connection($adm)->hasTable('accounts')) {
                return;
            }

            if (!Schema::connection($adm)->hasTable('billing_statements')) {
                return;
            }

            $hasEstado  = Schema::connection($adm)->hasColumn('accounts', 'estado_cuenta');
            $hasBilling = Schema::connection($adm)->hasColumn('accounts', 'billing_status');

            if (!$hasEstado && !$hasBilling) {
                return;
            }

            $accountIds = array_values(array_unique(array_filter([
                $aidStr,
                $aidInt > 0 ? (string) $aidInt : null,
                $aidInt > 0 ? $aidInt : null,
            ], static fn ($v) => $v !== null && $v !== '')));

            $account = DB::connection($adm)->table('accounts')
                ->whereIn('id', array_values(array_filter([
                    $aidStr,
                    $aidInt > 0 ? $aidInt : null,
                ], static fn ($v) => $v !== null && $v !== '')))
                ->orderByDesc('id')
                ->first([
                    'id',
                    'plan',
                    'plan_actual',
                    'modo_cobro',
                    'estado_cuenta',
                    'billing_status',
                ]);

            if (!$account) {
                return;
            }

            $currentPeriod = now()->format('Y-m');

            // -------------------------------------------------
            // 1) Cobertura activa por subscription vigente
            // -------------------------------------------------
            $hasActiveCoverage = false;

            if (Schema::connection($adm)->hasTable('subscriptions')) {
                $sub = DB::connection($adm)->table('subscriptions')
                    ->where('account_id', (int) $account->id)
                    ->whereIn('status', ['active', 'trialing', 'paid'])
                    ->orderByDesc('current_period_end')
                    ->first(['plan', 'status', 'current_period_end']);

                if ($sub && !empty($sub->current_period_end)) {
                    try {
                        $end = Carbon::parse((string) $sub->current_period_end);
                        if ($end->endOfDay()->gte(now())) {
                            $hasActiveCoverage = true;
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }

            $modoCobro = strtolower(trim((string) ($account->modo_cobro ?? '')));
            $plan      = strtolower(trim((string) ($account->plan_actual ?? $account->plan ?? '')));

            if (
                str_contains($modoCobro, 'anual') ||
                str_contains($plan, 'anual') ||
                str_contains($plan, 'annual')
            ) {
                if ($hasActiveCoverage) {
                    $upd = ['updated_at' => now()];

                    if ($hasEstado) {
                        $upd['estado_cuenta'] = 'activa';
                    }

                    if ($hasBilling) {
                        $upd['billing_status'] = 'active';
                    }

                    DB::connection($adm)->table('accounts')
                        ->where('id', (int) $account->id)
                        ->update($upd);

                    Log::info('[BILLING_STATE_SYNC] active by subscription coverage', [
                        'account_id' => $aidStr,
                        'reason'     => $reason,
                        'upd'        => $upd,
                    ]);

                    return;
                }
            }

            // -------------------------------------------------
            // 2) Overrides "pagado" por periodo (solo apoyo operativo)
            // -------------------------------------------------
            $overridePaid = [];

            if (Schema::connection($adm)->hasTable('billing_statement_status_overrides')) {
                $ovRows = DB::connection($adm)->table('billing_statement_status_overrides')
                    ->where('account_id', (string) $account->id)
                    ->where('status_override', 'pagado')
                    ->get(['period']);

                foreach ($ovRows as $ov) {
                    $p = trim((string) ($ov->period ?? ''));
                    if ($p !== '' && self::isValidPeriod($p)) {
                        $overridePaid[$p] = true;
                    }
                }
            }

            // -------------------------------------------------
            // 3) Total pagado real acumulado en payments
            // -------------------------------------------------
            $totalPaid = self::sumAllValidPaymentsMxn($adm, $accountIds);

            // -------------------------------------------------
            // 4) Cargos exigibles acumulados hasta el periodo actual
            //    - solo periodos <= actual
            //    - solo cargos > 0
            //    - si el periodo tiene override "pagado", se excluye
            // -------------------------------------------------
            $statementRows = DB::connection($adm)->table('billing_statements')
                ->where('account_id', (string) $account->id)
                ->where('period', '<=', $currentPeriod)
                ->orderBy('period')
                ->orderBy('id')
                ->get([
                    'id',
                    'period',
                    'total_cargo',
                    'saldo',
                    'status',
                    'paid_at',
                ]);

            $requiredCharges = 0.0;
            $evaluatedPeriods = [];
            $periodBreakdown = [];

            foreach ($statementRows as $row) {
                $period = trim((string) ($row->period ?? ''));

                if ($period === '' || !self::isValidPeriod($period)) {
                    continue;
                }

                $cargo = round(max(0.0, (float) ($row->total_cargo ?? 0)), 2);

                if ($cargo <= 0.00001) {
                    continue;
                }

                $statusNorm = strtolower(trim((string) ($row->status ?? '')));
                if (in_array($statusNorm, ['void', 'cancelled', 'canceled'], true)) {
                    continue;
                }

                if (isset($overridePaid[$period])) {
                    $periodBreakdown[] = [
                        'period' => $period,
                        'cargo'  => $cargo,
                        'mode'   => 'override_paid_excluded',
                    ];
                    continue;
                }

                $requiredCharges += $cargo;
                $evaluatedPeriods[] = $period;

                $periodBreakdown[] = [
                    'period' => $period,
                    'cargo'  => $cargo,
                    'mode'   => 'required',
                ];
            }

            $requiredCharges = round($requiredCharges, 2);
            $totalPaid       = round($totalPaid, 2);

            // -------------------------------------------------
            // 5) Resultado global real
            // -------------------------------------------------
            $pending = $totalPaid + 0.00001 < $requiredCharges;
            $credit  = round(max(0.0, $totalPaid - $requiredCharges), 2);
            $debt    = round(max(0.0, $requiredCharges - $totalPaid), 2);

            $upd = ['updated_at' => now()];

            if ($hasEstado) {
                $upd['estado_cuenta'] = $pending ? 'pendiente' : 'activa';
            }

            if ($hasBilling) {
                $upd['billing_status'] = $pending ? 'overdue' : 'active';
            }

            DB::connection($adm)->table('accounts')
                ->where('id', (int) $account->id)
                ->update($upd);

            Log::info('[BILLING_STATE_SYNC] ok', [
                'account_id'        => $aidStr,
                'reason'            => $reason,
                'current_period'    => $currentPeriod,
                'total_paid'        => $totalPaid,
                'required_charges'  => $requiredCharges,
                'credit'            => $credit,
                'debt'              => $debt,
                'pending'           => $pending,
                'evaluated_periods' => $evaluatedPeriods,
                'period_breakdown'  => $periodBreakdown,
                'upd'               => $upd,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[BILLING_STATE_SYNC] fail', [
                'account_id' => $aidStr,
                'reason'     => $reason,
                'err'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Suma todos los pagos válidos reales de la cuenta.
     * No depende del period del pago.
     */
    private static function sumAllValidPaymentsMxn(string $adm, array $accountIds): float
    {
        if (empty($accountIds)) {
            return 0.0;
        }

        if (!Schema::connection($adm)->hasTable('payments')) {
            return 0.0;
        }

        $payCols = Schema::connection($adm)->getColumnListing('payments');
        $payLc   = array_map('strtolower', $payCols);
        $hasPay  = static fn (string $c): bool => in_array(strtolower($c), $payLc, true);

        if (!$hasPay('account_id') || !$hasPay('status')) {
            return 0.0;
        }

        $amountExpr = null;

        if ($hasPay('amount_mxn')) {
            $amountExpr = 'COALESCE(amount_mxn,0)';
        } elseif ($hasPay('monto_mxn')) {
            $amountExpr = 'COALESCE(monto_mxn,0)';
        } elseif ($hasPay('amount_cents')) {
            $amountExpr = 'COALESCE(amount_cents,0)/100';
        } elseif ($hasPay('amount')) {
            $amountExpr = 'COALESCE(amount,0)/100';
        }

        if ($amountExpr === null) {
            return 0.0;
        }

        $sum = DB::connection($adm)->table('payments')
            ->whereIn('account_id', $accountIds)
            ->whereIn(DB::raw('LOWER(status)'), [
                'paid',
                'pagado',
                'succeeded',
                'success',
                'completed',
                'complete',
                'captured',
                'authorized',
                'paid_ok',
                'ok',
            ])
            ->selectRaw("SUM({$amountExpr}) as s")
            ->value('s');

        return round((float) ($sum ?? 0), 2);
    }

    private static function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', trim($period));
    }
}