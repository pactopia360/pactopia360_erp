<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\UiController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ConfigController;
use App\Http\Controllers\Admin\ReportesController;
use App\Http\Controllers\Admin\Empresas\DashboardController;

use App\Http\Controllers\Admin\Empresas\Pactopia360\CRM\CarritosController;
use App\Http\Controllers\Admin\Empresas\Pactopia360\CRM\ContactosController;

/**
 * Helper permiso -> middleware 'can:perm,<clave>'
 * - En local/dev/testing no aplica (para no estorbar).
 * - Acepta string o array de strings.
 */
if (!function_exists('perm_mw')) {
    function perm_mw(string|array $perm): array
    {
        if (app()->environment(['local','development','testing'])) return [];
        $perms = is_array($perm) ? $perm : [$perm];
        return array_map(fn($p) => 'can:perm,'.$p, $perms);
    }
}

/**
 * Placeholder reusable:
 * - Si existe la vista admin.generic.placeholder la usa,
 * - si no, regresa un HTML simple (para no romper).
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

/* ===========================
   UI: heartbeat / log
   =========================== */
Route::match(['GET','HEAD'], 'ui/heartbeat', [UiController::class, 'heartbeat'])
    ->middleware('throttle:60,1')->name('ui.heartbeat');

Route::match(['POST','GET'], 'ui/log', [UiController::class, 'log'])
    ->withoutMiddleware([
        \App\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ])
    ->middleware('throttle:240,1')->name('ui.log');

/* ===========================
   Auth (guard admin)
   =========================== */
Route::middleware('guest:admin')->group(function () {
    Route::get('login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')->name('login.do');
});

