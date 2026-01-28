<?php
// C:\wamp64\www\pactopia360_erp\routes\cliente.php
// PACTOPIA360 · Cliente routes (CORE)
// ✅ Canónico: este archivo = portal cliente general (auth, perfil, mi-cuenta, billing, etc.)
// ✅ SAT completo (incluye cotizador) vive en routes/cliente_sat.php (montado en routes/web.php)
// ✅ Aquí NO debe existir ninguna ruta sat.* (para evitar duplicados)
//
// IMPORTANTÍSIMO (route:cache):
// - No duplicar nombres de rutas entre cliente.php y cliente_sat.php
// - Este archivo ya se monta con prefix('cliente') + as('cliente.') desde routes/web.php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Cliente\Auth\LoginController as ClienteLogin;
use App\Http\Controllers\Cliente\Auth\FirstPasswordController;
use App\Http\Controllers\Cliente\HomeController as ClienteHome;
use App\Http\Controllers\Cliente\RegisterController;
use App\Http\Controllers\Cliente\VerificationController;
use App\Http\Controllers\Cliente\PasswordController;
use App\Http\Controllers\Cliente\StripeController;
use App\Http\Controllers\Cliente\AccountBillingController;
use App\Http\Controllers\Cliente\FacturacionController as ClienteFacturacion;

use App\Http\Controllers\Cliente\AlertasController;
use App\Http\Controllers\Cliente\ChatController;
use App\Http\Controllers\Cliente\MarketplaceController;
use App\Http\Controllers\Cliente\PerfilController;
use App\Http\Controllers\Cliente\MiCuentaController;
use App\Http\Controllers\Cliente\UiController;

// ✅ Mi cuenta / Facturas (ZIP estados de cuenta admin SOT)
use App\Http\Controllers\Cliente\MiCuenta\FacturasController;

// ✅ CSRF (solo para local)
use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;

$isLocal = app()->environment(['local', 'development', 'testing']);

// =========================
// Throttles (local más permisivo)
// =========================
$throttleLogin        = $isLocal ? 'throttle:60,1'  : 'throttle:5,1';
$throttleRegister     = $isLocal ? 'throttle:60,1'  : 'throttle:6,1';
$throttleCheckout     = $isLocal ? 'throttle:120,1' : 'throttle:10,1';
$throttleVerifyResend = $isLocal ? 'throttle:60,1'  : 'throttle:10,10';
$throttleOtpSend      = $isLocal ? 'throttle:30,1'  : 'throttle:3,5';
$throttleOtpCheck     = $isLocal ? 'throttle:60,1'  : 'throttle:6,5';
$throttlePassEmail    = $isLocal ? 'throttle:30,1'  : 'throttle:5,10';
$throttlePassReset    = $isLocal ? 'throttle:60,1'  : 'throttle:6,10';
$throttleBillingPay   = $isLocal ? 'throttle:120,1' : 'throttle:10,1';
$throttleUiTheme      = $isLocal ? 'throttle:120,1' : 'throttle:30,1';
$throttleAlerts       = $isLocal ? 'throttle:120,1' : 'throttle:30,1';
$throttleChat         = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$throttleHomeData     = $isLocal ? 'throttle:120,1' : 'throttle:30,1';

// =========================
// Helper: quitar CSRF solo en local
// =========================
$noCsrfLocal = function ($route) use ($isLocal) {
    if ($isLocal && $route) {
        $route->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }
    return $route;
};

