<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Admin\Billing\StatementAccountPeriodMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

final class BillingProcessScheduledEmails extends Command
{
    protected $signature = 'p360:billing:process-scheduled-emails {--limit=50}';
    protected $description = 'Procesa y envía correos programados desde billing_email_logs (status=queued)';

    private string $adm;

    public function __construct()
    {
        parent::__construct();
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function handle(): int
    {
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            $this->info('billing_email_logs no existe. Nada que procesar.');
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $now   = now();

        $rows = DB::connection($this->adm)->table('billing_email_logs')
            ->where('status', 'queued')
            ->where(function ($q) use ($now) {
                $q->whereNull('queued_at')->orWhere('queued_at', '<=', $now);
            })
            ->orderBy('queued_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($rows->count() === 0) {
            $this->info('No hay correos queued para enviar.');
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($rows as $r) {
            $id        = (int) ($r->id ?? 0);
            $emailId   = trim((string) ($r->email_id ?? ''));
            $accountId = trim((string) ($r->account_id ?? ''));
            $period    = trim((string) ($r->period ?? ''));
            $subject   = trim((string) ($r->subject ?? ''));

            $toList = trim((string) ($r->to_list ?? ''));
            if ($toList === '') {
                $toList = trim((string) ($r->to ?? $r->email ?? ''));
            }

            $tos = $this->parseToList($toList);

            $payload = $this->decodePayload($r->payload ?? null);

            if ($accountId === '' && !empty($payload['account_id'])) {
                $accountId = trim((string) $payload['account_id']);
            }

            if ($period === '' && !empty($payload['period'])) {
                $period = trim((string) $payload['period']);
            }

            if ($id <= 0 || $emailId === '' || $accountId === '' || !preg_match('/^\d{4}\-\d{2}$/', $period) || empty($tos)) {
                $failed++;
                $this->markFailed($id, 'Payload inválido (id/email_id/account_id/period/to_list).', [
                    'id'         => $id,
                    'email_id'   => $emailId,
                    'account_id' => $accountId,
                    'period'     => $period,
                    'to_list'    => $toList,
                    'payload'    => $payload,
                ]);
                continue;
            }

            if (empty($payload) || !is_array($payload)) {
                $failed++;
                $this->markFailed($id, 'El log no contiene payload utilizable.', [
                    'id'         => $id,
                    'email_id'   => $emailId,
                    'account_id' => $accountId,
                    'period'     => $period,
                    'to_list'    => $toList,
                ]);
                continue;
            }

            // Refuerza datos mínimos del payload ya generado por el flujo unificado
            $payload['account_id'] = $accountId;
            $payload['period']     = $period;
            $payload['email_id']   = $emailId;

            if ($subject !== '') {
                $payload['subject_override'] = $subject;
                $payload['subject'] = $subject;
            }

            if (empty($payload['template'])) {
                $payload['template'] = 'emails.admin.billing.statement_account_period';
            }

            if (empty($payload['generated_at'])) {
                $payload['generated_at'] = now();
            }

            try {
                Mail::to($tos)->send(new StatementAccountPeriodMail($accountId, $period, $payload));

                $sent++;

                $update = [
                    'status'     => 'sent',
                    'sent_at'    => now(),
                    'updated_at' => now(),
                ];

                if ($this->hasColumn('billing_email_logs', 'error')) {
                    $update['error'] = null;
                }

                DB::connection($this->adm)
                    ->table('billing_email_logs')
                    ->where('id', $id)
                    ->update($update);
            } catch (\Throwable $e) {
                $failed++;

                Log::error('[P360][BILLING][SCHEDULED_EMAIL] fallo envío', [
                    'id'         => $id,
                    'email_id'   => $emailId,
                    'to_list'    => $tos,
                    'account_id' => $accountId,
                    'period'     => $period,
                    'e'          => $e->getMessage(),
                ]);

                $this->markFailed($id, $e->getMessage(), [
                    'id'         => $id,
                    'email_id'   => $emailId,
                    'to_list'    => $tos,
                    'account_id' => $accountId,
                    'period'     => $period,
                ]);
            }
        }

        $this->info("Procesados: {$rows->count()} | sent={$sent} | failed={$failed}");
        return self::SUCCESS;
    }

    /**
     * @return array<int,string>
     */
    private function parseToList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $raw = str_replace([';', "\n", "\r", "\t"], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, static fn ($x) => $x !== '');

        $out = [];
        foreach ($parts as $p) {
            if (preg_match('/<([^>]+)>/', $p, $m)) {
                $p = trim((string) $m[1]);
            }

            if (filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $out[] = strtolower($p);
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 20);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        try {
            if (is_array($payload)) {
                return $payload;
            }

            if (is_string($payload) && trim($payload) !== '') {
                $decoded = json_decode($payload, true);
                return is_array($decoded) ? $decoded : [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $context
     */
    private function markFailed(int $id, string $message, array $context = []): void
    {
        try {
            $update = [
                'status'     => 'failed',
                'failed_at'  => now(),
                'updated_at' => now(),
            ];

            if ($this->hasColumn('billing_email_logs', 'error')) {
                $update['error'] = mb_substr($message, 0, 65000);
            }

            if ($this->hasColumn('billing_email_logs', 'meta')) {
                $update['meta'] = json_encode(array_merge($context, [
                    'error'    => $message,
                    'error_at' => now()->toDateTimeString(),
                    'source'   => 'p360:billing:process-scheduled-emails',
                ]), JSON_UNESCAPED_UNICODE);
            }

            DB::connection($this->adm)
                ->table('billing_email_logs')
                ->where('id', $id)
                ->update($update);
        } catch (\Throwable $e) {
            Log::error('[P360][BILLING][SCHEDULED_EMAIL] no se pudo marcar failed', [
                'id'  => $id,
                'msg' => $message,
                'ctx' => $context,
                'e'   => $e->getMessage(),
            ]);
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection($this->adm)->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}