<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\AdminSessionConfig;
use App\Http\Middleware\ClientSessionConfig;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Aquí puedes vincular interfaces a implementaciones si lo necesitas.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Aliases de middleware (compatibilidad con rutas que aún usen strings)
        // Nota: aliasMiddleware es idempotente; si existe, se re-mapea sin romper.
        Route::aliasMiddleware('account.active', EnsureAccountIsActive::class);

        // Puentes de compatibilidad para eliminar "Target class [session.cliente] does not exist"
        Route::aliasMiddleware('session.cliente', ClientSessionConfig::class);
        Route::aliasMiddleware('session.admin',   AdminSessionConfig::class);

        // Default para índices largos en MySQL/MariaDB
        Schema::defaultStringLength(191);
    }
}
