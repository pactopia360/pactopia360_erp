<?php

namespace App\Http\Controllers\Admin\Soporte;

use App\Http\Controllers\Controller;
use App\Models\Cliente\CuentaCliente;
use App\Models\Cliente\UsuarioCuenta;
use App\Support\ClientAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ResetClientePasswordController extends Controller
{
    public function __construct()
    {
        // Solo admins autenticados
        $this->middleware('auth:admin');
    }

    /** Formulario simple para reset por RFC */
    public function showForm()
    {
        $this->abortIfNotSuper();
        return view('admin.soporte.reset_pass');
    }

    /** POST: reset por RFC (OWNER prioritario) */
    public function resetByRfc(Request $r)
    {
        $this->abortIfNotSuper();

        $data = $r->validate([
            'rfc' => ['required','string','max:32'],
        ]);

        $rfc = Str::upper(trim($data['rfc']));

        // 1) Buscar cuenta por RFC (mysql_clientes.cuentas_cliente.rfc_padre)
        /** @var \App\Models\Cliente\CuentaCliente|null $cuenta */
        $cuenta = CuentaCliente::on('mysql_clientes')
            ->whereRaw('UPPER(rfc_padre) = ?', [$rfc])
            ->first();

        if (!$cuenta) {
            return back()->withErrors(['rfc' => 'No existe una cuenta con ese RFC.']);
        }

        // 2) Buscar OWNER primero (por rol/tipo), si no, el primer usuario más antiguo
        $schema = Schema::connection('mysql_clientes');

        $q = DB::connection('mysql_clientes')->table('usuarios_cuenta')
            ->where('cuenta_id', $cuenta->id);

        if ($schema->hasColumn('usuarios_cuenta', 'rol')) {
            $q->orderByRaw("FIELD(rol,'owner','admin_owner','dueño','propietario') DESC");
        }
        if ($schema->hasColumn('usuarios_cuenta', 'tipo')) {
            $q->orderByRaw("FIELD(tipo,'owner','admin_owner','dueño','propietario','padre') DESC");
        }
        $q->orderBy('created_at', 'asc');

        $u = $q->first();
        if (!$u) {
            return back()->withErrors(['rfc' => 'La cuenta no tiene usuarios asociados.']);
        }

        // 3) Generar temporal robusta
        $plain = $this->generateTempPassword(12);

        // 4) Guardar hash normalizado
        /** @var \App\Models\Cliente\UsuarioCuenta|null $userModel */
        $userModel = UsuarioCuenta::on('mysql_clientes')->find($u->id);
        if (!$userModel) {
            return back()->withErrors(['rfc' => 'No fue posible cargar el usuario para actualizar el password.']);
        }

        $userModel->password = ClientAuth::make($plain);

        if ($schema->hasColumn('usuarios_cuenta', 'password_temp'))  { $userModel->password_temp  = null; }
        if ($schema->hasColumn('usuarios_cuenta', 'password_plain')) { $userModel->password_plain = null; }

        if ($schema->hasColumn('usuarios_cuenta', 'must_change_password')) {
            $userModel->must_change_password = true;
        }

        $userModel->saveQuietly();

        // === 4.1) Sesión y Cookies para que el admin lo vea de inmediato ===
        // Sesiones (panel admin lee estas claves)
        session([
            "tmp_user.$rfc" => $userModel->email,
            "tmp_pass.$rfc" => $plain,
            'tmp_last'      => [
                'key'  => $rfc,
                'user' => $userModel->email,
                'pass' => $plain,
                'ts'   => now()->toDateTimeString(),
            ],
        ]);

        // Cookies (fallback mostrado en la vista, 10 minutos)
        $min = 10;
        cookie()->queue(cookie("p360_tmp_user_{$rfc}", $userModel->email, $min, null, null, false, false, false, 'Lax'));
        cookie()->queue(cookie("p360_tmp_pass_{$rfc}", $plain,           $min, null, null, false, false, false, 'Lax'));

        // 5) Confirmación
        return back()->with([
            'ok'   => true,
            'msg'  => 'Contraseña temporal generada y asignada al OWNER (o primer usuario) de la cuenta.',
            'reset'=> [
                'rfc'       => $rfc,
                'cuenta_id' => $cuenta->id,
                'user_id'   => $userModel->id,
                'email'     => $userModel->email,
                'password'  => $plain, // visible solo al superadmin
            ],
        ]);
    }

    /* ================= Helpers ================= */

    private function abortIfNotSuper(): void
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin || !$this->isSuper($admin)) {
            abort(403, 'Solo superadministradores.');
        }
    }

    private function isSuper($user): bool
    {
        try {
            if (!$user) return false;
            $get = fn($k) => method_exists($user, 'getAttribute') ? $user->getAttribute($k) : ($user->$k ?? null);

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

    /** Genera temporal robusta (sin caracteres confusos) */
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
}
