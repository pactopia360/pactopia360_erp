<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente\UsuarioCuenta;
use App\Models\Cliente\CuentaCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Support\ClientAuth;

class LoginController extends Controller
{
    public function showLogin(Request $request)
    {
        // ✅ Asegura sesión aislada del portal cliente (cookie correcta)
        Config::set('session.cookie', 'p360_client_session');

        Auth::shouldUse('web');

        // ✅ Limpieza de error "enlace inválido" en pantalla de login.
        // Si el usuario llega a /cliente/login SIN parámetros de firma,
        // no debemos mostrar un error de "link expirado/usado" (eso pertenece a la pantalla de error 403).
        $hasSignedParams = $request->query->has('signature') || $request->query->has('expires');

        if (!$hasSignedParams) {
            $err = session('error');

            if (is_string($err) && $err !== '') {
                $low = mb_strtolower($err, 'UTF-8');

                $looksLikeSignedError =
                    str_contains($low, 'enlace') &&
                    (str_contains($low, 'no es válido') || str_contains($low, 'no es valido') || str_contains($low, 'ya fue usado') || str_contains($low, 'expir'));

                if ($looksLikeSignedError) {
                    $request->session()->forget('error');
                }
            }
        }

        if (Auth::guard('web')->check()) {
            return redirect()->intended(
                \Route::has('cliente.home') ? route('cliente.home') : '/'
            );
        }

        // Soportar redirect deseado post-login (ej: /cliente/billing/statement)
        $next = (string) $request->query('next', '');
        if ($next !== '') {
            if (str_starts_with($next, '/')) {
                session(['url.intended' => url($next)]);
            }
        }

        return view('cliente.auth.login');
    }

