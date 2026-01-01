<?php

declare(strict_types=1);

namespace App\Services\Sat;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use App\Models\Cliente\VaultFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SatDownloadService
{
    public function __construct(private readonly SatDownloadBalancer $balancer) {}

    /* ==========================================================
     | HELPERS INTERNOS
     * ==========================================================*/

    private function resolveSatZipDisk(): string
    {
        return config('filesystems.disks.sat_zip') ? 'sat_zip' : config('filesystems.default', 'local');
    }

    private function resolveVaultDisk(): string
    {
        if (config('filesystems.disks.sat_vault')) return 'sat_vault';
        if (config('filesystems.disks.vault')) return 'vault';
        if (config('filesystems.disks.private')) return 'private';
        return $this->resolveSatZipDisk();
    }

    private function decrypt(string $enc): string
    {
        try {
            return ($enc !== '') ? Crypt::decryptString($enc) : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function schemaHas(string $conn, string $table, string $column): bool
    {
        try {
            return Schema::connection($conn)->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    /* ==========================================================
     | CREDENCIALES
     * ==========================================================*/

    public function upsertCredentials(
        string $cuentaId,
        string $rfc,
        ?UploadedFile $cer,
        ?UploadedFile $key,
        string $password
    ): SatCredential {
        $cred = SatCredential::updateOrCreate(
            ['cuenta_id' => $cuentaId, 'rfc' => strtoupper($rfc)],
            []
        );

        if ($cer instanceof UploadedFile) {
            $path           = $cer->store('sat/certs/' . $cuentaId, 'public');
            $cred->cer_path = $path;
        }

        if ($key instanceof UploadedFile) {
            $path           = $key->store('sat/keys/' . $cuentaId, 'public');
            $cred->key_path = $path;
        }

        if ($password !== '') {
            $enc                    = Crypt::encryptString($password);
            $cred->key_password_enc = $enc;

            // Soporte legacy: columna key_password (si existe)
            try {
                $conn = $cred->getConnectionName() ?: config('database.default');
                if (Schema::connection($conn)->hasColumn($cred->getTable(), 'key_password')) {
                    $cred->key_password = $enc;
                }
            } catch (\Throwable) {
                // no-op
            }
        }

        $cred->save();
        return $cred;
    }

    public function validateCredentials(SatCredential $cred): bool
    {
        try {
            [, $info] = $this->buildFiel($cred);
            $cred->validated_at = now();
            $cred->save();

            Log::info('[SAT] Credenciales OK', [
                'rfc'    => $cred->rfc,
                'expira' => $info['validTo'] ?? null,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[SAT] Credenciales inválidas', [
                'rfc' => $cred->rfc,
                'ex'  => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function buildFiel(SatCredential $cred): array
    {
        $cerAbs = $cred->cer_path ? Storage::disk('public')->path($cred->cer_path) : '';
        $keyAbs = $cred->key_path ? Storage::disk('public')->path($cred->key_path) : '';

        if (!is_file($cerAbs) || !is_file($keyAbs)) {
            throw new \RuntimeException('Archivos .cer/.key no encontrados');
        }

        $pwd = $this->decrypt($cred->key_password_enc ?? $cred->key_password ?? '');

        /** @var \PhpCfdi\Credentials\Credential $credential */
        $credential  = \PhpCfdi\Credentials\Credential::openFiles($cerAbs, $keyAbs, $pwd);
        $certificate = $credential->certificate();

        $fmt = static function ($v): ?string {
            if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d H:i:s');
            if (is_string($v) && trim($v) !== '') return trim($v);
            return null;
        };

        $validFrom = null;
        $validTo   = null;

        try { $validFrom = $certificate->validFrom(); } catch (\Throwable) {}
        try { $validTo   = $certificate->validTo(); }   catch (\Throwable) {}

        $info = [
            'rfc'       => $certificate->rfc(),
            'serial'    => $certificate->serialNumber()->bytes(),
            'validFrom' => $fmt($validFrom),
            'validTo'   => $fmt($validTo),
        ];

        return [$credential, $info];
    }

    /* ==========================================================
     | SOLICITUDES
     * ==========================================================*/

    public function requestPackages(
        SatCredential $cred,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        string $tipo
    ): SatDownload {
        $tipo = strtolower(trim($tipo));
        if (!in_array($tipo, ['emitidos', 'recibidos'], true)) $tipo = 'emitidos';

        $from = ($from instanceof \DateTimeImmutable) ? $from : \DateTimeImmutable::createFromInterface($from);
        $to   = ($to   instanceof \DateTimeImmutable) ? $to   : \DateTimeImmutable::createFromInterface($to);
        if ($to < $from) [$from, $to] = [$to, $from];

        $localReqId = 'REQ-' . strtoupper(substr(bin2hex(random_bytes(16)), 0, 12));

        $download = SatDownload::create([
            'cuenta_id'     => (string) $cred->cuenta_id,
            'rfc'           => strtoupper((string) $cred->rfc),
            'tipo'          => $tipo,
            'date_from'     => $from->format('Y-m-d'),
            'date_to'       => $to->format('Y-m-d'),
            'status'        => 'pending',
            'request_id'    => $localReqId,
            'paid_at'       => null,
            'expires_at'    => null,
            'error_message' => null,
        ]);

        $driver = (string) config('services.sat.download.driver', 'multi');

        // PROD/STAGING: usar balancer (multi o satws)
        if (app()->environment(['production', 'staging']) && in_array($driver, ['multi', 'satws'], true)) {
            $t0 = microtime(true);

            try {
                $res       = $this->balancer->requestPackages($cred, $from, $to, $tipo);
                $elapsedMs = (int) round((microtime(true) - $t0) * 1000);

                // Persistencia defensiva (si existen columnas)
                try {
                    $conn   = $download->getConnectionName() ?: config('database.default');
                    $table  = $download->getTable();
                    $schema = Schema::connection($conn);

                    if ($schema->hasColumn($table, 'provider')) $download->provider = $res->provider ?? null;
                    if ($schema->hasColumn($table, 'provider_ref')) $download->provider_ref = $res->providerRef ?? null;
                    if ($schema->hasColumn($table, 'error_code')) $download->error_code = $res->errorCode ?? null;

                    if ($schema->hasColumn($table, 'error_context')) {
                        $download->error_context = !empty($res->errorContext)
                            ? json_encode($res->errorContext, JSON_UNESCAPED_UNICODE)
                            : null;
                    }

                    if ($schema->hasColumn($table, 'error_at')) {
                        $download->error_at = ($res->ok ?? false) ? null : now();
                    }

                    if ($schema->hasColumn($table, 'attempts')) {
                        $download->attempts = (int) (($download->attempts ?? 0) + 1);
                    }

                    $metaPayload               = (array)($res->meta ?? []);
                    $metaPayload['elapsed_ms'] = $elapsedMs;

                    if ($schema->hasColumn($table, 'meta_json')) {
                        $download->meta_json = json_encode($metaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    } elseif ($schema->hasColumn($table, 'meta')) {
                        $download->meta = json_encode($metaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[SAT] requestPackages: no se pudo persistir metadata provider', [
                        'download_id' => (string) $download->id,
                        'ex'          => $e->getMessage(),
                    ]);
                }

                if (($res->ok ?? false) && !empty($res->providerRef)) {
                    $download->request_id    = (string) $res->providerRef;
                    $download->status        = 'processing';
                    $download->error_message = null;
                    $download->save();

                    Log::info('[SAT] Solicitud enviada (balancer)', [
                        'download_id' => (string) $download->id,
                        'rfc'         => (string) $cred->rfc,
                        'tipo'        => $tipo,
                        'provider'    => $res->provider ?? null,
                        'req'         => (string) $res->providerRef,
                        'elapsed_ms'  => $elapsedMs,
                    ]);

                    return $download;
                }

                $download->status        = 'error';
                $download->error_message = ($res->errorMessage ?? null) ?: 'Solicitud fallida (sin detalle)';
                $download->save();

                Log::error('[SAT] Solicitud fallida (balancer)', [
                    'download_id' => (string) $download->id,
                    'rfc'         => (string) $cred->rfc,
                    'tipo'        => $tipo,
                    'provider'    => $res->provider ?? null,
                    'code'        => $res->errorCode ?? null,
                    'msg'         => $res->errorMessage ?? null,
                ]);

                return $download;
            } catch (\Throwable $e) {
                $download->status        = 'error';
                $download->error_message = 'Solicitud multi: ' . $e->getMessage();

                try {
                    $conn   = $download->getConnectionName() ?: config('database.default');
                    $table  = $download->getTable();
                    $schema = Schema::connection($conn);

                    if ($schema->hasColumn($table, 'error_code')) $download->error_code = 'EXCEPTION';
                    if ($schema->hasColumn($table, 'error_context')) {
                        $download->error_context = json_encode(['exception' => get_class($e)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    if ($schema->hasColumn($table, 'error_at')) $download->error_at = now();
                } catch (\Throwable) {}

                $download->save();

                Log::error('[SAT] Error requestPackages (prod/staging)', [
                    'download_id' => (string) $download->id,
                    'rfc'         => (string) $cred->rfc,
                    'tipo'        => $tipo,
                    'ex'          => $e->getMessage(),
                ]);

                return $download;
            }
        }

        // LOCAL/DEV/TEST: DEMO con ZIP real
        Log::info('[SatDownloadService] DEMO: requestPackages, generando ZIP de prueba', [
            'download_id' => (string) $download->id,
            'cuenta_id'   => (string) $download->cuenta_id,
            'rfc'         => (string) $download->rfc,
            'tipo'        => $tipo,
        ]);

        try {
            $diskName = $this->resolveSatZipDisk();
            $disk     = Storage::disk($diskName);

            $folder = 'sat/packages/' . (string) $download->cuenta_id;
            $fname  = 'pkg_' . (string) $download->id . '.zip';

            $zipRel = $folder . '/' . $fname;
            $zipAbs = $disk->path($zipRel);

            $dir = \dirname($zipAbs);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);

            $ok = $this->buildDemoZip(
                zipPath:   $zipAbs,
                rfc:       (string) $download->rfc,
                requestId: (string) ($download->request_id ?? ''),
                count:     (int) config('services.sat.download.demo_xml_count', 8),
                tipo:      (string) $download->tipo,
                from:      (string) ($download->date_from ?? ''),
                to:        (string) ($download->date_to ?? '')
            );

            if ($ok && $disk->exists($zipRel)) {
                $bytes = (int) $disk->size($zipRel);

                $download->zip_path      = $zipRel;
                $download->status        = 'done';
                $download->error_message = null;

                $conn  = $download->getConnectionName() ?: config('database.default');
                $table = $download->getTable();

                if ($this->schemaHas($conn, $table, 'zip_disk')) $download->zip_disk = $diskName;
                if ($this->schemaHas($conn, $table, 'zip_bytes')) $download->zip_bytes = $bytes;
                if ($this->schemaHas($conn, $table, 'size_bytes')) $download->size_bytes = $bytes;
                if ($this->schemaHas($conn, $table, 'size_mb')) $download->size_mb = $bytes > 0 ? round($bytes / 1024 / 1024, 4) : 0.0;
                if ($this->schemaHas($conn, $table, 'attempts')) $download->attempts = (int) (($download->attempts ?? 0) + 1);

                if (!$download->expires_at) {
                    $download->expires_at = now()->addHours((int) config('services.sat.download.demo_ttl_hours', 24));
                }

                $download->save();

                // Crear VaultFile (si aplica)
                try {
                    $this->registerZipIntoVaultFilesIfMissing([
                        'cuenta_id'   => (string) $download->cuenta_id,
                        'rfc'         => (string) $download->rfc,
                        'download_id' => (string) $download->id,
                        'disk'        => $diskName,
                        'zip_rel'     => $zipRel,
                        'size_bytes'  => $bytes,
                    ]);
                } catch (\Throwable) {}

                Log::info('[SatDownloadService] DEMO: ZIP generado', [
                    'download_id' => (string) $download->id,
                    'zip_rel'     => $zipRel,
                    'disk'        => $diskName,
                    'size_bytes'  => $bytes,
                ]);

                return $download;
            }

            $download->status        = 'error';
            $download->error_message = 'No se pudo crear ZIP demo desde requestPackages';
            $download->save();

            return $download;
        } catch (\Throwable $e) {
            $download->status        = 'error';
            $download->error_message = 'DEMO ZIP: ' . $e->getMessage();
            $download->save();

            Log::error('[SatDownloadService] DEMO: error generando ZIP', [
                'download_id' => (string) $download->id,
                'ex'          => $e->getMessage(),
            ]);

            return $download;
        }
    }

    /* ==========================================================
     | VAULT FILES (ZIP) - helper
     * ==========================================================*/

    private function registerZipIntoVaultFilesIfMissing(array $data): void
    {
        $cuentaId   = (string) ($data['cuenta_id'] ?? '');
        $rfc        = (string) ($data['rfc'] ?? '');
        $downloadId = (string) ($data['download_id'] ?? '');
        $disk       = (string) ($data['disk'] ?? $this->resolveSatZipDisk());
        $zipRel     = ltrim((string) ($data['zip_rel'] ?? ''), '/');
        $bytes      = (int)    ($data['size_bytes'] ?? 0);

        if ($cuentaId === '' || $rfc === '' || $downloadId === '' || $zipRel === '') return;

        try {
            $exists = VaultFile::query()
                ->where('cuenta_id', $cuentaId)
                ->where('source', 'sat_download')
                ->where('source_id', $downloadId)
                ->where(function ($w) {
                    $w->where('mime', 'application/zip')
                      ->orWhere('filename', 'like', '%.zip')
                      ->orWhere('path', 'like', '%.zip');
                })
                ->exists();

            if ($exists) return;

            VaultFile::query()->create([
                'cuenta_id' => $cuentaId,
                'rfc'       => strtoupper($rfc),
                'source'    => 'sat_download',
                'source_id' => $downloadId,
                'filename'  => basename($zipRel),
                'path'      => $zipRel,
                'disk'      => $disk,
                'mime'      => 'application/zip',
                'bytes'     => $bytes,
            ]);

            Log::info('[SatDownloadService] VaultFile creado para ZIP', [
                'cuenta_id'   => $cuentaId,
                'download_id' => $downloadId,
                'disk'        => $disk,
                'path'        => $zipRel,
                'bytes'       => $bytes,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SatDownloadService] registerZipIntoVaultFilesIfMissing falló', [
                'download_id' => $downloadId,
                'ex'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ruta RELATIVA del ZIP.
     * Retorna [disk, pathRel].
     */
    public function getZipStoragePath(SatDownload $download): array
    {
        $diskDefault = $this->resolveSatZipDisk();

        // Si tu tabla ya soporta zip_disk, úsalo; si no, cae al default
        $diskDb = (string) ($download->zip_disk ?? '');
        $diskDb = $diskDb !== '' ? $diskDb : $diskDefault;

        $zipPath = ltrim((string) ($download->zip_path ?? ''), '/');

        // 1) zip_path existente: validar en discos típicos
        if ($zipPath !== '') {
            foreach (array_unique([$diskDb, $diskDefault, 'sat_zip', 'private', 'local']) as $tryDisk) {
                try {
                    if (config("filesystems.disks.{$tryDisk}") && Storage::disk($tryDisk)->exists($zipPath)) {
                        // Si existe columna zip_disk, la alineamos
                        try {
                            $conn  = $download->getConnectionName() ?: 'mysql_clientes';
                            $table = $download->getTable();
                            if ($this->schemaHas($conn, $table, 'zip_disk') && ($download->zip_disk ?? '') !== $tryDisk) {
                                $download->zip_disk = $tryDisk;
                                $download->save();
                            }
                        } catch (\Throwable) {}
                        return [$tryDisk, $zipPath];
                    }
                } catch (\Throwable) {}
            }
        }

        // 2) Convención estándar
        $candidate = 'sat/packages/' . (string) $download->cuenta_id . '/pkg_' . (string) $download->id . '.zip';

        foreach (array_unique([$diskDb, $diskDefault, 'sat_zip', 'private', 'local']) as $tryDisk) {
            try {
                if (config("filesystems.disks.{$tryDisk}") && Storage::disk($tryDisk)->exists($candidate)) {
                    // Persistimos si existen columnas (sin ensuciar si no existe)
                    try {
                        $conn  = $download->getConnectionName() ?: 'mysql_clientes';
                        $table = $download->getTable();
                        if ($this->schemaHas($conn, $table, 'zip_path')) $download->zip_path = $candidate;
                        if ($this->schemaHas($conn, $table, 'zip_disk')) $download->zip_disk = $tryDisk;
                        if ($download->isDirty()) $download->save();
                    } catch (\Throwable) {}
                    return [$tryDisk, $candidate];
                }
            } catch (\Throwable) {}
        }

        // 3) No existe aún
        return [$diskDefault, $candidate];
    }

    public function downloadPackage(SatCredential $cred, SatDownload $dl, ?string $pkgId = null): SatDownload
    {
        // LOCAL/DEV/TEST: el ZIP ya se genera en requestPackages; aquí solo aseguramos y hacemos ingest.
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
                $dl->status        = 'error';
                $dl->error_message = 'DEMO: ZIP no encontrado para downloadPackage';
                $dl->save();
                return $dl;
            }

            $bytes = 0;
            try { $bytes = (int) $disk->size($zipRel); } catch (\Throwable) {}

            $conn  = $dl->getConnectionName() ?: 'mysql_clientes';
            $table = $dl->getTable();

            // Persistencia defensiva
            if ($this->schemaHas($conn, $table, 'zip_path')) $dl->zip_path = $zipRel;
            if ($this->schemaHas($conn, $table, 'zip_disk')) $dl->zip_disk = $diskName;
            if ($this->schemaHas($conn, $table, 'zip_bytes')) $dl->zip_bytes = $bytes;
            if ($this->schemaHas($conn, $table, 'size_bytes')) $dl->size_bytes = $bytes;
            if ($this->schemaHas($conn, $table, 'size_mb')) $dl->size_mb = $bytes > 0 ? round($bytes / 1024 / 1024, 4) : 0.0;

            $dl->status        = 'done';
            $dl->error_message = null;

            if ($this->schemaHas($conn, $table, 'paid_at') && empty($dl->paid_at)) $dl->paid_at = now();

            if ($this->schemaHas($conn, $table, 'expires_at') && empty($dl->expires_at)) {
                $dl->expires_at = now()->addHours((int) config('services.sat.download.demo_ttl_hours', 24));
            }

            $dl->save();

            // Registrar en sat_vault_files (si falta)
            $this->registerZipIntoVaultFilesIfMissing([
                'cuenta_id'   => (string) $dl->cuenta_id,
                'rfc'         => (string) $dl->rfc,
                'download_id' => (string) $dl->id,
                'disk'        => $diskName,
                'zip_rel'     => $zipRel,
                'size_bytes'  => $bytes,
            ]);

            // Importar CFDIs desde ZIP -> cfdis (si existe tabla)
            try {
                $this->importCfdisFromZip($dl, $disk, $zipRel);
            } catch (\Throwable $e) {
                Log::warning('[SatDownloadService] importCfdisFromZip falló', [
                    'download_id' => (string) $dl->id,
                    'ex'          => $e->getMessage(),
                ]);
            }

            return $dl;
        }

        // PROD/STAGING: delegar al balancer
        $res = $this->balancer->downloadPackage($cred, $dl, $pkgId);

        $zipPath = (string) ($res->zip_path ?? $res->zipPath ?? '');
        $status  = (string) ($res->status ?? '');

        if ($zipPath !== '') $dl->zip_path = ltrim($zipPath, '/');
        if ($status !== '') $dl->status = $status;

        $dl->error_message = null;
        $dl->save();

        return $dl;
    }

    public function canClientDownload(SatDownload $dl): bool
    {
        $isPaid = false;

        foreach (['is_paid', 'paid', 'pagado'] as $fld) {
            $v = $dl->{$fld} ?? null;
            if (!is_null($v) && $v !== '' && $v !== 0 && $v !== false) {
                $isPaid = true;
                break;
            }
        }

        if (!$isPaid && !empty($dl->paid_at)) $isPaid = true;

        $isExpired = false;
        if (!empty($dl->expires_at)) {
            try {
                $exp = ($dl->expires_at instanceof Carbon) ? $dl->expires_at : Carbon::parse((string) $dl->expires_at);
                $isExpired = $exp->isPast();
            } catch (\Throwable) {}
        }

        $statusRaw = (string) ($dl->status ?? $dl->estado ?? $dl->sat_status ?? '');
        $statusLow = strtolower(trim($statusRaw));

        $badStatuses = ['error','errored','failed','cancelled','canceled','cancelado','cancelada','expirado','expirada','expired'];
        if (in_array($statusLow, $badStatuses, true)) return false;

        return $isPaid && !$isExpired;
    }

    /**
     * (LOCAL) Incrementa uso de bóveda en cuentas_cliente si existen columnas vault_used_*.
     */
    public function enqueueVaultIngestion(SatDownload $dl): void
    {
        try {
            $diskName = $this->resolveSatZipDisk();
            $disk     = Storage::disk($diskName);

            $bytes = null;

            if (isset($dl->zip_bytes) && $dl->zip_bytes) {
                $bytes = (int) $dl->zip_bytes;
            } elseif (!empty($dl->zip_path) && $disk->exists($dl->zip_path)) {
                $bytes = (int) $disk->size($dl->zip_path);
            }

            if (!$bytes || $bytes <= 0) return;

            $conn         = $dl->getConnectionName() ?: 'mysql_clientes';
            $cuentasTable = 'cuentas_cliente';

            if (!Schema::connection($conn)->hasTable($cuentasTable)) return;

            $schema      = Schema::connection($conn);
            $hasBytesCol = $schema->hasColumn($cuentasTable, 'vault_used_bytes');
            $hasGbCol    = $schema->hasColumn($cuentasTable, 'vault_used_gb');

            if (!$hasBytesCol && !$hasGbCol) return;
            if (empty($dl->cuenta_id)) return;

            $row       = DB::connection($conn)->table($cuentasTable)->where('id', $dl->cuenta_id)->first();
            $prevBytes = 0;

            if ($row) {
                if ($hasBytesCol) $prevBytes = (int) ($row->vault_used_bytes ?? 0);
                elseif ($hasGbCol) $prevBytes = (int) round(((float) ($row->vault_used_gb ?? 0.0)) * 1024 * 1024 * 1024);
            }

            $newBytes = max(0, $prevBytes + (int)$bytes);
            $newGb    = round($newBytes / (1024 * 1024 * 1024), 4);

            $update = [];
            if ($hasBytesCol) $update['vault_used_bytes'] = $newBytes;
            if ($hasGbCol) $update['vault_used_gb'] = $newGb;

            if ($update) {
                DB::connection($conn)->table($cuentasTable)->where('id', $dl->cuenta_id)->update($update);
            }
        } catch (\Throwable $e) {
            Log::warning('[Vault] enqueueVaultIngestion error', [
                'download_id' => $dl->id ?? null,
                'cuenta_id'   => $dl->cuenta_id ?? null,
                'ex'          => $e->getMessage(),
            ]);
        }
    }

    /* ==========================================================
     | SATWS
     * ==========================================================*/

    private function satwsSolicitar(
        SatCredential $cred,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        string $tipo
    ): string {
        [$credential, ] = $this->buildFiel($cred);

        $client = $this->buildSatWsClient($credential);

        $isEmitidos = ($tipo === 'emitidos');

        $fiel = new \PhpCfdi\SatWsDescargaMasiva\Shared\Fiel(
            $credential->certificate(),
            $credential->privateKey()
        );

        $service = new \PhpCfdi\SatWsDescargaMasiva\Services\DownloadService($client, $fiel);

        $rfc      = strtoupper($cred->rfc);
        $criteria = $isEmitidos
            ? \PhpCfdi\SatWsDescargaMasiva\PackageReader\Filters\Criteria::issued($rfc, $from, $to)
            : \PhpCfdi\SatWsDescargaMasiva\PackageReader\Filters\Criteria::received($rfc, $from, $to);

        $request = $service->request($criteria);
        if (!$request->isAccepted()) {
            throw new \RuntimeException('SAT rechazó la solicitud: ' . $request->getMessage());
        }

        return (string) $request->getRequestId();
    }

    private function satwsDescargar(SatCredential $cred, SatDownload $download, string $zipAbs): void
    {
        [$credential, ] = $this->buildFiel($cred);
        $client  = $this->buildSatWsClient($credential);

        $fiel    = new \PhpCfdi\SatWsDescargaMasiva\Shared\Fiel(
            $credential->certificate(),
            $credential->privateKey()
        );
        $service = new \PhpCfdi\SatWsDescargaMasiva\Services\DownloadService($client, $fiel);

        $requestId = (string) ($download->request_id ?? '');
        if ($requestId === '') throw new \RuntimeException('Falta request_id');

        $verify = $service->verify($requestId);
        if (!$verify->isAccepted()) throw new \RuntimeException('SAT no aceptó la verificación: ' . $verify->getMessage());

        $packageIds = $verify->getPackageIds();
        if (empty($packageIds)) {
            $this->buildReadmeOnly($zipAbs, (string)$download->rfc, $requestId);
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipAbs, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo abrir ZIP destino');
        }

        $manifest   = [];
        $manifest[] = ['archivo', 'tipo', 'uuid', 'rfc_emisor', 'rfc_receptor', 'fecha', 'total'];

        foreach ($packageIds as $pid) {
            $packageResponse = $service->download($pid);
            if (!$packageResponse->isAccepted()) {
                Log::warning('[SATWS] Paquete no aceptado', ['package' => $pid, 'msg' => $packageResponse->getMessage()]);
                continue;
            }

            $content = $packageResponse->getPackageContent();
            $tmp     = tmpfile();
            $meta    = stream_get_meta_data($tmp);
            $tmpPath = $meta['uri'];
            file_put_contents($tmpPath, $content);

            $inner = new \ZipArchive();
            if ($inner->open($tmpPath) === true) {
                for ($i = 0; $i < $inner->numFiles; $i++) {
                    $name = $inner->getNameIndex($i);
                    if (!$name) continue;

                    $xml = $inner->getFromName($name);
                    if ($xml === false) continue;

                    $base = $this->normalizeXmlName($name, (string)$download->rfc);
                    $zip->addFromString('XML/' . $base, $xml);

                    [$uuid, $emi, $rec, $fecha, $total] = $this->quickParseCfdi($xml);
                    $manifest[] = [$base, 'XML', $uuid, $emi, $rec, $fecha, $total];

                    $this->persistCfdiRow($download, $uuid, $emi, $rec, $fecha, $total);

                    $pdfName = preg_replace('~\.xml$~i', '.pdf', $base);
                    $zip->addFromString('PDF/' . $pdfName, $this->tinyPdf(
                        "CFDI: {$uuid}\nEmisor: {$emi}\nReceptor: {$rec}\nFecha: {$fecha}\nTotal: {$total}\n"
                    ));
                    $manifest[] = [$pdfName, 'PDF', $uuid, $emi, $rec, $fecha, $total];
                }

                $inner->close();
            }

            fclose($tmp);
        }

        $zip->addFromString('README.txt', "Paquete SAT real\nRFC: {$download->rfc}\nRequest: {$requestId}\nRango: {$download->date_from} a {$download->date_to}\n");
        $zip->addFromString('MANIFEST.csv', $this->csvUtf8($manifest));
        $zip->close();
    }

    private function buildSatWsClient(\PhpCfdi\Credentials\Credential $credential): \PhpCfdi\SatWsDescargaMasiva\Shared\Soap\SoapClientInterface
    {
        $timeout     = (int) config('services.sat.download.http_timeout', 60);
        $soapFactory = new \PhpCfdi\SatWsDescargaMasiva\Shared\Soap\SoapClientFactory($timeout);
        return $soapFactory->createClient();
    }

    /* ==========================================================
     | DEMO / UTILS
     * ==========================================================*/

    private function buildZipFromDatabase(string $zipPath, SatDownload $download): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) return false;

        $zip->addFromString('README.txt', "Driver=database\nRFC: {$download->rfc}\nRango: {$download->date_from} a {$download->date_to}\n");
        $zip->close();

        return true;
    }

    private function buildDemoZip(
        string $zipPath,
        string $rfc,
        string $requestId,
        int $count = 5,
        string $tipo = 'emitidos',
        string $from = '',
        string $to = ''
    ): bool {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) return false;

        $zip->addFromString('README.txt',
            "Paquete DEMO de descargas SAT\nRFC: {$rfc}\nTipo: {$tipo}\nRango: {$from} a {$to}\nRequest: {$requestId}\nContenido: XML/, PDF/, MANIFEST.csv, reportes/\n"
        );

        $manifestRows   = [];
        $manifestRows[] = ['archivo', 'tipo', 'uuid', 'rfc_emisor', 'rfc_receptor', 'fecha', 'total'];

        for ($i = 1; $i <= max(1, $count); $i++) {
            $uuid  = $this->uuidV4();
            $fecha = now()->subDays(rand(0, 27))->format('Y-m-d');
            $total = number_format(mt_rand(1000, 250000) / 100, 2, '.', '');
            $emi   = strtoupper($rfc);
            $rec   = ($tipo === 'emitidos') ? 'XAXX010101000' : strtoupper($rfc);

            $base    = "{$fecha}_{$uuid}_{$emi}_{$rec}";
            $xmlName = "XML/{$base}.xml";
            $pdfName = "PDF/{$base}.pdf";

            $xml = $this->minimalCfdi40($uuid, $fecha, $total, $emi, $rec);
            $zip->addFromString($xmlName, $xml);

            $zip->addFromString($pdfName, $this->tinyPdf(
                "CFDI: {$uuid}\nRFC Emisor: {$emi}\nRFC Receptor: {$rec}\nFecha: {$fecha}\nTotal: {$total}\n"
            ));

            $manifestRows[] = [$base . '.xml', 'XML', $uuid, $emi, $rec, $fecha, $total];
            $manifestRows[] = [$base . '.pdf', 'PDF', $uuid, $emi, $rec, $fecha, $total];
        }

        $manifestCsv = $this->csvUtf8($manifestRows);
        $zip->addFromString('MANIFEST.csv', $manifestCsv);
        $zip->addFromString('reportes/resumen_descarga.csv', $manifestCsv);

        $zip->addFromString('reportes/REP_' . $tipo . '_' . now()->format('Ymd') . '.pdf', $this->tinyPdf(
            "Reporte DEMO de descarga SAT\nRFC: {$rfc}\nTipo: {$tipo}\nRango: {$from} a {$to}\n"
        ));

        $zip->close();
        return true;
    }

    public function ensureLocalDemoZipWithCfdis(SatDownload $download): ?string
    {
        if (!app()->environment(['local', 'development', 'testing'])) return null;

        $diskName = $this->resolveSatZipDisk();
        $disk     = Storage::disk($diskName);

        $folder = 'sat/packages/' . (string)$download->cuenta_id;
        $fname  = 'pkg_' . (string)$download->id . '.zip';

        $zipRel = $folder . '/' . $fname;
        $zipAbs = $disk->path($zipRel);

        if ($disk->exists($zipRel) && (int) $disk->size($zipRel) > 0) return $zipRel;

        $dir = \dirname($zipAbs);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $ok = $this->buildDemoZip(
            zipPath:   $zipAbs,
            rfc:       (string) ($download->rfc ?? 'XAXX010101000'),
            requestId: (string) ($download->request_id ?? ''),
            count:     8,
            tipo:      (string) ($download->tipo ?? 'emitidos'),
            from:      (string) ($download->date_from ?? ''),
            to:        (string) ($download->date_to ?? '')
        );

        if (!$ok) return null;

        // actualizar zip_path/zip_bytes solo si existen columnas
        try {
            $conn  = $download->getConnectionName() ?: config('database.default');
            $table = $download->getTable();

            if ($this->schemaHas($conn, $table, 'zip_path')) $download->zip_path = $zipRel;
            if ($this->schemaHas($conn, $table, 'zip_disk')) $download->zip_disk = $diskName;
            if ($this->schemaHas($conn, $table, 'zip_bytes')) $download->zip_bytes = (int) $disk->size($zipRel);

            if ($download->isDirty()) $download->save();
        } catch (\Throwable $e) {
            Log::warning('[SatDownloadService] ensureLocalDemoZipWithCfdis: no se pudo actualizar zip_*', [
                'download_id' => (string) $download->id,
                'ex'          => $e->getMessage(),
            ]);
        }

        return $zipRel;
    }

    private function buildReadmeOnly(string $zipPath, string $rfc, string $requestId): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) return false;
        $zip->addFromString('README.txt', "Paquete para {$rfc}\nRequest: {$requestId}\n");
        $zip->close();
        return true;
    }

    private function minimalCfdi40(string $uuid, string $fecha, string $total, string $rfcEmisor, string $rfcReceptor): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante Version="4.0" Fecha="{$fecha}T12:00:00"
    SubTotal="{$total}" Moneda="MXN" Total="{$total}" TipoDeComprobante="I"
    Exportacion="01" LugarExpedicion="01000" xmlns:cfdi="http://www.sat.gob.mx/cfd/4"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd">
  <cfdi:Emisor Rfc="{$rfcEmisor}" Nombre="EMISOR DEMO" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="{$rfcReceptor}" Nombre="RECEPTOR DEMO" DomicilioFiscalReceptor="01000" RegimenFiscalReceptor="605" UsoCFDI="G03"/>
  <cfdi:Conceptos>
    <cfdi:Concepto ClaveProdServ="01010101" Cantidad="1" ClaveUnidad="H87" Descripcion="SERVICIO DEMO" ValorUnitario="{$total}" Importe="{$total}"/>
  </cfdi:Conceptos>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"
      Version="1.1" UUID="{$uuid}" FechaTimbrado="{$fecha}T12:00:00" RfcProvCertif="AAA010101AAA"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;
    }

    private function tinyPdf(string $text): string
    {
        $text = str_replace(["\r", "\n"], ["", "\\n"], $text);

        return "%PDF-1.4
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj
3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>>>>>endobj
4 0 obj<</Length 5 0 R>>stream
BT /F1 12 Tf 50 780 Td ({$text}) Tj ET
endstream
endobj
5 0 obj 74 endobj
xref
0 6
0000000000 65535 f 
0000000010 00000 n 
0000000060 00000 n 
0000000119 00000 n 
0000000285 00000 n 
0000000420 00000 n 
trailer<</Size 6/Root 1 0 R>>
startxref
490
%%EOF";
    }

    private function csvUtf8(array $rows): string
    {
        $out = chr(0xEF) . chr(0xBB) . chr(0xBF);
        foreach ($rows as $r) {
            $out .= implode(',', array_map(function ($v) {
                $v = (string) $v;
                $v = str_replace('"', '""', $v);
                return '"' . $v . '"';
            }, $r)) . "\n";
        }
        return $out;
    }

    private function uuidV4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function normalizeXmlName(string $original, string $rfc): string
    {
        $name = trim($original, "/\\ \t\r\n");
        if (!str_ends_with(strtolower($name), '.xml')) $name .= '.xml';
        if (!str_starts_with($name, $rfc . '_')) $name = $rfc . '_' . $name;
        return preg_replace('~[^A-Za-z0-9_\-\.]~', '_', $name) ?? $name;
    }

    private function quickParseCfdi(string $xml): array
    {
        $uuid  = '';
        $emi   = '';
        $rec   = '';
        $fecha = '';
        $total = '';

        try {
            $sxe = new \SimpleXMLElement($xml);
            $sxe->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
            $sxe->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

            $comp = $sxe->xpath('/cfdi:Comprobante');
            if ($comp && isset($comp[0])) {
                $c     = $comp[0];
                $fecha = (string) ($c['Fecha'] ?? $c['fecha'] ?? '');
                $total = (string) ($c['Total'] ?? $c['total'] ?? '');
            }

            $em = $sxe->xpath('/cfdi:Comprobante/cfdi:Emisor');
            if ($em && isset($em[0])) $emi = strtoupper((string) ($em[0]['Rfc'] ?? $em[0]['rfc'] ?? ''));

            $re = $sxe->xpath('/cfdi:Comprobante/cfdi:Receptor');
            if ($re && isset($re[0])) $rec = strtoupper((string) ($re[0]['Rfc'] ?? $re[0]['rfc'] ?? ''));

            $tf = $sxe->xpath('//tfd:TimbreFiscalDigital');
            if ($tf && isset($tf[0])) $uuid = strtoupper((string) ($tf[0]['UUID'] ?? $tf[0]['Uuid'] ?? ''));
        } catch (\Throwable) {
            // no-op
        }

        return [$uuid, $emi, $rec, $fecha, $total];
    }

    private function persistCfdiRow(SatDownload $download, string $uuid, string $emi, string $rec, string $fecha, string $total): void
    {
        try {
            $conn = $download->getConnectionName() ?: 'mysql_clientes';

            if (!Schema::connection($conn)->hasTable('cfdis')) return;

            $schema = Schema::connection($conn);
            if (!$schema->hasColumn('cfdis', 'uuid') || !$schema->hasColumn('cfdis', 'fecha')) return;

            $uuidClean = $uuid !== '' ? $uuid : (string) Str::uuid();
            $fechaDate = $fecha !== '' ? substr($fecha, 0, 10) : now()->toDateString();
            $totalNum  = is_numeric($total) ? (float) $total : 0.0;

            $data = ['uuid' => $uuidClean, 'fecha' => $fechaDate];

            if ($schema->hasColumn('cfdis', 'total')) $data['total'] = $totalNum;
            if ($schema->hasColumn('cfdis', 'subtotal')) $data['subtotal'] = $totalNum;
            if ($schema->hasColumn('cfdis', 'iva')) $data['iva'] = 0.0;

            if ($schema->hasColumn('cfdis', 'cuenta_id') && !empty($download->cuenta_id)) $data['cuenta_id'] = (string) $download->cuenta_id;

            if ($schema->hasColumn('cfdis', 'cliente_id')) {
                $clienteId = 0;
                if (isset($download->cliente_id) && is_numeric($download->cliente_id)) $clienteId = (int) $download->cliente_id;
                $data['cliente_id'] = $clienteId;
            }

            if ($schema->hasColumn('cfdis', 'tipo')) $data['tipo'] = $download->tipo;
            if ($schema->hasColumn('cfdis', 'rfc_emisor')) $data['rfc_emisor'] = $emi;
            if ($schema->hasColumn('cfdis', 'rfc_receptor')) $data['rfc_receptor'] = $rec;
            if ($schema->hasColumn('cfdis', 'status')) $data['status'] = 'sat_imported';

            $timestamps = [];
            if ($schema->hasColumn('cfdis', 'updated_at')) $timestamps['updated_at'] = now();
            if ($schema->hasColumn('cfdis', 'created_at')) $timestamps['created_at'] = now();

            DB::connection($conn)->table('cfdis')->updateOrInsert(['uuid' => $uuidClean], array_merge($data, $timestamps));
        } catch (\Throwable $e) {
            Log::warning('[SatDownloadService] persistCfdiRow error', [
                'download_id' => $download->id ?? null,
                'cfdi_uuid'   => $uuid ?? null,
                'ex'          => $e->getMessage(),
            ]);
        }
    }

    /* ==========================================================
     | ZIP -> VAULT DISK (opcional)
     * ==========================================================*/

    public function moveZipToVault(SatDownload $download, bool $deleteOriginal = false): ?string
    {
        $srcDiskName   = $this->resolveSatZipDisk();
        $vaultDiskName = $this->resolveVaultDisk();

        $downloadId = (string) $download->id;
        $cuentaId   = (string) $download->cuenta_id;

        [$srcDiskResolved, $srcPathResolved] = $this->getZipStoragePath($download);
        $srcDiskName = $srcDiskResolved ?: $srcDiskName;
        $srcPath     = ltrim((string)$srcPathResolved, '/');

        if ($srcPath === '' || !Storage::disk($srcDiskName)->exists($srcPath)) {
            Log::warning('[SAT:Vault] moveZipToVault: ZIP origen no encontrado', [
                'download_id' => $downloadId,
                'cuenta_id'   => $cuentaId,
                'disk'        => $srcDiskName,
                'path'        => $srcPath,
            ]);
            return null;
        }

        $bytes = 0;
        try { $bytes = (int) Storage::disk($srcDiskName)->size($srcPath); } catch (\Throwable) {}
        if ($bytes <= 0) {
            Log::warning('[SAT:Vault] moveZipToVault: bytes=0', [
                'download_id' => $downloadId,
                'path'        => $srcPath,
            ]);
            return null;
        }

        $destPath = "vault/{$cuentaId}/{$downloadId}.zip";

        // asegurar carpeta
        try {
            $dir = dirname($destPath);
            if ($dir !== '.' && !Storage::disk($vaultDiskName)->exists($dir)) {
                Storage::disk($vaultDiskName)->makeDirectory($dir);
            }
        } catch (\Throwable) {}

        $stream = Storage::disk($srcDiskName)->readStream($srcPath);
        if (!$stream) return null;

        $ok = Storage::disk($vaultDiskName)->writeStream($destPath, $stream);
        try { if (is_resource($stream)) fclose($stream); } catch (\Throwable) {}

        if (!$ok) return null;

        if ($deleteOriginal && !app()->environment(['local', 'development', 'testing'])) {
            try { Storage::disk($srcDiskName)->delete($srcPath); } catch (\Throwable) {}
        }

        // persistir vault_path/zip_disk/zip_path/size_*
        $conn  = $download->getConnectionName() ?: 'mysql_clientes';
        $table = $download->getTable();

        if ($this->schemaHas($conn, $table, 'vault_path')) $download->vault_path = $destPath;
        if ($this->schemaHas($conn, $table, 'zip_path')) $download->zip_path = $destPath; // opcional: apuntar a vault
        if ($this->schemaHas($conn, $table, 'zip_disk')) $download->zip_disk = $vaultDiskName;
        if ($this->schemaHas($conn, $table, 'zip_bytes')) $download->zip_bytes = $bytes;
        if ($this->schemaHas($conn, $table, 'size_bytes')) $download->size_bytes = $bytes;
        if ($this->schemaHas($conn, $table, 'size_mb')) $download->size_mb = round($bytes / 1024 / 1024, 4);

        $download->save();

        // crear/actualizar VaultFile
        $this->registerZipIntoVaultFilesIfMissing([
            'cuenta_id'   => $cuentaId,
            'rfc'         => (string)$download->rfc,
            'download_id' => $downloadId,
            'disk'        => $vaultDiskName,
            'zip_rel'     => $destPath,
            'size_bytes'  => $bytes,
        ]);

        return $destPath;
    }

    private function importCfdisFromZip(SatDownload $download, Filesystem $disk, string $path): void
    {
        $cuentaId = (string) ($download->cuenta_id ?? '');
        if ($cuentaId === '') return;

        $fullZipPath = $disk->path($path);
        $zip = new \ZipArchive();

        if ($zip->open($fullZipPath) !== true) return;

        // 1) MANIFEST.csv
        $manifestIndex = $zip->locateName('MANIFEST.csv', \ZipArchive::FL_NOCASE | \ZipArchive::FL_NODIR);
        if ($manifestIndex !== false) {
            $manifest = $zip->getFromIndex($manifestIndex);
            if ($manifest !== false && $manifest !== '') {
                $lines = preg_split("/\r\n|\n|\r/", $manifest);
                $first = true;

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    if ($first) { $first = false; continue; }

                    $cols = str_getcsv($line);
                    if (count($cols) < 7) continue;

                    [, , $uuid, $emi, $rec, $fecha, $total] = $cols;
                    $this->persistCfdiRow($download, (string)$uuid, (string)$emi, (string)$rec, (string)$fecha, (string)$total);
                }
            }

            $zip->close();
            return;
        }

        // 2) sin MANIFEST: leer XML
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!$name || !preg_match('~\.xml$~i', $name)) continue;

            $contents = $zip->getFromIndex($i);
            if ($contents === false || trim($contents) === '') continue;

            [$uuid, $emi, $rec, $fecha, $total] = $this->quickParseCfdi($contents);
            if ($uuid === '' && $emi === '' && $rec === '') continue;

            $this->persistCfdiRow($download, $uuid, $emi, $rec, $fecha, $total);
        }

        $zip->close();
    }
}
