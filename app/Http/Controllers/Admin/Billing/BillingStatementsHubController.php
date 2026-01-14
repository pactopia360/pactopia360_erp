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

    public function index(Request $req): View|Response
    {
        $tab = (string) $req->get('tab', 'statements'); // statements|emails|payments|invoice_requests|invoices

        $q = trim((string) $req->get('q', ''));

        $period = (string) $req->get('period', now()->format('Y-m'));
        if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
            $period = now()->format('Y-m');
        }

        $accountId = trim((string) $req->get('accountId', ''));

        // filtros PRO (UI)
        $status   = strtolower(trim((string) $req->get('status', ''))); // pagado|pendiente|parcial|vencido|sin_mov
        $saldoMin = $this->toFloat($req->get('saldo_min', null));
        $saldoMax = $this->toFloat($req->get('saldo_max', null));
        $plan     = strtolower(trim((string) $req->get('plan', '')));
        $modo     = strtolower(trim((string) $req->get('modo', '')));   // mensual|anual (meta.billing.mode)
        $sent     = strtolower(trim((string) $req->get('sent', '')));   // never|today|7d|30d

        $perPage  = (int) $req->get('per_page', 25);
        if ($perPage <= 0) $perPage = 25;
        if ($perPage > 250) $perPage = 250;

<<<<<<< HEAD
        // rango (placeholder, aún no se usa)
