<?php
// C:\wamp64\www\pactopia360_erp\routes\cliente.php
// PACTOPIA360 · Cliente routes (CORE)
//
// ✅ Canónico: portal cliente general (auth, perfil, mi-cuenta, billing, etc.)
// ✅ SAT completo vive en routes/cliente_sat.php (montado por RouteServiceProvider dentro del grupo "cliente").
// ✅ Este archivo NO debe definir rutas sat.* para evitar duplicados.
//
// Arquitectura correcta (cache-safe):
// - RouteServiceProvider monta este archivo con:
//   middleware('cliente') + prefix('cliente') + as('cliente.')
// - NO se monta desde routes/web.php
// - El grupo middleware "cliente" (Kernel) incluye ClientSessionConfig ANTES de StartSession.
//   Por eso NO forzamos "web" aquí.

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
use App\Http\Controllers\Cliente\RfcsController as ClienteRfcs;
use App\Http\Controllers\Cliente\ProductosController as ClienteProductos;

use App\Http\Controllers\Cliente\AlertasController;
use App\Http\Controllers\Cliente\ChatController;
use App\Http\Controllers\Cliente\MarketplaceController;
use App\Http\Controllers\Cliente\PerfilController;
use App\Http\Controllers\Cliente\MiCuentaController;
use App\Http\Controllers\Cliente\UiController;
use App\Http\Controllers\Cliente\ImpersonateController;

use App\Http\Middleware\ClientSessionConfig;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Controllers\Cliente\ModulosController;


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
| ROOT
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth('web')->check()
        ? redirect()->route('cliente.home')
        : redirect()->route('cliente.login');
})->name('root');

/*
|--------------------------------------------------------------------------
| DEBUG (TEMP, SIN AUTH) — validar cookie/guard/sesión en /cliente
|--------------------------------------------------------------------------
*/
Route::get('__debug', function (Request $request) {

    $cookieName = (string) config('session.cookie');
    $inCookie   = $request->cookie($cookieName);

    return response()->json([
        'path'   => $request->path(),
        'host'   => $request->getHost(),
        'method' => $request->method(),

        'config' => [
            'session_driver' => config('session.driver'),
            'session_cookie' => $cookieName,
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
            'expected_cookie_name' => $cookieName,
            'has_expected_cookie'  => $inCookie !== null,
        ],

        'auth' => [
            'auth_web_check' => auth('web')->check(),
            'user_id'        => auth('web')->id(),
        ],
    ]);
})->name('debug.public');

