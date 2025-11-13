<?php
declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiotController extends Controller
{
    public function buildBatch(Request $r)
    {
        // Espera rows ya normalizados (desde visor o desde tus XML)
        $rows = (array) $r->input('rows', []);
        // Genera DIOT (formato TXT según especificación vigente).
        // Demo: 13 campos por línea, | separador
        $lines = [];
        foreach ($rows as $i => $row) {
            $line = [
                $row['RFC'] ?? '',
                $row['Nacionalidad'] ?? 'N',
                $row['TipoTercero'] ?? '04',
                $row['TipoOperacion'] ?? '85',
                $row['Monto'] ?? '0.00',
                $row['IVA'] ?? '0.00',
                $row['RetIVA'] ?? '0.00',
                $row['RetISR'] ?? '0.00',
                $row['IVA16'] ?? '0.00',
                $row['IVAz'] ?? '0.00',
                $row['IVA0'] ?? '0.00',
                $row['IVAExento'] ?? '0.00',
                $row['IVAImport'] ?? '0.00',
            ];
            $lines[] = implode('|', $line);
        }

        $txt = implode("\r\n", $lines)."\r\n";
        $name = 'diot_'.Str::random(6).'.txt';
        return response($txt,200,[
            'Content-Type'=>'text/plain; charset=utf-8',
            'Content-Disposition'=>'attachment; filename="'.$name.'"',
        ]);
    }
}
