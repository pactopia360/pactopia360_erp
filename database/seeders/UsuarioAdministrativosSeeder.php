<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UsuarioAdministrativosSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection('mysql')->table('usuario_administrativos')->insert([
            'nombre' => 'Marco Padilla',
            'email' => 'marco.padilla@pactopia.com',
            'password' => Hash::make('Marco123!'),
            'rol' => 'superadmin',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

