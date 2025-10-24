<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Modelos
use App\Models\Cliente\Cfdi;
use App\Models\Cliente\CfdiConcepto;
use App\Models\Cliente\Producto;
use App\Models\Cliente\Receptor;
use App\Models\Cliente as Emisor;

class FacturacionController extends Controller
{
    /* ===================== Utilidades de esquema/conn ===================== */

    protected function tableExists(string $table, string $conn): bool
    {
        try { return Schema::connection($conn)->hasTable($table); } catch (\Throwable $e) { return false; }
    }

    protected function hasColumn(string $table, string $col, string $conn): bool
    {
        try { return Schema::connection($conn)->hasColumn($table, $col); } catch (\Throwable $e) { return false; }
    }

    /**
     * Devuelve la primera conexión donde exista la tabla.
     * Si no existe en ninguna, retorna null.
     */
    protected function firstConnWith(string $table, array $order = ['mysql','mysql_clientes']): ?string
    {
        foreach ($order as $conn) {
            if ($this->tableExists($table, $conn)) return $conn;
        }
        return null;
    }

    /** Conexión natural del modelo Cfdi. */
    protected function cfdiConn(): string
    {
        try { return (new Cfdi)->getConnectionName() ?: 'mysql'; } catch (\Throwable $e) { return 'mysql'; }
    }

    /* ===================== Filtros / periodo ===================== */

    protected function cfdiBaseQuery(Request $request)
    {
        $conn   = $this->cfdiConn();
        $q      = Cfdi::on($conn);
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        // Filtra por emisores de la cuenta si aplica
        $cliConn = $this->firstConnWith('clientes') ?? $conn;
        if ($cuenta && $this->hasColumn('clientes', 'cuenta_id', $cliConn)) {
            $ids = Emisor::on($cliConn)->where('cuenta_id', $cuenta->id)->pluck('id')->all();
            $q->whereIn('cliente_id', empty($ids) ? [-1] : $ids);
        }

        return $q;
    }

