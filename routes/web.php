<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\Admin\QaController;
use App\Http\Middleware\VerifyCsrfToken;

$isLocal = app()->environment(['local','development','testing']);

Route::middleware('web')->group(function () use ($isLocal) {

    /* ===================== Público ===================== */
    // Home público (compatible con route:cache)
    Route::view('/', 'welcome')->name('home.public');

    // Alias de login genérico → admin
    Route::redirect('/login', '/admin/login')->name('login');

    /* ===================== Admin ===================== */
    Route::prefix('admin')
        ->as('admin.')
        ->group(base_path('routes/admin.php'));

    /* ===================== Cliente ===================== */
    // En local quitamos CSRF para testeo (login/reset/qa)
    $clienteGroup = Route::prefix('cliente')->as('cliente.');
    if ($isLocal) {
        $clienteGroup = $clienteGroup->withoutMiddleware([VerifyCsrfToken::class]);
    }

    // Rutas normales del panel cliente
    $clienteGroup->group(base_path('routes/cliente.php'));

    // Rutas QA del panel cliente (solo en local)
    if ($isLocal) {
        $clienteGroup->group(base_path('routes/cliente_qa.php'));
    }

    /* ===================== Utilidades ===================== */
    // Hook de deploy (opcional)
    Route::get('/_deploy/finish/{signature}', [DeployController::class, 'finish'])
        ->where('signature', '[A-Za-z0-9._-]+')
        ->middleware('throttle:12,1')
        ->name('deploy.finish');

    // Servir storage en local si no hay symlink
    if ($isLocal) {
        Route::get('storage/{path}', function (string $path) {
            $full = storage_path('app/public/' . $path);
            abort_unless(is_file($full), 404);
            return response()->file($full);
        })->where('path', '.*')->middleware('throttle:60,1')->name('storage.local');
    }

    /* ===================== QA Admin/Dev (Local) ===================== */
    if ($isLocal) {
        Route::prefix('admin/dev')
            ->name('admin.dev.')
            ->middleware('auth:admin')
            ->group(function () {
                Route::get('/qa',            [QaController::class, 'index'])->name('qa');
                Route::post('/resend-email', [QaController::class, 'resendEmail'])->name('resend_email');
                Route::post('/send-otp',     [QaController::class, 'sendOtp'])->name('send_otp');
                Route::post('/force-email',  [QaController::class, 'forceEmailVerified'])->name('force_email');
                Route::post('/force-phone',  [QaController::class, 'forcePhoneVerified'])->name('force_phone');
            });
    }

    // Route::fallback(fn () => abort(404)); // opcional
});
