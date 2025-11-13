<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /**
     * Mostrar formulario de login admin.
     */
    public function showLogin(Request $request)
    {
        // Aseguramos que TODO este flujo use el guard 'admin'
        Auth::shouldUse('admin');

        // Si ya está autenticado como admin, mándalo directo al dashboard admin
        if (Auth::guard('admin')->check()) {
            return redirect()->intended(
                \Route::has('admin.home') ? route('admin.home') : '/'
            );
        }

        return view('admin.auth.login');
    }

    /**
     * Procesar login admin (email o codigo_usuario).
     */
    public function login(Request $request)
    {
        // Importantísimo: fijar contexto del guard admin,
        // para que no intente usar el guard web del cliente.
        Auth::shouldUse('admin');

        $reqId  = (string) Str::ulid();
        $diag   = [];
        $this->d($diag, $reqId, 'Start', [
            'ip' => $request->ip(),
        ]);

        // Acepta email o codigo_usuario (si existe)
        $identifier = $this->normalizeIdentifier(
            (string) ($request->input('email') ?? $request->input('codigo_usuario') ?? '')
        );
        $password   = (string) $request->input('password', '');
        $remember   = $request->boolean('remember', false);

        // Validación mínima (string para permitir email o código)
        $request->merge(['_login' => $identifier]);
        $request->validate([
            '_login'   => ['required','string','max:150'],
            'password' => ['required','string','min:6','max:200'],
        ], [], ['_login' => 'usuario/email']);

        // Throttle (intentos fallidos)
        if ($this->tooManyAttempts($request, $identifier)) {
            $wait = $this->remainingLockSeconds($request, $identifier);
            return $this->failBack(
                "Demasiados intentos. Intenta de nuevo en {$wait}s.",
                $identifier,
                $diag,
                $reqId,
                'E0: throttled'
            );
        }

        // Intento 1: por email (si parece email)
        $ok    = false;
        $guard = Auth::guard('admin');

        $isEmail   = Str::contains($identifier, '@');
        $hasCodigo = $this->colExists('codigo_usuario');

        if ($isEmail) {
            $this->d($diag, $reqId, 'Try by email');
            $ok = $guard->attempt(
                ['email' => Str::lower($identifier), 'password' => $password],
                $remember
            );
        }

        // Intento 2: por codigo_usuario (si existe columna o si el input NO parece email)
        if (!$ok) {
            if ($hasCodigo) {
                $this->d($diag, $reqId, 'Try by codigo_usuario');
                $ok = $guard->attempt(
                    ['codigo_usuario' => $identifier, 'password' => $password],
                    $remember
                );
            } else {
                $this->d($diag, $reqId, 'codigo_usuario column not present, skipped');
            }
        }

        if (!$ok) {
            $this->hitThrottle($request, $identifier);
            return $this->failBack(
                'Credenciales inválidas',
                $identifier,
                $diag,
                $reqId,
                'E1: bad credentials'
            );
        }

        /** @var \App\Models\Admin\Auth\UsuarioAdministrativo $user */
        $user = $guard->user();

        // Superadmin (rol/flag/lista en .env) salta validaciones duras de estado
        $isSuper = $this->isSuper($user);
        $this->d($diag, $reqId, 'Logged in (pre-status checks)', [
            'user_id' => $user?->id,
            'email'   => $user?->email ?? null,
            'super'   => $isSuper,
        ]);

        // Validaciones de estado (si existen las columnas) — solo para no-superadmins
        if (!$isSuper) {
            if ($this->colExists('activo') && (int)($user->activo ?? 0) !== 1) {
                $this->logoutNow($request);
                $this->hitThrottle($request, $identifier);
                return $this->failBack(
                    'Cuenta inactiva',
                    $identifier,
                    $diag,
                    $reqId,
                    'E2: inactiva'
                );
            }

            if ($this->colExists('estatus')) {
                $st = Str::lower((string)($user->estatus ?? ''));
                if (!in_array($st, ['activo','active','enabled','ok'], true)) {
                    $this->logoutNow($request);
                    $this->hitThrottle($request, $identifier);
                    return $this->failBack(
                        'Cuenta no autorizada',
                        $identifier,
                        $diag,
                        $reqId,
                        'E3: estatus bloqueado'
                    );
                }
            }

            if ($this->colExists('is_blocked') && (int)($user->is_blocked ?? 0) === 1) {
                $this->logoutNow($request);
                $this->hitThrottle($request, $identifier);
                return $this->failBack(
                    'Cuenta bloqueada',
                    $identifier,
                    $diag,
                    $reqId,
                    'E4: blocked'
                );
            }
        }

        // Registra último acceso (si el modelo lo soporta) — no interrumpe
        try {
            if (method_exists($user, 'markLastLogin')) {
                $user->markLastLogin($request->ip());
            } else {
                // Fallback suave: si existen columnas, intenta guardarlas
                $updated = false;
                if ($this->colExists('ultimo_login_at')) {
                    $user->ultimo_login_at = now();
                    $updated = true;
                }
                if ($this->colExists('ip_ultimo_login')) {
                    $user->ip_ultimo_login = (string) $request->ip();
                    $updated = true;
                }
                if ($updated && method_exists($user, 'saveQuietly')) {
                    $user->saveQuietly();
                }
            }
        } catch (\Throwable $e) {
            $this->d($diag, $reqId, 'markLastLogin error', ['e' => $e->getMessage()]);
        }

        // Regenera sesión y limpia throttle
        $request->session()->regenerate();
        $this->clearThrottle($request, $identifier);

        // Forzar cambio de contraseña (si existe la bandera/columna)
        if ($this->colExists('force_password_change') && (bool)($user->force_password_change ?? false)) {
            $this->d($diag, $reqId, 'Must change password');
            $this->flashDiag($diag, $reqId);

            return redirect()
                ->route('admin.perfil.edit')
                ->with('password_force', true)
                ->with('warn', 'Debes actualizar tu contraseña antes de continuar.');
        }

        $this->d($diag, $reqId, 'LOGIN OK', [
            'user_id' => $user?->id,
            'email'   => $user?->email ?? null,
        ]);

        $this->flashDiag($diag, $reqId);

        return redirect()->intended(
            \Route::has('admin.home') ? route('admin.home') : '/'
        );
    }

    /**
     * Cerrar sesión admin.
     */
    public function logout(Request $request)
    {
        // logout SIEMPRE del guard admin, limpia sesión
        $this->logoutNow($request);

        // redirigir SIEMPRE al login admin
        return redirect()->route('admin.login');
    }

    /* ===================== Helpers ===================== */

    private function normalizeIdentifier(string $v): string
    {
        $v = trim($v);
        if (Str::contains($v, '@')) {
            return Str::lower($v);
        }
        return $v;
    }

    private function logoutNow(Request $request): void
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function isSuper($user): bool
    {
        try {
            if (!$user) return false;

            $get = fn($k) => method_exists($user, 'getAttribute')
                ? $user->getAttribute($k)
                : ($user->$k ?? null);

            $sa  = (bool)($get('es_superadmin') ?? $get('is_superadmin') ?? $get('superadmin') ?? false);
            if ($sa) return true;

            $rol = strtolower((string)($get('rol') ?? $get('role') ?? ''));
            if ($rol === 'superadmin') return true;

            $envList = array_filter(array_map('trim', explode(',', (string) env('APP_SUPERADMINS', ''))));
            $list    = array_map('strtolower', $envList);
            $email   = Str::lower((string) ($get('email') ?? ''));
            foreach ($list as $allowed) {
                if ($email !== '' && Str::lower(trim($allowed)) === $email) return true;
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function colExists(string $col): bool
    {
        try {
            [$conn, $table] = $this->adminConnAndTable();
            return Schema::connection($conn)->hasColumn($table, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function adminConnAndTable(): array
    {
        $provider = Auth::guard('admin')->getProvider();
        $modelCls = method_exists($provider, 'getModel') ? $provider->getModel() : null;

        if ($modelCls) {
            $m = new $modelCls;
            $conn  = $m->getConnectionName() ?? (config('database.default') ?? 'mysql');
            $table = $m->getTable() ?? 'usuario_administrativos';
            return [$conn, $table];
        }

        $conn  = config('database.connections.mysql_admin')
            ? 'mysql_admin'
            : (config('database.default') ?? 'mysql');

        $table = 'usuario_administrativos';
        return [$conn, $table];
    }

    /* ===================== Throttle (por sesión) ===================== */

    private function throttleKey(Request $r, string $identifier): string
    {
        return 'admin_login_attempts:' . sha1(($r->ip() ?: '0.0.0.0') . '|' . Str::lower($identifier));
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

    /* ===================== Diagnóstico / Debug ===================== */

    private function d(array &$diag, string $reqId, string $step, array $ctx = []): void
    {
        if (app()->environment(['local','development','testing'])) {
            Log::debug('[admin-login]['.$reqId.'] '.$step, $ctx);
        }
        $diag[] = [
            'req'  => $reqId,
            'step' => $step,
            'ctx'  => $ctx,
            'ts'   => now()->toDateTimeString()
        ];
    }

    private function flashDiag(array $diag, string $reqId): void
    {
        if (app()->environment(['local','development','testing'])) {
            session()->flash('admin_diag', $diag);
            session()->flash('admin_diag_req', $reqId);
        }
    }

    private function failBack(string $message, string $identifier, array $diag, string $reqId, string $code)
    {
        $msg = app()->environment(['local','development','testing'])
            ? "$message ($code)"
            : $message;

        $this->flashDiag($diag, $reqId);

        return back()
            ->withErrors(['email' => $msg])
            ->withInput(['email' => $identifier]);
    }
}
