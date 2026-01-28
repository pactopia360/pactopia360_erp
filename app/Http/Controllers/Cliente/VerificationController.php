<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VerificationController extends Controller
{
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_TTL_MINUTES  = 10;

    private const CONN_ADMIN   = 'mysql_admin';
    private const CONN_CLIENTE = 'mysql_clientes';

    /** @var array<string, array<int,string>> */
    private array $otpColumnsCache = [];

    /** @var array<string, array<int,string>> */
    private array $accountsColumnsCache = [];

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
        if (Auth::guard('web')->check()) {
            return true;
        }

        try {
            if (Auth::guard('cliente')->check()) {
                return true;
            }
        } catch (\Throwable $e) {
            // ignore
        }

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
        $row = DB::connection(self::CONN_ADMIN)
            ->table('email_verifications')
            ->where('token', $token)
            ->first();

        // ✅ FIX: NO contaminar /cliente/login con session('error').
        if (!$row) {
            if (view()->exists('cliente.auth.verify_email')) {
                return view('cliente.auth.verify_email', [
                    'status'       => 'invalid',
                    'message'      => 'El enlace no es válido o ya fue usado. Solicita uno nuevo.',
                    'email'        => null,
                    'phone_masked' => null,
                ]);
            }

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

        DB::connection(self::CONN_ADMIN)
            ->table('accounts')
            ->where('id', $row->account_id)
            ->update([
                'email_verified_at' => now(),
                'updated_at'        => now(),
            ]);

        DB::connection(self::CONN_ADMIN)
            ->table('email_verifications')
            ->where('account_id', $row->account_id)
            ->delete();

        $resolvedAccountId = null;

        // Resolver columna de teléfono REAL en accounts
        $phoneCol = $this->adminPhoneColumn();

        if ($this->clientIsAuthenticated()) {
            $user       = $this->currentClientUser();
            $userCuenta = $user?->cuenta()?->first();
            if ($userCuenta && !empty($userCuenta->rfc_padre)) {
                $accAdm = DB::connection(self::CONN_ADMIN)
                    ->table('accounts')
                    ->whereRaw('UPPER(rfc)=?', [Str::upper((string) $userCuenta->rfc_padre)])
                    ->select('id', "{$phoneCol} as phone")
                    ->orderByDesc('id')
                    ->first();

                if ($accAdm) {
                    $resolvedAccountId = (int) $accAdm->id;
                }
            }
        }

        if (!$resolvedAccountId) {
            $accAdmByToken = DB::connection(self::CONN_ADMIN)
                ->table('accounts')
                ->where('id', $row->account_id)
                ->select('id', "{$phoneCol} as phone")
                ->first();

            if ($accAdmByToken) {
                $resolvedAccountId = (int) $accAdmByToken->id;
            }
        }

        if ($resolvedAccountId) {
            session([
                'verify.account_id' => $resolvedAccountId,
                'verify.email'      => Str::lower((string) ($row->email ?? '')),
            ]);

            // ✅ Forzar persistencia inmediata
            try {
                session()->save();
                            // ✅ FIX: crear/actualizar espejo en mysql_clientes.accounts
            try {
                $this->ensureClientesAccountMirror((int) $resolvedAccountId);
            } catch (\Throwable $e) {
                Log::warning('[MIRROR] ensureClientesAccountMirror failed (verifyEmail)', [
                    'account_id' => (int) $resolvedAccountId,
                    'e' => $e->getMessage(),
                ]);
            }

            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($resolvedAccountId) {
            return redirect()
                ->route('cliente.verify.phone', ['account_id' => $resolvedAccountId])
                ->with('ok', 'Correo verificado. Ahora verifica tu teléfono.');
        }

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

        $emailFromLink = trim($emailFromLink);

        DB::connection(self::CONN_ADMIN)
            ->table('accounts')
            ->where('id', $accountIdFromLink)
            ->where(function ($q) use ($emailFromLink) {
                $this->whereAccountEmailMatches($q, $emailFromLink);
            })
            ->update([
                'email_verified_at' => now(),
                'updated_at'        => now(),
            ]);

        $resolvedAccountId = null;

        $phoneCol = $this->adminPhoneColumn();

        if ($this->clientIsAuthenticated()) {
            $user       = $this->currentClientUser();
            $userCuenta = $user?->cuenta()?->first();
            if ($userCuenta && !empty($userCuenta->rfc_padre)) {
                $accAdm = DB::connection(self::CONN_ADMIN)
                    ->table('accounts')
                    ->whereRaw('UPPER(rfc)=?', [Str::upper((string) $userCuenta->rfc_padre)])
                    ->select('id', "{$phoneCol} as phone")
                    ->orderByDesc('id')
                    ->first();

                if ($accAdm) {
                    $resolvedAccountId = (int) $accAdm->id;
                }
            }
        }

        if (!$resolvedAccountId) {
            $accAdmByLink = DB::connection(self::CONN_ADMIN)
                ->table('accounts')
                ->where('id', $accountIdFromLink)
                ->select('id', "{$phoneCol} as phone")
                ->first();

            if ($accAdmByLink) {
                $resolvedAccountId = (int) $accAdmByLink->id;
            }
        }

        if ($resolvedAccountId) {
            session([
                'verify.account_id' => $resolvedAccountId,
                'verify.email'      => Str::lower($emailFromLink),
            ]);

            try {
                session()->save();
                            try {
                $this->ensureClientesAccountMirror((int) $resolvedAccountId);
            } catch (\Throwable $e) {
                Log::warning('[MIRROR] ensureClientesAccountMirror failed (verifyEmailSigned)', [
                    'account_id' => (int) $resolvedAccountId,
                    'e' => $e->getMessage(),
                ]);
            }

            } catch (\Throwable $e) {
                // ignore
            }
        }

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
    public function showResendEmail(Request $request)
    {
        // Prefill si viene por query (ej. ?email=...).
        $email = trim((string) $request->query('email', ''));

        // Si ya hay una sesión de verify.email, úsala.
        $sessEmail = (string) session('verify.email', '');
        if ($email === '' && $sessEmail !== '') {
            $email = $sessEmail;
        }

        // IMPORTANTE: esta pantalla SIEMPRE es formulario (no “enlace inválido”)
        return view('cliente.auth.verify_email_resend', [
            'email' => $email !== '' ? Str::lower($email) : '',
        ]);
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

        $email = Str::lower((string) $request->email);

        // ✅ FIX: buscar por columnas reales existentes (evita "Unknown column correo_contacto")
        $account = DB::connection(self::CONN_ADMIN)
            ->table('accounts')
            ->where(function ($q) use ($email) {
                $this->whereAccountEmailMatches($q, $email);
            })
            ->orderByDesc('id')
            ->first();

        if (!$account) {
            return back()
                ->withErrors(['email' => 'No encontramos una cuenta con ese correo.'])
                ->withInput();
        }

        if (!empty($account->email_verified_at)) {
            session([
                'verify.account_id' => (int) $account->id,
                'verify.email'      => $email,
            ]);

            try {
                session()->save();
                try {
                $this->ensureClientesAccountMirror((int) $account->id);
            } catch (\Throwable $e) {
                Log::warning('[MIRROR] ensureClientesAccountMirror failed (resendEmail)', [
                    'account_id' => (int) $account->id,
                    'e' => $e->getMessage(),
                ]);
            }
            } catch (\Throwable $e) {
                // ignore
            }

            return redirect()
                ->route('cliente.verify.phone', ['account_id' => (int) $account->id])
                ->with('ok', 'Ese correo ya está verificado. Continúa con tu teléfono.');
        }

        $token = $this->createEmailVerificationToken((int) $account->id, $email);
        $this->sendEmailVerification($email, $token, $this->adminDisplayName($account));

        session([
            'verify.account_id' => (int) $account->id,
            'verify.email'      => $email,
        ]);

        return back()->with('ok', 'Te enviamos un nuevo enlace de verificación.');
    }

    /**
     * Resolución FINAL del account_id admin que vamos a usar en todo el flujo.
     */
    /**
     * Resolución FINAL del account_id admin que vamos a usar en todo el flujo.
     * Robusto para:
     * - sesión verify.account_id
     * - query/input account_id
     * - sesión estándar del portal
     * - auth:web
     * - fallback por verify.email (lookup en mysql_admin.accounts)
     */
    private function resolveAccountId(Request $request): ?int
    {
        // 1) flujo verificación -> session.verify.account_id
        $aid = (int) session('verify.account_id', 0);
        if ($aid > 0) {
            Log::debug('[OTP-FLOW][resolveAccountId] from session.verify.account_id', ['aid' => $aid]);
            return $aid;
        }

        // 2) request explícito (GET ?account_id=) o POST account_id
        $aid = (int) ($request->query('account_id', 0) ?: $request->input('account_id', 0));
        if ($aid > 0) {
            Log::debug('[OTP-FLOW][resolveAccountId] from request.account_id', [
                'aid'  => $aid,
                'from' => $request->query('account_id') ? 'query' : 'input',
                'path' => $request->path(),
                'full' => $request->fullUrl(),
            ]);

            try {
                session(['verify.account_id' => $aid]);
                session()->save();
            } catch (\Throwable $e) {
                Log::warning('[OTP-FLOW][resolveAccountId] could not persist verify.account_id into session', [
                    'aid' => $aid,
                    'e'   => $e->getMessage(),
                ]);
            }

            return $aid;
        }

        // 3) sesiones estándar del portal cliente
        foreach (['cliente.account_id', 'client.account_id', 'account_id', 'cliente.cuenta_id', 'client.cuenta_id', 'cuenta_id'] as $k) {
            $v = (int) session($k, 0);
            if ($v > 0) {
                Log::debug('[OTP-FLOW][resolveAccountId] from session', ['key' => $k, 'aid' => $v]);
                return $v;
            }
        }

        // 4) fallback por auth:web
        try {
            $u = auth('web')->user();
            if ($u) {
                $direct = (int) data_get($u, 'account_id', 0);
                if ($direct > 0) {
                    Log::debug('[OTP-FLOW][resolveAccountId] from auth.user.account_id', ['aid' => $direct]);
                    return $direct;
                }

                $rel = (int) data_get($u, 'cuenta.account_id', 0);
                if ($rel > 0) {
                    Log::debug('[OTP-FLOW][resolveAccountId] from auth.user.cuenta.account_id', ['aid' => $rel]);
                    return $rel;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[OTP-FLOW][resolveAccountId] auth fallback failed', ['e' => $e->getMessage()]);
        }

        // 5) fallback por email en sesión (MUY importante para cuando llegan directo a /cliente/verificar/telefono)
        $byEmail = $this->resolveAccountIdFromVerifyEmailSession($request);
        if ($byEmail && $byEmail > 0) {
            return $byEmail;
        }

        Log::warning('[OTP-FLOW][resolveAccountId] could not resolve account id', [
            'path' => $request->path(),
            'full' => $request->fullUrl(),
            'verify_email' => (string) session('verify.email', ''),
        ]);

        return null;
    }

    /**
     * Fallback: si hay verify.email en sesión, intenta resolver account_id en mysql_admin.accounts
     * usando columnas reales existentes (adminEmailColumns()).
     *
     * Esto corrige el caso producción donde abren /cliente/verificar/telefono sin querystring account_id.
     */
    private function resolveAccountIdFromVerifyEmailSession(Request $request): ?int
    {
        $email = trim((string) session('verify.email', ''));
        if ($email === '') {
            return null;
        }

        $email = Str::lower($email);

        try {
            $acc = DB::connection(self::CONN_ADMIN)
                ->table('accounts')
                ->where(function ($q) use ($email) {
                    $this->whereAccountEmailMatches($q, $email);
                })
                ->select('id')
                ->orderByDesc('id')
                ->first();

            if (!$acc || empty($acc->id)) {
                Log::warning('[OTP-FLOW][resolveAccountId] email fallback: account not found', [
                    'email' => $email,
                    'path'  => $request->path(),
                ]);
                return null;
            }

            $aid = (int) $acc->id;

            // Persistimos para el resto del flujo
            try {
                session(['verify.account_id' => $aid]);
                session()->save();
            } catch (\Throwable $e) {
                Log::warning('[OTP-FLOW][resolveAccountId] email fallback: could not persist session', [
                    'aid' => $aid,
                    'e'   => $e->getMessage(),
                ]);
            }

            Log::debug('[OTP-FLOW][resolveAccountId] from verify.email fallback', [
                'aid'   => $aid,
                'email' => $email,
            ]);

            return $aid;
        } catch (\Throwable $e) {
            Log::warning('[OTP-FLOW][resolveAccountId] email fallback failed', [
                'email' => $email,
                'e'     => $e->getMessage(),
            ]);
            return null;
        }
    }



    public function finalizeActivationAndSendCredentialsByRfc(string $rfc): void
    {
        if (method_exists($this, 'finalizeActivationAndSendCredentials')) {
            $this->finalizeActivationAndSendCredentials($rfc);
            return;
        }

        Log::warning('finalizeActivationAndSendCredentialsByRfc: no-op (missing implementation)', ['rfc' => $rfc]);
    }

    private function fetchAccountEmailById(int $accountId): string
    {
        $schemaAdmin = Schema::connection(self::CONN_ADMIN);
        $emailCol    = $schemaAdmin->hasColumn('accounts', 'correo_contacto')
            ? 'correo_contacto'
            : ($schemaAdmin->hasColumn('accounts', 'email') ? 'email' : 'email');

        $acc = DB::connection(self::CONN_ADMIN)
            ->table('accounts')
            ->where('id', $accountId)
            ->select($emailCol . ' as email')
            ->first();

        return $acc ? Str::lower((string) ($acc->email ?? '')) : '';
    }

    /**
     * GET /cliente/verificar/telefono
     */
    public function showOtp(Request $request)
    {
        $accountId = $this->resolveAccountId($request);

        // Asegura que quede persistido (evita perder el accountId entre requests)
        if ($accountId && (int) session('verify.account_id', 0) !== (int) $accountId) {
            try {
                session(['verify.account_id' => (int) $accountId]);
                session()->save();
            } catch (\Throwable $e) {
                // ignore
            }
        }


        if (app()->environment(['local', 'development', 'testing'])) {
            Log::debug('[OTP-FLOW][DEBUG showOtp FINAL]', [
                'auth_web_id'        => Auth::guard('web')->id(),
                'auth_cli_id'        => (function () {
                    try {
                        return Auth::guard('cliente')->id();
                    } catch (\Throwable $e) {
                        return null;
                    }
                })(),
                'session_verify_acc' => session('verify.account_id'),
                'final_account_id'   => $accountId,
            ]);
        }

        if (!$accountId) {
            return redirect()
                ->route('cliente.verify.email.resend')
                ->withErrors([
                    'email' => 'No pudimos ubicar tu cuenta para actualizar el teléfono. Solicita un enlace nuevo.',
                ]);
        }

        // ✅ FIX: asegurar espejo antes de mostrar la pantalla
        try {
            $this->ensureClientesAccountMirror((int) $accountId);
        } catch (\Throwable $e) {
            Log::warning('[MIRROR] ensureClientesAccountMirror failed (showOtp)', [
                'account_id' => (int) $accountId,
                'e' => $e->getMessage(),
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
            $account = DB::connection(self::CONN_ADMIN)
                ->table('accounts')
                ->select('id', "{$phoneCol} as phone")
                ->where('id', $accountId)
                ->first();
        }

        $safeAccount = $account
            ? (object) [
                'id'    => $account->id,
                'phone' => $account->phone,
            ]
            : (object) [
                'id'    => null,
                'phone' => null,
            ];

        $phoneMasked = $this->maskPhone((string) ($safeAccount->phone ?? ''));

        $hasOtpSession = session()->has('verify.otp_code');
        $state         = ($hasOtpSession || !empty($safeAccount->phone))
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

        $country = trim((string) $request->country_code);
        $line    = trim((string) $request->telefono);
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

        DB::connection(self::CONN_ADMIN)
            ->table('accounts')
            ->where('id', $accountId)
            ->update([
                $phoneCol    => $full,
                'updated_at' => now(),
            ]);

        $channel = (string) config('services.otp.channel', 'whatsapp'); // whatsapp|sms
        $digits  = preg_replace('/\D+/', '', (string) $full);

        [$code, $expiresTs] = $this->generateAndStoreOtp(
            $accountId,
            (string) $digits,
            (string) $channel
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

        $accAdm = DB::connection(self::CONN_ADMIN)
            ->table('accounts')
            ->where('id', $accountId)
            ->select('id', "{$phoneCol} as phone")
            ->first();

        $rawPhone   = (string) ($accAdm?->phone ?? '');
        $onlyDigits = preg_replace('/\D+/', '', $rawPhone);

        $channel = (string) config('services.otp.channel', 'whatsapp'); // whatsapp|sms

        [$code, $expiresTs] = $this->generateAndStoreOtp(
            $accountId,
            (string) $onlyDigits,
            (string) $channel
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
        $inputCode = (string) $request->code;

        Log::info('[OTP-FLOW][3] checkOtp start', [
            'input_code'          => $inputCode,
            'session_account_id'  => session('verify.account_id'),
            'resolved_account_id' => $accountId,
        ]);

        if (!$accountId) {
            return back()->withErrors(['general' => 'Sesión no válida.']);
        }

        [$otpConn, $otpRow] = $this->findLatestOtpRow($accountId);

        if (!$otpRow) {
            return back()->withErrors(['code' => 'No existe ningún código activo.']);
        }

        // Bloqueo por intentos
        if ((int) ($otpRow->attempts ?? 0) >= self::OTP_MAX_ATTEMPTS) {
            return back()->withErrors(['code' => 'Demasiados intentos. Solicita un código nuevo.']);
        }

        if (!empty($otpRow->expires_at) && now()->greaterThan($otpRow->expires_at)) {
            Log::warning('[OTP-FLOW][5] OTP expirado', [
                'otp_id'     => $otpRow->id ?? null,
                'conn'       => $otpConn,
                'expires_at' => $otpRow->expires_at,
            ]);
            return back()->withErrors(['code' => 'El código expiró.']);
        }

        // Comparación estricta
        $dbCodeA = (string) ($otpRow->code ?? '');
        $dbCodeB = (string) ($otpRow->otp ?? '');

        if ($dbCodeA !== $inputCode && $dbCodeB !== $inputCode) {
            DB::connection($otpConn)
                ->table('phone_otps')
                ->where('id', $otpRow->id)
                ->update([
                    'attempts'   => DB::raw('attempts + 1'),
                    'updated_at' => now(),
                ]);

            // Best-effort mirror a la otra BD (no crítico)
            try {
                $this->mirrorOtpAttemptIncrement($accountId, $inputCode);
            } catch (\Throwable $e) {
                Log::debug('[OTP-FLOW][6B] mirrorOtpAttemptIncrement failed', [
                    'account_id' => $accountId,
                    'err'        => $e->getMessage(),
                ]);
            }

            Log::warning('[OTP-FLOW][6] Código incorrecto', [
                'input_code' => $inputCode,
                'db_code'    => $dbCodeA,
                'db_otp'     => $dbCodeB,
                'conn'       => $otpConn,
            ]);

            return back()->withErrors(['code' => 'Código incorrecto.']);
        }

        // éxito → marcar usado
        DB::connection($otpConn)
            ->table('phone_otps')
            ->where('id', $otpRow->id)
            ->update([
                'used_at'    => now(),
                'updated_at' => now(),
            ]);

        // Best-effort mirror a la otra BD (no crítico)
        try {
            $this->mirrorOtpMarkUsed($accountId, $inputCode);
        } catch (\Throwable $e) {
            Log::debug('[OTP-FLOW][7B] mirrorOtpMarkUsed failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
        }

        DB::connection(self::CONN_ADMIN)
            ->table('accounts')
            ->where('id', $accountId)
            ->update([
                'phone_verified_at' => now(),
                'updated_at'        => now(),
            ]);

                    try {
            $this->ensureClientesAccountMirror((int) $accountId);
        } catch (\Throwable $e) {
            Log::warning('[MIRROR] ensureClientesAccountMirror failed (checkOtp)', [
                'account_id' => (int) $accountId,
                'e' => $e->getMessage(),
            ]);
        }


        Log::info('[OTP-FLOW][7] OTP OK, phone_verified_at set', [
            'account_id' => $accountId,
            'otp_conn'   => $otpConn,
            'otp_id'     => $otpRow->id ?? null,
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
            $user = \App\Models\Cliente\UsuarioCuenta::on(self::CONN_CLIENTE)->find($userId);
            if ($user) {
                auth()->login($user, $remember);
                $request->session()->regenerate();
                $logged = true;
            }
        }

        // Plan B: por RFC (case-insensitive)
        if (!$logged) {
            $acc = DB::connection(self::CONN_ADMIN)
                ->table('accounts')
                ->where('id', $accountId)
                ->select('rfc')
                ->first();

            if ($acc && !empty($acc->rfc)) {
                $cuentaCli = DB::connection(self::CONN_CLIENTE)
                    ->table('cuentas_cliente')
                    ->whereRaw('UPPER(rfc_padre)=?', [Str::upper((string) $acc->rfc)])
                    ->first();

                if ($cuentaCli) {
                    $ownerRow = DB::connection(self::CONN_CLIENTE)
                        ->table('usuarios_cuenta')
                        ->where('cuenta_id', $cuentaCli->id)
                        ->orderByRaw("FIELD(rol,'owner') DESC, FIELD(tipo,'owner') DESC")
                        ->orderBy('created_at', 'asc')
                        ->first();

                    if ($ownerRow) {
                        $owner = \App\Models\Cliente\UsuarioCuenta::on(self::CONN_CLIENTE)->find($ownerRow->id);
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
            $emailCol = $this->adminPrimaryEmailColumn();

            $accInfo = DB::connection(self::CONN_ADMIN)
                ->table('accounts')
                ->select('rfc', 'razon_social', "{$emailCol} as correo", 'plan')
                ->where('id', $accountId)
                ->first();

            $auditData = [
                'timestamp'    => now()->toDateTimeString(),
                'event'        => 'ACTIVACION_COMPLETA',
                'account_id'   => $accountId,
                'usuario_id'   => $uid,
                'rfc'          => $accInfo->rfc ?? null,
                'razon_social' => $accInfo->razon_social ?? null,
                'correo'       => $accInfo->correo ?? null,
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
        $acc = DB::connection(self::CONN_ADMIN)
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

        // ✅ FIX: columna real de email
        $emailCol = $this->adminPrimaryEmailColumn();
        $email    = (string) (data_get($acc, $emailCol) ?? data_get($acc, 'email') ?? '');
        $email    = $email ? Str::lower($email) : null;

        $rfc   = $acc->rfc ?? null;
        $plan  = strtoupper((string) ($acc->plan ?? 'FREE'));

        $cuenta = DB::connection(self::CONN_CLIENTE)
            ->table('cuentas_cliente')
            ->whereRaw('UPPER(rfc_padre)=?', [Str::upper((string) $rfc)])
            ->first();

        if (!$cuenta) {
            Log::error('[ACTIVATION] No se encontró cuenta_cliente', ['rfc' => $rfc]);
            return null;
        }

        $usuario = DB::connection(self::CONN_CLIENTE)
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

        DB::connection(self::CONN_CLIENTE)->beginTransaction();
        try {
            DB::connection(self::CONN_CLIENTE)
                ->table('usuarios_cuenta')
                ->where('id', $usuario->id)
                ->update([
                    'activo'               => 1,
                    'must_change_password' => 1,
                    'updated_at'           => now(),
                ]);

            DB::connection(self::CONN_CLIENTE)
                ->table('cuentas_cliente')
                ->where('id', $cuenta->id)
                ->update([
                    'estado_cuenta' => 'activa',
                    'updated_at'    => now(),
                ]);

            DB::connection(self::CONN_CLIENTE)->commit();
        } catch (\Throwable $e) {
            DB::connection(self::CONN_CLIENTE)->rollBack();
            Log::error('[ACTIVATION] Error activando cuenta', [
                'error'      => $e->getMessage(),
                'account_id' => $adminAccountId,
            ]);
            return null;
        }

        if ($email) {
            try {
                $loginUrl = route('cliente.login');

                $viewData = [
                    'nombre'    => $this->adminDisplayName($acc),
                    'email'     => $email,
                    'rfc'       => $rfc,
                    'loginUrl'  => $loginUrl,
                    'is_pro'    => ($plan === 'PRO'),
                    'soporte'   => 'soporte@pactopia.com',
                    'preheader' => 'Tu cuenta ya está activa. Inicia sesión y cambia tu contraseña.',
                ];

                Mail::send(
                    [
                        'html' => 'emails.cliente.welcome_active',
                        'text' => 'emails.cliente.welcome_active_text',
                    ],
                    $viewData,
                    fn ($m) => $m->to($email)->subject('Tu cuenta ya está activa · Pactopia360')
                );

                Log::info('[EMAIL] Enviado correo de bienvenida final', [
                    'to'        => $email,
                    'accountId' => $adminAccountId,
                    'rfc'       => $rfc,
                    'plan'      => $plan,
                ]);
            } catch (\Throwable $e) {
                Log::error('[EMAIL] Falló envío bienvenida', [
                    'to'    => $email,
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
        if (!$phone) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen((string) $digits) < 4) {
            return $phone;
        }

        $digits = (string) $digits;
        return str_repeat('•', max(0, strlen($digits) - 4)) . substr($digits, -4);
    }

    private function createEmailVerificationToken(int $accountId, string $email): string
    {
        $token = Str::random(40);

        DB::connection(self::CONN_ADMIN)
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
        $url = route('cliente.verify.email.token', ['token' => $token]);

        $data = [
            'nombre'    => $nombre,
            'actionUrl' => $url,
            'soporte'   => 'soporte@pactopia.com',
            'preheader' => 'Confirma tu correo para activar tu cuenta en Pactopia360.',
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
                if (Schema::connection(self::CONN_ADMIN)->hasColumn('accounts', $c)) {
                    return $c;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return 'telefono';
    }

    /**
     * Columnas reales de email en mysql_admin.accounts (orden de preferencia).
     *
     * @return array<int,string>
     */
    private function adminEmailColumns(): array
    {
        if (isset($this->accountsColumnsCache['email_cols'])) {
            return $this->accountsColumnsCache['email_cols'];
        }

        $candidates = ['correo_contacto', 'email', 'correo', 'email_contacto'];
        $found = [];

        foreach ($candidates as $c) {
            try {
                if (Schema::connection(self::CONN_ADMIN)->hasColumn('accounts', $c)) {
                    $found[] = $c;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // si no detecta, por lo menos intenta con email
        if (empty($found)) {
            $found = ['email'];
        }

        return $this->accountsColumnsCache['email_cols'] = $found;
    }

    /**
     * Columna primaria de email (la primera disponible).
     */
    private function adminPrimaryEmailColumn(): string
    {
        $cols = $this->adminEmailColumns();
        return (string) ($cols[0] ?? 'email');
    }

    /**
     * Aplica WHERE para encontrar una cuenta por email usando las columnas reales existentes.
     * - Usa adminEmailColumns() (detecta correo_contacto/email/etc)
     * - Hace comparación case-insensitive (LOWER)
     */
    private function whereAccountEmailMatches($q, string $email): void
    {
        $email = Str::lower(trim($email));
        $cols  = $this->adminEmailColumns();

        $q->where(function ($qq) use ($cols, $email) {
            $first = true;

            foreach ($cols as $col) {
                // LOWER(col) = email
                if ($first) {
                    $qq->whereRaw("LOWER(`{$col}`) = ?", [$email]);
                    $first = false;
                } else {
                    $qq->orWhereRaw("LOWER(`{$col}`) = ?", [$email]);
                }
            }
        });
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

        /* =========================================================
     * MIRROR: mysql_clientes.accounts (correo_contacto/telefono)
     * ========================================================= */

    private function ensureClientesAccountMirror(int $adminAccountId): void
    {
        if ($adminAccountId <= 0) return;

        try {
            if (!Schema::connection(self::CONN_CLIENTE)->hasTable('accounts')) return;
        } catch (\Throwable) {
            return;
        }

        $phoneCol = $this->adminPhoneColumn();
        $emailCol = $this->adminPrimaryEmailColumn();

        $select = ['id', 'is_blocked', 'updated_at'];

        foreach (['rfc', 'razon_social', 'plan', 'billing_cycle', 'billing_status', 'estado_cuenta'] as $c) {
            try {
                if (Schema::connection(self::CONN_ADMIN)->hasColumn('accounts', $c)) {
                    $select[] = $c;
                }
            } catch (\Throwable) {}
        }

        $select[] = DB::raw("`{$emailCol}` as _email");
        $select[] = DB::raw("`{$phoneCol}` as _phone");

        $acc = DB::connection(self::CONN_ADMIN)
            ->table('accounts')
            ->where('id', $adminAccountId)
            ->select($select)
            ->first();

        if (!$acc) return;

        $email = Str::lower(trim((string) ($acc->_email ?? '')));
        if ($email === '') return;

        $rfc = trim((string) ($acc->rfc ?? ''));
        if ($rfc === '') $rfc = 'XAXX010101000';

        $razon = trim((string) ($acc->razon_social ?? ''));
        if ($razon === '') {
            $razon = $this->adminDisplayName($acc);
            if ($razon === '' || $razon === 'Usuario') $razon = 'CLIENTE';
        }

        $telefono = trim((string) ($acc->_phone ?? ''));

        $planCli = $this->mapClientePlanFromAdmin($acc);
        $cycle   = $this->mapClienteBillingCycleFromAdmin($acc);

        $billingStatus = 'activa';
        $isBlocked = (int) ($acc->is_blocked ?? 0);

        if ($isBlocked === 1) {
            $billingStatus = 'bloqueada';
        } else {
            $tmp = trim((string) ($acc->billing_status ?? ''));
            if ($tmp !== '') {
                $billingStatus = $tmp;
            } else {
                $tmp2 = trim((string) ($acc->estado_cuenta ?? ''));
                if ($tmp2 !== '') {
                    if (in_array($tmp2, ['activa','activo','active'], true))  $billingStatus = 'activa';
                    if (in_array($tmp2, ['pendiente','pending'], true))      $billingStatus = 'pendiente';
                    if (in_array($tmp2, ['bloqueada','blocked'], true))      $billingStatus = 'bloqueada';
                }
            }
        }

        $cols = [];
        try {
            $cols = Schema::connection(self::CONN_CLIENTE)->getColumnListing('accounts');
            $cols = array_values(array_map('strval', $cols));
        } catch (\Throwable) {
            $cols = [];
        }

        $now = now();

        $existing = DB::connection(self::CONN_CLIENTE)
            ->table('accounts')
            ->where('correo_contacto', $email)
            ->first();

        if ($existing) {
            $upd = [
                'rfc'            => $rfc,
                'razon_social'   => $razon,
                'telefono'       => $telefono,
                'plan'           => $planCli,
                'billing_cycle'  => $cycle,
                'billing_status' => $billingStatus,
                'is_blocked'     => $isBlocked,
                'updated_at'     => $now,
            ];

            $filtered = [];
            foreach ($upd as $k => $v) {
                if (empty($cols) || in_array($k, $cols, true)) $filtered[$k] = $v;
            }

            DB::connection(self::CONN_CLIENTE)
                ->table('accounts')
                ->where('id', $existing->id)
                ->update($filtered);

            return;
        }

        $ins = [
            'rfc'              => $rfc,
            'razon_social'     => $razon,
            'correo_contacto'  => $email,
            'telefono'         => $telefono,
            'plan'             => $planCli,
            'billing_cycle'    => $cycle,
            'billing_status'   => $billingStatus,
            'next_invoice_date'=> null,
            'is_blocked'       => $isBlocked,
            'user_code'        => strtoupper(Str::random(8)),
            'created_at'       => $now,
            'updated_at'       => $now,
        ];

        $filtered = [];
        foreach ($ins as $k => $v) {
            if (empty($cols) || in_array($k, $cols, true)) $filtered[$k] = $v;
        }

        DB::connection(self::CONN_CLIENTE)->table('accounts')->insert($filtered);
    }

    private function mapClientePlanFromAdmin(object $acc): string
    {
        $p = Str::lower(trim((string) ($acc->plan ?? '')));
        if ($p === 'pro') return 'premium';
        if ($p === 'premium') return 'premium';
        if ($p === 'basic') return 'basic';
        if ($p === 'free') return 'free';
        return 'premium';
    }

    private function mapClienteBillingCycleFromAdmin(object $acc): string
    {
        $c = Str::lower(trim((string) ($acc->billing_cycle ?? '')));
        if (in_array($c, ['monthly','mensual'], true)) return 'monthly';
        if (in_array($c, ['annual','yearly','anual'], true)) return 'annual';
        return 'monthly';
    }


    /**
     * Genera y guarda un OTP, intenta enviarlo por el canal configurado
     * y devuelve [code, expires_timestamp].
     *
     * MEJORA: Dual-write (mysql_admin + mysql_clientes best-effort)
     */
    private function generateAndStoreOtp(int $accountId, string $digitsPhone, string $channel): array
    {
        $digitsPhone = preg_replace('/\D+/', '', (string) $digitsPhone);

        if (!$accountId || !$digitsPhone) {
            return [null, null];
        }

        $code    = (string) random_int(100000, 999999);
        $now     = now();
        $expires = $now->copy()->addMinutes(self::OTP_TTL_MINUTES);

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
                'used_at'    => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Source of truth
            $this->insertOtpRowFiltered(self::CONN_ADMIN, $row);

            // Dual-write best-effort a clientes (no rompe flujo si falla)
            try {
                $this->replicateOtpToClientes($row);
            } catch (\Throwable $e) {
                Log::warning('[OTP-FLOW][2B] replicateOtpToClientes failed', [
                    'account_id' => $accountId,
                    'err'        => $e->getMessage(),
                ]);
            }

            // =========================================================
            // ENVÍO OTP (robusto)
            // - En LOCAL: si no hay provider real, simulamos y mostramos el code
            // - En PROD: usa OtpService real
            // =========================================================
            $driver = (string) config('services.otp.driver', '');

            $isLocalEnv      = app()->environment(['local', 'development', 'testing']);
            $localNoProvider = $isLocalEnv && ($driver === '' || in_array($driver, ['local', 'fake', 'log', 'none'], true));

            $sent = false;

            if ($localNoProvider) {
                $sent = true;
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
                    $svc = null;
                    try {
                        $svc = app(OtpService::class);
                    } catch (\Throwable $e) {
                        $svc = null;
                    }

                    if ($svc && method_exists($svc, 'send')) {
                        $sent = (bool) $svc->send($digitsPhone, $code, $normalizedChannel);
                    } else {
                        $sent = (bool) OtpService::send($digitsPhone, $code, $normalizedChannel);
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

    /**
     * Busca el último OTP por account_id:
     * - Primero en mysql_admin (source of truth),
     * - Fallback a mysql_clientes si no existe.
     *
     * @return array{0:string,1:object|null} [conn, row]
     */
    private function findLatestOtpRow(int $accountId): array
    {
        $primary = self::CONN_ADMIN;
        $alt     = self::CONN_CLIENTE;

        $row = DB::connection($primary)
            ->table('phone_otps')
            ->where('account_id', $accountId)
            ->orderByDesc('id')
            ->first();

        if ($row) {
            return [$primary, $row];
        }

        $row2 = DB::connection($alt)
            ->table('phone_otps')
            ->where('account_id', $accountId)
            ->orderByDesc('id')
            ->first();

        if ($row2) {
            return [$alt, $row2];
        }

        return [$primary, null];
    }

    /**
     * Replica OTP a mysql_clientes (best-effort).
     * Nota: Filtra columnas para evitar fallos por esquemas distintos.
     *
     * @param array<string,mixed> $row
     */
    private function replicateOtpToClientes(array $row): void
    {
        // Si no existe tabla en clientes, no hacemos nada
        try {
            if (!Schema::connection(self::CONN_CLIENTE)->hasTable('phone_otps')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $accountId = (int) ($row['account_id'] ?? 0);
        $code      = (string) ($row['code'] ?? '');
        $otp       = (string) ($row['otp'] ?? $code);

        if ($accountId <= 0 || $code === '') {
            return;
        }

        // Evitar duplicar el mismo código reciente
        $exists = DB::connection(self::CONN_CLIENTE)
            ->table('phone_otps')
            ->where('account_id', $accountId)
            ->where(function ($q) use ($code, $otp) {
                $q->where('code', $code)->orWhere('otp', $otp);
            })
            ->where('created_at', '>=', now()->subMinutes(30))
            ->exists();

        if ($exists) {
            return;
        }

        $this->insertOtpRowFiltered(self::CONN_CLIENTE, $row);
    }

    /**
     * Espejo best-effort del incremento de attempts en ambas conexiones.
     */
    private function mirrorOtpAttemptIncrement(int $accountId, string $inputCode): void
    {
        $conns = [self::CONN_ADMIN, self::CONN_CLIENTE];

        foreach ($conns as $conn) {
            try {
                if (!Schema::connection($conn)->hasTable('phone_otps')) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }

            $row = DB::connection($conn)
                ->table('phone_otps')
                ->where('account_id', $accountId)
                ->where(function ($q) use ($inputCode) {
                    $q->where('code', $inputCode)->orWhere('otp', $inputCode);
                })
                ->orderByDesc('id')
                ->first();

            if ($row) {
                DB::connection($conn)
                    ->table('phone_otps')
                    ->where('id', $row->id)
                    ->update($this->filterOtpUpdateColumns($conn, [
                        'attempts'   => DB::raw('attempts + 1'),
                        'updated_at' => now(),
                    ]));
            }
        }
    }

    /**
     * Espejo best-effort de marcar como usado el OTP en ambas conexiones.
     */
    private function mirrorOtpMarkUsed(int $accountId, string $inputCode): void
    {
        $conns = [self::CONN_ADMIN, self::CONN_CLIENTE];

        foreach ($conns as $conn) {
            try {
                if (!Schema::connection($conn)->hasTable('phone_otps')) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }

            $row = DB::connection($conn)
                ->table('phone_otps')
                ->where('account_id', $accountId)
                ->where(function ($q) use ($inputCode) {
                    $q->where('code', $inputCode)->orWhere('otp', $inputCode);
                })
                ->orderByDesc('id')
                ->first();

            if ($row) {
                DB::connection($conn)
                    ->table('phone_otps')
                    ->where('id', $row->id)
                    ->update($this->filterOtpUpdateColumns($conn, [
                        'used_at'    => now(),
                        'updated_at' => now(),
                    ]));
            }
        }
    }

    /**
     * Inserta un row en phone_otps filtrando columnas por conexión
     * (para que no truene si el esquema difiere entre mysql_admin y mysql_clientes).
     *
     * @param array<string,mixed> $row
     */
    private function insertOtpRowFiltered(string $conn, array $row): void
    {
        $cols = $this->otpColumns($conn);

        if (empty($cols)) {
            // fallback mínimo
            DB::connection($conn)->table('phone_otps')->insert($row);
            return;
        }

        $filtered = [];
        foreach ($row as $k => $v) {
            if (in_array($k, $cols, true)) {
                $filtered[$k] = $v;
            }
        }

        // Asegura timestamps si existen
        if (in_array('created_at', $cols, true) && !array_key_exists('created_at', $filtered)) {
            $filtered['created_at'] = now();
        }
        if (in_array('updated_at', $cols, true) && !array_key_exists('updated_at', $filtered)) {
            $filtered['updated_at'] = now();
        }

        DB::connection($conn)->table('phone_otps')->insert($filtered);
    }

    /**
     * Filtra updates por columnas existentes (evita fallos por columnas faltantes).
     *
     * @param array<string,mixed> $update
     * @return array<string,mixed>
     */
    private function filterOtpUpdateColumns(string $conn, array $update): array
    {
        $cols = $this->otpColumns($conn);
        if (empty($cols)) {
            return $update;
        }

        $filtered = [];
        foreach ($update as $k => $v) {
            if (in_array($k, $cols, true)) {
                $filtered[$k] = $v;
            }
        }
        return $filtered;
    }

    /**
     * Column listing cache para phone_otps por conexión.
     *
     * @return array<int,string>
     */
    private function otpColumns(string $conn): array
    {
        if (isset($this->otpColumnsCache[$conn])) {
            return $this->otpColumnsCache[$conn];
        }

        try {
            if (!Schema::connection($conn)->hasTable('phone_otps')) {
                return $this->otpColumnsCache[$conn] = [];
            }
        } catch (\Throwable $e) {
            return $this->otpColumnsCache[$conn] = [];
        }

        try {
            // getColumnListing está disponible en Schema\Builder
            $cols = Schema::connection($conn)->getColumnListing('phone_otps');
            return $this->otpColumnsCache[$conn] = array_values(array_map('strval', $cols));
        } catch (\Throwable $e) {
            return $this->otpColumnsCache[$conn] = [];
        }
    }
}
