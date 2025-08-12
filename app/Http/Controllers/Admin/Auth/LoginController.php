<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    /**
     * Mostrar formulario de login admin.
     */
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    /**
     * Procesar login admin.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required'],
        ]);

        // Solo usuarios activos
        $credentials['activo'] = 1;

        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Guardar último acceso
            $adminId = Auth::guard('admin')->id();
            return redirect()->route('admin.dashboard');
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
