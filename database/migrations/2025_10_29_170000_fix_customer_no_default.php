<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // DEFAULT '' (idempotente)
        DB::statement("ALTER TABLE `cuentas_cliente`
            MODIFY `customer_no` VARCHAR(50) NOT NULL DEFAULT ''");

        // Backfill
        DB::statement("UPDATE `cuentas_cliente`
            SET `customer_no` = `codigo_cliente`
            WHERE (`customer_no` IS NULL OR `customer_no` = '')
              AND (`codigo_cliente` IS NOT NULL AND `codigo_cliente` <> '')");
    }

    public function down(): void
    {
        // Si necesitas revertir, lo más seguro es permitir NULL sin default.
        DB::statement("ALTER TABLE `cuentas_cliente`
            MODIFY `customer_no` VARCHAR(50) NULL");
    }
};
