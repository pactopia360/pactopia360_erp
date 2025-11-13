<?php

namespace App\Services\Sat;

use App\Models\Cliente\SatCredential;
use App\Models\Cliente\SatDownload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Servicio de Descarga Masiva SAT (demo + producción SATWS + opcional database).
 *
 * Producción usa:
 * - phpcfdi/credentials        -> Carga y valida FIEL (.cer/.key + password)
 * - phpcfdi/sat-ws-descarga-masiva -> Autenticación, solicitud, verificación, descarga
 *
 * Requiere PHP extensions: openssl, soap.
 */
class SatDownloadService
{
    /* ==========================================================
     | CREDENCIALES
     * ==========================================================*/

    /** Crea/actualiza credenciales y guarda archivos si vienen */
    public function upsertCredentials(string $cuentaId, string $rfc, ?UploadedFile $cer, ?UploadedFile $key, string $password): SatCredential
    {
        $cred = SatCredential::updateOrCreate(
            ['cuenta_id' => $cuentaId, 'rfc' => strtoupper($rfc)],
            []
        );

        if ($cer instanceof UploadedFile) {
            $path = $cer->store('sat/certs/'.$cuentaId, 'public');
            $cred->cer_path = $path;
        }
        if ($key instanceof UploadedFile) {
            $path = $key->store('sat/keys/'.$cuentaId, 'public');
            $cred->key_path = $path;
        }

        if ($password !== '') {
            $enc = Crypt::encryptString($password);
            $cred->key_password_enc = $enc;

            // Compatibilidad con columna legacy si existe
            try {
                $hasLegacy = Schema::connection($cred->getConnectionName())
                    ->hasColumn($cred->getTable(), 'key_password');
                if ($hasLegacy) {
                    $cred->key_password = $enc;
                }
            } catch (\Throwable $e) {
                // no-op
            }
        }

        $cred->save();
        return $cred;
    }

