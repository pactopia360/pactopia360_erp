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
        $planFilter    = (string) $request->get('plan', '');
        $blocked       = $request->get('blocked');
        $billingStatus = $request->get('billing_status');

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
            $query->where('plan', $planFilter);
        }

        if (($blocked === '0' || $blocked === '1') && $this->hasCol($this->adminConn, 'accounts', 'is_blocked')) {
            $query->where('is_blocked', (int) $blocked);
        }

        if ($billingStatus !== null && $billingStatus !== '' && $this->hasCol($this->adminConn, 'accounts', 'billing_status')) {
            $query->where('billing_status', $billingStatus);
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
        }

        $billingStatuses = [
            'active'    => 'Activa',
            'trial'     => 'Prueba',
            'grace'     => 'Gracia',
            'overdue'   => 'Falta de pago',
            'suspended' => 'Suspendida',
            'cancelled' => 'Cancelada',
            'demo'      => 'Demo/QA',
        ];

        return view('admin.clientes.index', compact('rows', 'extras', 'creds', 'recipients', 'billingStatuses'));
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

        // 3) fallback: rfc_padre (en tu caso guarda RFC)
        if ($rfc !== '' && $schemaCli->hasColumn('cuentas_cliente', 'rfc_padre')) {
            $row = $connCli->table('cuentas_cliente')->whereRaw('UPPER(rfc_padre) = ?', [$rfc])->first();
            if ($row) return $row;
        }

        return null;
    }


    // ======================= GUARDAR (accounts) =======================
    public function save(string $key, Request $request): RedirectResponse
    {
        // ✅ key puede ser accounts.id (numérico) o accounts.rfc
        $acc = $this->requireAccount($key, ['id', $this->colRfcAdmin(), 'meta']);
        $accountId = (string) $acc->id;
        $rfcReal   = strtoupper(trim((string) ($acc->{$this->colRfcAdmin()} ?? '')));

        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();

        $rules = [
            'razon_social'      => 'nullable|string|max:190',
            'email'             => 'nullable|email|max:190',
            'phone'             => 'nullable|string|max:25',
            'plan'              => 'nullable|string|max:50',
            'billing_cycle'     => ['nullable', Rule::in(['monthly', 'yearly', '', null])],
            'billing_status'    => 'nullable|string|max:30',
            'next_invoice_date' => 'nullable|date',
            'is_blocked'        => 'nullable|boolean',
            'custom_amount_mxn' => 'nullable|numeric|min:0|max:99999999',
        ];
        $data = validator($request->all(), $rules)->validate();

        $payload = [
            'razon_social' => $data['razon_social'] ?? null,
            $emailCol      => isset($data['email']) ? strtolower((string) $data['email']) : null,
            $phoneCol      => $data['phone'] ?? null,
            'updated_at'   => now(),
        ];

        if ($this->hasCol($this->adminConn, 'accounts', 'plan')) {
            $payload['plan'] = $data['plan'] ?? null;
        }
        if ($this->hasCol($this->adminConn, 'accounts', 'billing_cycle')) {
            $payload['billing_cycle'] = $data['billing_cycle'] ?? null;
        }
        if ($this->hasCol($this->adminConn, 'accounts', 'billing_status')) {
            $payload['billing_status'] = $data['billing_status'] ?? null;
        }
        if ($this->hasCol($this->adminConn, 'accounts', 'next_invoice_date')) {
            $payload['next_invoice_date'] = $data['next_invoice_date'] ?? null;
        }
        if ($this->hasCol($this->adminConn, 'accounts', 'is_blocked')) {
            $payload['is_blocked'] = (int) ($data['is_blocked'] ?? 0);
        }

        $custom = isset($data['custom_amount_mxn']) ? (float) $data['custom_amount_mxn'] : null;
        $custom = ($custom !== null && $custom > 0.00001) ? round($custom, 2) : null;

        if ($custom !== null) {
            $accRow = DB::connection($this->adminConn)->table('accounts')->where('id', $accountId)->first(['meta']);
            $meta = $this->decodeMeta($accRow->meta ?? null);

            data_set($meta, 'billing.override.amount_mxn', $custom);
            data_set($meta, 'billing.override.enabled', true);

            if ($this->hasCol($this->adminConn, 'accounts', 'meta')) {
                $payload['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            }

            foreach (['custom_amount_mxn', 'override_amount_mxn', 'billing_amount_mxn', 'amount_mxn', 'license_amount_mxn'] as $col) {
                if ($this->hasCol($this->adminConn, 'accounts', $col)) {
                    $payload[$col] = $custom;
                    break;
                }
            }
        }

        DB::connection($this->adminConn)->table('accounts')->where('id', $accountId)->update($payload);

        // ✅ Legacy "clientes" debe ir por RFC real (no por id)
        if ($rfcReal !== '') {
            $this->upsertClienteLegacy($rfcReal, $payload);
        }

        // ✅ Espejo mysql_clientes usa rfc_padre = accounts.id (por tu implementación actual)
        $this->syncPlanToMirror($accountId, $payload);

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
                        data_get($meta, 'billing.year_amount_mxn'),
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

        // 1) Resolver destinatarios
        $input = $request->input('to', $request->input('recipients', ''));
        $to = $this->normalizeEmails($input);

        if (empty($to)) {
            $to = $this->resolveRecipientsForAccountAdminSide($accountId);
        }

        if (empty($to)) {
            return back()->with('error', 'No hay correos destinatarios válidos para enviar credenciales.');
        }

        // 2) Generar credenciales (reset password del OWNER por RFC REAL)
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

        // 3) Payload para plantilla admin ($p)
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

        // 4) Enviar (admin template preferido, fallback a cliente)
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

    $owner = UsuarioCuenta::on('mysql_clientes')->find($pack['owner']->id);
    abort_if(!$owner || !(int) $owner->activo, 404, 'Usuario owner no disponible');

    // ✅ Token 1-uso en cache
    $token = Str::random(32);

    Cache::put("impersonate.token.$token", [
        'owner_id'   => (string) $owner->id,
        'admin_id'   => (string) auth('admin')->id(),
        'rfc'        => $rfcReal,
        'account_id' => $accountId,
    ], now()->addMinutes(5));

    // ✅ URL firmada temporal hacia /cliente/impersonate/{token}
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


    // ======================= SYNC accounts -> clientes =======================
    public function syncToClientes(): RedirectResponse
    {
        if (!$this->legacyHasTable('clientes')) {
            return back()->with('ok', 'Sync omitido: no existe tabla clientes (legacy).');
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

        return back()->with('ok', "Sync clientes OK. Creados {$created}, Actualizados {$updated}.");
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

        // 2) meta override
        $meta = $this->decodeMeta($accRow->meta ?? null);
        $enabled = (bool) data_get($meta, 'billing.override.enabled', false);
        $amt = data_get($meta, 'billing.override.amount_mxn', null);
        if ($enabled && $amt !== null && (float) $amt > 0) {
            return round((float) $amt, 2);
        }

        // 3) default por plan/ciclo con inferencia cuando plan viene vacío
        $plan  = strtolower(trim((string) ($accRow->plan ?? '')));
        $cycle = strtolower(trim((string) ($accRow->billing_cycle ?? '')));
        $bs    = strtolower(trim((string) ($accRow->billing_status ?? '')));

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

            $owners = DB::connection('mysql_clientes')
                ->table('cuentas_cliente as c')
                ->join('usuarios_cuenta as u', 'u.cuenta_id', '=', 'c.id')
                ->whereIn('c.rfc_padre', array_map('strval', $accountIds))
                ->where(function ($q) use ($schemaCliHasTipo) {
                    $q->where('u.rol', 'owner');
                    if ($schemaCliHasTipo) $q->orWhere('u.tipo', 'owner');
                })
                ->select('c.rfc_padre as account_id', 'u.email')
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

        $ownerQ = $conn->table('usuarios_cuenta')
            ->where('cuenta_id', $cuenta->id)
            ->where(function ($q) use ($schemaCli) {
                $q->where('rol', 'owner');
                if ($schemaCli->hasColumn('usuarios_cuenta', 'tipo')) $q->orWhere('tipo', 'owner');
            })
            ->orderBy('created_at', 'asc');

        $owner = $ownerQ->first();
        if ($owner) return (object) ['id' => $owner->id, 'email' => $owner->email];

        $baseEmail = strtolower((string) ($acc->email ?: ('owner@' . $rfcReal . '.example.test')));

        $userSameEmailSameCuenta = $conn->table('usuarios_cuenta')
            ->where('cuenta_id', $cuenta->id)
            ->where('email', $baseEmail)
            ->first();

        if ($userSameEmailSameCuenta) {
            $upd = ['rol' => 'owner', 'activo' => 1, 'updated_at' => now()];
            if ($schemaCli->hasColumn('usuarios_cuenta', 'tipo')) $upd['tipo'] = 'owner';
            $conn->table('usuarios_cuenta')->where('id', $userSameEmailSameCuenta->id)->update($upd);

            return (object) ['id' => $userSameEmailSameCuenta->id, 'email' => $userSameEmailSameCuenta->email];
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
            'nombre'     => 'Owner ' . (string) ($acc->id ?? ''),
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
     * ✅ Asegura mirror por accounts.id (rfc_padre) y owner.
     *    $accountId: accounts.id
     *    $rfcReal: accounts.rfc (real)
     */
    private function ensureMirrorAndOwner(string $accountId, string $rfcReal): array
    {
        $accountId = trim($accountId);
        $rfcReal   = strtoupper(trim($rfcReal));

        abort_unless(
            Schema::connection('mysql_clientes')->hasTable('cuentas_cliente') &&
            Schema::connection('mysql_clientes')->hasTable('usuarios_cuenta'),
            500,
            'Faltan tablas espejo (cuentas_cliente / usuarios_cuenta) en mysql_clientes.'
        );

        $emailCol = $this->colEmail();
        $acc = DB::connection($this->adminConn)->table('accounts')->where('id', $accountId)->first([
            'id',
            $this->colRfcAdmin(),
            'razon_social',
            DB::raw("$emailCol as email"),
        ]);
        abort_if(!$acc, 404, 'Cuenta SOT (admin.accounts) no existe');

        $cuenta = DB::connection('mysql_clientes')->table('cuentas_cliente')
            ->where('rfc_padre', $acc->id)
            ->first();

        if (!$cuenta) {
            $cid = (string) Str::uuid();

            $payload = [
                'id'           => $cid,
                'rfc_padre'    => (string) $acc->id,
                'razon_social' => $acc->razon_social ?: ('Cuenta ' . (string) $acc->id),
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            $schemaCli = Schema::connection('mysql_clientes');

            if ($schemaCli->hasColumn('cuentas_cliente', 'codigo_cliente')) $payload['codigo_cliente'] = $this->genCodigoClienteEspejo();
            if ($schemaCli->hasColumn('cuentas_cliente', 'customer_no'))    $payload['customer_no'] = $this->nextCustomerNo();
            if ($schemaCli->hasColumn('cuentas_cliente', 'nombre_comercial')) $payload['nombre_comercial'] = $payload['razon_social'];
            if ($schemaCli->hasColumn('cuentas_cliente', 'activo')) $payload['activo'] = 1;
            if ($schemaCli->hasColumn('cuentas_cliente', 'email'))  $payload['email'] = $acc->email ?: null;

            if ($schemaCli->hasColumn('cuentas_cliente', 'telefono'))          $payload['telefono'] = null;
            if ($schemaCli->hasColumn('cuentas_cliente', 'plan'))              $payload['plan'] = null;
            if ($schemaCli->hasColumn('cuentas_cliente', 'billing_cycle'))     $payload['billing_cycle'] = null;
            if ($schemaCli->hasColumn('cuentas_cliente', 'next_invoice_date')) $payload['next_invoice_date'] = null;

            DB::connection('mysql_clientes')->table('cuentas_cliente')->insert($payload);
            $cuenta = (object) ['id' => $cid, 'rfc_padre' => (string) $acc->id];
        }

        $ownerObj = $this->upsertOwnerForCuenta($acc, $cuenta, $rfcReal !== '' ? $rfcReal : (string) $acc->id);

        return ['cuenta' => $cuenta, 'owner' => $ownerObj];
    }

    private function syncPlanToMirror(string $accountId, array $payload): void
    {
        if (!Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) return;

        $conn   = DB::connection('mysql_clientes');
        $cuenta = $conn->table('cuentas_cliente')->where('rfc_padre', $accountId)->first();

        if (!$cuenta) return;

        $schemaCli = Schema::connection('mysql_clientes');

        $upd = ['updated_at' => now()];

        $plan         = $payload['plan'] ?? null;
        $billingCycle = $payload['billing_cycle'] ?? null;
        $nextInvoice  = $payload['next_invoice_date'] ?? null;

        if ($schemaCli->hasColumn('cuentas_cliente', 'plan'))              $upd['plan'] = $plan;
        if ($schemaCli->hasColumn('cuentas_cliente', 'plan_actual'))       $upd['plan_actual'] = $plan;
        if ($schemaCli->hasColumn('cuentas_cliente', 'billing_cycle'))     $upd['billing_cycle'] = $billingCycle;
        if ($schemaCli->hasColumn('cuentas_cliente', 'next_invoice_date')) $upd['next_invoice_date'] = $nextInvoice;

        if (count($upd) <= 1) return;

        $conn->table('cuentas_cliente')->where('rfc_padre', $accountId)->update($upd);
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
 
}
