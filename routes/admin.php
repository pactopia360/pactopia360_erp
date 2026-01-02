<?php
// C:\wamp64\www\pactopia360_erp\routes\admin.php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------|
| Controladores ADMIN
|--------------------------------------------------------------------------|
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

// Billing suite (Admin)
use App\Http\Controllers\Admin\Billing\PriceCatalogController;
use App\Http\Controllers\Admin\Billing\AccountLicensesController;
use App\Http\Controllers\Admin\Billing\PaymentsController;
use App\Http\Controllers\Admin\Billing\InvoiceRequestsController;
use App\Http\Controllers\Admin\Billing\AccountsController;

// ✅ Estados de cuenta (legacy)
use App\Http\Controllers\Admin\Billing\BillingStatementsController;

// ✅ HUB nuevo (estados + pagos + emails + facturas + tracking)
use App\Http\Controllers\Admin\Billing\BillingStatementsHubController;

/*
|--------------------------------------------------------------------------|
| CSRF middlewares (para quitar en local en algunos POST)
|--------------------------------------------------------------------------|
*/
use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;

/*
|--------------------------------------------------------------------------|
| ENV + throttles
|--------------------------------------------------------------------------|
*/
$isLocal = app()->environment(['local','development','testing']);

$thrLogin        = $isLocal ? 'throttle:60,1'  : 'throttle:5,1';
$thrUiHeartbeat  = $isLocal ? 'throttle:120,1' : 'throttle:60,1';
$thrUiLog        = $isLocal ? 'throttle:480,1' : 'throttle:240,1';
$thrHomeStats    = $isLocal ? 'throttle:120,1' : 'throttle:60,1';
$thrUiDiag       = $isLocal ? 'throttle:60,1'  : 'throttle:30,1';
$thrUiBotAsk     = $isLocal ? 'throttle:60,1'  : 'throttle:30,1';
$thrDevQa        = $isLocal ? 'throttle:120,1' : 'throttle:60,1';
$thrDevPosts     = $isLocal ? 'throttle:60,1'  : 'throttle:30,1';
$thrAdminPosts   = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';

/*
|--------------------------------------------------------------------------|
| Helper permisos → middleware 'can:perm,<clave>'
|--------------------------------------------------------------------------|
*/
if (!function_exists('perm_mw')) {
    function perm_mw(string|array $perm): array
    {
        if (app()->environment(['local','development','testing'])) return [];
        $perms = is_array($perm) ? $perm : [$perm];
        return array_map(fn($p) => 'can:perm,' . $p, $perms);
    }
}

