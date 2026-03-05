<?php
// C:\wamp64\www\pactopia360_erp\routes\admin.php
// PACTOPIA360 · ADMIN routes (SOT)
// ✅ Mejoras incluidas (v2026-03-04):
// - Mantiene route:cache safe (feature-flags por method_exists)
// - Tracking billing OPEN/CLICK sin sesión/cookies
// - PayLink público sin sesión/cookies
// - Orden consistente, sin compat redirects que deben vivir en web.php
// - CSRF bypass SOLO en local y SOLO en POST/DELETE sensibles
// - ✅ FIX: /admin/clientes/{id} ya no hace loop (agrega GET clientes/{key} → show/edit)
// ✅ + /admin/_cfg para diagnóstico rápido del contexto admin (cookie/guard/driver)

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
|---------------------------------------------------------------------------
| Controladores ADMIN
|---------------------------------------------------------------------------
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

/*
|---------------------------------------------------------------------------
| CSRF middlewares (para quitar en local en algunos POST/DELETE)
|---------------------------------------------------------------------------
*/
use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;

/*
|---------------------------------------------------------------------------
| ENV + throttles
|---------------------------------------------------------------------------
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
|---------------------------------------------------------------------------
| Helper permisos → middleware 'can:perm,<clave>'
|---------------------------------------------------------------------------
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
|---------------------------------------------------------------------------
| Placeholder rápido (si falta una vista admin)
|---------------------------------------------------------------------------
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
|---------------------------------------------------------------------------
| ✅ Stack de middlewares a remover para endpoints públicos sin cookies/sesión
|---------------------------------------------------------------------------
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
|---------------------------------------------------------------------------
| ✅ Tracking público Billing (OPEN/CLICK) — SIN auth y SIN cookies/sesión
| Ruta final (por prefix global del provider): /admin/t/billing/*
| Names: admin.billing.hub.*
|---------------------------------------------------------------------------
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
|---------------------------------------------------------------------------
| ✅ PayLink público (GET) para Estados de Cuenta (HUB)
| Ruta final: /admin/billing/statements-hub/paylink
| Name: admin.billing.hub.paylink
|---------------------------------------------------------------------------
*/
Route::get('billing/statements-hub/paylink', [BillingStatementsHubController::class, 'payLink'])
    ->middleware('throttle:240,1')
    ->withoutMiddleware($noCookies)
    ->name('billing.hub.paylink');

