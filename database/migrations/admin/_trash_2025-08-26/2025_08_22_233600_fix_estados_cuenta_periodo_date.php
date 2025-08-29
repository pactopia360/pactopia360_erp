<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_admin';

    public function up(): void
    {
        if (Schema::connection($this->conn)->hasTable('estados_cuenta')) {
            // Si la columna no es tipo DATE, la convertimos de forma segura.
            // Creamos columna temporal, copiamos y luego renombramos.
            try {
                DB::connection($this->conn)->statement("ALTER TABLE estados_cuenta ADD COLUMN periodo_tmp DATE NULL AFTER cuenta_id");
            } catch (\Throwable $e) {
                // si ya existe, seguimos
            }

            // Intento de conversión: si 'periodo' trae 'YYYY-MM' o 'YYYY-MM-01' o es DATE ya, lo normalizamos.
            try {
                // Cuando sea DATE ya, copia directa
                DB::connection($this->conn)->statement("UPDATE estados_cuenta SET periodo_tmp = periodo WHERE periodo REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
            } catch (\Throwable $e) {}

            try {
                // Cuando venga como 'YYYY-MM', asumimos día 01
                DB::connection($this->conn)->statement("UPDATE estados_cuenta SET periodo_tmp = STR_TO_DATE(CONCAT(periodo,'-01'), '%Y-%m-%d') WHERE periodo REGEXP '^[0-9]{4}-[0-9]{2}$'");
            } catch (\Throwable $e) {}

            try {
                // Si ya es convertible como fecha
                DB::connection($this->conn)->statement("UPDATE estados_cuenta SET periodo_tmp = STR_TO_DATE(periodo, '%Y-%m-%d') WHERE periodo_tmp IS NULL");
            } catch (\Throwable $e) {}

            // Si todo OK, reemplazamos columna
            try {
                DB::connection($this->conn)->statement("ALTER TABLE estados_cuenta DROP COLUMN periodo");
            } catch (\Throwable $e) {}

            try {
                DB::connection($this->conn)->statement("ALTER TABLE estados_cuenta CHANGE COLUMN periodo_tmp periodo DATE NULL");
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        // No revertimos el tipo.
    }
};
