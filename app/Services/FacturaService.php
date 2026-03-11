<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class FacturaService
{

    public static function generarFactura($paymentId)
    {

        $pago = DB::table('payments')->where('id',$paymentId)->first();

        $cuenta = DB::table('cuentas')
        ->where('id',$pago->account_id)
        ->first();

        $xml = CFDIGenerator::crearXML($pago,$cuenta);

        $response = Http::post(
            'https://api.facturotopia.com/timbrar',
            [
                'xml'=>$xml
            ]
        );

        $data = $response->json();

        $uuid = $data['uuid'];
        $xmlTimbrado = $data['xml'];

        $xmlPath = "facturas/$uuid.xml";

        Storage::put($xmlPath,$xmlTimbrado);

        $pdf = PDFService::crearPDF($xmlTimbrado);

        $pdfPath = "facturas/$uuid.pdf";

        Storage::put($pdfPath,$pdf);

        DB::table('facturas')->insert([
            'uuid'=>$uuid,
            'account_id'=>$pago->account_id,
            'payment_id'=>$paymentId,
            'xml_path'=>$xmlPath,
            'pdf_path'=>$pdfPath,
            'total'=>$pago->amount,
            'status'=>'timbrado'
        ]);

        EmailFactura::enviar($cuenta,$pdfPath,$xmlPath);

    }

}