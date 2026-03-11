<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
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
            'requests_total'     => 0,
            'requests_pending'   => 0,
            'requests_issued'    => 0,
            'requests_error'     => 0,
            'invoices_total'     => 0,
            'invoices_sent'      => 0,
            'invoices_unsent'    => 0,
            'month_total'        => 0,
            'month_requests'     => 0,
            'month_invoices'     => 0,
        ];

        $recentRequests = collect();
        $recentInvoices = collect();

        [$requestsTable, $mode] = $this->resolveInvoiceRequestsTable();

        try {
            if ($requestsTable !== null && Schema::connection($this->adm)->hasTable($requestsTable)) {
                $cards['requests_total']   = (int) DB::connection($this->adm)->table($requestsTable)->count();
                $cards['requests_pending'] = (int) $this->countRequestsByStatuses($requestsTable, $mode, ['requested', 'in_progress']);
                $cards['requests_issued']  = (int) $this->countRequestsByStatuses($requestsTable, $mode, ['issued', 'done']);
                $cards['requests_error']   = (int) $this->countRequestsByStatuses($requestsTable, $mode, ['error', 'rejected']);

                $recentRequests = DB::connection($this->adm)->table($requestsTable)
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get();

                $currentYm = now()->format('Y-m');
                $periodCol = Schema::connection($this->adm)->hasColumn($requestsTable, 'period')
                    ? 'period'
                    : (Schema::connection($this->adm)->hasColumn($requestsTable, 'periodo') ? 'periodo' : null);

                if ($periodCol !== null) {
                    $cards['month_requests'] = (int) DB::connection($this->adm)->table($requestsTable)
                        ->where($periodCol, $currentYm)
                        ->count();
                }
            }

            if (Schema::connection($this->adm)->hasTable('billing_invoices')) {
                $cards['invoices_total'] = (int) DB::connection($this->adm)->table('billing_invoices')->count();

                if (Schema::connection($this->adm)->hasColumn('billing_invoices', 'sent_at')) {
                    $cards['invoices_sent'] = (int) DB::connection($this->adm)->table('billing_invoices')
                        ->whereNotNull('sent_at')
                        ->count();

                    $cards['invoices_unsent'] = max(0, $cards['invoices_total'] - $cards['invoices_sent']);
                } else {
                    $cards['invoices_sent']   = 0;
                    $cards['invoices_unsent'] = $cards['invoices_total'];
                }

                $recentInvoices = DB::connection($this->adm)->table('billing_invoices')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get();

                if (Schema::connection($this->adm)->hasColumn('billing_invoices', 'period')) {
                    $cards['month_invoices'] = (int) DB::connection($this->adm)->table('billing_invoices')
                        ->where('period', now()->format('Y-m'))
                        ->count();
                }
            }

            $cards['month_total'] = (int) ($cards['month_requests'] + $cards['month_invoices']);
        } catch (Throwable $e) {
            // No rompemos el dashboard si algún schema/tabla aún está en transición.
        }

        return view('admin.billing.invoicing.dashboard', [
            'cards'          => $cards,
            'recentRequests' => $recentRequests,
            'recentInvoices' => $recentInvoices,
            'requestsMode'   => $mode,
            'requestsTable'  => $requestsTable,
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
}