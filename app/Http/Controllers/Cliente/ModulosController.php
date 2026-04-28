<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\Billing\FacturotopiaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ModulosController extends Controller
{
    private function baseData(): array
    {
        $user = Auth::guard('web')->user();

        return [
            'clienteUser' => $user,
            'cuenta'      => $user->cuenta ?? null,
        ];
    }

    private function hasTable(string $connection, string $table): bool
    {
        try {
            return Schema::connection($connection)->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasColumn(string $connection, string $table, string $column): bool
    {
        try {
            return Schema::connection($connection)->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_object($meta)) {
            return (array) $meta;
        }

        if (! is_string($meta) || trim($meta) === '') {
            return [];
        }

        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveAdminAccountId(?object $cuenta): ?int
    {
        if (! $cuenta || empty($cuenta->id)) {
            return null;
        }

        if (! empty($cuenta->admin_account_id)) {
            return (int) $cuenta->admin_account_id;
        }

        if (! $this->hasTable('mysql_clientes', 'cuentas_cliente')) {
            return null;
        }

        try {
            $row = DB::connection('mysql_clientes')
                ->table('cuentas_cliente')
                ->where('id', (string) $cuenta->id)
                ->first(['id', 'admin_account_id', 'rfc', 'rfc_padre']);

            if ($row && ! empty($row->admin_account_id)) {
                return (int) $row->admin_account_id;
            }

            $rfc = strtoupper(trim((string) ($cuenta->rfc ?? $row->rfc ?? $row->rfc_padre ?? '')));

            if ($rfc !== '' && $this->hasTable('mysql_admin', 'accounts')) {
                $rfcColumn = $this->hasColumn('mysql_admin', 'accounts', 'rfc') ? 'rfc' : null;

                if ($rfcColumn) {
                    $adminId = DB::connection('mysql_admin')
                        ->table('accounts')
                        ->whereRaw('UPPER(' . $rfcColumn . ') = ?', [$rfc])
                        ->value('id');

                    return $adminId ? (int) $adminId : null;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    private function resolveAdminAccount(?object $cuenta): ?object
    {
        $adminAccountId = $this->resolveAdminAccountId($cuenta);

        if (! $adminAccountId || ! $this->hasTable('mysql_admin', 'accounts')) {
            return null;
        }

        try {
            $columns = ['id'];

            foreach (['rfc', 'razon_social', 'name', 'plan', 'billing_status', 'meta', 'updated_at'] as $column) {
                if ($this->hasColumn('mysql_admin', 'accounts', $column)) {
                    $columns[] = $column;
                }
            }

            return DB::connection('mysql_admin')
                ->table('accounts')
                ->where('id', $adminAccountId)
                ->first($columns);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function cfdiTableInfo(): array
    {
        foreach (['mysql_clientes', 'mysql'] as $connection) {
            if ($this->hasTable($connection, 'cfdis')) {
                return [$connection, 'cfdis'];
            }
        }

        return ['mysql_clientes', 'cfdis'];
    }

    private function scopedCfdiQuery(?object $cuenta)
    {
        [$connection, $table] = $this->cfdiTableInfo();

        $query = DB::connection($connection)->table($table);

        if (! $cuenta || empty($cuenta->id) || ! $this->hasTable($connection, $table)) {
            return $query->whereRaw('1 = 0');
        }

        $cuentaId = (string) $cuenta->id;

        $query->where(function ($q) use ($connection, $table, $cuentaId, $cuenta) {
            if ($this->hasColumn($connection, $table, 'cuenta_id')) {
                $q->orWhere('cuenta_id', $cuentaId);
            }

            if ($this->hasColumn($connection, $table, 'account_id')) {
                $q->orWhere('account_id', $cuentaId);
            }

            if (is_numeric($cuenta->id) && $this->hasColumn($connection, $table, 'cliente_id')) {
                $q->orWhere('cliente_id', (int) $cuenta->id);
            }
        });

        return $query;
    }

    private function buildTimbresData(?object $cuenta): array
    {
        $adminAccount = $this->resolveAdminAccount($cuenta);
        $meta = $this->decodeMeta($adminAccount->meta ?? null);

        $facturotopia = (array) data_get($meta, 'facturotopia', []);

        $facturotopiaPassword = '';

        try {
            $encryptedPassword = (string) data_get($facturotopia, 'auth.password_encrypted', '');

            if ($encryptedPassword !== '') {
                $facturotopiaPassword = Crypt::decryptString($encryptedPassword);
            }
        } catch (\Throwable $e) {
            $facturotopiaPassword = '';
        }

        $asignados = (int) data_get($facturotopia, 'timbres.asignados', 0);
        $consumidos = (int) data_get($facturotopia, 'timbres.consumidos', 0);
        $disponibles = max(0, $asignados - $consumidos);
        $usoPct = $asignados > 0 ? round(($consumidos / max(1, $asignados)) * 100, 2) : 0.0;

        $hitsAsignados = (int) data_get($facturotopia, 'hits.asignados', 0);
        $hitsConsumidos = (int) data_get($facturotopia, 'hits.consumidos', 0);
        $hitsDisponibles = max(0, $hitsAsignados - $hitsConsumidos);

        $env = strtolower((string) data_get($facturotopia, 'env', 'sandbox'));
        $env = in_array($env, ['sandbox', 'production'], true) ? $env : 'sandbox';

        [$cfdiConn, $cfdiTable] = $this->cfdiTableInfo();

        $now = now();
        $from = $now->copy()->startOfMonth();
        $to = $now->copy()->endOfMonth();

        $totalPeriodo = 0.0;
        $emitidosCount = 0;
        $canceladosCount = 0;
        $erroresCount = 0;
        $promedioPacMs = 0;
        $seriesLabels = [];
        $seriesConsumo = [];
        $seriesMonto = [];
        $consumoPorRfc = collect();
        $ultimosCfdi = collect();

        try {
            $base = $this->scopedCfdiQuery($cuenta);

            $dateColumn = $this->hasColumn($cfdiConn, $cfdiTable, 'fecha_timbrado')
                ? 'fecha_timbrado'
                : 'fecha';

            $statusColumn = $this->hasColumn($cfdiConn, $cfdiTable, 'estatus')
                ? 'estatus'
                : null;

            $periodQuery = (clone $base)->whereBetween($dateColumn, [
                $from->toDateTimeString(),
                $to->toDateTimeString(),
            ]);

            if ($this->hasColumn($cfdiConn, $cfdiTable, 'total')) {
                $totalPeriodo = (float) ((clone $periodQuery)->sum('total') ?? 0);
            }

            if ($statusColumn) {
                $emitidosCount = (int) ((clone $periodQuery)
                    ->whereIn($statusColumn, ['timbrado', 'emitido'])
                    ->count() ?? 0);

                $canceladosCount = (int) ((clone $periodQuery)
                    ->where($statusColumn, 'cancelado')
                    ->count() ?? 0);

                $erroresCount = (int) ((clone $periodQuery)
                    ->whereIn($statusColumn, ['error', 'rechazado', 'fallido'])
                    ->count() ?? 0);
            }

            if ($this->hasColumn($cfdiConn, $cfdiTable, 'pac_response_ms')) {
                $promedioPacMs = (int) ((clone $periodQuery)->avg('pac_response_ms') ?? 0);
            }

            $daily = (clone $periodQuery)
                ->selectRaw('DATE(' . $dateColumn . ') as d')
                ->selectRaw('COUNT(*) as total_cfdi')
                ->selectRaw($this->hasColumn($cfdiConn, $cfdiTable, 'total') ? 'SUM(total) as monto' : '0 as monto')
                ->groupBy('d')
                ->orderBy('d')
                ->get();

            foreach ($daily as $row) {
                $seriesLabels[] = (string) $row->d;
                $seriesConsumo[] = (int) $row->total_cfdi;
                $seriesMonto[] = round((float) $row->monto, 2);
            }

            $rfcColumn = $this->hasColumn($cfdiConn, $cfdiTable, 'receptor_rfc') ? 'receptor_rfc' : null;

            if (! $rfcColumn && $this->hasColumn($cfdiConn, $cfdiTable, 'receptor_id') && $this->hasTable($cfdiConn, 'receptores')) {
                $consumoPorRfc = (clone $periodQuery)
                    ->leftJoin('receptores', 'receptores.id', '=', $cfdiTable . '.receptor_id')
                    ->selectRaw('COALESCE(receptores.rfc, "SIN RFC") as rfc')
                    ->selectRaw('COALESCE(receptores.razon_social, receptores.nombre_comercial, "Receptor") as receptor')
                    ->selectRaw('COUNT(*) as cantidad')
                    ->selectRaw($this->hasColumn($cfdiConn, $cfdiTable, 'total') ? 'SUM(' . $cfdiTable . '.total) as monto' : '0 as monto')
                    ->groupBy('rfc', 'receptor')
                    ->orderByDesc('cantidad')
                    ->limit(8)
                    ->get();
            } elseif ($rfcColumn) {
                $consumoPorRfc = (clone $periodQuery)
                    ->selectRaw('COALESCE(' . $rfcColumn . ', "SIN RFC") as rfc')
                    ->selectRaw('COALESCE(' . $rfcColumn . ', "Receptor") as receptor')
                    ->selectRaw('COUNT(*) as cantidad')
                    ->selectRaw($this->hasColumn($cfdiConn, $cfdiTable, 'total') ? 'SUM(total) as monto' : '0 as monto')
                    ->groupBy($rfcColumn)
                    ->orderByDesc('cantidad')
                    ->limit(8)
                    ->get();
            }

            $select = ['id'];

            foreach ([
                'uuid',
                'serie',
                'folio',
                'fecha',
                'fecha_timbrado',
                'estatus',
                'total',
                'tipo_documento',
                'tipo_comprobante',
                'receptor_id',
                'receptor_rfc',
                'pac_response_ms',
                'pac_error',
            ] as $column) {
                if ($this->hasColumn($cfdiConn, $cfdiTable, $column)) {
                    $select[] = $column;
                }
            }

            $ultimosCfdi = (clone $base)
                ->select($select)
                ->orderByDesc($dateColumn)
                ->limit(12)
                ->get();

            if ($this->hasTable($cfdiConn, 'receptores') && $ultimosCfdi->isNotEmpty()) {
                $receptorIds = $ultimosCfdi
                    ->pluck('receptor_id')
                    ->filter()
                    ->unique()
                    ->values();

                $receptores = DB::connection($cfdiConn)
                    ->table('receptores')
                    ->whereIn('id', $receptorIds)
                    ->get(['id', 'rfc', 'razon_social', 'nombre_comercial'])
                    ->keyBy('id');

                $ultimosCfdi = $ultimosCfdi->map(function ($row) use ($receptores) {
                    $receptor = $receptores->get($row->receptor_id ?? null);

                    $row->receptor_rfc_resuelto = $row->receptor_rfc ?? $receptor->rfc ?? null;
                    $row->receptor_nombre_resuelto = $receptor->razon_social ?? $receptor->nombre_comercial ?? 'Receptor';

                    return $row;
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $alertas = [];

        if ($asignados <= 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'titulo' => 'Sin bolsa configurada',
                'texto' => 'Admin aún no ha asignado timbres de producción a esta cuenta.',
            ];
        } elseif ($disponibles <= 5) {
            $alertas[] = [
                'tipo' => 'danger',
                'titulo' => 'Saldo crítico',
                'texto' => 'Quedan pocos timbres disponibles. Recomienda comprar o asignar un nuevo paquete.',
            ];
        } elseif ($usoPct >= 80) {
            $alertas[] = [
                'tipo' => 'warning',
                'titulo' => 'Consumo alto',
                'texto' => 'La cuenta ya consumió más del 80% de su bolsa asignada.',
            ];
        }

        if ($erroresCount > 0) {
            $alertas[] = [
                'tipo' => 'danger',
                'titulo' => 'Errores detectados',
                'texto' => 'Hay eventos con error/rechazo en el periodo. Conviene revisar RFC, régimen, CP o respuesta PAC.',
            ];
        }

        if ($emitidosCount > 0 && $disponibles > 0) {
            $diasTranscurridos = max(1, now()->day);
            $promedioDiario = $emitidosCount / $diasTranscurridos;
            $diasRestantes = $promedioDiario > 0 ? floor($disponibles / $promedioDiario) : null;

            if ($diasRestantes !== null && $diasRestantes <= 7) {
                $alertas[] = [
                    'tipo' => 'warning',
                    'titulo' => 'Compra sugerida',
                    'texto' => 'Al ritmo actual, la bolsa podría agotarse en aproximadamente ' . $diasRestantes . ' días.',
                ];
            }
        }

        return [
            'adminAccount' => $adminAccount,
            'facturotopia' => [
                'status' => (string) data_get($facturotopia, 'status', 'pendiente'),
                'env' => $env,
                'customer_id' => (string) data_get($facturotopia, 'customer_id', ''),
                'user' => (string) data_get($facturotopia, 'auth.user', ''),
                'password' => $facturotopiaPassword,
                'sandbox_base_url' => (string) data_get($facturotopia, 'sandbox.base_url', ''),
                'sandbox_api_key' => (string) data_get($facturotopia, 'sandbox.api_key', ''),
                'production_base_url' => (string) data_get($facturotopia, 'production.base_url', ''),
                'production_api_key' => (string) data_get($facturotopia, 'production.api_key', ''),
                'updated_at' => (string) data_get($facturotopia, 'updated_at', ''),
            ],
            'saldo' => [
                'timbres_asignados' => $asignados,
                'timbres_consumidos' => $consumidos,
                'timbres_disponibles' => $disponibles,
                'uso_pct' => $usoPct,
                'hits_asignados' => $hitsAsignados,
                'hits_consumidos' => $hitsConsumidos,
                'hits_disponibles' => $hitsDisponibles,
                'ultimo_consumo_at' => (string) data_get($facturotopia, 'timbres.ultimo_consumo_at', ''),
                'ultimo_uuid' => (string) data_get($facturotopia, 'timbres.ultimo_uuid', ''),
                'ultimo_cfdi_id' => data_get($facturotopia, 'timbres.ultimo_cfdi_id'),
            ],
            'periodo' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'label' => $from->format('d/m/Y') . ' — ' . $to->format('d/m/Y'),
            ],
            'kpis' => [
                'total_periodo' => round($totalPeriodo, 2),
                'emitidos_count' => $emitidosCount,
                'cancelados_count' => $canceladosCount,
                'errores_count' => $erroresCount,
                'promedio_pac_ms' => $promedioPacMs,
            ],
            'series' => [
                'labels' => $seriesLabels,
                'consumo' => $seriesConsumo,
                'monto' => $seriesMonto,
            ],
            'consumoPorRfc' => $consumoPorRfc,
            'ultimosCfdi' => $ultimosCfdi,
            'alertasIa' => $alertas,
        ];
    }

    public function crm(): View
    {
        return view('cliente.modulos.crm', $this->baseData());
    }

    public function inventario(): View
    {
        return view('cliente.modulos.inventario', $this->baseData());
    }

    public function ventas(): View
    {
        return view('cliente.modulos.ventas', $this->baseData());
    }

    public function reportes(): View
    {
        return view('cliente.modulos.reportes', $this->baseData());
    }

    public function rh(): View
    {
        return view('cliente.modulos.rh', $this->baseData());
    }

    public function timbres(): View
    {
        $base = $this->baseData();

        return view('cliente.modulos.timbres', $base + [
            'timbresData' => $this->buildTimbresData($base['cuenta'] ?? null),
        ]);
    }

    public function facturotopiaTest(Request $request, FacturotopiaService $facturotopia): JsonResponse
{
    $base = $this->baseData();
    $cuenta = $base['cuenta'] ?? null;

    $adminAccountId = $this->resolveAdminAccountId($cuenta);

    if (! $adminAccountId) {
        return response()->json([
            'ok' => false,
            'message' => 'No se pudo resolver la cuenta admin del cliente.',
        ], 422);
    }

    $env = strtolower((string) $request->input('env', ''));
    $env = in_array($env, ['sandbox', 'production'], true) ? $env : null;

    try {
        $startedAt = microtime(true);

        $result = $facturotopia->testConnection($adminAccountId, $env);

        $ms = (int) round((microtime(true) - $startedAt) * 1000);

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => ! empty($result['ok'])
                ? 'Conexión Facturotopia configurada correctamente.'
                : 'Faltan datos de conexión Facturotopia.',
            'env' => $result['env'] ?? $env ?? 'sandbox',
            'base_url' => $result['base_url'] ?? '',
            'has_api_key' => (bool) ($result['has_api_key'] ?? false),
            'has_user' => (bool) ($result['has_user'] ?? false),
            'has_password' => (bool) ($result['has_password'] ?? false),
            'customer_id' => $result['customer_id'] ?? '',
            'response_ms' => $ms,
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'ok' => false,
            'message' => 'Error al probar conexión: ' . $e->getMessage(),
        ], 500);
    }
}
}