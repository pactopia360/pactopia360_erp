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

        /**
         * WEB genérico (si lo sigues usando en otros lados).
         * OJO: aquí no metemos session.admin/session.cliente para no cruzar contextos.
         */
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,

            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,

            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        /**
         * ✅ CLIENTE: bootstrap antes de StartSession + hydrate después de StartSession
         */
        'cliente' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,

            // ✅ ANTES de StartSession (cookie + auth provider/guard)
            \App\Http\Middleware\ClientPortalBootstrap::class,

            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,

            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,

            // ✅ DESPUÉS de StartSession (ya existe session store)
            \App\Http\Middleware\ClientSessionHydrate::class,

            // Tus middlewares de módulos (se quedan igual)
            \App\Http\Middleware\Cliente\InjectModulesState::class,
            \App\Http\Middleware\ShareClientModules::class,
        ],



        /**
         * ✅ ADMIN: config de sesión ANTES de StartSession
         */
        'admin' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,

            // ✅ aquí va ANTES
            \App\Http\Middleware\AdminSessionConfig::class,

            \Illuminate\Session\Middleware\StartSession::class,
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
     * Alias de middleware (Laravel 10/11/12).
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

        // Custom
        'auth.any'         => \App\Http\Middleware\AuthenticateAny::class,
        'account.active'   => \App\Http\Middleware\EnsureAccountIsActive::class,
        'vault.active'     => \App\Http\Middleware\EnsureVaultIsActive::class,

        // Conveniencia
        'auth.admin'       => \App\Http\Middleware\Authenticate::class,
        'auth.web'         => \App\Http\Middleware\Authenticate::class,

        // Aislamiento de sesión por sub-plataforma (alias, por si los sigues usando)
        'session.admin'    => \App\Http\Middleware\AdminSessionConfig::class,
        'session.cliente'  => \App\Http\Middleware\ClientPortalBootstrap::class,

        'phone.verified'   => \App\Http\Middleware\EnsurePhoneVerified::class,
    ];

    /**
     * Compatibilidad con versiones/paquetes que aún leen $routeMiddleware.
     */
    protected $routeMiddleware = [
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

        'auth.any'         => \App\Http\Middleware\AuthenticateAny::class,
        'account.active'   => \App\Http\Middleware\EnsureAccountIsActive::class,
        'vault.active'     => \App\Http\Middleware\EnsureVaultIsActive::class,

        'auth.admin'       => \App\Http\Middleware\Authenticate::class,
        'auth.web'         => \App\Http\Middleware\Authenticate::class,

        'session.admin'    => \App\Http\Middleware\AdminSessionConfig::class,
        'session.cliente'  => \App\Http\Middleware\ClientPortalBootstrap::class,

        'phone.verified'   => \App\Http\Middleware\EnsurePhoneVerified::class,
    ];

    /**
     * Prioridad (ok dejarla, pero lo importante ya quedó en grupos).
     */
    protected $middlewarePriority = [
        \App\Http\Middleware\AdminSessionConfig::class,
        \App\Http\Middleware\ClientPortalBootstrap::class,

        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,

        \App\Http\Middleware\ClientSessionHydrate::class,

        \App\Http\Middleware\Authenticate::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ];

}
