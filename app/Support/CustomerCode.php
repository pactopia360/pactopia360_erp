<?php

namespace App\Support;

use Illuminate\Support\Str;

class CustomerCode
{
    /**
     * Genera un código único y ordenable:
     *   RFC-<ULID>  (p.ej. ACM010101ABC-01J5RZ6H9N4D3K2W8M7XQZB5)
     * Garantiza unicidad por base + unique index en DB.
     */
    public static function make(string $rfc): string
    {
        $rfc = strtoupper(trim($rfc));
        // ULID ya incluye marca de tiempo, muy útil para seguimiento
        return $rfc . '-' . Str::ulid()->toBase32();
    }
}
