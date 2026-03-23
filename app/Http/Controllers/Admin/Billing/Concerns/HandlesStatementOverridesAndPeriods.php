<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\Billing\Concerns\HandlesStatementOverridesAndPeriods.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait HandlesStatementOverridesAndPeriods
{
    // =========================================================
    // PERIOD helpers
    // =========================================================

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
                $v = trim($value);
                if ($v === '') {
                    return null;
                }

                $v = str_replace('/', '-', $v);

                if ($this->isValidPeriod($v)) {
                    return $v;
                }

                if (preg_match('/^\d{4}-(0[1-9]|1[0-2])-\d{2}$/', $v)) {
                    return Carbon::parse($v)->format('Y-m');
                }

                return Carbon::parse($v)->format('Y-m');
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    // =========================================================
    // LAST PAID helpers
    // =========================================================

    private function resolveLastPaidPeriodForAccount(string $accountId, array $meta): ?string
    {
        $key = trim((string) $accountId);
        if ($key === '') {
            return null;
        }

        if (property_exists($this, 'cacheLastPaid') && array_key_exists($key, $this->cacheLastPaid)) {
            return $this->cacheLastPaid[$key];
        }

        $lastPaid = null;

        try {
            foreach ([
                data_get($meta, 'stripe.last_paid_at'),
                data_get($meta, 'stripe.lastPaidAt'),
                data_get($meta, 'billing.last_paid_at'),
                data_get($meta, 'billing.lastPaidAt'),
                data_get($meta, 'last_paid_at'),
                data_get($meta, 'lastPaidAt'),
            ] as $v) {
                $p = $this->parseToPeriod($v);
                if ($p) {
                    $lastPaid = $p;
                    break;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if (!$lastPaid && Schema::connection($this->adm)->hasTable('payments')) {
            try {
                $cols = Schema::connection($this->adm)->getColumnListing('payments');
                $lc   = array_map('strtolower', $cols);
                $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

                if ($has('account_id') && $has('status') && $has('period')) {
                    $q = DB::connection($this->adm)->table('payments')
                        ->where('account_id', $key)
                        ->whereIn('status', [
                            'paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized',
                        ]);

                    $row = $q->orderByDesc(
                        $has('paid_at') ? 'paid_at'
                        : ($has('created_at') ? 'created_at'
                        : ($has('id') ? 'id' : $cols[0]))
                    )->first(['period']);

                    if ($row && !empty($row->period)) {
                        $p = $this->parseToPeriod($row->period);
                        if ($p && $this->isValidPeriod($p)) {
                            $lastPaid = $p;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        try {
            $ovPaid = $this->resolveLastPaidPeriodFromOverrides($key);
            if ($ovPaid && $this->isValidPeriod($ovPaid)) {
                if (!$lastPaid || ($lastPaid < $ovPaid)) {
                    $lastPaid = $ovPaid;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if (property_exists($this, 'cacheLastPaid')) {
            $this->cacheLastPaid[$key] = $lastPaid;
        }

        return $lastPaid;
    }

    // =========================================================
    // STATUS OVERRIDE (cuenta+periodo)
    // =========================================================

    private function overrideTable(): string
    {
        return 'billing_statement_status_overrides';
    }

    /**
     * Si un periodo está marcado "pagado" por override,
     * ese mes debe considerarse cerrado.
     */
    private function isPeriodPaidByOverride(string $accountId, string $period): bool
    {
        try {
            if (!$this->isValidPeriod($period)) {
                return false;
            }

            if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
                return false;
            }

            $cols = Schema::connection($this->adm)->getColumnListing($this->overrideTable());
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('status_override')) {
                return false;
            }

            return DB::connection($this->adm)->table($this->overrideTable())
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->where('status_override', 'pagado')
                ->limit(1)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Devuelve el periodo más reciente marcado como pagado en overrides.
     */
    private function resolveLastPaidPeriodFromOverrides(string $accountId): ?string
    {
        try {
            if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
                return null;
            }

            $cols = Schema::connection($this->adm)->getColumnListing($this->overrideTable());
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id') || !$has('period') || !$has('status_override')) {
                return null;
            }

            $row = DB::connection($this->adm)->table($this->overrideTable())
                ->where('account_id', $accountId)
                ->where('status_override', 'pagado')
                ->orderByDesc('period')
                ->first(['period']);

            if ($row && !empty($row->period)) {
                $p = $this->parseToPeriod($row->period);
                return ($p && $this->isValidPeriod($p)) ? $p : null;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<int,string|int> $accountIds
     * @return array<string, array{
     *   status:string,
     *   reason:?string,
     *   updated_by:?int,
     *   updated_at:?string,
     *   pay_method:?string,
     *   pay_provider:?string,
     *   pay_status:?string,
     *   paid_at:mixed
     * }>
     */
    private function fetchStatusOverridesForAccountsPeriod(array $accountIds, string $period): array
    {
        $out = [];
        if (empty($accountIds)) {
            return $out;
        }

        try {
            if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
                return $out;
            }

            $cols = Schema::connection($this->adm)->getColumnListing($this->overrideTable());
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            $select = ['account_id', 'period', 'status_override', 'reason', 'updated_by', 'updated_at'];

            foreach (['pay_method', 'pay_provider', 'pay_status', 'status', 'paid_at', 'meta'] as $c) {
                if ($has($c)) {
                    $select[] = $c;
                }
            }

            $rows = DB::connection($this->adm)->table($this->overrideTable())
                ->select($select)
                ->where('period', $period)
                ->whereIn('account_id', $accountIds)
                ->get();

            foreach ($rows as $r) {
                $aid = (string) ($r->account_id ?? '');
                if ($aid === '') {
                    continue;
                }

                $metaOv = [];
                if ($has('meta') && isset($r->meta) && $r->meta !== null && $r->meta !== '') {
                    try {
                        $metaOv = is_array($r->meta) ? $r->meta : json_decode((string) $r->meta, true);
                        if (!is_array($metaOv)) {
                            $metaOv = [];
                        }
                    } catch (\Throwable $e) {
                        $metaOv = [];
                    }
                }

                $pm = $has('pay_method') ? (string) ($r->pay_method ?? '') : '';
                $pp = $has('pay_provider') ? (string) ($r->pay_provider ?? '') : '';

                $ps = '';
                if ($has('pay_status')) {
                    $ps = (string) ($r->pay_status ?? '');
                } elseif ($has('status')) {
                    $ps = (string) ($r->status ?? '');
                }

                $paidAt = $has('paid_at') ? ($r->paid_at ?? null) : null;

                if (trim($pm) === '') {
                    $pm = (string) data_get($metaOv, 'pay_method', data_get($metaOv, 'method', ''));
                }
                if (trim($pp) === '') {
                    $pp = (string) data_get($metaOv, 'pay_provider', data_get($metaOv, 'provider', ''));
                }
                if (trim($ps) === '') {
                    $ps = (string) data_get($metaOv, 'pay_status', data_get($metaOv, 'status', ''));
                }
                if ($paidAt === null) {
                    $paidAt = data_get($metaOv, 'paid_at');
                }

                $pm = trim($pm) !== '' ? trim($pm) : null;
                $pp = trim($pp) !== '' ? trim($pp) : null;
                $ps = trim($ps) !== '' ? trim($ps) : null;

                $out[$aid] = [
                    'status'       => (string) ($r->status_override ?? ''),
                    'reason'       => isset($r->reason) ? (string) $r->reason : null,
                    'updated_by'   => isset($r->updated_by) ? (int) $r->updated_by : null,
                    'updated_at'   => isset($r->updated_at) ? (string) $r->updated_at : null,
                    'pay_method'   => $pm,
                    'pay_provider' => $pp,
                    'pay_status'   => $ps,
                    'paid_at'      => $paidAt,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] fetchStatusOverridesForAccountsPeriod failed', [
                'period' => $period,
                'err'    => $e->getMessage(),
            ]);
        }

        return $out;
    }

    private function applyStatusOverride(object $row, ?array $ov): object
    {
        $row->status_auto = (string) ($row->status_pago ?? 'sin_mov');

        $row->ov_pay_method   = null;
        $row->ov_pay_provider = null;
        $row->ov_pay_status   = null;
        $row->ov_paid_at      = null;

        $allowed = ['pendiente', 'parcial', 'pagado', 'vencido', 'sin_mov'];

        if ($ov && isset($ov['status'])) {
            $s = strtolower(trim((string) $ov['status']));

            if ($s !== '' && in_array($s, $allowed, true)) {
                $row->status_override            = $s;
                $row->status_pago                = $s;
                $row->status_override_reason     = $ov['reason'] ?? null;
                $row->status_override_updated_at = $ov['updated_at'] ?? null;
                $row->status_override_updated_by = $ov['updated_by'] ?? null;

                $pm = isset($ov['pay_method']) && trim((string) $ov['pay_method']) !== '' ? (string) $ov['pay_method'] : null;
                $pp = isset($ov['pay_provider']) && trim((string) $ov['pay_provider']) !== '' ? (string) $ov['pay_provider'] : null;
                $ps = isset($ov['pay_status']) && trim((string) $ov['pay_status']) !== '' ? (string) $ov['pay_status'] : null;

                $row->ov_pay_method   = $pm;
                $row->ov_pay_provider = $pp;
                $row->ov_pay_status   = $ps;
                $row->ov_paid_at      = $ov['paid_at'] ?? null;

                if ($pm !== null) {
                    $row->pay_method = $pm;
                }
                if ($pp !== null) {
                    $row->pay_provider = $pp;
                }
                if ($ps !== null) {
                    $row->pay_status = $ps;
                }

                if ($row->ov_paid_at !== null) {
                    $row->pay_last_paid_at = $row->ov_paid_at;
                }

                if ($s === 'pagado') {
                    $total = (float) ($row->total_shown ?? 0.0);

                    if ($total <= 0.00001) {
                        $c = (float) ($row->cargo ?? 0.0);
                        $e = (float) ($row->expected_total ?? 0.0);
                        $total = $c > 0.00001 ? $c : $e;
                    }

                    $row->abono_pay   = round($total, 2);
                    $row->abono       = round($total, 2);
                    $row->saldo_shown = 0.0;
                    $row->saldo       = 0.0;

                    if (empty($row->pay_status)) {
                        $row->pay_status = 'paid';
                    }
                } else {
                    $row->abono_pay = 0.0;

                    $abEdo = (float) ($row->abono_edo ?? 0.0);

                    $total = (float) ($row->total_shown ?? 0.0);
                    if ($total <= 0.00001) {
                        $c = (float) ($row->cargo ?? 0.0);
                        $e = (float) ($row->expected_total ?? 0.0);
                        $total = $c > 0.00001 ? $c : $e;
                    }

                    $row->abono = round($abEdo, 2);

                    $saldo = (float) max(0.0, $total - $abEdo);
                    $row->saldo_shown = round($saldo, 2);
                    $row->saldo       = round($saldo, 2);
                }

                return $row;
            }
        }

        $row->status_override            = null;
        $row->status_override_reason     = null;
        $row->status_override_updated_at = null;
        $row->status_override_updated_by = null;

        return $row;
    }
}