=======
        // rango (placeholder, aÃƒÂºn no se usa)
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
        $from = trim((string) $req->get('from', ''));
        $to   = trim((string) $req->get('to', ''));

        // tablas
        $hasAccounts     = Schema::connection($this->adm)->hasTable('accounts');
        $hasStatements   = Schema::connection($this->adm)->hasTable('estados_cuenta');
        $hasEmailLogs    = Schema::connection($this->adm)->hasTable('billing_email_logs');
        $hasInvoiceReq   = Schema::connection($this->adm)->hasTable('billing_invoice_requests');
        $hasInvoices     = Schema::connection($this->adm)->hasTable('billing_invoices');
        $hasPayments     = Schema::connection($this->adm)->hasTable('payments');

        // ==========================
        // 1) Estados de cuenta (HUB)
        // ==========================
        $rows = collect();
        $kpis = ['cargo' => 0, 'abono' => 0, 'saldo' => 0, 'accounts' => 0];

        if ($hasAccounts && $hasStatements) {
            $accCols = Schema::connection($this->adm)->getColumnListing('accounts');
            $alc = array_map('strtolower', $accCols);
            $ahas = static fn(string $c) => in_array(strtolower($c), $alc, true);

            $select = ['accounts.id', 'accounts.email'];
            foreach (['name', 'razon_social', 'rfc', 'plan', 'plan_actual', 'meta', 'created_at'] as $c) {
                if ($ahas($c)) $select[] = "accounts.$c";
            }
            foreach ([
                'billing_amount_mxn', 'amount_mxn', 'precio_mxn', 'monto_mxn',
                'override_amount_mxn', 'custom_amount_mxn', 'license_amount_mxn',
                'billing_amount', 'amount', 'precio', 'monto',
            ] as $c) {
                if ($ahas($c)) $select[] = "accounts.$c";
            }

            $qb = DB::connection($this->adm)->table('accounts')->select($select);

            if ($accountId !== '') {
                $qb->where('accounts.id', $accountId);
            }

            if ($q !== '') {
                $qb->where(function ($w) use ($q, $ahas) {
                    $w->where('accounts.id', 'like', "%{$q}%")
                      ->orWhere('accounts.email', 'like', "%{$q}%");

                    if ($ahas('name'))         $w->orWhere('accounts.name', 'like', "%{$q}%");
                    if ($ahas('razon_social')) $w->orWhere('accounts.razon_social', 'like', "%{$q}%");
                    if ($ahas('rfc'))          $w->orWhere('accounts.rfc', 'like', "%{$q}%");
                });
            }

            $qb->orderByDesc($ahas('created_at') ? 'accounts.created_at' : 'accounts.id');

            // base list (limit duro para evitar cargas gigantes)
            $rows = collect($qb->limit(250)->get());
            $ids  = $rows->pluck('id')->filter()->values()->all();

            // 1) agregados desde estados_cuenta
            $agg = DB::connection($this->adm)->table('estados_cuenta')
                ->selectRaw('account_id as aid, SUM(COALESCE(cargo,0)) as cargo, SUM(COALESCE(abono,0)) as abono')
                ->whereIn('account_id', !empty($ids) ? $ids : ['__none__'])
                ->where('periodo', '=', $period)
                ->groupBy('account_id')
                ->get()
                ->keyBy('aid');

            // 2) pagos pagados por cuenta
            $paidByAcc = $this->sumPaymentsPaidByAccountForPeriod($ids, $period);

<<<<<<< HEAD
            // 3) último enviado por cuenta/periodo
=======
            // 3) ÃƒÂºltimo enviado por cuenta/periodo
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
            $sentMap = $this->lastSentAtByAccountForPeriod($ids, $period);

            // 4) tracking open/click por cuenta/periodo (incluye first/last + last_sent_at)
            $trkMap = $this->trackingByAccountForPeriod($ids, $period);

            $rows = $rows->map(function ($r) use ($agg, $paidByAcc, $sentMap, $trkMap, $period) {
                $a = $agg[$r->id] ?? null;

                $cargoReal = (float) ($a->cargo ?? 0);
                $paidEc    = (float) ($a->abono ?? 0);

                // FIX: SIEMPRE sumar EC + PAYMENTS
                $paidPay = (float) ($paidByAcc[(string) $r->id] ?? 0);
                $paid    = $paidEc + $paidPay;

                $meta = $this->decodeMeta($r->meta ?? null);

                // tracking
                $t = $trkMap[(string) $r->id] ?? [];
                $r->open_count      = (int) ($t['open_count'] ?? 0);
                $r->click_count     = (int) ($t['click_count'] ?? 0);
                $r->first_open_at   = $t['first_open_at'] ?? null;
                $r->last_open_at    = $t['last_open_at'] ?? null;
                $r->first_click_at  = $t['first_click_at'] ?? null;
                $r->last_click_at   = $t['last_click_at'] ?? null;

                // monto esperado (licencia / personalizado)
                $custom = $this->extractCustomAmountMxn($r, $meta);

                if ($custom !== null && $custom > 0.00001) {
                    $expected    = $custom;
                    $tarifaLabel = 'PERSONALIZADO';
                    $tarifaPill  = 'pill-info';
                } else {
                    [$expected, $tarifaLabel, $tarifaPill] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);
                    $tarifaPill = $this->mapTarifaPillToCss($tarifaPill);
                }

                $totalShown = $cargoReal > 0 ? $cargoReal : (float) $expected;
                $saldo      = max(0.0, $totalShown - $paid);

                $r->cargo = round($cargoReal, 2);
                $r->abono = round($paid, 2);
                $r->abono_ec  = round($paidEc, 2);
                $r->abono_pay = round($paidPay, 2);

                $r->expected_total = round((float) $expected, 2);
                $r->tarifa_label   = (string) $tarifaLabel;
                $r->tarifa_pill    = (string) $tarifaPill;

                // status base
                $r->status_pago = ($totalShown <= 0.00001) ? 'sin_mov'
                    : (($saldo <= 0.00001) ? 'pagado'
                    : (($paid > 0.00001) ? 'parcial' : 'pendiente'));

                // vencido
                $r->is_overdue = false;
                try {
                    $cur = now()->format('Y-m');
                    if (in_array($r->status_pago, ['pendiente', 'parcial'], true) && $period < $cur) {
                        $r->is_overdue = true;
                        $r->status_pago = 'vencido';
                    }
                } catch (\Throwable $e) {
                    // ignore
                }

                // para filtros de envÃƒÂ­o
                $r->last_sent_at = $sentMap[(string) $r->id] ?? null;
                if (empty($r->last_sent_at) && !empty($t['last_sent_at'])) {
                    $r->last_sent_at = (string) $t['last_sent_at'];
                }

                // modo cobro desde meta.billing.mode
                $mode = strtolower(trim((string) (data_get($meta, 'billing.mode') ?? data_get($meta, 'billing.modo') ?? '')));
                $r->billing_mode = $mode;

                // plan normalized
                $r->plan_norm = strtolower(trim((string) ($r->plan_actual ?? $r->plan ?? '')));

                return $r;
            });

            // ==========================
            // APLICAR FILTROS PRO (collection)
            // ==========================
            if ($status !== '') {
                $rows = $rows->filter(fn($r) => strtolower((string) ($r->status_pago ?? '')) === $status)->values();
            }

            if ($saldoMin !== null) {
                $rows = $rows->filter(function ($r) use ($saldoMin) {
                    $totalShown = (float) (((float) ($r->cargo ?? 0) > 0) ? ($r->cargo ?? 0) : ($r->expected_total ?? 0));
                    $paid = (float) ($r->abono ?? 0);
                    $saldo = max(0.0, $totalShown - $paid);
                    return $saldo >= (float) $saldoMin;
                })->values();
            }

            if ($saldoMax !== null) {
                $rows = $rows->filter(function ($r) use ($saldoMax) {
                    $totalShown = (float) (((float) ($r->cargo ?? 0) > 0) ? ($r->cargo ?? 0) : ($r->expected_total ?? 0));
                    $paid = (float) ($r->abono ?? 0);
                    $saldo = max(0.0, $totalShown - $paid);
                    return $saldo <= (float) $saldoMax;
                })->values();
            }

            if ($plan !== '') {
                $rows = $rows->filter(function ($r) use ($plan) {
                    $p = (string) ($r->plan_norm ?? '');
                    if ($p === '') return false;
                    return str_contains($p, $plan);
                })->values();
            }

            if ($modo !== '') {
                $rows = $rows->filter(fn($r) => strtolower((string) ($r->billing_mode ?? '')) === $modo)->values();
            }

            if ($sent !== '') {
                $now = now();
                $rows = $rows->filter(function ($r) use ($sent, $now) {
                    $ts = $r->last_sent_at ?? null;

                    if ($sent === 'never') return empty($ts);
                    if (empty($ts)) return false;

                    try {
                        $dt = Carbon::parse((string) $ts);
                    } catch (\Throwable $e) {
                        return false;
                    }

                    if ($sent === 'today') return $dt->toDateString() === $now->toDateString();
                    if ($sent === '7d')    return $dt->greaterThanOrEqualTo($now->copy()->subDays(7));
                    if ($sent === '30d')   return $dt->greaterThanOrEqualTo($now->copy()->subDays(30));

                    return true;
                })->values();
            }

            // paginaciÃƒÂ³n simple (UI per_page)
            $rows = $rows->slice(0, $perPage)->values();

            // KPIs con lo mostrado
            $kCargo = 0.0; $kAbono = 0.0; $kSaldo = 0.0; $kAcc = 0;
            foreach ($rows as $r) {
                $totalShown = (float) (((float) ($r->cargo ?? 0) > 0) ? ($r->cargo ?? 0) : ($r->expected_total ?? 0));
                $paid = (float) ($r->abono ?? 0);
                $saldo = max(0.0, $totalShown - $paid);

                $kCargo += $totalShown;
                $kAbono += $paid;
                $kSaldo += $saldo;
                $kAcc++;
            }

            $kpis = [
                'cargo'    => round($kCargo, 2),
                'abono'    => round($kAbono, 2),
                'saldo'    => round($kSaldo, 2),
                'accounts' => (int) $kAcc,
            ];
        }

        // ==========================
        // 2) Emails
        // ==========================
        $emails = collect();
        if ($hasEmailLogs) {
            $emailsQ = DB::connection($this->adm)->table('billing_email_logs')->orderByDesc('id');

            $cols = Schema::connection($this->adm)->getColumnListing('billing_email_logs');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

            if ($accountId !== '' && $has('account_id')) $emailsQ->where('account_id', $accountId);
            if ($period !== '' && $has('period')) $emailsQ->where('period', $period);

            if ($q !== '') {
                $emailsQ->where(function ($w) use ($q, $has) {
                    $started = false;

                    if ($has('email')) {
                        $w->where('email', 'like', "%{$q}%"); $started = true;
                    }
                    if ($has('to_list')) {
                        $started ? $w->orWhere('to_list', 'like', "%{$q}%") : $w->where('to_list', 'like', "%{$q}%");
                        $started = true;
                    }
                    if ($has('subject')) {
                        $started ? $w->orWhere('subject', 'like', "%{$q}%") : $w->where('subject', 'like', "%{$q}%");
                        $started = true;
                    }
                    if ($has('template')) {
                        $started ? $w->orWhere('template', 'like', "%{$q}%") : $w->where('template', 'like', "%{$q}%");
                        $started = true;
                    }
                    if ($has('status')) {
                        $started ? $w->orWhere('status', 'like', "%{$q}%") : $w->where('status', 'like', "%{$q}%");
                        $started = true;
                    }
                    if ($has('account_id')) {
                        $started ? $w->orWhere('account_id', 'like', "%{$q}%") : $w->where('account_id', 'like', "%{$q}%");
                        $started = true;
                    }
                    if ($has('period')) {
                        $started ? $w->orWhere('period', 'like', "%{$q}%") : $w->where('period', 'like', "%{$q}%");
                        $started = true;
                    }
                    if ($has('email_id')) {
                        $started ? $w->orWhere('email_id', 'like', "%{$q}%") : $w->where('email_id', 'like', "%{$q}%");
                    }
                });
            }

            $emails = collect($emailsQ->limit(200)->get());
        }

        // ==========================
        // 3) Pagos
        // ==========================
        $payments = collect();
        if ($hasPayments) {
            $payQ = DB::connection($this->adm)->table('payments')->orderByDesc('id');

            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

            if ($accountId !== '' && $has('account_id')) $payQ->where('account_id', $accountId);
            if ($period !== '' && $has('period')) $payQ->where('period', $period);

            if ($q !== '') {
                $payQ->where(function ($w) use ($q, $has) {
                    $started = false;

                    if ($has('status')) {
                        $w->where('status', 'like', "%{$q}%"); $started = true;
                    }
                    if ($has('provider')) {
                        $started ? $w->orWhere('provider', 'like', "%{$q}%") : $w->where('provider', 'like', "%{$q}%");
                        $started = true;
                    }
                    if ($has('reference')) {
                        $started ? $w->orWhere('reference', 'like', "%{$q}%") : $w->where('reference', 'like', "%{$q}%");
                        $started = true;
                    }
                    if ($has('stripe_session_id')) {
                        $started ? $w->orWhere('stripe_session_id', 'like', "%{$q}%") : $w->where('stripe_session_id', 'like', "%{$q}%");
                        $started = true;
                    }
                    if ($has('account_id')) {
                        $started ? $w->orWhere('account_id', 'like', "%{$q}%") : $w->where('account_id', 'like', "%{$q}%");
                    }
                });
            }

            $payments = collect($payQ->limit(200)->get());
        }

        // ==========================
        // 4) Solicitudes de factura
        // ==========================
        $invoiceRequests = collect();
        if ($hasInvoiceReq) {
            $irQ = DB::connection($this->adm)->table('billing_invoice_requests')->orderByDesc('id');
            if ($accountId !== '') $irQ->where('account_id', $accountId);
            if ($period !== '') $irQ->where('period', $period);

            if ($q !== '') {
                $irQ->where(function ($w) use ($q) {
                    $w->where('account_id', 'like', "%{$q}%")
                      ->orWhere('period', 'like', "%{$q}%")
                      ->orWhere('status', 'like', "%{$q}%")
                      ->orWhere('cfdi_uuid', 'like', "%{$q}%")
                      ->orWhere('notes', 'like', "%{$q}%");
                });
            }

            $invoiceRequests = collect($irQ->limit(200)->get());
        }

        // ==========================
        // 5) Facturas emitidas (admin)
        // ==========================
        $invoices = collect();
        if ($hasInvoices) {
            $invQ = DB::connection($this->adm)->table('billing_invoices')->orderByDesc('id');
            if ($accountId !== '') $invQ->where('account_id', $accountId);
            if ($period !== '') $invQ->where('period', $period);

            if ($q !== '') {
                $invQ->where(function ($w) use ($q) {
                    $w->where('account_id', 'like', "%{$q}%")
                      ->orWhere('period', 'like', "%{$q}%")
                      ->orWhere('cfdi_uuid', 'like', "%{$q}%")
                      ->orWhere('folio', 'like', "%{$q}%")
                      ->orWhere('serie', 'like', "%{$q}%")
                      ->orWhere('notes', 'like', "%{$q}%");
                });
            }

            $invoices = collect($invQ->limit(200)->get());
        }

        $isModal = (string) $req->query('modal', '') === '1';

        $view = view('admin.billing.statements.hub', compact(
            'tab', 'q', 'period', 'accountId',
            'status', 'saldoMin', 'saldoMax', 'plan', 'modo', 'sent', 'from', 'to', 'perPage',
            'rows', 'kpis',
            'emails', 'payments', 'invoiceRequests', 'invoices',
            'hasAccounts', 'hasStatements', 'hasEmailLogs', 'hasPayments', 'hasInvoiceReq', 'hasInvoices',
            'isModal'
        ));

        if ($isModal) {
            return response($view->render(), 200, [
                'Content-Type'    => 'text/html; charset=UTF-8',
                'Cache-Control'   => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'          => 'no-cache',
                'X-Frame-Options' => 'SAMEORIGIN',
            ]);
        }

        return $view;
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

