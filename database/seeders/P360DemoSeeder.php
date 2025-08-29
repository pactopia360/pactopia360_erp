<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class P360DemoSeeder extends Seeder {
    public function run(): void {
        $cuentaId = (string) Str::uuid();

        // Crear cuenta demo
        DB::table('cuentas')->insert([
            'id' => $cuentaId,
            'rfc_padre' => 'XAXX010101000',
            'razon_social' => 'Pactopia Demo',
            'codigo_cliente' => 'XAX-DEMO',
            'email_principal' => 'demo@pactopia360.com',
            'email_verified_at' => now(),
            'plan_id' => 1, // free
            'estado' => 'free',
            'timbres' => 50,
            'espacio_mb' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Usuario admin demo
        DB::table('usuarios_admin')->insert([
            'cuenta_id' => $cuentaId,
            'nombre' => 'Administrador Demo',
            'email' => 'admin@pactopia360.com',
            'password' => Hash::make('demo1234'),
            'rol' => 'superadmin',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cliente espejo
        $clienteId = (string) Str::uuid();
        DB::table('clientes')->insert([
            'id' => $clienteId,
            'empresa' => 'Pactopia Demo',
            'rfc' => 'XAXX010101000',
            'estado' => 'activo',
            'plan_id' => 1,
            'timbres' => 50,
            'espacio_mb' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Usuario cliente demo
        DB::table('usuarios_clientes')->insert([
            'cliente_id' => $clienteId,
            'nombre' => 'Usuario Cliente Demo',
            'email' => 'cliente@pactopia360.com',
            'password' => Hash::make('cliente1234'),
            'codigo_usuario' => 'CLI-DEMO',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "[P360] Cuenta + Cliente + Usuarios demo creados.\n";
    }
}
