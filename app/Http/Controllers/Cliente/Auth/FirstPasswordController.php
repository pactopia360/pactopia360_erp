<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Auth;

use App\Http\Controllers\Controller;
use App\Support\ClientAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class FirstPasswordController extends Controller
{
    /** GET /cliente/password/first  -> muestra formulario */
    public function show()
    {
        return view('cliente.auth.first');
        
        /** @var \App\Models\Cliente\UsuarioCuenta|null $u */
        $u = Auth::guard('web')->user();
        if (!$u) return redirect()->route('cliente.login');

        // Si no está marcada la bandera, mándalo al home
        if (!($u->must_change_password ?? false)) {
            return redirect()->route('cliente.home');
        }

        return view('cliente.auth.first_password'); // crea un blade simple con 2 campos
    }

    /** POST /cliente/password/first -> guarda y limpia bandera */
    public function store(Request $r)
    {
        $r->validate([
            'password' => 'required|string|min:8|max:100|confirmed',
        ]);

        /** @var \App\Models\Cliente\UsuarioCuenta|null $u */
        $u = Auth::guard('web')->user();
        if (!$u) return redirect()->route('cliente.login');

        $u->password = ClientAuth::make((string) $r->input('password'));
        try { if (Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta','must_change_password')) $u->must_change_password = 0; } catch (\Throwable $e) {}
        $u->saveQuietly();

        return redirect()->route('cliente.home')->with('ok','Contraseña actualizada.');
    }
}