/*
|--------------------------------------------------------------------------
| ✅ GARANTIZAR STACK WEB (SESSION/COOKIES/CSRF) PARA TODO /cliente
|--------------------------------------------------------------------------
| Esto blinda el flujo de verificación (email/telefono) que depende de session().
| Aunque cliente.php sea montado desde otro archivo/grupo, aquí aseguramos 'web'.
*/
Route::middleware(['web'])->group(function () use (
    $noCsrfLocal,
    $throttleLogin,
    $throttleRegister,
    $throttleCheckout,
    $throttleVerifyResend,
    $throttleOtpSend,
    $throttleOtpCheck,
    $throttlePassEmail,
    $throttlePassReset,
    $throttleBillingPay,
    $throttleUiTheme,
    $throttleAlerts,
    $throttleChat,
    $throttleHomeData
) {

    /*
    |----------------------------------------------------------------------
    | ROOT
    |----------------------------------------------------------------------
    */
    Route::get('/', function () {
        return auth('web')->check()
            ? redirect()->route('cliente.home')
            : redirect()->route('cliente.login');
    })->name('root');

    /*
    |----------------------------------------------------------------------
    | DEBUG (TEMP, SIN AUTH) — VALIDAR COOKIE/GUARD/SESSION EN /cliente
    |----------------------------------------------------------------------
    */
    Route::get('__debug', function (Request $request) {

        $inCookie = $request->cookie(config('session.cookie'));

        return response()->json([
            'path'   => $request->path(),
            'host'   => $request->getHost(),
            'method' => $request->method(),

            'config' => [
                'session_driver' => config('session.driver'),
                'session_cookie' => config('session.cookie'),
                'session_table'  => config('session.table'),
                'session_conn'   => config('session.connection'),
                'default_guard'  => config('auth.defaults.guard'),
                'web_provider'   => config('auth.guards.web.provider'),
                'provider_model_clientes' => config('auth.providers.clientes.model'),
            ],

            'session_runtime' => [
                'has_session' => $request->hasSession(),
                'session_id'  => $request->hasSession() ? $request->session()->getId() : null,
                'csrf_token'  => $request->hasSession() ? $request->session()->token() : null,
            ],

            'cookies' => [
                'expected_cookie_name' => config('session.cookie'),
                'has_expected_cookie'  => $inCookie !== null,
            ],

            'auth' => [
                'auth_web_check' => auth('web')->check(),
                'user_id'        => auth('web')->id(),
            ],
        ]);
    })->name('debug.public');

    /*
    |----------------------------------------------------------------------
    | DEBUG SESSION (TEMP) — VALIDAR COOKIE/GUARD/SESSION EN CONTEXTO /cliente
    |----------------------------------------------------------------------
    | IMPORTANTE:
    | - Se ejecuta bajo /cliente/* para que PortalSessionBootstrap aplique
    |   session.cookie = p360_client_session y auth.defaults.guard = web.
    | - Protegida con auth:web para verla ya logueado.
    */
    Route::get('__session', function () {
        return response()->json([
            'auth_web' => auth('web')->check(),
            'user_id'  => auth('web')->id(),
            'session'  => [
                'cliente.cuenta_id'   => session('cliente.cuenta_id'),
                'cliente.account_id'  => session('cliente.account_id'),
                'client.cuenta_id'    => session('client.cuenta_id'),
                'client.account_id'   => session('client.account_id'),
                'cuenta_id'           => session('cuenta_id'),
                'account_id'          => session('account_id'),
                'client_cuenta_id'    => session('client_cuenta_id'),
                'client_account_id'   => session('client_account_id'),
            ],
            'config' => [
                'session_driver' => config('session.driver'),
                'session_cookie' => config('session.cookie'),
                'session_table'  => config('session.table'),
                'session_conn'   => config('session.connection'),
                'default_guard'  => config('auth.defaults.guard'),
                'web_provider'   => config('auth.guards.web.provider'),
                'provider_model' => config('auth.providers.clientes.model'),
            ],
        ]);
    })->middleware(['auth:web'])->name('debug.session');

    /*
    |----------------------------------------------------------------------
    | ✅ PAYWALL (SIN LOGIN) — redirige a Stripe Checkout PRO
    |----------------------------------------------------------------------
    | Regla: desde el primer login válido, si is_blocked=1, NO mostramos mensaje,
    | redirigimos directo a Stripe Checkout (mensual/anual). Se desbloquea solo vía webhook.
    */
    Route::get('paywall', function (Request $request) {
        $accountId = (int) $request->session()->get('paywall.account_id', 0);
        $cycle     = strtolower((string) $request->session()->get('paywall.cycle', 'mensual'));
        $email     = (string) $request->session()->get('paywall.email', '');

        if ($accountId <= 0) {
            return redirect()->route('cliente.login');
        }

        $cycle = ($cycle === 'anual' || $cycle === 'annual') ? 'anual' : 'mensual';

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
    |----------------------------------------------------------------------
    | PDF / PAGO PÚBLICOS (SIN LOGIN) — CONTROLADOS EN CONTROLLER
    |----------------------------------------------------------------------
    | ✅ FIX:
    | - NO usar middleware 'signed' aquí (hay flujos sin query signature).
    | - La validación se hace en el Controller: si no hay sesión, exige firma.
    */
    Route::get('billing/statement/public-pdf/{accountId}/{period}', [AccountBillingController::class, 'publicPdf'])
        ->whereNumber('accountId')
        ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
        ->name('billing.publicPdf');

    Route::get('billing/statement/public-pdf-inline/{accountId}/{period}', [AccountBillingController::class, 'publicPdfInline'])
        ->whereNumber('accountId')
        ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
        ->name('billing.publicPdfInline');

    Route::get('billing/statement/public-pay/{accountId}/{period}', [AccountBillingController::class, 'publicPay'])
        ->whereNumber('accountId')
        ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
        ->name('billing.publicPay');

    /*
    |----------------------------------------------------------------------
    | GUARDAR PERFIL FACTURACIÓN (legacy/aux)
    |----------------------------------------------------------------------
    */
    Route::post('billing/profile/save', [AccountBillingController::class, 'saveBillingProfile'])
        ->name('billing.profile.save');

    /*
    |----------------------------------------------------------------------
    | ✅ STRIPE CHECKOUT PRO (PÚBLICO) — GET
    |----------------------------------------------------------------------
    */
    Route::get('checkout/pro/mensual', [StripeController::class, 'checkoutMonthly'])
        ->middleware($throttleCheckout)
        ->name('checkout.pro.monthly');

    Route::get('checkout/pro/anual', [StripeController::class, 'checkoutAnnual'])
        ->middleware($throttleCheckout)
        ->name('checkout.pro.annual');

    /*
    |----------------------------------------------------------------------
    | STRIPE CALLBACKS
    |----------------------------------------------------------------------
    */
    Route::get('checkout/success', [StripeController::class, 'success'])->name('checkout.success');
    Route::get('checkout/cancel',  [StripeController::class, 'cancel'])->name('checkout.cancel');

    /*
    |----------------------------------------------------------------------
    | INVITADOS (GUEST)
    |----------------------------------------------------------------------
    */
    Route::middleware(['guest:web'])->group(function () use (
        $noCsrfLocal,
        $throttleLogin,
        $throttleRegister,
        $throttleVerifyResend
    ) {

        /*
        |------------------------------------------------------------------
        | AUTH
        |------------------------------------------------------------------
        */
        Route::get('login', [ClienteLogin::class, 'showLogin'])->name('login');

        $postLogin = Route::post('login', [ClienteLogin::class, 'login'])
            ->middleware($throttleLogin)
            ->name('login.do');

        $noCsrfLocal($postLogin);

        /*
        |------------------------------------------------------------------
        | REGISTRO
        |------------------------------------------------------------------
        */
        Route::get('registro', [RegisterController::class, 'showFree'])->name('registro.free');

        $rFree = Route::post('registro', [RegisterController::class, 'storeFree'])
            ->middleware($throttleRegister)
            ->name('registro.free.do');

        Route::get('registro/pro', [RegisterController::class, 'showPro'])->name('registro.pro');

        $rPro = Route::post('registro/pro', [RegisterController::class, 'storePro'])
            ->middleware($throttleRegister)
            ->name('registro.pro.do');

        $noCsrfLocal($rFree);
        $noCsrfLocal($rPro);

        /*
        |------------------------------------------------------------------
        | REENVIAR VERIFICACIÓN EMAIL
        |------------------------------------------------------------------
        */
        Route::get('verificar/email/reenviar', [VerificationController::class, 'showResendEmail'])
            ->name('verify.email.resend');

        $resendEmail = Route::post('verificar/email/reenviar', [VerificationController::class, 'resendEmail'])
            ->middleware($throttleVerifyResend)
            ->name('verify.email.resend.do');

        $noCsrfLocal($resendEmail);

        /*
        |------------------------------------------------------------------
        | VERIFICACIÓN EMAIL (TOKEN)
        |------------------------------------------------------------------
        */
        Route::get('verificar/email/{token}', [VerificationController::class, 'verifyEmail'])
            ->where('token', '[A-Za-z0-9\-\_\.]+')
            ->name('verify.email.token');

        /*
        |------------------------------------------------------------------
        | VERIFICACIÓN EMAIL (SIGNED LINK)
        |------------------------------------------------------------------
        */
        Route::get('verificar/email/link', [VerificationController::class, 'verifyEmailSigned'])
            ->middleware('signed')
            ->name('verify.email.signed');

        

        /*
        |------------------------------------------------------------------
        | RESET PASSWORD (si existe en tu controller)
        |------------------------------------------------------------------
        | Nota: si PasswordController no se usa, puedes quitar estas rutas.
        */
        // Route::get('password/forgot', [PasswordController::class, 'forgot'])->name('password.forgot');
        // Route::post('password/email', [PasswordController::class, 'email'])->middleware($throttlePassEmail)->name('password.email');
        // Route::get('password/reset/{token}', [PasswordController::class, 'reset'])->name('password.reset');
        // Route::post('password/reset', [PasswordController::class, 'update'])->middleware($throttlePassReset)->name('password.update');
    });

    /*
    |----------------------------------------------------------------------
    | ✅ VERIFICACIÓN TELÉFONO (OTP) — DISPONIBLE CON SESSION WEB
    |----------------------------------------------------------------------
    | IMPORTANTÍSIMO:
    | - NO va en guest:web (porque desde login normal ya no sería guest).
    | - NO va forzado a auth:web (porque el flujo por email puede llegar sin auth).
    | - Requiere SIEMPRE stack 'web' para que session() y auth() funcionen.
    |
    | FIX Laravel 12:
    | - Evita Route::group(Closure) (TypeError). Usamos middleware([])->group(...)
    */
    Route::middleware([])->group(function () use ($noCsrfLocal, $throttleOtpSend, $throttleOtpCheck) {

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

        $noCsrfLocal($updPhone);
        $noCsrfLocal($sendOtp);
        $noCsrfLocal($checkOtp);
    });

    /*
    |----------------------------------------------------------------------
    | PRIMER PASSWORD (POST-LOGIN)
    |----------------------------------------------------------------------
    */
    Route::middleware(['auth:web'])->group(function () use ($noCsrfLocal) {

        Route::get('password/first', [FirstPasswordController::class, 'show'])->name('password.first');

        $firstPassStore = Route::post('password/first', [FirstPasswordController::class, 'store'])
            ->name('password.first.store');

        $firstPassDo = Route::post('password/first/do', [FirstPasswordController::class, 'store'])
            ->name('password.first.do');

        $noCsrfLocal($firstPassStore);
        $noCsrfLocal($firstPassDo);
    });

    /*
    |----------------------------------------------------------------------
    | UI helpers (demo-mode, theme, etc.)
    |----------------------------------------------------------------------
    */
    Route::middleware(['auth:web'])->group(function () use ($noCsrfLocal, $throttleUiTheme) {

        $demoPost = Route::post('ui/demo-mode', [UiController::class, 'demoMode'])
            ->name('ui.demo_mode');

        Route::get('ui/demo-mode', [UiController::class, 'demoModeGet'])
            ->name('ui.demo_mode.get');

        $noCsrfLocal($demoPost);

        // Theme switch (si existe route/controller)
        // Route::post('ui/theme/switch', [UiController::class, 'switchTheme'])
        //     ->middleware($throttleUiTheme)
        //     ->name('ui.theme.switch');
    });

    /*
    |----------------------------------------------------------------------
    | LEGAL (PÚBLICO)
    |----------------------------------------------------------------------
    */
    Route::view('terminos', 'legal.terminos')->name('terminos');

    /*
    |----------------------------------------------------------------------
    | ÁREA AUTENTICADA
    |----------------------------------------------------------------------
    | ✅ FIX REAL:
    | - hidratar módulos desde admin SOT para que el cliente respete ON/OFF
    | - forzar session.cliente por consistencia
    */
    Route::middleware(['session.cliente', 'auth:web', 'account.active', 'cliente.hydrate_modules'])
        ->group(function () use (
            $throttleBillingPay,
            $throttleAlerts,
            $throttleChat,
            $throttleHomeData
        ) {

            /*
            |------------------------------------------------------------------
            | HOME
            |------------------------------------------------------------------
            */
            Route::get('home', [ClienteHome::class, 'index'])->name('home');

            /*
            |------------------------------------------------------------------
            | PERFIL (Cliente)
            |------------------------------------------------------------------
            */
            Route::prefix('perfil')->name('perfil.')->group(function () {

                Route::get('/', [PerfilController::class, 'index'])->name('index');
                Route::get('show', [PerfilController::class, 'index'])->name('show');

                Route::match(['POST', 'PUT'], 'password', [PerfilController::class, 'updatePassword'])
                    ->name('password.update');

                Route::match(['POST', 'PUT'], 'phone', [PerfilController::class, 'updatePhone'])
                    ->name('phone.update');

                Route::match(['POST', 'PUT'], 'avatar', [PerfilController::class, 'uploadAvatar'])
                    ->name('avatar.update');

                Route::get('settings', [PerfilController::class, 'settings'])
                    ->name('settings');
            });

            Route::get('perfil', [PerfilController::class, 'index'])->name('perfil');

            /*
            |------------------------------------------------------------------
            | MI CUENTA (Cliente)
            |------------------------------------------------------------------
            */
            Route::prefix('mi-cuenta')->name('mi_cuenta.')->group(function () {

                Route::get('/', [MiCuentaController::class, 'index'])->name('index');

                Route::get('pagos', [MiCuentaController::class, 'pagos'])->name('pagos');

                Route::post('profile/update', [MiCuentaController::class, 'profileUpdate'])->name('profile.update');
                Route::post('security/update', [MiCuentaController::class, 'securityUpdate'])->name('security.update');
                Route::post('preferences/update', [MiCuentaController::class, 'preferencesUpdate'])->name('preferences.update');
                Route::post('brand/update', [MiCuentaController::class, 'brandUpdate'])->name('brand.update');
                Route::post('billing/update', [MiCuentaController::class, 'billingUpdate'])->name('billing.update');

                Route::get('contratos', [MiCuentaController::class, 'contratosIndex'])->name('contratos.index');
                Route::get('contratos/{contract}', [MiCuentaController::class, 'showContract'])->whereNumber('contract')->name('contratos.show');
                Route::post('contratos/{contract}/sign', [MiCuentaController::class, 'signContract'])->whereNumber('contract')->name('contratos.sign');
                Route::get('contratos/{contract}/pdf', [MiCuentaController::class, 'downloadSignedPdf'])->whereNumber('contract')->name('contratos.pdf');

                /*
                |----------------------------------------------------------------
                | ✅ FACTURAS (Mi cuenta) — SOLICITUDES DE ESTADO DE CUENTA
                |----------------------------------------------------------------
                */
                Route::get('facturas', [FacturasController::class, 'index'])->name('facturas.index');
                Route::post('facturas', [FacturasController::class, 'store'])->name('facturas.store');
                Route::get('facturas/{id}', [FacturasController::class, 'show'])->whereNumber('id')->name('facturas.show');
                Route::get('facturas/{id}/download', [FacturasController::class, 'downloadZip'])->whereNumber('id')->name('facturas.download');
            });

            /*
            |------------------------------------------------------------------
            | ESTADO DE CUENTA
            |------------------------------------------------------------------
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
            |------------------------------------------------------------------
            | PAGO
            |------------------------------------------------------------------
            */
            Route::match(['GET', 'POST'], 'billing/statement/pay/{period}', [AccountBillingController::class, 'pay'])
                ->middleware($throttleBillingPay)
                ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
                ->name('billing.pay');

            /*
            |------------------------------------------------------------------
            | FACTURAR (Solicitud y Descarga ZIP)
            |------------------------------------------------------------------
            */
            Route::post('billing/statement/factura/{period}', [AccountBillingController::class, 'requestInvoice'])
                ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
                ->name('billing.factura.request');

            Route::get('billing/statement/factura/{period}/download', [AccountBillingController::class, 'downloadInvoiceZip'])
                ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
                ->name('billing.factura.download');

            /*
            |------------------------------------------------------------------
            | LOGOUT
            |------------------------------------------------------------------
            */
            Route::post('logout', [ClienteLogin::class, 'logout'])->name('logout');
        });

    /*
    |----------------------------------------------------------------------
    | STRIPE WEBHOOK
    |----------------------------------------------------------------------
    */
    Route::post('stripe/webhook', [StripeController::class, 'webhook'])
        ->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])
        ->name('stripe.webhook');

}); // ✅ END middleware(['web']) group (todo /cliente)
