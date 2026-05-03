<?php

use App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('p360:billing-statements-monthly {period?}', function (?string $period = null) {
    $period = trim((string) ($period ?: now()->format('Y-m')));

    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
        $this->error("Periodo inválido: {$period}");
        return self::FAILURE;
    }

    /** @var BillingStatementsV2Controller $controller */
    $controller = app(BillingStatementsV2Controller::class);

    Log::info('[P360][BILLING_AUTO] Inicio corte + envío automático', [
        'period' => $period,
    ]);

    $cutoffRequest = Request::create('/admin/billing/statements-v2/generate-cutoff', 'POST', [
        'period' => $period,
        'force'  => 0,
    ]);

    $controller->generateCutoff($cutoffRequest);

    $sendRequest = Request::create('/admin/billing/statements-v2/bulk/send', 'POST', [
        'period'   => $period,
        'mode'     => 'visible',
        'per_page' => 1000,
    ]);

    $response = $controller->sendBulk($sendRequest);
    $payload = $response->getData(true);

    Log::info('[P360][BILLING_AUTO] Fin corte + envío automático', [
        'period'   => $period,
        'response' => $payload,
    ]);

    $this->info('Corte + envío automático terminado.');
    $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return (($payload['ok'] ?? false) === true) ? self::SUCCESS : self::FAILURE;
})->purpose('Genera el corte mensual y envía estados de cuenta automáticamente.');

/*
|--------------------------------------------------------------------------
| Programación automática
|--------------------------------------------------------------------------
| Día 1 de cada mes a las 08:00 AM.
*/
Schedule::command('p360:billing-statements-monthly')
    ->monthlyOn(1, '08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/p360-billing-statements-monthly.log'));