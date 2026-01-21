<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Admin\SatDiscountCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class SatDiscountCodesController extends Controller
{
    public function index(Request $request): View
    {
        $q = SatDiscountCode::query()->orderByDesc('id');

        $active = $request->get('active');
        if ($active === '1') $q->where('active', 1);
        if ($active === '0') $q->where('active', 0);

        $scope = (string)($request->get('scope') ?? '');
        if ($scope !== '') $q->where('scope', $scope);

        $search = trim((string)($request->get('q') ?? ''));
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('code', 'like', '%' . $search . '%')
                  ->orWhere('label', 'like', '%' . $search . '%')
                  ->orWhere('partner_id', 'like', '%' . $search . '%')
                  ->orWhere('account_id', 'like', '%' . $search . '%');
            });
        }

        $rows = $q->paginate(30)->withQueryString();

        return view('admin.billing.sat.discounts.index', compact('rows', 'active', 'scope', 'search'));
    }

    public function create(): View
    {
        $row = new SatDiscountCode([
            'code' => 'SOCIO-' . strtoupper(Str::random(6)),
            'type' => 'percent',
            'pct' => 10,
            'amount_mxn' => 0,
            'scope' => 'partner',
            'partner_type' => 'socio',
            'active' => 1,
            'starts_at' => null,
            'ends_at' => null,
            'max_uses' => null,
        ]);

        return view('admin.billing.sat.discounts.form', compact('row'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        try {
            SatDiscountCode::create($data);
            return redirect()->route('admin.sat.discounts.index')->with('ok', 'Código creado.');
        } catch (\Throwable $e) {
            Log::error('[ADMIN][SAT_DISCOUNTS][store] error', ['err' => $e->getMessage()]);
            return back()->withInput()->with('error', 'No se pudo crear el código.');
        }
    }

    public function edit(int $id): View
    {
        $row = SatDiscountCode::query()->findOrFail($id);
        return view('admin.billing.sat.discounts.form', compact('row'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $this->validateData($request);

        try {
            $row = SatDiscountCode::query()->findOrFail($id);
            $row->update($data);
            return redirect()->route('admin.sat.discounts.index')->with('ok', 'Código actualizado.');
        } catch (\Throwable $e) {
            Log::error('[ADMIN][SAT_DISCOUNTS][update] error', ['id' => $id, 'err' => $e->getMessage()]);
            return back()->withInput()->with('error', 'No se pudo actualizar el código.');
        }
    }

    public function toggle(int $id): RedirectResponse
    {
        try {
            $row = SatDiscountCode::query()->findOrFail($id);
            $row->active = !$row->active;
            $row->save();

            return redirect()->route('admin.sat.discounts.index')->with('ok', 'Estado actualizado.');
        } catch (\Throwable $e) {
            Log::error('[ADMIN][SAT_DISCOUNTS][toggle] error', ['id' => $id, 'err' => $e->getMessage()]);
            return back()->with('error', 'No se pudo actualizar el estado.');
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $row = SatDiscountCode::query()->findOrFail($id);
            $row->delete();
            return redirect()->route('admin.sat.discounts.index')->with('ok', 'Código eliminado.');
        } catch (\Throwable $e) {
            Log::error('[ADMIN][SAT_DISCOUNTS][destroy] error', ['id' => $id, 'err' => $e->getMessage()]);
            return back()->with('error', 'No se pudo eliminar el código.');
        }
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required','string','max:64'],
            'label' => ['nullable','string','max:140'],

            'type' => ['required','in:percent,fixed'],
            'pct' => ['nullable','integer','min:0','max:90'],
            'amount_mxn' => ['nullable','numeric','min:0'],

            'scope' => ['required','in:global,account,partner'],
            'account_id' => ['nullable','string','max:64'],
            'partner_type' => ['nullable','in:socio,distribuidor'],
            'partner_id' => ['nullable','string','max:64'],

            'active' => ['nullable'],

            'starts_at' => ['nullable','date'],
            'ends_at' => ['nullable','date'],

            'max_uses' => ['nullable','integer','min:1','max:1000000'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['active'] = (bool)($request->input('active') ? true : false);

        $type = $data['type'];
        if ($type === 'fixed') {
            $data['pct'] = 0;
            $data['amount_mxn'] = isset($data['amount_mxn']) ? (float)$data['amount_mxn'] : 0.0;
        } else {
            $pct = (int)($data['pct'] ?? 0);
            if ($pct < 0) $pct = 0;
            if ($pct > 90) $pct = 90;
            $data['pct'] = $pct;
            $data['amount_mxn'] = 0.0;
        }

        if ($data['scope'] === 'global') {
            $data['account_id'] = null;
            $data['partner_type'] = null;
            $data['partner_id'] = null;
        }

        if ($data['scope'] === 'account') {
            $data['partner_type'] = null;
            $data['partner_id'] = null;
            $data['account_id'] = trim((string)($data['account_id'] ?? '')) ?: null;
        }

        if ($data['scope'] === 'partner') {
            $data['account_id'] = null;
            $data['partner_type'] = trim((string)($data['partner_type'] ?? '')) ?: 'socio';
            $data['partner_id'] = trim((string)($data['partner_id'] ?? '')) ?: null;
        }

        return $data;
    }
}
