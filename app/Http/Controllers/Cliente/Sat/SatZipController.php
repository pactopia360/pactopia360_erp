<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SatZipController extends Controller
{
    private const CONN = 'mysql_clientes';

    public function download(Request $request, string $downloadId)
    {
        $user     = $request->user();
        $cuentaId = $this->resolveCuentaId($user);

        if ($cuentaId === '') {
            abort(403, 'Cuenta no válida.');
        }

        $conn = self::CONN;
        $db   = DB::connection($conn);
        $sch  = Schema::connection($conn);

        if (!$sch->hasTable('sat_downloads')) {
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
            abort(404, 'Descarga no encontrada.');
        }

        $downloadRowId = (string)($download->id ?? $downloadId);
        $rfc           = strtoupper(trim((string)($download->rfc ?? '')));

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
                $resp = $this->tryDownloadVaultZipAndSync($cuentaId, $downloadRowId, $download, $vaultZip, $conn);
                if ($resp) return $resp;
            }
        }

        // ==========================================================
        // 2) FALLBACK: zip_path / vault_path en sat_downloads
        // ==========================================================
        $pathsToTry = [];

        $zipPath = ltrim((string)($download->zip_path ?? ''), '/');
        if ($zipPath !== '') $pathsToTry[] = $zipPath;

        $vaultPath = ltrim((string)($download->vault_path ?? ''), '/');
        if ($vaultPath !== '' && $vaultPath !== $zipPath) $pathsToTry[] = $vaultPath;

        foreach ($pathsToTry as $p) {
            foreach ($this->candidateDisksForPath($p) as $disk) {
                try {
                    if ($this->diskConfigured($disk) && Storage::disk($disk)->exists($p)) {
                        $bytes = $this->safeSize($disk, $p);

                        $this->syncDownloadMetricsFromFoundZip(
                            $cuentaId,
                            $downloadRowId,
                            $disk,
                            $p,
                            $bytes,
                            0,
                            null // source_id opcional
                        );

                        return Storage::disk($disk)->download($p, basename($p));
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        // ==========================================================
        // 3) FALLBACK INTELIGENTE: buscar ZIP por RFC en sat_vault_files
        //    (porque NO hay vínculo download_id/request_id/package_id en sat_downloads)
        // ==========================================================
        if ($sch->hasTable('sat_vault_files') && $rfc !== '') {
            $vaultZipGuess = $db->table('sat_vault_files')
                ->where('cuenta_id', $cuentaId)
                ->where('source', 'sat_download')
                ->where(function ($w) use ($rfc) {
                    $w->where('mime', 'application/zip')
                      ->orWhere('filename', 'like', '%.zip')
                      ->orWhere('path', 'like', '%.zip');
                })
                ->where(function ($w) use ($rfc) {
                    // RFC suele venir en filename SAT_<RFC>_YYYY...
                    $w->where('filename', 'like', "%{$rfc}%")
                      ->orWhere('path', 'like', "%/{$rfc}%")
                      ->orWhere('path', 'like', "%{$rfc}%");
                })
                ->orderByDesc('id')
                ->first();

            if ($vaultZipGuess) {
                $resp = $this->tryDownloadVaultZipAndSync($cuentaId, $downloadRowId, $download, $vaultZipGuess, $conn);
                if ($resp) return $resp;
            }
        }

        // ==========================================================
        // 4) NO DISPONIBLE
        // ==========================================================
        return response()->json([
            'ok'          => false,
            'message'     => 'ZIP aún no disponible para esta descarga (no se encontró vínculo a bóveda ni paths).',
            'download_id' => $downloadRowId,
            'status'      => $download->status ?? null,
            'zip_path'    => $download->zip_path ?? null,
            'vault_path'  => $download->vault_path ?? null,
            'zip_disk'    => $download->zip_disk ?? null,
            'rfc'         => $rfc ?: null,
        ], 409);
    }

    /* ==========================================================
     * Descarga desde sat_vault_files y sincroniza sat_downloads
     * ========================================================== */

    private function tryDownloadVaultZipAndSync(string $cuentaId, string $downloadRowId, object $download, object $vaultZip, string $conn)
    {
        $disk = (string)($vaultZip->disk ?? 'sat_vault');
        $path = ltrim((string)($vaultZip->path ?? ''), '/');

        if ($path === '' || !$this->diskConfigured($disk)) {
            return null;
        }

        if (!Storage::disk($disk)->exists($path)) {
            return null;
        }

        $bytes = $this->safeSize($disk, $path);

        // ✅ Aquí amarramos sat_downloads con el ZIP real
        $this->syncDownloadMetricsFromFoundZip(
            $cuentaId,
            $downloadRowId,
            $disk,
            $path,
            $bytes,
            (int)($vaultZip->id ?? 0),
            (string)($vaultZip->source_id ?? null) // <<--- clave
        );

        return Storage::disk($disk)->download(
            $path,
            (string)($vaultZip->filename ?: basename($path))
        );
    }

    /* ==========================================================
     * Helpers
     * ========================================================== */

    private function resolveCuentaId($user): string
    {
        try {
            $cid = (string)($user->cuenta_id ?? '');
            if ($cid !== '') return $cid;

            if (isset($user->cuenta) && is_object($user->cuenta)) {
                $cid = (string)($user->cuenta->id ?? $user->cuenta->cuenta_id ?? '');
                if ($cid !== '') return $cid;
            }

            if (method_exists($user, 'cuenta') && $user->cuenta) {
                $cid = (string)($user->cuenta->id ?? $user->cuenta->cuenta_id ?? '');
                if ($cid !== '') return $cid;
            }
        } catch (\Throwable) {}

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

    private function candidateSourceIds(object $download, $sch): array
    {
        $ids = [];
        $ids[] = (string)($download->id ?? '');

        if ($sch->hasColumn('sat_downloads', 'download_id')) $ids[] = (string)($download->download_id ?? '');
        if ($sch->hasColumn('sat_downloads', 'request_id'))  $ids[] = (string)($download->request_id ?? '');
        if ($sch->hasColumn('sat_downloads', 'package_id'))  $ids[] = (string)($download->package_id ?? '');

        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        return !empty($ids) ? $ids : [(string)($download->id ?? '')];
    }

    private function candidateDisksForPath(string $path): array
    {
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'vault/') || str_contains($path, '/vault/')) {
            return ['sat_vault', 'vault', 'private', 'sat_zip', 'sat_downloads', 'local', 'public'];
        }

        return ['sat_zip', 'sat_downloads', 'private', 'local', 'sat_vault', 'vault', 'public'];
    }

    /**
     * Sincroniza métricas del ZIP encontrado hacia sat_downloads
     * y bytes a sat_vault_files si aplica.
     *
     * ✅ Además: si existe sat_downloads.download_id y está NULL,
     *           lo llenamos con source_id del vault file para dejar vínculo fijo.
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

            if ($sch->hasColumn('sat_downloads', 'zip_disk'))  $upd['zip_disk']  = $disk;
            if ($sch->hasColumn('sat_downloads', 'zip_path'))  $upd['zip_path']  = $path;
            if ($sch->hasColumn('sat_downloads', 'vault_path')) $upd['vault_path'] = $path;

            // ✅ amarrar download_id al source_id real del vault (si existe columna)
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
                    // ignore
                }
            }

            if ($bytes > 0) {
                if ($sch->hasColumn('sat_downloads', 'zip_bytes'))  $upd['zip_bytes']  = $bytes;
                if ($sch->hasColumn('sat_downloads', 'size_bytes')) $upd['size_bytes'] = $bytes;

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
                        } catch (\Throwable) {}
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
                } catch (\Throwable) {}
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
            } catch (\Throwable) {}
        }
    }
}
