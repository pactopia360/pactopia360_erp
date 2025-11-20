<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use App\Support\ClientAuth;
use App\Services\ClientCredentials;

use App\Models\Cliente\UsuarioCuenta; // para impersonate

/**
 * Administración de clientes usando accounts (mysql_admin) como SOT.
 */
class ClientesController extends \App\Http\Controllers\Controller
{
    protected string $adminConn = 'mysql_admin';

      // ======================= LISTADO =======================
    public function index(Request $request): View
    {
        $q             = trim((string)$request->get('q',''));

        // Filtro opcional por plan desde la UI (NO usar $cuenta aquí)
        $planFilter    = (string) $request->get('plan',''); // '' = todos

        $blocked       = $request->get('blocked'); // "0" / "1"
        $billingStatus = $request->get('billing_status'); // active, overdue, etc.
        $perPage       = (int) $request->integer('per_page', 25);
        $perPage       = in_array($perPage, [10,25,50,100], true) ? $perPage : 25;

        // Orden permitido
        $sort    = (string) $request->get('sort','created_at');
        $dir     = strtolower((string) $request->get('dir','desc')) === 'asc' ? 'asc' : 'desc';
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
        if (!in_array($sort, $allowed, true)) $sort = 'created_at';

        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();
        $rfcCol   = $this->colRfcAdmin(); // detecta la columna RFC real o cae a 'id'

        $query = DB::connection($this->adminConn)->table('accounts');

        if ($q !== '') {
            $query->where(function ($qq) use ($q, $emailCol, $phoneCol) {
                $qq->where('id','like',"%{$q}%")
                   ->orWhere('razon_social','like',"%{$q}%")
                   ->orWhere($emailCol,'like',"%{$q}%")
                   ->orWhere($phoneCol,'like',"%{$q}%");
            });
        }

        // Filtro por plan (solo si la columna existe y el filtro viene en la URL)
        if ($planFilter !== '') {
            if (Schema::connection($this->adminConn)->hasColumn('accounts','plan')) {
                $query->where('plan',$planFilter);
            }
        }

        if ($blocked === '0' || $blocked === '1') {
            if (Schema::connection($this->adminConn)->hasColumn('accounts','is_blocked')) {
                $query->where('is_blocked',(int)$blocked);
            }
        }

        if ($billingStatus !== null && $billingStatus !== '') {
            if (Schema::connection($this->adminConn)->hasColumn('accounts','billing_status')) {
                $query->where('billing_status', $billingStatus);
            }
        }

        $query->select([
            'id',
            DB::raw("$rfcCol as rfc"),   // <<<< SIEMPRE manda un alias 'rfc'
            'razon_social',
            $emailCol.' as email',
            $phoneCol.' as phone',
            DB::raw(Schema::connection($this->adminConn)->hasColumn('accounts','plan')
                ? 'plan' : "'' as plan"),
            DB::raw(Schema::connection($this->adminConn)->hasColumn('accounts','billing_cycle')
                ? 'billing_cycle' : "NULL as billing_cycle"),
            DB::raw(Schema::connection($this->adminConn)->hasColumn('accounts','billing_status')
                ? 'billing_status' : "NULL as billing_status"),
            DB::raw(Schema::connection($this->adminConn)->hasColumn('accounts','next_invoice_date')
                ? 'next_invoice_date' : "NULL as next_invoice_date"),
            DB::raw(Schema::connection($this->adminConn)->hasColumn('accounts','is_blocked')
                ? 'is_blocked' : "0 as is_blocked"),
            DB::raw(Schema::connection($this->adminConn)->hasColumn('accounts','email_verified_at')
                ? 'email_verified_at' : "NULL as email_verified_at"),
            DB::raw(Schema::connection($this->adminConn)->hasColumn('accounts','phone_verified_at')
                ? 'phone_verified_at' : "NULL as phone_verified_at"),
            'created_at',
        ]);

        $query->orderBy($sort, $dir);

        $rows   = $query->paginate($perPage)->appends($request->query());
        $rfcs   = $rows->pluck('id')->all();
        $extras = $this->collectExtrasForRfcs($rfcs);
        $creds  = $this->collectCredsForRfcs($rfcs);

        // Opciones de estatus para la vista
        $billingStatuses = [
            'active'    => 'Activa',
            'trial'     => 'Prueba',
            'grace'     => 'Gracia',
            'overdue'   => 'Falta de pago',
            'suspended' => 'Suspendida',
            'cancelled' => 'Cancelada',
            'demo'      => 'Demo/QA',
        ];

        return view('admin.clientes.index', compact('rows','extras','creds','billingStatuses'));
    }

