<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthAny
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        foreach ($guards as $guard) {
            if (auth($guard)->check()) {
                auth()->shouldUse($guard); // fija el guard activo
                return $next($request);
            }
        }

        // Sin autenticación en ninguno:
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Redirige al login admin por defecto (ajusta si usas otro)
        return redirect()->guest(route('admin.login'));
    }
}
