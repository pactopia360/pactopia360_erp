<?php

declare(strict_types=1);

namespace App\Services\Sat;

use App\Models\Cliente\VaultCfdi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class VaultZipImportService
{
    /**
     * Importa todos los XML dentro de un ZIP guardado en sat_vault_files.
     *
     * @param array{
     *   id:int,
     *   cuenta_id:string,
     *   disk:string,
     *   path:string,
     *   filename?:string
     * } $vaultFile
     */
    public function importFromVaultZip(array $vaultFile): array
    {
        $vaultFileId = (int)($vaultFile['id'] ?? 0);
        $cuentaId    = (string)($vaultFile['cuenta_id'] ?? '');
        $disk        = (string)($vaultFile['disk'] ?? 'private');
        $path        = (string)($vaultFile['path'] ?? '');

        if (!$vaultFileId || !$cuentaId || !$path) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'error' => 'vaultFile inválido'];
        }

        if (!Storage::disk($disk)->exists($path)) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'error' => "ZIP no existe en disk={$disk} path={$path}"];
        }

        // Copiar ZIP a temporal local (ZipArchive requiere ruta real)
        $tmpDir  = storage_path('app/tmp/vault_zip_import');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        $tmpZip = $tmpDir . DIRECTORY_SEPARATOR . ('vault_zip_' . $vaultFileId . '_' . uniqid() . '.zip');
        file_put_contents($tmpZip, Storage::disk($disk)->get($path));

        $zip = new ZipArchive();
        $res = $zip->open($tmpZip);

        if ($res !== true) {
            @unlink($tmpZip);
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'error' => 'No se pudo abrir ZIP (ZipArchive)'];
        }

        $imported = 0;
        $skipped  = 0;

        DB::beginTransaction();

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                if (!str_ends_with(strtolower($name), '.xml')) {
                    continue;
                }

                $xmlContent = $zip->getFromIndex($i);
                if (!is_string($xmlContent) || trim($xmlContent) === '') {
                    $skipped++;
                    continue;
                }

                $parsed = $this->parseCfdiXml($xmlContent);
                if (!$parsed['uuid']) {
                    $skipped++;
                    continue;
                }

                $data = [
                    'cuenta_id'              => $cuentaId,
                    'uuid'                   => $parsed['uuid'],
                    'fecha'                  => $parsed['fecha'],
                    'tipo'                   => $parsed['tipo'],
                    'rfc_emisor'             => $parsed['rfc_emisor'],
                    'rfc_receptor'           => $parsed['rfc_receptor'],
                    'razon_social_emisor'    => $parsed['razon_social_emisor'],
                    'razon_social_receptor'  => $parsed['razon_social_receptor'],
                    'subtotal'               => $parsed['subtotal'],
                    'iva'                    => $parsed['iva'],
                    'total'                  => $parsed['total'],
                    'tasa_iva'               => $parsed['tasa_iva'],
                    'vault_file_id'          => $vaultFileId,
                    'source'                 => 'zip',
                    'meta'                   => [
                        'zip_entry' => $name,
                        'version'   => $parsed['version'],
                    ],
                ];

                // upsert por (cuenta_id, uuid)
                $exists = VaultCfdi::query()
                    ->where('cuenta_id', $cuentaId)
                    ->where('uuid', $parsed['uuid'])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                VaultCfdi::create($data);
                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[VAULT:import] Error importando ZIP', [
                'vault_file_id' => $vaultFileId,
                'cuenta_id'     => $cuentaId,
                'error'         => $e->getMessage(),
            ]);
            $zip->close();
            @unlink($tmpZip);
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'error' => $e->getMessage()];
        }

        $zip->close();
        @unlink($tmpZip);

        Log::info('[VAULT:import] Import ZIP ok', [
            'vault_file_id' => $vaultFileId,
            'cuenta_id'     => $cuentaId,
            'imported'      => $imported,
            'skipped'       => $skipped,
        ]);

        return ['ok' => true, 'imported' => $imported, 'skipped' => $skipped, 'error' => null];
    }

    /**
     * Parser simple CFDI 3.3/4.0.
     * Devuelve campos mínimos para indexación.
     */
    private function parseCfdiXml(string $xml): array
    {
        $out = [
            'uuid'                  => null,
            'fecha'                 => null,
            'tipo'                  => null,
            'rfc_emisor'            => null,
            'rfc_receptor'          => null,
            'razon_social_emisor'   => null,
            'razon_social_receptor' => null,
            'subtotal'              => 0.0,
            'iva'                   => 0.0,
            'total'                 => 0.0,
            'tasa_iva'              => null,
            'version'               => null,
        ];

        try {
            libxml_use_internal_errors(true);
            $sx = simplexml_load_string($xml);
            if (!$sx) return $out;

            // CFDI usa namespaces; intentamos detectarlos y registrar
            $namespaces = $sx->getNamespaces(true);

            // Nodo Comprobante (raíz)
            $attrs = $sx->attributes();
            $out['version']  = isset($attrs['Version']) ? (string)$attrs['Version'] : (isset($attrs['version']) ? (string)$attrs['version'] : null);
            $out['fecha']    = isset($attrs['Fecha']) ? (string)$attrs['Fecha'] : (isset($attrs['fecha']) ? (string)$attrs['fecha'] : null);
            $out['tipo']     = isset($attrs['TipoDeComprobante']) ? (string)$attrs['TipoDeComprobante'] : null;
            $out['subtotal'] = isset($attrs['SubTotal']) ? (float)$attrs['SubTotal'] : 0.0;
            $out['total']    = isset($attrs['Total']) ? (float)$attrs['Total'] : 0.0;

            // Emisor/Receptor (en CFDI 4.0: cfdi:Emisor / cfdi:Receptor)
            $emisor = $sx->children($namespaces['cfdi'] ?? null)->Emisor ?? $sx->Emisor ?? null;
            if ($emisor) {
                $ea = $emisor->attributes();
                $out['rfc_emisor']          = isset($ea['Rfc']) ? (string)$ea['Rfc'] : null;
                $out['razon_social_emisor'] = isset($ea['Nombre']) ? (string)$ea['Nombre'] : null;
            }

            $receptor = $sx->children($namespaces['cfdi'] ?? null)->Receptor ?? $sx->Receptor ?? null;
            if ($receptor) {
                $ra = $receptor->attributes();
                $out['rfc_receptor']          = isset($ra['Rfc']) ? (string)$ra['Rfc'] : null;
                $out['razon_social_receptor'] = isset($ra['Nombre']) ? (string)$ra['Nombre'] : null;
            }

            // UUID: TimbreFiscalDigital (tfd)
            $complemento = $sx->children($namespaces['cfdi'] ?? null)->Complemento ?? $sx->Complemento ?? null;
            if ($complemento) {
                // buscar TimbreFiscalDigital en cualquiera de sus namespaces
                foreach ($complemento->children() as $child) {
                    $n = $child->getName();
                    if (strcasecmp($n, 'TimbreFiscalDigital') === 0) {
                        $ta = $child->attributes();
                        $out['uuid'] = isset($ta['UUID']) ? (string)$ta['UUID'] : null;
                        break;
                    }
                }

                // fallback por namespace tfd si existe
                if (!$out['uuid'] && isset($namespaces['tfd'])) {
                    $tfd = $complemento->children($namespaces['tfd'])->TimbreFiscalDigital ?? null;
                    if ($tfd) {
                        $ta = $tfd->attributes();
                        $out['uuid'] = isset($ta['UUID']) ? (string)$ta['UUID'] : null;
                    }
                }
            }

            // IVA: buscar traslados en Impuestos
            $impuestos = $sx->children($namespaces['cfdi'] ?? null)->Impuestos ?? $sx->Impuestos ?? null;
            if ($impuestos) {
                $traslados = $impuestos->children($namespaces['cfdi'] ?? null)->Traslados ?? $impuestos->Traslados ?? null;
                if ($traslados) {
                    $iva = 0.0;
                    $tasa = null;

                    foreach ($traslados->children($namespaces['cfdi'] ?? null) as $t) {
                        if ($t->getName() !== 'Traslado') continue;
                        $ta = $t->attributes();

                        $imp = isset($ta['Impuesto']) ? (string)$ta['Impuesto'] : '';
                        $imp = strtoupper(trim($imp));

                        // IVA suele venir como "002"
                        if ($imp !== '002') continue;

                        $importe = isset($ta['Importe']) ? (float)$ta['Importe'] : 0.0;
                        $iva += $importe;

                        if ($tasa === null) {
                            if (isset($ta['TasaOCuota'])) $tasa = (float)$ta['TasaOCuota'];
                        }
                    }

                    $out['iva'] = $iva;
                    $out['tasa_iva'] = $tasa;
                }
            }
        } catch (\Throwable) {
            // silencioso
        }

        // normalizar fecha a formato SQL (si viene ISO)
        if (is_string($out['fecha']) && $out['fecha'] !== '') {
            // la guardamos tal cual; Eloquent castea a datetime si es compatible
        } else {
            $out['fecha'] = null;
        }

        if (is_string($out['uuid'])) {
            $out['uuid'] = strtoupper(trim($out['uuid']));
        }

        return $out;
    }
}
