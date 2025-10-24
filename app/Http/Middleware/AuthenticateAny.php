<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Base;
use Illuminate\Support\Facades\Route;

class AuthenticateAny extends Base
{
    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = array_keys(config('auth.guards', []));
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                $this->auth->shouldUse($guard);
                return;
            }
        }

        $this->unauthenticated($request, $guards);
    }

    protected function redirectTo($request)
    {
        if ($request->expectsJson()) return null;

        // Detecta área por ruta y por URL
        $routeName = optional($request->route())->getName();

        $isClienteArea =
            ($routeName && str_starts_with($routeName, 'cliente.')) ||
            $request->is('cliente') || $request->is('cliente/*');

        $isAdminArea =
            ($routeName && str_starts_with($routeName, 'admin.')) ||
            $request->is('admin') || $request->is('admin/*');

        // 1) Si es área cliente → login cliente
        if ($isClienteArea && Route::has('cliente.login')) {
            return route('cliente.login');
        }

        // 2) Si es área admin → login admin
        if ($isAdminArea && Route::has('admin.login')) {
            return route('admin.login');
        }

        // 3) Fallbacks: preferimos cliente si existe
        if (Route::has('cliente.login')) return route('cliente.login');
        if (Route::has('login'))         return route('login');
        return '/';
    }
}
