<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class P360CompatSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('=== [P360CompatSeeder] Iniciando siembra compat ===');

        // 1) Cuentas (maneja customer_no único y columnas NOT NULL)
        $this->call(P360CompatAccountsSeeder::class);

        // 2) Shadow users (dueños/owners espejo en admin)
        $this->call(P360CompatShadowUsersSeeder::class);

        $this->command->info('=== [P360CompatSeeder] Finalizado OK ===');
    }
}
