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
        if ($token === '') {
            abort(404);
        }

        $key  = "impersonate.token.$token";
        $pack = Cache::get($key);

        if (!is_array($pack)) {
            Log::warning('cliente.impersonate.consume.token_missing', [
                'token' => $token,
                'ip'    => $request->ip(),
                'url'   => $request->fullUrl(),
            ]);

            abort(403, 'Token inválido o expirado.');
        }

        Cache::forget($key);

        $ownerId   = (string) ($pack['owner_id'] ?? '');
        $cuentaId  = (string) ($pack['cuenta_id'] ?? '');
        $adminId   = (string) ($pack['admin_id'] ?? '');
        $rfc       = (string) ($pack['rfc'] ?? '');
        $accountId = (int) ($pack['account_id'] ?? 0);

        if ($accountId <= 0) {
            Log::warning('cliente.impersonate.consume.invalid_pack', [
                'pack' => $pack,
                'ip'   => $request->ip(),
            ]);

            abort(403, 'Token incompleto.');
        }

        $ownerRow = null;

        if ($ownerId !== '') {
            $ownerRow = \DB::connection('mysql_clientes')
                ->table('usuarios_cuenta')
                ->where('id', $ownerId)
                ->first(['id', 'cuenta_id', 'activo', 'rol', 'email']);
        }

        if (!$ownerRow && $cuentaId !== '') {
            $ownerQuery = \DB::connection('mysql_clientes')
                ->table('usuarios_cuenta')
                ->where('cuenta_id', $cuentaId)
                ->where(function ($q) {
                    $q->where('rol', 'owner');

                    try {
                        if (\Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta', 'tipo')) {
                            $q->orWhere('tipo', 'owner');
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                })
                ->orderBy('created_at', 'asc');

            $ownerRow = $ownerQuery->first(['id', 'cuenta_id', 'activo', 'rol', 'email']);
        }

        if (!$ownerRow) {
            Log::error('cliente.impersonate.consume.owner_not_found', [
                'owner_id'   => $ownerId,
                'cuenta_id'  => $cuentaId,
                'account_id' => $accountId,
                'rfc'        => $rfc,
                'admin_id'   => $adminId,
                'ip'         => $request->ip(),
            ]);

            abort(403, 'No se pudo resolver el usuario owner para impersonación.');
        }

        if (!(int) ($ownerRow->activo ?? 0)) {
            Log::warning('cliente.impersonate.consume.owner_inactive', [
                'owner_id'   => (string) $ownerRow->id,
                'cuenta_id'  => (string) ($ownerRow->cuenta_id ?? ''),
                'account_id' => $accountId,
                'rfc'        => $rfc,
                'admin_id'   => $adminId,
                'ip'         => $request->ip(),
            ]);

            abort(403, 'Usuario inactivo.');
        }

        $owner = UsuarioCuenta::on('mysql_clientes')
            ->where('id', (string) $ownerRow->id)
            ->first();

        if (!$owner) {
            Log::error('cliente.impersonate.consume.owner_model_not_found', [
                'owner_id'   => (string) $ownerRow->id,
                'cuenta_id'  => (string) ($ownerRow->cuenta_id ?? ''),
                'account_id' => $accountId,
                'rfc'        => $rfc,
                'admin_id'   => $adminId,
                'ip'         => $request->ip(),
            ]);

            abort(403, 'No se pudo cargar el usuario owner.');
        }

        ClientSessionConfig::hardReset($request);

        try {
            Auth::guard('web')->logout();
        } catch (\Throwable $e) {
            // ignore
        }

        Auth::guard('web')->login($owner, false);

        $request->session()->regenerate();

        ClientSessionConfig::setAccountId($request, $accountId);

        $request->session()->put('impersonated_by_admin', [
            'admin_id'   => $adminId,
            'account_id' => $accountId,
            'cuenta_id'  => $cuentaId,
            'owner_id'   => (string) $owner->id,
            'rfc'        => $rfc,
            'at'         => now()->toISOString(),
        ]);

        Log::info('cliente.impersonate.consume', [
            'owner_id'   => (string) $owner->id,
            'cuenta_id'  => $cuentaId,
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
