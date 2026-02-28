<?php

declare(strict_types=1);

namespace App\Services\Admin\Finance;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class IncomeGridService
{
    // ==========================
    // Schema helpers
    // ==========================
    private function hasCol(string $conn, string $table, string $col): bool
    {
        try {
            return Schema::connection($conn)->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function cols(string $conn, string $table): array
    {
        try {
            return collect(Schema::connection($conn)->getColumnListing($table))
                ->map(fn($c) => strtolower((string)$c))
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function decodeJson(mixed $raw): array
    {
        if ($raw === null || $raw === '') return [];
        if (is_array($raw)) return $raw;

        $j = json_decode((string) $raw, true);
        return is_array($j) ? $j : [];
    }

    // ==========================
    // Account normalization (UUID/NUM dedupe)
    // ==========================
    private function normalizeAccountKey(?string $statementAccountId, ?object $cc): string
    {
        $sid = trim((string) ($statementAccountId ?? ''));

        // si tenemos admin_account_id => canónico
        if ($cc && !empty($cc->admin_account_id)) {
            return (string) $cc->admin_account_id;
        }

        // si ya viene numérico
        if ($sid !== '' && preg_match('/^\d+$/', $sid)) return $sid;

        // si viene UUID, lo dejamos como último recurso
        if ($sid !== '' && preg_match('/^[0-9a-f\-]{36}$/i', $sid)) return $sid;

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

            // 2) fallback: puede ser admin.accounts.uuid -> accounts.id -> clientes.admin_account_id
            try {
                if (Schema::connection($admConn)->hasTable('accounts')) {
                    $accId = DB::connection($admConn)->table('accounts')->where('uuid', $sid)->value('id');
                    if (!empty($accId)) {
                        return $cuentaByAdmin->get((string) $accId);
                    }
                }
            } catch (\Throwable $e) {
                // noop
            }

            return null;
        }

        // 3) Numérico: admin_account_id
        if (preg_match('/^\d+$/', $sid)) {
            return $cuentaByAdmin->get($sid);
        }

        return null;
    }

    private function statementScore(object $s, Collection $itemsForStatement): int
    {
        // pagado > emitido > locked > con items > total_cargo>0 > más nuevo
        $score = 0;

        if (!empty($s->paid_at)) $score += 1000;
        if (!empty($s->sent_at)) $score += 500;
        if ((int)($s->is_locked ?? 0) === 1) $score += 200;

        $cntItems = (int) $itemsForStatement->count();
        if ($cntItems > 0) $score += 100 + min(50, $cntItems);

        $tc = (float) ($s->total_cargo ?? 0);
        if ($tc > 0) $score += 50;

        $score += (int) ($s->id ?? 0) > 0 ? 1 : 0;

        return $score;
    }

    private function pickBestStatement(Collection $group, Collection $itemsByStatement): ?object
    {
        if ($group->isEmpty()) return null;

        $best = null;
        $bestScore = -PHP_INT_MAX;

        foreach ($group as $s) {
            $its = collect($itemsByStatement->get((int)($s->id ?? 0), collect()));
            $sc  = $this->statementScore($s, $its);

            if ($sc > $bestScore) {
                $bestScore = $sc;
                $best = $s;
            } elseif ($sc === $bestScore) {
                // desempate por id
                $bid = (int) ($best->id ?? 0);
                $sid = (int) ($s->id ?? 0);
                if ($sid > $bid) $best = $s;
            }
        }

        return $best;
    }

    // ==========================
    // Amount normalization (para que cuadre con Estado de cuenta)
    // ==========================
    /**
     * En tu BD, billing_statements.total_cargo muchas veces es SUBTOTAL (sin IVA).
     * El Estado de cuenta muestra ese "Total" (sin IVA).
     *
     * Regla:
     *  - subtotal := total_cargo (si > 0)
     *  - si no, intenta snapshot/meta subtotals
     *  - si no, suma items->amount
     *  - si no, si hay total, infiere subtotal = total/1.16
     */
    private function resolveStatementSubtotal(object $s, Collection $items, array $snap, array $meta): float
    {
        // 1) Si existen items, normalmente reflejan el "estado de cuenta" real (incluye cargos extra)
        //    OJO: billing_statements.total_cargo muchas veces es baseline (sin extras).
        $sumItems = (float) $items->sum(fn ($it) => (float) ($it->amount ?? 0));
        if ($sumItems > 0) {
            return round($sumItems, 2);
        }

        // 2) Fallback a total_cargo
        $tc = (float) ($s->total_cargo ?? 0);
        if ($tc > 0) return round($tc, 2);

        // 3) Snapshot/meta subtotal
        $snapSubtotal =
            (float) (data_get($snap, 'totals.subtotal') ?? 0) ?:
            (float) (data_get($snap, 'statement.subtotal') ?? 0) ?:
            (float) (data_get($snap, 'subtotal') ?? 0) ?:
            (float) (data_get($meta, 'totals.subtotal') ?? 0) ?:
            (float) (data_get($meta, 'statement.subtotal') ?? 0) ?:
            (float) (data_get($meta, 'subtotal') ?? 0);

        if ($snapSubtotal > 0) return round($snapSubtotal, 2);

        // 4) Último recurso: si hay total, infiere subtotal=total/1.16
        $snapTotal =
            (float) (data_get($snap, 'totals.total') ?? 0) ?:
            (float) (data_get($snap, 'statement.total') ?? 0) ?:
            (float) (data_get($snap, 'total') ?? 0) ?:
            (float) (data_get($meta, 'totals.total') ?? 0) ?:
            (float) (data_get($meta, 'statement.total') ?? 0) ?:
            (float) (data_get($meta, 'total') ?? 0);

        if ($snapTotal > 0) return round($snapTotal / 1.16, 2);

        return 0.0;
    }

    private function normalizeStatementStatus(object $s): string
    {
        if (!empty($s->paid_at)) return 'pagado';
        if (!empty($s->sent_at)) return 'emitido';

        $st = strtolower(trim((string) ($s->status ?? '')));

        $norm = match ($st) {
            'paid', 'pagado'       => 'pagado',
            'sent', 'emitido'      => 'emitido',
            'overdue', 'vencido'   => 'vencido',
            'pending', 'pendiente' => 'pending',
            default                => 'pending',
        };

        if ($norm === 'pending' && !empty($s->due_date)) {
            try {
                $due = Carbon::parse($s->due_date)->startOfDay();
                if ($due->lt(now()->startOfDay())) return 'vencido';
            } catch (\Throwable $e) {}
        }

        return $norm;
    }

    private function isPlaceholderName(string $name): bool
    {
        $n = trim($name);
        if ($n === '') return true;
        return (bool) preg_match('/^cuenta\s+\d+$/i', $n);
    }

    private function pickCompanyName(?object $cc, array $snap, array $meta): string
    {
        $ccName = '';
        if ($cc) {
            $ccName = (string) (
                ($cc->nombre_comercial ?? '') !== '' ? $cc->nombre_comercial :
                (($cc->razon_social ?? '') !== '' ? $cc->razon_social :
                (($cc->empresa ?? '') !== '' ? $cc->empresa : ''))
            );
            $ccName = trim($ccName);
        }

        if ($ccName !== '' && !$this->isPlaceholderName($ccName)) return $ccName;

        $snapName = trim((string) (
            data_get($snap, 'account.company')
            ?? data_get($snap, 'company')
            ?? data_get($snap, 'razon_social')
            ?? data_get($meta, 'account.company')
            ?? data_get($meta, 'company')
            ?? data_get($meta, 'razon_social')
            ?? ''
        ));
        if ($snapName !== '' && !$this->isPlaceholderName($snapName)) return $snapName;

        return '—';
    }

    public function build(Request $req): array
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $cli = (string) (config('p360.conn.clientes') ?: 'mysql_clientes');

        // filtro month: YYYY-MM
        $month = (string) ($req->get('month') ?: now()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        // filtros
        $origin    = strtolower((string) ($req->get('origin') ?: 'all')); // all|recurrente|no_recurrente|unico
        if ($origin === 'unico') $origin = 'no_recurrente';

        $stStatus  = strtolower((string) ($req->get('statement_status') ?: 'all')); // all|pending|emitido|pagado|vencido
        $invStatus = strtolower((string) ($req->get('invoice_status') ?: 'all'));
        $vendorId  = (string) ($req->get('vendor_id') ?: 'all');
        $rfc       = trim((string) ($req->get('receiver_rfc') ?: ''));
        $payMethod = trim((string) ($req->get('pay_method') ?: ''));

        $from = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $to   = (clone $from)->endOfMonth();

        // ==========================
        // Vendors list
        // ==========================
        $vendors = collect();
        if (Schema::connection($adm)->hasTable('finance_vendors')) {
            $vendors = collect(
                DB::connection($adm)->table('finance_vendors')
                    ->select(['id', 'name'])
                    ->orderBy('name')
                    ->get()
            );
        }

        // ==========================
        // 0) cuentas_cliente map (para dedupe + company)
        // ==========================
        $cuentas = collect();
        $cuentaByUuid = collect();
        $cuentaByAdmin = collect();

        if (Schema::connection($cli)->hasTable('cuentas_cliente')) {
            $sel = ['id','admin_account_id','razon_social','nombre_comercial'];
            if ($this->hasCol($cli, 'cuentas_cliente', 'empresa')) $sel[] = 'empresa';
            if ($this->hasCol($cli, 'cuentas_cliente', 'rfc')) $sel[] = 'rfc';
            if ($this->hasCol($cli, 'cuentas_cliente', 'rfc_padre')) $sel[] = 'rfc_padre';

            $cuentas = collect(DB::connection($cli)->table('cuentas_cliente')->select($sel)->get());
            $cuentaByUuid  = $cuentas->keyBy(fn($c) => (string)$c->id);
            $cuentaByAdmin = $cuentas->filter(fn($c) => !empty($c->admin_account_id))
                ->keyBy(fn($c) => (string)$c->admin_account_id);
        }

        // ==========================
        // 1) Statements (billing_statements) -> recurrente real del mes
        // ==========================
        $recRows = collect();

        if (Schema::connection($adm)->hasTable('billing_statements')) {
            // trae todos los del mes
            $stmts = collect(DB::connection($adm)->table('billing_statements as bs')
                ->select([
                    'bs.id',
                    'bs.account_id',
                    'bs.period',
                    'bs.total_cargo',
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
                ->where('bs.period', '=', $month)
                ->orderBy('bs.id')
                ->get());

            // items por statement (si existe tabla)
            $itemsByStatement = collect();
            if (Schema::connection($adm)->hasTable('billing_statement_items') && $stmts->isNotEmpty()) {
                $ids = $stmts->pluck('id')->filter()->values()->all();
                if (!empty($ids)) {
                    $itemsByStatement = collect(DB::connection($adm)->table('billing_statement_items as bi')
                        ->select(['bi.id','bi.statement_id','bi.type','bi.code','bi.description','bi.amount','bi.meta'])
                        ->whereIn('bi.statement_id', $ids)
                        ->get())->groupBy('statement_id');
                }
            }

            // invoices por (account_id|period) si existe billing_invoice_requests
            $invByAccPeriod = collect();
            if (Schema::connection($adm)->hasTable('billing_invoice_requests')) {
                $invAll = collect(DB::connection($adm)->table('billing_invoice_requests')
                    ->select(['id','account_id','period','status','cfdi_uuid','issued_at','meta'])
                    ->where('period', '=', $month)
                    ->orderByDesc('id')
                    ->get());

                $invByAccPeriod = $invAll->groupBy(fn($r) => (string)$r->account_id.'|'.(string)$r->period);
            }

            // agrupar por cuenta_canónica|period y escoger el mejor statement (dedupe)
            $grouped = $stmts->map(function($s) use ($adm, $cuentaByUuid, $cuentaByAdmin, $month) {

                    $sid = (string) ($s->account_id ?? '');

                    // ✅ resolve cc usando helper (soporta accounts.uuid -> accounts.id -> clientes.admin_account_id)
                    $cc = $this->resolveCuentaClienteFromStatementAccountId(
                        $adm,
                        $sid,
                        $cuentaByUuid,
                        $cuentaByAdmin
                    );

                    $canon = $this->normalizeAccountKey($sid, $cc);

                    return (object)[
                        'canon' => $canon,
                        'stmt'  => $s,
                    ];
                })
                ->groupBy(fn($x) => (string)$x->canon.'|'.$month);

            $bestStatements = $grouped->map(function($g) use ($itemsByStatement) {
                $only = collect($g)->map(fn($x) => $x->stmt)->values();
                return $this->pickBestStatement($only, $itemsByStatement);
            })->filter()->values();

            $recRows = $bestStatements->map(function ($s) use (
                $adm,
                $month,
                $itemsByStatement,
                $invByAccPeriod,
                $cuentaByUuid,
                $cuentaByAdmin
                ) {
                    $sid = trim((string) ($s->account_id ?? ''));

                    // ✅ resolve robusto (soporta: clientes.id UUID | admin_account_id int | admin.accounts.uuid -> accounts.id -> clientes.admin_account_id)
                    $cc = $this->resolveCuentaClienteFromStatementAccountId(
                        $adm,
                        $sid,
                        $cuentaByUuid,
                        $cuentaByAdmin
                    );

                    $snap  = $this->decodeJson($s->snapshot);
                    $meta  = $this->decodeJson($s->meta);
                    $items = collect($itemsByStatement->get((int) ($s->id ?? 0), collect()));

                    // ==========================
                    // Amounts (cuadrar con Estado de cuenta)
                    // ==========================
                    $subtotal = $this->resolveStatementSubtotal($s, $items, $snap, $meta);
                    $iva      = round($subtotal * 0.16, 2);
                    $total    = round($subtotal + $iva, 2);

                    // ==========================
                    // Company
                    // ==========================
                    $company = $this->pickCompanyName($cc, $snap, $meta);

                    // ==========================
                    // Estado de cuenta status
                    // ==========================
                    $ecStatus = $this->normalizeStatementStatus($s);

                    // ==========================
                    // Invoice (billing_invoice_requests) — lookup robusto
                    // ==========================
                    $normalizeInvStatus = function (?string $st): string {
                        $st = strtolower(trim((string) ($st ?? '')));
                        if ($st === '') return 'sin_solicitud';

                        return match ($st) {
                            'issued', 'facturada', 'facturado' => 'issued',
                            'ready', 'en_proceso', 'en proceso', 'processing' => 'ready',
                            'requested', 'solicitada', 'request' => 'requested',
                            'cancelled', 'canceled', 'cancelada' => 'cancelled',
                            'pending', 'pendiente' => 'pending',
                            default => $st, // si viene un status custom lo respetamos (pero normalizado)
                        };
                    };

                    $inv = null;

                    // 1) key raw (tal cual viene en billing_invoice_requests.account_id)
                    $k1 = $sid !== '' ? ($sid . '|' . $month) : null;
                    if ($k1) {
                        $inv = optional($invByAccPeriod->get($k1))->first();
                    }

                    // 2) fallback: si tenemos admin_account_id canónico en cuenta_cliente
                    if (!$inv && $cc && !empty($cc->admin_account_id)) {
                        $k2  = (string) $cc->admin_account_id . '|' . $month;
                        $inv = optional($invByAccPeriod->get($k2))->first();
                    }

                    // 3) fallback: si sid es UUID de admin.accounts.uuid, intentar resolver a accounts.id
                    if (!$inv && $sid !== '' && preg_match('/^[0-9a-f\-]{36}$/i', $sid)) {
                        try {
                            if (Schema::connection($adm)->hasTable('accounts')) {
                                $accId = DB::connection($adm)->table('accounts')->where('uuid', $sid)->value('id');
                                if (!empty($accId)) {
                                    $k3  = (string) $accId . '|' . $month;
                                    $inv = optional($invByAccPeriod->get($k3))->first();
                                }
                            }
                        } catch (\Throwable $e) {
                            // noop
                        }
                    }

                    $invStatus    = $normalizeInvStatus($inv?->status ? (string) $inv->status : null);
                    $cfdiUuid     = !empty($inv?->cfdi_uuid) ? (string) $inv->cfdi_uuid : null;
                    $invoiceDate  = $inv?->issued_at ?: null;

                    // ==========================
                    // Vendor (desde snap/meta) + nombre (si existe finance_vendors)
                    // ==========================
                    $vendorId = data_get($meta, 'vendor_id')
                        ?? data_get($meta, 'vendor.id')
                        ?? data_get($snap, 'vendor_id')
                        ?? data_get($snap, 'vendor.id')
                        ?? null;
                    $vendorId = !empty($vendorId) ? (string) $vendorId : null;

                    $vendorName = null;
                    if ($vendorId && ctype_digit($vendorId) && Schema::connection($adm)->hasTable('finance_vendors')) {
                        try {
                            $vendorName = DB::connection($adm)->table('finance_vendors')->where('id', (int) $vendorId)->value('name');
                            $vendorName = $vendorName !== null ? (string) $vendorName : null;
                        } catch (\Throwable $e) {
                            $vendorName = null;
                        }
                    }

                    // ==========================
                    // Periodicity (mensual/anual) — normalizado
                    // ==========================
                    $perioRaw = (string) (data_get($snap, 'license.mode')
                        ?? data_get($meta, 'license.mode')
                        ?? data_get($snap, 'periodicity')
                        ?? data_get($meta, 'periodicity')
                        ?? 'mensual');

                    $perio = strtolower(trim($perioRaw));
                    if (!in_array($perio, ['mensual', 'anual', 'unico'], true)) $perio = 'mensual';

                    // ==========================
                    // RFC receptor / pay_method — más variantes típicas
                    // ==========================
                    $receiverRfc = (string) (
                        data_get($snap, 'receiver_rfc')
                        ?? data_get($snap, 'receptor_rfc')
                        ?? data_get($snap, 'rfc_receptor')
                        ?? data_get($meta, 'receiver_rfc')
                        ?? data_get($meta, 'receptor_rfc')
                        ?? data_get($meta, 'rfc_receptor')
                        ?? ''
                    );

                    $payMethod = (string) (
                        data_get($snap, 'pay_method')
                        ?? data_get($snap, 'payment_method')
                        ?? data_get($snap, 'forma_pago')
                        ?? data_get($meta, 'pay_method')
                        ?? data_get($meta, 'payment_method')
                        ?? data_get($meta, 'forma_pago')
                        ?? ''
                    );

                    return (object) [
                        'row_type' => 'statement',
                        'row_id'   => (int) ($s->id ?? 0),

                        'account_id' => (string) ($s->account_id ?? ''),
                        'period'     => (string) ($s->period ?? ''),

                        'origin'      => 'recurrente',
                        'periodicity' => $perio,

                        'sale_code'    => null,
                        'receiver_rfc' => $receiverRfc, // opcional
                        'pay_method'   => $payMethod,   // opcional

                        'f_cta'        => $s->sent_at ?: null,
                        'f_mov'        => null,
                        'invoice_date' => $invoiceDate,
                        'paid_date'    => $s->paid_at ?: null,
                        'sale_date'    => $s->created_at ?: null,

                        // SUBTOTAL cuadra con Estado de cuenta
                        'subtotal' => $subtotal,
                        'iva'      => $iva,
                        'total'    => $total,

                        'statement_status' => $ecStatus,
                        'invoice_status'   => $invStatus,
                        'invoice_uuid'     => $cfdiUuid,

                        'vendor_id'   => $vendorId,
                        'vendor_name' => $vendorName,

                        // extra UI
                        'company' => $company,
                    ];
                });
        }

        // ==========================
        // 2) Ventas (finance_sales) -> no_recurrente/unico
        //    FIX: excluir include_in_statement=1 si ya hay statement del mes
        // ==========================
        $salesRows = collect();

        $statementKeyHash = [];
        if ($recRows->isNotEmpty()) {
            // llave base para excluir ventas "incluidas"
            foreach ($recRows as $r) {
                $k = (string)($r->account_id ?? '').'|'.$month;
                if ($k !== '|'.$month) $statementKeyHash[$k] = true;
            }
        }

        if (Schema::connection($adm)->hasTable('finance_sales')) {
            $fsCols = collect($this->cols($adm, 'finance_sales'));

            $colInvoiceUuid = $fsCols->contains('cfdi_uuid') ? 'cfdi_uuid' : ($fsCols->contains('invoice_uuid') ? 'invoice_uuid' : null);

            $q = DB::connection($adm)->table('finance_sales as s')
                ->leftJoin('finance_vendors as v', 'v.id', '=', 's.vendor_id');

            $q->select([
                DB::raw("'venta' as row_type"),
                's.id as row_id',
                's.account_id',
                's.origin',
                's.periodicity',
                's.sale_code',
                's.receiver_rfc',
                's.pay_method',
                's.f_cta',
                's.f_mov',
                's.invoice_date',
                's.paid_date',
                's.sale_date',
                's.subtotal',
                's.iva',
                's.total',
                's.statement_status',
                's.invoice_status',
                $colInvoiceUuid ? DB::raw("s.`{$colInvoiceUuid}` as invoice_uuid") : DB::raw("NULL as invoice_uuid"),
                's.include_in_statement',
                $fsCols->contains('statement_period_target') ? 's.statement_period_target' : DB::raw("NULL as statement_period_target"),
                'v.name as vendor_name',
                's.vendor_id',
            ]);

            $q->whereBetween(DB::raw('COALESCE(s.sale_date, s.f_mov, s.f_cta, s.invoice_date, s.paid_date)'), [
                $from->toDateString(), $to->toDateString()
            ]);

            // origen: solo únicos (si origin=all no filtra, pero el query sí debe evitar recurrentes)
            $q->where(function($w){
                $w->whereIn('s.origin', ['unico', 'no_recurrente'])
                  ->orWhere('s.periodicity', '=', 'unico');
            });

            if ($origin !== 'all') {
                if ($origin === 'recurrente') {
                    $q->whereRaw('1=0'); // este bloque es solo ventas únicas
                } else {
                    $q->whereIn('s.origin', ['unico','no_recurrente']);
                }
            }

            if ($stStatus !== 'all') $q->where('s.statement_status', $stStatus);
            if ($invStatus !== 'all') $q->where('s.invoice_status', $invStatus);
            if ($vendorId !== 'all' && ctype_digit($vendorId)) $q->where('s.vendor_id', (int)$vendorId);
            if ($rfc !== '') $q->where('s.receiver_rfc', 'like', '%'.$rfc.'%');
            if ($payMethod !== '') $q->where('s.pay_method', 'like', '%'.$payMethod.'%');

            $sales = collect($q->orderByRaw('COALESCE(s.sale_date, s.f_mov, s.f_cta, s.invoice_date, s.paid_date) desc')->get());

            // EXCLUSIÓN: si include_in_statement=1 y ya hay statement del mes (por account_id|month o target)
            $sales = $sales->filter(function($s) use ($statementKeyHash, $month) {
                $aid = (string) ($s->account_id ?? '');
                if ($aid === '') return true;

                $inc = (int) ($s->include_in_statement ?? 0);
                if ($inc !== 1) return true;

                $perTarget = (string) ($s->statement_period_target ?? '');
                if ($perTarget !== '' && isset($statementKeyHash[$aid.'|'.$perTarget])) return false;

                if (isset($statementKeyHash[$aid.'|'.$month])) return false;

                return true;
            })->values();

            $salesRows = $sales;
        }

        // ==========================
        // Merge & normalize
        // ==========================
        $rows = $recRows->concat($salesRows)->map(function ($r) {
            $sub = (float) ($r->subtotal ?? 0);
            $iva = (float) ($r->iva ?? 0);
            if ($sub > 0 && $iva <= 0) $iva = round($sub * 0.16, 2);

            $tot = (float) ($r->total ?? 0);
            if ($tot <= 0 && ($sub > 0 || $iva > 0)) $tot = round($sub + $iva, 2);

            $r->subtotal = round($sub, 2);
            $r->iva      = round($iva, 2);
            $r->total    = round($tot, 2);

            // normaliza statuses
            $r->statement_status = strtolower((string) ($r->statement_status ?? 'pending'));
            if (!in_array($r->statement_status, ['pending','emitido','pagado','vencido'], true)) {
                $r->statement_status = 'pending';
            }

            $r->invoice_status = strtolower((string) ($r->invoice_status ?? 'sin_solicitud'));
            if ($r->invoice_status === '') $r->invoice_status = 'sin_solicitud';

            return $r;
        })->values();

        // ==========================
        // KPIs (CLAVE: por SUBTOTAL para cuadrar con Estado de cuenta)
        // ==========================
        $kpi = [
            'pending' => ['count'=>0,'total'=>0.0],
            'emitido' => ['count'=>0,'total'=>0.0],
            'pagado'  => ['count'=>0,'total'=>0.0],
            'vencido' => ['count'=>0,'total'=>0.0],
            'all'     => ['count'=>0,'total'=>0.0],
        ];

        foreach ($rows as $r) {
            $st = (string) ($r->statement_status ?? 'pending');
            if (!isset($kpi[$st])) $st = 'pending';

            $kpi[$st]['count']++;
            $kpi[$st]['total'] += (float) ($r->subtotal ?? 0);

            $kpi['all']['count']++;
            $kpi['all']['total'] += (float) ($r->subtotal ?? 0);
        }

                foreach ($kpi as $k => $v) {
            $kpi[$k]['total'] = round((float) $kpi[$k]['total'], 2);
        }

        // ==========================
        // KPI: CAJA (por FECHA REAL de pago) desde payments.paid_at
        // - payments.amount es bigint (centavos) => convertir a MXN dividiendo entre 100
        // - Esto NO depende del "period" contable del payment (justo para resolver mismatch)
        // ==========================
        $kpiCash = [
            'count'           => 0,
            'amount'          => 0.0,
            'mismatch_count'  => 0,
            'mismatch_amount' => 0.0,
        ];

        if (Schema::connection($adm)->hasTable('payments')) {
            try {
                $fromDt = (clone $from)->startOfDay();
                $toDt   = (clone $to)->endOfDay();

                // Caja total del mes por paid_at
                $cashRow = DB::connection($adm)->table('payments')
                    ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount),0) as sum_amount')
                    ->where('status', '=', 'paid')
                    ->whereNotNull('paid_at')
                    ->whereBetween('paid_at', [$fromDt->toDateTimeString(), $toDt->toDateTimeString()])
                    ->first();

                $cashCount = (int) data_get($cashRow, 'n', 0);
                $cashSumCents = (float) data_get($cashRow, 'sum_amount', 0);

                $kpiCash['count']  = $cashCount;
                $kpiCash['amount'] = round($cashSumCents / 100.0, 2);

                // Mismatch: payments.period != DATE_FORMAT(paid_at,'%Y-%m') (dentro del mes)
                // Ej: paid_at=2025-12 pero period=2026-01
                $misRow = DB::connection($adm)->table('payments')
                    ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount),0) as sum_amount')
                    ->where('status', '=', 'paid')
                    ->whereNotNull('paid_at')
                    ->whereNotNull('period')
                    ->whereBetween('paid_at', [$fromDt->toDateTimeString(), $toDt->toDateTimeString()])
                    ->whereRaw('period <> DATE_FORMAT(paid_at, "%Y-%m")')
                    ->first();

                $misCount = (int) data_get($misRow, 'n', 0);
                $misSumCents = (float) data_get($misRow, 'sum_amount', 0);

                $kpiCash['mismatch_count']  = $misCount;
                $kpiCash['mismatch_amount'] = round($misSumCents / 100.0, 2);

            } catch (\Throwable $e) {
                // no rompemos el grid si algo falla en payments
            }
        }

        // Lo metemos dentro del mismo kpi para consumo en UI/Controller
        $kpi['cash'] = $kpiCash;

        return [
            'month'   => $month,
            'filters' => compact('origin','stStatus','invStatus','vendorId','rfc','payMethod'),
            'kpi'     => $kpi,
            'vendors' => $vendors,
            'rows'    => $rows,
        ];
    }
}