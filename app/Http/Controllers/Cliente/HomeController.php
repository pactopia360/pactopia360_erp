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
use Carbon\Carbon;

class HomeController extends Controller
{
    /**
     * Dashboard principal del cliente.
     */
    public function index(Request $request): View
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        // Plan / saldo / timbres (valores seguros)
        $plan    = strtoupper((string) ($cuenta->plan_actual ?? 'FREE'));  // FREE|PRO
        $planKey = strtolower($plan);
        $timbres = (int) ($cuenta->timbres_disponibles ?? ($plan === 'FREE' ? 10 : 0));
        $saldo   = (float) ($cuenta->saldo_mxn ?? 0.0);
        $razon   = $cuenta->razon_social
                 ?? $cuenta->nombre_fiscal
                 ?? ($user->nombre ?? $user->email ?? '—');

        $isLocal = app()->environment(['local','development','testing']);

        // Base de consulta (scoped por cuenta si aplica)
        $base = Cfdi::on('mysql_clientes');
        if ($cuenta && $this->schemaHasCol('clientes', 'cuenta_id')) {
            $clienteIds = DB::connection('mysql_clientes')
                ->table('clientes')
                ->where('cuenta_id', $cuenta->id)
                ->pluck('id')
                ->all();
            $base->whereIn('cliente_id', empty($clienteIds) ? [-1] : $clienteIds);
        }

        // Últimos CFDI (no críticos)
        $recent = (clone $base)
            ->orderByDesc('fecha')
            ->limit(8)
            ->get(['id','uuid','serie','folio','total','fecha','estatus','cliente_id']);

        // Período: mes corriente
        [$from, $to] = $this->resolveMonthRange(null);

        // KPIs + series reales
        $kpis   = $this->calcKpisFor(clone $base, $from, $to);
        $series = $this->buildSeriesFor(clone $base, $from, $to);

        // ¿Hay datos reales?
        $hasRealSeries = !empty($series['series']['emitidos_total']);

        // Fallback DEMO solo en local: si no hay datos reales
        $usedDemo = false;
        if ($this->isDemoMode() && !$hasRealSeries) {
            [$demoKpis, $demoSeries] = $this->buildDemoData($from, $to);
            // si hay algo real en KPIs, respétalo; si no, usa demo
            $kpis   = $kpis['total'] > 0 ? $kpis : $demoKpis;
            $series = $demoSeries;
            $usedDemo = true;
        }

        // Summary cacheado (ligero)
        $summary = Cache::remember(
            'home:summary:uid:'.(Auth::id() ?? 'guest'),
            30,
            fn() => $this->buildAccountSummary()
        );

        $pricing = [
            'monthly' => (float) config('services.stripe.display_price_monthly', 990.00),
            'annual'  => (float) config('services.stripe.display_price_annual', 9990.00),
        ];

        // dataSource para la vista (db|demo) y flag de entorno
        $dataSource = $usedDemo ? 'demo' : 'db';

