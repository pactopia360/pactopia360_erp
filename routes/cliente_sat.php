<?php
// C:\wamp64\www\pactopia360_erp\routes\cliente_sat.php
// PACTOPIA360 · SAT Cliente routes (SOT SAT)
// ✅ Este archivo se monta desde routes/web.php con:
//    Route::prefix('cliente')->as('cliente.')->middleware('cliente')->group(base_path('routes/cliente_sat.php'));
// Por lo tanto, aquí DEFINIMOS rutas relativas a /cliente/... y nombres relativos a cliente.*
//
// Objetivos:
// ✅ Evitar duplicados (route:cache)
// ✅ Robustez en deploy: solo registrar rutas si existe el método
// ✅ CSRF solo relajado en local (cuando aplique)
// ✅ Throttle consistente por operación
// ✅ Paths y names estables (para JS y Blade)
//
// ✅ Opción A aplicada:
// - Movido el COTIZADOR (quote.*) desde routes/cliente.php a este archivo (SOT SAT único).

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;

use App\Http\Controllers\Cliente\Sat\SatDescargaController;
use App\Http\Controllers\Cliente\Sat\SatReporteController;
use App\Http\Controllers\Cliente\Sat\ExcelViewerController;
use App\Http\Controllers\Cliente\Sat\DiotController;
use App\Http\Controllers\Cliente\Sat\VaultController;
use App\Http\Controllers\Cliente\Sat\SatCartController;
use App\Http\Controllers\Cliente\Sat\SatZipController;
use App\Http\Controllers\Cliente\Sat\SatExternalPublicController;

/*
|--------------------------------------------------------------------------
| Rutas SAT Cliente (Descargas masivas CFDI, Bóveda, Reportes, Carrito, Cotizador)
|--------------------------------------------------------------------------
| Prefijo final: /cliente/sat/...
| Names final:   cliente.sat.*  (por as('cliente.') en web.php + as('sat.') aquí)
|
| IMPORTANTE:
| - Este archivo YA está envuelto por middleware('cliente') en routes/web.php.
| - Por eso aquí NO debemos declarar auth:* (evita loops /cliente/login).
|
| Middleware base sugerido aquí:
| - session.cliente
| - account.active
|
| Nota negocio:
| - La cuenta puede ser FREE (sin PRO) y aun así pagar Bóveda.
| - Por eso BÓVEDA se protege con middleware separado: vault.active
|--------------------------------------------------------------------------
*/

$isLocal = app()->environment(['local', 'development', 'testing']);

/**
 * Quitar CSRF solo en local, de manera segura.
 */
$noCsrfLocal = function ($route) use ($isLocal) {
    if ($isLocal && $route) {
        $route->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
    }
    return $route;
};

/**
 * Registrar ruta solo si existe el método en el Controller (evita 500 en deploy).
 */
$hasMethod = static function (string $controllerClass, string $method): bool {
    return method_exists($controllerClass, $method);
};

/**
 * Helper: define una ruta solo si existe el método del controller y opcionalmente aplica noCsrfLocal.
 */
$onlyIfMethod = static function (
    string $controllerClass,
    string $method,
    callable $defineRoute,
    bool $applyNoCsrfLocal = true
) use ($hasMethod, $noCsrfLocal): void {
    if (!$hasMethod($controllerClass, $method)) {
        return;
    }
    $r = $defineRoute();
    if ($applyNoCsrfLocal) {
        $noCsrfLocal($r);
    }
};

// =========================
// Throttles (local más permisivo)
// =========================
$thrCredsAlias  = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrRequest     = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrVerify      = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrDownload    = $isLocal ? 'throttle:120,1' : 'throttle:20,1';
$thrZip         = $isLocal ? 'throttle:120,1' : 'throttle:30,1';
$thrReportExp   = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrPdfBatch    = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrExcelPrev   = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrDiotBuild   = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrVaultExport = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';

