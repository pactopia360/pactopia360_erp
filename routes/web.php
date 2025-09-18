<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeployController;

/*
|--------------------------------------------------------------------------
| Web Routes (públicas)
|--------------------------------------------------------------------------
*/

// Página de bienvenida
Route::get('/', function () {
    return view('welcome');
})->name('home.public');

// Alias de login genérico → manda al login del panel admin
Route::get('/login', function () {
    return redirect()->route('admin.login');
})->name('login');

// Panel ADMIN (carga routes/admin.php con prefijo y nombres)
Route::prefix('admin')
    ->as('admin.')
    ->group(base_path('routes/admin.php'));

// Panel CLIENTE (carga routes/cliente.php con prefijo y nombres)
Route::prefix('cliente')
    ->as('cliente.')
    ->group(base_path('routes/cliente.php'));

// Hook de deploy (opcional; para CI/CD)
Route::get('/_deploy/finish/{signature}', [DeployController::class, 'finish'])
    ->where('signature', '[A-Za-z0-9._-]+')
    ->name('deploy.finish');

/*
|--------------------------------------------------------------------------
| Utilidades de entorno local (opcionales)
| Útiles cuando no existe el symlink de storage en Windows/WAMP.
|--------------------------------------------------------------------------
*/
if (app()->environment(['local', 'development'])) {
    Route::get('storage/{path}', function (string $path) {
        $full = storage_path('app/public/' . $path);
        abort_unless(is_file($full), 404);
        return response()->file($full);
    })->where('path', '.*')->name('storage.local');
}
