<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next)
    {
        // Obtén account_id desde la sesión/autenticación del cliente:
        $accountId = $request->user()?->account_id ?? session('account_id');

        if (!$accountId) return $next($request);

        // Admin (autoridad) es la que decide el bloqueo
        $acc = DB::connection('mysql_admin')->table('accounts')->where('id', $accountId)->first();
        if (!$acc) return $next($request);

        // Suscripción y vencimientos
        $sub = DB::connection('mysql_admin')->table('subscriptions')->where('account_id',$accountId)->first();
        $blocked = (bool)($acc->is_blocked ?? false);

        // Regla de “día 1 factura” y “bloqueo día 5” (si monthly)
        if ($sub && ($sub->status === 'past_due' || $blocked)) {
            // Redirige siempre a estado de cuenta (ruta cliente/admin según contexto)
            return redirect()->route('billing.statement')->with('lock','past_due');
        }

        return $next($request);
    }
}
