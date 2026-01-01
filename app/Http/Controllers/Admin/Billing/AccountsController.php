<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Response;

class AccountsController extends Controller
{
    private string $conn = 'mysql_admin';
    private string $table = 'accounts';
    private string $metaCol = 'meta';

    /**
     * Catálogo SOT (debe cubrir lo que el CLIENTE renderiza en sidebar).
     * Keys = banderas. Labels solo informativo en Admin.
     */
    private array $modulesCatalog = [
        // Cuenta (navegación)
        'mi_cuenta'     => 'Mi cuenta',
        'estado_cuenta' => 'Estado de cuenta',
        'pagos'         => 'Pagos',
        'facturas'      => 'Facturas',

        // Módulos principales
        'facturacion'   => 'Facturación',
        'sat_descargas' => 'SAT Descargas Masivas',
        'boveda_fiscal' => 'Bóveda Fiscal',

        // ERP
        'crm'           => 'CRM',
        'nomina'        => 'Nómina',
        'pos'           => 'Punto de venta',
        'inventario'    => 'Inventario',

        // Sistema
        'reportes'      => 'Reportes',
        'integraciones' => 'Integraciones',
        'alertas'       => 'Alertas',
        'chat'          => 'Chat',
        'marketplace'   => 'Marketplace',
        'configuracion_avanzada' => 'Configuración avanzada',
    ];

    /** Estados soportados */
    private const MOD_ACTIVE   = 'active';   // visible y habilitado
    private const MOD_INACTIVE = 'inactive'; // visible, pero deshabilitado (lock)
    private const MOD_HIDDEN   = 'hidden';   // NO se muestra
    private const MOD_BLOCKED  = 'blocked';  // visible pero bloqueado (lock duro)

    public function __construct()
    {
        $this->conn    = (string) (env('P360_BILLING_SOT_CONN') ?: 'mysql_admin');
        $this->table   = (string) (env('P360_BILLING_SOT_TABLE') ?: 'accounts');
        $this->metaCol = (string) (env('P360_BILLING_META_COL') ?: 'meta');
    }

