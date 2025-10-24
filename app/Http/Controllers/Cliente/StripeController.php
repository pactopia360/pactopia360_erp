<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeController extends Controller
{
    private StripeClient $stripe;

    public function __construct()
    {
        $secret = (string) config('services.stripe.secret');
        if (!$secret) {
            Log::warning('Stripe secret ausente en config(services.stripe.secret)');
        }
        $this->stripe = new StripeClient($secret ?: '');
    }

    /** Checkout PRO mensual */
    public function checkoutMonthly(Request $request)
    {
        return $this->createCheckout($request, 'mensual');
    }

    /** Checkout PRO anual */
    public function checkoutAnnual(Request $request)
    {
        return $this->createCheckout($request, 'anual');
    }

    /** Crear sesión de checkout (plan mensual/anual) */
    private function createCheckout(Request $request, string $plan)
    {
        try {
            $validated = $request->validate([
                'account_id' => ['required'],
                'email'      => ['nullable', 'email'],
            ]);

            $accountId = $validated['account_id'];

            // --- Admin: buscamos cuenta ---
            $account = DB::connection('mysql_admin')->table('accounts')->where('id', $accountId)->first();
            if (!$account) {
                throw ValidationException::withMessages(['plan' => 'No se encontró la cuenta proporcionada.']);
            }

            // --- Price IDs desde config/services.php ---
            $priceId = $plan === 'mensual'
                ? config('services.stripe.price_monthly')
                : config('services.stripe.price_annual');

            if (!$priceId) {
                throw ValidationException::withMessages(['plan' => 'Precio Stripe no configurado.']);
            }

            // --- URLs de retorno ---
            $successUrl = route('cliente.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl  = route('cliente.checkout.cancel');

            // --- Email preferido ---
            $emailCol = $this->accountEmailColumn();
            $customerEmail = $validated['email'] ?? ($account->{$emailCol} ?? null);

            // --- Crear Checkout Session (modo suscripción) ---
            $idempotencyKey = 'checkout:' . $accountId . ':' . $plan . ':' . Str::uuid();

            $session = $this->stripe->checkout->sessions->create([
                'mode'                   => 'subscription',
                'payment_method_types'   => ['card'],
                'allow_promotion_codes'  => true,
                'line_items' => [[
                    'price'    => $priceId,
                    'quantity' => 1,
                ]],
                'customer_email'        => $customerEmail,
                'client_reference_id'   => (string)$accountId,
                'success_url'           => $successUrl,
                'cancel_url'            => $cancelUrl,
                'metadata'              => [
                    'account_id' => (string)$accountId,
                    'plan'       => $plan,
                ],
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            Log::info('Stripe checkout session creada', [
                'account_id' => $accountId,
                'plan'       => $plan,
                'session'    => $session->id ?? null,
            ]);

            return redirect($session->url);
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            Log::error('Error creando Stripe Checkout', [
                'error'      => $e->getMessage(),
                'plan'       => $plan,
                'account_id' => $request->get('account_id'),
            ]);
            return back()->withErrors(['plan' => 'No se pudo iniciar el checkout. Intenta de nuevo.']);
        }
    }

    /** Pantalla de éxito */
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');

        if (!$sessionId) {
            return redirect()->route('cliente.login')->with('ok', 'Pago confirmado.');
        }

        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId, []);
            $plan    = $session->metadata->plan ?? 'PRO';
            $accountId = $session->metadata->account_id
                      ?? $session->client_reference_id
                      ?? null;

            return view('cliente.auth.success', [
                'plan'      => $plan,
                'accountId' => $accountId,
                'sessionId' => $sessionId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo recuperar session de Stripe en success', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            return redirect()->route('cliente.login')->with('ok', 'Pago confirmado.');
        }
    }

    /** Pantalla de cancelación */
    public function cancel()
    {
        return redirect()->route('cliente.registro.pro')->withErrors([
            'plan' => 'El pago fue cancelado. Puedes intentarlo de nuevo.',
        ]);
    }

    /**
     * Webhook Stripe (confirmación de pago/renovaciones)
     * IMPORTANTE: la ruta excluye CSRF y aplica throttle alto (ver routes/cliente.php).
     */
    public function webhook(Request $request)
    {
        $endpointSecret = (string) config('services.stripe.webhook_secret');
        $skipVerify     = (bool) env('STRIPE_WEBHOOK_SKIP_VERIFY', false);

        try {
            $payload   = $request->getContent();
            $signature = $request->header('Stripe-Signature');

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
                    $session = $event->data->object; // \Stripe\Checkout\Session
                    $this->handleCheckoutCompleted($session);
                    break;

                case 'invoice.paid':
                    $invoice = $event->data->object; // \Stripe\Invoice
                    $this->handleInvoicePaid($invoice);
                    break;

                case 'invoice.payment_failed':
                    $invoice = $event->data->object; // \Stripe\Invoice
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

    /**
     * Procesar checkout completado (alta inicial PRO)
     */
    private function handleCheckoutCompleted($session): void
    {
        try {
            $accountId   = $session->metadata->account_id ?? $session->client_reference_id ?? null;
            $plan        = $session->metadata->plan ?? 'mensual';
            $currency    = $session->currency ?? 'mxn';
            $customer    = $session->customer ?? null;       // cus_...
            $subscription= $session->subscription ?? null;   // sub_...
            $email       = $session->customer_details->email ?? $session->customer_email ?? null;

            if (!$accountId) {
                Log::warning('checkout.session.completed sin account_id');
                return;
            }

            $amountTotal = property_exists($session, 'amount_total') ? $session->amount_total : null;

            DB::connection('mysql_admin')->beginTransaction();
            DB::connection('mysql_clientes')->beginTransaction();

            // 1) Admin: actualizar accounts (solo columnas existentes)
            $this->updateAccountActivation((int)$accountId, [
                'plan'        => (strtolower($plan) === 'anual') ? 'anual' : 'mensual', // normalizamos a tu estilo si está en minúsculas
                'plan_actual' => 'PRO',
                'modo_cobro'  => (strtolower($plan) === 'anual') ? 'anual' : 'mensual',
                'is_blocked'  => 0,
                'estado'      => 'activo', // usar "activo"
                'stripe_customer_id'     => $customer,
                'stripe_subscription_id' => $subscription,
            ]);

            // 2) Admin: subscriptions (mapeo flexible)
            $this->upsertSubscription((int)$accountId, [
                'status'      => 'active',
                'plan_key'    => (strtolower($plan) === 'anual' ? 'pro_anual' : 'pro_mensual'),
                'external_id' => $subscription,
                'customer_id' => $customer,
                'cycle'       => (strtolower($plan) === 'anual' ? 'annual' : 'monthly'),
            ]);

            // 3) Admin: payments (mapeo flexible)
            $this->insertPayment((int)$accountId, [
                'amount'     => is_numeric($amountTotal) ? ($amountTotal / 100) : null,
                'currency'   => strtoupper($currency ?? 'MXN'),
                'status'     => 'paid',
                'reference'  => $session->id,
                'subscription_external_id' => $subscription,
                'customer_external_id'     => $customer,
                'stripe_session_id'        => $session->id ?? null,
            ]);

            // 4) Clientes: subir límites a PRO enlazando por admin_account_id
            $tomorrowMidnight = now()->startOfDay()->addDay();
            $this->promoteClienteByAdminAccount((int)$accountId, [
                'plan_actual'               => 'PRO',
                'max_usuarios'              => 10,
                'max_empresas'              => 9999,
                'max_mass_invoices_per_day' => 100,
                'mass_invoices_used_today'  => 0,
                'mass_invoices_reset_at'    => $tomorrowMidnight,
                'espacio_asignado_mb'       => 15360,
                'estado_cuenta'             => 'activo', // usar "activo"
            ]);

            // 5) CRM: marcar carrito como "ganado"
            $this->winCrmCarritoByEmail($email);

            DB::connection('mysql_clientes')->commit();
            DB::connection('mysql_admin')->commit();

            Log::info('Stripe pago confirmado y cuenta PRO activada', [
                'account_id'   => $accountId,
                'plan'         => $plan,
                'session_id'   => $session->id ?? null,
                'subscription' => $subscription,
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql_clientes')->rollBack();
            DB::connection('mysql_admin')->rollBack();

            Log::error('Error procesando checkout.session.completed', [
                'error'     => $e->getMessage(),
                'session'   => $session->id ?? null,
                'metadata'  => $session->metadata ?? null,
            ]);
        }
    }

    /** Procesar invoice.paid (renovaciones) */
    private function handleInvoicePaid($invoice): void
    {
        try {
            $subscriptionId = $invoice->subscription ?? null;
            $customerId     = $invoice->customer ?? null;

            $account = DB::connection('mysql_admin')->table('accounts')
                ->where('stripe_subscription_id', $subscriptionId)
                ->orWhere('stripe_customer_id', $customerId)
                ->first();

            if (!$account) {
                Log::warning('invoice.paid sin account resuelto', [
                    'subscription' => $subscriptionId,
                    'customer'     => $customerId,
                ]);
                return;
            }

            // Registrar pago (flex)
            $this->insertPayment((int)$account->id, [
                'amount'     => is_numeric($invoice->amount_paid ?? null) ? ($invoice->amount_paid / 100) : null,
                'currency'   => strtoupper($invoice->currency ?? 'MXN'),
                'status'     => 'paid',
                'reference'  => $invoice->id ?? null,
                'subscription_external_id' => $subscriptionId,
                'customer_external_id'     => $customerId,
                'stripe_invoice_id'        => $invoice->id ?? null,
            ]);

            // Desbloquear cuenta
            $this->updateAccountActivation((int)$account->id, [
                'is_blocked'    => 0,
                'estado'        => 'activo',
            ]);

            Log::info('Renovación registrada invoice.paid', [
                'account_id'   => $account->id,
                'invoice'      => $invoice->id ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en handleInvoicePaid', ['error' => $e->getMessage()]);
        }
    }

    /** Procesar invoice.payment_failed (suspensiones) */
    private function handleInvoiceFailed($invoice): void
    {
        try {
            $subscriptionId = $invoice->subscription ?? null;
            $customerId     = $invoice->customer ?? null;

            $account = DB::connection('mysql_admin')->table('accounts')
                ->where('stripe_subscription_id', $subscriptionId)
                ->orWhere('stripe_customer_id', $customerId)
                ->first();

            if (!$account) {
                Log::warning('invoice.payment_failed sin account resuelto', [
                    'subscription' => $subscriptionId,
                    'customer'     => $customerId,
                ]);
                return;
            }

            // Bloqueo suave
            $this->updateAccountActivation((int)$account->id, [
                'estado' => 'pago_pendiente',
            ]);

            Log::info('Cuenta marcada pago pendiente por invoice.payment_failed', [
                'account_id' => $account->id,
                'invoice'    => $invoice->id ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en handleInvoiceFailed', ['error' => $e->getMessage()]);
        }
    }

    /* =================== Helpers flexibles de esquema =================== */

    private function adminHas(string $col): bool
    {
        try { return Schema::connection('mysql_admin')->hasColumn('accounts', $col); }
        catch (\Throwable $e) { return false; }
    }

    private function accountEmailColumn(): string
    {
        foreach (['correo_contacto','email'] as $c) if ($this->adminHas($c)) return $c;
        return 'email';
    }

    private function updateAccountActivation(int $accountId, array $data): void
    {
        $upd = [];

        // Plan / modo cobro (solo si existen)
        if ($this->adminHas('plan') && isset($data['plan']))           $upd['plan'] = $data['plan'];
        if ($this->adminHas('plan_actual') && isset($data['plan_actual'])) $upd['plan_actual'] = $data['plan_actual'];
        if ($this->adminHas('modo_cobro') && isset($data['modo_cobro']))   $upd['modo_cobro'] = $data['modo_cobro'];

        // Estado / bloqueos
        if ($this->adminHas('is_blocked') && array_key_exists('is_blocked', $data)) $upd['is_blocked'] = $data['is_blocked'];
        // Algunos esquemas usan estado_cuenta o status
        if ($this->adminHas('estado_cuenta') && isset($data['estado'])) $upd['estado_cuenta'] = $data['estado'];
        elseif ($this->adminHas('status') && isset($data['estado']))    $upd['status'] = $data['estado'];

        // Stripe ids si existen
        if ($this->adminHas('stripe_customer_id') && isset($data['stripe_customer_id']))         $upd['stripe_customer_id'] = $data['stripe_customer_id'];
        if ($this->adminHas('stripe_subscription_id') && isset($data['stripe_subscription_id'])) $upd['stripe_subscription_id'] = $data['stripe_subscription_id'];

        if ($this->adminHas('updated_at')) $upd['updated_at'] = now();

        if (!empty($upd)) {
            DB::connection('mysql_admin')->table('accounts')->where('id', $accountId)->update($upd);
        }
    }

    private function upsertSubscription(int $accountId, array $meta): void
    {
        $table = 'subscriptions';
        if (!Schema::connection('mysql_admin')->hasTable($table)) return;

        $cols = Schema::connection('mysql_admin')->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn($c) => in_array(strtolower($c), $lc, true);

        $row = [];
        // IDs externos (si existen)
        if ($has('provider'))    $row['provider']    = 'stripe';
        if ($has('external_id') && isset($meta['external_id'])) $row['external_id'] = $meta['external_id'];
        if ($has('customer_id') && isset($meta['customer_id'])) $row['customer_id'] = $meta['customer_id'];

        // Estado / plan
        if ($has('status'))      $row['status']      = $meta['status'] ?? 'active';
        if ($has('plan_key'))    $row['plan_key']    = $meta['plan_key'] ?? null;

        // Compatibilidad con tu esquema existente
        if ($has('plan'))        $row['plan']        = 'PRO';
        if ($has('billing_cycle')) $row['billing_cycle'] = $meta['cycle'] ?? 'monthly';
        if ($has('cycle'))       $row['cycle']       = $meta['cycle'] ?? 'monthly';
        if ($has('started_at'))  $row['started_at']  = now();
        if ($has('updated_at'))  $row['updated_at']  = now();
        if ($has('created_at'))  $row['created_at']  = now();

        // Upsert por account_id si existe la columna
        if ($has('account_id')) {
            DB::connection('mysql_admin')->table($table)->updateOrInsert(
                ['account_id' => $accountId],
                $row + ['account_id' => $accountId]
            );
        } else {
            // Fallback: insert simple
            DB::connection('mysql_admin')->table($table)->insert($row);
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
        if ($has('amount'))      $row['amount']      = $meta['amount'] ?? null;
        if ($has('currency'))    $row['currency']    = $meta['currency'] ?? 'MXN';
        if ($has('status'))      $row['status']      = $meta['status'] ?? 'paid';

        // Campos “estándar” si existen
        if ($has('method'))      $row['method']      = 'stripe';
        if ($has('provider'))    $row['provider']    = 'stripe';
        if ($has('reference') && !empty($meta['reference'])) $row['reference'] = $meta['reference'];

        // Tu esquema observado
        if ($has('gateway') && empty($row['provider'])) $row['gateway'] = 'stripe';
        if ($has('gateway_txn_id') && !empty($meta['reference'])) $row['gateway_txn_id'] = $meta['reference'];
        if ($has('subscription_id')) {
            // si existe relación numérica, dejamos null; si tienes tabla relacional distinta, puedes mapear aquí.
        }
        if ($has('stripe_session_id') && !empty($meta['stripe_session_id']))   $row['stripe_session_id']   = $meta['stripe_session_id'];
        if ($has('stripe_invoice_id') && !empty($meta['stripe_invoice_id']))   $row['stripe_invoice_id']   = $meta['stripe_invoice_id'];
        if ($has('stripe_payment_intent') && !empty($meta['stripe_payment_intent'])) $row['stripe_payment_intent'] = $meta['stripe_payment_intent'];

        if ($has('paid_at') && ($row['status'] ?? null) === 'paid') $row['paid_at'] = now();

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

    private function winCrmCarritoByEmail(?string $email): void
    {
        if (!$email) return;

        $table = 'crm_carritos';
        try {
            if (!Schema::connection('mysql_admin')->hasTable($table)) return;
            $cols = Schema::connection('mysql_admin')->getColumnListing($table);
        } catch (\Throwable $e) {
            return;
        }

        $lc = array_map('strtolower', $cols);
        $map = function(array $opts) use ($lc) {
            foreach ($opts as $o) if (in_array(strtolower($o), $lc, true)) return $o;
            return null;
        };

        $cEmail  = $map(['email','correo','correo_contacto']);
        $cEstado = $map(['estado','status','state','fase']);
        $cUAt    = $map(['updated_at','actualizado_en','fecha_actualizacion']);

        if (!$cEmail || !$cEstado) return;

        $upd = [$cEstado => 'ganado'];
        if ($cUAt) $upd[$cUAt] = now();

        DB::connection('mysql_admin')->table($table)
            ->where($cEmail, $email)
            ->update($upd);
    }
}
