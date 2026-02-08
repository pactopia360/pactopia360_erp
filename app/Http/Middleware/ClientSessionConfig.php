<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Cliente\UsuarioCuenta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

final class ClientSessionConfig
{
    public function handle(Request $request, Closure $next)
    {
        // =========================================================
        // ✅ BYPASS ADMIN (CRÍTICO)
        // Este middleware SOLO aplica al portal CLIENTE.
        // =========================================================
        try {
            $routeName = optional($request->route())->getName();

            if ($request->is('admin', 'admin/*')) {
                return $next($request);
            }

            if (is_string($routeName) && $routeName !== '' && str_starts_with($routeName, 'admin.')) {
                return $next($request);
            }

            if (Auth::guard('admin')->check()) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            return $next($request);
        }

        // =========================================================
        // 1) Sesión separada Cliente (DEBE correr ANTES de StartSession)
        // =========================================================
        Config::set('session.cookie', 'p360_client_session');

        // =========================================================
        // 2) BLINDAJE AUTH: guard web + provider UsuarioCuenta
        // =========================================================
        Config::set('auth.defaults.guard', 'web');
        Config::set('auth.defaults.passwords', 'clientes');
        Config::set('auth.providers.users.driver', 'eloquent');
        Config::set('auth.providers.users.model', UsuarioCuenta::class);

        try { Auth::shouldUse('web'); } catch (\Throwable $e) {}

        // ⚠️ IMPORTANTE:
        // Aquí NO tocamos session()->put() ni hidratar módulos,
        // porque todavía NO existe el session store (StartSession aún no corrió).
        // La hidratación de módulos se hace en ClientModulesHydrator (after StartSession).

        return $next($request);
    }
}
