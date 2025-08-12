<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Ruta por defecto post-login / cuando ya hay sesión.
     */
    public const HOME = '/admin/home';

    public function boot(): void
    {
        //
    }
}
