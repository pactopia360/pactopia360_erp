<?php

namespace App\Console\Commands;

use App\Models\Cliente\SatDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SatVaultImportZips extends Command
{
    protected $signature = 'sat:vault-import-zips
        {--cuenta_id= : UUID cuenta_id}
        {--limit=200 : Máximo de descargas}
        {--force : Reimporta aunque ya exista}
        {--all : No filtra por status (toma todas las filas de la cuenta)}
    ';


    protected $description = 'Copia ZIP a sat_vault_files (si falta) e indexa XML dentro del ZIP en sat_vault_cfdis.';

    public function handle(): int
    {
        $conn = 'mysql_clientes';
        $schema = Schema::connection($conn);

        if (!$schema->hasTable('sat_downloads')) {
            $this->error('Tabla sat_downloads no existe.');
            return self::FAILURE;
        }
        if (!$schema->hasTable('sat_vault_files')) {
            $this->error('Tabla sat_vault_files no existe.');
            return self::FAILURE;
        }
        if (!$schema->hasTable('sat_vault_cfdis')) {
            $this->error('Tabla sat_vault_cfdis no existe.');
            return self::FAILURE;
        }

        $hasZipDisk = $schema->hasColumn('sat_downloads', 'zip_disk');
        $hasZipPath = $schema->hasColumn('sat_downloads', 'zip_path');
        $hasRequestId = $schema->hasColumn('sat_downloads', 'request_id');
        $hasPackageId = $schema->hasColumn('sat_downloads', 'package_id');

        $cuentaId = trim((string)($this->option('cuenta_id') ?? ''));
        $limit    = (int)($this->option('limit') ?? 200);
        $limit    = max(1, min($limit, 5000));
        $force = (bool)$this->option('force');
        $all   = (bool)$this->option('all');

        $qb = SatDownload::on($conn)->newQuery();

        if ($cuentaId !== '') {
            $qb->where('cuenta_id', $cuentaId);
        }

        if (!$all) {
            $all = (bool)$this->option('all');

            if (!$all) {
                $qb->whereIn('status', ['ready','done','listo','PAID','paid','PAGADO','pagado','downloaded']);
            }

        }

        $downloads = $qb->orderByDesc('created_at')->limit($limit)->get();

        $this->info('Descargas a procesar: ' . $downloads->count());

        $importedCfdi = 0;
        $vaultFilesUpserted = 0;
        $skipped = 0;

        foreach ($downloads as $dl) {

            [$srcDisk, $srcPath] = $this->resolveZipDiskPath($dl, $hasZipDisk, $hasZipPath, $hasRequestId, $hasPackageId);

            if ($srcDisk === '' || $srcPath === '') {
                $skipped++;
                $this->line("SKIP {$dl->id}: sin zip resoluble");
                continue;
            }

            $srcPath = ltrim($srcPath, '/');

            if (!$this->diskConfigured($srcDisk) || !Storage::disk($srcDisk)->exists($srcPath)) {
                $skipped++;
                $this->line("SKIP {$dl->id}: no existe {$srcDisk}:{$srcPath}");
                continue;
            }

            $rfc = strtoupper((string)($dl->rfc ?? ''));
            if ($rfc === '') $rfc = 'XAXX010101000';

            $vaultDisk = config('filesystems.disks.sat_vault')
                ? 'sat_vault'
                : (config('filesystems.disks.vault') ? 'vault' : (config('filesystems.disks.private') ? 'private' : $srcDisk));

            $baseDir = 'vault/' . (string)$dl->cuenta_id . '/' . $rfc;

            $destName = 'SAT_' . $rfc . '_' . Carbon::now()->format('Ymd_His') . '_' . Str::random(6) . '.zip';
            $destPath = $baseDir . '/' . $destName;

            try { Storage::disk($vaultDisk)->makeDirectory($baseDir); } catch (\Throwable) {}

            // upsert sat_vault_files por (cuenta_id,disk,path) UNIQUE
            $bytes = 0;
            try { $bytes = (int)Storage::disk($srcDisk)->size($srcPath); } catch (\Throwable) {}

            if ($bytes <= 0) {
                $skipped++;
                $this->line("SKIP {$dl->id}: bytes<=0 origen {$srcDisk}:{$srcPath}");
                continue;
            }

            // Copiar a bóveda si aún no existe registro
            $vf = DB::connection($conn)->table('sat_vault_files')
                ->where('cuenta_id', (string)$dl->cuenta_id)
                ->where('source', 'sat_download')
                ->where('source_id', (string)$dl->id)
                ->orderByDesc('id')
                ->first();

            $vaultFileId = (int)($vf->id ?? 0);
            $alreadyHasZipInVault = $vf && isset($vf->disk, $vf->path) && $vf->path && $vf->disk && $this->diskConfigured((string)$vf->disk)
                && Storage::disk((string)$vf->disk)->exists((string)$vf->path);

            if (!$alreadyHasZipInVault) {
                // copiar stream
                $read = null;
                try {
                    $read = Storage::disk($srcDisk)->readStream($srcPath);
                    if (!$read) throw new \RuntimeException('No se pudo abrir stream origen');

                    $ok = Storage::disk($vaultDisk)->writeStream($destPath, $read);

                    if (is_resource($read)) fclose($read);
                    if (!$ok) throw new \RuntimeException('writeStream falló a bóveda');

                } catch (\Throwable $e) {
                    try { if (is_resource($read)) fclose($read); } catch (\Throwable) {}
                    $skipped++;
                    $this->line("SKIP {$dl->id}: error copiando a bóveda => {$e->getMessage()}");
                    continue;
                }

                $vbytes = 0;
                try { $vbytes = (int)Storage::disk($vaultDisk)->size($destPath); } catch (\Throwable) {}

                if ($vbytes <= 0) {
                    try { Storage::disk($vaultDisk)->delete($destPath); } catch (\Throwable) {}
                    $skipped++;
                    $this->line("SKIP {$dl->id}: copiado pero bytes=0, eliminado");
                    continue;
                }

                $data = [
                    'cuenta_id'  => (string)$dl->cuenta_id,
                    'rfc'        => $rfc,
                    'source'     => 'sat_download',
                    'source_id'  => (string)$dl->id,
                    'filename'   => basename($destPath),
                    'path'       => $destPath,
                    'disk'       => $vaultDisk,
                    'mime'       => 'application/zip',
                    'bytes'      => $vbytes,
                    'updated_at' => now(),
                ];

                if ($vf) {
                    DB::connection($conn)->table('sat_vault_files')->where('id', $vf->id)->update($data);
                    $vaultFileId = (int)$vf->id;
                } else {
                    $data['created_at'] = now();
                    DB::connection($conn)->table('sat_vault_files')->insert($data);
                    $vaultFileId = (int)DB::connection($conn)->getPdo()->lastInsertId();
                }

                $vaultFilesUpserted++;
                $this->line("OK {$dl->id}: vault_file_id={$vaultFileId} ZIP {$vaultDisk}:{$destPath}");
            } else {
                $vaultFileId = (int)$vf->id;
                $destPath = (string)$vf->path;
                $vaultDisk = (string)$vf->disk;
            }

            // Indexar a sat_vault_cfdis (idempotente por cuenta+uuid)
            $already = DB::connection($conn)->table('sat_vault_cfdis')
                ->where('cuenta_id', (string)$dl->cuenta_id)
                ->where('vault_file_id', $vaultFileId)
                ->limit(1)
                ->exists();

            if ($already && !$force) {
                $this->line("SKIP {$dl->id}: ya indexado vault_file_id={$vaultFileId}");
                continue;
            }

            $imported = $this->importZipIntoCfdis($vaultDisk, $destPath, (string)$dl->cuenta_id, $rfc, $vaultFileId);
            $importedCfdi += $imported;

            $this->line("OK {$dl->id}: CFDI importados={$imported} vault_file_id={$vaultFileId}");
        }

        $this->info("VaultFiles upsert: {$vaultFilesUpserted} | CFDI importados: {$importedCfdi} | Skips: {$skipped}");
        return self::SUCCESS;
    }

    private function importZipIntoCfdis(string $disk, string $path, string $cuentaId, string $rfc = '', int $vaultFileId = 0): int
{
    $disk = $disk !== '' ? $disk : 'private';
    $path = ltrim((string)$path, '/');

    if ($path === '' || !$this->diskConfigured($disk) || !Storage::disk($disk)->exists($path)) {
        $this->line("WARN vault_file_id={$vaultFileId}: no existe {$disk}:{$path}");
        return 0;
    }

    // 1) Validar firma ZIP (PK..) para detectar HTML/JSON guardado como .zip
    try {
        $rs = Storage::disk($disk)->readStream($path);
        if (!$rs) {
            throw new \RuntimeException('readStream=false');
        }
        $head = fread($rs, 4);
        fclose($rs);

        $sig = bin2hex($head ?: '');
        // PK\x03\x04 => 504b0304 | PK\x05\x06 => 504b0506 | PK\x07\x08 => 504b0708
        if (!in_array($sig, ['504b0304', '504b0506', '504b0708'], true)) {
            $this->line("WARN vault_file_id={$vaultFileId}: NO es ZIP (sig={$sig}) {$disk}:{$path}");
            return 0;
        }
    } catch (\Throwable $e) {
        $this->line("WARN vault_file_id={$vaultFileId}: no se pudo leer header ZIP: {$e->getMessage()}");
        return 0;
    }

    $abs = Storage::disk($disk)->path($path);

    $zip = new \ZipArchive();
    $ok  = $zip->open($abs);
    if ($ok !== true) {
        $this->line("WARN vault_file_id={$vaultFileId}: ZipArchive->open falló (code={$ok}) {$abs}");
        return 0;
    }

    // 2) Debug mínimo
    $this->line("INFO vault_file_id={$vaultFileId}: zipEntries={$zip->numFiles} {$disk}:{$path}");

    $xmlEntries = 0;
    $firstNames = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!$name) continue;

        if (count($firstNames) < 10) $firstNames[] = $name;

        if (str_ends_with(strtolower($name), '.xml')) {
            $xmlEntries++;
        }
    }

    if ($xmlEntries === 0) {
        $this->line("WARN vault_file_id={$vaultFileId}: ZIP sin XML. Primeros entries: " . implode(' | ', $firstNames));
        $zip->close();
        return 0;
    }

    $this->line("INFO vault_file_id={$vaultFileId}: xmlEntries={$xmlEntries}");

    $schema = Schema::connection('mysql_clientes');
    $has = fn(string $col) => $schema->hasTable('sat_vault_cfdis') && $schema->hasColumn('sat_vault_cfdis', $col);

    $ownerRfc = strtoupper(trim((string)$rfc));
    if ($ownerRfc === '') $ownerRfc = null;

    $inserted = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = $zip->getNameIndex($i);
        if (!$entryName) continue;

        // Soporta .xml y .XML
        if (!str_ends_with(strtolower($entryName), '.xml')) continue;

        $xml = $zip->getFromIndex($i);
        if (!$xml) continue;

        $uuid = '';
        $fecha = '';
        $subtotal = 0.0;
        $total = 0.0;

        $emisorRfc = '';
        $receptorRfc = '';
        $emisorNombre = '';
        $receptorNombre = '';
        $iva = 0.0;

        $tipoDeComprobante = ''; // I/E/T/N/P etc.

        try {
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;

            // Evita warnings de XML mal formado sin reventar
            $prev = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            if (!$loaded) continue;

            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
            $xpath->registerNamespace('cfdi33', 'http://www.sat.gob.mx/cfd/3');
            $xpath->registerNamespace('tfd',  'http://www.sat.gob.mx/TimbreFiscalDigital');

            $comprobante = $xpath->query('//cfdi:Comprobante')->item(0);
            if (!$comprobante) $comprobante = $xpath->query('//cfdi33:Comprobante')->item(0);

            if ($comprobante) {
                $fecha             = (string)($comprobante->getAttribute('Fecha') ?: $comprobante->getAttribute('fecha'));
                $subtotal          = (float)($comprobante->getAttribute('SubTotal') ?: $comprobante->getAttribute('subTotal') ?: 0);
                $total             = (float)($comprobante->getAttribute('Total') ?: $comprobante->getAttribute('total') ?: 0);
                $tipoDeComprobante = (string)($comprobante->getAttribute('TipoDeComprobante') ?: '');
            }

            $emisor = $xpath->query('//cfdi:Emisor')->item(0);
            if (!$emisor) $emisor = $xpath->query('//cfdi33:Emisor')->item(0);
            if ($emisor) {
                $emisorRfc    = (string)($emisor->getAttribute('Rfc') ?: $emisor->getAttribute('rfc'));
                $emisorNombre = (string)($emisor->getAttribute('Nombre') ?: $emisor->getAttribute('nombre'));
            }

            $receptor = $xpath->query('//cfdi:Receptor')->item(0);
            if (!$receptor) $receptor = $xpath->query('//cfdi33:Receptor')->item(0);
            if ($receptor) {
                $receptorRfc    = (string)($receptor->getAttribute('Rfc') ?: $receptor->getAttribute('rfc'));
                $receptorNombre = (string)($receptor->getAttribute('Nombre') ?: $receptor->getAttribute('nombre'));
            }

            $tfd = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);
            if ($tfd) {
                $uuid = (string)($tfd->getAttribute('UUID') ?: $tfd->getAttribute('Uuid'));
            }

            // IVA: primero intenta traslados IVA (Impuesto=002), si no, fallback total-subtotal
            $ivaFound = 0.0;
            $tras = $xpath->query('//*[local-name()="Traslado"]');
            if ($tras && $tras->length > 0) {
                foreach ($tras as $node) {
                    if (!($node instanceof \DOMElement)) continue;
                    $imp = (string)($node->getAttribute('Impuesto') ?: $node->getAttribute('impuesto'));
                    if ($imp !== '002') continue;

                    $importe = (float)($node->getAttribute('Importe') ?: $node->getAttribute('importe') ?: 0);
                    if ($importe > 0) $ivaFound += $importe;
                }
            }

            if ($ivaFound > 0) {
                $iva = round($ivaFound, 2);
            } elseif ($subtotal > 0 && $total > $subtotal) {
                $iva = round($total - $subtotal, 2);
            }
        } catch (\Throwable) {
            continue;
        }

        $uuid = strtoupper(trim((string)$uuid));
        if ($uuid === '') continue;

        $exists = DB::connection('mysql_clientes')
            ->table('sat_vault_cfdis')
            ->where('cuenta_id', $cuentaId)
            ->where('uuid', $uuid)
            ->exists();

        if ($exists) continue;

        $emisorRfcU   = strtoupper(trim((string)$emisorRfc));
        $receptorRfcU = strtoupper(trim((string)$receptorRfc));

        // tipo Vault: por RFC dueño
        $tipoVault = null;
        if ($ownerRfc) {
            if ($receptorRfcU !== '' && $receptorRfcU === $ownerRfc) {
                $tipoVault = 'recibidos';
            } elseif ($emisorRfcU !== '' && $emisorRfcU === $ownerRfc) {
                $tipoVault = 'emitidos';
            }
        }

        // fallback: si no se pudo inferir, usa TipoDeComprobante (I/E) como pista
        if ($tipoVault === null && $tipoDeComprobante !== '') {
            $tdc = strtoupper(trim($tipoDeComprobante));
            if (in_array($tdc, ['I', 'E'], true)) {
                $tipoVault = ($tdc === 'I') ? 'emitidos' : 'recibidos';
            }
        }

        $payload = [
            'cuenta_id'  => $cuentaId,
            'uuid'       => $uuid,
            'subtotal'   => (float)$subtotal,
            'iva'        => (float)$iva,
            'total'      => (float)$total,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($has('fecha_emision')) {
            $payload['fecha_emision'] = $fecha !== '' ? substr($fecha, 0, 10) : null;
        } elseif ($has('fecha')) {
            $payload['fecha'] = $fecha !== '' ? $fecha : null;
        }

        if ($has('tipo')) $payload['tipo'] = $tipoVault ?: 'emitidos';

        if ($has('rfc_emisor'))   $payload['rfc_emisor']   = $emisorRfcU   !== '' ? $emisorRfcU   : null;
        if ($has('rfc_receptor')) $payload['rfc_receptor'] = $receptorRfcU !== '' ? $receptorRfcU : null;

        if ($has('razon_emisor'))   $payload['razon_emisor']   = $emisorNombre   !== '' ? $emisorNombre   : null;
        if ($has('razon_receptor')) $payload['razon_receptor'] = $receptorNombre !== '' ? $receptorNombre : null;

        if ($has('vault_file_id') && $vaultFileId > 0) $payload['vault_file_id'] = $vaultFileId;
        if ($has('xml_path')) $payload['xml_path'] = $path . '#' . $entryName;

        if ($has('tipo_comprobante') && $tipoDeComprobante !== '') {
            $payload['tipo_comprobante'] = $tipoDeComprobante;
        }

        DB::connection('mysql_clientes')->table('sat_vault_cfdis')->insert($payload);
        $inserted++;
    }

    $zip->close();
    return $inserted;
}


