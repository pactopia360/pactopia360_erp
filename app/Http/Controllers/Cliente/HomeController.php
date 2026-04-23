<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente\Cfdi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HomeController extends Controller
{

        /**
     * SOT: Obtiene el usuario autenticado respetando el guard por defecto.
     * - Primero intenta el guard default (config auth.defaults.guard).
     * - Luego fallback a 'cliente'
     * - Luego fallback a 'web'
     */
    private function authUser()
    {
        try {
            $default = (string) (config('auth.defaults.guard') ?? 'web');
            $u = Auth::guard($default)->user();
            if ($u) return $u;
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $u = Auth::guard('cliente')->user();
            if ($u) return $u;
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            return Auth::guard('web')->user();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function authUserId(): ?string
    {
        $u = $this->authUser();
        if (!$u) return null;
        try {
            return (string) ($u->getAuthIdentifier() ?? null);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function authGuardName(): string
    {
        return (string) (config('auth.defaults.guard') ?? 'web');
    }

    public function index(Request $request): View
{
    $user   = $this->authUser();
    $cuenta = $user?->cuenta;

    if (is_array($cuenta)) {
        $cuenta = (object) $cuenta;
    }

    $monthRaw = $request->input('month');
    $month    = is_string($monthRaw) ? trim($monthRaw) : null;

    [$from, $to] = $this->resolveMonthRange($month);

    $perPage = max(1, (int) $request->integer('per_page', 15));

    $cfdis = new \Illuminate\Pagination\LengthAwarePaginator(
        collect(),
        0,
        $perPage,
        \Illuminate\Pagination\Paginator::resolveCurrentPage(),
        [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]
    );

    $current = (object) [
        'id'         => null,
        'uuid'       => null,
        'serie'      => null,
        'folio'      => null,
        'subtotal'   => 0,
        'iva'        => 0,
        'total'      => 0,
        'fecha'      => null,
        'estatus'    => null,
        'cliente_id' => null,
    ];

    $recent = collect();

    $kpis = [
        'total'      => 0.0,
        'emitidos'   => 0.0,
        'cancelados' => 0.0,
        'delta'      => 0.0,
        'period'     => ['from' => $from, 'to' => $to],
    ];

    $series = [
        'labels' => [],
        'series' => [
            'emitidos_total'   => [],
            'line_facturacion' => [],
            'line_cancelados'  => [],
            'bar_q'            => [0, 0, 0, 0],
        ],
    ];

    $dataSource = 'db';

    if ($this->canQueryCfdi()) {
        try {
            $base = Cfdi::on('mysql_clientes');

            if ($cuenta && $this->schemaHasCol('clientes', 'cuenta_id')) {
                $clienteIds = DB::connection('mysql_clientes')
                    ->table('clientes')
                    ->where('cuenta_id', $cuenta->id)
                    ->pluck('id')
                    ->all();

                $base->whereIn('cliente_id', empty($clienteIds) ? [-1] : $clienteIds);
            }

            $q = (clone $base)->whereBetween('fecha', [$from, $to]);

            $search = trim((string) $request->input('q', ''));
            if ($search !== '') {
                $q->where(function ($sub) use ($search) {
                    $sub->where('uuid', 'like', "%{$search}%")
                        ->orWhere('serie', 'like', "%{$search}%")
                        ->orWhere('folio', 'like', "%{$search}%");
                });
            }

            $status = trim((string) $request->input('status', ''));
            if ($status !== '') {
                $q->where('estatus', $status);
            }

            $q->with([
                'cliente:id,razon_social,nombre_comercial,rfc',
                'conceptos:id,cfdi_id,descripcion',
            ]);

            $cfdis = $q->orderByDesc('fecha')
                ->paginate(
                    $perPage,
                    ['id', 'uuid', 'serie', 'folio', 'subtotal', 'iva', 'total', 'fecha', 'estatus', 'cliente_id']
                )
                ->withQueryString();

            $first = $cfdis->getCollection()->first();
            if ($first) {
                $current = $first;
            }

            $recent = $cfdis->getCollection()
                ->take(8)
                ->values();

            $kpis   = $this->calcKpisFor(clone $base, $from, $to);
            $series = $this->buildSeriesFor(clone $base, $from, $to);

            if ($this->isDemoMode() && empty($series['series']['emitidos_total'])) {
                [$demoKpis, $demoSeries] = $this->buildDemoData($from, $to);

                if ((float) ($kpis['total'] ?? 0) <= 0) {
                    $kpis = $demoKpis;
                }

                $series     = $demoSeries;
                $dataSource = 'demo';
            }
        } catch (\Throwable $e) {
            Log::error('cliente.home.index_failed', [
                'message' => $e->getMessage(),
                'user_id' => $this->authUserId(),
            ]);

            if ($this->isDemoMode()) {
                [$demoKpis, $demoSeries] = $this->buildDemoData($from, $to);
                $kpis       = $demoKpis;
                $series     = $demoSeries;
                $dataSource = 'demo';
            }
        }
    } elseif ($this->isDemoMode()) {
        [$demoKpis, $demoSeries] = $this->buildDemoData($from, $to);
        $kpis       = $demoKpis;
        $series     = $demoSeries;
        $dataSource = 'demo';
    }

    $summary = $this->buildAccountSummary();

    $summaryPlanNorm = strtolower((string) ($summary['plan_norm'] ?? $summary['plan'] ?? ''));
    $isPro = array_key_exists('is_pro', $summary)
        ? (bool) $summary['is_pro']
        : in_array($summaryPlanNorm, ['pro', 'premium', 'empresa', 'business'], true);

    if ($isPro) {
        $plan = 'PRO';
    } else {
        $plan = strtoupper((string) ($summary['plan'] ?? 'FREE'));
        if ($plan === '') {
            $plan = 'FREE';
        }
    }

    $planKey = strtolower((string) $plan);

    $mes  = (int) Carbon::parse($from)->format('m');
    $anio = (int) Carbon::parse($from)->format('Y');

    return view('cliente.home', [
        'period_from' => $from,
        'period_to'   => $to,

        'kpis'        => $kpis,
        'series'      => $series,

        'cfdis'       => $cfdis,
        'cfdi'        => $current,
        'recent'      => $recent,

        'summary'     => $summary,
        'razon'       => (string) ($summary['razon'] ?? 'Cliente'),
        'timbres'     => (int) ($summary['timbres'] ?? 0),
        'saldo'       => (float) ($summary['balance'] ?? 0),

        'plan'        => $plan,
        'planKey'     => $planKey,
        'isPro'       => $isPro,

        'dataSource'  => $dataSource,
        'isLocal'     => app()->environment(['local', 'development', 'testing']),

        'filters'     => [
            'q'      => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
            'month'  => trim((string) $request->input('month', '')),
            'mes'    => $mes,
            'anio'   => $anio,
        ],
    ]);
}

    /**
     * KPIs (JSON). Devuelve 'source' y 'row_count'.
     */
    public function kpis(Request $request): JsonResponse
    {
        $user   = $this->authUser();
        $cuenta = $user?->cuenta;

        if (is_array($cuenta)) {
            $cuenta = (object)$cuenta;
        }

        $monthRaw = $request->input('month');
        $month    = is_string($monthRaw) ? $monthRaw : null;

        [$from, $to] = $this->resolveMonthRange($month);

        $k        = [
            'total'      => 0.0,
            'emitidos'   => 0.0,
            'cancelados' => 0.0,
            'delta'      => 0.0,
            'period'     => ['from' => $from, 'to' => $to],
        ];
        $source   = 'db';
        $rowCount = 0;

        if ($this->canQueryCfdi()) {
            try {
                $base = Cfdi::on('mysql_clientes');

                if ($cuenta && $this->schemaHasCol('clientes', 'cuenta_id')) {
                    $clienteIds = DB::connection('mysql_clientes')
                        ->table('clientes')
                        ->where('cuenta_id', $cuenta->id)
                        ->pluck('id')
                        ->all();

                    $base->whereIn('cliente_id', empty($clienteIds) ? [-1] : $clienteIds);
                }

                $k        = $this->calcKpisFor(clone $base, $from, $to);
                $rowCount = (int) (clone $base)
                    ->whereBetween('fecha', [$from, $to])
                    ->count();

                if ($this->isDemoMode() && $k['total'] <= 0) {
                    [$demoKpis] = $this->buildDemoData($from, $to);
                    $k          = $demoKpis;
                    $source     = 'demo';
                    $rowCount   = 0;
                }
            } catch (\Throwable $e) {
                if ($this->isDemoMode()) {
                    [$demoKpis] = $this->buildDemoData($from, $to);
                    $k          = $demoKpis;
                    $source     = 'demo';
                    $rowCount   = 0;
                }
            }
        } elseif ($this->isDemoMode()) {
            [$demoKpis] = $this->buildDemoData($from, $to);
            $k          = $demoKpis;
            $source     = 'demo';
            $rowCount   = 0;
        }

        return response()->json($k + [
            'source'    => $source,
            'row_count' => $rowCount,
        ]);
    }

    /**
     * Series (JSON). Devuelve 'source' y 'row_count'.
     */
    public function series(Request $request): JsonResponse
    {
        $user   = $this->authUser();
        $cuenta = $user?->cuenta;

        if (is_array($cuenta)) {
            $cuenta = (object)$cuenta;
        }

        $monthRaw = $request->input('month');
        $month    = is_string($monthRaw) ? $monthRaw : null;
        [$from, $to] = $this->resolveMonthRange($month);

        $payload = [
            'labels'    => [],
            'series'    => [
                'line_facturacion' => [],
                'line_cancelados'  => [],
                'bar_q'            => [0, 0, 0, 0],
                'emitidos_total'   => [],
            ],
            'source'    => 'db',
            'row_count' => 0,
        ];

        if ($this->canQueryCfdi()) {
            try {
                $base = Cfdi::on('mysql_clientes');

                if ($cuenta && $this->schemaHasCol('clientes', 'cuenta_id')) {
                    $clienteIds = DB::connection('mysql_clientes')
                        ->table('clientes')
                        ->where('cuenta_id', $cuenta->id)
                        ->pluck('id')
                        ->all();
                    $base->whereIn('cliente_id', empty($clienteIds) ? [-1] : $clienteIds);
                }

                $rows = (clone $base)->whereBetween('fecha', [$from, $to])
                    ->selectRaw("
                        DATE(fecha) as d,
                        SUM(CASE WHEN estatus='emitido'   THEN total ELSE 0 END) as em_total,
                        SUM(CASE WHEN estatus='cancelado' THEN total ELSE 0 END) as ca_total
                    ")
                    ->groupBy('d')
                    ->orderBy('d', 'asc')
                    ->get();

                $labels = [];
                $lineEmitidos = [];
                $lineCancelados = [];

                foreach ($rows as $r) {
                    $labels[]         = $r->d;
                    $lineEmitidos[]   = round((float) $r->em_total, 2);
                    $lineCancelados[] = round((float) $r->ca_total, 2);
                }

                // Barras por cuartiles del mes
                $q = [0, 0, 0, 0];
                foreach ($rows as $r) {
                    $day   = (int) Carbon::parse($r->d)->format('j');
                    $bucket= min(3, intdiv($day - 1, 8));
                    $q[$bucket] += (float) $r->em_total;
                }
                $barQ = array_map(fn ($v) => round($v, 2), $q);

                $payload = [
                    'labels' => $labels,
                    'series' => [
                        'line_facturacion' => $lineEmitidos,
                        'line_cancelados'  => $lineCancelados,
                        'bar_q'            => $barQ,
                        'emitidos_total'   => $lineEmitidos,
                    ],
                    'source'    => 'db',
                    'row_count' => count($rows),
                ];

                if ($this->isDemoMode() && empty($lineEmitidos)) {
                    [, $demoSeries] = $this->buildDemoData($from, $to);
                    $payload = $demoSeries + [
                        'source'    => 'demo',
                        'row_count' => 0,
                    ];
                }
            } catch (\Throwable $e) {
                if ($this->isDemoMode()) {
                    [, $demoSeries] = $this->buildDemoData($from, $to);
                    $payload = $demoSeries + [
                        'source'    => 'demo',
                        'row_count' => 0,
                    ];
                }
            }
        } elseif ($this->isDemoMode()) {
            [, $demoSeries] = $this->buildDemoData($from, $to);
            $payload = $demoSeries + [
                'source'    => 'demo',
                'row_count' => 0,
            ];
        }

        return response()->json($payload);
    }

       /**
     * (Opcional) Combo para un solo fetch en el front. Incluye 'source'.
     */
    public function combo(Request $request): JsonResponse
    {
        $user   = $this->authUser();
        $cuenta = $user?->cuenta;

        if (is_array($cuenta)) {
            $cuenta = (object) $cuenta;
        }

        $monthRaw = $request->input('month');
        $month    = is_string($monthRaw) ? $monthRaw : null;

        [$from, $to] = $this->resolveMonthRange($month);

        $k = [
            'total'      => 0.0,
            'emitidos'   => 0.0,
            'cancelados' => 0.0,
            'delta'      => 0.0,
            'period'     => ['from' => $from, 'to' => $to],
        ];

        $s = [
            'labels' => [],
            'series' => [
                'emitidos_total'   => [],
                'line_facturacion' => [],
                'line_cancelados'  => [],
                'bar_q'            => [0, 0, 0, 0],
            ],
        ];

        $source = 'db';

        if ($this->canQueryCfdi()) {
            try {
                $base = Cfdi::on('mysql_clientes');

                if ($cuenta && $this->schemaHasCol('clientes', 'cuenta_id')) {
                    $ids = DB::connection('mysql_clientes')
                        ->table('clientes')
                        ->where('cuenta_id', $cuenta->id)
                        ->pluck('id')
                        ->all();

                    $base->whereIn('cliente_id', empty($ids) ? [-1] : $ids);
                }

                $k = $this->calcKpisFor(clone $base, $from, $to);
                $s = $this->buildSeriesFor(clone $base, $from, $to);

                if ($this->isDemoMode() && empty($s['series']['emitidos_total'])) {
                    [$dk, $ds] = $this->buildDemoData($from, $to);
                    $k      = ($k['total'] > 0) ? $k : $dk;
                    $s      = $ds;
                    $source = 'demo';
                }
            } catch (\Throwable $e) {
                if ($this->isDemoMode()) {
                    [$dk, $ds] = $this->buildDemoData($from, $to);
                    $k      = $dk;
                    $s      = $ds;
                    $source = 'demo';
                }
            }
        } elseif ($this->isDemoMode()) {
            [$dk, $ds] = $this->buildDemoData($from, $to);
            $k      = $dk;
            $s      = $ds;
            $source = 'demo';
        }

        return response()->json([
            'summary' => $this->buildAccountSummary(),
            'kpis'    => $k,
            'series'  => $s,
            'source'  => $source,
        ]);
    }

    /**
     * Sincroniza el modo DEMO del front (localStorage/query) hacia sesión.
     * - Solo permitido en local/dev/testing.
     * - Requiere auth (cliente/web según tu config).
     */
    public function setDemoMode(Request $request): JsonResponse
    {
        if (!app()->environment(['local', 'development', 'testing'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'DEMO mode is disabled in this environment.',
            ], 403);
        }

        $on = filter_var($request->input('demo', false), FILTER_VALIDATE_BOOLEAN);

        $request->session()->put('p360_demo_mode', $on);

        return response()->json([
            'ok'   => true,
            'demo' => (bool) $request->session()->get('p360_demo_mode', false),
        ]);
    }


    /* ===========================
     * Helpers internos
     * =========================== */

    private function calcKpisFor($baseQuery, string $from, string $to): array
    {
        $period   = (clone $baseQuery)->whereBetween('fecha', [$from, $to]);
        $total    = (float) ($period->clone()->sum('total') ?? 0);
        $emitidos = (float) ($period->clone()->where('estatus', 'emitido')->sum('total') ?? 0);
        $cancel   = (float) ($period->clone()->where('estatus', 'cancelado')->sum('total') ?? 0);

        $fromC    = Carbon::parse($from);
        $prevFrom = $fromC->copy()->subMonth()->startOfMonth()->toDateTimeString();
        $prevTo   = $fromC->copy()->subMonth()->endOfMonth()->toDateTimeString();
        $prevTotal= (float) ((clone $baseQuery)->whereBetween('fecha', [$prevFrom, $prevTo])->sum('total') ?? 0);

        return [
            'total'      => round($total, 2),
            'emitidos'   => round($emitidos, 2),
            'cancelados' => round($cancel, 2),
            'delta'      => $this->deltaPct($prevTotal, $total),
            'period'     => ['from' => $from, 'to' => $to],
        ];
    }

    private function buildSeriesFor($baseQuery, string $from, string $to): array
    {
        $rows = (clone $baseQuery)
            ->whereBetween('fecha', [$from, $to])
            ->selectRaw("
                DATE(fecha) as d,
                SUM(CASE WHEN estatus='emitido' THEN total ELSE 0 END) as emitidos_total
            ")
            ->groupBy('d')
            ->orderBy('d', 'asc')
            ->get();

        $labels = [];
        $vals   = [];

        foreach ($rows as $r) {
            $labels[] = $r->d;
            $vals[]   = round((float) $r->emitidos_total, 2);
        }

        return [
            'labels' => $labels,
            'series' => [
                'emitidos_total'   => $vals,
                'line_facturacion' => $vals,
            ],
        ];
    }

    private function deltaPct(float $prev, float $now): float
    {
        if ($prev <= 0 && $now <= 0) return 0.0;
        if ($prev <= 0 && $now > 0)  return 100.0;
        return round((($now - $prev) / max($prev, 0.00001)) * 100, 2);
    }

    private function schemaHasCol(string $table, string $col): bool
    {
        try {
            return Schema::connection('mysql_clientes')->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasCol(string $conn, string $table, string $col): bool
    {
        try {
            return Schema::connection($conn)->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasTable(string $conn, string $table): bool
    {
        try {
            return Schema::connection($conn)->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function canQueryCfdi(): bool
    {
        $table = (new Cfdi)->getTable();
        return $this->hasTable('mysql_clientes', $table);
    }

    /**
     * Resumen de cuenta.
     * ✅ Incluye billing (base/override/effective) gobernado por Admin.
     */
    public function buildAccountSummary(): array
    {
        $u      = $this->authUser();
        $cuenta = $u?->cuenta;

        if (is_array($cuenta)) {
            $cuenta = (object)$cuenta;
        }

        $admConn = 'mysql_admin';

        $planKey = strtoupper((string) ($cuenta->plan_actual ?? 'FREE'));
        $timbres = (int) ($cuenta->timbres_disponibles ?? ($planKey === 'FREE' ? 10 : 0));
        $saldoMx = (float) ($cuenta->saldo_mxn ?? 0.0);
        $razon   = $cuenta->razon_social ?? $cuenta->nombre_fiscal ?? ($u->nombre ?? $u->email ?? '—');

                // ==========================================================
        // Resolución ROBUSTA de la cuenta admin
        // Prioridad:
        // 1) cuenta_cliente.admin_account_id
        // 2) cuenta_cliente.rfc
        // 3) cuenta_cliente.rfc_padre como RFC real
        // 4) cuenta_cliente.rfc_padre legacy como accounts.id
        // 5) email de cuenta cliente / usuario autenticado
        // ==========================================================
        $adminId   = $cuenta->admin_account_id ?? null;
        $rfcMirror = strtoupper(trim((string) ($cuenta->rfc ?? '')));
        $rfcPadre  = strtoupper(trim((string) ($cuenta->rfc_padre ?? '')));
        $rfc       = $rfcMirror !== '' ? $rfcMirror : $rfcPadre;

        $emailCandidates = array_values(array_unique(array_filter([
            strtolower(trim((string) ($cuenta->email ?? ''))),
            strtolower(trim((string) ($cuenta->correo ?? ''))),
            strtolower(trim((string) ($cuenta->email_fiscal ?? ''))),
            strtolower(trim((string) ($u->email ?? ''))),
        ], fn ($v) => $v !== '')));

        if (Schema::connection($admConn)->hasTable('accounts')) {
            // 1) ID directo
            if ($adminId && !is_numeric($adminId)) {
                $adminId = null;
            }

            // 2) Fallback por RFC real
            if (!$adminId && $rfc !== '' && $this->hasCol($admConn, 'accounts', 'rfc')) {
                $accByRfc = DB::connection($admConn)
                    ->table('accounts')
                    ->select('id')
                    ->whereRaw('UPPER(rfc) = ?', [$rfc])
                    ->first();

                if ($accByRfc) {
                    $adminId = (int) $accByRfc->id;
                }
            }

            // 3) Fallback legacy: rfc_padre guardado como accounts.id
            if (!$adminId && $rfcPadre !== '' && ctype_digit($rfcPadre)) {
                $accById = DB::connection($admConn)
                    ->table('accounts')
                    ->select('id')
                    ->where('id', (int) $rfcPadre)
                    ->first();

                if ($accById) {
                    $adminId = (int) $accById->id;
                }
            }

            // 4) Fallback por email exacto
            if (
                !$adminId
                && !empty($emailCandidates)
                && $this->hasCol($admConn, 'accounts', 'email')
            ) {
                $accByEmail = DB::connection($admConn)
                    ->table('accounts')
                    ->select('id')
                    ->whereIn(DB::raw('LOWER(email)'), $emailCandidates)
                    ->orderByDesc('id')
                    ->first();

                if ($accByEmail) {
                    $adminId = (int) $accByEmail->id;
                }
            }
        }

        $acc = null;
        if ($adminId && Schema::connection($admConn)->hasTable('accounts')) {
            $cols = ['id'];

            foreach ([
                // Identidad / registro externo
                'rfc',
                'name',
                'razon_social',
                'email',
                'email_verified_at',
                'phone_verified_at',

                // Estado / billing
                'plan',
                'plan_actual',
                'billing_cycle',
                'modo_cobro',
                'billing_status',
                'next_invoice_date',
                'estado_cuenta',
                'is_blocked',
                'meta',

                // columnas posibles de pricing en accounts (por si existen)
                'billing_amount_mxn','amount_mxn','precio_mxn','monto_mxn',
                'override_amount_mxn','custom_amount_mxn','license_amount_mxn',
                'billing_amount','amount','precio','monto',
            ] as $c) {
                if ($this->hasCol($admConn, 'accounts', $c)) {
                    $cols[] = $c;
                }
            }

            $acc = DB::connection($admConn)
                ->table('accounts')
                ->select(array_values(array_unique($cols)))
                ->where('id', $adminId)
                ->first();

        }

        // ===========================
        // Balance (tu lógica existente)
        // ===========================
        $balance = $saldoMx;
        if (Schema::connection($admConn)->hasTable('estados_cuenta')) {
            $linkCol = null;
            $linkVal = null;

            if ($this->hasCol($admConn, 'estados_cuenta', 'account_id') && $adminId) {
                $linkCol = 'account_id';
                $linkVal = $adminId;
            } elseif ($this->hasCol($admConn, 'estados_cuenta', 'cuenta_id') && $adminId) {
                $linkCol = 'cuenta_id';
                $linkVal = $adminId;
            } elseif ($this->hasCol($admConn, 'estados_cuenta', 'rfc') && $rfc) {
                $linkCol = 'rfc';
                $linkVal = strtoupper($rfc);
            }

            if ($linkCol !== null) {
                $orderCol = $this->hasCol($admConn, 'estados_cuenta', 'periodo')
                    ? 'periodo'
                    : ($this->hasCol($admConn, 'estados_cuenta', 'created_at') ? 'created_at' : 'id');

                $last = DB::connection($admConn)->table('estados_cuenta')
                    ->where($linkCol, $linkVal)
                    ->orderByDesc($orderCol)
                    ->first();

                if ($last && property_exists($last, 'saldo') && $last->saldo !== null) {
                    $balance = (float) $last->saldo;
                } else {
                    $hasCargo = $this->hasCol($admConn, 'estados_cuenta', 'cargo');
                    $hasAbono = $this->hasCol($admConn, 'estados_cuenta', 'abono');

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

        // ===========================
        // Espacio (tu lógica existente)
        // ===========================
        $spaceTotal = (float) ($cuenta->espacio_total_mb ?? 512);
        $spaceUsed  = (float) ($cuenta->espacio_usado_mb ?? 0);
        $spacePct   = $spaceTotal > 0 ? min(100, round(($spaceUsed / $spaceTotal) * 100, 1)) : 0;

        // ===========================
        // Plan + ciclo (normalizados) — NULL SAFE si no hay $acc
        // ===========================
        $planRaw = (string) (
            ($acc?->plan)
            ?? ($acc?->plan_actual)
            ?? $planKey
        );

        $norm = $this->normalizePlanAndCycle($planRaw);

        // plan "base" sin sufijos: pro/premium/free/...
        $planBase = (string) ($norm['plan_base'] ?? 'free');

        // billing_cycle tiene prioridad si existe; si no, derivamos del sufijo del plan;
        // si no, usamos modo_cobro admin; si no, espejo cliente
        $cycle = ($acc?->billing_cycle)
            ?? (($norm['cycle'] ?? null)
            ?: (($acc?->modo_cobro) ?? ($cuenta->modo_cobro ?? 'mensual')));

        $estado = ($acc?->estado_cuenta)
            ?? ($acc?->billing_status)
            ?? ($cuenta->estado_cuenta ?? null);

        $blocked = (bool) (((int)($acc?->is_blocked ?? 0)) || ((int)($cuenta->is_blocked ?? 0)));

        // Exponemos plan como base normalizada (sin _mensual/_anual)
        $plan = $planBase;



        // ===========================
        // ✅ BILLING: precio vigente gobernado por Admin
        // ===========================
        $periodNow = now()->format('Y-m');

        $meta = $this->decodeMeta($acc?->meta ?? null);


        $lastPaid = $this->resolveLastPaidPeriodForAdminAccount((int)($acc->id ?? 0), $meta, $admConn);
        $payAllowed = $lastPaid
            ? Carbon::createFromFormat('Y-m', $lastPaid)->addMonthNoOverflow()->format('Y-m')
            : $periodNow;

        $pricing = $this->resolveEffectiveMonthlyAmountFromAdmin($acc, $meta, $periodNow, $payAllowed);

        // ===========================
        // ✅ RFC EXTERNO (registro admin)
        // ===========================
        $rfcExterno = null;
        if ($acc && isset($acc->rfc) && is_string($acc->rfc) && trim($acc->rfc) !== '') {
            $rfcExterno = strtoupper(trim($acc->rfc));
        } elseif (is_string($rfc) && trim($rfc) !== '') {
            // fallback a rfc_padre si existiera
            $rfcExterno = strtoupper(trim($rfc));
        }

        // Señal “verificado externamente” (defensivo: usa verificación de contacto como proxy si no hay flag dedicado)
        $externalVerified = false;
        if ($acc) {
            $externalVerified = !empty($acc->email_verified_at) || !empty($acc->phone_verified_at);
        }


         return [
            'razon'        => (string) (($acc?->razon_social) ?? $razon),

            // Normalizado para lógica
            'plan'         => $planBase,

            // Valor original desde admin/local
            'plan_raw'     => $planRaw,

            // Alias explícito
            'plan_norm'    => $planBase,

            // Badge comercial uniforme
            'is_pro'       => in_array($planBase, ['pro', 'premium', 'empresa', 'business'], true),

            'cycle'        => $cycle,
            'next_invoice' => $acc?->next_invoice_date ?? null,
            'estado'       => $estado,
            'blocked'      => $blocked,
            'balance'      => $balance,
            'space_total'  => $spaceTotal,
            'space_used'   => $spaceUsed,
            'space_pct'    => $spacePct,
            'timbres'      => $timbres,
            'admin_id'     => $adminId,

            // ✅ compat / consumo directo en UI
            'billing'      => $pricing,
            'amount_mxn'   => (float)($pricing['effective_amount_mxn'] ?? 0),
            'last_paid'    => $lastPaid,
            'pay_allowed'  => $payAllowed,

            // RFC proveniente de registro externo (Admin)
            'rfc_externo'           => $rfcExterno,
            'rfc_source'            => $rfcExterno ? 'external_registry' : null,
            'rfc_external_verified' => $externalVerified,

            // compat: algunos blades esperan rfc directo
            'rfc'                   => $rfcExterno,
        ];

    }

        /**
     * Normaliza planes tipo "pro_mensual", "pro_anual", "premium_anual", etc.
     * Retorna:
     * - plan_base: "pro" | "premium" | "free" | ...
     * - cycle: "mensual" | "anual" | null
     * - plan_norm: string (slug normalizado)
     */
    private function normalizePlanAndCycle(?string $planRaw): array
    {
        $p = strtolower(trim((string) $planRaw));
        $p = str_replace([' ', '-'], '_', $p);
        $p = preg_replace('/_+/', '_', $p) ?: '';

        $cycle = null;
        if (str_ends_with($p, '_mensual')) {
            $cycle = 'mensual';
            $p = substr($p, 0, -8); // remove "_mensual"
        } elseif (str_ends_with($p, '_anual')) {
            $cycle = 'anual';
            $p = substr($p, 0, -6); // remove "_anual"
        }

        $base = $p ?: 'free';

        return [
            'plan_base' => $base,
            'cycle'     => $cycle,
            'plan_norm' => $base, // base ya viene sin sufijo
        ];
    }


    // month=YYYY-MM
    private function resolveMonthRange(?string $month): array
    {
        if ($month && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $month)) {
            $from = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $to   = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        } else {
            $from = now()->startOfMonth();
            $to   = now()->endOfMonth();
        }

        return [$from->toDateTimeString(), $to->toDateTimeString()];
    }

    /**
     * DEMO solo si:
     * - Estás en local/dev/testing
     * - Y el usuario lo habilitó explícitamente en sesión.
     *
     * NOTA: Con esto, una cuenta nueva SIEMPRE verá ceros reales.
     */
    private function isDemoMode(): bool
    {
        if (!app()->environment(['local', 'development', 'testing'])) {
            return false;
        }

        return (bool) session('p360_demo_mode', false);
    }

    /**
     * Construye KPIs y series DEMO estables dentro del mes dado.
     */
    private function buildDemoData(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfMonth();
        $end   = Carbon::parse($to)->endOfMonth();

        $seed = crc32((string) (($this->authUserId() ?? '0')) . '|' . $start->format('Y-m'));
        mt_srand($seed);

        $labels = [];
        $emit   = [];
        $canc   = [];

        $day = $start->copy();
        while ($day->lte($end)) {
            $labels[] = $day->format('Y-m-d');

            $base = 3000 + mt_rand(0, 15000);
            $wave = 1 + 0.25 * sin(($day->dayOfYear / 58) * 3.14159);
            $val  = round($base * $wave, 2);

            $emit[] = $val;
            $canc[] = round($val * (mt_rand(5, 30) / 1000), 2);

            $day->addDay();
        }

        $q = [0, 0, 0, 0];
        foreach ($labels as $i => $d) {
            $dayNum = (int) substr($d, 8, 2);
            $bucket = min(3, intdiv($dayNum - 1, 8));
            $q[$bucket] += $emit[$i];
        }
        $barQ = array_map(fn ($v) => round($v, 2), $q);

        $sumEmit = array_sum($emit);
        $sumCanc = array_sum($canc);
        $kpis = [
            'total'      => round($sumEmit, 2),
            'emitidos'   => round($sumEmit, 2),
            'cancelados' => round($sumCanc, 2),
            'delta'      => mt_rand(-12, 18),
            'period'     => ['from' => $from, 'to' => $to],
        ];

        $series = [
            'labels' => $labels,
            'series' => [
                'line_facturacion' => $emit,
                'line_cancelados'  => $canc,
                'bar_q'            => $barQ,
                'emitidos_total'   => $emit,
            ],
        ];

        return [$kpis, $series];
    }

    // ===========================
    // ✅ Billing helpers (Admin -> Cliente)
    // ===========================

    private function decodeMeta($meta): array
    {
        if (is_array($meta)) return $meta;
        if (is_object($meta)) return (array) $meta;

        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
        }
        return [];
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

    private function resolveLastPaidPeriodForAdminAccount(int $adminAccountId, array $meta, string $admConn): ?string
    {
        if ($adminAccountId <= 0) return null;

        // 1) meta
        foreach ([
            data_get($meta, 'stripe.last_paid_at'),
            data_get($meta, 'stripe.lastPaidAt'),
            data_get($meta, 'billing.last_paid_at'),
            data_get($meta, 'billing.lastPaidAt'),
            data_get($meta, 'last_paid_at'),
            data_get($meta, 'lastPaidAt'),
        ] as $v) {
            $p = $this->parseToPeriod($v);
            if ($p) return $p;
        }

        // 2) payments (paid/succeeded) si existe
        if (Schema::connection($admConn)->hasTable('payments')) {
            try {
                $cols = Schema::connection($admConn)->getColumnListing('payments');
                $lc   = array_map('strtolower', $cols);
                $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

                if ($has('account_id') && $has('status') && $has('period')) {
                    $q = DB::connection($admConn)->table('payments')
                        ->where('account_id', $adminAccountId)
                        ->whereIn('status', ['paid','succeeded','success','completed','complete','captured','authorized']);

                    $order = $has('paid_at') ? 'paid_at' : ($has('created_at') ? 'created_at' : ($has('id') ? 'id' : $cols[0]));
                    $row = $q->orderByDesc($order)->first(['period']);

                    if ($row && !empty($row->period) && $this->isValidPeriod((string)$row->period)) {
                        return (string)$row->period;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return null;
    }

    private function resolveEffectiveMonthlyAmountFromAdmin(?object $acc, array $meta, string $period, string $payAllowed): array
    {
        // =========================================================
        // FIX CRÍTICO: primer acceso sin cuenta admin asociada
        // =========================================================
        if (!$acc) {
            return [
                'amount_mxn'            => 0.0,
                'override'              => [
                    'amount_mxn' => 0.0,
                    'effective'  => null,
                ],
                'effective_amount_mxn'  => 0.0,
                'label'                 => 'Sin tarifa',
                'pill'                  => 'Base',
            ];
        }

        $billing = (array)($meta['billing'] ?? []);

        // =========================================================
        // Base (prioridad: meta.billing.amount_mxn -> columnas -> 0)
        // =========================================================
        $base = $this->toFloat($billing['amount_mxn'] ?? ($billing['amount'] ?? null));

        if ($base === null || $base <= 0) {
            foreach ([
                'billing_amount_mxn','amount_mxn','precio_mxn','monto_mxn','license_amount_mxn',
                'billing_amount','amount','precio','monto',
            ] as $prop) {
                if (isset($acc->{$prop})) {
                    $n = $this->toFloat($acc->{$prop});
                    if ($n !== null && $n > 0) {
                        $base = $n;
                        break;
                    }
                }
            }
        }

        $base = (float)($base ?? 0.0);

        // =========================================================
        // Override (prioridad: meta.billing.override.amount_mxn
        // -> meta.billing.override_amount_mxn -> columnas override)
        // =========================================================
        $ov = (array)($billing['override'] ?? []);
        $override = $this->toFloat($ov['amount_mxn'] ?? ($billing['override_amount_mxn'] ?? null)) ?? 0.0;

        if ($override <= 0) {
            foreach (['override_amount_mxn','custom_amount_mxn'] as $prop) {
                if (isset($acc->{$prop})) {
                    $n = $this->toFloat($acc->{$prop});
                    if ($n !== null && $n > 0) {
                        $override = $n;
                        break;
                    }
                }
            }
        }

        // =========================================================
        // Aplicación del override (now | next)
        // =========================================================
        $eff = strtolower(trim((string)($ov['effective'] ?? ($billing['override_effective'] ?? ''))));
        if (!in_array($eff, ['now','next'], true)) {
            $eff = '';
        }

        $apply = false;
        if ($override > 0) {
            if ($eff === 'now') {
                $apply = true;
            } elseif ($eff === 'next') {
                // Aplica desde payAllowed en adelante
                $apply = ($payAllowed !== '' && $this->isValidPeriod($payAllowed) && $period >= $payAllowed);
            }
        }

        $effective = $apply ? (float)$override : (float)$base;

        $label = $apply ? 'Tarifa ajustada' : 'Tarifa base';
        $pillText = $apply
            ? (($eff === 'next') ? 'Ajuste (próximo periodo)' : 'Ajuste (vigente)')
            : 'Base';

        return [
            'amount_mxn'            => round((float)$base, 2),
            'override'              => [
                'amount_mxn' => round((float)$override, 2),
                'effective'  => $eff ?: null,
            ],
            'effective_amount_mxn'  => round((float)$effective, 2),
            'label'                 => (string)$label,
            'pill'                  => (string)$pillText,
        ];
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

        /**
     * Catálogo SOT de módulos visibles al cliente.
     * Debe coincidir con Admin/Billing/Accounts.
     */
    private function modulesCatalog(): array
    {
        return [
            'mi_cuenta' => [
                'label' => 'Mi cuenta',
                'desc'  => 'Pantalla de cuenta, perfil, configuración y accesos.',
                'group' => 'Cuenta',
            ],
            'estado_cuenta' => [
                'label' => 'Estado de cuenta',
                'desc'  => 'Estados de cuenta, periodos, cargos y seguimiento de billing.',
                'group' => 'Cuenta',
            ],
            'pagos' => [
                'label' => 'Pagos',
                'desc'  => 'Historial de pagos, confirmaciones y control administrativo.',
                'group' => 'Cuenta',
            ],
            'facturas' => [
                'label' => 'Facturas',
                'desc'  => 'Facturas generadas por la plataforma para la cuenta.',
                'group' => 'Cuenta',
            ],

            'sat_descargas' => [
                'label' => 'SAT Descargas',
                'desc'  => 'RFC, cotizaciones SAT, pagos, seguimiento operativo y descargas.',
                'group' => 'Fiscal',
            ],
            'boveda_fiscal' => [
                'label' => 'Bóveda Fiscal SAT',
                'desc'  => 'Consulta documental y operación ligada al ecosistema SAT.',
                'group' => 'Fiscal',
            ],
            'facturacion' => [
                'label' => 'Facturación',
                'desc'  => 'CFDI comerciales con consumo de timbres / hits.',
                'group' => 'Fiscal',
            ],
            'timbres_hits' => [
                'label' => 'Timbres / Hits',
                'desc'  => 'Compra, saldo, consumo y configuración de timbrado.',
                'group' => 'Fiscal',
            ],

            'crm' => [
                'label' => 'CRM',
                'desc'  => 'Prospectos, clientes, pipeline comercial y seguimiento.',
                'group' => 'Comercial',
            ],
            'inventario' => [
                'label' => 'Inventario',
                'desc'  => 'Productos, existencias, movimientos y base operativa.',
                'group' => 'Operación',
            ],
            'ventas' => [
                'label' => 'Ventas',
                'desc'  => 'Tickets, códigos de venta y base para autofacturación.',
                'group' => 'Operación',
            ],
            'reportes' => [
                'label' => 'Reportes',
                'desc'  => 'Indicadores, dashboards y análisis general.',
                'group' => 'Operación',
            ],
            'recursos_humanos' => [
                'label' => 'Recursos Humanos',
                'desc'  => 'Empleados, incidencias, nómina y CFDI de nómina dentro del mismo módulo.',
                'group' => 'Recursos Humanos',
            ],
        ];
    }

    /**
     * Normaliza modules_state desde admin.accounts.meta.
     * Soporta bool/int/string/array.
     */
    private function normalizeModulesState(array $meta): array
    {
        $catalog = $this->modulesCatalog();

        $raw = data_get($meta, 'modules_state');
        if (!is_array($raw)) {
            $raw = data_get($meta, 'modules.state');
        }
        if (!is_array($raw)) {
            $raw = data_get($meta, 'modules');
        }
        if (!is_array($raw)) {
            $raw = [];
        }

        $state = [];

        foreach ($catalog as $key => $cfg) {
            $value = $raw[$key] ?? true;

            $entry = [
                'key'     => (string) $key,
                'label'   => (string) ($cfg['label'] ?? $key),
                'desc'    => (string) ($cfg['desc'] ?? ''),
                'group'   => (string) ($cfg['group'] ?? 'General'),
                'visible' => true,
                'enabled' => true,
                'hidden'  => false,
                'status'  => 'active',
            ];

            if (is_bool($value)) {
                $entry['visible'] = $value;
                $entry['enabled'] = $value;
                $entry['hidden']  = !$value;
                $entry['status']  = $value ? 'active' : 'hidden';
            } elseif (is_numeric($value)) {
                $on = ((int) $value) === 1;
                $entry['visible'] = $on;
                $entry['enabled'] = $on;
                $entry['hidden']  = !$on;
                $entry['status']  = $on ? 'active' : 'hidden';
            } elseif (is_string($value)) {
                $s = strtolower(trim($value));

                if (in_array($s, ['1', 'true', 'on', 'yes', 'enabled', 'active', 'visible'], true)) {
                    $entry['visible'] = true;
                    $entry['enabled'] = true;
                    $entry['hidden']  = false;
                    $entry['status']  = 'active';
                } elseif (in_array($s, ['0', 'false', 'off', 'no', 'disabled', 'inactive', 'hidden'], true)) {
                    $entry['visible'] = false;
                    $entry['enabled'] = false;
                    $entry['hidden']  = true;
                    $entry['status']  = 'hidden';
                } elseif ($s === 'blocked') {
                    $entry['visible'] = true;
                    $entry['enabled'] = false;
                    $entry['hidden']  = false;
                    $entry['status']  = 'blocked';
                }
            } elseif (is_array($value)) {
                if (array_key_exists('visible', $value)) {
                    $entry['visible'] = (bool) $value['visible'];
                }
                if (array_key_exists('enabled', $value)) {
                    $entry['enabled'] = (bool) $value['enabled'];
                }
                if (array_key_exists('hidden', $value)) {
                    $entry['hidden'] = (bool) $value['hidden'];
                }
                if (array_key_exists('status', $value) && is_string($value['status'])) {
                    $entry['status'] = strtolower(trim((string) $value['status']));
                }

                if ($entry['hidden']) {
                    $entry['visible'] = false;
                }

                if ($entry['status'] === 'blocked') {
                    $entry['visible'] = true;
                    $entry['enabled'] = false;
                    $entry['hidden']  = false;
                } elseif (in_array($entry['status'], ['hidden', 'inactive', 'disabled'], true)) {
                    $entry['visible'] = false;
                    $entry['enabled'] = false;
                    $entry['hidden']  = true;
                    $entry['status']  = 'hidden';
                } else {
                    $entry['status'] = $entry['enabled'] ? 'active' : 'inactive';
                }
            }

            $state[$key] = $entry;
        }

        return $state;
    }
}
