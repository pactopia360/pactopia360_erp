<?php

declare(strict_types=1);

namespace App\Services\Finance;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminIncomeService
{
    public function build(Request $req): array
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $cli = (string) (config('p360.conn.clientes') ?: 'mysql_clientes');

        // -------------------------
        // Filtros
        // -------------------------
        $year   = (int) ($req->input('year') ?: (int) now()->format('Y'));
        $month  = (string) ($req->input('month') ?: 'all'); // 01..12 | all

        // Excel: Origen = recurrente | unico
        // Compat: UI vieja puede mandar no_recurrente
        $origin = strtolower((string) ($req->input('origin') ?: 'all')); // recurrente|unico|no_recurrente|all

        $st     = (string) ($req->input('status') ?: 'all'); // pending|emitido|pagado|vencido|all
        $invSt  = (string) ($req->input('invoice_status') ?: 'all');
        $q      = trim((string) ($req->input('q') ?: ''));

        $periodFrom = Carbon::create($year, 1, 1)->startOfMonth();
        $periodTo   = Carbon::create($year, 12, 1)->endOfMonth();

        if (!Schema::connection($adm)->hasTable('billing_statements')) {
            return [
                'filters' => compact('year', 'month', 'origin', 'st', 'invSt', 'q'),
                'kpis'    => $this->blankKpis(),
                'rows'    => collect(),
            ];
        }

        // -------------------------
        // Periodos del año (para proyección y pagos)
        // -------------------------
        $periodsYear = [];
        for ($m = 1; $m <= 12; $m++) {
            $periodsYear[] = sprintf('%04d-%02d', $year, $m);
        }

        if ($month !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            $periodsYear = [sprintf('%04d-%s', $year, $month)];
        }

        // -------------------------
        // Statements existentes (año / mes)
        // -------------------------
        $statementsQ = DB::connection($adm)->table('billing_statements as bs')
            ->select([
                'bs.id',
                'bs.account_id',
                'bs.period',
                'bs.total_cargo',
                'bs.total_abono',
                'bs.saldo',
                'bs.status',
                'bs.due_date',
                'bs.sent_at',
                'bs.paid_at',
                'bs.snapshot',
                'bs.meta',
                'bs.is_locked',
                'bs.created_at',
                'bs.updated_at',
            ])
            ->whereBetween('bs.period', [
                $periodFrom->format('Y-m'),
                $periodTo->format('Y-m'),
            ]);

        if ($month !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            $statementsQ->where('bs.period', '=', sprintf('%04d-%s', $year, $month));
        }

        $statements = collect($statementsQ->orderBy('bs.period')->orderBy('bs.id')->get());

        // Mapa rápido: statement por account|period
        $stByKey = $statements->keyBy(fn ($s) => (string) $s->account_id . '|' . (string) $s->period);

        $statementIds = $statements->pluck('id')->all();

        // -------------------------
        // Items por statement (para totales / baseline recurrente)
        // -------------------------
        $itemsByStatement = collect();
        if (Schema::connection($adm)->hasTable('billing_statement_items') && !empty($statementIds)) {
            $itemsByStatement = DB::connection($adm)->table('billing_statement_items as bi')
                ->select([
                    'bi.id',
                    'bi.statement_id',
                    'bi.type',
                    'bi.code',
                    'bi.description',
                    'bi.qty',
                    'bi.unit_price',
                    'bi.amount',
                    'bi.ref',
                    'bi.meta',
                ])
                ->whereIn('bi.statement_id', $statementIds)
                ->get()
                ->groupBy('statement_id');
        }

        // -------------------------
        // Invoices (por statement_id y/o account_id+period)
        // -------------------------
        $invByStatement = collect();
        $invByAccPeriod = collect();

