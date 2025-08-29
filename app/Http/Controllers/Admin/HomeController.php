<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        return view('admin.home');
    }

    /**
     * Dashboard stats (JSON)
     * - Logs detallados con throttle (.env)
     * - Cache opcional (.env) con override ?nocache=1
     * - “Bots” (tips/alertas) en payload['bots'] sin romper tu UI
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

        // ====== Cache key ======
        $cacheKey = sprintf('home:stats:m%d:plan:%s', $months, $planFilter ?: 'all');

        // ====== Compute (con cache opcional) ======
        $bots = [];
        $compute = function () use ($from, $to, $months, $planFilter, &$bots) {

            // Helpers tabla/col
            $has    = fn(string $t)             => Schema::hasTable($t);
            $hasCol = fn(string $t, string $c)  => Schema::hasColumn($t, $c);
            $resolveTable = function (array $candidates) use ($has): ?string {
                foreach ($candidates as $t) if ($has($t)) return $t;
                return null;
            };

            // Tablas detectadas (flex)
            $T_CLIENTES = $resolveTable(['clientes', 'empresas_cliente', 'clientes_empresa', 'empresas']);
            $T_PAGOS    = $resolveTable(['pagos', 'payments', 'cobros']);
            $T_PLANES   = $resolveTable(['planes', 'subscription_plans']);
            $T_CFDI     = $resolveTable(['cfdis', 'facturas', 'comprobantes', 'facturacion']);

            // ===== Meses base =====
            $monthsMap = [];
            for ($d = $from->copy(); $d <= $to; $d->addMonth()) $monthsMap[$d->format('Y-m')] = 0;
            $labels = array_keys($monthsMap);

            // ===== Filtro por plan (join condicional) =====
            $applyPlanFilter = function ($query, string $mainTable, ?string $clienteIdCol = 'cliente_id') use ($planFilter, $T_CLIENTES, $T_PLANES, $hasCol) {
                if (!$planFilter || !$T_CLIENTES) return $query;
                if ($clienteIdCol && $hasCol($mainTable, $clienteIdCol)) {
                    $query->join($T_CLIENTES, "{$T_CLIENTES}.id", '=', "{$mainTable}.{$clienteIdCol}");
                    if ($T_PLANES && $hasCol($T_CLIENTES, 'plan_id') && $hasCol($T_PLANES, 'clave')) {
                        $query->join($T_PLANES, "{$T_PLANES}.id", '=', "{$T_CLIENTES}.plan_id")
                            ->whereRaw('LOWER('.$T_PLANES.'.clave) = ?', [$planFilter]);
                    } elseif ($hasCol($T_CLIENTES, 'plan')) {
                        $query->whereRaw('LOWER('.$T_CLIENTES.'.plan) = ?', [$planFilter]);
                    }
                }
                return $query;
            };

            // ===== KPIs =====
            $totalClientes = $T_CLIENTES ? (int) DB::table($T_CLIENTES)->count() : 0;
            $activos = ($T_CLIENTES && $hasCol($T_CLIENTES, 'activo')) ? (int) DB::table($T_CLIENTES)->where('activo',1)->count() : 0;
            $inactivos = max(0, $totalClientes - $activos);

            $nuevosMes = 0;
            if ($T_CLIENTES && $hasCol($T_CLIENTES, 'created_at')) {
                $nuevosMes = (int) DB::table($T_CLIENTES)
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count();
            }

            $premium = 0;
            if ($T_CLIENTES) {
                if ($T_PLANES && $hasCol($T_CLIENTES, 'plan_id') && $hasCol($T_PLANES, 'clave')) {
                    $premium = (int) DB::table($T_CLIENTES)
                        ->join($T_PLANES, "{$T_PLANES}.id", '=', "{$T_CLIENTES}.plan_id")
                        ->whereRaw('LOWER('.$T_PLANES.'.clave) = ?', ['premium'])
                        ->count();
                } elseif ($hasCol($T_CLIENTES, 'plan')) {
                    $premium = (int) DB::table($T_CLIENTES)->whereRaw('LOWER('.$T_CLIENTES.'.plan) = ?', ['premium'])->count();
                }
            }

            $pendientes = 0;
            if ($T_PAGOS) {
                $estadoCol = $hasCol($T_PAGOS, 'estado') ? 'estado' : ($hasCol($T_PAGOS, 'status') ? 'status' : null);
                if ($estadoCol) {
                    $pendientes = (int) DB::table($T_PAGOS)
                        ->whereIn(DB::raw("LOWER($estadoCol)"), ['pendiente','pending'])->count();
                }
            }

            // ===== Ingresos serie + tabla mensual =====
            $ingresos = $monthsMap;
            $ingresoMesActual = 0.0;
            $ingresosTabla = [];

            if ($T_PAGOS) {
                $amtCol  = $hasCol($T_PAGOS,'monto') ? 'monto' : ($hasCol($T_PAGOS,'amount') ? 'amount' : null);
                $dateCol = $hasCol($T_PAGOS,'fecha') ? 'fecha' : ($hasCol($T_PAGOS,'created_at') ? 'created_at' : null);
                if ($amtCol && $dateCol) {
                    $q = DB::table($T_PAGOS)
                        ->selectRaw("DATE_FORMAT($dateCol, '%Y-%m') as ym, COUNT(*) as pagos, SUM($amtCol) as total, AVG($amtCol) as avg_ticket")
                        ->whereBetween($dateCol, [$from, $to])
                        ->groupByRaw("DATE_FORMAT($dateCol, '%Y-%m')");
                    $q = $applyPlanFilter($q, $T_PAGOS);
                    $rows = $q->get();
                    foreach ($rows as $r) if (isset($ingresos[$r->ym])) $ingresos[$r->ym] = (float) $r->total;

                    $ingresoMesActual = (float) $applyPlanFilter(
                        DB::table($T_PAGOS)->whereBetween($dateCol, [now()->startOfMonth(), now()->endOfMonth()]),
                        $T_PAGOS
                    )->sum($amtCol);

                    $cursor = $from->copy();
                    while ($cursor <= $to) {
                        $ym = $cursor->format('Y-m');
                        $match = $rows->firstWhere('ym', $ym);
                        $ingresosTabla[] = [
                            'ym'    => $ym,
                            'label' => $cursor->translatedFormat('F Y'),
                            'total' => $match ? (float) $match->total : 0.0,
                            'pagos' => $match ? (int) $match->pagos : 0,
                            'avg'   => $match ? (float) $match->avg_ticket : 0.0,
                        ];
                        $cursor->addMonth();
                    }
                }
            }

            // ===== Timbres serie =====
            $timbres = $monthsMap;
            $timbresUsados = 0;
            if ($T_CFDI) {
                $cfdiDate = $hasCol($T_CFDI, 'fecha') ? 'fecha' : ($hasCol($T_CFDI, 'created_at') ? 'created_at' : null);
                if ($cfdiDate) {
                    $q = DB::table($T_CFDI)
                        ->selectRaw("DATE_FORMAT($cfdiDate, '%Y-%m') as ym, COUNT(*) as c")
                        ->whereBetween($cfdiDate, [$from, $to])
                        ->groupByRaw("DATE_FORMAT($cfdiDate, '%Y-%m')");
                    if ($planFilter && $hasCol($T_CFDI, 'cliente_id') && $T_CLIENTES) {
                        $q->join($T_CLIENTES, "{$T_CLIENTES}.id", '=', "{$T_CFDI}.cliente_id");
                        if ($T_PLANES && $hasCol($T_CLIENTES, 'plan_id') && $hasCol($T_PLANES,'clave')) {
                            $q->join($T_PLANES, "{$T_PLANES}.id", '=', "{$T_CLIENTES}.plan_id")
                                ->whereRaw('LOWER('.$T_PLANES.'.clave) = ?', [$planFilter]);
                        } elseif ($hasCol($T_CLIENTES, 'plan')) {
                            $q->whereRaw('LOWER('.$T_CLIENTES.'.plan) = ?', [$planFilter]);
                        }
                    }
                    $rows = $q->get();
                    foreach ($rows as $r) {
                        if (isset($timbres[$r->ym])) $timbres[$r->ym] = (int) $r->c;
                        $timbresUsados += (int) $r->c;
                    }
                }
            }

            // ===== Clientes por plan (dona) + opciones =====
            $planesChart = [];
            $planOptions = [];
            if ($T_CLIENTES) {
                if ($T_PLANES && $hasCol($T_CLIENTES,'plan_id')) {
                    $rows = DB::table($T_CLIENTES)
                        ->join($T_PLANES, "{$T_PLANES}.id", '=', "{$T_CLIENTES}.plan_id")
                        ->selectRaw('LOWER('.$T_PLANES.'.clave) as clave, COUNT(*) as c, MAX('.$T_PLANES.'.nombre) as nombre')
                        ->groupBy('clave')->get();
                    foreach ($rows as $r) {
                        $planesChart[$r->clave] = (int) $r->c;
                        $planOptions[] = ['value'=>$r->clave,'label'=>$r->nombre ?: ucfirst($r->clave)];
                    }
                } elseif ($hasCol($T_CLIENTES, 'plan')) {
                    $rows = DB::table($T_CLIENTES)->selectRaw('LOWER(plan) as clave, COUNT(*) as c')->groupBy('clave')->get();
                    foreach ($rows as $r) {
                        $planesChart[$r->clave] = (int) $r->c;
                        $planOptions[] = ['value'=>$r->clave,'label'=>ucfirst($r->clave)];
                    }
                }
            }

            // ===== Nuevos Clientes por mes (serie) =====
            $labels = array_keys($monthsMap);
            $nuevosClientes = array_fill(0, count($labels), 0);
            if ($T_CLIENTES && $hasCol($T_CLIENTES, 'created_at')) {
                $rows = DB::table($T_CLIENTES)
                    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as c")
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('ym')->get();
                $map = [];
                foreach ($rows as $r) $map[$r->ym] = (int) $r->c;
                foreach ($labels as $i => $ym) $nuevosClientes[$i] = $map[$ym] ?? 0;
            }

            // ===== Ingresos por plan (stacked) =====
            $ingresosPorPlan = ['labels' => $labels, 'plans' => []];
            if ($T_PAGOS) {
                $amtCol = $hasCol($T_PAGOS,'monto') ? 'monto' : ($hasCol($T_PAGOS,'amount') ? 'amount' : null);
                $dateCol = $hasCol($T_PAGOS,'fecha') ? 'fecha' : ($hasCol($T_PAGOS,'created_at') ? 'created_at' : null);
                if ($amtCol && $dateCol) {
                    $q = DB::table($T_PAGOS)
                        ->whereBetween($dateCol, [$from,$to])
                        ->selectRaw("DATE_FORMAT($dateCol,'%Y-%m') as ym");

                    $canJoinClientes = $T_CLIENTES && $hasCol($T_PAGOS,'cliente_id');

                    if ($canJoinClientes) {
                        $caseSql = "
                            CASE
                              WHEN ".($T_PLANES ? " {$T_PLANES}.clave IS NOT NULL " : " 1=0 ")." THEN LOWER({$T_PLANES}.clave)
                              ".($hasCol($T_CLIENTES,'plan') ? " WHEN {$T_CLIENTES}.plan IS NOT NULL THEN LOWER({$T_CLIENTES}.plan) " : "")."
                              ELSE 'sin_plan'
                            END
                        ";
                        $q->selectRaw("$caseSql as pkey")
                          ->selectRaw("SUM($amtCol) as total")
                          ->join($T_CLIENTES, "{$T_CLIENTES}.id", '=', "{$T_PAGOS}.cliente_id");

                        if ($T_PLANES && $hasCol($T_CLIENTES,'plan_id') && $hasCol($T_PLANES,'clave')) {
                            $q->leftJoin($T_PLANES, "{$T_PLANES}.id",'=',"{$T_CLIENTES}.plan_id");
                        }

                        $q = $applyPlanFilter($q, $T_PAGOS);
                        $q->groupBy(['ym','pkey']);
                    } else {
                        $q->selectRaw("'sin_plan' as pkey")
                          ->selectRaw("SUM($amtCol) as total")
                          ->groupBy('ym');
                    }

                    $rows = $q->get();
                    $allPlans = collect($rows)->pluck('pkey')->unique()->values()->all();
                    foreach ($allPlans as $p) $ingresosPorPlan['plans'][$p] = array_fill(0, count($labels), 0.0);
                    foreach ($rows as $r) {
                        $idx = array_search($r->ym, $labels, true);
                        if ($idx !== false) $ingresosPorPlan['plans'][$r->pkey][$idx] = (float) $r->total;
                    }
                }
            }

            // ===== Top 10 Clientes por ingresos =====
            $topClientes = ['labels'=>[], 'values'=>[]];
            if ($T_PAGOS && $T_CLIENTES && $hasCol($T_PAGOS,'cliente_id')) {
                $amtCol = $hasCol($T_PAGOS,'monto') ? 'monto' : ($hasCol($T_PAGOS,'amount') ? 'amount' : null);
                $dateCol = $hasCol($T_PAGOS,'fecha') ? 'fecha' : ($hasCol($T_PAGOS,'created_at') ? 'created_at' : null);
                if ($amtCol && $dateCol) {
                    $nameCol = $hasCol($T_CLIENTES,'razon_social') ? 'razon_social' : ($hasCol($T_CLIENTES,'nombre') ? 'nombre' : 'id');
                    $q = DB::table($T_PAGOS)
                        ->join($T_CLIENTES, "{$T_CLIENTES}.id", '=', "{$T_PAGOS}.cliente_id")
                        ->whereBetween($dateCol, [$from,$to])
                        ->selectRaw("MAX({$T_CLIENTES}.{$nameCol}) as cliente, SUM({$T_PAGOS}.{$amtCol}) as total")
                        ->groupBy("{$T_PAGOS}.cliente_id")
                        ->orderByDesc('total')->limit(10);
                    $q = $applyPlanFilter($q, $T_PAGOS);
                    $rows = $q->get();
                    foreach ($rows as $r) { $topClientes['labels'][] = (string) $r->cliente; $topClientes['values'][] = (float) $r->total; }
                }
            }

            // ===== Scatter (ingresos vs timbres) =====
            $scatterIncomeStamps = [];
            foreach ($labels as $ym) {
                $scatterIncomeStamps[] = ['x' => (float) ($ingresos[$ym] ?? 0), 'y' => (int) ($timbres[$ym] ?? 0), 'label' => $ym];
            }

            // ===== Tabla clientes activos =====
            $clientesTabla = [];
            $timbresPorCliente = [];

            if ($T_CLIENTES) {
                if ($T_CFDI && $hasCol($T_CFDI, 'cliente_id')) {
                    $cfdiDate = $hasCol($T_CFDI,'fecha') ? 'fecha' : ($hasCol($T_CFDI,'created_at') ? 'created_at' : null);
                    if ($cfdiDate) {
                        $qT = DB::table($T_CFDI)
                            ->selectRaw("{$T_CFDI}.cliente_id, COUNT(*) as c")
                            ->whereBetween($cfdiDate, [$from, $to])
                            ->groupBy("{$T_CFDI}.cliente_id");

                        if ($planFilter) {
                            $qT->join($T_CLIENTES, "{$T_CLIENTES}.id", '=', "{$T_CFDI}.cliente_id");
                            if ($T_PLANES && $hasCol($T_CLIENTES,'plan_id') && $hasCol($T_PLANES,'clave')) {
                                $qT->join($T_PLANES, "{$T_PLANES}.id", '=', "{$T_CLIENTES}.plan_id")
                                   ->whereRaw('LOWER('.$T_PLANES.'.clave) = ?', [$planFilter]);
                            } elseif ($hasCol($T_CLIENTES,'plan')) {
                                $qT->whereRaw('LOWER('.$T_CLIENTES.'.plan) = ?', [$planFilter]);
                            }
                        }

                        $tRows = $qT->get();
                        foreach ($tRows as $tr) $timbresPorCliente[(int) $tr->cliente_id] = (int) $tr->c;
                    }
                }

                $nameCol = $hasCol($T_CLIENTES,'razon_social') ? 'razon_social' : ($hasCol($T_CLIENTES,'nombre') ? 'nombre' : 'id');
                $rfcCol  = $hasCol($T_CLIENTES,'rfc') ? 'rfc' : null;
                $tsCol   = $hasCol($T_CLIENTES,'updated_at') ? 'updated_at' : ($hasCol($T_CLIENTES,'created_at') ? 'created_at' : null);

                $qC = DB::table($T_CLIENTES)->select(["{$T_CLIENTES}.id", "{$T_CLIENTES}.{$nameCol} as nombre"]);
                if ($rfcCol) $qC->addSelect("{$T_CLIENTES}.{$rfcCol} as rfc");
                if ($tsCol)  $qC->addSelect("{$T_CLIENTES}.{$tsCol} as ts");
                if ($hasCol($T_CLIENTES,'activo')) $qC->where("{$T_CLIENTES}.activo",1);

                if ($planFilter) {
                    if ($T_PLANES && $hasCol($T_CLIENTES,'plan_id') && $hasCol($T_PLANES,'clave')) {
                        $qC->join($T_PLANES, "{$T_PLANES}.id",'=',"{$T_CLIENTES}.plan_id")
                           ->whereRaw('LOWER('.$T_PLANES.'.clave) = ?', [$planFilter]);
                    } elseif ($hasCol($T_CLIENTES,'plan')) {
                        $qC->whereRaw('LOWER('.$T_CLIENTES.'.plan) = ?', [$planFilter]);
                    }
                }

                $rows = $qC->orderByDesc($tsCol ?? "{$T_CLIENTES}.id")->limit(50)->get();
                foreach ($rows as $r) {
                    $cid = (int) $r->id;
                    $clientesTabla[] = [
                        'id'      => $cid,
                        'empresa' => (string) ($r->nombre ?? ('#'.$cid)),
                        'rfc'     => $r->rfc ?? '',
                        'timbres' => $timbresPorCliente[$cid] ?? 0,
                        'ultimo'  => isset($r->ts) ? Carbon::parse($r->ts)->diffForHumans() : '',
                        'estado'  => 'Activo',
                    ];
                }
            }

            // ===== DIAGNÓSTICO (para HUD) =====
            $diagnostics = [
                'tables' => [
                    'clientes' => $T_CLIENTES,
                    'pagos'    => $T_PAGOS,
                    'planes'   => $T_PLANES,
                    'cfdi'     => $T_CFDI,
                ],
                'columns' => [
                    'pagos' => [
                        'cliente_id' => $T_PAGOS ? $hasCol($T_PAGOS,'cliente_id') : false,
                        'monto'      => $T_PAGOS ? ($hasCol($T_PAGOS,'monto') || $hasCol($T_PAGOS,'amount')) : false,
                        'fecha'      => $T_PAGOS ? ($hasCol($T_PAGOS,'fecha') || $hasCol($T_PAGOS,'created_at')) : false,
                    ],
                    'clientes' => [
                        'plan'       => $T_CLIENTES ? $hasCol($T_CLIENTES,'plan') : false,
                        'plan_id'    => $T_CLIENTES ? $hasCol($T_CLIENTES,'plan_id') : false,
                        'activo'     => $T_CLIENTES ? $hasCol($T_CLIENTES,'activo') : false,
                    ],
                    'planes' => [
                        'clave'      => $T_PLANES ? $hasCol($T_PLANES,'clave') : false,
                    ],
                ],
                'counts' => [
                    'clientes' => $T_CLIENTES ? (int) DB::table($T_CLIENTES)->count() : 0,
                    'pagos'    => $T_PAGOS    ? (int) DB::table($T_PAGOS)->count()    : 0,
                    'cfdi'     => $T_CFDI     ? (int) DB::table($T_CFDI)->count()     : 0,
                ],
                'warnings' => [],
            ];

            if ($T_PAGOS && !$hasCol($T_PAGOS,'cliente_id')) {
                $diagnostics['warnings'][] = 'pagos.cliente_id no existe → "Ingresos por Plan" y "Top clientes" usan fallback (sin_plan/vacío).';
                $this->pushBot($bots, 'warning', 'missing_cliente_fk', 'La tabla pagos no tiene cliente_id; algunas gráficas usan fallback.', [
                    'hint' => 'Agrega FK pagos.cliente_id → clientes.id para métricas por plan/top clientes.',
                    'sql'  => 'ALTER TABLE pagos ADD COLUMN cliente_id BIGINT NULL; -- y luego poblar/crear FK',
                ]);
            }
            // ingresos vacíos
            // (ojo: array_sum puede ser costoso, pero aquí es chico)
            // @phpstan-ignore-next-line
            if (!$T_PAGOS || !array_sum(array_values($ingresos))) {
                $diagnostics['warnings'][] = 'Serie de ingresos vacía para el periodo/columns; revisa columnas monto/fecha o datos.';
                $this->pushBot($bots, 'info', 'empty_ingresos', 'No hay datos de ingresos en el periodo/columnas detectadas.', [
                    'action' => 'Revisa columnas monto/fecha o amplía el periodo.',
                ]);
            }
            if ($planFilter && empty($planesChart[$planFilter] ?? null)) {
                $this->pushBot($bots, 'info', 'plan_sin_datos', "Sin datos para el plan '{$planFilter}' en el periodo.", [
                    'suggest' => 'Quita el filtro de plan o amplía meses.',
                ]);
            }

            // ===== RESPUESTA =====
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
                    'nuevosClientes'       => ['labels'=>$labels, 'values'=>$nuevosClientes],
                    'ingresosPorPlan'      => $ingresosPorPlan,
                    'topClientes'          => $topClientes,
                    'scatterIncomeStamps'  => $scatterIncomeStamps,
                ],
                'ingresosTable' => $ingresosTabla,
                'clientes'      => $clientesTabla,
                // “Bots” → tu front puede mostrarlos (HUD/NovaBot) o ignorarlos sin romper nada
                'bots'          => $bots,
            ];

            return [$payload, $diagnostics];
        };

        // Cache remember
        $cacheHit = false;
        if ($TTL > 0 && !$NO_CACHE) {
            [$payload, $diagnostics] = Cache::remember($cacheKey, $TTL, function () use ($compute) {
                return $compute();
            });
            $cacheHit = true;
        } else {
            [$payload, $diagnostics] = $compute();
        }

        // Meta + timings
        $tookMs = (int) ((microtime(true) - $t0) * 1000);
        $payload['meta'] = [
            'request_id' => $rid,
            'admin_id'   => $adminId,
            'ip'         => $ip,
            'generated_at' => now()->toIso8601String(),
            'took_ms'    => $tookMs,
            'cache'      => $cacheHit ? 'hit' : 'miss',
        ];
        $diagnostics['took_ms'] = $tookMs;

        // Bots de performance
        if ($tookMs >= $WARN_MS) {
            $this->pushBot($payload['bots'], 'warning', 'slow_request', "La respuesta tardó {$tookMs} ms.", [
                'threshold_ms' => $WARN_MS,
                'hint' => 'Considera aumentar HOME_STATS_TTL o agregar índices (pagos.fecha, pagos.monto).',
            ]);
        }
        if ($LOG_ENABLED && $LOG_QUERIES) {
            if ($queryCount >= $QUERY_WARN) {
                $this->pushBot($payload['bots'], 'warning', 'many_queries', "Se ejecutaron {$queryCount} queries.", [
                    'threshold' => $QUERY_WARN,
                    'suggest'   => 'Revisar joins/índices, considerar vistas materializadas o cache.',
                ]);
            }
            if (count($slowQueries)) {
                $this->pushBot($payload['bots'], 'warning', 'slow_queries', count($slowQueries).' queries lentas detectadas.', [
                    'top' => array_slice($slowQueries, 0, 3),
                ]);
            }
            $payload['meta']['query_count']   = $queryCount;
            $payload['meta']['slow_queries']  = count($slowQueries);
            if (!empty($sampleQueries)) {
                $payload['meta']['sample_queries'] = $sampleQueries;
            }
        }

        // ===== LOG (opt-in con throttle) =====
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
                        'filters'   => ['months'=>$months, 'plan'=>$planFilter ?: 'all'],
                        'tables'    => $diagnostics['tables'],
                        'columns'   => $diagnostics['columns'],
                        'counts'    => $diagnostics['counts'],
                        'warnings'  => $diagnostics['warnings'],
                        'took_ms'   => $tookMs,
                        'cache'     => $cacheHit ? 'hit' : 'miss',
                        'query_cnt' => $queryCount,
                        'slow_cnt'  => count($slowQueries),
                    ]);
                }
            } catch (\Throwable $e) {
                // nunca romper por logging
            }
        }

        return response()->json($payload);
    }

    /**
     * Drill-down: pagos por mes (JSON)
     * - Cache opcional (.env) con override ?nocache=1
     * - Logs opcionales
     */
    public function incomeByMonth(Request $request, string $ym): JsonResponse
    {
        $rid     = $request->headers->get('X-Request-Id') ?: Str::uuid()->toString();
        $adminId = Auth::guard('admin')->id();
        $ip      = $request->ip();
        $t0      = microtime(true);

        $LOG_ENABLED = filter_var(env('HOME_INCOME_LOG', false), FILTER_VALIDATE_BOOLEAN);
        $TTL         = max(0, (int) env('HOME_INCOME_TTL', 30));
        $SLOW_MS     = max(1, (int) env('HOME_INCOME_SLOW_MS', 250));
        $NO_CACHE    = $request->boolean('nocache');

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) {
            return response()->json(['message' => 'Formato inválido, usa YYYY-MM'], 422);
        }

        $planFilter = trim(mb_strtolower((string) $request->string('plan')));
        if ($planFilter === 'all' || $planFilter === '') $planFilter = null;

        $cacheKey = sprintf('home:income:%s:plan:%s', $ym, $planFilter ?: 'all');

        $compute = function () use ($ym, $planFilter) {

            $has    = fn(string $t)             => Schema::hasTable($t);
            $hasCol = fn(string $t, string $c)  => Schema::hasColumn($t, $c);
            $resolveTable = function (array $cands) use ($has): ?string {
                foreach ($cands as $t) if ($has($t)) return $t; return null;
            };

            $T_CLIENTES = $resolveTable(['clientes', 'empresas_cliente', 'clientes_empresa', 'empresas']);
            $T_PAGOS    = $resolveTable(['pagos', 'payments', 'cobros']);
            $T_PLANES   = $resolveTable(['planes', 'subscription_plans']);

            if (!$T_PAGOS) return ['rows'=>[], 'totals'=>['monto'=>0,'pagos'=>0], 'bots'=>[
                ['level'=>'warning','code'=>'no_pagos_table','text'=>'No existe la tabla de pagos.']
            ]];

            $dateCol = $hasCol($T_PAGOS,'fecha') ? 'fecha' : ($hasCol($T_PAGOS,'created_at') ? 'created_at' : null);
            $amtCol  = $hasCol($T_PAGOS,'monto') ? 'monto' : ($hasCol($T_PAGOS,'amount') ? 'amount' : null);
            $estado  = $hasCol($T_PAGOS,'estado') ? 'estado' : ($hasCol($T_PAGOS,'status') ? 'status' : null);
            if (!$dateCol || !$amtCol) return ['rows'=>[], 'totals'=>['monto'=>0,'pagos'=>0], 'bots'=>[
                ['level'=>'warning','code'=>'missing_cols','text'=>'Faltan columnas de fecha o monto en pagos.']
            ]];

            $from = Carbon::createFromFormat('Y-m-d', $ym.'-01')->startOfMonth();
            $to   = $from->copy()->endOfMonth();

            $q = DB::table($T_PAGOS)
                ->select(["{$T_PAGOS}.id", "{$T_PAGOS}.{$dateCol} as fecha", "{$T_PAGOS}.{$amtCol} as monto"])
                ->whereBetween($dateCol, [$from, $to]);
            if ($estado) $q->addSelect("{$T_PAGOS}.{$estado} as estado");

            if ($T_CLIENTES && $hasCol($T_PAGOS,'cliente_id')) {
                $nameCol = $hasCol($T_CLIENTES,'razon_social') ? 'razon_social' : ($hasCol($T_CLIENTES,'nombre') ? 'nombre' : 'id');
                $rfcCol  = $hasCol($T_CLIENTES,'rfc') ? 'rfc' : null;
                $q->join($T_CLIENTES, "{$T_CLIENTES}.id",'=',"{$T_PAGOS}.cliente_id")
                  ->addSelect("{$T_CLIENTES}.{$nameCol} as cliente", "{$T_CLIENTES}.id as cliente_id");
                if ($rfcCol) $q->addSelect("{$T_CLIENTES}.{$rfcCol} as rfc");

                if ($planFilter) {
                    if ($T_PLANES && $hasCol($T_CLIENTES,'plan_id') && $hasCol($T_PLANES,'clave')) {
                        $q->join($T_PLANES, "{$T_PLANES}.id",'=',"{$T_CLIENTES}.plan_id")
                          ->whereRaw('LOWER('.$T_PLANES.'.clave) = ?', [$planFilter]);
                    } elseif ($hasCol($T_CLIENTES,'plan')) {
                        $q->whereRaw('LOWER('.$T_CLIENTES.'.plan) = ?', [$planFilter]);
                    }
                }
            }

            if ($hasCol($T_PAGOS,'metodo_pago')) $q->addSelect("{$T_PAGOS}.metodo_pago");
            if ($hasCol($T_PAGOS,'referencia'))  $q->addSelect("{$T_PAGOS}.referencia");

            $rows = $q->orderBy($dateCol,'asc')->limit(2000)->get();

            $totalMonto = 0.0; $count = 0; $out = [];
            foreach ($rows as $r) {
                $count++;
                $monto = (float) $r->monto;
                $totalMonto += $monto;
                $out[] = [
                    'id'         => $r->id,
                    'fecha'      => Carbon::parse($r->fecha)->format('Y-m-d H:i'),
                    'cliente'    => $r->cliente ?? (isset($r->cliente_id) ? ('#'.$r->cliente_id) : ''),
                    'rfc'        => $r->rfc ?? '',
                    'metodo'     => $r->metodo_pago ?? '',
                    'referencia' => $r->referencia ?? '',
                    'estado'     => $r->estado ?? '',
                    'monto'      => $monto,
                ];
            }

            $bots = [];
            if ($count === 0) {
                $bots[] = ['level'=>'info','code'=>'empty_month','text'=>"No hay pagos para {$ym}. Considera revisar filtros/periodo."];
            }

            return [
                'ym'     => $ym,
                'rows'   => $out,
                'totals' => ['monto'=>$totalMonto, 'pagos'=>$count],
                'bots'   => $bots,
            ];
        };

        $cacheHit = false;
        if ($TTL > 0 && !$NO_CACHE) {
            $payload = Cache::remember($cacheKey, $TTL, function () use ($compute) {
                return $compute();
            });
            $cacheHit = true;
        } else {
            $payload = $compute();
        }

        // Meta + log
        $tookMs = (int) ((microtime(true) - $t0) * 1000);
        $payload['meta'] = [
            'request_id' => $rid,
            'admin_id'   => $adminId,
            'ip'         => $ip,
            'generated_at' => now()->toIso8601String(),
            'took_ms'    => $tookMs,
            'cache'      => $cacheHit ? 'hit' : 'miss',
        ];

        if ($LOG_ENABLED) {
            try {
                Log::channel('home')->debug('[home.incomeByMonth]', [
                    'rid'       => $rid,
                    'admin_id'  => $adminId,
                    'ip'        => $ip,
                    'ym'        => $ym,
                    'plan'      => $planFilter ?: 'all',
                    'rows'      => (int) ($payload['totals']['pagos'] ?? 0),
                    'amount'    => (float) ($payload['totals']['monto'] ?? 0),
                    'took_ms'   => $tookMs,
                    'cache'     => $cacheHit ? 'hit' : 'miss',
                ]);
            } catch (\Throwable $e) {
                // no romper por logging
            }
        }

        return response()->json($payload);
    }

    // ===== Helpers internos =====

    /** Normaliza bindings para no exponer datos sensibles en logs */
    private function safeBindings($bindings): array
    {
        try {
            return collect($bindings)->map(function ($b) {
                if (is_string($b)) {
                    $s = (string) $b;
                    // ofusca posibles tokens/correos
                    if (filter_var($s, FILTER_VALIDATE_EMAIL)) return '[email]';
                    if (preg_match('/[A-Za-z0-9\-_]{20,}/', $s)) return '[secret]';
                    return mb_strlen($s) > 64 ? mb_substr($s, 0, 64).'…' : $s;
                }
                if (is_numeric($b)) return $b + 0;
                if ($b instanceof \DateTimeInterface) return $b->format('c');
                return is_scalar($b) ? $b : gettype($b);
            })->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Agrega un “bot” (alerta/sugerencia) al arreglo destino */
    private function pushBot(array &$bots, string $level, string $code, string $text, array $meta = []): void
    {
        $bots[] = [
            'level' => $level,   // info | warning | error
            'code'  => $code,    // clave corta para tu HUD/NovaBot
            'text'  => $text,    // mensaje legible
            'meta'  => $meta,    // datos opcionales (sql sugerida, thresholds, etc.)
        ];
    }
}
