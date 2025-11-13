<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ClientSessionConfig
{
    /**
     * Forzamos que TODAS las rutas cliente usen su propia cookie de sesión
     * (independiente del panel admin).
     */
    public function handle(Request $request, Closure $next)
    {
        // cookie exclusiva para clientes
        config([
            'session.cookie' => 'p360_client_session',
            // 'session.path'   => '/cliente', // opcional
        ]);

        // Y aquí el guard por defecto debe ser "web"
        config([
            'auth.defaults.guard' => 'web',
            'auth.defaults.passwords' => 'clientes',
        ]);

        return $next($request);
    }
}
