<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use App\Models\Cliente\SatDownload;
use App\Models\Cliente\VaultFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VaultController extends Controller
{
    private const CONN = 'mysql_clientes';

    /* ==========================================================
     *   VISTA PRINCIPAL
     * ========================================================== */

    public function index(Request $request)
    {
        [$user, $cuenta, $cuentaId, $clienteId] = $this->currentAccount();

        // ===============================
        // BLINDAJE: sin cuenta / sin cliente
        // ===============================
        if ($cuentaId === '' && !$clienteId) {
            $vaultZips = [];

            return view('cliente.sat.vault', [
                'credList'      => [],
                'bootData'      => [],
                'vaultFiles'    => [],
                'vaultFileRows' => [],
                'vaultZipRows'  => $vaultZips,
                'storage'      => [
                    'has_quota'     => false,
                    'quota_gb'      => 0.0,
                    'quota_bytes'   => 0,
                    'used_gb'       => 0.0,
                    'used_bytes'    => 0,
                    'available_gb'  => 0.0,
                    'used_pct'      => 0.0,
                    'available_pct' => 0.0,
                    'files_count'   => 0,
                ],
                'vault'        => [
                    'vaultZips'     => $vaultZips,
                    'quota_gb'      => 0.0,
                    'used_gb'       => 0.0,
                    'used'          => 0.0,
                    'free_gb'       => 0.0,
                    'available_gb'  => 0.0,
                    'enabled'       => false,
                ],
            ]);
        }

        // ===============================
        // Credenciales / RFCs
        // ===============================
        $credList = is_iterable($cuenta?->satCredenciales ?? null)
            ? $cuenta->satCredenciales
            : ($request->get('credList', []) ?? []);

        $credNorm = $this->normalizeCreds($credList);

        $accountRfcs = $this->getAccountRfcs($cuentaId, $clienteId, $credNorm);

        // ===============================
        // Resumen de bóveda (storage)
        // ===============================
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

        try {
            $storageSummary = $this->buildStorageSummary($cuentaId, $cuenta);

            $vaultSummary = [
                'has_quota'     => ((float)($storageSummary['quota_gb'] ?? 0)) > 0,
                'quota_gb'      => (float)($storageSummary['quota_gb'] ?? 0),
                'quota_bytes'   => (int)($storageSummary['quota_bytes'] ?? 0),
                'used_gb'       => (float)($storageSummary['used_gb'] ?? 0),
                'used_bytes'    => (int)($storageSummary['used_bytes'] ?? 0),
                'available_gb'  => (float)($storageSummary['free_gb'] ?? 0),
                'used_pct'      => (float)($storageSummary['used_pct'] ?? 0),
                'available_pct' => (float)($storageSummary['free_pct'] ?? 0),
                'files_count'   => 0,
            ];
        } catch (\Throwable $e) {
            Log::warning('[VAULT:index] Error calculando resumen de bóveda', [
                'cuenta_id' => $cuentaId,
                'error'     => $e->getMessage(),
            ]);
        }

        // ===============================
        // ZIPs guardados (sat_vault_files)
        // ===============================
        $vaultZipRows = $this->buildZipRowsFromVaultFiles($cuentaId);
        if ($vaultZipRows instanceof \Illuminate\Support\Collection) {
            $vaultZipRows = $vaultZipRows->values()->all();
        }

        // Ingesta ZIP -> CFDI (si aplica)
        $this->importZipsToVaultCfdisIfNeeded($cuentaId, $vaultZipRows);

        // ===============================
        // Boot data (CFDI)
        // ===============================
        $bootData   = [];
        $bootSource = 'none';

        if ($this->cfdiAvailable()) {
            $bootData   = $this->buildBootDataFromCfdis($cuentaId, $clienteId, $accountRfcs);
            $bootSource = !empty($bootData) ? 'sat_vault_cfdis' : 'none';
        }

        if (empty($bootData) && $this->isDemoMode($request)) {
            $bootData   = $this->fakeItems($credNorm);
            $bootSource = 'fake';
        }

        // ===============================
        // Vault files (tabla de archivos subidos)
        // ===============================
        $vaultFiles    = $this->buildBootDataFromVaultFiles($cuentaId);
        $vaultFileRows = $this->buildUploadedFileRows($cuentaId);

        Log::info('[VAULT:index] bootData desde origen', [
            'cuenta_id'  => $cuentaId,
            'cliente_id' => $clienteId,
            'count'      => is_countable($bootData) ? count($bootData) : 0,
            'source'     => $bootSource,
            'zips'       => is_countable($vaultZipRows) ? count($vaultZipRows) : 0,
        ]);

        // ===============================
        // Normalización final para vista (SIEMPRE consistente)
        // ===============================
        $quotaGb     = (float)($vaultSummary['quota_gb'] ?? 0);
        $usedGb      = (float)($vaultSummary['used_gb'] ?? 0);
        $availableGb = (float)($vaultSummary['available_gb'] ?? 0);

        $enabled = (bool)(((int)($vaultSummary['quota_bytes'] ?? 0)) > 0 || $quotaGb > 0);

        return view('cliente.sat.vault', [
            'credList'     => $credNorm,
            'bootData'     => $bootData,
            'vaultFiles'    => $vaultFiles,
            'vaultFileRows' => $vaultFileRows,
            'vaultZipRows'  => $vaultZipRows,
            'storage'      => $vaultSummary,

            'vault'        => [
                'vaultZips'     => $vaultZipRows,
                'quota_gb'      => $quotaGb,
                'used_gb'       => $usedGb,
                'used'          => $usedGb,
                'free_gb'       => $availableGb,
                'available_gb'  => $availableGb,
                'enabled'       => $enabled,
            ],
        ]);
    }

    /* ==========================================================
     *   QUICK (API ligera para bóveda)
     * ========================================================== */

    public function quick(Request $request): JsonResponse
    {
        if (!$request->expectsJson() && !$request->ajax()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Endpoint JSON. Usa /cliente/sat/vault para la vista.',
            ], 406);
        }

        [$user, $cuenta, $cuentaId, $clienteId] = $this->currentAccount();

        if (!$user || $cuentaId === '') {
            return response()->json([
                'ok'      => false,
                'message' => 'Cuenta inválida.',
            ], 422);
        }

        $credList = is_iterable($cuenta?->satCredenciales ?? null)
            ? $cuenta->satCredenciales
            : ($request->get('credList', []) ?? []);

        $credNorm    = $this->normalizeCreds($credList);
        $accountRfcs = $this->getAccountRfcs($cuentaId, $clienteId, $credNorm);

        $storage = [
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

        try {
            $s = $this->buildStorageSummary($cuentaId, $cuenta);

            $storage = [
                'has_quota'     => ((float)($s['quota_gb'] ?? 0)) > 0,
                'quota_gb'      => (float)($s['quota_gb'] ?? 0),
                'quota_bytes'   => (int)($s['quota_bytes'] ?? 0),
                'used_gb'       => (float)($s['used_gb'] ?? 0),
                'used_bytes'    => (int)($s['used_bytes'] ?? 0),
                'available_gb'  => (float)($s['free_gb'] ?? 0),
                'used_pct'      => (float)($s['used_pct'] ?? 0),
                'available_pct' => (float)($s['free_pct'] ?? 0),
                'files_count'   => 0,
            ];
        } catch (\Throwable $e) {
            Log::warning('[VAULT:quick] Error calculando resumen de bóveda', [
                'cuenta_id' => $cuentaId,
                'error'     => $e->getMessage(),
            ]);
        }

        $vaultZipRows = $this->buildZipRowsFromVaultFiles($cuentaId);
        if ($vaultZipRows instanceof \Illuminate\Support\Collection) {
            $vaultZipRows = $vaultZipRows->values()->all();
        }

        $limit = (int)($request->query('limit', 5000));
        $limit = max(1, min($limit, 50000));

        $bootData = [];
        if ($this->cfdiAvailable()) {
            $bootData = $this->buildBootDataFromCfdis($cuentaId, $clienteId, $accountRfcs);
            if (count($bootData) > $limit) {
                $bootData = array_slice($bootData, 0, $limit);
            }
        }

        $quotaGb     = (float)($storage['quota_gb'] ?? 0);
        $usedGb      = (float)($storage['used_gb'] ?? 0);
        $availableGb = (float)($storage['available_gb'] ?? 0);
        $enabled     = (bool)(((int)($storage['quota_bytes'] ?? 0)) > 0 || $quotaGb > 0);

        return response()->json([
            'ok'         => true,
            'cuenta_id'  => (string)$cuentaId,
            'cliente_id' => $clienteId ? (string)$clienteId : null,
            'storage'    => $storage,
            'vault'      => [
                'enabled'       => $enabled,
                'quota_gb'      => $quotaGb,
                'used_gb'       => $usedGb,
                'free_gb'       => $availableGb,
                'available_gb'  => $availableGb,
                'vaultZips'     => $vaultZipRows,
            ],
            'rows'       => $bootData,
            'counts'     => [
                'cfdi' => is_countable($bootData) ? count($bootData) : 0,
                'zips' => is_countable($vaultZipRows) ? count($vaultZipRows) : 0,
            ],
        ]);
    }

    /* ==========================================================
     *   DESCARGA ZIP DE BÓVEDA (sat_vault_files)
     *   NECESARIO para registrar la ruta cliente.sat.vault.file
     * ========================================================== */

    public function downloadVaultFile(Request $request, string $id)
    {
        [$user, , $cuentaId] = $this->currentAccount3();

        if (!$user || $cuentaId === '') {
            abort(401);
        }

        $conn   = self::CONN;
        $schema = Schema::connection($conn);

        if (!$schema->hasTable('sat_vault_files')) {
            abort(404, 'Tabla sat_vault_files no existe.');
        }

        $row = DB::connection($conn)->table('sat_vault_files')
            ->where('cuenta_id', $cuentaId)
            ->where('id', $id)
            ->first();

        if (!$row) {
            abort(404, 'Archivo no encontrado.');
        }

        $disk = (string)($row->disk ?? 'private');
        $path = ltrim((string)($row->path ?? ''), '/');

        if ($path === '') {
            abort(404, 'Ruta inválida.');
        }

        if (!$this->diskConfigured($disk)) {
            $disk = 'private';
        }

        if (!$this->diskExistsSafe($disk, $path)) {
            Log::warning('[VAULT:downloadVaultFile] Archivo no existe en storage', [
                'cuenta_id' => $cuentaId,
                'id'        => $id,
                'disk'      => $disk,
                'path'      => $path,
            ]);
            abort(404, 'Archivo no existe en storage.');
        }

        $filename = (string)($row->original_name ?? $row->filename ?? basename($path));
        $filename = trim($filename) !== '' ? $filename : basename($path);

        $mime = (string)($row->mime ?? '');
        if ($mime === '') {
            $mime = $this->guessMimeByExtension($filename);
        }

        return Storage::disk($disk)->download($path, $filename, [
            'Content-Type' => $mime,
        ]);
    }

    private function isDemoMode(Request $request): bool
    {
        $mode = strtolower((string)$request->cookie('sat_mode', 'prod'));
        return $mode === 'demo';
    }

    /* ==========================================================
     *   REDIRECT ROBUSTO A BÓVEDA
     * ========================================================== */

    private function redirectToVault()
    {
        foreach (['cliente.sat.vault', 'sat.vault', 'sat.vault.index'] as $name) {
            if (RouteFacade::has($name)) {
                return redirect()->route($name);
            }
        }
        return redirect('/cliente/sat/vault');
    }

    public function verify(Request $request)
{
    $cuentaId  = null;
    $clienteId = null;
    $user      = auth('web')->user();

    try {
        if (method_exists($this, 'currentAccount')) {
            [$u, , $cid, $clid] = $this->currentAccount();
            $user      = $u ?: $user;
            $cuentaId  = $cid ?: null;
            $clienteId = $clid ?: null;
        } elseif (method_exists($this, 'currentAccount3')) {
            [$u, , $cid] = $this->currentAccount3();
            $user     = $u ?: $user;
            $cuentaId = $cid ?: null;
        }
    } catch (\Throwable) {
        // no-op
    }

    if (!$cuentaId && $user) {
        try {
            if (isset($user->cuenta_id) && $user->cuenta_id) {
                $cuentaId = (string)$user->cuenta_id;
            } elseif (method_exists($user, 'cuenta') && $user->cuenta && isset($user->cuenta->id)) {
                $cuentaId = (string)$user->cuenta->id;
            }
        } catch (\Throwable) {
            // no-op
        }
    }

    if (!$cuentaId) {
        return response()->json([
            'ok'      => false,
            'message' => 'Cuenta inválida (no se pudo resolver cuenta_id).',
        ], 422);
    }

    $downloadId = trim((string)($request->get('download_id') ?? $request->get('id') ?? ''));
    if ($downloadId === '') {
        return response()->json([
            'ok'      => false,
            'message' => 'Falta download_id.',
        ], 422);
    }

    $conn   = self::CONN;
    $db     = DB::connection($conn);
    $schema = Schema::connection($conn);

    if (!$schema->hasTable('sat_downloads')) {
        return response()->json([
            'ok'      => false,
            'message' => 'Tabla sat_downloads no existe.',
        ], 500);
    }

    $row = $db->table('sat_downloads')
        ->where('cuenta_id', $cuentaId)
        ->where('id', $downloadId)
        ->first();

    if (!$row) {
        return response()->json([
            'ok'      => false,
            'message' => 'Descarga no encontrada.',
        ], 404);
    }

    $estatusSat     = (string)($row->estatus_sat ?? $row->estado_sat ?? $row->status_sat ?? '');
    $disponibilidad = (string)($row->disponibilidad ?? $row->availability ?? '');
    $zipPath        = ltrim((string)($row->zip_path ?? ''), '/');

    // zip_disk: columna si existe, si no, meta/meta_json
    $zipDisk = '';
    if ($schema->hasColumn('sat_downloads', 'zip_disk')) {
        $zipDisk = (string)($row->zip_disk ?? '');
    } else {
        $metaArr = $this->decodeMetaToArray($row->meta ?? null);
        if (!$metaArr && $schema->hasColumn('sat_downloads', 'meta_json')) {
            $metaArr = $this->decodeMetaToArray($row->meta_json ?? null);
        }
        if (is_array($metaArr)) {
            $zipDisk = (string)($metaArr['zip_disk'] ?? $metaArr['meta_zip_disk'] ?? '');
        }
    }

    // “disponible” por SAT aunque zip_path venga vacío
    $isReady = ($zipPath !== '') || (mb_strtolower($disponibilidad) === 'disponible');

    // ==========================================================
    // NUEVO: si está “disponible” pero no tenemos path/disk real,
    // intentamos resolverlo por fallback y persistir.
    // ==========================================================
    if ($isReady && ($zipPath === '' || $zipDisk === '' || !$this->diskExistsSafe($zipDisk !== '' ? $zipDisk : 'private', $zipPath))) {
        try {
            $resolved = $this->resolveZipForDownloadFallback((string)$cuentaId, (string)$downloadId);
            if (is_array($resolved) && count($resolved) === 2) {
                [$d, $p] = $resolved;
                $d = (string)$d;
                $p = ltrim((string)$p, '/');

                if ($p !== '' && $this->diskExistsSafe($d, $p)) {
                    $zipDisk = $d;
                    $zipPath = $p;

                    // Persistimos mapping en sat_downloads si existen columnas
                    $upd = ['updated_at' => now()];
                    if ($schema->hasColumn('sat_downloads', 'zip_disk')) $upd['zip_disk'] = $zipDisk;
                    if ($schema->hasColumn('sat_downloads', 'zip_path')) $upd['zip_path'] = $zipPath;

                    try {
                        $db->table('sat_downloads')
                            ->where('cuenta_id', $cuentaId)
                            ->where('id', $downloadId)
                            ->update($upd);
                    } catch (\Throwable) {
                        // no-op
                    }
                }
            }
        } catch (\Throwable) {
            // no-op
        }
    }

    // ==========================================================
    // NUEVO: si ya tenemos zip real, calculamos bytes y guardamos
    // peso/costo/bytes para que tu listado deje “Pendiente”.
    // ==========================================================
    $bytes   = 0;
    $pesoGb  = 0.0;
    $pesoMb  = 0.0;
    $costo   = null;

    if ($isReady && $zipPath !== '' && $zipDisk !== '') {
        if ($this->diskExistsSafe($zipDisk, $zipPath)) {
            $bytes = $this->safeSize($zipDisk, $zipPath);

            if ($bytes > 0) {
                $pesoGb = round($this->bytesToGb($bytes), 4);
                $pesoMb = round(($bytes / 1024 / 1024), 2);

                // Persistencia robusta en sat_downloads (solo columnas existentes)
                $costo = $this->syncSatDownloadMetricsFromZip(
                    (string)$cuentaId,
                    (string)$downloadId,
                    (string)$zipDisk,
                    (string)$zipPath,
                    (int)$bytes
                );
            }
        }
    }

    return response()->json([
        'ok'             => true,
        'download_id'    => $downloadId,
        'cuenta_id'      => (string)$cuentaId,
        'cliente_id'     => $clienteId ? (string)$clienteId : null,
        'estatus_sat'    => $estatusSat,
        'disponible'     => $isReady,
        'disponibilidad' => $disponibilidad,
        'zip_disk'       => $zipDisk,
        'zip_path'       => $zipPath,

        // NUEVO: datos listos para que el frontend pinte PESO/COSTO
        'bytes'          => (int)$bytes,
        'peso_gb'        => (float)$pesoGb,
        'peso_mb'        => (float)$pesoMb,
        'costo'          => $costo,
    ]);
}


    /* ==========================================================
     *   API: GUARDAR DESCARGA EN BÓVEDA (indexa ZIP->CFDI)
     * ========================================================== */

    public function fromDownload(Request $request, string $download)
    {
        [$user, , $cuentaId] = $this->currentAccount3();

        if (!$cuentaId) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Cuenta inválida.',
                ], 422);
            }
            return $this->redirectToVault()->with('error', 'Cuenta inválida.');
        }

        $conn   = self::CONN;
        $db     = DB::connection($conn);
        $schema = Schema::connection($conn);

        $zf = null;

        if ($schema->hasTable('sat_vault_files')) {
            $zf = $db->table('sat_vault_files')
                ->where('cuenta_id', $cuentaId)
                ->where('source', 'sat_download')
                ->where('source_id', $download)
                ->orderByDesc('id')
                ->first();
        }

        if (!$zf) {
            Log::warning('[VAULT:fromDownload] No se encontró sat_vault_files para download; intentando fallback desde sat_downloads', [
                'cuenta_id' => $cuentaId,
                'source'    => 'sat_download',
                'source_id' => $download,
            ]);

            $zf = $this->ensureVaultFileForDownload($cuentaId, $download);

            if (!$zf) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'ok'      => false,
                        'message' => 'No se encontró el ZIP (ni en bóveda ni en descargas) para esa descarga.',
                    ], 404);
                }

                return $this->redirectToVault()->with('error', 'No se encontró el ZIP (ni en bóveda ni en descargas) para esa descarga.');
            }
        }

        $vaultFileId = (int)($zf->id ?? 0);

        try {
            if ($vaultFileId > 0 && $schema->hasTable('sat_vault_cfdis') && $schema->hasColumn('sat_vault_cfdis', 'vault_file_id')) {
                $alreadyImported = $db->table('sat_vault_cfdis')
                    ->where('cuenta_id', $cuentaId)
                    ->where('vault_file_id', $vaultFileId)
                    ->limit(1)
                    ->exists();

                if ($alreadyImported) {
                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json([
                            'ok'            => true,
                            'message'       => 'ZIP ya estaba indexado en bóveda.',
                            'inserted'      => 0,
                            'vault_file_id' => $vaultFileId,
                            'download_id'   => (string)$download,
                            'skipped'       => true,
                        ]);
                    }

                    return $this->redirectToVault()->with('success', 'ZIP ya estaba indexado en bóveda.');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[VAULT:fromDownload] No se pudo validar idempotencia, continuando', [
                'cuenta_id'     => $cuentaId,
                'vault_file_id' => $vaultFileId,
                'error'         => $e->getMessage(),
            ]);
        }

        try {
            $inserted = $this->importZipIntoCfdis(
                (string)($zf->disk ?? 'private'),
                (string)($zf->path ?? ''),
                $cuentaId,
                (string)($zf->rfc ?? ''),
                $vaultFileId
            );
        } catch (\Throwable $e) {
            Log::error('[VAULT:fromDownload] Error importando ZIP a CFDIs', [
                'cuenta_id'     => $cuentaId,
                'vault_file_id' => $vaultFileId,
                'error'         => $e->getMessage(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Error importando ZIP a CFDIs: ' . $e->getMessage(),
                ], 500);
            }

            return $this->redirectToVault()->with('error', 'Error importando ZIP a CFDIs.');
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'            => true,
                'message'       => 'ZIP indexado en bóveda.',
                'inserted'      => (int)$inserted,
                'vault_file_id' => $vaultFileId,
                'download_id'   => (string)$download,
            ]);
        }

        return $this->redirectToVault()->with('success', 'ZIP indexado en bóveda. CFDI importados: ' . (int)$inserted);
    }

    /**
     * Fallback: si sat_vault_files NO tiene el ZIP, lo localiza en sat_downloads,
     * lo copia a vaultDisk y crea/actualiza sat_vault_files.
     */
    private function ensureVaultFileForDownload(string $cuentaId, string $downloadId): ?object
    {
        $conn   = self::CONN;
        $schema = Schema::connection($conn);

        if (!$schema->hasTable('sat_downloads') || !$schema->hasTable('sat_vault_files')) {
            return null;
        }

        $dl = DB::connection($conn)->table('sat_downloads')
            ->where('cuenta_id', $cuentaId)
            ->where('id', $downloadId)
            ->first();

        if (!$dl) {
            Log::warning('[VAULT:fallback] sat_downloads no encontró download', [
                'cuenta_id'   => $cuentaId,
                'download_id' => $downloadId,
            ]);
            return null;
        }

        $resolved = $this->resolveZipForDownloadFallback($cuentaId, $downloadId);
        if (!$resolved) {
            return null;
        }

        [$srcDisk, $srcPath] = $resolved;
        $srcPath = ltrim((string)$srcPath, '/');

        if ($srcPath === '' || !$this->diskExistsSafe($srcDisk, $srcPath)) {
            Log::warning('[VAULT:fallback] ZIP no existe en storage (post-resolve)', [
                'cuenta_id'   => $cuentaId,
                'download_id' => $downloadId,
                'src_disk'    => $srcDisk,
                'src_path'    => $srcPath,
            ]);
            return null;
        }

        $vaultDisk = config('filesystems.disks.sat_vault')
            ? 'sat_vault'
            : (config('filesystems.disks.vault') ? 'vault'
                : (config('filesystems.disks.private') ? 'private' : $srcDisk));

        $rfc = strtoupper((string)($dl->rfc ?? ''));
        if ($rfc === '') {
            $rfc = 'XAXX010101000';
        }

        $now     = Carbon::now();
        $baseDir = 'vault/' . $cuentaId . '/' . $rfc;

        $destName = 'SAT_' . $rfc . '_' . $now->format('Ymd_His') . '_' . Str::random(6) . '.zip';
        $destPath = $baseDir . '/' . $destName;

        try {
            Storage::disk($vaultDisk)->makeDirectory($baseDir);
        } catch (\Throwable) {
            // no-op
        }

        $read = null;
        try {
            $read = Storage::disk($srcDisk)->readStream($srcPath);
            if (!$read) {
                Log::warning('[VAULT:fallback] No se pudo abrir stream del ZIP origen', [
                    'cuenta_id' => $cuentaId,
                    'src_disk'  => $srcDisk,
                    'src_path'  => $srcPath,
                ]);
                return null;
            }

            $ok = Storage::disk($vaultDisk)->writeStream($destPath, $read);

            if (is_resource($read)) {
                fclose($read);
            }

            if (!$ok) {
                Log::warning('[VAULT:fallback] writeStream a bóveda falló', [
                    'cuenta_id'  => $cuentaId,
                    'vault_disk' => $vaultDisk,
                    'dest_path'  => $destPath,
                ]);
                return null;
            }
        } catch (\Throwable $e) {
            try {
                if (is_resource($read)) fclose($read);
            } catch (\Throwable) {}

            Log::error('[VAULT:fallback] Error copiando ZIP a bóveda', [
                'cuenta_id'   => $cuentaId,
                'download_id' => $downloadId,
                'src_disk'    => $srcDisk,
                'src_path'    => $srcPath,
                'vault_disk'  => $vaultDisk,
                'dest_path'   => $destPath,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }

        $bytes = 0;
        try { $bytes = (int) Storage::disk($vaultDisk)->size($destPath); } catch (\Throwable) {}

        if ($bytes <= 0) {
            try { Storage::disk($vaultDisk)->delete($destPath); } catch (\Throwable) {}
            Log::warning('[VAULT:fallback] ZIP copiado pero bytes=0, eliminado', [
                'cuenta_id'  => $cuentaId,
                'vault_disk' => $vaultDisk,
                'dest_path'  => $destPath,
            ]);
            return null;
        }

        $fileName = basename($destPath);

        $data = [
            'cuenta_id'  => $cuentaId,
            'rfc'        => $rfc,
            'source'     => 'sat_download',
            'source_id'  => $downloadId,
            'filename'   => $fileName,
            'path'       => $destPath,
            'disk'       => $vaultDisk,
            'mime'       => 'application/zip',
            'bytes'      => $bytes,
            'updated_at' => now(),
        ];

        $existing = DB::connection($conn)->table('sat_vault_files')
            ->where('cuenta_id', $cuentaId)
            ->where('source', 'sat_download')
            ->where('source_id', $downloadId)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            DB::connection($conn)->table('sat_vault_files')->where('id', $existing->id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::connection($conn)->table('sat_vault_files')->insert($data);
        }

        Log::info('[VAULT:fallback] ZIP reconstruido en sat_vault_files', [
            'cuenta_id'   => $cuentaId,
            'download_id' => $downloadId,
            'src_disk'    => $srcDisk,
            'src_path'    => $srcPath,
            'vault_disk'  => $vaultDisk,
            'dest_path'   => $destPath,
            'bytes'       => $bytes,
        ]);

        return DB::connection($conn)->table('sat_vault_files')
            ->where('cuenta_id', $cuentaId)
            ->where('source', 'sat_download')
            ->where('source_id', $downloadId)
            ->orderByDesc('id')
            ->first();
    }

    /* ==========================================================
     *   ALMACENAMIENTO
     * ========================================================== */

    public function buildStorageSummary(?string $cuentaId, $cuentaObj): array
    {
        $conn       = self::CONN;
        $schemaConn = Schema::connection($conn);

        $cuentaId = (string)($cuentaId ?? '');
        if ($cuentaId === '') {
            return [
                'quota_gb'    => 0.0,
                'quota_bytes' => 0,
                'used_gb'     => 0.0,
                'used_bytes'  => 0,
                'free_gb'     => 0.0,
                'used_pct'    => 0.0,
                'free_pct'    => 0.0,
            ];
        }

        $planRaw   = (string)($cuentaObj->plan_actual ?? 'FREE');
        $plan      = strtoupper($planRaw);
        $isProPlan = in_array($plan, ['PRO', 'PREMIUM', 'EMPRESA', 'BUSINESS'], true);

        $vaultBaseGb = $isProPlan ? (float)config('services.sat.vault.base_gb_pro', 0.0) : 0.0;

        $quotaBytesFromAccount = 0;
        if ($schemaConn->hasTable('cuentas_cliente')) {
            if ($schemaConn->hasColumn('cuentas_cliente', 'vault_quota_bytes')) {
                $quotaBytesFromAccount = (int)($cuentaObj->vault_quota_bytes ?? 0);
            } elseif ($schemaConn->hasColumn('cuentas_cliente', 'vault_quota_gb')) {
                $quotaGb = (float)($cuentaObj->vault_quota_gb ?? 0);
                $quotaBytesFromAccount = (int)round($quotaGb * 1024 * 1024 * 1024);
            }
        }

        $quotaBytesFromAccount = max(0, $quotaBytesFromAccount);
        $quotaGbFromAccount    = $this->bytesToGb($quotaBytesFromAccount);

        $quotaGbFromVaultRows = 0.0;
        if ($schemaConn->hasTable('sat_downloads')) {
            try {
                $vaultRowsPaid = DB::connection($conn)->table('sat_downloads')
                    ->where('cuenta_id', $cuentaId)
                    ->where(function ($q) {
                        $q->where('tipo', 'VAULT')->orWhere('tipo', 'BOVEDA');
                    })
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

                    if ($gb > 0) {
                        $totalGb += $gb;
                    }
                }

                $quotaGbFromVaultRows = max(0.0, $totalGb);
            } catch (\Throwable $e) {
                Log::warning('[VaultController] Error leyendo compras de bóveda', [
                    'cuenta_id' => $cuentaId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $quotaGbComputed = max(0.0, (float)$vaultBaseGb + (float)$quotaGbFromVaultRows);
        $quotaGbFinal    = max($quotaGbComputed, (float)$quotaGbFromAccount);
        $quotaBytesFinal = (int)round($quotaGbFinal * 1024 * 1024 * 1024);

        $usedBytes = 0;

        if ($schemaConn->hasTable('cuentas_cliente') && $schemaConn->hasColumn('cuentas_cliente', 'vault_used_bytes')) {
            $usedBytes = (int)($cuentaObj->vault_used_bytes ?? 0);
        }

        if ($usedBytes <= 0 && $schemaConn->hasTable('sat_vault_files') && $schemaConn->hasColumn('sat_vault_files', 'bytes')) {
            try {
                $usedBytes = (int)DB::connection($conn)->table('sat_vault_files')
                    ->where('cuenta_id', $cuentaId)
                    ->sum('bytes');
            } catch (\Throwable) {
                // no-op
            }
        }

        $usedBytes = max(0, $usedBytes);

        $quotaBytes = max($quotaBytesFinal, $usedBytes);

        $usedGb  = $this->bytesToGb($usedBytes);
        $quotaGb = $this->bytesToGb($quotaBytes);
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

    /* ==========================================================
     *   DISPONIBILIDAD
     * ========================================================== */

    private function cfdiAvailable(): bool
    {
        try {
            return Schema::connection(self::CONN)->hasTable('sat_vault_cfdis');
        } catch (\Throwable) {
            return false;
        }
    }

    /* ==========================================================
     *   DATASET DESDE CFDIS
     * ========================================================== */

    private function buildBootDataFromCfdis(string $cuentaId, $clienteId, array $accountRfcs): array
    {
        if (!$this->cfdiAvailable()) {
            return [];
        }

        $schema = Schema::connection(self::CONN);

        $fechaCol = $schema->hasColumn('sat_vault_cfdis', 'fecha_emision')
            ? 'fecha_emision'
            : ($schema->hasColumn('sat_vault_cfdis', 'fecha') ? 'fecha' : null);

        $qb = DB::connection(self::CONN)
            ->table('sat_vault_cfdis')
            ->where('cuenta_id', $cuentaId);

        if ($fechaCol) {
            $qb->orderByDesc($fechaCol);
        } else {
            $qb->orderByDesc('id');
        }

        $rfcs = array_values(array_filter(array_map('strtoupper', $accountRfcs)));
        if (!empty($rfcs)) {
            $qb->where(function ($w) use ($schema, $rfcs) {
                if ($schema->hasColumn('sat_vault_cfdis', 'rfc_emisor')) {
                    $w->orWhereIn('rfc_emisor', $rfcs);
                }
                if ($schema->hasColumn('sat_vault_cfdis', 'rfc_receptor')) {
                    $w->orWhereIn('rfc_receptor', $rfcs);
                }
                if ($schema->hasColumn('sat_vault_cfdis', 'rfc')) {
                    $w->orWhereIn('rfc', $rfcs);
                }
            });
        }

        $rows = $qb->limit(5000)->get();

        return $rows->map(function ($c) use ($schema, $fechaCol) {
            $fecha = '';
            if ($fechaCol) {
                $fecha = (string)($c->{$fechaCol} ?? '');
            }

            $tipo  = (string)($c->tipo ?? '');
            $uuid  = (string)($c->uuid ?? '');

            $subtotal = (float)($c->subtotal ?? 0);
            $iva      = (float)($c->iva ?? 0);
            $total    = (float)($c->total ?? 0);

            if ($iva <= 0 && $subtotal > 0 && $total > $subtotal) {
                $iva = round($total - $subtotal, 2);
            }
            if ($total <= 0 && ($subtotal > 0 || $iva > 0)) {
                $total = $subtotal + $iva;
            }

            $rfc = '';
            if ($schema->hasColumn('sat_vault_cfdis', 'rfc_emisor')) {
                $rfc = (string)($c->rfc_emisor ?? '');
            }
            if ($rfc === '' && $schema->hasColumn('sat_vault_cfdis', 'rfc_receptor')) {
                $rfc = (string)($c->rfc_receptor ?? '');
            }
            if ($rfc === '' && $schema->hasColumn('sat_vault_cfdis', 'rfc')) {
                $rfc = (string)($c->rfc ?? '');
            }

            $razon = '—';
            if ($schema->hasColumn('sat_vault_cfdis', 'razon_emisor')) {
                $razon = (string)($c->razon_emisor ?? '—');
            }
            if (($razon === '' || $razon === '—') && $schema->hasColumn('sat_vault_cfdis', 'razon_receptor')) {
                $razon = (string)($c->razon_receptor ?? '—');
            }

            return [
                'kind'     => 'cfdi',
                'fecha'    => $fecha !== '' ? substr($fecha, 0, 10) : '',
                'tipo'     => $tipo,
                'rfc'      => strtoupper($rfc),
                'razon'    => $razon !== '' ? $razon : '—',
                'uuid'     => strtoupper($uuid),
                'subtotal' => $subtotal,
                'iva'      => $iva,
                'total'    => $total,
            ];
        })->values()->all();
    }

    /* ==========================================================
     *   AUTO-IMPORT ZIPs -> sat_vault_cfdis
     * ========================================================== */

    private function importZipsToVaultCfdisIfNeeded(string $cuentaId, array $vaultZipRows): void
    {
        if ($cuentaId === '') return;
        if (!$this->cfdiAvailable()) return;
        if (empty($vaultZipRows)) return;

        $zips = DB::connection(self::CONN)
            ->table('sat_vault_files')
            ->where('cuenta_id', $cuentaId)
            ->where(function ($w) {
                $w->where('mime', 'application/zip')
                    ->orWhere('filename', 'like', '%.zip')
                    ->orWhere('path', 'like', '%.zip');
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        if ($zips->isEmpty()) {
            return;
        }

        foreach ($zips as $zf) {
            try {
                $schema = Schema::connection(self::CONN);
                if ($schema->hasTable('sat_vault_cfdis') && $schema->hasColumn('sat_vault_cfdis', 'vault_file_id')) {
                    $already = DB::connection(self::CONN)->table('sat_vault_cfdis')
                        ->where('cuenta_id', $cuentaId)
                        ->where('vault_file_id', (int)($zf->id ?? 0))
                        ->limit(1)
                        ->exists();

                    if ($already) continue;
                }

                $this->importZipIntoCfdis(
                    (string)($zf->disk ?? 'private'),
                    (string)($zf->path ?? ''),
                    (string)($zf->cuenta_id ?? $cuentaId),
                    (string)($zf->rfc ?? ''),
                    (int)($zf->id ?? 0)
                );
            } catch (\Throwable $e) {
                Log::warning('[VAULT:autoImport] Error importando ZIP', [
                    'cuenta_id'     => $cuentaId,
                    'vault_file_id' => (int)($zf->id ?? 0),
                    'error'         => $e->getMessage(),
                ]);
            }
        }
    }

    /* ==========================================================
     *   IMPORT ZIP -> sat_vault_cfdis
     * ========================================================== */

    private function importZipIntoCfdis(string $disk, string $path, string $cuentaId, string $rfc = '', int $vaultFileId = 0): int
    {
        $disk = $disk !== '' ? $disk : 'private';
        $path = ltrim($path, '/');

        if ($path === '' || !$this->diskExistsSafe($disk, $path)) {
            Log::warning('[VAULT:importZip] ZIP no existe', [
                'cuenta_id' => $cuentaId,
                'disk'      => $disk,
                'path'      => $path,
            ]);
            return 0;
        }

        $abs = Storage::disk($disk)->path($path);

        $zip = new \ZipArchive();
        $ok  = $zip->open($abs);

        if ($ok !== true) {
            Log::warning('[VAULT:importZip] No se pudo abrir ZIP', [
                'cuenta_id' => $cuentaId,
                'disk'      => $disk,
                'path'      => $path,
                'zip_open'  => $ok,
            ]);
            return 0;
        }

        $schema = Schema::connection(self::CONN);
        $has = function (string $col) use ($schema): bool {
            try {
                return $schema->hasTable('sat_vault_cfdis') && $schema->hasColumn('sat_vault_cfdis', $col);
            } catch (\Throwable) {
                return false;
            }
        };

        $ownerRfc = strtoupper(trim((string)$rfc));
        if ($ownerRfc === '') $ownerRfc = null;

        $inserted = 0;

               for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (!$entryName) continue;
            if (!str_ends_with(strtolower($entryName), '.xml')) continue;

            $xml = $zip->getFromIndex($i);
            if (!$xml) continue;

            try {
                $inserted += $this->importXmlStringIntoCfdis(
                    (string) $xml,
                    $cuentaId,
                    (string) ($ownerRfc ?? ''),
                    $vaultFileId,
                    $path . '#' . $entryName
                );
            } catch (\Throwable $e) {
                Log::warning('[VAULT:importZip] XML inválido', [
                    'cuenta_id' => $cuentaId,
                    'entry'     => $entryName,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $zip->close();

        return $inserted;
    }

    /* ==========================================================
     *   DATASET DESDE BÓVEDA (sat_vault_files)
     * ========================================================== */

    private function buildBootDataFromVaultFiles(string $cuentaId): array
    {
        try {
            $conn  = self::CONN;
            $table = (new VaultFile())->getTable() ?: 'sat_vault_files';

            if (!Schema::connection($conn)->hasTable($table)) {
                $table = 'sat_vault_files';
            }

            if (!Schema::connection($conn)->hasTable($table)) {
                return [];
            }

            $rows = DB::connection($conn)
                ->table($table)
                ->where('cuenta_id', $cuentaId)
                ->orderByDesc('id')
                ->limit(500)
                ->get();

            $items = [];
            foreach ($rows as $r) {
                $filename = (string)($r->filename ?? '');
                $path     = (string)($r->path ?? '');
                $mime     = strtolower((string)($r->mime ?? ''));

                $ext   = strtolower(pathinfo($filename !== '' ? $filename : $path, PATHINFO_EXTENSION));
                $isZip = ($ext === 'zip') || ($mime === 'application/zip') || str_ends_with(strtolower($path), '.zip');

                if ($isZip) {
                    $created   = (string)($r->created_at ?? '');
                    $fechaFile = $created !== '' ? substr($created, 0, 10) : null;

                    $items[] = [
                        'kind'     => 'file',
                        'id'       => (string)($r->id ?? ''),
                        'fecha'    => $fechaFile,
                        'tipo'     => 'archivo',
                        'rfc'      => strtoupper((string)($r->rfc ?? '')),
                        'razon'    => $filename !== '' ? $filename : 'Archivo',
                        'uuid'     => '',
                        'subtotal' => 0.0,
                        'iva'      => 0.0,
                        'total'    => 0.0,
                        'bytes'    => (int)($r->bytes ?? $r->size_bytes ?? 0),
                    ];
                    continue;
                }
            }

            return $items;
        } catch (\Throwable $e) {
            Log::warning('[VaultController] Error leyendo bóveda', [
                'cuenta_id' => $cuentaId,
                'error'     => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function resolveLinkedDownloadIdFromMeta(?string $metaRaw): ?string
    {
        if (!$metaRaw) return null;

        $meta = json_decode($metaRaw, true);
        if (!is_array($meta)) return null;

        foreach (['download_id', 'new_download_id', 'linked_download_id'] as $k) {
            $v = $meta[$k] ?? null;
            if (is_string($v) && preg_match('~^[0-9a-f\-]{36}$~i', $v)) {
                return $v;
            }
        }

        $paths = [
            'payload.download_id',
            'payload.new_download_id',
            'linked.download_id',
            'linked.new',
            'link.new',
            'link.download_id',
            'force_process_payload.download_id',
        ];

        foreach ($paths as $p) {
            $v = data_get($meta, $p);
            if (is_string($v) && preg_match('~^[0-9a-f\-]{36}$~i', $v)) {
                return $v;
            }
        }

        return null;
    }

    private function findMostRecentZipForAccount(string $disk, string $cuentaId, string $rfc = ''): string
    {
        $rfc = strtoupper(trim($rfc));

        $dirs = [
            "packages/{$cuentaId}",
            "sat/packages/{$cuentaId}",
            $cuentaId,
        ];

        $candidates = [];

        foreach ($dirs as $dir) {
            $dir = trim($dir, '/');
            $files = [];

            try { $files = array_merge($files, Storage::disk($disk)->files($dir)); } catch (\Throwable) {}
            try { $files = array_merge($files, Storage::disk($disk)->allFiles($dir)); } catch (\Throwable) {}

            foreach ($files as $f) {
                $f = ltrim((string)$f, '/');
                if ($f === '') continue;
                if (!str_ends_with(strtolower($f), '.zip')) continue;

                if ($rfc !== '' && stripos($f, $rfc) === false) {
                    $candidates[] = ['path' => $f, 'score' => 0];
                    continue;
                }

                $candidates[] = ['path' => $f, 'score' => 10];
            }
        }

        if (!count($candidates)) return '';

        usort($candidates, function ($a, $b) use ($disk) {
            if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];

            $ma = 0; $mb = 0;
            try { $ma = (int)Storage::disk($disk)->lastModified($a['path']); } catch (\Throwable) {}
            try { $mb = (int)Storage::disk($disk)->lastModified($b['path']); } catch (\Throwable) {}

            return $mb <=> $ma;
        });

        return (string)($candidates[0]['path'] ?? '');
    }

    /**
     * Resuelve el ZIP real para un downloadId, sin copiar nada.
     * Regresa [disk, path] o null.
     */
    private function resolveZipForDownloadFallback(string $cuentaId, string $downloadId): ?array
    {
        /** @var SatDownload|null $dl */
        $dl = SatDownload::on(self::CONN)
            ->where('id', $downloadId)
            ->where('cuenta_id', $cuentaId)
            ->first();

        if (!$dl) {
            Log::warning('[VAULT:fallback] sat_downloads no encontrado', [
                'cuenta_id'   => $cuentaId,
                'download_id' => $downloadId,
                'conn'        => self::CONN,
            ]);
            return null;
        }

        $meta = [];
        foreach (['meta', 'meta_json'] as $field) {
            try {
                $raw = $dl->{$field} ?? null;
                if (is_array($raw)) {
                    $meta = array_replace_recursive($meta, $raw);
                } elseif (is_string($raw) && trim($raw) !== '') {
                    $tmp = json_decode($raw, true);
                    if (is_array($tmp)) $meta = array_replace_recursive($meta, $tmp);
                }
            } catch (\Throwable) {}
        }

        $linkedId = null;
        try {
            $linkedId = $this->resolveLinkedDownloadIdFromMeta(
                is_string($dl->meta ?? null) ? (string)$dl->meta : (is_string($dl->meta_json ?? null) ? (string)$dl->meta_json : null)
            );
        } catch (\Throwable) {}

        $diskCandidates = $this->diskCandidates([
            (string)($dl->zip_disk ?? ''),
            (string)($meta['zip_disk'] ?? ''),
            (string)($meta['meta_zip_disk'] ?? ''),
            (string)config('filesystems.sat_downloads_disk', ''),
            'sat_zip',
            'sat_downloads',
        ]);

        $pathCandidates = [];

        foreach ([
            (string)($dl->zip_path ?? ''),
            (string)($dl->package_path ?? ''),
            (string)($meta['zip_path'] ?? ''),
            (string)($meta['package_path'] ?? ''),
            (string)data_get($meta, 'payload.zip_path', ''),
            (string)data_get($meta, 'payload.package_path', ''),
        ] as $p) {
            $p = ltrim(trim((string)$p), '/');
            if ($p !== '') $pathCandidates[] = $p;
        }

        $pathCandidates[] = "packages/{$cuentaId}/pkg_{$downloadId}.zip";
        $pathCandidates[] = "sat/packages/{$cuentaId}/pkg_{$downloadId}.zip";
        $pathCandidates[] = "packages/{$cuentaId}/{$downloadId}.zip";
        $pathCandidates[] = "sat/packages/{$cuentaId}/{$downloadId}.zip";

        if ($linkedId) {
            $pathCandidates[] = "packages/{$cuentaId}/pkg_{$linkedId}.zip";
            $pathCandidates[] = "sat/packages/{$cuentaId}/pkg_{$linkedId}.zip";
            $pathCandidates[] = "packages/{$cuentaId}/{$linkedId}.zip";
            $pathCandidates[] = "sat/packages/{$cuentaId}/{$linkedId}.zip";
        }

        $pathCandidates = array_values(array_unique(array_filter($pathCandidates)));

        foreach ($diskCandidates as $disk) {
            foreach ($pathCandidates as $path) {
                $path = ltrim((string)$path, '/');
                if ($path === '') continue;

                if ($this->diskExistsSafe($disk, $path)) {
                    return [$disk, $path];
                }
            }
        }

        foreach ($diskCandidates as $disk) {
            try {
                $found = $this->findZipByListing($disk, $cuentaId, $downloadId, $linkedId);
                if ($found !== '') {
                    return [$disk, ltrim($found, '/')];
                }
            } catch (\Throwable) {}
        }

        foreach ($diskCandidates as $disk) {
            try {
                $fallbackZip = $this->findMostRecentZipForAccount($disk, $cuentaId, (string)($dl->rfc ?? ''));
                if ($fallbackZip !== '') {
                    Log::warning('[VAULT:fallback] Usando ZIP más reciente por falta de mapping (último recurso)', [
                        'cuenta_id'   => $cuentaId,
                        'download_id' => $downloadId,
                        'disk'        => $disk,
                        'zip'         => $fallbackZip,
                    ]);
                    return [$disk, ltrim($fallbackZip, '/')];
                }
            } catch (\Throwable) {}
        }

        Log::warning('[VAULT:fallback] ZIP no existe en storage de descargas', [
            'cuenta_id'   => $cuentaId,
            'download_id' => $downloadId,
            'linked_id'   => $linkedId,
            'disk_try'    => $diskCandidates,
            'path_try'    => $pathCandidates,
        ]);

        return null;
    }

    private function diskCandidates(array $preferred = []): array
    {
        $candidates = [];

        foreach ($preferred as $d) {
            $d = trim((string)$d);
            if ($d !== '') $candidates[] = $d;
        }

        foreach ([
            'sat_zip',
            'sat_downloads',
            'private',
            'public',
            'local',
            'sat_vault',
            'vault',
            'sat',
        ] as $d) {
            if ($this->diskConfigured($d)) $candidates[] = $d;
        }

        $default = (string)config('filesystems.default', 'local');
        if ($default !== '') $candidates[] = $default;

        $candidates = array_values(array_unique($candidates));

        return array_values(array_filter($candidates, fn($d) => $this->diskConfigured((string)$d)));
    }

    protected function findZipByListing(string $disk, string $cuentaId, string $downloadId, ?string $linkedId = null): string
    {
        $needles = array_values(array_filter([
            $downloadId,
            $linkedId ?: null,
        ]));

        $dirs = [];

        if ($disk === 'sat_zip') {
            $dirs = [
                "packages/{$cuentaId}",
                "packages/{$cuentaId}/done",
                "packages/{$cuentaId}/paid",
                "packages/{$cuentaId}/tmp",

                "sat/packages/{$cuentaId}",
                "sat/packages/{$cuentaId}/done",
                "sat/packages/{$cuentaId}/paid",
                "sat/packages/{$cuentaId}/tmp",

                "downloads/{$cuentaId}",
                "zips/{$cuentaId}",
                "tmp/{$cuentaId}",
                "sat/demo",
            ];
        } elseif ($disk === 'sat_downloads') {
            $dirs = [
                $cuentaId,
                "{$cuentaId}/done",
                "{$cuentaId}/paid",
                "{$cuentaId}/tmp",
                "packages/{$cuentaId}",
                "packages/{$cuentaId}/done",
                "packages/{$cuentaId}/paid",
            ];
        } else {
            $dirs = [
                "sat/packages/{$cuentaId}",
                "packages/{$cuentaId}",
                "sat/packages/{$cuentaId}/done",
                "sat/packages/{$cuentaId}/paid",
                "packages/{$cuentaId}/done",
                "packages/{$cuentaId}/paid",
                "sat_downloads/{$cuentaId}",
            ];
        }

        $dirs = array_values(array_unique(array_filter($dirs)));

        foreach ($dirs as $dir) {
            $dir = trim($dir, '/');
            $files = [];

            try { $files = array_merge($files, Storage::disk($disk)->files($dir)); } catch (\Throwable) {}
            try { $files = array_merge($files, Storage::disk($disk)->allFiles($dir)); } catch (\Throwable) {}

            if (!count($files)) continue;

            foreach ($files as $f) {
                $f = ltrim((string)$f, '/');
                if ($f === '') continue;
                if (!str_ends_with(strtolower($f), '.zip')) continue;

                foreach ($needles as $needle) {
                    if ($needle && stripos($f, $needle) !== false) {
                        return $f;
                    }
                }
            }
        }

        $bases = [];

        if ($disk === 'sat_zip') {
            $bases = ['packages', 'sat/packages', 'downloads', 'zips', 'tmp'];
        } elseif ($disk === 'sat_downloads') {
            $bases = ['', 'packages'];
        } else {
            $bases = ['sat', 'sat/packages', 'packages', 'sat_downloads'];
        }

        foreach ($bases as $base) {
            $base = trim((string)$base, '/');

            $files = [];
            try { $files = Storage::disk($disk)->allFiles($base); } catch (\Throwable) { $files = []; }

            if (!count($files)) continue;

            foreach ($files as $f) {
                $f = ltrim((string)$f, '/');
                if ($f === '') continue;

                if (!str_ends_with(strtolower($f), '.zip')) continue;

                $looksLikeAccountScoped = (stripos($f, $cuentaId) !== false) || (stripos($f, 'pkg_') !== false);

                foreach ($needles as $needle) {
                    if ($needle && stripos($f, $needle) !== false && $looksLikeAccountScoped) {
                        return $f;
                    }
                }
            }
        }

        return '';
    }

    private function buildZipRowsFromVaultFiles(string $cuentaId): array
    {
        try {
            $conn  = self::CONN;
            $table = (new VaultFile())->getTable() ?: 'sat_vault_files';

            if (!Schema::connection($conn)->hasTable($table)) {
                $table = 'sat_vault_files';
            }
            if (!Schema::connection($conn)->hasTable($table)) {
                return [];
            }

            $rows = DB::connection($conn)->table($table)
                ->where('cuenta_id', $cuentaId)
                ->orderByDesc('id')
                ->limit(200)
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $filename = (string)($r->filename ?? '');
                $path     = (string)($r->path ?? '');
                $mime     = strtolower((string)($r->mime ?? ''));

                $ext   = strtolower(pathinfo($filename !== '' ? $filename : $path, PATHINFO_EXTENSION));
                $isZip = ($ext === 'zip') || ($mime === 'application/zip') || str_ends_with(strtolower($path), '.zip');

                if (!$isZip) continue;

                $out[] = [
                    'id'       => (string)($r->id ?? ''),
                    'fecha'    => (string)substr((string)($r->created_at ?? ''), 0, 10),
                    'rfc'      => strtoupper((string)($r->rfc ?? '')),
                    'filename' => $filename !== '' ? $filename : basename($path),
                    'bytes'    => (int)($r->bytes ?? $r->size_bytes ?? 0),
                    'disk'     => (string)($r->disk ?? ''),
                    'path'     => $path,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('[VAULT] Error listando ZIPs de bóveda', [
                'cuenta_id' => $cuentaId,
                'error'     => $e->getMessage(),
            ]);
            return [];
        }
    }

    /* ==========================================================
     *   RFCs / Credenciales
     * ========================================================== */

    private function normalizeCreds($credList): array
    {
        $out = [];
        foreach ((array)$credList as $c) {
            $rfc   = is_array($c) ? ($c['rfc'] ?? null) : ($c->rfc ?? null);
            $razon = is_array($c) ? ($c['razon_social'] ?? null) : ($c->razon_social ?? null);

            if ($rfc) {
                $out[] = [
                    'rfc'          => strtoupper((string)$rfc),
                    'razon_social' => (string)($razon ?? '—'),
                    'validado'     => (bool)(is_array($c) ? ($c['validado'] ?? false) : ($c->validado ?? false)),
                ];
            }
        }
        return $out;
    }

    private function getAccountRfcs(string $cuentaId, ?int $clienteId = null, array $credNorm = []): array
    {
        $rfcs = collect($credNorm)
            ->map(fn($x) => strtoupper(trim((string)data_get($x, 'rfc', ''))))
            ->filter()
            ->unique()
            ->values()
            ->all();

        try {
            $schema = Schema::connection(self::CONN);

            if ($schema->hasTable('sat_credentials')) {
                $q = DB::connection(self::CONN)->table('sat_credentials');

                if ($schema->hasColumn('sat_credentials', 'cuenta_id') && $cuentaId !== '') {
                    $q->where('cuenta_id', $cuentaId);
                } elseif ($schema->hasColumn('sat_credentials', 'cliente_id') && $clienteId) {
                    $q->where('cliente_id', $clienteId);
                }

                $more = $q->pluck('rfc')
                    ->map(fn($x) => strtoupper(trim((string)$x)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $rfcs = array_values(array_unique(array_merge($rfcs, $more)));
            }
        } catch (\Throwable) {
            // no-op
        }

        return $rfcs;
    }

    /* ==========================================================
     *   DATOS FAKE (solo demo)
     * ========================================================== */

    private function fakeItems($credList): array
    {
        $rfcs = collect($this->normalizeCreds($credList))
            ->pluck('rfc')
            ->filter()
            ->values()
            ->all();

        if (empty($rfcs)) {
            $rfcs = ['XAXX010101000', 'COSC8001137NA'];
        }

        $razones = ['Demo, S.A. de C.V.', 'Ejemplo Comercial, S.A.', 'Servicios Prueba, S.C.'];
        $now     = now();

        $items = [];
        for ($i = 0; $i < 80; $i++) {
            $tipo  = ($i % 2 === 0) ? 'emitidos' : 'recibidos';
            $fecha = $now->copy()->subDays(rand(0, 150))->format('Y-m-d');
            $rfc   = $rfcs[array_rand($rfcs)];
            $razon = $razones[array_rand($razones)];
            $sub   = rand(5000, 350000) / 100;
            $iva   = round($sub * 0.16, 2);
            $total = round($sub + $iva, 2);
            $uuid  = strtoupper((string)Str::uuid());

            $items[] = [
                'kind'     => 'cfdi',
                'fecha'    => $fecha,
                'tipo'     => $tipo,
                'rfc'      => $rfc,
                'razon'    => $razon,
                'uuid'     => $uuid,
                'subtotal' => $sub,
                'iva'      => $iva,
                'total'    => $total,
            ];
        }

        return $items;
    }

    /* ==========================================================
     *   HELPERS INTERNOS
     * ========================================================== */

    private function currentAccount(): array
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        if (is_array($cuenta)) {
            $cuenta = (object)$cuenta;
        }

        $cuentaId  = (string)($cuenta->id ?? $cuenta->cuenta_id ?? '');
        $clienteId = null;

        if (isset($cuenta->cliente_id) && is_numeric($cuenta->cliente_id)) {
            $clienteId = (int)$cuenta->cliente_id;
        } elseif (isset($user->cliente_id) && is_numeric($user->cliente_id)) {
            $clienteId = (int)$user->cliente_id;
        }

        return [$user, $cuenta, $cuentaId, $clienteId];
    }

    private function currentAccount3(): array
    {
        $user   = Auth::guard('web')->user();
        $cuenta = $user?->cuenta;

        if (is_array($cuenta)) {
            $cuenta = (object)$cuenta;
        }

        $cuentaId = (string)($cuenta->id ?? $cuenta->cuenta_id ?? '');

        return [$user, $cuenta, $cuentaId];
    }

    private function bytesToGb(int $bytes): float
    {
        return $bytes > 0 ? ($bytes / 1024 / 1024 / 1024) : 0.0;
    }

    private function decodeMetaToArray($meta): ?array
    {
        if (is_array($meta)) return $meta;
        if (is_string($meta) && trim($meta) !== '') {
            $tmp = json_decode($meta, true);
            if (is_array($tmp)) return $tmp;
        }
        return null;
    }

    private function diskConfigured(string $disk): bool
    {
        $cfg = config("filesystems.disks.$disk");
        return is_array($cfg) && !empty($cfg['driver']);
    }

    private function diskExistsSafe(string $disk, string $path): bool
    {
        try {
            if (!$this->diskConfigured($disk)) {
                return false;
            }
            return Storage::disk($disk)->exists($path);
        } catch (\Throwable) {
            return false;
        }
    }

    /* ==========================================================
     *   EXPORT
     * ========================================================== */

    public function export(Request $request): StreamedResponse
    {
        [$user, , $cuentaId] = $this->currentAccount3();
        if (!$user || $cuentaId === '') {
            abort(401);
        }

        $tipo  = strtolower(trim((string)$request->query('tipo', '')));
        $rfc   = strtoupper(trim((string)$request->query('rfc', '')));
        $q     = strtoupper(trim((string)$request->query('q', '')));
        $desde = trim((string)$request->query('desde', ''));
        $hasta = trim((string)$request->query('hasta', ''));

        $min = $request->query('min', null);
        $max = $request->query('max', null);
        $min = ($min === null || $min === '') ? null : (float)$min;
        $max = ($max === null || $max === '') ? null : (float)$max;

        $schema = Schema::connection(self::CONN);
        abort_unless($schema->hasTable('sat_vault_cfdis'), 404);

        $qb = DB::connection(self::CONN)
            ->table('sat_vault_cfdis')
            ->where('cuenta_id', $cuentaId);

        if (in_array($tipo, ['emitidos', 'recibidos'], true) && $schema->hasColumn('sat_vault_cfdis', 'tipo')) {
            $qb->whereRaw('LOWER(COALESCE(tipo,"")) = ?', [$tipo]);
        }

        if ($rfc !== '') {
            $qb->where(function ($w) use ($schema, $rfc) {
                if ($schema->hasColumn('sat_vault_cfdis', 'rfc_emisor')) {
                    $w->orWhereRaw('UPPER(COALESCE(rfc_emisor,"")) = ?', [$rfc]);
                }
                if ($schema->hasColumn('sat_vault_cfdis', 'rfc_receptor')) {
                    $w->orWhereRaw('UPPER(COALESCE(rfc_receptor,"")) = ?', [$rfc]);
                }
                if ($schema->hasColumn('sat_vault_cfdis', 'rfc')) {
                    $w->orWhereRaw('UPPER(COALESCE(rfc,"")) = ?', [$rfc]);
                }
            });
        }

        if ($q !== '') {
            $qb->where(function ($w) use ($schema, $q) {
                if ($schema->hasColumn('sat_vault_cfdis', 'uuid')) {
                    $w->orWhereRaw('UPPER(COALESCE(uuid,"")) LIKE ?', ['%' . $q . '%']);
                }
                if ($schema->hasColumn('sat_vault_cfdis', 'razon_emisor')) {
                    $w->orWhereRaw('UPPER(COALESCE(razon_emisor,"")) LIKE ?', ['%' . $q . '%']);
                }
                if ($schema->hasColumn('sat_vault_cfdis', 'razon_receptor')) {
                    $w->orWhereRaw('UPPER(COALESCE(razon_receptor,"")) LIKE ?', ['%' . $q . '%']);
                }
            });
        }

        $fechaCol = $schema->hasColumn('sat_vault_cfdis', 'fecha_emision')
            ? 'fecha_emision'
            : ($schema->hasColumn('sat_vault_cfdis', 'fecha') ? 'fecha' : null);

        if ($fechaCol) {
            if ($desde !== '') $qb->whereDate($fechaCol, '>=', $desde);
            if ($hasta !== '') $qb->whereDate($fechaCol, '<=', $hasta);
        }

        if ($schema->hasColumn('sat_vault_cfdis', 'total')) {
            if ($min !== null) $qb->where('total', '>=', $min);
            if ($max !== null) $qb->where('total', '<=', $max);
        }

        $rows = $qb->orderByDesc('id')->limit(50000)->get();

        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setTitle('Boveda');

        $headers = ['Fecha', 'Tipo', 'RFC Emisor', 'RFC Receptor', 'Razón Emisor', 'Razón Receptor', 'UUID', 'Subtotal', 'IVA', 'Total'];
        $ws->fromArray($headers, null, 'A1');

        $i = 2;
        foreach ($rows as $r) {
            $fecha = '';
            if ($fechaCol) $fecha = (string)($r->{$fechaCol} ?? '');
            $tipoV = strtoupper((string)($r->tipo ?? ''));

            $rfcE  = strtoupper((string)($r->rfc_emisor ?? ''));
            $rfcR  = strtoupper((string)($r->rfc_receptor ?? ''));

            $razE  = (string)($r->razon_emisor ?? '—');
            $razR  = (string)($r->razon_receptor ?? '—');

            $uuid  = (string)($r->uuid ?? '');

            $sub   = (float)($r->subtotal ?? 0);
            $iva   = (float)($r->iva ?? 0);
            $tot   = (float)($r->total ?? 0);

            if ($tot <= 0 && ($sub > 0 || $iva > 0)) $tot = $sub + $iva;
            if ($iva <= 0 && $sub > 0 && $tot > $sub) $iva = round($tot - $sub, 2);

            $ws->fromArray([$fecha, $tipoV, $rfcE, $rfcR, $razE, $razR, $uuid, $sub, $iva, $tot], null, 'A' . $i);
            $i++;
        }

        $filename = 'boveda_cfdi_' . $cuentaId . '_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($sheet) {
            $writer = new Xlsx($sheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    } 

private function safeSize(string $disk, string $path): int
{
    try {
        $path = ltrim((string)$path, '/');
        if ($path === '' || !$this->diskConfigured($disk)) return 0;
        return (int) Storage::disk($disk)->size($path);
    } catch (\Throwable) {
        return 0;
    }
}

/**
 * Guarda bytes/peso/costo/zip_disk/zip_path en sat_downloads (solo si existen columnas).
 * Regresa costo calculado si se pudo (o null).
 */
private function syncSatDownloadMetricsFromZip(string $cuentaId, string $downloadId, string $disk, string $path, int $bytes): ?float
{
    $conn   = self::CONN;
    $db     = DB::connection($conn);
    $schema = Schema::connection($conn);

    if (!$schema->hasTable('sat_downloads')) return null;

    $disk = trim($disk) !== '' ? trim($disk) : 'private';
    $path = ltrim((string)$path, '/');

    $upd = ['updated_at' => now()];

    if ($schema->hasColumn('sat_downloads', 'zip_disk')) $upd['zip_disk'] = $disk;
    if ($schema->hasColumn('sat_downloads', 'zip_path')) $upd['zip_path'] = $path;

    if ($bytes > 0) {
        if ($schema->hasColumn('sat_downloads', 'bytes'))     $upd['bytes'] = $bytes;
        if ($schema->hasColumn('sat_downloads', 'size_bytes'))$upd['size_bytes'] = $bytes;

        $gb = round($this->bytesToGb($bytes), 4);

        if ($schema->hasColumn('sat_downloads', 'peso_gb')) $upd['peso_gb'] = $gb;
        if ($schema->hasColumn('sat_downloads', 'size_gb')) $upd['size_gb'] = $gb;
        if ($schema->hasColumn('sat_downloads', 'tam_gb'))  $upd['tam_gb']  = $gb;
    }

    $computedCost = null;

    // Costo: solo si existe columna costo y solo si no hay costo ya > 0
    if ($bytes > 0 && $schema->hasColumn('sat_downloads', 'costo')) {
        try {
            $current = (float)($db->table('sat_downloads')
                ->where('cuenta_id', $cuentaId)
                ->where('id', $downloadId)
                ->value('costo') ?? 0);

            if ($current <= 0) {
                $precioPorGb = (float) config('services.sat.precio_gb', 0);
                if ($precioPorGb > 0) {
                    $computedCost = round((round($this->bytesToGb($bytes), 4) * $precioPorGb), 2);
                    $upd['costo'] = $computedCost;
                }
            } else {
                $computedCost = $current;
            }
        } catch (\Throwable) {
            // no-op
        }
    }

    if (count($upd) > 1) {
        try {
            $db->table('sat_downloads')
                ->where('cuenta_id', $cuentaId)
                ->where('id', $downloadId)
                ->update($upd);
        } catch (\Throwable) {
            // no-op
        }
    }

    return $computedCost;
}

    /* ==========================================================
     *   IMPORTACIÓN MANUAL (ZIP / XML / CSV)
     * ========================================================== */

    public function importForm(Request $request)
    {
        return $this->redirectToVault();
    }

        public function importStore(Request $request)
    {
        [$user, , $cuentaId] = $this->currentAccount3();

        if (!$user || $cuentaId === '') {
            return $this->redirectToVault()->with('error', 'Cuenta inválida.');
        }

        $request->validate([
            'archivo'   => ['required_without:archivos', 'nullable', 'file', 'max:512000'],
            'archivos'  => ['required_without:archivo', 'nullable', 'array', 'min:1'],
            'archivos.*'=> ['file', 'max:512000'],
            'rfc'       => ['nullable', 'string', 'max:20'],
        ], [
            'archivo.required_without'   => 'Selecciona al menos un archivo ZIP, XML o CSV.',
            'archivos.required_without'  => 'Selecciona al menos un archivo ZIP, XML o CSV.',
            'archivos.array'             => 'El lote de archivos no es válido.',
            'archivos.min'               => 'Selecciona al menos un archivo.',
            'archivo.file'               => 'El archivo enviado no es válido.',
            'archivos.*.file'            => 'Uno de los archivos enviados no es válido.',
            'archivo.max'                => 'Uno de los archivos excede el tamaño permitido (500 MB).',
            'archivos.*.max'             => 'Uno de los archivos excede el tamaño permitido (500 MB).',
        ]);

        $files = [];

        if ($request->hasFile('archivos')) {
            $batch = $request->file('archivos');
            if (is_array($batch)) {
                foreach ($batch as $f) {
                    if ($f instanceof UploadedFile) {
                        $files[] = $f;
                    }
                }
            }
        }

        if (empty($files) && $request->hasFile('archivo')) {
            $single = $request->file('archivo');
            if ($single instanceof UploadedFile) {
                $files[] = $single;
            }
        }

        if (empty($files)) {
            return $this->redirectToVault()->with('error', 'No se pudo leer ningún archivo.');
        }

        $rfc = strtoupper(trim((string) (
            $request->input('rfc')
            ?: $request->input('rfc_select')
            ?: ''
        )));

        if ($rfc === '') {
            $rfc = 'XAXX010101000';
        }

        $disk = $this->resolveVaultDisk();
        $dir  = 'vault/' . $cuentaId . '/' . $rfc . '/manual/' . now()->format('Y/m');

        try {
            Storage::disk($disk)->makeDirectory($dir);
        } catch (\Throwable) {
            // no-op
        }

        $processedFiles = 0;
        $totalInserted  = 0;
        $detailMessages = [];

        foreach ($files as $file) {
            if (!$file->isValid()) {
                $detailMessages[] = 'Un archivo no se pudo leer correctamente.';
                continue;
            }

            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (!in_array($ext, ['zip', 'xml', 'csv'], true)) {
                $detailMessages[] = 'Archivo omitido por tipo no permitido: ' . $file->getClientOriginalName();
                continue;
            }

            $name       = $this->sanitizeUploadFilename((string) $file->getClientOriginalName());
            $storedName = now()->format('Ymd_His') . '_' . Str::random(8) . '_' . $name;
            $path       = $file->storeAs($dir, $storedName, $disk);

            if (!$path) {
                $detailMessages[] = 'No se pudo guardar: ' . $file->getClientOriginalName();
                continue;
            }

            $bytes = 0;
            try {
                $bytes = (int) Storage::disk($disk)->size($path);
            } catch (\Throwable) {
                $bytes = 0;
            }

            $vaultFileId = $this->createVaultFileRecord(
                $cuentaId,
                $rfc,
                $disk,
                $path,
                $file->getClientOriginalName(),
                (string) ($file->getMimeType() ?: ''),
                $bytes,
                'manual_upload'
            );

            $inserted = 0;

            try {
                if ($ext === 'zip') {
                    $inserted = $this->importZipIntoCfdis($disk, $path, $cuentaId, $rfc, $vaultFileId);
                    $detailMessages[] = $file->getClientOriginalName() . ': ZIP importado, CFDI detectados ' . $inserted . '.';
                } elseif ($ext === 'xml') {
                    $xml = (string) Storage::disk($disk)->get($path);
                    $inserted = $this->importXmlStringIntoCfdis($xml, $cuentaId, $rfc, $vaultFileId, $path);
                    $detailMessages[] = $file->getClientOriginalName() . ': XML importado, CFDI detectados ' . $inserted . '.';
                } elseif ($ext === 'csv') {
                    $inserted = $this->importCsvIntoCfdis($disk, $path, $cuentaId, $rfc, $vaultFileId);
                    $detailMessages[] = $file->getClientOriginalName() . ': CSV indexado, CFDI detectados ' . $inserted . '.';
                }

                $processedFiles++;
                $totalInserted += (int) $inserted;
            } catch (\Throwable $e) {
                Log::error('[VAULT:importStore] Error importando archivo', [
                    'cuenta_id' => $cuentaId,
                    'disk'      => $disk,
                    'path'      => $path,
                    'ext'       => $ext,
                    'filename'  => $file->getClientOriginalName(),
                    'error'     => $e->getMessage(),
                ]);

                $detailMessages[] = $file->getClientOriginalName() . ': se guardó, pero no se pudo procesar (' . $e->getMessage() . ').';
            }
        }

        if ($processedFiles <= 0) {
            return $this->redirectToVault()->with('error', implode(' ', $detailMessages));
        }

        $message = 'Proceso completado. Archivos cargados: ' . $processedFiles . '. CFDI importados/indexados: ' . $totalInserted . '.';

        if (!empty($detailMessages)) {
            $message .= ' ' . implode(' ', $detailMessages);
        }

        return $this->redirectToVault()->with('success', $message);
    }

    public function downloadXml(Request $request)
    {
        [$user, , $cuentaId] = $this->currentAccount3();

        if (!$user || $cuentaId === '') {
            abort(401);
        }

        $uuid = strtoupper(trim((string) $request->query('uuid', '')));
        if ($uuid === '') {
            abort(422, 'UUID requerido.');
        }

        $row = DB::connection(self::CONN)
            ->table('sat_vault_cfdis')
            ->where('cuenta_id', $cuentaId)
            ->where('uuid', $uuid)
            ->first();

        if (!$row) {
            abort(404, 'CFDI no encontrado.');
        }

        $xmlPath = (string) ($row->xml_path ?? '');
        if ($xmlPath === '') {
            abort(404, 'XML no disponible para este CFDI.');
        }

        [$disk, $path, $entry] = $this->resolveVaultStoredPathForCfdi($row, $xmlPath);

        if ($entry !== null) {
            $xml = $this->readZipEntry($disk, $path, $entry);
            if ($xml === null) {
                abort(404, 'No se pudo extraer el XML desde el ZIP.');
            }

            $filename = $uuid . '.xml';

            return response($xml, 200, [
                'Content-Type'        => 'application/xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        if (!$this->diskExistsSafe($disk, $path)) {
            abort(404, 'XML no encontrado en storage.');
        }

        return Storage::disk($disk)->download($path, $uuid . '.xml', [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function downloadPdf(Request $request)
    {
        [$user, , $cuentaId] = $this->currentAccount3();

        if (!$user || $cuentaId === '') {
            abort(401);
        }

        $uuid = strtoupper(trim((string) $request->query('uuid', '')));
        if ($uuid === '') {
            abort(422, 'UUID requerido.');
        }

        $schema = Schema::connection(self::CONN);
        if (!$schema->hasTable('sat_vault_cfdis')) {
            abort(404, 'Tabla sat_vault_cfdis no existe.');
        }

        $row = DB::connection(self::CONN)
            ->table('sat_vault_cfdis')
            ->where('cuenta_id', $cuentaId)
            ->where('uuid', $uuid)
            ->first();

        if (!$row) {
            abort(404, 'CFDI no encontrado.');
        }

        $pdfPath = '';
        if ($schema->hasColumn('sat_vault_cfdis', 'pdf_path')) {
            $pdfPath = (string) ($row->pdf_path ?? '');
        }

        if ($pdfPath === '') {
            abort(404, 'PDF no disponible para este CFDI.');
        }

        [$disk, $path, $entry] = $this->resolveVaultStoredPathForCfdi($row, $pdfPath);

        if ($entry !== null) {
            $pdf = $this->readZipEntry($disk, $path, $entry);
            if ($pdf === null) {
                abort(404, 'No se pudo extraer el PDF desde el ZIP.');
            }

            $filename = $uuid . '.pdf';

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        if (!$this->diskExistsSafe($disk, $path)) {
            abort(404, 'PDF no encontrado en storage.');
        }

        return Storage::disk($disk)->download($path, $uuid . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadZip(Request $request)
    {
        [$user, , $cuentaId] = $this->currentAccount3();

        if (!$user || $cuentaId === '') {
            abort(401);
        }

        $uuid = strtoupper(trim((string) $request->query('uuid', '')));
        if ($uuid === '') {
            abort(422, 'UUID requerido.');
        }

        $schema = Schema::connection(self::CONN);
        if (!$schema->hasTable('sat_vault_cfdis')) {
            abort(404, 'Tabla sat_vault_cfdis no existe.');
        }

        $row = DB::connection(self::CONN)
            ->table('sat_vault_cfdis')
            ->where('cuenta_id', $cuentaId)
            ->where('uuid', $uuid)
            ->first();

        if (!$row) {
            abort(404, 'CFDI no encontrado.');
        }

        $vaultFileId = (int) ($row->vault_file_id ?? 0);
        if ($vaultFileId <= 0) {
            abort(404, 'ZIP origen no disponible para este CFDI.');
        }

        return $this->downloadVaultFile($request, (string) $vaultFileId);
    }

    private function resolveVaultDisk(): string
    {
        if ($this->diskConfigured('sat_vault')) return 'sat_vault';
        if ($this->diskConfigured('vault')) return 'vault';
        if ($this->diskConfigured('private')) return 'private';
        return (string) config('filesystems.default', 'local');
    }

    private function sanitizeUploadFilename(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'archivo.bin';
        }

        $name = preg_replace('/[^A-Za-z0-9\.\-_]+/', '_', $name) ?: 'archivo.bin';
        return ltrim($name, '._');
    }

    private function createVaultFileRecord(
        string $cuentaId,
        string $rfc,
        string $disk,
        string $path,
        string $originalName,
        string $mime,
        int $bytes,
        string $source = 'manual_upload'
    ): int {
        $schema = Schema::connection(self::CONN);

        if (!$schema->hasTable('sat_vault_files')) {
            return 0;
        }

        $safeOriginalName = trim($originalName) !== ''
            ? $this->sanitizeUploadFilename($originalName)
            : basename($path);

        $data = [
            'cuenta_id'  => $cuentaId,
            'rfc'        => strtoupper(trim($rfc)),
            'filename'   => $safeOriginalName,
            'path'       => $path,
            'disk'       => $disk,
            'bytes'      => max(0, $bytes),
            'updated_at' => now(),
        ];

        if ($schema->hasColumn('sat_vault_files', 'source')) {
            $data['source'] = $source;
        }

        if ($schema->hasColumn('sat_vault_files', 'mime')) {
            $data['mime'] = $mime !== '' ? $mime : $this->guessMimeByExtension($path);
        }

        if ($schema->hasColumn('sat_vault_files', 'size_bytes')) {
            $data['size_bytes'] = max(0, $bytes);
        }

        if ($schema->hasColumn('sat_vault_files', 'original_name')) {
            $data['original_name'] = $safeOriginalName;
        }

        $data['created_at'] = now();

        try {
            return (int) DB::connection(self::CONN)
                ->table('sat_vault_files')
                ->insertGetId($data);
        } catch (\Throwable $e) {
            Log::warning('[VAULT:createVaultFileRecord] No se pudo insertar sat_vault_files', [
                'cuenta_id' => $cuentaId,
                'path'      => $path,
                'error'     => $e->getMessage(),
            ]);

            return 0;
        }
    }

    private function guessMimeByExtension(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'zip' => 'application/zip',
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    private function importXmlStringIntoCfdis(
        string $xml,
        string $cuentaId,
        string $ownerRfc = '',
        int $vaultFileId = 0,
        ?string $xmlRefPath = null
    ): int {
        $schema = Schema::connection(self::CONN);

        if (!$schema->hasTable('sat_vault_cfdis')) {
            return 0;
        }

        $has = function (string $col) use ($schema): bool {
            try {
                return $schema->hasColumn('sat_vault_cfdis', $col);
            } catch (\Throwable) {
                return false;
            }
        };

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xpath->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
        $xpath->registerNamespace('cfdi33', 'http://www.sat.gob.mx/cfd/3');

        $comprobante = $xpath->query('//cfdi:Comprobante')->item(0);
        if (!$comprobante) $comprobante = $xpath->query('//cfdi33:Comprobante')->item(0);

        $fecha = '';
        $subtotal = 0.0;
        $total = 0.0;
        $tipoDeComprobante = '';
        $moneda = '';
        $formaPago = '';
        $metodoPago = '';

        if ($comprobante) {
            $fecha             = (string) ($comprobante->getAttribute('Fecha') ?: $comprobante->getAttribute('fecha'));
            $subtotal          = (float) ($comprobante->getAttribute('SubTotal') ?: $comprobante->getAttribute('subTotal') ?: 0);
            $total             = (float) ($comprobante->getAttribute('Total') ?: $comprobante->getAttribute('total') ?: 0);
            $tipoDeComprobante = (string) ($comprobante->getAttribute('TipoDeComprobante') ?: '');
            $moneda            = (string) ($comprobante->getAttribute('Moneda') ?: '');
            $formaPago         = (string) ($comprobante->getAttribute('FormaPago') ?: '');
            $metodoPago        = (string) ($comprobante->getAttribute('MetodoPago') ?: '');
        }

        $emisor = $xpath->query('//cfdi:Emisor')->item(0);
        if (!$emisor) $emisor = $xpath->query('//cfdi33:Emisor')->item(0);

        $receptor = $xpath->query('//cfdi:Receptor')->item(0);
        if (!$receptor) $receptor = $xpath->query('//cfdi33:Receptor')->item(0);

        $tfd = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);

        $uuid = '';
        if ($tfd) {
            $uuid = (string) ($tfd->getAttribute('UUID') ?: $tfd->getAttribute('Uuid'));
        }

        $uuid = strtoupper(trim($uuid));
        if ($uuid === '') {
            return 0;
        }

        $exists = DB::connection(self::CONN)
            ->table('sat_vault_cfdis')
            ->where('cuenta_id', $cuentaId)
            ->where('uuid', $uuid)
            ->exists();

        if ($exists) {
            return 0;
        }

        $emisorRfc    = strtoupper(trim((string) ($emisor?->getAttribute('Rfc') ?: $emisor?->getAttribute('rfc') ?: '')));
        $receptorRfc  = strtoupper(trim((string) ($receptor?->getAttribute('Rfc') ?: $receptor?->getAttribute('rfc') ?: '')));
        $emisorNombre = trim((string) ($emisor?->getAttribute('Nombre') ?: $emisor?->getAttribute('nombre') ?: ''));
        $receptorNombre = trim((string) ($receptor?->getAttribute('Nombre') ?: $receptor?->getAttribute('nombre') ?: ''));

        $iva = 0.0;
        if ($subtotal > 0 && $total > $subtotal) {
            $iva = round($total - $subtotal, 2);
        }

        $ownerRfc = strtoupper(trim($ownerRfc));

        $tipoVault = null;

        // 1) Caso ideal: inferir por RFC dueño de la cuenta
        if ($ownerRfc !== '') {
            if ($receptorRfc !== '' && $receptorRfc === $ownerRfc) {
                $tipoVault = 'recibidos';
            } elseif ($emisorRfc !== '' && $emisorRfc === $ownerRfc) {
                $tipoVault = 'emitidos';
            }
        }

        // 2) Fallback por tipo de comprobante / heurística segura
        //    Nunca dejar tipo null porque la tabla lo exige.
        if ($tipoVault === null) {
            $tipoComprobanteUpper = strtoupper(trim($tipoDeComprobante));

            // Ingresos / nómina / traslado suelen ser emitidos desde la cuenta origen
            if (in_array($tipoComprobanteUpper, ['I', 'N', 'T'], true)) {
                $tipoVault = 'emitidos';
            }
            // Egreso / pago suelen ser recibidos con más frecuencia en análisis de bóveda
            elseif (in_array($tipoComprobanteUpper, ['E', 'P'], true)) {
                $tipoVault = 'recibidos';
            }
            // Si no hay forma confiable de inferirlo, dejamos un default estable
            else {
                $tipoVault = 'emitidos';
            }
        }

        $payload = [
            'cuenta_id'  => $cuentaId,
            'uuid'       => $uuid,
            'subtotal'   => $subtotal,
            'iva'        => $iva,
            'total'      => $total,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($has('fecha_emision')) {
            $payload['fecha_emision'] = $fecha !== '' ? substr($fecha, 0, 10) : null;
        } elseif ($has('fecha')) {
            $payload['fecha'] = $fecha !== '' ? $fecha : null;
        }

        if ($has('tipo')) $payload['tipo'] = $tipoVault ?: 'emitidos';
        if ($has('rfc_emisor')) $payload['rfc_emisor'] = $emisorRfc !== '' ? $emisorRfc : null;
        if ($has('rfc_receptor')) $payload['rfc_receptor'] = $receptorRfc !== '' ? $receptorRfc : null;
        if ($has('razon_emisor')) $payload['razon_emisor'] = $emisorNombre !== '' ? $emisorNombre : null;
        if ($has('razon_receptor')) $payload['razon_receptor'] = $receptorNombre !== '' ? $receptorNombre : null;
        if ($has('tipo_comprobante')) $payload['tipo_comprobante'] = $tipoDeComprobante !== '' ? $tipoDeComprobante : null;
        if ($has('moneda')) $payload['moneda'] = $moneda !== '' ? $moneda : null;
        if ($has('forma_pago')) $payload['forma_pago'] = $formaPago !== '' ? $formaPago : null;
        if ($has('metodo_pago')) $payload['metodo_pago'] = $metodoPago !== '' ? $metodoPago : null;
        if ($has('vault_file_id') && $vaultFileId > 0) $payload['vault_file_id'] = $vaultFileId;
        if ($has('xml_path') && $xmlRefPath !== null && $xmlRefPath !== '') $payload['xml_path'] = $xmlRefPath;

        DB::connection(self::CONN)->table('sat_vault_cfdis')->insert($payload);

        return 1;
    }

    private function importCsvIntoCfdis(
        string $disk,
        string $path,
        string $cuentaId,
        string $ownerRfc = '',
        int $vaultFileId = 0
    ): int {
        if (!$this->diskExistsSafe($disk, $path)) {
            return 0;
        }

        $abs = Storage::disk($disk)->path($path);
        if (!is_file($abs)) {
            return 0;
        }

        $fp = fopen($abs, 'rb');
        if (!$fp) {
            return 0;
        }

        $firstLine = fgets($fp);
        if ($firstLine === false) {
            fclose($fp);
            return 0;
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', (string) $firstLine);

        $delimiter = ',';
        $commaCount = substr_count($firstLine, ',');
        $semiCount  = substr_count($firstLine, ';');
        $tabCount   = substr_count($firstLine, "\t");

        if ($semiCount > $commaCount && $semiCount >= $tabCount) {
            $delimiter = ';';
        } elseif ($tabCount > $commaCount && $tabCount > $semiCount) {
            $delimiter = "\t";
        }

        rewind($fp);

        $headers = fgetcsv($fp, 0, $delimiter);
        if (!$headers || !is_array($headers)) {
            fclose($fp);
            return 0;
        }

        $headers = array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $h));
            $h = mb_strtolower($h, 'UTF-8');
            $h = str_replace(['á','é','í','ó','ú','ñ',' '], ['a','e','i','o','u','n','_'], $h);
            return preg_replace('/[^a-z0-9_\.]/', '', $h);
        }, $headers);

        $schema = Schema::connection(self::CONN);
        $has = function (string $col) use ($schema): bool {
            try {
                return $schema->hasColumn('sat_vault_cfdis', $col);
            } catch (\Throwable) {
                return false;
            }
        };

        $ownerRfcU = strtoupper(trim($ownerRfc));
        $inserted  = 0;

        while (($row = fgetcsv($fp, 0, $delimiter)) !== false) {
            if (!is_array($row) || count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $idx => $key) {
                $assoc[$key] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
            }

            $uuid = strtoupper(trim((string) (
                $assoc['uuid'] ??
                $assoc['folio_fiscal'] ??
                $assoc['uuid_cfdi'] ??
                ''
            )));

            if ($uuid === '') {
                continue;
            }

            $exists = DB::connection(self::CONN)
                ->table('sat_vault_cfdis')
                ->where('cuenta_id', $cuentaId)
                ->where('uuid', $uuid)
                ->exists();

            if ($exists) {
                continue;
            }

            $fecha = (string) (
                $assoc['fecha'] ??
                $assoc['fecha_emision'] ??
                $assoc['timbre_fiscal_digital_fecha_timbrado'] ??
                $assoc['fechacomprobante'] ??
                ''
            );

            $rfcEmisor = strtoupper(trim((string) (
                $assoc['rfc_emisor'] ??
                $assoc['emisor_rfc'] ??
                $assoc['rfcemisor'] ??
                ''
            )));

            $rfcReceptor = strtoupper(trim((string) (
                $assoc['rfc_receptor'] ??
                $assoc['receptor_rfc'] ??
                $assoc['rfcreceptor'] ??
                ''
            )));

            $razonEmisor = trim((string) (
                $assoc['razon_emisor'] ??
                $assoc['nombre_emisor'] ??
                $assoc['emisor_nombre'] ??
                $assoc['razon_social_emisor'] ??
                ''
            ));

            $razonReceptor = trim((string) (
                $assoc['razon_receptor'] ??
                $assoc['nombre_receptor'] ??
                $assoc['receptor_nombre'] ??
                $assoc['razon_social_receptor'] ??
                ''
            ));

            $subtotal = (float) (
                $assoc['subtotal'] ??
                $assoc['sub_total'] ??
                0
            );

            $traslados = (float) (
                $assoc['iva'] ??
                $assoc['total_traslados'] ??
                $assoc['impuestos_trasladados'] ??
                0
            );

            $retenidos = (float) (
                $assoc['total_retenidos'] ??
                0
            );

            $total = (float) (
                $assoc['total'] ??
                $assoc['monto_total'] ??
                0
            );

            if ($total <= 0) {
                $total = round($subtotal + $traslados - $retenidos, 2);
            }

            $iva = $traslados;
            if ($iva <= 0 && $subtotal > 0 && $total > $subtotal) {
                $iva = round($total - $subtotal, 2);
            }

            $tipoComprobante = strtoupper(trim((string) (
                $assoc['tipo'] ??
                $assoc['tipo_de_comprobante'] ??
                $assoc['tipodecomprobante'] ??
                ''
            )));

            $tipoVault = null;

            if ($ownerRfcU !== '') {
                if ($rfcReceptor !== '' && $rfcReceptor === $ownerRfcU) {
                    $tipoVault = 'recibidos';
                } elseif ($rfcEmisor !== '' && $rfcEmisor === $ownerRfcU) {
                    $tipoVault = 'emitidos';
                }
            }

            if ($tipoVault === null) {
                if (in_array($tipoComprobante, ['I', 'N', 'T'], true)) {
                    $tipoVault = 'emitidos';
                } elseif (in_array($tipoComprobante, ['E', 'P'], true)) {
                    $tipoVault = 'recibidos';
                } else {
                    $tipoVault = 'emitidos';
                }
            }

            $payload = [
                'cuenta_id'  => $cuentaId,
                'uuid'       => $uuid,
                'subtotal'   => $subtotal,
                'iva'        => $iva,
                'total'      => $total,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($has('fecha_emision')) {
                $payload['fecha_emision'] = $fecha !== '' ? substr($fecha, 0, 10) : null;
            } elseif ($has('fecha')) {
                $payload['fecha'] = $fecha !== '' ? substr($fecha, 0, 19) : null;
            }

            if ($has('tipo')) $payload['tipo'] = $tipoVault ?: 'emitidos';
            if ($has('rfc_emisor')) $payload['rfc_emisor'] = $rfcEmisor !== '' ? $rfcEmisor : null;
            if ($has('rfc_receptor')) $payload['rfc_receptor'] = $rfcReceptor !== '' ? $rfcReceptor : null;
            if ($has('razon_emisor')) $payload['razon_emisor'] = $razonEmisor !== '' ? $razonEmisor : null;
            if ($has('razon_receptor')) $payload['razon_receptor'] = $razonReceptor !== '' ? $razonReceptor : null;
            if ($has('tipo_comprobante')) $payload['tipo_comprobante'] = $tipoComprobante !== '' ? $tipoComprobante : null;
            if ($has('vault_file_id') && $vaultFileId > 0) $payload['vault_file_id'] = $vaultFileId;
            if ($has('xml_path')) $payload['xml_path'] = $path;

            DB::connection(self::CONN)->table('sat_vault_cfdis')->insert($payload);
            $inserted++;
        }

        fclose($fp);

        return $inserted;
    }

    private function resolveVaultStoredPathForCfdi(object $row, string $stored): array
    {
        $disk = 'private';
        $path = ltrim($stored, '/');
        $entry = null;

        $vaultFileId = (int) ($row->vault_file_id ?? 0);

        if (str_contains($path, '#')) {
            [$path, $entry] = explode('#', $path, 2);
        }

        if ($vaultFileId > 0 && Schema::connection(self::CONN)->hasTable('sat_vault_files')) {
            $vf = DB::connection(self::CONN)
                ->table('sat_vault_files')
                ->where('id', $vaultFileId)
                ->first();

            if ($vf) {
                $disk = (string) ($vf->disk ?? 'private');
                if (str_contains($stored, '#')) {
                    $path = ltrim((string) ($vf->path ?? ''), '/');
                } elseif ($path === '') {
                    $path = ltrim((string) ($vf->path ?? ''), '/');
                }
            }
        }

        return [$disk, $path, $entry];
    }

    private function readZipEntry(string $disk, string $path, string $entry): ?string
    {
        if ($path === '' || $entry === '' || !$this->diskExistsSafe($disk, $path)) {
            return null;
        }

        try {
            $abs = Storage::disk($disk)->path($path);
            $zip = new \ZipArchive();
            $ok = $zip->open($abs);

            if ($ok !== true) {
                return null;
            }

            $content = $zip->getFromName($entry);
            $zip->close();

            return $content !== false ? (string) $content : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildUploadedFileRows(string $cuentaId): array
    {
        try {
            $conn  = self::CONN;
            $table = (new VaultFile())->getTable() ?: 'sat_vault_files';

            if (!Schema::connection($conn)->hasTable($table)) {
                $table = 'sat_vault_files';
            }

            if (!Schema::connection($conn)->hasTable($table)) {
                return [];
            }

            $rows = DB::connection($conn)->table($table)
                ->where('cuenta_id', $cuentaId)
                ->orderByDesc('id')
                ->limit(500)
                ->get();

            $out = [];

            foreach ($rows as $r) {
                $path         = (string)($r->path ?? '');
                $storedName   = (string)($r->filename ?? basename($path));
                $originalName = (string)($r->original_name ?? '');
                $filename     = trim($originalName) !== '' ? $originalName : $storedName;
                $mime         = strtolower((string)($r->mime ?? ''));

                $ext = strtolower(pathinfo($filename !== '' ? $filename : $path, PATHINFO_EXTENSION));

                if ($ext === '' && $mime !== '') {
                    $ext = match ($mime) {
                        'application/zip' => 'zip',
                        'application/xml', 'text/xml' => 'xml',
                        'text/csv', 'application/csv', 'application/vnd.ms-excel' => 'csv',
                        default => '',
                    };
                }

                $tipo = match ($ext) {
                    'zip' => 'ZIP',
                    'xml' => 'XML',
                    'csv' => 'CSV',
                    default => strtoupper($ext ?: 'ARCHIVO'),
                };

                $out[] = [
                    'id'            => (string)($r->id ?? ''),
                    'fecha'         => (string)substr((string)($r->created_at ?? ''), 0, 10),
                    'rfc'           => strtoupper((string)($r->rfc ?? '')),
                    'tipo_archivo'  => $tipo,
                    'extension'     => $ext,
                    'filename'      => $filename !== '' ? $filename : 'Archivo',
                    'stored_name'   => $storedName,
                    'bytes'         => (int)($r->bytes ?? $r->size_bytes ?? 0),
                    'mime'          => $mime,
                    'disk'          => (string)($r->disk ?? ''),
                    'path'          => $path,
                    'source'        => (string)($r->source ?? ''),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('[VAULT] Error listando archivos subidos de bóveda', [
                'cuenta_id' => $cuentaId,
                'error'     => $e->getMessage(),
            ]);

            return [];
        }
    }

}
