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
    public function build(Request $req): array
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        $month = (string) ($req->get('month') ?: now()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        $origin    = (string) ($req->get('origin') ?: 'all'); // all|recurrente|no_recurrente
        $stStatus  = (string) ($req->get('statement_status') ?: 'all'); // all|pending|emitido|pagado
        $invStatus = (string) ($req->get('invoice_status') ?: 'all');
        $vendorId  = (string) ($req->get('vendor_id') ?: 'all');
        $rfc       = trim((string) ($req->get('receiver_rfc') ?: ''));
        $payMethod = trim((string) ($req->get('pay_method') ?: ''));

        $from = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $to   = (clone $from)->endOfMonth();

        // ============
        // 1) Ventas (finance_sales) -> origen no_recurrente/unico
        // ============
        $salesRows = collect();
        if (Schema::connection($adm)->hasTable('finance_sales')) {
            $q = DB::connection($adm)->table('finance_sales as s')
                ->leftJoin('finance_vendors as v', 'v.id', '=', 's.vendor_id')
                ->select([
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
                    's.invoice_uuid',
                    'v.name as vendor_name',
                ])
                ->whereBetween(DB::raw('COALESCE(s.sale_date, s.f_mov, s.f_cta, s.invoice_date, s.paid_date)'), [$from->toDateString(), $to->toDateString()]);

            if ($origin !== 'all') $q->where('s.origin', $origin);
            if ($stStatus !== 'all') $q->where('s.statement_status', $stStatus);
            if ($invStatus !== 'all') $q->where('s.invoice_status', $invStatus);
            if ($vendorId !== 'all' && ctype_digit($vendorId)) $q->where('s.vendor_id', (int)$vendorId);
            if ($rfc !== '') $q->where('s.receiver_rfc', 'like', '%'.$rfc.'%');
            if ($payMethod !== '') $q->where('s.pay_method', 'like', '%'.$payMethod.'%');

            $salesRows = collect($q->orderByRaw('COALESCE(s.sale_date, s.f_mov, s.f_cta, s.invoice_date, s.paid_date) desc')->get());
        }

        // ============
        // 2) Recurrentes: intentamos tomar de billing_statements (si existe)
        //    (porque ya trae estatus emitido/pagado/pending por mes)
        // ============
        $recRows = collect();
        $statementsTable = null;

        if (Schema::connection($adm)->hasTable('billing_statements')) $statementsTable = 'billing_statements';
        if ($statementsTable === null && Schema::connection($adm)->hasTable('billing_statements_hub')) $statementsTable = 'billing_statements_hub';

        if ($statementsTable) {
            $q = DB::connection($adm)->table($statementsTable.' as st')
                ->select([
                    DB::raw("'recurrente' as row_type"),
                    DB::raw("st.id as row_id"),
                    DB::raw("st.account_id as account_id"),
                    DB::raw("'recurrente' as origin"),
                    DB::raw("COALESCE(st.periodicity,'mensual') as periodicity"),
                    DB::raw("NULL as sale_code"),
                    DB::raw("st.receiver_rfc as receiver_rfc"),
                    DB::raw("st.pay_method as pay_method"),
                    DB::raw("st.sent_at as f_cta"),
                    DB::raw("st.movement_at as f_mov"),
                    DB::raw("st.invoice_date as invoice_date"),
                    DB::raw("st.paid_at as paid_date"),
                    DB::raw("st.issued_at as sale_date"),
                    DB::raw("st.subtotal as subtotal"),
                    DB::raw("st.iva as iva"),
                    DB::raw("st.total as total"),
                    DB::raw("COALESCE(st.status,'pending') as statement_status"),
                    DB::raw("COALESCE(st.invoice_status,'sin_solicitud') as invoice_status"),
                    DB::raw("st.invoice_uuid as invoice_uuid"),
                    DB::raw("st.vendor_name as vendor_name"),
                ])
                ->where('st.period', '=', $month);

            if ($stStatus !== 'all') $q->where(DB::raw("COALESCE(st.status,'pending')"), $stStatus);

            // origin filter: recurrente vs no_recurrente
            if ($origin === 'no_recurrente') {
                $q->whereRaw('1=0'); // este query es solo recurrentes
            }

            if ($rfc !== '') $q->where('st.receiver_rfc', 'like', '%'.$rfc.'%');
            if ($payMethod !== '') $q->where('st.pay_method', 'like', '%'.$payMethod.'%');

            $recRows = collect($q->orderBy('st.account_id')->get());
        }

        // ============
        // Merge & normalize (aplica IVA 16% si faltara, y total)
        // ============
        $rows = $recRows->concat($salesRows)->map(function ($r) {
            $sub = (float) ($r->subtotal ?? 0);
            $iva = (float) ($r->iva ?? 0);
            if ($sub > 0 && $iva <= 0) {
                $iva = round($sub * 0.16, 2);
            }
            $tot = (float) ($r->total ?? 0);
            if ($tot <= 0 && ($sub > 0 || $iva > 0)) {
                $tot = round($sub + $iva, 2);
            }

            $r->subtotal = $sub;
            $r->iva = $iva;
            $r->total = $tot;

            return $r;
        });

        // KPIs
        $kpi = [
            'pending' => ['count'=>0,'total'=>0.0],
            'emitido' => ['count'=>0,'total'=>0.0],
            'pagado'  => ['count'=>0,'total'=>0.0],
            'all'     => ['count'=>0,'total'=>0.0],
        ];

        foreach ($rows as $r) {
            $st = (string) ($r->statement_status ?? 'pending');
            if (!isset($kpi[$st])) $st = 'pending';

            $kpi[$st]['count']++;
            $kpi[$st]['total'] += (float) $r->total;

            $kpi['all']['count']++;
            $kpi['all']['total'] += (float) $r->total;
        }

        // Vendors list
        $vendors = collect();
        if (Schema::connection($adm)->hasTable('finance_vendors')) {
            $vendors = collect(
                DB::connection($adm)->table('finance_vendors')
                    ->select(['id','name','is_active'])
                    ->orderBy('name')
                    ->get()
            );
        }

        return [
            'month'   => $month,
            'filters' => compact('origin','stStatus','invStatus','vendorId','rfc','payMethod'),
            'kpi'     => $kpi,
            'vendors' => $vendors,
            'rows'    => $rows,
        ];
    }
}
