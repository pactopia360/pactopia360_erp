<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EnviarEstadoCuenta extends Command
{
    protected $signature = 'p360:enviar-estado-cuenta';
    protected $description = 'Envía el estado de cuenta a clientes (mensual)';

    public function handle(): int
    {
        $clientes = DB::connection('mysql_admin')->table('clientes')->get();
        foreach ($clientes as $c) {
            Mail::send('emails.estado_cuenta', ['cliente'=>$c], function($m) use ($c){
                $m->to($c->email)->subject('Estado de cuenta - Pactopia360');
            });
        }
        $this->info('Estados de cuenta enviados.');
        return self::SUCCESS;
    }
}
