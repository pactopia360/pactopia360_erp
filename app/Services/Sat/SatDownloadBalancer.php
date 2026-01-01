<?php

declare(strict_types=1);

namespace App\Services\Sat;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Services\Sat\Providers\ProviderResult;
use App\Services\Sat\Providers\SatProviderRegistry;
use Illuminate\Support\Facades\Log;

final class SatDownloadBalancer
{
    public function __construct(private readonly SatProviderRegistry $registry) {}

    public function requestPackages(
        SatCredential $cred,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        string $tipo
    ): ProviderResult {
        $failover = (bool) config('services.sat.download.failover', true);

        foreach ($this->registry->ordered() as $provider) {
            if (!$provider->isAvailable()) {
                continue;
            }

            $t0 = microtime(true);

            try {
                $res = $provider->requestPackages($cred, $from, $to, $tipo);
                $res->meta['elapsed_ms'] = (int) round((microtime(true) - $t0) * 1000);

                if ($res->ok) {
                    return $res;
                }

                if (!$failover || !$this->isRetryable($res->errorCode)) {
                    return $res;
                }

                Log::warning('[SAT:Balancer] requestPackages failover', [
                    'provider' => $res->provider,
                    'code'     => $res->errorCode,
                    'msg'      => $res->errorMessage,
                ]);
            } catch (\Throwable $e) {
                $elapsed = (int) round((microtime(true) - $t0) * 1000);

                $res = ProviderResult::fail(
                    $provider->name(),
                    'EXCEPTION',
                    $e->getMessage(),
                    ['exception' => get_class($e)],
                    ['elapsed_ms' => $elapsed]
                );

                if (!$failover || !$this->isRetryable($res->errorCode)) {
                    return $res;
                }

                Log::warning('[SAT:Balancer] requestPackages exception failover', [
                    'provider'    => $provider->name(),
                    'exception'   => get_class($e),
                    'msg'         => $e->getMessage(),
                    'elapsed_ms'  => $elapsed,
                ]);
            }
        }

        return ProviderResult::fail('multi', 'NO_PROVIDER', 'No hay proveedores disponibles o todos fallaron.');
    }

