<?php

use Illuminate\Support\Facades\Route;

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
