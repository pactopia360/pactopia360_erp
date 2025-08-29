<?php

use Illuminate\Support\Facades\File;

/**
 * Helpers globales (compartidos). Mantiene compat con vistas que ya usan assetv().
 * En admin/cliente preferir admin_assetv() / cliente_assetv().
 */

if (! function_exists('assetv')) {
    /**
     * Retorna URL de asset con versión por timestamp (compat global).
     * Internamente delega a admin_assetv si existe, para mantener
     * compatibilidad con vistas actuales sin cambiarlas de inmediato.
     */
    function assetv(string $path): string
    {
        if (function_exists('admin_assetv')) {
            return admin_assetv($path);
        }

        $fullPath = public_path($path);
        $version  = File::exists($fullPath) ? File::lastModified($fullPath) : time();
        return asset($path) . '?v=' . $version;
    }
}
