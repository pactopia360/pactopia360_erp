<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica que la cuenta del usuario cliente (BD Clientes) esté ACTIVA.
 * - Si la cuenta no está 'activa', se permite solo flujos de verificación/pago.
 * - Para mayor control de pago/verificación, usa también EnsureAccountIsActive.
 */
class EnsureClientAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('web')->user();

        // Whitelist mínima para evitar loops (las rutas de verificación/pago ya están en EnsureAccountIsActive)
        $allowed = [
            'cliente.login', 'cliente.login.do',
            'cliente.logout',
            'cliente.verify.email.*',
            'cliente.verify.phone*',
            'cliente.password.*',
            'cliente.registro.*',
            'cliente.checkout.*',
            'cliente.webhook.stripe',
            'cliente.terminos',
        ];

        $name = optional($request->route())->getName();
        foreach ($allowed as $pat) {
            // Soporta comodines simples tipo 'cliente.verify.email.*'
            if ($pat === $name || (str_contains($pat, '*') && fnmatch($pat, (string) $name))) {
                return $next($request);
            }
        }

        if ($user) {
            try {
                $cuenta = $user->relationLoaded('cuenta') ? $user->cuenta : $user->cuenta()->first();

                if ($cuenta && isset($cuenta->estado_cuenta) && $cuenta->estado_cuenta !== 'activa') {
                    // No forzamos logout inmediato; mejor redirigir con mensaje amigable.
                    // Logout solo en estados críticos (opcional).
                    $estado = (string) $cuenta->estado_cuenta;

                    // Estados típicos: pendiente_verificacion, bloqueada_pago, pago_pendiente, suspendida
                    if (in_array($estado, ['bloqueada_pago', 'pago_pendiente', 'suspendida'], true)) {
                        return redirect()
                            ->route('cliente.billing.statement')
                            ->withErrors(['plan' => 'Tu cuenta no está activa ('.$estado.'). Realiza el pago para continuar.']);
                    }

                    // Si es verificación pendiente, empujar a flujo de verificación
                    if ($estado === 'pendiente_verificacion') {
                        return redirect()
                            ->route('cliente.verify.email.resend')
                            ->with('lock', 'verify_email');
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('EnsureClientAccountIsActive: error resolviendo cuenta', ['e' => $e->getMessage()]);
            }
        }

        return $next($request);
    }
}
