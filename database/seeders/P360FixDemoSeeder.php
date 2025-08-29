<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class P360FixDemoSeeder extends Seeder
{
    /** Conexiones configuradas en config/database.php */
    private string $adminConn = 'mysql_admin';
    private string $clientesConn = 'mysql_clientes';

    public function run(): void
    {
        // Confirma que las conexiones están disponibles
        DB::connection($this->adminConn)->getPdo();
        DB::connection($this->clientesConn)->getPdo();

        $this->seedModulosAdmin();
        $this->seedCuentasYOwnerAdmin();
        $this->seedOutboxAdmin();
        $this->seedUsuariosClientes();
    }

    private function seedModulosAdmin(): void
    {
        if (!Schema::connection($this->adminConn)->hasTable('modulos')) {
            return;
        }

        $mods = [
            ['slug' => 'cfdi',   'tier' => 'FREE', 'activo' => 1],
            ['slug' => 'rrhh',   'tier' => 'PRO',  'activo' => 1],
            ['slug' => 'nomina', 'tier' => 'PRO',  'activo' => 1],
        ];

        foreach ($mods as $m) {
            DB::connection($this->adminConn)->table('modulos')->updateOrInsert(
                ['slug' => $m['slug']],
                [
                    'tier'       => $m['tier'],
                    'activo'     => $m['activo'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedCuentasYOwnerAdmin(): void
    {
        if (!Schema::connection($this->adminConn)->hasTable('cuentas')) {
            return;
        }

        $cuentas = [
            [
                'rfc_padre'      => 'XAXX010101000',
                'nombre_cuenta'  => 'Pactopia Demo 1',
                'email_owner'    => 'owner1@demo.com',
                'plan'           => 'FREE',
                'periodicidad'   => null,
                'hits_incluidos' => 20,
                'espacio_gb'     => 1,
            ],
            [
                'rfc_padre'      => 'PAGM800101ABC',
                'nombre_cuenta'  => 'Acme Manufacturing',
                'email_owner'    => 'owner2@demo.com',
                'plan'           => 'PRO',
                'periodicidad'   => 'MENSUAL',
                'hits_incluidos' => 500,
                'espacio_gb'     => 50,
            ],
            [
                'rfc_padre'      => 'QWER9202029Z1',
                'nombre_cuenta'  => 'Servicios Nova',
                'email_owner'    => 'owner3@demo.com',
                'plan'           => 'FREE',
                'periodicidad'   => null,
                'hits_incluidos' => 20,
                'espacio_gb'     => 1,
            ],
        ];

        $colsCuentas = $this->cols($this->adminConn, 'cuentas');

        foreach ($cuentas as $c) {
            $id = (string) Str::ulid();
            $codigo = strtoupper(substr($c['rfc_padre'], 0, 3)) . '-' . substr($id, -6);

            $data = [
                'nombre_cuenta'     => $c['nombre_cuenta'],
                'email_owner'       => $c['email_owner'],
                'codigo_cliente'    => $codigo,
                'plan'              => $c['plan'],
                'periodicidad'      => $c['periodicidad'],
                'email_verified_at' => now(),
                'estatus'           => 'activa',
                'hits_incluidos'    => $c['hits_incluidos'],
                'espacio_gb'        => $c['espacio_gb'],
                'hits_consumidos'   => 0,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
            if ($colsCuentas->has('proximo_corte')) {
                $data['proximo_corte'] = $c['periodicidad'] === 'MENSUAL'
                    ? Carbon::now()->startOfMonth()->addMonth()->toDateString()
                    : null;
            }
            if ($colsCuentas->has('id')) {
                $data['id'] = $id; // solo si la PK es compatible
            }

            DB::connection($this->adminConn)->table('cuentas')->updateOrInsert(
                ['rfc_padre' => $c['rfc_padre']],
                $data
            );

            // Owner sombra (si existe)
            if (Schema::connection($this->adminConn)->hasTable('usuarios_cliente_shadow')) {
                $shadowCols = $this->cols($this->adminConn, 'usuarios_cliente_shadow');

                $ownerId = (string) Str::ulid();
                $shadow = [
                    'cuenta_id'      => $colsCuentas->has('id')
                        ? $id
                        : DB::connection($this->adminConn)->table('cuentas')->where('rfc_padre', $c['rfc_padre'])->value('id'),
                    'es_owner'       => true,
                    'nombre'         => 'Owner ' . (explode(' ', $c['nombre_cuenta'])[0] ?? 'Demo'),
                    'email'          => $c['email_owner'],
                    'password_hash'  => Hash::make('P360_demo_123'),
                    'codigo_cliente' => $codigo,
                    'activo'         => true,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
                if ($shadowCols->has('id')) {
                    $shadow['id'] = $ownerId;
                }

                DB::connection($this->adminConn)->table('usuarios_cliente_shadow')->updateOrInsert(
                    ['email' => $c['email_owner']],
                    $shadow
                );
            }
        }
    }

    private function seedOutboxAdmin(): void
    {
        if (!Schema::connection($this->adminConn)->hasTable('sync_outbox')) {
            return;
        }

        $cuentas = DB::connection($this->adminConn)->table('cuentas')
            ->select('id', 'rfc_padre', 'nombre_cuenta', 'email_owner', 'codigo_cliente', 'plan', 'periodicidad', 'hits_incluidos', 'espacio_gb')
            ->get();

        foreach ($cuentas as $cuenta) {
            $outId = (string) Str::ulid();
            $payload = [
                'cuenta_config' => [
                    'cuenta_id'      => $cuenta->id,
                    'codigo_cliente' => $cuenta->codigo_cliente,
                    'plan'           => $cuenta->plan,
                    'periodicidad'   => $cuenta->periodicidad,
                    'estatus'        => 'activa',
                    'hits_incluidos' => $cuenta->hits_incluidos,
                    'hits_consumidos'=> 0,
                    'espacio_gb'     => $cuenta->espacio_gb,
                    'proximo_corte'  => $cuenta->periodicidad === 'MENSUAL'
                        ? Carbon::now()->startOfMonth()->addMonth()->toDateString()
                        : null,
                ],
                'owner' => [
                    'id'             => (string) Str::ulid(),
                    'cuenta_id'      => $cuenta->id,
                    'es_owner'       => true,
                    'nombre'         => 'Owner ' . (explode(' ', $cuenta->nombre_cuenta)[0] ?? 'Demo'),
                    'email'          => $cuenta->email_owner,
                    'password_plain' => 'P360_demo_123',
                    'codigo_cliente' => $cuenta->codigo_cliente,
                    'activo'         => true,
                ],
            ];

            DB::connection($this->adminConn)->table('sync_outbox')->updateOrInsert(
                ['id' => $outId],
                [
                    'id'         => $outId,
                    'entidad'    => 'cuenta',
                    'entidad_id' => $cuenta->id,
                    'operacion'  => 'upsert',
                    'payload'    => json_encode($payload),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedUsuariosClientes(): void
    {
        if (!Schema::connection($this->clientesConn)->hasTable('users')) {
            return;
        }

        DB::connection($this->clientesConn)->table('users')->updateOrInsert(
            ['email' => 'owner.demo@cliente.local'],
            [
                'name'       => 'Owner Demo',
                'password'   => Hash::make('Cliente!2025'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::connection($this->clientesConn)->table('users')->updateOrInsert(
            ['email' => 'user.demo@cliente.local'],
            [
                'name'       => 'Usuario Hijo Demo',
                'password'   => Hash::make('ClienteHijo!2025'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function cols(string $conn, string $table): \Illuminate\Support\Collection
    {
        try {
            $cols = DB::connection($conn)->getSchemaBuilder()->getColumnListing($table);
            return collect($cols)->mapWithKeys(fn($c) => [$c => true]);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
