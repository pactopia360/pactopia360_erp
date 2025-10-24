<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTestMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /** número de reintentos */
    public int $tries = 3;

    /** timeout por job (segundos) */
    public int $timeout = 30;

    public function __construct(
        public string $to,
        public string $subject,
        public string $body
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $fromAddr = config('mail.from.address');
        $fromName = config('mail.from.name');

        Mail::raw($this->body, function ($m) use ($fromAddr, $fromName) {
            $m->from($fromAddr, $fromName)
              ->to($this->to)
              ->subject($this->subject);
        });
    }
}
