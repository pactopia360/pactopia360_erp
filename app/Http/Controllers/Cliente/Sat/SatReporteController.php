<?php
declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class SatReporteController extends Controller
{
    public function index()
    {
        return view('cliente.sat.reporte'); // tu vista existente
    }

    public function export(Request $r)
    {
        // params: periodo(YYYY-MM), tipo(Emitidos|Recibidos), fmt(csv|xlsx), include_concepts(0|1)
        $periodo = (string) $r->input('periodo','');
        $tipo    = (string) $r->input('tipo','Emitidos');
        $fmt     = Str::lower((string) $r->input('fmt','csv'));
        $inc     = (bool) $r->boolean('include_concepts', false);

        // Aquí llama a tu servicio que lee XML y arma dataset
        $rows = $this->fakeRows($tipo, $inc);

        if ($fmt === 'xlsx') {
            $xlsx = app(\App\Support\XlsxBuilder::class)->fromArray($rows, 'Reporte');
            $path = storage_path('app/temp/reporte_'.Str::slug($tipo).'_'.($periodo ?: 'periodo').'.xlsx');
            @mkdir(dirname($path), 0775, true);
            file_put_contents($path, $xlsx);
            return Response::download($path)->deleteFileAfterSend(true);
        }

        $csv  = $this->csv($rows);
        return response($csv,200,[
            'Content-Type'=>'text/csv; charset=utf-8',
            'Content-Disposition'=>'attachment; filename="reporte_'.Str::slug($tipo).'_'.($periodo ?: 'periodo').'.csv"',
        ]);
    }

    public function exportCanceled(Request $r){ return $this->exportByKind($r, 'cancelados'); }
    public function exportPayments(Request $r){ return $this->exportByKind($r, 'pagos'); }
    public function exportCreditNotes(Request $r){ return $this->exportByKind($r, 'notas_credito'); }

    private function exportByKind(Request $r, string $kind)
    {
        $r->merge(['tipo'=>ucwords(str_replace('_',' ', $kind))]);
        return $this->export($r);
    }

    /* ===== helpers mínimos ===== */
    private function csv(array $rows): string {
        if (empty($rows)) return '';
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) fputcsv($out, $row);
        rewind($out); $csv = stream_get_contents($out); fclose($out);
        return $csv ?: '';
    }
    private function fakeRows(string $tipo, bool $inc): array {
        $base = [
            'UUID'=>'XXXX-YYYY','RFC Emisor'=>'AAA010101AAA','RFC Receptor'=>'XAXX010101000',
            'Fecha'=>'2025-11-01','Subtotal'=>1000.00,'IVA'=>160.00,'Total'=>1160.00,'Metodo'=>'PPD','UsoCFDI'=>'G03',
        ];
        if ($inc) $base['Conceptos'] = '1-Servicio demo;2-Producto demo';
        return [$base];
    }
}
