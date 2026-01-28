<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class LocalStorageController
{
    /**
     * Sirve archivos desde storage/app/public SOLO para entorno local/dev/testing.
     *
     * Seguridad:
     * - Bloquea traversal (../) y separadores raros
     * - Normaliza a path relativo
     * - Permite solo extensiones “seguras” (ajustable)
     * - Exige que el archivo exista y sea archivo regular
     */
    public function show(Request $request, string $path): BinaryFileResponse
    {
        // Normaliza separadores y recorta
        $path = trim(str_replace('\\', '/', $path));

        // Bloquea null bytes y rutas vacías
        abort_if($path === '' || str_contains($path, "\0"), 404);

        // Bloquea path absoluto o esquemas
        abort_if(
            str_starts_with($path, '/') ||
            preg_match('~^[a-zA-Z]+://~', $path) === 1,
            404
        );

        // Bloquea traversal explícito
        abort_if(
            str_contains($path, '../') ||
            str_contains($path, '..\\') ||
            $path === '..' ||
            str_starts_with($path, '../') ||
            str_ends_with($path, '/..'),
            404
        );

        // Permitir únicamente caracteres razonables (evita inyecciones raras)
        // Ajusta si necesitas espacios u otros caracteres.
        abort_if(
            preg_match('~^[a-zA-Z0-9/_\.\-]+$~', $path) !== 1,
            404
        );

        // Allowlist de extensiones (ajusta según tu uso real)
        $allowed = [
            'txt','log','json','csv',
            'png','jpg','jpeg','gif','webp','svg',
            'pdf','xml','zip',
        ];

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_if($ext === '' || !in_array($ext, $allowed, true), 404);

        $base = storage_path('app/public');
        $full = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

        // Asegura que realmente cae dentro de storage/app/public
        $realBase = realpath($base);
        $realFull = realpath($full);

        abort_unless(is_string($realBase) && is_string($realFull), 404);
        abort_unless(str_starts_with($realFull, $realBase . DIRECTORY_SEPARATOR) || $realFull === $realBase, 404);
        abort_unless(is_file($realFull), 404);

        return response()->file($realFull, [
            // Cache corto (local), evita “stale” al estar probando.
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}