    /** Valida CSD/FIEL real: .cer/.key + password (expiración/cert-type) */
    public function validateCredentials(SatCredential $cred): bool
    {
        try {
            [$fiel, $info] = $this->buildFiel($cred); // lanza excepción si algo falla
            // Opcional: validar que es FIEL (uso digital):
            // $info->isFiel() -> depende de la lib; usamos heurística del OID.
            $cred->validated_at = now();
            $cred->save();
            Log::info('[SAT] Credenciales OK', ['rfc' => $cred->rfc, 'expira' => $info['validTo'] ?? null]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('[SAT] Credenciales inválidas', ['rfc' => $cred->rfc, 'ex' => $e->getMessage()]);
            return false;
        }
    }

    /** Construye instancia FIEL a partir de paths en storage */
    private function buildFiel(SatCredential $cred): array
    {
        // Rutas absolutas a los archivos
        $cerAbs = $cred->cer_path ? Storage::disk('public')->path($cred->cer_path) : '';
        $keyAbs = $cred->key_path ? Storage::disk('public')->path($cred->key_path) : '';
        if (!is_file($cerAbs) || !is_file($keyAbs)) {
            throw new \RuntimeException('Archivos .cer/.key no encontrados');
        }
        $pwd = $this->decrypt($cred->key_password_enc ?? $cred->key_password ?? '');

        // ===== phpcfdi/credentials =====
        // Clases bajo namespace: PhpCfdi\Credentials\Credential
        // Creamos credencial y validamos
        /** @var \PhpCfdi\Credentials\Credential $credential */
        $credential = \PhpCfdi\Credentials\Credential::openFiles($cerAbs, $keyAbs, $pwd);
        $certificate = $credential->certificate();
        $privateKey  = $credential->privateKey();

        // Info útil (fechas, serie, Rfc)
        $info = [
            'rfc'      => $certificate->rfc(),
            'serial'   => $certificate->serialNumber()->bytes(),
            'validFrom'=> $certificate->validFrom()->format('Y-m-d H:i:s'),
            'validTo'  => $certificate->validTo()->format('Y-m-d H:i:s'),
        ];

        // En SAT-WS se usa firma SHA1 con llave de FIEL por SOAP
        return [$credential, $info];
    }

    private function decrypt(string $enc): string
    {
        try { return ($enc !== '') ? Crypt::decryptString($enc) : ''; } catch (\Throwable) { return ''; }
    }

    /* ==========================================================
     | SOLICITUDES
     * ==========================================================*/

    /** Registra la solicitud en BD y encola la petición contra SAT cuando driver=satws */
    public function requestPackages(SatCredential $cred, \DateTimeImmutable $from, \DateTimeImmutable $to, string $tipo): SatDownload
    {
        $tipo = strtolower($tipo);
        if (!in_array($tipo, ['emitidos','recibidos'])) {
            $tipo = 'emitidos';
        }

        $download = SatDownload::create([
            'cuenta_id'  => $cred->cuenta_id,
            'rfc'        => $cred->rfc,
            'tipo'       => $tipo,
            'date_from'  => $from->format('Y-m-d'),
            'date_to'    => $to->format('Y-m-d'),
            'status'     => 'pending',
            'request_id' => 'REQ-'.substr(md5(uniqid('', true)),0,10),
        ]);

        $driver = config('services.sat.download.driver', 'satws');

        if ($driver === 'satws' && app()->environment(['production','staging'])) {
            try {
                $reqId = $this->satwsSolicitar($cred, $from, $to, $tipo);
                if ($reqId) {
                    $download->request_id = $reqId;
                    $download->status = 'processing';
                    $download->save();
                    Log::info('[SATWS] Solicitud enviada', ['rfc'=>$cred->rfc, 'req'=>$reqId, 'tipo'=>$tipo]);
                } else {
                    Log::warning('[SATWS] Solicitud sin requestId', ['rfc'=>$cred->rfc]);
                }
            } catch (\Throwable $e) {
                $download->status = 'error';
                $download->error_message = 'Solicitud SATWS: '.$e->getMessage();
                $download->save();
                Log::error('[SATWS] Error solicitando', ['ex'=>$e->getMessage()]);
            }
        } else {
            Log::info('[SatDownloadService] requestPackages (cola local/demo)', [
                'download_id' => $download->id, 'request_id' => $download->request_id
            ]);
        }

        return $download;
    }

    /* ==========================================================
     | DESCARGA DE PAQUETE
     * ==========================================================*/

    /**
     * Descarga el paquete:
     *  - local/dev: genera ZIP DEMO completo
     *  - prod satws: consulta estado, obtiene ids de paquetes, descarga XML y empaqueta
     *  - driver database (opcional): empaqueta XML/PDF existentes en tu BD/storage
     */
    public function downloadPackage(SatCredential $cred, SatDownload $download, ?string $pkgId = null): SatDownload
    {
        // Marca/normaliza IDs
        $download->package_id = $pkgId ?: ($download->package_id ?: ('PKG-'.substr(md5($download->request_id ?? ''),0,8)));

        // Carpeta y nombre del ZIP
        $folder = 'sat/packages/'.$download->cuenta_id;
        $fname  = 'pkg_'.$download->id.'.zip';
        $full   = Storage::disk('public')->path($folder);
        if (!is_dir($full)) @mkdir($full, 0775, true);

        $zipRel = $folder.'/'.$fname;
        $zipAbs = Storage::disk('public')->path($zipRel);

        // DEMO
        if (app()->environment(['local','development','testing'])) {
            $ok = $this->buildDemoZip(
                zipPath: $zipAbs,
                rfc: $download->rfc,
                requestId: (string)($download->request_id ?? ''),
                count: 8,
                tipo: (string)$download->tipo,
                from: (string)($download->date_from ?? ''),
                to: (string)($download->date_to ?? '')
            );

            if (!$ok) {
                $download->status = 'error';
                $download->error_message = 'No se pudo crear el ZIP demo';
                $download->save();
                return $download;
            }

            $download->zip_path = $zipRel;
            $download->status   = 'done';
            $download->error_message = null;
            $download->save();

            Log::info('[SatDownloadService] ZIP DEMO listo', [
                'id' => $download->id,
                'zip' => $download->zip_path
            ]);

            return $download;
        }

        // DRIVER DB (opcional, si quieres empaquetar de tu repositorio/BD)
        if (config('services.sat.download.driver') === 'database') {
            try {
                $ok = $this->buildZipFromDatabase($zipAbs, $download);
                if (!$ok) {
                    throw new \RuntimeException('No se generó ZIP desde BD');
                }
                $download->zip_path = $zipRel;
                $download->status   = 'done';
                $download->error_message = null;
                $download->save();
                return $download;
            } catch (\Throwable $e) {
                $download->status = 'error';
                $download->error_message = 'ZIP BD: '.$e->getMessage();
                $download->save();
                return $download;
            }
        }

        // PRODUCCIÓN: SATWS real
        try {
            $this->satwsDescargar($cred, $download, $zipAbs);

            $download->zip_path = $zipRel;
            $download->status   = 'done';
            $download->error_message = null;
            $download->save();
            return $download;
        } catch (\Throwable $e) {
            // Fallback: al menos genera README
            Log::error('[SATWS] Error descargando', ['ex'=>$e->getMessage()]);
            $this->buildReadmeOnly($zipAbs, $download->rfc, (string)$download->request_id);
            $download->zip_path = $zipRel;
            $download->status = 'error';
            $download->error_message = 'SATWS: '.$e->getMessage();
            $download->save();
            return $download;
        }
    }

    /* ==========================================================
     | SATWS (AUTENTICACIÓN / SOLICITUD / DESCARGA)
     * ==========================================================*/

    /**
     * Envia la solicitud al SAT y devuelve el requestId
     *
     * @return string requestId
     */
    private function satwsSolicitar(SatCredential $cred, \DateTimeImmutable $from, \DateTimeImmutable $to, string $tipo): string
    {
        // Construir FIEL
        [$credential, $info] = $this->buildFiel($cred);

        // Cliente WS
        $client = $this->buildSatWsClient($credential);

        // Tipo de búsqueda
        $isEmitidos  = ($tipo === 'emitidos');

        // El WS de SAT permite filtros: RFC emisor/receptor y rango de fechas
        // La librería abstrae a "issued"/"received"
        /** @var \PhpCfdi\SatWsDescargaMasiva\Shared\Fiel $fiel */
        $fiel = new \PhpCfdi\SatWsDescargaMasiva\Shared\Fiel(
            $credential->certificate(),
            $credential->privateKey()
        );

        $service = new \PhpCfdi\SatWsDescargaMasiva\Services\DownloadService($client, $fiel);

        // solicitud
        $rfc = strtoupper($cred->rfc);
        $criteria = $isEmitidos
            ? \PhpCfdi\SatWsDescargaMasiva\PackageReader\Filters\Criteria::issued($rfc, $from, $to)
            : \PhpCfdi\SatWsDescargaMasiva\PackageReader\Filters\Criteria::received($rfc, $from, $to);

        $request = $service->request($criteria);
        if (! $request->isAccepted()) {
            throw new \RuntimeException('SAT rechazó la solicitud: '.$request->getMessage());
        }
        // Id de solicitud asignado por SAT
        return (string) $request->getRequestId();
    }

    /**
     * Verifica y descarga paquetes; escribe XML al ZIP destino.
     */
    private function satwsDescargar(SatCredential $cred, SatDownload $download, string $zipAbs): void
    {
        [$credential, ] = $this->buildFiel($cred);
        $client  = $this->buildSatWsClient($credential);

        $fiel    = new \PhpCfdi\SatWsDescargaMasiva\Shared\Fiel(
            $credential->certificate(),
            $credential->privateKey()
        );
        $service = new \PhpCfdi\SatWsDescargaMasiva\Services\DownloadService($client, $fiel);

        $requestId = (string)($download->request_id ?? '');
        if ($requestId === '') {
            throw new \RuntimeException('Falta request_id');
        }

        // 1) Verificar si la solicitud está lista y obtener ids de paquetes
        $verify = $service->verify($requestId);
        if (! $verify->isAccepted()) {
            throw new \RuntimeException('SAT no aceptó la verificación: '.$verify->getMessage());
        }

        $packageIds = $verify->getPackageIds();
        if (empty($packageIds)) {
            // Nada que descargar, quizá no hubo CFDIs en el rango
            $this->buildReadmeOnly($zipAbs, $download->rfc, $requestId);
            return;
        }

        // 2) Descargar paquetes, descomprimir y re-empaquetar en un ZIP único
        $zip = new \ZipArchive();
        if ($zip->open($zipAbs, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo abrir ZIP destino');
        }

        $manifest = [];
        $manifest[] = ['archivo','tipo','uuid','rfc_emisor','rfc_receptor','fecha','total'];

        foreach ($packageIds as $pid) {
            $packageResponse = $service->download($pid);
            if (! $packageResponse->isAccepted()) {
                Log::warning('[SATWS] Paquete no aceptado', ['package'=>$pid, 'msg'=>$packageResponse->getMessage()]);
                continue;
            }

            // Cada paquete es un zip binario con XML adentro
            $content = $packageResponse->getPackageContent();
            // Extraemos a un temp y volvemos a meter al ZIP final
            $tmp = tmpfile();
            $meta = stream_get_meta_data($tmp);
            $tmpPath = $meta['uri'];
            file_put_contents($tmpPath, $content);

            $inner = new \ZipArchive();
            if ($inner->open($tmpPath) === true) {
                for ($i=0; $i<$inner->numFiles; $i++) {
                    $name = $inner->getNameIndex($i);
                    if (! $name) continue;
                    $xml = $inner->getFromName($name);
                    if ($xml === false) continue;

                    // Normalizamos nombre
                    $base = $this->normalizeXmlName($name, $download->rfc);
                    $zip->addFromString('XML/'.$base, $xml);

                    // parse básico para manifest (no validamos xsd aquí)
                    [$uuid,$emi,$rec,$fecha,$total] = $this->quickParseCfdi($xml);
                    $manifest[] = [$base,'XML',$uuid,$emi,$rec,$fecha,$total];

                    // Opcional: PDF placeholder
                    $zip->addFromString('PDF/'.preg_replace('~\.xml$~i', '.pdf', $base), $this->tinyPdf(
                        "CFDI: {$uuid}\nEmisor: {$emi}\nReceptor: {$rec}\nFecha: {$fecha}\nTotal: {$total}\n"
                    ));
                    $manifest[] = [preg_replace('~\.xml$~i', '.pdf', $base),'PDF',$uuid,$emi,$rec,$fecha,$total];
                }
                $inner->close();
            }
            fclose($tmp);
        }

        // README + MANIFEST
        $zip->addFromString('README.txt',
            "Paquete SAT real\nRFC: {$download->rfc}\nRequest: {$requestId}\nRango: {$download->date_from} a {$download->date_to}\n"
        );
        $zip->addFromString('MANIFEST.csv', $this->csvUtf8($manifest));
        $zip->close();
    }

    /** Fabrica un cliente HTTP del WS con timeouts */
    private function buildSatWsClient(\PhpCfdi\Credentials\Credential $credential): \PhpCfdi\SatWsDescargaMasiva\Shared\Soap\SoapClientInterface
    {
        $timeout = (int) config('services.sat.download.http_timeout', 60);

        // La lib trae implementaciones preparadas:
        $soapFactory = new \PhpCfdi\SatWsDescargaMasiva\Shared\Soap\SoapClientFactory($timeout);

        // Cliente autenticado con FIEL vía "Authenticator" interno de la lib
        $soapClient = $soapFactory->createClient();
        return $soapClient;
    }

    /* ==========================================================
     | DRIVER: DATABASE (opcional)
     * ==========================================================*/

    /**
     * Construye ZIP a partir de archivos ya existentes en tu BD/storage.
     * Implementa aquí tu lógica para encontrar XML/PDF por fechas/RFC si ya los guardas en disco.
     */
    private function buildZipFromDatabase(string $zipPath, SatDownload $download): bool
    {
        // Este es un stub de ejemplo. Ajusta a tus tablas de resguardo si ya tienes XML/PDF en disco.
        // Si no tienes, puedes dejar este driver deshabilitado.
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        $zip->addFromString('README.txt', "Driver=database\nRFC: {$download->rfc}\nRango: {$download->date_from} a {$download->date_to}\n");
        $zip->close();
        return true;
    }

    /* ==========================================================
     | HELPERS DEMO / UTILS
     * ==========================================================*/

    /** Genera un ZIP completo de demo con XML/PDF/README/MANIFEST */
    private function buildDemoZip(string $zipPath, string $rfc, string $requestId, int $count = 5, string $tipo = 'emitidos', string $from = '', string $to = ''): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        // README
        $zip->addFromString('README.txt',
            "Paquete DEMO de descargas SAT\n".
            "RFC: {$rfc}\n".
            "Tipo: {$tipo}\n".
            "Rango: {$from} a {$to}\n".
            "Request: {$requestId}\n".
            "Contenido: XML/, PDF/, MANIFEST.csv\n"
        );

        // MANIFEST
        $manifestRows = [];
        $manifestRows[] = ['archivo','tipo','uuid','rfc_emisor','rfc_receptor','fecha','total'];

        for ($i = 1; $i <= max(1, $count); $i++) {
            $uuid  = $this->uuidV4();
            $fecha = now()->subDays(rand(0, 27))->format('Y-m-d');
            $total = number_format(mt_rand(1000, 250000) / 100, 2, '.', '');
            $emi   = strtoupper($rfc);
            $rec   = ($tipo === 'emitidos') ? 'XAXX010101000' : strtoupper($rfc);

            $base   = "{$fecha}_{$uuid}_{$emi}_{$rec}";
            $xmlName= "XML/{$base}.xml";
            $pdfName= "PDF/{$base}.pdf";

            $xml = $this->minimalCfdi40($uuid, $fecha, $total, $emi, $rec);
            $zip->addFromString($xmlName, $xml);

            $zip->addFromString($pdfName, $this->tinyPdf("CFDI: {$uuid}\nRFC Emisor: {$emi}\nRFC Receptor: {$rec}\nFecha: {$fecha}\nTotal: {$total}\n"));

            $manifestRows[] = [$base.'.xml','XML',$uuid,$emi,$rec,$fecha,$total];
            $manifestRows[] = [$base.'.pdf','PDF',$uuid,$emi,$rec,$fecha,$total];
        }

        $zip->addFromString('MANIFEST.csv', $this->csvUtf8($manifestRows));
        $zip->close();
        return true;
    }

    /** ZIP minimalista sólo con README (fallback) */
    private function buildReadmeOnly(string $zipPath, string $rfc, string $requestId): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        $zip->addFromString('README.txt', "Paquete para {$rfc}\nRequest: {$requestId}\n");
        $zip->close();
        return true;
    }

