<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ProjectionsController extends Controller
{
    private string $adm;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function index(Request $req): View
    {
        // Placeholder estable (Paso 1): preparamos meses y totales simples.
        $from = (string) ($req->input('from') ?: now()->format('Y-m'));
        $to   = (string) ($req->input('to')   ?: now()->addMonths(5)->format('Y-m'));

        $sales = DB::connection($this->adm)->table('finance_sales')
            ->whereNotNull('period')
            ->whereBetween('period', [$from, $to])
            ->get(['period','total','statement_status','invoice_status']);

        $byMonth = $sales->groupBy('period')->map(function ($rows) {
            return [
                'total' => (float) $rows->sum('total'),
                'count' => (int) $rows->count(),
            ];
        })->sortKeys();

        return view('admin.finance.projections.index', [
            'from'    => $from,
            'to'      => $to,
            'byMonth' => $byMonth,
        ]);
    }
}
