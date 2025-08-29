<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $conn = 'mysql_admin';

    public function up(): void
    {
        // 0) Desactivar FKs temporalmente
        DB::connection($this->conn)->statement('SET @OLD_FK_CHECKS := @@FOREIGN_KEY_CHECKS');
        DB::connection($this->conn)->statement('SET FOREIGN_KEY_CHECKS = 0');

        // 1) Quitar FKs que apunten a cuentas(id) (pagos, estados_cuenta)
        $fkRows = DB::connection($this->conn)->select("
            SELECT TABLE_NAME, CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME = 'cuentas'
        ");
        foreach ($fkRows as $fk) {
            DB::connection($this->conn)->statement(
                "ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`"
            );
        }

        // 2) Asegurar que columnas 'cuenta_id' existan y sean de texto "temporal" suficientemente ancho
        // (evita truncamiento durante el cambio)
        // pagos
        if (!Schema::connection($this->conn)->hasColumn('pagos', 'cuenta_id')) {
            DB::connection($this->conn)->statement("ALTER TABLE pagos ADD COLUMN cuenta_id VARCHAR(64) NULL AFTER id");
        } else {
            DB::connection($this->conn)->statement("ALTER TABLE pagos MODIFY cuenta_id VARCHAR(64) NULL");
        }
        // estados_cuenta
        if (Schema::connection($this->conn)->hasTable('estados_cuenta')) {
            if (!Schema::connection($this->conn)->hasColumn('estados_cuenta', 'cuenta_id')) {
                DB::connection($this->conn)->statement("ALTER TABLE estados_cuenta ADD COLUMN cuenta_id VARCHAR(64) NULL AFTER id");
            } else {
                DB::connection($this->conn)->statement("ALTER TABLE estados_cuenta MODIFY cuenta_id VARCHAR(64) NULL");
            }
        }

        // 3) Convertir cuentas.id a CHAR(36) (UUID)
        //    Si hoy es BIGINT o CHAR con otra longitud, esto lo normaliza.
        DB::connection($this->conn)->statement("ALTER TABLE cuentas MODIFY id CHAR(36) NOT NULL");

        // 4) Normalizar datos existentes de cuenta_id para que quepan en 36 (si algo es más largo, se recorta)
        DB::connection($this->conn)->statement("UPDATE pagos SET cuenta_id = LEFT(cuenta_id,36) WHERE CHAR_LENGTH(cuenta_id) > 36");
        if (Schema::connection($this->conn)->hasTable('estados_cuenta')) {
            DB::connection($this->conn)->statement("UPDATE estados_cuenta SET cuenta_id = LEFT(cuenta_id,36) WHERE CHAR_LENGTH(cuenta_id) > 36");
        }

        // 5) Forzar tipo final CHAR(36) NOT NULL en FK
        DB::connection($this->conn)->statement("ALTER TABLE pagos MODIFY cuenta_id CHAR(36) NOT NULL");
        if (Schema::connection($this->conn)->hasTable('estados_cuenta')) {
            DB::connection($this->conn)->statement("ALTER TABLE estados_cuenta MODIFY cuenta_id CHAR(36) NOT NULL");
        }

        // 6) Re-crear llaves foráneas limpias
        DB::connection($this->conn)->statement("
            ALTER TABLE pagos
            ADD CONSTRAINT pagos_cuenta_id_fk
            FOREIGN KEY (cuenta_id) REFERENCES cuentas(id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        ");
        if (Schema::connection($this->conn)->hasTable('estados_cuenta')) {
            DB::connection($this->conn)->statement("
                ALTER TABLE estados_cuenta
                ADD CONSTRAINT estados_cuenta_cuenta_id_fk
                FOREIGN KEY (cuenta_id) REFERENCES cuentas(id)
                ON UPDATE CASCADE ON DELETE RESTRICT
            ");
        }

        // 7) Restaurar FKs
        DB::connection($this->conn)->statement('SET FOREIGN_KEY_CHECKS = @OLD_FK_CHECKS');
    }

    public function down(): void
    {
        // No implementamos reversa (sería volver a BIGINT y quitar FKs).
    }
};
