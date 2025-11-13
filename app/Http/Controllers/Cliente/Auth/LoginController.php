<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente\UsuarioCuenta;
use App\Models\Cliente\CuentaCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Support\ClientAuth;

class LoginController extends Controller
{
    /* ============================================================
     | VISTAS
     * ============================================================*/
    public function showLogin(Request $request)
    {
        // Aseguramos que este flujo SIEMPRE trabaje con el guard 'web'
        Auth::shouldUse('web');

        // Si YA está logueado en guard web, mándalo directo a su dashboard cliente
        if (Auth::guard('web')->check()) {
            return redirect()->intended(
                \Route::has('cliente.home') ? route('cliente.home') : '/'
            );
        }

        return view('cliente.auth.login');
    }

    /* ============================================================
     | LOGIN (correo o RFC)
     * ============================================================*/
    public function login(Request $request)
    {
        Auth::shouldUse('web');

        $reqId      = (string) Str::ulid();
        $identifier = trim((string) $request->input('login', $request->input('email', '')));
        $password   = (string) $request->input('password', '');
        $remember   = $request->boolean('remember');

        try {
            if (!Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta', 'remember_token')) {
                $remember = false;
            }
        } catch (\Throwable $e) { $remember = false; }

        $diag = [];
        $this->d($diag, $reqId, 'Start', [
            'identifier' => $identifier,
            'has_csrf'   => (bool) $request->session()->token(),
            'ip'         => $request->ip(),
        ]);

        $request->merge(['identifier' => $identifier]);
        $request->validate([
            'identifier' => 'required|string|max:150',
            'password'   => 'required|string|min:6|max:100',
        ], [
            'identifier.required' => 'Ingresa tu correo o tu RFC.',
            'password.required'   => 'Ingresa tu contraseña.',
        ]);

        if ($this->tooManyAttempts($request, $identifier)) {
            $wait = $this->remainingLockSeconds($request, $identifier);
            return $this->failBack("Demasiados intentos. Intenta de nuevo en {$wait}s.", $identifier, $diag, $reqId, 'E0: throttled');
        }

        $usuario = null;
        $cuenta  = null;

        // ¿Email o RFC?
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $this->d($diag, $reqId, 'Identifier type', ['is_email' => $isEmail]);

        if ($isEmail) {
            // -------- EMAIL --------
            $email   = strtolower($identifier);
            $usuario = UsuarioCuenta::on('mysql_clientes')->where('email', $email)->first();
            $this->d($diag, $reqId, 'User by email', ['found' => (bool) $usuario, 'user_id' => $usuario->id ?? null]);

            if (!$usuario) {
                $this->hitThrottle($request, $identifier);
                return $this->invalid($identifier, $diag, $reqId, 'E1: email no existe en usuarios_cuenta');
            }

            $cuenta = $usuario->cuenta ?: CuentaCliente::on('mysql_clientes')->find($usuario->cuenta_id);

            $hashRaw = (string) $usuario->getRawOriginal('password');
            if (!$this->passwordMatchesAny($password, $hashRaw)) {
                $this->hitThrottle($request, $identifier);
                return $this->invalid($identifier, $diag, $reqId, 'E3: pass mismatch (email)');
            }

        } else {
            // -------- RFC --------
            $rfcInput  = (string) $identifier;
            $rfcUpper  = Str::upper($rfcInput);
            $rfcSan    = $this->sanitizeRfc($rfcUpper);
            $rfcColCli = $this->rfcColumnClientes();

            $this->d($diag, $reqId, 'RFC normalized', [
                'input' => $rfcInput, 'rfc_upper' => $rfcUpper, 'rfc_sanitized' => $rfcSan, 'rfc_col' => $rfcColCli
            ]);

            $cuentas = CuentaCliente::on('mysql_clientes')
                ->where(function ($q) use ($rfcUpper, $rfcSan, $rfcColCli) {
                    $q->whereRaw("UPPER($rfcColCli) = ?", [$rfcUpper])
                    ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER('.$rfcColCli.')," ",""),"-",""),"_",""),".",""),"/","") = ?', [$rfcSan]);
                })
                ->get();

            $this->d($diag, $reqId, 'Cuentas candidate por RFC', ['count' => $cuentas->count()]);

            if ($cuentas->isEmpty()) {
                $this->hitThrottle($request, $identifier);
                if (!$this->looksLikeRfc($rfcSan)) {
                    return $this->failValidation('El RFC no tiene un formato válido.', $diag, $reqId, 'E4: RFC inválido');
                }
                return $this->invalid($identifier, $diag, $reqId, 'E5: RFC no corresponde a ninguna cuenta');
            }

            [$cuenta, $usuariosAll, $rankDump] = $this->pickBestCuentaByRfcCandidates($cuentas, $diag, $reqId);
            $this->d($diag, $reqId, 'Cuenta elegida', [
                'cuenta_id' => $cuenta->id ?? null,
                'rfc_padre' => $cuenta->rfc_padre ?? null,
                'ranking'   => $rankDump,
            ]);

            if ($usuariosAll->isEmpty()) {
                $this->hitThrottle($request, $identifier);
                return $this->failBack('Tu cuenta no tiene usuarios registrados.', $identifier, $diag, $reqId, 'E6: sin usuarios');
            }

            $activos    = $usuariosAll->filter(fn ($u) => $this->userLooksActive($u));
            $candidatos = $activos->isNotEmpty() ? $activos : $usuariosAll;

            $this->d($diag, $reqId, 'Candidatos password', [
                'activos_primero' => $activos->isNotEmpty(),
                'cand_count'      => $candidatos->count(),
            ]);

            $usuario = null;
            foreach ($candidatos as $row) {
                $hashFromRow = isset($row->password) ? (string) $row->password : '';
                $matched     = ($hashFromRow !== '') && $this->passwordMatchesAny($password, $hashFromRow);

                if (!$matched) {
                    $m = UsuarioCuenta::on('mysql_clientes')->find($row->id);
                    if ($m) {
                        $hashRaw = (string) $m->getRawOriginal('password');
                        $matched = ($hashRaw !== '') && $this->passwordMatchesAny($password, $hashRaw);
                    }
                } else {
                    $m = UsuarioCuenta::on('mysql_clientes')->find($row->id);
                }

                if (!empty($matched) && !empty($m)) {
                    $usuario = $m;
                    break;
                }
            }

            if (!$usuario && $activos->isNotEmpty() && $usuariosAll->count() > $activos->count()) {
                foreach ($usuariosAll as $row) {
                    $hashFromRow = isset($row->password) ? (string) $row->password : '';
                    $matched     = ($hashFromRow !== '') && $this->passwordMatchesAny($password, $hashFromRow);

                    if (!$matched) {
                        $m = UsuarioCuenta::on('mysql_clientes')->find($row->id);
                        if ($m) {
                            $hashRaw = (string) $m->getRawOriginal('password');
                            $matched = ($hashRaw !== '') && $this->passwordMatchesAny($password, $hashRaw);
                        }
                    } else {
                        $m = UsuarioCuenta::on('mysql_clientes')->find($row->id);
                    }

                    if (!empty($matched) && !empty($m)) {
                        $usuario = $m;
                        break;
                    }
                }
            }

            $this->d($diag, $reqId, 'Resultado match', [
                'matched' => (bool) $usuario,
                'user_id' => $usuario?->id,
                'email'   => $usuario?->email,
            ]);

            if (!$usuario) {
                $this->hitThrottle($request, $identifier);
                return $this->invalid($identifier, $diag, $reqId, 'E7: pass mismatch (RFC route)');
            }
        }

        // ===== Estado de cuenta local
        if (in_array((string) ($cuenta->estado_cuenta ?? ''), ['bloqueada', 'suspendida'], true)) {
            $this->hitThrottle($request, $identifier);
            return $this->failBack('Tu cuenta no está activa. Contacta a soporte@pactopia.com', $identifier, $diag, $reqId, 'E8: cuenta bloqueada/suspendida');
        }

        // ===== Validación en admin.accounts
        $accAdmin = $this->findAdminAccountByRfc($cuenta->rfc_padre ?? '');
        if ($accAdmin) {
            if ((int) ($accAdmin->is_blocked ?? 0) === 1) {
                $this->hitThrottle($request, $identifier);
                return $this->failBack('Tu cuenta requiere pago para activarse. Completa tu pago para continuar.', $identifier, $diag, $reqId, 'E9: admin.is_blocked=1');
            }

            // <<< CONTEXTO PARA POST-VERIFICACIÓN Y OTP >>>
            $rfcPadre = Str::upper((string) ($cuenta->rfc_padre ?? ''));
            $emailLo  = Str::lower((string) ($usuario->email ?? ''));
            $accId    = (int) ($accAdmin->id ?? 0);

            // Autologin post-verify
            $this->setPostVerifyContext($usuario, $remember);

            // Contexto OTP/resolveAccountId
            session([
                'verify.account_id' => $accId,
                'verify.rfc'        => $rfcPadre,
                'verify.email'      => $emailLo,
            ]);

            // Si falta email verificado → flujo email
            if (property_exists($accAdmin, 'email_verified_at') && empty($accAdmin->email_verified_at)) {
                $this->flashDiag($diag, $reqId);
                return redirect()
                    ->route('cliente.verify.email.resend')
                    ->with('info', 'Debes confirmar tu correo antes de entrar.');
            }

            // Si falta teléfono verificado → flujo teléfono (OTP)
            if (property_exists($accAdmin, 'phone_verified_at') && empty($accAdmin->phone_verified_at)) {
                $this->flashDiag($diag, $reqId);
                return redirect()
                    ->route('cliente.verify.phone')
                    ->with('info', 'Debes verificar tu teléfono antes de entrar.');
            }
        }

        // ===== Autenticación en guard 'web' (ya todo verificado)
        Auth::guard('web')->login($usuario, $remember);
        $request->session()->regenerate();
        $this->clearThrottle($request, $identifier);

        $this->d($diag, $reqId, 'LOGIN OK', [
            'user_id' => $usuario->id,
            'email'   => $usuario->email,
            'cuenta'  => $cuenta->id ?? null,
        ]);

        if ($this->hasCol('mysql_clientes', 'usuarios_cuenta', 'must_change_password') && (bool) ($usuario->must_change_password ?? false)) {
            $this->flashDiag($diag, $reqId);
            return redirect()->route('cliente.password.first');
        }

        $this->flashDiag($diag, $reqId);
        return redirect()->route('cliente.home');
    }


    /* ============================================================
     | LOGOUT
     * ============================================================*/
    public function logout(Request $request)
    {
        // por si venías de impersonate admin
        $request->session()->forget('impersonated_by_admin');

        // Cerrar sesión del cliente (guard 'web')
        auth('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirigir SIEMPRE al login cliente, NUNCA al admin
        return redirect()
            ->route('cliente.login')
            ->with('logged_out', true);
    }

    /* ============================================================
     | HELPERS DE ENTORNO / ESQUEMA
     * ============================================================*/
    private function isLocal(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }

    private function hasCol(string $conn, string $table, string $col): bool
    {
        try { return Schema::connection($conn)->hasColumn($table, $col); }
        catch (\Throwable $e) { return false; }
    }

    /* ============================================================
     | RFC HELPERS
     * ============================================================*/
    /** RFC en mayúsculas y sin separadores (A-Z, 0-9, & y Ñ) */
    private function sanitizeRfc(string $raw): string
    {
        $u = Str::upper($raw);
        return preg_replace('/[^A-Z0-9&Ñ]+/u', '', $u) ?? '';
    }

    private function looksLikeRfc(string $rfc): bool
    {
        return (bool) preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc);
    }

    /** Detecta la columna RFC real en mysql_clientes.cuentas_cliente */
    private function rfcColumnClientes(): string
    {
        $conn = 'mysql_clientes';
        foreach (['rfc_padre', 'rfc', 'rfc_cliente', 'tax_id'] as $c) {
            try {
                if (Schema::connection($conn)->hasColumn('cuentas_cliente', $c)) return $c;
            } catch (\Throwable $e) {}
        }
        return 'rfc_padre';
    }

    /* ============================================================
     | USERS & CUENTAS
     * ============================================================*/
    private function isOwner($u): bool
    {
        $rol  = Str::lower((string) ($u->rol  ?? ''));
        $tipo = Str::lower((string) ($u->tipo ?? ''));
        return in_array($rol,  ['owner', 'dueño', 'propietario', 'admin_owner'], true)
            || in_array($tipo, ['owner', 'dueño', 'propietario', 'admin_owner'], true);
    }

    private function userLooksActive($u): bool
    {
        if (isset($u->activo))    return is_bool($u->activo) ? $u->activo : ((int) $u->activo === 1);
        if (isset($u->is_active)) return is_bool($u->is_active) ? $u->is_active : ((int) $u->is_active === 1);
        if (isset($u->status)) {
            $s = Str::lower((string) $u->status);
            if (in_array($s, ['activo', 'active', 'a', '1', 'enabled', 'enable', 'on'], true)) return true;
            if (in_array($s, ['inactivo', 'inactive', 'i', '0', 'disabled', 'disable', 'off'], true)) return false;
        }
        return true;
    }

    /**
     * Dada una colección de cuentas por RFC, calcula ranking y regresa:
     * [ mejorCuenta, usuariosDeEsaCuenta (owner primero), dumpRanking ].
     * NOTA: seleccionamos columnas mínimas necesarias (sin password*).
     */
    private function pickBestCuentaByRfcCandidates($cuentas, array &$diag, string $reqId): array
    {
        $ranked = [];

        foreach ($cuentas as $c) {
            $usuarios = DB::connection('mysql_clientes')
                ->table('usuarios_cuenta as u')
                ->where('u.cuenta_id', $c->id)
                ->select([
                    'u.id', 'u.email', 'u.activo', 'u.rol', 'u.tipo',
                ])
                ->get();

            // owner primero
            $usuarios = $usuarios->sortBy(function ($u) {
                $rol  = Str::lower((string) ($u->rol ?? ''));
                $tipo = Str::lower((string) ($u->tipo ?? ''));
                $isOwner = in_array($rol, ['owner', 'dueño', 'propietario', 'admin_owner'], true)
                        || in_array($tipo, ['owner', 'dueño', 'propietario', 'admin_owner'], true);
                return $isOwner ? 0 : 1;
            })->values();

            $ownerPresent = (bool) $usuarios->first(fn ($u) => $this->isOwner($u));
            $countUsers   = $usuarios->count();
            $ts           = $c->updated_at ?? $c->created_at ?? now();
            $tsNum        = is_string($ts) ? strtotime($ts) : ($ts?->getTimestamp() ?? time());

            $ranked[] = [
                'cuenta'        => $c,
                'usuarios'      => $usuarios,
                'owner'         => $ownerPresent,
                'users_count'   => $countUsers,
                'ts'            => $tsNum,
                'rfc_padre'     => $c->rfc_padre ?? null,
            ];
        }

        // Orden: owner desc, users_count desc, ts desc
        usort($ranked, function ($a, $b) {
            return [$b['owner'], $b['users_count'], $b['ts']] <=> [$a['owner'], $a['users_count'], $a['ts']];
        });

        $dump = collect($ranked)->map(function ($r) {
            return [
                'cuenta_id'   => $r['cuenta']->id ?? null,
                'rfc_padre'   => $r['rfc_padre'],
                'owner'       => $r['owner'],
                'users_count' => $r['users_count'],
                'ts'          => $r['ts'],
                'emails'      => collect($r['usuarios'])->take(6)->pluck('email')->all(),
            ];
        })->all();

        $best = $ranked[0] ?? null;

        $this->d($diag, $reqId, 'RFC multi-cuentas ranking', ['items' => $dump]);

        return [$best['cuenta'], collect($best['usuarios']), $dump];
    }

    /* ============================================================
     | PASSWORD CHECKS
     * ============================================================*/
    /** Autodetecta “parece hash” ($2y$, $argon2…). */
    private function isHash(string $value): bool
    {
        return Str::startsWith($value, '$2y$') || Str::startsWith($value, '$argon2');
    }

    /** Compara contra hash probando TODOS los caminos seguros. */
    private function passwordMatchesAny(string $plain, string $hash): bool
    {
        // 1) Bcrypt / hash ya guardado
        if ($hash && Hash::check($plain, $hash)) {
            return true;
        }

        // 2) Compat con ClientAuth (normalización interna)
        if ($hash && ClientAuth::check($plain, $hash)) {
            return true;
        }

        // 3) Variante con trim
        $t = trim($plain);
        if ($t !== $plain && Hash::check($t, $hash)) return true;
        if ($t !== $plain && ClientAuth::check($t, $hash)) return true;

        return false;
    }

    /** Chequeos Argon con variantes (raw/trim/normalized) */
    private function checkArgonVariants(string $plain, string $hash): bool
    {
        try { if (Hash::driver(config('hashing.driver', 'argon'))->check($plain, $hash)) return true; } catch (\Throwable $e) {}
        try { if (Hash::driver('argon')->check($plain, $hash)) return true; } catch (\Throwable $e) {}

        $t = trim($plain);
        if ($t !== $plain) {
            try { if (Hash::driver(config('hashing.driver', 'argon'))->check($t, $hash)) return true; } catch (\Throwable $e) {}
            try { if (Hash::driver('argon')->check($t, $hash)) return true; } catch (\Throwable $e) {}
        }

        $n = ClientAuth::normalizePassword($plain);
        if ($n !== $plain) {
            try { if (Hash::driver(config('hashing.driver', 'argon'))->check($n, $hash)) return true; } catch (\Throwable $e) {}
            try { if (Hash::driver('argon')->check($n, $hash)) return true; } catch (\Throwable $e) {}
        }
        return false;
    }

    /** Promueve contraseña a hash seguro (si aplicara) y limpia columnas legacy. */
    private function promotePassword($user, string $password, bool $colTemp, bool $colPlain): void
    {
        $userModel = $user instanceof UsuarioCuenta
            ? $user
            : UsuarioCuenta::on('mysql_clientes')->find($user->id);

        if ($userModel) {
            $userModel->password = ClientAuth::make($password);
            if ($colTemp)  { $userModel->password_temp  = null; }
            if ($colPlain) { $userModel->password_plain = null; }
            if ($this->hasCol('mysql_clientes', 'usuarios_cuenta', 'must_change_password')) {
                try { $userModel->must_change_password = true; } catch (\Throwable $e) {}
            }
            $userModel->save();
        }
    }

    /* ============================================================
     | ADMIN ACCOUNTS LOOKUP
     * ============================================================*/
    private function findAdminAccountByAnyRfc(string $rfcUpper)
    {
        if ($rfcUpper === '') {
            return null;
        }

        $conn = DB::connection('mysql_admin');

        // 1) buscar por RFC normalizado
        $acc = $conn->table('accounts')
            ->whereRaw('UPPER(COALESCE(rfc, "")) = ?', [$rfcUpper])
            ->first();

        if ($acc) {
            return $acc;
        }

        // 2) fallback legacy: a veces en instalaciones viejas el RFC se usaba como id
        $acc = $conn->table('accounts')
            ->whereRaw('UPPER(CAST(id AS CHAR)) = ?', [$rfcUpper])
            ->first();

        return $acc ?: null;
    }

    private function findAdminAccountByRfc(string $rfcPadre)
    {
        $rfc = Str::upper((string) $rfcPadre);
        if ($rfc === '') return null;
        return $this->findAdminAccountByAnyRfc($rfc);
    }

    /* ============================================================
     | THROTTLE SIMPLE (sesión)
     * ============================================================*/
    private function throttleKey(Request $r, string $identifier): string
    {
        return 'login_attempts:' . sha1(($r->ip() ?: '0.0.0.0') . '|' . Str::lower($identifier));
    }

    private function tooManyAttempts(Request $r, string $identifier, int $max = 7, int $decay = 60): bool
    {
        $key    = $this->throttleKey($r, $identifier);
        $bucket = session($key);
        if (!$bucket) return false;

        $attempts = (int) ($bucket['count'] ?? 0);
        $ts       = (int) ($bucket['ts'] ?? time());
        if (time() - $ts > $decay) {
            session()->forget($key);
            return false;
        }
        return $attempts >= $max;
    }

    private function hitThrottle(Request $r, string $identifier, int $decay = 60): void
    {
        $key    = $this->throttleKey($r, $identifier);
        $bucket = session($key) ?: ['count' => 0, 'ts' => time()];
        $bucket['count'] = (int) $bucket['count'] + 1;
        if (!isset($bucket['ts'])) $bucket['ts'] = time();
        session([$key => $bucket]);
    }

    private function clearThrottle(Request $r, string $identifier): void
    {
        session()->forget($this->throttleKey($r, $identifier));
    }

    private function remainingLockSeconds(Request $r, string $identifier, int $decay = 60): int
    {
        $bucket = session($this->throttleKey($r, $identifier));
        if (!$bucket) return 0;
        $age = time() - (int) $bucket['ts'];
        return $age >= $decay ? 0 : ($decay - $age);
    }

    /* ============================================================
     | RESPUESTAS con diagnóstico
     * ============================================================*/
    private function invalid(string $identifier, array $diag, string $reqId, string $code)
    {
        $msg = $this->isLocal() ? "Credenciales no válidas. ($code)" : 'Credenciales no válidas.';
        return back()->withErrors(['login' => $msg])
                     ->withInput(['login' => $identifier])
                     ->with('diag', $this->safeDiag($diag))
                     ->with('diag_req', $reqId);
    }

    private function failBack(string $message, string $identifier, array $diag, string $reqId, string $code)
    {
        $msg = $this->isLocal() ? "$message ($code)" : $message;
        return back()->withErrors(['login' => $msg])
                     ->withInput(['login' => $identifier])
                     ->with('diag', $this->safeDiag($diag))
                     ->with('diag_req', $reqId);
    }

    private function failValidation(string $message, array $diag, string $reqId, string $code)
    {
        $msg = $this->isLocal() ? "$message ($code)" : $message;
        session()->flash('diag', $this->safeDiag($diag));
        session()->flash('diag_req', $reqId);
        throw ValidationException::withMessages(['login' => $msg]);
    }

    private function d(array &$diag, string $reqId, string $step, array $ctx = []): void
    {
        $row = ['req' => $reqId, 'step' => $step, 'ctx' => $ctx, 'ts' => now()->toDateTimeString()];
        $diag[] = $row;
        if ($this->isLocal()) {
            Log::debug('[cliente-login][' . $reqId . '] ' . $step, $ctx);
        }
    }

    private function safeDiag(array $diag): array
    {
        return $diag;
    }

    private function flashDiag(array $diag, string $reqId): void
    {
        if ($this->isLocal()) {
            session()->flash('diag', $this->safeDiag($diag));
            session()->flash('diag_req', $reqId);
        }
    }

    /** Construye diagnóstico por-usuario (no expone contraseñas) */
    private function buildUsersDiag($users, string $password): array
    {
        $colTemp  = $this->hasCol('mysql_clientes', 'usuarios_cuenta', 'password_temp');
        $colPlain = $this->hasCol('mysql_clientes', 'usuarios_cuenta', 'password_plain');

        $out = [];
        foreach ($users as $u) {
            $row = [
                'id'     => $u->id,
                'email'  => $u->email,
                'rol'    => $u->rol ?? null,
                'tipo'   => $u->tipo ?? null,
                'activo' => $u->activo ?? null,
                'have'   => [
                    'password'       => !empty($u->password),
                    'password_temp'  => $colTemp  ? !empty($u->password_temp  ?? null) : false,
                    'password_plain' => $colPlain ? !empty($u->password_plain ?? null) : false,
                ],
                'meta'   => [
                    'password_is_hash'      => !empty($u->password) ? $this->isHash((string) $u->password) : null,
                    'password_temp_is_hash' => ($colTemp && !empty($u->password_temp)) ? $this->isHash((string) $u->password_temp) : null,
                    'password_prefix'       => !empty($u->password) ? substr((string) $u->password, 0, 4) : null,
                    'password_temp_prefix'  => ($colTemp && !empty($u->password_temp)) ? substr((string) $u->password_temp, 0, 4) : null,
                ],
                'match' => [
                    'password'       => !empty($u->password)        ? ClientAuth::check($password, (string) $u->password)        : false,
                    'password_temp'  => ($colTemp  && !empty($u->password_temp))  ? ClientAuth::check($password, (string) $u->password_temp)  : false,
                    'password_plain' => ($colPlain && !empty($u->password_plain)) ? hash_equals((string) $u->password_plain, $password)       : false,
                ],
            ];
            $out[] = $row;
        }
        return $out;
    }

    /* ============================================================
     | QA (SOLO LOCAL/DEV/TEST)
     * ============================================================*/

    /** QA: probar password contra todos los usuarios de la mejor cuenta por RFC (sin modificar DB) */
    public function qaTestPassword(Request $request)
    {
        abort_unless($this->isLocal(), 404);

        $request->validate([
            'rfc'      => 'required|string|max:32',
            'password' => 'required|string|max:100',
        ]);

        $rfcInput  = (string) $request->string('rfc');
        $plain     = (string) $request->string('password');
        $rfcUpper  = Str::upper($rfcInput);
        $rfcSan    = $this->sanitizeRfc($rfcUpper);
        $rfcColCli = $this->rfcColumnClientes();

        $cuentas = CuentaCliente::on('mysql_clientes')
            ->where(function ($q) use ($rfcUpper, $rfcSan, $rfcColCli) {
                $q->whereRaw("UPPER($rfcColCli) = ?", [$rfcUpper])
                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER('.$rfcColCli.')," ",""),"-",""),"_",""),".",""),"/","") = ?', [$rfcSan]);
            })
            ->get();

        if ($cuentas->isEmpty()) {
            return response()->json(['ok' => false, 'error' => 'Cuenta no encontrada por RFC en clientes.', 'rfc' => $rfcInput], 404);
        }

        $diag  = [];
        $reqId = (string) Str::ulid();
        [$cuenta, $usuarios, $rankDump] = $this->pickBestCuentaByRfcCandidates($cuentas, $diag, $reqId);

        if (!$cuenta) {
            return response()->json(['ok' => false, 'error' => 'No se pudo resolver una cuenta principal para ese RFC.'], 404);
        }
        if ($usuarios->isEmpty()) {
            return response()->json(['ok' => false, 'error' => 'La cuenta no tiene usuarios.', 'cuenta_id' => $cuenta->id, 'rfc_padre' => $cuenta->rfc_padre ?? null], 404);
        }

        $rows = [];
        $colTemp  = $this->hasCol('mysql_clientes', 'usuarios_cuenta', 'password_temp');
        $colPlain = $this->hasCol('mysql_clientes', 'usuarios_cuenta', 'password_plain');

        foreach ($usuarios as $u) {
            /** @var \App\Models\Cliente\UsuarioCuenta|null $m */
            $m = UsuarioCuenta::on('mysql_clientes')->find($u->id);
            if (!$m) {
                $rows[] = ['id' => $u->id, 'email' => $u->email, 'exists' => false];
                continue;
            }

            $hash = (string) $m->getRawOriginal('password');
            $matchPwd   = $hash !== '' ? $this->passwordMatchesAny($plain, $hash) : false;

            // Compat opcional si existieran columnas legacy
            $matchTemp  = false;
            $matchPlain = false;
            if ($colTemp && Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta', 'password_temp')) {
                $tmp = (string) ($m->password_temp ?? '');
                $matchTemp = $tmp !== '' ? $this->passwordMatchesAny($plain, $tmp) : false;
            }
            if ($colPlain && Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta', 'password_plain')) {
                $pln = (string) ($m->password_plain ?? '');
                $matchPlain = $pln !== '' && (
                    hash_equals($pln, $plain) ||
                    hash_equals($pln, trim($plain)) ||
                    hash_equals($pln, ClientAuth::normalizePassword($plain))
                );
            }

            $rows[] = [
                'id'     => $m->id,
                'email'  => $m->email,
                'owner'  => $this->isOwner($u),
                'activo' => $this->userLooksActive($u),
                'have'   => ['password' => $hash !== '', 'password_temp' => $colTemp, 'password_plain' => $colPlain],
                'meta'   => ['password_len' => strlen($hash), 'password_prefix' => substr($hash, 0, 4)],
                'match'  => ['password' => $matchPwd, 'password_temp' => $matchTemp, 'password_plain' => $matchPlain],
            ];
        }

        return response()->json([
            'ok'        => true,
            'rfc_input' => $rfcInput,
            'cuenta_id' => $cuenta->id,
            'rfc_padre' => $cuenta->rfc_padre ?? null,
            'rank'      => $rankDump,
            'diag'      => $diag,
            'users'     => $rows,
        ]);
    }

    /** QA: Hash check contra OWNER sin modificar nada */
    public function qaHashCheck(Request $r)
    {
        abort_unless($this->isLocal(), 404);

        $r->validate([
            'rfc' => 'required|string|max:32',
            'password' => 'required|string|max:200',
        ]);

        $rfc      = Str::upper(trim((string) $r->rfc));
        $plainRaw = (string) $r->password;

        $cuenta = DB::connection('mysql_clientes')->table('cuentas_cliente')
            ->whereRaw('UPPER(rfc_padre)=?', [$rfc])->first();

        if (!$cuenta) {
            return response()->json(['ok' => false, 'msg' => 'Cuenta no encontrada por RFC.', 'rfc' => $rfc], 404);
        }

        $schema = Schema::connection('mysql_clientes');
        $q = DB::connection('mysql_clientes')->table('usuarios_cuenta')->where('cuenta_id', $cuenta->id);
        if ($schema->hasColumn('usuarios_cuenta', 'rol'))  $q->orderByRaw("FIELD(rol,'owner') DESC");
        if ($schema->hasColumn('usuarios_cuenta', 'tipo')) $q->orderByRaw("FIELD(tipo,'owner') DESC");
        $q->orderBy('created_at', 'asc');
        $u = $q->first();

        if (!$u) return response()->json(['ok' => false, 'msg' => 'La cuenta no tiene usuarios.'], 404);

        /** @var \App\Models\Cliente\UsuarioCuenta|null $m */
        $m = UsuarioCuenta::on('mysql_clientes')->find($u->id);
        if (!$m) return response()->json(['ok' => false, 'msg' => 'Owner no se pudo cargar como modelo.'], 500);

        $hash = (string) $m->getRawOriginal('password');
        $norm = ClientAuth::normalizePassword($plainRaw);

        $match_raw  = $hash !== '' ? $this->passwordMatchesAny($plainRaw, $hash) : false;
        $match_norm = $hash !== '' ? $this->passwordMatchesAny($norm,     $hash) : false;

        return response()->json([
            'ok'        => true,
            'rfc'       => $rfc,
            'cuenta_id' => $cuenta->id,
            'user_id'   => $m->id,
            'email'     => $m->email,
            'hash'      => ['len' => strlen($hash), 'p7' => substr($hash, 0, 7)],
            'input'     => ['raw_len' => strlen($plainRaw), 'norm_len' => strlen($norm), 'changed' => ($plainRaw !== $norm)],
            'match'     => ['raw' => $match_raw, 'normalized' => $match_norm],
            'env'       => ['driver' => config('hashing.driver'), 'bcrypt' => config('hashing.bcrypt')],
        ]);
    }

    /** QA: Reset password del OWNER con hash consistente y verificación inmediata */
    public function qaResetPassword(Request $r)
    {
        abort_unless($this->isLocal(), 404);

        $r->validate(['rfc' => 'required|string|max:32']);
        $rfc = Str::upper(trim((string) $r->rfc));

        try {
            // Cuenta
            $cuenta = DB::connection('mysql_clientes')
                ->table('cuentas_cliente')
                ->whereRaw('UPPER(rfc_padre) = ?', [$rfc])
                ->first();

            if (!$cuenta) {
                return response()->json(['ok' => false, 'msg' => 'Cuenta no encontrada por RFC.', 'rfc' => $rfc], 404);
            }

            // Owner primero
            $schema = Schema::connection('mysql_clientes');
            $q = DB::connection('mysql_clientes')->table('usuarios_cuenta')->where('cuenta_id', $cuenta->id);
            if ($schema->hasColumn('usuarios_cuenta', 'rol'))  $q->orderByRaw("FIELD(rol,'owner') DESC");
            if ($schema->hasColumn('usuarios_cuenta', 'tipo')) $q->orderByRaw("FIELD(tipo,'owner') DESC");
            $q->orderBy('created_at', 'asc');
            $u = $q->first();

            if (!$u) {
                return response()->json(['ok' => false, 'msg' => 'La cuenta no tiene usuarios.', 'cuenta_id' => $cuenta->id], 404);
            }

            // Temporal
            $plain = $this->generateTempPassword(12);

            // Guardar hash normalizado / limpiar legacy
            DB::connection('mysql_clientes')->beginTransaction();

            /** @var UsuarioCuenta|null $userModel */
            $userModel = UsuarioCuenta::on('mysql_clientes')->find($u->id);
            if (!$userModel) {
                DB::connection('mysql_clientes')->rollBack();
                return response()->json(['ok' => false, 'msg' => 'No fue posible cargar el modelo de usuario.'], 500);
            }

            $userModel->password = ClientAuth::make($plain); // normaliza adentro
            if ($this->hasCol('mysql_clientes', 'usuarios_cuenta', 'must_change_password')) { try { $userModel->must_change_password = true; } catch (\Throwable $e) {} }
            if ($this->hasCol('mysql_clientes', 'usuarios_cuenta', 'password_temp'))  { try { $userModel->password_temp  = null; } catch (\Throwable $e) {} }
            if ($this->hasCol('mysql_clientes', 'usuarios_cuenta', 'password_plain')) { try { $userModel->password_plain = null; } catch (\Throwable $e) {} }

            $userModel->updated_at = now();
            $userModel->saveQuietly();

            // Verificar
            $hash     = (string) $userModel->getRawOriginal('password');
            $verified = $hash !== '' ? $this->passwordMatchesAny($plain, $hash) : false;

            if (!$verified) {
                DB::connection('mysql_clientes')->rollBack();
                return response()->json(['ok' => false, 'msg' => 'El hash guardado no valida contra la temporal generada.'], 500);
            }

            DB::connection('mysql_clientes')->commit();

            // Respuesta (temporal en claro SOLO QA local)
            return response()->json([
                'ok'        => true,
                'msg'       => 'Contraseña temporal actualizada.',
                'rfc'       => $rfc,
                'cuenta_id' => $cuenta->id,
                'user_id'   => $userModel->id,
                'email'     => $userModel->email,
                'password'  => $plain,
                'verified'  => true,
                'hash'      => ['len' => strlen($hash), 'p7' => substr($hash, 0, 7)],
                'env'       => ['driver' => config('hashing.driver'), 'bcrypt' => config('hashing.bcrypt')],
            ]);
        } catch (\Throwable $e) {
            try { DB::connection('mysql_clientes')->rollBack(); } catch (\Throwable $e2) {}
            Log::error('qaResetPassword error', ['rfc' => $rfc, 'e' => $e->getMessage()]);
            return response()->json(['ok' => false, 'msg' => 'Error interno al resetear la contraseña.', 'err' => $e->getMessage()], 500);
        }
    }

    /** QA: Fuerza password del OWNER con bcrypt y valida de inmediato */
    public function qaForceOwnerPassword(Request $r)
    {
        abort_unless($this->isLocal(), 404);

        $r->validate([
            'rfc'      => 'required|string|max:32',
            'password' => 'required|string|min:6|max:200',
        ]);

        $rfc   = Str::upper(trim((string) $r->rfc));
        $plain = (string) $r->password;

        $cuenta = DB::connection('mysql_clientes')->table('cuentas_cliente')
            ->whereRaw('UPPER(rfc_padre)=?', [$rfc])->first();
        if (!$cuenta) {
            return response()->json(['ok' => false, 'msg' => 'Cuenta no encontrada por RFC.', 'rfc' => $rfc], 404);
        }

        $schema = Schema::connection('mysql_clientes');
        $q = DB::connection('mysql_clientes')->table('usuarios_cuenta')->where('cuenta_id', $cuenta->id);
        if ($schema->hasColumn('usuarios_cuenta', 'rol'))  $q->orderByRaw("FIELD(rol,'owner') DESC");
        if ($schema->hasColumn('usuarios_cuenta', 'tipo')) $q->orderByRaw("FIELD(tipo,'owner') DESC");
        $q->orderBy('created_at', 'asc');
        $owner = $q->first();
        if (!$owner) return response()->json(['ok' => false, 'msg' => 'La cuenta no tiene usuarios.'], 404);

        // Actualiza hash en password (bcrypt) usando normalización
        DB::connection('mysql_clientes')->table('usuarios_cuenta')
            ->where('id', $owner->id)
            ->update([
                'password'   => Hash::driver('bcrypt')->make(ClientAuth::normalizePassword($plain)),
                'updated_at' => now(),
            ]);

        // Verifica
        /** @var \App\Models\Cliente\UsuarioCuenta|null $m */
        $m    = UsuarioCuenta::on('mysql_clientes')->find($owner->id);
        $hash = (string) ($m?->getRawOriginal('password') ?? '');
        $ok   = $hash !== '' ? $this->passwordMatchesAny($plain, $hash) : false;

        return response()->json([
            'ok'        => true,
            'msg'       => 'Password del OWNER actualizada.',
            'rfc'       => $rfc,
            'cuenta_id' => $cuenta->id,
            'user_id'   => $owner->id,
            'email'     => $owner->email,
            'hash'      => ['len' => strlen($hash), 'p7' => substr($hash, 0, 7)],
            'verified'  => $ok,
        ]);
    }

    /** QA: UNIVERSAL CHECK (BUSCARV) */
    public function qaUniversalCheck(Request $r)
    {
        abort_unless($this->isLocal(), 404);

        $r->validate([
            'login'    => 'required|string|max:150',
            'password' => 'required|string|max:200',
        ]);

        $reqId   = (string) \Illuminate\Support\Str::ulid();
        $input   = trim((string) $r->input('login'));
        $plain   = (string) $r->input('password');

        $isEmail = filter_var($input, FILTER_VALIDATE_EMAIL) !== false;

        // ---------- 1) Camino EMAIL ----------
        $emailReport = null;
        if ($isEmail) {
            $email = \Illuminate\Support\Str::lower($input);
            /** @var \App\Models\Cliente\UsuarioCuenta|null $u */
            $u = \App\Models\Cliente\UsuarioCuenta::on('mysql_clientes')->where('email', $email)->first();

            $emailReport = [
                'input'   => $email,
                'found'   => (bool) $u,
                'user_id' => $u->id ?? null,
                'hash'    => $u ? [
                    'len' => strlen((string) $u->getRawOriginal('password')),
                    'p7'  => substr((string) $u->getRawOriginal('password'), 0, 7),
                ] : null,
                'match'   => $u ? [
                    'raw'  => $this->passwordMatchesAny($plain, (string) $u->getRawOriginal('password')),
                    'norm' => $this->passwordMatchesAny(\App\Support\ClientAuth::normalizePassword($plain), (string) $u->getRawOriginal('password')),
                ] : ['raw' => false, 'norm' => false],
            ];
        } else {
            $emailReport = ['skipped' => true];
        }

        // ---------- 2) Camino RFC ----------
        $rfcReport = null;
        if (!$isEmail) {
            $rfcUpper  = \Illuminate\Support\Str::upper($input);
            $rfcSan    = $this->sanitizeRfc($rfcUpper);
            $rfcColCli = $this->rfcColumnClientes();

            // Cuentas por RFC (igual que en login)
            $cuentas = \App\Models\Cliente\CuentaCliente::on('mysql_clientes')
                ->where(function ($q) use ($rfcUpper, $rfcSan, $rfcColCli) {
                    $q->whereRaw("UPPER($rfcColCli) = ?", [$rfcUpper])
                    ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER('.$rfcColCli.')," ",""),"-",""),"_",""),".",""),"/","") = ?', [$rfcSan]);
                })
                ->get();

            $diag = [];
            [$cuenta, $usuarios, $rank] = $cuentas->isEmpty()
                ? [null, collect(), []]
                : $this->pickBestCuentaByRfcCandidates($cuentas, $diag, $reqId);

            // Tomamos hasta 5 usuarios para reporte (owner primero ya viene)
            $rows = [];
            foreach ($usuarios->take(5) as $u) {
                /** @var \App\Models\Cliente\UsuarioCuenta|null $m */
                $m = \App\Models\Cliente\UsuarioCuenta::on('mysql_clientes')->find($u->id);
                if (!$m) {
                    $rows[] = [
                        'id'     => $u->id,
                        'email'  => $u->email,
                        'exists' => false,
                    ];
                    continue;
                }
                $hash = (string) $m->getRawOriginal('password');
                $rows[] = [
                    'id'     => $m->id,
                    'email'  => $m->email,
                    'exists' => true,
                    'hash'   => ['len' => strlen($hash), 'p7' => substr($hash, 0, 7)],
                    'match'  => [
                        'raw'  => $this->passwordMatchesAny($plain, $hash),
                        'trim' => $this->passwordMatchesAny(trim($plain), $hash),
                        'norm' => $this->passwordMatchesAny(\App\Support\ClientAuth::normalizePassword($plain), $hash),
                    ],
                ];
            }

            // Además, probamos explícitamente contra el OWNER (si existe)
            $ownerMatch = null;
            if ($usuarios->isNotEmpty()) {
                $ownerRow = $usuarios->first();
                $owner    = \App\Models\Cliente\UsuarioCuenta::on('mysql_clientes')->find($ownerRow->id);
                if ($owner) {
                    $hash = (string) $owner->getRawOriginal('password');
                    $ownerMatch = [
                        'user_id' => $owner->id,
                        'email'   => $owner->email,
                        'hash'    => ['len' => strlen($hash), 'p7' => substr($hash, 0, 7)],
                        'ok'      => [
                            'raw'  => $this->passwordMatchesAny($plain, $hash),
                            'trim' => $this->passwordMatchesAny(trim($plain), $hash),
                            'norm' => $this->passwordMatchesAny(\App\Support\ClientAuth::normalizePassword($plain), $hash),
                        ],
                    ];
                }
            }

            $rfcReport = [
                'input'     => $input,
                'rfc_upper' => $rfcUpper,
                'rfc_san'   => $rfcSan,
                'cuentas'   => [
                    'count' => $cuentas->count(),
                    'rank'  => $rank,
                ],
                'owner_try' => $ownerMatch,
                'users_try' => $rows,
            ];
        } else {
            $rfcReport = ['skipped' => true];
        }

        return response()->json([
            'ok'      => true,
            'login'   => $input,
            'email'   => $emailReport,
            'rfc'     => $rfcReport,
            'len_in'  => [
                'raw'  => strlen($plain),
                'trim' => strlen(trim($plain)),
                'norm' => strlen(\App\Support\ClientAuth::normalizePassword($plain)),
            ],
            'env'     => [
                'hash_driver' => config('hashing.driver'),
                'bcrypt'      => config('hashing.bcrypt'),
            ],
            'req'     => $reqId,
        ]);
    }

    /* ============================================================
     | UTILIDADES
     * ============================================================*/
    /** Genera contraseña temporal robusta (sin caracteres confusos). */
    private function generateTempPassword(int $length = 12): string
    {
        $length = max(8, min(48, $length));
        $sets = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '.,;:!?@#$%&*+-_=',
        ];
        $all = implode('', $sets);
        $pwd = '';
        foreach ($sets as $set) { $pwd .= $set[random_int(0, strlen($set) - 1)]; }
        for ($i = strlen($pwd); $i < $length; $i++) { $pwd .= $all[random_int(0, strlen($all) - 1)]; }
        return str_shuffle($pwd);
    }

    /* ============================================================
    | VERIFICACIÓN (helpers privados para login/OTP)
    * ============================================================*/

    /**
     * Determina si el usuario ya tiene verificados correo y teléfono
     * en la fila correspondiente de mysql_admin.accounts (vía RFC).
     *
     * Regresa true si:
     *  - existe la cuenta admin para el RFC del usuario, y
     *  - (si existen las columnas) email_verified_at y phone_verified_at NO están vacías.
     */
    private function emailYTelefonoVerificados(UsuarioCuenta $user): bool
    {
        try {
            // RFC del usuario (desde su cuenta en clientes)
            $cuenta = $user->cuenta()->first();
            $rfcPadre = $cuenta?->rfc_padre ? Str::upper($cuenta->rfc_padre) : null;
            if (!$rfcPadre) {
                return false;
            }

            // Busca la fila en admin.accounts para ese RFC (usa el helper ya existente)
            $accAdmin = $this->findAdminAccountByRfc($rfcPadre);
            if (!$accAdmin) {
                return false;
            }

            // Si las columnas existen, deben estar pobladas
            $schemaAdmin = Schema::connection('mysql_admin');

            $emailOk = true;
            if ($schemaAdmin->hasColumn('accounts', 'email_verified_at')) {
                $emailOk = !empty($accAdmin->email_verified_at);
            }

            $phoneOk = true;
            if ($schemaAdmin->hasColumn('accounts', 'phone_verified_at')) {
                $phoneOk = !empty($accAdmin->phone_verified_at);
            }

            return $emailOk && $phoneOk;

        } catch (\Throwable $e) {
            Log::error('[login] emailYTelefonoVerificados error', ['e' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Guarda en sesión el contexto necesario para autologin
     * cuando el usuario complete la verificación (OTP).
     *
     * Se lee posteriormente en VerificationController@checkOtp().
     */
    private function setPostVerifyContext(UsuarioCuenta $user, bool $remember = false): void
    {
        session([
            'post_verify.user_id'  => $user->id,
            'post_verify.remember' => $remember,
        ]);
    }

    /**
     * Limpia el contexto de autologin post-verificación.
     * Útil en logout o al finalizar correctamente el proceso.
     */
    private function clearPostVerifyContext(): void
    {
        session()->forget(['post_verify.user_id', 'post_verify.remember']);
    }

}
