<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing\Sat;

use App\Http\Controllers\Controller;
use App\Models\Admin\SatDiscountCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SatDiscountCodesController extends Controller
{
    // ✅ Rutas canónicas
    private const ROUTE_INDEX   = 'admin.sat.discounts.index';
    private const ROUTE_CREATE  = 'admin.sat.discounts.create';
    private const ROUTE_EDIT    = 'admin.sat.discounts.edit';
    private const ROUTE_STORE   = 'admin.sat.discounts.store';
    private const ROUTE_UPDATE  = 'admin.sat.discounts.update';
    private const ROUTE_TOGGLE  = 'admin.sat.discounts.toggle';
    private const ROUTE_DESTROY = 'admin.sat.discounts.destroy';

    // ✅ Vistas (las tuyas actuales)
    private const VIEW_INDEX = 'admin.billing.sat.discounts.index';
    private const VIEW_FORM  = 'admin.billing.sat.discounts.form';

    public function index(Request $request): View
    {
        $q     = trim((string) $request->get('q', ''));
        $scope = trim((string) $request->get('scope', ''));
        $act   = trim((string) $request->get('active', ''));

        $rows = SatDiscountCode::query()
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($qq) use ($q) {
                    $qq->where('code', 'like', '%' . $q . '%')
                       ->orWhere('label', 'like', '%' . $q . '%')
                       ->orWhere('account_id', 'like', '%' . $q . '%')
                       ->orWhere('partner_id', 'like', '%' . $q . '%');
                });
            })
            ->when($scope !== '', fn ($w) => $w->where('scope', $scope))
            ->when($act !== '', fn ($w) => $w->where('active', (int) $act))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view(self::VIEW_INDEX, [
            'rows'   => $rows,
            'q'      => $q,
            'scope'  => $scope,
            'active' => $act,
        ]);
    }

    public function create(): View
    {
        $row = new SatDiscountCode([
            'active'      => 1,
            'type'        => 'percent', // percent|fixed
            'pct'         => 10,
            'amount_mxn'  => 0,
            'scope'       => 'global',  // global|account|partner
            'max_uses'    => null,
            'uses_count'  => 0,
            'starts_at'   => null,
            'ends_at'     => null,
        ]);

        return view(self::VIEW_FORM, [
            'mode' => 'create',
            'row'  => $row,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        /** @var SatDiscountCode $row */
        $row = DB::connection('mysql_admin')->transaction(function () use ($data) {
            return SatDiscountCode::create($data);
        });

        return redirect()
            ->route(self::ROUTE_INDEX)
            ->with('success', 'Código creado (#' . $row->id . ').');
    }

    public function edit(int $id): View
    {
        $row = SatDiscountCode::query()->findOrFail($id);

        return view(self::VIEW_FORM, [
            'mode' => 'edit',
            'row'  => $row,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $row  = SatDiscountCode::query()->findOrFail($id);
        $data = $this->validatePayload($request, $row->id);

        DB::connection('mysql_admin')->transaction(function () use ($row, $data) {
            $row->fill($data);
            $row->save();
        });

        return redirect()
            ->route(self::ROUTE_INDEX)
            ->with('success', 'Código actualizado (#' . $row->id . ').');
    }

    public function destroy(int $id): RedirectResponse
    {
        $row = SatDiscountCode::query()->findOrFail($id);

        DB::connection('mysql_admin')->transaction(function () use ($row) {
            $row->delete();
        });

        return redirect()
            ->route(self::ROUTE_INDEX)
            ->with('success', 'Código eliminado.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $row = SatDiscountCode::query()->findOrFail($id);

        DB::connection('mysql_admin')->transaction(function () use ($row) {
            $row->active = !(bool) $row->active;
            $row->save();
        });

        return redirect()
            ->route(self::ROUTE_INDEX)
            ->with('success', 'Estado actualizado.');
    }

    private function validatePayload(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'code'         => [
                'required', 'string', 'max:64',
                Rule::unique((new SatDiscountCode)->getTable(), 'code')->ignore($id),
            ],
            'label'        => ['nullable', 'string', 'max:120'],
            'active'       => ['nullable', 'boolean'],
            'type'         => ['required', 'in:percent,fixed'],
            'pct'          => ['nullable', 'integer', 'min:0', 'max:90'],
            'amount_mxn'   => ['nullable', 'numeric', 'min:0'],
            'scope'        => ['required', 'in:global,account,partner'],
            'account_id'   => ['nullable', 'string', 'max:64'],
            'partner_type' => ['nullable', 'string', 'max:32'],
            'partner_id'   => ['nullable', 'string', 'max:64'],
            'starts_at'    => ['nullable', 'date'],
            'ends_at'      => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses'     => ['nullable', 'integer', 'min:0'],
        ]);

        $data['code']   = strtoupper(trim((string) $data['code']));
        $data['active'] = (bool) ($data['active'] ?? false);

        $starts = !empty($data['starts_at']) ? Carbon::parse((string) $data['starts_at'])->startOfDay() : null;
        $ends   = !empty($data['ends_at'])   ? Carbon::parse((string) $data['ends_at'])->endOfDay()     : null;

        $data['starts_at'] = $starts;
        $data['ends_at']   = $ends;

        $scope = (string) ($data['scope'] ?? 'global');
        if ($scope === 'global') {
            $data['account_id']   = null;
            $data['partner_type'] = null;
            $data['partner_id']   = null;
        } elseif ($scope === 'account') {
            $data['partner_type'] = null;
            $data['partner_id']   = null;
        }

        if (($data['type'] ?? '') === 'fixed') {
            $data['pct']        = 0;
            $data['amount_mxn'] = (float) ($data['amount_mxn'] ?? 0);
        } else {
            $data['amount_mxn'] = 0;
            $data['pct']        = (int) ($data['pct'] ?? 0);
        }

        return $data;
    }
}
