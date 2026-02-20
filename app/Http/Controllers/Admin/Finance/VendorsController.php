<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\Admin\Finance\FinanceVendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class VendorsController extends Controller
{
    private string $conn;

    public function __construct()
    {
        $this->conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function index(Request $req): View
    {
        $q      = trim((string) $req->query('q', ''));
        $active = (string) $req->query('active', 'all'); // all|1|0

        $base = DB::connection($this->conn)->table('finance_vendors');

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('name', 'like', '%' . $q . '%')
                  ->orWhere('email', 'like', '%' . $q . '%')
                  ->orWhere('phone', 'like', '%' . $q . '%');
            });
        }

        if ($active === '1') $base->where('is_active', 1);
        if ($active === '0') $base->where('is_active', 0);

        $rows = $base
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // KPIs rápidos
        $kpiTotal    = (int) DB::connection($this->conn)->table('finance_vendors')->count();
        $kpiActive   = (int) DB::connection($this->conn)->table('finance_vendors')->where('is_active', 1)->count();
        $kpiInactive = max(0, $kpiTotal - $kpiActive);

        return view('admin.finance.vendors.index', [
            'rows'        => $rows,
            'q'           => $q,
            'active'      => $active,
            'kpiTotal'    => $kpiTotal,
            'kpiActive'   => $kpiActive,
            'kpiInactive' => $kpiInactive,
        ]);
    }

    public function create(): View
    {
        return view('admin.finance.vendors.form', [
            'mode' => 'create',
            'row'  => new FinanceVendor([
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $req): RedirectResponse
    {
        $data = $this->validateData($req);

        $m = new FinanceVendor();
        $m->setConnection($this->conn);
        $m->fill($data);
        $m->save();

        return redirect()
            ->route('admin.finance.vendors.index')
            ->with('status', 'Vendedor creado.');
    }

    public function edit(int $id): View
    {
        $m = FinanceVendor::on($this->conn)->findOrFail($id);

        return view('admin.finance.vendors.form', [
            'mode' => 'edit',
            'row'  => $m,
        ]);
    }

    public function update(Request $req, int $id): RedirectResponse
    {
        $m = FinanceVendor::on($this->conn)->findOrFail($id);

        $data = $this->validateData($req);

        $m->fill($data);
        $m->save();

        return redirect()
            ->route('admin.finance.vendors.index')
            ->with('status', 'Vendedor actualizado.');
    }

    public function toggle(Request $req, int $id): RedirectResponse
    {
        $m = FinanceVendor::on($this->conn)->findOrFail($id);

        $m->is_active = !$m->is_active;
        $m->save();

        return redirect()
            ->route('admin.finance.vendors.index', Arr::only($req->query(), ['q', 'active', 'page']))
            ->with('status', 'Estatus actualizado.');
    }

    private function validateData(Request $req): array
    {
        $v = $req->validate([
            'name'            => ['required', 'string', 'max:140'],
            'email'           => ['nullable', 'string', 'max:190'],
            'phone'           => ['nullable', 'string', 'max:40'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:1'], // 0.050 = 5%
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $v['is_active'] = (bool) ($req->boolean('is_active', true));

        // Limpieza básica
        $v['email'] = $v['email'] !== null ? trim((string) $v['email']) : null;
        $v['phone'] = $v['phone'] !== null ? trim((string) $v['phone']) : null;

        return $v;
    }
}
