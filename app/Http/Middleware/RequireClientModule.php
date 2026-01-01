<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class RequireClientModule
{
    public function handle(Request $request, Closure $next, string $moduleKey)
    {
        $mods = session('p360.modules', []);
        if (!is_array($mods)) $mods = [];

        $st = $mods[$moduleKey] ?? null;
        $visible = is_array($st) ? (bool)($st['visible'] ?? false) : false;
        $enabled = is_array($st) ? (bool)($st['enabled'] ?? false) : false;

        if (!$visible || !$enabled) {
            // Puedes cambiar a redirect a “Mi cuenta” o a una pantalla “Módulo no disponible”
            abort(403, 'Módulo no disponible para esta cuenta.');
        }

        return $next($request);
    }
}
