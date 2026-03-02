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

    // ✅ resiliencia de cola (por tus timeouts SMTP)
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
        $label  = (string) ($this->data['period_label'] ?? '');

        // subject override (si viene desde billing_email_logs.subject)
        $subjectOverride = (string) ($this->data['subject_override'] ?? '');
        $subject = $subjectOverride !== ''
            ? $subjectOverride
            : ('Pactopia360 · Estado de cuenta ' . $period . ($label ? ' · ' . $label : ''));

        $replyAddr = (string) ($this->data['MAIL_REPLY_TO_ADDRESS'] ?? ($this->data['reply_to_address'] ?? ''));
        $replyName = (string) ($this->data['MAIL_REPLY_TO_NAME'] ?? ($this->data['reply_to_name'] ?? ''));

        $view = (string) ($this->data['template'] ?? 'admin.mail.statement');

        // =====================================================
        // ✅ AUTORIDAD: si existe billing_statement del periodo, úsalo para totales
        // (evita que el correo muestre 0 cuando hay saldo real)
        // =====================================================
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (Schema::connection($adm)->hasTable('billing_statements')) {

            // Trae TODOS los statements del periodo y elige el “mejor”
            // (saldo desc, cargo desc, id desc)
            $st = DB::connection($adm)->table('billing_statements')
                ->where('account_id', (string) $this->accountId)
                ->where('period', $period)
                ->orderByDesc(DB::raw('CAST(saldo AS DECIMAL(18,2))'))
                ->orderByDesc(DB::raw('CAST(total_cargo AS DECIMAL(18,2))'))
                ->orderByDesc('id')
                ->first([
                    'id', 'account_id', 'period',
                    'status', 'total_cargo', 'total_abono', 'saldo',
                    'due_date', 'sent_at', 'paid_at',
                    'snapshot', 'meta', 'is_locked',
                    'created_at', 'updated_at',
                ]);

            if ($st) {
                // Normaliza números (por si vienen string)
                $cargo = (float) ($st->total_cargo ?? 0);
                $abono = (float) ($st->total_abono ?? 0);
                $saldo = (float) ($st->saldo ?? 0);

                // Inyecta al payload para que el Blade sea consistente
                $this->data['statement'] = $st;

                // Claves “autoridad” (recomendadas para el Blade)
                $this->data['statement_id']     = (int) ($st->id ?? 0);
                $this->data['statement_status'] = (string) ($st->status ?? '');
                $this->data['statement_cargo']  = $cargo;
                $this->data['statement_abono']  = $abono;
                $this->data['statement_saldo']  = $saldo;

                // Compat: si tu template usa estos nombres
                $this->data['total_cargo'] = $cargo;
                $this->data['total_abono'] = $abono;
                $this->data['saldo']       = $saldo;
                $this->data['total']       = $saldo; // “saldo a pagar”
            }
        }

        $m = $this->subject($subject)
            ->view($view)
            ->with($this->data);

        if ($replyAddr !== '') {
            $m->replyTo($replyAddr, $replyName !== '' ? $replyName : null);
        }

        return $m;
    }
}