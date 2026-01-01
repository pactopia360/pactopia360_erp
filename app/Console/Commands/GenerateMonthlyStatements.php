<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class GenerateMonthlyStatements extends Command
{
    protected $signature = 'p360:billing:generate-statements {period? : Periodo Y-m, ej 2025-12} {--send-email : Enviar estado de cuenta por correo}';
    protected $description = 'Genera cargos mensuales (licencia) en estados_cuenta (mysql_admin) de forma idempotente.';

    private string $adm = 'mysql_admin';

    public function handle(): int
    {
        $period = (string)($this->argument('period') ?: now()->format('Y-m'));
        if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('Periodo inválido. Usa formato Y-m (ej: 2025-12).');
            return self::FAILURE;
        }

        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            $this->error('No existe tabla accounts en mysql_admin.');
            return self::FAILURE;
        }
        if (!Schema::connection($this->adm)->hasTable('estados_cuenta')) {
            $this->error('No existe tabla estados_cuenta en mysql_admin.');
            return self::FAILURE;
        }

        $sendEmail = (bool)$this->option('send-email');

        // Trae cuentas a facturar: ajusta filtros según tu lógica
        $accounts = DB::connection($this->adm)->table('accounts')
            ->select('id','email','rfc','razon_social','name','plan_actual','modo_cobro','is_blocked','estado_cuenta','meta')
            ->where(function($w){
                $w->whereNull('estado_cuenta')->orWhere('estado_cuenta','!=','cancelado');
            })
            ->orderBy('id')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($accounts as $acc) {
            $accountId = (int)$acc->id;
            $meta = $this->decodeMeta($acc->meta ?? null);

            // 1) Resolver precio asignado (prioridad: meta.billing.price_key)
            $priceKey = (string) data_get($meta, 'billing.price_key', '');
            $billingCycle = (string) data_get($meta, 'billing.billing_cycle', '');

            // fallback por columnas legacy
            if ($billingCycle === '') $billingCycle = (string)($acc->modo_cobro ?? '');

            $billingCycle = strtolower($billingCycle);
            if (!in_array($billingCycle, ['mensual','anual'], true)) $billingCycle = 'mensual';

            [$amountPesos, $concept] = $this->resolveAmountAndConcept($priceKey, $billingCycle, $acc);

            // Si no hay monto, no generamos cargo
            if ($amountPesos <= 0) {
                $skipped++;
                continue;
            }

            $ref = "system:license:{$accountId}:{$period}";

            $exists = DB::connection($this->adm)->table('estados_cuenta')
                ->where('account_id', $accountId)
                ->where('periodo', $period)
                ->where('ref', $ref)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            DB::connection($this->adm)->table('estados_cuenta')->insert([
                'account_id' => $accountId,
                'periodo'    => $period, // OJO: Y-m (string 7)
                'concepto'   => $concept,
                'detalle'    => 'Generado automáticamente por sistema (licencia).',
                'cargo'      => round($amountPesos, 2),
                'abono'      => 0.00,
                'saldo'      => null,
                'source'     => 'system',
                'ref'        => $ref,
                'meta'       => json_encode([
                    'type' => 'license_charge',
                    'price_key' => $priceKey ?: null,
                    'billing_cycle' => $billingCycle,
                    'generated_at' => now()->toISOString(),
                ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $created++;

            Log::info('Monthly statement charge created', [
                'account_id' => $accountId,
                'period' => $period,
                'amount' => $amountPesos,
                'price_key' => $priceKey ?: null,
            ]);

            // Envío por correo (si lo quieres dentro del mismo command)
            if ($sendEmail) {
                // Aquí NO mandamos correo directo para no acoplar.
                // La siguiente iteración: invocar tu BillingStatementsController->email(...) vía servicio o Job.
            }
        }

        $this->info("OK. Period={$period}. Created={$created}. Skipped={$skipped}.");
        return self::SUCCESS;
    }

    private function resolveAmountAndConcept(string $priceKey, string $billingCycle, object $acc): array
    {
        $amount = 0.0;
        $concept = 'Licencia Pactopia360';

        // Si existe stripe_price_list, usamos display_amount como monto “humano”
        if ($priceKey !== '' && Schema::connection($this->adm)->hasTable('stripe_price_list')) {
            $row = DB::connection($this->adm)->table('stripe_price_list')
                ->select('display_amount','currency','name','plan','billing_cycle','price_key')
                ->where('is_active', 1)
                ->whereRaw('LOWER(price_key)=?', [strtolower($priceKey)])
                ->orderByDesc('id')
                ->first();

            if ($row && is_numeric($row->display_amount)) {
                $amount = (float)$row->display_amount;
                $concept = trim((string)($row->name ?? '')) !== ''
                    ? (string)$row->name
                    : ('Licencia ' . strtoupper((string)($row->plan ?? '')) . ' ' . (string)($row->billing_cycle ?? $billingCycle));
                return [$amount, $concept];
            }
        }

        // Fallback mínimo: si no hay catalogo o no hay price_key, decide por plan_actual/modo_cobro (ajústalo a tu negocio)
        $plan = strtoupper((string)($acc->plan_actual ?? ''));
        if ($plan === '') $plan = strtoupper((string)($acc->plan ?? ''));

        // Pon aquí defaults si quieres (mejor dejar 0 para obligarte a asignar precio)
        $amount = 0.0;
        $concept = 'Licencia (sin precio asignado)';

        return [$amount, $concept];
    }

    private function decodeMeta($meta): array
    {
        try {
            if (is_array($meta)) return $meta;
            if (is_string($meta) && trim($meta) !== '') {
                return json_decode($meta, true) ?: [];
            }
        } catch (\Throwable $e) {}
        return [];
    }
}
