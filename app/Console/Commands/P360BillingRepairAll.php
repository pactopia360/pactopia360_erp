<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Admin\Billing\AccountBillingStateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class P360BillingRepairAll extends Command
{
    protected $signature = 'p360:billing:repair-all
                            {--account_id= : Reparar solo una cuenta}
                            {--period= : Reparar solo un periodo YYYY-MM}
                            {--clear-overrides=0 : 1=elimina overrides pagado antes de reconstruir}
                            {--dry-run=0 : 1=solo simula, no guarda cambios}
                            {--chunk=200 : Tamaño de lote para cuentas}';

    protected $description = 'Reconstruye billing_statements desde payments y resincroniza accounts sin enviar correos.';

    public function handle(): int
    {
        $adm = (string) config('p360.conn.admin', 'mysql_admin');

        $accountId      = trim((string) ($this->option('account_id') ?? ''));
        $period         = trim((string) ($this->option('period') ?? ''));
        $clearOverrides = (string) ($this->option('clear-overrides') ?? '0') === '1';
        $dryRun         = (string) ($this->option('dry-run') ?? '0') === '1';
        $chunk          = max(1, (int) ($this->option('chunk') ?? 200));

        if ($period !== '' && !preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
            $this->error("Periodo inválido: {$period}");
            return self::FAILURE;
        }

        if (!Schema::connection($adm)->hasTable('billing_statements')) {
            $this->error('No existe billing_statements.');
            return self::FAILURE;
        }

        if (!Schema::connection($adm)->hasTable('payments')) {
            $this->error('No existe payments.');
            return self::FAILURE;
        }

        $payCols = Schema::connection($adm)->getColumnListing('payments');
        $payLc   = array_map('strtolower', $payCols);
        $hasPay  = static fn (string $c): bool => in_array(strtolower($c), $payLc, true);

        $amountExpr = null;
        if ($hasPay('amount_mxn')) {
            $amountExpr = 'COALESCE(amount_mxn,0)';
        } elseif ($hasPay('monto_mxn')) {
            $amountExpr = 'COALESCE(monto_mxn,0)';
        } elseif ($hasPay('amount_cents')) {
            $amountExpr = 'COALESCE(amount_cents,0)/100';
        } elseif ($hasPay('amount')) {
            $amountExpr = 'COALESCE(amount,0)/100';
        }

        if ($amountExpr === null) {
            $this->error('No encontré columna de monto compatible en payments.');
            return self::FAILURE;
        }

        $statusesPaid = [
            'paid', 'pagado', 'succeeded', 'success',
            'completed', 'complete', 'captured', 'authorized',
            'paid_ok', 'ok',
        ];

        if ($clearOverrides && Schema::connection($adm)->hasTable('billing_statement_status_overrides')) {
            $ovQ = DB::connection($adm)->table('billing_statement_status_overrides');

            if ($accountId !== '') {
                $ovQ->where('account_id', $accountId);
            }

            if ($period !== '') {
                $ovQ->where('period', $period);
            }

            $ovCount = (clone $ovQ)->count();

            if ($dryRun) {
                $this->warn("[DRY-RUN] Se eliminarían {$ovCount} overrides.");
            } else {
                $ovQ->delete();
                $this->warn("Overrides eliminados: {$ovCount}");
            }
        }

        $stQ = DB::connection($adm)->table('billing_statements')
            ->select(['id', 'account_id', 'period', 'total_cargo']);

        if ($accountId !== '') {
            $stQ->where('account_id', $accountId);
        }

        if ($period !== '') {
            $stQ->where('period', $period);
        }

        $total = (clone $stQ)->count();

        if ($total === 0) {
            $this->info('No hay statements para reparar con esos filtros.');
            return self::SUCCESS;
        }

        $this->info("Statements a revisar: {$total}");

        $updated = 0;
        $accountIdsTouched = [];

        $stQ->orderBy('id')->chunk($chunk, function ($rows) use (
            $adm,
            $amountExpr,
            $statusesPaid,
            $dryRun,
            &$updated,
            &$accountIdsTouched
        ) {
            foreach ($rows as $st) {
                $paidQ = DB::connection($adm)->table('payments')
                    ->where('account_id', (string) $st->account_id)
                    ->where(function ($w) use ($st) {
                        $w->where('period', (string) $st->period)
                          ->orWhere('period', 'like', (string) $st->period . '%');
                    })
                    ->whereIn(DB::raw('LOWER(status)'), $statusesPaid);

                $paid  = round((float) ($paidQ->selectRaw("SUM({$amountExpr}) as s")->value('s') ?? 0), 2);
                $cargo = round(max(0.0, (float) ($st->total_cargo ?? 0)), 2);
                $saldo = round(max(0.0, $cargo - $paid), 2);

                $status = 'pending';
                $paidAt = null;

                if ($cargo <= 0.00001 && $paid <= 0.00001) {
                    $status = 'void';
                } elseif ($saldo <= 0.00001 && $paid > 0.00001) {
                    $status = 'paid';
                    $paidAt = now();
                } elseif ($paid > 0.00001 && $saldo > 0.00001) {
                    $status = 'partial';
                }

                $accountIdsTouched[(string) $st->account_id] = true;

                if ($dryRun) {
                    $this->line("[DRY-RUN] account={$st->account_id} period={$st->period} cargo={$cargo} abono={$paid} saldo={$saldo} status={$status}");
                    continue;
                }

                DB::connection($adm)->table('billing_statements')
                    ->where('id', (int) $st->id)
                    ->update([
                        'total_abono' => $paid,
                        'saldo'       => $saldo,
                        'status'      => $status,
                        'paid_at'     => $paidAt,
                        'updated_at'  => now(),
                    ]);

                $updated++;
                $this->line("OK account={$st->account_id} period={$st->period} cargo={$cargo} abono={$paid} saldo={$saldo} status={$status}");
            }
        });

        $this->info($dryRun ? 'Simulación terminada.' : "Statements actualizados: {$updated}");

        $syncCount = 0;
        foreach (array_keys($accountIdsTouched) as $aid) {
            if ($dryRun) {
                $this->line("[DRY-RUN] sync account={$aid}");
                continue;
            }

            AccountBillingStateService::sync($aid, 'p360:billing:repair-all');
            $syncCount++;
        }

        $this->info($dryRun ? 'Simulación de sync terminada.' : "Cuentas resincronizadas: {$syncCount}");
        $this->info('DONE.');

        return self::SUCCESS;
    }
}