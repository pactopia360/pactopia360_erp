<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// ================= Controladores =================
use App\Http\Controllers\Cliente\Auth\LoginController as ClienteLogin;
use App\Http\Controllers\Cliente\HomeController as ClienteHome;
use App\Http\Controllers\Cliente\RegisterController;
use App\Http\Controllers\Cliente\VerificationController;
use App\Http\Controllers\Cliente\PasswordController; // Forgot/Reset clásico
use App\Http\Controllers\Cliente\StripeController;
use App\Http\Controllers\Cliente\EstadoCuentaController;
use App\Http\Controllers\Cliente\AccountBillingController;
use App\Http\Controllers\Cliente\Auth\FirstPasswordController;
use App\Http\Controllers\Cliente\Auth\PasswordResetController;
// Facturación (cliente)
use App\Http\Controllers\Cliente\FacturacionController as ClienteFacturacion;

// ====== NUEVOS (para header) ======
use App\Http\Controllers\Cliente\AlertasController;
use App\Http\Controllers\Cliente\ChatController;
use App\Http\Controllers\Cliente\MarketplaceController;
use App\Http\Controllers\Cliente\PerfilController;

// ================= Middlewares =================
use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;

// ================= Jobs QA =================
use App\Jobs\SendTestMailJob;

/* -------------------------------------------
| Flags / Throttles
|------------------------------------------- */
$isLocal = app()->environment(['local', 'development', 'testing']);

$throttleLogin        = $isLocal ? 'throttle:60,1'  : 'throttle:5,1';
$throttleRegister     = $isLocal ? 'throttle:60,1'  : 'throttle:6,1';
$throttleCheckout     = $isLocal ? 'throttle:120,1' : 'throttle:10,1';
$throttleVerifyResend = $isLocal ? 'throttle:30,1'  : 'throttle:3,10';
$throttleOtpSend      = $isLocal ? 'throttle:30,1'  : 'throttle:3,5';
$throttleOtpCheck     = $isLocal ? 'throttle:60,1'  : 'throttle:6,5';
$throttlePassEmail    = $isLocal ? 'throttle:30,1'  : 'throttle:5,10';
$throttlePassReset    = $isLocal ? 'throttle:60,1'  : 'throttle:6,10';
$throttleBillingPay   = $isLocal ? 'throttle:120,1' : 'throttle:10,1';
$throttleQaSeedClean  = $isLocal ? 'throttle:120,1' : 'throttle:12,1';
$throttleUiTheme      = $isLocal ? 'throttle:120,1' : 'throttle:30,1';

/* Módulos header */
$throttleAlerts       = $isLocal ? 'throttle:120,1' : 'throttle:30,1';
$throttleChat         = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';

/* Home data (AJAX) */
$throttleHomeData     = $isLocal ? 'throttle:120,1' : 'throttle:30,1';

