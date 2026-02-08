<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente\UsuarioCuenta;
use App\Support\ClientSessionConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class ImpersonateController extends Controller
{
    /**
     * Consume token 1-uso generado por Admin\ClientesController@impersonate().
     * Ruta: cliente.impersonate.consume (signed)
     */
    public function consume(Request $request, string $token)
    {
        ClientSessionConfig::applyCookie();

        $token = trim((string) $token);
        if ($token === '') abort(404);

        $key  = "impersonate.token.$token";
        $pack = Cache::get($key);

        if (!is_array($pack)) {
            abort(403, 'Token inválido o expirado.');
        }

        // 1-uso
        Cache::forget($key);

        $ownerId   = (string) ($pack['owner_id'] ?? '');
        $adminId   = (string) ($pack['admin_id'] ?? '');
        $rfc       = (string) ($pack['rfc'] ?? '');
        $accountId = (int)    ($pack['account_id'] ?? 0);

        if ($ownerId === '' || $accountId <= 0) {
            abort(403, 'Token incompleto.');
        }

        $owner = UsuarioCuenta::on('mysql_clientes')->find($ownerId);
        abort_if(!$owner, 404, 'Usuario no encontrado.');
        abort_if(!(int) ($owner->activo ?? 0), 403, 'Usuario inactivo.');

        // ✅ CLAVE: borrar llaves de cuenta/módulos ANTES de loguear
        ClientSessionConfig::hardReset($request);

        try {
            Auth::guard('web')->logout();
        } catch (\Throwable $e) {}

        Auth::guard('web')->login($owner, false);

        // Seguridad: nuevo ID de sesión
        $request->session()->regenerate();

        // ✅ CLAVE: setear account_id correcto en todas las llaves (p360 + legacy)
        ClientSessionConfig::setAccountId($request, $accountId);

        // Marca impersonación
        $request->session()->put('impersonated_by_admin', [
            'admin_id'   => $adminId,
            'account_id' => $accountId,
            'rfc'        => $rfc,
            'at'         => now()->toISOString(),
        ]);

        Log::info('cliente.impersonate.consume', [
            'owner_id'   => $ownerId,
            'account_id' => $accountId,
            'admin_id'   => $adminId,
            'rfc'        => $rfc,
            'ip'         => $request->ip(),
        ]);

        return redirect()->route('cliente.home');
    }

    /**
     * STOP canónico (POST) + compat (GET) — rutas ya existen en routes/cliente.php
     */
    public function stop(Request $request)
    {
        ClientSessionConfig::applyCookie();

        // Borra banderas de impersonación + cuenta/módulos
        try {
            ClientSessionConfig::hardReset($request);
        } catch (\Throwable $e) {}

        try {
            $request->session()->forget('impersonated_by_admin');
        } catch (\Throwable $e) {}

        try {
            Auth::guard('web')->logout();
        } catch (\Throwable $e) {}

        try {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Throwable $e) {}

        return redirect()
            ->route('cliente.login')
            ->with('info', 'Impersonación finalizada.');
    }
}
