<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class CfdiStampService
{
    private string $adm;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    /**
     * Timbrar CFDI de una factura
     */
    public function stamp(int $invoiceId): array
    {
        $invoice = DB::connection($this->adm)
            ->table('billing_invoices')
            ->where('id', $invoiceId)
            ->first();

        if (!$invoice) {
            throw new \RuntimeException('Factura no encontrada.');
        }

        if (!empty($invoice->cfdi_uuid)) {
            throw new \RuntimeException('La factura ya está timbrada.');
        }

        /**
         * 1 Generar XML CFDI
         */
        $xml = $this->buildXml($invoice);

        /**
         * 2 Enviar a PAC
         * (simulado por ahora)
         */
        $uuid = (string) Str::uuid();

        /**
         * 3 Guardar XML
         */
        $xmlPath = 'cfdi/' . $uuid . '.xml';

        Storage::disk('local')->put($xmlPath, $xml);

        /**
         * 4 Guardar PDF (placeholder)
         */
        $pdfPath = 'cfdi/' . $uuid . '.pdf';

        Storage::disk('local')->put(
            $pdfPath,
            'PDF placeholder para factura ' . $uuid
        );

        /**
         * 5 Actualizar factura
         */
        DB::connection($this->adm)
            ->table('billing_invoices')
            ->where('id', $invoiceId)
            ->update([
                'cfdi_uuid' => $uuid,
                'xml_path' => $xmlPath,
                'pdf_path' => $pdfPath,
                'status' => 'stamped',
                'issued_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'uuid' => $uuid,
            'xml_path' => $xmlPath,
            'pdf_path' => $pdfPath,
        ];
    }

    /**
     * Construir XML CFDI (base)
     */
    private function buildXml(object $invoice): string
    {
        $total = number_format((float) ($invoice->amount_mxn ?? 0), 2, '.', '');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante
    Version="4.0"
    Serie="{$invoice->serie}"
    Folio="{$invoice->folio}"
    Fecha="{$invoice->issued_at}"
    SubTotal="{$total}"
    Total="{$total}"
    Moneda="MXN"
    TipoDeComprobante="I"
    xmlns:cfdi="http://www.sat.gob.mx/cfd/4">

    <cfdi:Emisor
        Rfc="AAA010101AAA"
        Nombre="PACTOPIA360 SA DE CV"
        RegimenFiscal="601"/>

    <cfdi:Receptor
        Rfc="{$invoice->receptor_rfc}"
        Nombre="{$invoice->receptor_nombre}"
        UsoCFDI="G03"/>

</cfdi:Comprobante>
XML;
    }
}