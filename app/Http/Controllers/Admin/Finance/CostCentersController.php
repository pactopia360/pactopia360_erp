<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class CostCentersController extends Controller
{
    public function index(): View
    {
        return view('admin.finance.cost_centers.index');
    }
}
