<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\Billing\Concerns\LastPaidHelper.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait LastPaidHelper
{
    /**
     * Requiere en el controller:
     * - protected/private string $adm
     * - protected/private array $cacheLastPaid
     * - method resolveLastPaidPeriodFromOverrides(string $accountId): ?string
     */

    private function resolveLastPaidPeriodForAccount(string $accountId, array $meta): ?string
    {
        $key = trim((string) $accountId);
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->cacheLastPaid)) {
            return $this->cacheLastPaid[$key];
        }

        $lastPaid = $this->resolveLastPaidPeriodFromMeta($meta);

        if (!$lastPaid) {
            $lastPaid = $this->resolveLastPaidPeriodFromPayments($key);
        }

        // SOT: override pagado también cierra el mes.
        try {
            $overridePaid = $this->resolveLastPaidPeriodFromOverrides($key);
            if ($overridePaid && $this->isValidPeriod($overridePaid)) {
                if (!$lastPaid || $lastPaid < $overridePaid) {
                    $lastPaid = $overridePaid;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $this->cacheLastPaid[$key] = $lastPaid;
        return $lastPaid;
    }

    private function resolveLastPaidPeriodFromMeta(array $meta): ?string
    {
        try {
            foreach ([
                data_get($meta, 'stripe.last_paid_at'),
                data_get($meta, 'stripe.lastPaidAt'),
                data_get($meta, 'billing.last_paid_at'),
                data_get($meta, 'billing.lastPaidAt'),
                data_get($meta, 'last_paid_at'),
                data_get($meta, 'lastPaidAt'),
            ] as $value) {
                $period = $this->parseToPeriod($value);
                if ($period && $this->isValidPeriod($period)) {
                    return $period;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    private function resolveLastPaidPeriodFromPayments(string $accountId): ?string
    {
        try {
            if (!Schema::connection($this->adm)->hasTable('payments')) {
                return null;
            }

            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id') || !$has('status') || !$has('period')) {
                return null;
            }

            $orderCol = $has('paid_at')
                ? 'paid_at'
                : ($has('created_at')
                    ? 'created_at'
                    : ($has('id') ? 'id' : $cols[0]));

            $row = DB::connection($this->adm)->table('payments')
                ->where('account_id', $accountId)
                ->whereIn('status', [
                    'paid',
                    'succeeded',
                    'success',
                    'completed',
                    'complete',
                    'captured',
                    'authorized',
                ])
                ->orderByDesc($orderCol)
                ->first(['period']);

            if (!$row || empty($row->period)) {
                return null;
            }

            $period = $this->parseToPeriod($row->period);
            return ($period && $this->isValidPeriod($period)) ? $period : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period);
    }

    private function parseToPeriod(mixed $value): ?string
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->format('Y-m');
            }

            if (is_numeric($value)) {
                $ts = (int) $value;
                if ($ts > 0) {
                    return Carbon::createFromTimestamp($ts)->format('Y-m');
                }
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    return null;
                }

                $value = str_replace('/', '-', $value);

                if ($this->isValidPeriod($value)) {
                    return $value;
                }

                if (preg_match('/^\d{4}-(0[1-9]|1[0-2])-\d{2}$/', $value)) {
                    return Carbon::parse($value)->format('Y-m');
                }

                return Carbon::parse($value)->format('Y-m');
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}