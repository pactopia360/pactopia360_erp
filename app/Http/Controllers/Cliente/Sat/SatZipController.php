<?php

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SatZipController extends Controller
{
    private const CONN = 'mysql_clientes';

    /**
     * Descarga el ZIP de una descarga SAT.
     *
     * Reglas:
     * 1) Prioriza ZIP ya en bóveda (sat_vault_files) por source=sat_download, source_id=downloadId
     * 2) Fallback a zip_path en sat_downloads buscando en varios disks
     * 3) Si lo encuentra, actualiza métricas (bytes/peso_gb/size_gb/zip_disk/zip_path/costo) en sat_downloads
     *    y bytes en sat_vault_files (si aplica) de forma segura (hasColumn).
     */
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

        $download = $db->table('sat_downloads')
            ->where('id', $downloadId)
            ->where('cuenta_id', $cuentaId)
            ->first();

        if (!$download) {
            abort(404, 'Descarga no encontrada.');
        }

        // ==========================================================
        // 1) PRIORIDAD: ZIP ya en bóveda (sat_vault_files)
        // ==========================================================
        if ($sch->hasTable('sat_vault_files')) {
            $vaultZip = $db->table('sat_vault_files')
                ->where('cuenta_id', $cuentaId)
                ->where('source', 'sat_download')
                ->where('source_id', $downloadId)
                ->where(function ($w) {
                    $w->where('mime', 'application/zip')
                      ->orWhere('filename', 'like', '%.zip')
                      ->orWhere('path', 'like', '%.zip');
                })
                ->orderByDesc('id')
                ->first();

            if ($vaultZip) {
                $disk = (string)($vaultZip->disk ?? 'sat_vault');
                $path = ltrim((string)($vaultZip->path ?? ''), '/');

                if ($path !== '' && $this->diskConfigured($disk) && Storage::disk($disk)->exists($path)) {
                    $bytes = $this->safeSize($disk, $path);

                    // Actualiza métricas en sat_downloads y bytes en sat_vault_files
                    $this->syncDownloadMetricsFromFoundZip(
                        $cuentaId,
                        $downloadId,
                        $disk,
                        $path,
                        $bytes,
                        (int)($vaultZip->id ?? 0)
                    );

                    return Storage::disk($disk)->download(
                        $path,
                        (string)($vaultZip->filename ?: basename($path))
                    );
                }
            }
        }

        // ==========================================================
        // 2) FALLBACK: zip_path directo (sin depender de zip_disk)
        // ==========================================================
        $zipPath = ltrim((string)($download->zip_path ?? ''), '/');

        if ($zipPath !== '') {
            // si parece vault/, probamos bóveda primero
            $candidates = str_starts_with($zipPath, 'vault/')
                ? ['sat_vault', 'vault', 'private', 'sat_zip', 'sat_downloads', 'local', 'public']
                : ['sat_zip', 'sat_downloads', 'private', 'local', 'sat_vault', 'vault', 'public'];

            foreach ($candidates as $disk) {
                try {
                    if ($this->diskConfigured($disk) && Storage::disk($disk)->exists($zipPath)) {
                        $bytes = $this->safeSize($disk, $zipPath);

                        // Actualiza métricas en sat_downloads (aunque no esté en sat_vault_files)
                        $this->syncDownloadMetricsFromFoundZip(
                            $cuentaId,
                            $downloadId,
                            $disk,
                            $zipPath,
                            $bytes,
                            0
                        );

                        return Storage::disk($disk)->download($zipPath, basename($zipPath));
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        // ==========================================================
        // 3) AÚN NO LISTO
        // ==========================================================
        return response()->json([
            'ok'          => false,
            'message'     => 'ZIP aún no disponible para esta descarga.',
            'download_id' => $downloadId,
            'status'      => $download->status ?? null,
        ], 409);
    }

    /* ==========================================================
     * Helpers
     * ========================================================== */

    private function resolveCuentaId($user): string
    {
        try {
            // Caso común: UsuarioCuenta trae cuenta_id
            $cid = (string)($user->cuenta_id ?? '');
            if ($cid !== '') return $cid;

            // Caso: relación cuenta()->id
            if (isset($user->cuenta) && is_object($user->cuenta)) {
                $cid = (string)($user->cuenta->id ?? $user->cuenta->cuenta_id ?? '');
                if ($cid !== '') return $cid;
            }

            // Caso: método cuenta()
            if (method_exists($user, 'cuenta') && $user->cuenta) {
                $cid = (string)($user->cuenta->id ?? $user->cuenta->cuenta_id ?? '');
                if ($cid !== '') return $cid;
            }
        } catch (\Throwable) {
            // no-op
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

    /**
     * Sincroniza métricas del ZIP encontrado hacia sat_downloads
     * y (si se pasa vaultFileId) también bytes hacia sat_vault_files cuando esté en 0.
     */
    private function syncDownloadMetricsFromFoundZip(
        string $cuentaId,
        string $downloadId,
        string $disk,
        string $path,
        int $bytes,
        int $vaultFileId = 0
    ): void {
        $conn = self::CONN;
        $sch  = Schema::connection($conn);

        $disk = $disk !== '' ? $disk : 'sat_zip';
        $path = ltrim($path, '/');

        // -------- sat_downloads --------
        if ($sch->hasTable('sat_downloads')) {
            $upd = [];

            // zip_disk / zip_path (si existen columnas)
            if ($sch->hasColumn('sat_downloads', 'zip_disk')) {
                $upd['zip_disk'] = $disk;
            }
            if ($sch->hasColumn('sat_downloads', 'zip_path')) {
                $upd['zip_path'] = $path;
            }

            // bytes / size_bytes (si existen)
            if ($bytes > 0) {
                if ($sch->hasColumn('sat_downloads', 'bytes')) {
                    $upd['bytes'] = $bytes;
                }
                if ($sch->hasColumn('sat_downloads', 'size_bytes')) {
                    $upd['size_bytes'] = $bytes;
                }

                // peso_gb / size_gb
                $gb = round($this->bytesToGb($bytes), 4);
                if ($sch->hasColumn('sat_downloads', 'peso_gb')) {
                    $upd['peso_gb'] = $gb;
                }
                if ($sch->hasColumn('sat_downloads', 'size_gb')) {
                    $upd['size_gb'] = $gb;
                }
                if ($sch->hasColumn('sat_downloads', 'tam_gb')) {
                    $upd['tam_gb'] = $gb;
                }

                // costo (solo si existe columna)
                if ($sch->hasColumn('sat_downloads', 'costo')) {
                    // Regla: si ya trae costo > 0, no lo pisamos.
                    // Si costo es 0 o null, calculamos por GB usando config.
                    $precioPorGb = (float) config('services.sat.precio_gb', 0);
                    if ($precioPorGb > 0) {
                        $current = DB::connection($conn)->table('sat_downloads')
                            ->where('cuenta_id', $cuentaId)
                            ->where('id', $downloadId)
                            ->value('costo');

                        $current = (float)($current ?? 0);
                        if ($current <= 0) {
                            $upd['costo'] = round($gb * $precioPorGb, 2);
                        }
                    }
                }
            }

            if (!empty($upd)) {
                $upd['updated_at'] = now();

                try {
                    DB::connection($conn)->table('sat_downloads')
                        ->where('cuenta_id', $cuentaId)
                        ->where('id', $downloadId)
                        ->update($upd);
                } catch (\Throwable) {
                    // no-op
                }
            }
        }

        // -------- sat_vault_files (si aplica) --------
        if ($vaultFileId > 0 && $bytes > 0 && $sch->hasTable('sat_vault_files') && $sch->hasColumn('sat_vault_files', 'bytes')) {
            try {
                // Solo actualiza si está en 0 (o null)
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
                // no-op
            }
        }
    }
}
