<?php
// app/Http/Middleware/CheckBillingStatus.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckBillingStatus {
    public function handle(Request $request, Closure $next) {
        $user = $request->user(); // account_users (cliente)
        if (!$user) return $next($request);

        $account = DB::connection('mysql_admin')->table('accounts')->find($user->account_id);
        if (!$account) return $next($request);

        // Si es premium con ciclo mensual: el día 1 envía estado de cuenta (tarea programada) y el 5 bloquea si no pagó
        $today = now()->startOfDay();
        $blocked = (bool)$account->is_blocked;

        if ($blocked) {
            return redirect()->route('cliente.estado_cuenta'); // pantalla de pago
        }
        return $next($request);
    }
}
