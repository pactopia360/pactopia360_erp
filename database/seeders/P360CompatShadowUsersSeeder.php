<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class P360CompatShadowUsersSeeder extends Seeder
{
    private string $adminConn = 'mysql_admin';

    public function run(): void
    {
        $table = $this->detectTable(['usuarios_cliente_shadow','cliente_shadow_users','shadow_users']);
        if (!$table) {
            $this->command->warn('[P360] No encontré tabla de shadow users. Nada que hacer.');
            return;
        }

        $cols = collect(Schema::connection($this->adminConn)->getColumnListing($table))
            ->mapWithKeys(fn($c) => [strtolower($c) => $c]);

        $now = now();
        $demo = [
            ['nombre'=>'Owner Demo1','email'=>'owner1@demo.com','cuenta_id'=>'01DUMMYCUENTA1'],
            ['nombre'=>'Owner Demo2','email'=>'owner2@demo.com','cuenta_id'=>'01DUMMYCUENTA2'],
        ];

        foreach ($demo as $d) {
            $id = (string) Str::ulid();
            $codigo = strtoupper(substr($d['nombre'],0,3)).'-'.substr($id,-6);

            $payload = [];
            $this->put($payload, $cols, ['id'], $id);
            $this->put($payload, $cols, ['cuenta_id','account_id'], $d['cuenta_id']);
            $this->put($payload, $cols, ['nombre','name'], $d['nombre']);
            $this->put($payload, $cols, ['email','correo','email_owner'], $d['email']);
            $this->put($payload, $cols, ['codigo_cliente','code'], $codigo);
            $this->put($payload, $cols, ['password_hash','password'], bcrypt('P360_demo_123'));
            $this->put($payload, $cols, ['es_owner','is_owner'], true);
            $this->put($payload, $cols, ['activo','is_active'], true);
            if (isset($cols['created_at'])) $payload[$cols['created_at']] = $now;
            if (isset($cols['updated_at'])) $payload[$cols['updated_at']] = $now;

            // 🔒 Blindaje: rellenar cualquier columna NOT NULL sin default
            $payload = $this->fillMissingRequired($table, $cols, $payload);

            $unique = [$cols['email'] ?? $cols['correo'] => $d['email']];
            DB::connection($this->adminConn)->table($table)->updateOrInsert($unique, $payload);
        }

        $this->command->info("[P360] Shadow users demo sembrados en tabla '{$table}' de forma compatible.");
    }

    private function put(array &$payload, $cols, array $candidates, $value): void
    {
        foreach ($candidates as $c) {
            $lc = strtolower($c);
            if (isset($cols[$lc])) {
                $payload[$cols[$lc]] = $value;
                return;
            }
        }
    }

    private function detectTable(array $candidates): ?string
    {
        foreach ($candidates as $t) {
            if (Schema::connection($this->adminConn)->hasTable($t)) return $t;
        }
        return null;
    }

    private function fillMissingRequired(string $table, $cols, array $payload): array
    {
        $conn = DB::connection($this->adminConn);
        $db = $conn->getDatabaseName();

        $columns = $conn->select("
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ", [$db, $table]);

        foreach ($columns as $col) {
            $name = $col->COLUMN_NAME;
            if (isset($payload[$name])) continue;

            $notNull = $col->IS_NULLABLE === 'NO';
            $hasDefault = $col->COLUMN_DEFAULT !== null;

            if ($notNull && !$hasDefault) {
                $payload[$name] = $this->dummyValue($col->DATA_TYPE, $name);
            }
        }
        return $payload;
    }

    private function dummyValue(string $type, string $name)
    {
        $type = strtolower($type);
        if (str_contains($type,'int') || str_contains($type,'decimal') || str_contains($type,'float'))
            return 0;
        if (str_contains($type,'date') || str_contains($type,'time'))
            return Carbon::now()->toDateTimeString();
        if (str_contains($type,'bool') || str_contains($name,'activo'))
            return false;
        return 'N/A';
    }
}
