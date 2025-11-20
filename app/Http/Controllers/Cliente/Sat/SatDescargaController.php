<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\SatDownloadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SatDescargaController extends Controller
{
    public function __construct(private readonly SatDownloadService $service) {}

    private function cu(): ?object
    {
        return auth('web')->user();
    }

    private function cuId(): string
    {
        $u = $this->cu();
        return (string) ($u?->cuenta_id ?? $u?->cuenta?->id ?? $u?->id ?? '');
    }

    private function trace(): string
    {
        return (string) Str::ulid();
    }

    /* ==================== VISTA ==================== */
    public function index(Request $request): View
    {
        $trace    = $this->trace();
        $user     = Auth::guard('web')->user();
        $cuentaId = $this->cuId();

        $pendientes = SatDownload::where('cuenta_id', $cuentaId)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        $listas = SatDownload::where('cuenta_id', $cuentaId)
            ->whereIn('status', ['ready', 'done', 'listo'])
            ->count();

        $ultimas = SatDownload::where('cuenta_id', $cuentaId)->count();

        $initialRows = SatDownload::where('cuenta_id', $cuentaId)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get([
                'id', 'rfc', 'tipo', 'date_from', 'date_to',
                'status', 'package_id', 'request_id', 'created_at',
            ])
            ->map(function ($r) {
                return [
                    'dlid'       => (string) $r->id,
                    'request_id' => (string) ($r->request_id ?? ''),
                    'rfc'        => strtoupper((string) $r->rfc),
                    'razon'      => '',
                    'tipo'       => (string) $r->tipo,
                    'desde'      => optional($r->date_from)->format('Y-m-d'),
                    'hasta'      => optional($r->date_to)->format('Y-m-d'),
                    'estado'     => (string) $r->status,
                    'fecha'      => optional($r->created_at)->format('Y-m-d'),
                    'package_id' => (string) ($r->package_id ?? ''),
                ];
            })
            ->values()
            ->all();

        $credList = SatCredential::where('cuenta_id', $cuentaId)
            ->get(['rfc', 'razon_social', 'validated_at', 'cer_path', 'key_path'])
            ->map(fn ($c) => [
                'rfc'          => strtoupper(trim((string) $c->rfc)),
                'razon_social' => (string) ($c->razon_social ?? ''),
                'validated_at' => $c->validated_at ? $c->validated_at->toDateTimeString() : null,
                'has_files'    => filled($c->cer_path) && filled($c->key_path),
            ])
            ->values()
            ->all();

        Log::info('[SAT:index] Render vista SAT', [
            'trace_id'  => $trace,
            'user_id'   => $user?->id,
            'cuenta_id' => $cuentaId,
            'pend'      => $pendientes,
            'listas'    => $listas,
            'ult'       => $ultimas,
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
    public function storeCredentials(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        Log::info('[SAT:storeCredentials] IN', [
            'trace_id'  => $trace,
            'cuenta_id' => $cuentaId,
            'rfc_in'    => $request->input('rfc'),
            'alias_in'  => $request->input('alias'),
            'has_cer'   => $request->hasFile('cer'),
            'has_key'   => $request->hasFile('key'),
        ]);

        $data = $request->validate([
            'rfc'          => ['required', 'string', 'min:12', 'max:13'],
            'alias'        => ['nullable', 'string', 'max:190'],
            'cer'          => ['nullable', 'file'],
            'key'          => ['nullable', 'file'],
            'key_password' => ['nullable', 'string', 'min:1'],
            'pwd'          => ['nullable', 'string', 'min:1'],
        ]);

        $cer = $request->file('cer');
        $key = $request->file('key');

        if ($cer && strtolower($cer->getClientOriginalExtension()) !== 'cer') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'El archivo .cer no es válido',
                'trace_id' => $trace,
            ], 422);
        }

        if ($key && strtolower($key->getClientOriginalExtension()) !== 'key') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'El archivo .key no es válido',
                'trace_id' => $trace,
            ], 422);
        }

        $password = $data['key_password'] ?? $data['pwd'] ?? '';
        $alias    = $data['alias'] ?? null;

        try {
            $cred = $this->service->upsertCredentials(
                $cuentaId,
                strtoupper($data['rfc']),
                $cer,
                $key,
                $password
            );

            if ($alias !== null && $alias !== '') {
                $cred->razon_social = $alias;
                $cred->save();
            }

            $ok = $this->service->validateCredentials($cred);

            Log::info('[SAT:storeCredentials] Guardadas', [
                'trace_id'     => $trace,
                'cuenta_id'    => $cuentaId,
                'rfc'          => $cred->rfc,
                'ok'           => $ok,
                'id'           => $cred->getKey(),
                'pk_type'      => $cred->getKeyType(),
                'incrementing' => $cred->getIncrementing(),
            ]);

            return response()->json([
                'ok'       => (bool) $ok,
                'trace_id' => $trace,
                'rfc'      => $cred->rfc,
                'alias'    => $cred->razon_social,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SAT:storeCredentials] Error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'ex'        => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'msg'      => 'Error guardando credenciales',
                'trace_id' => $trace,
            ], 500);
        }
    }

    public function registerRfc(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $data = $request->validate([
            'rfc'   => ['required', 'string', 'min:12', 'max:13'],
            'alias' => ['nullable', 'string', 'max:190'],
        ]);

        $rfc   = strtoupper($data['rfc']);
        $alias = $data['alias'] ?? null;

        try {
            $cred = SatCredential::updateOrCreate(
                ['cuenta_id' => $cuentaId, 'rfc' => $rfc],
                ['razon_social' => $alias]
            );

            Log::info('[SAT:registerRfc] Registrado', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfc,
            ]);

            return response()->json([
                'ok'       => true,
                'trace_id' => $trace,
                'rfc'      => $cred->rfc,
                'alias'    => $cred->razon_social,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SAT:registerRfc] Error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'ex'        => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'msg'      => 'No se pudo registrar',
                'trace_id' => $trace,
            ], 500);
        }
    }

    public function saveAlias(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $data = $request->validate([
            'rfc'   => ['required', 'string', 'min:12', 'max:13'],
            'alias' => ['nullable', 'string', 'max:190'],
        ]);

        try {
            $cred = SatCredential::where('cuenta_id', $cuentaId)
                ->where('rfc', strtoupper($data['rfc']))
                ->firstOrFail();

            $cred->razon_social = $data['alias'] ?? null;
            $cred->save();

            Log::info('[SAT:saveAlias] Alias actualizado', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $cred->rfc,
            ]);

            return response()->json([
                'ok'       => true,
                'trace_id' => $trace,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SAT:saveAlias] Error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'ex'        => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'msg'      => 'No se pudo actualizar',
                'trace_id' => $trace,
            ], 500);
        }
    }

    /* ==================== SOLICITUD ==================== */
    public function requestList(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $trace    = $this->trace();
        $user     = Auth::guard('web')->user();
        $cuentaId = $this->cuId();

        if (! $user) {
            abort(401);
        }

        $data = $request->validate([
            'rfc'  => ['required', 'string', 'max:13'],
            'from' => ['required', 'date'],
            'to'   => ['required', 'date'],
            'tipo' => ['nullable', 'string'],
        ]);

        $tipo = $data['tipo'] ?: 'emitidos';

        // Buscar credencial del RFC en esta cuenta
        $cred = SatCredential::where('cuenta_id', $cuentaId)
            ->where('rfc', strtoupper($data['rfc']))
            ->first();

        if (! $cred) {
            $msg = 'No se encontraron credenciales para el RFC seleccionado.';
            Log::warning('[SAT:requestList] RFC sin credenciales', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $data['rfc'],
            ]);

            $payload = [
                'ok'       => false,
                'trace_id' => $trace,
                'msg'      => $msg,
            ];

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($payload, 422);
            }

            return back()->with('error', $msg);
        }

        $from = Carbon::parse($data['from'])->startOfDay()->toImmutable();
        $to   = Carbon::parse($data['to'])->endOfDay()->toImmutable();

        try {
            // Aquí ya usamos el servicio real (en local genera DEMO, en prod SAT real)
            $download = $this->service->requestPackages($cred, $from, $to, $tipo);

            Log::info('[SAT:requestList] Solicitud registrada', [
                'trace_id'   => $trace,
                'cuenta_id'  => $cuentaId,
                'rfc'        => $cred->rfc,
                'tipo'       => $tipo,
                'from'       => $from->format('Y-m-d'),
                'to'         => $to->format('Y-m-d'),
                'download_id'=> $download->id,
                'request_id' => $download->request_id,
                'status'     => $download->status,
            ]);

            $msg = sprintf(
                'Se registró la solicitud de descarga para el RFC %s (%s - %s).',
                $cred->rfc,
                $from->format('Y-m-d'),
                $to->format('Y-m-d')
            );

            $payload = [
                'ok'          => true,
                'trace_id'    => $trace,
                'download_id' => $download->id,
                'request_id'  => $download->request_id,
                'status'      => $download->status,
                'msg'         => $msg,
            ];

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($payload);
            }

            return back()->with('ok', $msg);
        } catch (\Throwable $e) {
            Log::error('[SAT:requestList] Error al registrar solicitud', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $data['rfc'],
                'ex'        => $e->getMessage(),
            ]);

            $payload = [
                'ok'       => false,
                'trace_id' => $trace,
                'msg'      => 'Ocurrió un error al registrar la solicitud de descarga.',
            ];

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($payload, 500);
            }

            return back()->with('error', $payload['msg']);
        }
    }


    /* ==================== VERIFICAR ==================== */
    public function verify(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();
        $user     = $this->cu();

        if (! $user) {
            return response()->json([
                'ok'  => false,
                'msg' => 'unauth',
            ], 401);
        }

        // DEMO local: promover uno de pending -> done
        if (app()->environment('local')) {
            $one = SatDownload::where('cuenta_id', $cuentaId)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($one) {
                $one->status     = 'done';
                $one->package_id = $one->package_id ?: ('PKG-' . substr(md5($one->request_id ?? ''), 0, 10));
                $one->save();

                Log::info('[SAT:verify] Demo promote -> done', [
                    'trace_id' => $trace,
                    'id'       => $one->id,
                    'request'  => $one->request_id,
                    'pkg'      => $one->package_id,
                ]);
            }
        }

        $pendingCount = SatDownload::where('cuenta_id', $cuentaId)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        $readyCount = SatDownload::where('cuenta_id', $cuentaId)
            ->whereIn('status', ['ready', 'done', 'listo'])
            ->count();

        Log::info('[SAT:verify] Ping', [
            'trace_id' => $trace,
            'cuenta_id'=> $cuentaId,
            'pending'  => $pendingCount,
            'ready'    => $readyCount,
        ]);

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'pending'  => $pendingCount,
            'ready'    => $readyCount,
        ]);
    }

    /* ==================== DATOS PARA GRÁFICAS ==================== */
    public function charts(Request $request): JsonResponse
    {
        $cuenta   = auth('web')->user()->cuenta ?? null;
        $cuentaId = $cuenta?->id ?? null;

        $scope = strtolower((string) $request->input('scope', 'emitidos'));
        if (! in_array($scope, ['emitidos', 'recibidos', 'ambos'], true)) {
            $scope = 'emitidos';
        }

        $end   = Carbon::now()->endOfMonth();
        $start = Carbon::now()->subMonths(5)->startOfMonth();

        if ($this->cfdiAvailable()) {
            [$labels, $amounts, $counts] = $this->buildCfdiChartSeries($cuentaId, $start, $end, $scope);
        } else {
            [$labels, $amounts, $counts] = $this->buildDownloadChartSeries($cuentaId, $start, $end, $scope);
        }

        return response()->json([
            'ok'     => true,
            'scope'  => $scope,
            'labels' => $labels,
            'series' => [
                'label_amount' => 'Importe total',
                'label_count'  => '# CFDI',
                'amounts'      => $amounts,
                'counts'       => $counts,
            ],
        ]);
    }

    /* ==================== DESCARGA ==================== */

    public function download(Request $request): JsonResponse
    {
        return $this->downloadPackage($request);
    }

    public function downloadPackage(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();
        $user     = $this->cu();

        if (! $user) {
            return response()->json([
                'ok'       => false,
                'trace_id' => $trace,
                'msg'      => 'No autenticado.',
            ], 401);
        }

        $data = $request->validate([
            'download_id' => ['required', 'string'],
        ]);

        $id = (string) $data['download_id'];

        // Siempre sobre la conexión de clientes
        $download = SatDownload::where('cuenta_id', $cuentaId)->findOrFail($id);

        // Buscar credencial del RFC usado en la solicitud
        $cred = SatCredential::where('cuenta_id', $cuentaId)
            ->where('rfc', $download->rfc)
            ->first();

        if (! $cred) {
            Log::warning('[SAT:downloadPackage] Sin credenciales para descarga', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'download_id' => $download->id,
                'rfc'       => $download->rfc,
            ]);

            return response()->json([
                'ok'       => false,
                'trace_id' => $trace,
                'msg'      => 'No hay credenciales SAT para el RFC de esta descarga.',
            ], 422);
        }

        // Si ya hay ZIP listo, no volvemos a generarlo
        if (! empty($download->zip_path) && in_array($download->status, ['done', 'ready', 'listo'], true)) {
            return response()->json([
                'ok'       => true,
                'trace_id' => $trace,
                'id'       => $download->id,
                'status'   => $download->status,
                'zip_url'  => route('cliente.sat.zip', $download->id),
            ]);
        }

        try {
            // Aquí sí usamos el servicio real:
            // - local/dev: ZIP DEMO con XML/PDF/MANIFEST
            // - prod satws: descarga real desde SAT
            $updated = $this->service->downloadPackage($cred, $download);

            Log::info('[SAT:downloadPackage] ZIP generado', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'id'        => $updated->id,
                'status'    => $updated->status,
                'zip_path'  => $updated->zip_path,
            ]);

            return response()->json([
                'ok'       => true,
                'trace_id' => $trace,
                'id'       => $updated->id,
                'status'   => $updated->status,
                'zip_url'  => route('cliente.sat.zip', $updated->id),
            ]);
        } catch (\Throwable $e) {
            Log::error('[SAT:downloadPackage] Error generando ZIP', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'id'        => $download->id,
                'ex'        => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'trace_id' => $trace,
                'msg'      => 'Ocurrió un error al generar el ZIP de la descarga SAT.',
            ], 500);
        }
    }


    public function zip(Request $request, string $id)
    {
        return $this->downloadZip($id);
    }

    public function downloadZip(string $id)
    {
        $traceId = (string) Str::uuid();

        try {
            $row = SatDownload::on('mysql_clientes')->find($id);

            if (! $row) {
                Log::warning('[SAT:downloadZip] Registro no encontrado', [
                    'trace_id' => $traceId,
                    'id'       => $id,
                ]);

                return redirect()
                    ->route('cliente.sat.index')
                    ->with('error', 'No se encontró la descarga SAT indicada.');
            }

            // Si estamos en local/dev y aún no hay ZIP, podemos generarlo al vuelo
            if (empty($row->zip_path) && app()->environment(['local', 'development', 'testing'])) {
                $cred = SatCredential::where('cuenta_id', $row->cuenta_id)
                    ->where('rfc', $row->rfc)
                    ->first();

                if ($cred) {
                    $row = $this->service->downloadPackage($cred, $row);
                }
            }

            if (empty($row->zip_path)) {
                Log::warning('[SAT:downloadZip] ZIP no listo/no existe', [
                    'trace_id' => $traceId,
                    'id'       => $id,
                    'status'   => $row->status,
                    'zip_path' => $row->zip_path,
                ]);

                return redirect()
                    ->route('cliente.sat.index')
                    ->with(
                        'error',
                        'El paquete aún no tiene ZIP generado. Vuelve a presionar "Verificar / Descargar" en unos minutos.'
                    );
            }

            $relative = ltrim($row->zip_path, '/');

            // Usamos el mismo disco que el servicio (sat_zip si existe, si no el default)
            $diskName = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
            $disk     = Storage::disk($diskName);
            $fullPath = $disk->path($relative);

            if (! is_file($fullPath)) {
                Log::warning('[SAT:downloadZip] Archivo ZIP no encontrado en disco', [
                    'trace_id' => $traceId,
                    'id'       => $id,
                    'zip_path' => $row->zip_path,
                    'full'     => $fullPath,
                ]);

                return redirect()
                    ->route('cliente.sat.index')
                    ->with(
                        'error',
                        'El archivo ZIP asociado ya no existe en el servidor. Vuelve a generar la descarga SAT.'
                    );
            }

            $downloadName = basename($fullPath);

            return response()->download($fullPath, $downloadName);
        } catch (\Throwable $e) {
            Log::error('[SAT:downloadZip] Error inesperado', [
                'trace_id' => $traceId,
                'id'       => $id,
                'msg'      => $e->getMessage(),
            ]);

            return redirect()
                ->route('cliente.sat.index')
                ->with(
                    'error',
                    'Ocurrió un error al preparar el ZIP de la descarga SAT.'
                );
        }
    }

    /* ==================== REPORTE (CSV/XLSX/JSON PARA GRÁFICAS) ==================== */

    public function report(Request $request): StreamedResponse|JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();
        $user     = $this->cu();

        $periodo = (string) $request->input('periodo', '');
        $fmt     = strtolower((string) $request->input('fmt', 'csv'));
        $scope   = strtolower((string) $request->input('scope', 'emitidos'));

        if (! in_array($scope, ['emitidos', 'recibidos', 'ambos'], true)) {
            $scope = 'emitidos';
        }

        if ($periodo !== '' && preg_match('~^\d{4}\-\d{2}$~', $periodo)) {
            $from = Carbon::createFromFormat('Y-m', $periodo)->startOfMonth();
            $to   = (clone $from)->endOfMonth();
        } else {
            $from = Carbon::parse(
                $request->input('date_from', now()->subMonths(5)->startOfMonth()->toDateString())
            )->startOfDay();

            $to = Carbon::parse(
                $request->input('date_to', now()->endOfMonth()->toDateString())
            )->endOfDay();
        }

        if ($from->greaterThan($to)) {
            $t    = $from;
            $from = $to;
            $to   = $t;
        }

        if ($fmt === 'json') {
            $useReal = $this->cfdiAvailable();

            if ($useReal) {
                [$labels, $amounts, $counts] = $this->buildCfdiMetrics($cuentaId, $from, $to, $scope);
            } else {
                [$labels, $amounts, $counts] = $this->buildDownloadMetrics($cuentaId, $from, $to, $scope);
            }

            Log::info('[SAT:report-json] Stats', [
                'trace_id' => $trace,
                'user_id'  => $user?->id,
                'scope'    => $scope,
                'from'     => $from->toDateString(),
                'to'       => $to->toDateString(),
                'source'   => $useReal ? 'cfdis' : 'sat_downloads',
            ]);

            return response()->json([
                'ok'       => true,
                'trace_id' => $trace,
                'scope'    => $scope,
                'labels'   => $labels,
                'amounts'  => $amounts,
                'counts'   => $counts,
                'source'   => $useReal ? 'cfdis' : 'sat_downloads',
            ]);
        }

        $useReal = $this->cfdiAvailable();

        $rows = $useReal
            ? $this->buildCfdiRows($cuentaId, $from, $to)
            : $this->buildAggregatedRows(
                $cuentaId,
                $from,
                $to,
                $request->input('rfc'),
                $request->input('tipo_db')
            );

        if ($useReal) {
            array_unshift($rows, [
                'UUID',
                'RFC Emisor',
                'RFC Receptor',
                'Fecha',
                'Subtotal',
                'IVA',
                'Total',
                'Metodo',
                'UsoCFDI',
            ]);
        } else {
            array_unshift($rows, [
                'RFC',
                'Tipo',
                'Status',
                'Desde',
                'Hasta',
                '#Solicitudes',
                '#Paquetes',
            ]);
        }

        Log::info('[SAT:report] Generando', [
            'trace_id' => $trace,
            'user_id'  => $user?->id,
            'fmt'      => $fmt,
            'from'     => $from->toDateString(),
            'to'       => $to->toDateString(),
            'rows'     => count($rows),
            'source'   => $useReal ? 'cfdis' : 'sat_downloads(fallback)',
        ]);

        $base     = $useReal ? 'reporte_cfdi' : 'reporte_descargas';
        $filename = $base . '_' . ($periodo ?: $from->format('Y-m')) . ($fmt === 'xlsx' ? '.xlsx' : '.csv');

        if ($fmt === 'xlsx') {
            return $this->streamXlsx($rows, $filename, $trace);
        }

        $callback = static function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
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
        $fmt = strtolower((string) ($request->input('format') ?? $request->input('fmt') ?? 'csv'));
        $request->merge(['fmt' => $fmt]);

        /** @var StreamedResponse $resp */
        $resp = $this->report($request);
        return $resp;
    }

    /* ===== Helpers de REPORTE reales/fallback ===== */

    private function cfdiAvailable(): bool
    {
        try {
            return Schema::connection('mysql_clientes')->hasTable('cfdis');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getSatConnection(): string
    {
        // Ajusta si usas otro nombre de conexión para clientes
        return 'mysql_clientes';
    }

    private function buildCfdiRows(string $cuentaId, Carbon $from, Carbon $to): array
    {
        $conn = $this->getSatConnection();

        $hasClientes   = Schema::connection($conn)->hasTable('clientes')
            && Schema::connection($conn)->hasColumn('clientes', 'rfc');

        $hasReceptores = Schema::connection($conn)->hasTable('receptores')
            && Schema::connection($conn)->hasColumn('receptores', 'rfc');

        $q = DB::connection($conn)->table('cfdis as c')
            ->whereBetween('c.fecha', [$from->toDateString(), $to->toDateString()]);

        if (Schema::connection($conn)->hasColumn('cfdis', 'cuenta_id')) {
            $q->where('c.cuenta_id', $cuentaId);
        }

        if ($hasClientes) {
            $q->leftJoin('clientes as em', 'em.id', '=', 'c.cliente_id');
        }

        if ($hasReceptores) {
            $q->leftJoin('receptores as re', 're.id', '=', 'c.receptor_id');
        }

        $select = [
            'c.uuid',
            DB::raw('DATE(c.fecha) as fecha'),
            DB::raw('COALESCE(c.subtotal,0) as subtotal'),
            DB::raw('COALESCE(c.total,0) as total'),
        ];

        if (Schema::connection($conn)->hasColumn('cfdis', 'iva')) {
            $select[] = DB::raw('COALESCE(c.iva,0) as iva');
        } else {
            $select[] = DB::raw('0 as iva');
        }

        $select[] = Schema::connection($conn)->hasColumn('cfdis', 'metodo_pago')
            ? DB::raw('COALESCE(c.metodo_pago,"") as metodo_pago')
            : DB::raw('"" as metodo_pago');

        $select[] = Schema::connection($conn)->hasColumn('cfdis', 'uso_cfdi')
            ? DB::raw('COALESCE(c.uso_cfdi,"") as uso_cfdi')
            : DB::raw('"" as uso_cfdi');

        $select[] = $hasClientes
            ? DB::raw('UPPER(COALESCE(em.rfc,"")) as rfc_emisor')
            : DB::raw('"" as rfc_emisor');

        $select[] = $hasReceptores
            ? DB::raw('UPPER(COALESCE(re.rfc,"")) as rfc_receptor')
            : DB::raw('"" as rfc_receptor');

        $rows = [];

        $q->select($select)
            ->orderBy('c.fecha')
            ->chunk(2000, function ($chunk) use (&$rows) {
                foreach ($chunk as $r) {
                    $rows[] = [
                        (string) $r->uuid,
                        (string) ($r->rfc_emisor ?? ''),
                        (string) ($r->rfc_receptor ?? ''),
                        (string) $r->fecha,
                        (string) $r->subtotal,
                        (string) $r->iva,
                        (string) $r->total,
                        (string) ($r->metodo_pago ?? ''),
                        (string) ($r->uso_cfdi ?? ''),
                    ];
                }
            });

        return $rows;
    }

    private function buildCfdiMetrics(string $cuentaId, Carbon $from, Carbon $to, string $scope): array
    {
        $conn = $this->getSatConnection();

        if (! Schema::connection($conn)->hasTable('cfdis')) {
            return [[], [], []];
        }

        $hasTipo = Schema::connection($conn)->hasColumn('cfdis', 'tipo');

        $q = DB::connection($conn)->table('cfdis as c')
            ->whereBetween('c.fecha', [$from->toDateString(), $to->toDateString()]);

        if (Schema::connection($conn)->hasColumn('cfdis', 'cuenta_id')) {
            $q->where('c.cuenta_id', $cuentaId);
        }

        if ($hasTipo && $scope !== 'ambos') {
            $q->where('c.tipo', $scope);
        }

        $rowsDb = $q->select([
                DB::raw("DATE_FORMAT(c.fecha,'%Y-%m') as ym"),
                DB::raw('COUNT(*) as n'),
                DB::raw('SUM(COALESCE(c.total,0)) as total'),
            ])
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $map = [];
        foreach ($rowsDb as $r) {
            $map[$r->ym] = [
                'n' => (int) $r->n,
                't' => (float) $r->total,
            ];
        }

        $labels  = [];
        $amounts = [];
        $counts  = [];

        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= $to) {
            $ym       = $cursor->format('Y-m');
            $labels[] = $cursor->format('M y');

            $row = $map[$ym] ?? ['n' => 0, 't' => 0.0];

            $amounts[] = round($row['t'], 2);
            $counts[]  = (int) $row['n'];

            $cursor->addMonth();
        }

        return [$labels, $amounts, $counts];
    }

    private function buildAggregatedRows(
        string $cuentaId,
        Carbon $from,
        Carbon $to,
        ?string $rfc,
        ?string $tipoDb
    ): array {
        $q = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->whereBetween('date_from', [$from->toDateString(), $to->toDateString()]);

        if ($rfc) {
            $q->where('rfc', strtoupper($rfc));
        }

        if ($tipoDb) {
            $q->where('tipo', $tipoDb);
        }

        return $q->select([
                'rfc',
                'tipo',
                'status',
                DB::raw('MIN(date_from) as df'),
                DB::raw('MAX(date_to) as dt'),
                DB::raw('COUNT(*) as cnt'),
                DB::raw('SUM(CASE WHEN package_id IS NOT NULL AND package_id <> "" THEN 1 ELSE 0 END) as pkgs'),
            ])
            ->groupBy('rfc', 'tipo', 'status')
            ->orderBy('rfc')
            ->orderBy('tipo')
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => [
                (string) $r->rfc,
                (string) $r->tipo,
                (string) $r->status,
                (string) $r->df,
                (string) $r->dt,
                (int) $r->cnt,
                (int) $r->pkgs,
            ])
            ->toArray();
    }

    private function buildDownloadMetrics(string $cuentaId, Carbon $from, Carbon $to, string $scope): array
    {
        $q = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->whereBetween('date_from', [$from->toDateString(), $to->toDateString()]);

        if ($scope !== 'ambos') {
            $q->where('tipo', $scope);
        }

        $rowsDb = $q->select([
                DB::raw("DATE_FORMAT(date_from,'%Y-%m') as ym"),
                DB::raw('COUNT(*) as n'),
                DB::raw('SUM(CASE WHEN package_id IS NOT NULL AND package_id <> "" THEN 1 ELSE 0 END) as pkgs'),
            ])
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $map = [];
        foreach ($rowsDb as $r) {
            $map[$r->ym] = [
                'n'    => (int) $r->n,
                'pkgs' => (int) $r->pkgs,
            ];
        }

        $labels  = [];
        $amounts = [];
        $counts  = [];

        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= $to) {
            $ym       = $cursor->format('Y-m');
            $labels[] = $cursor->format('M y');

            $row = $map[$ym] ?? ['n' => 0, 'pkgs' => 0];

            if ($scope === 'emitidos') {
                $amounts[] = (int) $row['n'];
                $counts[]  = (int) $row['pkgs'];
            } elseif ($scope === 'recibidos') {
                $amounts[] = (int) $row['n'];
                $counts[]  = (int) $row['pkgs'];
            } else {
                $total     = (int) $row['n'];
                $amounts[] = $total;
                $counts[]  = (int) $row['pkgs'];
            }

            $cursor->addMonth();
        }

        return [$labels, $amounts, $counts];
    }

    private function buildCfdiChartSeries(
        ?string $cuentaId,
        Carbon $from,
        Carbon $to,
        string $scope
    ): array {
        $months = [];
        $cursor = $from->copy();

        while ($cursor->lessThanOrEqualTo($to)) {
            $key          = $cursor->format('Y-m');
            $months[$key] = [
                'label'  => $cursor->format('M y'),
                'amount' => 0.0,
                'count'  => 0,
            ];
            $cursor->addMonth();
        }

        $q = DB::connection($this->getSatConnection())->table('cfdis as c')
            ->whereBetween('c.fecha', [$from->toDateString(), $to->toDateString()]);

        if ($cuentaId && Schema::connection($this->getSatConnection())->hasColumn('cfdis', 'cuenta_id')) {
            $q->where('c.cuenta_id', $cuentaId);
        }

        if (Schema::connection($this->getSatConnection())->hasColumn('cfdis', 'tipo')
            && in_array($scope, ['emitidos', 'recibidos'], true)) {
            $q->where('c.tipo', $scope);
        }

        $rows = $q->selectRaw(
            'DATE_FORMAT(c.fecha,"%Y-%m") as ym, ' .
            'SUM(COALESCE(c.total,0)) as amount, ' .
            'COUNT(*) as cnt'
        )->groupBy('ym')->get();

        foreach ($rows as $r) {
            $ym = (string) $r->ym;
            if (! isset($months[$ym])) {
                continue;
            }
            $months[$ym]['amount'] = (float) $r->amount;
            $months[$ym]['count']  = (int) $r->cnt;
        }

        $labels  = [];
        $amounts = [];
        $counts  = [];

        foreach ($months as $m) {
            $labels[]  = $m['label'];
            $amounts[] = $m['amount'];
            $counts[]  = $m['count'];
        }

        return [$labels, $amounts, $counts];
    }

    private function buildDownloadChartSeries(
        ?string $cuentaId,
        Carbon $from,
        Carbon $to,
        string $scope
    ): array {
        $months = [];
        $cursor = $from->copy();

        while ($cursor->lessThanOrEqualTo($to)) {
            $key          = $cursor->format('Y-m');
            $months[$key] = [
                'label'     => $cursor->format('M y'),
                'emitidos'  => 0,
                'recibidos' => 0,
            ];
            $cursor->addMonth();
        }

        $rows = SatDownload::query()
            ->whereBetween('date_from', [$from->toDateString(), $to->toDateString()])
            ->when($cuentaId, fn ($q) => $q->where('cuenta_id', $cuentaId))
            ->selectRaw('DATE_FORMAT(date_from,"%Y-%m") as ym, tipo, COUNT(*) as cnt')
            ->groupBy('ym', 'tipo')
            ->get();

        foreach ($rows as $r) {
            $ym = (string) $r->ym;
            if (! isset($months[$ym])) {
                continue;
            }

            $tipo = strtolower((string) $r->tipo);
            if ($tipo === 'emitidos') {
                $months[$ym]['emitidos'] += (int) $r->cnt;
            }
            if ($tipo === 'recibidos') {
                $months[$ym]['recibidos'] += (int) $r->cnt;
            }
        }

        $labels  = [];
        $amounts = [];
        $counts  = [];

        foreach ($months as $m) {
            $labels[] = $m['label'];

            if ($scope === 'emitidos') {
                $amounts[] = $m['emitidos'];
                $counts[]  = $m['emitidos'];
            } elseif ($scope === 'recibidos') {
                $amounts[] = $m['recibidos'];
                $counts[]  = $m['recibidos'];
            } else {
                $total     = $m['emitidos'] + $m['recibidos'];
                $amounts[] = $total;
                $counts[]  = $total;
            }
        }

        return [$labels, $amounts, $counts];
    }

    /* ==================== XLSX mínimo ==================== */

    private function streamXlsx(array $rows, string $filename, string $trace): StreamedResponse
    {
        $headers = [
            'X-Debug-Trace-Id'    => $trace,
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = static function () use ($rows) {
            $zip     = new \ZipArchive();
            $tmp     = tmpfile();
            $tmpPath = stream_get_meta_data($tmp)['uri'];

            $zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

            $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
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
                    $cells[]   = '<c r="' . $cellRef . '" t="inlineStr"><is><t>' .
                        self::xml($val) .
                        '</t></is></c>';
                }
                $rowsXml[] = '<row r="' . ($rIndex + 1) . '">' . implode('', $cells) . '</row>';
            }

            $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetData>' . implode('', $rowsXml) . '</sheetData>
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
        while ($n > 0) {
            $n--;
            $s = chr($n % 26 + 65) . $s;
            $n = intdiv($n, 26);
        }
        return $s;
    }

    private static function xml(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
