<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('mysql_clientes')->hasTable('usuarios_cuenta')) {
            return;
        }

        // Si ya es char/varchar, no hacemos nada
        try {
            $col = DB::connection('mysql_clientes')->selectOne("
                SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'usuarios_cuenta'
                  AND COLUMN_NAME = 'cuenta_id'
                LIMIT 1
            ");

            if ($col && in_array(strtolower((string)$col->DATA_TYPE), ['char','varchar'], true)) {
                // si ya es string, ok
                return;
            }
        } catch (\Throwable $e) {
            // si falla el introspect, seguimos al alter con try/catch
        }

        // 1) Intentar dropear FK si existiera (nombre desconocido)
        try {
            $fks = DB::connection('mysql_clientes')->select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'usuarios_cuenta'
                  AND COLUMN_NAME = 'cuenta_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            foreach ($fks as $fk) {
                $name = $fk->CONSTRAINT_NAME ?? null;
                if ($name) {
                    DB::connection('mysql_clientes')->statement("ALTER TABLE `usuarios_cuenta` DROP FOREIGN KEY `$name`");
                }
            }
        } catch (\Throwable $e) {
            // si no hay FK o no se pudo dropear, continuamos
        }

        // 2) Cambiar tipo a CHAR(36)
        DB::connection('mysql_clientes')->statement("
            ALTER TABLE `usuarios_cuenta`
            MODIFY `cuenta_id` CHAR(36) NOT NULL
        ");

        // 3) Asegurar índice
        try {
            DB::connection('mysql_clientes')->statement("CREATE INDEX `idx_usuarios_cuenta_cuenta_id` ON `usuarios_cuenta` (`cuenta_id`)");
        } catch (\Throwable $e) {
            // ya existe
        }

        // 4) (Opcional) Re-crear FK a cuentas_cliente.id si quieres integridad
        // OJO: solo si cuentas_cliente existe y su id es CHAR(36)
        try {
            if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
                DB::connection('mysql_clientes')->statement("
                    ALTER TABLE `usuarios_cuenta`
                    ADD CONSTRAINT `fk_usuarios_cuenta_cuentas_cliente`
                    FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_cliente`(`id`)
                    ON DELETE CASCADE
                ");
            }
        } catch (\Throwable $e) {
            // si ya existe o no se pudo por datos previos, lo dejamos sin FK (no bloquea tu registro)
        }
    }

    public function down(): void
    {
        // No revertimos a integer para no romper registros
    }
};
