<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminCoreSeeder extends Seeder
{
    public function run(): void
    {
        // Planes
        DB::connection('mysql_admin')->table('planes')->updateOrInsert(
            ['clave' => 'free'],
            ['nombre'=>'Free','descripcion'=>'Cuenta gratuita','precio_mensual'=>0,'precio_anual'=>0,'activo'=>true,'updated_at'=>now(),'created_at'=>now()]
        );
        DB::connection('mysql_admin')->table('planes')->updateOrInsert(
            ['clave' => 'premium'],
            ['nombre'=>'Premium','descripcion'=>'Cuenta Pro','precio_mensual'=>999,'precio_anual'=>999*12,'activo'=>true,'updated_at'=>now(),'created_at'=>now()]
        );

        // Superadmin
        DB::connection('mysql_admin')->table('usuarios_admin')->updateOrInsert(
            ['email'=>'superadmin@pactopia.test'],
            ['nombre'=>'Super Admin','password'=>Hash::make('secret123'), 'rol'=>'superadmin','created_at'=>now(),'updated_at'=>now()]
        );

        // Cliente demo mínimo (lo real harás desde el formulario de registro)
        if (!DB::connection('mysql_admin')->table('clientes')->where('rfc','DEMO010101AA1')->exists()) {
            DB::connection('mysql_admin')->table('clientes')->insert([
                'codigo_usuario'   => 'DEMO01-ABCD1234-'.strtoupper(base_convert(time(),10,36)),
                'rfc'              => 'DEMO010101AA1',
                'razon_social'     => 'Empresa Demo S.A. de C.V.',
                'email'            => 'demo@empresa.test',
                'email_verificado' => true,
                'plan_id'          => DB::connection('mysql_admin')->table('planes')->where('clave','free')->value('id'),
                'estatus'          => 'free',
                'espacio_gb'       => 1,
                'hits_asignados'   => 20,
                'hits_consumidos'  => 0,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }
}
