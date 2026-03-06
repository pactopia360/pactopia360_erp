<?php

declare(strict_types=1);

namespace App\Services\Finance;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminIncomeService
{
    // ==========================
    // Normalización de cuenta (dedupe UUID/NUM)
    // ==========================
    private function normalizeAccountKey(?string $statementAccountId, ?object $cc): string
    {
        $sid = (string) ($statementAccountId ?? '');
        $sid = trim($sid);

        // Si tenemos cuenta cliente con admin_account_id, ese es el ID canónico para dedupe
        if ($cc && !empty($cc->admin_account_id)) {
            return (string) $cc->admin_account_id;
        }

        // Si el statement ya viene numérico, úsalo
        if ($sid !== '' && preg_match('/^\d+$/', $sid)) {
            return $sid;
        }

        // Si viene UUID, úsalo como último recurso
        if ($sid !== '' && preg_match('/^[0-9a-f\-]{36}$/i', $sid)) {
            return $sid;
        }

        return $sid !== '' ? $sid : '0';
    }

    // ==========================
    // Resolve cuenta_cliente desde statement account_id
    // (cuando account_id es uuid de ADMIN.accounts y no uuid de clientes)
    // ==========================
    private function resolveCuentaClienteFromStatementAccountId(
        string $admConn,
        string $stmtAccountId,
        Collection $cuentaByUuid,
        Collection $cuentaByAdmin
    ): ?object {
        $sid = trim((string) $stmtAccountId);
        if ($sid === '') return null;

        // 1) UUID: primero intenta clientes.id
        if (preg_match('/^[0-9a-f\-]{36}$/i', $sid)) {
            $cc = $cuentaByUuid->get($sid);
            if ($cc) return $cc;

            // 2) Fallback robusto:
            // En tu BD, admin.accounts NO tiene uuid, y algunos billing_statements.account_id quedan "huérfanos".
            // Entonces intentamos inferir por accounts.meta (si aplica).
            // NOTA: No tenemos el periodo aquí, así que este fallback NO usa payments.
            try {
                if (Schema::connection($admConn)->hasTable('accounts') && $this->hasCol($admConn, 'accounts', 'meta')) {
                    // Heurística: si el uuid se guardó en meta (JSON/text), lo encontramos por LIKE
                    $accId = DB::connection($admConn)->table('accounts')
                        ->where('meta', 'like', '%' . $sid . '%')
                        ->value('id');

                    if (!empty($accId)) {
                        $cc2 = $cuentaByAdmin->get((string) $accId);
                        if ($cc2) return $cc2;
                    }
                }
            } catch (\Throwable $e) {
                // noop
            }

            return null;
        }

        // 3) Numérico: admin_account_id directo
        if (preg_match('/^\d+$/', $sid)) {
            return $cuentaByAdmin->get($sid);
        }

        return null;
    }

    // ==========================
    // Inferir admin_account_id cuando billing_statements.account_id es UUID "huérfano"
    // Caso real (tu output):
    // - billing_statements.account_id = UUID que NO existe en clientes.cuentas_cliente.id
    // - admin.accounts NO tiene columna uuid
    // - payments.account_id es numérico (admin_account_id) y reference trae patrón:
    //   admin_mark_paid:{adminId}:{period}:...
    // ==========================
    private function inferAdminAccountIdForStatement(string $admConn, string $period, ?int $statementId = null): ?int
    {
        $per = trim((string) $period);
        if ($per === '') return null;

        if (!Schema::connection($admConn)->hasTable('payments')) return null;

        // Columnas disponibles (PROD-safe)
        $pHasReference = $this->hasCol($admConn, 'payments', 'reference');
        $pHasStatus    = $this->hasCol($admConn, 'payments', 'status');
        $pHasMeta      = $this->hasCol($admConn, 'payments', 'meta');

        try {

            // ✅ NO usar "+" para concatenar arrays
            $sel = ['id', 'account_id', 'period'];
            if ($pHasReference) $sel[] = 'reference';
            if ($pHasStatus)    $sel[] = 'status';
            if ($pHasMeta)      $sel[] = 'meta';

            $q = DB::connection($admConn)->table('payments')
                ->select($sel)
                ->where('period', '=', $per)
                ->orderByDesc('id');

            // Preferimos pagos "paid" si existe status
            if ($pHasStatus) {
                $q->whereIn('status', ['paid', 'pagado', 'succeeded', 'success', 'complete', 'completed']);
            }

            $rows = collect($q->limit(80)->get());

            // 1) Si meta trae statement_id (si existe), úsalo
            if ($statementId && $pHasMeta) {
                foreach ($rows as $p) {
                    $meta = $this->decodeJson($p->meta ?? null);
                    $sid  = (int) (data_get($meta, 'statement_id') ?? data_get($meta, 'billing_statement_id') ?? 0);
                    if ($sid > 0 && $sid === (int) $statementId) {
                        $aid = (int) ($p->account_id ?? 0);
                        return $aid > 0 ? $aid : null;
                    }
                }
            }

            // 2) Patrón real: reference = "admin_mark_paid:15:2026-01:..."
            if ($pHasReference) {
                foreach ($rows as $p) {
                    $ref = (string) ($p->reference ?? '');
                    if ($ref === '') continue;

                    if (preg_match('/admin_mark_paid:(\d+):' . preg_quote($per, '/') . ':/', $ref, $m)) {
                        $aid = (int) ($m[1] ?? 0);
                        return $aid > 0 ? $aid : null;
                    }
                }
            }

            // ✅ NO hacemos "fallback" a cualquier paid del periodo.
            // Si no hay señales fuertes, mejor NO inferir para evitar asignaciones incorrectas.
            return null;

        } catch (\Throwable $e) {
            return null;
        }
    }

    private function statementScore(object $s, Collection $itemsForStatement): int
    {
        // Queremos elegir UN statement por (cuenta_canónica, periodo)
        // Prioridad: pagado > emitido > locked > con items > total_cargo>0 > más nuevo
        $score = 0;

        if (!empty($s->paid_at)) $score += 1000;
        if (!empty($s->sent_at)) $score += 500;
        if ((int) ($s->is_locked ?? 0) === 1) $score += 200;

        $cntItems = (int) $itemsForStatement->count();
        if ($cntItems > 0) $score += 100 + min(50, $cntItems);

        $tc = (float) ($s->total_cargo ?? 0);
        if ($tc > 0) $score += 50;

        // desempate por updated_at/id
        $score += ((int) ($s->id ?? 0) > 0) ? 1 : 0;

        return $score;
    }

    private function pickBestStatement(Collection $group, Collection $itemsByStatement): ?object
    {
        if ($group->isEmpty()) return null;

        $best = null;
        $bestScore = -PHP_INT_MAX;

        foreach ($group as $s) {
            $its = collect($itemsByStatement->get($s->id, collect()));
            $sc  = $this->statementScore($s, $its);

            if ($sc > $bestScore) {
                $bestScore = $sc;
                $best      = $s;
            } elseif ($sc === $bestScore) {
                // desempate: preferir el más nuevo por id
                $bid = (int) ($best->id ?? 0);
                $sid = (int) ($s->id ?? 0);
                if ($sid > $bid) $best = $s;
            }
        }

        return $best;
    }

    // ==========================
    // UI data hygiene
    // ==========================
    private function isPlaceholderName(string $name): bool
    {
        $n = trim($name);
        if ($n === '') return true;
        return (bool) preg_match('/^cuenta\s+\d+$/i', $n);
    }

    private function isValidRfc(string $rfc): bool
    {
        $r = strtoupper(trim($rfc));
        if ($r === '') return false;

        return (bool) preg_match('/^([A-ZÑ&]{3,4})(\d{6})([A-Z0-9]{3})$/', $r);
    }

    private function pickRfcEmisor(?string $rfcPadre, ?string $rfc): string
    {
        $a = strtoupper(trim((string) ($rfcPadre ?? '')));
        $b = strtoupper(trim((string) ($rfc ?? '')));

        if ($a !== '' && $this->isValidRfc($a)) return $a;
        if ($b !== '' && $this->isValidRfc($b)) return $b;

        return '';
    }

    private function pickCompanyName(
        ?object $cuentaCliente,
        array $snapshot,
        array $meta,
        ?object $billingProfile
    ): string {
        $ccName = '';
        if ($cuentaCliente) {
            $ccName = (string) (
                ($cuentaCliente->nombre_comercial ?? '') !== '' ? $cuentaCliente->nombre_comercial :
                (($cuentaCliente->razon_social ?? '') !== '' ? $cuentaCliente->razon_social :
                ((property_exists($cuentaCliente, 'empresa') && (string) $cuentaCliente->empresa !== '') ? $cuentaCliente->empresa : ''))
            );
            $ccName = trim($ccName);
        }

        // Si viene "Cuenta N" o vacío, intentamos fuentes mejores
        if ($ccName === '' || $this->isPlaceholderName($ccName)) {
            $bpName = trim((string) ($billingProfile?->razon_social ?? ''));
            if ($bpName !== '' && !$this->isPlaceholderName($bpName)) return $bpName;

            $snapName = trim((string) (
                data_get($snapshot, 'account.company')
                ?? data_get($snapshot, 'company')
                ?? data_get($snapshot, 'razon_social')
                ?? data_get($meta, 'account.company')
                ?? data_get($meta, 'company')
                ?? data_get($meta, 'razon_social')
                ?? ''
            ));
            if ($snapName !== '' && !$this->isPlaceholderName($snapName)) return $snapName;
        }

        if ($ccName !== '' && !$this->isPlaceholderName($ccName)) return $ccName;

        $fallback = trim((string) (data_get($snapshot, 'company') ?? data_get($meta, 'company') ?? '—'));
        if ($fallback !== '' && !$this->isPlaceholderName($fallback)) return $fallback;

        return '—';
    }

