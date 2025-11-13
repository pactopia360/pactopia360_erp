<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * Se ejecutan en toda petición HTTP.
     */
    protected $middleware = [
        \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
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
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,

            // StartSession corre después; la prioridad la forzamos abajo.
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,

            \Illuminate\View\Middleware\ShareErrorsFromSession::class,

            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * Alias de middleware (Laravel 10/11+).
     */
    protected $middlewareAliases = [
        // Core auth / seguridad
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

        // Custom existentes
        'auth.any'         => \App\Http\Middleware\AuthenticateAny::class,
        'account.active'   => \App\Http\Middleware\EnsureAccountIsActive::class,

        // Conveniencia (mismo Authenticate; el guard lo decide la ruta/config)
        'auth.admin'       => \App\Http\Middleware\Authenticate::class,
        'auth.web'         => \App\Http\Middleware\Authenticate::class,

        // Aislamiento de sesión por sub-plataforma
        'session.admin'    => \App\Http\Middleware\AdminSessionConfig::class,
        'session.cliente'  => \App\Http\Middleware\ClientSessionConfig::class,

        'phone.verified'   => \App\Http\Middleware\EnsurePhoneVerified::class,
    ];

    /**
     * Compatibilidad con versiones que aún leen $routeMiddleware.
     * (Clonamos los aliases para evitar el error "Target class [session.cliente] does not exist")
     */
    protected $routeMiddleware = [
        // Core
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

        // Custom
        'auth.any'         => \App\Http\Middleware\AuthenticateAny::class,
        'account.active'   => \App\Http\Middleware\EnsureAccountIsActive::class,

        // Conveniencia
        'auth.admin'       => \App\Http\Middleware\Authenticate::class,
        'auth.web'         => \App\Http\Middleware\Authenticate::class,

        // Aislamiento
        'session.admin'    => \App\Http\Middleware\AdminSessionConfig::class,
        'session.cliente'  => \App\Http\Middleware\ClientSessionConfig::class,

        'phone.verified'   => \App\Http\Middleware\EnsurePhoneVerified::class,
    ];

    /**
     * Prioridad de ejecución de middleware.
     * — Garantizamos que los configuradores de sesión corran ANTES de StartSession.
     */
    protected $middlewarePriority = [
        // 1) Config de cookie/guard por contexto
        \App\Http\Middleware\AdminSessionConfig::class,
        \App\Http\Middleware\ClientSessionConfig::class,

        // 2) Lo que necesita sesión viva
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,

        // 3) Resto
        \App\Http\Middleware\Authenticate::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ];
}
