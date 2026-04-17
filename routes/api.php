<?php

use App\Http\Controllers\Api\Mobile\Auth\MobileAuthController;
use App\Http\Controllers\Api\Mobile\Sat\MobileSatDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'ok'      => true,
        'service' => 'PACTOPIA360 API',
        'version' => 'v1',
        'status'  => 'online',
    ]);
});

Route::prefix('v1')->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'ok'        => true,
            'service'   => 'PACTOPIA360 API v1',
            'status'    => 'online',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Mobile API - Cliente
    |--------------------------------------------------------------------------
    | API dedicada para la app Android / Flutter.
    | No interfiere con el login web actual del portal cliente.
    */
    Route::prefix('mobile')->group(function () {
        Route::get('/ping', function () {
            return response()->json([
                'ok'        => true,
                'channel'   => 'mobile',
                'service'   => 'PACTOPIA360 Mobile API',
                'status'    => 'ready',
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Auth móvil
        |--------------------------------------------------------------------------
        */
        Route::prefix('auth')->group(function () {
            Route::post('/login', [MobileAuthController::class, 'login'])
                ->name('api.mobile.auth.login');

            Route::middleware('auth:sanctum')->group(function () {
                Route::get('/me', [MobileAuthController::class, 'me'])
                    ->name('api.mobile.auth.me');

                Route::post('/logout', [MobileAuthController::class, 'logout'])
                    ->name('api.mobile.auth.logout');
            });
        });

        /*
        |--------------------------------------------------------------------------
        | SAT móvil
        |--------------------------------------------------------------------------
        */
        Route::middleware('auth:sanctum')->prefix('sat')->group(function () {
            Route::get('/ping', function (Request $request) {
                return response()->json([
                    'ok'        => true,
                    'channel'   => 'mobile-sat',
                    'status'    => 'ready',
                    'user_id'   => optional($request->user())->id,
                    'timestamp' => now()->toIso8601String(),
                ]);
            })->name('api.mobile.sat.ping');

            Route::get('/dashboard', [MobileSatDashboardController::class, 'index'])
                ->name('api.mobile.sat.dashboard');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | API general autenticada
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
        return response()->json([
            'ok'   => true,
            'user' => $request->user(),
        ]);
    });
});