<?php

namespace App\Support;

use Illuminate\Support\Str;

final class P360Ids
{
    /**
     * Genera un código de cuenta legible y único.
     * Formato: P360-<RFCPADRE>-<ULID8>
     *   - ULID corto = primeros 8 chars de Str::ulid()
     */
    public static function cuenta(string $rfcPadre): string
    {
        $rfc = strtoupper(preg_replace('/\s+/', '', $rfcPadre));
        $ul8 = substr((string) Str::ulid(), 0, 8);
        return "P360-{$rfc}-{$ul8}";
    }

    /**
     * Genera un código de usuario único y estable.
     * Formato: U-<RFC-or-ACC>-<YYYYMMDD>-<BASE36>
     */
    public static function usuario(?string $rfc, string $codigoCuenta): string
    {
        $base = $rfc ? strtoupper($rfc) : strtoupper($codigoCuenta);
        $fecha = now()->format('Ymd');
        $rand  = strtoupper(base_convert(bin2hex(random_bytes(5)), 16, 36)); // 10 chars aprox
        return "U-{$base}-{$fecha}-{$rand}";
    }
}
