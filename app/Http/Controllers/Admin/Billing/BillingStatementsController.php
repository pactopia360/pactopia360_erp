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

use Barryvdh\DomPDF\Facade\Pdf;

// ✅ QR local con bacon/bacon-qr-code v3 (ya lo tienes en tu proyecto)
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
        if (!$this->isValidPeriod($period)) $period = now()->format('Y-m');

        $accountId = trim((string) $req->get('accountId', ''));
        $accountId = $accountId !== '' ? $accountId : null;

        // ✅ perPage configurable
        $perPage = (int) $req->get('perPage', 25);
        $allowedPerPage = [25, 50, 100, 250, 500, 1000];
        if (!in_array($perPage, $allowedPerPage, true)) $perPage = 25;

        // ✅ filtro estatus: all|pendiente|pagado|parcial|vencido|sin_mov
        $status = strtolower(trim((string) $req->get('status', 'all')));
        $allowedStatus = ['all', 'pendiente', 'pagado', 'parcial', 'vencido', 'sin_mov'];
        if (!in_array($status, $allowedStatus, true)) $status = 'all';

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

        // datos básicos
        foreach ([
            'name', 'razon_social', 'rfc',
            'plan', 'plan_actual', 'modo_cobro', 'billing_cycle',
            'is_blocked', 'estado_cuenta',
            'meta',
            'created_at',
        ] as $c) {
            if ($has($c)) $select[] = "accounts.$c";
        }

        // columnas típicas donde a veces se guarda "personalizado" fuera de meta
        foreach ([
            'billing_amount_mxn', 'amount_mxn', 'precio_mxn', 'monto_mxn',
            'override_amount_mxn', 'custom_amount_mxn', 'license_amount_mxn',
            'billing_amount', 'amount', 'precio', 'monto',
        ] as $c) {
            if ($has($c)) $select[] = "accounts.$c";
        }

        $qb = DB::connection($this->adm)->table('accounts')
            ->select($select)
            ->orderByDesc($has('created_at') ? 'accounts.created_at' : 'accounts.id');

        if ($accountId) $qb->where('accounts.id', $accountId);

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
        $payAgg = $this->sumPaymentsForAccountsPeriod($ids, $period); // [account_id => mxn]

        // ====== pagos meta (último pago, due_date, método/provider/status) ======
        $payMeta = $this->fetchPaymentsMetaForAccountsPeriod($ids, $period); // [aid => array]

        // ====== Transform: expected_total + labels + pagos + status avanzados ======
        $now = Carbon::now();

        $rows->getCollection()->transform(function ($r) use ($agg, $payAgg, $payMeta, $period, $now) {
            $a = $agg[$r->id] ?? null;

            $cargoEdo = (float) ($a->cargo ?? 0);
            $abonoEdo = (float) ($a->abono ?? 0);

            $paidPayments = (float) ($payAgg[(string) $r->id] ?? 0);

            // ✅ abono total = abonos de estados_cuenta + pagos registrados (payments)
            $abonoTotal = $abonoEdo + $paidPayments;

            $r->cargo = round($cargoEdo, 2);
            $r->abono = round($abonoTotal, 2);
            $r->saldo = round(max(0, $cargoEdo - $abonoTotal), 2);

            // extra para UI/debug
            $r->abono_edo = round($abonoEdo, 2);
            $r->abono_pay = round($paidPayments, 2);

            // payments meta (si existe)
            $pm = $payMeta[(string) $r->id] ?? [];
            $r->pay_last_paid_at = $pm['last_paid_at'] ?? null;
            $r->pay_due_date     = $pm['due_date'] ?? null;
            $r->pay_method       = $pm['method'] ?? null;
            $r->pay_provider     = $pm['provider'] ?? null;
            $r->pay_status       = $pm['status'] ?? null;

            $meta = $this->hub->decodeMeta($r->meta ?? null);

            // lastPaid robusto
            $lastPaid = $this->resolveLastPaidPeriodForAccount((int) $r->id, $meta);

            $payAllowed = $lastPaid
                ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
                : $period;

            // Detecta monto personalizado
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

            // Total mostrado: cargo real si existe; si no, esperado (licencia)
            $totalShown = $r->cargo > 0 ? (float) $r->cargo : (float) $r->expected_total;
            $paidShown  = (float) $r->abono;
            $saldoShown = max(0, $totalShown - $paidShown);

            // ✅ status avanzado
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

            // ✅ vencido (si aplica)
            if ($saldoShown > 0.00001 && $this->isOverdue($period, $r->pay_due_date ?? null, $now)) {
                $statusPago = 'vencido';
            }

            $r->status_pago = $statusPago;

            $r->last_paid   = $lastPaid;
            $r->pay_allowed = $payAllowed;

            // para UI
            $r->total_shown = round($totalShown, 2);
            $r->saldo_shown = round($saldoShown, 2);

            return $r;
        });

        // ====== Filtro status (opcional) ======
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

        // ✅ sumar pagos desde payments
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

            'isModal'        => $req->boolean('modal'),
        ]);
    }

    public function addItem(Request $req, string $accountId, string $period): RedirectResponse
    {
        abort_if(!$this->isValidPeriod($period), 422);

        $data = $req->validate([
            'concepto'    => 'required|string|max:255',
            'detalle'     => 'nullable|string|max:2000',
            'cargo'       => 'nullable|numeric|min:0|max:99999999',
            'abono'       => 'nullable|numeric|min:0|max:99999999',
            'send_email'  => 'nullable|boolean',

            // ✅ Ahora permitimos string con múltiples destinatarios: a@x.com,b@y.com
            'to'          => 'nullable|string|max:2000',
        ]);

        $cargo = (float) ($data['cargo'] ?? 0);
        $abono = (float) ($data['abono'] ?? 0);

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

            try {
                $cols = Schema::connection($this->adm)->getColumnListing('estados_cuenta');
                $lc   = array_map('strtolower', $cols);
                $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

                $items = DB::connection($this->adm)->table('estados_cuenta')
                    ->where('account_id', $accountId)
                    ->where('periodo', '=', $period)
                    ->orderByDesc($has('id') ? 'id' : $cols[0])
                    ->get();

                $saldo  = max(0, (float) $items->sum('cargo') - (float) $items->sum('abono'));
                $lastId = (int) ($items->first()->id ?? 0);

                if ($has('saldo') && $has('id') && $lastId > 0) {
                    DB::connection($this->adm)->table('estados_cuenta')->where('id', $lastId)->update([
                        'saldo' => round($saldo, 2),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // silencioso
            }
        });

        if (($data['send_email'] ?? false) === true) {
            $to = trim((string) ($data['to'] ?? ''));
            $this->sendStatementEmailWithPayLink($accountId, $period, $to !== '' ? $to : null);
        }

        return back()->with('ok', 'Movimiento agregado.');
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

        // ✅ Si viene vacío => se envía a destinatarios registrados
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
     * ✅ NUEVO: envío masivo por periodo
     * POST /admin/billing/statements/bulk-email
     * - period (required)
     * - account_ids[] (optional) si no => todas las cuentas
     * - status (optional) filtra por saldo/status si quieres (best-effort)
     */
    public function bulkEmail(Request $req)
    {
        $data = $req->validate([
            'period'      => 'required|string',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'nullable|string|max:64',
            'status'      => 'nullable|string|in:all,pendiente,pagado,parcial,vencido,sin_mov',
        ]);

        $period = (string) $data['period'];
        if (!$this->isValidPeriod($period)) {
            return back()->withErrors(['period' => 'Periodo inválido. Formato YYYY-MM.']);
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
                // Si quieres filtrar por status, lo hacemos “best effort” con buildStatementData
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

            // micro-throttle para evitar timeouts SMTP
            usleep(180000); // 180ms
        }

        if ($req->expectsJson()) {
            return response()->json(['ok' => true, 'sent' => $ok, 'failed' => $fail]);
        }

        return back()->with('ok', "Envío masivo disparado. Enviados: {$ok}. Fallidos: {$fail}.");
    }

    /**
     * ✅ NUEVO comportamiento:
     * - Si $to viene null => resolver destinatarios desde account_recipients + accounts.email
     * - Si $to viene string => puede ser "a@a.com,b@b.com; c@c.com"
     */
    private function sendStatementEmailWithPayLink(string $accountId, string $period, ?string $to = null): void
    {
        $acc = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        if (!$acc) return;

        $recipients = $this->normalizeRecipientList($to);

        // ✅ Si no pasaron "to", usamos destinatarios configurados
        if (empty($recipients)) {
            $recipients = $this->resolveRecipientsForAccount((string)$accountId, (string)($acc->email ?? ''));
        }

        if (empty($recipients)) return;

        // ✅ base data
        $data = $this->buildStatementData($accountId, $period);

        $data['pdf_url'] = route('cliente.billing.publicPdfInline', [
            'accountId' => $accountId,
            'period'    => $period,
        ]);

        $data['portal_url'] = route('cliente.estado_cuenta') . '?period=' . urlencode($period);

        // En buildStatementData "total" es el saldo (a pagar).
        $totalPesos = (float) ($data['total'] ?? 0);

        $payUrl = null;
        $sessionId = null;

        if ($totalPesos > 0.00001) {
            try {
                [$payUrl, $sessionId] = $this->createStripeCheckoutForStatement($acc, $period, $totalPesos);
            } catch (\Throwable $e) {
                Log::error('[ADMIN][STATEMENT][EMAIL] No se pudo crear Stripe checkout', [
                    'account_id' => $accountId,
                    'period'     => $period,
                    'e'          => $e->getMessage(),
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

        // ✅ Enviar a todos los destinatarios
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
                    'e'          => $e->getMessage(),
                ]);
            }

            usleep(90000); // 90ms
        }
    }

    /**
     * ✅ Destinatarios por cuenta:
     * - account_recipients (activos) + accounts.email
     * - dedup, lower-case
     *
     * @return array<int, string>
     */
    private function resolveRecipientsForAccount(string $accountId, string $fallbackEmail): array
    {
        $emails = [];

        // accounts.email
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

        // dedup
        $emails = array_values(array_unique($emails));

        return $emails;
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
        if ($has('amount'))     $row['amount']     = $amountCents; // cents
        if ($has('amount_mxn')) $row['amount_mxn'] = round($uiTotalPesos, 2);

        if ($has('currency'))   $row['currency']   = 'MXN';
        if ($has('status'))     $row['status']     = 'pending';
        if ($has('due_date'))   $row['due_date']   = now();

        if ($has('period'))     $row['period']     = $period;
        if ($has('method'))     $row['method']     = 'card';
        if ($has('provider'))   $row['provider']   = 'stripe';
        if ($has('concept'))    $row['concept']    = 'Pactopia360 · Estado de cuenta ' . $period;
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

        $consumos = [];

        $monthlyCandidates = [];
        foreach ($items as $it) {
            $concepto = (string) ($it->concepto ?? '');
            $cargoIt  = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;

            if ($cargoIt <= 0) continue;

            $c = mb_strtolower(trim($concepto));
            if (
                str_contains($c, 'servicio mensual') ||
                str_contains($c, 'licencia') ||
                str_contains($c, 'suscrip') ||
                str_contains($c, 'membres')
            ) {
                $monthlyCandidates[] = $it;
            }
        }

        $monthlyItem = !empty($monthlyCandidates) ? $monthlyCandidates[0] : null;

        $monthlySubtotal = 0.0;
        if ($monthlyItem) {
            $monthlySubtotal = is_numeric($monthlyItem->cargo ?? null) ? (float) $monthlyItem->cargo : 0.0;
        } else {
            $monthlySubtotal = (float) $expectedTotal;
        }

        $consumos[] = [
            'service'   => 'Servicio mensual',
            'unit_cost' => round($monthlySubtotal, 2),
            'qty'       => 1,
            'subtotal'  => round($monthlySubtotal, 2),
        ];

        $extrasSum = 0.0;

        foreach ($items as $it) {
            $cargoIt = is_numeric($it->cargo ?? null) ? (float) $it->cargo : 0.0;
            if ($cargoIt <= 0) continue;

            if ($monthlyItem && isset($it->id) && isset($monthlyItem->id) && (string)$it->id === (string)$monthlyItem->id) {
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

        $totalConsumos = (float) $monthlySubtotal + (float) $extrasSum;

        $cargoShown = round($totalConsumos, 2);
        $saldo = round(max(0, $cargoShown - (float) $abono), 2);

        $assets = $this->buildPdfAssetUris();

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

            'total_due'      => $saldo,
            'total'          => $saldo,

            'generated_at'   => now(),

            'last_paid'      => $lastPaid,
            'pay_allowed'    => $payAllowed,

            'pay_url'        => $qrText,
            'qr_data_uri'    => $qrDataUri,
            'qr_url'         => $qrUrl,

            'logo_data_uri'          => $assets['logo_data_uri'] ?? null,

            'pay_paypal_data_uri'    => $assets['pay_paypal_data_uri'] ?? null,
            'pay_visa_data_uri'      => $assets['pay_visa_data_uri'] ?? null,
            'pay_amex_data_uri'      => $assets['pay_amex_data_uri'] ?? null,
            'pay_mc_data_uri'        => $assets['pay_mc_data_uri'] ?? null,
            'pay_oxxo_data_uri'      => $assets['pay_oxxo_data_uri'] ?? null,

            'social_fb_data_uri'     => $assets['social_fb_data_uri'] ?? null,
            'social_in_data_uri'     => $assets['social_in_data_uri'] ?? null,
            'social_yt_data_uri'     => $assets['social_yt_data_uri'] ?? null,
            'social_ig_data_uri'     => $assets['social_ig_data_uri'] ?? null,
        ];
    }

    // =========================================================
    // QR + ASSETS (dompdf-safe)
    // =========================================================

    private function fileToDataUri(string $absPath): ?string
    {
        try {
            if (!is_file($absPath) || !is_readable($absPath)) return null;

            $bin = file_get_contents($absPath);
            if ($bin === false || $bin === '') return null;

            $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png'  => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'svg'  => 'image/svg+xml',
                default => 'application/octet-stream',
            };

            return 'data:' . $mime . ';base64,' . base64_encode($bin);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildPdfAssetUris(): array
    {
        $pub = public_path();

        $logoPath = $pub . '/assets/brand/pactopia-logo.png';

        $payDir = $pub . '/assets/brand/pdf/payments';
        $socDir = $pub . '/assets/brand/pdf/social';

        return [
            'logo_data_uri' => $this->fileToDataUri($logoPath),

            'pay_paypal_data_uri' => $this->fileToDataUri($payDir . '/paypal.png'),
            'pay_visa_data_uri'   => $this->fileToDataUri($payDir . '/visa.png'),
            'pay_amex_data_uri'   => $this->fileToDataUri($payDir . '/amex.png'),
            'pay_mc_data_uri'     => $this->fileToDataUri($payDir . '/mastercard.png'),
            'pay_oxxo_data_uri'   => $this->fileToDataUri($payDir . '/oxxo.png'),

            'social_fb_data_uri'  => $this->fileToDataUri($socDir . '/facebook.png'),
            'social_in_data_uri'  => $this->fileToDataUri($socDir . '/linkedin.png'),
            'social_yt_data_uri'  => $this->fileToDataUri($socDir . '/youtube.png'),
            'social_ig_data_uri'  => $this->fileToDataUri($socDir . '/instagram.png'),
        ];
    }

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

            $orderCol = $has('paid_at') ? 'paid_at' : ($has('updated_at') ? 'updated_at' : ($has('created_at') ? 'created_at' : ($has('id') ? 'id' : $cols[0])));

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
        $fallback = [0.0, '—', 'dim'];

        try {
            $res = $this->hub->resolveEffectiveAmountForPeriodFromMeta($meta, $period, $payAllowed);

            if (is_array($res)) {
                $mxn   = isset($res[0]) && is_numeric($res[0]) ? (float) $res[0] : 0.0;
                $label = isset($res[1]) && is_string($res[1]) ? (string) $res[1] : '—';
                $pill  = isset($res[2]) && is_string($res[2]) ? (string) $res[2] : 'dim';

                $pill = in_array($pill, ['info', 'warn', 'ok', 'dim', 'bad'], true) ? $pill : 'dim';

                return [round($mxn, 2), $label, $pill];
            }

            if (is_numeric($res)) {
                return [round((float) $res, 2), '—', 'dim'];
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
