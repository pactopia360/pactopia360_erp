<?php

namespace App\Console\Commands;

use App\Services\Admin\Billing\StatementSyncService;
use Illuminate\Console\Command;

class P360SyncStatements extends Command
{
    protected $signature = 'p360:statements:sync
                            {--period= : Periodo YYYY-MM (default: mes actual)}
                            {--force-license : Recalcular línea de licencia aunque exista}
                            {--actor=system : Actor para auditoría}';

    protected $description = 'Sincroniza estados de cuenta por periodo (incluye mensualidad PRO como primera línea).';

    public function handle(StatementSyncService $svc): int
    {
        $period = (string)($this->option('period') ?: now()->format('Y-m'));

        $count = $svc->syncAllForPeriod($period, [
            'force_rebuild_license_line' => (bool)$this->option('force-license'),
            'actor' => (string)$this->option('actor'),
            'notes' => 'sync via artisan',
        ]);

        $this->info("OK: synced {$count} accounts for period {$period}");
        return self::SUCCESS;
    }
}