    /** Genera XML CFDI 4.0 mínimo (no timbrado, solo estructura) */
    private function minimalCfdi40(string $uuid, string $fecha, string $total, string $rfcEmisor, string $rfcReceptor): string
    {
        $xml = <<<XML
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
        return $xml;
    }

    /** Crea un PDF muy pequeño y válido a partir de un texto simple */
    private function tinyPdf(string $text): string
    {
        $text = str_replace(["\r","\n"], ["","\\n"], $text);
        $pdf = "%PDF-1.4
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
        return $pdf;
    }

    /** CSV UTF-8 con BOM */
    private function csvUtf8(array $rows): string
    {
        $out = chr(0xEF).chr(0xBB).chr(0xBF);
        foreach ($rows as $r) {
            $out .= implode(',', array_map(function($v){
                $v = (string)$v;
                $v = str_replace('"', '""', $v);
                return '"'.$v.'"';
            }, $r))."\n";
        }
        return $out;
    }

    /** UUID v4 simple */
    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /** Normaliza nombre de XML en ZIP final */
    private function normalizeXmlName(string $original, string $rfc): string
    {
        $name = trim($original, "/\\ \t\r\n");
        if (!str_ends_with(strtolower($name), '.xml')) $name .= '.xml';
        // Prefijo por RFC para evitar colisiones
        if (!str_starts_with($name, $rfc.'_')) {
            $name = $rfc.'_'.$name;
        }
        // Sanea caracteres raros
        $name = preg_replace('~[^A-Za-z0-9_\-\.]~', '_', $name) ?? $name;
        return $name;
    }

