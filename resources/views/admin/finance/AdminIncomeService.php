<?php

declare(strict_types=1);

namespace App\Services\Finance;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AdminIncomeService
{
    public function build(Request $req): array
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        // Filtros
        $year   = (int) ($req->input('year') ?: (int) now()->format('Y'));
        $month  = (string) ($req->input('month') ?: 'all'); // 01..12 | all
        $origin = (string) ($req->input('origin') ?: 'all'); // recurrente|no_recurrente|all
        $st     = (string) ($req->input('status') ?: 'all'); // pending|emitido|pagado|vencido|all
        $invSt  = (string) ($req->input('invoice_status') ?: 'all');
        $q      = trim((string) ($req->input('q') ?: ''));

        $periodFrom = Carbon::create($year, 1, 1)->startOfMonth();
        $periodTo   = Carbon::create($year, 12, 1)->endOfMonth();

        // Statements del año (o del mes si aplica)
        $statementsQ = DB::connection($adm)->table('billing_statements as bs')
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

        if ($month !== 'all' && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
            $statementsQ->where('bs.period', '=', sprintf('%04d-%s', $year, $month));
        }

        $statements = collect($statementsQ->orderBy('bs.period')->orderBy('bs.id')->get());

        if ($statements->isEmpty()) {
            return [
                'filters' => compact('year', 'month', 'origin', 'st', 'invSt', 'q'),
                'kpis'    => $this->blankKpis(),
                'rows'    => collect(),
            ];
        }

        $statementIds = $statements->pluck('id')->all();

        // Items por statement
        $items = DB::connection($adm)->table('billing_statement_items as bi')
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

