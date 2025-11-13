<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Evita que un usuario autenticado vea páginas públicas (login/registro).
     *
     * Resolución del guard:
     * - Si el middleware se invoca como guest:cliente -> usa guard 'cliente'
     * - Si se invoca como guest:admin   -> usa guard 'admin'
     * - Si no recibe guard explícito, infiere por URL:
     *     /admin/*   -> 'admin'
     *     /cliente/* -> 'cliente'
     *     otro       -> auth.defaults.guard (por defecto 'cliente')
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        // Normaliza guards (si vienen de guest:<guard>)
        $guards = array_values(array_filter($guards, fn ($g) => $g !== null && $g !== ''));

        // Si no vienen, inferimos por URL
        if (empty($guards)) {
            if ($request->is('admin') || $request->is('admin/*')) {
                $guards = ['admin'];
            } elseif ($request->is('cliente') || $request->is('cliente/*')) {
                $guards = ['cliente'];
            } else {
                $guards = [config('auth.defaults.guard', 'cliente')];
            }
        }

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Redirección por guard activo
                return redirect()->to($this->homeUrlForGuard($guard));
            }
        }

        return $next($request);
    }

    /**
     * Devuelve la URL "home" para el guard indicado, usando rutas nombradas si existen.
     */
    private function homeUrlForGuard(string $guard): string
    {
        if ($guard === 'admin') {
            if (Route::has('admin.home')) {
                return route('admin.home');
            }
            // Fallback razonable
            return url('/admin');
        }

        // Cliente (web) por defecto
        if (Route::has('cliente.home')) {
            return route('cliente.home');
        }
        return url('/cliente');
    }
}
