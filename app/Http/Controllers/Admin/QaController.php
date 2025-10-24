<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class QaController extends Controller
{
    /** Conexiones */
    protected string $admin = 'mysql_admin';
    protected string $cli   = 'mysql_clientes';

    public function __construct()
    {
        // Sesión admin + cuenta activa
        $this->middleware(['auth:admin', 'account.active']);
    }

    /**
     * Permite usar el panel sólo en local/dev/testing o por superadmins listados en APP_SUPERADMINS.
     */
    private function ensureLocalSuperadmin(): void
    {
        // En local/dev/testing siempre permitido
        if (app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        // En otros ambientes, sólo superadmins
        $user = auth('admin')->user();
        if (!$user) {
            abort(403, 'Requiere sesión de admin.');
        }

        // Lee de config('app.superadmins') o APP_SUPERADMINS (CSV)
        $raw  = config('app.superadmins') ?? env('APP_SUPERADMINS', '');
        $list = is_array($raw)
            ? array_map(static fn($v) => strtolower(trim((string)$v)), $raw)
            : array_map(static fn($v) => strtolower(trim($v)), explode(',', (string)$raw));

        $email = strtolower((string) $user->email);
        if (!$email || !in_array($email, $list, true)) {
            abort(403, 'Sólo superadmin puede usar QA en este ambiente.');
        }
    }

    /* =========================================================
       LISTA / PANEL
       ========================================================= */
    public function index(Request $r): View
    {
        $this->ensureLocalSuperadmin();

        $emailCol = $this->adminEmailColumn();
        $phoneCol = $this->adminPhoneColumn();

        $q = DB::connection($this->admin)->table('accounts as a')
            ->select(
                'a.id', 'a.rfc', 'a.plan', 'a.plan_actual', 'a.modo_cobro', 'a.estado_cuenta',
                DB::raw("a.$emailCol as email"),
                DB::raw("a.$phoneCol as phone"),
                'a.email_verified_at', 'a.phone_verified_at',
                DB::raw("COALESCE(a.nombre,a.razon_social,a.nombre_cuenta,a.name) as display_name"),
                'a.created_at', 'a.updated_at'
            )
            ->orderByDesc('a.id');

        if ($r->filled('rfc'))   $q->where('a.rfc', 'like', '%'.$r->string('rfc').'%');
        if ($r->filled('email')) $q->where("a.$emailCol", 'like', '%'.$r->string('email').'%');

        $accounts = $q->limit(80)->get();

        $tokens = DB::connection($this->admin)->table('email_verifications')
            ->select('id','account_id','email','token','expires_at','created_at')
            ->orderByDesc('id')->limit(200)->get()
            ->groupBy('account_id');

        $codeCol = $this->phoneOtpsCodeColumn();
        $otps = DB::connection($this->admin)->table('phone_otps')
            ->select('id','account_id','phone', DB::raw("$codeCol as code"), 'channel','expires_at','attempts','used_at','created_at')
            ->orderByDesc('id')->limit(200)->get()
            ->groupBy('account_id');

        // Owners de clientes (para cotejar activación)
        $owners = DB::connection($this->cli)->table('usuarios_cuenta as u')
            ->join('cuentas_cliente as c','c.id','=','u.cuenta_id')
            ->select('u.id','u.email','u.nombre','u.activo','u.must_change_password','c.rfc_padre','c.estado_cuenta','c.plan_actual','u.created_at')
            ->where('u.tipo','owner')->orderByDesc('u.created_at')->limit(80)->get();

        return view('admin.dev.qa', compact('accounts','tokens','otps','owners'));
    }

    /* =========================================================
       ACCIONES
       ========================================================= */

    public function resendEmail(Request $r): RedirectResponse
    {
        $this->ensureLocalSuperadmin();

        $r->validate([
            'account_id' => 'required|integer',
            'email'      => 'required|email'
        ]);

        $token = Str::random(40);

        DB::connection($this->admin)->table('email_verifications')->insert([
            'account_id' => (int) $r->integer('account_id'),
            'email'      => strtolower($r->string('email')),
            'token'      => $token,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // En QA mandamos un correo (si MAIL_MAILER=log lo verás en storage/logs/laravel.log)
        try {
            $url = route('cliente.verify.email.token', ['token' => $token]);
            Mail::send('emails.cliente.verify_email', [
                'nombre' => 'Usuario',
                'url'    => $url,
            ], function($m) use ($r) {
                $m->to((string)$r->email)->subject('Confirma tu correo - Pactopia360');
            });
        } catch (\Throwable $e) {
            // swallow
        }

        return back()->with('ok','Nuevo enlace generado y enviado (revisa el log de correo).');
    }

    public function sendOtp(Request $r): RedirectResponse
    {
        $this->ensureLocalSuperadmin();

        $r->validate([
            'account_id' => 'required|integer',
            'channel'    => 'required|in:sms,wa,whatsapp',
            'phone'      => [
                'required','string','max:25',
                function($attr,$val,$fail){
                    $tel = preg_replace('/[^\d\+\-\s]/','', trim((string)$val));
                    if (!preg_match('/^\+?[0-9\s\-]{8,20}$/', $tel)) {
                        $fail('Teléfono inválido.');
                    }
                }
            ],
            'update_account_phone' => 'nullable|boolean',
        ]);

        // Si se pide actualizar el teléfono en la cuenta, lo hacemos
        if ($r->boolean('update_account_phone')) {
            $phoneCol = $this->adminPhoneColumn();
            DB::connection($this->admin)->table('accounts')
                ->where('id', (int) $r->integer('account_id'))
                ->update([$phoneCol => trim((string)$r->phone), 'updated_at'=>now()]);
        }

        $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeCol = $this->phoneOtpsCodeColumn();

        DB::connection($this->admin)->table('phone_otps')->insert([
            'account_id' => (int) $r->integer('account_id'),
            'phone'      => trim((string)$r->phone),
            $codeCol     => $code,
            'channel'    => $r->string('channel') === 'whatsapp' ? 'wa' : (string)$r->channel,
            'attempts'   => $this->adminHasPhoneOtps('attempts') ? 0 : null,
            'used_at'    => $this->adminHasPhoneOtps('used_at')  ? null : null,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // En QA mandamos el código por correo para verlo rápido
        try {
            $emailCol = $this->adminEmailColumn();
            $acc = DB::connection($this->admin)->table('accounts')
                ->select('id', DB::raw("$emailCol as email"))
                ->where('id', (int) $r->integer('account_id'))
                ->first();

            if ($acc?->email) {
                Mail::send('emails.cliente.verify_phone', ['code'=>$code,'minutes'=>10], function($m) use ($acc) {
                    $m->to($acc->email)->subject('Tu código de verificación - Pactopia360');
                });
            }
        } catch (\Throwable $e) {
            // swallow
        }

        return back()->with('ok','OTP generado: '.$code.' (enviado por correo de QA y guardado en phone_otps).');
    }

    public function forceEmailVerified(Request $r): RedirectResponse
    {
        $this->ensureLocalSuperadmin();

        $r->validate(['account_id'=>'required|integer']);

        $upd = ['updated_at'=>now()];
        if ($this->adminHas('email_verified_at')) {
            $upd['email_verified_at'] = now();
        }

        DB::connection($this->admin)->table('accounts')
            ->where('id', (int) $r->integer('account_id'))
            ->update($upd);

        return back()->with('ok','Email marcado como verificado.');
    }

    public function forcePhoneVerified(Request $r): RedirectResponse
    {
        $this->ensureLocalSuperadmin();

        $r->validate(['account_id'=>'required|integer']);

        $upd = ['updated_at'=>now()];
        if ($this->adminHas('phone_verified_at')) {
            $upd['phone_verified_at'] = now();
        }

        DB::connection($this->admin)->table('accounts')
            ->where('id', (int) $r->integer('account_id'))
            ->update($upd);

        // Intentar finalizar activación + envío de credenciales como en el flujo real
        try {
            app(\App\Http\Controllers\Cliente\VerificationController::class)
                ->finalizeActivationAndSendCredentials((int)$r->integer('account_id'));
        } catch (\Throwable $e) {
            // swallow
        }

        return back()->with('ok','Teléfono verificado y activación final intentada.');
    }

    /* =========================================================
       HELPERS DE ESQUEMA (columnas variables)
       ========================================================= */

    private function adminHas(string $col): bool
    {
        try {
            return Schema::connection($this->admin)->hasColumn('accounts', $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function adminHasPhoneOtps(string $col): bool
    {
        try {
            return Schema::connection($this->admin)->hasColumn('phone_otps', $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function adminEmailColumn(): string
    {
        foreach (['correo_contacto', 'email'] as $c) {
            if ($this->adminHas($c)) return $c;
        }
        return 'email';
    }

    private function adminPhoneColumn(): string
    {
        foreach (['telefono', 'phone'] as $c) {
            if ($this->adminHas($c)) return $c;
        }
        return 'telefono';
    }

    private function phoneOtpsCodeColumn(): string
    {
        return $this->adminHasPhoneOtps('code') ? 'code' : 'otp';
    }
}
