<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ✅ Esto es de ADMIN. Evita que corra en clientes.
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        $conn = $this->connection ?: config('database.default');

        // ✅ Si no existe la tabla, no rompas migraciones
        if (!Schema::connection($conn)->hasTable('cuentas_cliente')) {
            return;
        }

        // ✅ DEFAULT '' (idempotente)
        DB::connection($conn)->statement("
            ALTER TABLE `cuentas_cliente`
            MODIFY `customer_no` VARCHAR(50) NOT NULL DEFAULT ''
        ");

        // ✅ Backfill
        DB::connection($conn)->statement("
            UPDATE `cuentas_cliente`
            SET `customer_no` = `codigo_cliente`
            WHERE (`customer_no` IS NULL OR `customer_no` = '')
              AND (`codigo_cliente` IS NOT NULL AND `codigo_cliente` <> '')
        ");
    }

    public function down(): void
    {
        $conn = $this->connection ?: config('database.default');

        if (!Schema::connection($conn)->hasTable('cuentas_cliente')) {
            return;
        }

        DB::connection($conn)->statement("
            ALTER TABLE `cuentas_cliente`
            MODIFY `customer_no` VARCHAR(50) NULL
        ");
    }
};
