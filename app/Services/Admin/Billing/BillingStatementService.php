<?php

declare(strict_types=1);

namespace App\Services\Admin\Billing;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class BillingStatementService
{
    public function __construct(
        private readonly string $admConn = 'mysql_admin'
    ) {}

    public function paginateHub(string $period, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->guardTables();

        $q       = trim((string)($filters['q'] ?? ''));
        $status  = trim((string)($filters['status'] ?? '')); // pending|partial|paid|no_mov
        $plan    = trim((string)($filters['plan'] ?? ''));
        $blocked = ($filters['blocked'] ?? null); // null|0|1
        $sort    = (string)($filters['sort'] ?? 'created_desc'); // created_desc|saldo_desc|saldo_asc|name_asc

        $query = DB::connection($this->admConn)->table('accounts as a')
            ->select([
                'a.id',
                'a.email',
                'a.name',
                'a.razon_social',
                'a.rfc',
                'a.plan',
                'a.billing_cycle',
                'a.modo_cobro',
                'a.is_blocked',
                'a.estado_cuenta',
                'a.meta',
                'a.created_at',
            ])
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('a.id', 'like', "%{$q}%")
                      ->orWhere('a.email', 'like', "%{$q}%")
                      ->orWhere('a.name', 'like', "%{$q}%")
                      ->orWhere('a.razon_social', 'like', "%{$q}%")
                      ->orWhere('a.rfc', 'like', "%{$q}%");
                });
            })
            ->when($plan !== '', fn($qb) => $qb->where('a.plan', $plan))
            ->when($blocked === 0 || $blocked === '0', fn($qb) => $qb->where('a.is_blocked', 0))
            ->when($blocked === 1 || $blocked === '1', fn($qb) => $qb->where('a.is_blocked', 1));

        // Sorting base (el sort final por saldo se hace luego en colección, porque saldo se calcula)
        if ($sort === 'created_desc') $query->orderByDesc('a.created_at');
        elseif ($sort === 'name_asc') $query->orderBy('a.razon_social')->orderBy('a.name');
        else $query->orderByDesc('a.created_at');

        $pag = $query->paginate($perPage)->withQueryString();

        $ids = $pag->getCollection()->pluck('id')->values()->all();
        if (empty($ids)) {
            return $pag->setCollection(collect());
        }

        $calc = $this->hydrateCalculatedRows($ids, $period);

        $rows = $pag->getCollection()->map(function ($a) use ($calc) {
            $c = $calc[(string)$a->id] ?? null;
            if (!$c) return $a;

            foreach ($c as $k => $v) {
                $a->{$k} = $v;
            }

            return $a;
        });

        // Filtro de status (ya con status calculado)
        if ($status !== '') {
            $rows = $rows->filter(function ($a) use ($status) {
                return match ($status) {
                    'pending' => ($a->status_pago ?? '') === 'pendiente',
                    'partial' => ($a->status_pago ?? '') === 'parcial',
                    'paid'    => ($a->status_pago ?? '') === 'pagado',
                    'no_mov'  => ($a->status_pago ?? '') === 'sin_mov',
                    default   => true,
                };
            })->values();
        }

        // Sort por saldo (post-cálculo)
        if ($sort === 'saldo_desc') {
            $rows = $rows->sortByDesc(fn($a) => (float)($a->saldo ?? 0))->values();
        } elseif ($sort === 'saldo_asc') {
            $rows = $rows->sortBy(fn($a) => (float)($a->saldo ?? 0))->values();
        }

        return $pag->setCollection($rows);
    }

    /**
     * Devuelve un arreglo por accountId con:
     * total, pagado, saldo, status_pago, expected_total, cargo_total, abono_total,
     * pay_url/provider/session, invoice_status, email_tracking, last_payment, etc.
     */
    public function hydrateCalculatedRows(array $accountIds, string $period): array
    {
        $accountIds = array_values(array_unique(array_map('strval', $accountIds)));
        if (empty($accountIds)) return [];

        $now = now();

        // 1) Sumas reales del periodo
        $agg = DB::connection($this->admConn)->table('estados_cuenta')
            ->selectRaw('account_id as aid, SUM(cargo) as cargo, SUM(abono) as abono')
            ->whereIn('account_id', $accountIds)
            ->where('periodo', '=', $period)
            ->groupBy('account_id')
            ->get()
            ->keyBy('aid');

        // 2) Meta HUB del periodo
        $meta = DB::connection($this->admConn)->table('billing_statement_meta')
            ->whereIn('account_id', $accountIds)
            ->where('period', '=', $period)
            ->get()
            ->keyBy('account_id');

        // 3) Último pago (global)
        $lastPay = DB::connection($this->admConn)->table('payments')
            ->selectRaw('account_id as aid, MAX(paid_at) as last_paid_at')
            ->whereIn('account_id', $accountIds)
            ->whereNotNull('paid_at')
            ->groupBy('account_id')
            ->get()
            ->keyBy('aid');

        // 4) Último email log del periodo
        $emailLastId = DB::connection($this->admConn)->table('billing_email_logs')
            ->selectRaw('account_id, MAX(id) as last_id')
            ->whereIn('account_id', $accountIds)
            ->where('period', '=', $period)
            ->groupBy('account_id')
            ->get()
            ->pluck('last_id', 'account_id');

        $emailLast = collect();
        if ($emailLastId->count() > 0) {
            $emailLast = DB::connection($this->admConn)->table('billing_email_logs')
                ->whereIn('id', $emailLastId->values()->all())
                ->get()
                ->keyBy('account_id');
        }

        // 5) Accounts meta para expected_total
        $accounts = DB::connection($this->admConn)->table('accounts')
            ->select(['id', 'meta', 'billing_cycle', 'modo_cobro'])
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        $out = [];

        foreach ($accountIds as $aid) {
            $acc = $accounts[$aid] ?? null;

            $cargo = (float) (($agg[$aid]->cargo ?? 0));
            $abono = (float) (($agg[$aid]->abono ?? 0));

            $accMeta = $this->decodeMeta($acc?->meta ?? null);

            // expected_total = mensualidad efectiva para ese periodo (si no hay movimientos)
            $expected = $this->resolveExpectedMonthlyForPeriod($accMeta, $period, (string)($acc?->billing_cycle ?? ''), (string)($acc?->modo_cobro ?? ''));

            // total mostrado: si hay cargos reales, se respeta, si no hay, se usa expected
            $totalShown = $cargo > 0 ? $cargo : $expected;

            $saldo = max(0, $totalShown - $abono);

            $statusPago = 'sin_mov';
            if ($totalShown > 0.00001) {
                if ($saldo <= 0.00001) $statusPago = 'pagado';
                elseif ($abono > 0.00001) $statusPago = 'parcial';
                else $statusPago = 'pendiente';
            }

            $m = $meta[$aid] ?? null;
            $el = $emailLast[$aid] ?? null;

            $out[$aid] = [
                'period'         => $period,
                'cargo_total'    => round($cargo, 2),
                'abono_total'    => round($abono, 2),
                'expected_total' => round($expected, 2),
                'total'          => round($totalShown, 2),
                'saldo'          => round($saldo, 2),
                'status_pago'    => $statusPago,

                // HUB meta
                'hub_status'     => (string)($m->status ?? $statusPago),
                'pay_provider'   => $m->pay_provider ?? null,
                'pay_session_id' => $m->pay_session_id ?? null,
                'pay_url'        => $m->pay_url ?? null,
                'pay_link_created_at' => $m->pay_link_created_at ?? null,

                'invoice_status'       => $m->invoice_status ?? null,
                'invoice_requested_at' => $m->invoice_requested_at ?? null,

                'last_sent_at'   => $m->last_sent_at ?? null,
                'open_count'     => (int)($m->open_count ?? 0),
                'click_count'    => (int)($m->click_count ?? 0),

                // Email last
                'email_last_status' => $el->status ?? null,
                'email_last_to'     => $el->to ?? null,
                'email_last_subject'=> $el->subject ?? null,

                // Last payment
                'last_paid_at' => $lastPay[$aid]->last_paid_at ?? null,
            ];
        }

        return $out;
    }

    /**
     * Regla de sincronización:
     * - Base: meta.billing.amount_mxn
     * - Override: meta.billing.override.amount_mxn (effective: now|current => aplica ya; next => próximo periodo)
     * - Si billing_cycle es anual, prorratea /12 para el esperado mensual (solo para display).
     * - Si no hay amount, retorna 0.
     */
    public function resolveExpectedMonthlyForPeriod(array $meta, string $period, string $billingCycleRaw, string $modoCobroRaw): float
    {
        $billing = (array)($meta['billing'] ?? []);

        $base = $this->money($billing['amount_mxn'] ?? $billing['amount'] ?? $meta['amount_mxn'] ?? $meta['amount'] ?? null);

        $override = $this->money(
            data_get($billing, 'override.amount_mxn')
            ?? data_get($billing, 'override_amount_mxn')
            ?? data_get($billing, 'custom_amount_mxn')
        );

        $effective = strtolower(trim((string) data_get($billing, 'override.effective', 'now')));
        if ($effective === '') $effective = 'now';

        $cycle = $this->normalizeCycle($billingCycleRaw ?: (string)($billing['billing_cycle'] ?? '') ?: (string)($meta['billing_cycle'] ?? '') ?: 'monthly');

        $applyOverride = false;

        if ($override > 0) {
            if (in_array($effective, ['now', 'current'], true)) {
                $applyOverride = true;
            } elseif ($effective === 'next') {
                // next = siguiente mes del periodo actual del sistema
                // (esto mantiene coherencia con Cuentas/Pagos sin depender de payAllowed)
                $next = now()->copy()->addMonthNoOverflow()->format('Y-m');
                $applyOverride = ($period === $next);
            }
        }

        $mxn = ($applyOverride && $override > 0) ? $override : $base;

        if ($mxn <= 0) return 0.0;

        if ($cycle === 'yearly') {
            $mxn = round($mxn / 12.0, 2);
        }

        return round($mxn, 2);
    }

    public function decodeMeta(mixed $meta): array
    {
        if ($meta === null) return [];
        if (is_array($meta)) {
            return json_decode(json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true) ?: [];
        }
        if (is_object($meta)) {
            return json_decode(json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true) ?: [];
        }
        if (is_string($meta)) {
            $meta = trim($meta);
            if ($meta === '') return [];
            $arr = json_decode($meta, true);
            return is_array($arr) ? $arr : [];
        }
        return [];
    }

    private function normalizeCycle(string $raw): string
    {
        $c = strtolower(trim($raw));
        if (in_array($c, ['yearly', 'annual', 'anual', 'year', '12m'], true)) return 'yearly';
        return 'monthly';
    }

    private function money(mixed $v): float
    {
        if ($v === null) return 0.0;
        if (is_int($v) || is_float($v)) return (float)$v;
        if (!is_string($v)) return 0.0;

        $s = trim($v);
        if ($s === '') return 0.0;

        $s = str_replace(['$', 'MXN', 'mxn', ' '], '', $s);
        $s = str_replace([','], '', $s);

        return is_numeric($s) ? (float)$s : 0.0;
    }

    private function guardTables(): void
    {
        $need = ['accounts', 'estados_cuenta', 'payments', 'billing_statement_meta', 'billing_email_logs'];
        foreach ($need as $t) {
            if (!Schema::connection($this->admConn)->hasTable($t)) {
                abort(500, "Falta tabla {$t} en conexión {$this->admConn}.");
            }
        }
    }
}
