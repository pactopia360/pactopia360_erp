<?php
// C:\wamp64\www\pactopia360_erp\routes\web.php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DeployController;
use App\Http\Controllers\Admin\QaController;
use App\Http\Controllers\Auth\SmartLoginController;
use App\Http\Controllers\LocalStorageController;

/*
|--------------------------------------------------------------------------
| ENTORNO
|--------------------------------------------------------------------------
*/
$isLocal = app()->environment(['local', 'development', 'testing']);

/*
|--------------------------------------------------------------------------
| Público
|--------------------------------------------------------------------------
*/
Route::redirect('/', '/cliente')->name('home.public');

// Login “inteligente” que respeta el contexto cliente/admin
Route::get('/login', SmartLoginController::class)->name('login');

/*
|--------------------------------------------------------------------------
| Admin / Cliente (rutas separadas por archivo) + ✅ middleware groups
|--------------------------------------------------------------------------
| CRÍTICO:
| - admin.php debe correr bajo middleware group "admin"
| - cliente*.php debe correr bajo middleware group "cliente"
|
| FIX aplicado:
| - Unificamos el montaje de /cliente en un solo group para consistencia y cache-safety.
| - Movemos /admin/dev (QA) dentro del stack "admin" real.
*/

/**
 * ADMIN (SOT)
 */
Route::prefix('admin')
    ->as('admin.')
    ->middleware('admin') // ✅ IMPORTANTÍSIMO
    ->group(function () use ($isLocal) {

        // Monta routes/admin.php (core admin)
        require base_path('routes/admin.php');

        /*
        |----------------------------------------------------------------------
        | QA Admin/Dev (Local)
        |----------------------------------------------------------------------
        | ✅ Ahora SI queda bajo middleware('admin'):
        | - AdminSessionConfig / cookie / guard / sesión aislada correctamente.
        */
        if ($isLocal) {
            Route::prefix('dev')
                ->name('dev.')
                ->middleware(['auth:admin'])
                ->group(function () {

                    Route::get('/qa',            [QaController::class, 'index'])->name('qa');
                    Route::post('/resend-email', [QaController::class, 'resendEmail'])->name('resend_email');
                    Route::post('/send-otp',     [QaController::class, 'sendOtp'])->name('send_otp');
                    Route::post('/force-email',  [QaController::class, 'forceEmailVerified'])->name('force_email');
                    Route::post('/force-phone',  [QaController::class, 'forcePhoneVerified'])->name('force_phone');

                    Route::post('/clean-otps',   [QaController::class, 'cleanOtps'])
                        ->name('clean_otps')
                        ->middleware('throttle:30,1');
                });
        }
    });

/**
 * CLIENTE (SOT) — un solo montaje para /cliente
 * - cache-safe
 * - evita inconsistencias por tener 2 grupos iguales
 */
Route::prefix('cliente')
    ->as('cliente.')
    ->middleware('cliente') // ✅ IMPORTANTÍSIMO
    ->group(function () use ($isLocal) {

        // Portal cliente general (auth, perfil, mi-cuenta, billing, etc.)
        require base_path('routes/cliente.php');

        // SAT completo (descargas, bóveda, reportes, carrito, externo, etc.)
        require base_path('routes/cliente_sat.php');

        // QA cliente (local)
        if ($isLocal) {
            require base_path('routes/cliente_qa.php');
        }
    });

/*
|--------------------------------------------------------------------------
| Utilidades / Infra
|--------------------------------------------------------------------------
*/
Route::get('/_deploy/finish/{signature}', [DeployController::class, 'finish'])
    ->where('signature', '[A-Za-z0-9._-]+')
    ->middleware('throttle:12,1')
    ->name('deploy.finish');

/*
|--------------------------------------------------------------------------
| Local file serve (DEV ONLY)
|--------------------------------------------------------------------------
| IMPORTANTE:
| - Laravel 12 ya registra una ruta interna llamada "storage.local" (ServeFile)
|   para el disk "local" (normalmente storage/app/private).
| - Para NO colisionar, aquí exponemos SOLO storage/app/public con OTRO name.
| - En prod NO debe existir esto.
*/
if ($isLocal) {
    Route::get('storage-public/{path}', [LocalStorageController::class, 'show'])
        ->where('path', '.*')
        ->middleware('throttle:60,1')
        ->name('storage.public.local');
}
