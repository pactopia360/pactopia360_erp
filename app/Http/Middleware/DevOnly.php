<?php

namespace App\Http\Middleware;

use Closure;

class DevOnly
{
    public function handle($request, Closure $next)
    {
        $ok = false;
        $emails = array_map('trim', explode(',', env('DEV_ALLOWED_EMAILS','')));
        $user = auth()->user();

        if ($user && in_array(strtolower($user->email), array_map('strtolower',$emails), true)) $ok = true;
        if (!$ok && $request->header('X-Dev-Key') && hash_equals($request->header('X-Dev-Key'), env('DEV_MASTER_KEY'))) $ok = true;

        abort_unless($ok, 403, 'Dev zone');
        return $next($request);
    }
}