        if (Schema::connection($adm)->hasTable('billing_invoice_requests')) {
            $invAll = DB::connection($adm)->table('billing_invoice_requests')
                ->select([
                    'id',
                    'statement_id',
                    'account_id',
                    'period',
                    'status',
                    'cfdi_uuid',
                    'cfdi_folio',
                    'cfdi_url',
                    'requested_at',
                    'issued_at',
                    'zip_ready_at',
                    'zip_sent_at',
                    'meta',
                ])
                ->whereIn('period', $periodsYear)
                ->orderBy('id', 'desc')
                ->get();

            $invByStatement = $invAll->whereNotNull('statement_id')->groupBy('statement_id');
            $invByAccPeriod = $invAll->groupBy(fn ($r) => (string) $r->account_id . '|' . (string) $r->period);
        }

        // -------------------------
        // Billing profiles (RFC receptor / forma pago CFDI)
        // -------------------------
        $profiles = collect();
        if (Schema::connection($adm)->hasTable('finance_billing_profiles')) {
            $profiles = DB::connection($adm)->table('finance_billing_profiles')
                ->select([
                    'account_id',
                    'rfc_receptor',
                    'razon_social',
                    'email_cfdi',
                    'uso_cfdi',
                    'regimen_fiscal',
                    'cp_fiscal',
                    'forma_pago',
                    'metodo_pago',
                    'meta',
                ])
                ->get()
                ->keyBy('account_id');
        }

        // -------------------------
        // Cuentas cliente (mysql_clientes)
        // -------------------------
        $cuentas = collect();
        if (Schema::connection($cli)->hasTable('cuentas_cliente')) {

            $accIdsFromStatements = $statements->pluck('account_id')->unique()->filter()->values();

            $uuidIds = $accIdsFromStatements->filter(fn ($x) => is_string($x) && preg_match('/^[0-9a-f\-]{36}$/i', $x))->values()->all();
            $numIds  = $accIdsFromStatements->filter(fn ($x) => is_string($x) && preg_match('/^\d+$/', $x))->map(fn ($x) => (int)$x)->values()->all();

            $qAcc = DB::connection($cli)->table('cuentas_cliente')
                ->select([
                    'id',
                    'admin_account_id',
                    'rfc',
                    'rfc_padre',
                    'razon_social',
                    'nombre_comercial',
                    'plan_actual',
                    'modo_cobro',
                    'estado_cuenta',
                    'activo',
                    'is_blocked',
                    'email',
                    'telefono',
                    'next_invoice_date',
                    'created_at',
                    'meta',
                ]);

            if (!empty($uuidIds)) $qAcc->whereIn('id', $uuidIds);
            if (!empty($numIds))  $qAcc->orWhereIn('admin_account_id', $numIds);

            $cuentas = collect($qAcc->get());
        }

        $cuentaByUuid    = $cuentas->keyBy('id');
        $cuentaByAdminId = $cuentas->filter(fn ($c) => !empty($c->admin_account_id))->keyBy('admin_account_id');

        // -------------------------
        // Payments (admin.payments) — account_id BIGINT = admin_account_id
        // -------------------------
        $paymentsByAdminAccPeriod = collect();
        if (Schema::connection($adm)->hasTable('payments')) {

            $adminIds = $cuentas->pluck('admin_account_id')->filter()->unique()->values()->all();

            if (!empty($adminIds)) {
                $paymentsByAdminAccPeriod = DB::connection($adm)->table('payments')
                    ->select([
                        'id',
                        'account_id',
                        'amount',
                        'currency',
                        'method',
                        'provider',
                        'concept',
                        'reference',
                        'status',
                        'period',
                        'due_date',
                        'paid_at',
                        'meta',
                        'created_at',
                    ])
                    ->whereIn('account_id', $adminIds)
                    ->whereIn('period', $periodsYear)
                    ->orderBy('id', 'desc')
                    ->get()
                    ->groupBy(fn ($p) => (string) $p->account_id . '|' . (string) $p->period);
            }
        }

