<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\Billing\InvoiceRequestsController;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GeneratePpdInvoices extends Command
{
    protected $signature = 'billing:generate-ppd-invoices
        {--period= : Periodo YYYY-MM. Default: mes actual}
        {--date= : Fecha de ejecución YYYY-MM-DD. Default: hoy}
        {--limit=100 : Límite de perfiles a procesar}
        {--dry-run : Simula sin guardar}
        {--stamp : Forzar timbrado aunque auto_stamp esté apagado}
        {--send : Forzar envío aunque auto_send esté apagado}';

    protected $description = 'Genera solicitudes PPD programadas, timbra y envía facturas especiales de Pactopia360.';

    private string $adm = 'mysql_admin';

    public function handle(): int
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (!Schema::connection($this->adm)->hasTable('billing_invoice_profiles')) {
            $this->error('No existe billing_invoice_profiles.');
            return self::FAILURE;
        }

        if (!Schema::connection($this->adm)->hasTable('billing_invoice_requests')) {
            $this->error('No existe billing_invoice_requests.');
            return self::FAILURE;
        }

        if (!Schema::connection($this->adm)->hasTable('billing_statements')) {
            $this->error('No existe billing_statements.');
            return self::FAILURE;
        }

        $runDate = $this->resolveRunDate();
        $period = $this->resolvePeriod();
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $forceStamp = (bool) $this->option('stamp');
        $forceSend = (bool) $this->option('send');

        $this->info("Procesando PPD · periodo={$period} · fecha={$runDate->toDateString()} · dryRun=" . ($dryRun ? 'yes' : 'no'));

        $profiles = DB::connection($this->adm)
            ->table('billing_invoice_profiles')
            ->where('status', 'active')
            ->where('requires_invoice', 1)
            ->where('auto_generate_request', 1)
            ->where(function ($q) use ($runDate) {
                $q->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $runDate->toDateString());
            })
            ->where(function ($q) use ($runDate) {
                $q->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $runDate->toDateString());
            })
            ->where(function ($q) use ($runDate) {
                $q->whereNull('schedule_day')
                    ->orWhere('schedule_day', '<=', (int) $runDate->day);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($profiles->isEmpty()) {
            $this->info('No hay perfiles PPD programados para procesar.');
            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $stamped = 0;
        $sent = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($profiles as $profile) {
            try {
                $result = $this->processProfile($profile, $period, $runDate, $dryRun, $forceStamp, $forceSend);

                match ($result['action']) {
                    'created' => $created++,
                    'updated' => $updated++,
                    'skipped' => $skipped++,
                    default => null,
                };

                if ($result['stamped'] ?? false) {
                    $stamped++;
                }

                if ($result['sent'] ?? false) {
                    $sent++;
                }

                $this->line(sprintf(
                    '[%s] profile=%s account=%s request=%s %s',
                    strtoupper((string) $result['action']),
                    (string) $profile->id,
                    (string) $profile->account_id,
                    (string) ($result['request_id'] ?? '-'),
                    (string) ($result['message'] ?? '')
                ));
            } catch (Throwable $e) {
                $errors++;

                Log::error('[BILLING][PPD_COMMAND] profile failed', [
                    'profile_id' => $profile->id ?? null,
                    'account_id' => $profile->account_id ?? null,
                    'period' => $period,
                    'error' => $e->getMessage(),
                ]);

                $this->error('ERROR profile=' . ($profile->id ?? '-') . ' account=' . ($profile->account_id ?? '-') . ': ' . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Listo. created={$created}, updated={$updated}, stamped={$stamped}, sent={$sent}, skipped={$skipped}, errors={$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processProfile(object $profile, string $period, Carbon $runDate, bool $dryRun, bool $forceStamp, bool $forceSend): array
    {
        $accountId = trim((string) ($profile->account_id ?? ''));

        if ($accountId === '') {
            return [
                'action' => 'skipped',
                'message' => 'Perfil sin account_id.',
            ];
        }

        $statement = DB::connection($this->adm)
            ->table('billing_statements')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->orderByDesc('id')
            ->first();

        if (!$statement) {
            return [
                'action' => 'skipped',
                'message' => 'No existe estado de cuenta para el periodo.',
            ];
        }

        if ((float) ($statement->total_cargo ?? 0) <= 0) {
            return [
                'action' => 'skipped',
                'message' => 'Estado sin total facturable.',
            ];
        }

        $existing = DB::connection($this->adm)
            ->table('billing_invoice_requests')
            ->where('account_id', $accountId)
            ->where('period', $period)
            ->first();

        $now = now();

        $payload = [
            'account_id' => $accountId,
            'period' => $period,
            'statement_id' => (int) $statement->id,
            'invoice_profile_id' => (int) $profile->id,
            'status' => 'requested',
            'tipo_comprobante' => 'I',
            'metodo_pago' => strtoupper(trim((string) ($profile->metodo_pago ?? 'PPD'))) ?: 'PPD',
            'forma_pago' => trim((string) ($profile->forma_pago ?? '99')) ?: '99',
            'uso_cfdi' => trim((string) ($profile->uso_cfdi ?? 'G03')) ?: 'G03',
            'regimen_fiscal' => trim((string) ($profile->regimen_fiscal ?? '')) ?: null,
            'scheduled_for' => $runDate->toDateString(),
            'scheduled_at' => $now,
            'last_error' => null,
            'send_statement_pdf' => (int) ($profile->send_statement_pdf ?? 1),
            'send_cfdi_pdf' => (int) ($profile->send_cfdi_pdf ?? 1),
            'send_cfdi_xml' => (int) ($profile->send_cfdi_xml ?? 1),
            'notes' => 'Solicitud PPD generada automáticamente por comando.',
            'meta' => json_encode([
                'source' => 'billing_generate_ppd_invoices_command',
                'profile_id' => (int) $profile->id,
                'account_id' => $accountId,
                'period' => $period,
                'statement_id' => (int) $statement->id,
                'run_date' => $runDate->toDateString(),
                'generated_at' => $now->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => $now,
        ];

        $payload = $this->filterColumns('billing_invoice_requests', $payload);

        if ($dryRun) {
            return [
                'action' => $existing ? 'updated' : 'created',
                'request_id' => $existing->id ?? null,
                'message' => 'DRY RUN sin guardar.',
            ];
        }

        if ($existing) {
            DB::connection($this->adm)
                ->table('billing_invoice_requests')
                ->where('id', (int) $existing->id)
                ->update($payload);

            $requestId = (int) $existing->id;
            $action = 'updated';
        } else {
            $payload['created_at'] = $now;
            $payload = $this->filterColumns('billing_invoice_requests', $payload);

            $requestId = (int) DB::connection($this->adm)
                ->table('billing_invoice_requests')
                ->insertGetId($payload);

            $action = 'created';
        }

        $stamped = false;
        $sent = false;

        $shouldStamp = $forceStamp || (bool) ($profile->auto_stamp ?? false);
        $shouldSend = $forceSend || (bool) ($profile->auto_send ?? false);

        if ($shouldStamp) {
            $this->stampRequest($requestId);
            $stamped = $this->isRequestIssued($requestId);
        }

        if ($shouldSend && $this->isRequestIssued($requestId)) {
            $this->sendRequest($requestId);
            $sent = true;
        }

        return [
            'action' => $action,
            'request_id' => $requestId,
            'stamped' => $stamped,
            'sent' => $sent,
            'message' => 'Procesado correctamente.',
        ];
    }

    private function stampRequest(int $requestId): void
    {
        /** @var InvoiceRequestsController $controller */
        $controller = app(InvoiceRequestsController::class);

        $request = Request::create('/admin/billing/invoices/requests/' . $requestId . '/approve-generate?src=hub', 'POST');

        $controller->approveAndGenerate($request, $requestId);
    }

    private function sendRequest(int $requestId): void
    {
        /** @var InvoiceRequestsController $controller */
        $controller = app(InvoiceRequestsController::class);

        $request = Request::create('/admin/billing/invoices/requests/' . $requestId . '/send?src=hub', 'POST');

        $controller->sendInvoice($request, $requestId);
    }

    private function isRequestIssued(int $requestId): bool
    {
        $row = DB::connection($this->adm)
            ->table('billing_invoice_requests')
            ->where('id', $requestId)
            ->first();

        return $row && in_array((string) ($row->status ?? ''), ['issued', 'done'], true);
    }

    private function filterColumns(string $table, array $payload): array
    {
        $cols = Schema::connection($this->adm)->getColumnListing($table);
        $cols = array_map('strtolower', $cols);

        return collect($payload)
            ->filter(fn ($value, $key) => in_array(strtolower((string) $key), $cols, true))
            ->all();
    }

    private function resolveRunDate(): Carbon
    {
        $date = trim((string) $this->option('date'));

        if ($date !== '') {
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        }

        return now()->startOfDay();
    }

    private function resolvePeriod(): string
    {
        $period = trim((string) $this->option('period'));

        if ($period !== '') {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
                throw new \InvalidArgumentException('El periodo debe tener formato YYYY-MM.');
            }

            return $period;
        }

        return now()->format('Y-m');
    }
}