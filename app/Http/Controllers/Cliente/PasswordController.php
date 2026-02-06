<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente\UsuarioCuenta;
use App\Models\Cliente\CuentaCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class PasswordController extends Controller
{
    /* ============================================================
     * 1) Primer inicio: cambio obligatorio
     * ============================================================ */
    public function showFirst()
    {
        return view('cliente.auth.password_first');
    }

    public function updateFirst(Request $request)
    {
        $request->validate([
            'password' => [
                'required', 'string', 'min:8',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&._-]/',
                'confirmed',
            ],
        ], [
            'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex'     => 'Debe incluir al menos un número y un carácter especial.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        $usuario = Auth::guard('web')->user();
        if (!$usuario) {
            return redirect()->route('cliente.login')->withErrors([
                'email' => 'Debes iniciar sesión primero.',
            ]);
        }

        $usuario->password = Hash::make($request->password);

        if (\Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta', 'password_temp')) {
            $usuario->password_temp = null;
        }
        if (\Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta', 'password_plain')) {
            $usuario->password_plain = null;
        }
        if (array_key_exists('must_change_password', $usuario->getAttributes())) {
            $usuario->must_change_password = false;
        }

        $usuario->save();

        return redirect()->route('cliente.home')->with('ok', 'Contraseña actualizada correctamente.');
    }

    /* ============================================================
     * 2) Olvidé mi contraseña (cliente) — PRO: link firmado con token
     * ============================================================ */

    /** GET /cliente/password/forgot */
    public function showLinkRequestForm()
    {
        return view('cliente.auth.forgot');
    }

    /**
     * POST /cliente/password/email
     * Acepta email o RFC. Si es RFC, resolvemos a emails de usuarios de esa cuenta.
     * Guardamos token (hasheado) en mysql_clientes.password_reset_tokens y enviamos link.
     */
    public function sendResetLinkEmail(Request $request)
    {
        // ✅ Compat: acepta login o email
        $request->validate([
            'login' => 'nullable|string|max:150',
            'email' => 'nullable|string|max:150',
        ], [
            'login.max' => 'El valor excede el máximo permitido.',
            'email.max' => 'El valor excede el máximo permitido.',
        ]);

        $identifier = trim((string) ($request->input('login') ?: $request->input('email') ?: ''));
        if ($identifier === '') {
            return back()->withErrors(['login' => 'Ingresa tu correo o RFC.'])->withInput();
        }

        // 🔎 Resolver emails
        [$emails, $normalizedForUi] = $this->resolveEmailsFromIdentifier($identifier);

        // ✅ Log local (debug)
        if (app()->environment('local')) {
            \Log::info('[CLIENTE-PASS] request', [
                'identifier' => $identifier,
                'resolved_emails_count' => $emails->count(),
                'resolved_emails' => $emails->values()->all(),
            ]);
        }

        // Respuesta neutra
        if ($emails->isEmpty()) {
            return back()->with('ok', 'Si encontramos una cuenta asociada, te enviaremos un enlace para restablecer tu contraseña.');
        }

        // ✅ Verifica tabla/columnas antes de insertar (para evitar “no envía” silencioso)
        try {
            if (!\Schema::connection('mysql_clientes')->hasTable('password_reset_tokens')) {
                if (app()->environment('local')) {
                    \Log::error('[CLIENTE-PASS] missing_table password_reset_tokens');
                }
                // En PROD mantenemos neutro; en local mostramos claro para arreglar rápido
                return app()->environment('local')
                    ? back()->withErrors(['login' => 'Falta la tabla mysql_clientes.password_reset_tokens.'])->withInput()
                    : back()->with('ok', 'Si encontramos una cuenta asociada, te enviaremos un enlace para restablecer tu contraseña.');
            }

            $cols = \Schema::connection('mysql_clientes')->getColumnListing('password_reset_tokens');
            foreach (['email','token','created_at'] as $need) {
                if (!in_array($need, $cols, true)) {
                    if (app()->environment('local')) {
                        \Log::error('[CLIENTE-PASS] bad_schema password_reset_tokens', ['cols' => $cols]);
                    }
                    return app()->environment('local')
                        ? back()->withErrors(['login' => 'La tabla password_reset_tokens no tiene columnas requeridas (email, token, created_at).'])->withInput()
                        : back()->with('ok', 'Si encontramos una cuenta asociada, te enviaremos un enlace para restablecer tu contraseña.');
                }
            }
        } catch (\Throwable $e) {
            if (app()->environment('local')) {
                \Log::error('[CLIENTE-PASS] schema_check_failed', ['err' => $e->getMessage()]);
            }
            return back()->with('ok', 'Si encontramos una cuenta asociada, te enviaremos un enlace para restablecer tu contraseña.');
        }

        // Genera token seguro (URL-safe) y guarda HASH en DB
        $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $tokenHash  = Hash::make($plainToken);
        $now        = now();

        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '') continue;

            try {
                // limpia tokens previos
                DB::connection('mysql_clientes')->table('password_reset_tokens')
                    ->where('email', $email)
                    ->delete();

                DB::connection('mysql_clientes')->table('password_reset_tokens')->insert([
                    'email'      => $email,
                    'token'      => $tokenHash,
                    'created_at' => $now,
                ]);

                if (app()->environment('local')) {
                    \Log::info('[CLIENTE-PASS] token_saved', ['email' => $email]);
                }
            } catch (\Throwable $e) {
                if (app()->environment('local')) {
                    \Log::error('[CLIENTE-PASS] token_save_failed', ['email' => $email, 'err' => $e->getMessage()]);
                }
                // no revelamos al usuario
                continue;
            }

            $resetUrl = route('cliente.password.reset', ['token' => $plainToken]) . '?e=' . urlencode($email);

            try {
                Mail::raw(
                    "Hola,\n\n" .
                    "Recibimos una solicitud para restablecer tu contraseña de Pactopia360.\n\n" .
                    "Abre este enlace para crear una nueva contraseña (válido por 60 minutos):\n" .
                    $resetUrl . "\n\n" .
                    "Si tú no solicitaste esto, puedes ignorar este mensaje.\n\n" .
                    "— Equipo Pactopia360",
                    function ($m) use ($email) {
                        $m->to($email)->subject('Restablecer contraseña · Pactopia360');
                    }
                );

                if (app()->environment('local')) {
                    \Log::info('[CLIENTE-PASS] mail_sent', ['email' => $email, 'resetUrl' => $resetUrl]);
                }
            } catch (\Throwable $e) {
                // Silencioso hacia usuario, visible en log
                if (app()->environment('local')) {
                    \Log::warning('[CLIENTE-PASS] mail_failed', [
                        'email' => $email,
                        'err' => $e->getMessage(),
                        'resetUrl' => $resetUrl,
                    ]);
                }
            }
        }

        return back()->with('ok', 'Si encontramos una cuenta asociada, te enviamos un enlace para restablecer tu contraseña.');
    }

    /** GET /cliente/password/reset/{token} */
    public function showResetForm(string $token, Request $request)
    {
        $email = (string) $request->query('e', '');
        $email = strtolower(trim($email));

        return view('cliente.auth.reset', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /** POST /cliente/password/reset */
    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email:rfc,dns|max:150',
            'password' => [
                'required', 'string', 'min:8',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&._-]/',
                'confirmed',
            ],
        ], [
            'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex'     => 'La contraseña debe incluir al menos un número y un carácter especial.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        $email = strtolower(trim((string) $request->input('email')));
        $token = (string) $request->input('token');

        $row = DB::connection('mysql_clientes')->table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$row) {
            return back()->withErrors(['token' => 'El enlace de restablecimiento no es válido.'])->withInput();
        }

        // TTL 60 min
        $createdAt = !empty($row->created_at) ? Carbon::parse($row->created_at) : now()->subHours(24);
        if ($createdAt->lt(now()->subMinutes(60))) {
            DB::connection('mysql_clientes')->table('password_reset_tokens')
                ->where('email', $email)->delete();

            return back()->withErrors(['token' => 'El enlace de restablecimiento ha expirado. Solicita uno nuevo.'])->withInput();
        }

        // Token hasheado
        $tokenHash = (string) ($row->token ?? '');
        if ($tokenHash === '' || !Hash::check($token, $tokenHash)) {
            return back()->withErrors(['token' => 'El enlace de restablecimiento no es válido.'])->withInput();
        }

        $usuario = UsuarioCuenta::on('mysql_clientes')->where('email', $email)->first();
        if (!$usuario) {
            DB::connection('mysql_clientes')->table('password_reset_tokens')->where('email', $email)->delete();
            return back()->withErrors(['email' => 'No se encontró el usuario.'])->withInput();
        }

        $usuario->password = Hash::make((string) $request->input('password'));

        if (\Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta', 'password_temp')) {
            $usuario->password_temp = null;
        }
        if (\Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta', 'password_plain')) {
            $usuario->password_plain = null;
        }
        if (array_key_exists('must_change_password', $usuario->getAttributes())) {
            $usuario->must_change_password = false;
        }

        $usuario->save();

        DB::connection('mysql_clientes')->table('password_reset_tokens')->where('email', $email)->delete();

        return redirect()->route('cliente.login')->with('ok', 'Tu contraseña fue restablecida. Ahora puedes iniciar sesión.');
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    /**
     * Acepta correo o RFC.
     * - Si es email: devuelve ese email si existe usuario(s) con ese email.
     * - Si es RFC: busca cuentas por rfc_padre o rfc y devuelve emails de usuarios de esas cuentas.
     * Nunca debe tirar error, solo colecciones.
     *
     * @return array{0:\Illuminate\Support\Collection,1:string}
     */
    private function resolveEmailsFromIdentifier(string $identifier): array
    {
        $id = trim((string) $identifier);
        $isEmail = filter_var($id, FILTER_VALIDATE_EMAIL) !== false;

        if ($isEmail) {
            $email = strtolower($id);

            $exists = UsuarioCuenta::on('mysql_clientes')
                ->where('email', $email)
                ->exists();

            return [$exists ? collect([$email]) : collect(), $email];
        }

        // RFC
        $rfc = strtoupper(preg_replace('/\s+/', '', $id));
        $cuentas = CuentaCliente::on('mysql_clientes')
            ->whereRaw('UPPER(rfc_padre) = ?', [$rfc])
            ->orWhereRaw('UPPER(rfc) = ?', [$rfc])
            ->get(['id']);

        if ($cuentas->isEmpty()) {
            return [collect(), $rfc];
        }

        $emails = UsuarioCuenta::on('mysql_clientes')
            ->whereIn('cuenta_id', $cuentas->pluck('id'))
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn($e) => strtolower(trim((string) $e)))
            ->filter(fn($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        return [$emails, $rfc];
    }
}