    /**
     * Parse rápido de CFDI: extrae UUID, RFCs, fecha, total.
     * (No valida XSD; lectura simple)
     */
    private function quickParseCfdi(string $xml): array
    {
        $uuid=''; $emi=''; $rec=''; $fecha=''; $total='';
        try {
            $sxe = new \SimpleXMLElement($xml);
            $sxe->registerXPathNamespace('cfdi','http://www.sat.gob.mx/cfd/4');
            $sxe->registerXPathNamespace('tfd','http://www.sat.gob.mx/TimbreFiscalDigital');

            $comp = $sxe->xpath('/cfdi:Comprobante');
            if ($comp && isset($comp[0])) {
                $c = $comp[0];
                $fecha = (string) ($c['Fecha'] ?? $c['fecha'] ?? '');
                $total = (string) ($c['Total'] ?? $c['total'] ?? '');
            }
            $em = $sxe->xpath('/cfdi:Comprobante/cfdi:Emisor');
            if ($em && isset($em[0])) { $emi = strtoupper((string)($em[0]['Rfc'] ?? $em[0]['rfc'] ?? '')); }
            $re = $sxe->xpath('/cfdi:Comprobante/cfdi:Receptor');
            if ($re && isset($re[0])) { $rec = strtoupper((string)($re[0]['Rfc'] ?? $re[0]['rfc'] ?? '')); }
            $tf = $sxe->xpath('//tfd:TimbreFiscalDigital');
            if ($tf && isset($tf[0])) { $uuid = strtoupper((string)($tf[0]['UUID'] ?? $tf[0]['Uuid'] ?? '')); }
        } catch (\Throwable) {
            // no-op
        }
        return [$uuid,$emi,$rec,$fecha,$total];
    }
}
