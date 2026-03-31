<?php

namespace App\Console\Commands;

use App\Jobs\Admin\Billing\SendStatementEmailJob;
use App\Models\Admin\Billing\BillingStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class P360SendStatements extends Command
{
    protected $signature = 'p360:statements:send
                            {--period= : Periodo YYYY-MM (default: mes anterior)}
                            {--status=pending : pending|paid|credit|all}
                            {--actor=system : Actor para auditoría}
                            {--connection= : Queue connection (default: config queue.default)}
                            {--queue=emails : Queue name (default: emails)}
                            {--delay=0 : Delay en segundos por job (default: 0)}
                            {--chunk=200 : Tamaño de chunk (default: 200)}
                            {--force=0 : 1=reenviar aunque el statement ya tenga sent_at}';

    protected $description = 'Encola el envío de estados de cuenta por email (por periodo).';

    public function handle(): int
    {
        $period = (string) ($this->option('period') ?: now()->subMonth()->format('Y-m'));
        $status = (string) ($this->option('status') ?: 'pending');
        $actor  = (string) ($this->option('actor') ?: 'system');

        $connection = (string) ($this->option('connection') ?: config('queue.default'));
        $queue      = (string) ($this->option('queue') ?: 'emails');
        $delaySec   = (int) ($this->option('delay') ?: 0);
        $chunkSize  = max(1, (int) ($this->option('chunk') ?: 200));
        $force      = (string) ($this->option('force') ?: '0') === '1';

        if (!preg_match('/^\d{4}\-\d{2}$/', $period)) {
            $this->error("Invalid period format. Expected YYYY-MM. Got: {$period}");
            return self::FAILURE;
        }

        $baseQuery = BillingStatement::query()->where('period', $period);

        if ($status !== 'all') {
            $baseQuery->where('status', $status);
        }

        $totalMatching = (clone $baseQuery)->count();

        // Anti-duplicado por billing_statements.sent_at
        if (!$force && Schema::connection('mysql_admin')->hasColumn('billing_statements', 'sent_at')) {
            $baseQuery->whereNull('sent_at');
        }

        $toQueue = (clone $baseQuery)->count();
        $skipped = max(0, $totalMatching - $toQueue);

        $this->info(
            "Queueing {$toQueue} statements for period {$period} (status={$status}) on {$connection}:{$queue}"
            . ($force ? ' [FORCED]' : '')
        );

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} statements because they already have sent_at.");
        }

        if ($toQueue === 0) {
            $this->info('DONE. queued=0');
            return self::SUCCESS;
        }

        $queued = 0;

        $baseQuery
            ->orderBy('id')
            ->select(['id'])
            ->chunkById($chunkSize, function ($rows) use (
                $actor,
                $connection,
                $queue,
                $delaySec,
                &$queued,
                $period,
                $status,
                $force
            ) {
                foreach ($rows as $st) {
                    $pending = SendStatementEmailJob::dispatch((int) $st->id, $actor)
                        ->onConnection($connection)
                        ->onQueue($queue);

                    if ($delaySec > 0) {
                        $pending->delay(now()->addSeconds($delaySec));
                    }

                    $queued++;
                }

                Log::info('[STATEMENTS_SEND] chunk queued', [
                    'period'     => $period,
                    'status'     => $status,
                    'queued'     => $queued,
                    'connection' => $connection,
                    'queue'      => $queue,
                    'force'      => $force,
                ]);
            });

        $this->info("DONE. queued={$queued}");

        return self::SUCCESS;
    }
}