        // -------------------------
        // Baseline recurrente: último subtotal recurrente conocido por cuenta (uuid)
        // -------------------------
        $baselineRecurring = [];
        foreach ($statements as $s) {
            $its = collect($itemsByStatement->get($s->id, collect()));

            $originGuess = $this->guessOrigin($its, $this->decodeJson($s->snapshot), $this->decodeJson($s->meta));
            if ($originGuess !== 'recurrente') continue;

            $subtotal = (float) $its->sum(fn ($it) => (float) ($it->amount ?? 0));
            $key = (string) $s->account_id;

            if (!isset($baselineRecurring[$key])) {
                $baselineRecurring[$key] = ['period' => (string)$s->period, 'subtotal' => $subtotal];
            } else {
                if ((string)$s->period >= (string)$baselineRecurring[$key]['period']) {
                    $baselineRecurring[$key] = ['period' => (string)$s->period, 'subtotal' => $subtotal];
                }
            }
        }

        // -------------------------
        // 1) Filas de statements existentes
        // -------------------------
        $rowsExisting = $statements->map(function ($s) use (
            $itemsByStatement,
            $invByStatement,
            $invByAccPeriod,
            $profiles,
            $cuentaByUuid,
            $cuentaByAdminId,
            $paymentsByAdminAccPeriod
        ) {

            $snap = $this->decodeJson($s->snapshot);
            $meta = $this->decodeJson($s->meta);

            $cc = null;
            $sid = (string) $s->account_id;
            if (preg_match('/^[0-9a-f\-]{36}$/i', $sid)) {
                $cc = $cuentaByUuid->get($sid);
            } elseif (preg_match('/^\d+$/', $sid)) {
                $cc = $cuentaByAdminId->get((int)$sid);
            }

            $company = (string) (
                ($cc?->nombre_comercial ?: null)
                ?? ($cc?->razon_social ?: null)
                ?? data_get($snap, 'account.company')
                ?? data_get($snap, 'company')
                ?? data_get($snap, 'razon_social')
                ?? data_get($meta, 'company')
                ?? ('Cuenta ' . $s->account_id)
            );

            $rfcEmisor = (string) (
                ($cc?->rfc_padre ?: null)
                ?? ($cc?->rfc ?: null)
                ?? data_get($snap, 'account.rfc')
                ?? data_get($snap, 'rfc')
                ?? ''
            );

            $its = collect($itemsByStatement->get($s->id, collect()));

            $subtotal = (float) $its->sum(fn ($it) => (float) ($it->amount ?? 0));
            $iva      = round($subtotal * 0.16, 2);
            $total    = round($subtotal + $iva, 2);

            $origin = $this->guessOrigin($its, $snap, $meta);

            $periodicity = $this->guessPeriodicity($snap, $meta, $its);
            $modoCobro = strtolower((string) ($cc?->modo_cobro ?? ''));
            if (in_array($modoCobro, ['mensual', 'anual'], true)) {
                $periodicity = $modoCobro;
                $origin = 'recurrente';
            }

            if ($origin === 'recurrente' && $periodicity === 'unico') {
                $periodicity = 'mensual';
            }

            $ecStatus = $this->normalizeStatementStatus($s);

            $bp = $profiles->get((string) $s->account_id);
            $rfcReceptor = (string) ($bp->rfc_receptor ?? '');
            $formaPago   = (string) ($bp->forma_pago ?? '');

            $invRow = optional($invByStatement->get($s->id))->first();
            if (!$invRow) {
                $invRow = optional($invByAccPeriod->get((string)$s->account_id . '|' . (string)$s->period))->first();
            }

            $invStatus   = $invRow?->status ? (string) $invRow->status : null;
            $invoiceDate = $invRow?->issued_at ?: null;
            $cfdiUuid    = $invRow?->cfdi_uuid ?: null;

            $adminAccId = $cc?->admin_account_id;
            $p = null;
            if (!empty($adminAccId)) {
                $p = optional($paymentsByAdminAccPeriod->get((string)$adminAccId . '|' . (string)$s->period))->first();
            }

            $paidAt = $p?->paid_at ?: $s->paid_at ?: null;

            $ym = (string) $s->period;
            $y  = (int) substr($ym, 0, 4);
            $m  = (int) substr($ym, 5, 2);

            $desc = $this->buildDescriptionFromItems($its, $origin, $periodicity);

            return (object) [
                'year'         => $y,
                'month_num'    => sprintf('%02d', $m),
                'month_name'   => $this->monthNameEs($m),

                // vendedor (se conecta por ventas)
                'vendor'       => null,

                'client'       => $company,
                'description'  => $desc,

                'period'       => $ym,
                'account_id'   => (string) $s->account_id,

                'company'      => $company,
                'rfc_emisor'   => $rfcEmisor,

                'origin'       => $origin,        // recurrente|unico
                'periodicity'  => $periodicity,   // mensual|anual|unico

                'subtotal'     => $subtotal,
                'iva'          => $iva,
                'total'        => $total,

                'ec_status'    => $ecStatus,
                'due_date'     => $s->due_date,
                'sent_at'      => $s->sent_at,
                'paid_at'      => $paidAt,

                'rfc_receptor' => $rfcReceptor,
                'forma_pago'   => $formaPago,

                'f_emision'    => $s->sent_at,
                'f_pago'       => $paidAt,
                'f_cta'        => $s->sent_at,
                'f_mov'        => null,

                'f_factura'      => $invoiceDate,
                'invoice_date'   => $invoiceDate,
                'invoice_status' => $invStatus,
                'invoice_status_raw' => $invStatus,
                'cfdi_uuid'      => $cfdiUuid,

                'payment_method' => $p?->method ?: null,
                'payment_status' => $p?->status ?: null,

                'raw_statement_status' => (string) $s->status,
            ];
        });

