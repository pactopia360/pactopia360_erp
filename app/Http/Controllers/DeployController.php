<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

class DeployController extends Controller
{
    public function finish(Request $request, string $signature)
    {
        $expected = config('app.deploy_secret');
        if (!$expected || !hash_equals($expected, $signature)) {
            abort(Response::HTTP_FORBIDDEN, 'Forbidden');
        }

        // Modo mantenimiento
        Artisan::call('down');

        // Migraciones (si usas 2 conexiones, descomenta los 2 bloques)
        try {
            Artisan::call('migrate', ['--force' => true]);

            // Ejemplo por conexiones separadas:
            // Artisan::call('migrate', ['--path' => 'database/migrations/admin', '--database' => 'mysql', '--force' => true]);
            // Artisan::call('migrate', ['--path' => 'database/migrations/clientes', '--database' => 'mysql', '--force' => true]);
        } catch (\Throwable $e) {
            // Log opcional
        }

        // Cacheo/optimizaciones
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        Artisan::call('event:cache');

        Artisan::call('up');

        return response()->json([
            'ok'    => true,
            'notes' => 'Deployed and optimized',
            'ts'    => now()->toDateTimeString(),
        ]);
    }
}
