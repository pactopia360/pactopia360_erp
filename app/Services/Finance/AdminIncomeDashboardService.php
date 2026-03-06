<?php

declare(strict_types=1);

namespace App\Services\Finance;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminIncomeDashboardService
{
    private string $adm;
    private string $cli;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $this->cli = (string) (config('p360.conn.clientes') ?: 'mysql_clientes');
    }

    public function build(Request $request): array
    {
        $filters = $this->resolveFilters($request);

        $context = $this->loadContext($filters);

        $statementRows  = $this->loadStatementRows($filters, $context);
        $salesRows      = $this->loadSalesRows($filters, $context);
        $projectionRows = $this->loadProjectionRows($filters, $context, $statementRows, $salesRows);

        $rows = $statementRows
            ->concat($salesRows)
            ->concat($projectionRows)
            ->values();

        $rows = $this->applyOverrides($rows, $context);
        $rows = $this->applyFilters($rows, $filters);
        $rows = $this->sortRows($rows);

        $kpis       = $this->buildKpis($rows);
        $charts     = $this->buildCharts($rows, $filters);
        $highlights = $this->buildHighlights($rows, $filters);

        return [
            'filters'    => $filters + [
                'vendor_list' => $context['vendor_list'],
            ],
            'kpis'       => $kpis,
            'charts'     => $charts,
            'highlights' => $highlights,
            'rows'       => $rows,
            'meta'       => [
                'counts' => [
                    'statements'  => $statementRows->count(),
                    'sales'       => $salesRows->count(),
                    'projections' => $projectionRows->count(),
                    'rows'        => $rows->count(),
                ],
            ],
        ];
    }

    private function resolveFilters(Request $request): array
    {
        $year = (int) ($request->input('year') ?: now()->year);
        if ($year < 2020 || $year > ((int) now()->year + 5)) {
            $year = (int) now()->year;
        }

        $month = (string) ($request->input('month') ?: 'all');
        if ($month !== 'all' && !preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            $month = 'all';
        }

        $origin = strtolower(trim((string) ($request->input('origin') ?: 'all')));
        if ($origin === 'no_recurrente') {
            $origin = 'unico';
        }
        if (!in_array($origin, ['all', 'recurrente', 'unico'], true)) {
            $origin = 'all';
        }

        $status = strtolower(trim((string) ($request->input('status') ?: 'all')));
        if (!in_array($status, ['all', 'pending', 'emitido', 'pagado', 'vencido'], true)) {
            $status = 'all';
        }

        $invoiceStatus = strtolower(trim((string) ($request->input('invoice_status') ?: 'all')));
        $invoiceStatus = $this->normalizeInvoiceStatus($invoiceStatus, true);

        $vendorId = trim((string) ($request->input('vendor_id') ?: 'all'));
        if ($vendorId === '') {
            $vendorId = 'all';
        }

        $q = trim((string) ($request->input('q') ?: ''));

        $includeSales = (int) ($request->input('include_sales', 1)) === 1;
        $includeProjections = (int) ($request->input('include_projections', 1)) === 1;

        $periods = [];
        for ($m = 1; $m <= 12; $m++) {
            $periods[] = sprintf('%04d-%02d', $year, $m);
        }

        if ($month !== 'all') {
            $periods = [sprintf('%04d-%s', $year, $month)];
        }

        return [
            'year'                => $year,
            'month'               => $month,
            'origin'              => $origin,
            'status'              => $status,
            'invoice_status'      => $invoiceStatus,
            'vendor_id'           => $vendorId,
            'q'                   => $q,
            'include_sales'       => $includeSales ? 1 : 0,
            'include_projections' => $includeProjections ? 1 : 0,
            'periods'             => $periods,
        ];
    }

    private function loadContext(array $filters): array
    {
        $vendors = collect();
        if (Schema::connection($this->adm)->hasTable('finance_vendors')) {
            $vendors = collect(
                DB::connection($this->adm)
                    ->table('finance_vendors')
                    ->select(['id', 'name', 'is_active'])
                    ->orderBy('name')
                    ->get()
            );
        }

        $vendorById = $vendors->keyBy(fn ($v) => (string) $v->id);

        $billingProfilesByAccountId = collect();
        if (Schema::connection($this->adm)->hasTable('finance_billing_profiles')) {
            $billingProfilesByAccountId = collect(
                DB::connection($this->adm)
                    ->table('finance_billing_profiles')
                    ->select([
                        'account_id',
                        'rfc_receptor',
                        'razon_social',
                        'forma_pago',
                        'metodo_pago',
                        'meta',
                    ])
                    ->get()
            )->keyBy(fn ($r) => (string) $r->account_id);
        }

        $accountsAdmin = collect();
        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $select = ['id'];
            if ($this->hasCol($this->adm, 'accounts', 'name')) {
                $select[] = 'name';
            }
            if ($this->hasCol($this->adm, 'accounts', 'razon_social')) {
                $select[] = 'razon_social';
            }
            if ($this->hasCol($this->adm, 'accounts', 'rfc')) {
                $select[] = 'rfc';
            }
            if ($this->hasCol($this->adm, 'accounts', 'meta')) {
                $select[] = 'meta';
            }

            $accountsAdmin = collect(
                DB::connection($this->adm)
                    ->table('accounts')
                    ->select($select)
                    ->get()
            )->keyBy(fn ($r) => (string) $r->id);
        }

        $cuentasClienteByAdmin = collect();
        $cuentasClienteByUuid  = collect();
        if (Schema::connection($this->cli)->hasTable('cuentas_cliente')) {
            $select = ['id', 'admin_account_id', 'rfc', 'rfc_padre', 'razon_social', 'nombre_comercial', 'modo_cobro', 'plan_actual'];
            if ($this->hasCol($this->cli, 'cuentas_cliente', 'empresa')) {
                $select[] = 'empresa';
            }
            if ($this->hasCol($this->cli, 'cuentas_cliente', 'activo')) {
                $select[] = 'activo';
            }

            $cc = collect(
                DB::connection($this->cli)
                    ->table('cuentas_cliente')
                    ->select($select)
                    ->get()
            );

            $cuentasClienteByAdmin = $cc
                ->filter(fn ($r) => !empty($r->admin_account_id))
                ->keyBy(fn ($r) => (string) $r->admin_account_id);

            $cuentasClienteByUuid = $cc
                ->filter(fn ($r) => !empty($r->id))
                ->keyBy(fn ($r) => (string) $r->id);
        }

        $accountVendorMap = collect();
        if (Schema::connection($this->adm)->hasTable('finance_account_vendor')) {
            $accountVendorMap = collect(
                DB::connection($this->adm)
                    ->table('finance_account_vendor as fav')
                    ->leftJoin('finance_vendors as v', 'v.id', '=', 'fav.vendor_id')
                    ->select([
                        'fav.account_id',
                        'fav.client_uuid',
                        'fav.vendor_id',
                        'fav.starts_on',
                        'fav.ends_on',
                        'fav.is_primary',
                        'v.name as vendor_name',
                    ])
                    ->orderByDesc('fav.is_primary')
                    ->orderByDesc('fav.id')
                    ->get()
            )->groupBy(fn ($r) => (string) $r->account_id);
        }

        $invoiceByStatementId = collect();
        $invoiceByAccountPeriod = collect();
        if (Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            $invoiceRows = collect(
                DB::connection($this->adm)
                    ->table('billing_invoice_requests')
                    ->select([
                        'id',
                        'statement_id',
                        'account_id',
                        'period',
                        'status',
                        'cfdi_uuid',
                        'issued_at',
                        'meta',
                    ])
                    ->whereIn('period', $filters['periods'])
                    ->orderByDesc('id')
                    ->get()
            );

            $invoiceByStatementId = $invoiceRows
                ->filter(fn ($r) => !empty($r->statement_id))
                ->groupBy(fn ($r) => (int) $r->statement_id);

            $invoiceByAccountPeriod = $invoiceRows
                ->groupBy(fn ($r) => (string) $r->account_id . '|' . (string) $r->period);
        }

        $paymentsByAccountPeriod = collect();
        if (Schema::connection($this->adm)->hasTable('payments')) {
            $paymentRows = collect(
                DB::connection($this->adm)
                    ->table('payments')
                    ->select([
                        'id',
                        'account_id',
                        'amount',
                        'period',
                        'method',
                        'status',
                        'paid_at',
                        'reference',
                        'meta',
                    ])
                    ->whereIn('period', $filters['periods'])
                    ->orderByDesc('id')
                    ->get()
            );

            $agg = [];
            foreach ($paymentRows as $p) {
                $accountId = (string) ($p->account_id ?? '');
                $period    = (string) ($p->period ?? '');
                if ($accountId === '' || $period === '') {
                    continue;
                }

                $key = $accountId . '|' . $period;
                if (!isset($agg[$key])) {
                    $agg[$key] = (object) [
                        'account_id' => $accountId,
                        'period'     => $period,
                        'amount'     => 0.0,
                        'status'     => null,
                        'method'     => null,
                        'paid_at'    => null,
                        'reference'  => null,
                    ];
                }

                $amountMxn = ((float) ($p->amount ?? 0)) / 100;
                $agg[$key]->amount += $amountMxn;

                $status = strtolower(trim((string) ($p->status ?? '')));
                if ($agg[$key]->status === null || $this->paymentStatusWeight($status) >= $this->paymentStatusWeight((string) $agg[$key]->status)) {
                    $agg[$key]->status    = $status;
                    $agg[$key]->method    = $p->method ?? null;
                    $agg[$key]->reference = $p->reference ?? null;
                }

                if (!empty($p->paid_at) && (empty($agg[$key]->paid_at) || (string) $p->paid_at > (string) $agg[$key]->paid_at)) {
                    $agg[$key]->paid_at = $p->paid_at;
                }
            }

            $paymentsByAccountPeriod = collect($agg);
        }

        return [
            'vendor_by_id'               => $vendorById,
            'vendor_list'                => $vendors->map(fn ($v) => ['id' => (string) $v->id, 'name' => (string) $v->name])->values()->all(),
            'billing_profiles_by_acc'    => $billingProfilesByAccountId,
            'accounts_admin'             => $accountsAdmin,
            'cuentas_cliente_by_admin'   => $cuentasClienteByAdmin,
            'cuentas_cliente_by_uuid'    => $cuentasClienteByUuid,
            'account_vendor_map'         => $accountVendorMap,
            'invoice_by_statement_id'    => $invoiceByStatementId,
            'invoice_by_account_period'  => $invoiceByAccountPeriod,
            'payments_by_account_period' => $paymentsByAccountPeriod,
        ];
    }

    private function loadStatementRows(array $filters, array $context): Collection
    {
        if (!Schema::connection($this->adm)->hasTable('billing_statements')) {
            return collect();
        }

        $statementRows = collect(
            DB::connection($this->adm)
                ->table('billing_statements')
                ->select([
                    'id',
                    'account_id',
                    'period',
                    'total_cargo',
                    'status',
                    'due_date',
                    'sent_at',
                    'paid_at',
                    'snapshot',
                    'meta',
                    'created_at',
                    'updated_at',
                ])
                ->whereIn('period', $filters['periods'])
                ->orderBy('period')
                ->orderBy('id')
                ->get()
        );

        $itemsByStatementId = collect();
        if (Schema::connection($this->adm)->hasTable('billing_statement_items') && $statementRows->isNotEmpty()) {
            $itemsByStatementId = collect(
                DB::connection($this->adm)
                    ->table('billing_statement_items')
                    ->select([
                        'id',
                        'statement_id',
                        'type',
                        'code',
                        'description',
                        'qty',
                        'unit_price',
                        'amount',
                        'ref',
                        'meta',
                    ])
                    ->whereIn('statement_id', $statementRows->pluck('id')->map(fn ($v) => (int) $v)->all())
                    ->orderBy('statement_id')
                    ->orderBy('id')
                    ->get()
            )->groupBy(fn ($r) => (int) $r->statement_id);
        }

        return $statementRows->map(function ($st) use ($context, $itemsByStatementId) {
            $accountRaw = trim((string) ($st->account_id ?? ''));
            $period     = (string) ($st->period ?? '');
            $items      = collect($itemsByStatementId->get((int) ($st->id ?? 0), collect()));
            $snapshot   = $this->decodeJson($st->snapshot ?? null);
            $meta       = $this->decodeJson($st->meta ?? null);

            [$adminAccountId, $clientUuid, $clientObj, $accountAdmin] = $this->resolveAccountReferences($accountRaw, $context);

            $displayAccountId = $adminAccountId !== '' ? $adminAccountId : $accountRaw;
            $clientName       = $this->resolveClientName($clientObj, $accountAdmin, $snapshot, $meta);
            $rfcEmisor        = $this->resolveRfcEmisor($clientObj, $accountAdmin);
            $billingProfile   = $this->resolveBillingProfile($displayAccountId, $accountRaw, $context);

            [$vendorId, $vendorName] = $this->resolveVendorForStatement(
                $displayAccountId,
                $clientUuid,
                $period,
                $items,
                $snapshot,
                $meta,
                $context
            );

            $invoiceRow = $this->resolveInvoiceForStatement(
                (int) ($st->id ?? 0),
                $displayAccountId,
                $accountRaw,
                $period,
                $context
            );

            $paymentAgg = $displayAccountId !== ''
                ? $context['payments_by_account_period']->get($displayAccountId . '|' . $period)
                : null;

            $amounts = $this->resolveStatementAmounts(
                (float) ($st->total_cargo ?? 0),
                $items,
                $snapshot,
                $meta
            );

            $origin      = $this->resolveOriginFromStatement($items, $snapshot, $meta, $clientObj);
            $periodicity = $this->resolvePeriodicityFromStatement($items, $snapshot, $meta, $clientObj);

            $ecStatus = $this->normalizeStatementStatus(
                (string) ($st->status ?? ''),
                $st->due_date ?? null,
                $paymentAgg?->status ?? null,
                $st->paid_at ?? null,
                $paymentAgg?->paid_at ?? null,
                $st->sent_at ?? null
            );

            $invoiceStatus = $this->normalizeInvoiceStatus(
                (string) ($invoiceRow?->status ?? ''),
                false
            );

            $invoiceMeta = $this->decodeJson($invoiceRow?->meta ?? null);
            $paidAt = $paymentAgg?->paid_at ?: ($st->paid_at ?? null);
            $fPago  = $paidAt ?: (data_get($invoiceMeta, 'paid_at') ?: null);

            $description = $this->buildStatementDescription($items, $origin, $periodicity);

            return (object) [
                'source'                  => 'statement',
                'source_label'            => 'Statement',
                'source_priority'         => 10,
                'is_projection'           => 0,
                'exclude_from_kpi'        => 0,
                'has_statement'           => 1,

                'period'                  => $period,
                'year'                    => $this->periodYear($period),
                'month_num'               => $this->periodMonth($period),
                'month_name'              => $this->monthNameEsFromPeriod($period),

                'account_id'              => $displayAccountId,
                'account_id_raw'          => $accountRaw,
                'client_account_id'       => $clientUuid !== '' ? $clientUuid : null,

                'client'                  => $clientName,
                'company'                 => $clientName,
                'description'             => $description,

                'vendor_id'               => $vendorId,
                'vendor'                  => $vendorName,

                'origin'                  => $origin,
                'periodicity'             => $periodicity,

                'subtotal'                => $amounts['subtotal'],
                'iva'                     => $amounts['iva'],
                'total'                   => $amounts['total'],

                'ec_status'               => $ecStatus,
                'invoice_status'          => $invoiceStatus,
                'invoice_status_raw'      => (string) ($invoiceRow?->status ?? ''),

                'rfc_emisor'              => $rfcEmisor,
                'rfc_receptor'            => (string) ($billingProfile?->rfc_receptor ?? ''),
                'forma_pago'              => (string) ($billingProfile?->forma_pago ?? (data_get($invoiceMeta, 'forma_pago') ?: '')),
                'invoice_metodo_pago'     => (string) ($billingProfile?->metodo_pago ?? (data_get($invoiceMeta, 'metodo_pago') ?: '')),

                'f_cta'                   => $st->created_at ?? null,
                'f_mov'                   => $st->updated_at ?? null,
                'f_factura'               => $invoiceRow?->issued_at ?? null,
                'f_pago'                  => $fPago,

                'sent_at'                 => $st->sent_at ?? null,
                'paid_at'                 => $paidAt,
                'due_date'                => $st->due_date ?? null,

                'payment_method'          => $paymentAgg?->method ?? null,
                'payment_status'          => $paymentAgg?->status ?? null,

                'cfdi_uuid'               => (string) ($invoiceRow?->cfdi_uuid ?? ''),
                'statement_id'            => (int) ($st->id ?? 0),
                'sale_id'                 => 0,
                'include_in_statement'    => null,
                'statement_period_target' => null,

                'notes'                   => (string) (data_get($meta, 'notes') ?? ''),
                'audit_key'               => 'statement|' . $displayAccountId . '|' . $period,
            ];
        })->values();
    }

    private function loadSalesRows(array $filters, array $context): Collection
    {
        if ((int) $filters['include_sales'] !== 1) {
            return collect();
        }

        if (!Schema::connection($this->adm)->hasTable('finance_sales')) {
            return collect();
        }

        $sales = collect(
            DB::connection($this->adm)
                ->table('finance_sales as s')
                ->leftJoin('finance_vendors as v', 'v.id', '=', 's.vendor_id')
                ->leftJoin('accounts as a', 'a.id', '=', 's.account_id')
                ->select([
                    's.id',
                    's.sale_code',
                    's.account_id',
                    's.period',
                    's.vendor_id',
                    's.origin',
                    's.periodicity',
                    's.f_cta',
                    's.f_mov',
                    's.invoice_date',
                    's.paid_date',
                    's.sale_date',
                    's.receiver_rfc',
                    's.pay_method',
                    's.subtotal',
                    's.iva',
                    's.total',
                    's.statement_status',
                    's.invoice_status',
                    's.invoice_uuid',
                    's.cfdi_uuid',
                    's.include_in_statement',
                    's.target_period',
                    's.statement_period_target',
                    's.statement_id',
                    's.statement_item_id',
                    's.notes',
                    's.meta',
                    's.created_at',
                    's.updated_at',
                    'v.name as vendor_name',
                    'a.name as account_name',
                    'a.razon_social as account_razon_social',
                    'a.rfc as account_rfc',
                ])
                ->whereIn('s.period', $filters['periods'])
                ->orderBy('s.period')
                ->orderBy('s.id')
                ->get()
        );

        return $sales->map(function ($sale) use ($context) {
            $period         = (string) ($sale->period ?? '');
            $accountId      = (string) ($sale->account_id ?? '');
            $accountAdmin   = $context['accounts_admin']->get($accountId);
            $clientObj      = $context['cuentas_cliente_by_admin']->get($accountId);
            $clientName     = $this->resolveClientName($clientObj, $accountAdmin, [], []);
            $rfcEmisor      = $this->resolveRfcEmisor($clientObj, $accountAdmin);
            $invoiceStatus  = $this->normalizeInvoiceStatus((string) ($sale->invoice_status ?? ''), false);
            $ecStatus       = $this->normalizeSaleStatus((string) ($sale->statement_status ?? 'pending'));

            $description = trim((string) ($sale->notes ?? ''));
            if ($description === '') {
                $description = trim((string) ($sale->sale_code ?? ''));
            }
            if ($description === '') {
                $description = strtolower((string) ($sale->origin ?? '')) === 'recurrente'
                    ? 'Venta recurrente'
                    : 'Venta única';
            }

            $origin = strtolower(trim((string) ($sale->origin ?? 'unico')));
            if ($origin === 'no_recurrente') {
                $origin = 'unico';
            }
            if (!in_array($origin, ['recurrente', 'unico'], true)) {
                $origin = 'unico';
            }

            $periodicity = strtolower(trim((string) ($sale->periodicity ?? 'unico')));
            if (!in_array($periodicity, ['mensual', 'anual', 'unico'], true)) {
                $periodicity = 'unico';
            }

            return (object) [
                'source'                  => ((int) ($sale->include_in_statement ?? 0) === 1) ? 'sale_linked' : 'sale',
                'source_label'            => 'Venta',
                'source_priority'         => ((int) ($sale->include_in_statement ?? 0) === 1) ? 30 : 20,
                'is_projection'           => 0,
                'exclude_from_kpi'        => 0,
                'has_statement'           => ((int) ($sale->include_in_statement ?? 0) === 1 && !empty($sale->statement_id)) ? 1 : 0,

                'period'                  => $period,
                'year'                    => $this->periodYear($period),
                'month_num'               => $this->periodMonth($period),
                'month_name'              => $this->monthNameEsFromPeriod($period),

                'account_id'              => $accountId,
                'account_id_raw'          => $accountId,
                'client_account_id'       => $clientObj?->id ? (string) $clientObj->id : null,

                'client'                  => $clientName,
                'company'                 => $clientName,
                'description'             => $description,

                'vendor_id'               => !empty($sale->vendor_id) ? (string) $sale->vendor_id : null,
                'vendor'                  => !empty($sale->vendor_name) ? (string) $sale->vendor_name : null,

                'origin'                  => $origin,
                'periodicity'             => $periodicity,

                'subtotal'                => round((float) ($sale->subtotal ?? 0), 2),
                'iva'                     => round((float) ($sale->iva ?? 0), 2),
                'total'                   => round((float) ($sale->total ?? 0), 2),

                'ec_status'               => $ecStatus,
                'invoice_status'          => $invoiceStatus,
                'invoice_status_raw'      => (string) ($sale->invoice_status ?? ''),

                'rfc_emisor'              => $rfcEmisor,
                'rfc_receptor'            => (string) ($sale->receiver_rfc ?? ''),
                'forma_pago'              => (string) ($sale->pay_method ?? ''),
                'invoice_metodo_pago'     => null,

                'f_cta'                   => $sale->f_cta ?? null,
                'f_mov'                   => $sale->f_mov ?? ($sale->sale_date ?? null),
                'f_factura'               => $sale->invoice_date ?? null,
                'f_pago'                  => $sale->paid_date ?? null,

                'sent_at'                 => null,
                'paid_at'                 => $sale->paid_date ?? null,
                'due_date'                => null,

                'payment_method'          => null,
                'payment_status'          => null,

                'cfdi_uuid'               => (string) (($sale->cfdi_uuid ?: null) ?? ($sale->invoice_uuid ?: '')),
                'statement_id'            => !empty($sale->statement_id) ? (int) $sale->statement_id : null,
                'sale_id'                 => (int) ($sale->id ?? 0),
                'include_in_statement'    => (int) ($sale->include_in_statement ?? 0),
                'statement_period_target' => (string) (($sale->statement_period_target ?: null) ?? ($sale->target_period ?: '')),

                'notes'                   => (string) ($sale->notes ?? ''),
                'audit_key'               => 'sale|' . (int) ($sale->id ?? 0),
            ];
        })->values();
    }

    private function loadProjectionRows(
        array $filters,
        array $context,
        Collection $statementRows,
        Collection $salesRows
    ): Collection {
        if ((int) $filters['include_projections'] !== 1) {
            return collect();
        }

        $existingKeys = [];

        foreach ($statementRows as $row) {
            $key = (string) ($row->account_id ?? '') . '|' . (string) ($row->period ?? '');
            if ($key !== '|') {
                $existingKeys[$key] = true;
            }
        }

        foreach ($salesRows as $row) {
            $key = (string) ($row->account_id ?? '') . '|' . (string) ($row->period ?? '');
            if ($key !== '|') {
                $existingKeys[$key] = true;
            }
        }

        $rows = collect();

        foreach ($context['cuentas_cliente_by_admin'] as $adminAccountId => $cc) {
            $modo = strtolower(trim((string) ($cc->modo_cobro ?? '')));
            if (!in_array($modo, ['mensual', 'anual'], true)) {
                continue;
            }

            $plan = strtolower(trim((string) ($cc->plan_actual ?? '')));
            $baseSubtotal = $this->guessProjectionSubtotalFromPlan($plan, $modo);
            if ($baseSubtotal <= 0) {
                continue;
            }

            foreach ($filters['periods'] as $period) {
                $key = (string) $adminAccountId . '|' . (string) $period;
                if (isset($existingKeys[$key])) {
                    continue;
                }

                [$vendorId, $vendorName] = $this->resolveVendorFallback(
                    (string) $adminAccountId,
                    (string) ($cc->id ?? ''),
                    (string) $period,
                    $context
                );

                $clientName = $this->resolveClientName($cc, $context['accounts_admin']->get((string) $adminAccountId), [], []);
                $rfcEmisor  = $this->resolveRfcEmisor($cc, $context['accounts_admin']->get((string) $adminAccountId));

                $subtotal = round($baseSubtotal, 2);
                $iva      = round($subtotal * 0.16, 2);
                $total    = round($subtotal + $iva, 2);

                $rows->push((object) [
                    'source'                  => 'projection',
                    'source_label'            => 'Proyección',
                    'source_priority'         => 40,
                    'is_projection'           => 1,
                    'exclude_from_kpi'        => 1,
                    'has_statement'           => 0,

                    'period'                  => $period,
                    'year'                    => $this->periodYear($period),
                    'month_num'               => $this->periodMonth($period),
                    'month_name'              => $this->monthNameEsFromPeriod($period),

                    'account_id'              => (string) $adminAccountId,
                    'account_id_raw'          => (string) $adminAccountId,
                    'client_account_id'       => !empty($cc->id) ? (string) $cc->id : null,

                    'client'                  => $clientName,
                    'company'                 => $clientName,
                    'description'             => $modo === 'anual' ? 'Proyección recurrente anual' : 'Proyección recurrente mensual',

                    'vendor_id'               => $vendorId,
                    'vendor'                  => $vendorName,

                    'origin'                  => 'recurrente',
                    'periodicity'             => $modo,

                    'subtotal'                => $subtotal,
                    'iva'                     => $iva,
                    'total'                   => $total,

                    'ec_status'               => 'pending',
                    'invoice_status'          => 'sin_solicitud',
                    'invoice_status_raw'      => 'sin_solicitud',

                    'rfc_emisor'              => $rfcEmisor,
                    'rfc_receptor'            => '',
                    'forma_pago'              => '',
                    'invoice_metodo_pago'     => '',

                    'f_cta'                   => null,
                    'f_mov'                   => null,
                    'f_factura'               => null,
                    'f_pago'                  => null,

                    'sent_at'                 => null,
                    'paid_at'                 => null,
                    'due_date'                => null,

                    'payment_method'          => null,
                    'payment_status'          => null,

                    'cfdi_uuid'               => '',
                    'statement_id'            => null,
                    'sale_id'                 => 0,
                    'include_in_statement'    => 0,
                    'statement_period_target' => null,

                    'notes'                   => '',
                    'audit_key'               => 'projection|' . $adminAccountId . '|' . $period,
                ]);
            }
        }

        return $rows->values();
    }

    private function applyOverrides(Collection $rows, array $context): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        if (!Schema::connection($this->adm)->hasTable('finance_income_overrides')) {
            return $rows;
        }

        $accountIds = $rows
            ->pluck('account_id')
            ->filter(fn ($v) => (string) $v !== '')
            ->unique()
            ->values()
            ->all();

        $periods = $rows
            ->pluck('period')
            ->filter(fn ($v) => (string) $v !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($accountIds) || empty($periods)) {
            return $rows;
        }

        $overrideRows = collect(
            DB::connection($this->adm)
                ->table('finance_income_overrides')
                ->select([
                    'row_type',
                    'account_id',
                    'period',
                    'sale_id',
                    'vendor_id',
                    'ec_status',
                    'invoice_status',
                    'cfdi_uuid',
                    'rfc_receptor',
                    'forma_pago',
                    'subtotal',
                    'iva',
                    'total',
                    'notes',
                    'updated_by',
                    'updated_at',
                ])
                ->whereIn('account_id', $accountIds)
                ->whereIn('period', $periods)
                ->get()
        );

        $byKey = $overrideRows->keyBy(function ($ov) {
            return (string) $ov->row_type . '|' . (string) $ov->account_id . '|' . (string) $ov->period;
        });

        return $rows->map(function ($row) use ($byKey, $context) {
            $rowType = match ((string) ($row->source ?? '')) {
                'projection' => 'projection',
                default      => 'statement',
            };

            if ($rowType === 'statement' && (int) ($row->sale_id ?? 0) > 0) {
                return $row;
            }

            $key = $rowType . '|' . (string) ($row->account_id ?? '') . '|' . (string) ($row->period ?? '');
            $ov  = $byKey->get($key);

            if (!$ov) {
                return $row;
            }

            if ($ov->vendor_id !== null) {
                $row->vendor_id = (string) $ov->vendor_id;
                $vendorRow = $context['vendor_by_id']->get((string) $ov->vendor_id);
                if ($vendorRow) {
                    $row->vendor = (string) ($vendorRow->name ?? $row->vendor ?? '');
                }
            }

            if ($ov->ec_status !== null) {
                $row->ec_status = strtolower(trim((string) $ov->ec_status));
            }

            if ($ov->invoice_status !== null) {
                $row->invoice_status = $this->normalizeInvoiceStatus((string) $ov->invoice_status, false);
                $row->invoice_status_raw = (string) $ov->invoice_status;
            }

            if ($ov->cfdi_uuid !== null) {
                $row->cfdi_uuid = (string) $ov->cfdi_uuid;
            }

            if ($ov->rfc_receptor !== null) {
                $row->rfc_receptor = (string) $ov->rfc_receptor;
            }

            if ($ov->forma_pago !== null) {
                $row->forma_pago = (string) $ov->forma_pago;
            }

            if ($ov->subtotal !== null) {
                $row->subtotal = round((float) $ov->subtotal, 2);
            }

            if ($ov->iva !== null) {
                $row->iva = round((float) $ov->iva, 2);
            }

            if ($ov->total !== null) {
                $row->total = round((float) $ov->total, 2);
            }

            if ($ov->notes !== null) {
                $row->notes = (string) $ov->notes;
            }

            $row->has_override      = 1;
            $row->override_updated_by = $ov->updated_by !== null ? (int) $ov->updated_by : null;
            $row->override_updated_at = $ov->updated_at ?? null;

            return $row;
        })->values();
    }

    private function applyFilters(Collection $rows, array $filters): Collection
    {
        return $rows->filter(function ($row) use ($filters) {
            $origin = strtolower(trim((string) ($row->origin ?? '')));
            if ($filters['origin'] !== 'all' && $origin !== $filters['origin']) {
                return false;
            }

            $status = strtolower(trim((string) ($row->ec_status ?? '')));
            if ($filters['status'] !== 'all' && $status !== $filters['status']) {
                return false;
            }

            $invoiceStatus = $this->normalizeInvoiceStatus((string) ($row->invoice_status ?? ''), false);
            if ($filters['invoice_status'] !== 'all' && $invoiceStatus !== $filters['invoice_status']) {
                return false;
            }

            $vendorId = (string) ($row->vendor_id ?? '');
            if ($filters['vendor_id'] !== 'all' && $vendorId !== (string) $filters['vendor_id']) {
                return false;
            }

            if ($filters['q'] !== '') {
                $haystack = strtolower(implode(' ', array_filter([
                    (string) ($row->client ?? ''),
                    (string) ($row->company ?? ''),
                    (string) ($row->account_id ?? ''),
                    (string) ($row->description ?? ''),
                    (string) ($row->vendor ?? ''),
                    (string) ($row->rfc_emisor ?? ''),
                    (string) ($row->rfc_receptor ?? ''),
                    (string) ($row->cfdi_uuid ?? ''),
                    (string) ($row->source ?? ''),
                ])));

                if (!str_contains($haystack, strtolower($filters['q']))) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function sortRows(Collection $rows): Collection
    {
        return $rows
            ->sortBy([
                fn ($r) => (string) ($r->period ?? ''),
                fn ($r) => (int) ($r->source_priority ?? 999),
                fn ($r) => -1 * (float) ($r->total ?? 0),
                fn ($r) => (string) ($r->client ?? ''),
            ])
            ->values();
    }

    private function buildKpis(Collection $rows): array
    {
        $realRows = $rows->filter(fn ($r) => (int) ($r->exclude_from_kpi ?? 0) !== 1)->values();
        $projectionRows = $rows->filter(fn ($r) => (string) ($r->source ?? '') === 'projection')->values();

        $pending  = $realRows->where('ec_status', 'pending');
        $emitido  = $realRows->where('ec_status', 'emitido');
        $pagado   = $realRows->where('ec_status', 'pagado');
        $vencido  = $realRows->where('ec_status', 'vencido');

        $realTotal = round((float) $realRows->sum('total'), 2);
        $projected = round((float) $projectionRows->sum('total'), 2);
        $collected = round((float) $pagado->sum('total'), 2);
        $receivable = round((float) ($pending->sum('total') + $emitido->sum('total') + $vencido->sum('total')), 2);

        $goal = round($realTotal + $projected, 2);
        $goalProgress = $goal > 0 ? round(($realTotal / $goal) * 100, 2) : 0.0;

        return [
            'total' => [
                'count'  => $realRows->count(),
                'amount' => $realTotal,
            ],
            'pagado' => [
                'count'  => $pagado->count(),
                'amount' => round((float) $pagado->sum('total'), 2),
            ],
            'pending' => [
                'count'  => $pending->count(),
                'amount' => round((float) $pending->sum('total'), 2),
            ],
            'emitido' => [
                'count'  => $emitido->count(),
                'amount' => round((float) $emitido->sum('total'), 2),
            ],
            'vencido' => [
                'count'  => $vencido->count(),
                'amount' => round((float) $vencido->sum('total'), 2),
            ],
            'projected' => [
                'count'  => $projectionRows->count(),
                'amount' => $projected,
            ],
            'receivable' => [
                'count'  => $pending->count() + $emitido->count() + $vencido->count(),
                'amount' => $receivable,
            ],
            'goal' => [
                'amount'   => $goal,
                'progress' => $goalProgress,
            ],
            'mix' => [
                'recurrente' => round((float) $realRows->where('origin', 'recurrente')->sum('total'), 2),
                'unico'      => round((float) $realRows->where('origin', 'unico')->sum('total'), 2),
            ],
            'sources' => [
                'statement' => round((float) $realRows->where('source', 'statement')->sum('total'), 2),
                'sale'      => round((float) $realRows->filter(fn ($r) => str_starts_with((string) ($r->source ?? ''), 'sale'))->sum('total'), 2),
            ],
            'cash' => [
                'amount' => $collected,
            ],
        ];
    }

    private function buildCharts(Collection $rows, array $filters): array
    {
        $allPeriods = $filters['periods'];

        $monthly = [];
        foreach ($allPeriods as $period) {
            $slice = $rows->where('period', $period);

            $real = round((float) $slice->filter(fn ($r) => (int) ($r->exclude_from_kpi ?? 0) !== 1)->sum('total'), 2);
            $projected = round((float) $slice->where('source', 'projection')->sum('total'), 2);
            $collected = round((float) $slice->where('ec_status', 'pagado')->sum('total'), 2);

            $monthly[] = [
                'period'     => $period,
                'label'      => $this->monthNameEsFromPeriod($period),
                'real'       => $real,
                'projected'  => $projected,
                'collected'  => $collected,
            ];
        }

        $realRows = $rows->filter(fn ($r) => (int) ($r->exclude_from_kpi ?? 0) !== 1)->values();

        $originMix = [
            ['label' => 'Recurrente', 'value' => round((float) $realRows->where('origin', 'recurrente')->sum('total'), 2)],
            ['label' => 'Único',      'value' => round((float) $realRows->where('origin', 'unico')->sum('total'), 2)],
        ];

        $vendorTop = $realRows
            ->groupBy(fn ($r) => (string) ($r->vendor ?: 'Sin vendedor'))
            ->map(function ($group, $vendorName) {
                return [
                    'label' => $vendorName,
                    'value' => round((float) $group->sum('total'), 2),
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->take(5)
            ->all();

        return [
            'monthly'   => $monthly,
            'originMix' => $originMix,
            'vendorTop' => $vendorTop,
        ];
    }

    private function buildHighlights(Collection $rows, array $filters): array
    {
        $realRows = $rows->filter(fn ($r) => (int) ($r->exclude_from_kpi ?? 0) !== 1)->values();

        $bestMonth = $realRows
            ->groupBy('period')
            ->map(fn ($group, $period) => [
                'period' => (string) $period,
                'label'  => $this->monthNameEsFromPeriod((string) $period),
                'total'  => round((float) $group->sum('total'), 2),
            ])
            ->sortByDesc('total')
            ->first();

        $topVendor = $realRows
            ->groupBy(fn ($r) => (string) ($r->vendor ?: 'Sin vendedor'))
            ->map(fn ($group, $vendor) => [
                'vendor' => $vendor,
                'total'  => round((float) $group->sum('total'), 2),
            ])
            ->sortByDesc('total')
            ->first();

        $criticalPending = $realRows
            ->filter(fn ($r) => in_array((string) ($r->ec_status ?? ''), ['pending', 'vencido'], true))
            ->sortByDesc(fn ($r) => (float) ($r->total ?? 0))
            ->first();

        return [
            'best_month' => $bestMonth ?: [
                'period' => null,
                'label'  => '—',
                'total'  => 0.0,
            ],
            'top_vendor' => $topVendor ?: [
                'vendor' => '—',
                'total'  => 0.0,
            ],
            'critical_pending' => $criticalPending ? [
                'client' => (string) ($criticalPending->client ?? '—'),
                'period' => (string) ($criticalPending->period ?? ''),
                'total'  => round((float) ($criticalPending->total ?? 0), 2),
                'status' => (string) ($criticalPending->ec_status ?? ''),
            ] : [
                'client' => '—',
                'period' => '',
                'total'  => 0.0,
                'status' => '',
            ],
        ];
    }

    private function resolveAccountReferences(string $accountRaw, array $context): array
    {
        $adminAccountId = '';
        $clientUuid = '';
        $clientObj = null;
        $accountAdmin = null;

        if ($accountRaw !== '' && preg_match('/^\d+$/', $accountRaw)) {
            $adminAccountId = $accountRaw;
            $clientObj = $context['cuentas_cliente_by_admin']->get($accountRaw);
            if ($clientObj && !empty($clientObj->id)) {
                $clientUuid = (string) $clientObj->id;
            }
            $accountAdmin = $context['accounts_admin']->get($accountRaw);
        } elseif ($accountRaw !== '' && preg_match('/^[0-9a-f\-]{36}$/i', $accountRaw)) {
            $clientUuid = $accountRaw;
            $clientObj = $context['cuentas_cliente_by_uuid']->get($accountRaw);
            if ($clientObj && !empty($clientObj->admin_account_id)) {
                $adminAccountId = (string) $clientObj->admin_account_id;
                $accountAdmin = $context['accounts_admin']->get($adminAccountId);
            }
        }

        return [$adminAccountId, $clientUuid, $clientObj, $accountAdmin];
    }

    private function resolveClientName(?object $clientObj, ?object $accountAdmin, array $snapshot, array $meta): string
    {
        $candidates = [
            $clientObj?->nombre_comercial ?? null,
            $clientObj?->razon_social ?? null,
            $clientObj?->empresa ?? null,
            $accountAdmin?->razon_social ?? null,
            $accountAdmin?->name ?? null,
            data_get($snapshot, 'account.company'),
            data_get($snapshot, 'company'),
            data_get($snapshot, 'razon_social'),
            data_get($meta, 'account.company'),
            data_get($meta, 'company'),
            data_get($meta, 'razon_social'),
        ];

        foreach ($candidates as $value) {
            $value = trim((string) ($value ?? ''));
            if ($value !== '' && !$this->isPlaceholderName($value)) {
                return $value;
            }
        }

        return '—';
    }

    private function resolveRfcEmisor(?object $clientObj, ?object $accountAdmin): string
    {
        $candidates = [
            $clientObj?->rfc_padre ?? null,
            $clientObj?->rfc ?? null,
            $accountAdmin?->rfc ?? null,
        ];

        foreach ($candidates as $value) {
            $value = strtoupper(trim((string) ($value ?? '')));
            if ($value !== '' && $this->isValidRfc($value)) {
                return $value;
            }
        }

        return '';
    }

    private function resolveBillingProfile(string $accountId, string $accountRaw, array $context): ?object
    {
        if ($accountId !== '' && $context['billing_profiles_by_acc']->has($accountId)) {
            return $context['billing_profiles_by_acc']->get($accountId);
        }

        if ($accountRaw !== '' && $context['billing_profiles_by_acc']->has($accountRaw)) {
            return $context['billing_profiles_by_acc']->get($accountRaw);
        }

        return null;
    }

    private function resolveVendorForStatement(
        string $adminAccountId,
        string $clientUuid,
        string $period,
        Collection $items,
        array $snapshot,
        array $meta,
        array $context
    ): array {
        $vendorId = null;

        $candidateIds = [];

        $candidateIds[] = data_get($meta, 'vendor_id');
        $candidateIds[] = data_get($meta, 'vendor.id');
        $candidateIds[] = data_get($snapshot, 'vendor_id');
        $candidateIds[] = data_get($snapshot, 'vendor.id');

        foreach ($items as $item) {
            $itemMeta = $this->decodeJson($item->meta ?? null);
            $candidateIds[] = data_get($itemMeta, 'vendor_id');
            $candidateIds[] = data_get($itemMeta, 'vendor.id');
        }

        foreach ($candidateIds as $candidate) {
            $candidate = trim((string) ($candidate ?? ''));
            if ($candidate !== '') {
                $vendorId = $candidate;
                break;
            }
        }

        if ($vendorId !== null && $context['vendor_by_id']->has((string) $vendorId)) {
            $vendor = $context['vendor_by_id']->get((string) $vendorId);
            return [(string) $vendorId, (string) ($vendor->name ?? '')];
        }

        return $this->resolveVendorFallback($adminAccountId, $clientUuid, $period, $context);
    }

    private function resolveVendorFallback(string $adminAccountId, string $clientUuid, string $period, array $context): array
    {
        if ($adminAccountId !== '' && $context['account_vendor_map']->has($adminAccountId)) {
            foreach ($context['account_vendor_map']->get($adminAccountId) as $assign) {
                if ($this->assignmentMatchesPeriod($assign, $period)) {
                    return [
                        !empty($assign->vendor_id) ? (string) $assign->vendor_id : null,
                        !empty($assign->vendor_name) ? (string) $assign->vendor_name : null,
                    ];
                }
            }
        }

        return [null, null];
    }

    private function resolveInvoiceForStatement(
        int $statementId,
        string $displayAccountId,
        string $accountRaw,
        string $period,
        array $context
    ): ?object {
        if ($statementId > 0 && $context['invoice_by_statement_id']->has($statementId)) {
            return $context['invoice_by_statement_id']->get($statementId)->first();
        }

        if ($displayAccountId !== '') {
            $key = $displayAccountId . '|' . $period;
            if ($context['invoice_by_account_period']->has($key)) {
                return $context['invoice_by_account_period']->get($key)->first();
            }
        }

        if ($accountRaw !== '') {
            $key = $accountRaw . '|' . $period;
            if ($context['invoice_by_account_period']->has($key)) {
                return $context['invoice_by_account_period']->get($key)->first();
            }
        }

        return null;
    }

    private function resolveStatementAmounts(float $totalCargo, Collection $items, array $snapshot, array $meta): array
    {
        $snapSubtotal = (float) (
            data_get($snapshot, 'totals.subtotal')
            ?? data_get($snapshot, 'statement.subtotal')
            ?? data_get($snapshot, 'subtotal')
            ?? data_get($meta, 'totals.subtotal')
            ?? data_get($meta, 'statement.subtotal')
            ?? data_get($meta, 'subtotal')
            ?? 0
        );

        $snapIva = (float) (
            data_get($snapshot, 'totals.iva')
            ?? data_get($snapshot, 'statement.iva')
            ?? data_get($snapshot, 'iva')
            ?? data_get($meta, 'totals.iva')
            ?? data_get($meta, 'statement.iva')
            ?? data_get($meta, 'iva')
            ?? 0
        );

        $snapTotal = (float) (
            data_get($snapshot, 'totals.total')
            ?? data_get($snapshot, 'statement.total')
            ?? data_get($snapshot, 'total')
            ?? data_get($meta, 'totals.total')
            ?? data_get($meta, 'statement.total')
            ?? data_get($meta, 'total')
            ?? 0
        );

        if ($snapTotal > 0 || $snapSubtotal > 0) {
            $subtotal = $snapSubtotal > 0 ? round($snapSubtotal, 2) : round($snapTotal / 1.16, 2);
            $total    = $snapTotal > 0 ? round($snapTotal, 2) : round($subtotal * 1.16, 2);
            $iva      = $snapIva > 0 ? round($snapIva, 2) : round($total - $subtotal, 2);

            return [
                'subtotal' => round(max(0, $subtotal), 2),
                'iva'      => round(max(0, $iva), 2),
                'total'    => round(max(0, $total), 2),
            ];
        }

        $totalCargo = round((float) $totalCargo, 2);
        if ($totalCargo > 0) {
            $subtotal = round($totalCargo / 1.16, 2);
            $iva      = round($totalCargo - $subtotal, 2);

            return [
                'subtotal' => $subtotal,
                'iva'      => $iva,
                'total'    => $totalCargo,
            ];
        }

        $itemsAmount = round((float) $items->sum(fn ($r) => (float) ($r->amount ?? 0)), 2);
        if ($itemsAmount > 0) {
            $subtotal = round($itemsAmount, 2);
            $iva      = round($subtotal * 0.16, 2);
            $total    = round($subtotal + $iva, 2);

            return [
                'subtotal' => $subtotal,
                'iva'      => $iva,
                'total'    => $total,
            ];
        }

        return [
            'subtotal' => 0.0,
            'iva'      => 0.0,
            'total'    => 0.0,
        ];
    }

    private function resolveOriginFromStatement(Collection $items, array $snapshot, array $meta, ?object $clientObj): string
    {
        $mode = strtolower(trim((string) (
            data_get($snapshot, 'license.mode')
            ?? data_get($meta, 'license.mode')
            ?? ($clientObj?->modo_cobro ?? '')
        )));

        if (in_array($mode, ['mensual', 'anual'], true)) {
            return 'recurrente';
        }

        foreach ($items as $item) {
            $itemMeta = $this->decodeJson($item->meta ?? null);

            $origin = strtolower(trim((string) (
                data_get($itemMeta, 'origin')
                ?? ''
            )));

            if ($origin === 'no_recurrente') {
                return 'unico';
            }

            if (in_array($origin, ['recurrente', 'unico'], true)) {
                return $origin;
            }

            $type = strtolower(trim((string) ($item->type ?? '')));
            if (in_array($type, ['license', 'subscription', 'plan'], true)) {
                return 'recurrente';
            }
        }

        return 'unico';
    }

    private function resolvePeriodicityFromStatement(Collection $items, array $snapshot, array $meta, ?object $clientObj): string
    {
        $mode = strtolower(trim((string) (
            data_get($snapshot, 'license.mode')
            ?? data_get($meta, 'license.mode')
            ?? ($clientObj?->modo_cobro ?? '')
        )));

        if (in_array($mode, ['mensual', 'anual'], true)) {
            return $mode;
        }

        foreach ($items as $item) {
            $itemMeta = $this->decodeJson($item->meta ?? null);
            $periodicity = strtolower(trim((string) (
                data_get($itemMeta, 'periodicity')
                ?? ''
            )));

            if (in_array($periodicity, ['mensual', 'anual', 'unico'], true)) {
                return $periodicity;
            }
        }

        return 'unico';
    }

    private function buildStatementDescription(Collection $items, string $origin, string $periodicity): string
    {
        $parts = $items
            ->map(function ($item) {
                $desc = trim((string) ($item->description ?? ''));
                if ($desc === '') {
                    $desc = trim((string) ($item->code ?? ''));
                }
                return $desc;
            })
            ->filter()
            ->unique()
            ->values()
            ->take(3)
            ->all();

        if (!empty($parts)) {
            return implode(' · ', $parts);
        }

        if ($origin === 'recurrente') {
            return $periodicity === 'anual'
                ? 'Estado de cuenta recurrente anual'
                : 'Estado de cuenta recurrente mensual';
        }

        return 'Estado de cuenta';
    }

    private function normalizeStatementStatus(
        string $statusRaw,
        mixed $dueDate,
        ?string $paymentStatus,
        mixed $paidAt,
        mixed $paymentPaidAt,
        mixed $sentAt
    ): string {
        if (!empty($paidAt) || !empty($paymentPaidAt)) {
            return 'pagado';
        }

        $paymentStatus = strtolower(trim((string) ($paymentStatus ?? '')));
        if (in_array($paymentStatus, ['paid', 'pagado', 'succeeded', 'success', 'complete', 'completed'], true)) {
            return 'pagado';
        }

        if (!empty($sentAt)) {
            return 'emitido';
        }

        $status = strtolower(trim((string) $statusRaw));
        $status = match ($status) {
            'paid', 'pagado'      => 'pagado',
            'sent', 'emitido'     => 'emitido',
            'overdue', 'vencido'  => 'vencido',
            default               => 'pending',
        };

        if ($status === 'pending' && !empty($dueDate)) {
            try {
                if (Carbon::parse((string) $dueDate)->startOfDay()->lt(now()->startOfDay())) {
                    return 'vencido';
                }
            } catch (\Throwable $e) {
                // noop
            }
        }

        return $status;
    }

    private function normalizeSaleStatus(string $statusRaw): string
    {
        $status = strtolower(trim($statusRaw));

        return match ($status) {
            'emitido' => 'emitido',
            'pagado'  => 'pagado',
            'vencido' => 'vencido',
            default   => 'pending',
        };
    }

    private function normalizeInvoiceStatus(string $statusRaw, bool $allowAll): string
    {
        $status = strtolower(trim($statusRaw));
        $status = str_replace([' ', '-'], '_', $status);

        if ($allowAll && in_array($status, ['', 'all', 'todos', 'todas'], true)) {
            return 'all';
        }

        return match ($status) {
            '', 'sin', 'none', 'sin_solicitud', 'no_request', 'no_solicitud' => 'sin_solicitud',
            'requested', 'solicitada', 'solicitado'                          => 'requested',
            'ready', 'en_proceso', 'procesando'                              => 'ready',
            'issued', 'facturada', 'facturado', 'timbrada', 'timbrado'      => 'issued',
            'cancelled', 'canceled', 'rechazada', 'rechazado'                => 'cancelled',
            default                                                          => $allowAll ? 'all' : 'sin_solicitud',
        };
    }

    private function paymentStatusWeight(string $status): int
    {
        return match (strtolower(trim($status))) {
            'paid', 'pagado', 'succeeded', 'success', 'complete', 'completed' => 100,
            'pending'                                                          => 50,
            default                                                            => 0,
        };
    }

    private function assignmentMatchesPeriod(object $assign, string $period): bool
    {
        if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
            return true;
        }

        $periodDate = $period . '-01';
        $startsOn   = (string) ($assign->starts_on ?? '');
        $endsOn     = (string) ($assign->ends_on ?? '');

        if ($startsOn !== '' && $periodDate < $startsOn) {
            return false;
        }

        if ($endsOn !== '' && $periodDate > $endsOn) {
            return false;
        }

        return true;
    }

    private function guessProjectionSubtotalFromPlan(string $plan, string $modo): float
    {
        $map = [
            'free'       => ['mensual' => 0.00,   'anual' => 0.00],
            'basic'      => ['mensual' => 580.00, 'anual' => 5800.00],
            'pro'        => ['mensual' => 980.00, 'anual' => 9800.00],
            'enterprise' => ['mensual' => 1980.00, 'anual' => 19800.00],
        ];

        return (float) ($map[$plan][$modo] ?? 0.0);
    }

    private function isPlaceholderName(string $name): bool
    {
        return (bool) preg_match('/^cuenta\s+\d+$/i', trim($name));
    }

    private function isValidRfc(string $rfc): bool
    {
        return (bool) preg_match('/^([A-ZÑ&]{3,4})(\d{6})([A-Z0-9]{3})$/', strtoupper(trim($rfc)));
    }

    private function decodeJson(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function hasCol(string $conn, string $table, string $column): bool
    {
        try {
            return Schema::connection($conn)->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function periodYear(string $period): int
    {
        return preg_match('/^\d{4}\-\d{2}$/', $period) ? (int) substr($period, 0, 4) : (int) now()->year;
    }

    private function periodMonth(string $period): string
    {
        return preg_match('/^\d{4}\-\d{2}$/', $period) ? substr($period, 5, 2) : now()->format('m');
    }

    private function monthNameEsFromPeriod(string $period): string
    {
        return $this->monthNameEs((int) $this->periodMonth($period));
    }

    private function monthNameEs(int $month): string
    {
        return match ($month) {
            1  => 'Enero',
            2  => 'Febrero',
            3  => 'Marzo',
            4  => 'Abril',
            5  => 'Mayo',
            6  => 'Junio',
            7  => 'Julio',
            8  => 'Agosto',
            9  => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
            default => '—',
        };
    }
}