<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    /** Nombre de la conexión que usaremos para estadísticas */
    protected string $statsConn;

    public function __construct()
    {
        // Permite fijarlo en config('database.stats_connection') o en .env (DB_STATS_CONNECTION)
        $this->statsConn = config('database.stats_connection')
            ?? env('DB_STATS_CONNECTION', config('database.default'));
    }

    /** Atajo para DB con la conexión de stats */
    protected function db()
    {
        return DB::connection($this->statsConn);
    }

    /** Atajo para Schema con la conexión de stats */
    protected function schema()
    {
        return Schema::connection($this->statsConn);
    }

    public function index()
    {
        return view('admin.home');
    }

    /**
     * Dashboard stats (JSON)
     */
    public function stats(Request $request): JsonResponse
    {
        $rid     = $request->headers->get('X-Request-Id') ?: Str::uuid()->toString();
        $adminId = Auth::guard('admin')->id();
        $ip      = $request->ip();
        $t0      = microtime(true);

        try { app()->setLocale('es'); Carbon::setLocale('es'); } catch (\Throwable $e) {}

        // Flags/env
        $LOG_ENABLED   = filter_var(env('HOME_STATS_LOG', false), FILTER_VALIDATE_BOOLEAN);
        $LOG_EVERY     = max(5, (int) env('HOME_STATS_LOG_EVERY', 60));
        $LOG_QUERIES   = filter_var(env('HOME_STATS_LOG_QUERIES', false), FILTER_VALIDATE_BOOLEAN);
        $SLOW_MS       = max(1, (int) env('HOME_STATS_SLOW_MS', 250));
        $WARN_MS       = max(1, (int) env('HOME_STATS_WARN_MS', 800));
        $QUERY_WARN    = max(1, (int) env('HOME_STATS_QUERY_WARN', 60));
        $TTL           = max(0, (int) env('HOME_STATS_TTL', 30));
        $NO_CACHE      = $request->boolean('nocache');

        // ====== Captura de queries (opcional) ======
        $queryCount = 0;
        $slowQueries = [];
        $sampleQueries = [];
        if ($LOG_ENABLED && $LOG_QUERIES) {
            DB::listen(function ($q) use (&$queryCount, &$slowQueries, &$sampleQueries, $SLOW_MS) {
                $queryCount++;
                $ms = (int) $q->time;
                if ($ms >= $SLOW_MS) {
                    $slowQueries[] = [
                        'time_ms'  => $ms,
                        'sql'      => $q->sql,
                        'bindings' => $this->safeBindings($q->bindings),
                    ];
                }
                if (count($sampleQueries) < 20) {
                    $sampleQueries[] = ['time_ms' => $ms, 'sql' => $q->sql];
                }
            });
        }

        // ====== Filtros (UI) ======
        // UI manda: from=YYYY-MM, to=YYYY-MM, scope=paid|issued|all, group=...
        // Backend: soportamos from/to/scope; ignoramos group (por ahora).
        $fromYm = trim((string) $request->string('from', ''));
        $toYm   = trim((string) $request->string('to', ''));
        $scope  = trim(mb_strtolower((string) $request->string('scope', 'paid')));
        if (!in_array($scope, ['paid','issued','all'], true)) $scope = 'paid';

        // Compat: si alguien manda months aún
        $monthsLegacy = max(3, min(24, (int) $request->integer('months', 12)));

        // Plan filter (no está en UI hoy, pero lo dejamos listo)
        $planFilter = trim(mb_strtolower((string) $request->string('plan', '')));
        if ($planFilter === 'all' || $planFilter === '') $planFilter = null;

        // Rango real
        [$from, $to, $months] = $this->resolveRange($fromYm, $toYm, $monthsLegacy);

        // ====== Cache key (amarrada a conexión + filtros reales) ======
        $cacheKey = sprintf(
            'home:stats:v3:conn:%s:from:%s:to:%s:scope:%s:plan:%s',
            $this->statsConn,
            $from->format('Y-m'),
            $to->format('Y-m'),
            $scope,
            $planFilter ?: 'all'
        );

        $bots = [];
        $compute = function () use ($from, $to, $months, $planFilter, $scope, &$bots) {
            $db  = $this->db();
            $sch = $this->schema();

            $has    = fn(string $t) => $sch->hasTable($t);
            $hasCol = fn(string $t, string $c) => $sch->hasColumn($t, $c);

            // =========================
            // ✅ Selección de tablas REAL
            // =========================
            $T_ACCOUNTS = $has('accounts') ? 'accounts' : null;

            // Pagos: priorizar payments
            $T_PAYMENTS = null;
            if ($has('payments') && ($hasCol('payments', 'amount') || $hasCol('payments', 'monto'))) {
                $T_PAYMENTS = 'payments';
            } elseif ($has('pagos') && ($hasCol('pagos', 'amount') || $hasCol('pagos', 'monto'))) {
                $T_PAYMENTS = 'pagos';
            } elseif ($has('cobros') && ($hasCol('cobros', 'amount') || $hasCol('cobros', 'monto'))) {
                $T_PAYMENTS = 'cobros';
            }

            $T_CFDI   = $has('cfdis') ? 'cfdis' : null;

            // Helper seguro para LOWER(col)
            $lowerCol = function (?string $table, string $col) {
                return $table ? DB::raw("LOWER({$table}.{$col})") : DB::raw("LOWER({$col})");
            };

            // ===== Meses base =====
            $monthsMap = [];
            for ($d = $from->copy(); $d <= $to; $d->addMonth()) {
                $monthsMap[$d->format('Y-m')] = 0;
            }
            $labels = array_keys($monthsMap);

            // =========================
            // ✅ KPIs (sobre ACCOUNTS)
            // =========================
            $totalClientes = $T_ACCOUNTS ? (int) $db->table($T_ACCOUNTS)->count() : 0;

            $hasBlocked = $T_ACCOUNTS && $hasCol($T_ACCOUNTS, 'is_blocked');
            $hasEstado  = $T_ACCOUNTS && $hasCol($T_ACCOUNTS, 'estado_cuenta');
            $hasPlan    = $T_ACCOUNTS && $hasCol($T_ACCOUNTS, 'plan');
            $hasCreated = $T_ACCOUNTS && $hasCol($T_ACCOUNTS, 'created_at');

            $activos = 0;
            $pendientes = 0;
            $inactivos = 0;

            if ($T_ACCOUNTS) {
                if ($hasBlocked) {
                    $pendientes = (int) $db->table($T_ACCOUNTS)->where('is_blocked', 1)->count();
                }

                $qAct = $db->table($T_ACCOUNTS);
                if ($hasEstado) {
                    $qAct->whereRaw("LOWER(estado_cuenta) IN ('activa','activo','active')");
                } elseif ($hasBlocked) {
                    $qAct->where('is_blocked', 0);
                }
                $activos = (int) $qAct->count();

                $inactivos = max(0, $totalClientes - $activos);
            }

            $nuevosMes = 0;
            if ($T_ACCOUNTS && $hasCreated) {
                $nuevosMes = (int) $db->table($T_ACCOUNTS)
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count();
            }

            $premium = 0;
            if ($T_ACCOUNTS && $hasPlan) {
                $premium = (int) $db->table($T_ACCOUNTS)
                    ->whereRaw("LOWER(plan) NOT IN ('free','gratis','trial','demo')")
                    ->count();
            }

            // =========================
            // ✅ Ingresos (sobre PAYMENTS)
            // =========================
            $ingresos = $monthsMap;
            $ingresosTabla = [];
            $ingresoMesActual = 0.0;
            $ingresoTotalRango = 0.0;
            $pagosTotalRango = 0;

            $paidStatuses = ['paid','succeeded','success','completed','complete','captured','authorized'];

            // scope:
            // - paid: solo statuses "pagado"
            // - issued: NO filtra status (si tu tabla guarda emisiones; si no, equivale a all)
            // - all: NO filtra status
            $mustFilterPaid = ($scope === 'paid');

            $paymentsMeta = [
                'table' => $T_PAYMENTS,
                'amtCol' => null,
                'dateCol' => null,
                'statusCol' => null,
                'accountIdCol' => null,
            ];

            if ($T_PAYMENTS) {
                $amtCol    = $hasCol($T_PAYMENTS,'monto') ? 'monto' : ($hasCol($T_PAYMENTS,'amount') ? 'amount' : null);
                $dateCol   = $hasCol($T_PAYMENTS,'paid_at') ? 'paid_at' : ($hasCol($T_PAYMENTS,'fecha') ? 'fecha' : ($hasCol($T_PAYMENTS,'created_at') ? 'created_at' : null));
                $statusCol = $hasCol($T_PAYMENTS,'status') ? 'status' : ($hasCol($T_PAYMENTS,'estado') ? 'estado' : null);
                $accountIdCol = $hasCol($T_PAYMENTS,'account_id') ? 'account_id' : null;

                $paymentsMeta['amtCol'] = $amtCol;
                $paymentsMeta['dateCol'] = $dateCol;
                $paymentsMeta['statusCol'] = $statusCol;
                $paymentsMeta['accountIdCol'] = $accountIdCol;

                if ($amtCol && $dateCol) {
                    $q = $db->table($T_PAYMENTS)
                        ->selectRaw("DATE_FORMAT({$T_PAYMENTS}.{$dateCol}, '%Y-%m') as ym, COUNT(*) as pagos, SUM({$T_PAYMENTS}.{$amtCol}) as total, AVG({$T_PAYMENTS}.{$amtCol}) as avg_ticket")
                        ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [$from, $to]);

                    if ($mustFilterPaid && $statusCol) {
                        $q->whereIn($lowerCol($T_PAYMENTS, $statusCol), $paidStatuses);
                    }

                    if ($planFilter && $T_ACCOUNTS && $hasPlan && $accountIdCol) {
                        $q->join($T_ACCOUNTS, "{$T_ACCOUNTS}.id", '=', "{$T_PAYMENTS}.{$accountIdCol}")
                          ->whereRaw("LOWER({$T_ACCOUNTS}.plan) = ?", [$planFilter]);
                    }

                    $rows = $q->groupByRaw("DATE_FORMAT({$T_PAYMENTS}.{$dateCol}, '%Y-%m')")->get();

                    $map = [];
                    foreach ($rows as $r) {
                        if (isset($ingresos[$r->ym])) $ingresos[$r->ym] = (float) $r->total;
                        $map[$r->ym] = [
                            'total' => (float) $r->total,
                            'pagos' => (int) $r->pagos,
                            'avg'   => (float) $r->avg_ticket,
                        ];
                    }

                    // Totales rango
                    $ingresoTotalRango = array_sum(array_values($ingresos));
                    $pagosTotalRango = array_sum(array_map(fn($v) => (int)($v['pagos'] ?? 0), $map));
                   
                    // Ingreso mes actual
                    $qNow = $db->table($T_PAYMENTS)
                        ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [now()->startOfMonth(), now()->endOfMonth()]);

                    if ($statusCol) {
                        $qNow->whereIn($lowerCol($T_PAYMENTS, $statusCol), $paidStatuses);
                    }

                    if ($planFilter && $T_ACCOUNTS && $hasPlan && $hasCol($T_PAYMENTS, 'account_id')) {
                        $qNow->join($T_ACCOUNTS, "{$T_ACCOUNTS}.id", '=', "{$T_PAYMENTS}.account_id")
                            ->whereRaw("LOWER({$T_ACCOUNTS}.plan) = ?", [$planFilter]);
                    }

                    $ingresoMesActual = (float) $qNow->sum("{$T_PAYMENTS}.{$amtCol}");


                    // Tabla mensual completa (incluye meses en 0)
                    $cursor = $from->copy();
                    while ($cursor <= $to) {
                        $ym = $cursor->format('Y-m');
                        $ingresosTabla[] = [
                            'ym'    => $ym,
                            'label' => $cursor->translatedFormat('F Y'),
                            'total' => (float) ($map[$ym]['total'] ?? 0.0),
                            'pagos' => (int)   ($map[$ym]['pagos'] ?? 0),
                            'avg'   => (float) ($map[$ym]['avg'] ?? 0.0),
                        ];
                        $cursor->addMonth();
                    }
                } else {
                    $this->pushBot($bots, 'warning', 'payments_cols_missing', 'No se detectaron columnas de monto/fecha en payments.', [
                        'table' => $T_PAYMENTS,
                        'need'  => ['amount|monto', 'paid_at|created_at|fecha'],
                    ]);
                }
            } else {
                $this->pushBot($bots, 'warning', 'no_payments_table', 'No se detectó tabla de pagos (payments/pagos/cobros).');
            }

            // =========================
            // ✅ Timbres (CFDI) - por mes
            // =========================
            $timbres = $monthsMap;
            $timbresUsados = 0;

            // Importante: solo podemos contar si existe tabla cfdis en ESTA conexión.
            // Si cfdis vive en otra conexión, aquí quedará 0 (y lo reportamos en bots).
            if ($T_CFDI) {
                $cfdiDate = $hasCol($T_CFDI,'fecha') ? 'fecha' : ($hasCol($T_CFDI,'created_at') ? 'created_at' : null);
                if ($cfdiDate) {
                    $rows = $db->table($T_CFDI)
                        ->selectRaw("DATE_FORMAT({$T_CFDI}.{$cfdiDate}, '%Y-%m') as ym, COUNT(*) as c")
                        ->whereBetween("{$T_CFDI}.{$cfdiDate}", [$from, $to])
                        ->groupByRaw("DATE_FORMAT({$T_CFDI}.{$cfdiDate}, '%Y-%m')")
                        ->get();

                    foreach ($rows as $r) {
                        if (isset($timbres[$r->ym])) $timbres[$r->ym] = (int) $r->c;
                        $timbresUsados += (int) $r->c;
                    }
                } else {
                    $this->pushBot($bots, 'warning', 'cfdi_missing_date', 'Existe cfdis pero no hay columna fecha/created_at para agrupar.', [
                        'table' => $T_CFDI,
                    ]);
                }
            }

            // =========================
            // ✅ Planes (dona)
            // =========================
            $planesChart = [];
            if ($T_ACCOUNTS && $hasPlan) {
                $rows = $db->table($T_ACCOUNTS)
                    ->selectRaw("LOWER(plan) as clave, COUNT(*) as c")
                    ->groupBy('clave')
                    ->get();

                foreach ($rows as $r) {
                    $planesChart[$r->clave] = (int) $r->c;
                }
            }

            // =========================
            // ✅ Nuevos clientes por mes
            // =========================
            $nuevosClientes = array_fill(0, count($labels), 0);
            if ($T_ACCOUNTS && $hasCreated) {
                $rows = $db->table($T_ACCOUNTS)
                    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as c")
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('ym')
                    ->get();

                $map = [];
                foreach ($rows as $r) $map[$r->ym] = (int) $r->c;
                foreach ($labels as $i => $ym) $nuevosClientes[$i] = $map[$ym] ?? 0;
            }

            // =========================
            // ✅ Ingresos por plan
            // =========================
            $ingresosPorPlan = ['labels' => $labels, 'plans' => []];
            if ($T_PAYMENTS && $T_ACCOUNTS && $hasPlan && ($paymentsMeta['accountIdCol'] ?? null)) {
                $amtCol    = $paymentsMeta['amtCol'];
                $dateCol   = $paymentsMeta['dateCol'];
                $statusCol = $paymentsMeta['statusCol'];
                $accountIdCol = $paymentsMeta['accountIdCol'];

                if ($amtCol && $dateCol) {
                    $q = $db->table($T_PAYMENTS)
                        ->join($T_ACCOUNTS, "{$T_ACCOUNTS}.id", '=', "{$T_PAYMENTS}.{$accountIdCol}")
                        ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [$from, $to])
                        ->selectRaw("DATE_FORMAT({$T_PAYMENTS}.{$dateCol}, '%Y-%m') as ym, LOWER({$T_ACCOUNTS}.plan) as pkey, SUM({$T_PAYMENTS}.{$amtCol}) as total");

                    if ($mustFilterPaid && $statusCol) {
                        $q->whereIn(DB::raw("LOWER({$T_PAYMENTS}.{$statusCol})"), $paidStatuses);
                    }
                    if ($planFilter) {
                        $q->whereRaw("LOWER({$T_ACCOUNTS}.plan) = ?", [$planFilter]);
                    }

                    $rows = $q->groupBy(['ym','pkey'])->get();

                    $allPlans = collect($rows)->pluck('pkey')->unique()->values()->all();
                    foreach ($allPlans as $p) $ingresosPorPlan['plans'][$p] = array_fill(0, count($labels), 0.0);

                    foreach ($rows as $r) {
                        $idx = array_search($r->ym, $labels, true);
                        if ($idx !== false) $ingresosPorPlan['plans'][$r->pkey][$idx] = (float) $r->total;
                    }
                }
            }

            // =========================
            // ✅ Top clientes por ingresos (gráfica)
            // =========================
            $topClientes = ['labels' => [], 'values' => []];
            if ($T_PAYMENTS && $T_ACCOUNTS && ($paymentsMeta['accountIdCol'] ?? null)) {
                $amtCol    = $paymentsMeta['amtCol'];
                $dateCol   = $paymentsMeta['dateCol'];
                $statusCol = $paymentsMeta['statusCol'];
                $accountIdCol = $paymentsMeta['accountIdCol'];

                $nameCol = $hasCol($T_ACCOUNTS,'razon_social') ? 'razon_social'
                    : ($hasCol($T_ACCOUNTS,'nombre') ? 'nombre'
                    : ($hasCol($T_ACCOUNTS,'email') ? 'email' : 'id'));

                if ($amtCol && $dateCol) {
                    $q = $db->table($T_PAYMENTS)
                        ->join($T_ACCOUNTS, "{$T_ACCOUNTS}.id", '=', "{$T_PAYMENTS}.{$accountIdCol}")
                        ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [$from, $to])
                        ->selectRaw("MAX({$T_ACCOUNTS}.{$nameCol}) as cliente, SUM({$T_PAYMENTS}.{$amtCol}) as total")
                        ->groupBy("{$T_PAYMENTS}.{$accountIdCol}")
                        ->orderByDesc('total')
                        ->limit(10);

                    if ($mustFilterPaid && $statusCol) {
                        $q->whereIn(DB::raw("LOWER({$T_PAYMENTS}.{$statusCol})"), $paidStatuses);
                    }
                    if ($planFilter && $hasPlan) {
                        $q->whereRaw("LOWER({$T_ACCOUNTS}.plan) = ?", [$planFilter]);
                    }

                    $rows = $q->get();
                    foreach ($rows as $r) {
                        $topClientes['labels'][] = (string) $r->cliente;
                        $topClientes['values'][] = (float) $r->total;
                    }
                }
            }

            // Scatter ingresos vs timbres
            $scatterIncomeStamps = [];
            foreach ($labels as $ym) {
                $scatterIncomeStamps[] = [
                    'x' => (float) ($ingresos[$ym] ?? 0),
                    'y' => (int) ($timbres[$ym] ?? 0),
                    'label' => $ym,
                ];
            }

            // =========================
            // ✅ Tabla clientes (Top por ingresos en el periodo)
            //    - Nombre robusto: COALESCE(NULLIF(...))
            //    - Ingresos: SUM(payments.amount|monto) por account_id (según filtros)
            //    - Timbres por cliente: solo si cfdis tiene account_id (si no, queda 0)
            // =========================
            $clientesTabla = [];
            if ($T_ACCOUNTS) {

                // Columnas posibles para "nombre" (vamos a coalescer varias)
                $nameCandidates = [];
                foreach (['razon_social', 'name', 'nombre', 'empresa', 'email'] as $c) {
                    if ($hasCol($T_ACCOUNTS, $c)) $nameCandidates[] = "{$T_ACCOUNTS}.{$c}";
                }

                // Expresión SQL: COALESCE(NULLIF(col,''), ..., CONCAT('Cuenta #',id))
                // Si no hay ninguna columna, cae a "Cuenta #id".
                $nameExprParts = [];
                foreach ($nameCandidates as $colRef) {
                    $nameExprParts[] = "NULLIF(TRIM({$colRef}), '')";
                }
                $nameExpr = count($nameExprParts)
                    ? ("COALESCE(" . implode(', ', $nameExprParts) . ", CONCAT('Cuenta #', {$T_ACCOUNTS}.id))")
                    : ("CONCAT('Cuenta #', {$T_ACCOUNTS}.id)");

                $rfcCol = $hasCol($T_ACCOUNTS, 'rfc') ? "{$T_ACCOUNTS}.rfc" : null;

                // Estado/plan para mostrar en tabla
                $planCol   = $hasCol($T_ACCOUNTS, 'plan') ? "{$T_ACCOUNTS}.plan" : null;
                $estadoCol = $hasCol($T_ACCOUNTS, 'estado_cuenta') ? "{$T_ACCOUNTS}.estado_cuenta" : null;
                $blockedCol = $hasCol($T_ACCOUNTS, 'is_blocked') ? "{$T_ACCOUNTS}.is_blocked" : null;

                // Timestamp para “último”
                $tsCol = $hasCol($T_ACCOUNTS, 'updated_at') ? "{$T_ACCOUNTS}.updated_at"
                    : ($hasCol($T_ACCOUNTS, 'created_at') ? "{$T_ACCOUNTS}.created_at" : null);

                // ---------- Ingresos por cuenta (subquery agregada) ----------
                $incomeByAccount = null;
                if ($T_PAYMENTS && $hasCol($T_PAYMENTS, 'account_id')) {

                    $amtCol  = $hasCol($T_PAYMENTS,'monto') ? 'monto' : ($hasCol($T_PAYMENTS,'amount') ? 'amount' : null);
                    $dateCol = $hasCol($T_PAYMENTS,'paid_at') ? 'paid_at' : ($hasCol($T_PAYMENTS,'fecha') ? 'fecha' : ($hasCol($T_PAYMENTS,'created_at') ? 'created_at' : null));
                    $statusCol = $hasCol($T_PAYMENTS,'status') ? 'status' : ($hasCol($T_PAYMENTS,'estado') ? 'estado' : null);

                    if ($amtCol && $dateCol) {
                        $qInc = $db->table($T_PAYMENTS)
                            ->selectRaw("{$T_PAYMENTS}.account_id as account_id, SUM({$T_PAYMENTS}.{$amtCol}) as ingresos")
                            ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [$from, $to])
                            ->groupBy("{$T_PAYMENTS}.account_id");

                        if ($statusCol) {
                            $qInc->whereIn($lowerCol($T_PAYMENTS, $statusCol), $paidStatuses);
                        }

                        $incomeByAccount = $qInc;
                    }
                }

                // ---------- Timbres por cuenta (subquery agregada) ----------
                $stampsByAccount = null;
                if ($T_CFDI && $hasCol($T_CFDI, 'account_id')) {

                    $cfdiDate = $hasCol($T_CFDI,'fecha') ? 'fecha' : ($hasCol($T_CFDI,'created_at') ? 'created_at' : null);
                    if ($cfdiDate) {
                        $stampsByAccount = $db->table($T_CFDI)
                            ->selectRaw("{$T_CFDI}.account_id as account_id, COUNT(*) as timbres")
                            ->whereBetween("{$T_CFDI}.{$cfdiDate}", [$from, $to])
                            ->groupBy("{$T_CFDI}.account_id");
                    }
                }

                // ---------- Query final: cuentas + ingresos + timbres ----------
                $qC = $db->table($T_ACCOUNTS)
                    ->selectRaw("{$T_ACCOUNTS}.id as id, {$nameExpr} as empresa");

                if ($rfcCol)   $qC->addSelect(DB::raw("{$rfcCol} as rfc"));
                if ($planCol)  $qC->addSelect(DB::raw("{$planCol} as plan"));
                if ($estadoCol) $qC->addSelect(DB::raw("{$estadoCol} as estado_cuenta"));
                if ($blockedCol) $qC->addSelect(DB::raw("{$blockedCol} as is_blocked"));
                if ($tsCol)    $qC->addSelect(DB::raw("{$tsCol} as ts"));

                if ($incomeByAccount) {
                    $qC->leftJoinSub($incomeByAccount, 'inc', function ($j) use ($T_ACCOUNTS) {
                        $j->on('inc.account_id', '=', "{$T_ACCOUNTS}.id");
                    });
                    $qC->addSelect(DB::raw("COALESCE(inc.ingresos, 0) as ingresos"));
                } else {
                    $qC->addSelect(DB::raw("0 as ingresos"));
                }

                if ($stampsByAccount) {
                    $qC->leftJoinSub($stampsByAccount, 'st', function ($j) use ($T_ACCOUNTS) {
                        $j->on('st.account_id', '=', "{$T_ACCOUNTS}.id");
                    });
                    $qC->addSelect(DB::raw("COALESCE(st.timbres, 0) as timbres"));
                } else {
                    $qC->addSelect(DB::raw("0 as timbres"));
                }

                // Filtros: excluye bloqueados si existe is_blocked (como ya venías haciendo)
                if ($blockedCol) {
                    $qC->where("{$T_ACCOUNTS}.is_blocked", 0);
                }

                if ($planFilter && $hasPlan) {
                    $qC->whereRaw("LOWER({$T_ACCOUNTS}.plan) = ?", [$planFilter]);
                }

                // Orden: top ingresos, luego recencia
                $qC->orderByDesc('ingresos');
                if ($tsCol) $qC->orderByDesc('ts');
                else $qC->orderByDesc("{$T_ACCOUNTS}.id");

                $rows = $qC->limit(50)->get();

                foreach ($rows as $r) {
                    $planTxt = '';
                    if (isset($r->plan) && $r->plan !== null && trim((string)$r->plan) !== '') {
                        $planTxt = strtoupper(trim((string)$r->plan));
                    }

                    $estadoTxt = '';
                    if (isset($r->estado_cuenta) && $r->estado_cuenta !== null && trim((string)$r->estado_cuenta) !== '') {
                        $estadoTxt = trim((string)$r->estado_cuenta);
                    } elseif ($blockedCol) {
                        $estadoTxt = ((int)($r->is_blocked ?? 0) === 1) ? 'Bloqueada_pago' : 'Activa';
                    } else {
                        $estadoTxt = '—';
                    }

                    $planEstado = trim($planTxt . ($planTxt ? ' / ' : '') . $estadoTxt);

                    $clientesTabla[] = [
                        'id'       => (int) ($r->id ?? 0),
                        'empresa'  => (string) ($r->empresa ?? '—'),
                        'rfc'      => (string) ($r->rfc ?? ''),
                        'plan'     => $planEstado ?: '—',
                        'ingresos' => (float) ($r->ingresos ?? 0),
                        'timbres'  => (int) ($r->timbres ?? 0),
                        'ultimo'   => (!empty($r->ts) ? Carbon::parse($r->ts)->diffForHumans() : ''),
                    ];
                }
            }


            // =========================
            // ✅ KPIs CONSISTENTES (mismo rango)
            // =========================
            $arpa = 0.0;
            $ticketProm = 0.0;

            if (($activos > 0) && $ingresoTotalRango > 0) {
                $arpa = $ingresoTotalRango / $activos;
            }
            if (($pagosTotalRango > 0) && $ingresoTotalRango > 0) {
                $ticketProm = $ingresoTotalRango / $pagosTotalRango;
            }

            // =========================
            // Diagnóstico
            // =========================
            $diagnostics = [
                'tables' => [
                    'accounts' => $T_ACCOUNTS,
                    'payments' => $T_PAYMENTS,
                    'cfdi'     => $T_CFDI,
                ],
                'counts' => [
                    'accounts' => $T_ACCOUNTS ? (int) $db->table($T_ACCOUNTS)->count() : 0,
                    'payments' => $T_PAYMENTS ? (int) $db->table($T_PAYMENTS)->count() : 0,
                    'cfdi'     => $T_CFDI ? (int) $db->table($T_CFDI)->count() : 0,
                ],
                'range' => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                    'scope'=> $scope,
                    'months'=> $months,
                ],
                'warnings' => [],
            ];

            if ($T_PAYMENTS && ($diagnostics['counts']['payments'] ?? 0) === 0) {
                $this->pushBot($bots, 'info', 'payments_empty', 'No hay registros en la tabla de pagos detectada para el rango.', [
                    'table' => $T_PAYMENTS,
                    'range' => [$from->toDateString(), $to->toDateString()],
                    'scope' => $scope,
                ]);
            }

            // Payload final
            $payload = [
                'filters' => [
                    'from'   => $from->format('Y-m'),
                    'to'     => $to->format('Y-m'),
                    'months' => $months,
                    'scope'  => $scope,
                    'plan'   => $planFilter ?: 'all',
                ],
                'kpis' => [
                    'totalClientes'      => $totalClientes,
                    'activos'            => $activos,
                    'inactivos'          => $inactivos,
                    'nuevosMes'          => $nuevosMes,
                    'pendientes'         => $pendientes,
                    'premium'            => $premium,

                    // Timbres en rango
                    'timbresUsados'      => (int) $timbresUsados,

                    // Ingresos: mes actual (referencia) y rango total (KPI principal)
                    'ingresosMesActual'  => (float) $ingresoMesActual,
                    'ingresosTotal'      => (float) $ingresoTotalRango,

                    // KPI consistentes
                    'arpa'               => (float) $arpa,
                    'ticketProm'         => (float) $ticketProm,
                    'pagosTotal'         => (int) $pagosTotalRango,
                ],
                'series' => [
                    'labels'   => $labels,
                    'ingresos' => array_values($ingresos),
                    'timbres'  => array_values($timbres),
                    'planes'   => $planesChart,
                ],
                'extra' => [
                    'nuevosClientes'      => ['labels' => $labels, 'values' => $nuevosClientes],
                    'ingresosPorPlan'     => $ingresosPorPlan,
                    'topClientes'         => $topClientes,
                    'scatterIncomeStamps' => $scatterIncomeStamps,
                ],
                'ingresosTable' => $ingresosTabla,
                'clientes'      => $clientesTabla,
                'bots'          => $bots,
                '_diag'         => $diagnostics, // útil para HUD / debugging
            ];

            return [$payload, $diagnostics];
        };

        // Cache remember
        $cacheHit = false;
        try {
            if ($TTL > 0 && !$NO_CACHE) {
                [$payload, $diagnostics] = Cache::remember($cacheKey, $TTL, function () use ($compute) {
                    return $compute();
                });
                $cacheHit = true;
            } else {
                [$payload, $diagnostics] = $compute();
            }
        } catch (\Throwable $e) {
            $payload = [
                'filters' => ['from' => $fromYm, 'to' => $toYm, 'months' => $monthsLegacy, 'scope' => $scope, 'plan' => $planFilter ?: 'all'],
                'kpis'    => ['totalClientes' => 0, 'activos' => 0, 'inactivos' => 0, 'nuevosMes' => 0, 'pendientes' => 0, 'premium' => 0, 'timbresUsados' => 0, 'ingresosMesActual' => 0.0, 'ingresosTotal' => 0.0, 'arpa' => 0.0, 'ticketProm' => 0.0, 'pagosTotal' => 0],
                'series'  => ['labels' => [], 'ingresos' => [], 'timbres' => [], 'planes' => []],
                'extra'   => ['nuevosClientes' => ['labels' => [], 'values' => []], 'ingresosPorPlan' => ['labels' => [], 'plans' => []], 'topClientes' => ['labels' => [], 'values' => []], 'scatterIncomeStamps' => []],
                'ingresosTable' => [],
                'clientes' => [],
                'bots'     => [['level' => 'error', 'code' => 'stats_exception', 'text' => 'No se pudieron calcular las métricas.', 'meta' => ['error' => $e->getMessage()]]],
            ];
            $diagnostics = ['took_ms' => 0, 'tables' => [], 'counts' => [], 'warnings' => ['exception: ' . $e->getMessage()]];
        }

        $tookMs = (int) ((microtime(true) - $t0) * 1000);
        $payload['meta'] = [
            'request_id'   => $rid,
            'admin_id'     => $adminId,
            'ip'           => $ip,
            'generated_at' => now()->toIso8601String(),
            'took_ms'      => $tookMs,
            'cache'        => $cacheHit ? 'hit' : 'miss',
            'conn'         => $this->statsConn,
        ];
        $diagnostics['took_ms'] = $tookMs;

        if ($tookMs >= $WARN_MS) {
            $this->pushBot($payload['bots'], 'warning', 'slow_request', "La respuesta tardó {$tookMs} ms.", [
                'threshold_ms' => $WARN_MS,
                'hint' => 'Considera aumentar HOME_STATS_TTL o agregar índices (payments.paid_at/created_at, payments.status, payments.account_id).',
            ]);
        }

        if ($LOG_ENABLED && $LOG_QUERIES) {
            if ($queryCount >= $QUERY_WARN) {
                $this->pushBot($payload['bots'], 'warning', 'many_queries', "Se ejecutaron {$queryCount} queries.", [
                    'threshold' => $QUERY_WARN,
                    'suggest'   => 'Revisar joins/índices, considerar cache.',
                ]);
            }
            if (count($slowQueries)) {
                $this->pushBot($payload['bots'], 'warning', 'slow_queries', count($slowQueries) . ' queries lentas detectadas.', [
                    'top' => array_slice($slowQueries, 0, 3),
                ]);
            }
            $payload['meta']['query_count']  = $queryCount;
            $payload['meta']['slow_queries'] = count($slowQueries);
            if (!empty($sampleQueries)) {
                $payload['meta']['sample_queries'] = $sampleQueries;
            }
        }

        if ($LOG_ENABLED) {
            try {
                $cacheKeyLog = 'home:stats:lastlog';
                $lastAt = Cache::get($cacheKeyLog);
                if (!$lastAt || now()->diffInSeconds($lastAt) >= $LOG_EVERY) {
                    Cache::put($cacheKeyLog, now(), $LOG_EVERY);
                    Log::channel('home')->debug('[home.stats]', [
                        'rid'       => $rid,
                        'admin_id'  => $adminId,
                        'ip'        => $ip,
                        'filters'   => $payload['filters'] ?? [],
                        'tables'    => $diagnostics['tables'] ?? [],
                        'counts'    => $diagnostics['counts'] ?? [],
                        'range'     => $diagnostics['range'] ?? [],
                        'warnings'  => $diagnostics['warnings'] ?? [],
                        'took_ms'   => $tookMs,
                        'cache'     => $cacheHit ? 'hit' : 'miss',
                        'query_cnt' => $queryCount,
                        'slow_cnt'  => count($slowQueries),
                        'conn'      => $this->statsConn,
                    ]);
                }
            } catch (\Throwable $e) {}
        }

        return response()->json($payload);
    }

    /**
     * Drill-down: pagos por mes (JSON)
     */
    public function incomeByMonth(Request $request, string $ym): JsonResponse
    {
        $rid     = $request->headers->get('X-Request-Id') ?: Str::uuid()->toString();
        $adminId = Auth::guard('admin')->id();
        $ip      = $request->ip();
        $t0      = microtime(true);

        $LOG_ENABLED = filter_var(env('HOME_INCOME_LOG', false), FILTER_VALIDATE_BOOLEAN);
        $TTL         = max(0, (int) env('HOME_INCOME_TTL', 30));
        $NO_CACHE    = $request->boolean('nocache');

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) {
            return response()->json(['message' => 'Formato inválido, usa YYYY-MM'], 422);
        }

        $planFilter = trim(mb_strtolower((string) $request->string('plan')));
        if ($planFilter === 'all' || $planFilter === '') $planFilter = null;

        $scope = trim(mb_strtolower((string) $request->string('scope', 'paid')));
        if (!in_array($scope, ['paid','issued','all'], true)) $scope = 'paid';
        $mustFilterPaid = ($scope === 'paid');

        $cacheKey = sprintf('home:income:v3:conn:%s:%s:plan:%s:scope:%s', $this->statsConn, $ym, $planFilter ?: 'all', $scope);

        $compute = function () use ($ym, $planFilter, $scope, $mustFilterPaid) {
            $db  = $this->db();
            $sch = $this->schema();

            $has    = fn(string $t) => $sch->hasTable($t);
            $hasCol = fn(string $t, string $c) => $sch->hasColumn($t, $c);

            $T_ACCOUNTS = $has('accounts') ? 'accounts' : null;

            $T_PAYMENTS = null;
            if ($has('payments') && ($hasCol('payments','amount') || $hasCol('payments','monto'))) $T_PAYMENTS = 'payments';
            elseif ($has('pagos') && ($hasCol('pagos','amount') || $hasCol('pagos','monto'))) $T_PAYMENTS = 'pagos';
            elseif ($has('cobros') && ($hasCol('cobros','amount') || $hasCol('cobros','monto'))) $T_PAYMENTS = 'cobros';

            if (!$T_PAYMENTS) {
                return [
                    'ym'     => $ym,
                    'rows'   => [],
                    'totals' => ['monto' => 0, 'pagos' => 0],
                    'bots'   => [['level' => 'warning', 'code' => 'no_payments_table', 'text' => 'No existe la tabla payments/pagos/cobros.']],
                ];
            }

            $dateCol   = $hasCol($T_PAYMENTS,'paid_at') ? 'paid_at' : ($hasCol($T_PAYMENTS,'fecha') ? 'fecha' : ($hasCol($T_PAYMENTS,'created_at') ? 'created_at' : null));
            $amtCol    = $hasCol($T_PAYMENTS,'monto') ? 'monto' : ($hasCol($T_PAYMENTS,'amount') ? 'amount' : null);
            $statusCol = $hasCol($T_PAYMENTS,'status') ? 'status' : ($hasCol($T_PAYMENTS,'estado') ? 'estado' : null);

            if (!$dateCol || !$amtCol) {
                return [
                    'ym'     => $ym,
                    'rows'   => [],
                    'totals' => ['monto' => 0, 'pagos' => 0],
                    'bots'   => [['level' => 'warning', 'code' => 'missing_cols', 'text' => 'Faltan columnas de fecha o monto en payments/pagos/cobros.']],
                ];
            }

            $paidStatuses = ['paid','succeeded','success','completed','complete','captured','authorized'];

            $from = Carbon::createFromFormat('Y-m-d', $ym . '-01')->startOfMonth();
            $to   = $from->copy()->endOfMonth();

            $q = $db->table($T_PAYMENTS)
                ->select(["{$T_PAYMENTS}.id", "{$T_PAYMENTS}.{$dateCol} as fecha", "{$T_PAYMENTS}.{$amtCol} as monto"])
                ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [$from, $to]);

            if ($statusCol) {
                $q->addSelect("{$T_PAYMENTS}.{$statusCol} as estado");
                if ($mustFilterPaid) {
                    $q->whereIn(DB::raw("LOWER({$T_PAYMENTS}.{$statusCol})"), $paidStatuses);
                }
            }

            if ($T_ACCOUNTS && $hasCol($T_PAYMENTS, 'account_id')) {
                $nameCol = $hasCol($T_ACCOUNTS, 'razon_social') ? 'razon_social'
                    : ($hasCol($T_ACCOUNTS, 'nombre') ? 'nombre'
                    : ($hasCol($T_ACCOUNTS, 'email') ? 'email' : 'id'));

                $rfcCol  = $hasCol($T_ACCOUNTS, 'rfc') ? 'rfc' : null;

                $q->join($T_ACCOUNTS, "{$T_ACCOUNTS}.id", '=', "{$T_PAYMENTS}.account_id")
                  ->addSelect("{$T_ACCOUNTS}.{$nameCol} as cliente", "{$T_ACCOUNTS}.id as account_id");
                if ($rfcCol) $q->addSelect("{$T_ACCOUNTS}.{$rfcCol} as rfc");

                if ($planFilter && $hasCol($T_ACCOUNTS, 'plan')) {
                    $q->whereRaw("LOWER({$T_ACCOUNTS}.plan) = ?", [$planFilter]);
                }
            }

            if ($hasCol($T_PAYMENTS,'metodo_pago')) $q->addSelect("{$T_PAYMENTS}.metodo_pago");
            if ($hasCol($T_PAYMENTS,'referencia'))  $q->addSelect("{$T_PAYMENTS}.referencia");

            $rows = $q->orderBy("{$T_PAYMENTS}.{$dateCol}", 'asc')->limit(5000)->get();

            $totalMonto = 0.0;
            $count = 0;
            $out = [];

            foreach ($rows as $r) {
                $count++;
                $monto = (float) $r->monto;
                $totalMonto += $monto;

                $out[] = [
                    'id'         => $r->id,
                    'fecha'      => Carbon::parse($r->fecha)->format('Y-m-d H:i'),
                    'cliente'    => $r->cliente ?? (isset($r->account_id) ? ('#'.$r->account_id) : ''),
                    'rfc'        => $r->rfc ?? '',
                    'metodo'     => $r->metodo_pago ?? '',
                    'referencia' => $r->referencia ?? '',
                    'estado'     => $r->estado ?? '',
                    'monto'      => $monto,
                ];
            }

            $bots = [];
            if ($count === 0) {
                $bots[] = ['level' => 'info', 'code' => 'empty_month', 'text' => "No hay pagos para {$ym} con scope={$scope}."];
            }

            return [
                'ym'     => $ym,
                'rows'   => $out,
                'totals' => ['monto' => $totalMonto, 'pagos' => $count],
                'bots'   => $bots,
            ];
        };

        $cacheHit = false;
        try {
            if ($TTL > 0 && !$NO_CACHE) {
                $payload = Cache::remember($cacheKey, $TTL, function () use ($compute) {
                    return $compute();
                });
                $cacheHit = true;
            } else {
                $payload = $compute();
            }
        } catch (\Throwable $e) {
            $payload = [
                'ym'     => $ym,
                'rows'   => [],
                'totals' => ['monto' => 0.0, 'pagos' => 0],
                'bots'   => [['level' => 'error', 'code' => 'income_exception', 'text' => 'No se pudo obtener el detalle del mes.', 'meta' => ['error' => $e->getMessage()]]],
            ];
        }

        $tookMs = (int) ((microtime(true) - $t0) * 1000);
        $payload['meta'] = [
            'request_id'   => $rid,
            'admin_id'     => $adminId,
            'ip'           => $ip,
            'generated_at' => now()->toIso8601String(),
            'took_ms'      => $tookMs,
            'cache'        => $cacheHit ? 'hit' : 'miss',
            'conn'         => $this->statsConn,
        ];

        if ($LOG_ENABLED) {
            try {
                Log::channel('home')->debug('[home.incomeByMonth]', [
                    'rid'      => $rid,
                    'admin_id' => $adminId,
                    'ip'       => $ip,
                    'ym'       => $ym,
                    'plan'     => $planFilter ?: 'all',
                    'scope'    => $scope,
                    'rows'     => (int) ($payload['totals']['pagos'] ?? 0),
                    'amount'   => (float) ($payload['totals']['monto'] ?? 0),
                    'took_ms'  => $tookMs,
                    'cache'    => $cacheHit ? 'hit' : 'miss',
                    'conn'     => $this->statsConn,
                ]);
            } catch (\Throwable $e) {}
        }

        return response()->json($payload);
    }

    /**
     * ✅ Diario: mes actual vs promedio de 2 meses anteriores (JSON)
     */
    public function compare(Request $request, string $ym): JsonResponse
    {
        // Tu compare ya estaba bien; solo agregamos soporte scope paid|issued|all
        $rid     = $request->headers->get('X-Request-Id') ?: Str::uuid()->toString();
        $adminId = Auth::guard('admin')->id();
        $ip      = $request->ip();
        $t0      = microtime(true);

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) {
            return response()->json(['message' => 'Formato inválido, usa YYYY-MM'], 422);
        }

        $TTL      = max(0, (int) env('HOME_COMPARE_TTL', 30));
        $NO_CACHE = $request->boolean('nocache');

        $planFilter = trim(mb_strtolower((string) $request->string('plan', '')));
        if ($planFilter === 'all' || $planFilter === '') $planFilter = null;

        $scope = trim(mb_strtolower((string) $request->string('scope', 'paid')));
        if (!in_array($scope, ['paid','issued','all'], true)) $scope = 'paid';

        $cacheKey = sprintf('home:compare:v3:conn:%s:%s:plan:%s:scope:%s',
            $this->statsConn, $ym, $planFilter ?: 'all', $scope
        );

        $compute = function () use ($ym, $planFilter, $scope) {
            $db  = $this->db();
            $sch = $this->schema();

            $has    = fn(string $t) => $sch->hasTable($t);
            $hasCol = fn(string $t, string $c) => $sch->hasColumn($t, $c);

            $T_ACCOUNTS = $has('accounts') ? 'accounts' : null;

            $T_PAYMENTS = null;
            if ($has('payments') && ($hasCol('payments', 'amount') || $hasCol('payments', 'monto'))) {
                $T_PAYMENTS = 'payments';
            } elseif ($has('pagos') && ($hasCol('pagos', 'amount') || $hasCol('pagos', 'monto'))) {
                $T_PAYMENTS = 'pagos';
            } elseif ($has('cobros') && ($hasCol('cobros', 'amount') || $hasCol('cobros', 'monto'))) {
                $T_PAYMENTS = 'cobros';
            }

            if (!$T_PAYMENTS) {
                return [
                    'ym' => $ym,
                    'labels' => [],
                    'current' => [],
                    'avg_prev2' => [],
                    'bots' => [['level'=>'warning','code'=>'no_payments_table','text'=>'No se detectó tabla de pagos (payments/pagos/cobros).']],
                ];
            }

            $amtCol    = $hasCol($T_PAYMENTS,'monto') ? 'monto' : ($hasCol($T_PAYMENTS,'amount') ? 'amount' : null);
            $dateCol   = $hasCol($T_PAYMENTS,'paid_at') ? 'paid_at' : ($hasCol($T_PAYMENTS,'fecha') ? 'fecha' : ($hasCol($T_PAYMENTS,'created_at') ? 'created_at' : null));
            $statusCol = $hasCol($T_PAYMENTS,'status') ? 'status' : ($hasCol($T_PAYMENTS,'estado') ? 'estado' : null);

            if (!$amtCol || !$dateCol) {
                return [
                    'ym' => $ym,
                    'labels' => [],
                    'current' => [],
                    'avg_prev2' => [],
                    'bots' => [[
                        'level'=>'warning',
                        'code'=>'missing_cols',
                        'text'=>'Faltan columnas de fecha o monto en la tabla de pagos detectada.',
                        'meta'=>['table'=>$T_PAYMENTS,'need'=>['amount|monto','paid_at|fecha|created_at']]
                    ]],
                ];
            }

            $paidStatuses = ['paid','succeeded','success','completed','complete','captured','authorized'];
            $mustFilterPaid = ($scope === 'paid');

            $m0Start = Carbon::createFromFormat('Y-m-d', $ym.'-01')->startOfMonth();
            $m0End   = $m0Start->copy()->endOfMonth();
            $daysN   = (int) $m0Start->daysInMonth;

            $labels = [];
            for ($d=1; $d <= $daysN; $d++) $labels[] = str_pad((string)$d, 2, '0', STR_PAD_LEFT);

            $m1Start = $m0Start->copy()->subMonthNoOverflow()->startOfMonth();
            $m1End   = $m1Start->copy()->endOfMonth();
            $m2Start = $m0Start->copy()->subMonthsNoOverflow(2)->startOfMonth();
            $m2End   = $m2Start->copy()->endOfMonth();

            $dailySums = function (Carbon $from, Carbon $to) use ($db, $T_PAYMENTS, $T_ACCOUNTS, $planFilter, $statusCol, $paidStatuses, $amtCol, $dateCol, $hasCol, $mustFilterPaid) {
                $q = $db->table($T_PAYMENTS)
                    ->selectRaw("DAY({$T_PAYMENTS}.{$dateCol}) as d, SUM({$T_PAYMENTS}.{$amtCol}) as total")
                    ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [$from, $to]);

                if ($mustFilterPaid && $statusCol) {
                    $q->whereIn(DB::raw("LOWER({$T_PAYMENTS}.{$statusCol})"), $paidStatuses);
                }

                if ($planFilter && $T_ACCOUNTS && $hasCol($T_PAYMENTS,'account_id') && $hasCol($T_ACCOUNTS,'plan')) {
                    $q->join($T_ACCOUNTS, "{$T_ACCOUNTS}.id", '=', "{$T_PAYMENTS}.account_id")
                      ->whereRaw("LOWER({$T_ACCOUNTS}.plan) = ?", [$planFilter]);
                }

                $rows = $q->groupByRaw("DAY({$T_PAYMENTS}.{$dateCol})")->get();

                $map = [];
                foreach ($rows as $r) {
                    $day = (int) ($r->d ?? 0);
                    if ($day >= 1 && $day <= 31) $map[$day] = (float) ($r->total ?? 0);
                }
                return $map;
            };

            $m0 = $dailySums($m0Start, $m0End);
            $m1 = $dailySums($m1Start, $m1End);
            $m2 = $dailySums($m2Start, $m2End);

            $current = [];
            $avgPrev2 = [];

            for ($day=1; $day <= $daysN; $day++) {
                $current[] = (float) ($m0[$day] ?? 0.0);

                $vals = [];
                if (isset($m1[$day])) $vals[] = (float) $m1[$day];
                if (isset($m2[$day])) $vals[] = (float) $m2[$day];

                $avgPrev2[] = count($vals) ? array_sum($vals) / count($vals) : 0.0;
            }

            $bots = [];
            if (array_sum($current) <= 0.0) {
                $bots[] = ['level'=>'info','code'=>'empty_month','text'=>"No hay ingresos diarios para {$ym} con scope={$scope}."];
            }

            return [
                'ym' => $ym,
                'labels' => $labels,
                'current' => $current,
                'avg_prev2' => $avgPrev2,
                'bots' => $bots,
            ];
        };

        $cacheHit = false;
        try {
            if ($TTL > 0 && !$NO_CACHE) {
                $payload = Cache::remember($cacheKey, $TTL, function () use ($compute) {
                    return $compute();
                });
                $cacheHit = true;
            } else {
                $payload = $compute();
            }
        } catch (\Throwable $e) {
            $payload = [
                'ym' => $ym,
                'labels' => [],
                'current' => [],
                'avg_prev2' => [],
                'bots' => [[
                    'level'=>'error',
                    'code'=>'compare_exception',
                    'text'=>'No se pudo calcular la serie diaria comparativa.',
                    'meta'=>['error'=>$e->getMessage()]
                ]],
            ];
        }

        $tookMs = (int) ((microtime(true) - $t0) * 1000);
        $payload['meta'] = [
            'request_id'   => $rid,
            'admin_id'     => $adminId,
            'ip'           => $ip,
            'generated_at' => now()->toIso8601String(),
            'took_ms'      => $tookMs,
            'cache'        => $cacheHit ? 'hit' : 'miss',
            'conn'         => $this->statsConn,
        ];

        return response()->json($payload);
    }

    // ===== Helpers internos =====

    private function resolveRange(string $fromYm, string $toYm, int $monthsFallback): array
    {
        // Si UI no manda from/to, usamos monthsFallback (12 por defecto)
        $validYm = fn(string $s) => (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $s);

        if ($validYm($fromYm) && $validYm($toYm)) {
            $from = Carbon::createFromFormat('Y-m-d', $fromYm . '-01')->startOfMonth();
            $to   = Carbon::createFromFormat('Y-m-d', $toYm . '-01')->endOfMonth();

            if ($from->greaterThan($to)) {
                // swap
                [$from, $to] = [$to->copy()->startOfMonth(), $from->copy()->endOfMonth()];
            }

            // meses inclusivos
            $months = max(1, ($from->diffInMonths($to) + 1));
            $months = max(3, min(24, $months));

            // clamp a 24 meses si excede
            if ($months > 24) {
                $from = $to->copy()->startOfMonth()->subMonths(23);
                $months = 24;
            }

            return [$from, $to, $months];
        }

        $months = max(3, min(24, $monthsFallback));
        $from = now()->startOfMonth()->subMonths($months - 1);
        $to   = now()->endOfMonth();
        return [$from, $to, $months];
    }

    private function safeBindings($bindings): array
    {
        try {
            return collect($bindings)->map(function ($b) {
                if (is_string($b)) {
                    $s = (string) $b;
                    if (filter_var($s, FILTER_VALIDATE_EMAIL)) return '[email]';
                    if (preg_match('/[A-Za-z0-9\-_]{20,}/', $s)) return '[secret]';
                    return mb_strlen($s) > 64 ? mb_substr($s, 0, 64) . '…' : $s;
                }
                if (is_numeric($b)) return $b + 0;
                if ($b instanceof \DateTimeInterface) return $b->format('c');
                return is_scalar($b) ? $b : gettype($b);
            })->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function pushBot(array &$bots, string $level, string $code, string $text, array $meta = []): void
    {
        $bots[] = [
            'level' => $level,
            'code'  => $code,
            'text'  => $text,
            'meta'  => $meta,
        ];
    }
}
