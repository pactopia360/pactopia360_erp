<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * Se ejecutan en toda petición HTTP.
     */
    protected $middleware = [
        // Restringe hosts permitidos (configurable en App\Http\Middleware\TrustHosts)
        \App\Http\Middleware\TrustHosts::class,

        // Respeta proxies (X-Forwarded-*) y fija IP real del cliente
        \App\Http\Middleware\TrustProxies::class,

        // CORS
        \Illuminate\Http\Middleware\HandleCors::class,

        // Modo mantenimiento / tamaño de POST / normalizaciones
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * Grupos de middleware.
     */
    protected $middlewareGroups = [
        'web' => [
            // Cookies (¡incluye excepción para p360_tmp_* en EncryptCookies!)
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,

            // Sesión
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,

            // Errores de validación a las vistas
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,

            // CSRF + route model binding
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * Alias de middleware para usar en rutas.
     * Sustituye al antiguo $routeMiddleware.
     */
    protected $middlewareAliases = [
        // Núcleo de auth / seguridad
        'auth'             => \App\Http\Middleware\Authenticate::class,
        'auth.basic'       => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session'     => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'guest'            => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'can'              => \Illuminate\Auth\Middleware\Authorize::class,
        'throttle'         => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'signed'           => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'verified'         => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'cache.headers'    => \Illuminate\Http\Middleware\SetCacheHeaders::class,

        // --- Custom existentes
        'auth.any'         => \App\Http\Middleware\AuthenticateAny::class,
        'account.active'   => \App\Http\Middleware\EnsureAccountIsActive::class,

        // --- Clientes
        'client.account.active' => \App\Http\Middleware\EnsureClientAccountIsActive::class,

        // --- Conveniencia (mismo Authenticate; el guard lo decide la ruta/config)
        'auth.admin'       => \App\Http\Middleware\Authenticate::class,
        'auth.web'         => \App\Http\Middleware\Authenticate::class,
    ];
}
