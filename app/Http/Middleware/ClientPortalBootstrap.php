<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Cliente\UsuarioCuenta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

final class ClientPortalBootstrap
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ BYPASS ADMIN (NO tocar cookie/guard si estamos en admin)
        try {
            $routeName = optional($request->route())->getName();

            if ($request->is('admin', 'admin/*')) return $next($request);
            if (is_string($routeName) && $routeName !== '' && str_starts_with($routeName, 'admin.')) return $next($request);
            if (Auth::guard('admin')->check()) return $next($request);
        } catch (\Throwable $e) {
            return $next($request);
        }

        // ✅ Cookie aislada del portal cliente (CRÍTICO: antes de StartSession)
        Config::set('session.cookie', 'p360_client_session');

        // ✅ Guard/provider del portal cliente
        Config::set('auth.defaults.guard', 'web');
        Config::set('auth.defaults.passwords', 'clientes');
        Config::set('auth.providers.users.driver', 'eloquent');
        Config::set('auth.providers.users.model', UsuarioCuenta::class);

        try { Auth::shouldUse('web'); } catch (\Throwable $e) {}

        return $next($request);
    }
}
