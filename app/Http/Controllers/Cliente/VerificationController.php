<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class VerificationController extends Controller
{
    private const OTP_MAX_ATTEMPTS = 5;

    /* =========================================================
     * EMAIL
     * ========================================================= */

    public function verifyEmail(string $token)
    {
        $row = DB::connection('mysql_admin')
            ->table('email_verifications')
            ->where('token', $token)
            ->first();

        if (!$row) {
            return view('cliente.auth.verify_email', [
                'status'  => 'error',
                'message' => 'El enlace no es válido. Solicita uno nuevo.',
            ]);
        }

        if ($row->expires_at && now()->greaterThan($row->expires_at)) {
            return view('cliente.auth.verify_email', [
                'status'  => 'expired',
                'message' => 'El enlace expiró. Reenvíalo para continuar.',
                'email'   => $row->email,
            ]);
        }

        $toUpdate = [];
        if ($this->adminHas('email_verified_at')) $toUpdate['email_verified_at'] = now();
        if ($this->adminHas('updated_at'))        $toUpdate['updated_at']        = now();
        if (!empty($toUpdate)) {
            $emailCol = $this->adminEmailColumn();
            DB::connection('mysql_admin')->table('accounts')
                ->where('id', $row->account_id)
                ->where($emailCol, $row->email)
                ->update($toUpdate);
        }

        DB::connection('mysql_admin')->table('email_verifications')
            ->where('account_id', $row->account_id)
            ->delete();

        session([
            'verify.account_id' => $row->account_id,
            'verify.email'      => $row->email,
        ]);

        $phoneCol = $this->adminPhoneColumn();
        $account = DB::connection('mysql_admin')->table('accounts')
            ->select($phoneCol.' as phone')
            ->where('id', $row->account_id)
            ->first();

        return view('cliente.auth.verify_email', [
            'status'       => 'ok',
            'message'      => '¡Correo verificado! Ahora verifica tu teléfono para asegurar tu cuenta.',
            'phone_masked' => $this->maskPhone($account?->phone ?? ''),
        ]);
    }

    public function verifyEmailSigned(Request $request)
    {
        $accountId = (int) $request->query('account_id');
        $email     = (string) $request->query('email', '');
        if (!$accountId || !$email) abort(404);

        $toUpdate = [];
        if ($this->adminHas('email_verified_at')) $toUpdate['email_verified_at'] = now();
        if ($this->adminHas('updated_at'))        $toUpdate['updated_at']        = now();
        if (!empty($toUpdate)) {
            $emailCol = $this->adminEmailColumn();
            DB::connection('mysql_admin')->table('accounts')
                ->where('id', $accountId)->where($emailCol, $email)
                ->update($toUpdate);
        }

        session(['verify.account_id' => $accountId, 'verify.email' => $email]);

        return redirect()->route('cliente.verify.phone')
            ->with('ok', 'Correo verificado. Verifica tu teléfono para terminar.');
    }

    public function showResendEmail()
    {
        return view('cliente.auth.verify_email_resend');
    }

    public function resendEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:150'],
        ]);

        $emailCol = $this->adminEmailColumn();
        $account = DB::connection('mysql_admin')->table('accounts')
            ->where($emailCol, strtolower($request->email))
            ->first();

        if (!$account) {
            return back()->withErrors(['email' => 'No encontramos una cuenta con ese correo.'])->withInput();
        }

        if ($this->adminHas('email_verified_at') && $account->email_verified_at) {
            return back()->with('ok', 'Ese correo ya estaba verificado. Puedes continuar con la verificación de teléfono.');
        }

        $token = $this->createEmailVerificationToken($account->id, $request->email);
        $this->sendEmailVerification($request->email, $token, $this->adminDisplayName($account));

        return back()->with('ok', 'Te enviamos un nuevo enlace de verificación. Revisa tu correo.');
    }

    /* =========================================================
     * TELÉFONO (OTP 6 dígitos)
     * ========================================================= */

    public function showOtp(Request $request)
    {
        $accountId = session('verify.account_id');

        $email = $request->query('email');
        if (!$accountId && $email) {
            $emailCol = $this->adminEmailColumn();
            $acc = DB::connection('mysql_admin')->table('accounts')
                ->where($emailCol, strtolower($email))
                ->first();
            if ($acc) {
                session(['verify.account_id' => $acc->id, 'verify.email' => $email]);
                $accountId = $acc->id;
            }
        }

        $phoneCol = $this->adminPhoneColumn();
        $account = null;
        if ($accountId) {
            $account = DB::connection('mysql_admin')->table('accounts')
                ->select('id', $phoneCol.' as phone')
                ->where('id', $accountId)->first();
        }

        return view('cliente.auth.verify_phone', [
            'account'      => $account,
            'phone_masked' => $this->maskPhone($account?->phone ?? ''),
        ]);
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'channel' => ['required', 'in:sms,whatsapp'],
        ], [
            'channel.required' => 'Elige un canal para recibir tu código.',
        ]);

        $accountId = session('verify.account_id');
        if (!$accountId) {
            return back()->withErrors(['general' => 'No pudimos identificar tu cuenta. Abre tu enlace de verificación de correo nuevamente.']);
        }

        $phoneCol = $this->adminPhoneColumn();
        $emailCol = $this->adminEmailColumn();

        $account = DB::connection('mysql_admin')->table('accounts')
            ->select('id', $phoneCol.' as phone', $emailCol.' as email')
            ->where('id', $accountId)->first();

        if (!$account) {
            return back()->withErrors(['general' => 'Cuenta no encontrada.'])->withInput();
        }

        if (!$account->phone) {
            return back()->withErrors(['general' => 'No tenemos teléfono registrado. Completa tu registro nuevamente.']);
        }

        // Limpia OTPs vencidos
        DB::connection('mysql_admin')->table('phone_otps')
            ->where('account_id', $account->id)
            ->where('expires_at', '<', now())
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $data = [
                'account_id' => $account->id,
                'phone'      => $account->phone,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $codeCol = $this->phoneOtpsCodeColumn();
            $data[$codeCol] = $code;
            $chanCol = 'channel';
            $data[$chanCol] = $request->channel === 'whatsapp'
                ? ($this->phoneOtpsAcceptsWa() ? 'wa' : 'whatsapp')
                : 'sms';
            if ($this->adminHasPhoneOtps('attempts')) $data['attempts'] = 0;
            if ($this->adminHasPhoneOtps('used_at'))  $data['used_at']  = null;

            DB::connection('mysql_admin')->table('phone_otps')->insert($data);
        } catch (\Throwable $e) {
            Log::error('No se pudo insertar OTP', ['e' => $e->getMessage()]);
            return back()->withErrors([
                'general' => 'No pudimos generar el código en este momento. Intenta más tarde.',
            ]);
        }

        // Envío simulado por email (en cola si MAIL_QUEUE=true)
        $this->mailRawQueued($account->email, 'Código de verificación Pactopia360', "Tu código Pactopia360 es: {$code} (válido 10 minutos).");

        return back()->with('ok', 'Enviamos un código de 6 dígitos. Revisa tu dispositivo e ingrésalo a continuación.')
                     ->with('otp_sent', true);
    }

    public function checkOtp(Request $request)
    {
        $request->validate([
            'code' => ['required','digits:6'],
        ], [
            'code.required' => 'Ingresa el código que te enviamos.',
            'code.digits'   => 'El código debe tener 6 dígitos.',
        ]);

        $accountId = session('verify.account_id');
        if (!$accountId) {
            return back()->withErrors(['general' => 'Sesión no válida. Abre de nuevo tu enlace de verificación.']);
        }

        $codeCol = $this->phoneOtpsCodeColumn();

        $otp = DB::connection('mysql_admin')->table('phone_otps')
            ->where('account_id', $accountId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (!$otp) {
            return back()->withErrors(['code' => 'El código expiró o no existe. Solicita otro.']);
        }

        if ($this->adminHasPhoneOtps('attempts') && (int)($otp->attempts ?? 0) >= self::OTP_MAX_ATTEMPTS) {
            DB::connection('mysql_admin')->table('phone_otps')->where('id', $otp->id)
                ->update(['expires_at' => now()->subMinute(), 'updated_at' => now()]);
            return back()->withErrors(['code' => 'Se excedió el número de intentos. Solicita un código nuevo.']);
        }

        if (($otp->{$codeCol} ?? null) !== $request->code) {
            if ($this->adminHasPhoneOtps('attempts')) {
                DB::connection('mysql_admin')->table('phone_otps')->where('id', $otp->id)
                    ->update(['attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
            }
            return back()->withErrors(['code' => 'Código incorrecto. Intenta nuevamente.']);
        }

        // Marca OTP usado
        DB::connection('mysql_admin')->table('phone_otps')->where('id', $otp->id)->update([
            'used_at'    => now(),
            'updated_at' => now(),
        ]);

        // Marca phone_verified_at
        $upd = [];
        if ($this->adminHas('phone_verified_at')) $upd['phone_verified_at'] = now();
        if ($this->adminHas('updated_at'))        $upd['updated_at']        = now();
        if (!empty($upd)) {
            DB::connection('mysql_admin')->table('accounts')->where('id', $accountId)->update($upd);
        }

        // Activación final (sin cambiar contraseña)
        $this->finalizeActivationAndNotify($accountId);

        session()->forget(['verify.account_id', 'verify.email']);

        return redirect()->route('cliente.login')
            ->with('ok', '¡Listo! Verificaste tu correo y tu teléfono. Ya puedes acceder.');
    }

    /* =========================================================
     * Helpers
     * ========================================================= */

    private function maskPhone(string $phone): string
    {
        if (!$phone) return '';
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) < 4) return $phone;
        return str_repeat('•', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    private function createEmailVerificationToken(int $accountId, string $email): string
    {
        $token = Str::random(40);

        DB::connection('mysql_admin')->table('email_verifications')->insert([
            'account_id' => $accountId,
            'email'      => $email,
            'token'      => $token,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    private function sendEmailVerification(string $email, string $token, string $nombre): void
    {
        $url  = route('cliente.verify.email.token', ['token' => $token]);
        $body = "Hola {$nombre},\n\n".
                "Confirma tu correo haciendo clic en el siguiente enlace (válido 24 h):\n{$url}\n\n".
                "Después te pediremos verificar tu teléfono.\n\n".
                "Si no fuiste tú, ignora este correo.";

        $this->mailRawQueued($email, 'Confirma tu correo - Pactopia360', $body);
    }

    /**
     * Activa FREE (cuando aplique) sin tocar la contraseña, y avisa por correo.
     */
    private function finalizeActivationAndNotify(int $adminAccountId): void
    {
        $emailCol = $this->adminEmailColumn();

        $select = [$emailCol.' as email', 'rfc', 'plan'];
        if ($this->adminHas('is_blocked'))        $select[] = 'is_blocked';
        if ($this->adminHas('email_verified_at')) $select[] = 'email_verified_at';
        if ($this->adminHas('phone_verified_at')) $select[] = 'phone_verified_at';

        $acc = DB::connection('mysql_admin')->table('accounts')
            ->select($select)
            ->where('id', $adminAccountId)->first();

        if (!$acc) return;

        $emailOk = $this->adminHas('email_verified_at') ? (bool) $acc->email_verified_at : true;
        $phoneOk = $this->adminHas('phone_verified_at') ? (bool) $acc->phone_verified_at : true;

        // Vincula cuenta clientes por RFC
        $cuenta = DB::connection('mysql_clientes')->table('cuentas_cliente')
            ->where('rfc_padre', $acc->rfc)->first();
        if (!$cuenta) return;

        // Owner
        $usuario = DB::connection('mysql_clientes')->table('usuarios_cuenta')
            ->where('cuenta_id', $cuenta->id)->where('tipo', 'owner')->first();
        if (!$usuario) return;

        if ($emailOk && $phoneOk) {
            DB::connection('mysql_clientes')->beginTransaction();
            try {
                // Activar usuario (NO cambiamos password)
                DB::connection('mysql_clientes')->table('usuarios_cuenta')
                    ->where('id', $usuario->id)
                    ->update([
                        'activo'               => 1,
                        'must_change_password' => 1,
                        'updated_at'           => now(),
                    ]);

                // FREE → activar cuenta
                if (strtoupper($acc->plan) === 'FREE') {
                    DB::connection('mysql_clientes')->table('cuentas_cliente')
                        ->where('id', $cuenta->id)
                        ->update(['estado_cuenta' => 'activa', 'updated_at' => now()]);
                }

                DB::connection('mysql_clientes')->commit();
            } catch (\Throwable $e) {
                DB::connection('mysql_clientes')->rollBack();
                Log::error('Error finalizando activación', ['e' => $e->getMessage(), 'account_id' => $adminAccountId]);
                return;
            }

            // Aviso simple de activación (sin reenviar password)
            $loginUrl = route('cliente.login');
            $this->mailRawQueued($acc->email, 'Tu cuenta ya está activa - Pactopia360',
                "¡Listo! Completaste la verificación en Pactopia360.\n\n".
                "Ya puedes iniciar sesión aquí: {$loginUrl}\n\n".
                "Si no recuerdas tu contraseña, usa \"Olvidé mi contraseña\" para restablecerla.");
        }
    }

    /* ===== Helpers de esquema admin.accounts / phone_otps ===== */

    private function adminHas(string $col): bool
    {
        try { return Schema::connection('mysql_admin')->hasColumn('accounts', $col); }
        catch (\Throwable $e) { return false; }
    }

    private function adminEmailColumn(): string
    {
        foreach (['correo_contacto','email'] as $c) if ($this->adminHas($c)) return $c;
        return 'email';
    }

    private function adminPhoneColumn(): string
    {
        foreach (['telefono','phone'] as $c) if ($this->adminHas($c)) return $c;
        return 'telefono';
    }

    private function adminDisplayName(object $account): string
    {
        foreach (['name','razon_social','nombre','nombre_cuenta'] as $c) {
            if (isset($account->{$c}) && $account->{$c}) return (string) $account->{$c};
        }
        return 'Usuario';
    }

    private function adminHasPhoneOtps(string $col): bool
    {
        try { return Schema::connection('mysql_admin')->hasColumn('phone_otps', $col); }
        catch (\Throwable $e) { return false; }
    }

    private function phoneOtpsCodeColumn(): string
    {
        return $this->adminHasPhoneOtps('code') ? 'code' : 'otp';
    }

    private function phoneOtpsAcceptsWa(): bool
    {
        return true;
    }

    /* ====== Envío de correo en cola opcional ====== */

    private function mailRawQueued(string $to, string $subject, string $body): void
    {
        $useQueue = (bool) env('MAIL_QUEUE', false);

        if ($useQueue) {
            Bus::dispatch(function () use ($to, $subject, $body) {
                Mail::raw($body, function ($m) use ($to, $subject) {
                    $m->to($to)->subject($subject);
                });
            })->onQueue('default');
        } else {
            try {
                Mail::raw($body, function ($m) use ($to, $subject) {
                    $m->to($to)->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::error('Fallo envío de correo', ['to' => $to, 'subject' => $subject, 'e' => $e->getMessage()]);
            }
        }
    }

    /* ====== Actualizar teléfono desde el usuario autenticado o sesión de verificación ====== */

    public function updatePhone(Request $request)
    {
        $request->validate([
            'phone'   => 'required|string|max:25',
            'channel' => 'nullable|in:sms,whatsapp,wa',
        ]);

        $phone   = trim($request->phone);
        $channel = $request->channel ?: 'sms';

        // 1) Preferimos el account_id de la sesión de verificación
        $accountId = session('verify.account_id');

        // 2) Si no hay, tratamos de obtenerlo desde el usuario autenticado (vía cuenta.admin_account_id)
        if (!$accountId && auth('web')->check()) {
            $accountId = optional(auth('web')->user()->cuenta)->admin_account_id;
        }

        if (!$accountId) {
            return back()->withErrors(['general' => 'No pudimos ubicar tu cuenta para actualizar el teléfono.'])
                         ->withInput();
        }

        // Actualiza teléfono en admin.accounts (columna dinámica)
        $phoneCol = $this->adminPhoneColumn();
        DB::connection('mysql_admin')->table('accounts')
            ->where('id', $accountId)
            ->update([$phoneCol => $phone, 'updated_at'=>now()]);

        return back()->with('ok','Teléfono actualizado. Ahora solicita tu OTP.')->withInput(['channel' => $channel]);
    }
}
