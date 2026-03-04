<?php

declare(strict_types=1);

namespace App\Services\Admin\Billing;

use App\Models\Admin\Billing\BillingStatement;
use App\Models\Admin\Billing\BillingStatementEmail;
use App\Models\Admin\Billing\BillingStatementEvent;
use App\Models\Admin\Billing\BillingStatementItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class StatementSyncService
{
    // =========================
    // PUBLIC API
    // =========================

    public function syncForAccountAndPeriod(string $accountId, string $period, array $opts = []): BillingStatement
    {
        $opts = array_merge([
            'force_rebuild_license_line' => false,
            'lock_if_paid'               => true,
            'actor'                      => 'system',
            'notes'                      => null,

            // ✅ NUEVO: repara automáticamente "paid+locked con saldo/cargo 0"
            // cuando el meta de HUB dice que sí hay que cobrar.
            'repair_locked_zero'         => true,

            // ✅ NUEVO: por defecto NO facturamos ANUAL fuera de su mes de renovación
            // (si necesitas forzar, pasa ['allow_yearly_offcycle'=>true])
            'allow_yearly_offcycle'      => false,
        ], $opts);

        // ✅ Compat proyecto: p360.conn.clientes (pero dejamos fallback)
        $connClients = (string) (config('p360.conn.clientes') ?: (config('p360.conn.clients') ?: 'mysql_clientes'));
        $connAdmin   = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (!preg_match('/^\d{4}\-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('Invalid period format. Expected YYYY-MM');
        }

        return DB::connection($connAdmin)->transaction(function () use ($connClients, $connAdmin, $accountId, $period, $opts) {

            // ===== 1) Cuenta cliente
            $acc = DB::connection($connClients)->table('cuentas_cliente')->where('id', $accountId)->first();
            
            // ==============================
            // RULE: no generar estados antes
            // de que exista la cuenta
            // ==============================
            $accountCreatedAt = $acc->created_at ?? null;

            if ($accountCreatedAt) {

                try {

                    $accountStart = \Carbon\Carbon::parse($accountCreatedAt)->startOfMonth();
                    $periodStart  = \Carbon\Carbon::createFromFormat('Y-m', $period)->startOfMonth();

                    if ($periodStart->lt($accountStart)) {

                        throw new \RuntimeException(
                            "SKIP_BEFORE_ACCOUNT_START: account={$accountId} period={$period} created_at=".$accountStart->format('Y-m')
                        );

                    }

                } catch (\Throwable $e) {
                    // ignora parse errors
                }

            }

            if (!$acc) {
                throw new \RuntimeException("Account not found in {$connClients}.cuentas_cliente: {$accountId}");
            }
            $adminAccountId = (int) ($acc->admin_account_id ?? 0);
            $canonicalAccountId = $adminAccountId > 0 ? (string) $adminAccountId : (string) $accountId;

            // ===== 1.1) Meta (clientes + admin/accounts)
            $metaCli = $this->decodeMeta($acc->meta ?? null);

            $accountsTable  = Schema::connection($connAdmin)->hasTable('accounts') ? 'accounts' : null;
            $accountsMetaCol = $accountsTable ? $this->pickFirstMetaCol($connAdmin, $accountsTable) : null;

            $metaAdm = [];
            $admAccCreatedAt = null;

            if ($adminAccountId > 0 && $accountsTable && $accountsMetaCol) {
                $admAcc = DB::connection($connAdmin)->table($accountsTable)
                    ->where('id', $adminAccountId)
                    ->first(['id', $accountsMetaCol, 'created_at']);

                if ($admAcc) {
                    $metaAdm = $this->decodeMeta($admAcc->{$accountsMetaCol} ?? null);
                    $admAccCreatedAt = $admAcc->created_at ?? null;
                }
            }

            // Admin meta gana (override/amount_mxn viven ahí)
            $meta = array_replace_recursive($metaCli, $metaAdm);

            // ===== 1.2) Perfil licencia canónico
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

            $cycleNorm = $this->normalizeCycle($cycleKey);

            // =========================================================
            // ✅ RULE: NO backfill (no crear estados de cuenta antes de que exista la cuenta)
            // - Por defecto bloquea periodos anteriores al alta.
            // - Para migraciones/ajustes: pasa ['allow_backfill' => true].
            // Fuente de "inicio":
            //   1) subscriptions.started_at (si existe) para admin_account_id
            //   2) admin.accounts.created_at
            //   3) clientes.cuentas_cliente.created_at
            // =========================================================
            $allowBackfill = (bool)($opts['allow_backfill'] ?? false);

            if (!$allowBackfill) {
                $periodStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth();

                $start = null;

                // 1) subscriptions.started_at
                if ($adminAccountId > 0 && Schema::connection($connAdmin)->hasTable('subscriptions')) {
                    $sub = DB::connection($connAdmin)->table('subscriptions')
                        ->where('account_id', $adminAccountId)
                        ->orderByDesc('id')
                        ->first(['id', 'started_at']);

                    if ($sub && !empty($sub->started_at)) {
                        $start = $sub->started_at;
                    }
                }

                // 2) admin.accounts.created_at
                if (!$start && !empty($admAccCreatedAt)) {
                    $start = $admAccCreatedAt;
                }

                // 3) clientes.cuentas_cliente.created_at
                if (!$start && !empty($acc->created_at)) {
                    $start = $acc->created_at;
                }

                if ($start) {
                    $startDt = Carbon::parse($start)->startOfMonth();

                    if ($periodStart->lt($startDt)) {
                        throw new \RuntimeException(
                            "SKIP_BEFORE_ACCOUNT_START: account={$canonicalAccountId} period={$period} start=".$startDt->format('Y-m')
                        );
                    }
                }
            }

            // ======================
            // ✅ Sync modo_cobro a clientes (source of truth: meta.billing.billing_cycle)
            // Evita inconsistencia: admin yearly pero clientes free/mensual
            // ======================
            try {
                $desiredModo = null;

                if ($cycleNorm === 'yearly') {
                    $desiredModo = 'anual';
                } elseif ($cycleNorm === 'monthly') {
                    $desiredModo = 'mensual';
                }

                if ($desiredModo) {
                    // Solo toca si viene distinto (defensivo)
                    $currentModo = strtolower((string)($acc->modo_cobro ?? ''));
                    if ($currentModo !== $desiredModo) {
                        DB::connection($connClients)->table('cuentas_cliente')
                            ->where('id', (string)$acc->id)
                            ->update([
                                'modo_cobro' => $desiredModo,
                                'updated_at' => now(),
                            ]);

                        Log::info('StatementSyncService.sync_client_modo_cobro', [
                            'client_uuid' => (string)$acc->id,
                            'admin_account_id' => $adminAccountId > 0 ? $adminAccountId : null,
                            'from' => $currentModo,
                            'to'   => $desiredModo,
                            'cycle_norm' => $cycleNorm,
                            'period' => $period,
                        ]);

                        // refrescar $acc para que lo que sigue (snapshot) ya lleve modo correcto
                        $acc = DB::connection($connClients)->table('cuentas_cliente')
                            ->where('id', (string)$acc->id)
                            ->first();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('StatementSyncService.sync_client_modo_cobro.failed', [
                    'client_uuid' => (string)($acc->id ?? ''),
                    'admin_account_id' => $adminAccountId > 0 ? $adminAccountId : null,
                    'period' => $period,
                    'err' => $e->getMessage(),
                ]);
            }

            // =========================================================
            // ✅ RULE: YEARLY solo en su mes due (anchor + 12n)
            // - Por defecto NO permitimos off-cycle (mes a mes).
            // - allow_yearly_offcycle=true lo deja pasar (solo para debugging/migraciones).
            // =========================================================
            $allowYearlyOffcycle = (bool)($opts['allow_yearly_offcycle'] ?? false);

            if ($cycleNorm === 'yearly' && !$allowYearlyOffcycle) {
                $anchor = $this->resolveYearlyAnchorPeriod($meta, $period);

                if (!$this->isYearlyDue($anchor, $period)) {
                    // No toca cobrar este mes.
                    // Importante: NO creamos/actualizamos statement.
                    throw new \RuntimeException("SKIP_YEARLY_NOT_DUE: offcycle account={$canonicalAccountId} period={$period} anchor={$anchor}");
                }
            }

            $baseAmt = (float)(
                data_get($meta, 'billing.amount_mxn')
                ?? data_get($meta, 'license.amount_mxn')
                ?? data_get($meta, 'license.base_amount')
                ?? data_get($meta, 'license.amount')
                ?? 0
            );

            $overrideByCycle = data_get($meta, "billing.override.{$cycleNorm}.amount_mxn");
            $legacyOverride  = data_get($meta, 'billing.override.amount_mxn') ?? data_get($meta, 'billing.override_amount_mxn');

            $overrideAmt = is_numeric($overrideByCycle)
                ? (float)$overrideByCycle
                : (is_numeric($legacyOverride) ? (float)$legacyOverride : null);

            $override = ($overrideAmt !== null && $overrideAmt > 0.00001)
                ? ['amount_mxn' => $overrideAmt, 'cycle' => $cycleNorm]
                : (data_get($meta, 'license.override') ?? null);

            // Monto efectivo a cobrar (prioridad override)
            $effectiveAmount = ($overrideAmt !== null && $overrideAmt > 0.00001) ? (float)$overrideAmt : (float)$baseAmt;
            $isBillable = $effectiveAmount > 0.00001;

            // “is_pro” lo dejamos como compat (pero el driver real es isBillable)
            $isPro = $isBillable || ($priceKey !== '') || in_array(strtolower($planKey), ['pro', 'premium'], true);

            // ===== 1.3) Emails
            $emails = [];
            $emailPrimary = trim((string)($acc->email ?? ''));
            if ($emailPrimary !== '') $emails[] = $emailPrimary;

            $extraEmails = [];
            if (isset($meta['billing_emails']) && is_array($meta['billing_emails'])) $extraEmails = $meta['billing_emails'];
            if (isset($meta['emails_facturacion']) && is_array($meta['emails_facturacion'])) {
                $extraEmails = array_merge($extraEmails, $meta['emails_facturacion']);
            }

            foreach ($extraEmails as $em) {
                $em = trim((string)$em);
                if ($em !== '') $emails[] = $em;
            }

            $emails = array_values(array_unique(array_map('mb_strtolower', $emails)));

            // ===== 2) Upsert statement
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
                if ((string)$st->account_id !== (string)$canonicalAccountId) {
                    $st->account_id = (string)$canonicalAccountId;
                }
            }

            // ===== 2.1) prev_saldo (roll forward)
            $prevSaldo = (float) DB::connection($connAdmin)->table('billing_statements')
                ->where('account_id', $canonicalAccountId)
                ->where('period', '<', $period)
                ->where('saldo', '>', 0)
                ->sum('saldo');

            // ===== 2.2) Meta trazabilidad
            $stMeta = $this->decodeMeta($st->meta ?? null);
            $stMeta['prev_saldo']       = $prevSaldo;
            $stMeta['sync_amount_mxn']  = (float)$effectiveAmount;
            $stMeta['sync_cycle']       = $cycleNorm;
            $stMeta['sync_source']      = $isBillable ? 'meta.billing' : 'free_or_unknown';
            $st->meta = $stMeta;

            // ===== 3) Snapshot
            $snap = [
                'account' => [
                    'client_uuid'      => (string) $acc->id,
                    'admin_account_id' => $adminAccountId > 0 ? $adminAccountId : null,
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
                    'cycle_norm'  => $cycleNorm,
                    'price_key'   => $priceKey,

                    // 👇 clave: dejamos el efectivo en snapshot para PDF/correo
                    'base_amount' => (float)$effectiveAmount,
                    'override'    => $override,
                ],
                'emails'        => $emails,
                'generated_at'  => now()->toISOString(),
            ];

            $st->snapshot = $snap;

            // due_date: día 5 del mes siguiente
            $due = Carbon::createFromFormat('Y-m', $period)->startOfMonth()->addMonth()->day(5);
            $st->due_date = $st->due_date ?: $due->toDateString();

            // Si aún no sabemos status, no inventamos paid_at
            if ((string)($st->status ?? '') !== 'paid') {
                $st->paid_at = null;
            }

            $st->save();

            // ===== 4) Emails de envío (tabla aparte)
            $this->syncEmails($st->id, $emails);

            // ===== 5) REPAIR: paid+locked con cargo 0 pero expected>0
            // Esto es EXACTAMENTE lo que te rompió los correos.
            if (
                (bool)$opts['repair_locked_zero']
                && (bool)($st->is_locked ?? false)
                && (string)($st->status ?? '') === 'paid'
                && $isBillable
            ) {
                $hasLicense = BillingStatementItem::query()
                    ->where('statement_id', $st->id)
                    ->where('type', 'license')
                    ->exists();

                $cargoDb = (float)($st->total_cargo ?? 0);
                $saldoDb = (float)($st->saldo ?? 0);

                // si no hay línea o está en ceros, reparamos
                if (!$hasLicense || (abs($cargoDb) <= 0.00001 && abs($saldoDb) <= 0.00001)) {
                    Log::warning('StatementSyncService.repair_locked_zero', [
                        'statement_id' => $st->id,
                        'account_id'   => (string)$canonicalAccountId,
                        'period'       => $period,
                        'is_locked'    => (int)$st->is_locked,
                        'status'       => (string)$st->status,
                        'effective'    => (float)$effectiveAmount,
                        'has_license'  => (int)$hasLicense,
                        'cargo_db'     => (float)$cargoDb,
                        'saldo_db'     => (float)$saldoDb,
                    ]);

                    // desbloqueamos para permitir rebuild de items
                    $st->is_locked = false;
                    $st->paid_at   = null;
                    $st->status    = 'pending';
                    $st->save();

                    BillingStatementEvent::create([
                        'statement_id' => $st->id,
                        'event'        => 'repair_locked_zero',
                        'actor'        => (string)$opts['actor'],
                        'notes'        => 'Auto-repair: paid+locked with zero totals but expected>0',
                        'meta'         => [
                            'period' => $period,
                            'amount_mxn' => (float)$effectiveAmount,
                        ],
                    ]);
                }
            }

            // Re-leer estado de lock tras reparación
            $st->refresh();
            $locked = (bool)($st->is_locked ?? false);

            // ===== 6) Items (solo si no está locked, o force)
            if (!$locked || $opts['force_rebuild_license_line']) {

                if ($isBillable) {
                    $this->upsertLicenseLine(
                        $st->id,
                        $period,
                        $planKey,
                        $cycleNorm,
                        $priceKey,
                        (float)$effectiveAmount,
                        $override,
                        (bool)$opts['force_rebuild_license_line']
                    );
                } else {
                    BillingStatementItem::query()
                        ->where('statement_id', $st->id)
                        ->where('type', 'license')
                        ->delete();
                }

                // aquí integrarías compras/consumos/ajustes
            }

            // ===== 7) Recalcular totales/saldo/estatus (incluye prev_saldo)
            $this->recalcStatement($st->id);

            // ===== 8) Lock si pagado (solo si realmente pagado)
            $st->refresh();
            if ($opts['lock_if_paid'] && (string)$st->status === 'paid' && !(bool)$st->is_locked) {
                $st->is_locked = true;
                $st->paid_at   = $st->paid_at ?: now();
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
                'meta'         => [
                    'period'      => $period,
                    'is_billable' => $isBillable,
                    'amount_mxn'  => (float)$effectiveAmount,
                    'cycle_norm'  => $cycleNorm,
                ],
            ]);

            Log::info('StatementSyncService.synced', [
                'conn_clients'         => $connClients,
                'conn_admin'           => $connAdmin,
                'account_id_input'     => (string)$accountId,
                'account_client_uuid'  => (string)($acc->id ?? ''),
                'admin_account_id'     => $adminAccountId > 0 ? $adminAccountId : null,
                'account_id_canonical' => (string)$canonicalAccountId,
                'period'               => $period,
                'statement_id'         => $st->id,
                'is_billable'          => $isBillable,
                'amount_mxn'           => (float)$effectiveAmount,
                'prev_saldo'           => (float)$prevSaldo,
                'status'               => (string)($st->status ?? ''),
                'locked'               => (int)($st->is_locked ?? 0),
            ]);

            return $st;
        });
    }

    public function syncAllForPeriod(string $period, array $opts = []): int
    {
        $connClients = (string) (config('p360.conn.clientes') ?: (config('p360.conn.clients') ?: 'mysql_clientes'));

        // Traemos solo activos
        $rows = DB::connection($connClients)->table('cuentas_cliente')
            ->where('activo', 1)
            ->get(['id', 'admin_account_id']);

        // Dedupe por canonical:
        // - si hay admin_account_id => canonical = admin_account_id (string)
        // - si no => canonical = uuid
        $seen = [];
        $idsToSync = [];

        foreach ($rows as $r) {
            $uuid = (string) ($r->id ?? '');
            $adm  = (string) ($r->admin_account_id ?? '');

            $canonical = ($adm !== '' && preg_match('/^\d+$/', $adm)) ? $adm : $uuid;
            if ($canonical === '' || $uuid === '') continue;

            if (isset($seen[$canonical])) {
                continue; // duplicado por canonical
            }

            $seen[$canonical] = true;

            // Importante: syncForAccountAndPeriod espera UUID de clientes
            $idsToSync[] = $uuid;
        }

        $ok = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($idsToSync as $id) {
            try {
                $this->syncForAccountAndPeriod((string) $id, $period, $opts);
                $ok++;
            } catch (\Throwable $e) {

                $msg = (string) $e->getMessage();

                // ✅ SKIP esperado (yearly offcycle / not due)
                if (str_contains($msg, 'SKIP_YEARLY_NOT_DUE') || str_contains($msg, 'SKIP_BEFORE_ACCOUNT_START')) {
                    $skipped++;

                    // Lo dejamos como INFO (no ERROR) para que no contamine alertas
                    $logSkipped = (bool)($opts['log_skipped_yearly'] ?? false);

                    if ($logSkipped) {
                        Log::info('StatementSyncService.syncAllForPeriod.skipped_yearly', [
                            'account_id' => (string) $id,
                            'period'     => $period,
                            'msg'        => $msg,
                        ]);
                    }

                    continue;
                }

                // ❌ error real
                $failed++;

                Log::error('StatementSyncService.syncAllForPeriod.failed', [
                    'account_id' => (string) $id,
                    'period'     => $period,
                    'err'        => $msg,
                ]);
            }
        }

        // ✅ Resumen (muy útil en producción)
        Log::info('StatementSyncService.syncAllForPeriod.summary', [
            'period'        => $period,
            'candidates'    => count($idsToSync),
            'ok'            => $ok,
            'skipped_yearly'=> $skipped,
            'failed'        => $failed,
        ]);

        // ✅ Retornamos "procesadas" para que te dé 26 cuando todo lo demás está ok
        return $ok + $skipped;
    }

    // =========================
    // INTERNAL HELPERS
    // =========================

    private function decodeMeta($meta): array
    {
        if (is_array($meta)) return $meta;
        if (is_object($meta)) return (array)$meta;
        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
        }
        return [];
    }

    private function pickFirstMetaCol(string $conn, string $table): ?string
    {
        foreach (['meta', 'billing_meta', 'settings', 'data', 'payload'] as $c) {
            if (Schema::connection($conn)->hasColumn($table, $c)) return $c;
        }
        return null;
    }

    private function normalizeCycle(string $cycle): string
    {
        $c = strtolower(trim($cycle));

        // legacy del proyecto
        if ($c === 'mensual') return 'monthly';
        if ($c === 'anual') return 'yearly';

        if (in_array($c, ['monthly', 'month', 'mes', 'm'], true)) return 'monthly';
        if (in_array($c, ['yearly', 'annual', 'year', 'año', 'y'], true)) return 'yearly';

        return 'monthly';
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
        string $cycleNorm,
        string $priceKey,
        float $baseAmt,
        $override,
        bool $force
    ): void {
        $finalAmt = $baseAmt;

        if (is_array($override) && isset($override['amount_mxn']) && is_numeric($override['amount_mxn'])) {
            $finalAmt = (float)$override['amount_mxn'];
        } elseif (is_array($override) && isset($override['amount']) && is_numeric($override['amount'])) {
            $finalAmt = (float)$override['amount'];
        } elseif (is_numeric($override)) {
            $finalAmt = (float)$override;
        }

        if ($finalAmt <= 0.00001) return;

        $labelPlan  = strtoupper(trim($planKey) !== '' ? $planKey : ($priceKey !== '' ? 'PRO' : 'LICENCIA'));
        $labelCycle = ($cycleNorm === 'yearly') ? 'ANUAL' : 'MENSUAL';

        $desc = "Licencia {$labelPlan} · {$labelCycle} ({$period})";

        $q = BillingStatementItem::query()
            ->where('statement_id', $statementId)
            ->where('type', 'license');

        $exists = $q->first();

        $payload = [
            'statement_id' => $statementId,
            'type'         => 'license',
            'code'         => 'LICENSE',
            'description'  => $desc,
            'qty'          => 1,
            'unit_price'   => $finalAmt,
            'amount'       => $finalAmt,
            'ref'          => $priceKey !== '' ? $priceKey : null,
            'meta'         => [
                'plan'        => $planKey,
                'cycle'       => $cycleNorm,
                'pk'          => $priceKey,
                'base_amount' => $baseAmt,
                'override'    => $override,
            ],
        ];

        if (!$exists) {
            BillingStatementItem::create($payload);
            return;
        }

        if ($force) {
            $exists->update($payload);
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

        // roll-forward: prev_saldo desde meta
        $meta = $this->decodeMeta($st->meta ?? null);
        $prev = (float)($meta['prev_saldo'] ?? 0);

        $saldo = round(max(0.0, $prev + $cargo - $abono), 2);

        $status = 'pending';
        if ($saldo <= 0.00001) $status = 'paid';

        $st->total_cargo = round($cargo, 2);
        $st->total_abono = round($abono, 2);
        $st->saldo       = $saldo;
        $st->status      = $status;

        // Si queda pending, nunca debe mantener paid_at/lock
        if ($status !== 'paid') {
            $st->paid_at   = null;
            $st->is_locked = false;
        }

        $st->save();
    }

    private function resolveYearlyAnchorPeriod(array $meta, string $fallbackPeriod): string
    {
        // 1) ✅ anchor explícito (source of truth si ya lo guardaste)
        $ap = (string) (data_get($meta, 'billing.anchor_period') ?? '');
        if (preg_match('/^\d{4}\-\d{2}$/', $ap)) {
            return $ap;
        }

        // 2) Si el override trae updated_at -> anchor = YYYY-MM (legacy compat)
        $ts = (string) (data_get($meta, 'billing.override.updated_at') ?? '');
        if ($ts !== '') {
            try {
                return \Carbon\Carbon::parse($ts)->format('Y-m');
            } catch (\Throwable $e) {
                // ignora parse
            }
        }

        // 3) fallback: el periodo que estás sincronizando
        return $fallbackPeriod;
    }

    private function isYearlyDue(string $anchorPeriod, string $period): bool
    {
        if (!preg_match('/^\d{4}\-\d{2}$/', $anchorPeriod)) return false;
        if (!preg_match('/^\d{4}\-\d{2}$/', $period)) return false;

        try {
            $a = \Carbon\Carbon::createFromFormat('Y-m', $anchorPeriod)->startOfMonth();
            $p = \Carbon\Carbon::createFromFormat('Y-m', $period)->startOfMonth();

            // si es antes del anchor, no toca
            if ($p->lt($a)) return false;

            // diferencia en meses
            $diff = ($a->year * 12 + $a->month) <= ($p->year * 12 + $p->month)
                ? (($p->year * 12 + $p->month) - ($a->year * 12 + $a->month))
                : 0;

            // due si: 0, 12, 24...
            return ($diff % 12) === 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function deleteStatementForAccountPeriod(string $connAdmin, string $accountId, string $period): void
    {
        $st = DB::connection($connAdmin)->table('billing_statements')
            ->where('account_id', (string)$accountId)
            ->where('period', (string)$period)
            ->first(['id', 'snapshot']);

        if (!$st) return;

        $id = (int)($st->id ?? 0);
        if ($id <= 0) return;

        DB::connection($connAdmin)->transaction(function () use ($connAdmin, $id) {

            if (Schema::connection($connAdmin)->hasTable('billing_statement_items')) {
                DB::connection($connAdmin)->table('billing_statement_items')->where('statement_id', $id)->delete();
            }
            if (Schema::connection($connAdmin)->hasTable('billing_statement_emails')) {
                DB::connection($connAdmin)->table('billing_statement_emails')->where('statement_id', $id)->delete();
            }
            if (Schema::connection($connAdmin)->hasTable('billing_statement_events')) {
                DB::connection($connAdmin)->table('billing_statement_events')->where('statement_id', $id)->delete();
            }

            DB::connection($connAdmin)->table('billing_statements')->where('id', $id)->delete();
        });
    }
}