    public function login(Request $request)
    {
        // ✅ Asegura sesión aislada del portal cliente (cookie correcta)
        // Importante: debe ejecutarse ANTES de leer/escribir session()
        Config::set('session.cookie', 'p360_client_session');

        Auth::shouldUse('web');

        $reqId      = (string) Str::ulid();
        $identifier = trim((string) $request->input('login', $request->input('email', '')));
        $password   = (string) $request->input('password', '');
        $remember   = $request->boolean('remember');

        try {
            if (!Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta', 'remember_token')) {
                $remember = false;
            }
        } catch (\Throwable $e) {
            $remember = false;
        }

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

        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $this->d($diag, $reqId, 'Identifier type', ['is_email' => $isEmail]);

        if ($isEmail) {
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
            $rfcInput = (string) $identifier;
            $rfcUpper = Str::upper($rfcInput);
            $rfcSan   = $this->sanitizeRfc($rfcUpper);

            $this->d($diag, $reqId, 'RFC normalized', [
                'input' => $rfcInput,
                'rfc_upper' => $rfcUpper,
                'rfc_sanitized' => $rfcSan,
            ]);

            $rfcColCli = $this->rfcColumnClientes();

            $cuentas = CuentaCliente::on('mysql_clientes')
                ->where(function ($q) use ($rfcUpper, $rfcSan, $rfcColCli) {
                    $q->whereRaw("UPPER($rfcColCli) = ?", [$rfcUpper])
                      ->orWhereRaw(
                          'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER('.$rfcColCli.')," ",""),"-",""),"_",""),".",""),"/","") = ?',
                          [$rfcSan]
                      );
                })
                ->get();

            $this->d($diag, $reqId, 'Cuentas candidate por RFC (clientes)', ['count' => $cuentas->count(), 'col' => $rfcColCli]);

            if ($cuentas->isEmpty()) {
                $this->d($diag, $reqId, 'RFC not found in mysql_clientes, fallback to admin.accounts', [
                    'rfcUpper' => $rfcUpper,
                ]);

                if (!$this->looksLikeRfc($rfcSan)) {
                    $this->hitThrottle($request, $identifier);
                    return $this->failValidation('El RFC no tiene un formato válido.', $diag, $reqId, 'E4: RFC inválido');
                }

                [$cuentaBoot, $usuarioBoot, $bootCode] = $this->bootstrapClienteFromAdminAccount($rfcUpper, $password, $diag, $reqId);

                if (!$cuentaBoot || !$usuarioBoot) {
                    $this->hitThrottle($request, $identifier);
                    return $this->invalid($identifier, $diag, $reqId, $bootCode ?: 'E5: RFC no corresponde a ninguna cuenta');
                }

                $cuenta  = $cuentaBoot;
                $usuario = $usuarioBoot;

                $this->d($diag, $reqId, 'Bootstrap OK', [
                    'cuenta_id' => $cuenta?->id,
                    'user_id'   => $usuario?->id,
                    'email'     => $usuario?->email,
                ]);
            } else {
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
        }

        if ($cuenta && in_array((string) ($cuenta->estado_cuenta ?? ''), ['bloqueada', 'suspendida'], true)) {
            $this->hitThrottle($request, $identifier);
            return $this->failBack('Tu cuenta no está activa. Contacta a soporte@pactopia.com', $identifier, $diag, $reqId, 'E8: cuenta bloqueada/suspendida');
        }

        // ===== Validación en admin.accounts
        $accAdmin = $this->findAdminAccountByRfc($cuenta->rfc_padre ?? '');

        if ($accAdmin) {
            $blocked = (int) ($accAdmin->is_blocked ?? 0) === 1;

            // ===== REGLA: si está bloqueada, NO mostrar mensaje, redirigir a Stripe Checkout (paywall)
            if ($blocked && !$this->bypassBlockedGate($request, $accAdmin, $usuario, $cuenta)) {
                $cycle = strtolower((string)($accAdmin->modo_cobro ?? $accAdmin->billing_cycle ?? 'mensual'));
                $cycle = ($cycle === 'anual' || $cycle === 'annual') ? 'anual' : 'mensual';

                session([
                    'paywall.account_id' => (int) ($accAdmin->id ?? 0),
                    'paywall.cycle'      => $cycle,
                    'paywall.email'      => (string) ($usuario->email ?? ''),
                ]);

                $this->d($diag, $reqId, 'PAYWALL redirect (admin.is_blocked=1)', [
                    'admin_id' => $accAdmin->id ?? null,
                    'cycle'    => $cycle,
                    'rfc'      => $cuenta->rfc_padre ?? null,
                ]);

                $this->flashDiag($diag, $reqId);
                return redirect()->route('cliente.paywall');
            }

            if ($blocked) {
                $this->d($diag, $reqId, 'Bypass admin.is_blocked gate', [
                    'env' => app()->environment(),
                    'admin_id' => $accAdmin->id ?? null,
                    'rfc' => $cuenta->rfc_padre ?? null,
                ]);
            }

            $rfcPadre = Str::upper((string) ($cuenta->rfc_padre ?? ''));
            $emailLo  = Str::lower((string) ($usuario->email ?? ''));
            $accId    = (int) ($accAdmin->id ?? 0);

            $this->setPostVerifyContext($usuario, $remember);

            session([
                'verify.account_id' => $accId,
                'verify.rfc'        => $rfcPadre,
                'verify.email'      => $emailLo,
            ]);

            if (property_exists($accAdmin, 'email_verified_at') && empty($accAdmin->email_verified_at)) {
                $this->flashDiag($diag, $reqId);
                return redirect()
                    ->route('cliente.verify.email.resend')
                    ->with('info', 'Debes confirmar tu correo antes de entrar.');
            }

            if (property_exists($accAdmin, 'phone_verified_at') && empty($accAdmin->phone_verified_at)) {
                $this->flashDiag($diag, $reqId);
                return redirect()
                    ->route('cliente.verify.phone')
                    ->with('info', 'Debes verificar tu teléfono antes de entrar.');
            }
        }

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

    public function logout(Request $request)
    {
        // ✅ Asegura que el logout opere sobre la sesión/cookie del portal cliente
        Config::set('session.cookie', 'p360_client_session');

        $request->session()->forget('impersonated_by_admin');

        auth('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('cliente.login')
            ->with('logged_out', true);
    }

    /* ===================== Gate helpers ===================== */

    private function bypassBlockedGate(Request $request, object $accAdmin, UsuarioCuenta $usuario, ?CuentaCliente $cuenta): bool
    {
        $enforceLocal = filter_var(env('P360_ENFORCE_ACCOUNT_ACTIVE_LOCAL', false), FILTER_VALIDATE_BOOLEAN);

        if (app()->environment(['local', 'development', 'testing']) && !$enforceLocal) {
            return true;
        }

        if ($request->session()->has('impersonated_by_admin')) {
            return true;
        }

        return false;
    }

    /* ===================== Schema / env ===================== */

    private function isLocal(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }

    private function hasCol(string $conn, string $table, string $col): bool
    {
        try { return Schema::connection($conn)->hasColumn($table, $col); }
        catch (\Throwable $e) { return false; }
    }

    /* ===================== RFC ===================== */

    private function sanitizeRfc(string $raw): string
    {
        $u = Str::upper($raw);
        return preg_replace('/[^A-Z0-9&Ñ]+/u', '', $u) ?? '';
    }

    private function looksLikeRfc(string $rfc): bool
    {
        return (bool) preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc);
    }

    private function rfcColumnClientes(): string
    {
        $conn  = 'mysql_clientes';
        $table = (new CuentaCliente())->getTable() ?: 'cuentas_cliente';

        foreach (['rfc_padre', 'rfc', 'rfc_cliente', 'tax_id'] as $c) {
            try {
                if (Schema::connection($conn)->hasColumn($table, $c)) return $c;
            } catch (\Throwable $e) {}
        }
        return 'rfc';
    }

    /* ===================== Bootstrap desde admin ===================== */

    private function bootstrapClienteFromAdminAccount(string $rfcUpper, string $plainPassword, array &$diag, string $reqId): array
    {
        try {
            $acc = $this->findAdminAccountByAnyRfc($rfcUpper);

            if (!$acc) {
                $this->d($diag, $reqId, 'Admin account NOT found', ['rfc' => $rfcUpper]);
                return [null, null, 'E5: RFC no corresponde a ninguna cuenta (admin.accounts)'];
            }

            $accEmail = Str::lower((string)($acc->email ?? ''));
            $accRfc   = Str::upper((string)($acc->rfc ?? ''));
            $accName  = (string)($acc->razon_social ?? $acc->name ?? 'Cuenta');

            $this->d($diag, $reqId, 'Admin account found', [
                'admin_id' => $acc->id ?? null,
                'email'    => $accEmail,
                'rfc'      => $accRfc,
            ]);

            $hashAdmin = $this->extractAdminPasswordHash($acc);

            if ($hashAdmin !== '') {
                $ok = $this->passwordMatchesAny($plainPassword, $hashAdmin);
                $this->d($diag, $reqId, 'Admin password check', [
                    'has_hash' => true,
                    'ok'       => $ok,
                ]);

                if (!$ok) {
                    return [null, null, 'E5A: password mismatch (admin.accounts)'];
                }
            } else {
                $this->d($diag, $reqId, 'Admin password hash missing', [
                    'local' => $this->isLocal(),
                ]);

                if (!$this->isLocal()) {
                    return [null, null, 'E5B: admin sin password hash'];
                }
            }

            $rfcPadre = $accRfc !== '' ? $accRfc : Str::upper((string)($acc->id ?? $rfcUpper));

            $cuenta = CuentaCliente::on('mysql_clientes')->where('rfc_padre', $rfcPadre)->first();
            if (!$cuenta) {
                $cuenta = new CuentaCliente();
                $cuenta->setConnection('mysql_clientes');

                if ($this->hasCol('mysql_clientes', $cuenta->getTable(), 'rfc_padre')) $cuenta->rfc_padre = $rfcPadre;
                if ($this->hasCol('mysql_clientes', $cuenta->getTable(), 'rfc'))      $cuenta->rfc = $rfcPadre;

                if ($this->hasCol('mysql_clientes', $cuenta->getTable(), 'razon_social')) $cuenta->razon_social = $accName;
                if ($this->hasCol('mysql_clientes', $cuenta->getTable(), 'nombre'))      $cuenta->nombre = $accName;

                if ($this->hasCol('mysql_clientes', $cuenta->getTable(), 'estado_cuenta')) {
                    $cuenta->estado_cuenta = 'operando';
                }

                $cuenta->save();
                $this->d($diag, $reqId, 'CuentaCliente created', ['cuenta_id' => $cuenta->id ?? null, 'rfc_padre' => $rfcPadre]);
            } else {
                $this->d($diag, $reqId, 'CuentaCliente exists', ['cuenta_id' => $cuenta->id ?? null, 'rfc_padre' => $rfcPadre]);
            }

            $user = null;

            if ($accEmail !== '') {
                $user = UsuarioCuenta::on('mysql_clientes')->where('email', $accEmail)->first();
            }

            if (!$user) {
                $user = new UsuarioCuenta();
                $user->setConnection('mysql_clientes');

                if ($this->hasCol('mysql_clientes', $user->getTable(), 'cuenta_id')) $user->cuenta_id = $cuenta->id;
                if ($this->hasCol('mysql_clientes', $user->getTable(), 'email'))    $user->email = $accEmail ?: ('no-reply+' . Str::lower($rfcPadre) . '@pactopia.local');

                $finalHash = $hashAdmin !== '' ? $hashAdmin : Hash::make($plainPassword);
                if ($this->hasCol('mysql_clientes', $user->getTable(), 'password')) $user->password = $finalHash;

                if ($this->hasCol('mysql_clientes', $user->getTable(), 'activo')) $user->activo = 1;
                if ($this->hasCol('mysql_clientes', $user->getTable(), 'rol'))    $user->rol = 'owner';
                if ($this->hasCol('mysql_clientes', $user->getTable(), 'tipo'))   $user->tipo = 'owner';

                $user->save();
                $this->d($diag, $reqId, 'UsuarioCuenta created', ['user_id' => $user->id ?? null, 'email' => $user->email ?? null]);
            } else {
                $dirty = false;

                if ($this->hasCol('mysql_clientes', $user->getTable(), 'cuenta_id') && (string)($user->cuenta_id ?? '') !== (string)($cuenta->id ?? '')) {
                    $user->cuenta_id = $cuenta->id;
                    $dirty = true;
                }

                if ($hashAdmin !== '' && $this->hasCol('mysql_clientes', $user->getTable(), 'password')) {
                    $raw = (string) $user->getRawOriginal('password');
                    if ($raw === '' || !$this->passwordMatchesAny($plainPassword, $raw)) {
                        $user->password = $hashAdmin;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $user->save();
                    $this->d($diag, $reqId, 'UsuarioCuenta updated', ['user_id' => $user->id ?? null]);
                } else {
                    $this->d($diag, $reqId, 'UsuarioCuenta exists', ['user_id' => $user->id ?? null]);
                }

                if ($hashAdmin === '') {
                    $raw = (string) $user->getRawOriginal('password');
                    if ($raw !== '' && !$this->passwordMatchesAny($plainPassword, $raw)) {
                        return [null, null, 'E5C: password mismatch (usuarios_cuenta)'];
                    }
                }
            }

            return [$cuenta, $user, ''];
        } catch (\Throwable $e) {
            $this->d($diag, $reqId, 'bootstrap exception', ['e' => $e->getMessage()]);
            return [null, null, 'E5X: bootstrap exception'];
        }
    }

    private function extractAdminPasswordHash(object $acc): string
    {
        try {
            $candidates = [
                'password',
                'password_hash',
                'passwd',
                'temp_password_hash',
                'password_temp_hash',
            ];

            foreach ($candidates as $col) {
                if (property_exists($acc, $col) && !empty($acc->{$col})) {
                    return (string) $acc->{$col};
                }
            }
        } catch (\Throwable $e) {}
        return '';
    }

    /* ===================== Password ===================== */

    private function passwordMatchesAny(string $plain, string $hash): bool
    {
        if ($hash && Hash::check($plain, $hash)) return true;
        if ($hash && ClientAuth::check($plain, $hash)) return true;

        $t = trim($plain);
        if ($t !== $plain && Hash::check($t, $hash)) return true;
        if ($t !== $plain && ClientAuth::check($t, $hash)) return true;

        return false;
    }

    /* ===================== Admin accounts lookup ===================== */

    private function findAdminAccountByAnyRfc(string $rfcUpper)
    {
        if ($rfcUpper === '') return null;

        $conn = DB::connection('mysql_admin');

        $acc = $conn->table('accounts')
            ->whereRaw('UPPER(COALESCE(rfc, "")) = ?', [$rfcUpper])
            ->first();

        if ($acc) return $acc;

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

    /* ===================== Ranking / activity ===================== */

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

    private function pickBestCuentaByRfcCandidates($cuentas, array &$diag, string $reqId): array
    {
        $ranked = [];

        foreach ($cuentas as $c) {
            $usuarios = DB::connection('mysql_clientes')
                ->table('usuarios_cuenta as u')
                ->where('u.cuenta_id', $c->id)
                ->select(['u.id','u.email','u.activo','u.rol','u.tipo','u.password'])
                ->get();

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

        usort($ranked, fn ($a, $b) => [$b['owner'], $b['users_count'], $b['ts']] <=> [$a['owner'], $a['users_count'], $a['ts']]);

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

    /* ===================== Throttle ===================== */

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

    /* ===================== Responses / diag ===================== */

    private function invalid(string $identifier, array $diag, string $reqId, string $code)
    {
        $msg = $this->isLocal() ? "Credenciales no válidas. ($code)" : 'Credenciales no válidas.';
        return back()->withErrors(['login' => $msg])
            ->withInput(['login' => $identifier])
            ->with('diag', $diag)
            ->with('diag_req', $reqId);
    }

    private function failBack(string $message, string $identifier, array $diag, string $reqId, string $code)
    {
        $msg = $this->isLocal() ? "$message ($code)" : $message;
        return back()->withErrors(['login' => $msg])
            ->withInput(['login' => $identifier])
            ->with('diag', $diag)
            ->with('diag_req', $reqId);
    }

    private function failValidation(string $message, array $diag, string $reqId, string $code)
    {
        $msg = $this->isLocal() ? "$message ($code)" : $message;
        session()->flash('diag', $diag);
        session()->flash('diag_req', $reqId);
        throw ValidationException::withMessages(['login' => $msg]);
    }

    private function d(array &$diag, string $reqId, string $step, array $ctx = []): void
    {
        $diag[] = ['req' => $reqId, 'step' => $step, 'ctx' => $ctx, 'ts' => now()->toDateTimeString()];
        if ($this->isLocal()) Log::debug('[cliente-login]['.$reqId.'] '.$step, $ctx);
    }

    private function flashDiag(array $diag, string $reqId): void
    {
        if ($this->isLocal()) {
            session()->flash('diag', $diag);
            session()->flash('diag_req', $reqId);
        }
    }

    private function setPostVerifyContext(UsuarioCuenta $user, bool $remember = false): void
    {
        session([
            'post_verify.user_id'  => $user->id,
            'post_verify.remember' => $remember,
        ]);
    }
}
