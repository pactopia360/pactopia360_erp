<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SalesController extends Controller
{
    private string $adm;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function index(Request $req): View
    {
        // ✅ Por ahora: listado base desde finance_sales (si existe), sin romper.
        $q = DB::connection($this->adm)->table('finance_sales as s')
            ->leftJoin('finance_vendors as v', 'v.id', '=', 's.vendor_id')
            ->select([
                's.id',
                's.sale_code',
                's.receiver_rfc',
                's.origin',
                's.periodicity',
                's.subtotal',
                's.iva',
                's.total',
                's.statement_status',
                's.invoice_status',
                's.period',
                's.created_at',
                'v.name as vendor_name',
            ])
            ->orderByDesc('s.id');

        // Filtros ligeros (no obligatorios)
        if ($req->filled('period')) {
            $q->where('s.period', (string) $req->input('period'));
        }
        if ($req->filled('origin')) {
            $q->where('s.origin', (string) $req->input('origin'));
        }
        if ($req->filled('vendor_id')) {
            $q->where('s.vendor_id', (int) $req->input('vendor_id'));
        }

        $rows = $q->limit(500)->get();

        $kpis = [
            'total'   => (float) $rows->sum('total'),
            'pending' => (float) $rows->where('statement_status', 'pending')->sum('total'),
            'emitido' => (float) $rows->where('statement_status', 'emitido')->sum('total'),
            'pagado'  => (float) $rows->where('statement_status', 'pagado')->sum('total'),
        ];

        return view('admin.finance.sales.index', [
            'rows' => $rows,
            'kpis' => $kpis,
        ]);
    }

    public function create(): View
    {
        $vendors = DB::connection($this->adm)
            ->table('finance_vendors')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.finance.sales.create', compact('vendors'));
    }

    public function store(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'sale_code'     => ['nullable', 'string', 'max:80'],
            'receiver_rfc'  => ['nullable', 'string', 'max:20'],
            'origin'        => ['required', 'in:recurrente,no_recurrente,unico'],
            'periodicity'   => ['required', 'in:mensual,anual,unico'],
            'vendor_id'     => ['nullable', 'integer'],
            'subtotal'      => ['required', 'numeric', 'min:0'],
            'period'        => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $subtotal = (float) $data['subtotal'];
        $iva      = round($subtotal * 0.16, 2);
        $total    = round($subtotal + $iva, 2);

        DB::connection($this->adm)->table('finance_sales')->insert([
            'sale_code'        => $data['sale_code'] ?? null,
            'receiver_rfc'     => $data['receiver_rfc'] ?? null,
            'origin'           => $data['origin'],
            'periodicity'      => $data['periodicity'],
            'vendor_id'        => $data['vendor_id'] ?? null,
            'subtotal'         => $subtotal,
            'iva'              => $iva,
            'total'            => $total,
            'period'           => $data['period'] ?? null,

            // Estados iniciales (luego lo conectamos con EdoCta/Facturas reales)
            'statement_status' => 'pending',
            'invoice_status'   => 'pending',

            'meta'             => json_encode([], JSON_UNESCAPED_UNICODE),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return redirect()
            ->route('admin.finance.sales.index')
            ->with('ok', 'Venta registrada.');
    }
}
