<?php

namespace App\Jobs\Admin\Billing;

use App\Mail\Admin\Billing\StatementMail;
use App\Models\Admin\Billing\BillingStatement;
use App\Models\Admin\Billing\BillingStatementEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendStatementEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $statementId, public string $actor = 'system') {}

    public function handle(): void
    {
        $st = BillingStatement::query()
            ->with(['emails'])
            ->findOrFail($this->statementId);

        $emails = $st->emails->pluck('email')->filter()->values()->all();
        if (!$emails) return;

        Mail::to($emails)->send(new StatementMail($st->id));

        $st->sent_at = now();
        $st->save();

        BillingStatementEvent::create([
            'statement_id' => $st->id,
            'event'        => 'sent',
            'actor'        => $this->actor,
            'notes'        => 'Sent via queue',
            'meta'         => ['emails' => $emails],
        ]);
    }
}