Route::middleware('auth:admin')->group(function () {
    // Aliases
    Route::get('/', fn() => redirect()->route('admin.home'))->name('root');
    Route::get('dashboard', fn() => redirect()->route('admin.home'))->name('dashboard');

    // ===== WhoAmI (diagnóstico rápido) =====
    Route::get('_whoami', function () {
        $u = auth('admin')->user();
        return response()->json([
            'ok'    => (bool) $u,
            'id'    => $u?->id,
            'name'  => $u?->name ?? $u?->nombre,
            'email' => $u?->email,
            'guard' => 'admin',
            'now'   => now()->toDateTimeString(),
            'canAny'=> [
                'access-admin' => Gate::forUser($u)->allows('access-admin'),
            ],
        ]);
    })->name('whoami');

    /* ===========================
       Home & métricas
       =========================== */
    Route::get('home', [HomeController::class, 'index'])->name('home');
    Route::get('home/stats', [HomeController::class, 'stats'])
        ->middleware('throttle:60,1')->name('home.stats');
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

    /* ===========================
       Utilidades
       =========================== */
    Route::get('search', [SearchController::class, 'index'])->name('search');

    Route::get('notificaciones',        [NotificationController::class, 'index'])->name('notificaciones');
    Route::get('notificaciones/count',  [NotificationController::class, 'count'])->name('notificaciones.count');
    Route::get('notificaciones/list',   [NotificationController::class, 'list'])->name('notificaciones.list');
    Route::post('notificaciones/read-all', [NotificationController::class, 'readAll'])->name('notificaciones.readAll');

    Route::get('ui/diag', [UiController::class, 'diag'])->middleware('throttle:30,1')->name('ui.diag');
    Route::post('ui/bot-ask', [UiController::class, 'botAsk'])->middleware('throttle:30,1')->name('ui.botAsk');

    // Perfil
    Route::get('perfil', [ProfileController::class, 'index'])->name('perfil');
    Route::get('perfil/edit', [ProfileController::class, 'edit'])->name('perfil.edit');
    Route::put('perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::post('perfil/password', [ProfileController::class, 'password'])->name('perfil.password');

    // Configuración
    Route::get('config', [ConfigController::class, 'index'])->name('config.index');

    Route::get('reportes', [ReportesController::class, 'index'])
        ->middleware(perm_mw('reportes.ver'))->name('reportes.index');

    /* ===========================
       Usuarios Admin
       =========================== */
    if (class_exists(\App\Http\Controllers\Admin\UsuariosController::class)) {
        Route::get('usuarios/export', [\App\Http\Controllers\Admin\UsuariosController::class, 'export'])
            ->middleware(perm_mw('usuarios_admin.ver'))->name('usuarios.export');

        Route::post('usuarios/bulk', [\App\Http\Controllers\Admin\UsuariosController::class, 'bulk'])
            ->middleware(perm_mw('usuarios_admin.editar'))->name('usuarios.bulk');

        Route::post('usuarios/{usuario}/impersonate', [\App\Http\Controllers\Admin\UsuariosController::class, 'impersonate'])
            ->middleware(perm_mw('usuarios_admin.impersonar'))->name('usuarios.impersonate');

        Route::post('usuarios/impersonate/stop', [\App\Http\Controllers\Admin\UsuariosController::class, 'impersonateStop'])
            ->name('usuarios.impersonateStop');

        Route::resource('usuarios', \App\Http\Controllers\Admin\UsuariosController::class)
            ->parameters(['usuarios' => 'usuario'])
            ->middleware(perm_mw('usuarios_admin.ver'));
    } else {
        Route::get('usuarios', fn () =>
            response('<h1>Usuarios Admin</h1><p>Pendiente de implementar.</p>', 200)
        )->middleware(perm_mw('usuarios_admin.ver'))->name('usuarios.index');
    }

    /* ===========================
       Perfiles & Permisos
       =========================== */
    if (class_exists(\App\Http\Controllers\Admin\PerfilesController::class)) {
        Route::get('perfiles/export', [\App\Http\Controllers\Admin\PerfilesController::class, 'export'])
            ->middleware(perm_mw('perfiles.ver'))->name('perfiles.export');

        Route::post('perfiles/bulk', [\App\Http\Controllers\Admin\PerfilesController::class, 'bulk'])
            ->middleware(perm_mw('perfiles.editar'))->name('perfiles.bulk');

        Route::post('perfiles/toggle', [\App\Http\Controllers\Admin\PerfilesController::class, 'toggle'])
            ->middleware(perm_mw('perfiles.editar'))->name('perfiles.toggle');

        Route::get('perfiles/permissions', [\App\Http\Controllers\Admin\PerfilesController::class, 'permissions'])
            ->middleware(perm_mw('perfiles.ver'))->name('perfiles.permissions');

        Route::post('perfiles/permissions', [\App\Http\Controllers\Admin\PerfilesController::class, 'permissionsSave'])
            ->middleware(perm_mw('perfiles.editar'))->name('perfiles.permissions.save');

        Route::resource('perfiles', \App\Http\Controllers\Admin\PerfilesController::class)
            ->parameters(['perfiles' => 'perfil'])
            ->middleware(perm_mw('perfiles.ver'));
    } else {
        Route::get('perfiles', fn () =>
            response('<h1>Perfiles & Permisos</h1><p>Pendiente de implementar.</p>', 200)
        )->middleware(perm_mw('perfiles.ver'))->name('perfiles.index');
    }

    /* ===========================
       Clientes
       =========================== */
    if (class_exists(\App\Http\Controllers\Admin\ClientesController::class)) {
        Route::get('clientes/export', [\App\Http\Controllers\Admin\ClientesController::class, 'export'])
            ->middleware(perm_mw('clientes.ver'))->name('clientes.export');

        Route::resource('clientes', \App\Http\Controllers\Admin\ClientesController::class)
            ->parameters(['clientes' => 'cliente'])
            ->middleware(perm_mw('clientes.ver'));
    } else {
        Route::get('clientes', fn () =>
            response('<h1>Clientes</h1><p>Pendiente de implementar.</p>', 200)
        )->middleware(perm_mw('clientes.ver'))->name('clientes.index');
    }

    /* ===========================
       Planes
       =========================== */
    if (class_exists(\App\Http\Controllers\Admin\PlanesController::class)) {
        Route::resource('planes', \App\Http\Controllers\Admin\PlanesController::class)
            ->parameters(['planes' => 'plan'])
            ->middleware(perm_mw('planes.ver'));
    } else {
        Route::get('planes', fn () =>
            response('<h1>Planes</h1><p>Pendiente de implementar.</p>', 200)
        )->middleware(perm_mw('planes.ver'))->name('planes.index');
    }

    /* ===========================
       Pagos
       =========================== */
    if (class_exists(\App\Http\Controllers\Admin\PagosController::class)) {
        Route::get('pagos/export', [\App\Http\Controllers\Admin\PagosController::class, 'export'])
            ->middleware(perm_mw('pagos.ver'))->name('pagos.export');

        Route::resource('pagos', \App\Http\Controllers\Admin\PagosController::class)
            ->parameters(['pagos' => 'pago'])
            ->middleware(perm_mw('pagos.ver'));
    } else {
        Route::get('pagos', fn () =>
            response('<h1>Pagos</h1><p>Pendiente de implementar.</p>', 200)
        )->middleware(perm_mw('pagos.ver'))->name('pagos.index');
    }

    /* ===========================
       Facturación
       =========================== */
    if (class_exists(\App\Http\Controllers\Admin\FacturacionController::class)) {
        Route::get('facturacion/export', [\App\Http\Controllers\Admin\FacturacionController::class, 'export'])
            ->middleware(perm_mw('facturacion.ver'))->name('facturacion.export');

        Route::resource('facturacion', \App\Http\Controllers\Admin\FacturacionController::class)
            ->parameters(['facturacion' => 'factura'])
            ->middleware(perm_mw('facturacion.ver'));
    } else {
        Route::get('facturacion', fn () =>
            response('<h1>Facturación</h1><p>Pendiente de implementar.</p>', 200)
        )->middleware(perm_mw('facturacion.ver'))->name('facturacion.index');
    }

    /* =======================================================
       EMPRESAS · Dashboards
       ======================================================= */
    Route::get('empresas/pactopia360', [DashboardController::class, 'pactopia360'])
        ->name('empresas.pactopia360.dashboard');

    Route::get('empresas/pactopia', [DashboardController::class, 'pactopia'])
        ->name('empresas.pactopia.dashboard');

    Route::get('empresas/waretek-mx', [DashboardController::class, 'waretekMx'])
        ->name('empresas.waretek-mx.dashboard');

    /* =======================================================
       EMPRESAS · Submódulos
       ======================================================= */

    // -------- Pactopia360 --------
    Route::prefix('empresas/pactopia360')->name('empresas.pactopia360.')->group(function () {

        // === CRM · Carritos (CRUD real) ===
        Route::resource('crm/carritos', CarritosController::class)
            ->parameters(['carritos' => 'id'])
            // ->middleware(perm_mw('crm.ver p360'))
            ->names([
                'index'   => 'crm.carritos.index',
                'create'  => 'crm.carritos.create',
                'store'   => 'crm.carritos.store',
                'show'    => 'crm.carritos.show',
                'edit'    => 'crm.carritos.edit',
                'update'  => 'crm.carritos.update',
                'destroy' => 'crm.carritos.destroy',
            ]);

        // === CRM · Contactos (CRUD real) ===
        Route::resource('crm/contactos', ContactosController::class)
            ->parameters(['contactos' => 'contacto'])
            // ->middleware(perm_mw('crm.ver p360'))
            ->names([
                'index'   => 'crm.contactos.index',
                'create'  => 'crm.contactos.create',
                'store'   => 'crm.contactos.store',
                'edit'    => 'crm.contactos.edit',
                'update'  => 'crm.contactos.update',
                'destroy' => 'crm.contactos.destroy',
            ])
            ->only(['index','create','store','edit','update','destroy']); // (no show por ahora)

        // === CRM · resto (placeholders clicables) ===
        Route::get('crm/comunicaciones',  fn() => admin_placeholder_view('CRM · Comunicaciones', 'Pactopia360'))->name('crm.comunicaciones.index');
        Route::get('crm/correos',         fn() => admin_placeholder_view('CRM · Correos', 'Pactopia360'))->name('crm.correos.index');
        Route::get('crm/empresas',        fn() => admin_placeholder_view('CRM · Empresas', 'Pactopia360'))->name('crm.empresas.index');
        Route::get('crm/contratos',       fn() => admin_placeholder_view('CRM · Contratos', 'Pactopia360'))->name('crm.contratos.index');
        Route::get('crm/cotizaciones',    fn() => admin_placeholder_view('CRM · Cotizaciones', 'Pactopia360'))->name('crm.cotizaciones.index');
        Route::get('crm/facturas',        fn() => admin_placeholder_view('CRM · Facturas', 'Pactopia360'))->name('crm.facturas.index');
        Route::get('crm/estados',         fn() => admin_placeholder_view('CRM · Estados de cuenta', 'Pactopia360'))->name('crm.estados.index');
        Route::get('crm/negocios',        fn() => admin_placeholder_view('CRM · Negocios', 'Pactopia360'))->name('crm.negocios.index');
        Route::get('crm/notas',           fn() => admin_placeholder_view('CRM · Notas', 'Pactopia360'))->name('crm.notas.index');
        Route::get('crm/suscripciones',   fn() => admin_placeholder_view('CRM · Suscripciones', 'Pactopia360'))->name('crm.suscripciones.index');
        Route::get('crm/robots',          fn() => admin_placeholder_view('CRM · Robots', 'Pactopia360'))->name('crm.robots.index');

        // === Cuentas por pagar ===
        Route::get('cxp/gastos',          fn() => admin_placeholder_view('CxP · Gastos', 'Pactopia360'))->name('cxp.gastos.index');
        Route::get('cxp/proveedores',     fn() => admin_placeholder_view('CxP · Proveedores', 'Pactopia360'))->name('cxp.proveedores.index');
        Route::get('cxp/viaticos',        fn() => admin_placeholder_view('CxP · Viáticos', 'Pactopia360'))->name('cxp.viaticos.index');
        Route::get('cxp/robots',          fn() => admin_placeholder_view('CxP · Robots', 'Pactopia360'))->name('cxp.robots.index');

        // === Cuentas por cobrar ===
        Route::get('cxc/ventas',          fn() => admin_placeholder_view('CxC · Ventas', 'Pactopia360'))->name('cxc.ventas.index');
        Route::get('cxc/facturacion',     fn() => admin_placeholder_view('CxC · Facturación y cobranza', 'Pactopia360'))->name('cxc.facturacion.index');
        Route::get('cxc/robots',          fn() => admin_placeholder_view('CxC · Robots', 'Pactopia360'))->name('cxc.robots.index');

        // === Contabilidad ===
        Route::get('conta/robots',        fn() => admin_placeholder_view('Contabilidad · Robots', 'Pactopia360'))->name('conta.robots.index');

        // === Nómina ===
        Route::get('nomina/robots',       fn() => admin_placeholder_view('Nómina · Robots', 'Pactopia360'))->name('nomina.robots.index');

        // === Facturación ===
        Route::get('facturacion/timbres',   fn() => admin_placeholder_view('Facturación · Timbres / HITS', 'Pactopia360'))->name('facturacion.timbres.index');
        Route::get('facturacion/cancel',    fn() => admin_placeholder_view('Facturación · Cancelaciones', 'Pactopia360'))->name('facturacion.cancel.index');
        Route::get('facturacion/resguardo', fn() => admin_placeholder_view('Facturación · Resguardo 6 meses', 'Pactopia360'))->name('facturacion.resguardo.index');
        Route::get('facturacion/robots',    fn() => admin_placeholder_view('Facturación · Robots', 'Pactopia360'))->name('facturacion.robots.index');

        // === Documentación ===
        Route::get('docs',                fn() => admin_placeholder_view('Documentación · Gestor/Plantillas', 'Pactopia360'))->name('docs.index');
        Route::get('docs/robots',         fn() => admin_placeholder_view('Documentación · Robots', 'Pactopia360'))->name('docs.robots.index');

        // === Punto de venta ===
        Route::get('pv/cajas',            fn() => admin_placeholder_view('Punto de venta · Cajas', 'Pactopia360'))->name('pv.cajas.index');
        Route::get('pv/tickets',          fn() => admin_placeholder_view('Punto de venta · Tickets', 'Pactopia360'))->name('pv.tickets.index');
        Route::get('pv/arqueos',          fn() => admin_placeholder_view('Punto de venta · Arqueos', 'Pactopia360'))->name('pv.arqueos.index');
        Route::get('pv/robots',           fn() => admin_placeholder_view('Punto de venta · Robots', 'Pactopia360'))->name('pv.robots.index');

        // === Bancos ===
        Route::get('bancos/cuentas',      fn() => admin_placeholder_view('Bancos · Cuentas', 'Pactopia360'))->name('bancos.cuentas.index');
        Route::get('bancos/concilia',     fn() => admin_placeholder_view('Bancos · Conciliación', 'Pactopia360'))->name('bancos.concilia.index');
        Route::get('bancos/robots',       fn() => admin_placeholder_view('Bancos · Robots', 'Pactopia360'))->name('bancos.robots.index');
    });

    // -------- Pactopia --------
    Route::prefix('empresas/pactopia')->name('empresas.pactopia.')->group(function () {
        Route::get('crm/contactos',  fn() => admin_placeholder_view('CRM · Contactos', 'Pactopia'))->name('crm.contactos.index');
        Route::get('crm/robots',     fn() => admin_placeholder_view('CRM · Robots', 'Pactopia'))->name('crm.robots.index');
    });

    // -------- Waretek México --------
    Route::prefix('empresas/waretek-mx')->name('empresas.waretek-mx.')->group(function () {
        Route::get('crm/contactos',  fn() => admin_placeholder_view('CRM · Contactos', 'Waretek México'))->name('crm.contactos.index');
        Route::get('crm/robots',     fn() => admin_placeholder_view('CRM · Robots', 'Waretek México'))->name('crm.robots.index');
    });

    /* =======================================================
       ADMINISTRACIÓN (Usuarios / Soporte)
      ======================================================= */
    Route::get('usuarios/robots', fn() => admin_placeholder_view('Usuarios · Robots'))->name('usuarios.robots.index');

    Route::prefix('soporte')->name('soporte.')->group(function () {
        Route::get('tickets', fn() => admin_placeholder_view('Soporte · Tickets'))->name('tickets.index');
        Route::get('sla',     fn() => admin_placeholder_view('Soporte · SLA / Asignación'))->name('sla.index');
        Route::get('comms',   fn() => admin_placeholder_view('Soporte · Comunicaciones'))->name('comms.index');
        Route::get('robots',  fn() => admin_placeholder_view('Soporte · Robots'))->name('robots.index');
    });

    /* =======================================================
       AUDITORÍA (submódulos)
      ======================================================= */
    Route::prefix('auditoria')->name('auditoria.')->group(function () {
        Route::get('accesos',    fn() => admin_placeholder_view('Auditoría · Logs de acceso'))->name('accesos.index');
        Route::get('cambios',    fn() => admin_placeholder_view('Auditoría · Bitácora de cambios'))->name('cambios.index');
        Route::get('integridad', fn() => admin_placeholder_view('Auditoría · Integridad'))->name('integridad.index');
        Route::get('robots',     fn() => admin_placeholder_view('Auditoría · Robots'))->name('robots.index');
    });

    /* =======================================================
       CONFIGURACIÓN (Plataforma / Integraciones / Parámetros)
      ======================================================= */
    Route::prefix('config')->name('config.')->group(function () {
        // Plataforma
        Route::get('mantenimiento', fn() => admin_placeholder_view('Config · Mantenimiento'))->name('mantenimiento');
        Route::get('limpieza',      fn() => admin_placeholder_view('Config · Optimización/Limpieza demo'))->name('limpieza');
        Route::get('backups',       fn() => admin_placeholder_view('Config · Backups / Restore'))->name('backups');
        Route::get('robots',        fn() => admin_placeholder_view('Config · Robots'))->name('robots');

        // Integraciones
        Route::prefix('int')->name('int.')->group(function () {
            Route::get('pacs',   fn() => admin_placeholder_view('Integraciones · PAC(s)'))->name('pacs');
            Route::get('mail',   fn() => admin_placeholder_view('Integraciones · Mailgun/MailerLite'))->name('mail');
            Route::get('api',    fn() => admin_placeholder_view('Integraciones · API Keys / Webhooks'))->name('api');
            Route::get('pay',    fn() => admin_placeholder_view('Integraciones · Stripe/Conekta'))->name('pay');
            Route::get('robots', fn() => admin_placeholder_view('Integraciones · Robots'))->name('robots');
        });

        // Parámetros
        Route::prefix('param')->name('param.')->group(function () {
            Route::get('precios', fn() => admin_placeholder_view('Parámetros · Planes & Precios'))->name('precios');
            Route::get('cupones', fn() => admin_placeholder_view('Parámetros · Descuentos / Cupones'))->name('cupones');
            Route::get('limites', fn() => admin_placeholder_view('Parámetros · Límites por plan'))->name('limites');
            Route::get('robots',  fn() => admin_placeholder_view('Parámetros · Robots'))->name('robots');
        });
    });

    /* =======================================================
       PERFIL (extras)
      ======================================================= */
    Route::prefix('perfil')->name('perfil.')->group(function () {
        Route::get('preferencias', fn() => admin_placeholder_view('Mi cuenta · Preferencias'))->name('preferencias');
        Route::get('robots',       fn() => admin_placeholder_view('Mi cuenta · Robots'))->name('robots');
    });

    /* =======================================================
       REPORTES (por módulo)
      ======================================================= */
    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::get('crm',         fn() => admin_placeholder_view('Reportes · CRM'))->name('crm');
        Route::get('cxp',         fn() => admin_placeholder_view('Reportes · Cuentas por pagar'))->name('cxp');
        Route::get('cxc',         fn() => admin_placeholder_view('Reportes · Cuentas por cobrar'))->name('cxc');
        Route::get('conta',       fn() => admin_placeholder_view('Reportes · Contabilidad'))->name('conta');
        Route::get('nomina',      fn() => admin_placeholder_view('Reportes · Nómina'))->name('nomina');
        Route::get('facturacion', fn() => admin_placeholder_view('Reportes · Facturación'))->name('facturacion');
        Route::get('descargas',   fn() => admin_placeholder_view('Reportes · Descargas'))->name('descargas');
        Route::get('robots',      fn() => admin_placeholder_view('Reportes · Robots'))->name('robots');
    });

    /* ===========================
       Miscelánea
       =========================== */
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('permcheck', function () {
        $u = auth('admin')->user();
        return response()->json([
            'env'  => app()->environment(),
            'user' => $u ? $u->only(['id','email','rol','es_superadmin']) : null,
            'can'  => [
                'usuarios_admin.ver'        => Gate::forUser($u)->allows('perm','usuarios_admin.ver'),
                'usuarios_admin.impersonar' => Gate::forUser($u)->allows('perm','usuarios_admin.impersonar'),
                'clientes.ver'              => Gate::forUser($u)->allows('perm','clientes.ver'),
                'planes.ver'                => Gate::forUser($u)->allows('perm','planes.ver'),
                'pagos.ver'                 => Gate::forUser($u)->allows('perm','pagos.ver'),
                'facturacion.ver'           => Gate::forUser($u)->allows('perm','facturacion.ver'),
                'auditoria.ver'             => Gate::forUser($u)->allows('perm','auditoria.ver'),
                'reportes.ver'              => Gate::forUser($u)->allows('perm','reportes.ver'),
            ],
            'path' => request()->path(),
        ]);
    })->name('permcheck');

    Route::fallback(fn () => redirect()->route('admin.home'));
});
