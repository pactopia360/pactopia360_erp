<?php

namespace App\Services;

use Illuminate\Support\Str;

class UserCode
{
    // Ejemplo: P360-ACM-01JZ6W7E8A9BK3M8J4N5Q6R7S8 (prefijo+RFC3+ULID)
    public static function make(string $rfc): string
    {
        $r = strtoupper(preg_replace('/[^A-Z0-9]/i','', $rfc));
        $sig = substr($r, 0, 3) ?: 'USR';
        $ulid = (string) Str::ulid(); // 26 chars
        return "P360-{$sig}-{$ulid}";
    }
}
