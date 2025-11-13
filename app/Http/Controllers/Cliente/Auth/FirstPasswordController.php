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
    /** GET /cliente/password/first -> muestra formulario (FORZA TEMA CLARO) */
    public function show(Request $request)
    {
        // Forzar tema claro para esta pantalla (coherente con /home por ahora)
        session(['client_ui.theme' => 'light']);

        /** @var \App\Models\Cliente\UsuarioCuenta|null $u */
        $u = Auth::guard('web')->user();
        if (!$u) {
            return redirect()->route('cliente.login');
        }

        // Si no está marcada la bandera, redirigir al home
        if (!($u->must_change_password ?? false)) {
            return redirect()->route('cliente.home');
        }

        $email = $request->input('email', $u->email ?? null);

        // Vista de primer cambio de contraseña
        return view('cliente.auth.password_first', compact('email'));
    }

    /** POST /cliente/password/first -> guarda y limpia bandera */
    public function store(Request $r)
    {
        $r->validate([
            'password' => 'required|string|min:8|max:100|confirmed',
        ]);

        /** @var \App\Models\Cliente\UsuarioCuenta|null $u */
        $u = Auth::guard('web')->user();
        if (!$u) {
            return redirect()->route('cliente.login');
        }

        $u->password = ClientAuth::make((string) $r->input('password'));

        // Limpia la bandera si existe la columna
        try {
            if (Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta', 'must_change_password')) {
                $u->must_change_password = 0;
            }
        } catch (\Throwable $e) {
            // La columna puede no existir en algunos entornos
        }

        $u->saveQuietly();

        return redirect()->route('cliente.home')->with('ok', 'Contraseña actualizada.');
    }
}
