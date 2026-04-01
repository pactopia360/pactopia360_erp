<?php
// app/Services/Admin/Billing/AccountBillingStateService.php

declare(strict_types=1);

namespace App\Services\Admin\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class AccountBillingStateService
{
    /**
     * Sincroniza accounts.estado_cuenta + accounts.billing_status según billing_statements.
     * Regla:
     * - Si existe al menos 1 statement con saldo>0 y status != paid => cuenta pendiente/overdue
     * - Si NO => activa/active
     *
     * IMPORTANTE:
     * - NO toca is_blocked (tu paywall real manda por is_blocked + webhook).
     */
    public static function sync(int|string $accountId, ?string $reason = null): void
    {
        $adm = (string) config('p360.conn.admin', 'mysql_admin');
        $aidStr = trim((string) $accountId);
        $aidInt = is_numeric($accountId) ? (int) $accountId : 0;

        if ($aidStr === '') {
            return;
        }

        try {
            if (!Schema::connection($adm)->hasTable('accounts')) return;
            if (!Schema::connection($adm)->hasTable('billing_statements')) return;

            $hasEstado  = Schema::connection($adm)->hasColumn('accounts', 'estado_cuenta');
            $hasBilling = Schema::connection($adm)->hasColumn('accounts', 'billing_status');
            if (!$hasEstado && !$hasBilling) return;

            $ids = array_values(array_unique(array_filter([
                $aidStr,
                $aidInt > 0 ? (string) $aidInt : null,
            ])));

            $hasOverrides = Schema::connection($adm)->hasTable('billing_statement_status_overrides');

            $rows = DB::connection($adm)->table('billing_statements')
                ->whereIn('account_id', $ids)
                ->orderByDesc('period')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'account_id',
                    'period',
                    'status',
                    'saldo',
                    'paid_at',
                ]);

            $overridePaid = [];
            if ($hasOverrides) {
                $ovRows = DB::connection($adm)->table('billing_statement_status_overrides')
                    ->whereIn('account_id', $ids)
                    ->where('status_override', 'pagado')
                    ->get(['account_id', 'period']);

                foreach ($ovRows as $ov) {
                    $k = trim((string) $ov->account_id) . '|' . trim((string) $ov->period);
                    $overridePaid[$k] = true;
                }
            }

            $pending = false;

            foreach ($rows as $row) {
                $period = trim((string) ($row->period ?? ''));
                $saldo  = round(max(0.0, (float) ($row->saldo ?? 0)), 2);
                $status = strtolower(trim((string) ($row->status ?? '')));
                $paidAt = $row->paid_at ?? null;

                if ($saldo <= 0.00001) {
                    continue;
                }

                if (!empty($paidAt)) {
                    continue;
                }

                if (in_array($status, ['paid', 'pagado', 'complete', 'completed', 'success', 'succeeded', 'captured'], true)) {
                    continue;
                }

                $key1 = trim((string) ($row->account_id ?? '')) . '|' . $period;
                if (isset($overridePaid[$key1])) {
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
                ->whereIn('id', $ids)
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
