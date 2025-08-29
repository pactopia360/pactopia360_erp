<?php

namespace App\Jobs\Admin;

use App\Models\Admin\Cuenta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class EnviarEstadoCuentaMensual implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $hoy = now()->startOfMonth();
        $cuentas = Cuenta::on('mysql_admin')->get();

        foreach ($cuentas as $c) {
            // Cargo por licencia PRO, FREE = 0
            $cargo = $c->licencia === 'pro'
                ? ($c->ciclo === 'mensual' ? 999 : 12*999) // ajustable con promociones
                : 0;

            DB::connection('mysql_admin')->table('estados_cuenta')->insert([
                'cuenta_id' => $c->id,
                'periodo'   => $hoy->toDateString(),
                'cargo'     => $cargo,
                'abono'     => 0,
                'saldo'     => 0, // lo recalculas en reporte
                'concepto'  => 'Cargo de licencia '.$c->licencia.' ('.$c->ciclo.')',
                'referencia'=> 'AUTO-'.now()->format('YmdHis'),
                'created_at'=> now(),
                'updated_at'=> now(),
            ]);

            // Aquí envías email con estado de cuenta (omito implementación del Mailable por brevedad)
        }
    }
}
