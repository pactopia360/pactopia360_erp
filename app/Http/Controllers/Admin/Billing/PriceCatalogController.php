<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class PriceCatalogController extends Controller
{
    private string $adm = 'mysql_admin';

    public function index(Request $req): View
    {
        $q = trim((string) $req->get('q', ''));
        $onlyActive = (int) $req->get('active', 0);

        if (!Schema::connection($this->adm)->hasTable('stripe_price_list')) {
            return view('admin.billing.prices.index', [
                'rows' => collect(),
                'q' => $q,
                'active' => $onlyActive,
                'error' => 'No existe la tabla stripe_price_list en p360v1_admin.',
            ]);
        }

        $qb = DB::connection($this->adm)
            ->table('stripe_price_list')
            ->orderByDesc('is_active')
            ->orderBy('plan')
            ->orderBy('billing_cycle')
            ->orderBy('name');

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('price_key', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
                  ->orWhere('stripe_price_id', 'like', "%{$q}%")
                  ->orWhere('plan', 'like', "%{$q}%")
                  ->orWhere('billing_cycle', 'like', "%{$q}%");
            });
        }

        if ($onlyActive === 1) {
            $qb->where('is_active', 1);
        }

        $rows = $qb->paginate(25)->withQueryString();

        return view('admin.billing.prices.index', [
            'rows' => $rows,
            'q' => $q,
            'active' => $onlyActive,
            'error' => null,
        ]);
    }

    public function edit(int $id): View
    {
        abort_unless(Schema::connection($this->adm)->hasTable('stripe_price_list'), 404);

        $row = DB::connection($this->adm)->table('stripe_price_list')->where('id', $id)->first();
        abort_unless($row, 404);

        return view('admin.billing.prices.edit', ['row' => $row]);
    }

    public function update(Request $req, int $id): RedirectResponse
    {
        abort_unless(Schema::connection($this->adm)->hasTable('stripe_price_list'), 404);

        $data = $req->validate([
            'price_key'       => 'required|string|max:60',
            'name'            => 'nullable|string|max:120',
            'plan'            => 'required|string|max:30',
            'billing_cycle'   => 'required|in:mensual,anual',
            'stripe_price_id' => 'required|string|max:120',
            'currency'        => 'required|string|max:10',
            'display_amount'  => 'nullable|numeric|min:0|max:99999999',
            'is_active'       => 'nullable|in:0,1',
        ]);

        $priceKey = trim((string) $data['price_key']);
        $stripePriceId = trim((string) $data['stripe_price_id']);

        // Opcional pero recomendado: evita duplicados cruzados
        $dup = DB::connection($this->adm)->table('stripe_price_list')
            ->where('id', '<>', $id)
            ->where(function ($w) use ($priceKey, $stripePriceId) {
                $w->where('price_key', $priceKey)
                  ->orWhere('stripe_price_id', $stripePriceId);
            })
            ->first();

        if ($dup) {
            return back()->withErrors([
                'price_key' => 'Ya existe otro registro con el mismo price_key o stripe_price_id.',
            ])->withInput();
        }

        $displayAmount = $data['display_amount'] ?? null;
        if ($displayAmount === '' || $displayAmount === false) {
            $displayAmount = null;
        }

        DB::connection($this->adm)->table('stripe_price_list')->where('id', $id)->update([
            'price_key'       => $priceKey,
            'name'            => $data['name'] ?? null,
            'plan'            => strtoupper(trim((string) $data['plan'])),
            'billing_cycle'   => (string) $data['billing_cycle'],
            'stripe_price_id' => $stripePriceId,
            'currency'        => strtoupper(trim((string) $data['currency'])),
            'display_amount'  => $displayAmount,
            'is_active'       => (int) ($data['is_active'] ?? 0),
            'updated_at'      => now(),
        ]);

        return redirect()->route('admin.billing.prices.index')->with('ok', 'Precio actualizado.');
    }

    public function toggle(int $id): RedirectResponse
    {
        abort_unless(Schema::connection($this->adm)->hasTable('stripe_price_list'), 404);

        $row = DB::connection($this->adm)->table('stripe_price_list')->select('id', 'is_active')->where('id', $id)->first();
        abort_unless($row, 404);

        $new = ((int) $row->is_active) ? 0 : 1;

        DB::connection($this->adm)->table('stripe_price_list')->where('id', $id)->update([
            'is_active'  => $new,
            'updated_at' => now(),
        ]);

        return back()->with('ok', $new ? 'Precio habilitado.' : 'Precio deshabilitado.');
    }
}
