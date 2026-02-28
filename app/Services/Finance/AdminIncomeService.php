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
     * - 1 fila por línea
     * - toma estatus/periodo/account_id de billing_statements
     * - extrae origin/periodicity/vendor/montos desde la línea si existen
     * - liga invoice por statement_id
     */
    private function buildRowsFromStatementLines(
        string $adm,
        string $cli,
        Collection $statementsFiltered,     // statements ya deduplicados y filtrados por mes si aplica
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
        // primero armamos statementIds
        $statementIds = $statementsFiltered
            ->pluck('id')
            ->filter(fn ($x) => !empty($x))
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();

        if (empty($statementIds)) {
            return ['rows' => collect(), 'saleIdSet' => []];
        }

        // ✅ elegir tabla real con base en esos IDs
        $lineTable = $this->pickStatementLinesTable($adm, $statementIds);
        if (!$lineTable) {
            return ['rows' => collect(), 'saleIdSet' => []];
        }
        if (!$lineTable || $statementsFiltered->isEmpty()) {
            return ['rows' => collect(), 'saleIdSet' => []];
        }

        $statementIds = $statementsFiltered
            ->pluck('id')
            ->filter(fn ($x) => !empty($x))
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();

        if (empty($statementIds)) {
            return ['rows' => collect(), 'saleIdSet' => []];
        }

        // Detectar columnas disponibles en la tabla de líneas (PROD-safe)
        $cols = collect();
        try {
            $cols = collect(Schema::connection($adm)->getColumnListing($lineTable))
                ->map(fn ($c) => strtolower((string) $c))
                ->values();
        } catch (\Throwable $e) {
            $cols = collect();
        }

        $has = fn (string $c) => $cols->contains(strtolower($c));

        // Selección dinámica (solo las que existan)
        $sel = [
            'l.id as line_id',
            'l.statement_id',
        ];

        if ($has('type'))        $sel[] = 'l.type';
        if ($has('code'))        $sel[] = 'l.code';
        if ($has('description')) $sel[] = 'l.description';
        if ($has('qty'))         $sel[] = 'l.qty';
        if ($has('unit_price'))  $sel[] = 'l.unit_price';

        // Montos: preferimos subtotal/iva/total; fallback a amount
        if ($has('subtotal')) $sel[] = 'l.subtotal';
        if ($has('iva'))      $sel[] = 'l.iva';
        if ($has('total'))    $sel[] = 'l.total';
        if ($has('amount'))   $sel[] = 'l.amount';

        if ($has('origin'))      $sel[] = 'l.origin';
        if ($has('periodicity')) $sel[] = 'l.periodicity';

        if ($has('vendor_id')) $sel[] = 'l.vendor_id';

        // asociación a venta (si existe)
        $saleIdCol = null;
        foreach (['sale_id', 'finance_sale_id', 'sales_id'] as $c) {
            if ($has($c)) {
                $saleIdCol = $c;
                $sel[] = "l.`{$c}` as sale_id";
                break;
            }
        }

        if ($has('ref'))  $sel[] = 'l.ref';
        if ($has('meta')) $sel[] = 'l.meta';

        // Join a billing_statements (SOT de account_id/period/status/dates/snapshot/meta)
        $q = DB::connection($adm)->table($lineTable . ' as l')
            ->join('billing_statements as bs', 'bs.id', '=', 'l.statement_id')
            ->select(array_merge($sel, [
                'bs.account_id as bs_account_id',
                'bs.period as bs_period',
                'bs.status as bs_status',
                'bs.due_date as bs_due_date',
                'bs.sent_at as bs_sent_at',
                'bs.paid_at as bs_paid_at',
                'bs.snapshot as bs_snapshot',
                'bs.meta as bs_meta',
            ]))
            ->whereIn('l.statement_id', $statementIds)
            ->orderBy('bs.period')
            ->orderBy('l.statement_id')
            ->orderBy('l.id');

        $lineRows = collect($q->get());

        // Para evitar duplicar ventas: guardamos sale_id presentes en líneas
        $saleIdSet = [];
        if ($saleIdCol) {
            foreach ($lineRows as $lr) {
                $sid = (int) ($lr->sale_id ?? 0);
                if ($sid > 0) $saleIdSet[$sid] = true;
            }
        }

        $rows = $lineRows->map(function ($lr) use (
            $adm,
            $cuentaByUuid,
            $cuentaByAdminId,
            $profilesByAccountId,
            $profilesByAdminAcc,
            $invByStatement,
            $invByAccPeriod,
            $paymentsAggByAdminAccPeriod,
            $vendorsById,
            $vendorAssignByAdminAcc
        ) {
            $accId = (string) ($lr->bs_account_id ?? '');
            $per   = (string) ($lr->bs_period ?? '');

            $snap = $this->decodeJson($lr->bs_snapshot ?? null);
            $meta = $this->decodeJson($lr->bs_meta ?? null);

            $cc = $this->resolveCuentaClienteFromStatementAccountId(
                $adm,
                $accId,
                $cuentaByUuid,
                $cuentaByAdminId
            );

            // admin_account_id preferido desde cuenta cliente
            $adminAccId = $cc?->admin_account_id ? (string) $cc->admin_account_id : '';

            // ✅ Fallback robusto: si el UUID del statement no mapea a cuentas_cliente,
            // inferimos admin_account_id desde payments usando el periodo (y statement_id si aplica).
            if ($adminAccId === '') {
                $aid = $this->inferAdminAccountIdForStatement(
                    $adm,
                    $per,
                    (int) ($lr->statement_id ?? 0)
                );

                if (!empty($aid)) {
                    $adminAccId = (string) $aid;

                    // Si encontramos adminAccId, intenta resolver cuenta cliente por admin_account_id
                    $cc2 = $cuentaByAdminId->get($adminAccId);
                    if ($cc2) {
                        $cc = $cc2;
                    }
                }
            }

            // Payments agg ya puede resolverse por adminAccId
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

            $rfcEmisor = $this->pickRfcEmisor(
                $cc?->rfc_padre ?? null,
                $cc?->rfc ?? null
            );

            // -------------------------
            // ✅ Identidad normalizada (blindaje UUID/NUM)
            // -------------------------
            $accountIdRaw       = (string) $accId;
            $accountIdCanonical = $adminAccId !== '' ? (string) $adminAccId : $accountIdRaw;
            $clientAccountId    = $cc?->id ? (string) $cc->id : null;

            // Montos desde la línea (SOT), con fallback seguro
            $subtotal = 0.0;
            $iva      = 0.0;
            $total    = 0.0;

            if (property_exists($lr, 'subtotal') && $lr->subtotal !== null && (float) $lr->subtotal > 0) {
                $subtotal = round((float) $lr->subtotal, 2);
                $iva      = property_exists($lr, 'iva') && $lr->iva !== null ? round((float) $lr->iva, 2) : round($subtotal * 0.16, 2);
                $total    = property_exists($lr, 'total') && $lr->total !== null ? round((float) $lr->total, 2) : round($subtotal + $iva, 2);
            } else {
                // items/lines viejas suelen tener amount como subtotal
                $amt      = property_exists($lr, 'amount') ? (float) ($lr->amount ?? 0) : 0.0;
                $subtotal = round(max(0, $amt), 2);
                $iva      = round($subtotal * 0.16, 2);
                $total    = round($subtotal + $iva, 2);
            }

            // origin/periodicity desde línea si existe; fallback robusto (NO depende solo de cc)
            $lineMeta = $this->decodeJson($lr->meta ?? null);

            // 1) tomar desde línea / meta de línea si existe
            $origin = strtolower(trim((string)($lr->origin ?? (data_get($lineMeta, 'origin') ?? ''))));
            if ($origin === 'no_recurrente') $origin = 'unico';

            // periodicity explícita (si existiera en tabla) o en meta
            $periodicity = strtolower(trim((string)($lr->periodicity ?? (data_get($lineMeta, 'periodicity') ?? ''))));

            // ✅ NUEVO: muchos items (como tu caso) guardan ciclo como billing_cycle (mensual/anual)
            $billingCycle = strtolower(trim((string)(
                data_get($lineMeta, 'billing_cycle')
                ?? data_get($lineMeta, 'cycle')
                ?? data_get($lineMeta, 'cobro')
                ?? ''
            )));

            if ($periodicity === '' && in_array($billingCycle, ['mensual','anual'], true)) {
                $periodicity = $billingCycle;
            }

            // ✅ NUEVO: si no viene origin pero el item es license/subscription/plan y/o billing_cycle existe,
            // forzamos recurrente (evita que quede “unico” por falta de cc)
            if (!in_array($origin, ['recurrente','unico'], true) && in_array($billingCycle, ['mensual','anual'], true)) {
                $origin = 'recurrente';
            }

            // 2) heurística por snapshot/meta del statement (license.mode)
            $snapMode = strtolower(trim((string) (
                data_get($snap, 'license.mode')
                ?? data_get($meta, 'license.mode')
                ?? data_get($snap, 'subscription.mode')
                ?? data_get($meta, 'subscription.mode')
                ?? ''
            )));

            // 3) heurística por tipo/código/descripción de la línea
            $lineType = strtolower(trim((string) ($lr->type ?? '')));
            $lineCode = strtolower(trim((string) ($lr->code ?? '')));
            $lineDesc = strtolower(trim((string) ($lr->description ?? '')));

            $looksRecurring = false;

            if (in_array($snapMode, ['mensual', 'anual'], true)) {
                $looksRecurring = true;
            }

            if (in_array($lineType, ['license', 'subscription', 'plan'], true)) {
                $looksRecurring = true;
            }

            if ($lineCode !== '' && (str_contains($lineCode, 'lic') || str_contains($lineCode, 'plan') || str_contains($lineCode, 'sub'))) {
                $looksRecurring = true;
            }

            if ($lineDesc !== '' && (str_contains($lineDesc, 'licencia') || str_contains($lineDesc, 'suscrip') || str_contains($lineDesc, 'plan'))) {
                $looksRecurring = true;
            }

            // 4) normalización final de origin
            if (!in_array($origin, ['recurrente', 'unico'], true)) {
                // primero intenta con señales del statement/linea
                if ($looksRecurring) {
                    $origin = 'recurrente';
                } else {
                    // fallback a cuenta cliente si existe
                    $modoCobro = strtolower((string) ($cc?->modo_cobro ?? ''));
                    $origin = in_array($modoCobro, ['mensual', 'anual'], true) ? 'recurrente' : 'unico';
                }
            } else {
                // si vino explícito pero contradice señales fuertes, dejamos señales ganar (evita "unico" falso)
                if ($origin === 'unico' && $looksRecurring) {
                    $origin = 'recurrente';
                }
            }

            // 5) normalización final de periodicity
            if (!in_array($periodicity, ['mensual', 'anual', 'unico'], true)) {
                if (in_array($snapMode, ['mensual', 'anual'], true)) {
                    $periodicity = $snapMode;
                } else {
                    $modoCobro = strtolower((string) ($cc?->modo_cobro ?? ''));
                    $periodicity = in_array($modoCobro, ['mensual', 'anual'], true) ? $modoCobro : 'unico';
                }
            }

            // 6) coherencia: recurrente no puede quedar con periodicity=unico
            if ($origin === 'recurrente' && $periodicity === 'unico') {
                if (in_array($snapMode, ['mensual', 'anual'], true)) {
                    $periodicity = $snapMode;
                } else {
                    $modoCobro = strtolower((string) ($cc?->modo_cobro ?? ''));
                    $periodicity = in_array($modoCobro, ['mensual', 'anual'], true) ? $modoCobro : 'mensual';
                }
            }

            // Vendor: línea > meta/snap > asignación
            $vendorId   = null;
            $vendorName = null;

            if (!empty($lr->vendor_id)) {
                $vendorId = (string) $lr->vendor_id;
            } else {
                $vendorId = $this->extractVendorId($meta, $snap, collect());
            }

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

            // -------------------------
            // ✅ Invoice lookup robusto (statement_id -> adminAccId|per -> rawAccId|per)
            // -------------------------
            $invRow = optional($invByStatement->get((int) ($lr->statement_id ?? 0)))->first();
            if (!$invRow) {
                if ($adminAccId !== '') {
                    $invRow = optional($invByAccPeriod->get($adminAccId . '|' . $per))->first();
                }
            }
            if (!$invRow) {
                $invRow = optional($invByAccPeriod->get($accId . '|' . $per))->first();
            }

            $invStatus   = $invRow?->status ? (string) $invRow->status : null;
            $invoiceDate = $invRow?->issued_at ?: null;
            $cfdiUuid    = $invRow?->cfdi_uuid ?: null;

            $invMeta = $this->decodeJson($invRow?->meta ?? null);
            $invoiceFormaPago  = (string) (data_get($invMeta, 'forma_pago') ?? data_get($invMeta, 'cfdi.forma_pago') ?? '');
            $invoiceMetodoPago = (string) (data_get($invMeta, 'metodo_pago') ?? data_get($invMeta, 'cfdi.metodo_pago') ?? '');
            $invoicePaidAt     = data_get($invMeta, 'paid_at') ?? data_get($invMeta, 'fecha_pago') ?? null;

            $paidAt = $pAgg?->paid_at ?: ($lr->bs_paid_at ?? null) ?: ($invoicePaidAt ?: null);

            // 1) Normalización base: usa paidAt REAL (paymentsAgg/bs/invoice), no solo bs_paid_at
            $ecStatus = $this->normalizeStatementStatus((object) [
                'paid_at'  => $paidAt,
                'sent_at'  => $lr->bs_sent_at ?? null,
                'status'   => $lr->bs_status ?? null,
                'due_date' => $lr->bs_due_date ?? null,
            ]);

            // 2) Blindaje final: si payments dice "paid", forzamos pagado sí o sí
            if ($pAgg && !empty($pAgg->status)) {
                $ps = strtolower(trim((string) $pAgg->status));
                if (in_array($ps, ['paid', 'pagado', 'succeeded', 'success', 'complete', 'completed'], true)) {
                    $ecStatus = 'pagado';
                }
            }

            $y = (int) substr($per, 0, 4);
            $m = (int) substr($per, 5, 2);

            $desc = trim((string) ($lr->description ?? ''));
            if ($desc === '') $desc = trim((string) ($lr->code ?? ''));
            if ($desc === '') $desc = $origin === 'recurrente'
                ? ($periodicity === 'anual' ? 'Recurrente Anual' : 'Recurrente Mensual')
                : 'Venta Única';

            return (object) [
                'source'        => 'statement_line',
                'is_projection' => 0,

                'year'       => $y,
                'month_num'  => sprintf('%02d', $m),
                'month_name' => $this->monthNameEs($m),

                'vendor_id' => $vendorId,
                'vendor'    => $vendorName,

                'client'      => $company,
                'description' => $desc,

                'period' => $per,

                // ✅ identidad blindada
                'account_id'        => $accountIdCanonical, // numérico si hay admin_account_id
                'account_id_raw'    => $accountIdRaw,       // uuid/raw del statement
                'client_account_id' => $clientAccountId,    // uuid real de cuentas_cliente.id

                'company'    => $company,
                'rfc_emisor' => $rfcEmisor,

                'origin'      => $origin,
                'periodicity' => $periodicity,

                'subtotal' => $subtotal,
                'iva'      => $iva,
                'total'    => $total,

                'ec_status' => $ecStatus,
                'due_date'  => $lr->bs_due_date ?? null,
                'sent_at'   => $lr->bs_sent_at ?? null,
                'paid_at'   => $paidAt,

                'rfc_receptor' => (string) ($bp->rfc_receptor ?? ''),
                'forma_pago'   => (string) ($bp->forma_pago ?? '') !== ''
                    ? (string) ($bp->forma_pago ?? '')
                    : ($invoiceFormaPago ?: ''),

                'f_emision' => $lr->bs_sent_at ?? null,
                'f_pago'    => $paidAt,
                'f_cta'     => $lr->bs_sent_at ?? null,
                'f_mov'     => null,

                'f_factura'          => $invoiceDate,
                'invoice_date'       => $invoiceDate,
                'invoice_status'     => $invStatus,
                'invoice_status_raw' => $invStatus,
                'cfdi_uuid'          => $cfdiUuid,

                'invoice_metodo_pago' => $invoiceMetodoPago !== '' ? $invoiceMetodoPago : null,

                'payment_method' => $pAgg?->method ?: null,
                'payment_status' => $pAgg?->status ?: null,

                'statement_id' => (int) ($lr->statement_id ?? 0),
                'line_id'      => (int) ($lr->line_id ?? 0),
                'sale_id'      => (int) ($lr->sale_id ?? 0),

                'notes' => null,
            ];
        });

        return ['rows' => $rows->values(), 'saleIdSet' => $saleIdSet];
    }

    /**
     * Resuelve montos del statement (subtotal/iva/total) de forma robusta.
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

        // 2) items heurística
        $sumItems = round((float) $items->sum(fn ($it) => (float) ($it->amount ?? 0)), 2);

        if ($tc > 0 && $sumItems > 0) {
            if (abs($sumItems - $tc) < 0.02) {
                // total_cargo == subtotal
                $subtotal = $tc;
                $total    = round($subtotal * 1.16, 2);
                $iva      = round($total - $subtotal, 2);

                return ['subtotal' => $subtotal, 'iva' => $iva, 'total' => $total, 'mode' => 'tc_is_subtotal'];
            }

            if (abs(($sumItems * 1.16) - $tc) < 0.05) {
                // total_cargo == total con IVA; items sum == subtotal
                $subtotal = $sumItems;
                $total    = $tc;
                $iva      = round($total - $subtotal, 2);

                return ['subtotal' => $subtotal, 'iva' => $iva, 'total' => $total, 'mode' => 'tc_is_total'];
            }
        }

        // 3) fallback: tc como subtotal
        if ($tc > 0) {
            $subtotal = $tc;
            $total    = round($subtotal * 1.16, 2);
            $iva      = round($total - $subtotal, 2);
            return ['subtotal' => $subtotal, 'iva' => $iva, 'total' => $total, 'mode' => 'fallback_tc_subtotal'];
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

    private function blankKpis(): array
    {
        return [
            'total'   => ['count' => 0, 'amount' => 0.0],
            'pending' => ['count' => 0, 'amount' => 0.0],
            'emitido' => ['count' => 0, 'amount' => 0.0],
            'pagado'  => ['count' => 0, 'amount' => 0.0],
            'vencido' => ['count' => 0, 'amount' => 0.0],
        ];
    }

    private function computeKpis(Collection $rows): array
    {
        $k = $this->blankKpis();

        foreach ($rows as $r) {
            $k['total']['count']++;
            $k['total']['amount'] += (float) ($r->subtotal ?? 0); // KPI base: SUBTOTAL (cuadra con estados)

            $st = strtolower((string) ($r->ec_status ?? ''));
            if ($st !== '' && isset($k[$st])) {
                $k[$st]['count']++;
                $k[$st]['amount'] += (float) ($r->subtotal ?? 0);
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
        // Filtros
        // -------------------------
        $year  = (int) ($req->input('year') ?: (int) now()->format('Y'));
        $month = (string) ($req->input('month') ?: 'all'); // 01..12 | all

        // Excel: Origen = recurrente | unico
        // Compat: UI vieja puede mandar no_recurrente
        $origin = strtolower((string) ($req->input('origin') ?: 'all')); // recurrente|unico|no_recurrente|all

        // -------------------------
        // Normalización robusta de filtros (compat UI nueva + legacy)
        // UI nueva manda: vendor_id, status, invoice_status, q
        // UI legacy/vNext manda: vendorId, st, invSt, qSearch
        // -------------------------
        $statusFilter = strtolower(trim((string) ($req->input('status') ?: $req->input('st') ?: 'all'))); // pending|emitido|pagado|vencido|all
        $invSt = strtolower(trim((string) ($req->input('invoice_status') ?: $req->input('invSt') ?: 'all')));

        // ✅ Canonizador único invoice_status (entrada + rows)
        $canonInv = function ($val): string {
            $x = strtolower(trim((string) $val));
            if ($x === '' || in_array($x, ['all', 'todos', 'todas'], true)) return 'all';

            // normaliza separadores para evitar "sin solicitud" vs "sin_solicitud"
            $x = str_replace([' ', '-'], '_', $x);

            return match ($x) {
                // “sin solicitud”
                'sin_solicitud', 'no_solicitud', 'no_solicitada', 'none', 'no_request', 'sin' => 'sin_solicitud',

                // “solicitada”
                'requested', 'solicitada', 'solicitado', 'request', 'enviada', 'enviado' => 'requested',

                // “emitida / facturada”
                'issued', 'facturada', 'facturado', 'timbrada', 'timbrado' => 'issued',

                // “en proceso / lista”
                'ready', 'en_proceso', 'procesando', 'preparando' => 'ready',

                // “cancelada / rechazada”
                'cancelled', 'canceled', 'rechazada', 'rechazado', 'cancelada', 'cancelado' => 'cancelled',

                default => $x,
            };
        };

        // ✅ Normaliza invoice_status request → canónico
        $invSt = $canonInv($invSt);

        // Para compat con filtros y blade (algunos esperan 'st')
        $st = $statusFilter;

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

        // ✅ SOT: si existen líneas de estado de cuenta, NO usamos proyecciones aquí
        // ✅ SOT: NO decidimos aquí si hay líneas.
        // Lo decidimos cuando tengamos statementIds reales del periodo/mes.
        $linesTable = null;

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

        // -------------------------
        // Periodos del año
        // -------------------------
        $periodsYear = [];
        for ($m = 1; $m <= 12; $m++) $periodsYear[] = sprintf('%04d-%02d', $year, $m);

        // DEBUG
        $dbg = [
            'year'  => $year,
            'month' => $month,
            'periodsYear_count' => 0,

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
        ];

        // filtro mes al universo de periodos
        if ($month !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            $periodsYear = [sprintf('%04d-%s', $year, $month)];
        }
        $dbg['periodsYear_count'] = count($periodsYear);

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

        // ✅ statementIds (necesario para items + debug)
        $statementIds = $statementsAllYear
            ->pluck('id')
            ->filter(fn ($x) => !empty($x))
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();
        $dbg['statement_ids_count'] = count($statementIds);

        // ✅ Detectar tabla real de líneas para estos statementIds.
        // Si hay líneas reales, apagamos proyecciones (porque el grid ya se basa en líneas).
        $linesTable = $this->pickStatementLinesTable($adm, $statementIds);
        if ($linesTable) {
            $includeProjections = false;
        }

        // -------------------------
        // Items por statement
        // -------------------------
        $itemsByStatement = collect();
        if (Schema::connection($adm)->hasTable('billing_statement_items') && !empty($statementIds)) {
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
                ->whereIn('bi.statement_id', $statementIds)
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
        // Cuentas cliente (mysql_clientes) para mapa
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
        // B.0) Detectar admin_account_id con PAYMENTS en el año/mes (aunque la cuenta sea FREE)
        // + armar mapa por periodo para poder "pagar-driven projections"
        // =========================
        $payAdminIds = [];
        $payAdminIdsByPeriod = collect(); // key "YYYY-MM" => [adminId=>true]

        if (Schema::connection($adm)->hasTable('payments')) {
            try {
                $pQ = DB::connection($adm)->table('payments')
                    ->select(['account_id', 'period'])
                    ->whereIn('period', $periodsYear);

                if ($month !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
                    $pQ->where('period', '=', sprintf('%04d-%s', $year, $month));
                }

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

        // A) incluir explícitamente cuentas ligadas a statements (uuid/admin_id)
        $hasStatementFilter = (!empty($uuidIds) || !empty($numIds));

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
        // DEDUPE statements (UUID/NUM) + statementKeyHash CANÓNICO
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

        $dbg['statements_after_dedupe_year'] = $statementsAllYear->count();
        $dbg['statements_filtered_post_dedupe'] = $statements->count();

        // Rebuild statementKeyHash canónico + expand uuid<->admin
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

        // -------------------------
        // Payments agg
        // -------------------------
        $paymentsAggByAdminAccPeriod = collect(); // key "adminId|YYYY-MM"
        $lastPaymentByAdminAcc       = collect(); // key "adminId"

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

                if ($month !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
                    $pQ->where('period', '=', sprintf('%04d-%s', $year, $month));
                }

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

                    if (!isset($lastPaid[$aid])) {
                        $lastPaid[$aid] = $p;
                        continue;
                    }

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
        // SOT Estado de Cuenta (líneas)
        // -------------------------
        $rowsExisting = collect();
        $statementLineSaleIdSet = [];

        // $linesTable ya fue detectada con statementIds (si aplica)
            if ($linesTable && $statementsFiltered->isNotEmpty()) {
            $sot = $this->buildRowsFromStatementLines(
                $adm,
                $cli,
                $statementsFiltered,
                $cuentaByUuid,
                $cuentaByAdminId,
                $profilesByAccountId,
                $profilesByAdminAcc,
                $invByStatement,
                $invByAccPeriod,
                $paymentsAggByAdminAccPeriod,
                $vendorsById,
                $vendorAssignByAdminAcc
            );

            $rowsExisting = $sot['rows'] ?? collect();
            if (!($rowsExisting instanceof Collection)) $rowsExisting = collect($rowsExisting);

            $statementLineSaleIdSet = (array) ($sot['saleIdSet'] ?? []);
        }

        // -------------------------
        // Fallback legacy: cabecera statement (si no hay líneas)
        // -------------------------
        if ($rowsExisting->isEmpty()) {
            // baseline recurrente (simplificado aquí: usa statement con origin recurrente y subtotal>0)
            $baselineRecurring = [];
            foreach ($statementsAllYear as $s) {
                $its  = collect($itemsByStatement->get($s->id, collect()));
                $snap = $this->decodeJson($s->snapshot);
                $meta = $this->decodeJson($s->meta);

                $originGuess = $this->guessOrigin($its, $snap, $meta);
                if ($originGuess !== 'recurrente') continue;

                $am = $this->resolveStatementAmounts($s, $its, $snap, $meta);
                $subtotal = (float) ($am['subtotal'] ?? 0.0);
                if ($subtotal <= 0) continue;

                $accKey = (string) $s->account_id;

                $baselineRecurring[$accKey] = [
                    'period'   => (string) $s->period,
                    'subtotal' => round($subtotal, 2),
                ];

                if (preg_match('/^[0-9a-f\-]{36}$/i', $accKey)) {
                    $cc = $cuentaByUuid->get($accKey);
                    if ($cc && !empty($cc->admin_account_id)) {
                        $baselineRecurring[(string) $cc->admin_account_id] = [
                            'period'   => (string) $s->period,
                            'subtotal' => round($subtotal, 2),
                        ];
                    }
                }
            }

            $planPrice = function (?string $plan, ?string $modo): float {
                $plan = strtolower(trim((string) $plan));
                $modo = strtolower(trim((string) $modo));

                $map = [
                    'free'       => ['mensual' => 0.0,    'anual' => 0.0],
                    'basic'      => ['mensual' => 580.0,  'anual' => 5800.0],
                    'pro'        => ['mensual' => 980.0,  'anual' => 9800.0],
                    'enterprise' => ['mensual' => 1980.0, 'anual' => 19800.0],
                ];

                if (!isset($map[$plan])) return 0.0;
                if (!isset($map[$plan][$modo])) return 0.0;

                return (float) $map[$plan][$modo];
            };

            $rowsExisting = $statements->map(function ($s) use (
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
                $vendorAssignByAdminAcc,
                $baselineRecurring,
                $lastPaymentByAdminAcc,
                $planPrice
            ) {
                $accId = (string) ($s->account_id ?? '');
                $per   = (string) ($s->period ?? '');

                $snap = $this->decodeJson($s->snapshot);
                $meta = $this->decodeJson($s->meta);

                $cc = $this->resolveCuentaClienteFromStatementAccountId(
                    $adm,
                    $accId,
                    $cuentaByUuid,
                    $cuentaByAdminId
                );

                $adminAccId = $cc?->admin_account_id ? (string) $cc->admin_account_id : '';

                // fallback: si statement trae UUID y admin.accounts tuviera uuid (si algún día)
                if ($adminAccId === '' && $accId !== '' && preg_match('/^[0-9a-f\-]{36}$/i', $accId)) {
                    try {
                        if (Schema::connection($adm)->hasTable('accounts')) {
                            $tmpId = DB::connection($adm)->table('accounts')->where('uuid', $accId)->value('id');
                            if (!empty($tmpId) && preg_match('/^\d+$/', (string) $tmpId)) {
                                $adminAccId = (string) $tmpId;
                            }
                        }
                    } catch (\Throwable $e) {
                        // noop
                    }
                }

                $pAgg = null;
                if ($adminAccId !== '') {
                    $pAgg = $paymentsAggByAdminAccPeriod->get($adminAccId . '|' . $per);
                }

                $bp = $this->resolveBillingProfile(
                    (string) $s->account_id,
                    (string) ($cc?->admin_account_id ?? ''),
                    $profilesByAccountId,
                    $profilesByAdminAcc
                );

                $company = $this->pickCompanyName($cc, $snap, $meta, $bp);

                $rfcEmisor = $this->pickRfcEmisor(
                    $cc?->rfc_padre ?? null,
                    $cc?->rfc ?? null
                );

                $its = collect($itemsByStatement->get($s->id, collect()));
                $am  = $this->resolveStatementAmounts($s, $its, $snap, $meta);

                $subtotal = (float) ($am['subtotal'] ?? 0);
                $iva      = (float) ($am['iva'] ?? 0);
                $total    = (float) ($am['total'] ?? 0);

                if ($subtotal <= 0) {
                    $modoCobro  = strtolower((string) ($cc?->modo_cobro ?? ''));
                    $isRecurring = in_array($modoCobro, ['mensual', 'anual'], true);

                    if ($isRecurring) {
                        $accKey = (string) $s->account_id;
                        $base = (float) (data_get($baselineRecurring, $accKey . '.subtotal') ?? 0.0);

                        if ($base <= 0 && !empty($cc?->admin_account_id)) {
                            $base = (float) (data_get($baselineRecurring, (string) $cc->admin_account_id . '.subtotal') ?? 0.0);
                        }

                        if ($base <= 0 && !empty($cc?->admin_account_id) && $lastPaymentByAdminAcc->has((string) $cc->admin_account_id)) {
                            $lp = $lastPaymentByAdminAcc->get((string) $cc->admin_account_id);
                            $amtCents = (float) ($lp->amount ?? 0);
                            $amtMxn   = $amtCents > 0 ? ($amtCents / 100) : 0.0;
                            if ($amtMxn > 0) $base = round($amtMxn / 1.16, 2);
                        }

                        if ($base <= 0) {
                            $plan = strtolower((string) ($cc?->plan_actual ?? ''));
                            $base = (float) $planPrice($plan, $modoCobro);
                        }

                        $subtotal = round(max(0, $base), 2);
                        $total    = round($subtotal * 1.16, 2);
                        $iva      = round($total - $subtotal, 2);
                    }
                }

                if ($subtotal <= 0 && $pAgg && (float) ($pAgg->sum_amount_mxn ?? 0) > 0) {
                    $subtotal = round(((float) $pAgg->sum_amount_mxn) / 1.16, 2);
                    $total    = round($subtotal * 1.16, 2);
                    $iva      = round($total - $subtotal, 2);
                }

                $origin = $this->guessOrigin($its, $snap, $meta);
                $periodicity = $this->guessPeriodicity($snap, $meta, $its);

                $modoCobro = strtolower((string) ($cc?->modo_cobro ?? ''));
                if (in_array($modoCobro, ['mensual', 'anual'], true)) {
                    $periodicity = $modoCobro;
                    $origin      = 'recurrente';
                }
                if ($origin === 'recurrente' && $periodicity === 'unico') $periodicity = 'mensual';

                $ecStatus = $this->normalizeStatementStatus($s);

                $vendorId = $this->extractVendorId($meta, $snap, $its);
                $vendorName = null;

                if (!empty($vendorId) && $vendorsById->has($vendorId)) {
                    $vendorName = (string) ($vendorsById->get($vendorId)?->name ?? null);
                }

                if ((empty($vendorId) || !$vendorName) && !empty($cc?->admin_account_id)) {
                    $aid = (string) $cc->admin_account_id;
                    $as  = $vendorAssignByAdminAcc->get($aid);

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

                $invRow = optional($invByStatement->get((int) ($s->id ?? 0)))->first();
                if (!$invRow) {
                    if ($adminAccId !== '') $invRow = optional($invByAccPeriod->get($adminAccId . '|' . $per))->first();
                }
                if (!$invRow) {
                    $invRow = optional($invByAccPeriod->get((string) $s->account_id . '|' . (string) $s->period))->first();
                }

                $invStatus   = $invRow?->status ? (string) $invRow->status : null;
                $invoiceDate = $invRow?->issued_at ?: null;
                $cfdiUuid    = $invRow?->cfdi_uuid ?: null;

                $invMeta = $this->decodeJson($invRow?->meta ?? null);
                $invoiceFormaPago  = (string) (data_get($invMeta, 'forma_pago') ?? data_get($invMeta, 'cfdi.forma_pago') ?? '');
                $invoiceMetodoPago = (string) (data_get($invMeta, 'metodo_pago') ?? data_get($invMeta, 'cfdi.metodo_pago') ?? '');
                $invoicePaidAt     = data_get($invMeta, 'paid_at') ?? data_get($invMeta, 'fecha_pago') ?? null;

                $paidAt = $pAgg?->paid_at ?: $s->paid_at ?: $invoicePaidAt ?: null;

                $ym = (string) $s->period;
                $y  = (int) substr($ym, 0, 4);
                $m  = (int) substr($ym, 5, 2);

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

                    'period'     => $ym,
                    'account_id' => (string) $s->account_id,

                    'company'    => $company,
                    'rfc_emisor' => $rfcEmisor,

                    'origin'      => $origin,
                    'periodicity' => $periodicity,

                    'subtotal' => $subtotal,
                    'iva'      => $iva,
                    'total'    => $total,

                    'ec_status' => $ecStatus,
                    'due_date'  => $s->due_date,
                    'sent_at'   => $s->sent_at,
                    'paid_at'   => $paidAt,

                    'rfc_receptor' => (string) ($bp?->rfc_receptor ?? ''),
                    'forma_pago'   => (string) ($bp?->forma_pago ?? '') !== ''
                        ? (string) ($bp?->forma_pago ?? '')
                        : ($invoiceFormaPago ?: ''),

                    'f_emision' => $s->sent_at,
                    'f_pago'    => $paidAt,
                    'f_cta'     => $s->sent_at,
                    'f_mov'     => null,

                    'f_factura'          => $invoiceDate,
                    'invoice_date'       => $invoiceDate,
                    'invoice_status'     => $invStatus,
                    'invoice_status_raw' => $invStatus,
                    'cfdi_uuid'          => $cfdiUuid,

                    'invoice_metodo_pago' => $invoiceMetodoPago !== '' ? $invoiceMetodoPago : null,

                    'payment_method' => $pAgg?->method ?: null,
                    'payment_status' => $pAgg?->status ?: null,

                    'raw_statement_status' => (string) ($s->status ?? ''),
                    'notes' => null,
                ];
            });
        }

        // -------------------------
        // 2) PROYECCIONES (si se habilitan; normalmente apagadas si hay líneas)
        // -------------------------
        $rowsProjected = collect();
        // (Se deja preparado pero apagado por $includeProjections=false cuando hay líneas)

        // -------------------------
        // 3) Ventas únicas (finance_sales) con anti-doble conteo
        // -------------------------
        $rowsSales = collect();

        if ($includeSales && Schema::connection($adm)->hasTable('finance_sales')) {
            $fsCols = collect(Schema::connection($adm)->getColumnListing('finance_sales'))
                ->map(fn ($c) => strtolower((string) $c))->values();

            $colPeriod = $fsCols->contains('period')
                ? 'period'
                : ($fsCols->contains('target_period') ? 'target_period' : null);

            $colStmtTarget = $fsCols->contains('statement_period_target')
                ? 'statement_period_target'
                : ($fsCols->contains('target_period') ? 'target_period' : null);

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

            $qSales->whereIn("s.{$colPeriod}", $periodsYear);

            $sales = collect($qSales->orderBy('period')->orderBy('s.id')->get());
            $dbg['sales_rows_period'] = $sales->count();

            $sales = $this->attachCompanyFromClientes($sales, $cli);

            $sales = $sales->filter(function ($s) use ($statementKeyHash, $statementLineSaleIdSet) {
                // ✅ si ya vino en líneas del estado de cuenta, no la dupliques aquí
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

            $rowsSales = $sales->map(function ($s) use ($vendorAssignByAdminAcc, $vendorsById) {
            $per = (string) $s->period;
            $y = (int) substr($per, 0, 4);
            $m = (int) substr($per, 5, 2);

            $origin = strtolower((string) $s->origin);
            if ($origin === 'no_recurrente') $origin = 'unico';
            if (!in_array($origin, ['recurrente', 'unico'], true)) $origin = 'unico';

            $periodicity = strtolower((string) $s->periodicity);
            if (!in_array($periodicity, ['mensual', 'anual', 'unico'], true)) $periodicity = 'unico';

            $ecStatus = strtolower((string) ($s->statement_status ?? 'pending'));
            if (!in_array($ecStatus, ['pending', 'emitido', 'pagado', 'vencido'], true)) {
                $ecStatus = 'pending';
            }

            $invRaw = strtolower((string) ($s->invoice_status ?? ''));
            $invCanonical = $this->mapSalesInvoiceStatusToCanonical($invRaw);

            // ==========================
            // ✅ DESCRIPCIÓN CORREGIDA
            // ==========================
            $notes    = trim((string) ($s->notes ?? ''));
            $saleCode = trim((string) ($s->sale_code ?? ''));

            if ($notes === '') {
                if ($saleCode !== '') {
                    $desc = $saleCode;
                } else {
                    if ($origin === 'recurrente') {
                        $desc = ($periodicity === 'anual')
                            ? 'Recurrente Anual'
                            : 'Recurrente Mensual';
                    } else {
                        $desc = 'Venta Única';
                    }
                }
            } else {
                $desc = ($saleCode !== '')
                    ? ($saleCode . ' · ' . $notes)
                    : $notes;
            }

            $fMov = $s->f_mov ?: ($s->sale_date ?: ($s->created_at ?: null));

            // ==========================
            // ✅ Vendor fallback (MISMA LÓGICA que statement/lines)
            // ==========================
            $vendorId   = !empty($s->vendor_id) ? (string) $s->vendor_id : null;
            $vendorName = !empty($s->vendor_name) ? (string) $s->vendor_name : null;

            if ((!$vendorId || !$vendorName) && !empty($s->account_id) && preg_match('/^\d+$/', (string) $s->account_id)) {
                $aid = (string) $s->account_id;
                $as  = $vendorAssignByAdminAcc->get($aid);

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

            // Si ya tenemos vendorId pero no nombre, intenta desde finance_vendors
            if ($vendorId && !$vendorName && $vendorsById->isNotEmpty() && $vendorsById->has($vendorId)) {
                $vendorName = (string) ($vendorsById->get($vendorId)?->name ?? null);
            }

            return (object) [
                'source'        => 'sale',
                'is_projection' => 0,

                'year'       => $y,
                'month_num'  => sprintf('%02d', $m),
                'month_name' => $this->monthNameEs($m),

                'vendor_id' => $vendorId,
                'vendor'    => $vendorName,

                'client'      => (string) ($s->company ?? ('Cuenta ' . $s->account_id)),
                'description' => $desc,

                'period'     => $per,
                'account_id' => (string) $s->account_id,
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

                'f_factura'          => $s->invoice_date ?: null,
                'invoice_date'       => $s->invoice_date ?: null,
                'invoice_status'     => $invCanonical,
                'invoice_status_raw' => $invRaw,
                'cfdi_uuid'          => $s->cfdi_uuid ?: null,

                'payment_method' => null,
                'payment_status' => null,

                'sale_id' => (int) $s->id,
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

        $dbg['rows_existing']  = $rowsExisting->count();
        $dbg['rows_projected'] = $rowsProjected->count();
        $dbg['rows_sales']     = $rowsSales->count();
        $dbg['rows_total_before_filters'] = $rows->count();

        $dbg['before_filters_by_source'] = $rows->groupBy('source')->map->count()->all();
        $dbg['before_filters_sample'] = $rows->map(function ($r) {
            return [
                'source'         => $r->source ?? null,
                'period'         => $r->period ?? null,
                'client'         => $r->client ?? ($r->company ?? null),
                'origin'         => $r->origin ?? null,
                'ec_status'      => $r->ec_status ?? null,
                'invoice_status' => $r->invoice_status ?? null,
                'vendor_id'      => $r->vendor_id ?? null,
                'account_id'     => $r->account_id ?? null,
                'subtotal'       => $r->subtotal ?? null,
                'paid_at'        => $r->paid_at ?? null,
                'payment_status' => $r->payment_status ?? null,
            ];
        })->take(12)->values()->all();

        // -------------------------
        // Apply overrides
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
                    $rowType = match ($src) {
                        'projection' => 'projection',
                        'statement_line' => 'statement_line',
                        default => 'statement',
                    };

                    $key = $rowType . '|' . (string) ($r->account_id ?? '') . '|' . (string) ($r->period ?? '');
                    $ov  = $ovByKey->get($key);
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

                    if ($ov->notes !== null) $r->notes = (string) $ov->notes;

                    $vid = (string) ($r->vendor_id ?? '');
                    if ($vid !== '' && $vendorsById->isNotEmpty() && $vendorsById->has($vid)) {
                        $r->vendor = (string) ($vendorsById->get($vid)?->name ?? $r->vendor ?? null);
                    }

                    $r->has_override = 1;
                    return $r;
                });
            }
        }

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

        // -------------------------
        // FAST-PATH HARD: si todo está en all y sin búsqueda, NO filtrar
        // -------------------------
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

        if ($noFiltersHard) {
            $rows = $rows->sortBy([
                fn ($r) => (string) ($r->period ?? ''),
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
                    'st'     => $statusFilter,
                    'invSt'  => $invSt,
                    'vendorId' => $vendorId,
                    'qSearch'  => $qSearch,
                    'include_projections' => $includeProjections ? 1 : 0,
                    'include_sales'       => $includeSales ? 1 : 0,
                    'vendor_list'         => $vendorList,
                    'debug_counts'        => $dbg,
                ],
                'kpis' => $kpis,
                'rows' => $rows,
            ];
        }

        // -------------------------
        // Filtros finales
        // -------------------------
        $noFilters =
            ($originNorm === 'all') &&
            ($stNorm0 === '' || in_array($stNorm0, ['all', 'todos', 'todas'], true)) &&
            ($invNorm0 === '' || in_array($invNorm0, ['all', 'todos', 'todas'], true)) &&
            (($vendorNorm === '') || ($vendorNorm === 'all')) &&
            ($qNorm === '');

        if (!$noFilters) {
            $rows = $rows->filter(function ($r) use ($originNorm, $st, $invSt, $vendorId, $qSearch) {
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
                    // primero intenta invoice_status, si viene vacío usa raw
                    $cmp = (string) ($r->invoice_status ?? '');
                    if (trim($cmp) === '') {
                        $cmp = (string) ($r->invoice_status_raw ?? '');
                    }

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
                'st'     => $statusFilter,
                'invSt'  => $invSt,
                'vendorId' => $vendorId,
                'qSearch'  => $qSearch,
                'include_projections' => $includeProjections ? 1 : 0,
                'include_sales'       => $includeSales ? 1 : 0,
                'vendor_list'         => $vendorList,
                'debug_counts'        => $dbg,
            ],
            'kpis' => $kpis,
            'rows' => $rows,
        ];
    }
}