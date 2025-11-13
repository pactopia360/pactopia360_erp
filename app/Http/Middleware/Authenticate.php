<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Admin primero
        if ($request->is('admin') || $request->is('admin/*')) {
            if (Route::has('admin.login')) {
                return route('admin.login');
            }
        }

        // Cliente después
        if ($request->is('cliente') || $request->is('cliente/*')) {
            if (Route::has('cliente.login')) {
                return route('cliente.login');
            }
        }

        // Fallback por guard por defecto
        $defaultGuard = (string) config('auth.defaults.guard', 'cliente');

        if ($defaultGuard === 'admin' && Route::has('admin.login')) {
            return route('admin.login');
        }
        if (Route::has('cliente.login')) {
            return route('cliente.login');
        }

        return null;
    }
}
