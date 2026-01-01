<?php

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
| Rutas SAT Cliente (Descargas masivas CFDI, Bóveda, Reportes, Pagos)
|--------------------------------------------------------------------------
| Prefijo final: /cliente/sat/...
| Este archivo cuelga de routes/cliente.php
| Middleware base: auth:web, session.cliente, account.active
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
$hasMethod = function (string $controllerClass, string $method): bool {
    return method_exists($controllerClass, $method);
};

/**
 * Helper: define una ruta solo si existe el método del controller y opcionalmente aplica noCsrfLocal.
 */
$onlyIfMethod = function (
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

// Throttles (local más permisivo)
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

Route::middleware(['auth:web', 'session.cliente', 'account.active'])
    ->prefix('sat')
    ->as('sat.')
    ->group(function () use (
        $isLocal,
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
        |----------------------------------------------------------------------
        | Dashboard SAT
        |----------------------------------------------------------------------
        | GET /cliente/sat
        */
        Route::get('/', [SatDescargaController::class, 'index'])->name('index');

        /*
        |----------------------------------------------------------------------
        | BÓVEDA (protegida por vault.active)
        |----------------------------------------------------------------------
        */
        Route::get('/vault', [VaultController::class, 'index'])
            ->middleware('vault.active')
            ->name('vault');

        $onlyIfMethod(VaultController::class, 'quick', function () {
            return Route::get('/vault/quick', [VaultController::class, 'quick'])
                ->middleware('vault.active')
                ->name('vault.quick');
        });

        $onlyIfMethod(VaultController::class, 'importForm', function () {
            return Route::get('/vault/import', [VaultController::class, 'importForm'])
                ->middleware('vault.active')
                ->name('vault.import.form');
        });

        $onlyIfMethod(VaultController::class, 'importStore', function () {
            return Route::post('/vault/import', [VaultController::class, 'importStore'])
                ->middleware('vault.active')
                ->name('vault.import.store');
        });

        $onlyIfMethod(VaultController::class, 'export', function () use ($thrVaultExport) {
            return Route::get('/vault/export', [VaultController::class, 'export'])
                ->middleware(['vault.active', $thrVaultExport])
                ->name('vault.export');
        });

        $onlyIfMethod(VaultController::class, 'downloadXml', function () use ($thrZip) {
            return Route::get('/vault/xml', [VaultController::class, 'downloadXml'])
                ->middleware(['vault.active', $thrZip])
                ->name('vault.xml');
        });

        $onlyIfMethod(VaultController::class, 'downloadPdf', function () use ($thrZip) {
            return Route::get('/vault/pdf', [VaultController::class, 'downloadPdf'])
                ->middleware(['vault.active', $thrZip])
                ->name('vault.pdf');
        });

        $onlyIfMethod(VaultController::class, 'downloadZip', function () use ($thrZip) {
            return Route::get('/vault/zip', [VaultController::class, 'downloadZip'])
                ->middleware(['vault.active', $thrZip])
                ->name('vault.zip');
        });

        $onlyIfMethod(VaultController::class, 'fromDownload', function () {
            return Route::post('/vault/from-download/{download}', [VaultController::class, 'fromDownload'])
                ->middleware('vault.active')
                ->name('vault.fromDownload');
        });

        $onlyIfMethod(VaultController::class, 'downloadVaultFile', function () use ($thrZip) {
            return Route::get('/vault/file/{id}', [VaultController::class, 'downloadVaultFile'])
                ->middleware(['vault.active', $thrZip])
                ->name('vault.file');
        }, applyNoCsrfLocal: false);

        /*
        |----------------------------------------------------------------------
        | Modo demo/prod
        |----------------------------------------------------------------------
        | POST /cliente/sat/mode
        */
        $mode = Route::post('/mode', function (Request $request) {
            $current = strtolower((string) $request->cookie('sat_mode', 'prod'));
            $next    = $current === 'demo' ? 'prod' : 'demo';
            $minutes = 60 * 24 * 30; // 30 días

            return response()
                ->json(['ok' => true, 'mode' => $next])
                // IMPORTANTE: evitar named parameters aquí (provocaban Unknown named parameter $path)
                // cookie(name, value, minutes, path, domain, secure, httpOnly, raw, sameSite)
                ->cookie('sat_mode', $next, $minutes, '/', null, false, false, false, 'lax');
        })->name('mode');

        $noCsrfLocal($mode);

        /*
        |----------------------------------------------------------------------
        | RFC / Credenciales / Alias
        |----------------------------------------------------------------------
        */
        $r1 = Route::post('/credenciales/store', [SatDescargaController::class, 'storeCredentials'])
            ->middleware($thrCredsAlias)
            ->name('credenciales.store');

        $r2 = Route::post('/rfc/register', [SatDescargaController::class, 'registerRfc'])
            ->middleware($thrCredsAlias)
            ->name('rfc.register');

        $r3 = Route::post('/rfc/alias', [SatDescargaController::class, 'saveAlias'])
            ->middleware($thrCredsAlias)
            ->name('alias');

        $r4 = Route::match(['POST', 'DELETE'], '/rfc/delete', [SatDescargaController::class, 'deleteRfc'])
            ->middleware($thrCredsAlias)
            ->name('rfc.delete');

        $noCsrfLocal($r1);
        $noCsrfLocal($r2);
        $noCsrfLocal($r3);
        $noCsrfLocal($r4);

        /*
        |----------------------------------------------------------------------
        | Descargas masivas SAT
        |----------------------------------------------------------------------
        */
        $req = Route::post('/request', [SatDescargaController::class, 'request'])
            ->middleware($thrRequest)
            ->name('request');

        $ver = Route::post('/verify', [SatDescargaController::class, 'verify'])
            ->middleware($thrVerify)
            ->name('verify');

        $cancel = Route::post('/download/cancel', [SatDescargaController::class, 'cancelDownload'])
            ->middleware($thrDownload)
            ->name('download.cancel');

        $zip = Route::get('/zip/{downloadId}', [SatZipController::class, 'download'])
            ->middleware($thrZip)
            ->name('zip.download');

        $noCsrfLocal($req);
        $noCsrfLocal($ver);
        $noCsrfLocal($cancel);
        $noCsrfLocal($zip);

        /*
        |----------------------------------------------------------------------
        | Carrito SAT
        |----------------------------------------------------------------------
        */
        $cartIndex    = Route::get('/cart', [SatCartController::class, 'index'])->name('cart.index');
        $cartList     = Route::get('/cart/list', [SatCartController::class, 'list'])->name('cart.list');
        $cartAdd      = Route::post('/cart/add', [SatCartController::class, 'add'])->name('cart.add');

        $cartRemove   = Route::match(['POST', 'DELETE'], '/cart/remove/{id?}', [SatCartController::class, 'remove'])
            ->name('cart.remove');

        $cartClear    = Route::post('/cart/clear', [SatCartController::class, 'clear'])->name('cart.clear');
        $cartCheckout = Route::post('/cart/checkout', [SatCartController::class, 'checkout'])->name('cart.checkout');

        $cartSuccess = Route::get('/cart/success', [SatCartController::class, 'success'])->name('cart.success');
        $cartCancel  = Route::get('/cart/cancel',  [SatCartController::class, 'cancel'])->name('cart.cancel');

        $noCsrfLocal($cartIndex);
        $noCsrfLocal($cartList);
        $noCsrfLocal($cartAdd);
        $noCsrfLocal($cartRemove);
        $noCsrfLocal($cartClear);
        $noCsrfLocal($cartCheckout);
        $noCsrfLocal($cartSuccess);
        $noCsrfLocal($cartCancel);

        /*
        |----------------------------------------------------------------------
        | Reportes SAT
        |----------------------------------------------------------------------
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
        |----------------------------------------------------------------------
        | PDF masivo
        |----------------------------------------------------------------------
        */
        $pdfBatch = Route::post('/pdf/batch', [SatDescargaController::class, 'downloadWithPdf'])
            ->middleware($thrPdfBatch)
            ->name('pdf.batch');

        $noCsrfLocal($pdfBatch);

        /*
        |----------------------------------------------------------------------
        | Excel / DIOT
        |----------------------------------------------------------------------
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
        |----------------------------------------------------------------------
        | Gráficas SAT
        |----------------------------------------------------------------------
        */
        Route::get('/charts', [SatDescargaController::class, 'charts'])->name('charts');

        /*
        |----------------------------------------------------------------------
        | Pago Stripe directo (si aún lo necesitas)
        |----------------------------------------------------------------------
        */
        $pay = Route::post('/pay', [SatDescargaController::class, 'pay'])
            ->middleware($thrDownload)
            ->name('pay');

        Route::get('/pay/success', [SatDescargaController::class, 'paySuccess'])->name('pay.success');
        Route::get('/pay/cancel',  [SatDescargaController::class, 'payCancel'])->name('pay.cancel');

        $noCsrfLocal($pay);
    });
