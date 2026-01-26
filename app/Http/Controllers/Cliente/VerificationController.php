<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Services\OtpService;

class VerificationController extends Controller
{
    private const OTP_MAX_ATTEMPTS = 5;

    /* =========================
     * Helpers de guard unificado
     * ========================= */
    private function currentClientUser()
    {
        if (Auth::guard('web')->check()) {
            return Auth::guard('web')->user();
        }
        try {
            if (Auth::guard('cliente')->check()) {
                return Auth::guard('cliente')->user();
            }
        } catch (\Throwable $e) {
            // ignore
        }
        if (auth()->check()) {
            return auth()->user();
        }
        return null;
    }

    private function clientIsAuthenticated(): bool
    {
        if (Auth::guard('web')->check()) return true;
        try {
            if (Auth::guard('cliente')->check()) return true;
        } catch (\Throwable $e) {}
        return auth()->check();
    }

    /* =========================================================
     * EMAIL con token /cliente/verificar/email/{token}
     * ========================================================= */

    /**
     * Alias por si alguna ruta/controller lo invoca con otro nombre.
     */
    public function verifyEmailToken(string $token)
    {
        return $this->verifyEmail($token);
    }

    public function verifyEmail(string $token)
    {
        $row = DB::connection('mysql_admin')
            ->table('email_verifications')
            ->where('token', $token)
            ->first();

        // ✅ FIX: NO contaminar /cliente/login con session('error').
        // Si el token no existe (usado o inválido), mostramos pantalla de verificación
        // (o reenviar) en lugar de redirigir al login.
        if (!$row) {
            // Si tienes la vista verify_email, úsala
            if (view()->exists('cliente.auth.verify_email')) {
                return view('cliente.auth.verify_email', [
                    'status'       => 'invalid',
                    'message'      => 'El enlace no es válido o ya fue usado. Solicita uno nuevo.',
                    'email'        => null,
                    'phone_masked' => null,
                ]);
            }

            // Fallback: mandar a reenviar (sin session('error'))
            return redirect()
                ->route('cliente.verify.email.resend')
                ->with('info', 'El enlace no es válido o ya fue usado. Solicita uno nuevo.');
        }

        if ($row->expires_at && now()->greaterThan($row->expires_at)) {
            return view('cliente.auth.verify_email', [
                'status'       => 'expired',
                'message'      => 'El enlace de verificación expiró. Solicita uno nuevo.',
                'email'        => $row->email ?? null,
                'phone_masked' => null,
            ]);
        }

        DB::connection('mysql_admin')
            ->table('accounts')
            ->where('id', $row->account_id)
            ->update([
                'email_verified_at' => now(),
                'updated_at'        => now(),
            ]);

        DB::connection('mysql_admin')
            ->table('email_verifications')
            ->where('account_id', $row->account_id)
            ->delete();

        $resolvedAccountId = null;
        $resolvedPhone     = null;

        // Resolver columna de teléfono REAL en accounts
        $phoneCol = $this->adminPhoneColumn();

        if ($this->clientIsAuthenticated()) {
            $user       = $this->currentClientUser();
            $userCuenta = $user?->cuenta()?->first();
            if ($userCuenta && !empty($userCuenta->rfc_padre)) {
                $accAdm = DB::connection('mysql_admin')
                    ->table('accounts')
                    ->whereRaw('UPPER(rfc)=?', [Str::upper($userCuenta->rfc_padre)])
                    ->select('id', "{$phoneCol} as phone")
                    ->orderByDesc('id')
                    ->first();

                if ($accAdm) {
                    $resolvedAccountId = (int) $accAdm->id;
                    $resolvedPhone     = $accAdm->phone ?? null;
                }
            }
        }

        if (!$resolvedAccountId) {
            $accAdmByToken = DB::connection('mysql_admin')
                ->table('accounts')
                ->where('id', $row->account_id)
                ->select('id', "{$phoneCol} as phone")
                ->first();

            if ($accAdmByToken) {
                $resolvedAccountId = (int) $accAdmByToken->id;
                $resolvedPhone     = $accAdmByToken->phone ?? null;
            }
        }

        if ($resolvedAccountId) {
            session([
                'verify.account_id' => $resolvedAccountId,
                'verify.email'      => Str::lower((string) $row->email),
            ]);

            // ✅ Forzar persistencia inmediata (evita perder la sesión en algunos flujos)
            try { session()->save(); } catch (\Throwable $e) {}
        }

        // ✅ Robusto: manda a OTP con account_id como fallback duro (aunque la sesión falle)
        if ($resolvedAccountId) {
            return redirect()
                ->route('cliente.verify.phone', ['account_id' => $resolvedAccountId])
                ->with('ok', 'Correo verificado. Ahora verifica tu teléfono.');
        }

        // Fallback (no debería pasar, pero evitamos pantalla OTP sin cuenta)
        return redirect()
            ->route('cliente.verify.email.resend')
            ->with('info', 'Correo verificado, pero no pudimos ubicar tu cuenta. Solicita un enlace nuevo.');

    }

