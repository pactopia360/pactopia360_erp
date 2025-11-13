<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /* ======================================================
           1) Planes base (idempotente)
           ====================================================== */
        // PlanSeeder está en el mismo namespace, no requiere "use"
        if (class_exists(PlanSeeder::class)) {
            $this->call(PlanSeeder::class);
        }

        /* ======================================================
           2) Superadmin + perfil/permiso (solo si existen tablas)
           ====================================================== */
        if (Schema::hasTable('usuario_administrativos')) {
            // Controlado por ENV:
            //  ADMIN_EMAIL, ADMIN_PASSWORD (opcional)
            $adminEmail = env('ADMIN_EMAIL', 'admin@pactopia360.local');
            $adminPass  = env('ADMIN_PASSWORD'); // si viene vacío, NO se cambia el password existente

            $admin = DB::table('usuario_administrativos')->where('email', $adminEmail)->first();

            if (!$admin) {
                DB::table('usuario_administrativos')->insert([
                    'nombre'     => 'Super Admin',
                    'email'      => $adminEmail,
                    'password'   => Hash::make($adminPass ?: 'Admin!2025'),
                    'activo'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $update = [
                    'nombre'     => $admin->nombre ?: 'Super Admin',
                    'activo'     => 1,
                    'updated_at' => now(),
                ];
                if (!empty($adminPass)) {
                    $update['password'] = Hash::make($adminPass);
                }
                DB::table('usuario_administrativos')->where('email', $adminEmail)->update($update);
            }

            // Crear/asegurar perfil "superadmin" si existe tabla perfiles
            $perfilId = null;
            if (Schema::hasTable('perfiles')) {
                $hasClave   = Schema::hasColumn('perfiles', 'clave');
                $hasNombre  = Schema::hasColumn('perfiles', 'nombre');
                $hasDesc    = Schema::hasColumn('perfiles', 'descripcion');
                $hasActivo  = Schema::hasColumn('perfiles', 'activo');

                $keyCol = $hasClave ? 'clave' : ($hasNombre ? 'nombre' : null);
                $keyVal = $hasClave ? 'superadmin' : 'Super Administrador';

                if ($keyCol) {
                    $update = [];
                    if ($hasNombre) $update['nombre'] = $hasClave ? 'Super Administrador' : $keyVal;
                    if ($hasDesc)   $update['descripcion'] = 'Acceso total al panel';
                    if ($hasActivo) $update['activo'] = 1;
                    $update['created_at'] = now();
                    $update['updated_at'] = now();

                    DB::table('perfiles')->updateOrInsert([$keyCol => $keyVal], $update);

                    $perfil   = DB::table('perfiles')->where($keyCol, $keyVal)->first();
                    $perfilId = $perfil?->id ?? null;
                }
            }

            // Vincular usuario ↔ perfil si existe tabla pivote
            if ($perfilId && Schema::hasTable('usuario_perfil')) {
                $admin = DB::table('usuario_administrativos')->where('email', $adminEmail)->first();
                if ($admin) {
                    $pivot = [
                        'usuario_id' => $admin->id,
                        'perfil_id'  => $perfilId,
                    ];
                    if (Schema::hasColumn('usuario_perfil', 'asignado_por')) $pivot['asignado_por'] = 'seeder';
                    if (Schema::hasColumn('usuario_perfil', 'created_at'))  $pivot['created_at']  = now();
                    if (Schema::hasColumn('usuario_perfil', 'updated_at'))  $pivot['updated_at']  = now();

                    DB::table('usuario_perfil')->updateOrInsert(
                        ['usuario_id' => $admin->id, 'perfil_id' => $perfilId],
                        $pivot
                    );
                }
            }

            // Dar todos los permisos al perfil si existen tablas
            if ($perfilId && Schema::hasTable('perfil_permiso') && Schema::hasTable('permisos')) {
                $permIds = DB::table('permisos')->pluck('id');
                foreach ($permIds as $pid) {
                    $up = ['perfil_id' => $perfilId, 'permiso_id' => $pid];
                    if (Schema::hasColumn('perfil_permiso', 'created_at')) $up['created_at'] = now();
                    if (Schema::hasColumn('perfil_permiso', 'updated_at')) $up['updated_at'] = now();

                    DB::table('perfil_permiso')->updateOrInsert(
                        ['perfil_id' => $perfilId, 'permiso_id' => $pid],
                        $up
                    );
                }
            }
        }

        /* ======================================================
           3) Datos demo para dashboard (solo si hay tablas)
           ====================================================== */

        // Clientes demo
        if (Schema::hasTable('clientes')) {
            if (DB::table('clientes')->count() === 0) {
                $planIds = Schema::hasTable('planes')
                    ? DB::table('planes')->pluck('id', 'clave')
                    : collect([]);

                $now = now();
                DB::table('clientes')->insert([
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

        // Pagos de 12 meses por cliente
        if (Schema::hasTable('pagos') && Schema::hasTable('clientes')) {
            $clienteIds = DB::table('clientes')->pluck('id');
            if ($clienteIds->count()) {
                $start = now()->startOfMonth()->subMonths(11);
                foreach ($clienteIds as $cid) {
                    for ($i = 0; $i < 12; $i++) {
                        $date = $start->copy()->addMonths($i)->addDays(rand(0, 25));
                        DB::table('pagos')->insert([
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

        // CFDIs demo (1–3 por mes por cliente)
        if (Schema::hasTable('cfdis') && Schema::hasTable('clientes')) {
            $clienteIds = DB::table('clientes')->pluck('id');
            if ($clienteIds->count()) {
                $start = now()->startOfMonth()->subMonths(11);
                foreach ($clienteIds as $cid) {
                    for ($i = 0; $i < 12; $i++) {
                        $date = $start->copy()->addMonths($i)->addDays(rand(0, 25));
                        for ($j = 0; $j < rand(1, 3); $j++) {
                            DB::table('cfdis')->insert([
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

        /* ======================================================
           4) Datos de CRM · Carritos (opcional si existe el seeder)
           ====================================================== */
        if (class_exists(CarritosSeeder::class)) {
            $this->call(CarritosSeeder::class);
        }

        /* ======================================================
           5) SAT · Credenciales dummy (solo si existe el seeder)
              - útil para habilitar UI SAT sin subir CSD real
           ====================================================== */
        if (class_exists(SatDummyCredentialSeeder::class)) {
            // En local/dev/testing siempre se corre; en prod solo si EXPLICITAMENTE
            $env = app()->environment();
            if (in_array($env, ['local','development','testing'], true) || env('SEED_SAT_DUMMY', false)) {
                $this->call(SatDummyCredentialSeeder::class);
            }
        }
    }
}
