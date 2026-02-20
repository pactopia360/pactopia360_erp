<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\Admin\Finance\FinanceVendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $kpiTotal  = (int) DB::connection($this->conn)->table('finance_vendors')->count();
        $kpiActive = (int) DB::connection($this->conn)->table('finance_vendors')->where('is_active', 1)->count();
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
        return view('admin.finance.vendors.create');
    }

    public function store(Request $req): RedirectResponse
    {
        $data = $req->validate([
            'name'                   => ['required', 'string', 'max:140'],
            'email'                  => ['nullable', 'string', 'max:190'],
            'phone'                  => ['nullable', 'string', 'max:40'],
            'default_commission_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active'              => ['nullable'],
        ]);

        $m = new FinanceVendor();
        $m->setConnection($this->conn);

        $m->name  = trim((string) $data['name']);
        $m->email = isset($data['email']) && $data['email'] !== '' ? trim((string) $data['email']) : null;
        $m->phone = isset($data['phone']) && $data['phone'] !== '' ? trim((string) $data['phone']) : null;

        $pct = $data['default_commission_pct'] ?? null;
        $m->default_commission_pct = ($pct === null || $pct === '') ? null : (float) $pct;

        $m->is_active = $req->boolean('is_active', true);

        $m->meta = [];
        $m->save();

        return redirect()
            ->route('admin.finance.vendors.index')
            ->with('ok', 'Vendedor creado.');
    }
}