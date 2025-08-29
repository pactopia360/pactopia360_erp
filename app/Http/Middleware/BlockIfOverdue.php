<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class BlockIfOverdue
{
    public function handle($request, Closure $next)
    {
        // Asume sesión con account_id (ajusta a tu auth)
        $accountId = session('account_id');
        if (!$accountId) return $next($request);

        $conn = 'mysql_admin';
        $blocked = DB::connection($conn)->table('accounts')->where('id',$accountId)->value('blocked');
        if ($blocked) {
            if (!$request->is('estado-cuenta*')) {
                return redirect()->to(route('estado.cuenta'))->with('warn','Tu cuenta está bloqueada por pago pendiente.');
            }
        }
        return $next($request);
    }
}
