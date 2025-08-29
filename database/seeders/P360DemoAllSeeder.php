<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class P360DemoAllSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('=== [P360DemoAllSeeder] Inicio ===');

        // 0) Planes base, si lo tienes (ya existe en tu árbol)
        if (class_exists(\Database\Seeders\PlanSeeder::class)) {
            $this->call(\Database\Seeders\PlanSeeder::class);
        }

        // 1) Compat (cuentas + shadow users, blindados)
        $this->call(P360CompatSeeder::class);

        // 2) Dashboard demo (si existe en tu repo)
        if (class_exists(\Database\Seeders\DashboardDemoSeeder::class)) {
            $this->call(\Database\Seeders\DashboardDemoSeeder::class);
        }

        // 3) Otros seeders de tu proyecto si existen (no rompemos nada)
        $optional = [
            \Database\Seeders\AdminDemoSeeder::class,
            \Database\Seeders\AdminTestUserSeeder::class,
            \Database\Seeders\P360BootstrapSeeder::class,
            \Database\Seeders\ClientesSeeder::class,
            \Database\Seeders\P360DemoSeeder::class,
            \Database\Seeders\P360FixDemoSeeder::class,
            \Database\Seeders\DemoAccountSeeder::class,
        ];

        foreach ($optional as $seederClass) {
            if (class_exists($seederClass)) {
                $this->call($seederClass);
            }
        }

        $this->command->info('=== [P360DemoAllSeeder] Listo ===');
    }
}
