<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\Billing\BillingStatementsHubController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Mail\Admin\Billing\StatementAccountPeriodMail;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
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
        $tab = (string)$req->get('tab', 'statements'); // statements|emails|payments|invoice_requests|invoices
        $q = trim((string) $req->get('q', ''));
        $period = (string) $req->get('period', now()->format('Y-m'));
        if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) $period = now()->format('Y-m');

        $accountId = trim((string)$req->get('accountId', ''));

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
        $kpis = ['cargo'=>0,'abono'=>0,'saldo'=>0,'accounts'=>0];

        if ($hasAccounts && $hasStatements) {
            $accCols = Schema::connection($this->adm)->getColumnListing('accounts');
            $alc = array_map('strtolower', $accCols);
            $ahas = fn(string $c) => in_array(strtolower($c), $alc, true);

            $select = ['accounts.id','accounts.email'];
            foreach (['name','razon_social','rfc','plan','plan_actual','meta','created_at'] as $c) {
                if ($ahas($c)) $select[] = "accounts.$c";
            }
            foreach ([
                'billing_amount_mxn','amount_mxn','precio_mxn','monto_mxn',
                'override_amount_mxn','custom_amount_mxn','license_amount_mxn',
                'billing_amount','amount','precio','monto',
            ] as $c) {
                if ($ahas($c)) $select[] = "accounts.$c";
            }

            $qb = DB::connection($this->adm)->table('accounts')->select($select);

            if ($accountId !== '') $qb->where('accounts.id', $accountId);

            if ($q !== '') {
                $qb->where(function ($w) use ($q, $ahas) {
                    $w->where('accounts.id', 'like', "%{$q}%");
                    $w->orWhere('accounts.email', 'like', "%{$q}%");
                    if ($ahas('name'))          $w->orWhere('accounts.name', 'like', "%{$q}%");
                    if ($ahas('razon_social'))  $w->orWhere('accounts.razon_social', 'like', "%{$q}%");
                    if ($ahas('rfc'))           $w->orWhere('accounts.rfc', 'like', "%{$q}%");
                });
            }

            $qb->orderByDesc($ahas('created_at') ? 'accounts.created_at' : 'accounts.id');

            $rows = collect($qb->limit(250)->get());

            $ids = $rows->pluck('id')->filter()->values()->all();

            // 1) agregados desde estados_cuenta (SOT tradicional)
            $agg = DB::connection($this->adm)->table('estados_cuenta')
                ->selectRaw('account_id as aid, SUM(cargo) as cargo, SUM(abono) as abono')
                ->whereIn('account_id', !empty($ids) ? $ids : ['__none__'])
                ->where('periodo', '=', $period)
                ->groupBy('account_id')
                ->get()
                ->keyBy('aid');

            // 2) agregados desde payments (SOT Stripe/webhook) para evitar "pagado en ceros"
            $paidByAcc = $this->sumPaymentsPaidByAccountForPeriod($ids, $period);

            $kCargo=0.0; $kAbono=0.0; $kSaldo=0.0; $kAcc=0;

            $rows = $rows->map(function ($r) use ($agg, $paidByAcc, $period, &$kCargo, &$kAbono, &$kSaldo, &$kAcc) {
                $a = $agg[$r->id] ?? null;

                $cargoReal = (float)($a->cargo ?? 0);
                $paidEc    = (float)($a->abono ?? 0);

                // ✅ Si estados_cuenta no trae abonos (0), usamos payments pagados.
                $paidPay = (float)($paidByAcc[(string)$r->id] ?? 0);
                $paid    = ($paidEc > 0.00001) ? $paidEc : $paidPay;

                $meta = $this->decodeMeta($r->meta ?? null);

                // monto “esperado” (licencia / personalizado)
                $custom = $this->extractCustomAmountMxn($r, $meta);
                if ($custom !== null && $custom > 0.00001) {
                    $expected = $custom;
                    $tarifaLabel = 'PERSONALIZADO';
                    $tarifaPill  = 'pill-info';
                } else {
                    // aquí no tenemos payAllowed (HUB listing), se evalúa con base a meta/base.
                    [$expected, $tarifaLabel, $tarifaPill] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);
                    $tarifaPill = $this->mapTarifaPillToCss($tarifaPill);
                }

                $totalShown = $cargoReal > 0 ? $cargoReal : (float)$expected;
                $saldo = max(0, $totalShown - $paid);

                $r->cargo = round($cargoReal,2);
                $r->abono = round($paid,2);
                $r->expected_total = round((float)$expected,2);
                $r->tarifa_label = (string)$tarifaLabel;
                $r->tarifa_pill  = (string)$tarifaPill;

                $r->status_pago = ($totalShown <= 0.00001) ? 'sin_mov' : (($saldo <= 0.00001) ? 'pagado' : 'pendiente');

                $kCargo += $totalShown;
                $kAbono += $paid;
                $kSaldo += $saldo;
                $kAcc++;

                return $r;
            });

            $kpis = [
                'cargo'    => round($kCargo,2),
                'abono'    => round($kAbono,2),
                'saldo'    => round($kSaldo,2),
                'accounts' => (int)$kAcc,
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
            $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

            if ($accountId !== '' && $has('account_id')) $emailsQ->where('account_id', $accountId);
            if ($period !== '' && $has('period')) $emailsQ->where('period', $period);

            if ($q !== '') {
                $emailsQ->where(function($w) use ($q, $has) {
                    if ($has('email'))      $w->orWhere('email', 'like', "%{$q}%");
                    if ($has('to_list'))    $w->orWhere('to_list', 'like', "%{$q}%");
                    if ($has('subject'))    $w->orWhere('subject', 'like', "%{$q}%");
                    if ($has('template'))   $w->orWhere('template', 'like', "%{$q}%");
                    if ($has('status'))     $w->orWhere('status', 'like', "%{$q}%");
                    if ($has('account_id')) $w->orWhere('account_id', 'like', "%{$q}%");
                    if ($has('period'))     $w->orWhere('period', 'like', "%{$q}%");
                });
            }

            $emails = $emailsQ->limit(120)->get();
        }

        // ==========================
        // 3) Pagos
        // ==========================
        $payments = collect();
        if ($hasPayments) {
            $payQ = DB::connection($this->adm)->table('payments')->orderByDesc('id');
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

            if ($accountId !== '' && $has('account_id')) $payQ->where('account_id', $accountId);
            if ($period !== '' && $has('period')) $payQ->where('period', $period);

            if ($q !== '') {
                $payQ->where(function($w) use ($q, $has) {
                    if ($has('status'))            $w->orWhere('status', 'like', "%{$q}%");
                    if ($has('provider'))          $w->orWhere('provider', 'like', "%{$q}%");
                    if ($has('reference'))         $w->orWhere('reference', 'like', "%{$q}%");
                    if ($has('stripe_session_id')) $w->orWhere('stripe_session_id', 'like', "%{$q}%");
                    if ($has('account_id'))        $w->orWhere('account_id', 'like', "%{$q}%");
                });
            }

            $payments = $payQ->limit(120)->get();
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
                $irQ->where(function($w) use ($q) {
                    $w->orWhere('account_id','like',"%{$q}%")
                      ->orWhere('period','like',"%{$q}%")
                      ->orWhere('status','like',"%{$q}%")
                      ->orWhere('cfdi_uuid','like',"%{$q}%")
                      ->orWhere('notes','like',"%{$q}%");
                });
            }
            $invoiceRequests = $irQ->limit(120)->get();
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
                $invQ->where(function($w) use ($q) {
                    $w->orWhere('account_id','like',"%{$q}%")
                      ->orWhere('period','like',"%{$q}%")
                      ->orWhere('cfdi_uuid','like',"%{$q}%")
                      ->orWhere('folio','like',"%{$q}%")
                      ->orWhere('serie','like',"%{$q}%")
                      ->orWhere('notes','like',"%{$q}%");
                });
            }
            $invoices = $invQ->limit(120)->get();
        }

        $isModal = (string)$req->query('modal', '') === '1';

        $view = view('admin.billing.statements.hub', compact(
            'tab','q','period','accountId',
            'rows','kpis',
            'emails','payments','invoiceRequests','invoices',
            'hasAccounts','hasStatements','hasEmailLogs','hasPayments','hasInvoiceReq','hasInvoices',
            'isModal'
        ));

        if ($isModal) {
            return response($view->render(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'X-Frame-Options' => 'SAMEORIGIN',
            ]);
        }

        return $view;
    }

    // =========================================================
    // ENVÍO: ahora / reenvío / multi-destinatario
    // =========================================================

    public function sendEmail(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required','regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'to'         => 'nullable|string|max:2000',
        ]);

        $accountId = (string)$data['account_id'];
        $period    = (string)$data['period'];
        $toRaw     = trim((string)($data['to'] ?? ''));

        $acc = DB::connection($this->adm)->table('accounts')
            ->select('id','email','rfc','razon_social','name','meta')
            ->where('id', $accountId)
            ->first();

        if (!$acc) return back()->withErrors(['account_id' => 'Cuenta no encontrada.']);

        $tos = $this->parseToList($toRaw);
        if (empty($tos)) $tos = $this->parseToList((string)($acc->email ?? ''));

        if (empty($tos)) return back()->withErrors(['to' => 'No hay correos destino.']);

        $emailId = (string) Str::ulid();

        $logId = $this->insertEmailLog([
            'email_id'     => $emailId,
            'account_id'   => $accountId,
            'period'       => $period,
            'email'        => $tos[0] ?? null,
            'to_list'      => implode(',', $tos),
            'template'     => 'statement',
            'status'       => 'queued',
            'provider'     => config('mail.default') ?: 'smtp',
            'subject'      => null,
            'payload'      => null,
            'meta'         => json_encode(['source'=>'admin_send_now','account_id'=>$accountId,'period'=>$period], JSON_UNESCAPED_UNICODE),
            'queued_at'    => now(),
        ]);

        try {
            $payload = $this->buildStatementEmailPayloadPublic($acc, $accountId, $period, $emailId);

            DB::connection($this->adm)->table('billing_email_logs')->where('id',$logId)->update([
                'subject'    => (string)($payload['subject'] ?? null),
                'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            Mail::to($tos)->send(new StatementAccountPeriodMail($accountId, $period, $payload));

            DB::connection($this->adm)->table('billing_email_logs')->where('id',$logId)->update([
                'status'=>'sent','sent_at'=>now(),'updated_at'=>now(),
            ]);

            return back()->with('ok', 'Correo enviado. Tracking activo (email_id='.$emailId.').');
        } catch (\Throwable $e) {
            DB::connection($this->adm)->table('billing_email_logs')->where('id',$logId)->update([
                'status'=>'failed','failed_at'=>now(),'updated_at'=>now(),
                'meta'=>json_encode(['error'=>$e->getMessage(),'account_id'=>$accountId,'period'=>$period], JSON_UNESCAPED_UNICODE),
            ]);

            Log::error('[BILLING_HUB][sendEmail] fallo', [
                'account_id'=>$accountId,'period'=>$period,'to'=>$tos,'email_id'=>$emailId,'e'=>$e->getMessage(),
            ]);

            return back()->withErrors(['mail' => 'Falló el envío: '.$e->getMessage()]);
        }
    }

    public function scheduleEmail(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required','regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'to'         => 'nullable|string|max:2000',
            'queued_at'  => 'nullable|date',
        ]);

        $accountId = (string)$data['account_id'];
        $period    = (string)$data['period'];
        $toRaw     = trim((string)($data['to'] ?? ''));

        $acc = DB::connection($this->adm)->table('accounts')->select('id','email')->where('id',$accountId)->first();
        if (!$acc) return back()->withErrors(['account_id' => 'Cuenta no encontrada.']);

        $tos = $this->parseToList($toRaw);
        if (empty($tos)) $tos = $this->parseToList((string)($acc->email ?? ''));

        if (empty($tos)) return back()->withErrors(['to' => 'No hay correos destino.']);

        $queuedAt = !empty($data['queued_at']) ? Carbon::parse((string)$data['queued_at']) : now();

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
            'meta'       => json_encode(['source'=>'admin_schedule','account_id'=>$accountId,'period'=>$period], JSON_UNESCAPED_UNICODE),
            'queued_at'  => $queuedAt,
        ]);

        return back()->with('ok', 'Programado. log_id='.$logId.' email_id='.$emailId.' queued_at='.$queuedAt->toDateTimeString());
    }

    public function resendEmail(Request $req, int $id): RedirectResponse
    {
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            return back()->withErrors(['email' => 'No existe billing_email_logs.']);
        }

        $row = DB::connection($this->adm)->table('billing_email_logs')->where('id',$id)->first();
        if (!$row) return back()->withErrors(['email' => 'Log no encontrado.']);

        $accountId = (string)($row->account_id ?? '');
        $period    = (string)($row->period ?? '');

        if ($accountId === '' || $period === '') {
            return back()->withErrors(['email' => 'El log no tiene account_id/period.']);
        }

        $acc = DB::connection($this->adm)->table('accounts')->where('id',$accountId)->first();
        if (!$acc) return back()->withErrors(['email' => 'Cuenta no encontrada.']);

        $tos = $this->parseToList((string)($row->to_list ?? $row->email ?? ''));
        if (empty($tos)) $tos = $this->parseToList((string)($acc->email ?? ''));

        if (empty($tos)) return back()->withErrors(['email' => 'Sin destinatarios.']);

        $emailId = (string) Str::ulid();

        try {
            $payload = $this->buildStatementEmailPayloadPublic($acc, $accountId, $period, $emailId);

            Mail::to($tos)->send(new StatementAccountPeriodMail($accountId, $period, $payload));

            DB::connection($this->adm)->table('billing_email_logs')->where('id',$id)->update([
                'email_id' => $emailId,
                'status'   => 'sent',
                'sent_at'  => now(),
                'failed_at'=> null,
                'subject'  => (string)($payload['subject'] ?? null),
                'payload'  => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'updated_at'=> now(),
            ]);

            return back()->with('ok', 'Reenvío OK. Nuevo email_id='.$emailId);
        } catch (\Throwable $e) {
            DB::connection($this->adm)->table('billing_email_logs')->where('id',$id)->update([
                'status'=>'failed','failed_at'=>now(),'updated_at'=>now(),
                'meta'=>json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE),
            ]);

            return back()->withErrors(['email' => 'Reenvío falló: '.$e->getMessage()]);
        }
    }

    public function previewEmail(Request $req): Response
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required','regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
        ]);

        $acc = DB::connection($this->adm)->table('accounts')->where('id',(string)$data['account_id'])->first();
        if (!$acc) abort(404);

        $emailId = (string) Str::ulid();
        $payload = $this->buildStatementEmailPayloadPublic($acc, (string)$data['account_id'], (string)$data['period'], $emailId);

        $html = view('emails.admin.billing.statement_account_period', $payload)->render();
        return response($html, 200, ['Content-Type'=>'text/html; charset=UTF-8']);
    }

    // =========================================================
    // LIGA DIRECTA DE PAGO (Stripe Checkout) + registro pending
    // =========================================================

    public function createPayLink(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => ['required','regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
        ]);

        $accountId = (string)$data['account_id'];
        $period    = (string)$data['period'];

        $acc = DB::connection($this->adm)->table('accounts')->where('id',$accountId)->first();
        if (!$acc) return back()->withErrors(['pay' => 'Cuenta no encontrada.']);

        // total del periodo (si no hay cargo, usa esperado)
        $items = DB::connection($this->adm)->table('estados_cuenta')
            ->where('account_id',$accountId)->where('periodo',$period)->get();

        $cargoReal = (float)$items->sum('cargo');
        $abonoEc   = (float)$items->sum('abono');

        // ✅ complemento por payments pagados
        $abonoPay  = (float)$this->sumPaymentsPaidForAccountPeriod($accountId, $period);
        $abono     = ($abonoEc > 0.00001) ? $abonoEc : $abonoPay;

        $meta = $this->decodeMeta($acc->meta ?? null);
        $custom = $this->extractCustomAmountMxn($acc, $meta);

        if ($custom !== null && $custom > 0.00001) $expected = $custom;
        else [$expected] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);

        $totalShown = $cargoReal > 0 ? $cargoReal : (float)$expected;
        $saldo = max(0, $totalShown - $abono);

        if ($saldo <= 0.00001) {
            return back()->with('ok', 'No hay saldo pendiente para ese periodo.');
        }

        try {
            [$url, $sessionId] = $this->createStripeCheckoutForStatement($acc, $period, $saldo);
            return back()->with('ok', 'Liga de pago (Stripe): '.$url.' (session='.$sessionId.')');
        } catch (\Throwable $e) {
            return back()->withErrors(['pay' => 'No se pudo generar liga: '.$e->getMessage()]);
        }
    }

    private function createStripeCheckoutForStatement(object $acc, string $period, float $totalPesos): array
    {
        $secret = (string) config('services.stripe.secret');
        if (trim($secret) === '') throw new \RuntimeException('Stripe secret vacío.');

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

        $this->upsertPendingPaymentForStatement((string)($acc->id ?? ''), $period, $unitAmountCents, $sessionId, $totalPesos);

        return [$sessionUrl, $sessionId];
    }

    private function upsertPendingPaymentForStatement(string $accountId, string $period, int $amountCents, string $sessionId, float $uiTotalPesos): void
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) return;

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

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
        if ($has('amount'))     $row['amount']     = $amountCents;
        if ($has('currency'))   $row['currency']   = 'MXN';
        if ($has('status'))     $row['status']     = 'pending';
        if ($has('due_date'))   $row['due_date']   = now();

        if ($has('period'))     $row['period']     = $period;
        if ($has('method'))     $row['method']     = 'card';
        if ($has('provider'))   $row['provider']   = 'stripe';
        if ($has('concept'))    $row['concept']    = 'Pactopia360 · Estado de cuenta ' . $period;
        if ($has('reference'))  $row['reference']  = $sessionId ?: ('hub_stmt:' . $accountId . ':' . $period);

        if ($has('stripe_session_id')) $row['stripe_session_id'] = $sessionId;

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
            DB::connection($this->adm)->table('payments')->where('id', (int)$existing->id)->update($row);
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
            'period'     => ['required','regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
            'notes'      => 'nullable|string|max:5000',
        ]);

        if (!Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            return back()->withErrors(['invoice' => 'No existe la tabla billing_invoice_requests.']);
        }

        $accountId = (string)$data['account_id'];
        $period    = (string)$data['period'];
        $notes     = trim((string)($data['notes'] ?? ''));

        $now = now();

        $row = DB::connection($this->adm)->table('billing_invoice_requests')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        if ($row) {
            DB::connection($this->adm)->table('billing_invoice_requests')->where('id',(int)$row->id)->update([
                'notes' => $notes !== '' ? $notes : ($row->notes ?? null),
                'status'=> $row->status ?: 'requested',
                'updated_at'=>$now,
            ]);

            return back()->with('ok', 'Solicitud de factura actualizada (#'.(int)$row->id.')');
        }

        $id = (int)DB::connection($this->adm)->table('billing_invoice_requests')->insertGetId([
            'account_id'=>$accountId,'period'=>$period,'status'=>'requested','notes'=>($notes!==''?$notes:null),
            'created_at'=>$now,'updated_at'=>$now,
        ]);

        return back()->with('ok', 'Solicitud de factura creada (#'.$id.')');
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

        $id = (int)$data['id'];

        $ok = DB::connection($this->adm)->table('billing_invoice_requests')->where('id',$id)->update([
            'status'=>trim((string)$data['status']),
            'cfdi_uuid'=>($data['cfdi_uuid'] ?? null) ?: null,
            'notes'=>($data['notes'] ?? null) ?: null,
            'updated_at'=>now(),
        ]);

        if (!$ok) return back()->withErrors(['invoice' => 'No se pudo actualizar. ID no encontrado.']);

        return back()->with('ok', 'Estatus solicitud actualizado (#'.$id.').');
    }

    public function saveInvoice(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id'  => 'required|string|max:64',
            'period'      => ['required','regex:/^\d{4}\-(0[1-9]|1[0-2])$/'],
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

        $accountId = (string)$data['account_id'];
        $period    = (string)$data['period'];

        $amountCents = (int)round(((float)($data['amount_mxn'] ?? 0)) * 100);

        $row = DB::connection($this->adm)->table('billing_invoices')->where('account_id',$accountId)->where('period',$period)->first();
        $now = now();

        $payload = [
            'serie' => $data['serie'] ?? null,
            'folio' => $data['folio'] ?? null,
            'cfdi_uuid' => $data['cfdi_uuid'] ?? null,
            'issued_date' => !empty($data['issued_date']) ? Carbon::parse((string)$data['issued_date'])->toDateString() : null,
            'amount_cents' => $amountCents,
            'currency' => 'MXN',
            'status' => 'issued',
            'notes'  => ($data['notes'] ?? null) ?: null,
            'updated_at' => $now,
        ];

        if ($row) {
            DB::connection($this->adm)->table('billing_invoices')->where('id',(int)$row->id)->update($payload);

            if (Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
                DB::connection($this->adm)->table('billing_invoice_requests')
                    ->where('account_id',$accountId)->where('period',$period)
                    ->update(['status'=>'issued','cfdi_uuid'=>$payload['cfdi_uuid'],'updated_at'=>$now]);
            }

            return back()->with('ok', 'Factura actualizada (account/period).');
        }

        $payload['account_id'] = $accountId;
        $payload['period'] = $period;
        $payload['created_at'] = $now;

        DB::connection($this->adm)->table('billing_invoices')->insert($payload);

        if (Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            DB::connection($this->adm)->table('billing_invoice_requests')
                ->where('account_id',$accountId)->where('period',$period)
                ->update(['status'=>'issued','cfdi_uuid'=>$payload['cfdi_uuid'],'updated_at'=>$now]);
        }

        return back()->with('ok', 'Factura registrada.');
    }

    // =========================================================
    // Tracking
    // =========================================================

    public function trackOpen(string $emailId): Response
    {
        if (Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            $row = DB::connection($this->adm)->table('billing_email_logs')
                ->where('email_id', $emailId)
                ->first(['id','open_count','first_open_at','last_open_at']);

            if ($row) {
                $now = now();
                DB::connection($this->adm)->table('billing_email_logs')->where('id',(int)$row->id)->update([
                    'open_count'    => (int)($row->open_count ?? 0) + 1,
                    'first_open_at' => $row->first_open_at ?: $now,
                    'last_open_at'  => $now,
                    'updated_at'    => $now,
                ]);
            }
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
        $u = (string)$req->query('u', '');
        $target = $u !== '' ? urldecode($u) : url('/');

        if (Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            $row = DB::connection($this->adm)->table('billing_email_logs')
                ->where('email_id', $emailId)
                ->first(['id','click_count','first_click_at','last_click_at']);

            if ($row) {
                $now = now();
                DB::connection($this->adm)->table('billing_email_logs')->where('id',(int)$row->id)->update([
                    'click_count'    => (int)($row->click_count ?? 0) + 1,
                    'first_click_at' => $row->first_click_at ?: $now,
                    'last_click_at'  => $now,
                    'updated_at'     => $now,
                ]);
            }
        }

        return redirect()->away($target);
    }

    // =========================================================
    // PUBLIC: payload builder usado por comando
    // =========================================================
    public function buildStatementEmailPayloadPublic(object $acc, string $accountId, string $period, string $emailId): array
    {
        $items = DB::connection($this->adm)->table('estados_cuenta')
            ->where('account_id', $accountId)
            ->where('periodo', '=', $period)
            ->orderBy('id')
            ->get();

        $cargoReal = (float) $items->sum('cargo');
        $abonoEc   = (float) $items->sum('abono');

        // ✅ complemento por payments pagados (cuando EC no refleja el pago todavía)
        $abonoPay  = (float) $this->sumPaymentsPaidForAccountPeriod($accountId, $period);
        $abono     = ($abonoEc > 0.00001) ? $abonoEc : $abonoPay;

        $meta = $this->decodeMeta($acc->meta ?? null);

        $custom = $this->extractCustomAmountMxn($acc, $meta);

        if ($custom !== null && $custom > 0.00001) {
            $expectedTotal = $custom;
            $tarifaLabel = 'PERSONALIZADO';
            $tarifaPill = 'pill-info';
        } else {
            [$expectedTotal, $tarifaLabel, $tarifaPillRaw] = $this->resolveEffectiveAmountForPeriodFromMeta($meta, $period, null);
            $tarifaPill = $this->mapTarifaPillToCss($tarifaPillRaw);
        }

        $totalShown = $cargoReal > 0 ? $cargoReal : (float)$expectedTotal;
        $saldo = max(0, $totalShown - $abono);

        $pdfUrl = Route::has('cliente.billing.publicPdfInline')
            ? route('cliente.billing.publicPdfInline', ['accountId'=>$accountId, 'period'=>$period])
            : '';

        $portalUrl = Route::has('cliente.estado_cuenta')
            ? route('cliente.estado_cuenta') . '?period=' . urlencode($period)
            : url('/cliente/estado-de-cuenta?period='.urlencode($period));

        $payUrl = '';
        $stripeSessionId = '';
        if ($saldo > 0.00001) {
            try {
                [$payUrl, $stripeSessionId] = $this->createStripeCheckoutForStatement($acc, $period, $saldo);
            } catch (\Throwable $e) {
                Log::warning('[HUB][EMAIL] no pudo crear checkout', ['e'=>$e->getMessage()]);
            }
        }

        $openPixelUrl = route('track.billing.open', ['emailId' => $emailId]);

        $wrapClick = function(string $url) use ($emailId): string {
            if ($url === '') return '';
            return route('track.billing.click', ['emailId' => $emailId]) . '?u=' . urlencode($url);
        };

        return [
            'account'         => $acc,
            'account_id'      => $accountId,
            'period'          => $period,
            'period_label'    => Str::title(Carbon::parse($period.'-01')->translatedFormat('F Y')),
            'items'           => $items,

            'cargo_real'      => round($cargoReal,2),
            'expected_total'  => round((float)$expectedTotal,2),
            'tarifa_label'    => (string)$tarifaLabel,
            'tarifa_pill'     => (string)$tarifaPill,

            'cargo'           => round($totalShown,2),
            'abono'           => round($abono,2),
            'total'           => round($saldo,2),

            'pdf_url'         => $pdfUrl,
            'pay_url'         => $payUrl,
            'stripe_session_id'=> $stripeSessionId,
            'portal_url'      => $portalUrl,

            'open_pixel_url'  => $openPixelUrl,
            'pdf_track_url'   => $wrapClick($pdfUrl),
            'pay_track_url'   => $wrapClick($payUrl),
            'portal_track_url'=> $wrapClick($portalUrl),

            'email_id'        => $emailId,
            'generated_at'    => now(),
            'subject'         => 'Pactopia360 · Estado de cuenta '.$period,
        ];
    }

    // =========================================================
    // ✅ MÉTODO: usado por el Cliente para resolver precio
    // =========================================================
    /**
     * Resuelve la mensualidad EN CENTAVOS desde meta.billing (admin.accounts.meta),
     * aplicando override_amount_mxn con effective now/next.
     *
     * - Si no hay override aplicable, regresa 0 para permitir fallback.
     */
    public function resolveMonthlyCentsForPeriodFromAdminAccount(int $adminAccountId, string $period, string $payAllowed): int
    {
        if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) return 0;

        try {
            if (!Schema::connection($this->adm)->hasTable('accounts')) return 0;

            $cols = Schema::connection($this->adm)->getColumnListing('accounts');
            $lc   = array_map('strtolower', $cols);
            $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

            if (!$has('meta')) return 0;

            $acc = DB::connection($this->adm)->table('accounts')
                ->select(['id', 'meta'])
                ->where('id', $adminAccountId)
                ->first();

            if (!$acc) return 0;

            $meta = $this->decodeMeta($acc->meta ?? null);
            $billing = (array)($meta['billing'] ?? []);

            $overrideMxn = $billing['override_amount_mxn']
                ?? data_get($billing, 'override.amount_mxn')
                ?? null;

            if (!is_numeric($overrideMxn) || (float)$overrideMxn <= 0) return 0;

            $eff = strtolower(trim((string)(
                $billing['override_effective']
                ?? data_get($billing, 'override.effective')
                ?? ''
            )));

            if (!in_array($eff, ['now','next'], true)) $eff = 'now';

            $apply = false;

            if ($eff === 'now') {
                $apply = true;
            } else {
                if (preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $payAllowed)) {
                    $apply = ($period >= $payAllowed);
                } else {
                    $apply = false;
                }
            }

            if (!$apply) return 0;

            $mxn = (float)$overrideMxn;
            $cents = (int) round($mxn * 100);

            return $cents > 0 ? $cents : 0;

        } catch (\Throwable $e) {
            Log::warning('[HUB] resolveMonthlyCentsForPeriodFromAdminAccount failed', [
                'account_id' => $adminAccountId,
                'period'     => $period,
                'payAllowed' => $payAllowed,
                'err'        => $e->getMessage(),
            ]);
            return 0;
        }
    }

    // =========================================================
    // Payments helpers (para evitar "pagado en ceros")
    // =========================================================

    /**
     * Suma pagos pagados/confirmados para un account+period y regresa MXN.
     */
    private function sumPaymentsPaidForAccountPeriod(string $accountId, string $period): float
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) return 0.0;

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) return 0.0;

        $q = DB::connection($this->adm)->table('payments')->where('account_id',$accountId)->where('period',$period);

        // status pagado (variantes)
        if ($has('status')) {
            $q->whereIn('status', ['paid','succeeded','success','completed','complete']);
        }

        // provider stripe (si existe)
        if ($has('provider')) {
            $q->where(function($w){
                $w->whereNull('provider')->orWhere('provider','')->orWhere('provider','stripe');
            });
        }

        // monto: preferimos amount (centavos), si no, buscamos amount_mxn
        $sumMxn = 0.0;

        if ($has('amount')) {
            $sumCents = (float) $q->sum('amount'); // centavos
            $sumMxn = $sumCents / 100.0;
        } elseif ($has('amount_mxn')) {
            $sumMxn = (float) $q->sum('amount_mxn');
        } elseif ($has('monto_mxn')) {
            $sumMxn = (float) $q->sum('monto_mxn');
        }

        return round(max(0.0, $sumMxn), 2);
    }

    /**
     * Suma pagos pagados por account_id para un periodo, en lote.
     * Retorna array account_id => mxn.
     *
     * @param array<int,string|int> $accountIds
     * @return array<string,float>
     */
    private function sumPaymentsPaidByAccountForPeriod(array $accountIds, string $period): array
    {
        $out = [];
        if (empty($accountIds)) return $out;
        if (!Schema::connection($this->adm)->hasTable('payments')) return $out;

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) return $out;

        $q = DB::connection($this->adm)->table('payments')
            ->whereIn('account_id', $accountIds)
            ->where('period', $period);

        if ($has('status')) {
            $q->whereIn('status', ['paid','succeeded','success','completed','complete']);
        }

        if ($has('provider')) {
            $q->where(function($w){
                $w->whereNull('provider')->orWhere('provider','')->orWhere('provider','stripe');
            });
        }

        if ($has('amount')) {
            $rows = $q->selectRaw('account_id as aid, SUM(amount) as cents')->groupBy('account_id')->get();
            foreach ($rows as $r) {
                $aid = (string)($r->aid ?? '');
                $mxn = ((float)($r->cents ?? 0)) / 100.0;
                if ($aid !== '') $out[$aid] = round(max(0.0, $mxn), 2);
            }
            return $out;
        }

        // fallback: amount_mxn/monto_mxn
        $colMxn = $has('amount_mxn') ? 'amount_mxn' : ($has('monto_mxn') ? 'monto_mxn' : null);
        if (!$colMxn) return $out;

        $rows = $q->selectRaw('account_id as aid, SUM('.$colMxn.') as mxn')->groupBy('account_id')->get();
        foreach ($rows as $r) {
            $aid = (string)($r->aid ?? '');
            $mxn = (float)($r->mxn ?? 0);
            if ($aid !== '') $out[$aid] = round(max(0.0, $mxn), 2);
        }

        return $out;
    }

    // =========================================================
    // Helpers
    // =========================================================

    public function parseToList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];

        $raw = str_replace([';',"\n","\r","\t"], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, fn($x) => $x !== '');

        $out = [];
        foreach ($parts as $p) {
            if (preg_match('/<([^>]+)>/', $p, $m)) $p = trim((string)$m[1]);
            if (filter_var($p, FILTER_VALIDATE_EMAIL)) $out[] = strtolower($p);
        }

        $out = array_values(array_unique($out));
        return array_slice($out, 0, 10);
    }

    public function decodeMeta($meta): array
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
        if (str_contains($raw, 'próximo') || str_contains($raw, 'proximo') || str_contains($raw, 'next')) return 'pill-warn';
        if ($raw === 'base') return 'pill-dim';
        return 'pill-dim';
    }

    /**
     * Retorna SIEMPRE [mxn,label,pillText]
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

        $eff = strtolower(trim((string)($ov['effective'] ?? ($billing['override_effective'] ?? ''))));
        if (!in_array($eff, ['now','next'], true)) $eff = '';

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

        return [round((float)$effective,2), (string)$label, (string)$pillText];
    }

    private function toFloat(mixed $v): ?float
    {
        if ($v === null) return null;
        if (is_float($v) || is_int($v)) return (float)$v;

        if (is_string($v)) {
            $s = trim($v);
            if ($s === '') return null;
            $s = str_replace(['$',',','MXN','mxn',' '], '', $s);
            if (!is_numeric($s)) return null;
            return (float)$s;
        }

        if (is_numeric($v)) return (float)$v;
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
            'override_amount_mxn','custom_amount_mxn','billing_amount_mxn','amount_mxn','precio_mxn','monto_mxn','license_amount_mxn',
            'billing_amount','amount','precio','monto',
        ] as $prop) {
            if (isset($row->{$prop})) {
                $n = $this->toFloat($row->{$prop});
                if ($n !== null && $n > 0.00001) return $n;
            }
        }

        return null;
    }

    /**
     * Inserta log robusto
     * @param array<string,mixed> $row
     */
    private function insertEmailLog(array $row): int
    {
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            throw new \RuntimeException('No existe billing_email_logs en '.$this->adm);
        }

        $cols = Schema::connection($this->adm)->getColumnListing('billing_email_logs');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        $ins = [];
        if ($has('email_id'))     $ins['email_id']     = (string)($row['email_id'] ?? Str::ulid());
        if ($has('account_id'))   $ins['account_id']   = (string)($row['account_id'] ?? '');
        if ($has('period'))       $ins['period']       = (string)($row['period'] ?? '');

        if ($has('email'))        $ins['email']        = $row['email'] ?? null;
        if ($has('to_list'))      $ins['to_list']      = $row['to_list'] ?? null;

        if ($has('template'))     $ins['template']     = (string)($row['template'] ?? 'statement');
        if ($has('status'))       $ins['status']       = (string)($row['status'] ?? 'queued');
        if ($has('provider'))     $ins['provider']     = $row['provider'] ?? null;
        if ($has('provider_message_id')) $ins['provider_message_id'] = $row['provider_message_id'] ?? null;
        if ($has('subject'))      $ins['subject']      = $row['subject'] ?? null;
        if ($has('payload'))      $ins['payload']      = $row['payload'] ?? null;
        if ($has('meta'))         $ins['meta']         = $row['meta'] ?? null;

        if ($has('queued_at'))    $ins['queued_at']    = $row['queued_at'] ?? now();
        if ($has('sent_at'))      $ins['sent_at']      = $row['sent_at'] ?? null;
        if ($has('failed_at'))    $ins['failed_at']    = $row['failed_at'] ?? null;

        if ($has('open_count'))   $ins['open_count']   = (int)($row['open_count'] ?? 0);
        if ($has('click_count'))  $ins['click_count']  = (int)($row['click_count'] ?? 0);

        $ins['created_at'] = now();
        $ins['updated_at'] = now();

        return (int)DB::connection($this->adm)->table('billing_email_logs')->insertGetId($ins);
    }
}
