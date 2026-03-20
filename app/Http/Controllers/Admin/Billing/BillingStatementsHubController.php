<?php
// app/Http/Controllers/Admin/Billing/BillingStatementsHubController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Mail\Admin\Billing\StatementAccountPeriodMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;

final class BillingStatementsHubController extends Controller
{
    private string $adm;
    private StripeClient $stripe;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        $secret = (string) config('services.stripe.secret');
        $this->stripe = new StripeClient($secret ?: '');
    }

    public function index(Request $req): View
    {
        $q = trim((string) $req->get('q', ''));

        $period = (string) $req->get('period', now()->format('Y-m'));
        if (!$this->isValidPeriod($period)) {
            $period = now()->format('Y-m');
        }

        $accountId = trim((string) $req->get('accountId', ''));
        $accountId = $accountId !== '' ? $accountId : null;

        $perPage = (int) $req->get('perPage', 25);
        $allowedPerPage = [25, 50, 100, 250, 500, 1000];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 25;
        }

        $status = strtolower(trim((string) $req->get('status', 'all')));
        $allowedStatus = ['all', 'pendiente', 'pagado', 'parcial', 'vencido', 'sin_mov'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'all';
        }

        $includeAnnual = $req->boolean('includeAnnual')
            || $req->boolean('include_annual')
            || ((string)$req->get('includeAnnual', '') === '1')
            || ((string)$req->get('include_annual', '') === '1');

        $onlySelected = $req->boolean('only_selected')
            || $req->boolean('onlySelected')
            || ((string)$req->get('only_selected', '') === '1');

        $selectedIds = [];
        $idsRaw = $req->get('ids', null);

        if (is_array($idsRaw)) {
            $selectedIds = array_values(array_filter(array_map(static function ($v) {
                $s = trim((string)$v);
                if ($s === '') return null;
                if (preg_match('/^\d+$/', $s)) return $s;
                if (preg_match('/^[a-zA-Z0-9\-_]+$/', $s)) return $s;
                return null;
            }, $idsRaw)));
        } elseif (is_string($idsRaw)) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $idsRaw))));
            $selectedIds = array_values(array_filter(array_map(static function ($s) {
                $s = trim((string)$s);
                if ($s === '') return null;
                if (preg_match('/^\d+$/', $s)) return $s;
                if (preg_match('/^[a-zA-Z0-9\-_]+$/', $s)) return $s;
                return null;
            }, $parts)));
        }

        if (count($selectedIds) > 500) {
            $selectedIds = array_slice($selectedIds, 0, 500);
        }

        if ($onlySelected && empty($selectedIds)) {
            return view('admin.billing.statements.index', [
                'rows'      => collect(),
                'q'         => $q,
                'period'    => $period,
                'accountId' => $accountId,
                'status'    => $status,
                'perPage'   => $perPage,
                'onlySelected' => true,
                'idsCsv'       => '',
                'error'     => 'Filtro "solo seleccionadas" activo, pero no recibí IDs (ids).',
                'kpis'      => [
                    'cargo'         => 0,
                    'abono'         => 0,
                    'saldo'         => 0,
                    'prev_pending'  => 0,
                    'accounts'      => 0,
                    'paid_edo'      => 0,
                    'paid_pay'      => 0,
                ],
            ]);
        }

        if (
            !Schema::connection($this->adm)->hasTable('accounts') ||
            !Schema::connection($this->adm)->hasTable('estados_cuenta')
        ) {
            return view('admin.billing.statements.index', [
                'rows'      => collect(),
                'q'         => $q,
                'period'    => $period,
                'accountId' => $accountId,
                'status'    => $status,
                'perPage'   => $perPage,
                'error'     => 'Faltan tablas accounts y/o estados_cuenta en p360v1_admin.',
                'kpis'      => [
                    'cargo'         => 0,
                    'abono'         => 0,
                    'saldo'         => 0,
                    'prev_pending'  => 0,
                    'accounts'      => 0,
                    'paid_edo'      => 0,
                    'paid_pay'      => 0,
                ],
            ]);
        }

        $cols = Schema::connection($this->adm)->getColumnListing('accounts');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

        $select = ['accounts.id', 'accounts.email'];

        foreach ([
            'name', 'razon_social', 'rfc',
            'plan', 'plan_actual', 'modo_cobro', 'billing_cycle',
            'is_blocked', 'estado_cuenta',
            'meta',
            'created_at',
        ] as $c) {
            if ($has($c)) {
                $select[] = "accounts.$c";
            }
        }

        foreach ([
            'billing_amount_mxn', 'amount_mxn', 'precio_mxn', 'monto_mxn',
            'override_amount_mxn', 'custom_amount_mxn', 'license_amount_mxn',
            'billing_amount', 'amount', 'precio', 'monto',
        ] as $c) {
            if ($has($c)) {
                $select[] = "accounts.$c";
            }
        }

        $hasSubs = Schema::connection($this->adm)->hasTable('subscriptions');

        $qb = DB::connection($this->adm)->table('accounts');

        if ($hasSubs) {
            $subMax = DB::connection($this->adm)->table('subscriptions')
                ->selectRaw('account_id, MAX(id) as max_id')
                ->groupBy('account_id');

            $qb->leftJoinSub($subMax, 'sx_submax', function ($j) {
                $j->on('sx_submax.account_id', '=', 'accounts.id');
            });

            $qb->leftJoin('subscriptions as sx_sub', 'sx_sub.id', '=', 'sx_submax.max_id');

            $select[] = DB::raw('sx_sub.started_at as sub_started_at');
            $select[] = DB::raw('sx_sub.current_period_end as sub_current_period_end');
            $select[] = DB::raw('sx_sub.status as sub_status');
        }

        $qb->select($select)
            ->orderByDesc($has('created_at') ? 'accounts.created_at' : 'accounts.id');

        if ($onlySelected) {
            $qb->whereIn('accounts.id', $selectedIds);
            $accountId = null;
            $q = '';
        } else {
            if ($accountId) {
                $qb->where('accounts.id', $accountId);
            }

            if ($q !== '') {
                $qb->where(function ($w) use ($q, $has) {
                    $w->where('accounts.id', 'like', "%{$q}%");
                    if ($has('name')) {
                        $w->orWhere('accounts.name', 'like', "%{$q}%");
                    }
                    if ($has('razon_social')) {
                        $w->orWhere('accounts.razon_social', 'like', "%{$q}%");
                    }
                    if ($has('rfc')) {
                        $w->orWhere('accounts.rfc', 'like', "%{$q}%");
                    }
                    $w->orWhere('accounts.email', 'like', "%{$q}%");
                });
            }
        }

        if (!$includeAnnual && $hasSubs) {
            $annualExpr = "LOWER(COALESCE(accounts.modo_cobro,'')) IN ('anual','annual','year','yearly','12m','12')";
            $renewYm    = "DATE_FORMAT(COALESCE(sx_sub.current_period_end, sx_sub.started_at, accounts.created_at), '%Y-%m')";
            $qb->whereRaw("NOT ($annualExpr) OR ($renewYm = ?)", [$period]);
        } elseif (!$includeAnnual && !$hasSubs) {
            $qb->whereRaw("LOWER(COALESCE(accounts.modo_cobro,'')) NOT IN ('anual','annual','year','yearly','12m','12')");
        }

        // =========================================================
        // IMPORTANTE:
        // Para que no se oculten cuentas por filtrar después de paginar,
        // primero cargamos el universo filtrado base y luego paginamos manual.
        // =========================================================
        $baseRows = collect($qb->get());

        $ids = $baseRows->pluck('id')->filter()->values()->all();

        $agg = DB::connection($this->adm)->table('estados_cuenta')
            ->selectRaw('account_id as aid, SUM(COALESCE(cargo,0)) as cargo, SUM(COALESCE(abono,0)) as abono')
            ->whereIn('account_id', !empty($ids) ? $ids : ['__none__'])
            ->where('periodo', '=', $period)
            ->groupBy('account_id')
            ->get()
            ->keyBy('aid');

        $payAgg  = $this->sumPaymentsForAccountsPeriod($ids, $period);
        $payMeta = $this->fetchPaymentsMetaForAccountsPeriod($ids, $period);
        $ovMap   = $this->fetchStatusOverridesForAccountsPeriod($ids, $period);

        $now = Carbon::now();

        $rowsCollection = $baseRows->map(function ($r) use ($agg, $payAgg, $payMeta, $ovMap, $period, $now) {
            $a = $agg[$r->id] ?? null;

            $cargoEdo = (float) ($a->cargo ?? 0);
            $abonoEdo = (float) ($a->abono ?? 0);

            $paidPayments = (float) ($payAgg[(string) $r->id] ?? 0);
            $abonoTotal   = $abonoEdo + $paidPayments;

            $meta = $this->hub->decodeMeta($r->meta ?? null);
            if (!is_array($meta)) {
                $meta = [];
            }

            $lastPaid = $this->resolveLastPaidPeriodForAccount((string) $r->id, $meta);

            $payAllowed = $lastPaid
                ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
                : $period;

            $customMxn = $this->extractCustomAmountMxn($r, $meta);

            if ($customMxn !== null && $customMxn > 0.00001) {
                $effectiveMxn = $customMxn;
                $tarifaLabel  = 'PERSONALIZADO';
                $tarifaPill   = 'info';
            } else {
                [$effectiveMxn, $tarifaLabel, $tarifaPill] = $this->safeResolveEffectiveAmountFromMeta($meta, $period, $payAllowed);
            }

            $totalCurrent = $cargoEdo > 0.00001 ? $cargoEdo : (float) $effectiveMxn;
            $saldoCurrent = (float) max(0.0, $totalCurrent - $abonoTotal);

            $pm  = $payMeta[(string) $r->id] ?? [];
            $due = $pm['due_date'] ?? null;

            // saldo pendiente anterior real
            $prevInfo = $this->computePrevOpenBalance((string) $r->id, $period, $lastPaid);
            $prevBalance = (float) ($prevInfo['prev_balance'] ?? 0.0);
            $prevPeriod  = $prevInfo['prev_period'] ?? null;

            $totalDue = round(max(0.0, $saldoCurrent + $prevBalance), 2);

            $statusPago = 'pendiente';
            if ($totalCurrent <= 0.00001 && $prevBalance <= 0.00001) {
                $statusPago = 'sin_mov';
            } elseif ($totalDue <= 0.00001) {
                $statusPago = 'pagado';
            } elseif ($prevBalance > 0.00001) {
                $statusPago = 'vencido';
            } elseif ($saldoCurrent > 0.00001 && $this->isOverdue($period, $due, $now)) {
                $statusPago = 'vencido';
            } elseif ($abonoTotal > 0.00001 && $abonoTotal < ($totalCurrent - 0.00001)) {
                $statusPago = 'parcial';
            } else {
                $statusPago = 'pendiente';
            }

            $r->cargo            = round($cargoEdo, 2);
            $r->expected_total   = round((float) $effectiveMxn, 2);
            $r->total_shown      = round($totalCurrent, 2);

            $r->abono            = round($abonoTotal, 2);
            $r->abono_edo        = round($abonoEdo, 2);
            $r->abono_pay        = round($paidPayments, 2);

            $r->saldo_current    = round($saldoCurrent, 2);
            $r->saldo_shown      = round($saldoCurrent, 2); // compat legacy
            $r->saldo            = round($saldoCurrent, 2); // compat legacy

            $r->prev_balance     = round($prevBalance, 2);
            $r->prev_period      = $prevPeriod;
            $r->total_due        = round($totalDue, 2);

            $r->tarifa_label     = (string) $tarifaLabel;
            $r->tarifa_pill      = (string) $tarifaPill;

            $r->status_pago      = $statusPago;
            $r->status_auto      = $statusPago;

            $r->last_paid        = $lastPaid;
            $r->pay_allowed      = $payAllowed;

            $r->pay_last_paid_at = $pm['last_paid_at'] ?? null;
            $r->pay_due_date     = $due;
            $r->pay_method       = $pm['method'] ?? null;
            $r->pay_provider     = $pm['provider'] ?? null;
            $r->pay_status       = $pm['status'] ?? null;

            $ov = $ovMap[(string) $r->id] ?? null;
            $r  = $this->applyStatusOverride($r, $ov);

            // Si el override es pagado, el total_due visual debe ir a cero
            if ((string) ($r->status_pago ?? '') === 'pagado') {
                $r->saldo_current = 0.0;
                $r->saldo_shown   = 0.0;
                $r->saldo         = 0.0;
                $r->prev_balance  = 0.0;
                $r->total_due     = 0.0;
            }

            return $r;
        });

        if ($status !== 'all') {
            $rowsCollection = $rowsCollection
                ->filter(static fn ($x) => (string) ($x->status_pago ?? '') === $status)
                ->values();
        }

        $currentPage = (int) $req->get('page', LengthAwarePaginator::resolveCurrentPage());
        $currentPage = max(1, $currentPage);

        $rows = $this->repaginateCollection(
            $rowsCollection,
            $perPage,
            $currentPage,
            $req->url(),
            $req->query()
        );

        $kCargo = 0.0;
        $kAbono = 0.0;
        $kSaldo = 0.0;
        $kPrev  = 0.0;
        $kEdo   = 0.0;
        $kPay   = 0.0;

        foreach ($rows->getCollection() as $r) {
            $kCargo += (float) ($r->total_shown ?? 0);
            $kAbono += (float) ($r->abono ?? 0);
            $kSaldo += (float) ($r->total_due ?? 0);
            $kPrev  += (float) ($r->prev_balance ?? 0);
            $kEdo   += (float) ($r->abono_edo ?? 0);
            $kPay   += (float) ($r->abono_pay ?? 0);
        }

        return view('admin.billing.statements.index', [
            'rows'      => $rows,
            'q'         => $q,
            'period'    => $period,
            'accountId' => $accountId,
            'status'    => $status,
            'perPage'   => $perPage,
            'onlySelected' => $onlySelected,
            'idsCsv'       => $onlySelected ? implode(',', $selectedIds) : '',
            'error'     => null,
            'kpis'      => [
                'cargo'         => round($kCargo, 2),
                'abono'         => round($kAbono, 2),
                'saldo'         => round($kSaldo, 2),
                'prev_pending'  => round($kPrev, 2),
                'accounts'      => (int) $rows->getCollection()->count(),
                'paid_edo'      => round($kEdo, 2),
                'paid_pay'      => round($kPay, 2),
            ],
        ]);
    }

    // =========================================================
    // ACCIONES MASIVAS (UI bulkbar)
    // =========================================================

    public function bulkSend(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'period'      => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'account_ids' => 'required|string|max:20000', // csv
            'to'          => 'nullable|string|max:2000',  // override destinos
            'mode'        => 'nullable|string|max:20',    // now|queue
        ]);

        $period = (string) $data['period'];
        $ids    = $this->parseIdCsv((string) $data['account_ids']);
        if (empty($ids)) return back()->withErrors(['bulk' => 'No hay cuentas seleccionadas.']);

        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            return back()->withErrors(['bulk' => 'No existe tabla accounts.']);
        }
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            return back()->withErrors(['bulk' => 'No existe billing_email_logs.']);
        }

        $mode = strtolower(trim((string) ($data['mode'] ?? 'now')));
        if (!in_array($mode, ['now', 'queue'], true)) $mode = 'now';

        $toRaw        = trim((string) ($data['to'] ?? ''));
        $overrideTos  = $this->parseToList($toRaw);

        $accounts = DB::connection($this->adm)->table('accounts')
            ->select(['id', 'email', 'rfc', 'razon_social', 'name', 'meta'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $sent = 0; $queued = 0; $failed = 0;

        foreach ($ids as $aid) {
            $acc = $accounts[$aid] ?? null;
            if (!$acc) { $failed++; continue; }

            $tos = !empty($overrideTos) ? $overrideTos : $this->parseToList((string) ($acc->email ?? ''));
            if (empty($tos)) { $failed++; continue; }

            $emailId = (string) Str::ulid();

            $logId = $this->insertEmailLog([
                'email_id'   => $emailId,
                'account_id' => (string) $aid,
                'period'     => $period,
                'email'      => $tos[0] ?? null,
                'to_list'    => implode(',', $tos),
                'template'   => 'statement',
                'status'     => 'queued',
                'provider'   => config('mail.default') ?: 'smtp',
                'subject'    => null,
                'payload'    => null,
                'meta'       => json_encode([
                    'source'     => 'admin_bulk_send',
                    'mode'       => $mode,
                    'account_id' => (string) $aid,
                    'period'     => $period,
                ], JSON_UNESCAPED_UNICODE),
                'queued_at'  => now(),
            ]);

            if ($mode === 'queue') {
                $queued++;
                continue;
            }

            try {
                $payload = $this->buildStatementEmailPayloadPublic($acc, (string) $aid, $period, $emailId);

                DB::connection($this->adm)->table('billing_email_logs')->where('id', $logId)->update([
                    'subject'     => (string) ($payload['subject'] ?? null),
                    'payload'     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'updated_at'  => now(),
                ]);

                Mail::to($tos)->send(new StatementAccountPeriodMail((string) $aid, $period, $payload));

                DB::connection($this->adm)->table('billing_email_logs')->where('id', $logId)->update([
                    'status'     => 'sent',
                    'sent_at'    => now(),
                    'updated_at' => now(),
                ]);

                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                DB::connection($this->adm)->table('billing_email_logs')->where('id', $logId)->update([
                    'status'     => 'failed',
                    'failed_at'  => now(),
                    'updated_at' => now(),
                    'meta'       => json_encode([
                        'error'      => $e->getMessage(),
                        'source'     => 'admin_bulk_send',
                        'account_id' => (string) $aid,
                        'period'     => $period,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        $msg = "Bulk envío listo. sent={$sent}, queued={$queued}, failed={$failed}.";
        return back()->with('ok', $msg);
    }

    // =========================================================
    // EMAIL: enviar / reenviar / preview
    // =========================================================

    public function sendEmail(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'to'         => 'nullable|string|max:2000',
        ]);

        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];
        $toRaw     = trim((string) ($data['to'] ?? ''));

        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            return back()->withErrors(['account_id' => 'No existe la tabla accounts.']);
        }
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            return back()->withErrors(['email' => 'No existe billing_email_logs.']);
        }

        $acc = DB::connection($this->adm)->table('accounts')
            ->select(['id', 'email', 'rfc', 'razon_social', 'name', 'meta'])
            ->where('id', $accountId)
            ->first();

        if (!$acc) {
            return back()->withErrors(['account_id' => 'Cuenta no encontrada.']);
        }

        $tos = $this->parseToList($toRaw);
        if (empty($tos)) {
            $tos = $this->parseToList((string) ($acc->email ?? ''));
        }
        if (empty($tos)) {
            return back()->withErrors(['to' => 'No hay correos destino.']);
        }

        $emailId = (string) Str::ulid();

        $logId = $this->insertEmailLog([
            'email_id'   => $emailId,
            'account_id' => $accountId,
            'period'     => $period,
            'email'      => $tos[0] ?? null,
            'to_list'    => implode(',', $tos),
            'template'   => 'statement',
            'status'     => 'queued',
            'provider'   => config('mail.default') ?: 'smtp',
            'subject'    => null,
            'payload'    => null,
            'meta'       => json_encode([
                'source'     => 'admin_send_now',
                'account_id' => $accountId,
                'period'     => $period,
            ], JSON_UNESCAPED_UNICODE),
            'queued_at'  => now(),
        ]);

        try {
            $payload = $this->buildStatementEmailPayloadPublic($acc, $accountId, $period, $emailId);

            DB::connection($this->adm)->table('billing_email_logs')->where('id', $logId)->update([
                'subject'     => (string) ($payload['subject'] ?? null),
                'payload'     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'updated_at'  => now(),
            ]);

            Mail::to($tos)->send(new StatementAccountPeriodMail($accountId, $period, $payload));

            DB::connection($this->adm)->table('billing_email_logs')->where('id', $logId)->update([
                'status'     => 'sent',
                'sent_at'    => now(),
                'updated_at' => now(),
            ]);

            return back()->with('ok', 'Correo enviado. Tracking activo (email_id=' . $emailId . ').');
        } catch (\Throwable $e) {
            DB::connection($this->adm)->table('billing_email_logs')->where('id', $logId)->update([
                'status'     => 'failed',
                'failed_at'  => now(),
                'updated_at' => now(),
                'meta'       => json_encode([
                    'error'      => $e->getMessage(),
                    'source'     => 'admin_send_now',
                    'account_id' => $accountId,
                    'period'     => $period,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            Log::error('[BILLING_HUB][sendEmail] fallo', [
                'account_id' => $accountId,
                'period'     => $period,
                'to'         => $tos,
                'email_id'   => $emailId,
                'e'          => $e->getMessage(),
            ]);

            return back()->withErrors(['mail' => 'Falló el envío: ' . $e->getMessage()]);
        }
    }

    public function resendEmail(Request $req, int $id): RedirectResponse
    {
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            return back()->withErrors(['email' => 'No existe billing_email_logs.']);
        }

        $row = DB::connection($this->adm)->table('billing_email_logs')->where('id', $id)->first();
        if (!$row) {
            return back()->withErrors(['email' => 'Log no encontrado.']);
        }

        $accountId = (string) ($row->account_id ?? '');
        $period    = (string) ($row->period ?? '');

        if ($accountId === '' || $period === '') {
            return back()->withErrors(['email' => 'El log no tiene account_id/period.']);
        }

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        if (!$acc) {
            return back()->withErrors(['email' => 'Cuenta no encontrada.']);
        }

        $tos = $this->parseToList((string) ($row->to_list ?? $row->email ?? ''));
        if (empty($tos)) {
            $tos = $this->parseToList((string) ($acc->email ?? ''));
        }
        if (empty($tos)) {
            return back()->withErrors(['email' => 'Sin destinatarios.']);
        }

        $emailId = (string) Str::ulid();

        try {
            $payload = $this->buildStatementEmailPayloadPublic($acc, $accountId, $period, $emailId);

            Mail::to($tos)->send(new StatementAccountPeriodMail($accountId, $period, $payload));

            DB::connection($this->adm)->table('billing_email_logs')->where('id', $id)->update([
                'email_id'    => $emailId,
                'status'      => 'sent',
                'sent_at'     => now(),
                'failed_at'   => null,
                'subject'     => (string) ($payload['subject'] ?? null),
                'payload'     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'updated_at'  => now(),
            ]);

            return back()->with('ok', 'Reenvío OK. Nuevo email_id=' . $emailId);
        } catch (\Throwable $e) {
            DB::connection($this->adm)->table('billing_email_logs')->where('id', $id)->update([
                'status'     => 'failed',
                'failed_at'  => now(),
                'updated_at' => now(),
                'meta'       => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);

            return back()->withErrors(['email' => 'Reenvío falló: ' . $e->getMessage()]);
        }
    }

    public function previewEmail(Request $req): Response
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
        ]);

        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        if (!$acc) abort(404);

        $emailId = (string) Str::ulid();

        // preview: no generar checkout Stripe (sin efectos colaterales)
        $payload = $this->buildStatementEmailPayloadPublic($acc, $accountId, $period, $emailId, true);

        $html = view('emails.admin.billing.statement_account_period', $payload)->render();

        return response($html, 200, [
            'Content-Type'    => 'text/html; charset=UTF-8',
            'Cache-Control'   => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'          => 'no-cache',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    // =========================================================
    // LIGA DIRECTA DE PAGO (Stripe Checkout) + registro pending
    // =========================================================

    public function createPayLink(Request $req): Response|RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
        ]);

        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        if (!$acc) {
            return back()->withErrors(['pay' => 'Cuenta no encontrada.'])->withInput();
        }

        $items = collect();
        if (Schema::connection($this->adm)->hasTable('estados_cuenta')) {
            $items = DB::connection($this->adm)->table('estados_cuenta')
                ->where('account_id', $accountId)
                ->where('periodo', $period)
                ->get();
        }

        $cargoReal = (float) $items->sum('cargo');
        $abonoEc   = (float) $items->sum('abono');

        // FIX: SIEMPRE sumar payments pagados
        $abonoPay  = (float) $this->sumPaymentsPaidForAccountPeriod($accountId, $period);
        $abono     = $abonoEc + $abonoPay;

        $meta   = $this->decodeMeta($acc->meta ?? null);
        $custom = $this->extractCustomAmountMxn($acc, $meta);

        if ($custom !== null && $custom > 0.00001) {
            $expected = $custom;
        } else {
            [$expected] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);
        }

        $totalShown = $cargoReal > 0 ? $cargoReal : (float) $expected;
        $saldo      = max(0.0, $totalShown - $abono);

        if ($saldo <= 0.00001) {
            return back()->with('ok', 'No hay saldo pendiente para ese periodo.')->withInput();
        }

        try {
            [$url, $sessionId] = $this->createStripeCheckoutForStatement($acc, $period, $saldo);

            // UX: redirigir directo a Stripe (en lugar de mostrar la liga)
            return redirect()
                ->away((string) $url)
                ->withHeaders([
                    'Cache-Control' => 'no-store, max-age=0, public',
                    'Pragma'        => 'no-cache',
                ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['pay' => 'No se pudo generar liga: ' . $e->getMessage()])->withInput();
        }
    }

    private function paymentsAmountExpr(): string
    {
        static $cache = [];

        $conn = (string) ($this->adm ?: (config('p360.conn.admin') ?: 'mysql_admin'));
        if (isset($cache[$conn])) return $cache[$conn];

        $schema = \Illuminate\Support\Facades\Schema::connection($conn);

        $parts = [];
        if ($schema->hasColumn('payments', 'amount_mxn')) {
            $parts[] = "WHEN amount_mxn IS NOT NULL AND amount_mxn != '' THEN amount_mxn";
        }
        if ($schema->hasColumn('payments', 'monto_mxn')) {
            $parts[] = "WHEN monto_mxn IS NOT NULL AND monto_mxn != '' THEN monto_mxn";
        }
        if ($schema->hasColumn('payments', 'amount_cents')) {
            $parts[] = "WHEN amount_cents IS NOT NULL AND amount_cents > 0 THEN (amount_cents/100)";
        }
        if ($schema->hasColumn('payments', 'amount')) {
            $parts[] = "WHEN amount IS NOT NULL AND amount > 0 THEN (amount/100)";
        }

        // Fallback ultra-defensivo (evita romper)
        if (!$parts) {
            $cache[$conn] = "(0)";
            return $cache[$conn];
        }

        $cache[$conn] = "(CASE " . implode(' ', $parts) . " ELSE 0 END)";
        return $cache[$conn];
    }

    private function sumPaymentsForAccountPeriod(string $accountId, string $period): float
    {
        $amtExpr = $this->paymentsAmountExpr();

        $paid = (float) \Illuminate\Support\Facades\DB::connection($this->adm)->table('payments')
            ->where('account_id', (int) $accountId)
            ->where('period', (string) $period)
            ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(status)'), ['paid','succeeded','success','completed','complete','pagado','paid_ok','ok'])
            ->where(function ($w) {
                $w->whereNull('provider')
                ->orWhere('provider', '')
                ->orWhereRaw('LOWER(provider) IN (?,?,?,?)', ['stripe','stripe_checkout','manual','checkout']);
            })
            ->selectRaw("SUM($amtExpr) AS s")
            ->value('s');

        return (float) $paid;
    }

    private function sumPaymentsForAccountsPeriod(array $accountIds, string $period): array
    {
        $ids = array_values(array_unique(array_map('intval', $accountIds)));
        if (!$ids) return [];

        $amtExpr = $this->paymentsAmountExpr();

        $rows = \Illuminate\Support\Facades\DB::connection($this->adm)->table('payments')
            ->whereIn('account_id', $ids)
            ->where('period', (string) $period)
            ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(status)'), ['paid','succeeded','success','completed','complete','pagado','paid_ok','ok'])
            ->where(function ($w) {
                $w->whereNull('provider')
                ->orWhere('provider', '')
                ->orWhereRaw('LOWER(provider) IN (?,?,?,?)', ['stripe','stripe_checkout','manual','checkout']);
            })
            ->selectRaw("account_id, SUM($amtExpr) AS paid_mxn")
            ->groupBy('account_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r->account_id] = (float) ($r->paid_mxn ?? 0);
        }
        return $out;
    }



    private function createStripeCheckoutForStatement(object $acc, string $period, float $totalPesos): array
    {
        $secret = (string) config('services.stripe.secret');
        if (trim($secret) === '') {
            throw new \RuntimeException('Stripe secret vacío.');
        }

        $unitAmountCents = (int) round($totalPesos * 100);

        $successUrl = Route::has('cliente.checkout.success')
            ? route('cliente.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}'
            : url('/cliente/checkout/success') . '?session_id={CHECKOUT_SESSION_ID}';

        $cancelUrl = Route::has('cliente.estado_cuenta')
            ? route('cliente.estado_cuenta') . '?period=' . urlencode($period)
            : url('/cliente/estado-de-cuenta') . '?period=' . urlencode($period);

        $idempotencyKey = 'hub_stmt:' . ((string) ($acc->id ?? '')) . ':' . $period . ':' . md5((string) microtime(true));

        $session = $this->stripe->checkout->sessions->create([
            'mode'                 => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency'     => 'mxn',
                    'unit_amount'  => $unitAmountCents,
                    'product_data' => [
                        'name' => 'Pactopia360 · Estado de cuenta ' . $period,
                    ],
                ],
                'quantity' => 1,
            ]],
            'customer_email'      => $acc->email ?? null,
            'client_reference_id' => (string) ($acc->id ?? ''),
            'success_url'         => $successUrl,
            'cancel_url'          => $cancelUrl,
            'metadata'            => [
                'type'      => 'billing_statement',
                'account_id' => (string) ($acc->id ?? ''),
                'period'     => $period,
                'source'     => 'admin_hub',
            ],
        ], [
            'idempotency_key' => $idempotencyKey,
        ]);

        $sessionId  = (string) ($session->id ?? '');
        $sessionUrl = (string) ($session->url ?? '');

        $this->upsertPendingPaymentForStatement((string) ($acc->id ?? ''), $period, $unitAmountCents, $sessionId, $totalPesos);

        return [$sessionUrl, $sessionId];
    }

    private function upsertPendingPaymentForStatement(string $accountId, string $period, int $amountCents, string $sessionId, float $uiTotalPesos): void
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) return;

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

        $existing = null;

        if ($has('stripe_session_id') && $sessionId !== '') {
            $existing = DB::connection($this->adm)->table('payments')
                ->where('account_id', $accountId)
                ->where('stripe_session_id', $sessionId)
                ->first();
        }

        if (!$existing && $has('period') && $has('status')) {
            $q = DB::connection($this->adm)->table('payments')
                ->where('account_id', $accountId)
                ->where('status', 'pending')
                ->where('period', $period);

            if ($has('provider')) $q->where('provider', 'stripe');
            $existing = $q->orderByDesc($has('id') ? 'id' : $cols[0])->first();
        }

        $row = [];
        if ($has('account_id')) $row['account_id'] = $accountId;

        if ($has('amount'))     $row['amount'] = $amountCents;
        if ($has('amount_cents')) $row['amount_cents'] = $amountCents;

        if ($has('amount_mxn')) $row['amount_mxn'] = round($uiTotalPesos, 2);
        if ($has('monto_mxn'))  $row['monto_mxn']  = round($uiTotalPesos, 2);

        if ($has('currency'))   $row['currency'] = 'MXN';
        if ($has('status'))     $row['status']   = 'pending';
        if ($has('due_date'))   $row['due_date'] = now();

        if ($has('period'))     $row['period']   = $period;
        if ($has('method'))     $row['method']   = 'card';
        if ($has('provider'))   $row['provider'] = 'stripe';

        if ($has('concept')) {
            $row['concept'] = 'Pactopia360 · Estado de cuenta ' . $period;
        }

        if ($has('reference')) {
            $row['reference'] = $sessionId ?: ('hub_stmt:' . $accountId . ':' . $period);
        }

        if ($has('stripe_session_id')) {
            $row['stripe_session_id'] = $sessionId;
        }

        if ($has('meta')) {
            $row['meta'] = json_encode([
                'type'           => 'billing_statement',
                'period'         => $period,
                'ui_total_pesos' => round($uiTotalPesos, 2),
                'source'         => 'admin_hub',
            ], JSON_UNESCAPED_UNICODE);
        }

        $row['updated_at'] = now();
        if (!$existing) $row['created_at'] = now();

        if ($existing && $has('id')) {
            DB::connection($this->adm)->table('payments')->where('id', (int) $existing->id)->update($row);
        } else {
            DB::connection($this->adm)->table('payments')->insert($row);
        }
    }

    // =========================================================
    // Solicitudes de factura + facturas admin
    // =========================================================

    public function invoiceRequest(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'notes'      => 'nullable|string|max:5000',
        ]);

        if (!Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            return back()->withErrors(['invoice' => 'No existe la tabla billing_invoice_requests.']);
        }

        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];
        $notes     = trim((string) ($data['notes'] ?? ''));

        $now = now();

        $row = DB::connection($this->adm)->table('billing_invoice_requests')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        if ($row) {
            DB::connection($this->adm)->table('billing_invoice_requests')->where('id', (int) $row->id)->update([
                'notes'      => $notes !== '' ? $notes : ($row->notes ?? null),
                'status'     => $row->status ?: 'requested',
                'updated_at' => $now,
            ]);

            return back()->with('ok', 'Solicitud de factura actualizada (#' . (int) $row->id . ')');
        }

        $id = (int) DB::connection($this->adm)->table('billing_invoice_requests')->insertGetId([
            'account_id'  => $accountId,
            'period'      => $period,
            'status'      => 'requested',
            'notes'       => ($notes !== '' ? $notes : null),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return back()->with('ok', 'Solicitud de factura creada (#' . $id . ')');
    }

    public function invoiceStatus(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'id'        => 'required|integer|min:1',
            'status'    => 'required|string|max:40',
            'cfdi_uuid' => 'nullable|string|max:80',
            'notes'     => 'nullable|string|max:5000',
        ]);

        if (!Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            return back()->withErrors(['invoice' => 'No existe la tabla billing_invoice_requests.']);
        }

        $id = (int) $data['id'];

        $ok = DB::connection($this->adm)->table('billing_invoice_requests')->where('id', $id)->update([
            'status'     => trim((string) $data['status']),
            'cfdi_uuid'  => ($data['cfdi_uuid'] ?? null) ?: null,
            'notes'      => ($data['notes'] ?? null) ?: null,
            'updated_at' => now(),
        ]);

        if (!$ok) return back()->withErrors(['invoice' => 'No se pudo actualizar. ID no encontrado.']);

        return back()->with('ok', 'Estatus solicitud actualizado (#' . $id . ').');
    }

    public function saveInvoice(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id'  => 'required|string|max:64',
            'period'      => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'serie'       => 'nullable|string|max:20',
            'folio'       => 'nullable|string|max:40',
            'cfdi_uuid'   => 'nullable|string|max:80',
            'issued_date' => 'nullable|date',
            'amount_mxn'  => 'nullable|numeric|min:0|max:999999999',
            'notes'       => 'nullable|string|max:5000',
        ]);

        if (!Schema::connection($this->adm)->hasTable('billing_invoices')) {
            return back()->withErrors(['invoice' => 'No existe la tabla billing_invoices.']);
        }

        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];

        $amountCents = (int) round(((float) ($data['amount_mxn'] ?? 0)) * 100);

        $row = DB::connection($this->adm)->table('billing_invoices')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        $now = now();

        $payload = [
            'serie'       => $data['serie'] ?? null,
            'folio'       => $data['folio'] ?? null,
            'cfdi_uuid'   => $data['cfdi_uuid'] ?? null,
            'issued_date' => !empty($data['issued_date']) ? Carbon::parse((string) $data['issued_date'])->toDateString() : null,
            'status'      => 'issued',
            'notes'       => ($data['notes'] ?? null) ?: null,
            'updated_at'  => $now,
        ];

        $invCols = Schema::connection($this->adm)->getColumnListing('billing_invoices');
        $ilc = array_map('strtolower', $invCols);
        $invHas = static fn(string $c) => in_array(strtolower($c), $ilc, true);

        if ($invHas('amount_mxn')) {
            $payload['amount_mxn'] = round((float) ($data['amount_mxn'] ?? 0), 2);
        } elseif ($invHas('amount')) {
            $payload['amount'] = $amountCents;
        } elseif ($invHas('amount_cents')) {
            $payload['amount_cents'] = $amountCents;
        }

        if ($invHas('currency')) {
            $payload['currency'] = 'MXN';
        }

        if ($row) {
            DB::connection($this->adm)->table('billing_invoices')->where('id', (int) $row->id)->update($payload);

            if (Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
                DB::connection($this->adm)->table('billing_invoice_requests')
                    ->where('account_id', $accountId)
                    ->where('period', $period)
                    ->update(['status' => 'issued', 'cfdi_uuid' => $payload['cfdi_uuid'], 'updated_at' => $now]);
            }

            return back()->with('ok', 'Factura actualizada (account/period).');
        }

        $payload['account_id']  = $accountId;
        $payload['period']      = $period;
        $payload['created_at']  = $now;

        DB::connection($this->adm)->table('billing_invoices')->insert($payload);

        if (Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            DB::connection($this->adm)->table('billing_invoice_requests')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->update(['status' => 'issued', 'cfdi_uuid' => $payload['cfdi_uuid'], 'updated_at' => $now]);
        }

        return back()->with('ok', 'Factura registrada.');
    }

    // =========================================================
    // Tracking (open/click)
    // =========================================================

    public function trackOpen(string $emailId): Response
    {
        try {
            if (Schema::connection($this->adm)->hasTable('billing_email_logs')) {
                $cols = Schema::connection($this->adm)->getColumnListing('billing_email_logs');
                $lc   = array_map('strtolower', $cols);
                $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

                $select = ['id'];
                if ($has('open_count'))    $select[] = 'open_count';
                if ($has('first_open_at')) $select[] = 'first_open_at';
                if ($has('last_open_at'))  $select[] = 'last_open_at';

                $row = DB::connection($this->adm)->table('billing_email_logs')
                    ->where('email_id', $emailId)
                    ->first($select);

                if ($row) {
                    $now = now();
                    $upd = ['updated_at' => $now];

                    if ($has('open_count')) {
                        $upd['open_count'] = (int) (($row->open_count ?? 0) + 1);
                    }
                    if ($has('first_open_at')) {
                        $upd['first_open_at'] = ($row->first_open_at ?? null) ?: $now;
                    }
                    if ($has('last_open_at')) {
                        $upd['last_open_at'] = $now;
                    }

                    DB::connection($this->adm)->table('billing_email_logs')
                        ->where('id', (int) $row->id)
                        ->update($upd);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING_HUB][trackOpen] error', ['email_id' => $emailId, 'e' => $e->getMessage()]);
        }

        $gif = base64_decode('R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==') ?: '';
        return response($gif, 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    public function trackClick(Request $req, string $emailId): RedirectResponse
    {
        $target = '/';

        try {
            $u = (string) $req->query('u', '');
            if ($u !== '') {
                $decoded = urldecode($u);
                $target  = $this->sanitizeTrackingTarget($decoded);
            }

            if (Schema::connection($this->adm)->hasTable('billing_email_logs')) {
                $cols = Schema::connection($this->adm)->getColumnListing('billing_email_logs');
                $lc   = array_map('strtolower', $cols);
                $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

                $select = ['id'];
                if ($has('click_count'))     $select[] = 'click_count';
                if ($has('first_click_at'))  $select[] = 'first_click_at';
                if ($has('last_click_at'))   $select[] = 'last_click_at';

                $row = DB::connection($this->adm)->table('billing_email_logs')
                    ->where('email_id', $emailId)
                    ->first($select);

                if ($row) {
                    $now = now();
                    $upd = ['updated_at' => $now];

                    if ($has('click_count')) {
                        $upd['click_count'] = (int) (($row->click_count ?? 0) + 1);
                    }
                    if ($has('first_click_at')) {
                        $upd['first_click_at'] = ($row->first_click_at ?? null) ?: $now;
                    }
                    if ($has('last_click_at')) {
                        $upd['last_click_at'] = $now;
                    }

                    DB::connection($this->adm)->table('billing_email_logs')
                        ->where('id', (int) $row->id)
                        ->update($upd);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING_HUB][trackClick] error', ['email_id' => $emailId, 'e' => $e->getMessage()]);
        }

        return redirect()->to($target);
    }

    private function sanitizeTrackingTarget(mixed $u): string
    {
        $fallback = '/';

        if (!is_string($u)) return $fallback;

        $u = trim($u);
        if ($u === '') return $fallback;

        // Bloquea protocolo-relativo tipo //evil.com
        if (str_starts_with($u, '//')) return $fallback;

        // Permitir rutas internas: /admin/login, /cliente/...
        if (str_starts_with($u, '/')) return $u;

        $parts = @parse_url($u);
        if (!is_array($parts)) return $fallback;

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) return $fallback;

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') return $fallback;

        $allowed = $this->allowedTrackingHosts();
        if (!in_array($host, $allowed, true)) return $fallback;

        return $u;
    }

    private function allowedTrackingHosts(): array
    {
        $hosts = [];

        try {
            $rh = strtolower((string) request()->getHost());
            if ($rh !== '') $hosts[] = $rh;
        } catch (\Throwable $e) {
            // ignore
        }

        $appUrl  = (string) config('app.url');
        $appHost = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        if ($appHost !== '') $hosts[] = $appHost;

        $hosts[] = 'pactopia360.com';
        $hosts[] = 'www.pactopia360.com';

        $hosts = array_values(array_unique(array_filter($hosts)));
        return $hosts;
    }

    // =========================================================
    // Email payload builder (public)
    // =========================================================

    /**
     * Genera payload para la vista del correo y para logs.
     * $isPreview=true evita efectos colaterales (no genera checkout).
     *
     * @return array<string,mixed>
     */
    private function buildStatementEmailPayloadPublic(object $acc, string $accountId, string $period, string $emailId, bool $isPreview = false): array
    {
        $meta = $this->decodeMeta($acc->meta ?? null);

        $rs   = (string) ($acc->razon_social ?? $acc->name ?? 'Cliente');
        $rfc  = (string) ($acc->rfc ?? '');
        $to   = (string) ($acc->email ?? '');

        // Totales del estado (si existe)
        $cargoReal = 0.0;
        $abonoEc   = 0.0;

        if (Schema::connection($this->adm)->hasTable('estados_cuenta')) {
            $items = DB::connection($this->adm)->table('estados_cuenta')
                ->where('account_id', $accountId)
                ->where('periodo', $period)
                ->get();

            $cargoReal = (float) $items->sum('cargo');
            $abonoEc   = (float) $items->sum('abono');
        }

        $abonoPay = (float) $this->sumPaymentsPaidForAccountPeriod($accountId, $period);
        $abono    = $abonoEc + $abonoPay;

        $custom = $this->extractCustomAmountMxn($acc, $meta);
        if ($custom !== null && $custom > 0.00001) {
            $expected = $custom;
        } else {
            [$expected] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);
        }

        $totalShown = $cargoReal > 0 ? $cargoReal : (float) $expected;
        $saldo      = max(0.0, $totalShown - $abono);

        // Links base
        $portalUrl = Route::has('cliente.login') ? route('cliente.login') : url('/cliente/login');

        // tracking URLs
        $openPixelUrl = Route::has('admin.billing.hub.track_open')
            ? route('admin.billing.hub.track_open', ['emailId' => $emailId])
            : url('/admin/billing/hub/track/open/' . urlencode($emailId));

        $wrapClick = function (string $url) use ($emailId): string {
            $u = urlencode($url);
            if (Route::has('admin.billing.hub.track_click')) {
                return route('admin.billing.hub.track_click', ['emailId' => $emailId]) . '?u=' . $u;
            }
            return url('/admin/billing/hub/track/click/' . urlencode($emailId)) . '?u=' . $u;
        };

        // PDF / Estado de cuenta (si tienes ruta real, cámbiala aquí)
        $pdfUrl = Route::has('cliente.estado_cuenta')
            ? route('cliente.estado_cuenta') . '?period=' . urlencode($period)
            : url('/cliente/estado-de-cuenta') . '?period=' . urlencode($period);

        // Pay URL: en preview no generar checkout
        $payUrl = '';
        if (!$isPreview && $saldo > 0.00001) {
            // Esto solo construye link al HUB action (no crea checkout aquí)
            // (el checkout se crea con createPayLink en el HUB)
            $payUrl = Route::has('admin.billing.hub.paylink')
                ? route('admin.billing.hub.paylink') . '?account_id=' . urlencode($accountId) . '&period=' . urlencode($period)
                : url('/admin/billing/hub/paylink') . '?account_id=' . urlencode($accountId) . '&period=' . urlencode($period);
        }

        $subject = 'Pactopia360 · Estado de cuenta ' . $period . ' · ' . $rs;

        return [
            'brand' => [
                'name' => 'Pactopia360',
            ],
            'account' => [
                'id'           => $accountId,
                'razon_social' => $rs,
                'rfc'          => $rfc,
                'email'        => $to,
            ],
            'period' => $period,

            'totals' => [
                'cargo_real' => round($cargoReal, 2),
                'abono_ec'   => round($abonoEc, 2),
                'abono_pay'  => round($abonoPay, 2),
                'abono'      => round($abono, 2),
                'expected'   => round((float) $expected, 2),
                'total'      => round($totalShown, 2),
                'saldo'      => round($saldo, 2),
            ],

            'portal_url'     => $portalUrl,
            'pdf_url'        => $pdfUrl,
            'pay_url'        => $payUrl,

            'open_pixel_url' => $openPixelUrl,

            'pdf_track_url'    => $wrapClick($pdfUrl),
            'pay_track_url'    => $payUrl !== '' ? $wrapClick($payUrl) : '',
            'portal_track_url' => $wrapClick($portalUrl),

            'email_id'     => $emailId,
            'generated_at' => now(),
            'subject'      => $subject,
        ];
    }

    // =========================================================
    // Payments helpers
    // =========================================================

    private function sumPaymentsPaidForAccountPeriod(string $accountId, string $period): float
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return 0.0;
        }

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) {
            return 0.0;
        }

        $q = DB::connection($this->adm)->table('payments')
            ->where('account_id', $accountId)
            ->where('period', $period);

        if ($has('status')) {
            $q->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized']);
        }

        // IMPORTANTE:
        // NO filtrar provider. El HUB real está considerando pagos pagados
        // tanto stripe como manual.
        if ($has('amount_mxn')) {
            $sumMxn = (float) $q->sum('amount_mxn');
            return round(max(0.0, $sumMxn), 2);
        }

        if ($has('monto_mxn')) {
            $sumMxn = (float) $q->sum('monto_mxn');
            return round(max(0.0, $sumMxn), 2);
        }

        if ($has('amount')) {
            $sumCents = (float) $q->sum('amount');
            return round(max(0.0, $sumCents / 100.0), 2);
        }

        if ($has('amount_cents')) {
            $sumCents = (float) $q->sum('amount_cents');
            return round(max(0.0, $sumCents / 100.0), 2);
        }

        return 0.0;
    }

    /**
     * @param array<int,string> $accountIds
     * @return array<string,float>
     */
    private function sumPaymentsPaidByAccountForPeriod(array $accountIds, string $period): array
    {
        $out = [];

        if (empty($accountIds)) {
            return $out;
        }

        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return $out;
        }

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) {
            return $out;
        }

        $q = DB::connection($this->adm)->table('payments')
            ->whereIn('account_id', $accountIds)
            ->where('period', $period);

        if ($has('status')) {
            $q->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized']);
        }

        // IMPORTANTE:
        // NO filtrar provider. Deben entrar manual + stripe + cualquier provider pagado.
        if ($has('amount_mxn')) {
            $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(amount_mxn,0)) as mxn')
                ->groupBy('account_id')
                ->get();

            foreach ($rows as $r) {
                $aid = (string) ($r->aid ?? '');
                $mxn = (float) ($r->mxn ?? 0);
                if ($aid !== '') {
                    $out[$aid] = round(max(0.0, $mxn), 2);
                }
            }

            return $out;
        }

        if ($has('monto_mxn')) {
            $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(monto_mxn,0)) as mxn')
                ->groupBy('account_id')
                ->get();

            foreach ($rows as $r) {
                $aid = (string) ($r->aid ?? '');
                $mxn = (float) ($r->mxn ?? 0);
                if ($aid !== '') {
                    $out[$aid] = round(max(0.0, $mxn), 2);
                }
            }

            return $out;
        }

        if ($has('amount')) {
            $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(amount,0)) as cents')
                ->groupBy('account_id')
                ->get();

            foreach ($rows as $r) {
                $aid = (string) ($r->aid ?? '');
                $mxn = ((float) ($r->cents ?? 0)) / 100.0;
                if ($aid !== '') {
                    $out[$aid] = round(max(0.0, $mxn), 2);
                }
            }

            return $out;
        }

        if ($has('amount_cents')) {
            $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(amount_cents,0)) as cents')
                ->groupBy('account_id')
                ->get();

            foreach ($rows as $r) {
                $aid = (string) ($r->aid ?? '');
                $mxn = ((float) ($r->cents ?? 0)) / 100.0;
                if ($aid !== '') {
                    $out[$aid] = round(max(0.0, $mxn), 2);
                }
            }

            return $out;
        }

        return $out;
    }

    // =========================================================
    // Email logs helper (último enviado por cuenta/periodo)
    // =========================================================

    /**
     * @param array<int,string> $accountIds
     * @return array<string,string>
     */
    private function lastSentAtByAccountForPeriod(array $accountIds, string $period): array
    {
        $out = [];
        if (empty($accountIds)) return $out;
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) return $out;

        $cols = Schema::connection($this->adm)->getColumnListing('billing_email_logs');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) return $out;

        $q = DB::connection($this->adm)->table('billing_email_logs')
            ->whereIn('account_id', $accountIds)
            ->where('period', $period);

        if ($has('status')) {
            $q->whereIn('status', ['sent', 'delivered', 'ok']);
        }

        if ($has('sent_at')) {
            $rows = $q->selectRaw('account_id as aid, MAX(sent_at) as last_sent_at')
                ->groupBy('account_id')
                ->get();

            foreach ($rows as $r) {
                $aid = (string) ($r->aid ?? '');
                $ts  = $r->last_sent_at ?? null;
                if ($aid !== '' && !empty($ts)) $out[$aid] = (string) $ts;
            }
            return $out;
        }

        if ($has('updated_at')) {
            $rows = $q->selectRaw('account_id as aid, MAX(updated_at) as last_sent_at')
                ->groupBy('account_id')
                ->get();

            foreach ($rows as $r) {
                $aid = (string) ($r->aid ?? '');
                $ts  = $r->last_sent_at ?? null;
                if ($aid !== '' && !empty($ts)) $out[$aid] = (string) $ts;
            }
        }

        return $out;
    }

    /**
     * Tracking agregado por account_id/period.
     *
     * @param array<int|string> $accountIds
     * @return array<string, array<string, mixed>>
     */
    private function trackingByAccountForPeriod(array $accountIds, string $period): array
    {
        if (empty($accountIds)) return [];
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) return [];

        $rows = DB::connection($this->adm)->table('billing_email_logs')
            ->selectRaw('
                account_id as aid,
                SUM(COALESCE(open_count,0))   as open_count,
                SUM(COALESCE(click_count,0))  as click_count,
                MIN(first_open_at)            as first_open_at,
                MAX(last_open_at)             as last_open_at,
                MIN(first_click_at)           as first_click_at,
                MAX(last_click_at)            as last_click_at,
                MAX(sent_at)                  as last_sent_at
            ')
            ->where('period', '=', $period)
            ->whereIn('account_id', $accountIds)
            ->groupBy('account_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $aid = (string) ($r->aid ?? '');
            if ($aid === '') continue;

            $out[$aid] = [
                'open_count'     => (int) ($r->open_count ?? 0),
                'click_count'    => (int) ($r->click_count ?? 0),
                'first_open_at'  => $r->first_open_at,
                'last_open_at'   => $r->last_open_at,
                'first_click_at' => $r->first_click_at,
                'last_click_at'  => $r->last_click_at,
                'last_sent_at'   => $r->last_sent_at,
            ];
        }

        return $out;
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * @return array<int,string>
     */
    public function parseToList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];

        $raw = str_replace([';', "\n", "\r", "\t"], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, static fn($x) => $x !== '');

        $out = [];
        foreach ($parts as $p) {
            if (preg_match('/<([^>]+)>/', $p, $m)) $p = trim((string) $m[1]);
            if (filter_var($p, FILTER_VALIDATE_EMAIL)) $out[] = strtolower($p);
        }

        $out = array_values(array_unique($out));
        return array_slice($out, 0, 10);
    }

    /**
     * @return array<int,string>
     */
    private function parseIdCsv(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];

        $raw = str_replace([";", "\n", "\r", "\t", " "], ",", $raw);
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, static fn($x) => $x !== '');

        $out = [];
        foreach ($parts as $p) {
            if (!preg_match('/^[a-zA-Z0-9\-\_]{1,80}$/', $p)) continue;
            $out[] = $p;
        }

        $out = array_values(array_unique($out));
        return array_slice($out, 0, 300);
    }

    /**
     * @return array<string,mixed>
     */
    public function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) return $meta;
        if (is_object($meta)) return (array) $meta;

        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
        }

        return [];
    }

    private function mapTarifaPillToCss(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if (str_contains($raw, 'vigente')) return 'pill-info';
        if (str_contains($raw, 'proximo') || str_contains($raw, 'próximo') || str_contains($raw, 'next')) return 'pill-warn';
        if ($raw === 'base') return 'pill-dim';
        return 'pill-dim';
    }

    /**
     * @return array{0:float,1:string,2:string}
     */
    public function resolveEffectiveAmountForPeriodFromMeta(array $meta, string $period, ?string $payAllowed): array
    {
        $billing = (array) ($meta['billing'] ?? []);

        $rawBase = $billing['amount_mxn'] ?? ($billing['amount'] ?? null);
        $base = $this->toFloat($rawBase) ?? 0.0;

        $ov = (array) ($billing['override'] ?? []);
        $override = $this->toFloat(
            $ov['amount_mxn'] ?? ($billing['override_amount_mxn'] ?? null)
        ) ?? 0.0;

        $eff = strtolower(trim((string) ($ov['effective'] ?? ($billing['override_effective'] ?? ''))));
        if (!in_array($eff, ['now', 'next'], true)) $eff = '';

        $apply = false;
        if ($override > 0) {
            if ($eff === 'now') {
                $apply = true;
            } elseif ($eff === 'next') {
                $apply = ($payAllowed && $period >= $payAllowed);
            }
        }

        $effective = $apply ? $override : $base;
        $label = $apply ? 'Tarifa ajustada' : 'Tarifa base';
        $pillText = $apply
            ? (($eff === 'next') ? 'Ajuste (próximo periodo)' : 'Ajuste (vigente)')
            : 'Base';

        return [round((float) $effective, 2), (string) $label, (string) $pillText];
    }

    private function toFloat(mixed $v): ?float
    {
        if ($v === null) return null;
        if (is_float($v) || is_int($v)) return (float) $v;

        if (is_string($v)) {
            $s = trim($v);
            if ($s === '') return null;

            $s = str_replace(['$', ',', 'MXN', 'mxn', ' '], '', $s);
            if (!is_numeric($s)) return null;
            return (float) $s;
        }

        if (is_numeric($v)) return (float) $v;
        return null;
    }

    private function extractCustomAmountMxn(object $row, array $meta): ?float
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
            if ($n !== null && $n > 0.00001) return $n;
        }

        foreach ([
            'override_amount_mxn', 'custom_amount_mxn', 'billing_amount_mxn', 'amount_mxn', 'precio_mxn', 'monto_mxn', 'license_amount_mxn',
            'billing_amount', 'amount', 'precio', 'monto',
        ] as $prop) {
            if (isset($row->{$prop})) {
                $n = $this->toFloat($row->{$prop});
                if ($n !== null && $n > 0.00001) return $n;
            }
        }

        return null;
    }



    private function insertEmailLog(array $row): int
    {
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            throw new \RuntimeException('No existe billing_email_logs en ' . $this->adm);
        }

        $cols = Schema::connection($this->adm)->getColumnListing('billing_email_logs');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

        $ins = [];
        if ($has('email_id'))     $ins['email_id']     = (string) ($row['email_id'] ?? Str::ulid());
        if ($has('account_id'))   $ins['account_id']   = (string) ($row['account_id'] ?? '');
        if ($has('period'))       $ins['period']       = (string) ($row['period'] ?? '');

        if ($has('email'))        $ins['email']        = $row['email'] ?? null;
        if ($has('to_list'))      $ins['to_list']      = $row['to_list'] ?? null;

        if ($has('template'))     $ins['template']     = (string) ($row['template'] ?? 'statement');
        if ($has('status'))       $ins['status']       = (string) ($row['status'] ?? 'queued');
        if ($has('provider'))     $ins['provider']     = $row['provider'] ?? null;

        if ($has('provider_message_id')) $ins['provider_message_id'] = $row['provider_message_id'] ?? null;

        if ($has('subject'))      $ins['subject']      = $row['subject'] ?? null;
        if ($has('payload'))      $ins['payload']      = $row['payload'] ?? null;
        if ($has('meta'))         $ins['meta']         = $row['meta'] ?? null;

        if ($has('queued_at'))    $ins['queued_at']    = $row['queued_at'] ?? now();
        if ($has('sent_at'))      $ins['sent_at']      = $row['sent_at'] ?? null;
        if ($has('failed_at'))    $ins['failed_at']    = $row['failed_at'] ?? null;

        if ($has('open_count'))   $ins['open_count']   = (int) ($row['open_count'] ?? 0);
        if ($has('click_count'))  $ins['click_count']  = (int) ($row['click_count'] ?? 0);

        $ins['created_at'] = now();
        $ins['updated_at'] = now();

           return (int) DB::connection($this->adm)->table('billing_email_logs')->insertGetId($ins);
    }

    /**
     * Compat: usado por Cliente/AccountBillingController cuando se apoya en HUB.
     * Devuelve el costo mensual (en centavos) para un periodo YYYY-MM.
     *
     * Regla:
     * - Si hay monto personalizado (custom/override) lo usa.
     * - Si no, usa billing.amount_mxn y/o override (resolveEffectiveAmountForPeriodFromMeta).
     * - Si no se puede resolver, devuelve 0.
     */
    public function resolveMonthlyCentsForPeriodFromAdminAccount(
        int $accountId,
        string $period,
        ?string $lastPaid = null,
        ?string $payAllowed = null
    ): int {
        try {
            if ($accountId <= 0) return 0;

            if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
                $period = now()->format('Y-m');
            }

            if (!Schema::connection($this->adm)->hasTable('accounts')) {
                return 0;
            }

            $accCols = Schema::connection($this->adm)->getColumnListing('accounts');
            $lc = array_map('strtolower', $accCols);
            $has = static fn(string $c) => in_array(strtolower($c), $lc, true);

            $select = ['id'];
            foreach (['meta', 'plan', 'plan_actual', 'modo_cobro', 'email', 'rfc', 'razon_social', 'name'] as $c) {
                if ($has($c)) $select[] = $c;
            }
            foreach ([
                'billing_amount_mxn', 'amount_mxn', 'precio_mxn', 'monto_mxn',
                'override_amount_mxn', 'custom_amount_mxn', 'license_amount_mxn',
                'billing_amount', 'amount', 'precio', 'monto',
            ] as $c) {
                if ($has($c)) $select[] = $c;
            }

            $acc = DB::connection($this->adm)->table('accounts')
                ->where('id', $accountId)
                ->first(array_values(array_unique($select)));

            if (!$acc) return 0;

            $meta = $this->decodeMeta($acc->meta ?? null);

            // 1) custom/override
            $custom = $this->extractCustomAmountMxn($acc, $meta);
            if ($custom !== null && $custom > 0.00001) {
                return max(0, (int) round(((float) $custom) * 100));
            }

            // 2) base/override por meta
            [$expected] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, $payAllowed);

            $expected = (float) $expected;
            if ($expected <= 0.00001) return 0;

            return max(0, (int) round($expected * 100));
        } catch (\Throwable $e) {
            return 0;
        }
    }

       public function payLink(Request $req): Response|RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
        ]);

        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        if (!$acc) {
            return response('Cuenta no encontrada.', 404)
                ->header('Cache-Control', 'no-store, max-age=0, public')
                ->header('Pragma', 'no-cache');
        }

        $items = collect();
        if (Schema::connection($this->adm)->hasTable('estados_cuenta')) {
            $items = DB::connection($this->adm)->table('estados_cuenta')
                ->where('account_id', $accountId)
                ->where('periodo', $period)
                ->get();
        }

        $cargoReal = (float) $items->sum('cargo');
        $abonoEc   = (float) $items->sum('abono');

        $abonoPay  = (float) $this->sumPaymentsPaidForAccountPeriod($accountId, $period);
        $abono     = $abonoEc + $abonoPay;

        $meta   = $this->decodeMeta($acc->meta ?? null);
        $custom = $this->extractCustomAmountMxn($acc, $meta);

        if ($custom !== null && $custom > 0.00001) {
            $expected = $custom;
        } else {
            [$expected] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);
        }

        $totalShown = $cargoReal > 0 ? $cargoReal : (float) $expected;
        $saldo      = max(0.0, $totalShown - $abono);

        if ($saldo <= 0.00001) {
            return response('No hay saldo pendiente para ese periodo.', 200)
                ->header('Cache-Control', 'no-store, max-age=0, public')
                ->header('Pragma', 'no-cache');
        }

        try {
            [$url, $sessionId] = $this->createStripeCheckoutForStatement($acc, $period, $saldo);

            return redirect()
                ->away((string) $url)
                ->withHeaders([
                    'Cache-Control' => 'no-store, max-age=0, public',
                    'Pragma'        => 'no-cache',
                ]);
        } catch (\Throwable $e) {
            return response('No se pudo generar liga: ' . $e->getMessage(), 500);
        }
    }

        private function connClientes(): string
    {
        return (string) (
            config('p360.conn.clientes')
            ?: config('p360.conn.clients')
            ?: 'mysql_clientes'
        );
    }

    /**
     * Soporta account_id en billing_statements tanto por ID admin como por UUID de cliente.
     *
     * @param  string|int  $adminAccountId
     * @return array<int,string>
     */
    private function billingStatementRefsForAdminAccount(string|int $adminAccountId): array
    {
        $aid = trim((string) $adminAccountId);
        if ($aid === '') return [];

        $refs = [$aid];

        if (ctype_digit($aid)) {
            $refs[] = (string) ((int) $aid);
        }

        try {
            $cli = $this->connClientes();

            if (
                Schema::connection($cli)->hasTable('cuentas_cliente') &&
                Schema::connection($cli)->hasColumn('cuentas_cliente', 'admin_account_id')
            ) {
                $uuids = DB::connection($cli)->table('cuentas_cliente')
                    ->where('admin_account_id', (int) $aid)
                    ->limit(500)
                    ->pluck('id')
                    ->map(fn ($x) => trim((string) $x))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                foreach ($uuids as $uuid) {
                    $refs[] = $uuid;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING_HUB] billingStatementRefsForAdminAccount failed', [
                'account_id' => $aid,
                'err'        => $e->getMessage(),
            ]);
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($x) => trim((string) $x),
            $refs
        ))));
    }

    /**
     * Carga billing_statements del periodo y los mapea al account_id admin.
     * PRIORIDAD SOT: si existe statement, se usa en lugar de recalcular con estados_cuenta/payments.
     *
     * @param  array<int|string>  $accountIds
     * @return array<string, array<string,mixed>>
     */
    private function loadBillingStatementsMirrorMap(array $accountIds, string $period): array
    {
        $out = [];

        if (empty($accountIds)) return $out;
        if (!Schema::connection($this->adm)->hasTable('billing_statements')) return $out;

        $refToAdmin = [];
        $allRefs    = [];

        foreach ($accountIds as $aid) {
            $aidStr = trim((string) $aid);
            if ($aidStr === '') continue;

            $refs = $this->billingStatementRefsForAdminAccount($aidStr);
            foreach ($refs as $ref) {
                $refToAdmin[(string) $ref] = $aidStr;
                $allRefs[] = (string) $ref;
            }
        }

        $allRefs = array_values(array_unique(array_filter($allRefs)));
        if (!$allRefs) return $out;

        $rows = DB::connection($this->adm)->table('billing_statements')
            ->whereIn('account_id', $allRefs)
            ->where('period', $period)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'account_id',
                'period',
                'status',
                'total_cargo',
                'total_abono',
                'saldo',
                'paid_at',
                'created_at',
                'updated_at',
            ]);

        foreach ($rows as $row) {
            $ref = trim((string) ($row->account_id ?? ''));
            if ($ref === '' || !isset($refToAdmin[$ref])) continue;

            $adminId = $refToAdmin[$ref];

            if (!isset($out[$adminId])) {
                $out[$adminId] = [
                    'id'          => (int) ($row->id ?? 0),
                    'account_ref' => $ref,
                    'period'      => (string) ($row->period ?? ''),
                    'status'      => strtolower(trim((string) ($row->status ?? 'pending'))),
                    'total_cargo' => round((float) ($row->total_cargo ?? 0), 2),
                    'total_abono' => round((float) ($row->total_abono ?? 0), 2),
                    'saldo'       => round((float) ($row->saldo ?? 0), 2),
                    'paid_at'     => $row->paid_at ?? null,
                    'updated_at'  => $row->updated_at ?? null,
                ];
            }
        }

        return $out;
    }

    /**
     * Suma saldos abiertos de periodos anteriores desde billing_statements.
     * Esto evita que el HUB invente/deforme adeudos previos.
     *
     * @param  array<int|string>  $accountIds
     * @return array<string, array{prev_balance: float, prev_period: ?string}>
     */
    private function loadPreviousOpenStatementsMap(array $accountIds, string $period): array
    {
        $out = [];

        if (empty($accountIds)) return $out;
        if (!Schema::connection($this->adm)->hasTable('billing_statements')) return $out;

        $refToAdmin = [];
        $allRefs    = [];

        foreach ($accountIds as $aid) {
            $aidStr = trim((string) $aid);
            if ($aidStr === '') continue;

            $refs = $this->billingStatementRefsForAdminAccount($aidStr);
            foreach ($refs as $ref) {
                $refToAdmin[(string) $ref] = $aidStr;
                $allRefs[] = (string) $ref;
            }
        }

        $allRefs = array_values(array_unique(array_filter($allRefs)));
        if (!$allRefs) return $out;

        $rows = DB::connection($this->adm)->table('billing_statements')
            ->whereIn('account_id', $allRefs)
            ->where('period', '<', $period)
            ->orderByDesc('period')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'account_id',
                'period',
                'status',
                'total_cargo',
                'total_abono',
                'saldo',
                'paid_at',
                'updated_at',
            ]);

        $seen = [];

        foreach ($rows as $row) {
            $ref = trim((string) ($row->account_id ?? ''));
            if ($ref === '' || !isset($refToAdmin[$ref])) continue;

            $adminId = $refToAdmin[$ref];
            $p       = trim((string) ($row->period ?? ''));

            if ($p === '') continue;

            $key = $adminId . '|' . $p;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $saldo   = round((float) ($row->saldo ?? 0), 2);
            $status  = strtolower(trim((string) ($row->status ?? 'pending')));
            $paidAt  = $row->paid_at ?? null;
            $cargo   = round((float) ($row->total_cargo ?? 0), 2);
            $abono   = round((float) ($row->total_abono ?? 0), 2);

            $isPaid = false;
            if ($paidAt) $isPaid = true;
            if ($status === 'paid' || $status === 'pagado') $isPaid = true;
            if ($saldo <= 0.0001 && ($cargo > 0.0001 || $abono > 0.0001)) $isPaid = true;

            if ($isPaid) {
                continue;
            }

            if (!isset($out[$adminId])) {
                $out[$adminId] = [
                    'prev_balance' => 0.0,
                    'prev_period'  => null,
                ];
            }

            $out[$adminId]['prev_balance'] += max(0.0, $saldo);

            if (empty($out[$adminId]['prev_period'])) {
                $out[$adminId]['prev_period'] = $p;
            }
        }

        foreach ($out as $aid => $vals) {
            $out[$aid]['prev_balance'] = round((float) ($vals['prev_balance'] ?? 0), 2);
        }

        return $out;
    }

}
