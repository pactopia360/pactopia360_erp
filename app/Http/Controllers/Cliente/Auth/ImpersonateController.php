<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente\UsuarioCuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImpersonateController extends Controller
{
    /**
     * Consume token 1-uso generado por Admin y crea sesión REAL de cliente.
     *
     * Ruta firmada: cliente.impersonate.consume
     * URL: /cliente/impersonate/consume/{token}?signature=...&expires=...
     */
    public function consume(Request $request, string $token)
    {
        // ✅ Sesión aislada del portal cliente (MISMA que LoginController)
        Config::set('session.cookie', 'p360_client_session');
        Auth::shouldUse('web');

        // ✅ Validación manual de firma (cache-safe)
        if (method_exists($request, 'hasValidSignature') && !$request->hasValidSignature()) {
            abort(403, 'Enlace inválido o expirado.');
        }

        $token = trim((string) $token);
        if ($token === '') abort(404, 'Token vacío.');

        // ✅ Token 1-uso
        $payload = Cache::pull("impersonate.token.$token");
        if (!is_array($payload)) {
            abort(403, 'Token no disponible o ya fue usado.');
        }

        $ownerId   = (string) ($payload['owner_id'] ?? '');
        $adminId   = (string) ($payload['admin_id'] ?? '');
        $rfc       = (string) ($payload['rfc'] ?? '');
        $accountId = (string) ($payload['account_id'] ?? '');

        if ($ownerId === '') abort(404, 'Owner inválido.');

        // Cargar usuario owner (mysql_clientes)
        $user = UsuarioCuenta::on('mysql_clientes')->find($ownerId);
        abort_if(!$user, 404, 'Usuario owner no encontrado.');

        // Si existe campo activo, respetarlo
        try {
            $active = $user->activo ?? 1;
            if ((int) $active !== 1) abort(403, 'Usuario no activo.');
        } catch (\Throwable) {}

        // ✅ LOGIN REAL
        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();

        // ✅ Flag para bypass de paywall y para UI
        $request->session()->put('impersonated_by_admin', $adminId !== '' ? $adminId : true);
        $request->session()->put('impersonated_rfc', Str::upper($rfc));
        $request->session()->put('impersonated_account_id', $accountId);

        // ✅ Opcional: guardar a dónde volver
        $request->session()->put('impersonate.return_to_admin', route('admin.clientes.index'));

        Log::info('[IMP] consume ok', [
            'owner_id'   => $ownerId,
            'admin_id'   => $adminId,
            'account_id' => $accountId,
            'rfc'        => $rfc,
            'ip'         => $request->ip(),
        ]);

        // ✅ Entra al portal cliente igual que login normal
        return redirect()->route('cliente.home');
    }

    /**
     * Terminar impersonación DESDE portal cliente (cookie correcta).
     * Ruta: cliente.impersonate.stop
     */
    public function stop(Request $request)
    {
        Config::set('session.cookie', 'p360_client_session');
        Auth::shouldUse('web');

        $request->session()->forget([
            'impersonated_by_admin',
            'impersonated_rfc',
            'impersonated_account_id',
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $return = (string) $request->session()->pull('impersonate.return_to_admin', '');
        if ($return !== '') {
            return redirect($return)->with('ok', 'Impersonación finalizada.');
        }

        return redirect()->route('cliente.login')->with('logged_out', true);
    }
}