/*
|--------------------------------------------------------------------------
| Raíz pública de /cliente (redirige a home si hay sesión; si no, a login)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth('web')->check()
        ? redirect()->route('cliente.home')
        : redirect()->route('cliente.login');
})->name('root');

/*
|--------------------------------------------------------------------------
| Invitados (guard web)
|--------------------------------------------------------------------------
*/
Route::middleware('guest:web')->group(function () use (
    $isLocal, $throttleLogin, $throttleRegister, $throttleCheckout,
    $throttleVerifyResend, $throttleOtpSend, $throttleOtpCheck,
    $throttlePassEmail, $throttlePassReset
) {
    // Login
    Route::get('login',  [ClienteLogin::class, 'showLogin'])->name('login');
    $postLogin = Route::post('login', [ClienteLogin::class, 'login'])
        ->middleware($throttleLogin)
        ->name('login.do');
    if ($isLocal) {
        $postLogin->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }

    // Registro FREE
    Route::get('registro', [RegisterController::class, 'showFree'])->name('registro.free');
    Route::post('registro', [RegisterController::class, 'storeFree'])
        ->middleware($throttleRegister)->name('registro.free.do');

    // Registro PRO / Checkout
    Route::get('registro/pro', [RegisterController::class, 'showPro'])->name('registro.pro');
    Route::post('registro/pro', [RegisterController::class, 'storePro'])
        ->middleware($throttleRegister)->name('registro.pro.do');

    Route::post('checkout/pro/mensual', [StripeController::class, 'checkoutMonthly'])
        ->middleware($throttleCheckout)->name('checkout.pro.monthly');
    Route::post('checkout/pro/anual',   [StripeController::class, 'checkoutAnnual'])
        ->middleware($throttleCheckout)->name('checkout.pro.annual');
    Route::get('checkout/success', [StripeController::class, 'success'])->name('checkout.success');
    Route::get('checkout/cancel',  [StripeController::class, 'cancel'])->name('checkout.cancel');

    // Verificación de correo
    Route::get('verificar/email/{token}', [VerificationController::class, 'verifyEmail'])
        ->where('token', '[A-Za-z0-9\-_]{20,100}')
        ->name('verify.email.token');

    Route::get('verificar/email/link', [VerificationController::class, 'verifyEmailSigned'])
        ->middleware('signed')->name('verify.email.signed');

    // Reenvío verificación
    Route::get('verificar/email/resend', [VerificationController::class, 'showResendEmail'])
        ->name('verify.email.resend');

    Route::post('verificar/email/resend', [VerificationController::class, 'resendEmail'])
        ->middleware($throttleVerifyResend)->name('verify.email.resend.do');

    // Teléfono (OTP)
    Route::get('verificar/telefono', [VerificationController::class, 'showOtp'])->name('verify.phone');
    Route::post('verificar/telefono', [VerificationController::class, 'sendOtp'])
        ->middleware($throttleOtpSend)->name('verify.phone.send');
    Route::post('verificar/telefono/check', [VerificationController::class, 'checkOtp'])
        ->middleware($throttleOtpCheck)->name('verify.phone.check');

    // Forgot/Reset password (flujo estándar por email token)
    Route::get('password/forgot',  [PasswordController::class, 'showLinkRequestForm'])->name('password.forgot');
    Route::post('password/email',  [PasswordController::class, 'sendResetLinkEmail'])
        ->middleware($throttlePassEmail)->name('password.email');

    Route::get('password/reset/{token}', [PasswordController::class, 'showResetForm'])
        ->where('token', '[A-Za-z0-9\-_]{20,100}')->name('password.reset');

    Route::post('password/reset', [PasswordController::class, 'reset'])
        ->middleware($throttlePassReset)->name('password.update');

    // Términos
    Route::get('terminos', function () {
        $url = config('app.terms_url') ?? env('APP_TERMS_URL') ?? 'https://pactopia.com/terminos';
        return redirect()->away($url);
    })->name('terminos');
});

/*
|--------------------------------------------------------------------------
| Switch de Tema (público con CSRF)
|--------------------------------------------------------------------------
| Usado por el layout del cliente para alternar light/dark y cambiar el logo.
*/
Route::post('ui/theme', function (Request $r) {
    $data = $r->validate(['theme' => 'required|string|in:light,dark']);
    session(['client_ui.theme' => $data['theme']]);
    return response()->noContent();
})->middleware($throttleUiTheme)->name('ui.theme.switch');

