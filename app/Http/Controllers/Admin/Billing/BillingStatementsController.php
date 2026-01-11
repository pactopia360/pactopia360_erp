<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\Billing\BillingStatementsController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Mail\Admin\Billing\StatementAccountPeriodMail;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Stripe\StripeClient;

// Ã¢Å“â€¦ QR local con bacon/bacon-qr-code v3
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\GdImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

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

    public function index(Request $req): View
    {
        $q = trim((string) $req->get('q', ''));
        $period = (string) $req->get('period', now()->format('Y-m'));
        if (!$this->isValidPeriod($period)) {
            $period = now()->format('Y-m');
        }

        $accountId = trim((string) $req->get('accountId', ''));
        $accountId = $accountId !== '' ? $accountId : null;

        // Ã¢Å“â€¦ perPage configurable
        $perPage = (int) $req->get('perPage', 25);
        $allowedPerPage = [25, 50, 100, 250, 500, 1000];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 25;
        }

        // Ã¢Å“â€¦ filtro estatus: all|pendiente|pagado|parcial|vencido|sin_mov
        $status = strtolower(trim((string) $req->get('status', 'all')));
        $allowedStatus = ['all', 'pendiente', 'pagado', 'parcial', 'vencido', 'sin_mov'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'all';
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

        // ====== Accounts columns (tolerante) ======
        $cols = Schema::connection($this->adm)->getColumnListing('accounts');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        $select = ['accounts.id', 'accounts.email'];

        // datos bÃƒÂ¡sicos
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

        // columnas tÃƒÂ­picas donde a veces se guarda "personalizado" fuera de meta
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

        if ($accountId) {
            $qb->where('accounts.id', $accountId);
        }

        if ($q !== '') {
            $qb->where(function ($w) use ($q, $has) {
                $w->where('accounts.id', 'like', "%{$q}%");
                if ($has('name'))         $w->orWhere('accounts.name', 'like', "%{$q}%");
                if ($has('razon_social')) $w->orWhere('accounts.razon_social', 'like', "%{$q}%");
                if ($has('rfc'))          $w->orWhere('accounts.rfc', 'like', "%{$q}%");
                $w->orWhere('accounts.email', 'like', "%{$q}%");
            });
        }

        $rows = $qb->paginate($perPage)->withQueryString();
        $ids  = $rows->pluck('id')->filter()->values()->all();

        // ====== movimientos reales por periodo (SUM cargo/abono) ======
        $agg = DB::connection($this->adm)->table('estados_cuenta')
            ->selectRaw('account_id as aid, SUM(COALESCE(cargo,0)) as cargo, SUM(COALESCE(abono,0)) as abono')
            ->whereIn('account_id', !empty($ids) ? $ids : ['__none__'])
            ->where('periodo', '=', $period)
            ->groupBy('account_id')
            ->get()
            ->keyBy('aid');

        // ====== pagos por periodo (SUM payments paid/succeeded/etc) ======
        $payAgg = $this->sumPaymentsForAccountsPeriod($ids, $period);

        // ====== pagos meta (ÃƒÂºltimo pago, due_date, mÃƒÂ©todo/provider/status) ======
        $payMeta = $this->fetchPaymentsMetaForAccountsPeriod($ids, $period);

        // ====== Transform ======
        $now = Carbon::now();

        $rows->getCollection()->transform(function ($r) use ($agg, $payAgg, $payMeta, $period, $now) {
            $a = $agg[$r->id] ?? null;

            $cargoEdo = (float) ($a->cargo ?? 0);
            $abonoEdo = (float) ($a->abono ?? 0);

            $paidPayments = (float) ($payAgg[(string) $r->id] ?? 0);

            // Ã¢Å“â€¦ abono total = abonos de estados_cuenta + pagos registrados (payments)
            $abonoTotal = $abonoEdo + $paidPayments;

            $r->cargo = round($cargoEdo, 2);
            $r->abono = round($abonoTotal, 2);
            $r->saldo = round(max(0, $cargoEdo - $abonoTotal), 2);

            $r->abono_edo = round($abonoEdo, 2);
            $r->abono_pay = round($paidPayments, 2);

            $pm = $payMeta[(string) $r->id] ?? [];
            $r->pay_last_paid_at = $pm['last_paid_at'] ?? null;
            $r->pay_due_date     = $pm['due_date'] ?? null;
            $r->pay_method       = $pm['method'] ?? null;
            $r->pay_provider     = $pm['provider'] ?? null;
            $r->pay_status       = $pm['status'] ?? null;

            $meta = $this->hub->decodeMeta($r->meta ?? null);

            $lastPaid = $this->resolveLastPaidPeriodForAccount((int) $r->id, $meta);

            $payAllowed = $lastPaid
                ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
                : $period;

            $customMxn = $this->extractCustomAmountMxn($r, $meta);

            if ($customMxn !== null && $customMxn > 0.00001) {
                $effectiveMxn = $customMxn;
                $tarifaLabel  = 'PERSONALIZADO';
                $tarifaPill   = 'info';
            } else {
                [$effectiveMxn, $tarifaLabel, $tarifaPill] = $this->safeResolveEffectiveAmountFromMeta(
                    $meta,
                    $period,
                    $payAllowed
                );
            }

            $r->expected_total = round((float) $effectiveMxn, 2);
            $r->tarifa_label   = (string) $tarifaLabel;
            $r->tarifa_pill    = (string) $tarifaPill;

            $totalShown = $r->cargo > 0 ? (float) $r->cargo : (float) $r->expected_total;
            $paidShown  = (float) $r->abono;
            $saldoShown = max(0, $totalShown - $paidShown);

            $statusPago = 'pendiente';

            if ($totalShown <= 0.00001) {
                $statusPago = 'sin_mov';
            } elseif ($saldoShown <= 0.00001) {
                $statusPago = 'pagado';
            } elseif ($paidShown > 0.00001 && $paidShown < ($totalShown - 0.00001)) {
                $statusPago = 'parcial';
            } else {
                $statusPago = 'pendiente';
            }

            if ($saldoShown > 0.00001 && $this->isOverdue($period, $r->pay_due_date ?? null, $now)) {
                $statusPago = 'vencido';
            }

            $r->status_pago = $statusPago;

            $r->last_paid   = $lastPaid;
            $r->pay_allowed = $payAllowed;

            $r->total_shown = round($totalShown, 2);
            $r->saldo_shown = round($saldoShown, 2);

            return $r;
        });

        // ====== Filtro status ======
        if ($status !== 'all') {
            $filtered = $rows->getCollection()
                ->filter(fn($x) => (string) ($x->status_pago ?? '') === $status)
                ->values();

            $rows->setCollection($filtered);
        }

        // ====== KPIs ======
        $kCargo = 0.0;
        $kAbono = 0.0;
        $kSaldo = 0.0;
        $kEdo   = 0.0;
        $kPay   = 0.0;

        foreach ($rows->getCollection() as $r) {
            $totalShown = ((float) ($r->cargo ?? 0)) > 0 ? (float) $r->cargo : (float) ($r->expected_total ?? 0);
            $paid = (float) ($r->abono ?? 0);

            $kCargo += $totalShown;
            $kAbono += $paid;
            $kSaldo += max(0, $totalShown - $paid);

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

        $cargoReal = (float) $items->sum('cargo');
        $abonoEdo  = (float) $items->sum('abono');

        $abonoPay = (float) $this->sumPaymentsForAccountPeriod($accountId, $period);
        $abono    = $abonoEdo + $abonoPay;

        $meta = $this->hub->decodeMeta($acc->meta ?? null);

        $lastPaid = $this->resolveLastPaidPeriodForAccount((int) $accountId, $meta);
        $payAllowed = $lastPaid
            ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
            : $period;

        $customMxn = $this->extractCustomAmountMxn($acc, $meta);

        if ($customMxn !== null && $customMxn > 0.00001) {
            $expected = $customMxn;
            $tarifaLabel = 'PERSONALIZADO';
            $tarifaPill  = 'info';
        } else {
            [$expected, $tarifaLabel, $tarifaPill] = $this->safeResolveEffectiveAmountFromMeta($meta, $period, $payAllowed);
        }

        $totalShown = $cargoReal > 0 ? $cargoReal : (float) $expected;
        $saldoShown = max(0, $totalShown - $abono);

        $statusPago = 'pendiente';
        if ($totalShown <= 0.00001) $statusPago = 'sin_mov';
        elseif ($saldoShown <= 0.00001) $statusPago = 'pagado';
        elseif ($abono > 0.00001 && $abono < ($totalShown - 0.00001)) $statusPago = 'parcial';
        else $statusPago = 'pendiente';

        // Ã¢Å“â€¦ Carga config guardada (modo/notas) si existe
        $stmtCfg = $this->getStatementConfigFromMeta($meta, $period);

        // Ã¢Å“â€¦ Destinatarios activos si existe tabla
        $recipients = $this->resolveRecipientsForAccount((string)$accountId, (string)($acc->email ?? ''));

        // Ã¢Å“â€¦ Aliases para UI nueva (sin romper legacy)
        $rows = $items; // alias
        $summary = [
            'cargo_real'     => round($cargoReal, 2),
            'expected_total' => round((float) $expected, 2),
            'abono'          => round($abono, 2),
            'abono_edo'      => round($abonoEdo, 2),
            'abono_pay'      => round($abonoPay, 2),
            'total'          => round($totalShown, 2),
            'saldo'          => round($saldoShown, 2),
            'status_pago'    => $statusPago,
            'last_paid'      => $lastPaid,
            'pay_allowed'    => $payAllowed,
            'tarifa_label'   => (string) $tarifaLabel,
            'tarifa_pill'    => (string) $tarifaPill,
        ];

        return view('admin.billing.statements.show', [
            'account'        => $acc,
            'period'         => $period,
            'period_label'   => Str::title(Carbon::parse($period . '-01')->translatedFormat('F Y')),
            'items'          => $items,

            'cargo_real'     => round($cargoReal, 2),
            'expected_total' => round((float) $expected, 2),
            'tarifa_label'   => (string) $tarifaLabel,
            'tarifa_pill'    => (string) $tarifaPill,

            'abono'          => round($abono, 2),
            'abono_edo'      => round($abonoEdo, 2),
            'abono_pay'      => round($abonoPay, 2),

            'total'          => round($totalShown, 2),
            'saldo'          => round($saldoShown, 2),

            'last_paid'      => $lastPaid,
            'pay_allowed'    => $payAllowed,

            'status_pago'    => $statusPago,

            // Ã¢Å“â€¦ nuevo
            'statement_cfg'  => $stmtCfg,
            'recipients'     => $recipients,

            // Ã¢Å“â€¦ FIX: asegurar $meta para la vista legacy/nueva
            'meta'           => $meta,

            'isModal'        => $req->boolean('modal'),
        ]);
    }

    public function addItem(Request $req, string $accountId, string $period): RedirectResponse
    {
        abort_if(!$this->isValidPeriod($period), 422);

        // Ã¢Å“â€¦ soporta legacy (cargo/abono) y UI nueva (tipo/monto)
        $data = $req->validate([
            'concepto'    => 'required|string|max:255',
            'detalle'     => 'nullable|string|max:2000',

            // legacy
            'cargo'       => 'nullable|numeric|min:0|max:99999999',
            'abono'       => 'nullable|numeric|min:0|max:99999999',

            // UI nueva
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
     * Ã¢Å“â€¦ NUEVO: CRUD de lÃƒÂ­neas (para tu UI nueva)
     * POST /admin/billing/statements/lines
     */
    public function lineStore(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => 'required|string',
            'concepto'   => 'required|string|max:255',
            'detalle'    => 'nullable|string|max:2000',

            // legacy
            'cargo'      => 'nullable|numeric|min:0|max:99999999',
            'abono'      => 'nullable|numeric|min:0|max:99999999',

            // UI nueva
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

        return back()->with('ok', 'LÃƒÂ­nea agregada.');
    }

    /**
     * Ã¢Å“â€¦ NUEVO: CRUD de lÃƒÂ­neas
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

            // legacy
            'cargo'      => 'nullable|numeric|min:0|max:99999999',
            'abono'      => 'nullable|numeric|min:0|max:99999999',

            // UI nueva
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

            // Blindaje: evita editar otra cuenta/periodo por error
            $q->where('account_id', $accountId)->where('periodo', $period);

            $updated = $q->update([
                'concepto'   => $data['concepto'],
                'detalle'    => $data['detalle'] ?? null,
                'cargo'      => round($cargo, 2),
                'abono'      => round($abono, 2),
                'updated_at' => now(),
            ]);

            if (!$updated) {
                throw new \RuntimeException('No se encontrÃƒÂ³ la lÃƒÂ­nea para actualizar (id/account/period).');
            }

            $this->recalcStatementSaldoIfPossible($accountId, $period);
        });

        return back()->with('ok', 'LÃƒÂ­nea actualizada.');
    }

    /**
     * Ã¢Å“â€¦ NUEVO: CRUD de lÃƒÂ­neas
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
                throw new \RuntimeException('No se encontrÃƒÂ³ la lÃƒÂ­nea para eliminar (id/account/period).');
            }

            $this->recalcStatementSaldoIfPossible($accountId, $period);
        });

        return back()->with('ok', 'LÃƒÂ­nea eliminada.');
    }

    /**
     * Ã¢Å“â€¦ NUEVO: Guardar configuraciÃƒÂ³n del estado (modo ÃƒÂºnica/mensual, notas) y destinatarios.
     * POST /admin/billing/statements/save
     */
    public function saveStatement(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'account_id' => 'required|string|max:64',
            'period'     => 'required|string',

            // Config (acepta ambos dialectos)
            'mode'       => 'nullable|string|in:unique,monthly,unica,mensual',
            'notes'      => 'nullable|string|max:4000',

            // Destinatarios (acepta ambos)
            'recipients' => 'nullable|string|max:8000', // "a@a.com,b@b.com"
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
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        $modeRaw = (string) ($data['mode'] ?? 'monthly');
        $mode    = $this->normalizeStatementMode($modeRaw);

        $notes = trim((string) ($data['notes'] ?? ''));

        // 1) Guardar config en meta (best-effort)
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
                'mode'       => $mode, // unique|monthly (canÃƒÂ³nico)
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

        // 2) Guardar destinatarios en account_recipients (si existe)
        $rawRecipients = trim((string) ($data['recipients'] ?? ''));
        if ($rawRecipients === '') {
            $rawRecipients = trim((string) ($data['to'] ?? ''));
        }

        if (Schema::connection($this->adm)->hasTable('account_recipients')) {
            // Si viene vacÃƒÂ­o, NO tocamos recipients (para no desactivar todo por accidente)
            if ($rawRecipients !== '') {
                $emails = $this->normalizeRecipientList($rawRecipients);

                DB::connection($this->adm)->transaction(function () use ($accountId, $emails) {
                    // Desactiva todos primero (soft)
                    DB::connection($this->adm)->table('account_recipients')
                        ->where('account_id', $accountId)
                        ->update([
                            'is_active'  => 0,
                            'updated_at' => now(),
                        ]);

                    // Activa/Upsert los enviados
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

    public function pdf(Request $req, string $accountId, string $period)
    {
        abort_if(!$this->isValidPeriod($period), 422);

        $data = $this->buildStatementData($accountId, $period);
        $data['isModal'] = $req->boolean('modal');

        $viewName = 'cliente.billing.pdf.statement';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::setOptions([
                'isRemoteEnabled'       => true,
                'isHtml5ParserEnabled'  => true,
                'defaultFont'           => 'DejaVu Sans',
                'dpi'                   => 96,
            ])->loadView($viewName, $data);

            $name = 'EstadoCuenta_' . $accountId . '_' . $period . '.pdf';
            return $pdf->download($name);
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

        if (!$acc) return back()->withErrors(['account' => 'Cuenta no encontrada.']);

        $this->sendStatementEmailWithPayLink($accountId, $period, $toRaw !== '' ? $toRaw : null);

        return back()->with('ok', 'Estado de cuenta enviado por correo (a destinatarios configurados).');
    }

    /**
     * Ã¢Å“â€¦ NUEVO: envÃƒÂ­o masivo por periodo
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
            return back()->withErrors(['period' => 'Periodo invÃƒÂ¡lido. Formato YYYY-MM.']);
        }

        abort_unless(Schema::connection($this->adm)->hasTable('accounts'), 404);

        $status = (string) ($data['status'] ?? 'all');

        $ids = collect($data['account_ids'] ?? [])
            ->filter(fn($x) => is_string($x) && trim($x) !== '')
            ->map(fn($x) => trim((string)$x))
            ->values()
            ->all();

        $q = DB::connection($this->adm)->table('accounts')->select('id');

        if (!empty($ids)) {
            $q->whereIn('id', $ids);
        }

        $accounts = $q->get()->pluck('id')->map(fn($x) => (string)$x)->values();

        $ok = 0;
        $fail = 0;

        foreach ($accounts as $aid) {
            try {
                if ($status !== 'all') {
                    $d = $this->buildStatementData((string)$aid, $period);
                    $cargo = (float)($d['cargo'] ?? 0);
                    $abono = (float)($d['abono'] ?? 0);
                    $saldo = (float)($d['saldo'] ?? 0);

                    $computed = 'pendiente';
                    if ($cargo <= 0.00001) $computed = 'sin_mov';
                    elseif ($saldo <= 0.00001) $computed = 'pagado';
                    elseif ($abono > 0.00001 && $saldo > 0.00001) $computed = 'parcial';
                    else $computed = 'pendiente';

                    if ($computed !== $status) {
                        continue;
                    }
                }

                $this->sendStatementEmailWithPayLink((string)$aid, $period, null);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                Log::warning('[ADMIN][STATEMENTS][BULK] fallo', [
                    'account_id' => (string)$aid,
                    'period' => $period,
                    'err' => $e->getMessage(),
                ]);
            }

            usleep(180000);
        }

        if ($req->expectsJson()) {
            return response()->json(['ok' => true, 'sent' => $ok, 'failed' => $fail]);
        }

        return back()->with('ok', "EnvÃƒÂ­o masivo disparado. Enviados: {$ok}. Fallidos: {$fail}.");
    }

    /**
     * ✅ Envío con PayLink/QR
     */
    private function sendStatementEmailWithPayLink(string $accountId, string $period, ?string $to = null): void
    {
        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        if (!$acc) {
            return;
        }

        // 1) Destinatarios: primero los que vengan por request, si no, los configurados
        $recipients = $this->normalizeRecipientList($to);

        if (empty($recipients)) {
            $recipients = $this->resolveRecipientsForAccount((string) $accountId, (string) ($acc->email ?? ''));
        }

        if (empty($recipients)) {
            return;
        }

        // 2) Armar data del estado de cuenta
        $data = $this->buildStatementData($accountId, $period);

        // PDF público inline (firmado)
        try {
            $data['pdf_url'] = URL::signedRoute('cliente.billing.publicPdfInline', [
                'accountId' => $accountId,
                'period'    => $period,
            ]);
        } catch (\Throwable $e) {
            $data['pdf_url'] = null;
        }

        // Portal cliente
        try {
            $data['portal_url'] = route('cliente.estado_cuenta') . '?period=' . urlencode($period);
        } catch (\Throwable $e) {
            $data['portal_url'] = null;
        }

        $totalPesos = (float) ($data['total'] ?? 0);

        $payUrl     = null;
        $sessionId  = null;

        // 3) Crear Checkout en Stripe SOLO si hay saldo a pagar
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

        // 4) QR/Pay URL
        if (is_string($payUrl) && $payUrl !== '') {
            $data['pay_url'] = $payUrl;

            [$qrDataUri, $qrUrl] = $this->makeQrDataForText($payUrl);
            $data['qr_data_uri'] = $qrDataUri;
            $data['qr_url']      = $qrUrl;
        }

        $data['stripe_session_id'] = $sessionId;

        // 5) Envío
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

    /**
     * Ã¢Å“â€¦ Destinatarios por cuenta:
     * - account_recipients (activos) + accounts.email
     *
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
                    $e = strtolower(trim((string)($r->email ?? '')));
                    if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $e;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[ADMIN][STATEMENTS] resolveRecipientsForAccount failed', [
                    'account_id' => $accountId,
                    'err' => $e->getMessage(),
                ]);
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Convierte "a@a.com,b@b.com; c@c.com" => array
     * @return array<int,string>
     */
    private function normalizeRecipientList(?string $to): array
    {
        $to = trim((string)$to);
        if ($to === '') return [];

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

    private function createStripeCheckoutForStatement(object $acc, string $period, float $totalPesos): array
    {
        $secret = (string) config('services.stripe.secret');
        if (trim($secret) === '') {
            throw new \RuntimeException('Stripe secret vacÃƒÂ­o en config(services.stripe.secret).');
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
        if ($has('amount_mxn')) $row['amount_mxn'] = round($uiTotalPesos, 2);

        if ($has('currency'))   $row['currency']   = 'MXN';
        if ($has('status'))     $row['status']     = 'pending';
        if ($has('due_date'))   $row['due_date']   = now();

        if ($has('period'))     $row['period']     = $period;
        if ($has('method'))     $row['method']     = 'card';
        if ($has('provider'))   $row['provider']   = 'stripe';
        if ($has('concept'))    $row['concept']    = 'Pactopia360 Ã‚Â· Estado de cuenta ' . $period;
        if ($has('reference'))  $row['reference']  = $sessionId ?: ('admin_stmt:' . $accountId . ':' . $period);

        if ($has('stripe_session_id')) $row['stripe_session_id'] = $sessionId;

        if ($has('meta')) {
            $row['meta'] = json_encode([
                'type'           => 'billing_statement',
                'period'         => $period,
                'ui_total_pesos' => round($uiTotalPesos, 2),
                'source'         => 'admin_email',
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

    private function buildStatementData(string $accountId, string $period): array
    {
        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();

        $items = DB::connection($this->adm)->table('estados_cuenta')
            ->where('account_id', $accountId)
            ->where('periodo', '=', $period)
            ->orderBy('id')
            ->get();

        $cargoReal = (float) $items->sum('cargo');
        $abonoEdo  = (float) $items->sum('abono');

        $abonoPay = (float) $this->sumPaymentsForAccountPeriod($accountId, $period);
        $abono    = $abonoEdo + $abonoPay;

        $meta = $this->hub->decodeMeta($acc->meta ?? null);

        $lastPaid = $this->resolveLastPaidPeriodForAccount((int) $accountId, $meta);
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

        // =====================
        // CONSUMOS (Servicio base + extras)
        // - Respeta modo mensual/anual
        // - NO hardcodea "Servicio mensual"
        // - Busca servicio mensual/anual/licencia/suscripciÃƒÂ³n en items
        // - Si no existe item servicio, inyecta servicio usando tarifa esperada (expectedTotal)
        // =====================
        $consumos = [];

        // Resolver modo (mensual/anual) desde meta o plan
        $mode = strtolower(trim((string) (
            data_get($meta, 'billing.mode')
            ?? data_get($meta, 'billing.modo')
            ?? ''
        )));

        $planStr = strtolower(trim((string) ($acc->plan_actual ?? $acc->plan ?? '')));

        // NormalizaciÃƒÂ³n del modo
        if (!in_array($mode, ['mensual', 'anual'], true)) {
            if (str_contains($planStr, 'anual') || str_contains($planStr, 'annual')) $mode = 'anual';
            if (str_contains($planStr, 'mensual') || str_contains($planStr, 'monthly')) $mode = 'mensual';
        }
        if (!in_array($mode, ['mensual', 'anual'], true)) $mode = 'mensual';

        // Etiqueta correcta para el servicio
        $serviceLabelDefault = ($mode === 'anual') ? 'Servicio anual' : 'Servicio mensual';

        // Si el modo es anual, intentar tomar un monto anual explÃƒÂ­cito desde meta
        $serviceExpected = (float) $expectedTotal;

        if ($mode === 'anual') {
            $annualCandidates = [
                data_get($meta, 'billing.annual_amount_mxn'),
                data_get($meta, 'billing.anual_amount_mxn'),
                data_get($meta, 'billing.amount_mxn_annual'),
                data_get($meta, 'billing.amount_anual_mxn'),
                data_get($meta, 'billing.year_amount_mxn'),
                data_get($meta, 'billing.override.annual_amount_mxn'),
                data_get($meta, 'billing.override.amount_mxn_annual'),
            ];

            foreach ($annualCandidates as $v) {
                $n = $this->toFloat($v);
                if ($n !== null && $n > 0.00001) {
                    $serviceExpected = (float) $n;
                    break;
                }
            }
        }

        // 1) Detectar si ya existe un item de servicio (mensual/anual/licencia/suscripciÃƒÂ³n)
        $serviceCandidates = [];
        foreach ($items as $it) {
            $concepto = (string) ($it->concepto ?? '');
            $cargoIt  = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
            if ($cargoIt <= 0) continue;

            $c = mb_strtolower(trim($concepto));
            if (
                str_contains($c, 'servicio mensual') ||
                str_contains($c, 'servicio anual')   ||
                str_contains($c, 'licencia')         ||
                str_contains($c, 'suscrip')          ||
                str_contains($c, 'membres')
            ) {
                $serviceCandidates[] = $it;
            }
        }

        $serviceItem = !empty($serviceCandidates) ? $serviceCandidates[0] : null;

        // 2) Subtotal servicio (si hay item, usa su cargo; si no, usa expected Ã¢â‚¬Å“correctoÃ¢â‚¬Â)
        $serviceSubtotal = 0.0;
        if ($serviceItem) {
            $serviceSubtotal = is_numeric($serviceItem->cargo ?? null) ? (float) $serviceItem->cargo : 0.0;
        } else {
            $serviceSubtotal = (float) $serviceExpected;
        }

        // 3) Nombre del servicio
        $serviceName = $serviceLabelDefault;
        if ($serviceItem) {
            $svcConcept = trim((string) ($serviceItem->concepto ?? ''));
            if ($svcConcept !== '') $serviceName = $svcConcept;
        }

        // 4) Agregar SIEMPRE el servicio
        $consumos[] = [
            'service'   => $serviceName,
            'unit_cost' => round($serviceSubtotal, 2),
            'qty'       => 1,
            'subtotal'  => round($serviceSubtotal, 2),
        ];

        $extrasSum = 0.0;

        foreach ($items as $it) {
            $cargoIt = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
            if ($cargoIt <= 0) continue;

            // Ã¢Å“â€¦ si detectamos item de servicio, no lo duplicamos como extra
            if ($serviceItem && isset($it->id) && isset($serviceItem->id) && (string)$it->id === (string)$serviceItem->id) {
                continue;
            }

            $concepto = trim((string) ($it->concepto ?? ''));
            if ($concepto === '') $concepto = 'Cargo';

            $consumos[] = [
                'service'   => $concepto,
                'unit_cost' => round($cargoIt, 2),
                'qty'       => 1,
                'subtotal'  => round($cargoIt, 2),
            ];

            $extrasSum += $cargoIt;
        }

        $totalConsumos = (float) $serviceSubtotal + (float) $extrasSum;

        $cargoShown = round($totalConsumos, 2);
        $saldo = round(max(0, $cargoShown - (float) $abono), 2);

        $qrText = $this->resolveQrTextForStatement($accountId, $period, null);
        [$qrDataUri, $qrUrl] = $this->makeQrDataForText((string) ($qrText ?? ''));

        return [
            'account'        => $acc,
            'account_id'     => $accountId,
            'period'         => $period,
            'period_label'   => Str::title(Carbon::parse($period . '-01')->translatedFormat('F Y')),

            'items'          => $items,

            'consumos'       => $consumos,
            'consumos_total' => round($totalConsumos, 2),

            'cargo_real'     => round($cargoReal, 2),
            'expected_total' => round((float) $expectedTotal, 2),
            'tarifa_label'   => (string) $tarifaLabel,
            'tarifa_pill'    => (string) $tarifaPill,

            'cargo'          => $cargoShown,
            'abono'          => round((float) $abono, 2),
            'abono_edo'      => round((float) $abonoEdo, 2),
            'abono_pay'      => round((float) $abonoPay, 2),
            'saldo'          => $saldo,

            // Ã¢Å“â€¦ Para email/checkout (tu flujo usa total como "lo a pagar")
            'total_due'      => $saldo,
            'total'          => $saldo,

            'generated_at'   => now(),

            'last_paid'      => $lastPaid,
            'pay_allowed'    => $payAllowed,

            'pay_url'        => $qrText,
            'qr_data_uri'    => $qrDataUri,
            'qr_url'         => $qrUrl,
        ];
    }

    // =========================================================
    // Ã¢Å“â€¦ HELPERS internos para lÃƒÂ­neas/config
    // =========================================================

    private function normalizeStatementMode(string $modeRaw): string
    {
        $m = strtolower(trim($modeRaw));

        if (in_array($m, ['mensual', 'monthly'], true)) return 'monthly';
        if (in_array($m, ['unica', 'ÃƒÂºnica', 'unique'], true)) return 'unique';

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

        // UI nueva (tiene prioridad si estÃƒÂ¡ presente)
        $tipo  = isset($data['tipo']) ? strtolower(trim((string)$data['tipo'])) : '';
        $monto = isset($data['monto']) && is_numeric($data['monto']) ? (float)$data['monto'] : 0.0;

        if ($tipo !== '' && $monto > 0) {
            if ($tipo === 'cargo') { $cargo = $monto; $abono = 0.0; }
            if ($tipo === 'abono') { $abono = $monto; $cargo = 0.0; }
        }

        return [round(max(0.0, $cargo), 2), round(max(0.0, $abono), 2)];
    }

    private function recalcStatementSaldoIfPossible(string $accountId, string $period): void
    {
        try {
            if (!Schema::connection($this->adm)->hasTable('estados_cuenta')) return;

            $cols = Schema::connection($this->adm)->getColumnListing('estados_cuenta');
            $lc   = array_map('strtolower', $cols);
            $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

            // Si no existe columna saldo, no forzamos nada
            if (!$has('saldo') || !$has('id')) return;

            $items = DB::connection($this->adm)->table('estados_cuenta')
                ->where('account_id', $accountId)
                ->where('periodo', $period)
                ->orderBy('id')
                ->get(['id', 'cargo', 'abono']);

            $saldo = 0.0;

            foreach ($items as $it) {
                $cargo = is_numeric($it->cargo ?? null) ? (float)$it->cargo : 0.0;
                $abono = is_numeric($it->abono ?? null) ? (float)$it->abono : 0.0;

                // Saldo acumulado estilo cuenta corriente
                $saldo = max(0.0, $saldo + $cargo - $abono);

                DB::connection($this->adm)->table('estados_cuenta')
                    ->where('id', (int)$it->id)
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

    private function getStatementConfigFromMeta(array $meta, string $period): array
    {
        $cfg = data_get($meta, 'billing.statements.' . $period);
        if (!is_array($cfg)) $cfg = [];

        $mode = (string)($cfg['mode'] ?? 'monthly');
        $mode = $this->normalizeStatementMode($mode);

        return [
            'mode'       => $mode,
            'notes'      => (string)($cfg['notes'] ?? ''),
            'updated_at' => $cfg['updated_at'] ?? null,
            'by'         => $cfg['by'] ?? null,
        ];
    }

    // =========================================================
    // QR (dompdf-safe)
    // =========================================================

    private function makeQrDataForText(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [null, null];

        try {
            if (class_exists(Writer::class) && class_exists(GdImageBackEnd::class)) {
                $renderer = new ImageRenderer(
                    new RendererStyle(260),
                    new GdImageBackEnd()
                );

                $writer = new Writer($renderer);
                $png = $writer->writeString($text);

                if (is_string($png) && $png !== '') {
                    return ['data:image/png;base64,' . base64_encode($png), null];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ADMIN][STATEMENTS] QR local failed (bacon)', [
                'err' => $e->getMessage(),
            ]);
        }

        $qrUrl = 'https://quickchart.io/qr?text=' . urlencode($text) . '&size=260&margin=1&format=png';
        return [null, $qrUrl];
    }

    private function resolveQrTextForStatement(string $accountId, string $period, ?string $payUrl = null): ?string
    {
        if (is_string($payUrl) && trim($payUrl) !== '') return trim($payUrl);

        try {
            if (Route::has('cliente.billing.publicPay')) {
                return URL::signedRoute('cliente.billing.publicPay', [
                    'accountId' => $accountId,
                    'period'    => $period,
                ]);
            }
        } catch (\Throwable $e) {}

        try {
            if (Route::has('cliente.estado_cuenta')) {
                return route('cliente.estado_cuenta') . '?period=' . urlencode($period);
            }
        } catch (\Throwable $e) {}

        return null;
    }

    // =========================================================
    // PAYMENTS: sumar pagos "pagados" desde tabla payments
    // =========================================================

    private function sumPaymentsForAccountPeriod(string $accountId, string $period): float
    {
        if (!Schema::connection($this->adm)->hasTable('payments')) return 0.0;

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

            if (!$has('account_id')) return 0.0;

            $amountMxnCol = $has('amount_mxn') ? 'amount_mxn' : null;
            $amountCol    = $has('amount') ? 'amount' : null;

            if (!$amountMxnCol && !$amountCol) return 0.0;

            $q = DB::connection($this->adm)->table('payments')
                ->where('account_id', $accountId);

            if ($has('period')) {
                $q->where('period', $period);
            }

            if ($has('status')) {
                $q->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized']);
            }

            if ($amountMxnCol) {
                $sum = (float) ($q->sum($amountMxnCol) ?? 0);
                return round($sum, 2);
            }

            $cents = (float) ($q->sum($amountCol) ?? 0);
            if ($cents <= 0) return 0.0;

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

    private function sumPaymentsForAccountsPeriod(array $accountIds, string $period): array
    {
        $out = [];
        if (empty($accountIds)) return $out;
        if (!Schema::connection($this->adm)->hasTable('payments')) return $out;

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

            if (!$has('account_id')) return $out;

            $amountMxnCol = $has('amount_mxn') ? 'amount_mxn' : null;
            $amountCol    = $has('amount') ? 'amount' : null;

            if (!$amountMxnCol && !$amountCol) return $out;

            $q = DB::connection($this->adm)->table('payments')
                ->whereIn('account_id', $accountIds);

            if ($has('period')) {
                $q->where('period', $period);
            }

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

    private function fetchPaymentsMetaForAccountsPeriod(array $accountIds, string $period): array
    {
        $out = [];
        if (empty($accountIds)) return $out;
        if (!Schema::connection($this->adm)->hasTable('payments')) return $out;

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

            if (!$has('account_id')) return $out;

            $q = DB::connection($this->adm)->table('payments')
                ->whereIn('account_id', $accountIds);

            if ($has('period')) {
                $q->where('period', $period);
            }

            $select = ['account_id'];
            foreach (['status', 'method', 'provider', 'due_date', 'paid_at', 'created_at', 'updated_at'] as $c) {
                if ($has($c)) $select[] = $c;
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
                if ($aid === '' || isset($out[$aid])) continue;

                $lastPaidAt = null;
                if ($has('paid_at') && !empty($r->paid_at)) $lastPaidAt = $r->paid_at;
                elseif ($has('updated_at') && !empty($r->updated_at)) $lastPaidAt = $r->updated_at;
                elseif ($has('created_at') && !empty($r->created_at)) $lastPaidAt = $r->created_at;

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
        try {
            if ($dueDate instanceof \DateTimeInterface) {
                return Carbon::instance($dueDate)->startOfDay()->lt($now->startOfDay());
            }

            if (is_string($dueDate) && trim($dueDate) !== '') {
                $d = Carbon::parse($dueDate)->startOfDay();
                return $d->lt($now->startOfDay());
            }

            if ($this->isValidPeriod($period)) {
                $p = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
                $cur = $now->copy()->startOfMonth();
                return $p->lt($cur);
            }
        } catch (\Throwable $e) {}

        return false;
    }

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
            if ($n !== null && $n > 0.00001) return $n;
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
                if ($n !== null && $n > 0.00001) return $n;
            }
        }

        return null;
    }

    private function toFloat(mixed $v): ?float
    {
        if ($v === null) return null;
        if (is_float($v) || is_int($v)) return (float) $v;

        if (is_string($v)) {
            $s = trim($v);
            if ($s === '') return null;

            $s = str_replace(['$', ',', ' '], '', $s);
            if (!is_numeric($s)) return null;

            return (float) $s;
        }

        if (is_numeric($v)) return (float) $v;

        return null;
    }

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



    private function resolveLastPaidPeriodForAccount(int $accountId, array $meta): ?string
    {
        $key = (string) $accountId;
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
        } catch (\Throwable $e) {}

        if (!$lastPaid && Schema::connection($this->adm)->hasTable('payments')) {
            try {
                $cols = Schema::connection($this->adm)->getColumnListing('payments');
                $lc   = array_map('strtolower', $cols);
                $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

                if ($has('account_id') && $has('status') && $has('period')) {
                    $q = DB::connection($this->adm)->table('payments')
                        ->where('account_id', $accountId)
                        ->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized']);

                    $row = $q->orderByDesc(
                        $has('paid_at') ? 'paid_at' : ($has('created_at') ? 'created_at' : ($has('id') ? 'id' : $cols[0]))
                    )->first(['period']);

                    if ($row && !empty($row->period) && $this->isValidPeriod((string) $row->period)) {
                        $lastPaid = (string) $row->period;
                    }
                }
            } catch (\Throwable $e) {}
        }

        if (!$lastPaid) {
            try {
                $items = DB::connection($this->adm)->table('estados_cuenta')
                    ->where('account_id', $accountId)
                    ->orderByDesc('periodo')
                    ->limit(48)
                    ->get(['periodo', 'cargo', 'abono', 'saldo']);

                foreach ($items as $it) {
                    $p = $this->parseToPeriod($it->periodo ?? null);
                    if (!$p) continue;

                    $cargo = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
                    $abono = is_numeric($it->abono ?? null) ? (float) $it->abono : 0.0;
                    $saldo = is_numeric($it->saldo ?? null) ? (float) $it->saldo : max(0.0, $cargo - $abono);

                    if ($saldo <= 0.0001 || ($cargo > 0 && $abono >= $cargo)) {
                        $lastPaid = $p;
                        break;
                    }
                }
            } catch (\Throwable $e) {}
        }

        $this->cacheLastPaid[$key] = $lastPaid;
        return $lastPaid;
    }

    private function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period);
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
                if ($this->isValidPeriod($v)) return $v;

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
}