    /**
     * Descarga/arma el ZIP usando providers (con failover).
     * $zipAbs es la ruta absoluta donde el provider debe escribir el ZIP final.
     */
    public function downloadPackage(SatCredential $cred, SatDownload $dl, ?string $pkgId = null): SatDownload
    {
        // LOCAL/DEV/TEST: asegurar ZIP + limpiar estado + importar CFDIs
        if (app()->environment(['local', 'development', 'testing'])) {
            $diskName = $this->resolveSatZipDisk();
            $disk     = Storage::disk($diskName);

            $zipRel = (string) ($dl->zip_path ?? '');
            if ($zipRel === '') {
                $zipRel = 'sat/packages/' . (string) $dl->cuenta_id . '/pkg_' . (string) $dl->id . '.zip';
            }
            $zipRel = ltrim($zipRel, '/');

            if (!$disk->exists($zipRel)) {
                $zipRel2 = $this->ensureLocalDemoZipWithCfdis($dl);
                if ($zipRel2) $zipRel = ltrim($zipRel2, '/');
            }

            if (!$disk->exists($zipRel)) {
                $dl->status = 'error';
                $dl->error_message = 'DEMO: ZIP no encontrado para downloadPackage';
                $dl->save();
                return $dl;
            }

            $bytes = 0;
            try { $bytes = (int) $disk->size($zipRel); } catch (\Throwable) {}

            // Persistir + limpiar basura
            try {
                $conn   = $dl->getConnectionName() ?: 'mysql_clientes';
                $table  = $dl->getTable();
                $schema = Schema::connection($conn);

                if ($schema->hasColumn($table, 'zip_path'))   $dl->zip_path   = $zipRel;
                if ($schema->hasColumn($table, 'zip_bytes'))  $dl->zip_bytes  = $bytes;
                if ($schema->hasColumn($table, 'size_bytes')) $dl->size_bytes = $bytes;
                if ($schema->hasColumn($table, 'size_mb'))    $dl->size_mb    = $bytes > 0 ? round($bytes / 1024 / 1024, 4) : 0.0;

                $dl->status = 'done';
                $dl->error_message = null;

                // Para no bloquear UI en local
                if ($schema->hasColumn($table, 'paid_at') && empty($dl->paid_at)) {
                    $dl->paid_at = now();
                }
                if ($schema->hasColumn($table, 'expires_at') && empty($dl->expires_at)) {
                    $dl->expires_at = now()->addHours((int) config('services.sat.download.demo_ttl_hours', 24));
                }
            } catch (\Throwable) {
                $dl->zip_path = $zipRel;
                $dl->status = 'done';
                $dl->error_message = null;
                if (empty($dl->paid_at)) $dl->paid_at = now();
            }

            $dl->save();

            // Registrar VaultFile para que VaultController encuentre el ZIP por source/source_id
            try {
                $this->registerZipIntoVaultFilesIfMissing([
                    'cuenta_id'   => (string) $dl->cuenta_id,
                    'rfc'         => (string) $dl->rfc,
                    'download_id' => (string) $dl->id,
                    'disk'        => $diskName,
                    'zip_rel'     => $zipRel,
                    'size_bytes'  => $bytes,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[SatDownloadService] downloadPackage: no se pudo registrar VaultFile', [
                    'download_id' => (string) $dl->id,
                    'ex'          => $e->getMessage(),
                ]);
            }

            // Importar CFDIs (MANIFEST o XML)
            try {
                $this->importCfdisFromZip($dl, $disk, $zipRel);
            } catch (\Throwable $e) {
                Log::warning('[SatDownloadService] downloadPackage: importCfdisFromZip falló', [
                    'download_id' => (string) $dl->id,
                    'ex'          => $e->getMessage(),
                ]);
            }

            return $dl;
        }

        // ==========================
        // PROD/STAGING: delegar a balancer (FIRMA CORRECTA)
        // ==========================

        // Resolver donde debe escribirse el ZIP final
        [$zipDisk, $zipRel] = $this->getZipStoragePath($dl);
        $zipRel = ltrim($zipRel, '/');

        $disk = Storage::disk($zipDisk);
        $zipAbs = $disk->path($zipRel);

        // Asegurar directorio
        $dir = \dirname($zipAbs);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // Balancer escribe ZIP en $zipAbs
        $res = $this->balancer->downloadPackage($cred, $dl, $zipAbs);

        // Persistir ruta y status si OK
        if (($res->ok ?? false) && is_file($zipAbs)) {
            $dl->zip_path = $zipRel;
            $dl->status = 'done';
            $dl->error_message = null;

            try {
                $bytes = (int) filesize($zipAbs);
                if (Schema::connection($dl->getConnectionName() ?: 'mysql_clientes')->hasColumn($dl->getTable(), 'size_bytes')) {
                    $dl->size_bytes = $bytes;
                }
                if (Schema::connection($dl->getConnectionName() ?: 'mysql_clientes')->hasColumn($dl->getTable(), 'size_mb')) {
                    $dl->size_mb = $bytes > 0 ? round($bytes / 1024 / 1024, 4) : 0.0;
                }
            } catch (\Throwable) {
                // no-op
            }

            $dl->save();
            return $dl;
        }

        // No OK
        $dl->status = 'error';
        $dl->error_message = ($res->errorMessage ?? null) ?: 'Error descargando paquete (provider).';
        $dl->save();

        return $dl;
    }


    private function tryProviderDownload(
        $provider,
        SatCredential $cred,
        SatDownload $download,
        string $zipAbs
    ): ProviderResult {
        $t0 = microtime(true);

        try {
            $res = $provider->downloadPackage($cred, $download, $zipAbs);
            $res->meta['elapsed_ms'] = (int) round((microtime(true) - $t0) * 1000);
            return $res;
        } catch (\Throwable $e) {
            $elapsed = (int) round((microtime(true) - $t0) * 1000);

            return ProviderResult::fail(
                $provider->name(),
                'EXCEPTION',
                $e->getMessage(),
                ['exception' => get_class($e)],
                ['elapsed_ms' => $elapsed]
            );
        }
    }

    private function isRetryable(?string $code): bool
    {
        $code = strtoupper((string) $code);

        return in_array($code, [
            'TIMEOUT',
            'NETWORK',
            'RATE_LIMIT',
            'UPSTREAM_503',
            'TEMPORARY',
            'EXCEPTION',
        ], true);
    }
}
