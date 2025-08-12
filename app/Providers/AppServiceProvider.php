<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Evita "Specified key was too long" en MySQL/MariaDB con utf8mb4
        Schema::defaultStringLength(191);
    }
}
