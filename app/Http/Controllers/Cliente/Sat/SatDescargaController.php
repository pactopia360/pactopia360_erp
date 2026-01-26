<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Cliente\Sat\SatDescargaController.php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\SatDownloadService;
use App\Services\Sat\SatDownloadZipHelper;
use App\Services\Sat\VaultAccountSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;



final class SatDescargaController extends Controller
{
    public function __construct(
        private readonly SatDownloadService         $service,
        private readonly SatDownloadZipHelper       $zipHelper,
        private readonly VaultAccountSummaryService $vaultSummaryService,
    ) {}

    /* ===================================================
     *  AUTH / SESSION HELPERS (SOT Cliente)
     * =================================================== */

    private function clientGuard(): string
    {
        try {
            auth()->guard('cliente');
            return 'cliente';
        } catch (\Throwable) {
            return 'web';
        }
    }

    private function cu(): ?object
    {
        try {
            $u = auth('cliente')->user();
            if ($u) return $u;
        } catch (\Throwable) {}

        try {
            return auth('web')->user();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveCuentaIdFromUser($user): ?string
    {
        if (!$user) return null;

        if (!empty($user->cuenta_id)) return (string) $user->cuenta_id;

        if (isset($user->cuenta)) {
            $c = $user->cuenta;
            if (is_object($c)) return (string) ($c->id ?? $c->cuenta_id ?? '');
            if (is_array($c))  return (string) ($c['id'] ?? $c['cuenta_id'] ?? '');
        }

        // fallback por sesión si existe
        foreach ([
            'cliente.cuenta_id',
            'cliente.account_id',
            'client.cuenta_id',
            'client.account_id',
            'cuenta_id',
            'account_id',
            'client_cuenta_id',
            'client_account_id',
        ] as $k) {
            $v = (string) session($k, '');
            if ($v !== '') return $v;
        }

        return null;
    }

    private function cuId(): string
    {
        $u = $this->cu();
        return (string) ($this->resolveCuentaIdFromUser($u) ?? '');
    }

    private function trace(): string
    {
        return (string) Str::ulid();
    }

    private function isAjax(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /* ===================================================
     *  HELPERS COSTO / PESO / EXPIRACIÓN
     * =================================================== */

    private function computeDownloadCost(int $numXml): float
    {
        $n = max(0, $numXml);

        if ($n <= 0)        return 0.0;
        if ($n <= 5000)     return (float) ($n * 1);
        if ($n <= 25000)    return (float) ($n * 0.08);
        if ($n <= 40000)    return (float) ($n * 0.05);
        if ($n <= 100000)   return (float) ($n * 0.03);
        if ($n <= 500000)   return 12500.0;
        if ($n <= 1000000)  return 18500.0;
        if ($n <= 2000000)  return 25000.0;
        if ($n <= 3000000)  return 31000.0;

        return (float) ($n * 0.01);
    }

    protected function computeDownloadCostPhp(int $n): float
    {
        return $this->computeDownloadCost($n);
    }

    private function computeCostFromSizeOrXml(?int $sizeBytes, int $xmlCount): float
    {
        $xmlCount = max(0, (int) $xmlCount);

        if ($xmlCount > 0) {
            return $this->computeDownloadCost($xmlCount);
        }

        $b = (int) ($sizeBytes ?? 0);
        if ($b <= 0) return 0.0;

        $estXml = (int) max(1, (int) ceil($b / 4096)); // 4KB promedio
        return $this->computeDownloadCost($estXml);
    }

    private function hydrateDownloadMetrics(SatDownload $d): SatDownload
    {
        $conn   = $d->getConnectionName() ?? 'mysql_clientes';
        $table  = $d->getTable();
        $schema = Schema::connection($conn);

        $has = static function (string $col) use ($schema, $table): bool {
            try { return $schema->hasColumn($table, $col); } catch (\Throwable) { return false; }
        };

        // meta puede venir array por casts; si no, intenta decodificar
        $meta = [];
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
            if (!is_null($val) && (int) $val > 0) {
                $sizeBytes = (int) $val;
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
            if (!is_null($val) && (float) $val > 0) {
                $sizeMb = (float) $val;
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
            if (!is_null($val) && (float) $val > 0) {
                $sizeGb = (float) $val;
                break;
            }
        }

        if (!$sizeBytes && $sizeMb) $sizeBytes = (int) round($sizeMb * 1024 * 1024);
        if (!$sizeBytes && $sizeGb) $sizeBytes = (int) round($sizeGb * 1024 * 1024 * 1024);

        if ($sizeBytes && !$sizeMb) $sizeMb = $sizeBytes / (1024 * 1024);
        if ($sizeBytes && !$sizeGb) $sizeGb = $sizeBytes / (1024 * 1024 * 1024);
        if (!$sizeMb && $sizeGb)    $sizeMb = $sizeGb * 1024;

        // 3) Si aún no hay tamaño, intenta size real del zip_path
        if ((!$sizeBytes || $sizeBytes <= 0) && !empty($d->zip_path)) {
            try {
                $diskName = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
                $disk     = Storage::disk($diskName);
                $relative = ltrim((string) $d->zip_path, '/');

                if ($relative !== '' && $disk->exists($relative)) {
                    $sizeBytes = (int) $disk->size($relative);
                }
            } catch (\Throwable) {}
        }

        // 4) Si aún no existe, resuelve ubicación
        if ((!$sizeBytes || $sizeBytes <= 0)) {
            try {
                [$realDisk, $realPath] = $this->resolveDownloadZipLocation($d);
                if ($realDisk !== '' && $realPath !== '') {
                    $realPath = ltrim((string) $realPath, '/');
                    if ($realPath !== '' && Storage::disk($realDisk)->exists($realPath)) {
                        $sizeBytes = (int) Storage::disk($realDisk)->size($realPath);

                        if ($has('zip_path')) $d->zip_path = $realPath;

                        $meta['zip_disk'] = (string) $realDisk;
                        $d->meta = $meta;
                    }
                }
            } catch (\Throwable) {}
        }

        // Recalcula MB/GB final
        if ($sizeBytes && $sizeBytes > 0) {
            $sizeMb = $sizeMb ?: ($sizeBytes / (1024 * 1024));
            $sizeGb = $sizeGb ?: ($sizeBytes / (1024 * 1024 * 1024));
        }

        // Asigna (persistible si existe columna) + en memoria para UI
        if ($sizeBytes && $sizeBytes > 0) {
            if ($has('size_bytes')) $d->size_bytes = $sizeBytes;
            elseif ($has('bytes'))  $d->bytes = $sizeBytes;

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

        $finalCost = (float) $currentCost;

        if ($finalCost <= 0.0) {
            $finalCost = $this->computeCostFromSizeOrXml(
                $sizeBytes ? (int) $sizeBytes : null,
                (int) ($xmlCount ?? 0)
            );
        }

        if ($has('costo')) $d->costo = $finalCost;
        $d->setAttribute('costo', $finalCost);

        // Expiración
        if (!$d->expires_at && !empty($d->disponible_hasta)) {
            try { $d->expires_at = Carbon::parse((string) $d->disponible_hasta); } catch (\Throwable) {}
        } elseif (!$d->expires_at && $d->created_at) {
            $d->expires_at = $d->created_at instanceof Carbon
                ? $d->created_at->copy()->addHours(12)
                : Carbon::parse((string) $d->created_at)->addHours(12);
        }

        return $d;
    }

    private function formatPesoFromDownloadRow(object $dl): string
    {
        $bytes = 0;

        foreach (['size_bytes', 'bytes'] as $col) {
            if (isset($dl->{$col}) && (int) $dl->{$col} > $bytes) {
                $bytes = (int) $dl->{$col};
            }
        }

        if ($bytes <= 0) return 'Pendiente';

        $mb = $bytes / 1024 / 1024;

        if ($mb < 1024) return number_format($mb, 2) . ' Mb';

        $gb = $mb / 1024;
        return number_format($gb, 3) . ' Gb';
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
        } catch (\Throwable) {}

        $conn     = 'mysql_clientes';
        $cuentaId = (string) $cuentaId;

        $planRaw   = (string) ($cuentaObj->plan_actual ?? 'FREE');
        $plan      = strtoupper($planRaw);
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        $vaultBaseGb = $isProPlan ? (float) config('services.sat.vault.base_gb_pro', 0.0) : 0.0;

        $quotaBytesFromAccount = 0;
        $schema = Schema::connection($conn);

        if ($schema->hasTable('cuentas_cliente')) {
            if ($schema->hasColumn('cuentas_cliente', 'vault_quota_bytes')) {
                $quotaBytesFromAccount = (int) ($cuentaObj->vault_quota_bytes ?? 0);
            } elseif ($schema->hasColumn('cuentas_cliente', 'vault_quota_gb')) {
                $quotaGb = (float) ($cuentaObj->vault_quota_gb ?? 0);
                $quotaBytesFromAccount = (int) round($quotaGb * 1024 * 1024 * 1024);
            }
        }

        $quotaGbFromAccount = $quotaBytesFromAccount > 0 ? ($quotaBytesFromAccount / 1024 / 1024 / 1024) : 0.0;

        $quotaGbFromVaultRows = 0.0;
        if ($schema->hasTable('sat_downloads')) {
            try {
                $vaultRowsPaid = SatDownload::query()
                    ->where('cuenta_id', $cuentaId)
                    ->whereRaw('LOWER(COALESCE(tipo,"")) IN ("vault","boveda")')
                    ->where(function ($q) {
                        $q->whereNotNull('paid_at')
                          ->orWhereRaw('LOWER(COALESCE(status,"")) IN ("paid","pagado")');
                    })
                    ->get();

                $totalGb = 0.0;
                foreach ($vaultRowsPaid as $row) {
                    $gb = (float) ($row->vault_gb ?? $row->gb ?? 0);
                    if ($gb <= 0) {
                        $alias = (string) ($row->alias ?? $row->nombre ?? '');
                        if ($alias !== '' && preg_match('/(\d+)\s*gb/i', $alias, $m)) {
                            $gb = (float) $m[1];
                        }
                    }
                    if ($gb > 0) $totalGb += $gb;
                }
                $quotaGbFromVaultRows = max(0.0, $totalGb);
            } catch (\Throwable) {}
        }

        $quotaGbComputed = max(0.0, $vaultBaseGb + $quotaGbFromVaultRows);
        $quotaGbFinal    = max($quotaGbComputed, $quotaGbFromAccount);
        $quotaBytesFinal = (int) round($quotaGbFinal * 1024 * 1024 * 1024);

        $usedBytes = 0;
        if ($schema->hasTable('cuentas_cliente') && $schema->hasColumn('cuentas_cliente', 'vault_used_bytes')) {
            $usedBytes = (int) ($cuentaObj->vault_used_bytes ?? 0);
        }

        if ($usedBytes <= 0 && $schema->hasTable('sat_vault_files') && $schema->hasColumn('sat_vault_files', 'bytes')) {
            try {
                $usedBytes = (int) DB::connection($conn)->table('sat_vault_files')->where('cuenta_id', $cuentaId)->sum('bytes');
            } catch (\Throwable) {}
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
            $summary = $this->buildVaultStorageSummary($cuentaId, $cuentaObj ?? (object) []);
            return ((int) ($summary['quota_bytes'] ?? 0)) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function vaultIsActiveForAccount($cuenta): bool
    {
        if (!$cuenta) return false;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $cuentaId = (string) ($cuenta->id ?? $cuenta->cuenta_id ?? '');
        return $this->hasActiveVault($cuentaId, $cuenta);
    }

    private function fetchCuentaObjForVault(string $cuentaId): object
    {

        try {
            if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                $row = DB::connection('mysql_clientes')
                    ->table('cuentas_cliente')
                    ->where('id', $cuentaId)
                    ->first();
                if ($row) return (object) $row;
            }
        } catch (\Throwable) {}

        $u = $this->cu();
        $c = $u?->cuenta ?? null;
        if (is_array($c)) $c = (object) $c;
        if (is_object($c)) return $c;

        return (object) ['id' => $cuentaId];
    }

        /**
     * SOT: Cuenta del cliente desde mysql_clientes.cuentas_cliente
     * (porque en tu entorno NO existe mysql_clientes.cuentas).
     */
    private function fetchCuentaCliente(string $cuentaId): ?object
    {
        $cuentaId = trim((string) $cuentaId);
        if ($cuentaId === '') return null;

        try {
            $conn = 'mysql_clientes';

            if (!Schema::connection($conn)->hasTable('cuentas_cliente')) {
                return null;
            }

            $row = DB::connection($conn)
                ->table('cuentas_cliente')
                ->where('id', $cuentaId)
                ->first();

            return $row ? (object) $row : null;
        } catch (\Throwable) {
            return null;
        }
    }


    /**
     * RFC options para "Descargas manuales":
     * - Solo RFCs válidos (con CSD / validado).
     * - Formato uniforme para Blade/JS.
     */
    private function buildRfcOptionsForManual($credList): array
    {
        $out = [];

        foreach (collect($credList) as $c) {
            $rfc = strtoupper(trim((string) ($c->rfc ?? '')));
            if ($rfc === '') continue;

            $estatusRaw = strtolower((string) ($c->estatus ?? ''));

            $isValid =
                !empty($c->validado ?? null)
                || !empty($c->validated_at ?? null)
                || !empty($c->has_files ?? null)
                || !empty($c->has_csd ?? null)
                || !empty($c->cer_path ?? null)
                || !empty($c->key_path ?? null)
                || in_array($estatusRaw, ['ok', 'valido', 'válido', 'validado', 'valid'], true);

            if (!$isValid) continue;

            $alias = trim((string) ($c->razon_social ?? $c->alias ?? ''));
            $out[] = [
                'rf'    => $rfc,
                'alias' => $alias !== '' ? $alias : null,
            ];
        }

        // orden por RFC
        usort($out, static fn($a, $b) => strcmp((string)$a['rf'], (string)$b['rf']));

        return $out;
    }


    /* ===================================================
     *  VISTA SAT
     * =================================================== */

    public function index(Request $request)
    {
        $user = $this->cu();
        if (!$user) {
            return redirect()->route('cliente.login');
        }

        // ✅ CuentaId primero (SOT)
        $cuentaId = (string) ($this->resolveCuentaIdFromUser($user) ?? '');

        // ✅ SOT: cuentas_cliente (porque NO existe mysql_clientes.cuentas)
        $cuentaCliente = null;
        if ($cuentaId !== '') {
            $cuentaCliente = $this->fetchCuentaCliente($cuentaId);
        }

        // Fallback: si en tu auth/modelo existe $user->cuenta úsalo solo como respaldo
        if (!$cuentaCliente) {
            $tmp = $user?->cuenta ?? null;
            if (is_array($tmp)) $tmp = (object) $tmp;
            if (is_object($tmp)) {
                // Si no teníamos cuentaId, intenta inferirlo
                if ($cuentaId === '') {
                    $cuentaId = (string) ($tmp->id ?? $tmp->cuenta_id ?? '');
                }
                $cuentaCliente = $tmp;
            }
        }

        $planRaw   = (string) (($cuentaCliente->plan_actual ?? $cuentaCliente->plan ?? 'FREE'));

        $plan      = strtoupper($planRaw);
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        // =========================
        // Bóveda summary (defensivo)
        // =========================
        $vaultSummary = [
            'has_quota'       => false,
            'quota_gb'        => 0.0,
            'quota_bytes'     => 0,
            'used_gb'         => 0.0,
            'used_bytes'      => 0,
            'available_gb'    => 0.0,
            'used_pct'        => 0.0,
            'available_pct'   => 0.0,
            'files_count'     => 0,
        ];

        $vaultForJs = [
            'quota_gb'      => 0.0,
            'used_gb'       => 0.0,
            'used'          => 0.0,
            'free_gb'       => 0.0,
            'available_gb'  => 0.0,
            'used_pct'      => 0.0,
            'files_count'   => 0,
            'enabled'       => false,
        ];

        if ($cuentaId !== '') {
            try {
                $storage = $this->buildVaultStorageSummary($cuentaId, $cuentaCliente);
                $enabled = ((int)($storage['quota_bytes'] ?? 0)) > 0;

                // ✅ files_count (si existe sat_vault_files)
                $vaultFilesCount = 0;
                try {
                    if (Schema::connection('mysql_clientes')->hasTable('sat_vault_files')) {
                        $vaultFilesCount = (int) DB::connection('mysql_clientes')
                            ->table('sat_vault_files')
                            ->where('cuenta_id', $cuentaId)
                            ->count();
                    }
                } catch (\Throwable) {
                    $vaultFilesCount = 0;
                }


                $vaultSummary = [
                    'has_quota'       => $enabled,
                    'quota_gb'        => (float)($storage['quota_gb'] ?? 0),
                    'quota_bytes'     => (int)($storage['quota_bytes'] ?? 0),
                    'used_gb'         => (float)($storage['used_gb'] ?? 0),
                    'used_bytes'      => (int)($storage['used_bytes'] ?? 0),
                    'available_gb'    => (float)($storage['free_gb'] ?? 0),
                    'used_pct'        => (float)($storage['used_pct'] ?? 0),
                    'available_pct'   => (float)($storage['free_pct'] ?? 0),
                    'files_count' => $vaultFilesCount,
                ];

                $vaultForJs = [
                    'quota_gb'      => (float)($storage['quota_gb'] ?? 0),
                    'used_gb'       => (float)($storage['used_gb'] ?? 0),
                    'used'          => (float)($storage['used_gb'] ?? 0),
                    'free_gb'       => (float)($storage['free_gb'] ?? 0),
                    'available_gb'  => (float)($storage['free_gb'] ?? 0),
                    'used_pct'      => (float)($storage['used_pct'] ?? 0),
                    'files_count' => $vaultFilesCount,
                    'enabled'       => $enabled,
                ];
            } catch (\Throwable $e) {
                Log::warning('[SAT:index] Error calculando resumen de bóveda', [
                    'cuenta_id' => $cuentaId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // =========================
        // Datos principales
        // =========================
        $credList           = collect();
        $credMap            = [];
        $rfcOptions         = [];   // ✅ FIX para Blade
        $initialRows        = [];
        $cartIds            = [];
        $downloadsPage      = null;
        $downloadsTotalAll  = 0;

        if ($cuentaId !== '') {
            try {
                // limpia expirada
                try { $this->cleanupExpiredHistory($cuentaId, 5); } catch (\Throwable) {}

                $cartIds = $this->getCartIds($cuentaId);

                // =========================
                // Credenciales (RFCs)
                // =========================
                $credList = SatCredential::query()
                    ->where('cuenta_id', $cuentaId)
                    ->orderBy('rfc')
                    ->get();

                // Mapa RFC => alias
                foreach ($credList as $c) {
                    $rfc = strtoupper(trim((string) $c->rfc));
                    if ($rfc !== '') {
                        $credMap[$rfc] = (string) ($c->razon_social ?? $c->alias ?? '');
                    }
                }

                // ✅ FIX: RFC options para “Manual” (solo validados)
                $rfcOptions = $this->buildRfcOptionsForManual($credList);

                // =========================
                // Descargas (NO bóveda)
                // =========================
                $perPage = 20;

                $baseQuery = SatDownload::query()
                    ->where('cuenta_id', $cuentaId)
                    ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')  // ✅ FIX case-insensitive
                    ->orderByDesc('created_at');

                $downloadsTotalAll = (int) $baseQuery->count();

                $downloadsPage = $baseQuery->paginate($perPage);

                $now = Carbon::now();

                // Filtra solicitudes “request/solicitud” por flags + tipo (defensivo)
                $collection = $downloadsPage->getCollection()
                    ->filter(function (SatDownload $d) {
                        $tipo = strtolower((string) data_get($d, 'tipo', ''));
                        $isRequest = (bool) data_get($d, 'is_request', false);
                        $esSolicitud = (bool) data_get($d, 'es_solicitud', false);

                        if ($isRequest || $esSolicitud) return false;
                        if (in_array($tipo, ['solicitud', 'request', 'peticion'], true)) return false;

                        return true;
                    })
                    ->values();

                $rowsH = $collection->map(function (SatDownload $d) use ($credMap, $cartIds, $now) {
                    return $this->transformDownloadRow($d, $credMap, $cartIds, $now);
                });

                // Sustituye collection paginada por rows transformadas
                $downloadsPage->setCollection($rowsH);

                $initialRows = $rowsH->values()->all();
            } catch (\Throwable $e) {
                Log::error('[SAT:index] Error cargando descargas/credenciales', [
                    'cuenta_id' => $cuentaId,
                    'error'     => $e->getMessage(),
                ]);

                // ✅ Mantén rfcOptions vacío (evita undefined en Blade si cae aquí)
                $rfcOptions = [];
            }
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

        'vault_quota_gb'     => (float) ($vaultSummary['quota_gb'] ?? 0.0),
        'vault_used_gb'      => (float) ($vaultSummary['used_gb'] ?? 0.0),
        'vault_used_pct'     => (float) ($vaultSummary['used_pct'] ?? 0.0),

        'cartIds'            => $cartIds,

        // ✅ FIX: requerido por el Blade “Descargas manuales”
        'rfcOptions'         => $rfcOptions ?? [],
    ]);

    }


    protected function transformDownloadRow(
    SatDownload $d,
    array $credMap,
    array $cartIds,
    Carbon $now
    ): array {
        $rfc   = strtoupper((string) ($d->rfc ?? ''));
        $alias = $credMap[$rfc] ?? (string) ($d->razon_social ?? $d->alias ?? '');

        $from = $d->date_from ?? $d->desde ?? null;
        $to   = $d->date_to   ?? $d->hasta ?? null;

        $fromStr = $from ? substr((string) $from, 0, 10) : null;
        $toStr   = $to   ? substr((string) $to,   0, 10) : null;

        $expRaw = $d->expires_at ?? $d->disponible_hasta ?? null;
        $exp    = null;

        try {
            if ($expRaw instanceof Carbon) $exp = $expRaw->copy();
            elseif ($expRaw) $exp = Carbon::parse((string) $expRaw);
        } catch (\Throwable) {
            $exp = null;
        }

        if (!$exp && $d->paid_at) {
            $exp = $d->paid_at instanceof Carbon
                ? $d->paid_at->copy()->addDays(15)
                : Carbon::parse((string) $d->paid_at)->addDays(15);
        }

        if (!$exp && $d->created_at) {
            $exp = $d->created_at instanceof Carbon
                ? $d->created_at->copy()->addHours(12)
                : Carbon::parse((string) $d->created_at)->addHours(12);
        }

        $secondsLeft = null;
        if ($exp) $secondsLeft = $now->diffInSeconds($exp, false);

        $estadoStr = (string) ($d->estado ?? $d->status ?? $d->sat_status ?? '');
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

        $tipoLow = strtolower((string) ($d->tipo ?? ''));
        $estadoLow = strtolower((string) $estadoStr);

        // ✅ canPay robusto: solo si está “listo” y NO es vault/boveda
        $canPay = !$pagado
        && !in_array($tipoLow, ['vault', 'boveda'], true)
        && in_array($estadoLow, ['ready', 'done', 'listo', 'completed', 'finalizado'], true);


        // ✅ Asegura métricas (peso, xml_count, costo, etc.)
        $this->hydrateDownloadMetrics($d);

        $xmlCount  = (int) ($d->xml_count ?? $d->total_xml ?? 0);
        $sizeGb    = (float) ($d->size_gb ?? 0);
        $sizeMb    = (float) ($d->size_mb ?? 0);
        $sizeBytes = (int) ($d->size_bytes ?? 0);
        $costo     = (float) ($d->costo ?? 0);

        $discount   = $d->discount_pct ?? $d->descuento_pct ?? null;
        $createdStr = $d->created_at ? $d->created_at->format('Y-m-d H:i:s') : null;

        $sizeLabel = (string) ($d->size_label ?? ($sizeMb > 0
            ? number_format($sizeMb, 2) . ' Mb'
            : 'Pendiente'
        ));

        $remainingLabel = '00:00:00';
        if ($secondsLeft !== null && $secondsLeft > 0) {
            $remainingLabel = gmdate('H:i:s', (int) floor($secondsLeft));
        }

        $inCart = in_array((string) $d->id, $cartIds, true);

        $pesoMb    = $sizeMb > 0 ? $sizeMb : (($sizeBytes > 0) ? ($sizeBytes / (1024 * 1024)) : 0.0);
        $pesoLabel = $pesoMb > 0 ? number_format($pesoMb, 2) . ' MB' : 'Pendiente';

        // ✅ Manual flag (columna o meta)
        $isManual = false;
        try {
            $meta = [];
            if (isset($d->meta)) {
                if (is_array($d->meta)) $meta = $d->meta;
                elseif (is_string($d->meta) && $d->meta !== '') {
                    $tmp = json_decode($d->meta, true);
                    if (is_array($tmp)) $meta = $tmp;
                }
            }

            $isManual =
                !empty($d->is_manual ?? null)
                || !empty($d->manual ?? null)
                || !empty($meta['is_manual'] ?? null)
                || !empty($meta['manual'] ?? null);
        } catch (\Throwable) {
            $isManual = false;
        }

        return [
            'id'           => (string) $d->id,
            'dlid'         => (string) $d->id,

            'rfc'          => $rfc,
            'razon'        => $alias,
            'razon_social' => $alias,
            'alias'        => $alias,
            'tipo'         => (string) ($d->tipo ?? ''),

            // ✅ NUEVO: para filtrar/etiquetar "Descargas manuales"
            'is_manual'    => $isManual,
            'manual'       => $isManual,

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
            'cost_usd'     => $costo, // legacy UI key
            'in_cart'      => $inCart,
            'created_at'   => $d->created_at ? $d->created_at->toIso8601String() : null,

            'peso_mb'      => (float) $pesoMb,
            'peso_label'   => (string) $pesoLabel,
        ];
    }


    /* ===================================================
     *  RFC: registrar / alias / eliminar
     * =================================================== */

    public function registerRfc(Request $request): JsonResponse|RedirectResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $isAjax = $this->isAjax($request);

        $data = $request->validate([
            'rfc'   => ['required', 'string', 'min:12', 'max:13'],
            'alias' => ['nullable', 'string', 'max:190'],
        ]);

        $rfc   = strtoupper(trim((string) $data['rfc']));
        $alias = isset($data['alias']) ? trim((string) $data['alias']) : null;

        try {
            if ($cuentaId === '') {
                $msg = 'No se pudo determinar la cuenta actual.';
                if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 403);
                return redirect()->route('cliente.sat.index')->with('error', $msg);
            }

            $cred = SatCredential::on('mysql_clientes')
                ->where('cuenta_id', $cuentaId)
                ->whereRaw('UPPER(rfc) = ?', [$rfc])
                ->first();

            if (!$cred) {
                $cred = new SatCredential();
                $cred->setConnection('mysql_clientes');
                $cred->cuenta_id = $cuentaId;
                $cred->rfc       = $rfc;
            } else {
                // Asegura conexión por si el modelo venía de otro lado
                $cred->setConnection('mysql_clientes');
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
            } catch (\Throwable) {}

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

    public function saveAlias(Request $request): JsonResponse|RedirectResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $isAjax = $this->isAjax($request);

        $data = $request->validate([
            'rfc'   => ['required', 'string', 'min:12', 'max:13'],
            'alias' => ['nullable', 'string', 'max:190'],
        ]);

        $rfc   = strtoupper(trim((string) $data['rfc']));
        $alias = trim((string) ($data['alias'] ?? ''));

        try {
            if ($cuentaId === '') {
                $msg = 'No se pudo determinar la cuenta actual.';
                if ($isAjax) return response()->json(['ok' => false, 'msg' => $msg, 'trace_id' => $trace], 403);
                return redirect()->route('cliente.sat.index')->with('error', $msg);
            }

            $cred = SatCredential::on('mysql_clientes')
                ->where('cuenta_id', $cuentaId)
                ->whereRaw('UPPER(rfc) = ?', [$rfc])
                ->first();

            if (!$cred) {
                $cred = new SatCredential();
                $cred->setConnection('mysql_clientes');
                $cred->cuenta_id = $cuentaId;
                $cred->rfc       = $rfc;
            } else {
                $cred->setConnection('mysql_clientes');
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
        $cuentaId = (string) $this->cuId();

        if (!$user || $cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Sesión expirada o cuenta inválida.', 'trace_id' => $trace], 401);
        }

        $data = $request->validate([
            'rfc' => ['required', 'string', 'min:12', 'max:13'],
        ]);

        $rfcUpper = strtoupper(trim((string) $data['rfc']));

        try {
            $cred = SatCredential::query()
                ->where('cuenta_id', $cuentaId)
                ->whereRaw('UPPER(rfc) = ?', [$rfcUpper])
                ->first();

            if (!$cred) {
                return response()->json(['ok' => false, 'msg' => 'RFC no encontrado en tu cuenta.', 'trace_id' => $trace], 404);
            }

            // intenta borrar archivos asociados si existen columnas
            try {
                $conn   = $cred->getConnectionName() ?? 'mysql_clientes';
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                $cerPath = $schema->hasColumn($table, 'cer_path') ? (string) ($cred->cer_path ?? '') : '';
                $keyPath = $schema->hasColumn($table, 'key_path') ? (string) ($cred->key_path ?? '') : '';

                $diskName = config('filesystems.disks.private')
                    ? 'private'
                    : (config('filesystems.disks.vault') ? 'vault' : config('filesystems.default', 'local'));

                foreach ([$cerPath, $keyPath] as $p0) {
                    $p0 = ltrim((string) $p0, '/');
                    if ($p0 === '') continue;
                    try {
                        if (Storage::disk($diskName)->exists($p0)) Storage::disk($diskName)->delete($p0);
                    } catch (\Throwable) {}
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
        $user = $this->cu();
        if (!$user) abort(401, 'Sesión expirada, vuelve a iniciar sesión.');

        $cuenta = $user->cuenta ?? null;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $cuentaId = (string) ($this->resolveCuentaIdFromUser($user) ?? '');
        if ($cuentaId === '') abort(403, 'No se pudo determinar la cuenta actual.');

        /** @var SatDownload $download */
        $download = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('id', $id)
            ->firstOrFail();

        $paid  = !empty($download->paid_at);
        $stRaw = (string) ($download->status ?? $download->estado ?? $download->sat_status ?? '');
        $st    = strtolower($stRaw);

        $ready = $paid || in_array($st, ['done', 'ready', 'paid', 'downloaded', 'listo', 'completed', 'finalizado'], true);

        if (!$ready) {
            abort(403, 'Este paquete aún no está listo para descarga.');
        }

        // marcar como descargado si hay columna status
        try {
            $tbl = (new SatDownload())->getTable();
            if (Schema::connection('mysql_clientes')->hasTable($tbl)
                && Schema::connection('mysql_clientes')->hasColumn($tbl, 'status')
            ) {
                if (strtolower((string) $download->status) !== 'downloaded') {
                    $download->status = 'downloaded';
                    $download->save();
                }
            }
        } catch (\Throwable) {}

        // Disk desde meta.zip_disk o sat_zip/default
        $disk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');

        $path = $download->zip_path
            ?? $download->path
            ?? $download->file_path
            ?? null;

        if ($path) $path = ltrim((string) $path, '/');

        // Local/demo
        if (app()->environment(['local', 'development', 'testing'])) {
            try {
                $demoRel = $this->zipHelper->ensureLocalDemoZipWithCfdis($download);
                if ($demoRel) {
                    $disk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
                    $path = ltrim($demoRel, '/');
                }
            } catch (\Throwable) {}
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
                    $path = ltrim((string) $realPath, '/');

                    if ($path !== '' && Storage::disk($disk)->exists($path)) {
                        $exists = true;

                        // Persistir (no bloqueante)
                        try {
                            $download->zip_path = $path;

                            $meta = is_array($download->meta) ? $download->meta : [];
                            $meta['zip_disk'] = (string) $disk;

                            $download->meta = $meta;
                            $download->save();
                        } catch (\Throwable) {}
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
                            $q->where('source_id', (string) $id)
                              ->orWhere('download_id', (string) $id);
                        })
                        ->orderByDesc('id')
                        ->first();

                    if ($vf) {
                        $vDisk = $vf->disk ?: (config('filesystems.disks.sat_vault') ? 'sat_vault' : 'local');
                        $vPath = ltrim((string) $vf->path, '/');

                        if ($vPath !== '' && Storage::disk($vDisk)->exists($vPath)) {
                            Log::info('[SAT:zip] Servido desde BÓVEDA (mapeo directo)', [
                                'cuenta_id'   => $cuentaId,
                                'download_id' => (string) $id,
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
                    'download_id' => (string) $id,
                    'err'         => $e->getMessage(),
                ]);
            }

            abort(404, 'Archivo ZIP no disponible.');
        }

        // Bóveda automática (no bloquea)
        try {
            if ($this->vaultIsActiveForAccount($cuenta)) {
                $this->moveZipToVault($download);
            }
        } catch (\Throwable $e) {
            Log::warning('[SAT:zip] Error moviendo ZIP a bóveda (no bloquea descarga)', [
                'download_id' => (string) $download->id,
                'error'       => $e->getMessage(),
            ]);
        }

        $fileName = $download->zip_name
            ?? $download->filename
            ?? ('sat_' . (string) $download->id . '.zip');

        return Storage::disk($disk)->download($path, $fileName);
    }


        /**
     * Determina si el plan de la cuenta es PRO-like.
     */
    private function isProPlanForCuenta($cuenta): bool
    {
        if (!$cuenta) return false;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $planRaw = (string) ($cuenta->plan_actual ?? $cuenta->plan ?? $cuenta->plan_name ?? 'FREE');
        $plan    = strtoupper(trim($planRaw));

        return in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);
    }

    /**
     * Cuenta meses inclusivos entre dos fechas (por mes calendario).
     * Ej: 2026-01-01 a 2026-01-31 => 1
     *     2026-01-15 a 2026-02-01 => 2
     */
    private function monthsSpanInclusive(Carbon $from, Carbon $to): int
    {
        $a = $from->copy()->startOfMonth();
        $b = $to->copy()->startOfMonth();

        $months = (($b->year - $a->year) * 12) + ($b->month - $a->month) + 1;
        return max(1, (int) $months);
    }

    /* ===================================================
     *   CREAR SOLICITUDES SAT
     * =================================================== */

    public function request(Request $request): JsonResponse
    {
        $user = $this->cu();
        $cuentaId = (string) $this->cuId();

        // ✅ SOT: cuentas_cliente
        $cuenta = $cuentaId !== '' ? $this->fetchCuentaCliente($cuentaId) : null;

        // fallback (solo respaldo)
        if (!$cuenta) {
            $tmp = $user?->cuenta ?? null;
            if (is_array($tmp)) $tmp = (object) $tmp;
            if (is_object($tmp)) $cuenta = $tmp;
        }

        if (!$user || !$cuenta || $cuentaId === '') {

            return response()->json(['ok' => false, 'msg' => 'No se encontró la cuenta del cliente.'], 422);
        }

        $data = $request->validate([
            'tipo'   => 'required|string|in:emitidos,recibidos,ambos',
            'from'   => 'required|date',
            'to'     => 'required|date|after_or_equal:from',
            'rfcs'   => 'required|array|min:1',
            'rfcs.*' => 'required|string|min:12|max:13',

            // ✅ bandera manual desde UI (descargas manuales)
            'manual' => 'nullable',
        ]);

        // Manual robusto
        $isManual = false;
        try {
            $v = $data['manual'] ?? null;
            if (is_bool($v)) {
                $isManual = $v;
            } elseif (is_numeric($v)) {
                $isManual = ((int) $v) === 1;
            } elseif (is_string($v)) {
                $vv = strtolower(trim($v));
                $isManual = in_array($vv, ['1', 'true', 'on', 'yes', 'si', 'sí'], true);
            }
        } catch (\Throwable) {
            $isManual = false;
        }

        $tipo = (string) $data['tipo'];
        $from = Carbon::parse((string) $data['from'])->startOfDay();
        $to   = Carbon::parse((string) $data['to'])->endOfDay();

        // ✅ REGLA NEGOCIO:
        // FREE solo 1 mes POR EJECUCIÓN, pero si es MANUAL (pagada / por ejecución), NO limitamos.
        $isProPlan = $this->isProPlanForCuenta($cuenta);

        if (!$isProPlan && !$isManual) {
            $months = $this->monthsSpanInclusive($from, $to);
            if ($months > 1) {
                return response()->json([
                    'ok'   => false,
                    'msg'  => 'En FREE sólo puedes solicitar hasta 1 mes por ejecución.',
                    'code' => 'FREE_MONTH_LIMIT',
                    'meta' => [
                        'months' => $months,
                        'from'   => $from->toDateString(),
                        'to'     => $to->toDateString(),
                    ],
                ], 422);
            }
        }

        $rfcs = array_values(array_unique(array_map(
            static fn($r) => strtoupper(trim((string) $r)),
            (array) $data['rfcs']
        )));

        $credList = SatCredential::query()
            ->where('cuenta_id', $cuentaId)
            ->whereIn(DB::raw('UPPER(rfc)'), $rfcs)
            ->get();

        // ✅ Mapa RFC => Credencial (para meta/alias, etc.)
        $credByRfc = $credList->keyBy(function ($c) {
            return strtoupper(trim((string) ($c->rfc ?? '')));
        });

        $validRfcs = $credList->filter(function ($c) {
            $estatusRaw = strtolower((string) ($c->estatus ?? ''));

            $okFlag =
                !empty($c->validado ?? null)
                || !empty($c->validated_at ?? null)
                || !empty($c->has_files ?? null)
                || !empty($c->has_csd ?? null)
                || !empty($c->cer_path ?? null)
                || !empty($c->key_path ?? null)
                || in_array($estatusRaw, ['ok', 'valido', 'válido', 'validado', 'valid'], true);

            return $okFlag;
        })->pluck('rfc')->map(fn($r) => strtoupper((string) $r))->unique()->values()->all();


        if (!count($validRfcs)) {
            return response()->json(['ok' => false, 'msg' => 'Debes seleccionar al menos un RFC validado (con CSD cargado).'], 422);
        }

        $tipos = $tipo === 'ambos' ? ['emitidos', 'recibidos'] : [$tipo];

        $created = [];

        $dlModel = new SatDownload();
        $table   = $dlModel->getTable();
        $schema  = Schema::connection($dlModel->getConnectionName() ?? 'mysql_clientes');

        // ✅ Detecta columnas opcionales para “manual”
        $hasIsManual = false;
        $hasMeta     = false;
        try {
            $hasIsManual = $schema->hasColumn($table, 'is_manual') || $schema->hasColumn($table, 'manual');
            $hasMeta     = $schema->hasColumn($table, 'meta');
        } catch (\Throwable) {
            $hasIsManual = false;
            $hasMeta     = false;
        }

        foreach ($validRfcs as $rfc) {
            foreach ($tipos as $tipoSat) {
                try {
                    $dl = new SatDownload();
                    $dl->setConnection('mysql_clientes');

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

                    if ($schema->hasColumn($table, 'user_id') && isset($user->id)) $dl->user_id = $user->id;

                    // ✅ Marca “manual”
                    if ($isManual) {
                        if ($hasIsManual) {
                            if ($schema->hasColumn($table, 'is_manual')) $dl->is_manual = 1;
                            if ($schema->hasColumn($table, 'manual'))    $dl->manual    = 1;
                        }

                        if ($hasMeta) {
                            $meta = [];

                            // ✅ toma meta desde la credencial del RFC (si existe)
                            try {
                                $credForMeta = $credByRfc->get(strtoupper($rfc));
                                $raw = $credForMeta?->meta ?? null;

                                if (is_array($raw)) {
                                    $meta = $raw;
                                } elseif (is_string($raw) && $raw !== '') {
                                    $tmp = json_decode($raw, true);
                                    if (is_array($tmp)) $meta = $tmp;
                                }
                            } catch (\Throwable) {
                                $meta = [];
                            }

                            $meta['is_manual'] = true;
                            $meta['manual']    = true;
                            $meta['source']    = 'manual_ui';

                            $dl->meta = $meta;
                        }

                    }

                    $dl->save();
                    $created[] = $dl;

                    // opcional: crear request en servicio si existe
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
                        'user_id'   => $user->id ?? null,
                        'rfc'       => $rfc,
                        'tipo'      => $tipoSat,
                        'manual'    => $isManual ? 1 : 0,
                        'msg'       => $e->getMessage(),
                    ]);
                }
            }
        }

        if (!count($created)) {
            return response()->json(['ok' => false, 'msg' => 'No se pudieron crear las solicitudes SAT. Revisa el log.'], 500);
        }

        return response()->json([
            'ok'     => true,
            'count'  => count($created),
            'manual' => $isManual ? 1 : 0,
        ]);
    }

    /**
     * Cancelar / eliminar una descarga SAT (borrado duro local + limpia carrito).
     */
    public function cancelDownload(Request $request): JsonResponse
    {
        $trace      = $this->trace();
        $downloadId = trim((string) ($request->input('download_id') ?? ''));
        $cuentaId   = (string) $this->cuId();

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

                // 1) carrito por sesión
                try {
                    $key = $this->cartKeyForCuenta($cuentaId);
                    $ids = session($key, []);
                    $ids = array_values(array_filter(array_map('strval', (array) $ids)));
                    $ids = array_values(array_diff($ids, [(string) $downloadId]));
                    session([$key => $ids]);
                } catch (\Throwable) {}

                // 2) carrito por tabla si existe
                try {
                    if (Schema::connection($conn)->hasTable('sat_cart_items')) {
                        DB::connection($conn)->table('sat_cart_items')
                            ->where('cuenta_id', $cuentaId)
                            ->where('download_id', $downloadId)
                            ->delete();
                    }
                } catch (\Throwable) {}

                // 3) borrar ZIP si existe en sat_zip
                try {
                    $zipPath = (string) ($dl->zip_path ?? '');
                    if ($zipPath !== '') {
                        $zipPath = ltrim($zipPath, '/');
                        $disk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
                        if (Storage::disk($disk)->exists($zipPath)) {
                            Storage::disk($disk)->delete($zipPath);
                        }
                    }
                } catch (\Throwable) {}

                // 4) borrar registro
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

    /* ===================================================
     * VERIFICAR ESTADO (AJAX)
     * =================================================== */

    public function verify(Request $request): JsonResponse
    {
        $user   = $this->cu();
        $cuenta = $user?->cuenta ?? null;

        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        if (!$user || !$cuenta) {
            return response()->json(['ok' => false, 'msg' => 'No se encontró la cuenta del cliente.'], 422);
        }

        $cuentaId = (string) $this->cuId();
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
            ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
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

        // Local: simula DONE + llena métricas
        if (app()->environment(['local', 'development', 'testing'])) {
            foreach ($rows as $dl) {
                if ($hasEstado)  $dl->estado     = 'DONE';
                if ($hasSatStat) $dl->sat_status = 'DONE';
                if ($hasStatus)  $dl->status     = 'DONE';

                if ($hasReadyAt && empty($dl->ready_at)) $dl->ready_at = now();
                if ($hasExpiresAt && empty($dl->expires_at)) $dl->expires_at = now()->addHours(12);

                if ($hasCosto) {
                    $xmlCount = (int) ($dl->xml_count ?? $dl->total_xml ?? 0);
                    if ($xmlCount > 0 && ((float) ($dl->costo ?? 0) <= 0)) {
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

                $newStatus = strtoupper((string) $statusInfo['status']);

                if (in_array($newStatus, $errorStatuses, true)) {
                    if ($hasEstado)   $dl->estado     = 'ERROR';
                    if ($hasSatStat)  $dl->sat_status = 'ERROR';
                    if ($hasStatus)   $dl->status     = 'ERROR';
                    if ($hasErrorMsg) $dl->error_msg  = (string) ($statusInfo['message'] ?? 'Error en descarga SAT.');

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

                // listo
                if (in_array($newStatus, $readyStatuses, true) || !in_array($newStatus, $errorStatuses, true)) {
                    if ($hasEstado)  $dl->estado     = 'DONE';
                    if ($hasSatStat) $dl->sat_status = 'DONE';
                    if ($hasStatus)  $dl->status     = 'DONE';

                    if ($hasReadyAt && empty($dl->ready_at)) $dl->ready_at = now();
                    if ($hasExpiresAt && empty($dl->expires_at)) $dl->expires_at = now()->addHours(12);

                    $xmlInfo = $statusInfo['xml_count'] ?? $statusInfo['total_xml'] ?? $statusInfo['num_xml'] ?? null;
                    if ($hasXmlCount && $xmlInfo !== null) {
                        if ($schema->hasColumn($table, 'xml_count')) $dl->xml_count = (int) $xmlInfo;
                        if ($schema->hasColumn($table, 'total_xml')) $dl->total_xml = (int) $xmlInfo;
                    }

                    $sizeInfo = $statusInfo['size_bytes'] ?? $statusInfo['bytes'] ?? $statusInfo['zip_bytes'] ?? $statusInfo['peso_bytes'] ?? null;
                    if ($hasSizeBytes && $sizeInfo !== null) {
                        if ($schema->hasColumn($table, 'size_bytes'))      $dl->size_bytes = (int) $sizeInfo;
                        elseif ($schema->hasColumn($table, 'bytes'))       $dl->bytes      = (int) $sizeInfo;
                        elseif ($schema->hasColumn($table, 'zip_bytes'))   $dl->zip_bytes  = (int) $sizeInfo;
                    }

                    if ($hasCosto) {
                        $c = $statusInfo['costo'] ?? $statusInfo['cost_mxn'] ?? $statusInfo['precio'] ?? null;
                        if ($c !== null && (float) $c > 0) $dl->costo = (float) $c;
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
                    'download_id' => (string) ($dl->id ?? ''),
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

   public function storeCredentials(Request $request): JsonResponse|RedirectResponse
    {
        $trace    = $this->trace();
        $cuentaId = $this->cuId();

        $isAjax = $this->isAjax($request);

        // ✅ "solo_guardar" explícito desde UI
        $soloGuardar = (string) $request->input('solo_guardar') === '1';

        if ($cuentaId !== '') {
            try { $this->cleanupExpiredHistory((string) $cuentaId, 200); } catch (\Throwable) {}
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

        $password = (string) ($data['key_password'] ?? $data['pwd'] ?? '');
        $alias    = $data['alias'] ?? null;

        // =========================================================
        // ✅ AUTO-SOLO_GUARDAR (blindaje backend)
        // - Si faltan archivos => NO se puede validar.
        // - Si falta password => NO intentes validar (evita loop UI).
        // =========================================================
        if (!$cer || !$key) {
            $soloGuardar = true;
        }

        // Si pretende validar pero no hay password, fuerza solo_guardar.
        // (Si tu negocio permite password vacío, elimina este bloque)
        if (!$soloGuardar && trim($password) === '') {
            $soloGuardar = true;
        }

        try {
            $rfcUpper = strtoupper((string) $data['rfc']);

            // Upsert SIEMPRE: guarda lo que venga (aunque sea solo RFC/alias)
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

            // =========================================================
            // ✅ VALIDACIÓN (solo cuando aplica)
            // =========================================================
            $didValidate     = false;
            $okValidacion    = false;  // <- IMPORTANTÍSIMO: default false
            $validationError = null;

            if (!$soloGuardar) {
                $didValidate = true;

                try {
                    $okValidacion = (bool) $this->service->validateCredentials($cred);
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

            // =========================================================
            // Persistir flags si columnas existen
            // =========================================================
            try {
                $conn   = $cred->getConnectionName() ?? 'mysql_clientes';
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                $hasEstatus     = $schema->hasColumn($table, 'estatus');
                $hasValidado    = $schema->hasColumn($table, 'validado');
                $hasValidatedAt = $schema->hasColumn($table, 'validated_at');
                $hasCsdError    = $schema->hasColumn($table, 'csd_error');
                $hasErrorMsg    = $schema->hasColumn($table, 'error_msg');

                if ($soloGuardar || !$didValidate) {
                    // Guardado sin validar
                    if ($hasEstatus)     $cred->estatus      = 'pending';
                    if ($hasValidado)    $cred->validado     = 0;
                    if ($hasValidatedAt) $cred->validated_at = null;

                    if ($hasCsdError) $cred->csd_error = null;
                    if ($hasErrorMsg) $cred->error_msg = null;
                } else {
                    // Validación ejecutada
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

            // =========================================================
            // Mensajes
            // =========================================================
            if ($soloGuardar || !$didValidate) {
                $msg = 'Credenciales guardadas correctamente.';
                $ok  = true;
            } else {
                if ($okValidacion) {
                    $msg = 'CSD validado correctamente.';
                    $ok  = true;
                } else {
                    $msg = 'Archivos CSD guardados, pero la validación no fue exitosa. Verifica el .cer, .key y la contraseña.';
                    $ok  = true; // guardado sí ocurrió
                }
            }

            if ($isAjax) {
                return response()->json([
                    'ok'               => $ok,

                    // ✅ IMPORTANTE: "validated" solo true si realmente se validó y pasó
                    'validated'        => ($didValidate && $okValidacion) ? true : false,
                    'did_validate'     => $didValidate ? 1 : 0,

                    'validation_error' => $validationError,
                    'trace_id'         => $trace,
                    'rfc'              => $cred->rfc,
                    'alias'            => $cred->razon_social,
                    'solo_guardar'     => $soloGuardar ? 1 : 0,
                    'msg'              => $msg,
                ], 200);
            }

            if ($soloGuardar || ($didValidate && $okValidacion)) {
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
     *  LIMPIEZA EXPIRADAS + CAP HISTORIAL
     * =================================================== */

    private function cleanupExpiredHistory(string $cuentaId, int $keep = 0): void
    {
        $now = Carbon::now();

        // 1) eliminar expiradas
        $expired = SatDownload::where('cuenta_id', $cuentaId)
            ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
            ->where(function ($q) use ($now) {
                $q->where(function ($q1) use ($now) {
                    $q1->whereNotNull('expires_at')
                       ->where('expires_at', '<=', $now);
                })->orWhere(function ($q2) use ($now) {
                    $q2->whereNull('expires_at')
                       ->whereNotNull('created_at')
                       ->where('created_at', '<=', $now->copy()->subHours(12));
                });
            })
            ->get();

        foreach ($expired as $d) {
            try {
                if (!empty($d->zip_path)) {
                    $relative = ltrim((string) $d->zip_path, '/');
                    $diskName = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
                    if ($relative !== '' && Storage::disk($diskName)->exists($relative)) {
                        Storage::disk($diskName)->delete($relative);
                    }
                }
            } catch (\Throwable) {}

            try { $d->delete(); } catch (\Throwable) {}
        }

        // 2) cap historial (mantén los más recientes)
        if ($keep > 0) {
            try {
                $idsToKeep = SatDownload::query()
                    ->where('cuenta_id', $cuentaId)
                    ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
                    ->orderByDesc('created_at')
                    ->limit($keep)
                    ->pluck('id')
                    ->map(fn($v) => (string) $v)
                    ->all();

                if (count($idsToKeep) > 0) {
                    $old = SatDownload::query()
                        ->where('cuenta_id', $cuentaId)
                        ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
                        ->whereNotIn('id', $idsToKeep)
                        ->get();

                    foreach ($old as $d) {
                        try {
                            if (!empty($d->zip_path)) {
                                $relative = ltrim((string) $d->zip_path, '/');
                                $diskName = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
                                if ($relative !== '' && Storage::disk($diskName)->exists($relative)) {
                                    Storage::disk($diskName)->delete($relative);
                                }
                            }
                        } catch (\Throwable) {}

                        try { $d->delete(); } catch (\Throwable) {}
                    }
                }
            } catch (\Throwable) {}
        }
    }

    /* ===================================================
     *  CARRITO (SESSION + OPTIONAL DB sat_cart_items)
     * =================================================== */

    private function cartKeyForCuenta(string $cuentaId): string
    {
        return 'sat_cart_' . $cuentaId;
    }

    private function getCartIds(string $cuentaId): array
    {
        $key = $this->cartKeyForCuenta($cuentaId);
        $ids = session($key, []);
        return array_values(array_filter(array_unique(array_map('strval', (array) $ids))));
    }

    private function putCartIdsForCuenta(string $cuentaId, array $ids): void
    {
        $key = $this->cartKeyForCuenta($cuentaId);
        session([$key => array_values(array_unique(array_map('strval', $ids)))]);
    }

    /**
     * ✅ Agregar 1 descarga al carrito.
     * Payload: {download_id}
     */
    public function cartAdd(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = (string) $this->cuId();

        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 401);
        }

        $data = $request->validate([
            'download_id' => ['required', 'string', 'max:64'],
        ]);

        $id = (string) $data['download_id'];

        $dl = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->where('id', $id)
            ->first();

        if (!$dl) {
            return response()->json(['ok' => false, 'msg' => 'Descarga no encontrada.', 'trace_id' => $trace], 404);
        }

        // no permite vault/boveda
        if (in_array(strtolower((string) ($dl->tipo ?? '')), ['vault', 'boveda'], true)) {
            return response()->json(['ok' => false, 'msg' => 'No puedes agregar un item de bóveda al carrito.', 'trace_id' => $trace], 422);
        }

        $ids = $this->getCartIds($cuentaId);
        if (!in_array($id, $ids, true)) $ids[] = $id;
        $this->putCartIdsForCuenta($cuentaId, $ids);

        // opcional persistencia en DB
        try {
            if (Schema::connection('mysql_clientes')->hasTable('sat_cart_items')) {
                DB::connection('mysql_clientes')->table('sat_cart_items')->updateOrInsert([
                    'cuenta_id'   => $cuentaId,
                    'download_id' => $id,
                ], [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable) {}

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'count'    => count($ids),
            'ids'      => $ids,
        ]);
    }

    /**
     * ✅ Quitar 1 descarga del carrito.
     * Payload: {download_id}
     */
    public function cartRemove(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = (string) $this->cuId();

        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 401);
        }

        $data = $request->validate([
            'download_id' => ['required', 'string', 'max:64'],
        ]);

        $id  = (string) $data['download_id'];
        $ids = $this->getCartIds($cuentaId);
        $ids = array_values(array_diff($ids, [$id]));
        $this->putCartIdsForCuenta($cuentaId, $ids);

        try {
            if (Schema::connection('mysql_clientes')->hasTable('sat_cart_items')) {
                DB::connection('mysql_clientes')->table('sat_cart_items')
                    ->where('cuenta_id', $cuentaId)
                    ->where('download_id', $id)
                    ->delete();
            }
        } catch (\Throwable) {}

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'count'    => count($ids),
            'ids'      => $ids,
        ]);
    }

    /**
     * ✅ Agregar múltiples ids.
     * Payload: {ids: []} o {download_ids: []}
     */
    public function cartBulkAdd(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = (string) $this->cuId();

        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 401);
        }

        $data = $request->validate([
            'ids'           => ['nullable', 'array'],
            'ids.*'         => ['string', 'max:64'],
            'download_ids'  => ['nullable', 'array'],
            'download_ids.*'=> ['string', 'max:64'],
        ]);

        $idsIn = array_values(array_filter(array_map('strval', (array) ($data['ids'] ?? $data['download_ids'] ?? []))));
        $idsIn = array_values(array_unique($idsIn));

        if (!count($idsIn)) {
            return response()->json(['ok' => false, 'msg' => 'ids requerido.', 'trace_id' => $trace], 422);
        }

        // valida que existan y no sean vault/boveda
        $validIds = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->whereIn('id', $idsIn)
            ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
            ->pluck('id')
            ->map(fn($v) => (string) $v)
            ->all();

        $cur = $this->getCartIds($cuentaId);
        $merged = array_values(array_unique(array_merge($cur, $validIds)));
        $this->putCartIdsForCuenta($cuentaId, $merged);

        try {
            if (Schema::connection('mysql_clientes')->hasTable('sat_cart_items') && count($validIds)) {
                $rows = [];
                foreach ($validIds as $id) {
                    $rows[] = [
                        'cuenta_id'   => $cuentaId,
                        'download_id' => $id,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }
                // inserta evitando duplicados (MySQL)
                foreach ($rows as $r) {
                    DB::connection('mysql_clientes')->table('sat_cart_items')->updateOrInsert([
                        'cuenta_id'   => $r['cuenta_id'],
                        'download_id' => $r['download_id'],
                    ], [
                        'updated_at' => $r['updated_at'],
                        'created_at' => $r['created_at'],
                    ]);
                }
            }
        } catch (\Throwable) {}

        return response()->json([
            'ok'         => true,
            'trace_id'   => $trace,
            'added'      => count($validIds),
            'requested'  => count($idsIn),
            'count'      => count($merged),
            'ids'        => $merged,
        ]);
    }

    public function cartClear(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = (string) $this->cuId();

        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 401);
        }

        $this->putCartIdsForCuenta($cuentaId, []);

        try {
            if (Schema::connection('mysql_clientes')->hasTable('sat_cart_items')) {
                DB::connection('mysql_clientes')->table('sat_cart_items')->where('cuenta_id', $cuentaId)->delete();
            }
        } catch (\Throwable) {}

        return response()->json(['ok' => true, 'trace_id' => $trace, 'count' => 0, 'ids' => []]);
    }

    public function cartGet(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $cuentaId = (string) $this->cuId();

        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 401);
        }

        $ids = $this->getCartIds($cuentaId);

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'count'    => count($ids),
            'ids'      => $ids,
        ]);
    }

    /* ===================================================
     *  CHARTS (placeholder)
     * =================================================== */

    public function charts(Request $request): JsonResponse
    {
        return response()->json([
            'ok'     => true,
            'rows'   => [],
            'totals' => [
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

        $zipDisk = (string) ($meta['zip_disk'] ?? '');
        if ($zipDisk === '') {
            $zipDisk = config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
        }

        $zipPath = ltrim((string) ($download->zip_path ?? ''), '/');
        if ($zipPath !== '') {
            try {
                if (Storage::disk($zipDisk)->exists($zipPath)) return [$zipDisk, $zipPath];
            } catch (\Throwable) {}
        }

        $cuentaId  = (string) ($download->cuenta_id ?? '');
        $packageId = (string) ($download->package_id ?? $download->packageId ?? $download->package ?? '');

        if ($cuentaId !== '' && $packageId !== '') {
            $candPkg = "sat/packages/{$cuentaId}/pkg_{$packageId}.zip";
            try {
                if (Storage::disk($zipDisk)->exists($candPkg)) return [$zipDisk, $candPkg];
            } catch (\Throwable) {}
        }

        $id = (string) ($download->id ?? '');
        if ($cuentaId !== '' && $id !== '') {
            $candLegacy = "sat/packages/{$cuentaId}/pkg_{$id}.zip";
            try {
                if (Storage::disk($zipDisk)->exists($candLegacy)) return [$zipDisk, $candLegacy];
            } catch (\Throwable) {}
        }

        try {
            $needle = $packageId !== '' ? $packageId : $id;
            $found  = $this->findZipByListing($zipDisk, $cuentaId, $needle);
            if ($found !== '') return [$zipDisk, ltrim($found, '/')];
        } catch (\Throwable) {}

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
                    $f = ltrim((string) $f, '/');
                    if ($f === '') continue;

                    $low = strtolower($f);
                    if (!str_ends_with($low, '.zip')) continue;

                    if (stripos($f, $downloadId) !== false) return $f;
                }
            } catch (\Throwable) {}
        }

        return '';
    }

    /* ==========================================================
     *  BÓVEDA: BACKFILL MASIVO
     * ========================================================== */

    public function vaultBackfill(Request $request): JsonResponse
    {
        $trace = $this->trace();

        $user = $this->cu();
        if (!$user) {
            return response()->json(['ok' => false, 'msg' => 'Sesión expirada.', 'trace_id' => $trace], 401);
        }

        $cuenta = $user->cuenta ?? null;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;

        $cuentaId = (string) ($this->resolveCuentaIdFromUser($user) ?? '');
        if ($cuentaId === '') {
            return response()->json(['ok' => false, 'msg' => 'Cuenta inválida.', 'trace_id' => $trace], 422);
        }

        if (!$this->vaultIsActiveForAccount($cuenta)) {
            return response()->json([
                'ok'       => false,
                'msg'      => 'La bóveda no está activa para esta cuenta.',
                'trace_id' => $trace,
            ], 422);
        }

        $onlyTipo = strtolower((string) $request->get('tipo', ''));

        $q = SatDownload::query()
            ->where('cuenta_id', $cuentaId)
            ->whereRaw('LOWER(COALESCE(status,"")) IN ("done","paid","downloaded")')
            ->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")')
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
                            ->where('source_id', (string) $dl->id)
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
                        ->where('source_id', (string) $dl->id)
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
                    'download_id' => (string) ($dl->id ?? ''),
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
            $cuentaId = (string) ($download->cuenta_id ?? '');
            if ($cuentaId === '') return;

            $tipo = strtolower((string) ($download->tipo ?? ''));
            if (!in_array($tipo, ['emitidos', 'recibidos', 'ambos'], true)) return;

            [$srcDisk, $srcPath] = $this->resolveDownloadZipLocation($download);
            if ($srcDisk === '' || $srcPath === '') {
                Log::warning('[SAT:Vault] No se pudo resolver ZIP para mover a bóveda', [
                    'download_id' => (string) ($download->id ?? ''),
                    'cuenta_id'   => $cuentaId,
                    'zip_path'    => (string) ($download->zip_path ?? ''),
                ]);
                return;
            }

            if (Schema::connection($conn)->hasTable('sat_vault_files')) {
                $exists = DB::connection($conn)->table('sat_vault_files')
                    ->where('cuenta_id', $cuentaId)
                    ->where('source', 'sat_download')
                    ->where('source_id', (string) $download->id)
                    ->first();

                if ($exists && !empty($exists->path)) {
                    try {
                        $d = (string) ($exists->disk ?? (config('filesystems.disks.sat_vault') ? 'sat_vault' : 'local'));
                        $p = ltrim((string) $exists->path, '/');
                        if ($p !== '' && Storage::disk($d)->exists($p)) return;
                    } catch (\Throwable) {
                        // re-copiar
                    }
                }
            }

            $cuentaObj  = $this->fetchCuentaObjForVault($cuentaId);
            $storage    = $this->buildVaultStorageSummary($cuentaId, $cuentaObj);
            $quotaBytes = (int) ($storage['quota_bytes'] ?? 0);
            $usedBytes  = (int) ($storage['used_bytes'] ?? 0);
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

            $rfc = strtoupper((string) ($download->rfc ?? ''));
            if ($rfc === '') $rfc = 'XAXX010101000';

            $now     = Carbon::now();
            $baseDir = "vault/{$cuentaId}/{$rfc}";

            $destPath = $baseDir . '/SAT_' . $rfc . '_' . $now->format('Ymd_His') . '_' . (string) $download->id . '.zip';

            try {
                if (!Storage::disk($vaultDisk)->exists($baseDir)) {
                    Storage::disk($vaultDisk)->makeDirectory($baseDir);
                }
            } catch (\Throwable) {}

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
                'source_id'  => (string) $download->id,
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
                ->where('source_id', (string) $download->id)
                ->first();

            if ($existing) {
                DB::connection($conn)->table('sat_vault_files')->where('id', $existing->id)->update($data);
            } else {
                $data['created_at'] = Carbon::now()->toDateTimeString();
                DB::connection($conn)->table('sat_vault_files')->insert($data);
            }

            Log::info('[SAT:Vault] ZIP copiado a bóveda', [
                'cuenta_id'   => $cuentaId,
                'download_id' => (string) $download->id,
                'src_disk'    => $srcDisk,
                'src_path'    => $srcPath,
                'vault_disk'  => $vaultDisk,
                'vault_path'  => $destPath,
                'bytes'       => $bytes,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SAT:Vault] Error moviendo ZIP a bóveda', [
                'download_id' => (string) ($download->id ?? ''),
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /* ==========================================================
     *  ✅ COTIZADOR SAT: CALCULAR (JSON) + PDF
     * ========================================================== */

    public function quoteCalc(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = (string) $this->cuId();

        if (!$user || $cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Sesión expirada o cuenta inválida.',
                'trace_id' => $trace,
            ], 401);
        }

        $data = $request->validate([
            'xml_count'     => ['required', 'integer', 'min:1', 'max:50000000'],
            'discount_code' => ['nullable', 'string', 'max:64'],
            'iva'           => ['nullable', 'integer', 'min:0', 'max:16'],
            'unit_cost'     => ['nullable'], // IGNORADO
        ]);

        $xmlCount     = (int) $data['xml_count'];
        $discountCode = trim((string) ($data['discount_code'] ?? ''));
        $ivaRate      = (int) ($data['iva'] ?? 16);

        $cuenta = null;
        try {
            if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                $cuenta = DB::connection('mysql_clientes')->table('cuentas_cliente')->where('id', $cuentaId)->first();
            }
        } catch (\Throwable) {
            $cuenta = null;
        }

        $planRaw = '';
        if ($cuenta && isset($cuenta->plan_actual)) {
            $planRaw = (string) $cuenta->plan_actual;
        } else {
            try {
                $c = $user->cuenta ?? null;
                if (is_array($c)) $c = (object) $c;
                if (is_object($c) && isset($c->plan_actual)) $planRaw = (string) $c->plan_actual;
            } catch (\Throwable) {}
        }

        $plan = strtoupper(trim($planRaw)) ?: 'FREE';

        $empresa = null;
        if ($cuenta) {
            foreach (['razon_social', 'razon', 'empresa', 'nombre', 'name'] as $col) {
                if (isset($cuenta->{$col}) && trim((string) $cuenta->{$col}) !== '') {
                    $empresa = trim((string) $cuenta->{$col});
                    break;
                }
            }
        }
        if (!$empresa) {
            try {
                $c = $user->cuenta ?? null;
                if (is_array($c)) $c = (object) $c;
                if (is_object($c)) {
                    foreach (['razon_social', 'razon', 'empresa', 'nombre', 'name'] as $col) {
                        if (isset($c->{$col}) && trim((string) $c->{$col}) !== '') {
                            $empresa = trim((string) $c->{$col});
                            break;
                        }
                    }
                }
            } catch (\Throwable) {}
        }

        $folio      = 'SATQ-' . strtoupper(Str::ulid()->toBase32());
        $generated  = now();
        $validUntil = $generated->copy()->addDays(7);

        $base = round((float) $this->computeDownloadCostPhp($xmlCount), 2);

        $discountPct    = (int) $this->resolveDiscountPctForQuote($cuentaId, $discountCode);
        $discountAmount = round($base * ($discountPct / 100), 2);
        $subtotal       = max(0, round($base - $discountAmount, 2));

        $ivaAmount = ($ivaRate > 0) ? round($subtotal * ($ivaRate / 100), 2) : 0.0;
        $total     = round($subtotal + $ivaAmount, 2);

        $note = $this->pricingNoteForXml($xmlCount);
        if ($discountPct > 0) $note .= ' Descuento aplicado: ' . $discountPct . '%.';

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'data'     => [
                'folio'            => $folio,
                'generated_at'     => $generated->toIso8601String(),
                'valid_until'      => $validUntil->toDateString(),

                'plan'             => $plan,
                'cuenta_id'        => $cuentaId,
                'empresa'          => $empresa ?: '—',

                'xml_count'        => $xmlCount,
                'base'             => $base,

                'discount_code'    => $discountCode !== '' ? $discountCode : null,
                'discount_pct'     => $discountPct,
                'discount_amount'  => $discountAmount,

                'subtotal'         => $subtotal,
                'iva_rate'         => $ivaRate,
                'iva_amount'       => $ivaAmount,
                'total'            => $total,

                'note'             => $note,
            ],
        ]);
    }

        /* ==========================================================
     *  ✅ CALCULADORA RÁPIDA (GUÍAS RÁPIDAS): CALCULAR (JSON) + PDF
     *  - NO solicita RFC
     *  - Debe usar lista de precios en Admin (mysql_admin) cuando exista
     * ========================================================== */

    public function quickCalc(Request $request): JsonResponse
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = (string) $this->cuId();

        if (!$user || $cuentaId === '') {
            return response()->json([
                'ok'       => false,
                'msg'      => 'Sesión expirada o cuenta inválida.',
                'trace_id' => $trace,
            ], 401);
        }

        $data = $request->validate([
                    // Compatibilidad UI
                    'xml_count'           => ['nullable', 'integer', 'min:1', 'max:50000000'],
                    'xml_count_estimated' => ['nullable', 'integer', 'min:1', 'max:50000000'],

                    'discount_code' => ['nullable', 'string', 'max:64'],

                    // IVA puede venir como 16 o 0.16
                    'iva'      => ['nullable'],
                    'iva_rate' => ['nullable'],
                ]);

                $xmlCount = (int) (
            $data['xml_count']
            ?? $data['xml_count_estimated']
            ?? 0
        );

        if ($xmlCount <= 0) {
            return response()->json([
                'ok' => false,
                'msg' => 'xml_count requerido.',
                'trace_id' => $trace,
            ], 422);
        }

        $discountCode = trim((string) ($data['discount_code'] ?? ''));

        // IVA flexible: 16 o 0.16
        $ivaRate = 16;
        $ivaRaw  = $data['iva'] ?? $data['iva_rate'] ?? 16;

        if (is_numeric($ivaRaw)) {
            $v = (float) $ivaRaw;
            $ivaRate = ($v > 1) ? (int) round($v) : (int) round($v * 100);
        }

        $ivaRate = max(0, min(16, $ivaRate));


        // Info de cuenta (solo para encabezado / PDF)
        $cuenta = null;
        try {
            if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                $cuenta = DB::connection('mysql_clientes')->table('cuentas_cliente')->where('id', $cuentaId)->first();
            }
        } catch (\Throwable) {
            $cuenta = null;
        }

        $planRaw = '';
        if ($cuenta && isset($cuenta->plan_actual)) {
            $planRaw = (string) $cuenta->plan_actual;
        } else {
            try {
                $c = $user->cuenta ?? null;
                if (is_array($c)) $c = (object) $c;
                if (is_object($c) && isset($c->plan_actual)) $planRaw = (string) $c->plan_actual;
            } catch (\Throwable) {}
        }
        $plan = strtoupper(trim($planRaw)) ?: 'FREE';

        $empresa = null;
        if ($cuenta) {
            foreach (['razon_social', 'razon', 'empresa', 'nombre', 'name'] as $col) {
                if (isset($cuenta->{$col}) && trim((string) $cuenta->{$col}) !== '') {
                    $empresa = trim((string) $cuenta->{$col});
                    break;
                }
            }
        }
        if (!$empresa) {
            try {
                $c = $user->cuenta ?? null;
                if (is_array($c)) $c = (object) $c;
                if (is_object($c)) {
                    foreach (['razon_social', 'razon', 'empresa', 'nombre', 'name'] as $col) {
                        if (isset($c->{$col}) && trim((string) $c->{$col}) !== '') {
                            $empresa = trim((string) $c->{$col});
                            break;
                        }
                    }
                }
            } catch (\Throwable) {}
        }

        $folio      = 'SATQ-' . strtoupper(Str::ulid()->toBase32());
        $generated  = now();
        $validUntil = $generated->copy()->addDays(7);

        // ✅ BASE desde Admin (si existe), con fallback seguro
        [$base, $priceNote, $priceSource] = $this->resolveAdminPriceForXml($xmlCount);
        $base = round((float) $base, 2);

        $discountPct    = (int) $this->resolveDiscountPctForQuote($cuentaId, $discountCode);
        $discountAmount = round($base * ($discountPct / 100), 2);
        $subtotal       = max(0, round($base - $discountAmount, 2));

        $ivaAmount = ($ivaRate > 0) ? round($subtotal * ($ivaRate / 100), 2) : 0.0;
        $total     = round($subtotal + $ivaAmount, 2);

        $note = $priceNote !== '' ? $priceNote : $this->pricingNoteForXml($xmlCount);
        if ($discountPct > 0) $note .= ' Descuento aplicado: ' . $discountPct . '%.';
        if ($priceSource !== '') $note .= ' Fuente: ' . $priceSource . '.';

        return response()->json([
            'ok'       => true,
            'trace_id' => $trace,
            'data'     => [
                'mode'             => 'quick',
                'folio'            => $folio,
                'generated_at'     => $generated->toIso8601String(),
                'valid_until'      => $validUntil->toDateString(),

                'plan'             => $plan,
                'cuenta_id'        => $cuentaId,
                'empresa'          => $empresa ?: '—',

                'xml_count'        => $xmlCount,
                'base'             => $base,

                'discount_code'    => $discountCode !== '' ? $discountCode : null,
                'discount_pct'     => $discountPct,
                'discount_amount'  => $discountAmount,

                'subtotal'         => $subtotal,
                'iva_rate'         => $ivaRate,
                'iva_amount'       => $ivaAmount,
                'total'            => $total,

                'note'             => $note,
            ],
        ]);
    }

    // =========================
    // ✅ PATCH: quickPdf robusto
    // - acepta xml_count o xml_count_estimated
    // - acepta iva como 16 o 0.16 (igual que quickCalc)
    // - vista defensiva antes de DomPDF
    // =========================
    public function quickPdf(Request $request)
    {
        $trace    = $this->trace();
        $user     = $this->cu();
        $cuentaId = (string) $this->cuId();

        if (!$user || $cuentaId === '') {
            abort(401, 'Sesión expirada o cuenta inválida.');
        }

        $data = $request->validate([
            // Compatibilidad UI (igual que quickCalc)
            'xml_count'           => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'xml_count_estimated' => ['nullable', 'integer', 'min:1', 'max:50000000'],

            'discount_code' => ['nullable', 'string', 'max:64'],

            // IVA puede venir como 16 o 0.16
            'iva'      => ['nullable'],
            'iva_rate' => ['nullable'],
        ]);

        $xmlCount = (int) (
            $data['xml_count']
            ?? $data['xml_count_estimated']
            ?? 0
        );

        if ($xmlCount <= 0) {
            abort(422, 'xml_count requerido.');
        }

        $discountCode = trim((string) ($data['discount_code'] ?? ''));

        // IVA flexible: 16 o 0.16
        $ivaRate = 16;
        $ivaRaw  = $data['iva'] ?? $data['iva_rate'] ?? 16;

        if (is_numeric($ivaRaw)) {
            $v = (float) $ivaRaw;
            $ivaRate = ($v > 1) ? (int) round($v) : (int) round($v * 100);
        }
        $ivaRate = max(0, min(16, $ivaRate));

        $cuenta = null;
        try {
            if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                $cuenta = DB::connection('mysql_clientes')->table('cuentas_cliente')->where('id', $cuentaId)->first();
            }
        } catch (\Throwable) {
            $cuenta = null;
        }

        $planRaw = '';
        if ($cuenta && isset($cuenta->plan_actual)) {
            $planRaw = (string) $cuenta->plan_actual;
        } else {
            try {
                $c = $user->cuenta ?? null;
                if (is_array($c)) $c = (object) $c;
                if (is_object($c) && isset($c->plan_actual)) $planRaw = (string) $c->plan_actual;
            } catch (\Throwable) {}
        }
        $plan = strtoupper(trim($planRaw)) ?: 'FREE';

        $empresa = null;
        if ($cuenta) {
            foreach (['razon_social', 'razon', 'empresa', 'nombre', 'name'] as $col) {
                if (isset($cuenta->{$col}) && trim((string) $cuenta->{$col}) !== '') {
                    $empresa = trim((string) $cuenta->{$col});
                    break;
                }
            }
        }
        if (!$empresa) {
            try {
                $c = $user->cuenta ?? null;
                if (is_array($c)) $c = (object) $c;
                if (is_object($c)) {
                    foreach (['razon_social', 'razon', 'empresa', 'nombre', 'name'] as $col) {
                        if (isset($c->{$col}) && trim((string) $c->{$col}) !== '') {
                            $empresa = trim((string) $c->{$col});
                            break;
                        }
                    }
                }
            } catch (\Throwable) {}
        }

        $folio      = 'SATQ-' . strtoupper(Str::ulid()->toBase32());
        $generated  = now();
        $validUntil = $generated->copy()->addDays(7);

        // ✅ BASE desde Admin (si existe), con fallback seguro
        [$base, $priceNote, $priceSource] = $this->resolveAdminPriceForXml($xmlCount);
        $base = round((float) $base, 2);

        $discountPct    = (int) $this->resolveDiscountPctForQuote($cuentaId, $discountCode);
        $discountAmount = round($base * ($discountPct / 100), 2);
        $subtotal       = max(0, round($base - $discountAmount, 2));

        $ivaAmount = ($ivaRate > 0) ? round($subtotal * ($ivaRate / 100), 2) : 0.0;
        $total     = round($subtotal + $ivaAmount, 2);

        $note = $priceNote !== '' ? $priceNote : $this->pricingNoteForXml($xmlCount);
        if ($discountPct > 0) $note .= ' Descuento aplicado: ' . $discountPct . '%.';
        if ($priceSource !== '') $note .= ' Fuente: ' . $priceSource . '.';

        $payload = [
            'trace_id'        => $trace,
            'mode'            => 'quick',

            'folio'           => $folio,
            'generated_at'    => $generated,
            'valid_until'     => $validUntil,

            'plan'            => $plan,
            'cuenta_id'       => $cuentaId,
            'empresa'         => $empresa ?: '—',

            'xml_count'       => $xmlCount,
            'base'            => $base,

            'discount_code'   => $discountCode !== '' ? $discountCode : null,
            'discount_pct'    => (float) $discountPct,
            'discount_amount' => $discountAmount,

            'subtotal'        => $subtotal,
            'iva_rate'        => $ivaRate,
            'iva_amount'      => $ivaAmount,
            'total'           => $total,

            'note'            => $note,
        ];

        // Vista defensiva (evita errores silenciosos)
        if (!View::exists('cliente.sat.pdf.quote')) {
            return response(
                'No existe la vista PDF: resources/views/cliente/sat/pdf/quote.blade.php (cliente.sat.pdf.quote).',
                501
            );
        }

        try {
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cliente.sat.pdf.quote', $payload)
                    ->setPaper('letter', 'portrait');

                $file = 'cotizacion_sat_rapida_' . $cuentaId . '_' . $generated->format('Ymd_His') . '.pdf';
                return $pdf->download($file);
            }
        } catch (\Throwable $e) {
            Log::error('[SAT:quickPdf] DomPDF error', [
                'trace_id' => $trace,
                'err'      => $e->getMessage(),
            ]);
        }

        return response(
            'No está disponible el generador PDF (DomPDF). Instala/activa barryvdh/laravel-dompdf.',
            501
        );
    }


        /**
     * ✅ Precio desde Admin (mysql_admin) por rangos / catálogo.
     * Retorna: [base_mxn, note, source]
     *
     * Estrategia:
     * - Detecta tabla de precios en mysql_admin (varios nombres posibles)
     * - Detecta columnas min/max y unit/flat
     * - Selecciona el rango donde cae xmlCount
     * - Si no encuentra nada: fallback a computeDownloadCostPhp()
     */
    private function resolveAdminPriceForXml(int $xmlCount): array
    {
        $n = max(0, (int) $xmlCount);
        if ($n <= 0) return [0.0, 'Sin documentos.', 'fallback'];

        $conn = 'mysql_admin';

        // Candidatos de tabla (defensivo)
        $tables = [
            'sat_download_price_ranges',
            'sat_download_prices',
            'sat_price_ranges',
            'sat_prices',
            'sat_price_catalog',
            'sat_cfdi_price_ranges',
        ];

        try {
            $schema = Schema::connection($conn);

            $tableFound = null;
            foreach ($tables as $t) {
                if ($schema->hasTable($t)) { $tableFound = $t; break; }
            }

            if (!$tableFound) {
                $base = (float) $this->computeDownloadCostPhp($n);
                return [$base, '', 'fallback'];
            }

            $cols = $schema->getColumnListing($tableFound);

            $pickCol = static function (array $candidates, array $cols): ?string {
                foreach ($candidates as $c) {
                    if (in_array($c, $cols, true)) return $c;
                }
                return null;
            };

            // Rangos
            $colMin = $pickCol(['min_xml','min','min_docs','min_count','desde','from'], $cols);
            $colMax = $pickCol(['max_xml','max','max_docs','max_count','hasta','to'], $cols);

            // Precio fijo vs unitario
            $colFlat = $pickCol(['price','price_mxn','base_mxn','amount','amount_mxn','flat_price','flat_mxn','total','total_mxn'], $cols);
            $colUnit = $pickCol(['unit_price','unit_price_mxn','unit_mxn','per_xml','per_doc','rate','rate_mxn'], $cols);

            // Nota / etiqueta
            $colNote = $pickCol(['note','label','descripcion','description','name'], $cols);

            // Estatus/activo
            $colActive = $pickCol(['is_active','active','enabled','estatus','status'], $cols);
            $colSort   = $pickCol(['sort','priority','orden','order'], $cols);

            // Query base
            $q = DB::connection($conn)->table($tableFound);

            // Filtra activo si existe
            if ($colActive) {
                // Acepta 1/0, true/false, 'active', etc.
                $q->where(function ($w) use ($colActive) {
                    $w->where($colActive, 1)
                    ->orWhere($colActive, true)
                    ->orWhereRaw('LOWER(COALESCE('.$colActive.', "")) IN ("1","true","active","activo","enabled","on")');
                });
            }

            // Orden preferente
            if ($colSort) {
                $q->orderBy($colSort, 'asc');
            }
            if ($colMin) {
                $q->orderBy($colMin, 'asc');
            }

            $rows = $q->get();

            $best = null;

            foreach ($rows as $row) {
                $minOk = true;
                $maxOk = true;

                $minVal = null;
                $maxVal = null;

                if ($colMin && isset($row->{$colMin}) && is_numeric($row->{$colMin})) {
                    $minVal = (int) $row->{$colMin};
                    $minOk  = $n >= $minVal;
                }
                if ($colMax && isset($row->{$colMax}) && is_numeric($row->{$colMax})) {
                    $maxVal = (int) $row->{$colMax};
                    $maxOk  = $n <= $maxVal;
                }

                if (!$minOk || !$maxOk) continue;

                // Toma el primer match (por orden) como mejor
                $best = $row;
                break;
            }

            if (!$best) {
                // Si no hubo match por rangos, fallback seguro
                $base = (float) $this->computeDownloadCostPhp($n);
                return [$base, '', 'fallback'];
            }

            $flat = 0.0;
            if ($colFlat && isset($best->{$colFlat}) && is_numeric($best->{$colFlat})) {
                $flat = (float) $best->{$colFlat};
            }

            $unit = 0.0;
            if ($colUnit && isset($best->{$colUnit}) && is_numeric($best->{$colUnit})) {
                $unit = (float) $best->{$colUnit};
            }

            $base = 0.0;

            // Preferencia: flat si existe, si no unit*n, si no fallback
            if ($flat > 0) {
                $base = $flat;
            } elseif ($unit > 0) {
                $base = $unit * $n;
            } else {
                $base = (float) $this->computeDownloadCostPhp($n);
            }

            $note = '';
            if ($colNote && isset($best->{$colNote}) && trim((string) $best->{$colNote}) !== '') {
                $note = trim((string) $best->{$colNote});
            }

            // Construye una nota mínima si no hay
            if ($note === '') {
                $minTxt = null;
                $maxTxt = null;

                if ($colMin && isset($best->{$colMin}) && is_numeric($best->{$colMin})) $minTxt = (int) $best->{$colMin};
                if ($colMax && isset($best->{$colMax}) && is_numeric($best->{$colMax})) $maxTxt = (int) $best->{$colMax};

                if ($minTxt !== null && $maxTxt !== null) $note = "Precio Admin ({$minTxt}-{$maxTxt} XML)";
                elseif ($minTxt !== null) $note = "Precio Admin (>= {$minTxt} XML)";
                elseif ($maxTxt !== null) $note = "Precio Admin (<= {$maxTxt} XML)";
                else $note = 'Precio Admin';
            }

            return [$base, $note, $tableFound];
        } catch (\Throwable $e) {
            Log::warning('[SAT:resolveAdminPriceForXml] Fallback por excepción', [
                'xml_count' => $n,
                'err'       => $e->getMessage(),
            ]);

            $base = (float) $this->computeDownloadCostPhp($n);
            return [$base, '', 'fallback'];
        }
    }

        /**
     * Wrapper de compatibilidad (SOT)
     * Algunos flujos llaman resolveDiscountPctForQuote()
     */
    private function resolveDiscountPctForQuote(string $cuentaId, ?string $code): float
    {
        return (float) $this->resolveDiscountPctFromCode($cuentaId, $code);
    }

    private function resolveDiscountPctFromCode(string $cuentaId, ?string $code): float
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') return 0.0;

        $conn = 'mysql_clientes';

        // 1) cuentas_cliente
        try {
            if (Schema::connection($conn)->hasTable('cuentas_cliente')) {
                $cols = Schema::connection($conn)->getColumnListing('cuentas_cliente');

                $codeCols = array_values(array_filter([
                    in_array('discount_code', $cols, true) ? 'discount_code' : null,
                    in_array('codigo_socio',  $cols, true) ? 'codigo_socio'  : null,
                    in_array('codigo_cliente',$cols, true) ? 'codigo_cliente': null,
                    in_array('coupon_code',   $cols, true) ? 'coupon_code'   : null,
                ]));

                $pctCols = array_values(array_filter([
                    in_array('discount_pct',   $cols, true) ? 'discount_pct'   : null,
                    in_array('descuento_pct',  $cols, true) ? 'descuento_pct'  : null,
                    in_array('discount_rate',  $cols, true) ? 'discount_rate'  : null,
                ]));

                if (count($codeCols) && count($pctCols)) {
                    $row = DB::connection($conn)->table('cuentas_cliente')
                        ->where('id', $cuentaId)
                        ->first();

                    if ($row) {
                        foreach ($codeCols as $cc) {
                            $stored = strtoupper(trim((string) ($row->{$cc} ?? '')));
                            if ($stored !== '' && $stored === $code) {
                                foreach ($pctCols as $pc) {
                                    $pct = (float) ($row->{$pc} ?? 0);
                                    if ($pct > 0) return max(0.0, min(100.0, $pct));
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable) {}

        // 2) discount_codes
        try {
            if (Schema::connection($conn)->hasTable('discount_codes')) {
                $cols = Schema::connection($conn)->getColumnListing('discount_codes');

                $codeCol = in_array('code', $cols, true) ? 'code' : null;
                $pctCol  = in_array('pct',  $cols, true) ? 'pct'  : (in_array('discount_pct', $cols, true) ? 'discount_pct' : null);
                $actCol  = in_array('active', $cols, true) ? 'active' : null;
                $valCol  = in_array('valid_until', $cols, true) ? 'valid_until' : null;

                if ($codeCol && $pctCol) {
                    $q = DB::connection($conn)->table('discount_codes')
                        ->whereRaw('UPPER(' . $codeCol . ') = ?', [$code]);

                    if ($actCol) $q->where($actCol, 1);
                    if ($valCol) $q->where(function ($w) use ($valCol) {
                        $w->whereNull($valCol)->orWhere($valCol, '>=', now());
                    });

                    $row = $q->first();
                    if ($row) {
                        $pct = (float) ($row->{$pctCol} ?? 0);
                        if ($pct > 0) return max(0.0, min(100.0, $pct));
                    }
                }
            }
        } catch (\Throwable) {}

        // 3) config
        try {
            $map = (array) config('services.sat.discount_codes', []);
            foreach ($map as $k => $v) {
                if (strtoupper(trim((string) $k)) === $code) {
                    $pct = (float) $v;
                    if ($pct > 0) return max(0.0, min(100.0, $pct));
                }
            }
        } catch (\Throwable) {}

        return 0.0;
    }

    private function pricingNoteForXml(int $xmlCount): string
    {
        $n = max(0, $xmlCount);

        if ($n <= 0) return 'Sin documentos.';
        if ($n <= 5000) return 'Tarifa: $1 MXN por documento (1–5,000).';
        if ($n <= 25000) return 'Tarifa: $0.08 MXN por documento (5,001–25,000).';
        if ($n <= 40000) return 'Tarifa: $0.05 MXN por documento (25,001–40,000).';
        if ($n <= 100000) return 'Tarifa: $0.03 MXN por documento (40,001–100,000).';
        if ($n <= 500000) return 'Tarifa plana: $12,500 MXN (100,001–500,000).';
        if ($n <= 1000000) return 'Tarifa plana: $18,500 MXN (500,001–1,000,000).';
        if ($n <= 2000000) return 'Tarifa plana: $25,000 MXN (1,000,001–2,000,000).';
        if ($n <= 3000000) return 'Tarifa plana: $31,000 MXN (2,000,001–3,000,000).';

        return 'Tarifa: $0.01 MXN por documento (> 3,000,000).';
    }

        // ============================================================
    // ✅ REGISTRO EXTERNO / INVITE
    // ============================================================
    // POST  /cliente/sat/external/invite   -> cliente.sat.external.invite
    // GET   /cliente/sat/external/register -> cliente.sat.external.register (signed)
    //
    // Flujo:
    // - Cliente invita por correo a un emisor externo.
    // - Se genera link firmado temporal (7 días).
    // - Se envía por correo el link.
    // - La pantalla signed puede ser un view si existe, si no, responde HTML simple.
    // ============================================================

    public function externalInvite(Request $request): JsonResponse
    {
        $email = trim((string) $request->input('email', ''));
        $note  = trim((string) $request->input('note', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Correo inválido.',
            ], 422);
        }

        // Link firmado temporal (7 días)
        // Incluimos email como param para precargar/identificar.
        $expiresAt = now()->addDays(7);

        try {
            // Contexto de cuenta (obligatorio para que "Mis RFCs" lo vea)
            $user   = $this->cu();
            $cuenta = $user?->cuenta ?? null;
            if (is_array($cuenta)) $cuenta = (object) $cuenta;

            $cuentaId = (string) ($this->resolveCuentaIdFromUser($user) ?? '');
            if ($cuentaId === '') {
                return response()->json([
                    'ok'  => false,
                    'msg' => 'No se pudo determinar la cuenta que invita.',
                ], 422);
            }

            // Token simple para correlación (no es secreto; la seguridad real la da la firma)
            $inviteId = (string) Str::ulid();

            // Link firmado temporal (7 días) con cuenta_id + email + inv
            $expiresAt = now()->addDays(7);

            try {
                $signedUrl = URL::temporarySignedRoute(
                    'cliente.sat.external.register',
                    $expiresAt,
                    [
                        'email'     => $email,
                        'cuenta_id' => $cuentaId,
                        'inv'       => $inviteId,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('[SAT][externalInvite] signedRoute error', [
                    'err' => $e->getMessage(),
                ]);

                return response()->json([
                    'ok'  => false,
                    'msg' => 'No se pudo generar el link de invitación. Revisa rutas/URL.',
                ], 500);
            }

        } catch (\Throwable $e) {
            Log::error('[SAT][externalInvite] signedRoute error', [
                'err' => $e->getMessage(),
            ]);

            return response()->json([
                'ok'  => false,
                'msg' => 'No se pudo generar el link de invitación. Revisa rutas/URL.',
            ], 500);
        }

        // Datos de contexto (opcional) - SOT Cliente
        $user   = $this->cu();
        $cuenta = $user?->cuenta ?? null;
        if (is_array($cuenta)) $cuenta = (object) $cuenta;


        $accountName = (string) ($cuenta?->razon_social ?? $cuenta?->nombre ?? $user?->name ?? 'Pactopia360');
        $appName     = (string) (config('app.name') ?: 'Pactopia360');
        $subject     = $appName . ' · Invitación para registrar RFC/CSD (SAT)';

        // Mensaje simple (sin Mailable)
        $lines = [];
        $lines[] = "Hola,";
        $lines[] = "";
        $lines[] = "Se te invitó desde {$accountName} para registrar tu RFC y (opcional) tu e.firma (CSD) en Pactopia360.";
        $lines[] = "";
        $lines[] = "Abre este enlace para continuar (válido hasta " . $expiresAt->format('Y-m-d H:i') . "):";
        $lines[] = $signedUrl;
        $lines[] = "";
        if ($note !== '') {
            $lines[] = "Nota:";
            $lines[] = $note;
            $lines[] = "";
        }
        $lines[] = "Si no esperabas este correo, ignóralo.";

        try {
            Mail::raw(implode("\n", $lines), function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('[SAT][externalInvite] mail error', [
                'email' => $email,
                'err'   => $e->getMessage(),
            ]);

            return response()->json([
                'ok'  => false,
                'msg' => 'Se generó el link, pero falló el envío de correo. Revisa configuración MAIL.',
                'url' => $signedUrl, // útil para debug en local
            ], 500);
        }

        return response()->json([
            'ok'   => true,
            'msg'  => 'Invitación enviada.',
            'url'  => $signedUrl, // útil para debug
            'exp'  => $expiresAt->toIso8601String(),
        ]);
    }

    public function externalRegister(Request $request)
    {
        if (!$request->hasValidSignature()) {
            return response('Firma inválida o expirada.', 403);
        }

        $email    = trim((string) $request->query('email', ''));
        $cuentaId = trim((string) $request->query('cuenta_id', ''));
        $inv      = trim((string) $request->query('inv', ''));

        if ($email === '' || $cuentaId === '') {
            return response('Parámetros incompletos.', 422);
        }

        // Si existe un view, úsalo
        if (View::exists('cliente.sat.external.register')) {
            return view('cliente.sat.external.register', [
                'email'    => $email,
                'cuentaId' => $cuentaId,
                'inv'      => $inv,
            ]);
        }

        // Fallback HTML simple con formulario funcional
        $safeEmail    = e($email);
        $safeCuentaId = e($cuentaId);
        $safeInv      = e($inv);

        // Endpoint POST (también firmado). Debes crear la ruta 'cliente.sat.external.store'
        // usando URL::temporarySignedRoute con los mismos params para que firme el POST.
        $postUrl = '';
        try {
            $postUrl = URL::temporarySignedRoute(
                'cliente.sat.external.store',
                now()->addDays(7),
                [
                    'email'     => $email,
                    'cuenta_id' => $cuentaId,
                    'inv'       => $inv,
                ]
            );
        } catch (\Throwable) {
            $postUrl = '';
        }

        $safePostUrl = e($postUrl);
        $csrf        = e(csrf_token());

        $html = <<<HTML

    <!doctype html>
    <html lang="es">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pactopia360 · Registro externo</title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:#0b1220; color:#e7eefc; margin:0; padding:24px;}
        .card{max-width:760px; margin:0 auto; background:#101a33; border:1px solid rgba(255,255,255,.08); border-radius:16px; padding:18px;}
        h1{font-size:18px; margin:0 0 6px;}
        p{opacity:.9; line-height:1.45}
        .mut{opacity:.7; font-size:13px}
        label{display:block; margin-top:10px; font-size:13px; opacity:.85}
        input{width:100%; padding:10px 12px; border-radius:12px; border:1px solid rgba(255,255,255,.10); background:rgba(255,255,255,.04); color:#e7eefc; outline:none}
        .row{display:grid; grid-template-columns:1fr; gap:10px}
        .btn{margin-top:14px; padding:10px 12px; border-radius:12px; border:0; background:#3b82f6; color:white; font-weight:600; cursor:pointer}
        .warn{margin-top:10px; background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.25); padding:10px; border-radius:12px}
        code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
    </style>
    </head>
    <body>
    <div class="card">
        <h1>Registro externo · RFC</h1>
        <p class="mut">Este RFC se registrará dentro de la cuenta que te invitó y aparecerá en “Mis RFCs”.</p>

        <div class="warn">
        <div class="mut">Correo invitado:</div>
        <div><code>{$safeEmail}</code></div>
        <div class="mut" style="margin-top:6px">Cuenta destino:</div>
        <div><code>{$safeCuentaId}</code></div>
        </div>

        <form method="POST" action="{$safePostUrl}">
        <input type="hidden" name="_token" value="{$csrf}">

        <label>RFC</label>
        <input name="rfc" required minlength="12" maxlength="13" placeholder="XAXX010101000">

        <label>Alias / Razón social (opcional)</label>
        <input name="alias" maxlength="190" placeholder="Mi empresa S.A. de C.V.">

        <button class="btn" type="submit">Registrar RFC</button>

        <p class="mut" style="margin-top:10px">
            Nota: en esta versión solo registra RFC. Si quieres subir CSD (.cer/.key) aquí mismo, lo agregamos en el siguiente patch.
        </p>
        </form>
    </div>
    </body>
    </html>
    HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

public function externalStore(Request $request)
{
    // ======================================================
    // 1) Signed URL hard-check (sin redirects)
    // ======================================================
    if (!method_exists($request, 'hasValidSignature') || !$request->hasValidSignature()) {
        return $request->wantsJson()
            ? response()->json(['ok' => false, 'msg' => 'Firma inválida o expirada.'], 403)
            : response('Firma inválida o expirada.', 403);
    }

    // ======================================================
    // 2) Query params (SOT) + cuenta_id variants
    // ======================================================
    $email = trim((string) $request->query('email', ''));

    $cuentaId = null;
    foreach (['cuenta_id', 'cuenta', 'account_id', 'account'] as $k) {
        $v = $request->query($k, null);
        if (is_scalar($v) && trim((string) $v) !== '') {
            $cuentaId = trim((string) $v);
            break;
        }
    }

    $inv = trim((string) $request->query('inv', ''));

    if ($email === '' || !$cuentaId) {
        return $request->wantsJson()
            ? response()->json(['ok' => false, 'msg' => 'Parámetros incompletos.'], 422)
            : response('Parámetros incompletos.', 422);
    }

    // Normaliza cuenta_id numérica (evita "00012" y similares)
    $cuentaIdNorm = ctype_digit($cuentaId) ? (string) ((int) $cuentaId) : $cuentaId;

    // ======================================================
    // 3) Validación BODY (multipart/form-data) según UI
    // ======================================================
    $data = $request->validate([
        'rfc'          => ['required', 'string', 'min:12', 'max:13'],
        'alias'        => ['nullable', 'string', 'max:190'],  // UI: alias (razón social)
        'razon_social' => ['nullable', 'string', 'max:190'],  // compat
        'cer'          => ['required', 'file', 'max:5120'],   // 5MB
        'key'          => ['required', 'file', 'max:5120'],   // 5MB
        'key_password' => ['nullable', 'string', 'max:120'],
        'pwd'          => ['nullable', 'string', 'max:120'],  // compat
        'note'         => ['nullable', 'string', 'max:500'],
        'confirm'      => ['accepted'], // checkbox
    ], [
        'confirm.accepted' => 'Debes confirmar la autorización para registrar este RFC.',
    ]);

    $rfc = strtoupper(trim((string) $data['rfc']));

    // RFC básico (SAT)
    if (!preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc)) {
        return $request->wantsJson()
            ? response()->json(['ok' => false, 'msg' => 'RFC inválido.'], 422)
            : response('RFC inválido.', 422);
    }

    $alias = trim((string) ($data['alias'] ?? $data['razon_social'] ?? ''));
    $note  = trim((string) ($data['note'] ?? ''));

    $cer = $request->file('cer');
    $key = $request->file('key');

    // Extensiones (sin depender de mime)
    if ($cer && strtolower((string) $cer->getClientOriginalExtension()) !== 'cer') {
        return $request->wantsJson()
            ? response()->json(['ok' => false, 'msg' => 'El archivo .cer no es válido.'], 422)
            : response('El archivo .cer no es válido.', 422);
    }
    if ($key && strtolower((string) $key->getClientOriginalExtension()) !== 'key') {
        return $request->wantsJson()
            ? response()->json(['ok' => false, 'msg' => 'El archivo .key no es válido.'], 422)
            : response('El archivo .key no es válido.', 422);
    }

    $password = (string) ($data['key_password'] ?? $data['pwd'] ?? '');

    // ======================================================
    // 4) Persistencia (mysql_clientes) reusando upsertCredentials
    //    - Guarda archivos en disk('public') (por compat con Admin/SAT)
    //    - Cifra password (key_password_enc si existe)
    //    - Meta merge seguro
    //    - Flags pending por defecto (externo)
    // ======================================================
    try {
        return DB::connection('mysql_clientes')->transaction(function () use (
            $request, $cuentaIdNorm, $email, $inv, $rfc, $alias, $note, $cer, $key, $password
        ) {
            // Reusa tu servicio (igual que storeCredentials)
            // Debe:
            // - almacenar cer/key en disk('public')
            // - setear cer_path/key_path en SatCredential
            // - (idealmente) gestionar reemplazo/limpieza anterior
            $cred = $this->service->upsertCredentials(
                $cuentaIdNorm,
                $rfc,
                $cer,
                $key,
                $password
            );

            // Razón social / alias
            if ($alias !== '') {
                // preferencia: razon_social
                try { $cred->razon_social = $alias; } catch (\Throwable) {}
                // fallback: alias si existiera
                try { if (property_exists($cred, 'alias')) $cred->alias = $alias; } catch (\Throwable) {}
            }

            // Ajustes de columnas opcionales (cache-safe)
            try {
                $conn   = $cred->getConnectionName() ?? 'mysql_clientes';
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                // Por externo: pending (validación posterior) si existe
                if ($schema->hasColumn($table, 'estatus'))      $cred->estatus      = 'pending';
                if ($schema->hasColumn($table, 'status'))       $cred->status       = 'pending';
                if ($schema->hasColumn($table, 'validado'))     $cred->validado     = 0;
                if ($schema->hasColumn($table, 'validated_at')) $cred->validated_at = null;

                // Password cifrada (si tu upsertCredentials no lo hace)
                // - key_password_enc si existe; si no, key_password
                if ($password !== '') {
                    $enc = null;
                    try { $enc = encrypt($password); } catch (\Throwable) { $enc = base64_encode($password); }

                    if ($schema->hasColumn($table, 'key_password_enc')) {
                        $cred->key_password_enc = $enc;
                        if ($schema->hasColumn($table, 'key_password')) {
                            $cred->key_password = null;
                        }
                    } elseif ($schema->hasColumn($table, 'key_password')) {
                        $cred->key_password = $enc;
                    }
                }

                // Meta merge
                if ($schema->hasColumn($table, 'meta')) {
                    $meta = [];
                    $current = $cred->meta ?? null;

                    if (is_array($current)) {
                        $meta = $current;
                    } elseif (is_string($current) && $current !== '') {
                        $tmp = json_decode($current, true);
                        if (is_array($tmp)) $meta = $tmp;
                    }

                    $meta['source']         = 'external_register';
                    $meta['external_email'] = $email;
                    $meta['invite_id']      = ($inv !== '' ? $inv : null);
                    $meta['note']           = ($note !== '' ? $note : null);
                    $meta['ip']             = $request->ip();
                    $meta['ua']             = (string) $request->userAgent();
                    $meta['updated_at']     = now()->toDateTimeString();

                    // evidencia de archivos (si tu modelo expone rutas)
                    try {
                        $meta['stored'] = [
                            'cer' => $cred->cer_path ?? null,
                            'key' => $cred->key_path ?? null,
                        ];
                    } catch (\Throwable) {}

                    $cred->meta = $meta;
                }
            } catch (\Throwable $ignore) {
                // no-op
            }

            $cred->save();

            if ($request->wantsJson()) {
                return response()->json([
                    'ok'  => true,
                    'msg' => 'RFC y CSD registrados. Ya puedes cerrar esta pestaña.',
                    'rfc' => $rfc,
                ], 200);
            }

            return response('RFC y CSD registrados. Ya puedes cerrar esta pestaña.', 200);
        });
    } catch (\Throwable $e) {
        Log::error('[SAT][externalStore] Error', [
            'cuenta_id' => $cuentaIdNorm,
            'email'     => $email,
            'inv'       => $inv,
            'rfc'       => $rfc ?? null,
            'err'       => $e->getMessage(),
        ]);

        return $request->wantsJson()
            ? response()->json(['ok' => false, 'msg' => 'No se pudo registrar.'], 500)
            : response('No se pudo registrar.', 500);
    }
}



public function dashboardStats(Request $request): JsonResponse
{
    // =========================================================
    // SAT Dashboard Stats (SOT · cuenta-scoped · defensivo)
    // - Usa helpers del controller (cu(), cuId(), resolveCuentaIdFromUser())
    // - Nunca global: TODO filtrado por cuenta_id
    // - Fechas: YYYY-MM-DD + límite de rango
    // - Breakdown por status/estado/sat_status (si existe)
    // - Serie diaria para gráfica
    // =========================================================

    $trace = $this->trace();

    // 1) Usuario autenticado (cliente portal)
    $user = $this->cu();

    if (!$user) {
        return response()->json([
            'ok'   => false,
            'msg'  => 'No autenticado.',
            'code' => 'UNAUTH',
            'trace_id' => $trace,
        ], 401);
    }

    // 2) Resolver cuenta_id robusto (SOT controller)
    $cuentaId = '';
    try { $cuentaId = (string) ($this->cuId() ?? ''); } catch (\Throwable) { $cuentaId = ''; }

    // Fallbacks adicionales (por si la sesión no trae cuenta_id aún)
    if ($cuentaId === '') {
        try {
            $cuentaId = (string) ($this->resolveCuentaIdFromUser($user) ?? '');
        } catch (\Throwable) {
            $cuentaId = '';
        }
    }

    // Fallback: relación cuenta
    if ($cuentaId === '') {
        try {
            $c = $user->cuenta ?? null;
            if (is_array($c)) $c = (object) $c;
            if (is_object($c)) {
                $cuentaId = (string) ($c->id ?? $c->cuenta_id ?? '');
            }
        } catch (\Throwable) {
            $cuentaId = '';
        }
    }

    // Fallback: sesión genérica
    if ($cuentaId === '') {
        foreach ([
            'cliente.cuenta_id',
            'cliente.account_id',
            'client.cuenta_id',
            'client.account_id',
            'cuenta_id',
            'account_id',
            'client_cuenta_id',
            'client_account_id',
        ] as $k) {
            try {
                $v = (string) session($k, '');
                if ($v !== '') { $cuentaId = $v; break; }
            } catch (\Throwable) {}
        }
    }

    // (Opcional) fallback por lookup (no revienta si no existe la tabla)
    if ($cuentaId === '') {
        try {
            $maybe = DB::connection('mysql_clientes')
                ->table('cuentas')
                ->where('user_id', (int) ($user->id ?? 0))
                ->value('id');

            if ($maybe) $cuentaId = (string) $maybe;
        } catch (\Throwable) {
            // ignore
        }
    }

    if ($cuentaId === '') {
        return response()->json([
            'ok'   => false,
            'msg'  => 'Cuenta no encontrada.',
            'code' => 'ACCOUNT_NOT_FOUND',
            'trace_id' => $trace,
            'debug' => [
                'user_id' => $user->id ?? null,
                'cuenta_id_field' => $user->cuenta_id ?? null,
                'has_cuenta_rel' => isset($user->cuenta),
                'session_cuenta_id' => session('cuenta_id'),
            ],
        ], 422);
    }

    // 3) Validación fechas (Y-m-d) + rango razonable
    $from = (string) $request->query('from', '');
    $to   = (string) $request->query('to', '');

    try {
        $toDate = $to !== ''
            ? Carbon::createFromFormat('Y-m-d', $to)->endOfDay()
            : now()->endOfDay();
    } catch (\Throwable) {
        return response()->json([
            'ok'   => false,
            'msg'  => 'Fecha "to" inválida. Usa YYYY-MM-DD.',
            'code' => 'INVALID_TO',
            'trace_id' => $trace,
        ], 422);
    }

    try {
        $fromDate = $from !== ''
            ? Carbon::createFromFormat('Y-m-d', $from)->startOfDay()
            : now()->subDays(30)->startOfDay();
    } catch (\Throwable) {
        return response()->json([
            'ok'   => false,
            'msg'  => 'Fecha "from" inválida. Usa YYYY-MM-DD.',
            'code' => 'INVALID_FROM',
            'trace_id' => $trace,
        ], 422);
    }

    if ($fromDate->gt($toDate)) {
        return response()->json([
            'ok'   => false,
            'msg'  => 'El rango es inválido: "from" > "to".',
            'code' => 'INVALID_RANGE',
            'trace_id' => $trace,
        ], 422);
    }

    // Limita rango para performance
    if ($fromDate->diffInDays($toDate) > 370) {
        return response()->json([
            'ok'   => false,
            'msg'  => 'Rango demasiado grande. Máximo 370 días.',
            'code' => 'RANGE_TOO_LARGE',
            'trace_id' => $trace,
        ], 422);
    }

    // 4) Conteos estrictamente por cuenta_id (nunca global)
    $conn   = 'mysql_clientes';
    $table  = 'sat_downloads';

    if (!Schema::connection($conn)->hasTable($table)) {
        return response()->json([
            'ok'   => false,
            'msg'  => 'Tabla sat_downloads no existe.',
            'code' => 'TABLE_NOT_FOUND',
            'trace_id' => $trace,
        ], 422);
    }

    $schema = Schema::connection($conn);

    $hasCuentaId = $schema->hasColumn($table, 'cuenta_id');
    if (!$hasCuentaId) {
        return response()->json([
            'ok'   => false,
            'msg'  => 'La tabla sat_downloads no tiene columna cuenta_id.',
            'code' => 'MISSING_CUENTA_ID',
            'trace_id' => $trace,
        ], 422);
    }

    $statusCols = array_values(array_filter([
        $schema->hasColumn($table, 'status')     ? 'status'     : null,
        $schema->hasColumn($table, 'estado')     ? 'estado'     : null,
        $schema->hasColumn($table, 'sat_status') ? 'sat_status' : null,
    ]));

    $createdAtCol = $schema->hasColumn($table, 'created_at') ? 'created_at' : null;
    if (!$createdAtCol) {
        return response()->json([
            'ok'   => false,
            'msg'  => 'La tabla sat_downloads no tiene created_at.',
            'code' => 'MISSING_CREATED_AT',
            'trace_id' => $trace,
        ], 422);
    }

    // Base query: cuenta + rango
    $q = DB::connection($conn)
        ->table($table)
        ->where('cuenta_id', $cuentaId)
        ->whereBetween($createdAtCol, [$fromDate, $toDate]);

    // Excluir bóveda si existe columna tipo (consistente con el resto del módulo)
    if ($schema->hasColumn($table, 'tipo')) {
        $q->whereRaw('LOWER(COALESCE(tipo,"")) NOT IN ("vault","boveda")');
    }

    $total = 0;
    try {
        $total = (int) (clone $q)->count();
    } catch (\Throwable $e) {
        return response()->json([
            'ok'   => false,
            'msg'  => 'No se pudieron calcular totales.',
            'code' => 'COUNT_FAILED',
            'trace_id' => $trace,
        ], 500);
    }

    // Breakdown por estado (mejor columna disponible)
    $byStatus = [];
    try {
        if (count($statusCols)) {
            $col = $statusCols[0]; // prioridad: status > estado > sat_status (por construcción)
            $byStatus = (clone $q)
                ->selectRaw('COALESCE(NULLIF(TRIM(' . $col . '),""),"unknown") as st, COUNT(*) as n')
                ->groupBy('st')
                ->pluck('n', 'st')
                ->toArray();
        }
    } catch (\Throwable) {
        $byStatus = [];
    }

    // Serie por día
    $series = [];
    try {
        $rows = (clone $q)
            ->selectRaw('DATE(' . $createdAtCol . ') as d, COUNT(*) as n')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        foreach ($rows as $r) {
            $series[] = [
                'date'  => (string) $r->d,
                'count' => (int) $r->n,
            ];
        }
    } catch (\Throwable) {
        $series = [];
    }

    return response()->json([
        'ok'       => true,
        'trace_id' => $trace,
        'data'     => [
            'cuenta_id' => (string) $cuentaId,
            'range'     => [
                'from' => $fromDate->toDateString(),
                'to'   => $toDate->toDateString(),
            ],
            'totals' => [
                'total'     => (int) $total,
                'by_status' => $byStatus,
            ],
            'series' => $series,
        ],
    ]);

    
    }
}