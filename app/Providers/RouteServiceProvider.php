<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Ruta por defecto post-login / cuando ya hay sesión.
     */
    public const HOME = '/admin/home';

    public function boot(): void
    {
        $this->routes(function () {

            /**
             * =========================
             * WEB "GENERAL"
             * =========================
             */
            if (file_exists(base_path('routes/web.php'))) {
                Route::middleware('web')
                    ->group(base_path('routes/web.php'));
            }

            /**
             * =========================
             * ADMIN
             * - Usa grupo "admin" (NO "web") para que AdminSessionConfig corra antes de StartSession
             * - Prefix /admin y nombres admin.*
             * =========================
             */
            if (file_exists(base_path('routes/admin.php'))) {
                Route::middleware('admin')
                    ->prefix('admin')
                    ->as('admin.')
                    ->group(base_path('routes/admin.php'));
            }

            /**
             * =========================
             * CLIENTE
             * - Usa grupo "cliente" (NO "web") para que ClientSessionConfig corra antes de StartSession
             * - Prefix /cliente y nombres cliente.*
             * =========================
             */
            if (file_exists(base_path('routes/cliente.php'))) {
                Route::middleware('cliente')
                    ->prefix('cliente')
                    ->as('cliente.')
                    ->group(base_path('routes/cliente.php'));
            }

            /**
             * =========================
             * API
             * =========================
             */
            if (file_exists(base_path('routes/api.php'))) {
                Route::prefix('api')
                    ->middleware('api')
                    ->group(base_path('routes/api.php'));
            }
        });
    }
}
