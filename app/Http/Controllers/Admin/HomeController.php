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

        // ====== Filtros ======
        $months = max(3, min(24, (int) $request->integer('months', 12)));
        $planFilter = trim(mb_strtolower((string) $request->string('plan')));
        if ($planFilter === 'all' || $planFilter === '') $planFilter = null;

        $from = now()->startOfMonth()->subMonths($months - 1);
        $to   = now()->endOfMonth();

        // ====== Cache key (amarrada a la conexión) ======
        $cacheKey = sprintf('home:stats:v2:conn:%s:m%d:plan:%s', $this->statsConn, $months, $planFilter ?: 'all');

        $bots = [];
        $compute = function () use ($from, $to, $months, $planFilter, &$bots) {
            $db  = $this->db();
            $sch = $this->schema();

            $has    = fn(string $t) => $sch->hasTable($t);
            $hasCol = fn(string $t, string $c) => $sch->hasColumn($t, $c);

            // =========================
            // ✅ Selección de tablas REAL
            // =========================
            $T_ACCOUNTS = $has('accounts') ? 'accounts' : null;

            // Pagos: priorizar payments (porque pagos existe pero está vacío en tu caso)
            $T_PAYMENTS = null;
            if ($has('payments') && ($hasCol('payments', 'amount') || $hasCol('payments', 'monto'))) {
                $T_PAYMENTS = 'payments';
            } elseif ($has('pagos') && ($hasCol('pagos', 'amount') || $hasCol('pagos', 'monto'))) {
                $T_PAYMENTS = 'pagos';
            } elseif ($has('cobros')) {
                $T_PAYMENTS = 'cobros';
            }

            $T_CFDI   = $has('cfdis') ? 'cfdis' : null;
            $T_PLANES = $has('planes') ? 'planes' : null;

            // Helper seguro para LOWER(col) con/ sin prefijo tabla
            $lowerCol = function (?string $table, string $col) {
                return $table ? DB::raw("LOWER({$table}.{$col})") : DB::raw("LOWER({$col})");
            };

            // ===== Meses base =====
            $monthsMap = [];
            for ($d = $from->copy(); $d <= $to; $d->addMonth()) $monthsMap[$d->format('Y-m')] = 0;
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
            $ingresoMesActual = 0.0;
            $ingresosTabla = [];

            $paidStatuses = ['paid','succeeded','success','completed','complete','captured','authorized'];

            if ($T_PAYMENTS) {
                $amtCol    = $hasCol($T_PAYMENTS,'monto') ? 'monto' : ($hasCol($T_PAYMENTS,'amount') ? 'amount' : null);
                $dateCol   = $hasCol($T_PAYMENTS,'paid_at') ? 'paid_at' : ($hasCol($T_PAYMENTS,'fecha') ? 'fecha' : ($hasCol($T_PAYMENTS,'created_at') ? 'created_at' : null));
                $statusCol = $hasCol($T_PAYMENTS,'status') ? 'status' : ($hasCol($T_PAYMENTS,'estado') ? 'estado' : null);

                if ($amtCol && $dateCol) {
                    $q = $db->table($T_PAYMENTS)
                        ->selectRaw("DATE_FORMAT({$T_PAYMENTS}.{$dateCol}, '%Y-%m') as ym, COUNT(*) as pagos, SUM({$T_PAYMENTS}.{$amtCol}) as total, AVG({$T_PAYMENTS}.{$amtCol}) as avg_ticket")
                        ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [$from, $to]);

                    if ($statusCol) {
                        $q->whereIn($lowerCol($T_PAYMENTS, $statusCol), $paidStatuses);
                    }

                    if ($planFilter && $T_ACCOUNTS && $hasPlan && $hasCol($T_PAYMENTS, 'account_id')) {
                        $q->join($T_ACCOUNTS, "{$T_ACCOUNTS}.id", '=', "{$T_PAYMENTS}.account_id")
                          ->whereRaw("LOWER({$T_ACCOUNTS}.plan) = ?", [$planFilter]);
                    }

                    $rows = $q->groupByRaw("DATE_FORMAT({$T_PAYMENTS}.{$dateCol}, '%Y-%m')")->get();

                    foreach ($rows as $r) {
                        if (isset($ingresos[$r->ym])) $ingresos[$r->ym] = (float) $r->total;
                    }

                    // Ingreso mes actual
                    $qNow = $db->table($T_PAYMENTS)->whereBetween("{$T_PAYMENTS}.{$dateCol}", [now()->startOfMonth(), now()->endOfMonth()]);
                    if ($statusCol) $qNow->whereIn($lowerCol($T_PAYMENTS, $statusCol), $paidStatuses);
                    if ($planFilter && $T_ACCOUNTS && $hasPlan && $hasCol($T_PAYMENTS, 'account_id')) {
                        $qNow->join($T_ACCOUNTS, "{$T_ACCOUNTS}.id", '=', "{$T_PAYMENTS}.account_id")
                             ->whereRaw("LOWER({$T_ACCOUNTS}.plan) = ?", [$planFilter]);
                    }
                    $ingresoMesActual = (float) $qNow->sum("{$T_PAYMENTS}.{$amtCol}");

                    // Tabla mensual completa (aunque no haya rows para todos)
                    $map = [];
                    foreach ($rows as $r) {
                        $map[$r->ym] = [
                            'total' => (float) $r->total,
                            'pagos' => (int) $r->pagos,
                            'avg'   => (float) $r->avg_ticket,
                        ];
                    }
                    $cursor = $from->copy();
                    while ($cursor <= $to) {
                        $ym = $cursor->format('Y-m');
                        $ingresosTabla[] = [
                            'ym'    => $ym,
                            'label' => $cursor->translatedFormat('F Y'),
                            'total' => $map[$ym]['total'] ?? 0.0,
                            'pagos' => $map[$ym]['pagos'] ?? 0,
                            'avg'   => $map[$ym]['avg'] ?? 0.0,
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
                $this->pushBot($bots, 'warning', 'no_payments_table', 'No se detectó tabla de pagos (payments/pagos).');
            }

            // =========================
            // ✅ Timbres (CFDI)
            // =========================
            $timbres = $monthsMap;
            $timbresUsados = 0;
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
                }
            }

            // =========================
            // ✅ Clientes por plan (dona)
            // =========================
            $planesChart = [];
            $planOptions = [];
            if ($T_ACCOUNTS && $hasPlan) {
                $rows = $db->table($T_ACCOUNTS)
                    ->selectRaw("LOWER(plan) as clave, COUNT(*) as c")
                    ->groupBy('clave')
                    ->get();

                foreach ($rows as $r) {
                    $planesChart[$r->clave] = (int) $r->c;
                    $planOptions[] = ['value' => $r->clave, 'label' => ucfirst($r->clave)];
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
            if ($T_PAYMENTS && $T_ACCOUNTS && $hasPlan && $hasCol($T_PAYMENTS, 'account_id')) {
                $amtCol    = $hasCol($T_PAYMENTS,'monto') ? 'monto' : ($hasCol($T_PAYMENTS,'amount') ? 'amount' : null);
                $dateCol   = $hasCol($T_PAYMENTS,'paid_at') ? 'paid_at' : ($hasCol($T_PAYMENTS,'fecha') ? 'fecha' : ($hasCol($T_PAYMENTS,'created_at') ? 'created_at' : null));
                $statusCol = $hasCol($T_PAYMENTS,'status') ? 'status' : ($hasCol($T_PAYMENTS,'estado') ? 'estado' : null);

                if ($amtCol && $dateCol) {
                    $q = $db->table($T_PAYMENTS)
                        ->join($T_ACCOUNTS, "{$T_ACCOUNTS}.id", '=', "{$T_PAYMENTS}.account_id")
                        ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [$from, $to])
                        ->selectRaw("DATE_FORMAT({$T_PAYMENTS}.{$dateCol}, '%Y-%m') as ym, LOWER({$T_ACCOUNTS}.plan) as pkey, SUM({$T_PAYMENTS}.{$amtCol}) as total");

                    if ($statusCol) {
                        $q->whereIn(DB::raw("LOWER({$T_PAYMENTS}.{$statusCol})"), ['paid','succeeded','success','completed','complete','captured','authorized']);
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
            // ✅ Top clientes por ingresos
            // =========================
            $topClientes = ['labels' => [], 'values' => []];
            if ($T_PAYMENTS && $T_ACCOUNTS && $hasCol($T_PAYMENTS,'account_id')) {
                $amtCol    = $hasCol($T_PAYMENTS,'monto') ? 'monto' : ($hasCol($T_PAYMENTS,'amount') ? 'amount' : null);
                $dateCol   = $hasCol($T_PAYMENTS,'paid_at') ? 'paid_at' : ($hasCol($T_PAYMENTS,'fecha') ? 'fecha' : ($hasCol($T_PAYMENTS,'created_at') ? 'created_at' : null));
                $statusCol = $hasCol($T_PAYMENTS,'status') ? 'status' : ($hasCol($T_PAYMENTS,'estado') ? 'estado' : null);

                $nameCol = $hasCol($T_ACCOUNTS,'razon_social') ? 'razon_social'
                    : ($hasCol($T_ACCOUNTS,'nombre') ? 'nombre'
                    : ($hasCol($T_ACCOUNTS,'email') ? 'email' : 'id'));

                if ($amtCol && $dateCol) {
                    $q = $db->table($T_PAYMENTS)
                        ->join($T_ACCOUNTS, "{$T_ACCOUNTS}.id", '=', "{$T_PAYMENTS}.account_id")
                        ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [$from, $to])
                        ->selectRaw("MAX({$T_ACCOUNTS}.{$nameCol}) as cliente, SUM({$T_PAYMENTS}.{$amtCol}) as total")
                        ->groupBy("{$T_PAYMENTS}.account_id")
                        ->orderByDesc('total')
                        ->limit(10);

                    if ($statusCol) {
                        $q->whereIn(DB::raw("LOWER({$T_PAYMENTS}.{$statusCol})"), ['paid','succeeded','success','completed','complete','captured','authorized']);
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

            // Tabla clientes (últimos 50, activos)
            $clientesTabla = [];
            if ($T_ACCOUNTS) {
                $nameCol = $hasCol($T_ACCOUNTS,'razon_social') ? 'razon_social'
                    : ($hasCol($T_ACCOUNTS,'nombre') ? 'nombre'
                    : ($hasCol($T_ACCOUNTS,'email') ? 'email' : 'id'));

                $rfcCol  = $hasCol($T_ACCOUNTS,'rfc') ? 'rfc' : null;
                $tsCol   = $hasCol($T_ACCOUNTS,'updated_at') ? 'updated_at' : ($hasCol($T_ACCOUNTS,'created_at') ? 'created_at' : null);

                $qC = $db->table($T_ACCOUNTS)->select(["{$T_ACCOUNTS}.id", "{$T_ACCOUNTS}.{$nameCol} as nombre"]);
                if ($rfcCol) $qC->addSelect("{$T_ACCOUNTS}.{$rfcCol} as rfc");
                if ($tsCol)  $qC->addSelect("{$T_ACCOUNTS}.{$tsCol} as ts");

                if ($hasBlocked) $qC->where("{$T_ACCOUNTS}.is_blocked", 0);
                if ($planFilter && $hasPlan) $qC->whereRaw("LOWER({$T_ACCOUNTS}.plan) = ?", [$planFilter]);

                $rows = $qC->orderByDesc($tsCol ?? "{$T_ACCOUNTS}.id")->limit(50)->get();
                foreach ($rows as $r) {
                    $clientesTabla[] = [
                        'id'      => (int) $r->id,
                        'empresa' => (string) $r->nombre,
                        'rfc'     => $r->rfc ?? '',
                        'timbres' => 0,
                        'ultimo'  => isset($r->ts) ? Carbon::parse($r->ts)->diffForHumans() : '',
                        'estado'  => ($hasBlocked ? 'Activo' : '—'),
                    ];
                }
            }

            // Diagnóstico HUD
            $diagnostics = [
                'tables' => [
                    'accounts' => $T_ACCOUNTS,
                    'payments' => $T_PAYMENTS,
                    'planes'   => $T_PLANES,
                    'cfdi'     => $T_CFDI,
                ],
                'counts' => [
                    'accounts' => $T_ACCOUNTS ? (int) $db->table($T_ACCOUNTS)->count() : 0,
                    'payments' => $T_PAYMENTS ? (int) $db->table($T_PAYMENTS)->count() : 0,
                    'cfdi'     => $T_CFDI ? (int) $db->table($T_CFDI)->count() : 0,
                ],
                'warnings' => [],
            ];

            if ($T_PAYMENTS === 'pagos') {
                $this->pushBot($bots, 'warning', 'using_pagos', 'Se está usando la tabla pagos; si está vacía, los ingresos saldrán en 0. Se recomienda payments.', [
                    'hint' => 'Tu BD ya tiene payments con datos; este controlador ya prioriza payments.',
                ]);
            }

            if ($T_PAYMENTS && ($diagnostics['counts']['payments'] ?? 0) === 0) {
                $this->pushBot($bots, 'info', 'payments_empty', 'No hay registros en la tabla de pagos detectada para el periodo.', [
                    'table' => $T_PAYMENTS,
                    'period' => [$from->toDateString(), $to->toDateString()],
                ]);
            }

            $payload = [
                'filters' => [
                    'months'      => $months,
                    'plan'        => $planFilter ?: 'all',
                    'planOptions' => array_values(collect($planOptions)->unique('value')->sortBy('label')->values()->all()),
                ],
                'kpis' => [
                    'totalClientes' => $totalClientes,
                    'activos'       => $activos,
                    'inactivos'     => $inactivos,
                    'nuevosMes'     => $nuevosMes,
                    'pendientes'    => $pendientes,
                    'premium'       => $premium,
                    'timbresUsados' => $timbresUsados,
                    'ingresosMes'   => $ingresoMesActual,
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
                'filters' => ['months' => $months, 'plan' => $planFilter ?: 'all', 'planOptions' => []],
                'kpis'    => ['totalClientes' => 0, 'activos' => 0, 'inactivos' => 0, 'nuevosMes' => 0, 'pendientes' => 0, 'premium' => 0, 'timbresUsados' => 0, 'ingresosMes' => 0.0],
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
                'hint' => 'Considera aumentar HOME_STATS_TTL o agregar índices (payments.paid_at/created_at, payments.status).',
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
                        'filters'   => ['months' => $months, 'plan' => $planFilter ?: 'all'],
                        'tables'    => $diagnostics['tables'] ?? [],
                        'counts'    => $diagnostics['counts'] ?? [],
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

        $cacheKey = sprintf('home:income:v2:conn:%s:%s:plan:%s', $this->statsConn, $ym, $planFilter ?: 'all');

        $compute = function () use ($ym, $planFilter) {
            $db  = $this->db();
            $sch = $this->schema();

            $has    = fn(string $t) => $sch->hasTable($t);
            $hasCol = fn(string $t, string $c) => $sch->hasColumn($t, $c);

            $T_ACCOUNTS = $has('accounts') ? 'accounts' : null;

            $T_PAYMENTS = null;
            if ($has('payments')) $T_PAYMENTS = 'payments';
            elseif ($has('pagos')) $T_PAYMENTS = 'pagos';

            if (!$T_PAYMENTS) {
                return [
                    'ym'     => $ym,
                    'rows'   => [],
                    'totals' => ['monto' => 0, 'pagos' => 0],
                    'bots'   => [['level' => 'warning', 'code' => 'no_payments_table', 'text' => 'No existe la tabla payments/pagos.']],
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
                    'bots'   => [['level' => 'warning', 'code' => 'missing_cols', 'text' => 'Faltan columnas de fecha o monto en payments/pagos.']],
                ];
            }

            $paidStatuses = ['paid','succeeded','success','completed','complete','captured','authorized'];

            $from = Carbon::createFromFormat('Y-m-d', $ym . '-01')->startOfMonth();
            $to   = $from->copy()->endOfMonth();

            $q = $db->table($T_PAYMENTS)
                ->select(["{$T_PAYMENTS}.id", "{$T_PAYMENTS}.{$dateCol} as fecha", "{$T_PAYMENTS}.{$amtCol} as monto"])
                ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [$from, $to]);

            if ($statusCol) {
                $q->addSelect("{$T_PAYMENTS}.{$statusCol} as estado")
                  ->whereIn(DB::raw("LOWER({$T_PAYMENTS}.{$statusCol})"), $paidStatuses);
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
                $bots[] = ['level' => 'info', 'code' => 'empty_month', 'text' => "No hay pagos pagados para {$ym}."];
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
     *
     * Formato:
     * {
     *   ym: "2026-01",
     *   labels: ["01","02",...],
     *   current: [..],
     *   avg_prev2: [..],
     *   meta: {...}
     * }
     */
    public function compare(Request $request, string $ym): JsonResponse
    {
        $rid     = $request->headers->get('X-Request-Id') ?: Str::uuid()->toString();
        $adminId = Auth::guard('admin')->id();
        $ip      = $request->ip();
        $t0      = microtime(true);

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) {
            return response()->json(['message' => 'Formato inválido, usa YYYY-MM'], 422);
        }

        $TTL      = max(0, (int) env('HOME_COMPARE_TTL', 30));
        $NO_CACHE = $request->boolean('nocache');

        $planFilter = trim(mb_strtolower((string) $request->string('plan')));
        if ($planFilter === 'all' || $planFilter === '') $planFilter = null;

        $scope = trim(mb_strtolower((string) $request->string('scope', 'paid')));
        if (!in_array($scope, ['paid','issued','all'], true)) $scope = 'paid';

        $cacheKey = sprintf('home:compare:v2:conn:%s:%s:plan:%s:scope:%s',
            $this->statsConn, $ym, $planFilter ?: 'all', $scope
        );

        $compute = function () use ($ym, $planFilter, $scope, $rid) {
            $db  = $this->db();
            $sch = $this->schema();

            $has    = fn(string $t) => $sch->hasTable($t);
            $hasCol = fn(string $t, string $c) => $sch->hasColumn($t, $c);

            $T_ACCOUNTS = $has('accounts') ? 'accounts' : null;

            // Detecta payments
            $T_PAYMENTS = null;
            if ($has('payments') && ($hasCol('payments', 'amount') || $hasCol('payments', 'monto'))) {
                $T_PAYMENTS = 'payments';
            } elseif ($has('pagos') && ($hasCol('pagos', 'amount') || $hasCol('pagos', 'monto'))) {
                $T_PAYMENTS = 'pagos';
            } elseif ($has('cobros')) {
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

            // Mes target
            $m0Start = Carbon::createFromFormat('Y-m-d', $ym.'-01')->startOfMonth();
            $m0End   = $m0Start->copy()->endOfMonth();
            $daysN   = (int) $m0Start->daysInMonth;

            // Labels "01..N"
            $labels = [];
            for ($d=1; $d <= $daysN; $d++) $labels[] = str_pad((string)$d, 2, '0', STR_PAD_LEFT);

            // 2 meses previos
            $m1Start = $m0Start->copy()->subMonthNoOverflow()->startOfMonth();
            $m1End   = $m1Start->copy()->endOfMonth();
            $m2Start = $m0Start->copy()->subMonthsNoOverflow(2)->startOfMonth();
            $m2End   = $m2Start->copy()->endOfMonth();

            // Helper para daily sums por rango mensual
            $dailySums = function (Carbon $from, Carbon $to) use ($db, $T_PAYMENTS, $T_ACCOUNTS, $planFilter, $scope, $statusCol, $paidStatuses, $amtCol, $dateCol, $hasCol, $has) {
                $q = $db->table($T_PAYMENTS)
                    ->selectRaw("DAY({$T_PAYMENTS}.{$dateCol}) as d, SUM({$T_PAYMENTS}.{$amtCol}) as total")
                    ->whereBetween("{$T_PAYMENTS}.{$dateCol}", [$from, $to]);

                // scope: paid/issued/all
                if ($scope === 'paid' && $statusCol) {
                    $q->whereIn(DB::raw("LOWER({$T_PAYMENTS}.{$statusCol})"), $paidStatuses);
                }

                // plan filter
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
            $sum0 = array_sum($current);
            if ($sum0 <= 0.0) {
                $bots[] = ['level'=>'info','code'=>'empty_month','text'=>"No hay ingresos diarios para {$ym} con los filtros actuales."];
            }

            return [
                'ym' => $ym,
                'labels' => $labels,
                'current' => $current,
                'avg_prev2' => $avgPrev2,
                'bots' => $bots,
                'ranges' => [
                    'current' => [$m0Start->toDateString(), $m0End->toDateString()],
                    'prev1'   => [$m1Start->toDateString(), $m1End->toDateString()],
                    'prev2'   => [$m2Start->toDateString(), $m2End->toDateString()],
                ],
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
