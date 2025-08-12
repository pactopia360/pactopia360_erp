<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ClientesSeeder extends Seeder
{
    public function run(): void
    {
        // Cuenta cliente
        $cuentaId = (string) Str::uuid();
        DB::connection('mysql_clientes')->table('cuentas_cliente')->insert([
            'id' => $cuentaId,
            'codigo_cliente' => 'CLI-TEST-001',
            'customer_no' => '1001',
            'rfc_padre' => 'XAXX010101000',
            'razon_social' => 'Cuenta de Prueba SA de CV',
            'plan_actual' => 'PREMIUM',
            'modo_cobro' => 'mensual',
            'estado_cuenta' => 'activa',
            'espacio_asignado_mb' => 2048,
            'hits_asignados' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Usuario de la cuenta cliente
        $usuarioId = (string) Str::uuid();
        DB::connection('mysql_clientes')->table('usuarios_cuenta')->insert([
            'id' => $usuarioId,
            'cuenta_id' => $cuentaId,
            'tipo' => 'padre',
            'nombre' => 'Cliente Demo',
            'email' => 'cliente@demo.com',
            'password' => Hash::make('123456'),
            'rol' => 'owner',
            'activo' => true,
            'ultimo_login_at' => now(),
            'ip_ultimo_login' => '127.0.0.1',
            'sync_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Plan de prueba cliente
        DB::connection('mysql_clientes')->table('planes')->insert([
            'id' => 1,
            'nombre' => 'Plan Premium',
            'precio_mensual' => 999.00,
            'precio_anual' => 9999.00,
            'espacio_mb' => 2048,
            'hits_iniciales' => 100,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Suscripción cliente
        DB::connection('mysql_clientes')->table('suscripciones')->insert([
            'id' => 1,
            'cuenta_id' => $cuentaId,
            'plan_id' => 1,
            'modo' => 'mensual',
            'vigente_desde' => now(),
            'vigente_hasta' => now()->addMonth(),
            'estatus' => 'activa',
            'precio_neto' => 999.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
