<?php
// database/seeders/DemoAccountSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DemoAccountSeeder extends Seeder
{
    private string $admin = 'mysql_admin';
    private string $cli   = 'mysql_clientes';

    public function run(): void
    {
        // --------- Planes base (por si hace falta) ----------
        if (Schema::connection($this->admin)->hasTable('plans')) {
            DB::connection($this->admin)->table('plans')->upsert([
                [
                    'name' => 'free',
                    'price_monthly' => 0,
                    'price_annual'  => 0,
                    'included_features' => json_encode(['modules'=>['cfdi_basic'],'space_gb'=>1,'hits_included'=>0]),
                    'active' => 1,
                    'created_at'=>now(),'updated_at'=>now(),
                ],
                [
                    'name' => 'premium',
                    'price_monthly' => 999.00,
                    'price_annual'  => 999.00 * 12,
                    'included_features' => json_encode(['modules'=>['cfdi','rrhh','nomina','reportes'],'space_gb'=>50,'hits_included'=>20]),
                    'active' => 1,
                    'created_at'=>now(),'updated_at'=>now(),
                ],
            ], ['name'], ['price_monthly','price_annual','included_features','active','updated_at']);
        }

        // IDs de planes (soportando 'plans' o 'planes')
        $planIds = $this->resolvePlanIds(); // ['free'=>?, 'premium'=>?]

        // ---------- Cuentas DEMO en ADMIN ----------
        if (Schema::connection($this->admin)->hasTable('accounts')) {

            // RFCs <= 13 chars (la col es VARCHAR(13))
            $freeRFC    = 'DEMOFRE01010A';   // 13
            $premiumRFC = 'DEMOPRE01010B';   // 13

            $freeId = $this->upsertAccount([
                'rfc'            => $freeRFC,
                'razon_social'   => 'Demo Free S.A. de C.V.',
                'correo_contacto'=> 'owner+free@demo.local',
                'telefono'       => '555-000-0001',
                'plan'           => 'free',
                'billing_cycle'  => null, // Free no factura → puede ser null si tu esquema lo permite
                'next_invoice'   => null,
                'features'       => ['space_gb'=>1,'hits'=>0],
            ]);

            $premiumId = $this->upsertAccount([
                'rfc'            => $premiumRFC,
                'razon_social'   => 'Demo Premium S.A. de C.V.',
                'correo_contacto'=> 'owner+premium@demo.local',
                'telefono'       => '555-000-0002',
                'plan'           => 'premium',
                'billing_cycle'  => 'monthly',
                'next_invoice'   => Carbon::now()->startOfMonth()->addMonth()->startOfDay(),
                'features'       => ['space_gb'=>50,'hits'=>20],
            ]);

            // ---------- Suscripciones ----------
            if (Schema::connection($this->admin)->hasTable('subscriptions')) {
                // FREE (deseado: status='free', billing_cycle='monthly' para evitar null)
                $this->upsertSubscription($freeId, [
                    'plan_id'               => $planIds['free'],
                    'status'                => 'free',     // se normaliza a tu tipo real de columna
                    'billing_cycle'         => 'monthly',  // idem
                    'current_period_start'  => null,
                    'current_period_end'    => null,
                    'next_invoice_date'     => null,
                    'grace_days'            => 0,
                    'auto_renew'            => 0,
                ]);

                // PREMIUM mensual activo
                $start = Carbon::now()->startOfMonth();
                $end   = Carbon::now()->endOfMonth()->setTime(23,59,59);
                $this->upsertSubscription($premiumId, [
                    'plan_id'               => $planIds['premium'],
                    'status'                => 'active',
                    'billing_cycle'         => 'monthly',
                    'current_period_start'  => $start,
                    'current_period_end'    => $end,
                    'next_invoice_date'     => $end->copy()->addDay()->startOfDay(), // 1 del siguiente mes
                    'grace_days'            => 5,
                    'auto_renew'            => 1,
                ]);
            }
        }

        // ---------- Usuarios DEMO en CLIENTES ----------
        if (Schema::connection($this->cli)->hasTable('users')) {
            // owner free
            $this->upsertClientUser([
                'email'         => 'owner+free@demo.local',
                'account_id'    => $this->findAccountIdByRFC('DEMOFRE01010A'),
                'name'          => 'Owner Free',
                'password_hash' => Hash::make('Demo!2025'),
                'role'          => 'owner',
                'email_verified'=> 1,
            ]);

            // owner premium
            $this->upsertClientUser([
                'email'         => 'owner+premium@demo.local',
                'account_id'    => $this->findAccountIdByRFC('DEMOPRE01010B'),
                'name'          => 'Owner Premium',
                'password_hash' => Hash::make('Demo!2025'),
                'role'          => 'owner',
                'email_verified'=> 1,
            ]);
        }
    }

    // ---------------- helpers ----------------

    private function resolvePlanIds(): array
    {
        $ids = ['free'=>null,'premium'=>null];

        if (Schema::connection($this->admin)->hasTable('plans')) {
            $ids['free']    = DB::connection($this->admin)->table('plans')->where('name','free')->value('id');
            $ids['premium'] = DB::connection($this->admin)->table('plans')->where('name','premium')->value('id');
        } elseif (Schema::connection($this->admin)->hasTable('planes')) {
            $idCol = Schema::connection($this->admin)->hasColumn('planes','id_plan') ? 'id_plan' : 'id';
            $key   = Schema::connection($this->admin)->hasColumn('planes','clave') ? 'clave'
                    : (Schema::connection($this->admin)->hasColumn('planes','nombre_plan') ? 'nombre_plan' : 'nombre');
            $ids['free']    = DB::connection($this->admin)->table('planes')->where($key,'Free')->value($idCol);
            $ids['premium'] = DB::connection($this->admin)->table('planes')->where($key,'Premium')->value($idCol);
        }
        return $ids;
    }

    private function upsertAccount(array $in): ?int
    {
        $row = [
            'rfc'              => $in['rfc'],                 // <= 13 chars
            'razon_social'     => $in['razon_social'] ?? null,
            'correo_contacto'  => $in['correo_contacto'] ?? null,
            'telefono'         => $in['telefono'] ?? null,
            'plan'             => $in['plan'] ?? 'free',
            'billing_cycle'    => $in['billing_cycle'],        // null para free; valor para premium
            'next_invoice_date'=> $in['next_invoice'] ?? null,
            'is_blocked'       => 0,
            'features'         => json_encode($in['features'] ?? []),
            'updated_at'       => now(),
            'created_at'       => now(),
        ];

        $exists = DB::connection($this->admin)->table('accounts')->where('rfc', $in['rfc'])->first();
        if ($exists) {
            DB::connection($this->admin)->table('accounts')->where('id', $exists->id)->update($row);
            return (int)$exists->id;
        } else {
            return (int) DB::connection($this->admin)->table('accounts')->insertGetId($row);
        }
    }

    private function upsertSubscription(int $accountId = null, array $in = []): void
    {
        if (!$accountId) return;

        // Normaliza 'status' y 'billing_cycle' según el tipo real de columna
        [$statusValue, $billingCycleValue] = $this->normalizeSubscriptionFields(
            $in['status'] ?? 'free',
            $in['billing_cycle'] ?? 'monthly'
        );

        $row = [
            'plan_id'              => $in['plan_id'] ?? null,
            'status'               => $statusValue,
            'billing_cycle'        => $billingCycleValue,
            'current_period_start' => $in['current_period_start'],
            'current_period_end'   => $in['current_period_end'],
            'next_invoice_date'    => $in['next_invoice_date'],
            'grace_days'           => $in['grace_days'] ?? 0,
            'auto_renew'           => $in['auto_renew'] ?? 0,
            'updated_at'           => now(),
            'created_at'           => now(),
        ];

        $exists = DB::connection($this->admin)->table('subscriptions')->where('account_id', $accountId)->first();
        if ($exists) {
            DB::connection($this->admin)->table('subscriptions')->where('account_id', $accountId)->update($row);
        } else {
            $row['account_id'] = $accountId;
            DB::connection($this->admin)->table('subscriptions')->insert($row);
        }
    }

    /**
     * Ajusta 'status' y 'billing_cycle' al tipo de columna actual (ENUM/INT/VARCHAR).
     */
    private function normalizeSubscriptionFields(string $desiredStatus, string $desiredCycle): array
    {
        // STATUS
        $statusCol = DB::connection($this->admin)->selectOne("SHOW COLUMNS FROM `subscriptions` LIKE 'status'");
        $statusType = strtolower($statusCol->Type ?? '');
        $statusValue = $desiredStatus;

        if (str_contains($statusType, 'enum')) {
            preg_match_all("/'([^']+)'/", $statusType, $m);
            $enumValues = $m[1] ?? [];
            if (!in_array($desiredStatus, $enumValues, true)) {
                $statusValue = in_array('active', $enumValues, true) ? 'active' : ($enumValues[0] ?? 'active');
            }
        } elseif (str_contains($statusType, 'int')) {
            $statusValue = 1; // activo
        } // else varchar → dejamos el deseado

        // BILLING CYCLE
        $bcCol = DB::connection($this->admin)->selectOne("SHOW COLUMNS FROM `subscriptions` LIKE 'billing_cycle'");
        $bcType = strtolower($bcCol->Type ?? '');
        $billingCycleValue = $desiredCycle;

        if (str_contains($bcType, 'enum')) {
            preg_match_all("/'([^']+)'/", $bcType, $m2);
            $bcEnum = $m2[1] ?? [];
            if (!in_array($desiredCycle, $bcEnum, true)) {
                $billingCycleValue = in_array('monthly', $bcEnum, true) ? 'monthly' : ($bcEnum[0] ?? 'monthly');
            }
        } elseif (str_contains($bcType, 'int')) {
            $billingCycleValue = 1; // monthly
        } // else varchar → dejamos el deseado

        return [$statusValue, $billingCycleValue];
    }

    private function upsertClientUser(array $in): void
    {
        if (empty($in['email'])) return;

        // Detectar columnas existentes en mysql_clientes.users
        $cols = $this->userColumns();

        // Armamos el row solo con columnas reales
        $row = [];

        if ($cols['name'])        $row['name'] = $in['name'] ?? 'Owner';
        if ($cols['password'])    $row['password'] = $in['password_hash'];

        // role (si existe)
        if ($cols['role'] && !empty($in['role'])) {
            $row['role'] = $in['role'];
        }

        // account_id (si existe)
        if ($cols['account_id'] && !empty($in['account_id'])) {
            $row['account_id'] = $in['account_id'];
        }

        // verificación de email: preferimos email_verified_at si existe
        if ($cols['email_verified_at'] && !empty($in['email_verified'])) {
            $row['email_verified_at'] = now();
        } elseif ($cols['email_verified']) {
            $row['email_verified'] = (int)($in['email_verified'] ?? 0);
        }

        // timestamps
        if ($cols['updated_at']) $row['updated_at'] = now();
        if ($cols['created_at']) $row['created_at'] = now();

        // Insert/Update por email (columna requerida)
        $exists = DB::connection($this->cli)->table('users')->where('email', $in['email'])->first();

        if ($exists) {
            // no intentes actualizar columnas que no existen
            $updateRow = $row;
            unset($updateRow['created_at']); // no tocar created_at en update
            DB::connection($this->cli)->table('users')->where('id', $exists->id)->update($updateRow);
        } else {
            // en insert sí necesitas 'email'
            $row['email'] = $in['email'];
            DB::connection($this->cli)->table('users')->insert($row);
        }
    }

    private function userColumns(): array
    {
        $conn = $this->cli;
        $has  = fn($c) => Schema::connection($conn)->hasColumn('users', $c);

        return [
            'id'                => $has('id'),
            'name'              => $has('name'),
            'email'             => $has('email'),
            'password'          => $has('password'),
            'role'              => $has('role'),              // opcional
            'account_id'        => $has('account_id'),        // opcional
            'email_verified'    => $has('email_verified'),    // boolean/tinyint opcional
            'email_verified_at' => $has('email_verified_at'), // timestamp típico de Laravel
            'created_at'        => $has('created_at'),
            'updated_at'        => $has('updated_at'),
        ];
    }

    private function findAccountIdByRFC(string $rfc): ?int
    {
        $id = DB::connection($this->admin)->table('accounts')->where('rfc', $rfc)->value('id');
        return $id ? (int)$id : null;
    }
}
