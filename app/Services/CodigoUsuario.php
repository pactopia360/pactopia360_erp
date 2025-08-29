<?php
// app/Services/CodigoUsuario.php

namespace App\Services;

use Illuminate\Support\Str;

class CodigoUsuario
{
    /** Código legible, estable y corto: PREFIJO-XXXX-9999 */
    public static function generar(string $prefijo = 'USR', int $len = 6): string
    {
        $rand = strtoupper(Str::random($len));
        $num  = random_int(1000, 9999);
        return sprintf('%s-%s-%d', trim($prefijo), $rand, $num);
    }
}
