<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class P360BootstrapSeeder extends Seeder
{
    /**
     * Ejecuta la siembra en ambas conexiones (admin y clientes) de forma idempotente.
     */
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedClientes();
    }

    /* =========================================================================
       ADMIN  (mysql_admin)  -> p360v1_admin
       ========================================================================= */
    private function seedAdmin(): void
    {
        $conn = 'mysql_admin';

        // Evita errores si aún no están todas las tablas
        if (!Schema::connection($conn)->hasTable('plans')) {
            $this->command?->warn('[admin] Tabla plans no existe aún. Omite seed de planes.');
        } else {
            // Planes: Free y Premium
            $now = Carbon::now()->toDateTimeString();
            $plans = [
                [
                    'code'           => 'free',
                    'name'           => 'Free',
                    'description'    => 'Plan gratuito de introducción',
                    'price_month'    => 0.00,
                    'price_year'     => 0.00,
                    'is_active'      => 1,
                    'features_json'  => json_encode(['hits_included' => 0, 'storage_gb' => 1, 'support' => 'none']),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ],
                [
                    'code'           => 'premium',
                    'name'           => 'Premium',
                    'description'    => 'Plan completo con todos los módulos',
                    'price_month'    => 999.00,
                    // Precio anual base = 999 * 12 (lojística de descuentos se manejará en promociones)
                    'price_year'     => 999.00 * 12,
                    'is_active'      => 1,
                    'features_json'  => json_encode(['hits_included' => 0, 'storage_gb' => 50, 'support' => 'standard']),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ],
            ];

            foreach ($plans as $p) {
                DB::connection($conn)->table('plans')->updateOrInsert(
                    ['code' => $p['code']],
                    $p
                );
            }
            $this->command?->info('[admin] Plans seeded (free/premium).');
        }

        if (!Schema::connection($conn)->hasTable('modules')) {
            $this->command?->warn('[admin] Tabla modules no existe aún. Omite seed de módulos.');
        } else {
            // Módulos ejemplo (marca qué entra en free vs premium)
            $now = Carbon::now()->toDateTimeString();
            $modules = [
                ['code' => 'core',     'name' => 'Core',             'tier' => 'free', 'created_at'=>$now,'updated_at'=>$now],
                ['code' => 'cfdi',     'name' => 'CFDI (timbres)',   'tier' => 'premium', 'created_at'=>$now,'updated_at'=>$now],
                ['code' => 'rrhh',     'name' => 'Recursos Humanos', 'tier' => 'premium', 'created_at'=>$now,'updated_at'=>$now],
                ['code' => 'nomina',   'name' => 'Nómina',           'tier' => 'premium', 'created_at'=>$now,'updated_at'=>$now],
                ['code' => 'reportes', 'name' => 'Reportes/KPIs',    'tier' => 'free', 'created_at'=>$now,'updated_at'=>$now],
            ];
            foreach ($modules as $m) {
                DB::connection($conn)->table('modules')->updateOrInsert(
                    ['code' => $m['code']],
                    $m
                );
            }
            // Relación plan <-> módulos si existe tabla pivot
            if (Schema::connection($conn)->hasTable('plan_module')) {
                $planFreeId    = DB::connection($conn)->table('plans')->where('code', 'free')->value('id');
                $planPremiumId = DB::connection($conn)->table('plans')->where('code', 'premium')->value('id');

                $modIds = DB::connection($conn)->table('modules')->pluck('id','code');
                $attach = [];
                if ($planFreeId) {
                    foreach (['core','reportes'] as $code) {
                        if (isset($modIds[$code])) {
                            $attach[] = ['plan_id'=>$planFreeId,'module_id'=>$modIds[$code]];
                        }
                    }
                }
                if ($planPremiumId) {
                    foreach (['core','reportes','cfdi','rrhh','nomina'] as $code) {
                        if (isset($modIds[$code])) {
                            $attach[] = ['plan_id'=>$planPremiumId,'module_id'=>$modIds[$code]];
                        }
                    }
                }
                foreach ($attach as $row) {
                    DB::connection($conn)->table('plan_module')->updateOrInsert(
                        ['plan_id'=>$row['plan_id'], 'module_id'=>$row['module_id']],
                        $row
                    );
                }
            }
            $this->command?->info('[admin] Modules seeded (+ pivot si aplica).');
        }

        // Usuario administrador interno para pruebas de login admin
        if (Schema::connection($conn)->hasTable('admin_users')) {
            $now = Carbon::now()->toDateTimeString();
            $adminEmail = 'admin@pactopia.local';
            DB::connection($conn)->table('admin_users')->updateOrInsert(
                ['email' => $adminEmail],
                [
                    'name'       => 'Super Admin',
                    'email'      => $adminEmail,
                    'password'   => Hash::make('P@ct0pia#2025'), // cambiar en prod
                    'role'       => 'superadmin',
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $this->command?->info('[admin] Admin user seeded: admin@pactopia.local / P@ct0pia#2025');
        } else {
            $this->command?->warn('[admin] Tabla admin_users no existe aún. Omite seed de admin user.');
        }

        // Cliente Demo + Cuenta + Suscripción (si existen tablas)
        $hasCustomers   = Schema::connection($conn)->hasTable('customers');
        $hasAccounts    = Schema::connection($conn)->hasTable('accounts');
        $hasSubs        = Schema::connection($conn)->hasTable('subscriptions');

        if ($hasCustomers || $hasAccounts) {
            $now = Carbon::now();
            $rfcDemo = 'DEM010101ABC';
            $code = $this->makeCustomerCode($rfcDemo);

            // customers
            if ($hasCustomers) {
                DB::connection($conn)->table('customers')->updateOrInsert(
                    ['rfc' => $rfcDemo],
                    [
                        'name'          => 'Demo Company SA de CV',
                        'email'         => 'demo@cliente.test',
                        'phone'         => '555-000-0000',
                        'customer_code' => $code,
                        'status'        => 'active',
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]
                );
            }

            // accounts
            if ($hasAccounts) {
                DB::connection($conn)->table('accounts')->updateOrInsert(
                    ['slug' => 'demo-company'],
                    [
                        'name'       => 'Demo Company',
                        'slug'       => 'demo-company',
                        'plan_code'  => 'free',
                        'status'     => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            // subscriptions
            if ($hasSubs && Schema::connection($conn)->hasColumn('subscriptions','billing_cycle')) {
                $planId = DB::connection($conn)->table('plans')->where('code','free')->value('id');
                DB::connection($conn)->table('subscriptions')->updateOrInsert(
                    ['account_slug' => 'demo-company'],
                    [
                        'plan_id'        => $planId,
                        'plan_code'      => 'free',
                        'status'         => 'active',
                        'billing_cycle'  => 'monthly',
                        'next_billing_at'=> $now->copy()->firstOfMonth()->addMonth()->startOfDay(),
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]
                );
            }

            $this->command?->info('[admin] Cliente demo / cuenta / suscripción listos (si tablas existen).');
        }
    }

    /* =========================================================================
       CLIENTES (mysql_clientes)  -> p360v1_clientes (espejo mínimo)
       ========================================================================= */
    private function seedClientes(): void
    {
        $conn = 'mysql_clientes';

        $hasAccounts = Schema::connection($conn)->hasTable('accounts');
        $hasUsers    = Schema::connection($conn)->hasTable('users');
        $hasCompanies= Schema::connection($conn)->hasTable('companies');

        $now = Carbon::now();

        if ($hasAccounts) {
            DB::connection($conn)->table('accounts')->updateOrInsert(
                ['slug' => 'demo-company'],
                [
                    'name'       => 'Demo Company',
                    'slug'       => 'demo-company',
                    'plan_code'  => 'free',
                    'status'     => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if ($hasUsers) {
            DB::connection($conn)->table('users')->updateOrInsert(
                ['email' => 'owner@demo.test'],
                [
                    'name'          => 'Owner Demo',
                    'email'         => 'owner@demo.test',
                    'password'      => Hash::make('Demo#2025'),
                    'account_slug'  => 'demo-company', // si tu esquema usa account_id, ajústalo
                    'role'          => 'owner',
                    'status'        => 'active',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]
            );
        }

        if ($hasCompanies) {
            DB::connection($conn)->table('companies')->updateOrInsert(
                ['rfc' => 'DEM010101ABC'],
                [
                    'name'       => 'Demo Company SA de CV',
                    'rfc'        => 'DEM010101ABC',
                    'account_slug' => 'demo-company',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->command?->info('[clientes] Espejo demo listo (accounts/users/companies si existen).');
    }

    /**
     * Genera un código único de cliente basado en RFC + timestamp + aleatorio.
     */
    private function makeCustomerCode(string $rfc): string
    {
        $ts = Carbon::now()->format('YmdHis');
        $rand = strtoupper(Str::random(4));
        return strtoupper(preg_replace('/[^A-Z0-9]/i','', $rfc)) . '-' . $ts . '-' . $rand;
    }
}
