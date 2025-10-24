<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ClientAuth
{
    /**
     * Normaliza el password de entrada:
     * - Convierte NBSP/ZWSP y espacios raros a espacio regular
     * - Normaliza saltos de línea a \n y luego elimina CR/LF
     * - Trim
     */
    public static function normalizePassword(string $input): string
    {
        // Reemplazos de espacios invisibles / raros
        $map = [
            "\u{00A0}" => ' ', // NBSP
            "\u{2007}" => ' ', // Figure space
            "\u{202F}" => ' ', // Narrow NBSP
            "\u{200B}" => '',  // ZWSP
            "\u{200C}" => '',  // ZWNJ
            "\u{200D}" => '',  // ZWJ
            "\u{FEFF}" => '',  // BOM
        ];
        $s = strtr($input, $map);

        // Normaliza saltos de línea y elimina CR/LF
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = str_replace("\n", '', $s);

        // Colapsa espacios consecutivos al interior (opcional)
        // $s = preg_replace('/[ \t]{2,}/u', ' ', $s);

        return trim($s);
    }

    /**
     * Hashea con el driver por defecto de Laravel (respetando config/hashing.php)
     * Siempre normaliza primero.
     */
    public static function make(string $plain): string
    {
        $norm = self::normalizePassword($plain);
        // Usamos el driver configurado (bcrypt por defecto en tu proyecto)
        return Hash::make($norm);
    }

    /**
     * Verifica un password contra un hash o texto plano heredado.
     * - $2y$ -> password_verify (bcrypt nativo de PHP)
     * - $argon2* -> Hash::check (driver auto)
     * - otro (sin prefijo) -> comparación estricta (legado)
     * Siempre normaliza el input antes de verificar.
     */
    public static function check(string $plain, string $stored): bool
    {
        $stored = (string) $stored;
        if ($stored === '') return false;

        $norm = self::normalizePassword($plain);

        // bcrypt
        if (Str::startsWith($stored, '$2y$')) {
            return password_verify($norm, $stored);
        }

        // argon / argon2id
        if (Str::startsWith($stored, '$argon2')) {
            try {
                return Hash::check($norm, $stored);
            } catch (\Throwable $e) {
                // Fallback por si el driver activo no coincide
                return Hash::driver('argon')->check($norm, $stored);
            }
        }

        // Texto plano legado
        return hash_equals($stored, $norm);
    }
}
