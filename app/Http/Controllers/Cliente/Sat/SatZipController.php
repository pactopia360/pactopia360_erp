<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SatZipController extends Controller
{
    private const CONN = 'mysql_clientes';

    /**
     * Descarga ZIP por downloadId (id o download_id), SOLO si pertenece a la cuenta del usuario.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
     */
    public function download(Request $request, string $downloadId): StreamedResponse|Response
    {
        $rid      = (string) ($request->header('X-Request-Id') ?: $request->headers->get('X-Correlation-Id') ?: uniqid('zip_', true));
        $user     = $request->user();
        $cuentaId = $this->resolveCuentaId($user);

        Log::info('[SAT_ZIP] start', [
            'rid'        => $rid,
            'downloadId' => $downloadId,
            'user_id'    => $user?->id ?? null,
            'cuenta_id'  => $cuentaId ?: null,
            'path'       => $request->path(),
        ]);

        if ($cuentaId === '') {
            Log::warning('[SAT_ZIP] forbidden: empty cuenta_id', ['rid' => $rid]);
            abort(403, 'Cuenta no válida.');
        }

        $conn = self::CONN;
        $db   = DB::connection($conn);
        $sch  = Schema::connection($conn);

        if (!$sch->hasTable('sat_downloads')) {
            Log::error('[SAT_ZIP] missing table sat_downloads', ['rid' => $rid, 'conn' => $conn]);
            abort(500, 'Tabla sat_downloads no existe.');
        }

        // ==========================================================
        // 0) Resolver descarga por id OR download_id (si aplica)
        // ==========================================================
        $download = $db->table('sat_downloads')
            ->where('cuenta_id', $cuentaId)
            ->where(function ($q) use ($downloadId, $sch) {
                $q->where('id', $downloadId);
                if ($sch->hasColumn('sat_downloads', 'download_id')) {
                    $q->orWhere('download_id', $downloadId);
                }
            })
            ->orderByDesc('created_at')
            ->first();

        if (!$download) {
            Log::warning('[SAT_ZIP] not found', ['rid' => $rid, 'downloadId' => $downloadId, 'cuenta_id' => $cuentaId]);
            abort(404, 'Descarga no encontrada.');
        }

        $downloadRowId = (string) ($download->id ?? $downloadId);
        $rfc           = strtoupper(trim((string) ($download->rfc ?? '')));
        $statusRaw     = (string) ($download->status ?? '');
        $statusNorm    = $this->statusNormalized($statusRaw);

        Log::info('[SAT_ZIP] download resolved', [
            'rid'          => $rid,
            'row_id'       => $downloadRowId,
            'status_raw'   => $statusRaw ?: null,
            'status_norm'  => $statusNorm ?: null,
            'zip_disk'     => $download->zip_disk ?? null,
            'zip_path'     => $download->zip_path ?? null,
            'vault_path'   => $download->vault_path ?? null,
            'request_id'   => $download->request_id ?? null,
            'package_id'   => $download->package_id ?? null,
            'download_id'  => $download->download_id ?? null,
            'rfc'          => $rfc ?: null,
        ]);

        // Opcional (recomendado): si NO está listo, corta con 409 (pero devuelve JSON con debug)
        if (!$this->isReadyStatus($statusNorm)) {
            Log::notice('[SAT_ZIP] not ready status', ['rid' => $rid, 'status' => $statusRaw, 'norm' => $statusNorm]);
            return response()->json([
                'ok'          => false,
                'message'     => 'ZIP aún no disponible (status no listo).',
                'download_id' => $downloadRowId,
                'status'      => $statusRaw ?: null,
                'status_norm' => $statusNorm ?: null,
                'zip_disk'    => $download->zip_disk ?? null,
                'zip_path'    => $download->zip_path ?? null,
                'vault_path'  => $download->vault_path ?? null,
            ], 409);
        }

        // ==========================================================
        // 1) PRIORIDAD: ZIP ya en bóveda por source_id exacto
        // ==========================================================
        if ($sch->hasTable('sat_vault_files')) {
            $sourceIds = $this->candidateSourceIds($download, $sch);

            $vaultZip = $db->table('sat_vault_files')
                ->where('cuenta_id', $cuentaId)
                ->where('source', 'sat_download')
                ->whereIn('source_id', $sourceIds)
                ->where(function ($w) {
                    $w->where('mime', 'application/zip')
                        ->orWhere('filename', 'like', '%.zip')
                        ->orWhere('path', 'like', '%.zip');
                })
                ->orderByDesc('id')
                ->first();

            if ($vaultZip) {
                Log::info('[SAT_ZIP] found vaultZip by source_id', ['rid' => $rid, 'vault_id' => $vaultZip->id ?? null]);
                $resp = $this->tryDownloadVaultZipAndSync($rid, $cuentaId, $downloadRowId, $download, $vaultZip);
                if ($resp) return $resp;
            }
        }

        // ==========================================================
        // 2) FALLBACK: zip_path / vault_path en sat_downloads
        //     - ✅ intenta primero zip_disk si viene poblado
        // ==========================================================
        $pathsToTry = [];

        $zipPath = ltrim((string) ($download->zip_path ?? ''), '/');
        if ($zipPath !== '') $pathsToTry[] = $zipPath;

        $vaultPath = ltrim((string) ($download->vault_path ?? ''), '/');
        if ($vaultPath !== '' && $vaultPath !== $zipPath) $pathsToTry[] = $vaultPath;

        $preferDisk = trim((string) ($download->zip_disk ?? ''));

        foreach ($pathsToTry as $p) {
            $disks = $this->candidateDisksForPath($p);

            // ✅ prioridad: si ya viene zip_disk, va primero
            if ($preferDisk !== '') {
                array_unshift($disks, $preferDisk);
                $disks = array_values(array_unique($disks));
            }

            foreach ($disks as $disk) {
                try {
                    if (!$this->diskConfigured($disk)) continue;

                    if (Storage::disk($disk)->exists($p)) {
                        $bytes = $this->safeSize($disk, $p);

                        Log::info('[SAT_ZIP] found by sat_downloads path', [
                            'rid'   => $rid,
                            'disk'  => $disk,
                            'path'  => $p,
                            'bytes' => $bytes,
                        ]);

                        $this->syncDownloadMetricsFromFoundZip(
                            $cuentaId,
                            $downloadRowId,
                            $disk,
                            $p,
                            $bytes,
                            0,
                            null
                        );

                        return Storage::disk($disk)->download($p, basename($p));
                    }
                } catch (\Throwable $e) {
                    Log::warning('[SAT_ZIP] disk/path check error', [
                        'rid'   => $rid,
                        'disk'  => $disk,
                        'path'  => $p,
                        'err'   => $e->getMessage(),
                    ]);
                }
            }
        }

        // ==========================================================
        // 3) FALLBACK INTELIGENTE: buscar ZIP por RFC en sat_vault_files
        // ==========================================================
        if ($sch->hasTable('sat_vault_files') && $rfc !== '') {
            $vaultZipGuess = $db->table('sat_vault_files')
                ->where('cuenta_id', $cuentaId)
                ->where('source', 'sat_download')
                ->where(function ($w) {
                    $w->where('mime', 'application/zip')
                        ->orWhere('filename', 'like', '%.zip')
                        ->orWhere('path', 'like', '%.zip');
                })
                ->where(function ($w) use ($rfc) {
                    $w->where('filename', 'like', "%{$rfc}%")
                        ->orWhere('path', 'like', "%/{$rfc}%")
                        ->orWhere('path', 'like', "%{$rfc}%");
                })
                ->orderByDesc('id')
                ->first();

            if ($vaultZipGuess) {
                Log::info('[SAT_ZIP] found vaultZip guess by RFC', ['rid' => $rid, 'vault_id' => $vaultZipGuess->id ?? null]);
                $resp = $this->tryDownloadVaultZipAndSync($rid, $cuentaId, $downloadRowId, $download, $vaultZipGuess);
                if ($resp) return $resp;
            }
        }

        // ==========================================================
        // 4) NO DISPONIBLE
        // ==========================================================
        Log::notice('[SAT_ZIP] not available', [
            'rid'        => $rid,
            'downloadId' => $downloadId,
            'row_id'     => $downloadRowId,
            'status'     => $statusRaw ?: null,
            'zip_disk'   => $download->zip_disk ?? null,
            'zip_path'   => $download->zip_path ?? null,
            'vault_path' => $download->vault_path ?? null,
        ]);

        return response()->json([
            'ok'          => false,
            'message'     => 'ZIP aún no disponible para esta descarga (no se encontró vínculo a bóveda ni paths).',
            'download_id' => $downloadRowId,
            'status'      => $statusRaw ?: null,
            'status_norm' => $statusNorm ?: null,
            'zip_path'    => $download->zip_path ?? null,
            'vault_path'  => $download->vault_path ?? null,
            'zip_disk'    => $download->zip_disk ?? null,
            'rfc'         => $rfc ?: null,
        ], 409);
    }

    /* ==========================================================
     * Descarga desde sat_vault_files y sincroniza sat_downloads
     * ========================================================== */

    private function tryDownloadVaultZipAndSync(string $rid, string $cuentaId, string $downloadRowId, object $download, object $vaultZip): ?StreamedResponse
    {
        $disk = (string) ($vaultZip->disk ?? 'sat_vault');
        $path = ltrim((string) ($vaultZip->path ?? ''), '/');

        if ($path === '' || !$this->diskConfigured($disk)) {
            Log::warning('[SAT_ZIP] vault invalid disk/path', ['rid' => $rid, 'disk' => $disk, 'path' => $path ?: null]);
            return null;
        }

        if (!Storage::disk($disk)->exists($path)) {
            Log::warning('[SAT_ZIP] vault file not exists', ['rid' => $rid, 'disk' => $disk, 'path' => $path]);
            return null;
        }

        $bytes = $this->safeSize($disk, $path);

        $this->syncDownloadMetricsFromFoundZip(
            $cuentaId,
            $downloadRowId,
            $disk,
            $path,
            $bytes,
            (int) ($vaultZip->id ?? 0),
            (string) ($vaultZip->source_id ?? '')
        );

        $filename = (string) ($vaultZip->filename ?: basename($path));

        Log::info('[SAT_ZIP] download from vault', [
            'rid'      => $rid,
            'disk'     => $disk,
            'path'     => $path,
            'filename' => $filename,
            'bytes'    => $bytes,
        ]);

        return Storage::disk($disk)->download($path, $filename);
    }

    /* ==========================================================
     * Helpers
     * ========================================================== */

    private function resolveCuentaId($user): string
    {
        try {
            $cid = (string) ($user->cuenta_id ?? '');
            if ($cid !== '') return $cid;

            if (isset($user->cuenta) && is_object($user->cuenta)) {
                $cid = (string) ($user->cuenta->id ?? $user->cuenta->cuenta_id ?? '');
                if ($cid !== '') return $cid;
            }

            if (method_exists($user, 'cuenta') && $user->cuenta) {
                $cid = (string) ($user->cuenta->id ?? $user->cuenta->cuenta_id ?? '');
                if ($cid !== '') return $cid;
            }
        } catch (\Throwable) {
        }

        return '';
    }

    private function diskConfigured(string $disk): bool
    {
        $cfg = config("filesystems.disks.$disk");
        return is_array($cfg) && !empty($cfg['driver']);
    }

    private function safeSize(string $disk, string $path): int
    {
        try {
            $path = ltrim($path, '/');
            if ($path === '' || !$this->diskConfigured($disk)) return 0;
            return (int) Storage::disk($disk)->size($path);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function bytesToGb(int $bytes): float
    {
        return $bytes > 0 ? ($bytes / 1024 / 1024 / 1024) : 0.0;
    }

    private function statusNormalized(?string $raw): string
    {
        $s = strtolower(trim((string) $raw));
        // soporta DONE / LISTO / READY / etc
        $map = [
            'done' => 'done',
            'listo' => 'done',
            'ready' => 'done',
            'completo' => 'done',
            'completed' => 'done',

            'processing' => 'processing',
            'procesando' => 'processing',
            'pending' => 'pending',
            'pendiente' => 'pending',

            'canceled' => 'canceled',
            'cancelled' => 'canceled',
            'cancelado' => 'canceled',
        ];

        return $map[$s] ?? $s;
    }

    private function isReadyStatus(string $statusNorm): bool
    {
        return in_array($statusNorm, ['done'], true);
    }

    private function candidateSourceIds(object $download, $sch): array
    {
        $ids = [];
        $ids[] = (string) ($download->id ?? '');

        if ($sch->hasColumn('sat_downloads', 'download_id')) $ids[] = (string) ($download->download_id ?? '');
        if ($sch->hasColumn('sat_downloads', 'request_id'))  $ids[] = (string) ($download->request_id ?? '');
        if ($sch->hasColumn('sat_downloads', 'package_id'))  $ids[] = (string) ($download->package_id ?? '');

        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        return !empty($ids) ? $ids : [(string) ($download->id ?? '')];
    }

    private function candidateDisksForPath(string $path): array
    {
        $path = ltrim($path, '/');

        // ✅ para tu caso: sat/zips/... vive en disk public
        if (str_starts_with($path, 'sat/')) {
            return ['public', 'sat_zip', 'sat_downloads', 'local', 'private', 'sat_vault', 'vault'];
        }

        if (str_starts_with($path, 'vault/') || str_contains($path, '/vault/')) {
            return ['sat_vault', 'vault', 'private', 'local', 'public', 'sat_zip', 'sat_downloads'];
        }

        return ['sat_zip', 'sat_downloads', 'public', 'local', 'private', 'sat_vault', 'vault'];
    }

    /**
     * Sincroniza métricas del ZIP encontrado hacia sat_downloads
     * y bytes a sat_vault_files si aplica.
     *
     * ✅ Si existe sat_downloads.download_id y está NULL,
     *    lo llenamos con source_id del vault file para dejar vínculo fijo.
     */
    private function syncDownloadMetricsFromFoundZip(
        string $cuentaId,
        string $downloadRowId,
        string $disk,
        string $path,
        int $bytes,
        int $vaultFileId = 0,
        ?string $vaultSourceId = null
    ): void {
        $conn = self::CONN;
        $sch  = Schema::connection($conn);

        $disk = $disk !== '' ? $disk : 'sat_zip';
        $path = ltrim($path, '/');

        // -------- sat_downloads --------
        if ($sch->hasTable('sat_downloads')) {
            $upd = [];

            if ($sch->hasColumn('sat_downloads', 'zip_disk'))   $upd['zip_disk']   = $disk;
            if ($sch->hasColumn('sat_downloads', 'zip_path'))   $upd['zip_path']   = $path;
            if ($sch->hasColumn('sat_downloads', 'vault_path')) $upd['vault_path'] = $path;

            if ($vaultSourceId && $sch->hasColumn('sat_downloads', 'download_id')) {
                try {
                    $current = DB::connection($conn)->table('sat_downloads')
                        ->where('cuenta_id', $cuentaId)
                        ->where('id', $downloadRowId)
                        ->value('download_id');

                    if (empty($current)) {
                        $upd['download_id'] = $vaultSourceId;
                    }
                } catch (\Throwable) {
                }
            }

            if ($bytes > 0) {
                if ($sch->hasColumn('sat_downloads', 'zip_bytes'))   $upd['zip_bytes']   = $bytes;
                if ($sch->hasColumn('sat_downloads', 'size_bytes'))  $upd['size_bytes']  = $bytes;

                $gb = round($this->bytesToGb($bytes), 8);
                if ($sch->hasColumn('sat_downloads', 'size_gb')) $upd['size_gb'] = $gb;
                if ($sch->hasColumn('sat_downloads', 'size_mb')) $upd['size_mb'] = round($bytes / 1024 / 1024, 4);

                if ($sch->hasColumn('sat_downloads', 'costo')) {
                    $precioPorGb = (float) config('services.sat.precio_gb', 0);
                    if ($precioPorGb > 0) {
                        try {
                            $current = (float) (DB::connection($conn)->table('sat_downloads')
                                ->where('cuenta_id', $cuentaId)
                                ->where('id', $downloadRowId)
                                ->value('costo') ?? 0);

                            if ($current <= 0) $upd['costo'] = round($gb * $precioPorGb, 2);
                        } catch (\Throwable) {
                        }
                    }
                }
            }

            if (!empty($upd)) {
                $upd['updated_at'] = now();
                try {
                    DB::connection($conn)->table('sat_downloads')
                        ->where('cuenta_id', $cuentaId)
                        ->where('id', $downloadRowId)
                        ->update($upd);
                } catch (\Throwable) {
                }
            }
        }

        // -------- sat_vault_files (si aplica) --------
        if ($vaultFileId > 0 && $bytes > 0 && $sch->hasTable('sat_vault_files') && $sch->hasColumn('sat_vault_files', 'bytes')) {
            try {
                $current = (int) (DB::connection($conn)->table('sat_vault_files')
                    ->where('cuenta_id', $cuentaId)
                    ->where('id', $vaultFileId)
                    ->value('bytes') ?? 0);

                if ($current <= 0) {
                    DB::connection($conn)->table('sat_vault_files')
                        ->where('cuenta_id', $cuentaId)
                        ->where('id', $vaultFileId)
                        ->update([
                            'bytes'      => $bytes,
                            'updated_at' => now(),
                        ]);
                }
            } catch (\Throwable) {
            }
        }
    }
}