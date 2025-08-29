<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSuperSeeder::class,  // superadmin
            AdminDemoSeeder::class,   // datos demo: cuentas/pagos/etc.
        ]);
    }
}