        // -------------------------
        // 2) PROYECCIÓN recurrente (pending) si no hay statement
        // -------------------------
        $rowsProjected = collect();

        if (Schema::connection($cli)->hasTable('cuentas_cliente')) {

            $recQ = DB::connection($cli)->table('cuentas_cliente')
                ->select([
                    'id',
                    'admin_account_id',
                    'rfc',
                    'rfc_padre',
                    'razon_social',
                    'nombre_comercial',
                    'plan_actual',
                    'modo_cobro',
                    'estado_cuenta',
                    'activo',
                    'is_blocked',
                    'next_invoice_date',
                    'created_at',
                    'meta',
                ])
                ->whereIn('modo_cobro', ['mensual', 'anual'])
                ->where('activo', '=', 1);

            $rec = collect($recQ->get());

            foreach ($rec as $cc) {
                $modo = strtolower((string) $cc->modo_cobro);
                if (!in_array($modo, ['mensual', 'anual'], true)) continue;

                $expectedPeriods = [];

                if ($modo === 'mensual') {
                    $expectedPeriods = $periodsYear;
                } else {
                    $m = 1;
                    try {
                        if (!empty($cc->next_invoice_date)) {
                            $d = Carbon::parse($cc->next_invoice_date);
                            if ((int)$d->format('Y') === $year) $m = (int)$d->format('m');
                        }
                        if ($m === 1 && !empty($cc->created_at)) {
                            $d2 = Carbon::parse($cc->created_at);
                            $m = (int)$d2->format('m');
                        }
                    } catch (\Throwable $e) {
                        $m = 1;
                    }
                    $expectedPeriods = [sprintf('%04d-%02d', $year, $m)];
                }

                foreach ($expectedPeriods as $per) {

                    $key = (string) $cc->id . '|' . (string) $per;
                    if ($stByKey->has($key)) continue;

                    $base = (float) (data_get($baselineRecurring, (string)$cc->id . '.subtotal') ?? 0.0);

                    $subtotal = $base;
                    $iva      = round($subtotal * 0.16, 2);
                    $total    = round($subtotal + $iva, 2);

                    $company = (string) (($cc->nombre_comercial ?: null) ?? ($cc->razon_social ?: null) ?? ('Cuenta ' . $cc->id));
                    $rfcEmisor = (string) (($cc->rfc_padre ?: null) ?? ($cc->rfc ?: null) ?? '');

                    $bp = $profiles->get((string) $cc->id);
                    $rfcReceptor = (string) ($bp->rfc_receptor ?? '');
                    $formaPago   = (string) ($bp->forma_pago ?? '');

                    $invRow = optional($invByAccPeriod->get((string)$cc->id . '|' . (string)$per))->first();
                    $invStatus   = $invRow?->status ? (string)$invRow->status : null;
                    $invoiceDate = $invRow?->issued_at ?: null;
                    $cfdiUuid    = $invRow?->cfdi_uuid ?: null;

                    $p = null;
                    if (!empty($cc->admin_account_id)) {
                        $p = optional($paymentsByAdminAccPeriod->get((string)$cc->admin_account_id . '|' . (string)$per))->first();
                    }

                    $ecStatus = 'pending';
                    if (!empty($p?->paid_at) || (strtolower((string)($p?->status ?? '')) === 'paid')) {
                        $ecStatus = 'pagado';
                    }

                    $y = (int) substr($per, 0, 4);
                    $m = (int) substr($per, 5, 2);

                    $rowsProjected->push((object) [
                        'year'         => $y,
                        'month_num'    => sprintf('%02d', $m),
                        'month_name'   => $this->monthNameEs($m),

                        'vendor'       => null,

                        'client'       => $company,
                        'description'  => ($modo === 'anual')
                            ? 'Recurrente Anual (proyección)'
                            : 'Recurrente Mensual (proyección)',

                        'period'       => (string) $per,
                        'account_id'   => (string) $cc->id,
                        'company'      => $company,
                        'rfc_emisor'   => $rfcEmisor,

                        'origin'       => 'recurrente',
                        'periodicity'  => $modo,

                        'subtotal'     => $subtotal,
                        'iva'          => $iva,
                        'total'        => $total,

                        'ec_status'    => $ecStatus,
                        'due_date'     => null,
                        'sent_at'      => null,
                        'paid_at'      => $p?->paid_at ?: null,

                        'rfc_receptor' => $rfcReceptor,
                        'forma_pago'   => $formaPago,

                        'f_emision'    => null,
                        'f_pago'       => $p?->paid_at ?: null,
                        'f_cta'        => null,
                        'f_mov'        => null,

                        'f_factura'      => $invoiceDate,
                        'invoice_date'   => $invoiceDate,
                        'invoice_status' => $invStatus,
                        'invoice_status_raw' => $invStatus,
                        'cfdi_uuid'      => $cfdiUuid,

                        'payment_method' => $p?->method ?: null,
                        'payment_status' => $p?->status ?: null,
                    ]);
                }
            }
        }

