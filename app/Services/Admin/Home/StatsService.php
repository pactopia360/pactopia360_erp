<?php

namespace App\Services\Admin\Home;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Servicio de estadísticas para el Home Admin (robusto).
 * - Tolera nombres de columnas/tables distintos (estado/estatus, fecha_emision/created_at, etc.)
 * - Si algo falta, devuelve estructuras vacías válidas (no rompe el frontend).
 */
class StatsService
{
    protected $dbc; // mysql_clientes
    protected $dba; // mysql_admin

    public function __construct()
    {
        // Si no existen las conexiones nombradas, cae en 'mysql' por compatibilidad
        $this->dbc = DB::connection(config('database.connections.mysql_clientes') ? 'mysql_clientes' : 'mysql');
        $this->dba = DB::connection(config('database.connections.mysql_admin') ? 'mysql_admin' : 'mysql');
    }

    /* ============================ Helpers de esquema ============================ */

    protected function tableExists(string $conn, string $table): bool
    {
        try { return Schema::connection($conn)->hasTable($table); }
        catch (\Throwable $e) { return false; }
    }

    /** Devuelve la PRIMERA columna del listado que exista en la tabla */
    protected function firstColumn(string $conn, string $table, array $candidates, ?string $fallback = null): ?string
    {
        try {
            foreach ($candidates as $c) {
                if (Schema::connection($conn)->hasColumn($table, $c)) return $c;
            }
        } catch (\Throwable $e) { /* noop */ }
        return $fallback;
    }

