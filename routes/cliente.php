<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Cliente\Auth\LoginController as ClienteLogin;
use App\Http\Controllers\Cliente\HomeController as ClienteHome;
use App\Http\Controllers\Cliente\RegisterController;
use App\Http\Controllers\Cliente\VerificationController;
use App\Http\Controllers\Cliente\PasswordController;
use App\Http\Controllers\Cliente\StripeController;
use App\Http\Controllers\Cliente\AccountBillingController;
use App\Http\Controllers\Cliente\Auth\FirstPasswordController;
use App\Http\Controllers\Cliente\FacturacionController as ClienteFacturacion;

use App\Http\Controllers\Cliente\AlertasController;
use App\Http\Controllers\Cliente\ChatController;
use App\Http\Controllers\Cliente\MarketplaceController;
use App\Http\Controllers\Cliente\PerfilController;
use App\Http\Controllers\Cliente\MiCuentaController;
use App\Http\Controllers\Cliente\UiController;

// ✅ FIX: importar FacturasController con su namespace real
use App\Http\Controllers\Cliente\MiCuenta\FacturasController;

// ✅ CSRF (solo para local)
use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;

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
$throttleAlerts       = $isLocal ? 'throttle:120,1' : 'throttle:30,1';
$throttleChat         = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$throttleHomeData     = $isLocal ? 'throttle:120,1' : 'throttle:30,1';

/*
|--------------------------------------------------------------------------
| ROOT
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth('web')->check()
        ? redirect()->route('cliente.home')
        : redirect()->route('cliente.login');
})->name('root');

/*
|---------------------------------------------------------------------------
| ✅ PAYWALL (SIN LOGIN) — redirige a Stripe Checkout PRO
|---------------------------------------------------------------------------
| LoginController (cuando admin.is_blocked=1) hace:
|   return redirect()->route('cliente.paywall');
|
| Aquí NO podemos mandar al "estado de cuenta" porque requiere auth:web y eso
| provoca loop a login. Entonces redirigimos directo a Checkout PRO.
*/
Route::get('paywall', function (Request $request) {
    $accountId = (int) $request->session()->get('paywall.account_id', 0);
    $cycle     = strtolower((string) $request->session()->get('paywall.cycle', 'mensual'));
    $email     = (string) $request->session()->get('paywall.email', '');

    if ($accountId <= 0) {
        return redirect()->route('cliente.login');
    }

    $cycle = ($cycle === 'anual' || $cycle === 'annual') ? 'anual' : 'mensual';

    // ✅ Redirección inmediata (sin mensajes)
    // Usamos rutas públicas GET para evitar CSRF y porque aún no hay auth.
    if ($cycle === 'anual') {
        return redirect()->route('cliente.checkout.pro.annual', [
            'account_id' => $accountId,
            'email'      => $email ?: null,
        ]);
    }

    return redirect()->route('cliente.checkout.pro.monthly', [
        'account_id' => $accountId,
        'email'      => $email ?: null,
    ]);
})->name('paywall');

/*
|--------------------------------------------------------------------------
| PDF / PAGO PÚBLICOS FIRMADOS (SIN LOGIN)
|--------------------------------------------------------------------------
| IMPORTANTE:
| - Ya estás dentro del grupo "cliente" desde RouteServiceProvider,
|   así que ClientSessionConfig ya corre antes de StartSession.
| - Para enlaces firmados NO necesitas auth. Solo signed.
*/
Route::get('billing/statement/public-pdf/{accountId}/{period}', [AccountBillingController::class, 'publicPdf'])
    ->whereNumber('accountId')
    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
    ->middleware(['signed'])
    ->name('billing.publicPdf');

Route::get('billing/statement/public-pdf-inline/{accountId}/{period}', [AccountBillingController::class, 'publicPdfInline'])
    ->whereNumber('accountId')
    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
    ->middleware(['signed'])
    ->name('billing.publicPdfInline');

Route::get('billing/statement/public-pay/{accountId}/{period}', [AccountBillingController::class, 'publicPay'])
    ->whereNumber('accountId')
    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
    ->middleware(['signed'])
    ->name('billing.publicPay');

