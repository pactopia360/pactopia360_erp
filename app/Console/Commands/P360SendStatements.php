<?php

namespace App\Console\Commands;

use App\Jobs\Admin\Billing\SendStatementEmailJob;
use App\Models\Admin\Billing\BillingStatement;
use Illuminate\Console\Command;

class P360SendStatements extends Command
{
    protected $signature = 'p360:statements:send
                            {--period= : Periodo YYYY-MM (default: mes anterior)}
                            {--status=pending : pending|paid|credit|all}
                            {--actor=system : Actor para auditoría}';

    protected $description = 'Envía estados de cuenta por email el 1ro de cada mes (o por periodo).';

    public function handle(): int
    {
        $period = (string)($this->option('period') ?: now()->subMonth()->format('Y-m'));
        $status = (string)($this->option('status') ?: 'pending');
        $actor  = (string)($this->option('actor') ?: 'system');

        $q = BillingStatement::query()->where('period', $period);

        if ($status !== 'all') $q->where('status', $status);

        $rows = $q->orderBy('id')->get(['id']);

        $this->info("Queueing ".count($rows)." statements for period {$period} (status={$status})");

        foreach ($rows as $st) {
            SendStatementEmailJob::dispatch((int)$st->id, $actor);
        }

        return self::SUCCESS;
    }
}