    /* =========================================================
     * EMAIL firmada /cliente/verificar/email/link?account_id=..&email=..
     * ========================================================= */
    public function verifyEmailSigned(Request $request)
    {
        $accountIdFromLink = (int) $request->query('account_id');
        $emailFromLink     = (string) $request->query('email', '');

        if (!$accountIdFromLink || !$emailFromLink) {
            abort(404);
        }

        DB::connection('mysql_admin')
            ->table('accounts')
            ->where('id', $accountIdFromLink)
            ->where(function ($q) use ($emailFromLink) {
                $q->where('correo_contacto', $emailFromLink)
                  ->orWhere('correo_contacto', Str::lower($emailFromLink))
                  ->orWhere('correo_contacto', Str::upper($emailFromLink))
                  ->orWhere('email', $emailFromLink)
                  ->orWhere('email', Str::lower($emailFromLink))
                  ->orWhere('email', Str::upper($emailFromLink));
            })
            ->update([
                'email_verified_at' => now(),
                'updated_at'        => now(),
            ]);

        $resolvedAccountId = null;
        $resolvedPhone     = null;

        // Resolver columna de teléfono REAL en accounts
        $phoneCol = $this->adminPhoneColumn();

        if ($this->clientIsAuthenticated()) {
            $user       = $this->currentClientUser();
            $userCuenta = $user?->cuenta()?->first();
            if ($userCuenta && !empty($userCuenta->rfc_padre)) {
                $accAdm = DB::connection('mysql_admin')
                    ->table('accounts')
                    ->whereRaw('UPPER(rfc)=?', [Str::upper($userCuenta->rfc_padre)])
                    ->select('id', "{$phoneCol} as phone")
                    ->orderByDesc('id')
                    ->first();

                if ($accAdm) {
                    $resolvedAccountId = (int) $accAdm->id;
                    $resolvedPhone     = $accAdm->phone ?? null;
                }
            }
        }

        if (!$resolvedAccountId) {
            $accAdmByLink = DB::connection('mysql_admin')
                ->table('accounts')
                ->where('id', $accountIdFromLink)
                ->select('id', "{$phoneCol} as phone")
                ->first();

            if ($accAdmByLink) {
                $resolvedAccountId = (int) $accAdmByLink->id;
                $resolvedPhone     = $accAdmByLink->phone ?? null;
            }
        }

        if ($resolvedAccountId) {
            session([
                'verify.account_id' => $resolvedAccountId,
                'verify.email'      => Str::lower($emailFromLink),
            ]);

            // ✅ Forzar persistencia inmediata
            try { session()->save(); } catch (\Throwable $e) {}
        }

        // ✅ Redirigir directo a OTP con fallback por query
        if ($resolvedAccountId) {
            return redirect()
                ->route('cliente.verify.phone', ['account_id' => $resolvedAccountId])
                ->with('ok', 'Correo verificado. Falta tu teléfono.');
        }

        return redirect()
            ->route('cliente.verify.email.resend')
            ->with('info', 'Correo verificado, pero no pudimos ubicar tu cuenta. Solicita un enlace nuevo.');

    }

    /* =========================================================
     * Reenviar verificación de correo
     * ========================================================= */
    public function showResendEmail()
    {
        return view('cliente.auth.verify_email_resend');
    }

