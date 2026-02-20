<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\AdminIncomeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class IncomeController extends Controller
{
    public function index(Request $req, AdminIncomeService $svc): View
    {
        $data = $svc->build($req);

        return view('admin.finance.income.index', [
            'filters' => $data['filters'],
            'kpis'    => $data['kpis'],
            'rows'    => $data['rows'],
        ]);
    }
}
