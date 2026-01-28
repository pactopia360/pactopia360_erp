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
        $key  = "impersonate.token.$token";
        $data = Cache::get($key);

        if (!is_array($data) || empty($data['owner_id'])) {
            return redirect()->route('cliente.login')->withErrors([
                'login' => 'Impersonación inválida o expirada.',
            ]);
        }

        // 1 solo uso
        Cache::forget($key);

        $ownerId = (string) $data['owner_id'];

        $owner = UsuarioCuenta::on('mysql_clientes')->find($ownerId);
        if (!$owner || !(int) $owner->activo) {
            return redirect()->route('cliente.login')->withErrors([
                'login' => 'Usuario owner no disponible.',
            ]);
        }

        // Limpia cualquier sesión cliente previa
        try { Auth::guard('web')->logout(); } catch (\Throwable $e) {}

        // ✅ Login bajo cookie cliente (porque ESTA request está en grupo 'cliente')
        Auth::guard('web')->login($owner, false);

        // ✅ Anti session fixation
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        // Flags de auditoría en sesión CLIENTE
        session([

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
        try { Auth::guard('web')->logout(); } catch (\Throwable $e) {}

        // Limpia flags de impersonación (sin nukear toda la sesión)
        $request->session()->forget([
            'impersonated_by_admin',
            'impersonated_rfc',
        ]);

        // Regenera para seguridad
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        // Importante: admin usa otra cookie (/admin). Al redirigir, el browser enviará la cookie admin.
        return redirect()->route('admin.clientes.index')->with('ok', 'Sesión de cliente finalizada.');
    }

}
