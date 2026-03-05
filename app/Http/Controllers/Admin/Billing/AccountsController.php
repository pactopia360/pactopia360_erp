<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Services\Admin\Accounts\AccountBillingConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AccountsController extends Controller
{
    public function __construct(
        private readonly AccountBillingConfigService $svc
    ) {}

    // ======================= INDEX =======================
    public function index(Request $request)
    {
        $q = trim((string)$request->get('q',''));
        $filter = (string)$request->get('filter','all');
        $filter = in_array($filter, ['all','blocked','no_license','override'], true) ? $filter : 'all';

        $emailCol = $this->svc->colEmail();
        $phoneCol = $this->svc->colPhone();
        $rfcCol   = $this->svc->colRfcAdmin();

        $hasMeta = $this->svc->hasMetaColumn();
        $metaCol = $this->svc->metaCol();

        $qb = DB::connection($this->svc->conn())->table($this->svc->table());

        if ($q !== '') {
            $qb->where(function($w) use ($q, $emailCol, $phoneCol, $rfcCol){
                $w->where('id', 'like', "%{$q}%")
                  ->orWhere('razon_social', 'like', "%{$q}%")
                  ->orWhere($emailCol, 'like', "%{$q}%")
                  ->orWhere($phoneCol, 'like', "%{$q}%");
                if ($rfcCol !== 'id') $w->orWhere($rfcCol, 'like', "%{$q}%");
            });
        }

        if ($filter === 'blocked') {
            if (Schema::connection($this->svc->conn())->hasColumn($this->svc->table(), 'is_blocked')) {
                $qb->where('is_blocked', 1);
            } else {
                $qb->whereRaw('1=0');
            }
        }

        if ($filter === 'no_license') {
            if ($hasMeta) {
                $qb->where(function($w) use ($metaCol){
                    $w->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`{$metaCol}`,'$.billing.price_key')) IS NULL")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(`{$metaCol}`,'$.billing.price_key')) = ''");
                });
            } else {
                $qb->whereRaw('1=0');
            }
        }

        if ($filter === 'override') {
            if ($hasMeta) {
                $qb->where(function($w) use ($metaCol){
                    $w->whereRaw("JSON_EXTRACT(`{$metaCol}`,'$.billing.override.amount_mxn') IS NOT NULL")
                      ->orWhereRaw("JSON_EXTRACT(`{$metaCol}`,'$.billing.override_amount_mxn') IS NOT NULL")
                      ->orWhereRaw("JSON_EXTRACT(`{$metaCol}`,'$.billing.override.monthly.amount_mxn') IS NOT NULL")
                      ->orWhereRaw("JSON_EXTRACT(`{$metaCol}`,'$.billing.override.yearly.amount_mxn') IS NOT NULL");
                });
            } else {
                $qb->whereRaw('1=0');
            }
        }

        $rows = $qb
            ->select([
                'id',
                DB::raw("$rfcCol as rfc"),
                'razon_social',
                DB::raw("$emailCol as email"),
                DB::raw("$phoneCol as phone"),
                DB::raw(Schema::connection($this->svc->conn())->hasColumn($this->svc->table(), 'plan') ? 'plan' : "'' as plan"),
                DB::raw(Schema::connection($this->svc->conn())->hasColumn($this->svc->table(), 'billing_cycle') ? 'billing_cycle' : "NULL as billing_cycle"),
                DB::raw(Schema::connection($this->svc->conn())->hasColumn($this->svc->table(), 'billing_status') ? 'billing_status' : "NULL as billing_status"),
                DB::raw(Schema::connection($this->svc->conn())->hasColumn($this->svc->table(), 'next_invoice_date') ? 'next_invoice_date' : "NULL as next_invoice_date"),
                DB::raw(Schema::connection($this->svc->conn())->hasColumn($this->svc->table(), 'is_blocked') ? 'is_blocked' : "0 as is_blocked"),
                DB::raw($hasMeta ? $metaCol : "NULL as {$metaCol}"),
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('created_at')
            ->paginate(12)
            ->appends(['q'=>$q, 'filter'=>$filter]);

        if ($hasMeta) {
            $rows->getCollection()->each(function($acc){
                try { $this->svc->ensureBillingMetaIntegrityInPlace($acc); } catch (\Throwable $e) {}
            });
        }

        Log::info('AdminBilling.accounts.index', [
            'conn' => $this->svc->conn(),
            'table' => $this->svc->table(),
            'metaCol' => $this->svc->metaCol(),
            'hasMeta' => $hasMeta,
            'q' => $q,
            'filter' => $filter,
        ]);

        return view('admin.billing.accounts.index', [
            'rows' => $rows,
            'q' => $q,
            'filter' => $filter,
            'conn_used' => $this->svc->conn(),
            'meta_col' => $this->svc->metaCol(),
            'has_meta' => $hasMeta,
        ]);
    }

    // ======================= SHOW =======================
    public function show(Request $request, string $id): Response
    {
        $account = $this->svc->getAccountOrFail($id);

        $savedBilling = $this->svc->ensureBillingMetaIntegrityInPlace($account);
        if ($savedBilling) $account = $this->svc->getAccountOrFail($id);

        $hasMeta = $this->svc->hasMetaColumn();
        $meta = $hasMeta
            ? $this->svc->decodeMeta($account->{$this->svc->metaCol()} ?? null)
            : [];

        $catalog = $this->svc->priceCatalog();

        $pk = (string) data_get($meta, 'billing.price_key', '');
        $cycle = (string) data_get($meta, 'billing.billing_cycle', 'monthly');
        $stripePriceId = (string) data_get($meta, 'billing.stripe_price_id', '');
        $baseAmount = (int) data_get($meta, 'billing.amount_mxn', 0);

        $overrideByCycle = data_get($meta, "billing.override.$cycle.amount_mxn");

        $legacyMonthly = data_get($meta, 'billing.override.amount_mxn')
            ?? data_get($meta, 'billing.override_amount_mxn');

        $overrideAmount = is_numeric($overrideByCycle)
            ? (int)$overrideByCycle
            : (is_numeric($legacyMonthly) ? (int)$legacyMonthly : null);

        $overrideEffective = (string) (
            data_get($meta, "billing.override.$cycle.effective")
            ?? data_get($meta, 'billing.override.effective')
            ?? data_get($meta, 'billing.override_effective')
            ?? 'next'
        );

        $overrideAt = (string) (
            data_get($meta, "billing.override.$cycle.updated_at")
            ?? data_get($meta, 'billing.override.updated_at')
            ?? data_get($meta, 'billing.override_updated_at')
            ?? ''
        );

        $currentAmount = ($overrideAmount !== null) ? $overrideAmount : $baseAmount;

        $modulesState = $this->svc->buildModulesStateFromMeta($meta);

        $isBlocked = (int)($account->is_blocked ?? 0) === 1;
        $periodNow = now()->format('Y-m');

        $payload = [
            'account' => $account,
            'meta' => $meta,
            'catalog' => $catalog,
            'price_key' => $pk,
            'billing_cycle' => $cycle,
            'stripe_price_id' => $stripePriceId,
            'base_amount_mxn' => $baseAmount,
            'override_amount_mxn' => $overrideAmount,
            'override_effective' => $overrideEffective,
            'override_updated_at' => $overrideAt,
            'current_amount_mxn' => $currentAmount,

            'modules_catalog' => $this->svc->modulesCatalog(),
            'modules_state'   => $modulesState,

            'isBlocked' => $isBlocked,
            'periodNow' => $periodNow,
            'isModal' => $request->boolean('modal'),
            'conn_used' => $this->svc->conn(),
            'meta_col' => $this->svc->metaCol(),
            'has_meta' => $hasMeta,
        ];

        $resp = response()->view('admin.billing.accounts.show', $payload);

        if ($request->boolean('modal')) {
            $resp->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $resp->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }

        return $resp;
    }

    // ======================= UPDATE LICENSE =======================
    public function updateLicense(Request $request, string $id): RedirectResponse
    {
        $this->svc->updateLicense($id, $request);

        $priceKey = trim((string) $request->input('price_key', ''));
        if ($priceKey === 'custom') {
            return back()->with('ok', 'Licencia personalizada guardada.');
        }

        return back()->with('ok', 'Licencia/Precio actualizado (SOT).');
    }

    // ======================= UPDATE OVERRIDE =======================
    public function updateOverride(Request $request, string $id): RedirectResponse
    {
        $res = $this->svc->updateOverride($id, $request);

        if (($res['cleared'] ?? false) === true) {
            $cycle = (string) ($res['cycle'] ?? 'monthly');
            return back()->with('ok', "Override {$cycle} removido. Se usará el precio asignado.");
        }

        $cycle = (string) ($res['cycle'] ?? 'monthly');
        return back()->with('ok', "Override {$cycle} guardado (SOT).");
    }

    // ======================= UPDATE MODULES =======================
    public function updateModules(Request $request, string $id): RedirectResponse
    {
        $this->svc->updateModules($id, $request);
        return back()->with('ok', 'Módulos guardados: visible/invisible/activo/bloqueado (SOT).');
    }
}