<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class SalesController extends Controller
{
    private string $adm;
    private string $cli;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $this->cli = (string) (config('p360.conn.clientes') ?: 'mysql_clientes');
    }

    public function index(Request $req): View
    {
        if (!Schema::connection($this->adm)->hasTable('finance_sales')) {
            return view('admin.finance.sales.index', [
                'rows' => collect(),
                'kpis' => [
                    'total' => 0.0, 'pending' => 0.0, 'emitido' => 0.0, 'pagado' => 0.0,
                ],
            ]);
        }

        $q = DB::connection($this->adm)->table('finance_sales as s')
            ->leftJoin('finance_vendors as v', 'v.id', '=', 's.vendor_id')
            ->leftJoin('accounts as a', 'a.id', '=', 's.account_id')
            ->select([
                's.*',
                'v.name as vendor_name',
                'a.name as account_name',
                'a.razon_social as account_razon_social',
                'a.rfc as account_rfc',
            ])
            ->orderByDesc('s.id');

        // period YYYY-MM
        if ($req->filled('period') && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)$req->input('period'))) {
            $q->where('s.period', (string) $req->input('period'));
        }

        // origin: all|recurrente|no_recurrente|unico
        if ($req->filled('origin')) {
            $origin = strtolower(trim((string)$req->input('origin')));
            if ($origin !== '' && $origin !== 'all') {
                if ($origin === 'unico') $origin = 'no_recurrente';
                $q->where('s.origin', $origin);
            }
        }

        if ($req->filled('vendor_id')) {
            $vid = (string)$req->input('vendor_id');
            if ($vid !== '' && $vid !== 'all') {
                $q->where('s.vendor_id', (int) $vid);
            }
        }

        if ($req->filled('statement_status')) {
            $st = strtolower(trim((string)$req->input('statement_status')));
            if ($st !== '' && $st !== 'all') {
                $q->where('s.statement_status', $st);
            }
        }

        if ($req->filled('invoice_status')) {
            $ist = strtolower(trim((string)$req->input('invoice_status')));
            if ($ist !== '' && $ist !== 'all') {
                $q->where('s.invoice_status', $ist);
            }
        }

        $rows = collect($q->limit(800)->get());

        // Mapear company desde mysql_clientes.cuentas_cliente usando RFC (sin JOIN cross-db)
        $rows = $this->attachCompanyFromClientesByRfc($rows);

        $kpis = [
            'total'   => round((float) $rows->sum('total'), 2),
            'pending' => round((float) $rows->where('statement_status', 'pending')->sum('total'), 2),
            'emitido' => round((float) $rows->where('statement_status', 'emitido')->sum('total'), 2),
            'pagado'  => round((float) $rows->where('statement_status', 'pagado')->sum('total'), 2),
        ];

        return view('admin.finance.sales.index', [
            'rows' => $rows,
            'kpis' => $kpis,
        ]);
    }

    public function create(): View
    {
        $vendors = Schema::connection($this->adm)->hasTable('finance_vendors')
            ? DB::connection($this->adm)->table('finance_vendors')->where('is_active', 1)->orderBy('name')->get(['id', 'name'])
            : collect();

        // ✅ Cuentas admin (accounts.id) — esto es lo que va en finance_sales.account_id (idealmente BIGINT)
        $accounts = Schema::connection($this->adm)->hasTable('accounts')
            ? DB::connection($this->adm)->table('accounts')
                ->select(['id', 'rfc', 'razon_social', 'name'])
                ->orderByRaw('COALESCE(razon_social, name) asc')
                ->limit(800)
                ->get()
            : collect();

        return view('admin.finance.sales.create', compact('vendors', 'accounts'));
    }

    public function store(Request $req): RedirectResponse
    {
        if (!Schema::connection($this->adm)->hasTable('finance_sales')) {
            return redirect()->route('admin.finance.sales.index')->with('err', 'Falta la tabla finance_sales. Corre migraciones.');
        }

        $data = $req->validate([
            'account_id'    => ['required', 'integer', 'min:1'],
            'sale_code'     => ['nullable', 'string', 'max:80'],
            'receiver_rfc'  => ['nullable', 'string', 'max:20'],
            'pay_method'    => ['nullable', 'string', 'max:60'],

            // UI puede mandar unico; DB guarda no_recurrente
            'origin'        => ['required', 'in:recurrente,no_recurrente,unico'],
            'periodicity'   => ['required', 'in:mensual,anual,unico'],
            'vendor_id'     => ['nullable', 'integer'],

            'period'        => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],

            'sale_date'     => ['nullable', 'date'],
            'f_cta'         => ['nullable', 'date'],
            'f_mov'         => ['nullable', 'date'],
            'invoice_date'  => ['nullable', 'date'],
            'paid_date'     => ['nullable', 'date'],

            'subtotal'         => ['required', 'numeric', 'min:0'],

            // tu UI/servicio usa vencido también; DB permite string, lo dejamos pasar
            'statement_status' => ['required', 'in:pending,emitido,pagado,vencido'],
            'invoice_status'   => ['required', 'in:sin_solicitud,solicitada,en_proceso,facturada,rechazada'],
            'cfdi_uuid'        => ['nullable', 'string', 'max:64'],

            'include_in_statement' => ['nullable'],
            'notes' => ['nullable', 'string'],
        ]);

        // Normaliza origin
        $origin = strtolower((string)$data['origin']);
        if ($origin === 'unico') $origin = 'no_recurrente';

        // Validar account en admin
        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            return redirect()->back()->with('err', 'Falta la tabla accounts en mysql_admin.');
        }
        $account = DB::connection($this->adm)->table('accounts')->where('id', (int) $data['account_id'])->first(['id', 'rfc', 'razon_social', 'name']);
        if (!$account) {
            return redirect()->back()->with('err', 'La cuenta seleccionada no existe en accounts.');
        }

        $subtotal = round((float) $data['subtotal'], 2);
        $iva      = round($subtotal * 0.16, 2);
        $total    = round($subtotal + $iva, 2);

        $include = $req->boolean('include_in_statement', false);

        // target: mes siguiente al period
        $target = null;
        try {
            $d = Carbon::createFromFormat('Y-m', (string) $data['period'])->startOfMonth()->addMonth();
            $target = $d->format('Y-m');
        } catch (\Throwable $e) {
            $target = null;
        }

        $saleId = null;

        DB::connection($this->adm)->transaction(function () use ($data, $origin, $subtotal, $iva, $total, $include, $target, &$saleId) {

            $saleId = (int) DB::connection($this->adm)->table('finance_sales')->insertGetId([
                'account_id'             => (int) $data['account_id'],
                'sale_code'              => $data['sale_code'] ?? null,
                'receiver_rfc'           => $data['receiver_rfc'] ?? null,
                'pay_method'             => $data['pay_method'] ?? null,

                'origin'                 => $origin,
                'periodicity'            => $data['periodicity'],
                'vendor_id'              => $data['vendor_id'] ?? null,
                'period'                 => (string) $data['period'],

                'sale_date'              => $data['sale_date'] ?? null,
                'f_cta'                  => $data['f_cta'] ?? null,
                'f_mov'                  => $data['f_mov'] ?? null,
                'invoice_date'           => $data['invoice_date'] ?? null,
                'paid_date'              => $data['paid_date'] ?? null,

                'subtotal'               => $subtotal,
                'iva'                    => $iva,
                'total'                  => $total,

                'statement_status'       => (string) $data['statement_status'],
                'invoice_status'         => (string) $data['invoice_status'],
                'cfdi_uuid'              => $data['cfdi_uuid'] ?? null,

                'include_in_statement'    => $include ? 1 : 0,
                'statement_period_target' => $include ? $target : null,
                'statement_id'            => null,
                'statement_item_id'       => null,

                'notes'                  => $data['notes'] ?? null,
                'meta'                   => json_encode([], JSON_UNESCAPED_UNICODE),

                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            if ($include) {
                $sale = DB::connection($this->adm)->table('finance_sales')->where('id', $saleId)->first();
                if ($sale) {
                    $this->syncEstadoCuentaLine($sale);
                }
            }
        });

        return redirect()
            ->route('admin.finance.sales.index')
            ->with('ok', 'Venta registrada.');
    }

    /**
     * POST admin/finance/sales/{id}/toggle-include
     * - Si activa include: crea/actualiza línea en estados_cuenta (idempotente)
     * - Si desactiva: elimina SOLO la línea de esta venta (por source/ref)
     */
    public function toggleInclude(int $id): RedirectResponse
    {
        if (!Schema::connection($this->adm)->hasTable('finance_sales')) {
            return redirect()
                ->route('admin.finance.sales.index')
                ->with('err', 'Falta la tabla finance_sales.');
        }

        $row = DB::connection($this->adm)->table('finance_sales')->where('id', $id)->first();

        if (!$row) {
            return redirect()
                ->route('admin.finance.sales.index')
                ->with('err', 'Venta no encontrada.');
        }

        $isIncluded = (int)($row->include_in_statement ?? 0) === 1;

        // Si hoy está incluido, lo quitamos y limpiamos vínculos + BORRAMOS línea en estados_cuenta
        if ($isIncluded) {

            // 1) borrar línea
            $this->deleteEstadoCuentaLine((int)$row->id);

            // 2) limpiar columnas (safe por si no existen)
            $this->updateFinanceSalesColumnsSafe((int)$row->id, [
                'include_in_statement'    => 0,
                'target_period'           => null,
                'statement_period_target' => null,
                'statement_id'            => null,
                'statement_item_id'       => null,
                'updated_at'              => now(),
            ]);

            return redirect()
                ->route('admin.finance.sales.index')
                ->with('ok', 'Se quitó del Estado de Cuenta.');
        }

        // Si NO está incluido, lo incluimos y calculamos el target (mes siguiente al period)
        $period = (string)($row->period ?? '');
        $target = null;

        try {
            $d = Carbon::createFromFormat('Y-m', $period)->startOfMonth()->addMonth();
            $target = $d->format('Y-m');
        } catch (\Throwable $e) {
            $target = null;
        }

        $this->updateFinanceSalesColumnsSafe((int)$row->id, [
            'include_in_statement'    => 1,
            'target_period'           => $target,
            'statement_period_target' => $target,
            'updated_at'              => now(),
        ]);

        // recargar la venta ya actualizada (para statement_period_target)
        $sale = DB::connection($this->adm)->table('finance_sales')->where('id', $id)->first();
        if ($sale) {
            $this->syncEstadoCuentaLine($sale);
        }

        return redirect()
            ->route('admin.finance.sales.index')
            ->with('ok', 'Se agregó al Estado de Cuenta (Paso 2).');
    }

    /**
     * Update SAFE: solo actualiza columnas que existan realmente en finance_sales.
     */
    private function updateFinanceSalesColumnsSafe(int $id, array $data): void
    {
        $table = 'finance_sales';
        if (!Schema::connection($this->adm)->hasTable($table)) return;

        $safe = [];
        foreach ($data as $col => $val) {
            if (Schema::connection($this->adm)->hasColumn($table, $col)) {
                $safe[$col] = $val;
            }
        }

        if (empty($safe)) return;

        DB::connection($this->adm)->table($table)->where('id', $id)->update($safe);
    }

    /**
     * Construye/actualiza una línea en estados_cuenta para una venta.
     * Identidad: (source='finance_sale', ref='finance_sale:{sale_id}')
     */
    private function syncEstadoCuentaLine(object $sale): void
    {
        if (!Schema::connection($this->adm)->hasTable('estados_cuenta')) return;

        $periodo = (string) ($sale->statement_period_target ?: '');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) {
            return;
        }

        $accountId = (int) ($sale->account_id ?? 0);
        if ($accountId <= 0) return;

        $ref    = $this->saleRef((int) $sale->id);
        $source = 'finance_sale';

        $concepto = 'Venta';
        if (!empty($sale->sale_code)) {
            $concepto .= ' ' . (string) $sale->sale_code;
        }
        $concepto .= ' · ' . $periodo;

        $detalleParts = [];
        if (!empty($sale->receiver_rfc)) $detalleParts[] = 'RFC: ' . (string) $sale->receiver_rfc;
        if (!empty($sale->pay_method))   $detalleParts[] = 'Método: ' . (string) $sale->pay_method;
        $detalleParts[] = 'VentaID: ' . (int) $sale->id;
        $detalle = implode(' · ', $detalleParts);

        $cargo = round((float) ($sale->total ?? 0), 2);

        $meta = [
            'finance_sale_id' => (int) $sale->id,
            'period'          => (string) ($sale->period ?? null),
            'origin'          => (string) ($sale->origin ?? null),
            'periodicity'     => (string) ($sale->periodicity ?? null),
        ];

        $exists = DB::connection($this->adm)->table('estados_cuenta')
            ->where('source', $source)
            ->where('ref', $ref)
            ->first(['id']);

        $payload = [
            'account_id' => $accountId,
            'periodo'    => $periodo,
            'concepto'   => $concepto,
            'detalle'    => $detalle,
            'cargo'      => $cargo,
            'abono'      => 0.00,
            'source'     => $source,
            'ref'        => $ref,
            'meta'       => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];

        if ($exists) {
            DB::connection($this->adm)->table('estados_cuenta')->where('id', (int) $exists->id)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::connection($this->adm)->table('estados_cuenta')->insert($payload);
        }
    }

    /**
     * Elimina SOLO la línea asociada a esta venta (por source/ref).
     */
    private function deleteEstadoCuentaLine(int $saleId): void
    {
        if (!Schema::connection($this->adm)->hasTable('estados_cuenta')) return;

        DB::connection($this->adm)->table('estados_cuenta')
            ->where('source', 'finance_sale')
            ->where('ref', $this->saleRef($saleId))
            ->delete();
    }

    private function saleRef(int $saleId): string
    {
        return 'finance_sale:' . $saleId;
    }

    /**
     * Enriquecer display con datos de mysql_clientes, pero usando RFC (no ids).
     */
    private function attachCompanyFromClientesByRfc(Collection $rows): Collection
    {
        if ($rows->isEmpty()) return $rows;

        if (!Schema::connection($this->cli)->hasTable('cuentas_cliente')) {
            return $rows->map(function ($r) {
                $r->company = (string) (($r->account_razon_social ?: null) ?? ($r->account_name ?: null) ?? ('Cuenta ' . ($r->account_id ?? '')));
                $r->rfc_emisor = (string) ($r->account_rfc ?? '');
                return $r;
            });
        }

        $rfcs = $rows->pluck('account_rfc')->filter()->unique()->values()->all();
        if (empty($rfcs)) {
            return $rows->map(function ($r) {
                $r->company = (string) (($r->account_razon_social ?: null) ?? ($r->account_name ?: null) ?? ('Cuenta ' . ($r->account_id ?? '')));
                $r->rfc_emisor = (string) ($r->account_rfc ?? '');
                return $r;
            });
        }

        $map = DB::connection($this->cli)->table('cuentas_cliente')
            ->select(['rfc_padre', 'razon_social', 'nombre_comercial'])
            ->whereIn('rfc_padre', $rfcs)
            ->get()
            ->keyBy('rfc_padre');

        return $rows->map(function ($r) use ($map) {
            $rfc = (string) ($r->account_rfc ?? '');
            $c = $rfc !== '' ? $map->get($rfc) : null;

            $r->company = $c
                ? (string) (($c->nombre_comercial ?: null) ?? ($c->razon_social ?: null) ?? (($r->account_razon_social ?: null) ?? ($r->account_name ?: null) ?? ('Cuenta ' . ($r->account_id ?? ''))))
                : (string) (($r->account_razon_social ?: null) ?? ($r->account_name ?: null) ?? ('Cuenta ' . ($r->account_id ?? '')));

            $r->rfc_emisor = $rfc;

            return $r;
        });
    }
}