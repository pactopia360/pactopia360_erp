<?php
// C:\wamp64\www\pactopia360_erp\routes\cliente_sat.php
// PACTOPIA360 · SAT Cliente routes (SOT SAT)
// ✅ Este archivo se monta desde routes/web.php con:
//    Route::prefix('cliente')->as('cliente.')->group(base_path('routes/cliente_sat.php'));
// Por lo tanto, aquí DEFINIMOS rutas relativas a /cliente/... y nombres relativos a cliente.*
//
// Objetivos:
// ✅ Evitar duplicados (route:cache)
// ✅ Robustez en deploy: solo registrar rutas si existe el método
// ✅ CSRF solo relajado en local (cuando aplique)
// ✅ Throttle consistente por operación
// ✅ Paths y names estables (para JS y Blade)

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;

use App\Http\Controllers\Cliente\Sat\SatDescargaController;
use App\Http\Controllers\Cliente\Sat\SatReporteController;
use App\Http\Controllers\Cliente\Sat\ExcelViewerController;
use App\Http\Controllers\Cliente\Sat\DiotController;
use App\Http\Controllers\Cliente\Sat\VaultController;
use App\Http\Controllers\Cliente\Sat\SatCartController;
use App\Http\Controllers\Cliente\Sat\SatZipController;

/*
|--------------------------------------------------------------------------
| Rutas SAT Cliente (Descargas masivas CFDI, Bóveda, Reportes, Carrito)
|--------------------------------------------------------------------------
| Prefijo final: /cliente/sat/...
| Names final:   cliente.sat.*  (por as('cliente.') en web.php + as('sat.') aquí)
|
| Middleware base:
| - auth:web
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
// Grupo base SAT
// =========================
Route::middleware(['auth:web', 'session.cliente', 'account.active'])
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
        | Modo demo/prod (cookie sat_mode)
        |--------------------------------------------------------------------------
        | POST /cliente/sat/mode
        */
        $mode = Route::post('/mode', function (Request $request) {
            $current = strtolower((string) $request->cookie('sat_mode', 'prod'));
            $next    = $current === 'demo' ? 'prod' : 'demo';
            $minutes = 60 * 24 * 30; // 30 días

            return response()
                ->json(['ok' => true, 'mode' => $next])
                // cookie(name, value, minutes, path, domain, secure, httpOnly, raw, sameSite)
                ->cookie('sat_mode', $next, $minutes, '/', null, false, false, false, 'lax');
        })->name('mode');

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

        // Alias: aquí dejamos name('rfc.alias') (ANTES tenías name('alias') y eso rompe consistencia)
        $rfcAlias = Route::post('/rfc/alias', [SatDescargaController::class, 'saveAlias'])
            ->middleware($thrCredsAlias)
            ->name('rfc.alias');
        $noCsrfLocal($rfcAlias);

        // ✅ IMPORTANTE: SOLO UNA RUTA con name('rfc.delete') (evita el duplicado que te rompió route:cache)
        $rfcDelete = Route::match(['POST', 'DELETE'], '/rfc/delete', [SatDescargaController::class, 'deleteRfc'])
            ->middleware($thrCredsAlias)
            ->name('rfc.delete');
        $noCsrfLocal($rfcDelete);

        /*
        |--------------------------------------------------------------------------
        | Descargas masivas SAT
        |--------------------------------------------------------------------------
        */
        $req = Route::post('/request', [SatDescargaController::class, 'request'])
            ->middleware($thrRequest)
            ->name('request');
        $noCsrfLocal($req);

        // ✅ Consistencia: verify por POST (poll desde JS puede ser GET o POST; aquí dejamos POST para CSRF token)
        $ver = Route::post('/verify', [SatDescargaController::class, 'verify'])
            ->middleware($thrVerify)
            ->name('verify');
        $noCsrfLocal($ver);

        $cancel = Route::post('/download/cancel', [SatDescargaController::class, 'cancelDownload'])
            ->middleware($thrDownload)
            ->name('download.cancel');
        $noCsrfLocal($cancel);

        // ZIP: descarga es GET (no se debe quitar CSRF; en local no afecta, pero mantenemos applyNoCsrfLocal=false)
        $zip = Route::get('/zip/{downloadId}', [SatZipController::class, 'download'])
            ->whereNumber('downloadId')
            ->middleware($thrZip)
            ->name('zip.download');

        /*
        |--------------------------------------------------------------------------
        | PDF masivo (si existe el método)
        |--------------------------------------------------------------------------
        */
        $onlyIfMethod(SatDescargaController::class, 'downloadWithPdf', function () use ($thrPdfBatch) {
            return Route::post('/pdf/batch', [SatDescargaController::class, 'downloadWithPdf'])
                ->middleware($thrPdfBatch)
                ->name('pdf.batch');
        });

        /*
        |--------------------------------------------------------------------------
        | Gráficas SAT (si existe el método)
        |--------------------------------------------------------------------------
        */
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
            ->middleware('vault.active')
            ->name('vault');

        $onlyIfMethod(VaultController::class, 'quick', function () {
            return Route::get('/vault/quick', [VaultController::class, 'quick'])
                ->middleware('vault.active')
                ->name('vault.quick');
        }, applyNoCsrfLocal: false);

        $onlyIfMethod(VaultController::class, 'importForm', function () {
            return Route::get('/vault/import', [VaultController::class, 'importForm'])
                ->middleware('vault.active')
                ->name('vault.import.form');
        }, applyNoCsrfLocal: false);

        $onlyIfMethod(VaultController::class, 'importStore', function () {
            return Route::post('/vault/import', [VaultController::class, 'importStore'])
                ->middleware('vault.active')
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
                ->middleware('vault.active')
                ->whereNumber('download')
                ->name('vault.fromDownload');
        });

        // Descarga de archivo bóveda: GET (no CSRF). applyNoCsrfLocal=false
        $onlyIfMethod(VaultController::class, 'downloadVaultFile', function () use ($thrZip) {
            return Route::get('/vault/file/{id}', [VaultController::class, 'downloadVaultFile'])
                ->middleware(['vault.active', $thrZip])
                ->whereNumber('id')
                ->name('vault.file');
        }, applyNoCsrfLocal: false);

        /*
        |--------------------------------------------------------------------------
        | Carrito SAT
        |--------------------------------------------------------------------------
        */
        Route::get('/cart', [SatCartController::class, 'index'])->name('cart.index');

        $cartList = Route::get('/cart/list', [SatCartController::class, 'list'])
            ->middleware($thrVerify)
            ->name('cart.list');

        $cartAdd = Route::post('/cart/add', [SatCartController::class, 'add'])
            ->middleware($thrRequest)
            ->name('cart.add');

        $cartRemove = Route::match(['POST', 'DELETE'], '/cart/remove/{id?}', [SatCartController::class, 'remove'])
            ->where(['id' => '\d+'])
            ->middleware($thrRequest)
            ->name('cart.remove');

        $cartClear = Route::post('/cart/clear', [SatCartController::class, 'clear'])
            ->middleware($thrRequest)
            ->name('cart.clear');

        $cartCheckout = Route::post('/cart/checkout', [SatCartController::class, 'checkout'])
            ->middleware($thrDownload)
            ->name('cart.checkout');

        // Callbacks GET (no CSRF)
        Route::get('/cart/success', [SatCartController::class, 'success'])->name('cart.success');
        Route::get('/cart/cancel',  [SatCartController::class, 'cancel'])->name('cart.cancel');

        // CSRF relax solo local para POSTS
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
        | Nota: si ya lo migraste a carrito/checkout, puedes eliminar este bloque.
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
