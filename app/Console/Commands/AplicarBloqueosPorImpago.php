<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\MirrorService;

class AplicarBloqueosPorImpago extends Command
{
    protected $signature = 'p360:aplicar-bloqueos';
    protected $description = 'Bloquea cuentas con saldo pendiente después del día 5';

    public function handle(): int
    {
        // Lógica simple: si no hay pago registrado del mes vigente → bloquear
        $inicio = now()->startOfMonth();
        $clientes = DB::connection('mysql_admin')->table('clientes')->select('id','codigo_usuario','estatus')->get();

        foreach ($clientes as $c) {
            $pagado = DB::connection('mysql_admin')->table('pagos')
                ->where('cliente_id',$c->id)
                ->where('status','paid')
                ->whereBetween('fecha',[$inicio, now()])
                ->exists();

            if (!$pagado) {
                if ($c->estatus !== 'bloqueado') {
                    MirrorService::updateAccountStatus($c->codigo_usuario, 'bloqueado',  ($c->estatus==='pro'?50:1), ($c->estatus==='pro'?0:20));
                }
            }
        }
        $this->info('Bloqueos aplicados donde corresponde.');
        return self::SUCCESS;
    }
}
