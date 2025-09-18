<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class LoginController extends Controller
{
    /**
     * Mostrar formulario de login admin.
     */
    public function showLogin(Request $request)
    {
        // Forzar guard admin en esta pantalla
        Auth::shouldUse('admin');

        // Si ya hay sesión admin, redirige al home
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
        Auth::shouldUse('admin'); // refuerza guard correcto

        // Acepta email o codigo_usuario (NO usar username porque no existe la columna)
        $loginField = trim($request->input('email', $request->input('codigo_usuario', '')));

        // Validación mínima (string para permitir email o código)
        $request->merge(['_login' => $loginField]);
        $data = $request->validate([
            '_login'   => ['required','string'],
            'password' => ['required','string'],
        ], [], ['_login' => 'usuario/email']);

        $remember = $request->boolean('remember', false);

        // 1) Intento por email
        $ok = Auth::guard('admin')->attempt(
            ['email' => $data['_login'], 'password' => $data['password']],
            $remember
        );

        // 2) Si falla por email, intenta por codigo_usuario SOLO si la columna existe
        if (!$ok) {
            // Intentar detectar la tabla del provider 'admin'
            $provider = Auth::guard('admin')->getProvider();
            $modelCls = method_exists($provider, 'getModel') ? $provider->getModel() : null;
            $table    = $modelCls ? (new $modelCls)->getTable() : 'usuario_administrativos';

            // Usa la conexión por defecto (en tu proyecto apunta a p360v1_admin)
            $canCodigo = Schema::hasColumn($table, 'codigo_usuario');

            if ($canCodigo) {
                $ok = Auth::guard('admin')->attempt(
                    ['codigo_usuario' => $data['_login'], 'password' => $data['password']],
                    $remember
                );
            }
        }

        if (!$ok) {
            return back()
                ->withErrors(['email' => 'Credenciales inválidas'])
                ->onlyInput('email');
        }

        // Ya autenticado: checa 'activo' y 'estatus' si existen
        $user = Auth::guard('admin')->user();

        if (isset($user->activo) && (int)$user->activo !== 1) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Cuenta inactiva'])->onlyInput('email');
        }

        if (isset($user->estatus) && !in_array(strtolower((string)$user->estatus), ['activo','active','enabled'], true)) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Cuenta no autorizada'])->onlyInput('email');
        }

        // Regenera sesión y redirige
        $request->session()->regenerate();
        return redirect()->intended(route('admin.home'));
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
