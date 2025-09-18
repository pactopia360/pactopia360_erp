<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * Middleware global de la aplicación.
     * Se ejecutan en toda petición HTTP.
     */
    protected $middleware = [
        // Restringe hosts permitidos (configurable en App\Http\Middleware\TrustHosts)
        \App\Http\Middleware\TrustHosts::class,

        // Respeta proxies (X-Forwarded-*) y fija IP real del cliente
        \App\Http\Middleware\TrustProxies::class,

        // CORS para peticiones cross-domain (config/cors.php)
        \Illuminate\Http\Middleware\HandleCors::class,

        // Modo mantenimiento (artisan down)
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,

        // Límite de tamaño de POST
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,

        // Recorta espacios en inputs
        \App\Http\Middleware\TrimStrings::class,

        // Convierte cadenas vacías a null
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * Grupos de middleware por tipo de rutas.
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class, // habilítalo si lo requieres
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * Aliases de middleware (Laravel 11/12).
     * Si tu proyecto aún usa $routeMiddleware, también lo dejamos por compat.
     */
    protected $middlewareAliases = [
        'auth'               => \App\Http\Middleware\Authenticate::class,
        'auth.basic'         => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session'       => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers'      => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can'                => \Illuminate\Auth\Middleware\Authorize::class,
        'guest'              => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm'   => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed'             => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle'           => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified'           => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'auth.any'           => \App\Http\Middleware\AuthenticateAny::class,

        // Personalizados
        'account.active'     => \App\Http\Middleware\EnsureAccountIsActive::class,
    ];

    // Compatibilidad con proyectos que aún referencian $routeMiddleware
    protected $routeMiddleware = [
        'auth'               => \App\Http\Middleware\Authenticate::class,
        'auth.basic'         => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session'       => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers'      => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can'                => \Illuminate\Auth\Middleware\Authorize::class,
        'guest'              => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm'   => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed'             => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle'           => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified'           => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'auth.any'           => \App\Http\Middleware\AuthenticateAny::class,
        'account.active'     => \App\Http\Middleware\EnsureAccountIsActive::class,
    ];
}
