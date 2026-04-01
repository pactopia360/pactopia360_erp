<?php

declare(strict_types=1);

namespace App\Mail\Admin\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class StatementAccountPeriodMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $accountId;
    public string $period;

    /** @var array<string,mixed> */
    public array $data;

    public int $tries = 10;
    public int $timeout = 120;

    /** @var int[] */
    public array $backoff = [60, 120, 300, 600];

    public function __construct(string $accountId, string $period, array $data)
    {
        $this->accountId = $accountId;
        $this->period    = $period;
        $this->data      = $data;
    }

    public function build()
    {
        $period = (string) ($this->data['period'] ?? $this->period);
        $label  = (string) ($this->data['period_label'] ?? $period);

        $subjectOverride = trim((string) ($this->data['subject_override'] ?? ''));
        $subject = $subjectOverride !== ''
            ? $subjectOverride
            : ((string) ($this->data['subject'] ?? ''));

        if ($subject === '') {
            $subject = 'Pactopia360 · Estado de cuenta ' . $period . ($label !== '' ? ' · ' . $label : '');
        }

        $replyAddr = trim((string) ($this->data['MAIL_REPLY_TO_ADDRESS'] ?? ($this->data['reply_to_address'] ?? '')));
        $replyName = trim((string) ($this->data['MAIL_REPLY_TO_NAME'] ?? ($this->data['reply_to_name'] ?? '')));

        $view = (string) ($this->data['template'] ?? 'emails.admin.billing.statement_account_period');
        $this->data['template'] = $view;

        // Solo hidratar desde DB si falta información clave.
        $needsHydration = !isset($this->data['statement_id']) || !isset($this->data['statement']) || !isset($this->data['total_due']);

        if ($needsHydration) {
            $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

            if (Schema::connection($adm)->hasTable('billing_statements')) {
                $st = DB::connection($adm)->table('billing_statements')
                    ->where('account_id', (string) $this->accountId)
                    ->where('period', $period)
                    ->orderByDesc('id')
                    ->first([
                        'id',
                        'account_id',
                        'period',
                        'status',
                        'total_cargo',
                        'total_abono',
                        'saldo',
                        'due_date',
                        'sent_at',
                        'paid_at',
                        'snapshot',
                        'meta',
                        'is_locked',
                        'created_at',
                        'updated_at',
                    ]);

                if ($st) {
                    $cargo = (float) ($st->total_cargo ?? 0);
                    $abono = (float) ($st->total_abono ?? 0);
                    $saldo = (float) ($st->saldo ?? 0);

                    $meta = [];
                    if (is_array($st->meta)) {
                        $meta = $st->meta;
                    } elseif (is_string($st->meta) && trim($st->meta) !== '') {
                        $decoded = json_decode($st->meta, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $meta = $decoded;
                        }
                    }

                    $prev = (float) ($meta['prev_saldo'] ?? 0);
                    $periodDue = max(0, $cargo - $abono);

                    $this->data['statement']         = $st;
                    $this->data['statement_id']      = (int) ($st->id ?? 0);
                    $this->data['statement_status']  = (string) ($st->status ?? '');
                    $this->data['statement_cargo']   = $cargo;
                    $this->data['statement_abono']   = $abono;
                    $this->data['statement_saldo']   = $periodDue;

                    $this->data['total_cargo']       = $cargo;
                    $this->data['total_abono']       = $abono;
                    $this->data['saldo']             = $saldo;
                    $this->data['total']             = $saldo;
                    $this->data['prev_balance']      = $prev;
                    $this->data['current_period_due']= $periodDue;
                    $this->data['total_due']         = $saldo;
                }
            }
        }

        $this->data['period']         = $period;
        $this->data['period_label']   = $label;
        $this->data['subject']        = $subject;
        $this->data['generated_at']   = $this->data['generated_at'] ?? now();
        $this->data['emailTitle']     = $subject;
        $this->data['openPixelUrl']   = (string) ($this->data['open_pixel_url'] ?? '');
        $this->data['footerPrimary']  = (string) ($this->data['footerPrimary'] ?? 'Este correo fue emitido por Pactopia360.');
        $this->data['footerSecondary']= (string) ($this->data['footerSecondary'] ?? 'Para cualquier aclaración, responde a este mensaje o entra a tu portal.');

        $saldoMail = (float) ($this->data['total_due'] ?? $this->data['total'] ?? $this->data['saldo'] ?? 0);
        $this->data['emailPreheader'] = (string) ($this->data['emailPreheader'] ?? (
            $saldoMail > 0.00001
                ? ('Tienes un saldo pendiente en tu estado de cuenta por $' . number_format($saldoMail, 2) . ' MXN.')
                : 'Tu estado de cuenta está al corriente.'
        ));

        $m = $this->subject($subject)
            ->view($view)
            ->with($this->data)
            ->bcc('notificaciones@pactopia.com', 'Pactopia Notificaciones');

        if ($replyAddr !== '') {
            $m->replyTo($replyAddr, $replyName !== '' ? $replyName : null);
        }

        return $m;
    }
}