<?php
// C:\wamp64\www\pactopia360_erp\routes\cliente_public.php
// PACTOPIA360 · Cliente PUBLIC routes (SIN middleware "cliente")

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cliente\AccountBillingController;

/*
|--------------------------------------------------------------------------
| Billing públicos (SIN LOGIN) — firmados
|--------------------------------------------------------------------------
| - NO pasan por middleware "cliente" (ClientSessionConfig).
| - Protegidos con signed + throttle.
| - Prefijo /cliente y nombres cliente.* se aplican desde RouteServiceProvider.
*/

Route::get('billing/statement/public-pdf/{accountId}/{period}', [AccountBillingController::class, 'publicPdf'])
    ->middleware(['signed', 'throttle:60,1'])
    ->whereNumber('accountId')
    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
    ->name('billing.publicPdf');

Route::get('billing/statement/public-pdf-inline/{accountId}/{period}', [AccountBillingController::class, 'publicPdfInline'])
    ->middleware(['signed', 'throttle:60,1'])
    ->whereNumber('accountId')
    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
    ->name('billing.publicPdfInline');

Route::get('billing/statement/public-pay/{accountId}/{period}', [AccountBillingController::class, 'publicPay'])
    ->middleware(['signed', 'throttle:60,1'])
    ->whereNumber('accountId')
    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
    ->name('billing.publicPay');
