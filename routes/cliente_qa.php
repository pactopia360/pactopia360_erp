<?php

// routes/cliente_qa.php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;
use App\Services\ClientCredentials;

/**
 * NOTA:
 * Este archivo solo se carga en local/dev/testing (ver web.php).
 * Además evitamos colisionar con las rutas QA que ya define routes/cliente.php.
 */

$isLocal = app()->environment(['local','development','testing']);

if ($isLocal) {
    // Solo registrar si NO existe la ruta principal de reset QA
    if (!Route::has('cliente.qa.reset_pass')) {
        Route::post('_qa/reset-pass', function (Request $r) {
            $r->validate(['rfc' => 'required|string|max:32']);
            $res = ClientCredentials::resetOwnerByRfc($r->string('rfc')->toString());
            return response()->json($res, $res['ok'] ? 200 : 422);
        })
        ->middleware(['throttle:30,1', \App\Http\Middleware\ClientSessionConfig::class])
        ->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])
        // nombre único para evitar choques con la versión de cliente.php
        ->name('qa.reset_pass');
    }
}
