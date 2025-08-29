<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDemoBillingSeeder extends Seeder
{
    protected string $conn = 'mysql_admin';

    public function run(): void
    {
        $hasClave = Schema::connection($this->conn)->hasColumn('planes', 'clave');
        $now = now();

        // Definir filas según exista o no 'clave'
        $planes = $hasClave
            ? [
                ['clave' => 'free',    'nombre' => 'Free',    'costo_mensual' => 0,   'costo_anual' => 0,        'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['clave' => 'premium', 'nombre' => 'Premium', 'costo_mensual' => 999, 'costo_anual' => 999 * 12, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
              ]
            : [
                ['nombre' => 'Free',    'costo_mensual' => 0,   'costo_anual' => 0,        'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'Premium', 'costo_mensual' => 999, 'costo_anual' => 999 * 12, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now],
              ];

        // Upsert por la clave correcta
        foreach ($planes as $p) {
            $unique = $hasClave ? ['clave' => $p['clave']] : ['nombre' => $p['nombre']];
            DB::connection($this->conn)->table('planes')->updateOrInsert($unique, $p);
        }

        // Recuperar id del plan Premium (por clave o por nombre)
        $premium = $hasClave
            ? DB::connection($this->conn)->table('planes')->where('clave', 'premium')->first()
            : DB::connection($this->conn)->table('planes')->where('nombre', 'Premium')->first();

        // ==============================
        // Cuentas demo
        // ==============================
        $cuentas = [
            [
                'id'              => (string) Str::uuid(),
                'rfc_padre'       => 'XAXX010101000',
                'razon_social'    => 'Demo S.A. de C.V.',
                'codigo_cliente'  => 'XAXX010101000-'.Str::ulid()->toBase32(),
                'email_principal' => 'demo1@cliente.test',
                'plan_id'         => $premium->id ?? null,
                'estado'          => 'premium',
                'timbres'         => 500,
                'espacio_mb'      => 50 * 1024,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'id'              => (string) Str::uuid(),
                'rfc_padre'       => 'AAA010101AAA',
                'razon_social'    => 'Cliente Mensual Pendiente',
                'codigo_cliente'  => 'AAA010101AAA-'.Str::ulid()->toBase32(),
                'email_principal' => 'demo2@cliente.test',
                'plan_id'         => $premium->id ?? null,
                'estado'          => 'premium',
                'timbres'         => 100,
                'espacio_mb'      => 50 * 1024,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ];

        // Evitar duplicados por rfc_padre (índice unique)
        foreach ($cuentas as $c) {
            DB::connection($this->conn)->table('cuentas')->updateOrInsert(
                ['rfc_padre' => $c['rfc_padre']], // <-- clave única real en tu tabla
                $c
            );
        }

        // ==============================
        // Pagos demo
        // ==============================
        $cuenta1 = DB::connection($this->conn)->table('cuentas')->where('email_principal', 'demo1@cliente.test')->first();
        if ($cuenta1) {
            DB::connection($this->conn)->table('pagos')->updateOrInsert(
                ['id'=>null],
                [
                    'cuenta_id'  => $cuenta1->id,
                    'concepto'   => 'Suscripción Anual Premium',
                    'monto'      => 999*12,
                    'metodo'     => 'transferencia',
                    'estatus'    => 'pagado',
                    'fecha'      => now(),                        // <--- AÑADIR
                    'fecha_pago' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $cuenta2 = DB::connection($this->conn)->table('cuentas')->where('email_principal', 'demo2@cliente.test')->first();
        if ($cuenta2) {
            DB::connection($this->conn)->table('pagos')->updateOrInsert(
                ['id'=>null],
                [
                    'cuenta_id'  => $cuenta2->id,
                    'concepto'   => 'Suscripción Mensual Premium',
                    'monto'      => 999,
                    'metodo'     => 'transferencia',
                    'estatus'    => 'pendiente',
                    'fecha'      => now()->startOfMonth(),        // <--- AÑADIR
                    'fecha_pago' => null,
                    'created_at' => now()->startOfMonth(),
                    'updated_at' => now(),
                ]
            );
        }

        // (Opcional) Estado de cuenta
        foreach ([$cuenta1 ?? null, $cuenta2 ?? null] as $cx) {
            if (!$cx) continue;
            DB::connection($this->conn)->table('estados_cuenta')->updateOrInsert(
                ['cuenta_id' => $cx->id, 'periodo' => $now->startOfMonth()->toDateString()],
                [
                    'cargo'      => 999,
                    'abono'      => ($cx->email_principal === 'demo1@cliente.test') ? 999 : 0,
                    'saldo'      => ($cx->email_principal === 'demo1@cliente.test') ? 0   : 999,
                    'concepto'   => 'Renta mensual',
                    'referencia' => 'MX-'.Str::upper(Str::random(10)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