    // ==========================
    // Schema helpers (PROD-safe)
    // ==========================
    private function hasCol(string $conn, string $table, string $col): bool
    {
        try {
            return Schema::connection($conn)->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ==========================
    // Schema helper SQL (más confiable que Schema::hasColumn en algunos entornos)
    // ==========================
    private function hasColSql(string $conn, string $table, string $col): bool
    {
        try {
            // SHOW COLUMNS es la fuente de verdad en MySQL/MariaDB
            $rows = DB::connection($conn)->select(
                "SHOW COLUMNS FROM `{$table}` LIKE ?",
                [$col]
            );
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ==========================
    // Statement lines: detectar tabla real (según tu BD)
    // ✅ NUEVO: elegir la tabla que realmente tiene filas para los statement_ids actuales
    // ==========================
    private function pickStatementLinesTable(string $admConn, array $statementIds = []): ?string
    {
        $candidates = [
            'billing_statement_lines',
            'billing_statement_items',
            'billing_statement_rows',
        ];

        $bestTable = null;
        $bestCount = -1;

        foreach ($candidates as $t) {
            try {
                if (!Schema::connection($admConn)->hasTable($t)) continue;
                if (!Schema::connection($admConn)->hasColumn($t, 'statement_id')) continue;

                // Si no nos pasan IDs, mantenemos lógica legacy: el primero válido.
                if (empty($statementIds)) {
                    return $t;
                }

                $cnt = (int) DB::connection($admConn)->table($t)
                    ->whereIn('statement_id', $statementIds)
                    ->count();

                if ($cnt > $bestCount) {
                    $bestCount = $cnt;
                    $bestTable = $t;
                }
            } catch (\Throwable $e) {
                // noop
            }
        }

        // Si nos pasaron statementIds, solo consideramos "líneas" si realmente hay filas.
        if (!empty($statementIds)) {
            return ($bestCount > 0) ? $bestTable : null;
        }

        // Legacy: si no pasaron IDs, devolvemos la primera tabla válida.
        return $bestTable;
    }

     /**
     * Construye filas de ingresos desde LÍNEAS del estado de cuenta (SOT).
     * ✅ 1 fila por statement_id (cabecera agregada)
     * ✅ Montos SIEMPRE desde billing_statements.total_cargo (ya trae IVA)
     * ✅ Devuelve statementIdSet para saber qué statements quedaron cubiertos por líneas.
     */
    private function buildRowsFromStatementLines(
        string $adm,
        string $cli,
        Collection $statementsFiltered,
        Collection $cuentaByUuid,
        Collection $cuentaByAdminId,
        Collection $profilesByAccountId,
        Collection $profilesByAdminAcc,
        Collection $invByStatement,
        Collection $invByAccPeriod,
        Collection $paymentsAggByAdminAccPeriod,
        Collection $vendorsById,
        Collection $vendorAssignByAdminAcc
    ): array {
        $statementIds = $statementsFiltered
            ->pluck('id')
            ->filter(fn ($x) => !empty($x))
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();

        if (empty($statementIds)) {
            return ['rows' => collect(), 'saleIdSet' => [], 'statementIdSet' => []];
        }

        $lineTable = $this->pickStatementLinesTable($adm, $statementIds);
        if (!$lineTable || $statementsFiltered->isEmpty()) {
            return ['rows' => collect(), 'saleIdSet' => [], 'statementIdSet' => []];
        }

        // Columnas disponibles en la tabla de líneas
        $cols = collect();
        try {
            $cols = collect(Schema::connection($adm)->getColumnListing($lineTable))
                ->map(fn ($c) => strtolower((string) $c))
                ->values();
        } catch (\Throwable $e) {
            $cols = collect();
        }

        $has = fn (string $c) => $cols->contains(strtolower($c));

        $sel = [
            'l.id as line_id',
            'l.statement_id',
        ];

        if ($has('origin'))      $sel[] = 'l.origin';
        if ($has('periodicity')) $sel[] = 'l.periodicity';
        if ($has('vendor_id'))   $sel[] = 'l.vendor_id';
        if ($has('meta'))        $sel[] = 'l.meta';

        // asociación a venta (si existe)
        $saleIdCol = null;
        foreach (['sale_id', 'finance_sale_id', 'sales_id'] as $c) {
            if ($has($c)) {
                $saleIdCol = $c;
                $sel[] = "l.`{$c}` as sale_id";
                break;
            }
        }

        // Query líneas + join billing_statements (incluye total_cargo)
        $q = DB::connection($adm)->table($lineTable . ' as l')
            ->join('billing_statements as bs', 'bs.id', '=', 'l.statement_id')
            ->select(array_merge($sel, [
                'bs.id as bs_id',
                'bs.account_id as bs_account_id',
                'bs.period as bs_period',
                'bs.status as bs_status',
                'bs.due_date as bs_due_date',
                'bs.sent_at as bs_sent_at',
                'bs.paid_at as bs_paid_at',
                'bs.snapshot as bs_snapshot',
                'bs.meta as bs_meta',
                'bs.total_cargo as bs_total_cargo',
                'bs.is_locked as bs_is_locked',
            ]))
            ->whereIn('l.statement_id', $statementIds)
            ->orderBy('bs.period')
            ->orderBy('l.statement_id')
            ->orderBy('l.id');

        $lineRows = collect($q->get());
        if ($lineRows->isEmpty()) {
            return ['rows' => collect(), 'saleIdSet' => [], 'statementIdSet' => []];
        }

        // saleIdSet para evitar duplicar ventas
        $saleIdSet = [];
        if ($saleIdCol) {
            foreach ($lineRows as $lr) {
                $sid = (int) ($lr->sale_id ?? 0);
                if ($sid > 0) $saleIdSet[$sid] = true;
            }
        }

        // agrupamos por statement_id (cabecera)
        $groups = $lineRows->groupBy(fn ($lr) => (int) ($lr->statement_id ?? 0));

        $statementIdSet = [];
        $rows = $groups->map(function (Collection $g, int $statementId) use (
            $adm,
            $cuentaByUuid,
            $cuentaByAdminId,
            $profilesByAccountId,
            $profilesByAdminAcc,
            $invByStatement,
            $invByAccPeriod,
            $paymentsAggByAdminAccPeriod,
            $vendorsById,
            $vendorAssignByAdminAcc,
            &$statementIdSet
        ) {
            $first = $g->first();
            if (!$first) return null;

            $statementIdSet[$statementId] = true;

            $accId = (string) ($first->bs_account_id ?? '');
            $per   = (string) ($first->bs_period ?? '');

            $snap = $this->decodeJson($first->bs_snapshot ?? null);
            $meta = $this->decodeJson($first->bs_meta ?? null);

            $cc = $this->resolveCuentaClienteFromStatementAccountId(
                $adm,
                $accId,
                $cuentaByUuid,
                $cuentaByAdminId
            );

            $adminAccId = $cc?->admin_account_id ? (string) $cc->admin_account_id : '';

            if ($adminAccId === '' && $accId !== '' && preg_match('/^\d+$/', $accId)) {
                $adminAccId = $accId;
            }

            if ($adminAccId === '') {
                $aid = $this->inferAdminAccountIdForStatement($adm, $per, (int) $statementId);
                if (!empty($aid)) {
                    $adminAccId = (string) $aid;
                    $cc2 = $cuentaByAdminId->get($adminAccId);
                    if ($cc2) $cc = $cc2;
                }
            }

            $pAgg = null;
            if ($adminAccId !== '') {
                $pAgg = $paymentsAggByAdminAccPeriod->get($adminAccId . '|' . $per);
            }

            $bp = $this->resolveBillingProfile(
                $accId,
                $adminAccId,
                $profilesByAccountId,
                $profilesByAdminAcc
            );

            $company = $this->pickCompanyName($cc, $snap, $meta, $bp);
            $rfcEmisor = $this->pickRfcEmisor($cc?->rfc_padre ?? null, $cc?->rfc ?? null);

            // ✅ Identidad
            $accountIdRaw       = $accId;
            $accountIdCanonical = $adminAccId !== '' ? $adminAccId : $accountIdRaw;
            $clientAccountId    = $cc?->id ? (string) $cc->id : null;

            // ✅ Montos desde billing_statements.total_cargo (ya trae IVA)
            $sObj = (object) ['total_cargo' => (float) ($first->bs_total_cargo ?? 0)];
            $am   = $this->resolveStatementAmounts($sObj, collect(), $snap, $meta);

            $subtotal = (float) ($am['subtotal'] ?? 0);
            $iva      = (float) ($am['iva'] ?? 0);
            $total    = (float) ($am['total'] ?? 0);

            // Origin / Periodicity (desde líneas + snap/meta)
            $origin = '';
            $periodicity = '';

            $anyRecurring = false;
            $anyCycle = '';

            foreach ($g as $lr) {
                $lineMeta = $this->decodeJson($lr->meta ?? null);

                $o = strtolower(trim((string) ($lr->origin ?? (data_get($lineMeta, 'origin') ?? ''))));
                if ($o === 'no_recurrente') $o = 'unico';
                if ($o === 'recurrente') $anyRecurring = true;

                $p = strtolower(trim((string) ($lr->periodicity ?? (data_get($lineMeta, 'periodicity') ?? ''))));
                if (in_array($p, ['mensual', 'anual', 'unico'], true)) $periodicity = $p;

                $cycle = strtolower(trim((string)(
                    data_get($lineMeta, 'billing_cycle')
                    ?? data_get($lineMeta, 'cycle')
                    ?? data_get($lineMeta, 'cobro')
                    ?? ''
                )));
                if (in_array($cycle, ['mensual', 'anual'], true)) $anyCycle = $cycle;
            }

            $snapMode = strtolower(trim((string)(
                data_get($snap, 'license.mode')
                ?? data_get($meta, 'license.mode')
                ?? data_get($snap, 'subscription.mode')
                ?? data_get($meta, 'subscription.mode')
                ?? ''
            )));

            if ($periodicity === '' && in_array($anyCycle, ['mensual', 'anual'], true)) $periodicity = $anyCycle;
            if ($periodicity === '' && in_array($snapMode, ['mensual', 'anual'], true)) $periodicity = $snapMode;

            if ($anyRecurring || in_array($periodicity, ['mensual','anual'], true)) {
                $origin = 'recurrente';
                if (!in_array($periodicity, ['mensual','anual'], true)) $periodicity = 'mensual';
            } else {
                $origin = 'unico';
                if ($periodicity === '') $periodicity = 'unico';
            }

            // Vendor: línea -> meta/snap -> asignación
            $vendorId = null;
            $vendorName = null;

            foreach ($g as $lr) {
                if (!empty($lr->vendor_id)) { $vendorId = (string) $lr->vendor_id; break; }
            }

            if (!$vendorId) $vendorId = $this->extractVendorId($meta, $snap, collect());

            if ($vendorId && $vendorsById->has($vendorId)) {
                $vendorName = (string) ($vendorsById->get($vendorId)?->name ?? null);
            }

            if ((!$vendorId || !$vendorName) && $adminAccId !== '') {
                $as = $vendorAssignByAdminAcc->get($adminAccId);
                if ($as) {
                    $perDate = $per !== '' ? ($per . '-01') : null;
                    $ok = true;

                    if ($perDate) {
                        $stOn = (string) ($as->starts_on ?? '');
                        $enOn = (string) ($as->ends_on ?? '');
                        if ($stOn !== '' && $perDate < $stOn) $ok = false;
                        if ($enOn !== '' && $perDate > $enOn) $ok = false;
                    }

                    if ($ok) {
                        $vendorId   = (string) ($as->vendor_id ?? $vendorId);
                        $vendorName = (string) ($as->vendor_name ?? $vendorName);
                    }
                }
            }

            // Invoice lookup
            $invRow = optional($invByStatement->get((int) $statementId))->first();

            if (!$invRow && $adminAccId !== '') $invRow = optional($invByAccPeriod->get($adminAccId . '|' . $per))->first();
            if (!$invRow) $invRow = optional($invByAccPeriod->get($accId . '|' . $per))->first();

            $invStatus   = $invRow?->status ? (string) $invRow->status : null;
            $invoiceDate = $invRow?->issued_at ?: null;
            $cfdiUuid    = $invRow?->cfdi_uuid ?: null;

            $invMeta = $this->decodeJson($invRow?->meta ?? null);
            $invoiceFormaPago  = (string) (data_get($invMeta, 'forma_pago') ?? data_get($invMeta, 'cfdi.forma_pago') ?? '');
            $invoiceMetodoPago = (string) (data_get($invMeta, 'metodo_pago') ?? data_get($invMeta, 'cfdi.metodo_pago') ?? '');
            $invoicePaidAt     = data_get($invMeta, 'paid_at') ?? data_get($invMeta, 'fecha_pago') ?? null;

            $paidAt = $pAgg?->paid_at ?: ($first->bs_paid_at ?? null) ?: ($invoicePaidAt ?: null);

            $ecStatus = $this->normalizeStatementStatus((object) [
                'paid_at'  => $paidAt,
                'sent_at'  => $first->bs_sent_at ?? null,
                'status'   => $first->bs_status ?? null,
                'due_date' => $first->bs_due_date ?? null,
            ]);

            if ($pAgg && !empty($pAgg->status)) {
                $ps = strtolower(trim((string) $pAgg->status));
                if (in_array($ps, ['paid', 'pagado', 'succeeded', 'success', 'complete', 'completed'], true)) {
                    $ecStatus = 'pagado';
                }
            }

            $y = (int) substr($per, 0, 4);
            $m = (int) substr($per, 5, 2);

            $desc = $origin === 'recurrente'
                ? ($periodicity === 'anual' ? 'Estado de cuenta · Recurrente Anual' : 'Estado de cuenta · Recurrente Mensual')
                : 'Estado de cuenta · Venta Única';

            return (object) [
                'source'        => 'statement_line',
                'is_projection' => 0,

                'has_statement'    => 1,
                'exclude_from_kpi' => 0,

                'year'       => $y,
                'month_num'  => sprintf('%02d', $m),
                'month_name' => $this->monthNameEs($m),

                'vendor_id' => $vendorId,
                'vendor'    => $vendorName,

                'client'      => $company,
                'description' => $desc,

                'period' => $per,

                'account_id'        => $accountIdCanonical,
                'account_id_raw'    => $accountIdRaw,
                'client_account_id' => $clientAccountId,

                'company'    => $company,
                'rfc_emisor' => $rfcEmisor,

                'origin'      => $origin,
                'periodicity' => $periodicity,

                'subtotal' => round($subtotal, 2),
                'iva'      => round($iva, 2),
                'total'    => round($total, 2),

                'ec_status' => $ecStatus,
                'due_date'  => $first->bs_due_date ?? null,
                'sent_at'   => $first->bs_sent_at ?? null,
                'paid_at'   => $paidAt,

                'rfc_receptor' => (string) ($bp->rfc_receptor ?? ''),
                'forma_pago'   => (string) ($bp->forma_pago ?? '') !== ''
                    ? (string) ($bp->forma_pago ?? '')
                    : ($invoiceFormaPago ?: ''),

                'f_emision' => $first->bs_sent_at ?? null,
                'f_pago'    => $paidAt,
                'f_cta'     => $first->bs_sent_at ?? null,
                'f_mov'     => null,

                'f_factura'           => $invoiceDate,
                'invoice_date'        => $invoiceDate,
                'invoice_status'      => $invStatus,
                'invoice_status_raw'  => $invStatus,
                'cfdi_uuid'           => $cfdiUuid,
                'invoice_metodo_pago' => $invoiceMetodoPago !== '' ? $invoiceMetodoPago : null,

                'payment_method' => $pAgg?->method ?: null,
                'payment_status' => $pAgg?->status ?: null,

                'statement_id' => (int) $statementId,
                'line_id'      => (int) ($first->line_id ?? 0),
                'sale_id'      => (int) ($first->sale_id ?? 0),

                'notes' => null,
            ];
        })->filter()->values();

        return [
            'rows' => $rows,
            'saleIdSet' => $saleIdSet,
            'statementIdSet' => $statementIdSet,
        ];
    }

    /**
 * Resuelve montos del statement (subtotal/iva/total) de forma robusta.
 * ✅ REGLA P360: billing_statements.total_cargo YA TRAE IVA (es TOTAL).
    */
    private function resolveStatementAmounts(object $s, Collection $items, array $snap, array $meta): array
    {
        $tc = round((float) ($s->total_cargo ?? 0), 2);

        // 1) snapshot/meta: si vienen totales explícitos, ganan
        $snapSubtotal =
            (float) (data_get($snap, 'totals.subtotal') ?? 0) ?:
            (float) (data_get($snap, 'statement.subtotal') ?? 0) ?:
            (float) (data_get($snap, 'subtotal') ?? 0) ?:
            (float) (data_get($meta, 'totals.subtotal') ?? 0) ?:
            (float) (data_get($meta, 'statement.subtotal') ?? 0) ?:
            (float) (data_get($meta, 'subtotal') ?? 0);

        $snapIva =
            (float) (data_get($snap, 'totals.iva') ?? 0) ?:
            (float) (data_get($snap, 'statement.iva') ?? 0) ?:
            (float) (data_get($snap, 'iva') ?? 0) ?:
            (float) (data_get($meta, 'totals.iva') ?? 0) ?:
            (float) (data_get($meta, 'statement.iva') ?? 0) ?:
            (float) (data_get($meta, 'iva') ?? 0);

        $snapTotal =
            (float) (data_get($snap, 'totals.total') ?? 0) ?:
            (float) (data_get($snap, 'statement.total') ?? 0) ?:
            (float) (data_get($snap, 'total') ?? 0) ?:
            (float) (data_get($meta, 'totals.total') ?? 0) ?:
            (float) (data_get($meta, 'statement.total') ?? 0) ?:
            (float) (data_get($meta, 'total') ?? 0);

        if ($snapSubtotal > 0 || $snapTotal > 0) {
            $subtotal = $snapSubtotal > 0 ? round($snapSubtotal, 2) : round(($snapTotal > 0 ? $snapTotal / 1.16 : 0), 2);
            $total    = $snapTotal > 0 ? round($snapTotal, 2) : round($subtotal * 1.16, 2);
            $iva      = $snapIva > 0 ? round($snapIva, 2) : round($total - $subtotal, 2);

            return [
                'subtotal' => round(max(0, $subtotal), 2),
                'iva'      => round(max(0, $iva), 2),
                'total'    => round(max(0, $total), 2),
                'mode'     => 'snapshot',
            ];
        }

        // 2) items heurística (si existen)
        $sumItems = round((float) $items->sum(fn ($it) => (float) ($it->amount ?? 0)), 2);

        // ✅ Si items parece subtotal y tc parece total con IVA:
        if ($tc > 0 && $sumItems > 0) {
            // items == subtotal y tc == total (con iva)
            if (abs(($sumItems * 1.16) - $tc) < 0.05) {
                $subtotal = $sumItems;
                $total    = $tc;
                $iva      = round($total - $subtotal, 2);

                return ['subtotal' => round($subtotal, 2), 'iva' => round($iva, 2), 'total' => round($total, 2), 'mode' => 'tc_is_total_items_subtotal'];
            }

            // items ya viene total (raro) y tc también total
            if (abs($sumItems - $tc) < 0.05) {
                $total    = $tc;
                $subtotal = round($total / 1.16, 2);
                $iva      = round($total - $subtotal, 2);

                return ['subtotal' => $subtotal, 'iva' => $iva, 'total' => $total, 'mode' => 'tc_is_total_items_total'];
            }
        }

        // 3) ✅ REGLA: fallback principal = tc es TOTAL con IVA
        if ($tc > 0) {
            $total    = $tc;
            $subtotal = round($total / 1.16, 2);
            $iva      = round($total - $subtotal, 2);

            return ['subtotal' => $subtotal, 'iva' => $iva, 'total' => $total, 'mode' => 'fallback_tc_total'];
        }

        // 4) último fallback: items como subtotal
        if ($sumItems > 0) {
            $subtotal = $sumItems;
            $total    = round($subtotal * 1.16, 2);
            $iva      = round($total - $subtotal, 2);
            return ['subtotal' => $subtotal, 'iva' => $iva, 'total' => $total, 'mode' => 'fallback_items_subtotal'];
        }

        return ['subtotal' => 0.0, 'iva' => 0.0, 'total' => 0.0, 'mode' => 'zero'];
    }

    /**
     * Compat helper: subtotal del statement usando resolveStatementAmounts().
     */
    private function resolveStatementSubtotal(object $s, Collection $items, array $snap, array $meta): float
    {
        $am = $this->resolveStatementAmounts($s, $items, $snap, $meta);
        return round((float) ($am['subtotal'] ?? 0.0), 2);
    }

    /**
     * Construye filas desde CABECERA de billing_statements (1 fila por statement).
     * ✅ Montos desde total_cargo (ya trae IVA)
     * ✅ Se usa para statements del mes que NO tienen líneas.
     */
    private function buildRowsFromStatementsHeader(
        string $adm,
        string $cli,
        Collection $statements,
        Collection $itemsByStatement,
        Collection $invByStatement,
        Collection $invByAccPeriod,
        Collection $profilesByAccountId,
        Collection $profilesByAdminAcc,
        Collection $cuentaByUuid,
        Collection $cuentaByAdminId,
        Collection $paymentsAggByAdminAccPeriod,
        Collection $vendorsById,
        Collection $vendorAssignByAdminAcc
    ): Collection {
        if ($statements->isEmpty()) return collect();

        return $statements->map(function ($s) use (
            $adm,
            $itemsByStatement,
            $invByStatement,
            $invByAccPeriod,
            $profilesByAccountId,
            $profilesByAdminAcc,
            $cuentaByUuid,
            $cuentaByAdminId,
            $paymentsAggByAdminAccPeriod,
            $vendorsById,
            $vendorAssignByAdminAcc
        ) {
            $accId = (string) ($s->account_id ?? '');
            $per   = (string) ($s->period ?? '');

            $snap = $this->decodeJson($s->snapshot ?? null);
            $meta = $this->decodeJson($s->meta ?? null);

            $cc = $this->resolveCuentaClienteFromStatementAccountId(
                $adm,
                $accId,
                $cuentaByUuid,
                $cuentaByAdminId
            );

            $adminAccId = $cc?->admin_account_id ? (string) $cc->admin_account_id : '';
            if ($adminAccId === '' && $accId !== '' && preg_match('/^\d+$/', $accId)) {
                $adminAccId = $accId;
            }
            if ($adminAccId === '') {
                $aid = $this->inferAdminAccountIdForStatement($adm, $per, (int) ($s->id ?? 0));
                if (!empty($aid)) {
                    $adminAccId = (string) $aid;
                    $cc2 = $cuentaByAdminId->get($adminAccId);
                    if ($cc2) $cc = $cc2;
                }
            }

            $pAgg = null;
            if ($adminAccId !== '') {
                $pAgg = $paymentsAggByAdminAccPeriod->get($adminAccId . '|' . $per);
            }

            $bp = $this->resolveBillingProfile(
                $accId,
                $adminAccId,
                $profilesByAccountId,
                $profilesByAdminAcc
            );

            $company   = $this->pickCompanyName($cc, $snap, $meta, $bp);
            $rfcEmisor = $this->pickRfcEmisor($cc?->rfc_padre ?? null, $cc?->rfc ?? null);

            // ✅ Montos por regla: total_cargo es TOTAL con IVA
            $its = collect($itemsByStatement->get((int) ($s->id ?? 0), collect()));
            $am  = $this->resolveStatementAmounts($s, $its, $snap, $meta);

            $subtotal = (float) ($am['subtotal'] ?? 0);
            $iva      = (float) ($am['iva'] ?? 0);
            $total    = (float) ($am['total'] ?? 0);

            $origin      = $this->guessOrigin($its, $snap, $meta);
            $periodicity = $this->guessPeriodicity($snap, $meta, $its);

            $modoCobro = strtolower((string) ($cc?->modo_cobro ?? ''));
            if (in_array($modoCobro, ['mensual', 'anual'], true)) {
                $periodicity = $modoCobro;
                $origin      = 'recurrente';
            }
            if ($origin === 'recurrente' && $periodicity === 'unico') $periodicity = 'mensual';

            // Vendor
            $vendorId = $this->extractVendorId($meta, $snap, $its);
            $vendorName = null;

            if (!empty($vendorId) && $vendorsById->has($vendorId)) {
                $vendorName = (string) ($vendorsById->get($vendorId)?->name ?? null);
            }

            if ((empty($vendorId) || !$vendorName) && $adminAccId !== '') {
                $as = $vendorAssignByAdminAcc->get($adminAccId);
                if ($as) {
                    $perDate = $per !== '' ? ($per . '-01') : null;
                    $ok = true;

                    if ($perDate) {
                        $stOn = (string) ($as->starts_on ?? '');
                        $enOn = (string) ($as->ends_on ?? '');
                        if ($stOn !== '' && $perDate < $stOn) $ok = false;
                        if ($enOn !== '' && $perDate > $enOn) $ok = false;
                    }

                    if ($ok) {
                        $vendorId   = (string) ($as->vendor_id ?? $vendorId);
                        $vendorName = (string) ($as->vendor_name ?? $vendorName);
                    }
                }
            }

            // Invoice
            $invRow = optional($invByStatement->get((int) ($s->id ?? 0)))->first();
            if (!$invRow && $adminAccId !== '') $invRow = optional($invByAccPeriod->get($adminAccId . '|' . $per))->first();
            if (!$invRow) $invRow = optional($invByAccPeriod->get($accId . '|' . $per))->first();

            $invStatus   = $invRow?->status ? (string) $invRow->status : null;
            $invoiceDate = $invRow?->issued_at ?: null;
            $cfdiUuid    = $invRow?->cfdi_uuid ?: null;

            $invMeta = $this->decodeJson($invRow?->meta ?? null);
            $invoiceFormaPago  = (string) (data_get($invMeta, 'forma_pago') ?? data_get($invMeta, 'cfdi.forma_pago') ?? '');
            $invoiceMetodoPago = (string) (data_get($invMeta, 'metodo_pago') ?? data_get($invMeta, 'cfdi.metodo_pago') ?? '');
            $invoicePaidAt     = data_get($invMeta, 'paid_at') ?? data_get($invMeta, 'fecha_pago') ?? null;

            $paidAt = $pAgg?->paid_at ?: ($s->paid_at ?? null) ?: ($invoicePaidAt ?: null);

            $ecStatus = $this->normalizeStatementStatus((object) [
                'paid_at'  => $paidAt,
                'sent_at'  => $s->sent_at ?? null,
                'status'   => $s->status ?? null,
                'due_date' => $s->due_date ?? null,
            ]);

            if ($pAgg && !empty($pAgg->status)) {
                $ps = strtolower(trim((string) $pAgg->status));
                if (in_array($ps, ['paid', 'pagado', 'succeeded', 'success', 'complete', 'completed'], true)) {
                    $ecStatus = 'pagado';
                }
            }

            $y = (int) substr($per, 0, 4);
            $m = (int) substr($per, 5, 2);

            $desc = $this->buildDescriptionFromItems($its, $origin, $periodicity);

            return (object) [
                'source'        => 'statement',
                'is_projection' => 0,

                'year'       => $y,
                'month_num'  => sprintf('%02d', $m),
                'month_name' => $this->monthNameEs($m),

                'vendor_id' => $vendorId,
                'vendor'    => $vendorName,

                'client'      => $company,
                'description' => $desc,

                'period' => $per,

                'account_id'        => ($adminAccId !== '' ? $adminAccId : $accId),
                'account_id_raw'    => $accId,
                'client_account_id' => ($cc?->id ? (string) $cc->id : null),

                'company'    => $company,
                'rfc_emisor' => $rfcEmisor,

                'origin'      => $origin,
                'periodicity' => $periodicity,

                'subtotal' => round($subtotal, 2),
                'iva'      => round($iva, 2),
                'total'    => round($total, 2),

                'ec_status' => $ecStatus,
                'due_date'  => $s->due_date ?? null,
                'sent_at'   => $s->sent_at ?? null,
                'paid_at'   => $paidAt,

                'rfc_receptor' => (string) ($bp->rfc_receptor ?? ''),
                'forma_pago'   => (string) ($bp->forma_pago ?? '') !== ''
                    ? (string) ($bp->forma_pago ?? '')
                    : ($invoiceFormaPago ?: ''),

                'f_emision' => $s->sent_at ?? null,
                'f_pago'    => $paidAt,
                'f_cta'     => $s->sent_at ?? null,
                'f_mov'     => null,

                'f_factura'           => $invoiceDate,
                'invoice_date'        => $invoiceDate,
                'invoice_status'      => $invStatus,
                'invoice_status_raw'  => $invStatus,
                'cfdi_uuid'           => $cfdiUuid,
                'invoice_metodo_pago' => $invoiceMetodoPago !== '' ? $invoiceMetodoPago : null,

                'payment_method' => $pAgg?->method ?: null,
                'payment_status' => $pAgg?->status ?: null,

                'statement_id' => (int) ($s->id ?? 0),
                'line_id'      => null,
                'sale_id'      => 0,

                'notes' => null,
            ];
        })->filter()->values();
    }

        /**
     * SOT REAL para Ingresos:
     * - cargo real desde estados_cuenta
     * - abono real = estados_cuenta.abono + payments paid
     * - expected_total desde accounts/meta SOLO como fallback
     * - si no hay evidencia real y la cuenta es recurrente => projection
     *
     * @return array{existing:\Illuminate\Support\Collection, projected:\Illuminate\Support\Collection}
     */
    private function buildRowsFromBillingLedgerSot(
        string $adm,
        array $periodsYear,
        Collection $cuentas,
        Collection $profilesByAccountId,
        Collection $profilesByAdminAcc,
        Collection $paymentsAggByAdminAccPeriod,
        Collection $vendorsById,
        Collection $vendorAssignByAdminAcc,
        Collection $invByAccPeriod
    ): array {
        $rowsExisting  = collect();
        $rowsProjected = collect();

        if ($cuentas->isEmpty() || empty($periodsYear)) {
            return ['existing' => $rowsExisting, 'projected' => $rowsProjected];
        }

        $adminIds = $cuentas
            ->pluck('admin_account_id')
            ->filter(fn ($x) => !empty($x) && preg_match('/^\d+$/', (string) $x))
            ->map(fn ($x) => (string) $x)
            ->unique()
            ->values()
            ->all();

        $edoAgg = collect();

        if (!empty($adminIds) && Schema::connection($adm)->hasTable('estados_cuenta')) {
            $edoAgg = collect(DB::connection($adm)->table('estados_cuenta')
                ->selectRaw('account_id, periodo, SUM(COALESCE(cargo,0)) as cargo_real, SUM(COALESCE(abono,0)) as abono_edo, COUNT(*) as lines_count')
                ->whereIn('account_id', $adminIds)
                ->whereIn('periodo', $periodsYear)
                ->groupBy('account_id', 'periodo')
                ->get())
                ->keyBy(fn ($r) => (string) $r->account_id . '|' . (string) $r->periodo);
        }

        $currentPeriod = now()->format('Y-m');

        foreach ($cuentas as $cc) {
            $adminAccId = trim((string) ($cc->admin_account_id ?? ''));
            if ($adminAccId === '' || !preg_match('/^\d+$/', $adminAccId)) {
                continue;
            }

            $clientUuid = trim((string) ($cc->id ?? ''));
            $modoCobro  = strtolower(trim((string) ($cc->modo_cobro ?? '')));
            $isRecurring = in_array($modoCobro, ['mensual', 'anual'], true);

            $bp = $this->resolveBillingProfile(
                $clientUuid,
                $adminAccId,
                $profilesByAccountId,
                $profilesByAdminAcc
            );

            $company   = $this->pickCompanyName($cc, [], [], $bp);
            $rfcEmisor = $this->pickRfcEmisor($cc->rfc_padre ?? null, $cc->rfc ?? null);

            foreach ($periodsYear as $per) {
                $agg  = $edoAgg->get($adminAccId . '|' . $per);
                $pAgg = $paymentsAggByAdminAccPeriod->get($adminAccId . '|' . $per);

                $cargoReal = round((float) ($agg->cargo_real ?? 0), 2);
                $abonoEdo  = round((float) ($agg->abono_edo ?? 0), 2);
                $abonoPay  = round((float) ($pAgg->sum_amount_mxn ?? 0), 2);
                $abonoTot  = round($abonoEdo + $abonoPay, 2);

                // expected_total solo fallback (misma idea que Billing)
                $expected = 0.0;

                $meta = $this->decodeJson($cc->meta ?? null);

                foreach ([
                    data_get($meta, 'billing.override_amount_mxn'),
                    data_get($meta, 'billing.override.amount_mxn'),
                    data_get($meta, 'billing.custom.amount_mxn'),
                    data_get($meta, 'billing.custom_mxn'),
                    data_get($meta, 'custom.amount_mxn'),
                    data_get($meta, 'custom_mxn'),
                    $cc->override_amount_mxn ?? null,
                    $cc->custom_amount_mxn ?? null,
                    $cc->billing_amount_mxn ?? null,
                    $cc->amount_mxn ?? null,
                    $cc->precio_mxn ?? null,
                    $cc->monto_mxn ?? null,
                    $cc->license_amount_mxn ?? null,
                    $cc->billing_amount ?? null,
                    $cc->amount ?? null,
                    $cc->precio ?? null,
                    $cc->monto ?? null,
                ] as $cand) {
                    $n = is_numeric($cand) ? (float) $cand : null;
                    if ($n !== null && $n > 0.00001) {
                        $expected = round($n, 2);
                        break;
                    }
                }

                $totalShown = $cargoReal > 0.00001 ? $cargoReal : $expected;
                $saldo      = round(max(0.0, $totalShown - $abonoTot), 2);

                $hasEvidence = (
                    $cargoReal > 0.00001 ||
                    $abonoEdo > 0.00001 ||
                    $abonoPay > 0.00001 ||
                    (int) ($agg->lines_count ?? 0) > 0
                );

                if ($totalShown <= 0.00001 && !$hasEvidence) {
                    continue;
                }

                if ($totalShown <= 0.00001) {
                    $ecStatus = 'pending';
                } elseif ($saldo <= 0.00001) {
                    $ecStatus = 'pagado';
                } elseif ($per < $currentPeriod) {
                    $ecStatus = 'vencido';
                } else {
                    $ecStatus = 'pending';
                }

                $origin      = $isRecurring ? 'recurrente' : 'unico';
                $periodicity = $isRecurring ? $modoCobro : 'unico';

                $vendorId = null;
                $vendorName = null;

                $as = $vendorAssignByAdminAcc->get($adminAccId);
                if ($as) {
                    $perDate = $per . '-01';
                    $ok = true;

                    $stOn = (string) ($as->starts_on ?? '');
                    $enOn = (string) ($as->ends_on ?? '');

                    if ($stOn !== '' && $perDate < $stOn) $ok = false;
                    if ($enOn !== '' && $perDate > $enOn) $ok = false;

                    if ($ok) {
                        $vendorId   = (string) ($as->vendor_id ?? '');
                        $vendorName = (string) ($as->vendor_name ?? '');
                    }
                }

                if ($vendorId !== '' && $vendorName === '' && $vendorsById->has($vendorId)) {
                    $vendorName = (string) ($vendorsById->get($vendorId)?->name ?? '');
                }

                $invRow = optional($invByAccPeriod->get($adminAccId . '|' . $per))->first();
                if (!$invRow && $clientUuid !== '') {
                    $invRow = optional($invByAccPeriod->get($clientUuid . '|' . $per))->first();
                }

                $invStatus   = $invRow?->status ? (string) $invRow->status : null;
                $invoiceDate = $invRow?->issued_at ?: null;
                $cfdiUuid    = $invRow?->cfdi_uuid ?: null;

                $invMeta = $this->decodeJson($invRow?->meta ?? null);
                $invoiceFormaPago  = (string) (data_get($invMeta, 'forma_pago') ?? data_get($invMeta, 'cfdi.forma_pago') ?? '');
                $invoiceMetodoPago = (string) (data_get($invMeta, 'metodo_pago') ?? data_get($invMeta, 'cfdi.metodo_pago') ?? '');
                $invoicePaidAt     = data_get($invMeta, 'paid_at') ?? data_get($invMeta, 'fecha_pago') ?? null;

                $paidAt = $pAgg?->paid_at ?: ($invoicePaidAt ?: null);

                $subtotal = round($totalShown / 1.16, 2);
                $iva      = round($totalShown - $subtotal, 2);

                $y = (int) substr($per, 0, 4);
                $m = (int) substr($per, 5, 2);

                $desc = $isRecurring
                    ? ($modoCobro === 'anual' ? 'Estado de cuenta · Recurrente Anual' : 'Estado de cuenta · Recurrente Mensual')
                    : 'Estado de cuenta · Venta Única';

                $row = (object) [
                    'source'        => $hasEvidence ? 'statement' : 'projection',
                    'is_projection' => $hasEvidence ? 0 : 1,

                    'has_statement'    => $hasEvidence ? 1 : 0,
                    'exclude_from_kpi' => $hasEvidence ? 0 : 1,

                    'year'       => $y,
                    'month_num'  => sprintf('%02d', $m),
                    'month_name' => $this->monthNameEs($m),

                    'vendor_id' => $vendorId ?: null,
                    'vendor'    => $vendorName ?: null,

                    'client'      => $company,
                    'description' => $desc,

                    'period' => $per,

                    'account_id'        => $adminAccId,
                    'account_id_raw'    => $adminAccId,
                    'client_account_id' => $clientUuid !== '' ? $clientUuid : null,

                    'company'    => $company,
                    'rfc_emisor' => $rfcEmisor,

                    'origin'      => $origin,
                    'periodicity' => $periodicity,

                    'subtotal' => round($subtotal, 2),
                    'iva'      => round($iva, 2),
                    'total'    => round($totalShown, 2),

                    'ec_status' => $ecStatus,
                    'due_date'  => null,
                    'sent_at'   => null,
                    'paid_at'   => $paidAt,

                    'rfc_receptor' => (string) ($bp->rfc_receptor ?? ''),
                    'forma_pago'   => (string) ($bp->forma_pago ?? '') !== ''
                        ? (string) ($bp->forma_pago ?? '')
                        : ($invoiceFormaPago ?: ''),

                    'f_emision' => null,
                    'f_pago'    => $paidAt,
                    'f_cta'     => null,
                    'f_mov'     => null,

                    'f_factura'           => $invoiceDate,
                    'invoice_date'        => $invoiceDate,
                    'invoice_status'      => $invStatus,
                    'invoice_status_raw'  => $invStatus,
                    'cfdi_uuid'           => $cfdiUuid,
                    'invoice_metodo_pago' => $invoiceMetodoPago !== '' ? $invoiceMetodoPago : null,

                    'payment_method' => $pAgg?->method ?: null,
                    'payment_status' => $pAgg?->status ?: null,

                    'statement_id' => 0,
                    'line_id'      => null,
                    'sale_id'      => 0,

                    'notes' => null,
                ];

                if ($hasEvidence) {
                    $rowsExisting->push($row);
                } else {
                    if ($isRecurring) {
                        $rowsProjected->push($row);
                    }
                }
            }
        }

        return [
            'existing'  => $rowsExisting->values(),
            'projected' => $rowsProjected->values(),
        ];
    }

    // ==========================
    // Billing profile resolver
    // ==========================
    private function resolveBillingProfile(
        string $accountId,
        string $adminAccountId,
        Collection $profilesByAccountId,
        Collection $profilesByAdminAcc
    ): ?object {
        $bp = $profilesByAccountId->get((string) $accountId);
        if ($bp) return $bp;

        if ($adminAccountId !== '' && $profilesByAdminAcc->isNotEmpty()) {
            $bp = $profilesByAdminAcc->get((string) $adminAccountId);
            if ($bp) return $bp;
        }

        if ($adminAccountId === '' && preg_match('/^\d+$/', $accountId)) {
            $bp = $profilesByAccountId->get((string) $accountId);
            if ($bp) return $bp;
        }

        return null;
    }

    private function extractVendorId(array $meta, array $snap, Collection $items): ?string
    {
        $vid = data_get($meta, 'vendor_id')
            ?? data_get($meta, 'vendor.id')
            ?? data_get($snap, 'vendor_id')
            ?? data_get($snap, 'vendor.id')
            ?? null;

        if (!empty($vid)) return (string) $vid;

        foreach ($items as $it) {
            $im   = $this->decodeJson($it->meta ?? null);
            $vid2 = data_get($im, 'vendor_id') ?? data_get($im, 'vendor.id') ?? null;
            if (!empty($vid2)) return (string) $vid2;
        }

        return null;
    }

        private function extractCustomAmountMxnFromAdminAccount(?object $row, array $meta): ?float
    {
        $candidates = [
            data_get($meta, 'billing.override_amount_mxn'),
            data_get($meta, 'billing.override.amount_mxn'),
            data_get($meta, 'billing.custom.amount_mxn'),
            data_get($meta, 'billing.custom_mxn'),
            data_get($meta, 'custom.amount_mxn'),
            data_get($meta, 'custom_mxn'),
        ];

        foreach ($candidates as $v) {
            $n = $this->toFloatCompat($v);
            if ($n !== null && $n > 0.00001) return $n;
        }

        if ($row) {
            foreach ([
                'override_amount_mxn',
                'custom_amount_mxn',
                'billing_amount_mxn',
                'amount_mxn',
                'precio_mxn',
                'monto_mxn',
                'license_amount_mxn',
                'billing_amount',
                'amount',
                'precio',
                'monto',
            ] as $prop) {
                if (isset($row->{$prop})) {
                    $n = $this->toFloatCompat($row->{$prop});
                    if ($n !== null && $n > 0.00001) return $n;
                }
            }
        }

        return null;
    }

    private function toFloatCompat(mixed $v): ?float
    {
        if ($v === null) return null;
        if (is_float($v) || is_int($v)) return (float) $v;

        if (is_string($v)) {
            $s = trim($v);
            if ($s === '') return null;

            $s = str_replace(['$', ',', 'MXN', 'mxn', ' '], '', $s);
            if (!is_numeric($s)) return null;

            return (float) $s;
        }

        if (is_numeric($v)) return (float) $v;

        return null;
    }

    /**
     * Compat con BillingStatementsHubController::resolveEffectiveAmountForPeriodFromMeta()
     *
     * @return array{0:float,1:string,2:string}
     */
    private function resolveEffectiveAmountForPeriodFromMetaCompat(array $meta, string $period, ?string $payAllowed): array
    {
        $billing = (array) ($meta['billing'] ?? []);

        $rawBase = $billing['amount_mxn'] ?? ($billing['amount'] ?? null);
        $base = $this->toFloatCompat($rawBase) ?? 0.0;

        $ov = (array) ($billing['override'] ?? []);
        $override = $this->toFloatCompat(
            $ov['amount_mxn'] ?? ($billing['override_amount_mxn'] ?? null)
        ) ?? 0.0;

        $eff = strtolower(trim((string) ($ov['effective'] ?? ($billing['override_effective'] ?? ''))));
        if (!in_array($eff, ['now', 'next'], true)) {
            $eff = '';
        }

        $apply = false;
        if ($override > 0) {
            if ($eff === 'now') {
                $apply = true;
            } elseif ($eff === 'next') {
                $apply = ($payAllowed && $period >= $payAllowed);
            }
        }

        $effective = $apply ? $override : $base;
        $label = $apply ? 'Tarifa ajustada' : 'Tarifa base';
        $pillText = $apply
            ? (($eff === 'next') ? 'Ajuste (próximo periodo)' : 'Ajuste (vigente)')
            : 'Base';

        return [round((float) $effective, 2), (string) $label, (string) $pillText];
    }

    private function resolveLastPaidPeriodFromPaymentsCompat(string $adm, string $accountId): ?string
    {
        $accountId = trim($accountId);
        if ($accountId === '' || !preg_match('/^\d+$/', $accountId)) {
            return null;
        }

        if (!Schema::connection($adm)->hasTable('payments')) {
            return null;
        }

        try {
            $cols = Schema::connection($adm)->getColumnListing('payments');
            $lc   = array_map('strtolower', $cols);
            $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

            if (!$has('account_id') || !$has('status') || !$has('period')) {
                return null;
            }

            $row = DB::connection($adm)->table('payments')
                ->where('account_id', (int) $accountId)
                ->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete', 'captured', 'authorized', 'pagado'])
                ->orderByDesc($has('paid_at') ? 'paid_at' : ($has('created_at') ? 'created_at' : ($has('id') ? 'id' : $cols[0])))
                ->first(['period']);

            if (!$row || empty($row->period)) {
                return null;
            }

            $p = trim((string) $row->period);
            if (preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $p)) {
                return $p;
            }

            if (preg_match('/^\d{4}\-(0[1-9]|1[0-2])\-\d{2}/', $p)) {
                return substr($p, 0, 7);
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * SOT Billing:
     * arma el total mostrado por (admin_account_id|period)
     * usando la misma lógica del Billing HUB.
     */
    private function buildBillingSotByAdminAccPeriod(
        string $adm,
        Collection $adminAccountsById,
        array $periodsYear,
        Collection $paymentsAggByAdminAccPeriod
    ): Collection {
        if ($adminAccountsById->isEmpty() || empty($periodsYear)) {
            return collect();
        }

        $adminIds = $adminAccountsById->keys()
            ->map(fn ($x) => (string) $x)
            ->filter(fn ($x) => $x !== '' && preg_match('/^\d+$/', $x))
            ->values()
            ->all();

        if (empty($adminIds) || !Schema::connection($adm)->hasTable('estados_cuenta')) {
            return collect();
        }

        $edoAgg = collect(DB::connection($adm)->table('estados_cuenta')
            ->selectRaw('account_id, periodo, SUM(COALESCE(cargo,0)) as cargo_real, SUM(COALESCE(abono,0)) as abono_ec')
            ->whereIn('account_id', $adminIds)
            ->whereIn('periodo', $periodsYear)
            ->groupBy('account_id', 'periodo')
            ->get())
            ->keyBy(fn ($r) => (string) $r->account_id . '|' . (string) $r->periodo);

        $currentPeriod = now()->format('Y-m');
        $out = collect();

        foreach ($adminIds as $aid) {
            $acc = $adminAccountsById->get((string) $aid);
            $meta = $this->decodeJson($acc->meta ?? null);

            $lastPaid = $this->resolveLastPaidPeriodFromPaymentsCompat($adm, (string) $aid);
            $payAllowed = null;

            if ($lastPaid && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $lastPaid)) {
                try {
                    $payAllowed = Carbon::createFromFormat('Y-m', $lastPaid)
                        ->addMonthNoOverflow()
                        ->format('Y-m');
                } catch (\Throwable $e) {
                    $payAllowed = null;
                }
            }

            foreach ($periodsYear as $per) {
                $agg = $edoAgg->get((string) $aid . '|' . $per);

                $cargoReal = round((float) ($agg->cargo_real ?? 0), 2);
                $abonoEc   = round((float) ($agg->abono_ec ?? 0), 2);

                $pAgg = $paymentsAggByAdminAccPeriod->get((string) $aid . '|' . $per);
                $abonoPay = round((float) ($pAgg->sum_amount_mxn ?? 0), 2);

                $custom = $this->extractCustomAmountMxnFromAdminAccount($acc, $meta);
                if ($custom !== null && $custom > 0.00001) {
                    $expected = round((float) $custom, 2);
                } else {
                    [$expected] = $this->resolveEffectiveAmountForPeriodFromMetaCompat($meta, $per, $payAllowed);
                    $expected = round((float) $expected, 2);
                }

                $totalShown = $cargoReal > 0.00001 ? $cargoReal : $expected;
                $abonoTotal = round($abonoEc + $abonoPay, 2);
                $saldo      = round(max(0.0, $totalShown - $abonoTotal), 2);

                if ($totalShown <= 0.00001) {
                    $ecStatus = 'pending';
                } elseif ($saldo <= 0.00001) {
                    $ecStatus = 'pagado';
                } elseif ($per < $currentPeriod) {
                    $ecStatus = 'vencido';
                } else {
                    $ecStatus = 'pending';
                }

                $subtotal = round($totalShown / 1.16, 2);
                $iva      = round($totalShown - $subtotal, 2);

                $out->put((string) $aid . '|' . $per, (object) [
                    'admin_account_id' => (string) $aid,
                    'period'           => (string) $per,
                    'cargo_real'       => $cargoReal,
                    'abono_ec'         => $abonoEc,
                    'abono_pay'        => $abonoPay,
                    'abono_total'      => $abonoTotal,
                    'expected_total'   => $expected,
                    'total'            => round($totalShown, 2),
                    'subtotal'         => $subtotal,
                    'iva'              => $iva,
                    'saldo'            => $saldo,
                    'ec_status'        => $ecStatus,
                    'paid_at'          => $pAgg?->paid_at ?: null,
                ]);
            }
        }

        return $out;
    }

    private function attachCompanyFromClientes(Collection $rows, string $cliConn): Collection
    {
        $ids = $rows->pluck('account_id')->filter()->unique()->values()->all();
        if (empty($ids)) return $rows;

        if (!Schema::connection($cliConn)->hasTable('cuentas_cliente')) return $rows;

        $hasEmpresa   = Schema::connection($cliConn)->hasColumn('cuentas_cliente', 'empresa');
        $hasRfc       = Schema::connection($cliConn)->hasColumn('cuentas_cliente', 'rfc');
        $hasRfcPadre  = Schema::connection($cliConn)->hasColumn('cuentas_cliente', 'rfc_padre');

        $uuidIds  = [];
        $adminIds = [];

        foreach ($ids as $id) {
            $id = (string) $id;
            if ($id === '') continue;

            if (preg_match('/^[0-9a-f\-]{36}$/i', $id)) {
                $uuidIds[] = $id;
            } elseif (preg_match('/^\d+$/', $id)) {
                $adminIds[] = (int) $id;
            }
        }

        $sel = ['id', 'admin_account_id', 'razon_social', 'nombre_comercial'];
        if ($hasEmpresa)  $sel[] = 'empresa';
        if ($hasRfc)      $sel[] = 'rfc';
        if ($hasRfcPadre) $sel[] = 'rfc_padre';

        $q = DB::connection($cliConn)->table('cuentas_cliente')->select($sel);

        if (!empty($uuidIds) || !empty($adminIds)) {
            $q->where(function ($w) use ($uuidIds, $adminIds) {
                if (!empty($uuidIds))  $w->whereIn('id', $uuidIds);
                if (!empty($adminIds)) $w->orWhereIn('admin_account_id', $adminIds);
            });
        }

        $found = collect($q->get());

        $byUuid = $found->filter(fn ($c) => !empty($c->id))->keyBy(fn ($c) => (string) $c->id);
        $byAdm  = $found->filter(fn ($c) => !empty($c->admin_account_id))->keyBy(fn ($c) => (string) $c->admin_account_id);

        return $rows->map(function ($r) use ($byUuid, $byAdm) {
            $aid = (string) ($r->account_id ?? '');
            $c   = null;

            if ($aid !== '') {
                if (preg_match('/^[0-9a-f\-]{36}$/i', $aid)) {
                    $c = $byUuid->get($aid);
                } elseif (preg_match('/^\d+$/', $aid)) {
                    $c = $byAdm->get($aid);
                }
            }

            $name = 'Cuenta ' . $aid;
            if ($c) {
                $cand = trim((string) (
                    ($c->nombre_comercial ?? '') !== '' ? $c->nombre_comercial :
                    (($c->razon_social ?? '') !== '' ? $c->razon_social :
                    (($c->empresa ?? '') !== '' ? $c->empresa : ''))
                ));

                if ($cand !== '' && !$this->isPlaceholderName($cand)) $name = $cand;
                elseif (($c->razon_social ?? '') !== '' && !$this->isPlaceholderName((string) $c->razon_social)) $name = (string) $c->razon_social;
                elseif (($c->empresa ?? '') !== '' && !$this->isPlaceholderName((string) $c->empresa)) $name = (string) $c->empresa;
            }

            $r->company = $name;
            $r->rfc_emisor = $c ? $this->pickRfcEmisor($c->rfc_padre ?? null, $c->rfc ?? null) : '';

            return $r;
        });
    }

    private function mapSalesInvoiceStatusToCanonical(string $raw): string
    {
        return match ($raw) {
            'solicitada'    => 'requested',
            'en_proceso'    => 'ready',
            'facturada'     => 'issued',
            'rechazada'     => 'cancelled',
            'sin_solicitud' => 'sin_solicitud',
            default         => $raw !== '' ? $raw : 'sin_solicitud',
        };
    }

    // ==========================
    // KPIs: estructura base
    // ==========================
    private function blankKpis(): array
    {
        $mk = fn () => ['count' => 0, 'amount' => 0.0];

        return [
            // Ingreso real (base SUBTOTAL)
            'total'   => $mk(),
            'pending' => $mk(),
            'emitido' => $mk(),
            'pagado'  => $mk(),
            'vencido' => $mk(),

            // Pipeline (excluido de ingreso real)
            'pipeline_total'   => $mk(),
            'pipeline_pending' => $mk(),
            'pipeline_emitido' => $mk(),
            'pipeline_pagado'  => $mk(),
            'pipeline_vencido' => $mk(),
        ];
    }

    private function computeKpis(Collection $rows): array
    {
        $k = $this->blankKpis();

        foreach ($rows as $r) {
            // ✅ En Ingresos la base debe ser TOTAL (con IVA), no subtotal
            $amount = (float) ($r->total ?? 0);
            $st = strtolower((string) ($r->ec_status ?? ''));
            $isExcluded = (int) ($r->exclude_from_kpi ?? 0) === 1;

            if ($isExcluded) {
                $k['pipeline_total']['count']++;
                $k['pipeline_total']['amount'] += $amount;

                $pk = 'pipeline_' . $st;
                if ($st !== '' && isset($k[$pk])) {
                    $k[$pk]['count']++;
                    $k[$pk]['amount'] += $amount;
                }
                continue;
            }

            $k['total']['count']++;
            $k['total']['amount'] += $amount;

            if ($st !== '' && isset($k[$st])) {
                $k[$st]['count']++;
                $k[$st]['amount'] += $amount;
            }
        }

        foreach ($k as $key => $v) {
            $k[$key]['amount'] = round((float) $k[$key]['amount'], 2);
        }

        return $k;
    }
    private function normalizeStatementStatus(object $s): string
    {
        // 1) Señales duras por timestamps
        if (!empty($s->paid_at)) return 'pagado';
        if (!empty($s->sent_at)) return 'emitido';

        $st = strtolower(trim((string) ($s->status ?? '')));

        // 2) ✅ Blindaje: si el status explícito ya dice "paid/pagado",
        // lo tratamos como pagado aunque paid_at venga null (caso real en tu seed).
        if (in_array($st, ['paid', 'pagado'], true)) {
            return 'pagado';
        }

        $norm = match ($st) {
            'sent', 'emitido'      => 'emitido',
            'overdue', 'vencido'   => 'vencido',
            'pending', 'pendiente' => 'pending',
            default                => 'pending',
        };

        // 3) Si está pending y hay due_date vencida => vencido
        if ($norm === 'pending' && !empty($s->due_date)) {
            try {
                $due = Carbon::parse($s->due_date)->startOfDay();
                if ($due->lt(now()->startOfDay())) return 'vencido';
            } catch (\Throwable $e) {
                // noop
            }
        }

        return $norm;
    }

    private function guessOrigin(Collection $items, array $snap, array $meta): string
    {
        $mode = strtolower((string) (data_get($snap, 'license.mode') ?? data_get($meta, 'license.mode') ?? ''));
        if (in_array($mode, ['mensual', 'anual'], true)) return 'recurrente';

        foreach ($items as $it) {
            $type = strtolower((string) ($it->type ?? ''));
            $code = strtolower((string) ($it->code ?? ''));

            if (in_array($type, ['license', 'subscription', 'plan'], true)) return 'recurrente';
            if (str_contains($code, 'lic') || str_contains($code, 'plan')) return 'recurrente';

            $im   = $this->decodeJson($it->meta ?? null);
            $orig = strtolower((string) (data_get($im, 'origin') ?? ''));

            if ($orig === 'no_recurrente') return 'unico';
            if ($orig === 'recurrente') return 'recurrente';
            if ($orig === 'unico') return 'unico';
        }

        return 'unico';
    }

    private function guessPeriodicity(array $snap, array $meta, Collection $items): string
    {
        $mode = strtolower((string) (data_get($snap, 'license.mode') ?? data_get($meta, 'license.mode') ?? ''));
        if (in_array($mode, ['mensual', 'anual'], true)) return $mode;

        foreach ($items as $it) {
            $im = $this->decodeJson($it->meta ?? null);
            $p  = strtolower((string) (data_get($im, 'periodicity') ?? ''));
            if (in_array($p, ['mensual', 'anual', 'unico'], true)) return $p;
        }

        return 'unico';
    }

    private function decodeJson(mixed $raw): array
    {
        if ($raw === null || $raw === '') return [];
        if (is_array($raw)) return $raw;

        $s = (string) $raw;
        $j = json_decode($s, true);
        return is_array($j) ? $j : [];
    }

    private function monthNameEs(int $m): string
    {
        return match ($m) {
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
            default => '',
        };
    }

    // ==========================
    // Orden de prioridad por source (UI/UX)
    // statement_line/statement deben dominar sobre sale
    // ==========================
    private function sourceSortWeight(?string $src): int
    {
        $s = strtolower(trim((string) $src));

        return match ($s) {
            'statement_line' => 10,
            'statement'      => 20,
            'projection'     => 30,
            'sale_pipeline'  => 80,
            'sale'           => 90,
            default          => 50,
        };
    }

    private function buildDescriptionFromItems(Collection $items, string $origin, string $periodicity): string
    {
        $origin      = strtolower($origin);
        $periodicity = strtolower($periodicity);

        if ($items->isEmpty()) {
            if ($origin === 'recurrente') {
                return $periodicity === 'anual' ? 'Recurrente Anual' : 'Recurrente Mensual';
            }
            return 'Venta Única';
        }

        $parts = $items
            ->map(function ($it) {
                $d = trim((string) ($it->description ?? ''));
                if ($d === '') $d = trim((string) ($it->code ?? ''));
                return $d;
            })
            ->filter()
            ->unique()
            ->values()
            ->take(3)
            ->all();

        $txt = implode(' · ', $parts);
        if ($txt === '') {
            if ($origin === 'recurrente') {
                return $periodicity === 'anual' ? 'Recurrente Anual' : 'Recurrente Mensual';
            }
            return 'Venta Única';
        }

        if ($origin === 'recurrente') {
            $prefix = $periodicity === 'anual' ? 'Recurrente Anual' : 'Recurrente Mensual';
            return $prefix . ' · ' . $txt;
        }

        return 'Venta Única · ' . $txt;
    }

    // =====================================================
    // BUILD (principal)
    // =====================================================
    public function build(Request $req): array
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $cli = (string) (config('p360.conn.clientes') ?: 'mysql_clientes');

        // -------------------------
        // Filtros base
        // -------------------------
        $year  = (int) ($req->input('year') ?: (int) now()->format('Y'));
        $month = (string) ($req->input('month') ?: 'all'); // 01..12 | all

        // Excel: Origen = recurrente | unico
        // Compat: UI vieja puede mandar no_recurrente
        $origin = strtolower((string) ($req->input('origin') ?: 'all')); // recurrente|unico|no_recurrente|all

        // -------------------------
        // Normalización robusta de filtros (UI nueva + legacy)
        // -------------------------
        $statusFilter = strtolower(trim((string) ($req->input('status') ?: $req->input('st') ?: 'all'))); // pending|emitido|pagado|vencido|all
        $invStRaw     = strtolower(trim((string) ($req->input('invoice_status') ?: $req->input('invSt') ?: 'all')));

        // ✅ Canonizador único invoice_status (entrada + rows)
        $canonInv = function ($val): string {
            $x = strtolower(trim((string) $val));
            if ($x === '' || in_array($x, ['all', 'todos', 'todas'], true)) return 'all';
            $x = str_replace([' ', '-'], '_', $x);

            return match ($x) {
                'sin_solicitud', 'no_solicitud', 'no_solicitada', 'none', 'no_request', 'sin' => 'sin_solicitud',
                'requested', 'solicitada', 'solicitado', 'request', 'enviada', 'enviado' => 'requested',
                'issued', 'facturada', 'facturado', 'timbrada', 'timbrado' => 'issued',
                'ready', 'en_proceso', 'procesando', 'preparando' => 'ready',
                'cancelled', 'canceled', 'rechazada', 'rechazado', 'cancelada', 'cancelado' => 'cancelled',
                default => $x,
            };
        };

        $invSt = $canonInv($invStRaw);
        $st    = $statusFilter;

        // Vendor: UI nueva vendor_id | UI legacy vendorId
        $vendorId = (string) ($req->input('vendor_id') ?: $req->input('vendorId') ?: 'all');
        $vendorId = trim($vendorId);
        if ($vendorId === '') $vendorId = 'all';

        // Search: UI nueva q | UI legacy qSearch
        $qSearch = (string) ($req->input('q') ?: $req->input('qSearch') ?: '');
        $qSearch = trim($qSearch);

        // flags
        $includeProjections = (int) ($req->input('include_projections') ?? 1) === 1;
        $includeSales       = (int) ($req->input('include_sales') ?? 1) === 1;

        // -------------------------
        // Periodos del año
        // -------------------------
        $periodFrom = Carbon::create($year, 1, 1)->startOfMonth();
        $periodTo   = Carbon::create($year, 12, 1)->endOfMonth();

        if (!Schema::connection($adm)->hasTable('billing_statements')) {
            return [
                'filters' => [
                    'year'   => $year,
                    'month'  => $month,
                    'origin' => $origin,
                    'st'     => $statusFilter,
                    'invSt'  => $invSt,
                    'vendorId' => $vendorId,
                    'qSearch'  => $qSearch,
                    'include_projections' => $includeProjections ? 1 : 0,
                    'include_sales'       => $includeSales ? 1 : 0,
                ],
                'kpis' => $this->blankKpis(),
                'rows' => collect(),
            ];
        }

        $periodsYear = [];
        for ($m = 1; $m <= 12; $m++) $periodsYear[] = sprintf('%04d-%02d', $year, $m);

        // filtro mes al universo de periodos
        if ($month !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            $periodsYear = [sprintf('%04d-%s', $year, $month)];
        }

        // DEBUG
        $dbg = [
            'year'  => $year,
            'month' => $month,
            'periodsYear_count' => count($periodsYear),

            'statements_raw_year' => 0,
            'statements_filtered_pre_dedupe' => 0,
            'statements_after_dedupe_year' => 0,
            'statements_filtered_post_dedupe' => 0,

            'statement_ids_count' => 0,
            'items_statement_ids_count' => 0,

            'payments_rows_period' => 0,
            'sales_rows_period' => 0,

            'rows_existing' => 0,
            'rows_projected' => 0,
            'rows_sales' => 0,
            'rows_total_before_filters' => 0,
            'rows_total_after_filters' => 0,

            'before_filters_by_source' => [],
            'before_filters_sample' => [],
            'after_filters_by_source' => [],
            'after_filters_sample' => [],

            'noFiltersHard' => 0,
            'filters_effective' => [],
        ];

        // -------------------------
        // Vendors
        // -------------------------
        $vendorsById = collect();
        if (Schema::connection($adm)->hasTable('finance_vendors')) {
            $vendorsById = collect(DB::connection($adm)->table('finance_vendors')
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get())
                ->keyBy(fn ($v) => (string) $v->id);
        }

        // -------------------------
        // Statements existentes (año/mes)
        // -------------------------
        $statementsAllYearQ = DB::connection($adm)->table('billing_statements as bs')
            ->select([
                'bs.id',
                'bs.account_id',
                'bs.period',
                'bs.total_cargo',
                'bs.total_abono',
                'bs.saldo',
                'bs.status',
                'bs.due_date',
                'bs.sent_at',
                'bs.paid_at',
                'bs.snapshot',
                'bs.meta',
                'bs.is_locked',
                'bs.created_at',
                'bs.updated_at',
            ])
            ->whereBetween('bs.period', [
                $periodFrom->format('Y-m'),
                $periodTo->format('Y-m'),
            ]);

        $statementsAllYear = collect($statementsAllYearQ->orderBy('bs.period')->orderBy('bs.id')->get());
        $dbg['statements_raw_year'] = $statementsAllYear->count();

        $statementsFiltered = $statementsAllYear;
        if ($month !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            $statementsFiltered = $statementsAllYear
                ->where('period', '=', sprintf('%04d-%s', $year, $month))
                ->values();
        }
        $dbg['statements_filtered_pre_dedupe'] = $statementsFiltered->count();

        // statementIds (año)
        $statementIdsAllYear = $statementsAllYear
            ->pluck('id')
            ->filter(fn ($x) => !empty($x))
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();
        $dbg['statement_ids_count'] = count($statementIdsAllYear);

        // statementIds (filtrado)
        $statementIdsFiltered = $statementsFiltered
            ->pluck('id')
            ->filter(fn ($x) => !empty($x))
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();

        // -------------------------
        // Items por statement
        // -------------------------
        $itemsByStatement = collect();
        if (Schema::connection($adm)->hasTable('billing_statement_items') && !empty($statementIdsAllYear)) {
            $itemsByStatement = DB::connection($adm)->table('billing_statement_items as bi')
                ->select([
                    'bi.id',
                    'bi.statement_id',
                    'bi.type',
                    'bi.code',
                    'bi.description',
                    'bi.qty',
                    'bi.unit_price',
                    'bi.amount',
                    'bi.ref',
                    'bi.meta',
                ])
                ->whereIn('bi.statement_id', $statementIdsAllYear)
                ->get()
                ->groupBy('statement_id');
        }
        $dbg['items_statement_ids_count'] = $itemsByStatement->count();

        // -------------------------
        // Invoices
        // -------------------------
        $invByStatement = collect();
        $invByAccPeriod = collect();

        if (Schema::connection($adm)->hasTable('billing_invoice_requests')) {
            $invAll = DB::connection($adm)->table('billing_invoice_requests')
                ->select([
                    'id',
                    'statement_id',
                    'account_id',
                    'period',
                    'status',
                    'cfdi_uuid',
                    'cfdi_folio',
                    'cfdi_url',
                    'requested_at',
                    'issued_at',
                    'zip_ready_at',
                    'zip_sent_at',
                    'meta',
                ])
                ->whereIn('period', $periodsYear)
                ->orderBy('id', 'desc')
                ->get();

            $invByStatement = $invAll->whereNotNull('statement_id')->groupBy('statement_id');
            $invByAccPeriod = $invAll->groupBy(fn ($r) => (string) $r->account_id . '|' . (string) $r->period);
        }

        // -------------------------
        // Billing profiles
        // -------------------------
        $profilesByAccountId = collect();
        $profilesByAdminAcc  = collect();

        if (Schema::connection($adm)->hasTable('finance_billing_profiles')) {
            $qProfiles = DB::connection($adm)->table('finance_billing_profiles')
                ->select([
                    'account_id',
                    'rfc_receptor',
                    'razon_social',
                    'email_cfdi',
                    'uso_cfdi',
                    'regimen_fiscal',
                    'cp_fiscal',
                    'forma_pago',
                    'metodo_pago',
                    'meta',
                ]);

            if (Schema::connection($adm)->hasColumn('finance_billing_profiles', 'admin_account_id')) {
                $qProfiles->addSelect('admin_account_id');
            }

            $profilesRaw = collect($qProfiles->get());

            $profilesByAccountId = $profilesRaw
                ->filter(fn ($r) => !empty($r->account_id))
                ->keyBy(fn ($r) => (string) $r->account_id);

            if ($profilesRaw->first() && property_exists($profilesRaw->first(), 'admin_account_id')) {
                $profilesByAdminAcc = $profilesRaw
                    ->filter(fn ($r) => !empty($r->admin_account_id))
                    ->keyBy(fn ($r) => (string) $r->admin_account_id);
            }
        }

        // -------------------------
        // Cuentas cliente (mysql_clientes)
        // -------------------------
        $hasActivo     = Schema::connection($cli)->hasColumn('cuentas_cliente', 'activo');
        $hasIsBlocked  = Schema::connection($cli)->hasColumn('cuentas_cliente', 'is_blocked');
        $hasEmail      = Schema::connection($cli)->hasColumn('cuentas_cliente', 'email');
        $hasTelefono   = Schema::connection($cli)->hasColumn('cuentas_cliente', 'telefono');
        $hasNextInv    = Schema::connection($cli)->hasColumn('cuentas_cliente', 'next_invoice_date');
        $hasCreatedAt  = Schema::connection($cli)->hasColumn('cuentas_cliente', 'created_at');
        $hasMeta       = Schema::connection($cli)->hasColumn('cuentas_cliente', 'meta');
        $hasEmpresa    = Schema::connection($cli)->hasColumn('cuentas_cliente', 'empresa');

        $accSelect = [
            'id',
            'admin_account_id',
            'rfc',
            'rfc_padre',
            'razon_social',
            'nombre_comercial',
            'plan_actual',
            'modo_cobro',
            'estado_cuenta',
        ];

        if ($hasEmpresa) $accSelect[] = 'empresa';

        $accSelect[] = $hasActivo ? 'activo' : DB::raw('1 as activo');
        $accSelect[] = $hasIsBlocked ? 'is_blocked' : DB::raw('0 as is_blocked');

        if ($hasEmail)     $accSelect[] = 'email';
        if ($hasTelefono)  $accSelect[] = 'telefono';
        if ($hasNextInv)   $accSelect[] = 'next_invoice_date';
        if ($hasCreatedAt) $accSelect[] = 'created_at';
        if ($hasMeta)      $accSelect[] = 'meta';

        // IDs desde statements
        $uuidIds = [];
        $numIds  = [];

        $accIds = $statementsAllYear->pluck('account_id')->filter()->unique()->values()->all();
        foreach ($accIds as $id) {
            $id = (string) $id;
            if ($id === '') continue;

            if (preg_match('/^[0-9a-f\-]{36}$/i', $id)) {
                $uuidIds[] = $id;
            } elseif (preg_match('/^\d+$/', $id)) {
                $numIds[] = (int) $id;
            }
        }

        $uuidIds = array_values(array_unique($uuidIds));
        $numIds  = array_values(array_unique($numIds));

        $qAcc = DB::connection($cli)->table('cuentas_cliente')->select($accSelect);

        // =========================
        // Detectar admin_account_id con PAYMENTS en el periodo (aunque la cuenta sea FREE)
        // =========================
        $payAdminIds = [];
        $payAdminIdsByPeriod = collect(); // key "YYYY-MM" => [adminId=>true]

        if (Schema::connection($adm)->hasTable('payments')) {
            try {
                $pQ = DB::connection($adm)->table('payments')
                    ->select(['account_id', 'period'])
                    ->whereIn('period', $periodsYear);

                $pRows = collect($pQ->get());

                $payAdminIds = $pRows
                    ->pluck('account_id')
                    ->filter(fn ($x) => $x !== null && $x !== '' && preg_match('/^\d+$/', (string) $x))
                    ->map(fn ($x) => (int) $x)
                    ->unique()
                    ->values()
                    ->all();

                foreach ($pRows as $r) {
                    $aid = trim((string) ($r->account_id ?? ''));
                    $per = trim((string) ($r->period ?? ''));
                    if ($aid === '' || $per === '') continue;
                    if (!preg_match('/^\d+$/', $aid)) continue;
                    if (!in_array($per, $periodsYear, true)) continue;

                    if (!$payAdminIdsByPeriod->has($per)) $payAdminIdsByPeriod->put($per, []);
                    $tmp = (array) $payAdminIdsByPeriod->get($per, []);
                    $tmp[$aid] = true;
                    $payAdminIdsByPeriod->put($per, $tmp);
                }
            } catch (\Throwable $e) {
                $payAdminIds = [];
                $payAdminIdsByPeriod = collect();
            }
        }

        $hasStatementFilter = (!empty($uuidIds) || !empty($numIds));

        // A) incluir explícitamente cuentas ligadas a statements
        // B) incluir recurrentes
        // C) incluir también cuentas con payments aunque sean FREE
        $qAcc->where(function ($w) use ($hasStatementFilter, $uuidIds, $numIds, $payAdminIds) {
            $added = false;

            if ($hasStatementFilter) {
                $w->where(function ($ww) use ($uuidIds, $numIds) {
                    if (!empty($uuidIds)) $ww->whereIn('id', $uuidIds);
                    if (!empty($numIds))  $ww->orWhereIn('admin_account_id', $numIds);
                });
                $added = true;
            }

            if (!empty($payAdminIds)) {
                if ($added) $w->orWhereIn('admin_account_id', $payAdminIds);
                else $w->whereIn('admin_account_id', $payAdminIds);
                $added = true;
            }

            if ($added) $w->orWhereIn('modo_cobro', ['mensual', 'anual']);
            else $w->whereIn('modo_cobro', ['mensual', 'anual']);
        });

        $cuentas = collect($qAcc->get())->map(function ($c) use ($hasEmail, $hasTelefono, $hasNextInv, $hasEmpresa) {
            if (!$hasEmail && !property_exists($c, 'email')) $c->email = null;
            if (!$hasTelefono && !property_exists($c, 'telefono')) $c->telefono = null;
            if (!$hasNextInv && !property_exists($c, 'next_invoice_date')) $c->next_invoice_date = null;
            if (!$hasEmpresa && !property_exists($c, 'empresa')) $c->empresa = null;
            return $c;
        });

        $cuentaByUuid    = $cuentas->keyBy(fn ($c) => (string) $c->id);
        $cuentaByAdminId = $cuentas->filter(fn ($c) => !empty($c->admin_account_id))
            ->keyBy(fn ($c) => (string) $c->admin_account_id);

        // -------------------------
        // DEDUPE statements (UUID/NUM)
        // -------------------------
        $statementsAllYear = $statementsAllYear->map(function ($s) use ($adm, $cuentaByUuid, $cuentaByAdminId) {
            $sid = (string) ($s->account_id ?? '');

            $cc = $this->resolveCuentaClienteFromStatementAccountId(
                $adm,
                $sid,
                $cuentaByUuid,
                $cuentaByAdminId
            );

            $s->_canon_account = $this->normalizeAccountKey($sid, $cc);
            return $s;
        });

        $groups = $statementsAllYear->groupBy(function ($s) {
            return (string) ($s->_canon_account ?? '0') . '|' . (string) ($s->period ?? '');
        });

        $dedup = $groups->map(function ($g) use ($itemsByStatement) {
            return $this->pickBestStatement($g, $itemsByStatement);
        })->filter()->values();

        $statementsAllYear = $dedup
            ->sortBy([
                fn ($s) => (string) ($s->period ?? ''),
                fn ($s) => (int) ($s->id ?? 0),
            ])
            ->values();

        $statementsFiltered = $statementsAllYear;
        if ($month !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            $statementsFiltered = $statementsAllYear
                ->where('period', '=', sprintf('%04d-%s', $year, $month))
                ->values();
        }

        $statements = $statementsFiltered;

        $dbg['statements_after_dedupe_year']     = $statementsAllYear->count();
        $dbg['statements_filtered_post_dedupe']  = $statements->count();

        // -------------------------
        // statementKeyHash (para shadowing/exclusión de proyecciones/ventas)
        // -------------------------
        $statementKeySet = collect();
        foreach ($statementsAllYear as $s) {
            $acc = (string) ($s->_canon_account ?? $s->account_id);
            $per = (string) ($s->period ?? '');
            if ($acc !== '' && $per !== '') $statementKeySet->push($acc . '|' . $per);
        }

        foreach ($statementsAllYear as $s) {
            $acc = (string) ($s->account_id ?? '');
            $per = (string) ($s->period ?? '');
            if ($acc === '' || $per === '') continue;

            if (preg_match('/^[0-9a-f\-]{36}$/i', $acc)) {
                $cc = $cuentaByUuid->get($acc);
                if ($cc && !empty($cc->admin_account_id)) {
                    $statementKeySet->push((string) $cc->admin_account_id . '|' . $per);
                }
            }

            if (preg_match('/^\d+$/', $acc)) {
                $cc = $cuentaByAdminId->get($acc);
                if ($cc && !empty($cc->id)) {
                    $statementKeySet->push((string) $cc->id . '|' . $per);
                }
            }
        }

        $statementKeyHash = array_fill_keys($statementKeySet->all(), true);

        // Shadowing set (solo mes filtrado si aplica)
        $hasStatementByAccPeriod = [];
        foreach ($statementsFiltered as $s) {
            $canon = (string) ($s->_canon_account ?? $s->account_id ?? '');
            $per   = (string) ($s->period ?? '');
            if ($canon !== '' && $per !== '') {
                $hasStatementByAccPeriod[$canon . '|' . $per] = true;
            }
        }

        foreach ($statementsFiltered as $s) {
            $acc = (string) ($s->account_id ?? '');
            $per = (string) ($s->period ?? '');
            if ($acc === '' || $per === '') continue;

            if (preg_match('/^[0-9a-f\-]{36}$/i', $acc)) {
                $cc = $cuentaByUuid->get($acc);
                if ($cc && !empty($cc->admin_account_id)) {
                    $hasStatementByAccPeriod[(string) $cc->admin_account_id . '|' . $per] = true;
                }
            }

            if (preg_match('/^\d+$/', $acc)) {
                $cc = $cuentaByAdminId->get($acc);
                if ($cc && !empty($cc->id)) {
                    $hasStatementByAccPeriod[(string) $cc->id . '|' . $per] = true;
                }
            }
        }

        // -------------------------
        // Payments agg
        // -------------------------
        $paymentsAggByAdminAccPeriod = collect(); // key "adminId|YYYY-MM" => object agg
        $lastPaymentByAdminAcc       = collect(); // key "adminId" => last paid row

        if (Schema::connection($adm)->hasTable('payments')) {
            $pHasStatus  = $this->hasCol($adm, 'payments', 'status');
            $pHasMethod  = $this->hasCol($adm, 'payments', 'method');
            $pHasPaidAt  = $this->hasCol($adm, 'payments', 'paid_at');
            $pHasMeta    = $this->hasCol($adm, 'payments', 'meta');
            $pHasCreated = $this->hasCol($adm, 'payments', 'created_at');
            $pHasAmount  = $this->hasCol($adm, 'payments', 'amount');

            try {
                $pQ = DB::connection($adm)->table('payments')
                    ->select(array_filter([
                        'id',
                        'account_id',
                        'period',
                        $pHasAmount ? 'amount' : null,
                        $pHasStatus ? 'status' : null,
                        $pHasMethod ? 'method' : null,
                        $pHasPaidAt ? 'paid_at' : null,
                        $pHasMeta ? 'meta' : null,
                        $pHasCreated ? 'created_at' : null,
                    ]))
                    ->whereIn('period', $periodsYear);

                $pRows = collect($pQ->orderByDesc('id')->get());
                $dbg['payments_rows_period'] = $pRows->count();

                $agg = [];
                foreach ($pRows as $p) {
                    $aid = trim((string) ($p->account_id ?? ''));
                    $per = trim((string) ($p->period ?? ''));
                    if ($aid === '' || $per === '') continue;
                    if (!preg_match('/^\d+$/', $aid)) continue;

                    $key = $aid . '|' . $per;

                    if (!isset($agg[$key])) {
                        $agg[$key] = (object) [
                            'admin_account_id' => (int) $aid,
                            'period' => $per,
                            'sum_amount_mxn' => 0.0,
                            'any_paid' => 0,
                            'paid_at' => null,
                            'status' => null,
                            'method' => null,
                            'last_payment_id' => null,
                        ];
                    }

                    $stp = strtolower(trim((string) ($p->status ?? '')));
                    $isPaid = in_array($stp, ['paid', 'pagado', 'succeeded', 'success', 'complete', 'completed'], true);

                    $amtCents = (float) ($p->amount ?? 0);
                    $amtMxn   = $amtCents > 0 ? ($amtCents / 100) : 0.0;

                    $agg[$key]->sum_amount_mxn += $amtMxn;
                    if ($isPaid) $agg[$key]->any_paid = 1;

                    $pPaidAt = $pHasPaidAt ? ($p->paid_at ?? null) : null;
                    if (empty($pPaidAt) && $pHasCreated) $pPaidAt = $p->created_at ?? null;

                    if ($isPaid && !empty($pPaidAt)) {
                        if (empty($agg[$key]->paid_at) || (string) $pPaidAt > (string) $agg[$key]->paid_at) {
                            $agg[$key]->paid_at = $pPaidAt;
                        }
                    }

                    if ($agg[$key]->last_payment_id === null || (int) ($p->id ?? 0) > (int) ($agg[$key]->last_payment_id ?? 0)) {
                        $agg[$key]->last_payment_id = (int) ($p->id ?? 0);
                        $agg[$key]->status = $pHasStatus ? (string) ($p->status ?? '') : null;
                        $agg[$key]->method = $pHasMethod ? (string) ($p->method ?? '') : null;
                    }
                }

                $paymentsAggByAdminAccPeriod = collect($agg);

                $lastPaid = [];
                foreach ($pRows as $p) {
                    $aid = trim((string) ($p->account_id ?? ''));
                    if ($aid === '' || !preg_match('/^\d+$/', $aid)) continue;

                    $stp = strtolower(trim((string) ($p->status ?? '')));
                    $isPaid = in_array($stp, ['paid', 'pagado', 'succeeded', 'success', 'complete', 'completed'], true);
                    if (!$isPaid) continue;

                    $pPaidAt = $pHasPaidAt ? ($p->paid_at ?? null) : null;
                    if (empty($pPaidAt) && $pHasCreated) $pPaidAt = $p->created_at ?? null;

                    if (!isset($lastPaid[$aid])) { $lastPaid[$aid] = $p; continue; }

                    $cur = $lastPaid[$aid];
                    $curPaidAt = $pHasPaidAt ? ($cur->paid_at ?? null) : null;
                    if (empty($curPaidAt) && $pHasCreated) $curPaidAt = $cur->created_at ?? null;

                    if (!empty($pPaidAt) && (empty($curPaidAt) || (string) $pPaidAt > (string) $curPaidAt)) {
                        $lastPaid[$aid] = $p;
                        continue;
                    }

                    if ((int) ($p->id ?? 0) > (int) ($cur->id ?? 0)) {
                        $lastPaid[$aid] = $p;
                    }
                }

                $lastPaymentByAdminAcc = collect($lastPaid);
            } catch (\Throwable $e) {
                $paymentsAggByAdminAccPeriod = collect();
                $lastPaymentByAdminAcc = collect();
            }
        }

        // -------------------------
        // Vendor assignment fallback
        // -------------------------
        $vendorAssignByAdminAcc = collect();
        if (Schema::connection($adm)->hasTable('finance_account_vendor') && $cuentas->isNotEmpty()) {
            $adminIds = $cuentas->pluck('admin_account_id')->filter()->unique()->values()->all();

            if (!empty($adminIds)) {
                $assignRows = DB::connection($adm)->table('finance_account_vendor as fav')
                    ->leftJoin('finance_vendors as v', 'v.id', '=', 'fav.vendor_id')
                    ->select([
                        'fav.id',
                        'fav.account_id',
                        'fav.client_uuid',
                        'fav.vendor_id',
                        'fav.starts_on',
                        'fav.ends_on',
                        'fav.is_primary',
                        'v.name as vendor_name',
                        'fav.created_at',
                    ])
                    ->whereIn('fav.account_id', $adminIds)
                    ->orderByDesc('fav.is_primary')
                    ->orderByDesc('fav.starts_on')
                    ->orderByDesc('fav.id')
                    ->get();

                $best = [];
                foreach ($assignRows as $a) {
                    $aid = (string) $a->account_id;
                    if (!isset($best[$aid])) { $best[$aid] = $a; continue; }

                    $cur = $best[$aid];
                    $curPrimary = (int) ($cur->is_primary ?? 0);
                    $newPrimary = (int) ($a->is_primary ?? 0);

                    if ($newPrimary > $curPrimary) { $best[$aid] = $a; continue; }

                    $curStart = (string) ($cur->starts_on ?? '');
                    $newStart = (string) ($a->starts_on ?? '');
                    if ($newStart !== '' && ($curStart === '' || $newStart > $curStart)) {
                        $best[$aid] = $a; continue;
                    }
                }

                $vendorAssignByAdminAcc = collect($best);
            }
        }

                // -------------------------
        // Admin accounts (SOT Billing para expected_total / meta)
        // -------------------------
        $adminAccountsById = collect();

        if (Schema::connection($adm)->hasTable('accounts')) {
            $accIdsForAdmin = collect();

            $accIdsForAdmin = $accIdsForAdmin
                ->merge($cuentas->pluck('admin_account_id')->filter())
                ->merge($statementsAllYear->pluck('_canon_account')->filter(fn ($x) => preg_match('/^\d+$/', (string) $x)))
                ->merge($statementsAllYear->pluck('account_id')->filter(fn ($x) => preg_match('/^\d+$/', (string) $x)))
                ->unique()
                ->values();

            if ($accIdsForAdmin->isNotEmpty()) {
                $accCols = Schema::connection($adm)->getColumnListing('accounts');
                $alc = array_map('strtolower', $accCols);
                $ahas = static fn (string $c): bool => in_array(strtolower($c), $alc, true);

                $accSelect = ['id'];
                foreach ([
                    'email',
                    'name',
                    'razon_social',
                    'rfc',
                    'plan',
                    'plan_actual',
                    'modo_cobro',
                    'billing_cycle',
                    'meta',
                    'created_at',
                    'billing_amount_mxn',
                    'amount_mxn',
                    'precio_mxn',
                    'monto_mxn',
                    'override_amount_mxn',
                    'custom_amount_mxn',
                    'license_amount_mxn',
                    'billing_amount',
                    'amount',
                    'precio',
                    'monto',
                ] as $c) {
                    if ($ahas($c)) $accSelect[] = $c;
                }

                $adminAccountsById = collect(DB::connection($adm)->table('accounts')
                    ->select(array_values(array_unique($accSelect)))
                    ->whereIn('id', $accIdsForAdmin->all())
                    ->get())
                    ->keyBy(fn ($r) => (string) $r->id);
            }
        }

        // -------------------------
        // Billing SOT by admin_account_id|period
        // -------------------------
        $billingSotByAdminAccPeriod = $this->buildBillingSotByAdminAccPeriod(
            adm: $adm,
            adminAccountsById: $adminAccountsById,
            periodsYear: $periodsYear,
            paymentsAggByAdminAccPeriod: $paymentsAggByAdminAccPeriod
        );

        // -------------------------
        // 1) ROWS EXISTING + 2) PROYECCIONES
        // ✅ SOT REAL = estados_cuenta + payments + accounts/meta
        // -------------------------
        $rowsExisting = collect();
        $rowsProjected = collect();
        $statementLineSaleIdSet = []; // ya no aplica aquí, pero lo dejamos por compat

        $ledgerBuild = $this->buildRowsFromBillingLedgerSot(
            adm: $adm,
            periodsYear: $periodsYear,
            cuentas: $cuentas,
            profilesByAccountId: $profilesByAccountId,
            profilesByAdminAcc: $profilesByAdminAcc,
            paymentsAggByAdminAccPeriod: $paymentsAggByAdminAccPeriod,
            vendorsById: $vendorsById,
            vendorAssignByAdminAcc: $vendorAssignByAdminAcc,
            invByAccPeriod: $invByAccPeriod
        );

        $rowsExisting = collect($ledgerBuild['existing'] ?? []);
        $rowsProjected = $includeProjections
            ? collect($ledgerBuild['projected'] ?? [])
            : collect();

        // -------------------------
        // 3) Ventas únicas (finance_sales) con anti-doble conteo + shadowing
        // -------------------------
        $rowsSales = collect();

        if ($includeSales && Schema::connection($adm)->hasTable('finance_sales')) {

            $hasPeriod       = $this->hasColSql($adm, 'finance_sales', 'period');
            $hasTargetPeriod = $this->hasColSql($adm, 'finance_sales', 'target_period');
            $hasStmtTarget   = $this->hasColSql($adm, 'finance_sales', 'statement_period_target');

            $colPeriod = $hasPeriod ? 'period' : ($hasTargetPeriod ? 'target_period' : null);
            $colStmtTarget = $hasStmtTarget ? 'statement_period_target' : ($hasTargetPeriod ? 'target_period' : null);

            $qSales = DB::connection($adm)->table('finance_sales as s')
                ->leftJoin('finance_vendors as v', 'v.id', '=', 's.vendor_id');

            $qSales->select([
                's.id',
                's.account_id',
                's.sale_code',
                's.receiver_rfc',
                's.pay_method',
                's.origin',
                's.periodicity',
                's.vendor_id',
                'v.name as vendor_name',
                's.sale_date',
                's.f_cta',
                's.f_mov',
                's.invoice_date',
                's.paid_date',
                's.subtotal',
                's.iva',
                's.total',
                's.statement_status',
                's.invoice_status',
                's.cfdi_uuid',
                's.invoice_uuid',
                's.include_in_statement',
                's.notes',
                's.created_at',
            ]);

            if ($colPeriod) {
                $qSales->addSelect(DB::raw("s.`{$colPeriod}` as period"));
            } else {
                $qSales->addSelect(DB::raw("DATE_FORMAT(COALESCE(s.sale_date, s.created_at), '%Y-%m') as period"));
            }

            if ($colStmtTarget) {
                $qSales->addSelect(DB::raw("s.`{$colStmtTarget}` as statement_period_target"));
            } else {
                $qSales->addSelect(DB::raw("NULL as statement_period_target"));
            }

            if ($colPeriod) {
                $qSales->whereIn("s.$colPeriod", $periodsYear);
            } else {
                $placeholders = implode(',', array_fill(0, count($periodsYear), '?'));
                $qSales->whereRaw(
                    "DATE_FORMAT(COALESCE(s.sale_date, s.created_at), '%Y-%m') IN ({$placeholders})",
                    $periodsYear
                );
            }

            $sales = collect($qSales->orderBy('period')->orderBy('s.id')->get());
            $dbg['sales_rows_period'] = $sales->count();

            $sales = $this->attachCompanyFromClientes($sales, $cli);

            // anti-dup: si ya vino en líneas, o si se incluyó en statement del periodo
            $sales = $sales->filter(function ($s) use ($statementKeyHash, $statementLineSaleIdSet) {
                $sid = (int) ($s->id ?? 0);
                if ($sid > 0 && isset($statementLineSaleIdSet[$sid])) return false;

                $aid = (string) ($s->account_id ?? '');
                if ($aid === '') return true;

                $inc = (int) ($s->include_in_statement ?? 0);
                if ($inc !== 1) return true;

                $perTarget = (string) ($s->statement_period_target ?? '');
                $per       = (string) ($s->period ?? '');

                if ($perTarget !== '' && isset($statementKeyHash[$aid . '|' . $perTarget])) return false;
                if ($per !== '' && isset($statementKeyHash[$aid . '|' . $per])) return false;

                return true;
            })->values();

            $rowsSales = $sales->map(function ($s) use (
                $vendorAssignByAdminAcc,
                $vendorsById,
                $invByAccPeriod,
                $canonInv,
                $hasStatementByAccPeriod
            ) {
                $per = (string) ($s->period ?? '');
                $y   = (int) substr($per, 0, 4);
                $m   = (int) substr($per, 5, 2);

                $accKey    = (string) ($s->account_id ?? '');
                $shadowKey = ($accKey !== '' && $per !== '') ? ($accKey . '|' . $per) : '';
                $hasStatementForThis = ($shadowKey !== '' && isset($hasStatementByAccPeriod[$shadowKey]));

                $saleSource = $hasStatementForThis ? 'sale_pipeline' : 'sale';
                $excludeFromKpi = $hasStatementForThis ? 1 : 0;

                $origin = strtolower((string) ($s->origin ?? ''));
                if ($origin === 'no_recurrente') $origin = 'unico';
                if (!in_array($origin, ['recurrente', 'unico'], true)) $origin = 'unico';

                $periodicity = strtolower((string) ($s->periodicity ?? ''));
                if (!in_array($periodicity, ['mensual', 'anual', 'unico'], true)) $periodicity = 'unico';

                $ecStatus = strtolower((string) ($s->statement_status ?? 'pending'));
                if (!in_array($ecStatus, ['pending', 'emitido', 'pagado', 'vencido'], true)) $ecStatus = 'pending';

                $invRow = null;
                if ($per !== '' && $accKey !== '') {
                    $invRow = optional($invByAccPeriod->get($accKey . '|' . $per))->first();
                }

                $invRaw = $invRow ? (string) ($invRow->status ?? '') : (string) ($s->invoice_status ?? '');
                $invCanonical = $canonInv($invRaw);

                $invoiceDate = $s->invoice_date ?: ($invRow?->issued_at ?: null);

                $uuidFromSale = trim((string) ($s->cfdi_uuid ?? ''));
                if ($uuidFromSale === '') $uuidFromSale = trim((string) ($s->invoice_uuid ?? ''));

                if ($uuidFromSale === '' && $invRow) {
                    $uuidFromSale = trim((string) ($invRow->cfdi_uuid ?? ''));
                    if ($uuidFromSale === '') {
                        $invMeta = $this->decodeJson($invRow->meta ?? null);
                        $uuidFromSale = trim((string) (
                            data_get($invMeta, 'cfdi_uuid')
                            ?? data_get($invMeta, 'uuid')
                            ?? data_get($invMeta, 'cfdi.uuid')
                            ?? data_get($invMeta, 'cfdi.UUID')
                            ?? ''
                        ));
                    }
                }

                $notes    = trim((string) ($s->notes ?? ''));
                $saleCode = trim((string) ($s->sale_code ?? ''));

                if ($notes === '') {
                    $desc = $saleCode !== '' ? $saleCode : (
                        $origin === 'recurrente'
                            ? (($periodicity === 'anual') ? 'Recurrente Anual' : 'Recurrente Mensual')
                            : 'Venta Única'
                    );
                } else {
                    $desc = ($saleCode !== '') ? ($saleCode . ' · ' . $notes) : $notes;
                }

                $fMov = $s->f_mov ?: ($s->sale_date ?: ($s->created_at ?: null));

                // Vendor fallback
                $vendorId   = !empty($s->vendor_id) ? (string) $s->vendor_id : null;
                $vendorName = !empty($s->vendor_name) ? (string) $s->vendor_name : null;

                if ((!$vendorId || !$vendorName) && $accKey !== '' && preg_match('/^\d+$/', $accKey)) {
                    $as = $vendorAssignByAdminAcc->get($accKey);
                    if ($as) {
                        $perDate = $per !== '' ? ($per . '-01') : null;
                        $ok = true;

                        if ($perDate) {
                            $stOn = (string) ($as->starts_on ?? '');
                            $enOn = (string) ($as->ends_on ?? '');
                            if ($stOn !== '' && $perDate < $stOn) $ok = false;
                            if ($enOn !== '' && $perDate > $enOn) $ok = false;
                        }

                        if ($ok) {
                            if (!$vendorId)   $vendorId   = (string) ($as->vendor_id ?? $vendorId);
                            if (!$vendorName) $vendorName = (string) ($as->vendor_name ?? $vendorName);
                        }
                    }
                }

                if ($vendorId && !$vendorName && $vendorsById->isNotEmpty() && $vendorsById->has($vendorId)) {
                    $vendorName = (string) ($vendorsById->get($vendorId)?->name ?? null);
                }

                return (object) [
                    'source'        => $saleSource, // sale | sale_pipeline
                    'is_projection' => 0,

                    'has_statement'    => $hasStatementForThis ? 1 : 0,
                    'exclude_from_kpi' => $excludeFromKpi,

                    'year'       => $y,
                    'month_num'  => sprintf('%02d', $m),
                    'month_name' => $this->monthNameEs($m),

                    'vendor_id' => $vendorId,
                    'vendor'    => $vendorName,

                    'client'      => (string) ($s->company ?? ('Cuenta ' . $s->account_id)),
                    'description' => $desc,

                    'period'     => $per,
                    'account_id' => (string) ($s->account_id ?? ''),
                    'company'    => (string) ($s->company ?? ('Cuenta ' . $s->account_id)),
                    'rfc_emisor' => (string) ($s->rfc_emisor ?? ''),

                    'origin'      => $origin,
                    'periodicity' => $periodicity,

                    'subtotal' => (float) ($s->subtotal ?? 0),
                    'iva'      => (float) ($s->iva ?? 0),
                    'total'    => (float) ($s->total ?? 0),

                    'ec_status' => $ecStatus,
                    'due_date'  => null,
                    'sent_at'   => null,
                    'paid_at'   => $s->paid_date ?: null,

                    'rfc_receptor' => (string) ($s->receiver_rfc ?? ''),
                    'forma_pago'   => (string) ($s->pay_method ?? ''),

                    'f_emision' => $s->f_cta ?: null,
                    'f_pago'    => $s->paid_date ?: null,
                    'f_cta'     => $s->f_cta ?: null,
                    'f_mov'     => $fMov,

                    'f_factura'          => $invoiceDate,
                    'invoice_date'       => $invoiceDate,
                    'invoice_status'     => ($invCanonical !== 'all' ? $invCanonical : null),
                    'invoice_status_raw' => ($invRaw !== '' ? $invRaw : null),
                    'cfdi_uuid'          => ($uuidFromSale !== '' ? $uuidFromSale : null),

                    'payment_method' => null,
                    'payment_status' => null,

                    'sale_id' => (int) ($s->id ?? 0),
                    'include_in_statement' => (int) ($s->include_in_statement ?? 0),
                    'statement_period_target' => $s->statement_period_target ?: null,
                    'notes' => (string) ($s->notes ?? ''),
                ];
            });
        }

        // -------------------------
        // Unión
        // -------------------------
        $rows = $rowsExisting
            ->concat($rowsProjected)
            ->concat($rowsSales);

        // -------------------------
        // Normalización final de flags (blindaje)
        // - statement_line/statement => has_statement=1, exclude_from_kpi=0
        // - projection               => exclude_from_kpi=1
        // - sale_pipeline            => has_statement=1, exclude_from_kpi=1
        // -------------------------
               $rows = $rows->map(function ($r) use ($billingSotByAdminAccPeriod, $cuentaByUuid, $cuentaByAdminId) {
            $src = strtolower(trim((string) ($r->source ?? '')));

            if (!property_exists($r, 'exclude_from_kpi')) $r->exclude_from_kpi = 0;
            if (!property_exists($r, 'has_statement'))    $r->has_statement    = 0;

            if (in_array($src, ['statement', 'statement_line'], true)) {
                $r->has_statement = 1;
                $r->exclude_from_kpi = 0;
            }

            if ($src === 'projection') {
                $r->exclude_from_kpi = 1;
            }

            if ($src === 'sale_pipeline') {
                $r->has_statement = 1;
                $r->exclude_from_kpi = 1;
            }

            // =====================================================
            // ✅ SOT Billing reconciliation
            // statement / statement_line / projection
            // total mostrado = estados_cuenta.cargo o expected_total desde accounts/meta
            // =====================================================
            if (in_array($src, ['statement', 'statement_line', 'projection'], true)) {
                $per = trim((string) ($r->period ?? ''));
                $adminAccId = '';

                $aid = trim((string) ($r->account_id ?? ''));
                if ($aid !== '' && preg_match('/^\d+$/', $aid)) {
                    $adminAccId = $aid;
                }

                if ($adminAccId === '') {
                    $aidRaw = trim((string) ($r->account_id_raw ?? ''));
                    if ($aidRaw !== '' && preg_match('/^\d+$/', $aidRaw)) {
                        $adminAccId = $aidRaw;
                    }
                }

                if ($adminAccId === '') {
                    $clientUuid = trim((string) ($r->client_account_id ?? ''));
                    if ($clientUuid !== '' && $cuentaByUuid->has($clientUuid)) {
                        $cc = $cuentaByUuid->get($clientUuid);
                        if (!empty($cc?->admin_account_id)) {
                            $adminAccId = (string) $cc->admin_account_id;
                        }
                    }
                }

                if ($adminAccId === '' && $aid !== '' && $cuentaByAdminId->has($aid)) {
                    $cc = $cuentaByAdminId->get($aid);
                    if (!empty($cc?->admin_account_id)) {
                        $adminAccId = (string) $cc->admin_account_id;
                    }
                }

                if ($adminAccId !== '' && $per !== '') {
                    $sot = $billingSotByAdminAccPeriod->get($adminAccId . '|' . $per);

                    if ($sot) {
                        $r->account_id = $adminAccId;

                        $r->subtotal = round((float) ($sot->subtotal ?? 0), 2);
                        $r->iva      = round((float) ($sot->iva ?? 0), 2);
                        $r->total    = round((float) ($sot->total ?? 0), 2);

                        if ($src !== 'projection') {
                            $r->ec_status = (string) ($sot->ec_status ?? ($r->ec_status ?? 'pending'));
                        } else {
                            // si ya existe cargo real en Billing para ese periodo, deja de verse como proyección “falsa”
                            if ((float) ($sot->cargo_real ?? 0) > 0.00001) {
                                $r->ec_status = (string) ($sot->ec_status ?? 'pending');
                            }
                        }

                        if (empty($r->paid_at) && !empty($sot->paid_at)) {
                            $r->paid_at = $sot->paid_at;
                        }
                        if (empty($r->f_pago) && !empty($sot->paid_at)) {
                            $r->f_pago = $sot->paid_at;
                        }
                    }
                }
            }

            return $r;
        });

        $dbg['rows_existing']  = $rowsExisting->count();
        $dbg['rows_projected'] = $rowsProjected->count();
        $dbg['rows_sales']     = $rowsSales->count();

        $dbg['rows_total_before_filters'] = $rows->count();
        $dbg['before_filters_by_source'] = $rows->groupBy('source')->map->count()->all();
        $dbg['before_filters_sample'] = $rows->take(12)->map(fn ($r) => [
            'source' => $r->source ?? null,
            'period' => $r->period ?? null,
            'client' => $r->client ?? ($r->company ?? null),
            'origin' => $r->origin ?? null,
            'ec_status' => $r->ec_status ?? null,
            'invoice_status' => $r->invoice_status ?? null,
            'vendor_id' => $r->vendor_id ?? null,
            'account_id' => $r->account_id ?? null,
            'subtotal' => $r->subtotal ?? null,
        ])->values()->all();

        // -------------------------
        // Apply overrides (solo statement/projection/statement_line)
        // -------------------------
        if (Schema::connection($adm)->hasTable('finance_income_overrides') && $rows->isNotEmpty()) {
            $ovAccountIds = $rows
                ->filter(fn ($r) => in_array((string) ($r->source ?? ''), ['statement', 'projection', 'statement_line'], true))
                ->pluck('account_id')
                ->filter(fn ($x) => (string) $x !== '')
                ->unique()
                ->values()
                ->all();

            $ovPeriods = $rows
                ->filter(fn ($r) => in_array((string) ($r->source ?? ''), ['statement', 'projection', 'statement_line'], true))
                ->pluck('period')
                ->filter(fn ($x) => (string) $x !== '')
                ->unique()
                ->values()
                ->all();

            if (!empty($ovAccountIds) && !empty($ovPeriods)) {
                $ovRows = collect(DB::connection($adm)->table('finance_income_overrides')
                    ->select([
                        'row_type', 'account_id', 'period',
                        'vendor_id', 'ec_status', 'invoice_status', 'cfdi_uuid', 'rfc_receptor', 'forma_pago',
                        'subtotal', 'iva', 'total', 'notes',
                        'updated_at', 'updated_by',
                    ])
                    ->whereIn('account_id', $ovAccountIds)
                    ->whereIn('period', $ovPeriods)
                    ->whereIn('row_type', ['statement', 'projection', 'statement_line'])
                    ->get());

                $ovByKey = $ovRows->keyBy(fn ($o) => (string) $o->row_type . '|' . (string) $o->account_id . '|' . (string) $o->period);

                $rows = $rows->map(function ($r) use ($ovByKey, $vendorsById) {
                $src = (string) ($r->source ?? '');
                $accountId = (string) ($r->account_id ?? '');
                $period    = (string) ($r->period ?? '');

                $rowType = match ($src) {
                    'projection'     => 'projection',
                    'statement_line' => 'statement_line',
                    default          => 'statement',
                };

                $key = $rowType . '|' . $accountId . '|' . $period;
                $ov  = $ovByKey->get($key);

                // Compat importante:
                // el controlador hoy guarda statement_line como row_type=statement,
                // así que si no existe statement_line, intentamos statement.
                if (!$ov && $rowType === 'statement_line') {
                    $ov = $ovByKey->get('statement|' . $accountId . '|' . $period);
                }

                if (!$ov) return $r;

                $apply = function ($prop, $val) use ($r) {
                    if ($val === null) return;
                    $r->{$prop} = $val;
                };

                $apply('vendor_id', $ov->vendor_id !== null ? (string) $ov->vendor_id : null);
                $apply('ec_status', $ov->ec_status !== null ? (string) $ov->ec_status : null);
                $apply('invoice_status', $ov->invoice_status !== null ? (string) $ov->invoice_status : null);
                $apply('invoice_status_raw', $ov->invoice_status !== null ? (string) $ov->invoice_status : null);
                $apply('cfdi_uuid', $ov->cfdi_uuid !== null ? (string) $ov->cfdi_uuid : null);
                $apply('rfc_receptor', $ov->rfc_receptor !== null ? (string) $ov->rfc_receptor : null);
                $apply('forma_pago', $ov->forma_pago !== null ? (string) $ov->forma_pago : null);

                if ($ov->subtotal !== null) $r->subtotal = round((float) $ov->subtotal, 2);
                if ($ov->iva !== null)      $r->iva      = round((float) $ov->iva, 2);
                if ($ov->total !== null)    $r->total    = round((float) $ov->total, 2);

                if ($ov->notes !== null) {
                    $r->notes = (string) $ov->notes;
                }

                $vid = (string) ($r->vendor_id ?? '');
                if ($vid !== '' && $vendorsById->isNotEmpty() && $vendorsById->has($vid)) {
                    $r->vendor = (string) ($vendorsById->get($vid)?->name ?? ($r->vendor ?? null));
                }

                $r->has_override = 1;

                return $r;
            });
            }
        }

        // -------------------------
        // Filtros
        // -------------------------
        $originNorm = $origin;
        if ($originNorm === 'no_recurrente') $originNorm = 'unico';

        $dbg['filters_effective'] = [
            'origin'     => (string) $origin,
            'originNorm' => (string) $originNorm,
            'status'     => (string) $statusFilter,
            'invSt'      => (string) $invSt,
            'vendorId'   => (string) $vendorId,
            'qSearch'    => (string) $qSearch,
        ];

        $stNorm0    = strtolower(trim((string) $statusFilter));
        $invNorm0   = strtolower(trim((string) $invSt));
        $vendorNorm = (string) $vendorId;
        $qNorm      = trim((string) $qSearch);

        $noFiltersHard =
            ($originNorm === 'all') &&
            ($stNorm0 === '' || in_array($stNorm0, ['all', 'todos', 'todas'], true)) &&
            ($invNorm0 === '' || in_array($invNorm0, ['all', 'todos', 'todas'], true)) &&
            ($vendorNorm === '' || $vendorNorm === 'all') &&
            ($qNorm === '');

        $dbg['noFiltersHard'] = $noFiltersHard ? 1 : 0;

        if (!$noFiltersHard) {
            $rows = $rows->filter(function ($r) use ($originNorm, $st, $invSt, $vendorId, $qSearch, $canonInv) {
                $rowOrigin = strtolower(trim((string) ($r->origin ?? '')));
                $rowStatus = strtolower(trim((string) ($r->ec_status ?? '')));

                if ($originNorm !== 'all' && $rowOrigin !== $originNorm) return false;

                $stNorm = strtolower(trim((string) $st));
                if ($stNorm !== '' && !in_array($stNorm, ['all', 'todos', 'todas'], true)) {
                    $rowStatus = match ($rowStatus) {
                        'paid' => 'pagado',
                        'sent' => 'emitido',
                        'overdue' => 'vencido',
                        'pendiente' => 'pending',
                        default => $rowStatus,
                    };
                    if ($rowStatus !== $stNorm) return false;
                }

                $invNorm = $canonInv($invSt);
                if ($invNorm !== 'all') {
                    $cmp = (string) ($r->invoice_status ?? '');
                    if (trim($cmp) === '') $cmp = (string) ($r->invoice_status_raw ?? '');
                    $cmp = $canonInv($cmp);
                    if ($cmp !== $invNorm) return false;
                }

                $v = (string) $vendorId;
                if ($v !== '' && $v !== 'all') {
                    $rid = (string) ($r->vendor_id ?? '');
                    if ($rid === '' || $rid !== $v) return false;
                }

                $q = trim((string) $qSearch);
                if ($q !== '') {
                    $hay = strtolower(
                        (string) ($r->client ?? '') . ' ' .
                        (string) ($r->company ?? '') . ' ' .
                        (string) ($r->account_id ?? '') . ' ' .
                        (string) ($r->rfc_emisor ?? '') . ' ' .
                        (string) ($r->rfc_receptor ?? '') . ' ' .
                        (string) ($r->cfdi_uuid ?? '') . ' ' .
                        (string) ($r->description ?? '') . ' ' .
                        (string) ($r->vendor ?? '') . ' ' .
                        (string) ($r->source ?? '')
                    );

                    if (!str_contains($hay, strtolower($q))) return false;
                }

                return true;
            })->values();
        } else {
            $rows = $rows->values();
        }

        $rows = $rows->sortBy([
            fn ($r) => (string) ($r->period ?? ''),
            fn ($r) => $this->sourceSortWeight($r->source ?? null), // statement_line arriba
            fn ($r) => (string) ($r->client ?? $r->company ?? ''),
            fn ($r) => -1 * (float) ($r->subtotal ?? 0),
        ])->values();

        $dbg['rows_total_after_filters'] = $rows->count();
        $dbg['after_filters_by_source'] = $rows->groupBy('source')->map->count()->all();
        $dbg['after_filters_sample'] = $rows->take(12)->map(fn ($r) => [
            'source' => $r->source ?? null,
            'period' => $r->period ?? null,
            'client' => $r->client ?? ($r->company ?? null),
            'origin' => $r->origin ?? null,
            'ec_status' => $r->ec_status ?? null,
            'invoice_status' => $r->invoice_status ?? null,
            'vendor_id' => $r->vendor_id ?? null,
            'account_id' => $r->account_id ?? null,
            'subtotal' => $r->subtotal ?? null,
        ])->values()->all();

        $kpis = $this->computeKpis($rows);

        $vendorList = $vendorsById
            ->map(fn ($v) => ['id' => (string) $v->id, 'name' => (string) $v->name])
            ->values();

        return [
            'filters' => [
                'year'   => $year,
                'month'  => $month,
                'origin' => $origin,

                // nueva UI
                'status'         => $statusFilter,
                'invoice_status' => $invSt,
                'vendor_id'      => $vendorId,
                'q'              => $qSearch,

                // compat legacy
                'st'       => $statusFilter,
                'invSt'    => $invSt,
                'vendorId' => $vendorId,
                'qSearch'  => $qSearch,

                'include_projections' => $includeProjections ? 1 : 0,
                'include_sales'       => $includeSales ? 1 : 0,
                'vendor_list'         => $vendorList,
                'debug_counts'        => $dbg,
            ],
        ];
    }
    
}