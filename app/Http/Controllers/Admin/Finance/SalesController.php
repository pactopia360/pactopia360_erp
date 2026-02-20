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
            ->select([
                's.*',
                'v.name as vendor_name',
            ])
            ->orderByDesc('s.id');

        if ($req->filled('period')) {
            $q->where('s.period', (string) $req->input('period'));
        }
        if ($req->filled('origin')) {
            $q->where('s.origin', (string) $req->input('origin'));
        }
        if ($req->filled('vendor_id')) {
            $q->where('s.vendor_id', (int) $req->input('vendor_id'));
        }

        $rows = collect($q->limit(800)->get());

        // Mapear company desde mysql_clientes.cuentas_cliente (sin JOIN cross-db)
        $rows = $this->attachCompanyFromClientes($rows);

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

        // Cuentas cliente para seleccionar (limit para no matar UI)
        $accounts = collect();
        if (Schema::connection($this->cli)->hasTable('cuentas_cliente')) {
            $accounts = DB::connection($this->cli)->table('cuentas_cliente')
                ->select(['id', 'rfc_padre', 'razon_social', 'nombre_comercial', 'activo'])
                ->where('activo', 1)
                ->orderByRaw('COALESCE(nombre_comercial, razon_social) asc')
                ->limit(600)
                ->get();
        }

        return view('admin.finance.sales.create', compact('vendors', 'accounts'));
    }

    public function store(Request $req): RedirectResponse
    {
        if (!Schema::connection($this->adm)->hasTable('finance_sales')) {
            return redirect()->route('admin.finance.sales.index')->with('err', 'Falta la tabla finance_sales. Corre migraciones.');
        }

        $data = $req->validate([
            'account_id'    => ['required', 'string', 'size:36'],
            'sale_code'     => ['nullable', 'string', 'max:80'],
            'receiver_rfc'  => ['nullable', 'string', 'max:20'],
            'pay_method'    => ['nullable', 'string', 'max:60'],

            'origin'        => ['required', 'in:recurrente,no_recurrente,unico'],
            'periodicity'   => ['required', 'in:mensual,anual,unico'],
            'vendor_id'     => ['nullable', 'integer'],

            'period'        => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],

            'sale_date'     => ['nullable', 'date'],
            'f_cta'         => ['nullable', 'date'],
            'f_mov'         => ['nullable', 'date'],
            'invoice_date'  => ['nullable', 'date'],
            'paid_date'     => ['nullable', 'date'],

            'subtotal'      => ['required', 'numeric', 'min:0'],
            'statement_status' => ['required', 'in:pending,emitido,pagado,vencido'],
            'invoice_status'   => ['required', 'in:sin_solicitud,solicitada,en_proceso,facturada,rechazada'],
            'cfdi_uuid'        => ['nullable', 'string', 'max:64'],

            'include_in_statement' => ['nullable'],
            'notes' => ['nullable', 'string'],
        ]);

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

        DB::connection($this->adm)->table('finance_sales')->insert([
            'account_id'             => (string) $data['account_id'],
            'sale_code'              => $data['sale_code'] ?? null,
            'receiver_rfc'           => $data['receiver_rfc'] ?? null,
            'pay_method'             => $data['pay_method'] ?? null,

            'origin'                 => $data['origin'],
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

            'include_in_statement'   => $include ? 1 : 0,
            'statement_period_target'=> $include ? $target : null,
            'statement_id'           => null,
            'statement_item_id'      => null,

            'notes'                  => $data['notes'] ?? null,
            'meta'                   => json_encode([], JSON_UNESCAPED_UNICODE),

            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        return redirect()
            ->route('admin.finance.sales.index')
            ->with('ok', 'Venta registrada.');
    }

    private function attachCompanyFromClientes(Collection $rows): Collection
    {
        $ids = $rows->pluck('account_id')->filter()->unique()->values()->all();
        if (empty($ids)) return $rows;

        if (!Schema::connection($this->cli)->hasTable('cuentas_cliente')) return $rows;

        $map = DB::connection($this->cli)->table('cuentas_cliente')
            ->select(['id', 'razon_social', 'nombre_comercial', 'rfc_padre'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return $rows->map(function ($r) use ($map) {
            $c = $map->get((string) $r->account_id);

            $r->company = $c
                ? (string) (($c->nombre_comercial ?: null) ?? ($c->razon_social ?: null) ?? ('Cuenta ' . $r->account_id))
                : (string) ('Cuenta ' . $r->account_id);

            $r->rfc_emisor = $c ? (string) ($c->rfc_padre ?? '') : '';

            return $r;
        });
    }
}