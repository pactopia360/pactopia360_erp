<?php

declare(strict_types=1);

namespace App\Mail\Admin\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class StatementAccountPeriodMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $accountId;
    public string $period;

    /** @var array<string,mixed> */
    public array $data;

    public function __construct(string $accountId, string $period, array $data)
    {
        $this->accountId = $accountId;
        $this->period    = $period;
        $this->data      = $data;
    }

    public function build()
    {
        $period = (string)($this->data['period'] ?? $this->period);
        $label  = (string)($this->data['period_label'] ?? '');

        // subject override (si viene desde billing_email_logs.subject)
        $subjectOverride = (string)($this->data['subject_override'] ?? '');
        $subject = $subjectOverride !== ''
            ? $subjectOverride
            : ('Pactopia360 · Estado de cuenta '.$period.($label ? ' · '.$label : ''));

        $replyAddr = (string)($this->data['MAIL_REPLY_TO_ADDRESS'] ?? ($this->data['reply_to_address'] ?? ''));
        $replyName = (string)($this->data['MAIL_REPLY_TO_NAME'] ?? ($this->data['reply_to_name'] ?? ''));

        $view = (string)($this->data['template'] ?? 'admin.mail.statement');

        $m = $this->subject($subject)
            ->view($view)
            ->with($this->data);

        if ($replyAddr !== '') {
            $m->replyTo($replyAddr, $replyName !== '' ? $replyName : null);
        }

        return $m;
    }
}
