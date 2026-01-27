<?php
// C:\wamp64\www\pactopia360_erp\routes\admin.php
// PACTOPIA360 · ADMIN routes (SOT)
// ✅ Mejoras incluidas (v2026-01-21):
// - FIX 419/logout: añade GET /admin/logout (compat) + POST /admin/logout (real)
// - FIX “arrastre” de sesión: AdminSessionConfig aplicado en guest/auth
// - FIX: evita 500 si NO existe BillingStatementsHubController::scheduleEmail (route condicional)
// - Orden + cierre de braces consistente (route:cache safe)
// - SAT Admin (prices/discounts) queda dentro de auth:admin (como debe ser)
// - CSRF bypass en local solo en rutas POST críticas
// ✅ + /admin/_cfg para diagnóstico rápido del contexto admin (cookie/guard/driver)

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Controladores ADMIN
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\UiController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ConfigController;
use App\Http\Controllers\Admin\ReportesController;
use App\Http\Controllers\Admin\ClientesController;
use App\Http\Controllers\Admin\QaController;
use App\Http\Controllers\Admin\Soporte\ResetClientePasswordController;
use App\Http\Controllers\Admin\Soporte\EmailClienteCredentialsController;

// Billing suite (Admin)
use App\Http\Controllers\Admin\Billing\PriceCatalogController;
use App\Http\Controllers\Admin\Billing\AccountLicensesController;
use App\Http\Controllers\Admin\Billing\PaymentsController;
use App\Http\Controllers\Admin\Billing\InvoiceRequestsController;
use App\Http\Controllers\Admin\Billing\AccountsController;

// Estados de cuenta (legacy)
use App\Http\Controllers\Admin\Billing\BillingStatementsController;

// HUB nuevo (estados + pagos + emails + facturas + tracking)
use App\Http\Controllers\Admin\Billing\BillingStatementsHubController;

// Usuarios admin (módulo)
use App\Http\Controllers\Admin\Usuarios\AdministrativosController;

// SAT Admin
use App\Http\Controllers\Admin\Billing\Sat\SatDiscountCodesController as AdminSatDiscountCodesController;
use App\Http\Controllers\Admin\Billing\Sat\SatPriceRulesController as AdminSatPriceRulesController;
use App\Http\Controllers\Admin\Sat\SatCredentialsController;

// SAT Ops (Backoffice)
use App\Http\Controllers\Admin\Sat\Ops\SatOpsController;
use App\Http\Controllers\Admin\Sat\Ops\SatOpsCredentialsController;
use App\Http\Controllers\Admin\Sat\Ops\SatOpsDownloadsController;
use App\Http\Controllers\Admin\Sat\Ops\SatOpsManualRequestsController;
use App\Http\Controllers\Admin\Sat\Ops\SatOpsPaymentsController;

/*
|--------------------------------------------------------------------------
| CSRF middlewares (para quitar en local en algunos POST)
|--------------------------------------------------------------------------
*/
use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;

/*
|--------------------------------------------------------------------------
| ENV + throttles
|--------------------------------------------------------------------------
*/
$isLocal = app()->environment(['local', 'development', 'testing']);

$thrLogin       = $isLocal ? 'throttle:60,1'  : 'throttle:5,1';
$thrUiHeartbeat = $isLocal ? 'throttle:120,1' : 'throttle:60,1';
$thrUiLog       = $isLocal ? 'throttle:480,1' : 'throttle:240,1';
$thrHomeStats   = $isLocal ? 'throttle:120,1' : 'throttle:60,1';
$thrUiDiag      = $isLocal ? 'throttle:60,1'  : 'throttle:30,1';
$thrUiBotAsk    = $isLocal ? 'throttle:60,1'  : 'throttle:30,1';
$thrDevQa       = $isLocal ? 'throttle:120,1' : 'throttle:60,1';
$thrDevPosts    = $isLocal ? 'throttle:60,1'  : 'throttle:30,1';
$thrAdminPosts  = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';

/*
|--------------------------------------------------------------------------
| Helper permisos → middleware 'can:perm,<clave>'
|--------------------------------------------------------------------------
*/
if (!function_exists('perm_mw')) {
    function perm_mw(string|array $perm): array
    {
        // En local: no forzamos permisos para poder trabajar rápido.
        if (app()->environment(['local', 'development', 'testing'])) {
            return [];
        }

        $perms = is_array($perm) ? $perm : [$perm];
        return array_map(static fn ($p) => 'can:perm,' . $p, $perms);
    }
}

