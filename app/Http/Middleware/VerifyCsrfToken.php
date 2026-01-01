<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * URIs excluidas de verificación CSRF (siempre, todos los entornos).
     *
     * Nota:
     * - Mantener estas rutas limitadas y justificadas.
     * - Los endpoints admin/* suelen estar protegidos por auth:admin, pero
     *   siguen siendo susceptibles a CSRF si se abren sin token; úsalo solo
     *   como FIX rápido mientras corriges sesión/cookies en producción.
     */
    protected $except = [
        // Admin UI logging/diagnostics
        'admin/ui/log',
        'admin/ui/log/*',
        'admin/ui/bot',
        'admin/ui/bot-*',

        // ✅ FIX rápido: 419 en producción en acciones puntuales de clientes
        'admin/clientes/*/force-phone',
        'admin/clientes/*/force-email',
        'admin/clientes/*/send-otp',
        'admin/clientes/*/resend-email',
        'admin/clientes/*/reset-password',
        'admin/clientes/*/email-credentials',
        'admin/clientes/*/save',

        // Cliente webhooks / QA
        'cliente/webhook/stripe',
        'cliente/_qa/set-temp-pass',
        'cliente/_qa/test-pass',
        'cliente/_qa/reset-pass',
    ];

    /**
     * Bypass total de CSRF para /cliente/* SOLO en entornos locales.
     * Esto evita 419 mientras terminas flujos locales.
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
     * Respetar la lista $except estándar.
     */
    protected function inExceptArray($request)
    {
        if (parent::inExceptArray($request)) {
            return true;
        }

        return false;
    }
}
