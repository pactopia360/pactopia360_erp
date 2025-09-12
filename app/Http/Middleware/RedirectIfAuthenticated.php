<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Si el usuario YA está autenticado en el/los guard(s) indicados,
     * redirige al HOME del panel. Si no se indicó guard, elegimos
     * inteligentemente según la URL/route (admin -> guard 'admin').
     */
    public function handle(Request $request, Closure $next, string ...$guards)
    {
        // Si no especificaron guard, elegimos por contexto.
        if (empty($guards)) {
            $routeName = optional($request->route())->getName();
            $isAdminArea =
                ($routeName && str_starts_with($routeName, 'admin.')) ||
                $request->is('admin') || $request->is('admin/*');

            $guards = [$isAdminArea ? 'admin' : config('auth.defaults.guard', 'web')];
        }

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Ya tiene sesión en ese guard → manda al HOME del sistema
                return redirect()->intended(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