    public function resendEmail(Request $request)
    {
        $request->validate(
            [
                'email' => ['required', 'email:rfc,dns', 'max:150'],
            ],
            [
                'email.required' => 'Ingresa tu correo electrónico.',
                'email.email'    => 'Escribe un correo válido (ej. nombre@dominio.com).',
                'email.max'      => 'El correo no debe exceder 150 caracteres.',
            ]
        );

        $email = Str::lower($request->email);

        $account = DB::connection('mysql_admin')
            ->table('accounts')
            ->where('correo_contacto', $email)
            ->orWhere('email', $email)
            ->orderByDesc('id')
            ->first();

        if (!$account) {
            return back()
                ->withErrors(['email' => 'No encontramos una cuenta con ese correo.'])
                ->withInput();
        }

        if (!empty($account->email_verified_at)) {
            // ✅ Si ya está verificado, manda directo a OTP con account_id (sin depender de sesión)
            session([
                'verify.account_id' => (int) $account->id,
                'verify.email'      => $email,
            ]);
            try { session()->save(); } catch (\Throwable $e) {}

            return redirect()
                ->route('cliente.verify.phone', ['account_id' => (int) $account->id])
                ->with('ok', 'Ese correo ya está verificado. Continúa con tu teléfono.');
        }


        $token = $this->createEmailVerificationToken((int)$account->id, $email);
        $this->sendEmailVerification($email, $token, $this->adminDisplayName($account));

        session([
            'verify.account_id' => (int)$account->id,
            'verify.email'      => $email,
        ]);

        return back()->with('ok', 'Te enviamos un nuevo enlace de verificación.');
    }

