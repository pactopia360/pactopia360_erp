<?php

namespace App\Console\Commands;

use App\Jobs\Admin\Billing\SendStatementEmailJob;
use App\Models\Admin\Billing\BillingStatement;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class P360SendStatements extends Command
{
    protected $signature = 'p360:statements:send
                            {--period= : Periodo YYYY-MM (default: mes actual)}
                            {--status=all : pending|paid|credit|all}
                            {--actor=system : Actor para auditoría}
                            {--connection= : Queue connection (default: config queue.default)}
                            {--queue=emails : Queue name (default: emails)}
                            {--delay=0 : Delay en segundos por job (default: 0)}
                            {--chunk=200 : Tamaño de chunk (default: 200)}
                            {--force=0 : 1=reenviar aunque el statement ya tenga sent_at}';

    protected $description = 'Encola el envío de estados de cuenta por email (por periodo actual o indicado).';

    public function handle(): int
    {
        $period = (string) ($this->option('period') ?: now()->format('Y-m'));
        $status = strtolower((string) ($this->option('status') ?: 'all'));
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

        if (!in_array($status, ['pending', 'paid', 'credit', 'all'], true)) {
            $this->error("Invalid status. Expected pending|paid|credit|all. Got: {$status}");
            return self::FAILURE;
        }

        $baseQuery = BillingStatement::query()->where('period', $period);

        if ($status !== 'all') {
            $baseQuery->where('status', $status);
        }

        $totalMatching = (clone $baseQuery)->count();

        // Anti-duplicado por billing_statements.sent_at
        $sentAtSkipped = 0;
        if (!$force && Schema::connection('mysql_admin')->hasColumn('billing_statements', 'sent_at')) {
            $beforeSentFilter = (clone $baseQuery)->count();
            $baseQuery->whereNull('sent_at');
            $afterSentFilter = (clone $baseQuery)->count();
            $sentAtSkipped = max(0, $beforeSentFilter - $afterSentFilter);
        }

        // Guard previo de integridad:
        // 1) snapshot debe existir y no ser null/vacío
        // 2) no encolar statements completamente vacíos (cargo=0, abono=0, saldo=0)
        $beforeIntegrityFilter = (clone $baseQuery)->count();
        $this->applyIntegrityGuards($baseQuery);
        $afterIntegrityFilter = (clone $baseQuery)->count();
        $integritySkipped = max(0, $beforeIntegrityFilter - $afterIntegrityFilter);

        $toQueue = $afterIntegrityFilter;
        $skipped = max(0, $totalMatching - $toQueue);

        $this->info(
            "Queueing {$toQueue} statements for period {$period} (status={$status}) on {$connection}:{$queue}"
            . ($force ? ' [FORCED]' : '')
        );

        if ($sentAtSkipped > 0) {
            $this->warn("Skipped {$sentAtSkipped} statements because they already have sent_at.");
        }

        if ($integritySkipped > 0) {
            $this->warn("Skipped {$integritySkipped} statements due to integrity guard (snapshot vacío/nulo o statement sin importes).");
        }

        if ($skipped > 0 && $sentAtSkipped === 0 && $integritySkipped === 0) {
            $this->warn("Skipped {$skipped} statements by current filters.");
        }

        if ($toQueue === 0) {
            Log::warning('[STATEMENTS_SEND] nothing queued after guards', [
                'period'            => $period,
                'status'            => $status,
                'connection'        => $connection,
                'queue'             => $queue,
                'force'             => $force,
                'total_matching'    => $totalMatching,
                'sent_at_skipped'   => $sentAtSkipped,
                'integrity_skipped' => $integritySkipped,
            ]);

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

    private function applyIntegrityGuards(Builder $query): void
    {
        $conn = 'mysql_admin';

        // snapshot debe existir y no ser null/vacío
        if (Schema::connection($conn)->hasColumn('billing_statements', 'snapshot')) {
            $query->whereNotNull('snapshot')
                ->where('snapshot', '<>', '')
                ->where('snapshot', '<>', 'null')
                ->where('snapshot', '<>', '[]')
                ->where('snapshot', '<>', '{}');
        }

        // no encolar statements totalmente vacíos
        $hasCargo = Schema::connection($conn)->hasColumn('billing_statements', 'total_cargo');
        $hasAbono = Schema::connection($conn)->hasColumn('billing_statements', 'total_abono');
        $hasSaldo = Schema::connection($conn)->hasColumn('billing_statements', 'saldo');

        if ($hasCargo && $hasAbono && $hasSaldo) {
            $query->where(function (Builder $q) {
                $q->where('total_cargo', '>', 0)
                  ->orWhere('total_abono', '>', 0)
                  ->orWhere('saldo', '>', 0);
            });
        }
    }
}