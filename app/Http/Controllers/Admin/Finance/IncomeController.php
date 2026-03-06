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
        $data = $service->build($request);

        return view('admin.finance.income.index', [
            'filters'   => $data['filters']   ?? [],
            'kpis'      => $data['kpis']      ?? [],
            'charts'    => $data['charts']    ?? [],
            'highlights'=> $data['highlights']?? [],
            'rows'      => $data['rows']      ?? collect(),
            'meta'      => $data['meta']      ?? [],
        ]);
    }
}