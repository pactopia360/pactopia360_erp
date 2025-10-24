<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

// Controladores ADMIN
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


// CSRF (para desactivar en local cuando convenga)
use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;

$isLocal = app()->environment(['local','development','testing']);

// Throttles
$thrLogin        = $isLocal ? 'throttle:60,1'  : 'throttle:5,1';
$thrUiHeartbeat  = $isLocal ? 'throttle:120,1' : 'throttle:60,1';
$thrUiLog        = $isLocal ? 'throttle:480,1' : 'throttle:240,1';
$thrHomeStats    = $isLocal ? 'throttle:120,1' : 'throttle:60,1';
$thrUiDiag       = $isLocal ? 'throttle:60,1'  : 'throttle:30,1';
$thrUiBotAsk     = $isLocal ? 'throttle:60,1'  : 'throttle:30,1';
$thrDevQa        = $isLocal ? 'throttle:120,1' : 'throttle:60,1';
$thrDevPosts     = $isLocal ? 'throttle:60,1'  : 'throttle:30,1';
$thrAdminPosts   = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';

/** Helper permisos → middleware 'can:perm,<clave>' */
if (!function_exists('perm_mw')) {
    function perm_mw(string|array $perm): array
    {
        if (app()->environment(['local','development','testing'])) return [];
        $perms = is_array($perm) ? $perm : [$perm];
        return array_map(fn($p) => 'can:perm,'.$p, $perms);
    }
}

/** Placeholder rápido (por si faltan vistas) */
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

/* ============ UI ============ */
Route::match(['GET','HEAD'], 'ui/heartbeat', [UiController::class, 'heartbeat'])
    ->middleware($thrUiHeartbeat)->name('ui.heartbeat');

$uiLog = Route::match(['POST','GET'], 'ui/log', [UiController::class, 'log'])
    ->middleware($thrUiLog)->name('ui.log');
if ($isLocal) $uiLog->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);

/* ============ Auth (guard admin) ============ */
Route::middleware('guest:admin')->group(function () use ($isLocal, $thrLogin) {
    Route::get('login', [LoginController::class, 'showLogin'])->name('login');

    $loginPost = Route::post('login', [LoginController::class, 'login'])
        ->middleware($thrLogin)->name('login.do');

    if ($isLocal) {
        $loginPost->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }
});

// Notificaciones: contador público
Route::match(['GET','HEAD'], 'notificaciones/count', [NotificationController::class, 'count'])
    ->middleware('throttle:60,1')->name('notificaciones.count');

