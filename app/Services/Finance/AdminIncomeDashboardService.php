<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Http\Controllers\Admin\Billing\BillingStatementsHubController;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminIncomeDashboardService
{
    private string $adm;
    private string $cli;
    private ?BillingStatementsHubController $hub = null;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $this->cli = (string) (config('p360.conn.clientes') ?: 'mysql_clientes');

        try {
            $this->hub = app(BillingStatementsHubController::class);
        } catch (\Throwable $e) {
            $this->hub = null;
        }
    }

    public function build(Request $request): array
    {
        $filters = $this->resolveFilters($request);
        $context = $this->loadContext($filters);

        $statementRows = $this->loadStatementRows($filters, $context);

        $linkedSaleIds = $statementRows
            ->flatMap(fn ($r) => collect($r->linked_sale_ids ?? []))
            ->filter(fn ($v) => (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $salesRows = $this->loadSalesRows($filters, $context, $linkedSaleIds);

        $rows = collect();

        if ($filters['source'] === 'statements') {
            $rows = $statementRows;
        } elseif ($filters['source'] === 'sales') {
            $rows = $salesRows;
        } else {
            $rows = $statementRows->concat($salesRows)->values();
        }

        $rows = $this->applyFilters($rows, $filters);
        $rows = $this->sortRows($rows);

        $kpis       = $this->buildKpis($rows);
        $charts     = $this->buildCharts($rows, $filters);
        $highlights = $this->buildHighlights($rows);

        return [
            'filters'    => $filters + [
                'vendor_list' => $context['vendor_list'],
                'source_list' => [
                    ['id' => 'all', 'name' => 'Todos'],
                    ['id' => 'sales', 'name' => 'Ventas'],
                    ['id' => 'statements', 'name' => 'Estados de cuenta'],
                ],
            ],
            'kpis'       => $kpis,
            'charts'     => $charts,
            'highlights' => $highlights,
            'rows'       => $rows,
            'meta'       => [
                'counts' => [
                    'statements'  => $statementRows->count(),
                    'sales'       => $salesRows->count(),
                    'projections' => 0,
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

        $source = strtolower(trim((string) ($request->input('source') ?: 'all')));
        if (!in_array($source, ['all', 'sales', 'statements'], true)) {
            $source = 'all';
        }

        $origin = strtolower(trim((string) ($request->input('origin') ?: 'all')));
        if ($origin === 'no_recurrente') {
            $origin = 'unico';
        }
        if (!in_array($origin, ['all', 'recurrente', 'unico'], true)) {
            $origin = 'all';
        }

        $status = strtolower(trim((string) ($request->input('status') ?: 'all')));
        if (!in_array($status, ['all', 'pending', 'emitido', 'pagado', 'vencido', 'parcial', 'sin_mov'], true)) {
            $status = 'all';
        }

        $invoiceStatus = strtolower(trim((string) ($request->input('invoice_status') ?: 'all')));
        $invoiceStatus = $this->normalizeInvoiceStatus($invoiceStatus, true);

        $vendorId = trim((string) ($request->input('vendor_id') ?: 'all'));
        if ($vendorId === '') {
            $vendorId = 'all';
        }

        $q = trim((string) ($request->input('q') ?: ''));

        $periods = [];
        for ($m = 1; $m <= 12; $m++) {
            $periods[] = sprintf('%04d-%02d', $year, $m);
        }

        if ($month !== 'all') {
            $periods = [sprintf('%04d-%s', $year, $month)];
        }

        return [
            'year'           => $year,
            'month'          => $month,
            'source'         => $source,
            'origin'         => $origin,
            'status'         => $status,
            'invoice_status' => $invoiceStatus,
            'vendor_id'      => $vendorId,
            'q'              => $q,
            'periods'        => $periods,
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

        $accounts = collect();
        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $select = ['id', 'email'];
            foreach ([
                'name', 'razon_social', 'rfc', 'plan', 'plan_actual', 'modo_cobro',
                'billing_cycle', 'is_blocked', 'estado_cuenta', 'meta', 'created_at',
                'billing_amount_mxn', 'amount_mxn', 'precio_mxn', 'monto_mxn',
                'override_amount_mxn', 'custom_amount_mxn', 'license_amount_mxn',
                'billing_amount', 'amount', 'precio', 'monto',
            ] as $col) {
                if ($this->hasCol($this->adm, 'accounts', $col)) {
                    $select[] = $col;
                }
            }

            $accounts = collect(
                DB::connection($this->adm)
                    ->table('accounts')
                    ->select($select)
                    ->get()
            )->keyBy(fn ($r) => (string) $r->id);
        }

        $cuentasClienteByAdmin = collect();
        if (Schema::connection($this->cli)->hasTable('cuentas_cliente')) {
            $select = ['id', 'admin_account_id', 'rfc', 'rfc_padre', 'razon_social', 'nombre_comercial', 'modo_cobro', 'plan_actual'];
            if ($this->hasCol($this->cli, 'cuentas_cliente', 'empresa')) {
                $select[] = 'empresa';
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

        $paymentsAgg = $this->loadPaymentsAgg($filters['periods']);
        $paymentsMeta = $this->loadPaymentsMeta($filters['periods']);
        $overrides = $this->loadStatementOverrides($filters['periods']);
        $subsRenewMap = $this->loadSubscriptionsRenewMap();

        return [
            'vendor_by_id'              => $vendorById,
            'vendor_list'               => $vendors->map(fn ($v) => ['id' => (string) $v->id, 'name' => (string) $v->name])->values()->all(),
            'accounts'                  => $accounts,
            'cuentas_cliente_by_admin'  => $cuentasClienteByAdmin,
            'account_vendor_map'        => $accountVendorMap,
            'payments_agg'              => $paymentsAgg,
            'payments_meta'             => $paymentsMeta,
            'statement_overrides'       => $overrides,
            'subs_renew_map'            => $subsRenewMap,
        ];
    }

    private function loadStatementRows(array $filters, array $context): Collection
    {
        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            return collect();
        }

        $periods = $filters['periods'];

        $edoAgg = collect();
        if (Schema::connection($this->adm)->hasTable('estados_cuenta')) {
            $select = [
                'account_id',
                'periodo',
                DB::raw('SUM(COALESCE(cargo,0)) as cargo'),
                DB::raw('SUM(COALESCE(abono,0)) as abono'),
                DB::raw('MAX(updated_at) as updated_at'),
                DB::raw('MAX(created_at) as created_at'),
            ];

            $edoAgg = collect(
                DB::connection($this->adm)
                    ->table('estados_cuenta')
                    ->select($select)
                    ->whereIn('periodo', $periods)
                    ->groupBy('account_id', 'periodo')
                    ->get()
            )->keyBy(fn ($r) => (string) $r->account_id . '|' . (string) $r->periodo);
        }

        $edoItems = collect();
        if (Schema::connection($this->adm)->hasTable('estados_cuenta')) {
            $itemSelect = ['id', 'account_id', 'periodo', 'concepto', 'detalle', 'cargo', 'abono'];
            foreach (['source', 'ref', 'meta', 'updated_at', 'created_at'] as $col) {
                if ($this->hasCol($this->adm, 'estados_cuenta', $col)) {
                    $itemSelect[] = $col;
                }
            }

            $edoItems = collect(
                DB::connection($this->adm)
                    ->table('estados_cuenta')
                    ->select($itemSelect)
                    ->whereIn('periodo', $periods)
                    ->orderBy('periodo')
                    ->orderBy('id')
                    ->get()
            )->groupBy(fn ($r) => (string) $r->account_id . '|' . (string) $r->periodo);
        }

        $rows = collect();

        foreach ($context['accounts'] as $accountId => $acc) {
            $meta = $this->decodeMeta($acc->meta ?? null);
            $lastPaid = $this->resolveLastPaidPeriodForAccount((string) $accountId, $meta);

            foreach ($periods as $period) {
                $key      = (string) $accountId . '|' . $period;
                $agg      = $edoAgg->get($key);
                $items    = collect($edoItems->get($key, collect()));
                $payAgg   = $context['payments_agg']->get($key);
                $payMeta  = $context['payments_meta']->get($key, []);
                $override = $context['statement_overrides']->get($key);

                $cargoEdo = round((float) ($agg->cargo ?? 0), 2);
                $abonoEdo = round((float) ($agg->abono ?? 0), 2);
                $abonoPay = round((float) ($payAgg->amount ?? 0), 2);
                $abonoTot = round($abonoEdo + $abonoPay, 2);

                $expectedTotal = $this->resolveExpectedAmountForStatement(
                    (object) $acc,
                    $meta,
                    $period,
                    $lastPaid,
                    $context['subs_renew_map']
                );

                $hasEvidence = $cargoEdo > 0.00001
                    || $abonoEdo > 0.00001
                    || $abonoPay > 0.00001
                    || $items->isNotEmpty()
                    || !empty($override)
                    || $expectedTotal > 0.00001;

                if (!$hasEvidence) {
                    continue;
                }

                $totalShown = $cargoEdo > 0.00001 ? $cargoEdo : $expectedTotal;
                $saldoShown = round(max(0.0, $totalShown - $abonoTot), 2);

                $statusPago = $this->computeStatementStatus(
                    $period,
                    $totalShown,
                    $abonoTot,
                    $saldoShown,
                    $payMeta['due_date'] ?? null
                );

                $display = $this->applyStatementOverrideDisplay(
                    totalShown: $totalShown,
                    abonoEdo: $abonoEdo,
                    abonoPay: $abonoPay,
                    saldoShown: $saldoShown,
                    statusPago: $statusPago,
                    override: is_array($override) ? $override : null
                );

                $company = $this->resolveCompanyName($context['cuentas_cliente_by_admin']->get((string) $accountId), $acc);
                $rfcEmisor = $this->resolveRfcEmisor($context['cuentas_cliente_by_admin']->get((string) $accountId), $acc);
                [$vendorId, $vendorName] = $this->resolveVendorForAccountPeriod((string) $accountId, $period, $context['account_vendor_map']);

                $description = $this->buildStatementDescriptionFromItems($items, $expectedTotal, $cargoEdo);
                $origin = $this->resolveStatementOrigin($items, $acc, $meta);
                $periodicity = $this->resolveStatementPeriodicity($acc, $meta);

                $invoiceStatus = $this->resolveStatementInvoiceStatusFromItemsOrOverride($items, $override);
                $cfdiUuid = $this->resolveStatementCfdiFromItemsOrOverride($items, $override);
                $linkedSaleIds = $this->extractLinkedSaleIdsFromItems($items);

                $rows->push((object) [
                    'source'                  => 'statement',
                    'source_label'            => 'Estado de cuenta',
                    'source_priority'         => 10,
                    'is_projection'           => 0,
                    'exclude_from_kpi'        => 0,
                    'has_statement'           => 1,

                    'period'                  => $period,
                    'year'                    => $this->periodYear($period),
                    'month_num'               => $this->periodMonth($period),
                    'month_name'              => $this->monthNameEsFromPeriod($period),

                    'account_id'              => (string) $accountId,
                    'account_id_raw'          => (string) $accountId,
                    'client_account_id'       => $context['cuentas_cliente_by_admin']->get((string) $accountId)?->id ?? null,

                    'client'                  => $company,
                    'company'                 => $company,
                    'description'             => $description,

                    'vendor_id'               => $vendorId,
                    'vendor'                  => $vendorName,

                    'origin'                  => $origin,
                    'periodicity'             => $periodicity,

                    'subtotal'                => round($display['total'] / 1.16, 2),
                    'iva'                     => round($display['total'] - round($display['total'] / 1.16, 2), 2),
                    'total'                   => round($display['total'], 2),

                    'ec_status'               => (string) $display['status'],
                    'invoice_status'          => $invoiceStatus,
                    'invoice_status_raw'      => $invoiceStatus,

                    'rfc_emisor'              => $rfcEmisor,
                    'rfc_receptor'            => null,
                    'forma_pago'              => $override['pay_method'] ?? ($payMeta['method'] ?? null),
                    'invoice_metodo_pago'     => null,

                    'f_cta'                   => $agg->created_at ?? null,
                    'f_mov'                   => $agg->updated_at ?? null,
                    'f_factura'               => null,
                    'f_pago'                  => $override['paid_at'] ?? ($payMeta['last_paid_at'] ?? null),

                    'sent_at'                 => null,
                    'paid_at'                 => $override['paid_at'] ?? ($payMeta['last_paid_at'] ?? null),
                    'due_date'                => $payMeta['due_date'] ?? null,

                    'payment_method'          => $override['pay_method'] ?? ($payMeta['method'] ?? null),
                    'payment_status'          => $override['pay_status'] ?? ($payMeta['status'] ?? null),

                    'cfdi_uuid'               => $cfdiUuid,
                    'statement_id'            => null,
                    'sale_id'                 => 0,
                    'include_in_statement'    => null,
                    'statement_period_target' => null,

                    'notes'                   => $this->extractStatementNotes($items, $override),
                    'audit_key'               => 'statement|' . $accountId . '|' . $period,
                    'linked_sale_ids'         => $linkedSaleIds,
                ]);
            }
        }

        return $rows->values();
    }

    private function loadSalesRows(array $filters, array $context, array $linkedSaleIds): Collection
    {
        if (!Schema::connection($this->adm)->hasTable('finance_sales')) {
            return collect();
        }

        $optionalSalesCols = [
            'sale_code',
            'account_id',
            'period',
            'vendor_id',
            'origin',
            'periodicity',
            'f_cta',
            'f_mov',
            'invoice_date',
            'paid_date',
            'sale_date',
            'receiver_rfc',
            'pay_method',
            'subtotal',
            'iva',
            'total',
            'statement_status',
            'invoice_status',
            'invoice_uuid',
            'cfdi_uuid',
            'include_in_statement',
            'target_period',
            'statement_period_target',
            'statement_id',
            'statement_item_id',
            'notes',
            'meta',
            'created_at',
            'updated_at',
        ];

        $salesSelect = ['s.id'];

        foreach ($optionalSalesCols as $col) {
            if ($this->hasCol($this->adm, 'finance_sales', $col)) {
                $salesSelect[] = "s.{$col}";
            } else {
                $salesSelect[] = DB::raw("NULL as {$col}");
            }
        }

        $vendorSelect = $this->hasCol($this->adm, 'finance_vendors', 'name')
            ? ['v.name as vendor_name']
            : [DB::raw('NULL as vendor_name')];

        $accountSelect = [];
        $accountSelect[] = $this->hasCol($this->adm, 'accounts', 'name')
            ? 'a.name as account_name'
            : DB::raw('NULL as account_name');

        $accountSelect[] = $this->hasCol($this->adm, 'accounts', 'razon_social')
            ? 'a.razon_social as account_razon_social'
            : DB::raw('NULL as account_razon_social');

        $accountSelect[] = $this->hasCol($this->adm, 'accounts', 'rfc')
            ? 'a.rfc as account_rfc'
            : DB::raw('NULL as account_rfc');

        $q = DB::connection($this->adm)
            ->table('finance_sales as s')
            ->leftJoin('finance_vendors as v', 'v.id', '=', 's.vendor_id')
            ->leftJoin('accounts as a', 'a.id', '=', 's.account_id')
            ->select(array_merge($salesSelect, $vendorSelect, $accountSelect));

        if ($this->hasCol($this->adm, 'finance_sales', 'period')) {
            $q->whereIn('s.period', $filters['periods']);
        } else {
            return collect();
        }

        if ($this->hasCol($this->adm, 'finance_sales', 'deleted_at')) {
            $q->whereNull('s.deleted_at');
        }

        if ($filters['source'] === 'all' && !empty($linkedSaleIds)) {
            $q->whereNotIn('s.id', $linkedSaleIds);
        }

        $sales = collect(
            $q->orderBy('s.period')
                ->orderBy('s.id')
                ->get()
        );

        return $sales->map(function ($sale) use ($context) {
            $period       = (string) ($sale->period ?? '');
            $accountId    = (string) ($sale->account_id ?? '');
            $accountAdmin = $context['accounts']->get($accountId);
            $clientObj    = $context['cuentas_cliente_by_admin']->get($accountId);

            $company   = $this->resolveCompanyName($clientObj, $accountAdmin);
            $rfcEmisor = $this->resolveRfcEmisor($clientObj, $accountAdmin);

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

            $invoiceStatus = $this->normalizeInvoiceStatus((string) ($sale->invoice_status ?? ''), false);
            $ecStatus      = $this->normalizeSaleStatus((string) ($sale->statement_status ?? 'pending'));

            $description = trim((string) ($sale->notes ?? ''));
            if ($description === '') {
                $description = trim((string) ($sale->sale_code ?? ''));
            }
            if ($description === '') {
                $description = $origin === 'recurrente' ? 'Venta recurrente' : 'Venta única';
            }

            $cfdiUuid = trim((string) ($sale->cfdi_uuid ?? ''));
            if ($cfdiUuid === '') {
                $cfdiUuid = trim((string) ($sale->invoice_uuid ?? ''));
            }

            $includeInStatement = (int) ($sale->include_in_statement ?? 0);
            $statementId = !empty($sale->statement_id) ? (int) $sale->statement_id : null;

            $statementPeriodTarget = '';
            if (!empty($sale->statement_period_target)) {
                $statementPeriodTarget = (string) $sale->statement_period_target;
            } elseif (!empty($sale->target_period)) {
                $statementPeriodTarget = (string) $sale->target_period;
            }

            return (object) [
                'source'                  => ($includeInStatement === 1) ? 'sale_linked' : 'sale',
                'source_label'            => 'Venta',
                'source_priority'         => ($includeInStatement === 1) ? 30 : 20,
                'is_projection'           => 0,
                'exclude_from_kpi'        => 0,
                'has_statement'           => ($includeInStatement === 1 && !empty($statementId)) ? 1 : 0,

                'period'                  => $period,
                'year'                    => $this->periodYear($period),
                'month_num'               => $this->periodMonth($period),
                'month_name'              => $this->monthNameEsFromPeriod($period),

                'account_id'              => $accountId,
                'account_id_raw'          => $accountId,
                'client_account_id'       => $clientObj?->id ? (string) $clientObj->id : null,

                'client'                  => $company,
                'company'                 => $company,
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

                'cfdi_uuid'               => $cfdiUuid,
                'statement_id'            => $statementId,
                'sale_id'                 => (int) ($sale->id ?? 0),
                'include_in_statement'    => $includeInStatement,
                'statement_period_target' => $statementPeriodTarget,

                'notes'                   => (string) ($sale->notes ?? ''),
                'audit_key'               => 'sale|' . (int) ($sale->id ?? 0),
                'linked_sale_ids'         => [],
            ];
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
                    (string) ($row->source_label ?? ''),
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
        $realRows = $rows->values();

        $pending  = $realRows->where('ec_status', 'pending');
        $emitido  = $realRows->where('ec_status', 'emitido');
        $parcial  = $realRows->where('ec_status', 'parcial');
        $pagado   = $realRows->where('ec_status', 'pagado');
        $vencido  = $realRows->where('ec_status', 'vencido');

        $realTotal  = round((float) $realRows->sum('total'), 2);
        $collected  = round((float) $pagado->sum('total'), 2);
        $receivable = round((float) ($pending->sum('total') + $emitido->sum('total') + $parcial->sum('total') + $vencido->sum('total')), 2);

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
                'count'  => 0,
                'amount' => 0.0,
            ],
            'receivable' => [
                'count'  => $pending->count() + $emitido->count() + $parcial->count() + $vencido->count(),
                'amount' => $receivable,
            ],
            'goal' => [
                'amount'   => $realTotal,
                'progress' => $realTotal > 0 ? 100.0 : 0.0,
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
        $monthly = [];
        foreach ($filters['periods'] as $period) {
            $slice = $rows->where('period', $period);

            $real = round((float) $slice->sum('total'), 2);
            $collected = round((float) $slice->where('ec_status', 'pagado')->sum('total'), 2);

            $monthly[] = [
                'period'     => $period,
                'label'      => $this->monthNameEsFromPeriod($period),
                'real'       => $real,
                'projected'  => 0.0,
                'collected'  => $collected,
            ];
        }

        $originMix = [
            ['label' => 'Recurrente', 'value' => round((float) $rows->where('origin', 'recurrente')->sum('total'), 2)],
            ['label' => 'Único',      'value' => round((float) $rows->where('origin', 'unico')->sum('total'), 2)],
        ];

        $vendorTop = $rows
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

    private function buildHighlights(Collection $rows): array
    {
        $bestMonth = $rows
            ->groupBy('period')
            ->map(fn ($group, $period) => [
                'period' => (string) $period,
                'label'  => $this->monthNameEsFromPeriod((string) $period),
                'total'  => round((float) $group->sum('total'), 2),
            ])
            ->sortByDesc('total')
            ->first();

        $topVendor = $rows
            ->groupBy(fn ($r) => (string) ($r->vendor ?: 'Sin vendedor'))
            ->map(fn ($group, $vendor) => [
                'vendor' => $vendor,
                'total'  => round((float) $group->sum('total'), 2),
            ])
            ->sortByDesc('total')
            ->first();

        $criticalPending = $rows
            ->filter(fn ($r) => in_array((string) ($r->ec_status ?? ''), ['pending', 'vencido', 'parcial'], true))
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

    private function loadPaymentsAgg(array $periods): Collection
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return collect();
        }

        $q = DB::connection($this->adm)->table('payments');
        if ($this->hasCol($this->adm, 'payments', 'period')) {
            $q->whereIn('period', $periods);
        }

        if ($this->hasCol($this->adm, 'payments', 'status')) {
            $q->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized']);
        }

        $amountExpr = $this->hasCol($this->adm, 'payments', 'amount_mxn')
            ? 'SUM(COALESCE(amount_mxn,0))'
            : 'SUM(COALESCE(amount,0))/100';

        return collect(
            $q->selectRaw('account_id, period, ' . $amountExpr . ' as amount')
                ->groupBy('account_id', 'period')
                ->get()
        )->keyBy(fn ($r) => (string) $r->account_id . '|' . (string) $r->period);
    }

    private function loadPaymentsMeta(array $periods): Collection
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return collect();
        }

        $select = ['account_id', 'period'];
        foreach (['status', 'method', 'provider', 'due_date', 'paid_at', 'created_at', 'updated_at'] as $col) {
            if ($this->hasCol($this->adm, 'payments', $col)) {
                $select[] = $col;
            }
        }

        $q = DB::connection($this->adm)->table('payments')->select($select);
        if ($this->hasCol($this->adm, 'payments', 'period')) {
            $q->whereIn('period', $periods);
        }

        $orderCol = $this->hasCol($this->adm, 'payments', 'paid_at')
            ? 'paid_at'
            : ($this->hasCol($this->adm, 'payments', 'updated_at')
                ? 'updated_at'
                : ($this->hasCol($this->adm, 'payments', 'created_at') ? 'created_at' : 'period'));

        $rows = collect($q->orderByDesc($orderCol)->get());

        $out = [];
        foreach ($rows as $r) {
            $key = (string) ($r->account_id ?? '') . '|' . (string) ($r->period ?? '');
            if ($key === '|' || isset($out[$key])) {
                continue;
            }

            $out[$key] = [
                'status'       => isset($r->status) ? (string) $r->status : null,
                'method'       => isset($r->method) ? (string) $r->method : null,
                'provider'     => isset($r->provider) ? (string) $r->provider : null,
                'due_date'     => $r->due_date ?? null,
                'last_paid_at' => $r->paid_at ?? ($r->updated_at ?? ($r->created_at ?? null)),
            ];
        }

        return collect($out);
    }

    private function loadStatementOverrides(array $periods): Collection
    {
        $table = 'billing_statement_status_overrides';
        if (!Schema::connection($this->adm)->hasTable($table)) {
            return collect();
        }

        $select = ['account_id', 'period', 'status_override', 'reason', 'updated_by', 'updated_at'];
        foreach (['pay_method', 'pay_provider', 'pay_status', 'status', 'paid_at', 'meta'] as $col) {
            if ($this->hasCol($this->adm, $table, $col)) {
                $select[] = $col;
            }
        }

        $rows = collect(
            DB::connection($this->adm)
                ->table($table)
                ->select($select)
                ->whereIn('period', $periods)
                ->get()
        );

        return $rows->mapWithKeys(function ($r) {
            $meta = $this->decodeMeta($r->meta ?? null);

            $payMethod = trim((string) ($r->pay_method ?? ''));
            if ($payMethod === '') {
                $payMethod = (string) data_get($meta, 'pay_method', '');
            }

            $payProvider = trim((string) ($r->pay_provider ?? ''));
            if ($payProvider === '') {
                $payProvider = (string) data_get($meta, 'pay_provider', '');
            }

            $payStatus = trim((string) ($r->pay_status ?? ($r->status ?? '')));
            if ($payStatus === '') {
                $payStatus = (string) data_get($meta, 'pay_status', '');
            }

            $paidAt = $r->paid_at ?? data_get($meta, 'paid_at');

            $key = (string) $r->account_id . '|' . (string) $r->period;

            return [$key => [
                'status'       => (string) ($r->status_override ?? ''),
                'reason'       => isset($r->reason) ? (string) $r->reason : null,
                'updated_by'   => isset($r->updated_by) ? (int) $r->updated_by : null,
                'updated_at'   => isset($r->updated_at) ? (string) $r->updated_at : null,
                'pay_method'   => $payMethod !== '' ? $payMethod : null,
                'pay_provider' => $payProvider !== '' ? $payProvider : null,
                'pay_status'   => $payStatus !== '' ? $payStatus : null,
                'paid_at'      => $paidAt,
            ]];
        });
    }

    private function loadSubscriptionsRenewMap(): array
    {
        if (!Schema::connection($this->adm)->hasTable('subscriptions')) {
            return [];
        }

        $subMax = DB::connection($this->adm)->table('subscriptions')
            ->selectRaw('account_id, MAX(id) as max_id')
            ->groupBy('account_id');

        $rows = DB::connection($this->adm)->table('subscriptions as s')
            ->joinSub($subMax, 'mx', function ($j) {
                $j->on('mx.max_id', '=', 's.id');
            })
            ->select(['s.account_id', 's.started_at', 's.current_period_end'])
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $date = $r->current_period_end ?: $r->started_at;
            if (!$date) {
                continue;
            }

            try {
                $map[(string) $r->account_id] = Carbon::parse((string) $date)->format('Y-m');
            } catch (\Throwable $e) {
                // noop
            }
        }

        return $map;
    }

    private function resolveExpectedAmountForStatement(
        object $acc,
        array $meta,
        string $period,
        ?string $lastPaid,
        array $subsRenewMap
    ): float {
        $modo = strtolower(trim((string) ($acc->modo_cobro ?? data_get($meta, 'billing.mode') ?? 'mensual')));
        $isAnnual = in_array($modo, ['anual', 'annual', 'year', 'yearly', '12m', '12'], true);

        if ($isAnnual) {
            $duePeriod = $subsRenewMap[(string) $acc->id] ?? null;

            if (!$duePeriod) {
                try {
                    if (!empty($acc->created_at)) {
                        $duePeriod = Carbon::parse((string) $acc->created_at)->format('Y-m');
                    }
                } catch (\Throwable $e) {
                    $duePeriod = null;
                }
            }

            if ($duePeriod !== $period) {
                return 0.0;
            }
        }

        $payAllowed = $lastPaid
            ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
            : $period;

        try {
            if ($this->hub && method_exists($this->hub, 'resolveEffectiveAmountForPeriodFromMeta')) {
                $res = $this->hub->resolveEffectiveAmountForPeriodFromMeta($meta, $period, $payAllowed);

                if (is_array($res) && isset($res[0]) && is_numeric($res[0])) {
                    return round((float) $res[0], 2);
                }

                if (is_numeric($res)) {
                    return round((float) $res, 2);
                }
            }
        } catch (\Throwable $e) {
            // fallback abajo
        }

        foreach ([
            data_get($meta, 'billing.override.amount_mxn'),
            data_get($meta, 'billing.custom.amount_mxn'),
            data_get($meta, 'license.override.amount_mxn'),
            data_get($meta, 'pricing.override.amount_mxn'),
            $acc->override_amount_mxn ?? null,
            $acc->custom_amount_mxn ?? null,
            $acc->billing_amount_mxn ?? null,
            $acc->amount_mxn ?? null,
            $acc->precio_mxn ?? null,
            $acc->monto_mxn ?? null,
            $acc->license_amount_mxn ?? null,
            $acc->billing_amount ?? null,
            $acc->amount ?? null,
            $acc->precio ?? null,
            $acc->monto ?? null,
        ] as $v) {
            $n = $this->toFloat($v);
            if ($n !== null && $n > 0.00001) {
                return round($n, 2);
            }
        }

        return 0.0;
    }

    private function resolveLastPaidPeriodForAccount(string $accountId, array $meta): ?string
    {
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
                return $p;
            }
        }

        if (Schema::connection($this->adm)->hasTable('payments') && $this->hasCol($this->adm, 'payments', 'period')) {
            try {
                $q = DB::connection($this->adm)->table('payments')
                    ->where('account_id', $accountId);

                if ($this->hasCol($this->adm, 'payments', 'status')) {
                    $q->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized']);
                }

                $row = $q->orderByDesc(
                    $this->hasCol($this->adm, 'payments', 'paid_at')
                        ? 'paid_at'
                        : ($this->hasCol($this->adm, 'payments', 'created_at') ? 'created_at' : 'period')
                )->first(['period']);

                if ($row && !empty($row->period)) {
                    $p = $this->parseToPeriod($row->period);
                    if ($p) {
                        return $p;
                    }
                }
            } catch (\Throwable $e) {
                // noop
            }
        }

        $table = 'billing_statement_status_overrides';
        if (Schema::connection($this->adm)->hasTable($table)) {
            try {
                $row = DB::connection($this->adm)->table($table)
                    ->where('account_id', $accountId)
                    ->where('status_override', 'pagado')
                    ->orderByDesc('period')
                    ->first(['period']);

                if ($row && !empty($row->period)) {
                    $p = $this->parseToPeriod($row->period);
                    if ($p) {
                        return $p;
                    }
                }
            } catch (\Throwable $e) {
                // noop
            }
        }

        return null;
    }

    private function computeStatementStatus(
        string $period,
        float $totalShown,
        float $abonoTotal,
        float $saldoShown,
        mixed $dueDate
    ): string {
        if ($totalShown <= 0.00001) {
            return 'sin_mov';
        }

        if ($saldoShown <= 0.00001) {
            return 'pagado';
        }

        if ($abonoTotal > 0.00001 && $abonoTotal < ($totalShown - 0.00001)) {
            return 'parcial';
        }

        if ($this->isOverdue($period, $dueDate)) {
            return 'vencido';
        }

        return 'pending';
    }

    private function applyStatementOverrideDisplay(
        float $totalShown,
        float $abonoEdo,
        float $abonoPay,
        float $saldoShown,
        string $statusPago,
        ?array $override
    ): array {
        $abono = round($abonoEdo + $abonoPay, 2);
        $saldo = round($saldoShown, 2);
        $status = $statusPago;

        if (!$override || empty($override['status'])) {
            return [
                'total'  => round($totalShown, 2),
                'abono'  => $abono,
                'saldo'  => $saldo,
                'status' => $status,
            ];
        }

        $s = strtolower(trim((string) $override['status']));
        if (!in_array($s, ['pendiente', 'parcial', 'pagado', 'vencido', 'sin_mov'], true)) {
            return [
                'total'  => round($totalShown, 2),
                'abono'  => $abono,
                'saldo'  => $saldo,
                'status' => $status,
            ];
        }

        $status = $s === 'pendiente' ? 'pending' : $s;

        if ($s === 'pagado') {
            return [
                'total'  => round($totalShown, 2),
                'abono'  => round($totalShown, 2),
                'saldo'  => 0.0,
                'status' => 'pagado',
            ];
        }

        return [
            'total'  => round($totalShown, 2),
            'abono'  => round($abonoEdo, 2),
            'saldo'  => round(max(0.0, $totalShown - $abonoEdo), 2),
            'status' => $status,
        ];
    }

    private function resolveCompanyName(?object $clientObj, ?object $accountAdmin): string
    {
        foreach ([
            $clientObj?->nombre_comercial ?? null,
            $clientObj?->razon_social ?? null,
            $clientObj?->empresa ?? null,
            $accountAdmin?->razon_social ?? null,
            $accountAdmin?->name ?? null,
        ] as $v) {
            $v = trim((string) ($v ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '—';
    }

    private function resolveRfcEmisor(?object $clientObj, ?object $accountAdmin): string
    {
        foreach ([
            $clientObj?->rfc_padre ?? null,
            $clientObj?->rfc ?? null,
            $accountAdmin?->rfc ?? null,
        ] as $v) {
            $v = strtoupper(trim((string) ($v ?? '')));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    private function resolveVendorForAccountPeriod(string $accountId, string $period, Collection $accountVendorMap): array
    {
        if (!$accountVendorMap->has($accountId)) {
            return [null, null];
        }

        foreach ($accountVendorMap->get($accountId) as $assign) {
            if ($this->assignmentMatchesPeriod($assign, $period)) {
                return [
                    !empty($assign->vendor_id) ? (string) $assign->vendor_id : null,
                    !empty($assign->vendor_name) ? (string) $assign->vendor_name : null,
                ];
            }
        }

        return [null, null];
    }

    private function buildStatementDescriptionFromItems(Collection $items, float $expectedTotal, float $cargoEdo): string
    {
        $parts = $items
            ->map(function ($item) {
                $c = trim((string) ($item->concepto ?? ''));
                if ($c === '') {
                    $c = trim((string) ($item->detalle ?? ''));
                }
                return $c;
            })
            ->filter()
            ->unique()
            ->values()
            ->take(3)
            ->all();

        if (!empty($parts)) {
            return implode(' · ', $parts);
        }

        if ($cargoEdo <= 0.00001 && $expectedTotal > 0.00001) {
            return 'Estado de cuenta calculado por tarifa';
        }

        return 'Estado de cuenta';
    }

    private function resolveStatementOrigin(Collection $items, object $acc, array $meta): string
    {
        $mode = strtolower(trim((string) ($acc->modo_cobro ?? data_get($meta, 'billing.mode') ?? '')));
        if (in_array($mode, ['mensual', 'anual'], true)) {
            return 'recurrente';
        }

        foreach ($items as $item) {
            $concepto = strtolower(trim((string) ($item->concepto ?? '')));
            if (str_contains($concepto, 'servicio mensual') || str_contains($concepto, 'servicio anual') || str_contains($concepto, 'licencia')) {
                return 'recurrente';
            }
        }

        return 'unico';
    }

    private function resolveStatementPeriodicity(object $acc, array $meta): string
    {
        $mode = strtolower(trim((string) ($acc->modo_cobro ?? data_get($meta, 'billing.mode') ?? '')));
        if (in_array($mode, ['mensual', 'anual'], true)) {
            return $mode;
        }

        return 'unico';
    }

    private function resolveStatementInvoiceStatusFromItemsOrOverride(Collection $items, ?array $override): string
    {
        if (!empty($override['pay_status']) && strtolower((string) $override['pay_status']) === 'paid') {
            return 'issued';
        }

        foreach ($items as $item) {
            $meta = $this->decodeMeta($item->meta ?? null);
            $cfdi = trim((string) (data_get($meta, 'cfdi_uuid') ?? data_get($meta, 'invoice_uuid') ?? ''));
            if ($cfdi !== '') {
                return 'issued';
            }
        }

        return 'sin_solicitud';
    }

    private function resolveStatementCfdiFromItemsOrOverride(Collection $items, ?array $override): string
    {
        foreach ($items as $item) {
            $meta = $this->decodeMeta($item->meta ?? null);
            $cfdi = trim((string) (data_get($meta, 'cfdi_uuid') ?? data_get($meta, 'invoice_uuid') ?? ''));
            if ($cfdi !== '') {
                return $cfdi;
            }
        }

        return '';
    }

    private function extractLinkedSaleIdsFromItems(Collection $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $source = strtolower(trim((string) ($item->source ?? '')));
            $ref = trim((string) ($item->ref ?? ''));

            if ($source === 'finance_sale' && preg_match('/finance_sale\:(\d+)/', $ref, $m)) {
                $ids[] = (int) $m[1];
                continue;
            }

            $meta = $this->decodeMeta($item->meta ?? null);
            $saleId = (int) (data_get($meta, 'finance_sale_id') ?? 0);
            if ($saleId > 0) {
                $ids[] = $saleId;
            }
        }

        $ids = array_values(array_unique(array_filter($ids, fn ($v) => (int) $v > 0)));
        sort($ids);

        return $ids;
    }

    private function extractStatementNotes(Collection $items, ?array $override): string
    {
        if (!empty($override['reason'])) {
            return (string) $override['reason'];
        }

        $notes = $items
            ->map(function ($item) {
                $d = trim((string) ($item->detalle ?? ''));
                return $d;
            })
            ->filter()
            ->unique()
            ->take(2)
            ->implode(' · ');

        return (string) $notes;
    }

    private function normalizeSaleStatus(string $statusRaw): string
    {
        $status = strtolower(trim($statusRaw));

        return match ($status) {
            'emitido', 'sent'  => 'emitido',
            'pagado', 'paid'   => 'pagado',
            'vencido'          => 'vencido',
            'parcial'          => 'parcial',
            default            => 'pending',
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
            'cancelled', 'canceled', 'rechazada', 'rechazado', 'cancelada', 'cancelado' => 'cancelled',
            default                                                          => $allowAll ? 'all' : 'sin_solicitud',
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

    private function decodeMeta(mixed $value): array
    {
        if ($this->hub && method_exists($this->hub, 'decodeMeta')) {
            try {
                $decoded = $this->hub->decodeMeta($value);
                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable $e) {
                // fallback abajo
            }
        }

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

    private function toFloat(mixed $v): ?float
    {
        if ($v === null) {
            return null;
        }

        if (is_float($v) || is_int($v)) {
            return (float) $v;
        }

        if (is_string($v)) {
            $s = trim($v);
            if ($s === '') {
                return null;
            }

            $s = str_replace(['$', ',', ' '], '', $s);
            if (!is_numeric($s)) {
                return null;
            }

            return (float) $s;
        }

        if (is_numeric($v)) {
            return (float) $v;
        }

        return null;
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
                if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v)) {
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

    private function isOverdue(string $period, mixed $dueDate): bool
    {
        $now = now();

        if (!preg_match('/^\d{4}\-\d{2}$/', $period)) {
            return false;
        }

        $currentPeriod = $now->format('Y-m');
        if ($period >= $currentPeriod) {
            return false;
        }

        try {
            if ($dueDate !== null && $dueDate !== '') {
                $due = $dueDate instanceof Carbon ? $dueDate : Carbon::parse((string) $dueDate);
                return $due->lt($now);
            }
        } catch (\Throwable $e) {
            // fallback abajo
        }

        try {
            $start = Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfMonth();
            $cut   = $start->copy()->endOfMonth()->addDays(4)->endOfDay();
            return $now->gt($cut);
        } catch (\Throwable $e) {
            return false;
        }
    }
}