<<<<<<< HEAD
=======

>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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

<<<<<<< HEAD
=======

>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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

            return back()->with('ok', 'ReenvÃƒÂ­o OK. Nuevo email_id=' . $emailId);
        } catch (\Throwable $e) {
            DB::connection($this->adm)->table('billing_email_logs')->where('id', $id)->update([
                'status'     => 'failed',
                'failed_at'  => now(),
                'updated_at' => now(),
                'meta'       => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);

            return back()->withErrors(['email' => 'ReenvÃƒÂ­o fallÃƒÂ³: ' . $e->getMessage()]);
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

    public function createPayLink(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
        ]);

        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];


        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        if (!$acc) { return response('Cuenta no encontrada.', 404)->header("Cache-Control","no-store, max-age=0, public")->header('Pragma','no-cache'); }

        $items = DB::connection($this->adm)->table('estados_cuenta')
            ->where('account_id', $accountId)
            ->where('periodo', $period)
            ->get();

        $cargoReal = (float) $items->sum('cargo');
        $abonoEc   = (float) $items->sum('abono');

        // FIX: SIEMPRE sumar payments pagados
        $abonoPay  = (float) $this->sumPaymentsPaidForAccountPeriod($accountId, $period);
        $abono     = $abonoEc + $abonoPay;

        $meta = $this->decodeMeta($acc->meta ?? null);
        $custom = $this->extractCustomAmountMxn($acc, $meta);

        if ($custom !== null && $custom > 0.00001) {
            $expected = $custom;
        } else {
            [$expected] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);
        }

        $totalShown = $cargoReal > 0 ? $cargoReal : (float) $expected;
        $saldo = max(0.0, $totalShown - $abono);

        if ($saldo <= 0.00001) {
            return response('No hay saldo pendiente para ese periodo.', 200)->header("Cache-Control","no-store, max-age=0, public")->header('Pragma','no-cache');
        }

        try {
            [$url, $sessionId] = $this->createStripeCheckoutForStatement($acc, $period, $saldo);
            return back()->with('ok', 'Liga de pago (Stripe): ' . $url . ' (session=' . $sessionId . ')');
        } catch (\Throwable $e) {
            return response('No se pudo generar liga: ' . $e->getMessage(), 500);
        }
    }

    private function createStripeCheckoutForStatement(object $acc, string $period, float $totalPesos): array
    {
        $secret = (string) config('services.stripe.secret');
        if (trim($secret) === '') {
<<<<<<< HEAD
            throw new \RuntimeException('Stripe secret vacío.');
=======
            throw new \RuntimeException('Stripe secret vacÃƒÂ­o.');
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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
                        'name' => 'Pactopia360 Ã‚Â· Estado de cuenta ' . $period,
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
<<<<<<< HEAD
            $row['concept'] = 'Pactopia360 · Estado de cuenta ' . $period;
=======
            $row['concept'] = 'Pactopia360 Ã‚Â· Estado de cuenta ' . $period;
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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

<<<<<<< HEAD
        // PDF / Estado de cuenta (si tienes ruta real, cámbiala aquí)
=======
        // PDF / Estado de cuenta (si tienes ruta real, cÃƒÂ¡mbiala aquÃƒÂ­)
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
        $pdfUrl = Route::has('cliente.estado_cuenta')
            ? route('cliente.estado_cuenta') . '?period=' . urlencode($period)
            : url('/cliente/estado-de-cuenta') . '?period=' . urlencode($period);

        // Pay URL: en preview no generar checkout
        $payUrl = '';
        if (!$isPreview && $saldo > 0.00001) {
<<<<<<< HEAD
            // Esto solo construye link al HUB action (no crea checkout aquí)
=======
            // Esto solo construye link al HUB action (no crea checkout aquÃƒÂ­)
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
            // (el checkout se crea con createPayLink en el HUB)
            $payUrl = Route::has('admin.billing.hub.paylink')
                ? route('admin.billing.hub.paylink') . '?account_id=' . urlencode($accountId) . '&period=' . urlencode($period)
                : url('/admin/billing/hub/paylink') . '?account_id=' . urlencode($accountId) . '&period=' . urlencode($period);
        }

<<<<<<< HEAD
        $subject = 'Pactopia360 · Estado de cuenta ' . $period . ' · ' . $rs;
=======
        $subject = 'Pactopia360 Ã‚Â· Estado de cuenta ' . $period . ' Ã‚Â· ' . $rs;
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)

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
        if (!Schema::connection($this->adm)->hasTable('payments')) return 0.0;

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) return 0.0;

        $q = DB::connection($this->adm)->table('payments')
            ->where('account_id', $accountId)
            ->where('period', $period);

        if ($has('status')) {
            $q->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized']);
        }

        if ($has('provider')) {
            $q->where(function ($w) {
                $w->whereNull('provider')->orWhere('provider', '')->orWhere('provider', 'stripe');
            });
        }

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
        if (empty($accountIds)) return $out;
        if (!Schema::connection($this->adm)->hasTable('payments')) return $out;

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) return $out;

        $q = DB::connection($this->adm)->table('payments')
            ->whereIn('account_id', $accountIds)
            ->where('period', $period);

        if ($has('status')) {
            $q->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized']);
        }

        if ($has('provider')) {
            $q->where(function ($w) {
                $w->whereNull('provider')->orWhere('provider', '')->orWhere('provider', 'stripe');
            });
        }

        if ($has('amount_mxn')) {
            $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(amount_mxn,0)) as mxn')
                ->groupBy('account_id')
                ->get();

            foreach ($rows as $r) {
                $aid = (string) ($r->aid ?? '');
                $mxn = (float) ($r->mxn ?? 0);
                if ($aid !== '') $out[$aid] = round(max(0.0, $mxn), 2);
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
                if ($aid !== '') $out[$aid] = round(max(0.0, $mxn), 2);
            }
            return $out;
        }

        $colMxn = $has('monto_mxn') ? 'monto_mxn' : null;
        if (!$colMxn) return $out;

        $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(' . $colMxn . ',0)) as mxn')
            ->groupBy('account_id')
            ->get();

        foreach ($rows as $r) {
            $aid = (string) ($r->aid ?? '');
            $mxn = (float) ($r->mxn ?? 0);
            if ($aid !== '') $out[$aid] = round(max(0.0, $mxn), 2);
        }

        return $out;
    } 

