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
        $aidStr = (string) $accountId;
        $aidInt = (int) $accountId;

        if ($aidInt <= 0 && $aidStr === '0') return;

        try {
            if (!Schema::connection($adm)->hasTable('accounts')) return;
            if (!Schema::connection($adm)->hasTable('billing_statements')) return;

            $hasEstado = Schema::connection($adm)->hasColumn('accounts', 'estado_cuenta');
            $hasBilling = Schema::connection($adm)->hasColumn('accounts', 'billing_status');
            if (!$hasEstado && !$hasBilling) return;

            // pendiente real si saldo>0 y status != paid
            $pending = DB::connection($adm)->table('billing_statements')
                ->whereIn('account_id', [$aidStr, $aidInt])
                ->whereRaw('COALESCE(saldo,0) > 0')
                ->whereRaw('LOWER(COALESCE(status,"")) <> "paid"')
                ->exists();

            $upd = ['updated_at' => now()];
            if ($hasEstado)  $upd['estado_cuenta']  = $pending ? 'pendiente' : 'activa';
            if ($hasBilling) $upd['billing_status'] = $pending ? 'overdue' : 'active';

            DB::connection($adm)->table('accounts')
                ->whereIn('id', [$aidStr, $aidInt])
                ->update($upd);

            Log::info('[BILLING_STATE_SYNC] ok', [
                'account_id' => $aidStr,
                'pending'    => $pending,
                'reason'     => $reason,
                'upd'        => $upd,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[BILLING_STATE_SYNC] fail', [
                'account_id' => (string)$accountId,
                'reason'     => $reason,
                'err'        => $e->getMessage(),
            ]);
        }
    }
}