        // -------------------------
        // 3) VENTAS ÚNICAS DEL MES (finance_sales) — Origen Único
        // -------------------------
        $rowsSales = collect();

        if (Schema::connection($adm)->hasTable('finance_sales')) {

            $qSales = DB::connection($adm)->table('finance_sales as s')
                ->leftJoin('finance_vendors as v', 'v.id', '=', 's.vendor_id')
                ->select([
                    's.id',
                    's.account_id',
                    's.sale_code',
                    's.receiver_rfc',
                    's.pay_method',
                    's.origin',
                    's.periodicity',
                    's.vendor_id',
                    'v.name as vendor_name',
                    's.period',
                    's.sale_date',
                    's.f_cta',
                    's.f_mov',
                    's.invoice_date',
                    's.paid_date',
                    's.subtotal',
                    's.iva',
                    's.total',
                    's.statement_status',
                    's.invoice_status',
                    's.cfdi_uuid',
                    's.include_in_statement',
                    's.statement_period_target',
                    's.notes',
                    's.created_at',
                ])
                ->whereIn('s.period', $periodsYear);

            // Excel: ventas únicas (no recurrentes)
            // permitimos origin unico/no_recurrente, y/o periodicity unico
            $qSales->where(function ($w) {
                $w->whereIn('s.origin', ['unico', 'no_recurrente'])
                  ->orWhere('s.periodicity', '=', 'unico');
            });

            $sales = collect($qSales->orderBy('s.period')->orderBy('s.id')->get());

            // company desde mysql_clientes.cuentas_cliente (sin JOIN cross-db)
            $sales = $this->attachCompanyFromClientes($sales, $cli);

            $rowsSales = $sales->map(function ($s) {

                $per = (string) $s->period;
                $y = (int) substr($per, 0, 4);
                $m = (int) substr($per, 5, 2);

                $origin = strtolower((string) $s->origin);
                if ($origin === 'no_recurrente') $origin = 'unico';
                if (!in_array($origin, ['recurrente', 'unico'], true)) $origin = 'unico';

                $periodicity = strtolower((string) $s->periodicity);
                if (!in_array($periodicity, ['mensual', 'anual', 'unico'], true)) $periodicity = 'unico';

                $ecStatus = strtolower((string) ($s->statement_status ?? 'pending'));
                if (!in_array($ecStatus, ['pending', 'emitido', 'pagado', 'vencido'], true)) $ecStatus = 'pending';

                // Map invoice_status (finance_sales) -> filtro “tipo invoice_requests”
                $invRaw = strtolower((string) ($s->invoice_status ?? ''));
                $invCanonical = $this->mapSalesInvoiceStatusToCanonical($invRaw);

                $desc = trim((string) ($s->notes ?? ''));
                if ($desc === '') {
                    $desc = trim((string) ($s->sale_code ?? 'Venta única'));
                } else {
                    // Agregar sale_code si existe para trazabilidad
                    $sc = trim((string) ($s->sale_code ?? ''));
                    if ($sc !== '') $desc = $sc . ' · ' . $desc;
                }

                // F Mov: priorizar f_mov, luego sale_date, luego created_at
                $fMov = $s->f_mov ?: ($s->sale_date ?: ($s->created_at ?: null));

                return (object) [
                    'year'         => $y,
                    'month_num'    => sprintf('%02d', $m),
                    'month_name'   => $this->monthNameEs($m),

                    'vendor'       => $s->vendor_name ?: null,

                    'client'       => (string) ($s->company ?? ('Cuenta ' . $s->account_id)),
                    'description'  => $desc !== '' ? $desc : 'Venta Única',

                    'period'       => $per,
                    'account_id'   => (string) $s->account_id,
                    'company'      => (string) ($s->company ?? ('Cuenta ' . $s->account_id)),
                    'rfc_emisor'   => (string) ($s->rfc_emisor ?? ''),

                    'origin'       => $origin,
                    'periodicity'  => $periodicity,

                    'subtotal'     => (float) ($s->subtotal ?? 0),
                    'iva'          => (float) ($s->iva ?? 0),
                    'total'        => (float) ($s->total ?? 0),

                    'ec_status'    => $ecStatus,
                    'due_date'     => null,
                    'sent_at'      => null,
                    'paid_at'      => $s->paid_date ?: null,

                    // Bloque facturación (desde venta)
                    'rfc_receptor' => (string) ($s->receiver_rfc ?? ''),
                    'forma_pago'   => (string) ($s->pay_method ?? ''),

                    'f_emision'    => $s->f_cta ?: null, // en ventas, lo más cercano a “emisión”/cta
                    'f_pago'       => $s->paid_date ?: null,
                    'f_cta'        => $s->f_cta ?: null,
                    'f_mov'        => $fMov,

                    'f_factura'      => $s->invoice_date ?: null,
                    'invoice_date'   => $s->invoice_date ?: null,
                    'invoice_status' => $invCanonical,      // 👈 para filtro UI (requested/issued/…)
                    'invoice_status_raw' => $invRaw,         // 👈 por si quieres mostrar raw
                    'cfdi_uuid'      => $s->cfdi_uuid ?: null,

                    'payment_method' => null,                // pagos reales del payments center (admin.payments)
                    'payment_status' => null,

                    'sale_id'        => (int) $s->id,
                    'include_in_statement' => (int) ($s->include_in_statement ?? 0),
                    'statement_period_target' => $s->statement_period_target ?: null,
                ];
            });
        }

