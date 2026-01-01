<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class SatBackfillCfdisMeta extends Command
{
    protected $signature = 'sat:backfill-cfdis-meta
        {--cuenta_id= : Cuenta ID (uuid)}
        {--limit=50 : Max ZIPs a procesar}
        {--disk=vault : Disk donde están los ZIP (vault/private/local)}
    ';

    protected $description = 'Extrae RFC/Razón/Subtotal/IVA/Tipo desde XML (en ZIPs) y actualiza tabla cfdis.';

    public function handle(): int
    {
        $cuentaId = (string)($this->option('cuenta_id') ?? '');
        if ($cuentaId === '') {
            $this->error('Falta --cuenta_id');
            return 1;
        }

        $disk = (string)($this->option('disk') ?? 'vault');
        if (!config("filesystems.disks.$disk")) {
            $this->error("Disk [$disk] no configurado en filesystems.php");
            return 1;
        }

        if (!Schema::connection('mysql_clientes')->hasTable('sat_vault_files')) {
            $this->error('No existe sat_vault_files');
            return 1;
        }

        $rows = DB::connection('mysql_clientes')
            ->table('sat_vault_files')
            ->where('cuenta_id', $cuentaId)
            ->orderByDesc('id')
            ->limit((int)$this->option('limit'))
            ->get(['id','path','disk','filename']);

        if ($rows->isEmpty()) {
            $this->warn('No hay archivos en sat_vault_files para esa cuenta.');
            return 0;
        }

        $processed = 0;
        foreach ($rows as $r) {
            $zipDisk = $r->disk ?: $disk;
            $zipPath = (string)($r->path ?? '');
            if ($zipPath === '') continue;

            if (!config("filesystems.disks.$zipDisk")) $zipDisk = $disk;
            if (!Storage::disk($zipDisk)->exists($zipPath)) continue;

            // Descargar ZIP a temporal local (ZipArchive requiere path local)
            $tmp = storage_path('app/_tmp_zip_' . uniqid() . '.zip');
            $stream = Storage::disk($zipDisk)->readStream($zipPath);
            if (!$stream) continue;
            file_put_contents($tmp, stream_get_contents($stream));
            if (is_resource($stream)) fclose($stream);

            $zip = new ZipArchive();
            if ($zip->open($tmp) !== true) {
                @unlink($tmp);
                continue;
            }

            $updates = 0;

            for ($i=0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!$name) continue;
                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xml') continue;

                $xmlRaw = $zip->getFromIndex($i);
                if (!$xmlRaw) continue;

                $meta = $this->parseCfdiXml($xmlRaw);
                if (!$meta || empty($meta['uuid'])) continue;

                // actualizar por cuenta_id + uuid
                $aff = DB::connection('mysql_clientes')->table('cfdis')
                    ->where('cuenta_id', $cuentaId)
                    ->where('uuid', $meta['uuid'])
                    ->update([
                        'rfc_emisor'     => $meta['rfc_emisor'] ?? null,
                        'rfc_receptor'   => $meta['rfc_receptor'] ?? null,
                        'razon_emisor'   => $meta['razon_emisor'] ?? null,
                        'razon_receptor' => $meta['razon_receptor'] ?? null,
                        'subtotal'       => $meta['subtotal'] ?? 0,
                        'iva'            => $meta['iva'] ?? 0,
                        'total'          => $meta['total'] ?? 0,
                        'tipo'           => $meta['tipo'] ?? null,
                        'updated_at'     => now(),
                    ]);

                if ($aff > 0) $updates++;
            }

            $zip->close();
            @unlink($tmp);

            $processed++;
            $this->info("ZIP #{$r->id}: updated {$updates} cfdis");
        }

        $this->info("Listo. ZIPs procesados: {$processed}");
        return 0;
    }

    private function parseCfdiXml(string $xmlRaw): ?array
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlRaw);
        if (!$xml) return null;

        // CFDI suele traer namespaces
        $ns = $xml->getNamespaces(true);
        $cfdiNs = $ns['cfdi'] ?? null;

        $root = $cfdiNs ? $xml->children($cfdiNs) : $xml;

        // Datos del comprobante
        $attrs = $xml->attributes();
        $subTotal = (float)($attrs['SubTotal'] ?? 0);
        $total    = (float)($attrs['Total'] ?? 0);

        // Emisor/Receptor
        $emisor   = $xml->xpath('//@Rfc') ? null : null; // fallback si no hay xpath
        $emisorN  = '';
        $receptorN= '';
        $rfcE = '';
        $rfcR = '';

        // Usar xpath con namespaces
        $xml->registerXPathNamespace('cfdi', $cfdiNs ?: 'http://www.sat.gob.mx/cfd/4');

        $e = $xml->xpath('//cfdi:Emisor');
        if ($e && isset($e[0])) {
            $a = $e[0]->attributes();
            $rfcE = strtoupper((string)($a['Rfc'] ?? ''));
            $emisorN = (string)($a['Nombre'] ?? '');
        }

        $r = $xml->xpath('//cfdi:Receptor');
        if ($r && isset($r[0])) {
            $a = $r[0]->attributes();
            $rfcR = strtoupper((string)($a['Rfc'] ?? ''));
            $receptorN = (string)($a['Nombre'] ?? '');
        }

        $tfdNs = $ns['tfd'] ?? null;
        if ($tfdNs) $xml->registerXPathNamespace('tfd', $tfdNs);

        $uuid = '';
        $t = $xml->xpath('//tfd:TimbreFiscalDigital');
        if ($t && isset($t[0])) {
            $a = $t[0]->attributes();
            $uuid = strtoupper((string)($a['UUID'] ?? ''));
        }

        // IVA: sumar traslados de impuesto 002 (IVA)
        $iva = 0.0;
        $tras = $xml->xpath('//cfdi:Impuestos//cfdi:Traslado');
        if ($tras) {
            foreach ($tras as $tr) {
                $a = $tr->attributes();
                $imp = (string)($a['Impuesto'] ?? '');
                $importe = (float)($a['Importe'] ?? 0);
                if ($imp === '002') $iva += $importe;
            }
        }

        // tipo (emitidos/recibidos) por RFCs de la cuenta lo defines después.
        // Aquí lo dejamos null: el controller luego clasifica si tiene RFCs de cuenta.
        return [
            'uuid'           => $uuid,
            'rfc_emisor'     => $rfcE ?: null,
            'rfc_receptor'   => $rfcR ?: null,
            'razon_emisor'   => $emisorN ?: null,
            'razon_receptor' => $receptorN ?: null,
            'subtotal'       => $subTotal,
            'iva'            => round($iva, 2),
            'total'          => $total,
            'tipo'           => null,
        ];
    }
}
