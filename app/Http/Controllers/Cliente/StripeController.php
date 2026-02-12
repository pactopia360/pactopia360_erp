<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use App\Services\Admin\Billing\AccountBillingStateService;

class StripeController extends Controller
{
    private StripeClient $stripe;

    public function __construct()
    {
        $secret = (string) config('services.stripe.secret');
        if (!trim($secret)) {
            Log::warning('Stripe secret ausente en config(services.stripe.secret)');
        }
        $this->stripe = new StripeClient($secret ?: '');
    }

    /* =========================================================
     |  PRO (suscripción)
     * ========================================================= */

    public function checkoutMonthly(Request $request)
    {
        return $this->createCheckoutPro($request, 'mensual');
    }

    public function checkoutAnnual(Request $request)
    {
        return $this->createCheckoutPro($request, 'anual');
    }

    private function createCheckoutPro(Request $request, string $cycle)
    {
        try {
            $validated = $request->validate([
                'account_id' => ['required'],
                'email'      => ['nullable', 'email'],
            ]);

            $accountId = (int) $validated['account_id'];

            $account = DB::connection('mysql_admin')->table('accounts')->where('id', $accountId)->first();
            if (!$account) {
                throw ValidationException::withMessages(['plan' => 'No se encontró la cuenta proporcionada.']);
            }

            // PRO por ciclo (keys estables)
            $key     = ($cycle === 'anual') ? 'pro_anual' : 'pro_mensual';
            $priceId = $this->resolveStripePriceIdOrFailByKey($key);

            $successUrl = route('cliente.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl  = route('cliente.checkout.cancel');

            $customerEmail = $validated['email'] ?? ($account->email ?? null);

            $idempotencyKey = 'checkout:pro:' . $accountId . ':' . $cycle . ':' . Str::uuid();

            $session = $this->stripe->checkout->sessions->create([
                'mode'                  => 'subscription',
                'payment_method_types'  => ['card'],
                'allow_promotion_codes' => true,
                'line_items' => [[
                    'price'    => $priceId,
                    'quantity' => 1,
                ]],
                'customer_email'      => $customerEmail,
                'client_reference_id' => (string) $accountId,
                'success_url'         => $successUrl,
                'cancel_url'          => $cancelUrl,
                'metadata'            => [
                    'type'      => 'pro',
                    'account_id' => (string) $accountId,
                    'plan'       => $cycle, // mensual|anual
                ],
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            Log::info('Stripe checkout PRO creada', [
                'account_id' => $accountId,
                'cycle'      => $cycle,
                'price_id'   => $priceId,
                'session'    => $session->id ?? null,
            ]);

            return redirect($session->url);
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            Log::error('Error creando Stripe Checkout PRO', [
                'error'      => $e->getMessage(),
                'cycle'      => $cycle,
                'account_id' => $request->get('account_id'),
            ]);
            return back()->withErrors(['plan' => 'No se pudo iniciar el checkout. Intenta de nuevo.']);
        }
    }

    /* =========================================================
     |  BÓVEDA (suscripción)
     * ========================================================= */

    public function checkoutVault(Request $request)
    {
        try {
            $validated = $request->validate([
                'cuenta_id' => ['required', 'string'],              // cuenta_cliente.id
                'email'     => ['nullable', 'email'],
                'action'    => ['nullable', 'in:activate,upgrade'], // activate|upgrade
                'gb'        => ['nullable', 'integer', 'min:1'],    // sólo para upgrade
                'cycle'     => ['nullable', 'in:mensual,anual'],    // por defecto mensual
            ]);

            $cuentaId = (string) $validated['cuenta_id'];
            $action   = (string) ($validated['action'] ?? 'activate');
            $cycle    = (string) ($validated['cycle'] ?? 'mensual');
            $gb       = (int)    ($validated['gb'] ?? 0);

            $cuenta = DB::connection('mysql_clientes')->table('cuentas_cliente')->where('id', $cuentaId)->first();
            if (!$cuenta) {
                throw ValidationException::withMessages(['vault' => 'No se encontró la cuenta cliente (cuentas_cliente).']);
            }

            $customerEmail = $validated['email'] ?? ($cuenta->email ?? null);

            $priceKey = null;
            if ($action === 'activate') {
                $priceKey = ($cycle === 'anual') ? 'vault_service_anual' : 'vault_service_mensual';
            } else {
                $gbNorm   = $this->normalizeVaultGb($gb > 0 ? $gb : 10);
                $priceKey = 'vault_gb_' . $gbNorm . '_' . $cycle;
            }

            $priceId = $this->resolveStripePriceIdOrFailByKey($priceKey);

            $successUrl = route('cliente.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl  = route('cliente.checkout.cancel');

            $idempotencyKey = 'checkout:vault:' . $cuentaId . ':' . $action . ':' . $cycle . ':' . ($gb ?: 0) . ':' . Str::uuid();

            $session = $this->stripe->checkout->sessions->create([
                'mode'                  => 'subscription',
                'payment_method_types'  => ['card'],
                'allow_promotion_codes' => true,
                'line_items' => [[
                    'price'    => $priceId,
                    'quantity' => 1,
                ]],
                'customer_email'      => $customerEmail ?: null,
                'client_reference_id' => (string) $cuentaId,
                'success_url'         => $successUrl,
                'cancel_url'          => $cancelUrl,
                'metadata' => [
                    'type'      => 'vault',
                    'cuenta_id' => (string) $cuentaId,
                    'action'    => $action,
                    'cycle'     => $cycle,
                    'gb'        => (string) ($gb ?: 0),
                    'price_key' => $priceKey,
                ],
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            Log::info('Stripe checkout VAULT creada', [
                'cuenta_id' => $cuentaId,
                'action'    => $action,
                'cycle'     => $cycle,
                'gb'        => $gb ?: 0,
                'price_key' => $priceKey,
                'price_id'  => $priceId,
                'session'   => $session->id ?? null,
            ]);

            return redirect($session->url);

        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            Log::error('Error creando Stripe Checkout VAULT', [
                'error'   => $e->getMessage(),
                'payload' => $request->all(),
            ]);
            return back()->withErrors(['vault' => 'No se pudo iniciar el checkout de bóveda. Intenta de nuevo.']);
        }
    }

    /* =========================================================
     |  Success / Cancel
     * ========================================================= */

    public function success(Request $request)
    {
        $sessionId = (string) $request->get('session_id', '');

        Log::info('[SUCCESS] hit', [
            'session_id' => $sessionId ?: null,
            'url'        => $request->fullUrl(),
        ]);

        if ($sessionId === '') {
            return redirect()->route('cliente.login')->with('ok', 'Pago confirmado.');
        }

        try {
            // ✅ IMPORTANTE: expand para poder resolver montos en suscripción (line_items, subscription.latest_invoice)
            $session = $this->stripe->checkout->sessions->retrieve($sessionId, [
                'expand' => [
                    'line_items',
                    'subscription',
                    'subscription.latest_invoice',
                ],
            ]);

            $type = (string) ($session->metadata->type ?? 'pro');

            Log::info('[SUCCESS] stripe session', [
                'id'             => $session->id ?? null,
                'type'           => $type,
                'status'         => $session->status ?? null,
                'payment_status' => $session->payment_status ?? null,
                'mode'           => $session->mode ?? null,
            ]);

            // ===== VAULT =====
            if ($type === 'vault') {
                $cuentaId = (string) ($session->metadata->cuenta_id ?? $session->client_reference_id ?? '');
                if ($cuentaId !== '') {
                    $this->syncVaultFromCheckoutSession($cuentaId, $session);
                }
            }
            // ===== BILLING (statement / public / pending_total) =====
            elseif (str_starts_with($type, 'billing_')) {
                $accountId = (int) ($session->metadata->account_id ?? $session->client_reference_id ?? 0);
                $period    = (string) ($session->metadata->period ?? '');

                if ($accountId > 0 && $period !== '') {
                    $isPendingTotal = in_array($type, ['billing_pending_total', 'billing_pending_total_public'], true);
                    $this->syncBillingStatementFromCheckoutSession($accountId, $period, $session, $isPendingTotal);
                }

                // ✅ FIX: tu ruta cliente.billing.statement NO existe. Redirige a Estado de cuenta.
                $url = route('cliente.estado_cuenta');
                if ($period !== '') $url .= '?period=' . urlencode($period);

                return redirect($url)->with('ok', 'Pago confirmado.');
            }
            // ===== PRO =====
            else {
                $accountId = $session->metadata->account_id ?? $session->client_reference_id ?? null;
                $cycle = strtolower((string) ($session->metadata->plan ?? 'mensual'));
                $cycle = ($cycle === 'anual' || $cycle === 'annual') ? 'anual' : 'mensual';

                if ($accountId) {
                    $this->syncAccountFromCheckoutSession((int) $accountId, $session, $cycle);
                }
            }

            return view('cliente.auth.success', [
                'plan'      => ($type === 'vault') ? 'vault' : (string) ($session->metadata->plan ?? 'mensual'),
                'accountId' => $session->metadata->account_id ?? null,
                'sessionId' => $this->isLocal() ? $sessionId : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo recuperar session de Stripe en success', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            return redirect()->route('cliente.login')->with('ok', 'Pago confirmado.');
        }
    }

    public function cancel()
    {
        return redirect()->route('cliente.login')->withErrors([
            'plan' => 'El pago fue cancelado. Puedes intentarlo de nuevo.',
        ]);
    }

    /* =========================================================
     |  Webhook Stripe
     * ========================================================= */

    public function webhook(Request $request)
    {
        $endpointSecret = (string) config('services.stripe.webhook_secret');
        $skipVerify     = (bool) env('STRIPE_WEBHOOK_SKIP_VERIFY', false);

        try {
            $payload   = $request->getContent();
            $signature = $request->header('Stripe-Signature');

            if (!$skipVerify && !trim($endpointSecret)) {
                Log::error('Stripe webhook_secret ausente en config(services.stripe.webhook_secret)');
                return response()->json(['error' => 'Webhook secret missing'], 500);
            }

            if ($skipVerify) {
                $event = json_decode($payload);
                if (!$event || !isset($event->type)) {
                    Log::error('Webhook diagnóstico: payload inválido');
                    return response()->json(['error' => 'Invalid payload'], 400);
                }
            } else {
                $event = Webhook::constructEvent($payload, $signature, $endpointSecret);
            }

            switch ($event->type) {
                case 'checkout.session.completed':
                    $session = $event->data->object;
                    $this->handleCheckoutCompleted($session);
                    break;

                case 'invoice.paid':
                    $invoice = $event->data->object;
                    $this->handleInvoicePaid($invoice);
                    break;

                case 'invoice.payment_failed':
                    $invoice = $event->data->object;
                    $this->handleInvoiceFailed($invoice);
                    break;

                default:
                    Log::info('Stripe webhook ignorado', ['type' => $event->type]);
                    break;
            }

            return response()->json(['status' => 'ok']);
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe Webhook payload inválido', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Stripe Webhook firma inválida', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Throwable $e) {
            Log::error('Stripe Webhook error inesperado', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook error'], 500);
        }
    }

    private function handleCheckoutCompleted($session): void
    {
        try {
            $type = (string) ($session->metadata->type ?? 'pro');

            // Idempotencia simple por session
            $sid = (string) ($session->id ?? '');
            if ($sid !== '') {
                $lockKey = 'p360:webhook:checkout_completed:' . $sid;
                if (Cache::get($lockKey)) {
                    Log::info('checkout.session.completed skip (lock)', ['session_id' => $sid, 'type' => $type]);
                    return;
                }
                Cache::put($lockKey, 1, now()->addMinutes(5));
            }

            // 1) VAULT
            if ($type === 'vault') {
                $cuentaId = (string) ($session->metadata->cuenta_id ?? $session->client_reference_id ?? '');
                if ($cuentaId === '') {
                    Log::warning('checkout.session.completed VAULT sin cuenta_id');
                    return;
                }
                $this->syncVaultFromCheckoutSession($cuentaId, $session);
                Log::info('Stripe webhook vault checkout.session.completed OK', [
                    'cuenta_id' => $cuentaId,
                    'session_id'=> $session->id ?? null,
                ]);
                return;
            }

            // 2) BILLING
            if (str_starts_with($type, 'billing_')) {
                $accountId = (int) ($session->metadata->account_id ?? $session->client_reference_id ?? 0);
                $period    = (string) ($session->metadata->period ?? '');

                if (!$accountId || $period === '') {
                    Log::warning('checkout.session.completed billing_* sin account_id/period', [
                        'type'      => $type,
                        'account_id'=> $accountId,
                        'period'    => $period,
                        'session_id'=> $session->id ?? null,
                    ]);
                    return;
                }

                $isPendingTotal = in_array($type, ['billing_pending_total', 'billing_pending_total_public'], true);

                $this->syncBillingStatementFromCheckoutSession($accountId, $period, $session, $isPendingTotal);

                Log::info('Stripe webhook billing_* checkout.session.completed OK', [
                    'type'      => $type,
                    'account_id'=> $accountId,
                    'period'    => $period,
                    'session_id'=> $session->id ?? null,
                ]);
                return;
            }

            // 3) PRO
            $accountId = $session->metadata->account_id ?? $session->client_reference_id ?? null;
            $cycle     = strtolower((string) ($session->metadata->plan ?? 'mensual'));
            $cycle     = ($cycle === 'anual' || $cycle === 'annual') ? 'anual' : 'mensual';

            if (!$accountId) {
                Log::warning('checkout.session.completed PRO sin account_id');
                return;
            }

            $this->syncAccountFromCheckoutSession((int) $accountId, $session, $cycle);

            Log::info('Stripe webhook PRO checkout.session.completed OK', [
                'account_id' => (int) $accountId,
                'cycle'      => $cycle,
                'session_id' => $session->id ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error procesando checkout.session.completed', [
                'error'   => $e->getMessage(),
                'session' => $session->id ?? null,
            ]);
        }
    }

    /* =========================================================
     |  VAULT SYNC (idempotente)
     * ========================================================= */

    private function syncVaultFromCheckoutSession(string $cuentaId, $session): void
    {
        $paymentStatus = strtolower((string) ($session->payment_status ?? ''));
        if ($paymentStatus !== 'paid') {
            Log::warning('[VAULT:SYNC] session no pagada, no aplica', [
                'cuenta_id' => $cuentaId,
                'session_id'=> $session->id ?? null,
                'payment_status' => $paymentStatus ?: null,
            ]);
            return;
        }

        $action   = (string) ($session->metadata->action ?? 'activate');
        $cycle    = (string) ($session->metadata->cycle ?? 'mensual');
        $gbRaw    = (int) ($session->metadata->gb ?? 0);
        $gbNorm   = $this->normalizeVaultGb($gbRaw > 0 ? $gbRaw : 10);

        $customer     = $session->customer ?? null;
        $subscription = $session->subscription ?? null;
        $sessionId    = $session->id ?? null;

        DB::connection('mysql_clientes')->beginTransaction();

        try {
            if (!Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                DB::connection('mysql_clientes')->commit();
                return;
            }

            $schema = Schema::connection('mysql_clientes');
            $cols   = $schema->getColumnListing('cuentas_cliente');
            $lc     = array_map('strtolower', $cols);
            $has    = fn(string $c) => in_array(strtolower($c), $lc, true);

            $upd = [];

            if ($has('vault_activated_at')) {
                $current = DB::connection('mysql_clientes')->table('cuentas_cliente')->where('id', $cuentaId)->value('vault_activated_at');
                if (empty($current)) {
                    $upd['vault_activated_at'] = now();
                }
            }

            if ($has('vault_quota_gb')) {
                $currentGb = (float) DB::connection('mysql_clientes')->table('cuentas_cliente')->where('id', $cuentaId)->value('vault_quota_gb');
                $currentGb = is_numeric($currentGb) ? (float) $currentGb : 0.0;

                if ($action === 'upgrade') {
                    $upd['vault_quota_gb'] = $currentGb + $gbNorm;
                } else {
                    if ($gbRaw > 0) {
                        $upd['vault_quota_gb'] = max($currentGb, (float) $gbNorm);
                    }
                }
            }

            if ($has('vault_quota_bytes') && isset($upd['vault_quota_gb'])) {
                $upd['vault_quota_bytes'] = (int) round(((float) $upd['vault_quota_gb']) * 1024 * 1024 * 1024);
            }

            if ($has('vault_meta')) {
                $metaCur = DB::connection('mysql_clientes')->table('cuentas_cliente')->where('id', $cuentaId)->value('vault_meta');
                $arr = [];
                if (is_string($metaCur) && $metaCur !== '') {
                    $d = json_decode($metaCur, true);
                    if (is_array($d)) $arr = $d;
                }

                $arr = array_replace_recursive($arr, [
                    'stripe' => [
                        'customer_id'     => $customer,
                        'subscription_id' => $subscription,
                        'last_session_id' => $sessionId,
                        'last_paid_at'    => now()->toISOString(),
                        'cycle'           => $cycle,
                        'action'          => $action,
                        'gb'              => $gbNorm,
                    ],
                ]);

                $upd['vault_meta'] = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if ($has('updated_at')) {
                $upd['updated_at'] = now();
            }

            if (!empty($upd)) {
                DB::connection('mysql_clientes')->table('cuentas_cliente')
                    ->where('id', $cuentaId)
                    ->update($upd);
            }

            DB::connection('mysql_clientes')->commit();

            Log::info('[VAULT:SYNC] OK', [
                'cuenta_id' => $cuentaId,
                'action'    => $action,
                'gb'        => $gbNorm,
                'session_id'=> $sessionId,
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql_clientes')->rollBack();

            Log::error('[VAULT:SYNC] error', [
                'cuenta_id' => $cuentaId,
                'error'     => $e->getMessage(),
                'session_id'=> $sessionId,
            ]);

            throw $e;
        }
    }

    /**
     * =========================================================
     * Resolver monto pagado REAL (para subscription checkout)
     * =========================================================
     * Retorna [amountCents, amountMxn, source]
     */
    private function resolvePaidAmountFromCheckoutSession($session, int $accountId = 0, string $period = ''): array
    {
        $sessionId = (string)($session->id ?? '');

        // 1) session.amount_total (cuando existe)
        if (property_exists($session, 'amount_total') && is_numeric($session->amount_total) && (int)$session->amount_total > 0) {
            $c = (int)$session->amount_total;
            return [$c, round($c / 100, 2), 'session.amount_total'];
        }

        // 2) line_items sum (si viene expandido)
        try {
            $sum = 0;
            if (isset($session->line_items) && isset($session->line_items->data) && is_array($session->line_items->data)) {
                foreach ($session->line_items->data as $li) {
                    $amt = $li->amount_total ?? null;
                    if (is_numeric($amt)) $sum += (int)$amt;
                }
            }
            if ($sum > 0) {
                return [$sum, round($sum / 100, 2), 'session.line_items.sum(amount_total)'];
            }
        } catch (\Throwable $e) {}

        // 3) subscription.latest_invoice (suscripción)
        try {
            $subId = $session->subscription ?? null;

            // expandido como objeto
            if (is_object($subId) && isset($subId->latest_invoice)) {
                $inv = $subId->latest_invoice;
                $paid = $inv->amount_paid ?? null;
                $total = $inv->total ?? null;
                $use = is_numeric($paid) && (int)$paid > 0 ? (int)$paid : (is_numeric($total) ? (int)$total : 0);
                if ($use > 0) return [$use, round($use / 100, 2), 'subscription.latest_invoice.amount_paid/total'];
            }

            // viene como string, consultamos
            if (is_string($subId) && $subId !== '') {
                $sub = $this->stripe->subscriptions->retrieve($subId, ['expand' => ['latest_invoice']]);
                if ($sub && isset($sub->latest_invoice)) {
                    $inv = $sub->latest_invoice;
                    $paid = $inv->amount_paid ?? null;
                    $total = $inv->total ?? null;
                    $use = is_numeric($paid) && (int)$paid > 0 ? (int)$paid : (is_numeric($total) ? (int)$total : 0);
                    if ($use > 0) return [$use, round($use / 100, 2), 'stripe.subscriptions.retrieve.latest_invoice.amount_paid/total'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING:SYNC] resolve amount via subscription.latest_invoice failed', [
                'session_id' => $sessionId ?: null,
                'err' => $e->getMessage(),
            ]);
        }

        // 4) ✅ FIX FINAL: si no resolvió Stripe, usar meta.billing (tu HUB ya sabe el precio real)
        if ($accountId > 0 && $period !== '') {
            [$c, $m, $src] = $this->resolveAmountFromAdminMetaBilling($accountId, $period);
            if ($c > 0) return [$c, $m, $src];
        }

        return [0, 0.0, 'none'];
    }

    /**
     * Sync de pago por periodo (Estado de cuenta)
     * - Marca payment pending -> paid (match robusto)
     * - Inserta ABONO idempotente en mysql_admin.estados_cuenta
     * - ✅ Actualiza accounts.meta.stripe.last_paid_at / last_paid_period (lo que tu UI usa)
     * - ✅ Actualiza mysql_clientes.estados_cuenta para cerrar saldo del periodo
     */
    private function syncBillingStatementFromCheckoutSession(int $accountId, string $period, $session, bool $isPendingTotal = false): void
    {
        $paymentStatus = strtolower((string)($session->payment_status ?? ''));

        Log::info('[BILLING:SYNC] start', [
            'account_id'     => $accountId,
            'period'         => $period,
            'session_id'     => $session->id ?? null,
            'payment_status' => $paymentStatus ?: null,
            'pending_total'  => $isPendingTotal,
        ]);

        if ($paymentStatus !== 'paid') {
            Log::warning('[BILLING:SYNC] session no pagada, no aplica', [
                'account_id' => $accountId,
                'period'     => $period,
                'session_id' => $session->id ?? null,
                'payment_status' => $paymentStatus ?: null,
            ]);
            return;
        }

        if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
            Log::warning('[BILLING:SYNC] periodo inválido', [
                'account_id' => $accountId,
                'period'     => $period,
            ]);
            return;
        }

        $adm = 'mysql_admin';
        $cli = 'mysql_clientes';

        if (!Schema::connection($adm)->hasTable('payments')) {
            Log::error('[BILLING:SYNC] abort: no existe tabla payments en mysql_admin');
            return;
        }

        $sessionId = (string)($session->id ?? '');
        if ($sessionId === '') {
            Log::error('[BILLING:SYNC] abort: session_id vacío');
            return;
        }

        // ✅ Monto cobrado REAL en Stripe (robusto: amount_total, line_items, latest_invoice, fallback meta)
        [$stripeCents, $stripeMxn, $amountSource] = $this->resolvePaidAmountFromCheckoutSession($session, $accountId, $period);

        Log::info('[BILLING:SYNC] computed', [
            'account_id'     => $accountId,
            'period'         => $period,
            'session_id'     => $sessionId,
            'stripe_cents'   => $stripeCents ?: null,
            'stripe_mxn'     => $stripeMxn,
            'amount_source'  => $amountSource,
            'mode'           => $session->mode ?? null,
            'pending_total'  => $isPendingTotal,
        ]);

        // ✅ Bloqueo duro si no pudimos resolver monto (evita “pagado $0.00”)
        if ((int)$stripeCents <= 0 || (float)$stripeMxn <= 0.0) {
            Log::error('[BILLING:SYNC] abort: stripe amount is 0 (cannot apply)', [
                'account_id'     => $accountId,
                'period'         => $period,
                'session_id'     => $sessionId,
                'amount_source'  => $amountSource,
            ]);
            return;
        }

        // =========================================================
        // ✅ STRIPE MINIMUM: separar "cobrado" vs "aplicado al periodo"
        // - Stripe cobrado real: $stripeCents/$stripeMxn
        // - Sistema/UI: metadata.amount_cents/amount_mxn (lo que debe cerrar el periodo)
        // - Aplicado al periodo: min(stripe, ui)
        // - Crédito excedente: stripe - ui (se registra en meta, NO se aplica al periodo)
        // =========================================================
        $uiCents = 0;
        $uiMxn   = 0.0;

        try {
            $mCents = $session->metadata->amount_cents ?? null;
            if (is_numeric($mCents) && (int)$mCents > 0) {
                $uiCents = (int)$mCents;
                $uiMxn   = round($uiCents / 100, 2);
            } else {
                $mMxn = $session->metadata->amount_mxn ?? null;
                if (is_numeric($mMxn) && (float)$mMxn > 0) {
                    $uiMxn   = round((float)$mMxn, 2);
                    $uiCents = (int) round($uiMxn * 100);
                }
            }
        } catch (\Throwable $e) {
            // ignora
        }

        // Compat: si no vino UI, asumimos que lo cobrado es lo aplicable
        if ($uiCents <= 0) {
            $uiCents = (int)$stripeCents;
            $uiMxn   = round($uiCents / 100, 2);
        }

        $applyCents  = (int) max(0, min((int)$stripeCents, (int)$uiCents));
        $applyMxn    = round($applyCents / 100, 2);
        $creditCents = (int) max(0, (int)$stripeCents - (int)$uiCents);
        $creditMxn   = round($creditCents / 100, 2);

        Log::info('[BILLING:SYNC] minimum split', [
            'account_id'     => $accountId,
            'period'         => $period,
            'session_id'     => $sessionId,
            'stripe_cents'   => (int)$stripeCents,
            'stripe_mxn'     => (float)$stripeMxn,
            'ui_cents'       => (int)$uiCents,
            'ui_mxn'         => (float)$uiMxn,
            'apply_cents'    => (int)$applyCents,
            'apply_mxn'      => (float)$applyMxn,
            'credit_cents'   => (int)$creditCents,
            'credit_mxn'     => (float)$creditMxn,
            'pending_total'  => $isPendingTotal,
        ]);

        // ✅ Si por alguna razón UI->apply queda 0, no cierres el periodo
        if ($applyCents <= 0 || $applyMxn <= 0.0) {
            Log::error('[BILLING:SYNC] abort: apply amount is 0 (cannot close period)', [
                'account_id'   => $accountId,
                'period'       => $period,
                'session_id'   => $sessionId,
                'stripe_cents' => $stripeCents,
                'ui_cents'     => $uiCents,
            ]);
            return;
        }

        DB::connection($adm)->beginTransaction();
        DB::connection($cli)->beginTransaction();

        try {
            // =========================================================
            // (A) Actualizar payments (admin)  ✅ aquí va COBRO REAL (Stripe)
            // =========================================================
            $cols = Schema::connection($adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = fn($c) => in_array(strtolower($c), $lc, true);

            $baseUpd = [];
            if ($has('status'))     $baseUpd['status'] = 'paid';
            if ($has('paid_at'))    $baseUpd['paid_at'] = now();
            if ($has('updated_at')) $baseUpd['updated_at'] = now();
            if ($has('provider'))   $baseUpd['provider'] = 'stripe';
            if ($has('method'))     $baseUpd['method'] = 'card';

            if ($has('amount')) {
                $baseUpd['amount'] = (int)$stripeCents; // ✅ COBRADO REAL
            }

            if ($has('stripe_payment_intent') && !empty($session->payment_intent)) {
                $baseUpd['stripe_payment_intent'] = (string)$session->payment_intent;
            }

            $updatedRows = 0;

            // 1) Match preferente: stripe_session_id
            if ($has('stripe_session_id')) {
                $q = DB::connection($adm)->table('payments')
                    ->where('account_id', $accountId)
                    ->where('stripe_session_id', $sessionId);

                if (!$isPendingTotal && $has('period')) {
                    $q->where('period', $period);
                }

                $updatedRows = (int)$q->update($baseUpd);
            }

            // 2) Fallback: reference
            if ($updatedRows === 0 && $has('reference')) {
                $q = DB::connection($adm)->table('payments')
                    ->where('account_id', $accountId)
                    ->where('reference', $sessionId);

                if (!$isPendingTotal && $has('period')) {
                    $q->where('period', $period);
                }

                $updatedRows = (int)$q->update($baseUpd);
            }

            // 3) Fallback final: concept + pending
            if ($updatedRows === 0 && $has('concept')) {
                $q = DB::connection($adm)->table('payments')
                    ->where('account_id', $accountId)
                    ->where('status', 'pending');

                if (!$isPendingTotal && $has('period')) {
                    $q->where('period', $period);
                }

                if ($isPendingTotal) {
                    $q->where('concept', 'like', '%saldo pendiente%');
                } else {
                    $q->where('concept', 'like', '%Estado de cuenta%');
                }

                $payload = $baseUpd;
                if ($has('stripe_session_id')) $payload['stripe_session_id'] = $sessionId;
                if ($has('reference'))        $payload['reference'] = $sessionId;

                $updatedRows = (int)$q->update($payload);
            }

            Log::info('[BILLING:SYNC] payments updated', [
                'account_id'   => $accountId,
                'period'       => $period,
                'session_id'   => $sessionId,
                'updated_rows' => $updatedRows,
                'pending_total'=> $isPendingTotal,
                'stripe_mxn'   => $stripeMxn,
            ]);

            // =========================================================
            // (B) Insert idempotente en estados_cuenta (ADMIN)
            // ✅ aquí va APLICADO AL PERIODO (apply)
            // =========================================================
            if (!Schema::connection($adm)->hasTable('estados_cuenta')) {
                Log::error('[BILLING:SYNC] abort: no existe tabla estados_cuenta en mysql_admin');
            } else {
                $ecCols = Schema::connection($adm)->getColumnListing('estados_cuenta');
                $ecLc   = array_map('strtolower', $ecCols);
                $ecHas  = fn($c) => in_array(strtolower($c), $ecLc, true);

                $linkCol = $ecHas('account_id') ? 'account_id' : ($ecHas('cuenta_id') ? 'cuenta_id' : null);

                if ($linkCol && $ecHas('periodo')) {

                    $existsQ = DB::connection($adm)->table('estados_cuenta')
                        ->where($linkCol, $accountId)
                        ->where('periodo', $period);

                    // Si existen columnas para “marcar” session_id, úsalo.
                    // Si NO existen, NO bloquees por “ya existe el cargo del periodo”; bloquea solo si ya hay abono>0.
                    $hasAnyRefCol = ($ecHas('ref') || $ecHas('detalle') || $ecHas('concepto') || $ecHas('meta'));
                    $refApplied   = false;

                    if ($hasAnyRefCol) {
                        $existsQ->where(function ($w) use ($ecHas, $sessionId, &$refApplied) {
                            $any = false;
                            if ($ecHas('ref')) {
                                $w->orWhere('ref', $sessionId);
                                $any = true;
                            }
                            if ($ecHas('detalle')) {
                                $w->orWhere('detalle', 'like', '%' . $sessionId . '%');
                                $any = true;
                            }
                            if ($ecHas('concepto')) {
                                $w->orWhere('concepto', 'like', '%' . $sessionId . '%');
                                $any = true;
                            }
                            if ($ecHas('meta')) {
                                $w->orWhere('meta', 'like', '%' . $sessionId . '%');
                                $any = true;
                            }
                            $refApplied = $any;
                        });
                    }

                    if (!$refApplied) {
                        if ($ecHas('abono')) {
                            $existsQ->where('abono', '>', 0);
                        }
                        // Si no hay columna abono, no agregamos nada: dejamos insertar (lock del webhook + payments ayudan).
                    }

                    $already = $existsQ->exists();

                    if ($already) {
                        Log::info('[BILLING:SYNC] abono ya existe (admin estados_cuenta), skip', [
                            'account_id' => $accountId,
                            'period'     => $period,
                            'session_id' => $sessionId,
                            'refApplied' => $refApplied,
                        ]);
                    } else {
                        $row = [];
                        $row[$linkCol]  = $accountId;
                        $row['periodo'] = $period;

                        if ($ecHas('concepto')) {
                            $row['concepto'] = $isPendingTotal
                                ? ('Pago saldo pendiente (Stripe) · ' . $sessionId)
                                : ('Pago estado de cuenta ' . $period . ' (Stripe) · ' . $sessionId);
                        }

                        if ($ecHas('detalle')) $row['detalle'] = 'Stripe session: ' . $sessionId;
                        if ($ecHas('ref'))     $row['ref']     = $sessionId;
                        if ($ecHas('source'))  $row['source']  = 'stripe';

                        // ✅ En admin: abono = APLICADO AL PERIODO (no necesariamente lo cobrado)
                        if ($ecHas('abono')) $row['abono'] = round($applyMxn, 2);
                        if ($ecHas('cargo')) $row['cargo'] = 0;

                        if ($ecHas('created_at')) $row['created_at'] = now();
                        if ($ecHas('updated_at')) $row['updated_at'] = now();

                        if ($ecHas('meta')) {
                            $row['meta'] = json_encode([
                                'type'             => $isPendingTotal ? 'billing_pending_total' : 'billing_statement',
                                'period'           => $period,
                                'session_id'       => $sessionId,
                                'payment_intent'   => $session->payment_intent ?? null,

                                // Stripe (cobrado)
                                'stripe_amount_cents' => (int)$stripeCents,
                                'stripe_amount_mxn'   => (float)$stripeMxn,

                                // Sistema/UI (debía cerrar)
                                'ui_amount_cents'  => (int)$uiCents,
                                'ui_amount_mxn'    => (float)$uiMxn,

                                // Aplicado / Crédito
                                'applied_cents'    => (int)$applyCents,
                                'applied_mxn'      => (float)$applyMxn,
                                'credit_cents'     => (int)$creditCents,
                                'credit_mxn'       => (float)$creditMxn,

                                'amount_source'    => $amountSource,
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }

                        DB::connection($adm)->table('estados_cuenta')->insert($row);

                        Log::info('[BILLING:SYNC] admin estados_cuenta insert OK', [
                            'account_id' => $accountId,
                            'period'     => $period,
                            'session_id' => $sessionId,
                            'apply_mxn'  => $applyMxn,
                            'stripe_mxn' => $stripeMxn,
                            'refApplied' => $refApplied,
                        ]);
                    }

                } else {
                    Log::error('[BILLING:SYNC] estados_cuenta admin sin columnas requeridas', [
                        'linkCol'     => $linkCol,
                        'has_periodo' => $ecHas('periodo'),
                    ]);
                }
            }

            // =========================================================
            // (C) ✅ Actualizar accounts.meta (ADMIN)
            // - last_amount_mxn = aplicado (cierre de periodo)
            // - last_stripe_amount_mxn = cobrado real
            // =========================================================
            if (Schema::connection($adm)->hasTable('accounts')) {
                $acc = DB::connection($adm)->table('accounts')
                    ->select(['id','meta'])
                    ->where('id', $accountId)
                    ->first();

                if ($acc) {
                    $metaArr = [];
                    if (is_string($acc->meta ?? null) && (string)$acc->meta !== '') {
                        $d = json_decode((string)$acc->meta, true);
                        if (is_array($d)) $metaArr = $d;
                    } elseif (is_array($acc->meta ?? null)) {
                        $metaArr = (array)$acc->meta;
                    }

                    data_set($metaArr, 'stripe.last_paid_at', now()->toISOString());
                    data_set($metaArr, 'stripe.last_paid_period', $period);
                    data_set($metaArr, 'stripe.last_checkout_session_id', $sessionId);
                    if (!empty($session->payment_intent)) data_set($metaArr, 'stripe.last_payment_intent', (string)$session->payment_intent);

                    // aplicado (lo que cierra periodo)
                    data_set($metaArr, 'stripe.last_amount_mxn', $applyMxn);
                    data_set($metaArr, 'stripe.last_amount_cents', $applyCents);

                    // cobrado real (auditoría)
                    data_set($metaArr, 'stripe.last_stripe_amount_mxn', $stripeMxn);
                    data_set($metaArr, 'stripe.last_stripe_amount_cents', (int)$stripeCents);

                    // UI / crédito
                    data_set($metaArr, 'stripe.last_ui_amount_mxn', $uiMxn);
                    data_set($metaArr, 'stripe.last_ui_amount_cents', (int)$uiCents);
                    data_set($metaArr, 'stripe.last_credit_mxn', $creditMxn);
                    data_set($metaArr, 'stripe.last_credit_cents', (int)$creditCents);

                    data_set($metaArr, 'stripe.last_amount_source', $amountSource);

                    DB::connection($adm)->table('accounts')->where('id', $accountId)->update([
                        'meta'          => json_encode($metaArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'is_blocked'    => 0,
                        'estado_cuenta' => 'activa',
                        'updated_at'    => now(),
                    ]);

                    Log::info('[BILLING:SYNC] accounts.meta updated', [
                        'account_id' => $accountId,
                        'period'     => $period,
                        'session_id' => $sessionId,
                    ]);
                }
            }

            // =========================================================
            // (D) ✅ Cerrar saldo del periodo en mysql_clientes.estados_cuenta
            // ✅ aquí va APLICADO AL PERIODO (apply)
            // =========================================================
            if (Schema::connection($cli)->hasTable('estados_cuenta')) {
                $colsCli = Schema::connection($cli)->getColumnListing('estados_cuenta');
                $lcCli   = array_map('strtolower', $colsCli);
                $hasCli  = fn($c) => in_array(strtolower($c), $lcCli, true);

                $linkCli = $hasCli('account_id') ? 'account_id' : ($hasCli('cuenta_id') ? 'cuenta_id' : null);

                if ($linkCli && $hasCli('periodo')) {
                    $rowCli = DB::connection($cli)->table('estados_cuenta')
                        ->where($linkCli, $accountId)
                        ->where('periodo', $period)
                        ->first();

                    if (!$rowCli) {
                        $ins = [
                            $linkCli  => $accountId,
                            'periodo' => $period,
                        ];

                        // ✅ clientes: cargo=abono=APLICADO (para UI “Monto pagado”)
                        if ($hasCli('cargo')) $ins['cargo'] = round($applyMxn, 2);
                        if ($hasCli('abono')) $ins['abono'] = round($applyMxn, 2);
                        if ($hasCli('saldo')) $ins['saldo'] = 0.0;
                        if ($hasCli('created_at')) $ins['created_at'] = now();
                        if ($hasCli('updated_at')) $ins['updated_at'] = now();

                        DB::connection($cli)->table('estados_cuenta')->insert($ins);

                        Log::info('[BILLING:SYNC] clientes estados_cuenta created PAID', [
                            'account_id' => $accountId,
                            'period'     => $period,
                            'apply_mxn'  => $applyMxn,
                            'stripe_mxn' => $stripeMxn,
                        ]);
                    } else {
                        $cargo = 0.0;
                        if ($hasCli('cargo') && is_numeric($rowCli->cargo ?? null)) $cargo = (float)$rowCli->cargo;
                        if ($cargo <= 0) $cargo = (float)$applyMxn; // ✅ antes usaba Stripe (incorrecto con mínimo)

                        $upd = [];
                        if ($hasCli('cargo')) $upd['cargo'] = round($cargo, 2);
                        if ($hasCli('abono')) $upd['abono'] = round($cargo, 2);
                        if ($hasCli('saldo')) $upd['saldo'] = 0.0;
                        if ($hasCli('updated_at')) $upd['updated_at'] = now();

                        DB::connection($cli)->table('estados_cuenta')
                            ->where($linkCli, $accountId)
                            ->where('periodo', $period)
                            ->update($upd);

                        Log::info('[BILLING:SYNC] clientes estados_cuenta updated PAID', [
                            'account_id' => $accountId,
                            'period'     => $period,
                            'cargo'      => $cargo,
                            'apply_mxn'  => $applyMxn,
                        ]);
                    }
                } else {
                    Log::warning('[BILLING:SYNC] clientes estados_cuenta sin columnas esperadas', [
                        'linkCol'     => $linkCli,
                        'has_periodo' => $hasCli('periodo'),
                    ]);
                }
            }

            DB::connection($cli)->commit();
            DB::connection($adm)->commit();
            
            // ✅ P360: re-evaluar estado real por billing_statements (por si hay pendientes previos)
            try {
                AccountBillingStateService::sync($accountId, 'cliente.stripe.pro.sync');
            } catch (\Throwable $e) {
                Log::warning('[SYNC] AccountBillingStateService sync failed', [
                    'account_id' => $accountId,
                    'session_id' => $sessionId ?: null,
                    'error'      => $e->getMessage(),
                ]);
            }

            Log::info('[BILLING:SYNC] DONE OK', [
                'account_id' => $accountId,
                'period'     => $period,
                'session_id' => $sessionId,
                'apply_mxn'  => $applyMxn,
                'stripe_mxn' => $stripeMxn,
                'credit_mxn' => $creditMxn,
            ]);

        } catch (\Throwable $e) {
            DB::connection($cli)->rollBack();
            DB::connection($adm)->rollBack();

            Log::error('[BILLING:SYNC] error', [
                'account_id' => $accountId,
                'period'     => $period,
                'session_id' => $sessionId ?: null,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }



    /* =========================================================
     |  invoice handlers (PRO)
     * ========================================================= */

    private function handleInvoicePaid($invoice): void
    {
        try {
            $subscriptionId = $invoice->subscription ?? null;
            $customerId     = $invoice->customer ?? null;

            $account = DB::connection('mysql_admin')->table('accounts')
                ->whereRaw("JSON_EXTRACT(meta, '$.stripe.subscription_id') = ?", [$subscriptionId])
                ->orWhereRaw("JSON_EXTRACT(meta, '$.stripe.customer_id') = ?", [$customerId])
                ->first();

            if (!$account) {
                Log::warning('invoice.paid sin account resuelto', [
                    'subscription' => $subscriptionId,
                    'customer'     => $customerId,
                ]);
                return;
            }

            $this->insertPayment((int) $account->id, [
                'amount_cents'          => is_numeric($invoice->amount_paid ?? null) ? (int) $invoice->amount_paid : null,
                'currency'              => strtoupper((string) ($invoice->currency ?? 'MXN')),
                'status'                => 'paid',
                'stripe_session_id'     => null,
                'stripe_invoice_id'     => $invoice->id ?? null,
                'stripe_payment_intent' => $invoice->payment_intent ?? null,
                'meta' => [
                    'source'       => 'invoice.paid',
                    'customer_id'  => $customerId,
                    'subscription' => $subscriptionId,
                ],
            ]);

            $this->updateAccountActivation((int) $account->id, [
                'is_blocked' => 0,
                'estado'     => 'activo',
                'meta_merge' => [
                    'stripe' => [
                        'customer_id'     => $customerId,
                        'subscription_id' => $subscriptionId,
                        'last_invoice_id' => $invoice->id ?? null,
                        'last_paid_at'    => now()->toISOString(),
                    ],
                ],
            ]);

            // ✅ P360: recalcular estado real de cuenta vs billing_statements
            try {
                AccountBillingStateService::sync((int)$account->id, 'cliente.stripe.invoice.paid');
            } catch (\Throwable $e) {
                Log::warning('[invoice.paid] AccountBillingStateService sync failed', [
                    'account_id' => (int)$account->id,
                    'invoice'    => $invoice->id ?? null,
                    'error'      => $e->getMessage(),
                ]);
            }


            Log::info('Renovación registrada invoice.paid', [
                'account_id' => $account->id,
                'invoice'    => $invoice->id ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en handleInvoicePaid', ['error' => $e->getMessage()]);
        }
    }

    private function handleInvoiceFailed($invoice): void
    {
        try {
            $subscriptionId = $invoice->subscription ?? null;
            $customerId     = $invoice->customer ?? null;

            $account = DB::connection('mysql_admin')->table('accounts')
                ->whereRaw("JSON_EXTRACT(meta, '$.stripe.subscription_id') = ?", [$subscriptionId])
                ->orWhereRaw("JSON_EXTRACT(meta, '$.stripe.customer_id') = ?", [$customerId])
                ->first();

            if (!$account) {
                Log::warning('invoice.payment_failed sin account resuelto', [
                    'subscription' => $subscriptionId,
                    'customer'     => $customerId,
                ]);
                return;
            }

            $this->updateAccountActivation((int) $account->id, [
                'estado' => 'pago_pendiente',
                'meta_merge' => [
                    'stripe' => [
                        'last_failed_invoice_id' => $invoice->id ?? null,
                        'last_failed_at'         => now()->toISOString(),
                    ],
                ],
            ]);

            // ✅ P360: recalcular estado real de cuenta vs billing_statements
            try {
                AccountBillingStateService::sync((int)$account->id, 'cliente.stripe.invoice.failed');
            } catch (\Throwable $e) {
                Log::warning('[invoice.failed] AccountBillingStateService sync failed', [
                    'account_id' => (int)$account->id,
                    'invoice'    => $invoice->id ?? null,
                    'error'      => $e->getMessage(),
                ]);
            }


            Log::info('Cuenta marcada pago pendiente por invoice.payment_failed', [
                'account_id' => $account->id,
                'invoice'    => $invoice->id ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en handleInvoiceFailed', ['error' => $e->getMessage()]);
        }
    }

    /* =========================================================
     |  Resolve Stripe Price (por price_key estable)
     * ========================================================= */

    private function resolveStripePriceIdOrFailByKey(string $priceKey): string
{
    $priceKey = strtolower(trim($priceKey));
    if ($priceKey === '') {
        throw ValidationException::withMessages(['plan' => 'price_key inválido.']);
    }

    $cacheKey = "p360:stripe_price_id:key:{$priceKey}";

    // 1) Resolver desde DB (como hoy)
    $priceId = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($priceKey) {
        $row = DB::connection('mysql_admin')
            ->table('stripe_price_list')
            ->where('is_active', 1)
            ->whereRaw('LOWER(price_key)=?', [$priceKey])
            ->orderByDesc('id')
            ->first();

        return $row->stripe_price_id ?? null;
    });

    if (!$priceId) {
        throw ValidationException::withMessages([
            'plan' => "Precio Stripe no disponible (stripe_price_list) para price_key={$priceKey}.",
        ]);
    }

    $priceId = (string) $priceId;

    // 2) Verificar que el Price exista en Stripe (mismo entorno/cuenta del secret actual)
    try {
        $this->stripe->prices->retrieve($priceId, []);
        return $priceId; // OK
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        $msg = (string) $e->getMessage();

        // Si Stripe dice "No such price", intentamos auto-reparar usando lookup_key
        if (stripos($msg, 'No such price') !== false || stripos($msg, 'no such price') !== false) {

            // 3) Buscar en Stripe por lookup_key = priceKey (requiere que configures lookup_key en Stripe)
            try {
                $found = $this->stripe->prices->all([
                    'active'      => true,
                    'limit'       => 10,
                    'lookup_keys' => [$priceKey],
                    'expand'      => ['data.product'],
                ]);

                $newId = null;
                if ($found && isset($found->data) && is_array($found->data) && count($found->data) > 0) {
                    // Tomamos el primer match activo
                    $newId = (string) ($found->data[0]->id ?? '');
                }

                if ($newId) {
                    // 4) Persistir en DB como el nuevo activo (y desactivar anteriores)
                    DB::connection('mysql_admin')->beginTransaction();
                    try {
                        DB::connection('mysql_admin')
                            ->table('stripe_price_list')
                            ->whereRaw('LOWER(price_key)=?', [$priceKey])
                            ->update([
                                'is_active'   => 0,
                                'updated_at'  => now(),
                            ]);

                        DB::connection('mysql_admin')
                            ->table('stripe_price_list')
                            ->insert([
                                'price_key'        => $priceKey,
                                'stripe_price_id'  => $newId,
                                'is_active'        => 1,
                                'created_at'       => now(),
                                'updated_at'       => now(),
                            ]);

                        DB::connection('mysql_admin')->commit();
                    } catch (\Throwable $tx) {
                        DB::connection('mysql_admin')->rollBack();
                        throw $tx;
                    }

                    // 5) Limpiar cache para que no se quede el viejo
                    Cache::forget($cacheKey);

                    Log::warning('Stripe price reparado por lookup_key', [
                        'price_key'     => $priceKey,
                        'old_price_id'  => $priceId,
                        'new_price_id'  => $newId,
                    ]);

                    return $newId;
                }

                // No se pudo reparar: no existe lookup_key configurado o no hay price activo
                throw ValidationException::withMessages([
                    'plan' => "Stripe price inválido para price_key={$priceKey}. En DB={$priceId} pero Stripe no lo reconoce. " .
                              "Configura en Stripe el lookup_key='{$priceKey}' o actualiza stripe_price_list con un price_id válido del mismo entorno (test/live).",
                ]);

            } catch (ValidationException $ve) {
                throw $ve;
            } catch (\Throwable $inner) {
                throw ValidationException::withMessages([
                    'plan' => "Stripe price inválido para price_key={$priceKey}. En DB={$priceId} pero Stripe no lo reconoce y no se pudo auto-reparar. " .
                              "Detalle: " . $inner->getMessage(),
                ]);
            }
        }

        // Otro error de Stripe distinto a "No such price"
        throw ValidationException::withMessages([
            'plan' => "No se pudo validar el precio Stripe ({$priceKey}). Detalle: {$msg}",
        ]);
    } catch (\Throwable $e) {
        throw ValidationException::withMessages([
            'plan' => "No se pudo validar el precio Stripe ({$priceKey}). Detalle: " . $e->getMessage(),
        ]);
    }
}


    /* =========================================================
     |  PRO SYNC
     * ========================================================= */

    private function syncAccountFromCheckoutSession(int $accountId, $session, string $cycle): void
    {
        $paymentStatus = strtolower((string) ($session->payment_status ?? ''));
        if ($paymentStatus !== 'paid') {
            Log::warning('[SYNC] session no pagada, no se activa', [
                'account_id'     => $accountId,
                'session_id'     => $session->id ?? null,
                'payment_status' => $paymentStatus ?: null,
                'status'         => $session->status ?? null,
            ]);
            return;
        }

        $currency     = strtoupper((string) ($session->currency ?? 'MXN'));
        $customer     = $session->customer ?? null;
        $subscription = $session->subscription ?? null;
        $sessionId    = $session->id ?? null;

        // ✅ Monto robusto también para PRO (subscription)
        [$amountCents] = $this->resolvePaidAmountFromCheckoutSession($session);

        DB::connection('mysql_admin')->beginTransaction();
        DB::connection('mysql_clientes')->beginTransaction();

        try {
            if ($sessionId && Schema::connection('mysql_admin')->hasTable('payments')) {
                $exists = DB::connection('mysql_admin')->table('payments')
                    ->where('account_id', $accountId)
                    ->where('stripe_session_id', $sessionId)
                    ->exists();

                if (!$exists) {
                    $this->insertPayment($accountId, [
                        'amount_cents'          => $amountCents,
                        'currency'              => $currency ?: 'MXN',
                        'status'                => 'paid',
                        'stripe_session_id'     => $sessionId,
                        'stripe_invoice_id'     => null,
                        'stripe_payment_intent' => $session->payment_intent ?? null,
                        'meta' => [
                            'source'       => 'checkout_session',
                            'cycle'        => $cycle,
                            'customer_id'  => $customer,
                            'subscription' => $subscription,
                        ],
                    ]);
                }
            }

            $this->updateAccountActivation($accountId, [
                'plan'        => 'PRO',
                'plan_actual' => 'PRO',
                'modo_cobro'  => ($cycle === 'anual') ? 'anual' : 'mensual',
                'is_blocked'  => 0,
                'estado'      => 'activo',
                'meta_merge'  => [
                    'stripe' => [
                        'customer_id'     => $customer,
                        'subscription_id' => $subscription,
                        'last_session_id' => $sessionId,
                        'last_paid_at'    => now()->toISOString(),
                        'cycle'           => $cycle,
                    ],
                ],
            ]);

            $tomorrowMidnight = now()->startOfDay()->addDay();
            $this->promoteClienteByAdminAccount($accountId, [
                'plan_actual'               => 'PRO',
                'max_usuarios'              => 10,
                'max_empresas'              => 9999,
                'max_mass_invoices_per_day' => 100,
                'mass_invoices_used_today'  => 0,
                'mass_invoices_reset_at'    => $tomorrowMidnight,
                'espacio_asignado_mb'       => 15360,
                'estado_cuenta'             => 'activo',
            ]);

            DB::connection('mysql_clientes')->commit();
            DB::connection('mysql_admin')->commit();

            Log::info('[SYNC] cuenta PRO activada OK', [
                'account_id' => $accountId,
                'cycle'      => $cycle,
                'session_id' => $sessionId,
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql_clientes')->rollBack();
            DB::connection('mysql_admin')->rollBack();

            Log::error('[SYNC] error activando cuenta PRO', [
                'account_id' => $accountId,
                'error'      => $e->getMessage(),
                'session_id' => $sessionId,
            ]);

            throw $e;
        }
    }

    /* =========================================================
     |  Helpers de esquema
     * ========================================================= */

    private function adminHas(string $col): bool
    {
        try {
            return Schema::connection('mysql_admin')->hasColumn('accounts', $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function updateAccountActivation(int $accountId, array $data): void
    {
        $upd = [];

        if ($this->adminHas('plan') && isset($data['plan']))                 $upd['plan'] = $data['plan'];
        if ($this->adminHas('plan_actual') && isset($data['plan_actual']))   $upd['plan_actual'] = $data['plan_actual'];
        if ($this->adminHas('modo_cobro') && isset($data['modo_cobro']))     $upd['modo_cobro'] = $data['modo_cobro'];

        if ($this->adminHas('is_blocked') && array_key_exists('is_blocked', $data)) $upd['is_blocked'] = (int) $data['is_blocked'];

        if ($this->adminHas('estado_cuenta') && isset($data['estado']))      $upd['estado_cuenta'] = $data['estado'];

        if ($this->adminHas('meta') && isset($data['meta_merge']) && is_array($data['meta_merge'])) {
            $current = DB::connection('mysql_admin')->table('accounts')->where('id', $accountId)->value('meta');
            $curArr  = [];

            if (is_string($current) && $current !== '') {
                $decoded = json_decode($current, true);
                if (is_array($decoded)) $curArr = $decoded;
            } elseif (is_array($current)) {
                $curArr = $current;
            }

            $merged = array_replace_recursive($curArr, $data['meta_merge']);
            $upd['meta'] = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($this->adminHas('updated_at')) $upd['updated_at'] = now();

        if (!empty($upd)) {
            DB::connection('mysql_admin')->table('accounts')->where('id', $accountId)->update($upd);
        }
    }

    private function insertPayment(int $accountId, array $meta): void
    {
        $table = 'payments';
        if (!Schema::connection('mysql_admin')->hasTable($table)) return;

        $cols = Schema::connection('mysql_admin')->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn($c) => in_array(strtolower($c), $lc, true);

        $row = [];

        if ($has('account_id'))  $row['account_id']  = $accountId;

        if ($has('amount')) {
            $amountCents = $meta['amount_cents'] ?? null;
            $row['amount'] = is_numeric($amountCents) ? (int) $amountCents : 0;
        }

        if ($has('currency'))    $row['currency']    = (string) ($meta['currency'] ?? 'MXN');
        if ($has('status'))      $row['status']      = (string) ($meta['status'] ?? 'paid');

        if ($has('stripe_session_id') && !empty($meta['stripe_session_id'])) $row['stripe_session_id'] = (string) $meta['stripe_session_id'];
        if ($has('stripe_invoice_id') && !empty($meta['stripe_invoice_id'])) $row['stripe_invoice_id'] = (string) $meta['stripe_invoice_id'];
        if ($has('stripe_payment_intent') && !empty($meta['stripe_payment_intent'])) $row['stripe_payment_intent'] = (string) $meta['stripe_payment_intent'];

        if ($has('paid_at') && (($row['status'] ?? null) === 'paid')) $row['paid_at'] = now();

        if ($has('meta') && isset($meta['meta'])) {
            $row['meta'] = json_encode($meta['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($has('created_at'))  $row['created_at']  = now();
        if ($has('updated_at'))  $row['updated_at']  = now();

        DB::connection('mysql_admin')->table($table)->insert($row);
    }

    private function promoteClienteByAdminAccount(int $accountId, array $vals): void
    {
        if (!Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) return;

        $cols = Schema::connection('mysql_clientes')->getColumnListing('cuentas_cliente');
        $lc   = array_map('strtolower', $cols);
        $has  = fn($c) => in_array(strtolower($c), $lc, true);

        $upd = [];
        foreach ($vals as $k => $v) {
            if ($has($k)) $upd[$k] = $v;
        }
        if ($has('updated_at')) $upd['updated_at'] = now();

        if (!empty($upd) && $has('admin_account_id')) {
            DB::connection('mysql_clientes')->table('cuentas_cliente')
                ->where('admin_account_id', $accountId)
                ->update($upd);
        }
    }

    private function isLocal(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }

    private function normalizeVaultGb(int $gb): int
    {
        $allowed = [5, 10, 20, 50, 100, 500, 1024];
        sort($allowed);

        foreach ($allowed as $val) {
            if ($gb <= $val) return $val;
        }
        return 1024;
    } 

    /**
 * Fallback de monto (cuando Stripe session no trae amount_total en subscription mode)
 * Intenta obtener el monto del periodo desde mysql_admin.accounts.meta.billing
 * Soporta estructuras:
 * - meta.billing.amount_cents / amount_mxn
 * - meta.billing[YYYY-MM].cents / mxn / amount_cents / amount_mxn
 */
private function resolveAmountFromAdminMetaBilling(int $accountId, string $period): array
{
    $adm = 'mysql_admin';
    if (!Schema::connection($adm)->hasTable('accounts')) {
        return [0, 0.0, 'admin.meta.billing:none'];
    }

    $acc = DB::connection($adm)->table('accounts')->select(['id','meta'])->where('id', $accountId)->first();
    if (!$acc) return [0, 0.0, 'admin.meta.billing:account_not_found'];

    $metaArr = [];
    if (is_string($acc->meta ?? null) && (string)$acc->meta !== '') {
        $d = json_decode((string)$acc->meta, true);
        if (is_array($d)) $metaArr = $d;
    } elseif (is_array($acc->meta ?? null)) {
        $metaArr = (array)$acc->meta;
    }

    $billing = $metaArr['billing'] ?? null;
    if (!is_array($billing)) {
        return [0, 0.0, 'admin.meta.billing:missing'];
    }

    // Caso 1: billing.amount_cents / billing.amount_mxn
    $cents = $billing['amount_cents'] ?? $billing['cents'] ?? null;
    $mxn   = $billing['amount_mxn']   ?? $billing['mxn']   ?? null;

    // Caso 2: billing[period].*
    if ((!is_numeric($cents) || (int)$cents <= 0) && isset($billing[$period]) && is_array($billing[$period])) {
        $node  = $billing[$period];
        $cents = $node['amount_cents'] ?? $node['cents'] ?? null;
        $mxn   = $node['amount_mxn']   ?? $node['mxn']   ?? null;
    }

    // Normaliza a cents
    if (!is_numeric($cents) || (int)$cents <= 0) {
        if (is_numeric($mxn) && (float)$mxn > 0) {
            $c = (int) round(((float)$mxn) * 100);
            return [$c, round($c / 100, 2), 'admin.meta.billing:mxn_to_cents'];
        }
        return [0, 0.0, 'admin.meta.billing:unresolved'];
    }

    $c = (int)$cents;
    return [$c, round($c / 100, 2), 'admin.meta.billing:cents'];
}


}
