<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BloquearMorosos extends Command
{
    protected $signature = 'p360:bloquear-morosos';
    protected $description = 'Bloquea cuentas con saldo vencido (día 5)';

    public function handle()
    {
        $conn='mysql_admin';
        $desde = Carbon::now()->startOfMonth();
        $hasta = Carbon::now()->endOfMonth();

        // regla simple: si no hay pago "paid" del mes → bloquear
        $pagos = DB::connection($conn)->table('payments')
            ->select('account_id')
            ->whereBetween('created_at', [$desde, $hasta])
            ->where('status','paid');

        $affected = DB::connection($conn)->table('accounts')
            ->whereNotIn('id',$pagos)
            ->update(['blocked'=>1,'updated_at'=>now()]);

        Log::channel('home')->warning('[bloqueo.morosos]', ['affected'=>$affected]);
        $this->info("Bloqueados: {$affected}");
    }
}
