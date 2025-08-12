<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Planes base
        $this->call(PlanSeeder::class);

        // 2) Superadmin
        if (Schema::connection('mysql')->hasTable('usuario_administrativos')) {
            DB::connection('mysql')->table('usuario_administrativos')->updateOrInsert(
                ['email' => 'admin@pactopia360.local'],
                [
                    'nombre'     => 'Super Admin',
                    'password'   => Hash::make('Admin!2025'),
                    'activo'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Perfil superadmin
            $perfilId = null;
            if (Schema::connection('mysql')->hasTable('perfiles')) {
                $keyCol = Schema::connection('mysql')->hasColumn('perfiles', 'clave') ? 'clave' : 'nombre';
                $keyVal = $keyCol === 'clave' ? 'superadmin' : 'Super Administrador';

                DB::connection('mysql')->table('perfiles')->updateOrInsert(
                    [$keyCol => $keyVal],
                    [
                        'nombre'       => 'Super Administrador',
                        'descripcion'  => 'Acceso total al panel',
                        'activo'       => 1,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]
                );

                $perfil = DB::connection('mysql')->table('perfiles')->where($keyCol, $keyVal)->first();
                $perfilId = $perfil?->id ?? null;
            }

            // Vincular usuario ↔ perfil
            if ($perfilId && Schema::connection('mysql')->hasTable('usuario_perfil')) {
                $admin = DB::connection('mysql')->table('usuario_administrativos')->where('email', 'admin@pactopia360.local')->first();
                if ($admin) {
                    DB::connection('mysql')->table('usuario_perfil')->updateOrInsert(
                        ['usuario_id' => $admin->id, 'perfil_id' => $perfilId],
                        [
                            'asignado_por' => 'seeder',
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]
                    );
                }
            }

            // Dar permisos al perfil
            if ($perfilId && Schema::connection('mysql')->hasTable('perfil_permiso') && Schema::connection('mysql')->hasTable('permisos')) {
                $permIds = DB::connection('mysql')->table('permisos')->pluck('id');
                foreach ($permIds as $pid) {
                    DB::connection('mysql')->table('perfil_permiso')->updateOrInsert(
                        ['perfil_id' => $perfilId, 'permiso_id' => $pid],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        // 3) Datos demo para clientes
        if (Schema::connection('mysql')->hasTable('clientes')) {
            if (DB::connection('mysql')->table('clientes')->count() === 0) {
                $planIds = Schema::connection('mysql')->hasTable('planes')
                    ? DB::connection('mysql')->table('planes')->pluck('id', 'clave')
                    : collect([]);

                $now = now();
                DB::connection('mysql')->table('clientes')->insert([
                    [
                        'razon_social'     => 'Acme S.A. de C.V.',
                        'nombre_comercial' => 'ACME',
                        'rfc'              => 'ACM010101ABC',
                        'plan_id'          => $planIds['pro'] ?? null,
                        'plan'             => isset($planIds['pro']) ? null : 'pro',
                        'activo'           => 1,
                        'created_at'       => $now->copy()->subMonths(5),
                        'updated_at'       => $now->copy()->subDays(2),
                    ],
                    [
                        'razon_social'     => 'Globex S.A.P.I.',
                        'nombre_comercial' => 'Globex',
                        'rfc'              => 'GLO020202XYZ',
                        'plan_id'          => $planIds['premium'] ?? null,
                        'plan'             => isset($planIds['premium']) ? null : 'premium',
                        'activo'           => 1,
                        'created_at'       => $now->copy()->subMonths(2),
                        'updated_at'       => $now->copy()->subDay(),
                    ],
                ]);
            }
        }

        // 4) Pagos
        if (Schema::connection('mysql')->hasTable('pagos') && Schema::connection('mysql')->hasTable('clientes')) {
            $clienteIds = DB::connection('mysql')->table('clientes')->pluck('id');
            if ($clienteIds->count()) {
                $start = now()->startOfMonth()->subMonths(11);
                foreach ($clienteIds as $cid) {
                    for ($i = 0; $i < 12; $i++) {
                        $date = $start->copy()->addMonths($i)->addDays(rand(0, 25));
                        DB::connection('mysql')->table('pagos')->insert([
                            'cliente_id'  => $cid,
                            'monto'       => rand(300, 2000),
                            'fecha'       => $date,
                            'estado'      => 'pagado',
                            'metodo_pago' => 'transferencia',
                            'referencia'  => Str::upper(Str::random(8)),
                            'created_at'  => $date,
                            'updated_at'  => $date,
                        ]);
                    }
                }
            }
        }

        // 5) CFDIs
        if (Schema::connection('mysql')->hasTable('cfdis') && Schema::connection('mysql')->hasTable('clientes')) {
            $clienteIds = DB::connection('mysql')->table('clientes')->pluck('id');
            if ($clienteIds->count()) {
                $start = now()->startOfMonth()->subMonths(11);
                foreach ($clienteIds as $cid) {
                    for ($i = 0; $i < 12; $i++) {
                        $date = $start->copy()->addMonths($i)->addDays(rand(0, 25));
                        for ($j = 0; $j < rand(1, 3); $j++) {
                            DB::connection('mysql')->table('cfdis')->insert([
                                'cliente_id' => $cid,
                                'serie'      => 'A',
                                'folio'      => (string) rand(1000, 9999),
                                'total'      => rand(500, 4000),
                                'fecha'      => $date,
                                'estatus'    => 'emitido',
                                'uuid'       => (string) Str::uuid(),
                                'created_at' => $date,
                                'updated_at' => $date,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