/*
|--------------------------------------------------------------------------
| Placeholder rápido (si falta una vista admin)
|--------------------------------------------------------------------------
*/
if (!function_exists('admin_placeholder_view')) {
    function admin_placeholder_view(string $title, string $company = 'PACTOPIA 360')
    {
        if (view()->exists('admin.generic.placeholder')) {
            return view('admin.generic.placeholder', compact('title', 'company'));
        }

        $html = "<!doctype html><meta charset='utf-8'><title>{$title}</title>
                 <div style='font:16px system-ui;padding:20px'>
                   <h1 style='margin:0 0 8px'>{$title}</h1>
                   <p style='color:#64748b'>Empresa: {$company}</p>
                   <p style='margin-top:14px'>Vista placeholder. Implementación pendiente.</p>
                 </div>";

        return response($html, 200);
    }
}

/*
|--------------------------------------------------------------------------
| ✅ Tracking público Billing (OPEN/CLICK) — SIN auth y SIN cookies/sesión
|--------------------------------------------------------------------------
*/
$noCookies = [
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \App\Http\Middleware\VerifyCsrfToken::class,
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
];

Route::prefix('t/billing')
    ->name('track.billing.')
    ->middleware('throttle:240,1')
    ->group(function () use ($noCookies) {

        Route::get('open/{emailId}', [BillingStatementsHubController::class, 'trackOpen'])
            ->where('emailId', '[A-Za-z0-9\-]+')
            ->withoutMiddleware($noCookies)
            ->name('open');

        Route::get('open/{emailId}.gif', [BillingStatementsHubController::class, 'trackOpen'])
            ->where('emailId', '[A-Za-z0-9\-]+')
            ->withoutMiddleware($noCookies)
            ->name('open_gif');

        Route::get('click/{emailId}', [BillingStatementsHubController::class, 'trackClick'])
            ->where('emailId', '[A-Za-z0-9\-]+')
            ->withoutMiddleware($noCookies)
            ->name('click');
    });

/*
|--------------------------------------------------------------------------
| ✅ PayLink público (GET) para Estados de Cuenta (HUB)
|--------------------------------------------------------------------------
*/
Route::get('billing/statements-hub/paylink', [BillingStatementsHubController::class, 'payLink'])
    ->middleware('throttle:240,1')
    ->withoutMiddleware($noCookies)
    ->name('billing.statements_hub.paylink');

/*
|--------------------------------------------------------------------------
| UI (heartbeat, log)
|--------------------------------------------------------------------------
*/
Route::match(['GET', 'HEAD'], 'ui/heartbeat', [UiController::class, 'heartbeat'])
    ->middleware($thrUiHeartbeat)
    ->name('ui.heartbeat');

$uiLog = Route::match(['POST', 'GET'], 'ui/log', [UiController::class, 'log'])
    ->middleware($thrUiLog)
    ->name('ui.log');

if ($isLocal) {
    $uiLog->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
}

/*
|--------------------------------------------------------------------------
| ✅ Diag rápido (admin context): /admin/_cfg
|--------------------------------------------------------------------------
*/
Route::get('_cfg', function () {
    return response()->json([
        'ok'             => true,
        'auth_default'   => config('auth.defaults.guard'),
        'session_cookie' => config('session.cookie'),
        'session_driver' => config('session.driver'),
        'session_conn'   => config('session.connection'),
        'now'            => now()->toDateTimeString(),
        'admin_id'       => auth('admin')->id(),
        'admin_email'    => auth('admin')->user()?->email,
    ]);
})->middleware([\App\Http\Middleware\AdminSessionConfig::class])
  ->name('cfg');

/*
|--------------------------------------------------------------------------
| Auth admin (guest:admin + sesión aislada)
|--------------------------------------------------------------------------
*/
Route::middleware([
    'guest:admin',
    \App\Http\Middleware\AdminSessionConfig::class,
])->group(function () use ($isLocal, $thrLogin) {

    Route::get('login', [LoginController::class, 'showLogin'])->name('login');

    $loginPost = Route::post('login', [LoginController::class, 'login'])
        ->middleware($thrLogin)
        ->name('login.do');

    if ($isLocal) {
        $loginPost->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }
});

/*
|--------------------------------------------------------------------------
| Notificaciones públicas (contador)
|--------------------------------------------------------------------------
*/
Route::match(['GET', 'HEAD'], 'notificaciones/count', [NotificationController::class, 'count'])
    ->middleware('throttle:60,1')
    ->name('notificaciones.count');

