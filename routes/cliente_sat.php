<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Middleware\VerifyCsrfToken as AppCsrf;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkCsrf;

/*
|--------------------------------------------------------------------------
| Controladores SAT (cliente)
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Cliente\Sat\SatDescargaController;
use App\Http\Controllers\Cliente\Sat\SatReporteController;
use App\Http\Controllers\Cliente\Sat\ExcelViewerController;
use App\Http\Controllers\Cliente\Sat\DiotController;
use App\Http\Controllers\Cliente\Sat\VaultController;

$isLocal = app()->environment(['local','development','testing']);

/*
|--------------------------------------------------------------------------
| Throttles específicos SAT
|--------------------------------------------------------------------------
*/
$thrCredsAlias = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrRequest    = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrVerify     = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrDownload   = $isLocal ? 'throttle:120,1' : 'throttle:20,1';
$thrZip        = $isLocal ? 'throttle:120,1' : 'throttle:30,1';

$thrReportExp  = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrPdfBatch   = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrExcelPrev  = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';
$thrDiotBuild  = $isLocal ? 'throttle:60,1'  : 'throttle:12,1';

/*
|--------------------------------------------------------------------------
| Área autenticada cliente + sesión aislada + cuenta activa
| Prefijo /cliente/sat  —  Nombre de rutas cliente.sat.*
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:web','session.cliente','account.active'])
    ->prefix('sat')
    ->as('sat.')
    ->group(function () use (
        $isLocal,
        $thrCredsAlias, $thrRequest, $thrVerify, $thrDownload, $thrZip,
        $thrReportExp, $thrPdfBatch, $thrExcelPrev, $thrDiotBuild
    ) {

        /* ---------- Home / Dashboard ---------- */
        Route::get('/', [SatDescargaController::class, 'index'])->name('index');

        /* ---------- Bóveda Fiscal ---------- */
        Route::get('/vault', [VaultController::class, 'index'])->name('vault');

        /* ---------- Toggle DEMO/PROD (cookie sat_mode) ---------- */
        $mode = Route::post('/mode', function (Request $request) {
            $current = strtolower((string) $request->cookie('sat_mode', 'prod'));
            $next    = $current === 'demo' ? 'prod' : 'demo';
            $minutes = 60 * 24 * 30; // 30 días

            return response()->json(['ok' => true, 'mode' => $next])
                ->cookie('sat_mode', $next, $minutes, path: '/', domain: null, secure: false, httpOnly: false, sameSite: 'lax');
        })->name('mode');

        if ($isLocal) {
            $mode->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        /* ---------- RFC / Credenciales / Alias ---------- */
        $r1 = Route::post('/credenciales/store', [SatDescargaController::class, 'storeCredentials'])
            ->middleware($thrCredsAlias)->name('credenciales.store');

        $r2 = Route::post('/rfc/register', [SatDescargaController::class, 'registerRfc'])
            ->middleware($thrCredsAlias)->name('rfc.register');

        $r3 = Route::post('/rfc/alias', [SatDescargaController::class, 'saveAlias'])
            ->middleware($thrCredsAlias)->name('alias');

        if ($isLocal) foreach ([$r1,$r2,$r3] as $route) {
            $route->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        /* ---------- Descarga masiva: request / verify / download / zip ---------- */
        $req = Route::post('/request',  [SatDescargaController::class, 'requestList'])
            ->middleware($thrRequest)->name('request');

        $ver = Route::post('/verify',   [SatDescargaController::class, 'verify'])
            ->middleware($thrVerify)->name('verify');

        $dl  = Route::post('/download', [SatDescargaController::class, 'downloadPackage'])
            ->middleware($thrDownload)->name('download');

        Route::get('/zip/{id}', [SatDescargaController::class, 'downloadZip'])
            ->whereNumber('id')->middleware($thrZip)->name('zip');

        if ($isLocal) foreach ([$req,$ver,$dl] as $route) {
            $route->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        /* ---------- Reportes ---------- */
        Route::get('/reporte', [SatReporteController::class, 'index'])->name('report');

        $rExp   = Route::post('/reporte/export',     [SatReporteController::class, 'export'])
            ->middleware($thrReportExp)->name('report.export');

        $rCanc  = Route::post('/reporte/cancelados', [SatReporteController::class, 'exportCanceled'])
            ->middleware($thrReportExp)->name('report.canceled');

        $rPay   = Route::post('/reporte/pagos',      [SatReporteController::class, 'exportPayments'])
            ->middleware($thrReportExp)->name('report.payments');

        $rNotes = Route::post('/reporte/notas',      [SatReporteController::class, 'exportCreditNotes'])
            ->middleware($thrReportExp)->name('report.credits');

        if ($isLocal) foreach ([$rExp,$rCanc,$rPay,$rNotes] as $route) {
            $route->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }

        /* ---------- PDF masivo (hook) ---------- */
        $pdfBatch = Route::post('/pdf/batch', [SatDescargaController::class, 'downloadWithPdf'])
            ->middleware($thrPdfBatch)->name('pdf.batch');

        if ($isLocal) $pdfBatch->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);

        /* ---------- Visor Excel / DIOT ---------- */
        $excelPrev = Route::post('/excel/preview', [ExcelViewerController::class, 'preview'])
            ->middleware($thrExcelPrev)->name('excel.preview');

        $diot = Route::post('/diot/build', [DiotController::class, 'buildBatch'])
            ->middleware($thrDiotBuild)->name('diot.build');

        if ($isLocal) foreach ([$excelPrev,$diot] as $route) {
            $route->withoutMiddleware([AppCsrf::class, FrameworkCsrf::class]);
        }
    });
