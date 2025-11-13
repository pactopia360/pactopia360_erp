<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next)
    {
        // Siempre trabajamos con el guard del cliente
        $user = Auth::guard('web')->user();
        if (!$user) {
            return redirect()->route('cliente.login');
        }

        // Relación cuenta() debe existir en el modelo UsuarioCuenta
        $cuenta = $user->cuenta()->first();

        // Si el usuario no tiene rfc_padre todavía, lo dejamos pasar
        if (!$cuenta || empty($cuenta->rfc_padre)) {
            return $next($request);
        }

        // Buscamos la fila admin.accounts que corresponde a ESTE RFC
        $accAdm = DB::connection('mysql_admin')
            ->table('accounts')
            ->whereRaw('UPPER(rfc)=?', [strtoupper($cuenta->rfc_padre)])
            ->select('id', 'rfc', 'telefono', 'correo_contacto', 'phone_verified_at', 'email_verified_at')
            ->orderByDesc('id')
            ->first();

        // Si no existe en admin todavía, no bloqueamos
        if (!$accAdm) {
            return $next($request);
        }

        // Forzamos que la sesión de verificación apunte SIEMPRE a esta cuenta admin
        session([
            'verify.account_id' => $accAdm->id,
            'verify.email'      => strtolower((string) ($accAdm->correo_contacto ?? '')),
        ]);

        // Si no tiene teléfono verificado -> mandarlo al flujo OTP correcto
        if (empty($accAdm->phone_verified_at)) {
            return redirect()
                ->route('cliente.verify.phone')
                ->withErrors([
                    'general' => 'Necesitamos verificar tu teléfono antes de continuar.',
                ]);
        }

        // Ya verificado, continuar
        return $next($request);
    }
}
