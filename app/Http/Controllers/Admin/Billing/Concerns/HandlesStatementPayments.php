<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\Billing\Concerns\HandlesStatementPayments.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Stripe\StripeClient;

trait HandlesStatementPayments
{
    private function applyPaymentsPeriodFilter($q, string $period, bool $hasPeriodCol): void
    {
        if (!$hasPeriodCol) {
            return;
        }

        $p = trim($period);
        if (!$this->isValidPeriod($p)) {
            $pp = $this->parseToPeriod($p);
            if (!$pp) {
                return;
            }
            $p = $pp;
        }

        $q->where(function ($w) use ($p) {
            $w->where('period', $p)
              ->orWhere('period', 'like', $p . '%');
        });
    }

    private function hasPaymentsForAccountPeriod(string $accountId, string $period): bool
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return false;
        }

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn(string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id') || !$has('period')) {
                return false;
            }

            $q = DB::connection($this->adm)->table('payments')
                ->where('account_id', $accountId);

            $this->applyPaymentsPeriodFilter($q, $period, $has('period'));

            if ($has('status')) {
                $q->whereIn('status', [
                    'paid',
                    'succeeded',
                    'success',
                    'completed',
                    'complete',
                    'captured',
                    'authorized',
                ]);
            } else {
                return false;
            }

            return $q->limit(1)->exists();
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] hasPaymentsForAccountPeriod failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sumPaymentsForAccountPeriod(string $accountId, string $period): float
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return 0.0;
        }

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn(string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id')) {
                return 0.0;
            }

            $amountMxnCol = $has('amount_mxn') ? 'amount_mxn' : null;
            $amountCol    = $has('amount') ? 'amount' : null;
            $amountCentsCol = $has('amount_cents') ? 'amount_cents' : null;
            $montoMxnCol  = $has('monto_mxn') ? 'monto_mxn' : null;

            if (!$amountMxnCol && !$amountCol && !$amountCentsCol && !$montoMxnCol) {
                return 0.0;
            }

            $q = DB::connection($this->adm)->table('payments')
                ->where('account_id', $accountId);

            $this->applyPaymentsPeriodFilter($q, $period, $has('period'));

            if ($has('status')) {
                $q->whereIn('status', [
                    'paid',
                    'succeeded',
                    'success',
                    'completed',
                    'complete',
                    'captured',
                    'authorized',
                ]);
            }

            if ($amountMxnCol) {
                return round((float) ($q->sum($amountMxnCol) ?? 0), 2);
            }

            if ($montoMxnCol) {
                return round((float) ($q->sum($montoMxnCol) ?? 0), 2);
            }

            if ($amountCentsCol) {
                $cents = (float) ($q->sum($amountCentsCol) ?? 0);
                return round($cents / 100.0, 2);
            }

            if ($amountCol) {
                $cents = (float) ($q->sum($amountCol) ?? 0);
                return round($cents / 100.0, 2);
            }

            return 0.0;
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] sumPaymentsForAccountPeriod failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * @param array<int,string|int> $accountIds
     * @return array<string,float>
     */
    private function sumPaymentsForAccountsPeriod(array $accountIds, string $period): array
    {
        $out = [];

        if (empty($accountIds)) {
            return $out;
        }

        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return $out;
        }

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn(string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id')) {
                return $out;
            }

            $amountMxnCol   = $has('amount_mxn') ? 'amount_mxn' : null;
            $montoMxnCol    = $has('monto_mxn') ? 'monto_mxn' : null;
            $amountCol      = $has('amount') ? 'amount' : null;
            $amountCentsCol = $has('amount_cents') ? 'amount_cents' : null;

            if (!$amountMxnCol && !$montoMxnCol && !$amountCol && !$amountCentsCol) {
                return $out;
            }

            $q = DB::connection($this->adm)->table('payments')
                ->whereIn('account_id', $accountIds);

            $this->applyPaymentsPeriodFilter($q, $period, $has('period'));

            if ($has('status')) {
                $q->whereIn('status', [
                    'paid',
                    'succeeded',
                    'success',
                    'completed',
                    'complete',
                    'captured',
                    'authorized',
                ]);
            }

            if ($amountMxnCol) {
                $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(' . $amountMxnCol . ',0)) as s')
                    ->groupBy('account_id')
                    ->get();

                foreach ($rows as $r) {
                    $out[(string) $r->aid] = round((float) ($r->s ?? 0), 2);
                }

                return $out;
            }

            if ($montoMxnCol) {
                $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(' . $montoMxnCol . ',0)) as s')
                    ->groupBy('account_id')
                    ->get();

                foreach ($rows as $r) {
                    $out[(string) $r->aid] = round((float) ($r->s ?? 0), 2);
                }

                return $out;
            }

            if ($amountCentsCol) {
                $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(' . $amountCentsCol . ',0)) as s')
                    ->groupBy('account_id')
                    ->get();

                foreach ($rows as $r) {
                    $out[(string) $r->aid] = round(((float) ($r->s ?? 0)) / 100.0, 2);
                }

                return $out;
            }

            if ($amountCol) {
                $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(' . $amountCol . ',0)) as s')
                    ->groupBy('account_id')
                    ->get();

                foreach ($rows as $r) {
                    $out[(string) $r->aid] = round(((float) ($r->s ?? 0)) / 100.0, 2);
                }

                return $out;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] sumPaymentsForAccountsPeriod failed', [
                'period' => $period,
                'err'    => $e->getMessage(),
            ]);

            return $out;
        }
    }

    /**
     * @param array<int,string|int> $accountIds
     * @return array<string,array{status:?string,method:?string,provider:?string,due_date:mixed,last_paid_at:mixed}>
     */
    private function fetchPaymentsMetaForAccountsPeriod(array $accountIds, string $period): array
    {
        $out = [];

        if (empty($accountIds)) {
            return $out;
        }

        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return $out;
        }

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn(string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id')) {
                return $out;
            }

            $q = DB::connection($this->adm)->table('payments')
                ->whereIn('account_id', $accountIds);

            $this->applyPaymentsPeriodFilter($q, $period, $has('period'));

            $select = ['account_id'];
            foreach (['status', 'method', 'provider', 'due_date', 'paid_at', 'created_at', 'updated_at'] as $c) {
                if ($has($c)) {
                    $select[] = $c;
                }
            }

            $orderCol = $has('paid_at')
                ? 'paid_at'
                : ($has('updated_at')
                    ? 'updated_at'
                    : ($has('created_at')
                        ? 'created_at'
                        : ($has('id') ? 'id' : $cols[0])));

            $rows = $q->select($select)
                ->orderByDesc($orderCol)
                ->get();

            foreach ($rows as $r) {
                $aid = (string) ($r->account_id ?? '');
                if ($aid === '' || isset($out[$aid])) {
                    continue;
                }

                $lastPaidAt = null;
                if ($has('paid_at') && !empty($r->paid_at)) {
                    $lastPaidAt = $r->paid_at;
                } elseif ($has('updated_at') && !empty($r->updated_at)) {
                    $lastPaidAt = $r->updated_at;
                } elseif ($has('created_at') && !empty($r->created_at)) {
                    $lastPaidAt = $r->created_at;
                }

                $out[$aid] = [
                    'status'       => $has('status') ? (string) ($r->status ?? '') : null,
                    'method'       => $has('method') ? (string) ($r->method ?? '') : null,
                    'provider'     => $has('provider') ? (string) ($r->provider ?? '') : null,
                    'due_date'     => $has('due_date') ? ($r->due_date ?? null) : null,
                    'last_paid_at' => $lastPaidAt,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] fetchPaymentsMetaForAccountsPeriod failed', [
                'period' => $period,
                'err'    => $e->getMessage(),
            ]);

            return $out;
        }
    }

    /**
     * @return array{0:string,1:string}
     */
    private function createStripeCheckoutForStatement(object $acc, string $period, float $totalPesos): array
    {
        $secret = (string) config('services.stripe.secret');
        if (trim($secret) === '') {
            throw new \RuntimeException('Stripe secret vacío en config(services.stripe.secret).');
        }

        if (!isset($this->stripe) || !($this->stripe instanceof StripeClient)) {
            $this->stripe = new StripeClient($secret);
        }

        $unitAmountCents = (int) round($totalPesos * 100);
        $accountId       = (string) ($acc->id ?? '');
        $email           = (string) ($acc->email ?? '');

        $successUrl = \Illuminate\Support\Facades\Route::has('cliente.checkout.success')
            ? route('cliente.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}'
            : url('/cliente/checkout/success') . '?session_id={CHECKOUT_SESSION_ID}';

        $cancelUrl = \Illuminate\Support\Facades\Route::has('cliente.estado_cuenta')
            ? route('cliente.estado_cuenta') . '?period=' . urlencode($period)
            : url('/cliente/estado-de-cuenta') . '?period=' . urlencode($period);

        $idempotencyKey = 'admin_stmt:' . $accountId . ':' . $period . ':' . $unitAmountCents;

        $session = $this->stripe->checkout->sessions->create([
            'mode'                 => 'payment',
            'payment_method_types' => ['card'],
            'line_items'           => [[
                'price_data' => [
                    'currency'     => 'mxn',
                    'unit_amount'  => $unitAmountCents,
                    'product_data' => [
                        'name' => 'Pactopia360 · Estado de cuenta ' . $period,
                    ],
                ],
                'quantity' => 1,
            ]],
            'customer_email'      => $email !== '' ? $email : null,
            'client_reference_id' => $accountId,
            'success_url'         => $successUrl,
            'cancel_url'          => $cancelUrl,
            'metadata'            => [
                'type'       => 'billing_statement',
                'account_id' => $accountId,
                'period'     => $period,
                'source'     => 'admin_email',
                'amount_mxn' => round($totalPesos, 2),
            ],
        ], [
            'idempotency_key' => $idempotencyKey,
        ]);

        $sessionId  = (string) ($session->id ?? '');
        $sessionUrl = (string) ($session->url ?? '');

        $this->upsertPendingPaymentForStatement($accountId, $period, $unitAmountCents, $sessionId, $totalPesos);

        return [$sessionUrl, $sessionId];
    }

    private function upsertPendingPaymentForStatement(
        string $accountId,
        string $period,
        int $amountCents,
        string $sessionId,
        float $uiTotalPesos
    ): void {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return;
        }

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn(string $c): bool => in_array(strtolower($c), $lc, true);

        $existing = null;

        if ($has('stripe_session_id') && $sessionId !== '') {
            $existing = DB::connection($this->adm)->table('payments')
                ->where('account_id', $accountId)
                ->where('stripe_session_id', $sessionId)
                ->first();
        }

        if (!$existing && $has('period') && $has('status')) {
            $q = DB::connection($this->adm)->table('payments')
                ->where('account_id', $accountId)
                ->where('status', 'pending')
                ->where('period', $period);

            if ($has('provider')) {
                $q->where('provider', 'stripe');
            }

            $existing = $q->orderByDesc($has('id') ? 'id' : $cols[0])->first();
        }

        $row = [];

        if ($has('account_id')) {
            $row['account_id'] = $accountId;
        }
        if ($has('amount')) {
            $row['amount'] = $amountCents;
        }
        if ($has('amount_cents')) {
            $row['amount_cents'] = $amountCents;
        }
        if ($has('amount_mxn')) {
            $row['amount_mxn'] = round($uiTotalPesos, 2);
        }
        if ($has('monto_mxn')) {
            $row['monto_mxn'] = round($uiTotalPesos, 2);
        }
        if ($has('currency')) {
            $row['currency'] = 'MXN';
        }
        if ($has('status')) {
            $row['status'] = 'pending';
        }
        if ($has('due_date')) {
            $row['due_date'] = now()->addDays(4);
        }
        if ($has('period')) {
            $row['period'] = $period;
        }
        if ($has('method')) {
            $row['method'] = 'card';
        }
        if ($has('provider')) {
            $row['provider'] = 'stripe';
        }
        if ($has('concept')) {
            $row['concept'] = 'Pactopia360 · Estado de cuenta ' . $period;
        }
        if ($has('reference')) {
            $row['reference'] = $sessionId ?: ('admin_stmt:' . $accountId . ':' . $period);
        }
        if ($has('stripe_session_id')) {
            $row['stripe_session_id'] = $sessionId;
        }
        if ($has('meta')) {
            $row['meta'] = json_encode([
                'type'           => 'billing_statement',
                'period'         => $period,
                'ui_total_pesos' => round($uiTotalPesos, 2),
                'source'         => 'admin_email',
            ], JSON_UNESCAPED_UNICODE);
        }

        $row['updated_at'] = now();
        if (!$existing) {
            $row['created_at'] = now();
        }

        if ($existing && $has('id')) {
            DB::connection($this->adm)->table('payments')
                ->where('id', (int) $existing->id)
                ->update($row);
            return;
        }

        $aid = (int) ($row['account_id'] ?? 0);
        if ($aid <= 0 && isset($accountId)) {
            $aid = (int) $accountId;
        }
        if ($aid <= 0) {
            throw new \RuntimeException('payments insert blocked: missing account_id');
        }

        $row['account_id'] = $aid;

        DB::connection($this->adm)->table('payments')->insert($row);
    }
}