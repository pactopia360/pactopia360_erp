<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StripePriceStoreRequest;
use App\Http\Requests\Admin\StripePriceUpdateRequest;
use App\Models\Admin\StripePrice;
use Illuminate\Http\Request;

class StripePriceController extends Controller
{
    public function index(Request $request)
    {
        $q     = trim((string)$request->get('q',''));
        $plan  = trim((string)$request->get('plan',''));
        $cycle = trim((string)$request->get('cycle',''));
        $act   = $request->get('active','');

        $rows = StripePrice::query()
            ->when($q !== '', fn($qq) => $qq->where(function($w) use ($q){
                $w->where('price_key','like',"%{$q}%")
                  ->orWhere('name','like',"%{$q}%")
                  ->orWhere('stripe_price_id','like',"%{$q}%");
            }))
            ->when($plan !== '', fn($qq) => $qq->where('plan',$plan))
            ->when($cycle !== '', fn($qq) => $qq->where('billing_cycle',$cycle))
            ->when($act !== '' && in_array($act, ['0','1'], true), fn($qq) => $qq->where('is_active',(int)$act))
            ->orderByDesc('is_active')
            ->orderBy('plan')
            ->orderBy('billing_cycle')
            ->orderBy('price_key')
            ->paginate(20)
            ->withQueryString();

        $plans = StripePrice::query()->select('plan')->distinct()->orderBy('plan')->pluck('plan')->all();

        return view('admin.config.param.stripe_prices.index', compact('rows','q','plan','cycle','act','plans'));
    }

    public function create()
    {
        return view('admin.config.param.stripe_prices.form', [
            'mode' => 'create',
            'row'  => new StripePrice(['plan'=>'PRO','billing_cycle'=>'mensual','currency'=>'MXN','is_active'=>true]),
        ]);
    }

    public function store(StripePriceStoreRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = (bool)($data['is_active'] ?? true);

        StripePrice::create($data);

        return redirect()
            ->route('admin.config.param.stripe_prices.index')
            ->with('status', 'Precio creado.');
    }

    public function edit(int $id)
    {
        $row = StripePrice::findOrFail($id);

        return view('admin.config.param.stripe_prices.form', [
            'mode' => 'edit',
            'row'  => $row,
        ]);
    }

    public function update(StripePriceUpdateRequest $request, int $id)
    {
        $row  = StripePrice::findOrFail($id);
        $data = $request->validated();
        $data['is_active'] = (bool)($data['is_active'] ?? false);

        $row->update($data);

        return redirect()
            ->route('admin.config.param.stripe_prices.index')
            ->with('status', 'Precio actualizado.');
    }

    public function toggle(int $id)
    {
        $row = StripePrice::findOrFail($id);
        $row->is_active = !$row->is_active;
        $row->save();

        return back()->with('status', $row->is_active ? 'Precio activado.' : 'Precio desactivado.');
    }

    public function destroy(int $id)
    {
        $row = StripePrice::findOrFail($id);
        $row->delete();

        return redirect()
            ->route('admin.config.param.stripe_prices.index')
            ->with('status', 'Precio eliminado.');
    }
}