/* ============ Área autenticada ADMIN ============ */
Route::middleware(['auth:admin','account.active'])->group(function () use ($thrHomeStats, $thrUiDiag, $thrUiBotAsk, $thrDevQa, $thrDevPosts, $thrAdminPosts, $isLocal) {

    // Aliases
    Route::get('/', fn() => redirect()->route('admin.home'))->name('root');
    Route::get('dashboard', fn() => redirect()->route('admin.home'))->name('dashboard');

    // WhoAmI
    Route::get('_whoami', function () {
        $u = auth('admin')->user();
        return response()->json([
            'ok'     => (bool) $u,
            'id'     => $u?->id,
            'name'   => $u?->name ?? $u?->nombre,
            'email'  => $u?->email,
            'guard'  => 'admin',
            'now'    => now()->toDateTimeString(),
            'canAny' => ['access-admin' => \Illuminate\Support\Facades\Gate::forUser($u)->allows('access-admin')],
            'super'  => method_exists($u, 'isSuperAdmin') ? (bool) $u->isSuperAdmin() : false,
        ]);
    })->name('whoami');

    /* Home & métricas */
    Route::get('home', [HomeController::class, 'index'])->name('home');
    Route::get('home/stats', [HomeController::class, 'stats'])->middleware($thrHomeStats)->name('home.stats');
    Route::get('home/income/{ym}', [HomeController::class, 'incomeByMonth'])
        ->where(['ym' => '\d{4}-(0[1-9]|1[0-2])'])->name('home.incomeMonth');

    if (method_exists(HomeController::class, 'compare')) {
        Route::get('home/compare/{ym}', [HomeController::class, 'compare'])
            ->where(['ym' => '\d{4}-(0[1-9]|1[0-2])'])->name('home.compare');
    }
    if (method_exists(HomeController::class, 'ytd')) {
        Route::get('home/ytd/{year}', [HomeController::class, 'ytd'])
            ->where(['year' => '\d{4}'])->name('home.ytd');
    }
    if (method_exists(HomeController::class, 'hitsHeatmap')) {
        Route::get('home/hits-heatmap/{weeks?}', [HomeController::class, 'hitsHeatmap'])
            ->where(['weeks'=>'\d+'])->name('home.hitsHeatmap');
    }
    if (method_exists(HomeController::class, 'modulesTop')) {
        Route::get('home/modules-top/{months?}', [HomeController::class, 'modulesTop'])
            ->where(['months'=>'\d+'])->name('home.modulesTop');
    }
    if (method_exists(HomeController::class, 'plansBreakdown')) {
        Route::get('home/plans-breakdown/{months?}', [HomeController::class, 'plansBreakdown'])
            ->where(['months'=>'\d+'])->name('home.plansBreakdown');
    }
    if (method_exists(HomeController::class, 'export')) {
        Route::get('home/export', [HomeController::class, 'export'])->name('home.export');
    }

    /* Utilidades */
    Route::get('search', [SearchController::class, 'index'])->name('search');

    Route::get('notificaciones',        [NotificationController::class, 'index'])->name('notificaciones');
    Route::get('notificaciones/list',   [NotificationController::class, 'list'])->name('notificaciones.list');
    Route::post('notificaciones/read-all', [NotificationController::class, 'readAll'])->name('notificaciones.readAll');

    Route::get('ui/diag', [UiController::class, 'diag'])->middleware($thrUiDiag)->name('ui.diag');
    Route::post('ui/bot-ask', [UiController::class, 'botAsk'])->middleware($thrUiBotAsk)->name('ui.botAsk');

    // Perfil
    Route::get('perfil', [ProfileController::class, 'index'])->name('perfil');
    Route::get('perfil/edit', [ProfileController::class, 'edit'])->name('perfil.edit');
    Route::put('perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::post('perfil/password', [ProfileController::class, 'password'])->name('perfil.password');

    // Config
    Route::get('config', [ConfigController::class, 'index'])
        ->middleware(perm_mw('admin.config'))->name('config.index');

    // Reportes
    Route::get('reportes', [ReportesController::class, 'index'])
        ->middleware(perm_mw('reportes.ver'))->name('reportes.index');

    /* Clientes (accounts) */
    if (class_exists(\App\Http\Controllers\Admin\ClientesController::class)) {

        Route::get('clientes', [ClientesController::class, 'index'])
            ->middleware(perm_mw('clientes.ver'))->name('clientes.index');

        Route::post('clientes/{rfc}/save', [ClientesController::class, 'save'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])->name('clientes.save');

        Route::post('clientes/{rfc}/resend-email', [ClientesController::class, 'resendEmailVerification'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])->name('clientes.resendEmail');

        Route::post('clientes/{rfc}/send-otp', [ClientesController::class, 'sendPhoneOtp'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])->name('clientes.sendOtp');

        Route::post('clientes/{rfc}/force-email', [ClientesController::class, 'forceEmailVerified'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])->name('clientes.forceEmail');

        Route::post('clientes/{rfc}/force-phone', [ClientesController::class, 'forcePhoneVerified'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])->name('clientes.forcePhone');

        $rp = Route::post('clientes/{rfc}/reset-password', [ClientesController::class, 'resetPassword'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])
            ->name('clientes.resetPassword');

        if ($isLocal) {
            // En local: desactiva CSRF para el POST…
            $rp->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);

            // …y habilita un GET para poder pegar la URL en el navegador y ver ?format=pretty / ?format=json
            Route::get('clientes/{rfc}/reset-password', [ClientesController::class, 'resetPassword'])
                ->middleware($thrAdminPosts)
                ->name('clientes.resetPassword.get');
        }

        Route::post('clientes/{rfc}/email-credentials', [ClientesController::class, 'emailCredentials'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])->name('clientes.emailCredentials');

        Route::post('clientes/{rfc}/impersonate', [ClientesController::class, 'impersonate'])
            ->middleware([$thrAdminPosts, ...perm_mw(['clientes.ver','clientes.impersonate'])])
            ->name('clientes.impersonate');

        Route::post('clientes/impersonate/stop', [ClientesController::class, 'impersonateStop'])
            ->middleware($thrAdminPosts)->name('clientes.impersonate.stop');

        Route::post('clientes/sync-to-clientes', [ClientesController::class, 'syncToClientes'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])->name('clientes.syncToClientes');

        Route::post('clientes/bulk', [ClientesController::class, 'bulk'])
            ->middleware([$thrAdminPosts, ...perm_mw('clientes.editar')])->name('clientes.bulk');

    } else {
        Route::get('clientes', fn () =>
            response('<h1>Clientes</h1><p>Pendiente de implementar.</p>', 200)
        )->middleware(perm_mw('clientes.ver'))->name('clientes.index');
    }


    Route::middleware(['auth:admin'])->prefix('soporte')->as('admin.soporte.')->group(function () {
        Route::get('reset-pass', [ResetClientePasswordController::class, 'showForm'])->name('reset_pass.show');
        Route::post('reset-pass', [ResetClientePasswordController::class, 'resetByRfc'])->name('reset_pass.do');
    });

    /* EMPRESAS · PACTOPIA360 */
    Route::prefix('empresas/pactopia360')->name('empresas.pactopia360.')->group(function () {
        if (view()->exists('admin.empresas.pactopia360.dashboard')) {
            Route::view('/', 'admin.empresas.pactopia360.dashboard')->name('dashboard');
        } else {
            Route::get('/', fn()=>admin_placeholder_view('Pactopia360 — Dashboard'));
        }

        Route::prefix('crm')->name('crm.')->group(function () {
            Route::prefix('carritos')->name('carritos.')->group(function () {
                Route::view('/', 'admin.empresas.pactopia360.crm.carritos.index')->name('index');
                Route::view('/create', 'admin.empresas.pactopia360.crm.carritos.create')->name('create');
                Route::view('/{id}', 'admin.empresas.pactopia360.crm.carritos.show')->whereNumber('id')->name('show');
                Route::view('/{id}/edit', 'admin.empresas.pactopia360.crm.carritos.edit')->whereNumber('id')->name('edit');
            });

            Route::prefix('contactos')->name('contactos.')->group(function () {
                Route::view('/', 'admin.empresas.pactopia360.crm.contactos.index')->name('index');
                Route::view('/create', 'admin.empresas.pactopia360.crm.contactos.create')->name('create');
                Route::view('/{id}/edit', 'admin.empresas.pactopia360.crm.contactos.edit')->whereNumber('id')->name('edit');
            });

            foreach ([['comunicaciones','Comunicaciones'],['correos','Correos'],['empresas','Empresas'],
                      ['contratos','Contratos'],['cotizaciones','Cotizaciones'],['facturas','Facturas'],
                      ['estados','Estados de cuenta'],['negocios','Negocios'],['notas','Notas'],
                      ['suscripciones','Suscripciones'],['robots','Robots']] as [$slug,$label]) {
                Route::get($slug, fn()=>admin_placeholder_view("CRM · {$label}", 'PACTOPIA 360'))->name("{$slug}.index");
            }
        });

        Route::prefix('cxp')->name('cxp.')->group(function () {
            foreach ([['gastos','Gastos'],['proveedores','Proveedores'],['viaticos','Viáticos'],['robots','Robots']] as [$slug,$label]) {
                Route::get($slug, fn()=>admin_placeholder_view("Cuentas por pagar · {$label}", 'PACTOPIA 360'))->name("{$slug}.index");
            }
        });

        Route::prefix('cxc')->name('cxc.')->group(function () {
            foreach ([['ventas','Ventas'],['facturacion','Facturación y cobranza'],['robots','Robots']] as [$slug,$label]) {
                Route::get($slug, fn()=>admin_placeholder_view("Cuentas por cobrar · {$label}", 'PACTOPIA 360'))->name("{$slug}.index");
            }
        });

        Route::prefix('conta')->name('conta.')->group(function () {
            Route::get('robots', fn()=>admin_placeholder_view('Contabilidad · Robots','PACTOPIA 360'))->name('robots.index');
        });

        Route::prefix('nomina')->name('nomina.')->group(function () {
            Route::get('robots', fn()=>admin_placeholder_view('Nómina · Robots','PACTOPIA 360'))->name('robots.index');
        });

        Route::prefix('facturacion')->name('facturacion.')->group(function () {
            foreach ([['timbres','Timbres / HITS'],['cancel','Cancelaciones'],['resguardo','Resguardo 6 meses'],['robots','Robots']] as [$slug,$label]) {
                Route::get($slug, fn()=>admin_placeholder_view("Facturación · {$label}", 'PACTOPIA 360'))->name("{$slug}.index");
            }
        });

        Route::prefix('docs')->name('docs.')->group(function () {
            Route::get('/', fn()=>admin_placeholder_view('Documentación · Gestor / Plantillas','PACTOPIA 360'))->name('index');
            Route::get('robots', fn()=>admin_placeholder_view('Documentación · Robots','PACTOPIA 360'))->name('robots.index');
        });

        Route::prefix('pv')->name('pv.')->group(function () {
            foreach ([['cajas','Cajas'],['tickets','Tickets'],['arqueos','Arqueos'],['robots','Robots']] as [$slug,$label]) {
                Route::get($slug, fn()=>admin_placeholder_view("Punto de venta · {$label}", 'PACTOPIA 360'))->name("{$slug}.index");
            }
        });

        Route::prefix('bancos')->name('bancos.')->group(function () {
            foreach ([['cuentas','Cuentas'],['concilia','Conciliación'],['robots','Robots']] as [$slug,$label]) {
                Route::get($slug, fn()=>admin_placeholder_view("Bancos · {$label}", 'PACTOPIA 360'))->name("{$slug}.index");
            }
        });
    });

    /* EMPRESAS · PACTOPIA */
    Route::prefix('empresas/pactopia')->name('empresas.pactopia.')->group(function () {
        if (view()->exists('admin.empresas.pactopia.dashboard')) {
            Route::view('/', 'admin.empresas.pactopia.dashboard')->name('dashboard');
        } else {
            Route::get('/', fn()=>admin_placeholder_view('Pactopia — Dashboard', 'Pactopia'));
        }
        Route::prefix('crm')->name('crm.')->group(function () {
            foreach ([['contactos','Contactos'],['robots','Robots']] as [$slug,$label]) {
                Route::get($slug, fn()=>admin_placeholder_view("CRM · {$label}", 'Pactopia'))->name("{$slug}.index");
            }
        });
    });

    /* EMPRESAS · WARETEK MX */
    Route::prefix('empresas/waretek-mx')->name('empresas.waretek-mx.')->group(function () {
        if (view()->exists('admin.empresas.waretek-mx.dashboard')) {
            Route::view('/', 'admin.empresas.waretek-mx.dashboard')->name('dashboard');
        } else {
            Route::get('/', fn()=>admin_placeholder_view('Waretek México — Dashboard', 'Waretek México'));
        }
        Route::prefix('crm')->name('crm.')->group(function () {
            foreach ([['contactos','Contactos'],['robots','Robots']] as [$slug,$label]) {
                Route::get($slug, fn()=>admin_placeholder_view("CRM · {$label}", 'Waretek México'))->name("{$slug}.index");
            }
        });
    });

    /* Administración */
    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', fn()=> view()->exists('admin.usuarios.index')
            ? view('admin.usuarios.index')
            : admin_placeholder_view('Usuarios · Administrativos'))->name('index');
        Route::get('robots', fn()=>admin_placeholder_view('Usuarios · Robots'))->name('robots.index');
    });

    Route::prefix('soporte')->name('soporte.')->group(function () {
        foreach ([['tickets','Tickets'],['sla','SLA / Asignación'],['comms','Comunicaciones'],['robots','Robots']] as [$slug,$label]) {
            Route::get($slug, fn()=>admin_placeholder_view("Soporte · {$label}"))->name("{$slug}.index");
        }
    });

    /* Auditoría */
    Route::prefix('auditoria')->name('auditoria.')->group(function () {
        foreach ([['accesos','Logs de acceso'],['cambios','Bitácora cambios'],['integridad','Integridad'],['robots','Robots']] as [$slug,$label]) {
            Route::get($slug, fn()=>admin_placeholder_view("Auditoría · {$label}"))->name("{$slug}.index");
        }
    });

    /* Config (submódulos) */
    Route::prefix('config')->name('config.')->group(function () {
        foreach ([['mantenimiento','Mantenimiento'],['limpieza','Optimización/Limpieza demo'],
                  ['backups','Backups / Restore'],['robots','Robots']] as [$slug,$label]) {
            Route::get($slug, fn()=>admin_placeholder_view("Plataforma · {$label}"))->name($slug);
        }

        Route::prefix('int')->name('int.')->group(function () {
            foreach ([['pacs','PAC(s)'],['mail','Mailgun/MailerLite'],['api','API Keys / Webhooks'],
                      ['pay','Stripe / Conekta'],['robots','Robots']] as [$slug,$label]) {
                Route::get($slug, fn()=>admin_placeholder_view("Integraciones · {$label}"))->name($slug);
            }
        });

        Route::prefix('param')->name('param.')->group(function () {
            foreach ([['precios','Planes & Precios'],['cupones','Descuentos / Cupones'],
                      ['limites','Límites por plan'],['robots','Robots']] as [$slug,$label]) {
                Route::get($slug, fn()=>admin_placeholder_view("Parámetros · {$label}"))->name($slug);
            }
        });
    });

    /* Reportes (subrutas) */
    Route::prefix('reportes')->name('reportes.')->group(function () {
        foreach (['crm','cxp','cxc','conta','nomina','facturacion','descargas','robots'] as $slug) {
            Route::get($slug, fn()=>admin_placeholder_view('Reportes · '.Str::headline($slug)))->name($slug);
        }
    });

    // Logout
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    /* DEV · QA */
    Route::prefix('dev')->name('dev.')->group(function () use ($thrDevQa, $thrDevPosts, $isLocal) {
        Route::get('qa', [QaController::class,'index'])->middleware($thrDevQa)->name('qa');

        $r1 = Route::post('resend-email', [QaController::class,'resendEmail'])->middleware($thrDevPosts)->name('resend_email');
        $r2 = Route::post('send-otp',     [QaController::class,'sendOtp'])->middleware($thrDevPosts)->name('send_otp');
        $r3 = Route::post('force-email',  [QaController::class,'forceEmailVerified'])->middleware($thrDevPosts)->name('force_email');
        $r4 = Route::post('force-phone',  [QaController::class,'forcePhoneVerified'])->middleware($thrDevPosts)->name('force_phone');

        if ($isLocal) {
            foreach ([$r1,$r2,$r3,$r4] as $route) {
                $route->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
            }
        }
    });

    // Fallback interno
    Route::fallback(fn () => redirect()->route('admin.home'));
});
