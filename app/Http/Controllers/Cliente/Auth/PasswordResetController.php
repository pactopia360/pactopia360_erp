<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente\CuentaCliente;
use App\Models\Cliente\UsuarioCuenta;
use App\Support\ClientAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cookie;

class PasswordResetController extends Controller
{
    /**
     * POST /cliente/auth/reset-by-rfc
     * Body: rfc=GODE561231GR8
     *
     * Resetea la contraseña del OWNER (o el primer usuario creado si no hay OWNER)
     * de la cuenta hallada por RFC. Marca must_change_password=1 y valida que
     * el hash guardado corresponda a la temporal generada.
     */
    public function resetByRfc(Request $r)
    {
        $data = $r->validate([
            'rfc'       => 'required|string|max:32',
            'send_mail' => 'sometimes|boolean', // para futura notificación
        ]);

        $rfc = Str::upper(trim((string) $data['rfc']));

        /** @var CuentaCliente|null $cuenta */
        $cuenta = CuentaCliente::on('mysql_clientes')
            ->whereRaw('UPPER(rfc_padre)=?', [$rfc])
            ->first();

        if (!$cuenta) {
            throw ValidationException::withMessages([
                'rfc' => 'Cuenta no encontrada por RFC.',
            ]);
        }

        $schema = Schema::connection('mysql_clientes');

        // Owner primero; si no hay, el más antiguo.
        $q = DB::connection('mysql_clientes')
            ->table('usuarios_cuenta')
            ->where('cuenta_id', $cuenta->id);

        if ($schema->hasColumn('usuarios_cuenta', 'rol'))  {
            $q->orderByRaw("FIELD(rol,'owner') DESC");
        }
        if ($schema->hasColumn('usuarios_cuenta', 'tipo')) {
            $q->orderByRaw("FIELD(tipo,'owner') DESC");
        }
        $q->orderBy('created_at', 'asc');

        $owner = $q->first();
        if (!$owner) {
            return response()->json(['ok' => false, 'msg' => 'La cuenta no tiene usuarios.'], 404);
        }

        // Genera temporal segura
        $plain = $this->generateTempPassword(12);

        // $rfcUpper y $email deben estar definidos (usa el que corresponda)
        Cookie::queue(
            Cookie::make('p360_tmp_user_'.$rfcUpper, $ownerEmail, 10, null, null, false, false, false, 'Lax')
        );
        Cookie::queue(
            Cookie::make('p360_tmp_pass_'.$rfcUpper, $plain, 10, null, null, false, false, false, 'Lax')
        );

        try {
            DB::connection('mysql_clientes')->transaction(function () use ($owner, $schema, $plain) {
                /** @var UsuarioCuenta $user */
                $user = UsuarioCuenta::on('mysql_clientes')->lockForUpdate()->find($owner->id);
                if (!$user) {
                    throw new \RuntimeException('No se pudo cargar el modelo de usuario.');
                }

                // Hash + normalización
                $user->password = ClientAuth::make($plain);

                // Limpieza columnas legacy si existen
                try { if ($schema->hasColumn('usuarios_cuenta', 'password_temp'))  { $user->password_temp  = null; } } catch (\Throwable $e) {}
                try { if ($schema->hasColumn('usuarios_cuenta', 'password_plain')) { $user->password_plain = null; } } catch (\Throwable $e) {}

                // Forzar cambio en primer login
                try { if ($schema->hasColumn('usuarios_cuenta', 'must_change_password')) { $user->must_change_password = 1; } } catch (\Throwable $e) {}

                $user->updated_at = now();
                $user->saveQuietly();

                // Verificación inmediata del hash persistido
                $hash = (string) $user->getRawOriginal('password');
                if ($hash === '' || !ClientAuth::check($plain, $hash)) {
                    throw new \RuntimeException('El hash guardado no validó contra la temporal generada.');
                }
            });

            return response()->json([
                'ok'        => true,
                'msg'       => 'Contraseña temporal creada para el OWNER.',
                'rfc'       => $rfc,
                'cuenta_id' => $cuenta->id,
                'user_id'   => $owner->id,
                'email'     => $owner->email,
                // En local devolvemos la temporal para pruebas; en prod NO.
                'password'  => app()->environment('local') ? $plain : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('resetByRfc error', ['rfc' => $rfc, 'e' => $e->getMessage()]);
            return response()->json(['ok' => false, 'msg' => 'Error interno al resetear la contraseña.'], 500);
        }
    }

    /**
     * POST /cliente/auth/reset-by-email
     * Body: email=usuario@dominio.tld
     *
     * Resetea por email exacto (BD clientes). Marca must_change_password=1
     * y valida el hash guardado.
     */
    public function resetByEmail(Request $r)
    {
        $data = $r->validate([
            'email'     => 'required|email:rfc,dns',
            'send_mail' => 'sometimes|boolean',
        ]);

        $email = Str::lower(trim((string) $data['email']));

        /** @var UsuarioCuenta|null $user */
        $user = UsuarioCuenta::on('mysql_clientes')->where('email', $email)->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => 'Usuario no encontrado por email.',
            ]);
        }

        $schema = Schema::connection('mysql_clientes');
        $plain  = $this->generateTempPassword(12);

        try {
            DB::connection('mysql_clientes')->transaction(function () use ($user, $schema, $plain) {
                /** @var UsuarioCuenta $u */
                $u = UsuarioCuenta::on('mysql_clientes')->lockForUpdate()->find($user->id);
                if (!$u) {
                    throw new \RuntimeException('No se pudo cargar el modelo de usuario.');
                }

                $u->password = ClientAuth::make($plain);

                try { if ($schema->hasColumn('usuarios_cuenta', 'password_temp'))  { $u->password_temp  = null; } } catch (\Throwable $e) {}
                try { if ($schema->hasColumn('usuarios_cuenta', 'password_plain')) { $u->password_plain = null; } } catch (\Throwable $e) {}
                try { if ($schema->hasColumn('usuarios_cuenta', 'must_change_password')) { $u->must_change_password = 1; } } catch (\Throwable $e) {}

                $u->updated_at = now();
                $u->saveQuietly();

                $hash = (string) $u->getRawOriginal('password');
                if ($hash === '' || !ClientAuth::check($plain, $hash)) {
                    throw new \RuntimeException('El hash guardado no validó contra la temporal generada.');
                }
            });

            return response()->json([
                'ok'       => true,
                'msg'      => 'Contraseña temporal creada.',
                'user_id'  => $user->id,
                'email'    => $user->email,
                'password' => app()->environment('local') ? $plain : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('resetByEmail error', ['email' => $email, 'e' => $e->getMessage()]);
            return response()->json(['ok' => false, 'msg' => 'Error interno al resetear la contraseña.'], 500);
        }
    }

    /** Generador de temporales sin caracteres confusos. */
    private function generateTempPassword(int $length = 12): string
    {
        $length = max(8, min(48, $length));
        $sets = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '.,;:!?@#$%&*+-_=', // evita comillas/espacios raros
        ];
        $all = implode('', $sets);
        $pwd = '';
        foreach ($sets as $set) { $pwd .= $set[random_int(0, strlen($set) - 1)]; }
        for ($i = strlen($pwd); $i < $length; $i++) { $pwd .= $all[random_int(0, strlen($all) - 1)]; }
        return str_shuffle($pwd);
    }
}
