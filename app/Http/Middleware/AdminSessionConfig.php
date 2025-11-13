<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminSessionConfig
{
    /**
     * Forzamos que TODAS las rutas admin usen su propia cookie de sesión
     * (independiente del portal cliente) para evitar pisarnos el guard.
     */
    public function handle(Request $request, Closure $next)
    {
        // cookie exclusiva para admin
        config([
            'session.cookie' => 'p360_admin_session',
            // opcional: podemos aislar también el session.path si quieres,
            // pero con cookie separada basta. Si quisieras limitarla:
            // 'session.path'   => '/admin',
        ]);

        // MUY IMPORTANTE: forzamos guard default a "admin"
        // para que algunos middlewares genéricos como 'auth' usen admin aquí.
        config([
            'auth.defaults.guard' => 'admin',
            'auth.defaults.passwords' => 'admins', // opcional
        ]);

        return $next($request);
    }
}
