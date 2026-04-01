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
     * usando saldo real basado en payments vs billing_statements.total_cargo.
     *
     * REGLA:
     * - Una cuenta queda pendiente si existe al menos 1 periodo con cargo > pagos aplicados
     * - Respeta override "pagado" por periodo
     * - Respeta subscriptions activas con current_period_end futuro para evitar falso overdue
     *
     * IMPORTANTE:
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

            $ids = array_values(array_unique(array_filter([
                $aidStr,
                $aidInt > 0 ? (string) $aidInt : null,
            ])));

            $account = DB::connection($adm)->table('accounts')
                ->whereIn('id', $ids)
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

            $overridePaid = [];
            if (Schema::connection($adm)->hasTable('billing_statement_status_overrides')) {
                $ovRows = DB::connection($adm)->table('billing_statement_status_overrides')
                    ->where('account_id', (string) $account->id)
                    ->where('status_override', 'pagado')
                    ->get(['period']);

                foreach ($ovRows as $ov) {
                    $p = trim((string) ($ov->period ?? ''));
                    if ($p !== '') {
                        $overridePaid[$p] = true;
                    }
                }
            }

            $paymentAmountExpr = null;
            if (Schema::connection($adm)->hasTable('payments')) {
                $payCols = Schema::connection($adm)->getColumnListing('payments');
                $payLc   = array_map('strtolower', $payCols);
                $hasPay  = static fn (string $c): bool => in_array(strtolower($c), $payLc, true);

                if ($hasPay('amount_mxn')) {
                    $paymentAmountExpr = 'COALESCE(amount_mxn,0)';
                } elseif ($hasPay('monto_mxn')) {
                    $paymentAmountExpr = 'COALESCE(monto_mxn,0)';
                } elseif ($hasPay('amount_cents')) {
                    $paymentAmountExpr = 'COALESCE(amount_cents,0)/100';
                } elseif ($hasPay('amount')) {
                    $paymentAmountExpr = 'COALESCE(amount,0)/100';
                }
            }

            $rows = DB::connection($adm)->table('billing_statements')
                ->where('account_id', (string) $account->id)
                ->orderByDesc('period')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'period',
                    'total_cargo',
                    'saldo',
                    'status',
                    'paid_at',
                ]);

            $pending = false;

            foreach ($rows as $row) {
                $period = trim((string) ($row->period ?? ''));
                if ($period === '') {
                    continue;
                }

                // CLAVE:
                // solo periodos anteriores al actual vuelven la cuenta overdue
                if ($period >= $currentPeriod) {
                    continue;
                }

                if (isset($overridePaid[$period])) {
                    continue;
                }

                $cargo = round(max(0.0, (float) ($row->total_cargo ?? 0)), 2);
                if ($cargo <= 0.00001) {
                    continue;
                }

                $paid = 0.0;

                if ($paymentAmountExpr !== null && Schema::connection($adm)->hasTable('payments')) {
                    $q = DB::connection($adm)->table('payments')
                        ->where('account_id', (int) $account->id)
                        ->where(function ($w) use ($period) {
                            $w->where('period', $period)
                            ->orWhere('period', 'like', $period . '%');
                        })
                        ->whereIn(DB::raw('LOWER(status)'), [
                            'paid', 'pagado', 'succeeded', 'success',
                            'completed', 'complete', 'captured', 'authorized',
                            'paid_ok', 'ok',
                        ]);

                    $paid = round((float) ($q->selectRaw("SUM({$paymentAmountExpr}) as s")->value('s') ?? 0), 2);
                }

                $saldoReal = round(max(0.0, $cargo - $paid), 2);

                if ($saldoReal <= 0.00001) {
                    continue;
                }

                $pending = true;
                break;
            }

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
                'account_id' => $aidStr,
                'pending'    => $pending,
                'reason'     => $reason,
                'upd'        => $upd,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[BILLING_STATE_SYNC] fail', [
                'account_id' => $aidStr,
                'reason'     => $reason,
                'err'        => $e->getMessage(),
            ]);
        }
    }
}