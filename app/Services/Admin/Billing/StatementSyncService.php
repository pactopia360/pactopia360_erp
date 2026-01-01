<?php

namespace App\Services\Admin\Billing;

use App\Models\Admin\Billing\BillingStatement;
use App\Models\Admin\Billing\BillingStatementEmail;
use App\Models\Admin\Billing\BillingStatementEvent;
use App\Models\Admin\Billing\BillingStatementItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatementSyncService
{
    public function syncForAccountAndPeriod(string $accountId, string $period, array $opts = []): BillingStatement
    {
        $opts = array_merge([
            'force_rebuild_license_line' => false,
            'lock_if_paid'               => true,
            'actor'                      => 'system',
            'notes'                      => null,
        ], $opts);

        $connClients = (string) (config('p360.conn.clients') ?: 'mysql_clientes');

        // Period validation
        if (!preg_match('/^\d{4}\-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('Invalid period format. Expected YYYY-MM');
        }

        return DB::connection('mysql_admin')->transaction(function () use ($connClients, $accountId, $period, $opts) {

            // ===== 1) Leer cuenta cliente (mysql_clientes)
            $acc = DB::connection($connClients)->table('cuentas_cliente')->where('id', $accountId)->first();
            if (!$acc) {
                throw new \RuntimeException("Account not found in $connClients.cuentas_cliente: $accountId");
            }

            $meta = $this->decodeMeta($acc->meta ?? null);

            // Detecta plan/licencia desde meta['license'] o meta['billing']
            $lic = [];
            if (isset($meta['license']) && is_array($meta['license'])) $lic = $meta['license'];
            if (!$lic && isset($meta['billing']) && is_array($meta['billing'])) $lic = $meta['billing'];

            $planKey   = (string)($lic['plan'] ?? $meta['plan'] ?? ($acc->plan ?? '') ?? '');
            $cycleKey  = (string)($lic['cycle'] ?? $lic['billing_cycle'] ?? $meta['billing_cycle'] ?? ($acc->billing_cycle ?? '') ?? '');
            $priceKey  = (string)($lic['pk'] ?? $lic['price_key'] ?? $lic['price_id'] ?? '');
            $baseAmt   = (float) ($lic['base_amount'] ?? $lic['amount'] ?? 0);
            $override  = $lic['override'] ?? null;

            $isPro = (strtolower($planKey) === 'pro' || strtolower($planKey) === 'premium' || $priceKey !== '');

            // Correos: por defecto el email principal de la cuenta
            $emails = [];
            $emailPrimary = trim((string)($acc->email ?? ''));
            if ($emailPrimary !== '') $emails[] = $emailPrimary;

            // extras desde meta
            $extraEmails = [];
            if (isset($meta['billing_emails']) && is_array($meta['billing_emails'])) $extraEmails = $meta['billing_emails'];
            if (isset($meta['emails_facturacion']) && is_array($meta['emails_facturacion'])) $extraEmails = array_merge($extraEmails, $meta['emails_facturacion']);

            foreach ($extraEmails as $em) {
                $em = trim((string)$em);
                if ($em !== '') $emails[] = $em;
            }

            $emails = array_values(array_unique(array_map('mb_strtolower', $emails)));

            // ===== 2) Upsert statement
            $st = BillingStatement::query()
                ->where('account_id', $accountId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (!$st) {
                $st = new BillingStatement([
                    'account_id' => $accountId,
                    'period'     => $period,
                ]);
            }

            // Si está locked y no es rebuild explícito, no tocar items
            $locked = (bool)($st->is_locked ?? false);

            // ===== 3) Snapshot (para PDF y consistencia)
            $snap = [
                'account' => [
                    'id'              => (string)$acc->id,
                    'razon_social'     => (string)($acc->razon_social ?? ''),
                    'nombre_comercial' => (string)($acc->nombre_comercial ?? ''),
                    'rfc'              => (string)($acc->rfc ?? ''),
                    'email'            => (string)($acc->email ?? ''),
                    'is_blocked'       => (int)($acc->is_blocked ?? 0),
                ],
                'license' => [
                    'is_pro'     => $isPro,
                    'plan'       => $planKey,
                    'cycle'      => $cycleKey,
                    'price_key'  => $priceKey,
                    'base_amount'=> $baseAmt,
                    'override'   => $override,
                ],
                'emails' => $emails,
                'generated_at' => now()->toISOString(),
            ];

            $st->snapshot = $snap;

            // due_date: default día 5 del mes siguiente al periodo (ajústalo a tu regla)
            $due = Carbon::createFromFormat('Y-m', $period)->startOfMonth()->addMonth()->day(5);
            $st->due_date = $st->due_date ?: $due->toDateString();

            $st->save();

            // ===== 4) Emails de envío (tabla aparte)
            // Se sincroniza aunque esté locked
            $this->syncEmails($st->id, $emails);

            // ===== 5) Items (aquí está el “PRIMERA LÍNEA”)
            if (!$locked || $opts['force_rebuild_license_line']) {

                // 5.1 Asegurar línea de licencia si PRO
                if ($isPro) {
                    $this->upsertLicenseLine($st->id, $period, $planKey, $cycleKey, $priceKey, $baseAmt, $override, $opts['force_rebuild_license_line']);
                } else {
                    // FREE => elimina cualquier línea license “fantasma”
                    BillingStatementItem::query()
                        ->where('statement_id', $st->id)
                        ->where('type', 'license')
                        ->delete();
                }

                // 5.2 Aquí es donde integrarías compras/consumos/ajustes:
                // - Si tienes tabla de compras en mysql_admin, insertas items type=purchase
                // - Si pagaron por Stripe, insertas items type=payment (negativos)
                // Por ahora: NO invento tablas. Solo dejo preparado el cálculo.
            }

            // ===== 6) Recalcular totales/saldo/estatus
            $this->recalcStatement($st->id);

            // ===== 7) Lock si pagado
            $st->refresh();
            if ($opts['lock_if_paid'] && $st->status === 'paid' && !$st->is_locked) {
                $st->is_locked = true;
                $st->paid_at = $st->paid_at ?: now();
                $st->save();

                BillingStatementEvent::create([
                    'statement_id' => $st->id,
                    'event'        => 'locked',
                    'actor'        => (string)$opts['actor'],
                    'notes'        => 'Auto-lock: status=paid',
                    'meta'         => ['period'=>$period],
                ]);
            }

            BillingStatementEvent::create([
                'statement_id' => $st->id,
                'event'        => 'synced',
                'actor'        => (string)$opts['actor'],
                'notes'        => $opts['notes'],
                'meta'         => ['period'=>$period],
            ]);

            Log::info('StatementSyncService.synced', [
                'account_id' => $accountId,
                'period'     => $period,
                'statement_id'=> $st->id,
                'is_pro'     => $isPro,
            ]);

            return $st;
        });
    }

    public function syncAllForPeriod(string $period, array $opts = []): int
    {
        $connClients = (string) (config('p360.conn.clients') ?: 'mysql_clientes');

        $ids = DB::connection($connClients)->table('cuentas_cliente')->pluck('id')->all();

        $count = 0;
        foreach ($ids as $id) {
            try {
                $this->syncForAccountAndPeriod((string)$id, $period, $opts);
                $count++;
            } catch (\Throwable $e) {
                Log::error('StatementSyncService.syncAllForPeriod.failed', [
                    'account_id' => (string)$id,
                    'period'     => $period,
                    'err'        => $e->getMessage(),
                ]);
            }
        }
        return $count;
    }

    private function decodeMeta($meta): array
    {
        if (is_array($meta)) return $meta;
        if (is_object($meta)) return (array)$meta;
        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
        }
        return [];
    }

    private function syncEmails(int $statementId, array $emails): void
    {
        BillingStatementEmail::query()->where('statement_id', $statementId)->delete();

        foreach ($emails as $i => $email) {
            BillingStatementEmail::create([
                'statement_id' => $statementId,
                'email'        => $email,
                'is_primary'   => $i === 0,
            ]);
        }
    }

    private function upsertLicenseLine(
        int $statementId,
        string $period,
        string $planKey,
        string $cycleKey,
        string $priceKey,
        float $baseAmt,
        $override,
        bool $force
    ): void
    {
        // “Mensualidad” siempre se registra como cargo positivo.
        // Si override trae amount, lo aplicamos.
        $finalAmt = $baseAmt;

        if (is_array($override) && isset($override['amount'])) {
            $finalAmt = (float)$override['amount'];
        } elseif (is_numeric($override)) {
            $finalAmt = (float)$override;
        }

        // Si no hay monto, no insertamos (evita estados “en cero” por datos incompletos)
        if ($finalAmt <= 0) return;

        $label = strtoupper($planKey ?: 'PRO');
        $cycle = $cycleKey ? strtoupper($cycleKey) : 'MENSUAL';

        $desc = "Mensualidad {$label} · {$cycle} ({$period})";

        $q = BillingStatementItem::query()
            ->where('statement_id', $statementId)
            ->where('type', 'license');

        $exists = $q->first();

        if (!$exists) {
            BillingStatementItem::create([
                'statement_id' => $statementId,
                'type'         => 'license',
                'code'         => 'LICENSE',
                'description'  => $desc,
                'qty'          => 1,
                'unit_price'   => $finalAmt,
                'amount'       => $finalAmt,
                'ref'          => $priceKey ?: null,
                'meta'         => [
                    'plan' => $planKey,
                    'cycle'=> $cycleKey,
                    'pk'   => $priceKey,
                    'base_amount' => $baseAmt,
                    'override' => $override,
                ],
            ]);
        } else {
            if ($force) {
                $exists->update([
                    'description' => $desc,
                    'qty'         => 1,
                    'unit_price'  => $finalAmt,
                    'amount'      => $finalAmt,
                    'ref'         => $priceKey ?: $exists->ref,
                    'meta'        => array_merge((array)($exists->meta ?? []), [
                        'plan' => $planKey,
                        'cycle'=> $cycleKey,
                        'pk'   => $priceKey,
                        'base_amount' => $baseAmt,
                        'override' => $override,
                    ]),
                ]);
            }
        }
    }

    private function recalcStatement(int $statementId): void
    {
        $st = BillingStatement::query()->where('id', $statementId)->lockForUpdate()->firstOrFail();

        $items = BillingStatementItem::query()
            ->where('statement_id', $statementId)
            ->get(['amount']);

        $cargo = 0.0;
        $abono = 0.0;

        foreach ($items as $it) {
            $a = (float)$it->amount;
            if ($a >= 0) $cargo += $a;
            else $abono += abs($a);
        }

        $saldo = $cargo - $abono;

        $status = 'pending';
        if ($saldo == 0.0) $status = 'paid';
        if ($saldo < 0.0)  $status = 'credit';

        $st->total_cargo = $cargo;
        $st->total_abono = $abono;
        $st->saldo       = $saldo;
        $st->status      = $status;

        $st->save();
    }
}
