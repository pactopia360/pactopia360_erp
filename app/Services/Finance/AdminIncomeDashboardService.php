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
        $projectionRows = $this->loadProjectionRows($filters, $context, $statementRows, $salesRows);

        $rows = collect();

        if ($filters['source'] === 'statements') {
            $rows = $statementRows;
        } elseif ($filters['source'] === 'sales') {
            $rows = $salesRows;
        } else {
            $rows = $statementRows
                ->concat($salesRows)
                ->concat($projectionRows)
                ->values();
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
            $select = ['id', 'admin_account_id'];

            foreach ([
                'rfc',
                'rfc_padre',
                'razon_social',
                'nombre_comercial',
                'empresa',
                'modo_cobro',
                'plan_actual',
                'email',
                'codigo_cliente',
            ] as $col) {
                if ($this->hasCol($this->cli, 'cuentas_cliente', $col)) {
                    $select[] = $col;
                }
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
        if (!Schema::connection($this->adm)->hasTable('estados_cuenta')) {
            return collect();
        }

        $periods = $filters['periods'];

        $itemSelect = [
            'id',
            'account_id',
            'periodo',
            'concepto',
            'detalle',
            'cargo',
            'abono',
        ];

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
                ->orderBy('account_id')
                ->orderBy('id')
                ->get()
        )->groupBy(fn ($r) => (string) $r->account_id . '|' . (string) $r->periodo);

        $rows = collect();

        foreach ($edoItems as $key => $itemsGroup) {
            $items = collect($itemsGroup);
            if ($items->isEmpty()) {
                continue;
            }

            [$accountId, $period] = explode('|', (string) $key, 2);

            if ($accountId === '' || $period === '') {
                continue;
            }

            $acc       = $context['accounts']->get((string) $accountId);
            $clientObj = $context['cuentas_cliente_by_admin']->get((string) $accountId);
            $override  = $context['statement_overrides']->get((string) $key);

            $cargoEdo = round((float) $items->sum(fn ($x) => (float) ($x->cargo ?? 0)), 2);
            $abonoEdo = round((float) $items->sum(fn ($x) => (float) ($x->abono ?? 0)), 2);

            $totalShown = $cargoEdo;
            $saldoShown = round(max(0.0, $cargoEdo - $abonoEdo), 2);

            if ($totalShown <= 0.00001) {
                continue;
            }

            $statusPago = $this->computeStatementStatusFromEstadoCuenta(
                totalCargo: $cargoEdo,
                totalAbono: $abonoEdo,
                period: $period
            );

            $display = $this->applyStatementOverrideDisplayFromEstadoCuenta(
                totalShown: $totalShown,
                abonoEdo: $abonoEdo,
                saldoShown: $saldoShown,
                statusPago: $statusPago,
                override: is_array($override) ? $override : null
            );

            $company = $this->resolveCompanyName($clientObj, $acc);
            $rfcEmisor = $this->resolveRfcEmisor($clientObj, $acc);
            [$vendorId, $vendorName] = $this->resolveVendorForAccountPeriod(
                (string) $accountId,
                $period,
                $context['account_vendor_map']
            );

            $description = $this->buildStatementDescriptionFromItems($items, 0.0, $cargoEdo);
            $origin = $this->resolveStatementOrigin($items, $acc ?: (object) [], []);
            $periodicity = $this->resolveStatementPeriodicity($acc ?: (object) [], []);

            $invoiceStatus = $this->resolveStatementInvoiceStatusFromItemsOrOverride($items, $override);
            $cfdiUuid = $this->resolveStatementCfdiFromItemsOrOverride($items, $override);
            $linkedSaleIds = $this->extractLinkedSaleIdsFromItems($items);

            $firstCreated = $items->pluck('created_at')->filter()->sort()->first();
            $lastUpdated  = $items->pluck('updated_at')->filter()->sortDesc()->first();

            $lastPaidAt = null;
            if ($display['status'] === 'pagado') {
                $lastPaidAt = $override['paid_at'] ?? $lastUpdated ?? $firstCreated;
            }

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
                'client_account_id'       => $clientObj?->id ?? null,

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

                'facturado'               => round($display['total'], 2),
                'abono'                   => round($display['abono'], 2),
                'saldo'                   => round($display['saldo'], 2),
                'cobrado_real'            => round($display['abono'], 2),

                'ec_status'               => (string) $display['status'],
                'invoice_status'          => $invoiceStatus,
                'invoice_status_raw'      => $invoiceStatus,

                'rfc_emisor'              => $rfcEmisor,
                'rfc_receptor'            => null,
                'forma_pago'              => $override['pay_method'] ?? null,
                'invoice_metodo_pago'     => null,

                'f_cta'                   => $firstCreated,
                'f_mov'                   => $lastUpdated,
                'f_factura'               => null,
                'f_pago'                  => $lastPaidAt,

                'sent_at'                 => null,
                'paid_at'                 => $lastPaidAt,
                'due_date'                => null,

                'payment_method'          => $override['pay_method'] ?? null,
                'payment_status'          => $override['pay_status'] ?? null,

                'cfdi_uuid'               => $cfdiUuid,
                'statement_id'            => null,
                'sale_id'                 => 0,
                'include_in_statement'    => null,
                'statement_period_target' => null,

                'notes'                   => $this->extractStatementNotes($items, $override),
                'audit_key'               => 'statement|' . $accountId . '|' . $period,
                'linked_sale_ids'         => $linkedSaleIds,

                'expected_total_raw'      => 0.0,
                'cargo_raw'               => round($cargoEdo, 2),
                'abono_ec_raw'            => round($abonoEdo, 2),
                'abono_pay_raw'           => 0.0,
                'tarifa_label_raw'        => null,
            ]);
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
            'statement_sent_at',
            'statement_paid_at',
            'invoice_status',
            'invoice_uuid',
            'cfdi_uuid',
            'payment_id',
            'invoice_request_id',
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

        if ($filters['source'] === 'all') {
            if ($this->hasCol($this->adm, 'finance_sales', 'include_in_statement')) {
                $q->where(function ($w) {
                    $w->whereNull('s.include_in_statement')
                      ->orWhere('s.include_in_statement', 0);
                });
            }

            if (!empty($linkedSaleIds)) {
                $q->whereNotIn('s.id', $linkedSaleIds);
            }
        }

        $sales = collect(
            $q->orderBy('s.period')
                ->orderBy('s.id')
                ->get()
        );

        return $sales->map(function ($sale) use ($context, $filters) {
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

            $facturado = round((float) ($sale->total ?? 0), 2);
            $abono     = $ecStatus === 'pagado' ? $facturado : 0.0;
            $saldo     = round(max(0.0, $facturado - $abono), 2);

            $excludeFromKpi = 0;
            if ($filters['source'] === 'all' && $includeInStatement !== 1) {
                $excludeFromKpi = 1;
            }

            return (object) [
                'source'                  => ($includeInStatement === 1) ? 'sale_linked' : 'sale',
                'source_label'            => 'Venta',
                'source_priority'         => ($includeInStatement === 1) ? 30 : 20,
                'is_projection'           => 0,
                'exclude_from_kpi'        => $excludeFromKpi,
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
                'total'                   => $facturado,

                'facturado'               => $facturado,
                'abono'                   => round($abono, 2),
                'saldo'                   => $saldo,
                'cobrado_real'            => round($abono, 2),

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
                'f_pago'                  => $sale->paid_date ?? ($sale->statement_paid_at ?? null),

                'sent_at'                 => $sale->statement_sent_at ?? null,
                'paid_at'                 => $sale->paid_date ?? ($sale->statement_paid_at ?? null),
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

    private function loadProjectionRows(
        array $filters,
        array $context,
        Collection $statementRows,
        Collection $salesRows
    ): Collection {
        if ($filters['source'] !== 'all') {
            return collect();
        }

        $accounts = $context['accounts'] ?? collect();
        if ($accounts->isEmpty()) {
            return collect();
        }

        $existingStatementKeys = $statementRows
            ->map(fn ($r) => (string) ($r->account_id ?? '') . '|' . (string) ($r->period ?? ''))
            ->filter()
            ->values()
            ->all();

        $existingRecurringSalesKeys = $salesRows
            ->filter(function ($r) {
                $origin = strtolower(trim((string) ($r->origin ?? '')));
                return str_starts_with((string) ($r->source ?? ''), 'sale')
                    && $origin === 'recurrente';
            })
            ->map(fn ($r) => (string) ($r->account_id ?? '') . '|' . (string) ($r->period ?? ''))
            ->filter()
            ->values()
            ->all();

        $blockedKeys = array_fill_keys(array_merge($existingStatementKeys, $existingRecurringSalesKeys), true);

        $rows = collect();

        foreach ($accounts as $acc) {
            $accountId = (string) ($acc->id ?? '');
            if ($accountId === '') {
                continue;
            }

            $meta = $this->decodeMeta($acc->meta ?? null);

            $recurrence = $this->resolveRecurringRuleForProjection($acc, $meta, $context['subs_renew_map'] ?? []);
            if (!$recurrence['is_recurrente']) {
                continue;
            }

            $clientObj = $context['cuentas_cliente_by_admin']->get($accountId);
            $company   = $this->resolveCompanyName($clientObj, $acc);
            $rfcEmisor = $this->resolveRfcEmisor($clientObj, $acc);

            foreach ($filters['periods'] as $period) {
                if (!$this->periodMatchesRecurrenceForProjection($period, $recurrence)) {
                    continue;
                }

                $auditKey = $accountId . '|' . $period;
                if (isset($blockedKeys[$auditKey])) {
                    continue;
                }

                $custom = $this->extractCustomAmountMxnFromAccountMeta($acc, $meta);
                if ($custom !== null && $custom > 0.00001) {
                    $expected = round($custom, 2);
                    $tarifaLabel = 'PERSONALIZADO';
                } else {
                    [$expected, $tarifaLabel] = $this->resolveExpectedAmountForStatement($acc, $meta, $period);
                    $expected = round((float) $expected, 2);
                }

                if ($expected <= 0.00001) {
                    continue;
                }

                [$vendorId, $vendorName] = $this->resolveVendorForAccountPeriod(
                    $accountId,
                    $period,
                    $context['account_vendor_map']
                );

                $description = $recurrence['periodicity'] === 'anual'
                    ? 'Proyección recurrente anual esperada'
                    : 'Proyección recurrente mensual esperada';

                $rows->push((object) [
                    'source'                  => 'projection',
                    'source_label'            => 'Proyección',
                    'source_priority'         => 40,
                    'is_projection'           => 1,
                    'exclude_from_kpi'        => 0,
                    'has_statement'           => 0,

                    'period'                  => $period,
                    'year'                    => $this->periodYear($period),
                    'month_num'               => $this->periodMonth($period),
                    'month_name'              => $this->monthNameEsFromPeriod($period),

                    'account_id'              => $accountId,
                    'account_id_raw'          => $accountId,
                    'client_account_id'       => $clientObj?->id ?? null,

                    'client'                  => $company,
                    'company'                 => $company,
                    'description'             => $description,

                    'vendor_id'               => $vendorId,
                    'vendor'                  => $vendorName,

                    'origin'                  => 'recurrente',
                    'periodicity'             => $recurrence['periodicity'],

                    'subtotal'                => round($expected / 1.16, 2),
                    'iva'                     => round($expected - round($expected / 1.16, 2), 2),
                    'total'                   => $expected,

                    'facturado'               => 0.0,
                    'abono'                   => 0.0,
                    'saldo'                   => $expected,
                    'cobrado_real'            => 0.0,

                    'ec_status'               => 'pending',
                    'invoice_status'          => 'sin_solicitud',
                    'invoice_status_raw'      => 'sin_solicitud',

                    'rfc_emisor'              => $rfcEmisor,
                    'rfc_receptor'            => null,
                    'forma_pago'              => null,
                    'invoice_metodo_pago'     => null,

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
                    'include_in_statement'    => null,
                    'statement_period_target' => null,

                    'notes'                   => 'Proyección generada desde configuración recurrente de la cuenta.',
                    'audit_key'               => 'projection|' . $accountId . '|' . $period,
                    'linked_sale_ids'         => [],

                    'expected_total_raw'      => $expected,
                    'cargo_raw'               => 0.0,
                    'abono_ec_raw'            => 0.0,
                    'abono_pay_raw'           => 0.0,
                    'tarifa_label_raw'        => $tarifaLabel,
                ]);
            }
        }

        return $rows->values();
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
            ->sort(function ($a, $b) {
                $periodA = (string) ($a->period ?? '');
                $periodB = (string) ($b->period ?? '');

                if ($periodA !== $periodB) {
                    return $periodA <=> $periodB;
                }

                $projA = (int) ($a->is_projection ?? 0);
                $projB = (int) ($b->is_projection ?? 0);

                // reales primero, proyecciones al final
                if ($projA !== $projB) {
                    return $projA <=> $projB;
                }

                $srcRank = function ($r): int {
                    $src = (string) ($r->source ?? '');

                    return match ($src) {
                        'statement'   => 0,
                        'sale',
                        'sale_linked' => 1,
                        'projection'  => 2,
                        default       => 9,
                    };
                };

                $srcA = $srcRank($a);
                $srcB = $srcRank($b);

                if ($srcA !== $srcB) {
                    return $srcA <=> $srcB;
                }

                $totalA = (float) ($a->total ?? 0);
                $totalB = (float) ($b->total ?? 0);

                // mayor total primero
                if ($totalA !== $totalB) {
                    return $totalB <=> $totalA;
                }

                $clientA = (string) ($a->client ?? '');
                $clientB = (string) ($b->client ?? '');

                return $clientA <=> $clientB;
            })
            ->values();
    }

    private function buildKpis(Collection $rows): array
    {
        $realRows = $rows
            ->filter(fn ($r) => (int) ($r->is_projection ?? 0) !== 1)
            ->filter(fn ($r) => (int) ($r->exclude_from_kpi ?? 0) !== 1)
            ->values();

        $projectionRows = $rows
            ->filter(fn ($r) => (int) ($r->is_projection ?? 0) === 1)
            ->filter(fn ($r) => (int) ($r->exclude_from_kpi ?? 0) !== 1)
            ->values();

        $pending = $realRows->where('ec_status', 'pending');
        $emitido = $realRows->where('ec_status', 'emitido');
        $parcial = $realRows->where('ec_status', 'parcial');
        $pagado  = $realRows->where('ec_status', 'pagado');
        $vencido = $realRows->where('ec_status', 'vencido');

        $facturado = round((float) $realRows->sum(fn ($r) => (float) ($r->facturado ?? $r->total ?? 0)), 2);
        $cobrado   = round((float) $realRows->sum(fn ($r) => (float) ($r->abono ?? 0)), 2);
        $saldo     = round((float) $realRows->sum(fn ($r) => (float) ($r->saldo ?? 0)), 2);
        $projected = round((float) $projectionRows->sum(fn ($r) => (float) ($r->total ?? 0)), 2);

        return [
            'total' => [
                'count'  => $realRows->count(),
                'amount' => $facturado,
            ],
            'pagado' => [
                'count'  => $pagado->count(),
                'amount' => round((float) $pagado->sum(fn ($r) => (float) ($r->facturado ?? $r->total ?? 0)), 2),
            ],
            'pending' => [
                'count'  => $pending->count(),
                'amount' => round((float) $pending->sum(fn ($r) => (float) ($r->saldo ?? 0)), 2),
            ],
            'emitido' => [
                'count'  => $emitido->count(),
                'amount' => round((float) $emitido->sum(fn ($r) => (float) ($r->saldo ?? 0)), 2),
            ],
            'vencido' => [
                'count'  => $vencido->count(),
                'amount' => round((float) $vencido->sum(fn ($r) => (float) ($r->saldo ?? 0)), 2),
            ],
            'projected' => [
                'count'  => $projectionRows->count(),
                'amount' => $projected,
            ],
            'receivable' => [
                'count'  => $realRows->filter(fn ($r) => (float) ($r->saldo ?? 0) > 0.00001)->count(),
                'amount' => $saldo,
            ],
            'goal' => [
                'amount'   => round($facturado + $projected, 2),
                'progress' => $facturado > 0 ? round(min(100, ($cobrado / $facturado) * 100), 1) : 0.0,
            ],
            'mix' => [
                'recurrente' => round((float) $realRows->where('origin', 'recurrente')->sum(fn ($r) => (float) ($r->facturado ?? 0)), 2),
                'unico'      => round((float) $realRows->where('origin', 'unico')->sum(fn ($r) => (float) ($r->facturado ?? 0)), 2),
            ],
            'sources' => [
                'statement' => round((float) $realRows->where('source', 'statement')->sum(fn ($r) => (float) ($r->facturado ?? 0)), 2),
                'sale'      => round((float) $realRows->filter(fn ($r) => str_starts_with((string) ($r->source ?? ''), 'sale'))->sum(fn ($r) => (float) ($r->facturado ?? 0)), 2),
            ],
            'cash' => [
                'amount' => $cobrado,
            ],
        ];
    }

    private function buildCharts(Collection $rows, array $filters): array
    {
        $baseRows = $rows
            ->filter(fn ($r) => (int) ($r->exclude_from_kpi ?? 0) !== 1)
            ->values();

        $realRows = $baseRows->filter(fn ($r) => (int) ($r->is_projection ?? 0) !== 1)->values();
        $projectionRows = $baseRows->filter(fn ($r) => (int) ($r->is_projection ?? 0) === 1)->values();

        $monthly = [];
        foreach ($filters['periods'] as $period) {
            $sliceReal = $realRows->where('period', $period);
            $sliceProj = $projectionRows->where('period', $period);

            $real = round((float) $sliceReal->sum(fn ($r) => (float) ($r->facturado ?? $r->total ?? 0)), 2);
            $collected = round((float) $sliceReal->sum(fn ($r) => (float) ($r->abono ?? 0)), 2);
            $projected = round((float) $sliceProj->sum(fn ($r) => (float) ($r->total ?? 0)), 2);

            $monthly[] = [
                'period'     => $period,
                'label'      => $this->monthNameEsFromPeriod($period),
                'real'       => $real,
                'projected'  => $projected,
                'collected'  => $collected,
            ];
        }

        $originMix = [
            ['label' => 'Recurrente', 'value' => round((float) $realRows->where('origin', 'recurrente')->sum(fn ($r) => (float) ($r->facturado ?? 0)), 2)],
            ['label' => 'Único',      'value' => round((float) $realRows->where('origin', 'unico')->sum(fn ($r) => (float) ($r->facturado ?? 0)), 2)],
        ];

        $vendorTop = $baseRows
            ->groupBy(fn ($r) => (string) ($r->vendor ?: 'Sin vendedor'))
            ->map(function ($group, $vendorName) {
                return [
                    'label' => $vendorName,
                    'value' => round((float) $group->sum(function ($r) {
                        if ((int) ($r->is_projection ?? 0) === 1) {
                            return (float) ($r->total ?? 0);
                        }
                        return (float) ($r->facturado ?? 0);
                    }), 2),
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
        $baseRows = $rows
            ->filter(fn ($r) => (int) ($r->exclude_from_kpi ?? 0) !== 1)
            ->filter(fn ($r) => (int) ($r->is_projection ?? 0) !== 1)
            ->values();

        $bestMonth = $baseRows
            ->groupBy('period')
            ->map(fn ($group, $period) => [
                'period' => (string) $period,
                'label'  => $this->monthNameEsFromPeriod((string) $period),
                'total'  => round((float) $group->sum(fn ($r) => (float) ($r->facturado ?? $r->total ?? 0)), 2),
            ])
            ->sortByDesc('total')
            ->first();

        $topVendor = $rows
            ->filter(fn ($r) => (int) ($r->exclude_from_kpi ?? 0) !== 1)
            ->groupBy(fn ($r) => (string) ($r->vendor ?: 'Sin vendedor'))
            ->map(fn ($group, $vendor) => [
                'vendor' => $vendor,
                'total'  => round((float) $group->sum(function ($r) {
                    if ((int) ($r->is_projection ?? 0) === 1) {
                        return (float) ($r->total ?? 0);
                    }
                    return (float) ($r->facturado ?? $r->total ?? 0);
                }), 2),
            ])
            ->sortByDesc('total')
            ->first();

        $criticalPending = $baseRows
            ->filter(fn ($r) => (float) ($r->saldo ?? 0) > 0.00001)
            ->sortByDesc(fn ($r) => (float) ($r->saldo ?? 0))
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
                'total'  => round((float) ($criticalPending->saldo ?? 0), 2),
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
        string $period
    ): array {
        $billing = (array) ($meta['billing'] ?? []);

        $rawBase = $billing['amount_mxn'] ?? ($billing['amount'] ?? null);
        $base = $this->toFloat($rawBase) ?? 0.0;

        $ov = (array) ($billing['override'] ?? []);
        $override = $this->toFloat(
            $ov['amount_mxn'] ?? ($billing['override_amount_mxn'] ?? null)
        ) ?? 0.0;

        $eff = strtolower(trim((string) ($ov['effective'] ?? ($billing['override_effective'] ?? ''))));
        if (!in_array($eff, ['now', 'next'], true)) {
            $eff = '';
        }

        $lastPaid = $this->resolveLastPaidPeriodForAccount((string) ($acc->id ?? ''), $meta);
        $payAllowed = $lastPaid
            ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
            : $period;

        $apply = false;
        if ($override > 0.00001) {
            if ($eff === 'now') {
                $apply = true;
            } elseif ($eff === 'next') {
                $apply = ($payAllowed && $period >= $payAllowed);
            }
        }

        $effective = $apply ? $override : $base;
        $label     = $apply ? 'Tarifa ajustada' : 'Tarifa base';

        return [round(max(0.0, (float) $effective), 2), $label];
    }

    private function extractCustomAmountMxnFromAccountMeta(object $row, array $meta): ?float
    {
        $candidates = [
            data_get($meta, 'billing.override_amount_mxn'),
            data_get($meta, 'billing.override.amount_mxn'),
            data_get($meta, 'billing.custom.amount_mxn'),
            data_get($meta, 'billing.custom_mxn'),
            data_get($meta, 'custom.amount_mxn'),
            data_get($meta, 'custom_mxn'),
        ];

        foreach ($candidates as $v) {
            $n = $this->toFloat($v);
            if ($n !== null && $n > 0.00001) {
                return $n;
            }
        }

        foreach ([
            'override_amount_mxn',
            'custom_amount_mxn',
            'billing_amount_mxn',
            'amount_mxn',
            'precio_mxn',
            'monto_mxn',
            'license_amount_mxn',
            'billing_amount',
            'amount',
            'precio',
            'monto',
        ] as $prop) {
            if (isset($row->{$prop})) {
                $n = $this->toFloat($row->{$prop});
                if ($n !== null && $n > 0.00001) {
                    return $n;
                }
            }
        }

        return null;
    }

    private function resolveLastPaidPeriodForAccount(string $accountId, array $meta): ?string
    {
        foreach ([
            data_get($meta, 'stripe.last_paid_period'),
            data_get($meta, 'stripe.lastPaidPeriod'),
            data_get($meta, 'billing.last_paid_period'),
            data_get($meta, 'billing.lastPaidPeriod'),
            data_get($meta, 'last_paid_period'),
            data_get($meta, 'lastPaidPeriod'),
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

    private function resolveRecurringRuleForProjection(object $acc, array $meta, array $subsRenewMap): array
    {
        $billing = (array) ($meta['billing'] ?? []);

        $mode = strtolower(trim((string) (
            $acc->modo_cobro
            ?? data_get($meta, 'billing.mode')
            ?? data_get($meta, 'billing.billing_cycle')
            ?? data_get($meta, 'billing.cycle')
            ?? ''
        )));

        $priceKey = strtolower(trim((string) ($billing['price_key'] ?? '')));
        $plan = strtolower(trim((string) ($acc->plan_actual ?? $acc->plan ?? '')));

        $periodicity = 'unico';

        if (in_array($mode, ['mensual', 'monthly'], true)) {
            $periodicity = 'mensual';
        } elseif (in_array($mode, ['anual', 'annual', 'yearly'], true)) {
            $periodicity = 'anual';
        } elseif (str_contains($priceKey, 'anual') || str_contains($priceKey, 'year')) {
            $periodicity = 'anual';
        } elseif (str_contains($priceKey, 'mensual') || str_contains($priceKey, 'month')) {
            $periodicity = 'mensual';
        } elseif (str_contains($plan, 'anual')) {
            $periodicity = 'anual';
        } elseif (
            str_contains($plan, 'pro')
            || str_contains($plan, 'mensual')
            || str_contains($plan, 'monthly')
        ) {
            $periodicity = 'mensual';
        }

        $expected = 0.0;
        $custom = $this->extractCustomAmountMxnFromAccountMeta($acc, $meta);
        if ($custom !== null && $custom > 0.00001) {
            $expected = round($custom, 2);
        } else {
            [$baseExpected] = $this->resolveExpectedAmountForStatement($acc, $meta, now()->format('Y-m'));
            $expected = round((float) $baseExpected, 2);
        }

        if ($expected <= 0.00001) {
            return [
                'is_recurrente' => false,
                'periodicity'   => 'unico',
                'anchor_month'  => null,
            ];
        }

        if (!in_array($periodicity, ['mensual', 'anual'], true)) {
            return [
                'is_recurrente' => false,
                'periodicity'   => 'unico',
                'anchor_month'  => null,
            ];
        }

        $anchorMonth = null;

        $lastPaidPeriod = $this->resolveLastPaidPeriodForAccount((string) ($acc->id ?? ''), $meta);
        if ($lastPaidPeriod && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $lastPaidPeriod)) {
            $anchorMonth = (int) substr($lastPaidPeriod, 5, 2);
        }

        if ($anchorMonth === null) {
            $renew = (string) ($subsRenewMap[(string) ($acc->id ?? '')] ?? '');
            if ($renew !== '' && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $renew)) {
                $anchorMonth = (int) substr($renew, 5, 2);
            }
        }

        if ($anchorMonth === null) {
            foreach ([
                data_get($meta, 'billing.assigned_at'),
                data_get($meta, 'stripe.last_paid_at'),
                $acc->created_at ?? null,
            ] as $candidate) {
                $p = $this->parseToPeriod($candidate);
                if ($p && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $p)) {
                    $anchorMonth = (int) substr($p, 5, 2);
                    break;
                }
            }
        }

        if ($anchorMonth === null || $anchorMonth < 1 || $anchorMonth > 12) {
            $anchorMonth = 1;
        }

        return [
            'is_recurrente' => true,
            'periodicity'   => $periodicity,
            'anchor_month'  => $anchorMonth,
        ];
    }

    private function periodMatchesRecurrenceForProjection(string $period, array $rule): bool
    {
        if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
            return false;
        }

        $periodicity = (string) ($rule['periodicity'] ?? 'unico');
        if ($periodicity === 'mensual') {
            return true;
        }

        if ($periodicity === 'anual') {
            $month = (int) substr($period, 5, 2);
            $anchor = (int) ($rule['anchor_month'] ?? 1);
            return $month === $anchor;
        }

        return false;
    }

    private function computeStatementStatusFromEstadoCuenta(
        float $totalCargo,
        float $totalAbono,
        string $period
    ): string {
        $total = round(max(0.0, $totalCargo), 2);
        $abono = round(max(0.0, $totalAbono), 2);
        $saldo = round(max(0.0, $total - $abono), 2);

        if ($total <= 0.00001) {
            return 'sin_mov';
        }

        if ($saldo <= 0.00001) {
            return 'pagado';
        }

        if ($abono > 0.00001 && $abono < ($total - 0.00001)) {
            return 'parcial';
        }

        if ($this->isOverdue($period, null)) {
            return 'vencido';
        }

        return 'pending';
    }

    private function applyStatementOverrideDisplayFromEstadoCuenta(
        float $totalShown,
        float $abonoEdo,
        float $saldoShown,
        string $statusPago,
        ?array $override
    ): array {
        $total  = round(max(0.0, $totalShown), 2);
        $abono  = round(max(0.0, $abonoEdo), 2);
        $saldo  = round(max(0.0, $saldoShown), 2);
        $status = strtolower(trim((string) $statusPago));

        if (!in_array($status, ['pending', 'emitido', 'parcial', 'pagado', 'vencido', 'sin_mov'], true)) {
            $status = 'pending';
        }

        $abonoAplicado = round(min($abono, $total), 2);
        $saldoAplicado = round(max(0.0, $total - $abonoAplicado), 2);

        if (!$override || !isset($override['status']) || trim((string) $override['status']) === '') {
            return [
                'total'  => $total,
                'abono'  => $abonoAplicado,
                'saldo'  => $saldoAplicado,
                'status' => $status,
            ];
        }

        $raw = strtolower(trim((string) $override['status']));
        $raw = str_replace([' ', '-'], '_', $raw);

        if ($raw === 'pendiente') {
            $raw = 'pending';
        }

        if (!in_array($raw, ['pending', 'emitido', 'parcial', 'pagado', 'vencido', 'sin_mov'], true)) {
            return [
                'total'  => $total,
                'abono'  => $abonoAplicado,
                'saldo'  => $saldoAplicado,
                'status' => $status,
            ];
        }

        if ($raw === 'pagado') {
            return [
                'total'  => $total,
                'abono'  => $total,
                'saldo'  => 0.0,
                'status' => 'pagado',
            ];
        }

        if ($raw === 'sin_mov') {
            return [
                'total'  => $total,
                'abono'  => 0.0,
                'saldo'  => $total,
                'status' => 'sin_mov',
            ];
        }

        if ($raw === 'parcial') {
            if ($abonoAplicado <= 0.00001) {
                $abonoAplicado = 0.0;
                $saldoAplicado = $total;
            } elseif ($abonoAplicado >= $total) {
                $abonoAplicado = round(max(0.0, $total - 0.01), 2);
                $saldoAplicado = round(max(0.0, $total - $abonoAplicado), 2);
            }

            return [
                'total'  => $total,
                'abono'  => $abonoAplicado,
                'saldo'  => $saldoAplicado,
                'status' => 'parcial',
            ];
        }

        return [
            'total'  => $total,
            'abono'  => $abonoAplicado,
            'saldo'  => $saldoAplicado,
            'status' => $raw,
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
        ] as $value) {
            $value = trim((string) ($value ?? ''));
            if ($value !== '') {
                return $value;
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
        $mode = strtolower(trim((string) (
            $acc->modo_cobro
            ?? data_get($meta, 'billing.mode')
            ?? data_get($meta, 'billing.billing_cycle')
            ?? ''
        )));

        if (in_array($mode, ['mensual', 'monthly', 'anual', 'annual', 'yearly'], true)) {
            return 'recurrente';
        }

        foreach ($items as $item) {
            $concepto = strtolower(trim((string) ($item->concepto ?? '')));
            if (
                str_contains($concepto, 'servicio mensual')
                || str_contains($concepto, 'servicio anual')
                || str_contains($concepto, 'licencia')
            ) {
                return 'recurrente';
            }
        }

        return 'unico';
    }

    private function resolveStatementPeriodicity(object $acc, array $meta): string
    {
        $mode = strtolower(trim((string) (
            $acc->modo_cobro
            ?? data_get($meta, 'billing.mode')
            ?? data_get($meta, 'billing.billing_cycle')
            ?? ''
        )));

        return match ($mode) {
            'mensual', 'monthly' => 'mensual',
            'anual', 'annual', 'yearly' => 'anual',
            default => 'unico',
        };
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
            'sin_mov'          => 'sin_mov',
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