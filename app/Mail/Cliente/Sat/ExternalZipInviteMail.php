<?php
// C:\wamp64\www\pactopia360_erp\app\Mail\Cliente\Sat\ExternalZipInviteMail.php

declare(strict_types=1);

namespace App\Mail\Cliente\Sat;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class ExternalZipInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $inviteUrl,
        public readonly ?string $reference = null,
        public readonly ?string $traceId   = null,
        public readonly ?string $expiresAt = null,
    ) {}

    public function build(): self
    {
        $subj = 'Invitación para subir ZIP de FIEL';

        if ($this->reference) {
            $subj .= ' · ' . $this->reference;
        }

        return $this
            ->subject($subj)
            ->view('cliente.sat.mail.external_zip_invite')
            ->with([
                'inviteUrl' => $this->inviteUrl,
                'reference' => $this->reference,
                'traceId'   => $this->traceId,
                'expiresAt' => $this->expiresAt,
                'appName'   => (string) (config('app.name') ?: 'Pactopia360'),
                'appUrl'    => (string) (config('app.url') ?: ''),
            ]);
    }
}
