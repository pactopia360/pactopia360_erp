<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureClientModuleEnabled
{
    /**
     * Regla:
     * - Fuente de verdad en cliente es la sesión:
     *   p360.modules_state[key]   = active|inactive|hidden|blocked
     *   p360.modules_access[key]  = bool (true solo si active)
     *   p360.modules_visible[key] = bool (false si hidden)
     *
     * - Si la sesión no trae bundle (vacía), NO bloqueamos
     *   para evitar falsos 403 (primer request / edge-cases).
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $key = strtolower(trim($moduleKey));
        if ($key === '') {
            return $next($request);
        }

        $state   = (array) session('p360.modules_state', []);
        $access  = (array) session('p360.modules_access', []);
        $visible = (array) session('p360.modules_visible', []);

        // Si no hay bundle, no bloquear (evita falsos 403).
        if (empty($state) && empty($access) && empty($visible)) {
            return $next($request);
        }

        $st = $this->normState($state[$key] ?? 'active');

        // hidden => no debe existir para el usuario (si llega por URL, bloqueamos)
        if ($st === 'hidden') {
            return $this->deny($request, $key, $st, 'Módulo no disponible.');
        }

        // inactive / blocked => visible pero no accesible
        if ($st === 'inactive' || $st === 'blocked') {
            return $this->deny($request, $key, $st, 'Módulo deshabilitado.');
        }

        // active => OK (state manda)
        return $next($request);
    }

    private function deny(Request $request, string $key, string $state, string $msg): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => false,
                'message' => $msg,
                'module'  => $key,
                'state'   => $state,
            ], 403);
        }

        return redirect()
            ->route('cliente.home')
            ->with('warn', $msg);
    }

    private function normState(mixed $s): string
    {
        $s = strtolower(trim((string) $s));
        return in_array($s, ['active', 'inactive', 'hidden', 'blocked'], true) ? $s : 'active';
    }
}
