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

        // reemplaza temporal y marca como cambiada
        $usuario->password = Hash::make($request->password);
        if (\Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta','password_temp')) {
            $usuario->password_temp = null;
        }
        if (\Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta','password_plain')) {
            $usuario->password_plain = null;
        }
        if (array_key_exists('must_change_password', $usuario->getAttributes())) {
            $usuario->must_change_password = false;
        }

        $usuario->save();

        return redirect()->route('cliente.home')->with('ok', 'Contraseña actualizada correctamente.');
    }


    /* ============================================================
     * 2) Olvidé mi contraseña (cliente)
     * ============================================================ */

    /** GET /cliente/password/forgot */
    public function showLinkRequestForm()
    {
        return view('cliente.auth.forgot');
    }

    /**
     * POST /cliente/password/email
     * Acepta email o RFC. Si es RFC, buscamos un usuario de esa cuenta.
     * Guarda token en mysql_clientes.password_reset_tokens y envía correo.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|string|max:150',
        ]);

        $identifier = trim((string)$request->input('email'));
        $isEmail    = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

        // 1) Resolver usuarios por email o RFC
        $usuarios = collect();

        if ($isEmail) {
            $usuarios = UsuarioCuenta::on('mysql_clientes')
                ->where('email', strtolower($identifier))
                ->get();
        } else {
            $rfcUpper = strtoupper(preg_replace('/\s+/', '', $identifier));
            $cuentas = CuentaCliente::on('mysql_clientes')
                ->whereRaw('UPPER(rfc_padre) = ?', [$rfcUpper])
                ->get();

            if ($cuentas->isNotEmpty()) {
                $usuarios = UsuarioCuenta::on('mysql_clientes')
                    ->whereIn('cuenta_id', $cuentas->pluck('id'))
                    ->get();
            }
        }

        // 2) Respuesta neutra (no revelamos existencia)
        if ($usuarios->isEmpty()) {
            return back()->with('ok', 'Si encontramos una cuenta asociada, te enviaremos una contraseña temporal.');
        }

        // 3) Generar una misma clave temporal y setearla como hash en password_temp para todos
        $plain = $this->makeHumanTemp();

        foreach ($usuarios as $u) {
            $u->password_temp = \Illuminate\Support\Facades\Hash::make($plain);
            if (\Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta','must_change_password')) {
                $u->must_change_password = true;
            }
            if (\Schema::connection('mysql_clientes')->hasColumn('usuarios_cuenta','password_plain')) {
                $u->password_plain = null;
            }
            // opcional: borrar password para forzar el flujo por temp si aún existía uno legado
            // $u->password = $u->password; // lo dejamos intacto (si existe) para no romper sesiones actuales
            $u->save();

            try {
                \Mail::raw(
                    "Hola,\n\nTu contraseña temporal de Pactopia360 es: {$plain}\n\n" .
                    "Puedes iniciar sesión con tu CORREO o con tu RFC. " .
                    "Por seguridad, te pediremos cambiarla al entrar.\n\n— Equipo Pactopia360",
                    function ($m) use ($u) {
                        $m->to($u->email)->subject('Acceso temporal - Pactopia360');
                    }
                );
            } catch (\Throwable $e) {
                // silencio para no revelar existencia; se podría loguear en local
            }
        }

        return back()->with('ok', 'Si encontramos una cuenta asociada, te enviamos una contraseña temporal.');
    }

    /** Generador simple, legible y robusto (evita 0/O/I/1) */
    private function makeHumanTemp(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $pick = function (int $n) use ($alphabet) {
            $o = '';
            for ($i=0;$i<$n;$i++) {
                $o .= $alphabet[random_int(0, strlen($alphabet)-1)];
            }
            return $o;
        };
        // ejemplo: 4-4-4 (p.ej. Z8DJ-7QGK-2XHM)
        return $pick(4) . '-' . $pick(4) . '-' . $pick(4);
    }


    /** GET /cliente/password/reset/{token} */
    public function showResetForm(string $token, Request $request)
    {
        $email = $request->query('e');
        return view('cliente.auth.reset', compact('token', 'email'));
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

        $email = strtolower(trim($request->email));
        $token = $request->token;

        // Validación de token (TTL 60 min)
        $row = DB::connection('mysql_clientes')->table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $token)
            ->first();

        if (!$row) {
            return back()->withErrors(['token' => 'El enlace de restablecimiento no es válido.'])->withInput();
        }

        $createdAt = optional($row->created_at) ? \Illuminate\Support\Carbon::parse($row->created_at) : now()->subHours(24);
        if ($createdAt->lt(now()->subMinutes(60))) {
            DB::connection('mysql_clientes')->table('password_reset_tokens')
                ->where('email', $email)->delete();

            return back()->withErrors(['token' => 'El enlace de restablecimiento ha expirado. Solicita uno nuevo.']);
        }

        $usuario = UsuarioCuenta::on('mysql_clientes')->where('email', $email)->first();
        if (!$usuario) {
            DB::connection('mysql_clientes')->table('password_reset_tokens')->where('email', $email)->delete();
            return back()->withErrors(['email' => 'No se encontró el usuario.'])->withInput();
        }

        $usuario->password = Hash::make($request->password);
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

    /** Detección básica de RFC (con o sin homoclave). */
    private function isRfc(string $value): bool
    {
        $v = strtoupper(preg_replace('/\s+/', '', $value));
        return (bool) preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{2,3}$/', $v);
    }
}
