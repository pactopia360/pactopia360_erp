<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente\UsuarioCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ImpersonateController extends Controller
{
    /**
     * Consume un token (1 solo uso) y hace login del OWNER
     * bajo el contexto CLIENTE (cookie/path del grupo 'cliente').
     */
    public function consume(Request $request, string $token): RedirectResponse
    {
        $token = trim((string) $token);
        if ($token === '') {
            return redirect()->route('cliente.login')->withErrors([
                'login' => 'Impersonación inválida.',
            ]);
        }

        // ✅ 1-uso atómico: pull = get + delete
        $key  = "impersonate.token.$token";
        $data = Cache::pull($key);

        if (!is_array($data) || empty($data['owner_id'])) {
            return redirect()->route('cliente.login')->withErrors([
                'login' => 'Impersonación inválida o expirada.',
            ]);
        }

        $ownerId = (string) $data['owner_id'];

        $owner = UsuarioCuenta::on('mysql_clientes')->find($ownerId);
        if (!$owner || !(int) $owner->activo) {
            return redirect()->route('cliente.login')->withErrors([
                'login' => 'Usuario owner no disponible.',
            ]);
        }

        // Limpia cualquier sesión cliente previa
        try {
            Auth::guard('web')->logout();
        } catch (\Throwable $e) {
            // ignore
        }

        // ✅ Endurecer contra session fixation
        try {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Throwable $e) {
            // ignore (por si la sesión aún no está inicializada en algún edge)
        }

        // ✅ Login bajo cookie cliente (porque ESTA request está en grupo 'cliente')
        Auth::guard('web')->login($owner, false);

        // ✅ Regenera después del login también (doble capa)
        try {
            $request->session()->regenerate();
            $request->session()->regenerateToken();
        } catch (\Throwable $e) {
            // ignore
        }

        // Flags de auditoría en sesión CLIENTE
        $request->session()->put([
            'impersonated'          => true,
            'impersonated_by_admin' => (string) ($data['admin_id'] ?? ''),
            'impersonated_rfc'      => (string) (($data['rfc'] ?? '') ?: ($data['account_id'] ?? '')),
        ]);

        return redirect()->route('cliente.home');
    }

    /**
     * Cierra sesión cliente (impersonación) y regresa al listado admin.
     * (No intenta restaurar sesión admin, porque son cookies separadas por path).
     */
    public function stop(Request $request): RedirectResponse
    {
        try {
            Auth::guard('web')->logout();
        } catch (\Throwable $e) {
            // ignore
        }

        // Limpia flags de impersonación
        try {
            $request->session()->forget([
                'impersonated',
                'impersonated_by_admin',
                'impersonated_rfc',
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        // ✅ Seguridad: invalidar sesión cliente
        try {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Throwable $e) {
            // ignore
        }

        // Admin usa otra cookie (/admin). Al redirigir, el browser enviará la cookie admin.
        return redirect()->route('admin.clientes.index')->with('ok', 'Sesión de cliente finalizada.');
    }
}