/**
 * Devuelve el último pago "pagado" por cuenta/periodo para pintarlo en UI
 * (fecha + método + proveedor + referencia + monto).
 *
 * @return array<string,array{paid_at:?string,method:string,provider:string,reference:string,amount:float}>
 */
private function lastPaidPaymentByAccountForPeriod(array $accountIds, string $period): array
{
    $out = [];

    if (empty($accountIds)) return $out;
    if (!Schema::connection($this->adm)->hasTable('payments')) return $out;

    $cols = Schema::connection($this->adm)->getColumnListing('payments');
    $lc   = array_map('strtolower', $cols);
    $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

    if (!$has('account_id') || !$has('period')) return $out;

    // status "pagado"
    $paidStatuses = ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized'];

    // NOTA: amounts en payments están en centavos (MXN) en tu data real.
    // Vamos a normalizar a pesos.
    $rows = DB::connection($this->adm)->table('payments')
        ->select([
            'account_id',
            $has('paid_at')   ? 'paid_at'   : DB::raw('NULL as paid_at'),
            $has('method')    ? 'method'    : DB::raw("'' as method"),
            $has('provider')  ? 'provider'  : DB::raw("'' as provider"),
            $has('reference') ? 'reference' : DB::raw("'' as reference"),
            $has('amount')    ? 'amount'    : DB::raw('0 as amount'),
            $has('currency')  ? 'currency'  : DB::raw("'' as currency"),
            'id',
        ])
        ->whereIn('account_id', $accountIds)
        ->where('period', $period);

    if ($has('status')) {
        $rows->whereIn('status', $paidStatuses);
    }

    // preferimos paid_at, si existe; si no, por id desc
    if ($has('paid_at')) {
        $rows->orderByDesc('paid_at');
    }
    $rows->orderByDesc('id');

    $all = $rows->get();

    foreach ($all as $p) {
        $aid = (string) ($p->account_id ?? '');
        if ($aid === '' || isset($out[$aid])) continue;

        $rawAmount = (float) ($p->amount ?? 0);

        // Normaliza a pesos (centavos -> pesos). En tu BD: 5800000 => 58000.00
        $amountMxn = $rawAmount / 100.0;

        $out[$aid] = [
            'paid_at'   => !empty($p->paid_at) ? (string) $p->paid_at : null,
            'method'    => trim((string) ($p->method ?? '')),
            'provider'  => trim((string) ($p->provider ?? '')),
            'reference' => trim((string) ($p->reference ?? '')),
            'amount'    => round($amountMxn, 2),
        ];
    }

    return $out;
}



    // =========================================================
    // Email logs helper (ÃƒÂºltimo enviado por cuenta/periodo)
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

