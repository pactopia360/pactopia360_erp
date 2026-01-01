<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\SatDownloadService;
use App\Services\Sat\SatDownloadZipHelper;
use App\Services\Sat\VaultAccountSummaryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SatDescargaController extends Controller
{
    public function __construct(
        private readonly SatDownloadService         $service,
        private readonly SatDownloadZipHelper       $zipHelper,
        private readonly VaultAccountSummaryService $vaultSummaryService,
    ) {}

    private function cu(): ?object
    {
        return auth('web')->user();
    }

    private function resolveCuentaIdFromUser($user): ?string
    {
        if (!$user) return null;

        if (!empty($user->cuenta_id)) return (string)$user->cuenta_id;

        if (isset($user->cuenta)) {
            $c = $user->cuenta;
            if (is_object($c)) return (string)($c->id ?? $c->cuenta_id ?? '');
            if (is_array($c))  return (string)($c['id'] ?? $c['cuenta_id'] ?? '');
        }

        return null;
    }

    private function cuId(): string
    {
        $u = $this->cu();
        return (string)($this->resolveCuentaIdFromUser($u) ?? '');
    }

    private function trace(): string
    {
        return (string) Str::ulid();
    }

    /* ===================================================
     *  HELPERS COSTO / PESO / EXPIRACIÓN
     * =================================================== */

    private function computeDownloadCost(int $numXml): float
    {
        $n = max(0, $numXml);

        if ($n <= 0)        return 0.0;
        if ($n <= 5000)     return (float)($n * 1);
        if ($n <= 25000)    return (float)($n * 0.08);
        if ($n <= 40000)    return (float)($n * 0.05);
        if ($n <= 100000)   return (float)($n * 0.03);
        if ($n <= 500000)   return 12500.0;
        if ($n <= 1000000)  return 18500.0;
        if ($n <= 2000000)  return 25000.0;
        if ($n <= 3000000)  return 31000.0;

        return (float)($n * 0.01);
    }

    protected function computeDownloadCostPhp(int $n): float
    {
        return $this->computeDownloadCost($n);
    }

    private function computeCostFromSizeOrXml(?int $sizeBytes, int $xmlCount): float
    {
        $xmlCount = max(0, (int)$xmlCount);

        if ($xmlCount > 0) {
            return $this->computeDownloadCost($xmlCount);
        }

        $b = (int)($sizeBytes ?? 0);
        if ($b <= 0) return 0.0;

        $estXml = (int) max(1, (int)ceil($b / 4096)); // 4KB promedio
        return $this->computeDownloadCost($estXml);
    }

    private function hydrateDownloadMetrics(SatDownload $d): SatDownload
{
    $conn  = $d->getConnectionName() ?? 'mysql_clientes';
    $table = $d->getTable();
    $schema = Schema::connection($conn);

    $has = static function(string $col) use ($schema, $table): bool {
        try { return $schema->hasColumn($table, $col); } catch (\Throwable) { return false; }
    };

    $meta = [];

    // meta puede venir como array o JSON
    try {
        if (isset($d->meta)) {
            if (is_array($d->meta)) $meta = $d->meta;
            elseif (is_string($d->meta) && $d->meta !== '') {
                $tmp = json_decode($d->meta, true);
                if (is_array($tmp)) $meta = $tmp;
            }
        }
    } catch (\Throwable) {
        $meta = [];
    }

    $sizeBytes = null;
    $sizeMb    = null;
    $sizeGb    = null;

    // 1) Candidates bytes
    $bytesCandidates = [
        $meta['zip_bytes']      ?? null,
        $meta['size_bytes']     ?? null,
        $meta['bytes']          ?? null,
        $meta['zip_size_bytes'] ?? null,

        $d->size_bytes ?? null,
        $d->bytes      ?? null,
        $d->zip_bytes  ?? null,
        $d->peso_bytes ?? null,
    ];

    foreach ($bytesCandidates as $val) {
        if (!is_null($val) && (int)$val > 0) {
            $sizeBytes = (int)$val;
            break;
        }
    }

    // 2) Candidates MB/GB
    $mbCandidates = [
        $meta['size_mb'] ?? null,
        $meta['zip_mb']  ?? null,
        $meta['peso_mb'] ?? null,

        $d->size_mb ?? null,
        $d->peso_mb ?? null,
        $d->tam_mb  ?? null,
    ];

    foreach ($mbCandidates as $val) {
        if (!is_null($val) && (float)$val > 0) {
            $sizeMb = (float)$val;
            break;
        }
    }

    $gbCandidates = [
        $meta['size_gb'] ?? null,
        $meta['zip_gb']  ?? null,
        $meta['peso_gb'] ?? null,

        $d->size_gb ?? null,
        $d->peso_gb ?? null,
        $d->tam_gb  ?? null,
    ];

    foreach ($gbCandidates as $val) {
        if (!is_null($val) && (float)$val > 0) {
            $sizeGb = (float)$val;
            break;
        }
    }

    if (!$sizeBytes && $sizeMb) $sizeBytes = (int) round($sizeMb * 1024 * 1024);
    if (!$sizeBytes && $sizeGb) $sizeBytes = (int) round($sizeGb * 1024 * 1024 * 1024);

    if ($sizeBytes && !$sizeMb) $sizeMb = $sizeBytes / (1024 * 1024);
    if ($sizeBytes && !$sizeGb) $sizeGb = $sizeBytes / (1024 * 1024 * 1024);
    if (!$sizeMb && $sizeGb)    $sizeMb = $sizeGb * 1024;

    // 3) Si aún no hay tamaño, intenta resolver ZIP real (disk+path) y leer size()
    if ((!$sizeBytes || $sizeBytes <= 0) && !empty($d->zip_path)) {
        try {
            $diskName = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
            $disk     = Storage::disk($diskName);
            $relative = ltrim((string)$d->zip_path, '/');

            if ($relative !== '' && $disk->exists($relative)) {
                $sizeBytes = (int) $disk->size($relative);
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    // 4) Si aún no existe zip_path válido, intenta resolver ubicación completa (disk+path)
    if ((!$sizeBytes || $sizeBytes <= 0)) {
        try {
            [$realDisk, $realPath] = $this->resolveDownloadZipLocation($d);
            if ($realDisk !== '' && $realPath !== '') {
                $realPath = ltrim((string)$realPath, '/');
                if ($realPath !== '' && Storage::disk($realDisk)->exists($realPath)) {
                    $sizeBytes = (int) Storage::disk($realDisk)->size($realPath);

                    // persistimos zip_path si existe columna
                    if ($has('zip_path')) {
                        $d->zip_path = $realPath;
                    }

                    // persistimos zip_disk en meta (aunque meta sea string en DB)
                    $meta['zip_disk'] = (string)$realDisk;
                    $d->meta = $meta;
                }
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    // Recalcula MB/GB final si bytes ya existe
    if ($sizeBytes && $sizeBytes > 0) {
        $sizeMb = $sizeMb ?: ($sizeBytes / (1024 * 1024));
        $sizeGb = $sizeGb ?: ($sizeBytes / (1024 * 1024 * 1024));
    }

    // Asignar atributos (solo persistibles si existen columnas)
    if ($sizeBytes && $sizeBytes > 0) {
        if ($has('size_bytes')) $d->size_bytes = $sizeBytes;
        elseif ($has('bytes'))  $d->bytes = $sizeBytes;

        // aunque no exista columna, lo dejamos en memoria para la vista
        $d->setAttribute('size_bytes', $sizeBytes);
    }

    if ($sizeMb && $sizeMb > 0) {
        if ($has('size_mb')) $d->size_mb = $sizeMb;
        $d->setAttribute('size_mb', $sizeMb);
    }

    if ($sizeGb && $sizeGb > 0) {
        if ($has('size_gb')) $d->size_gb = $sizeGb;
        $d->setAttribute('size_gb', $sizeGb);
    }

    // XML count
    $xmlCount = $meta['xml_count']
        ?? $meta['total_xml']
        ?? $meta['num_xml']
        ?? $d->xml_count
        ?? $d->total_xml
        ?? $d->num_xml
        ?? null;

    if ($xmlCount === null && $sizeBytes && $sizeBytes > 0) {
        $xmlCount = (int) max(1, ceil($sizeBytes / 4096));
        if ($has('xml_count')) $d->xml_count = $xmlCount;
        if ($has('total_xml') && $d->total_xml === null) $d->total_xml = $xmlCount;
        $d->setAttribute('xml_count', $xmlCount);
        $d->setAttribute('total_xml', $xmlCount);
    }

    // Costo
    $currentCost = $meta['price_mxn']
        ?? $meta['costo']
        ?? $meta['cost_mxn']
        ?? $meta['precio']
        ?? $d->costo
        ?? $d->cost_mxn
        ?? $d->precio
        ?? 0.0;

    $finalCost = (float)$currentCost;

    if ($finalCost <= 0.0) {
        $finalCost = $this->computeCostFromSizeOrXml(
            $sizeBytes ? (int)$sizeBytes : null,
            (int)($xmlCount ?? 0)
        );
    }

    if ($has('costo')) $d->costo = $finalCost;
    $d->setAttribute('costo', $finalCost);

    // Expiración
    if (!$d->expires_at && !empty($d->disponible_hasta)) {
        try { $d->expires_at = Carbon::parse((string)$d->disponible_hasta); } catch (\Throwable) {}
    } elseif (!$d->expires_at && $d->created_at) {
        $d->expires_at = $d->created_at instanceof Carbon
            ? $d->created_at->copy()->addHours(12)
            : Carbon::parse((string)$d->created_at)->addHours(12);
    }

    return $d;
}

    /* ===================================================
     *  BÓVEDA: RESUMEN / ACTIVA
     * =================================================== */

    private function buildVaultStorageSummary(string $cuentaId, $cuentaObj): array
    {
        try {
            if (method_exists($this->vaultSummaryService, 'buildStorageSummary')) {
                $res = $this->vaultSummaryService->buildStorageSummary($cuentaId, $cuentaObj);
                if (is_array($res) && isset($res['quota_bytes'])) return $res;
            }
        } catch (\Throwable) {
            // fallback abajo
        }

        $conn     = 'mysql_clientes';
        $cuentaId = (string)$cuentaId;

        $planRaw   = (string)($cuentaObj->plan_actual ?? 'FREE');
        $plan      = strtoupper($planRaw);
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        $vaultBaseGb = $isProPlan ? (float) config('services.sat.vault.base_gb_pro', 0.0) : 0.0;

        $quotaBytesFromAccount = 0;
        $schema = Schema::connection($conn);

        if ($schema->hasTable('cuentas_cliente')) {
            if ($schema->hasColumn('cuentas_cliente', 'vault_quota_bytes')) {
                $quotaBytesFromAccount = (int)($cuentaObj->vault_quota_bytes ?? 0);
            } elseif ($schema->hasColumn('cuentas_cliente', 'vault_quota_gb')) {
                $quotaGb = (float)($cuentaObj->vault_quota_gb ?? 0);
                $quotaBytesFromAccount = (int) round($quotaGb * 1024 * 1024 * 1024);
            }
        }

        $quotaGbFromAccount = $quotaBytesFromAccount > 0 ? ($quotaBytesFromAccount / 1024 / 1024 / 1024) : 0.0;

        $quotaGbFromVaultRows = 0.0;
        if ($schema->hasTable('sat_downloads')) {
            try {
                $vaultRowsPaid = SatDownload::query()
                    ->where('cuenta_id', $cuentaId)
                    ->whereIn('tipo', ['VAULT', 'BOVEDA'])
                    ->where(function ($q) {
                        $q->whereNotNull('paid_at')->orWhereIn('status', ['PAID', 'paid', 'PAGADO', 'pagado']);
                    })
                    ->get();

                $totalGb = 0.0;
                foreach ($vaultRowsPaid as $row) {
                    $gb = (float)($row->vault_gb ?? $row->gb ?? 0);
                    if ($gb <= 0) {
                        $alias = (string)($row->alias ?? $row->nombre ?? '');
                        if ($alias !== '' && preg_match('/(\d+)\s*gb/i', $alias, $m)) {
                            $gb = (float)$m[1];
                        }
                    }
                    if ($gb > 0) $totalGb += $gb;
                }
                $quotaGbFromVaultRows = max(0.0, $totalGb);
            } catch (\Throwable) {
                // ignore
            }
        }

        $quotaGbComputed = max(0.0, $vaultBaseGb + $quotaGbFromVaultRows);
        $quotaGbFinal    = max($quotaGbComputed, $quotaGbFromAccount);
        $quotaBytesFinal = (int) round($quotaGbFinal * 1024 * 1024 * 1024);

        $usedBytes = 0;
        if ($schema->hasTable('cuentas_cliente') && $schema->hasColumn('cuentas_cliente', 'vault_used_bytes')) {
            $usedBytes = (int)($cuentaObj->vault_used_bytes ?? 0);
        }

        if ($usedBytes <= 0 && $schema->hasTable('sat_vault_files') && $schema->hasColumn('sat_vault_files', 'bytes')) {
            try {
                $usedBytes = (int) DB::connection($conn)->table('sat_vault_files')->where('cuenta_id', $cuentaId)->sum('bytes');
            } catch (\Throwable) {
                // ignore
            }
        }

        $usedBytes  = max(0, $usedBytes);
        $quotaBytes = max($quotaBytesFinal, $usedBytes);

        $usedGb  = $usedBytes / 1024 / 1024 / 1024;
        $quotaGb = $quotaBytes / 1024 / 1024 / 1024;
        $freeGb  = max(0.0, $quotaGb - $usedGb);

        $usedPct = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 2) : 0.0;
        $freePct = max(0.0, 100.0 - $usedPct);

        return [
            'quota_gb'    => round($quotaGb, 2),
            'quota_bytes' => $quotaBytes,
            'used_gb'     => round($usedGb, 2),
            'used_bytes'  => $usedBytes,
            'free_gb'     => round($freeGb, 2),
            'used_pct'    => $usedPct,
            'free_pct'    => $freePct,
        ];
    }

    private function hasActiveVault(string $cuentaId, $cuentaObj = null): bool
    {
        if ($cuentaId === '') return false;

        try {
            $summary = $this->buildVaultStorageSummary($cuentaId, $cuentaObj ?? (object)[]);
            return ((int)($summary['quota_bytes'] ?? 0)) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function vaultIsActiveForAccount($cuenta): bool
    {
        if (!$cuenta) return false;
        if (is_array($cuenta)) $cuenta = (object)$cuenta;

        $cuentaId = (string)($cuenta->id ?? $cuenta->cuenta_id ?? '');
        return $this->hasActiveVault($cuentaId, $cuenta);
    }

    private function fetchCuentaObjForVault(string $cuentaId): object
    {
        // Preferimos datos reales de cuentas_cliente para plan_actual y quotas.
        try {
            if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                $row = DB::connection('mysql_clientes')
                    ->table('cuentas_cliente')
                    ->where('id', $cuentaId)
                    ->first();
                if ($row) return (object)$row;
            }
        } catch (\Throwable) {
            // ignore
        }

        // Fallback: lo que venga del auth->cuenta.
        $u = $this->cu();
        $c = $u?->cuenta ?? null;
        if (is_array($c)) $c = (object)$c;
        if (is_object($c)) return $c;

        return (object)['id' => $cuentaId];
    }

    /* ==================== VISTA ==================== */

    public function index(Request $request)
    {
        $user          = auth('web')->user();
        $cuentaCliente = $user?->cuenta ?? null;

        if (is_array($cuentaCliente)) $cuentaCliente = (object)$cuentaCliente;

        $cuentaId = (string)($cuentaCliente->id ?? $cuentaCliente->cuenta_id ?? '');
        $planRaw  = (string)($cuentaCliente->plan_actual ?? 'FREE');

        $plan      = strtoupper($planRaw);
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        $vaultSummary = [
            'has_quota'     => false,
            'quota_gb'      => 0.0,
            'quota_bytes'   => 0,
            'used_gb'       => 0.0,
            'used_bytes'    => 0,
            'available_gb'  => 0.0,
            'used_pct'      => 0.0,
            'available_pct' => 0.0,
            'files_count'   => 0,
        ];

        $vaultForJs = [
            'quota_gb'     => 0.0,
            'used_gb'      => 0.0,
            'used'         => 0.0,
            'free_gb'      => 0.0,
            'available_gb' => 0.0,
            'used_pct'     => 0.0,
            'files_count'  => 0,
            'enabled'      => false,
        ];

        if ($cuentaId !== '') {
            try {
                $storage = $this->buildVaultStorageSummary($cuentaId, $cuentaCliente);
                $enabled = ((int)($storage['quota_bytes'] ?? 0)) > 0;

                $vaultSummary = [
                    'has_quota'     => $enabled,
                    'quota_gb'      => (float)($storage['quota_gb'] ?? 0),
                    'quota_bytes'   => (int)($storage['quota_bytes'] ?? 0),
                    'used_gb'       => (float)($storage['used_gb'] ?? 0),
                    'used_bytes'    => (int)($storage['used_bytes'] ?? 0),
                    'available_gb'  => (float)($storage['free_gb'] ?? 0),
                    'used_pct'      => (float)($storage['used_pct'] ?? 0),
                    'available_pct' => (float)($storage['free_pct'] ?? 0),
                    'files_count'   => 0,
                ];

                $vaultForJs = [
                    'quota_gb'     => (float)($storage['quota_gb'] ?? 0),
                    'used_gb'      => (float)($storage['used_gb'] ?? 0),
                    'used'         => (float)($storage['used_gb'] ?? 0),
                    'free_gb'      => (float)($storage['free_gb'] ?? 0),
                    'available_gb' => (float)($storage['free_gb'] ?? 0),
                    'used_pct'     => (float)($storage['used_pct'] ?? 0),
                    'files_count'  => 0,
                    'enabled'      => $enabled,
                ];
            } catch (\Throwable $e) {
                Log::warning('[SAT:index] Error calculando resumen de bóveda', [
                    'cuenta_id' => $cuentaId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $credList          = collect();
        $initialRows       = [];
        $cartIds           = [];
        $downloadsPage     = null;
        $downloadsTotalAll = 0;

        if ($cuentaId !== '') {
            try {
                $this->cleanupExpiredHistory($cuentaId, 5);
            } catch (\Throwable) {
                // ignore
            }

            $cartIds = $this->getCartIds($cuentaId);

            $credList = SatCredential::query()
                ->where('cuenta_id', $cuentaId)
                ->orderBy('rfc')
                ->get();

            $credMap = [];
            foreach ($credList as $c) {
                $rfc = strtoupper(trim((string)$c->rfc));
                if ($rfc !== '') {
                    $credMap[$rfc] = (string)($c->razon_social ?? $c->alias ?? '');
                }
            }

            $perPage = 20;

            $baseQuery = SatDownload::query()
                ->where('cuenta_id', $cuentaId)
                ->whereNotIn('tipo', ['VAULT', 'BOVEDA'])
                ->orderByDesc('created_at');

            $downloadsTotalAll = (int)$baseQuery->count();
            $downloadsPage     = $baseQuery->paginate($perPage);

            $now        = Carbon::now();
            $collection = $downloadsPage->getCollection();

            $collection = $collection
                ->filter(function (SatDownload $d) {
                    $tipo        = strtolower((string)data_get($d, 'tipo', ''));
                    $isRequest   = (bool)data_get($d, 'is_request', false);
                    $esSolicitud = (bool)data_get($d, 'es_solicitud', false);

                    if ($isRequest || $esSolicitud) return false;
                    if (in_array($tipo, ['solicitud', 'request', 'peticion'], true)) return false;

                    return true;
                })
                ->values();

            $rowsH = $collection->map(function (SatDownload $d) use ($credMap, $cartIds, $now) {
                return $this->transformDownloadRow($d, $credMap, $cartIds, $now);
            });

            $downloadsPage->setCollection($rowsH);
            $initialRows = $rowsH->values()->all();
        }

        return view('cliente.sat.index', [
            'plan'               => $plan,
            'isProPlan'          => $isProPlan,
            'credList'           => $credList,
            'initialRows'        => $initialRows,
            'downloads'          => $initialRows,
            'downloadsPaginator' => $downloadsPage,
            'downloadsTotalAll'  => $downloadsTotalAll,
            'cuenta'             => $cuentaCliente,

            'vaultSummary'       => $vaultSummary,
            'storage'            => $vaultSummary,
            'vault'              => $vaultForJs,

            'vault_quota_gb'     => $vaultSummary['quota_gb'] ?? 0.0,
            'vault_used_gb'      => $vaultSummary['used_gb'] ?? 0.0,
            'vault_used_pct'     => $vaultSummary['used_pct'] ?? 0.0,

            'cartIds'            => $cartIds,
        ]);
    }

    protected function transformDownloadRow(
        SatDownload $d,
        array $credMap,
        array $cartIds,
        Carbon $now
    ): array {
        $rfc   = strtoupper((string)($d->rfc ?? ''));
        $alias = $credMap[$rfc] ?? (string)($d->razon_social ?? $d->alias ?? '');

        $from = $d->date_from ?? $d->desde ?? null;
        $to   = $d->date_to   ?? $d->hasta ?? null;

        $fromStr = $from ? substr((string)$from, 0, 10) : null;
        $toStr   = $to   ? substr((string)$to,   0, 10) : null;

        $expRaw = $d->expires_at ?? $d->disponible_hasta ?? null;
        $exp    = null;

        try {
            if ($expRaw instanceof Carbon) $exp = $expRaw->copy();
            elseif ($expRaw) $exp = Carbon::parse((string)$expRaw);
        } catch (\Throwable) {
            $exp = null;
        }

        if (!$exp && $d->paid_at) {
            $exp = $d->paid_at instanceof Carbon
                ? $d->paid_at->copy()->addDays(15)
                : Carbon::parse((string)$d->paid_at)->addDays(15);
        }

        if (!$exp && $d->created_at) {
            $exp = $d->created_at instanceof Carbon
                ? $d->created_at->copy()->addHours(12)
                : Carbon::parse((string)$d->created_at)->addHours(12);
        }

        $secondsLeft = null;
        if ($exp) $secondsLeft = $now->diffInSeconds($exp, false);

        $estadoStr = (string)($d->estado ?? $d->status ?? $d->sat_status ?? '');
        $estadoLow = strtolower($estadoStr);

        $pagado    = !empty($d->paid_at);
        $isExpired = false;

        if (!$pagado && $secondsLeft !== null && $secondsLeft <= 0) {
            $estadoStr = 'EXPIRADA';
            $estadoLow = 'expirada';
            $isExpired = true;
        }

        if ($pagado) {
            $estadoStr = 'PAID';
            $estadoLow = 'paid';
            $isExpired = false;
        }

        $canPay = !$pagado && in_array($estadoLow, ['ready', 'done', 'listo'], true);

        $this->hydrateDownloadMetrics($d);

        $xmlCount  = (int)($d->xml_count ?? $d->total_xml ?? 0);
        $sizeGb    = (float)($d->size_gb ?? 0);
        $sizeMb    = (float)($d->size_mb ?? 0);
        $sizeBytes = (int)($d->size_bytes ?? 0);
        $costo     = (float)($d->costo ?? 0);

        $discount   = $d->discount_pct ?? $d->descuento_pct ?? null;
        $createdStr = $d->created_at ? $d->created_at->format('Y-m-d H:i:s') : null;

        $sizeLabel = $d->size_label ?? ($sizeMb > 0
            ? number_format($sizeMb, 2) . ' Mb'
            : 'Pendiente'
        );

        $remainingLabel = '00:00:00';
        if ($secondsLeft !== null && $secondsLeft > 0) {
            $remainingLabel = gmdate('H:i:s', (int) floor($secondsLeft));
        }

        $inCart = in_array((string)$d->id, $cartIds, true);
        $pesoMb = $sizeMb > 0 ? $sizeMb : (($sizeBytes > 0) ? ($sizeBytes / (1024 * 1024)) : 0.0);
        $pesoLabel = $pesoMb > 0 ? number_format($pesoMb, 2) . ' MB' : 'Pendiente';


        return [
            'id'           => (string)$d->id,
            'dlid'         => (string)$d->id,

            'rfc'          => $rfc,
            'razon'        => $alias,
            'razon_social' => $alias,
            'alias'        => $alias,
            'tipo'         => (string)($d->tipo ?? ''),

            'desde'        => $fromStr,
            'hasta'        => $toStr,
            'fecha'        => $createdStr,

            'estado'          => $estadoStr,
            'status'          => $estadoStr,
            'status_sat'      => $estadoStr,
            'status_sat_text' => $estadoStr,

            'pagado'       => $pagado,
            'paid'         => $pagado,
            'paid_at'      => $d->paid_at ? $d->paid_at->toIso8601String() : null,
            'can_pay'      => $canPay,

            'xml_count'    => $xmlCount,
            'total_xml'    => $xmlCount,
            'size_mb'      => $sizeMb,
            'size_gb'      => $sizeGb,
            'size_bytes'   => $sizeBytes,
            'costo'        => $costo,

            'discount_pct' => $discount,

            'expires_at'      => $exp ? $exp->toIso8601String() : null,
            'time_left'       => $secondsLeft,
            'is_expired'      => $isExpired,
            'remaining_label' => $remainingLabel,

            'size_label'   => $sizeLabel,
            'cost_usd'     => $costo,
            'in_cart'      => $inCart,
            'created_at'   => $d->created_at ? $d->created_at->toIso8601String() : null,

            'peso_mb'    => (float)$pesoMb,
            'peso_label' => (string)$pesoLabel,

        ];
    } 

    /* ===================================================
     *  RFC: registrar / alias / eliminar
     * =================================================== */

    public function registerRfc(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $isAjax = $request->ajax()
            || $request->wantsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';

        $data = $request->validate([
            'rfc'   => ['required', 'string', 'min:12', 'max:13'],
            'alias' => ['nullable', 'string', 'max:190'],
        ]);

        $rfc   = strtoupper(trim((string)$data['rfc']));
        $alias = isset($data['alias']) ? trim((string)$data['alias']) : null;

        try {
            if ($cuentaId === '') {
                $msg = 'No se pudo determinar la cuenta actual.';
                if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 403);
                return redirect()->route('cliente.sat.index')->with('error', $msg);
            }

            $cred = SatCredential::query()
                ->where('cuenta_id', $cuentaId)
                ->whereRaw('UPPER(rfc) = ?', [$rfc])
                ->first();

            if (!$cred) {
                $cred = new SatCredential();
                $cred->cuenta_id = $cuentaId;
                $cred->rfc       = $rfc;
            }

            if ($alias !== null && $alias !== '') {
                $cred->razon_social = $alias;
            }

            try {
                $conn   = $cred->getConnectionName() ?? 'mysql_clientes';
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                if ($schema->hasColumn($table, 'estatus') && empty($cred->estatus)) {
                    $cred->estatus = 'pending';
                }
            } catch (\Throwable) {
                // ignore
            }

            $cred->save();

            $msg = 'RFC registrado correctamente.';
            if ($isAjax) {
                return response()->json([
                    'ok'       => true,
                    'trace_id' => $trace,
                    'rfc'      => $cred->rfc,
                    'alias'    => $cred->razon_social ?? null,
                    'msg'      => $msg,
                ]);
            }

            return redirect()->route('cliente.sat.index')->with('ok', $msg);
        } catch (\Throwable $e) {
            Log::error('[SAT:registerRfc] Error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfc,
                'ex'        => $e->getMessage(),
            ]);

            $msg = 'No se pudo registrar el RFC.';
            if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 500);
            return redirect()->route('cliente.sat.index')->with('error', $msg);
        }
    }

    public function saveAlias(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $isAjax = $request->ajax()
            || $request->wantsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';

        $data = $request->validate([
            'rfc'   => ['required', 'string', 'min:12', 'max:13'],
            'alias' => ['nullable', 'string', 'max:190'],
        ]);

        $rfc   = strtoupper(trim((string)$data['rfc']));
        $alias = trim((string)($data['alias'] ?? ''));

        try {
            if ($cuentaId === '') {
                $msg = 'No se pudo determinar la cuenta actual.';
                if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 403);
                return redirect()->route('cliente.sat.index')->with('error', $msg);
            }

            $cred = SatCredential::query()
                ->where('cuenta_id', $cuentaId)
                ->whereRaw('UPPER(rfc) = ?', [$rfc])
                ->first();

            if (!$cred) {
                $msg = 'RFC no encontrado.';
                if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 404);
                return redirect()->route('cliente.sat.index')->with('error', $msg);
            }

            $cred->razon_social = $alias !== '' ? $alias : null;
            $cred->save();

            $msg = 'Alias actualizado.';
            if ($isAjax) return response()->json(['ok' => true, 'trace_id' => $trace, 'msg' => $msg]);
            return redirect()->route('cliente.sat.index')->with('ok', $msg);
        } catch (\Throwable $e) {
            Log::error('[SAT:saveAlias] Error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfc,
                'ex'        => $e->getMessage(),
            ]);

            $msg = 'No se pudo actualizar el alias.';
            if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 500);
            return redirect()->route('cliente.sat.index')->with('error', $msg);
        }
    }

    public function deleteRfc(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = (string)$this->cuId();

        if (!$user || $cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Sesión expirada o cuenta inválida.', 'trace_id' => $trace], 401);
        }

        $data = $request->validate([
            'rfc' => ['required', 'string', 'min:12', 'max:13'],
        ]);

        $rfcUpper = strtoupper(trim((string)$data['rfc']));

        try {
            $cred = SatCredential::query()
                ->where('cuenta_id', $cuentaId)
                ->whereRaw('UPPER(rfc) = ?', [$rfcUpper])
                ->first();

            if (!$cred) {
                return response()->json(['ok' => false, 'msg' => 'RFC no encontrado en tu cuenta.', 'trace_id' => $trace], 404);
            }

            try {
                $conn   = $cred->getConnectionName() ?? 'mysql_clientes';
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                $cerPath = null;
                $keyPath = null;

                if ($schema->hasColumn($table, 'cer_path')) $cerPath = (string)($cred->cer_path ?? '');
                if ($schema->hasColumn($table, 'key_path')) $keyPath = (string)($cred->key_path ?? '');

                $diskName = config('filesystems.disks.private')
                    ? 'private'
                    : (config('filesystems.disks.vault') ? 'vault' : config('filesystems.default', 'local'));

                if ($cerPath !== '') {
                    $p = ltrim($cerPath, '/');
                    try { if (Storage::disk($diskName)->exists($p)) Storage::disk($diskName)->delete($p); } catch (\Throwable) {}
                }

                if ($keyPath !== '') {
                    $p = ltrim($keyPath, '/');
                    try { if (Storage::disk($diskName)->exists($p)) Storage::disk($diskName)->delete($p); } catch (\Throwable) {}
                }
            } catch (\Throwable $e) {
                Log::warning('[SAT:deleteRfc] No se pudieron eliminar archivos asociados', [
                    'trace_id'  => $trace,
                    'cuenta_id' => $cuentaId,
                    'rfc'       => $rfcUpper,
                    'ex'        => $e->getMessage(),
                ]);
            }

            $cred->delete();

            return response()->json([
                'ok'       => true,
                'msg'      => 'RFC eliminado correctamente.',
                'rfc'      => $rfcUpper,
                'trace_id' => $trace,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SAT:deleteRfc] Error eliminando RFC', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfcUpper,
                'ex'        => $e->getMessage(),
            ]);

            return response()->json([
                'ok'       => false,
                'msg'      => 'No se pudo eliminar el RFC.',
                'trace_id' => $trace,
            ], 500);
        }
    }

    /* ==========================================================
     * ZIP DIRECTO (por ID)
     * ========================================================== */

    public function zip(Request $request, string $id): StreamedResponse
    {
        $user = auth('web')->user();
        if (!$user) abort(401, 'Sesión expirada, vuelve a iniciar sesión.');

        $cuenta = $user->cuenta ?? null;
        if (is_array($cuenta)) $cuenta = (object)$cuenta;

        $cuentaId = (string)($this->resolveCuentaIdFromUser($user) ?? '');
        if ($cuentaId === '') abort(403, 'No se pudo determinar la cuenta actual.');

        /** @var SatDownload $download */
        $download = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('id', $id)
            ->firstOrFail();

        $paid   = !empty($download->paid_at);
        $stRaw  = (string)($download->status ?? $download->estado ?? $download->sat_status ?? '');
        $st     = strtolower($stRaw);
        $ready  = $paid || in_array($st, ['done', 'ready', 'paid', 'downloaded', 'listo', 'completed', 'finalizado'], true);

        if (!$ready) {
            abort(403, 'Este paquete aún no está listo para descarga.');
        }

        // Marcar como descargado (no bloqueante)
        try {
            if (Schema::connection('mysql_clientes')->hasTable((new SatDownload())->getTable())
                && Schema::connection('mysql_clientes')->hasColumn((new SatDownload())->getTable(), 'status')
            ) {
                if (strtolower((string)$download->status) !== 'downloaded') {
                    $download->status = 'downloaded';
                    $download->save();
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        // Disk desde meta.zip_disk o sat_zip/default
        $disk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');

        $path = $download->zip_path
            ?? $download->path
            ?? $download->file_path
            ?? null;

        if ($path) $path = ltrim((string)$path, '/');

        // Local/demo
        if (app()->environment(['local', 'development', 'testing'])) {
            try {
                $demoRel = $this->zipHelper->ensureLocalDemoZipWithCfdis($download);
                if ($demoRel) {
                    $disk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
                    $path = ltrim($demoRel, '/');
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // Resolver robusto si no existe
        $exists = false;
        try {
            if ($path && Storage::disk($disk)->exists($path)) $exists = true;
        } catch (\Throwable) {
            $exists = false;
        }

        if (!$exists) {
            try {
                [$realDisk, $realPath] = $this->resolveDownloadZipLocation($download);
                if ($realDisk !== '' && $realPath !== '') {
                    $disk = $realDisk;
                    $path = ltrim((string)$realPath, '/');
                    if ($path !== '' && Storage::disk($disk)->exists($path)) {
                        $exists = true;

                        // Persistir ruta/disk en meta para próximos hits (no bloqueante)
                        try {
                            $download->zip_path = $path;

                            $meta = [];
                            if (is_array($download->meta)) $meta = $download->meta;
                            elseif (is_string($download->meta) && $download->meta !== '') {
                                $tmp = json_decode($download->meta, true);
                                if (is_array($tmp)) $meta = $tmp;
                            }

                            $meta['zip_disk'] = (string)$disk;
                            $download->meta = $meta;
                            $download->save();
                        } catch (\Throwable) {
                            // ignore
                        }
                    }
                }
            } catch (\Throwable) {
                $exists = false;
            }
        }

        // Fallback: servir desde BÓVEDA si existe un sat_vault_file ligado al download
        if (!$exists) {
            try {
                if (class_exists(\App\Models\Cliente\SatVaultFile::class)) {
                    $vf = \App\Models\Cliente\SatVaultFile::on('mysql_clientes')
                        ->where('cuenta_id', $cuentaId)
                        ->where(function ($q) use ($id) {
                            $q->where('source_id', (string)$id)
                              ->orWhere('download_id', (string)$id);
                        })
                        ->orderByDesc('id')
                        ->first();

                    if ($vf) {
                        $vDisk = $vf->disk ?: (config('filesystems.disks.sat_vault') ? 'sat_vault' : 'local');
                        $vPath = ltrim((string)$vf->path, '/');

                        if ($vPath !== '' && Storage::disk($vDisk)->exists($vPath)) {
                            Log::info('[SAT:zip] Servido desde BÓVEDA (mapeo directo)', [
                                'cuenta_id'   => $cuentaId,
                                'download_id' => (string)$id,
                                'vault_id'    => $vf->id,
                                'disk'        => $vDisk,
                                'path'        => $vPath,
                            ]);

                            return Storage::disk($vDisk)->download($vPath, basename($vPath));
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('[SAT:zip] Fallback bóveda falló', [
                    'cuenta_id'   => $cuentaId,
                    'download_id' => (string)$id,
                    'err'         => $e->getMessage(),
                ]);
            }

            abort(404, 'Archivo ZIP no disponible.');
        }

        // Bóveda automática (no bloqueante)
        try {
            if ($this->vaultIsActiveForAccount($cuenta)) {
                $this->moveZipToVault($download);
            }
        } catch (\Throwable $e) {
            Log::warning('[SAT:zip] Error moviendo ZIP a bóveda (no bloquea descarga)', [
                'download_id' => (string)$download->id,
                'error'       => $e->getMessage(),
            ]);
        }

        $fileName = $download->zip_name
            ?? $download->filename
            ?? ('sat_' . (string)$download->id . '.zip');

        return Storage::disk($disk)->download($path, $fileName);
    }

    /* ===================================================
     *   CREAR SOLICITUDES SAT
     * =================================================== */

    public function request(Request $request): JsonResponse
    {
        $user   = $this->cu();
        $cuenta = $user?->cuenta ?? null;

        if (is_array($cuenta)) $cuenta = (object)$cuenta;

        $cuentaId = (string)$this->cuId();

        if (!$user || !$cuenta || $cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'No se encontró la cuenta del cliente.'], 422);
        }

        $data = $request->validate([
            'tipo'   => 'required|string|in:emitidos,recibidos,ambos',
            'from'   => 'required|date',
            'to'     => 'required|date|after_or_equal:from',
            'rfcs'   => 'required|array|min:1',
            'rfcs.*' => 'required|string|min:12|max:13',
        ]);

        $tipo = $data['tipo'];
        $from = Carbon::parse($data['from'])->startOfDay();
        $to   = Carbon::parse($data['to'])->endOfDay();

        $rfcs = array_values(array_unique(array_map(
            fn($r) => strtoupper(trim((string)$r)),
            (array)$data['rfcs']
        )));

        $credList = SatCredential::query()
            ->where('cuenta_id', $cuentaId)
            ->whereIn(DB::raw('UPPER(rfc)'), $rfcs)
            ->get();

        $validRfcs = $credList->filter(function ($c) {
            $estatusRaw = strtolower((string)($c->estatus ?? ''));

            $okFlag =
                !empty($c->validado ?? null)
                || !empty($c->validated_at ?? null)
                || !empty($c->has_files ?? null)
                || !empty($c->has_csd ?? null)
                || !empty($c->cer_path ?? null)
                || !empty($c->key_path ?? null)
                || in_array($estatusRaw, ['ok', 'valido', 'válido', 'validado', 'valid'], true);

            return $okFlag;
        })->pluck('rfc')->map(fn($r) => strtoupper((string)$r))->unique()->values()->all();

        if (!count($validRfcs)) {
            return response()->json(['ok' => false, 'msg' => 'Debes seleccionar al menos un RFC validado (con CSD cargado).'], 422);
        }

        $tipos = $tipo === 'ambos' ? ['emitidos', 'recibidos'] : [$tipo];

        $created = [];

        $dlModel = new SatDownload();
        $table   = $dlModel->getTable();
        $schema  = Schema::connection($dlModel->getConnectionName() ?? 'mysql_clientes');

        foreach ($validRfcs as $rfc) {
            foreach ($tipos as $tipoSat) {
                try {
                    $dl = new SatDownload();

                    if ($schema->hasColumn($table, 'id')) {
                        $dl->id = (string)Str::uuid();
                    }

                    $dl->cuenta_id = $cuentaId;
                    $dl->rfc       = $rfc;
                    $dl->tipo      = $tipoSat;

                    if ($schema->hasColumn($table, 'status'))     $dl->status     = 'pending';
                    if ($schema->hasColumn($table, 'estado'))     $dl->estado     = 'REQUESTED';
                    if ($schema->hasColumn($table, 'sat_status')) $dl->sat_status = 'REQUESTED';

                    if ($schema->hasColumn($table, 'desde'))     $dl->desde     = $from->toDateString();
                    if ($schema->hasColumn($table, 'hasta'))     $dl->hasta     = $to->toDateString();
                    if ($schema->hasColumn($table, 'date_from')) $dl->date_from = $from->toDateString();
                    if ($schema->hasColumn($table, 'date_to'))   $dl->date_to   = $to->toDateString();

                    if ($schema->hasColumn($table, 'user_id')) $dl->user_id = $user->id;

                    $dl->save();
                    $created[] = $dl;

                    try {
                        if (method_exists($this->service, 'createRequest')) {
                            $satRef = $this->service->createRequest($dl);
                            if ($satRef && $schema->hasColumn($table, 'sat_request_id')) {
                                $dl->sat_request_id = $satRef;
                                $dl->save();
                            }
                        }
                    } catch (\Throwable $e) {
                        if ($schema->hasColumn($table, 'estado'))     $dl->estado     = 'ERROR';
                        if ($schema->hasColumn($table, 'sat_status')) $dl->sat_status = 'ERROR';
                        if ($schema->hasColumn($table, 'status'))     $dl->status     = 'ERROR';
                        if ($schema->hasColumn($table, 'error_msg'))  $dl->error_msg  = $e->getMessage();
                        $dl->save();
                    }
                } catch (\Throwable $e) {
                    Log::error('[SAT:request] Error creando registro de descarga', [
                        'cuenta_id' => $cuentaId,
                        'user_id'   => $user->id,
                        'rfc'       => $rfc,
                        'tipo'      => $tipoSat,
                        'msg'       => $e->getMessage(),
                    ]);
                }
            }
        }

        if (!count($created)) {
            return response()->json(['ok' => false, 'msg' => 'No se pudieron crear las solicitudes SAT. Revisa el log.'], 500);
        }

        return response()->json(['ok' => true, 'count' => count($created)]);
    } 

    /**
     * Cancelar / eliminar una descarga SAT.
     * Regla práctica:
     * - Si está PAID: no “cancelas en SAT”, pero sí puedes ocultarla del listado (soft delete) o borrarla localmente.
     * - Si no está PAID: puedes borrarla localmente.
     */
    public function cancelDownload(Request $request): JsonResponse
    {
        $trace      = $this->trace();
        $downloadId = trim((string)($request->input('download_id') ?? ''));
        $cuentaId   = (string)$this->cuId();

        if ($downloadId === '') {
            return response()->json(['ok' => false, 'msg' => 'download_id requerido.', 'trace_id' => $trace], 422);
        }

        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 401);
        }

        try {
            $dl = SatDownload::query()
                ->where('cuenta_id', $cuentaId)
                ->where('id', $downloadId)
                ->first();

            if (!$dl) {
                return response()->json(['ok' => false, 'msg' => 'Solicitud no encontrada.', 'trace_id' => $trace], 404);
            }

            $conn = $dl->getConnectionName() ?? 'mysql_clientes';

            DB::connection($conn)->transaction(function () use ($dl, $downloadId, $cuentaId, $conn) {

                // 1) Limpia carrito por sesión (tu carrito principal)
                try {
                    $key = $this->cartKeyForCuenta($cuentaId);
                    $ids = session($key, []);
                    $ids = array_values(array_filter(array_map('strval', (array)$ids)));
                    $ids = array_values(array_diff($ids, [(string)$downloadId]));
                    session([$key => $ids]);
                } catch (\Throwable) {
                    // ignore
                }

                // 2) Limpia carrito por tabla si existe (no rompe si no existe)
                try {
                    if (Schema::connection($conn)->hasTable('sat_cart_items')) {
                        DB::connection($conn)->table('sat_cart_items')
                            ->where('cuenta_id', $cuentaId)
                            ->where('download_id', $downloadId)
                            ->delete();
                    }
                } catch (\Throwable) {
                    // ignore
                }

                // 3) Si tiene zip_path, intenta borrar ZIP (solo si NO está en bóveda)
                try {
                    $zipPath = (string)($dl->zip_path ?? '');
                    if ($zipPath !== '') {
                        $zipPath = ltrim($zipPath, '/');
                        $disk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
                        if (Storage::disk($disk)->exists($zipPath)) {
                            Storage::disk($disk)->delete($zipPath);
                        }
                    }
                } catch (\Throwable) {
                    // ignore
                }

                // 4) Borrado duro del registro (esto es lo que tú necesitas: que desaparezca del listado)
                $dl->delete();
            });

            return response()->json([
                'ok'       => true,
                'msg'      => 'Solicitud eliminada correctamente.',
                'trace_id' => $trace,
            ]);

        } catch (\Throwable $e) {
            Log::error('[SAT:cancelDownload] error', [
                'trace_id'    => $trace,
                'cuenta_id'   => $cuentaId,
                'download_id' => $downloadId,
                'error'       => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'msg' => 'Error al eliminar.', 'trace_id' => $trace], 500);
        } 
    } 

    private function formatPesoFromDownloadRow(object $dl): string
{
    $bytes = 0;

    foreach (['size_bytes','bytes'] as $col) {
        if (isset($dl->{$col}) && (int)$dl->{$col} > $bytes) {
            $bytes = (int)$dl->{$col};
        }
    }

    if ($bytes <= 0) {
        // si tu UI tiene “Pendiente” por diseño
        return 'Pendiente';
    }

    $mb = $bytes / 1024 / 1024;

    if ($mb < 1024) {
        return number_format($mb, 2) . ' Mb';
    }

    $gb = $mb / 1024;
    return number_format($gb, 3) . ' Gb';
}




    /* ===================================================
     * VERIFICAR ESTADO (AJAX)
     * =================================================== */

    public function verify(Request $request): JsonResponse
    {
        $user   = $this->cu();
        $cuenta = $user?->cuenta ?? null;

        if (is_array($cuenta)) $cuenta = (object)$cuenta;

        if (!$user || !$cuenta) {
            return response()->json(['ok' => false, 'msg' => 'No se encontró la cuenta del cliente.'], 422);
        }

        $cuentaId = (string)$this->cuId();
        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.'], 422);
        }

        $conn   = 'mysql_clientes';
        $schema = Schema::connection($conn);
        $table  = (new SatDownload())->getTable();

        $hasEstado    = $schema->hasColumn($table, 'estado');
        $hasStatus    = $schema->hasColumn($table, 'status');
        $hasSatStat   = $schema->hasColumn($table, 'sat_status');
        $hasReadyAt   = $schema->hasColumn($table, 'ready_at');
        $hasExpiresAt = $schema->hasColumn($table, 'expires_at');
        $hasTimeLeft  = $schema->hasColumn($table, 'time_left');

        $hasXmlCount  = $schema->hasColumn($table, 'xml_count') || $schema->hasColumn($table, 'total_xml');
        $hasSizeBytes = $schema->hasColumn($table, 'size_bytes') || $schema->hasColumn($table, 'bytes') || $schema->hasColumn($table, 'zip_bytes');
        $hasCosto     = $schema->hasColumn($table, 'costo');
        $hasErrorMsg  = $schema->hasColumn($table, 'error_msg');

        $q = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->whereNotIn(DB::raw('LOWER(COALESCE(tipo,""))'), ['vault', 'boveda'])
            ->orderBy('created_at', 'asc');

        if ($hasEstado) {
            $q->whereIn('estado', ['REQUESTED', 'PROCESSING', 'PENDING']);
        } elseif ($hasSatStat) {
            $q->whereIn('sat_status', ['REQUESTED', 'PROCESSING', 'PENDING']);
        } elseif ($hasStatus) {
            $q->whereIn('status', ['REQUESTED', 'PROCESSING', 'PENDING', 'pending', 'processing']);
        } else {
            return response()->json([
                'ok'  => false,
                'msg' => 'La tabla sat_downloads no tiene columnas de estado (estado/status/sat_status).',
            ], 422);
        }

        $rows    = $q->get();
        $pending = 0;
        $ready   = 0;

        if (app()->environment(['local', 'development', 'testing'])) {
            foreach ($rows as $dl) {
                if ($hasEstado)  $dl->estado     = 'DONE';
                if ($hasSatStat) $dl->sat_status = 'DONE';
                if ($hasStatus)  $dl->status     = 'DONE';

                if ($hasReadyAt && empty($dl->ready_at)) $dl->ready_at = now();
                if ($hasExpiresAt && empty($dl->expires_at)) $dl->expires_at = now()->addHours(12);

                if ($hasCosto) {
                    $xmlCount = (int)($dl->xml_count ?? $dl->total_xml ?? 0);
                    if ($xmlCount > 0 && ((float)($dl->costo ?? 0) <= 0)) {
                        $dl->costo = $this->computeDownloadCostPhp($xmlCount);
                    }
                }

                if ($hasTimeLeft && $hasExpiresAt && !empty($dl->expires_at)) {
                    $dl->time_left = max(0, now()->diffInSeconds($dl->expires_at, false));
                }

                $dl = $this->hydrateDownloadMetrics($dl);
                $dl->save();

                $ready++;
            }

            return response()->json(['ok' => true, 'pending' => 0, 'ready' => $ready]);
        }

        $readyStatuses   = ['DONE', 'READY', 'LISTO', 'COMPLETED', 'COMPLETADO', 'FINISHED', 'FINALIZADO', 'TERMINADO'];
        $errorStatuses   = ['ERROR', 'FAILED', 'CANCELLED', 'CANCELED'];
        $pendingStatuses = ['PENDING', 'PENDIENTE', 'REQUESTED', 'PROCESSING', 'EN_PROCESO', 'CREATED', 'CREADA'];

        foreach ($rows as $dl) {
            try {
                if (!method_exists($this->service, 'syncStatus')) {
                    $pending++;
                    continue;
                }

                $statusInfo = $this->service->syncStatus($dl);
                if (!is_array($statusInfo) || empty($statusInfo['status'])) {
                    $pending++;
                    continue;
                }

                $newStatus = strtoupper((string)$statusInfo['status']);

                if (in_array($newStatus, $errorStatuses, true)) {
                    if ($hasEstado)   $dl->estado     = 'ERROR';
                    if ($hasSatStat)  $dl->sat_status = 'ERROR';
                    if ($hasStatus)   $dl->status     = 'ERROR';
                    if ($hasErrorMsg) $dl->error_msg  = (string)($statusInfo['message'] ?? 'Error en descarga SAT.');

                    $dl->save();
                    continue;
                }

                if (in_array($newStatus, $pendingStatuses, true)) {
                    if ($hasEstado)  $dl->estado     = 'PROCESSING';
                    if ($hasSatStat) $dl->sat_status = $newStatus;
                    if ($hasStatus)  $dl->status     = 'PROCESSING';

                    $dl->save();
                    $pending++;
                    continue;
                }

                if (in_array($newStatus, $readyStatuses, true) || !in_array($newStatus, $errorStatuses, true)) {
                    if ($hasEstado)  $dl->estado     = 'DONE';
                    if ($hasSatStat) $dl->sat_status = 'DONE';
                    if ($hasStatus)  $dl->status     = 'DONE';

                    if ($hasReadyAt && empty($dl->ready_at)) $dl->ready_at = now();
                    if ($hasExpiresAt && empty($dl->expires_at)) $dl->expires_at = now()->addHours(12);

                    $xmlInfo = $statusInfo['xml_count'] ?? $statusInfo['total_xml'] ?? $statusInfo['num_xml'] ?? null;
                    if ($hasXmlCount && $xmlInfo !== null) {
                        $dl->xml_count = (int)$xmlInfo;
                        if ($schema->hasColumn($table, 'total_xml')) $dl->total_xml = (int)$xmlInfo;
                    }

                    $sizeInfo = $statusInfo['size_bytes'] ?? $statusInfo['bytes'] ?? $statusInfo['zip_bytes'] ?? $statusInfo['peso_bytes'] ?? null;
                    if ($hasSizeBytes && $sizeInfo !== null) {
                        if ($schema->hasColumn($table, 'size_bytes')) $dl->size_bytes = (int)$sizeInfo;
                        elseif ($schema->hasColumn($table, 'bytes'))  $dl->bytes      = (int)$sizeInfo;
                        elseif ($schema->hasColumn($table, 'zip_bytes')) $dl->zip_bytes = (int)$sizeInfo;
                    }

                    if ($hasCosto) {
                        $c = $statusInfo['costo'] ?? $statusInfo['cost_mxn'] ?? $statusInfo['precio'] ?? null;
                        if ($c !== null && (float)$c > 0) $dl->costo = (float)$c;
                    }

                    if ($hasTimeLeft && $hasExpiresAt && !empty($dl->expires_at)) {
                        $dl->time_left = max(0, now()->diffInSeconds($dl->expires_at, false));
                    }

                    $dl = $this->hydrateDownloadMetrics($dl);
                    $dl->save();

                    $ready++;
                    continue;
                }

                $pending++;
            } catch (\Throwable $e) {
                Log::error('[SAT:verify] Error sincronizando estado', [
                    'download_id' => (string)($dl->id ?? ''),
                    'cuenta_id'   => $cuentaId,
                    'msg'         => $e->getMessage(),
                ]);
                $pending++;
            }
        }

        return response()->json(['ok' => true, 'pending' => $pending, 'ready' => $ready]);
    }

    /* ===================================================
     *  CREDENCIALES
     * =================================================== */

    public function storeCredentials(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $isAjax = $request->ajax()
            || $request->wantsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';

        $soloGuardar = (string)$request->input('solo_guardar') === '1';

        if ($cuentaId) {
            $this->cleanupExpiredHistory((string)$cuentaId);
        }

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
            $msg = 'El archivo .cer no es válido.';
            if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 422);
            return redirect()->route('cliente.sat.index')->with('error', $msg);
        }

        if ($key && strtolower($key->getClientOriginalExtension()) !== 'key') {
            $msg = 'El archivo .key no es válido.';
            if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 422);
            return redirect()->route('cliente.sat.index')->with('error', $msg);
        }

        $password = (string)($data['key_password'] ?? $data['pwd'] ?? '');
        $alias    = $data['alias'] ?? null;

        try {
            $rfcUpper = strtoupper((string)$data['rfc']);

            $cred = $this->service->upsertCredentials(
                $cuentaId,
                $rfcUpper,
                $cer,
                $key,
                $password
            );

            if ($alias !== null && $alias !== '') {
                $cred->razon_social = $alias;
            }

            $okValidacion    = true;
            $validationError = null;

            if (!$soloGuardar) {
                try {
                    $okValidacion = (bool)$this->service->validateCredentials($cred);
                } catch (\Throwable $ex) {
                    $okValidacion    = false;
                    $validationError = $ex->getMessage();

                    Log::warning('[SAT:CSD] Validación lanzó excepción', [
                        'trace_id'  => $trace,
                        'cuenta_id' => $cuentaId,
                        'rfc'       => $rfcUpper,
                        'ex'        => $validationError,
                    ]);
                }
            }

            try {
                $conn   = $cred->getConnectionName() ?? 'mysql_clientes';
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                $hasEstatus     = $schema->hasColumn($table, 'estatus');
                $hasValidado    = $schema->hasColumn($table, 'validado');
                $hasValidatedAt = $schema->hasColumn($table, 'validated_at');

                $hasCsdError = $schema->hasColumn($table, 'csd_error');
                $hasErrorMsg = $schema->hasColumn($table, 'error_msg');

                if ($soloGuardar) {
                    if ($hasEstatus)     $cred->estatus      = 'pending';
                    if ($hasValidado)    $cred->validado     = 0;
                    if ($hasValidatedAt) $cred->validated_at = null;

                    if ($hasCsdError) $cred->csd_error = null;
                    if ($hasErrorMsg) $cred->error_msg = null;
                } else {
                    if ($okValidacion) {
                        if ($hasEstatus)     $cred->estatus      = 'valid';
                        if ($hasValidado)    $cred->validado     = 1;
                        if ($hasValidatedAt) $cred->validated_at = now();

                        if ($hasCsdError) $cred->csd_error = null;
                        if ($hasErrorMsg) $cred->error_msg = null;
                    } else {
                        if ($hasEstatus)     $cred->estatus      = 'invalid';
                        if ($hasValidado)    $cred->validado     = 0;
                        if ($hasValidatedAt) $cred->validated_at = null;

                        $msgErr = $validationError ?: 'Validación CSD no exitosa. Verifica .cer, .key y contraseña.';
                        if ($hasCsdError) $cred->csd_error = $msgErr;
                        if ($hasErrorMsg) $cred->error_msg = $msgErr;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[SAT:CSD] No se pudieron persistir flags de validación', [
                    'trace_id'  => $trace,
                    'cuenta_id' => $cuentaId,
                    'rfc'       => $rfcUpper,
                    'ex'        => $e->getMessage(),
                ]);
            }

            $cred->save();

            if ($soloGuardar) {
                $msg = 'Credenciales guardadas correctamente.';
                $ok  = true;
            } else {
                if ($okValidacion) {
                    $msg = 'CSD validado correctamente.';
                    $ok  = true;
                } else {
                    $msg = 'Archivos CSD guardados, pero la validación no fue exitosa. Verifica el .cer, .key y la contraseña.';
                    $ok  = true;
                }
            }

            if ($isAjax) {
                return response()->json([
                    'ok'               => $ok,
                    'validated'        => (bool)$okValidacion,
                    'validation_error' => $validationError,
                    'trace_id'         => $trace,
                    'rfc'              => $cred->rfc,
                    'alias'            => $cred->razon_social,
                    'solo_guardar'     => $soloGuardar,
                    'msg'              => $msg,
                ], 200);
            }

            if ($soloGuardar || $okValidacion) {
                return redirect()->route('cliente.sat.index')->with('ok', $msg);
            }

            return redirect()->route('cliente.sat.index')->with('info', $msg);
        } catch (\Throwable $e) {
            Log::error('[SAT:storeCredentials] Error', [
                'trace_id'  => $trace,
                'cuenta_id' => $cuentaId,
                'ex'        => $e->getMessage(),
            ]);

            $msg = 'Error guardando credenciales SAT.';
            if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 500);
            return redirect()->route('cliente.sat.index')->with('error', $msg);
        }
    }

    /* ===================================================
     *  LIMPIEZA EXPIRADAS
     * =================================================== */

    private function cleanupExpiredHistory(string $cuentaId, int $keep = 0): void
    {
        $now = Carbon::now();

        $expired = SatDownload::where('cuenta_id', $cuentaId)
            ->where(function ($q) use ($now) {
                $q->whereNotNull('expires_at')
                    ->where('expires_at', '<=', $now)
                    ->orWhere(function ($q2) use ($now) {
                        $q2->whereNull('expires_at')
                            ->whereNotNull('created_at')
                            ->where('created_at', '<=', $now->copy()->subHours(12));
                    });
            })
            ->get();

        if ($expired->isEmpty()) return;

        foreach ($expired as $d) {
            try {
                if (!empty($d->zip_path)) {
                    $relative = ltrim((string)$d->zip_path, '/');
                    $diskName = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
                    $disk = Storage::disk($diskName);
                    if ($relative !== '' && $disk->exists($relative)) $disk->delete($relative);
                }
            } catch (\Throwable) {
                // ignore
            }

            $d->delete();
        }
    }

    /* ===================================================
     *  CARRITO
     * =================================================== */

    private function cartKeyForCuenta(string $cuentaId): string
    {
        return 'sat_cart_' . $cuentaId;
    }

    private function getCartIds(string $cuentaId): array
    {
        $key = $this->cartKeyForCuenta($cuentaId);
        $ids = session($key, []);
        return array_values(array_filter(array_unique(array_map('strval', (array)$ids))));
    }

    private function putCartIdsForCuenta(string $cuentaId, array $ids): void
    {
        $key = $this->cartKeyForCuenta($cuentaId);
        session([$key => array_values(array_unique(array_map('strval', $ids)))]);
    }

    public function charts(Request $request): JsonResponse
    {
        return response()->json([
            'ok'    => true,
            'rows'  => [],
            'totals'=> [
                'count' => 0,
                'sub'   => 0.0,
                'iva'   => 0.0,
                'tot'   => 0.0,
            ],
        ]);
    }

    /* ==========================================================
     *  ZIP: resolver ubicación (disk + path)
     * ========================================================== */

    protected function resolveDownloadZipLocation(SatDownload $download): array
    {
        $meta = [];
        try {
            if (is_array($download->meta)) {
                $meta = $download->meta;
            } elseif (is_string($download->meta) && $download->meta !== '') {
                $tmp = json_decode($download->meta, true);
                if (is_array($tmp)) $meta = $tmp;
            }
        } catch (\Throwable) {
            $meta = [];
        }

        $zipDisk = (string)($meta['zip_disk'] ?? '');
        if ($zipDisk === '') {
            $zipDisk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
        }

        $zipPath = ltrim((string)($download->zip_path ?? ''), '/');
        if ($zipPath !== '') {
            try {
                if (Storage::disk($zipDisk)->exists($zipPath)) return [$zipDisk, $zipPath];
            } catch (\Throwable) {
                // continue
            }
        }

        $cuentaId  = (string)($download->cuenta_id ?? '');
        $packageId = (string)($download->package_id ?? $download->packageId ?? $download->package ?? '');

        if ($cuentaId !== '' && $packageId !== '') {
            $candPkg = "sat/packages/{$cuentaId}/pkg_{$packageId}.zip";
            try {
                if (Storage::disk($zipDisk)->exists($candPkg)) return [$zipDisk, $candPkg];
            } catch (\Throwable) {
                // continue
            }
        }

        $id = (string)($download->id ?? '');
        if ($cuentaId !== '' && $id !== '') {
            $candLegacy = "sat/packages/{$cuentaId}/pkg_{$id}.zip";
            try {
                if (Storage::disk($zipDisk)->exists($candLegacy)) return [$zipDisk, $candLegacy];
            } catch (\Throwable) {
                // continue
            }
        }

        try {
            $needle = $packageId !== '' ? $packageId : $id;
            $found  = $this->findZipByListing($zipDisk, $cuentaId, $needle);
            if ($found !== '') return [$zipDisk, ltrim($found, '/')];
        } catch (\Throwable) {
            // ignore
        }

        return ['', ''];
    }

    protected function findZipByListing(string $disk, string $cuentaId, string $downloadId): string
    {
        $dirs = [
            'packages/' . $cuentaId,
            'sat/packages/' . $cuentaId,
            'sat/packages/' . $cuentaId . '/done',
            'sat/packages/' . $cuentaId . '/paid',
            'sat/demo',
        ];

        foreach ($dirs as $dir) {
            $dir = trim($dir, '/');
            try {
                if (!Storage::disk($disk)->exists($dir)) continue;

                $files = [];
                try { $files = array_merge($files, Storage::disk($disk)->files($dir)); } catch (\Throwable) {}
                try { $files = array_merge($files, Storage::disk($disk)->allFiles($dir)); } catch (\Throwable) {}

                if (!count($files)) continue;

                foreach ($files as $f) {
                    $f = ltrim((string)$f, '/');
                    if ($f === '') continue;

                    $low = strtolower($f);
                    if (!str_ends_with($low, '.zip')) continue;

                    if (stripos($f, $downloadId) !== false) {
                        return $f;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return '';
    }

    /* ==========================================================
     *  BÓVEDA: BACKFILL MASIVO
     * ========================================================== */

    public function vaultBackfill(Request $request): JsonResponse
    {
        $trace = $this->trace();

        $user = auth('web')->user();
        if (!$user) return response()->json(['ok' => false, 'msg' => 'Sesión expirada.', 'trace_id' => $trace], 401);

        $cuenta = $user->cuenta ?? null;
        if (is_array($cuenta)) $cuenta = (object)$cuenta;

        $cuentaId = (string)($this->resolveCuentaIdFromUser($user) ?? '');
        if ($cuentaId === '') return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 422);

        if (!$this->vaultIsActiveForAccount($cuenta)) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'La bóveda no está activa para esta cuenta.',
                'trace_id' => $trace,
            ], 422);
        }

        $onlyTipo = strtolower((string)$request->get('tipo', ''));

        $q = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->whereIn(DB::raw('LOWER(COALESCE(status,""))'), ['done', 'paid', 'downloaded'])
            ->whereNotIn(DB::raw('LOWER(COALESCE(tipo,""))'), ['vault', 'boveda'])
            ->orderBy('created_at', 'asc');

        if (in_array($onlyTipo, ['emitidos', 'recibidos'], true)) {
            $q->whereRaw('LOWER(COALESCE(tipo,"")) = ?', [$onlyTipo]);
        }

        $rows = $q->get();

        $moved   = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($rows as $dl) {
            try {
                $exists = false;
                try {
                    if (Schema::connection('mysql_clientes')->hasTable('sat_vault_files')) {
                        $exists = DB::connection('mysql_clientes')->table('sat_vault_files')
                            ->where('cuenta_id', $cuentaId)
                            ->where('source', 'sat_download')
                            ->where('source_id', (string)$dl->id)
                            ->exists();
                    }
                } catch (\Throwable) {
                    $exists = false;
                }

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $this->moveZipToVault($dl);

                $ok = false;
                try {
                    $ok = DB::connection('mysql_clientes')->table('sat_vault_files')
                        ->where('cuenta_id', $cuentaId)
                        ->where('source', 'sat_download')
                        ->where('source_id', (string)$dl->id)
                        ->exists();
                } catch (\Throwable) {
                    $ok = false;
                }

                if ($ok) $moved++;
                else $failed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('[SAT:vaultBackfill] Falló backfill de un download', [
                    'trace_id'    => $trace,
                    'cuenta_id'   => $cuentaId,
                    'download_id' => (string)($dl->id ?? ''),
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'total'    => $rows->count(),
            'moved'    => $moved,
            'skipped'  => $skipped,
            'failed'   => $failed,
            'msg'      => 'Backfill finalizado.',
        ]);
    }

    /* ==========================================================
     *  BÓVEDA: mover ZIP (robusto y consistente por RFC)
     * ========================================================== */

    protected function moveZipToVault(SatDownload $download): void
    {
        $conn = 'mysql_clientes';

        try {
            $cuentaId = (string)($download->cuenta_id ?? '');
            if ($cuentaId === '') return;

            $tipo = strtolower((string)($download->tipo ?? ''));
            if (!in_array($tipo, ['emitidos', 'recibidos', 'ambos'], true)) return;

            [$srcDisk, $srcPath] = $this->resolveDownloadZipLocation($download);
            if ($srcDisk === '' || $srcPath === '') {
                Log::warning('[SAT:Vault] No se pudo resolver ZIP para mover a bóveda', [
                    'download_id' => (string)($download->id ?? ''),
                    'cuenta_id'   => $cuentaId,
                    'zip_path'    => (string)($download->zip_path ?? ''),
                ]);
                return;
            }

            if (Schema::connection($conn)->hasTable('sat_vault_files')) {
                $exists = DB::connection($conn)->table('sat_vault_files')
                    ->where('cuenta_id', $cuentaId)
                    ->where('source', 'sat_download')
                    ->where('source_id', (string)$download->id)
                    ->first();

                if ($exists && !empty($exists->path)) {
                    try {
                        $d = (string)($exists->disk ?? (config('filesystems.disks.sat_vault') ? 'sat_vault' : 'local'));
                        $p = ltrim((string)$exists->path, '/');
                        if ($p !== '' && Storage::disk($d)->exists($p)) return;
                    } catch (\Throwable) {
                        // re-copiar
                    }
                }
            }

            $cuentaObj  = $this->fetchCuentaObjForVault($cuentaId);
            $storage    = $this->buildVaultStorageSummary($cuentaId, $cuentaObj);
            $quotaBytes = (int)($storage['quota_bytes'] ?? 0);
            $usedBytes  = (int)($storage['used_bytes'] ?? 0);
            if ($quotaBytes <= 0) return;

            $srcBytes = 0;
            try { $srcBytes = (int) Storage::disk($srcDisk)->size($srcPath); } catch (\Throwable) {}
            if ($srcBytes <= 0) return;

            if (($usedBytes + $srcBytes) > $quotaBytes) {
                Log::info('[SAT:Vault] Sin espacio suficiente para copiar ZIP', [
                    'cuenta_id' => $cuentaId,
                    'need'      => $srcBytes,
                    'used'      => $usedBytes,
                    'quota'     => $quotaBytes,
                ]);
                return;
            }

            $vaultDisk = config('filesystems.disks.sat_vault')
                ? 'sat_vault'
                : (config('filesystems.disks.vault') ? 'vault' : (config('filesystems.disks.private') ? 'private' : $srcDisk));

            $rfc = strtoupper((string)($download->rfc ?? ''));
            if ($rfc === '') $rfc = 'XAXX010101000';

            $now     = Carbon::now();
            $baseDir = "vault/{$cuentaId}/{$rfc}";

            $destPath = $baseDir . '/SAT_' . $rfc . '_' . $now->format('Ymd_His') . '_' . (string)$download->id . '.zip';

            try {
                if (!Storage::disk($vaultDisk)->exists($baseDir)) {
                    Storage::disk($vaultDisk)->makeDirectory($baseDir);
                }
            } catch (\Throwable) {
                // ignore
            }

            $read = null;
            try { $read = Storage::disk($srcDisk)->readStream($srcPath); } catch (\Throwable) {}
            if (!$read) return;

            $ok = false;
            try { $ok = (bool) Storage::disk($vaultDisk)->writeStream($destPath, $read); } catch (\Throwable) { $ok = false; }
            if (is_resource($read)) fclose($read);
            if (!$ok) return;

            $bytes = 0;
            try { $bytes = (int) Storage::disk($vaultDisk)->size($destPath); } catch (\Throwable) {}
            if ($bytes <= 0) {
                try { Storage::disk($vaultDisk)->delete($destPath); } catch (\Throwable) {}
                return;
            }

            if (!Schema::connection($conn)->hasTable('sat_vault_files')) return;

            $data = [
                'cuenta_id'  => $cuentaId,
                'source'     => 'sat_download',
                'source_id'  => (string)$download->id,
                'rfc'        => $rfc,
                'filename'   => basename($destPath),
                'path'       => $destPath,
                'disk'       => $vaultDisk,
                'mime'       => 'application/zip',
                'bytes'      => $bytes,
                'updated_at' => Carbon::now()->toDateTimeString(),
            ];

            $existing = DB::connection($conn)->table('sat_vault_files')
                ->where('cuenta_id', $cuentaId)
                ->where('source', 'sat_download')
                ->where('source_id', (string)$download->id)
                ->first();

            if ($existing) {
                DB::connection($conn)->table('sat_vault_files')->where('id', $existing->id)->update($data);
            } else {
                $data['created_at'] = Carbon::now()->toDateTimeString();
                DB::connection($conn)->table('sat_vault_files')->insert($data);
            }

            Log::info('[SAT:Vault] ZIP copiado a bóveda', [
                'cuenta_id'   => $cuentaId,
                'download_id' => (string)$download->id,
                'src_disk'    => $srcDisk,
                'src_path'    => $srcPath,
                'vault_disk'  => $vaultDisk,
                'vault_path'  => $destPath,
                'bytes'       => $bytes,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SAT:Vault] Error moviendo ZIP a bóveda', [
                'download_id' => (string)($download->id ?? ''),
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