    /**
     * Resolución FINAL del account_id admin que vamos a usar en todo el flujo.
     */
    private function resolveAccountId(Request $request): ?int
    {
        // 1) flujo verificación (correo) -> session.verify.account_id
        $aid = (int) session('verify.account_id', 0);
        if ($aid > 0) {
            \Log::debug('[OTP-FLOW][resolveAccountId] from session.verify.account_id', ['aid' => $aid]);
            return $aid;
        }

        // 2) request explícito (GET ?account_id=) o POST account_id
        // IMPORTANTE: en GET (click en <a>) viene por query()
        $aid = (int) ($request->query('account_id', 0) ?: $request->input('account_id', 0));
        if ($aid > 0) {
            \Log::debug('[OTP-FLOW][resolveAccountId] from request.account_id', [
                'aid'   => $aid,
                'from'  => $request->query('account_id') ? 'query' : 'input',
                'path'  => $request->path(),
                'full'  => $request->fullUrl(),
            ]);

            // Si venimos por query y la sesión está vacía, la sembramos para siguientes requests
            try {
                session(['verify.account_id' => $aid]);
                session()->save();
            } catch (\Throwable $e) {
                \Log::warning('[OTP-FLOW][resolveAccountId] could not persist verify.account_id into session', [
                    'aid' => $aid,
                    'e'   => $e->getMessage(),
                ]);
            }

            return $aid;
        }

        // 3) sesiones estándar del portal cliente
        foreach (['cliente.account_id','client.account_id','account_id','cliente.cuenta_id','client.cuenta_id','cuenta_id'] as $k) {
            $v = (int) session($k, 0);
            if ($v > 0) {
                \Log::debug('[OTP-FLOW][resolveAccountId] from session', ['key' => $k, 'aid' => $v]);
                return $v;
            }
        }

        // 4) fallback por auth:web (cuando viene desde login normal)
        try {
            $u = auth('web')->user();
            if ($u) {
                $direct = (int) data_get($u, 'account_id', 0);
                if ($direct > 0) {
                    \Log::debug('[OTP-FLOW][resolveAccountId] from auth.user.account_id', ['aid' => $direct]);
                    return $direct;
                }

                $rel = (int) data_get($u, 'cuenta.account_id', 0);
                if ($rel > 0) {
                    \Log::debug('[OTP-FLOW][resolveAccountId] from auth.user.cuenta.account_id', ['aid' => $rel]);
                    return $rel;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[OTP-FLOW][resolveAccountId] auth fallback failed', ['e' => $e->getMessage()]);
        }

        \Log::warning('[OTP-FLOW][resolveAccountId] could not resolve account id', [
            'path' => $request->path(),
            'full' => $request->fullUrl(),
        ]);

        return null;
    }

    public function finalizeActivationAndSendCredentialsByRfc(string $rfc): void
    {
        // Alias compat: si ya existe otro método finalizador, llámalo.
        if (method_exists($this, 'finalizeActivationAndSendCredentials')) {
            $this->finalizeActivationAndSendCredentials($rfc);
            return;
        }

        \Log::warning('finalizeActivationAndSendCredentialsByRfc: no-op (missing implementation)', ['rfc' => $rfc]);
    }




    private function fetchAccountEmailById(int $accountId): string
    {
        $schemaAdmin = Schema::connection('mysql_admin');
        $emailCol = $schemaAdmin->hasColumn('accounts', 'correo_contacto')
            ? 'correo_contacto'
            : ($schemaAdmin->hasColumn('accounts', 'email') ? 'email' : 'correo_contacto');

        $acc = DB::connection('mysql_admin')
            ->table('accounts')
            ->where('id', $accountId)
            ->select($emailCol . ' as email')
            ->first();

        return $acc ? Str::lower((string)($acc->email ?? '')) : '';
    }

    /**
     * GET /cliente/verificar/telefono
     */
    public function showOtp(Request $request)
    {
        $accountId = $this->resolveAccountId($request);

        if (app()->environment(['local','development','testing'])) {
            Log::debug('[OTP-FLOW][DEBUG showOtp FINAL]', [
                'auth_web_id'         => Auth::guard('web')->id(),
                'auth_cli_id'         => (function(){ try { return Auth::guard('cliente')->id(); } catch (\Throwable $e) { return null; } })(),
                'session_verify_acc'  => session('verify.account_id'),
                'final_account_id'    => $accountId,
            ]);
        }

        if (!$accountId) {
            return redirect()
                ->route('cliente.verify.email.resend')
                ->withErrors([
                    'email' => 'No pudimos ubicar tu cuenta para actualizar el teléfono. Solicita un enlace nuevo.',
                ]);
        }


        $viewData = $this->loadAccountWithPhone($accountId);
        return view('cliente.auth.verify_phone', $viewData);

    }

    private function loadAccountWithPhone(?int $accountId): array
    {
        $phoneCol = $this->adminPhoneColumn();
        $account  = null;

        if ($accountId) {
            $account = DB::connection('mysql_admin')
                ->table('accounts')
                ->select('id', "{$phoneCol} as phone")
                ->where('id', $accountId)
                ->first();
        }

        $safeAccount = $account ? (object)[
            'id'    => $account->id,
            'phone' => $account->phone,
        ] : (object)[
            'id'    => null,
            'phone' => null,
        ];

        $phoneMasked = $this->maskPhone($safeAccount->phone ?? '');

        $hasOtpSession = session()->has('verify.otp_code');
        $state = ($hasOtpSession || !empty($safeAccount->phone))
            ? 'otp'
            : 'phone';

        return [
            'account'      => $safeAccount,
            'phone_masked' => $phoneMasked,
            'state'        => $state,
        ];
    }

    /* =========================================================
     * POST cliente.verify.phone.update
     * Guardar teléfono + generar OTP (con envío)
     * ========================================================= */
    public function updatePhone(Request $request)
    {
        $request->validate([
            'country_code' => 'required|string|max:5',
            'telefono'     => 'required|string|max:25',
            'account_id'   => 'nullable|integer',
        ]);

        $country = trim($request->country_code);
        $line    = trim($request->telefono);
        $full    = trim($country . ' ' . $line);

        $accountId = $this->resolveAccountId($request);

        Log::info('[OTP-FLOW][1] updatePhone', [
            'fullPhone'          => $full,
            'pre_session_accid'  => session('verify.account_id'),
            'resolved_accountId' => $accountId,
        ]);

        if (!$accountId) {
            return redirect()
                ->route('cliente.verify.phone')
                ->withErrors([
                    'general' => 'No pudimos ubicar tu cuenta para actualizar el teléfono.',
                ]);
        }

        $phoneCol = $this->adminPhoneColumn();

        DB::connection('mysql_admin')
            ->table('accounts')
            ->where('id', $accountId)
            ->update([
                $phoneCol    => $full,
                'updated_at' => now(),
            ]);

        // Canal: whatsapp|sms (el driver/proveedor va aparte en generateAndStoreOtp)
        $channel = (string) config('services.otp.channel', 'whatsapp'); // whatsapp|sms


        $digits = preg_replace('/\D+/', '', $full);
        [$code, $expiresTs] = $this->generateAndStoreOtp(
            $accountId,
            $digits,
            $channel
        );

        Log::info('[OTP-FLOW][2] OTP generado', [
            'account_id' => $accountId,
            'code'       => $code,
            'expires'    => $expiresTs,
            'channel'    => $channel,
        ]);

        if (!$code) {
        return redirect()
            ->route('cliente.verify.phone', ['account_id' => $accountId])
            ->withErrors(['general' => 'Error generando el código.']);
        }


        session([
            'verify.account_id'  => $accountId,
            'verify.phone'       => $digits,
            'verify.otp_code'    => $code,
            'verify.otp_expires' => $expiresTs,
        ]);

        
        return redirect()
        ->route('cliente.verify.phone', ['account_id' => $accountId])
        ->with(
            'ok',
            'Te enviamos tu código por ' . strtoupper($channel) . '. Ingrésalo abajo. Si no llega en 1–2 minutos, toca “Reenviar código”.'
        );

    }

    /* =========================================================
     * POST cliente.verify.phone.send
     * Reenviar OTP
     * ========================================================= */
    public function sendOtp(Request $request)
    {
        $accountId = $this->resolveAccountId($request);

        if (!$accountId) {
            return redirect()
                ->route('cliente.verify.email.resend')
                ->withErrors([
                    'email' => 'No pudimos asociar tu cuenta. Abre de nuevo tu enlace de verificación o solicita uno nuevo.',
                ]);
        }


        $phoneCol = $this->adminPhoneColumn();

        $accAdm = DB::connection('mysql_admin')
            ->table('accounts')
            ->where('id', $accountId)
            ->select('id', "{$phoneCol} as phone")
            ->first();

        $rawPhone   = $accAdm?->phone ?? '';
        $onlyDigits = preg_replace('/\D+/', '', $rawPhone);

        // Canal: whatsapp|sms (el driver/proveedor va aparte en generateAndStoreOtp)
        $channel = (string) config('services.otp.channel', 'whatsapp'); // whatsapp|sms


        [$code, $expiresTs] = $this->generateAndStoreOtp(
            $accountId,
            $onlyDigits,
            $channel
        );

        if (!$code) {
            return redirect()
                ->route('cliente.verify.phone', ['account_id' => $accountId])
                ->withErrors([
                    'general' => 'No pudimos generar el código en este momento. Intenta más tarde.',
                ]);
        }


        session([
            'verify.account_id'  => $accountId,
            'verify.phone'       => $onlyDigits,
            'verify.otp_code'    => $code,
            'verify.otp_expires' => $expiresTs,
        ]);

        return redirect()
        ->route('cliente.verify.phone', ['account_id' => $accountId])
        ->with('ok', 'Reenviamos tu código por ' . strtoupper($channel) . '. Ingrésalo abajo.');

    }

    /* =========================================================
     * POST cliente.verify.phone.check
     * Validar OTP, activar cuenta, etc.
     * ========================================================= */
    public function checkOtp(Request $request)
    {
        $request->validate([
            'code'       => ['required', 'digits:6'],
            'account_id' => ['nullable', 'integer'],
        ]);

        $accountId = (int) $this->resolveAccountId($request);
        $inputCode = $request->code;

        Log::info('[OTP-FLOW][3] checkOtp start', [
            'input_code'          => $inputCode,
            'session_account_id'  => session('verify.account_id'),
            'resolved_account_id' => $accountId,
        ]);

        if (!$accountId) {
            return back()->withErrors(['general' => 'Sesión no válida.']);
        }

        $otpRow = DB::connection('mysql_admin')
            ->table('phone_otps')
            ->where('account_id', $accountId)
            ->orderByDesc('id')
            ->first();

        if (!$otpRow) {
            return back()->withErrors(['code' => 'No existe ningún código activo.']);
        }

        // Bloqueo por intentos
        if ((int)($otpRow->attempts ?? 0) >= self::OTP_MAX_ATTEMPTS) {
            return back()->withErrors(['code' => 'Demasiados intentos. Solicita un código nuevo.']);
        }

        if (!empty($otpRow->expires_at) && now()->greaterThan($otpRow->expires_at)) {
            Log::warning('[OTP-FLOW][5] OTP expirado', [
                'otp_id'     => $otpRow->id,
                'expires_at' => $otpRow->expires_at,
            ]);
            return back()->withErrors(['code' => 'El código expiró.']);
        }

        // Comparación estricta
        $dbCodeA = (string)($otpRow->code ?? '');
        $dbCodeB = (string)($otpRow->otp  ?? '');

        if ($dbCodeA !== $inputCode && $dbCodeB !== $inputCode) {
            DB::connection('mysql_admin')
                ->table('phone_otps')
                ->where('id', $otpRow->id)
                ->update([
                    'attempts'   => DB::raw('attempts + 1'),
                    'updated_at' => now(),
                ]);

            Log::warning('[OTP-FLOW][6] Código incorrecto', [
                'input_code' => $inputCode,
                'db_code'    => $dbCodeA,
                'db_otp'     => $dbCodeB,
            ]);
            return back()->withErrors(['code' => 'Código incorrecto.']);
        }

        // éxito → marcar usado
        DB::connection('mysql_admin')
            ->table('phone_otps')
            ->where('id', $otpRow->id)
            ->update([
                'used_at'    => now(),
                'updated_at' => now(),
            ]);

        DB::connection('mysql_admin')
            ->table('accounts')
            ->where('id', $accountId)
            ->update([
                'phone_verified_at' => now(),
                'updated_at'        => now(),
            ]);

        Log::info('[OTP-FLOW][7] OTP OK, phone_verified_at set', [
            'account_id' => $accountId,
        ]);

        // ==== AUTO LOGIN CLIENTE ====
        try {
            Auth::shouldUse('cliente');
        } catch (\Throwable $e) {
            Auth::shouldUse('web');
        }

        if (Auth::guard('admin')->check()) {
            Auth::guard('admin')->logout();
        }

        $logged   = false;
        $userId   = (int) session('post_verify.user_id');
        $remember = (bool) session('post_verify.remember', false);

        if ($userId) {
            $user = \App\Models\Cliente\UsuarioCuenta::on('mysql_clientes')->find($userId);
            if ($user) {
                auth()->login($user, $remember);
                $request->session()->regenerate();
                $logged = true;
            }
        }

        // Plan B: por RFC (case-insensitive)
        if (!$logged) {
            $acc = DB::connection('mysql_admin')
                ->table('accounts')
                ->where('id', $accountId)
                ->select('rfc')
                ->first();

            if ($acc && !empty($acc->rfc)) {
                $cuentaCli = DB::connection('mysql_clientes')
                    ->table('cuentas_cliente')
                    ->whereRaw('UPPER(rfc_padre)=?', [Str::upper($acc->rfc)])
                    ->first();

                if ($cuentaCli) {
                    $ownerRow = DB::connection('mysql_clientes')
                        ->table('usuarios_cuenta')
                        ->where('cuenta_id', $cuentaCli->id)
                        ->orderByRaw("FIELD(rol,'owner') DESC, FIELD(tipo,'owner') DESC")
                        ->orderBy('created_at','asc')
                        ->first();

                    if ($ownerRow) {
                        $owner = \App\Models\Cliente\UsuarioCuenta::on('mysql_clientes')->find($ownerRow->id);
                        if ($owner) {
                            auth()->login($owner, false);
                            $request->session()->regenerate();
                            $logged = true;
                        }
                    }
                }
            }
        }

        // FINALIZAR ACTIVACIÓN + BIENVENIDA
        $uid = null;
        try {
            $uid = $this->finalizeActivationAndNotify($accountId);
            Log::info('[ACTIVATION] finalizeActivationAndNotify ejecutado', [
                'account_id' => $accountId,
                'usuario_id' => $uid,
            ]);
        } catch (\Throwable $e) {
            Log::error('[ACTIVATION] Error al ejecutar finalizeActivationAndNotify', [
                'account_id' => $accountId,
                'error'      => $e->getMessage(),
            ]);
        }

        // AUDITORÍA (archivo)
        try {
            $accInfo = DB::connection('mysql_admin')
                ->table('accounts')
                ->select('rfc', 'razon_social', 'correo_contacto', 'plan')
                ->where('id', $accountId)
                ->first();

            $auditData = [
                'timestamp'    => now()->toDateTimeString(),
                'event'        => 'ACTIVACION_COMPLETA',
                'account_id'   => $accountId,
                'usuario_id'   => $uid,
                'rfc'          => $accInfo->rfc ?? null,
                'razon_social' => $accInfo->razon_social ?? null,
                'correo'       => $accInfo->correo_contacto ?? null,
                'plan'         => $accInfo->plan ?? null,
                'ip'           => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ];

            $path  = storage_path('logs/p360_audit.log');
            $entry = '[' . now()->format('Y-m-d H:i:s') . '] ' . json_encode($auditData, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);

            Log::info('[AUDIT] Registro de activación guardado', ['account_id' => $accountId]);
        } catch (\Throwable $e) {
            Log::error('[AUDIT] Error al escribir en p360_audit.log', [
                'error'      => $e->getMessage(),
                'account_id' => $accountId,
            ]);
        }

        // Limpiar sesión temporal OTP
        session()->forget([
            'post_verify.user_id',
            'post_verify.remember',
            'verify.otp_code',
            'verify.otp_expires',
            'verify.phone',
        ]);


        return redirect()
            ->route('cliente.home')
            ->with('ok', 'Teléfono verificado correctamente. Tu cuenta ya fue activada.');
    }

    /* =========================================================
     * FINALIZAR ACTIVACIÓN + ENVIAR CORREO DE BIENVENIDA
     * ========================================================= */
    private function finalizeActivationAndNotify(int $adminAccountId): ?string
    {
        $acc = DB::connection('mysql_admin')
            ->table('accounts')
            ->where('id', $adminAccountId)
            ->first();

        if (!$acc) {
            return null;
        }

        $emailOk = !empty($acc->email_verified_at);
        $phoneOk = !empty($acc->phone_verified_at);

        if (!$emailOk || !$phoneOk) {
            Log::warning('[ACTIVATION] Aún faltan verificaciones', [
                'account_id' => $adminAccountId,
                'email_ok'   => $emailOk,
                'phone_ok'   => $phoneOk,
            ]);
            return null;
        }

        $email = $acc->correo_contacto ?? $acc->email ?? null;
        $rfc   = $acc->rfc ?? null;
        $plan  = strtoupper((string)($acc->plan ?? 'FREE'));

        $cuenta = DB::connection('mysql_clientes')
            ->table('cuentas_cliente')
            ->whereRaw('UPPER(rfc_padre)=?', [Str::upper((string)$rfc)])
            ->first();

        if (!$cuenta) {
            Log::error('[ACTIVATION] No se encontró cuenta_cliente', ['rfc' => $rfc]);
            return null;
        }

        $usuario = DB::connection('mysql_clientes')
            ->table('usuarios_cuenta')
            ->where('cuenta_id', $cuenta->id)
            ->where(function ($q) {
                $q->where('tipo', 'owner')
                  ->orWhere('rol', 'owner');
            })
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$usuario) {
            Log::error('[ACTIVATION] No se encontró usuario owner', [
                'cuenta_id' => $cuenta->id,
                'rfc'       => $rfc,
            ]);
            return null;
        }

        DB::connection('mysql_clientes')->beginTransaction();
        try {
            DB::connection('mysql_clientes')
                ->table('usuarios_cuenta')
                ->where('id', $usuario->id)
                ->update([
                    'activo'               => 1,
                    'must_change_password' => 1,
                    'updated_at'           => now(),
                ]);

            DB::connection('mysql_clientes')
                ->table('cuentas_cliente')
                ->where('id', $cuenta->id)
                ->update([
                    'estado_cuenta' => 'activa',
                    'updated_at'    => now(),
                ]);

            DB::connection('mysql_clientes')->commit();
        } catch (\Throwable $e) {
            DB::connection('mysql_clientes')->rollBack();
            Log::error('[ACTIVATION] Error activando cuenta', [
                'error' => $e->getMessage(),
                'account_id' => $adminAccountId,
            ]);
            return null;
        }

        if ($email) {
            try {
                $loginUrl = route('cliente.login');

                $viewData = [
                    'nombre'       => $this->adminDisplayName($acc),
                    'email'        => $email,
                    'rfc'          => $rfc,
                    'loginUrl'     => $loginUrl,
                    'is_pro'       => ($plan === 'PRO'),
                    'soporte'      => 'soporte@pactopia.com',
                    'preheader'    => 'Tu cuenta ya está activa. Inicia sesión y cambia tu contraseña.',
                ];

                Mail::send(
                    [
                        'html' => 'emails.cliente.welcome_active',
                        'text' => 'emails.cliente.welcome_active_text',
                    ],
                    $viewData,
                    fn ($m) => $m->to($email)
                                 ->subject('Tu cuenta ya está activa · Pactopia360')
                );

                Log::info('[EMAIL] Enviado correo de bienvenida final', [
                    'to'        => $email,
                    'accountId' => $adminAccountId,
                    'rfc'       => $rfc,
                    'plan'      => $plan,
                ]);
            } catch (\Throwable $e) {
                Log::error('[EMAIL] Falló envío bienvenida', [
                    'to' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return (string) $usuario->id;
    }

    /* =========================================================
     * HELPERS
     * ========================================================= */

    private function maskPhone(string $phone): string
    {
        if (!$phone) return '';
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) < 4) return $phone;
        return str_repeat('•', max(0, strlen($digits) - 4)) . substr($digits, -4);
    }

    private function createEmailVerificationToken(int $accountId, string $email): string
    {
        $token = Str::random(40);

        DB::connection('mysql_admin')
            ->table('email_verifications')
            ->insert([
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
        // IMPORTANTE: este nombre debe existir en routes/cliente.php -> name('verify.email.token')
        $url = route('cliente.verify.email.token', ['token' => $token]);

        $data = [
            'nombre'     => $nombre,
            'actionUrl'  => $url,
            'soporte'    => 'soporte@pactopia.com',
            'preheader'  => 'Confirma tu correo para activar tu cuenta en Pactopia360.',
        ];

        try {
            Mail::send(
                ['html' => 'emails.cliente.verify_email', 'text' => 'emails.cliente.verify_email_text'],
                $data,
                fn ($m) => $m->to($email)->subject('Confirma tu correo · Pactopia360')
            );

            Log::info('[EMAIL] Enviado enlace de verificación', ['to' => $email, 'url' => $url]);
        } catch (\Throwable $e) {
            Log::error('[EMAIL] Falló envío verificación', ['to' => $email, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Columna REAL de teléfono en mysql_admin.accounts.
     */
    private function adminPhoneColumn(): string
    {
        foreach (['telefono', 'phone', 'tel', 'celular'] as $c) {
            try {
                if (Schema::connection('mysql_admin')->hasColumn('accounts', $c)) {
                    return $c;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return 'telefono';
    }

    private function adminDisplayName(object $account): string
    {
        foreach (['razon_social', 'nombre', 'name', 'nombre_cuenta'] as $c) {
            if (!empty($account->{$c})) {
                return (string) $account->{$c};
            }
        }
        return 'Usuario';
    }

    /**
     * Genera y guarda un OTP, intenta enviarlo por el canal configurado
     * y devuelve [code, expires_timestamp].
     */
    private function generateAndStoreOtp(int $accountId, string $digitsPhone, string $channel): array
    {
        $code    = (string) random_int(100000, 999999);
        $now     = now();
        $expires = $now->copy()->addMinutes(10);

        try {
            $normalizedChannel = ($channel === 'whatsapp') ? 'whatsapp' : 'sms';

            $row = [
                'account_id' => $accountId,
                'phone'      => $digitsPhone,
                'code'       => $code,
                'otp'        => $code,
                'channel'    => $normalizedChannel,
                'attempts'   => 0,
                'expires_at' => $expires,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            DB::connection('mysql_admin')->table('phone_otps')->insert($row);

            // =========================================================
            // ENVÍO OTP (robusto)
            // - En LOCAL: si no hay provider real, simulamos y mostramos el code
            // - En PROD: usa OtpService real
            // =========================================================
            $driver = (string) config('services.otp.driver', '');

            $isLocalEnv = app()->environment(['local','development','testing']);
            $localNoProvider = $isLocalEnv && ($driver === '' || in_array($driver, ['local','fake','log','none'], true));

            $sent = false;

            if ($localNoProvider) {
                // Simulación LOCAL: no intenta WhatsApp/Twilio. Permite probar el flujo completo.
                $sent = true;

                // Guardar para UI (flash) y para logs
                session()->flash('otp_debug_code', $code);

                Log::debug('[OTP][LOCAL] simulated send', [
                    'account_id' => $accountId,
                    'phone'      => $digitsPhone,
                    'code'       => $code,
                    'channel'    => $normalizedChannel,
                    'expires_at' => $expires->toDateTimeString(),
                    'driver'     => $driver ?: '(empty)',
                ]);
            } else {
                try {
                    // Preferimos service container si existe instancia
                    // (por si OtpService no es estático en tu implementación real)
                    $svc = null;
                    try { $svc = app(\App\Services\OtpService::class); } catch (\Throwable $e) { $svc = null; }

                    if ($svc && method_exists($svc, 'send')) {
                        $sent = (bool) $svc->send($digitsPhone, $code, $normalizedChannel);
                    } else {
                        // fallback a estático (tu código actual)
                        $sent = (bool) \App\Services\OtpService::send($digitsPhone, $code, $normalizedChannel);
                    }

                    if (!$sent) {
                        Log::warning('[OTP SEND] Provider returned false', [
                            'account_id' => $accountId,
                            'phone'      => $digitsPhone,
                            'channel'    => $normalizedChannel,
                            'driver'     => $driver ?: null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('[OTP SEND] Exception during send', [
                        'e'          => $e->getMessage(),
                        'account_id' => $accountId,
                        'phone'      => $digitsPhone,
                        'channel'    => $normalizedChannel,
                        'driver'     => $driver ?: null,
                    ]);
                }
            }

            // Debug general (LOCAL)
            if ($isLocalEnv) {
                Log::debug('[OTP-GENERATED]', [
                    'account_id' => $accountId,
                    'phone'      => $digitsPhone,
                    'code'       => $code,
                    'channel'    => $normalizedChannel,
                    'expires_at' => $expires->toDateTimeString(),
                    'sent'       => $sent,
                    'driver'     => $driver ?: '(empty)',
                ]);
            }

            return [$code, $expires->timestamp];

        } catch (\Throwable $e) {
            Log::error('Error al generar OTP', [
                'e'          => $e->getMessage(),
                'account_id' => $accountId,
            ]);
            return [null, null];
        }
    }

}
