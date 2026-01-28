<?php
// C:\wamp64\www\pactopia360_erp\routes\web.php
//
// WEB "GENERAL" (público / utilidades)
// ✅ NO montar aquí /cliente ni /admin core si quieres aislamiento total.
//    Esos se montan desde App\Providers\RouteServiceProvider con sus middleware groups:
//    - admin   -> rutas/admin.php
//    - cliente -> rutas/cliente.php
//
// Motivo:
// - ClientSessionConfig/AdminSessionConfig necesitan correr ANTES de StartSession,
//   por eso NO deben pasar por el grupo "web" primero.

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DeployController;
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
| PÚBLICO
|--------------------------------------------------------------------------
*/
Route::redirect('/', '/cliente')->name('home.public');

/**
 * Login “inteligente” que respeta el contexto cliente/admin
 * - Redirige a cliente.login si intended/referer/cookie cliente aplica
 * - Fallback a admin.login
 */
Route::get('/login', SmartLoginController::class)->name('login');

/*
|--------------------------------------------------------------------------
| ✅ IMPORTANTE: NO montar /cliente aquí
|--------------------------------------------------------------------------
| Antes se montaba /cliente (cliente.php y cliente_sat.php) dentro de web.php,
| lo cual hacía que las rutas tuviesen el stack:
|   web -> StartSession (cookie global) -> cliente (ClientSessionConfig ya tarde)
|
| Eso rompe el aislamiento de cookie (p360_client_session) y genera:
|   403 CUENTA NO SELECCIONADA
|
| Ahora /cliente se monta EXCLUSIVAMENTE desde RouteServiceProvider:
|   Route::middleware('cliente')->prefix('cliente')->as('cliente.')->group(...)
|
| ✅ No agregues require('routes/cliente*.php') aquí.
*/

/*
|--------------------------------------------------------------------------
| DEPLOY / INFRA
|--------------------------------------------------------------------------
| Endpoint de “finish” (si lo necesitas en prod)
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
