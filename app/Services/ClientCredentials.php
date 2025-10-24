<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Support\ClientAuth;

final class ClientCredentials
{
    /** Genera y asigna una contraseña temporal al OWNER (o primer usuario) de la cuenta por RFC. */
    public static function resetOwnerByRfc(string $rfc): array
    {
        $rfc = Str::upper(trim($rfc));
        $conn = DB::connection('mysql_clientes');
        $schema = Schema::connection('mysql_clientes');

        // Localiza cuenta por RFC
        $cuenta = $conn->table('cuentas_cliente')->whereRaw('UPPER(rfc_padre)=?', [$rfc])->first();
        if (!$cuenta) {
            return ['ok'=>false,'error'=>'Cuenta no encontrada en espejo (mysql_clientes.cuentas_cliente).'];
        }

        // Owner primero; si no hay, el más antiguo
        $q = $conn->table('usuarios_cuenta')->where('cuenta_id', $cuenta->id);
        if ($schema->hasColumn('usuarios_cuenta','rol'))  $q->orderByRaw("FIELD(rol,'owner') DESC");
        if ($schema->hasColumn('usuarios_cuenta','tipo')) $q->orderByRaw("FIELD(tipo,'owner') DESC");
        $q->orderBy('created_at','asc');
        $u = $q->first();
        if (!$u) return ['ok'=>false,'error'=>'La cuenta no tiene usuarios.'];

        // Temporal amigable
        $tmp = preg_replace('/[^A-Za-z0-9@#\-\_\.\!\?]/', '', \Illuminate\Support\Str::password(12));
        if (!$tmp || strlen($tmp) < 8) $tmp = 'P360#'.Str::random(8);

        // Escribe hash normalizado
        $hash = ClientAuth::make($tmp);

        // === PAYLOAD DINÁMICO, SOLO si existen columnas ===
        $payload = [
            'password'   => $hash,
            'updated_at' => now(),
        ];
        if ($schema->hasColumn('usuarios_cuenta','password_temp')) {
            $payload['password_temp'] = null;
        }
        if ($schema->hasColumn('usuarios_cuenta','password_plain')) {
            $payload['password_plain'] = null;
        }
        if ($schema->hasColumn('usuarios_cuenta','must_change_password')) {
            $payload['must_change_password'] = 1;
        }

        $conn->table('usuarios_cuenta')->where('id',$u->id)->update($payload);

        // Relee y valida inmediatamente
        $re = $conn->table('usuarios_cuenta')->select('email','password')->where('id',$u->id)->first();
        if (!$re || !is_string($re->password)) {
            return ['ok'=>false,'error'=>'No fue posible confirmar la actualización de contraseña.'];
        }
        if (!ClientAuth::check($tmp, (string)$re->password)) {
            return ['ok'=>false,'error'=>'La temporal no coincide con el hash guardado.'];
        }

        return [
            'ok'      => true,
            'rfc'     => $rfc,
            'user_id' => $u->id,
            'email'   => $re->email,
            'pass'    => $tmp,
        ];
    }
}