        // -------------------------
        // Unión + filtros + sort
        // -------------------------
        $rows = $rowsExisting->concat($rowsProjected)->concat($rowsSales);

        $originNorm = $origin;
        if ($originNorm === 'no_recurrente') $originNorm = 'unico';

        $rows = $rows->filter(function ($r) use ($originNorm, $st, $invSt, $q) {

            if ($originNorm !== 'all' && strtolower((string)$r->origin) !== $originNorm) return false;
            if ($st !== 'all' && strtolower((string)$r->ec_status) !== strtolower($st)) return false;

            if ($invSt !== 'all') {
                $cmp = strtolower((string) ($r->invoice_status ?? ''));
                if ($cmp !== strtolower($invSt)) return false;
            }

            if ($q !== '') {
                $hay = strtolower(
                    ($r->client ?? '') . ' ' .
                    ($r->company ?? '') . ' ' .
                    ($r->account_id ?? '') . ' ' .
                    ($r->rfc_emisor ?? '') . ' ' .
                    ($r->rfc_receptor ?? '') . ' ' .
                    (($r->cfdi_uuid ?? '') . '') . ' ' .
                    (($r->description ?? '') . '') . ' ' .
                    (($r->vendor ?? '') . '')
                );
                if (!str_contains($hay, strtolower($q))) return false;
            }

            return true;
        });