<<<<<<< HEAD
    /**
     * @return arrayng>
=======
   /**
     * @return array<int,string>
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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

<<<<<<< HEAD
=======

>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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
<<<<<<< HEAD
        if (str_contains($raw, 'proximo') || str_contains($raw, 'próximo') || str_contains($raw, 'next')) return 'pill-warn';
=======
        if (str_contains($raw, 'proximo') || str_contains($raw, 'prÃƒÂ³ximo') || str_contains($raw, 'next')) return 'pill-warn';
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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

<<<<<<< HEAD
    public function payLink(Request $req)
=======
public function payLink(Request $req): RedirectResponse
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
{
    $data = $req->validate([
        'account_id' => 'required|string|max:64',
        'period'     => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
    ]);

    $accountId = (string) $data['account_id'];
    $period    = (string) $data['period'];

    $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
<<<<<<< HEAD
    if (!$acc) { return response('Cuenta no encontrada.', 404)->header("Cache-Control","no-store, max-age=0, public")->header('Pragma','no-cache'); }
=======
    if (!$acc) {
        return redirect()->to('/admin/login')
            ->withErrors(['pay' => 'Cuenta no encontrada.']);
    }
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)

    $items = collect();
    if (Schema::connection($this->adm)->hasTable('estados_cuenta')) {
        $items = DB::connection($this->adm)->table('estados_cuenta')
            ->where('account_id', $accountId)
            ->where('periodo', $period)
            ->get();
    }

    $cargoReal = (float) $items->sum('cargo');
    $abonoEc   = (float) $items->sum('abono');

<<<<<<< HEAD
    $abonoPay  = (float) $this->sumPaymentsPaidForAccountPeriod($accountId, $period);
    $abono     = $abonoEc + $abonoPay;

    $meta = $this->decodeMeta($acc->meta ?? null);
=======
    // FIX: sumar payments pagados
    $abonoPay  = (float) $this->sumPaymentsPaidForAccountPeriod($accountId, $period);
    $abono     = $abonoEc + $abonoPay;

    $meta   = $this->decodeMeta($acc->meta ?? null);
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
    $custom = $this->extractCustomAmountMxn($acc, $meta);

    if ($custom !== null && $custom > 0.00001) {
        $expected = $custom;
    } else {
        [$expected] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);
    }

    $totalShown = $cargoReal > 0 ? $cargoReal : (float) $expected;
<<<<<<< HEAD
    $saldo = max(0.0, $totalShown - $abono);

    if ($saldo <= 0.00001) {
        return response('No hay saldo pendiente para ese periodo.', 200)->header("Cache-Control","no-store, max-age=0, public")->header('Pragma','no-cache');
    }

    try {
        [$url, $sessionId] = $this->createStripeCheckoutForStatement($acc, $period, $saldo);
        return redirect()->away((string) $url)->withHeaders(['Cache-Control' => 'no-store, max-age=0, public', 'Pragma' => 'no-cache']);
    } catch (\Throwable $e) {
        return response('No se pudo generar liga: ' . $e->getMessage(), 500);
    }
}

=======
    $saldo      = max(0.0, $totalShown - $abono);

    if ($saldo <= 0.00001) {
        return redirect()->to('/admin/login')
            ->with('ok', 'No hay saldo pendiente para ese periodo.');
    }

    try {
        [$url] = $this->createStripeCheckoutForStatement($acc, $period, $saldo);
        return redirect()->away((string) $url)->withHeaders([
            'Cache-Control' => 'no-store, max-age=0, public',
            'Pragma'        => 'no-cache',
        ]);
    } catch (\Throwable $e) {
        return redirect()->to('/admin/login')
            ->withErrors(['pay' => 'No se pudo generar liga: ' . $e->getMessage()]);
    }
}

/**
 * Registrar pago MANUAL (transferencia/efectivo/otro) con fecha de pago.
 * Esto impacta el HUB porque el status se calcula por suma de pagos pagados.
 *
 * Reglas:
 * - Si viene amount_mxn vacÃ­o o 0 y viene liquidate=1 => paga el saldo restante del periodo.
 * - Si amount_mxn > 0 => registra ese monto (pagado o parcial).
 */