/*
|--------------------------------------------------------------------------|
| Placeholder rápido (si falta una vista admin)
|--------------------------------------------------------------------------|
*/
if (!function_exists('admin_placeholder_view')) {
    function admin_placeholder_view(string $title, string $company = 'PACTOPIA 360') {
        if (view()->exists('admin.generic.placeholder')) {
            return view('admin.generic.placeholder', compact('title','company'));
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
|--------------------------------------------------------------------------|
| UI (heartbeat, log)
|--------------------------------------------------------------------------|
*/
Route::match(['GET','HEAD'], 'ui/heartbeat', [UiController::class, 'heartbeat'])
    ->middleware($thrUiHeartbeat)
    ->name('ui.heartbeat');

$uiLog = Route::match(['POST','GET'], 'ui/log', [UiController::class, 'log'])
    ->middleware($thrUiLog)
    ->name('ui.log');

if ($isLocal) {
    $uiLog->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
}

/*
|--------------------------------------------------------------------------|
| Auth admin (guest:admin + sesión aislada)
|--------------------------------------------------------------------------|
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
|--------------------------------------------------------------------------|
| Notificaciones públicas (contador)
|--------------------------------------------------------------------------|
*/
Route::match(['GET','HEAD'], 'notificaciones/count', [NotificationController::class, 'count'])
    ->middleware('throttle:60,1')
    ->name('notificaciones.count');

/*
|--------------------------------------------------------------------------|
| Área autenticada ADMIN (auth:admin + sesión aislada)
|--------------------------------------------------------------------------|
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
    Route::get('/', fn() => redirect()->route('admin.home'))->name('root');
    Route::get('dashboard', fn() => redirect()->route('admin.home'))->name('dashboard');

    /* ---------- WhoAmI admin ---------- */
    Route::get('_whoami', function () {
        $u = auth('admin')->user();
        return response()->json([
            'ok'     => (bool) $u,
            'id'     => $u?->id,
            'name'   => $u?->name ?? $u?->nombre,
            'email'  => $u?->email,
            'guard'  => 'admin',
            'now'    => now()->toDateTimeString(),
            'canAny' => [
                'access-admin' => Gate::forUser($u)->allows('access-admin'),
            ],
            'super'  => method_exists($u, 'isSuperAdmin')
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
            ->where(['weeks'=>'\d+'])
            ->name('home.hitsHeatmap');
    }

    if (method_exists(HomeController::class, 'modulesTop')) {
        Route::get('home/modules-top/{months?}', [HomeController::class, 'modulesTop'])
            ->where(['months'=>'\d+'])
            ->name('home.modulesTop');
    }

    if (method_exists(HomeController::class, 'plansBreakdown')) {
        Route::get('home/plans-breakdown/{months?}', [HomeController::class, 'plansBreakdown'])
            ->where(['months'=>'\d+'])
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

        /*
        |--------------------------------------------------------------------------|
        | ✅ Nombres CANÓNICOS (coinciden con el controller y la vista nueva)
        |--------------------------------------------------------------------------|
        */
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

        /*
        |--------------------------------------------------------------------------|
        | ✅ Aliases legacy/compat (para no romper vistas viejas)
        |--------------------------------------------------------------------------|
        */
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

        /*
        |--------------------------------------------------------------------------|
        | ✅ Reset password (OWNER) — soporte RFC o ID (en controller acepta rfcOrId)
        |--------------------------------------------------------------------------|
        */
        $rp = Route::match(['GET','POST'], 'clientes/{rfcOrId}/reset-password', [ClientesController::class, 'resetPassword'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.resetPassword');

        if ($isLocal) {
            $rp->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        Route::post('clientes/{rfc}/email-credentials', [ClientesController::class, 'emailCredentials'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.emailCredentials');

        Route::post('clientes/{rfc}/impersonate', [ClientesController::class, 'impersonate'])
            ->middleware([$thrAdminPosts, ...perm_mw(['clientes.ver','clientes.impersonate'])])
            ->name('clientes.impersonate');

        /*
        |--------------------------------------------------------------------------|
        | ✅ Stop impersonate — nombre canónico + alias legacy
        |--------------------------------------------------------------------------|
        */
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

        /*
        |--------------------------------------------------------------------------|
        | ✅ Destinatarios / Sembrar Edo. Cuenta (periodo)
        |--------------------------------------------------------------------------|
        */
        if (method_exists(ClientesController::class, 'recipientsUpsert')) {
            Route::post('clientes/{rfc}/recipients-upsert', [ClientesController::class, 'recipientsUpsert'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.recipientsUpsert');

            // alias legacy si alguna vista usaba /clientes/{rfc}/recipients
            Route::post('clientes/{rfc}/recipients', [ClientesController::class, 'recipientsUpsert'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.recipients');
        }

        if (method_exists(ClientesController::class, 'seedStatement')) {
            Route::post('clientes/{rfc}/seed-statement', [ClientesController::class, 'seedStatement'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.seedStatement');
        }

        // Nota: sendCredentialsAndMaybeStatement NO existe en tu controller actual; se deja solo si existe
        if (method_exists(ClientesController::class, 'sendCredentialsAndMaybeStatement')) {
            Route::post('clientes/{rfc}/send-credentials', [ClientesController::class, 'sendCredentialsAndMaybeStatement'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.sendCredentials');
        }

    } else {
        Route::get('clientes', fn () =>
            response('<h1>Clientes</h1><p>Pendiente de implementar.</p>', 200)
        )
        ->middleware(perm_mw('clientes.ver'))
        ->name('clientes.index');
    }

    /* ---------- Soporte interno admin ---------- */
    Route::prefix('soporte')
        ->as('soporte.')
        ->middleware(['auth:admin', \App\Http\Middleware\AdminSessionConfig::class])
        ->group(function () {
            Route::get('reset-pass', [ResetClientePasswordController::class, 'showForm'])->name('reset_pass.show');
            Route::post('reset-pass', [ResetClientePasswordController::class, 'resetByRfc'])->name('reset_pass.do');
        });

    /* ---------- Logout admin ---------- */
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    /* ---------- DEV / QA interno ---------- */
    Route::prefix('dev')->name('dev.')->group(function () use ($thrDevQa, $thrDevPosts, $isLocal) {
        Route::get('qa', [QaController::class,'index'])
            ->middleware($thrDevQa)
            ->name('qa');

        $r1 = Route::post('resend-email', [QaController::class,'resendEmail'])
            ->middleware($thrDevPosts)
            ->name('resend_email');

        $r2 = Route::post('send-otp', [QaController::class,'sendOtp'])
            ->middleware($thrDevPosts)
            ->name('send_otp');

        $r3 = Route::post('force-email', [QaController::class,'forceEmailVerified'])
            ->middleware($thrDevPosts)
            ->name('force_email');

        $r4 = Route::post('force-phone', [QaController::class,'forcePhoneVerified'])
            ->middleware($thrDevPosts)
            ->name('force_phone');

        if ($isLocal) {
            foreach ([$r1,$r2,$r3,$r4] as $route) {
                $route->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }
    });

    /*
    |--------------------------------------------------------------------------|
    | BILLING (DEBE IR ANTES DEL FALLBACK)
    |--------------------------------------------------------------------------|
    */
    Route::prefix('billing')->name('billing.')->group(function () {

        /*
        |--------------------------------------------------------------------------|
        | Billing SaaS · Cuentas
        |--------------------------------------------------------------------------|
        */
        Route::get('accounts', [AccountsController::class, 'index'])
            ->name('accounts.index');

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

        /*
        |--------------------------------------------------------------------------|
        | HUB · extras (preview / resend / save invoice)
        |--------------------------------------------------------------------------|
        */
        Route::get('statements-hub/preview-email', [BillingStatementsHubController::class, 'previewEmail'])
            ->name('statements_hub.preview_email');

        Route::post('statements-hub/emails/{id}/resend', [BillingStatementsHubController::class, 'resendEmail'])
            ->whereNumber('id')
            ->name('statements_hub.resend');

        Route::post('statements-hub/invoices/save', [BillingStatementsHubController::class, 'saveInvoice'])
            ->name('statements_hub.save_invoice');

        /*
        |--------------------------------------------------------------------------|
        | Estados de cuenta (ADMIN) — BillingStatementsController (legacy)
        |--------------------------------------------------------------------------|
        */
        Route::get('statements', [BillingStatementsController::class, 'index'])
            ->name('statements.index');

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

        /*
        |--------------------------------------------------------------------------|
        | HUB ADMIN · Estados de cuenta + Pagos + Correos + Facturas + Programación
        |--------------------------------------------------------------------------|
        */
        Route::get('statements-hub', [BillingStatementsHubController::class, 'index'])
            ->name('statements_hub.index');

        Route::post('statements-hub/send-email', [BillingStatementsHubController::class, 'sendEmail'])
            ->name('statements_hub.send_email');

        Route::post('statements-hub/create-pay-link', [BillingStatementsHubController::class, 'createPayLink'])
            ->name('statements_hub.create_pay_link');

        Route::post('statements-hub/invoice-request', [BillingStatementsHubController::class, 'invoiceRequest'])
            ->name('statements_hub.invoice_request');

        Route::post('statements-hub/invoice-status', [BillingStatementsHubController::class, 'invoiceStatus'])
            ->name('statements_hub.invoice_status');

        Route::post('statements-hub/schedule', [BillingStatementsHubController::class, 'scheduleEmail'])
            ->name('statements_hub.schedule');

        /*
        |--------------------------------------------------------------------------|
        | Suite existente (si la sigues usando)
        |--------------------------------------------------------------------------|
        */
        Route::get('prices', [PriceCatalogController::class, 'index'])->name('prices.index');
        Route::get('prices/{id}/edit', [PriceCatalogController::class, 'edit'])->whereNumber('id')->name('prices.edit');
        Route::put('prices/{id}', [PriceCatalogController::class, 'update'])->whereNumber('id')->name('prices.update');
        Route::post('prices/{id}/toggle', [PriceCatalogController::class, 'toggle'])->whereNumber('id')->name('prices.toggle');

        Route::get('licenses', [AccountLicensesController::class, 'index'])->name('licenses.index');
        Route::get('licenses/{accountId}', [AccountLicensesController::class, 'show'])->whereNumber('accountId')->name('licenses.show');

        Route::post('licenses/{accountId}/assign-price', [AccountLicensesController::class, 'assignPrice'])
            ->whereNumber('accountId')->name('licenses.assignPrice');

        Route::post('licenses/{accountId}/modules', [AccountLicensesController::class, 'saveModules'])
            ->whereNumber('accountId')->name('licenses.modules.save');

        Route::post('licenses/{accountId}/email/license', [AccountLicensesController::class, 'emailLicenseSummary'])
            ->whereNumber('accountId')->name('licenses.email.license');

        Route::get('payments', [PaymentsController::class, 'index'])->name('payments.index');
        Route::post('payments/manual', [PaymentsController::class, 'manual'])->name('payments.manual');
        Route::post('payments/{id}/email', [PaymentsController::class, 'emailReceipt'])
            ->whereNumber('id')->name('payments.email');

        Route::get('invoices/requests', [InvoiceRequestsController::class, 'index'])->name('invoices.requests.index');
        Route::post('invoices/requests/{id}/status', [InvoiceRequestsController::class, 'setStatus'])
            ->whereNumber('id')->name('invoices.requests.status');
        Route::post('invoices/requests/{id}/email', [InvoiceRequestsController::class, 'email'])
            ->whereNumber('id')->name('invoices.requests.email');
    });

    /* ---------- Fallback interno admin ---------- */
    Route::fallback(fn () => redirect()->route('admin.home'));
});
