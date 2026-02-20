<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CommissionsController extends Controller
{
    private string $adm;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function index(Request $req): View
    {
        // Placeholder estable (Paso 1): solo listamos vendedores y ventas recientes.
        $vendors = DB::connection($this->adm)->table('finance_vendors')
            ->orderBy('name')
            ->get(['id','name','commission_rate','is_active']);

        $sales = DB::connection($this->adm)->table('finance_sales as s')
            ->leftJoin('finance_vendors as v', 'v.id', '=', 's.vendor_id')
            ->orderByDesc('s.id')
            ->limit(100)
            ->get([
                's.id',
                's.sale_code',
                's.period',
                's.total',
                's.subtotal',
                's.iva',
                's.vendor_id',
                'v.name as vendor_name',
                'v.commission_rate',
                's.created_at',
            ]);

        return view('admin.finance.commissions.index', [
            'vendors' => $vendors,
            'sales'   => $sales,
        ]);
    }
}
