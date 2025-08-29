<?php

use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\Cliente\Auth\LoginController as ClienteLogin;
// use App\Http\Controllers\Cliente\HomeController as ClienteHome;

Route::middleware('guest')->group(function () {
    // Route::get('login',  [ClienteLogin::class, 'showLogin'])->name('login');
    // Route::post('login', [ClienteLogin::class, 'login'])->name('login.do');
});

Route::middleware('auth')->group(function () {
    // Route::get('/', fn () => redirect()->route('cliente.home'))->name('root');
    // Route::get('home', [ClienteHome::class, 'index'])->name('home');
    // Route::post('logout', [ClienteLogin::class, 'logout'])->name('logout');
});
