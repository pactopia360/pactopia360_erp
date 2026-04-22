<?php

use App\Http\Controllers\Api\Mobile\Account\MobileAccountController;
use App\Http\Controllers\Api\Mobile\Auth\MobileAuthController;
use App\Http\Controllers\Api\Mobile\Billing\MobileBillingController;
use App\Http\Controllers\Api\Mobile\Billing\MobileInvoicesController;
use App\Http\Controllers\Api\Mobile\Sat\MobileSatDashboardController;
use App\Http\Controllers\Api\Mobile\Sat\MobileSatQuotesController;
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
        | Área autenticada móvil
        |--------------------------------------------------------------------------
        */
        Route::middleware('auth:sanctum')->group(function () {
            /*
            |--------------------------------------------------------------------------
            | Dashboard móvil general (HOME)
            |--------------------------------------------------------------------------
            */
            Route::get('/dashboard', [MobileSatDashboardController::class, 'index'])
                ->name('api.mobile.dashboard');

            /*
            |--------------------------------------------------------------------------
            | Mi cuenta móvil
            |--------------------------------------------------------------------------
            */
            Route::prefix('account')->group(function () {
                Route::get('/profile', [MobileAccountController::class, 'profile'])
                    ->name('api.mobile.account.profile');

                Route::get('/payments', [MobileAccountController::class, 'payments'])
                    ->name('api.mobile.account.payments');
            });

           /*
            |--------------------------------------------------------------------------
            | Billing móvil
            |--------------------------------------------------------------------------
            */
            Route::prefix('billing')->group(function () {
                Route::get('/statement', [MobileBillingController::class, 'statement'])
                    ->name('api.mobile.billing.statement');

                Route::get('/statement/{period}/pdf-url', [MobileBillingController::class, 'pdfUrl'])
                    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
                    ->name('api.mobile.billing.statement.pdf-url');

                Route::get('/statement/{period}/pay-url', [MobileBillingController::class, 'payUrl'])
                    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
                    ->name('api.mobile.billing.statement.pay-url');

                Route::post('/statement/{period}/invoice-request', [MobileBillingController::class, 'requestInvoice'])
                    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
                    ->name('api.mobile.billing.statement.invoice-request');
            });

            /*
            |--------------------------------------------------------------------------
            | Facturas móviles
            |--------------------------------------------------------------------------
            */
            Route::prefix('invoices')->group(function () {
                Route::get('/', [MobileInvoicesController::class, 'index'])
                    ->name('api.mobile.invoices.index');

                Route::get('/{id}/download-url', [MobileInvoicesController::class, 'downloadUrl'])
                    ->name('api.mobile.invoices.download-url');
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

            Route::prefix('quotes')->group(function () {
                Route::get('/', [MobileSatQuotesController::class, 'index'])
                    ->name('api.mobile.sat.quotes.index');

                Route::get('/{id}', [MobileSatQuotesController::class, 'show'])
                    ->name('api.mobile.sat.quotes.show');

                Route::post('/quick-calc', [MobileSatQuotesController::class, 'quickCalc'])
                    ->name('api.mobile.sat.quotes.quick-calc');

                Route::post('/', [MobileSatQuotesController::class, 'store'])
                    ->name('api.mobile.sat.quotes.store');

                Route::post('/{id}/checkout', [MobileSatQuotesController::class, 'checkout'])
                    ->name('api.mobile.sat.quotes.checkout');

                Route::post('/{id}/transfer-proof', [MobileSatQuotesController::class, 'submitTransferProof'])
                    ->name('api.mobile.sat.quotes.transfer-proof');
            });
        });
    });

    Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
        return response()->json([
            'ok'   => true,
            'user' => $request->user(),
        ]);
    });
});