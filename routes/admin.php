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

// -------------------------------------------------------------
// Helper permiso -> middleware 'can:perm,<clave>'
// - En local/dev/testing no aplica (para no estorbar).
// - Acepta string o array de strings.
// -------------------------------------------------------------
if (!function_exists('perm_mw')) {
    function perm_mw(string|array $perm): array
    {
        if (app()->environment(['local','development','testing'])) return [];
        $perms = is_array($perm) ? $perm : [$perm];
        return array_map(fn($p) => 'can:perm,'.$p, $perms);
    }
}

// -------------------------------------------------------------
// UI endpoints (heartbeat / log)
// -------------------------------------------------------------
Route::match(['GET','HEAD'], 'ui/heartbeat', [UiController::class, 'heartbeat'])
    ->middleware('throttle:60,1')->name('ui.heartbeat');

Route::match(['POST','GET'], 'ui/log', [UiController::class, 'log'])
    ->withoutMiddleware([
        \App\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ])
    ->middleware('throttle:240,1')->name('ui.log');

// -------------------------------------------------------------
// Auth (guard admin)
// -------------------------------------------------------------
Route::middleware('guest:admin')->group(function () {
    Route::get('login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')->name('login.do');
});

Route::middleware('auth:admin')->group(function () {

    // Alias clásicos
    Route::get('/', fn() => redirect()->route('admin.home'))->name('root');
    Route::get('dashboard', fn() => redirect()->route('admin.home'))->name('dashboard');

    // ---------------------------------------------------------
    // Home & métricas
    // ---------------------------------------------------------
    Route::get('home', [HomeController::class, 'index'])->name('home');
    Route::get('home/stats', [HomeController::class, 'stats'])
        ->middleware('throttle:60,1')->name('home.stats');
    Route::get('home/income/{ym}', [HomeController::class, 'incomeByMonth'])
        ->where(['ym' => '\d{4}-(0[1-9]|1[0-2])'])->name('home.incomeMonth');

    // Endpoints extra del Home (sólo si existen los métodos)
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
        Route::get('home/export', [HomeController::class, 'export'])
            ->name('home.export');
    }

    // ---------------------------------------------------------
    // Utilidades de panel
    // ---------------------------------------------------------
    Route::get('search', [SearchController::class, 'index'])->name('search');

    Route::get('notificaciones',        [NotificationController::class, 'index'])->name('notificaciones');
    Route::get('notificaciones/count',  [NotificationController::class, 'count'])->name('notificaciones.count');
    Route::get('notificaciones/list',   [NotificationController::class, 'list'])->name('notificaciones.list');
    Route::post('notificaciones/read-all', [NotificationController::class, 'readAll'])->name('notificaciones.readAll');

    Route::get('ui/diag', [UiController::class, 'diag'])->middleware('throttle:30,1')->name('ui.diag');
    Route::post('ui/bot-ask', [UiController::class, 'botAsk'])->middleware('throttle:30,1')->name('ui.botAsk');

    // Mi perfil
    Route::get('perfil', [ProfileController::class, 'index'])->name('perfil');
    Route::get('perfil/edit', [ProfileController::class, 'edit'])->name('perfil.edit');
    Route::put('perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::post('perfil/password', [ProfileController::class, 'password'])->name('perfil.password');

    // Configuración (si aplica)
    Route::get('config', [ConfigController::class, 'index'])->name('config.index');

    // Configuración (si aplica)
    Route::get('config', [ConfigController::class, 'index'])->name('config.index');

    Route::get('reportes', [ReportesController::class, 'index'])
        ->middleware(perm_mw('reportes.ver'))->name('reportes.index');

    // ---------------------------------------------------------
    // Usuarios Admin  (¡rutas planas ANTES del resource!)
    // ---------------------------------------------------------
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

    // ---------------------------------------------------------
    // Perfiles & Permisos  (extras + resource)
    // ---------------------------------------------------------
    if (class_exists(\App\Http\Controllers\Admin\PerfilesController::class)) {

        // Extras usados por el front (deben ir antes del resource)
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

    // ---------------------------------------------------------
    // Clientes
    // ---------------------------------------------------------
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

    // ---------------------------------------------------------
    // Planes
    // ---------------------------------------------------------
    if (class_exists(\App\Http\Controllers\Admin\PlanesController::class)) {
        Route::resource('planes', \App\Http\Controllers\Admin\PlanesController::class)
            ->parameters(['planes' => 'plan'])
            ->middleware(perm_mw('planes.ver'));
    } else {
        Route::get('planes', fn () =>
            response('<h1>Planes</h1><p>Pendiente de implementar.</p>', 200)
        )->middleware(perm_mw('planes.ver'))->name('planes.index');
    }

    // ---------------------------------------------------------
    // Pagos
    // ---------------------------------------------------------
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

    // ---------------------------------------------------------
    // Facturación
    // ---------------------------------------------------------
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

    // ---------------------------------------------------------
    // Auditoría (solo lectura)
    // ---------------------------------------------------------
    if (class_exists(\App\Http\Controllers\Admin\AuditoriaController::class)) {
        Route::resource('auditoria', \App\Http\Controllers\Admin\AuditoriaController::class)
            ->only(['index','show'])
            ->parameters(['auditoria' => 'evento'])
            ->middleware(perm_mw('auditoria.ver'));
    } else {
        Route::get('auditoria', fn () =>
            response('<h1>Auditoría</h1><p>Pendiente de implementar.</p>', 200)
        )->middleware(perm_mw('auditoria.ver'))->name('auditoria.index');
    }

    // ---------------------------------------------------------
    // Miscelánea
    // ---------------------------------------------------------
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('permcheck', function () {
        $u = auth('admin')->user();
        return response()->json([
            'env'  => app()->environment(),
            'user' => $u ? $u->only(['id','email','rol','es_superadmin']) : null,
            'can'  => [
                'usuarios_admin.ver' => Gate::forUser($u)->allows('perm','usuarios_admin.ver'),
                'clientes.ver'       => Gate::forUser($u)->allows('perm','clientes.ver'),
                'planes.ver'         => Gate::forUser($u)->allows('perm','planes.ver'),
                'pagos.ver'          => Gate::forUser($u)->allows('perm','pagos.ver'),
                'facturacion.ver'    => Gate::forUser($u)->allows('perm','facturacion.ver'),
                'auditoria.ver'      => Gate::forUser($u)->allows('perm','auditoria.ver'),
                'reportes.ver'       => Gate::forUser($u)->allows('perm','reportes.ver'),
            ],
            'path' => request()->path(),
        ]);
    })->name('permcheck');

    Route::fallback(fn () => redirect()->route('admin.home'));
});
