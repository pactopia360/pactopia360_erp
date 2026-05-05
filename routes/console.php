<?php

use App\Http\Controllers\Admin\Billing\BillingStatementsV2Controller;
use App\Http\Controllers\Admin\Billing\InvoiceRequestsController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

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
    $payload = method_exists($response, 'getData') ? $response->getData(true) : ['ok' => true];

    Log::info('[P360][BILLING_AUTO] Fin corte + envío automático', [
        'period'   => $period,
        'response' => $payload,
    ]);

    $this->info('Corte + envío automático terminado.');
    $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return (($payload['ok'] ?? false) === true) ? self::SUCCESS : self::FAILURE;
})->purpose('Genera el corte mensual y envía estados de cuenta automáticamente.');

Artisan::command('p360:billing-ppd-specials {period?} {--dry-run}', function (?string $period = null) {
    $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    $period = trim((string) ($period ?: now()->format('Y-m')));
    $dryRun = (bool) $this->option('dry-run');

    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
        $this->error("Periodo inválido: {$period}");
        return self::FAILURE;
    }

    foreach (['billing_invoice_profiles', 'billing_statements', 'billing_invoice_requests', 'billing_invoices'] as $table) {
        if (!Schema::connection($adm)->hasTable($table)) {
            $this->error("Falta tabla requerida: {$table}");
            return self::FAILURE;
        }
    }

    $profileCols = Schema::connection($adm)->getColumnListing('billing_invoice_profiles');
    $profileLc = array_map('strtolower', $profileCols);
    $profileHas = static fn (string $column): bool => in_array(strtolower($column), $profileLc, true);

    $profilesQuery = DB::connection($adm)
        ->table('billing_invoice_profiles');

    if ($profileHas('requires_invoice')) {
        $profilesQuery->where('requires_invoice', 1);
    }

    if ($profileHas('is_active')) {
        $profilesQuery->where('is_active', 1);
    } elseif ($profileHas('active')) {
        $profilesQuery->where('active', 1);
    } elseif ($profileHas('enabled')) {
        $profilesQuery->where('enabled', 1);
    } elseif ($profileHas('status')) {
        $profilesQuery->whereIn('status', ['active', 'activo', 'enabled']);
    }

    $profilesQuery->where(function ($q) use ($profileHas) {
        $hasAny = false;

        if ($profileHas('metodo_pago')) {
            $q->orWhere('metodo_pago', 'PPD');
            $hasAny = true;
        }

        if ($profileHas('auto_generate_request')) {
            $q->orWhere('auto_generate_request', 1);
            $hasAny = true;
        }

        if ($profileHas('auto_stamp')) {
            $q->orWhere('auto_stamp', 1);
            $hasAny = true;
        }

        if ($profileHas('auto_send')) {
            $q->orWhere('auto_send', 1);
            $hasAny = true;
        }

        if (!$hasAny) {
            $q->whereRaw('1 = 1');
        }
    });

    $profiles = $profilesQuery
        ->orderBy('id')
        ->get();
    $stats = [
        'period' => $period,
        'dry_run' => $dryRun,
        'profiles' => $profiles->count(),
        'requests_created' => 0,
        'requests_existing' => 0,
        'stamped' => 0,
        'sent' => 0,
        'skipped' => 0,
        'failed' => 0,
        'errors' => [],
    ];

    /** @var BillingStatementsV2Controller $statements */
    $statements = app(BillingStatementsV2Controller::class);

    /** @var InvoiceRequestsController $invoices */
    $invoices = app(InvoiceRequestsController::class);

    Log::info('[P360][BILLING_PPD_SPECIALS] START', [
        'period' => $period,
        'dry_run' => $dryRun,
    ]);

    foreach ($profiles as $profile) {
        $accountId = trim((string) ($profile->account_id ?? ''));

        if ($accountId === '') {
            $stats['skipped']++;
            continue;
        }

        try {
            $statement = DB::connection($adm)
                ->table('billing_statements')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->first();

            if (!$statement) {
                $stats['skipped']++;
                continue;
            }

            $existingRequest = DB::connection($adm)
                ->table('billing_invoice_requests')
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->orderByDesc('id')
                ->first();

            if (!$existingRequest && (int) ($profile->auto_generate_request ?? 0) === 1) {
                if (!$dryRun) {
                    $req = Request::create('/admin/billing/statements-v2/invoice-request', 'POST', [
                        'account_id' => $accountId,
                        'period' => $period,
                        'invoice_profile_id' => (int) $profile->id,
                    ]);

                    $statements->generateInvoiceRequest($req, $accountId, $period);
                }

                $stats['requests_created']++;

                $existingRequest = DB::connection($adm)
                    ->table('billing_invoice_requests')
                    ->where('account_id', $accountId)
                    ->where('period', $period)
                    ->orderByDesc('id')
                    ->first();
            } elseif ($existingRequest) {
                $stats['requests_existing']++;
            }

            if (!$existingRequest) {
                $stats['skipped']++;
                continue;
            }

            $requestId = (int) $existingRequest->id;

            $invoice = DB::connection($adm)
                ->table('billing_invoices')
                ->where('request_id', $requestId)
                ->orderByDesc('id')
                ->first();

            if (!$invoice && (int) ($profile->auto_stamp ?? 0) === 1) {
                if (!$dryRun) {
                    $stampReq = Request::create('/admin/billing/invoices/requests/' . $requestId . '/approve-generate', 'POST', [
                        'src' => 'hub',
                    ]);

                    $invoices->approveAndGenerate($stampReq, $requestId);
                }

                $invoice = DB::connection($adm)
                    ->table('billing_invoices')
                    ->where('request_id', $requestId)
                    ->orderByDesc('id')
                    ->first();

                $freshRequest = DB::connection($adm)
                    ->table('billing_invoice_requests')
                    ->where('id', $requestId)
                    ->first();

                if ($invoice || !empty($freshRequest->cfdi_uuid)) {
                    $stats['stamped']++;
                } else {
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'account_id' => $accountId,
                        'profile_id' => (int) ($profile->id ?? 0),
                        'request_id' => $requestId,
                        'error' => (string) ($freshRequest->notes ?? $freshRequest->last_error ?? 'No se generó factura.'),
                    ];

                    continue;
                }
            }

            if ($invoice && (int) ($profile->auto_send ?? 0) === 1) {
                $alreadySent = !empty($invoice->sent_at);

                if (!$alreadySent) {
                    if (!$dryRun) {
                        $sendReq = Request::create('/admin/billing/invoices/requests/' . $requestId . '/send', 'POST', [
                            'src' => 'hub',
                        ]);

                        $invoices->sendInvoice($sendReq, $requestId);
                    }

                    $stats['sent']++;
                }
            }
        } catch (Throwable $e) {
            $stats['failed']++;
            $stats['errors'][] = [
                'account_id' => $accountId,
                'profile_id' => (int) ($profile->id ?? 0),
                'error' => $e->getMessage(),
            ];

            Log::error('[P360][BILLING_PPD_SPECIALS] failed profile', [
                'account_id' => $accountId,
                'profile_id' => (int) ($profile->id ?? 0),
                'period' => $period,
                'error' => $e->getMessage(),
            ]);
        }
    }

    Log::info('[P360][BILLING_PPD_SPECIALS] END', $stats);

    $this->info('Proceso PPD especiales terminado.');
    $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
})->purpose('Genera, timbra y envía facturas PPD para clientes especiales.');

Schedule::command('p360:billing-statements-monthly')
    ->monthlyOn(1, '08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/p360-billing-statements-monthly.log'));

Schedule::command('p360:billing-ppd-specials')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/p360-billing-ppd-specials.log'));