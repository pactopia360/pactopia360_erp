<?php

namespace App\Http\Middleware\Cliente;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BloqueoPorDeuda
{
    public function handle(Request $request, Closure $next)
    {
        $uid = $request->user()?->cuenta_id;
        if (!$uid) return $next($request);

        $cli = DB::connection('mysql_clientes')->table('clientes')->where('id',$uid)->first();
        if ($cli && $cli->bloqueado) {
            if (!$request->is('estado-cuenta*','pago*')) {
                return redirect()->to('/estado-cuenta')->with('error','Tu cuenta está bloqueada por falta de pago.');
            }
        }
        return $next($request);
    }
}
