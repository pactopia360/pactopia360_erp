<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminStaffSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('usuario_administrativos')) {
            $this->command?->warn('[AdminStaffSeeder] Tabla usuario_administrativos no existe. Me salto.');
            return;
        }

        $items = [
            ['nombre' => 'Soporte Demo', 'email' => 'soporte@pactopia360.local', 'password' => 'Soporte!2025'],
            ['nombre' => 'Ventas Demo',  'email' => 'ventas@pactopia360.local',  'password' => 'Ventas!2025'],
        ];

        foreach ($items as $u) {
            DB::table('usuario_administrativos')->updateOrInsert(
                ['email' => $u['email']],
                [
                    'nombre'     => $u['nombre'],
                    'password'   => Hash::make($u['password']),
                    'activo'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command?->info('[AdminStaffSeeder] Usuarios soporte/ventas listos.');
    }
}
