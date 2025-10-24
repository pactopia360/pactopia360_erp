<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Deja las temporales de QA legibles por el front admin:
        'p360_tmp_pass_*',
        'p360_tmp_user_*',
    ];
}
