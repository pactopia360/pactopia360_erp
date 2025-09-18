<?php

namespace Database\Seeders\Admin;

use Illuminate\Database\Seeder;
use App\Models\Admin\Auth\UsuarioAdministrativo;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        UsuarioAdministrativo::on('mysql_admin')->firstOrCreate(
            ['email' => 'marco.padilla@pactopia.com'],
            [
                'nombre'         => 'Super Admin',
                'password'       => 'Pacto!2024', // se hashea por el cast 'password' => 'hashed'
                'rol'            => 'superadmin',
                'activo'         => true,
                'es_superadmin'  => true,
            ]
        );
    }
}
