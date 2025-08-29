<?php

namespace App\Support;

final class UserCode
{
    /**
     * Genera un código único legible para el cliente.
     * Estructura:  RFC6-AAAAXXXX-BASE36TS  (≈ 22-26 chars)
     *  - RFC6:     primeros 6 del RFC (limpio)
     *  - AAAAXXXX: 8 chars de hash (rfc + email + microtime) en base32
     *  - BASE36TS: marca de tiempo en base36 para orden temporal
     */
    public static function make(string $rfc, string $email = ''): string
    {
        $r = strtoupper(preg_replace('/[^A-Z0-9]/i','', $rfc));
        $r = substr($r, 0, 6) ?: 'RFC000';
        $hash = strtoupper(substr(self::base32(hash('sha256', $r.$email.microtime(true), true)), 0, 8));
        $ts = strtoupper(base_convert((string)floor(microtime(true)*1000), 10, 36));
        return "{$r}-{$hash}-{$ts}";
    }

    private static function base32(string $bin): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }
}
