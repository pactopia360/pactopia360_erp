<?php
// C:\wamp64\www\pactopia360_erp\routes\admin.php
// PACTOPIA360 · ADMIN routes (SOT)

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Controladores ADMIN
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\AdminPasswordResetController;
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
use App\Http\Controllers\Admin\Billing\BillingStatementEmailsController;

// Invoicing admin
use App\Http\Controllers\Admin\Billing\InvoicingDashboardController;
use App\Http\Controllers\Admin\Billing\InvoicesController;
use App\Http\Controllers\Admin\Billing\InvoicingSettingsController;
use App\Http\Controllers\Admin\Billing\InvoicingLogsController;

// Usuarios admin (módulo)
use App\Http\Controllers\Admin\Usuarios\AdministrativosController;

// SAT Admin
use App\Http\Controllers\Admin\Billing\Sat\SatDiscountCodesController as AdminSatDiscountCodesController;
use App\Http\Controllers\Admin\Billing\Sat\SatPriceRulesController as AdminSatPriceRulesController;
use App\Http\Controllers\Admin\Sat\SatCredentialsController;
use App\Http\Controllers\Admin\Sat\Ops\SatOpsRfcsController;

//Facturacion
use App\Http\Controllers\Admin\Billing\EmisoresController;
use App\Http\Controllers\Admin\Billing\ReceptoresController;

// SAT Ops (Backoffice)
use App\Http\Controllers\Admin\Sat\Ops\SatOpsController;
use App\Http\Controllers\Admin\Sat\Ops\SatOpsCredentialsController;
use App\Http\Controllers\Admin\Sat\Ops\SatOpsDownloadsController;
use App\Http\Controllers\Admin\Sat\Ops\SatOpsQuotesController;
use App\Http\Controllers\Admin\Sat\Ops\SatOpsManualRequestsController;
use App\Http\Controllers\Admin\Sat\Ops\SatOpsPaymentsController;


// Finanzas (módulo)
use App\Http\Controllers\Admin\Finance\CostCentersController;
use App\Http\Controllers\Admin\Finance\IncomeController;
use App\Http\Controllers\Admin\Finance\ExpensesController;
use App\Http\Controllers\Admin\Finance\SalesController;
use App\Http\Controllers\Admin\Finance\VendorsController;
use App\Http\Controllers\Admin\Finance\CommissionsController;
use App\Http\Controllers\Admin\Finance\ProjectionsController;
use App\Http\Controllers\Admin\Finance\IncomeActionsController;
use App\Http\Controllers\Admin\Finance\ExpensesActionsController;
use App\Http\Controllers\Admin\Sat\Ops\SatOpsVaultAccessController;

/*
|--------------------------------------------------------------------------
| CSRF middlewares
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
        if (app()->environment(['local', 'development', 'testing'])) {
            return [];
        }

        $perms = is_array($perm) ? $perm : [$perm];
        return array_map(static fn ($p) => 'can:' . $p, $perms);
    }
}

/*
|--------------------------------------------------------------------------
| Placeholder rápido
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
| Stack de middlewares a remover para endpoints públicos sin cookies/sesión
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

/*
|--------------------------------------------------------------------------
| Tracking público Billing (OPEN/CLICK) — SIN auth y SIN cookies/sesión
|--------------------------------------------------------------------------
*/
Route::prefix('t/billing')
    ->name('billing.hub.')
    ->middleware('throttle:240,1')
    ->group(function () use ($noCookies) {
        Route::get('open/{emailId}', [BillingStatementsHubController::class, 'trackOpen'])
            ->where('emailId', '[A-Za-z0-9\-]+')
            ->withoutMiddleware($noCookies)
            ->name('track_open');

        Route::get('open/{emailId}.gif', [BillingStatementsHubController::class, 'trackOpen'])
            ->where('emailId', '[A-Za-z0-9\-]+')
            ->withoutMiddleware($noCookies)
            ->name('track_open_gif');

        Route::get('click/{emailId}', [BillingStatementsHubController::class, 'trackClick'])
            ->where('emailId', '[A-Za-z0-9\-]+')
            ->withoutMiddleware($noCookies)
            ->name('track_click');
    });

/*
|--------------------------------------------------------------------------
| PayLink público HUB
|--------------------------------------------------------------------------
*/
Route::get('billing/statements-hub/paylink', [BillingStatementsHubController::class, 'payLink'])
    ->middleware('throttle:240,1')
    ->withoutMiddleware($noCookies)
    ->name('billing.hub.paylink');

/*
|--------------------------------------------------------------------------
| UI
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
| Diag rápido
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
| COMPAT GET: /admin/clientes/sync-to-clientes
|--------------------------------------------------------------------------
*/
Route::get('clientes/sync-to-clientes', function () {
    if (!auth('admin')->check()) {
        return redirect()->route('admin.login');
    }

    return redirect()->route('admin.clientes.index');
})->middleware([\App\Http\Middleware\AdminSessionConfig::class])
  ->name('clientes.sync_to_clientes.get_compat');

/*
|--------------------------------------------------------------------------
| Auth admin (guest:admin + sesión aislada)
|--------------------------------------------------------------------------
*/
Route::middleware([
    'admin',
    'guest:admin',
])->group(function () use ($isLocal, $thrLogin) {
    Route::get('login', [LoginController::class, 'showLogin'])->name('login');

    $loginPost = Route::post('login', [LoginController::class, 'login'])
        ->middleware($thrLogin)
        ->name('login.do');

    Route::get('password/forgot', [AdminPasswordResetController::class, 'showRequestForm'])
        ->name('password.request');

    $passwordEmail = Route::post('password/email', [AdminPasswordResetController::class, 'sendResetLink'])
        ->middleware($thrLogin)
        ->name('password.email');

    Route::get('password/reset/{token}', [AdminPasswordResetController::class, 'showResetForm'])
        ->where('token', '[A-Za-z0-9]+')
        ->name('password.reset');

    $passwordReset = Route::post('password/reset', [AdminPasswordResetController::class, 'reset'])
        ->middleware($thrLogin)
        ->name('password.update');

    if ($isLocal) {
        $loginPost->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        $passwordEmail->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        $passwordReset->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }
});