    /** Últimos N meses: [keys YYYY-MM, labels “MMM YYYY”] */
    protected function lastNMonthsLabels(int $n = 12): array
    {
        $now = CarbonImmutable::now();
        $ym = []; $label = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $d = $now->subMonths($i);
            $ym[] = $d->format('Y-m');
            $label[] = $d->isoFormat('MMM YYYY');
        }
        return [$ym, $label];
    }

    /** Etiquetas 01..dd para un mes YYYY-MM */
    protected function daysOfMonthLabels(string $ym): array
    {
        try { [$y,$m] = explode('-', $ym); $d = CarbonImmutable::createFromDate((int)$y,(int)$m,1); }
        catch (\Throwable $e) { $d = CarbonImmutable::now()->startOfMonth(); }
        return array_map(fn($i)=>str_pad((string)$i,2,'0',STR_PAD_LEFT), range(1,$d->daysInMonth));
    }

    /** SUMA por mes defensiva */
    protected function sumByMonth(string $conn, string $table, ?string $dateCol, ?string $amountCol, int $n = 12): array
    {
        [$ymKeys, $labels] = $this->lastNMonthsLabels($n);
        $out = array_fill_keys($ymKeys, 0.0);
        if (!$this->tableExists($conn,$table) || !$dateCol || !$amountCol) {
            return ['labels'=>$labels,'values'=>array_values($out)];
        }

        try {
            $rows = DB::connection($conn)->table($table)
                ->selectRaw("DATE_FORMAT($dateCol, '%Y-%m') as ym, SUM($amountCol) as total")
                ->where($dateCol, '>=', Carbon::now()->subMonths($n-1)->startOfMonth())
                ->groupBy('ym')->get();
            foreach ($rows as $r) if (isset($out[$r->ym])) $out[$r->ym] = (float)$r->total;
        } catch (\Throwable $e) {
            Log::warning('Stats.sumByMonth', ['table'=>$table,'e'=>$e->getMessage()]);
        }
        return ['labels'=>$labels,'values'=>array_values($out)];
    }

    /** SUMA por día del mes defensiva */
    protected function sumByDayOfMonth(string $conn, string $table, ?string $dateCol, ?string $amountCol, string $ym): array
    {
        $labels = $this->daysOfMonthLabels($ym);
        $out = array_fill(0, count($labels), 0.0);
        if (!$this->tableExists($conn,$table) || !$dateCol || !$amountCol) {
            return ['labels'=>$labels,'values'=>$out];
        }

        try {
            [$y,$m] = explode('-', $ym);
            $from = Carbon::create((int)$y,(int)$m,1)->startOfDay();
            $to   = (clone $from)->endOfMonth();

            $rows = DB::connection($conn)->table($table)
                ->selectRaw("DAY($dateCol) as d, SUM($amountCol) as total")
                ->whereBetween($dateCol, [$from,$to])
                ->groupBy('d')->get();

            foreach ($rows as $r) {
                $idx = max(1, (int)$r->d) - 1;
                if ($idx >= 0 && $idx < count($out)) $out[$idx] = (float)$r->total;
            }
        } catch (\Throwable $e) {
            Log::warning('Stats.sumByDayOfMonth', ['table'=>$table,'e'=>$e->getMessage()]);
        }

        return ['labels'=>$labels,'values'=>$out];
    }

    /** Conteo por mes defensivo (altas clientes) */
    protected function countByMonth(string $conn, string $table, ?string $dateCol, int $n = 12): array
    {
        [$ymKeys, $labels] = $this->lastNMonthsLabels($n);
        $out = array_fill_keys($ymKeys, 0);

        if (!$this->tableExists($conn,$table) || !$dateCol) {
            return collect($labels)->map(fn($l)=>['label'=>$l,'count'=>0])->all();
        }

        try {
            $rows = DB::connection($conn)->table($table)
                ->selectRaw("DATE_FORMAT($dateCol, '%Y-%m') as ym, COUNT(*) as c")
                ->where($dateCol, '>=', Carbon::now()->subMonths($n-1)->startOfMonth())
                ->groupBy('ym')->get();
            foreach ($rows as $r) if (isset($out[$r->ym])) $out[$r->ym] = (int)$r->c;
        } catch (\Throwable $e) {
            Log::warning('Stats.countByMonth', ['table'=>$table,'e'=>$e->getMessage()]);
        }

        $i = 0;
        return collect($out)->map(fn($v,$k)=>['label'=>$labels[$i++],'count'=>$v])->values()->all();
    }

    /* ============================ Secciones dashboard ============================ */

    public function summary(): array
    {
        $total=0; $activos=0; $ingMes=0.0; $movHoy=0;

        // clientes
        if ($this->tableExists('mysql_clientes','clientes')) {
            try { $total = (int)$this->dbc->table('clientes')->count(); } catch (\Throwable $e) {}

            try {
                $statusCol = $this->firstColumn('mysql_clientes','clientes',['estatus','status','estado']);
                if ($statusCol) {
                    $activos = (int)$this->dbc->table('clientes')->where(function ($q) use ($statusCol) {
                        $q->whereIn($statusCol, ['activo','ACTIVO','Activo','A','ACT'])
                          ->orWhere($statusCol, 1)->orWhere($statusCol, '1');
                    })->count();
                }
            } catch (\Throwable $e) {}
        }

        // ingresos del mes (pagos/ingresos/facturas)
        $ymStart = Carbon::now()->startOfMonth();
        foreach ([['pagos','fecha_pago','monto'], ['ingresos','fecha','monto'], ['facturas','fecha','total']] as $t) {
            [$table,$dateC,$amtC] = $t;
            if ($this->tableExists('mysql_clientes',$table)) {
                $dateCol = $this->firstColumn('mysql_clientes',$table, [$dateC,'created_at','fecha_emision']);
                $amtCol  = $this->firstColumn('mysql_clientes',$table, [$amtC,'importe','subtotal','total']);
                if ($dateCol && $amtCol) {
                    try {
                        $ingMes += (float)$this->dbc->table($table)->where($dateCol,'>=',$ymStart)->sum($amtCol);
                    } catch (\Throwable $e) {}
                    break;
                }
            }
        }

        // movimientos hoy
        foreach ([['movimientos','fecha'],['pagos','fecha_pago'],['facturas','fecha']] as $t) {
            [$table,$dateC] = $t;
            if ($this->tableExists('mysql_clientes',$table)) {
                $dateCol = $this->firstColumn('mysql_clientes',$table, [$dateC,'created_at']);
                if ($dateCol) {
                    try {
                        $movHoy = (int)$this->dbc->table($table)->whereDate($dateCol, Carbon::today())->count();
                        break;
                    } catch (\Throwable $e) {}
                }
            }
        }

        return [
            'clientesTotal'   => $total,
            'clientesActivos' => $activos,
            'ingresosMes'     => round($ingMes,2),
            'movimientosHoy'  => $movHoy,
        ];
    }

    public function incomeLast12(): array
    {
        foreach ([['pagos','fecha_pago','monto'],['ingresos','fecha','monto'],['facturas','fecha','total']] as $t) {
            [$table,$dateC,$amtC] = $t;
            if ($this->tableExists('mysql_clientes',$table)) {
                $dateCol = $this->firstColumn('mysql_clientes',$table, [$dateC,'created_at','fecha_emision']);
                $amtCol  = $this->firstColumn('mysql_clientes',$table, [$amtC,'importe','subtotal','total']);
                return $this->sumByMonth('mysql_clientes', $table, $dateCol, $amtCol, 12);
            }
        }
        [$_,$labels] = $this->lastNMonthsLabels(12);
        return ['labels'=>$labels,'values'=>array_fill(0,12,0)];
    }

    public function clientsLast12(): array
    {
        if ($this->tableExists('mysql_clientes','clientes')) {
            $dateCol = $this->firstColumn('mysql_clientes','clientes',['created_at','fecha_alta','alta']);
            return $this->countByMonth('mysql_clientes','clientes',$dateCol,12);
        }
        [$_,$labels] = $this->lastNMonthsLabels(12);
        return collect($labels)->map(fn($l)=>['label'=>$l,'count'=>0])->all();
    }

    public function pvfForMonth(?string $ym=null): array
    {
        $ym = $ym ?: Carbon::now()->format('Y-m');

        // pagos (prioriza pagos/ingresos)
        $pagos = ['labels'=>$this->daysOfMonthLabels($ym),'values'=>array_fill(0, count($this->daysOfMonthLabels($ym)),0)];
        foreach ([['pagos','fecha_pago','monto'],['ingresos','fecha','monto']] as $t) {
            [$table,$dateC,$amtC] = $t;
            if ($this->tableExists('mysql_clientes',$table)) {
                $dateCol = $this->firstColumn('mysql_clientes',$table, [$dateC,'created_at']);
                $amtCol  = $this->firstColumn('mysql_clientes',$table, [$amtC,'importe','subtotal','total']);
                $pagos   = $this->sumByDayOfMonth('mysql_clientes',$table,$dateCol,$amtCol,$ym);
                break;
            }
        }

        // facturas
        $facturas = ['labels'=>$pagos['labels'],'values'=>array_fill(0, count($pagos['labels']),0)];
        if ($this->tableExists('mysql_clientes','facturas')) {
            $dateCol = $this->firstColumn('mysql_clientes','facturas',['fecha','fecha_emision','created_at']);
            $amtCol  = $this->firstColumn('mysql_clientes','facturas',['total','monto','importe','subtotal']);
            $facturas = $this->sumByDayOfMonth('mysql_clientes','facturas',$dateCol,$amtCol,$ym);
        }

        return ['labels'=>$pagos['labels'], 'pagos'=>$pagos['values'], 'facturas'=>$facturas['values']];
    }

    public function licenciasPie(): array
    {
        if (!$this->tableExists('mysql_clientes','clientes')) return ['labels'=>[],'values'=>[]];
        $col = $this->firstColumn('mysql_clientes','clientes',['estatus','status','estado']);
        if (!$col) return ['labels'=>[],'values'=>[]];

        try {
            $rows = $this->dbc->table('clientes')->select($col.' as k', DB::raw('COUNT(*) as c'))->groupBy('k')->get();
            return ['labels'=>$rows->pluck('k')->map(fn($x)=>(string)$x)->all(), 'values'=>$rows->pluck('c')->map(fn($x)=>(int)$x)->all()];
        } catch (\Throwable $e) { return ['labels'=>[],'values'=>[]]; }
    }

    public function planesPie(): array
    {
        if (!$this->tableExists('mysql_clientes','clientes')) return ['labels'=>[],'values'=>[]];
        $col = $this->firstColumn('mysql_clientes','clientes',['plan','plan_nombre','plan_id']);
        if (!$col) return ['labels'=>[],'values'=>[]];

        try {
            $rows = $this->dbc->table('clientes')->select($col.' as k', DB::raw('COUNT(*) as c'))->groupBy('k')->get();
            return ['labels'=>$rows->pluck('k')->map(fn($x)=>(string)$x)->all(), 'values'=>$rows->pluck('c')->map(fn($x)=>(int)$x)->all()];
        } catch (\Throwable $e) { return ['labels'=>[],'values'=>[]]; }
    }

    public function spark14(): array
    {
        $labels=[]; $values=[];
        for ($i=13;$i>=0;$i--) { $d=Carbon::today()->subDays($i); $labels[]=$d->isoFormat('DD/MM'); $values[]=0.0; }

        foreach ([['pagos','fecha_pago','monto'],['facturas','fecha','total']] as $t) {
            [$table,$dateC,$amtC] = $t;
            if (!$this->tableExists('mysql_clientes',$table)) continue;
            $dateCol = $this->firstColumn('mysql_clientes',$table, [$dateC,'created_at','fecha_emision']);
            $amtCol  = $this->firstColumn('mysql_clientes',$table, [$amtC,'importe','subtotal','total']);
            if (!$dateCol || !$amtCol) continue;

            try {
                $rows = $this->dbc->table($table)
                    ->selectRaw("DATE($dateCol) as d, SUM($amtCol) as t")
                    ->where($dateCol,'>=',Carbon::today()->subDays(13))
                    ->groupBy('d')->get();

                $map=[]; foreach ($rows as $r) $map[(string)$r->d]=(float)$r->t;
                foreach ($labels as $i=>$lab) {
                    $dt = Carbon::today()->subDays(13-$i)->toDateString();
                    $values[$i] += $map[$dt] ?? 0.0;
                }
            } catch (\Throwable $e) {}
        }

        return ['labels'=>$labels,'values'=>$values];
    }

    public function hitsHeatmap(int $weeks = 6): array
    {
        $labelsX = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
        $labelsY = []; $cells=[]; $max=0;

        if ($this->tableExists('mysql_clientes','facturas')) {
            $dateCol = $this->firstColumn('mysql_clientes','facturas',['fecha','fecha_emision','created_at']);
            if ($dateCol) {
                $start = Carbon::today()->subWeeks($weeks-1)->startOfWeek(Carbon::MONDAY);
                $end   = Carbon::today()->endOfWeek(Carbon::SUNDAY);

                for ($w=0;$w<$weeks;$w++) { $labelsY[] = 'W'.$start->copy()->addWeeks($w)->weekOfYear; }

                try {
                    $rows = $this->dbc->table('facturas')
                        ->selectRaw("YEARWEEK($dateCol, 3) as yw, WEEKDAY($dateCol) as wd, COUNT(*) as c")
                        ->whereBetween($dateCol, [$start,$end])
                        ->groupBy('yw','wd')->get();

                    $map=[];
                    foreach ($rows as $r) { $map[$r->yw][$r->wd]=(int)$r->c; }

                    for ($w=0;$w<$weeks;$w++) {
                        $yw = $start->copy()->addWeeks($w)->format('oW');
                        foreach (range(0,6) as $wd) {
                            $v = $map[$yw][$wd] ?? 0;
                            $cells[] = ['x'=>$wd,'y'=>$labelsY[$w],'v'=>$v];
                            $max = max($max,$v);
                        }
                    }
                } catch (\Throwable $e) { /* noop */ }
            }
        }

        return ['xLabels'=>$labelsX,'yLabels'=>$labelsY,'cells'=>$cells,'max'=>$max];
    }

    public function modulesTop(int $months = 6): array
    {
        if (!$this->tableExists('mysql_clientes','activaciones_modulo')) return ['labels'=>[], 'values'=>[]];
        $dateCol = $this->firstColumn('mysql_clientes','activaciones_modulo',['fecha','created_at']);
        $modCol  = $this->firstColumn('mysql_clientes','activaciones_modulo',['modulo','nombre_modulo','slug']);
        if (!$dateCol || !$modCol) return ['labels'=>[], 'values'=>[]];

        try {
            $rows = $this->dbc->table('activaciones_modulo')
                ->select($modCol.' as m', DB::raw('COUNT(*) as c'))
                ->where($dateCol,'>=',Carbon::now()->subMonths($months)->startOfDay())
                ->groupBy('m')->orderByDesc('c')->limit(10)->get();
            return ['labels'=>$rows->pluck('m')->map(fn($x)=>(string)$x)->all(), 'values'=>$rows->pluck('c')->map(fn($x)=>(int)$x)->all()];
        } catch (\Throwable $e) { return ['labels'=>[], 'values'=>[]]; }
    }

    public function plansBreakdown(int $months = 6): array
    {
        if (!$this->tableExists('mysql_clientes','clientes')) return ['labels'=>[], 'series'=>[]];
        $dateCol = $this->firstColumn('mysql_clientes','clientes',['created_at','fecha_alta','alta']);
        $planCol = $this->firstColumn('mysql_clientes','clientes',['plan','plan_nombre','plan_id']);
        if (!$dateCol || !$planCol) return ['labels'=>[], 'series'=>[]];

        [$ymKeys,$labels] = $this->lastNMonthsLabels($months);
        $series=[];

        try {
            $rows = $this->dbc->table('clientes')
                ->selectRaw("DATE_FORMAT($dateCol, '%Y-%m') as ym, $planCol as p, COUNT(*) as c")
                ->where($dateCol,'>=',Carbon::now()->subMonths($months-1)->startOfMonth())
                ->groupBy('ym','p')->get();

            foreach ($rows as $r) {
                $p = (string)($r->p ?? '—');
                $idx = array_search($r->ym, $ymKeys, true);
                if ($idx === false) continue;
                $series[$p] = $series[$p] ?? array_fill(0,$months,0);
                $series[$p][$idx] = (int)$r->c;
            }
        } catch (\Throwable $e) {}

        $out=[]; foreach ($series as $plan=>$arr) $out[]=['label'=>$plan,'data'=>array_values($arr)];
        return ['labels'=>$labels,'series'=>$out];
    }

    public function movimientos(int $limit = 10): array
    {
        if ($this->tableExists('mysql_clientes','movimientos')) {
            $dateCol=$this->firstColumn('mysql_clientes','movimientos',['fecha','created_at']);
            $cliCol =$this->firstColumn('mysql_clientes','movimientos',['cliente','razon_social','nombre_cliente']);
            $descCol=$this->firstColumn('mysql_clientes','movimientos',['concepto','descripcion','detalle']);
            $amtCol =$this->firstColumn('mysql_clientes','movimientos',['monto','importe','total']);
            if ($dateCol && $cliCol && $descCol && $amtCol) {
                $rows=$this->dbc->table('movimientos')
                    ->selectRaw("$dateCol as fecha, $descCol as concepto, $cliCol as cliente, $amtCol as monto")
                    ->orderByDesc($dateCol)->limit($limit)->get();
                return $rows->map(fn($r)=>[
                    'fecha'=>(string)$r->fecha,'concepto'=>(string)$r->concepto,'cliente'=>(string)$r->cliente,'monto'=>(float)$r->monto
                ])->all();
            }
        }

        if ($this->tableExists('mysql_clientes','pagos')) {
            $dateCol=$this->firstColumn('mysql_clientes','pagos',['fecha_pago','fecha','created_at']);
            $cliCol =$this->firstColumn('mysql_clientes','pagos',['cliente','cliente_nombre','razon_social']);
            $amtCol =$this->firstColumn('mysql_clientes','pagos',['monto','importe','total']);
            $descCol=$this->firstColumn('mysql_clientes','pagos',['concepto','descripcion','detalle']);
            if ($dateCol && $amtCol) {
                $rows=$this->dbc->table('pagos')
                    ->selectRaw("$dateCol as fecha, $amtCol as monto"
                        .($cliCol? ", $cliCol as cliente" : "")
                        .($descCol? ", $descCol as concepto" : ""))
                    ->orderByDesc($dateCol)->limit($limit)->get();
                return $rows->map(fn($r)=>[
                    'fecha'=>(string)($r->fecha??''), 'concepto'=>(string)($r->concepto??'Pago'),
                    'cliente'=>(string)($r->cliente??''), 'monto'=>(float)($r->monto??0),
                ])->all();
            }
        }

        if ($this->tableExists('mysql_clientes','facturas')) {
            $dateCol=$this->firstColumn('mysql_clientes','facturas',['fecha','fecha_emision','created_at']);
            $cliCol =$this->firstColumn('mysql_clientes','facturas',['cliente','cliente_nombre','razon_social','receptor']);
            $amtCol =$this->firstColumn('mysql_clientes','facturas',['total','monto','importe','subtotal']);
            $descCol=$this->firstColumn('mysql_clientes','facturas',['concepto','descripcion','detalle','serie_folio']);
            if ($dateCol && $amtCol) {
                $rows=$this->dbc->table('facturas')
                    ->selectRaw("$dateCol as fecha, $amtCol as monto"
                        .($cliCol? ", $cliCol as cliente" : "")
                        .($descCol? ", $descCol as concepto" : ""))
                    ->orderByDesc($dateCol)->limit($limit)->get();
                return $rows->map(fn($r)=>[
                    'fecha'=>(string)($r->fecha??''), 'concepto'=>(string)($r->concepto??'Factura'),
                    'cliente'=>(string)($r->cliente??''), 'monto'=>(float)($r->monto??0),
                ])->all();
            }
        }

        return [];
    }

    public function clientes(int $limit = 10): array
    {
        if (!$this->tableExists('mysql_clientes','clientes')) return [];
        $fecha  = $this->firstColumn('mysql_clientes','clientes',['created_at','fecha_alta','alta']);
        $nombre = $this->firstColumn('mysql_clientes','clientes',['razon_social','nombre','nombre_comercial','cliente']);
        $email  = $this->firstColumn('mysql_clientes','clientes',['email','correo','correo_electronico','contacto_email','contacto']);
        $status = $this->firstColumn('mysql_clientes','clientes',['estatus','status','estado']);

        try {
            $selects = [];
            if ($fecha)  $selects[] = "$fecha as alta";
            if ($nombre) $selects[] = "$nombre as nombre";
            if ($email)  $selects[] = "$email as email";
            if ($status) $selects[] = "$status as estatus";
            if (empty($selects)) return [];

            $rows = $this->dbc->table('clientes')->selectRaw(implode(', ', $selects))
                ->orderByDesc($fecha ?? 'id')->limit($limit)->get();

            return $rows->map(fn($r)=>[
                'alta'=>(string)($r->alta??''), 'nombre'=>(string)($r->nombre??''), 'email'=>(string)($r->email??''), 'estatus'=>(string)($r->estatus??''),
            ])->all();
        } catch (\Throwable $e) {
            Log::warning('Stats.clientes', ['e'=>$e->getMessage()]); return [];
        }
    }

    public function topClientes(int $months = 12, int $limit = 10): array
    {
        if ($this->tableExists('mysql_clientes','pagos')) {
            $dateCol=$this->firstColumn('mysql_clientes','pagos',['fecha_pago','fecha','created_at']);
            $amtCol =$this->firstColumn('mysql_clientes','pagos',['monto','importe','total']);
            $cliCol =$this->firstColumn('mysql_clientes','pagos',['cliente','cliente_nombre','razon_social']);
            if ($dateCol && $amtCol && $cliCol) {
                $rows = $this->dbc->table('pagos')
                    ->selectRaw("$cliCol as cliente, SUM($amtCol) as total")
                    ->where($dateCol,'>=',Carbon::now()->subMonths($months)->startOfDay())
                    ->groupBy('cliente')->orderByDesc('total')->limit($limit)->get();
                return $rows->map(fn($r)=>['cliente'=>(string)$r->cliente,'total'=>(float)$r->total])->all();
            }
        }

        if ($this->tableExists('mysql_clientes','facturas')) {
            $dateCol=$this->firstColumn('mysql_clientes','facturas',['fecha','fecha_emision','created_at']);
            $amtCol =$this->firstColumn('mysql_clientes','facturas',['total','monto','importe','subtotal']);
            $cliCol =$this->firstColumn('mysql_clientes','facturas',['cliente','cliente_nombre','razon_social','receptor']);
            if ($dateCol && $amtCol && $cliCol) {
                $rows = $this->dbc->table('facturas')
                    ->selectRaw("$cliCol as cliente, SUM($amtCol) as total")
                    ->where($dateCol,'>=',Carbon::now()->subMonths($months)->startOfDay())
                    ->groupBy('cliente')->orderByDesc('total')->limit($limit)->get();
                return $rows->map(fn($r)=>['cliente'=>(string)$r->cliente,'total'=>(float)$r->total])->all();
            }
        }

        return [];
    }

    public function vencimientos(int $days = 30): array
    {
        if (!$this->tableExists('mysql_clientes','clientes')) return [];
        $vtoCol = $this->firstColumn('mysql_clientes','clientes',['vencimiento','fecha_vencimiento','vence','fin_licencia']);
        $nombre = $this->firstColumn('mysql_clientes','clientes',['razon_social','nombre','nombre_comercial','cliente']);
        if (!$vtoCol || !$nombre) return [];

        try {
            $rows = $this->dbc->table('clientes')
                ->selectRaw("$nombre as cliente, $vtoCol as vence")
                ->whereBetween($vtoCol, [Carbon::today(), Carbon::today()->addDays($days)])
                ->orderBy($vtoCol)->limit(20)->get();
            return $rows->map(fn($r)=>['cliente'=>(string)$r->cliente,'vence'=>(string)$r->vence])->all();
        } catch (\Throwable $e) { return []; }
    }

    public function hints(): array
    {
        $h=[]; try {
            $summary = $this->summary();
            $income12= $this->incomeLast12();
            $values  = $income12['values'] ?? [];
            $n = count($values);

            if (($summary['clientesTotal'] ?? 0) > 0) {
                $ratio = ($summary['clientesActivos'] / max(1,$summary['clientesTotal'])) * 100;
                if ($ratio < 60)      $h[] = "Menos del 60% de clientes activos. Revisa renovaciones y comunicación.";
                elseif ($ratio > 85)  $h[] = "Excelente retención: más del 85% de clientes activos.";
            }

            if ($n >= 2) {
                $curr = $values[$n-1] ?? 0; $prev = $values[$n-2] ?? 0;
                if ($prev > 0) {
                    $delta = (($curr-$prev)/$prev)*100;
                    if ($delta >= 15)     $h[] = "Ingresos ↑ ".number_format($delta,1)."% vs mes anterior. Mantén el impulso.";
                    elseif ($delta <= -10)$h[] = "Ingresos ↓ ".number_format(abs($delta),1)."% vs mes anterior. Detecta causas y corrige.";
                }
            }

            if (($summary['ingresosMes'] ?? 0.0) == 0.0) $h[] = "Sin ingresos registrados este mes. Verifica carga de pagos/facturas.";
            if (empty($h)) $h[] = "Todo estable. Sin alertas por ahora.";
        } catch (\Throwable $e) { $h[]="No se pudieron calcular sugerencias (datos insuficientes)."; }

        return $h;
    }

    /* ============================ Agregador ============================ */

    public function statsPayload(?string $ym = null, int $weeks = 6, int $modulesMonths = 6, int $plansMonths = 6): array
    {
        $ym = $ym ?: Carbon::now()->format('Y-m');
        return [
            'summary'        => $this->summary(),
            'income12'       => $this->incomeLast12(),
            'clients12'      => $this->clientsLast12(),
            'pvfMonth'       => $this->pvfForMonth($ym),
            'licenciasPie'   => $this->licenciasPie(),
            'planesPie'      => $this->planesPie(),
            'spark'          => $this->spark14(),
            'hitsHeatmap'    => $this->hitsHeatmap($weeks),
            'modulesTop'     => $this->modulesTop($modulesMonths),
            'plansBreakdown' => $this->plansBreakdown($plansMonths),
            'movimientos'    => $this->movimientos(10),
            'clientes'       => $this->clientes(10),
            'topClientes'    => $this->topClientes(12, 10),
            'vencimientos'   => $this->vencimientos(30),
            'hints'          => $this->hints(),
        ];
    }
}
