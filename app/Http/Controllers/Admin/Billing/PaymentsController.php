<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Services\Admin\Billing\AccountBillingStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PaymentsController extends Controller
{
    private string $adm = 'mysql_admin';

    public function index(Request $req): View
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return view('admin.billing.payments.index', [
                'rows'     => collect(),
                'q'        => '',
                'status'   => '',
                'method'   => '',
                'provider' => '',
                'from'     => '',
                'to'       => '',
                'kpis'     => [],
                'chart'    => [],
                'error'    => 'No existe la tabla payments en p360v1_admin.',
            ]);
        }

        $q        = trim((string) $req->get('q', ''));
        $status   = trim((string) $req->get('status', ''));
        $method   = trim((string) $req->get('method', ''));
        $provider = trim((string) $req->get('provider', ''));
        $from     = trim((string) $req->get('from', ''));
        $to       = trim((string) $req->get('to', ''));

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = fn (string $c): bool => in_array(strtolower($c), $lc, true);

        $qb = DB::connection($this->adm)->table('payments')->orderByDesc('id');

        if ($status !== '') {
            $qb->where('status', $status);
        }

        if ($method !== '' && $has('method')) {
            $qb->where('method', $method);
        }

        if ($provider !== '' && $has('provider')) {
            $qb->where('provider', $provider);
        }

        $dateCol = $has('paid_at') ? 'paid_at' : ($has('created_at') ? 'created_at' : null);
        if ($dateCol) {
            if ($from !== '') {
                $fromC = $this->safeDate($from);
                if ($fromC) {
                    $qb->whereDate($dateCol, '>=', $fromC->toDateString());
                }
            }
            if ($to !== '') {
                $toC = $this->safeDate($to);
                if ($toC) {
                    $qb->whereDate($dateCol, '<=', $toC->toDateString());
                }
            }
        }

        if ($q !== '') {
            $qb->where(function ($w) use ($q, $has) {
                $w->where('account_id', $q);

                if ($has('stripe_session_id')) {
                    $w->orWhere('stripe_session_id', 'like', "%{$q}%");
                }
                if ($has('stripe_payment_intent')) {
                    $w->orWhere('stripe_payment_intent', 'like', "%{$q}%");
                }
                if ($has('stripe_invoice_id')) {
                    $w->orWhere('stripe_invoice_id', 'like', "%{$q}%");
                }
                if ($has('reference')) {
                    $w->orWhere('reference', 'like', "%{$q}%");
                }
            });
        }

        $rows = $qb->paginate(25)->withQueryString();

        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $ids = $rows->pluck('account_id')->filter()->unique()->values()->all();

            $acc = DB::connection($this->adm)->table('accounts')
                ->select('id', 'email', 'rfc', 'razon_social', 'name')
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            $rows->getCollection()->transform(function ($p) use ($acc) {
                $a = $acc[$p->account_id] ?? null;
                $p->account_rfc   = $a->rfc ?? null;
                $p->account_email = $a->email ?? null;
                $p->account_name  = $a->razon_social ?? ($a->name ?? null);
                return $p;
            });
        }

        [$kpis, $chart] = $this->buildKpisAndChart($has);

        $methods = [];
        $providers = [];
        if ($has('method')) {
            $methods = DB::connection($this->adm)->table('payments')
                ->select('method')->whereNotNull('method')->distinct()->orderBy('method')->pluck('method')->all();
        }
        if ($has('provider')) {
            $providers = DB::connection($this->adm)->table('payments')
                ->select('provider')->whereNotNull('provider')->distinct()->orderBy('provider')->pluck('provider')->all();
        }

        return view('admin.billing.payments.index', [
            'rows'      => $rows,
            'q'         => $q,
            'status'    => $status,
            'method'    => $method,
            'provider'  => $provider,
            'from'      => $from,
            'to'        => $to,
            'methods'   => $methods,
            'providers' => $providers,
            'kpis'      => $kpis,
            'chart'     => $chart,
            'error'     => null,
        ]);
    }

    public function manual(Request $req): RedirectResponse
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return back()->withErrors(['payments' => 'No existe la tabla payments.']);
        }

        $data = $req->validate([
            'account_id'           => 'required|integer|min:1',
            'amount_pesos'         => 'required|numeric|min:0.01|max:99999999',
            'currency'             => 'nullable|string|max:10',
            'concept'              => 'nullable|string|max:255',
            'period'               => ['nullable', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'also_apply_statement' => 'nullable|boolean',
        ]);

        $accountId   = (int) $data['account_id'];
        $amountPesos = round((float) $data['amount_pesos'], 2);
        $amountCents = (int) round($amountPesos * 100);

        $currency = strtoupper(trim((string) ($data['currency'] ?? 'MXN')));
        if ($currency === '') {
            $currency = 'MXN';
        }

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = fn (string $c): bool => in_array(strtolower($c), $lc, true);

        $period  = $data['period'] ?? now()->format('Y-m');
        $concept = trim((string) ($data['concept'] ?? '')) ?: 'Pago recibido (manual)';

        $row = [
            'account_id' => $accountId,
            'amount'     => $amountCents,
            'currency'   => $currency,
            'status'     => 'paid',
            'paid_at'    => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($has('due_date')) {
            $row['due_date'] = now();
        }
        if ($has('reference')) {
            $row['reference'] = 'manual:' . now()->format('YmdHis');
        }
        if ($has('provider')) {
            $row['provider'] = 'manual';
        }
        if ($has('method')) {
            $row['method'] = 'transfer';
        }
        if ($has('concept')) {
            $row['concept'] = $concept;
        }
        if ($has('period')) {
            $row['period'] = $period;
        }
        if ($has('amount_mxn')) {
            $row['amount_mxn'] = $amountPesos;
        }
        if ($has('monto_mxn')) {
            $row['monto_mxn'] = $amountPesos;
        }

        if ($has('meta')) {
            $row['meta'] = json_encode([
                'type'                 => 'manual',
                'concept'              => $concept,
                'period'               => $period,
                'also_apply_statement' => (bool) ($data['also_apply_statement'] ?? false),
            ], JSON_UNESCAPED_UNICODE);
        }

        $also = (bool) ($data['also_apply_statement'] ?? false);

        DB::connection($this->adm)->transaction(function () use ($also, $row, $accountId, $period, $concept, $amountPesos) {
            DB::connection($this->adm)->table('payments')->insert($row);

            if ($also) {
                if (!Schema::connection($this->adm)->hasTable('estados_cuenta')) {
                    throw new \RuntimeException('No existe estados_cuenta; no se puede aplicar el abono.');
                }

                DB::connection($this->adm)->table('estados_cuenta')->insert([
                    'account_id' => $accountId,
                    'periodo'    => $period,
                    'concepto'   => $concept,
                    'detalle'    => 'Registro manual en admin (Payments Center)',
                    'cargo'      => 0.00,
                    'abono'      => $amountPesos,
                    'saldo'      => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $items = DB::connection($this->adm)->table('estados_cuenta')
                    ->where('account_id', $accountId)
                    ->where('periodo', '=', $period)
                    ->orderByDesc('id')
                    ->get();

                $saldo = max(0, (float) $items->sum('cargo') - (float) $items->sum('abono'));
                $lastId = (int) ($items->first()->id ?? 0);

                if ($lastId > 0) {
                    DB::connection($this->adm)->table('estados_cuenta')->where('id', $lastId)->update([
                        'saldo'      => round($saldo, 2),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->rebuildBillingStatementForPeriod((string) $accountId, $period);
        });

        AccountBillingStateService::sync($accountId, 'admin.payments.manual');

        return back()->with('ok', 'Pago manual registrado.');
    }

    public function update(Request $req, int $id): RedirectResponse
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return back()->withErrors(['payments' => 'No existe la tabla payments.']);
        }

        $pay = DB::connection($this->adm)->table('payments')->where('id', $id)->first();
        abort_unless($pay, 404);

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = fn (string $c): bool => in_array(strtolower($c), $lc, true);

        $allowedStatuses = ['pending', 'paid', 'failed', 'canceled', 'cancelled', 'refunded'];

        $rules = [
            'amount_pesos' => 'required|numeric|min:0.01|max:99999999',
            'currency'     => 'nullable|string|max:10',
            'status'       => ['required', 'string', Rule::in($allowedStatuses)],
            'paid_at'      => 'nullable|date',
            'period'       => ['nullable', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'concept'      => 'nullable|string|max:255',
            'method'       => 'nullable|string|max:40',
            'provider'     => 'nullable|string|max:60',
            'reference'    => 'nullable|string|max:120',
        ];

        $data = $req->validate($rules);

        $amountPesos = round((float) $data['amount_pesos'], 2);
        $amountCents = (int) round($amountPesos * 100);
        $currency    = strtoupper(trim((string) ($data['currency'] ?? ($pay->currency ?? 'MXN'))));
        if ($currency === '') {
            $currency = 'MXN';
        }

        $upd = [
            'amount'     => $amountCents,
            'currency'   => $currency,
            'status'     => $data['status'],
            'updated_at' => now(),
        ];

        if ($has('amount_mxn')) {
            $upd['amount_mxn'] = $amountPesos;
        }
        if ($has('monto_mxn')) {
            $upd['monto_mxn'] = $amountPesos;
        }
        if ($has('paid_at')) {
            $upd['paid_at'] = ($data['paid_at'] ?? null) ? Carbon::parse((string) $data['paid_at']) : null;
        }
        if ($has('period')) {
            $upd['period'] = $data['period'] ?: null;
        }
        if ($has('concept')) {
            $upd['concept'] = $data['concept'] ?: null;
        }
        if ($has('method')) {
            $upd['method'] = $data['method'] ?: null;
        }
        if ($has('provider')) {
            $upd['provider'] = $data['provider'] ?: null;
        }
        if ($has('reference')) {
            $upd['reference'] = $data['reference'] ?: null;
        }

        DB::connection($this->adm)->transaction(function () use ($id, $upd, $pay, $data) {
            DB::connection($this->adm)->table('payments')->where('id', $id)->update($upd);

            $oldPeriod = trim((string) ($pay->period ?? ''));
            $newPeriod = trim((string) ($data['period'] ?? $oldPeriod));
            $accountId = (string) ($pay->account_id ?? '');

            if ($accountId !== '') {
                if ($oldPeriod !== '' && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $oldPeriod)) {
                    $this->rebuildBillingStatementForPeriod($accountId, $oldPeriod);
                }
                if ($newPeriod !== '' && $newPeriod !== $oldPeriod && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $newPeriod)) {
                    $this->rebuildBillingStatementForPeriod($accountId, $newPeriod);
                } elseif ($newPeriod !== '' && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $newPeriod)) {
                    $this->rebuildBillingStatementForPeriod($accountId, $newPeriod);
                }
            }
        });

        $accountId = (int) ($pay->account_id ?? 0);
        if ($accountId > 0) {
            AccountBillingStateService::sync($accountId, 'admin.payments.update');
        }

        return back()->with('ok', "Pago #{$id} actualizado.");
    }

    public function destroy(Request $req, int $id): RedirectResponse
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return back()->withErrors(['payments' => 'No existe la tabla payments.']);
        }

        $pay = DB::connection($this->adm)->table('payments')->where('id', $id)->first();
        abort_unless($pay, 404);

        DB::connection($this->adm)->transaction(function () use ($id, $pay) {
            DB::connection($this->adm)->table('payments')->where('id', $id)->delete();

            $accountId = trim((string) ($pay->account_id ?? ''));
            $period    = trim((string) ($pay->period ?? ''));

            if ($accountId !== '' && $period !== '' && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
                $this->rebuildBillingStatementForPeriod($accountId, $period);
            }
        });

        $accountId = (int) ($pay->account_id ?? 0);
        if ($accountId > 0) {
            AccountBillingStateService::sync($accountId, 'admin.payments.destroy');
        }

        return back()->with('ok', "Pago #{$id} eliminado. Nota: esto no revierte movimientos en estados_cuenta.");
    }

    public function emailReceipt(Request $req, int $id): RedirectResponse
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return back()->withErrors(['payments' => 'No existe la tabla payments.']);
        }

        $pay = DB::connection($this->adm)->table('payments')->where('id', $id)->first();
        abort_unless($pay, 404);

        $to = trim((string) $req->get('to', ''));

        $acc = null;
        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $acc = DB::connection($this->adm)->table('accounts')->where('id', (int) $pay->account_id)->first();
        }
        if ($to === '') {
            $to = (string) ($acc->email ?? '');
        }

        if ($to === '') {
            return back()->withErrors(['to' => 'No hay correo destino.']);
        }

        $amountPesos = round(((int) ($pay->amount ?? 0)) / 100, 2);

        $data = [
            'payment'      => $pay,
            'account'      => $acc,
            'amount_pesos' => $amountPesos,
            'generated_at' => now(),
        ];

        Mail::send('admin.mail.payment_receipt', $data, function ($m) use ($to, $pay) {
            $m->to($to)->subject('Pactopia360 · Recibo de pago #' . $pay->id);
        });

        return back()->with('ok', 'Recibo reenviado por correo.');
    }

    private function rebuildBillingStatementForPeriod(string $accountId, string $period): void
    {
        $accountId = trim($accountId);
        $period    = trim($period);

        if ($accountId === '' || !preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
            return;
        }

        if (!Schema::connection($this->adm)->hasTable('billing_statements')) {
            return;
        }

        $statement = DB::connection($this->adm)->table('billing_statements')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if (!$statement) {
            return;
        }

        $cargo = round((float) ($statement->total_cargo ?? 0), 2);
        $paid  = $this->sumPaidPaymentsMxnForPeriod($accountId, $period);
        $saldo = round(max(0.0, $cargo - $paid), 2);

        $status = 'pending';
        $paidAt = null;

        if ($cargo <= 0.00001 && $paid <= 0.00001) {
            $status = 'void';
        } elseif ($saldo <= 0.00001 && $paid > 0.00001) {
            $status = 'paid';
            $paidAt = now();
        } elseif ($paid > 0.00001 && $saldo > 0.00001) {
            $status = 'partial';
        }

        DB::connection($this->adm)->table('billing_statements')
            ->where('id', (int) $statement->id)
            ->update([
                'total_abono' => $paid,
                'saldo'       => $saldo,
                'status'      => $status,
                'paid_at'     => $paidAt,
                'updated_at'  => now(),
            ]);
    }

    private function sumPaidPaymentsMxnForPeriod(string $accountId, string $period): float
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return 0.0;
        }

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = fn (string $c): bool => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) {
            return 0.0;
        }

        $q = DB::connection($this->adm)->table('payments')
            ->where('account_id', $accountId)
            ->where(function ($w) use ($period) {
                $w->where('period', $period)
                  ->orWhere('period', 'like', $period . '%');
            });

        if ($has('status')) {
            $q->whereIn(DB::raw('LOWER(status)'), [
                'paid',
                'pagado',
                'succeeded',
                'success',
                'completed',
                'complete',
                'captured',
                'authorized',
                'paid_ok',
                'ok',
            ]);
        } else {
            return 0.0;
        }

        if ($has('amount_mxn')) {
            return round((float) ($q->sum('amount_mxn') ?? 0), 2);
        }

        if ($has('monto_mxn')) {
            return round((float) ($q->sum('monto_mxn') ?? 0), 2);
        }

        if ($has('amount_cents')) {
            return round(((float) ($q->sum('amount_cents') ?? 0)) / 100, 2);
        }

        if ($has('amount')) {
            return round(((float) ($q->sum('amount') ?? 0)) / 100, 2);
        }

        return 0.0;
    }

    private function safeDate(string $v): ?Carbon
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        try {
            return Carbon::parse($v);
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildKpisAndChart(callable $has): array
    {
        $conn = DB::connection($this->adm);

        $dateCol = $has('paid_at') ? 'paid_at' : ($has('created_at') ? 'created_at' : null);

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $last30 = now()->subDays(29)->toDateString();

        $paidQ = $conn->table('payments')->where('status', 'paid');
        if ($dateCol) {
            $paidQ->whereDate($dateCol, '>=', $monthStart);
        }
        $sumMonthCents = (int) ($paidQ->sum('amount') ?? 0);

        $paidTodayQ = $conn->table('payments')->where('status', 'paid');
        if ($dateCol) {
            $paidTodayQ->whereDate($dateCol, '=', $today);
        }
        $sumTodayCents = (int) ($paidTodayQ->sum('amount') ?? 0);

        $pendingCount = (int) $conn->table('payments')->where('status', 'pending')->count();
        $paidCount    = (int) $conn->table('payments')->where('status', 'paid')->count();

        $avgPaidCents = (int) $conn->table('payments')->where('status', 'paid')->avg('amount');

        $labels = [];
        $seriesCents = [];
        $byDay = collect();

        if ($dateCol) {
            $byDay = $conn->table('payments')
                ->selectRaw("DATE({$dateCol}) as d, SUM(amount) as s")
                ->where('status', 'paid')
                ->whereDate($dateCol, '>=', $last30)
                ->groupByRaw("DATE({$dateCol})")
                ->orderBy('d')
                ->get()
                ->keyBy('d');
        }

        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $labels[] = $d;
            $seriesCents[] = (int) (($byDay[$d]->s ?? 0));
        }

        $statusCounts = $conn->table('payments')
            ->selectRaw("status as k, COUNT(*) as c")
            ->groupBy('status')
            ->orderByDesc('c')
            ->get();

        $donutLabels = $statusCounts->pluck('k')->map(fn ($x) => (string) $x)->all();
        $donutValues = $statusCounts->pluck('c')->map(fn ($x) => (int) $x)->all();

        $kpis = [
            'today'    => round($sumTodayCents / 100, 2),
            'month'    => round($sumMonthCents / 100, 2),
            'pending'  => $pendingCount,
            'paid'     => $paidCount,
            'avg_paid' => round($avgPaidCents / 100, 2),
        ];

        $chart = [
            'line' => [
                'labels' => $labels,
                'data'   => array_map(fn ($c) => round($c / 100, 2), $seriesCents),
            ],
            'donut' => [
                'labels' => $donutLabels,
                'data'   => $donutValues,
            ],
        ];

        return [$kpis, $chart];
    }
}