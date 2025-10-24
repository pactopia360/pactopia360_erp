<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente\CuentaCliente;
use Carbon\Carbon;

class ResetDailyMassInvoices extends Command
{
    protected $signature = 'p360:reset-mass-invoices';
    protected $description = 'Resetea los contadores diarios de facturación masiva cuando corresponde';

    public function handle(): int
    {
        $now = Carbon::now();
        $rows = CuentaCliente::on('mysql_clientes')
            ->whereNotNull('mass_invoices_reset_at')
            ->where('mass_invoices_reset_at', '<=', $now)
            ->get();

        foreach ($rows as $c) {
            $c->mass_invoices_used_today = 0;
            $c->mass_invoices_reset_at = $now->copy()->startOfDay()->addDay();
            $c->save();
        }

        $this->info('Reset diario de facturación masiva completado.');
        return self::SUCCESS;
    }
}
