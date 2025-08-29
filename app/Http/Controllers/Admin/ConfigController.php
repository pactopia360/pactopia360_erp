<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class ConfigController extends Controller
{
    public function index()
    {
        return view()->exists('admin.config.index')
            ? view('admin.config.index')
            : response('<h1>Configuración</h1><p>Opciones del sistema.</p>', 200);
    }
}
