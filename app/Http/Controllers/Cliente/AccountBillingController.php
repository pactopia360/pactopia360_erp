<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
        $displayMonthly = (float) config('services.stripe.display_price_monthly', 990.00);
        $displayAnnual  = (float) config('services.stripe.display_price_annual', 9990.00);

        // Flags de estado para la vista
        $isPastDue     = $sub && ($sub->status === 'past_due');
        $isBlocked     = $acc && (bool)($acc->is_blocked ?? false);
        $estadoCuenta  = $acc->estado_cuenta ?? null;

        // === NUEVO: resumen común para que el header muestre PRO correcto ===
        $summary = $this->buildAccountSummary();

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
            'summary'          => $summary,
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

    /**
     * Resumen de cuenta igualado a la lógica de Home/Perfil/EstadoCuenta
     * para que el header sepa si la cuenta es PRO.
     */
    private function buildAccountSummary(): array
    {
        $u      = Auth::guard('web')->user();
        $cuenta = $u?->cuenta;

        if (!$cuenta) {
            return [
                'razon'        => (string) ($u->nombre ?? $u->email ?? '—'),
                'plan'         => 'free',
                'is_pro'       => false,
                'cycle'        => 'mensual',
                'next_invoice' => null,
                'estado'       => null,
                'blocked'      => false,
                'balance'      => 0.0,
                'space_total'  => 512.0,
                'space_used'   => 0.0,
                'space_pct'    => 0.0,
                'timbres'      => 0,
                'admin_id'     => null,
            ];
        }

        $admConn = 'mysql_admin';

        $planKey = strtoupper((string) ($cuenta->plan_actual ?? 'FREE'));
        $timbres = (int) ($cuenta->timbres_disponibles ?? ($planKey === 'FREE' ? 10 : 0));
        $saldoMx = (float) ($cuenta->saldo_mxn ?? 0.0);
        $razon   = $cuenta->razon_social ?? $cuenta->nombre_fiscal ?? ($u->nombre ?? $u->email ?? '—');

        $adminId = $cuenta->admin_account_id ?? null;
        $rfc     = $cuenta->rfc_padre ?? null;

        if (!$adminId && $rfc && Schema::connection($admConn)->hasTable('accounts')) {
            $acc = DB::connection($admConn)->table('accounts')->select('id')->where('rfc', strtoupper($rfc))->first();
            if ($acc) $adminId = (int) $acc->id;
        }

        $acc = null;
        if ($adminId && Schema::connection($admConn)->hasTable('accounts')) {
            $cols = ['id'];
            foreach ([
                'plan',
                'billing_cycle',
                'next_invoice_date',
                'estado_cuenta',
                'is_blocked',
                'razon_social',
                'email',
                'email_verified_at',
                'phone_verified_at',
            ] as $c) {
                if (Schema::connection($admConn)->hasColumn('accounts', $c)) {
                    $cols[] = $c;
                }
            }
            $acc = DB::connection($admConn)->table('accounts')->select($cols)->where('id', $adminId)->first();
        }

        $balance = $saldoMx;
        if (Schema::connection($admConn)->hasTable('estados_cuenta')) {
            $linkCol = null;
            $linkVal = null;

            if (Schema::connection($admConn)->hasColumn('estados_cuenta', 'account_id') && $adminId) {
                $linkCol = 'account_id';
                $linkVal = $adminId;
            } elseif (Schema::connection($admConn)->hasColumn('estados_cuenta', 'cuenta_id') && $adminId) {
                $linkCol = 'cuenta_id';
                $linkVal = $adminId;
            } elseif (Schema::connection($admConn)->hasColumn('estados_cuenta', 'rfc') && $rfc) {
                $linkCol = 'rfc';
                $linkVal = strtoupper($rfc);
            }

            if ($linkCol !== null) {
                $orderCol = Schema::connection($admConn)->hasColumn('estados_cuenta', 'periodo')
                    ? 'periodo'
                    : (Schema::connection($admConn)->hasColumn('estados_cuenta', 'created_at') ? 'created_at' : 'id');

                $last = DB::connection($admConn)->table('estados_cuenta')
                    ->where($linkCol, $linkVal)
                    ->orderByDesc($orderCol)
                    ->first();

                if ($last && property_exists($last, 'saldo') && $last->saldo !== null) {
                    $balance = (float) $last->saldo;
                } else {
                    $hasCargo = Schema::connection($admConn)->hasColumn('estados_cuenta', 'cargo');
                    $hasAbono = Schema::connection($admConn)->hasColumn('estados_cuenta', 'abono');

                    if ($hasCargo || $hasAbono) {
                        $cargo = $hasCargo
                            ? (float) DB::connection($admConn)->table('estados_cuenta')->where($linkCol, $linkVal)->sum('cargo')
                            : 0.0;
                        $abono = $hasAbono
                            ? (float) DB::connection($admConn)->table('estados_cuenta')->where($linkCol, $linkVal)->sum('abono')
                            : 0.0;
                        $balance = $cargo - $abono;
                    }
                }
            }
        }

        $spaceTotal = (float) ($cuenta->espacio_total_mb ?? 512);
        $spaceUsed  = (float) ($cuenta->espacio_usado_mb ?? 0);
        $spacePct   = $spaceTotal > 0 ? min(100, round(($spaceUsed / $spaceTotal) * 100, 1)) : 0;

        $plan   = strtolower((string) ($acc->plan ?? $planKey));
        $cycle  = $acc->billing_cycle ?? ($cuenta->modo_cobro ?? 'mensual');
        $estado = $acc->estado_cuenta ?? ($cuenta->estado_cuenta ?? null);
        $blocked = (bool) (($acc->is_blocked ?? 0) || ($cuenta->is_blocked ?? 0));

        return [
            'razon'        => (string) ($acc->razon_social ?? $razon),
            'plan'         => $plan,
            'is_pro'       => in_array($plan, ['pro','premium','empresa','business'], true) || Str::startsWith($plan, 'pro'),
            'cycle'        => $cycle,
            'next_invoice' => $acc->next_invoice_date ?? null,
            'estado'       => $estado,
            'blocked'      => $blocked,
            'balance'      => $balance,
            'space_total'  => $spaceTotal,
            'space_used'   => $spaceUsed,
            'space_pct'    => $spacePct,
            'timbres'      => $timbres,
            'admin_id'     => $adminId,
        ];
    }
}
