<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Cliente\Billing\PortalBillingContextService.php

declare(strict_types=1);

namespace App\Services\Cliente\Billing;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class PortalBillingContextService
{
    private string $adm;
    private string $cli;

    public function __construct()
    {
        $this->adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        $this->cli = (string) (
            config('p360.conn.clientes')
            ?: config('p360.conn.clients')
            ?: 'mysql_clientes'
        );
    }

    public function connAdmin(): string
    {
        return $this->adm;
    }

    public function connClientes(): string
    {
        return $this->cli;
    }

    public function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', trim($period));
    }

    public function parseToPeriod(mixed $value): ?string
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->format('Y-m');
            }

            if (is_numeric($value)) {
                $ts = (int) $value;
                if ($ts > 0) {
                    return Carbon::createFromTimestamp($ts)->format('Y-m');
                }
            }

            if (is_string($value)) {
                $v = trim($value);
                if ($v === '') {
                    return null;
                }

                $v = str_replace('/', '-', $v);

                if ($this->isValidPeriod($v)) {
                    return $v;
                }

                if (preg_match('/^\d{4}-(0[1-9]|1[0-2])-\d{2}$/', $v)) {
                    return Carbon::parse($v)->format('Y-m');
                }

                return Carbon::parse($v)->format('Y-m');
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    public function normalizePeriodOrNow(?string $period, ?string $fallback = null): string
    {
        $period = trim((string) $period);
        if ($period !== '' && $this->isValidPeriod($period)) {
            return $period;
        }

        $fallback = trim((string) $fallback);
        if ($fallback !== '' && $this->isValidPeriod($fallback)) {
            return $fallback;
        }

        return now()->format('Y-m');
    }

    public function resolveAdminAccountId(Request $req): array
    {
        $u = Auth::guard('web')->user();

        $toInt = static function ($v): int {
            if ($v === null) return 0;
            if (is_int($v)) return $v > 0 ? $v : 0;
            if (is_numeric($v)) {
                $i = (int) $v;
                return $i > 0 ? $i : 0;
            }
            if (is_string($v)) {
                $v = trim($v);
                if ($v !== '' && is_numeric($v)) {
                    $i = (int) $v;
                    return $i > 0 ? $i : 0;
                }
            }
            return 0;
        };

        $toStr = static function ($v): string {
            if ($v === null) return '';
            if (is_string($v)) return trim($v);
            return trim((string) $v);
        };

        $pickSessionId = function (Request $req, array $keys) use ($toInt): array {
            foreach ($keys as $k) {
                $v  = $req->session()->get($k);
                $id = $toInt($v);
                if ($id > 0) {
                    return [$id, 'session.' . $k];
                }
            }
            return [0, ''];
        };

        $routeAccountId = null;
        try {
            $routeAccountId = $req->route('account_id');
        } catch (\Throwable $e) {
            $routeAccountId = null;
        }

        $accountIdFromParam =
            $toInt($routeAccountId)
            ?: $toInt($req->query('account_id'))
            ?: $toInt($req->input('account_id'))
            ?: $toInt($req->query('aid'))
            ?: $toInt($req->input('aid'));

        if ($accountIdFromParam > 0) {
            try {
                $req->session()->put('billing.admin_account_id', $accountIdFromParam);
                $req->session()->put('billing.admin_account_src', 'param.account_id');
            } catch (\Throwable $e) {
            }

            return [$accountIdFromParam, 'param.account_id'];
        }

        $clientCuentaIdRaw =
            $req->session()->get('client.cuenta_id')
            ?? $req->session()->get('cuenta_id')
            ?? $req->session()->get('client_cuenta_id')
            ?? null;

        $clientCuentaId = $toStr($clientCuentaIdRaw);

        if ($clientCuentaId === '') {
            try {
                $email = strtolower(trim((string) ($u?->email ?? '')));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    if (Schema::connection($this->cli)->hasTable('usuarios_cuenta')) {
                        $cols = Schema::connection($this->cli)->getColumnListing('usuarios_cuenta');
                        $lc   = array_map('strtolower', $cols);
                        $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

                        if ($has('email') && $has('cuenta_id')) {
                            $q = DB::connection($this->cli)->table('usuarios_cuenta')
                                ->whereRaw('LOWER(TRIM(email)) = ?', [$email]);

                            if ($has('activo')) {
                                $q->where('activo', 1);
                            }

                            if ($has('rol')) {
                                $q->orderByRaw("CASE WHEN rol='owner' THEN 0 ELSE 1 END");
                            }

                            if ($has('tipo')) {
                                $q->orderByRaw("CASE WHEN tipo='owner' THEN 0 ELSE 1 END");
                            }

                            $orderCol = $has('created_at') ? 'created_at' : ($has('id') ? 'id' : $cols[0]);
                            $q->orderByDesc($orderCol);

                            $row = $q->first(['cuenta_id', 'email']);

                            $cid = $toStr($row?->cuenta_id ?? '');
                            if ($cid !== '') {
                                $clientCuentaId = $cid;

                                try {
                                    $req->session()->put('client.cuenta_id', $clientCuentaId);
                                } catch (\Throwable $e) {
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $adminFromClientCuenta = 0;
        $adminFromClientSrc    = '';

        if ($clientCuentaId !== '') {
            try {
                if (Schema::connection($this->cli)->hasTable('cuentas_cliente')) {
                    $cols = Schema::connection($this->cli)->getColumnListing('cuentas_cliente');
                    $lc   = array_map('strtolower', $cols);
                    $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

                    $sel = ['id'];
                    foreach (['admin_account_id', 'account_id', 'meta', 'rfc', 'rfc_padre', 'razon_social'] as $c) {
                        if ($has($c)) $sel[] = $c;
                    }
                    $sel = array_values(array_unique($sel));

                    $q = DB::connection($this->cli)->table('cuentas_cliente')->select($sel);

                    $q->where('id', $clientCuentaId);

                    $asInt = $toInt($clientCuentaId);
                    if ($asInt > 0) {
                        $q->orWhere('id', $asInt);
                    }

                    if ($has('meta')) {
                        foreach ([
                            '$.cuenta_uuid',
                            '$.cuenta.id',
                            '$.cuenta_id',
                            '$.uuid',
                            '$.public_id',
                            '$.cliente_uuid',
                        ] as $path) {
                            $q->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, ?)) = ?", [$path, $clientCuentaId]);
                        }
                    }

                    $cc = $q->first();

                    if ($cc) {
                        if ($has('admin_account_id')) {
                            $aid = $toInt($cc->admin_account_id ?? null);
                            if ($aid > 0) {
                                $adminFromClientCuenta = $aid;
                                $adminFromClientSrc    = 'cuentas_cliente.admin_account_id';
                            }
                        }

                        if ($adminFromClientCuenta <= 0 && $has('account_id')) {
                            $aid = $toInt($cc->account_id ?? null);
                            if ($aid > 0) {
                                $adminFromClientCuenta = $aid;
                                $adminFromClientSrc    = 'cuentas_cliente.account_id';
                            }
                        }

                        if ($adminFromClientCuenta <= 0 && $has('meta') && isset($cc->meta)) {
                            try {
                                $meta = is_string($cc->meta) ? (json_decode((string) $cc->meta, true) ?: []) : (array) $cc->meta;
                                $aid  = $toInt(data_get($meta, 'admin_account_id'));
                                if ($aid > 0) {
                                    $adminFromClientCuenta = $aid;
                                    $adminFromClientSrc    = 'cuentas_cliente.meta.admin_account_id';
                                }
                            } catch (\Throwable $e) {
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                $adminFromClientCuenta = 0;
                $adminFromClientSrc    = '';
            }
        }

        $adminFromUserRel = 0;
        try {
            if ($u && method_exists($u, 'relationLoaded') && !$u->relationLoaded('cuenta')) {
                try {
                    $u->load('cuenta');
                } catch (\Throwable $e) {
                }
            }

            $relAdmin = null;
            try {
                $relAdmin = $u?->cuenta?->admin_account_id ?? null;
            } catch (\Throwable $e) {
                $relAdmin = null;
            }

            $adminFromUserRel = $toInt($relAdmin);
        } catch (\Throwable $e) {
            $adminFromUserRel = 0;
        }

        $adminFromUserField = $toInt($u?->admin_account_id ?? null);

        [$adminFromSessionDirect, $sessionDirectSrc] = $pickSessionId($req, [
            'billing.admin_account_id',
            'verify.account_id',
            'paywall.account_id',
            'client.admin_account_id',
            'admin_account_id',
            'account_id',
            'client.account_id',
            'client_account_id',
        ]);

        if ($adminFromClientCuenta > 0) {
            try {
                $req->session()->put('billing.admin_account_id', $adminFromClientCuenta);
                $req->session()->put('billing.admin_account_src', (string) ($adminFromClientSrc ?: 'cuentas_cliente'));
            } catch (\Throwable $e) {
            }

            return [$adminFromClientCuenta, $adminFromClientSrc ?: 'cuentas_cliente'];
        }

        if ($adminFromUserRel > 0) {
            try {
                $req->session()->put('billing.admin_account_id', $adminFromUserRel);
                $req->session()->put('billing.admin_account_src', 'user.cuenta.admin_account_id');
            } catch (\Throwable $e) {
            }

            return [$adminFromUserRel, 'user.cuenta.admin_account_id'];
        }

        if ($adminFromUserField > 0) {
            try {
                $req->session()->put('billing.admin_account_id', $adminFromUserField);
                $req->session()->put('billing.admin_account_src', 'user.admin_account_id');
            } catch (\Throwable $e) {
            }

            return [$adminFromUserField, 'user.admin_account_id'];
        }

        if ($adminFromSessionDirect > 0) {
            try {
                $req->session()->put('billing.admin_account_id', $adminFromSessionDirect);
                $req->session()->put('billing.admin_account_src', (string) ($sessionDirectSrc ?: 'session.direct'));
            } catch (\Throwable $e) {
            }

            return [$adminFromSessionDirect, $sessionDirectSrc ?: 'session.direct'];
        }

        return [0, 'unresolved'];
    }

    public function resolveAdminAccountIdFromClientAccount(object $clientAccount): int
    {
        if (isset($clientAccount->admin_account_id) && is_numeric($clientAccount->admin_account_id)) {
            $id = (int) $clientAccount->admin_account_id;
            if ($id > 0) return $id;
        }

        if (isset($clientAccount->account_id) && is_numeric($clientAccount->account_id)) {
            $id = (int) $clientAccount->account_id;
            if ($id > 0) return $id;
        }

        $meta = [];
        try {
            if (isset($clientAccount->meta)) {
                $meta = is_string($clientAccount->meta)
                    ? (json_decode((string) $clientAccount->meta, true) ?: [])
                    : (array) $clientAccount->meta;
            }
        } catch (\Throwable $e) {
            $meta = [];
        }

        $id = (int) (data_get($meta, 'admin_account_id') ?? 0);

        return $id > 0 ? $id : 0;
    }

    public function resolveAdminAccountIdFromClientUuid(string $uuid): int
    {
        $uuid = trim($uuid);
        if ($uuid === '') return 0;

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            return 0;
        }

        try {
            if (Schema::connection($this->cli)->hasTable('usuarios_cuenta')) {
                $uqCols = Schema::connection($this->cli)->getColumnListing('usuarios_cuenta');
                $uqLC   = array_map('strtolower', $uqCols);
                $hasUq  = fn (string $c) => in_array(strtolower($c), $uqLC, true);

                $sel = [];
                foreach (['id', 'cuenta_id', 'email', 'rol', 'tipo', 'activo', 'created_at', 'admin_account_id'] as $c) {
                    if ($hasUq($c)) $sel[] = $c;
                }
                if (!$sel) $sel = ['id'];

                $q = DB::connection($this->cli)->table('usuarios_cuenta')->select($sel);

                if ($hasUq('cuenta_id')) {
                    $q->where('cuenta_id', $uuid);
                } else {
                    return 0;
                }

                if ($hasUq('activo')) $q->where('activo', 1);
                if ($hasUq('rol'))    $q->orderByRaw("CASE WHEN rol='owner' THEN 0 ELSE 1 END");
                if ($hasUq('tipo'))   $q->orderByRaw("CASE WHEN tipo='owner' THEN 0 ELSE 1 END");
                if ($hasUq('created_at')) $q->orderByDesc('created_at');

                $urow = $q->first();

                if ($urow) {
                    if (isset($urow->admin_account_id) && is_numeric($urow->admin_account_id) && (int) $urow->admin_account_id > 0) {
                        return (int) $urow->admin_account_id;
                    }

                    $email = strtolower(trim((string) ($urow->email ?? '')));
                    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        if (Schema::connection($this->adm)->hasTable('accounts')) {
                            $aCols = Schema::connection($this->adm)->getColumnListing('accounts');
                            $aLC   = array_map('strtolower', $aCols);
                            $hasA  = fn (string $c) => in_array(strtolower($c), $aLC, true);

                            if ($hasA('email')) {
                                $acc = DB::connection($this->adm)->table('accounts')
                                    ->select(['id', 'email'])
                                    ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                                    ->orderByDesc('id')
                                    ->first();

                                if ($acc && isset($acc->id) && is_numeric($acc->id) && (int) $acc->id > 0) {
                                    return (int) $acc->id;
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return 0;
    }

    public function resolveRfcAliasForUi(Request $req, int $adminAccountId): array
    {
        $u = Auth::guard('web')->user();

        $rfc = '';
        $alias = '';

        foreach (['rfc', 'tax_id', 'taxid', 'taxId', 'rfc_fiscal'] as $k) {
            if (!empty($u?->{$k})) {
                $rfc = (string) $u->{$k};
                break;
            }
        }

        foreach (['alias', 'name', 'nombre', 'nombre_comercial', 'razon_social', 'empresa'] as $k) {
            if (!empty($u?->{$k})) {
                $alias = (string) $u->{$k};
                break;
            }
        }

        $clientAccountId = $req->session()->get('client.cuenta_id')
            ?? $req->session()->get('cuenta_id')
            ?? $req->session()->get('client.account_id')
            ?? $req->session()->get('account_id')
            ?? $req->session()->get('client_account_id');

        try {
            if ($clientAccountId && Schema::connection($this->cli)->hasTable('cuentas_cliente')) {
                $cols = Schema::connection($this->cli)->getColumnListing('cuentas_cliente');
                $lc = array_map('strtolower', $cols);
                $has = fn (string $c) => in_array(strtolower($c), $lc, true);

                $sel = ['id'];
                foreach (['rfc', 'rfc_fiscal', 'razon_social', 'nombre_comercial', 'alias', 'email'] as $c) {
                    if ($has($c)) $sel[] = $c;
                }

                $tbl = DB::connection($this->cli)->table('cuentas_cliente')
                    ->select(array_values(array_unique($sel)));

                $cc = $tbl->where('id', $clientAccountId)->first();

                if (!$cc) {
                    $altCols = [];
                    foreach (['uuid', 'public_id', 'cuenta_uuid', 'uid'] as $c) {
                        if ($has($c)) $altCols[] = $c;
                    }

                    foreach ($altCols as $col) {
                        $cc = DB::connection($this->cli)->table('cuentas_cliente')
                            ->select(array_values(array_unique($sel)))
                            ->where($col, $clientAccountId)
                            ->first();

                        if ($cc) break;
                    }
                }

                if ($cc) {
                    if ($rfc === '') {
                        foreach (['rfc', 'rfc_fiscal'] as $k) {
                            if ($has($k) && !empty($cc->{$k})) {
                                $rfc = (string) $cc->{$k};
                                break;
                            }
                        }
                    }

                    if ($alias === '') {
                        if ($has('alias') && !empty($cc->alias)) {
                            $alias = (string) $cc->alias;
                        } elseif ($has('nombre_comercial') && !empty($cc->nombre_comercial)) {
                            $alias = (string) $cc->nombre_comercial;
                        } elseif ($has('razon_social') && !empty($cc->razon_social)) {
                            $alias = (string) $cc->razon_social;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolveRfcAliasForUi cuentas_cliente failed', [
                'admin_account_id' => $adminAccountId,
                'clientAccountId'  => $clientAccountId,
                'err'              => $e->getMessage(),
            ]);
        }

        try {
            if (Schema::connection($this->adm)->hasTable('accounts')) {
                $cols = Schema::connection($this->adm)->getColumnListing('accounts');
                $lc = array_map('strtolower', $cols);
                $has = fn (string $c) => in_array(strtolower($c), $lc, true);

                $select = ['id'];
                foreach (['rfc', 'razon_social', 'nombre_comercial', 'alias', 'meta'] as $c) {
                    if ($has($c)) $select[] = $c;
                }

                $acc = DB::connection($this->adm)->table('accounts')
                    ->select(array_values(array_unique($select)))
                    ->where('id', $adminAccountId)
                    ->first();

                if ($acc) {
                    if ($rfc === '' && $has('rfc') && !empty($acc->rfc)) {
                        $rfc = (string) $acc->rfc;
                    }

                    if ($alias === '') {
                        if ($has('alias') && !empty($acc->alias)) {
                            $alias = (string) $acc->alias;
                        } elseif ($has('nombre_comercial') && !empty($acc->nombre_comercial)) {
                            $alias = (string) $acc->nombre_comercial;
                        } elseif ($has('razon_social') && !empty($acc->razon_social)) {
                            $alias = (string) $acc->razon_social;
                        }
                    }

                    if ($has('meta') && isset($acc->meta)) {
                        $meta = [];
                        try {
                            $meta = is_string($acc->meta) ? (json_decode((string) $acc->meta, true) ?: []) : (array) $acc->meta;
                        } catch (\Throwable $e) {
                            $meta = [];
                        }

                        $billing = (array) ($meta['billing'] ?? []);
                        $company = (array) ($meta['company'] ?? []);

                        if ($rfc === '') {
                            foreach ([
                                $billing['rfc'] ?? null,
                                $billing['rfc_fiscal'] ?? null,
                                $company['rfc'] ?? null,
                                data_get($meta, 'rfc'),
                                data_get($meta, 'company.rfc'),
                            ] as $v) {
                                $v = is_string($v) ? trim($v) : '';
                                if ($v !== '') {
                                    $rfc = $v;
                                    break;
                                }
                            }
                        }

                        if ($alias === '') {
                            foreach ([
                                $billing['alias'] ?? null,
                                $billing['nombre_comercial'] ?? null,
                                $company['nombre_comercial'] ?? null,
                                $company['razon_social'] ?? null,
                                data_get($meta, 'alias'),
                                data_get($meta, 'company.razon_social'),
                            ] as $v) {
                                $v = is_string($v) ? trim($v) : '';
                                if ($v !== '') {
                                    $alias = $v;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BILLING] resolveRfcAliasForUi accounts failed', [
                'admin_account_id' => $adminAccountId,
                'err'              => $e->getMessage(),
            ]);
        }

        if ($rfc === '') $rfc = '—';
        if ($alias === '') $alias = '—';

        return [$rfc, $alias];
    }

    public function resolveContractStartPeriod(int $accountId): string
    {
        $fallback = now()->format('Y-m');

        if (!Schema::connection($this->adm)->hasTable('accounts')) {
            return $fallback;
        }

        try {
            $cols = Schema::connection($this->adm)->getColumnListing('accounts');
            $lc   = array_map('strtolower', $cols);
            $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

            $select = ['id'];
            foreach (['meta', 'created_at', 'activated_at', 'starts_at', 'start_date', 'subscription_started_at', 'contracted_at'] as $c) {
                if ($has($c)) $select[] = $c;
            }

            $acc = DB::connection($this->adm)->table('accounts')
                ->select(array_values(array_unique($select)))
                ->where('id', $accountId)
                ->first();

            if (!$acc) {
                return $fallback;
            }

            $meta = [];
            if ($has('meta') && isset($acc->meta)) {
                try {
                    $meta = is_string($acc->meta) ? (json_decode((string) $acc->meta, true) ?: []) : (array) $acc->meta;
                } catch (\Throwable $e) {
                    $meta = [];
                }
            }

            foreach ([
                data_get($meta, 'billing.start_period'),
                data_get($meta, 'subscription.start_period'),
                data_get($meta, 'plan.start_period'),
                data_get($meta, 'start_period'),
            ] as $v) {
                $v = is_string($v) ? trim($v) : '';
                if ($v !== '' && $this->isValidPeriod($v)) {
                    return $v;
                }
            }

            foreach (['start_date', 'starts_at', 'subscription_started_at', 'contracted_at', 'activated_at', 'created_at'] as $c) {
                if (!$has($c)) continue;
                $p = $this->parseToPeriod($acc->{$c} ?? null);
                if ($p) {
                    return $p;
                }
            }

            return $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    public function isAnnualBillingCycle(int $accountId): bool
    {
        if ($accountId <= 0) return false;

        try {
            if (!Schema::connection($this->adm)->hasTable('accounts')) return false;

            $cols = Schema::connection($this->adm)->getColumnListing('accounts');
            $lc   = array_map('strtolower', $cols);
            $has  = fn (string $c) => in_array(strtolower($c), $lc, true);

            $sel = ['id'];
            foreach (['modo_cobro', 'meta', 'plan', 'plan_actual'] as $c) {
                if ($has($c)) $sel[] = $c;
            }
            $sel = array_values(array_unique($sel));

            $acc = DB::connection($this->adm)->table('accounts')
                ->select($sel)
                ->where('id', $accountId)
                ->first();

            if (!$acc) return false;

            $norm = static function ($v): string {
                $s = strtolower(trim((string) $v));
                if ($s === '') return '';
                $s = str_replace(["\t", "\n", "\r"], ' ', $s);
                $s = str_replace(['_', '-', '.'], ' ', $s);
                $s = preg_replace('/\s+/', ' ', $s);
                return trim((string) $s);
            };

            $mcRaw   = $has('modo_cobro') ? $norm($acc->modo_cobro ?? '') : '';
            $planRaw = $norm(($acc->plan_actual ?? '') ?: ($acc->plan ?? ''));

            $isFreeToken = static function (string $s) use ($norm): bool {
                $s = $norm($s);
                if ($s === '') return false;

                if (in_array($s, [
                    'free', 'gratis', 'gratuito', 'trial', 'prueba', 'demo',
                    'none', 'sin costo', 'sincosto', 'sin pago', 'sinpago',
                ], true)) {
                    return true;
                }

                return str_contains($s, 'free')
                    || str_contains($s, 'gratis')
                    || str_contains($s, 'trial')
                    || str_contains($s, 'demo')
                    || str_contains($s, 'sin costo')
                    || str_contains($s, 'sin pago');
            };

            if ($isFreeToken($mcRaw) || $isFreeToken($planRaw)) {
                return false;
            }

            $isAnnualToken = static function (string $s) use ($norm): bool {
                $s = $norm($s);
                if ($s === '') return false;

                if (in_array($s, [
                    'anual', 'annual', 'annually',
                    'year', 'yearly',
                    '12m', '12 mes', '12 meses', '12meses',
                    '12-month', '12 months', '12months',
                    '1y', '1 year', 'one year',
                ], true)) {
                    return true;
                }

                return str_contains($s, 'anual')
                    || str_contains($s, 'annual')
                    || str_contains($s, 'year')
                    || str_contains($s, '12 mes')
                    || str_contains($s, '12m');
            };

            $isAnnualValue = static function ($v) use ($norm, $isAnnualToken): bool {
                $s = $norm($v);
                if ($s === '') return false;

                if ($isAnnualToken($s)) return true;

                $parts = preg_split('/[|,:;\/\\\\]/', $s) ?: [];
                foreach ($parts as $p) {
                    $p = trim((string) $p);
                    if ($p !== '' && $isAnnualToken($p)) {
                        return true;
                    }
                }

                return false;
            };

            if ($has('modo_cobro') && $isAnnualValue($mcRaw)) {
                return true;
            }

            if ($isAnnualValue($planRaw)) {
                return true;
            }

            if ($has('meta') && isset($acc->meta)) {
                $meta = is_string($acc->meta)
                    ? (json_decode((string) $acc->meta, true) ?: [])
                    : (array) $acc->meta;

                $candidates = [
                    data_get($meta, 'billing.billing_cycle'),
                    data_get($meta, 'billing.cycle'),
                    data_get($meta, 'billing.billingCycle'),
                    data_get($meta, 'billing.interval'),
                    data_get($meta, 'billing.modo_cobro'),
                    data_get($meta, 'billing_cycle'),
                    data_get($meta, 'stripe.billing_cycle'),
                    data_get($meta, 'stripe.cycle'),
                    data_get($meta, 'stripe.interval'),
                    data_get($meta, 'stripe.plan_interval'),
                    data_get($meta, 'subscription.billing_cycle'),
                    data_get($meta, 'subscription.cycle'),
                    data_get($meta, 'subscription.interval'),
                    data_get($meta, 'plan.billing_cycle'),
                    data_get($meta, 'plan.cycle'),
                    data_get($meta, 'plan.interval'),
                    data_get($meta, 'modo_cobro'),
                    data_get($meta, 'cycle'),
                    data_get($meta, 'interval'),
                ];

                foreach ($candidates as $v) {
                    if ($isAnnualValue($v)) {
                        return true;
                    }
                }

                try {
                    $raw = json_encode($meta, JSON_UNESCAPED_UNICODE);
                    $raw = is_string($raw) ? $norm($raw) : '';

                    if (
                        $raw !== ''
                        && (str_contains($raw, 'yearly') || str_contains($raw, 'billing_cycle'))
                        && (str_contains($raw, 'year') || str_contains($raw, 'annual') || str_contains($raw, 'anual'))
                    ) {
                        return true;
                    }
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    public function isAnnualAccount(int $accountId): bool
    {
        return $this->isAnnualBillingCycle($accountId);
    }

    public function annualRenewalWindowDays(): int
    {
        $n = (int) (config('p360.billing.annual_renewal_window_days') ?? 30);
        return $n > 0 ? $n : 30;
    }
}