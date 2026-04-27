<?php
// C:\wamp64\www\pactopia360_erp\app\Http\Controllers\Admin\ClientesController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Cliente\UsuarioCuenta;
use App\Services\ClientCredentials;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Crypt;

class ClientesController extends \App\Http\Controllers\Controller
{
    /**
     * SOT Admin (central)
     */
    protected string $adminConn = 'mysql_admin';

    /**
     * Legacy (tabla clientes). En tu error aparece Connection: mysql.
     */
    protected string $legacyConn = 'mysql';

    /**
     * Cache simple de checks de schema por request.
     * @var array<string,bool>
     */
    private array $schemaHas = [];

    // ======================= LISTADO =======================
    public function index(Request $request): View
    {
        $q             = trim((string) $request->get('q', ''));
        $planFilterRaw    = trim((string) $request->get('plan', ''));
        $blocked          = $request->get('blocked');
        $billingStatusRaw = trim((string) $request->get('billing_status', ''));

        $planFilter = strtolower($planFilterRaw);
        $billingStatus = strtolower($billingStatusRaw);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $sort    = (string) $request->get('sort', 'created_at');
        $dir     = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowed = [
            'created_at',
            'razon_social',
            'plan',
            'billing_cycle',
            'billing_status',
            'is_blocked',
            'email_verified_at',
            'phone_verified_at',
        ];
        if (!in_array($sort, $allowed, true)) {
            $sort = 'created_at';
        }

        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();
        $rfcCol   = $this->colRfcAdmin(); // en tu schema real: 'rfc'

        $query = DB::connection($this->adminConn)->table('accounts');

        // ✅ Ocultar soft-deleted por defecto
        // destroy() marca meta.deleted = true, así que aquí los excluimos del listado.
        if ($this->hasCol($this->adminConn, 'accounts', 'meta') && (string) $request->get('with_deleted', '0') !== '1') {
            $query->where(function ($qq) {
                $qq->whereNull('meta')
                   ->orWhereRaw("JSON_EXTRACT(meta, '$.deleted') IS NULL")
                   ->orWhereRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.deleted')), 'false')) NOT IN ('1','true')");
            });
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q, $emailCol, $phoneCol, $rfcCol) {
                $qq->where('id', 'like', "%{$q}%")
                    ->orWhere($rfcCol, 'like', "%{$q}%")
                    ->orWhere('razon_social', 'like', "%{$q}%")
                    ->orWhere($emailCol, 'like', "%{$q}%")
                    ->orWhere($phoneCol, 'like', "%{$q}%");
            });
        }

        if ($planFilter !== '' && $this->hasCol($this->adminConn, 'accounts', 'plan')) {
            if ($planFilter === 'pro') {
                $query->where(function ($qq) {
                    $qq->whereRaw('UPPER(plan) = ?', ['PRO'])
                       ->orWhereRaw('UPPER(plan) = ?', ['PRO_MENSUAL'])
                       ->orWhereRaw('UPPER(plan) = ?', ['PRO_ANUAL']);
                });
            } elseif ($planFilter === 'free') {
                $query->whereRaw('UPPER(plan) = ?', ['FREE']);
            } else {
                $query->whereRaw('UPPER(plan) = ?', [strtoupper($planFilter)]);
            }
        }

        if (($blocked === '0' || $blocked === '1') && $this->hasCol($this->adminConn, 'accounts', 'is_blocked')) {
            $query->where('is_blocked', (int) $blocked);
        }

        if ($billingStatus !== '' && $this->hasCol($this->adminConn, 'accounts', 'billing_status')) {
            $billingMap = [
                'active'    => ['active', 'activa'],
                'trial'     => ['trial', 'prueba'],
                'grace'     => ['grace', 'gracia'],
                'overdue'   => ['overdue', 'vencida'],
                'suspended' => ['suspended', 'suspendida'],
                'cancelled' => ['cancelled', 'cancelada'],
                'demo'      => ['demo'],
                'activa'     => ['active', 'activa'],
                'prueba'     => ['trial', 'prueba'],
                'gracia'     => ['grace', 'gracia'],
                'vencida'    => ['overdue', 'vencida'],
                'suspendida' => ['suspended', 'suspendida'],
                'cancelada'  => ['cancelled', 'cancelada'],
            ];

            $allowedStatuses = $billingMap[$billingStatus] ?? [$billingStatus];

            $query->where(function ($qq) use ($allowedStatuses) {
                foreach ($allowedStatuses as $st) {
                    $qq->orWhereRaw('LOWER(billing_status) = ?', [strtolower($st)]);
                }
            });
        }

        $schemaA = Schema::connection($this->adminConn);

        // Campos adicionales para calcular "pagando"
        $amountCols = [
            'custom_amount_mxn',
            'override_amount_mxn',
            'billing_amount_mxn',
            'amount_mxn',
            'precio_mxn',
            'monto_mxn',
            'license_amount_mxn',
        ];

        $select = [
            'id',
            DB::raw("$rfcCol as rfc"),
            'razon_social',

            // normalizamos siempre a alias "email" y "phone"
            $emailCol . ' as email',
            $phoneCol . ' as phone',

            DB::raw($schemaA->hasColumn('accounts', 'plan') ? 'plan' : "'' as plan"),
            DB::raw($schemaA->hasColumn('accounts', 'billing_cycle') ? 'billing_cycle' : "NULL as billing_cycle"),
            DB::raw($schemaA->hasColumn('accounts', 'billing_status') ? 'billing_status' : "NULL as billing_status"),
            DB::raw($schemaA->hasColumn('accounts', 'next_invoice_date') ? 'next_invoice_date' : "NULL as next_invoice_date"),
            DB::raw($schemaA->hasColumn('accounts', 'is_blocked') ? 'is_blocked' : "0 as is_blocked"),
            DB::raw($schemaA->hasColumn('accounts', 'email_verified_at') ? 'email_verified_at' : "NULL as email_verified_at"),
            DB::raw($schemaA->hasColumn('accounts', 'phone_verified_at') ? 'phone_verified_at' : "NULL as phone_verified_at"),
            'created_at',
        ];

        // meta para override billing
        if ($schemaA->hasColumn('accounts', 'meta')) {
            $select[] = 'meta';
        } else {
            $select[] = DB::raw("NULL as meta");
        }

        // alguna columna monto si existe
        foreach ($amountCols as $c) {
            if ($schemaA->hasColumn('accounts', $c)) {
                $select[] = $c;
                break;
            }
        }

        $query->select($select)->orderBy($sort, $dir);

        $rows = $query->paginate($perPage)->appends($request->query());

        // ✅ Enriquecer filas del listado con fallbacks reales
        $this->enrichRowsForListing($rows->items());

        // ✅ IMPORTANTE: aquí son IDs (accounts.id)
        $accountIds = $rows->pluck('id')->all();

        $extras     = $this->collectExtrasForAccountIds($accountIds);
        $creds      = $this->collectCredsForAccountIds($accountIds);
        $recipients = $this->collectRecipientsForAccountIds($accountIds);

        // Inyectar en $extras: recipients + monto efectivo (pagando)
        foreach ($rows as $r) {
            $id = (string) $r->id;

            if (!isset($extras[$id])) {
                $extras[$id] = [];
            }

            // recipients statement
            $recList = $recipients[$id]['statement'] ?? [];
            $emails = [];
            $primary = null;

            foreach ($recList as $rr) {
                $e = strtolower(trim((string) ($rr['email'] ?? '')));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $e;
                    if ((int) ($rr['is_primary'] ?? 0) === 1) {
                        $primary = $e;
                    }
                }
            }
            $emails = array_values(array_unique($emails));

            $extras[$id]['recipients'] = $emails;
            $extras[$id]['recipients_primary'] = $primary;

            // monto pagando (MXN)
            $extras[$id]['license_amount_mxn_effective'] = $this->computeEffectiveLicenseAmountMxn($r);

            // extras visibles para listado/drawer
            $extras[$id]['estado_cuenta'] = $extras[$id]['estado_cuenta']
                ?? (string) ($r->billing_status ?? '');

            if (!empty($r->billing_cycle)) {
                $extras[$id]['billing_cycle'] = (string) $r->billing_cycle;
            }

            if (!empty($r->next_invoice_date)) {
                $extras[$id]['next_invoice_date'] = (string) $r->next_invoice_date;
            }
        }

        $billingStatuses = [
                // EN
                'active'    => 'Activa',
                'trial'     => 'Prueba',
                'grace'     => 'Gracia',
                'overdue'   => 'Falta de pago',
                'suspended' => 'Suspendida',
                'cancelled' => 'Cancelada',
                'demo'      => 'Demo/QA',

                // ES (tu DB actual)
                'activa'     => 'Activa',
                'prueba'     => 'Prueba',
                'gracia'     => 'Gracia',
                'vencida'    => 'Falta de pago',
                'suspendida' => 'Suspendida',
                'cancelada'  => 'Cancelada',
            ];

        return view('admin.clientes.index', compact('rows', 'extras', 'creds', 'recipients', 'billingStatuses'));
    }

    // ======================= CREAR (UI SIMPLE) =======================
    public function create(Request $request): \Illuminate\Http\Response
    {
        // UI minimalista inline (no depende de layouts)
        $csrf = csrf_token();
        $postUrl = route('admin.clientes.store');

        $html = <<<HTML
        <!doctype html>
        <html lang="es">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Crear cliente · Pactopia360</title>
        <style>
            body{font:14px system-ui,Segoe UI,Roboto,sans-serif;background:#0b1020;color:#e5e7eb;margin:0;padding:24px}
            .wrap{max-width:980px;margin:0 auto}
            .card{background:#0f172a;border:1px solid #1f2a44;border-radius:14px;padding:18px 18px 14px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
            h1{margin:0 0 8px;font-size:18px}
            .muted{color:#94a3b8;margin:0 0 14px}
            .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
            label{display:block;font-size:12px;color:#cbd5e1;margin:0 0 6px}
            input,select{width:100%;box-sizing:border-box;border-radius:10px;border:1px solid #263252;background:#0b1228;color:#e5e7eb;padding:10px 12px}
            .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}
            .btn{border:1px solid #334155;background:#7c3aed;color:#fff;border-radius:10px;padding:10px 14px;font-weight:600;cursor:pointer}
            .btn2{border:1px solid #334155;background:#111827;color:#e5e7eb;border-radius:10px;padding:10px 14px;font-weight:600;text-decoration:none;display:inline-block}
            .chk{display:flex;gap:8px;align-items:center}
            .chk input{width:auto}
            .note{color:#94a3b8;font-size:12px;margin-top:10px}
            @media (max-width:860px){ .grid{grid-template-columns:1fr} }
        </style>
        </head>
        <body>
        <div class="wrap">
            <div class="card">
            <h1>Crear cliente manual</h1>
            <p class="muted">Crea cuenta SOT (admin.accounts) + espejo (mysql_clientes) + owner. Luego podrás verlo en /admin/clientes.</p>

            <form method="POST" action="{$postUrl}">
                <input type="hidden" name="_token" value="{$csrf}">

                <div class="grid">
                <div>
                    <label>RFC *</label>
                    <input name="rfc" required maxlength="20" placeholder="XAXX010101000">
                </div>

                <div>
                    <label>Razón social *</label>
                    <input name="razon_social" required maxlength="190" placeholder="Empresa SA de CV">
                </div>

                <div>
                    <label>Email (owner)</label>
                    <input name="email" type="email" maxlength="190" placeholder="correo@dominio.com">
                </div>

                <div>
                    <label>Teléfono</label>
                    <input name="phone" maxlength="25" placeholder="5512345678">
                </div>

                <div>
                    <label>Plan</label>
                    <select name="plan">
                    <option value="">(vacío)</option>
                    <option value="FREE">FREE</option>
                    <option value="PRO">PRO</option>
                    <option value="PRO_MENSUAL">PRO_MENSUAL</option>
                    <option value="PRO_ANUAL">PRO_ANUAL</option>
                    </select>
                </div>

                <div>
                    <label>Ciclo</label>
                    <select name="billing_cycle">
                    <option value="">(vacío)</option>
                    <option value="monthly">monthly</option>
                    <option value="yearly">yearly</option>
                    </select>
                </div>

                <div>
                    <label>Billing status</label>
                    <select name="billing_status">
                    <option value="">(vacío)</option>
                    <option value="active">active</option>
                    <option value="trial">trial</option>
                    <option value="grace">grace</option>
                    <option value="overdue">overdue</option>
                    <option value="suspended">suspended</option>
                    <option value="cancelled">cancelled</option>
                    <option value="demo">demo</option>
                    </select>
                </div>

                <div>
                    <label>Bloqueado</label>
                    <select name="is_blocked">
                    <option value="0">0 (no)</option>
                    <option value="1">1 (sí)</option>
                    </select>
                </div>
                </div>

                <div class="row">
                <label class="chk"><input type="checkbox" name="force_email_verified" value="1"> Marcar email verificado</label>
                <label class="chk"><input type="checkbox" name="force_phone_verified" value="1"> Marcar teléfono verificado</label>
                <label class="chk"><input type="checkbox" name="send_credentials" value="1" checked> Enviar credenciales al email</label>
                </div>

                <div class="row">
                <button class="btn" type="submit">Crear cliente</button>
                <a class="btn2" href="{$this->safeAdminClientesIndexUrl()}">Volver a clientes</a>
                </div>

                <div class="note">
                Nota: el envío de credenciales usa tu flujo <code>ClientCredentials::resetOwnerByRfc()</code>. Si falla, la cuenta se crea de todas formas.
                </div>
            </form>
            </div>
        </div>
        </body>
        </html>
        HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    // ======================= CREAR (STORE) =======================
    public function store(Request $request): RedirectResponse
    {
        $schemaA = Schema::connection($this->adminConn);

        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();
        $rfcCol   = $this->colRfcAdmin();

        $rules = [
            'rfc'                  => 'required|string|max:20',
            'razon_social'         => 'required|string|max:190',
            'email'                => 'nullable|email|max:190',
            'phone'                => 'nullable|string|max:25',
            'plan'                 => 'nullable|string|max:50',
            'billing_cycle'        => ['nullable', Rule::in(['monthly', 'yearly', '', null])],
            'billing_status'       => 'nullable|string|max:30',
            'is_blocked'           => 'nullable|boolean',
            'send_credentials'     => 'nullable|boolean',
            'force_email_verified' => 'nullable|boolean',
            'force_phone_verified' => 'nullable|boolean',
            'custom_amount_mxn'    => 'nullable|numeric|min:0|max:99999999',
        ];

        $data = validator($request->all(), $rules)->validate();

        $rfc   = strtoupper(trim((string) $data['rfc']));
        $rs    = trim((string) $data['razon_social']);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = trim((string) ($data['phone'] ?? ''));

        if ($rfc === '' || $rs === '') {
            return back()->with('error', 'RFC y Razón social son obligatorios.');
        }

        // ✅ Anti-duplicado por RFC (case-insensitive)
        $rfcColCheck = $schemaA->hasColumn('accounts', 'rfc') ? 'rfc' : $rfcCol;

        $exists = DB::connection($this->adminConn)->table('accounts')
            ->whereRaw('UPPER(' . $rfcColCheck . ') = ?', [$rfc])
            ->first(['id', $rfcColCheck]);

        if ($exists) {
            return redirect()->route('admin.clientes.index', ['q' => $rfc])
                ->with('error', "Ya existe una cuenta con RFC {$rfc} (account_id={$exists->id}).");
        }

        // Insert payload (solo columnas existentes)
        $payload = [
            'razon_social' => $rs,
            'updated_at'   => now(),
            'created_at'   => now(),
        ];

        // ==========================
        // ✅ HARD GUARANTEE (PROD)
        // ==========================
        // 1) RFC: si existe columna 'rfc', SIEMPRE mandar RFC desde el primer intento
        if ($schemaA->hasColumn('accounts', 'rfc')) {
            $payload['rfc'] = $rfc;
        } elseif ($schemaA->hasColumn('accounts', $rfcCol)) {
            $payload[$rfcCol] = $rfc;
        }

        // 2) NAME: si existe columna 'name', SIEMPRE mandar name desde el primer intento
        if ($schemaA->hasColumn('accounts', 'name')) {
            $payload['name'] = mb_substr(($rs !== '' ? $rs : $rfc), 0, 190);
        }

        // ✅ email (si existe la columna real)
        if ($schemaA->hasColumn('accounts', $emailCol)) {
            $payload[$emailCol] = ($email !== '') ? $email : null;
        }

        if ($schemaA->hasColumn('accounts', $phoneCol)) {
            $payload[$phoneCol] = ($phone !== '') ? $phone : null;
        }

        if ($schemaA->hasColumn('accounts', 'plan')) {
            $payload['plan'] = $data['plan'] ?? null;
        }

        if ($schemaA->hasColumn('accounts', 'plan_actual')) {
            $payload['plan_actual'] = $data['plan'] ?? null;
        }

        if ($schemaA->hasColumn('accounts', 'billing_cycle')) {
            $payload['billing_cycle'] = $data['billing_cycle'] ?? null;
        }

        if ($schemaA->hasColumn('accounts', 'billing_status')) {
            $bs = trim((string) ($data['billing_status'] ?? ''));
            $payload['billing_status'] = ($bs !== '') ? $bs : null;
        }

        if ($schemaA->hasColumn('accounts', 'is_blocked')) {
            $payload['is_blocked'] = (int) ($data['is_blocked'] ?? 0);
        }

        // meta default mínimo
        $customAmount = array_key_exists('custom_amount_mxn', $data) ? (float) $data['custom_amount_mxn'] : null;
        $customAmount = ($customAmount !== null && $customAmount > 0.00001) ? round($customAmount, 2) : null;

        if ($customAmount !== null) {
            if ($schemaA->hasColumn('accounts', 'meta')) {
                $meta = isset($meta) && is_array($meta) ? $meta : [];
                data_set($meta, 'billing.override.amount_mxn', $customAmount);
                data_set($meta, 'billing.override.enabled', true);
                $payload['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            }

            foreach (['custom_amount_mxn', 'override_amount_mxn', 'billing_amount_mxn', 'amount_mxn', 'license_amount_mxn'] as $col) {
                if ($schemaA->hasColumn('accounts', $col)) {
                    $payload[$col] = $customAmount;
                    break;
                }
            }
        }

        // ✅ Crear en admin.accounts (RETRY robusto)
        $accountId = null;
        $attempts = 0;
        $lastErr  = null;

        while ($attempts < 3) {
            $attempts++;

            try {
                // 🔒 Hard guarantee antes de intentar insertar
                if ($schemaA->hasColumn('accounts', 'name')) {
                    if (!array_key_exists('name', $payload) || trim((string) $payload['name']) === '') {
                        $payload['name'] = mb_substr(($rs !== '' ? $rs : $rfc), 0, 190);
                    }
                }
                if ($schemaA->hasColumn('accounts', 'rfc')) {
                    if (!array_key_exists('rfc', $payload) || trim((string) $payload['rfc']) === '') {
                        $payload['rfc'] = $rfc;
                    }
                }

                $accountId = (string) DB::connection($this->adminConn)->table('accounts')->insertGetId($payload);
                $lastErr = null;
                break;

            } catch (\Throwable $e) {
                $msg = (string) $e->getMessage();
                $lastErr = $e;

                // 1) Falta NAME => agregar y reintentar
                if (stripos($msg, "Field 'name' doesn't have a default value") !== false) {
                    $payload['name'] = mb_substr(($rs !== '' ? $rs : $rfc), 0, 190);
                    continue;
                }

                // 2) Falta RFC => agregar y reintentar
                if (stripos($msg, "Field 'rfc' doesn't have a default value") !== false) {
                    $payload['rfc'] = $rfc;
                    continue;
                }

                // 3) Unknown column => quitar y reintentar
                if (stripos($msg, "Unknown column 'name'") !== false) {
                    unset($payload['name']);
                    continue;
                }
                if (stripos($msg, "Unknown column 'rfc'") !== false) {
                    unset($payload['rfc']);
                    continue;
                }

                break;
            }
        }

        if (!$accountId) {
            try {
                Log::error('clientes.store insert admin.accounts failed', [
                    'rfc'      => $rfc,
                    'attempts' => $attempts,
                    'payload_keys' => array_keys($payload),
                    'error'    => $lastErr ? $lastErr->getMessage() : 'unknown',
                ]);
            } catch (\Throwable $e) {
                // ignore
            }

            return back()->with('error', 'No se pudo crear la cuenta en admin.accounts: ' . ($lastErr ? $lastErr->getMessage() : 'error desconocido'));
        }

        // ✅ Legacy clientes (por RFC real)
        try {
            $this->upsertClienteLegacy($rfc, ['razon_social' => $rs] + $payload);
        } catch (\Throwable $e) {
            try { Log::warning('clientes.store upsert legacy failed: ' . $e->getMessage(), ['rfc' => $rfc]); } catch (\Throwable $e2) {}
        }

        // ✅ Espejo + Owner (mysql_clientes)
        try {
            $this->ensureMirrorAndOwner($accountId, $rfc);

            $this->syncPlanToMirror($accountId, [
                'plan'              => $data['plan'] ?? null,
                'billing_cycle'     => $data['billing_cycle'] ?? null,
                'next_invoice_date' => null,
            ]);

        } catch (\Throwable $e) {
            try {
                Log::warning('clientes.store ensureMirrorAndOwner failed: ' . $e->getMessage(), [
                    'account_id' => $accountId,
                    'rfc'        => $rfc,
                ]);
            } catch (\Throwable $e2) {}
        }

        // ✅ Forzar verificados si se pidió
        try {
            $forceEmail = (bool) ($data['force_email_verified'] ?? false);
            $forcePhone = (bool) ($data['force_phone_verified'] ?? false);

            if ($forceEmail) {
                $this->markVerifiedAdminAndMirror($accountId, $rfc, 'email');
            }
            if ($forcePhone) {
                $this->markVerifiedAdminAndMirror($accountId, $rfc, 'phone');
            }
        } catch (\Throwable $e) {
            try {
                Log::warning('clientes.store markVerified failed: ' . $e->getMessage(), [
                    'account_id' => $accountId,
                    'rfc'        => $rfc,
                ]);
            } catch (\Throwable $e2) {}
        }

        // ✅ Enviar credenciales (si hay email)
        $sendCreds = (bool) ($data['send_credentials'] ?? false);
        if ($sendCreds && $email !== '') {
            try {
                $req2 = new Request(['to' => $email]);
                $this->emailCredentials($accountId, $req2);
            } catch (\Throwable $e) {
                try {
                    Log::warning('clientes.store send credentials failed: ' . $e->getMessage(), [
                        'account_id' => $accountId,
                        'rfc'        => $rfc,
                        'email'      => $email,
                    ]);
                } catch (\Throwable $e2) {}
            }
        }

        return redirect()->route('admin.clientes.index', ['q' => $rfc])
            ->with('ok', "Cliente creado: RFC {$rfc} (account_id={$accountId}).");
    }
    
    private function safeAdminClientesIndexUrl(): string
    {
        try {
            return \Illuminate\Support\Facades\Route::has('admin.clientes.index')
                ? route('admin.clientes.index')
                : url('/admin/clientes');
        } catch (\Throwable $e) {
            return url('/admin/clientes');
        }
    }

    /**
     * Marca verificación en admin + espejo, y limpia tokens/otps pendientes.
     * $type: email|phone
     */
    private function markVerifiedAdminAndMirror(string $accountId, string $rfcReal, string $type): void
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['email', 'phone'], true)) return;

        // admin.accounts
        if ($type === 'email' && $this->hasCol($this->adminConn, 'accounts', 'email_verified_at')) {
            DB::connection($this->adminConn)->table('accounts')
                ->where('id', $accountId)
                ->update(['email_verified_at' => now(), 'updated_at' => now()]);
            $this->purgeAdminVerificationArtifacts($accountId, 'email');
        }

        if ($type === 'phone' && $this->hasCol($this->adminConn, 'accounts', 'phone_verified_at')) {
            DB::connection($this->adminConn)->table('accounts')
                ->where('id', $accountId)
                ->update(['phone_verified_at' => now(), 'updated_at' => now()]);
            $this->purgeAdminVerificationArtifacts($accountId, 'phone');
        }

        // espejo mysql_clientes (tu helper robusto)
        $this->forceVerifyInMirror($accountId, $rfcReal, $type);
    }

    protected function resolveCuentaClienteFromAdminAccount(?object $acc): ?object
    {
        if (!$acc || empty($acc->id)) return null;

        $schemaCli = \Schema::connection('mysql_clientes');
        if (!$schemaCli->hasTable('cuentas_cliente')) return null;

        $connCli = \DB::connection('mysql_clientes');

        $accountId = (int) $acc->id;
        $rfc = strtoupper(trim((string)($acc->rfc ?? '')));

        // 1) match principal: admin_account_id
        if ($schemaCli->hasColumn('cuentas_cliente', 'admin_account_id')) {
            $row = $connCli->table('cuentas_cliente')->where('admin_account_id', $accountId)->first();
            if ($row) return $row;
        }

        // 2) fallback: rfc
        if ($rfc !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc')) {
            $row = $connCli->table('cuentas_cliente')->whereRaw('UPPER(rfc) = ?', [$rfc])->first();
            if ($row) return $row;
        }

        // 3) fallback: rfc_padre (solo como RFC real, NO como accounts.id)
        if (
            $rfc !== ''
            && $schemaCli->hasColumn('cuentas_cliente', 'rfc_padre')
        ) {
            $row = $connCli->table('cuentas_cliente')
                ->whereRaw('UPPER(rfc_padre) = ?', [$rfc])
                ->first();
            if ($row) return $row;
        }


        return null;
    }

    public function facturotopiaSave(string $key, Request $request): RedirectResponse
{
    $acc = $this->requireAccount($key, ['id', $this->colRfcAdmin(), 'razon_social', 'meta']);

    if (! $this->hasCol($this->adminConn, 'accounts', 'meta')) {
        return back()->with('error', 'La tabla accounts no tiene columna meta para guardar Facturotopia/API.');
    }

    $data = $request->validate([
        'facturotopia_status' => 'nullable|string|max:30',
        'facturotopia_env' => 'nullable|string|in:sandbox,production',
        'facturotopia_customer_id' => 'nullable|string|max:120',

        'facturotopia_user' => 'nullable|string|max:191',
        'facturotopia_password' => 'nullable|string|max:500',

        'facturotopia_base_url_sandbox' => 'nullable|string|max:255',
        'facturotopia_api_key_sandbox' => 'nullable|string|max:500',

        'facturotopia_base_url_production' => 'nullable|string|max:255',
        'facturotopia_api_key_production' => 'nullable|string|max:500',

        'timbres_asignados' => 'nullable|integer|min:0|max:999999999',
        'timbres_consumidos' => 'nullable|integer|min:0|max:999999999',
        'hits_asignados' => 'nullable|integer|min:0|max:999999999',
        'hits_consumidos' => 'nullable|integer|min:0|max:999999999',
        'notas_facturotopia' => 'nullable|string|max:3000',
    ]);

    $meta = $this->decodeMeta($acc->meta ?? null);

    $keep = function (string $key, mixed $newValue) use (&$meta) {
        if ($newValue === null) {
            return;
        }

        if (is_string($newValue) && trim($newValue) === '') {
            return;
        }

        data_set($meta, $key, is_string($newValue) ? trim($newValue) : $newValue);
    };

    $keep('facturotopia.status', $data['facturotopia_status'] ?? 'pendiente');
    $keep('facturotopia.env', $data['facturotopia_env'] ?? 'sandbox');
    $keep('facturotopia.customer_id', $data['facturotopia_customer_id'] ?? null);

    $keep('facturotopia.auth.user', $data['facturotopia_user'] ?? null);

    if (!empty($data['facturotopia_password'])) {
        data_set($meta, 'facturotopia.auth.password_encrypted', Crypt::encryptString((string) $data['facturotopia_password']));
    }

    $keep('facturotopia.sandbox.base_url', $data['facturotopia_base_url_sandbox'] ?? null);
    $keep('facturotopia.sandbox.api_key', $data['facturotopia_api_key_sandbox'] ?? null);

    $keep('facturotopia.production.base_url', $data['facturotopia_base_url_production'] ?? null);
    $keep('facturotopia.production.api_key', $data['facturotopia_api_key_production'] ?? null);

    data_set($meta, 'facturotopia.timbres.asignados', (int) ($data['timbres_asignados'] ?? data_get($meta, 'facturotopia.timbres.asignados', 0)));
    data_set($meta, 'facturotopia.timbres.consumidos', (int) ($data['timbres_consumidos'] ?? data_get($meta, 'facturotopia.timbres.consumidos', 0)));
    data_set($meta, 'facturotopia.hits.asignados', (int) ($data['hits_asignados'] ?? data_get($meta, 'facturotopia.hits.asignados', 0)));
    data_set($meta, 'facturotopia.hits.consumidos', (int) ($data['hits_consumidos'] ?? data_get($meta, 'facturotopia.hits.consumidos', 0)));

    if (array_key_exists('notas_facturotopia', $data)) {
        data_set($meta, 'facturotopia.notas', (string) ($data['notas_facturotopia'] ?? ''));
    }

    data_set($meta, 'facturotopia.updated_at', now()->toDateTimeString());
    data_set($meta, 'facturotopia.updated_by', auth('admin')->id());

    $updated = DB::connection($this->adminConn)
        ->table('accounts')
        ->where('id', (int) $acc->id)
        ->update([
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);

    Cache::forget('client.account.summary.' . $acc->id);
    Cache::forget('client.account.plan.' . $acc->id);
    Cache::forget('client.account.home.' . $acc->id);

    return back()->with('ok', $updated >= 0
        ? 'Datos Facturotopia/API/Timbres guardados correctamente.'
        : 'No se pudo confirmar el guardado de Facturotopia/API.'
    );
}

    // ======================= GUARDAR (accounts) =======================
    public function save(string $key, Request $request): RedirectResponse
    {
        // ✅ key puede ser accounts.id (numérico) o accounts.rfc
        $acc = $this->requireAccount($key, ['id', $this->colRfcAdmin(), 'meta']);
        $accountId = (string) $acc->id;
        $rfcCol    = $this->colRfcAdmin();
        $rfcReal   = strtoupper(trim((string) ($acc->{$rfcCol} ?? '')));

        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();

        // ✅ Acepta monthly/yearly + mensual/anual
        $rules = [
                'razon_social'      => 'nullable|string|max:190',
                'email'             => 'nullable|string|max:190',
                'phone'             => 'nullable|string|max:25',
                'plan'              => 'nullable|string|max:50',
                'billing_cycle'     => 'nullable|string|max:20',
                'billing_status'    => 'nullable|string|max:30',
                'next_invoice_date' => 'nullable|date',

                // ✅ checkbox actual UI (alias)
                'blocked'           => 'nullable|boolean',

                // ✅ compat / api
                'is_blocked'        => 'nullable|boolean',

                'custom_amount_mxn' => 'nullable|numeric|min:0|max:99999999',
                'vault_active'      => 'nullable|boolean',
            ];

        $data = validator($request->all(), $rules)->validate();

        // =========================
        // Normalización segura
        // =========================
        $razon = $this->blankToNull($data['razon_social'] ?? null);
        $email = $this->normalizeEmailNullable($data['email'] ?? null);
        $phone = $this->normalizePhoneNullable($data['phone'] ?? null);

        $plan  = $this->normalizePlanNullable($data['plan'] ?? null);
        $cycle = $this->normalizeBillingCycleNullable($data['billing_cycle'] ?? null);
        $bs    = $this->normalizeBillingStatusNullable($data['billing_status'] ?? null);

        // ✅ UI manda "blocked", backend/legacy manda "is_blocked"
        $blockedKey = array_key_exists('is_blocked', $data) ? 'is_blocked'
                    : (array_key_exists('blocked', $data) ? 'blocked' : null);

        $isBlocked = $blockedKey
            ? $this->normalizeBoolInt($data[$blockedKey], 0)
            : null;

        $vaultActive = array_key_exists('vault_active', $data)
            ? $this->normalizeBoolInt($data['vault_active'], 0)
            : null;

        // =========================================================
        // ✅ IMPORTANTE:
        // - $payloadAdmin: SOLO columnas que EXISTEN en admin.accounts
        // - $payloadMirror: lo que queremos reflejar en mysql_clientes (aunque admin no tenga columna)
        // =========================================================
        $payloadAdmin  = ['updated_at' => now()];
        $payloadMirror = ['updated_at' => now()];

        // =========================================================
        // ✅ META: cargar UNA vez, mutar, y escribir UNA vez (solo admin si existe meta)
        // =========================================================
        $metaDirty = false;
        $meta      = null;

        $loadMeta = function () use (&$meta, $acc) {
            if ($meta !== null) return;
            $meta = $this->decodeMeta($acc->meta ?? null);
            if (!is_array($meta)) $meta = [];
        };

        $flushMeta = function () use (&$meta, &$payloadAdmin, &$metaDirty) {
            if (!$metaDirty) return;
            $payloadAdmin['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        };

        // =========================
        // Campos directos (ADMIN)
        // =========================
        if ($razon !== null && $this->hasCol($this->adminConn, 'accounts', 'razon_social')) {
            $payloadAdmin['razon_social'] = (string) $razon;
        }

        if ($this->hasCol($this->adminConn, 'accounts', $emailCol) && array_key_exists('email', $data)) {
            $payloadAdmin[$emailCol] = $email; // null permitido para limpiar
        }

        // ===== PATCH: columns cache local (por request) =====
        $colsAccounts = null;
        $getAccountsCols = function () use (&$colsAccounts) {
            if (is_array($colsAccounts)) return $colsAccounts;
            try {
                $colsAccounts = collect(DB::connection($this->adminConn)->select("SHOW COLUMNS FROM accounts"))
                    ->pluck('Field')
                    ->map(fn($x) => strtolower((string)$x))
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                $colsAccounts = [];
            }
            return $colsAccounts;
        };
        $accHas = function (string $col) use ($getAccountsCols) : bool {
            $col = strtolower(trim($col));
            if ($col === '') return false;
            return in_array($col, $getAccountsCols(), true);
        };

        // ===== Teléfono (sync phone/telefono SIEMPRE) =====
        if (array_key_exists('phone', $data)) {

            // preferido (tu colPhone), si existe
            if ($phoneCol && $accHas($phoneCol)) {
                $payloadAdmin[$phoneCol] = $phone; // null permitido para limpiar
            }

            // ✅ mantener ambas SI existen (en tu schema sí existen ambas)
            if ($accHas('phone')) {
                $payloadAdmin['phone'] = $phone;
            }
            if ($accHas('telefono')) {
                $payloadAdmin['telefono'] = $phone;
            }

            // mirror (cuentas_cliente usa telefono)
            $payloadMirror['telefono'] = $phone;
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'plan') && array_key_exists('plan', $data)) {
            $payloadAdmin['plan'] = $plan;
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'plan_actual') && array_key_exists('plan', $data)) {
            $payloadAdmin['plan_actual'] = $plan;
        }

        // =========================================================
        // ✅ BILLING CYCLE (compat)
        // - accounts.modo_cobro: mensual|anual (si existe)
        // - accounts.meta.billing.billing_cycle: monthly|yearly
        // =========================================================
        if (array_key_exists('billing_cycle', $data)) {

            // 1) modo_cobro (admin) si existe
            if ($this->hasCol($this->adminConn, 'accounts', 'modo_cobro')) {
                $modo = $this->cycleToModo($cycle); // mensual|anual|null
                if ($modo !== null) {
                    $payloadAdmin['modo_cobro'] = $modo;
                    // también al espejo si existe
                    $payloadMirror['modo_cobro'] = $modo;
                }
            }

            // 2) meta (admin) si existe
            if ($this->hasCol($this->adminConn, 'accounts', 'meta')) {
                $loadMeta();

                if ($cycle !== null) {
                    data_set($meta, 'billing.billing_cycle', $cycle);
                    data_set($meta, 'billing.cycle', $cycle);
                }

                $modo = $this->cycleToModo($cycle);
                if ($modo) {
                    data_set($meta, 'billing.mode', $modo);
                }

                $metaDirty = true;
            }

            // 3) espejo billing_cycle (si existe en mysql_clientes)
            if ($cycle !== null) {
                $payloadMirror['billing_cycle'] = $cycle;
            }
        }

        // backfill modo_cobro desde meta si aplica (admin)
        if (
            $this->hasCol($this->adminConn, 'accounts', 'modo_cobro')
            && !array_key_exists('billing_cycle', $data)
            && !array_key_exists('modo_cobro', $payloadAdmin)
        ) {
            if ($this->hasCol($this->adminConn, 'accounts', 'meta')) {
                $loadMeta();
                $mode = strtolower(trim((string) (
                    data_get($meta, 'billing.mode')
                    ?? data_get($meta, 'billing.modo')
                    ?? ''
                )));
                if ($mode === 'mensual' || $mode === 'anual') {
                    $payloadAdmin['modo_cobro'] = $mode;
                    $payloadMirror['modo_cobro'] = $mode;
                }
            }
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'billing_status') && array_key_exists('billing_status', $data)) {
            $payloadAdmin['billing_status'] = $bs;
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'next_invoice_date') && array_key_exists('next_invoice_date', $data)) {
            $payloadAdmin['next_invoice_date'] = $this->blankToNull($data['next_invoice_date'] ?? null);
            $payloadMirror['next_invoice_date'] = $payloadAdmin['next_invoice_date'];
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'is_blocked') && $isBlocked !== null) {
            $payloadAdmin['is_blocked'] = (int) $isBlocked;
            $payloadMirror['is_blocked'] = (int) $isBlocked;
        }

        // =========================================================
        // ✅ VAULT ACTIVE:
        // - admin.accounts SOLO si la columna existe (CRÍTICO)
        // - espejo SIEMPRE intentamos reflejar (si existe col)
        // - meta compat si existe
        // =========================================================
        if ($vaultActive !== null) {

            if ($this->hasCol($this->adminConn, 'accounts', 'vault_active')) {
                $payloadAdmin['vault_active'] = (int) $vaultActive;
            }

            // meta (admin) si existe
            if ($this->hasCol($this->adminConn, 'accounts', 'meta')) {
                $loadMeta();
                data_set($meta, 'vault.active', (bool) $vaultActive);
                data_set($meta, 'vault_active', (bool) $vaultActive);
                $metaDirty = true;
            }

            // espejo: aunque admin no tenga columna, lo mandamos a syncPlanToMirror
            $payloadMirror['vault_active'] = (int) $vaultActive;
        }

        // =========================================================
        // ✅ BILLING OVERRIDE (custom_amount_mxn)
        // =========================================================
        $custom = array_key_exists('custom_amount_mxn', $data) ? (float) $data['custom_amount_mxn'] : null;
        $custom = ($custom !== null && $custom > 0.00001) ? round($custom, 2) : null;

        if ($custom !== null) {

            if ($this->hasCol($this->adminConn, 'accounts', 'meta')) {
                $loadMeta();

                data_set($meta, 'billing.override.amount_mxn', $custom);
                data_set($meta, 'billing.override.enabled', true);

                $modo = $this->cycleToModo($cycle);
                if ($modo) {
                    data_set($meta, 'billing.mode', $modo);
                }

                $metaDirty = true;
            }

            foreach (['custom_amount_mxn', 'override_amount_mxn', 'billing_amount_mxn', 'amount_mxn', 'license_amount_mxn'] as $col) {
                if ($this->hasCol($this->adminConn, 'accounts', $col)) {
                    $payloadAdmin[$col] = $custom;
                    break;
                }
            }

            // espejo (si existiera alguna col equivalente en cuentas_cliente) lo manejarías allá;
            // por ahora basta con meta/admin.
        }

        // ✅ Al final: si tocamos meta, se escribe UNA vez
        $flushMeta();

        // =========================================================
        // UPDATE admin.accounts (solo payloadAdmin)
        // =========================================================
        $affected = DB::connection($this->adminConn)
            ->table('accounts')
            ->where('id', (int) $accountId)
            ->update($payloadAdmin);

        if ($affected < 1) {
            \Log::warning('[CLIENTES] save(): UPDATE 0 rows', [
                'key'       => $key,
                'accountId' => $accountId,
                'conn'      => $this->adminConn,
                'payload'   => $payloadAdmin,
            ]);

            return back()->with('error', 'No se aplicaron cambios (UPDATE=0). Revisa el form (name=) y el key.');
        }

                // ✅ Legacy "clientes" debe ir por RFC real (no por id)
        if ($rfcReal !== '') {
            $this->upsertClienteLegacy($rfcReal, ['razon_social' => (string) ($acc->razon_social ?? '')] + $payloadAdmin);
        }

        // =========================================================
        // ✅ FIX CRÍTICO:
        // Forzar normalización fuerte del espejo ANTES del sync final.
        // Esto evita que el portal cliente siga leyendo una cuenta espejo
        // vieja/duplicada con plan FREE cuando en admin ya está PRO.
        // =========================================================
        try {
            $pack = $this->ensureMirrorAndOwner($accountId, $rfcReal);
            $cuentaMirror = $pack['cuenta'] ?? null;

            // Si ya tenemos cuenta espejo canónica, reforzar campos críticos
            if ($cuentaMirror && !empty($cuentaMirror->id)) {
                $schemaCli = Schema::connection('mysql_clientes');
                $connCli   = DB::connection('mysql_clientes');

                $forceMirror = [
                    'updated_at' => now(),
                ];

                if ($schemaCli->hasColumn('cuentas_cliente', 'admin_account_id')) {
                    $forceMirror['admin_account_id'] = (int) $accountId;
                }

                if ($rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc')) {
                    $forceMirror['rfc'] = $rfcReal;
                }

                if ($rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc_padre')) {
                    $forceMirror['rfc_padre'] = $rfcReal;
                }

                if ($schemaCli->hasColumn('cuentas_cliente', 'plan')) {
                    $forceMirror['plan'] = $plan;
                }

                if ($schemaCli->hasColumn('cuentas_cliente', 'plan_actual')) {
                    $forceMirror['plan_actual'] = $plan;
                }

                if ($schemaCli->hasColumn('cuentas_cliente', 'billing_cycle') && $cycle !== null) {
                    $forceMirror['billing_cycle'] = $cycle;
                }

                if ($schemaCli->hasColumn('cuentas_cliente', 'modo_cobro')) {
                    $modoCobro = $this->cycleToModo($cycle);
                    if ($modoCobro !== null) {
                        $forceMirror['modo_cobro'] = $modoCobro;
                    }
                }

                if ($schemaCli->hasColumn('cuentas_cliente', 'is_blocked') && $isBlocked !== null) {
                    $forceMirror['is_blocked'] = (int) $isBlocked;
                }

                if ($schemaCli->hasColumn('cuentas_cliente', 'estado_cuenta')) {
                    if ($isBlocked !== null) {
                        $forceMirror['estado_cuenta'] = ((int) $isBlocked === 1) ? 'suspendida' : 'activa';
                    } elseif ($bs !== null) {
                        $forceMirror['estado_cuenta'] = in_array($bs, ['suspended', 'suspendida', 'cancelled', 'cancelada'], true)
                            ? 'suspendida'
                            : 'activa';
                    }
                }

                if ($schemaCli->hasColumn('cuentas_cliente', 'billing_status') && $bs !== null) {
                    $forceMirror['billing_status'] = $bs;
                }

                if ($schemaCli->hasColumn('cuentas_cliente', 'next_invoice_date') && array_key_exists('next_invoice_date', $data)) {
                    $forceMirror['next_invoice_date'] = $this->blankToNull($data['next_invoice_date'] ?? null);
                }

                if ($schemaCli->hasColumn('cuentas_cliente', 'telefono') && $phone !== null) {
                    $forceMirror['telefono'] = $phone;
                }

                if ($schemaCli->hasColumn('cuentas_cliente', 'email') && $email !== null) {
                    $forceMirror['email'] = $email;
                }

                $connCli->table('cuentas_cliente')
                    ->where('id', (string) $cuentaMirror->id)
                    ->update($forceMirror);
            }
        } catch (\Throwable $e) {
            try {
                Log::warning('clientes.save force mirror sync failed: ' . $e->getMessage(), [
                    'account_id' => $accountId,
                    'rfc'        => $rfcReal,
                ]);
            } catch (\Throwable $e2) {
                // ignore
            }
        }

        // ✅ Sync espejo final
        $this->syncPlanToMirror($accountId, $payloadMirror + $payloadAdmin + [
            'plan'           => $plan,
            'plan_actual'    => $plan,
            'billing_cycle'  => $cycle,
            'billing_status' => $bs,
            'is_blocked'     => $isBlocked,
            'email'          => $email,
            'phone'          => $phone,
        ]);

        // ✅ Bust básico de caches ligados al account por seguridad
        try {
            Cache::forget('client.account.summary.' . $accountId);
            Cache::forget('client.account.plan.' . $accountId);
            Cache::forget('client.account.home.' . $accountId);
        } catch (\Throwable $e) {
            // ignore
        }

        return back()->with('ok', 'Datos guardados.');
    }

    // ======================= ✅ SEED STATEMENT (para que exista la ruta) =======================
    public function seedStatement(string $key, Request $request): RedirectResponse
    {
        $acc = $this->requireAccount($key, ['id']);
        $accountId = (string) $acc->id;

        $period = trim((string) $request->input('period', ''));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $period = now()->format('Y-m');
        }

        // Intento 1: si existe un HUB con método utilizable, úsalo
        try {
            $hubClass = \App\Http\Controllers\Admin\Billing\BillingStatementsHubController::class;
            if (class_exists($hubClass)) {
                $hub = app($hubClass);

                foreach (['seedStatement', 'seedForAccount', 'ensureStatementForPeriod', 'seedPeriod'] as $m) {
                    if (method_exists($hub, $m)) {
                        $hub->{$m}($accountId, $period);
                        return back()->with('ok', "Edo. cuenta {$period} asegurado (HUB).");
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('seedStatement hub: ' . $e->getMessage(), ['account_id' => $accountId, 'period' => $period]);
        }

        // Intento 2 (fallback): crear item base "Servicio mensual/anual" si existen tablas típicas
        try {
            $schema = Schema::connection($this->adminConn);

            $tblStatements = null;
            foreach (['billing_statements', 'statements', 'account_statements'] as $t) {
                if ($schema->hasTable($t)) { $tblStatements = $t; break; }
            }

            $tblItems = null;
            foreach (['billing_statement_items', 'statement_items', 'billing_items'] as $t) {
                if ($schema->hasTable($t)) { $tblItems = $t; break; }
            }

            if (!$tblStatements || !$tblItems) {
                return back()->with('error', "No pude sembrar: no existen tablas de estados de cuenta (fallback). Ya quedó la ruta, pero falta conectar el backend del billing.");
            }

            DB::connection($this->adminConn)->transaction(function () use ($accountId, $period, $tblStatements, $tblItems, $schema) {
                $stmt = DB::connection($this->adminConn)->table($tblStatements)
                    ->where('account_id', $accountId)
                    ->where('period', $period)
                    ->first();

                if (!$stmt) {
                    $ins = [
                        'account_id' => $accountId,
                        'period'     => $period,
                        'status'     => $schema->hasColumn($tblStatements, 'status') ? 'open' : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $ins = array_filter($ins, fn ($v) => $v !== null);
                    DB::connection($this->adminConn)->table($tblStatements)->insert($ins);

                    $stmt = DB::connection($this->adminConn)->table($tblStatements)
                        ->where('account_id', $accountId)->where('period', $period)->first();
                }

                $descCol = $schema->hasColumn($tblItems, 'description') ? 'description' : ($schema->hasColumn($tblItems, 'concept') ? 'concept' : null);
                $amtCol  = $schema->hasColumn($tblItems, 'amount_mxn') ? 'amount_mxn' : ($schema->hasColumn($tblItems, 'amount') ? 'amount' : null);

                if (!$descCol || !$amtCol) {
                    return;
                }

                $exists = DB::connection($this->adminConn)->table($tblItems)
                    ->where('account_id', $accountId)
                    ->where('period', $period)
                    ->where($descCol, 'like', '%Servicio%')
                    ->exists();

                if ($exists) {
                    return;
                }

                // Resolver modo desde accounts.meta o plan (mensual/anual)
                $accRow = DB::connection($this->adminConn)->table('accounts')
                    ->select(['id', 'meta', 'plan', 'plan_actual', 'billing_cycle', 'billing_status'])
                    ->where('id', $accountId)
                    ->first();

                $meta = [];
                if ($accRow && is_string($accRow->meta ?? null) && trim((string) $accRow->meta) !== '') {
                    $decoded = json_decode((string) $accRow->meta, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $meta = $decoded;
                }

                $mode = strtolower(trim((string) (
                    data_get($meta, 'billing.mode')
                    ?? data_get($meta, 'billing.modo')
                    ?? ''
                )));

                $planStr = strtolower(trim((string) (($accRow->plan_actual ?? null) ?: ($accRow->plan ?? ''))));
                $cycle   = strtolower(trim((string) ($accRow->billing_cycle ?? '')));

                if (!in_array($mode, ['mensual', 'anual'], true)) {
                    if (str_contains($planStr, 'anual') || str_contains($planStr, 'annual') || in_array($cycle, ['yearly','annual','anual'], true)) $mode = 'anual';
                    if (str_contains($planStr, 'mensual') || str_contains($planStr, 'monthly') || in_array($cycle, ['monthly','mensual'], true)) $mode = 'mensual';
                }
                if (!in_array($mode, ['mensual', 'anual'], true)) $mode = 'mensual';

                $serviceLabel = ($mode === 'anual') ? 'Servicio anual' : 'Servicio mensual';

                // Monto por defecto desde tu lógica de plan/ciclo/meta
                $amount = (float) $this->defaultLicenseAmountFromPlan($accountId);

                // Si es anual, intentar tomar monto anual explícito desde meta (sin *12 arbitrario)
                if ($mode === 'anual') {
                    $annualCandidates = [
                    data_get($meta, 'billing.annual_amount_mxn'),
                    data_get($meta, 'billing.anual_amount_mxn'),
                    data_get($meta, 'billing.amount_mxn_annual'),
                    data_get($meta, 'billing.amount_anual_mxn'),

                    // ✅ NUEVO: override por ciclo real (tu UI guarda monthly|yearly)
                    data_get($meta, 'billing.override.yearly.amount_mxn'),
                    data_get($meta, 'billing.override.annual.amount_mxn'), // por compat si algún día lo usas

                    // Overrides legacy/compat
                    data_get($meta, 'billing.override.annual_amount_mxn'),
                    data_get($meta, 'billing.override.amount_mxn_annual'),
                    ];


                    foreach ($annualCandidates as $v) {
                        if (is_numeric($v) && (float) $v > 0) { $amount = (float) $v; break; }
                        if (is_string($v)) {
                            $s = trim(str_replace(['$', ',', 'MXN', 'mxn', ' '], '', $v));
                            if (is_numeric($s) && (float) $s > 0) { $amount = (float) $s; break; }
                        }
                    }
                }

                $payload = [
                    'account_id' => $accountId,
                    'period'     => $period,
                    $descCol     => $serviceLabel,
                    $amtCol      => round($amount, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($stmt && $schema->hasColumn($tblItems, 'statement_id') && isset($stmt->id)) {
                    $payload['statement_id'] = (int) $stmt->id;
                }

                DB::connection($this->adminConn)->table($tblItems)->insert($payload);
            });

            return back()->with('ok', "Edo. cuenta {$period} sembrado/asegurado (fallback).");
        } catch (\Throwable $e) {
            Log::warning('seedStatement fallback: ' . $e->getMessage(), ['account_id' => $accountId, 'period' => $period]);
            return back()->with('error', 'No se pudo sembrar el estado de cuenta: ' . $e->getMessage());
        }
    }

    // ======================= VERIFICACIÓN / OTP =======================
    public function resendEmailVerification(string $key): RedirectResponse
    {
        $emailCol = $this->colEmail();

        $acc = $this->requireAccount($key, ['id', DB::raw("$emailCol as email")]);
        abort_if(empty($acc->email), 404, 'Cuenta o email no disponible');

        abort_unless(Schema::connection($this->adminConn)->hasTable('email_verifications'), 500, 'No existe tabla email_verifications');

        $token = Str::random(40);

        DB::connection($this->adminConn)->table('email_verifications')->insert([
            'account_id' => $acc->id,
            'email'      => strtolower((string) $acc->email),
            'token'      => $token,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $url = route('cliente.verify.email.token', ['token' => $token]);
            Mail::send('emails.cliente.verify_email', ['nombre' => 'Usuario', 'url' => $url], function ($m) use ($acc) {
                $m->to($acc->email)->subject('Confirma tu correo - Pactopia360');
            });
        } catch (\Throwable $e) {
            Log::warning('resendEmailVerification: ' . $e->getMessage());
        }

        return back()->with('ok', 'Enlace de verificación regenerado.');
    }

    public function sendPhoneOtp(string $key, Request $request): RedirectResponse
    {
        $request->validate([
            'channel' => 'required|in:sms,whatsapp,wa',
            'phone'   => 'nullable|string|max:25',
        ]);

        if (!Schema::connection($this->adminConn)->hasTable('phone_otps')) {
            return back()->withErrors(['otp' => 'No existe tabla phone_otps.']);
        }

        $phoneCol = $this->colPhone();
        $emailCol = $this->colEmail();

        $acc = $this->requireAccount($key, [
            'id',
            DB::raw("$emailCol as email"),
            DB::raw("$phoneCol as phone"),
        ]);

        $newPhone = trim((string) $request->get('phone', ''));
        if ($newPhone !== '') {
            DB::connection($this->adminConn)->table('accounts')
                ->where('id', $acc->id)
                ->update([$phoneCol => $newPhone, 'updated_at' => now()]);

            $acc->phone = $newPhone;
        }

        $raw   = trim((string) ($acc->phone ?? ''));
        $clean = preg_replace('/\D+/', '', $raw);

        if ($clean === '' || strlen($clean) < 10) {
            return back()->withErrors([
                'phone' => 'No hay teléfono registrado (o es inválido). Captura un número para poder enviar OTP.',
            ]);
        }

        $code   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpCol = $this->colOtp();

        $channel = (string) $request->get('channel');
        $channel = ($channel === 'whatsapp') ? 'wa' : $channel;

        $payload = [
            'account_id' => $acc->id,
            'phone'      => $clean,
            'channel'    => $channel,
            'attempts'   => Schema::connection($this->adminConn)->hasColumn('phone_otps', 'attempts') ? 0 : null,
            'used_at'    => null,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
            $otpCol      => $code,
        ];

        $hasOtp  = Schema::connection($this->adminConn)->hasColumn('phone_otps', 'otp');
        $hasCode = Schema::connection($this->adminConn)->hasColumn('phone_otps', 'code');
        if ($hasOtp && $hasCode) {
            $payload['otp']  = $code;
            $payload['code'] = $code;
        }

        DB::connection($this->adminConn)->table('phone_otps')->insert($payload);

        try {
            if ($acc->email) {
                Mail::send(
                    'emails.cliente.verify_phone',
                    ['code' => $code, 'minutes' => 10],
                    function ($m) use ($acc) {
                        $m->to($acc->email)->subject('Tu código de verificación - Pactopia360');
                    }
                );
            }
        } catch (\Throwable $e) {
            Log::warning('sendPhoneOtp: ' . $e->getMessage());
        }

        return back()->with('ok', 'OTP enviado. (QA) Código: ' . $code);
    }

    public function forceEmailVerified(string $key): RedirectResponse
    {
        $acc = $this->requireAccount($key, ['id', $this->colRfcAdmin()]);

        // ✅ IMPORTANTÍSIMO: NUNCA uses $acc en operaciones numéricas
        $accId = (int) ((is_object($acc) && isset($acc->id)) ? $acc->id : 0);
        if ($accId <= 0) {
            return back()->with('error', 'Cuenta inválida.');
        }

        $accountId = (string) $accId;
        $rfcCol    = $this->colRfcAdmin();
        $rfcReal   = strtoupper(trim((string) (is_object($acc) ? ($acc->{$rfcCol} ?? '') : '')));


        // 1) Admin SOT
        if ($this->hasCol($this->adminConn, 'accounts', 'email_verified_at')) {
            DB::connection($this->adminConn)->table('accounts')
                ->where('id', $acc->id)
                ->update(['email_verified_at' => now(), 'updated_at' => now()]);
        }

        // 2) Limpia tokens pendientes (admin)
        $this->purgeAdminVerificationArtifacts($accountId, 'email');

        // 3) Espejo mysql_clientes (owner + cuenta)
        $this->forceVerifyInMirror($accountId, $rfcReal, 'email');
        // 4) Intento de activación final (si existe)
        try {
            if ($rfcReal !== '' && class_exists(\App\Http\Controllers\Cliente\VerificationController::class)) {
                app(\App\Http\Controllers\Cliente\VerificationController::class)
                    ->finalizeActivationAndSendCredentialsByRfc($rfcReal);
            }
        } catch (\Throwable $e) {
            Log::warning('finalizeActivation (email): ' . $e->getMessage());
        }

        return back()->with('ok', 'Email marcado como verificado (admin + cliente).');
    }

    public function forcePhoneVerified(string $key): RedirectResponse
    {

       $acc = $this->requireAccount($key, ['id', $this->colRfcAdmin()]);
       $accountId = (string) $acc->id;
       $rfcReal   = strtoupper(trim((string) ($acc->{$this->colRfcAdmin()} ?? '')));

       // 1) Admin SOT
       if ($this->hasCol($this->adminConn, 'accounts', 'phone_verified_at')) {
            DB::connection($this->adminConn)->table('accounts')
                ->where('id', $acc->id)
                ->update(['phone_verified_at' => now(), 'updated_at' => now()]);
        }


        // 2) Limpia OTP pendientes (admin)
        $this->purgeAdminVerificationArtifacts($accountId, 'phone');

        // 3) Espejo mysql_clientes (owner + cuenta)
        $this->forceVerifyInMirror($accountId, $rfcReal, 'phone');

        // 4) Intento de activación final (si tu controlador cliente trabaja por RFC real)
        try {
            if ($rfcReal !== '' && class_exists(\App\Http\Controllers\Cliente\VerificationController::class)) {
                app(\App\Http\Controllers\Cliente\VerificationController::class)
                    ->finalizeActivationAndSendCredentialsByRfc($rfcReal);
            }
        } catch (\Throwable $e) {
            Log::warning('finalizeActivation (phone): ' . $e->getMessage());
        }

        return back()->with('ok', 'Teléfono verificado (admin + cliente).');
    }

    public function resetPassword(Request $request, string $rfcOrId)
    {
        // ✅ Acepta ID o RFC; resetOwnerByRfc requiere RFC real
        $acc = $this->resolveAccount(trim((string) $rfcOrId), ['id', $this->colRfcAdmin()]);
        if (!$acc) {
            $payload = ['ok' => false, 'error' => 'No pude resolver la cuenta.'];
            return $this->backOrJson($request, $payload, 404);
        }

        $rfcReal = strtoupper(trim((string) ($acc->{$this->colRfcAdmin()} ?? '')));
        if ($rfcReal === '') {
            $payload = ['ok' => false, 'error' => 'No pude resolver el RFC de la cuenta.'];
            return $this->backOrJson($request, $payload, 404);
        }

        $res = ClientCredentials::resetOwnerByRfc($rfcReal);

        $wantJson    = $request->expectsJson() || $request->wantsJson() || $request->ajax() || $request->query('format') === 'json';
        $methodIsGet = $request->isMethod('GET');
        $wantPretty  = $request->query('format') === 'pretty' || ($methodIsGet && !$wantJson && !$request->has('format'));

        if ($wantJson) {
            return response()->json($res, $res['ok'] ? 200 : 422);
        }

        if ($wantPretty) {
            $code   = $res['ok'] ? 200 : 422;
            $pretty = json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            return response("
                <!doctype html><meta charset='utf-8'>
                <title>P360 · Reset password</title>
                <style>
                body{font:14px system-ui,Segoe UI,Roboto,sans-serif;padding:16px;background:#f8fafc;color:#111827}
                pre{background:#0b1020;color:#e5e7eb;padding:12px;border-radius:8px;overflow:auto}
                .tip{margin:10px 0 14px;color:#475569}
                .pill{display:inline-block;border:1px solid #94a3b8;border-radius:999px;padding:2px 8px;font-size:12px}
                </style>
                <h1 style='margin:0 0 6px'>Reset de contraseña (OWNER)</h1>
                <div class='tip'>Formato: <span class='pill'>pretty</span> · Cambia a <code>?format=json</code> si quieres JSON puro.</div>
                <pre>{$pretty}</pre>
            ", $code)->header('Content-Type', 'text/html; charset=utf-8');
        }

        if (!$res['ok']) {
            return $this->backOrJson($request, ['error' => $res['error'] ?? 'No se pudo resetear'], 500);
        }

        // Guardar tmp por RFC real y por accountId para que el listado lo vea en ambos casos
        foreach ([$rfcReal, strtolower($rfcReal), (string) $acc->id, strtolower((string) $acc->id)] as $k) {
            session()->flash("tmp_pass.$k", $res['pass']);
            session()->flash("tmp_user.$k", $res['email']);
            Cache::put("tmp_pass.$k", $res['pass'], now()->addMinutes(15));
            Cache::put("tmp_user.$k", $res['email'], now()->addMinutes(15));
            cookie()->queue(cookie()->make("p360_tmp_pass_{$k}", $res['pass'], 10, null, null, false, false, false, 'Lax'));
            cookie()->queue(cookie()->make("p360_tmp_user_{$k}", $res['email'], 10, null, null, false, false, false, 'Lax'));
        }

        return back()
            ->with('ok', 'Contraseña temporal generada para el OWNER.')
            ->with('tmp_password', $res['pass'])
            ->with('tmp_user_email', $res['email']);
    }

    private function backOrJson(Request $request, array $data, int $status = 400)
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json($data + ['ok' => false], $status);
        }
        $key = isset($data['error']) ? 'error' : 'info';
        return back()->withErrors([$key => $data[$key] ?? 'Operación no completada.']);
    }

    /**
     * ✅ Enviar credenciales (incluyendo usuario+password) a:
     *  - emails capturados en textarea ("to"/"recipients")
     *  - o destinatarios guardados (account_recipients)
     *  - con fallback al email de la cuenta
     *
     * Usa plantilla admin:
     *   resources/views/emails/admin/cliente_credentials.blade.php (espera variable $p)
     * y fallback a plantilla cliente existente si no existe.
     */
    public function emailCredentials(string $key, Request $request): RedirectResponse
    {
        $emailCol = $this->colEmail();
        $rfcCol   = $this->colRfcAdmin();

        $acc = $this->requireAccount($key, ['id', $rfcCol, 'razon_social', DB::raw("$emailCol as email")]);
        $accountId = (string) $acc->id;
        $rfcReal   = strtoupper(trim((string) ($acc->{$rfcCol} ?? '')));

        abort_if($rfcReal === '', 422, 'RFC no disponible en la cuenta.');

        // =========================================================
        // ✅ 1) Sanitizar robusto "to" / "recipients" ANTES de validar
        // - acepta string CSV
        // - acepta array (to[])
        // - elimina vacíos, normaliza ; \n \r \t
        // =========================================================
        $raw = $request->input('to', $request->input('recipients', ''));

        // Normalización a string para casos raros (array con vacíos, saltos, etc.)
        if (is_array($raw)) {
            $raw = implode(',', array_map(static function ($v) {
                return is_scalar($v) ? (string)$v : '';
            }, $raw));
        }

        $raw = (string) $raw;
        $raw = str_replace([';', "\n", "\r", "\t"], [',', ',', ',', ' '], $raw);

        // Split, trim, filtra vacíos
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn($x) => $x !== ''));

        // Lower + valida formato email + unique
        $toClean = [];
        foreach ($parts as $p) {
            $e = strtolower(trim((string)$p));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $toClean[] = $e;
            }
        }
        $toClean = array_values(array_unique($toClean));

        // Si viene vacío, buscamos recipients guardados (y fallback email)
        if (empty($toClean)) {
            $toClean = $this->resolveRecipientsForAccountAdminSide($accountId);
        }

        if (empty($toClean)) {
            return back()->with('error', 'No hay correos destinatarios válidos para enviar credenciales.');
        }

        // =========================================================
        // ✅ 2) Validación final sobre el array YA limpio (sin vacíos)
        // =========================================================
        $v = validator(['to' => $toClean], [
            'to'   => 'required|array|min:1',
            'to.*' => 'required|email',
        ]);

        if ($v->fails()) {
            return back()->withErrors($v)->withInput();
        }

        $to = $v->validated()['to'];

        // =========================================================
        // ✅ 3) Generar credenciales (reset password del OWNER por RFC REAL)
        // =========================================================
        $res = ClientCredentials::resetOwnerByRfc($rfcReal);
        if (empty($res['ok'])) {
            Log::warning('emailCredentials: resetOwnerByRfc failed', [
                'account_id' => $accountId,
                'rfc'        => $rfcReal,
                'error'      => $res['error'] ?? null,
            ]);
            return back()->with('error', 'No se pudo generar la contraseña temporal del OWNER: ' . ($res['error'] ?? 'desconocido'));
        }

        $usuario   = (string) ($res['email'] ?? '');
        $password  = (string) ($res['pass'] ?? '');
        $accessUrl = \Illuminate\Support\Facades\Route::has('cliente.login') ? route('cliente.login') : url('/cliente/login');

        // Persistir tmp para UI (por ID y por RFC real)
        foreach ([$accountId, strtolower($accountId), $rfcReal, strtolower($rfcReal)] as $k) {
            session()->flash("tmp_pass.$k", $password);
            session()->flash("tmp_user.$k", $usuario);
            Cache::put("tmp_pass.$k", $password, now()->addMinutes(15));
            Cache::put("tmp_user.$k", $usuario, now()->addMinutes(15));
        }

        // 4) Payload para plantilla admin ($p)
        $p = [
            'brand' => [
                'name'     => config('app.name', 'Pactopia360'),
                'logo_url' => $this->brandLogoUrl(),
            ],
            'account' => [
                'rfc'          => $rfcReal,
                'razon_social' => (string) ($acc->razon_social ?? 'Cliente'),
            ],
            'credentials' => [
                'usuario'    => $usuario,
                'password'   => $password,
                'access_url' => $accessUrl,
            ],
        ];

        // 5) Enviar (admin template preferido, fallback a cliente)
        try {
            if (view()->exists('emails.admin.cliente_credentials')) {
                Mail::send('emails.admin.cliente_credentials', ['p' => $p], function ($m) use ($to) {
                    $m->to($to)->subject('Acceso · Pactopia360');
                });
            } elseif (view()->exists('emails.cliente.credentials')) {
                Mail::send('emails.cliente.credentials', [
                    'login'    => $accessUrl,
                    'email'    => $usuario,
                    'rfc'      => $rfcReal,
                    'password' => $password,
                ], function ($m) use ($to) {
                    $m->to($to)->subject('Acceso · Pactopia360');
                });
            } else {
                $list = implode(', ', $to);
                Mail::raw(
                    "Hola.\n\nTu acceso a Pactopia360 está listo.\n\nUsuario: {$usuario}\nContraseña temporal: {$password}\n\nLogin: {$accessUrl}\n\nDestinatarios: {$list}\n\nPor seguridad, cambia tu contraseña después del primer acceso.\n\n— Equipo Pactopia360",
                    function ($m) use ($to) {
                        $m->to($to)->subject('Acceso · Pactopia360');
                    }
                );
            }
        } catch (\Throwable $e) {
            Log::error('emailCredentials failed', [
                'account_id' => $accountId,
                'to'         => $to,
                'error'      => $e->getMessage(),
            ]);

            return back()->with('error', 'No se pudo enviar el correo. Revisa logs y configuración MAIL.');
        }

        if (Schema::connection($this->adminConn)->hasTable('credential_logs')) {
            DB::connection($this->adminConn)->table('credential_logs')->insert([
                'account_id'  => $acc->id,
                'action'      => 'email',
                'meta'        => json_encode(['by' => auth('admin')->id(), 'to' => $to], JSON_UNESCAPED_UNICODE),
                'sent_at'     => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return back()->with('ok', 'Credenciales enviadas por correo a: ' . implode(', ', $to));
    }

    /**
     * Logo “seguro” para emails: intenta URL pública; si no, vacío (la plantilla hace fallback a texto).
     */
    private function brandLogoUrl(): string
    {
        try {
            $p = public_path('assets/client/logop360dark.png');
            if (is_file($p)) {
                return asset('assets/client/logop360dark.png');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return '';
    }

    // ====== IMPERSONATE ======
    public function impersonate(string $key): RedirectResponse
    {
        $acc = $this->requireAccount($key, ['id', $this->colRfcAdmin()]);
        $accountId = (string) $acc->id;
        $rfcReal   = strtoupper(trim((string) ($acc->{$this->colRfcAdmin()} ?? '')));

        $pack = $this->ensureMirrorAndOwner($accountId, $rfcReal);

        $cuentaId = (string) ($pack['cuenta']->id ?? '');
        $ownerId  = (string) ($pack['owner']->id ?? '');

        abort_if($cuentaId === '', 404, 'Cuenta espejo no disponible.');
        abort_if($ownerId === '', 404, 'Usuario owner no disponible.');

        $ownerRow = DB::connection('mysql_clientes')
            ->table('usuarios_cuenta')
            ->where('id', $ownerId)
            ->first(['id', 'activo', 'cuenta_id', 'email']);

        abort_if(!$ownerRow || !(int) ($ownerRow->activo ?? 0), 404, 'Usuario owner no disponible');

        $token = Str::random(32);

        Cache::put("impersonate.token.$token", [
            'owner_id'   => $ownerId,
            'cuenta_id'  => $cuentaId,
            'admin_id'   => (string) auth('admin')->id(),
            'rfc'        => $rfcReal,
            'account_id' => $accountId,
        ], now()->addMinutes(5));

        $url = \URL::temporarySignedRoute(
            'cliente.impersonate.consume',
            now()->addMinutes(5),
            ['token' => $token]
        );

        return redirect($url);
    }

    public function impersonateStop(): RedirectResponse
    {
        // ⚠️ Admin NO puede cerrar la sesión del portal cliente porque es otra cookie (p360_client_session).
        // Este endpoint queda como compat para UI/admin: solo manda al usuario al portal cliente a cerrarla.
        try {
            $url = \Illuminate\Support\Facades\Route::has('cliente.home')
                ? route('cliente.home')
                : url('/cliente');

            return redirect($url)->with('info', 'Para finalizar la impersonación, ciérrala desde el portal cliente (menú usuario).');
        } catch (\Throwable $e) {
            return redirect()->route('admin.clientes.index')->with('info', 'Impersonación: usa el portal cliente para finalizarla.');
        }
    }

    // ======================= BILLING PANEL (embed en /admin/clientes) =======================
    /**
     * Panel HTML embebible para Facturación (Billing Accounts) dentro de Admin Clientes.
     *
     * Usa account_id canónico = admin.accounts.id.
     * key puede ser RFC o ID.
     *
     * URL sugerida:
     *   GET /admin/clientes/{key}/billing/panel
     */
    public function billingPanel(string $key, Request $request)
    {
        // ✅ Resolver cuenta (SOT admin.accounts)
        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();
        $rfcCol   = $this->colRfcAdmin();

        $acc = $this->requireAccount($key, [
            'id',
            $rfcCol,
            'razon_social',
            DB::raw("$emailCol as email"),
            DB::raw("$phoneCol as phone"),
            'plan',
            'billing_cycle',
            'billing_status',
            'next_invoice_date',
            'is_blocked',
            'meta',
            'created_at',
        ]);

        $accountId = (string) $acc->id;
        $rfcReal   = strtoupper(trim((string) ($acc->{$rfcCol} ?? '')));

        // ✅ cálculo monto efectivo (ya lo tienes)
        $licenseAmount = $this->computeEffectiveLicenseAmountMxn($acc);

        // ✅ Recipients (si existen)
        $recipients = [];
        try {
            $recipients = $this->resolveRecipientsForAccountAdminSide($accountId);
        } catch (\Throwable $e) {
            $recipients = [];
        }

        // ✅ URLS (si las rutas existen)
        $urls = [
            'billing_accounts_index'   => \Route::has('admin.billing.accounts.index') ? route('admin.billing.accounts.index') : url('/admin/billing/accounts'),
            'billing_accounts_show'    => \Route::has('admin.billing.accounts.show') ? route('admin.billing.accounts.show', ['id' => $accountId]) : url("/admin/billing/accounts/{$accountId}"),
            'billing_accounts_license' => \Route::has('admin.billing.accounts.license') ? route('admin.billing.accounts.license', ['id' => $accountId]) : url("/admin/billing/accounts/{$accountId}/license"),
            'billing_accounts_modules' => \Route::has('admin.billing.accounts.modules') ? route('admin.billing.accounts.modules', ['id' => $accountId]) : url("/admin/billing/accounts/{$accountId}/modules"),
            'billing_accounts_override'=> \Route::has('admin.billing.accounts.override') ? route('admin.billing.accounts.override', ['id' => $accountId]) : url("/admin/billing/accounts/{$accountId}/override"),
        ];

        // ✅ Extra: atajo a estados de cuenta si existen rutas típicas
        $urls['billing_statements_index'] =
            \Route::has('admin.billing.statements.index')
                ? route('admin.billing.statements.index', ['accountId' => $accountId])
                : (\Route::has('admin.billing.statements') ? route('admin.billing.statements', ['accountId' => $accountId]) : null);

        // Render parcial “panel billing”
        // (lo creamos abajo)
        return view('admin.clientes._billing_panel', [
            'acc'           => $acc,
            'accountId'     => $accountId,
            'rfcReal'       => $rfcReal,
            'licenseAmount' => $licenseAmount,
            'recipients'    => $recipients,
            'urls'          => $urls,
        ]);
    }

        // ======================= SHOW / CORE ACTIONS =======================

    public function show(string $key)
    {
        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();
        $rfcCol   = $this->colRfcAdmin();

        $acc = $this->requireAccount($key, [
            'id',
            $rfcCol,
            'razon_social',
            DB::raw("$emailCol as email"),
            DB::raw("$phoneCol as phone"),
            DB::raw($this->hasCol($this->adminConn, 'accounts', 'plan') ? 'plan' : "NULL as plan"),
            DB::raw($this->hasCol($this->adminConn, 'accounts', 'billing_cycle') ? 'billing_cycle' : "NULL as billing_cycle"),
            DB::raw($this->hasCol($this->adminConn, 'accounts', 'billing_status') ? 'billing_status' : "NULL as billing_status"),
            DB::raw($this->hasCol($this->adminConn, 'accounts', 'next_invoice_date') ? 'next_invoice_date' : "NULL as next_invoice_date"),
            DB::raw($this->hasCol($this->adminConn, 'accounts', 'is_blocked') ? 'is_blocked' : "0 as is_blocked"),
            DB::raw($this->hasCol($this->adminConn, 'accounts', 'email_verified_at') ? 'email_verified_at' : "NULL as email_verified_at"),
            DB::raw($this->hasCol($this->adminConn, 'accounts', 'phone_verified_at') ? 'phone_verified_at' : "NULL as phone_verified_at"),
            DB::raw($this->hasCol($this->adminConn, 'accounts', 'meta') ? 'meta' : "NULL as meta"),
            'created_at',
            'updated_at',
        ]);

        $this->enrichRowsForListing([$acc]);

        $extras     = $this->collectExtrasForAccountIds([(string) $acc->id]);
        $creds      = $this->collectCredsForAccountIds([(string) $acc->id]);
        $recipients = $this->collectRecipientsForAccountIds([(string) $acc->id]);

        return response()->json([
            'ok'         => true,
            'account'    => $acc,
            'extras'     => $extras[(string) $acc->id] ?? [],
            'creds'      => $creds[(string) $acc->id] ?? [],
            'recipients' => $recipients[(string) $acc->id] ?? [],
            'effective_amount_mxn' => $this->computeEffectiveLicenseAmountMxn($acc),
        ]);
    }

    public function block(string $key): RedirectResponse
    {
        return $this->setBlockedState($key, 1, 'Cuenta bloqueada.');
    }

    public function unblock(string $key): RedirectResponse
    {
        return $this->setBlockedState($key, 0, 'Cuenta desbloqueada.');
    }

    public function deactivate(string $key): RedirectResponse
    {
        $acc = $this->requireAccount($key, ['id', $this->colRfcAdmin(), 'razon_social', 'meta']);
        $accountId = (string) $acc->id;
        $rfcReal   = strtoupper(trim((string) ($acc->{$this->colRfcAdmin()} ?? '')));

        $payloadAdmin = ['updated_at' => now()];
        $payloadMirror = ['updated_at' => now()];

        if ($this->hasCol($this->adminConn, 'accounts', 'billing_status')) {
            $payloadAdmin['billing_status'] = 'cancelled';
            $payloadMirror['billing_status'] = 'cancelled';
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'is_blocked')) {
            $payloadAdmin['is_blocked'] = 1;
            $payloadMirror['is_blocked'] = 1;
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'meta')) {
            $meta = $this->decodeMeta($acc->meta ?? null);
            data_set($meta, 'billing.status', 'cancelled');
            data_set($meta, 'account.status', 'cancelled');
            $payloadAdmin['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        DB::connection($this->adminConn)
            ->table('accounts')
            ->where('id', (int) $accountId)
            ->update($payloadAdmin);

        if ($rfcReal !== '') {
            $this->upsertClienteLegacy($rfcReal, ['razon_social' => (string) ($acc->razon_social ?? '')] + $payloadAdmin);
        }

        $this->syncPlanToMirror($accountId, $payloadMirror + $payloadAdmin);

        return back()->with('ok', 'Cuenta dada de baja.');
    }

    public function reactivate(string $key): RedirectResponse
    {
        $acc = $this->requireAccount($key, ['id', $this->colRfcAdmin(), 'razon_social', 'meta']);
        $accountId = (string) $acc->id;
        $rfcReal   = strtoupper(trim((string) ($acc->{$this->colRfcAdmin()} ?? '')));

        $payloadAdmin = ['updated_at' => now()];
        $payloadMirror = ['updated_at' => now()];

        if ($this->hasCol($this->adminConn, 'accounts', 'billing_status')) {
            $payloadAdmin['billing_status'] = 'active';
            $payloadMirror['billing_status'] = 'active';
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'is_blocked')) {
            $payloadAdmin['is_blocked'] = 0;
            $payloadMirror['is_blocked'] = 0;
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'meta')) {
            $meta = $this->decodeMeta($acc->meta ?? null);
            data_set($meta, 'billing.status', 'active');
            data_set($meta, 'account.status', 'active');
            $payloadAdmin['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        DB::connection($this->adminConn)
            ->table('accounts')
            ->where('id', (int) $accountId)
            ->update($payloadAdmin);

        if ($rfcReal !== '') {
            $this->upsertClienteLegacy($rfcReal, ['razon_social' => (string) ($acc->razon_social ?? '')] + $payloadAdmin);
        }

        $this->syncPlanToMirror($accountId, $payloadMirror + $payloadAdmin);

        return back()->with('ok', 'Cuenta reactivada.');
    }

    public function destroy(string $key): RedirectResponse
    {
        $acc = $this->requireAccount($key, [
            'id',
            $this->colRfcAdmin(),
            'razon_social',
            DB::raw($this->hasCol($this->adminConn, 'accounts', 'meta') ? 'meta' : "NULL as meta"),
        ]);

        $accountId = (string) $acc->id;
        $rfcReal   = strtoupper(trim((string) ($acc->{$this->colRfcAdmin()} ?? '')));

        $payloadAdmin  = ['updated_at' => now()];
        $payloadMirror = ['updated_at' => now()];

        // Soft delete real del lado admin
        if ($this->hasCol($this->adminConn, 'accounts', 'is_blocked')) {
            $payloadAdmin['is_blocked']  = 1;
            $payloadMirror['is_blocked'] = 1;
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'billing_status')) {
            $payloadAdmin['billing_status']  = 'cancelled';
            $payloadMirror['billing_status'] = 'cancelled';
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'meta')) {
            $meta = $this->decodeMeta($acc->meta ?? null);

            data_set($meta, 'deleted', true);
            data_set($meta, 'deleted_at', now()->toDateTimeString());
            data_set($meta, 'account.deleted', true);
            data_set($meta, 'account.status', 'deleted');
            data_set($meta, 'billing.status', 'cancelled');

            $payloadAdmin['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        DB::connection($this->adminConn)
            ->table('accounts')
            ->where('id', (int) $accountId)
            ->update($payloadAdmin);

        if ($rfcReal !== '') {
            $this->upsertClienteLegacy($rfcReal, [
                'razon_social' => (string) ($acc->razon_social ?? ''),
            ] + $payloadAdmin);
        }

        $this->syncPlanToMirror($accountId, $payloadMirror + $payloadAdmin);

        return redirect()
            ->route('admin.clientes.index')
            ->with('ok', 'Cuenta eliminada del listado (soft delete).');
    }

    // ======================= SYNC accounts -> clientes =======================
    public function syncToClientes(): RedirectResponse
    {
        if (!$this->legacyHasTable('clientes')) {
            return redirect()->route('admin.clientes.index')
                ->with('info', 'Sync omitido: no existe tabla clientes (legacy).');
        }

        $created = 0;
        $updated = 0;

        $hasNombreComercial = $this->legacyHasColumn('clientes', 'nombre_comercial');
        $rfcCol = $this->colRfcAdmin();

        DB::connection($this->adminConn)->table('accounts')
            ->select(['id', $rfcCol, 'razon_social'])
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$created, &$updated, $hasNombreComercial, $rfcCol) {
                foreach ($rows as $r) {
                    $rfc = strtoupper(trim((string) ($r->{$rfcCol} ?? '')));
                    if ($rfc === '') continue;

                    $rs  = $r->razon_social ?: ('Cuenta ' . $rfc);

                    $exists = DB::connection($this->legacyConn)->table('clientes')->where('rfc', $rfc)->first();

                    if ($exists) {
                        $upd = [
                            'razon_social' => $rs,
                            'updated_at'   => now(),
                        ];
                        if ($hasNombreComercial) {
                            $upd['nombre_comercial'] = $rs;
                        }

                        DB::connection($this->legacyConn)->table('clientes')->where('rfc', $rfc)->update($upd);
                        $updated++;
                    } else {
                        $ins = [
                            'codigo'       => $this->genCodigoCliente(),
                            'razon_social' => $rs,
                            'rfc'          => $rfc,
                            'activo'       => 1,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ];
                        if ($hasNombreComercial) {
                            $ins['nombre_comercial'] = $rs;
                        }

                        DB::connection($this->legacyConn)->table('clientes')->insert($ins);
                        $created++;
                    }
                }
            });

        // ✅ IMPORTANTÍSIMO: NO usar back() aquí
        // porque el referer puede ser /admin/clientes/sync-to-clientes (GET => 404)
        return redirect()->route('admin.clientes.index')
            ->with('ok', "Sync clientes OK. Creados {$created}, Actualizados {$updated}.");
    }

    // ======================= BULK =======================
    public function bulk(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'     => 'required|string',
            'action'  => 'required|in:email_verify,otp_sms,block,unblock',
            'channel' => 'nullable|in:sms,whatsapp,wa',
        ]);

        $action  = $request->string('action')->toString();
        $channel = $request->string('channel')->toString() ?: 'sms';

        // ✅ puede venir una lista mixta: ids o rfcs
        $keys = collect(explode(',', (string) $request->string('ids')))
            ->map(fn ($x) => trim((string) $x))
            ->filter()
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return back()->with('error', 'No hay IDs/RFCs válidos para procesar.');
        }

        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();
        $otpCol   = $this->colOtp();
        $rfcCol   = $this->colRfcAdmin();

        $hasBlockedCol = $this->hasCol($this->adminConn, 'accounts', 'is_blocked');
        $hasEmailVer   = Schema::connection($this->adminConn)->hasTable('email_verifications');
        $hasPhoneOtps  = Schema::connection($this->adminConn)->hasTable('phone_otps');

        $ok = 0; $skip = 0; $err = 0;
        $skips = []; $errs = [];

        foreach ($keys as $key) {
            try {
                $acc = $this->resolveAccount((string) $key, [
                    'id',
                    $rfcCol,
                    'razon_social',
                    DB::raw("$emailCol as email"),
                    DB::raw("$phoneCol as phone"),
                ]);

                if (!$acc) {
                    $skip++;
                    $skips[] = "$key: cuenta no existe";
                    continue;
                }

                switch ($action) {

                    case 'block':
                        if (!$hasBlockedCol) { $skip++; $skips[] = "$key: columna is_blocked no existe"; break; }
                        DB::connection($this->adminConn)->table('accounts')
                            ->where('id', $acc->id)
                            ->update(['is_blocked' => 1, 'updated_at' => now()]);
                        $ok++;
                        break;

                    case 'unblock':
                        if (!$hasBlockedCol) { $skip++; $skips[] = "$key: columna is_blocked no existe"; break; }
                        DB::connection($this->adminConn)->table('accounts')
                            ->where('id', $acc->id)
                            ->update(['is_blocked' => 0, 'updated_at' => now()]);
                        $ok++;
                        break;

                    case 'email_verify':
                        if (!$hasEmailVer) { $skip++; $skips[] = "$key: tabla email_verifications no existe"; break; }
                        if (empty($acc->email)) { $skip++; $skips[] = "$key: sin email"; break; }

                        $token = Str::random(40);
                        DB::connection($this->adminConn)->table('email_verifications')->insert([
                            'account_id' => $acc->id,
                            'email'      => strtolower((string) $acc->email),
                            'token'      => $token,
                            'expires_at' => now()->addDay(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        try {
                            $url = route('cliente.verify.email.token', ['token' => $token]);
                            if (view()->exists('emails.cliente.verify_email')) {
                                Mail::send('emails.cliente.verify_email', ['nombre' => 'Usuario', 'url' => $url], function ($m) use ($acc) {
                                    $m->to($acc->email)->subject('Confirma tu correo - Pactopia360');
                                });
                            }
                        } catch (\Throwable $e) {
                            Log::warning("bulk.email_verify send: " . $e->getMessage());
                        }

                        $ok++;
                        break;

                    case 'otp_sms':
                        if (!$hasPhoneOtps) { $skip++; $skips[] = "$key: tabla phone_otps no existe"; break; }
                        if (empty($acc->phone)) { $skip++; $skips[] = "$key: sin teléfono"; break; }

                        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                        $payload = [
                            'account_id' => $acc->id,
                            'phone'      => $acc->phone,
                            'channel'    => $channel === 'whatsapp' ? 'wa' : $channel,
                            'attempts'   => Schema::connection($this->adminConn)->hasColumn('phone_otps', 'attempts') ? 0 : null,
                            'used_at'    => null,
                            'expires_at' => now()->addMinutes(10),
                            'created_at' => now(),
                            'updated_at' => now(),
                            $otpCol      => $code,
                        ];

                        $hasOtp  = Schema::connection($this->adminConn)->hasColumn('phone_otps', 'otp');
                        $hasCode = Schema::connection($this->adminConn)->hasColumn('phone_otps', 'code');
                        if ($hasOtp && $hasCode) {
                            $payload['otp']  = $code;
                            $payload['code'] = $code;
                        }

                        DB::connection($this->adminConn)->table('phone_otps')->insert($payload);

                        try {
                            if (!empty($acc->email) && view()->exists('emails.cliente.verify_phone')) {
                                Mail::send('emails.cliente.verify_phone', ['code' => $code, 'minutes' => 10], function ($m) use ($acc) {
                                    $m->to($acc->email)->subject('Tu código de verificación - Pactopia360');
                                });
                            }
                        } catch (\Throwable $e) {
                            Log::warning("bulk.otp send: " . $e->getMessage());
                        }

                        $ok++;
                        break;
                }
            } catch (\Throwable $e) {
                $err++;
                $errs[] = "$key: " . $e->getMessage();
                Log::warning('bulk: ' . $e->getMessage(), ['key' => (string) $key, 'action' => $action]);
            }
        }

        $msg = "Bulk '{$action}': OK {$ok}" . ($skip ? " · Saltados {$skip}" : "") . ($err ? " · Errores {$err}" : "");
        if ($skip) $msg .= "\nSaltos: " . implode('; ', array_slice($skips, 0, 5)) . ($skip > 5 ? '...' : '');
        if ($err)  $msg .= "\nErrores: " . implode('; ', array_slice($errs, 0, 5)) . ($err > 5 ? '...' : '');

        return back()->with($err ? 'error' : 'ok', $msg);
    }

    // =========================================================
    // ✅ DESTINATARIOS (account_recipients) — FIX ROBUSTO (account_id = accounts.id)
    // =========================================================

    public function recipientsUpsert(string $key, Request $request): RedirectResponse
    {
        // ✅ key puede ser RFC o ID, pero guardamos SIEMPRE por accounts.id
        $acc = $this->requireAccount($key, ['id']);
        $accountId = (string) $acc->id;

        abort_unless(Schema::connection($this->adminConn)->hasTable('account_recipients'), 500, 'No existe tabla account_recipients');

        $schema = Schema::connection($this->adminConn);

        // En tu DB real: puede NO existir "kind"
        $hasKind    = $schema->hasColumn('account_recipients', 'kind');
        $hasActive  = $schema->hasColumn('account_recipients', 'is_active');
        $hasPrimary = $schema->hasColumn('account_recipients', 'is_primary');

        $inputRecipients = $request->input('recipients', $request->input('to', null));

        $rules = [
            'recipients' => 'required',
            'primary'    => 'nullable|string|max:190',
            'active'     => 'nullable|boolean',
        ];
        if ($hasKind) {
            $rules['kind'] = 'nullable|string|in:statement,invoice,general';
        }

        $data = validator(
            array_merge($request->all(), ['recipients' => $inputRecipients]),
            $rules
        )->validate();

        $kind   = $hasKind ? (string) ($data['kind'] ?? 'statement') : null;
        $active = (int) ($data['active'] ?? 1);

        $emails = $this->normalizeEmails($data['recipients']);
        if (empty($emails)) {
            return back()->withErrors(['recipients' => 'No hay emails válidos.']);
        }

        $primary = strtolower(trim((string) ($data['primary'] ?? '')));
        if ($primary !== '' && !filter_var($primary, FILTER_VALIDATE_EMAIL)) {
            $primary = '';
        }
        if ($primary !== '' && !$hasPrimary) {
            $primary = '';
        }

        DB::connection($this->adminConn)->transaction(function () use ($accountId, $hasKind, $kind, $active, $emails, $primary, $hasActive, $hasPrimary) {

            foreach ($emails as $e) {

                $q = DB::connection($this->adminConn)->table('account_recipients')
                    ->where('account_id', $accountId)
                    ->where('email', $e);

                if ($hasKind) {
                    $q->where('kind', $kind);
                }

                $row = $q->first();

                $payload = [
                    'account_id' => $accountId,
                    'email'      => $e,
                    'updated_at' => now(),
                ];

                if ($hasKind) {
                    $payload['kind'] = $kind;
                }
                if ($hasActive) {
                    $payload['is_active'] = $active;
                }
                if ($hasPrimary) {
                    $payload['is_primary'] = ($primary !== '' && $primary === $e) ? 1 : 0;
                }

                if (!$row) {
                    $payload['created_at'] = now();
                    DB::connection($this->adminConn)->table('account_recipients')->insert($payload);
                } else {
                    DB::connection($this->adminConn)->table('account_recipients')
                        ->where('id', (int) $row->id)
                        ->update($payload);
                }
            }

            // Si hay primary, apagar los demás
            if ($hasPrimary && $primary !== '') {
                $q = DB::connection($this->adminConn)->table('account_recipients')
                    ->where('account_id', $accountId);

                if ($hasKind) {
                    $q->where('kind', $kind);
                }

                $q->where('email', '!=', $primary)
                    ->update(['is_primary' => 0, 'updated_at' => now()]);
            }
        });

        return back()->with('ok', 'Destinatarios guardados.');
    }

    /**
     * ✅ FIX: para el listado (index) — NO pedir columnas que no existen
     *      y operar por accounts.id
     */
    private function collectRecipientsForAccountIds(array $accountIds): array
    {
        if (empty($accountIds)) return [];

        if (!Schema::connection($this->adminConn)->hasTable('account_recipients')) {
            return [];
        }

        $schema = Schema::connection($this->adminConn);

        $hasKind    = $schema->hasColumn('account_recipients', 'kind'); // probablemente false
        $hasActive  = $schema->hasColumn('account_recipients', 'is_active');
        $hasPrimary = $schema->hasColumn('account_recipients', 'is_primary');

        $cols = ['account_id', 'email'];
        if ($hasKind)    $cols[] = 'kind';
        if ($hasActive)  $cols[] = 'is_active';
        if ($hasPrimary) $cols[] = 'is_primary';

        $q = DB::connection($this->adminConn)->table('account_recipients')
            ->whereIn('account_id', array_map('strval', $accountIds))
            ->select($cols)
            ->orderBy('account_id', 'asc');

        if ($hasKind) {
            $q->orderBy('kind', 'asc');
        }
        if ($hasPrimary) {
            $q->orderBy('is_primary', 'desc');
        }

        $q->orderBy('email', 'asc');

        $rows = $q->get();

        $out = [];
        foreach ($rows as $r) {
            $aid = (string) ($r->account_id ?? '');
            if ($aid === '') continue;

            $email = strtolower(trim((string) ($r->email ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

            // Si no hay kind en la tabla, todo cae a "statement"
            $kind = $hasKind ? (string) ($r->kind ?? 'statement') : 'statement';

            if (!isset($out[$aid])) $out[$aid] = [];
            if (!isset($out[$aid][$kind])) $out[$aid][$kind] = [];

            $out[$aid][$kind][] = [
                'email'      => $email,
                'is_active'  => $hasActive ? (int) ($r->is_active ?? 1) : 1,
                'is_primary' => $hasPrimary ? (int) ($r->is_primary ?? 0) : 0,
            ];
        }

        return $out;
    }

     /**
     * Enriquece datos del listado con fallbacks desde:
     * - admin.accounts.meta
     * - admin.accounts.modo_cobro
     * - mysql_clientes.cuentas_cliente
     */
    private function enrichRowsForListing(iterable $rows): void
    {
        $schemaA = Schema::connection($this->adminConn);

        foreach ($rows as $r) {
            if (!$r || !isset($r->id)) continue;

            $meta = $this->decodeMeta($r->meta ?? null);

            $mirror = $this->resolveMirrorCuentaForListing((string) $r->id, (string) ($r->rfc ?? ''));

            // -------------------------------------------------
            // billing_cycle
            // prioridad:
            // 1) accounts.billing_cycle
            // 2) accounts.meta.billing.billing_cycle / billing.cycle
            // 3) accounts.modo_cobro
            // 4) mirror.billing_cycle
            // 5) mirror.modo_cobro
            // -------------------------------------------------
            $cycle = strtolower(trim((string) ($r->billing_cycle ?? '')));
            if ($cycle === '') {
                $cycle = strtolower(trim((string) (
                    data_get($meta, 'billing.billing_cycle')
                    ?? data_get($meta, 'billing.cycle')
                    ?? ''
                )));
            }

            if ($cycle === '' && $schemaA->hasColumn('accounts', 'modo_cobro')) {
                try {
                    $modo = DB::connection($this->adminConn)
                        ->table('accounts')
                        ->where('id', (int) $r->id)
                        ->value('modo_cobro');

                    $cycle = strtolower(trim((string) ($modo ?? '')));
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            if ($cycle === '' && $mirror) {
                $cycle = strtolower(trim((string) (
                    ($mirror->billing_cycle ?? '')
                    ?: ($mirror->modo_cobro ?? '')
                )));
            }

            if ($cycle === 'mensual') $cycle = 'monthly';
            if ($cycle === 'anual' || $cycle === 'annual') $cycle = 'yearly';

            if ($cycle !== '') {
                $r->billing_cycle = $cycle;
            }

            // -------------------------------------------------
            // next_invoice_date
            // prioridad:
            // 1) accounts.next_invoice_date
            // 2) meta.billing.next_invoice_date / next_invoice_date
            // 3) mirror.next_invoice_date
            // -------------------------------------------------
            $next = trim((string) ($r->next_invoice_date ?? ''));

            if ($next === '') {
                $next = trim((string) (
                    data_get($meta, 'billing.next_invoice_date')
                    ?? data_get($meta, 'next_invoice_date')
                    ?? ''
                ));
            }

            if ($next === '' && $mirror) {
                $next = trim((string) ($mirror->next_invoice_date ?? ''));
            }

            if ($next !== '') {
                $r->next_invoice_date = $next;
            }

            // -------------------------------------------------
            // billing_status fallback
            // -------------------------------------------------
            $bs = strtolower(trim((string) ($r->billing_status ?? '')));
            if ($bs === '' && $mirror) {
                $bs = strtolower(trim((string) (
                    ($mirror->billing_status ?? '')
                    ?: ($mirror->estado_cuenta ?? '')
                )));
            }
            if ($bs !== '') {
                $r->billing_status = $bs;
            }
        }
    }

    /**
     * Busca la cuenta espejo correcta para el listado.
     */
    private function resolveMirrorCuentaForListing(string $accountId, string $rfcReal = ''): ?object
    {
        $accountId = trim($accountId);
        $rfcReal   = strtoupper(trim($rfcReal));

        try {
            $schemaCli = Schema::connection('mysql_clientes');
            if (!$schemaCli->hasTable('cuentas_cliente')) return null;

            $connCli = DB::connection('mysql_clientes');

            $select = ['id', 'updated_at'];
            foreach ([
                'admin_account_id',
                'rfc',
                'rfc_padre',
                'billing_cycle',
                'modo_cobro',
                'next_invoice_date',
                'billing_status',
                'estado_cuenta',
            ] as $col) {
                if ($schemaCli->hasColumn('cuentas_cliente', $col)) {
                    $select[] = $col;
                }
            }

            // 1) canónico: admin_account_id
            if ($schemaCli->hasColumn('cuentas_cliente', 'admin_account_id')) {
                $row = $connCli->table('cuentas_cliente')
                    ->where('admin_account_id', (int) $accountId)
                    ->orderByDesc('updated_at')
                    ->first($select);

                if ($row) return $row;
            }

            // 2) rfc exacto
            if ($rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc')) {
                $row = $connCli->table('cuentas_cliente')
                    ->whereRaw('UPPER(rfc) = ?', [$rfcReal])
                    ->orderByDesc('updated_at')
                    ->first($select);

                if ($row) return $row;
            }

            // 3) rfc_padre exacto RFC real
            if ($rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc_padre')) {
                $row = $connCli->table('cuentas_cliente')
                    ->whereRaw('UPPER(rfc_padre) = ?', [$rfcReal])
                    ->orderByDesc('updated_at')
                    ->first($select);

                if ($row) return $row;
            }

            // 4) legacy
            if ($schemaCli->hasColumn('cuentas_cliente', 'rfc_padre')) {
                $row = $connCli->table('cuentas_cliente')
                    ->where('rfc_padre', $accountId)
                    ->orderByDesc('updated_at')
                    ->first($select);

                if ($row) return $row;
            }
        } catch (\Throwable $e) {
            try {
                Log::warning('resolveMirrorCuentaForListing: ' . $e->getMessage(), [
                    'account_id' => $accountId,
                    'rfc'        => $rfcReal,
                ]);
            } catch (\Throwable $e2) {
                // ignore
            }
        }

        return null;
    }

    private function resolveRecipientsForAccountAdminSide(string $accountId): array
    {
        $emails = [];

        $emailCol = $this->colEmail();
        $acc = DB::connection($this->adminConn)->table('accounts')->where('id', $accountId)->first([$emailCol]);
        $fallback = strtolower(trim((string) ($acc->{$emailCol} ?? '')));
        if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) $emails[] = $fallback;

        if (Schema::connection($this->adminConn)->hasTable('account_recipients')) {
            $schema = Schema::connection($this->adminConn);
            $hasActive  = $schema->hasColumn('account_recipients', 'is_active');
            $hasPrimary = $schema->hasColumn('account_recipients', 'is_primary');

            $q = DB::connection($this->adminConn)->table('account_recipients')
                ->where('account_id', $accountId);

            if ($hasActive) {
                $q->where('is_active', 1);
            }

            if ($hasPrimary) {
                $q->orderByDesc('is_primary');
            }

            $q->orderBy('email');

            $rows = $q->get(['email']);

            foreach ($rows as $r) {
                $e = strtolower(trim((string) ($r->email ?? '')));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $emails[] = $e;
            }
        }

        return array_values(array_unique($emails));
    }

    // ======================= MONTO EFECTIVO LICENCIA =======================
    private function computeEffectiveLicenseAmountMxn(object $accRow): ?float
    {
        // 1) columnas conocidas (si vienen en SELECT)
        foreach ([
            'custom_amount_mxn',
            'override_amount_mxn',
            'billing_amount_mxn',
            'amount_mxn',
            'precio_mxn',
            'monto_mxn',
            'license_amount_mxn',
        ] as $p) {
            if (isset($accRow->{$p}) && $accRow->{$p} !== null && $accRow->{$p} !== '') {
                $v = (float) $accRow->{$p};
                if ($v > 0) return round($v, 2);
            }
        }

        // 2) meta override + amount base
        $meta = $this->decodeMeta($accRow->meta ?? null);

        // 2.1) override (si está habilitado)
        $enabled = (bool) data_get($meta, 'billing.override.enabled', false);
        $amt = data_get($meta, 'billing.override.amount_mxn', null);

        if ($enabled && $amt !== null && (float) $amt > 0) {
            return round((float) $amt, 2);
        }

        // 2.2) fallback: meta.billing.amount_mxn (tu caso real: 899)
        $base = data_get($meta, 'billing.amount_mxn', null);
        if ($base !== null) {
            if (is_numeric($base) && (float) $base > 0) {
                return round((float) $base, 2);
            }
            if (is_string($base)) {
                $s = trim(str_replace(['$', ',', 'MXN', 'mxn', ' '], '', $base));
                if (is_numeric($s) && (float) $s > 0) {
                    return round((float) $s, 2);
                }
            }
        }

        // 3) default por plan/ciclo con inferencia cuando plan viene vacío
        $plan = strtolower(trim((string) ($accRow->plan ?? '')));

        // ✅ billing_cycle NO existe en tu accounts, así que lo sacamos de meta
        $meta  = $this->decodeMeta($accRow->meta ?? null);

        $cycle = strtolower(trim((string) (
            $accRow->billing_cycle
            ?? data_get($meta, 'billing.billing_cycle')
            ?? data_get($meta, 'billing.cycle')
            ?? ''
        )));

        // Soportar 'mensual/anual' también (por si llega modo en vez de cycle)
        if ($cycle === 'mensual') $cycle = 'monthly';
        if ($cycle === 'anual')   $cycle = 'yearly';
        if ($cycle === 'annual')  $cycle = 'yearly';

        $bs = strtolower(trim((string) ($accRow->billing_status ?? '')));

        // ✅ Si plan viene vacío, inferimos PRO cuando hay señales de suscripción/billing
        if ($plan === '' || $plan === '—') {
            $looksPaid =
                in_array($bs, ['active', 'trial', 'grace', 'overdue', 'suspended'], true)
                || in_array($cycle, ['monthly', 'yearly', 'annual', 'mensual', 'anual'], true);

            if ($looksPaid) {
                $plan = 'pro';
            }
        }

        // Free explícito => 0
        if ($plan === 'free') return 0.00;

        if ($plan === 'pro') {
            $monthly = $this->displayPriceMonthly();
            $annual  = $this->displayPriceAnnual($monthly);

            if (in_array($cycle, ['yearly', 'annual', 'anual'], true)) return round($annual, 2);
            return round($monthly, 2);
        }

        // Si no podemos determinar plan, no inventamos
        return null;
    }

    private function defaultLicenseAmountFromPlan(string $accountId): float
    {
        $schema = Schema::connection($this->adminConn);

        $cols = ['id'];
        if ($schema->hasColumn('accounts', 'plan')) $cols[] = 'plan';
        if ($schema->hasColumn('accounts', 'billing_cycle')) $cols[] = 'billing_cycle';
        if ($schema->hasColumn('accounts', 'billing_status')) $cols[] = 'billing_status';
        if ($schema->hasColumn('accounts', 'meta')) $cols[] = 'meta';

        $acc = DB::connection($this->adminConn)->table('accounts')->where('id', $accountId)->first($cols);

        if ($acc) {
            $v = $this->computeEffectiveLicenseAmountMxn($acc);
            if ($v !== null) return (float) $v;
        }

        return 0.00;
    }

    private function displayPriceMonthly(): float
    {
        $v = config('services.stripe.display_price_monthly');
        if ($v === null || $v === '') $v = env('STRIPE_DISPLAY_PRICE_MONTHLY', null);

        $n = is_numeric($v) ? (float) $v : (float) preg_replace('/[^0-9.\-]/', '', (string) $v);
        if ($n <= 0) $n = 990.00;
        return round($n, 2);
    }

    private function displayPriceAnnual(float $monthly): float
    {
        $v = config('services.stripe.display_price_annual');
        if ($v === null || $v === '') $v = env('STRIPE_DISPLAY_PRICE_ANNUAL', null);

        $n = is_numeric($v) ? (float) $v : (float) preg_replace('/[^0-9.\-]/', '', (string) $v);
        if ($n <= 0) $n = $monthly * 12;
        return round($n, 2);
    }

    // ======================= HELPERS =======================
    private function colEmail(): string
    {
        foreach (['correo_contacto', 'email'] as $c) {
            if ($this->hasCol($this->adminConn, 'accounts', $c)) return $c;
        }
        return 'email';
    }

    private function colPhone(): string
    {
        foreach (['telefono', 'phone', 'tel', 'celular'] as $c) {
            if ($this->hasCol($this->adminConn, 'accounts', $c)) return $c;
        }
        return 'phone';
    }

    private function colOtp(): string
    {
        if ($this->hasCol($this->adminConn, 'phone_otps', 'otp'))  return 'otp';
        if ($this->hasCol($this->adminConn, 'phone_otps', 'code')) return 'code';
        return 'otp';
    }

    /**
     * ✅ En tu schema real, accounts.rfc existe y es UNIQUE.
     */
    private function colRfcAdmin(): string
    {
        foreach (['rfc', 'rfc_padre', 'tax_id', 'rfc_cliente'] as $c) {
            if ($this->hasCol($this->adminConn, 'accounts', $c)) return $c;
        }
        return 'rfc';
    }

    /**
     * Resolver cuenta por:
     *  - accounts.id (numérico) o
     *  - accounts.rfc (string, unique)
     *
     * Regresa registro de accounts.
     */
    private function resolveAccount(string $key, array $select = ['*'])
    {
        $key = trim((string) $key);
        if ($key === '') return null;

        $rfcCol = $this->colRfcAdmin();
        $q = DB::connection($this->adminConn)->table('accounts')->select($select);

        // 1) por ID si es numérico
        if (ctype_digit($key)) {
            $acc = $q->where('id', (int) $key)->first();
            if ($acc) return $acc;
        }

        // 2) por RFC (case-insensitive)
        $upper = Str::upper($key);
        $acc = DB::connection($this->adminConn)->table('accounts')
            ->select($select)
            ->whereRaw('UPPER(' . $rfcCol . ') = ?', [$upper])
            ->first();

        return $acc ?: null;
    }

    private function requireAccount(string $key, array $select = ['*'])
    {
        $acc = $this->resolveAccount($key, $select);
        abort_if(!$acc, 404, 'Cuenta no encontrada (id/rfc inválido).');
        return $acc;
    }

    private function collectExtrasForAccountIds(array $accountIds): array
    {
        if (empty($accountIds)) return [];

        $fmt = static function ($v) {
            if (empty($v)) return null;
            try { return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i'); }
            catch (\Throwable $e) { return (string) $v; }
        };

        $otpCol = $this->colOtp();

        $tokens = collect();
        if (Schema::connection($this->adminConn)->hasTable('email_verifications')) {
            $tokens = DB::connection($this->adminConn)->table('email_verifications')
                ->whereIn('account_id', $accountIds)
                ->select('account_id as id', 'token', 'expires_at')
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('id')
                ->map(fn ($g) => $g->first());
        }

        $otps = collect();
        if (Schema::connection($this->adminConn)->hasTable('phone_otps')) {
            $otps = DB::connection($this->adminConn)->table('phone_otps')
                ->whereIn('account_id', $accountIds)
                ->select('account_id as id', DB::raw(($otpCol === 'otp' ? 'otp' : 'code') . ' as code'), 'channel', 'expires_at')
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('id')
                ->map(fn ($g) => $g->first());
        }

        $logs = collect();
        if (Schema::connection($this->adminConn)->hasTable('credential_logs')) {
            $logs = DB::connection($this->adminConn)->table('credential_logs')
                ->whereIn('account_id', $accountIds)
                ->select('account_id as id', 'sent_at')
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('id')
                ->map(fn ($g) => $g->first());
        }

        $out = [];
        foreach ($accountIds as $id) {
            $tok = $tokens->get($id);
            $otp = $otps->get($id);
            $log = $logs->get($id);

            $out[(string) $id] = [
                'email_token'       => $tok->token ?? null,
                'email_expires_at'  => $fmt($tok->expires_at ?? null),
                'otp_code'          => $otp->code ?? null,
                'otp_channel'       => $otp->channel ?? null,
                'otp_expires_at'    => $fmt($otp->expires_at ?? null),
                'cred_last_sent_at' => $fmt($log->sent_at ?? null),
            ];
        }

        return $out;
    }

    /**
     * MEJORA CRÍTICA: si mysql_clientes falla (credenciales, grants, etc),
     * el admin NO se cae; regresa estructura base.
     *
     * ⚠️ Por tu diseño actual, cuentas_cliente.rfc_padre guarda accounts.id (numérico string).
     */
    private function collectCredsForAccountIds(array $accountIds): array
    {
        if (empty($accountIds)) return [];

        $blank = [];
        foreach ($accountIds as $id) {
            $blank[(string) $id] = ['owner_email' => null, 'temp_pass' => null];
        }

        try {
            $schemaCli = Schema::connection('mysql_clientes');

            if (!$schemaCli->hasTable('cuentas_cliente') || !$schemaCli->hasTable('usuarios_cuenta')) {
                return $blank;
            }

            $schemaCliHasTipo = false;
            try {
                $schemaCliHasTipo = $schemaCli->hasColumn('usuarios_cuenta', 'tipo');
            } catch (\Throwable $e) {
                $schemaCliHasTipo = false;
            }

            $base = DB::connection('mysql_clientes')
                ->table('cuentas_cliente as c')
                ->join('usuarios_cuenta as u', 'u.cuenta_id', '=', 'c.id');

            // ✅ Canon: admin_account_id
            if ($schemaCli->hasColumn('cuentas_cliente', 'admin_account_id')) {
                $base->whereIn('c.admin_account_id', array_map('intval', $accountIds));
            } else {
                // legacy: rfc_padre
                $base->whereIn('c.rfc_padre', array_map('strval', $accountIds));
            }

            $owners = $base
                ->where(function ($q) use ($schemaCliHasTipo) {
                    $q->where('u.rol', 'owner');
                    if ($schemaCliHasTipo) $q->orWhere('u.tipo', 'owner');
                })
                ->select(
                    $schemaCli->hasColumn('cuentas_cliente', 'admin_account_id')
                        ? 'c.admin_account_id as account_id'
                        : 'c.rfc_padre as account_id',
                    'u.email'
                )
                ->orderBy('u.created_at', 'asc')
                ->get()
                ->groupBy('account_id')
                ->map(fn ($g) => $g->first());

        } catch (\Throwable $e) {
            Log::warning('collectCredsForAccountIds: mysql_clientes unavailable', [
                'error' => $e->getMessage(),
            ]);
            return $blank;
        }

        $last = session('tmp_last');

        $out = [];
        foreach ($accountIds as $id) {
            $idStr = (string) $id;
            $idL   = strtolower($idStr);

            $emailOwner = optional($owners->get($idStr))->email;

            $userOverride =
                session()->get("tmp_user.$idStr")
                ?? session()->get("tmp_user.$idL")
                ?? Cache::get("tmp_user.$idStr")
                ?? Cache::get("tmp_user.$idL")
                ?? (($last['key'] ?? null) === $idStr ? ($last['user'] ?? null) : null);

            $temp =
                session()->get("tmp_pass.$idStr")
                ?? session()->get("tmp_pass.$idL")
                ?? Cache::get("tmp_pass.$idStr")
                ?? Cache::get("tmp_pass.$idL")
                ?? (($last['key'] ?? null) === $idStr ? ($last['pass'] ?? null) : null);

            $out[$idStr] = [
                'owner_email' => $userOverride ?: $emailOwner,
                'temp_pass'   => $temp,
            ];
        }

        return $out;
    }

    private function upsertClienteLegacy(string $rfc, array $payload): void
    {
        if (!$this->legacyHasTable('clientes')) return;

        $rfc = strtoupper(trim((string) $rfc));
        if ($rfc === '') return;

        $rs = trim((string) ($payload['razon_social'] ?? ''));
        if ($rs === '') return;

        $hasNombreComercial = $this->legacyHasColumn('clientes', 'nombre_comercial');

        // ✅ PROD FIX: legacy.clientes exige email NOT NULL (sin default)
        $hasEmail = $this->legacyHasColumn('clientes', 'email');

        // Resolver email desde payload (puede venir como 'email' o como columna custom tipo 'correo_contacto')
        $email = '';
        foreach (['email', 'correo', 'mail', 'correo_contacto', 'contact_email'] as $k) {
            if (!array_key_exists($k, $payload)) continue;
            $cand = strtolower(trim((string) $payload[$k]));
            if ($cand !== '' && filter_var($cand, FILTER_VALIDATE_EMAIL)) {
                $email = $cand;
                break;
            }
        }

        // Si aún no hay email y el payload trae una key rara (ej: 'correo_contacto' por colEmail())
        if ($email === '') {
            foreach ($payload as $k => $v) {
                if (!is_string($k)) continue;
                if (!str_contains(strtolower($k), 'mail') && !str_contains(strtolower($k), 'correo') && strtolower($k) !== 'email') {
                    continue;
                }
                $cand = strtolower(trim((string) $v));
                if ($cand !== '' && filter_var($cand, FILTER_VALIDATE_EMAIL)) {
                    $email = $cand;
                    break;
                }
            }
        }

        // Default estable para no romper inserts
        if ($email === '') {
            $rfcSafe = preg_replace('/[^A-Z0-9]/', '', $rfc) ?: 'CLIENTE';
            $email = strtolower($rfcSafe) . '@pactopia.local';
        }

        $exists = DB::connection($this->legacyConn)->table('clientes')->where('rfc', $rfc)->first();

        if ($exists) {
            $upd = [
                'razon_social' => $rs,
                'updated_at'   => now(),
            ];

            if ($hasNombreComercial) {
                $upd['nombre_comercial'] = $rs;
            }

            // ✅ si existe columna email, mantenerla llena
            if ($hasEmail) {
                $upd['email'] = $email;
            }

            DB::connection($this->legacyConn)->table('clientes')->where('rfc', $rfc)->update($upd);

        } else {
            $ins = [
                'codigo'       => $this->genCodigoCliente(),
                'razon_social' => $rs,
                'rfc'          => $rfc,
                'activo'       => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            if ($hasNombreComercial) {
                $ins['nombre_comercial'] = $rs;
            }

            // ✅ CRÍTICO: evitar 1364 "email doesn't have a default value"
            if ($hasEmail) {
                $ins['email'] = $email;
            }

            DB::connection($this->legacyConn)->table('clientes')->insert($ins);
        }
    }

    private function genCodigoCliente(): string
    {
        do {
            $cand = 'C' . base_convert((string) time(), 10, 36) . strtoupper(Str::random(6));
        } while (DB::connection($this->legacyConn)->table('clientes')->where('codigo', $cand)->exists());

        return $cand;
    }

    private function genCodigoClienteEspejo(): string
    {
        do {
            $cand = 'CC' . base_convert((string) time(), 10, 36) . strtoupper(Str::random(4));
        } while (
            Schema::connection('mysql_clientes')->hasTable('cuentas_cliente') &&
            DB::connection('mysql_clientes')->table('cuentas_cliente')->where('codigo_cliente', $cand)->exists()
        );

        return $cand;
    }

    private function nextCustomerNo(): string
    {
        $conn = DB::connection('mysql_clientes');
        $tbl  = 'cuentas_cliente';
        $col  = 'customer_no';

        $max = $conn->table($tbl)->select(DB::raw("MAX(CAST($col AS UNSIGNED)) as max_no"))->value('max_no');

        $n = ((int) $max) + 1;
        $candidate = str_pad((string) $n, 8, '0', STR_PAD_LEFT);

        $tries = 0;
        while ($conn->table($tbl)->where($col, $candidate)->exists() && $tries < 50) {
            $n++;
            $candidate = str_pad((string) $n, 8, '0', STR_PAD_LEFT);
            $tries++;
        }

        if ($tries >= 50) {
            do {
                $candidate = (string) random_int(10000000, 99999999);
            } while ($conn->table($tbl)->where($col, $candidate)->exists());
        }

        return $candidate;
    }

    private function ensureUniqueUserEmail(string $email, string $cuentaId, string $rfc): string
    {
        $conn  = DB::connection('mysql_clientes');
        $email = strtolower(trim($email));

        if ($email === '') $email = 'owner+' . $rfc . '@example.test';

        $exists = $conn->table('usuarios_cuenta')->where('email', $email)->first();
        if ($exists && (string) $exists->cuenta_id === (string) $cuentaId) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2) + [null, null];
        if (!$domain) {
            $local  = 'owner';
            $domain = 'example.test';
        }

        $tagBase = strtolower($rfc);
        $cleanLocal = preg_replace('/[^a-z0-9._+-]/i', '', (string) $local) ?: 'owner';
        $cleanLocal = substr($cleanLocal, 0, 48);

        $i = 0;
        do {
            $suffix = $i === 0 ? $tagBase : ($tagBase . $i);
            $candidateLocal = substr($cleanLocal . '+' . $suffix, 0, 64);
            $candidate = strtolower($candidateLocal . '@' . $domain);
            $taken = $conn->table('usuarios_cuenta')->where('email', $candidate)->exists();
            $i++;
        } while ($taken && $i < 200);

        if ($taken) {
            $candidate = strtolower($cleanLocal . '+' . base_convert((string) time(), 10, 36) . Str::random(4) . '@' . $domain);
        }

        return $candidate;
    }

    private function upsertOwnerForCuenta(object $acc, object $cuenta, string $rfcReal): object
    {
        $conn      = DB::connection('mysql_clientes');
        $schemaCli = Schema::connection('mysql_clientes');

        $displayName = trim((string) ($acc->razon_social ?? ''));
        if ($displayName === '') {
            $displayName = $rfcReal !== '' ? $rfcReal : ('Cuenta ' . (string) ($acc->id ?? ''));
        }

        $ownerQ = $conn->table('usuarios_cuenta')
            ->where('cuenta_id', $cuenta->id)
            ->where(function ($q) use ($schemaCli) {
                $q->where('rol', 'owner');
                if ($schemaCli->hasColumn('usuarios_cuenta', 'tipo')) $q->orWhere('tipo', 'owner');
            })
            ->orderBy('created_at', 'asc');

        $owner = $ownerQ->first();
        if ($owner) {
            $upd = [
                'updated_at' => now(),
                'rol'        => 'owner',
                'activo'     => 1,
            ];

            if ($schemaCli->hasColumn('usuarios_cuenta', 'tipo')) {
                $upd['tipo'] = 'owner';
            }

            if (trim((string) ($owner->nombre ?? '')) !== $displayName) {
                $upd['nombre'] = $displayName;
            }

            if (count($upd) > 1) {
                $conn->table('usuarios_cuenta')
                    ->where('id', (string) $owner->id)
                    ->update($upd);
            }

            return (object) [
                'id'    => $owner->id,
                'email' => $owner->email,
            ];
        }

        $baseEmail = strtolower((string) ($acc->email ?: ('owner@' . $rfcReal . '.example.test')));

        $userSameEmailSameCuenta = $conn->table('usuarios_cuenta')
            ->where('cuenta_id', $cuenta->id)
            ->where('email', $baseEmail)
            ->first();

        if ($userSameEmailSameCuenta) {
            $upd = [
                'rol'        => 'owner',
                'activo'     => 1,
                'nombre'     => $displayName,
                'updated_at' => now(),
            ];

            if ($schemaCli->hasColumn('usuarios_cuenta', 'tipo')) {
                $upd['tipo'] = 'owner';
            }

            $conn->table('usuarios_cuenta')
                ->where('id', $userSameEmailSameCuenta->id)
                ->update($upd);

            return (object) [
                'id'    => $userSameEmailSameCuenta->id,
                'email' => $userSameEmailSameCuenta->email,
            ];
        }

        $email = $this->ensureUniqueUserEmail($baseEmail, (string) $cuenta->id, $rfcReal);

        $uid = (string) Str::uuid();

        $tmpRaw = Str::password(12);
        $tmp    = preg_replace('/[^A-Za-z0-9@#\-\_\.\!\?]/', '', (string) $tmpRaw);
        if ($tmp === '' || strlen($tmp) < 8) $tmp = 'P360#' . Str::random(8);

        $hash = Hash::make($tmp);

        $payloadU = [
            'id'         => $uid,
            'cuenta_id'  => $cuenta->id,
            'rol'        => 'owner',
            'nombre'     => $displayName,
            'email'      => $email,
            'password'   => $hash,
            'activo'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($schemaCli->hasColumn('usuarios_cuenta', 'tipo')) $payloadU['tipo'] = 'owner';
        if ($schemaCli->hasColumn('usuarios_cuenta', 'must_change_password')) $payloadU['must_change_password'] = 1;
        if ($schemaCli->hasColumn('usuarios_cuenta', 'sync_version')) $payloadU['sync_version'] = 1;
        if ($schemaCli->hasColumn('usuarios_cuenta', 'ultimo_login_at')) $payloadU['ultimo_login_at'] = null;
        if ($schemaCli->hasColumn('usuarios_cuenta', 'ip_ultimo_login')) $payloadU['ip_ultimo_login'] = null;
        if ($schemaCli->hasColumn('usuarios_cuenta', 'password_temp')) $payloadU['password_temp'] = $hash;

        $conn->table('usuarios_cuenta')->insert($payloadU);

        // tmp por RFC real
        session()->flash("tmp_pass.$rfcReal", $tmp);
        session()->flash("tmp_user.$rfcReal", $email);

        return (object) ['id' => $uid, 'email' => $email];
    }

    /**
     * Normaliza duplicados en mysql_clientes.cuentas_cliente para un account_id.
     *
     * Caso real detectado:
     * - 2 filas con admin_account_id = {accountId}
     *   - una legacy: "Cuenta {id}" con rfc_padre="{id}" y rfc=null
     *   - una real: con razon_social real y/o rfc/rfc_padre con RFC real
     *
     * Estrategia:
     * - Elegir "winner" por score (no-legacy + rfc presente + rfc_padre RFC-like + updated_at)
     * - Asegurar que winner tenga:
     *    - admin_account_id = accountId (si existe col)
     *    - rfc = RFC real (si existe col y tenemos rfcReal)
     *    - rfc_padre = RFC real (si existe col y tenemos rfcReal)
     * - A los demás: desasociar admin_account_id (y opcional marcar razon_social)
     *
     * Devuelve la fila winner (object) o null si no hay tabla/rows.
     */
    private function normalizeMirrorCuenta(string $accountId, string $rfcReal): ?object
    {
        $accountId = trim((string)$accountId);
        $rfcReal   = strtoupper(trim((string)$rfcReal));

        $schemaCli = Schema::connection('mysql_clientes');
        if (!$schemaCli->hasTable('cuentas_cliente')) return null;

        $conn = DB::connection('mysql_clientes');

        // Traer candidatos que suelen colisionar
        $rows = $conn->table('cuentas_cliente')
            ->where(function($w) use ($schemaCli, $accountId, $rfcReal){
                if ($schemaCli->hasColumn('cuentas_cliente','admin_account_id')) {
                    $w->orWhere('admin_account_id', (int)$accountId);
                }
                if ($schemaCli->hasColumn('cuentas_cliente','rfc_padre')) {
                    $w->orWhere('rfc_padre', (string)$accountId);
                    if ($rfcReal !== '') $w->orWhereRaw('UPPER(rfc_padre)=?', [$rfcReal]);
                }
                if ($schemaCli->hasColumn('cuentas_cliente','rfc') && $rfcReal !== '') {
                    $w->orWhereRaw('UPPER(rfc)=?', [$rfcReal]);
                }
            })
            ->get(['id','admin_account_id','rfc','rfc_padre','razon_social','updated_at'])
            ->all();

        if (empty($rows)) return null;
        if (count($rows) === 1) {
            $winner = (object) $rows[0];
            // Curar mínimo
            $this->healMirrorWinner($winner, $accountId, $rfcReal);
            return $winner;
        }

        // Score ganador
        $best = null; $bestScore = -999999;

        foreach ($rows as $r) {
            $rs = strtolower(trim((string)($r->razon_social ?? '')));
            $isLegacyName = ($rs === strtolower('Cuenta '.$accountId)) || str_starts_with($rs, 'cuenta ');

            $score = 0;

            // Preferir NO legacy
            if (!$isLegacyName) $score += 50;

            // Preferir rfc presente
            $rfc = strtoupper(trim((string)($r->rfc ?? '')));
            if ($rfc !== '') $score += 30;

            // Preferir rfc_padre RFC-like (>= 12 chars y contiene letras+numeros)
            $rp = strtoupper(trim((string)($r->rfc_padre ?? '')));
            if ($rp !== '' && strlen($rp) >= 12 && preg_match('/[A-Z]/', $rp) && preg_match('/\d/', $rp)) $score += 15;

            // Preferir updated_at más reciente (si se puede parsear)
            try {
                $ts = \Illuminate\Support\Carbon::parse($r->updated_at)->timestamp;
                $score += (int) min(20, max(0, ($ts % 100000) / 5000)); // micro-bias estable
            } catch (\Throwable $e) {
                // ignore
            }

            // Preferir match exacto por RFC real si aplica
            if ($rfcReal !== '') {
                if ($rfc === $rfcReal) $score += 40;
                if ($rp === $rfcReal)  $score += 20;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $r;
            }
        }

        $winner = (object) $best;

        // Curar winner (admin_account_id / rfc / rfc_padre)
        $this->healMirrorWinner($winner, $accountId, $rfcReal);

        // Reasociar usuarios de cuentas duplicadas al winner
        if ($schemaCli->hasTable('usuarios_cuenta')) {
            foreach ($rows as $r) {
                if ((string) $r->id === (string) $winner->id) continue;

                try {
                    $conn->table('usuarios_cuenta')
                        ->where('cuenta_id', (string) $r->id)
                        ->update([
                            'cuenta_id'   => (string) $winner->id,
                            'updated_at'  => now(),
                        ]);
                } catch (\Throwable $e) {
                    // no-op
                }
            }
        }

        // Desasociar duplicados (muy importante para que ya no “agarre cualquiera”)
        foreach ($rows as $r) {
            if ((string)$r->id === (string)$winner->id) continue;

            $upd = ['updated_at' => now()];
            if ($schemaCli->hasColumn('cuentas_cliente','admin_account_id')) {
                // lo desasociamos para que no compita
                $upd['admin_account_id'] = null;
            }

            // opcional: marcar para diagnóstico (no borro para no perder datos)
            if ($schemaCli->hasColumn('cuentas_cliente','razon_social')) {
                $rs = trim((string)($r->razon_social ?? ''));
                if (!str_starts_with($rs, '[DUPLICATE]')) {
                    $upd['razon_social'] = '[DUPLICATE] ' . ($rs !== '' ? $rs : ('cuenta '.$accountId));
                }
            }

            try {
                $conn->table('cuentas_cliente')->where('id', $r->id)->update($upd);
            } catch (\Throwable $e) {
                // no rompe flujo
            }
        }

        // Refrescar winner ya curado
        try {
            $winner = $conn->table('cuentas_cliente')->where('id', $winner->id)->first();
        } catch (\Throwable $e) {
            // ignore
        }

        return $winner ?: (object)$best;
    }

    /**
     * Cura winner para que quede consistente.
     */
    private function healMirrorWinner(object $winner, string $accountId, string $rfcReal): void
    {
        $accountId = trim((string)$accountId);
        $rfcReal   = strtoupper(trim((string)$rfcReal));

        $schemaCli = Schema::connection('mysql_clientes');
        if (!$schemaCli->hasTable('cuentas_cliente')) return;

        $conn = DB::connection('mysql_clientes');

        $upd = ['updated_at' => now()];

        if ($schemaCli->hasColumn('cuentas_cliente','admin_account_id')) {
            if ((string)($winner->admin_account_id ?? '') !== (string)$accountId) {
                $upd['admin_account_id'] = (int)$accountId;
            }
        }

        if ($rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente','rfc')) {
            $cur = strtoupper(trim((string)($winner->rfc ?? '')));
            if ($cur !== $rfcReal) $upd['rfc'] = $rfcReal;
        }

        if ($rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente','rfc_padre')) {
            $cur = strtoupper(trim((string)($winner->rfc_padre ?? '')));
            // Si rfc_padre está vacío o es el id legacy, lo curamos a RFC real
            if ($cur === '' || $cur === (string)$accountId) {
                $upd['rfc_padre'] = $rfcReal;
            }
        }

        if (count($upd) <= 1) return;

        try {
            $conn->table('cuentas_cliente')->where('id', $winner->id)->update($upd);
        } catch (\Throwable $e) {
            // ignore
        }
    }


    /**
     * ✅ Asegura mirror por accounts.id (rfc_padre) y owner.
     *    $accountId: accounts.id
     *    $rfcReal: accounts.rfc (real)
     */
    private function ensureMirrorAndOwner(string $accountId, string $rfcReal): array
    {
        $accountId = trim((string) $accountId);
        $rfcReal   = strtoupper(trim((string) $rfcReal));

        abort_unless(
            Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')
            && Schema::connection('mysql_clientes')->hasTable('usuarios_cuenta'),
            500,
            'Faltan tablas espejo (cuentas_cliente / usuarios_cuenta) en mysql_clientes.'
        );

        // ====== Admin SOT ======
        $emailCol = $this->colEmail();
        $rfcColA  = $this->colRfcAdmin();

        $acc = DB::connection($this->adminConn)->table('accounts')->where('id', $accountId)->first([
            'id',
            $rfcColA,
            'razon_social',
            DB::raw("$emailCol as email"),
        ]);
        abort_if(!$acc, 404, 'Cuenta SOT (admin.accounts) no existe');

        // Si no vino RFC, tomamos el del SOT
        if ($rfcReal === '') {
            $rfcReal = strtoupper(trim((string) ($acc->{$rfcColA} ?? '')));
        }

        $schemaCli = Schema::connection('mysql_clientes');
        $connCli   = DB::connection('mysql_clientes');

        // ================================
        // ✅ NORMALIZACIÓN ANTI-DUPLICADOS
        // - Si existen duplicados, deja UN "winner" amarrado a accounts.id
        // - IMPORTANTE: si winner existe, lo usamos como cuenta canónica
        // ================================
        $winner = null;
        try {
            $winner = $this->normalizeMirrorCuenta((string) $acc->id, $rfcReal);
        } catch (\Throwable $e) {
            $winner = null; // no rompe flujo
        }

        // ==========================
        // Resolver cuenta espejo (determinístico)
        // ==========================
        $cuenta = null;

        // 0) Si normalizeMirrorCuenta ya eligió winner, úsalo
        if ($winner && is_object($winner) && !empty($winner->id)) {
            try {
                $cuenta = $connCli->table('cuentas_cliente')->where('id', (string) $winner->id)->first();
            } catch (\Throwable $e) {
                $cuenta = $winner; // fallback mínimo
            }
        }

        // 1) Canon: admin_account_id
        if (!$cuenta && $schemaCli->hasColumn('cuentas_cliente', 'admin_account_id')) {
            $cuenta = $connCli->table('cuentas_cliente')
                ->where('admin_account_id', (int) $acc->id)
                ->orderByDesc('updated_at')
                ->first();
        }

        // 2) rfc = RFC real
        if (!$cuenta && $rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc')) {
            $cuenta = $connCli->table('cuentas_cliente')
                ->whereRaw('UPPER(rfc) = ?', [$rfcReal])
                ->orderByDesc('updated_at')
                ->first();
        }

        // 3) rfc_padre = RFC real
        if (!$cuenta && $rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc_padre')) {
            $cuenta = $connCli->table('cuentas_cliente')
                ->whereRaw('UPPER(rfc_padre) = ?', [$rfcReal])
                ->orderByDesc('updated_at')
                ->first();
        }

        // 4) ÚLTIMO (legacy) SOLO si NO existe admin_account_id en schema
        if (
            !$cuenta
            && !$schemaCli->hasColumn('cuentas_cliente', 'admin_account_id')
            && $schemaCli->hasColumn('cuentas_cliente', 'rfc_padre')
        ) {
            $cuenta = $connCli->table('cuentas_cliente')
                ->where('rfc_padre', (string) $acc->id)
                ->orderByDesc('updated_at')
                ->first();
        }

        // ==========================
        // Crear si no existe
        // ==========================
        if (!$cuenta) {
            $cid = (string) Str::uuid();

            $payload = [
                'id'           => $cid,
                'razon_social' => $acc->razon_social ?: ('Cuenta ' . (string) $acc->id),
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            if ($schemaCli->hasColumn('cuentas_cliente', 'admin_account_id')) {
                $payload['admin_account_id'] = (int) $acc->id;
            }

            if ($schemaCli->hasColumn('cuentas_cliente', 'rfc') && $rfcReal !== '') {
                $payload['rfc'] = $rfcReal;
            }

            if ($schemaCli->hasColumn('cuentas_cliente', 'rfc_padre')) {
                $payload['rfc_padre'] = ($rfcReal !== '') ? $rfcReal : (string) $acc->id;
            }

            if ($schemaCli->hasColumn('cuentas_cliente', 'codigo_cliente'))    $payload['codigo_cliente'] = $this->genCodigoClienteEspejo();
            if ($schemaCli->hasColumn('cuentas_cliente', 'customer_no'))       $payload['customer_no'] = $this->nextCustomerNo();
            if ($schemaCli->hasColumn('cuentas_cliente', 'nombre_comercial'))  $payload['nombre_comercial'] = $payload['razon_social'];
            if ($schemaCli->hasColumn('cuentas_cliente', 'activo'))            $payload['activo'] = 1;
            if ($schemaCli->hasColumn('cuentas_cliente', 'email'))             $payload['email'] = $acc->email ?: null;

            if ($schemaCli->hasColumn('cuentas_cliente', 'telefono'))          $payload['telefono'] = null;
            if ($schemaCli->hasColumn('cuentas_cliente', 'plan'))              $payload['plan'] = null;
            if ($schemaCli->hasColumn('cuentas_cliente', 'billing_cycle'))     $payload['billing_cycle'] = null;
            if ($schemaCli->hasColumn('cuentas_cliente', 'next_invoice_date')) $payload['next_invoice_date'] = null;

            if ($schemaCli->hasColumn('cuentas_cliente', 'estado_cuenta'))      $payload['estado_cuenta'] = 'activa';
            if ($schemaCli->hasColumn('cuentas_cliente', 'is_blocked'))         $payload['is_blocked'] = 0;

            $connCli->table('cuentas_cliente')->insert($payload);

            $cuenta = $connCli->table('cuentas_cliente')->where('id', $cid)->first();
            if (!$cuenta) {
                $cuenta = (object) ['id' => $cid];
            }
        } else {

            // ==========================
            // Cura registro existente (consistencia mínima)
            // ==========================
            try {
                $upd2 = ['updated_at' => now()];

                // admin_account_id obligatorio si existe la columna
                if ($schemaCli->hasColumn('cuentas_cliente', 'admin_account_id')) {
                    $curA = (string) ($cuenta->admin_account_id ?? '');
                    if ($curA === '' || (int) $curA !== (int) $acc->id) {
                        $upd2['admin_account_id'] = (int) $acc->id;
                    }
                }

                // rfc real
                if ($schemaCli->hasColumn('cuentas_cliente', 'rfc') && $rfcReal !== '') {
                    $cur = strtoupper(trim((string) ($cuenta->rfc ?? '')));
                    if ($cur !== $rfcReal) $upd2['rfc'] = $rfcReal;
                }

                // rfc_padre real (si trae "14" u otra cosa corta)
                if ($schemaCli->hasColumn('cuentas_cliente', 'rfc_padre') && $rfcReal !== '') {
                    $cur = strtoupper(trim((string) ($cuenta->rfc_padre ?? '')));
                    if ($cur === '' || $cur === strtoupper((string) $acc->id) || strlen($cur) < 12) {
                        $upd2['rfc_padre'] = $rfcReal;
                    }
                }

                // activo=1
                if ($schemaCli->hasColumn('cuentas_cliente', 'activo') && (int) ($cuenta->activo ?? 1) === 0) {
                    $upd2['activo'] = 1;
                }

                // estado_cuenta=activa
                if ($schemaCli->hasColumn('cuentas_cliente', 'estado_cuenta')) {
                    $st = strtolower(trim((string) ($cuenta->estado_cuenta ?? '')));
                    if ($st === '' || $st === 'suspendida' || $st === 'bloqueada') {
                        $upd2['estado_cuenta'] = 'activa';
                    }
                }

                // is_blocked=0
                if ($schemaCli->hasColumn('cuentas_cliente', 'is_blocked') && (int) ($cuenta->is_blocked ?? 0) !== 0) {
                    $upd2['is_blocked'] = 0;
                }

                if (count($upd2) > 1) {
                    $connCli->table('cuentas_cliente')->where('id', $cuenta->id)->update($upd2);

                    // refrescar objeto local
                    foreach ($upd2 as $k => $v) {
                        if ($k === 'updated_at') continue;
                        $cuenta->{$k} = $v;
                    }
                }
            } catch (\Throwable $e) {
                // no rompe
            }
        }

        // ====== Owner ======
        $ownerObj = $this->upsertOwnerForCuenta($acc, $cuenta, $rfcReal !== '' ? $rfcReal : (string) $acc->id);

        return ['cuenta' => $cuenta, 'owner' => $ownerObj];
    }

    private function syncPlanToMirror(string $accountId, array $payload): void
    {
        if (!Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) return;

        $schemaCli = Schema::connection('mysql_clientes');
        $conn      = DB::connection('mysql_clientes');

        // =========================
        // RFC real desde admin.accounts
        // =========================
        $rfcReal = '';
        try {
            $rfcCol = $this->colRfcAdmin();
            $acc = DB::connection($this->adminConn)
                ->table('accounts')
                ->where('id', (int)$accountId)
                ->first([$rfcCol]);

            $rfcReal = strtoupper(trim((string)($acc->{$rfcCol} ?? '')));
        } catch (\Throwable $e) {
            $rfcReal = '';
        }

        // =========================
        // Localizar cuenta espejo (robusto)
        // =========================
        $cuenta = null;

        if ($schemaCli->hasColumn('cuentas_cliente', 'admin_account_id')) {
            $aid = (int)$accountId;

            try {
                $select = ['id', 'admin_account_id', 'updated_at'];
                if ($schemaCli->hasColumn('cuentas_cliente', 'rfc')) $select[] = 'rfc';
                if ($schemaCli->hasColumn('cuentas_cliente', 'rfc_padre')) $select[] = 'rfc_padre';

                $rows = $conn->table('cuentas_cliente')
                    ->where('admin_account_id', $aid)
                    ->orderByDesc('updated_at')
                    ->get($select)
                    ->toArray();

                if (!empty($rows)) {
                    if (count($rows) > 1) {
                        $picked = null;

                        // 1) match exacto por RFC real
                        if ($rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc')) {
                            foreach ($rows as $r) {
                                $rr = strtoupper(trim((string)($r->rfc ?? '')));
                                if ($rr !== '' && $rr === $rfcReal) { $picked = $r; break; }
                            }
                        }

                        // 2) si no, el primero con rfc no vacío
                        if (!$picked && $schemaCli->hasColumn('cuentas_cliente', 'rfc')) {
                            foreach ($rows as $r) {
                                if (trim((string)($r->rfc ?? '')) !== '') { $picked = $r; break; }
                            }
                        }

                        // 3) si no, el más reciente
                        if (!$picked) $picked = $rows[0];

                        $cuenta = $conn->table('cuentas_cliente')->where('id', (string)$picked->id)->first();
                    } else {
                        $cuenta = $conn->table('cuentas_cliente')->where('id', (string)$rows[0]->id)->first();
                    }
                }
            } catch (\Throwable $e) {
                $cuenta = $conn->table('cuentas_cliente')
                    ->where('admin_account_id', $aid)
                    ->orderByDesc('updated_at')
                    ->first();
            }
        }

        // B) fallback por rfc = RFC real
        if (!$cuenta && $rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc')) {
            $cuenta = $conn->table('cuentas_cliente')
                ->whereRaw('UPPER(rfc) = ?', [$rfcReal])
                ->orderByDesc('updated_at')
                ->first();
        }

        // C) fallback por rfc_padre = RFC real
        if (!$cuenta && $rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc_padre')) {
            $cuenta = $conn->table('cuentas_cliente')
                ->whereRaw('UPPER(rfc_padre) = ?', [$rfcReal])
                ->orderByDesc('updated_at')
                ->first();
        }

        // D) legacy
        if (!$cuenta && $schemaCli->hasColumn('cuentas_cliente', 'rfc_padre')) {
            $cuenta = $conn->table('cuentas_cliente')
                ->where('rfc_padre', (string)$accountId)
                ->orderByDesc('updated_at')
                ->first();
        }

        if (!$cuenta) return;

        // =========================
        // Helpers (normalización local)
        // =========================
        $blankToNull = function ($v) {
            $v = is_string($v) ? trim($v) : $v;
            if ($v === null) return null;
            if (is_string($v) && $v === '') return null;
            return $v;
        };

        $normEmail = function ($v) use ($blankToNull) {
            $v = $blankToNull($v);
            if ($v === null) return null;
            $v = strtolower(trim((string)$v));
            return $v === '' ? null : $v;
        };

        $normPhone = function ($v) use ($blankToNull) {
            $v = $blankToNull($v);
            if ($v === null) return null;
            return trim((string)$v) ?: null;
        };

        $normPlan = function ($v) use ($blankToNull) {
            $v = $blankToNull($v);
            if ($v === null) return null;
            $v = strtoupper(trim((string)$v));
            // normaliza compat: "pro/free/PRO/FREE"
            if ($v === 'PRO' || $v === 'FREE') return $v;
            return $v; // fallback en mayúsculas
        };

        $normModoCobro = function ($v) use ($blankToNull) {
            $v = $blankToNull($v);
            if ($v === null) return null;
            $v = strtolower(trim((string)$v));
            if ($v === 'monthly') return 'mensual';
            if ($v === 'yearly' || $v === 'annual') return 'anual';
            if ($v === 'mensual' || $v === 'anual') return $v;
            return $v ?: null;
        };

        $normBillingCycle = function ($v) use ($blankToNull) {
            $v = $blankToNull($v);
            if ($v === null) return null;
            $v = strtolower(trim((string)$v));
            if ($v === 'mensual') return 'monthly';
            if ($v === 'anual' || $v === 'annual') return 'yearly';
            if ($v === 'monthly' || $v === 'yearly') return $v;
            return $v ?: null;
        };

        $normBoolInt = function ($v) {
            if ($v === null) return null;
            if (is_bool($v)) return $v ? 1 : 0;
            $s = strtolower(trim((string)$v));
            if ($s === '1' || $s === 'true' || $s === 'on' || $s === 'yes') return 1;
            if ($s === '0' || $s === 'false' || $s === 'off' || $s === 'no') return 0;
            return ((int)$v) ? 1 : 0;
        };

        // =========================
        // UPDATE (solo lo que llega en payload)
        // =========================
        $upd = ['updated_at' => now()];

        // plan
        $planRaw = $payload['plan'] ?? ($payload['plan_actual'] ?? null);
        $plan    = $normPlan($planRaw);

        if ($plan !== null) {
            if ($schemaCli->hasColumn('cuentas_cliente', 'plan_actual')) $upd['plan_actual'] = $plan;
            if ($schemaCli->hasColumn('cuentas_cliente', 'plan'))       $upd['plan'] = $plan;
        }

        // email
        if ($schemaCli->hasColumn('cuentas_cliente', 'email') && array_key_exists('email', $payload)) {
            $upd['email'] = $normEmail($payload['email']);
        }

        // telefono (mirror usa "telefono") — SIEMPRE que venga en payload phone|telefono
        if ($schemaCli->hasColumn('cuentas_cliente', 'telefono') && (array_key_exists('phone', $payload) || array_key_exists('telefono', $payload))) {
            $tel = array_key_exists('telefono', $payload) ? $payload['telefono'] : ($payload['phone'] ?? null);
            $upd['telefono'] = $normPhone($tel);
        }

        // modo_cobro
        if ($schemaCli->hasColumn('cuentas_cliente', 'modo_cobro') && array_key_exists('modo_cobro', $payload)) {
            $upd['modo_cobro'] = $normModoCobro($payload['modo_cobro']);
        }

        // billing_cycle
        if ($schemaCli->hasColumn('cuentas_cliente', 'billing_cycle') && array_key_exists('billing_cycle', $payload)) {
            $upd['billing_cycle'] = $normBillingCycle($payload['billing_cycle']);
        }

        // next_invoice_date
        if ($schemaCli->hasColumn('cuentas_cliente', 'next_invoice_date') && array_key_exists('next_invoice_date', $payload)) {
            $upd['next_invoice_date'] = $blankToNull($payload['next_invoice_date']);
        }

        // mantener RFC real en espejo
        if ($rfcReal !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc')) {
            $upd['rfc'] = $rfcReal;
        }

        // vault_active
        if ($schemaCli->hasColumn('cuentas_cliente', 'vault_active') && array_key_exists('vault_active', $payload)) {
            $upd['vault_active'] = (int)($normBoolInt($payload['vault_active']) ?? 0);
        }

        // bloqueo -> espejo
        if ($schemaCli->hasColumn('cuentas_cliente', 'is_blocked') && array_key_exists('is_blocked', $payload)) {
            $upd['is_blocked'] = (int)($normBoolInt($payload['is_blocked']) ?? 0);
        }

        if ($schemaCli->hasColumn('cuentas_cliente', 'estado_cuenta') && array_key_exists('is_blocked', $payload)) {
            $upd['estado_cuenta'] = ((int)($normBoolInt($payload['is_blocked']) ?? 0) === 1) ? 'suspendida' : 'activa';
        }

        if (count($upd) <= 1) return;

        $conn->table('cuentas_cliente')->where('id', (string)$cuenta->id)->update($upd);
    }

        private function setBlockedState(string $key, int $blocked, string $okMessage): RedirectResponse
    {
        $blocked = $blocked === 1 ? 1 : 0;

        $acc = $this->requireAccount($key, ['id', $this->colRfcAdmin(), 'razon_social', 'meta']);
        $accountId = (string) $acc->id;
        $rfcReal   = strtoupper(trim((string) ($acc->{$this->colRfcAdmin()} ?? '')));

        $payloadAdmin = ['updated_at' => now()];
        $payloadMirror = ['updated_at' => now()];

        if ($this->hasCol($this->adminConn, 'accounts', 'is_blocked')) {
            $payloadAdmin['is_blocked'] = $blocked;
            $payloadMirror['is_blocked'] = $blocked;
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'billing_status')) {
            $payloadAdmin['billing_status'] = $blocked ? 'suspended' : 'active';
            $payloadMirror['billing_status'] = $blocked ? 'suspended' : 'active';
        }

        if ($this->hasCol($this->adminConn, 'accounts', 'meta')) {
            $meta = $this->decodeMeta($acc->meta ?? null);
            data_set($meta, 'account.is_blocked', (bool) $blocked);
            data_set($meta, 'billing.status', $blocked ? 'suspended' : 'active');
            $payloadAdmin['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        DB::connection($this->adminConn)
            ->table('accounts')
            ->where('id', (int) $accountId)
            ->update($payloadAdmin);

        if ($rfcReal !== '') {
            $this->upsertClienteLegacy($rfcReal, ['razon_social' => (string) ($acc->razon_social ?? '')] + $payloadAdmin);
        }

        $this->syncPlanToMirror($accountId, $payloadMirror + $payloadAdmin);

        return back()->with('ok', $okMessage);
    }

    private function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) return $meta;
        if (is_object($meta)) return (array) $meta;
        if (!is_string($meta)) return [];
        $s = trim($meta);
        if ($s === '') return [];
        try {
            $j = json_decode($s, true);
            return is_array($j) ? $j : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function normalizeEmails(mixed $input): array
    {
        if (is_array($input)) {
            $parts = $input;
        } else {
            $s = trim((string) $input);
            if ($s === '') return [];
            $s = str_replace([';', "\n", "\r", "\t"], [',', ',', ',', ' '], $s);
            $parts = array_filter(array_map('trim', explode(',', $s)));
        }

        $out = [];
        foreach ($parts as $p) {
            $e = strtolower(trim((string) $p));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $out[] = $e;
        }
        return array_values(array_unique($out));
    }

    private function legacyHasTable(string $table): bool
    {
        try {
            return Schema::connection($this->legacyConn)->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function legacyHasColumn(string $table, string $col): bool
    {
        try {
            return Schema::connection($this->legacyConn)->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Cache para hasColumn: reduce overhead de Schema en listados grandes.
     */
    private function hasCol(string $conn, string $table, string $col): bool
    {
         $k = "{$conn}.{$table}.{$col}";
         if (array_key_exists($k, $this->schemaHas)) return $this->schemaHas[$k];
         try {
             return $this->schemaHas[$k] = Schema::connection($conn)->hasColumn($table, $col);
         } catch (\Throwable $e) {
             return $this->schemaHas[$k] = false;
         }
    }

    /**
     * Marca verificación en mysql_clientes (owner + opcional cuentas_cliente)
     * y limpia pendientes (otp/tokens) si existen tablas.
     *
     * $type: 'email' | 'phone'
     */
    private function forceVerifyInMirror(string $accountId, string $rfcReal, string $type): void
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['email', 'phone'], true)) return;

        try {
            // Asegura espejo + owner
            $pack = $this->ensureMirrorAndOwner($accountId, $rfcReal !== '' ? $rfcReal : $accountId);
            $cuentaId = (string) ($pack['cuenta']->id ?? '');
            $ownerId  = (string) ($pack['owner']->id ?? '');

            $schemaCli = Schema::connection('mysql_clientes');
            $connCli   = DB::connection('mysql_clientes');

            // --- usuarios_cuenta (owner) ---
            if ($schemaCli->hasTable('usuarios_cuenta') && $ownerId !== '') {
                $uUpd = ['updated_at' => now()];
                if ($type === 'email' && $schemaCli->hasColumn('usuarios_cuenta', 'email_verified_at')) {
                    $uUpd['email_verified_at'] = now();
                }
                if ($type === 'phone' && $schemaCli->hasColumn('usuarios_cuenta', 'phone_verified_at')) {
                    $uUpd['phone_verified_at'] = now();
                }

                // Opcional: algunas implementaciones guardan flags booleanos
                if ($type === 'email' && $schemaCli->hasColumn('usuarios_cuenta', 'email_verified')) {
                    $uUpd['email_verified'] = 1;
                }
                if ($type === 'phone' && $schemaCli->hasColumn('usuarios_cuenta', 'phone_verified')) {
                    $uUpd['phone_verified'] = 1;
                }

                if (count($uUpd) > 1) {
                    $connCli->table('usuarios_cuenta')->where('id', $ownerId)->update($uUpd);
                }
            }

            // --- cuentas_cliente (opcional) ---
            if ($schemaCli->hasTable('cuentas_cliente') && $cuentaId !== '') {
                $cUpd = ['updated_at' => now()];
                if ($type === 'email' && $schemaCli->hasColumn('cuentas_cliente', 'email_verified_at')) {
                    $cUpd['email_verified_at'] = now();
                }
                if ($type === 'phone' && $schemaCli->hasColumn('cuentas_cliente', 'phone_verified_at')) {
                    $cUpd['phone_verified_at'] = now();
                }
                if ($type === 'email' && $schemaCli->hasColumn('cuentas_cliente', 'email_verified')) {
                    $cUpd['email_verified'] = 1;
                }
                if ($type === 'phone' && $schemaCli->hasColumn('cuentas_cliente', 'phone_verified')) {
                    $cUpd['phone_verified'] = 1;
                }

                if (count($cUpd) > 1) {
                    $connCli->table('cuentas_cliente')->where('id', $cuentaId)->update($cUpd);
                }
            }

            // Limpia pendientes en mysql_clientes (si existen)
            $this->purgeClientesVerificationArtifacts($accountId, $type, $cuentaId, $ownerId);

        } catch (\Throwable $e) {
            Log::warning('forceVerifyInMirror failed: ' . $e->getMessage(), [
                'account_id' => $accountId,
                'rfc'        => $rfcReal,
                'type'       => $type,
            ]);
        }
    }

    /**
     * Limpieza en mysql_admin para que no queden tokens/otps vigentes.
     * $type: email|phone
     */
    private function purgeAdminVerificationArtifacts(string $accountId, string $type): void
    {
        $type = strtolower(trim($type));
        try {
            if ($type === 'email' && Schema::connection($this->adminConn)->hasTable('email_verifications')) {
                DB::connection($this->adminConn)->table('email_verifications')
                    ->where('account_id', $accountId)
                    ->delete();
            }

            if ($type === 'phone' && Schema::connection($this->adminConn)->hasTable('phone_otps')) {
                DB::connection($this->adminConn)->table('phone_otps')
                    ->where('account_id', $accountId)
                    ->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('purgeAdminVerificationArtifacts: ' . $e->getMessage(), [
                'account_id' => $accountId,
                'type'       => $type,
            ]);
        }
    }

    /**
     * Limpieza en mysql_clientes (si existen tablas).
     * Nota: tu arquitectura ha tenido phone_otps también del lado clientes.
     */
    private function purgeClientesVerificationArtifacts(string $accountId, string $type, string $cuentaId = '', string $ownerId = ''): void
    {
        $type = strtolower(trim($type));
        try {
            $schemaCli = Schema::connection('mysql_clientes');
            $connCli   = DB::connection('mysql_clientes');

            // phone_otps en clientes puede colgarse de account_id o cuenta_id (dependiendo versión)
            if ($type === 'phone' && $schemaCli->hasTable('phone_otps')) {
                $q = $connCli->table('phone_otps');
                if ($schemaCli->hasColumn('phone_otps', 'account_id')) {
                    $q->where('account_id', $accountId);
                } elseif ($cuentaId !== '' && $schemaCli->hasColumn('phone_otps', 'cuenta_id')) {
                    $q->where('cuenta_id', $cuentaId);
                }
                $q->delete();
            }

            // email_verifications en clientes (si existiera) — por account_id o por email/usuario
            if ($type === 'email' && $schemaCli->hasTable('email_verifications')) {
                $q = $connCli->table('email_verifications');
                if ($schemaCli->hasColumn('email_verifications', 'account_id')) {
                    $q->where('account_id', $accountId);
                } elseif ($ownerId !== '' && $schemaCli->hasColumn('email_verifications', 'user_id')) {
                    $q->where('user_id', $ownerId);
                } elseif ($cuentaId !== '' && $schemaCli->hasColumn('email_verifications', 'cuenta_id')) {
                    $q->where('cuenta_id', $cuentaId);
                }
                $q->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('purgeClientesVerificationArtifacts: ' . $e->getMessage(), [
                'account_id' => $accountId,
                'type'       => $type,
            ]);
        }
    }

    /**
     * ✅ Normaliza duplicados en mysql_clientes.cuentas_cliente para un admin_account_id:
     * - winner: fila más “real” (tiene rfc no vacío) o rfc_padre con RFC-like; fallback: más reciente.
     * - winner queda con admin_account_id correcto + rfc/rfc_padre = RFC real (si existe columna).
     * - losers: admin_account_id = NULL y razón social marcada [DUPLICATE].
     *
     * Regresa la fila winner (object) o null si no hay filas con ese admin_account_id.
     */
    private function normalizeMirrorCuentaByAdminAccountId(int $adminAccountId, string $rfcReal): ?object
    {
        $rfcReal = strtoupper(trim($rfcReal));

        $schemaCli = Schema::connection('mysql_clientes');
        if (!$schemaCli->hasTable('cuentas_cliente')) return null;

        $connCli = DB::connection('mysql_clientes');

        $rows = $connCli->table('cuentas_cliente')
            ->where('admin_account_id', $adminAccountId)
            ->orderByDesc('updated_at')
            ->get(['id','admin_account_id','rfc','rfc_padre','razon_social','updated_at'])
            ->all();

        if (empty($rows)) return null;
        if (count($rows) === 1) return $rows[0];

        // winner: rfc lleno
        $winner = null;
        foreach ($rows as $r) {
            $rfc = strtoupper(trim((string)($r->rfc ?? '')));
            if ($rfc !== '') { $winner = $r; break; }
        }

        // fallback: rfc_padre RFC-like
        if (!$winner) {
            foreach ($rows as $r) {
                $rp = strtoupper(trim((string)($r->rfc_padre ?? '')));
                if ($rp !== '' && strlen($rp) >= 12 && preg_match('/[A-Z]/', $rp) && preg_match('/\d/', $rp)) {
                    $winner = $r; break;
                }
            }
        }

        // fallback final: el más reciente (ya viene orderByDesc)
        if (!$winner) $winner = $rows[0];

        // Curar winner
        $updW = ['updated_at' => now(), 'admin_account_id' => $adminAccountId];
        if ($schemaCli->hasColumn('cuentas_cliente', 'rfc') && $rfcReal !== '') {
            $updW['rfc'] = $rfcReal;
        }
        if ($schemaCli->hasColumn('cuentas_cliente', 'rfc_padre') && $rfcReal !== '') {
            $updW['rfc_padre'] = $rfcReal;
        }
        $connCli->table('cuentas_cliente')->where('id', $winner->id)->update($updW);

        // Reasociar usuarios al winner antes de desasociar duplicados
        if ($schemaCli->hasTable('usuarios_cuenta')) {
            foreach ($rows as $r) {
                if ((string) $r->id === (string) $winner->id) continue;

                try {
                    $connCli->table('usuarios_cuenta')
                        ->where('cuenta_id', (string) $r->id)
                        ->update([
                            'cuenta_id'  => (string) $winner->id,
                            'updated_at' => now(),
                        ]);
                } catch (\Throwable $e) {
                    // no-op
                }
            }
        }

        // Desasociar losers
        foreach ($rows as $r) {
            if ((string)$r->id === (string)$winner->id) continue;

            $upd = ['updated_at' => now()];
            if ($schemaCli->hasColumn('cuentas_cliente', 'admin_account_id')) {
                $upd['admin_account_id'] = null;
            }

            if ($schemaCli->hasColumn('cuentas_cliente', 'razon_social')) {
                $rs = trim((string)($r->razon_social ?? ''));
                if (!str_starts_with($rs, '[DUPLICATE]')) {
                    $upd['razon_social'] = '[DUPLICATE] ' . ($rs !== '' ? $rs : ('Cuenta ' . $adminAccountId));
                }
            }

            $connCli->table('cuentas_cliente')->where('id', $r->id)->update($upd);
        }

        // Re-leer winner ya curado
        return $connCli->table('cuentas_cliente')->where('id', $winner->id)->first();
    }

        // =========================================================
    // ✅ Normalizadores (evitan guardar basura y rompen menos UI)
    // =========================================================

    private function blankToNull(mixed $v): mixed
    {
        if ($v === null) return null;
        if (is_string($v)) {
            $s = trim($v);
            return $s === '' ? null : $s;
        }
        return $v;
    }

    private function normalizeEmailNullable(mixed $v): ?string
    {
        $s = strtolower(trim((string)($v ?? '')));
        if ($s === '') return null;
        return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : null;
    }

    private function normalizePhoneNullable(mixed $v): ?string
    {
        $s = trim((string)($v ?? ''));
        return $s === '' ? null : $s;
    }

    /**
     * Soporta:
     * - pro/free
     * - PRO / FREE
     * - PRO_MENSUAL / PRO_ANUAL (legacy)
     * - vacío => null
     */
    private function normalizePlanNullable(mixed $v): ?string
    {
        $raw = trim((string)($v ?? ''));
        if ($raw === '') return null;

        $s = strtoupper($raw);

        // Canon
        if ($s === 'PRO')  return 'PRO';
        if ($s === 'FREE') return 'FREE';

        // Legacy
        if ($s === 'PRO_MENSUAL') return 'PRO_MENSUAL';
        if ($s === 'PRO_ANUAL')   return 'PRO_ANUAL';

        // Si llega "monthly/yearly" por error, no lo metas como plan
        $sl = strtolower($raw);
        if (in_array($sl, ['monthly','yearly','mensual','anual','annual'], true)) {
            return null;
        }

        // fallback: guardamos lo que venga (pero normalizado a upper)
        return $s;
    }

    /**
     * Soporta:
     * - monthly/yearly
     * - mensual/anual
     * - annual/yearly
     */
    private function normalizeBillingCycleNullable(mixed $v): ?string
    {
        $s = strtolower(trim((string)($v ?? '')));
        if ($s === '') return null;

        if ($s === 'mensual') return 'monthly';
        if ($s === 'anual')   return 'yearly';
        if ($s === 'annual')  return 'yearly';
        if ($s === 'yearly')  return 'yearly';
        if ($s === 'monthly') return 'monthly';

        // si llega algo raro, no lo guardes
        return null;
    }

    private function normalizeBillingStatusNullable(mixed $v): ?string
    {
        $s = strtolower(trim((string)($v ?? '')));
        if ($s === '') return null;

        // Canon EN (legacy)
        $allowedEn = ['active','trial','grace','overdue','suspended','cancelled','demo'];

        // Canon ES (tu DB actual)
        $allowedEs = ['activa','prueba','gracia','vencida','suspendida','cancelada','demo'];

        if (in_array($s, $allowedEn, true) || in_array($s, $allowedEs, true)) {
            return $s; // guardamos tal cual para no romper tu DB actual
        }

        return null;
    }

    private function normalizeBoolInt(mixed $v, int $default = 0): int
    {
        if ($v === null) return $default;
        if (is_bool($v)) return $v ? 1 : 0;

        $s = strtolower(trim((string)$v));
        if ($s === '') return $default;

        if (in_array($s, ['1','true','yes','on'], true)) return 1;
        if (in_array($s, ['0','false','no','off'], true)) return 0;

        return $default;
    }

    /**
     * Convierte el payload de cuentas (admin.accounts) a "modo" para meta (mensual/anual).
     */
    private function cycleToModo(?string $cycle): ?string
    {
        $c = strtolower(trim((string)($cycle ?? '')));
        if ($c === 'monthly') return 'mensual';
        if ($c === 'yearly')  return 'anual';
        return null;
    }
 
}