    private function hasMetaColumn(): bool
    {
        try {
            return Schema::connection($this->conn)->hasColumn($this->table, $this->metaCol);
        } catch (\Throwable $e) {
            Log::error('AdminBilling.metaColumn.check_failed', [
                'conn' => $this->conn,
                'table' => $this->table,
                'metaCol' => $this->metaCol,
                'err' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function colEmail(): string
    {
        foreach (['correo_contacto', 'email'] as $c) {
            if (Schema::connection($this->conn)->hasColumn($this->table, $c)) return $c;
        }
        return 'email';
    }

    private function colPhone(): string
    {
        foreach (['telefono', 'phone', 'tel', 'celular'] as $c) {
            if (Schema::connection($this->conn)->hasColumn($this->table, $c)) return $c;
        }
        return 'phone';
    }

    private function colRfcAdmin(): string
    {
        foreach (['rfc', 'rfc_padre', 'tax_id', 'rfc_cliente'] as $c) {
            if (Schema::connection($this->conn)->hasColumn($this->table, $c)) return $c;
        }
        return 'id';
    }

    private function priceCatalog(): array
    {
        $cfg = config('p360.billing.prices');
        if (is_array($cfg) && !empty($cfg)) return $cfg;

        return [
            'free' => [
                'label' => 'Free',
                'billing_cycle' => 'none',
                'amount_mxn' => 0,
                'stripe_price_id' => null,
            ],
            'pro_mensual' => [
                'label' => 'PRO mensual',
                'billing_cycle' => 'monthly',
                'amount_mxn' => 899,
                'stripe_price_id' => null,
            ],
            'pro_anual' => [
                'label' => 'PRO anual',
                'billing_cycle' => 'yearly',
                'amount_mxn' => 8990,
                'stripe_price_id' => null,
            ],
        ];
    }

    private function defaultPriceKey(): string
    {
        return (string) (config('p360.billing.default_price_key') ?: 'pro_mensual');
    }

    private function decodeMeta($raw): array
    {
        try {
            if (is_array($raw)) return $raw;
            if (is_string($raw) && $raw !== '') return json_decode($raw, true) ?: [];
        } catch (\Throwable $e) {}
        return [];
    }

    private function encodeMeta(array $meta): string
    {
        return json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function getAccountOrFail(string $id): object
    {
        $row = DB::connection($this->conn)->table($this->table)->where('id', $id)->first();
        abort_if(!$row, 404, 'Cuenta no encontrada (SOT)');
        return $row;
    }

    /** Normaliza estado a enum permitido */
    private function normState(?string $s): string
    {
        $s = strtolower(trim((string)$s));
        return in_array($s, [self::MOD_ACTIVE, self::MOD_INACTIVE, self::MOD_HIDDEN, self::MOD_BLOCKED], true)
            ? $s
            : self::MOD_ACTIVE;
    }

    /**
     * Construye modules_state a partir de:
     * - meta.modules_state (nuevo)
     * - fallback legacy meta.modules boolean (true/false)
     */
    private function buildModulesStateFromMeta(array $meta): array
    {
        $state  = data_get($meta, 'modules_state', []);
        $legacy = data_get($meta, 'modules', []);

        if (!is_array($state)) $state = [];
        if (!is_array($legacy)) $legacy = [];

        $out = [];
        foreach ($this->modulesCatalog as $k => $_label) {
            if (array_key_exists($k, $state)) {
                $out[$k] = $this->normState((string)$state[$k]);
                continue;
            }

            if (array_key_exists($k, $legacy)) {
                $out[$k] = ((bool)$legacy[$k]) ? self::MOD_ACTIVE : self::MOD_INACTIVE;
                continue;
            }

            $out[$k] = self::MOD_ACTIVE;
        }

        return $out;
    }

    /** También genera el legacy boolean para compat (active=true; inactive/hidden/blocked=false) */
    private function buildLegacyBoolFromState(array $modulesState): array
    {
        $out = [];
        foreach ($this->modulesCatalog as $k => $_label) {
            $st = $this->normState($modulesState[$k] ?? self::MOD_ACTIVE);
            $out[$k] = ($st === self::MOD_ACTIVE);
        }
        return $out;
    }

    /**
     * Completa meta.billing.* si está incompleto.
     * @return bool true si guardó cambios
     */
    private function ensureBillingMetaIntegrityInPlace(object $account): bool
    {
        if (!$this->hasMetaColumn()) return false;

        $catalog = $this->priceCatalog();
        $meta = $this->decodeMeta($account->{$this->metaCol} ?? null);

        $pk = (string) data_get($meta, 'billing.price_key', '');

        $planHint = (string)($account->plan ?? '');
        if ($pk === '' && $planHint !== '' && isset($catalog[$planHint])) {
            $pk = $planHint;
            data_set($meta, 'billing.price_key', $pk);
            data_set($meta, 'billing.assigned_at', now()->toISOString());
            data_set($meta, 'billing.assigned_by', 'system.plan_hint');
        }

        if ($pk === '') {
            $pk = $this->defaultPriceKey();
            if (!isset($catalog[$pk])) return false;
            data_set($meta, 'billing.price_key', $pk);
            data_set($meta, 'billing.assigned_at', now()->toISOString());
            data_set($meta, 'billing.assigned_by', 'system.default');
        }

        if (!isset($catalog[$pk])) return false;
        $p = $catalog[$pk];

        $cycle = (string) data_get($meta, 'billing.billing_cycle', '');
        $amt   = (int) data_get($meta, 'billing.amount_mxn', 0);
        $spid  = (string) data_get($meta, 'billing.stripe_price_id', '');

        $catCycle = (string)($p['billing_cycle'] ?? 'monthly');
        $catAmt   = (int)($p['amount_mxn'] ?? 0);
        $catSpid  = (string)($p['stripe_price_id'] ?? '');

        $needSave = false;

        if ($cycle === '') {
            data_set($meta, 'billing.billing_cycle', $catCycle);
            $needSave = true;
        }

        if ($amt <= 0 && $catAmt > 0) {
            data_set($meta, 'billing.amount_mxn', $catAmt);
            $needSave = true;
        }

        if ($spid === '' && $catSpid !== '') {
            data_set($meta, 'billing.stripe_price_id', $catSpid);
            $needSave = true;
        }

        if (!$needSave) return false;

        DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update([
            $this->metaCol   => $this->encodeMeta($meta),
            'updated_at'     => now(),
        ]);

        $updSot = [];
        if (Schema::connection($this->conn)->hasColumn($this->table, 'plan')) {
            $updSot['plan'] = $pk;
        }
        if (Schema::connection($this->conn)->hasColumn($this->table, 'billing_cycle')) {
            $updSot['billing_cycle'] = (string) data_get($meta, 'billing.billing_cycle', $catCycle);
        }
        if (!empty($updSot)) {
            $updSot['updated_at'] = now();
            DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update($updSot);
        }

        Log::info('AdminBilling.ensureBillingMetaIntegrity.applied', [
            'account_id'  => (string)$account->id,
            'price_key'   => (string) data_get($meta, 'billing.price_key', ''),
            'cycle'       => (string) data_get($meta, 'billing.billing_cycle', ''),
            'amount_mxn'  => (int) data_get($meta, 'billing.amount_mxn', 0),
        ]);

        return true;
    }

    // ======================= INDEX =======================
    public function index(Request $request)
    {
        $q = trim((string)$request->get('q',''));
        $filter = (string)$request->get('filter','all');
        $filter = in_array($filter, ['all','blocked','no_license','override'], true) ? $filter : 'all';

        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();
        $rfcCol   = $this->colRfcAdmin();

        $hasMeta = $this->hasMetaColumn();
        $metaCol = $this->metaCol;

        $qb = DB::connection($this->conn)->table($this->table);

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
            if (Schema::connection($this->conn)->hasColumn($this->table, 'is_blocked')) {
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
                      ->orWhereRaw("JSON_EXTRACT(`{$metaCol}`,'$.billing.override_amount_mxn') IS NOT NULL");
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
                DB::raw(Schema::connection($this->conn)->hasColumn($this->table, 'plan') ? 'plan' : "'' as plan"),
                DB::raw(Schema::connection($this->conn)->hasColumn($this->table, 'billing_cycle') ? 'billing_cycle' : "NULL as billing_cycle"),
                DB::raw(Schema::connection($this->conn)->hasColumn($this->table, 'billing_status') ? 'billing_status' : "NULL as billing_status"),
                DB::raw(Schema::connection($this->conn)->hasColumn($this->table, 'next_invoice_date') ? 'next_invoice_date' : "NULL as next_invoice_date"),
                DB::raw(Schema::connection($this->conn)->hasColumn($this->table, 'is_blocked') ? 'is_blocked' : "0 as is_blocked"),
                DB::raw($hasMeta ? $metaCol : "NULL as {$metaCol}"),
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('created_at')
            ->paginate(12)
            ->appends(['q'=>$q, 'filter'=>$filter]);

        if ($hasMeta) {
            $rows->getCollection()->each(function($acc){
                try { $this->ensureBillingMetaIntegrityInPlace($acc); } catch (\Throwable $e) {}
            });
        }

        Log::info('AdminBilling.accounts.index', [
            'conn' => $this->conn,
            'table' => $this->table,
            'metaCol' => $this->metaCol,
            'hasMeta' => $hasMeta,
            'q' => $q,
            'filter' => $filter,
        ]);

        return view('admin.billing.accounts.index', [
            'rows' => $rows,
            'q' => $q,
            'filter' => $filter,
            'conn_used' => $this->conn,
            'meta_col' => $this->metaCol,
            'has_meta' => $hasMeta,
        ]);
    }

    // ======================= SHOW =======================
    public function show(Request $request, string $id): Response
    {
        $account = $this->getAccountOrFail($id);

        $savedBilling = $this->ensureBillingMetaIntegrityInPlace($account);
        if ($savedBilling) $account = $this->getAccountOrFail($id);

        $hasMeta = $this->hasMetaColumn();
        $meta = $hasMeta
            ? $this->decodeMeta($account->{$this->metaCol} ?? null)
            : [];

        $catalog = $this->priceCatalog();

        $pk = (string) data_get($meta, 'billing.price_key', '');
        $cycle = (string) data_get($meta, 'billing.billing_cycle', '');
        $stripePriceId = (string) data_get($meta, 'billing.stripe_price_id', '');
        $baseAmount = (int) data_get($meta, 'billing.amount_mxn', 0);

        $overrideAmountNew = data_get($meta, 'billing.override.amount_mxn');
        $overrideAmountOld = data_get($meta, 'billing.override_amount_mxn');
        $overrideAmount = is_numeric($overrideAmountNew) ? (int)$overrideAmountNew : (is_numeric($overrideAmountOld) ? (int)$overrideAmountOld : null);

        $overrideEffective = (string) data_get($meta, 'billing.override.effective', data_get($meta, 'billing.override_effective', 'next'));
        $overrideAt = (string) data_get($meta, 'billing.override.updated_at', data_get($meta, 'billing.override_updated_at', ''));

        $currentAmount = ($overrideAmount !== null) ? $overrideAmount : $baseAmount;

        $modulesState = $this->buildModulesStateFromMeta($meta);

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

            'modules_catalog' => $this->modulesCatalog,
            'modules_state'   => $modulesState,

            'isBlocked' => $isBlocked,
            'periodNow' => $periodNow,
            'isModal' => $request->boolean('modal'),
            'conn_used' => $this->conn,
            'meta_col' => $this->metaCol,
            'has_meta' => $hasMeta,
        ];

        // Render
        $resp = response()->view('admin.billing.accounts.show', $payload);

        // ✅ Si lo cargas en iframe (modal=1), asegúrate de permitir framing same-origin
        if ($request->boolean('modal')) {
            $resp->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $resp->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }

        return $resp;
    }

    // ======================= UPDATE LICENSE =======================
    public function updateLicense(Request $request, string $id): RedirectResponse
    {
        abort_if(!$this->hasMetaColumn(), 422, "No existe columna '{$this->metaCol}' en {$this->conn}.{$this->table}. Ejecuta la migración.");

        $account = $this->getAccountOrFail($id);
        $meta = $this->decodeMeta($account->{$this->metaCol} ?? null);

        $catalog = $this->priceCatalog();

        $priceKey = (string) $request->input('price_key', '');
        abort_if($priceKey === '' || !isset($catalog[$priceKey]), 422, 'price_key inválido');

        $p = $catalog[$priceKey];
        $cycle = (string) ($p['billing_cycle'] ?? 'monthly');
        $amount = (int) ($p['amount_mxn'] ?? 0);
        $stripePriceId = $p['stripe_price_id'] ?? null;

        data_set($meta, 'billing.price_key', $priceKey);
        data_set($meta, 'billing.billing_cycle', $cycle);
        data_set($meta, 'billing.amount_mxn', $amount);
        data_set($meta, 'billing.stripe_price_id', $stripePriceId);
        data_set($meta, 'billing.assigned_at', now()->toISOString());
        data_set($meta, 'billing.assigned_by', 'admin');

        DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update([
            $this->metaCol => $this->encodeMeta($meta),
            'updated_at' => now(),
        ]);

        $updSot = [];
        if (Schema::connection($this->conn)->hasColumn($this->table, 'plan')) $updSot['plan'] = $priceKey;
        if (Schema::connection($this->conn)->hasColumn($this->table, 'billing_cycle')) $updSot['billing_cycle'] = $cycle;
        if (!empty($updSot)) {
            $updSot['updated_at'] = now();
            DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update($updSot);
        }

        Log::info('AdminBilling.updateLicense', [
            'conn' => $this->conn,
            'account_id' => (string)$account->id,
            'price_key' => $priceKey,
            'cycle' => $cycle,
            'amount_mxn' => $amount,
            'stripe_price_id' => $stripePriceId,
        ]);

        return back()->with('ok', 'Licencia/Precio actualizado (SOT).');
    }

    // ======================= UPDATE OVERRIDE =======================
    public function updateOverride(Request $request, string $id): RedirectResponse
    {
        abort_if(!$this->hasMetaColumn(), 422, "No existe columna '{$this->metaCol}' en {$this->conn}.{$this->table}. Ejecuta la migración.");

        $account = $this->getAccountOrFail($id);
        $meta = $this->decodeMeta($account->{$this->metaCol} ?? null);

        $mode = (string) $request->input('override_mode', 'none');
        $effective = (string) $request->input('override_effective', 'next');
        $effective = in_array($effective, ['now','next'], true) ? $effective : 'next';

        if ($mode === 'none') {
            data_forget($meta, 'billing.override');
            data_forget($meta, 'billing.override_amount_mxn');

            DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update([
                $this->metaCol => $this->encodeMeta($meta),
                'updated_at' => now(),
            ]);

            Log::info('AdminBilling.overrideCleared', ['conn'=>$this->conn, 'account_id'=>(string)$account->id]);

            return back()->with('ok', 'Override removido. Se usará el precio asignado.');
        }

        $amount = $request->input('override_amount_mxn');
        abort_if(!is_numeric($amount), 422, 'Monto inválido');
        $amount = (int) $amount;
        abort_if($amount < 0, 422, 'Monto inválido');

        data_set($meta, 'billing.override.amount_mxn', $amount);
        data_set($meta, 'billing.override.effective', $effective);
        data_set($meta, 'billing.override.updated_at', now()->toISOString());
        data_set($meta, 'billing.override.updated_by', 'admin');

        data_set($meta, 'billing.override_amount_mxn', $amount);

        DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update([
            $this->metaCol => $this->encodeMeta($meta),
            'updated_at' => now(),
        ]);

        Log::info('AdminBilling.overrideSaved', [
            'conn' => $this->conn,
            'account_id' => (string)$account->id,
            'override_amount_mxn' => $amount,
            'effective' => $effective,
        ]);

        return back()->with('ok', 'Override mensual guardado (SOT).');
    }

    // ======================= UPDATE MODULES (VISIBLE/ACTIVE/BLOCKED) =======================
    public function updateModules(Request $request, string $id): RedirectResponse
    {
        abort_if(!$this->hasMetaColumn(), 422, "No existe columna '{$this->metaCol}' en {$this->conn}.{$this->table}. Ejecuta la migración.");

        $account = $this->getAccountOrFail($id);
        $meta = $this->decodeMeta($account->{$this->metaCol} ?? null);

        /**
         * Espera input:
         * modules_state[key] = active|inactive|hidden|blocked
         */
        $incoming = (array) $request->input('modules_state', []);
        $outState = [];

        foreach ($this->modulesCatalog as $k => $_label) {
            $raw = $incoming[$k] ?? self::MOD_ACTIVE;
            $outState[$k] = $this->normState(is_string($raw) ? $raw : (string)$raw);
        }

        data_set($meta, 'modules_state', $outState);
        data_set($meta, 'modules_state_updated_at', now()->toISOString());
        data_set($meta, 'modules_state_updated_by', 'admin');

        $legacyBool = $this->buildLegacyBoolFromState($outState);
        data_set($meta, 'modules', $legacyBool);
        data_set($meta, 'modules_updated_at', now()->toISOString());
        data_set($meta, 'modules_updated_by', 'admin.compat');

        DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update([
            $this->metaCol => $this->encodeMeta($meta),
            'updated_at' => now(),
        ]);

        // ✅ FIX: NO castear a int si id es UUID/string
        Cache::forget('p360:mods:acct:' . (string)$account->id);

        Log::info('AdminBilling.modulesStateSaved', [
            'conn' => $this->conn,
            'account_id' => (string)$account->id,
            'modules_state' => $outState,
        ]);

        return back()->with('ok', 'Módulos guardados: visible/invisible/activo/bloqueado (SOT).');
    }
}
