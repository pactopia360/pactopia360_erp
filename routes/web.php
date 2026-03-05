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
| ✅ Stack de middlewares a remover para endpoints públicos sin cookies/sesión
|--------------------------------------------------------------------------
| OJO: web.php normalmente corre con el grupo "web" (cookies + sesión).
| Para redirects/trackers públicos que NO deben tocar sesión, removemos esto.
*/
$noCookies = [
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \App\Http\Middleware\VerifyCsrfToken::class,
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
];

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
| ✅ COMPAT / ALIASES (NO /admin) — Billing
|--------------------------------------------------------------------------
| Estos redirects existen para links viejos o bookmarks que apuntan a rutas
| fuera del prefijo /admin, pero el módulo real vive en /admin/billing/*.
|
| Importante:
| - Los dejamos "neutros": sin cookies/sesión/CSRF
| - 301/302: usamos 302 (temporal) para no “cementar” en caches de navegador.
*/
Route::middleware('throttle:120,1')
    ->withoutMiddleware($noCookies)
    ->group(function () {

        // Canon: /admin/billing/accounts
        Route::match(['GET', 'HEAD'], '/billing/accounts', function () {
            return redirect()->route('admin.billing.accounts.index');
        })->name('compat.billing.accounts');

        // ✅ Compat detalle: /billing/accounts/{id} -> /admin/billing/accounts/{id}
        Route::match(['GET', 'HEAD'], '/billing/accounts/{id}', function (string $id) {
            return redirect()->route('admin.billing.accounts.show', ['id' => $id]);
        })->where('id', '[A-Za-z0-9\-]+')
          ->name('compat.billing.accounts.show');

        // Opcional: si alguien entra a /billing, mándalo al hub billing admin.
        Route::match(['GET', 'HEAD'], '/billing', function () {
            return redirect()->route('admin.billing.accounts.index');
        })->name('compat.billing.root');
    });

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