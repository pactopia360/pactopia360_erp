<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class InvoicingDashboardController extends Controller
{
    private string $adm;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function index(): View
    {
        $cards = [
            'requests_total'      => 0,
            'requests_pending'    => 0,
            'requests_issued'     => 0,
            'requests_error'      => 0,
            'invoices_total'      => 0,
            'invoices_sent'       => 0,
            'invoices_unsent'     => 0,
            'month_total'         => 0,
            'month_requests'      => 0,
            'month_invoices'      => 0,

            // timbres
            'stamps_assigned'     => 0,
            'stamps_used'         => 0,
            'stamps_available'    => 0,
            'stamps_usage_pct'    => 0.0,

            // montos
            'month_amount_total'  => 0.0,
            'month_amount_paid'   => 0.0,
            'month_amount_pending'=> 0.0,
        ];

        $recentRequests = collect();
        $recentInvoices = collect();

        $requestsChart = [];
        $invoicesChart = [];
        $statusDonut = [
            'pending'   => 0,
            'sent'      => 0,
            'paid'      => 0,
            'cancelled' => 0,
            'error'     => 0,
        ];

        [$requestsTable, $mode] = $this->resolveInvoiceRequestsTable();

        try {
            if ($requestsTable !== null && Schema::connection($this->adm)->hasTable($requestsTable)) {
                $cards['requests_total']   = (int) DB::connection($this->adm)->table($requestsTable)->count();
                $cards['requests_pending'] = (int) $this->countRequestsByStatuses($requestsTable, $mode, ['requested', 'in_progress']);
                $cards['requests_issued']  = (int) $this->countRequestsByStatuses($requestsTable, $mode, ['issued', 'done']);
                $cards['requests_error']   = (int) $this->countRequestsByStatuses($requestsTable, $mode, ['error', 'rejected']);

                $recentRequests = DB::connection($this->adm)->table($requestsTable)
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get();

                $periodCol = $this->resolvePeriodColumn($requestsTable, ['period', 'periodo']);
                if ($periodCol !== null) {
                    $cards['month_requests'] = (int) DB::connection($this->adm)->table($requestsTable)
                        ->where($periodCol, now()->format('Y-m'))
                        ->count();
                }

                $requestsChart = $this->buildRequestsMonthlyChart($requestsTable);
            }

            if (Schema::connection($this->adm)->hasTable('billing_invoices')) {
                $invoiceCols = Schema::connection($this->adm)->getColumnListing('billing_invoices');
                $invoiceColsLc = array_map('strtolower', $invoiceCols);
                $hasInvoiceCol = static fn (string $c): bool => in_array(strtolower($c), $invoiceColsLc, true);

                $cards['invoices_total'] = (int) DB::connection($this->adm)->table('billing_invoices')->count();

                if ($hasInvoiceCol('sent_at')) {
                    $cards['invoices_sent'] = (int) DB::connection($this->adm)->table('billing_invoices')
                        ->whereNotNull('sent_at')
                        ->count();
                } else {
                    $cards['invoices_sent'] = (int) DB::connection($this->adm)->table('billing_invoices')
                        ->where('status', 'sent')
                        ->count();
                }

                $cards['invoices_unsent'] = max(0, $cards['invoices_total'] - $cards['invoices_sent']);

                $recentInvoices = DB::connection($this->adm)->table('billing_invoices')
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get()
                    ->map(function ($row) {
                        $row->display_total_mxn = $this->resolveInvoiceAmountMxn($row);
                        return $row;
                    });

                if ($hasInvoiceCol('period')) {
                    $monthInvoicesQuery = DB::connection($this->adm)->table('billing_invoices')
                        ->where('period', now()->format('Y-m'));

                    $cards['month_invoices'] = (int) $monthInvoicesQuery->count();

                    $monthRows = $monthInvoicesQuery->get()->map(function ($row) {
                        $row->display_total_mxn = $this->resolveInvoiceAmountMxn($row);
                        return $row;
                    });

                    $cards['month_amount_total'] = round((float) $monthRows->sum(fn ($r) => (float) ($r->display_total_mxn ?? 0)), 2);
                    $cards['month_amount_paid'] = round((float) $monthRows->filter(function ($r) {
                        return strtolower(trim((string) ($r->status ?? ''))) === 'paid';
                    })->sum(fn ($r) => (float) ($r->display_total_mxn ?? 0)), 2);

                    $cards['month_amount_pending'] = round(max(0, $cards['month_amount_total'] - $cards['month_amount_paid']), 2);
                }

                $statusDonut = $this->buildInvoiceStatusBreakdown();
                $invoicesChart = $this->buildInvoicesMonthlyChart();
            }

            $cards['month_total'] = (int) ($cards['month_requests'] + $cards['month_invoices']);

            $stampInfo = $this->resolveStampsSummary();
            $cards['stamps_assigned']  = (int) ($stampInfo['assigned'] ?? 0);
            $cards['stamps_used']      = (int) ($stampInfo['used'] ?? 0);
            $cards['stamps_available'] = (int) ($stampInfo['available'] ?? 0);
            $cards['stamps_usage_pct'] = (float) ($stampInfo['usage_pct'] ?? 0);
        } catch (Throwable $e) {
            // no rompemos la vista
        }

        return view('admin.billing.invoicing.dashboard', [
            'cards'          => $cards,
            'recentRequests' => $recentRequests,
            'recentInvoices' => $recentInvoices,
            'requestsMode'   => $mode,
            'requestsTable'  => $requestsTable,
            'requestsChart'  => $requestsChart,
            'invoicesChart'  => $invoicesChart,
            'statusDonut'    => $statusDonut,
        ]);
    }

    private function resolveInvoiceRequestsTable(): array
    {
        $hasHub    = Schema::connection($this->adm)->hasTable('billing_invoice_requests');
        $hasLegacy = Schema::connection($this->adm)->hasTable('invoice_requests');

        if ($hasHub) {
            return ['billing_invoice_requests', 'hub'];
        }

        if ($hasLegacy) {
            return ['invoice_requests', 'legacy'];
        }

        return [null, 'missing'];
    }

    private function countRequestsByStatuses(string $table, string $mode, array $statuses): int
    {
        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);

        $statusCol = null;
        if (in_array('status', $lc, true)) {
            $statusCol = 'status';
        } elseif (in_array('estatus', $lc, true)) {
            $statusCol = 'estatus';
        }

        if ($statusCol === null) {
            return 0;
        }

        $normalized = [];
        foreach ($statuses as $st) {
            $normalized[] = $this->normalizeStatusForDb($st, $mode);
        }

        $normalized = array_values(array_unique($normalized));

        return (int) DB::connection($this->adm)->table($table)
            ->whereIn($statusCol, $normalized)
            ->count();
    }

    private function normalizeStatusForDb(string $raw, string $mode): string
    {
        $s = strtolower(trim($raw));

        $map = [
            'solicitada'  => 'requested',
            'solicitado'  => 'requested',
            'requested'   => 'requested',
            'request'     => 'requested',
            'pending'     => 'requested',

            'en_proceso'  => 'in_progress',
            'en proceso'  => 'in_progress',
            'proceso'     => 'in_progress',
            'processing'  => 'in_progress',
            'in_progress' => 'in_progress',

            'emitida'     => 'done',
            'emitido'     => 'done',
            'done'        => 'done',
            'completed'   => 'done',
            'facturada'   => 'done',
            'invoiced'    => 'done',
            'issued'      => 'done',

            'rechazada'   => 'rejected',
            'rechazado'   => 'rejected',
            'rejected'    => 'rejected',
            'canceled'    => 'rejected',
            'cancelled'   => 'rejected',

            'error'       => 'error',
            'failed'      => 'error',
        ];

        $canonical = $map[$s] ?? $s;

        if ($mode === 'hub' && $canonical === 'done') {
            return 'issued';
        }

        if ($mode !== 'hub' && $canonical === 'issued') {
            return 'done';
        }

        if (!in_array($canonical, ['requested', 'in_progress', 'done', 'issued', 'rejected', 'error'], true)) {
            $canonical = 'requested';
        }

        return $canonical;
    }

    private function resolvePeriodColumn(string $table, array $candidates): ?string
    {
        try {
            foreach ($candidates as $candidate) {
                if (Schema::connection($this->adm)->hasColumn($table, $candidate)) {
                    return $candidate;
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    private function buildRequestsMonthlyChart(string $table): array
    {
        $periodCol = $this->resolvePeriodColumn($table, ['period', 'periodo']);
        if ($periodCol === null) {
            return [];
        }

        $months = $this->lastMonths(6);
        $out = [];

        foreach ($months as $ym) {
            $out[] = [
                'ym'    => $ym,
                'label' => $this->monthShortLabel($ym),
                'value' => (int) DB::connection($this->adm)->table($table)
                    ->where($periodCol, $ym)
                    ->count(),
            ];
        }

        return $out;
    }

    private function buildInvoicesMonthlyChart(): array
    {
        if (!Schema::connection($this->adm)->hasTable('billing_invoices')) {
            return [];
        }

        if (!Schema::connection($this->adm)->hasColumn('billing_invoices', 'period')) {
            return [];
        }

        $months = $this->lastMonths(6);
        $out = [];

        foreach ($months as $ym) {
            $out[] = [
                'ym'    => $ym,
                'label' => $this->monthShortLabel($ym),
                'value' => (int) DB::connection($this->adm)->table('billing_invoices')
                    ->where('period', $ym)
                    ->count(),
            ];
        }

        return $out;
    }

    private function buildInvoiceStatusBreakdown(): array
    {
        $base = [
            'pending'   => 0,
            'sent'      => 0,
            'paid'      => 0,
            'cancelled' => 0,
            'error'     => 0,
        ];

        if (!Schema::connection($this->adm)->hasTable('billing_invoices')) {
            return $base;
        }

        if (!Schema::connection($this->adm)->hasColumn('billing_invoices', 'status')) {
            return $base;
        }

        $rows = DB::connection($this->adm)->table('billing_invoices')
            ->select(['status'])
            ->get();

        foreach ($rows as $row) {
            $status = strtolower(trim((string) ($row->status ?? '')));

            if (in_array($status, ['pending', 'draft', 'generated', 'stamped', 'issued', 'active'], true)) {
                $base['pending']++;
                continue;
            }

            if ($status === 'sent') {
                $base['sent']++;
                continue;
            }

            if ($status === 'paid') {
                $base['paid']++;
                continue;
            }

            if (in_array($status, ['cancelled', 'canceled'], true)) {
                $base['cancelled']++;
                continue;
            }

            if ($status === 'error') {
                $base['error']++;
            }
        }

        return $base;
    }

    private function resolveStampsSummary(): array
    {
        $base = [
            'assigned'  => 0,
            'used'      => 0,
            'available' => 0,
            'usage_pct' => 0.0,
        ];

        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            $base['used'] = $this->countStampedInvoices();
            return $this->finalizeStampsSummary($base);
        }

        if (!Schema::connection($this->adm)->hasColumn('accounts', 'meta')) {
            $base['used'] = $this->countStampedInvoices();
            return $this->finalizeStampsSummary($base);
        }

        $rows = DB::connection($this->adm)->table('accounts')
            ->select(['id', 'meta'])
            ->get();

        $assigned = 0;
        $usedFromMeta = 0;
        $available = 0;

        foreach ($rows as $row) {
            $meta = $this->decodeMeta($row->meta ?? null);
            $assigned += $this->firstNumericMeta($meta, [
                'billing.stamps.assigned',
                'billing.stamps.total',
                'billing.timbres.assigned',
                'billing.timbres.total',
                'sat.stamps.assigned',
                'sat.timbres.assigned',
                'stamps.assigned',
                'timbres.assigned',
            ]);

            $usedFromMeta += $this->firstNumericMeta($meta, [
                'billing.stamps.used',
                'billing.timbres.used',
                'sat.stamps.used',
                'sat.timbres.used',
                'stamps.used',
                'timbres.used',
                'billing.stamps.consumed',
                'billing.timbres.consumed',
            ]);

            $available += $this->firstNumericMeta($meta, [
                'billing.stamps.available',
                'billing.timbres.available',
                'sat.stamps.available',
                'sat.timbres.available',
                'stamps.available',
                'timbres.available',
                'billing.stamps.remaining',
                'billing.timbres.remaining',
            ]);
        }

        $base['assigned'] = max(0, (int) $assigned);

        $stampedInvoices = $this->countStampedInvoices();
        $base['used'] = max((int) $usedFromMeta, (int) $stampedInvoices);

        if ($available > 0) {
            $base['available'] = max(0, (int) $available);
        } elseif ($base['assigned'] > 0) {
            $base['available'] = max(0, $base['assigned'] - $base['used']);
        }

        return $this->finalizeStampsSummary($base);
    }

    private function finalizeStampsSummary(array $data): array
    {
        $assigned  = max(0, (int) ($data['assigned'] ?? 0));
        $used      = max(0, (int) ($data['used'] ?? 0));
        $available = max(0, (int) ($data['available'] ?? 0));

        if ($assigned > 0 && $available <= 0) {
            $available = max(0, $assigned - $used);
        }

        $usagePct = 0.0;
        if ($assigned > 0) {
            $usagePct = round(min(100, max(0, ($used / $assigned) * 100)), 1);
        }

        return [
            'assigned'  => $assigned,
            'used'      => $used,
            'available' => $available,
            'usage_pct' => $usagePct,
        ];
    }

    private function countStampedInvoices(): int
    {
        if (!Schema::connection($this->adm)->hasTable('billing_invoices')) {
            return 0;
        }

        $query = DB::connection($this->adm)->table('billing_invoices');

        if (Schema::connection($this->adm)->hasColumn('billing_invoices', 'cfdi_uuid')) {
            return (int) $query->whereNotNull('cfdi_uuid')
                ->where('cfdi_uuid', '<>', '')
                ->count();
        }

        if (Schema::connection($this->adm)->hasColumn('billing_invoices', 'status')) {
            return (int) $query->whereIn('status', ['stamped', 'sent', 'paid'])
                ->count();
        }

        return 0;
    }

    private function decodeMeta(mixed $raw): array
    {
        try {
            if (is_array($raw)) {
                return $raw;
            }

            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                return is_array($decoded) ? $decoded : [];
            }
        } catch (Throwable $e) {
            return [];
        }

        return [];
    }

    private function firstNumericMeta(array $meta, array $paths): int
    {
        foreach ($paths as $path) {
            $value = data_get($meta, $path);
            if ($value !== null && $value !== '' && is_numeric($value)) {
                return max(0, (int) $value);
            }
        }

        return 0;
    }

    private function resolveInvoiceAmountMxn(object $row): float
    {
        foreach (['display_total_mxn', 'amount_mxn', 'monto_mxn', 'total', 'subtotal'] as $k) {
            $v = data_get($row, $k);
            if ($v !== null && $v !== '' && is_numeric($v)) {
                return round((float) $v, 2);
            }
        }

        foreach (['amount_cents', 'amount'] as $k) {
            $v = data_get($row, $k);
            if ($v !== null && $v !== '' && is_numeric($v)) {
                return round(((float) $v) / 100, 2);
            }
        }

        return 0.0;
    }

    private function lastMonths(int $count = 6): array
    {
        $months = [];

        for ($i = $count - 1; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
        }

        return $months;
    }

    private function monthShortLabel(string $ym): string
    {
        try {
            return now()->createFromFormat('Y-m', $ym)->translatedFormat('M');
        } catch (Throwable $e) {
            return $ym;
        }
    }
}