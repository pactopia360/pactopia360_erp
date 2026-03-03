<?php

namespace App\Console\Commands;

use App\Jobs\Admin\Billing\SendStatementEmailJob;
use App\Models\Admin\Billing\BillingStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class P360SendStatements extends Command
{
    protected $signature = 'p360:statements:send
                            {--period= : Periodo YYYY-MM (default: mes anterior)}
                            {--status=pending : pending|paid|credit|all}
                            {--actor=system : Actor para auditoría}
                            {--connection= : Queue connection (default: config queue.default)}
                            {--queue=emails : Queue name (default: emails)}
                            {--delay=0 : Delay en segundos por job (default: 0)}
                            {--chunk=200 : Tamaño de chunk (default: 200)}';

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

        if (!preg_match('/^\d{4}\-\d{2}$/', $period)) {
            $this->error("Invalid period format. Expected YYYY-MM. Got: {$period}");
            return self::FAILURE;
        }

        $q = BillingStatement::query()->where('period', $period);
        if ($status !== 'all') {
            $q->where('status', $status);
        }

        $total = (int) $q->count();

        $this->info("Queueing {$total} statements for period {$period} (status={$status}) on {$connection}:{$queue}");

        $queued = 0;

        $q->orderBy('id')->select(['id'])->chunkById($chunkSize, function ($rows) use (
            $actor, $connection, $queue, $delaySec, &$queued, $period, $status
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
                'period' => $period,
                'status' => $status,
                'queued' => $queued,
                'connection' => $connection,
                'queue' => $queue,
            ]);
        });

        $this->info("DONE. queued={$queued}");

        return self::SUCCESS;
    }
}