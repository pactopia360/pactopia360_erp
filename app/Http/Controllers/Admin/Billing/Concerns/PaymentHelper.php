<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\Billing\Concerns\PaymentHelper.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait PaymentHelper
{
    private function isAutoCreatePaymentOnOverrideEnabled(): bool
    {
        return (bool) env('BILLING_AUTO_CREATE_PAYMENT_ON_OVERRIDE', false);
    }

    private function hasPaymentsTable(): bool
    {
        return Schema::connection($this->adm)->hasTable('payments');
    }

    /**
     * @return array{0: array<int,string>, 1: callable(string): bool}
     */
    private function paymentsColumnsMeta(): array
    {
        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);

        $has = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

        return [$cols, $has];
    }

    /**
     * @param array<int,string> $cols
     */
    private function paymentsPrimaryOrderColumn(array $cols, callable $has): string
    {
        if ($has('id')) {
            return 'id';
        }

        return $cols[0] ?? 'id';
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPaidPaymentRow(
        string $accountId,
        string $period,
        float $amountMxn,
        string $method = 'manual',
        string $provider = 'manual'
    ): array {
        [$cols, $has] = $this->paymentsColumnsMeta();

        $row = [
            'updated_at' => now(),
        ];

        $row['account_id'] = (int) $accountId;

        if ($has('period')) {
            $row['period'] = $period;
        }

        if ($has('status')) {
            $row['status'] = 'paid';
        }

        if ($has('provider')) {
            $row['provider'] = $provider !== '' ? $provider : 'manual';
        }

        if ($has('method')) {
            $row['method'] = $method !== '' ? $method : 'manual';
        }

        if ($has('currency')) {
            $row['currency'] = 'MXN';
        }

        if ($has('paid_at')) {
            $row['paid_at'] = now();
        }

        if ($has('amount_mxn')) {
            $row['amount_mxn'] = $amountMxn;
        }

        if ($has('amount')) {
            $row['amount'] = (int) round($amountMxn * 100);
        }

        if ($has('concept')) {
            $row['concept'] = 'billing_statement';
        }

        if ($has('reference')) {
            $row['reference'] = 'admin_paid:' . $accountId . ':' . $period;
        }

        if ($has('meta')) {
            $row['meta'] = json_encode([
                'type'   => 'billing_statement',
                'period' => $period,
                'source' => 'admin_statusAjax',
                'note'   => 'Pago manual PAID por override pagado (SOT). No toca Stripe pending.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $row;
    }

    private function findExistingManualPaidPaymentId(string $accountId, string $period): ?int
    {
        if (!$this->hasPaymentsTable()) {
            return null;
        }

        [$cols, $has] = $this->paymentsColumnsMeta();

        if (!$has('account_id') || !$has('reference')) {
            return null;
        }

        try {
            $ref   = 'admin_paid:' . $accountId . ':' . $period;
            $idCol = $this->paymentsPrimaryOrderColumn($cols, $has);

            $q = DB::connection($this->adm)->table('payments')
                ->where('account_id', $accountId)
                ->where('reference', $ref);

            if ($has('period')) {
                $this->applyPaymentsPeriodFilter($q, $period, true);
            }

            $row = $q->orderByDesc($idCol)->first([$idCol]);

            if ($row && isset($row->{$idCol})) {
                return (int) $row->{$idCol};
            }
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] findExistingManualPaidPaymentId failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * ✅ Upsert de payment manual "paid" SOLO si está habilitado por ENV.
     * SOT: por default NO crea payments al marcar override pagado.
     */
    private function upsertPaidPaymentForStatement(
        string $accountId,
        string $period,
        float $amountMxn,
        string $method = 'manual',
        string $provider = 'manual'
    ): void {
        if (!$this->isAutoCreatePaymentOnOverrideEnabled()) {
            return;
        }

        if (!$this->hasPaymentsTable()) {
            return;
        }

        $amountMxn = round(max(0.0, $amountMxn), 2);
        if ($amountMxn <= 0.00001) {
            return;
        }

        try {
            [$cols, $has] = $this->paymentsColumnsMeta();

            if (!$has('account_id')) {
                return;
            }

            $existingId = $this->findExistingManualPaidPaymentId($accountId, $period);
            $row        = $this->buildPaidPaymentRow($accountId, $period, $amountMxn, $method, $provider);

            DB::connection($this->adm)->transaction(function () use ($existingId, $row, $accountId) {
                if ($existingId !== null) {
                    DB::connection($this->adm)
                        ->table('payments')
                        ->where('id', $existingId)
                        ->update($row);

                    return;
                }

                $insert = $row;
                $insert['created_at'] = now();

                $aid = (int) ($insert['account_id'] ?? 0);
                if ($aid <= 0) {
                    $aid = (int) $accountId;
                }

                if ($aid <= 0) {
                    throw new \RuntimeException('payments insert blocked: missing account_id');
                }

                $insert['account_id'] = $aid;

                DB::connection($this->adm)->table('payments')->insert($insert);
            });
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] upsertPaidPaymentForStatement failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Legacy opcional.
     * SOT: por default NO crea movimientos financieros al marcar override pagado.
     */
    private function insertManualPaidPaymentForStatement(
        string $accountId,
        string $period,
        float $amountMxn,
        string $method
    ): void {
        if (!$this->isAutoCreatePaymentOnOverrideEnabled()) {
            return;
        }

        if (!$this->hasPaymentsTable()) {
            return;
        }

        $amountMxn = round(max(0.0, $amountMxn), 2);
        if ($amountMxn <= 0.00001) {
            return;
        }

        [$cols, $has] = $this->paymentsColumnsMeta();

        if (!$has('account_id')) {
            return;
        }

        $payload = [
            'updated_at' => now(),
            'created_at' => now(),
            'account_id' => (int) $accountId,
        ];

        if ($has('period')) {
            $payload['period'] = $period;
        }

        if ($has('status')) {
            $payload['status'] = 'paid';
        }

        if ($has('provider')) {
            $payload['provider'] = 'manual';
        }

        if ($has('method')) {
            $payload['method'] = $method !== '' ? $method : 'manual';
        }

        if ($has('amount_mxn')) {
            $payload['amount_mxn'] = $amountMxn;
        }

        if ($has('amount')) {
            $payload['amount'] = (int) round($amountMxn * 100);
        }

        if ($has('currency')) {
            $payload['currency'] = 'MXN';
        }

        if ($has('paid_at')) {
            $payload['paid_at'] = now();
        }

        if ($has('due_date')) {
            $payload['due_date'] = now()->addDays(4);
        }

        if ($has('concept')) {
            $payload['concept'] = 'Pago manual (admin) · Estado de cuenta ' . $period;
        }

        if ($has('reference')) {
            $payload['reference'] = 'admin_mark_paid:' . $accountId . ':' . $period . ':' . now()->format('YmdHis');
        }

        if ($has('meta')) {
            $payload['meta'] = json_encode([
                'type'   => 'billing_statement',
                'period' => $period,
                'source' => 'admin_statusAjax',
                'note'   => 'Pago manual para cerrar saldo por override pagado (LEGACY)',
            ], JSON_UNESCAPED_UNICODE);
        }

        $aid = (int) ($payload['account_id'] ?? 0);
        if ($aid <= 0) {
            throw new \RuntimeException('payments insert blocked: missing account_id (payload)');
        }

        DB::connection($this->adm)->table('payments')->insert($payload);
    }
}