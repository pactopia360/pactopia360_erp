<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeployController;

// Página de bienvenida (pública)
Route::get('/', function () {
    return view('welcome');
});

// Alias login genérico → manda al login admin
Route::get('/login', function () {
    return redirect()->route('admin.login');
})->name('login');

// Rutas del panel administrativo
Route::prefix('admin')
    ->name('admin.')                 // ← prefijo de nombre AQUÍ
    ->group(base_path('routes/admin.php'));

Route::get('/_deploy/finish/{signature}', [DeployController::class, 'finish'])
    ->name('deploy.finish');
