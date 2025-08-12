<?php

use Illuminate\Support\Facades\Route;

// Página de bienvenida (pública)
Route::get('/', function () {
    return view('welcome');
});

// Alias para el login genérico de Laravel → manda al login de admin
Route::get('/login', function () {
    return redirect()->route('admin.login');
})->name('login');

// Rutas del panel administrativo (se cargan desde routes/admin.php)
Route::prefix('admin')->group(base_path('routes/admin.php'));
