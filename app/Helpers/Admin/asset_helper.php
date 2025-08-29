<?php

use Illuminate\Support\Facades\File;

/**
 * Helpers específicos del panel Admin.
 */
if (! function_exists('admin_assetv')) {
    /**
     * Retorna la URL de un asset del panel Admin con versión por timestamp.
     * Ej: admin_assetv('assets/admin/js/home.js')
     */
    function admin_assetv(string $path): string
    {
        $fullPath = public_path($path);
        $version  = File::exists($fullPath) ? File::lastModified($fullPath) : time();
        return asset($path) . '?v=' . $version;
    }
}
