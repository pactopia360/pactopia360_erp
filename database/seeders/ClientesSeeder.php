<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class ClientesSeeder extends Seeder
{
    /**
     * Conexión de clientes.
     */
    protected string $conn = 'mysql_clientes';

    public function run(): void
    {
        // ====== 1) Cuenta demo (idempotente por codigo_cliente) ======
        $now = Carbon::now();

        // Si ya existe la cuenta con codigo_cliente = CLI-TEST-001, solo actualizamos campos;
        // si no existe, la creamos con un UUID nuevo.
        $cuenta = DB::connection($this->conn)->table('cuentas_cliente')
            ->where('codigo_cliente', 'CLI-TEST-001')
            ->first();

        if ($cuenta) {
            DB::connection($this->conn)->table('cuentas_cliente')
                ->where('id', $cuenta->id)
                ->update([
                    'customer_no'         => 1001,
                    'rfc_padre'           => 'XAXX010101000',
                    'razon_social'        => 'Cuenta de Prueba SA de CV',
                    'plan_actual'         => 'PREMIUM',
                    'modo_cobro'          => 'mensual',
                    'estado_cuenta'       => 'activa',
                    'espacio_asignado_mb' => 2048,
                    'hits_asignados'      => 100,
                    'updated_at'          => $now,
                ]);
            $cuentaId = $cuenta->id;
        } else {
            $cuentaId = (string) Str::uuid();
            DB::connection($this->conn)->table('cuentas_cliente')->insert([
                'id'                  => $cuentaId,
                'codigo_cliente'      => 'CLI-TEST-001',
                'customer_no'         => 1001,
                'rfc_padre'           => 'XAXX010101000',
                'razon_social'        => 'Cuenta de Prueba SA de CV',
                'plan_actual'         => 'PREMIUM',
                'modo_cobro'          => 'mensual',
                'estado_cuenta'       => 'activa',
                'espacio_asignado_mb' => 2048,
                'hits_asignados'      => 100,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        // ====== 2) Usuario dueño de la cuenta (idempotente por email) ======
        $email = 'cliente@demo.com';
        $usuario = DB::connection($this->conn)->table('usuarios_cuenta')
            ->where('email', $email)
            ->first();

        if ($usuario) {
            DB::connection($this->conn)->table('usuarios_cuenta')
                ->where('id', $usuario->id)
                ->update([
                    'cuenta_id'        => $cuentaId,
                    'tipo'             => 'padre',
                    'nombre'           => 'Cliente Demo',
                    // Solo si quieres forzar el password cada vez, descomenta la línea siguiente:
                    // 'password'      => Hash::make('demo1234'),
                    'rol'              => 'owner',
                    'activo'           => 1,
                    'ultimo_login_at'  => $now,
                    'ip_ultimo_login'  => '127.0.0.1',
                    'sync_version'     => DB::raw('GREATEST(sync_version, 1)'),
                    'updated_at'       => $now,
                ]);
        } else {
            DB::connection($this->conn)->table('usuarios_cuenta')->insert([
                'id'               => (string) Str::uuid(),
                'cuenta_id'        => $cuentaId,
                'tipo'             => 'padre',
                'nombre'           => 'Cliente Demo',
                'email'            => $email,
                'password'         => Hash::make('demo1234'),
                'rol'              => 'owner',
                'activo'           => 1,
                'ultimo_login_at'  => $now,
                'ip_ultimo_login'  => '127.0.0.1',
                'sync_version'     => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // (Opcional) agrega aquí otros datos espejo (planes_cliente, suscripciones, etc.) usando el mismo patrón idempotente.
    }
}
