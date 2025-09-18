<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Base;

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

        return \Illuminate\Support\Facades\Route::has('admin.login')
            ? route('admin.login')
            : ( \Illuminate\Support\Facades\Route::has('login') ? route('login') : '/' );
    }
}
