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
use App\Models\Cliente\Emisor; // ✅ ahora sí el modelo correcto

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

        // ✅ Filtra por EMISORES de la cuenta (tabla emisores), no por usuarios
        $emiConn = $this->firstConnWith('emisores', ['mysql_clientes','mysql']) ?? $conn;

        if ($cuenta && $this->hasColumn('emisores', 'cuenta_id', $emiConn)) {
            try {
                $ids = Emisor::on($emiConn)
                    ->where('cuenta_id', $cuenta->id)
                    ->pluck('id')
                    ->all();
            } catch (\Throwable $e) {
                $ids = [];
            }

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

        // CFDI actual (stub si no hay)
        $current = $cfdis->getCollection()->first();
        if (!$current) {
            $current = (object)[
                'id'=>null,'uuid'=>null,'serie'=>null,'folio'=>null,
                'subtotal'=>0,'iva'=>0,'total'=>0,'fecha'=>null,'estatus'=>null,'cliente_id'=>null
            ];
        }

        $kpis   = $this->calcKpis($request, $from, $to);
        $series = $this->buildSeries($request, $from, $to);

        $mes  = (int) Carbon::parse($from)->format('m');
        $anio = (int) Carbon::parse($from)->format('Y');

        /**
         * ==========================================================
         * SOT ADMIN EN VIVO
         * Usar la misma fuente que Home para evitar que Facturación
         * muestre FREE/BASE cuando Admin ya cambió la cuenta a PRO.
         * ==========================================================
         */
        try {
            $summary = app(HomeController::class)->buildAccountSummary();
        } catch (\Throwable $e) {
            $user   = Auth::guard('web')->user();
            $cuenta = $user?->cuenta;

            $planFallback = strtolower((string) ($cuenta->plan_actual ?? $cuenta->plan ?? 'free'));

            $summary = [
                'razon'   => (string) ($cuenta->razon_social ?? $cuenta->nombre_fiscal ?? $user?->nombre ?? $user?->email ?? '—'),
                'plan'    => $planFallback,
                'is_pro'  => in_array($planFallback, ['pro', 'pro_mensual', 'pro_anual', 'premium', 'empresa', 'business'], true),
                'cycle'   => (string) ($cuenta->modo_cobro ?? 'mensual'),
                'estado'  => (string) ($cuenta->estado_cuenta ?? 'activa'),
                'blocked' => (bool) ((int) ($cuenta->is_blocked ?? 0) === 1),
                'timbres' => (int) ($cuenta->timbres_disponibles ?? 0),
            ];
        }

        $defaultGuard = (string) (config('auth.defaults.guard') ?? 'web');

        $user = null;

        try {
            $user = Auth::guard($defaultGuard)->user();
        } catch (\Throwable $e) {
            $user = null;
        }

        if (!$user) {
            try {
                $user = Auth::guard('cliente')->user();
            } catch (\Throwable $e) {
                $user = null;
            }
        }

        if (!$user) {
            try {
                $user = Auth::guard('web')->user();
            } catch (\Throwable $e) {
                $user = null;
            }
        }

        if (is_array($user)) {
            $user = (object) $user;
        }

        $cuenta = $user?->cuenta ?? null;

        if (is_array($cuenta)) {
            $cuenta = (object) $cuenta;
        }

        $normalizePlanPortal = static function (?string $raw): array {
            $p = strtolower(trim((string) $raw));
            $p = str_replace([' ', '-'], '_', $p);
            $p = preg_replace('/_+/', '_', $p) ?: '';

            $cycle = null;

            if (str_ends_with($p, '_mensual')) {
                $cycle = 'monthly';
                $p = substr($p, 0, -8);
            } elseif (str_ends_with($p, '_anual')) {
                $cycle = 'yearly';
                $p = substr($p, 0, -6);
            } elseif (str_ends_with($p, '_monthly')) {
                $cycle = 'monthly';
                $p = substr($p, 0, -8);
            } elseif (str_ends_with($p, '_yearly')) {
                $cycle = 'yearly';
                $p = substr($p, 0, -7);
            } elseif (str_ends_with($p, '_annual')) {
                $cycle = 'yearly';
                $p = substr($p, 0, -7);
            }

            $base = $p !== '' ? $p : 'free';
            $isPro = in_array($base, ['pro', 'premium', 'empresa', 'business'], true);

            return [
                'raw'       => strtoupper(trim((string) $raw)),
                'plan'      => $isPro ? 'PRO' : 'FREE',
                'plan_key'  => $isPro ? 'pro' : 'free',
                'plan_norm' => $base,
                'is_pro'    => $isPro,
                'cycle'     => $cycle,
            ];
        };

        $adminPlanRaw = '';
        $adminCycle   = '';

        try {
            $adminConns = ['mysql_admin', 'mysql', 'mysql_clientes'];
            $adminTables = ['accounts', 'cuentas', 'clientes'];

            $planColumns = [
                'plan_actual',
                'tipo_cuenta',
                'plan',
                'paquete',
                'licencia',
                'membresia',
            ];

            $cycleColumns = [
                'billing_cycle',
                'modo_cobro',
                'ciclo',
                'periodicidad',
            ];

            $cuentaId = isset($cuenta->id) && is_numeric($cuenta->id) ? (int) $cuenta->id : null;

            $adminId = isset($cuenta->admin_account_id) && is_numeric($cuenta->admin_account_id)
                ? (int) $cuenta->admin_account_id
                : null;

            $rfcCandidates = array_values(array_unique(array_filter([
                strtoupper(trim((string) ($cuenta->rfc ?? ''))),
                strtoupper(trim((string) ($cuenta->rfc_padre ?? ''))),
            ], fn ($v) => $v !== '' && strlen($v) >= 12)));

            $emailCandidates = array_values(array_unique(array_filter([
                strtolower(trim((string) ($cuenta->email ?? ''))),
                strtolower(trim((string) ($user->email ?? ''))),
            ], fn ($v) => $v !== '')));

            foreach ($adminConns as $admConn) {
                foreach ($adminTables as $adminTable) {
                    if (!Schema::connection($admConn)->hasTable($adminTable)) {
                        continue;
                    }

                    $columns = Schema::connection($admConn)->getColumnListing($adminTable);

                    $hasId = in_array('id', $columns, true);
                    $hasCuentaId = in_array('cuenta_id', $columns, true);
                    $hasRfc = in_array('rfc', $columns, true);
                    $hasCorreo = in_array('correo_contacto', $columns, true);
                    $hasEmail = in_array('email', $columns, true);

                    $acc = null;

                    if ($adminId && $hasId) {
                        $acc = DB::connection($admConn)
                            ->table($adminTable)
                            ->where('id', $adminId)
                            ->first();
                    }

                    if (!$acc && $cuentaId && $hasCuentaId) {
                        $acc = DB::connection($admConn)
                            ->table($adminTable)
                            ->where('cuenta_id', $cuentaId)
                            ->first();
                    }

                    if (!$acc && $hasRfc) {
                        foreach ($rfcCandidates as $rfcSearch) {
                            $acc = DB::connection($admConn)
                                ->table($adminTable)
                                ->whereRaw('UPPER(rfc) = ?', [$rfcSearch])
                                ->first();

                            if ($acc) {
                                break;
                            }
                        }
                    }

                    if (!$acc && $hasCorreo) {
                        foreach ($emailCandidates as $emailSearch) {
                            $acc = DB::connection($admConn)
                                ->table($adminTable)
                                ->whereRaw('LOWER(correo_contacto) = ?', [$emailSearch])
                                ->first();

                            if ($acc) {
                                break;
                            }
                        }
                    }

                    if (!$acc && $hasEmail) {
                        foreach ($emailCandidates as $emailSearch) {
                            $acc = DB::connection($admConn)
                                ->table($adminTable)
                                ->whereRaw('LOWER(email) = ?', [$emailSearch])
                                ->first();

                            if ($acc) {
                                break;
                            }
                        }
                    }

                    if (!$acc) {
                        continue;
                    }

                    foreach ($planColumns as $planColumn) {
                        if (in_array($planColumn, $columns, true) && trim((string) ($acc->{$planColumn} ?? '')) !== '') {
                            $adminPlanRaw = trim((string) $acc->{$planColumn});
                            break;
                        }
                    }

                    foreach ($cycleColumns as $cycleColumn) {
                        if (in_array($cycleColumn, $columns, true) && trim((string) ($acc->{$cycleColumn} ?? '')) !== '') {
                            $adminCycle = trim((string) $acc->{$cycleColumn});
                            break;
                        }
                    }

                    if ($adminPlanRaw !== '') {
                        break 2;
                    }
                }
            }
        } catch (\Throwable $e) {
            $adminPlanRaw = '';
            $adminCycle   = '';
        }

        $resolved = $adminPlanRaw !== ''
            ? $normalizePlanPortal($adminPlanRaw)
            : $normalizePlanPortal((string) ($summary['plan_raw'] ?? $summary['plan'] ?? $cuenta->plan_actual ?? $cuenta->plan ?? 'free'));

        $summaryPlanNorm = strtolower(trim((string) (
            $summary['plan_raw']
            ?? $summary['plan_norm']
            ?? $summary['plan_key']
            ?? $summary['plan']
            ?? ''
        )));

        $summaryPlanNorm = str_replace([' ', '-'], '_', $summaryPlanNorm);

        $summaryIsPro =
            (bool) ($summary['is_pro'] ?? false)
            || in_array($summaryPlanNorm, [
                'pro',
                'pro_mensual',
                'pro_anual',
                'premium',
                'premium_mensual',
                'premium_anual',
                'empresa',
                'business',
            ], true)
            || str_starts_with($summaryPlanNorm, 'pro_');

        $isPro = (bool) ($resolved['is_pro'] ?? false) || $summaryIsPro;

        $plan    = $isPro ? 'PRO' : 'FREE';
        $planKey = $isPro ? 'pro' : 'free';

        $summary['is_pro']    = $isPro;
        $summary['plan']      = $plan;
        $summary['plan_norm'] = $planKey;
        $summary['plan_raw']  = $adminPlanRaw ?: ($summary['plan_raw'] ?? $summary['plan'] ?? 'FREE');

        if ($adminCycle !== '') {
            $summary['cycle'] = $adminCycle;
        }

        $accountFeatures = [
            'is_pro'             => $isPro,
            'blocked'            => (bool) ($summary['blocked'] ?? false),

            // BASE
            'cfdi_manual'        => true,
            'cfdi_emitidos'      => true,
            'cfdi_descargas'     => true,
            'cfdi_cancelacion'   => true,
            'catalogos'          => true,

            // PRO
            'cfdi_masivo'        => $isPro,
            'excel_templates'    => $isPro,
            'batch_processing'   => $isPro,
            'nomina_masiva'      => $isPro,
            'cfdi_nomina_pro'    => $isPro,
            'rep_masivo'         => $isPro,
            'carta_porte_masiva' => $isPro,
            'api_integrations'   => $isPro,
            'automation_rules'   => $isPro,
        ];

        return view('cliente.facturacion.index', [
            'summary'         => $summary,
            'plan'            => $plan,
            'planKey'         => $planKey,
            'isPro'           => $isPro,
            'accountFeatures' => $accountFeatures,

            'period_from' => $from,
            'period_to'   => $to,
            'kpis'        => $kpis,
            'series'      => $series,
            'cfdis'       => $cfdis,
            'cfdi'        => $current,
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
            return collect();
        }
        try {
            $q = DB::connection($connToUse)->table($table);
            if ($scope) $scope($q);
            if ($orderBy) $q->orderByRaw($orderBy);
            if ($limit > 0) $q->limit($limit);
            return $q->get($columns);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    

    /** Formulario: Nuevo Documento (robusto ante tablas faltantes). */
    public function create(Request $request): View
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        // ✅ EMISORES: sólo los de la cuenta, desde tabla emisores
        $emisores = collect();
        if ($cuenta) {
            try {
                $emisores = Emisor::query()
                    ->where('cuenta_id', $cuenta->id)
                    ->orderByRaw("COALESCE(nombre_comercial, razon_social, '') ASC")
                    ->get(['id','rfc','razon_social','nombre_comercial']);
            } catch (\Throwable $e) {
                $emisores = collect();
            }
        }

        // Receptores de la cuenta
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

        // Productos de la cuenta
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

        return view('cliente.facturacion.create', [
            'emisores'   => $emisores,
            'receptores' => $receptores,
            'productos'  => $productos,
        ]);
    }

    protected function onlyExistingColumnsForInsert(string $table, string $conn, array $payload): array
    {
        try {
            $columns = Schema::connection($conn)->getColumnListing($table);
        } catch (\Throwable $e) {
            return $payload;
        }

        return collect($payload)
            ->filter(fn ($value, string $key) => in_array($key, $columns, true))
            ->all();
    }

    /** Guardar borrador fiscal CFDI 4.0. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id'          => 'required|integer',
            'receptor_id'         => 'required|integer',

            'version_cfdi'        => 'nullable|string|max:10',
            'tipo_comprobante'    => 'nullable|string|max:5',
            'tipo_documento'      => 'nullable|string|max:5',

            'serie'               => 'nullable|string|max:10',
            'folio'               => 'nullable|string|max:20',
            'fecha'               => 'nullable|date',

            'moneda'              => 'nullable|string|max:10',
            'metodo_pago'         => 'nullable|string|max:10',
            'forma_pago'          => 'nullable|string|max:10',
            'condiciones_pago'    => 'nullable|string|max:120',

            'uso_cfdi'            => 'nullable|string|max:10',
            'regimen_receptor'    => 'nullable|string|max:10',
            'cp_receptor'         => 'nullable|string|max:10',

            'tipo_relacion'       => 'nullable|string|max:10',
            'uuid_relacionado'    => 'nullable|string|max:60',

            'observaciones'       => 'nullable|string|max:2000',

            'conceptos'           => 'required|array|min:1',
            'conceptos.*.producto_id'     => 'nullable|integer',
            'conceptos.*.descripcion'     => 'required|string|max:500',
            'conceptos.*.cantidad'        => 'required|numeric|min:0.0001',
            'conceptos.*.precio_unitario' => 'required|numeric|min:0',
            'conceptos.*.iva_tasa'        => 'nullable|numeric|min:0',
        ]);

        $metodoPago = strtoupper((string) ($data['metodo_pago'] ?? 'PUE'));
        $formaPago  = strtoupper((string) ($data['forma_pago'] ?? '03'));

        if ($metodoPago === 'PPD') {
            $formaPago = '99';
        }

        $subtotal = 0.0;
        $iva      = 0.0;
        $total    = 0.0;

        foreach ($data['conceptos'] as $c) {
            $cant = (float) $c['cantidad'];
            $ppu  = (float) $c['precio_unitario'];
            $tasa = isset($c['iva_tasa']) ? (float) $c['iva_tasa'] : 0.16;

            $lineSubtotal = round($cant * $ppu, 4);
            $lineIva      = round($lineSubtotal * $tasa, 4);
            $lineTotal    = round($lineSubtotal + $lineIva, 4);

            $subtotal += $lineSubtotal;
            $iva      += $lineIva;
            $total    += $lineTotal;
        }

        $subtotal = round($subtotal, 2);
        $iva      = round($iva, 2);
        $total    = round($total, 2);

        $conn          = $this->cfdiConn();
        $cfdiModel     = new Cfdi;
        $conceptoModel = new CfdiConcepto;

        $cfdiTable     = $cfdiModel->getTable();
        $conceptoTable = $conceptoModel->getTable();

        $uuidTemp = (string) \Illuminate\Support\Str::uuid();

        DB::connection($conn)->transaction(function () use (
            $conn,
            $cfdiTable,
            $conceptoTable,
            $data,
            $subtotal,
            $iva,
            $total,
            $uuidTemp,
            $metodoPago,
            $formaPago
        ) {
            $now = now();

            $cfdiPayload = [
                'cliente_id'          => $data['cliente_id'],
                'receptor_id'         => $data['receptor_id'] ?? null,

                'uuid'                => $uuidTemp,
                'serie'               => $data['serie'] ?? null,
                'folio'               => $data['folio'] ?? null,
                'fecha'               => $data['fecha'] ?? $now,

                'version_cfdi'        => $data['version_cfdi'] ?? '4.0',
                'tipo_comprobante'    => $data['tipo_comprobante'] ?? ($data['tipo_documento'] ?? 'I'),
                'tipo_documento'      => $data['tipo_documento'] ?? ($data['tipo_comprobante'] ?? 'I'),

                'moneda'              => $data['moneda'] ?? 'MXN',
                'metodo_pago'         => $metodoPago,
                'forma_pago'          => $formaPago,
                'condiciones_pago'    => $data['condiciones_pago'] ?? null,

                'uso_cfdi'            => $data['uso_cfdi'] ?? 'G03',
                'regimen_receptor'    => $data['regimen_receptor'] ?? null,
                'cp_receptor'         => $data['cp_receptor'] ?? null,

                'tipo_relacion'       => $data['tipo_relacion'] ?? null,
                'uuid_relacionado'    => $data['uuid_relacionado'] ?? null,

                'subtotal'            => $subtotal,
                'iva'                 => $iva,
                'total'               => $total,

                'saldo_original'      => $total,
                'saldo_pagado'        => 0,
                'saldo_pendiente'     => $metodoPago === 'PPD' ? $total : 0,

                'estatus'             => 'borrador',
                'estado_pago'         => $metodoPago === 'PPD' ? 'pendiente' : 'no_requiere_rep',

                'observaciones'       => $data['observaciones'] ?? null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            $cfdiPayload = $this->onlyExistingColumnsForInsert($cfdiTable, $conn, $cfdiPayload);

            $cfdiId = DB::connection($conn)
                ->table($cfdiTable)
                ->insertGetId($cfdiPayload);

            foreach ($data['conceptos'] as $c) {
                $cant = (float) $c['cantidad'];
                $ppu  = (float) $c['precio_unitario'];
                $tasa = isset($c['iva_tasa']) ? (float) $c['iva_tasa'] : 0.16;

                $lineSubtotal = round($cant * $ppu, 4);
                $lineIva      = round($lineSubtotal * $tasa, 4);
                $lineTotal    = round($lineSubtotal + $lineIva, 4);

                $conceptoPayload = [
                    'cfdi_id'         => $cfdiId,
                    'producto_id'     => $c['producto_id'] ?? null,
                    'descripcion'     => $c['descripcion'],
                    'cantidad'        => $cant,
                    'precio_unitario' => $ppu,
                    'iva_tasa'        => $tasa,
                    'subtotal'        => round($lineSubtotal, 2),
                    'iva'             => round($lineIva, 2),
                    'total'           => round($lineTotal, 2),
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];

                $conceptoPayload = $this->onlyExistingColumnsForInsert($conceptoTable, $conn, $conceptoPayload);

                DB::connection($conn)
                    ->table($conceptoTable)
                    ->insert($conceptoPayload);
            }
        });

        return redirect()
            ->route('cliente.facturacion.index', ['month' => now()->format('Y-m')])
            ->with('ok', 'Borrador CFDI 4.0 creado correctamente.');
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
