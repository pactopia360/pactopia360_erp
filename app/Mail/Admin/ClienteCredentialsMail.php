<?php

declare(strict_types=1);

namespace App\Mail\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ClienteCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string,mixed> */
    public array $payload;

    /**
     * @param array<string,mixed> $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function envelope(): Envelope
    {
        $brand = (string) data_get($this->payload, 'brand.name', 'Pactopia360');
        $rs    = (string) data_get($this->payload, 'account.razon_social', 'Cliente');

        return new Envelope(
            subject: "Credenciales de acceso · {$brand} · {$rs}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.cliente_credentials',
            with: [
                'p' => $this->payload,
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
