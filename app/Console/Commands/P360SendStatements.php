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
                            {--force=0 : 1=reenviar aunque el statement ya tenga sent_at}
                            {--reminder=0 : 1=modo recordatorio (solo aplica a pending y permite reenvio aunque ya tenga sent_at)}';

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
        $reminder   = (string) ($this->option('reminder') ?: '0') === '1';

        if (!preg_match('/^\d{4}\-\d{2}$/', $period)) {
            $this->error("Invalid period format. Expected YYYY-MM. Got: {$period}");
            return self::FAILURE;
        }

        if (!in_array($status, ['pending', 'paid', 'credit', 'all'], true)) {
            $this->error("Invalid status. Expected pending|paid|credit|all. Got: {$status}");
            return self::FAILURE;
        }

        if ($reminder && $status !== 'pending') {
            $this->error('Reminder mode only supports --status=pending.');
            return self::FAILURE;
        }

        $baseQuery = BillingStatement::query()->where('period', $period);

        $this->applyStatusFilter($baseQuery, $status);

        $totalMatching = (clone $baseQuery)->count();

        $skipSentAtGuard = $this->shouldSkipSentAtGuard($status, $force, $reminder);

        // Anti-duplicado por billing_statements.sent_at
        $sentAtSkipped = 0;
        if (
            !$skipSentAtGuard &&
            Schema::connection('mysql_admin')->hasColumn('billing_statements', 'sent_at')
        ) {
            $beforeSentFilter = (clone $baseQuery)->count();
            $baseQuery->whereNull('sent_at');
            $afterSentFilter = (clone $baseQuery)->count();
            $sentAtSkipped = max(0, $beforeSentFilter - $afterSentFilter);
        }

        // Guard previo de integridad
        $beforeIntegrityFilter = (clone $baseQuery)->count();
        $this->applyIntegrityGuards($baseQuery, $status, $reminder);
        $afterIntegrityFilter = (clone $baseQuery)->count();
        $integritySkipped = max(0, $beforeIntegrityFilter - $afterIntegrityFilter);

        $toQueue = $afterIntegrityFilter;
        $skipped = max(0, $totalMatching - $toQueue);

        $modeLabel = $reminder ? 'REMINDER' : ($force ? 'FORCED' : 'NORMAL');

        $this->info(
            "Queueing {$toQueue} statements for period {$period} (status={$status}, mode={$modeLabel}) on {$connection}:{$queue}"
        );

        if ($sentAtSkipped > 0) {
            $this->warn("Skipped {$sentAtSkipped} statements because they already have sent_at.");
        }

        if ($integritySkipped > 0) {
            $this->warn("Skipped {$integritySkipped} statements due to integrity guard.");
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
                'reminder'          => $reminder,
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
                $force,
                $reminder
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
                    'reminder'   => $reminder,
                ]);
            });

        $this->info("DONE. queued={$queued}");

        return self::SUCCESS;
    }

    private function shouldSkipSentAtGuard(string $status, bool $force, bool $reminder): bool
    {
        if ($force) {
            return true;
        }

        if ($reminder && $status === 'pending') {
            return true;
        }

        return false;
    }

    private function applyIntegrityGuards(Builder $query, string $status, bool $reminder): void
    {
        $conn = 'mysql_admin';

        if (Schema::connection($conn)->hasColumn('billing_statements', 'snapshot')) {
            $query->whereNotNull('snapshot')
                ->where('snapshot', '<>', '')
                ->where('snapshot', '<>', 'null')
                ->where('snapshot', '<>', '[]')
                ->where('snapshot', '<>', '{}');
        }

        $hasCargo = Schema::connection($conn)->hasColumn('billing_statements', 'total_cargo');
        $hasAbono = Schema::connection($conn)->hasColumn('billing_statements', 'total_abono');
        $hasSaldo = Schema::connection($conn)->hasColumn('billing_statements', 'saldo');

        // Regla principal del negocio:
        // si se pide pending, solo deben salir estados con saldo real > 0.
        // Esto evita enviar cuentas ya cubiertas por prepago, pago anual
        // o cualquier statement que ya no tenga adeudo aunque el status esté desfasado.
        if ($status === 'pending' && $hasSaldo) {
            $query->where('saldo', '>', 0);
            return;
        }

        // En otros estatus, no permitir statements totalmente vacíos.
        if ($hasCargo && $hasAbono && $hasSaldo) {
            $query->where(function (Builder $q) {
                $q->where('total_cargo', '>', 0)
                  ->orWhere('total_abono', '>', 0)
                  ->orWhere('saldo', '>', 0);
            });

            return;
        }

        if ($hasSaldo) {
            $query->where('saldo', '>', 0);
        }
    }

        private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === 'all') {
            return;
        }

        $values = $this->resolveStatusValues($status);

        if (empty($values)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function (Builder $q) use ($values) {
            foreach ($values as $index => $value) {
                if ($index === 0) {
                    $q->where('status', $value);
                    continue;
                }

                $q->orWhere('status', $value);
            }
        });
    }

    /**
     * @return array<int,string>
     */
    private function resolveStatusValues(string $status): array
    {
        return match ($status) {
            'pending' => [
                'pending',
                'pendiente',
                'overdue',
                'vencido',
                'late',
                'partial',
                'parcial',
            ],
            'paid' => [
                'paid',
                'pagado',
            ],
            'credit' => [
                'credit',
                'credito',
                'crédito',
                'sin_mov',
                'sin movimiento',
            ],
            default => [],
        };
    }
}