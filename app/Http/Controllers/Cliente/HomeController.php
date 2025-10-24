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

        // Plan / saldo / timbres (valores seguros por si no existen columnas aún)
        $plan    = strtoupper((string) ($cuenta->plan_actual ?? 'FREE'));  // FREE|PRO
        $timbres = (int) ($cuenta->timbres_disponibles ?? ($plan === 'FREE' ? 10 : 0));
        $saldo   = (float) ($cuenta->saldo_mxn ?? 0.0);
        $razon   = $cuenta->razon_social
                 ?? $cuenta->nombre_fiscal
                 ?? ($user->nombre ?? $user->email ?? '—');

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

        // Últimos CFDI sin agrupar (aquí sí se puede ordenar por fecha)
        $recent = (clone $base)
            ->orderByDesc('fecha')
            ->limit(8)
            ->get(['id','uuid','serie','folio','total','fecha','estatus','cliente_id']);

        // Período: mes corriente
        $from = now()->startOfMonth()->toDateTimeString();
        $to   = now()->endOfMonth()->toDateTimeString();

        // KPIs + series
        $kpis   = $this->calcKpisFor(clone $base, $from, $to);
        $series = $this->buildSeriesFor(clone $base, $from, $to);

        // NUEVO: summary + pricing (no interfiere con tus kpis/series)
        $summary = Cache::remember(
            'home:summary:uid:'.(Auth::id() ?? 'guest'),
            30,    // TTL segundos, ajustable
            fn() => $this->buildAccountSummary()
        );

        // Precios visibles para CTA upgrade
        $pricing = [
            'monthly' => (float) config('services.stripe.display_price_monthly', 249.99),
            'annual'  => (float) config('services.stripe.display_price_annual', 1999.99),
        ];

        return view('cliente.home', compact(
            'plan','timbres','saldo','razon','recent','kpis','series',
            'summary','pricing'
        ));
    }

    // === AJUSTE: kpis() acepta month ===
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
        return response()->json($this->calcKpisFor($base, $from, $to));
    }

    // === AJUSTE: series() acepta month y entrega claves esperadas por la vista ===
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

        // Serie diaria emitidos / cancelados
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

        // Barras Q1..Q4 con suma del mes por semanas (aprox. 4 cortes)
        // (no perfecto calendario; suficiente para tendencia visual)
        $q = [0,0,0,0];
        foreach ($rows as $r) {
            $day = (int) \Carbon\Carbon::parse($r->d)->format('j'); // 1..31
            $bucket = min(3, intdiv($day-1, 8)); // 0..3
            $q[$bucket] += (float)$r->em_total;
        }
        $barQ = array_map(fn($v)=>round($v,2), $q);

        return response()->json([
            'labels' => $labels,
            'series' => [
                'line_facturacion' => $lineEmitidos,
                'line_cancelados'  => $lineCancelados,
                'bar_q'            => $barQ,
            ],
        ]);
    }

    /**
     * (Opcional) Combo para un solo fetch en el front.
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

        $from = now()->startOfMonth()->toDateTimeString();
        $to   = now()->endOfMonth()->toDateTimeString();

        return response()->json([
            'summary' => $this->buildAccountSummary(),
            'kpis'    => $this->calcKpisFor(clone $base, $from, $to),
            'series'  => $this->buildSeriesFor(clone $base, $from, $to),
        ]);
    }

    /* ===========================
     * Helpers internos
     * =========================== */

    /**
     * Calcula KPIs del período: total, emitidos, cancelados, delta vs mes previo.
     */
    private function calcKpisFor($baseQuery, string $from, string $to): array
    {
        $period   = (clone $baseQuery)->whereBetween('fecha', [$from, $to]);
        $total    = (float) ($period->clone()->sum('total') ?? 0);
        $emitidos = (float) ($period->clone()->where('estatus', 'emitido')->sum('total') ?? 0);
        $cancel   = (float) ($period->clone()->where('estatus', 'cancelado')->sum('total') ?? 0);

        // Delta vs mes anterior (mismo rango mensual)
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

    /**
     * Construye serie por día (emitidos_total) sin violar ONLY_FULL_GROUP_BY.
     * Ordena por el alias del grupo (d).
     */
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
            'series' => ['emitidos_total' => $vals],
        ];
    }

    /** % delta con protección división entre 0. */
    private function deltaPct(float $prev, float $now): float
    {
        if ($prev <= 0 && $now <= 0) return 0.0;
        if ($prev <= 0 && $now > 0)  return 100.0;
        return round((($now - $prev) / max($prev, 0.00001)) * 100, 2);
    }

    /** Verifica columna en esquema de mysql_clientes. */
    private function schemaHasCol(string $table, string $col): bool
    {
        try {
            return Schema::connection('mysql_clientes')->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Busca si existe columna en una conexión dada. */
    private function hasCol(string $conn, string $table, string $col): bool
    {
        try { return Schema::connection($conn)->hasColumn($table, $col); } catch (\Throwable $e) { return false; }
    }

    /**
 * Resumen de cuenta: plan/ciclo/next_invoice/estado/bloqueo/saldo/espacio/pro/admin_id/razon.
 * Robusto ante esquemas: estados_cuenta puede tener account_id, cuenta_id o rfc.
 */
private function buildAccountSummary(): array
{
    $u = Auth::guard('web')->user();
    $cuenta = $u?->cuenta;
    $cliConn = 'mysql_clientes';
    $admConn = 'mysql_admin';

    // ----- Plan / timbres / saldo en espejo cliente -----
    $planKey = strtoupper((string) ($cuenta->plan_actual ?? 'FREE'));
    $timbres = (int) ($cuenta->timbres_disponibles ?? ($planKey === 'FREE' ? 10 : 0));
    $saldoMx = (float) ($cuenta->saldo_mxn ?? 0.0);
    $razon   = $cuenta->razon_social ?? $cuenta->nombre_fiscal ?? ($u->nombre ?? $u->email ?? '—');

    // ----- Resolver admin_id por relación directa o por RFC -----
    $adminId = $cuenta->admin_account_id ?? null;
    $rfc     = $cuenta->rfc_padre ?? null;

    if (!$adminId && $rfc && Schema::connection($admConn)->hasTable('accounts')) {
        $acc = DB::connection($admConn)->table('accounts')->select('id')->where('rfc', strtoupper($rfc))->first();
        if ($acc) $adminId = (int) $acc->id;
    }

    // ----- Traer cuenta admin (plan/ciclo/estado/bloqueo/next_invoice) -----
    $acc = null;
    if ($adminId && Schema::connection($admConn)->hasTable('accounts')) {
        $cols = ['id'];
        foreach (['plan','billing_cycle','next_invoice_date','estado_cuenta','is_blocked','razon_social','email','email_verified_at','phone_verified_at'] as $c) {
            if ($this->hasCol($admConn,'accounts',$c)) $cols[] = $c;
        }
        $acc = DB::connection($admConn)->table('accounts')->select($cols)->where('id',$adminId)->first();
    }

    // ----- Balance desde estados_cuenta (dinámico por columna de enlace) -----
    $balance = $saldoMx;

    if (Schema::connection($admConn)->hasTable('estados_cuenta')) {
        $linkCol = null; $linkVal = null;

        // Detecta columna de enlace en orden de preferencia
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

            // Último movimiento para intentar tomar 'saldo'
            $last = DB::connection($admConn)->table('estados_cuenta')
                ->where($linkCol, $linkVal)
                ->orderByDesc($orderCol)
                ->first();

            if ($last && property_exists($last, 'saldo') && $last->saldo !== null) {
                $balance = (float) $last->saldo;
            } else {
                // Fallback: sumar cargos/abonos sólo si existen ambas columnas
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
                // Si no existen columnas, dejamos el saldo como estaba (saldoMx).
            }
        }
    }

    // ----- Espacio usado/total -----
    $spaceTotal = (float) ($cuenta->espacio_total_mb ?? 512);
    $spaceUsed  = (float) ($cuenta->espacio_usado_mb ?? 0);
    $spacePct   = $spaceTotal > 0 ? min(100, round(($spaceUsed/$spaceTotal)*100,1)) : 0;

    // ----- Consolidado -----
    $plan   = strtolower((string) ($acc->plan ?? $planKey));
    $cycle  = $acc->billing_cycle ?? ($cuenta->modo_cobro ?? 'mensual');
    $estado = $acc->estado_cuenta ?? ($cuenta->estado_cuenta ?? null);
    $blocked = (bool) (($acc->is_blocked ?? 0) || ($cuenta->is_blocked ?? 0));

    return [
        'razon'        => (string) ($acc->razon_social ?? $razon),
        'plan'         => $plan,                    // 'free' | 'pro' | …
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


// === NUEVO: helper de periodo (month=YYYY-MM) ===
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

}