        // Invoice requests (preferimos billing_invoice_requests por statement_id)
        $inv1 = DB::connection($adm)->table('billing_invoice_requests')
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
                'meta',
            ])
            ->whereIn('statement_id', $statementIds)
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('statement_id');

        // Fallback por account_id+period (invoice_requests)
        $inv2 = DB::connection($adm)->table('invoice_requests')
            ->select([
                'id',
                'account_id',
                'period',
                'status',
                'cfdi_uuid',
                'notes',
                'zip_ready_at',
                'zip_sent_at',
                'created_at',
            ])
            ->whereIn('period', $statements->pluck('period')->unique()->values()->all())
            ->whereIn('account_id', $statements->pluck('account_id')->unique()->values()->all())
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy(fn ($r) => $r->account_id . '|' . $r->period);

        // Payments por account_id+period (y/o paid_at)
        $pay = DB::connection($adm)->table('payments')
            ->select([
                'id',
                'account_id',
                'amount',
                'currency',
                'method',
                'provider',
                'concept',
                'reference',
                'status',
                'period',
                'due_date',
                'paid_at',
                'meta',
                'created_at',
            ])
            ->whereIn('period', $statements->pluck('period')->unique()->values()->all())
            ->whereIn('account_id', $statements->pluck('account_id')->unique()->values()->all())
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy(fn ($r) => $r->account_id . '|' . $r->period);

        // Billing profile (RFC receptor/forma pago)
        $profiles = DB::connection($adm)->table('finance_billing_profiles')
            ->whereIn('account_id', $statements->pluck('account_id')->unique()->values()->all())
            ->get()
            ->keyBy('account_id');

        // Construcción de filas tipo Excel
        $rows = $statements->map(function ($s) use ($items, $inv1, $inv2, $pay, $profiles) {

            $snap = $this->decodeJson($s->snapshot);
            $meta = $this->decodeJson($s->meta);

            // Display de cuenta (sin asumir tabla accounts)
            $company = (string) (
                data_get($snap, 'account.company')
                ?? data_get($snap, 'company')
                ?? data_get($snap, 'razon_social')
                ?? data_get($meta, 'company')
                ?? ('Cuenta ' . $s->account_id)
            );

            $rfcEmisor = (string) (data_get($snap, 'account.rfc') ?? data_get($snap, 'rfc') ?? '');

            // Items
            $its = collect($items->get($s->id, collect()));

            $subtotal = (float) $its->sum(fn ($it) => (float) ($it->amount ?? 0));
            $iva      = round($subtotal * 0.16, 2);
            $total    = round($subtotal + $iva, 2);

            // Origen / periodicidad (heurística segura)
            $origin = $this->guessOrigin($its, $snap, $meta);
            $periodicity = $this->guessPeriodicity($snap, $meta, $its);

            // Estatus Estado de Cuenta (normalizado)
            $ecStatus = $this->normalizeStatementStatus($s);

            // Facturación profile
            $bp = $profiles->get($s->account_id);
            $rfcReceptor = (string) ($bp->rfc_receptor ?? '');
            $formaPago   = (string) ($bp->forma_pago ?? '');

            // Invoice status + fechas
            $invRow = optional($inv1->get($s->id))->first();
            if (!$invRow) {
                $invRow = optional($inv2->get($s->account_id . '|' . $s->period))->first();
            }

            $invStatus   = $invRow?->status ? (string) $invRow->status : '—';
            $invoiceDate = $invRow?->issued_at ?: null;
            $cfdiUuid    = $invRow?->cfdi_uuid ?: null;

            // Payment (forma pago real y fecha pago real)
            $p = optional($pay->get($s->account_id . '|' . $s->period))->first();
            $paidAt = $p?->paid_at ?: $s->paid_at ?: null;

            return (object) [
                'period'        => (string) $s->period,
                'account_id'    => (string) $s->account_id,
                'company'       => $company,
                'rfc_emisor'    => $rfcEmisor,

                'origin'        => $origin,        // recurrente/no_recurrente
                'periodicity'   => $periodicity,   // mensual/anual/unico

                'subtotal'      => $subtotal,
                'iva'           => $iva,
                'total'         => $total,

                // Estado de cuenta
                'ec_status'     => $ecStatus,
                'due_date'      => $s->due_date,
                'sent_at'       => $s->sent_at,
                'paid_at'       => $paidAt,

                // Bloque facturación
                'rfc_receptor'  => $rfcReceptor,
                'forma_pago'    => $formaPago,
                'f_cta'         => $s->sent_at,    // “F Cta” = emisión E.Cta (usamos sent_at por ahora)
                'f_mov'         => null,           // se llenará desde Ventas cuando aplique
                'invoice_date'  => $invoiceDate,
                'invoice_status'=> $invStatus,
                'cfdi_uuid'     => $cfdiUuid,
                'payment_method'=> $p?->method ?: null,
                'payment_status'=> $p?->status ?: null,

                // Extra
                'raw_statement_status' => (string) $s->status,
            ];
        });

        // Aplicar filtros “post” (porque algunos campos vienen de JSON/heurísticas)
        $rows = $rows->filter(function ($r) use ($origin, $st, $invSt, $q) {

            if ($origin !== 'all' && $r->origin !== $origin) return false;
            if ($st !== 'all' && $r->ec_status !== $st) return false;

            if ($invSt !== 'all') {
                $cmp = strtolower((string) $r->invoice_status);
                if ($cmp !== strtolower($invSt)) return false;
            }

            if ($q !== '') {
                $hay = strtolower(
                    $r->company . ' ' .
                    $r->account_id . ' ' .
                    $r->rfc_emisor . ' ' .
                    $r->rfc_receptor . ' ' .
                    ($r->cfdi_uuid ?? '')
                );
                if (!str_contains($hay, strtolower($q))) return false;
            }

            return true;
        })->values();

        $kpis = $this->computeKpis($rows);

        return [
            'filters' => compact('year', 'month', 'origin', 'st', 'invSt', 'q'),
            'kpis'    => $kpis,
            'rows'    => $rows,
        ];
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
            $k['total']['amount'] += (float) $r->total;

            if (isset($k[$r->ec_status])) {
                $k[$r->ec_status]['count']++;
                $k[$r->ec_status]['amount'] += (float) $r->total;
            }
        }

        // redondeo
        foreach ($k as $key => $v) {
            $k[$key]['amount'] = round((float) $k[$key]['amount'], 2);
        }

        return $k;
    }

    private function normalizeStatementStatus(object $s): string
    {
        // Prioridad por timestamps
        if (!empty($s->paid_at)) return 'pagado';
        if (!empty($s->sent_at)) return 'emitido';

        $st = strtolower(trim((string) ($s->status ?? '')));

        return match ($st) {
            'paid', 'pagado'       => 'pagado',
            'sent', 'emitido'      => 'emitido',
            'overdue', 'vencido'   => 'vencido',
            'pending', 'pendiente' => 'pending',
            default                => 'pending',
        };
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

            $im = $this->decodeJson($it->meta ?? null);
            $orig = strtolower((string) (data_get($im, 'origin') ?? ''));
            if (in_array($orig, ['recurrente', 'no_recurrente'], true)) return $orig;
        }

        return 'no_recurrente';
    }

    private function guessPeriodicity(array $snap, array $meta, Collection $items): string
    {
        $mode = strtolower((string) (data_get($snap, 'license.mode') ?? data_get($meta, 'license.mode') ?? ''));
        if (in_array($mode, ['mensual', 'anual'], true)) return $mode;

        foreach ($items as $it) {
            $im = $this->decodeJson($it->meta ?? null);
            $p = strtolower((string) (data_get($im, 'periodicity') ?? ''));
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
}
