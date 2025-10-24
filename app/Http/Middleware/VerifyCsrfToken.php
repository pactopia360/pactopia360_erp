<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;

class VerifyCsrfToken extends Middleware
{
    /**
     * URIs excluidas de verificación CSRF (siempre, todos los entornos).
     */
    protected $except = [
        'admin/ui/log',
        'admin/ui/log/*',
        'admin/ui/bot',
        'admin/ui/bot-*',
        'cliente/webhook/stripe',
        'cliente/_qa/set-temp-pass',
        'cliente/_qa/test-pass',
        'cliente/_qa/reset-pass',

    ];

    /**
     * Bypass total de CSRF para /cliente/* SOLO en entornos locales.
     * Esto garantiza que NO se dispare el 419 mientras terminas el flujo.
     * Cuando acabemos, comentas el if() para volver a la verificación normal.
     */
    public function handle($request, Closure $next)
    {
        if (app()->environment(['local', 'development', 'testing'])) {
            if ($request->is('cliente/*')) {
                return $next($request);
            }
        }
        return parent::handle($request, $next);
    }

    /**
     * Aun así respetamos la lista $except y, adicionalmente, si quisieras
     * meter rutas puntuales aquí, seguirá aplicando.
     */
    protected function inExceptArray($request)
    {
        if (parent::inExceptArray($request)) {
            return true;
        }

        // (Opcional extra) — si quisieras exceptuar rutas específicas:
        // if ($request->is('cliente/login')) return true;

        return false;
    }
}
