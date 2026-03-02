<?php

namespace App\Services\Admin\Billing;

use App\Models\Admin\Billing\BillingStatement;
use App\Models\Admin\Billing\BillingStatementEmail;
use App\Models\Admin\Billing\BillingStatementEvent;
use App\Models\Admin\Billing\BillingStatementItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

        // ✅ Compat proyecto: p360.conn.clientes (pero dejamos fallback)
        $connClients = (string) (config('p360.conn.clientes') ?: (config('p360.conn.clients') ?: 'mysql_clientes'));

        // Period validation
        if (!preg_match('/^\d{4}\-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('Invalid period format. Expected YYYY-MM');
        }

        return DB::connection('mysql_admin')->transaction(function () use ($connClients, $accountId, $period, $opts) {

            // ===== 1) Leer cuenta cliente (mysql_clientes)
            // $accountId aquí normalmente es UUID (cuentas_cliente.id).
            // Canon: billing_statements.account_id debe ser admin_account_id (numérico string) cuando exista.
            $acc = DB::connection($connClients)->table('cuentas_cliente')->where('id', $accountId)->first();
            if (!$acc) {
                throw new \RuntimeException("Account not found in $connClients.cuentas_cliente: $accountId");
            }

            $adminAccountId = (int) ($acc->admin_account_id ?? 0);

            // account_id canónico para billing_statements (evita UUID huérfano)
            $canonicalAccountId = $adminAccountId > 0 ? (string) $adminAccountId : (string) $accountId;

            // ===== 1.1) Meta (clientes + admin)
            // IMPORTANTE: "PERSONALIZADO" (billing.amount_mxn / billing.override.*) vive en Admin Accounts meta,
            // no en cuentas_cliente. Si solo leemos cuentas_cliente, el statement sale 0 aunque la UI muestre $96.
            $metaCli = $this->decodeMeta($acc->meta ?? null);
            $metaAdm = [];
            $admAcc  = null;

            if ($adminAccountId > 0) {
                $admConn  = 'mysql_admin';
                $admTable = 'accounts'; // default

                if (Schema::connection($admConn)->hasTable($admTable)) {
                    $admAcc = DB::connection($admConn)->table($admTable)
                        ->where('id', $adminAccountId)
                        ->first();

                    if ($admAcc) {
                        // Detectar columna meta en accounts (varía por proyecto)
                        $metaCols = ['meta', 'billing_meta', 'settings', 'data', 'payload'];
                        $metaCol = null;
                        foreach ($metaCols as $c) {
                            if (Schema::connection($admConn)->hasColumn($admTable, $c)) {
                                $metaCol = $c;
                                break;
                            }
                        }
                        if ($metaCol) {
                            $metaAdm = $this->decodeMeta($admAcc->{$metaCol} ?? null);
                        }
                    }
                }
            }

            // Merge: admin meta gana (porque ahí vive billing.override / amount_mxn)
            $meta = array_replace_recursive($metaCli, $metaAdm);

            // ===== 1.2) Licencia canónica (alineada al AccountsController)
            // billing.price_key, billing.billing_cycle, billing.amount_mxn, billing.override.{cycle}.amount_mxn
            $planKey = (string)(
                data_get($meta, 'billing.plan')
                ?? data_get($meta, 'license.plan')
                ?? data_get($meta, 'plan')
                ?? ($acc->plan ?? '')
                ?? ''
            );

            $cycleKey = (string)(
                data_get($meta, 'billing.billing_cycle')
                ?? data_get($meta, 'license.cycle')
                ?? data_get($meta, 'license.billing_cycle')
                ?? data_get($meta, 'billing_cycle')
                ?? ($acc->billing_cycle ?? '')
                ?? ''
            );

            $priceKey = (string)(
                data_get($meta, 'billing.price_key')
                ?? data_get($meta, 'license.pk')
                ?? data_get($meta, 'license.price_key')
                ?? data_get($meta, 'license.price_id')
                ?? ''
            );

            // base amount (MXN) - en Admin se usa amount_mxn
            $baseAmt = (float)(
                data_get($meta, 'billing.amount_mxn')
                ?? data_get($meta, 'license.amount_mxn')
                ?? data_get($meta, 'license.base_amount')
                ?? data_get($meta, 'license.amount')
                ?? 0
            );

            // override por ciclo (monthly/yearly) + legacy
            $cycleNorm = strtolower($cycleKey) ?: 'monthly';
            $overrideByCycle = data_get($meta, "billing.override.$cycleNorm.amount_mxn");
            $legacyOverride  = data_get($meta, 'billing.override.amount_mxn') ?? data_get($meta, 'billing.override_amount_mxn');

            $overrideAmt = is_numeric($overrideByCycle)
                ? (float) $overrideByCycle
                : (is_numeric($legacyOverride) ? (float) $legacyOverride : null);

            $override = $overrideAmt !== null
                ? ['amount_mxn' => $overrideAmt, 'cycle' => $cycleNorm]
                : (data_get($meta, 'license.override') ?? null);

            // Regla de "billable":
            // - si hay override o baseAmt > 0 => NO es FREE aunque cuentas_cliente diga FREE
            $isBillable = ($overrideAmt !== null && $overrideAmt > 0.00001) || ($baseAmt > 0.00001);

            // Compat: is_pro lo usamos como "tiene cargo de licencia"
            $isPro = $isBillable || ($priceKey !== '') || in_array(strtolower($planKey), ['pro', 'premium'], true);

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
            // IMPORTANT: billing_statements.account_id = ID canónico (admin_account_id) cuando exista
            $st = BillingStatement::query()
                ->where('account_id', $canonicalAccountId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (!$st) {
                $st = new BillingStatement([
                    'account_id' => $canonicalAccountId,
                    'period'     => $period,
                ]);
            } else {
                // Si por alguna razón existiera un row con account_id distinto, lo alineamos.
                if ((string) $st->account_id !== (string) $canonicalAccountId) {
                    $st->account_id = (string) $canonicalAccountId;
                }
            }

            // Si está locked y no es rebuild explícito, no tocar items
            $locked = (bool)($st->is_locked ?? false);

            // ===== 3) Snapshot (para PDF y consistencia)
            $snap = [
                'account' => [
                    // UUID de clientes (cuentas_cliente.id)
                    'client_uuid'      => (string) $acc->id,
                    // ID numérico admin (accounts.id) si existe
                    'admin_account_id' => $adminAccountId > 0 ? $adminAccountId : null,
                    // ID canónico persistido en billing_statements.account_id
                    'canonical_id'     => (string) $canonicalAccountId,

                    'razon_social'     => (string)($acc->razon_social ?? ''),
                    'nombre_comercial' => (string)($acc->nombre_comercial ?? ''),
                    'rfc'              => (string)($acc->rfc ?? ''),
                    'email'            => (string)($acc->email ?? ''),
                    'is_blocked'       => (int)($acc->is_blocked ?? 0),
                ],
                'license' => [
                    'is_pro'      => $isPro,
                    'plan'        => $planKey,
                    'cycle'       => $cycleKey,
                    'price_key'   => $priceKey,
                    'base_amount' => $baseAmt,
                    'override'    => $override,
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

            // ===== 5) Items
            if (!$locked || $opts['force_rebuild_license_line']) {

                // 5.1 Asegurar línea de licencia si hay cargo
                if ($isPro) {
                    $this->upsertLicenseLine(
                        $st->id,
                        $period,
                        $planKey,
                        $cycleKey,
                        $priceKey,
                        $baseAmt,
                        $override,
                        (bool)$opts['force_rebuild_license_line']
                    );
                } else {
                    // FREE => elimina cualquier línea license “fantasma”
                    BillingStatementItem::query()
                        ->where('statement_id', $st->id)
                        ->where('type', 'license')
                        ->delete();
                }

                // 5.2 aquí integrarías compras/consumos/ajustes
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
                    'meta'         => ['period' => $period],
                ]);
            }

            BillingStatementEvent::create([
                'statement_id' => $st->id,
                'event'        => 'synced',
                'actor'        => (string)$opts['actor'],
                'notes'        => $opts['notes'],
                'meta'         => ['period' => $period],
            ]);

            Log::info('StatementSyncService.synced', [
                'account_id_input'     => (string) $accountId,
                'account_client_uuid'  => (string) ($acc->id ?? ''),
                'admin_account_id'     => $adminAccountId > 0 ? $adminAccountId : null,
                'account_id_canonical' => (string) $canonicalAccountId,

                'period'       => $period,
                'statement_id' => $st->id,
                'is_pro'       => $isPro,
            ]);

            return $st;
        });
    }

    public function syncAllForPeriod(string $period, array $opts = []): int
    {
        // Compat: en el proyecto se usa p360.conn.clientes (pero dejamos fallback a p360.conn.clients)
        $connClients = (string) (config('p360.conn.clientes') ?: (config('p360.conn.clients') ?: 'mysql_clientes'));

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

        // compat: override puede venir como ['amount_mxn'=>X] o legacy ['amount'=>X]
        if (is_array($override) && isset($override['amount_mxn'])) {
            $finalAmt = (float)$override['amount_mxn'];
        } elseif (is_array($override) && isset($override['amount'])) {
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
                    'plan'        => $planKey,
                    'cycle'       => $cycleKey,
                    'pk'          => $priceKey,
                    'base_amount' => $baseAmt,
                    'override'    => $override,
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
                        'plan'        => $planKey,
                        'cycle'       => $cycleKey,
                        'pk'          => $priceKey,
                        'base_amount' => $baseAmt,
                        'override'    => $override,
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