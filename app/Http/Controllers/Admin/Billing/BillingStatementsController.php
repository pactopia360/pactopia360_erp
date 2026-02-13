<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\Billing\BillingStatementsController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
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

                // =========================================================
        // ✅ FILTRO: SOLO SELECCIONADAS (UI)
        // - only_selected=1
        // - ids=1,2,3 (CSV)  ó ids[]=1&ids[]=2
        // =========================================================
        $onlySelected = $req->boolean('only_selected')
            || $req->boolean('onlySelected')
            || ((string)$req->get('only_selected', '') === '1');

        $selectedIds = [];

        $idsRaw = $req->get('ids', null);

        if (is_array($idsRaw)) {
            $selectedIds = array_values(array_filter(array_map(static function ($v) {
                $s = trim((string)$v);
                if ($s === '') return null;

                // account_id en admin suele ser numérico
                if (preg_match('/^\d+$/', $s)) return $s;

                // fallback ultra permisivo por si algún día no es int
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

        // límite defensivo (evita URLs enormes)
        if (count($selectedIds) > 500) {
            $selectedIds = array_slice($selectedIds, 0, 500);
        }

        // Si only_selected=1 pero no viene ids, mostramos vacío (sin romper)
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
                    'cargo'    => 0,
                    'abono'    => 0,
                    'saldo'    => 0,
                    'accounts' => 0,
                    'paid_edo' => 0,
                    'paid_pay' => 0,
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
                    'cargo'    => 0,
                    'abono'    => 0,
                    'saldo'    => 0,
                    'accounts' => 0,
                    'paid_edo' => 0,
                    'paid_pay' => 0,
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

        $qb = DB::connection($this->adm)->table('accounts')
            ->select($select)
            ->orderByDesc($has('created_at') ? 'accounts.created_at' : 'accounts.id');

        // ✅ Prioridad: si viene only_selected, filtramos SOLO esos IDs
        if ($onlySelected) {
            $qb->whereIn('accounts.id', $selectedIds);

            // En modo "solo seleccionadas" ignoramos los filtros de cuenta exacta y búsqueda
            // para no "vaciar" la selección por accidente.
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


        $rows = $qb->paginate($perPage)->withQueryString();
        $ids  = $rows->pluck('id')->filter()->values()->all();

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

        $rows->getCollection()->transform(function ($r) use ($agg, $payAgg, $payMeta, $ovMap, $period, $now) {
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

            $totalShown = $cargoEdo > 0.00001 ? $cargoEdo : (float) $effectiveMxn;
            $saldoShown = (float) max(0.0, $totalShown - $abonoTotal);

            $statusPago = 'pendiente';
            if ($totalShown <= 0.00001) {
                $statusPago = 'sin_mov';
            } elseif ($saldoShown <= 0.00001) {
                $statusPago = 'pagado';
            } elseif ($abonoTotal > 0.00001 && $abonoTotal < ($totalShown - 0.00001)) {
                $statusPago = 'parcial';
            } else {
                $statusPago = 'pendiente';
            }

            $pm  = $payMeta[(string) $r->id] ?? [];
            $due = $pm['due_date'] ?? null;

            if ($saldoShown > 0.00001 && $this->isOverdue($period, $due, $now)) {
                $statusPago = 'vencido';
            }

            // ---- Normalización para Blade ----
            $r->cargo          = round($cargoEdo, 2);
            $r->expected_total = round((float) $effectiveMxn, 2);

            $r->total_shown = round($totalShown, 2);
            $r->abono       = round($abonoTotal, 2);
            $r->saldo_shown = round($saldoShown, 2);

            $r->saldo = round($saldoShown, 2);

            $r->abono_edo = round($abonoEdo, 2);
            $r->abono_pay = round($paidPayments, 2);

            $r->tarifa_label = (string) $tarifaLabel;
            $r->tarifa_pill  = (string) $tarifaPill;

            $r->status_pago = $statusPago;
            $r->status_auto = $statusPago;

            $r->last_paid   = $lastPaid;
            $r->pay_allowed = $payAllowed;

            $r->pay_last_paid_at = $pm['last_paid_at'] ?? null;
            $r->pay_due_date     = $due;
            $r->pay_method       = $pm['method'] ?? null;
            $r->pay_provider     = $pm['provider'] ?? null;
            $r->pay_status       = $pm['status'] ?? null;

            $ov = $ovMap[(string) $r->id] ?? null;
            $r  = $this->applyStatusOverride($r, $ov);

            return $r;
        });

        // Nota: este filtro aplica sobre la colección ya paginada (página actual).
        // Si quieres filtrar “global” por status, se requiere re-arquitectura (query/joins o prefetch).
        if ($status !== 'all') {
            $filtered = $rows->getCollection()
                ->filter(static fn ($x) => (string) ($x->status_pago ?? '') === $status)
                ->values();

            $rows = $this->repaginateCollection(
                $filtered,
                $perPage,
                (int) $req->get('page', LengthAwarePaginator::resolveCurrentPage()),
                $req->url(),
                $req->query()
            );
        }

        // KPIs basados en la colección mostrada
        $kCargo = 0.0;
        $kAbono = 0.0;
        $kSaldo = 0.0;
        $kEdo   = 0.0;
        $kPay   = 0.0;

        foreach ($rows->getCollection() as $r) {
            $totalShown = (float) ($r->total_shown ?? 0);
            $paid       = (float) ($r->abono ?? 0);
            $saldoShown = (float) ($r->saldo_shown ?? max(0, $totalShown - $paid));

            $kCargo += $totalShown;
            $kAbono += $paid;
            $kSaldo += $saldoShown;

            $kEdo += (float) ($r->abono_edo ?? 0);
            $kPay += (float) ($r->abono_pay ?? 0);
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
            'cargo'    => round($kCargo, 2),
            'abono'    => round($kAbono, 2),
            'saldo'    => round($kSaldo, 2),
            'accounts' => (int) $rows->getCollection()->count(),
            'paid_edo' => round($kEdo, 2),
            'paid_pay' => round($kPay, 2),
            ],
        ]);
    }

    /**
     * @param \Illuminate\Support\Collection<int, mixed> $items
     */
    private function repaginateCollection($items, int $perPage, int $page, string $path, array $query): LengthAwarePaginator
    {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $slice = $items->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            (int) $items->count(),
            $perPage,
            $page,
            ['path' => $path, 'query' => $query]
        );
    }

    // =========================================================
    // SHOW (DETALLE)
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
        $abonoTot = $abonoEdo + $abonoPay;

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
        $inject  = $this->shouldInjectServiceLine($items, (float) $expected, $stmtCfg['mode'] ?? 'monthly');

        $serviceLine = null;
        if ($inject) {
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

        $totalShown = $cargoEdo + ($serviceLine ? (float) $serviceLine->cargo : 0.0);
        $saldoShown = max(0.0, $totalShown - $abonoTot);

        $statusPago = 'pendiente';
        if ($totalShown <= 0.00001) {
            $statusPago = 'sin_mov';
        } elseif ($saldoShown <= 0.00001) {
            $statusPago = 'pagado';
        } elseif ($abonoTot > 0.00001 && $abonoTot < ($totalShown - 0.00001)) {
            $statusPago = 'parcial';
        } else {
            $statusPago = 'pendiente';
        }

        $recipients = $this->resolveRecipientsForAccount((string) $accountId, (string) ($acc->email ?? ''));

        return view('admin.billing.statements.show', [
            'account'        => $acc,
            'period'         => $period,
            'period_label'   => Str::title(Carbon::parse($period . '-01')->translatedFormat('F Y')),

            'items'          => $itemsUi,

            'cargo_real'     => round($cargoEdo, 2),
            'expected_total' => round((float) $expected, 2),
            'tarifa_label'   => (string) $tarifaLabel,
            'tarifa_pill'    => (string) $tarifaPill,

            'abono'          => round((float) $abonoTot, 2),
            'abono_edo'      => round((float) $abonoEdo, 2),
            'abono_pay'      => round((float) $abonoPay, 2),

            'total'          => round((float) $totalShown, 2),
            'saldo'          => round((float) $saldoShown, 2),

            'last_paid'      => $lastPaid,
            'pay_allowed'    => $payAllowed,

            'status_pago'    => $statusPago,

            'statement_cfg'  => $stmtCfg,
            'recipients'     => $recipients,

            'meta'           => $meta,

            'isModal'        => $req->boolean('modal'),
        ]);
    }

    /**
     * Calcula consumos/abonos/saldo para un periodo (misma lógica del PDF),
     * pero sin QR ni view. Se usa para sumar "periodo anterior pendiente".
     *
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

        $cargoEdo = (float) $items->sum('cargo');
        $abonoEdo = (float) $items->sum('abono');

        $abonoPay = (float) $this->sumPaymentsForAccountPeriod($accountId, $period);
        $abonoTot = $abonoEdo + $abonoPay;

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

        // =========================================================
        // ✅ Evidence gating para "prev_balance":
        // Si estamos escaneando periodos anteriores, NO inyectar servicio
        // cuando NO existe evidencia real en ese mes.
        // =========================================================
        $hasEvidence = $items->count() > 0;

        if (!$hasEvidence) {
            // payments por periodo (cualquier status) cuentan como evidencia
            if ($this->hasPaymentsForAccountPeriod($accountId, $period)) {
                $hasEvidence = true;
            }
        }

        if (!$hasEvidence) {
            // override manual también es evidencia
            if ($this->hasOverrideForAccountPeriod($accountId, $period)) {
                $hasEvidence = true;
            }
        }

        if ($forPrevScan && !$hasEvidence) {
            $inject = false; // evita meses fantasma
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

        $cargoShown = round($totalConsumos, 2);
        $saldo      = round(max(0.0, $cargoShown - $abonoTot), 2);

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

     /**
     * Un periodo solo debe considerarse "emitido" si hay evidencia real:
     * - movimientos en estados_cuenta, o
     * - pagos en payments (pending/paid/etc), o
     * - override en billing_statement_status_overrides
     */
    private function hasStatementEvidence(string $accountId, string $period): bool
    {
        try {
            // 1) estados_cuenta
            if (Schema::connection($this->adm)->hasTable('estados_cuenta')) {
                $cnt = (int) DB::connection($this->adm)->table('estados_cuenta')
                    ->where('account_id', $accountId)
                    ->where('periodo', $period)
                    ->count();

                if ($cnt > 0) return true;
            }

            // 2) payments
            if (Schema::connection($this->adm)->hasTable('payments')) {
                $cols = Schema::connection($this->adm)->getColumnListing('payments');
                $lc   = array_map('strtolower', $cols);
                $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

                if ($has('account_id')) {
                    $q = DB::connection($this->adm)->table('payments')->where('account_id', $accountId);

                    $this->applyPaymentsPeriodFilter($q, $period, $has('period'));

                    // si no hay status, igual cuenta como evidencia
                    if ($has('status')) {
                        $q->whereIn('status', [
                        'paid','succeeded','success','completed','complete','captured','authorized'
                        ]);

                    }

                    if ((int) $q->count() > 0) return true;
                }
            }

            // 3) overrides
            if (Schema::connection($this->adm)->hasTable($this->overrideTable())) {
                $cnt = (int) DB::connection($this->adm)->table($this->overrideTable())
                    ->where('account_id', $accountId)
                    ->where('period', $period)
                    ->count();

                if ($cnt > 0) return true;
            }
        } catch (\Throwable $e) {
            // en caso de error, mejor NO sumar prev para no inflar
            return false;
        }

        return false;
    }


    /**
     * Saldo anterior real = suma de saldos pendientes de periodos anteriores.
     * - Si hay lastPaid, NO consideramos periodos <= lastPaid (ya quedaron “cerrados”).
     * - Escanea hacia atrás hasta $maxMonths (seguridad).
     *
     * @return array{prev_period:?string, prev_balance:float}
     */
    private function computePrevOpenBalance(string $accountId, string $period, ?string $lastPaid, int $maxMonths = 24): array
    {
        $prevBalance = 0.0;
        $prevPeriodMostRecent = null;

        try {
            $cur = Carbon::createFromFormat('Y-m', $period)->startOfMonth();

            for ($i = 1; $i <= $maxMonths; $i++) {
                $p = $cur->copy()->subMonthsNoOverflow($i)->format('Y-m');
                if (!$this->isValidPeriod($p)) continue;

                if ($lastPaid && $this->isValidPeriod($lastPaid) && $p <= $lastPaid) {
                    break; // todo lo anterior ya no cuenta
                }

                // ✅ FIX: no sumar meses "fantasma" sin evidencia real de statement
                if (!$this->hasStatementEvidence($accountId, $p)) {
                    continue;
                }

                $tot = $this->computeStatementTotalsForPeriod($accountId, $p, true);

                $saldo = (float) ($tot['saldo'] ?? 0);

                if ($saldo > 0.00001) {
                    $prevBalance += $saldo;
                    if ($prevPeriodMostRecent === null) $prevPeriodMostRecent = $p;
                }

            }
        } catch (\Throwable $e) {
            return ['prev_period' => null, 'prev_balance' => 0.0];
        }

        $prevBalance = round(max(0.0, $prevBalance), 2);

        return [
            'prev_period'  => $prevPeriodMostRecent,
            'prev_balance' => $prevBalance,
        ];
    }


    // =========================================================
    // PDF / EMAIL DATA
    // =========================================================

    /**
     * @return array<string,mixed>
     */
    private function buildStatementData(string $accountId, string $period): array
    {
        $cur   = $this->computeStatementTotalsForPeriod($accountId, $period);
        $acc   = $cur['account'];
        $items = $cur['items'];

        $cargoShown = (float) ($cur['cargo'] ?? 0);
        $abonoTot   = (float) ($cur['abono'] ?? 0);
        $saldoCur   = (float) ($cur['saldo'] ?? 0);

        $prevInfo   = $this->computePrevOpenBalance((string)$accountId, (string)$period, $cur['last_paid'] ?? null);
        $prevPeriod = $prevInfo['prev_period'] ?? null;
        $prevSaldo  = (float) ($prevInfo['prev_balance'] ?? 0.0);
        $prevSaldo  = round(max(0.0, $prevSaldo), 2);


        $totalDue = round(max(0.0, $saldoCur + $prevSaldo), 2);

        $qrText = $this->resolveQrTextForStatement($accountId, $period, null);
        [$qrDataUri, $qrUrl] = $this->makeQrDataForText((string) ($qrText ?? ''));

        return [
            'account'        => $acc,
            'account_id'     => $accountId,
            'period'         => $period,
            'period_label'   => Str::title(Carbon::parse($period . '-01')->translatedFormat('F Y')),

            'items'          => $items,

            'consumos'       => $cur['consumos'] ?? [],
            'service_items'  => $cur['consumos'] ?? [],
            'consumos_total' => round((float) ($cur['consumos_total'] ?? 0), 2),

            'cargo_real'     => round((float) ($cur['cargo_real'] ?? 0), 2),
            'expected_total' => round((float) ($cur['expected_total'] ?? 0), 2),
            'tarifa_label'   => (string) ($cur['tarifa_label'] ?? '-'),
            'tarifa_pill'    => (string) ($cur['tarifa_pill'] ?? 'dim'),

            'cargo'              => round($cargoShown, 2),
            'abono'              => round($abonoTot, 2),
            'abono_edo'          => round((float) ($cur['abono_edo'] ?? 0), 2),
            'abono_pay'          => round((float) ($cur['abono_pay'] ?? 0), 2),
            'saldo'              => round(max(0.0, $saldoCur), 2),

            'prev_period'        => $prevPeriod,
            'prev_period_label'  => $prevPeriod ? Str::title(Carbon::parse($prevPeriod . '-01')->translatedFormat('F Y')) : null,
            'prev_balance'       => $prevPeriod ? $prevSaldo : 0.0,

            'current_period_due' => round(max(0.0, $saldoCur), 2),
            'total_due'          => $totalDue,

            'total'              => $totalDue,

            'generated_at'       => now(),

            'last_paid'          => $cur['last_paid'] ?? null,
            'pay_allowed'        => $cur['pay_allowed'] ?? null,

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
            'concepto'    => 'required|string|max:255',
            'detalle'     => 'nullable|string|max:2000',

            'cargo'       => 'nullable|numeric|min:0|max:99999999',
            'abono'       => 'nullable|numeric|min:0|max:99999999',

            'tipo'        => 'nullable|string|in:cargo,abono',
            'monto'       => 'nullable|numeric|min:0|max:99999999',

            'send_email'  => 'nullable|boolean',
            'to'          => 'nullable|string|max:2000',
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

    /**
     * POST /admin/billing/statements/lines
     */
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

    /**
     * PUT /admin/billing/statements/lines
     */
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

    /**
     * DELETE /admin/billing/statements/lines
     */
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

        $notes = trim((string) ($data['notes'] ?? ''));

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

        if (Schema::connection($this->adm)->hasTable('account_recipients')) {
            // Si viene vacío, NO tocamos recipients (para no desactivar todo por accidente)
            if ($rawRecipients !== '') {
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
        }

        return back()->with('ok', 'Estado de cuenta guardado (config + destinatarios).');
    }

    // =========================================================
    // PDF / EMAIL / BULK
    // =========================================================

    public function pdf(Request $req, string $accountId, string $period)
    {
        abort_if(!$this->isValidPeriod($period), 422);

        $data = $this->buildStatementData($accountId, $period);
        $data['isModal'] = $req->boolean('modal');

        // ✅ Forzar que el QR en Admin use overlay del logo (misma vista que Cliente)
        // (Cliente no se toca; solo Admin prende la bandera)
        $data['qr_force_overlay'] = true;

        // Tamaño sugerido del logo en el centro (px)
        // Ajusta a gusto: 30-44 suele verse bien
        $data['qr_logo_px'] = 38;

        $viewName = 'cliente.billing.pdf.statement';

        $inline = $req->boolean('inline') || $req->boolean('preview');

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

    /**
     * Envío masivo por periodo
     */
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
                    $saldo = (float) ($d['saldo'] ?? 0);

                    $computed = 'pendiente';
                    if ($cargo <= 0.00001) {
                        $computed = 'sin_mov';
                    } elseif ($saldo <= 0.00001) {
                        $computed = 'pagado';
                    } elseif ($abono > 0.00001 && $saldo > 0.00001) {
                        $computed = 'parcial';
                    } else {
                        $computed = 'pendiente';
                    }

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

    /**
     * Envío con PayLink/QR
     */
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

        foreach ($recipients as $dest) {
            try {
                Mail::to($dest)->send(new StatementAccountPeriodMail($accountId, $period, $data));

                Log::info('[ADMIN][STATEMENT][EMAIL] enviado', [
                    'to'         => $dest,
                    'account_id' => (string) ($acc->id ?? ''),
                    'period'     => $period,
                    'has_pay'    => (bool) ($data['pay_url'] ?? null),
                    'has_qr'     => (bool) (($data['qr_data_uri'] ?? null) ?: ($data['qr_url'] ?? null)),
                    'total'      => (float) ($data['total'] ?? 0),
                    'cargo'      => (float) ($data['cargo'] ?? 0),
                    'expected'   => (float) ($data['expected_total'] ?? 0),
                ]);
            } catch (\Throwable $e) {
                Log::error('[ADMIN][STATEMENT][EMAIL] fallo', [
                    'to'         => $dest,
                    'account_id' => (string) ($acc->id ?? ''),
                    'period'     => $period,
                    'err'        => $e->getMessage(),
                ]);
            }

            usleep(90000);
        }
    }

    // =========================================================
    // STRIPE (CHECKOUT) + PAYMENTS UPSERT
    // =========================================================

    /**
     * @return array{0:string,1:string} [checkout_url, session_id]
     */
    private function createStripeCheckoutForStatement(object $acc, string $period, float $totalPesos): array
    {
        $secret = (string) config('services.stripe.secret');
        if (trim($secret) === '') {
            throw new \RuntimeException('Stripe secret vacío en config(services.stripe.secret).');
        }

        $unitAmountCents = (int) round($totalPesos * 100);
        $accountId = (string) ($acc->id ?? '');
        $email     = (string) ($acc->email ?? '');

        $successUrl = Route::has('cliente.checkout.success')
            ? route('cliente.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}'
            : url('/cliente/checkout/success') . '?session_id={CHECKOUT_SESSION_ID}';

        $cancelUrl = Route::has('cliente.estado_cuenta')
            ? route('cliente.estado_cuenta') . '?period=' . urlencode($period)
            : url('/cliente/estado-de-cuenta') . '?period=' . urlencode($period);

        $idempotencyKey = 'admin_stmt:' . $accountId . ':' . $period . ':' . $unitAmountCents;

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
            'customer_email'      => $email !== '' ? $email : null,
            'client_reference_id' => $accountId,
            'success_url'         => $successUrl,
            'cancel_url'          => $cancelUrl,
            'metadata'            => [
                'type'      => 'billing_statement',
                'account_id' => $accountId,
                'period'     => $period,
                'source'     => 'admin_email',
                'amount_mxn' => round($totalPesos, 2),
            ],
        ], [
            'idempotency_key' => $idempotencyKey,
        ]);

        $sessionId  = (string) ($session->id ?? '');
        $sessionUrl = (string) ($session->url ?? '');

        $this->upsertPendingPaymentForStatement($accountId, $period, $unitAmountCents, $sessionId, $totalPesos);

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
        if ($has('amount_mxn')) {
            $row['amount_mxn'] = round($uiTotalPesos, 2);
        }

        if ($has('currency')) {
            $row['currency'] = 'MXN';
        }
        if ($has('status')) {
            $row['status'] = 'pending';
        }
        if ($has('due_date')) {
            $row['due_date'] = now()->addDays(4);
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
            $row['reference'] = $sessionId ?: ('admin_stmt:' . $accountId . ':' . $period);
        }

        if ($has('stripe_session_id')) {
            $row['stripe_session_id'] = $sessionId;
        }

        if ($has('meta')) {
            $row['meta'] = json_encode([
                'type'           => 'billing_statement',
                'period'         => $period,
                'ui_total_pesos' => round($uiTotalPesos, 2),
                'source'         => 'admin_email',
            ], JSON_UNESCAPED_UNICODE);
        }

        $row['updated_at'] = now();
        if (!$existing) {
            $row['created_at'] = now();
        }

        if ($existing && $has('id')) {
            DB::connection($this->adm)->table('payments')->where('id', (int) $existing->id)->update($row);
        } else {

            // ✅ payments.account_id es NOT NULL en mysql_admin: siempre forzar account_id
            $aid = (int)($row['account_id'] ?? 0);
            if ($aid <= 0 && isset($accountId)) {
                $aid = (int)$accountId;
            }
            if ($aid <= 0) {
                throw new \RuntimeException('payments insert blocked: missing account_id');
            }
            $row['account_id'] = $aid;

            DB::connection($this->adm)->table('payments')->insert($row);
        }
    }

    // =========================================================
    // RECIPIENTS
    // =========================================================

    /**
     * @return array<int, string>
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
    // HELPERS: SERVICE INJECTION / MODE
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
            $hasAnyCargo = false;
            foreach ($items as $it) {
                $cargoIt = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
                if ($cargoIt > 0.00001) {
                    $hasAnyCargo = true;
                    break;
                }
            }
            return $hasAnyCargo;
        }

        return true;
    }

    /**
     * Devuelve 'mensual' o 'anual'
     */
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

        if (!in_array($mode, ['mensual', 'anual'], true)) {
            $mode = 'mensual';
        }

        return $mode;
    }

    // =========================================================
    // HELPERS internos para líneas/config
    // =========================================================

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
    // QR (dompdf-safe)
    // =========================================================

    /**
     * @return array{0:?string,1:?string} [data_uri_png, remote_url]
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

        $qrUrl = 'https://quickchart.io/qr?text=' . urlencode($text)
            . '&size=' . $size
            . '&margin=1&format=png';

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
            // ignore
        }

        try {
            if (Route::has('cliente.estado_cuenta')) {
                return route('cliente.estado_cuenta') . '?period=' . urlencode($period);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    // =========================================================
    // EVIDENCE GATING (prev_balance)
    // =========================================================

    private function hasPaymentsForAccountPeriod(string $accountId, string $period): bool
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return false;
        }

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id') || !$has('period')) {
                return false;
            }

            $q = DB::connection($this->adm)->table('payments')
                ->where('account_id', $accountId);

            $this->applyPaymentsPeriodFilter($q, $period, $has('period'));


            // =========================================================
            // ✅ SOT (prev_balance):
            // Payments cuentan como evidencia SOLO si están PAGADOS.
            // Pending/open/incomplete NO deben crear "meses fantasma".
            // =========================================================
            if ($has('status')) {
                $q->whereIn('status', [
                    'paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized',
                ]);
            } else {
                // Si no hay columna status, mejor NO contar payments como evidencia (evita falsos positivos)
                return false;
            }

            return $q->limit(1)->exists();
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] hasPaymentsForAccountPeriod failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function hasOverrideForAccountPeriod(string $accountId, string $period): bool
    {
        try {
            if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
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


    // =========================================================
    // PAYMENTS (paid)
    // =========================================================

    // =========================================================
    // PAYMENTS PERIOD FILTER (robusto: YYYY-MM ó YYYY-MM-DD)
    // =========================================================
    private function applyPaymentsPeriodFilter($q, string $period, bool $hasPeriodCol): void
    {
        if (!$hasPeriodCol) return;

        // Acepta:
        // - "2026-02"
        // - "2026-02-01"
        // - "2026-02-01 10:22:33"
        // - date/datetime column (MySQL lo castea a string en LIKE)
        $p = trim($period);
        if (!$this->isValidPeriod($p)) {
            // si viene raro, intenta normalizar y si no, no filtra
            $pp = $this->parseToPeriod($p);
            if (!$pp) return;
            $p = $pp;
        }

        $q->where(function ($w) use ($p) {
            $w->where('period', $p)
            ->orWhere('period', 'like', $p . '%'); // cubre YYYY-MM-DD y datetime
        });
    }

    private function sumPaymentsForAccountPeriod(string $accountId, string $period): float
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return 0.0;
        }

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id')) {
                return 0.0;
            }

            $amountMxnCol = $has('amount_mxn') ? 'amount_mxn' : null;
            $amountCol    = $has('amount') ? 'amount' : null;

            if (!$amountMxnCol && !$amountCol) {
                return 0.0;
            }

            $q = DB::connection($this->adm)->table('payments')
                ->where('account_id', $accountId);

            $this->applyPaymentsPeriodFilter($q, $period, $has('period'));

            if ($has('status')) {
                $q->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized']);
            }

            if ($amountMxnCol) {
                return round((float) ($q->sum($amountMxnCol) ?? 0), 2);
            }

            $cents = (float) ($q->sum($amountCol) ?? 0);
            if ($cents <= 0) {
                return 0.0;
            }

            return round($cents / 100.0, 2);
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] sumPaymentsForAccountPeriod failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * @param array<int,string|int> $accountIds
     * @return array<string,float>
     */
    private function sumPaymentsForAccountsPeriod(array $accountIds, string $period): array
    {
        $out = [];
        if (empty($accountIds)) {
            return $out;
        }
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return $out;
        }

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id')) {
                return $out;
            }

            $amountMxnCol = $has('amount_mxn') ? 'amount_mxn' : null;
            $amountCol    = $has('amount') ? 'amount' : null;

            if (!$amountMxnCol && !$amountCol) {
                return $out;
            }

            $q = DB::connection($this->adm)->table('payments')
                ->whereIn('account_id', $accountIds);

            $this->applyPaymentsPeriodFilter($q, $period, $has('period'));

            if ($has('status')) {
                $q->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized']);
            }

            if ($amountMxnCol) {
                $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(' . $amountMxnCol . ',0)) as s')
                    ->groupBy('account_id')
                    ->get();

                foreach ($rows as $r) {
                    $out[(string) $r->aid] = round((float) ($r->s ?? 0), 2);
                }
                return $out;
            }

            $rows = $q->selectRaw('account_id as aid, SUM(COALESCE(' . $amountCol . ',0)) as s')
                ->groupBy('account_id')
                ->get();

            foreach ($rows as $r) {
                $cents = (float) ($r->s ?? 0);
                $out[(string) $r->aid] = round($cents / 100.0, 2);
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] sumPaymentsForAccountsPeriod failed', [
                'period' => $period,
                'err'    => $e->getMessage(),
            ]);
            return $out;
        }
    }

    /**
     * @param array<int,string|int> $accountIds
     * @return array<string,array{status:?string,method:?string,provider:?string,due_date:mixed,last_paid_at:mixed}>
     */
    private function fetchPaymentsMetaForAccountsPeriod(array $accountIds, string $period): array
    {
        $out = [];
        if (empty($accountIds)) {
            return $out;
        }
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return $out;
        }

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id')) {
                return $out;
            }

            $q = DB::connection($this->adm)->table('payments')
                ->whereIn('account_id', $accountIds);

            $this->applyPaymentsPeriodFilter($q, $period, $has('period'));

            $select = ['account_id'];
            foreach (['status', 'method', 'provider', 'due_date', 'paid_at', 'created_at', 'updated_at'] as $c) {
                if ($has($c)) {
                    $select[] = $c;
                }
            }

            $orderCol = $has('paid_at') ? 'paid_at'
                : ($has('updated_at') ? 'updated_at'
                : ($has('created_at') ? 'created_at'
                : ($has('id') ? 'id' : $cols[0])));

            $rows = $q->select($select)
                ->orderByDesc($orderCol)
                ->get();

            foreach ($rows as $r) {
                $aid = (string) ($r->account_id ?? '');
                if ($aid === '' || isset($out[$aid])) {
                    continue;
                }

                $lastPaidAt = null;
                if ($has('paid_at') && !empty($r->paid_at)) {
                    $lastPaidAt = $r->paid_at;
                } elseif ($has('updated_at') && !empty($r->updated_at)) {
                    $lastPaidAt = $r->updated_at;
                } elseif ($has('created_at') && !empty($r->created_at)) {
                    $lastPaidAt = $r->created_at;
                }

                $out[$aid] = [
                    'status'       => $has('status') ? (string) ($r->status ?? '') : null,
                    'method'       => $has('method') ? (string) ($r->method ?? '') : null,
                    'provider'     => $has('provider') ? (string) ($r->provider ?? '') : null,
                    'due_date'     => $has('due_date') ? ($r->due_date ?? null) : null,
                    'last_paid_at' => $lastPaidAt,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] fetchPaymentsMetaForAccountsPeriod failed', [
                'period' => $period,
                'err'    => $e->getMessage(),
            ]);
            return $out;
        }
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
            // Mes actual o futuro => NO vencido
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
            // Si dueDate es basura / parse falla, caer al fallback por periodo
        }

        // =========================================================
        // 2) Fallback robusto por periodo (sin depender de payments):
        //    - Se considera vencido si ya pasó el fin del mes + 4 días.
        // =========================================================
        try {
            $start = Carbon::createFromFormat('Y-m-d', $p . '-01')->startOfMonth();
            $cut   = $start->copy()->endOfMonth()->addDays(4)->endOfDay();
            return $now->gt($cut);
        } catch (\Throwable $e) {
            return false;
        }
    }


    // =========================================================
    // META / PRICING helpers
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
        if ($v === null) {
            return null;
        }
        if (is_float($v) || is_int($v)) {
            return (float) $v;
        }

        if (is_string($v)) {
            $s = trim($v);
            if ($s === '') {
                return null;
            }

            $s = str_replace(['$', ',', ' '], '', $s);
            if (!is_numeric($s)) {
                return null;
            }

            return (float) $s;
        }

        if (is_numeric($v)) {
            return (float) $v;
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

    private function resolveLastPaidPeriodForAccount(string $accountId, array $meta): ?string
    {
        $key = trim((string)$accountId);
        if ($key === '') return null;

        if (array_key_exists($key, $this->cacheLastPaid)) {
            return $this->cacheLastPaid[$key];
        }

        $lastPaid = null;

        try {
            foreach ([
                data_get($meta, 'stripe.last_paid_at'),
                data_get($meta, 'stripe.lastPaidAt'),
                data_get($meta, 'billing.last_paid_at'),
                data_get($meta, 'billing.lastPaidAt'),
                data_get($meta, 'last_paid_at'),
                data_get($meta, 'lastPaidAt'),
            ] as $v) {
                $p = $this->parseToPeriod($v);
                if ($p) { $lastPaid = $p; break; }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if (!$lastPaid && Schema::connection($this->adm)->hasTable('payments')) {
            try {
                $cols = Schema::connection($this->adm)->getColumnListing('payments');
                $lc   = array_map('strtolower', $cols);
                $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

                if ($has('account_id') && $has('status') && $has('period')) {
                    $q = DB::connection($this->adm)->table('payments')
                        ->where('account_id', $key)
                        ->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized']);

                    $row = $q->orderByDesc(
                        $has('paid_at') ? 'paid_at'
                        : ($has('created_at') ? 'created_at'
                        : ($has('id') ? 'id' : $cols[0]))
                    )->first(['period']);

                    if ($row && !empty($row->period)) {
                        $p = $this->parseToPeriod($row->period);
                        if ($p && $this->isValidPeriod($p)) {
                            $lastPaid = $p;
                        }
                    }

                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $this->cacheLastPaid[$key] = $lastPaid;
        return $lastPaid;
    }



    // =========================================================
    // PERIOD helpers
    // =========================================================

    private function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period);
    }

    private function parseToPeriod(mixed $value): ?string
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->format('Y-m');
            }

            if (is_numeric($value)) {
                $ts = (int) $value;
                if ($ts > 0) {
                    return Carbon::createFromTimestamp($ts)->format('Y-m');
                }
            }

            if (is_string($value)) {
                $v = trim($value);
                if ($v === '') {
                    return null;
                }

                $v = str_replace('/', '-', $v);
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

    // =========================================================
    // STATUS OVERRIDE (cuenta+periodo)
    // =========================================================

    private function overrideTable(): string
    {
        return 'billing_statement_status_overrides';
    }

    /**
     * @param array<int,string|int> $accountIds
     * @return array<string, array{
     *   status:string,
     *   reason:?string,
     *   updated_by:?int,
     *   updated_at:?string,
     *   pay_method:?string,
     *   pay_provider:?string,
     *   pay_status:?string,
     *   paid_at:mixed
     * }>
     */
    private function fetchStatusOverridesForAccountsPeriod(array $accountIds, string $period): array
    {
        $out = [];
        if (empty($accountIds)) return $out;

        try {
            if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
                return $out;
            }

            $cols = Schema::connection($this->adm)->getColumnListing($this->overrideTable());
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            $select = ['account_id', 'period', 'status_override', 'reason', 'updated_by', 'updated_at'];
            foreach (['pay_method','pay_provider','status','paid_at','meta'] as $c) {
                if ($has($c)) $select[] = $c;
            }

            $rows = DB::connection($this->adm)->table($this->overrideTable())
                ->select($select)
                ->where('period', $period)
                ->whereIn('account_id', $accountIds)
                ->get();

            foreach ($rows as $r) {
                $aid = (string) ($r->account_id ?? '');
                if ($aid === '') continue;

                $out[$aid] = [
                    'status'       => (string) ($r->status_override ?? ''),
                    'reason'       => isset($r->reason) ? (string) $r->reason : null,
                    'updated_by'   => isset($r->updated_by) ? (int) $r->updated_by : null,
                    'updated_at'   => isset($r->updated_at) ? (string) $r->updated_at : null,

                    // 👇 “fuente UI” (no payments)
                    'pay_method'   => $has('pay_method')   ? (string) ($r->pay_method ?? '')   : null,
                    'pay_provider' => $has('pay_provider') ? (string) ($r->pay_provider ?? '') : null,
                    'pay_status'   => $has('status')       ? (string) ($r->status ?? '')       : null,
                    'paid_at'      => $has('paid_at')      ? ($r->paid_at ?? null)             : null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] fetchStatusOverridesForAccountsPeriod failed', [
                'period' => $period,
                'err'    => $e->getMessage(),
            ]);
        }

        return $out;
    }


    private function applyStatusOverride(object $row, ?array $ov): object
    {
        $row->status_auto = (string) ($row->status_pago ?? 'sin_mov');

        // Valores default (lo que ya traías por payments)
        $row->ov_pay_method   = null;
        $row->ov_pay_provider = null;
        $row->ov_pay_status   = null;
        $row->ov_paid_at      = null;

        $allowed = ['pendiente', 'parcial', 'pagado', 'vencido', 'sin_mov'];

        if ($ov && isset($ov['status'])) {
            $s = strtolower(trim((string) $ov['status']));
            if ($s !== '' && in_array($s, $allowed, true)) {
                $row->status_override            = $s;
                $row->status_pago                = $s;

                $row->status_override_reason     = $ov['reason'] ?? null;
                $row->status_override_updated_at = $ov['updated_at'] ?? null;
                $row->status_override_updated_by = $ov['updated_by'] ?? null;

                // 👇 Si hay datos UI en overrides, úsalo para mostrar “Método/Prov/St”
                $pm = isset($ov['pay_method']) && trim((string)$ov['pay_method']) !== '' ? (string)$ov['pay_method'] : null;
                $pp = isset($ov['pay_provider']) && trim((string)$ov['pay_provider']) !== '' ? (string)$ov['pay_provider'] : null;
                $ps = isset($ov['pay_status']) && trim((string)$ov['pay_status']) !== '' ? (string)$ov['pay_status'] : null;

                $row->ov_pay_method   = $pm;
                $row->ov_pay_provider = $pp;
                $row->ov_pay_status   = $ps;
                $row->ov_paid_at      = $ov['paid_at'] ?? null;

                // ✅ FIX LISTADO (SOT):
                // El Blade pinta pay_* (no ov_*). Si hay override, el “display” debe venir del override.
                if ($pm !== null) $row->pay_method = $pm;
                if ($pp !== null) $row->pay_provider = $pp;
                if ($ps !== null) $row->pay_status = $ps;

                // paid_at visible (si tu UI lo usa como last_paid_at)
                if ($row->ov_paid_at !== null) {
                    $row->pay_last_paid_at = $row->ov_paid_at;
                }

                // ✅ FIX (SOT): si hay override y NO es "pagado", la UI debe ignorar payments
                // para reflejar saldo pendiente aunque existan payments PAID en el periodo.
                if ($s !== 'pagado') {
                    // Ignorar payments en UI
                    $row->abono_pay = 0.0;

                    $abEdo = (float) ($row->abono_edo ?? 0.0);

                    // total mostrado: usa total_shown si existe, si no, cargo o expected_total
                    $total = (float) ($row->total_shown ?? 0.0);
                    if ($total <= 0.00001) {
                        $c = (float) ($row->cargo ?? 0.0);
                        $e = (float) ($row->expected_total ?? 0.0);
                        $total = $c > 0.00001 ? $c : $e;
                    }

                    // En override NO pagado: abono = solo edo cta, saldo = total - abono_edo
                    $row->abono = round($abEdo, 2);

                    $saldo = (float) max(0.0, $total - $abEdo);
                    $row->saldo_shown = round($saldo, 2);
                    $row->saldo       = round($saldo, 2);
                }


                return $row;

            }
        }

        $row->status_override            = null;
        $row->status_override_reason     = null;
        $row->status_override_updated_at = null;
        $row->status_override_updated_by = null;

        return $row;
    }


    /**
     * POST /admin/billing/statements/status
     * AJAX: guarda override de status + método de pago.
     * Si status = pagado y existe saldo pendiente, registra un pago manual en payments para cerrar el saldo.
     */
    public function statusAjax(Request $req): JsonResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => 'required|string',
            'status'     => 'required|string|in:pendiente,parcial,pagado,vencido,sin_mov',
            'pay_method' => 'nullable|string|max:30',
        ]);

        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];
        $status    = strtolower(trim((string) $data['status']));
        $payMethod = strtolower(trim((string) ($data['pay_method'] ?? '')));
        $payMethod = $payMethod !== '' ? $payMethod : 'manual';

        if (!$this->isValidPeriod($period)) {
            return response()->json(['ok' => false, 'message' => 'Periodo inválido.'], 422);
        }

        if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
            return response()->json([
                'ok'      => false,
                'message' => 'No existe la tabla billing_statement_status_overrides (corre migraciones).',
            ], 422);
        }

        abort_unless(Schema::connection($this->adm)->hasTable('accounts'), 404);
        abort_unless(Schema::connection($this->adm)->hasTable('estados_cuenta'), 404);

        // columnas override (para guardar pay_method/provider si existen)
        $ovCols = Schema::connection($this->adm)->getColumnListing($this->overrideTable());
        $ovLc   = array_map('strtolower', $ovCols);
        $ovHas  = static fn (string $c): bool => in_array(strtolower($c), $ovLc, true);

        $by = auth('admin')->id();

        // 1) Guardar override (y método/proveedor si aplica)
        DB::connection($this->adm)->transaction(function () use ($accountId, $period, $status, $by, $payMethod, $ovHas) {
            $q = DB::connection($this->adm)->table($this->overrideTable())
                ->where('account_id', $accountId)
                ->where('period', $period);

            $exists = $q->first(['id']);

            $payload = [
                'account_id'      => $accountId,
                'period'          => $period,
                'status_override' => $status,
                'reason'          => null,
                'updated_by'      => $by ? (int) $by : null,
                'updated_at'      => now(),
            ];

            // Tu tabla override tiene estas cols (según tu tinker)
            if ($ovHas('pay_method'))   $payload['pay_method']   = $payMethod;
            if ($ovHas('pay_provider')) $payload['pay_provider'] = 'manual';
            if ($ovHas('paid_at'))      $payload['paid_at']      = ($status === 'pagado') ? now() : null;

            // ✅ Si existe columna status en overrides, úsala como "pay_status" UI (coherente con status_override)
            if ($ovHas('status'))       $payload['status']       = $status;

            if ($exists && isset($exists->id)) {
                DB::connection($this->adm)->table($this->overrideTable())
                    ->where('id', (int) $exists->id)
                    ->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::connection($this->adm)->table($this->overrideTable())->insert($payload);
            }
        });

        // 2) Cargar cuenta/meta
        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        if (!$acc) {
            return response()->json(['ok' => false, 'message' => 'Cuenta no encontrada.'], 404);
        }

        $agg = DB::connection($this->adm)->table('estados_cuenta')
            ->selectRaw('SUM(COALESCE(cargo,0)) as cargo, SUM(COALESCE(abono,0)) as abono')
            ->where('account_id', $accountId)
            ->where('periodo', $period)
            ->first();

        $cargoEdo = (float) ($agg->cargo ?? 0);
        $abonoEdo = (float) ($agg->abono ?? 0);

        $meta = $this->hub->decodeMeta($acc->meta ?? null);
        if (!is_array($meta)) $meta = [];

        $lastPaid = $this->resolveLastPaidPeriodForAccount((string) $accountId, $meta);
        $payAllowed = $lastPaid
            ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
            : $period;

        $customMxn = $this->extractCustomAmountMxn($acc, $meta);
        if ($customMxn !== null && $customMxn > 0.00001) {
            $expected = (float) $customMxn;
        } else {
            [$expected] = $this->safeResolveEffectiveAmountFromMeta($meta, $period, $payAllowed);
            $expected = (float) $expected;
        }

        // Helper local: calcula total/abono/saldo
        // ✅ Si override NO es "pagado", NO contamos payments para que el saldo pendiente se vea correctamente.
        $recalc = function (bool $includePayments = true) use ($accountId, $period, $cargoEdo, $abonoEdo, $expected): array {
            $paidPayments = $includePayments ? (float) $this->sumPaymentsForAccountPeriod($accountId, $period) : 0.0;

            $totalShown = $cargoEdo > 0.00001 ? $cargoEdo : $expected;

            // abono_edo + (payments paid si aplica)
            $abonoTotal = (float) ($abonoEdo + $paidPayments);

            $saldoShown = (float) max(0.0, $totalShown - $abonoTotal);

            return [
                'total'      => $totalShown,
                'abono_edo'  => (float) $abonoEdo,
                'abono_pay'  => (float) $paidPayments,
                'abono'      => (float) $abonoTotal,
                'saldo'      => (float) $saldoShown,
            ];
        };


        /**
         * ✅ UI override-aware:
         * - pagado  => abono=total, saldo=0
         * - pendiente/vencido/sin_mov => abono=0, saldo=total
         * - parcial => conserva abono real (limitado a total)
         */
        $mapUi = function (string $st, array $m): array {
            $total    = (float) ($m['total'] ?? 0);
            $abonoReal = (float) ($m['abono'] ?? 0);
            $saldoReal = (float) ($m['saldo'] ?? max(0.0, $total - $abonoReal));

            $abonoUi = $abonoReal;
            $saldoUi = $saldoReal;

            if ($st === 'pagado') {
                $abonoUi = $total;
                $saldoUi = 0.0;
            } elseif (in_array($st, ['pendiente', 'vencido', 'sin_mov'], true)) {
                $abEdo   = (float) ($m['abono_edo'] ?? 0.0);
                $abonoUi = max(0.0, min($abEdo, $total));
                $saldoUi = max(0.0, $total - $abonoUi);
            }
            elseif ($st === 'parcial') {
                $abonoUi = max(0.0, min($abonoReal, $total));
                $saldoUi = max(0.0, $total - $abonoUi);
            }

            return [
                'total' => $total,
                'abono' => $abonoUi,
                'saldo' => $saldoUi,
            ];
        };

        // 3) Si NO es pagado: revertir payment auto (si existe) para permitir cambiar entre estatus
        //    (Solo tocamos el payment con reference exacta admin_paid:{aid}:{period})
        if ($status !== 'pagado') {
            if (Schema::connection($this->adm)->hasTable('payments')) {
                try {
                    $pCols = Schema::connection($this->adm)->getColumnListing('payments');
                    $pLc   = array_map('strtolower', $pCols);
                    $pHas  = static fn (string $c): bool => in_array(strtolower($c), $pLc, true);

                    if ($pHas('account_id') && $pHas('reference')) {
                        $ref = 'admin_paid:' . $accountId . ':' . $period;

                        $q = DB::connection($this->adm)->table('payments')
                            ->where('account_id', $accountId)
                            ->where('reference', $ref);

                        // period robusto si existe col
                        $this->applyPaymentsPeriodFilter($q, $period, $pHas('period'));

                        // si existe status, cancelamos; si no, borramos (es nuestro auto-payment)
                        if ($pHas('status')) {
                            $q->update([
                                'status'     => 'canceled',
                                'updated_at' => now(),
                            ]);
                        } else {
                            $q->delete();
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('[ADMIN][STATEMENTS][statusAjax] revert auto-paid failed', [
                        'account_id' => $accountId,
                        'period'     => $period,
                        'err'        => $e->getMessage(),
                    ]);
                }
            }

            $m  = $recalc(false); // ✅ NO incluir payments si NO es pagado
            $ui = $mapUi($status, $m);

            return response()->json([

                'ok'         => true,
                'message'    => 'Guardado.',
                'account_id' => $accountId,
                'period'     => $period,
                'status'     => $status,
                'pay_method' => $payMethod,

                'total'      => round((float) $ui['total'], 2),
                'abono'      => round((float) $ui['abono'], 2),
                'saldo'      => round((float) $ui['saldo'], 2),
            ]);
        }

        // 4) Si es pagado: crear/upsert payment PAID por saldo pendiente (si aplica)
        $m0 = $recalc();
        if ((float) $m0['saldo'] > 0.00001) {
            $this->upsertPaidPaymentForStatement(
                $accountId,
                $period,
                (float) $m0['saldo'],
                $payMethod,
                'manual'
            );
        }

        // 5) (Opcional) sincronizar método en el payment más reciente del periodo (si existe)
        if (Schema::connection($this->adm)->hasTable('payments')) {
            try {
                $cols = Schema::connection($this->adm)->getColumnListing('payments');
                $lc   = array_map('strtolower', $cols);
                $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

                if ($has('account_id')) {
                    $q = DB::connection($this->adm)->table('payments')->where('account_id', $accountId);
                    $this->applyPaymentsPeriodFilter($q, $period, $has('period'));

                    $orderCol = $has('paid_at') ? 'paid_at'
                        : ($has('updated_at') ? 'updated_at'
                        : ($has('created_at') ? 'created_at'
                        : ($has('id') ? 'id' : $cols[0])));

                    $idCol = $has('id') ? 'id' : $cols[0];

                    $row = $q->orderByDesc($orderCol)->first([$idCol]);

                    if ($row && isset($row->{$idCol})) {
                        $update = ['updated_at' => now()];
                        if ($has('method'))   $update['method']   = $payMethod;
                        if ($has('provider')) $update['provider'] = 'manual';
                        DB::connection($this->adm)->table('payments')->where($idCol, (int) $row->{$idCol})->update($update);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[ADMIN][STATEMENTS][statusAjax] no pudo actualizar pay_method/provider en payments', [
                    'account_id' => $accountId,
                    'period'     => $period,
                    'err'        => $e->getMessage(),
                ]);
            }
        }

        // 6) Respuesta final ya con payment persistido (UI override-aware)
        $m1 = $recalc();
        $ui = $mapUi($status, $m1);

        return response()->json([
            'ok'         => true,
            'message'    => 'Guardado.',
            'account_id' => $accountId,
            'period'     => $period,
            'status'     => $status,
            'pay_method' => $payMethod,

            'total'      => round((float) $ui['total'], 2),
            'abono'      => round((float) $ui['abono'], 2),
            'saldo'      => round((float) $ui['saldo'], 2),
        ]);
    }


    /**
     * ✅ Upsert de pago "PAID" para un estado de cuenta (admin override pagado).
     * - Guarda/actualiza payments (paid) para (account_id, period).
     * - amount: guarda tanto amount_mxn (si existe) como amount en centavos.
     * - method: manual | transfer | card | etc
     * - provider: manual (default)
     */
    private function upsertPaidPaymentForStatement(
    string $accountId,
    string $period,
    float $amountMxn,
    string $method = 'manual',
    string $provider = 'manual'
    ): void {
        if (!Schema::connection($this->adm)->hasTable('payments')) return;

        $amountMxn = round(max(0.0, $amountMxn), 2);
        if ($amountMxn <= 0.00001) return;

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id')) return;

            $ref = 'admin_paid:' . $accountId . ':' . $period;

            // ✅ solo buscamos/actualizamos el payment MANUAL que nosotros creamos (por reference)
            $existing = null;
            if ($has('reference')) {
                $q = DB::connection($this->adm)->table('payments')
                    ->where('account_id', $accountId)
                    ->where('reference', $ref);

                if ($has('period')) {
                    $this->applyPaymentsPeriodFilter($q, $period, true);
                }

                $idCol = $has('id') ? 'id' : $cols[0];
                $existing = $q->orderByDesc($idCol)->first([$idCol]);
            }

            $row = [
                'updated_at' => now(),
            ];

            if ($has('period'))   $row['period']   = $period;
            if ($has('status'))   $row['status']   = 'paid';
            if ($has('provider')) $row['provider'] = $provider !== '' ? $provider : 'manual';
            if ($has('method'))   $row['method']   = $method !== '' ? $method : 'manual';

            if ($has('currency')) $row['currency'] = 'MXN';
            if ($has('paid_at'))  $row['paid_at']  = now();

            if ($has('amount_mxn')) $row['amount_mxn'] = $amountMxn;
            if ($has('amount'))     $row['amount']     = (int) round($amountMxn * 100);

            if ($has('concept')) {
                $row['concept'] = 'billing_statement';
            }

            if ($has('reference')) {
                $row['reference'] = $ref;
            }

            if ($has('meta')) {
                $row['meta'] = json_encode([
                    'type'   => 'billing_statement',
                    'period' => $period,
                    'source' => 'admin_statusAjax',
                    'note'   => 'Pago manual PAID por override pagado (SOT). No toca Stripe pending.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $idCol = $has('id') ? 'id' : $cols[0];

            DB::connection($this->adm)->transaction(function () use ($existing, $idCol, $row, $accountId) {

                if ($existing && isset($existing->{$idCol})) {
                    DB::connection($this->adm)->table('payments')->where($idCol, (int) $existing->{$idCol})->update($row);
                } else {
                    $row2 = $row;
                    $row2['created_at'] = now();
                    
                    // ✅ payments.account_id es NOT NULL en mysql_admin: siempre forzar account_id
                    $aid2 = (int)($row2['account_id'] ?? 0);
                    if ($aid2 <= 0) {
                        $aid2 = (int)$accountId;
                    }
                    if ($aid2 <= 0) {
                        throw new \RuntimeException('payments insert blocked: missing account_id (row2)');
                    }
                    $row2['account_id'] = $aid2;

                    DB::connection($this->adm)->table('payments')->insert($row2);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] upsertPaidPaymentForStatement failed', [
                'account_id' => $accountId,
                'period'     => $period,
                'err'        => $e->getMessage(),
            ]);
        }
    }


  private function insertManualPaidPaymentForStatement(string $accountId, string $period, float $amountMxn, string $method): void
    {
        // =========================================================
        // ✅ FIX GLOBAL (SOT):
        // Un "override pagado" NO debe crear movimientos financieros.
        // Eso genera "abonos fantasma" y rompe totales (PDF/UI).
        //
        // Los pagos reales deben registrarse SOLO en el flujo explícito
        // (p.ej. Hub manualPayment / conciliación / Stripe webhook).
        //
        // Si alguna vez se requiere reactivar el comportamiento,
        // se puede habilitar por ENV.
        // =========================================================
        $enabled = (bool) env('BILLING_AUTO_CREATE_PAYMENT_ON_OVERRIDE', false);
        if (!$enabled) {
            return;
        }

        // ---- (comportamiento legacy opcional) ----
        if (!Schema::connection($this->adm)->hasTable('payments')) {
            return;
        }

        $amountMxn = round(max(0.0, $amountMxn), 2);
        if ($amountMxn <= 0.00001) {
            return;
        }

        $cols = Schema::connection($this->adm)->getColumnListing('payments');
        $lc   = array_map('strtolower', $cols);
        $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

        if (!$has('account_id')) {
            return;
        }

        $payload = [];
        $payload['account_id'] = $accountId;

        if ($has('period')) {
            $payload['period'] = $period;
        }
        if ($has('status')) {
            $payload['status'] = 'paid';
        }
        if ($has('provider')) {
            $payload['provider'] = 'manual';
        }
        if ($has('method')) {
            $payload['method'] = $method !== '' ? $method : 'manual';
        }

        if ($has('amount_mxn')) {
            $payload['amount_mxn'] = $amountMxn;
        }
        if ($has('amount')) {
            $payload['amount'] = (int) round($amountMxn * 100);
        }

        if ($has('currency')) {
            $payload['currency'] = 'MXN';
        }
        if ($has('paid_at')) {
            $payload['paid_at'] = now();
        }
        if ($has('due_date')) {
            // SOT: due_date debe ser "límite de pago", no "ahorita"
            $payload['due_date'] = now()->addDays(4);
        }

        if ($has('concept')) {
            $payload['concept'] = 'Pago manual (admin) · Estado de cuenta ' . $period;
        }

        if ($has('reference')) {
            $payload['reference'] = 'admin_mark_paid:' . $accountId . ':' . $period . ':' . now()->format('YmdHis');
        }

        if ($has('meta')) {
            $payload['meta'] = json_encode([
                'type'   => 'billing_statement',
                'period' => $period,
                'source' => 'admin_statusAjax',
                'note'   => 'Pago manual para cerrar saldo por override pagado (LEGACY)',
            ], JSON_UNESCAPED_UNICODE);
        }

        $payload['updated_at'] = now();
        $payload['created_at'] = now();

         // ✅ payments.account_id es NOT NULL en mysql_admin: siempre forzar account_id
        $aid3 = (int)($payload['account_id'] ?? 0);
        if ($aid3 <= 0 && isset($accountId)) {
            $aid3 = (int)$accountId;
       }
        if ($aid3 <= 0) {
            throw new \RuntimeException('payments insert blocked: missing account_id (payload)');
        }
        $payload['account_id'] = $aid3;

        DB::connection($this->adm)->table('payments')->insert($payload);
    }

    /**
     * POST admin/billing/statements/status
     * status_override vacío => borra override (regresa a AUTO)
     */
    public function setStatusOverride(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id'      => 'required|string|max:64',
            'period'          => 'required|string',
            'status_override' => 'nullable|string|in:pendiente,parcial,pagado,vencido,sin_mov',
            'reason'          => 'nullable|string|max:255',
        ]);

        $accountId = (string) $data['account_id'];
        $period    = (string) $data['period'];

        abort_if(!$this->isValidPeriod($period), 422);

        if (!Schema::connection($this->adm)->hasTable($this->overrideTable())) {
            return back()->withErrors(['status' => 'No existe la tabla billing_statement_status_overrides (corre migraciones).']);
        }

        $status = strtolower(trim((string) ($data['status_override'] ?? '')));
        $reason = trim((string) ($data['reason'] ?? ''));
        $by     = auth('admin')->id();

        DB::connection($this->adm)->transaction(function () use ($accountId, $period, $status, $reason, $by) {
            $q = DB::connection($this->adm)->table($this->overrideTable())
                ->where('account_id', $accountId)
                ->where('period', $period);

            if ($status === '') {
                $q->delete();
                return;
            }

            $exists = $q->first(['id']);

            $payload = [
                'account_id'      => $accountId,
                'period'          => $period,
                'status_override' => $status,
                'reason'          => ($reason !== '' ? $reason : null),
                'updated_by'      => $by ? (int) $by : null,
                'updated_at'      => now(),
            ];

            if ($exists && isset($exists->id)) {
                DB::connection($this->adm)->table($this->overrideTable())
                    ->where('id', (int) $exists->id)
                    ->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::connection($this->adm)->table($this->overrideTable())->insert($payload);
            }
        });

        return back()->with('ok', $status === '' ? 'Override eliminado (AUTO).' : 'Estatus actualizado (override).');
    }
}