/*
|---------------------------------------------------------------------------
| UI (heartbeat, log)
|---------------------------------------------------------------------------
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
|---------------------------------------------------------------------------
| ✅ Diag rápido (admin context): /admin/_cfg
|---------------------------------------------------------------------------
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
|---------------------------------------------------------------------------
| Auth admin (guest:admin + sesión aislada)
|---------------------------------------------------------------------------
*/
Route::middleware([
    'admin',        // middlewareGroup admin del Kernel
    'guest:admin',
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
|---------------------------------------------------------------------------
| Notificaciones públicas (contador)
|---------------------------------------------------------------------------
*/
Route::match(['GET', 'HEAD'], 'notificaciones/count', [NotificationController::class, 'count'])
    ->middleware('throttle:60,1')
    ->name('notificaciones.count');

/*
|---------------------------------------------------------------------------
| Área autenticada ADMIN (auth:admin + sesión aislada)
|---------------------------------------------------------------------------
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
                : (bool) ($u?->es_superadmin ?? false),
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

    /*
    |-----------------------------------------------------------------------
    | CLIENTES (accounts / soporte)
    |-----------------------------------------------------------------------
    */
    if (class_exists(\App\Http\Controllers\Admin\ClientesController::class)) {

        // ✅ COMPAT: POST /admin/clientes/create => store()
        $clientesCreateCompat = Route::post('clientes/create', [ClientesController::class, 'store'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.create.post_compat');

        if ($isLocal) {
            $clientesCreateCompat->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        $clientesStore = Route::post('clientes', [ClientesController::class, 'store'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.store');

        if ($isLocal) {
            $clientesStore->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        Route::get('clientes', [ClientesController::class, 'index'])
            ->middleware(perm_mw('clientes.ver'))
            ->name('clientes.index');

        /*
        | ✅ FIX: GET /admin/clientes/{key}
        | - Canon: show() si existe; si no, edit()
        | - Evita loop a /admin/home cuando se intenta abrir /admin/clientes/16
        */
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

        // ==========================
        // ✅ SAVE aliases (FIX UI legacy / forms)
        // - Algunas vistas mandan POST/PUT/PATCH a /clientes/{key}
        // - Otras mandan POST a /clientes/{key}/save (canónico actual)
        // ==========================
        $clientesSavePostDirect = Route::post('clientes/{key}', [ClientesController::class, 'save'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.save.post_direct');

        $clientesSavePutDirect = Route::match(['put', 'patch'], 'clientes/{key}', [ClientesController::class, 'save'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.save.put_direct');

        if ($isLocal) {
            foreach ([$clientesSavePostDirect, $clientesSavePutDirect] as $rt) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        // ✅ SAVE (canónico): acepta RFC / ID numérico / UUID
        $clientesSaveKey = Route::post('clientes/{key}/save', [ClientesController::class, 'save'])
            ->where('key', '[A-Za-z0-9\-]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.save');

        // ✅ COMPAT legacy: por si algún lugar todavía usa {rfc} explícito
        $clientesSaveRfcCompat = Route::post('clientes/{rfc}/save', [ClientesController::class, 'save'])
            ->where('rfc', '[A-Za-z0-9]+')
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.save.rfc_compat');

        if ($isLocal) {
            foreach ([$clientesSaveKey, $clientesSaveRfcCompat] as $rt) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        // Panel Billing embebible dentro de Admin Clientes (feature-flag)
        if (method_exists(ClientesController::class, 'billingPanel')) {
            Route::get('clientes/{key}/billing/panel', [ClientesController::class, 'billingPanel'])
                ->where('key', '[A-Za-z0-9\-]+')
                ->middleware(perm_mw('clientes.ver'))
                ->name('admin.clientes.billing.panel');
        }

        // Acciones CORE (feature-flag por método)
        if (method_exists(ClientesController::class, 'block')) {
            $rt = Route::post('clientes/{rfc}/block', [ClientesController::class, 'block'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.block');
            if ($isLocal) $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        if (method_exists(ClientesController::class, 'unblock')) {
            $rt = Route::post('clientes/{rfc}/unblock', [ClientesController::class, 'unblock'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.unblock');
            if ($isLocal) $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        if (method_exists(ClientesController::class, 'deactivate')) {
            $rt = Route::post('clientes/{rfc}/deactivate', [ClientesController::class, 'deactivate'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.deactivate');
            if ($isLocal) $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        if (method_exists(ClientesController::class, 'reactivate')) {
            $rt = Route::post('clientes/{rfc}/reactivate', [ClientesController::class, 'reactivate'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.reactivate');
            if ($isLocal) $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        if (method_exists(ClientesController::class, 'destroy')) {
            $rt = Route::post('clientes/{rfc}/destroy', [ClientesController::class, 'destroy'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.destroy');

            $rt2 = Route::post('clientes/{rfc}/delete', [ClientesController::class, 'destroy'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.delete');

            if ($isLocal) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                $rt2->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        // OTP / Verificaciones
        $resendV = Route::post('clientes/{rfc}/resend-email-verification', [ClientesController::class, 'resendEmailVerification'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.resendEmailVerification');

        $sendOtp = Route::post('clientes/{rfc}/send-phone-otp', [ClientesController::class, 'sendPhoneOtp'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.sendPhoneOtp');

        $forceMail = Route::post('clientes/{rfc}/force-email-verified', [ClientesController::class, 'forceEmailVerified'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forceEmailVerified');

        $forcePhone = Route::post('clientes/{rfc}/force-phone-verified', [ClientesController::class, 'forcePhoneVerified'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forcePhoneVerified');

        // Aliases compat
        $resendV2 = Route::post('clientes/{rfc}/resend-email', [ClientesController::class, 'resendEmailVerification'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.resendEmail');

        $sendOtp2 = Route::post('clientes/{rfc}/send-otp', [ClientesController::class, 'sendPhoneOtp'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.sendOtp');

        $forceMail2 = Route::post('clientes/{rfc}/force-email', [ClientesController::class, 'forceEmailVerified'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forceEmail');

        $forcePhone2 = Route::post('clientes/{rfc}/force-phone', [ClientesController::class, 'forcePhoneVerified'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.forcePhone');

        if ($isLocal) {
            foreach ([$resendV, $sendOtp, $forceMail, $forcePhone, $resendV2, $sendOtp2, $forceMail2, $forcePhone2] as $rt) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        // Reset password + Email credenciales
        $rp = Route::match(['GET', 'POST'], 'clientes/{rfcOrId}/reset-password', [ClientesController::class, 'resetPassword'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.resetPassword');

        $emailCreds = Route::post('clientes/{rfc}/email-credentials', [ClientesController::class, 'emailCredentials'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.emailCredentials');

        if ($isLocal) {
            foreach ([$rp, $emailCreds] as $rt) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        // Impersonate
        $imp = Route::post('clientes/{rfc}/impersonate', [ClientesController::class, 'impersonate'])
            ->middleware([$thrAdminPosts, ...perm_mw(['clientes.ver', 'clientes.impersonate'])])
            ->name('clientes.impersonate');

        $impStop1 = Route::post('clientes/impersonate-stop', [ClientesController::class, 'impersonateStop'])
            ->middleware($thrAdminPosts)
            ->name('clientes.impersonateStop');

        $impStop2 = Route::post('clientes/impersonate/stop', [ClientesController::class, 'impersonateStop'])
            ->middleware($thrAdminPosts)
            ->name('clientes.impersonate.stop');

        if ($isLocal) {
            foreach ([$imp, $impStop1, $impStop2] as $rt) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        // Sync + Bulk
        $sync = Route::post('clientes/sync-to-clientes', [ClientesController::class, 'syncToClientes'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.syncToClientes');

        $bulk = Route::post('clientes/bulk', [ClientesController::class, 'bulk'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.bulk');

        if ($isLocal) {
            foreach ([$sync, $bulk] as $rt) {
                $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }

        // Opcional (feature-flag)
        if (method_exists(ClientesController::class, 'recipientsUpsert')) {
            $r1 = Route::post('clientes/{rfc}/recipients-upsert', [ClientesController::class, 'recipientsUpsert'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.recipientsUpsert');

            $r2 = Route::post('clientes/{rfc}/recipients', [ClientesController::class, 'recipientsUpsert'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.recipients');

            if ($isLocal) {
                foreach ([$r1, $r2] as $rt) {
                    $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                }
            }
        }

        if (method_exists(ClientesController::class, 'seedStatement')) {
            $rt = Route::post('clientes/{rfc}/seed-statement', [ClientesController::class, 'seedStatement'])
                ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
                ->name('clientes.seedStatement');

            if ($isLocal) $rt->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        if (method_exists(ClientesController::class, 'sendCredentialsAndMaybeStatement')) {
            $rt = Route::post('clientes/{rfc}/send-credentials', [ClientesController::class, 'sendCredentialsAndMaybeStatement'])
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
    |-----------------------------------------------------------------------
    | Soporte interno admin
    |-----------------------------------------------------------------------
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
    |-----------------------------------------------------------------------
    | Logout admin (POST real + GET compat)
    |-----------------------------------------------------------------------
    */
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('logout', function () {
        return redirect()->route('admin.home');
    })->name('logout.get');

    /*
    |-----------------------------------------------------------------------
    | DEV / QA interno
    |-----------------------------------------------------------------------
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
    |-----------------------------------------------------------------------
    | BILLING (DEBE IR ANTES DEL FALLBACK)
    |-----------------------------------------------------------------------
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

        // Facturas (archivos PDF/XML) – Admin
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

        // Legacy statements
        Route::get('statements', [BillingStatementsController::class, 'index'])
            ->name('statements.index');

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
        Route::post('invoices/requests/{id}/status', [InvoiceRequestsController::class, 'setStatus'])
            ->whereNumber('id')
            ->name('invoices.requests.status');

        /*
        |-----------------------------------------------------------------------
        | ✅ COMPAT: SAT Admin bajo billing (admin.billing.sat.*)
        | Canon actual: admin.sat.* (fuera de billing)
        |-----------------------------------------------------------------------
        */
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
        });
    });

    /*
    |-----------------------------------------------------------------------
    | FINANZAS (Admin) — base (CANÓNICO)  admin.finance.*
    |-----------------------------------------------------------------------
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
    |-----------------------------------------------------------------------
    | USUARIOS (ADMIN)
    |-----------------------------------------------------------------------
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
    |-----------------------------------------------------------------------
    | SAT · Lista de precios + Códigos de descuento (Admin) — CANÓNICO
    | admin.sat.*
    |-----------------------------------------------------------------------
    */
    Route::prefix('sat')->name('sat.')->group(function () use ($thrAdminPosts, $isLocal) {

        /*
        |-------------------------------------------------------------------
        | SAT · OPERACIÓN (Backoffice) — admin.sat.ops.*
        |-------------------------------------------------------------------
        */
        Route::prefix('ops')->name('ops.')->group(function () {

            Route::get('/', [SatOpsController::class, 'index'])->name('index');

            Route::prefix('credentials')->name('credentials.')->group(function () {

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

                if (app()->environment(['local', 'development', 'testing'])) {
                    $destroy->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
                }
            });

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

        // SAT · PRICE RULES (canónico)
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

        // SAT · DISCOUNT CODES (canónico)
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
    |-----------------------------------------------------------------------
    | ✅ Fallback interno admin (SIEMPRE AL FINAL)
    |-----------------------------------------------------------------------
    */
    Route::fallback(fn () => redirect()->route('admin.home'));
});