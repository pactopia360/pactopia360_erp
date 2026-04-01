<?php

namespace App\Jobs\Admin\Billing;

use App\Mail\Admin\Billing\StatementAccountPeriodMail;
use App\Models\Admin\Billing\BillingStatement;
use App\Models\Admin\Billing\BillingStatementEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SendStatementEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 5;

    private string $adm = 'mysql_admin';

    public function __construct(public int $statementId, public string $actor = 'system')
    {
    }

    public function handle(): void
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        /** @var BillingStatement|null $st */
        $st = BillingStatement::query()
            ->with(['items'])
            ->find($this->statementId);

        if (!$st) {
            Log::warning('[P360][STATEMENTS][JOB] Statement no encontrado, se omite job.', [
                'statement_id' => $this->statementId,
                'actor'        => $this->actor,
            ]);
            return;
        }

        $accountId = trim((string) $st->account_id);
        $period    = trim((string) $st->period);

        if ($accountId === '' || !preg_match('/^\d{4}\-\d{2}$/', $period)) {
            $this->markFailedAndSkip(
                $st,
                'Statement inválido para envío.',
                [
                    'statement_id' => $st->id,
                    'account_id'   => $accountId,
                    'period'       => $period,
                ]
            );
            return;
        }

        $account = DB::connection($this->adm)
            ->table('accounts')
            ->where('id', $accountId)
            ->first();

        if (!$account) {
            $this->markFailedAndSkip(
                $st,
                'Cuenta admin no encontrada.',
                [
                    'statement_id' => $st->id,
                    'account_id'   => $accountId,
                    'period'       => $period,
                ]
            );
            return;
        }

        $items = DB::connection($this->adm)
            ->table('billing_statement_items')
            ->where('statement_id', $st->id)
            ->orderBy('id')
            ->get();

        $guard = $this->validateStatementBeforeSend($st, $account, $items);
        if (!$guard['ok']) {
            $this->markFailedAndSkip(
                $st,
                (string) $guard['message'],
                [
                    'statement_id' => $st->id,
                    'account_id'   => $accountId,
                    'period'       => $period,
                    'guard'        => $guard['meta'],
                ]
            );
            return;
        }

        $recipients = $this->resolveRecipientsForAccount($accountId, (string) ($account->email ?? ''));
        if (empty($recipients)) {
            $this->markFailedAndSkip(
                $st,
                'Sin destinatarios configurados.',
                [
                    'statement_id' => $st->id,
                    'account_id'   => $accountId,
                    'period'       => $period,
                ]
            );
            return;
        }

        $sent = 0;
        $failed = 0;
        $sentEmails = [];
        $failedEmails = [];

        foreach ($recipients as $dest) {
            $emailId = (string) Str::ulid();
            $payload = $this->buildPayload($st, $account, $items, $emailId);

            $subject = trim((string) ($payload['subject'] ?? ''));
            if ($subject === '') {
                $subject = 'Pactopia360 · Estado de cuenta ' . $period . ' · ' . (string) ($account->razon_social ?? $account->name ?? 'Cliente');
                $payload['subject'] = $subject;
            }

            $logId = 0;

            try {
                $logId = $this->insertEmailLog([
                    'email_id'     => $emailId,
                    'statement_id' => $st->id,
                    'account_id'   => $accountId,
                    'period'       => $period,
                    'email'        => $dest,
                    'to_list'      => $dest,
                    'template'     => 'emails.admin.billing.statement_account_period',
                    'status'       => 'queued',
                    'provider'     => config('mail.default') ?: 'smtp',
                    'subject'      => $subject,
                    'payload'      => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'meta'         => json_encode([
                        'source'        => 'p360:statements:send',
                        'actor'         => $this->actor,
                        'statement_id'  => $st->id,
                        'account_id'    => $accountId,
                        'period'        => $period,
                        'bcc_monitor'   => 'notificaciones@pactopia.com',
                    ], JSON_UNESCAPED_UNICODE),
                    'queued_at'     => now(),
                ]);

                Mail::to($dest)->send(new StatementAccountPeriodMail($accountId, $period, $payload));

                $this->updateEmailLogSent($logId);

                $sent++;
                $sentEmails[] = $dest;
            } catch (\Throwable $e) {
                $failed++;
                $failedEmails[] = [
                    'email' => $dest,
                    'error' => $e->getMessage(),
                ];

                if ($logId > 0) {
                    $this->updateEmailLogFailed($logId, $e->getMessage(), [
                        'source'       => 'p360:statements:send',
                        'actor'        => $this->actor,
                        'statement_id' => $st->id,
                        'account_id'   => $accountId,
                        'period'       => $period,
                        'email'        => $dest,
                    ]);
                }

                Log::error('[P360][STATEMENTS][JOB] Fallo envío estado de cuenta', [
                    'statement_id' => $st->id,
                    'account_id'   => $accountId,
                    'period'       => $period,
                    'email'        => $dest,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        if ($sent > 0 && $failed === 0) {
            $st->sent_at = now();
            $st->save();
        }

        $eventName = 'sent';
        $eventNotes = 'Sent via queue (unified mail/log flow).';

        if ($sent > 0 && $failed > 0) {
            $eventName = 'partial';
            $eventNotes = 'Envío parcial: algunos destinatarios fallaron.';
        } elseif ($sent === 0 && $failed > 0) {
            $eventName = 'failed';
            $eventNotes = 'No se pudo enviar a ningún destinatario.';
        }

        $this->createEvent(
            $st->id,
            $eventName,
            $eventNotes,
            [
                'sent_count'    => $sent,
                'failed_count'  => $failed,
                'sent_emails'   => $sentEmails,
                'failed_emails' => $failedEmails,
                'actor'         => $this->actor,
            ]
        );
    }

    /**
     * @return array{ok:bool,message:string,meta:array<string,mixed>}
     */
    private function validateStatementBeforeSend(BillingStatement $st, object $account, $items): array
    {
        $snapshot = $st->snapshot;

        $hasSnapshot = false;
        if (is_array($snapshot) && !empty($snapshot)) {
            $hasSnapshot = true;
        } elseif (is_string($snapshot) && trim($snapshot) !== '') {
            $decoded = json_decode($snapshot, true);
            $hasSnapshot = json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded);
        }

        $itemCount = 0;
        if (is_iterable($items)) {
            foreach ($items as $item) {
                $itemCount++;
            }
        }

        $totalCargo = round((float) ($st->total_cargo ?? 0), 2);
        $totalAbono = round((float) ($st->total_abono ?? 0), 2);
        $saldo      = round((float) ($st->saldo ?? 0), 2);

        $accountEmail = trim((string) ($account->email ?? ''));

        if (!$hasSnapshot) {
            return [
                'ok'      => false,
                'message' => 'Guard blocked: statement sin snapshot válido.',
                'meta'    => [
                    'reason'       => 'missing_snapshot',
                    'statement_id' => $st->id,
                    'account_id'   => (string) $st->account_id,
                    'period'       => (string) $st->period,
                ],
            ];
        }

        if ($itemCount === 0 && $totalCargo <= 0.00001 && $totalAbono <= 0.00001 && $saldo <= 0.00001) {
            return [
                'ok'      => false,
                'message' => 'Guard blocked: statement vacío, sin items ni importes.',
                'meta'    => [
                    'reason'       => 'empty_statement',
                    'statement_id' => $st->id,
                    'account_id'   => (string) $st->account_id,
                    'period'       => (string) $st->period,
                    'item_count'   => $itemCount,
                    'total_cargo'  => $totalCargo,
                    'total_abono'  => $totalAbono,
                    'saldo'        => $saldo,
                ],
            ];
        }

        if ($accountEmail === '' && $itemCount === 0) {
            return [
                'ok'      => false,
                'message' => 'Guard blocked: sin email principal y sin items para respaldo visual.',
                'meta'    => [
                    'reason'       => 'weak_statement_data',
                    'statement_id' => $st->id,
                    'account_id'   => (string) $st->account_id,
                    'period'       => (string) $st->period,
                ],
            ];
        }

        return [
            'ok'      => true,
            'message' => 'ok',
            'meta'    => [
                'item_count'  => $itemCount,
                'total_cargo' => $totalCargo,
                'total_abono' => $totalAbono,
                'saldo'       => $saldo,
            ],
        ];
    }

    private function markFailedAndSkip(BillingStatement $st, string $message, array $meta = []): void
    {
        $this->createEvent($st->id, 'failed', $message, $meta);

        Log::warning('[P360][STATEMENTS][JOB] Envío bloqueado por guard.', array_merge([
            'statement_id' => $st->id,
            'actor'        => $this->actor,
            'message'      => $message,
        ], $meta));
    }

    /**
     * @return array<int,string>
     */
    private function resolveRecipientsForAccount(string $accountId, string $fallbackEmail): array
    {
        $emails = [];

        if (Schema::connection($this->adm)->hasTable('account_recipients')) {
            try {
                $q = DB::connection($this->adm)->table('account_recipients')
                    ->select('email');

                if (Schema::connection($this->adm)->hasColumn('account_recipients', 'is_active')) {
                    $q->where('is_active', 1);
                }

                $q->where('account_id', $accountId);

                if (Schema::connection($this->adm)->hasColumn('account_recipients', 'kind')) {
                    $q->where(function ($w) {
                        $w->where('kind', 'statement')
                          ->orWhereNull('kind');
                    });
                }

                if (Schema::connection($this->adm)->hasColumn('account_recipients', 'is_primary')) {
                    $q->orderByDesc('is_primary');
                }

                $rows = $q->orderBy('email')->get();

                foreach ($rows as $row) {
                    $e = strtolower(trim((string) ($row->email ?? '')));
                    if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $e;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[P360][STATEMENTS][JOB] resolveRecipientsForAccount failed', [
                    'account_id' => $accountId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $fallbackEmail = strtolower(trim($fallbackEmail));
        if ($fallbackEmail !== '' && filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $fallbackEmail;
        }

        return array_values(array_unique($emails));
    }

    /**
     * @param mixed $account
     * @param mixed $items
     * @return array<string,mixed>
     */
    private function buildPayload(BillingStatement $st, $account, $items, string $emailId): array
    {
        $period = trim((string) $st->period);

        $razonSocial  = trim((string) ($account->razon_social ?? $account->name ?? 'Cliente'));
        $accountEmail = trim((string) ($account->email ?? ''));
        $accountRfc   = trim((string) ($account->rfc ?? ''));

        $meta = is_array($st->meta) ? $st->meta : [];
        $prevSaldo = round((float) ($meta['prev_saldo'] ?? 0), 2);

        $totalCargo = round((float) ($st->total_cargo ?? 0), 2);
        $totalAbono = round((float) ($st->total_abono ?? 0), 2);
        $saldoTotal = round((float) ($st->saldo ?? max(0, $prevSaldo + $totalCargo - $totalAbono)), 2);
        $saldoPeriodo = round(max(0, $totalCargo - $totalAbono), 2);

        $status = strtolower(trim((string) ($st->status ?? 'pending')));
        $status = match ($status) {
            'paid', 'pagado', 'succeeded', 'success', 'completed', 'complete', 'captured', 'confirmed' => 'pagado',
            'partial', 'parcial' => 'parcial',
            'overdue', 'vencido', 'past_due', 'unpaid' => 'vencido',
            'sin_mov', 'sin_movimiento', 'sin movimiento', 'no_movement' => 'sin_mov',
            default => 'pendiente',
        };

        $periodLabel = $period;
        try {
            $dt = \Carbon\Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            $periodLabel = ucfirst($dt->locale('es')->translatedFormat('F Y'));
        } catch (\Throwable $e) {
        }

        $pdfUrl = '';
        $portalUrl = '';
        $payUrl = '';

        try {
            if (Route::has('cliente.billing.publicPdfInline')) {
                $pdfUrl = URL::signedRoute('cliente.billing.publicPdfInline', [
                    'accountId' => (string) $st->account_id,
                    'period'    => $period,
                ]);
            }
        } catch (\Throwable $e) {
            $pdfUrl = '';
        }

        try {
            if (Route::has('cliente.estado_cuenta')) {
                $portalUrl = route('cliente.estado_cuenta') . '?period=' . urlencode($period);
            }
        } catch (\Throwable $e) {
            $portalUrl = '';
        }

        try {
            if ($saldoTotal > 0.00001 && Route::has('admin.billing.hub.paylink')) {
                $payUrl = route('admin.billing.hub.paylink') . '?account_id=' . urlencode((string) $st->account_id) . '&period=' . urlencode($period);
            }
        } catch (\Throwable $e) {
            $payUrl = '';
        }

        $openPixelUrl = '';
        $pdfTrackUrl = $pdfUrl;
        $portalTrackUrl = $portalUrl;
        $payTrackUrl = $payUrl;

        try {
            if (Route::has('admin.billing.hub.track_open')) {
                $openPixelUrl = route('admin.billing.hub.track_open', ['emailId' => $emailId]);
            }
        } catch (\Throwable $e) {
            $openPixelUrl = '';
        }

        try {
            if (Route::has('admin.billing.hub.track_click')) {
                $wrap = function (string $url) use ($emailId): string {
                    return $url !== ''
                        ? route('admin.billing.hub.track_click', ['emailId' => $emailId]) . '?u=' . urlencode($url)
                        : '';
                };

                $pdfTrackUrl    = $wrap($pdfUrl);
                $portalTrackUrl = $wrap($portalUrl);
                $payTrackUrl    = $wrap($payUrl);
            }
        } catch (\Throwable $e) {
            $pdfTrackUrl    = $pdfUrl;
            $portalTrackUrl = $portalUrl;
            $payTrackUrl    = $payUrl;
        }

        $subject = 'Pactopia360 · Estado de cuenta ' . $period . ' · ' . $razonSocial;

        return [
            'template'            => 'emails.admin.billing.statement_account_period',
            'subject'             => $subject,
            'email_id'            => $emailId,
            'generated_at'        => now(),

            'account' => (object) [
                'id'           => (string) $st->account_id,
                'razon_social' => $razonSocial,
                'name'         => $razonSocial,
                'rfc'          => $accountRfc,
                'email'        => $accountEmail,
            ],

            'statement'           => $st,
            'statement_id'        => (int) $st->id,
            'statement_status'    => $status,
            'statement_cargo'     => $totalCargo,
            'statement_abono'     => $totalAbono,
            'statement_saldo'     => $saldoPeriodo,

            'period'              => $period,
            'period_label'        => $periodLabel,
            'items'               => $items,

            'tarifa_label'        => 'Estado de cuenta',
            'total_cargo'         => $totalCargo,
            'total_abono'         => $totalAbono,
            'saldo'               => $saldoTotal,
            'total'               => $saldoTotal,
            'current_period_due'  => $saldoPeriodo,
            'prev_balance'        => $prevSaldo,
            'total_due'           => $saldoTotal,
            'status_pago'         => $status,

            'pdf_url'             => $pdfUrl,
            'portal_url'          => $portalUrl,
            'pay_url'             => $payUrl,

            'open_pixel_url'      => $openPixelUrl,
            'pdf_track_url'       => $pdfTrackUrl,
            'portal_track_url'    => $portalTrackUrl,
            'pay_track_url'       => $payTrackUrl,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function insertEmailLog(array $row): int
    {
        if (!Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            throw new \RuntimeException('No existe billing_email_logs.');
        }

        $cols = array_map(
            static fn ($c) => strtolower((string) $c),
            Schema::connection($this->adm)->getColumnListing('billing_email_logs')
        );

        $has = static fn (string $c): bool => in_array(strtolower($c), $cols, true);

        $email   = trim((string) ($row['email'] ?? ''));
        $toList  = trim((string) ($row['to_list'] ?? ''));
        $subject = trim((string) ($row['subject'] ?? ''));

        if ($toList === '' && $email !== '') {
            $toList = $email;
        }

        if ($subject === '') {
            $subject = 'Pactopia360 · Estado de cuenta ' . (string) ($row['period'] ?? '');
        }

        $insert = [];

        if ($has('email_id'))            $insert['email_id'] = (string) ($row['email_id'] ?? Str::ulid());
        if ($has('statement_id'))        $insert['statement_id'] = $row['statement_id'] ?? null;
        if ($has('account_id'))          $insert['account_id'] = (string) ($row['account_id'] ?? '');
        if ($has('period'))              $insert['period'] = (string) ($row['period'] ?? '');
        if ($has('email'))               $insert['email'] = $email !== '' ? $email : null;
        if ($has('to'))                  $insert['to'] = $email !== '' ? $email : null;
        if ($has('to_list'))             $insert['to_list'] = $toList !== '' ? $toList : null;
        if ($has('template'))            $insert['template'] = (string) ($row['template'] ?? 'emails.admin.billing.statement_account_period');
        if ($has('status'))              $insert['status'] = (string) ($row['status'] ?? 'queued');
        if ($has('provider'))            $insert['provider'] = $row['provider'] ?? null;
        if ($has('provider_message_id')) $insert['provider_message_id'] = $row['provider_message_id'] ?? null;
        if ($has('subject'))             $insert['subject'] = $subject;
        if ($has('payload'))             $insert['payload'] = $row['payload'] ?? null;
        if ($has('meta'))                $insert['meta'] = $row['meta'] ?? null;
        if ($has('queued_at'))           $insert['queued_at'] = $row['queued_at'] ?? now();
        if ($has('sent_at'))             $insert['sent_at'] = $row['sent_at'] ?? null;
        if ($has('failed_at'))           $insert['failed_at'] = $row['failed_at'] ?? null;
        if ($has('open_count'))          $insert['open_count'] = (int) ($row['open_count'] ?? 0);
        if ($has('click_count'))         $insert['click_count'] = (int) ($row['click_count'] ?? 0);
        if ($has('created_at'))          $insert['created_at'] = now();
        if ($has('updated_at'))          $insert['updated_at'] = now();

        return (int) DB::connection($this->adm)
            ->table('billing_email_logs')
            ->insertGetId($insert);
    }

    private function updateEmailLogSent(int $logId): void
    {
        if ($logId <= 0 || !Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            return;
        }

        $update = ['updated_at' => now()];

        $cols = array_map(
            static fn ($c) => strtolower((string) $c),
            Schema::connection($this->adm)->getColumnListing('billing_email_logs')
        );

        if (in_array('status', $cols, true)) {
            $update['status'] = 'sent';
        }
        if (in_array('sent_at', $cols, true)) {
            $update['sent_at'] = now();
        }

        DB::connection($this->adm)
            ->table('billing_email_logs')
            ->where('id', $logId)
            ->update($update);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function updateEmailLogFailed(int $logId, string $message, array $context = []): void
    {
        if ($logId <= 0 || !Schema::connection($this->adm)->hasTable('billing_email_logs')) {
            return;
        }

        $cols = array_map(
            static fn ($c) => strtolower((string) $c),
            Schema::connection($this->adm)->getColumnListing('billing_email_logs')
        );

        $update = ['updated_at' => now()];

        if (in_array('status', $cols, true)) {
            $update['status'] = 'failed';
        }
        if (in_array('failed_at', $cols, true)) {
            $update['failed_at'] = now();
        }

        if (in_array('meta', $cols, true)) {
            $update['meta'] = json_encode(array_merge($context, [
                'error'    => $message,
                'error_at' => now()->toDateTimeString(),
            ]), JSON_UNESCAPED_UNICODE);
        }

        DB::connection($this->adm)
            ->table('billing_email_logs')
            ->where('id', $logId)
            ->update($update);
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function createEvent(int $statementId, string $event, string $notes, array $meta = []): void
    {
        try {
            BillingStatementEvent::create([
                'statement_id' => $statementId,
                'event'        => $event,
                'actor'        => $this->actor,
                'notes'        => $notes,
                'meta'         => $meta,
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[P360][STATEMENTS][JOB] No se pudo registrar BillingStatementEvent', [
                'statement_id' => $statementId,
                'event'        => $event,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}