/*
|--------------------------------------------------------------------------
| Autenticados (guard web + cuenta activa)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:web', 'account.active'])->group(function () use ($throttleBillingPay, $throttleAlerts, $throttleChat, $throttleHomeData) {

    // ===== Home (vista) =====
    Route::get('home', [ClienteHome::class, 'index'])->name('home');

    // ===== Home · Datos (AJAX) =====
    // Soporta: ?month=YYYY-MM   ó   ?mes=MM&anio=YYYY (el controlador unifica)
    Route::get('home/kpis',   [ClienteHome::class, 'kpis'])->middleware($throttleHomeData)->name('home.kpis');
    Route::get('home/series', [ClienteHome::class, 'series'])->middleware($throttleHomeData)->name('home.series');
    Route::get('home/combo',  [ClienteHome::class, 'combo'])->middleware($throttleHomeData)->name('home.combo');

    // First-login (forzar cambio)
    Route::get('password/first',  [FirstPasswordController::class, 'show'])->name('password.first');
    Route::post('password/first', [FirstPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.first.store');

    // Estado de cuenta
    Route::get('estado-cuenta', [EstadoCuentaController::class, 'index'])->name('estado_cuenta');

    // Billing & Payments
    Route::get('billing/statement', [AccountBillingController::class, 'statement'])->name('billing.statement');
    Route::post('billing/pay-pending', [AccountBillingController::class, 'payPending'])
        ->middleware($throttleBillingPay)->name('billing.payPending');

    // ====== Módulos Header ======
    // 🔔 Alertas
    Route::get('alertas', [AlertasController::class, 'index'])->name('alertas');
    Route::patch('alertas/{id}/read', [AlertasController::class, 'markAsRead'])
        ->middleware($throttleAlerts)->name('alertas.read');
    Route::delete('alertas/{id}', [AlertasController::class, 'destroy'])
        ->middleware($throttleAlerts)->name('alertas.delete');

    // 💬 Chat soporte
    Route::get('soporte/chat', [ChatController::class, 'index'])->name('soporte.chat');
    Route::post('soporte/chat/send', [ChatController::class, 'send'])
        ->middleware($throttleChat)->name('soporte.chat.send');

    // 🛒 Marketplace
    Route::get('marketplace', [MarketplaceController::class, 'index'])->name('marketplace');

    // 👤 Perfil
    Route::get('perfil', [\App\Http\Controllers\Cliente\PerfilController::class, 'show'])->name('perfil');

    // Empresas / Emisores desde Perfil
    Route::post('emisores', [\App\Http\Controllers\Cliente\PerfilController::class, 'storeEmisor'])->name('emisores.store');
    Route::post('emisores/{id}/logo', [\App\Http\Controllers\Cliente\PerfilController::class, 'uploadEmisorLogo'])->name('emisores.logo');
    Route::post('emisores/import', [\App\Http\Controllers\Cliente\PerfilController::class, 'importEmisores'])->name('emisores.import');


    // Logout
    Route::post('logout', [ClienteLogin::class, 'logout'])->name('logout');

    // ===== Facturación (cliente) =====
    Route::prefix('facturacion')->as('facturacion.')->group(function () {
        Route::get('/',        [ClienteFacturacion::class, 'index'])->name('index');
        Route::get('export',   [ClienteFacturacion::class, 'export'])->name('export');
        Route::get('kpis',     [ClienteFacturacion::class, 'kpis'])->name('kpis');
        Route::get('series',   [ClienteFacturacion::class, 'series'])->name('series');

        Route::get('nuevo',    [ClienteFacturacion::class, 'create'])->name('nuevo');

        // ⇩⇩ aquí están las dos rutas POST aceptadas
        Route::post('/',        [ClienteFacturacion::class, 'store'])->name('store');     // alias
        Route::post('guardar',  [ClienteFacturacion::class, 'store'])->name('guardar');   // la que ya tenías

        Route::get('{id}',        [ClienteFacturacion::class, 'show'])->name('show');
        Route::get('{id}/editar', [ClienteFacturacion::class, 'edit'])->name('edit');
        Route::put('{id}',        [ClienteFacturacion::class, 'update'])->name('actualizar');

        Route::post('{id}/timbrar',   [ClienteFacturacion::class, 'timbrar'])->name('timbrar');
        Route::post('{id}/cancelar',  [ClienteFacturacion::class, 'cancelar'])->name('cancelar');
        Route::post('{id}/duplicar',  [ClienteFacturacion::class, 'duplicar'])->name('duplicar');

        Route::get('{id}/pdf',  [ClienteFacturacion::class, 'verPdf'])->name('ver_pdf');
        Route::get('{id}/xml',  [ClienteFacturacion::class, 'descargarXml'])->name('descargar_xml');
    });


    // Debug rápido de sesión actual
    Route::get('_whoami', function () {
        $u = auth('web')->user();
        return response()->json([
            'ok'    => (bool) $u,
            'id'    => $u?->id,
            'name'  => $u?->name ?? $u?->nombre,
            'email' => $u?->email,
            'guard' => 'web',
            'now'   => now()->toDateTimeString(),
        ]);
    })->name('whoami');
});

/*
|--------------------------------------------------------------------------
| Webhooks (Stripe)
|--------------------------------------------------------------------------
*/
Route::post('webhook/stripe', [StripeController::class, 'webhook'])
    ->withoutMiddleware([AppCsrf::class])
    ->middleware('throttle:120,1')
    ->name('webhook.stripe');