/*
|--------------------------------------------------------------------------
| GUARDAR PERFIL FACTURACIÓN (legacy/aux)
|--------------------------------------------------------------------------
*/
Route::post('billing/profile/save', [AccountBillingController::class, 'saveBillingProfile'])
    ->name('billing.profile.save');

/*
|--------------------------------------------------------------------------
| ✅ STRIPE CHECKOUT PRO (PÚBLICO) — GET
|--------------------------------------------------------------------------
| Paywall redirige aquí con account_id/email por query string.
| StripeController ya valida account_id/email, funciona con GET o POST.
*/
Route::get('checkout/pro/mensual', [StripeController::class, 'checkoutMonthly'])
    ->middleware($throttleCheckout)
    ->name('checkout.pro.monthly');

Route::get('checkout/pro/anual', [StripeController::class, 'checkoutAnnual'])
    ->middleware($throttleCheckout)
    ->name('checkout.pro.annual');

/*
|--------------------------------------------------------------------------
| STRIPE CALLBACKS
|--------------------------------------------------------------------------
*/
Route::get('checkout/success', [StripeController::class, 'success'])
    ->name('checkout.success');

Route::get('checkout/cancel', [StripeController::class, 'cancel'])
    ->name('checkout.cancel');

/*
|--------------------------------------------------------------------------
| INVITADOS (GUEST)
|--------------------------------------------------------------------------
*/
Route::middleware(['guest:web'])->group(function () use (
    $isLocal,
    $throttleLogin,
    $throttleRegister,
    $throttleCheckout,
    $throttleVerifyResend,
    $throttleOtpSend,
    $throttleOtpCheck,
    $throttlePassEmail,
    $throttlePassReset
) {

    /*
    |--------------------------------------------------------------------------
    | AUTH
    |--------------------------------------------------------------------------
    */
    Route::get('login', [ClienteLogin::class, 'showLogin'])->name('login');

    $postLogin = Route::post('login', [ClienteLogin::class, 'login'])
        ->middleware($throttleLogin)
        ->name('login.do');

    if ($isLocal) {
        $postLogin->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }

    Route::get('registro', [RegisterController::class, 'showFree'])->name('registro.free');
    $rFree = Route::post('registro', [RegisterController::class, 'storeFree'])
        ->middleware($throttleRegister)
        ->name('registro.free.do');

    Route::get('registro/pro', [RegisterController::class, 'showPro'])->name('registro.pro');
    $rPro = Route::post('registro/pro', [RegisterController::class, 'storePro'])
        ->middleware($throttleRegister)
        ->name('registro.pro.do');

    if ($isLocal) {
        foreach ([$rFree, $rPro] as $r) {
            $r->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFICACIÓN EMAIL (TOKEN)
    |--------------------------------------------------------------------------
    */
    Route::get('verificar/email/{token}', [VerificationController::class, 'verifyEmail'])
        ->where('token', '[A-Za-z0-9\-\_\.]+')
        ->name('verify.email.token');

    /*
    |--------------------------------------------------------------------------
    | VERIFICACIÓN EMAIL (SIGNED LINK)
    |--------------------------------------------------------------------------
    */
    Route::get('verificar/email/link', [VerificationController::class, 'verifyEmailSigned'])
        ->middleware('signed')
        ->name('verify.email.signed');

    /*
    |--------------------------------------------------------------------------
    | REENVIAR VERIFICACIÓN EMAIL
    |--------------------------------------------------------------------------
    */
    Route::get('verificar/email/reenviar', [VerificationController::class, 'showResendEmail'])
        ->name('verify.email.resend');

    $resendEmail = Route::post('verificar/email/reenviar', [VerificationController::class, 'resendEmail'])
        ->middleware($throttleVerifyResend)
        ->name('verify.email.resend.do');

    if ($isLocal) {
        $resendEmail->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFICACIÓN TELÉFONO (OTP) - flujo invitado
    |--------------------------------------------------------------------------
    */
    Route::get('verificar/telefono', [VerificationController::class, 'showOtp'])
        ->name('verify.phone');

    $updPhone = Route::post('verificar/telefono', [VerificationController::class, 'updatePhone'])
        ->middleware($throttleOtpSend)
        ->name('verify.phone.update');

    $sendOtp = Route::post('verificar/telefono/send', [VerificationController::class, 'sendOtp'])
        ->middleware($throttleOtpSend)
        ->name('verify.phone.send');

    $checkOtp = Route::post('verificar/telefono/check', [VerificationController::class, 'checkOtp'])
        ->middleware($throttleOtpCheck)
        ->name('verify.phone.check');

    if ($isLocal) {
        foreach ([$updPhone, $sendOtp, $checkOtp] as $r) {
            $r->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }
    }
});

/*
|--------------------------------------------------------------------------
| PRIMER PASSWORD (POST-LOGIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:web'])->group(function () use ($isLocal) {

    Route::get('password/first', [FirstPasswordController::class, 'show'])
        ->name('password.first');

    $firstPassStore = Route::post('password/first', [FirstPasswordController::class, 'store'])
        ->name('password.first.store');

    $firstPassDo = Route::post('password/first/do', [FirstPasswordController::class, 'store'])
        ->name('password.first.do');

    if ($isLocal) {
        $firstPassStore->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        $firstPassDo->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }
});

/*
|--------------------------------------------------------------------------
| UI helpers (demo-mode, theme, etc.)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:web'])->group(function () use ($isLocal) {

    $demoPost = Route::post('ui/demo-mode', [UiController::class, 'demoMode'])
        ->name('ui.demo_mode');

    Route::get('ui/demo-mode', [UiController::class, 'demoModeGet'])
        ->name('ui.demo_mode.get');

    if ($isLocal) {
        $demoPost->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }
});

/*
|--------------------------------------------------------------------------
| LEGAL (PÚBLICO)
|--------------------------------------------------------------------------
*/
Route::view('terminos', 'legal.terminos')->name('terminos');


/*
|--------------------------------------------------------------------------
| ÁREA AUTENTICADA
|--------------------------------------------------------------------------
| ✅ FIX REAL:
| - hidratar módulos desde admin SOT para que el cliente respete ON/OFF
| - forzar session.cliente por consistencia (aunque ya venga del RouteServiceProvider)
*/
Route::middleware(['session.cliente', 'auth:web', 'account.active', 'cliente.hydrate_modules'])
    ->group(function () use (
        $throttleBillingPay,
        $throttleAlerts,
        $throttleChat,
        $throttleHomeData,
        $isLocal
    ) {

    Route::get('home', [ClienteHome::class, 'index'])->name('home');

    /*
    |--------------------------------------------------------------------------
    | PERFIL (Cliente)
    |--------------------------------------------------------------------------
    */
    Route::prefix('perfil')->name('perfil.')->group(function () use ($isLocal) {

        Route::get('/', [PerfilController::class, 'index'])->name('index');
        Route::get('/', [PerfilController::class, 'index'])->name('show');

        $pw = Route::match(['POST','PUT'], 'password', [PerfilController::class, 'updatePassword'])
            ->name('password.update');

        $ph = Route::match(['POST','PUT'], 'phone', [PerfilController::class, 'updatePhone'])
            ->name('phone.update');

        $av = Route::match(['POST','PUT'], 'avatar', [PerfilController::class, 'uploadAvatar'])
            ->name('avatar.update');

        Route::get('settings', [PerfilController::class, 'settings'])
            ->name('settings');

        if ($isLocal) {
            foreach ([$pw, $ph, $av] as $r) {
                $r->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }
    });

    Route::get('perfil', [PerfilController::class, 'index'])->name('perfil');

    /*
    |--------------------------------------------------------------------------
    | MI CUENTA (Cliente)
    |--------------------------------------------------------------------------
    */
    Route::prefix('mi-cuenta')->name('mi_cuenta.')->group(function () {

        Route::get('/', [MiCuentaController::class, 'index'])->name('index');

        // ✅ MIS PAGOS
        Route::get('pagos', [MiCuentaController::class, 'pagos'])->name('pagos');

        Route::post('profile/update', [MiCuentaController::class, 'profileUpdate'])->name('profile.update');
        Route::post('security/update', [MiCuentaController::class, 'securityUpdate'])->name('security.update');
        Route::post('preferences/update', [MiCuentaController::class, 'preferencesUpdate'])->name('preferences.update');
        Route::post('brand/update', [MiCuentaController::class, 'brandUpdate'])->name('brand.update');
        Route::post('billing/update', [MiCuentaController::class, 'billingUpdate'])->name('billing.update');

        // Contratos placeholders
        Route::get('contratos', [MiCuentaController::class, 'contratosIndex'])->name('contratos.index');
        Route::get('contratos/{contract}', [MiCuentaController::class, 'showContract'])->whereNumber('contract')->name('contratos.show');
        Route::post('contratos/{contract}/sign', [MiCuentaController::class, 'signContract'])->whereNumber('contract')->name('contratos.sign');
        Route::get('contratos/{contract}/pdf', [MiCuentaController::class, 'downloadSignedPdf'])->whereNumber('contract')->name('contratos.pdf');

        /*
        |--------------------------------------------------------------------------
        | ✅ FACTURAS (Mi cuenta) — SOLICITUDES DE ESTADO DE CUENTA
        |--------------------------------------------------------------------------
        | OJO:
        | Estas “facturas” son las solicitudes/ZIP de estados de cuenta (admin SOT),
        | NO dependen del módulo “facturacion” (CFDI).
        | Si lo dejas con cliente.module:facturacion, el iframe se redirige a Home.
        */
        Route::get('facturas', [FacturasController::class, 'index'])->name('facturas.index');
        Route::post('facturas', [FacturasController::class, 'store'])->name('facturas.store');
        Route::get('facturas/{id}', [FacturasController::class, 'show'])->whereNumber('id')->name('facturas.show');
        Route::get('facturas/{id}/download', [FacturasController::class, 'downloadZip'])->whereNumber('id')->name('facturas.download');

    });

    /*
    |--------------------------------------------------------------------------
    | ESTADO DE CUENTA
    |--------------------------------------------------------------------------
    */
    Route::get('estado-de-cuenta', [AccountBillingController::class, 'statement'])
        ->name('estado_cuenta');

    Route::get('billing/statement/pdf-inline/{period}', [AccountBillingController::class, 'pdfInline'])
        ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
        ->name('billing.pdfInline');

    Route::get('billing/statement/pdf/{period}', [AccountBillingController::class, 'pdf'])
        ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
        ->name('billing.pdf');

    /*
    |--------------------------------------------------------------------------
    | PAGO
    |--------------------------------------------------------------------------
    */
    $payPost = Route::post('billing/statement/pay/{period}', [AccountBillingController::class, 'pay'])
        ->middleware($throttleBillingPay)
        ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
        ->name('billing.pay');

    Route::get('billing/statement/pay/{period}', [AccountBillingController::class, 'pay'])
        ->middleware($throttleBillingPay)
        ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
        ->name('billing.pay.get');

    if ($isLocal) {
        $payPost->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }

    /*
    |--------------------------------------------------------------------------
    | FACTURAR (Solicitud y Descarga ZIP)
    |--------------------------------------------------------------------------
    */
    $reqInv = Route::post('billing/statement/factura/{period}', [AccountBillingController::class, 'requestInvoice'])
        ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
        ->name('billing.factura.request');

    Route::get('billing/statement/factura/{period}/download', [AccountBillingController::class, 'downloadInvoiceZip'])
        ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
        ->name('billing.factura.download');

    if ($isLocal) {
        $reqInv->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */
    Route::post('logout', [ClienteLogin::class, 'logout'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| STRIPE WEBHOOK
|--------------------------------------------------------------------------
*/
Route::post('stripe/webhook', [StripeController::class, 'webhook'])
    ->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])
    ->name('stripe.webhook');
