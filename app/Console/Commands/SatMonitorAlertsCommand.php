<?php

namespace App\Console\Commands;

use App\Models\Cliente\SatDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Monitor base: aquí puedes conectar validación de cancelaciones y listas negras
 * (p. ej. usando verificaciones de UUIDs contra el SAT o tus propios jobs).
 * Por ahora, deja un stub que recorra últimos "done" y registre un log.
 */
class SatMonitorAlertsCommand extends Command
{
    protected $signature = 'sat:monitor-alerts {--limit=50}';
    protected $description = 'SAT: monitorea cancelaciones / listas negras y genera alertas';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $this->info("SAT monitor-alerts: escaneando últimos {$limit} paquetes...");

        $done = SatDownload::query()
            ->where('status', 'done')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($done as $d) {
            // Aquí podrías disparar validaciones sobre los XML extraídos.
            Log::info('[sat:monitor-alerts] Escaneado paquete', ['id' => $d->id, 'rfc' => $d->rfc, 'tipo' => $d->tipo]);
        }

        $this->info('SAT monitor-alerts: terminado.');
        return self::SUCCESS;
    }
}
