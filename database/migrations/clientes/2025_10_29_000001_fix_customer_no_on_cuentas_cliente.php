<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nota: usamos SQL directo para máxima compatibilidad en Hostinger sin dbal
        try {
            // Asegurar columna existe
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'customer_no')) {
                DB::connection('mysql_clientes')->statement("
                    ALTER TABLE `cuentas_cliente`
                    ADD COLUMN `customer_no` BIGINT UNSIGNED NOT NULL
                ");
            }

            // Hacerla UNIQUE si no existe índice aún
            $indexes = collect(DB::connection('mysql_clientes')->select("SHOW INDEX FROM `cuentas_cliente`"))
                ->pluck('Key_name')->all();
            if (!in_array('customer_no_unique', $indexes, true)) {
                // Si ya hay índice con otro nombre lo ignoramos
                DB::connection('mysql_clientes')->statement("
                    ALTER TABLE `cuentas_cliente`
                    ADD UNIQUE KEY `customer_no_unique` (`customer_no`)
                ");
            }
        } catch (\Throwable $e) {
            // log soft
            \Log::warning('Migration customer_no warning', ['e' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        try {
            DB::connection('mysql_clientes')->statement("
                ALTER TABLE `cuentas_cliente` DROP INDEX `customer_no_unique`
            ");
        } catch (\Throwable $e) {}
        // No removemos la columna en down por seguridad de datos
    }
};