private function resolveZipDiskPath(\App\Models\Cliente\SatDownload $dl, bool $hasZipDisk, bool $hasZipPath, bool $hasRequestId, bool $hasPackageId): array
{
    $conn = 'mysql_clientes';

    $zipPath = $hasZipPath ? ltrim((string)($dl->zip_path ?? ''), '/') : '';
    $zipDisk = $hasZipDisk ? (string)($dl->zip_disk ?? '') : '';

    // 0) PRIMERA PRIORIDAD: si ya hay ZIP registrado en sat_vault_files para esta descarga, úsalo.
    // Esto arregla exactamente tus 2 filas "downloaded" sin zip_path/zip_disk.
    try {
        if (Schema::connection($conn)->hasTable('sat_vault_files')) {
            $vf = DB::connection($conn)->table('sat_vault_files')
                ->where('cuenta_id', (string)$dl->cuenta_id)
                ->where('source', 'sat_download')
                ->where('source_id', (string)$dl->id)
                ->where(function ($w) {
                    $w->where('mime', 'application/zip')
                      ->orWhere('filename', 'like', '%.zip')
                      ->orWhere('path', 'like', '%.zip');
                })
                ->orderByDesc('id')
                ->first();

            if ($vf && !empty($vf->disk) && !empty($vf->path)) {
                $d = (string)$vf->disk;
                $p = ltrim((string)$vf->path, '/');

                if ($p !== '' && $this->diskConfigured($d) && Storage::disk($d)->exists($p)) {
                    return [$d, $p];
                }
            }
        }
    } catch (\Throwable) {}

    // Meta JSON por compatibilidad
    $meta = [];
    foreach (['meta_json','meta','payload_json','payload','response_json','response'] as $col) {
        try {
            if (!isset($dl->{$col})) continue;
            $raw = $dl->{$col};

            if (is_array($raw)) {
                $meta = array_replace_recursive($meta, $raw);
                continue;
            }

            if (is_string($raw) && trim($raw) !== '') {
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

    // vault_path (si existe en sat_downloads)
    $vaultPath = '';
    try {
        if (Schema::connection($conn)->hasTable('sat_downloads') && Schema::connection($conn)->hasColumn('sat_downloads', 'vault_path')) {
            $vaultPath = ltrim((string)($dl->vault_path ?? ''), '/');
        }
    } catch (\Throwable) {}

    $linkedId = $this->extractLinkedDownloadId($dl, $meta);

    $diskCandidates = array_values(array_filter(array_unique([
        $zipDisk,
        (string)config('filesystems.sat_downloads_disk', ''),
        'sat_zip',
        'sat_downloads',
        'private',
        'local',
        'sat_vault',
        'vault',
    ])));

    // 1) zip_path directo
    if ($zipPath !== '') {
        foreach ($diskCandidates as $d) {
            if ($this->diskConfigured($d) && Storage::disk($d)->exists($zipPath)) {
                return [$d, $zipPath];
            }
        }
    }

    // 1.1) vault_path directo
    if ($vaultPath !== '') {
        foreach ($diskCandidates as $d) {
            if ($this->diskConfigured($d) && Storage::disk($d)->exists($vaultPath)) {
                return [$d, $vaultPath];
            }
        }
    }

    // 2) Heurísticas por nombre
    $did = (string)$dl->id;
    $cid = (string)$dl->cuenta_id;

    $rfc       = strtoupper(trim((string)($dl->rfc ?? '')));
    $requestId = $hasRequestId ? trim((string)($dl->request_id ?? '')) : '';
    $packageId = $hasPackageId ? trim((string)($dl->package_id ?? '')) : '';

    $ids = array_values(array_filter(array_unique([
        $did,
        $linkedId,
        $requestId !== '' ? $requestId : null,
        $packageId !== '' ? $packageId : null,
    ])));

    $pathCandidates = [];

    foreach ($ids as $id) {
        // OJO: tu storage real tiene AMBOS árboles: sat/packages y packages (por tu tinker)
        $pathCandidates[] = "sat/packages/{$cid}/pkg_{$id}.zip";
        $pathCandidates[] = "packages/{$cid}/pkg_{$id}.zip";

        $pathCandidates[] = "sat/packages/{$cid}/{$id}.zip";
        $pathCandidates[] = "packages/{$cid}/{$id}.zip";

        $pathCandidates[] = "sat/packages/{$cid}/done/pkg_{$id}.zip";
        $pathCandidates[] = "packages/{$cid}/done/pkg_{$id}.zip";

        $pathCandidates[] = "sat/packages/{$cid}/paid/pkg_{$id}.zip";
        $pathCandidates[] = "packages/{$cid}/paid/pkg_{$id}.zip";

        // Patrón SAT_{RFC}_{id}.zip (te está saliendo así)
        if ($rfc !== '') {
            $pathCandidates[] = "sat/packages/{$cid}/SAT_{$rfc}_{$id}.zip";
            $pathCandidates[] = "packages/{$cid}/SAT_{$rfc}_{$id}.zip";
        }
    }

    $pathCandidates = array_values(array_unique(array_filter($pathCandidates)));

    foreach ($diskCandidates as $d) {
        if (!$this->diskConfigured($d)) continue;

        foreach ($pathCandidates as $p) {
            $p = ltrim($p, '/');
            if ($p !== '' && Storage::disk($d)->exists($p)) {
                return [$d, $p];
            }
        }
    }

    // 3) Listado (si tu comando ya tiene este método, úsalo; si no, omítelo)
    if (method_exists($this, 'findZipByListingConsole')) {
        foreach ($diskCandidates as $d) {
            if (!$this->diskConfigured($d)) continue;

            $found = $this->findZipByListingConsole(
                $d,
                $cid,
                $did,
                $linkedId,
                (string)($dl->rfc ?? ''),
                (string)($dl->request_id ?? ''),
                (string)($dl->package_id ?? '')
            );

            if (is_string($found) && $found !== '') {
                return [$d, ltrim($found, '/')];
            }
        }
    }

    // 4) Último recurso por RFC (si tu comando ya tiene este método, úsalo; si no, omítelo)
    if ($rfc !== '' && method_exists($this, 'findMostRecentZipForAccount')) {
        foreach ($diskCandidates as $d) {
            if (!$this->diskConfigured($d)) continue;

            try {
                $fallbackZip = $this->findMostRecentZipForAccount($d, $cid, $rfc);
                if (is_string($fallbackZip) && $fallbackZip !== '' && Storage::disk($d)->exists($fallbackZip)) {
                    $this->warn("WARN {$did}: usando ZIP más reciente por RFC ({$rfc}) => {$d}:{$fallbackZip}");
                    return [$d, ltrim($fallbackZip, '/')];
                }
            } catch (\Throwable) {}
        }
    }

    return ['', ''];
}

private function extractLinkedDownloadId(\App\Models\Cliente\SatDownload $dl, array $meta): ?string
{
    $candidates = [];

    foreach (['linked_download_id','new_download_id','download_id'] as $k) {
        $v = $meta[$k] ?? null;
        if (is_string($v) && preg_match('~^[0-9a-f\-]{36}$~i', $v)) $candidates[] = $v;
    }

    foreach (['payload.download_id','payload.linked_download_id','link.new','linked.new','linked.download_id'] as $p) {
        $v = data_get($meta, $p);
        if (is_string($v) && preg_match('~^[0-9a-f\-]{36}$~i', $v)) $candidates[] = $v;
    }

    $candidates = array_values(array_unique($candidates));
    foreach ($candidates as $id) {
        if ($id !== (string)$dl->id) return $id;
    }

    return null;
}

}
