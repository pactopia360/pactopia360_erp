<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class ReportesController extends Controller
{
    public function index()
    {
        return view()->exists('admin.reportes.index')
            ? view('admin.reportes.index')
            : response('<h1>Reportes</h1><p>Próximamente gráficos y listados.</p>', 200);
    }
}