        return view('cliente.home', compact(
            'plan','planKey','timbres','saldo','razon','recent','kpis','series',
            'summary','pricing','dataSource','isLocal'
        ));
    }

    /**
     * KPIs (JSON). Devuelve 'source' y 'row_count'.
     */
    public function kpis(Request $request): JsonResponse
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        $base = Cfdi::on('mysql_clientes');
        if ($cuenta && $this->schemaHasCol('clientes', 'cuenta_id')) {
            $clienteIds = DB::connection('mysql_clientes')
                ->table('clientes')->where('cuenta_id', $cuenta->id)->pluck('id')->all();
            $base->whereIn('cliente_id', empty($clienteIds) ? [-1] : $clienteIds);
        }

        [$from, $to] = $this->resolveMonthRange($request->string('month'));
        $k = $this->calcKpisFor($base, $from, $to);

        $source = 'db';
        if ($this->isDemoMode() && $k['total'] <= 0) {
            [$demoKpis] = $this->buildDemoData($from, $to);
            $k = $demoKpis;
            $source = 'demo';
        }

        return response()->json($k + [
            'source'    => $source,
            'row_count' => (int) (clone $base)->whereBetween('fecha', [$from, $to])->count(),
        ]);
    }

    /**
     * Series (JSON). Devuelve 'source' y 'row_count'.
     */
    public function series(Request $request): JsonResponse
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        $base = Cfdi::on('mysql_clientes');
        if ($cuenta && $this->schemaHasCol('clientes', 'cuenta_id')) {
            $clienteIds = DB::connection('mysql_clientes')
                ->table('clientes')->where('cuenta_id', $cuenta->id)->pluck('id')->all();
            $base->whereIn('cliente_id', empty($clienteIds) ? [-1] : $clienteIds);
        }

        [$from, $to] = $this->resolveMonthRange($request->string('month'));

        $rows = (clone $base)->whereBetween('fecha', [$from, $to])
            ->selectRaw("
                DATE(fecha) as d,
                SUM(CASE WHEN estatus='emitido'   THEN total ELSE 0 END) as em_total,
                SUM(CASE WHEN estatus='cancelado' THEN total ELSE 0 END) as ca_total
            ")
            ->groupBy('d')->orderBy('d','asc')->get();

        $labels = []; $lineEmitidos = []; $lineCancelados = [];
        foreach ($rows as $r) {
            $labels[]         = $r->d;
            $lineEmitidos[]   = round((float)$r->em_total, 2);
            $lineCancelados[] = round((float)$r->ca_total, 2);
        }

        // Barras por cuartiles del mes
        $q = [0,0,0,0];
        foreach ($rows as $r) {
            $day = (int) \Carbon\Carbon::parse($r->d)->format('j');
            $bucket = min(3, intdiv($day-1, 8));
            $q[$bucket] += (float)$r->em_total;
        }
        $barQ = array_map(fn($v)=>round($v,2), $q);

        $payload = [
            'labels' => $labels,
            'series' => [
                'line_facturacion' => $lineEmitidos,
                'line_cancelados'  => $lineCancelados,
                'bar_q'            => $barQ,
                'emitidos_total'   => $lineEmitidos, // compat
            ],
            'source'    => 'db',
            'row_count' => count($rows),
        ];

        if ($this->isDemoMode() && empty($lineEmitidos)) {
            [, $demoSeries] = $this->buildDemoData($from, $to);
            $payload = $demoSeries + ['source' => 'demo', 'row_count' => 0];
        }

        return response()->json($payload);
    }

    /**
     * (Opcional) Combo para un solo fetch en el front. Incluye 'source'.
     */
    public function combo(Request $request): JsonResponse
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        $base = Cfdi::on('mysql_clientes');
        if ($cuenta && $this->schemaHasCol('clientes','cuenta_id')) {
            $ids = DB::connection('mysql_clientes')->table('clientes')->where('cuenta_id',$cuenta->id)->pluck('id')->all();
            $base->whereIn('cliente_id', empty($ids)?[-1]:$ids);
        }

        [$from, $to] = $this->resolveMonthRange($request->string('month'));
        $k = $this->calcKpisFor(clone $base, $from, $to);
        $s = $this->buildSeriesFor(clone $base, $from, $to);

        $source = 'db';
        if ($this->isDemoMode() && empty($s['series']['emitidos_total'])) {
            [$dk, $ds] = $this->buildDemoData($from, $to);
            $k = $k['total'] > 0 ? $k : $dk;
            $s = $ds;
            $source = 'demo';
        }

        return response()->json([
            'summary' => $this->buildAccountSummary(),
            'kpis'    => $k,
            'series'  => $s,
            'source'  => $source,
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

        $fromC = Carbon::parse($from);
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
                'emitidos_total'   => $vals,          // legacy / compat
                'line_facturacion' => $vals,          // nuevo nombre
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
        try { return Schema::connection($conn)->hasColumn($table, $col); } catch (\Throwable $e) { return false; }
    }

    /**
     * Resumen de cuenta.
     */
    private function buildAccountSummary(): array
    {
        $u = Auth::guard('web')->user();
        $cuenta = $u?->cuenta;
        $admConn = 'mysql_admin';

        $planKey = strtoupper((string) ($cuenta->plan_actual ?? 'FREE'));
        $timbres = (int) ($cuenta->timbres_disponibles ?? ($planKey === 'FREE' ? 10 : 0));
        $saldoMx = (float) ($cuenta->saldo_mxn ?? 0.0);
        $razon   = $cuenta->razon_social ?? $cuenta->nombre_fiscal ?? ($u->nombre ?? $u->email ?? '—');

        $adminId = $cuenta->admin_account_id ?? null;
        $rfc     = $cuenta->rfc_padre ?? null;

        if (!$adminId && $rfc && Schema::connection($admConn)->hasTable('accounts')) {
            $acc = DB::connection($admConn)->table('accounts')->select('id')->where('rfc', strtoupper($rfc))->first();
            if ($acc) $adminId = (int) $acc->id;
        }

        $acc = null;
        if ($adminId && Schema::connection($admConn)->hasTable('accounts')) {
            $cols = ['id'];
            foreach (['plan','billing_cycle','next_invoice_date','estado_cuenta','is_blocked','razon_social','email','email_verified_at','phone_verified_at'] as $c) {
                if ($this->hasCol($admConn,'accounts',$c)) $cols[] = $c;
            }
            $acc = DB::connection($admConn)->table('accounts')->select($cols)->where('id',$adminId)->first();
        }

        $balance = $saldoMx;
        if (Schema::connection($admConn)->hasTable('estados_cuenta')) {
            $linkCol = null; $linkVal = null;

            if ($this->hasCol($admConn,'estados_cuenta','account_id') && $adminId) {
                $linkCol = 'account_id'; $linkVal = $adminId;
            } elseif ($this->hasCol($admConn,'estados_cuenta','cuenta_id') && $adminId) {
                $linkCol = 'cuenta_id';  $linkVal = $adminId;
            } elseif ($this->hasCol($admConn,'estados_cuenta','rfc') && $rfc) {
                $linkCol = 'rfc';        $linkVal = strtoupper($rfc);
            }

            if ($linkCol !== null) {
                $orderCol = $this->hasCol($admConn,'estados_cuenta','periodo')
                    ? 'periodo'
                    : ($this->hasCol($admConn,'estados_cuenta','created_at') ? 'created_at' : 'id');

                $last = DB::connection($admConn)->table('estados_cuenta')
                    ->where($linkCol, $linkVal)
                    ->orderByDesc($orderCol)
                    ->first();

                if ($last && property_exists($last, 'saldo') && $last->saldo !== null) {
                    $balance = (float) $last->saldo;
                } else {
                    $hasCargo = $this->hasCol($admConn,'estados_cuenta','cargo');
                    $hasAbono = $this->hasCol($admConn,'estados_cuenta','abono');

                    if ($hasCargo || $hasAbono) {
                        $cargo = $hasCargo
                            ? (float) DB::connection($admConn)->table('estados_cuenta')->where($linkCol,$linkVal)->sum('cargo')
                            : 0.0;
                        $abono = $hasAbono
                            ? (float) DB::connection($admConn)->table('estados_cuenta')->where($linkCol,$linkVal)->sum('abono')
                            : 0.0;
                        $balance = $cargo - $abono;
                    }
                }
            }
        }

        $spaceTotal = (float) ($cuenta->espacio_total_mb ?? 512);
        $spaceUsed  = (float) ($cuenta->espacio_usado_mb ?? 0);
        $spacePct   = $spaceTotal > 0 ? min(100, round(($spaceUsed/$spaceTotal)*100,1)) : 0;

        $plan   = strtolower((string) ($acc->plan ?? $planKey));
        $cycle  = $acc->billing_cycle ?? ($cuenta->modo_cobro ?? 'mensual');
        $estado = $acc->estado_cuenta ?? ($cuenta->estado_cuenta ?? null);
        $blocked = (bool) (($acc->is_blocked ?? 0) || ($cuenta->is_blocked ?? 0));

        return [
            'razon'        => (string) ($acc->razon_social ?? $razon),
            'plan'         => $plan,
            'is_pro'       => in_array($plan, ['pro','premium','empresa','business'], true),
            'cycle'        => $cycle,
            'next_invoice' => $acc->next_invoice_date ?? null,
            'estado'       => $estado,
            'blocked'      => $blocked,
            'balance'      => $balance,
            'space_total'  => $spaceTotal,
            'space_used'   => $spaceUsed,
            'space_pct'    => $spacePct,
            'timbres'      => $timbres,
            'admin_id'     => $adminId,
        ];
    }

    // month=YYYY-MM
    private function resolveMonthRange(?string $month): array
    {
        if ($month && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $month)) {
            $from = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $to   = \Carbon\Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        } else {
            $from = now()->startOfMonth();
            $to   = now()->endOfMonth();
        }
        return [$from->toDateTimeString(), $to->toDateTimeString()];
    }

    /**
     * DEMO solo en entornos locales/desarrollo/testing.
     * En producción siempre retorna false.
     */
    private function isDemoMode(): bool
    {
        return app()->environment(['local','development','testing']);
    }

    /**
     * Construye KPIs y series DEMO estables dentro del mes dado.
     */
    private function buildDemoData(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfMonth();
        $end   = Carbon::parse($to)->endOfMonth();

        // Semilla estable por usuario+mes
        $seed = crc32((string)(Auth::id() ?? 0) . '|' . $start->format('Y-m'));
        mt_srand($seed);

        $labels = [];
        $emit   = [];
        $canc   = [];

        $day = $start->copy();
        while ($day->lte($end)) {
            $labels[] = $day->format('Y-m-d');

            // Base entre 3,000 y 18,000 al día con variación ondulante
            $base = 3000 + mt_rand(0, 15000);
            $wave = 1 + 0.25 * sin(($day->dayOfYear / 58) * 3.14159);
            $val  = round($base * $wave, 2);

            $emit[] = $val;

            // Cancelados ~1.5% promedio
            $canc[] = round($val * (mt_rand(5, 30) / 1000), 2);

            $day->addDay();
        }

        // Barras por cuartiles
        $q = [0,0,0,0];
        foreach ($labels as $i => $d) {
            $dayNum = (int) substr($d, 8, 2);
            $bucket = min(3, intdiv($dayNum-1, 8));
            $q[$bucket] += $emit[$i];
        }
        $barQ = array_map(fn($v)=>round($v,2), $q);

        // Totales demo
        $sumEmit = array_sum($emit);
        $sumCanc = array_sum($canc);
        $kpis = [
            'total'      => round($sumEmit, 2),
            'emitidos'   => round($sumEmit, 2),
            'cancelados' => round($sumCanc, 2),
            'delta'      => mt_rand(-12, 18), // +/- variación
            'period'     => ['from' => $from, 'to' => $to],
        ];

        $series = [
            'labels' => $labels,
            'series' => [
                'line_facturacion' => $emit,
                'line_cancelados'  => $canc,
                'bar_q'            => $barQ,
                'emitidos_total'   => $emit, // compat
            ],
        ];

        return [$kpis, $series];
    }
}