// =========================
// Grupo base SAT (interno)
// =========================
// OJO: NO auth:* aquí, porque ya viene desde web.php (middleware('cliente'))
Route::middleware(['session.cliente', 'account.active'])
    ->prefix('sat')
    ->as('sat.')
    ->group(function () use (
        $noCsrfLocal,
        $onlyIfMethod,
        $thrCredsAlias,
        $thrRequest,
        $thrVerify,
        $thrDownload,
        $thrZip,
        $thrReportExp,
        $thrPdfBatch,
        $thrExcelPrev,
        $thrDiotBuild,
        $thrVaultExport
    ) {

        /*
        |--------------------------------------------------------------------------
        | Dashboard SAT
        |--------------------------------------------------------------------------
        | GET /cliente/sat
        */
        Route::get('/', [SatDescargaController::class, 'index'])->name('index');

        /*
        |--------------------------------------------------------------------------
        | Dashboard SAT (JSON)
        |--------------------------------------------------------------------------
        | GET /cliente/sat/dashboard/stats -> cliente.sat.dashboard.stats
        */
        Route::get('/dashboard/stats', [SatDescargaController::class, 'dashboardStats'])
            ->middleware($thrVerify)
            ->name('dashboard.stats');

        /*
        |--------------------------------------------------------------------------
        | ✅ DESCARGAS MANUALES (cache-safe)
        |--------------------------------------------------------------------------
        */
        Route::redirect('/manual',        '/cliente/sat#block-manual-downloads', 302)->name('manual.index');
        Route::redirect('/manual/quote',  '/cliente/sat#block-manual-downloads', 302)->name('manual.quote');
        Route::redirect('/manual/create', '/cliente/sat#block-manual-downloads', 302)->name('manual.create');

        /*
        |--------------------------------------------------------------------------
        | ✅ CALCULADORA RÁPIDA (quick.*)
        |--------------------------------------------------------------------------
        */
        $onlyIfMethod(SatDescargaController::class, 'quickCalc', function () use ($thrRequest) {
            return Route::post('/quick/calc', [SatDescargaController::class, 'quickCalc'])
                ->middleware($thrRequest)
                ->name('quick.calc');
        });

        $onlyIfMethod(SatDescargaController::class, 'quickPdf', function () use ($thrVerify) {
            return Route::match(['GET', 'POST'], '/quick/pdf', [SatDescargaController::class, 'quickPdf'])
                ->middleware($thrVerify)
                ->name('quick.pdf');
        });

        /*
        |--------------------------------------------------------------------------
        | Modo demo/prod (cookie sat_mode)
        |--------------------------------------------------------------------------
        */
        $mode = Route::post('/mode', [SatExternalPublicController::class, 'toggleMode'])
            ->name('mode');
        $noCsrfLocal($mode);

        /*
        |--------------------------------------------------------------------------
        | RFC / Credenciales / Alias
        |--------------------------------------------------------------------------
        */
        $credStore = Route::post('/credenciales/store', [SatDescargaController::class, 'storeCredentials'])
            ->middleware($thrCredsAlias)
            ->name('credenciales.store');
        $noCsrfLocal($credStore);

        $rfcRegister = Route::post('/rfc/register', [SatDescargaController::class, 'registerRfc'])
            ->middleware($thrCredsAlias)
            ->name('rfc.register');
        $noCsrfLocal($rfcRegister);

        $rfcAlias = Route::post('/rfc/alias', [SatDescargaController::class, 'saveAlias'])
            ->middleware($thrCredsAlias)
            ->name('alias');
        $noCsrfLocal($rfcAlias);

        $rfcDelete = Route::match(['POST', 'DELETE'], '/rfc/delete', [SatDescargaController::class, 'deleteRfc'])
            ->middleware($thrCredsAlias)
            ->name('rfc.delete');
        $noCsrfLocal($rfcDelete);

        /*
        |--------------------------------------------------------------------------
        | ✅ REGISTRO EXTERNO / INVITE (cliente.sat.external.*)
        |--------------------------------------------------------------------------
        | GET  /cliente/sat/external/register  -> firmado (SIN LOGIN)
        | POST /cliente/sat/external/register  -> firmado (SIN LOGIN)
        */
        Route::get('/external/invite', [SatExternalPublicController::class, 'externalInviteGet'])
            ->name('external.invite.get');

        if (method_exists(SatDescargaController::class, 'externalInvite')) {
            $rInvite = Route::post('/external/invite', [SatDescargaController::class, 'externalInvite'])
                ->middleware($thrCredsAlias)
                ->name('external.invite');
            $noCsrfLocal($rInvite);
        } else {
            $rInvite = Route::post('/external/invite', [SatExternalPublicController::class, 'externalInviteFallback'])
                ->middleware($thrCredsAlias)
                ->name('external.invite');
            $noCsrfLocal($rInvite);
        }

        // SIN LOGIN: removemos middlewares del grupo (session/account) y el middleware('cliente') del web.php debe permitir signed guest
        Route::get('/external/register', [SatExternalPublicController::class, 'externalRegisterForm'])
            ->withoutMiddleware(['session.cliente', 'account.active'])
            ->name('external.register');

        $rRegisterStore = Route::post('/external/register', [SatExternalPublicController::class, 'externalRegisterStore'])
            ->middleware([$thrCredsAlias])
            ->withoutMiddleware(['session.cliente', 'account.active'])
            ->name('external.register.store');
        $noCsrfLocal($rRegisterStore);

        /*
        |--------------------------------------------------------------------------
        | Descargas masivas SAT
        |--------------------------------------------------------------------------
        */
        $req = Route::post('/request', [SatDescargaController::class, 'request'])
            ->middleware($thrRequest)
            ->name('request');
        $noCsrfLocal($req);

        $ver = Route::post('/verify', [SatDescargaController::class, 'verify'])
            ->middleware($thrVerify)
            ->name('verify');
        $noCsrfLocal($ver);

        $cancel = Route::post('/download/cancel', [SatDescargaController::class, 'cancelDownload'])
            ->middleware($thrDownload)
            ->name('download.cancel');
        $noCsrfLocal($cancel);

        Route::get('/zip/{downloadId}', [SatZipController::class, 'download'])
            ->where('downloadId', '[A-Za-z0-9\-_]+')
            ->middleware($thrZip)
            ->name('zip.download');

        $onlyIfMethod(SatDescargaController::class, 'downloadWithPdf', function () use ($thrPdfBatch) {
            return Route::post('/pdf/batch', [SatDescargaController::class, 'downloadWithPdf'])
                ->middleware($thrPdfBatch)
                ->name('pdf.batch');
        });

        $onlyIfMethod(SatDescargaController::class, 'charts', function () {
            return Route::get('/charts', [SatDescargaController::class, 'charts'])
                ->name('charts');
        }, applyNoCsrfLocal: false);

        /*
        |--------------------------------------------------------------------------
        | BÓVEDA (protegida por vault.active)
        |--------------------------------------------------------------------------
        */
        Route::get('/vault', [VaultController::class, 'index'])
            ->middleware(['vault.active'])
            ->name('vault');

        $onlyIfMethod(VaultController::class, 'quick', function () {
            return Route::get('/vault/quick', [VaultController::class, 'quick'])
                ->middleware(['vault.active'])
                ->name('vault.quick');
        }, applyNoCsrfLocal: false);

        $onlyIfMethod(VaultController::class, 'importForm', function () {
            return Route::get('/vault/import', [VaultController::class, 'importForm'])
                ->middleware(['vault.active'])
                ->name('vault.import.form');
        }, applyNoCsrfLocal: false);

        $onlyIfMethod(VaultController::class, 'importStore', function () {
            return Route::post('/vault/import', [VaultController::class, 'importStore'])
                ->middleware(['vault.active'])
                ->name('vault.import.store');
        });

        $onlyIfMethod(VaultController::class, 'export', function () use ($thrVaultExport) {
            return Route::get('/vault/export', [VaultController::class, 'export'])
                ->middleware(['vault.active', $thrVaultExport])
                ->name('vault.export');
        }, applyNoCsrfLocal: false);

        $onlyIfMethod(VaultController::class, 'downloadXml', function () use ($thrZip) {
            return Route::get('/vault/xml', [VaultController::class, 'downloadXml'])
                ->middleware(['vault.active', $thrZip])
                ->name('vault.xml');
        }, applyNoCsrfLocal: false);

        $onlyIfMethod(VaultController::class, 'downloadPdf', function () use ($thrZip) {
            return Route::get('/vault/pdf', [VaultController::class, 'downloadPdf'])
                ->middleware(['vault.active', $thrZip])
                ->name('vault.pdf');
        }, applyNoCsrfLocal: false);

        $onlyIfMethod(VaultController::class, 'downloadZip', function () use ($thrZip) {
            return Route::get('/vault/zip', [VaultController::class, 'downloadZip'])
                ->middleware(['vault.active', $thrZip])
                ->name('vault.zip');
        }, applyNoCsrfLocal: false);

        $onlyIfMethod(VaultController::class, 'fromDownload', function () {
            return Route::post('/vault/from-download/{download}', [VaultController::class, 'fromDownload'])
                ->middleware(['vault.active'])
                ->where('download', '[A-Za-z0-9\-_]+')
                ->name('vault.fromDownload');
        });

        $onlyIfMethod(VaultController::class, 'downloadVaultFile', function () use ($thrZip) {
            return Route::get('/vault/file/{id}', [VaultController::class, 'downloadVaultFile'])
                ->middleware(['vault.active', $thrZip])
                ->where('id', '[A-Za-z0-9\-_]+')
                ->name('vault.file');
        }, applyNoCsrfLocal: false);

        /*
        |--------------------------------------------------------------------------
        | Carrito SAT
        |--------------------------------------------------------------------------
        */
        Route::get('/cart', [SatCartController::class, 'index'])->name('cart.index');

        Route::get('/cart/list', [SatCartController::class, 'list'])
            ->middleware($thrVerify)
            ->name('cart.list');

        $cartAdd = Route::post('/cart/add', [SatCartController::class, 'add'])
            ->middleware($thrRequest)
            ->name('cart.add');

        $cartRemove = Route::match(['POST', 'DELETE'], '/cart/remove/{id?}', [SatCartController::class, 'remove'])
            ->where('id', '[A-Za-z0-9\-_]+')
            ->middleware($thrRequest)
            ->name('cart.remove');

        $cartClear = Route::post('/cart/clear', [SatCartController::class, 'clear'])
            ->middleware($thrRequest)
            ->name('cart.clear');

        $cartCheckout = Route::post('/cart/checkout', [SatCartController::class, 'checkout'])
            ->middleware($thrDownload)
            ->name('cart.checkout');

        Route::get('/cart/success', [SatCartController::class, 'success'])->name('cart.success');
        Route::get('/cart/cancel',  [SatCartController::class, 'cancel'])->name('cart.cancel');

        $noCsrfLocal($cartAdd);
        $noCsrfLocal($cartRemove);
        $noCsrfLocal($cartClear);
        $noCsrfLocal($cartCheckout);

        /*
        |--------------------------------------------------------------------------
        | Reportes SAT
        |--------------------------------------------------------------------------
        */
        Route::get('/reporte', [SatReporteController::class, 'index'])->name('report');

        $rExp = Route::post('/reporte/export', [SatReporteController::class, 'export'])
            ->middleware($thrReportExp)
            ->name('report.export');

        $rCanc = Route::post('/reporte/cancelados', [SatReporteController::class, 'exportCanceled'])
            ->middleware($thrReportExp)
            ->name('report.canceled');

        $rPay = Route::post('/reporte/pagos', [SatReporteController::class, 'exportPayments'])
            ->middleware($thrReportExp)
            ->name('report.payments');

        $rNotes = Route::post('/reporte/notas', [SatReporteController::class, 'exportCreditNotes'])
            ->middleware($thrReportExp)
            ->name('report.credits');

        $noCsrfLocal($rExp);
        $noCsrfLocal($rCanc);
        $noCsrfLocal($rPay);
        $noCsrfLocal($rNotes);

        /*
        |--------------------------------------------------------------------------
        | Excel / DIOT
        |--------------------------------------------------------------------------
        */
        $excelPrev = Route::post('/excel/preview', [ExcelViewerController::class, 'preview'])
            ->middleware($thrExcelPrev)
            ->name('excel.preview');

        $diot = Route::post('/diot/build', [DiotController::class, 'buildBatch'])
            ->middleware($thrDiotBuild)
            ->name('diot.build');

        $noCsrfLocal($excelPrev);
        $noCsrfLocal($diot);

        /*
        |--------------------------------------------------------------------------
        | Pago Stripe directo (LEGACY - solo si existe)
        |--------------------------------------------------------------------------
        */
        $onlyIfMethod(SatDescargaController::class, 'pay', function () use ($thrDownload, $noCsrfLocal) {
            $pay = Route::post('/pay', [SatDescargaController::class, 'pay'])
                ->middleware($thrDownload)
                ->name('pay');
            $noCsrfLocal($pay);
            return $pay;
        });

        $onlyIfMethod(SatDescargaController::class, 'paySuccess', function () {
            return Route::get('/pay/success', [SatDescargaController::class, 'paySuccess'])
                ->name('pay.success');
        }, applyNoCsrfLocal: false);

        $onlyIfMethod(SatDescargaController::class, 'payCancel', function () {
            return Route::get('/pay/cancel', [SatDescargaController::class, 'payCancel'])
                ->name('pay.cancel');
        }, applyNoCsrfLocal: false);
    });
