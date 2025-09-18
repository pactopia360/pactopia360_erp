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
            // Web "general"
            if (file_exists(base_path('routes/web.php'))) {
                Route::middleware('web')
                    ->group(base_path('routes/web.php'));
            }

            // ===== ADMIN: prefijo /admin y nombres admin.* =====
            if (file_exists(base_path('routes/admin.php'))) {
                Route::middleware(['web'])
                    ->prefix('admin')
                    ->as('admin.')
                    ->group(base_path('routes/admin.php'));
            }

            // ===== CLIENTE (opcional): /cliente y nombres cliente.* =====
            if (file_exists(base_path('routes/cliente.php'))) {
                Route::middleware(['web'])
                    ->prefix('cliente')
                    ->as('cliente.')
                    ->group(base_path('routes/cliente.php'));
            }

            // API (si la usas)
            if (file_exists(base_path('routes/api.php'))) {
                Route::prefix('api')
                    ->middleware('api')
                    ->group(base_path('routes/api.php'));
            }
        });
    }
}
