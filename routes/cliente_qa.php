<?php

declare(strict_types=1);

// C:\wamp64\www\pactopia360_erp\routes\cliente_qa.php
//
// QA Cliente (solo local/dev/testing)
// ✅ Se monta dentro del grupo "cliente" en RouteServiceProvider (NO en web.php)
// ✅ Ya corre con:
//    - EncryptCookies / AddQueuedCookiesToResponse
//    - ClientSessionConfig (antes de StartSession)
//    - StartSession / CSRF / etc.
// Por eso NO agregamos ClientSessionConfig aquí manualmente.

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;
use App\Services\ClientCredentials;

$isLocal = app()->environment(['local', 'development', 'testing']);

if (!$isLocal) {
    // En prod nunca registrar QA.
    return;
}

/**
 * NOTA:
 * - Este archivo vive bajo prefix('cliente') + as('cliente.') del RouteServiceProvider.
 * - Por lo tanto el path real queda: /cliente/_qa/reset-pass
 * - Y el nombre real queda: cliente.qa.reset_pass
 */

// Evita duplicados si se cachean rutas o si otro archivo define lo mismo
if (!Route::has('cliente.qa.reset_pass')) {

    Route::post('_qa/reset-pass', function (Request $r) {

        $r->validate([
            'rfc' => 'required|string|max:32',
        ]);

        $res = ClientCredentials::resetOwnerByRfc(
            $r->string('rfc')->toString()
        );

        return response()->json($res, ($res['ok'] ?? false) ? 200 : 422);
    })
        ->middleware(['throttle:30,1'])
        ->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])
        ->name('qa.reset_pass');
}
