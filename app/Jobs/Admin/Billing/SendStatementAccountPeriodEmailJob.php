<?php

declare(strict_types=1);

namespace App\Jobs\Admin\Billing;

use App\Mail\Admin\Billing\StatementAccountPeriodMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendStatementAccountPeriodEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public int $logId;
    public string $accountId;
    public string $period;

    /** @var array<int,string> */
    public array $tos;

    /** @var array<string,mixed> */
    public array $payload;

    /**
     * @param array<int,string> $tos
     * @param array<string,mixed> $payload
     */
    public function __construct(int $logId, string $accountId, string $period, array $tos, array $payload)
    {
        $this->logId     = $logId;
        $this->accountId = $accountId;
        $this->period    = $period;
        $this->tos       = $tos;
        $this->payload   = $payload;
    }

    public function handle(): void
    {
        // Source of truth: billing_email_logs
        $row = DB::connection('mysql_admin')
            ->table('billing_email_logs')
            ->where('id', $this->logId)
            ->first();

        $accountId = $this->accountId;
        $period    = $this->period;

        // Destinatarios (preferir log)
        $tos = $this->tos;

        if ($row) {
            $toList = trim((string) ($row->to_list ?? ''));
            if ($toList !== '') {
                $parts = preg_split('/[;,\s]+/', $toList) ?: [];
                $tos = array_values(array_filter(array_map('trim', $parts)));
            }

            if (empty($tos)) {
                $to = trim((string) ($row->to ?? $row->email ?? ''));
                if ($to !== '') $tos = [$to];
            }
        }

        if (empty($tos)) {
            throw new \RuntimeException('Sin destinatarios para enviar estado de cuenta (logId='.$this->logId.').');
        }

        // Payload (preferir log.payload si existe)
        $payload = $this->payload;

        if ($row && !empty($row->payload)) {
            $decoded = json_decode((string) $row->payload, true);
            if (is_array($decoded)) $payload = $decoded;
        }

        // Forzar subject_override desde billing_email_logs.subject
        if ($row) {
            $subject = trim((string) ($row->subject ?? ''));
            if ($subject !== '') $payload['subject_override'] = $subject;
        }

        // Enviar (este Job es el owner del tracking; NO usar ->queue aquÃ­)
        Mail::to($tos)->send(new StatementAccountPeriodMail($accountId, $period, $payload));

        // Marcar sent
        if ($row) {
            DB::connection('mysql_admin')->table('billing_email_logs')->where('id', $this->logId)->update([
                'status'     => 'sent',
                'sent_at'    => now(),
                'failed_at'  => null,
                'error'      => null,
                'updated_at' => now(),
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        DB::connection('mysql_admin')->table('billing_email_logs')->where('id', $this->logId)->update([
            'status'     => 'failed',
            'failed_at'  => now(),
            'error'      => mb_substr((string) $e->getMessage(), 0, 1000, 'UTF-8'),
            'updated_at' => now(),
        ]);
    }
}
