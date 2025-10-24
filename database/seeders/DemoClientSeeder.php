<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoClientSeeder extends Seeder
{
    public function run(): void
    {
        // ADMIN: cuentas
        DB::connection('mysql_admin')->table('accounts')->updateOrInsert(
            ['id'=>101],
            [
                'email'=>'free@example.com','rfc'=>'ABC0102039A1','phone'=>'+5215555555555',
                'plan'=>'FREE','plan_actual'=>'FREE','is_blocked'=>0,'estado_cuenta'=>'activa',
                'email_verified_at'=>null,'phone_verified_at'=>null,
                'created_at'=>now(),'updated_at'=>now()
            ]
        );
        DB::connection('mysql_admin')->table('accounts')->updateOrInsert(
            ['id'=>202],
            [
                'email'=>'pro@example.com','rfc'=>'XYZ010203AA1','phone'=>'+5215555559999',
                'plan'=>'PRO','plan_actual'=>'PRO','is_blocked'=>0,'estado_cuenta'=>'activa',
                'email_verified_at'=>now(),'phone_verified_at'=>null,
                'created_at'=>now(),'updated_at'=>now()
            ]
        );

        // Sub PRO activa
        DB::connection('mysql_admin')->table('subscriptions')->updateOrInsert(
            ['account_id'=>202],
            ['status'=>'active','current_period_end'=>now()->addDays(25),'created_at'=>now(),'updated_at'=>now()]
        );

        // Token email FREE
        DB::connection('mysql_admin')->table('email_verifications')->updateOrInsert(
            ['account_id'=>101,'email'=>'free@example.com'],
            ['token'=>'TOKENFREE1234567890abcdef','expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]
        );

        // CLIENTES: cuentas y usuarios
        DB::connection('mysql_clientes')->table('cuentas_clientes')->updateOrInsert(
            ['rfc_padre'=>'ABC0102039A1'],
            [
                'id'=>1001,'plan_actual'=>'FREE','estado_cuenta'=>'activa','max_usuarios'=>3,
                'hits_asignados'=>50,'espacio_asignado_mb'=>5120,'admin_account_id'=>101,
                'created_at'=>now(),'updated_at'=>now()
            ]
        );

        DB::connection('mysql_clientes')->table('usuarios_cuenta')->updateOrInsert(
            ['email'=>'free@example.com'],
            [
                'id'=>2001,'cuenta_id'=>1001,'tipo'=>'owner','nombre'=>'FREE Owner',
                'password'=>bcrypt('secret'), 'activo'=>0, 'must_change_password'=>0,
                'created_at'=>now(),'updated_at'=>now()
            ]
        );

        DB::connection('mysql_clientes')->table('cuentas_clientes')->updateOrInsert(
            ['rfc_padre'=>'XYZ010203AA1'],
            [
                'id'=>1002,'plan_actual'=>'PRO','estado_cuenta'=>'activa','max_usuarios'=>null,
                'hits_asignados'=>5000,'espacio_asignado_mb'=>15360,'max_empresas'=>9999,
                'max_mass_invoices_per_day'=>100,'admin_account_id'=>202,
                'created_at'=>now(),'updated_at'=>now()
            ]
        );

        DB::connection('mysql_clientes')->table('usuarios_cuenta')->updateOrInsert(
            ['email'=>'pro@example.com'],
            [
                'id'=>2002,'cuenta_id'=>1002,'tipo'=>'owner','nombre'=>'PRO Owner',
                'password'=>bcrypt('secret'), 'activo'=>1, 'must_change_password'=>1,
                'created_at'=>now(),'updated_at'=>now()
            ]
        );
    }
}
