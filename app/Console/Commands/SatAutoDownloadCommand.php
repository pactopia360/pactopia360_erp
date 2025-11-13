<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SatAutoDownloadJob;

class SatAutoDownloadCommand extends Command
{
    /**
     * php artisan sat:auto-download --limit=50
     */
    protected $signature = 'sat:auto-download {--limit=50 : Límite de paquetes por RFC en esta corrida}';

    protected $description = 'Genera solicitudes del día y descarga paquetes listos (auto) para credenciales SAT validadas de cuentas PRO.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $limit = $limit > 0 ? $limit : 50;

        dispatch(new SatAutoDownloadJob($limit))->onQueue('sat');

        $this->info(sprintf(
            'SatAutoDownloadJob despachado en cola "sat" con perRfcLimit=%d',
            $limit
        ));

        return self::SUCCESS;
    }
}