        // sort: period asc, client asc, total desc
        $rows = $rows->sortBy([
            fn ($r) => (string) ($r->period ?? ''),
            fn ($r) => (string) ($r->client ?? $r->company ?? ''),
            fn ($r) => -1 * (float) ($r->total ?? 0),
        ])->values();

        $kpis = $this->computeKpis($rows);

        return [
            'filters' => compact('year', 'month', 'origin', 'st', 'invSt', 'q'),
            'kpis'    => $kpis,
            'rows'    => $rows,
        ];
    }

    private function attachCompanyFromClientes(Collection $rows, string $cliConn): Collection
    {
        $ids = $rows->pluck('account_id')->filter()->unique()->values()->all();
        if (empty($ids)) return $rows;

        if (!Schema::connection($cliConn)->hasTable('cuentas_cliente')) return $rows;

        $map = DB::connection($cliConn)->table('cuentas_cliente')
            ->select(['id', 'razon_social', 'nombre_comercial', 'rfc_padre'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return $rows->map(function ($r) use ($map) {
            $c = $map->get((string) $r->account_id);

            $r->company = $c
                ? (string) (($c->nombre_comercial ?: null) ?? ($c->razon_social ?: null) ?? ('Cuenta ' . $r->account_id))
                : (string) ('Cuenta ' . $r->account_id);

            $r->rfc_emisor = $c ? (string) ($c->rfc_padre ?? '') : '';

            return $r;
        });
    }

    private function mapSalesInvoiceStatusToCanonical(string $raw): string
    {
        // finance_sales.invoice_status:
        // sin_solicitud | solicitada | en_proceso | facturada | rechazada
        return match ($raw) {
            'solicitada'    => 'requested',
            'en_proceso'    => 'ready',
            'facturada'     => 'issued',
            'rechazada'     => 'cancelled',
            'sin_solicitud' => 'pending',
            default         => $raw !== '' ? $raw : 'pending',
        };
    }

    private function blankKpis(): array
    {
        return [
            'total'   => ['count' => 0, 'amount' => 0.0],
            'pending' => ['count' => 0, 'amount' => 0.0],
            'emitido' => ['count' => 0, 'amount' => 0.0],
            'pagado'  => ['count' => 0, 'amount' => 0.0],
            'vencido' => ['count' => 0, 'amount' => 0.0],
        ];
    }

    private function computeKpis(Collection $rows): array
    {
        $k = $this->blankKpis();

        foreach ($rows as $r) {
            $k['total']['count']++;
            $k['total']['amount'] += (float) ($r->total ?? 0);

            $st = strtolower((string) ($r->ec_status ?? ''));
            if ($st !== '' && isset($k[$st])) {
                $k[$st]['count']++;
                $k[$st]['amount'] += (float) ($r->total ?? 0);
            }
        }

        foreach ($k as $key => $v) {
            $k[$key]['amount'] = round((float) $k[$key]['amount'], 2);
        }

        return $k;
    }

    private function normalizeStatementStatus(object $s): string
    {
        if (!empty($s->paid_at)) return 'pagado';
        if (!empty($s->sent_at)) return 'emitido';

        $st = strtolower(trim((string) ($s->status ?? '')));

        $norm = match ($st) {
            'paid', 'pagado'       => 'pagado',
            'sent', 'emitido'      => 'emitido',
            'overdue', 'vencido'   => 'vencido',
            'pending', 'pendiente' => 'pending',
            default                => 'pending',
        };

        if ($norm === 'pending' && !empty($s->due_date)) {
            try {
                $due = Carbon::parse($s->due_date)->startOfDay();
                if ($due->lt(now()->startOfDay())) return 'vencido';
            } catch (\Throwable $e) {}
        }

        return $norm;
    }

    private function guessOrigin(Collection $items, array $snap, array $meta): string
    {
        $mode = strtolower((string) (data_get($snap, 'license.mode') ?? data_get($meta, 'license.mode') ?? ''));
        if (in_array($mode, ['mensual', 'anual'], true)) return 'recurrente';

        foreach ($items as $it) {
            $type = strtolower((string) ($it->type ?? ''));
            $code = strtolower((string) ($it->code ?? ''));

            if (in_array($type, ['license', 'subscription', 'plan'], true)) return 'recurrente';
            if (str_contains($code, 'lic') || str_contains($code, 'plan')) return 'recurrente';

            $im = $this->decodeJson($it->meta ?? null);
            $orig = strtolower((string) (data_get($im, 'origin') ?? ''));

            if ($orig === 'no_recurrente') return 'unico';
            if ($orig === 'recurrente') return 'recurrente';
            if ($orig === 'unico') return 'unico';
        }

        return 'unico';
    }

    private function guessPeriodicity(array $snap, array $meta, Collection $items): string
    {
        $mode = strtolower((string) (data_get($snap, 'license.mode') ?? data_get($meta, 'license.mode') ?? ''));
        if (in_array($mode, ['mensual', 'anual'], true)) return $mode;

        foreach ($items as $it) {
            $im = $this->decodeJson($it->meta ?? null);
            $p = strtolower((string) (data_get($im, 'periodicity') ?? ''));
            if (in_array($p, ['mensual', 'anual', 'unico'], true)) return $p;
        }

        return 'unico';
    }

    private function decodeJson(mixed $raw): array
    {
        if ($raw === null || $raw === '') return [];
        if (is_array($raw)) return $raw;

        $s = (string) $raw;
        $j = json_decode($s, true);
        return is_array($j) ? $j : [];
    }

    private function monthNameEs(int $m): string
    {
        return match ($m) {
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
            default => '',
        };
    }

    private function buildDescriptionFromItems(Collection $items, string $origin, string $periodicity): string
    {
        $origin = strtolower($origin);
        $periodicity = strtolower($periodicity);

        if ($items->isEmpty()) {
            if ($origin === 'recurrente') {
                return $periodicity === 'anual' ? 'Recurrente Anual' : 'Recurrente Mensual';
            }
            return 'Venta Única';
        }

        $parts = $items
            ->map(function ($it) {
                $d = trim((string) ($it->description ?? ''));
                if ($d === '') $d = trim((string) ($it->code ?? ''));
                return $d;
            })
            ->filter()
            ->unique()
            ->values()
            ->take(3)
            ->all();

        $txt = implode(' · ', $parts);
        if ($txt === '') {
            if ($origin === 'recurrente') {
                return $periodicity === 'anual' ? 'Recurrente Anual' : 'Recurrente Mensual';
            }
            return 'Venta Única';
        }

        if ($origin === 'recurrente') {
            $prefix = $periodicity === 'anual' ? 'Recurrente Anual' : 'Recurrente Mensual';
            return $prefix . ' · ' . $txt;
        }

        return 'Venta Única · ' . $txt;
    }
}