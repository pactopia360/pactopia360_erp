<?php

namespace App\Jobs\Admin;

use App\Models\Admin\Cuenta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class BloquearCuentasMorosas implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $dia5 = now()->startOfMonth()->addDays(4); // 1..5
        if (now()->lt($dia5)) return;

        $cuentas = Cuenta::on('mysql_admin')->where('bloqueado',false)->get();

        foreach ($cuentas as $c) {
            $tieneDeuda = DB::connection('mysql_admin')
                ->table('estados_cuenta')
                ->where('cuenta_id',$c->id)
                ->where('periodo','>=', now()->startOfMonth()->toDateString())
                ->sum(DB::raw('cargo - abono')) > 0;

            if ($tieneDeuda) {
                $c->update([
                    'bloqueado' => true,
                    'bloqueado_desde' => now(),
                    'bloqueo_motivo' => 'Saldo pendiente de pago',
                ]);
            }
        }
    }
}
