<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DescargasController extends Controller
{
    /**
     * Listado simple de descargas disponibles para el cliente.
     * Ruta: GET /cliente/sat/descargas  -> name: cliente.sat.descargas.index
     */
    public function index(Request $request)
    {
        // IMPORTANTE: usar SIEMPRE el guard 'web' en portal cliente
        $user = auth('web')->user();
        if (!$user) {
            return redirect()->route('cliente.login');
        }

        // Puedes alimentar esto desde BD; por ahora archivos demo en /storage/app/public/descargas
        $items = [
            ['name' => 'Manual rápido Pactopia360 (PDF)', 'url' => url('storage/descargas/manual-rapido.pdf')],
            ['name' => 'Plantilla Importación CFDI (CSV)', 'url' => url('storage/descargas/plantilla_cfdi.csv')],
        ];

        return view('cliente.descargas.index', compact('items'));
    }
}
