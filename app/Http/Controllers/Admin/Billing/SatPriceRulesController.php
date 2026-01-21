<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Admin\SatPriceRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

final class SatPriceRulesController extends Controller
{
    public function index(Request $request): View
    {
        $q = SatPriceRule::query()->orderBy('sort')->orderBy('min_xml');

        $active = $request->get('active');
        if ($active === '1') $q->where('active', 1);
        if ($active === '0') $q->where('active', 0);

        $rows = $q->paginate(30)->withQueryString();

        return view('admin.billing.sat.prices.index', compact('rows', 'active'));
    }

    public function create(): View
    {
        $row = new SatPriceRule([
            'active' => 1,
            'unit' => 'range_per_xml',
            'min_xml' => 0,
            'max_xml' => null,
            'price_per_xml' => null,
            'flat_price' => null,
            'currency' => 'MXN',
            'sort' => 100,
        ]);

        return view('admin.billing.sat.prices.form', compact('row'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        try {
            SatPriceRule::create($data);
            return redirect()->route('admin.sat.prices.index')->with('ok', 'Regla creada.');
        } catch (\Throwable $e) {
            Log::error('[ADMIN][SAT_PRICES][store] error', ['err' => $e->getMessage()]);
            return back()->withInput()->with('error', 'No se pudo crear la regla.');
        }
    }

    public function edit(int $id): View
    {
        $row = SatPriceRule::query()->findOrFail($id);
        return view('admin.billing.sat.prices.form', compact('row'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $this->validateData($request);

        try {
            $row = SatPriceRule::query()->findOrFail($id);
            $row->update($data);
            return redirect()->route('admin.sat.prices.index')->with('ok', 'Regla actualizada.');
        } catch (\Throwable $e) {
            Log::error('[ADMIN][SAT_PRICES][update] error', ['id' => $id, 'err' => $e->getMessage()]);
            return back()->withInput()->with('error', 'No se pudo actualizar la regla.');
        }
    }

    public function toggle(int $id): RedirectResponse
    {
        try {
            $row = SatPriceRule::query()->findOrFail($id);
            $row->active = !$row->active;
            $row->save();

            return redirect()->route('admin.sat.prices.index')->with('ok', 'Estado actualizado.');
        } catch (\Throwable $e) {
            Log::error('[ADMIN][SAT_PRICES][toggle] error', ['id' => $id, 'err' => $e->getMessage()]);
            return back()->with('error', 'No se pudo actualizar el estado.');
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $row = SatPriceRule::query()->findOrFail($id);
            $row->delete();
            return redirect()->route('admin.sat.prices.index')->with('ok', 'Regla eliminada.');
        } catch (\Throwable $e) {
            Log::error('[ADMIN][SAT_PRICES][destroy] error', ['id' => $id, 'err' => $e->getMessage()]);
            return back()->with('error', 'No se pudo eliminar la regla.');
        }
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'active' => ['nullable'],
            'unit' => ['required','in:range_per_xml,flat'],
            'min_xml' => ['required','integer','min:0'],
            'max_xml' => ['nullable','integer','min:0'],
            'price_per_xml' => ['nullable','numeric','min:0'],
            'flat_price' => ['nullable','numeric','min:0'],
            'currency' => ['required','string','max:8'],
            'sort' => ['required','integer','min:0','max:999999'],
        ]);

        $data['active'] = (bool)($request->input('active') ? true : false);

        $min = (int)$data['min_xml'];
        $max = isset($data['max_xml']) && $data['max_xml'] !== null && $data['max_xml'] !== '' ? (int)$data['max_xml'] : null;
        if ($max !== null && $max < $min) $max = $min;

        $data['max_xml'] = $max;

        if ($data['unit'] === 'flat') {
            $data['price_per_xml'] = null;
            $data['flat_price'] = isset($data['flat_price']) ? (float)$data['flat_price'] : 0.0;
        } else {
            $data['flat_price'] = null;
            $data['price_per_xml'] = isset($data['price_per_xml']) ? (float)$data['price_per_xml'] : 0.0;
        }

        return $data;
    }
}
