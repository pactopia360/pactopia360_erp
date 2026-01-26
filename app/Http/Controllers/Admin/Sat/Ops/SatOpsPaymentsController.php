<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Sat\Ops;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SatOpsPaymentsController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.sat.ops.payments.index', [
            'title' => 'SAT · Operación · Pagos',
        ]);
    }
}
