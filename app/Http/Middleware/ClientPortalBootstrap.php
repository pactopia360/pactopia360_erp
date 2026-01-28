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
        // ✅ Debe correr ANTES de StartSession
        Config::set('session.cookie', 'p360_client_session');

        // ✅ Blindaje auth en contexto cliente
        Config::set('auth.defaults.guard', 'web');
        Config::set('auth.defaults.passwords', 'clientes');

        Config::set('auth.providers.users.driver', 'eloquent');
        Config::set('auth.providers.users.model', UsuarioCuenta::class);

        try { Auth::shouldUse('web'); } catch (\Throwable) {}

        return $next($request);
    }
}
