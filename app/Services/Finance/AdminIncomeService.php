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

        // nuevo: filtro vendedor
        $vendorId = (string) ($req->input('vendor_id') ?: 'all'); // all | <id>

        $qSearch = trim((string) ($req->input('q') ?: ''));

        $periodFrom = Carbon::create($year, 1, 1)->startOfMonth();
        $periodTo   = Carbon::create($year, 12, 1)->endOfMonth();

        if (!Schema::connection($adm)->hasTable('billing_statements')) {
            return [
                'filters' => compact('year', 'month', 'origin', 'st', 'invSt', 'vendorId', 'qSearch'),
                'kpis'    => $this->blankKpis(),
                'rows'    => collect(),
            ];
        }

        // -------------------------
        // Periodos del año
        // -------------------------
        $periodsYear = [];
        for ($m = 1; $m <= 12; $m++) $periodsYear[] = sprintf('%04d-%02d', $year, $m);

        if ($month !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            $periodsYear = [sprintf('%04d-%s', $year, $month)];
        }

        // -------------------------
        // Vendors (catálogo)
        // -------------------------
        $vendorsById = collect();
        if (Schema::connection($adm)->hasTable('finance_vendors')) {
            $vendorsById = collect(DB::connection($adm)->table('finance_vendors')
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get())
                ->keyBy(fn ($v) => (string) $v->id);
        }

        // -------------------------
        // Statements existentes (año / mes)
        // -------------------------
        $statementsAllYearQ = DB::connection($adm)->table('billing_statements as bs')
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

        $statementsAllYear = collect($statementsAllYearQ->orderBy('bs.period')->orderBy('bs.id')->get());

        $statementsFiltered = $statementsAllYear;
        if ($month !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            $statementsFiltered = $statementsAllYear->where('period', '=', sprintf('%04d-%s', $year, $month))->values();
        }

        $statements = $statementsFiltered;

        $statementKeySet = collect();
        foreach ($statementsAllYear as $s) {
            $acc = (string) $s->account_id;
            $per = (string) $s->period;
            if ($acc !== '' && $per !== '') $statementKeySet->push($acc . '|' . $per);
        }

        $statementIds = $statementsAllYear->pluck('id')->filter()->values()->all();

        // -------------------------
        // Items por statement
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
        // Invoices
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
        // Billing profiles
        // -------------------------
        $profilesByAccountId = collect();
        $profilesByAdminAcc  = collect();

        if (Schema::connection($adm)->hasTable('finance_billing_profiles')) {
            $qProfiles = DB::connection($adm)->table('finance_billing_profiles')
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
                ]);

            if (Schema::connection($adm)->hasColumn('finance_billing_profiles', 'admin_account_id')) {
                $qProfiles->addSelect('admin_account_id');
            }

            $profilesRaw = collect($qProfiles->get());

            $profilesByAccountId = $profilesRaw
                ->filter(fn ($r) => !empty($r->account_id))
                ->keyBy(fn ($r) => (string) $r->account_id);

            if ($profilesRaw->first() && property_exists($profilesRaw->first(), 'admin_account_id')) {
                $profilesByAdminAcc = $profilesRaw
                    ->filter(fn ($r) => !empty($r->admin_account_id))
                    ->keyBy(fn ($r) => (string) $r->admin_account_id);
            }
        }

        // -------------------------
        // Cuentas cliente (mysql_clientes)
        // -------------------------
        $hasActivo     = Schema::connection($cli)->hasColumn('cuentas_cliente', 'activo');
        $hasIsBlocked  = Schema::connection($cli)->hasColumn('cuentas_cliente', 'is_blocked');
        $hasEmail      = Schema::connection($cli)->hasColumn('cuentas_cliente', 'email');
        $hasTelefono   = Schema::connection($cli)->hasColumn('cuentas_cliente', 'telefono');
        $hasNextInv    = Schema::connection($cli)->hasColumn('cuentas_cliente', 'next_invoice_date');
        $hasCreatedAt  = Schema::connection($cli)->hasColumn('cuentas_cliente', 'created_at');
        $hasMeta       = Schema::connection($cli)->hasColumn('cuentas_cliente', 'meta');

        $accSelect = [
            'id',
            'admin_account_id',
            'rfc',
            'rfc_padre',
            'razon_social',
            'nombre_comercial',
            'plan_actual',
            'modo_cobro',
            'estado_cuenta',
        ];

        $accSelect[] = $hasActivo ? 'activo' : DB::raw('1 as activo');
        $accSelect[] = $hasIsBlocked ? 'is_blocked' : DB::raw('0 as is_blocked');

        if ($hasEmail)     $accSelect[] = 'email';
        if ($hasTelefono)  $accSelect[] = 'telefono';
        if ($hasNextInv)   $accSelect[] = 'next_invoice_date';
        if ($hasCreatedAt) $accSelect[] = 'created_at';
        if ($hasMeta)      $accSelect[] = 'meta';

        $qAcc = DB::connection($cli)->table('cuentas_cliente')->select($accSelect);

        if (!empty($uuidIds)) $qAcc->whereIn('id', $uuidIds);
        if (!empty($numIds))  $qAcc->orWhereIn('admin_account_id', $numIds);

        $cuentas = collect($qAcc->get())->map(function ($c) use ($hasEmail, $hasTelefono, $hasNextInv) {
            // Normaliza para que el resto del servicio no truene por propiedades faltantes
            if (!$hasEmail && !property_exists($c, 'email')) $c->email = null;
            if (!$hasTelefono && !property_exists($c, 'telefono')) $c->telefono = null;
            if (!$hasNextInv && !property_exists($c, 'next_invoice_date')) $c->next_invoice_date = null;
            return $c;
        });

        $cuentaByUuid    = $cuentas->keyBy(fn ($c) => (string) $c->id);
        $cuentaByAdminId = $cuentas->filter(fn ($c) => !empty($c->admin_account_id))
            ->keyBy(fn ($c) => (string) $c->admin_account_id);

        foreach ($statementsAllYear as $s) {
            $acc = (string) $s->account_id;
            $per = (string) $s->period;
            if ($acc === '' || $per === '') continue;

            if (preg_match('/^[0-9a-f\-]{36}$/i', $acc)) {
                $cc = $cuentaByUuid->get($acc);
                if ($cc && !empty($cc->admin_account_id)) {
                    $statementKeySet->push((string) $cc->admin_account_id . '|' . $per);
                }
            }

            if (preg_match('/^\d+$/', $acc)) {
                $cc = $cuentaByAdminId->get($acc);
                if ($cc && !empty($cc->id)) {
                    $statementKeySet->push((string) $cc->id . '|' . $per);
                }
            }
        }
        $statementKeySet = $statementKeySet->unique()->values();

        // -------------------------
        // Payments (admin.payments)
        // -------------------------
        $paymentsAggByAdminAccPeriod = collect(); // key: "admin|YYYY-MM"
        $lastPaymentByAdminAcc       = collect();

        if (Schema::connection($adm)->hasTable('payments')) {

            $adminIds = $cuentas->pluck('admin_account_id')->filter()->unique()->values()->all();

            if (!empty($adminIds)) {

                $winFrom = Carbon::create($year, 1, 1)->startOfMonth()->subMonths(18);
                $winTo   = Carbon::create($year, 12, 1)->endOfMonth()->addMonths(3);

                $payRows = collect(DB::connection($adm)->table('payments')
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
                    ->where(function ($w) use ($winFrom, $winTo) {
                        $w->whereBetween('paid_at', [$winFrom->toDateTimeString(), $winTo->toDateTimeString()])
                          ->orWhereBetween('created_at', [$winFrom->toDateTimeString(), $winTo->toDateTimeString()]);
                    })
                    ->orderBy('id', 'desc')
                    ->get());

                $paymentsAggByAdminAccPeriod = $payRows
                    ->filter(fn ($p) => in_array((string)($p->period ?? ''), $periodsYear, true))
                    ->groupBy(fn ($p) => (string) $p->account_id . '|' . (string) $p->period)
                    ->map(function ($g) {
                        $g = collect($g);

                        $sumCents = (float) $g->sum(fn ($x) => (float) ($x->amount ?? 0));
                        $maxPaidAt = $g->max(fn ($x) => $x->paid_at ?: null);
                        $anyPaid = $g->contains(function ($x) {
                            $st = strtolower((string) ($x->status ?? ''));
                            return $st === 'paid' || !empty($x->paid_at);
                        });

                        $latest = $g->sortByDesc(fn ($x) => (int) ($x->id ?? 0))->first();

                        return (object) [
                            'sum_amount_cents' => $sumCents,
                            'sum_amount_mxn'   => round($sumCents / 100, 2),
                            'paid_at'          => $maxPaidAt,
                            'any_paid'         => $anyPaid ? 1 : 0,
                            'method'           => $latest?->method ?: null,
                            'provider'         => $latest?->provider ?: null,
                            'status'           => $latest?->status ?: null,
                            'latest_id'        => $latest?->id ?: null,
                        ];
                    });

                $bad = ['failed','canceled','cancelled','void','refunded','chargeback'];
                $lastPaymentByAdminAcc = $payRows
                    ->groupBy(fn ($p) => (string) $p->account_id)
                    ->map(function ($g) use ($bad) {
                        $g2 = collect($g)->filter(function ($p) use ($bad) {
                            $amt = (float) ($p->amount ?? 0);
                            $st  = strtolower((string) ($p->status ?? ''));
                            if ($amt <= 0) return false;
                            if ($st !== '' && in_array($st, $bad, true)) return false;
                            return true;
                        });

                        return $g2->sortByDesc(fn ($x) => (int) $x->id)->first()
                            ?: collect($g)->sortByDesc(fn ($x) => (int) $x->id)->first();
                    });
            }
        }

        // -------------------------
        // ✅ Baseline recurrente robusto (DEBE USAR ALL YEAR, no el filtrado)
        // -------------------------
        $planPrice = function(?string $plan, ?string $modo) : float {
            $plan = strtolower(trim((string) $plan));
            $modo = strtolower(trim((string) $modo));

            $map = [
                'free'       => ['mensual' => 0.0,   'anual' => 0.0],
                'basic'      => ['mensual' => 580.0, 'anual' => 5800.0],
                'pro'        => ['mensual' => 980.0, 'anual' => 9800.0],
                'enterprise' => ['mensual' => 1980.0,'anual' => 19800.0],
            ];

            if (!isset($map[$plan])) return 0.0;
            if (!isset($map[$plan][$modo])) return 0.0;

            return (float) $map[$plan][$modo];
        };

        $baselineRecurring = [];
        foreach ($statementsAllYear as $s) {
            $its  = collect($itemsByStatement->get($s->id, collect()));
            $snap = $this->decodeJson($s->snapshot);
            $meta = $this->decodeJson($s->meta);

            $originGuess = $this->guessOrigin($its, $snap, $meta);
            if ($originGuess !== 'recurrente') continue;

            $accKey = (string) $s->account_id;

            // =========================
            // Subtotal robusto baseline
            // =========================
            $subtotalItems = (float) $its->sum(fn ($it) => (float) ($it->amount ?? 0));
            $subtotal = $subtotalItems;

            $totalCargo = (float) ($s->total_cargo ?? 0);
            if ($subtotal <= 0 && $totalCargo > 0) {
                $subtotal = round($totalCargo / 1.16, 2);
            }

            if ($subtotal <= 0) {
                $snapSubtotal =
                    (float) (data_get($snap, 'totals.subtotal') ?? 0) ?:
                    (float) (data_get($snap, 'statement.subtotal') ?? 0) ?:
                    (float) (data_get($snap, 'subtotal') ?? 0) ?:
                    (float) (data_get($meta, 'totals.subtotal') ?? 0) ?:
                    (float) (data_get($meta, 'statement.subtotal') ?? 0) ?:
                    (float) (data_get($meta, 'subtotal') ?? 0);

                if ($snapSubtotal > 0) {
                    $subtotal = round($snapSubtotal, 2);
                } else {
                    $snapTotal =
                        (float) (data_get($snap, 'totals.total') ?? 0) ?:
                        (float) (data_get($snap, 'statement.total') ?? 0) ?:
                        (float) (data_get($snap, 'total') ?? 0) ?:
                        (float) (data_get($meta, 'totals.total') ?? 0) ?:
                        (float) (data_get($meta, 'statement.total') ?? 0) ?:
                        (float) (data_get($meta, 'total') ?? 0);

                    if ($snapTotal > 0) {
                        $subtotal = round($snapTotal / 1.16, 2);
                    }
                }
            }

            if ($subtotal > 0) {
                $baselineRecurring[$accKey] = [
                    'period'   => (string) $s->period,
                    'subtotal' => $subtotal,
                ];

                // espejo para admin_account_id si account_id es UUID
                if (preg_match('/^[0-9a-f\-]{36}$/i', $accKey)) {
                    $cc = $cuentaByUuid->get($accKey);
                    if ($cc && !empty($cc->admin_account_id)) {
                        $baselineRecurring[(string)$cc->admin_account_id] = [
                            'period'   => (string) $s->period,
                            'subtotal' => $subtotal,
                        ];
                    }
                }
            }
        }

        // -------------------------
        // ✅ Vendor assignment fallback (finance_account_vendor)
        // -------------------------
        $vendorAssignByAdminAcc = collect();
        if (Schema::connection($adm)->hasTable('finance_account_vendor') && $cuentas->isNotEmpty()) {

            $adminIds = $cuentas->pluck('admin_account_id')->filter()->unique()->values()->all();

            if (!empty($adminIds)) {
                $assignRows = DB::connection($adm)->table('finance_account_vendor as fav')
                    ->leftJoin('finance_vendors as v', 'v.id', '=', 'fav.vendor_id')
                    ->select([
                        'fav.id',
                        'fav.account_id',
                        'fav.client_uuid',
                        'fav.vendor_id',
                        'fav.starts_on',
                        'fav.ends_on',
                        'fav.is_primary',
                        'v.name as vendor_name',
                        'fav.created_at',
                    ])
                    ->whereIn('fav.account_id', $adminIds)
                    ->orderByDesc('fav.is_primary')
                    ->orderByDesc('fav.starts_on')
                    ->orderByDesc('fav.id')
                    ->get();

                $best = [];
                foreach ($assignRows as $a) {
                    $aid = (string) $a->account_id;
                    if (!isset($best[$aid])) { $best[$aid] = $a; continue; }

                    $cur = $best[$aid];
                    $curPrimary = (int) ($cur->is_primary ?? 0);
                    $newPrimary = (int) ($a->is_primary ?? 0);

                    if ($newPrimary > $curPrimary) { $best[$aid] = $a; continue; }

                    $curStart = (string) ($cur->starts_on ?? '');
                    $newStart = (string) ($a->starts_on ?? '');
                    if ($newStart !== '' && ($curStart === '' || $newStart > $curStart)) {
                        $best[$aid] = $a; continue;
                    }
                }

                $vendorAssignByAdminAcc = collect($best);
            }
        }

        // -------------------------
        // 1) Filas de statements existentes
        // -------------------------
        $rowsExisting = $statements->map(function ($s) use (
                $itemsByStatement,
                $invByStatement,
                $invByAccPeriod,
                $profilesByAccountId,
                $profilesByAdminAcc,
                $cuentaByUuid,
                $cuentaByAdminId,
                $paymentsAggByAdminAccPeriod,
                $vendorsById,
                $vendorAssignByAdminAcc,
                $baselineRecurring,
                $lastPaymentByAdminAcc,
                $planPrice
            ) {

            $snap = $this->decodeJson($s->snapshot);
            $meta = $this->decodeJson($s->meta);

            $cc = null;
            $sid = (string) $s->account_id;

            if (preg_match('/^[0-9a-f\-]{36}$/i', $sid)) {
                $cc = $cuentaByUuid->get($sid);
            } elseif (preg_match('/^\d+$/', $sid)) {
                $cc = $cuentaByAdminId->get($sid);
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

            // =========================
            // Subtotal/IVA/Total robusto
            // =========================
            $subtotalItems = (float) $its->sum(fn ($it) => (float) ($it->amount ?? 0));
            $subtotal = $subtotalItems;

            // (1) Fallback: total_cargo en statement
            $totalCargo = (float) ($s->total_cargo ?? 0);
            if ($subtotal <= 0 && $totalCargo > 0) {
                $subtotal = round($totalCargo / 1.16, 2);
            }

            // (2) Fallback: snapshot/meta (legacy: guardan el monto ahí)
            if ($subtotal <= 0) {
                $snapSubtotal =
                    (float) (data_get($snap, 'totals.subtotal') ?? 0) ?:
                    (float) (data_get($snap, 'statement.subtotal') ?? 0) ?:
                    (float) (data_get($snap, 'subtotal') ?? 0) ?:
                    (float) (data_get($meta, 'totals.subtotal') ?? 0) ?:
                    (float) (data_get($meta, 'statement.subtotal') ?? 0) ?:
                    (float) (data_get($meta, 'subtotal') ?? 0);

                if ($snapSubtotal > 0) {
                    $subtotal = round($snapSubtotal, 2);
                } else {
                    $snapTotal =
                        (float) (data_get($snap, 'totals.total') ?? 0) ?:
                        (float) (data_get($snap, 'statement.total') ?? 0) ?:
                        (float) (data_get($snap, 'total') ?? 0) ?:
                        (float) (data_get($meta, 'totals.total') ?? 0) ?:
                        (float) (data_get($meta, 'statement.total') ?? 0) ?:
                        (float) (data_get($meta, 'total') ?? 0);

                    if ($snapTotal > 0) {
                        $subtotal = round($snapTotal / 1.16, 2);
                    }
                }
            }

            // (3) Fallback recurrente: si es cuenta recurrente y sigue en 0, usa baseline/último pago/plan
            if ($subtotal <= 0) {

                $modoCobro = strtolower((string) ($cc?->modo_cobro ?? ''));
                $isRecurring = in_array($modoCobro, ['mensual', 'anual'], true);

                if ($isRecurring) {

                    // 3.1 baseline por cuenta (uuid o admin_id)
                    $accKey = (string) $s->account_id;
                    $base = (float) (data_get($baselineRecurring, $accKey . '.subtotal') ?? 0.0);

                    if ($base <= 0 && !empty($cc?->admin_account_id)) {
                        $base = (float) (data_get($baselineRecurring, (string)$cc->admin_account_id . '.subtotal') ?? 0.0);
                    }

                    // 3.2 último pago
                    if ($base <= 0 && !empty($cc?->admin_account_id) && $lastPaymentByAdminAcc->has((string)$cc->admin_account_id)) {
                        $lp = $lastPaymentByAdminAcc->get((string)$cc->admin_account_id);
                        $amtCents = (float) ($lp->amount ?? 0);
                        $amtMxn   = $amtCents > 0 ? ($amtCents / 100) : 0.0;
                        if ($amtMxn > 0) $base = round($amtMxn / 1.16, 2);
                    }

                    // 3.3 planPrice
                    if ($base <= 0) {
                        $plan = strtolower((string) ($cc?->plan_actual ?? ''));
                        $base = (float) $planPrice($plan, $modoCobro);
                    }

                    // si es free => se queda en 0 (correcto)
                    $subtotal = round(max(0, $base), 2);
                }
            }

            $iva   = round($subtotal * 0.16, 2);
            $total = round($subtotal + $iva, 2);

            $origin = $this->guessOrigin($its, $snap, $meta);

            $periodicity = $this->guessPeriodicity($snap, $meta, $its);
            $modoCobro = strtolower((string) ($cc?->modo_cobro ?? ''));
            if (in_array($modoCobro, ['mensual', 'anual'], true)) {
                $periodicity = $modoCobro;
                $origin = 'recurrente';
            }
            if ($origin === 'recurrente' && $periodicity === 'unico') $periodicity = 'mensual';

            $ecStatus = $this->normalizeStatementStatus($s);

            $vendorId = $this->extractVendorId($meta, $snap, $its);
            $vendorName = null;

            if (!empty($vendorId) && $vendorsById->has($vendorId)) {
                $vendorName = (string) ($vendorsById->get($vendorId)?->name ?? null);
            }

            if ((empty($vendorId) || !$vendorName) && !empty($cc?->admin_account_id)) {
                $aid = (string) $cc->admin_account_id;
                $as  = $vendorAssignByAdminAcc->get($aid);

                if ($as) {
                    $per = (string) $s->period;
                    $perDate = $per !== '' ? ($per . '-01') : null;

                    $ok = true;
                    if ($perDate) {
                        $stOn = (string) ($as->starts_on ?? '');
                        $enOn = (string) ($as->ends_on ?? '');

                        if ($stOn !== '' && $perDate < $stOn) $ok = false;
                        if ($enOn !== '' && $perDate > $enOn) $ok = false;
                    }

                    if ($ok) {
                        $vendorId   = (string) ($as->vendor_id ?? $vendorId);
                        $vendorName = (string) ($as->vendor_name ?? $vendorName);
                    }
                }
            }

            $bp = $this->resolveBillingProfile(
                (string) $s->account_id,
                (string) ($cc?->admin_account_id ?? ''),
                $profilesByAccountId,
                $profilesByAdminAcc
            );

            $rfcReceptor = (string) ($bp->rfc_receptor ?? '');
            $formaPago   = (string) ($bp->forma_pago ?? '');

            $invRow = optional($invByStatement->get($s->id))->first();
            if (!$invRow) {
                $invRow = optional($invByAccPeriod->get((string)$s->account_id . '|' . (string)$s->period))->first();
            }

            $invStatus   = $invRow?->status ? (string) $invRow->status : null;
            $invoiceDate = $invRow?->issued_at ?: null;
            $cfdiUuid    = $invRow?->cfdi_uuid ?: null;

            $invMeta = $this->decodeJson($invRow?->meta ?? null);
            $invoiceFormaPago  = (string) (data_get($invMeta, 'forma_pago') ?? data_get($invMeta, 'cfdi.forma_pago') ?? '');
            $invoiceMetodoPago = (string) (data_get($invMeta, 'metodo_pago') ?? data_get($invMeta, 'cfdi.metodo_pago') ?? '');
            $invoicePaidAt     = data_get($invMeta, 'paid_at') ?? data_get($invMeta, 'fecha_pago') ?? null;

            $adminAccId = $cc?->admin_account_id;
            $pAgg = null;
            if (!empty($adminAccId)) {
                $pAgg = $paymentsAggByAdminAccPeriod->get((string)$adminAccId . '|' . (string)$s->period);
            }

            $paidAt = $pAgg?->paid_at ?: $s->paid_at ?: $invoicePaidAt ?: null;

            $ym = (string) $s->period;
            $y  = (int) substr($ym, 0, 4);
            $m  = (int) substr($ym, 5, 2);

            $desc = $this->buildDescriptionFromItems($its, $origin, $periodicity);

            return (object) [
                'source'       => 'statement',
                'is_projection'=> 0,

                'year'         => $y,
                'month_num'    => sprintf('%02d', $m),
                'month_name'   => $this->monthNameEs($m),

                'vendor_id'    => $vendorId,
                'vendor'       => $vendorName,

                'client'       => $company,
                'description'  => $desc,

                'period'       => $ym,
                'account_id'   => (string) $s->account_id,

                'company'      => $company,
                'rfc_emisor'   => $rfcEmisor,

                'origin'       => $origin,
                'periodicity'  => $periodicity,

                'subtotal'     => $subtotal,
                'iva'          => $iva,
                'total'        => $total,

                'ec_status'    => $ecStatus,
                'due_date'     => $s->due_date,
                'sent_at'      => $s->sent_at,
                'paid_at'      => $paidAt,

                'rfc_receptor' => $rfcReceptor,
                'forma_pago'   => $formaPago !== '' ? $formaPago : ($invoiceFormaPago ?: ''),

                'f_emision'    => $s->sent_at,
                'f_pago'       => $paidAt,
                'f_cta'        => $s->sent_at,
                'f_mov'        => null,

                'f_factura'      => $invoiceDate,
                'invoice_date'   => $invoiceDate,
                'invoice_status' => $invStatus,
                'invoice_status_raw' => $invStatus,
                'cfdi_uuid'      => $cfdiUuid,

                'invoice_metodo_pago' => $invoiceMetodoPago !== '' ? $invoiceMetodoPago : null,

                'payment_method' => $pAgg?->method ?: null,
                'payment_status' => $pAgg?->status ?: null,

                'raw_statement_status' => (string) $s->status,
            ];
        });

        // -------------------------
        // 2) PROYECCIÓN recurrente (pending) si no hay statement
        // -------------------------
        $rowsProjected = collect();

        if (Schema::connection($cli)->hasTable('cuentas_cliente')) {

                // ✅ Compat: columnas opcionales en cuentas_cliente (PROD puede variar)
                $hasActivo     = Schema::connection($cli)->hasColumn('cuentas_cliente', 'activo');
                $hasIsBlocked  = Schema::connection($cli)->hasColumn('cuentas_cliente', 'is_blocked');
                $hasNextInv    = Schema::connection($cli)->hasColumn('cuentas_cliente', 'next_invoice_date');
                $hasCreatedAt  = Schema::connection($cli)->hasColumn('cuentas_cliente', 'created_at');
                $hasMeta       = Schema::connection($cli)->hasColumn('cuentas_cliente', 'meta');

                $recSelect = [
                    'id',
                    'admin_account_id',
                    'rfc',
                    'rfc_padre',
                    'razon_social',
                    'nombre_comercial',
                    'plan_actual',
                    'modo_cobro',
                    'estado_cuenta',
                ];

                $recSelect[] = $hasActivo ? 'activo' : DB::raw('1 as activo');
                $recSelect[] = $hasIsBlocked ? 'is_blocked' : DB::raw('0 as is_blocked');

                if ($hasNextInv)   $recSelect[] = 'next_invoice_date';
                if ($hasCreatedAt) $recSelect[] = 'created_at';
                if ($hasMeta)      $recSelect[] = 'meta';

                $recQ = DB::connection($cli)->table('cuentas_cliente')
                    ->select($recSelect)
                    ->whereIn('modo_cobro', ['mensual', 'anual']);

                if ($hasActivo) {
                    $recQ->where('activo', '=', 1);
                }

                $rec = collect($recQ->get())->map(function ($cc) use ($hasNextInv, $hasCreatedAt, $hasMeta) {
                    // Normaliza props para evitar undefined property en el resto del flujo
                    if (!$hasNextInv   && !property_exists($cc, 'next_invoice_date')) $cc->next_invoice_date = null;
                    if (!$hasCreatedAt && !property_exists($cc, 'created_at'))        $cc->created_at = null;
                    if (!$hasMeta      && !property_exists($cc, 'meta'))              $cc->meta = null;
                    return $cc;
                });

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

                        $keyUuid = (string) $cc->id . '|' . (string) $per;
                        $keyAdm  = !empty($cc->admin_account_id) ? ((string) $cc->admin_account_id . '|' . (string) $per) : null;

                        if ($statementKeySet->contains($keyUuid)) continue;
                        if ($keyAdm && $statementKeySet->contains($keyAdm)) continue;

                        $base = (float) (data_get($baselineRecurring, (string)$cc->id . '.subtotal') ?? 0.0);
                        if ($base <= 0 && !empty($cc->admin_account_id)) {
                            $base = (float) (data_get($baselineRecurring, (string)$cc->admin_account_id . '.subtotal') ?? 0.0);
                        }

                        if ($base <= 0 && !empty($cc->admin_account_id) && $lastPaymentByAdminAcc->has((string)$cc->admin_account_id)) {
                            $lp  = $lastPaymentByAdminAcc->get((string)$cc->admin_account_id);
                            $amtCents = (float) ($lp->amount ?? 0);
                            $amtMxn = $amtCents > 0 ? ($amtCents / 100) : 0.0;
                            if ($amtMxn > 0) $base = round($amtMxn / 1.16, 2);
                        }

                        if ($base <= 0) {
                            $plan = (string) ($cc->plan_actual ?? '');
                            $base = (float) $planPrice($plan, $modo);
                        }

                        $subtotal = round(max(0, $base), 2);
                        $iva      = round($subtotal * 0.16, 2);
                        $total    = round($subtotal + $iva, 2);

                        $company = (string) (($cc->nombre_comercial ?: null) ?? ($cc->razon_social ?: null) ?? ('Cuenta ' . $cc->id));
                        $rfcEmisor = (string) (($cc->rfc_padre ?: null) ?? ($cc->rfc ?: null) ?? '');

                        $bp = $this->resolveBillingProfile(
                            (string) $cc->id,
                            (string) ($cc->admin_account_id ?? ''),
                            $profilesByAccountId,
                            $profilesByAdminAcc
                        );
                        $rfcReceptor = (string) ($bp->rfc_receptor ?? '');
                        $formaPago   = (string) ($bp->forma_pago ?? '');

                        $invRow = optional($invByAccPeriod->get((string)$cc->id . '|' . (string)$per))->first();
                        $invStatus   = $invRow?->status ? (string)$invRow->status : null;
                        $invoiceDate = $invRow?->issued_at ?: null;
                        $cfdiUuid    = $invRow?->cfdi_uuid ?: null;

                        $invMeta = $this->decodeJson($invRow?->meta ?? null);
                        $invoiceFormaPago = (string) (data_get($invMeta, 'forma_pago') ?? data_get($invMeta, 'cfdi.forma_pago') ?? '');

                        $pAgg = null;
                        if (!empty($cc->admin_account_id)) {
                            $pAgg = $paymentsAggByAdminAccPeriod->get((string)$cc->admin_account_id . '|' . (string)$per);
                        }

                        $ecStatus = 'pending';
                        if (($pAgg?->any_paid ?? 0) === 1 || !empty($pAgg?->paid_at)) {
                            $ecStatus = 'pagado';
                        }

                        $y = (int) substr($per, 0, 4);
                        $m = (int) substr($per, 5, 2);

                        $rowsProjected->push((object) [
                            'source'        => 'projection',
                            'is_projection' => 1,

                            'year'         => $y,
                            'month_num'    => sprintf('%02d', $m),
                            'month_name'   => $this->monthNameEs($m),

                            'vendor_id'    => null,
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
                            'paid_at'      => $pAgg?->paid_at ?: null,

                            'rfc_receptor' => $rfcReceptor,
                            'forma_pago'   => $formaPago !== '' ? $formaPago : ($invoiceFormaPago ?: ''),

                            'f_emision'    => null,
                            'f_pago'       => $pAgg?->paid_at ?: null,
                            'f_cta'        => null,
                            'f_mov'        => null,

                            'f_factura'      => $invoiceDate,
                            'invoice_date'   => $invoiceDate,
                            'invoice_status' => $invStatus,
                            'invoice_status_raw' => $invStatus,
                            'cfdi_uuid'      => $cfdiUuid,

                            'payment_method' => $pAgg?->method ?: null,
                            'payment_status' => $pAgg?->status ?: null,
                        ]);
                    }
                }
            }

        // -------------------------
        // 3) Ventas únicas (finance_sales)
        // -------------------------
        $rowsSales = collect();

        if (Schema::connection($adm)->hasTable('finance_sales')) {

            // ✅ Compat de columnas entre versiones
            $fsCols = collect(Schema::connection($adm)->getColumnListing('finance_sales'))->map(fn($c)=>strtolower((string)$c))->values();

            $colPeriod = $fsCols->contains('period')
                ? 'period'
                : ($fsCols->contains('target_period') ? 'target_period' : null);

            $colStmtTarget = $fsCols->contains('statement_period_target')
                ? 'statement_period_target'
                : ($fsCols->contains('target_period') ? 'target_period' : null);

            $qSales = DB::connection($adm)->table('finance_sales as s')
                ->leftJoin('finance_vendors as v', 'v.id', '=', 's.vendor_id');

            // SELECT base (solo columnas que “seguro” existen)
            $qSales->select([
                's.id',
                's.account_id',
                's.sale_code',
                's.receiver_rfc',
                's.pay_method',
                's.origin',
                's.periodicity',
                's.vendor_id',
                'v.name as vendor_name',
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
                's.notes',
                's.created_at',
            ]);

            // ✅ period con alias (si no existe, lo dejamos null y filtramos distinto)
            if ($colPeriod) {
                $qSales->addSelect(DB::raw("s.`{$colPeriod}` as period"));
            } else {
                // fallback duro: intenta derivar periodo por sale_date / created_at (YYYY-MM)
                $qSales->addSelect(DB::raw("DATE_FORMAT(COALESCE(s.sale_date, s.created_at), '%Y-%m') as period"));
            }

            // ✅ statement_period_target con alias (compat)
            if ($colStmtTarget) {
                $qSales->addSelect(DB::raw("s.`{$colStmtTarget}` as statement_period_target"));
            } else {
                $qSales->addSelect(DB::raw("NULL as statement_period_target"));
            }

            // Filtro de año/mes
            $qSales->whereIn('period', $periodsYear);

            // Solo ventas únicas (origen unico / no_recurrente o periodicity unico)
            $qSales->where(function ($w) {
                $w->whereIn('s.origin', ['unico', 'no_recurrente'])
                ->orWhere('s.periodicity', '=', 'unico');
            });

            $sales = collect($qSales->orderBy('period')->orderBy('s.id')->get());

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

                $invRaw = strtolower((string) ($s->invoice_status ?? ''));
                $invCanonical = $this->mapSalesInvoiceStatusToCanonical($invRaw);

                $desc = trim((string) ($s->notes ?? ''));
                if ($desc === '') {
                    $desc = trim((string) ($s->sale_code ?? 'Venta única'));
                } else {
                    $sc = trim((string) ($s->sale_code ?? ''));
                    if ($sc !== '') $desc = $sc . ' · ' . $desc;
                }

                $fMov = $s->f_mov ?: ($s->sale_date ?: ($s->created_at ?: null));
                $vendorId = !empty($s->vendor_id) ? (string) $s->vendor_id : null;

                return (object) [
                    'source'        => 'sale',
                    'is_projection' => 0,

                    'year'         => $y,
                    'month_num'    => sprintf('%02d', $m),
                    'month_name'   => $this->monthNameEs($m),

                    'vendor_id'    => $vendorId,
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

                    'rfc_receptor' => (string) ($s->receiver_rfc ?? ''),
                    'forma_pago'   => (string) ($s->pay_method ?? ''),

                    'f_emision'    => $s->f_cta ?: null,
                    'f_pago'       => $s->paid_date ?: null,
                    'f_cta'        => $s->f_cta ?: null,
                    'f_mov'        => $fMov,

                    'f_factura'      => $s->invoice_date ?: null,
                    'invoice_date'   => $s->invoice_date ?: null,
                    'invoice_status' => $invCanonical,
                    'invoice_status_raw' => $invRaw,
                    'cfdi_uuid'      => $s->cfdi_uuid ?: null,

                    'payment_method' => null,
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

        $rows = $rows->filter(function ($r) use ($originNorm, $st, $invSt, $vendorId, $qSearch) {

            if ($originNorm !== 'all' && strtolower((string)$r->origin) !== $originNorm) return false;
            if ($st !== 'all' && strtolower((string)$r->ec_status) !== strtolower($st)) return false;

            if ($invSt !== 'all') {
                $cmp = strtolower((string) ($r->invoice_status ?? ''));
                if ($cmp !== strtolower($invSt)) return false;
            }

            if ($vendorId !== 'all' && $vendorId !== '') {
                $rid = (string) ($r->vendor_id ?? '');
                if ($rid === '' || $rid !== (string) $vendorId) return false;
            }

            if ($qSearch !== '') {
                $hay = strtolower(
                    ($r->client ?? '') . ' ' .
                    ($r->company ?? '') . ' ' .
                    ($r->account_id ?? '') . ' ' .
                    ($r->rfc_emisor ?? '') . ' ' .
                    ($r->rfc_receptor ?? '') . ' ' .
                    (($r->cfdi_uuid ?? '') . '') . ' ' .
                    (($r->description ?? '') . '') . ' ' .
                    (($r->vendor ?? '') . '') . ' ' .
                    (($r->source ?? '') . '')
                );
                if (!str_contains($hay, strtolower($qSearch))) return false;
            }

            return true;
        });

        $rows = $rows->sortBy([
            fn ($r) => (string) ($r->period ?? ''),
            fn ($r) => (string) ($r->client ?? $r->company ?? ''),
            fn ($r) => -1 * (float) ($r->total ?? 0),
        ])->values();

        $kpis = $this->computeKpis($rows);

        $vendorList = $vendorsById
            ->map(fn ($v) => ['id' => (string) $v->id, 'name' => (string) $v->name])
            ->values();

        return [
            'filters' => compact('year', 'month', 'origin', 'st', 'invSt', 'vendorId', 'qSearch') + [
                'vendor_list' => $vendorList,
            ],
            'kpis'    => $kpis,
            'rows'    => $rows,
        ];
    }

    private function resolveBillingProfile(
        string $accountId,
        string $adminAccountId,
        Collection $profilesByAccountId,
        Collection $profilesByAdminAcc
    ): ?object {
        $bp = $profilesByAccountId->get((string) $accountId);
        if ($bp) return $bp;

        if ($adminAccountId !== '' && $profilesByAdminAcc->isNotEmpty()) {
            $bp = $profilesByAdminAcc->get((string) $adminAccountId);
            if ($bp) return $bp;
        }

        if ($adminAccountId === '' && preg_match('/^\d+$/', $accountId)) {
            $bp = $profilesByAccountId->get((string) $accountId);
            if ($bp) return $bp;
        }

        return null;
    }

    private function extractVendorId(array $meta, array $snap, Collection $items): ?string
    {
        $vid = data_get($meta, 'vendor_id')
            ?? data_get($meta, 'vendor.id')
            ?? data_get($snap, 'vendor_id')
            ?? data_get($snap, 'vendor.id')
            ?? null;

        if (!empty($vid)) return (string) $vid;

        foreach ($items as $it) {
            $im = $this->decodeJson($it->meta ?? null);
            $vid2 = data_get($im, 'vendor_id') ?? data_get($im, 'vendor.id') ?? null;
            if (!empty($vid2)) return (string) $vid2;
        }

        return null;
    }

    private function attachCompanyFromClientes(Collection $rows, string $cliConn): Collection
    {
        $ids = $rows->pluck('account_id')->filter()->unique()->values()->all();
        if (empty($ids)) return $rows;

        if (!Schema::connection($cliConn)->hasTable('cuentas_cliente')) return $rows;

        $uuidIds = [];
        $adminIds = [];

        foreach ($ids as $id) {
            $id = (string) $id;
            if ($id === '') continue;

            if (preg_match('/^[0-9a-f\-]{36}$/i', $id)) {
                $uuidIds[] = $id;
            } elseif (preg_match('/^\d+$/', $id)) {
                $adminIds[] = (int) $id;
            }
        }

        $q = DB::connection($cliConn)->table('cuentas_cliente')
            ->select(['id', 'admin_account_id', 'razon_social', 'nombre_comercial', 'rfc_padre']);

        if (!empty($uuidIds)) $q->whereIn('id', $uuidIds);
        if (!empty($adminIds)) $q->orWhereIn('admin_account_id', $adminIds);

        $found = collect($q->get());

        $byUuid = $found->filter(fn ($c) => !empty($c->id))->keyBy(fn ($c) => (string) $c->id);
        $byAdm  = $found->filter(fn ($c) => !empty($c->admin_account_id))->keyBy(fn ($c) => (string) $c->admin_account_id);

        return $rows->map(function ($r) use ($byUuid, $byAdm) {
            $aid = (string) ($r->account_id ?? '');
            $c = null;

            if ($aid !== '') {
                if (preg_match('/^[0-9a-f\-]{36}$/i', $aid)) {
                    $c = $byUuid->get($aid);
                } elseif (preg_match('/^\d+$/', $aid)) {
                    $c = $byAdm->get($aid);
                }
            }

            $r->company = $c
                ? (string) (($c->nombre_comercial ?: null) ?? ($c->razon_social ?: null) ?? ('Cuenta ' . $aid))
                : (string) ('Cuenta ' . $aid);

            $r->rfc_emisor = $c ? (string) ($c->rfc_padre ?? '') : '';

            return $r;
        });
    }

    private function mapSalesInvoiceStatusToCanonical(string $raw): string
    {
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