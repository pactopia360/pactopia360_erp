<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\AdminSessionConfig;
use App\Http\Middleware\ClientSessionConfig;

use App\Services\Sat\Providers\SatWsProvider;
use App\Services\Sat\Providers\Provider2Stub;
use App\Services\Sat\Providers\SatProviderRegistry;
use App\Services\Sat\Providers\SatDownloadProviderInterface;
use App\Services\Sat\SatDownloadBalancer;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([SatWsProvider::class, Provider2Stub::class], 'sat.download.providers');

        $this->app->singleton(SatProviderRegistry::class, function ($app) {
            return new SatProviderRegistry($app->tagged('sat.download.providers'));
        });

        $this->app->singleton(SatDownloadBalancer::class, fn($app) => new SatDownloadBalancer($app->make(SatProviderRegistry::class)));
    }

    public function boot(): void
    {
        Route::aliasMiddleware('account.active', EnsureAccountIsActive::class);
        Route::aliasMiddleware('session.cliente', ClientSessionConfig::class);
        Route::aliasMiddleware('session.admin',   AdminSessionConfig::class);

        Schema::defaultStringLength(191);

        // ✅ Cargar migraciones separadas (admin / clientes)
        // (Esto NO migra solo; solo registra rutas)
        $this->loadMigrationsFrom([
            database_path('migrations_admin'),
            database_path('migrations_clientes'),
        ]);
    }
}