/*
|--------------------------------------------------------------------------
| QA Local (solo local/dev/test)
|--------------------------------------------------------------------------
*/
if ($isLocal) {
    Route::prefix('_qa')->as('qa.')->group(function () use ($throttleQaSeedClean) {

        if (class_exists(\App\Http\Controllers\Cliente\QaController::class)) {
            Route::get('/', [\App\Http\Controllers\Cliente\QaController::class, 'index'])->name('index');
            Route::post('seed',  [\App\Http\Controllers\Cliente\QaController::class, 'seed'])
                ->middleware($throttleQaSeedClean)->name('seed');
            Route::post('clean', [\App\Http\Controllers\Cliente\QaController::class, 'clean'])
                ->middleware($throttleQaSeedClean)->name('clean');
        }

        // Hash check (owner)
        Route::post('hash-check', [\App\Http\Controllers\Cliente\Auth\LoginController::class, 'qaHashCheck'])
            ->middleware('throttle:60,1')
            ->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])
            ->name('hash_check');

        // Forzar password owner y verificar
        Route::post('force-owner-password', [\App\Http\Controllers\Cliente\Auth\LoginController::class, 'qaForceOwnerPassword'])
            ->middleware('throttle:60,1')
            ->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])
            ->name('force_owner_password');

        // Probar password contra todos los usuarios por RFC
        Route::post('test-pass', [\App\Http\Controllers\Cliente\Auth\LoginController::class, 'qaTestPassword'])
            ->middleware('throttle:60,1')
            ->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])
            ->name('test_pass');

        // Reset temporal aleatoria para la cuenta por RFC
        Route::post('reset-pass', [\App\Http\Controllers\Cliente\Auth\LoginController::class, 'qaResetPassword'])
            ->middleware('throttle:30,1')
            ->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])
            ->name('reset_pass');

        // UNIVERSAL CHECK (BUSCARV): email y rfc con el mismo input
        Route::post('universal-check', [\App\Http\Controllers\Cliente\Auth\LoginController::class, 'qaUniversalCheck'])
            ->middleware('throttle:60,1')
            ->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])
            ->name('universal_check');

        // Disparar un correo en cola (requiere queue:work)
        Route::post('queue-test', function (Request $r) {
            $data = $r->validate([
                'to'      => 'required|email',
                'subject' => 'nullable|string|max:120',
                'body'    => 'nullable|string|max:1000',
            ]);

            SendTestMailJob::dispatch(
                $data['to'],
                $data['subject'] ?? 'P360 · Cola OK',
                $data['body']    ?? 'Prueba COLA OK vía queue'
            );

            return response()->json([
                'ok'     => true,
                'queued' => true,
                'to'     => $data['to'],
                'queue'  => config('queue.default'),
            ]);
        })
        ->middleware('throttle:60,1')
        ->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])
        ->name('queue_test');
    });
}

/*
|--------------------------------------------------------------------------
| Público controlado: actualizar teléfono + reenviar OTP
|--------------------------------------------------------------------------
*/
Route::post('verify/phone/update', [VerificationController::class,'updatePhone'])
    ->middleware($isLocal ? 'throttle:60,1' : 'throttle:6,1')
    ->name('verify.phone.update');

/*
|--------------------------------------------------------------------------
| Resets rápidos para soporte (protegidos con auth:admin)
|--------------------------------------------------------------------------
| En local se desactiva CSRF para QA.
*/
Route::prefix('auth')->name('auth.')->middleware('auth:admin')->group(function () use ($isLocal) {
    $r1 = Route::post('reset-by-rfc',   [PasswordResetController::class, 'resetByRfc'])->name('reset_by_rfc');
    $r2 = Route::post('reset-by-email', [PasswordResetController::class, 'resetByEmail'])->name('reset_by_email');

    if ($isLocal) {
        $r1->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])->middleware('throttle:60,1');
        $r2->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])->middleware('throttle:60,1');
    } else {
        $r1->middleware('throttle:6,1');
        $r2->middleware('throttle:6,1');
    }

    // QA bajo auth:admin (soporte)
    Route::prefix('_qa')->as('qa.')->group(function () {
        Route::post('reset-pass', [ClienteLogin::class, 'qaResetPassword'])->name('reset_pass');
    });


    // ===== Emisores (cliente) =====
    Route::prefix('emisores')->as('emisores.')->group(function () {
        Route::get('/',        [\App\Http\Controllers\Cliente\EmisoresController::class, 'index'])->name('index');
        Route::get('nuevo',    [\App\Http\Controllers\Cliente\EmisoresController::class, 'create'])->name('nuevo');
        Route::post('',        [\App\Http\Controllers\Cliente\EmisoresController::class, 'store'])->name('store');

        // PRO
        Route::post('import',  [\App\Http\Controllers\Cliente\EmisoresController::class, 'import'])->name('import');          // csv
        Route::post('csd',     [\App\Http\Controllers\Cliente\EmisoresController::class, 'validateCsd'])->name('csd.validate'); // cer/key
    });

});
