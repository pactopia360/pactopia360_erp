<?php

namespace App\Http\Controllers\Api\Mobile\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MobileInvoicesController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'ok' => true,
            'data' => [],
            'message' => 'Módulo facturas móvil en construcción'
        ]);
    }
}