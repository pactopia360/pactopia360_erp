<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\SatDownloadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SatDescargaController extends Controller
{
    public function __construct(private readonly SatDownloadService $service) {}

    private function cu(): ?object { return auth('web')->user(); }
    private function cuId(): string
    {
        $u = $this->cu();
        return (string) ($u?->cuenta_id ?? $u?->cuenta?->id ?? $u?->id ?? '');
    }
    private function trace(): string { return (string) Str::ulid(); }

    /* ==================== VISTA ==================== */
    public function index(Request $request): View
    {
        $trace    = $this->trace();
        $user     = Auth::guard('web')->user();
        $cuentaId = $this->cuId();

        $pendientes = SatDownload::where('cuenta_id', $cuentaId)->whereIn('status', ['pending','processing'])->count();
        $listas     = SatDownload::where('cuenta_id', $cuentaId)->whereIn('status', ['ready','done','listo'])->count();
        $ultimas    = SatDownload::where('cuenta_id', $cuentaId)->count();

        $initialRows = SatDownload::where('cuenta_id', $cuentaId)
            ->orderByDesc('created_at')->limit(30)
            ->get(['id','rfc','tipo','date_from','date_to','status','package_id','request_id','created_at'])
            ->map(function ($r) {
                return [
                    'dlid'       => (string)$r->id,
                    'request_id' => (string)($r->request_id ?? ''),
                    'rfc'        => strtoupper((string)$r->rfc),
                    'razon'      => '',
                    'tipo'       => (string)$r->tipo,
                    'desde'      => optional($r->date_from)->format('Y-m-d'),
                    'hasta'      => optional($r->date_to)->format('Y-m-d'),
                    'estado'     => (string)$r->status,
                    'fecha'      => optional($r->created_at)->format('Y-m-d'),
                    'package_id' => (string)($r->package_id ?? ''),
                ];
            })->values()->all();

        $credList = SatCredential::where('cuenta_id', $cuentaId)
            ->get(['rfc','razon_social','validated_at','cer_path','key_path'])
            ->map(fn($c)=>[
                'rfc'          => strtoupper(trim((string)$c->rfc)),
                'razon_social' => (string)($c->razon_social ?? ''),
                'validated_at' => $c->validated_at ? $c->validated_at->toDateTimeString() : null,
                'has_files'    => filled($c->cer_path) && filled($c->key_path),
            ])->values()->all();

        Log::info('[SAT:index] Render vista SAT', [
            'trace_id'  => $trace, 'user_id' => $user?->id, 'cuenta_id' => $cuentaId,
            'pend'      => $pendientes, 'listas' => $listas, 'ult' => $ultimas,
        ]);

        return view('cliente.sat.index', [
            'u'           => $user,
            'cuenta'      => $user?->cuenta,
            'trace'       => $trace,
            'pendientes'  => $pendientes,
            'listas'      => $listas,
            'ultimas'     => $ultimas,
            'initialRows' => $initialRows,
            'credList'    => $credList,
        ]);
    }

    /* ==================== CREDENCIALES ==================== */
    public function storeCredentials(Request $request)
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        Log::info('[SAT:storeCredentials] IN', [
            'trace_id'=>$trace,
            'cuenta_id'=>$cuentaId,
            'rfc_in'=>$request->input('rfc'),
            'alias_in'=>$request->input('alias'),
            'has_cer'=>$request->hasFile('cer'),
            'has_key'=>$request->hasFile('key'),
        ]);

        $data = $request->validate([
            'rfc'          => ['required','string','min:12','max:13'],
            'alias'        => ['nullable','string','max:190'],
            'cer'          => ['nullable','file'],
            'key'          => ['nullable','file'],
            'key_password' => ['nullable','string','min:1'],
            'pwd'          => ['nullable','string','min:1'],
        ]);

        $cer = $request->file('cer');
        $key = $request->file('key');
        if ($cer && strtolower($cer->getClientOriginalExtension()) !== 'cer') {
            return response()->json(['ok'=>false,'msg'=>'El archivo .cer no es válido','trace_id'=>$trace], 422);
        }
        if ($key && strtolower($key->getClientOriginalExtension()) !== 'key') {
            return response()->json(['ok'=>false,'msg'=>'El archivo .key no es válido','trace_id'=>$trace], 422);
        }

        $password = $data['key_password'] ?? $data['pwd'] ?? '';
        $alias    = $data['alias'] ?? null;

        try {
            $cred = $this->service->upsertCredentials(
                $cuentaId,
                strtoupper($data['rfc']),
                $cer, $key, $password
            );

            if ($alias !== null && $alias !== '') {
                $cred->razon_social = $alias;
                $cred->save();
            }

            $ok = $this->service->validateCredentials($cred);

            Log::info('[SAT:storeCredentials] Guardadas', [
                'trace_id'=>$trace,
                'cuenta_id'=>$cuentaId,
                'rfc'=>$cred->rfc,
                'ok'=>$ok,
                'id'=>$cred->getKey(),
                'pk_type'=>$cred->getKeyType(),
                'incrementing'=>$cred->getIncrementing(),
            ]);

            return response()->json([
                'ok' => (bool)$ok,
                'trace_id'=>$trace,
                'rfc'=>$cred->rfc,
                'alias'=>$cred->razon_social,
            ]);

        } catch (\Throwable $e) {
            Log::error('[SAT:storeCredentials] Error', [
                'trace_id'=>$trace,
                'cuenta_id'=>$cuentaId,
                'ex'=>$e->getMessage(),
            ]);
            return response()->json(['ok'=>false,'msg'=>'Error guardando credenciales','trace_id'=>$trace], 500);
        }
    }

    public function registerRfc(Request $request)
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $data = $request->validate([
            'rfc'   => ['required','string','min:12','max:13'],
            'alias' => ['nullable','string','max:190'],
        ]);
        $rfc   = strtoupper($data['rfc']);
        $alias = $data['alias'] ?? null;

        try {
            $cred = SatCredential::updateOrCreate(
                ['cuenta_id' => $cuentaId, 'rfc' => $rfc],
                ['razon_social' => $alias]
            );

            Log::info('[SAT:registerRfc] Registrado', ['trace_id'=>$trace,'cuenta_id'=>$cuentaId,'rfc'=>$rfc]);

            return response()->json([
                'ok'=>true,'trace_id'=>$trace,'rfc'=>$cred->rfc,'alias'=>$cred->razon_social
            ]);
        } catch (\Throwable $e) {
            Log::error('[SAT:registerRfc] Error', ['trace_id'=>$trace,'cuenta_id'=>$cuentaId,'ex'=>$e->getMessage()]);
            return response()->json(['ok'=>false,'msg'=>'No se pudo registrar','trace_id'=>$trace], 500);
        }
    }

    public function saveAlias(Request $request)
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $data = $request->validate([
            'rfc'   => ['required','string','min:12','max:13'],
            'alias' => ['nullable','string','max:190'],
        ]);

        try {
            $cred = SatCredential::where('cuenta_id',$cuentaId)
                ->where('rfc', strtoupper($data['rfc']))->firstOrFail();

            $cred->razon_social = $data['alias'] ?? null;
            $cred->save();

            Log::info('[SAT:saveAlias] Alias actualizado', ['trace_id'=>$trace,'cuenta_id'=>$cuentaId,'rfc'=>$cred->rfc]);

            return response()->json(['ok'=>true,'trace_id'=>$trace]);
        } catch (\Throwable $e) {
            Log::error('[SAT:saveAlias] Error', ['trace_id'=>$trace,'cuenta_id'=>$cuentaId,'ex'=>$e->getMessage()]);
            return response()->json(['ok'=>false,'msg'=>'No se pudo actualizar','trace_id'=>$trace], 500);
        }
    }

    /* ==================== SOLICITUD ==================== */
    public function requestList(Request $request)
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = (string) ($user->cuenta_id ?? $user->id);

        $data = $request->validate([
            'rfcs'      => ['nullable'],
            'rfc'       => ['nullable','string','min:12','max:13'],
            'tipo'      => ['required', Rule::in(['recibidos','emitidos','ambos'])],
            'date_from' => ['nullable','date'],
            'date_to'   => ['nullable','date'],
            'desde'     => ['nullable','date'],
            'hasta'     => ['nullable','date'],
            'auto'      => ['nullable','boolean'],
        ]);

        $from = $data['date_from'] ?? $data['desde'] ?? null;
        $to   = $data['date_to']   ?? $data['hasta'] ?? null;
        if (!$from || !$to) {
            return response()->json(['ok'=>false,'msg'=>'Rango de fechas requerido','trace_id'=>$trace], 422);
        }
        if (Carbon::parse($from)->greaterThan(Carbon::parse($to))) {
            return response()->json(['ok'=>false,'msg'=>'Rango inválido','trace_id'=>$trace], 422);
        }

        $rfcs = [];
        if ($request->has('rfcs')) {
            $rfcs = is_array($request->rfcs)
                ? $request->rfcs
                : (json_decode((string)$request->rfcs,true) ?: []);
        } elseif (!empty($data['rfc'])) {
            $rfcs = [strtoupper($data['rfc'])];
        } else {
            $rfcs = SatCredential::where('cuenta_id',$cuentaId)
                ->pluck('rfc')->map(fn($r)=>strtoupper($r))->unique()->values()->all();
        }
        $rfcs = array_values(array_unique(array_filter(array_map('strtoupper',$rfcs))));
        if (empty($rfcs)) {
            return response()->json(['ok'=>false,'msg'=>'No hay RFCs seleccionados','trace_id'=>$trace], 422);
        }

        $planActual = strtoupper((string)($user?->cuenta?->plan_actual ?? 'FREE'));
        $auto = (bool)($data['auto'] ?? false);

        if (in_array($planActual, ['FREE','BASIC'], true)) {
            if (!$auto) {
                $ya = SatDownload::where('cuenta_id',$cuentaId)->count();
                if ($ya >= 1) {
                    Log::notice('[SAT:requestList] FREE limit hit', ['cuenta_id'=>$cuentaId, 'ya'=>$ya]);
                    return response()->json([
                        'ok'=>false,
                        'msg'=>'Plan FREE: solo 1 solicitud manual disponible. Activa PRO para ilimitadas y automatización.',
                        'trace_id'=>$trace
                    ], 403);
                }
            }
        }

        $tipo = $data['tipo'];
        $created = [];
        $faltantes = [];

        try {
            foreach ($rfcs as $rfc) {
                $cred = SatCredential::where('cuenta_id',$cuentaId)->where('rfc',$rfc)->first();
                if (!$cred) {
                    $faltantes[] = $rfc;
                    continue;
                }
                $tipos = ($tipo === 'ambos') ? ['emitidos','recibidos'] : [$tipo];
                foreach ($tipos as $t) {
                    $download = $this->service->requestPackages(
                        $cred, new \DateTimeImmutable($from), new \DateTimeImmutable($to), $t
                    );
                    if ($auto && $download && $download->exists) {
                        if (Schema::connection($download->getConnectionName())->hasColumn($download->getTable(),'auto')) {
                            $download->auto = true;
                            $download->save();
                        }
                    }
                    $created[] = $download->request_id;
                }
            }

            Log::info('[SAT:requestList] Solicitudes enviadas', [
                'trace_id'=>$trace,'cuenta_id'=>$cuentaId,'rfcs'=>$rfcs,'faltantes'=>$faltantes,'tipo'=>$tipo,'count'=>count($created),
            ]);

            return response()->json([
                'ok'       => true,
                'trace_id' => $trace,
                'requests' => $created,
                'missing'  => $faltantes,
            ]);

        } catch (\Throwable $e) {
            Log::error('[SAT:requestList] Error', [
                'trace_id'=>$trace,'cuenta_id'=>$cuentaId,'ex'=>$e->getMessage(),
            ]);
            return response()->json(['ok'=>false,'msg'=>'No fue posible solicitar','trace_id'=>$trace], 500);
        }
    }

    /* ==================== VERIFICAR ==================== */
    public function verify(Request $request)
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();
        $user     = $this->cu();
        if (!$user) return response()->json(['ok'=>false,'msg'=>'unauth'], 401);

        if (app()->environment('local')) {
            $one = SatDownload::where('cuenta_id',$cuentaId)->where('status','pending')->latest()->first();
            if ($one) {
                $one->status     = 'done';
                $one->package_id = $one->package_id ?: ('PKG-'.substr(md5($one->request_id ?? ''),0,10));
                $one->save();
                Log::info('[SAT:verify] Demo promote -> done', [
                    'trace_id'=>$trace,'id'=>$one->id,'request'=>$one->request_id,'pkg'=>$one->package_id
                ]);
            }
        }

        $pendingCount = SatDownload::where('cuenta_id',$cuentaId)->whereIn('status',['pending','processing'])->count();
        $readyCount   = SatDownload::where('cuenta_id',$cuentaId)->whereIn('status',['ready','done','listo'])->count();

        Log::info('[SAT:verify] Ping', ['trace_id'=>$trace,'cuenta_id'=>$cuentaId,'pending'=>$pendingCount,'ready'=>$readyCount]);

        return response()->json([
            'ok'      => true,
            'trace_id'=> $trace,
            'pending' => $pendingCount,
            'ready'   => $readyCount,
        ]);
    }

    /* ==================== DESCARGA ==================== */
    public function download(Request $request)
    {
        return $this->downloadPackage($request);
    }

    public function downloadPackage(Request $request)
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $data = $request->validate([
            'id'          => ['nullable','string'],
            'download_id' => ['nullable','string'],
            'request_id'  => ['nullable','string'],
            'package_id'  => ['nullable','string'],
        ]);

        $downloadId = (string)($data['download_id'] ?? $data['id'] ?? '');
        $requestId  = (string)($data['request_id'] ?? '');

        try {
            if ($downloadId !== '') {
                $download = SatDownload::where('id',$downloadId)->where('cuenta_id',$cuentaId)->firstOrFail();
            } elseif ($requestId !== '') {
                $download = SatDownload::where('request_id',$requestId)->where('cuenta_id',$cuentaId)->firstOrFail();
            } else {
                abort(422, 'Falta download_id o request_id');
            }

            $cred = SatCredential::where('cuenta_id',$cuentaId)->where('rfc',$download->rfc)->firstOrFail();

            $pkgId    = $data['package_id'] ?? $download->package_id ?? null;
            $download = $this->service->downloadPackage($cred, $download, $pkgId);

            Log::info('[SAT:downloadPackage] Resultado', [
                'trace_id'=>$trace, 'id'=>$download->id,'status'=>$download->status,
                'zip_path'=>$download->zip_path,'error'=>$download->error_message ?? null,
            ]);

            if ($download->status === 'done' && $download->zip_path) {
                $rel = ltrim($download->zip_path, '/');
                if (Storage::disk('public')->exists($rel)) {
                    $fname = 'sat_' . ($download->request_id ?: ('pkg_'.$download->id)) . '.zip';
                    return response()->download(Storage::disk('public')->path($rel), $fname);
                }
                Log::warning('[SAT:downloadPackage] ZIP no encontrado', ['trace_id'=>$trace,'rel'=>$rel]);
                return back()->with('error','ZIP descargado pero no encontrado. Trace: '.$trace)->with('tab','download');
            }

            return back()->with('error',
                $download->error_message ? ($download->error_message.' · Trace: '.$trace) : ('No fue posible descargar el paquete. Trace: '.$trace)
            )->with('tab','download');

        } catch (\Throwable $e) {
            Log::error('[SAT:downloadPackage] Excepción', [
                'trace_id'=>$trace,'cuenta_id'=>$cuentaId,'ex'=>$e->getMessage(),
            ]);
            return back()->with('error','Error descargando paquete. Trace: '.$trace)->with('tab','download');
        }
    }

    public function zip(Request $request, string $id)
    {
        return $this->downloadZip($request, $id);
    }

    public function downloadZip(Request $request, string $id)
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $download = SatDownload::where('id',$id)->where('cuenta_id',$cuentaId)->firstOrFail();
        $rel = ltrim((string)$download->zip_path, '/');

        if ($download->status !== 'done' || $rel === '' || !Storage::disk('public')->exists($rel)) {
            Log::warning('[SAT:downloadZip] ZIP no listo/no existe', [
                'trace_id'=>$trace, 'id'=>$id, 'status'=>$download->status, 'zip_path'=>$download->zip_path,
            ]);
            abort(404);
        }

        Log::info('[SAT:downloadZip] Sirviendo archivo', ['trace_id'=>$trace,'id'=>$id,'path'=>Storage::disk('public')->path($rel)]);
        $fname = 'sat_' . ($download->request_id ?: ('pkg_'.$download->id)) . '.zip';
        return response()->download(Storage::disk('public')->path($rel), $fname);
    }

    /* ==================== REPORTE (DATOS REALES) ==================== */
    public function report(Request $request): StreamedResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();
        $user     = $this->cu();

        $periodo = (string)$request->input('periodo', '');
        $fmt     = strtolower((string)$request->input('fmt', 'csv'));
        if (!in_array($fmt, ['csv','xlsx'], true)) $fmt = 'csv';

        // Rango
        if ($periodo !== '' && preg_match('~^\d{4}\-\d{2}$~', $periodo)) {
            $from = Carbon::createFromFormat('Y-m', $periodo)->startOfMonth();
            $to   = (clone $from)->endOfMonth();
        } else {
            $from = Carbon::parse($request->input('date_from', now()->startOfMonth()->toDateString()))->startOfDay();
            $to   = Carbon::parse($request->input('date_to',   now()->endOfMonth()->toDateString()))->endOfDay();
        }
        if ($from->greaterThan($to)) { $t = $from; $from = $to; $to = $t; }

        // ¿Tenemos tabla y columnas mínimas?
        $useReal = $this->cfdiAvailable();

        $rows = $useReal
            ? $this->buildCfdiRows($cuentaId, $from, $to)
            : $this->buildAggregatedRows($cuentaId, $from, $to, $request->input('rfc'), $request->input('tipo_db'));

        // Header
        if ($useReal) {
            array_unshift($rows, ['UUID','RFC Emisor','RFC Receptor','Fecha','Subtotal','IVA','Total','Metodo','UsoCFDI']);
        } else {
            array_unshift($rows, ['RFC','Tipo','Status','Desde','Hasta','#Solicitudes','#Paquetes']);
        }

        Log::info('[SAT:report] Generando', [
            'trace_id'=>$trace,'user_id'=>$user?->id,'fmt'=>$fmt,
            'from'=>$from->toDateString(),'to'=>$to->toDateString(),
            'rows'=>count($rows),'source'=>$useReal ? 'cfdis' : 'sat_downloads(fallback)',
        ]);

        $base = $useReal ? 'reporte_cfdi' : 'reporte_descargas';
        $filename = $base . '_' . ($periodo ?: $from->format('Y-m')) . ($fmt === 'xlsx' ? '.xlsx' : '.csv');

        if ($fmt === 'xlsx') return $this->streamXlsx($rows, $filename, $trace);

        $callback = static function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            foreach ($rows as $row) { fputcsv($out, $row); }
            fclose($out);
        };
        $headers = [
            'X-Debug-Trace-Id'    => $trace,
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];
        return response()->stream($callback, 200, $headers);
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $fmt = strtolower((string)($request->input('format') ?? $request->input('fmt') ?? 'csv'));
        $request->merge(['fmt' => $fmt]);
        return $this->report($request);
    }

    /* ===== Helpers de REPORTE reales/fallback ===== */

    /** Checa existencia de cfdis + columnas mínimas (uuid, fecha, subtotal, total) */
    private function cfdiAvailable(): bool
    {
        $conn = 'mysql_clientes';
        if (!Schema::connection($conn)->hasTable('cfdis')) return false;

        foreach (['uuid','fecha','subtotal','total'] as $c) {
            if (!Schema::connection($conn)->hasColumn('cfdis', $c)) return false;
        }
        // Las demás son opcionales: iva, metodo_pago, uso_cfdi, cliente_id, receptor_id
        return true;
    }

    /**
     * Construye filas reales desde cfdis. Intenta RFCs por joins opcionales:
     * - clientes (emisor)  : clientes.id = cfdis.cliente_id  -> clientes.rfc
     * - receptores (rec)   : receptores.id = cfdis.receptor_id -> receptores.rfc
     */
    private function buildCfdiRows(string $cuentaId, Carbon $from, Carbon $to): array
    {
        $conn = 'mysql_clientes';
        $hasClientes   = Schema::connection($conn)->hasTable('clientes')   && Schema::connection($conn)->hasColumn('clientes','rfc');
        $hasReceptores = Schema::connection($conn)->hasTable('receptores') && Schema::connection($conn)->hasColumn('receptores','rfc');

        $q = DB::connection($conn)->table('cfdis as c')
            ->whereBetween('c.fecha', [$from->toDateString(), $to->toDateString()]);

        // Si tienes segmentación por cuenta/tenant en cfdis, añade aquí:
        // ->where('c.cuenta_id', $cuentaId)  // sólo si existe esa columna en cfdis.
        if (Schema::connection($conn)->hasColumn('cfdis','cuenta_id')) {
            $q->where('c.cuenta_id', $cuentaId);
        }

        if ($hasClientes)   $q->leftJoin('clientes as em','em.id','=','c.cliente_id');
        if ($hasReceptores) $q->leftJoin('receptores as re','re.id','=','c.receptor_id');

        $select = [
            'c.uuid',
            DB::raw('DATE(c.fecha) as fecha'),
            DB::raw('COALESCE(c.subtotal,0) as subtotal'),
            DB::raw('COALESCE(c.total,0) as total'),
        ];
        // IVA opcional
        if (Schema::connection($conn)->hasColumn('cfdis','iva')) {
            $select[] = DB::raw('COALESCE(c.iva,0) as iva');
        } else {
            $select[] = DB::raw('0 as iva');
        }
        // método/uso opcionales
        $select[] = Schema::connection($conn)->hasColumn('cfdis','metodo_pago')
            ? DB::raw('COALESCE(c.metodo_pago,"") as metodo_pago')
            : DB::raw('"" as metodo_pago');
        $select[] = Schema::connection($conn)->hasColumn('cfdis','uso_cfdi')
            ? DB::raw('COALESCE(c.uso_cfdi,"") as uso_cfdi')
            : DB::raw('"" as uso_cfdi');

        // RFC emisor/receptor opcionales
        $select[] = $hasClientes   ? DB::raw('UPPER(COALESCE(em.rfc,"")) as rfc_emisor')   : DB::raw('"" as rfc_emisor');
        $select[] = $hasReceptores ? DB::raw('UPPER(COALESCE(re.rfc,"")) as rfc_receptor') : DB::raw('"" as rfc_receptor');

        $rows = [];
        $q->select($select)->orderBy('c.fecha')->chunk(2000, function ($chunk) use (&$rows) {
            foreach ($chunk as $r) {
                $rows[] = [
                    (string)$r->uuid,
                    (string)($r->rfc_emisor   ?? ''),
                    (string)($r->rfc_receptor ?? ''),
                    (string)$r->fecha,
                    (string)$r->subtotal,
                    (string)$r->iva,
                    (string)$r->total,
                    (string)($r->metodo_pago ?? ''),
                    (string)($r->uso_cfdi    ?? ''),
                ];
            }
        });

        return $rows;
    }

    /** Fallback: agregados por descargas */
    private function buildAggregatedRows(string $cuentaId, Carbon $from, Carbon $to, ?string $rfc, ?string $tipoDb): array
    {
        $q = SatDownload::query()
            ->where('cuenta_id',$cuentaId)
            ->whereBetween('date_from', [$from->toDateString(), $to->toDateString()]);

        if ($rfc)    $q->where('rfc', strtoupper($rfc));
        if ($tipoDb) $q->where('tipo', $tipoDb);

        return $q->select([
                'rfc','tipo','status',
                DB::raw('MIN(date_from) as df'),
                DB::raw('MAX(date_to) as dt'),
                DB::raw('COUNT(*) as cnt'),
                DB::raw('SUM(CASE WHEN package_id IS NOT NULL AND package_id <> "" THEN 1 ELSE 0 END) as pkgs'),
            ])
            ->groupBy('rfc','tipo','status')
            ->orderBy('rfc')->orderBy('tipo')->orderBy('status')
            ->get()
            ->map(fn($r)=>[
                (string)$r->rfc,(string)$r->tipo,(string)$r->status,(string)$r->df,(string)$r->dt,(int)$r->cnt,(int)$r->pkgs,
            ])->toArray();
    }

    /* ==================== XLSX mínimo ==================== */
    private function streamXlsx(array $rows, string $filename, string $trace): StreamedResponse
    {
        $headers = [
            'X-Debug-Trace-Id'    => $trace,
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = static function () use ($rows) {
            $zip = new \ZipArchive();
            $tmp = tmpfile();
            $tmpPath = stream_get_meta_data($tmp)['uri'];
            $zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');
            $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');
            $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');
            $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets>
</workbook>');
            $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>');
            $rowsXml = [];
            foreach ($rows as $rIndex => $cols) {
                $cells = [];
                foreach ($cols as $cIndex => $val) {
                    $colLetter = self::colLetter($cIndex + 1);
                    $cellRef   = $colLetter . ($rIndex + 1);
                    $val       = (string) $val;
                    $cells[]   = '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.self::xml($val).'</t></is></c>';
                }
                $rowsXml[] = '<row r="'.($rIndex+1).'">'.implode('', $cells).'</row>';
            }
            $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetData>'.implode('', $rowsXml).'</sheetData>
</worksheet>';
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
            $zip->close();
            readfile($tmpPath);
            fclose($tmp);
        };

        return response()->stream($callback, 200, $headers);
    }

    private static function colLetter(int $n): string
    {
        $s = '';
        while ($n > 0) { $n--; $s = chr($n % 26 + 65) . $s; $n = intdiv($n, 26); }
        return $s;
    }
    private static function xml(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1|ENT_COMPAT, 'UTF-8');
    }
}
