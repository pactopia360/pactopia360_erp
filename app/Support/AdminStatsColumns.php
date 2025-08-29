<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminStatsColumns
{
    public static function detect(string $conn = 'mysql_admin'): array
    {
        // Tablas candidatas
        $tPagos  = Schema::connection($conn)->hasTable('payments') ? 'payments'
                 : (Schema::connection($conn)->hasTable('pagos') ? 'pagos' : null);

        $tCtes   = Schema::connection($conn)->hasTable('accounts') ? 'accounts'
                 : (Schema::connection($conn)->hasTable('clientes') ? 'clientes' : null);

        $tPlanes = Schema::connection($conn)->hasTable('plans') ? 'plans'
                 : (Schema::connection($conn)->hasTable('planes') ? 'planes' : null);

        $tCfdi   = Schema::connection($conn)->hasTable('cfdis') ? 'cfdis' : null;

        $warn = [];
        if (!$tPagos) $warn[] = 'No se encontró tabla de pagos (payments/pagos).';
        if (!$tCtes)  $warn[] = 'No se encontró tabla de clientes (accounts/clientes).';

        $pagCols = $tPagos ? Schema::connection($conn)->getColumnListing($tPagos) : [];
        $cteCols = $tCtes  ? Schema::connection($conn)->getColumnListing($tCtes)  : [];
        $plaCols = $tPlanes? Schema::connection($conn)->getColumnListing($tPlanes): [];

        $c = fn($arr, $cands)=>collect($cands)->first(fn($x)=>in_array($x, $arr, true));

        $colPagoCliente = $c($pagCols, ['account_id','cliente_id','account']);
        $colPagoMonto   = $c($pagCols, ['amount','monto','total','importe']);
        $colPagoFecha   = $c($pagCols, ['created_at','fecha','date','paid_at']);

        $colCteId       = $c($cteCols, ['id']);
        $colCtePlanId   = $c($cteCols, ['plan_id']);
        $colCtePlanTxt  = $c($cteCols, ['plan']); // texto libre

        $colPlanId      = $c($plaCols, ['id','id_plan']);
        $colPlanClave   = $c($plaCols, ['code','clave','name','nombre','nombre_plan']);

        if (!$colPagoCliente) $warn[] = "{$tPagos}.(account_id/cliente_id) no existe → ingresos por plan/top clientes usarán fallback.";
        if (!$colPagoMonto || !$colPagoFecha) $warn[] = "Faltan columnas de monto/fecha en {$tPagos}.";

        return [
            'tables' => ['pagos'=>$tPagos, 'clientes'=>$tCtes, 'planes'=>$tPlanes, 'cfdi'=>$tCfdi],
            'cols'   => [
                'pago' => ['cliente'=>$colPagoCliente, 'monto'=>$colPagoMonto, 'fecha'=>$colPagoFecha],
                'cte'  => ['id'=>$colCteId, 'plan_id'=>$colCtePlanId, 'plan_txt'=>$colCtePlanTxt],
                'plan' => ['id'=>$colPlanId, 'clave'=>$colPlanClave],
            ],
            'warnings' => $warn,
        ];
    }
}
