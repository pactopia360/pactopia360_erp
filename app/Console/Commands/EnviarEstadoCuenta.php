<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnviarEstadoCuenta extends Command
{
    protected $signature = 'p360:enviar-estado-cuenta';
    protected $description = 'COMANDO LEGACY DESHABILITADO. No usar para envios de estados de cuenta.';

    public function handle(): int
    {
        $this->newLine();
        $this->error('El comando p360:enviar-estado-cuenta esta DESHABILITADO.');
        $this->line('Motivo: es un flujo legacy que envia correos sin validar saldo real, pagos, sent_at ni el HUB moderno.');
        $this->line('Ademas depende de la vista legacy emails.estado_cuenta, que ya no existe.');
        $this->newLine();
        $this->warn('No se envio ningun correo.');
        $this->line('Usa unicamente los flujos modernos del modulo Billing/HUB.');
        $this->newLine();

        return self::FAILURE;
    }
}