<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class AccountLicensesController extends Controller
{
    private string $adm = 'mysql_admin';

    public function index(Request $req): View
    {
        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            return view('admin.billing.accounts.index', [
                'rows'  => collect(),
                'q'     => '',
                'error' => 'No existe la tabla accounts en p360v1_admin.',
            ]);
        }

        $q = trim((string) $req->get('q', ''));

        $qb = DB::connection($this->adm)->table('accounts')
            ->select('id','name','email','rfc','razon_social','plan_actual','modo_cobro','is_blocked','estado_cuenta','meta','created_at')
            ->orderByDesc('id');

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('razon_social', 'like', "%{$q}%")
                  ->orWhere('rfc', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $rows = $qb->paginate(25)->withQueryString();

        return view('admin.billing.accounts.index', [
            'rows'  => $rows,
            'q'     => $q,
            'error' => null,
        ]);
    }

    public function show(int $accountId): View
    {
        abort_unless(Schema::connection($this->adm)->hasTable('accounts'), 404);

        $account = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        abort_unless($account, 404);

        $meta = $this->decodeMeta($account->meta ?? null);

        $prices = Schema::connection($this->adm)->hasTable('stripe_price_list')
            ? DB::connection($this->adm)->table('stripe_price_list')
                ->orderByDesc('is_active')
                ->orderBy('plan')
                ->orderBy('billing_cycle')
                ->orderBy('name')
                ->get()
            : collect();

        $assigned = [
            'price_key'       => data_get($meta, 'billing.price_key'),
            'stripe_price_id' => data_get($meta, 'billing.stripe_price_id'),
            'billing_cycle'   => data_get($meta, 'billing.billing_cycle'),
            'concept'         => data_get($meta, 'billing.concept'),
            'modules'         => (array) data_get($meta, 'modules', []),
        ];

        $priceRow = null;
        if (!empty($assigned['price_key']) && Schema::connection($this->adm)->hasTable('stripe_price_list')) {
            $priceRow = DB::connection($this->adm)->table('stripe_price_list')->where('price_key', $assigned['price_key'])->first();
        }
        if (!$priceRow && !empty($assigned['stripe_price_id']) && Schema::connection($this->adm)->hasTable('stripe_price_list')) {
            $priceRow = DB::connection($this->adm)->table('stripe_price_list')->where('stripe_price_id', $assigned['stripe_price_id'])->first();
        }

        return view('admin.billing.accounts.show', [
            'account'  => $account,
            'meta'     => $meta,
            'prices'   => $prices,
            'assigned' => $assigned,
            'priceRow' => $priceRow,
        ]);
    }

    public function assignPrice(Request $req, int $accountId): RedirectResponse
    {
        abort_unless(Schema::connection($this->adm)->hasTable('accounts'), 404);

        $data = $req->validate([
            'price_key'     => 'nullable|string|max:60',
            'billing_cycle' => 'nullable|in:mensual,anual',
            'concept'       => 'nullable|string|max:255',
        ]);

        $account = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        abort_unless($account, 404);

        $meta = $this->decodeMeta($account->meta ?? null);

        $priceKey = isset($data['price_key']) ? trim((string) $data['price_key']) : '';
        if ($priceKey === '') $priceKey = null;

        $stripePriceId = null;
        if ($priceKey && Schema::connection($this->adm)->hasTable('stripe_price_list')) {
            $p = DB::connection($this->adm)->table('stripe_price_list')->where('price_key', $priceKey)->first();
            if ($p) {
                $stripePriceId = $p->stripe_price_id ?? null;
                if (empty($data['billing_cycle']) && !empty($p->billing_cycle)) {
                    $data['billing_cycle'] = (string) $p->billing_cycle;
                }
            }
        }

        data_set($meta, 'billing.price_key', $priceKey);
        data_set($meta, 'billing.stripe_price_id', $priceKey ? $stripePriceId : null);

        if (!empty($data['billing_cycle'])) data_set($meta, 'billing.billing_cycle', (string) $data['billing_cycle']);
        if (array_key_exists('concept', $data)) data_set($meta, 'billing.concept', $data['concept']);

        DB::connection($this->adm)->table('accounts')->where('id', $accountId)->update([
            'meta'       => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return back()->with('ok', 'Licencia/precio asignado y guardado en accounts.meta.');
    }

    public function saveModules(Request $req, int $accountId): RedirectResponse
    {
        abort_unless(Schema::connection($this->adm)->hasTable('accounts'), 404);

        $data = $req->validate([
            'modules' => 'nullable|array',
        ]);

        $account = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        abort_unless($account, 404);

        $meta = $this->decodeMeta($account->meta ?? null);

        $truthy = ['1','on','true','yes','si'];
        $modules = [];

        foreach (($data['modules'] ?? []) as $k => $v) {
            $modules[(string) $k] = in_array(strtolower(trim((string) $v)), $truthy, true);
        }

        data_set($meta, 'modules', $modules);

        DB::connection($this->adm)->table('accounts')->where('id', $accountId)->update([
            'meta'       => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return back()->with('ok', 'Módulos actualizados.');
    }

    public function emailLicenseSummary(Request $req, int $accountId): RedirectResponse
    {
        abort_unless(Schema::connection($this->adm)->hasTable('accounts'), 404);

        $account = DB::connection($this->adm)->table('accounts')->where('id', $accountId)->first();
        abort_unless($account, 404);

        $to = trim((string) ($req->input('to') ?: ($account->email ?? '')));
        if ($to === '') return back()->withErrors(['to' => 'La cuenta no tiene email y no enviaste "to".']);

        $meta = $this->decodeMeta($account->meta ?? null);
        $priceKey = data_get($meta, 'billing.price_key');
        $concept  = data_get($meta, 'billing.concept');

        $priceRow = null;
        if ($priceKey && Schema::connection($this->adm)->hasTable('stripe_price_list')) {
            $priceRow = DB::connection($this->adm)->table('stripe_price_list')->where('price_key', $priceKey)->first();
        }

        $payload = [
            'account'  => $account,
            'meta'     => $meta,
            'price'    => $priceRow,
            'concept'  => $concept,
        ];

        Mail::send('admin.mail.license_summary', $payload, function ($m) use ($to, $account) {
            $m->to($to)
              ->subject('Pactopia360 · Resumen de licencia y módulos · ' . ($account->rfc ?? $account->name ?? 'Cuenta'));
        });

        return back()->with('ok', 'Correo enviado al cliente con resumen de licencia.');
    }

    private function decodeMeta($meta): array
    {
        try {
            if (is_array($meta)) return $meta;
            if (is_string($meta) && trim($meta) !== '') {
                $decoded = json_decode($meta, true);
                return is_array($decoded) ? $decoded : [];
            }
        } catch (\Throwable $e) {
            // no-op
        }
        return [];
    }
}
