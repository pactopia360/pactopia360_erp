<?php

namespace App\Mail\Admin\Billing;

use App\Models\Admin\Billing\BillingStatement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public int $statementId) {}

    public function build()
    {
        $st = BillingStatement::query()->with(['items'])->findOrFail($this->statementId);

        $subj = 'Pactopia360 · Estado de cuenta '.$st->period.' · '.strtoupper((string)$st->status);

        return $this->subject($subj)
            ->view('emails.admin.billing.statement')
            ->with(['st' => $st]);
    }
}
