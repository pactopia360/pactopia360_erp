<?php

namespace App\Notifications\Cliente;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TempPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $rfc,
        public string $email,
        public string $tempPassword
    ) {}

    public function via($notifiable): array
    {
        return ['mail']; // puedes agregar SMS/WhatsApp si tienes gateway
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pactopia360 · Acceso temporal')
            ->greeting('Hola,')
            ->line('Generamos una contraseña temporal para tu acceso al portal de clientes.')
            ->line('**RFC**: '.$this->rfc)
            ->line('**Usuario (email)**: '.$this->email)
            ->line('**Contraseña temporal**: `'.$this->tempPassword.'`')
            ->line('Por seguridad, te pediremos cambiarla al entrar.')
            ->action('Entrar al portal', route('cliente.login'))
            ->salutation('— Equipo Pactopia360');
    }
}
