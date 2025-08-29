<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MirrorService
{
    public static function updateEmailVerified(string $email, bool $verified): void
    {
        DB::connection('mysql_admin')->table('clientes')->where('email',$email)->update(['email_verificado'=>$verified]);
        DB::connection('mysql_clientes')->table('clientes')->where('email',$email)->update(['email_verificado'=>$verified]);
    }

    public static function updatePasswordForClientUser(int $clienteIdCli, string $email, string $hash): void
    {
        // usuarios_cliente (cliente)
        DB::connection('mysql_clientes')->table('usuarios_cliente')->where('email',$email)->update(['password'=>$hash]);

        // si quisieras reflejar un hash espejo en admin (si guardas usuarios “hijos” en admin),
        // agrega aquí la actualización en la tabla espejo correspondiente de admin.
    }

    public static function updateAccountStatus(string $codigoUsuario, string $estatus, int $espacioGb, int $hitsAsignados): void
    {
        DB::connection('mysql_admin')->table('clientes')->where('codigo_usuario',$codigoUsuario)
            ->update(['estatus'=>$estatus,'espacio_gb'=>$espacioGb,'hits_asignados'=>$hitsAsignados]);

        DB::connection('mysql_clientes')->table('clientes')->where('codigo_usuario',$codigoUsuario)
            ->update(['estatus'=>$estatus,'espacio_gb'=>$espacioGb,'hits_asignados'=>$hitsAsignados]);
    }
}
