<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BloqueoPorImpago
{
    public function handle(Request $request, Closure $next)
    {
        // Recupera por sesión del cliente actual (ajusta a tu auth)
        $email = $request->user('cliente')?->email ?? null; // o como lo tengas
        if (!$email) return $next($request);

        $cli = DB::connection('mysql_clientes')->table('clientes')->where('email',$email)->first();
        if ($cli && $cli->estatus === 'bloqueado') {
            if (!$request->routeIs('cliente.estado_cuenta')) {
                return redirect()->route('cliente.estado_cuenta');
            }
        }
        return $next($request);
    }
}
