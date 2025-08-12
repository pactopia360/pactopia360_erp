<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\HomeController;

Route::middleware('guest:admin')->group(function () {
    Route::get('login',  [LoginController::class, 'showLogin'])->name('admin.login');
    Route::post('login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('admin.login.do');
});

Route::middleware('auth:admin')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.home'))->name('admin.root');
    Route::get('dashboard', fn () => redirect()->route('admin.home'))->name('admin.dashboard');

    // KPIs + series
    Route::get('home', [HomeController::class, 'index'])->name('admin.home');
    Route::get('home/stats', [HomeController::class, 'stats'])->name('admin.home.stats');

    // 🔎 Drill-down por mes (YYYY-MM)
    Route::get('home/income/{ym}', [HomeController::class, 'incomeByMonth'])
        ->where('ym', '^\d{4}-(0[1-9]|1[0-2])$')
        ->name('admin.home.incomeMonth');

    Route::post('logout', [LoginController::class, 'logout'])->name('admin.logout');
});
