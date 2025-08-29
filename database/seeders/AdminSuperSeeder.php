<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\Admin\Auth\UsuarioAdministrativo;

class AdminSuperSeeder extends Seeder
{
    public function run(): void
    {
        $attrs = [
            'nombre'   => 'Marco Padilla',
            'email'    => 'marco.padilla@pactopia.com',
            'password' => Hash::make('cámbiame123'),
        ];

        // setea flags solo si existen en la tabla
        if (Schema::hasColumn('usuario_administrativos', 'es_superadmin')) {
            $attrs['es_superadmin'] = true;
        }
        if (Schema::hasColumn('usuario_administrativos', 'activo')) {
            $attrs['activo'] = true;
        }

        UsuarioAdministrativo::updateOrCreate(
            ['email' => 'marco.padilla@pactopia.com'],
            $attrs
        );
    }
}