/*
|--------------------------------------------------------------------------
| Notificaciones públicas
|--------------------------------------------------------------------------
*/
Route::match(['GET', 'HEAD'], 'notificaciones/count', [NotificationController::class, 'count'])
    ->middleware('throttle:60,1')
    ->name('notificaciones.count');

/*
|--------------------------------------------------------------------------
| Área autenticada ADMIN
|--------------------------------------------------------------------------
*/
Route::middleware([
    'admin',
    'auth:admin',
])->group(function () use (
    $thrHomeStats,
    $thrUiDiag,
    $thrUiBotAsk,
    $thrDevQa,
    $thrDevPosts,
    $thrAdminPosts,
    $isLocal
) {
    Route::get('/', fn () => redirect()->route('admin.home'))->name('root');
    Route::get('dashboard', fn () => redirect()->route('admin.home'))->name('dashboard');

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
                : (bool) ($u?->es_superadmin ?? false),
        ]);
    })->name('whoami');

    /*
    |--------------------------------------------------------------------------
    | Home
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | Utilidades admin
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | Perfil admin
    |--------------------------------------------------------------------------
    */
    Route::get('perfil', [ProfileController::class, 'index'])->name('perfil');
    Route::get('perfil/edit', [ProfileController::class, 'edit'])->name('perfil.edit');
    Route::put('perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::post('perfil/password', [ProfileController::class, 'password'])->name('perfil.password');

    /*
    |--------------------------------------------------------------------------
    | Config admin
    |--------------------------------------------------------------------------
    */
    Route::get('config', [ConfigController::class, 'index'])
        ->middleware(perm_mw('admin.config'))
        ->name('config.index');

    /*
    |--------------------------------------------------------------------------
    | Reportes raíz
    |--------------------------------------------------------------------------
    */
    Route::get('reportes', [ReportesController::class, 'index'])
        ->middleware(perm_mw('reportes.ver'))
        ->name('reportes.index');

        /*
    |--------------------------------------------------------------------------
    | CLIENTES
    |--------------------------------------------------------------------------
    */
    if (class_exists(ClientesController::class)) {
        $clientesCreateCompat = Route::post('clientes/create', [ClientesController::class, 'store'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.create.post_compat');

        $clientesStore = Route::post('clientes', [ClientesController::class, 'store'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.store');

        $sync = Route::post('clientes/sync-to-clientes', [ClientesController::class, 'syncToClientes'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.syncToClientes');

        $bulk = Route::post('clientes/bulk', [ClientesController::class, 'bulk'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.bulk');

        $impStop1 = Route::post('clientes/impersonate-stop', [ClientesController::class, 'impersonateStop'])
            ->middleware($thrAdminPosts)
            ->name('clientes.impersonateStop');

        $impStop2 = Route::post('clientes/impersonate/stop', [ClientesController::class, 'impersonateStop'])
            ->middleware($thrAdminPosts)
            ->name('clientes.impersonate.stop');

        if ($isLocal) {
            foreach ([
                $clientesCreateCompat,
                $clientesStore,
                $sync,
                $bulk,
                $impStop1,
                $impStop2,
            ] as $rt) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        Route::get('clientes', [ClientesController::class, 'index'])
            ->middleware(perm_mw('clientes.ver'))
            ->name('clientes.index');

        if (method_exists(ClientesController::class, 'show')) {
            Route::get('clientes/{key}', [ClientesController::class, 'show'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware(perm_mw('clientes.ver'))
                ->name('clientes.show');
        } elseif (method_exists(ClientesController::class, 'edit')) {
            Route::get('clientes/{key}', [ClientesController::class, 'edit'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware(perm_mw('clientes.ver'))
                ->name('clientes.show');
        } else {
            Route::get('clientes/{key}', function (string $key) {
                return admin_placeholder_view('Cliente · Pendiente', 'PACTOPIA 360');
            })->where('key', '[A-Za-z0-9\-]+')
              ->middleware(perm_mw('clientes.ver'))
              ->name('clientes.show');
        }

        if (method_exists(ClientesController::class, 'save')) {
            $clientesSavePostDirect = Route::post('clientes/{key}', [ClientesController::class, 'save'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.save.post_direct');

            $clientesSavePutDirect = Route::match(['put', 'patch'], 'clientes/{key}', [ClientesController::class, 'save'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.save.put_direct');

            $clientesSaveKey = Route::post('clientes/{key}/save', [ClientesController::class, 'save'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.save');

            if ($isLocal) {
                foreach ([
                    $clientesSavePostDirect,
                    $clientesSavePutDirect,
                    $clientesSaveKey,
                ] as $rt) {
                    $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                }
            }
        } else {
            Route::match(['post', 'put', 'patch'], 'clientes/{key}', function () {
                abort(404);
            })->where('key', '[A-Za-z0-9\-]+')
              ->name('clientes.save.missing');
        }

        if (method_exists(ClientesController::class, 'billingPanel')) {
            Route::get('clientes/{key}/billing/panel', [ClientesController::class, 'billingPanel'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware(perm_mw('clientes.ver'))
                ->name('clientes.billing.panel');
        }

        if (method_exists(ClientesController::class, 'block')) {
            $rt = Route::post('clientes/{key}/block', [ClientesController::class, 'block'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.block');
            if ($isLocal) $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        if (method_exists(ClientesController::class, 'unblock')) {
            $rt = Route::post('clientes/{key}/unblock', [ClientesController::class, 'unblock'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.unblock');
            if ($isLocal) $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        if (method_exists(ClientesController::class, 'deactivate')) {
            $rt = Route::post('clientes/{key}/deactivate', [ClientesController::class, 'deactivate'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.deactivate');
            if ($isLocal) $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        if (method_exists(ClientesController::class, 'reactivate')) {
            $rt = Route::post('clientes/{key}/reactivate', [ClientesController::class, 'reactivate'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.reactivate');
            if ($isLocal) $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        if (method_exists(ClientesController::class, 'destroy')) {
            $rt = Route::post('clientes/{key}/destroy', [ClientesController::class, 'destroy'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.destroy');

            $rt2 = Route::post('clientes/{key}/delete', [ClientesController::class, 'destroy'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.delete');

            if ($isLocal) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                $rt2->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        $resendV = Route::post('clientes/{key}/resend-email-verification', [ClientesController::class, 'resendEmailVerification'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.resendEmailVerification');

        $sendOtp = Route::post('clientes/{key}/send-phone-otp', [ClientesController::class, 'sendPhoneOtp'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.sendPhoneOtp');

        $forceMail = Route::post('clientes/{key}/force-email-verified', [ClientesController::class, 'forceEmailVerified'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forceEmailVerified');

        $forcePhone = Route::post('clientes/{key}/force-phone-verified', [ClientesController::class, 'forcePhoneVerified'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forcePhoneVerified');

        $resendV2 = Route::post('clientes/{key}/resend-email', [ClientesController::class, 'resendEmailVerification'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.resendEmail');

        $sendOtp2 = Route::post('clientes/{key}/send-otp', [ClientesController::class, 'sendPhoneOtp'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.sendOtp');

        $forceMail2 = Route::post('clientes/{key}/force-email', [ClientesController::class, 'forceEmailVerified'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forceEmail');

        $forcePhone2 = Route::post('clientes/{key}/force-phone', [ClientesController::class, 'forcePhoneVerified'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forcePhone');

        if ($isLocal) {
            foreach ([
                $resendV,
                $sendOtp,
                $forceMail,
                $forcePhone,
                $resendV2,
                $sendOtp2,
                $forceMail2,
                $forcePhone2,
            ] as $rt) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        $rp = Route::match(['GET', 'POST'], 'clientes/{key}/reset-password', [ClientesController::class, 'resetPassword'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.resetPassword');

        Route::get('clientes/{key}/email-credentials', function (string $key) {
            if (!auth('admin')->check()) {
                return redirect()->route('admin.login');
            }

            $target = \Illuminate\Support\Facades\Route::has('admin.clientes.show')
                ? route('admin.clientes.show', ['key' => $key])
                : route('admin.clientes.index', ['q' => $key]);

            return redirect($target)->with(
                'info',
                'La URL de envío de credenciales no es una pantalla. Ya te redirigimos al cliente para que envíes las credenciales desde ahí.'
            );
        })->where('key', '[A-Za-z0-9\-]+')
          ->middleware(perm_mw('clientes.ver'))
          ->name('clientes.emailCredentials.get_compat');

        $emailCreds = Route::post('clientes/{key}/email-credentials', [ClientesController::class, 'emailCredentials'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.emailCredentials');

        if ($isLocal) {
            foreach ([$rp, $emailCreds] as $rt) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        $imp = Route::post('clientes/{key}/impersonate', [ClientesController::class, 'impersonate'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw(['clientes.ver', 'clientes.impersonate'])])
            ->name('clientes.impersonate');

        if ($isLocal) {
            $imp->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        if (method_exists(ClientesController::class, 'recipientsUpsert')) {
            $r1 = Route::post('clientes/{key}/recipients-upsert', [ClientesController::class, 'recipientsUpsert'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.recipientsUpsert');

            $r2 = Route::post('clientes/{key}/recipients', [ClientesController::class, 'recipientsUpsert'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.recipients');

            if ($isLocal) {
                foreach ([$r1, $r2] as $rt) {
                    $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                }
            }
        }

        if (method_exists(ClientesController::class, 'seedStatement')) {
            $rt = Route::post('clientes/{key}/seed-statement', [ClientesController::class, 'seedStatement'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.seedStatement');

            if ($isLocal) $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        if (method_exists(ClientesController::class, 'sendCredentialsAndMaybeStatement')) {
            $rt = Route::post('clientes/{key}/send-credentials', [ClientesController::class, 'sendCredentialsAndMaybeStatement'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.sendCredentials');

            if ($isLocal) $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }
    } else {
        Route::get('clientes', fn () => response('<h1>Clientes</h1><p>Pendiente de implementar.</p>', 200))
            ->middleware(perm_mw('clientes.ver'))
            ->name('clientes.index');
    }

    /*
    |--------------------------------------------------------------------------
    | Soporte interno admin
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | Logout admin
    |--------------------------------------------------------------------------
    */
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('logout', function () {
        return redirect()->route('admin.home');
    })->name('logout.get');

    /*
    |--------------------------------------------------------------------------
    | DEV / QA interno
    |--------------------------------------------------------------------------
    */
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
    | BILLING
    |--------------------------------------------------------------------------
    */
    Route::prefix('billing')->name('billing.')->group(function () use ($thrAdminPosts, $isLocal) {
        // Accounts
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

        // Invoice requests helper
        Route::post('invoices/requests/{id}/email-ready', [InvoiceRequestsController::class, 'emailReady'])
            ->whereNumber('id')
            ->name('invoices.requests.email_ready');

        Route::post('invoices/requests/{id}/attach', [InvoiceRequestsController::class, 'attachInvoice'])
            ->whereNumber('id')
            ->name('invoices.requests.attach');

        Route::get('invoices/{invoiceId}/download/{kind}', [InvoiceRequestsController::class, 'downloadInvoice'])
            ->where([
                'invoiceId' => '[0-9]+',
                'kind'      => '(pdf|xml)',
            ])
            ->name('invoices.download');

        // HUB
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

        // Statements legacy deshabilitado visualmente -> redirección obligatoria a V2
        Route::get('statements', function () {
            return redirect()->route('admin.billing.statements_v2.index', [
                'period' => now()->format('Y-m'),
            ]);
        })->name('statements.index');
        
               // Statements V2 (nuevo, aislado del legacy y del HUB actual)
        Route::prefix('statements-v2')->name('statements_v2.')->group(function () use ($thrAdminPosts, $isLocal) {
            Route::get('/', [\App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller::class, 'index'])
                ->name('index');

            $statementsV2BulkSend = Route::post('bulk/send', [\App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller::class, 'sendBulk'])
                ->middleware($thrAdminPosts)
                ->name('bulk.send');

            $statementsV2AdvancePayments = Route::post('payments/advance', [\App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller::class, 'registerAdvancePayments'])
                ->middleware($thrAdminPosts)
                ->name('payments.advance');

            $statementsV2BulkPayments = Route::post('payments/bulk', [\App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller::class, 'registerBulkPayments'])
                ->middleware($thrAdminPosts)
                ->name('payments.bulk');

            $statementsV2CommercialAgreement = Route::post(
                '{accountId}/commercial-agreement',
                [\App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller::class, 'saveCommercialAgreement']
            )
                ->where([
                    'accountId' => '[A-Za-z0-9\-]+',
                ])
                ->middleware($thrAdminPosts)
                ->name('commercial_agreement.save');

            Route::get('{accountId}/{period}/preview', [\App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller::class, 'preview'])
                ->where([
                    'accountId' => '[A-Za-z0-9\-]+',
                    'period'    => '\d{4}-(0[1-9]|1[0-2])',
                ])
                ->name('preview');

            Route::get('{accountId}/{period}/download', [\App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller::class, 'download'])
                ->where([
                    'accountId' => '[A-Za-z0-9\-]+',
                    'period'    => '\d{4}-(0[1-9]|1[0-2])',
                ])
                ->name('download');

            Route::get('{accountId}/{period}/email/preview', [\App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller::class, 'emailPreview'])
                ->where([
                    'accountId' => '[A-Za-z0-9\-]+',
                    'period'    => '\d{4}-(0[1-9]|1[0-2])',
                ])
                ->name('email.preview');

            $statementsV2StatusUpdate = Route::post('{accountId}/{period}/status', [\App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller::class, 'updateStatus'])
                ->where([
                    'accountId' => '[A-Za-z0-9\-]+',
                    'period'    => '\d{4}-(0[1-9]|1[0-2])',
                ])
                ->middleware($thrAdminPosts)
                ->name('status.update');

            $statementsV2EmailSend = Route::post('{accountId}/{period}/email/send', [\App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller::class, 'sendEmail'])
                ->where([
                    'accountId' => '[A-Za-z0-9\-]+',
                    'period'    => '\d{4}-(0[1-9]|1[0-2])',
                ])
                ->middleware($thrAdminPosts)
                ->name('email.send');

            if ($isLocal) {
                foreach ([
                    $statementsV2BulkSend,
                    $statementsV2AdvancePayments,
                    $statementsV2BulkPayments,
                    $statementsV2CommercialAgreement,
                    $statementsV2StatusUpdate,
                    $statementsV2EmailSend,
                ] as $rt) {
                    $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                }
            }
        });

        // Email Estados
        Route::get('statement-emails', [BillingStatementEmailsController::class, 'index'])
            ->name('statement_emails.index');

        Route::get('statement-emails/{id}', [BillingStatementEmailsController::class, 'show'])
            ->whereNumber('id')
            ->name('statement_emails.show');

        Route::get('statement-emails/{id}/preview', [BillingStatementEmailsController::class, 'preview'])
            ->whereNumber('id')
            ->name('statement_emails.preview');

        $statementEmailsResend = Route::post('statement-emails/{id}/resend', [BillingStatementEmailsController::class, 'resend'])
            ->whereNumber('id')
            ->middleware($thrAdminPosts)
            ->name('statement_emails.resend');

        if ($isLocal) {
            $statementEmailsResend->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

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

        // HUB main
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

        Route::put('payments/{id}', [PaymentsController::class, 'update'])
            ->whereNumber('id')
            ->name('payments.update');

        Route::delete('payments/{id}', [PaymentsController::class, 'destroy'])
            ->whereNumber('id')
            ->name('payments.destroy');

        Route::post('payments/{id}/email', [PaymentsController::class, 'emailReceipt'])
            ->whereNumber('id')
            ->name('payments.email');

        // Facturas (requests)
        Route::get('invoices/requests', [InvoiceRequestsController::class, 'index'])->name('invoices.requests.index');

        Route::get('invoices/requests/{id}', [InvoiceRequestsController::class, 'show'])
            ->whereNumber('id')
            ->name('invoices.requests.show');

        Route::post('invoices/requests/{id}/status', [InvoiceRequestsController::class, 'setStatus'])
            ->whereNumber('id')
            ->name('invoices.requests.status');

        Route::post('invoices/requests/{id}/approve-generate', [InvoiceRequestsController::class, 'approveAndGenerate'])
            ->whereNumber('id')
            ->name('invoices.requests.approve_generate');

        Route::post('invoices/requests/{id}/stamp', [InvoiceRequestsController::class, 'stamp'])
            ->whereNumber('id')
            ->name('invoices.requests.stamp');

        Route::post('invoices/requests/{id}/retry-stamp', [InvoiceRequestsController::class, 'retryStamp'])
            ->whereNumber('id')
            ->name('invoices.requests.retry_stamp');

        Route::post('invoices/requests/{id}/send', [InvoiceRequestsController::class, 'sendInvoice'])
            ->whereNumber('id')
            ->name('invoices.requests.send');

        Route::post('invoices/requests/{id}/resend', [InvoiceRequestsController::class, 'resendInvoice'])
            ->whereNumber('id')
            ->name('invoices.requests.resend');

        // Invoicing module
       Route::prefix('invoicing')->name('invoicing.')->group(function () use ($thrAdminPosts, $isLocal) {
            Route::get('/', [InvoicingDashboardController::class, 'index'])->name('dashboard');

            Route::get('requests', [InvoiceRequestsController::class, 'index'])->name('requests.index');
            Route::get('requests/{id}', [InvoiceRequestsController::class, 'show'])
                ->whereNumber('id')
                ->name('requests.show');

            Route::post('requests/{id}/approve-generate', [InvoiceRequestsController::class, 'approveAndGenerate'])
                ->whereNumber('id')
                ->name('requests.approve_generate');

            Route::post('requests/{id}/retry-stamp', [InvoiceRequestsController::class, 'retryStamp'])
                ->whereNumber('id')
                ->name('requests.retry_stamp');

            Route::post('requests/{id}/send', [InvoiceRequestsController::class, 'sendInvoice'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('requests.send');

            Route::post('requests/{id}/resend', [InvoiceRequestsController::class, 'resendInvoice'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('requests.resend');

            Route::get('invoices', [InvoicesController::class, 'index'])->name('invoices.index');

            Route::get('invoices/form-seed', [InvoicesController::class, 'formSeed'])
                ->name('invoices.form_seed');

            Route::get('invoices/search-emisores', [InvoicesController::class, 'searchEmisores'])
                ->name('invoices.search_emisores');

            Route::get('invoices/search-receptores', [InvoicesController::class, 'searchReceptores'])
                ->name('invoices.search_receptores');

            Route::get('invoices/create', [InvoicesController::class, 'create'])
                ->name('invoices.create');

            Route::get('invoices/{id}', [InvoicesController::class, 'show'])
                ->whereNumber('id')
                ->name('invoices.show');

            Route::get('invoices/{id}/download/{kind}', [InvoicesController::class, 'download'])
                ->where([
                    'id'   => '[0-9]+',
                    'kind' => '(pdf|xml)',
                ])
                ->name('invoices.download');

            $invoiceCancel = Route::post('invoices/{id}/cancel', [InvoicesController::class, 'cancel'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('invoices.cancel');

            $invoiceStoreManual = Route::post('invoices/manual', [InvoicesController::class, 'storeManual'])
                ->middleware($thrAdminPosts)
                ->name('invoices.store_manual');

            $invoiceBulkStoreManual = Route::post('invoices/manual/bulk', [InvoicesController::class, 'bulkStoreManual'])
                ->middleware($thrAdminPosts)
                ->name('invoices.bulk_store_manual');

            $invoiceSend = Route::post('invoices/{id}/send', [InvoicesController::class, 'send'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('invoices.send');

            $invoiceResend = Route::post('invoices/{id}/resend', [InvoicesController::class, 'resend'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('invoices.resend');

            $invoiceBulkSend = Route::post('invoices/bulk-send', [InvoicesController::class, 'bulkSend'])
                ->middleware($thrAdminPosts)
                ->name('invoices.bulk_send');

            Route::get('settings', [InvoicingSettingsController::class, 'index'])->name('settings.index');
            Route::post('settings', [InvoicingSettingsController::class, 'save'])->name('settings.save');

            Route::get('emisores', [EmisoresController::class, 'index'])->name('emisores.index');
            Route::get('emisores/create', [EmisoresController::class, 'create'])->name('emisores.create');

            $emisoresSync = Route::post('emisores/sync-facturotopia', [EmisoresController::class, 'syncFacturotopia'])
                ->middleware($thrAdminPosts)
                ->name('emisores.sync_facturotopia');

            $emisoresStore = Route::post('emisores', [EmisoresController::class, 'store'])
                ->middleware($thrAdminPosts)
                ->name('emisores.store');

            Route::get('emisores/{id}/edit', [EmisoresController::class, 'edit'])
                ->whereNumber('id')
                ->name('emisores.edit');

            $emisoresUpdate = Route::put('emisores/{id}', [EmisoresController::class, 'update'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('emisores.update');

            $emisoresDestroy = Route::delete('emisores/{id}', [EmisoresController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('emisores.destroy');

            Route::get('receptores', [ReceptoresController::class, 'index'])->name('receptores.index');
            Route::get('receptores/create', [ReceptoresController::class, 'create'])->name('receptores.create');

            $receptoresSync = Route::post('receptores/sync-facturotopia', [ReceptoresController::class, 'syncFacturotopia'])
                ->middleware($thrAdminPosts)
                ->name('receptores.sync_facturotopia');

            $receptoresStore = Route::post('receptores', [ReceptoresController::class, 'store'])
                ->middleware($thrAdminPosts)
                ->name('receptores.store');

            Route::get('receptores/{id}/edit', [ReceptoresController::class, 'edit'])
                ->whereNumber('id')
                ->name('receptores.edit');

            $receptoresUpdate = Route::put('receptores/{id}', [ReceptoresController::class, 'update'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('receptores.update');

            $receptoresDestroy = Route::delete('receptores/{id}', [ReceptoresController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware($thrAdminPosts)
                ->name('receptores.destroy');

            Route::get('logs', [InvoicingLogsController::class, 'index'])->name('logs.index');

            if ($isLocal) {
                foreach ([
                    $invoiceCancel,
                    $invoiceStoreManual,
                    $invoiceBulkStoreManual,
                    $invoiceSend,
                    $invoiceResend,
                    $invoiceBulkSend,
                    $emisoresSync,
                    $emisoresStore,
                    $emisoresUpdate,
                    $emisoresDestroy,
                    $receptoresSync,
                    $receptoresStore,
                    $receptoresUpdate,
                    $receptoresDestroy,
                ] as $rt) {
                    $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                }
            }
        });

        Route::prefix('vault-access')->name('vault_access.')->group(function () use ($thrAdminPosts, $isLocal) {
            Route::get('/', [SatOpsVaultAccessController::class, 'index'])->name('index');

            $vaultV1Update = Route::post('account/{cuentaId}/v1', [SatOpsVaultAccessController::class, 'updateV1'])
                ->where('cuentaId', '[A-Za-z0-9\-]+')
                ->middleware($thrAdminPosts)
                ->name('v1.update');

            $vaultV2ModuleUpdate = Route::post('account/{cuentaId}/v2-module', [SatOpsVaultAccessController::class, 'updateV2Module'])
                ->where('cuentaId', '[A-Za-z0-9\-]+')
                ->middleware($thrAdminPosts)
                ->name('v2_module.update');

            $vaultV2UsersUpdate = Route::post('account/{cuentaId}/v2-users', [SatOpsVaultAccessController::class, 'updateV2Users'])
                ->where('cuentaId', '[A-Za-z0-9\-]+')
                ->middleware($thrAdminPosts)
                ->name('v2_users.update');

            $vaultV2UserStore = Route::post(
                'account/{cuentaId}/users',
                [SatOpsVaultAccessController::class, 'storeAccountUser']
            )
                ->where('cuentaId', '[A-Za-z0-9\-]+')
                ->middleware($thrAdminPosts)
                ->name('users.store');

            $vaultV2UserUpdate = Route::post(
                'account/{cuentaId}/users/{usuarioId}/update',
                [SatOpsVaultAccessController::class, 'updateAccountUser']
            )
                ->where([
                    'cuentaId'  => '[A-Za-z0-9\-]+',
                    'usuarioId' => '[A-Za-z0-9\-]+',
                ])
                ->middleware($thrAdminPosts)
                ->name('users.update');

            $vaultV2UserDelete = Route::post(
                'account/{cuentaId}/v2-users/{usuarioId}/delete',
                [SatOpsVaultAccessController::class, 'deleteV2UserAccess']
            )
                ->where([
                    'cuentaId'  => '[A-Za-z0-9\-]+',
                    'usuarioId' => '[A-Za-z0-9\-]+',
                ])
                ->middleware($thrAdminPosts)
                ->name('v2_users.delete');

            if ($isLocal) {
                foreach ([
                    $vaultV1Update,
                    $vaultV2ModuleUpdate,
                    $vaultV2UsersUpdate,
                    $vaultV2UserStore,
                    $vaultV2UserUpdate,
                    $vaultV2UserDelete,
                ] as $rt) {
                    $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                }
            }
        });

        // SAT admin dentro de billing
        Route::prefix('sat')->name('sat.')->group(function () use ($thrAdminPosts, $isLocal) {
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

            Route::post('billing/invoicing/invoices/{id}/stamp', [InvoicesController::class, 'stamp'])
                ->name('admin.billing.invoicing.invoices.stamp');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | FINANZAS
    |--------------------------------------------------------------------------
    */
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('cost-centers', [CostCentersController::class, 'index'])
            ->name('cost_centers.index');

        Route::get('income', [IncomeController::class, 'index'])
            ->name('income.index');

        Route::post('income/row', [IncomeActionsController::class, 'upsert'])
            ->name('income.row');

        Route::delete('income/row/{id}', [IncomeActionsController::class, 'destroy'])
            ->whereNumber('id')
            ->name('income.row.destroy');

        Route::get('expenses', [ExpensesController::class, 'index'])
            ->name('expenses.index');

        Route::post('expenses/row', [ExpensesActionsController::class, 'upsert'])
            ->name('expenses.row');

        Route::delete('expenses/row/{id}', [ExpensesActionsController::class, 'destroy'])
            ->whereNumber('id')
            ->name('expenses.row.destroy');

        Route::get('sales', [SalesController::class, 'index'])
            ->name('sales.index');

        Route::get('sales/create', [SalesController::class, 'create'])
            ->name('sales.create');

        Route::post('sales', [SalesController::class, 'store'])
            ->name('sales.store');

        Route::post('sales/{id}/toggle-include', [SalesController::class, 'toggleInclude'])
            ->whereNumber('id')
            ->name('sales.toggleInclude');

        Route::get('vendors', [VendorsController::class, 'index'])
            ->name('vendors.index');

        Route::get('vendors/create', [VendorsController::class, 'create'])
            ->name('vendors.create');

        Route::post('vendors', [VendorsController::class, 'store'])
            ->name('vendors.store');

        Route::get('commissions', [CommissionsController::class, 'index'])
            ->name('commissions.index');

        Route::get('projections', [ProjectionsController::class, 'index'])
            ->name('projections.index');
    });

    /*
    |--------------------------------------------------------------------------
    | USUARIOS (ADMIN)
    |--------------------------------------------------------------------------
    */
    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('administrativos', [AdministrativosController::class, 'index'])
            ->name('administrativos.index');

        Route::get('administrativos/create', [AdministrativosController::class, 'create'])
            ->name('administrativos.create');

        Route::post('administrativos', [AdministrativosController::class, 'store'])
            ->name('administrativos.store');

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
    | SAT canónico
    |--------------------------------------------------------------------------
    */
    Route::prefix('sat')->name('sat.')->group(function () use ($thrAdminPosts, $isLocal) {
        Route::prefix('ops')->name('ops.')->group(function () use ($isLocal, $thrAdminPosts) {
            Route::get('/', [SatOpsController::class, 'index'])->name('index');

            Route::prefix('rfcs')->name('rfcs.')->group(function () use ($thrAdminPosts, $isLocal) {
                Route::get('/', [SatOpsRfcsController::class, 'index'])->name('index');

                Route::get('{id}', [SatOpsRfcsController::class, 'show'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->name('show');

                Route::get('{id}/operational-data', [SatOpsRfcsController::class, 'operationalData'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->name('operational_data');

                Route::get('{id}/download/{kind}', [SatOpsRfcsController::class, 'download'])
                    ->where([
                        'id'   => '[A-Za-z0-9\-]+',
                        'kind' => 'fiel_cer|fiel_key|csd_cer|csd_key',
                    ])
                    ->name('download');

                $store = Route::post('/', [SatOpsRfcsController::class, 'store'])
                    ->middleware($thrAdminPosts)
                    ->name('store');

                $update = Route::post('{id}/update', [SatOpsRfcsController::class, 'update'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->middleware($thrAdminPosts)
                    ->name('update');

                $destroy = Route::post('{id}/delete', [SatOpsRfcsController::class, 'destroy'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->middleware($thrAdminPosts)
                    ->name('delete');

                if ($isLocal) {
                    foreach ([$store, $update, $destroy] as $rt) {
                        $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                    }
                }
            });

            Route::prefix('credentials')->name('credentials.')->group(function () use ($isLocal) {
                Route::get('/', [SatOpsCredentialsController::class, 'index'])->name('index');

                Route::get('{id}/cer', [SatOpsCredentialsController::class, 'cer'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->name('cer');

                Route::get('{id}/key', [SatOpsCredentialsController::class, 'key'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->name('key');

                $destroy = Route::delete('{id}', [SatOpsCredentialsController::class, 'destroy'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->name('destroy');

                if ($isLocal) {
                    $destroy->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                }
            });

            Route::prefix('downloads')->name('downloads.')->group(function () use ($isLocal, $thrAdminPosts) {
                Route::get('/', [SatOpsDownloadsController::class, 'index'])->name('index');

                $manualUploadFromQuote = Route::post(
                    'upload-from-quote',
                    [SatOpsDownloadsController::class, 'uploadFromPaidQuote']
                )
                    ->middleware($thrAdminPosts)
                    ->name('upload_from_quote');

                $manualUploadFromProfile = Route::post(
                    'upload-from-profile',
                    [SatOpsDownloadsController::class, 'uploadFromProfile']
                )
                    ->middleware($thrAdminPosts)
                    ->name('upload_from_profile');

                $manualReplaceUpload = Route::post(
                    'replace-upload',
                    [SatOpsDownloadsController::class, 'replaceUpload']
                )
                    ->middleware($thrAdminPosts)
                    ->name('replace_upload');

                Route::get('download/{type}/{id}', [SatOpsDownloadsController::class, 'download'])
                    ->where([
                        'type' => 'metadata|xml|report|vault|satdownload',
                        'id'   => '[A-Za-z0-9\-]+',
                    ])
                    ->name('download');

                /*
                |----------------------------------------------------------------------
                | Operaciones legacy / generales
                |----------------------------------------------------------------------
                */
                $delete = Route::delete('delete/{type}/{id}', [SatOpsDownloadsController::class, 'destroy'])
                    ->middleware($thrAdminPosts)
                    ->where([
                        'type' => 'metadata|xml|report|vault|satdownload',
                        'id'   => '[A-Za-z0-9\-]+',
                    ])
                    ->name('delete');

                $bulkDeleteFiles = Route::post('bulk/files/delete', [SatOpsDownloadsController::class, 'bulkDestroyFiles'])
                    ->middleware($thrAdminPosts)
                    ->name('bulk.files.delete');

                /*
                |----------------------------------------------------------------------
                | Metadata
                |----------------------------------------------------------------------
                */
                $metadataUpdate = Route::match(['PATCH', 'POST'], 'metadata/{id}/update', [SatOpsDownloadsController::class, 'updateMetadata'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('metadata.update');

                $metadataResetCount = Route::post('metadata/{id}/reset-count', [SatOpsDownloadsController::class, 'resetMetadataCount'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('metadata.reset_count');

                $metadataRecount = Route::post('metadata/{id}/recount', [SatOpsDownloadsController::class, 'recountMetadata'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('metadata.recount');

                $metadataPurgeItems = Route::post('metadata/{id}/purge-items', [SatOpsDownloadsController::class, 'purgeMetadataItems'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('metadata.purge_items');

                $metadataDestroyFull = Route::delete('metadata/{id}/destroy-full', [SatOpsDownloadsController::class, 'destroyMetadataFull'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('metadata.destroy_full');
                
                                $metadataRecordPurgeFiltered = Route::post(
                    'metadata/records/purge-filtered',
                    [SatOpsDownloadsController::class, 'purgeFilteredMetadataRecords']
                )
                    ->middleware($thrAdminPosts)
                    ->name('metadata.records.purge_filtered');

                $reportRecordPurgeFiltered = Route::post(
                    'report/records/purge-filtered',
                    [SatOpsDownloadsController::class, 'purgeFilteredReportRecords']
                )
                    ->middleware($thrAdminPosts)
                    ->name('report.records.purge_filtered');
                
                $metadataBatchDestroy = Route::delete(
                    'metadata/batch/{uploadId}',
                    [SatOpsDownloadsController::class, 'destroyMetadataBatch']
                )
                    ->middleware($thrAdminPosts)
                    ->whereNumber('uploadId')
                    ->name('metadata.batch.destroy');
                
                $reportBatchDestroy = Route::delete(
                    'report/batch/{uploadId}',
                    [SatOpsDownloadsController::class, 'destroyReportBatch']
                )
                    ->middleware($thrAdminPosts)
                    ->whereNumber('uploadId')
                    ->name('report.batch.destroy');

                /*
                |----------------------------------------------------------------------
                | XML
                |----------------------------------------------------------------------
                */
                $xmlUpdate = Route::match(['PATCH', 'POST'], 'xml/{id}/update', [SatOpsDownloadsController::class, 'updateXml'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('xml.update');

                $xmlResetCount = Route::post('xml/{id}/reset-count', [SatOpsDownloadsController::class, 'resetXmlCount'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('xml.reset_count');

                $xmlRecount = Route::post('xml/{id}/recount', [SatOpsDownloadsController::class, 'recountXml'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('xml.recount');

                $xmlPurgeCfdi = Route::post('xml/{id}/purge-cfdi', [SatOpsDownloadsController::class, 'purgeXmlCfdi'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('xml.purge_cfdi');

                $xmlDestroyFull = Route::delete('xml/{id}/destroy-full', [SatOpsDownloadsController::class, 'destroyXmlFull'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('xml.destroy_full');

                /*
                |----------------------------------------------------------------------
                | Reportes
                |----------------------------------------------------------------------
                */
                $reportUpdate = Route::match(['PATCH', 'POST'], 'report/{id}/update', [SatOpsDownloadsController::class, 'updateReport'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('report.update');

                $reportResetCount = Route::post('report/{id}/reset-count', [SatOpsDownloadsController::class, 'resetReportCount'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('report.reset_count');

                $reportRecount = Route::post('report/{id}/recount', [SatOpsDownloadsController::class, 'recountReport'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('report.recount');

                $reportPurgeItems = Route::post('report/{id}/purge-items', [SatOpsDownloadsController::class, 'purgeReportItems'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('report.purge_items');

                $reportDestroyFull = Route::delete('report/{id}/destroy-full', [SatOpsDownloadsController::class, 'destroyReportFull'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('report.destroy_full');

                                /*
                |--------------------------------------------------------------------------
                | Registros derivados · Metadata
                |--------------------------------------------------------------------------
                */
                $metadataRecordDelete = Route::delete('metadata/records/{id}', [SatOpsDownloadsController::class, 'destroyMetadataRecord'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('metadata.records.delete');

                $metadataRecordBulkDelete = Route::post('metadata/records/bulk-delete', [SatOpsDownloadsController::class, 'bulkDestroyMetadataRecords'])
                    ->middleware($thrAdminPosts)
                    ->name('metadata.records.bulk_delete');
                
                $metadataBatchDestroy = Route::delete(
                        'metadata/batch/{uploadId}',
                        [SatOpsDownloadsController::class, 'destroyMetadataBatch']
                    )
                        ->middleware($thrAdminPosts)
                        ->whereNumber('uploadId')
                        ->name('metadata.batch.destroy');

                /*
                |--------------------------------------------------------------------------
                | Registros derivados · Reportes
                |--------------------------------------------------------------------------
                */
                $reportRecordDelete = Route::delete('report/records/{id}', [SatOpsDownloadsController::class, 'destroyReportRecord'])
                    ->middleware($thrAdminPosts)
                    ->whereNumber('id')
                    ->name('report.records.delete');

                $reportRecordBulkDelete = Route::post('report/records/bulk-delete', [SatOpsDownloadsController::class, 'bulkDestroyReportRecords'])
                    ->middleware($thrAdminPosts)
                    ->name('report.records.bulk_delete');
                
                $reportBatchDestroy = Route::delete(
                        'report/batch/{uploadId}',
                        [SatOpsDownloadsController::class, 'destroyReportBatch']
                    )
                        ->middleware($thrAdminPosts)
                        ->whereNumber('uploadId')
                        ->name('report.batch.destroy');

                /*
                |----------------------------------------------------------------------
                | CFDI indexados
                |----------------------------------------------------------------------
                */
                $cfdiDelete = Route::delete('cfdi/{source}/{id}', [SatOpsDownloadsController::class, 'destroyCfdi'])
                    ->middleware($thrAdminPosts)
                    ->where([
                        'source' => 'vault_cfdi|user_cfdi',
                        'id'     => '[A-Za-z0-9\-]+',
                    ])
                    ->name('cfdi.delete');

                $bulkDeleteCfdi = Route::post('bulk/cfdi/delete', [SatOpsDownloadsController::class, 'bulkDestroyCfdi'])
                    ->middleware($thrAdminPosts)
                    ->name('bulk.cfdi.delete');

                $cfdiPurge = Route::post('cfdi/purge', [SatOpsDownloadsController::class, 'purgeCfdi'])
                    ->middleware($thrAdminPosts)
                    ->name('cfdi.purge');

                if ($isLocal) {
                    foreach ([
                        $manualUploadFromQuote,
                        $manualUploadFromProfile,
                        $manualReplaceUpload,
                        $delete,
                        $bulkDeleteFiles,

                        $metadataUpdate,
                        $metadataResetCount,
                        $metadataRecount,
                        $metadataPurgeItems,
                        $metadataDestroyFull,

                        $xmlUpdate,
                        $xmlResetCount,
                        $xmlRecount,
                        $xmlPurgeCfdi,
                        $xmlDestroyFull,

                        $reportUpdate,
                        $reportResetCount,
                        $reportRecount,
                        $reportPurgeItems,
                        $reportDestroyFull,

                        $metadataRecordDelete,
                        $metadataRecordBulkDelete,
                        $reportRecordDelete,
                        $reportRecordBulkDelete,
                        $metadataRecordPurgeFiltered,
                        $reportRecordPurgeFiltered,
                        $metadataBatchDestroy,
                        $reportBatchDestroy,
                        $metadataBatchDestroy,
                        $reportBatchDestroy,

                        $cfdiDelete,
                        $bulkDeleteCfdi,
                        $cfdiPurge,
                    ] as $rt) {
                        $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                    }
                }
            });

            Route::prefix('quotes')->name('quotes.')->group(function () use ($isLocal, $thrAdminPosts) {
                Route::get('/', [SatOpsQuotesController::class, 'index'])->name('index');

                $quotesStatusUpdate = Route::post('{id}/status', [SatOpsQuotesController::class, 'updateStatus'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->middleware($thrAdminPosts)
                    ->name('status.update');

                $quotesUpdate = Route::post('{id}/update', [SatOpsQuotesController::class, 'updateQuote'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->middleware($thrAdminPosts)
                    ->name('update');

                $quotesConfirm = Route::post('{id}/confirm', [SatOpsQuotesController::class, 'confirmQuote'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->middleware($thrAdminPosts)
                    ->name('confirm');

                $quotesReject = Route::post('{id}/reject', [SatOpsQuotesController::class, 'rejectQuote'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->middleware($thrAdminPosts)
                    ->name('reject');

                Route::get('{id}/operational-data', [SatOpsQuotesController::class, 'showOperationalData'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->name('operational_data');

                $quotesMarkSatRequested = Route::post('{id}/sat-request', [SatOpsQuotesController::class, 'markSatRequested'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->middleware($thrAdminPosts)
                    ->name('sat_request');

                $quotesComplete = Route::post('{id}/complete', [SatOpsQuotesController::class, 'completeQuote'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->middleware($thrAdminPosts)
                    ->name('complete');
                
                $quotesApproveTransfer = Route::post('{id}/transfer/approve', [SatOpsQuotesController::class, 'approveTransfer'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->middleware($thrAdminPosts)
                    ->name('transfer.approve');

                $quotesRejectTransfer = Route::post('{id}/transfer/reject', [SatOpsQuotesController::class, 'rejectTransfer'])
                    ->where('id', '[A-Za-z0-9\-]+')
                    ->middleware($thrAdminPosts)
                    ->name('transfer.reject');

                if ($isLocal) {
                    foreach ([
                        $quotesStatusUpdate,
                        $quotesUpdate,
                        $quotesConfirm,
                        $quotesReject,
                        $quotesMarkSatRequested,
                        $quotesComplete,
                        $quotesApproveTransfer,
                        $quotesRejectTransfer,
                    ] as $rt) {
                        $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                    }
                }
            });
            Route::prefix('manual')->name('manual.')->group(function () {
                Route::get('/', [SatOpsManualRequestsController::class, 'index'])->name('index');
            });

            Route::prefix('payments')->name('payments.')->group(function () {
                Route::get('/', [SatOpsPaymentsController::class, 'index'])->name('index');
            });
        });

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

            Route::get('credentials/{id}/cer', [SatCredentialsController::class, 'downloadCer'])
                ->where('id', '[0-9]+')
                ->name('credentials.cer');

            Route::get('credentials/{id}/key', [SatCredentialsController::class, 'downloadKey'])
                ->where('id', '[0-9]+')
                ->name('credentials.key');
        });

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
    | Fallback interno admin
    |--------------------------------------------------------------------------
    */
    Route::fallback(fn () => redirect()->route('admin.home'));
});