<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Mostrar formulario de login admin.
     */
    public function showLogin(Request $request)
    {
        // Forzar a usar siempre el guard 'admin' en esta pantalla
        Auth::shouldUse('admin');

        // Si ya hay sesión de admin, redirigir al home admin
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.home');
        }

        return view('admin.auth.login');
    }

    /**
     * Procesar login admin.
     */
    public function login(Request $request)
    {
        Auth::shouldUse('admin'); // refuerza el guard correcto

        $credentials = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required'],
        ]);

        // Solo usuarios activos
        $credentials['activo'] = 1;

        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Redirección segura al dashboard admin
            return redirect()->intended(route('admin.home'));
        }

        return back()
            ->withErrors(['email' => 'Credenciales inválidas'])
            ->onlyInput('email');
    }

    /**
     * Cerrar sesión admin.
     */
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
