<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NormalizeSlashes
{
    public function handle(Request $request, Closure $next): Response
    {
        // Solo normaliza el PATH (no tocar querystring)
        $uri = $request->getRequestUri(); // incluye query
        $qPos = strpos($uri, '?');
        $path = ($qPos === false) ? $uri : substr($uri, 0, $qPos);
        $qs   = ($qPos === false) ? ''   : substr($uri, $qPos);

        // Si hay múltiples slashes consecutivos en el path, redirige a una versión normalizada
        if (strpos($path, '//') !== false) {
            $normalized = preg_replace('#/+#', '/', $path) . $qs;

            // Evita loop si por algún motivo queda igual
            if ($normalized !== $uri) {
                return redirect($normalized, 301);
            }
        }

        return $next($request);
    }
}
