<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ClientePrep
{
    public function handle(Request $request, Closure $next)
    {
        // lo que hacías en el closure
        // ej: view()->share('algo','valor');
        return $next($request);
    }
}
