<?php

declare(strict_types=1);

namespace App\Services\Sat;

use App\Models\Cliente\SatDownload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SatDownloadZipHelper
{
    // =====================================================
    //  DEMO ZIP (ALINEADO A sat/packages/{cuenta}/pkg_{id}.zip)
    // =====================================================

    public function buildDemoZip(SatDownload $download, $user = null): void
    {
        $cuentaId = (string) ($download->cuenta_id ?? '');
        $rfc      = (string) ($download->rfc ?? '');

        if ($cuentaId === '' || $rfc === '') {
            Log::warning('[SatDownloadZipHelper] buildDemoZip sin cuenta_id o rfc', [
                'download_id' => $download->id ?? null,
                'cuenta_id'   => $cuentaId,
                'rfc'         => $rfc,
            ]);
            return;
        }

        // ===== MISMA convención que SatDownloadService =====
        $folder = 'sat/packages/' . $cuentaId;
        $fname  = 'pkg_' . (string) $download->id . '.zip';
        $zipRel = $folder . '/' . $fname;

        $diskName = $this->resolveSatZipDisk();

        Log::info('[SatDownloadZipHelper] DEMO: iniciando buildDemoZip', [
            'download_id' => (string) $download->id,
            'cuenta_id'   => $cuentaId,
            'rfc'         => $rfc,
            'zip_rel'     => $zipRel,
            'disk'        => $diskName,
        ]);

        try {
            if (!$this->diskConfigured($diskName)) {
                Log::error('[SatDownloadZipHelper] DEMO: disk no configurado', ['disk' => $diskName]);
                return;
            }

            $disk   = Storage::disk($diskName);
            $zipAbs = $disk->path($zipRel);
            $zipDir = \dirname($zipAbs);

            if (!is_dir($zipDir)) {
                @mkdir($zipDir, 0775, true);
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipAbs, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                Log::error('[SatDownloadZipHelper] DEMO: no se pudo abrir ZIP para escribir', [
                    'download_id' => (string) $download->id,
                    'zip_abs'     => $zipAbs,
                ]);
                return;
            }

            // Contenido DEMO mínimo
            $demoFiles = [
                'XML/CFDI_DEMO_1.xml' => '<xml>CFDI DEMO 1</xml>',
                'XML/CFDI_DEMO_2.xml' => '<xml>CFDI DEMO 2</xml>',
                'XML/CFDI_DEMO_3.xml' => '<xml>CFDI DEMO 3</xml>',
            ];

            foreach ($demoFiles as $name => $content) {
                $zip->addFromString($name, $content);
            }

            $zip->addFromString(
                'README.txt',
                "ZIP DEMO para descarga SAT\n"
                . "ID: {$download->id}\n"
                . "RFC: {$rfc}\n"
                . "TIPO: " . ($download->tipo ?? '') . "\n"
                . "RANGO: " . ($download->date_from ?? '') . " a " . ($download->date_to ?? '') . "\n"
            );

            $zip->close();

            $exists = $disk->exists($zipRel);
            $size   = $exists ? (int) $disk->size($zipRel) : 0;

            Log::info('[SatDownloadZipHelper] DEMO: ZIP generado', [
                'download_id' => (string) $download->id,
                'zip_rel'     => $zipRel,
                'disk'        => $diskName,
                'exists'      => $exists,
                'size_bytes'  => $size,
            ]);

            if (!$exists || $size <= 0) {
                return;
            }

            // Persistir metadatos del ZIP en sat_downloads
            $this->persistZipMetaOnDownload($download, $diskName, $zipRel, $size);

            // Importante:
            // No movemos a bóveda automáticamente aquí.
            // Llama $this->moveZipToVault($download, $user) donde corresponda (pago / acción explícita).
        } catch (\Throwable $e) {
            Log::error('[SatDownloadZipHelper] DEMO: Excepción en buildDemoZip', [
                'download_id' => (string) ($download->id ?? ''),
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mueve el ZIP a la bóveda, inserta sat_vault_files y actualiza uso (cuentas_cliente).
     * Este método queda alineado al flujo de VaultController@fromDownload().
     */
    public function moveZipToVault(SatDownload $download, $user = null): void
    {
        $cuentaId   = (string) ($download->cuenta_id ?? '');
        $downloadId = (string) ($download->id ?? '');

        if ($cuentaId === '' || $downloadId === '') {
            Log::warning('[SAT:Vault] moveZipToVault sin cuenta_id o download_id', [
                'download_id' => $downloadId,
                'cuenta_id'   => $cuentaId,
            ]);
            return;
        }

        // 1) Resolver disco/ruta del ZIP origen
        [$zipDisk, $zipPath] = $this->resolveZipLocationFromDownload($download);

        if (!$zipDisk || $zipPath === '' || !$this->diskExistsSafe($zipDisk, $zipPath)) {
            Log::warning('[SAT:Vault] ZIP no encontrado para moveZipToVault', [
                'download_id' => $downloadId,
                'cuenta_id'   => $cuentaId,
                'zipDisk'     => $zipDisk,
                'zipPath'     => $zipPath,
            ]);
            return;
        }

        $zipBytes = (int) $this->diskSizeSafe($zipDisk, $zipPath);
        if ($zipBytes <= 0) {
            Log::warning('[SAT:Vault] ZIP size inválido', [
                'download_id' => $downloadId,
                'cuenta_id'   => $cuentaId,
                'zipDisk'     => $zipDisk,
                'zipPath'     => $zipPath,
            ]);
            return;
        }

        // 2) Resolver disco destino bóveda
        $vaultDisk = $this->resolveVaultDisk();
        if (!$this->diskConfigured($vaultDisk)) {
            Log::error('[SAT:Vault] vault disk no configurado', ['vaultDisk' => $vaultDisk]);
            return;
        }

        // 3) Construir destino
        $rfc     = strtoupper((string) ($download->rfc ?? ''));
        $safeRfc = $rfc !== '' ? preg_replace('/[^A-Z0-9]/', '', $rfc) : 'SIN_RFC';

        $destDir  = 'vault/' . $cuentaId . '/' . $safeRfc;
        $destName = 'SAT_' . $safeRfc . '_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.zip';
        $destPath = $destDir . '/' . $destName;

        DB::beginTransaction();
        try {
            if (!Storage::disk($vaultDisk)->exists($destDir)) {
                Storage::disk($vaultDisk)->makeDirectory($destDir);
            }

            $readStream = Storage::disk($zipDisk)->readStream($zipPath);
            if ($readStream === false) {
                throw new \RuntimeException('No se pudo abrir stream del ZIP origen.');
            }

            try {
                $ok = Storage::disk($vaultDisk)->writeStream($destPath, $readStream);
            } finally {
                if (is_resource($readStream)) {
                    fclose($readStream);
                }
            }

            if (!$ok || !Storage::disk($vaultDisk)->exists($destPath)) {
                throw new \RuntimeException('No se pudo copiar el ZIP a bóveda.');
            }

            // Insertar sat_vault_files
            $this->insertVaultFileRow(
                cuentaId: $cuentaId,
                safeRfc: $safeRfc,
                downloadId: $downloadId,
                destName: $destName,
                destPath: $destPath,
                vaultDisk: $vaultDisk,
                zipSizeBytes: $zipBytes,
                userId: (string) (data_get($user, 'id', '') ?: data_get($download, 'created_by', '') ?: '')
            );

            // Marcar download como vaulted (si columnas existen)
            $this->markDownloadAsVaulted($download);

            // Incrementar uso bóveda (cuentas_cliente)
            $this->incrementCuentaClienteVaultUsedBytes($cuentaId, $zipBytes);

            DB::commit();

            Log::info('[SAT:Vault] ZIP movido a bóveda OK', [
                'download_id' => $downloadId,
                'cuenta_id'   => $cuentaId,
                'from'        => $zipDisk . ':' . $zipPath,
                'to'          => $vaultDisk . ':' . $destPath,
                'bytes'       => $zipBytes,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('[SAT:Vault] Error en moveZipToVault', [
                'download_id' => $downloadId,
                'cuenta_id'   => $cuentaId,
                'zipDisk'     => $zipDisk,
                'zipPath'     => $zipPath,
                'vaultDisk'   => $vaultDisk ?? null,
                'destPath'    => $destPath ?? null,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    // =====================================================
    //  RESOLVERS
    // =====================================================

    public function resolveSatZipDisk(): string
    {
        return config('filesystems.disks.sat_zip') ? 'sat_zip' : (string) config('filesystems.default', 'local');
    }

    private function resolveVaultDisk(): string
    {
        // Prioridad: sat_vault -> vault -> private -> default
        if (config('filesystems.disks.sat_vault')) {
            return 'sat_vault';
        }
        if (config('filesystems.disks.vault')) {
            return 'vault';
        }
        if (config('filesystems.disks.private')) {
            return 'private';
        }
        return (string) config('filesystems.default', 'local');
    }

    private function resolveZipLocationFromDownload(SatDownload $download): array
    {
        $downloadId = (string) ($download->id ?? '');
        $cuentaId   = (string) ($download->cuenta_id ?? '');

        // 0) Si existe zip_path, úsalo
        $zipPath = ltrim((string) ($download->zip_path ?? ''), '/');

        $meta = [];
        try {
            $m = data_get($download, 'meta');
            if (is_array($m)) $meta = $m;
            if (is_string($m) && $m !== '') {
                $tmp = json_decode($m, true);
                if (is_array($tmp)) $meta = $tmp;
            }
        } catch (\Throwable) {
            $meta = [];
        }

        $zipDisk = (string) (data_get($download, 'zip_disk', '') ?: ($meta['zip_disk'] ?? '') ?: (data_get($download, 'disk', '') ?: ''));

        if ($zipPath !== '') {
            foreach (array_values(array_unique(array_filter([$zipDisk, 'sat_zip', 'private', 'local', (string) config('filesystems.default', 'local')]))) as $d) {
                if ($d && $this->diskExistsSafe($d, $zipPath)) {
                    return [$d, $zipPath];
                }
            }
        }

        // 1) Candidatos alineados a "packages/pkg_"
        $candidates = [
            ['disk' => 'sat_zip',  'path' => "sat/packages/{$cuentaId}/pkg_{$downloadId}.zip"],
            ['disk' => 'private',  'path' => "sat/packages/{$cuentaId}/pkg_{$downloadId}.zip"],
            ['disk' => 'local',    'path' => "sat/packages/{$cuentaId}/pkg_{$downloadId}.zip"],
            // fallback legacy por si alguien dejó otro patrón
            ['disk' => 'sat_zip',  'path' => "sat/packages/{$cuentaId}/{$downloadId}.zip"],
            ['disk' => 'sat_zip',  'path' => "sat/demo/{$downloadId}.zip"],
        ];

        foreach ($candidates as $cand) {
            $d = (string) ($cand['disk'] ?? '');
            $p = ltrim((string) ($cand['path'] ?? ''), '/');
            if ($d !== '' && $p !== '' && $this->diskExistsSafe($d, $p)) {
                return [$d, $p];
            }
        }

        return [null, ''];
    }

    // =====================================================
    //  PERSISTENCIA / DB
    // =====================================================

    private function persistZipMetaOnDownload(SatDownload $download, string $diskName, string $zipRel, int $sizeBytes): void
    {
        try {
            $conn   = $download->getConnectionName() ?: 'mysql_clientes';
            $table  = $download->getTable() ?: 'sat_downloads';
            $schema = Schema::connection($conn);

            $upd = [
                'zip_path'   => $zipRel,
                'updated_at' => Carbon::now(),
            ];

            if ($schema->hasColumn($table, 'status') && empty($download->status)) {
                $upd['status'] = 'done';
            }

            if ($schema->hasColumn($table, 'size_bytes')) {
                $upd['size_bytes'] = $sizeBytes;
            }
            if ($schema->hasColumn($table, 'zip_bytes')) {
                $upd['zip_bytes'] = $sizeBytes;
            }

            if ($schema->hasColumn($table, 'zip_disk')) {
                $upd['zip_disk'] = $diskName;
            }
            if ($schema->hasColumn($table, 'disk')) {
                $upd['disk'] = $diskName;
            }

            if ($schema->hasColumn($table, 'meta')) {
                $upd['meta'] = DB::raw("JSON_SET(COALESCE(meta,'{}'), '$.zip_disk', '" . addslashes($diskName) . "')");
            }

            DB::connection($conn)->table($table)->where('id', (string) $download->id)->update($upd);

            Log::info('[SatDownloadZipHelper] SatDownload actualizado (tolerante)', [
                'download_id' => (string) $download->id,
                'zip_path'    => $zipRel,
                'disk'        => $diskName,
                'bytes'       => $sizeBytes,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SatDownloadZipHelper] No se pudo persistir zip meta en sat_downloads', [
                'download_id' => (string) ($download->id ?? ''),
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private function markDownloadAsVaulted(SatDownload $download): void
    {
        try {
            $conn   = $download->getConnectionName() ?: 'mysql_clientes';
            $table  = $download->getTable() ?: 'sat_downloads';
            $schema = Schema::connection($conn);

            $upd = ['updated_at' => Carbon::now()];

            if ($schema->hasColumn($table, 'vault_flag')) {
                $upd['vault_flag'] = 1;
            }
            if ($schema->hasColumn($table, 'in_vault')) {
                $upd['in_vault'] = 1;
            }
            if ($schema->hasColumn($table, 'vaulted_at')) {
                $upd['vaulted_at'] = Carbon::now();
            }

            if (count($upd) > 1) {
                DB::connection($conn)->table($table)->where('id', (string) $download->id)->update($upd);
            }
        } catch (\Throwable) {
            // no-op
        }
    }

    private function insertVaultFileRow(
        string $cuentaId,
        string $safeRfc,
        string $downloadId,
        string $destName,
        string $destPath,
        string $vaultDisk,
        int $zipSizeBytes,
        string $userId
    ): void {
        $connName = 'mysql_clientes';

        if (!Schema::connection($connName)->hasTable('sat_vault_files')) {
            Log::warning('[SAT:Vault] sat_vault_files no existe; no se insertó registro', [
                'cuenta_id' => $cuentaId,
                'path'      => $destPath,
            ]);
            return;
        }

        $has = function (string $col) use ($connName): bool {
            try {
                return Schema::connection($connName)->hasColumn('sat_vault_files', $col);
            } catch (\Throwable) {
                return false;
            }
        };

        $payload = [
            'cuenta_id'  => $cuentaId,
            'rfc'        => $safeRfc,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        if ($has('source'))     $payload['source'] = 'sat_download';
        if ($has('source_id'))  $payload['source_id'] = $downloadId;
        if ($has('filename'))   $payload['filename'] = $destName;
        if ($has('path'))       $payload['path'] = $destPath;
        if ($has('disk'))       $payload['disk'] = $vaultDisk;
        if ($has('mime'))       $payload['mime'] = 'application/zip';
        if ($has('created_by') && $userId !== '') $payload['created_by'] = $userId;

        if ($has('bytes'))      $payload['bytes'] = $zipSizeBytes;
        if ($has('size_bytes')) $payload['size_bytes'] = $zipSizeBytes;

        DB::connection($connName)->table('sat_vault_files')->insert($payload);
    }

    private function incrementCuentaClienteVaultUsedBytes(string $cuentaId, int $bytes): void
    {
        if ($cuentaId === '' || $bytes <= 0) {
            return;
        }

        try {
            $connName = 'mysql_clientes';
            $schema   = Schema::connection($connName);

            if (!$schema->hasTable('cuentas_cliente')) return;

            if ($schema->hasColumn('cuentas_cliente', 'vault_used_bytes')) {
                DB::connection($connName)
                    ->table('cuentas_cliente')
                    ->where('id', $cuentaId)
                    ->increment('vault_used_bytes', $bytes);
            }

            if ($schema->hasColumn('cuentas_cliente', 'vault_used_gb')) {
                $row = DB::connection($connName)->table('cuentas_cliente')->where('id', $cuentaId)->first();
                $prevBytes = 0;

                if ($row && isset($row->vault_used_bytes)) {
                    $prevBytes = (int) ($row->vault_used_bytes ?? 0);
                } elseif ($row) {
                    $prevGb    = (float) ($row->vault_used_gb ?? 0.0);
                    $prevBytes = (int) round($prevGb * 1024 * 1024 * 1024);
                }

                $newBytes = max(0, $prevBytes);
                $newGb    = round($newBytes / (1024 * 1024 * 1024), 4);

                DB::connection($connName)
                    ->table('cuentas_cliente')
                    ->where('id', $cuentaId)
                    ->update(['vault_used_gb' => $newGb]);
            }
        } catch (\Throwable $e) {
            Log::warning('[SAT:Vault] No se pudo incrementar uso de bóveda en cuentas_cliente', [
                'cuenta_id' => $cuentaId,
                'bytes'     => $bytes,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // =====================================================
    //  FILESYSTEM SAFE
    // =====================================================

    private function diskConfigured(string $disk): bool
    {
        return (bool) config("filesystems.disks.$disk");
    }

    private function diskExistsSafe(string $disk, string $path): bool
    {
        try {
            if (!$this->diskConfigured($disk)) return false;
            return Storage::disk($disk)->exists($path);
        } catch (\Throwable) {
            return false;
        }
    }

    private function diskSizeSafe(string $disk, string $path): int
    {
        try {
            if (!$this->diskConfigured($disk)) return 0;
            return (int) Storage::disk($disk)->size($path);
        } catch (\Throwable) {
            return 0;
        }
    }
}
