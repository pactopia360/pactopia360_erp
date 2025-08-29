<?php
// app/Services/Admin/Home/IncomeService.php

namespace App\Services\Admin\Home;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Servicio de ingresos (Pagos + Facturas) para dashboard Admin.
 * Tolerante a diferencias de nombres de tabla/columnas.
 */
class IncomeService
{
    protected string $adminConn    = 'mysql_admin';
    protected string $clientesConn = 'mysql_clientes';

    // Candidatos de tablas
    protected array $paymentTables = ['pagos','ingresos','payments','movimientos_cobranza'];
    protected array $invoiceTables = ['facturas','invoices','cfdi','comprobantes','facturacion'];

    /** Últimos 12 meses (pagos admin + facturas clientes si existen) */
    public function monthlySeries(): array
    {
        return $this->monthlySeriesN(12);
    }

    /** Serie diaria (pagos vs facturas) del mes YYYY-MM, usando StatsService defensivo */
    public function dailySeriesFromYm(string $ym): array
    {
        return (new StatsService)->pvfForMonth($ym);
    }

    /** YTD vs Año anterior (suma mensual) */
    public function ytd(int $year): array
    {
        $labels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        $current = $this->yearMonthlyTotals($year);
        $prev    = $this->yearMonthlyTotals($year-1);
        return ['labels'=>$labels, 'current'=>$current, 'prev'=>$prev, 'year'=>$year];
    }

    /* ================== Helpers ================== */

    public function monthlySeriesN(int $months): array
    {
        $now = Carbon::now()->startOfMonth();
        $startWindow = (clone $now)->subMonths($months-1)->startOfMonth();
        $labels = []; $values = []; $map = [];

        for ($i=$months-1; $i>=0; $i--) {
            $m = (clone $now)->subMonths($i);
            $ym = $m->format('Y-m'); $map[$ym] = 0.0; $labels[] = $m->isoFormat('MMM YYYY');
        }

        // pagos (admin)
        [$pTbl,$pDate,$pAmount] = $this->paymentTableInfo();
        if ($pTbl) {
            $rows = DB::connection($this->adminConn)->table($pTbl)
                ->selectRaw("DATE_FORMAT($pDate, '%Y-%m') as ym, SUM($pAmount) as total")
                ->whereBetween($pDate, [$startWindow, $now->copy()->endOfMonth()])
                ->groupBy('ym')->pluck('total','ym');
            foreach ($rows as $ym=>$sum) if (isset($map[$ym])) $map[$ym] += (float)$sum;
        }

        // facturas (clientes)
        [$fTbl,$fDate,$fAmount] = $this->invoiceTableInfo();
        if ($fTbl) {
            $rows = DB::connection($this->clientesConn)->table($fTbl)
                ->selectRaw("DATE_FORMAT($fDate, '%Y-%m') as ym, SUM($fAmount) as total")
                ->whereBetween($fDate, [$startWindow, $now->copy()->endOfMonth()])
                ->groupBy('ym')->pluck('total','ym');
            foreach ($rows as $ym=>$sum) if (isset($map[$ym])) $map[$ym] += (float)$sum;
        }

        foreach ($map as $ym=>$v) $values[] = round($v,2);
        return ['labels'=>$labels,'values'=>$values];
    }

    protected function yearMonthlyTotals(int $year): array
    {
        $start = Carbon::create($year,1,1)->startOfDay();
        $end   = Carbon::create($year,12,31)->endOfDay();
        $out   = array_fill(0, 12, 0.0);

        [$pTbl,$pDate,$pAmount] = $this->paymentTableInfo();
        if ($pTbl) {
            $rows = DB::connection($this->adminConn)->table($pTbl)
                ->selectRaw("MONTH($pDate) as m, SUM($pAmount) as t")->whereBetween($pDate, [$start,$end])
                ->groupBy('m')->pluck('t','m');
            foreach ($rows as $m=>$t) $out[$m-1] += (float)$t;
        }

        [$fTbl,$fDate,$fAmount] = $this->invoiceTableInfo();
        if ($fTbl) {
            $rows = DB::connection($this->clientesConn)->table($fTbl)
                ->selectRaw("MONTH($fDate) as m, SUM($fAmount) as t")->whereBetween($fDate, [$start,$end])
                ->groupBy('m')->pluck('t','m');
            foreach ($rows as $m=>$t) $out[$m-1] += (float)$t;
        }

        return array_map(fn($v)=>round($v,2), $out);
    }

    protected function paymentTableInfo(): array
    {
        $tbl = $this->pickTable($this->adminConn, $this->paymentTables);
        if (!$tbl) return [null,null,null];
        $date   = $this->firstColumn($this->adminConn, $tbl, ['fecha','fecha_pago','created_at']) ?? 'created_at';
        $amount = $this->firstColumn($this->adminConn, $tbl, ['monto','total','importe']) ?? 'monto';
        return [$tbl,$date,$amount];
    }

    protected function invoiceTableInfo(): array
    {
        $tbl = $this->pickTable($this->clientesConn, $this->invoiceTables);
        if (!$tbl) return [null,null,null];
        $date   = $this->firstColumn($this->clientesConn, $tbl, ['fecha','fecha_emision','created_at']) ?? 'created_at';
        $amount = $this->firstColumn($this->clientesConn, $tbl, ['total','monto','importe','subtotal']) ?? 'total';
        return [$tbl,$date,$amount];
    }

    protected function pickTable(string $conn, array $candidates): ?string
    {
        foreach ($candidates as $t) if (Schema::connection($conn)->hasTable($t)) return $t;
        return null;
    }

    protected function firstColumn(string $conn, string $table, array $candidates): ?string
    {
        foreach ($candidates as $c) if (Schema::connection($conn)->hasColumn($table,$c)) return $c;
        return null;
    }
}
