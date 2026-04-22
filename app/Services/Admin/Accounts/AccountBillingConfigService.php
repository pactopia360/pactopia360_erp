<?php declare(strict_types=1);

namespace App\Services\Admin\Accounts;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class AccountBillingConfigService
{
    /** Estados soportados */
    public const MOD_ACTIVE   = 'active';   // visible y habilitado
    public const MOD_INACTIVE = 'inactive'; // visible, pero deshabilitado (lock)
    public const MOD_HIDDEN   = 'hidden';   // NO se muestra
    public const MOD_BLOCKED  = 'blocked';  // visible pero bloqueado (lock duro)

    private string $conn;
    private string $table;
    private string $metaCol;

    /**
     * Catálogo SOT (debe cubrir lo que el CLIENTE renderiza en sidebar).
     * Keys = banderas. Labels solo informativo en Admin.
     */
        /**
     * Catálogo SOT (debe cubrir exactamente lo que CLIENTE web/móvil renderiza).
     * Keys = banderas persistidas en accounts.meta.modules_state
     * Labels = solo informativos en Admin.
     *
     * REGLAS DE NEGOCIO:
     * - SAT es un módulo principal. Cotizaciones SAT y Bóveda SAT viven dentro del ecosistema SAT.
     * - CFDI Nómina NO es módulo separado: vive dentro de Recursos Humanos.
     * - Timbres / Hits es módulo propio.
     * - Ventas es módulo propio, separado de Inventario pero relacionado.
     */
    private array $modulesCatalog = [
        // Navegación de cuenta
        'mi_cuenta'         => 'Mi cuenta',
        'estado_cuenta'     => 'Estado de cuenta',
        'pagos'             => 'Pagos',
        'facturas'          => 'Facturas',

        // Módulos principales visibles al cliente
        'sat_descargas'     => 'SAT Descargas',
        'boveda_fiscal'     => 'Bóveda Fiscal SAT',
        'facturacion'       => 'Facturación',
        'crm'               => 'CRM',
        'inventario'        => 'Inventario',
        'ventas'            => 'Ventas',
        'reportes'          => 'Reportes',
        'recursos_humanos'  => 'Recursos Humanos',
        'timbres_hits'      => 'Timbres / Hits',
    ];

    public function __construct()
    {
        $this->conn    = (string) (env('P360_BILLING_SOT_CONN') ?: 'mysql_admin');
        $this->table   = (string) (env('P360_BILLING_SOT_TABLE') ?: 'accounts');
        $this->metaCol = (string) (env('P360_BILLING_META_COL') ?: 'meta');
    }

    public function conn(): string { return $this->conn; }
    public function table(): string { return $this->table; }
    public function metaCol(): string { return $this->metaCol; }

    public function modulesCatalog(): array
    {
        return $this->modulesCatalog;
    }

    public function hasMetaColumn(): bool
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

    public function colEmail(): string
    {
        foreach (['correo_contacto', 'email'] as $c) {
            if (Schema::connection($this->conn)->hasColumn($this->table, $c)) return $c;
        }
        return 'email';
    }

    public function colPhone(): string
    {
        foreach (['telefono', 'phone', 'tel', 'celular'] as $c) {
            if (Schema::connection($this->conn)->hasColumn($this->table, $c)) return $c;
        }
        return 'phone';
    }

    public function colRfcAdmin(): string
    {
        foreach (['rfc', 'rfc_padre', 'tax_id', 'rfc_cliente'] as $c) {
            if (Schema::connection($this->conn)->hasColumn($this->table, $c)) return $c;
        }
        return 'rfc';
    }

    public function priceCatalog(): array
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

    public function defaultPriceKey(): string
    {
        return (string) (config('p360.billing.default_price_key') ?: 'pro_mensual');
    }

    public function decodeMeta($raw): array
    {
        try {
            if (is_array($raw)) return $raw;
            if (is_string($raw) && $raw !== '') return json_decode($raw, true) ?: [];
        } catch (\Throwable $e) {}
        return [];
    }