      // ======================= GUARDAR (accounts) =======================
    public function save(string $rfc, Request $request): RedirectResponse
    {
        $rfc = Str::of($rfc)->upper()->trim()->value();
        $this->assertExists($rfc);

        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();

        $rules = [
            'razon_social'      => 'nullable|string|max:190',
            'email'             => 'nullable|email|max:190',
            'phone'             => 'nullable|string|max:25',
            'plan'              => 'nullable|string|max:50',
            'billing_cycle'     => ['nullable', Rule::in(['monthly','yearly','',null])],
            'billing_status'    => 'nullable|string|max:30',
            'next_invoice_date' => 'nullable|date',
            'is_blocked'        => 'nullable|boolean',
        ];
        $data = validator($request->all(), $rules)->validate();

        $payload = [
            'razon_social' => $data['razon_social'] ?? null,
            $emailCol      => isset($data['email']) ? strtolower($data['email']) : null,
            $phoneCol      => $data['phone'] ?? null,
            'updated_at'   => now(),
        ];

        if (Schema::connection($this->adminConn)->hasColumn('accounts','plan')) {
            $payload['plan'] = $data['plan'] ?? null;
        }
        if (Schema::connection($this->adminConn)->hasColumn('accounts','billing_cycle')) {
            $payload['billing_cycle'] = $data['billing_cycle'] ?? null;
        }
        if (Schema::connection($this->adminConn)->hasColumn('accounts','billing_status')) {
            $payload['billing_status'] = $data['billing_status'] ?? null;
        }
        if (Schema::connection($this->adminConn)->hasColumn('accounts','next_invoice_date')) {
            $payload['next_invoice_date'] = $data['next_invoice_date'] ?? null;
        }
        if (Schema::connection($this->adminConn)->hasColumn('accounts','is_blocked')) {
            $payload['is_blocked'] = (int)($data['is_blocked'] ?? 0);
        }

        DB::connection($this->adminConn)->table('accounts')->where('id',$rfc)->update($payload);

        // Legacy clientes (tabla vieja 'clientes')
        $this->upsertClienteLegacy($rfc, $payload);

        // >>> NUEVO: sincronizar plan/billing al espejo de cliente (cuentas_cliente)
        $this->syncPlanToMirror($rfc, $payload);

        return back()->with('ok','Datos guardados.');
    }


