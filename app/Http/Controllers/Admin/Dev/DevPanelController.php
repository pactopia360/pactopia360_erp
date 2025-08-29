<?php

namespace App\Http\Controllers\Admin\Dev;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevPanelController extends Controller
{
    public function index(){ return view('admin.dev.panel'); }

    public function truncate(Request $r){
        $r->validate(['tabla'=>'required|string']);
        DB::connection('mysql_admin')->statement('TRUNCATE TABLE '.$r->tabla);
        return back()->with('ok','Tabla limpiada');
    }

    public function optimize(){
        DB::connection('mysql_admin')->statement('OPTIMIZE TABLE cuentas, planes, pagos, estados_cuenta');
        return back()->with('ok','Tablas optimizadas');
    }
}
