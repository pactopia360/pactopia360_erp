<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\Admin\QaController;
use App\Http\Controllers\Auth\SmartLoginController;

/*
|--------------------------------------------------------------------------
| ENTORNO
|--------------------------------------------------------------------------
*/
$isLocal = app()->environment(['local','development','testing']);

/*
|--------------------------------------------------------------------------
| Grupo global "web"
|--------------------------------------------------------------------------
*/
Route::middleware('web')->group(function () use ($isLocal) {

    /* ===================== Público ===================== */
    Route::redirect('/', '/cliente')->name('home.public');

    // Login “inteligente” que respeta el contexto cliente/admin
    Route::get('/login', SmartLoginController::class)->name('login');

    /* ===================== Admin ===================== */
    Route::prefix('admin')
        ->as('admin.')
        ->group(base_path('routes/admin.php'));

    /* ===================== Cliente ===================== */
    Route::prefix('cliente')
        ->as('cliente.')
        ->group(base_path('routes/cliente.php'));

    /* ===================== Cliente SAT (módulo separado) ===================== */
    Route::prefix('cliente')
        ->as('cliente.')
        ->group(base_path('routes/cliente_sat.php'));

    /* ===================== QA Cliente (Local) ===================== */
    if ($isLocal) {
        Route::prefix('cliente')
            ->as('cliente.')
            ->group(base_path('routes/cliente_qa.php'));
    }

    /* ===================== Utilidades / Infra ===================== */
    Route::get('/_deploy/finish/{signature}', [DeployController::class, 'finish'])
        ->where('signature', '[A-Za-z0-9._-]+')
        ->middleware('throttle:12,1')
        ->name('deploy.finish');

    if ($isLocal) {
        Route::get('storage/{path}', function (string $path) {
            $full = storage_path('app/public/' . $path);
            abort_unless(is_file($full), 404);
            return response()->file($full);
        })
        ->where('path', '.*')
        ->middleware('throttle:60,1')
        ->name('storage.local');
    }

    /* ===================== QA Admin/Dev (Local) ===================== */
    if ($isLocal) {
        Route::prefix('admin/dev')
            ->name('admin.dev.')
            ->middleware(['auth:admin', \App\Http\Middleware\AdminSessionConfig::class])
            ->group(function () {
                Route::get('/qa',                [QaController::class, 'index'])->name('qa');
                Route::post('/resend-email',     [QaController::class, 'resendEmail'])->name('resend_email');
                Route::post('/send-otp',         [QaController::class, 'sendOtp'])->name('send_otp');
                Route::post('/force-email',      [QaController::class, 'forceEmailVerified'])->name('force_email');
                Route::post('/force-phone',      [QaController::class, 'forcePhoneVerified'])->name('force_phone');

                Route::post('/clean-otps',       [QaController::class, 'cleanOtps'])
                    ->name('clean_otps')
                    ->middleware('throttle:30,1');
            });
    }

    // Route::fallback(fn () => abort(404));
});