    // ======================= VERIFICACIÓN / OTP =======================
    public function resendEmailVerification(string $rfc): RedirectResponse
    {
        $emailCol = $this->colEmail();
        $acc = $this->getAccount($rfc, ['id', DB::raw("$emailCol as email")]);
        abort_if(!$acc || !$acc->email, 404, 'Cuenta o email no disponible');

        $token = Str::random(40);
        DB::connection($this->adminConn)->table('email_verifications')->insert([
            'account_id' => $acc->id, 'email' => strtolower($acc->email),
            'token'      => $token, 'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
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

    public function sendPhoneOtp(string $rfc, Request $request): RedirectResponse
    {
        $request->validate([
            'channel' => 'required|in:sms,whatsapp,wa',
            'phone'   => 'nullable|string|max:25',
        ]);

        $phoneCol = $this->colPhone();
        $emailCol = $this->colEmail();

        $acc = $this->getAccount($rfc, [
            'id',
            DB::raw("$emailCol as email"),
            DB::raw("$phoneCol as phone")
        ]);
        abort_if(!$acc, 404, 'Cuenta no encontrada');

        // Si se pasó un nuevo teléfono, actualizarlo
        $newPhone = trim((string) $request->get('phone', ''));
        if ($newPhone !== '') {
            DB::connection($this->adminConn)->table('accounts')
                ->where('id', $acc->id)
                ->update([$phoneCol => $newPhone, 'updated_at' => now()]);
            $acc->phone = $newPhone;
        }
        abort_if(empty($acc->phone), 422, 'No hay teléfono registrado.');

        // Generar OTP de 6 dígitos
        $code   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpCol = $this->colOtp();

        $payload = [
            'account_id' => $acc->id,
            'phone'      => $acc->phone,
            'channel'    => $request->channel === 'whatsapp' ? 'wa' : $request->channel,
            'attempts'   => Schema::connection($this->adminConn)->hasColumn('phone_otps', 'attempts') ? 0 : null,
            'used_at'    => null,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Columna principal detectada
        $payload[$otpCol] = $code;

        // Si existen AMBAS columnas (otp y code), llena las dos
        $hasOtp  = Schema::connection($this->adminConn)->hasColumn('phone_otps', 'otp');
        $hasCode = Schema::connection($this->adminConn)->hasColumn('phone_otps', 'code');
        if ($hasOtp && $hasCode) {
            $payload['otp']  = $code;
            $payload['code'] = $code;
        }

        DB::connection($this->adminConn)->table('phone_otps')->insert($payload);

        // Enviar por correo (QA)
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

    public function forceEmailVerified(string $rfc): RedirectResponse
    {
        if (Schema::connection($this->adminConn)->hasColumn('accounts', 'email_verified_at')) {
            DB::connection($this->adminConn)->table('accounts')
                ->where('id', $rfc)->update(['email_verified_at' => now(), 'updated_at' => now()]);
        }
        return back()->with('ok', 'Email marcado como verificado.');
    }

    public function forcePhoneVerified(string $rfc): RedirectResponse
    {
        if (Schema::connection($this->adminConn)->hasColumn('accounts', 'phone_verified_at')) {
            DB::connection($this->adminConn)->table('accounts')
                ->where('id', $rfc)->update(['phone_verified_at' => now(), 'updated_at' => now()]);
        }

        try {
            if (class_exists(\App\Http\Controllers\Cliente\VerificationController::class)) {
                app(\App\Http\Controllers\Cliente\VerificationController::class)
                    ->finalizeActivationAndSendCredentialsByRfc($rfc);
            }
        } catch (\Throwable $e) {
            Log::warning('finalizeActivation: ' . $e->getMessage());
        }

        return back()->with('ok', 'Teléfono verificado. Activación final intentada.');
    }

    public function resetPassword(Request $request, string $rfcOrId)
    {
        // -------- 1) Resolver RFC real (puede venir ID o RFC)
        $input = trim($rfcOrId);
        $upper = \Illuminate\Support\Str::upper($input);

        $looksRfc = (bool) preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', $upper);
        if (!$looksRfc) {
            $acc = \DB::connection('mysql_admin')->table('accounts')
                ->where('id', $input)
                ->orWhereRaw('UPPER(COALESCE(rfc,"")) = ?', [$upper])
                ->first();

            if (!$acc || empty($acc->rfc)) {
                $payload = ['ok' => false, 'error' => 'No pude resolver el RFC de la cuenta.'];
                if ($request->expectsJson() || $request->wantsJson() || $request->ajax() || $request->query('format') === 'json') {
                    return response()->json($payload, 404);
                }
                return $this->backOrJson($request, $payload, 404);
            }

            $upper = \Illuminate\Support\Str::upper($acc->rfc);
        }

        // -------- 2) Reset vía servicio
        $res = \App\Services\ClientCredentials::resetOwnerByRfc($upper);

        // -------- 3) Salidas forzadas por formato
        $wantJson     = $request->expectsJson() || $request->wantsJson() || $request->ajax() || $request->query('format') === 'json';
        $wantPretty   = $request->query('format') === 'pretty';

        $methodIsGet  = $request->isMethod('GET');
        $wantPretty   = $wantPretty || ($methodIsGet && !$wantJson && !$request->has('format'));

        if ($wantJson) {
            return response()->json($res, $res['ok'] ? 200 : 422);
        }

        if ($wantPretty) {
            // Página mínima con pretty print (útil al pegar la URL en el navegador)
            $code = $res['ok'] ? 200 : 422;
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

        // -------- 4) Flujo normal web (redirección + flashes)
        if (!$res['ok']) {
            return $this->backOrJson($request, ['error' => $res['error'] ?? 'No se pudo resetear'], 500);
        }

        // Persistir temporal para la UI (15 min)
        foreach ([$upper, strtolower($upper)] as $key) {
            session()->flash("tmp_pass.$key", $res['pass']);
            session()->flash("tmp_user.$key", $res['email']);
            cache()->put("tmp_pass.$key", $res['pass'], now()->addMinutes(15));
            cache()->put("tmp_user.$key", $res['email'], now()->addMinutes(15));
            \Cookie::queue(\Cookie::make("p360_tmp_pass_{$key}", $res['pass'], 10, null, null, false, false, false, 'Lax'));
            \Cookie::queue(\Cookie::make("p360_tmp_user_{$key}", $res['email'], 10, null, null, false, false, false, 'Lax'));
        }

        return back()
            ->with('ok', 'Contraseña temporal generada para el OWNER.')
            ->with('tmp_password', $res['pass'])
            ->with('tmp_user_email', $res['email']);
    }

    /**
     * Helper para responder back/JSON manteniendo un solo punto de salida.
     */
    private function backOrJson(Request $request, array $data, int $status = 400)
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json($data + ['ok' => false], $status);
        }
        $key = isset($data['error']) ? 'error' : 'info';
        return back()->withErrors([$key => $data[$key] ?? 'Operación no completada.']);
    }

    public function emailCredentials(string $rfc): RedirectResponse
    {
        $emailCol = $this->colEmail();
        $acc = $this->getAccount($rfc, ['id', DB::raw("$emailCol as email")]);
        abort_if(!$acc || !$acc->email, 404, 'Cuenta sin correo');

        try {
            if (view()->exists('emails.cliente.credentials')) {
                Mail::send('emails.cliente.credentials', ['email' => $acc->email], function ($m) use ($acc) {
                    $m->to($acc->email)->subject('Acceso · Pactopia360');
                });
            } else {
                Mail::raw(
                    "Hola.\n\nTu acceso a Pactopia360 está listo.\nCorreo: {$acc->email}\n\nSi no recuerdas tu contraseña, usa “Olvidé mi contraseña”.\n\n— Equipo Pactopia360",
                    function ($m) use ($acc) {
                        $m->to($acc->email)->subject('Acceso · Pactopia360');
                    }
                );
            }
        } catch (\Throwable $e) {
            Log::warning('emailCredentials: ' . $e->getMessage());
        }

        if (Schema::connection($this->adminConn)->hasTable('credential_logs')) {
            DB::connection($this->adminConn)->table('credential_logs')->insert([
                'account_id' => $acc->id, 'action' => 'email',
                'meta'       => json_encode(['by' => auth('admin')->id()]),
                'sent_at'    => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return back()->with('ok', 'Credenciales enviadas por correo.');
    }

    // ====== IMPERSONATE: Iniciar sesión como el OWNER en el guard web ======
    public function impersonate(string $rfc): RedirectResponse
    {
        // Asegura espejo + owner (autoprovisiona si falta)
        $pack  = $this->ensureMirrorAndOwner($rfc);

        $owner = UsuarioCuenta::on('mysql_clientes')->find($pack['owner']->id);
        abort_if(!$owner || !$owner->activo, 404, 'Usuario owner no disponible');

        // Marca que es impersonate (por si quieres mostrar banner)
        session([
            'impersonated_by_admin' => auth('admin')->id(),
            'impersonated_rfc'      => Str::of($rfc)->upper()->trim()->value(),
        ]);

        // Login en guard web
        try {
            Auth::guard('web')->logout();
        } catch (\Throwable $e) {
        }
        Auth::guard('web')->login($owner, false);

        return redirect()->route('cliente.home');
    }

    /** Salir del modo impersonate */
    public function impersonateStop(): RedirectResponse
    {
        try {
            Auth::guard('web')->logout();
        } catch (\Throwable $e) {
        }
        session()->forget(['impersonated_by_admin', 'impersonated_rfc']);
        return redirect()->route('admin.clientes.index')->with('ok', 'Sesión de cliente finalizada.');
    }

    // ======================= SYNC accounts → clientes =======================
    public function syncToClientes(): RedirectResponse
    {
        if (!Schema::hasTable('clientes')) {
            return back()->with('ok', 'Sync omitido: no existe tabla clientes.');
        }

        $created = 0;
        $updated = 0;
        DB::connection($this->adminConn)->table('accounts')
            ->select(['id', 'razon_social'])
            ->orderBy('id')->chunk(500, function ($rows) use (&$created, &$updated) {
                foreach ($rows as $r) {
                    $rfc = strtoupper(trim($r->id));
                    $rs  = $r->razon_social ?: ('Cuenta ' . $rfc);
                    $exists = DB::table('clientes')->where('rfc', $rfc)->first();
                    if ($exists) {
                        DB::table('clientes')->where('rfc', $rfc)->update([
                            'razon_social'    => $rs,
                            'nombre_comercial'=> $rs,
                            'updated_at'      => now()
                        ]);
                        $updated++;
                    } else {
                        DB::table('clientes')->insert([
                            'codigo'           => $this->genCodigoCliente(), // requerido
                            'razon_social'     => $rs,
                            'nombre_comercial' => $rs,
                            'rfc'              => $rfc,
                            'activo'           => 1,
                            'created_at'       => now(),
                            'updated_at'       => now()
                        ]);
                        $created++;
                    }
                }
            });

        return back()->with('ok', "Sync clientes OK. Creados {$created}, Actualizados {$updated}.");
    }

    /**
     * Acciones masivas sobre accounts (SOT).
     * Soporta:
     * - action=email_verify   -> Re-genera token de verificación de correo (si hay email)
     * - action=otp_sms        -> Genera OTP (SMS por defecto) si hay teléfono
     * - action=block          -> Marca is_blocked=1 (si la columna existe)
     * - action=unblock        -> Marca is_blocked=0 (si la columna existe)
     */
    public function bulk(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'    => 'required|string',                // "RFC1,RFC2,..."
            'action' => 'required|in:email_verify,otp_sms,block,unblock',
            'channel'=> 'nullable|in:sms,whatsapp,wa',    // para otp_sms
        ]);

        $action  = $request->string('action')->toString();
        $channel = $request->string('channel')->toString() ?: 'sms';

        // Normaliza lista de RFCs (IDs en accounts)
        $rfcs = collect(explode(',', $request->string('ids')))
            ->map(fn($x) => strtoupper(trim((string) $x)))
            ->filter()
            ->unique()
            ->values();

        if ($rfcs->isEmpty()) {
            return back()->with('error', 'No hay IDs válidos para procesar.');
        }

        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();
        $otpCol   = $this->colOtp();

        $hasBlockedCol = Schema::connection($this->adminConn)->hasColumn('accounts', 'is_blocked');
        $hasEmailVer   = Schema::connection($this->adminConn)->hasTable('email_verifications');
        $hasPhoneOtps  = Schema::connection($this->adminConn)->hasTable('phone_otps');

        $ok = 0;
        $skip = 0;
        $err = 0;
        $skips = [];
        $errs  = [];

        foreach ($rfcs as $rfc) {
            try {
                // Trae registro base
                $acc = $this->getAccount($rfc, [
                    'id', 'razon_social',
                    DB::raw("$emailCol as email"),
                    DB::raw("$phoneCol as phone"),
                ]);

                if (!$acc) {
                    $skip++;
                    $skips[] = "$rfc: cuenta no existe";
                    continue;
                }

                switch ($action) {

                    case 'block':
                        if (!$hasBlockedCol) {
                            $skip++;
                            $skips[] = "$rfc: columna is_blocked no existe";
                            break;
                        }
                        DB::connection($this->adminConn)->table('accounts')
                            ->where('id', $acc->id)
                            ->update(['is_blocked' => 1, 'updated_at' => now()]);
                        $ok++;
                        break;

                    case 'unblock':
                        if (!$hasBlockedCol) {
                            $skip++;
                            $skips[] = "$rfc: columna is_blocked no existe";
                            break;
                        }
                        DB::connection($this->adminConn)->table('accounts')
                            ->where('id', $acc->id)
                            ->update(['is_blocked' => 0, 'updated_at' => now()]);
                        $ok++;
                        break;

                    case 'email_verify':
                        if (!$hasEmailVer) {
                            $skip++;
                            $skips[] = "$rfc: tabla email_verifications no existe";
                            break;
                        }
                        if (empty($acc->email)) {
                            $skip++;
                            $skips[] = "$rfc: sin email";
                            break;
                        }
                        $token = Str::random(40);
                        DB::connection($this->adminConn)->table('email_verifications')->insert([
                            'account_id' => $acc->id,
                            'email'      => strtolower($acc->email),
                            'token'      => $token,
                            'expires_at' => now()->addDay(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        // Envío por correo opcional (silencioso, QA)
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
                        if (!$hasPhoneOtps) {
                            $skip++;
                            $skips[] = "$rfc: tabla phone_otps no existe";
                            break;
                        }
                        if (empty($acc->phone)) {
                            $skip++;
                            $skips[] = "$rfc: sin teléfono";
                            break;
                        }
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
                        // Si existen ambas columnas, llena ambas
                        $hasOtp = Schema::connection($this->adminConn)->hasColumn('phone_otps', 'otp');
                        $hasCode = Schema::connection($this->adminConn)->hasColumn('phone_otps', 'code');
                        if ($hasOtp && $hasCode) {
                            $payload['otp']  = $code;
                            $payload['code'] = $code;
                        }
                        DB::connection($this->adminConn)->table('phone_otps')->insert($payload);

                        // Aviso QA por correo (si hay)
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
                $errs[] = "$rfc: " . $e->getMessage();
                Log::warning('bulk: ' . $e->getMessage(), ['rfc' => $rfc, 'action' => $action]);
            }
        }

        $msg = "Bulk '{$action}': OK {$ok}" . ($skip ? " · Saltados {$skip}" : "") . ($err ? " · Errores {$err}" : "");
        if ($skip) {
            $msg .= "\nSaltos: " . implode('; ', array_slice($skips, 0, 5)) . ($skip > 5 ? '...' : '');
        }
        if ($err) {
            $msg .= "\nErrores: " . implode('; ', array_slice($errs, 0, 5)) . ($err > 5 ? '...' : '');
        }

        return back()->with($err ? 'error' : 'ok', $msg);
    }

    // ======================= HELPERS =======================
    private function colEmail(): string
    {
        foreach (['correo_contacto', 'email'] as $c) {
            if (Schema::connection($this->adminConn)->hasColumn('accounts', $c)) {
                return $c;
            }
        }
        return 'email';
    }
    private function colPhone(): string
    {
        foreach (['telefono', 'phone'] as $c) {
            if (Schema::connection($this->adminConn)->hasColumn('accounts', $c)) {
                return $c;
            }
        }
        return 'telefono';
    }
    private function colPwd(): string
    {
        foreach (['password_hash', 'password'] as $c) {
            if (Schema::connection($this->adminConn)->hasColumn('accounts', $c)) {
                return $c;
            }
        }
        return 'password';
    }
    private function colOtp(): string
    {
        if (Schema::connection($this->adminConn)->hasColumn('phone_otps', 'otp')) {
            return 'otp';
        }
        if (Schema::connection($this->adminConn)->hasColumn('phone_otps', 'code')) {
            return 'code';
        }
        return 'otp';
    }

    private function getAccount(string $rfc, array $select = ['*'])
    {
        $rfc = Str::of($rfc)->upper()->trim()->value();
        return DB::connection($this->adminConn)->table('accounts')->where('id', $rfc)->select($select)->first();
    }
    private function assertExists(string $rfc): void
    {
        $ok = DB::connection($this->adminConn)->table('accounts')->where('id', $rfc)->exists();
        abort_if(!$ok, 404, 'Cuenta no encontrada');
    }
    private function collectExtrasForRfcs(array $rfcs): array
    {
        if (empty($rfcs)) {
            return [];
        }
        $fmt = static function ($v) {
            if (empty($v)) {
                return null;
            }
            try {
                return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i');
            } catch (\Throwable $e) {
                return (string) $v;
            }
        };
        $otpCol = $this->colOtp();

        $tokens = DB::connection($this->adminConn)->table('email_verifications')
            ->whereIn('account_id', $rfcs)->select('account_id as rfc', 'token', 'expires_at')
            ->orderBy('id', 'desc')->get()->groupBy('rfc')->map(fn($g) => $g->first());

        $otps = DB::connection($this->adminConn)->table('phone_otps')
            ->whereIn('account_id', $rfcs)
            ->select('account_id as rfc', DB::raw(($otpCol === 'otp' ? 'otp' : 'code') . ' as code'), 'channel', 'expires_at')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('rfc')
            ->map(fn($g) => $g->first());

        $hasCredLogs = Schema::connection($this->adminConn)->hasTable('credential_logs');
        $logs = $hasCredLogs
            ? DB::connection($this->adminConn)->table('credential_logs')
                ->whereIn('account_id', $rfcs)->select('account_id as rfc', 'sent_at')
                ->orderBy('id', 'desc')->get()->groupBy('rfc')->map(fn($g) => $g->first())
            : collect();

        $out = [];
        foreach ($rfcs as $rfc) {
            $tok = $tokens->get($rfc);
            $otp = $otps->get($rfc);
            $log = $logs->get($rfc);
            $out[$rfc] = [
                'email_token'      => $tok->token ?? null,
                'email_expires_at' => $fmt($tok->expires_at ?? null),
                'otp_code'         => $otp->code ?? null,
                'otp_channel'      => $otp->channel ?? null,
                'otp_expires_at'   => $fmt($otp->expires_at ?? null),
                'cred_last_sent_at'=> $fmt($log->sent_at ?? null),
            ];
        }
        return $out;
    }

    /** Dueño (email) y contraseña temporal desde sesión/cache (con fallback) */
    private function collectCredsForRfcs(array $rfcs): array
    {
        if (empty($rfcs)) {
            return [];
        }

        $schemaCliHasTipo = Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta', 'tipo');

        // emails owner (usa JOIN; solo filtra por u.tipo si existe la columna)
        $ownersQ = DB::connection('mysql_clientes')
            ->table('cuentas_cliente as c')
            ->join('usuarios_cuenta as u', 'u.cuenta_id', '=', 'c.id')
            ->whereIn('c.rfc_padre', $rfcs)
            ->where(function ($q) use ($schemaCliHasTipo) {
                $q->where('u.rol', 'owner');
                if ($schemaCliHasTipo) {
                    $q->orWhere('u.tipo', 'owner');
                }
            })
            ->select('c.rfc_padre as rfc', 'u.email')
            ->orderBy('u.created_at', 'asc');

        $owners = $ownersQ->get()->groupBy('rfc')->map(fn($g) => $g->first());

        // Último reset global (por si todo lo demás falla)
        $last = session('tmp_last'); // ['key'=>RFC,'user'=>..,'pass'=>..]

        $out = [];
        foreach ($rfcs as $rfc) {
            $rfcU = strtoupper(trim((string) $rfc));
            $rfcL = strtolower($rfcU);

            $emailOwner = optional($owners->get($rfc))->email;

            // usuario override (session/cache)
            $userOverride =
                session()->get("tmp_user.$rfcU")
            ?? session()->get("tmp_user.$rfcL")
            ?? Cache::get("tmp_user.$rfcU")
            ?? Cache::get("tmp_user.$rfcL")
            ?? (($last['key'] ?? null) === $rfcU ? ($last['user'] ?? null) : null);

            // pass temporal (session/cache)
            $temp =
                session()->get("tmp_pass.$rfcU")
            ?? session()->get("tmp_pass.$rfcL")
            ?? Cache::get("tmp_pass.$rfcU")
            ?? Cache::get("tmp_pass.$rfcL")
            ?? (($last['key'] ?? null) === $rfcU ? ($last['pass'] ?? null) : null);

            $out[$rfc] = [
                'owner_email' => $userOverride ?: $emailOwner,
                'temp_pass'   => $temp,
            ];
        }

        return $out;
    }

    private function upsertClienteLegacy(string $rfc, array $payload): void
    {
        if (!Schema::hasTable('clientes')) {
            return;
        }
        $rs = trim((string) ($payload['razon_social'] ?? ''));
        if ($rs === '') {
            return;
        }

        $exists = DB::table('clientes')->where('rfc', $rfc)->first();
        if ($exists) {
            DB::table('clientes')->where('rfc', $rfc)->update([
                'razon_social'    => $rs,
                'nombre_comercial'=> $rs,
                'updated_at'      => now()
            ]);
        } else {
            DB::table('clientes')->insert([
                'codigo'           => $this->genCodigoCliente(),
                'razon_social'     => $rs,
                'nombre_comercial' => $rs,
                'rfc'              => $rfc,
                'activo'           => 1,
                'created_at'       => now(),
                'updated_at'       => now()
            ]);
        }
    }

    private function genCodigoCliente(): string
    {
        do {
            $cand = 'C' . base_convert((string) time(), 10, 36) . strtoupper(Str::random(6));
        } while (DB::table('clientes')->where('codigo', $cand)->exists());

        return $cand;
    }

    /**
     * Genera un código para columna `codigo_cliente` de la tabla espejo `cuentas_cliente`.
     * Formato: CC + base36(epoch) + 4 letras.
     */
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

    /**
     * Genera un consecutivo numérico para la columna `customer_no` (VARCHAR/INT).
     * Intenta MAX(CAST(customer_no AS UNSIGNED))+1 y asegura unicidad.
     */
    private function nextCustomerNo(): string
    {
        $conn = DB::connection('mysql_clientes');
        $tbl  = 'cuentas_cliente';
        $col  = 'customer_no';

        // Obtiene el máximo numérico actual (ignora valores no numéricos)
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

    /**
     * Si el email ya existe en usuarios_cuenta y NO pertenece a la misma cuenta,
     * genera una variante única con tag +rfc, +rfc2, +rfc3, ...
     */
    private function ensureUniqueUserEmail(string $email, string $cuentaId, string $rfc): string
    {
        $conn = DB::connection('mysql_clientes');
        $email = strtolower(trim($email));
        if ($email === '') {
            $email = 'owner+' . $rfc . '@example.test';
        }

        $exists = $conn->table('usuarios_cuenta')->where('email', $email)->first();
        if ($exists) {
            if ((string) $exists->cuenta_id === (string) $cuentaId) {
                return $email;
            }
        }

        [$local, $domain] = explode('@', $email, 2) + [null, null];
        if (!$domain) {
            $local = 'owner';
            $domain = 'example.test';
        }

        $tagBase = strtolower($rfc);
        $cleanLocal = preg_replace('/[^a-z0-9._+-]/i', '', (string) $local);
        if ($cleanLocal === '') {
            $cleanLocal = 'owner';
        }

        $cleanLocal = substr($cleanLocal, 0, 48);

        $i = 0;
        do {
            $suffix = $i === 0 ? $tagBase : ($tagBase . $i);
            $candidateLocal = $cleanLocal . '+' . $suffix;
            $candidateLocal = substr($candidateLocal, 0, 64);
            $candidate = $candidateLocal . '@' . $domain;
            $exists = $conn->table('usuarios_cuenta')->where('email', $candidate)->exists();
            $i++;
        } while ($exists && $i < 200);

        if ($exists) {
            $candidate = $cleanLocal . '+' . base_convert((string) time(), 10, 36) . Str::random(4) . '@' . $domain;
            $candidate = strtolower($candidate);
        }

        return strtolower($candidate);
    }

    /**
     * Sube a OWNER un usuario existente de la misma cuenta (si lo hay con ese email),
     * o crea uno nuevo con email único.
     * Devuelve objeto (id,email) y flashea tmp_pass si crea.
     */
    private function upsertOwnerForCuenta(object $acc, object $cuenta, string $rfc): object
    {
        $conn = DB::connection('mysql_clientes');
        $schemaCli = Schema::connection('mysql_clientes');

        // ¿Ya hay owner?
        $ownerQ = $conn->table('usuarios_cuenta')
            ->where('cuenta_id', $cuenta->id)
            ->where(function ($q) use ($schemaCli) {
                $q->where('rol', 'owner');
                if ($schemaCli->hasColumn('usuarios_cuenta', 'tipo')) {
                    $q->orWhere('tipo', 'owner');
                }
            })
            ->orderBy('created_at', 'asc');

        $owner = $ownerQ->first();
        if ($owner) {
            return (object) ['id' => $owner->id, 'email' => $owner->email];
        }

        // ¿Hay un usuario con el mismo email en esta misma cuenta?
        $baseEmail = strtolower($acc->email ?: ('owner@' . $rfc . '.example.test'));
        $userSameEmailSameCuenta = $conn->table('usuarios_cuenta')
            ->where('cuenta_id', $cuenta->id)
            ->where('email', $baseEmail)
            ->first();

        if ($userSameEmailSameCuenta) {
            // Promover a OWNER
            $upd = ['rol' => 'owner', 'activo' => 1, 'updated_at' => now()];
            if ($schemaCli->hasColumn('usuarios_cuenta', 'tipo')) {
                $upd['tipo'] = 'owner';
            }
            $conn->table('usuarios_cuenta')->where('id', $userSameEmailSameCuenta->id)->update($upd);
            return (object) ['id' => $userSameEmailSameCuenta->id, 'email' => $userSameEmailSameCuenta->email];
        }

        // Si el email base ya está tomado por OTRA cuenta, generamos variante única
        $email = $this->ensureUniqueUserEmail($baseEmail, $cuenta->id, $rfc);

        // Crear OWNER nuevo
        $uid = (string) \Str::uuid();

        // Temporal amigable
        $tmpRaw = \Str::password(12);
        $tmp    = preg_replace('/[^A-Za-z0-9@#\-\_\.\!\?]/', '', $tmpRaw);
        if ($tmp === '' || strlen($tmp) < 8) {
            $tmp = 'P360#' . \Str::random(8);
        }
        // Hash SIN normalizar
        $hash = \Hash::make($tmp);

        $payloadU = [
            'id'         => $uid,
            'cuenta_id'  => $cuenta->id,
            'rol'        => 'owner',
            'nombre'     => 'Owner ' . $acc->id,
            'email'      => $email,
            'password'   => $hash,     // hash en password
            'activo'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($schemaCli->hasColumn('usuarios_cuenta', 'tipo')) {
            $payloadU['tipo'] = 'owner';
        }
        if ($schemaCli->hasColumn('usuarios_cuenta', 'must_change_password')) {
            $payloadU['must_change_password'] = 1;
        }
        if ($schemaCli->hasColumn('usuarios_cuenta', 'sync_version')) {
            $payloadU['sync_version'] = 1;
        }
        if ($schemaCli->hasColumn('usuarios_cuenta', 'ultimo_login_at')) {
            $payloadU['ultimo_login_at'] = null;
        }
        if ($schemaCli->hasColumn('usuarios_cuenta', 'ip_ultimo_login')) {
            $payloadU['ip_ultimo_login'] = null;
        }
        // También guarda en password_temp si la columna existe (compatibilidad)
        if ($schemaCli->hasColumn('usuarios_cuenta', 'password_temp')) {
            $payloadU['password_temp'] = $hash;
        }

        $conn->table('usuarios_cuenta')->insert($payloadU);

        // Mostrar credenciales en la UI admin (por RFC)
        session()->flash("tmp_pass.$rfc", $tmp);
        session()->flash("tmp_user.$rfc",  $email);

        return (object) ['id' => $uid, 'email' => $email];
    }

    // helper RFC admin
    private function colRfcAdmin(): string
    {
        // ajusta el orden según tu esquema real
        foreach (['rfc', 'rfc_padre', 'tax_id', 'rfc_cliente'] as $c) {
            if (Schema::connection($this->adminConn)->hasColumn('accounts', $c)) {
                return $c;
            }
        }
        // fallback (muestra id si no hay columna RFC)
        return 'id';
    }

    /**
     * Asegura que existan la cuenta espejo y el OWNER en mysql_clientes.
     * - Si no existe cuentas_cliente -> la crea llenando SIEMPRE `codigo_cliente` y `customer_no` si existen.
     * - Si no existe OWNER -> lo crea (activo=1) y flashea una contraseña temporal.
     * Devuelve ['cuenta'=>obj, 'owner'=>obj(id,email)].
     */
    private function ensureMirrorAndOwner(string $rfc): array
    {
        $rfc = Str::of($rfc)->upper()->trim()->value();

        // Requisitos mínimos
        abort_unless(
            Schema::connection('mysql_clientes')->hasTable('cuentas_cliente') &&
            Schema::connection('mysql_clientes')->hasTable('usuarios_cuenta'),
            500,
            'Faltan tablas espejo (cuentas_cliente / usuarios_cuenta) en mysql_clientes.'
        );

        // Traer cuenta SOT (admin.accounts)
        $emailCol = $this->colEmail();
        $acc = $this->getAccount($rfc, ['id', 'razon_social', DB::raw("$emailCol as email")]);
        abort_if(!$acc, 404, 'Cuenta SOT (admin.accounts) no existe');

        // Cuenta espejo
        $cuenta = DB::connection('mysql_clientes')
            ->table('cuentas_cliente')
            ->where('rfc_padre', $acc->id)
            ->first();

        if (!$cuenta) {
            $cid = (string) Str::uuid();

            $payload = [
                'id'           => $cid,
                'rfc_padre'    => $acc->id,
                'razon_social' => $acc->razon_social ?: ('Cuenta ' . $acc->id),
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            // Llenar SIEMPRE si existen en el esquema
            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'codigo_cliente')) {
                $payload['codigo_cliente'] = $this->genCodigoClienteEspejo();
            }
            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'customer_no')) {
                $payload['customer_no'] = $this->nextCustomerNo();
            }

            // Campos opcionales comunes
            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'nombre_comercial')) {
                $payload['nombre_comercial'] = $acc->razon_social ?: ('Cuenta ' . $acc->id);
            }
            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'activo')) {
                $payload['activo'] = 1;
            }
            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'email')) {
                $payload['email'] = $acc->email ?: null;
            }
            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'telefono')) {
                $payload['telefono'] = null;
            }
            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'plan')) {
                $payload['plan'] = null;
            }
            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'billing_cycle')) {
                $payload['billing_cycle'] = null;
            }
            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'next_invoice_date')) {
                $payload['next_invoice_date'] = null;
            }

            DB::connection('mysql_clientes')->table('cuentas_cliente')->insert($payload);
            $cuenta = (object) ['id' => $cid, 'rfc_padre' => $acc->id];
        }

        // ===== OWNER (id,email) asegurado y único por email =====
        $ownerObj = $this->upsertOwnerForCuenta($acc, $cuenta, $rfc);
        return ['cuenta' => $cuenta, 'owner' => $ownerObj];
    }

    /** Normaliza igual que el login y devuelve Hash::make(normalizado) */
    private function clientHash(string $plain): string
    {
        // Usa la misma normalización que el LoginController si existe
        $norm = method_exists(app(\App\Http\Controllers\Cliente\Auth\LoginController::class), 'normalizePassword')
            ? app(\App\Http\Controllers\Cliente\Auth\LoginController::class)->normalizePassword($plain)
            : trim($plain);

        return \Hash::make($norm);
    }

    /**
     * Sincroniza plan y datos de billing de admin.accounts → mysql_clientes.cuentas_cliente
     * y, si existe, también la columna plan_actual.
     */
    private function syncPlanToMirror(string $rfc, array $payload): void
    {
        // Si no hay conexión/tabla espejo, salimos
        if (!Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
            return;
        }

        $conn = DB::connection('mysql_clientes');

        // Busca la cuenta espejo por rfc_padre (que es el ID/RFC en accounts)
        $cuenta = $conn->table('cuentas_cliente')
            ->where('rfc_padre', $rfc)
            ->first();

        if (!$cuenta) {
            // Aún no hay espejo, no hacemos nada (se creará con ensureMirrorAndOwner)
            return;
        }

        $schemaCli = Schema::connection('mysql_clientes');

        $upd = [
            'updated_at' => now(),
        ];

        $plan         = $payload['plan']              ?? null;
        $billingCycle = $payload['billing_cycle']     ?? null;
        $nextInvoice  = $payload['next_invoice_date'] ?? null;

        // Plan principal
        if ($schemaCli->hasColumn('cuentas_cliente', 'plan')) {
            $upd['plan'] = $plan;
        }

        // Alias que suele usar tu código de cliente
        if ($schemaCli->hasColumn('cuentas_cliente', 'plan_actual')) {
            $upd['plan_actual'] = $plan;
        }

        // Ciclo (monthly / yearly)
        if ($schemaCli->hasColumn('cuentas_cliente', 'billing_cycle')) {
            $upd['billing_cycle'] = $billingCycle;
        }

        // Próxima factura
        if ($schemaCli->hasColumn('cuentas_cliente', 'next_invoice_date')) {
            $upd['next_invoice_date'] = $nextInvoice;
        }

        // Si no hay nada útil que actualizar, salimos
        if (count($upd) <= 1) {
            return;
        }

        $conn->table('cuentas_cliente')
            ->where('rfc_padre', $rfc)
            ->update($upd);
    }
}