public function manualPayment(Request $req): RedirectResponse
{
    $data = $req->validate([
        'account_id' => 'required|string|max:64',
        'period'     => ['required', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],

        // monto puede venir vacÃ­o si liquidate=1
        'amount_mxn' => 'nullable|numeric|min:0|max:999999999',

        // fecha de pago (si no viene, usa ahora)
        'paid_at'    => 'nullable|date',

        // transferencia|efectivo|otro|stripe(card) (para consistencia)
        'method'     => 'nullable|string|max:30',

        // referencia bancaria / folio
        'reference'  => 'nullable|string|max:120',

        // notas internas
        'notes'      => 'nullable|string|max:5000',

        // si 1: auto-calcula saldo restante y lo paga completo
        'liquidate'  => 'nullable|in:0,1',
    ]);

    $accountId = (string) $data['account_id'];
    $period    = (string) $data['period'];

    if (!Schema::connection($this->adm)->hasTable('payments')) {
        return back()->withErrors(['manual_payment' => 'No existe la tabla payments.']);
    }
    if (!Schema::connection($this->adm)->hasTable('accounts')) {
        return back()->withErrors(['manual_payment' => 'No existe la tabla accounts.']);
    }

    $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
    if (!$acc) return back()->withErrors(['manual_payment' => 'Cuenta no encontrada.']);

    $paidAt = !empty($data['paid_at'])
        ? Carbon::parse((string) $data['paid_at'])
        : now();

    $method = strtolower(trim((string) ($data['method'] ?? 'transfer')));
    if ($method === '') $method = 'transfer';
    if (!in_array($method, ['transfer','transferencia','cash','efectivo','other','otro','card','stripe'], true)) {
        $method = 'transfer';
    }
    // normaliza
    if ($method === 'transferencia') $method = 'transfer';
    if ($method === 'efectivo') $method = 'cash';
    if ($method === 'otro') $method = 'other';

    $amount = (float) ($data['amount_mxn'] ?? 0);
    $liquidate = (string) ($data['liquidate'] ?? '0') === '1';

    // si piden liquidar y monto viene 0 => calcular saldo y pagarlo completo
    if ($amount <= 0.00001 && $liquidate) {
        $saldo = $this->computeSaldoForAccountPeriod($accountId, $period, $acc);
        if ($saldo <= 0.00001) {
            return back()->with('ok', 'No hay saldo pendiente; no se registrÃ³ pago.');
        }
        $amount = $saldo;
    }

    if ($amount <= 0.00001) {
        return back()->withErrors(['manual_payment' => 'Monto invÃ¡lido. Usa un monto > 0 o marca "Liquidar".']);
    }

    $reference = trim((string) ($data['reference'] ?? ''));
    $notes     = trim((string) ($data['notes'] ?? ''));

    // Insertar en payments como paid + provider manual
    $id = $this->insertManualPaidPayment([
        'account_id' => $accountId,
        'period'     => $period,
        'amount_mxn' => round($amount, 2),
        'paid_at'    => $paidAt,
        'method'     => $method,
        'reference'  => $reference !== '' ? $reference : null,
        'notes'      => $notes !== '' ? $notes : null,
    ]);

    return back()->with('ok', 'Pago manual registrado (#' . $id . '). Se reflejarÃ¡ en el HUB automÃ¡ticamente.');
}

