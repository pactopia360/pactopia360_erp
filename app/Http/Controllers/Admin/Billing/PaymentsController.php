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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

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
                ->select('method')
                ->whereNotNull('method')
                ->distinct()
                ->orderBy('method')
                ->pluck('method')
                ->all();
        }

        if ($has('provider')) {
            $providers = DB::connection($this->adm)->table('payments')
                ->select('provider')
                ->whereNotNull('provider')
                ->distinct()
                ->orderBy('provider')
                ->pluck('provider')
                ->all();
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
            'period_to'            => ['nullable', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'also_apply_statement' => 'nullable|boolean',
        ]);

        $accountId   = (int) $data['account_id'];
        $amountPesos = round((float) $data['amount_pesos'], 2);

        $currency = strtoupper(trim((string) ($data['currency'] ?? 'MXN')));
        if ($currency === '') {
            $currency = 'MXN';
        }

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = fn (string $c): bool => in_array(strtolower($c), $lc, true);

        $period   = $data['period'] ?? now()->format('Y-m');
        $periodTo = trim((string) ($data['period_to'] ?? ''));
        $concept  = trim((string) ($data['concept'] ?? '')) ?: 'Pago recibido (manual)';
        $also     = (bool) ($data['also_apply_statement'] ?? false);

        if ($periodTo === '') {
            $periodTo = $period;
        }

        if (!$this->isValidPeriod($period) || !$this->isValidPeriod($periodTo)) {
            return back()->withErrors(['period' => 'El periodo o rango de periodos no es válido.'])->withInput();
        }

        if (strcmp($periodTo, $period) < 0) {
            return back()->withErrors(['period_to' => 'El periodo final no puede ser menor al periodo inicial.'])->withInput();
        }

        if (Schema::connection($this->adm)->hasTable('accounts')) {
            $existsAccount = DB::connection($this->adm)->table('accounts')
                ->where('id', $accountId)
                ->exists();

            if (!$existsAccount) {
                return back()->withErrors(['account_id' => 'La cuenta indicada no existe.'])->withInput();
            }
        }

        $periods = $this->buildPeriodRange($period, $periodTo);
        if (empty($periods)) {
            return back()->withErrors(['period' => 'No se pudo construir el rango de periodos a cubrir.'])->withInput();
        }

        $groupReference = $this->buildManualReference($accountId, $period);
        $affectedPeriods = [];

        DB::connection($this->adm)->transaction(function () use (
            $accountId,
            $amountPesos,
            $currency,
            $concept,
            $also,
            $periods,
            $has,
            $groupReference,
            &$affectedPeriods
        ) {
            $distribution = $this->resolveManualPaymentDistribution(
                $accountId,
                $periods,
                $amountPesos
            );

            if (empty($distribution['rows'])) {
                throw new \RuntimeException('No se pudo distribuir el pago manual entre los periodos indicados.');
            }

            foreach ($distribution['rows'] as $rowData) {
                $periodItem = (string) $rowData['period'];
                $appliedPesos = round((float) $rowData['amount_pesos'], 2);

                if ($appliedPesos <= 0.00001) {
                    continue;
                }

                $insert = $this->buildManualPaymentInsertRow(
                    $accountId,
                    $periodItem,
                    $appliedPesos,
                    $currency,
                    $concept,
                    $has,
                    $groupReference,
                    [
                        'type'                 => 'manual',
                        'source'               => 'admin.payments.manual',
                        'concept'              => $concept,
                        'period'               => $periodItem,
                        'period_from'          => $periods[0],
                        'period_to'            => end($periods),
                        'amount_pesos'         => $appliedPesos,
                        'distribution_group'   => $groupReference,
                        'distribution_kind'    => (string) ($rowData['kind'] ?? 'coverage'),
                        'distribution_due'     => (float) ($rowData['due'] ?? 0),
                        'distribution_notes'   => (string) ($rowData['notes'] ?? ''),
                        'also_apply_statement' => $also,
                        'captured_at'          => now()->toDateTimeString(),
                    ]
                );

                DB::connection($this->adm)->table('payments')->insert($insert);

                if ($also) {
                    $this->applyManualCreditToEstadoCuenta(
                        $accountId,
                        $periodItem,
                        $concept . ' · ' . $periodItem,
                        $appliedPesos
                    );
                }

                $affectedPeriods[] = $periodItem;
            }

            $affectedPeriods = array_values(array_unique(array_filter($affectedPeriods)));

            foreach ($affectedPeriods as $affectedPeriod) {
                $this->rebuildBillingStatementForPeriod((string) $accountId, $affectedPeriod);
            }
        });

        AccountBillingStateService::sync($accountId, 'admin.payments.manual.multi_period');

        $coveredText = count($affectedPeriods) > 1
            ? ('Pago manual registrado y distribuido en ' . count($affectedPeriods) . ' periodos.')
            : 'Pago manual registrado.';

        return back()->with('ok', $coveredText);
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

        $allowedStatuses = [
            'pending',
            'paid',
            'failed',
            'canceled',
            'cancelled',
            'refunded',
        ];

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

        $newStatus = strtolower(trim((string) $data['status']));
        $paidAt    = null;

        if ($newStatus === 'paid') {
            $paidAt = ($data['paid_at'] ?? null)
                ? Carbon::parse((string) $data['paid_at'])
                : (($pay->paid_at ?? null) ? Carbon::parse((string) $pay->paid_at) : now());
        } elseif ($has('paid_at')) {
            $paidAt = ($data['paid_at'] ?? null)
                ? Carbon::parse((string) $data['paid_at'])
                : null;
        }

        $upd = [
            'amount'     => $amountCents,
            'currency'   => $currency,
            'status'     => $newStatus,
            'updated_at' => now(),
        ];

        if ($has('amount_mxn')) {
            $upd['amount_mxn'] = $amountPesos;
        }

        if ($has('monto_mxn')) {
            $upd['monto_mxn'] = $amountPesos;
        }

        if ($has('paid_at')) {
            $upd['paid_at'] = $paidAt;
        }

        if ($has('period')) {
            $upd['period'] = !empty($data['period']) ? $data['period'] : null;
        }

        if ($has('concept')) {
            $upd['concept'] = !empty($data['concept']) ? $data['concept'] : null;
        }

        if ($has('method')) {
            $upd['method'] = !empty($data['method']) ? $data['method'] : null;
        }

        if ($has('provider')) {
            $upd['provider'] = !empty($data['provider']) ? $data['provider'] : null;
        }

        if ($has('reference')) {
            $upd['reference'] = !empty($data['reference']) ? $data['reference'] : null;
        }

        if ($has('meta') && isset($pay->meta) && $pay->meta !== null && trim((string) $pay->meta) !== '') {
            $meta = $this->decodeMeta((string) $pay->meta);
            $meta['last_update_source'] = 'admin.payments.update';
            $meta['last_updated_at']    = now()->toDateTimeString();
            $meta['status']             = $newStatus;
            $meta['amount_pesos']       = $amountPesos;

            if (!empty($data['period'])) {
                $meta['period'] = $data['period'];
            }

            $upd['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        DB::connection($this->adm)->transaction(function () use ($id, $upd, $pay, $data) {
            DB::connection($this->adm)->table('payments')->where('id', $id)->update($upd);

            $oldPeriod = trim((string) ($pay->period ?? ''));
            $newPeriod = trim((string) ($data['period'] ?? $oldPeriod));
            $accountId = trim((string) ($pay->account_id ?? ''));

            if ($accountId !== '') {
                if ($oldPeriod !== '' && $this->isValidPeriod($oldPeriod)) {
                    $this->rebuildBillingStatementForPeriod($accountId, $oldPeriod);
                }

                if ($newPeriod !== '' && $this->isValidPeriod($newPeriod) && $newPeriod !== $oldPeriod) {
                    $this->rebuildBillingStatementForPeriod($accountId, $newPeriod);
                } elseif ($newPeriod !== '' && $this->isValidPeriod($newPeriod)) {
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

            if ($accountId !== '' && $period !== '' && $this->isValidPeriod($period)) {
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
            $acc = DB::connection($this->adm)->table('accounts')
                ->where('id', (int) $pay->account_id)
                ->first();
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

        if ($accountId === '' || !$this->isValidPeriod($period)) {
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

        if (!$has('account_id') || !$has('period') || !$has('status')) {
            return 0.0;
        }

        $q = DB::connection($this->adm)->table('payments')
            ->where('account_id', $accountId)
            ->where(function ($w) use ($period) {
                $w->where('period', $period)
                    ->orWhere('period', 'like', $period . '%');
            })
            ->whereIn(DB::raw('LOWER(status)'), [
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

    private function applyManualCreditToEstadoCuenta(int $accountId, string $period, string $concept, float $amountPesos): void
    {
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
            ->where('periodo', $period)
            ->orderByDesc('id')
            ->get();

        $saldo  = max(0.0, (float) $items->sum('cargo') - (float) $items->sum('abono'));
        $lastId = (int) ($items->first()->id ?? 0);

        if ($lastId > 0) {
            DB::connection($this->adm)->table('estados_cuenta')
                ->where('id', $lastId)
                ->update([
                    'saldo'      => round($saldo, 2),
                    'updated_at' => now(),
                ]);
        }
    }

        /**
     * @param array<int,string> $periods
     * @return array{
     *     rows: array<int,array<string,mixed>>,
     *     remaining: float
     * }
     */
    private function resolveManualPaymentDistribution(int $accountId, array $periods, float $totalAmountPesos): array
    {
        $remaining = round($totalAmountPesos, 2);
        $rows = [];

        if ($remaining <= 0.00001) {
            return ['rows' => [], 'remaining' => 0.0];
        }

        $fallbackMonthlyCharge = $this->resolveFallbackMonthlyCharge($accountId);

        foreach ($periods as $index => $period) {
            if ($remaining <= 0.00001) {
                break;
            }

            $existingDue = $this->resolveOutstandingForPeriod($accountId, $period);
            $targetDue   = $existingDue > 0.00001 ? $existingDue : $fallbackMonthlyCharge;

            if ($targetDue <= 0.00001) {
                if ($index === array_key_last($periods)) {
                    $targetDue = $remaining;
                } else {
                    continue;
                }
            }

            $apply = round(min($remaining, $targetDue), 2);

            if ($apply <= 0.00001) {
                continue;
            }

            $rows[] = [
                'period'       => $period,
                'amount_pesos' => $apply,
                'due'          => $targetDue,
                'kind'         => $existingDue > 0.00001 ? 'open_statement' : 'prepaid_future_period',
                'notes'        => $existingDue > 0.00001
                    ? 'Aplicado a saldo abierto del periodo.'
                    : 'Aplicado como prepago de periodo futuro.',
            ];

            $remaining = round($remaining - $apply, 2);
        }

        if ($remaining > 0.00001 && !empty($rows)) {
            $lastIndex = array_key_last($rows);
            $rows[$lastIndex]['amount_pesos'] = round(((float) $rows[$lastIndex]['amount_pesos']) + $remaining, 2);
            $rows[$lastIndex]['notes'] = trim(((string) $rows[$lastIndex]['notes']) . ' Incluye excedente/prepago acumulado.');
            $remaining = 0.0;
        }

        return [
            'rows'      => $rows,
            'remaining' => $remaining,
        ];
    }

    private function resolveOutstandingForPeriod(int $accountId, string $period): float
    {
        if (!Schema::connection($this->adm)->hasTable('billing_statements')) {
            return 0.0;
        }

        $statement = DB::connection($this->adm)->table('billing_statements')
            ->where('account_id', (string) $accountId)
            ->where('period', $period)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first([
                'id',
                'total_cargo',
                'saldo',
            ]);

        if (!$statement) {
            return 0.0;
        }

        $saldo = round((float) ($statement->saldo ?? 0), 2);
        if ($saldo > 0.00001) {
            return $saldo;
        }

        $cargo = round((float) ($statement->total_cargo ?? 0), 2);
        $paid  = $this->sumPaidPaymentsMxnForPeriod((string) $accountId, $period);

        return round(max(0.0, $cargo - $paid), 2);
    }

    private function resolveFallbackMonthlyCharge(int $accountId): float
    {
        if (!Schema::connection($this->adm)->hasTable('billing_statements')) {
            return 0.0;
        }

        $row = DB::connection($this->adm)->table('billing_statements')
            ->where('account_id', (string) $accountId)
            ->where('total_cargo', '>', 0)
            ->orderByDesc('period')
            ->orderByDesc('id')
            ->first(['total_cargo']);

        return round((float) ($row->total_cargo ?? 0), 2);
    }

    /**
     * @param array<string,bool> $has
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function buildManualPaymentInsertRow(
        int $accountId,
        string $period,
        float $amountPesos,
        string $currency,
        string $concept,
        callable $has,
        string $groupReference,
        array $meta = []
    ): array {
        $amountCents = (int) round($amountPesos * 100);
        $now = now();

        $row = [
            'account_id' => $accountId,
            'amount'     => $amountCents,
            'currency'   => $currency,
            'status'     => 'paid',
            'paid_at'    => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($has('due_date')) {
            $row['due_date'] = $now;
        }

        if ($has('reference')) {
            $row['reference'] = $groupReference . ':' . $period;
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
            $row['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        return $row;
    }

    /**
     * @return array<int,string>
     */
    private function buildPeriodRange(string $periodFrom, string $periodTo): array
    {
        $periodFrom = trim($periodFrom);
        $periodTo   = trim($periodTo);

        if (!$this->isValidPeriod($periodFrom) || !$this->isValidPeriod($periodTo)) {
            return [];
        }

        try {
            $start = Carbon::createFromFormat('Y-m', $periodFrom)->startOfMonth();
            $end   = Carbon::createFromFormat('Y-m', $periodTo)->startOfMonth();
        } catch (Throwable) {
            return [];
        }

        if ($end->lt($start)) {
            return [];
        }

        $out = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $out[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $out;
    }

    private function buildManualReference(int $accountId, string $period): string
    {
        return sprintf(
            'manual:%d:%s:%s',
            $accountId,
            $period,
            strtoupper(Str::random(10))
        );
    }

    private function decodeMeta(string $meta): array
    {
        try {
            $decoded = json_decode($meta, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', trim($period));
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

        $dateCol    = $has('paid_at') ? 'paid_at' : ($has('created_at') ? 'created_at' : null);
        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $last30     = now()->subDays(29)->toDateString();

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