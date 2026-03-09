<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\AdminIncomeDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class IncomeController extends Controller
{
    public function index(Request $request, AdminIncomeDashboardService $service): View
    {
        // -------------------------------------------------------
        // Nuevo filtro de origen de ingresos
        // -------------------------------------------------------
        $source = $request->get('source', 'all');

        if (!in_array($source, ['all','sales','statements'], true)) {
            $source = 'all';
        }

        // agregamos al request para que el service lo use
        $request->merge([
            'source' => $source,
        ]);

        $data = $service->build($request);

        return view('admin.finance.income.index', [
            'filters'   => $data['filters']   ?? [],
            'kpis'      => $data['kpis']      ?? [],
            'charts'    => $data['charts']    ?? [],
            'highlights'=> $data['highlights']?? [],
            'rows'      => $data['rows']      ?? collect(),
            'meta'      => $data['meta']      ?? [],
            'source'    => $source, // nuevo
        ]);
    }
}