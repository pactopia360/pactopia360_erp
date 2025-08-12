<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin\AdminUser;

class AdminSuperSeeder extends Seeder
{
    public function run(): void
    {
        AdminUser::updateOrCreate(
            ['email' => 'marco.padilla@pactopia.com'],
            [
                'nombre' => 'Marco Padilla',
                'rfc'    => 'XAXX010101000',
                'password' => Hash::make('cámbiame123'),
                'rol' => 'superadmin',
                'activo' => true,
            ]
        );
    }
}