/**
 * Calcula saldo restante para account/period (misma lÃ³gica que hub).
 */
private function computeSaldoForAccountPeriod(string $accountId, string $period, object $acc): float
{
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

    $meta = $this->decodeMeta($acc->meta ?? null);
    $custom = $this->extractCustomAmountMxn($acc, $meta);

    if ($custom !== null && $custom > 0.00001) {
        $expected = $custom;
    } else {
        [$expected] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);
    }

    $totalShown = $cargoReal > 0 ? $cargoReal : (float) $expected;
    $saldo = max(0.0, $totalShown - $abono);

    return round($saldo, 2);
}

/**
 * Inserta un pago manual en payments (robusto a columnas distintas).
 * Retorna ID si existe autoincrement; si no, retorna 0.
 *
 * @param array<string,mixed> $payload
 */
private function insertManualPaidPayment(array $payload): int
{
    $conn = $this->adm;

    $cols = Schema::connection($conn)->getColumnListing('payments');
    $lc   = array_map('strtolower', $cols);
    $has  = static fn(string $c) => in_array(strtolower($c), $lc, true);

    $row = [];
    $now = now();

    if ($has('account_id')) $row['account_id'] = (string) ($payload['account_id'] ?? '');
    if ($has('period'))     $row['period']     = (string) ($payload['period'] ?? '');

    $mxn = (float) ($payload['amount_mxn'] ?? 0);

    // monto (preferencia: amount_mxn/monto_mxn; fallback: cents)
    if ($has('amount_mxn')) $row['amount_mxn'] = round($mxn, 2);
    if ($has('monto_mxn'))  $row['monto_mxn']  = round($mxn, 2);

    $cents = (int) round($mxn * 100);
    if ($has('amount'))       $row['amount']       = $cents;
    if ($has('amount_cents')) $row['amount_cents'] = $cents;

    if ($has('currency')) $row['currency'] = 'MXN';

    if ($has('status'))   $row['status']   = 'paid';
    if ($has('provider')) $row['provider'] = 'manual';

    // mÃ©todo
    if ($has('method')) $row['method'] = (string) ($payload['method'] ?? 'transfer');

    // referencia
    if ($has('reference')) $row['reference'] = $payload['reference'] ?? null;

    // fechas
    $paidAt = $payload['paid_at'] ?? $now;
    if ($has('paid_at')) $row['paid_at'] = $paidAt;
    if ($has('payment_date')) $row['payment_date'] = $paidAt;
    if ($has('fecha_pago')) $row['fecha_pago'] = $paidAt;

    // notas / concepto / meta
    if ($has('concept')) {
        $row['concept'] = 'Pago manual Â· Estado de cuenta ' . (string) ($payload['period'] ?? '');
    }
    if ($has('notes')) {
        $row['notes'] = $payload['notes'] ?? null;
    }
    if ($has('meta')) {
        $row['meta'] = json_encode([
            'type'      => 'billing_statement_manual',
            'period'    => (string) ($payload['period'] ?? ''),
            'source'    => 'admin_hub',
            'notes'     => $payload['notes'] ?? null,
        ], JSON_UNESCAPED_UNICODE);
    }

    // timestamps
    if ($has('created_at')) $row['created_at'] = $now;
    if ($has('updated_at')) $row['updated_at'] = $now;

    // inserciÃ³n (retorna id si existe)
    try {
        if ($has('id')) {
            return (int) DB::connection($conn)->table('payments')->insertGetId($row);
        }
        DB::connection($conn)->table('payments')->insert($row);
        return 0;
    } catch (\Throwable $e) {
        Log::error('[BILLING_HUB][manualPayment] insert failed', [
            'e' => $e->getMessage(),
            'row' => $row,
        ]);
        throw $e;
    }
}


>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
}
