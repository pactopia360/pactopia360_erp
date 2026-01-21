<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing\Sat;

use App\Http\Controllers\Controller;
use App\Models\Admin\SatPriceRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SatPriceRulesController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string)$request->get('q', ''));

        $rows = SatPriceRule::query()
            ->when($q !== '', function ($w) use ($q) {
                $w->where('name', 'like', '%' . $q . '%')
                  ->orWhere('unit', 'like', '%' . $q . '%');
            })
            ->orderBy('sort', 'asc')
            ->orderBy('min_xml', 'asc')
            ->paginate(30)
            ->withQueryString();

        return view('admin.billing.sat.prices.index', [
            'rows' => $rows,
            'q'    => $q,
        ]);
    }

    public function create(): View
    {
        $row = new SatPriceRule([
            'active'        => 1,
            'unit'          => 'range_per_xml',
            'min_xml'       => 1,
            'max_xml'       => null,
            'price_per_xml' => 0,
            'flat_price'    => 0,
            'currency'      => 'MXN',
            'sort'          => 10,
        ]);

        return view('admin.billing.sat.prices.form', [
            'mode' => 'create',
            'row'  => $row,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        /** @var SatPriceRule $row */
        $row = DB::connection('mysql_admin')->transaction(function () use ($data) {
            return SatPriceRule::create($data);
        });

        return redirect()
            ->route('admin.billing.sat.prices.index')
            ->with('success', 'Regla de precio creada (#' . $row->id . ').');
    }

    public function edit(int $id): View
    {
        $row = SatPriceRule::query()->findOrFail($id);

        return view('admin.billing.sat.prices.form', [
            'mode' => 'edit',
            'row'  => $row,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $row  = SatPriceRule::query()->findOrFail($id);
        $data = $this->validatePayload($request, $row->id);

        DB::connection('mysql_admin')->transaction(function () use ($row, $data) {
            $row->fill($data);
            $row->save();
        });

        return redirect()
            ->route('admin.billing.sat.prices.index')
            ->with('success', 'Regla de precio actualizada (#' . $row->id . ').');
    }

    public function destroy(int $id): RedirectResponse
    {
        $row = SatPriceRule::query()->findOrFail($id);

        DB::connection('mysql_admin')->transaction(function () use ($row) {
            $row->delete();
        });

        return redirect()
            ->route('admin.billing.sat.prices.index')
            ->with('success', 'Regla eliminada.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $row = SatPriceRule::query()->findOrFail($id);

        DB::connection('mysql_admin')->transaction(function () use ($row) {
            $row->active = !$row->active;
            $row->save();
        });

        return redirect()
            ->route('admin.billing.sat.prices.index')
            ->with('success', 'Estado actualizado.');
    }

    private function validatePayload(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name'          => ['required','string','max:120'],
            'active'        => ['nullable','boolean'],
            'unit'          => ['required','in:range_per_xml,flat'],
            'min_xml'       => ['required','integer','min:0'],
            'max_xml'       => ['nullable','integer','min:0'],
            'price_per_xml' => ['nullable','numeric','min:0'],
            'flat_price'    => ['nullable','numeric','min:0'],
            'currency'      => ['required','string','max:8'],
            'sort'          => ['nullable','integer','min:0','max:9999'],
        ]);

        $data['active'] = (bool)($data['active'] ?? false);
        $data['sort']   = (int)($data['sort'] ?? 0);

        $min = (int)($data['min_xml'] ?? 0);
        $max = $data['max_xml'] !== null ? (int)$data['max_xml'] : null;

        if ($max !== null && $max < $min) {
            $data['max_xml'] = $min;
        }

        // Normalización según unit
        if (($data['unit'] ?? '') === 'flat') {
            $data['price_per_xml'] = 0;
            $data['flat_price']    = (float)($data['flat_price'] ?? 0);
        } else {
            $data['flat_price']    = 0;
            $data['price_per_xml'] = (float)($data['price_per_xml'] ?? 0);
        }

        return $data;
    }
}
