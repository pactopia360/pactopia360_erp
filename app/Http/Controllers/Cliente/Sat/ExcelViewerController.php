<?php
declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExcelViewerController extends Controller
{
    public function preview(Request $r)
    {
        $r->validate(['file'=>'required|file|mimes:xlsx,csv,txt']);
        $file = $r->file('file')->getRealPath();

        // Usa tu lector (Spout, PhpSpreadsheet, League CSV, etc.)
        // Por ahora, devolvemos una tabla mínima:
        $sample = [
            ['RFC','Total','UUID'],
            ['AAA010101AAA', '1160.00', 'xxxx-1111'],
            ['XEXX010101000', '2320.00', 'xxxx-2222'],
        ];

        return response()->json(['ok'=>true,'headers'=>$sample[0],'rows'=>array_slice($sample,1)]);
    }
}
