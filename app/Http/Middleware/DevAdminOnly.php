<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DevAdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        // Solo local / development / testing
        if (!app()->environment(['local','development','testing'])) {
            abort(403, 'Solo disponible en entornos de desarrollo.');
        }

        // Debe estar logueado en guard admin
        if (!auth('admin')->check()) {
            return redirect()->route('admin.login');
        }

        // Superadmins por env o config:
        // APP_SUPERADMINS="correo1@dominio.com,correo2@dominio.com"
        $raw = config('app.superadmins') ?? env('APP_SUPERADMINS', '');
        $list = is_array($raw)
            ? array_map('strtolower', array_map('trim', $raw))
            : array_map('strtolower', array_map('trim', explode(',', (string) $raw)));

        $email = strtolower((string) auth('admin')->user()?->email);

        if (!$email || !in_array($email, $list, true)) {
            abort(403, 'Requiere superadmin.');
        }

        return $next($request);
    }
}
