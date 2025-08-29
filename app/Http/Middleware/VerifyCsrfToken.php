<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * URIs excluidas de verificación CSRF.
     * Importante: rutas relativas (sin slash inicial).
     */
    protected $except = [
        'admin/ui/log',
        'admin/ui/log/*',
        'admin/ui/bot',
        'admin/ui/bot-*',
    ];

}
