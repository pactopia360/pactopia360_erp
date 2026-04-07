<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\Billing\BillingStatementsController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Billing\Concerns\HandlesStatementOverridesAndPeriods;
use App\Http\Controllers\Admin\Billing\Concerns\HandlesStatementPayments;
use App\Http\Controllers\Admin\Billing\Concerns\PaymentHelper;
use App\Mail\Admin\Billing\StatementAccountPeriodMail;
use BaconQrCode\Renderer\Image\GdImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Stripe\StripeClient;

final class BillingStatementsController extends Controller
{
    use HandlesStatementOverridesAndPeriods;
    use HandlesStatementPayments;
    use PaymentHelper;

    private string $adm;
    private StripeClient $stripe;
    private BillingStatementsHubController $hub;

    /** @var array<string, string|null> */
    private array $cacheLastPaid = [];

    public function __construct(BillingStatementsHubController $hub)
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        $secret = (string) config('services.stripe.secret');
        $this->stripe = new StripeClient($secret ?: '');

        $this->hub = $hub;
    }

    // =========================================================
    // INDEX
    // =========================================================

    public function index(Request $req): View
    {
        return $this->hub->index($req);
    }

    /**
     * @param \Illuminate\Support\Collection<int, mixed> $items
     */
    private function repaginateCollection($items, int $perPage, int $page, string $path, array $query): LengthAwarePaginator
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

    // =========================================================
    // SHOW
    // =========================================================

        public function show(Request $req, string $accountId, string $period): View
    {
        abort_if(!$this->isValidPeriod($period), 422);

        abort_unless(Schema::connection($this->adm)->hasTable('accounts'), 404);
        abort_unless(Schema::connection($this->adm)->hasTable('estados_cuenta'), 404);

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        abort_unless($acc, 404);

        $items = DB::connection($this->adm)->table('estados_cuenta')
            ->where('account_id', $accountId)
            ->where('periodo', '=', $period)
            ->orderBy('id')
            ->get();

        $cargoEdo = (float) $items->sum('cargo');
        $abonoEdo = (float) $items->sum('abono');
        $abonoPay = (float) $this->sumPaymentsForAccountPeriod($accountId, $period);

        $meta = $this->hub->decodeMeta($acc->meta ?? null);
        if (!is_array($meta)) {
            $meta = [];
        }

        $lastPaid = $this->resolveLastPaidPeriodForAccount((string) $accountId, $meta);

        $payAllowed = $lastPaid
            ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
            : $period;

        $customMxn = $this->extractCustomAmountMxn($acc, $meta);

        if ($customMxn !== null && $customMxn > 0.00001) {
            $expected    = $customMxn;
            $tarifaLabel = 'PERSONALIZADO';
            $tarifaPill  = 'info';
        } else {
            [$expected, $tarifaLabel, $tarifaPill] = $this->safeResolveEffectiveAmountFromMeta($meta, $period, $payAllowed);
        }

        $stmtCfg = $this->getStatementConfigFromMeta($meta, $period);

        $hasStatement = Schema::connection($this->adm)->hasTable('billing_statements')
            && DB::connection($this->adm)->table('billing_statements')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->exists();

        $inject  = $this->shouldInjectServiceLine($items, (float) $expected, $stmtCfg['mode'] ?? 'monthly');

        $serviceLine = null;
        if ($inject && !$hasStatement) {
            $mode = $this->resolveBillingModeFromMetaOrPlan($meta, $acc);
            $serviceName = ($mode === 'anual') ? 'Servicio anual' : 'Servicio mensual';

            $serviceLine = (object) [
                'id'          => 0,
                'account_id'  => $accountId,
                'periodo'     => $period,
                'concepto'    => $serviceName,
                'detalle'     => 'Servicio base (auto)',
                'cargo'       => round((float) $expected, 2),
                'abono'       => 0.0,
                'saldo'       => null,
                'created_at'  => null,
                'updated_at'  => null,
                '__synthetic' => true,
            ];
        }

        $itemsUi = $items;
        if ($serviceLine) {
            $itemsUi = collect([$serviceLine])->merge($items)->values();
        }

        $calc = $this->recalcStatementFromPayments((string) $accountId, $period);

        $totalShown = (float) ($calc['cargo'] ?? 0.0);
        $abonoTot   = (float) ($calc['abono'] ?? 0.0);
        $saldoShown = (float) ($calc['saldo'] ?? 0.0);
        $statusPago = (string) ($calc['status'] ?? 'pendiente');

        if ($totalShown <= 0.00001) {
            $fallbackCargo = $cargoEdo + ($serviceLine ? (float) $serviceLine->cargo : 0.0);
            $totalShown = round((float) ($fallbackCargo > 0 ? $fallbackCargo : $expected), 2);
            $saldoShown = round(max(0.0, $totalShown - $abonoPay), 2);
            $abonoTot   = round((float) $abonoPay, 2);
            $statusPago = $this->computeFinancialStatus($totalShown, $abonoTot, 0.0);
        }

        $prevInfo   = $this->computePrevOpenBalance((string) $accountId, (string) $period, $lastPaid);
        $prevPeriod = $prevInfo['prev_period'] ?? null;
        $prevSaldo  = round(max(0.0, (float) ($prevInfo['prev_balance'] ?? 0.0)), 2);
        $totalDue   = round(max(0.0, (float) $saldoShown + $prevSaldo), 2);

        $statusPago = $this->computeFinancialStatus(
            (float) $totalShown,
            (float) $abonoTot,
            (float) $prevSaldo
        );

        $row = (object) [
            'cargo'            => round($cargoEdo, 2),
            'expected_total'   => round((float) $expected, 2),
            'total_shown'      => round((float) $totalShown, 2),
            'abono'            => round((float) $abonoTot, 2),
            'abono_edo'        => round((float) $abonoEdo, 2),
            'abono_pay'        => round((float) $abonoPay, 2),
            'saldo'            => round((float) $saldoShown, 2),
            'saldo_shown'      => round((float) $saldoShown, 2),
            'saldo_current'    => round((float) $saldoShown, 2),
            'prev_balance'     => $prevSaldo,
            'total_due'        => $totalDue,
            'tarifa_label'     => (string) $tarifaLabel,
            'tarifa_pill'      => (string) $tarifaPill,
            'status_pago'      => $statusPago,
            'status_auto'      => $statusPago,
            'last_paid'        => $lastPaid,
            'pay_allowed'      => $payAllowed,
            'pay_last_paid_at' => null,
            'pay_due_date'     => null,
            'pay_method'       => null,
            'pay_provider'     => null,
            'pay_status'       => $statusPago,
            'prev_period'      => $prevPeriod,
        ];

        $ov = $this->fetchStatusOverridesForAccountsPeriod([$accountId], $period);
        $row = $this->applyStatusOverride($row, $ov[(string) $accountId] ?? null);

        $recipients = $this->resolveRecipientsForAccount((string) $accountId, (string) ($acc->email ?? ''));

        return view('admin.billing.statements.show', [
            'account'          => $acc,
            'period'           => $period,
            'period_label'     => Str::title(Carbon::parse($period . '-01')->translatedFormat('F Y')),
            'items'            => $itemsUi,
            'cargo_real'       => round($cargoEdo, 2),
            'expected_total'   => round((float) ($row->expected_total ?? $expected), 2),
            'tarifa_label'     => (string) ($row->tarifa_label ?? $tarifaLabel),
            'tarifa_pill'      => (string) ($row->tarifa_pill ?? $tarifaPill),
            'abono'            => round((float) ($row->abono ?? $abonoTot), 2),
            'abono_edo'        => round((float) ($row->abono_edo ?? $abonoEdo), 2),
            'abono_pay'        => round((float) ($row->abono_pay ?? $abonoPay), 2),
            'total'            => round((float) ($row->total_shown ?? $totalShown), 2),
            'saldo'            => round((float) ($row->saldo ?? $saldoShown), 2),
            'prev_balance'     => round((float) ($row->prev_balance ?? 0), 2),
            'total_due'        => round((float) ($row->total_due ?? $saldoShown), 2),
            'last_paid'        => $lastPaid,
            'pay_allowed'      => $payAllowed,
            'status_pago'      => (string) ($row->status_pago ?? $statusPago),
            'pay_method'       => $row->pay_method ?? null,
            'pay_provider'     => $row->pay_provider ?? null,
            'pay_status'       => $row->pay_status ?? null,
            'pay_last_paid_at' => $row->pay_last_paid_at ?? null,
            'statement_cfg'    => $stmtCfg,
            'recipients'       => $recipients,
            'meta'             => $meta,
            'isModal'          => $req->boolean('modal'),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function computeStatementTotalsForPeriod(string $accountId, string $period, bool $forPrevScan = false): array
    {
        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        abort_unless($acc, 404);

        $items = DB::connection($this->adm)->table('estados_cuenta')
            ->where('account_id', $accountId)
            ->where('periodo', '=', $period)
            ->orderBy('id')
            ->get();

        $stmt = $this->getBillingStatementSnapshot($accountId, $period);

        $cargoEdo = (float) $items->sum('cargo');
        $abonoEdo = (float) $items->sum('abono');
        $abonoPay = (float) $this->sumPaymentsForAccountPeriod($accountId, $period);

        $meta = $this->hub->decodeMeta($acc->meta ?? null);
        if (!is_array($meta)) {
            $meta = [];
        }

        $lastPaid = $this->resolveLastPaidPeriodForAccount((string) $accountId, $meta);

        $payAllowed = $lastPaid
            ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
            : $period;

        $customMxn = $this->extractCustomAmountMxn($acc, $meta);

        if ($customMxn !== null && $customMxn > 0.00001) {
            $expectedTotal = $customMxn;
            $tarifaLabel   = 'PERSONALIZADO';
            $tarifaPill    = 'info';
        } else {
            [$expectedTotal, $tarifaLabel, $tarifaPill] = $this->safeResolveEffectiveAmountFromMeta($meta, $period, $payAllowed);
        }

        $stmtCfg = $this->getStatementConfigFromMeta($meta, $period);

       $hasEvidence = $items->count() > 0 || $stmt !== null;

        if (!$hasEvidence && $this->hasPaymentsForAccountPeriod($accountId, $period)) {
            $hasEvidence = true;
        }

        if (!$hasEvidence && $this->hasOverrideForAccountPeriod($accountId, $period)) {
            $hasEvidence = true;
        }

        if ($stmt) {
            $inject = false; // ✅ si existe billing_statements, no inventar línea base
        } elseif ($forPrevScan && !$hasEvidence) {
            $inject = false;
        } else {
            $inject = $this->shouldInjectServiceLine($items, (float) $expectedTotal, $stmtCfg['mode'] ?? 'monthly');
        }

        $mode = $this->resolveBillingModeFromMetaOrPlan($meta, $acc);
        $serviceName = ($mode === 'anual') ? 'Servicio anual' : 'Servicio mensual';

        $consumos = [];

        if ($inject) {
            $consumos[] = [
                'service'   => $serviceName,
                'unit_cost' => round((float) $expectedTotal, 2),
                'qty'       => 1,
                'subtotal'  => round((float) $expectedTotal, 2),
            ];
        }

        foreach ($items as $it) {
            $cargoIt = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
            if ($cargoIt <= 0.00001) {
                continue;
            }

            $concepto = trim((string) ($it->concepto ?? ''));
            if ($concepto === '') {
                $concepto = 'Cargo';
            }

            $consumos[] = [
                'service'   => $concepto,
                'unit_cost' => round($cargoIt, 2),
                'qty'       => 1,
                'subtotal'  => round($cargoIt, 2),
            ];
        }

        $totalConsumos = 0.0;
        foreach ($consumos as $c) {
            $totalConsumos += (float) ($c['subtotal'] ?? 0);
        }

        $baseCargo = round($totalConsumos, 2);

        $calc = $this->recalcStatementFromPayments($accountId, $period);

        $cargoShown = round((float) ($calc['cargo'] ?? 0.0), 2);
        $abonoTot   = round((float) ($calc['abono'] ?? 0.0), 2);
        $saldo      = round((float) ($calc['saldo'] ?? 0.0), 2);

        if ($cargoShown <= 0.00001) {
            $cargoShown = $baseCargo > 0.00001 ? $baseCargo : round((float) $expectedTotal, 2);
            $abonoTot   = round((float) $abonoPay, 2);
            $saldo      = round(max(0.0, $cargoShown - $abonoTot), 2);
        }

        return [
            'account'        => $acc,
            'items'          => $items,
            'consumos'       => $consumos,
            'consumos_total' => round($totalConsumos, 2),
            'cargo_real'     => round($cargoEdo, 2),
            'expected_total' => round((float) $expectedTotal, 2),
            'tarifa_label'   => (string) $tarifaLabel,
            'tarifa_pill'    => (string) $tarifaPill,
            'cargo'          => $cargoShown,
            'abono'          => round((float) $abonoTot, 2),
            'abono_edo'      => round((float) $abonoEdo, 2),
            'abono_pay'      => round((float) $abonoPay, 2),
            'saldo'          => $saldo,
            'last_paid'      => $lastPaid,
            'pay_allowed'    => $payAllowed,
            'statement_cfg'  => $stmtCfg,
        ];
    }

    private function hasStatementEvidence(string $accountId, string $period): bool
    {
        $accountId = trim($accountId);
        $period    = trim($period);

        if ($accountId === '' || !$this->isValidPeriod($period)) {
            return false;
        }

        try {
            // 1) Fuente principal: billing_statements
            if (Schema::connection($this->adm)->hasTable('billing_statements')) {
                $existsStatement = DB::connection($this->adm)->table('billing_statements')
                    ->where('account_id', $accountId)
                    ->where('period', $period)
                    ->limit(1)
                    ->exists();

                if ($existsStatement) {
                    return true;
                }
            }

            // 2) Evidencia legacy: estados_cuenta
            if (Schema::connection($this->adm)->hasTable('estados_cuenta')) {
                $existsEstado = DB::connection($this->adm)->table('estados_cuenta')
                    ->where('account_id', $accountId)
                    ->where('periodo', $period)
                    ->limit(1)
                    ->exists();

                if ($existsEstado) {
                    return true;
                }
            }

            // 3) Evidencia por pagos del periodo
            if ($this->hasPaymentsForAccountPeriod($accountId, $period)) {
                return true;
            }

            // 4) Evidencia por override manual
            if (Schema::connection($this->adm)->hasTable($this->overrideTable())) {
                $existsOverride = DB::connection($this->adm)->table($this->overrideTable())
                    ->where('account_id', $accountId)
                    ->where('period', $period)
                    ->limit(1)
                    ->exists();

                if ($existsOverride) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] hasStatementEvidence failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
            return false;
        }

        return false;
    }

    /**
     * @return array{prev_period:?string, prev_balance:float}
     */
    private function computePrevOpenBalance(string $accountId, string $period, ?string $lastPaid, int $maxMonths = 24): array
    {
        $accountId = trim($accountId);
        $period    = trim($period);
        $lastPaid  = $lastPaid !== null ? trim($lastPaid) : null;

        if ($accountId === '' || !$this->isValidPeriod($period)) {
            return ['prev_period' => null, 'prev_balance' => 0.0];
        }

        $maxMonths = max(1, min(60, $maxMonths));
        $prevBalance = 0.0;
        $prevPeriodMostRecent = null;

        try {
            $current = Carbon::createFromFormat('Y-m', $period)->startOfMonth();

            for ($i = 1; $i <= $maxMonths; $i++) {
                $scanPeriod = $current->copy()->subMonthsNoOverflow($i)->format('Y-m');

                if (!$this->isValidPeriod($scanPeriod)) {
                    continue;
                }

                if ($lastPaid && $this->isValidPeriod($lastPaid) && $scanPeriod <= $lastPaid) {
                    break;
                }

                if (!$this->hasStatementEvidence($accountId, $scanPeriod)) {
                    continue;
                }

                if ($this->isPeriodPaidByOverride($accountId, $scanPeriod)) {
                    continue;
                }

                $totals = $this->computeStatementTotalsForPeriod($accountId, $scanPeriod, true);
                $saldo  = round((float) ($totals['saldo'] ?? 0.0), 2);

                if ($saldo <= 0.00001) {
                    continue;
                }

                $prevBalance += $saldo;

                if ($prevPeriodMostRecent === null) {
                    $prevPeriodMostRecent = $scanPeriod;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] computePrevOpenBalance failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'last_paid'  => $lastPaid,
                'err'        => $e->getMessage(),
            ]);

            return ['prev_period' => null, 'prev_balance' => 0.0];
        }

        return [
            'prev_period'  => $prevPeriodMostRecent,
            'prev_balance' => round(max(0.0, $prevBalance), 2),
        ];
    }

    // =========================================================
    // PDF / EMAIL DATA
    // =========================================================

        /**
     * Construye líneas detalladas del PDF admin:
     * - una línea por cada billing_statement pendiente hasta el periodo solicitado
     * - separa saldo anterior vs periodo actual
     * - usa montos con IVA incluido; el blade ya los divide para mostrar tabla sin IVA
     *
     * @return array{
     *   service_items: array<int,array<string,mixed>>,
     *   prev_period: ?string,
     *   prev_period_label: ?string,
     *   prev_balance: float,
     *   current_period_due: float,
     *   total_due: float
     * }
     */
    private function buildDetailedPendingLinesForPdf(string $accountId, string $period): array
    {
        $accountId = trim($accountId);
        $period    = trim($period);

        if ($accountId === '' || !$this->isValidPeriod($period)) {
            return [
                'service_items'      => [],
                'prev_period'        => null,
                'prev_period_label'  => null,
                'prev_balance'       => 0.0,
                'current_period_due' => 0.0,
                'total_due'          => 0.0,
            ];
        }

        $serviceItems = [];
        $prevBalance = 0.0;
        $currentPeriodDue = 0.0;
        $prevPeriodMostRecent = null;
        $prevPeriodLabelMostRecent = null;

        try {
            if (!Schema::connection($this->adm)->hasTable('billing_statements')) {
                return [
                    'service_items'      => [],
                    'prev_period'        => null,
                    'prev_period_label'  => null,
                    'prev_balance'       => 0.0,
                    'current_period_due' => 0.0,
                    'total_due'          => 0.0,
                ];
            }

            $rows = DB::connection($this->adm)->table('billing_statements')
                ->where('account_id', $accountId)
                ->where('period', '<=', $period)
                ->orderBy('period')
                ->get([
                    'id',
                    'period',
                    'status',
                    'total_cargo',
                    'total_abono',
                    'saldo',
                ]);

            foreach ($rows as $st) {
                $rowPeriod = trim((string) ($st->period ?? ''));
                if (!$this->isValidPeriod($rowPeriod)) {
                    continue;
                }

                $status = strtolower(trim((string) ($st->status ?? 'pending')));
                $status = match ($status) {
                    'paid', 'pagado', 'succeeded', 'success', 'complete', 'completed', 'captured', 'confirmed' => 'pagado',
                    'partial', 'parcial' => 'parcial',
                    'overdue', 'vencido', 'past_due', 'unpaid' => 'vencido',
                    'sin_mov', 'sin mov', 'sin_movimiento', 'sin movimiento', 'no_movement', 'no movement' => 'sin_mov',
                    default => 'pendiente',
                };

                if ($status === 'pagado') {
                    continue;
                }

                $saldo = is_numeric($st->saldo ?? null)
                    ? (float) $st->saldo
                    : max(
                        0.0,
                        (float) ($st->total_cargo ?? 0) - (float) ($st->total_abono ?? 0)
                    );

                $saldo = round(max(0.0, $saldo), 2);

                if ($saldo <= 0.00001) {
                    continue;
                }

                $label = $rowPeriod;
                try {
                    $label = Str::title(Carbon::parse($rowPeriod . '-01')->translatedFormat('F Y'));
                } catch (\Throwable $e) {
                    $label = $rowPeriod;
                }

                $serviceItems[] = [
                    'service'   => 'Mensualidad ' . $label,
                    'name'      => 'Mensualidad ' . $label,
                    'unit_cost' => $saldo,
                    'unit_price'=> $saldo,
                    'qty'       => 1,
                    'subtotal'  => $saldo,
                    'period'    => $rowPeriod,
                ];

                if ($rowPeriod === $period) {
                    $currentPeriodDue += $saldo;
                } else {
                    $prevBalance += $saldo;
                    $prevPeriodMostRecent = $rowPeriod;
                    $prevPeriodLabelMostRecent = $label;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] buildDetailedPendingLinesForPdf failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
        }

        $prevBalance = round(max(0.0, $prevBalance), 2);
        $currentPeriodDue = round(max(0.0, $currentPeriodDue), 2);
        $totalDue = round(max(0.0, $prevBalance + $currentPeriodDue), 2);

        return [
            'service_items'      => $serviceItems,
            'prev_period'        => $prevPeriodMostRecent,
            'prev_period_label'  => $prevPeriodLabelMostRecent,
            'prev_balance'       => $prevBalance,
            'current_period_due' => $currentPeriodDue,
            'total_due'          => $totalDue,
        ];
    }

     /**
     * @return array<string,mixed>
     */
    private function buildStatementData(string $accountId, string $period): array
    {
        $cur   = $this->computeStatementTotalsForPeriod($accountId, $period);
        $acc   = $cur['account'];
        $items = $cur['items'];

        $cargoShown = (float) ($cur['cargo'] ?? 0.0);
        $abonoTot   = (float) ($cur['abono'] ?? 0.0);
        $saldoCur   = (float) ($cur['saldo'] ?? 0.0);

        $detail = $this->buildDetailedPendingLinesForPdf((string) $accountId, (string) $period);

        $detailedItems     = is_array($detail['service_items'] ?? null) ? $detail['service_items'] : [];
        $prevPeriod        = $detail['prev_period'] ?? null;
        $prevPeriodLabel   = $detail['prev_period_label'] ?? null;
        $prevSaldo         = round(max(0.0, (float) ($detail['prev_balance'] ?? 0.0)), 2);
        $currentPeriodDue  = round(max(0.0, (float) ($detail['current_period_due'] ?? 0.0)), 2);
        $totalDue          = round(max(0.0, (float) ($detail['total_due'] ?? 0.0)), 2);

        // Fallback si por alguna razón no pudo construir líneas detalladas
        if (empty($detailedItems)) {
            $prevInfo   = $this->computePrevOpenBalance((string) $accountId, (string) $period, $cur['last_paid'] ?? null);
            $prevPeriod = $prevInfo['prev_period'] ?? null;
            $prevSaldo  = round(max(0.0, (float) ($prevInfo['prev_balance'] ?? 0.0)), 2);

            $currentPeriodDue = round(max(0.0, $saldoCur), 2);
            $totalDue         = round(max(0.0, $currentPeriodDue + $prevSaldo), 2);

            if ($currentPeriodDue > 0.00001) {
                $label = Str::title(Carbon::parse($period . '-01')->translatedFormat('F Y'));
                $detailedItems[] = [
                    'service'   => 'Mensualidad ' . $label,
                    'name'      => 'Mensualidad ' . $label,
                    'unit_cost' => $currentPeriodDue,
                    'unit_price'=> $currentPeriodDue,
                    'qty'       => 1,
                    'subtotal'  => $currentPeriodDue,
                    'period'    => $period,
                ];
            }

            if ($prevPeriod && !$prevPeriodLabel) {
                try {
                    $prevPeriodLabel = Str::title(Carbon::parse($prevPeriod . '-01')->translatedFormat('F Y'));
                } catch (\Throwable $e) {
                    $prevPeriodLabel = $prevPeriod;
                }
            }
        }

        $statusBase = $this->computeFinancialStatus($cargoShown, $abonoTot, $prevSaldo);

        $row = (object) [
            'cargo'            => round((float) ($cur['cargo_real'] ?? 0.0), 2),
            'expected_total'   => round((float) ($cur['expected_total'] ?? 0.0), 2),
            'total_shown'      => round($cargoShown, 2),
            'abono'            => round($abonoTot, 2),
            'abono_edo'        => round((float) ($cur['abono_edo'] ?? 0.0), 2),
            'abono_pay'        => round((float) ($cur['abono_pay'] ?? 0.0), 2),
            'saldo'            => round(max(0.0, $saldoCur), 2),
            'saldo_shown'      => round(max(0.0, $saldoCur), 2),
            'saldo_current'    => round(max(0.0, $currentPeriodDue), 2),
            'prev_balance'     => $prevSaldo,
            'total_due'        => $totalDue,
            'tarifa_label'     => (string) ($cur['tarifa_label'] ?? '-'),
            'tarifa_pill'      => (string) ($cur['tarifa_pill'] ?? 'dim'),
            'status_pago'      => $statusBase,
            'status_auto'      => $statusBase,
            'last_paid'        => $cur['last_paid'] ?? null,
            'pay_allowed'      => $cur['pay_allowed'] ?? null,
            'pay_last_paid_at' => null,
            'pay_due_date'     => null,
            'pay_method'       => null,
            'pay_provider'     => null,
            'pay_status'       => $statusBase,
        ];

        $ov = $this->fetchStatusOverridesForAccountsPeriod([$accountId], $period);
        $row = $this->applyStatusOverride($row, $ov[(string) $accountId] ?? null);

        $finalSaldoCurrent = round(max(0.0, (float) ($row->saldo_current ?? $currentPeriodDue)), 2);
        $finalPrevBalance  = round(max(0.0, (float) ($row->prev_balance ?? $prevSaldo)), 2);
        $finalTotalDue     = round(max(0.0, (float) ($row->total_due ?? ($finalSaldoCurrent + $finalPrevBalance))), 2);

        $effectivePrevPeriod = $finalPrevBalance > 0.00001 ? $prevPeriod : null;
        $effectivePrevPeriodLabel = $finalPrevBalance > 0.00001 ? $prevPeriodLabel : null;

        $qrText = $this->resolveQrTextForStatement($accountId, $period, null);
        [$qrDataUri, $qrUrl] = $this->makeQrDataForText((string) ($qrText ?? ''));

        return [
            'account'            => $acc,
            'account_id'         => $accountId,
            'period'             => $period,
            'period_label'       => Str::title(Carbon::parse($period . '-01')->translatedFormat('F Y')),
            'items'              => $items,
            'consumos'           => $detailedItems,
            'service_items'      => $detailedItems,
            'consumos_total'     => round((float) $finalTotalDue, 2),
            'cargo_real'         => round((float) ($cur['cargo_real'] ?? 0), 2),
            'expected_total'     => round((float) ($row->expected_total ?? $cur['expected_total'] ?? 0), 2),
            'tarifa_label'       => (string) ($row->tarifa_label ?? $cur['tarifa_label'] ?? '-'),
            'tarifa_pill'        => (string) ($row->tarifa_pill ?? $cur['tarifa_pill'] ?? 'dim'),
            'cargo'              => round((float) ($row->total_shown ?? $cargoShown), 2),
            'abono'              => round((float) ($row->abono ?? $abonoTot), 2),
            'abono_edo'          => round((float) ($row->abono_edo ?? $cur['abono_edo'] ?? 0), 2),
            'abono_pay'          => round((float) ($row->abono_pay ?? $cur['abono_pay'] ?? 0), 2),
            'saldo'              => round((float) ($row->saldo ?? $saldoCur), 2),
            'prev_period'        => $effectivePrevPeriod,
            'prev_period_label'  => $effectivePrevPeriodLabel,
            'prev_balance'       => $finalPrevBalance,
            'current_period_due' => $finalSaldoCurrent,
            'total_due'          => $finalTotalDue,
            'total'              => $finalTotalDue,
            'generated_at'       => now(),
            'last_paid'          => $cur['last_paid'] ?? null,
            'pay_allowed'        => $cur['pay_allowed'] ?? null,
            'status_pago'        => (string) ($row->status_pago ?? 'pendiente'),
            'status_auto'        => (string) ($row->status_auto ?? 'pendiente'),
            'pay_method'         => $row->pay_method ?? null,
            'pay_provider'       => $row->pay_provider ?? null,
            'pay_status'         => $row->pay_status ?? null,
            'pay_last_paid_at'   => $row->pay_last_paid_at ?? null,
            'pay_url'            => $qrText,
            'qr_data_uri'        => $qrDataUri,
            'qr_url'             => $qrUrl,
        ];
    }

    // =========================================================
    // CRUD MOVIMIENTOS
    // =========================================================

    public function addItem(Request $req, string $accountId, string $period): RedirectResponse
    {
        abort_if(!$this->isValidPeriod($period), 422);

        $data = $req->validate([
            'concepto'   => 'required|string|max:255',
            'detalle'    => 'nullable|string|max:2000',
            'cargo'      => 'nullable|numeric|min:0|max:99999999',
            'abono'      => 'nullable|numeric|min:0|max:99999999',
            'tipo'       => 'nullable|string|in:cargo,abono',
            'monto'      => 'nullable|numeric|min:0|max:99999999',
            'send_email' => 'nullable|boolean',
            'to'         => 'nullable|string|max:2000',
        ]);

        [$cargo, $abono] = $this->resolveCargoAbonoFromRequest($data);

        if ($cargo <= 0 && $abono <= 0) {
            return back()->withErrors(['monto' => 'Debes capturar cargo o abono.']);
        }

        DB::connection($this->adm)->transaction(function () use ($accountId, $period, $data, $cargo, $abono) {
            DB::connection($this->adm)->table('estados_cuenta')->insert([
                'account_id' => $accountId,
                'periodo'    => $period,
                'concepto'   => $data['concepto'],
                'detalle'    => $data['detalle'] ?? null,
                'cargo'      => round($cargo, 2),
                'abono'      => round($abono, 2),
                'saldo'      => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->recalcStatementSaldoIfPossible($accountId, $period);
        });

        if (($data['send_email'] ?? false) === true) {
            $to = trim((string) ($data['to'] ?? ''));
            $this->sendStatementEmailWithPayLink($accountId, $period, $to !== '' ? $to : null);
        }

        return back()->with('ok', 'Movimiento agregado.');
    }

    public function lineStore(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => 'required|string',
            'concepto'   => 'required|string|max:255',
            'detalle'    => 'nullable|string|max:2000',
            'cargo'      => 'nullable|numeric|min:0|max:99999999',
            'abono'      => 'nullable|numeric|min:0|max:99999999',
            'tipo'       => 'nullable|string|in:cargo,abono',
            'monto'      => 'nullable|numeric|min:0|max:99999999',
        ]);

        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];

        abort_if(!$this->isValidPeriod($period), 422);

        [$cargo, $abono] = $this->resolveCargoAbonoFromRequest($data);

        if ($cargo <= 0 && $abono <= 0) {
            return back()->withErrors(['monto' => 'Debes capturar cargo o abono.']);
        }

        DB::connection($this->adm)->transaction(function () use ($accountId, $period, $data, $cargo, $abono) {
            DB::connection($this->adm)->table('estados_cuenta')->insert([
                'account_id' => $accountId,
                'periodo'    => $period,
                'concepto'   => $data['concepto'],
                'detalle'    => $data['detalle'] ?? null,
                'cargo'      => round($cargo, 2),
                'abono'      => round($abono, 2),
                'saldo'      => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->recalcStatementSaldoIfPossible($accountId, $period);
        });

        return back()->with('ok', 'Línea agregada.');
    }

    public function lineUpdate(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'id'         => 'required|integer|min:1',
            'account_id' => 'required|string|max:64',
            'period'     => 'required|string',
            'concepto'   => 'required|string|max:255',
            'detalle'    => 'nullable|string|max:2000',
            'cargo'      => 'nullable|numeric|min:0|max:99999999',
            'abono'      => 'nullable|numeric|min:0|max:99999999',
            'tipo'       => 'nullable|string|in:cargo,abono',
            'monto'      => 'nullable|numeric|min:0|max:99999999',
        ]);

        $id        = (int) $data['id'];
        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];

        abort_if(!$this->isValidPeriod($period), 422);

        [$cargo, $abono] = $this->resolveCargoAbonoFromRequest($data);

        if ($cargo <= 0 && $abono <= 0) {
            return back()->withErrors(['monto' => 'Debes capturar cargo o abono.']);
        }

        DB::connection($this->adm)->transaction(function () use ($id, $accountId, $period, $data, $cargo, $abono) {
            $q = DB::connection($this->adm)->table('estados_cuenta')->where('id', $id);
            $q->where('account_id', $accountId)->where('periodo', $period);

            $updated = $q->update([
                'concepto'   => $data['concepto'],
                'detalle'    => $data['detalle'] ?? null,
                'cargo'      => round($cargo, 2),
                'abono'      => round($abono, 2),
                'updated_at' => now(),
            ]);

            if (!$updated) {
                throw new \RuntimeException('No se encontró la línea para actualizar (id/account/period).');
            }

            $this->recalcStatementSaldoIfPossible($accountId, $period);
        });

        return back()->with('ok', 'Línea actualizada.');
    }

    public function lineDelete(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'id'         => 'required|integer|min:1',
            'account_id' => 'required|string|max:64',
            'period'     => 'required|string',
        ]);

        $id        = (int) $data['id'];
        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];

        abort_if(!$this->isValidPeriod($period), 422);

        DB::connection($this->adm)->transaction(function () use ($id, $accountId, $period) {
            $q = DB::connection($this->adm)->table('estados_cuenta')->where('id', $id);
            $q->where('account_id', $accountId)->where('periodo', $period);

            $deleted = $q->delete();

            if (!$deleted) {
                throw new \RuntimeException('No se encontró la línea para eliminar (id/account/period).');
            }

            $this->recalcStatementSaldoIfPossible($accountId, $period);
        });

        return back()->with('ok', 'Línea eliminada.');
    }

    // =========================================================
    // SAVE CONFIG + RECIPIENTS
    // =========================================================

    public function saveStatement(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => 'required|string',
            'mode'       => 'nullable|string|in:unique,monthly,unica,mensual,única',
            'notes'      => 'nullable|string|max:4000',
            'recipients' => 'nullable|string|max:8000',
            'to'         => 'nullable|string|max:8000',
        ]);

        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];

        abort_if(!$this->isValidPeriod($period), 422);
        abort_unless(Schema::connection($this->adm)->hasTable('accounts'), 404);

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        if (!$acc) {
            return back()->withErrors(['account' => 'Cuenta no encontrada.']);
        }

        $cols = Schema::connection($this->adm)->getColumnListing('accounts');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

        $modeRaw = (string) ($data['mode'] ?? 'monthly');
        $mode    = $this->normalizeStatementMode($modeRaw);
        $notes   = trim((string) ($data['notes'] ?? ''));

        if ($has('meta')) {
            $meta = $this->hub->decodeMeta($acc->meta ?? null);
            if (!is_array($meta)) {
                $meta = [];
            }

            if (!isset($meta['billing']) || !is_array($meta['billing'])) {
                $meta['billing'] = [];
            }
            if (!isset($meta['billing']['statements']) || !is_array($meta['billing']['statements'])) {
                $meta['billing']['statements'] = [];
            }

            $meta['billing']['statements'][$period] = [
                'mode'       => $mode,
                'notes'      => $notes !== '' ? $notes : null,
                'updated_at' => now()->toDateTimeString(),
                'by'         => auth('admin')->id() ?: null,
            ];

            DB::connection($this->adm)->table('accounts')
                ->where('id', $accountId)
                ->update([
                    'meta'       => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }

        $rawRecipients = trim((string) ($data['recipients'] ?? ''));
        if ($rawRecipients === '') {
            $rawRecipients = trim((string) ($data['to'] ?? ''));
        }

        if (Schema::connection($this->adm)->hasTable('account_recipients') && $rawRecipients !== '') {
            $emails = $this->normalizeRecipientList($rawRecipients);

            DB::connection($this->adm)->transaction(function () use ($accountId, $emails) {
                DB::connection($this->adm)->table('account_recipients')
                    ->where('account_id', $accountId)
                    ->update([
                        'is_active'  => 0,
                        'updated_at' => now(),
                    ]);

                foreach ($emails as $i => $email) {
                    $existing = DB::connection($this->adm)->table('account_recipients')
                        ->where('account_id', $accountId)
                        ->where('email', $email)
                        ->first();

                    $payload = [
                        'account_id' => $accountId,
                        'email'      => $email,
                        'is_active'  => 1,
                        'is_primary' => $i === 0 ? 1 : 0,
                        'updated_at' => now(),
                    ];

                    if ($existing) {
                        DB::connection($this->adm)->table('account_recipients')
                            ->where('id', (int) $existing->id)
                            ->update($payload);
                    } else {
                        $payload['created_at'] = now();
                        DB::connection($this->adm)->table('account_recipients')->insert($payload);
                    }
                }
            });
        }

        return back()->with('ok', 'Estado de cuenta guardado (config + destinatarios).');
    }

    // =========================================================
    // STATUS AJAX
    // =========================================================

  public function statusAjax(Request $req): JsonResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => 'required|string',
            'status'     => 'required|string|in:pendiente,parcial,pagado,vencido,sin_mov',
            'pay_method' => 'nullable|string|max:30',
            'paid_at'    => 'nullable|string',
        ]);

        $accountId   = trim((string) $data['account_id']);
        $period      = trim((string) $data['period']);
        $status      = strtolower(trim((string) $data['status']));
        $payMethod   = trim((string) ($data['pay_method'] ?? '')) ?: 'manual';
        $payProvider = in_array($payMethod, ['stripe', 'card'], true) ? 'stripe' : 'manual';

        if ($status === 'pagado') {
            return response()->json([
                'ok'      => false,
                'message' => 'No puedes marcar "pagado" desde estatus visual. Registra un pago real manual para conciliar payments y billing_statements.',
            ], 422);
        }

        if (!$this->isValidPeriod($period)) {
            return response()->json(['ok' => false, 'message' => 'Periodo inválido'], 422);
        }

        if ($status === 'pagado') {
            return response()->json([
                'ok'      => false,
                'message' => 'No puedes marcar "pagado" desde estatus visual. Registra un pago real manual para conciliar payments y billing_statements.',
            ], 422);
        }

        if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
            return response()->json(['ok' => false, 'message' => 'Tabla overrides no existe'], 422);
        }

        $paidAt = null;
        if ($status === 'pagado') {
            $paidAt = $this->parsePaidAtFromRequest((string) ($data['paid_at'] ?? '')) ?: now();
        }

        $by = auth('admin')->id();

        DB::connection($this->adm)->transaction(function () use (
            $accountId, $period, $status, $by, $payMethod, $payProvider, $paidAt
        ) {
            $table = $this->overrideTable();

            $row = DB::connection($this->adm)->table($table)
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->first(['id', 'meta']);

            $meta = [];
            if ($row && !empty($row->meta)) {
                try {
                    $meta = json_decode((string) $row->meta, true);
                    if (!is_array($meta)) {
                        $meta = [];
                    }
                } catch (\Throwable $e) {
                    $meta = [];
                }
            }

            $meta['pay_method']   = $payMethod;
            $meta['pay_provider'] = $payProvider;
            $meta['pay_status']   = $status;
            $meta['paid_at']      = $status === 'pagado' ? $paidAt?->toDateTimeString() : null;

            $payload = [
                'account_id'      => $accountId,
                'period'          => $period,
                'status_override' => $status,
                'updated_at'      => now(),
            ];

            if ($this->overrideTableHas('updated_by')) {
                $payload['updated_by'] = $by ? (int) $by : null;
            }

            if ($this->overrideTableHas('meta')) {
                $payload['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if ($row && isset($row->id)) {
                DB::connection($this->adm)->table($table)
                    ->where('id', (int) $row->id)
                    ->update($payload);
            } else {
                if ($this->overrideTableHas('created_at')) {
                    $payload['created_at'] = now();
                }
                DB::connection($this->adm)->table($table)->insert($payload);
            }

            // HOTFIX CRITICO:
            // NO tocar billing_statements.total_abono / saldo / status / paid_at
            // desde override manual. El override es visual-operativo, no contable.
        });

        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        if (!$acc) {
            return response()->json(['ok' => false, 'message' => 'Cuenta no encontrada'], 404);
        }

        $agg = DB::connection($this->adm)->table('estados_cuenta')
            ->selectRaw('SUM(COALESCE(cargo,0)) as cargo, SUM(COALESCE(abono,0)) as abono')
            ->where('account_id', $accountId)
            ->where('periodo', $period)
            ->first();

        $cargoEdo = (float) ($agg->cargo ?? 0);
        $abonoEdo = (float) ($agg->abono ?? 0);
        $abonoPay = (float) $this->sumPaymentsForAccountPeriod($accountId, $period);

        $meta = $this->hub->decodeMeta($acc->meta ?? null);
        if (!is_array($meta)) {
            $meta = [];
        }

        $lastPaid = $this->resolveLastPaidPeriodForAccount($accountId, $meta);

        $payAllowed = $lastPaid
            ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
            : $period;

        $custom = $this->extractCustomAmountMxn($acc, $meta);

        if ($custom !== null && $custom > 0.00001) {
            $expected = (float) $custom;
        } else {
            [$expected] = $this->safeResolveEffectiveAmountFromMeta($meta, $period, $payAllowed);
            $expected = (float) $expected;
        }

        $calc = $this->recalcStatementFromPayments($accountId, $period);

        $totalShown = round((float) ($calc['cargo'] ?? 0), 2);
        $abonoTotal = round((float) ($calc['abono'] ?? 0), 2);
        $saldoShown = round((float) ($calc['saldo'] ?? 0), 2);
        $statusCalc = (string) ($calc['status'] ?? 'pendiente');

        if ($totalShown <= 0.00001) {
            $totalShown = round((float) ($cargoEdo > 0.00001 ? $cargoEdo : $expected), 2);
            $abonoTotal = round((float) $abonoPay, 2);
            $saldoShown = round(max(0.0, $totalShown - $abonoTotal), 2);
            $statusCalc = $this->computeFinancialStatus($totalShown, $abonoTotal, 0.0);
        }

        $row = (object) [
            'cargo'            => round($cargoEdo, 2),
            'expected_total'   => round((float) $expected, 2),
            'total_shown'      => $totalShown,
            'abono'            => $abonoTotal,
            'abono_edo'        => round((float) $abonoEdo, 2),
            'abono_pay'        => round((float) $abonoPay, 2),
            'saldo'            => $saldoShown,
            'saldo_shown'      => $saldoShown,
            'saldo_current'    => $saldoShown,
            'prev_balance'     => 0.0,
            'total_due'        => $saldoShown,
            'status_pago'      => $statusCalc,
            'status_auto'      => $statusCalc,
            'pay_method'       => null,
            'pay_provider'     => null,
            'pay_status'       => $statusCalc,
            'pay_last_paid_at' => null,
            'pay_due_date'     => null,
        ];

        $ov = $this->fetchStatusOverridesForAccountsPeriod([$accountId], $period);
        $row = $this->applyStatusOverride($row, $ov[(string) $accountId] ?? null);

        return response()->json([
            'ok'             => true,
            'account_id'     => $accountId,
            'period'         => $period,
            'status'         => (string) ($row->status_pago ?? $status),
            'status_auto'    => (string) ($row->status_auto ?? $status),
            'pay_method'     => $row->pay_method ?? $payMethod,
            'pay_provider'   => $row->pay_provider ?? $payProvider,
            'pay_status'     => $row->pay_status ?? $status,
            'paid_at'        => $row->pay_last_paid_at ?? ($status === 'pagado' ? $paidAt?->toDateTimeString() : null),
            'total'          => round((float) ($row->total_shown ?? 0), 2),
            'abono'          => round((float) ($row->abono ?? 0), 2),
            'saldo'          => round((float) ($row->saldo ?? 0), 2),
            'saldo_current'  => round((float) ($row->saldo_current ?? 0), 2),
            'total_due'      => round((float) ($row->total_due ?? 0), 2),
        ]);
    }

    private function parsePaidAtFromRequest(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw)) {
                return Carbon::createFromFormat('Y-m-d\TH:i', $raw);
            }
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function status(Request $req): JsonResponse
    {
        return $this->statusAjax($req);
    }

    // =========================================================
    // PDF / EMAIL / BULK
    // =========================================================

    public function pdf(Request $req, string $accountId, string $period)
    {
        abort_if(!$this->isValidPeriod($period), 422);

        $data = $this->buildStatementData($accountId, $period);
        $data['isModal'] = false;

        $logoPx = (int) $req->get('qr_logo_px', 38);
        $logoPx = max(18, min(64, $logoPx));

        $data['qr_force_overlay'] = false;
        $data['qr_embedded']      = true;
        $data['qr_logo_px']       = $logoPx;

        try {
            $logoPath = public_path('assets/client/qr/pactopia-qr-logo.png');
            if (!is_file($logoPath) || !is_readable($logoPath)) {
                $logoPath = public_path('assets/client/Logo1Pactopia.png');
            }

            $basePng = $this->resolveQrPngBinaryFromData($data);

            if (!$basePng) {
                $payUrl = trim((string) (
                    $data['pay_url']
                    ?? $data['checkout_url']
                    ?? $data['payment_url']
                    ?? $data['url_pago']
                    ?? ''
                ));

                if ($payUrl !== '') {
                    $basePng = $this->makeQrPngBinary($payUrl, 320);
                }
            }

            if ($basePng) {
                $data['qr_data_uri'] = $this->embedCenterLogoIntoQrPngDataUri($basePng, $logoPath, $logoPx);
                $data['qr_url']  = null;
                $data['qr_path'] = null;
            } else {
                Log::warning('[STATEMENT_PDF][ADMIN_QR] No base QR found', [
                    'account_id' => $accountId,
                    'period'     => $period,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[STATEMENT_PDF][ADMIN_QR] Failed to force QR with logo', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
        }

        $viewName = 'cliente.billing.pdf.statement';
        $inline   = $req->boolean('inline') || $req->boolean('preview');

        if ($req->boolean('html')) {
            Log::info('[STATEMENT_PDF] debug html', [
                'view'       => $viewName,
                'account_id' => $accountId,
                'period'     => $period,
            ]);

            $html = view($viewName, $data)->render();
            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        Log::info('[STATEMENT_PDF] rendering', [
            'view'       => $viewName,
            'account_id' => $accountId,
            'period'     => $period,
            'inline'     => $inline,
        ]);

        $name = 'EstadoCuenta_' . $accountId . '_' . $period . '.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::setOptions([
                'isRemoteEnabled'      => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont'          => 'DejaVu Sans',
                'dpi'                  => 96,
                'defaultPaperSize'     => 'a4',
            ])->loadView($viewName, $data);

            return $inline ? $pdf->stream($name) : $pdf->download($name);
        }

        $html = view($viewName, $data)->render();
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function email(Request $req, string $accountId, string $period): RedirectResponse
    {
        abort_if(!$this->isValidPeriod($period), 422);

        $toRaw = trim((string) $req->get('to', ''));

        $acc = DB::connection($this->adm)->table('accounts')
            ->select('id', 'email', 'rfc', 'razon_social', 'name', 'meta')
            ->where('id', $accountId)
            ->first();

        if (!$acc) {
            return back()->withErrors(['account' => 'Cuenta no encontrada.']);
        }

        $this->sendStatementEmailWithPayLink($accountId, $period, $toRaw !== '' ? $toRaw : null);

        return back()->with('ok', 'Estado de cuenta enviado por correo (a destinatarios configurados).');
    }

    public function bulkEmail(Request $req)
    {
        $data = $req->validate([
            'period'        => 'required|string',
            'account_ids'   => 'nullable|array',
            'account_ids.*' => 'nullable|string|max:64',
            'status'        => 'nullable|string|in:all,pendiente,pagado,parcial,vencido,sin_mov',
        ]);

        $period = (string) $data['period'];
        if (!$this->isValidPeriod($period)) {
            return back()->withErrors(['period' => 'Periodo inválido. Formato YYYY-MM.']);
        }

        abort_unless(Schema::connection($this->adm)->hasTable('accounts'), 404);

        $status = (string) ($data['status'] ?? 'all');

        $ids = collect($data['account_ids'] ?? [])
            ->filter(static fn ($x) => is_string($x) && trim($x) !== '')
            ->map(static fn ($x) => trim((string) $x))
            ->values()
            ->all();

        $q = DB::connection($this->adm)->table('accounts')->select('id');
        if (!empty($ids)) {
            $q->whereIn('id', $ids);
        }

        $accounts = $q->get()->pluck('id')->map(static fn ($x) => (string) $x)->values();

        $ok = 0;
        $fail = 0;

        foreach ($accounts as $aid) {
            try {
                if ($status !== 'all') {
                    $d = $this->buildStatementData((string) $aid, $period);
                    $cargo = (float) ($d['cargo'] ?? 0);
                    $abono = (float) ($d['abono'] ?? 0);
                    $prev  = (float) ($d['prev_balance'] ?? 0);

                    $computed = $this->computeFinancialStatus($cargo, $abono, $prev);

                    if ($computed !== $status) {
                        continue;
                    }
                }

                $this->sendStatementEmailWithPayLink((string) $aid, $period, null);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                Log::warning('[ADMIN][STATEMENTS][BULK] fallo', [
                    'account_id' => (string) $aid,
                    'period'     => $period,
                    'err'        => $e->getMessage(),
                ]);
            }

            usleep(180000);
        }

        if ($req->expectsJson()) {
            return response()->json(['ok' => true, 'sent' => $ok, 'failed' => $fail]);
        }

        return back()->with('ok', "Envío masivo disparado. Enviados: {$ok}. Fallidos: {$fail}.");
    }

    private function sendStatementEmailWithPayLink(string $accountId, string $period, ?string $to = null): void
    {
        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        if (!$acc) {
            return;
        }

        $recipients = $this->normalizeRecipientList($to);
        if (empty($recipients)) {
            $recipients = $this->resolveRecipientsForAccount((string) $accountId, (string) ($acc->email ?? ''));
        }
        if (empty($recipients)) {
            return;
        }

        $data = $this->buildStatementData($accountId, $period);

        try {
            $data['pdf_url'] = URL::signedRoute('cliente.billing.publicPdfInline', [
                'accountId' => $accountId,
                'period'    => $period,
            ]);
        } catch (\Throwable $e) {
            $data['pdf_url'] = null;
        }

        try {
            $data['portal_url'] = route('cliente.estado_cuenta') . '?period=' . urlencode($period);
        } catch (\Throwable $e) {
            $data['portal_url'] = null;
        }

        $totalPesos = (float) ($data['total'] ?? 0);

        $payUrl    = null;
        $sessionId = null;

        if ($totalPesos > 0.00001) {
            try {
                [$payUrl, $sessionId] = $this->createStripeCheckoutForStatement($acc, $period, $totalPesos);
            } catch (\Throwable $e) {
                Log::error('[ADMIN][STATEMENT][EMAIL] No se pudo crear Stripe checkout', [
                    'account_id' => $accountId,
                    'period'     => $period,
                    'err'        => $e->getMessage(),
                ]);
            }
        }

        if (is_string($payUrl) && $payUrl !== '') {
            $data['pay_url'] = $payUrl;
            [$qrDataUri, $qrUrl] = $this->makeQrDataForText($payUrl);
            $data['qr_data_uri'] = $qrDataUri;
            $data['qr_url']      = $qrUrl;
        }

        $data['stripe_session_id'] = $sessionId;

        $emailId = (string) Str::ulid();
        $data['email_id'] = $emailId;

        try {
            if (Route::has('admin.billing.hub.track_open')) {
                $data['open_pixel_url'] = route('admin.billing.hub.track_open', ['emailId' => $emailId]);
            }
        } catch (\Throwable $e) {
            $data['open_pixel_url'] = null;
        }

        try {
            $wrapClick = function (?string $url) use ($emailId): ?string {
                $url = trim((string) $url);
                if ($url === '') {
                    return null;
                }

                if (Route::has('admin.billing.hub.track_click')) {
                    return route('admin.billing.hub.track_click', ['emailId' => $emailId]) . '?u=' . urlencode($url);
                }

                return null;
            };

            $data['pdf_track_url']    = $wrapClick((string) ($data['pdf_url'] ?? ''));
            $data['portal_track_url'] = $wrapClick((string) ($data['portal_url'] ?? ''));
            $data['pay_track_url']    = $wrapClick((string) ($data['pay_url'] ?? ''));
        } catch (\Throwable $e) {
            $data['pdf_track_url']    = $data['pdf_url'] ?? null;
            $data['portal_track_url'] = $data['portal_url'] ?? null;
            $data['pay_track_url']    = $data['pay_url'] ?? null;
        }

        $subject = 'Pactopia360 · Estado de cuenta ' . $period . ' · ' . (string) ($acc->razon_social ?? $acc->name ?? 'Cliente');
        $data['subject'] = $subject;

        $hasLogsTable = Schema::connection($this->adm)->hasTable('billing_email_logs');
        $logCols = $hasLogsTable ? array_map('strtolower', Schema::connection($this->adm)->getColumnListing('billing_email_logs')) : [];
        $hasLogCol = static fn (string $c): bool => in_array(strtolower($c), $logCols, true);

        foreach ($recipients as $dest) {
            $logId = 0;
            $metaBase = [
                'source'            => 'billing_statements_legacy_send',
                'account_id'        => $accountId,
                'period'            => $period,
                'bcc_monitor'       => 'notificaciones@pactopia.com',
                'stripe_session_id' => $sessionId,
            ];

            try {
                if ($hasLogsTable) {
                    $insert = [];

                    if ($hasLogCol('email_id')) {
                        $insert['email_id'] = $emailId;
                    }
                    if ($hasLogCol('account_id')) {
                        $insert['account_id'] = $accountId;
                    }
                    if ($hasLogCol('period')) {
                        $insert['period'] = $period;
                    }
                    if ($hasLogCol('statement_id')) {
                        $insert['statement_id'] = null;
                    }
                    if ($hasLogCol('email')) {
                        $insert['email'] = $dest;
                    }
                    if ($hasLogCol('to_list')) {
                        $insert['to_list'] = implode(',', $recipients);
                    }
                    if ($hasLogCol('subject')) {
                        $insert['subject'] = $subject;
                    }
                    if ($hasLogCol('template')) {
                        $insert['template'] = 'emails.admin.billing.statement_account_period';
                    }
                    if ($hasLogCol('status')) {
                        $insert['status'] = 'queued';
                    }
                    if ($hasLogCol('provider')) {
                        $insert['provider'] = config('mail.default') ?: 'smtp';
                    }
                    if ($hasLogCol('provider_message_id')) {
                        $insert['provider_message_id'] = null;
                    }
                    if ($hasLogCol('payload')) {
                        $insert['payload'] = json_encode($data, JSON_UNESCAPED_UNICODE);
                    }
                    if ($hasLogCol('meta')) {
                        $insert['meta'] = json_encode($metaBase, JSON_UNESCAPED_UNICODE);
                    }
                    if ($hasLogCol('queued_at')) {
                        $insert['queued_at'] = now();
                    }
                    if ($hasLogCol('open_count')) {
                        $insert['open_count'] = 0;
                    }
                    if ($hasLogCol('click_count')) {
                        $insert['click_count'] = 0;
                    }
                    if ($hasLogCol('created_at')) {
                        $insert['created_at'] = now();
                    }
                    if ($hasLogCol('updated_at')) {
                        $insert['updated_at'] = now();
                    }

                    $logId = (int) DB::connection($this->adm)
                        ->table('billing_email_logs')
                        ->insertGetId($insert);
                }

                Mail::to($dest)->send(new StatementAccountPeriodMail($accountId, $period, $data));

                if ($hasLogsTable && $logId > 0) {
                    $update = [];

                    if ($hasLogCol('status')) {
                        $update['status'] = 'sent';
                    }
                    if ($hasLogCol('sent_at')) {
                        $update['sent_at'] = now();
                    }
                    if ($hasLogCol('updated_at')) {
                        $update['updated_at'] = now();
                    }

                    if (!empty($update)) {
                        DB::connection($this->adm)->table('billing_email_logs')
                            ->where('id', $logId)
                            ->update($update);
                    }
                }

                Log::info('[ADMIN][STATEMENT][EMAIL] enviado', [
                    'to'         => $dest,
                    'account_id' => (string) ($acc->id ?? ''),
                    'period'     => $period,
                    'email_id'   => $emailId,
                    'has_pay'    => (bool) ($data['pay_url'] ?? null),
                    'has_qr'     => (bool) (($data['qr_data_uri'] ?? null) ?: ($data['qr_url'] ?? null)),
                    'total'      => (float) ($data['total'] ?? 0),
                    'cargo'      => (float) ($data['cargo'] ?? 0),
                    'expected'   => (float) ($data['expected_total'] ?? 0),
                ]);
            } catch (\Throwable $e) {
                if ($hasLogsTable && $logId > 0) {
                    $metaFail = $metaBase;
                    $metaFail['error'] = $e->getMessage();
                    $metaFail['error_at'] = now()->toDateTimeString();

                    $update = [];

                    if ($hasLogCol('status')) {
                        $update['status'] = 'failed';
                    }
                    if ($hasLogCol('failed_at')) {
                        $update['failed_at'] = now();
                    }
                    if ($hasLogCol('meta')) {
                        $update['meta'] = json_encode($metaFail, JSON_UNESCAPED_UNICODE);
                    }
                    if ($hasLogCol('updated_at')) {
                        $update['updated_at'] = now();
                    }

                    if (!empty($update)) {
                        DB::connection($this->adm)->table('billing_email_logs')
                            ->where('id', $logId)
                            ->update($update);
                    }
                }

                Log::error('[ADMIN][STATEMENT][EMAIL] fallo', [
                    'to'         => $dest,
                    'account_id' => (string) ($acc->id ?? ''),
                    'period'     => $period,
                    'email_id'   => $emailId,
                    'err'        => $e->getMessage(),
                ]);
            }

            usleep(90000);
        }
    }
    
    // =========================================================
    // RECIPIENTS
    // =========================================================

    /**
     * @return array<int,string>
     */
    private function resolveRecipientsForAccount(string $accountId, string $fallbackEmail): array
    {
        $emails = [];

        $fallbackEmail = strtolower(trim($fallbackEmail));
        if ($fallbackEmail !== '' && filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $fallbackEmail;
        }

        if (Schema::connection($this->adm)->hasTable('account_recipients')) {
            try {
                $rows = DB::connection($this->adm)->table('account_recipients')
                    ->select('email')
                    ->where('account_id', $accountId)
                    ->where('is_active', 1)
                    ->orderByDesc('is_primary')
                    ->get();

                foreach ($rows as $r) {
                    $e = strtolower(trim((string) ($r->email ?? '')));
                    if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $e;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[ADMIN][STATEMENTS] resolveRecipientsForAccount failed', [
                    'account_id' => $accountId,
                    'err'        => $e->getMessage(),
                ]);
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @return array<int,string>
     */
    private function normalizeRecipientList(?string $to): array
    {
        $to = trim((string) $to);
        if ($to === '') {
            return [];
        }

        $to = str_replace([';', "\n", "\r", "\t"], [',', ',', ',', ' '], $to);
        $parts = array_filter(array_map('trim', explode(',', $to)));

        $out = [];
        foreach ($parts as $p) {
            $e = strtolower(trim($p));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $out[] = $e;
            }
        }

        return array_values(array_unique($out));
    }

    // =========================================================
    // HELPERS: SERVICE / MODE / CONFIG
    // =========================================================

    private function shouldInjectServiceLine($items, float $expectedTotal, string $statementMode): bool
    {
        $mode = $this->normalizeStatementMode($statementMode);

        if ($expectedTotal <= 0.00001) {
            return false;
        }

        foreach ($items as $it) {
            $cargoIt = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
            if ($cargoIt <= 0.00001) {
                continue;
            }

            $c = mb_strtolower(trim((string) ($it->concepto ?? '')));
            if (
                str_contains($c, 'servicio mensual') ||
                str_contains($c, 'servicio anual') ||
                str_contains($c, 'licencia') ||
                str_contains($c, 'suscrip') ||
                str_contains($c, 'membres')
            ) {
                return false;
            }
        }

        if ($mode === 'unique') {
            foreach ($items as $it) {
                $cargoIt = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
                if ($cargoIt > 0.00001) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    private function resolveBillingModeFromMetaOrPlan(array $meta, object $acc): string
    {
        $mode = strtolower(trim((string) (
            data_get($meta, 'billing.mode')
            ?? data_get($meta, 'billing.modo')
            ?? ''
        )));

        $planStr = strtolower(trim((string) ($acc->plan_actual ?? $acc->plan ?? '')));

        if (!in_array($mode, ['mensual', 'anual'], true)) {
            if (str_contains($planStr, 'anual') || str_contains($planStr, 'annual')) {
                $mode = 'anual';
            }
            if (str_contains($planStr, 'mensual') || str_contains($planStr, 'monthly')) {
                $mode = 'mensual';
            }
        }

        return in_array($mode, ['mensual', 'anual'], true) ? $mode : 'mensual';
    }

    private function normalizeStatementMode(string $modeRaw): string
    {
        $m = strtolower(trim($modeRaw));

        if (in_array($m, ['mensual', 'monthly'], true)) {
            return 'monthly';
        }
        if (in_array($m, ['unica', 'única', 'unique'], true)) {
            return 'unique';
        }

        return 'monthly';
    }

    /**
     * @param array<string,mixed> $data
     * @return array{0:float,1:float}
     */
    private function resolveCargoAbonoFromRequest(array $data): array
    {
        $cargo = (float) ($data['cargo'] ?? 0);
        $abono = (float) ($data['abono'] ?? 0);

        $tipo  = isset($data['tipo']) ? strtolower(trim((string) $data['tipo'])) : '';
        $monto = isset($data['monto']) && is_numeric($data['monto']) ? (float) $data['monto'] : 0.0;

        if ($tipo !== '' && $monto > 0) {
            if ($tipo === 'cargo') {
                $cargo = $monto;
                $abono = 0.0;
            }
            if ($tipo === 'abono') {
                $abono = $monto;
                $cargo = 0.0;
            }
        }

        return [round(max(0.0, $cargo), 2), round(max(0.0, $abono), 2)];
    }

    private function recalcStatementSaldoIfPossible(string $accountId, string $period): void
    {
        try {
            if (!Schema::connection($this->adm)->hasTable('estados_cuenta')) {
                return;
            }

            $cols = Schema::connection($this->adm)->getColumnListing('estados_cuenta');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('saldo') || !$has('id')) {
                return;
            }

            $items = DB::connection($this->adm)->table('estados_cuenta')
                ->where('account_id', $accountId)
                ->where('periodo', $period)
                ->orderBy('id')
                ->get(['id', 'cargo', 'abono']);

            $saldo = 0.0;

            foreach ($items as $it) {
                $cargo = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
                $abono = is_numeric($it->abono ?? null) ? (float) $it->abono : 0.0;

                $saldo = max(0.0, $saldo + $cargo - $abono);

                DB::connection($this->adm)->table('estados_cuenta')
                    ->where('id', (int) $it->id)
                    ->update([
                        'saldo'      => round($saldo, 2),
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] recalcStatementSaldoIfPossible failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function getStatementConfigFromMeta(array $meta, string $period): array
    {
        $cfg = data_get($meta, 'billing.statements.' . $period);
        if (!is_array($cfg)) {
            $cfg = [];
        }

        $mode = (string) ($cfg['mode'] ?? 'monthly');
        $mode = $this->normalizeStatementMode($mode);

        return [
            'mode'       => $mode,
            'notes'      => (string) ($cfg['notes'] ?? ''),
            'updated_at' => $cfg['updated_at'] ?? null,
            'by'         => $cfg['by'] ?? null,
        ];
    }

    // =========================================================
    // META / PRICE HELPERS
    // =========================================================

    private function extractCustomAmountMxn(object $row, array $meta): ?float
    {
        $candidates = [
            data_get($meta, 'billing.override.amount_mxn'),
            data_get($meta, 'billing.override.amount'),
            data_get($meta, 'billing.override_mxn'),
            data_get($meta, 'billing.custom.amount_mxn'),
            data_get($meta, 'billing.custom.amount'),
            data_get($meta, 'billing.custom_mxn'),
            data_get($meta, 'billing.amount_override_mxn'),
            data_get($meta, 'billing.amount_mxn_override'),
            data_get($meta, 'license.override.amount_mxn'),
            data_get($meta, 'license.override.amount'),
            data_get($meta, 'license.custom.amount_mxn'),
            data_get($meta, 'license.custom.amount'),
            data_get($meta, 'license.custom_mxn'),
            data_get($meta, 'pricing.override.amount_mxn'),
            data_get($meta, 'pricing.override.amount'),
            data_get($meta, 'pricing.custom.amount_mxn'),
            data_get($meta, 'pricing.custom.amount'),
            data_get($meta, 'pricing.custom_mxn'),
            data_get($meta, 'override.amount_mxn'),
            data_get($meta, 'override.amount'),
            data_get($meta, 'custom.amount_mxn'),
            data_get($meta, 'custom.amount'),
            data_get($meta, 'custom_mxn'),
        ];

        foreach ($candidates as $v) {
            $n = $this->toFloat($v);
            if ($n !== null && $n > 0.00001) {
                return $n;
            }
        }

        foreach ([
            'override_amount_mxn',
            'custom_amount_mxn',
            'billing_amount_mxn',
            'amount_mxn',
            'precio_mxn',
            'monto_mxn',
            'license_amount_mxn',
            'billing_amount',
            'amount',
            'precio',
            'monto',
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

    private function toFloat(mixed $v): ?float
    {
        if ($v === null) return null;
        if (is_float($v) || is_int($v)) return (float) $v;
        if (is_numeric($v)) return (float) $v;

        if (is_string($v)) {
            $s = trim($v);
            if ($s === '') return null;
            $s = str_replace(['$', ',', ' '], '', $s);
            return is_numeric($s) ? (float) $s : null;
        }

        return null;
    }

    /**
     * @return array{0:float,1:string,2:string}
     */
    private function safeResolveEffectiveAmountFromMeta(array $meta, string $period, ?string $payAllowed): array
    {
        $fallback = [0.0, '-', 'dim'];

        try {
            $res = $this->hub->resolveEffectiveAmountForPeriodFromMeta($meta, $period, $payAllowed);

            if (is_array($res)) {
                $mxn   = (isset($res[0]) && is_numeric($res[0])) ? (float) $res[0] : 0.0;
                $label = (isset($res[1]) && is_string($res[1])) ? (string) $res[1] : '-';
                $pill  = (isset($res[2]) && is_string($res[2])) ? (string) $res[2] : 'dim';

                if (!in_array($pill, ['info', 'warn', 'ok', 'dim', 'bad'], true)) {
                    $pill = 'dim';
                }

                return [round($mxn, 2), $label, $pill];
            }

            if (is_numeric($res)) {
                return [round((float) $res, 2), '-', 'dim'];
            }

            return $fallback;
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] safeResolveEffectiveAmountFromMeta failed', [
                'period'      => $period,
                'pay_allowed' => $payAllowed,
                'err'         => $e->getMessage(),
            ]);
            return $fallback;
        }
    }

    // =========================================================
    // OVERRIDE TABLE HELPERS
    // =========================================================

    /**
     * @return array{cols:array<int,string>,lc:array<int,string>}
     */
    private function getOverrideTableColumns(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
            $cache = ['cols' => [], 'lc' => []];
            return $cache;
        }

        $cols = Schema::connection($this->adm)->getColumnListing($this->overrideTable());
        $lc   = array_map('strtolower', $cols);

        $cache = ['cols' => $cols, 'lc' => $lc];
        return $cache;
    }

    private function overrideTableHas(string $column): bool
    {
        $meta = $this->getOverrideTableColumns();
        return in_array(strtolower($column), $meta['lc'], true);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeOverrideMeta(mixed $raw): array
    {
        if (is_array($raw)) return $raw;
        if (is_object($raw)) return (array) $raw;

        if (is_string($raw) && trim($raw) !== '') {
            try {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable $e) {
            }
        }

        return [];
    }

    private function normalizeOverrideStatus(?string $status): ?string
    {
        $s = strtolower(trim((string) $status));

        if ($s === '') {
            return null;
        }

        return match ($s) {
            'paid', 'succeeded', 'success', 'completed', 'complete' => 'pagado',
            'pending' => 'pendiente',
            'partial' => 'parcial',
            'overdue', 'past_due', 'unpaid' => 'vencido',
            'sin_mov', 'sin-mov', 'no_mov', 'no-mov' => 'sin_mov',
            'pendiente', 'parcial', 'pagado', 'vencido' => $s,
            default => null,
        };
    }

    public function setStatusOverrideForm(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id'      => 'required|string|max:64',
            'period'          => 'required|string',
            'status_override' => 'nullable|string|in:pendiente,parcial,pagado,vencido,sin_mov',
            'reason'          => 'nullable|string|max:255',
        ]);

        $accountId = trim((string) $data['account_id']);
        $period    = trim((string) $data['period']);

        abort_if(!$this->isValidPeriod($period), 422);

        if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
            return back()->withErrors([
                'status' => 'No existe la tabla billing_statement_status_overrides (corre migraciones).',
            ]);
        }

        if (
            !$this->overrideTableHas('account_id') ||
            !$this->overrideTableHas('period') ||
            !$this->overrideTableHas('status_override')
        ) {
            return back()->withErrors([
                'status' => 'La tabla billing_statement_status_overrides no tiene la estructura esperada.',
            ]);
        }

        $status = strtolower(trim((string) ($data['status_override'] ?? '')));
        $reason = trim((string) ($data['reason'] ?? ''));
        $by     = auth('admin')->id();

        DB::connection($this->adm)->transaction(function () use ($accountId, $period, $status, $reason, $by) {
            $baseQuery = DB::connection($this->adm)->table($this->overrideTable())
                ->where('account_id', $accountId)
                ->where('period', $period);

            if ($status === '') {
                $baseQuery->delete();
                return;
            }

            $existing = $baseQuery->first(['id']);

            $payload = [
                'account_id'      => $accountId,
                'period'          => $period,
                'status_override' => $status,
                'updated_at'      => now(),
            ];

            if ($this->overrideTableHas('reason')) {
                $payload['reason'] = $reason !== '' ? $reason : null;
            }

            if ($this->overrideTableHas('updated_by')) {
                $payload['updated_by'] = $by ? (int) $by : null;
            }

            if ($existing && isset($existing->id)) {
                DB::connection($this->adm)->table($this->overrideTable())
                    ->where('id', (int) $existing->id)
                    ->update($payload);
            } else {
                if ($this->overrideTableHas('created_at')) {
                    $payload['created_at'] = now();
                }

                DB::connection($this->adm)->table($this->overrideTable())->insert($payload);
            }
        });

        return back()->with(
            'ok',
            $status === '' ? 'Override eliminado (AUTO).' : 'Estatus actualizado (override).'
        );
    }

    // =========================================================
    // QR HELPERS
    // =========================================================

    /**
     * @return array{0:?string,1:?string}
     */
    private function makeQrDataForText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [null, null];
        }

        $size = 170;

        try {
            if (class_exists(Writer::class) && class_exists(GdImageBackEnd::class)) {
                $renderer = new ImageRenderer(new RendererStyle($size), new GdImageBackEnd());
                $writer   = new Writer($renderer);
                $png      = $writer->writeString($text);

                if (is_string($png) && $png !== '') {
                    return ['data:image/png;base64,' . base64_encode($png), null];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] QR local failed (bacon)', ['err' => $e->getMessage()]);
        }

        $qrUrl = 'https://quickchart.io/qr?text=' . urlencode($text) . '&size=' . $size . '&margin=1&format=png';
        return [null, $qrUrl];
    }

    private function resolveQrTextForStatement(string $accountId, string $period, ?string $payUrl = null): ?string
    {
        if (is_string($payUrl) && trim($payUrl) !== '') {
            return trim($payUrl);
        }

        try {
            if (Route::has('cliente.billing.publicPay')) {
                return URL::signedRoute('cliente.billing.publicPay', [
                    'accountId' => $accountId,
                    'period'    => $period,
                ]);
            }
        } catch (\Throwable $e) {
        }

        try {
            if (Route::has('cliente.estado_cuenta')) {
                return route('cliente.estado_cuenta') . '?period=' . urlencode($period);
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function resolveQrPngBinaryFromData(array $data): ?string
    {
        $qrDataUri = trim((string) ($data['qr_data_uri'] ?? $data['qr_data'] ?? ''));
        if ($qrDataUri !== '' && str_starts_with($qrDataUri, 'data:image')) {
            $pos = strpos($qrDataUri, 'base64,');
            if ($pos !== false) {
                $b64 = substr($qrDataUri, $pos + 7);
                $bin = base64_decode($b64, true);
                if (is_string($bin) && strlen($bin) > 50) {
                    return $bin;
                }
            }
        }

        $qrUrl = trim((string) ($data['qr_url'] ?? $data['qr_path'] ?? ''));
        if ($qrUrl === '') {
            return null;
        }

        $tryLocal = null;

        if (str_starts_with($qrUrl, '/')) {
            $tryLocal = public_path(ltrim($qrUrl, '/'));
        } elseif (!preg_match('#^https?://#i', $qrUrl)) {
            $tryLocal = public_path(ltrim($qrUrl, '/'));
        }

        if ($tryLocal && is_file($tryLocal) && is_readable($tryLocal)) {
            $bin = @file_get_contents($tryLocal);
            if (is_string($bin) && strlen($bin) > 50) {
                return $bin;
            }
        }

        if (preg_match('#^https?://#i', $qrUrl)) {
            $ctx = stream_context_create([
                'http' => ['timeout' => 3],
                'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);

            $bin = @file_get_contents($qrUrl, false, $ctx);
            if (is_string($bin) && strlen($bin) > 50) {
                return $bin;
            }
        }

        return null;
    }

    private function makeQrPngBinary(string $text, int $size = 320): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (
            !class_exists(Writer::class) ||
            !class_exists(ImageRenderer::class) ||
            !class_exists(RendererStyle::class) ||
            !class_exists(GdImageBackEnd::class)
        ) {
            return null;
        }

        $size = max(160, min(520, (int) $size));

        try {
            $renderer = new ImageRenderer(
                new RendererStyle($size, 2),
                new GdImageBackEnd()
            );

            $writer = new Writer($renderer);
            $png = $writer->writeString($text);

            if (is_string($png) && strlen($png) > 50) {
                return $png;
            }
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] makeQrPngBinary failed', [
                'err'  => $e->getMessage(),
                'size' => $size,
            ]);
        }

        return null;
    }

    private function embedCenterLogoIntoQrPngDataUri(string $qrPngBin, string $logoPath, int $logoPx = 38): string
    {
        $fallback = 'data:image/png;base64,' . base64_encode($qrPngBin);

        if (!function_exists('imagecreatefromstring')) {
            return $fallback;
        }

        $qrImg = @imagecreatefromstring($qrPngBin);
        if (!$qrImg) {
            return $fallback;
        }

        $logoPx = max(18, min(64, (int) $logoPx));

        if ($logoPath === '' || !is_file($logoPath) || !is_readable($logoPath)) {
            imagedestroy($qrImg);
            return $fallback;
        }

        $logoBin = @file_get_contents($logoPath);
        if (!is_string($logoBin) || strlen($logoBin) < 50) {
            imagedestroy($qrImg);
            return $fallback;
        }

        $logoImg = @imagecreatefromstring($logoBin);
        if (!$logoImg) {
            imagedestroy($qrImg);
            return $fallback;
        }

        $lw = imagesx($logoImg);
        $lh = imagesy($logoImg);

        if ($lw <= 0 || $lh <= 0) {
            imagedestroy($logoImg);
            imagedestroy($qrImg);
            return $fallback;
        }

        $dst = imagecreatetruecolor($logoPx, $logoPx);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $logoPx, $logoPx, $transparent);

        imagecopyresampled($dst, $logoImg, 0, 0, 0, 0, $logoPx, $logoPx, $lw, $lh);

        $qrW = imagesx($qrImg);
        $qrH = imagesy($qrImg);

        $pad = 6;
        $box = $logoPx + ($pad * 2);

        $x = (int) (($qrW - $box) / 2);
        $y = (int) (($qrH - $box) / 2);

        $white = imagecolorallocate($qrImg, 255, 255, 255);
        imagefilledrectangle($qrImg, $x, $y, $x + $box, $y + $box, $white);

        imagecopy($qrImg, $dst, $x + $pad, $y + $pad, 0, 0, $logoPx, $logoPx);

        ob_start();
        imagepng($qrImg);
        $out = (string) ob_get_clean();

        imagedestroy($dst);
        imagedestroy($logoImg);
        imagedestroy($qrImg);

        if (strlen($out) < 50) {
            return $fallback;
        }

        return 'data:image/png;base64,' . base64_encode($out);
    }

        /**
     * =========================================================
     * SOT: billing_statements por account + period
     * =========================================================
     * Retorna null si no existe statement real.
     *
     * @return array<string,mixed>|null
     */
    private function getBillingStatementSnapshot(string $accountId, string $period): ?array
    {
        $accountId = trim($accountId);
        $period    = trim($period);

        if ($accountId === '' || !$this->isValidPeriod($period)) {
            return null;
        }

        try {
            if (!Schema::connection($this->adm)->hasTable('billing_statements')) {
                return null;
            }

            $row = DB::connection($this->adm)->table('billing_statements')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first([
                    'id',
                    'account_id',
                    'period',
                    'status',
                    'total_cargo',
                    'total_abono',
                    'saldo',
                    'due_date',
                    'paid_at',
                    'snapshot',
                    'meta',
                    'created_at',
                    'updated_at',
                ]);

            if (!$row) {
                return null;
            }

            $status = strtolower(trim((string) ($row->status ?? 'pending')));
            $status = match ($status) {
                'paid', 'pagado', 'succeeded', 'success', 'complete', 'completed', 'captured', 'confirmed' => 'pagado',
                'partial', 'parcial' => 'parcial',
                'overdue', 'vencido', 'past_due', 'unpaid' => 'vencido',
                'sin_mov', 'sin mov', 'sin_movimiento', 'sin movimiento', 'no_movement', 'no movement' => 'sin_mov',
                default => 'pendiente',
            };

            $cargo = is_numeric($row->total_cargo ?? null) ? (float) $row->total_cargo : 0.0;
            $abono = is_numeric($row->total_abono ?? null) ? (float) $row->total_abono : 0.0;
            $saldo = is_numeric($row->saldo ?? null) ? (float) $row->saldo : max(0.0, $cargo - $abono);

            if ($status === 'pagado') {
                $abono = max($abono, $cargo);
                $saldo = 0.0;
            }

            if ($status === 'parcial' && $saldo <= 0.00001 && $cargo > $abono) {
                $saldo = max(0.0, $cargo - $abono);
            }

            if ($status === 'pendiente' && $saldo <= 0.00001 && $cargo > 0.00001) {
                $saldo = max(0.0, $cargo - $abono);
            }

            return [
                'id'         => (int) ($row->id ?? 0),
                'account_id' => (string) ($row->account_id ?? ''),
                'period'     => (string) ($row->period ?? ''),
                'status'     => $status,
                'cargo'      => round(max(0.0, $cargo), 2),
                'abono'      => round(max(0.0, $abono), 2),
                'saldo'      => round(max(0.0, $saldo), 2),
                'due_date'   => $row->due_date ?? null,
                'paid_at'    => $row->paid_at ?? null,
                'snapshot'   => $row->snapshot ?? null,
                'meta'       => $row->meta ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] getBillingStatementSnapshot failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function hasBillingStatementSnapshot(string $accountId, string $period): bool
    {
        return $this->getBillingStatementSnapshot($accountId, $period) !== null;
    }
    
    private function isOverdue(string $period, mixed $dueDate, Carbon $now): bool
    {
        // =========================================================
        // Regla GLOBAL (SOT):
        // - El periodo ACTUAL (YYYY-MM) NUNCA se marca como vencido.
        // - Solo periodos ANTERIORES pueden ser vencidos.
        // =========================================================
        $p = trim((string) $period);
        if (!preg_match('/^\d{4}-\d{2}$/', $p)) {
            return false;
        }

        $currentPeriod = $now->format('Y-m');
        if ($p >= $currentPeriod) {
            return false;
        }

        // =========================================================
        // 1) Si tenemos due_date válido, úsalo como fuente primaria
        // =========================================================
        try {
            if ($dueDate !== null && $dueDate !== '') {
                $due = $dueDate instanceof Carbon ? $dueDate : Carbon::parse((string) $dueDate);
                return $due->lt($now);
            }
        } catch (\Throwable $e) {
            // Si dueDate falla, caer al fallback
        }

        // =========================================================
        // 2) Fallback robusto por periodo:
        //    vencido si ya pasó fin de mes + 4 días
        // =========================================================
        try {
            $start = Carbon::createFromFormat('Y-m-d', $p . '-01')->startOfMonth();
            $cut   = $start->copy()->endOfMonth()->addDays(4)->endOfDay();
            return $now->gt($cut);
        } catch (\Throwable $e) {
            return false;
        }
    }

        private function hasOverrideForAccountPeriod(string $accountId, string $period): bool
    {
        $accountId = trim($accountId);
        $period    = trim($period);

        if ($accountId === '' || !$this->isValidPeriod($period)) {
            return false;
        }

        try {
            if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
                return false;
            }

            if (
                !$this->overrideTableHas('account_id') ||
                !$this->overrideTableHas('period')
            ) {
                return false;
            }

            return DB::connection($this->adm)->table($this->overrideTable())
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->limit(1)
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] hasOverrideForAccountPeriod failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);

            return false;
        }
    }

        private function normalizeMoney(float|int|string|null $value): float
    {
        return round(max(0.0, (float) $value), 2);
    }

    private function computeFinancialStatus(
        float $totalCurrent,
        float $abonoTotal,
        float $prevBalance = 0.0
    ): string {
        $totalCurrent = $this->normalizeMoney($totalCurrent);
        $abonoTotal   = $this->normalizeMoney($abonoTotal);
        $prevBalance  = $this->normalizeMoney($prevBalance);

        $saldoCurrent = round(max(0.0, $totalCurrent - $abonoTotal), 2);
        $totalDue     = round(max(0.0, $saldoCurrent + $prevBalance), 2);

        if ($totalCurrent <= 0.00001 && $prevBalance <= 0.00001) {
            return 'sin_mov';
        }

        if ($totalDue <= 0.00001) {
            return 'pagado';
        }

        if ($prevBalance > 0.00001) {
            return 'vencido';
        }

        if ($abonoTotal > 0.00001 && $saldoCurrent > 0.00001) {
            return 'parcial';
        }

        return 'pendiente';
    }

    /**
     * Consolida snapshot + payments con la misma regla del HUB.
     *
     * Regla SOT:
     * - Si existe billing_statements => total = snapshot.total_cargo
     * - Abono consolidado = max(snapshot.total_abono, payments_pagados)
     * - Si NO existe billing_statements => total = cargo base visible
     * - Abono consolidado = payments_pagados
     * - Los abonos de estados_cuenta se conservan solo como dato informativo, no como fuente
     *   principal del cálculo financiero visible.
     *
     * @return array{
     *   cargo_edo: float,
     *   abono_edo: float,
     *   abono_pay: float,
     *   total_shown: float,
     *   abono_total: float,
     *   saldo_shown: float,
     *   status_pago: string,
     *   due_date: mixed,
     *   paid_at: mixed
     * }
     */
    private function resolveStatementFinancials(
        ?array $stmt,
        float $cargoBaseVisible,
        float $abonoEdo,
        float $abonoPay,
        float $expectedTotal,
        float $prevBalance = 0.0
    ): array {
        $cargoBaseVisible = $this->normalizeMoney($cargoBaseVisible);
        $abonoEdo         = $this->normalizeMoney($abonoEdo);
        $abonoPay         = $this->normalizeMoney($abonoPay);
        $expected         = $this->normalizeMoney($expectedTotal);
        $prevBalance      = $this->normalizeMoney($prevBalance);

        if ($stmt) {
            $totalShown = $this->normalizeMoney($stmt['cargo'] ?? 0.0);
            $abonoStmt  = $this->normalizeMoney($stmt['abono'] ?? 0.0);

            // ✅ Igual que HUB:
            // Mirror manda, payments consolidan sin duplicar edocta
            $abonoTotal = round(max($abonoStmt, $abonoPay), 2);
            $saldoShown = round(max(0.0, $totalShown - $abonoTotal), 2);

            return [
                'cargo_edo'    => $cargoBaseVisible,
                'abono_edo'    => $abonoEdo,
                'abono_pay'    => $abonoPay,
                'total_shown'  => $totalShown,
                'abono_total'  => $abonoTotal,
                'saldo_shown'  => $saldoShown,
                'status_pago'  => $this->computeFinancialStatus($totalShown, $abonoTotal, $prevBalance),
                'due_date'     => $stmt['due_date'] ?? null,
                'paid_at'      => $stmt['paid_at'] ?? null,
            ];
        }

        // ✅ Igual que HUB cuando no hay mirror:
        // total visible = cargo base/esperado; abono visible = payments
        $totalShown = $cargoBaseVisible > 0.00001 ? $cargoBaseVisible : $expected;
        $abonoTotal = round(max(0.0, $abonoPay), 2);
        $saldoShown = round(max(0.0, $totalShown - $abonoTotal), 2);

        return [
            'cargo_edo'    => $cargoBaseVisible,
            'abono_edo'    => $abonoEdo,
            'abono_pay'    => $abonoPay,
            'total_shown'  => $totalShown,
            'abono_total'  => $abonoTotal,
            'saldo_shown'  => $saldoShown,
            'status_pago'  => $this->computeFinancialStatus($totalShown, $abonoTotal, $prevBalance),
            'due_date'     => null,
            'paid_at'      => null,
        ];
    }


    private function recalcStatementFromPayments(string $accountId, string $period): array
    {
        $adm = $this->adm;

        $st = DB::connection($adm)->table('billing_statements')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        if (!$st) {
            return [
                'cargo' => 0,
                'abono' => 0,
                'saldo' => 0,
                'status' => 'void',
            ];
        }

        // ==============================
        // TOTAL PAGADO GLOBAL (CRITICO)
        // ==============================
        $totalPaidGlobal = DB::connection($adm)->table('payments')
            ->where('account_id', $accountId)
            ->whereIn(DB::raw('LOWER(status)'), [
                'paid','pagado','succeeded','success','completed','complete','captured'
            ])
            ->sum('amount');

        $totalPaidGlobal = round($totalPaidGlobal / 100, 2);

        // ==============================
        // TOTAL CARGOS GLOBAL
        // ==============================
        $totalChargedGlobal = DB::connection($adm)->table('billing_statements')
            ->where('account_id', $accountId)
            ->sum('total_cargo');

        $totalChargedGlobal = round((float)$totalChargedGlobal, 2);

        $globalBalance = round($totalPaidGlobal - $totalChargedGlobal, 2);

        // ==============================
        // CARGO DEL PERIODO
        // ==============================
        $cargo = round((float) $st->total_cargo, 2);

        // ==============================
        // CALCULAR SALDO ACUMULADO HASTA ESTE PERIODO
        // ==============================
        $totalPaidGlobal = DB::connection($adm)->table('payments')
            ->where('account_id', $accountId)
            ->whereIn(DB::raw('LOWER(status)'), [
                'paid','pagado','succeeded','success','completed','complete','captured'
            ])
            ->sum('amount');

        $totalPaidGlobal = round($totalPaidGlobal / 100, 2);

        // cargos hasta este periodo (ordenados)
        $totalChargedUntilPeriod = DB::connection($adm)->table('billing_statements')
            ->where('account_id', $accountId)
            ->where('period', '<=', $period)
            ->sum('total_cargo');

        $totalChargedUntilPeriod = round((float)$totalChargedUntilPeriod, 2);

        // saldo disponible hasta este periodo
        $balanceUntilPeriod = round($totalPaidGlobal - $totalChargedUntilPeriod, 2);

        // ==============================
        // SI ALCANZA PARA ESTE PERIODO → PAGADO
        // ==============================
        if ($balanceUntilPeriod >= 0) {
            return [
                'cargo' => $cargo,
                'abono' => $cargo,
                'saldo' => 0,
                'status' => 'paid',
            ];
        }

        // ==============================
        // SI NO → lógica normal por periodo
        // ==============================
        $paid = DB::connection($adm)->table('payments')
            ->where('account_id', $accountId)
            ->where(function ($q) use ($period) {
                $q->where('period', $period)
                ->orWhere('period', 'like', $period.'%');
            })
            ->whereIn(DB::raw('LOWER(status)'), [
                'paid','pagado','succeeded','success','completed','complete','captured'
            ])
            ->sum('amount');

        $paid = round($paid / 100, 2);
        $saldo = round(max(0, $cargo - $paid), 2);

        $status = 'pending';

        if ($saldo <= 0.01 && $paid > 0) {
            $status = 'paid';
        } elseif ($paid > 0 && $saldo > 0) {
            $status = 'partial';
        }

        return [
            'cargo' => $cargo,
            'abono' => $paid,
            'saldo' => $saldo,
            'status' => $status,
        ];
    }
}