<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use App\Http\Middleware\EnsureAccountIsActive;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Alias de middleware SIEMPRE disponible (funciona con Kernel clásico y bootstrap/app.php)
        Route::aliasMiddleware('account.active', EnsureAccountIsActive::class);
        Schema::defaultStringLength(191);
    }
}
