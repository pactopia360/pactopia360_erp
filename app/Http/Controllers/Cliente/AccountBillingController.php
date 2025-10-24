<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountBillingController extends Controller
{
    public function __construct()
    {
        // Exige sesión cliente y que la cuenta esté activa (o al menos verificada).
        // Ajusta middlewares si tu flujo requiere permitir ver el statement aún bloqueado.
        $this->middleware(['auth:web'])->only(['statement', 'payPending']);
        // Si deseas bloquear el acceso por estado, comenta la siguiente línea:
        // $this->middleware(['client.account.active'])->only(['statement']);
    }

    /**
     * Estado de cuenta + pagos pendientes/recientes.
     * Vista: resources/views/cliente/billing/statement.blade.php
     */
    public function statement(Request $req): View|RedirectResponse
    {
        // Resolver account_id del ADMIN de forma robusta
        [$accountId, $via] = $this->resolveAdminAccountId($req);

        if (!$accountId) {
            // Sincrónico pero seguro: mandamos a registro PRO si no se pudo resolver
            return redirect()
                ->route('cliente.registro.pro')
                ->withErrors([
                    'plan' => 'No pudimos resolver tu cuenta administrativa. Completa tu registro o contáctanos.',
                ]);
        }

        // ---- Admin: cuenta, suscripción, pagos ----
        $acc = DB::connection('mysql_admin')
            ->table('accounts')
            ->where('id', $accountId)
            ->first();

        $sub = DB::connection('mysql_admin')
            ->table('subscriptions')
            ->where('account_id', $accountId)
            ->first();

        // Pagos pendientes y últimos pagados (para historial)
        $pending = DB::connection('mysql_admin')
            ->table('payments')
            ->where('account_id', $accountId)
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->get();

        $recentPaid = DB::connection('mysql_admin')
            ->table('payments')
            ->where('account_id', $accountId)
            ->where('status', 'paid')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        $totalDue = (float) $pending->sum('amount');

        // Precios visibles (muestran CTA si hace falta)
        $displayMonthly = (float) config('services.stripe.display_price_monthly', 249.99);
        $displayAnnual  = (float) config('services.stripe.display_price_annual', 1999.99);

        // Flags de estado para la vista
        $isPastDue     = $sub && ($sub->status === 'past_due');
        $isBlocked     = $acc && (bool)($acc->is_blocked ?? false);
        $estadoCuenta  = $acc->estado_cuenta ?? null;

        return view('cliente.billing.statement', [
            'account'          => $acc,
            'subscription'     => $sub,
            'pending'          => $pending,
            'recentPaid'       => $recentPaid,
            'totalDue'         => $totalDue,
            'displayMonthly'   => $displayMonthly,
            'displayAnnual'    => $displayAnnual,
            'isPastDue'        => $isPastDue,
            'isBlocked'        => $isBlocked,
            'estadoCuenta'     => $estadoCuenta,
            'accountId'        => $accountId,   // útil para los formularios de checkout
            'resolvedVia'      => $via,         // debug/diag opcional en la vista
        ]);
    }

    /**
     * Simulación de pago directo de pendientes (si decides tener un botón "Pagar ahora").
     * En la implementación real, redirige a Stripe Checkout o a tu método de paywall.
     */
    public function payPending(Request $req): RedirectResponse
    {
        // Aquí típicamente rediriges a Stripe Checkout con el account_id, o a un portal de pagos.
        // Como ya tienes rutas de checkout mensual/anual con StripeController,
        // dejamos este método para compatibilidad y mensajes claros.
        return back()->with('ok', 'Redirigiendo a pasarela de pago…');
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    /**
     * Resuelve admin account_id usando (en orden):
     * 1) Relación cliente → admin_account_id (cuentas_clientes.admin_account_id)
     * 2) RFC de la cuenta de clientes buscando en admin.accounts
     * 3) session('account_id') / $req->user()->account_id como último recurso
     *
     * @return array{0:int|null,1:string}
     */
    private function resolveAdminAccountId(Request $req): array
    {
        $u = Auth::guard('web')->user();
        if ($u && !$u->relationLoaded('cuenta')) {
            $u->load('cuenta');
        }

        // 1) admin_account_id desde la cuenta de clientes
        $adminId = $u?->cuenta?->admin_account_id ?? null;
        if ($adminId) {
            return [(int) $adminId, 'clientes.admin_account_id'];
        }

        // 2) Buscar por RFC en admin.accounts (si tenemos RFC en la cuenta cliente)
        $rfc = $u?->cuenta?->rfc_padre;
        if ($rfc) {
            $acc = DB::connection('mysql_admin')
                ->table('accounts')
                ->select('id')
                ->where('rfc', strtoupper($rfc))
                ->first();

            if ($acc) {
                return [(int) $acc->id, 'admin.by_rfc'];
            }
        }

        // 3) Fallback por sesión o propiedad en user (si existe)
        $fallback = ($req->user()->account_id ?? null) ?: $req->session()->get('account_id');
        if ($fallback) {
            return [(int) $fallback, 'session_or_user'];
        }

        return [null, 'unresolved'];
    }
}
