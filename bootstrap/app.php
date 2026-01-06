<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        /**
         * Aliases de middleware (source of truth en Laravel 12).
         * Esto evita: Target class [vault.active] does not exist.
         */
        $middleware->alias([
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

            // Custom / Pactopia360
            'auth.any'         => \App\Http\Middleware\AuthenticateAny::class,
            'account.active'   => \App\Http\Middleware\EnsureAccountIsActive::class,
            'vault.active'     => \App\Http\Middleware\EnsureVaultIsActive::class,

            'session.admin'    => \App\Http\Middleware\AdminSessionConfig::class,
            'session.cliente'  => \App\Http\Middleware\ClientSessionConfig::class,
            'cliente.module'   => \App\Http\Middleware\EnsureClientModuleEnabled::class,

            // ✅ Hidrata módulos desde SOT admin para el cliente
            'cliente.hydrate_modules' => \App\Http\Middleware\Cliente\HydrateModulesState::class,

            'phone.verified'   => \App\Http\Middleware\EnsurePhoneVerified::class,

            // Conveniencia
            'auth.admin'       => \App\Http\Middleware\Authenticate::class,
            'auth.web'         => \App\Http\Middleware\Authenticate::class,
        ]);

        /**
         * ✅ DEFINIR GROUPS (CRÍTICO)
         * Tu RouteServiceProvider usa Route::middleware('admin') y ('cliente'),
         * así que estos grupos deben existir.
         *
         * OJO: aquí "componemos" sobre el grupo 'web' estándar, y añadimos tu session config.
         * La prioridad real de ejecución la controla $middleware->priority(...) más abajo.
         */
        $middleware->group('admin', [
            'web',
            'session.admin',
        ]);

        $middleware->group('cliente', [
            'web',
            'session.cliente',
        ]);

        /**
         * Prioridad
         * CRÍTICO:
         * - StartSession DEBE correr antes de cualquier middleware que use $request->session()
         * - Luego puedes correr tus "SessionConfig" para hidratar/ajustar datos en sesión
         * - Luego Auth
         */
        $middleware->priority([
            // ✅ 1) Sesión primero
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,

            // ✅ 2) Configuradores de sesión (ya con store disponible)
            \App\Http\Middleware\AdminSessionConfig::class,
            \App\Http\Middleware\ClientSessionConfig::class,

            // ✅ 3) Si hidratas módulos por sesión, también debe ir después de StartSession
            \App\Http\Middleware\Cliente\HydrateModulesState::class,

            // ✅ 4) Auth después de sesión
            \App\Http\Middleware\Authenticate::class,

            // Resto
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