/*
|--------------------------------------------------------------------------
| Área autenticada ADMIN (auth:admin + sesión aislada)
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:admin',
    \App\Http\Middleware\AdminSessionConfig::class,
])->group(function () use (
    $thrHomeStats,
    $thrUiDiag,
    $thrUiBotAsk,
    $thrDevQa,
    $thrDevPosts,
    $thrAdminPosts,
    $isLocal
) {

    /* ---------- Aliases raíz ---------- */
    Route::get('/', fn () => redirect()->route('admin.home'))->name('root');
    Route::get('dashboard', fn () => redirect()->route('admin.home'))->name('dashboard');

    /* ---------- WhoAmI admin ---------- */
    Route::get('_whoami', function () {
        $u = auth('admin')->user();

        $canAccessAdmin = false;
        if ($u) {
            $canAccessAdmin = Gate::forUser($u)->allows('access-admin');
        }

        return response()->json([
            'ok'     => (bool) $u,
            'id'     => $u?->id,
            'name'   => $u?->name ?? $u?->nombre,
            'email'  => $u?->email,
            'guard'  => 'admin',
            'now'    => now()->toDateTimeString(),
            'canAny' => [
                'access-admin' => $canAccessAdmin,
            ],
            'super'  => ($u && method_exists($u, 'isSuperAdmin'))
                ? (bool) $u->isSuperAdmin()
                : false,
        ]);
    })->name('whoami');

    /* ---------- Home ---------- */
    Route::get('home', [HomeController::class, 'index'])->name('home');

    Route::get('home/stats', [HomeController::class, 'stats'])
        ->middleware($thrHomeStats)
        ->name('home.stats');

    Route::get('home/income/{ym}', [HomeController::class, 'incomeByMonth'])
        ->where(['ym' => '\d{4}-(0[1-9]|1[0-2])'])
        ->name('home.incomeMonth');

    if (method_exists(HomeController::class, 'compare')) {
        Route::get('home/compare/{ym}', [HomeController::class, 'compare'])
            ->where(['ym' => '\d{4}-(0[1-9]|1[0-2])'])
            ->name('home.compare');
    }

    if (method_exists(HomeController::class, 'ytd')) {
        Route::get('home/ytd/{year}', [HomeController::class, 'ytd'])
            ->where(['year' => '\d{4}'])
            ->name('home.ytd');
    }

    if (method_exists(HomeController::class, 'hitsHeatmap')) {
        Route::get('home/hits-heatmap/{weeks?}', [HomeController::class, 'hitsHeatmap'])
            ->where(['weeks' => '\d+'])
            ->name('home.hitsHeatmap');
    }

    if (method_exists(HomeController::class, 'modulesTop')) {
        Route::get('home/modules-top/{months?}', [HomeController::class, 'modulesTop'])
            ->where(['months' => '\d+'])
            ->name('home.modulesTop');
    }

    if (method_exists(HomeController::class, 'plansBreakdown')) {
        Route::get('home/plans-breakdown/{months?}', [HomeController::class, 'plansBreakdown'])
            ->where(['months' => '\d+'])
            ->name('home.plansBreakdown');
    }

    if (method_exists(HomeController::class, 'export')) {
        Route::get('home/export', [HomeController::class, 'export'])->name('home.export');
    }

    /* ---------- Utilidades generales admin ---------- */
    Route::get('search', [SearchController::class, 'index'])->name('search');

    Route::get('notificaciones', [NotificationController::class, 'index'])->name('notificaciones');
    Route::get('notificaciones/list', [NotificationController::class, 'list'])->name('notificaciones.list');
    Route::post('notificaciones/read-all', [NotificationController::class, 'readAll'])->name('notificaciones.readAll');

    Route::get('ui/diag', [UiController::class, 'diag'])
        ->middleware($thrUiDiag)
        ->name('ui.diag');

    Route::post('ui/bot-ask', [UiController::class, 'botAsk'])
        ->middleware($thrUiBotAsk)
        ->name('ui.botAsk');

    /* ---------- Perfil admin ---------- */
    Route::get('perfil', [ProfileController::class, 'index'])->name('perfil');
    Route::get('perfil/edit', [ProfileController::class, 'edit'])->name('perfil.edit');
    Route::put('perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::post('perfil/password', [ProfileController::class, 'password'])->name('perfil.password');

    /* ---------- Config admin ---------- */
    Route::get('config', [ConfigController::class, 'index'])
        ->middleware(perm_mw('admin.config'))
        ->name('config.index');

    /* ---------- Reportes raíz ---------- */
    Route::get('reportes', [ReportesController::class, 'index'])
        ->middleware(perm_mw('reportes.ver'))
        ->name('reportes.index');

    /* ---------- Clientes (accounts / soporte) ---------- */
    if (class_exists(\App\Http\Controllers\Admin\ClientesController::class)) {

        Route::get('clientes', [ClientesController::class, 'index'])
            ->middleware(perm_mw('clientes.ver'))
            ->name('clientes.index');

        Route::post('clientes/{rfc}/save', [ClientesController::class, 'save'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.save');

        Route::post('clientes/{rfc}/resend-email-verification', [ClientesController::class, 'resendEmailVerification'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.resendEmailVerification');

        Route::post('clientes/{rfc}/send-phone-otp', [ClientesController::class, 'sendPhoneOtp'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.sendPhoneOtp');

        Route::post('clientes/{rfc}/force-email-verified', [ClientesController::class, 'forceEmailVerified'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forceEmailVerified');

        Route::post('clientes/{rfc}/force-phone-verified', [ClientesController::class, 'forcePhoneVerified'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forcePhoneVerified');

        // Aliases
        Route::post('clientes/{rfc}/resend-email', [ClientesController::class, 'resendEmailVerification'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.resendEmail');

        Route::post('clientes/{rfc}/send-otp', [ClientesController::class, 'sendPhoneOtp'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.sendOtp');

        Route::post('clientes/{rfc}/force-email', [ClientesController::class, 'forceEmailVerified'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forceEmail');

        Route::post('clientes/{rfc}/force-phone', [ClientesController::class, 'forcePhoneVerified'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forcePhone');

        $rp = Route::match(['GET', 'POST'], 'clientes/{rfcOrId}/reset-password', [ClientesController::class, 'resetPassword'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.resetPassword');

        if ($isLocal) {
            $rp->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        $emailCreds = Route::post('clientes/{rfc}/email-credentials', [ClientesController::class, 'emailCredentials'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.emailCredentials');

        if ($isLocal) {
            $emailCreds->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        Route::post('clientes/{rfc}/impersonate', [ClientesController::class, 'impersonate'])
            ->middleware([$thrAdminPosts, ...perm_mw(['clientes.ver', 'clientes.impersonate'])])
            ->name('clientes.impersonate');

        Route::post('clientes/impersonate-stop', [ClientesController::class, 'impersonateStop'])
            ->middleware($thrAdminPosts)
            ->name('clientes.impersonateStop');

        Route::post('clientes/impersonate/stop', [ClientesController::class, 'impersonateStop'])
            ->middleware($thrAdminPosts)
            ->name('clientes.impersonate.stop');

        Route::post('clientes/sync-to-clientes', [ClientesController::class, 'syncToClientes'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.syncToClientes');

        Route::post('clientes/bulk', [ClientesController::class, 'bulk'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.bulk');

        if (method_exists(ClientesController::class, 'recipientsUpsert')) {
            Route::post('clientes/{rfc}/recipients-upsert', [ClientesController::class, 'recipientsUpsert'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.recipientsUpsert');

            Route::post('clientes/{rfc}/recipients', [ClientesController::class, 'recipientsUpsert'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.recipients');
        }

        if (method_exists(ClientesController::class, 'seedStatement')) {
            Route::post('clientes/{rfc}/seed-statement', [ClientesController::class, 'seedStatement'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.seedStatement');
        }

        if (method_exists(ClientesController::class, 'sendCredentialsAndMaybeStatement')) {
            Route::post('clientes/{rfc}/send-credentials', [ClientesController::class, 'sendCredentialsAndMaybeStatement'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.sendCredentials');
        }
    } else {
        Route::get('clientes', fn () => response('<h1>Clientes</h1><p>Pendiente de implementar.</p>', 200))
            ->middleware(perm_mw('clientes.ver'))
            ->name('clientes.index');
    }

    /* ---------- Soporte interno admin ---------- */
    Route::prefix('soporte')->as('soporte.')->group(function () use ($thrAdminPosts, $isLocal) {

        Route::get('reset-pass', [ResetClientePasswordController::class, 'showForm'])->name('reset_pass.show');
        Route::post('reset-pass', [ResetClientePasswordController::class, 'resetByRfc'])->name('reset_pass.do');

        $sendCreds = Route::post('email-credentials/{accountId}', [EmailClienteCredentialsController::class, 'send'])
            ->middleware($thrAdminPosts)
            ->name('email_credentials.send');

        if ($isLocal) {
            $sendCreds->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }
    });

    /* ---------- Logout admin (POST real + GET compat) ---------- */
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    // Compatibilidad: si alguien pega /admin/logout (GET) en navegador, evita 419
    Route::get('logout', function () {
        return redirect()->route('admin.home');
    })->name('logout.get');

    /* ---------- DEV / QA interno ---------- */
    Route::prefix('dev')->name('dev.')->group(function () use ($thrDevQa, $thrDevPosts, $isLocal) {

        Route::get('qa', [QaController::class, 'index'])
            ->middleware($thrDevQa)
            ->name('qa');

        $r1 = Route::post('resend-email', [QaController::class, 'resendEmail'])
            ->middleware($thrDevPosts)
            ->name('resend_email');

        $r2 = Route::post('send-otp', [QaController::class, 'sendOtp'])
            ->middleware($thrDevPosts)
            ->name('send_otp');

        $r3 = Route::post('force-email', [QaController::class, 'forceEmailVerified'])
            ->middleware($thrDevPosts)
            ->name('force_email');

        $r4 = Route::post('force-phone', [QaController::class, 'forcePhoneVerified'])
            ->middleware($thrDevPosts)
            ->name('force_phone');

        if ($isLocal) {
            foreach ([$r1, $r2, $r3, $r4] as $route) {
                $route->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }
    });

    /*
    |--------------------------------------------------------------------------
    | BILLING (DEBE IR ANTES DEL FALLBACK)
    |--------------------------------------------------------------------------
    */
    Route::prefix('billing')->name('billing.')->group(function () use ($thrAdminPosts, $isLocal) {

        Route::get('accounts', [AccountsController::class, 'index'])->name('accounts.index');

        Route::get('accounts/{id}', [AccountsController::class, 'show'])
            ->where('id', '[A-Za-z0-9\-]+')
            ->name('accounts.show');

        Route::post('accounts/{id}/license', [AccountsController::class, 'updateLicense'])
            ->where('id', '[A-Za-z0-9\-]+')
            ->name('accounts.license');

        Route::post('accounts/{id}/override', [AccountsController::class, 'updateOverride'])
            ->where('id', '[A-Za-z0-9\-]+')
            ->name('accounts.override');

        Route::post('accounts/{id}/modules', [AccountsController::class, 'updateModules'])
            ->where('id', '[A-Za-z0-9\-]+')
            ->name('accounts.modules');

        Route::post('invoices/requests/{id}/email-ready', [InvoiceRequestsController::class, 'emailReady'])
            ->whereNumber('id')
            ->name('invoices.requests.email_ready');

        Route::get('statements-hub/preview-email', [BillingStatementsHubController::class, 'previewEmail'])
            ->name('statements_hub.preview_email');

        Route::post('statements-hub/emails/{id}/resend', [BillingStatementsHubController::class, 'resendEmail'])
            ->whereNumber('id')
            ->name('statements_hub.resend');

        Route::post('statements-hub/invoices/save', [BillingStatementsHubController::class, 'saveInvoice'])
            ->name('statements_hub.save_invoice');

        $bulkSend = Route::post('statements-hub/bulk/send', [BillingStatementsHubController::class, 'bulkSend'])
            ->middleware($thrAdminPosts)
            ->name('statements_hub.bulk_send');

        $bulkPay = Route::post('statements-hub/bulk/paylinks', [BillingStatementsHubController::class, 'bulkPayLinks'])
            ->middleware($thrAdminPosts)
            ->name('statements_hub.bulk_paylinks');

        if ($isLocal) {
            $bulkSend->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            $bulkPay->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        Route::get('statements', [BillingStatementsController::class, 'index'])
            ->name('statements.index');

        // ✅ AJAX: actualizar estatus override + pay_method override (por cuenta+periodo)
        $stStatus = Route::post('statements/status', [BillingStatementsController::class, 'statusAjax'])
            ->middleware($thrAdminPosts)
            ->name('statements.status');

        if ($isLocal) {
            $stStatus->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        Route::get('statements/{accountId}/{period}', [BillingStatementsController::class, 'show'])
            ->where([
                'accountId' => '[A-Za-z0-9\-]+',
                'period'    => '\d{4}-(0[1-9]|1[0-2])',
            ])
            ->name('statements.show');

        Route::post('statements/{accountId}/{period}/items', [BillingStatementsController::class, 'addItem'])
            ->where([
                'accountId' => '[A-Za-z0-9\-]+',
                'period'    => '\d{4}-(0[1-9]|1[0-2])',
            ])
            ->name('statements.items.add');

        Route::get('statements/{accountId}/{period}/pdf', [BillingStatementsController::class, 'pdf'])
            ->where([
                'accountId' => '[A-Za-z0-9\-]+',
                'period'    => '\d{4}-(0[1-9]|1[0-2])',
            ])
            ->name('statements.pdf');

        Route::post('statements/{accountId}/{period}/email', [BillingStatementsController::class, 'email'])
            ->where([
                'accountId' => '[A-Za-z0-9\-]+',
                'period'    => '\d{4}-(0[1-9]|1[0-2])',
            ])
            ->name('statements.email');

        Route::get('statements-hub', [BillingStatementsHubController::class, 'index'])
            ->name('statements_hub.index');

        Route::post('statements-hub/send-email', [BillingStatementsHubController::class, 'sendEmail'])
            ->name('statements_hub.send_email');

        Route::post('statements-hub/create-pay-link', [BillingStatementsHubController::class, 'createPayLink'])
            ->name('statements_hub.create_pay_link');

        $manualPayment = Route::post('statements-hub/manual-payment', [BillingStatementsHubController::class, 'manualPayment'])
            ->middleware($thrAdminPosts)
            ->name('statements_hub.manual_payment');

        if ($isLocal) {
            $manualPayment->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        Route::post('statements-hub/invoice-request', [BillingStatementsHubController::class, 'invoiceRequest'])
            ->name('statements_hub.invoice_request');

        Route::post('statements-hub/invoice-status', [BillingStatementsHubController::class, 'invoiceStatus'])
            ->name('statements_hub.invoice_status');

        // ✅ Feature-flag automático: solo registra la ruta si el método existe.
        if (method_exists(BillingStatementsHubController::class, 'scheduleEmail')) {
            Route::post('statements-hub/schedule', [BillingStatementsHubController::class, 'scheduleEmail'])
                ->name('statements_hub.schedule');
        }

        $stLinesStore = Route::post('statements/lines', [BillingStatementsController::class, 'lineStore'])
            ->middleware($thrAdminPosts)
            ->name('statements.lines.store');

        $stLinesUpdate = Route::put('statements/lines', [BillingStatementsController::class, 'lineUpdate'])
            ->middleware($thrAdminPosts)
            ->name('statements.lines.update');

        $stLinesDelete = Route::delete('statements/lines', [BillingStatementsController::class, 'lineDelete'])
            ->middleware($thrAdminPosts)
            ->name('statements.lines.delete');

        $stSave = Route::post('statements/save', [BillingStatementsController::class, 'saveStatement'])
            ->middleware($thrAdminPosts)
            ->name('statements.save');

        if ($isLocal) {
            foreach ([$stLinesStore, $stLinesUpdate, $stLinesDelete, $stSave] as $rt) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        // Catálogo de precios
        Route::get('prices', [PriceCatalogController::class, 'index'])->name('prices.index');
        Route::get('prices/{id}/edit', [PriceCatalogController::class, 'edit'])->whereNumber('id')->name('prices.edit');
        Route::put('prices/{id}', [PriceCatalogController::class, 'update'])->whereNumber('id')->name('prices.update');
        Route::post('prices/{id}/toggle', [PriceCatalogController::class, 'toggle'])->whereNumber('id')->name('prices.toggle');

        // Licencias
        Route::get('licenses', [AccountLicensesController::class, 'index'])->name('licenses.index');
        Route::get('licenses/{accountId}', [AccountLicensesController::class, 'show'])->whereNumber('accountId')->name('licenses.show');

        Route::post('licenses/{accountId}/assign-price', [AccountLicensesController::class, 'assignPrice'])
            ->whereNumber('accountId')
            ->name('licenses.assignPrice');

        Route::post('licenses/{accountId}/modules', [AccountLicensesController::class, 'saveModules'])
            ->whereNumber('accountId')
            ->name('licenses.modules.save');

        Route::post('licenses/{accountId}/email/license', [AccountLicensesController::class, 'emailLicenseSummary'])
            ->whereNumber('accountId')
            ->name('licenses.email.license');

        // Pagos
        Route::get('payments', [PaymentsController::class, 'index'])->name('payments.index');
        Route::post('payments/manual', [PaymentsController::class, 'manual'])->name('payments.manual');
        Route::post('payments/{id}/email', [PaymentsController::class, 'emailReceipt'])
            ->whereNumber('id')
            ->name('payments.email');

        // Facturas
        Route::get('invoices/requests', [InvoiceRequestsController::class, 'index'])->name('invoices.requests.index');
        Route::post('invoices/requests/{id}/status', [InvoiceRequestsController::class, 'setStatus'])
            ->whereNumber('id')
            ->name('invoices.requests.status');
        Route::post('invoices/requests/{id}/email', [InvoiceRequestsController::class, 'email'])
            ->whereNumber('id')
            ->name('invoices.requests.email');

                /*
        |----------------------------------------------------------------------
        | ✅ COMPAT: SAT Admin bajo billing (admin.billing.sat.*)
        | Motivo: código legacy/redirecciones usan admin.billing.sat.discounts.*
        | Canon actual: admin.sat.discounts.* (fuera de billing)
        |----------------------------------------------------------------------
        */
        Route::prefix('sat')->name('sat.')->group(function () use ($thrAdminPosts, $isLocal) {

            // === PRICE RULES (compat) ===
            Route::prefix('prices')->name('prices.')->group(function () use ($thrAdminPosts, $isLocal) {

                Route::get('/', [AdminSatPriceRulesController::class, 'index'])->name('index');
                Route::get('create', [AdminSatPriceRulesController::class, 'create'])->name('create');

                $store = Route::post('/', [AdminSatPriceRulesController::class, 'store'])
                    ->middleware($thrAdminPosts)
                    ->name('store');

                Route::get('{id}/edit', [AdminSatPriceRulesController::class, 'edit'])
                    ->whereNumber('id')
                    ->name('edit');

                $update = Route::put('{id}', [AdminSatPriceRulesController::class, 'update'])
                    ->whereNumber('id')
                    ->middleware($thrAdminPosts)
                    ->name('update');

                $toggle = Route::post('{id}/toggle', [AdminSatPriceRulesController::class, 'toggle'])
                    ->whereNumber('id')
                    ->middleware($thrAdminPosts)
                    ->name('toggle');

                $destroy = Route::delete('{id}', [AdminSatPriceRulesController::class, 'destroy'])
                    ->whereNumber('id')
                    ->middleware($thrAdminPosts)
                    ->name('destroy');

                if ($isLocal) {
                    foreach ([$store, $update, $toggle, $destroy] as $rt) {
                        $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                    }
                }
            });

            // === DISCOUNT CODES (compat) ===
            Route::prefix('discounts')->name('discounts.')->group(function () use ($thrAdminPosts, $isLocal) {

                Route::get('/', [AdminSatDiscountCodesController::class, 'index'])->name('index');
                Route::get('create', [AdminSatDiscountCodesController::class, 'create'])->name('create');

                $store = Route::post('/', [AdminSatDiscountCodesController::class, 'store'])
                    ->middleware($thrAdminPosts)
                    ->name('store');

                Route::get('{id}/edit', [AdminSatDiscountCodesController::class, 'edit'])
                    ->whereNumber('id')
                    ->name('edit');

                $update = Route::put('{id}', [AdminSatDiscountCodesController::class, 'update'])
                    ->whereNumber('id')
                    ->middleware($thrAdminPosts)
                    ->name('update');

                $toggle = Route::post('{id}/toggle', [AdminSatDiscountCodesController::class, 'toggle'])
                    ->whereNumber('id')
                    ->middleware($thrAdminPosts)
                    ->name('toggle');

                $destroy = Route::delete('{id}', [AdminSatDiscountCodesController::class, 'destroy'])
                    ->whereNumber('id')
                    ->middleware($thrAdminPosts)
                    ->name('destroy');

                if ($isLocal) {
                    foreach ([$store, $update, $toggle, $destroy] as $rt) {
                        $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                    }
                }
            });
        });

    });

    /*
    |--------------------------------------------------------------------------
    | USUARIOS (ADMIN)
    |--------------------------------------------------------------------------
    */
    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('administrativos', [AdministrativosController::class, 'index'])->name('administrativos.index');
        Route::get('administrativos/create', [AdministrativosController::class, 'create'])->name('administrativos.create');
        Route::post('administrativos', [AdministrativosController::class, 'store'])->name('administrativos.store');

        // ✅ soporta UUID (local) o int (prod)
        Route::get('administrativos/{id}/edit', [AdministrativosController::class, 'edit'])
            ->where('id', '[A-Za-z0-9\-]+')
            ->name('administrativos.edit');

        Route::put('administrativos/{id}', [AdministrativosController::class, 'update'])
            ->where('id', '[A-Za-z0-9\-]+')
            ->name('administrativos.update');

        Route::post('administrativos/{id}/toggle', [AdministrativosController::class, 'toggle'])
            ->where('id', '[A-Za-z0-9\-]+')
            ->name('administrativos.toggle');

        Route::post('administrativos/{id}/reset-password', [AdministrativosController::class, 'resetPassword'])
            ->where('id', '[A-Za-z0-9\-]+')
            ->name('administrativos.reset_password');

        Route::delete('administrativos/{id}', [AdministrativosController::class, 'destroy'])
            ->where('id', '[A-Za-z0-9\-]+')
            ->name('administrativos.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | SAT · Lista de precios + Códigos de descuento (Admin) — CANÓNICO
    |--------------------------------------------------------------------------
    */
    Route::prefix('sat')->name('sat.')->group(function () use ($thrAdminPosts, $isLocal) {

        /*
        |----------------------------------------------------------------------
        | SAT · OPERACIÓN (Backoffice) — admin.sat.ops.*
        |----------------------------------------------------------------------
        */
        Route::prefix('ops')->name('ops.')->group(function () {

        // HUB
        Route::get('/', [SatOpsController::class, 'index'])->name('index');

        /*
        |----------------------------------------------------------------------
        | OPS · CREDENCIALES — admin.sat.ops.credentials.*
        |----------------------------------------------------------------------
        */
        Route::prefix('credentials')->name('credentials.')->group(function () {

            Route::get('/', [SatOpsCredentialsController::class, 'index'])
                ->name('index');

            // Descargas
            Route::get('{id}/cer', [SatOpsCredentialsController::class, 'cer'])
                ->where('id', '[A-Za-z0-9\-]+')
                ->name('cer');

            Route::get('{id}/key', [SatOpsCredentialsController::class, 'key'])
                ->where('id', '[A-Za-z0-9\-]+')
                ->name('key');

            // ✅ DELETE correcto:
            // URL : /admin/sat/ops/credentials/{id}
            // NAME: admin.sat.ops.credentials.destroy
            $destroy = Route::delete('{id}', [SatOpsCredentialsController::class, 'destroy'])
                ->where('id', '[A-Za-z0-9\-]+')
                ->name('destroy');

            // En local, si usas fetch() sin token o con headers raros, esto evita fricción.
            // Si ya lo mandas bien con X-CSRF-TOKEN, puedes quitarlo.
            if (app()->environment(['local', 'development', 'testing'])) {
                $destroy->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        });

        /*
        |----------------------------------------------------------------------
        | OPS · DESCARGAS / MANUAL / PAYMENTS
        |----------------------------------------------------------------------
        */
        Route::prefix('downloads')->name('downloads.')->group(function () {
            Route::get('/', [SatOpsDownloadsController::class, 'index'])->name('index');
        });

        Route::prefix('manual')->name('manual.')->group(function () {
            Route::get('/', [SatOpsManualRequestsController::class, 'index'])->name('index');
        });

        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [SatOpsPaymentsController::class, 'index'])->name('index');
        });
    });


        // SAT · PRICE RULES
        Route::prefix('prices')->name('prices.')->group(function () use ($thrAdminPosts, $isLocal) {

            Route::get('/', [AdminSatPriceRulesController::class, 'index'])->name('index');
            Route::get('create', [AdminSatPriceRulesController::class, 'create'])->name('create');

            $store = Route::post('/', [AdminSatPriceRulesController::class, 'store'])
                ->middleware($thrAdminPosts)
                ->name('store');

            Route::get('{id}/edit', [AdminSatPriceRulesController::class, 'edit'])
                ->whereNumber('id')
                ->name('edit');

            $update = Route::put('{id}', [AdminSatPriceRulesController::class, 'update'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('update');

            $toggle = Route::post('{id}/toggle', [AdminSatPriceRulesController::class, 'toggle'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('toggle');

            $destroy = Route::delete('{id}', [AdminSatPriceRulesController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('destroy');

            if ($isLocal) {
                foreach ([$store, $update, $toggle, $destroy] as $rt) {
                    $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                }
            }

            // Descarga CSD (desde mysql_clientes.sat_credentials + disk public)
            Route::get('credentials/{id}/cer', [SatCredentialsController::class, 'downloadCer'])
                ->where('id', '[0-9]+')
                ->name('credentials.cer');

            Route::get('credentials/{id}/key', [SatCredentialsController::class, 'downloadKey'])
                ->where('id', '[0-9]+')
                ->name('credentials.key');


        });

        // SAT · DISCOUNT CODES
        Route::prefix('discounts')->name('discounts.')->group(function () use ($thrAdminPosts, $isLocal) {

            Route::get('/', [AdminSatDiscountCodesController::class, 'index'])->name('index');
            Route::get('create', [AdminSatDiscountCodesController::class, 'create'])->name('create');

            $store = Route::post('/', [AdminSatDiscountCodesController::class, 'store'])
                ->middleware($thrAdminPosts)
                ->name('store');

            Route::get('{id}/edit', [AdminSatDiscountCodesController::class, 'edit'])
                ->whereNumber('id')
                ->name('edit');

            $update = Route::put('{id}', [AdminSatDiscountCodesController::class, 'update'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('update');

            $toggle = Route::post('{id}/toggle', [AdminSatDiscountCodesController::class, 'toggle'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('toggle');

            $destroy = Route::delete('{id}', [AdminSatDiscountCodesController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('destroy');

            if ($isLocal) {
                foreach ([$store, $update, $toggle, $destroy] as $rt) {
                    $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                }
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | ✅ Fallback interno admin (SIEMPRE AL FINAL)
    |--------------------------------------------------------------------------
    */
    Route::fallback(fn () => redirect()->route('admin.home'));
});
