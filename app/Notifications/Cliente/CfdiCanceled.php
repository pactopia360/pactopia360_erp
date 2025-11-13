<?php

namespace App\Notifications\Cliente;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CfdiCanceled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $uuid,
        public ?string $emisor   = null,
        public ?string $receptor = null,
        public ?string $total    = null,
        public ?string $fecha    = null,
        public ?string $rfc      = null,
        public ?string $razon    = null,
    ) {
        // (opcional) fuerza esta notificación a la cola "mail"
        $this->onQueue('mail');
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $m = (new MailMessage)
            ->subject('Alerta: CFDI cancelado '.$this->uuid)
            ->greeting('Se detectó una cancelación de CFDI')
            ->line('UUID: '.$this->uuid);

        if ($this->rfc)      $m->line('RFC: '.$this->rfc);
        if ($this->razon)    $m->line('Razón social: '.$this->razon);
        if ($this->emisor)   $m->line('Emisor: '.$this->emisor);
        if ($this->receptor) $m->line('Receptor: '.$this->receptor);
        if ($this->total)    $m->line('Total: '.$this->total);
        if ($this->fecha)    $m->line('Fecha: '.$this->fecha);

        return $m->line('Este aviso se generó automáticamente por Pactopia360.')
                 ->salutation('— Equipo Pactopia360');
    }
}
