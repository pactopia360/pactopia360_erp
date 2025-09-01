<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bindings/Singletons aquí si se requieren más adelante.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Evita "Specified key was too long" en MySQL/MariaDB con utf8mb4
        Schema::defaultStringLength(191);

        // Paginación con estilos de Bootstrap 5 (coincide con el admin)
        Paginator::useBootstrapFive();

        // En desarrollo ayuda a detectar N+1 y lazy loading accidental
        Model::preventLazyLoading(!app()->isProduction());

        // Forzar HTTPS y URL raíz en producción si APP_URL usa https
        if (app()->environment('production')) {
            $appUrl = (string) config('app.url');
            if ($appUrl !== '' && str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
                URL::forceRootUrl($appUrl);
            }
        }
    }
}
