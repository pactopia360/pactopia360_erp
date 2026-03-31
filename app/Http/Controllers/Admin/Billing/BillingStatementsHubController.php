<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\Billing\BillingStatementsHubController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Mail\Admin\Billing\StatementAccountPeriodMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
    private string $cli;
    private StripeClient $stripe;

    /** @var array<string, string|null> */
    private array $cacheLastPaid = [];

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $this->cli = (string) (
            config('p360.conn.clientes')
            ?: config('p360.conn.clients')
            ?: 'mysql_clientes'
        );

        $secret = (string) config('services.stripe.secret');
        $this->stripe = new StripeClient($secret ?: '');
    }

    // =========================================================
    // INDEX
    // =========================================================

    public function index(Request $req): View
    {
        $q = trim((string) $req->get('q', ''));

        $period = (string) $req->get('period', now()->format('Y-m'));
        if (!$this->isValidPeriod($period)) {
            $period = now()->format('Y-m');
        }

        $periodFrom = trim((string) $req->get('period_from', ''));
        $periodTo   = trim((string) $req->get('period_to', ''));

        if (!$this->isValidPeriod($periodFrom)) {
            $periodFrom = '';
        }

        if (!$this->isValidPeriod($periodTo)) {
            $periodTo = '';
        }

        if ($periodFrom !== '' && $periodTo !== '' && strcmp($periodFrom, $periodTo) > 0) {
            [$periodFrom, $periodTo] = [$periodTo, $periodFrom];
        }

        $periodLabel = $period;
        if ($periodFrom !== '' || $periodTo !== '') {
            $periodLabel = ($periodFrom !== '' ? $periodFrom : '—') . ' → ' . ($periodTo !== '' ? $periodTo : '—');
        }

        $accountId = trim((string) $req->get('accountId', ''));
        $accountId = $accountId !== '' ? $accountId : null;

        $perPage = (int) $req->get('perPage', 25);
        $allowedPerPage = [10, 25, 50, 100, 200, 250, 500, 1000];
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
            || ((string) $req->get('includeAnnual', '') === '1')
            || ((string) $req->get('include_annual', '') === '1');

        $onlySelected = $req->boolean('only_selected')
            || $req->boolean('onlySelected')
            || ((string) $req->get('only_selected', '') === '1');

        $selectedIds = $this->parseSelectedIds($req->get('ids'));
        if (count($selectedIds) > 500) {
            $selectedIds = array_slice($selectedIds, 0, 500);
        }

        if ($onlySelected && empty($selectedIds)) {
            return view('admin.billing.statements.index', [
                'rows'         => collect(),
                'q'            => $q,
                'period'       => $period,
                'periodFrom'   => $periodFrom,
                'periodTo'     => $periodTo,
                'periodLabel'  => $periodLabel,
                'accountId'    => $accountId,
                'status'       => $status,
                'perPage'      => $perPage,
                'onlySelected' => true,
                'idsCsv'       => '',
                'error'        => 'Filtro "solo seleccionadas" activo, pero no recibí IDs (ids).',
                'kpis'         => $this->emptyKpis(),
            ]);
        }

        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            return view('admin.billing.statements.index', [
                'rows'         => collect(),
                'q'            => $q,
                'period'       => $period,
                'periodFrom'   => $periodFrom,
                'periodTo'     => $periodTo,
                'periodLabel'  => $periodLabel,
                'accountId'    => $accountId,
                'status'       => $status,
                'perPage'      => $perPage,
                'onlySelected' => $onlySelected,
                'idsCsv'       => $onlySelected ? implode(',', $selectedIds) : '',
                'error'        => 'Falta la tabla accounts en la conexión admin.',
                'kpis'         => $this->emptyKpis(),
            ]);
        }

        $accounts = $this->loadAccountsForIndex(
            q: $q,
            period: $period,
            periodFrom: $periodFrom,
            periodTo: $periodTo,
            accountId: $accountId,
            includeAnnual: $includeAnnual,
            onlySelected: $onlySelected,
            selectedIds: $selectedIds
        );

        $accountIds = $accounts->pluck('id')
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values()
            ->all();

        $mirrorCurrent = ($periodFrom !== '' || $periodTo !== '')
            ? $this->loadBillingStatementsMirrorMapForRange($accountIds, $periodFrom, $periodTo)
            : $this->loadBillingStatementsMirrorMap($accountIds, $period);

        $mirrorPrev = ($periodFrom !== '' || $periodTo !== '')
            ? []
            : $this->loadPreviousOpenStatementsMap($accountIds, $period);

        $payAgg = ($periodFrom !== '' || $periodTo !== '')
            ? $this->sumPaymentsPaidByAccountForRange($accountIds, $periodFrom, $periodTo)
            : $this->sumPaymentsPaidByAccountForPeriod($accountIds, $period);

        $emailTracking = ($periodFrom !== '' || $periodTo !== '')
            ? $this->trackingByAccountForRange($accountIds, $periodFrom, $periodTo)
            : $this->trackingByAccountForPeriod($accountIds, $period);

        $ovMap = ($periodFrom !== '' || $periodTo !== '')
            ? $this->loadStatusOverridesMapForRange($accountIds, $periodFrom, $periodTo)
            : $this->loadStatusOverridesMap($accountIds, $period);

        $rowsCollection = $accounts->map(function (object $acc) use (
            $period,
            $periodFrom,
            $periodTo,
            $mirrorCurrent,
            $mirrorPrev,
            $payAgg,
            $emailTracking,
            $ovMap
        ) {
            $aid = trim((string) ($acc->id ?? ''));
            $meta = $this->decodeMeta($acc->meta ?? null);

            $custom = $this->extractCustomAmountMxn($acc, $meta);
            [$expected, $tarifaLabel, $tarifaPill] = $custom !== null && $custom > 0.00001
                ? [round($custom, 2), 'PERSONALIZADO', 'info']
                : $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);

            $row = clone $acc;

            $statement   = $mirrorCurrent[$aid] ?? null;
            $prevInfo    = $mirrorPrev[$aid] ?? ['prev_balance' => 0.0, 'prev_period' => null];
            $payPaid     = (float) ($payAgg[$aid] ?? 0.0);
            $track       = $emailTracking[$aid] ?? [];
            $ov          = $ovMap[$aid] ?? null;
            $rangeActive = ($periodFrom !== '' || $periodTo !== '');

            if ($statement) {
                $totalCurrent = (float) ($statement['total_cargo'] ?? 0);
                $abonoMirror  = (float) ($statement['total_abono'] ?? 0);
                $saldoCurrent = (float) ($statement['saldo'] ?? max(0.0, $totalCurrent - $abonoMirror));

                $statusPago = $this->normalizeStatus((string) ($statement['status'] ?? 'pendiente'));
                if ($statusPago === 'sin_mov' && $totalCurrent > 0.00001) {
                    $statusPago = $saldoCurrent <= 0.00001 ? 'pagado' : 'pendiente';
                }

                $lastPaid = $statement['paid_at']
                    ? $this->parseToPeriod($statement['paid_at'])
                    : $this->resolveLastPaidPeriodForAccount($aid, $meta);

                $payAllowed = $lastPaid
                    ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
                    : ($periodTo !== '' ? $periodTo : $period);

                $row->cargo          = round($totalCurrent, 2);
                $row->expected_total = round((float) $expected, 2);
                $row->total_shown    = round($totalCurrent, 2);

                $row->abono_edo = round($abonoMirror, 2);
                $row->abono_pay = round(max(0.0, $payPaid), 2);
                $row->abono     = round($abonoMirror, 2);

                $row->saldo_current = round(max(0.0, $saldoCurrent), 2);
                $row->saldo_shown   = round(max(0.0, $saldoCurrent), 2);
                $row->saldo         = round(max(0.0, $saldoCurrent), 2);

                $row->prev_balance = round((float) ($prevInfo['prev_balance'] ?? 0.0), 2);
                $row->prev_period  = $prevInfo['prev_period'] ?? null;
                $row->total_due    = round(max(0.0, $row->saldo_current + $row->prev_balance), 2);

                $row->status_pago = $statusPago;
                $row->status_auto = $statusPago;

                $row->last_paid        = $lastPaid;
                $row->pay_allowed      = $payAllowed;
                $row->pay_last_paid_at = $statement['paid_at'] ?? null;
                $row->pay_due_date     = null;
                $row->pay_method       = null;
                $row->pay_provider     = null;
                $row->pay_status       = $statusPago;

                $row->tarifa_label = (string) $tarifaLabel;
                $row->tarifa_pill  = (string) $tarifaPill;
                $row->period       = $rangeActive
                    ? (($statement['period_from'] ?? '') . ' → ' . ($statement['period_to'] ?? ''))
                    : ($statement['period'] ?? $period);

                if ($ov) {
                    if (!empty($ov['status_override'])) {
                        $row->status_override = $ov['status_override'];
                        $row->status_pago     = $ov['status_override'];
                    }

                    if (!empty($ov['pay_method'])) {
                        $row->ov_pay_method = $ov['pay_method'];
                        $row->pay_method    = $ov['pay_method'];
                    }

                    if (!empty($ov['pay_provider'])) {
                        $row->ov_pay_provider = $ov['pay_provider'];
                        $row->pay_provider    = $ov['pay_provider'];
                    }

                    if (!empty($ov['pay_status'])) {
                        $row->ov_pay_status = $ov['pay_status'];
                        $row->pay_status    = $ov['pay_status'];
                    }

                    if (!empty($ov['paid_at'])) {
                        $row->pay_last_paid_at = $ov['paid_at'];
                    }
                }
            } else {
                $totalCurrent = (float) $expected;
                $abono        = round(max(0.0, $payPaid), 2);
                $saldoCurrent = round(max(0.0, $totalCurrent - $abono), 2);
                $prevBalance  = round((float) ($prevInfo['prev_balance'] ?? 0.0), 2);
                $totalDue     = round(max(0.0, $saldoCurrent + $prevBalance), 2);

                $statusPago = 'pendiente';
                if ($totalCurrent <= 0.00001 && $prevBalance <= 0.00001) {
                    $statusPago = 'sin_mov';
                } elseif ($totalDue <= 0.00001) {
                    $statusPago = 'pagado';
                } elseif ($prevBalance > 0.00001) {
                    $statusPago = 'vencido';
                } elseif ($abono > 0.00001 && $saldoCurrent > 0.00001) {
                    $statusPago = 'parcial';
                }

                $lastPaid = $this->resolveLastPaidPeriodForAccount($aid, $meta);
                $payAllowed = $lastPaid
                    ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
                    : ($periodTo !== '' ? $periodTo : $period);

                $row->cargo          = round($totalCurrent, 2);
                $row->expected_total = round((float) $expected, 2);
                $row->total_shown    = round($totalCurrent, 2);

                $row->abono_edo = 0.0;
                $row->abono_pay = round($abono, 2);
                $row->abono     = round($abono, 2);

                $row->saldo_current = round($saldoCurrent, 2);
                $row->saldo_shown   = round($saldoCurrent, 2);
                $row->saldo         = round($saldoCurrent, 2);

                $row->prev_balance = round($prevBalance, 2);
                $row->prev_period  = $prevInfo['prev_period'] ?? null;
                $row->total_due    = round($totalDue, 2);

                $row->status_pago = $statusPago;
                $row->status_auto = $statusPago;

                $row->last_paid        = $lastPaid;
                $row->pay_allowed      = $payAllowed;
                $row->pay_last_paid_at = null;
                $row->pay_due_date     = null;
                $row->pay_method       = null;
                $row->pay_provider     = null;
                $row->pay_status       = $statusPago;

                $row->tarifa_label = (string) $tarifaLabel;
                $row->tarifa_pill  = (string) $tarifaPill;
                $row->period       = $rangeActive
                    ? (($periodFrom !== '' ? $periodFrom : $period) . ' → ' . ($periodTo !== '' ? $periodTo : $period))
                    : $period;

                if ($ov) {
                    if (!empty($ov['status_override'])) {
                        $row->status_override = $ov['status_override'];
                        $row->status_pago     = $ov['status_override'];
                    }

                    if (!empty($ov['pay_method'])) {
                        $row->ov_pay_method = $ov['pay_method'];
                        $row->pay_method    = $ov['pay_method'];
                    }

                    if (!empty($ov['pay_provider'])) {
                        $row->ov_pay_provider = $ov['pay_provider'];
                        $row->pay_provider    = $ov['pay_provider'];
                    }

                    if (!empty($ov['pay_status'])) {
                        $row->ov_pay_status = $ov['pay_status'];
                        $row->pay_status    = $ov['pay_status'];
                    }

                    if (!empty($ov['paid_at'])) {
                        $row->pay_last_paid_at = $ov['paid_at'];
                    }
                }
            }

            $row->tracking_open_count   = (int) ($track['open_count'] ?? 0);
            $row->tracking_click_count  = (int) ($track['click_count'] ?? 0);
            $row->tracking_last_sent_at = $track['last_sent_at'] ?? null;

            return $row;
        });

        if ($status !== 'all') {
            $rowsCollection = $rowsCollection
                ->filter(fn ($x) => strtolower(trim((string) ($x->status_pago ?? ''))) === $status)
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

        $kpis = $this->buildKpis($rows->getCollection());

        return view('admin.billing.statements.index', [
            'rows'         => $rows,
            'q'            => $q,
            'period'       => $period,
            'periodFrom'   => $periodFrom,
            'periodTo'     => $periodTo,
            'periodLabel'  => $periodLabel,
            'accountId'    => $accountId,
            'status'       => $status,
            'perPage'      => $perPage,
            'onlySelected' => $onlySelected,
            'idsCsv'       => $onlySelected ? implode(',', $selectedIds) : '',
            'error'        => null,
            'kpis'         => $kpis,
        ]);
    }
    

    // =========================================================
    // ACCIONES MASIVAS
    // =========================================================

    public function bulkSend(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'period'      => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'account_ids' => 'required|string|max:20000',
            'to'          => 'nullable|string|max:2000',
            'mode'        => 'nullable|string|max:20',
        ]);

        $period = (string) $data['period'];
        $ids    = $this->parseIdCsv((string) $data['account_ids']);

        if (empty($ids)) {
            return back()->withErrors(['bulk' => 'No hay cuentas seleccionadas.']);
        }

        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            return back()->withErrors(['bulk' => 'No existe tabla accounts.']);
        }

        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            return back()->withErrors(['bulk' => 'No existe billing_email_logs.']);
        }

        $mode = strtolower(trim((string) ($data['mode'] ?? 'now')));
        if (!in_array($mode, ['now', 'queue'], true)) {
            $mode = 'now';
        }

        $overrideTos = $this->parseToList(trim((string) ($data['to'] ?? '')));

        $accounts = DB::connection($this->adm)->table('accounts')
            ->select(['id', 'email', 'rfc', 'razon_social', 'name', 'meta'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $sent = 0;
        $queued = 0;
        $failed = 0;

        foreach ($ids as $aid) {
            $acc = $accounts[$aid] ?? null;
            if (!$acc) {
                $failed++;
                continue;
            }

            $tos = !empty($overrideTos)
                    ? $overrideTos
                    : $this->resolveRecipientsForAccount((string) $aid, (string) ($acc->email ?? ''));

            if (empty($tos)) {
                $failed++;
                continue;
            }

            $emailId = (string) Str::ulid();

            try {
                // ✅ Generar payload antes del log para no insertar subject NULL
                $payload = $this->buildStatementEmailPayloadPublic($acc, (string) $aid, $period, $emailId);
                $subject = trim((string) ($payload['subject'] ?? ''));
                if ($subject === '') {
                    $subject = 'Pactopia360 · Estado de cuenta ' . $period . ' · ' . (string) ($acc->razon_social ?? $acc->name ?? 'Cliente');
                }

                $logId = $this->insertEmailLog([
                    'email_id'   => $emailId,
                    'account_id' => (string) $aid,
                    'period'     => $period,
                    'email'      => $tos[0] ?? null,
                    'to_list'    => implode(',', $tos),
                    'template'   => 'emails.admin.billing.statement_account_period',
                    'status'     => 'queued',
                    'provider'   => config('mail.default') ?: 'smtp',
                    'subject'    => $subject,
                    'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
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

                Mail::to($tos)->send(new StatementAccountPeriodMail((string) $aid, $period, $payload));

                DB::connection($this->adm)->table('billing_email_logs')
                    ->where('id', $logId)
                    ->update([
                        'status'     => 'sent',
                        'sent_at'    => now(),
                        'updated_at' => now(),
                    ]);

                $sent++;
            } catch (\Throwable $e) {
                $failed++;

                if (!empty($logId)) {
                    DB::connection($this->adm)->table('billing_email_logs')
                        ->where('id', $logId)
                        ->update([
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

                Log::warning('[BILLING_HUB][bulkSend] fallo', [
                    'account_id' => (string) $aid,
                    'period'     => $period,
                    'err'        => $e->getMessage(),
                ]);
            }
        }

        return back()->with('ok', "Bulk envío listo. sent={$sent}, queued={$queued}, failed={$failed}.");
    }

    // =========================================================
    // EMAIL
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
            $tos = $this->resolveRecipientsForAccount($accountId, (string) ($acc->email ?? ''));
}

        if (empty($tos)) {
            return back()->withErrors(['to' => 'No hay correos destino.']);
        }

        $emailId = (string) Str::ulid();

        // ✅ Generar payload primero para tener subject válido antes del insert del log
        $payload = $this->buildStatementEmailPayloadPublic($acc, $accountId, $period, $emailId);
        $subject = trim((string) ($payload['subject'] ?? ''));
        if ($subject === '') {
            $subject = 'Pactopia360 · Estado de cuenta ' . $period . ' · ' . (string) ($acc->razon_social ?? $acc->name ?? 'Cliente');
        }

        $logId = $this->insertEmailLog([
            'email_id'   => $emailId,
            'account_id' => $accountId,
            'period'     => $period,
            'email'      => $tos[0] ?? null,
            'to_list'    => implode(',', $tos),
            'template'   => 'emails.admin.billing.statement_account_period',
            'status'     => 'queued',
            'provider'   => config('mail.default') ?: 'smtp',
            'subject'    => $subject,
            'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'meta'       => json_encode([
                'source'     => 'admin_send_now',
                'account_id' => $accountId,
                'period'     => $period,
            ], JSON_UNESCAPED_UNICODE),
            'queued_at'  => now(),
        ]);

        try {
            Mail::to($tos)->send(new StatementAccountPeriodMail($accountId, $period, $payload));

            DB::connection($this->adm)->table('billing_email_logs')
                ->where('id', $logId)
                ->update([
                    'status'     => 'sent',
                    'sent_at'    => now(),
                    'updated_at' => now(),
                ]);

            return back()->with('ok', 'Correo enviado. Tracking activo (email_id=' . $emailId . ').');
        } catch (\Throwable $e) {
            DB::connection($this->adm)->table('billing_email_logs')
                ->where('id', $logId)
                ->update([
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
            $tos = $this->resolveRecipientsForAccount($accountId, (string) ($acc->email ?? ''));
        }
        if (empty($tos)) {
            return back()->withErrors(['email' => 'Sin destinatarios.']);
        }

        $emailId = (string) Str::ulid();
        $payload = $this->buildStatementEmailPayloadPublic($acc, $accountId, $period, $emailId);

        $subject = trim((string) ($payload['subject'] ?? ''));
        if ($subject === '') {
            $subject = 'Pactopia360 · Estado de cuenta ' . $period . ' · ' . (string) ($acc->razon_social ?? $acc->name ?? 'Cliente');
        }

        $newLogId = 0;

        try {
            $newLogId = $this->insertEmailLog([
                'email_id'   => $emailId,
                'account_id' => $accountId,
                'period'     => $period,
                'email'      => $tos[0] ?? null,
                'to_list'    => implode(',', $tos),
                'template'   => 'emails.admin.billing.statement_account_period',
                'status'     => 'queued',
                'provider'   => config('mail.default') ?: 'smtp',
                'subject'    => $subject,
                'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'meta'       => json_encode([
                    'source'         => 'admin_resend',
                    'resend_of_log'  => (int) $id,
                    'account_id'     => $accountId,
                    'period'         => $period,
                ], JSON_UNESCAPED_UNICODE),
                'queued_at'  => now(),
            ]);

            Mail::to($tos)->send(new StatementAccountPeriodMail($accountId, $period, $payload));

            DB::connection($this->adm)->table('billing_email_logs')
                ->where('id', $newLogId)
                ->update([
                    'status'     => 'sent',
                    'sent_at'    => now(),
                    'updated_at' => now(),
                ]);

            return back()->with('ok', 'Reenvío OK. Nuevo email_id=' . $emailId);
        } catch (\Throwable $e) {
            if ($newLogId > 0) {
                DB::connection($this->adm)->table('billing_email_logs')
                    ->where('id', $newLogId)
                    ->update([
                        'status'     => 'failed',
                        'failed_at'  => now(),
                        'updated_at' => now(),
                        'meta'       => json_encode([
                            'error'         => $e->getMessage(),
                            'source'        => 'admin_resend',
                            'resend_of_log' => (int) $id,
                            'account_id'    => $accountId,
                            'period'        => $period,
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
            }

            Log::error('[BILLING_HUB][resendEmail] fallo', [
                'log_id'     => $id,
                'new_log_id' => $newLogId,
                'account_id' => $accountId,
                'period'     => $period,
                'to'         => $tos,
                'email_id'   => $emailId,
                'e'          => $e->getMessage(),
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
        abort_unless($acc, 404);

        $emailId = (string) Str::ulid();
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
    // PAYLINK
    // =========================================================

    public function createPayLink(Request $req): Response|RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
        ]);

        return $this->redirectToCheckout((string) $data['account_id'], (string) $data['period'], false);
    }

    public function payLink(Request $req): Response|RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
        ]);

        return $this->redirectToCheckout((string) $data['account_id'], (string) $data['period'], true);
    }

    // =========================================================
    // FACTURAS / REQUESTS
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
        $now       = now();

        $row = DB::connection($this->adm)->table('billing_invoice_requests')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        if ($row) {
            DB::connection($this->adm)->table('billing_invoice_requests')
                ->where('id', (int) $row->id)
                ->update([
                    'notes'      => $notes !== '' ? $notes : ($row->notes ?? null),
                    'status'     => $row->status ?: 'requested',
                    'updated_at' => $now,
                ]);

            return back()->with('ok', 'Solicitud de factura actualizada (#' . (int) $row->id . ')');
        }

        $id = (int) DB::connection($this->adm)->table('billing_invoice_requests')->insertGetId([
            'account_id' => $accountId,
            'period'     => $period,
            'status'     => 'requested',
            'notes'      => $notes !== '' ? $notes : null,
            'created_at' => $now,
            'updated_at' => $now,
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

        $ok = DB::connection($this->adm)->table('billing_invoice_requests')
            ->where('id', (int) $data['id'])
            ->update([
                'status'     => trim((string) $data['status']),
                'cfdi_uuid'  => ($data['cfdi_uuid'] ?? null) ?: null,
                'notes'      => ($data['notes'] ?? null) ?: null,
                'updated_at' => now(),
            ]);

        if (!$ok) {
            return back()->withErrors(['invoice' => 'No se pudo actualizar. ID no encontrado.']);
        }

        return back()->with('ok', 'Estatus solicitud actualizado (#' . (int) $data['id'] . ').');
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
        $now = now();

        $row = DB::connection($this->adm)->table('billing_invoices')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        $invCols = Schema::connection($this->adm)->getColumnListing('billing_invoices');
        $ilc = array_map('strtolower', $invCols);
        $invHas = static fn (string $c): bool => in_array(strtolower($c), $ilc, true);

        $payload = [
            'serie'       => $data['serie'] ?? null,
            'folio'       => $data['folio'] ?? null,
            'cfdi_uuid'   => $data['cfdi_uuid'] ?? null,
            'issued_date' => !empty($data['issued_date']) ? Carbon::parse((string) $data['issued_date'])->toDateString() : null,
            'status'      => 'issued',
            'notes'       => ($data['notes'] ?? null) ?: null,
            'updated_at'  => $now,
        ];

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
            DB::connection($this->adm)->table('billing_invoices')
                ->where('id', (int) $row->id)
                ->update($payload);
        } else {
            $payload['account_id'] = $accountId;
            $payload['period']     = $period;
            $payload['created_at'] = $now;

            DB::connection($this->adm)->table('billing_invoices')->insert($payload);
        }

        if (Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            DB::connection($this->adm)->table('billing_invoice_requests')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->update([
                    'status'     => 'issued',
                    'cfdi_uuid'  => $payload['cfdi_uuid'],
                    'updated_at' => $now,
                ]);
        }

        return back()->with('ok', $row ? 'Factura actualizada (account/period).' : 'Factura registrada.');
    }

    // =========================================================
    // TRACKING
    // =========================================================

    public function trackOpen(string $emailId): Response
    {
        try {
            if (Schema::connection($this->adm)->hasTable('billing_email_logs')) {
                $cols = Schema::connection($this->adm)->getColumnListing('billing_email_logs');
                $lc   = array_map('strtolower', $cols);
                $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

                $select = ['id'];
                if ($has('open_count')) {
                    $select[] = 'open_count';
                }
                if ($has('first_open_at')) {
                    $select[] = 'first_open_at';
                }
                if ($has('last_open_at')) {
                    $select[] = 'last_open_at';
                }

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
            Log::warning('[BILLING_HUB][trackOpen] error', [
                'email_id' => $emailId,
                'e'        => $e->getMessage(),
            ]);
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
                $target = $this->sanitizeTrackingTarget(urldecode($u));
            }

            if (Schema::connection($this->adm)->hasTable('billing_email_logs')) {
                $cols = Schema::connection($this->adm)->getColumnListing('billing_email_logs');
                $lc   = array_map('strtolower', $cols);
                $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

                $select = ['id'];
                if ($has('click_count')) {
                    $select[] = 'click_count';
                }
                if ($has('first_click_at')) {
                    $select[] = 'first_click_at';
                }
                if ($has('last_click_at')) {
                    $select[] = 'last_click_at';
                }

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
            Log::warning('[BILLING_HUB][trackClick] error', [
                'email_id' => $emailId,
                'e'        => $e->getMessage(),
            ]);
        }

        return redirect()->to($target);
    }

    // =========================================================
    // COMPAT
    // =========================================================

    public function resolveMonthlyCentsForPeriodFromAdminAccount(
        int $accountId,
        string $period,
        ?string $lastPaid = null,
        ?string $payAllowed = null
    ): int {
        try {
            if ($accountId <= 0) {
                return 0;
            }

            if (!$this->isValidPeriod($period)) {
                $period = now()->format('Y-m');
            }

            if (!Schema::connection($this->adm)->hasTable('accounts')) {
                return 0;
            }

            $accCols = Schema::connection($this->adm)->getColumnListing('accounts');
            $lc = array_map('strtolower', $accCols);
            $has = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            $select = ['id'];
            foreach (['meta', 'plan', 'plan_actual', 'modo_cobro', 'email', 'rfc', 'razon_social', 'name'] as $c) {
                if ($has($c)) {
                    $select[] = $c;
                }
            }
            foreach ([
                'billing_amount_mxn', 'amount_mxn', 'precio_mxn', 'monto_mxn',
                'override_amount_mxn', 'custom_amount_mxn', 'license_amount_mxn',
                'billing_amount', 'amount', 'precio', 'monto',
            ] as $c) {
                if ($has($c)) {
                    $select[] = $c;
                }
            }

            $acc = DB::connection($this->adm)->table('accounts')
                ->where('id', $accountId)
                ->first(array_values(array_unique($select)));

            if (!$acc) {
                return 0;
            }

            $meta = $this->decodeMeta($acc->meta ?? null);

            $custom = $this->extractCustomAmountMxn($acc, $meta);
            if ($custom !== null && $custom > 0.00001) {
                return max(0, (int) round($custom * 100));
            }

            [$expected] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, $payAllowed);
            $expected = (float) $expected;

            return $expected > 0.00001
                ? max(0, (int) round($expected * 100))
                : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // =========================================================
    // PRIVATE: ACCOUNTS / LISTING
    // =========================================================

    private function loadAccountsForIndex(
        string $q,
        string $period,
        string $periodFrom,
        string $periodTo,
        ?string $accountId,
        bool $includeAnnual,
        bool $onlySelected,
        array $selectedIds
    ): Collection {
        $cols = Schema::connection($this->adm)->getColumnListing('accounts');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

        $select = ['accounts.id', 'accounts.email'];

        foreach ([
            'name', 'razon_social', 'rfc',
            'plan', 'plan_actual', 'modo_cobro', 'billing_cycle',
            'is_blocked', 'estado_cuenta',
            'meta', 'created_at',
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

        $rangeEnd = $periodTo !== '' ? $periodTo : ($periodFrom !== '' ? $periodFrom : $period);

        if (!$includeAnnual && $hasSubs) {
            $annualExpr = "LOWER(COALESCE(accounts.modo_cobro,'')) IN ('anual','annual','year','yearly','12m','12')";
            $renewYm    = "DATE_FORMAT(COALESCE(sx_sub.current_period_end, sx_sub.started_at, accounts.created_at), '%Y-%m')";
            $qb->whereRaw("NOT ($annualExpr) OR ($renewYm = ?)", [$rangeEnd]);
        } elseif (!$includeAnnual && !$hasSubs) {
            $qb->whereRaw("LOWER(COALESCE(accounts.modo_cobro,'')) NOT IN ('anual','annual','year','yearly','12m','12')");
        }

        return collect($qb->get());
    }

    /**
     * @param Collection<int, mixed> $items
     */
    private function repaginateCollection(Collection $items, int $perPage, int $page, string $path, array $query): LengthAwarePaginator
    {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $slice  = $items->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            (int) $items->count(),
            $perPage,
            $page,
            ['path' => $path, 'query' => $query]
        );
    }

    /**
     * @param Collection<int, mixed> $rows
     * @return array<string, float|int|null>
     */
    private function buildKpis(Collection $rows): array
    {
        $kCargo = 0.0;
        $kAbono = 0.0;
        $kSaldo = 0.0;
        $kPrev  = 0.0;
        $kEdo   = 0.0;
        $kPay   = 0.0;

        foreach ($rows as $r) {
            $kCargo += (float) ($r->total_shown ?? 0);
            $kAbono += (float) ($r->abono ?? 0);
            $kSaldo += (float) ($r->total_due ?? 0);
            $kPrev  += (float) ($r->prev_balance ?? 0);
            $kEdo   += (float) ($r->abono_edo ?? 0);
            $kPay   += (float) ($r->abono_pay ?? 0);
        }

        return [
            'cargo'        => round($kCargo, 2),
            'abono'        => round($kAbono, 2),
            'saldo'        => round($kSaldo, 2),
            'prev_pending' => round($kPrev, 2),
            'accounts'     => (int) $rows->count(),
            'paid_edo'     => round($kEdo, 2),
            'paid_pay'     => round($kPay, 2),
            'edocta'       => round($kEdo, 2),
            'payments'     => round($kPay, 2),
        ];
    }

    /**
     * @return array<string, float|int|null>
     */
    private function emptyKpis(): array
    {
        return [
            'cargo'        => 0,
            'abono'        => 0,
            'saldo'        => 0,
            'prev_pending' => 0,
            'accounts'     => 0,
            'paid_edo'     => 0,
            'paid_pay'     => 0,
            'edocta'       => 0,
            'payments'     => 0,
        ];
    }

    // =========================================================
    // PRIVATE: PAYLINK / TOTALS
    // =========================================================

    private function redirectToCheckout(string $accountId, string $period, bool $publicResponse): Response|RedirectResponse
    {
        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();

        if (!$acc) {
            return $publicResponse
                ? response('Cuenta no encontrada.', 404)
                    ->header('Cache-Control', 'no-store, max-age=0, public')
                    ->header('Pragma', 'no-cache')
                : back()->withErrors(['pay' => 'Cuenta no encontrada.'])->withInput();
        }

        [$totalShown, $abono, $saldo] = $this->computeSimpleStatementTotals($acc, $accountId, $period);

        if ($saldo <= 0.00001) {
            return $publicResponse
                ? response('No hay saldo pendiente para ese periodo.', 200)
                    ->header('Cache-Control', 'no-store, max-age=0, public')
                    ->header('Pragma', 'no-cache')
                : back()->with('ok', 'No hay saldo pendiente para ese periodo.')->withInput();
        }

        try {
            [$url] = $this->createStripeCheckoutForStatement($acc, $period, $saldo);

            return redirect()
                ->away((string) $url)
                ->withHeaders([
                    'Cache-Control' => 'no-store, max-age=0, public',
                    'Pragma'        => 'no-cache',
                ]);
        } catch (\Throwable $e) {
            return $publicResponse
                ? response('No se pudo generar liga: ' . $e->getMessage(), 500)
                : back()->withErrors(['pay' => 'No se pudo generar liga: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * @return array{0:float,1:float,2:float}
     */
    private function computeSimpleStatementTotals(object $acc, string $accountId, string $period): array
    {
        $mirror = $this->loadBillingStatementsMirrorMap([$accountId], $period);
        if (isset($mirror[$accountId])) {
            $m = $mirror[$accountId];
            $total = round((float) ($m['total_cargo'] ?? 0), 2);
            $abono = round((float) ($m['total_abono'] ?? 0), 2);
            $saldo = round((float) ($m['saldo'] ?? max(0.0, $total - $abono)), 2);
            return [$total, $abono, $saldo];
        }

        $meta = $this->decodeMeta($acc->meta ?? null);
        $custom = $this->extractCustomAmountMxn($acc, $meta);

        if ($custom !== null && $custom > 0.00001) {
            $expected = $custom;
        } else {
            [$expected] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);
        }

        $abonoPay = (float) $this->sumPaymentsPaidForAccountPeriod($accountId, $period);
        $total    = round((float) $expected, 2);
        $abono    = round(max(0.0, $abonoPay), 2);
        $saldo    = round(max(0.0, $total - $abono), 2);

        return [$total, $abono, $saldo];
    }

    // =========================================================
    // PRIVATE: STRIPE
    // =========================================================

    /**
     * @return array{0:string,1:string}
     */
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
            'line_items'           => [[
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
                'type'       => 'billing_statement',
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
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return;
        }

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

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

            if ($has('provider')) {
                $q->where('provider', 'stripe');
            }

            $existing = $q->orderByDesc($has('id') ? 'id' : $cols[0])->first();
        }

        $row = [];
        if ($has('account_id')) {
            $row['account_id'] = $accountId;
        }
        if ($has('amount')) {
            $row['amount'] = $amountCents;
        }
        if ($has('amount_cents')) {
            $row['amount_cents'] = $amountCents;
        }
        if ($has('amount_mxn')) {
            $row['amount_mxn'] = round($uiTotalPesos, 2);
        }
        if ($has('monto_mxn')) {
            $row['monto_mxn'] = round($uiTotalPesos, 2);
        }
        if ($has('currency')) {
            $row['currency'] = 'MXN';
        }
        if ($has('status')) {
            $row['status'] = 'pending';
        }
        if ($has('due_date')) {
            $row['due_date'] = now();
        }
        if ($has('period')) {
            $row['period'] = $period;
        }
        if ($has('method')) {
            $row['method'] = 'card';
        }
        if ($has('provider')) {
            $row['provider'] = 'stripe';
        }
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
        if (!$existing) {
            $row['created_at'] = now();
        }

        if ($existing && $has('id')) {
            DB::connection($this->adm)->table('payments')
                ->where('id', (int) $existing->id)
                ->update($row);
        } else {
            DB::connection($this->adm)->table('payments')->insert($row);
        }
    }

    // =========================================================
    // PRIVATE: TRACKING HELPERS
    // =========================================================

    private function sanitizeTrackingTarget(mixed $u): string
    {
        $fallback = '/';

        if (!is_string($u)) {
            return $fallback;
        }

        $u = trim($u);
        if ($u === '') {
            return $fallback;
        }

        if (str_starts_with($u, '//')) {
            return $fallback;
        }

        if (str_starts_with($u, '/')) {
            return $u;
        }

        $parts = @parse_url($u);
        if (!is_array($parts)) {
            return $fallback;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $fallback;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return $fallback;
        }

        return in_array($host, $this->allowedTrackingHosts(), true) ? $u : $fallback;
    }

    /**
     * @return array<int,string>
     */
    private function allowedTrackingHosts(): array
    {
        $hosts = [];

        try {
            $rh = strtolower((string) request()->getHost());
            if ($rh !== '') {
                $hosts[] = $rh;
            }
        } catch (\Throwable $e) {
        }

        $appUrl  = (string) config('app.url');
        $appHost = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        if ($appHost !== '') {
            $hosts[] = $appHost;
        }

        $hosts[] = 'pactopia360.com';
        $hosts[] = 'www.pactopia360.com';

        return array_values(array_unique(array_filter($hosts)));
    }

    /**
     * @param array<int|string> $accountIds
     * @return array<string, array<string, mixed>>
     */
    private function trackingByAccountForPeriod(array $accountIds, string $period): array
    {
        if (empty($accountIds) || !Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            return [];
        }

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
            if ($aid === '') {
                continue;
            }

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
    // PRIVATE: EMAIL HELPERS
    // =========================================================

    /**
     * @return array<int,string>
     */
    private function resolveRecipientsForAccount(string $accountId, string $fallbackEmail = ''): array
    {
        $emails = [];

        $fallbackEmail = strtolower(trim($fallbackEmail));
        if ($fallbackEmail !== '' && filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $fallbackEmail;
        }

        if (Schema::connection($this->adm)->hasTable('account_recipients')) {
            try {
                $q = DB::connection($this->adm)->table('account_recipients')
                    ->select('email')
                    ->where('account_id', $accountId);

                if (Schema::connection($this->adm)->hasColumn('account_recipients', 'is_active')) {
                    $q->where('is_active', 1);
                }

                if (Schema::connection($this->adm)->hasColumn('account_recipients', 'kind')) {
                    $q->where(function ($w) {
                        $w->where('kind', 'statement')
                        ->orWhereNull('kind');
                    });
                }

                if (Schema::connection($this->adm)->hasColumn('account_recipients', 'is_primary')) {
                    $q->orderByDesc('is_primary');
                }

                $rows = $q->orderBy('email')->get();

                foreach ($rows as $row) {
                    $e = strtolower(trim((string) ($row->email ?? '')));
                    if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $e;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[BILLING_HUB][resolveRecipientsForAccount] fallo', [
                    'account_id' => $accountId,
                    'err'        => $e->getMessage(),
                ]);
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @return array<string,mixed>
     */
    private function buildStatementEmailPayloadPublic(object $acc, string $accountId, string $period, string $emailId, bool $isPreview = false): array
    {
        $meta = $this->decodeMeta($acc->meta ?? null);

        $rs = (string) ($acc->razon_social ?? $acc->name ?? 'Cliente');
        $rfc = (string) ($acc->rfc ?? '');
        $to = (string) ($acc->email ?? '');

        $mirror = $this->loadBillingStatementsMirrorMap([$accountId], $period);
        $statement = $mirror[$accountId] ?? null;

        $items = collect();
        if (Schema::connection($this->adm)->hasTable('estados_cuenta')) {
            $items = DB::connection($this->adm)->table('estados_cuenta')
                ->where('account_id', $accountId)
                ->where('periodo', $period)
                ->orderBy('id')
                ->get();
        }

        if ($statement) {
            $cargoReal = round((float) ($statement['total_cargo'] ?? 0), 2);
            $abono = round((float) ($statement['total_abono'] ?? 0), 2);
            $saldo = round((float) ($statement['saldo'] ?? 0), 2);
            $abonoEc = $abono;
            $abonoPay = 0.0;
        } else {
            $cargoReal = round((float) $items->sum('cargo'), 2);
            $abonoEc   = round((float) $items->sum('abono'), 2);
            $abonoPay  = round((float) $this->sumPaymentsPaidForAccountPeriod($accountId, $period), 2);
            $abono     = round($abonoEc + $abonoPay, 2);

            $custom = $this->extractCustomAmountMxn($acc, $meta);
            if ($custom !== null && $custom > 0.00001) {
                $expected = $custom;
            } else {
                [$expected] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);
            }

            $cargoReal = $cargoReal > 0 ? $cargoReal : round((float) $expected, 2);
            $saldo = round(max(0.0, $cargoReal - $abono), 2);
        }

        $portalUrl = Route::has('cliente.login')
            ? route('cliente.login')
            : url('/cliente/login');

        $openPixelUrl = Route::has('admin.billing.hub.track_open')
            ? route('admin.billing.hub.track_open', ['emailId' => $emailId])
            : url('/admin/t/billing/open/' . urlencode($emailId));

        $wrapClick = function (string $url) use ($emailId): string {
            $u = urlencode($url);
            if (Route::has('admin.billing.hub.track_click')) {
                return route('admin.billing.hub.track_click', ['emailId' => $emailId]) . '?u=' . $u;
            }
            return url('/admin/t/billing/click/' . urlencode($emailId)) . '?u=' . $u;
        };

        $pdfUrl = Route::has('cliente.estado_cuenta')
            ? route('cliente.estado_cuenta') . '?period=' . urlencode($period)
            : url('/cliente/estado-de-cuenta') . '?period=' . urlencode($period);

        $payUrl = '';
        if (!$isPreview && $saldo > 0.00001) {
            $payUrl = Route::has('admin.billing.hub.paylink')
                ? route('admin.billing.hub.paylink') . '?account_id=' . urlencode($accountId) . '&period=' . urlencode($period)
                : url('/admin/billing/statements-hub/paylink') . '?account_id=' . urlencode($accountId) . '&period=' . urlencode($period);
        }

        $subject = 'Pactopia360 · Estado de cuenta ' . $period . ' · ' . $rs;

        return [
            'template' => 'emails.admin.billing.statement_account_period',
            'subject'  => $subject,

            'account' => (object) [
                'id'           => $accountId,
                'razon_social' => $rs,
                'name'         => $rs,
                'rfc'          => $rfc,
                'email'        => $to,
            ],

            'period'       => $period,
            'period_label' => $period,

            'items'        => $items,

            'tarifa_label' => 'Estado de cuenta',
            'total'        => round($saldo, 2),
            'saldo'        => round($saldo, 2),
            'total_cargo'  => round($cargoReal, 2),
            'total_abono'  => round($abono, 2),

            'totals' => [
                'cargo_real' => round($cargoReal, 2),
                'abono_ec'   => round($abonoEc, 2),
                'abono_pay'  => round($abonoPay, 2),
                'abono'      => round($abono, 2),
                'expected'   => round($cargoReal, 2),
                'total'      => round($cargoReal, 2),
                'saldo'      => round($saldo, 2),
            ],

            'portal_url'        => $portalUrl,
            'pdf_url'           => $pdfUrl,
            'pay_url'           => $payUrl,
            'open_pixel_url'    => $openPixelUrl,
            'pdf_track_url'     => $wrapClick($pdfUrl),
            'pay_track_url'     => $payUrl !== '' ? $wrapClick($payUrl) : '',
            'portal_track_url'  => $wrapClick($portalUrl),
            'email_id'          => $emailId,
            'generated_at'      => now(),
        ];
    }

    private function insertEmailLog(array $row): int
    {
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            throw new \RuntimeException('No existe billing_email_logs en ' . $this->adm);
        }

        $cols = Schema::connection($this->adm)->getColumnListing('billing_email_logs');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

        $accountId = trim((string) ($row['account_id'] ?? ''));
        $period    = trim((string) ($row['period'] ?? ''));
        $email     = trim((string) ($row['email'] ?? ''));
        $toList    = trim((string) ($row['to_list'] ?? ''));
        $subject   = trim((string) ($row['subject'] ?? ''));

        // ✅ Normaliza destinatarios
        $parsedTo = $this->parseToList($toList);

        if ($toList === '' && !empty($parsedTo)) {
            $toList = implode(',', $parsedTo);
        }

        if ($email === '' && !empty($parsedTo)) {
            $email = (string) $parsedTo[0];
        }

        // ✅ Columna `to` obligatoria en tu tabla
        $toSingle = $email !== ''
            ? $email
            : (!empty($parsedTo) ? (string) $parsedTo[0] : '');

        if ($toSingle === '' && $toList !== '') {
            $tmp = $this->parseToList($toList);
            if (!empty($tmp)) {
                $toSingle = (string) $tmp[0];
            }
        }

        // ✅ subject obligatorio
        if ($subject === '') {
            $subject = 'Pactopia360 · Estado de cuenta';
            if ($period !== '') {
                $subject .= ' ' . $period;
            }
            if ($accountId !== '') {
                $subject .= ' · Cuenta ' . $accountId;
            }
        }

        $ins = [];

        if ($has('email_id')) {
            $ins['email_id'] = (string) ($row['email_id'] ?? Str::ulid());
        }

        if ($has('account_id')) {
            $ins['account_id'] = $accountId;
        }

        if ($has('period')) {
            $ins['period'] = $period;
        }

        if ($has('email')) {
            $ins['email'] = $email !== '' ? $email : null;
        }

        // ✅ NUEVO: llenar columna `to`
        if ($has('to')) {
            $ins['to'] = $toSingle !== '' ? $toSingle : 'sin-destinatario@local.invalid';
        }

        if ($has('to_list')) {
            $ins['to_list'] = $toList !== '' ? $toList : ($toSingle !== '' ? $toSingle : 'sin-destinatario@local.invalid');
        }

        if ($has('template')) {
            $ins['template'] = (string) ($row['template'] ?? 'statement');
        }

        if ($has('status')) {
            $ins['status'] = (string) ($row['status'] ?? 'queued');
        }

        if ($has('provider')) {
            $ins['provider'] = $row['provider'] ?? null;
        }

        if ($has('provider_message_id')) {
            $ins['provider_message_id'] = $row['provider_message_id'] ?? null;
        }

        if ($has('subject')) {
            $ins['subject'] = $subject;
        }

        if ($has('payload')) {
            $ins['payload'] = $row['payload'] ?? null;
        }

        if ($has('meta')) {
            $ins['meta'] = $row['meta'] ?? null;
        }

        if ($has('queued_at')) {
            $ins['queued_at'] = $row['queued_at'] ?? now();
        }

        if ($has('sent_at')) {
            $ins['sent_at'] = $row['sent_at'] ?? null;
        }

        if ($has('failed_at')) {
            $ins['failed_at'] = $row['failed_at'] ?? null;
        }

        if ($has('open_count')) {
            $ins['open_count'] = (int) ($row['open_count'] ?? 0);
        }

        if ($has('click_count')) {
            $ins['click_count'] = (int) ($row['click_count'] ?? 0);
        }

        $ins['created_at'] = now();
        $ins['updated_at'] = now();

        return (int) DB::connection($this->adm)->table('billing_email_logs')->insertGetId($ins);
    }

    /**
     * @return array<int,string>
     */
    public function parseToList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $raw = str_replace([';', "\n", "\r", "\t"], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, static fn ($x) => $x !== '');

        $out = [];
        foreach ($parts as $p) {
            if (preg_match('/<([^>]+)>/', $p, $m)) {
                $p = trim((string) $m[1]);
            }
            if (filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $out[] = strtolower($p);
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 10);
    }

    // =========================================================
    // PRIVATE: PAYMENTS
    // =========================================================

    private function paymentsAmountExpr(): string
    {
        static $cache = [];

        if (isset($cache[$this->adm])) {
            return $cache[$this->adm];
        }

        $schema = Schema::connection($this->adm);
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

        $cache[$this->adm] = $parts
            ? "(CASE " . implode(' ', $parts) . " ELSE 0 END)"
            : "(0)";

        return $cache[$this->adm];
    }

    private function sumPaymentsPaidForAccountPeriod(string $accountId, string $period): float
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return 0.0;
        }

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) {
            return 0.0;
        }

        $q = DB::connection($this->adm)->table('payments')
            ->where('account_id', $accountId)
            ->where('period', $period);

        if ($has('status')) {
            $q->whereIn(DB::raw('LOWER(status)'), [
                'paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized', 'pagado', 'paid_ok', 'ok',
            ]);
        }

        $amtExpr = $this->paymentsAmountExpr();

        $paid = (float) $q->selectRaw("SUM($amtExpr) AS s")->value('s');

        return round(max(0.0, $paid), 2);
    }

    /**
     * @param array<int,string> $accountIds
     * @return array<string,float>
     */
    private function sumPaymentsPaidByAccountForPeriod(array $accountIds, string $period): array
    {
        $ids = array_values(array_unique(array_filter($accountIds)));
        if (empty($ids) || !Schema::connection($this->adm)->hasTable('payments')) {
            return [];
        }

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) {
            return [];
        }

        $amtExpr = $this->paymentsAmountExpr();

        $q = DB::connection($this->adm)->table('payments')
            ->whereIn('account_id', $ids)
            ->where('period', $period);

        if ($has('status')) {
            $q->whereIn(DB::raw('LOWER(status)'), [
                'paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized', 'pagado', 'paid_ok', 'ok',
            ]);
        }

        $rows = $q->selectRaw("account_id as aid, SUM($amtExpr) AS paid_mxn")
            ->groupBy('account_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $aid = trim((string) ($r->aid ?? ''));
            if ($aid !== '') {
                $out[$aid] = round((float) ($r->paid_mxn ?? 0), 2);
            }
        }

        return $out;
    }

    // =========================================================
    // PRIVATE: PERIOD / META / PRICING
    // =========================================================

    private function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', trim($period));
    }

    private function parseToPeriod(mixed $value): ?string
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->format('Y-m');
            }

            if (is_numeric($value)) {
                $ts = (int) $value;
                return $ts > 0 ? Carbon::createFromTimestamp($ts)->format('Y-m') : null;
            }

            if (is_string($value)) {
                $v = trim(str_replace('/', '-', $value));
                if ($v === '') {
                    return null;
                }

                if ($this->isValidPeriod($v)) {
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

    /**
     * @return array<string,mixed>
     */
    public function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }
        if (is_object($meta)) {
            return (array) $meta;
        }
        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
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
        if (!in_array($eff, ['now', 'next'], true)) {
            $eff = '';
        }

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

        return [round((float) $effective, 2), $label, $pillText];
    }

    private function toFloat(mixed $v): ?float
    {
        if ($v === null) {
            return null;
        }
        if (is_float($v) || is_int($v)) {
            return (float) $v;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }

        if (is_string($v)) {
            $s = trim($v);
            if ($s === '') {
                return null;
            }

            $s = str_replace(['$', ',', 'MXN', 'mxn', ' '], '', $s);
            return is_numeric($s) ? (float) $s : null;
        }

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
            if ($n !== null && $n > 0.00001) {
                return $n;
            }
        }

        foreach ([
            'override_amount_mxn', 'custom_amount_mxn', 'billing_amount_mxn', 'amount_mxn',
            'precio_mxn', 'monto_mxn', 'license_amount_mxn', 'billing_amount', 'amount',
            'precio', 'monto',
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
        $key = trim($accountId);
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->cacheLastPaid)) {
            return $this->cacheLastPaid[$key];
        }

        $lastPaid = null;

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
                $lastPaid = $p;
                break;
            }
        }

        $mirrorPrev = $this->loadBillingStatementsMirrorMap([$key], now()->format('Y-m'));
        unset($mirrorPrev); // reservado para futuras reglas

        $this->cacheLastPaid[$key] = $lastPaid;
        return $lastPaid;
    }

    private function normalizeStatus(string $status): string
    {
        $s = strtolower(trim($status));

        return match ($s) {
            'paid', 'pagado', 'success', 'succeeded', 'complete', 'completed' => 'pagado',
            'partial', 'parcial' => 'parcial',
            'overdue', 'past_due', 'vencido' => 'vencido',
            'sin_mov', 'sin_movimientos', 'no_mov', 'no_movement' => 'sin_mov',
            default => 'pendiente',
        };
    }

    // =========================================================
    // PRIVATE: IDS / STATEMENTS MIRROR
    // =========================================================

    /**
     * @return array<int,string>
     */
    private function parseSelectedIds(mixed $idsRaw): array
    {
        $selectedIds = [];

        if (is_array($idsRaw)) {
            $selectedIds = array_values(array_filter(array_map(static function ($v) {
                $s = trim((string) $v);
                if ($s === '') {
                    return null;
                }
                if (preg_match('/^\d+$/', $s)) {
                    return $s;
                }
                if (preg_match('/^[a-zA-Z0-9\-_]+$/', $s)) {
                    return $s;
                }
                return null;
            }, $idsRaw)));
        } elseif (is_string($idsRaw)) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $idsRaw))));
            $selectedIds = array_values(array_filter(array_map(static function ($s) {
                $s = trim((string) $s);
                if ($s === '') {
                    return null;
                }
                if (preg_match('/^\d+$/', $s)) {
                    return $s;
                }
                if (preg_match('/^[a-zA-Z0-9\-_]+$/', $s)) {
                    return $s;
                }
                return null;
            }, $parts)));
        }

        return array_values(array_unique($selectedIds));
    }

    /**
     * @return array<int,string>
     */
    private function parseIdCsv(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $raw = str_replace([';', "\n", "\r", "\t", ' '], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, static fn ($x) => $x !== '');

        $out = [];
        foreach ($parts as $p) {
            if (preg_match('/^[a-zA-Z0-9\-_]{1,80}$/', $p)) {
                $out[] = $p;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 300);
    }

    /**
     * @param string|int $adminAccountId
     * @return array<int,string>
     */
    private function billingStatementRefsForAdminAccount(string|int $adminAccountId): array
    {
        $aid = trim((string) $adminAccountId);
        if ($aid === '') {
            return [];
        }

        $refs = [$aid];

        if (ctype_digit($aid)) {
            $refs[] = (string) ((int) $aid);
        }

        try {
            if (
                Schema::connection($this->cli)->hasTable('cuentas_cliente') &&
                Schema::connection($this->cli)->hasColumn('cuentas_cliente', 'admin_account_id')
            ) {
                $uuids = DB::connection($this->cli)->table('cuentas_cliente')
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
     * @param array<int|string> $accountIds
     * @return array<string, array<string,mixed>>
     */
    private function loadBillingStatementsMirrorMap(array $accountIds, string $period): array
    {
        $out = [];

        if (empty($accountIds) || !Schema::connection($this->adm)->hasTable('billing_statements')) {
            return $out;
        }

        $refToAdmin = [];
        $allRefs    = [];

        foreach ($accountIds as $aid) {
            $aidStr = trim((string) $aid);
            if ($aidStr === '') {
                continue;
            }

            $refs = $this->billingStatementRefsForAdminAccount($aidStr);
            foreach ($refs as $ref) {
                $refToAdmin[$ref] = $aidStr;
                $allRefs[] = $ref;
            }
        }

        $allRefs = array_values(array_unique(array_filter($allRefs)));
        if (!$allRefs) {
            return $out;
        }

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
            if ($ref === '' || !isset($refToAdmin[$ref])) {
                continue;
            }

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
     * @param array<int|string> $accountIds
     * @return array<string, array{prev_balance: float, prev_period: ?string}>
     */
    private function loadPreviousOpenStatementsMap(array $accountIds, string $period): array
    {
        $out = [];

        if (empty($accountIds) || !Schema::connection($this->adm)->hasTable('billing_statements')) {
            return $out;
        }

        $refToAdmin = [];
        $allRefs    = [];

        foreach ($accountIds as $aid) {
            $aidStr = trim((string) $aid);
            if ($aidStr === '') {
                continue;
            }

            $refs = $this->billingStatementRefsForAdminAccount($aidStr);
            foreach ($refs as $ref) {
                $refToAdmin[$ref] = $aidStr;
                $allRefs[] = $ref;
            }
        }

        $allRefs = array_values(array_unique(array_filter($allRefs)));
        if (!$allRefs) {
            return $out;
        }

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
            if ($ref === '' || !isset($refToAdmin[$ref])) {
                continue;
            }

            $adminId = $refToAdmin[$ref];
            $p = trim((string) ($row->period ?? ''));
            if ($p === '') {
                continue;
            }

            $key = $adminId . '|' . $p;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $saldo  = round((float) ($row->saldo ?? 0), 2);
            $status = strtolower(trim((string) ($row->status ?? 'pending')));
            $paidAt = $row->paid_at ?? null;
            $cargo  = round((float) ($row->total_cargo ?? 0), 2);
            $abono  = round((float) ($row->total_abono ?? 0), 2);

            $isPaid = false;
            if ($paidAt) {
                $isPaid = true;
            }
            if (in_array($status, ['paid', 'pagado'], true)) {
                $isPaid = true;
            }
            if ($saldo <= 0.0001 && ($cargo > 0.0001 || $abono > 0.0001)) {
                $isPaid = true;
            }

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

    /**
     * @param array<int|string> $accountIds
     * @return array<string, array<string,mixed>>
     */
    private function loadStatusOverridesMap(array $accountIds, string $period): array
    {
        $out = [];

        if (empty($accountIds) || !Schema::connection($this->adm)->hasTable('billing_statement_status_overrides')) {
            return $out;
        }

        // 🔥 Detectar si existe columna meta (dinámico)
        $hasMeta = Schema::connection($this->adm)
            ->hasColumn('billing_statement_status_overrides', 'meta');

        $query = DB::connection($this->adm)
            ->table('billing_statement_status_overrides')
            ->whereIn('account_id', $accountIds)
            ->where('period', $period)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $cols = [
            'id',
            'account_id',
            'period',
            'status_override',
            'updated_at',
        ];

        if ($hasMeta) {
            $cols[] = 'meta';
        }

        $rows = $query->get($cols);

        foreach ($rows as $row) {
            $aid = trim((string) ($row->account_id ?? ''));
            if ($aid === '' || isset($out[$aid])) {
                continue;
            }

            $meta = [];

            // 🔥 Solo si existe columna meta
            if ($hasMeta && !empty($row->meta) && is_string($row->meta)) {
                try {
                    $decoded = json_decode($row->meta, true);
                    if (is_array($decoded)) {
                        $meta = $decoded;
                    }
                } catch (\Throwable $e) {
                    $meta = [];
                }
            }

            $out[$aid] = [
                'status_override' => $this->normalizeStatus((string) ($row->status_override ?? '')),
                'pay_method'      => strtolower(trim((string) ($meta['pay_method'] ?? ''))),
                'pay_provider'    => strtolower(trim((string) ($meta['pay_provider'] ?? ''))),
                'pay_status'      => $this->normalizeStatus((string) ($meta['pay_status'] ?? ($row->status_override ?? ''))),
                'paid_at'         => $meta['paid_at'] ?? null,
                'updated_at'      => $row->updated_at ?? null,
            ];
        }

        return $out;
    }

        /**
     * @param array<int|string> $accountIds
     * @return array<string, array<string,mixed>>
     */
    private function loadBillingStatementsMirrorMapForRange(array $accountIds, string $periodFrom, string $periodTo): array
    {
        $out = [];

        if (empty($accountIds) || !Schema::connection($this->adm)->hasTable('billing_statements')) {
            return $out;
        }

        $from = $periodFrom !== '' ? $periodFrom : '0000-01';
        $to   = $periodTo !== '' ? $periodTo : '9999-12';

        $refToAdmin = [];
        $allRefs    = [];

        foreach ($accountIds as $aid) {
            $aidStr = trim((string) $aid);
            if ($aidStr === '') {
                continue;
            }

            $refs = $this->billingStatementRefsForAdminAccount($aidStr);
            foreach ($refs as $ref) {
                $refToAdmin[$ref] = $aidStr;
                $allRefs[] = $ref;
            }
        }

        $allRefs = array_values(array_unique(array_filter($allRefs)));
        if (!$allRefs) {
            return $out;
        }

        $rows = DB::connection($this->adm)->table('billing_statements')
            ->whereIn('account_id', $allRefs)
            ->where('period', '>=', $from)
            ->where('period', '<=', $to)
            ->orderBy('period')
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

        foreach ($rows as $row) {
            $ref = trim((string) ($row->account_id ?? ''));
            if ($ref === '' || !isset($refToAdmin[$ref])) {
                continue;
            }

            $adminId = $refToAdmin[$ref];

            if (!isset($out[$adminId])) {
                $out[$adminId] = [
                    'id'          => 0,
                    'account_ref' => $ref,
                    'period'      => '',
                    'period_from' => null,
                    'period_to'   => null,
                    'status'      => 'sin_mov',
                    'total_cargo' => 0.0,
                    'total_abono' => 0.0,
                    'saldo'       => 0.0,
                    'paid_at'     => null,
                    'updated_at'  => null,
                ];
            }

            $p = (string) ($row->period ?? '');
            $out[$adminId]['id'] = max((int) $out[$adminId]['id'], (int) ($row->id ?? 0));
            $out[$adminId]['period_from'] = $out[$adminId]['period_from'] ?: $p;
            $out[$adminId]['period_to']   = $p !== '' ? $p : $out[$adminId]['period_to'];
            $out[$adminId]['period']      = $p;
            $out[$adminId]['total_cargo'] += round((float) ($row->total_cargo ?? 0), 2);
            $out[$adminId]['total_abono'] += round((float) ($row->total_abono ?? 0), 2);
            $out[$adminId]['saldo']       += round((float) ($row->saldo ?? 0), 2);
            $out[$adminId]['updated_at']   = $row->updated_at ?? $out[$adminId]['updated_at'];

            $status = $this->normalizeStatus((string) ($row->status ?? 'pendiente'));
            if ($status === 'vencido') {
                $out[$adminId]['status'] = 'vencido';
            } elseif ($status === 'parcial' && $out[$adminId]['status'] !== 'vencido') {
                $out[$adminId]['status'] = 'parcial';
            } elseif ($status === 'pendiente' && !in_array($out[$adminId]['status'], ['vencido', 'parcial'], true)) {
                $out[$adminId]['status'] = 'pendiente';
            }

            if (!empty($row->paid_at)) {
                $out[$adminId]['paid_at'] = $row->paid_at;
            }
        }

        foreach ($out as $aid => $vals) {
            $cargo = round((float) ($vals['total_cargo'] ?? 0), 2);
            $abono = round((float) ($vals['total_abono'] ?? 0), 2);
            $saldo = round(max(0.0, (float) ($vals['saldo'] ?? max(0.0, $cargo - $abono))), 2);

            $out[$aid]['total_cargo'] = $cargo;
            $out[$aid]['total_abono'] = $abono;
            $out[$aid]['saldo'] = $saldo;

            if ($saldo <= 0.00001 && $cargo > 0.00001) {
                $out[$aid]['status'] = 'pagado';
            } elseif ($cargo <= 0.00001 && $abono <= 0.00001) {
                $out[$aid]['status'] = 'sin_mov';
            }
        }

        return $out;
    }

    /**
     * @param array<int,string> $accountIds
     * @return array<string,float>
     */
    private function sumPaymentsPaidByAccountForRange(array $accountIds, string $periodFrom, string $periodTo): array
    {
        $ids = array_values(array_unique(array_filter($accountIds)));
        if (empty($ids) || !Schema::connection($this->adm)->hasTable('payments')) {
            return [];
        }

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) {
            return [];
        }

        $from = $periodFrom !== '' ? $periodFrom : '0000-01';
        $to   = $periodTo !== '' ? $periodTo : '9999-12';

        $amtExpr = $this->paymentsAmountExpr();

        $q = DB::connection($this->adm)->table('payments')
            ->whereIn('account_id', $ids)
            ->where('period', '>=', $from)
            ->where('period', '<=', $to);

        if ($has('status')) {
            $q->whereIn(DB::raw('LOWER(status)'), [
                'paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized', 'pagado', 'paid_ok', 'ok',
            ]);
        }

        $rows = $q->selectRaw("account_id as aid, SUM($amtExpr) AS paid_mxn")
            ->groupBy('account_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $aid = trim((string) ($r->aid ?? ''));
            if ($aid !== '') {
                $out[$aid] = round((float) ($r->paid_mxn ?? 0), 2);
            }
        }

        return $out;
    }

    /**
     * @param array<int|string> $accountIds
     * @return array<string, array<string, mixed>>
     */
    private function trackingByAccountForRange(array $accountIds, string $periodFrom, string $periodTo): array
    {
        if (empty($accountIds) || !Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            return [];
        }

        $from = $periodFrom !== '' ? $periodFrom : '0000-01';
        $to   = $periodTo !== '' ? $periodTo : '9999-12';

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
            ->where('period', '>=', $from)
            ->where('period', '<=', $to)
            ->whereIn('account_id', $accountIds)
            ->groupBy('account_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $aid = (string) ($r->aid ?? '');
            if ($aid === '') {
                continue;
            }

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

    /**
     * @param array<int|string> $accountIds
     * @return array<string, array<string,mixed>>
     */
    private function loadStatusOverridesMapForRange(array $accountIds, string $periodFrom, string $periodTo): array
    {
        $out = [];

        if (empty($accountIds) || !Schema::connection($this->adm)->hasTable('billing_statement_status_overrides')) {
            return $out;
        }

        $from = $periodFrom !== '' ? $periodFrom : '0000-01';
        $to   = $periodTo !== '' ? $periodTo : '9999-12';

        $hasMeta = Schema::connection($this->adm)
            ->hasColumn('billing_statement_status_overrides', 'meta');

        $query = DB::connection($this->adm)
            ->table('billing_statement_status_overrides')
            ->whereIn('account_id', $accountIds)
            ->where('period', '>=', $from)
            ->where('period', '<=', $to)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $cols = [
            'id',
            'account_id',
            'period',
            'status_override',
            'updated_at',
        ];

        if ($hasMeta) {
            $cols[] = 'meta';
        }

        $rows = $query->get($cols);

        foreach ($rows as $row) {
            $aid = trim((string) ($row->account_id ?? ''));
            if ($aid === '') {
                continue;
            }

            $meta = [];
            if ($hasMeta && !empty($row->meta) && is_string($row->meta)) {
                try {
                    $decoded = json_decode($row->meta, true);
                    if (is_array($decoded)) {
                        $meta = $decoded;
                    }
                } catch (\Throwable $e) {
                    $meta = [];
                }
            }

            if (!isset($out[$aid])) {
                $out[$aid] = [
                    'status_override' => $this->normalizeStatus((string) ($row->status_override ?? '')),
                    'pay_method'      => strtolower(trim((string) ($meta['pay_method'] ?? ''))),
                    'pay_provider'    => strtolower(trim((string) ($meta['pay_provider'] ?? ''))),
                    'pay_status'      => $this->normalizeStatus((string) ($meta['pay_status'] ?? ($row->status_override ?? ''))),
                    'paid_at'         => $meta['paid_at'] ?? null,
                    'updated_at'      => $row->updated_at ?? null,
                ];
                continue;
            }

            $status = $this->normalizeStatus((string) ($row->status_override ?? ''));
            if ($status === 'vencido') {
                $out[$aid]['status_override'] = 'vencido';
            } elseif ($status === 'parcial' && $out[$aid]['status_override'] !== 'vencido') {
                $out[$aid]['status_override'] = 'parcial';
            } elseif ($status === 'pendiente' && !in_array($out[$aid]['status_override'], ['vencido', 'parcial'], true)) {
                $out[$aid]['status_override'] = 'pendiente';
            }

            if (!empty($meta['paid_at'])) {
                $out[$aid]['paid_at'] = $meta['paid_at'];
            }
            if (!empty($meta['pay_method'])) {
                $out[$aid]['pay_method'] = strtolower(trim((string) $meta['pay_method']));
            }
            if (!empty($meta['pay_provider'])) {
                $out[$aid]['pay_provider'] = strtolower(trim((string) $meta['pay_provider']));
            }
            if (!empty($meta['pay_status'])) {
                $out[$aid]['pay_status'] = $this->normalizeStatus((string) $meta['pay_status']);
            }
        }

        return $out;
    }
}