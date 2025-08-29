<?php

use Illuminate\Support\Facades\File;

/**
 * Helpers específicos del panel Cliente.
 */
if (! function_exists('cliente_assetv')) {
    /**
     * Retorna la URL de un asset del panel Cliente con versión por timestamp.
     * Ej: cliente_assetv('assets/cliente/js/dashboard.js')
     */
    function cliente_assetv(string $path): string
    {
        $fullPath = public_path($path);
        $version  = File::exists($fullPath) ? File::lastModified($fullPath) : time();
        return asset($path) . '?v=' . $version;
    }
}