    protected function resolvePeriod(Request $request): array
    {
        $month = trim((string) $request->input('month', ''));
        $mes   = $request->input('mes');
        $anio  = $request->input('anio');
        $from  = trim((string) $request->input('from', ''));
        $to    = trim((string) $request->input('to', ''));

        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $f = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $t = $f->copy()->endOfMonth();
            return [$f->toDateTimeString(), $t->toDateTimeString()];
        }
        if (is_numeric($mes) && is_numeric($anio)) {
            $f = Carbon::createFromDate((int) $anio, (int) $mes, 1)->startOfMonth();
            $t = $f->copy()->endOfMonth();
            return [$f->toDateTimeString(), $t->toDateTimeString()];
        }
        if ($from !== '' && $to !== '') {
            $f = Carbon::parse($from)->startOfDay();
            $t = Carbon::parse($to)->endOfDay();
            return [$f->toDateTimeString(), $t->toDateTimeString()];
        }
        return [now()->startOfMonth()->toDateTimeString(), now()->endOfMonth()->toDateTimeString()];
    }

    protected function applyFilters($q, Request $request)
    {
        $qStr   = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));

        if ($qStr !== '') {
            $q->where(function ($w) use ($qStr) {
                $w->where('uuid', 'like', "%{$qStr}%")
                  ->orWhere('serie', 'like', "%{$qStr}%")
                  ->orWhere('folio', 'like', "%{$qStr}%");
            });
        }
        if ($status !== '') $q->where('estatus', $status);

        return $q;
    }

    /* ===================== Endpoints ===================== */

    public function index(Request $request): View
    {
        [$from, $to] = $this->resolvePeriod($request);
        $base = $this->cfdiBaseQuery($request);

        $q = $this->applyFilters(
            (clone $base)->whereBetween('fecha', [$from, $to]),
            $request
        )->with([
            'cliente:id,razon_social,nombre_comercial,rfc',
            'conceptos:id,cfdi_id,descripcion',
        ]);

        $perPage = (int) $request->integer('per_page', 15);
        $cfdis = $q->orderByDesc('fecha')
            ->paginate($perPage, ['id','uuid','serie','folio','subtotal','iva','total','fecha','estatus','cliente_id'])
            ->withQueryString();

        $kpis   = $this->calcKpis($request, $from, $to);
        $series = $this->buildSeries($request, $from, $to);

        $mes  = (int) Carbon::parse($from)->format('m');
        $anio = (int) Carbon::parse($from)->format('Y');

        return view('cliente.facturacion.index', [
            'period_from' => $from,
            'period_to'   => $to,
            'kpis'        => $kpis,
            'series'      => $series,
            'cfdis'       => $cfdis,
            'filters'     => [
                'q'      => trim((string) $request->input('q', '')),
                'status' => trim((string) $request->input('status', '')),
                'month'  => trim((string) $request->input('month', '')),
                'mes'    => $mes,
                'anio'   => $anio,
            ],
        ]);
    }

    public function kpis(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);
        return response()->json($this->calcKpis($request, $from, $to));
    }

    public function series(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);
        return response()->json($this->buildSeries($request, $from, $to));
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->resolvePeriod($request);
        $base = $this->cfdiBaseQuery($request);

        $q = $this->applyFilters(
            (clone $base)->whereBetween('fecha', [$from, $to]),
            $request
        )->orderByDesc('fecha');

        $filename = 'cfdis_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['UUID','Serie','Folio','Subtotal','IVA','Total','Fecha','Estatus','ClienteID','ReceptorID']);
            $q->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->uuid,
                        $r->serie,
                        $r->folio,
                        number_format((float) ($r->subtotal ?? 0), 2, '.', ''),
                        number_format((float) ($r->iva ?? 0), 2, '.', ''),
                        number_format((float) ($r->total ?? 0), 2, '.', ''),
                        optional($r->fecha)->format('Y-m-d H:i:s'),
                        $r->estatus,
                        $r->cliente_id,
                        $r->receptor_id ?? null,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Helper seguro: obtiene filas o colección vacía si no existe la tabla. */
    protected function safeList(
        string $table,
        array $columns,
        ?string $orderBy = null,
        int $limit = 200,
        ?string $conn = null,
        ?callable $scope = null
    ): Collection {
        $connToUse = $conn ?: $this->firstConnWith($table);
        if (!$connToUse || !$this->tableExists($table, $connToUse)) {
            return collect(); // tabla no existe en ninguna conexión -> no reventar
        }
        try {
            $q = DB::connection($connToUse)->table($table);
            if ($scope) $scope($q);
            if ($orderBy) $q->orderByRaw($orderBy);
            if ($limit > 0) $q->limit($limit);
            return $q->get($columns);
        } catch (\Throwable $e) {
            return collect(); // ante cualquier error, devolvemos vacío
        }
    }

    /** Formulario: Nuevo Documento (robusto ante tablas faltantes). */
    public function create(Request $request): View
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        $emisores = $this->safeList(
            'clientes',
            ['id','rfc','razon_social','nombre_comercial'],
            "COALESCE(nombre_comercial, razon_social, '') ASC",
            200,
            null,
            function ($q) use ($cuenta) {
                $conn = $q->getConnection()->getName();
                if ($cuenta && $this->hasColumn('clientes', 'cuenta_id', $conn)) {
                    $q->where('cuenta_id', $cuenta->id);
                }
            }
        );

        $receptores = $this->safeList(
            'receptores',
            ['id','rfc','razon_social','nombre_comercial'],
            "COALESCE(razon_social, nombre_comercial, '') ASC",
            200,
            null,
            function ($q) use ($cuenta) {
                $conn = $q->getConnection()->getName();
                if ($cuenta && $this->hasColumn('receptores', 'cuenta_id', $conn)) {
                    $q->where('cuenta_id', $cuenta->id);
                }
            }
        );

        $productos = $this->safeList(
            'productos',
            ['id','sku','descripcion','precio_unitario','iva_tasa','cuenta_id'],
            "descripcion ASC",
            400,
            null,
            function ($q) use ($cuenta) {
                $conn = $q->getConnection()->getName();
                if ($cuenta && $this->hasColumn('productos', 'cuenta_id', $conn)) {
                    $q->where('cuenta_id', $cuenta->id);
                }
            }
        );

        return view('cliente.facturacion.nuevo', [
            'emisores'   => $emisores,
            'receptores' => $receptores,
            'productos'  => $productos,
        ]);
    }

    /** Guardar borrador. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id'    => 'required|integer',
            'receptor_id'   => 'required|integer',
            'serie'         => 'nullable|string|max:10',
            'folio'         => 'nullable|string|max:20',
            'moneda'        => 'nullable|string|max:10',
            'forma_pago'    => 'nullable|string|max:10',
            'metodo_pago'   => 'nullable|string|max:10',
            'fecha'         => 'nullable|date',
            'conceptos'     => 'required|array|min:1',
            'conceptos.*.producto_id'     => 'nullable|integer',
            'conceptos.*.descripcion'     => 'required|string|max:500',
            'conceptos.*.cantidad'        => 'required|numeric|min:0.0001',
            'conceptos.*.precio_unitario' => 'required|numeric|min:0',
            'conceptos.*.iva_tasa'        => 'nullable|numeric|min:0',
        ]);

        $subtotal = 0.0; $iva = 0.0; $total = 0.0;
        foreach ($data['conceptos'] as $c) {
            $cant = (float) $c['cantidad'];
            $ppu  = (float) $c['precio_unitario'];
            $tasa = isset($c['iva_tasa']) ? (float) $c['iva_tasa'] : 0.16;

            $lsub = round($cant * $ppu, 4);
            $liva = round($lsub * $tasa, 4);
            $ltot = round($lsub + $liva, 4);

            $subtotal += $lsub; $iva += $liva; $total += $ltot;
        }
        $subtotal = round($subtotal, 2);
        $iva      = round($iva, 2);
        $total    = round($total, 2);

        $uuidTemp = (string) \Illuminate\Support\Str::uuid();
        $conn     = $this->cfdiConn();

        DB::connection($conn)->transaction(function () use ($data, $subtotal, $iva, $total, $uuidTemp, $conn) {
            $cfdi = (new Cfdi);
            $cfdi->setConnection($conn);
            $cfdi = $cfdi->newQuery()->create([
                'cliente_id'   => $data['cliente_id'],
                'receptor_id'  => $data['receptor_id'] ?? null,
                'serie'        => $data['serie'] ?? null,
                'folio'        => $data['folio'] ?? null,
                'subtotal'     => $subtotal,
                'iva'          => $iva,
                'total'        => $total,
                'fecha'        => $data['fecha'] ?? now(),
                'estatus'      => 'borrador',
                'uuid'         => $uuidTemp,
                'moneda'       => $data['moneda'] ?? 'MXN',
                'forma_pago'   => $data['forma_pago'] ?? null,
                'metodo_pago'  => $data['metodo_pago'] ?? null,
            ]);

            $cc = (new CfdiConcepto);
            $cc->setConnection($conn);

            foreach ($data['conceptos'] as $c) {
                $cant = (float) $c['cantidad'];
                $ppu  = (float) $c['precio_unitario'];
                $tasa = isset($c['iva_tasa']) ? (float) $c['iva_tasa'] : 0.16;

                $lsub = round($cant * $ppu, 4);
                $liva = round($lsub * $tasa, 4);
                $ltot = round($lsub + $liva, 4);

                $cc->newQuery()->create([
                    'cfdi_id'         => $cfdi->id,
                    'producto_id'     => $c['producto_id'] ?? null,
                    'descripcion'     => $c['descripcion'],
                    'cantidad'        => $cant,
                    'precio_unitario' => $ppu,
                    'subtotal'        => round($lsub, 2),
                    'iva'             => round($liva, 2),
                    'total'           => round($ltot, 2),
                ]);
            }
        });

        return redirect()
            ->route('cliente.facturacion.index', ['month' => now()->format('Y-m')])
            ->with('ok', 'Borrador creado correctamente.');
    }

    /* ===================== KPIs/Series ===================== */

    protected function calcKpis(Request $request, string $from, string $to): array
    {
        $base   = $this->cfdiBaseQuery($request);
        $period = (clone $base)->whereBetween('fecha', [$from, $to]);

        $totalPeriodo = (float) ($period->clone()->sum('total') ?? 0);
        $emitidos     = (float) ($period->clone()->where('estatus', 'emitido')->sum('total') ?? 0);
        $cancelados   = (float) ($period->clone()->where('estatus', 'cancelado')->sum('total') ?? 0);

        $fromC   = Carbon::parse($from);
        $toC     = Carbon::parse($to);
        $lenDays = $fromC->diffInDays($toC) + 1;

        $prevFrom = $fromC->copy()->subDays($lenDays);
        $prevTo   = $toC->copy()->subDays($lenDays);

        $periodPrev = (clone $base)->whereBetween('fecha', [$prevFrom->toDateTimeString(), $prevTo->toDateTimeString()]);
        $totalPrev  = (float) ($periodPrev->clone()->sum('total') ?? 0);
        $delta      = $this->deltaPct($totalPrev, $totalPeriodo);

        return [
            'total_periodo' => round($totalPeriodo, 2),
            'emitidos'      => round($emitidos, 2),
            'cancelados'    => round($cancelados, 2),
            'period'        => ['from' => $from, 'to' => $to],
            'prev_period'   => ['from' => $prevFrom->toDateTimeString(), 'to' => $prevTo->toDateTimeString()],
            'delta_total'   => $delta,
        ];
    }

    protected function buildSeries(Request $request, string $from, string $to): array
    {
        $conn = $this->cfdiConn();

        $sub = $this->cfdiBaseQuery($request)->clone()
            ->withoutGlobalScopes()
            ->whereBetween('fecha', [$from, $to])
            ->reorder()
            ->selectRaw("
                DATE(fecha) AS d,
                SUM(CASE WHEN estatus='emitido'   THEN 1     ELSE 0 END) AS cnt_emitidos,
                SUM(CASE WHEN estatus='cancelado' THEN 1     ELSE 0 END) AS cnt_cancelados,
                SUM(CASE WHEN estatus='emitido'   THEN total ELSE 0 END) AS total_emitidos,
                SUM(CASE WHEN estatus='cancelado' THEN total ELSE 0 END) AS total_cancelados
            ")
            ->groupBy('d');

        $rows = DB::connection($conn)->query()
            ->fromSub($sub, 't')->orderBy('d', 'asc')->get();

        $labels = $emCnt = $caCnt = $emTot = $caTot = [];

        foreach ($rows as $r) {
            $labels[] = $r->d;
            $emCnt[]  = (int) $r->cnt_emitidos;
            $caCnt[]  = (int) $r->cnt_cancelados;
            $emTot[]  = round((float) $r->total_emitidos, 2);
            $caTot[]  = round((float) $r->total_cancelados, 2);
        }

        return [
            'labels' => $labels,
            'series' => [
                'emitidos_cnt'     => $emCnt,
                'cancelados_cnt'   => $caCnt,
                'emitidos_total'   => $emTot,
                'cancelados_total' => $caTot,
            ],
        ];
    }

    /* ===================== Misc ===================== */

    protected function deltaPct(float $prev, float $now): float
    {
        if ($prev <= 0 && $now <= 0) return 0.0;
        if ($prev <= 0 && $now > 0)  return 100.0;
        return round((($now - $prev) / max($prev, 0.00001)) * 100, 2);
    }
}
