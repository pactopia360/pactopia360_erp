<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminSessionConfig
{
    /**
     * Aisla sesión ADMIN:
     * - cookie diferente
     * - path limitado a /admin (evita que cliente y admin se pisen)
     * - fuerza defaults auth para rutas admin
     */
    public function handle(Request $request, Closure $next)
    {
        // Solo aplica a /admin (incluye /admin/login, /admin/usuarios, etc.)
        // Si por alguna razón este middleware se ejecuta fuera de /admin, no hace nada.
        if (!$request->is('admin') && !$request->is('admin/*')) {
            return $next($request);
        }

        // Cookie exclusiva para admin + path /admin
        config([
            'session.cookie' => 'p360_admin_session',
            'session.path'   => '/admin',
        ]);

        // Opcional: si usas subdominios en prod, define SESSION_DOMAIN en .env
        // y Laravel lo aplicará. Aquí no lo forzamos.

        // Fuerza guard default a admin en todo /admin
        config([
            'auth.defaults.guard'     => 'admin',
            'auth.defaults.passwords' => 'admins',
        ]);

        return $next($request);
    }
}
