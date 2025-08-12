<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Base admin
        $this->call(AdminSeeder::class);
        $this->call(UsuarioAdministrativosSeeder::class);


        // Base clientes
        $this->call(ClientesSeeder::class);
    }
}