/*
|--------------------------------------------------------------------------
| DEBUG SESSION (TEMP) — validar llaves de sesión usadas por billing
|--------------------------------------------------------------------------
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

            // p360 (módulos)
            'p360.account_id'        => session('p360.account_id'),
            'p360.modules_version'   => session('p360.modules_version'),
            'p360.modules_synced_at' => session('p360.modules_synced_at'),
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
|--------------------------------------------------------------------------
| ✅ IMPERSONATE (ADMIN -> CLIENTE)
|--------------------------------------------------------------------------
| - NO requiere auth:web (aquí se hace login del cliente)
| - Protegido con signed + throttle
| - ✅ FIX: NO duplicar rutas, NO duplicar names.
| - STOP canónico: POST (logout), GET solo compat con OTRO name.
*/
Route::get('impersonate/{token}', [ImpersonateController::class, 'consume'])
    ->middleware(['signed', 'throttle:30,1'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('impersonate.consume');

// STOP real (POST)
Route::post('impersonate/stop', [ImpersonateController::class, 'stop'])
    ->name('impersonate.stop');

// Compat (GET) sin colisionar el name canónico
Route::get('impersonate/stop', [ImpersonateController::class, 'stop'])
    ->name('impersonate.stop.get');

/*
|--------------------------------------------------------------------------
| ✅ PAYWALL (SIN LOGIN) — redirige a Stripe Checkout PRO
|--------------------------------------------------------------------------
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
|--------------------------------------------------------------------------
| PDF / PAGO PÚBLICOS (SIN LOGIN) — firmados
|--------------------------------------------------------------------------
| Este archivo vive bajo el grupo "cliente" (RouteServiceProvider).
| Hay que quitar middleware por CLASE para evitar loops de sesión.
*/
Route::get('billing/statement/public-pdf/{accountId}/{period}', [AccountBillingController::class, 'publicPdf'])
    ->withoutMiddleware([
        ClientSessionConfig::class,      // ✅ corta loop de sesión cliente
        EnsureAccountIsActive::class,    // ✅ evita redirecciones a paywall/login
    ])
    ->middleware(['signed', 'throttle:60,1'])
    ->whereNumber('accountId')
    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
    ->name('billing.publicPdf');

Route::get('billing/statement/public-pdf-inline/{accountId}/{period}', [AccountBillingController::class, 'publicPdfInline'])
    ->withoutMiddleware([
        ClientSessionConfig::class,
        EnsureAccountIsActive::class,
    ])
    ->middleware(['signed', 'throttle:60,1'])
    ->whereNumber('accountId')
    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
    ->name('billing.publicPdfInline');

Route::get('billing/statement/public-pay/{accountId}/{period}', [AccountBillingController::class, 'publicPay'])
    ->withoutMiddleware([
        ClientSessionConfig::class,
        EnsureAccountIsActive::class,
    ])
    ->middleware(['signed', 'throttle:60,1'])
    ->whereNumber('accountId')
    ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
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
Route::get('checkout/success', [StripeController::class, 'success'])->name('checkout.success');
Route::get('checkout/cancel',  [StripeController::class, 'cancel'])->name('checkout.cancel');

/*
|--------------------------------------------------------------------------
| INVITADOS (GUEST)
|--------------------------------------------------------------------------
*/
Route::middleware(['guest:web'])->group(function () use (
    $noCsrfLocal,
    $throttleLogin,
    $throttleRegister,
    $throttleVerifyResend,
    $throttlePassEmail,
    $throttlePassReset
) {


    // AUTH
    Route::get('login', [ClienteLogin::class, 'showLogin'])->name('login');

    $postLogin = Route::post('login', [ClienteLogin::class, 'login'])
        ->middleware($throttleLogin)
        ->name('login.do');

    $noCsrfLocal($postLogin);

    // REGISTRO
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

    // REENVIAR VERIFICACIÓN EMAIL
    Route::get('verificar/email/reenviar', [VerificationController::class, 'showResendEmail'])
        ->name('verify.email.resend');

    $resendEmail = Route::post('verificar/email/reenviar', [VerificationController::class, 'resendEmail'])
        ->middleware($throttleVerifyResend)
        ->name('verify.email.resend.do');

    $noCsrfLocal($resendEmail);

    // VERIFICACIÓN EMAIL (TOKEN)
    Route::get('verificar/email/{token}', [VerificationController::class, 'verifyEmail'])
        ->where('token', '[A-Za-z0-9\-\_\.]+')
        ->name('verify.email.token');

    // VERIFICACIÓN EMAIL (SIGNED LINK)
    Route::get('verificar/email/link', [VerificationController::class, 'verifyEmailSigned'])
        ->middleware('signed')
        ->name('verify.email.signed');

    // ==========================================================
    // ✅ RESET PASSWORD (CLIENTE) — PRO (email o RFC) + TTL + throttle
    // ==========================================================
    Route::get('password/forgot', [PasswordController::class, 'showLinkRequestForm'])
        ->name('password.forgot');

    $passEmail = Route::post('password/email', [PasswordController::class, 'sendResetLinkEmail'])
        ->middleware($throttlePassEmail)
        ->name('password.email');

    Route::get('password/reset/{token}', [PasswordController::class, 'showResetForm'])
        ->where('token', '[A-Za-z0-9\-\_]+')
        ->name('password.reset');

    $passReset = Route::post('password/reset', [PasswordController::class, 'reset'])
        ->middleware($throttlePassReset)
        ->name('password.update');

    // En local puedes quitar CSRF si tu flujo lo requiere (tú ya lo usas en login/registro)
    $noCsrfLocal($passEmail);
    $noCsrfLocal($passReset);

});

/*
|--------------------------------------------------------------------------
| ✅ VERIFICACIÓN TELÉFONO (OTP)
|--------------------------------------------------------------------------
| - No va en guest:web (porque puede llegar estando logueado)
| - No va forzado a auth:web (porque el flujo por email puede llegar sin auth)
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
|--------------------------------------------------------------------------
| PRIMER PASSWORD (POST-LOGIN)
|--------------------------------------------------------------------------
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
|--------------------------------------------------------------------------
| UI helpers (demo-mode, theme, etc.)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:web'])->group(function () use ($noCsrfLocal) {

    $demoPost = Route::post('ui/demo-mode', [UiController::class, 'demoMode'])
        ->name('ui.demo_mode');

    Route::get('ui/demo-mode', [UiController::class, 'demoModeGet'])
        ->name('ui.demo_mode.get');

    $noCsrfLocal($demoPost);
});

/*
|--------------------------------------------------------------------------
| LEGAL (PÚBLICO)
|--------------------------------------------------------------------------
*/
Route::view('terminos', 'legal.terminos')->name('terminos');

/*
|--------------------------------------------------------------------------
| ✅ ÁREA AUTENTICADA
|--------------------------------------------------------------------------
| IMPORTANTE:
| - NO usar session.cliente aquí.
|   Ya viene ClientSessionConfig dentro del grupo middleware "cliente"
|   (y además debe correr ANTES de StartSession).
*/
Route::middleware(['auth:web', 'account.active'])

    ->group(function () use ($throttleBillingPay, $throttleAlerts, $throttleChat, $throttleHomeData) {

        // HOME
        Route::get('home', [ClienteHome::class, 'index'])->name('home');

        // PERFIL
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

        // MI CUENTA
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

            // FACTURAS (Mi cuenta) — SOLICITUDES DE ESTADO DE CUENTA
            Route::get('facturas', [FacturasController::class, 'index'])->name('facturas.index');
            Route::post('facturas', [FacturasController::class, 'store'])->name('facturas.store');
            Route::get('facturas/{id}', [FacturasController::class, 'show'])->whereNumber('id')->name('facturas.show');
            Route::get('facturas/{id}/download', [FacturasController::class, 'downloadZip'])->whereNumber('id')->name('facturas.download');
        });

        // ESTADO DE CUENTA
        Route::get('estado-de-cuenta', [AccountBillingController::class, 'statement'])
            ->name('estado_cuenta');

        Route::get('billing/statement/pdf-inline/{period}', [AccountBillingController::class, 'pdfInline'])
            ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
            ->name('billing.pdfInline');

        Route::get('billing/statement/pdf/{period}', [AccountBillingController::class, 'pdf'])
            ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
            ->name('billing.pdf');

        // PAGO
        Route::match(['GET', 'POST'], 'billing/statement/pay/{period}', [AccountBillingController::class, 'pay'])
            ->middleware($throttleBillingPay)
            ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
            ->name('billing.pay');

        // FACTURAR (Solicitud y Descarga ZIP)
        Route::post('billing/statement/factura/{period}', [AccountBillingController::class, 'requestInvoice'])
            ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
            ->name('billing.factura.request');

        Route::get('billing/statement/factura/{period}/download', [AccountBillingController::class, 'downloadInvoiceZip'])
            ->where(['period' => '\d{4}-(0[1-9]|1[0-2])'])
            ->name('billing.factura.download');

        /// LOGOUT
        // ✅ Permitir GET /cliente/logout (link) -> auto-POST con CSRF (seguro, cache-safe)
        Route::get('logout', function () {
            return response()->view('cliente.auth.logout');
        })->name('logout.get');

        // ✅ Logout real (POST)
        Route::post('logout', [ClienteLogin::class, 'logout'])->name('logout');

        /*
        |--------------------------------------------------------------------------
        | RFC / EMISORES CLIENTE
        |--------------------------------------------------------------------------
        | Módulo independiente para centralizar:
        | - Alta, baja y cambios de RFC
        | - Configuración fiscal del emisor
        | - FIEL / CSD
        | - Series y folios
        | - Estado activo/inactivo
        |
        | IMPORTANTE:
        | Los RFC vienen de sat_credentials y usan UUID, por eso NO debe usarse
        | whereNumber('rfc') ni whereNumber('serie').
        | Este módulo NO pertenece al Portal Descargas SAT.
        */
        Route::prefix('rfcs')->name('rfcs.')->group(function () {
            Route::get('/', [ClienteRfcs::class, 'index'])->name('index');
            Route::post('/', [ClienteRfcs::class, 'store'])->name('store');

            Route::get('/{rfc}', [ClienteRfcs::class, 'show'])->name('show');

            Route::put('/{rfc}', [ClienteRfcs::class, 'update'])->name('update');

            Route::delete('/{rfc}', [ClienteRfcs::class, 'destroy'])->name('destroy');

            Route::post('/{rfc}/toggle', [ClienteRfcs::class, 'toggle'])->name('toggle');

            Route::post('/{rfc}/certificados', [ClienteRfcs::class, 'storeCertificados'])->name('certificados.store');

            Route::post('/{rfc}/series', [ClienteRfcs::class, 'storeSerie'])->name('series.store');

            Route::put('/{rfc}/series/{serie}', [ClienteRfcs::class, 'updateSerie'])->name('series.update');

            Route::delete('/{rfc}/series/{serie}', [ClienteRfcs::class, 'destroySerie'])->name('series.destroy');
        });


        /*
        |--------------------------------------------------------------------------
        | FACTURACIÓN CLIENTE
        |--------------------------------------------------------------------------
        | Mantiene la lógica del sidebar:
        | - El acceso visual/sesión sigue dependiendo del módulo "facturacion"
        |   habilitado desde admin.
        | - Aquí solo declaramos las rutas que el sidebar ya espera.
        */
        Route::prefix('facturacion')->name('facturacion.')->group(function () {
            Route::get('/', [ClienteFacturacion::class, 'index'])->name('index');
            Route::get('/nuevo', [ClienteFacturacion::class, 'create'])->name('create');
            Route::post('/', [ClienteFacturacion::class, 'store'])->name('store');

            Route::get('/{cfdi}', [ClienteFacturacion::class, 'show'])
                ->whereNumber('cfdi')
                ->name('show');
            
            Route::get('/{cfdi}/editar', [ClienteFacturacion::class, 'edit'])
                ->whereNumber('cfdi')
                ->name('edit');

            Route::put('/{cfdi}/editar', [ClienteFacturacion::class, 'actualizar'])
                ->whereNumber('cfdi')
                ->name('actualizar');
                
            Route::delete('/{cfdi}', [ClienteFacturacion::class, 'destroy'])
                ->whereNumber('cfdi')
                ->name('destroy');

            Route::post('/{cfdi}/timbrar', [ClienteFacturacion::class, 'timbrar'])
                ->whereNumber('cfdi')
                ->name('timbrar');

            Route::get('/receptores/{receptor}', [ClienteFacturacion::class, 'receptorShow'])
                ->whereNumber('receptor')
                ->name('receptores.show');

            Route::post('/receptores', [ClienteFacturacion::class, 'receptorStore'])
                ->name('receptores.store');

            Route::put('/receptores/{receptor}', [ClienteFacturacion::class, 'receptorUpdate'])
                ->whereNumber('receptor')
                ->name('receptores.update');

            Route::post('/assistant', [ClienteFacturacion::class, 'assistant'])
                ->name('assistant');

            Route::get('/catalogs', [ClienteFacturacion::class, 'catalogs'])
                ->name('catalogs');

            Route::get('/postal-code/{cp}', [ClienteFacturacion::class, 'postalCode'])
                ->where('cp', '[0-9]{5}')
                ->name('postal-code');
            
            Route::get('/locations/countries', [ClienteFacturacion::class, 'locationCountries'])
                ->name('locations.countries');

            Route::get('/locations/states', [ClienteFacturacion::class, 'locationStates'])
                ->name('locations.states');

            Route::get('/locations/municipalities', [ClienteFacturacion::class, 'locationMunicipalities'])
                ->name('locations.municipalities');

            Route::get('/locations/colonies', [ClienteFacturacion::class, 'locationColonies'])
                ->name('locations.colonies');

            Route::get('/kpis', [ClienteFacturacion::class, 'kpis'])->name('kpis');
            Route::get('/series', [ClienteFacturacion::class, 'series'])->name('series');
            Route::get('/export', [ClienteFacturacion::class, 'export'])->name('export');
        });

        /*
        |--------------------------------------------------------------------------
        | PRODUCTOS CLIENTE
        |--------------------------------------------------------------------------
        */
        Route::prefix('productos')->name('productos.')->group(function () {
            Route::get('/', [ClienteProductos::class, 'index'])->name('index');
            Route::post('/', [ClienteProductos::class, 'store'])->name('store');
            Route::put('/{producto}', [ClienteProductos::class, 'update'])->name('update');
            Route::delete('/{producto}', [ClienteProductos::class, 'destroy'])->name('destroy');
        });

        /*
        |--------------------------------------------------------------------------
        | Módulos cliente
        |--------------------------------------------------------------------------
        | SAT sigue viviendo en routes/cliente_sat.php
        | CFDI Nómina vive dentro de RH, NO como módulo separado
        | Estos módulos se sirven desde controller para poder crecer
        | con KPIs, validaciones, datos reales y rediseño uniforme.
        */
        Route::prefix('modulos')->name('modulos.')->controller(ModulosController::class)->group(function () {
            Route::get('/crm', 'crm')->name('crm');
            Route::get('/inventario', 'inventario')->name('inventario');
            Route::get('/ventas', 'ventas')->name('ventas');
            Route::get('/reportes', 'reportes')->name('reportes');
            Route::get('/recursos-humanos', 'rh')->name('rh');
            Route::get('/timbres-hits', 'timbres')->name('timbres');

            Route::post('/timbres-hits/facturotopia/test', 'facturotopiaTest')
                ->name('timbres.facturotopia.test');
        });
    });

/*
|--------------------------------------------------------------------------
| STRIPE WEBHOOK
|--------------------------------------------------------------------------
*/
Route::post('stripe/webhook', [StripeController::class, 'webhook'])
    ->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class])
    ->name('stripe.webhook');