    public function encodeMeta(array $meta): string
    {
        return json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function getAccountOrFail(string $id): object
    {
        $row = DB::connection($this->conn)->table($this->table)->where('id', $id)->first();
        abort_if(!$row, 404, 'Cuenta no encontrada (SOT)');
        return $row;
    }

    /** Normaliza estado a enum permitido */
    public function normState(?string $s): string
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
    public function buildModulesStateFromMeta(array $meta): array
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

    /** Genera legacy boolean para compat (active=true; inactive/hidden/blocked=false) */
    public function buildLegacyBoolFromState(array $modulesState): array
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
     * - Respeta 'custom' (no lo pisa).
     * @return bool true si guardó cambios
     */
    public function ensureBillingMetaIntegrityInPlace(object $account): bool
    {
        if (!$this->hasMetaColumn()) return false;

        $catalog = $this->priceCatalog();
        $meta = $this->decodeMeta($account->{$this->metaCol} ?? null);

        $pk = (string) data_get($meta, 'billing.price_key', '');

        // ✅ Si es licencia personalizada, no forzamos catálogo.
        if ($pk === 'custom') return false;

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

    private function clientesConn(): string
    {
        // Permite override por config o env, con fallback estable
        $c = (string) (config('p360.conn.clientes') ?: '');
        $c = trim($c);

        if ($c !== '') return $c;

        $e = (string) (env('P360_CLIENTES_CONN') ?: '');
        $e = trim($e);

        return $e !== '' ? $e : 'mysql_clientes';
    }

   /**
     * Sincroniza Bóveda Fiscal a DB clientes (cuentas_cliente.vault_active)
     * - En Admin: modules_state['boveda_fiscal'] === 'active' => vault_active=1
     * - Cualquier otro estado => vault_active=0
     */
    private function syncVaultActiveToClientes(int|string $adminAccountId, string $state): void
    {
        $cliConn = $this->clientesConn();

        try {
            if (!Schema::connection($cliConn)->hasTable('cuentas_cliente')) {
                Log::warning('AdminBilling.vaultSync.no_table', [
                    'cli_conn' => $cliConn,
                    'table'    => 'cuentas_cliente',
                ]);
                return;
            }

            foreach (['vault_active', 'admin_account_id', 'updated_at'] as $col) {
                if (!Schema::connection($cliConn)->hasColumn('cuentas_cliente', $col)) {
                    Log::warning('AdminBilling.vaultSync.no_column', [
                        'cli_conn' => $cliConn,
                        'table'    => 'cuentas_cliente',
                        'column'   => $col,
                    ]);
                    return;
                }
            }

            $st = strtolower(trim((string) $state));
            $on = ($st === self::MOD_ACTIVE) ? 1 : 0;

            $adminIdInt = (int) $adminAccountId;

            // 1) update por llave canónica admin_account_id
            $q = DB::connection($cliConn)->table('cuentas_cliente')->where('admin_account_id', $adminIdInt);

            $affected = $q->update([
                'vault_active' => $on,
                'updated_at'   => now(),
            ]);

            // 2) fallback opcional: si no actualizó nada y el ID parece numérico, intenta por id (solo si existe y es numérico)
            //    (En tu esquema clientes.id suele ser UUID, así que esto normalmente NO aplicará.)
            if ($affected <= 0) {
                $idStr = trim((string) $adminAccountId);
                if ($idStr !== '' && preg_match('/^\d+$/', $idStr) && Schema::connection($cliConn)->hasColumn('cuentas_cliente', 'id')) {
                    $affected2 = DB::connection($cliConn)->table('cuentas_cliente')->where('id', $idStr)->update([
                        'vault_active' => $on,
                        'updated_at'   => now(),
                    ]);

                    if ($affected2 > 0) {
                        $affected = $affected2;
                    }
                }
            }

            Log::info('AdminBilling.vaultSync.applied', [
                'admin_account_id' => $adminIdInt,
                'state'            => $st,
                'vault_active'     => $on,
                'rows'             => $affected,
                'cli_conn'         => $cliConn,
            ]);
        } catch (\Throwable $e) {
            Log::error('AdminBilling.vaultSync.failed', [
                'admin_account_id' => (int) $adminAccountId,
                'state'            => (string) $state,
                'cli_conn'         => $cliConn,
                'err'              => $e->getMessage(),
            ]);
        }
    }

    // =========================
    // Acciones “core” reusables
    // =========================

    public function updateLicense(string $accountId, Request $request): void
    {
        abort_if(!$this->hasMetaColumn(), 422, "No existe columna '{$this->metaCol}'.");

        $account = $this->getAccountOrFail($accountId);
        $meta = $this->decodeMeta($account->{$this->metaCol} ?? null);

        $priceKey = trim((string) $request->input('price_key', ''));

        // ================= CUSTOM LICENSE =================
        if ($priceKey === 'custom') {
            $cycle  = trim((string) $request->input('billing_cycle', 'monthly'));
            $amount = $request->input('amount_mxn');

            abort_if(!in_array($cycle, ['monthly','yearly'], true), 422, 'Ciclo inválido');
            abort_if(!is_numeric($amount), 422, 'Monto inválido');

            $amount = (int) $amount;
            abort_if($amount < 0, 422, 'Monto inválido');

            data_set($meta, 'billing.price_key', 'custom');
            data_set($meta, 'billing.billing_cycle', $cycle);
            data_set($meta, 'billing.amount_mxn', $amount);
            data_set($meta, 'billing.stripe_price_id', null);

            data_set($meta, 'billing.custom.label', (string) $request->input('custom_label', 'Licencia personalizada'));
            data_set($meta, 'billing.custom.defined_by', 'admin');
            data_set($meta, 'billing.custom.updated_at', now()->toISOString());

            data_set($meta, 'billing.assigned_at', now()->toISOString());
            data_set($meta, 'billing.assigned_by', 'admin.custom');

            DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update([
                $this->metaCol => $this->encodeMeta($meta),
                'updated_at'   => now(),
            ]);

            $updSot = [];
            if (Schema::connection($this->conn)->hasColumn($this->table, 'plan')) $updSot['plan'] = 'custom';
            if (Schema::connection($this->conn)->hasColumn($this->table, 'billing_cycle')) $updSot['billing_cycle'] = $cycle;
            if (!empty($updSot)) {
                $updSot['updated_at'] = now();
                DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update($updSot);
            }

            Log::info('AdminBilling.updateLicense.custom', [
                'conn' => $this->conn,
                'account_id' => (string)$account->id,
                'cycle' => $cycle,
                'amount_mxn' => $amount,
            ]);

            return;
        }

        // ================= CATÁLOGO NORMAL =================
        $catalog = $this->priceCatalog();
        abort_if($priceKey === '' || !isset($catalog[$priceKey]), 422, 'price_key inválido');

        $p = $catalog[$priceKey];
        $cycle = (string) ($p['billing_cycle'] ?? 'monthly');
        $amount = (int) ($p['amount_mxn'] ?? 0);
        $stripePriceId = $p['stripe_price_id'] ?? null;

        data_forget($meta, 'billing.custom');

        data_set($meta, 'billing.price_key', $priceKey);
        data_set($meta, 'billing.billing_cycle', $cycle);
        data_set($meta, 'billing.amount_mxn', $amount);
        data_set($meta, 'billing.stripe_price_id', $stripePriceId);
        data_set($meta, 'billing.assigned_at', now()->toISOString());
        data_set($meta, 'billing.assigned_by', 'admin');

        DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update([
            $this->metaCol => $this->encodeMeta($meta),
            'updated_at'   => now(),
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
    }

    public function updateOverride(string $accountId, Request $request): array
    {
        abort_if(!$this->hasMetaColumn(), 422, "No existe columna '{$this->metaCol}'.");

        $account = $this->getAccountOrFail($accountId);
        $meta = $this->decodeMeta($account->{$this->metaCol} ?? null);

        $mode      = (string) $request->input('override_mode', 'none');
        $cycle     = (string) $request->input('override_cycle', 'monthly'); // monthly|yearly
        $effective = (string) $request->input('override_effective', 'next'); // now|next

        $cycle = in_array($cycle, ['monthly','yearly'], true) ? $cycle : 'monthly';
        $effective = in_array($effective, ['now','next'], true) ? $effective : 'next';

        if ($mode === 'none') {
            data_forget($meta, "billing.override.$cycle");

            if ($cycle === 'monthly') {
                data_forget($meta, 'billing.override.amount_mxn');
                data_forget($meta, 'billing.override_amount_mxn');
            }

            DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update([
                $this->metaCol => $this->encodeMeta($meta),
                'updated_at'   => now(),
            ]);

            Log::info('AdminBilling.overrideCleared', [
                'conn' => $this->conn,
                'account_id' => (string)$account->id,
                'cycle' => $cycle,
            ]);

            return ['cycle' => $cycle, 'cleared' => true];
        }

        $amount = $request->input('override_amount_mxn');
        abort_if(!is_numeric($amount), 422, 'Monto inválido');

        $amount = (int) $amount;
        abort_if($amount < 0, 422, 'Monto inválido');

        data_set($meta, "billing.override.$cycle.amount_mxn", $amount);
        data_set($meta, "billing.override.$cycle.effective", $effective);
        data_set($meta, "billing.override.$cycle.updated_at", now()->toISOString());
        data_set($meta, "billing.override.$cycle.updated_by", 'admin');

        if ($cycle === 'monthly') {
            data_set($meta, 'billing.override.amount_mxn', $amount);
            data_set($meta, 'billing.override.effective', $effective);
            data_set($meta, 'billing.override.updated_at', now()->toISOString());
            data_set($meta, 'billing.override.updated_by', 'admin.compat');

            data_set($meta, 'billing.override_amount_mxn', $amount);
        }

        DB::connection($this->conn)->table($this->table)->where('id', (string)$account->id)->update([
            $this->metaCol => $this->encodeMeta($meta),
            'updated_at'   => now(),
        ]);

        Log::info('AdminBilling.overrideSaved', [
            'conn' => $this->conn,
            'account_id' => (string)$account->id,
            'cycle' => $cycle,
            'override_amount_mxn' => $amount,
            'effective' => $effective,
        ]);

        return ['cycle' => $cycle, 'cleared' => false, 'amount' => $amount, 'effective' => $effective];
    }

    public function getModulesStateCached(string $accountId, bool $forceRefresh = false): array
    {
        $cacheKey = 'p360:mods:acct:' . (string) $accountId;

        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        $account = $this->getAccountOrFail($accountId);

        $meta = $this->decodeMeta($account->{$this->metaCol} ?? null);
        $state = $this->buildModulesStateFromMeta($meta);

        // Guardrail: si en meta venían keys raras o faltantes, aquí ya queda completo por catálogo.
        // Opcional: podrías persistir para “sanear” una vez, pero mejor dejarlo read-only.
        Cache::put($cacheKey, $state, now()->addHours(6));

        return $state;
    }

    public function getLegacyModulesBoolCached(string $accountId, bool $forceRefresh = false): array
    {
        $state = $this->getModulesStateCached($accountId, $forceRefresh);
        return $this->buildLegacyBoolFromState($state);
    }

    public function updateModules(string $accountId, Request $request): array
    {
        abort_if(!$this->hasMetaColumn(), 422, "No existe columna '{$this->metaCol}' en {$this->conn}.{$this->table}.");

        $account = $this->getAccountOrFail($accountId);
        $meta = $this->decodeMeta($account->{$this->metaCol} ?? null);

        // Estado anterior (para comparar)
        $prevState = $this->buildModulesStateFromMeta($meta);

        $incoming = (array) $request->input('modules_state', []);
        $outState = $prevState; // ✅ PATCH semántico: partimos del anterior y aplicamos incoming

        foreach ($incoming as $k => $raw) {
            if (!array_key_exists($k, $this->modulesCatalog)) continue;
            $outState[$k] = $this->normState(is_string($raw) ? $raw : (string) $raw);
        }

        // ✅ invalida cache SIEMPRE (antes y después) para evitar estados pegados
        Cache::forget('p360:mods:acct:' . (string) $account->id);

        // Si no hubo cambios reales, igual regresamos el state pero ya sin cache viejo
        if ($prevState === $outState) {
            return $outState;
        }

        data_set($meta, 'modules_state', $outState);
        data_set($meta, 'modules_state_updated_at', now()->toISOString());
        data_set($meta, 'modules_state_updated_by', 'admin');

        $legacyBool = $this->buildLegacyBoolFromState($outState);
        data_set($meta, 'modules', $legacyBool);
        data_set($meta, 'modules_updated_at', now()->toISOString());
        data_set($meta, 'modules_updated_by', 'admin.compat');

        DB::connection($this->conn)->table($this->table)->where('id', (string) $account->id)->update([
            $this->metaCol => $this->encodeMeta($meta),
            'updated_at'   => now(),
        ]);

        // Sync clientes: bóveda fiscal
        $this->syncVaultActiveToClientes((int) $account->id, (string) ($outState['boveda_fiscal'] ?? self::MOD_INACTIVE));

        // Re-cachear con nuevo estado
        Cache::put('p360:mods:acct:' . (string) $account->id, $outState, now()->addHours(6));

        Log::info('AdminBilling.modulesStateSaved', [
            'conn'         => $this->conn,
            'account_id'   => (string) $account->id,
            'modules_state'=> $outState,
        ]);

        return $outState;
    }

}