<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnviarEstadosCuenta extends Command
{
    protected $signature = 'p360:estado-cuenta';
    protected $description = 'Envía estado de cuenta mensual (día 1)';

    public function handle()
    {
        $conn='mysql_admin';
        $cuentas = DB::connection($conn)->table('accounts')->select('id','email')->get();
        foreach ($cuentas as $c) {
            // TODO: Mail real con Mailgun
            Log::channel('home')->info('[estado_cuenta.enviado]', ['account_id'=>$c->id, 'email'=>$c->email]);
        }
        $this->info('OK estados enviados');
    }
}
