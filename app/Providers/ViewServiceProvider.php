<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::composer('*', function ($view) {
            $view->with('notifCount', $view->notifCount ?? 0);
            $view->with('chatCount',  $view->chatCount  ?? 0);
            $view->with('cartCount',  $view->cartCount  ?? 0);
        });
    }
}
