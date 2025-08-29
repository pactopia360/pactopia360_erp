<?php

namespace App\Http\Controllers\Cliente;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class EstadoCuentaController extends Controller
{
    public function index()
    {
        $cuentaId = auth()->user()->cuenta_id;
        $movs = DB::connection('mysql_admin')->table('estados_cuenta')
            ->where('cuenta_id',$cuentaId)
            ->orderByDesc('periodo')
            ->limit(60)->get();

        return view('cliente.estado_cuenta', compact('movs'));
    }
}
