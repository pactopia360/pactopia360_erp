<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Cliente\Billing\PortalStatementMirrorService.php

declare(strict_types=1);

namespace App\Services\Cliente\Billing;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class PortalStatementMirrorService
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

    public function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', trim($period));
    }

    public function parseToPeriod(mixed $value): ?string
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->format('Y-m');
            }

            if (is_numeric($value)) {
                $ts = (int) $value;
                return $ts > 0 ? Carbon::createFromTimestamp($ts)->format('Y-m') : null;
            }

            if (!is_string($value)) {
                return null;
            }

            $value = str_replace('/', '-', trim($value));
            if ($value === '') {
                return null;
            }

            if ($this->isValidPeriod($value)) {
                return $value;
            }

            return Carbon::parse($value)->format('Y-m');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function normalizeStatementStatus(?string $status): string
    {
        $s = strtolower(trim((string) $status));

        return match ($s) {
            'paid', 'pagado', 'succeeded', 'success', 'complete', 'completed', 'captured', 'confirmed'
                => 'paid',

            'partial', 'parcial'
                => 'partial',

            'overdue', 'vencido', 'past_due', 'unpaid'
                => 'overdue',

            'sin_mov', 'sin mov', 'sin_movimiento', 'sin movimiento', 'no_movement', 'no movement'
                => 'no_movement',

            default
                => 'pending',
        };
    }

    public function statementIsPending(array $rr): bool
    {
        $st = $this->normalizeStatementStatus((string) ($rr['status'] ?? 'pending'));
        if ($st === 'paid') {
            return false;
        }

        $saldo = null;

        if (array_key_exists('saldo', $rr) && is_numeric($rr['saldo'])) {
            $saldo = (float) $rr['saldo'];
        } elseif (array_key_exists('balance', $rr) && is_numeric($rr['balance'])) {
            $saldo = (float) $rr['balance'];
        }

        if ($saldo === null || $saldo <= 0.0001) {
            $cargo = null;
            $abono = null;

            foreach (['total_cargo', 'charge', 'cargo', 'total'] as $k) {
                if (array_key_exists($k, $rr) && is_numeric($rr[$k])) {
                    $cargo = (float) $rr[$k];
                    break;
                }
            }

            foreach (['total_abono', 'paid_amount', 'abono', 'paid'] as $k) {
                if (array_key_exists($k, $rr) && is_numeric($rr[$k])) {
                    $abono = (float) $rr[$k];
                    break;
                }
            }

            if ($cargo !== null) {
                $abono = $abono ?? 0.0;
                $saldo = max(0.0, $cargo - $abono);
            }
        }

        if ($saldo === null || $saldo <= 0.0001) {
            $tc = null;
            $pc = null;

            foreach (['total_cents', 'total_amount_cents', 'amount_cents'] as $k) {
                if (array_key_exists($k, $rr) && is_numeric($rr[$k])) {
                    $tc = (int) $rr[$k];
                    break;
                }
            }

            foreach (['paid_cents', 'paid_amount_cents'] as $k) {
                if (array_key_exists($k, $rr) && is_numeric($rr[$k])) {
                    $pc = (int) $rr[$k];
                    break;
                }
            }

            if ($tc !== null) {
                $pc = $pc ?? 0;
                $saldo = max(0.0, ($tc - $pc) / 100.0);
            }
        }

        $saldo = is_numeric($saldo) ? (float) $saldo : 0.0;

        return $saldo > 0.0001;
    }

    public function buildStatementRefs(int $adminAccountId): array
    {
        $refs = [];

        try {
            $refs[] = (string) $adminAccountId;
            $refs[] = $adminAccountId;

            if (Schema::connection($this->cli)->hasTable('cuentas_cliente')) {
                $uuids = DB::connection($this->cli)->table('cuentas_cliente')
                    ->where('admin_account_id', $adminAccountId)
                    ->limit(200)
                    ->pluck('id')
                    ->map(fn ($x) => trim((string) $x))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                foreach ($uuids as $u) {
                    $refs[] = $u;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[CLIENTE][BILLING] buildStatementRefs failed', [
                'account_id' => $adminAccountId,
                'err'        => $e->getMessage(),
            ]);
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($x) => trim((string) $x),
            $refs
        ))));
    }

    public function loadRowsFromAdminBillingStatements(array $statementRefs, int $limit = 60): array
    {
        if (empty($statementRefs)) {
            return [];
        }

        if (!Schema::connection($this->adm)->hasTable('billing_statements')) {
            return [];
        }

        try {
            $rows = DB::connection($this->adm)->table('billing_statements')
                ->whereIn('account_id', $statementRefs)
                ->orderByDesc('period')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit(max(1, $limit))
                ->get()
                ->map(fn ($r) => (array) $r)
                ->toArray();

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            Log::warning('[CLIENTE][BILLING] loadRowsFromAdminBillingStatements failed', [
                'refs' => $statementRefs,
                'err'  => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function overrideTable(): string
    {
        return 'billing_statement_status_overrides';
    }

    public function fetchStatementOverrideMapForPeriods(int $adminAccountId, array $periods): array
    {
        $out = [];

        if ($adminAccountId <= 0 || empty($periods)) {
            return $out;
        }

        try {
            if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
                return $out;
            }

            $rows = DB::connection($this->adm)->table($this->overrideTable())
                ->select(['period', 'status_override'])
                ->where('account_id', $adminAccountId)
                ->whereIn('period', $periods)
                ->orderByDesc('period')
                ->get();

            foreach ($rows as $row) {
                $p = trim((string) ($row->period ?? ''));
                $s = strtolower(trim((string) ($row->status_override ?? '')));

                if ($p === '' || !$this->isValidPeriod($p) || $s === '') {
                    continue;
                }

                if (!isset($out[$p])) {
                    $out[$p] = ['status' => $s];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[CLIENTE][BILLING] fetchStatementOverrideMapForPeriods failed', [
                'account_id' => $adminAccountId,
                'periods'    => $periods,
                'err'        => $e->getMessage(),
            ]);
        }

        return $out;
    }

    public function applyStatementOverridesForAdminAccount(int $adminAccountId, array $rows): array
    {
        if ($adminAccountId <= 0 || empty($rows)) {
            return $rows;
        }

        $periods = [];
        foreach ($rows as $row) {
            $p = trim((string) ($row['period'] ?? ''));
            if ($p !== '' && $this->isValidPeriod($p)) {
                $periods[] = $p;
            }
        }

        $periods = array_values(array_unique($periods));
        if (empty($periods)) {
            return $rows;
        }

        $ovMap = $this->fetchStatementOverrideMapForPeriods($adminAccountId, $periods);
        if (empty($ovMap)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $period = trim((string) ($row['period'] ?? ''));
            if ($period === '' || !isset($ovMap[$period])) {
                continue;
            }

            $ovStatus = strtolower(trim((string) ($ovMap[$period]['status'] ?? '')));
            if ($ovStatus === '') {
                continue;
            }

            $cargo = 0.0;
            foreach (['total_cargo', 'charge', 'cargo', 'total'] as $k) {
                if (isset($row[$k]) && is_numeric($row[$k])) {
                    $cargo = (float) $row[$k];
                    break;
                }
            }

            $abono = 0.0;
            foreach (['total_abono', 'paid_amount', 'abono', 'paid'] as $k) {
                if (isset($row[$k]) && is_numeric($row[$k])) {
                    $abono = (float) $row[$k];
                    break;
                }
            }

            if ($ovStatus === 'pagado') {
                $row['status']      = 'paid';
                $row['saldo']       = 0.0;
                $row['total_abono'] = round(max($abono, $cargo), 2);
                $row['paid_amount'] = round(max($abono, $cargo), 2);
                $row['charge']      = round($cargo, 2);
                $row['total_cargo'] = round($cargo, 2);
                continue;
            }

            $saldo = max(0.0, $cargo - $abono);

            $row['status']      = 'pending';
            $row['saldo']       = round($saldo, 2);
            $row['total_abono'] = round($abono, 2);
            $row['paid_amount'] = round($abono, 2);
            $row['charge']      = round($cargo, 2);
            $row['total_cargo'] = round($cargo, 2);
        }
        unset($row);

        return $rows;
    }

    public function computePayAllowed(
        bool $isAnnual,
        string $basePeriod,
        ?string $lastPaid,
        array $rowsFromStatementsAll
    ): ?string {
        try {
            $pendingPeriods = [];

            foreach ($rowsFromStatementsAll as $rr) {
                $pp = (string) ($rr['period'] ?? '');
                if (!$this->isValidPeriod($pp)) {
                    continue;
                }

                $a = is_array($rr) ? $rr : (array) $rr;
                if ($this->statementIsPending($a)) {
                    $pendingPeriods[] = $pp;
                }
            }

            if (!empty($pendingPeriods)) {
                sort($pendingPeriods);
                return (string) $pendingPeriods[0];
            }
        } catch (\Throwable $e) {
        }

        try {
            if ($isAnnual) {
                $next = $lastPaid
                    ? Carbon::createFromFormat('Y-m', $lastPaid)->addYearNoOverflow()->format('Y-m')
                    : $basePeriod;

                $renewAt = Carbon::createFromFormat('Y-m', $next)->startOfMonth();
                $openAt  = $renewAt->copy()->subDays(30);

                if (now()->lessThan($openAt)) {
                    return null;
                }

                return $next;
            }

            return $lastPaid
                ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
                : $basePeriod;
        } catch (\Throwable $e) {
            return $basePeriod;
        }
    }

    public function mapAdminStatementRowsForPortal(
        int $accountId,
        array $rowsFromStatementsAll,
        ?string $lastPaid,
        ?string $payAllowedUi,
        string $rfc,
        string $alias,
        array $chargesByPeriod = []
    ): array {
        $out = [];

        foreach ($rowsFromStatementsAll as $row) {
            if (!is_array($row)) {
                continue;
            }

            $period = trim((string) ($row['period'] ?? ''));
            if (!$this->isValidPeriod($period)) {
                continue;
            }

            $status = $this->normalizeStatementStatus((string) ($row['status'] ?? 'pending'));

            $charge = 0.0;
            foreach (['total_cargo', 'charge', 'cargo', 'total'] as $k) {
                if (isset($row[$k]) && is_numeric($row[$k])) {
                    $charge = round(max(0.0, (float) $row[$k]), 2);
                    break;
                }
            }

            $paidAmount = 0.0;
            foreach (['total_abono', 'paid_amount', 'abono', 'paid'] as $k) {
                if (isset($row[$k]) && is_numeric($row[$k])) {
                    $paidAmount = round(max(0.0, (float) $row[$k]), 2);
                    break;
                }
            }

            $saldo = isset($row['saldo']) && is_numeric($row['saldo'])
                ? round(max(0.0, (float) $row['saldo']), 2)
                : round(max(0.0, $charge - $paidAmount), 2);

            if ($status === 'paid') {
                $shown = max($charge, $paidAmount);
                $charge     = round($shown, 2);
                $paidAmount = round($shown, 2);
                $saldo      = 0.0;
            } else {
                if (
                    $charge <= 0.0001 &&
                    $payAllowedUi &&
                    $period === $payAllowedUi &&
                    isset($chargesByPeriod[$period]) &&
                    is_numeric($chargesByPeriod[$period]) &&
                    (float) $chargesByPeriod[$period] > 0
                ) {
                    $charge = round((float) $chargesByPeriod[$period], 2);
                }

                if ($saldo <= 0.0001 && $charge > 0.0001) {
                    $saldo = round(max(0.0, $charge - $paidAmount), 2);
                }
            }

            $periodRange = $period;
            try {
                $c = Carbon::createFromFormat('Y-m', $period);
                $periodRange = $c->copy()->startOfMonth()->format('d/m/Y') . ' - ' . $c->copy()->endOfMonth()->format('d/m/Y');
            } catch (\Throwable $e) {
            }

            $canPay = (
                $payAllowedUi &&
                $period === $payAllowedUi &&
                $status !== 'paid' &&
                $saldo > 0.0001
            );

            $out[] = [
                'id'                     => $row['id'] ?? null,
                'account_id'             => $accountId,
                'admin_account_id'       => $accountId,
                'statement_account_ref'  => $row['account_id'] ?? (string) $accountId,
                'period'                 => $period,
                'status'                 => $status,
                'charge'                 => round($charge, 2),
                'total_cargo'            => round($charge, 2),
                'paid_amount'            => round($paidAmount, 2),
                'total_abono'            => round($paidAmount, 2),
                'saldo'                  => round($saldo, 2),
                'can_pay'                => $canPay,
                'period_range'           => $periodRange,
                'rfc'                    => $rfc !== '' ? $rfc : '—',
                'alias'                  => $alias !== '' ? $alias : '—',
                'invoice_request_status' => null,
                'invoice_has_zip'        => false,
                'price_source'           => 'admin.billing_statements',
                'service_items'          => is_array($row['service_items'] ?? null) ? $row['service_items'] : [],
                'meta'                   => $row['meta'] ?? null,
                'snapshot'               => $row['snapshot'] ?? null,
                'due_date'               => $row['due_date'] ?? null,
                'paid_at'                => $row['paid_at'] ?? null,
                'created_at'             => $row['created_at'] ?? null,
                'updated_at'             => $row['updated_at'] ?? null,
            ];
        }

        usort($out, function ($a, $b) use ($lastPaid, $payAllowedUi) {
            $pa = (string) ($a['period'] ?? '');
            $pb = (string) ($b['period'] ?? '');

            if ($lastPaid && $pa === $lastPaid && $pb !== $lastPaid) return -1;
            if ($lastPaid && $pb === $lastPaid && $pa !== $lastPaid) return 1;

            if ($payAllowedUi && $pa === $payAllowedUi && $pb !== $payAllowedUi) return 1;
            if ($payAllowedUi && $pb === $payAllowedUi && $pa !== $payAllowedUi) return -1;

            return strcmp($pa, $pb);
        });

        return $out;
    }

    public function ensurePortalPayAllowedRow(
        int $accountId,
        array $rows,
        ?string $payAllowedUi,
        array $chargesByPeriod,
        string $rfc,
        string $alias
    ): array {
        $payAllowedUi = trim((string) $payAllowedUi);

        if (!$this->isValidPeriod($payAllowedUi)) {
            return $rows;
        }

        foreach ($rows as $row) {
            if ((string) ($row['period'] ?? '') === $payAllowedUi) {
                return $rows;
            }
        }

        $charge = 0.0;
        if (isset($chargesByPeriod[$payAllowedUi]) && is_numeric($chargesByPeriod[$payAllowedUi])) {
            $charge = round((float) $chargesByPeriod[$payAllowedUi], 2);
        }

        if ($charge <= 0.0001) {
            return $rows;
        }

        $periodRange = $payAllowedUi;
        try {
            $c = Carbon::createFromFormat('Y-m', $payAllowedUi);
            $periodRange = $c->copy()->startOfMonth()->format('d/m/Y') . ' - ' . $c->copy()->endOfMonth()->format('d/m/Y');
        } catch (\Throwable $e) {
        }

        $rows[] = [
            'id'                     => null,
            'account_id'             => $accountId,
            'admin_account_id'       => $accountId,
            'statement_account_ref'  => (string) $accountId,
            'period'                 => $payAllowedUi,
            'status'                 => 'pending',
            'charge'                 => round($charge, 2),
            'total_cargo'            => round($charge, 2),
            'paid_amount'            => 0.0,
            'total_abono'            => 0.0,
            'saldo'                  => round($charge, 2),
            'can_pay'                => true,
            'period_range'           => $periodRange,
            'rfc'                    => $rfc !== '' ? $rfc : '—',
            'alias'                  => $alias !== '' ? $alias : '—',
            'invoice_request_status' => null,
            'invoice_has_zip'        => false,
            'price_source'           => 'synthetic.pay_allowed',
            'service_items'          => [],
            'meta'                   => null,
            'snapshot'               => null,
            'due_date'               => null,
            'paid_at'                => null,
            'created_at'             => null,
            'updated_at'             => null,
        ];

        usort($rows, fn ($a, $b) => strcmp((string) ($a['period'] ?? ''), (string) ($b['period'] ?? '')));

        return $rows;
    }
}