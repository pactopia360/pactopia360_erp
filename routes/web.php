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
});

// Alias de login genérico → manda al login del panel admin
Route::get('/login', function () {
    return redirect()->route('admin.login');
})->name('login');

// Rutas del panel administrativo (se cargan desde routes/admin.php)
Route::prefix('admin')
    ->name('admin.')
    ->group(base_path('routes/admin.php'));

// Hook de deploy (opcional; úsalo si tu proceso CI/CD lo invoca)
Route::get('/_deploy/finish/{signature}', [DeployController::class, 'finish'])
    ->where('signature', '[A-Za-z0-9._-]+')
    ->name('deploy.finish');

/*
|--------------------------------------------------------------------------
| Utilidades de entorno local (opcionales)
|--------------------------------------------------------------------------
| Útiles cuando no existe el symlink de storage en desarrollo Windows/WAMP.
| Puedes eliminarlas si ya usas "php artisan storage:link".
*/
if (app()->environment(['local', 'development'])) {
    Route::get('storage/{path}', function (string $path) {
        $full = storage_path('app/public/' . $path);
        abort_unless(is_file($full), 404);
        return response()->file($full);
    })->where('path', '.*')->name('storage.local');
}
