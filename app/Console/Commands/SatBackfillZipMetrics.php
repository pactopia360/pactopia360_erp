<?php

namespace App\Console\Commands;

use App\Models\Cliente\SatDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SatBackfillZipMetrics extends Command
{
    protected $signature = 'sat:backfill-zip-metrics
        {--cuenta_id= : UUID cuenta_id}
        {--limit=5000 : Máximo de filas}
        {--force : Recalcula aunque ya tenga size_bytes/zip_bytes/size_mb/size_gb}
        {--all : No filtra por status/zip_path (toma todas las filas con esa cuenta)}
    ';

    protected $description = 'Backfill size_bytes/zip_bytes/size_mb/size_gb resolviendo ZIP en storage (modo all disponible).';

    public function handle(): int
    {
        $conn   = 'mysql_clientes';
        $schema = Schema::connection($conn);

        if (!$schema->hasTable('sat_downloads')) {
            $this->error('Tabla sat_downloads no existe en mysql_clientes.');
            return self::FAILURE;
        }

        $hasZipPath    = $schema->hasColumn('sat_downloads', 'zip_path');
        $hasZipDisk    = $schema->hasColumn('sat_downloads', 'zip_disk');
        $hasVaultPath  = $schema->hasColumn('sat_downloads', 'vault_path');

        $hasSizeBytes  = $schema->hasColumn('sat_downloads', 'size_bytes');
        $hasZipBytes   = $schema->hasColumn('sat_downloads', 'zip_bytes');
        $hasSizeMb     = $schema->hasColumn('sat_downloads', 'size_mb');
        $hasSizeGb     = $schema->hasColumn('sat_downloads', 'size_gb');

        $hasRequestId  = $schema->hasColumn('sat_downloads', 'request_id');
        $hasPackageId  = $schema->hasColumn('sat_downloads', 'package_id');

        $cuentaId = trim((string)($this->option('cuenta_id') ?? ''));
        $limit    = (int)($this->option('limit') ?? 5000);
        $limit    = max(1, min($limit, 50000));
        $force    = (bool)$this->option('force');
        $all      = (bool)$this->option('all');

        $qb = SatDownload::on($conn)->newQuery();

        if ($cuentaId !== '') {
            $qb->where('cuenta_id', $cuentaId);
        }

        if (!$all) {
            $qb->where(function ($w) use ($hasZipPath) {
                if ($hasZipPath) {
                    $w->orWhere(function ($q) {
                        $q->whereNotNull('zip_path')->where('zip_path', '<>', '');
                    });
                }
                $w->orWhereIn('status', ['ready','done','listo','PAID','paid','PAGADO','pagado','downloaded']);
            });
        }

        if (!$force) {
            $qb->where(function ($w) use ($hasSizeBytes, $hasZipBytes, $hasSizeMb, $hasSizeGb) {
                if ($hasSizeBytes) $w->orWhereNull('size_bytes')->orWhere('size_bytes', '<=', 0);
                if ($hasZipBytes)  $w->orWhereNull('zip_bytes')->orWhere('zip_bytes', '<=', 0);
                if ($hasSizeMb)    $w->orWhere('size_mb', '<=', 0);
                if ($hasSizeGb)    $w->orWhereNull('size_gb')->orWhere('size_gb', '<=', 0);
            });
        }

        $rows = $qb->orderByDesc('created_at')->limit($limit)->get();

        $this->info('Encontradas: ' . $rows->count() . ' descargas ' . ($all ? '(modo ALL)' : 'candidatas'));

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $dl) {
            // 1) si existe sat_vault_files => usarlo primero (y si falta, NO dependas del zip_path)
            [$vfDisk, $vfPath, $vfBytes] = $this->resolveFromVaultFiles($conn, (string)$dl->cuenta_id, (string)$dl->id);

            if ($vfDisk !== '' && $vfPath !== '' && $vfBytes > 0) {
                $this->updateMetricsRow($conn, $dl, $vfDisk, $vfPath, $vfBytes, $hasZipPath, $hasZipDisk, $hasVaultPath, $hasSizeBytes, $hasZipBytes, $hasSizeMb, $hasSizeGb);
                $updated++;
                $this->line("OK {$dl->id}: {$vfBytes} bytes (vault_files) {$vfDisk}:{$vfPath}");
                continue;
            }

            // 2) fallback: resolver por storage (zip_path/heurísticas)
            [$disk, $path] = $this->resolveZipDiskPath($dl, $hasZipDisk, $hasZipPath, $hasVaultPath, $hasRequestId, $hasPackageId);

            if ($disk === '' || $path === '') {
                $skipped++;
                $this->line("SKIP {$dl->id}: sin disk/path resoluble");
                continue;
            }

            $path = ltrim($path, '/');

            if (!$this->diskConfigured($disk) || !Storage::disk($disk)->exists($path)) {
                $skipped++;
                $this->line("SKIP {$dl->id}: no existe {$disk}:{$path}");
                continue;
            }

            $bytes = 0;
            try { $bytes = (int) Storage::disk($disk)->size($path); } catch (\Throwable) { $bytes = 0; }

            if ($bytes <= 0) {
                $skipped++;
                $this->line("SKIP {$dl->id}: bytes=0 {$disk}:{$path}");
                continue;
            }

            $this->updateMetricsRow($conn, $dl, $disk, $path, $bytes, $hasZipPath, $hasZipDisk, $hasVaultPath, $hasSizeBytes, $hasZipBytes, $hasSizeMb, $hasSizeGb);

            $updated++;
            $this->line("OK {$dl->id}: {$bytes} bytes {$disk}:{$path}");
        }

        $this->info("Actualizadas: {$updated} | Omitidas: {$skipped}");
        return self::SUCCESS;
    }

    private function resolveFromVaultFiles(string $conn, string $cuentaId, string $downloadId): array
    {
        try {
            if (!Schema::connection($conn)->hasTable('sat_vault_files')) return ['', '', 0];

            $vf = DB::connection($conn)->table('sat_vault_files')
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

            if (!$vf) return ['', '', 0];

            $disk  = (string)($vf->disk ?? '');
            $path  = ltrim((string)($vf->path ?? ''), '/');
            $bytes = (int)($vf->bytes ?? 0);

            if ($disk === '' || $path === '' || $bytes <= 0) return ['', '', 0];

            // validación en storage (si existe)
            if ($this->diskConfigured($disk) && Storage::disk($disk)->exists($path)) {
                try { $bytes = (int)Storage::disk($disk)->size($path); } catch (\Throwable) {}
            }

            return [$disk, $path, $bytes];
        } catch (\Throwable) {
            return ['', '', 0];
        }
    }

    private function updateMetricsRow(
        string $conn,
        SatDownload $dl,
        string $disk,
        string $path,
        int $bytes,
        bool $hasZipPath,
        bool $hasZipDisk,
        bool $hasVaultPath,
        bool $hasSizeBytes,
        bool $hasZipBytes,
        bool $hasSizeMb,
        bool $hasSizeGb
    ): void {
        $mb = $bytes / 1024 / 1024;
        $gb = $bytes / 1024 / 1024 / 1024;

        $payload = [];

        if ($hasSizeBytes) $payload['size_bytes'] = $bytes;
        if ($hasZipBytes)  $payload['zip_bytes']  = $bytes;

        if ($hasSizeMb)    $payload['size_mb'] = round($mb, 2);
        if ($hasSizeGb)    $payload['size_gb'] = number_format($gb, 8, '.', '');

        if ($hasZipPath && (string)($dl->zip_path ?? '') === '') $payload['zip_path'] = $path;
        if ($hasZipDisk && (string)($dl->zip_disk ?? '') === '') $payload['zip_disk'] = $disk;

        if ($hasVaultPath && (string)($dl->vault_path ?? '') === '' && in_array($disk, ['sat_vault','vault','private'], true)) {
            $payload['vault_path'] = $path;
        }

        SatDownload::on($conn)->where('id', $dl->id)->update($payload);
    }

    private function resolveZipDiskPath(
        SatDownload $dl,
        bool $hasZipDisk,
        bool $hasZipPath,
        bool $hasVaultPath,
        bool $hasRequestId,
        bool $hasPackageId
    ): array {
        $zipPath   = $hasZipPath ? ltrim((string)($dl->zip_path ?? ''), '/') : '';
        $zipDisk   = $hasZipDisk ? (string)($dl->zip_disk ?? '') : '';
        $vaultPath = $hasVaultPath ? ltrim((string)($dl->vault_path ?? ''), '/') : '';

        $meta = [];
        foreach (['meta_json','meta','payload_json','payload','response_json','response'] as $col) {
            try {
                $raw = $dl->{$col} ?? null;

                if (is_array($raw)) {
                    $meta = array_replace_recursive($meta, $raw);
                } elseif (is_string($raw) && trim($raw) !== '') {
                    $arr = json_decode($raw, true);
                    if (is_array($arr)) $meta = array_replace_recursive($meta, $arr);
                }
            } catch (\Throwable) {}
        }

        if ($zipDisk === '') $zipDisk = (string)($meta['zip_disk'] ?? $meta['meta_zip_disk'] ?? '');
        if ($zipPath === '') {
            $zipPath = ltrim((string)(
                $meta['zip_path']
                ?? $meta['package_path']
                ?? data_get($meta, 'payload.zip_path')
                ?? data_get($meta, 'payload.package_path')
                ?? ''
            ), '/');
        }

        $linkedId = null;
        foreach (['linked_download_id','new_download_id','download_id'] as $k) {
            $v = $meta[$k] ?? null;
            if (is_string($v) && preg_match('~^[0-9a-f\-]{36}$~i', $v)) { $linkedId = $v; break; }
        }

        $diskCandidates = [];
        foreach (array_filter(array_unique([
            $zipDisk,
            (string)config('filesystems.sat_downloads_disk', ''),
            'sat_zip',
            'sat_downloads',
            'private',
            'local',
            'sat_vault',
            'vault',
        ])) as $d) {
            if ($this->diskConfigured($d)) $diskCandidates[] = $d;
        }

        if ($zipPath !== '') {
            foreach ($diskCandidates as $d) {
                if (Storage::disk($d)->exists($zipPath)) return [$d, $zipPath];
            }
        }

        if ($vaultPath !== '') {
            foreach ($diskCandidates as $d) {
                if (Storage::disk($d)->exists($vaultPath)) return [$d, $vaultPath];
            }
        }

        $did = (string)$dl->id;
        $cid = (string)$dl->cuenta_id;

        $requestId = $hasRequestId ? trim((string)($dl->request_id ?? '')) : '';
        $packageId = $hasPackageId ? trim((string)($dl->package_id ?? '')) : '';

        $ids = array_values(array_filter(array_unique([$did, $linkedId, $requestId ?: null, $packageId ?: null])));

        $paths = [];
        $rfc = strtoupper(trim((string)($dl->rfc ?? '')));

        foreach ($ids as $id) {
            $paths[] = "sat/packages/{$cid}/pkg_{$id}.zip";
            $paths[] = "packages/{$cid}/pkg_{$id}.zip";
            $paths[] = "sat/packages/{$cid}/{$id}.zip";
            $paths[] = "packages/{$cid}/{$id}.zip";
            $paths[] = "packages/{$cid}/done/pkg_{$id}.zip";
            $paths[] = "packages/{$cid}/paid/pkg_{$id}.zip";
            $paths[] = "sat/packages/{$cid}/done/pkg_{$id}.zip";
            $paths[] = "sat/packages/{$cid}/paid/pkg_{$id}.zip";

            // patrón real detectado en tu storage
            if ($rfc !== '') {
                $paths[] = "packages/{$cid}/SAT_{$rfc}_{$id}.zip";
                $paths[] = "sat/packages/{$cid}/SAT_{$rfc}_{$id}.zip";
            }
        }

        $paths = array_values(array_unique(array_filter($paths)));

        foreach ($diskCandidates as $d) {
            foreach ($paths as $p) {
                $p = ltrim($p, '/');
                if (Storage::disk($d)->exists($p)) return [$d, $p];
            }
        }

        return ['', ''];
    }

    private function diskConfigured(string $disk): bool
    {
        $cfg = config("filesystems.disks.$disk");
        return is_array($cfg) && !empty($cfg['driver']);
    }
}
