<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Cliente\AccountBillingController.php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Billing\BillingStatementsHubController;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class AccountBillingController extends Controller
{
    /**
     * Stripe se resuelve "lazy" para que /estado-de-cuenta NO truene
     * si stripe/stripe-php no está instalado o si falta el secret.
     */
    private $stripe = null;

    private BillingStatementsHubController $hub;

    public function __construct()
    {
        // ✅ publicPdf/publicPdfInline/publicPay sin sesión (firma)
        $this->middleware(['auth:web'])->except(['publicPdf', 'publicPdfInline', 'publicPay']);

        // ✅ HUB (misma lógica que Admin)
        $this->hub = App::make(BillingStatementsHubController::class);
    }

    /**
     * Retorna instancia de StripeClient solo si:
     * - stripe/stripe-php está instalado
     * - hay secret configurado
     */
    private function stripe()
    {
        if ($this->stripe) return $this->stripe;

        $secret = (string) config('services.stripe.secret');
        if (trim($secret) === '') return null;

        // Importante: no referenciar Stripe\StripeClient en types para evitar fatal
        if (!class_exists(\Stripe\StripeClient::class)) return null;

        try {
            $this->stripe = new \Stripe\StripeClient($secret);
            return $this->stripe;
        } catch (\Throwable $e) {
            Log::warning('[BILLING] StripeClient init failed', ['err' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ============================================
     * ESTADO DE CUENTA (2 cards)
     * ============================================
     */
    public function statement(Request $r)
    {
        $u = Auth::guard('web')->user();

        [$accountIdRaw, $src] = $this->resolveAdminAccountId($r);
        $accountId = is_numeric($accountIdRaw) ? (int) $accountIdRaw : 0;

        Log::info('[BILLING][DEBUG] resolved admin account', [
            'account_id_raw' => $accountIdRaw,
            'account_id'     => $accountId,
            'src'            => $src,
            'user_id'        => Auth::guard('web')->id(),
            'session'        => [
                'client.account_id' => $r->session()->get('client.account_id'),
                'account_id'        => $r->session()->get('account_id'),
                'client.cuenta_id'  => $r->session()->get('client.cuenta_id'),
                'cuenta_id'         => $r->session()->get('cuenta_id'),
                'client_account_id' => $r->session()->get('client_account_id'),
            ],
        ]);

        if ($accountId <= 0) {
            Log::warning('[BILLING] statement: account unresolved (redirect login)', [
                'user_id' => $u?->id,
                'src'     => $src,
                'session' => [
                    'verify.account_id'   => $r->session()->get('verify.account_id'),
                    'paywall.account_id'  => $r->session()->get('paywall.account_id'),
                    'client.account_id'   => $r->session()->get('client.account_id'),
                    'account_id'          => $r->session()->get('account_id'),
                    'client.cuenta_id'    => $r->session()->get('client.cuenta_id'),
                    'cuenta_id'           => $r->session()->get('cuenta_id'),
                    'client_account_id'   => $r->session()->get('client_account_id'),
                ],
            ]);

            return redirect()->route('cliente.login', [
                'next' => '/cliente/estado-de-cuenta',
            ]);
        }

        // ✅ CRÍTICO: NO contaminar llaves genéricas de sesión
        try {
            $r->session()->put('billing.admin_account_id', (string) $accountId);
            $r->session()->put('billing.admin_account_src', (string) $src);
        } catch (\Throwable $e) {
            // ignore
        }

        // Datos UI (RFC/Alias)
        [$rfc, $alias] = $this->resolveRfcAliasForUi($r, $accountId);

        // Inicio contrato (fallback)
        $contractStart = $this->resolveContractStartPeriod($accountId);

        // ✅ ÚLTIMO PAGADO (SOT: payments/meta, fallback clientes)
        $lastPaid = $this->adminLastPaidPeriod($accountId);

        $regen = ((string) $r->query('regen', '0') === '1');

        // ✅ Ciclo (mensual/anual)
        $isAnnual = $this->isAnnualBillingCycle($accountId);

        $basePeriod = (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $contractStart))
            ? (string) $contractStart
            : now()->format('Y-m');

        // ✅ Resolver refs (uuid/int/string) para billing_statements (SOT)
        $statementRefs = $this->buildStatementRefs($accountId);

        // ✅ Calcular payAllowed con SOT primero (pending real), luego fallback mensual/anual con ventana
        $payAllowed = $this->computePayAllowed($accountId, $isAnnual, $basePeriod, $lastPaid, $statementRefs);

        // ✅ Periodos base para cálculo de precio (NO UI final)
        $periods = [];

        // Si no hay periodo pagable (ej. anual fuera de ventana y sin pendientes), mostramos vacío después
        if ($payAllowed !== null) {
            if ($isAnnual) {
                // anual: periodo base + (si aplica) el payAllowed
                $periods[] = $lastPaid ?: $basePeriod;

                // solo agrega payAllowed si es distinto al base
                if ($payAllowed !== ($lastPaid ?: $basePeriod)) {
                    $periods[] = $payAllowed;
                }
            } else {
                // mensual: lastPaid (si existe) + payAllowed
                $periods = [$lastPaid, $payAllowed];
            }
        }

        $periods = array_values(array_unique(array_filter($periods)));


        // ==========================================================
        // ✅ PRECIO por periodo (Admin meta.billing + fallbacks)
        // ==========================================================
        $priceInfo = ['per_period' => []];
        foreach ($periods as $p) {
            $priceInfo['per_period'][$p] = ['cents' => 0, 'mxn' => 0.0, 'source' => 'none'];
        }

        foreach ($periods as $p) {
            $cents = $this->resolveMonthlyCentsForPeriodFromAdminAccount($accountId, $p, $lastPaid, $payAllowed);
            if ($cents > 0) {
                $priceInfo['per_period'][$p]['cents']  = $cents;
                $priceInfo['per_period'][$p]['mxn']    = round($cents / 100, 2);
                $priceInfo['per_period'][$p]['source'] = 'admin.accounts.meta.billing';
            }
        }

        foreach ($periods as $p) {
            if (($priceInfo['per_period'][$p]['cents'] ?? 0) > 0) continue;

            $cents = $this->resolveMonthlyCentsFromPlanesCatalog($accountId);
            if ($cents > 0) {
                $priceInfo['per_period'][$p]['cents']  = $cents;
                $priceInfo['per_period'][$p]['mxn']    = round($cents / 100, 2);
                $priceInfo['per_period'][$p]['source'] = 'admin.planes';
            }
        }

        foreach ($periods as $p) {
            if (($priceInfo['per_period'][$p]['cents'] ?? 0) > 0) continue;

            $cents = $this->resolveMonthlyCentsFromEstadosCuenta($accountId, $lastPaid, $payAllowed);
            if ($cents > 0) {
                $priceInfo['per_period'][$p]['cents']  = $cents;
                $priceInfo['per_period'][$p]['mxn']    = round($cents / 100, 2);
                $priceInfo['per_period'][$p]['source'] = 'admin.estados_cuenta';
            }
        }

        foreach ($periods as $p) {
            if (($priceInfo['per_period'][$p]['cents'] ?? 0) > 0) continue;

            $cents = $this->resolveMonthlyCentsFromClientesEstadosCuenta($accountId, $lastPaid, $payAllowed);
            if ($cents > 0) {
                $priceInfo['per_period'][$p]['cents']  = $cents;
                $priceInfo['per_period'][$p]['mxn']    = round($cents / 100, 2);
                $priceInfo['per_period'][$p]['source'] = 'clientes.estados_cuenta';
            }
        }

        // ==========================================================
        // ✅ ANUAL: forzar monto ANUAL
        // ==========================================================
        $annualTotalMxn   = 0.0;
        $annualCentsFinal = 0;

        if ($isAnnual) {
            try {
                $baseAnnual = $lastPaid ?: $basePeriod;

                $annualCents = (int) $this->resolveAnnualCents(
                    $accountId,
                    (string) $baseAnnual,
                    $lastPaid,
                    $payAllowed
                );

                if ($annualCents > 0) {
                    $annualCentsFinal = $annualCents;
                    $annualTotalMxn   = round($annualCents / 100, 2);

                    foreach ($periods as $p) {
                        $priceInfo['per_period'][$p] = [
                            'cents'  => $annualCentsFinal,
                            'mxn'    => $annualTotalMxn,
                            'source' => 'annual.resolveAnnualCents',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $annualTotalMxn   = 0.0;
                $annualCentsFinal = 0;
            }
        }

        // chargesByPeriod + sourcesByPeriod
        $chargesByPeriod = [];
        $sourcesByPeriod = [];
        foreach ($periods as $p) {
            $chargesByPeriod[$p] = (float) ($priceInfo['per_period'][$p]['mxn'] ?? 0.0);
            $sourcesByPeriod[$p] = (string) ($priceInfo['per_period'][$p]['source'] ?? 'none');
        }

        $payAllowed = is_string($payAllowed ?? null) ? trim((string)$payAllowed) : '';
        if ($payAllowed === '') {
            $payAllowed = is_string($period ?? null) && (string)$period !== '' ? trim((string)$period) : now()->format('Y-m');
        }


        // Fallback base (clientes.estados_cuenta)
        $rows = $this->buildPeriodRowsFromClientEstadosCuenta(
            $accountId,
            $periods,
            $payAllowed,
            $chargesByPeriod,
            $lastPaid
        );

        // ==========================================================
        // ✅ SOT REAL: billing_statements (uuid/int)
        // ==========================================================
        $statementRefs = [];
        try {
            $statementRefs[] = (string) $accountId;
            $statementRefs[] = $accountId;

            $cli = (string) config('p360.conn.clientes', 'mysql_clientes');
            if (Schema::connection($cli)->hasTable('cuentas_cliente')) {
                $uuids = DB::connection($cli)->table('cuentas_cliente')
                    ->where('admin_account_id', $accountId)
                    ->limit(200)
                    ->pluck('id')
                    ->map(fn ($x) => trim((string) $x))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                foreach ($uuids as $u) $statementRefs[] = $u;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 👇 aquí vienen NORMALIZADOS (paid/pending, saldo, etc.)
        $rowsFromStatementsAll = $this->loadRowsFromAdminBillingStatements($statementRefs, 60);

        // ✅ NUEVO: si Admin ya tiene PAID en statements, úsalo como "lastPaid" (aunque payments/meta no lo traiga)
        $lastPaidFromStatements = null;
        try {
            $paidPeriods = [];
            foreach ((array) $rowsFromStatementsAll as $rr) {
                $pp = (string) ($rr['period'] ?? '');
                if (!$pp || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $pp)) continue;
                if (strtolower((string) ($rr['status'] ?? '')) === 'paid') $paidPeriods[] = $pp;
            }
            if (!empty($paidPeriods)) {
                sort($paidPeriods); // asc
                $lastPaidFromStatements = end($paidPeriods) ?: null;
            }
        } catch (\Throwable $e) {
            $lastPaidFromStatements = null;
        }

        // ✅ Pendientes reales desde statements
        $rowsFromStatementsPending = [];
        try {
            $rowsFromStatementsPending = array_values(array_filter((array) $rowsFromStatementsAll, function ($rr) {
                $st    = strtolower((string) ($rr['status'] ?? 'pending'));
                $saldo = (float) ($rr['saldo'] ?? 0);
                return ($st !== 'paid') && ($saldo > 0.0001);
            }));
        } catch (\Throwable $e) {
            $rowsFromStatementsPending = [];
        }

        // ====== DEBUG opcional ======
        Log::info('[BILLING][DEBUG] statements probe', [
            'account_id'                  => $accountId,
            'statement_refs'              => $statementRefs,
            'rows_all_count'              => is_array($rowsFromStatementsAll) ? count($rowsFromStatementsAll) : 0,
            'rows_pending_count'          => is_array($rowsFromStatementsPending) ? count($rowsFromStatementsPending) : 0,
            'lastPaid_from_payments_meta' => $lastPaid,
            'lastPaid_from_statements'    => $lastPaidFromStatements,
        ]);

        if (!empty($rowsFromStatementsPending)) {
            $rows = $rowsFromStatementsPending;

            // Override por payments (si aplica)
            $rows = $this->applyAdminPaidAmountOverrides($accountId, $rows);

            usort($rows, function ($a, $b) {
                return strcmp((string) ($a['period'] ?? ''), (string) ($b['period'] ?? ''));
            });

            // payAllowed = primer pendiente
            $payAllowed = (string) ($rows[0]['period'] ?? $payAllowed);

        } else {
            // ✅ Si NO hay pendientes en statements pero sí hay "paid", entonces el permitido es el siguiente periodo.
            if ($lastPaidFromStatements && (!$lastPaid || $lastPaidFromStatements > $lastPaid)) {
                $lastPaid = $lastPaidFromStatements;

                try {
                    $payAllowed = $isAnnual
                        ? Carbon::createFromFormat('Y-m', $lastPaid)->addYearNoOverflow()->format('Y-m')
                        : Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m');
                } catch (\Throwable $e) {
                    // deja payAllowed como venía
                }

                // Asegura que exista cargo para payAllowed en chargesByPeriod
                if (!isset($chargesByPeriod[$payAllowed]) || (float) $chargesByPeriod[$payAllowed] <= 0) {
                    $cents = 0;

                    if ($isAnnual) {
                        try {
                            $baseAnnual = $lastPaid ?: $basePeriod;
                            $cents = (int) $this->resolveAnnualCents($accountId, (string) $baseAnnual, $lastPaid, $payAllowed);
                        } catch (\Throwable $e) {
                            $cents = 0;
                        }
                    } else {
                        $cents = (int) $this->resolveMonthlyCentsForPeriodFromAdminAccount($accountId, $payAllowed, $lastPaid, $payAllowed);
                        if ($cents <= 0) $cents = (int) $this->resolveMonthlyCentsFromPlanesCatalog($accountId);
                        if ($cents <= 0) $cents = (int) $this->resolveMonthlyCentsFromEstadosCuenta($accountId, $lastPaid, $payAllowed);
                        if ($cents <= 0) $cents = (int) $this->resolveMonthlyCentsFromClientesEstadosCuenta($accountId, $lastPaid, $payAllowed);
                    }

                    if ($cents > 0) {
                        $chargesByPeriod[$payAllowed] = round($cents / 100, 2);
                        $sourcesByPeriod[$payAllowed] = $isAnnual ? 'annual.resolveAnnualCents' : 'resolved.recalc.payAllowed';
                    } else {
                        $chargesByPeriod[$payAllowed] = (float) ($chargesByPeriod[$lastPaid] ?? 0.0);
                        $sourcesByPeriod[$payAllowed] = 'fallback.lastPaid_charge';
                    }
                }

                // Reconstruye SOLO el payAllowed como pendiente (enero ya pagado => febrero pendiente)
                $rows = $this->buildPeriodRowsFromClientEstadosCuenta(
                    $accountId,
                    [$payAllowed],
                    $payAllowed,
                    [$payAllowed => (float) $chargesByPeriod[$payAllowed]],
                    $lastPaid
                );

                $rows = $this->keepOnlyPayAllowedPeriod($rows, $payAllowed);
            } else {
                // fallback anterior
                $rows = $this->applyAdminPaidAmountOverrides($accountId, $rows);
                $rows = $this->keepOnlyPayAllowedPeriod($rows, $payAllowed);
            }
        }

        
        // Enriquecimiento UI + ✅ CANONICALIZACIÓN de account_id (evita mostrar ID_ANUAL en vez de ID_MENSUAL)
        foreach ($rows as &$row) {
            // ✅ Guardar referencia original (por debug) si venía diferente
            if (!isset($row['statement_account_ref'])) {
                $row['statement_account_ref'] = $row['account_id'] ?? null;
            }

            // ✅ SIEMPRE mostrar y usar el admin account id canónico en UI/PDF
            $row['admin_account_id'] = (int) $accountId;
            $row['account_id']       = (int) $accountId;

            $p = (string) ($row['period'] ?? '');
            if ($p !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p)) {
                $c = Carbon::createFromFormat('Y-m', $p);
                $row['period_range'] = $c->copy()->startOfMonth()->format('d/m/Y') . ' - ' . $c->copy()->endOfMonth()->format('d/m/Y');
            } else {
                $row['period_range'] = '';
            }

            $row['rfc']          = (string) ($row['rfc'] ?? $rfc);
            $row['alias']        = (string) ($row['alias'] ?? $alias);
            $row['price_source'] = $sourcesByPeriod[$p] ?? 'none';
        }
        unset($row);

        $rows = $this->attachInvoiceRequestStatus($accountId, $rows);

        // ✅ Si no hay periodo habilitado para pago (anual fuera de ventana y sin pendientes),
        // el portal debe mostrar "sin pendientes" (rows vacías).
        if ($payAllowed === null) {
            $rows = [];
            $payAllowed = $basePeriod; // para no reventar el blade si espera string (UI: no habrá can_pay)
        }


        // KPIs
        $pendingBalance = 0.0;
        foreach ($rows as $row) {
            if (($row['period'] ?? '') === $payAllowed && ($row['status'] ?? '') === 'pending') {
                $pendingBalance = (float) ($row['saldo'] ?? 0);
            }
        }

        $paidTotal = 0.0;

        Log::info('[BILLING] statement render', [
            'account_id'     => $accountId,
            'src'            => $src,
            'contract_start' => $contractStart,
            'last_paid'      => $lastPaid,
            'pay_allowed'    => $payAllowed,
            'rows_count'     => is_array($rows) ? count($rows) : 0,
            'ui_rfc'         => $rfc,
            'ui_alias'       => $alias,
        ]);

        $mensualidadAdmin = (float) ($chargesByPeriod[$payAllowed] ?? ($chargesByPeriod[$lastPaid] ?? 0.0));

        return view('cliente.billing.statement', [
            'accountId'            => $accountId,
            'contractStart'        => $contractStart,
            'lastPaid'             => $lastPaid,
            'payAllowed'           => $payAllowed,
            'rows'                 => $rows,
            'saldoPendiente'       => $pendingBalance,
            'periodosPagadosTotal' => $paidTotal,
            'mensualidadAdmin'     => $mensualidadAdmin,
            'annualTotalMxn'       => $annualTotalMxn,
            'isAnnual'             => (bool) $isAnnual,
            'rfc'                  => $rfc,
            'alias'                => $alias,
        ]);
    }

    /**
     * ✅ Construye refs válidos para consultar admin.billing_statements:
     * - admin account id (string/int)
     * - UUIDs de cuentas_cliente relacionadas (si existen)
     */
    private function buildStatementRefs(int $adminAccountId): array
    {
        $refs = [];

        try {
            $refs[] = (string) $adminAccountId;
            $refs[] = (int) $adminAccountId;

            $cli = (string) config('p360.conn.clientes', 'mysql_clientes');
            if (Schema::connection($cli)->hasTable('cuentas_cliente')) {
                $uuids = DB::connection($cli)->table('cuentas_cliente')
                    ->where('admin_account_id', $adminAccountId)
                    ->limit(200)
                    ->pluck('id')
                    ->map(fn ($x) => trim((string) $x))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                foreach ($uuids as $u) $refs[] = $u;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // unique + filter
        $refs = array_values(array_unique(array_filter($refs, fn ($x) => trim((string)$x) !== '')));
        return $refs;
    }

    /**
     * ✅ Calcula payAllowed de forma canónica:
     * 1) Si hay pending real en admin.billing_statements => primer periodo pendiente (asc)
     * 2) Si no hay pending:
     *    - Mensual: lastPaid+1 mes (o basePeriod si no hay lastPaid)
     *    - Anual: lastPaid+1 año (o basePeriod) SOLO si está dentro de ventana; si no, null
     */
    private function computePayAllowed(
        int $adminAccountId,
        bool $isAnnual,
        string $basePeriod,
        ?string $lastPaid,
        array $statementRefs
    ): ?string {
        // 1) pending real desde statements
        try {
            $rowsAll = $this->loadRowsFromAdminBillingStatements($statementRefs, 60);

            $pendingPeriods = [];
            foreach ((array) $rowsAll as $rr) {
                $pp = (string) ($rr['period'] ?? '');
                if (!$this->isValidPeriod($pp)) continue;

                $st = strtolower((string) ($rr['status'] ?? 'pending'));
                $saldo = (float) ($rr['saldo'] ?? 0);

                if ($st !== 'paid' && $saldo > 0.0001) {
                    $pendingPeriods[] = $pp;
                }
            }

            if (!empty($pendingPeriods)) {
                sort($pendingPeriods); // asc
                return (string) $pendingPeriods[0];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 2) fallback sin statements pendientes
        try {
            if ($isAnnual) {
                // next renewal
                $next = $lastPaid
                    ? Carbon::createFromFormat('Y-m', $lastPaid)->addYearNoOverflow()->format('Y-m')
                    : $basePeriod;

                // ventana anual
                $winDays = (int) $this->annualRenewalWindowDays();
                $renewAt = Carbon::createFromFormat('Y-m', $next)->startOfMonth();
                $openAt  = $renewAt->copy()->subDays(max(0, $winDays));

                // si aún no abre ventana => no hay pago habilitado
                if (now()->lessThan($openAt)) {
                    return null;
                }

                return $next;
            }

            // mensual
            $next = $lastPaid
                ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
                : $basePeriod;

            return $next;
        } catch (\Throwable $e) {
            // en caso raro, cae a base
            return $basePeriod;
        }
    }



    /**
     * ==========================================================
     * ✅ PAGO (AUTH) -> genera link firmado a publicPay
     * ==========================================================
     */
    public function pay(Request $r, string $period): RedirectResponse
    {
        if (!$this->isValidPeriod($period)) abort(422, 'Periodo inválido.');

        [$accountIdRaw] = $this->resolveAdminAccountId($r);
        $accountId = is_numeric($accountIdRaw) ? (int) $accountIdRaw : 0;
        if ($accountId <= 0) abort(403, 'Cuenta no seleccionada.');

        $url = URL::temporarySignedRoute(
            'cliente.billing.publicPay',
            now()->addMinutes(30),
            ['accountId' => $accountId, 'period' => $period]
        );

        return redirect()->away($url);
    }

    /**
     * ==========================================================
     * ✅ PDF INLINE (AUTH) -> genera link firmado a publicPdfInline
     * ==========================================================
     */
    public function pdfInline(Request $r, string $period): RedirectResponse
    {
        if (!$this->isValidPeriod($period)) abort(422, 'Periodo inválido.');

        [$accountIdRaw] = $this->resolveAdminAccountId($r);
        $accountId = is_numeric($accountIdRaw) ? (int) $accountIdRaw : 0;
        if ($accountId <= 0) abort(403, 'Cuenta no seleccionada.');

        $url = URL::temporarySignedRoute(
            'cliente.billing.publicPdfInline',
            now()->addMinutes(30),
            ['accountId' => $accountId, 'period' => $period]
        );

        return redirect()->away($url);
    }

    /**
     * ==========================================================
     * ✅ Guardar perfil de facturación (legacy/aux)
     * ==========================================================
     * Guarda en admin.accounts.meta.billing (si existe) para no romper flujos.
     */
    public function saveBillingProfile(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'rfc'          => 'nullable|string|max:20',
            'razon_social' => 'nullable|string|max:255',
            'alias'        => 'nullable|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:30',
        ]);

        [$accountIdRaw] = $this->resolveAdminAccountId($r);
        $accountId = is_numeric($accountIdRaw) ? (int) $accountIdRaw : 0;

        if ($accountId <= 0) {
            return back()->with('warning', 'No se pudo guardar: cuenta no seleccionada.');
        }

        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        if (!Schema::connection($adm)->hasTable('accounts')) {
            return back()->with('warning', 'No se pudo guardar: tabla accounts no disponible.');
        }

        $cols = Schema::connection($adm)->getColumnListing('accounts');
        $lc   = array_map('strtolower', $cols);
        $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('meta')) {
            // fallback: si existen columnas directas, intenta
            $upd = [];
            if ($has('rfc') && !empty($data['rfc'])) $upd['rfc'] = $data['rfc'];
            if ($has('razon_social') && !empty($data['razon_social'])) $upd['razon_social'] = $data['razon_social'];
            if ($has('alias') && !empty($data['alias'])) $upd['alias'] = $data['alias'];
            if ($has('email') && !empty($data['email'])) $upd['email'] = $data['email'];

            if ($upd) {
                if ($has('updated_at')) $upd['updated_at'] = now();
                DB::connection($adm)->table('accounts')->where('id', $accountId)->update($upd);
                return back()->with('success', 'Perfil de facturación actualizado.');
            }

            return back()->with('warning', 'No se pudo guardar: estructura incompleta (sin meta).');
        }

        try {
            $acc = DB::connection($adm)->table('accounts')->select(['id', 'meta'])->where('id', $accountId)->first();
            if (!$acc) return back()->with('warning', 'No se pudo guardar: cuenta no encontrada.');

            $meta = is_string($acc->meta ?? null) ? (json_decode((string) $acc->meta, true) ?: []) : (array) ($acc->meta ?? []);
            $meta['billing'] = is_array($meta['billing'] ?? null) ? $meta['billing'] : [];

            if (!empty($data['rfc']))          $meta['billing']['rfc'] = trim((string) $data['rfc']);
            if (!empty($data['razon_social'])) $meta['billing']['razon_social'] = trim((string) $data['razon_social']);
            if (!empty($data['alias']))        $meta['billing']['alias'] = trim((string) $data['alias']);
            if (!empty($data['email']))        $meta['billing']['email'] = trim((string) $data['email']);
            if (!empty($data['phone']))        $meta['billing']['phone'] = trim((string) $data['phone']);

            $upd = [
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ];
            if ($has('updated_at')) $upd['updated_at'] = now();

            DB::connection($adm)->table('accounts')->where('id', $accountId)->update($upd);

            return back()->with('success', 'Perfil de facturación actualizado.');
        } catch (\Throwable $e) {
            Log::error('[BILLING] saveBillingProfile failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
            return back()->with('warning', 'No se pudo guardar: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Monto pagado real (cents) desde admin.payments para un periodo.
     * Fuente primaria para evitar "PAGADO = 0".
     */
    private function resolvePaidCentsFromAdminPayments(int $accountId, string $period): int
    {
        if (!$this->isValidPeriod($period)) return 0;

        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        if (!Schema::connection($adm)->hasTable('payments')) return 0;

        try {
            $cols = Schema::connection($adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

            // Requiere al menos estas columnas para filtrar bien
            if (!$has('account_id') || !$has('period')) return 0;

            $q = DB::connection($adm)->table('payments')
                ->where('account_id', $accountId)
                ->where('period', $period);

            // Status pagado (soporta variaciones)
            if ($has('status')) {
                $q->whereIn('status', ['paid', 'succeeded', 'complete', 'completed', 'captured']);
            }

            // Provider stripe si existe (pero permite NULL/'')
            if ($has('provider')) {
                $q->where(function ($w) {
                    $w->whereNull('provider')
                        ->orWhere('provider', '')
                        ->orWhereIn('provider', ['stripe', 'Stripe']);
                });
            }

            // Columna amount en cents
            $amountCol = $has('amount') ? 'amount' : null;
            if (!$amountCol) return 0;

            $orderCol = $has('paid_at') ? 'paid_at'
                : ($has('confirmed_at') ? 'confirmed_at'
                    : ($has('updated_at') ? 'updated_at'
                        : ($has('created_at') ? 'created_at'
                            : ($has('id') ? 'id' : $cols[0]))));

            $row = $q->orderByDesc($orderCol)->first([$amountCol]);

            $cents = ($row && is_numeric($row->{$amountCol} ?? null)) ? (int) $row->{$amountCol} : 0;
            return $cents > 0 ? $cents : 0;
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolvePaidCentsFromAdminPayments failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
            return 0;
        }
    }

    private function resolveStatementTotalsCents(int $accountId, string $period): array
    {
        $adm = (string) config('p360.conn.admin', 'mysql_admin');

        $out = [
            'subtotal_cents' => 0,
            'tax_cents'      => 0,
            'total_cents'    => 0,
            'paid_cents'     => 0,
        ];

        if (!Schema::connection($adm)->hasTable('billing_statements')) {
            return $out;
        }

        $cols = Schema::connection($adm)->getColumnListing('billing_statements');
        $lc   = array_map('strtolower', $cols);
        $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) {
            return $out;
        }

        $subtotalCol = $has('subtotal_cents') ? 'subtotal_cents' : ($has('subtotal') ? 'subtotal' : null);
        $taxCol      = $has('tax_cents') ? 'tax_cents' : ($has('tax') ? 'tax' : ($has('iva_cents') ? 'iva_cents' : null));
        $totalCol    = $has('total_cents') ? 'total_cents' : ($has('total') ? 'total' : null);
        $paidCol     = $has('paid_cents') ? 'paid_cents' : ($has('paid') ? 'paid' : null);

        $orderCol    = $has('id') ? 'id' : ($has('created_at') ? 'created_at' : $cols[0]);

        $sel = ['period'];
        if ($subtotalCol) $sel[] = $subtotalCol;
        if ($taxCol)      $sel[] = $taxCol;
        if ($totalCol)    $sel[] = $totalCol;
        if ($paidCol)     $sel[] = $paidCol;

        $row = DB::connection($adm)->table('billing_statements')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->orderByDesc($orderCol)
            ->first($sel);

        if (!$row) return $out;

        $out['subtotal_cents'] = (int) ($subtotalCol ? ($row->{$subtotalCol} ?? 0) : 0);
        $out['tax_cents']      = (int) ($taxCol ? ($row->{$taxCol} ?? 0) : 0);
        $out['total_cents']    = (int) ($totalCol ? ($row->{$totalCol} ?? 0) : 0);
        $out['paid_cents']     = (int) ($paidCol ? ($row->{$paidCol} ?? 0) : 0);

        if ($out['paid_cents'] <= 0) {
            try {
                $out['paid_cents'] = (int) $this->resolvePaidCentsFromAdminPayments($accountId, $period);
            } catch (\Throwable $e) {
            }
        }

        return $out;
    }

    /**
     * ✅ Fuente primaria de monto: billing_statements (admin)
     */
    private function resolveChargeCentsFromAdminBillingStatements(int $accountId, string $period): int
    {
        if (!$this->isValidPeriod($period)) return 0;

        try {
            $t = $this->resolveStatementTotalsCents($accountId, $period);

            $total = (int) ($t['total_cents'] ?? 0);
            if ($total > 0) return $total;

            $sub = (int) ($t['subtotal_cents'] ?? 0);
            $tax = (int) ($t['tax_cents'] ?? 0);

            $sum = $sub + $tax;
            return $sum > 0 ? $sum : 0;
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolveChargeCentsFromAdminBillingStatements failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * ✅ Normaliza pagos usando admin.payments como fuente primaria.
     */
    private function applyAdminPaidAmountOverrides(int $accountId, array $rows): array
    {
        foreach ($rows as &$r) {
            $p = (string) ($r['period'] ?? '');
            if (!$this->isValidPeriod($p)) continue;

            $paidCents = $this->resolvePaidCentsFromAdminPayments($accountId, $p);
            if ($paidCents <= 0) continue;

            $paidMxn = round($paidCents / 100, 2);

            $r['charge']      = $paidMxn;
            $r['paid_amount'] = $paidMxn;
            $r['saldo']       = 0.0;
            $r['status']      = 'paid';
            $r['can_pay']     = false;
        }
        unset($r);

        return $rows;
    }

    private function buildPeriodRowsFromClientEstadosCuenta(
        int $accountId,
        array $periods,
        string $payAllowed,
        array $chargesByPeriod,
        ?string $lastPaid
    ): array {
        $rows = [];

        foreach ($periods as $p) {
            if (!$this->isValidPeriod($p)) continue;

            $charge = (float) ($chargesByPeriod[$p] ?? 0.0);
            $isPaid = ($lastPaid && $p === $lastPaid);

            $rows[$p] = [
                'period'                => $p,
                'status'                => $isPaid ? 'paid' : 'pending',
                'charge'                => round($charge, 2),
                'paid_amount'           => $isPaid ? round($charge, 2) : 0.0,
                'saldo'                 => $isPaid ? 0.0 : round($charge, 2),
                'can_pay'               => (!$isPaid && $p === $payAllowed),
                'invoice_request_status'=> null,
                'invoice_has_zip'       => false,
            ];
        }

        // Si en clientes.estados_cuenta existe, manda esa info (cargo/abono/saldo real)
        try {
            $cli = config('p360.conn.clients', 'mysql_clientes');
            if (!Schema::connection($cli)->hasTable('estados_cuenta')) {
                ksort($rows);
                return array_values($rows);
            }

            $items = DB::connection($cli)->table('estados_cuenta')
                ->where('account_id', $accountId)
                ->whereIn('periodo', array_keys($rows))
                ->get(['periodo', 'cargo', 'abono', 'saldo']);

            foreach ($items as $it) {
                $p = $this->parseToPeriod($it->periodo ?? null);
                if (!$p || !isset($rows[$p])) continue;

                $fallbackCharge = (float) ($rows[$p]['charge'] ?? 0.0);

                $cargo = is_numeric($it->cargo ?? null) ? (float) $it->cargo : $fallbackCharge;
                $abono = is_numeric($it->abono ?? null) ? (float) $it->abono : 0.0;
                $saldo = is_numeric($it->saldo ?? null) ? (float) $it->saldo : max(0.0, $cargo - $abono);

                $paid = ($saldo <= 0.0001) || ($cargo > 0 && $abono >= $cargo);

                $rows[$p]['charge']      = round(max(0.0, $cargo), 2);
                $rows[$p]['paid_amount'] = $paid ? round(max(0.0, $abono > 0 ? $abono : $cargo), 2) : 0.0;
                $rows[$p]['saldo']       = $paid ? 0.0 : round(max(0.0, $saldo), 2);
                $rows[$p]['status']      = $paid ? 'paid' : 'pending';
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] buildPeriodRowsFromClientEstadosCuenta failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
        }

        foreach ($rows as $p => $_) {
            $rows[$p]['can_pay'] = (($rows[$p]['status'] ?? 'pending') === 'pending' && $p === $payAllowed);
        }

        ksort($rows);
        return array_values($rows);
    }

    /**
     * ✅ Resuelve monto mensual (cents) por periodo desde Admin meta.billing.
     * Delegado al HUB para evitar divergencias.
     */
    private function resolveMonthlyCentsForPeriodFromAdminAccount(
        int $adminAccountId,
        string $period,
        ?string $lastPaid,
        string $payAllowed
    ): int {
        if (!$this->isValidPeriod($period)) return 0;

        try {
            $cents = (int) $this->hub->resolveMonthlyCentsForPeriodFromAdminAccount(
                $adminAccountId,
                $period,
                $payAllowed
            );

            if ($cents > 0) {
                Log::info('[BILLING] price from HUB meta.billing (resolved)', [
                    'account_id' => $adminAccountId,
                    'period'     => $period,
                    'last_paid'  => $lastPaid,
                    'pay_allowed'=> $payAllowed,
                    'cents'      => $cents,
                ]);
                return $cents;
            }

            return 0;
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolveMonthlyCentsForPeriodFromAdminAccount (HUB) failed', [
                'account_id' => $adminAccountId,
                'period'     => $period,
                'last_paid'  => $lastPaid,
                'pay_allowed'=> $payAllowed,
                'err'        => $e->getMessage(),
            ]);
            return 0;
        }
    }

    // =========================================================
    // Fallback (catálogo planes)
    // =========================================================
    private function resolveMonthlyCentsFromPlanesCatalog(int $accountId): int
    {
        $adm = config('p360.conn.admin', 'mysql_admin');
        if (!Schema::connection($adm)->hasTable('accounts')) return 0;
        if (!Schema::connection($adm)->hasTable('planes')) return 0;

        try {
            $acc = DB::connection($adm)->table('accounts')
                ->select(['id', 'plan', 'plan_actual', 'modo_cobro'])
                ->where('id', $accountId)
                ->first();

            if (!$acc) return 0;

            $planKey = trim((string) ($acc->plan_actual ?: $acc->plan));
            if ($planKey === '') return 0;

            $cycle = strtolower(trim((string) ($acc->modo_cobro ?: 'mensual')));
            $cycle = in_array($cycle, ['anual', 'annual', 'year', 'yearly'], true) ? 'anual' : 'mensual';

            $plan = DB::connection($adm)->table('planes')
                ->select(['clave', 'precio_mensual', 'precio_anual', 'activo'])
                ->where('clave', $planKey)
                ->first();

            if (!$plan) return 0;
            if (isset($plan->activo) && (int) $plan->activo !== 1) return 0;

            $monthly = (float) ($plan->precio_mensual ?? 0);
            $annual  = (float) ($plan->precio_anual ?? 0);

            if ($cycle === 'anual') {
                if ($annual > 0) $monthly = round($annual / 12.0, 2);
            }

            if ($monthly <= 0) return 0;

            return (int) round($monthly * 100);
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolveMonthlyCentsFromPlanesCatalog failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * ==========================
     * FACTURAR (Solicitud)
     * ==========================
     * ✅ Usa admin.billing_invoice_requests (SOT)
     */
    public function requestInvoice(Request $r, string $period): RedirectResponse
    {
        // ==========================================================
        // ✅ Validación periodo
        // ==========================================================
        if (!$this->isValidPeriod($period)) {
            return redirect()->route('cliente.estado_cuenta')
                ->with('invoice_window_error', true)
                ->with('invoice_window_error_msg', 'Periodo inválido para solicitar factura.');
        }

        // ==========================================================
        // ✅ Resolver cuenta admin (SOT)
        // ==========================================================
        [$accountIdRaw, $src] = $this->resolveAdminAccountId($r);
        $accountId = is_numeric($accountIdRaw) ? (int) $accountIdRaw : 0;

        if ($accountId <= 0) {
            return redirect()->route('cliente.estado_cuenta')
                ->with('invoice_window_error', true)
                ->with('invoice_window_error_msg', 'Cuenta no seleccionada.');
        }

        $now      = now();
        $lastPaid = $this->adminLastPaidPeriod($accountId);

        /**
         * ✅ REGLA (SOT):
         * - Solo se puede pedir factura del ÚLTIMO PERIODO PAGADO (lastPaid)
         * - Ventana: MES CALENDARIO de la FECHA REAL DE PAGO (paid_at)
         * - Si NO puedo determinar paid_at => NO permito facturar
         */
        $allowed = false;
        $paidAt  = null;
        $start   = null;
        $end     = null;

        // Debe coincidir exactamente con lastPaid
        if ($lastPaid && $period === $lastPaid) {
            try {
                $paidAt = $this->resolvePaidAtForPeriod($accountId, $lastPaid); // Carbon|null

                if (!$paidAt) {
                    Log::warning('[BILLING] invoice window blocked (paid_at unresolved)', [
                        'account_id' => $accountId,
                        'period'     => $period,
                        'last_paid'  => $lastPaid,
                        'now'        => $now->toDateTimeString(),
                        'src'        => $src,
                    ]);

                    $allowed = false;
                } else {
                    $start   = $paidAt->copy()->startOfMonth();
                    $end     = $paidAt->copy()->endOfMonth();
                    $allowed = $now->betweenIncluded($start, $end);
                }

                // ✅ Log SAFE (no revienta si paidAt es null)
                Log::info('[BILLING][DEBUG] invoice window check', [
                    'account_id' => $accountId,
                    'period'     => $period,
                    'last_paid'  => $lastPaid,
                    'paid_at'    => $paidAt ? $paidAt->toDateTimeString() : null,
                    'window'     => ($start && $end) ? ($start->format('Y-m-d') . ' -> ' . $end->format('Y-m-d')) : null,
                    'now'        => $now->toDateTimeString(),
                    'allowed'    => $allowed,
                    'src'        => $src,
                ]);
            } catch (\Throwable $e) {
                $allowed = false;

                Log::warning('[BILLING] invoice window check failed', [
                    'account_id' => $accountId,
                    'period'     => $period,
                    'last_paid'  => $lastPaid,
                    'src'        => $src,
                    'err'        => $e->getMessage(),
                ]);
            }
        } else {
            // Log leve: intentaron facturar un periodo distinto al último pagado
            Log::info('[BILLING] invoice request blocked (not last paid)', [
                'account_id' => $accountId,
                'period'     => $period,
                'last_paid'  => $lastPaid,
                'now'        => $now->toDateTimeString(),
                'src'        => $src,
            ]);
        }

        if (!$allowed) {
            Log::info('[BILLING] invoice request blocked (out of month / not allowed)', [
                'account_id' => $accountId,
                'period'     => $period,
                'last_paid'  => $lastPaid,
                'paid_at'    => $paidAt ? $paidAt->toDateTimeString() : null,
                'now'        => $now->toDateTimeString(),
                'src'        => $src,
            ]);

            return redirect()->route('cliente.estado_cuenta')
                ->with('invoice_window_error', true)
                ->with('invoice_window_error_msg', 'No se pudo validar la fecha real de pago para este periodo. Contacta soporte para facturación.');
        }

        // ==========================================================
        // ✅ Insert en admin.billing_invoice_requests (SOT)
        // ==========================================================
        $adm = (string) config('p360.conn.admin', 'mysql_admin');

        if (!Schema::connection($adm)->hasTable('billing_invoice_requests')) {
            Log::warning('[BILLING] billing_invoice_requests table missing in admin', [
                'account_id' => $accountId,
                'period'     => $period,
            ]);

            return redirect()->route('cliente.estado_cuenta')
                ->with('invoice_window_error', true)
                ->with('invoice_window_error_msg', 'No se pudo registrar la solicitud (módulo de facturación no disponible).');
        }

        $cols = Schema::connection($adm)->getColumnListing('billing_invoice_requests');
        $lc   = array_map('strtolower', $cols);
        $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('account_id') || !$has('period')) {
            Log::warning('[BILLING] billing_invoice_requests missing account_id/period', [
                'account_id' => $accountId,
                'period'     => $period,
            ]);

            return redirect()->route('cliente.estado_cuenta')
                ->with('invoice_window_error', true)
                ->with('invoice_window_error_msg', 'No se pudo registrar la solicitud (estructura incompleta).');
        }

        // ==========================================================
        // ✅ Evitar duplicados (idempotencia)
        // ==========================================================
        try {
            $orderCol = $has('id') ? 'id' : ($has('created_at') ? 'created_at' : $cols[0]);

            $existing = DB::connection($adm)->table('billing_invoice_requests')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->orderByDesc($orderCol)
                ->first();

            if ($existing) {
                Log::info('[BILLING] invoice request already exists (billing_invoice_requests)', [
                    'account_id' => $accountId,
                    'period'     => $period,
                ]);

                return redirect()->route('cliente.estado_cuenta')
                    ->with('success', 'Tu solicitud de factura ya fue registrada. Aparecerá como “Facturando” mientras se prepara.');
            }
        } catch (\Throwable $e) {
            // no bloquea: solo log
            Log::warning('[BILLING] invoice request dedupe check failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
        }

        // ==========================================================
        // ✅ Insert
        // ==========================================================
        $insert = [
            'account_id' => $accountId,
            'period'     => $period,
        ];

        if ($has('status'))       $insert['status'] = 'requested';
        if ($has('notes'))        $insert['notes'] = null;
        if ($has('requested_at')) $insert['requested_at'] = $now;
        if ($has('created_at'))   $insert['created_at'] = $now;
        if ($has('updated_at'))   $insert['updated_at'] = $now;

        if ($has('meta')) {
            $insert['meta'] = json_encode([
                'source'        => 'cliente_portal',
                'user_id'       => Auth::guard('web')->id(),
                'requested_ip'  => (string) $r->ip(),
                'last_paid'     => $lastPaid,
                'paid_at'       => $paidAt ? $paidAt->toISOString() : null,
                'window_start'  => ($start instanceof \DateTimeInterface) ? Carbon::instance($start)->toDateString() : null,
                'window_end'    => ($end instanceof \DateTimeInterface) ? Carbon::instance($end)->toDateString() : null,
                'resolver_src'  => $src,
            ], JSON_UNESCAPED_UNICODE);
        }

        try {
            DB::connection($adm)->table('billing_invoice_requests')->insert($insert);

            Log::info('[BILLING] invoice request created (billing_invoice_requests)', [
                'account_id' => $accountId,
                'period'     => $period,
                'insert'     => array_keys($insert),
            ]);

            return redirect()->route('cliente.estado_cuenta')
                ->with('success', 'Solicitud de factura enviada. Aparecerá como “Facturando”.');
        } catch (\Throwable $e) {
            Log::error('[BILLING] billing_invoice_requests insert failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);

            return redirect()->route('cliente.estado_cuenta')
                ->with('invoice_window_error', true)
                ->with('invoice_window_error_msg', 'No se pudo registrar la solicitud. Intenta nuevamente.');
        }
    }


    /**
     * ✅ Determina la fecha real de pago (paid_at) para un periodo, de forma robusta.
     */
    private function resolvePaidAtForPeriod(int $accountId, string $period): ?Carbon
    {
        if (!$this->isValidPeriod($period)) return null;

        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        // 1) payments
        try {
            if (Schema::connection($adm)->hasTable('payments')) {
                $cols = Schema::connection($adm)->getColumnListing('payments');
                $lc   = array_map('strtolower', $cols);
                $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

                $dateCols = array_values(array_filter([
                    $has('paid_at') ? 'paid_at' : null,
                    $has('paid_on') ? 'paid_on' : null,
                    $has('confirmed_at') ? 'confirmed_at' : null,
                    $has('captured_at') ? 'captured_at' : null,
                    $has('completed_at') ? 'completed_at' : null,
                    $has('updated_at') ? 'updated_at' : null,
                    $has('created_at') ? 'created_at' : null,
                ]));

                if ($has('account_id') && $has('period') && $dateCols) {
                    $q = DB::connection($adm)->table('payments')
                        ->where('account_id', $accountId)
                        ->where('period', $period);

                    if ($has('status')) {
                        $q->whereIn('status', ['paid', 'succeeded', 'complete', 'completed']);
                    }

                    $orderCol = $dateCols[0];
                    $row = $q->orderByDesc($orderCol)->first($dateCols);

                    if ($row) {
                        foreach ($dateCols as $c) {
                            $v = $row->{$c} ?? null;
                            $p = $this->parseToCarbon($v);
                            if ($p) return $p;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolvePaidAtForPeriod payments failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
        }

        // 2) accounts.meta.stripe.last_paid_at
        try {
            if (Schema::connection($adm)->hasTable('accounts')) {
                $acc = DB::connection($adm)->table('accounts')
                    ->select(['id', 'meta'])
                    ->where('id', $accountId)
                    ->first();

                if ($acc && isset($acc->meta)) {
                    $meta = is_string($acc->meta)
                        ? (json_decode((string)$acc->meta, true) ?: [])
                        : (array)$acc->meta;

                    $v = data_get($meta, 'stripe.last_paid_at');
                    $p = $this->parseToCarbon($v);
                    if ($p) return $p;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolvePaidAtForPeriod meta failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function parseToCarbon(mixed $value): ?Carbon
    {
        try {
            if ($value instanceof \DateTimeInterface) return Carbon::instance($value);
            if (is_numeric($value)) {
                $ts = (int) $value;
                if ($ts > 0) return Carbon::createFromTimestamp($ts);
            }
            if (is_string($value)) {
                $v = trim($value);
                if ($v === '') return null;
                return Carbon::parse($v);
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /**
     * ==========================
     * FACTURA (Descarga ZIP)
     * ==========================
     * ✅ SOT: admin.billing_invoice_requests (con fallback a invoice_requests si existe)
     */
    public function downloadInvoiceZip(Request $r, string $period)
    {
        if (!$this->isValidPeriod($period)) abort(422, 'Periodo inválido.');

        [$accountIdRaw] = $this->resolveAdminAccountId($r);
        $accountId = is_numeric($accountIdRaw) ? (int) $accountIdRaw : 0;
        if ($accountId <= 0) abort(403, 'Cuenta no seleccionada.');

        $lastPaid = $this->adminLastPaidPeriod($accountId);
        if (!$lastPaid) abort(403, 'Factura no disponible para este periodo.');

        try {
            $pReq  = Carbon::createFromFormat('Y-m', $period);
            $pLast = Carbon::createFromFormat('Y-m', $lastPaid);
            if ($pReq->greaterThan($pLast)) abort(403, 'Factura no disponible para este periodo.');
        } catch (\Throwable $e) {
            abort(403, 'Factura no disponible para este periodo.');
        }

        $adm = (string) config('p360.conn.admin', 'mysql_admin');

        // 1) Preferencia: billing_invoice_requests
        $found = $this->findInvoiceZipFromTable($adm, 'billing_invoice_requests', $accountId, $period);
        if (!$found) {
            // 2) Fallback legacy: invoice_requests
            $found = $this->findInvoiceZipFromTable($adm, 'invoice_requests', $accountId, $period);
        }

        if (!$found) abort(404, 'Factura aún no disponible.');

        $disk = (string) ($found['disk'] ?? 'public');
        $path = ltrim((string) ($found['path'] ?? ''), '/');
        $name = (string) ($found['filename'] ?? '');

        if ($path === '') abort(404, 'Factura aún no disponible.');

        $name = trim($name) !== '' ? $name : "Factura_{$period}.zip";
        if (!str_ends_with(strtolower($name), '.zip')) $name .= '.zip';

        if (!Storage::disk($disk)->exists($path)) {
            Log::warning('[BILLING] invoice zip missing on disk', [
                'account_id' => $accountId,
                'period'     => $period,
                'disk'       => $disk,
                'path'       => $path,
            ]);
            abort(404, 'Factura aún no disponible.');
        }

        try {
            try {
                $abs = Storage::disk($disk)->path($path);
                return response()->download($abs, $name, ['Content-Type' => 'application/zip']);
            } catch (\Throwable $e) {
                return Storage::disk($disk)->download($path, $name, ['Content-Type' => 'application/zip']);
            }
        } catch (\Throwable $e) {
            Log::error('[BILLING] invoice zip download failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'disk'       => $disk,
                'path'       => $path,
                'err'        => $e->getMessage(),
            ]);
            abort(500, 'No se pudo descargar la factura.');
        }
    }

    /**
     * Busca ZIP en una tabla dada (admin).
     * Retorna ['disk'=>..., 'path'=>..., 'filename'=>...] o null.
     */
    private function findInvoiceZipFromTable(string $adm, string $table, int $accountId, string $period): ?array
    {
        if (!Schema::connection($adm)->hasTable($table)) return null;

        $cols = Schema::connection($adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        // FK
        $fk = null;
        foreach (['account_id', 'admin_account_id', 'cuenta_id'] as $cand) {
            if ($has($cand)) { $fk = $cand; break; }
        }
        if (!$fk || !$has('period')) return null;

        $orderCol = $has('id') ? 'id' : ($has('created_at') ? 'created_at' : $cols[0]);

        $row = DB::connection($adm)->table($table)
            ->where($fk, $accountId)
            ->where('period', $period)
            ->orderByDesc($orderCol)
            ->first();

        if (!$row) return null;

        // detectores de columnas zip
        $disk = null;
        $path = null;
        $name = null;

        foreach ([
            // ✅ billing_invoice_requests usa zip_name
            ['zip_disk','zip_path','zip_name'],
            ['zip_disk','zip_path','zip_filename'],
            ['zip_disk','zip_path','filename'],

            // compat genérico
            ['file_disk','file_path','file_name'],
            ['disk','zip_path','zip_name'],
            ['disk','zip_path','zip_filename'],
            ['disk','file_path','filename'],

            // otros esquemas
            ['factura_disk','factura_path','factura_filename'],
        ] as $set) {

            [$cd,$cp,$cn] = $set;
            if ($has($cp)) {
                $p = (string) ($row->{$cp} ?? '');
                if ($p !== '') {
                    $path = $p;
                    $disk = $has($cd) ? (string) ($row->{$cd} ?? '') : '';
                    $name = $has($cn) ? (string) ($row->{$cn} ?? '') : '';
                    break;
                }
            }
        }

        if (($path ?? '') === '') {
            foreach (['zip', 'zip_file', 'zipfile', 'path', 'ruta_zip'] as $cp) {
                if ($has($cp) && !empty($row->{$cp})) {
                    $path = (string) $row->{$cp};
                    break;
                }
            }
        }

        $disk = trim((string)($disk ?? '')) !== '' ? (string)$disk : 'public';
        $path = (string)($path ?? '');
        $name = (string)($name ?? '');

        if (trim($path) === '') return null;

        return [
            'disk'     => $disk,
            'path'     => ltrim($path, '/'),
            'filename' => $name,
        ];
    }

    /**
     * PDF autenticado -> redirige a link público firmado (descarga)
     */
    public function pdf(Request $r, string $period): RedirectResponse
    {
        if (!$this->isValidPeriod($period)) abort(422, 'Periodo inválido.');

        [$accountIdRaw] = $this->resolveAdminAccountId($r);
        $accountId = is_numeric($accountIdRaw) ? (int) $accountIdRaw : 0;
        if ($accountId <= 0) abort(403, 'Cuenta no seleccionada.');

        $url = URL::temporarySignedRoute(
            'cliente.billing.publicPdf',
            now()->addMinutes(30),
            ['accountId' => $accountId, 'period' => $period]
        );

        return redirect()->away($url);
    }

    public function publicPdf(Request $r, int $accountId, string $period)
    {
        if (!$this->isValidPeriod($period)) abort(422, 'Periodo inválido.');
        if (!Auth::guard('web')->check() && !$r->hasValidSignature()) abort(403, 'Link inválido o expirado.');

        // ✅ Si hay sesión, evita cross-account
        try {
            [$sessAccountIdRaw] = $this->resolveAdminAccountId($r);
            $sessAccountId = is_numeric($sessAccountIdRaw) ? (int) $sessAccountIdRaw : 0;
            if (Auth::guard('web')->check() && $sessAccountId > 0 && $sessAccountId !== (int) $accountId) {
                abort(403, 'Cuenta no autorizada.');
            }
        } catch (\Throwable $e) {
            // ignora
        }

        $forceInline = ((string) $r->query('inline', '0') === '1');

        // ✅ FORZAR REGENERACIÓN (bypass PDF almacenado)
        // Usa ?regen=1 para ignorar billing_views y renderizar el Blade nuevo
        $regen = ((string) $r->query('regen', '0') === '1');

        // ✅ Ciclo de cobro (necesario también para nombre de archivo cuando se sirve PDF guardado)
        $isAnnual = $this->isAnnualBillingCycle((int) $accountId);

        // ==========================================================
        // 1) Si existe PDF guardado (admin.billing_views), servirlo
        // ==========================================================
        if (!$regen) {
            $found = $this->findAdminPdfForPeriod((int) $accountId, $period);
            if ($found && !empty($found['disk']) && !empty($found['path'])) {
                $disk  = (string) $found['disk'];
                $path  = (string) $found['path'];
                $fname = (string) ($found['filename'] ?? ($isAnnual ? "estado_cuenta_anual_{$period}.pdf" : "estado_cuenta_{$period}.pdf"));

                try {
                    if (Storage::disk($disk)->exists($path)) {
                        try {
                            $abs = Storage::disk($disk)->path($path);

                            if ($forceInline) {
                                return response()->file($abs, [
                                    'Content-Type'        => 'application/pdf',
                                    'Content-Disposition' => 'inline; filename="'.$fname.'"',
                                ]);
                            }

                            return response()->download($abs, $fname, ['Content-Type' => 'application/pdf']);
                        } catch (\Throwable $e) {
                            $headers = [
                                'Content-Type'        => 'application/pdf',
                                'Content-Disposition' => ($forceInline ? 'inline' : 'attachment') . '; filename="'.$fname.'"',
                            ];
                            return Storage::disk($disk)->response($path, $fname, $headers);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('[BILLING] serve admin pdf failed', [
                        'disk' => $disk,
                        'path' => $path,
                        'err'  => $e->getMessage(),
                    ]);
                }
            }
        } else {
            Log::info('[BILLING] publicPdf regen bypass enabled', [
                'account_id' => (int) $accountId,
                'period'     => $period,
                'inline'     => $forceInline,
            ]);
        }

        // ==========================================================
        // 2) Fallback: renderizar Blade actual (DomPDF) / HTML simple
        // ==========================================================
        [$rfc, $alias] = $accountId > 0 ? $this->resolveRfcAliasForUi($r, (int) $accountId) : ['—', '—'];

        $lastPaid = $this->adminLastPaidPeriod((int) $accountId);

        $payAllowed = $lastPaid
            ? (
                $isAnnual
                    ? Carbon::createFromFormat('Y-m', $lastPaid)->addYearNoOverflow()->format('Y-m')
                    : Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
            )
            : $period;

        // ✅ cargo del periodo (cents)
        // - Mensual: mensualidad
        // - Anual: total anual (NO mensual*12)
        $chargeCents = 0;

        if ($isAnnual) {
            $baseAnnual  = $lastPaid ?: $period;
            $chargeCents = (int) $this->resolveAnnualCents((int) $accountId, (string) $baseAnnual, $lastPaid, $payAllowed);
        } else {
            $chargeCents = (int) $this->resolveMonthlyCentsForPeriodFromAdminAccount((int) $accountId, $period, $lastPaid, $payAllowed);
            if ($chargeCents <= 0 && $accountId > 0) $chargeCents = (int) $this->resolveMonthlyCentsFromPlanesCatalog((int) $accountId);
            if ($chargeCents <= 0 && $accountId > 0) $chargeCents = (int) $this->resolveMonthlyCentsFromClientesEstadosCuenta((int) $accountId, null, $period);
        }

        $chargeMxn = round($chargeCents / 100, 2);

        // ==========================================================
        // ✅ Saldo anterior (periodo - 1) — se mantiene igual incluso en anual
        // ==========================================================
        $prevBalanceCents = (int) $this->resolvePrevBalanceCentsForPeriod((int) $accountId, $period);
        $prevBalanceMxn   = round($prevBalanceCents / 100, 2);

        $prevPeriod = '';
        try {
            $prevPeriod = Carbon::createFromFormat('Y-m', $period)->subMonthNoOverflow()->format('Y-m');
        } catch (\Throwable $e) {
            $prevPeriod = '';
        }

        $prevPeriodLabel = $prevPeriod;
        try {
            if ($prevPeriod !== '' && preg_match('/^\d{4}-\d{2}$/', $prevPeriod)) {
                $prevPeriodLabel = Carbon::parse($prevPeriod.'-01')->translatedFormat('F Y');
                $prevPeriodLabel = Str::ucfirst($prevPeriodLabel);
            }
        } catch (\Throwable $e) {
            $prevPeriodLabel = $prevPeriod;
        }

        $currentDueMxn = (float) $chargeMxn;
        $totalDueMxn   = round($currentDueMxn + $prevBalanceMxn, 2);

        // ==========================================================
        // ✅ Detectar si el PERIODO ACTUAL está pagado (NO el total)
        // - Query a accounts defensiva (columnas variables)
        // ==========================================================
        $isPaid = false;
        $acc    = null;

        try {
            $adm = config('p360.conn.admin', 'mysql_admin');

            if (Schema::connection($adm)->hasTable('accounts')) {
                $cols = Schema::connection($adm)->getColumnListing('accounts');
                $lc   = array_map('strtolower', $cols);
                $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

                $sel = ['id'];
                foreach (['email','razon_social','rfc','meta'] as $c) {
                    if ($has($c)) $sel[] = $c;
                }
                $sel = array_values(array_unique($sel));

                $acc = DB::connection($adm)->table('accounts')
                    ->select($sel)
                    ->where('id', (int) $accountId)
                    ->first();

                // Solo si hay meta usable
                if ($acc && $has('meta') && isset($acc->meta)) {
                    $meta = is_string($acc->meta)
                        ? (json_decode((string) $acc->meta, true) ?: [])
                        : (array) $acc->meta;

                    $lp = trim((string) data_get($meta, 'stripe.last_paid_period', ''));
                    if ($lp !== '' && $this->isValidPeriod($lp)) {
                        $isPaid = ($lp === $period);
                    } else {
                        $lastPaidAt = trim((string) data_get($meta, 'stripe.last_paid_at', ''));
                        if ($lastPaidAt !== '') {
                            try {
                                $p = Carbon::parse($lastPaidAt)->format('Y-m');
                                $isPaid = ($p === $period);
                            } catch (\Throwable $e) {
                                // ignora
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignora
        }

        // ==========================================================
        // ✅ QR payload: preferir URL firmada de pago si existe
        // ==========================================================
        $qrPayload = 'P360|ACC:'.$accountId.'|PER:'.$period;

        if (Route::has('cliente.billing.publicPay')) {
            try {
                $payUrl = URL::temporarySignedRoute(
                    'cliente.billing.publicPay',
                    now()->addMinutes(30),
                    ['accountId' => $accountId, 'period' => $period]
                );
                $qrPayload = (string) $payUrl;
            } catch (\Throwable $e) {
                // ignora
            }
        }

        // ==========================================================
        // ✅ Logo: rutas candidatas (compat)
        // ==========================================================
        $logoAbsCandidates = [
            public_path('assets/client/logp360ligjt.png'),   // compat (typo histórico)
            public_path('assets/client/logo_p360_light.png'),
            public_path('assets/client/logo-p360-light.png'),
            public_path('assets/client/logo.png'),
        ];

        $logoAbs = null;
        foreach ($logoAbsCandidates as $c) {
            if (is_string($c) && $c !== '' && is_file($c) && is_readable($c)) { $logoAbs = $c; break; }
        }

        $logoDataUri = $logoAbs ? $this->imgToDataUri($logoAbs) : null;
        $qrDataUri   = $this->qrToDataUri($qrPayload, 150);

        // ✅ Email del account (si existe) / si no, usa el del usuario logueado / si no, —.
        $email = '—';
        try {
            $email = (string) (
                ($acc && isset($acc->email) && trim((string) $acc->email) !== '') ? (string) $acc->email : (
                    Auth::guard('web')->user()?->email ?: '—'
                )
            );
        } catch (\Throwable $e) {
            $email = Auth::guard('web')->user()?->email ?: '—';
        }

        // ==========================================================
        // ✅ FIX: etiqueta del servicio (mensual vs anual) + compat legacy
        // - Evita que el Blade caiga en fallback "Servicio mensual"
        // ==========================================================
        $serviceLabel   = $isAnnual ? 'Servicio anual' : 'Servicio mensual';
        $consumosCompat = [[
            'concepto' => $serviceLabel,
            'costo'    => (float) $chargeMxn,
            'cantidad' => 1,
            'subtotal' => (float) $chargeMxn,
        ]];

        $data = [
            'period'       => $period,
            'account_id'   => (int) $accountId,
            'rfc'          => $rfc,
            'razon_social' => $alias,
            'email'        => $email,

            // ✅ Estado de cuenta: anterior + actual
            'prev_period'        => $prevPeriod !== '' ? $prevPeriod : null,
            'prev_period_label'  => ($prevPeriodLabel !== '' ? $prevPeriodLabel : ($prevPeriod !== '' ? $prevPeriod : null)),
            'prev_balance'       => (float) $prevBalanceMxn,
            'current_period_due' => (float) $currentDueMxn,
            'total_due'          => (float) $totalDueMxn,

            // Compat (tu PDF usa estos campos)
            'total' => (float) $totalDueMxn,                    // Total a pagar (incluye saldo anterior)
            'cargo' => (float) $currentDueMxn,                  // Cargo del periodo (mensual/anual)
            'abono' => $isPaid ? (float) $currentDueMxn : 0.0,  // Si el periodo actual ya está pagado
            'saldo' => (float) $currentDueMxn,                  // legacy fallback

            // ✅ SOT explícito: etiqueta del servicio
            'service_label' => $serviceLabel,

            // ✅ Compat legacy: si el Blade usa "consumos"
            'consumos' => $consumosCompat,

            // ✅ Formato moderno: si el Blade usa "service_items"
            'service_items' => array_values(array_filter([
                ($prevBalanceMxn > 0.0001 && $prevPeriod !== '') ? [
                    'name'       => 'Saldo anterior pendiente ('.$prevPeriod.')',
                    'unit_price' => (float) $prevBalanceMxn,
                    'qty'        => 1,
                    'subtotal'   => (float) $prevBalanceMxn,
                ] : null,
                [
                    'name'       => $serviceLabel.' · '.$period,
                    'unit_price' => (float) $currentDueMxn,
                    'qty'        => 1,
                    'subtotal'   => (float) $currentDueMxn,
                ],
            ])),

            'generated_at'  => now()->toISOString(),
            'due_at'        => now()->addDays(4)->toISOString(),
            'logo_data_uri' => $logoDataUri,
            'qr_data_uri'   => $qrDataUri,
        ];

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cliente.billing.pdf.statement', $data);

            if ($forceInline) {
                return $pdf->stream(($isAnnual ? "estado_cuenta_anual_{$period}.pdf" : "estado_cuenta_{$period}.pdf"), ['Attachment' => false]);
            }
            return $pdf->download(($isAnnual ? "estado_cuenta_anual_{$period}.pdf" : "estado_cuenta_{$period}.pdf"));
        }

        return response($this->renderSimplePdfHtml([
            'period' => $period,
            'rfc'    => $rfc,
            'alias'  => $alias,
            'status' => $isPaid ? 'PAGADO' : 'PENDIENTE',
            'amount' => $chargeMxn,
            'range'  => Carbon::createFromFormat('Y-m', $period)->startOfMonth()->format('d/m/Y')
                . ' - ' . Carbon::createFromFormat('Y-m', $period)->endOfMonth()->format('d/m/Y'),
        ]))->header('Content-Type', 'text/html; charset=UTF-8');
    }



    public function publicPdfInline(Request $r, int $accountId, string $period)
    {
        if (!$this->isValidPeriod($period)) abort(422, 'Periodo inválido.');
        if (!Auth::guard('web')->check() && !$r->hasValidSignature()) abort(403, 'Link inválido o expirado.');

        try {
            [$sessAccountIdRaw] = $this->resolveAdminAccountId($r);
            $sessAccountId = is_numeric($sessAccountIdRaw) ? (int) $sessAccountIdRaw : 0;
            if (Auth::guard('web')->check() && $sessAccountId > 0 && $sessAccountId !== (int) $accountId) {
                abort(403, 'Cuenta no autorizada.');
            }
        } catch (\Throwable $e) {
            // ignora
        }

        $r->merge(['inline' => '1']);
        return $this->publicPdf($r, $accountId, $period);
    }

    /**
     * ==========================
     * ✅ PAGO PÚBLICO FIRMADO (Stripe Checkout)
     * ==========================
     */
    public function publicPay(Request $r, int $accountId, string $period)
    {
        if (!$this->isValidPeriod($period)) abort(422, 'Periodo inválido.');
        if (!Auth::guard('web')->check() && !$r->hasValidSignature()) abort(403, 'Link inválido o expirado.');

        // Si hay sesión autenticada, evita cross-account.
        try {
            [$sessAccountIdRaw] = $this->resolveAdminAccountId($r);
            $sessAccountId = is_numeric($sessAccountIdRaw) ? (int) $sessAccountIdRaw : 0;
            if (Auth::guard('web')->check() && $sessAccountId > 0 && $sessAccountId !== (int) $accountId) {
                abort(403, 'Cuenta no autorizada.');
            }
        } catch (\Throwable $e) {
            // ignora
        }

        // Backward-compat: si alguien llega con st=success/cancel, solo informa
        $st = strtolower((string) $r->query('st', ''));
        if (in_array($st, ['success', 'cancel'], true)) {
            if ($st === 'success') {
                return redirect()->route('cliente.estado_cuenta', ['period' => $period])
                    ->with('success', 'Pago iniciado. En cuanto se confirme, se actualizará tu estado de cuenta.');
            }
            return redirect()->route('cliente.estado_cuenta', ['period' => $period])
                ->with('warning', 'Pago cancelado.');
        }

        // Regla: solo permitir pagar el "payAllowed"
        $lastPaid = $this->adminLastPaidPeriod((int) $accountId);
        $isAnnual = $this->isAnnualBillingCycle((int) $accountId);

        // basePeriod = contract start si existe, si no el periodo actual
        $basePeriod = $this->resolveContractStartPeriod((int) $accountId);
        $basePeriod = (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $basePeriod))
            ? (string) $basePeriod
            : now()->format('Y-m');

        $statementRefs = $this->buildStatementRefs((int) $accountId);
        $payAllowed = $this->computePayAllowed((int) $accountId, (bool) $isAnnual, (string) $basePeriod, $lastPaid, $statementRefs);

        // Si anual fuera de ventana y no hay pendientes, bloquea pago
        if ($payAllowed === null) {
            return redirect()->route('cliente.estado_cuenta')
                ->with('warning', 'Aún no está abierta la ventana de renovación.');
        }



        // ✅ Permitir pagar SOLO periodos pendientes según admin.billing_statements (si existen)
        $pend = $this->loadRowsFromAdminBillingStatements($statementRefs, 36);

        if (!empty($pend)) {
            $pendPeriods = [];
            foreach ($pend as $rr) {
                if (($rr['status'] ?? '') === 'pending' && !empty($rr['period'])) {
                    $pendPeriods[(string)$rr['period']] = true;
                }
            }

            if (!isset($pendPeriods[$period])) {
                Log::warning('[BILLING] publicPay blocked (period not pending)', [
                    'account_id' => $accountId,
                    'period'     => $period,
                ]);

                return redirect()->route('cliente.estado_cuenta')
                    ->with('warning', 'Este periodo no está pendiente o no está habilitado para pago.');
            }
        } else {
            // Fallback viejo si no hay statements
            if ($period !== $payAllowed) {
                Log::warning('[BILLING] publicPay blocked (period not allowed)', [
                    'account_id'  => $accountId,
                    'period'      => $period,
                    'last_paid'   => $lastPaid,
                    'pay_allowed' => $payAllowed,
                ]);

                return redirect()->route('cliente.estado_cuenta', ['period' => $period])
                    ->with('warning', 'Este periodo no está habilitado para pago.');
            }
        }


        if ($isAnnual) {
            $baseAnnual  = $lastPaid ?: $period;
            $annualCents = (int) $this->resolveAnnualCents((int)$accountId, (string)$baseAnnual, $lastPaid, $payAllowed);
            $monthlyCents = $annualCents; // para no reescribir todo el flujo abajo
        } else {

            $monthlyCents = $this->resolveMonthlyCentsForPeriodFromAdminAccount((int)$accountId, $period, $lastPaid, $payAllowed);
            if ($monthlyCents <= 0) $monthlyCents = $this->resolveMonthlyCentsFromPlanesCatalog((int) $accountId);
            if ($monthlyCents <= 0) $monthlyCents = $this->resolveMonthlyCentsFromClientesEstadosCuenta((int) $accountId, $lastPaid, $payAllowed);
        }

        if ($monthlyCents <= 0) {
            Log::error('[BILLING] publicPay blocked (amount unresolved)', [
                'account_id'  => $accountId,
                'period'      => $period,
                'last_paid'   => $lastPaid,
                'pay_allowed' => $payAllowed,
            ]);

            return redirect()->route('cliente.estado_cuenta', ['period' => $period])
                ->with('warning', 'No se pudo determinar el monto a pagar. Contacta soporte.');
        }

        // ==========================================================
        // ✅ STRIPE MINIMUM (MXN)
        // Stripe no permite Checkout por debajo de $10.00 MXN (1000 cents).
        // Si tu sistema quiere mostrar $1.00, aquí seguimos guardando ambos:
        // - ui/original: $monthlyCents (ej. 100)
        // - cobro real en Stripe: $stripeCents (mínimo 1000)
        // ==========================================================
        $prevBalanceCents = $this->resolvePrevBalanceCentsForPeriod((int)$accountId, $period);

        // ✅ total a pagar = mensualidad actual + saldo anterior (solo 1 periodo anterior)
        $originalCents = (int) max(0, (int)$monthlyCents + (int)$prevBalanceCents);

        // Stripe mínimo
        $stripeCents   = (int) max($originalCents, 1000);

        $amountMxnOriginal = round($originalCents / 100, 2);
        $amountMxnStripe   = round($stripeCents / 100, 2);

        // ✅ Si no hay nada que cobrar, no mandes a Stripe (evita cobrar mínimo cuando debe 0)
        if ($originalCents <= 0) {
            return redirect()->route('cliente.estado_cuenta', ['period' => $period])
                ->with('success', 'No hay saldo pendiente para pagar en este periodo.');
        }

        // Email del cliente (si está logueado) / fallback admin.accounts.email
        $customerEmail = Auth::guard('web')->user()?->email;
        if (!$customerEmail) {
            try {
                $adm = config('p360.conn.admin', 'mysql_admin');
                if (Schema::connection($adm)->hasTable('accounts')) {
                    $acc = DB::connection($adm)->table('accounts')->select(['id', 'email'])->where('id', (int)$accountId)->first();
                    $customerEmail = $acc?->email ?: null;
                }
            } catch (\Throwable $e) {
                // ignora
            }
        }

        // ✅ CRÍTICO: success debe ir a StripeController@success con session_id
        $successUrl = route('cliente.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl  = route('cliente.estado_cuenta') . '?period=' . urlencode($period);

        // ✅ Lazy Stripe: NO revienta si stripe/stripe-php no está instalado o falta el secret
        $stripe = $this->stripe();
        if (!$stripe) {
            Log::error('[BILLING] Stripe not available (missing package or secret)', [
                'account_id'   => $accountId,
                'period'       => $period,
                'has_secret'   => trim((string) config('services.stripe.secret')) !== '',
                'class_exists' => class_exists(\Stripe\StripeClient::class),
            ]);

            return redirect()->route('cliente.estado_cuenta', ['period' => $period])
                ->with('warning', 'No se pudo iniciar el pago (Stripe no disponible). Contacta soporte.');
        }

        try {
            $idempotencyKey = 'publicPay:' . $accountId . ':' . $period . ':' . Str::uuid()->toString();

            // ✅ Arma payload sin enviar customer_email=null (Stripe a veces valida string)
            $payload = [
                'mode' => 'payment',
                'payment_method_types' => ['card'],

                'success_url' => (string) $successUrl,
                'cancel_url'  => (string) $cancelUrl,

                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => 'mxn',
                        'unit_amount' => (int) $stripeCents, // ✅ COBRO REAL
                        'product_data' => [
                            'name' => 'Pactopia360 · Licencia · '.$period,
                            'description' => ($originalCents < 1000)
                               ? ('Stripe requiere mínimo $10.00 MXN. Cobro aplicado: $'.number_format($amountMxnStripe, 2).' MXN (monto sistema: $'.number_format($amountMxnOriginal, 2).' MXN).')
                                : ('Pago de licencia (periodo '.$period.').'),
                        ],
                    ],
                ]],

                // ✅ metadata.type debe empezar con "billing_"
                'metadata' => [
                    'type'                => 'billing_statement_public',
                    'account_id'          => (string)$accountId,
                    'period'              => $period,
                    'amount_mxn'          => (string)$amountMxnOriginal,
                    'amount_cents'        => (string)$originalCents,
                    'stripe_amount_mxn'   => (string)$amountMxnStripe,
                    'stripe_amount_cents' => (string)$stripeCents,
                    'source'              => 'cliente_publicPay',
                ],
            ];

            if ($customerEmail && filter_var((string)$customerEmail, FILTER_VALIDATE_EMAIL)) {
                $payload['customer_email'] = (string) $customerEmail;
            }

            $session = $stripe->checkout->sessions->create($payload, [
                'idempotency_key' => $idempotencyKey,
            ]);

            // Guardamos en admin.payments el COBRO REAL (stripeCents) y en meta dejamos el original
            $this->upsertPendingPaymentForStatementPublicPay(
                (string)$accountId,
                $period,
                (int)$stripeCents,
                (string)($session->id ?? ''),
                (float)$amountMxnOriginal,
                (int)$originalCents
            );

            if (!empty($session->url)) {
                // Nota: si hubo “mínimo Stripe”, avisamos al usuario antes de mandarlo al checkout
                if ($originalCents < 1000) {
                    $r->session()->flash('warning', 'Stripe requiere un mínimo de $10.00 MXN; se enviará a pago por $'.number_format($amountMxnStripe, 2).' MXN.');
                 }
                 return redirect()->away((string) $session->url);
            }

            return redirect()->route('cliente.estado_cuenta', ['period' => $period])
                ->with('warning', 'No se pudo iniciar el checkout. Intenta nuevamente.');
        } catch (\Throwable $e) {
            Log::error('[BILLING] publicPay checkout failed', [
                'account_id'         => $accountId,
                'period'             => $period,
                'amount_cents_ui'    => $originalCents,
                'amount_cents_stripe'=> $stripeCents,
                'err'                => $e->getMessage(),
            ]);

            return redirect()->route('cliente.estado_cuenta', ['period' => $period])
                ->with('warning', 'Error al iniciar pago: '.$e->getMessage());
        }
    }

    /**
     * Inserta/actualiza payments (admin) con status=pending (Stripe Checkout creado).
     */
    private function upsertPendingPaymentForStatementPublicPay(
        string $accountId,
        string $period,
        int $amountCentsStripe,
        string $sessionId,
        float $uiTotalMxn,
        int $amountCentsOriginal = 0
    ): void {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (!Schema::connection($adm)->hasTable('payments')) return;

        $cols = Schema::connection($adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        $existing = null;

        if ($sessionId !== '' && $has('stripe_session_id')) {
            $existing = DB::connection($adm)->table('payments')
                ->where('account_id', $accountId)
                ->where('stripe_session_id', $sessionId)
                ->first();
        }

        if (!$existing && $has('period') && $has('status')) {
            $q = DB::connection($adm)->table('payments')
                ->where('account_id', $accountId)
                ->where('status', 'pending')
                ->where('period', $period);

            if ($has('provider')) $q->where('provider', 'stripe');
            $existing = $q->orderByDesc($has('id') ? 'id' : $cols[0])->first();
        }

        $row = [];
        if ($has('account_id')) $row['account_id'] = $accountId;

        // ✅ amount = COBRO REAL (Stripe)
        if ($has('amount'))     $row['amount']     = (int) $amountCentsStripe;

        if ($has('currency'))   $row['currency']   = 'MXN';
        if ($has('status'))     $row['status']     = 'pending';
        if ($has('due_date'))   $row['due_date']   = now();

        if ($has('period'))     $row['period']     = $period;
        if ($has('method'))     $row['method']     = 'card';
        if ($has('provider'))   $row['provider']   = 'stripe';
        if ($has('concept'))    $row['concept']    = 'Pactopia360 · Estado de cuenta ' . $period;
        if ($has('reference'))  $row['reference']  = $sessionId ?: ('publicPay:' . $accountId . ':' . $period);

        if ($has('stripe_session_id')) $row['stripe_session_id'] = $sessionId;

        if ($has('meta')) {
            $row['meta'] = json_encode([
                'type'               => 'billing_statement_public',
                'period'             => $period,
                'ui_total_mxn'       => round($uiTotalMxn, 2),

                // ✅ trazabilidad: original vs cobrado
                'ui_amount_cents'    => $amountCentsOriginal > 0 ? (int)$amountCentsOriginal : null,
                'stripe_amount_cents'=> (int)$amountCentsStripe,

                'source'             => 'cliente_publicPay',
            ], JSON_UNESCAPED_UNICODE);
        }

        if ($has('updated_at')) $row['updated_at'] = now();
        if (!$existing && $has('created_at')) $row['created_at'] = now();

        if ($existing && $has('id')) {
            DB::connection($adm)->table('payments')->where('id', (int)$existing->id)->update($row);
        } else {
            DB::connection($adm)->table('payments')->insert($row);
        }
    }

    // =========================
    // Helpers (2 cards)
    // =========================
    /**
     * ✅ Portal cliente: solo mostrar el periodo habilitado para pago (payAllowed).
     * - Si payAllowed viene pagado => retorna [] (nada pendiente).
     * - Nunca muestra periodos "paid" ni futuros.
     */
    private function keepOnlyPayAllowedPeriod(array $rows, string $payAllowed): array
    {
        if (!$this->isValidPeriod($payAllowed)) return [];

        $picked = null;

        foreach ($rows as $r) {
            if ((string)($r['period'] ?? '') !== $payAllowed) continue;
            $picked = $r;
            break;
        }

        if (!$picked) return [];

        $st = strtolower((string)($picked['status'] ?? 'pending'));
        if ($st === 'paid') return [];

        // fuerza can_pay coherente (solo este periodo)
        $picked['can_pay'] = true;

        return [$picked];
    }

    
    private function enforceTwoCardsOnly(array $rows, ?string $lastPaid, string $payAllowed, float $monthlyMxn, bool $isAnnual = false): array
    {
        $valid = array_values(array_filter($rows, function ($r) {
            $p = (string) ($r['period'] ?? '');
            return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p);
        }));

       if (!$valid) {
            $baseRfc   = '—';
            $baseAlias = '—';

            $out = [[
                'period'                => $payAllowed,
                'status'                => 'pending',
                'charge'                => round($monthlyMxn, 2),
                'paid_amount'           => 0.0,
                'saldo'                 => round($monthlyMxn, 2),
                'can_pay'               => true,
                'period_range'          => '',
                'rfc'                   => $baseRfc,
                'alias'                 => $baseAlias,
                'invoice_request_status'=> null,
                'invoice_has_zip'       => false,
                'price_source'          => 'none',
            ]];

            // ✅ ANUAL: solo 1 card
            if ($isAnnual) return $out;

            // Mensual: mantiene 2 cards (siguiente mes)
            $next = Carbon::createFromFormat('Y-m', $payAllowed)->addMonthNoOverflow()->format('Y-m');
            $out[] = [
                'period'                => $next,
                'status'                => 'pending',
                'charge'                => round($monthlyMxn, 2),
                'paid_amount'           => 0.0,
                'saldo'                 => round($monthlyMxn, 2),
                'can_pay'               => false,
                'period_range'          => '',
                'rfc'                   => $baseRfc,
                'alias'                 => $baseAlias,
                'invoice_request_status'=> null,
                'invoice_has_zip'       => false,
                'price_source'          => 'none',
            ];

            return array_slice($out, 0, 2);
        }

        usort($valid, function ($a, $b) use ($lastPaid, $payAllowed) {
            $pa = (string) ($a['period'] ?? '');
            $pb = (string) ($b['period'] ?? '');

            if ($lastPaid && $pa === $lastPaid && $pb !== $lastPaid) return -1;
            if ($lastPaid && $pb === $lastPaid && $pa !== $lastPaid) return 1;

            if ($pa === $payAllowed && $pb !== $payAllowed) return 1;
            if ($pb === $payAllowed && $pa !== $payAllowed) return -1;

            return $pa <=> $pb;
        });

        $want = [];
        if ($lastPaid) $want[] = $lastPaid;
        $want[] = $payAllowed;
        $want = array_values(array_unique(array_filter($want)));

        $out = [];
        $seen = [];

        foreach ($want as $wp) {
            foreach ($valid as $r) {
                if ((string) ($r['period'] ?? '') === $wp && !isset($seen[$wp])) {
                    $seen[$wp] = true;
                    $out[] = $r;
                    break;
                }
            }
        }

        $baseRfc = (string)($out[0]['rfc'] ?? $valid[0]['rfc'] ?? '—');
        $baseAlias = (string)($out[0]['alias'] ?? $valid[0]['alias'] ?? '—');

        if (!$isAnnual && count($out) < 2) {
            $next = Carbon::createFromFormat('Y-m', $payAllowed)->addMonthNoOverflow()->format('Y-m');
            $out[] = [
                'period' => $next,
                'status' => 'pending',
                'charge' => round($monthlyMxn, 2),
                'paid_amount' => 0.0,
                'saldo' => round($monthlyMxn, 2),
                'can_pay' => false,
                'period_range' => '',
                'rfc' => $baseRfc,
                'alias' => $baseAlias,
                'invoice_request_status' => null,
                'invoice_has_zip' => false,
                'price_source' => 'none',
            ];
        }

        return $isAnnual ? array_slice($out, 0, 1) : array_slice($out, 0, 2);
    }

    /**
     * ✅ SOT UI: obtiene filas desde admin.billing_statements.
     * Regresa rows con: period,status,charge,paid_amount,saldo,can_pay,due_date,paid_at
     *
     * ⚠️ IMPORTANTE (PROD):
     * - billing_statements.account_id puede ser INT (admin_account_id) o UUID (cuentas_cliente.id).
     * - Acepta refs (int|string|array) y consulta con whereIn.
     */
    private function loadRowsFromAdminBillingStatements($accountId, int $limit = 24): array
    {
        $adm = (string) config('p360.conn.admin', 'mysql_admin');
        if (!Schema::connection($adm)->hasTable('billing_statements')) return [];

        // =========================================================
        // ✅ NORMALIZA REFS:
        // - si viene array, se interpreta como lista de refs (uuid/int/string)
        // - si viene escalar, se vuelve lista de 1
        // =========================================================
        $refs = [];

        try {
            if (is_array($accountId)) {
                // Caso raro: array asociativo tipo ['account_id'=>3]
                if (array_key_exists('account_id', $accountId) || array_key_exists('id', $accountId)) {
                    $v = $accountId['account_id'] ?? $accountId['id'] ?? null;
                    if ($v !== null) $refs[] = $v;
                } else {
                    // Lista real: [3,"uuid",...]
                    foreach ($accountId as $v) $refs[] = $v;
                }
            } else {
                $refs[] = $accountId;
            }

            // normaliza a strings limpios
            $refs = array_values(array_unique(array_map(fn ($x) => trim((string) $x), $refs)));
            $refs = array_values(array_filter($refs, fn ($x) => $x !== ''));

        } catch (\Throwable $e) {
            $refs = [];
        }

        if (empty($refs)) return [];

        // =========================================================
        // ✅ EXPANDE: si hay admin_account_id numérico, agrega UUIDs ligados
        // (esto asegura que aunque manden solo "3" se incluyan UUIDs)
        // =========================================================
        try {
            $adminIds = [];
            foreach ($refs as $v) {
                if (preg_match('/^\d+$/', $v)) $adminIds[] = (int) $v;
            }
            $adminIds = array_values(array_unique(array_filter($adminIds, fn ($x) => $x > 0)));

            if (!empty($adminIds)) {
                $cli = (string) config('p360.conn.clientes', 'mysql_clientes');
                if (Schema::connection($cli)->hasTable('cuentas_cliente') && Schema::connection($cli)->hasColumn('cuentas_cliente', 'admin_account_id')) {
                    $uuids = DB::connection($cli)->table('cuentas_cliente')
                        ->whereIn('admin_account_id', $adminIds)
                        ->pluck('id')
                        ->map(fn ($x) => trim((string) $x))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    if (!empty($uuids)) {
                        $refs = array_values(array_unique(array_merge($refs, $uuids)));
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $cols = Schema::connection($adm)->getColumnListing('billing_statements');
            $lc   = array_map('strtolower', $cols);
            $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

            if (!$has('account_id') || !$has('period')) return [];

            $sel = ['account_id', 'period'];
            foreach (['total_cargo', 'total_abono', 'saldo', 'status', 'due_date', 'paid_at'] as $c) {
                if ($has($c)) $sel[] = $c;
            }

            $orderCol = $has('period')
                ? 'period'
                : ($has('id') ? 'id' : ($has('created_at') ? 'created_at' : $cols[0]));

            $items = DB::connection($adm)->table('billing_statements')
                ->whereIn('account_id', $refs)
                ->orderByDesc($orderCol)
                ->limit(max(1, $limit))
                ->get($sel);

            Log::info('[BILLING] loadRowsFromAdminBillingStatements', [
                'refs'  => $refs,
                'count' => $items->count(),
            ]);

            if ($items->isEmpty()) return [];

            $rows = [];
            foreach ($items as $it) {
                $p = trim((string) ($it->period ?? ''));
                if (!$this->isValidPeriod($p)) continue;

                $cargo = is_numeric($it->total_cargo ?? null) ? (float) $it->total_cargo : 0.0;
                $abono = is_numeric($it->total_abono ?? null) ? (float) $it->total_abono : 0.0;

                $saldo = null;
                if (is_numeric($it->saldo ?? null)) {
                    $saldo = (float) $it->saldo;
                } else {
                    $saldo = max(0.0, $cargo - $abono);
                }

                $st     = strtolower(trim((string) ($it->status ?? 'pending')));
                $paidAt = $it->paid_at ?? null;

                $paid = false;
                if ($paidAt) $paid = true;
                if (in_array($st, ['paid', 'succeeded', 'success', 'complete', 'completed', 'captured', 'confirmed'], true)) $paid = true;
                if ($saldo <= 0.0001) $paid = true;

                $rows[] = [
                    'period'                 => $p,
                    'status'                 => $paid ? 'paid' : 'pending',
                    'charge'                 => round(max(0.0, $cargo), 2),
                    'paid_amount'            => $paid ? round(max(0.0, $abono > 0 ? $abono : $cargo), 2) : 0.0,
                    'saldo'                  => $paid ? 0.0 : round(max(0.0, $saldo), 2),
                    'can_pay'                => !$paid,
                    'due_date'               => $it->due_date ?? null,
                    'paid_at'                => $paidAt,
                    'invoice_request_status' => null,
                    'invoice_has_zip'        => false,
                ];
            }

            return $rows;

        } catch (\Throwable $e) {
            Log::warning('[BILLING] loadRowsFromAdminBillingStatements failed', [
                'refs' => $refs,
                'err'  => $e->getMessage(),
            ]);
            return [];
        }
    }


    private function adminLastPaidPeriod(int $accountId): ?string
    {
        if ($accountId <= 0) return null;

        $adm = (string) config('p360.conn.admin', 'mysql_admin');

        // =========================================================
        // 1) ✅ SOT: admin.payments
        // =========================================================
        try {
            if (Schema::connection($adm)->hasTable('payments')) {
                $cols = Schema::connection($adm)->getColumnListing('payments');
                $lc   = array_map('strtolower', $cols);
                $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

                if ($has('account_id') && $has('period')) {
                    $q = DB::connection($adm)->table('payments')
                        ->where('account_id', $accountId);

                    if ($has('status')) {
                        $q->where(function ($w) {
                            $w->whereNull('status')
                            ->orWhereIn('status', ['paid', 'succeeded', 'success', 'complete', 'completed', 'captured', 'confirmed'])
                            ->orWhereRaw('LOWER(TRIM(status)) IN ("paid","succeeded","success","complete","completed","captured","confirmed")');
                        });
                    }

                    if ($has('provider')) {
                        $q->where(function ($w) {
                            $w->whereNull('provider')
                            ->orWhere('provider', '')
                            ->orWhereRaw('LOWER(TRIM(provider)) = "stripe"');
                        });
                    }

                    if ($has('amount')) {
                        $q->where(function ($w) {
                            $w->whereNull('amount')->orWhere('amount', '>', 0);
                        });
                    }

                    $orderCol = $has('paid_at') ? 'paid_at'
                        : ($has('confirmed_at') ? 'confirmed_at'
                            : ($has('captured_at') ? 'captured_at'
                                : ($has('completed_at') ? 'completed_at'
                                    : ($has('updated_at') ? 'updated_at'
                                        : ($has('created_at') ? 'created_at'
                                            : ($has('id') ? 'id' : 'period'))))));

                    $row = $q->orderByDesc('period')
                        ->orderByDesc($orderCol)
                        ->first(['period', 'status', 'provider']);

                    if ($row) {
                        $p = trim((string) ($row->period ?? ''));
                        if ($p !== '' && $this->isValidPeriod($p)) return $p;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] adminLastPaidPeriod payments failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
        }

        // =========================================================
        // 1.5) ✅ FALLBACK SOT: admin.billing_statements (INT o UUID)
        // =========================================================
        try {
            if (Schema::connection($adm)->hasTable('billing_statements')) {
                $refs = $this->billingStatementRefsForAdminAccount($accountId);

                $cols = Schema::connection($adm)->getColumnListing('billing_statements');
                $lc   = array_map('strtolower', $cols);
                $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

                if ($has('account_id') && $has('period')) {
                    $sel = ['account_id', 'period'];
                    foreach (['status', 'paid_at', 'saldo', 'total_cargo', 'total_abono', 'created_at', 'updated_at'] as $c) {
                        if ($has($c)) $sel[] = $c;
                    }

                    $items = DB::connection($adm)->table('billing_statements')
                        ->whereIn('account_id', $refs)
                        ->orderByDesc('period')
                        ->limit(80)
                        ->get($sel);

                    foreach ($items as $it) {
                        $p = trim((string) ($it->period ?? ''));
                        if (!$this->isValidPeriod($p)) continue;

                        $st     = strtolower(trim((string) ($it->status ?? '')));
                        $paidAt = $it->paid_at ?? null;
                        $saldo  = is_numeric($it->saldo ?? null) ? (float) $it->saldo : null;
                        $cargo  = is_numeric($it->total_cargo ?? null) ? (float) $it->total_cargo : 0.0;
                        $abono  = is_numeric($it->total_abono ?? null) ? (float) $it->total_abono : 0.0;

                        $paid = false;
                        if ($paidAt) $paid = true;
                        if (in_array($st, ['paid','succeeded','success','complete','completed','captured','confirmed'], true)) $paid = true;
                        if ($saldo !== null && $saldo <= 0.0001 && ($cargo > 0.0001 || $abono > 0.0001)) $paid = true;

                        if ($paid) return $p; // ya viene orderByDesc(period)
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] adminLastPaidPeriod billing_statements failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
        }

        // =========================================================
        // 2) admin.accounts.meta.stripe (compat)
        // =========================================================
        try {
            if (Schema::connection($adm)->hasTable('accounts')) {
                $acc = DB::connection($adm)->table('accounts')
                    ->select(['id', 'meta'])
                    ->where('id', $accountId)
                    ->first();

                if ($acc && isset($acc->meta)) {
                    $meta = is_string($acc->meta)
                        ? (json_decode((string) $acc->meta, true) ?: [])
                        : (array) $acc->meta;

                    $p1 = trim((string) data_get($meta, 'stripe.last_paid_period', ''));
                    if ($p1 !== '' && $this->isValidPeriod($p1)) return $p1;

                    $lastPaidAt = data_get($meta, 'stripe.last_paid_at');
                    $p2 = $this->parseToPeriod($lastPaidAt);
                    if ($p2 && $this->isValidPeriod($p2)) return $p2;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] adminLastPaidPeriod meta failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
        }

        // =========================================================
        // 3) mysql_clientes.estados_cuenta (último recurso)
        // =========================================================
        try {
            $cli = (string) config('p360.conn.clientes', 'mysql_clientes');
            if (!Schema::connection($cli)->hasTable('estados_cuenta')) return null;

            $items = DB::connection($cli)->table('estados_cuenta')
                ->where('account_id', $accountId)
                ->orderByDesc('periodo')
                ->limit(48)
                ->get(['periodo', 'cargo', 'abono', 'saldo']);

            foreach ($items as $it) {
                $p = $this->parseToPeriod($it->periodo ?? null);
                if (!$p || !$this->isValidPeriod($p)) continue;

                $cargo = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
                $abono = is_numeric($it->abono ?? null) ? (float) $it->abono : 0.0;
                $saldo = is_numeric($it->saldo ?? null) ? (float) $it->saldo : max(0.0, $cargo - $abono);

                if ($saldo <= 0.0001 || ($cargo > 0 && $abono >= $cargo)) return $p;
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] adminLastPaidPeriod clientes failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
        }

        return null;
    }



    private function resolveMonthlyCentsFromClientesEstadosCuenta(int $accountId, ?string $lastPaid, ?string $payAllowed): int
    {
        $cli = config('p360.conn.clients', 'mysql_clientes');
        if (!Schema::connection($cli)->hasTable('estados_cuenta')) return 0;

        $tryPeriods = array_values(array_unique(array_filter([
            $payAllowed && $this->isValidPeriod($payAllowed) ? $payAllowed : null,
            $lastPaid && $this->isValidPeriod($lastPaid) ? $lastPaid : null,
        ])));

        try {
            if ($tryPeriods) {
                $items = DB::connection($cli)->table('estados_cuenta')
                    ->select(['periodo', 'cargo'])
                    ->where('account_id', $accountId)
                    ->whereIn('periodo', $tryPeriods)
                    ->orderByDesc('periodo')
                    ->limit(5)
                    ->get();

                foreach ($items as $it) {
                    $p = (string) ($it->periodo ?? '');
                    $cargo = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
                    if ($this->isValidPeriod($p) && $cargo > 0) {
                        return (int) round($cargo * 100);
                    }
                }
            }

            $items2 = DB::connection($cli)->table('estados_cuenta')
                ->select(['periodo', 'cargo'])
                ->where('account_id', $accountId)
                ->orderByDesc('periodo')
                ->limit(24)
                ->get();

            foreach ($items2 as $it) {
                $p = (string) ($it->periodo ?? '');
                $cargo = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
                if ($this->isValidPeriod($p) && $cargo > 0) {
                    return (int) round($cargo * 100);
                }
            }

            return 0;
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolveMonthlyCentsFromClientesEstadosCuenta failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * ✅ Saldo anterior pendiente (cents) para un periodo.
     * Regla: solo integra 1 mes anterior (period-1), como estado de cuenta clásico.
     *
     * Prioridad:
     * 1) mysql_clientes.estados_cuenta.saldo del periodo anterior
     * 2) mysql_admin.billing_statements: max(0, total_cents - paid_cents) del periodo anterior
     */
    private function resolvePrevBalanceCentsForPeriod(int $accountId, string $period): int
    {
        if (!$this->isValidPeriod($period)) return 0;

        try {
            $prev = Carbon::createFromFormat('Y-m', $period)->subMonthNoOverflow()->format('Y-m');
        } catch (\Throwable $e) {
            return 0;
        }

        // 1) clientes.estados_cuenta.saldo (fuente más directa)
        try {
            $cli = (string) config('p360.conn.clients', 'mysql_clientes');
            if (Schema::connection($cli)->hasTable('estados_cuenta')) {
                $row = DB::connection($cli)->table('estados_cuenta')
                    ->where('account_id', $accountId)
                    ->where('periodo', $prev)
                    ->orderByDesc('periodo')
                    ->first(['cargo','abono','saldo','periodo']);

                if ($row) {
                    $saldo = is_numeric($row->saldo ?? null) ? (float)$row->saldo : null;

                    // Si no hay saldo explícito, calcula cargo-abono
                    if ($saldo === null) {
                        $cargo = is_numeric($row->cargo ?? null) ? (float)$row->cargo : 0.0;
                        $abono = is_numeric($row->abono ?? null) ? (float)$row->abono : 0.0;
                        $saldo = max(0.0, $cargo - $abono);
                    }

                    $cents = (int) round(max(0.0, (float)$saldo) * 100);
                    if ($cents > 0) return $cents;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolvePrevBalanceCentsForPeriod clientes failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
        }

        // 2) admin.billing_statements (total - paid)
        try {
            $t = $this->resolveStatementTotalsCents($accountId, $prev);
            $total = (int) ($t['total_cents'] ?? 0);

            // si no hay total, intenta subtotal+iva
            if ($total <= 0) {
                $sub = (int) ($t['subtotal_cents'] ?? 0);
                $tax = (int) ($t['tax_cents'] ?? 0);
                $total = $sub + $tax;
            }

            $paid = (int) ($t['paid_cents'] ?? 0);

            $due = max(0, $total - $paid);
            return $due > 0 ? (int)$due : 0;

        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolvePrevBalanceCentsForPeriod admin failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
            return 0;
        }
    }

    private function resolveMonthlyCentsFromEstadosCuenta(int $accountId, ?string $lastPaid, ?string $payAllowed): int
    {
        $adm = config('p360.conn.admin', 'mysql_admin');

        try {
            if (!Schema::connection($adm)->hasTable('estados_cuenta')) return 0;

            $try = array_values(array_unique(array_filter([
                $payAllowed && $this->isValidPeriod($payAllowed) ? $payAllowed : null,
                $lastPaid && $this->isValidPeriod($lastPaid) ? $lastPaid : null,
            ])));

            $q = DB::connection($adm)->table('estados_cuenta')->where('account_id', $accountId);
            if ($try) $q->whereIn('periodo', $try);

            $row = $q->orderByDesc('periodo')->first(['periodo', 'cargo']);

            if ($row && is_numeric($row->cargo ?? null) && (float) $row->cargo > 0) {
                return (int) round(((float) $row->cargo) * 100);
            }

            return 0;
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolveMonthlyCentsFromEstadosCuenta failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * ✅ STATUS de solicitud de factura:
     * - Preferencia: admin.billing_invoice_requests
     * - Fallback: admin.invoice_requests (legacy)
     *
     * Entrega por periodo:
     * - invoice_request_status
     * - invoice_has_zip
     */
    private function attachInvoiceRequestStatus(int $accountId, array $rows): array
    {
        $adm = (string) config('p360.conn.admin', 'mysql_admin');

        $periods = array_values(array_unique(array_map(fn ($x) => (string) ($x['period'] ?? ''), $rows)));
        $periods = array_filter($periods, fn ($p) => (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p));
        if (!$periods) return $rows;

        $map = [];

        // 1) SOT: billing_invoice_requests
        $map = $this->loadInvoiceStatusMapFromTable($adm, 'billing_invoice_requests', $accountId, $periods);

        // 2) Fallback legacy si no hubo nada
        if (!$map) {
            $map = $this->loadInvoiceStatusMapFromTable($adm, 'invoice_requests', $accountId, $periods);
        }

        if ($map) {
            foreach ($rows as &$r) {
                $p = (string) ($r['period'] ?? '');
                $r['invoice_request_status'] = $map[$p]['status'] ?? null;
                $r['invoice_has_zip']        = (bool) ($map[$p]['has_zip'] ?? false);
            }
            unset($r);
        }

        return $rows;
    }

    /**
     * Carga status + has_zip desde una tabla (admin) para periodos.
     * Retorna map[period] = ['status'=>..., 'has_zip'=>bool]
     */
    private function loadInvoiceStatusMapFromTable(string $adm, string $table, int $accountId, array $periods): array
    {
        if (!Schema::connection($adm)->hasTable($table)) return [];

        $cols = Schema::connection($adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);
        $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

        if (!$has('period')) return [];

        // FK
        $fk = null;
        foreach (['account_id', 'admin_account_id', 'cuenta_id'] as $cand) {
            if ($has($cand)) { $fk = $cand; break; }
        }
        if (!$fk) return [];

        $statusCol = $has('status') ? 'status' : null;

        $zipPathCol = null;
        foreach (['zip_path', 'file_path', 'factura_path', 'path', 'ruta_zip', 'zip'] as $pcol) {
            if ($has($pcol)) { $zipPathCol = $pcol; break; }
        }

        $select = ['period'];
        if ($statusCol) $select[] = $statusCol;
        if ($zipPathCol) $select[] = $zipPathCol;

        $orderCol = $has('id') ? 'id' : ($has('created_at') ? 'created_at' : $cols[0]);

        try {
            $items = DB::connection($adm)->table($table)
                ->where($fk, $accountId)
                ->whereIn('period', $periods)
                ->orderByDesc($orderCol)
                ->get($select);

            $map = [];
            foreach ($items as $it) {
                $p = (string) ($it->period ?? '');
                if ($p === '' || isset($map[$p])) continue;

                $st = $statusCol ? strtolower((string) ($it->{$statusCol} ?? 'requested')) : 'requested';
                $zipPath = $zipPathCol ? (string) ($it->{$zipPathCol} ?? '') : '';
                $hasZip = trim($zipPath) !== '';

                $map[$p] = ['status' => $st, 'has_zip' => $hasZip];
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('[BILLING] loadInvoiceStatusMapFromTable failed', [
                'table' => $table,
                'account_id' => $accountId,
                'err' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // =========================
    // (resto de helpers)
    // =========================

    private function billingStatementRefsForAdminAccount(int $adminAccountId): array
    {
        $refs = [];

        if ($adminAccountId > 0) {
            $refs[] = (string) $adminAccountId;     // "3"
            $refs[] = (string) (int) $adminAccountId; // "3" (redundante, pero ok)
            $refs[] = $adminAccountId;              // 3 (por si alguien lo usa sin cast)
        }

        try {
            $cli = (string) config('p360.conn.clientes', 'mysql_clientes');

            if (
                $adminAccountId > 0 &&
                Schema::connection($cli)->hasTable('cuentas_cliente') &&
                Schema::connection($cli)->hasColumn('cuentas_cliente', 'admin_account_id')
            ) {
                $uuids = DB::connection($cli)->table('cuentas_cliente')
                    ->where('admin_account_id', $adminAccountId)
                    ->limit(500)
                    ->pluck('id')
                    ->map(fn ($x) => trim((string) $x))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                foreach ($uuids as $u) $refs[] = $u;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // normaliza
        $refs = array_values(array_unique(array_filter(array_map(
            fn ($x) => trim((string) $x),
            $refs
        ))));

        return $refs;
    }


    private function resolveRfcAliasForUi(Request $req, int $adminAccountId): array
    {
        $u = Auth::guard('web')->user();

        $rfc = '';
        $alias = '';

        foreach (['rfc', 'tax_id', 'taxid', 'taxId', 'rfc_fiscal'] as $k) {
            if (!empty($u?->{$k})) { $rfc = (string) $u->{$k}; break; }
        }
        foreach (['alias', 'name', 'nombre', 'nombre_comercial', 'razon_social', 'empresa'] as $k) {
            if (!empty($u?->{$k})) { $alias = (string) $u->{$k}; break; }
        }

        $clientAccountId = $req->session()->get('client.cuenta_id')
            ?? $req->session()->get('cuenta_id')
            ?? $req->session()->get('client.account_id')
            ?? $req->session()->get('account_id')
            ?? $req->session()->get('client_account_id');

        try {
            if ($clientAccountId) {
                $cli = config('p360.conn.clients', 'mysql_clientes');
                if (Schema::connection($cli)->hasTable('cuentas_cliente')) {
                    $cols = Schema::connection($cli)->getColumnListing('cuentas_cliente');
                    $lc = array_map('strtolower', $cols);
                    $has = fn (string $c) => in_array(strtolower($c), $lc, true);

                    $sel = ['id'];
                    foreach (['rfc', 'rfc_fiscal', 'razon_social', 'nombre_comercial', 'alias', 'email'] as $c) {
                        if ($has($c)) $sel[] = $c;
                    }

                    $tbl = DB::connection($cli)->table('cuentas_cliente')
                        ->select(array_values(array_unique($sel)));

                    // 1) intento por PK
                    $cc = $tbl->where('id', $clientAccountId)->first();

                    // 2) fallback UUID/public_id si existe
                    if (!$cc) {
                        $altCols = [];
                        foreach (['uuid', 'public_id', 'cuenta_uuid', 'uid'] as $c) {
                            if ($has($c)) $altCols[] = $c;
                        }

                        foreach ($altCols as $col) {
                            $cc = DB::connection($cli)->table('cuentas_cliente')
                                ->select(array_values(array_unique($sel)))
                                ->where($col, $clientAccountId)
                                ->first();
                            if ($cc) break;
                        }
                    }

                    if ($cc) {
                        if ($rfc === '') {
                            foreach (['rfc', 'rfc_fiscal'] as $k) {
                                if ($has($k) && !empty($cc->{$k})) { $rfc = (string) $cc->{$k}; break; }
                            }
                        }

                        if ($alias === '') {
                            if ($has('alias') && !empty($cc->alias)) $alias = (string) $cc->alias;
                            elseif ($has('nombre_comercial') && !empty($cc->nombre_comercial)) $alias = (string) $cc->nombre_comercial;
                            elseif ($has('razon_social') && !empty($cc->razon_social)) $alias = (string) $cc->razon_social;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolveRfcAliasForUi cuentas_cliente failed', [
                'admin_account_id' => $adminAccountId,
                'clientAccountId'  => $clientAccountId,
                'err'              => $e->getMessage(),
            ]);
        }

        try {
            $adm = config('p360.conn.admin', 'mysql_admin');
            if (Schema::connection($adm)->hasTable('accounts')) {
                $cols = Schema::connection($adm)->getColumnListing('accounts');
                $lc = array_map('strtolower', $cols);
                $has = fn (string $c) => in_array(strtolower($c), $lc, true);

                $select = ['id'];
                foreach (['rfc', 'razon_social', 'nombre_comercial', 'alias', 'meta'] as $c) {
                    if ($has($c)) $select[] = $c;
                }

                $acc = DB::connection($adm)->table('accounts')
                    ->select(array_values(array_unique($select)))
                    ->where('id', $adminAccountId)
                    ->first();

                if ($acc) {
                    if ($rfc === '' && $has('rfc') && !empty($acc->rfc)) $rfc = (string) $acc->rfc;

                    if ($alias === '') {
                        if ($has('alias') && !empty($acc->alias)) $alias = (string) $acc->alias;
                        elseif ($has('nombre_comercial') && !empty($acc->nombre_comercial)) $alias = (string) $acc->nombre_comercial;
                        elseif ($has('razon_social') && !empty($acc->razon_social)) $alias = (string) $acc->razon_social;
                    }

                    if ($has('meta') && isset($acc->meta)) {
                        $meta = [];
                        try {
                            $meta = is_string($acc->meta) ? (json_decode((string) $acc->meta, true) ?: []) : (array) $acc->meta;
                        } catch (\Throwable $e) { $meta = []; }

                        $billing = (array) ($meta['billing'] ?? []);
                        $company = (array) ($meta['company'] ?? []);

                        if ($rfc === '') {
                            foreach ([
                                $billing['rfc'] ?? null,
                                $billing['rfc_fiscal'] ?? null,
                                $company['rfc'] ?? null,
                                data_get($meta, 'rfc'),
                                data_get($meta, 'company.rfc'),
                            ] as $v) {
                                $v = is_string($v) ? trim($v) : '';
                                if ($v !== '') { $rfc = $v; break; }
                            }
                        }

                        if ($alias === '') {
                            foreach ([
                                $billing['alias'] ?? null,
                                $billing['nombre_comercial'] ?? null,
                                $company['nombre_comercial'] ?? null,
                                $company['razon_social'] ?? null,
                                data_get($meta, 'alias'),
                                data_get($meta, 'company.razon_social'),
                            ] as $v) {
                                $v = is_string($v) ? trim($v) : '';
                                if ($v !== '') { $alias = $v; break; }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolveRfcAliasForUi accounts failed', [
                'admin_account_id' => $adminAccountId,
                'err'              => $e->getMessage(),
            ]);
        }

        if ($rfc === '') $rfc = '—';
        if ($alias === '') $alias = '—';

        return [$rfc, $alias];
    }

    private function resolveContractStartPeriod(int $accountId): string
    {
        $adm = config('p360.conn.admin', 'mysql_admin');
        $fallback = now()->format('Y-m');

        if (!Schema::connection($adm)->hasTable('accounts')) return $fallback;

        try {
            $cols = Schema::connection($adm)->getColumnListing('accounts');
            $lc   = array_map('strtolower', $cols);
            $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

            $select = ['id'];
            foreach (['meta', 'created_at', 'activated_at', 'starts_at', 'start_date', 'subscription_started_at', 'contracted_at'] as $c) {
                if ($has($c)) $select[] = $c;
            }

            $acc = DB::connection($adm)->table('accounts')
                ->select(array_values(array_unique($select)))
                ->where('id', $accountId)
                ->first();

            if (!$acc) return $fallback;

            $meta = [];
            if ($has('meta') && isset($acc->meta)) {
                try {
                    $meta = is_string($acc->meta) ? (json_decode((string) $acc->meta, true) ?: []) : (array) $acc->meta;
                } catch (\Throwable $e) { $meta = []; }
            }

            foreach ([
                data_get($meta, 'billing.start_period'),
                data_get($meta, 'subscription.start_period'),
                data_get($meta, 'plan.start_period'),
                data_get($meta, 'start_period'),
            ] as $v) {
                $v = is_string($v) ? trim($v) : '';
                if ($v !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v)) return $v;
            }

            foreach (['start_date', 'starts_at', 'subscription_started_at', 'contracted_at', 'activated_at', 'created_at'] as $c) {
                if (!$has($c)) continue;
                $p = $this->parseToPeriod($acc->{$c} ?? null);
                if ($p) return $p;
            }

            return $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    private function parseToPeriod(mixed $value): ?string
    {
        try {
            if ($value instanceof \DateTimeInterface) return Carbon::instance($value)->format('Y-m');

            if (is_numeric($value)) {
                $ts = (int) $value;
                if ($ts > 0) return Carbon::createFromTimestamp($ts)->format('Y-m');
            }

            if (is_string($value)) {
                $v = trim($value);
                if ($v === '') return null;

                $v = str_replace('/', '-', $v);
                if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v)) return $v;

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

    private function resolveAdminAccountId(Request $req): array
    {
        $u = Auth::guard('web')->user();

        // =========================================================
        // Helpers
        // =========================================================
        $toInt = static function ($v): int {
            if ($v === null) return 0;
            if (is_int($v)) return $v > 0 ? $v : 0;
            if (is_numeric($v)) {
                $i = (int) $v;
                return $i > 0 ? $i : 0;
            }
            if (is_string($v)) {
                $v = trim($v);
                if ($v !== '' && is_numeric($v)) {
                    $i = (int) $v;
                    return $i > 0 ? $i : 0;
                }
            }
            return 0;
        };

        $toStr = static function ($v): string {
            if ($v === null) return '';
            if (is_string($v)) return trim($v);
            return trim((string) $v);
        };

        $pickSessionId = function (Request $req, array $keys) use ($toInt): array {
            foreach ($keys as $k) {
                $v = $req->session()->get($k);
                $id = $toInt($v);
                if ($id > 0) return [$id, 'session.' . $k];
            }
            return [0, ''];
        };

        // =========================================================
        // 0) Param/route explícito (si llega)
        // =========================================================
        $routeAccountId = null;
        try { $routeAccountId = $req->route('account_id'); } catch (\Throwable $e) { $routeAccountId = null; }

        $accountIdFromParam =
            $toInt($routeAccountId)
            ?: $toInt($req->query('account_id'))
            ?: $toInt($req->input('account_id'))
            ?: $toInt($req->query('aid'))
            ?: $toInt($req->input('aid'));

        if ($accountIdFromParam > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $accountIdFromParam);
                $req->session()->put('billing.admin_account_src', 'param.account_id');
            } catch (\Throwable $e) {}
            return [(string) $accountIdFromParam, 'param.account_id'];
        }

        // =========================================================
        // 1) Resolver "cuenta cliente" (UUID) — PRIORIDAD:
        //    A) sesión (si existe)
        //    B) usuarios_cuenta por email del user (PROD-friendly)
        // =========================================================
        $clientCuentaIdRaw =
            $req->session()->get('client.cuenta_id')
            ?? $req->session()->get('cuenta_id')
            ?? $req->session()->get('client_cuenta_id')
            ?? null;

        $clientCuentaId = $toStr($clientCuentaIdRaw);

        // B) Si la sesión no trae cuenta_id, intenta desde mysql_clientes.usuarios_cuenta por email
        if ($clientCuentaId === '') {
            try {
                $email = strtolower(trim((string) ($u?->email ?? '')));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $cli = (string) config('p360.conn.clients', 'mysql_clientes');

                    if (Schema::connection($cli)->hasTable('usuarios_cuenta')) {
                        $cols = Schema::connection($cli)->getColumnListing('usuarios_cuenta');
                        $lc   = array_map('strtolower', $cols);
                        $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

                        // columnas mínimas
                        if ($has('email') && $has('cuenta_id')) {
                            $q = DB::connection($cli)->table('usuarios_cuenta')
                                ->whereRaw('LOWER(TRIM(email)) = ?', [$email]);

                            if ($has('activo')) $q->where('activo', 1);

                            // prioridad owner si existe
                            if ($has('rol'))  $q->orderByRaw("CASE WHEN rol='owner' THEN 0 ELSE 1 END");
                            if ($has('tipo')) $q->orderByRaw("CASE WHEN tipo='owner' THEN 0 ELSE 1 END");

                            $orderCol = $has('created_at') ? 'created_at' : ($has('id') ? 'id' : $cols[0]);
                            $q->orderByDesc($orderCol);

                            $row = $q->first(['cuenta_id','email']);

                            $cid = $toStr($row?->cuenta_id ?? '');
                            if ($cid !== '') {
                                $clientCuentaId = $cid;

                                // cachea en sesión para la siguiente request
                                try {
                                    $req->session()->put('client.cuenta_id', $clientCuentaId);
                                } catch (\Throwable $e) {}
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignora
            }
        }

        // =========================================================
        // 2) Resolver admin_account_id desde mysql_clientes.cuentas_cliente
        // =========================================================
        $adminFromClientCuenta = 0;
        $adminFromClientSrc    = '';

        if ($clientCuentaId !== '') {
            try {
                $cli = (string) config('p360.conn.clients', 'mysql_clientes');

                if (Schema::connection($cli)->hasTable('cuentas_cliente')) {
                    $cols = Schema::connection($cli)->getColumnListing('cuentas_cliente');
                    $lc   = array_map('strtolower', $cols);
                    $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

                    $sel = ['id'];
                    foreach (['admin_account_id', 'account_id', 'meta', 'rfc', 'rfc_padre', 'razon_social'] as $c) {
                        if ($has($c)) $sel[] = $c;
                    }
                    $sel = array_values(array_unique($sel));

                    $q = DB::connection($cli)->table('cuentas_cliente')->select($sel);

                    // id es UUID char(36) en PROD, pero puede venir numérico en local: soporta ambos
                    $q->where('id', $clientCuentaId);

                    $asInt = $toInt($clientCuentaId);
                    if ($asInt > 0) $q->orWhere('id', $asInt);

                    // meta JSON (por si algún día guardas uuid alterno)
                    if ($has('meta')) {
                        foreach ([
                            '$.cuenta_uuid',
                            '$.cuenta.id',
                            '$.cuenta_id',
                            '$.uuid',
                            '$.public_id',
                            '$.cliente_uuid',
                        ] as $path) {
                            $q->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, ?)) = ?", [$path, $clientCuentaId]);
                        }
                    }

                    $cc = $q->first();

                    if ($cc) {
                        if ($has('admin_account_id')) {
                            $aid = $toInt($cc->admin_account_id ?? null);
                            if ($aid > 0) {
                                $adminFromClientCuenta = $aid;
                                $adminFromClientSrc    = 'cuentas_cliente.admin_account_id';
                            }
                        }

                        if ($adminFromClientCuenta <= 0 && $has('account_id')) {
                            $aid = $toInt($cc->account_id ?? null);
                            if ($aid > 0) {
                                $adminFromClientCuenta = $aid;
                                $adminFromClientSrc    = 'cuentas_cliente.account_id';
                            }
                        }

                        if ($adminFromClientCuenta <= 0 && $has('meta') && isset($cc->meta)) {
                            try {
                                $meta = is_string($cc->meta) ? (json_decode((string)$cc->meta, true) ?: []) : (array)$cc->meta;
                                $aid  = $toInt(data_get($meta, 'admin_account_id'));
                                if ($aid > 0) {
                                    $adminFromClientCuenta = $aid;
                                    $adminFromClientSrc    = 'cuentas_cliente.meta.admin_account_id';
                                }
                            } catch (\Throwable $e) {}
                        }
                    }
                }
            } catch (\Throwable $e) {
                $adminFromClientCuenta = 0;
                $adminFromClientSrc    = '';
            }
        }

        // =========================================================
        // 3) Fallbacks (solo si lo anterior no resolvió)
        // =========================================================
        $adminFromUserRel = 0;
        try {
            if ($u && method_exists($u, 'relationLoaded') && !$u->relationLoaded('cuenta')) {
                try { $u->load('cuenta'); } catch (\Throwable $e) {}
            }
            $relAdmin = null;
            try { $relAdmin = $u?->cuenta?->admin_account_id ?? null; } catch (\Throwable $e) { $relAdmin = null; }
            $adminFromUserRel = $toInt($relAdmin);
        } catch (\Throwable $e) {
            $adminFromUserRel = 0;
        }

        $adminFromUserField = $toInt($u?->admin_account_id ?? null);

        // ✅ MUY IMPORTANTE: NO uses llaves genéricas primero (account_id) porque en PROD puede contaminar.
        [$adminFromSessionDirect, $sessionDirectSrc] = $pickSessionId($req, [
            'billing.admin_account_id',
            'verify.account_id',
            'paywall.account_id',
            'client.admin_account_id',

            // ⚠️ genéricas al final
            'admin_account_id',
            'account_id',
            'client.account_id',
            'client_account_id',
        ]);

        // =========================================================
        // 4) Selección final + persistencia namespaced
        // =========================================================
        if ($adminFromClientCuenta > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromClientCuenta);
                $req->session()->put('billing.admin_account_src', (string) ($adminFromClientSrc ?: 'cuentas_cliente'));
            } catch (\Throwable $e) {}
            return [(string) $adminFromClientCuenta, $adminFromClientSrc ?: 'cuentas_cliente'];
        }

        if ($adminFromUserRel > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromUserRel);
                $req->session()->put('billing.admin_account_src', 'user.cuenta.admin_account_id');
            } catch (\Throwable $e) {}
            return [(string) $adminFromUserRel, 'user.cuenta.admin_account_id'];
        }

        if ($adminFromUserField > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromUserField);
                $req->session()->put('billing.admin_account_src', 'user.admin_account_id');
            } catch (\Throwable $e) {}
            return [(string) $adminFromUserField, 'user.admin_account_id'];
        }

        if ($adminFromSessionDirect > 0) {
            try {
                $req->session()->put('billing.admin_account_id', (string) $adminFromSessionDirect);
                $req->session()->put('billing.admin_account_src', (string) ($sessionDirectSrc ?: 'session.direct'));
            } catch (\Throwable $e) {}
            return [(string) $adminFromSessionDirect, $sessionDirectSrc ?: 'session.direct'];
        }

        return [null, 'unresolved'];
    }

    private function resolveAdminAccountIdFromClientAccount(object $clientAccount): int
    {
        if (isset($clientAccount->admin_account_id) && is_numeric($clientAccount->admin_account_id)) {
            $id = (int) $clientAccount->admin_account_id;
            if ($id > 0) return $id;
        }

        if (isset($clientAccount->account_id) && is_numeric($clientAccount->account_id)) {
            $id = (int) $clientAccount->account_id;
            if ($id > 0) return $id;
        }

        $meta = [];
        try {
            if (isset($clientAccount->meta)) {
                $meta = is_string($clientAccount->meta)
                    ? (json_decode((string)$clientAccount->meta, true) ?: [])
                    : (array) $clientAccount->meta;
            }
        } catch (\Throwable $e) {
            $meta = [];
        }

        $id = (int) (data_get($meta, 'admin_account_id') ?? 0);
        return $id > 0 ? $id : 0;
    }

    protected function resolveAdminAccountIdFromClientUuid(string $uuid): int
    {
        $uuid = trim($uuid);
        if ($uuid === '') return 0;

        // Solo UUID válido (evita queries raros)
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            return 0;
        }

        $cli = config('p360.conn.clients', 'mysql_clientes');
        $adm = config('p360.conn.admin', 'mysql_admin');

        try {
            // ==========================================================
            // 1) Buscar usuario owner/activo por cuenta_id (UUID) en clientes
            // ==========================================================
            if (Schema::connection($cli)->hasTable('usuarios_cuenta')) {
                $uqCols = Schema::connection($cli)->getColumnListing('usuarios_cuenta');
                $uqLC   = array_map('strtolower', $uqCols);
                $hasUq  = fn (string $c) => in_array(strtolower($c), $uqLC, true);

                $sel = [];
                foreach (['id', 'cuenta_id', 'email', 'rol', 'tipo', 'activo', 'created_at'] as $c) {
                    if ($hasUq($c)) $sel[] = $c;
                }
                if (!$sel) $sel = ['id'];

                $q = DB::connection($cli)->table('usuarios_cuenta')->select($sel);

                // match principal
                if ($hasUq('cuenta_id')) {
                    $q->where('cuenta_id', $uuid);
                } else {
                    // si no hay cuenta_id, no hay forma
                    return 0;
                }

                // prioriza owner/activo si existen columnas
                if ($hasUq('activo')) $q->where('activo', 1);

                // algunos esquemas usan "rol" y/o "tipo"
                if ($hasUq('rol'))  $q->orderByRaw("CASE WHEN rol='owner' THEN 0 ELSE 1 END");
                if ($hasUq('tipo')) $q->orderByRaw("CASE WHEN tipo='owner' THEN 0 ELSE 1 END");

                if ($hasUq('created_at')) $q->orderByDesc('created_at');

                $urow = $q->first();

                if ($urow) {
                    $email = strtolower(trim((string)($urow->email ?? '')));

                    // ==========================================================
                    // 2) Resolver admin account por email en mysql_admin.accounts
                    // ==========================================================
                    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        if (Schema::connection($adm)->hasTable('accounts')) {
                            $aCols = Schema::connection($adm)->getColumnListing('accounts');
                            $aLC   = array_map('strtolower', $aCols);
                            $hasA  = fn (string $c) => in_array(strtolower($c), $aLC, true);

                            if ($hasA('email')) {
                                $acc = DB::connection($adm)->table('accounts')
                                    ->select(['id', 'email'])
                                    ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                                    ->orderByDesc('id')
                                    ->first();

                                if ($acc && isset($acc->id) && is_numeric($acc->id) && (int)$acc->id > 0) {
                                    return (int)$acc->id;
                                }
                            }
                        }
                    }

                    // (Opcional) fallback: si algún día agregas admin_account_id directo a usuarios_cuenta
                    if (isset($urow->admin_account_id) && is_numeric($urow->admin_account_id) && (int)$urow->admin_account_id > 0) {
                        return (int)$urow->admin_account_id;
                    }
                }
            }

            // ==========================================================
            // 3) (Opcional) Si en el futuro existe un bridge en cuentas_cliente/meta, aquí podrías agregarlo
            // ==========================================================
            // Por ahora no hacemos nada: tu diagnóstico mostró que NO existe.

        } catch (\Throwable $e) {
            // ignora y regresa 0
        }

        return 0;
    }


    private function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period);
    }

    private function adminFkColumn(string $table): ?string
    {
        $adm = config('p360.conn.admin', 'mysql_admin');
        if (!Schema::connection($adm)->hasTable($table)) return null;

        $cols = Schema::connection($adm)->getColumnListing($table);
        $lc   = array_map('strtolower', $cols);

        foreach (['account_id', 'admin_account_id', 'cuenta_id', 'accountid', 'id_account', 'idcuenta'] as $c) {
            if (in_array(strtolower($c), $lc, true)) {
                foreach ($cols as $real) {
                    if (strtolower($real) === strtolower($c)) return $real;
                }
                return $c;
            }
        }
        return null;
    }

    private function findAdminPdfForPeriod(int $accountId, string $period): ?array
    {
        if (!$this->isValidPeriod($period)) return null;

        try {
            $adminConn = config('p360.conn.admin', 'mysql_admin');
            $admin     = DB::connection($adminConn);

            if (Schema::connection($adminConn)->hasTable('billing_views')) {
                $cols = Schema::connection($adminConn)->getColumnListing('billing_views');

                $q = $admin->table('billing_views');

                if (in_array('account_id', $cols, true)) $q->where('account_id', $accountId);
                if (in_array('period', $cols, true))     $q->where('period', $period);

                if (in_array('kind', $cols, true)) $q->whereIn('kind', ['statement', 'estado_cuenta', 'billing_statement', 'pdf']);
                if (in_array('type', $cols, true)) $q->whereIn('type', ['statement', 'estado_cuenta', 'billing_statement', 'pdf']);

                $orderCol = in_array('id', $cols, true) ? 'id' : (in_array('created_at', $cols, true) ? 'created_at' : $cols[0]);

                $row = $q->orderByDesc($orderCol)->first();

                if ($row) {
                    $disk = null; $path = null; $name = null;

                    if (in_array('pdf_disk', $cols, true))     $disk = $row->pdf_disk;
                    if (in_array('pdf_path', $cols, true))     $path = $row->pdf_path;
                    if (in_array('pdf_filename', $cols, true)) $name = $row->pdf_filename;

                    if (!$disk && in_array('disk', $cols, true))     $disk = $row->disk;
                    if (!$path && in_array('path', $cols, true))     $path = $row->path;
                    if (!$name && in_array('filename', $cols, true)) $name = $row->filename;

                    if ($path) {
                        return [
                            'disk'     => $disk ?: 'public',
                            'path'     => ltrim((string) $path, '/'),
                            'filename' => $name ?: ('EstadoCuenta_'.$period.'.pdf'),
                        ];
                    }
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('[BILLING] findAdminPdfForPeriod failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function imgToDataUri(string $absPath): ?string
    {
        try {
            if ($absPath === '' || !is_file($absPath) || !is_readable($absPath)) {
                return null;
            }

            $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png'  => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
                'svg'  => 'image/svg+xml',
                default => null,
            };

            if (!$mime) return null;

            $bin = file_get_contents($absPath);
            if ($bin === false || $bin === '') return null;

            return 'data:'.$mime.';base64,'.base64_encode($bin);
        } catch (\Throwable $e) {
            Log::warning('[BILLING] imgToDataUri failed', ['path' => $absPath, 'err' => $e->getMessage()]);
            return null;
        }
    }

    private function qrToDataUri(string $text, int $size = 150): ?string
    {
        try {
            // bacon/bacon-qr-code requerido
            if (!class_exists(\BaconQrCode\Writer::class) || !class_exists(\BaconQrCode\Renderer\ImageRenderer::class)) {
                return null;
            }

            $size = max(80, min(600, $size));

            // 1) Intentar PNG con GD si el backend existe (bacon v3)
            if (extension_loaded('gd') && class_exists(\BaconQrCode\Renderer\Image\GdImageBackEnd::class)) {
                $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                    new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size),
                    new \BaconQrCode\Renderer\Image\GdImageBackEnd()
                );
                $writer = new \BaconQrCode\Writer($renderer);

                $png = $writer->writeString($text);
                return 'data:image/png;base64,' . base64_encode($png);
            }

            // 2) Fallback SVG (según versión puede existir)
            if (class_exists(\BaconQrCode\Renderer\Image\SvgImageBackEnd::class)) {
                $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                    new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size),
                    new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
                );
                $writer = new \BaconQrCode\Writer($renderer);

                $svg = $writer->writeString($text);
                return 'data:image/svg+xml;base64,' . base64_encode($svg);
            }

            // Sin backend compatible
            return null;
        } catch (\Throwable $e) {
            \Log::warning('[BILLING] qrToDataUri failed', [
                'err' => $e->getMessage(),
                'gd'  => extension_loaded('gd'),
            ]);
            return null;
        }
    }

    /**
     * Determina si la cuenta está en ciclo ANUAL.
     * Fuentes:
     * - admin.accounts.modo_cobro
     * - admin.accounts.meta.billing.cycle / meta.stripe.billing_cycle
     */
    private function isAnnualBillingCycle(int $accountId): bool
    {
        if ($accountId <= 0) return false;

        try {
            $adm = (string) config('p360.conn.admin', 'mysql_admin');
            if (!Schema::connection($adm)->hasTable('accounts')) return false;

            $cols = Schema::connection($adm)->getColumnListing('accounts');
            $lc   = array_map('strtolower', $cols);
            $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

            $sel = ['id'];
            foreach (['modo_cobro', 'meta', 'plan', 'plan_actual'] as $c) {
                if ($has($c)) $sel[] = $c;
            }
            $sel = array_values(array_unique($sel));

            $acc = DB::connection($adm)->table('accounts')
                ->select($sel)
                ->where('id', $accountId)
                ->first();

            if (!$acc) return false;

            // ---------------------------------------------------------
            // Normalización robusta
            // - lower + trim
            // - colapsa espacios
            // - normaliza separadores (_ - .) a espacio
            // ---------------------------------------------------------
            $norm = static function ($v): string {
                $s = strtolower(trim((string) $v));
                if ($s === '') return '';
                $s = str_replace(["\t", "\n", "\r"], ' ', $s);
                $s = str_replace(['_', '-', '.'], ' ', $s);
                $s = preg_replace('/\s+/', ' ', $s);
                return trim((string) $s);
            };

            // ---------------------------------------------------------
            // Matcher anual
            // - exact tokens
            // - contains (anual/annual/year/12)
            // ---------------------------------------------------------
            $isAnnualToken = static function (string $s) use ($norm): bool {
                $s = $norm($s);
                if ($s === '') return false;

                if (in_array($s, [
                    'anual', 'annual', 'annually',
                    'year', 'yearly',
                    '12m', '12 mes', '12 meses', '12meses',
                    '12-month', '12 months', '12months',
                    '1y', '1 year', 'one year',
                ], true)) {
                    return true;
                }

                return str_contains($s, 'anual')
                    || str_contains($s, 'annual')
                    || str_contains($s, 'year')
                    || str_contains($s, '12 mes')
                    || str_contains($s, '12m');
            };

            // helper: evalúa un valor que puede venir compuesto (ej "billing:yearly|mxn")
            $isAnnualValue = static function ($v) use ($norm, $isAnnualToken): bool {
                $s = $norm($v);
                if ($s === '') return false;

                if ($isAnnualToken($s)) return true;

                // rompe por separadores comunes
                $parts = preg_split('/[|,:;\/\\\\]/', $s) ?: [];
                foreach ($parts as $p) {
                    $p = trim((string) $p);
                    if ($p !== '' && $isAnnualToken($p)) return true;
                }

                return false;
            };

            // 1) modo_cobro directo (si existe)
            if ($has('modo_cobro')) {
                $mc = $norm($acc->modo_cobro ?? '');
                if ($isAnnualValue($mc)) return true;
            }

            // 2) plan / plan_actual (señal fuerte)
            $plan = $norm(($acc->plan_actual ?? '') ?: ($acc->plan ?? ''));
            if ($isAnnualValue($plan)) return true;

            // 3) meta (SOT + variantes reales)
            if ($has('meta') && isset($acc->meta)) {
                $meta = is_string($acc->meta)
                    ? (json_decode((string) $acc->meta, true) ?: [])
                    : (array) $acc->meta;

                // ✅ prioridad alta: lo que tú ya viste en prod
                // meta.billing.billing_cycle = "yearly"
                $candidates = [
                    // billing.*
                    data_get($meta, 'billing.billing_cycle'),
                    data_get($meta, 'billing.cycle'),
                    data_get($meta, 'billing.billingCycle'),
                    data_get($meta, 'billing.interval'),
                    data_get($meta, 'billing.modo_cobro'),
                    data_get($meta, 'billing_cycle'),

                    // stripe.*
                    data_get($meta, 'stripe.billing_cycle'),
                    data_get($meta, 'stripe.cycle'),
                    data_get($meta, 'stripe.interval'),
                    data_get($meta, 'stripe.plan_interval'),

                    // subscription.*
                    data_get($meta, 'subscription.billing_cycle'),
                    data_get($meta, 'subscription.cycle'),
                    data_get($meta, 'subscription.interval'),

                    // plan.*
                    data_get($meta, 'plan.billing_cycle'),
                    data_get($meta, 'plan.cycle'),
                    data_get($meta, 'plan.interval'),

                    // root/meta compat
                    data_get($meta, 'modo_cobro'),
                    data_get($meta, 'cycle'),
                    data_get($meta, 'interval'),
                ];

                foreach ($candidates as $v) {
                    if ($isAnnualValue($v)) return true;
                }

                // Fallback extra: por si guardas "yearly" en alguna parte libre dentro de meta
                try {
                    $raw = json_encode($meta, JSON_UNESCAPED_UNICODE);
                    $raw = is_string($raw) ? $norm($raw) : '';
                    if ($raw !== '' && (str_contains($raw, 'yearly') || str_contains($raw, '\"billing_cycle\":\"year'))) {
                        // match liviano: si el json contiene yearly / year en billing_cycle
                        if (str_contains($raw, 'yearly') || str_contains($raw, 'billing cycle') || str_contains($raw, 'billing_cycle')) {
                            // solo activamos si también aparece "year"/"annual"/"anual" cerca de ciclo
                            if (str_contains($raw, 'year') || str_contains($raw, 'annual') || str_contains($raw, 'anual')) {
                                return true;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // ignora
                }
            }

        } catch (\Throwable $e) {
            // ignora
        }

        return false;
    }

    /**
     * Determina si la cuenta es ANUAL (robusto por escenarios).
     */
    private function isAnnualAccount(int $accountId): bool
    {
        $adm = (string) config('p360.conn.admin', 'mysql_admin');
        if (!Schema::connection($adm)->hasTable('accounts')) return false;

        try {
            $cols = Schema::connection($adm)->getColumnListing('accounts');
            $lc   = array_map('strtolower', $cols);
            $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

            $sel = ['id'];
            foreach (['modo_cobro','plan','plan_actual','meta'] as $c) {
                if ($has($c)) $sel[] = $c;
            }

            $acc = DB::connection($adm)->table('accounts')->where('id', $accountId)->first($sel);
            if (!$acc) return false;

            // 1) modo_cobro directo
            if ($has('modo_cobro')) {
                $mc = strtolower(trim((string)($acc->modo_cobro ?? '')));
                if (in_array($mc, ['anual','annual','year','yearly'], true)) return true;
            }

            // 2) meta.* (billing/subscription)
            if ($has('meta') && isset($acc->meta)) {
                $meta = is_string($acc->meta) ? (json_decode((string)$acc->meta, true) ?: []) : (array)$acc->meta;

                $cycle = strtolower(trim((string)(
                    data_get($meta, 'billing.cycle')
                    ?: data_get($meta, 'subscription.cycle')
                    ?: data_get($meta, 'plan.cycle')
                    ?: data_get($meta, 'cycle')
                    ?: ''
                )));

                if (in_array($cycle, ['anual','annual','year','yearly'], true)) return true;
            }

            // 3) planes.precio_anual como “señal” (si existe)
            if (Schema::connection($adm)->hasTable('planes') && ($has('plan') || $has('plan_actual'))) {
                $planKey = trim((string)($acc->plan_actual ?: $acc->plan));
                if ($planKey !== '') {
                    $p = DB::connection($adm)->table('planes')
                        ->where('clave', $planKey)
                        ->first(['precio_anual','activo']);

                    if ($p && (!isset($p->activo) || (int)$p->activo === 1)) {
                        $pa = (float)($p->precio_anual ?? 0);
                        if ($pa > 0) return true;
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::warning('[BILLING] isAnnualAccount failed', ['account_id'=>$accountId,'err'=>$e->getMessage()]);
        }

        return false;
    }

    /**
     * Ventana para mostrar renovación anual (días antes).
     * Default 30 días.
     */
    private function annualRenewalWindowDays(): int
    {
        $n = (int) (config('p360.billing.annual_renewal_window_days') ?? 30);
        return $n > 0 ? $n : 30;
    }

    /**
     * Calcula cents ANUALES:
     * - Preferir planes.precio_anual
     * - Si no existe, usar mensual * 12
     */
    private function resolveAnnualCents(int $accountId, string $basePeriod, ?string $lastPaid, string $payAllowed): int
    {
        $adm = (string) config('p360.conn.admin', 'mysql_admin');

        // 0) Resolver meta desde admin.accounts (SOT)
        $meta = [];
        try {
            $acc = DB::connection($adm)
                ->table('accounts')
                ->where('id', $accountId)
                ->first(['meta']);

            if ($acc && isset($acc->meta)) {
                $raw  = is_string($acc->meta) ? $acc->meta : json_encode($acc->meta);
                $meta = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
            }
        } catch (\Throwable $e) {
            $meta = [];
        }

        // 1) Primero: override anual explícito (si existe)
        $annualExplicit =
            data_get($meta, 'billing.override.yearly.amount_mxn')
            ?? data_get($meta, 'billing.override.annual.amount_mxn')
            ?? data_get($meta, 'billing.override.annual_amount_mxn')
            ?? data_get($meta, 'billing.annual_amount_mxn')
            ?? data_get($meta, 'billing.anual_amount_mxn')
            ?? data_get($meta, 'billing.amount_mxn_annual')
            ?? data_get($meta, 'billing.amount_anual_mxn')
            ?? null;

        if (is_numeric($annualExplicit)) {
            $mxn = (float) $annualExplicit;
            if ($mxn > 0) return (int) round($mxn * 100, 0);
        }

        // 2) ✅ FIX CLAVE: si NO hay override anual, pero SÍ hay billing.override.amount_mxn,
        //    y el ciclo es anual, entonces ESE override debe tomarse como ANUAL (no *12).
        $monthlyOverrideMaybe =
            data_get($meta, 'billing.override.amount_mxn')
            ?? data_get($meta, 'billing.override.monthly.amount_mxn')
            ?? null;

        if (is_numeric($monthlyOverrideMaybe)) {
            $mxn = (float) $monthlyOverrideMaybe;
            if ($mxn > 0) {
                // en anual lo tratamos como monto anual efectivo si no hay yearly override
                return (int) round($mxn * 100, 0);
            }
        }

        // 3) Fallback: precio anual de catálogo/config (si existe)
        //    (evita multiplicar mensual*12 si ya tienes env STRIPE_DISPLAY_PRICE_ANNUAL)
        try {
            $annualCfg = config('services.stripe.display_price_annual');
            if ($annualCfg === null || $annualCfg === '') $annualCfg = env('STRIPE_DISPLAY_PRICE_ANNUAL', null);

            $n = is_numeric($annualCfg)
                ? (float) $annualCfg
                : (float) preg_replace('/[^0-9.\-]/', '', (string) $annualCfg);

            if ($n > 0) {
                return (int) round($n * 100, 0);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 4) Último fallback: mensual * 12 (solo si no hay nada más)
        $monthly = (int) $this->resolveMonthlyCentsForPeriodFromAdminAccount($accountId, $basePeriod, $lastPaid, $payAllowed);
        if ($monthly <= 0) $monthly = (int) $this->resolveMonthlyCentsFromPlanesCatalog($accountId);
        if ($monthly <= 0) $monthly = (int) $this->resolveMonthlyCentsFromClientesEstadosCuenta($accountId, $lastPaid, $payAllowed);

        return $monthly > 0 ? (int) ($monthly * 12) : 0;
    }


    private function renderSimplePdfHtml(array $d): string
    {
        $amount = '$' . number_format((float) ($d['amount'] ?? 0), 2);
        $period = htmlspecialchars((string) ($d['period'] ?? ''), ENT_QUOTES, 'UTF-8');
        $rfc    = htmlspecialchars((string) ($d['rfc'] ?? ''), ENT_QUOTES, 'UTF-8');
        $alias  = htmlspecialchars((string) ($d['alias'] ?? ''), ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars((string) ($d['status'] ?? ''), ENT_QUOTES, 'UTF-8');
        $range  = htmlspecialchars((string) ($d['range'] ?? ''), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!doctype html>
        <html lang="es">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Estado de cuenta {$period}</title>
        <style>
        body{font-family:Arial,Helvetica,sans-serif;color:#111827;margin:24px}
        .card{border:1px solid #e5e7eb;border-radius:12px;padding:18px}
        .h{font-size:18px;font-weight:800;margin:0 0 10px}
        .row{display:flex;gap:16px;flex-wrap:wrap}
        .col{min-width:220px}
        .k{font-size:12px;color:#6b7280;font-weight:700}
        .v{font-size:14px;font-weight:800;margin-top:2px}
        .amt{font-size:20px;font-weight:900;margin-top:6px}
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800;background:#f3f4f6}
        </style>
        </head>
        <body>
        <div class="card">
            <div class="h">Estado de cuenta · {$period}</div>
            <div class="row">
            <div class="col">
                <div class="k">Alias</div>
                <div class="v">{$alias}</div>
            </div>
            <div class="col">
                <div class="k">RFC</div>
                <div class="v">{$rfc}</div>
            </div>
            <div class="col">
                <div class="k">Periodo</div>
                <div class="v">{$range}</div>
            </div>
            <div class="col">
                <div class="k">Estatus</div>
                <div class="v"><span class="badge">{$status}</span></div>
            </div>
            <div class="col">
                <div class="k">Importe</div>
                <div class="amt">{$amount}</div>
            </div>
            </div>
        </div>
        </body>
        </html>
        HTML;
            }
}
