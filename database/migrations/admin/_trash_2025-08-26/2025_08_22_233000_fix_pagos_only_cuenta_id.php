<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected string $conn = 'mysql_admin';
    public function up(): void
    {
        Schema::connection($this->conn)->table('pagos', function (Blueprint $t) {
            // Asegura cuenta_id (char(36) para UUID) y FK
            if (!Schema::connection($this->conn)->hasColumn('pagos', 'cuenta_id')) {
                $t->char('cuenta_id', 36)->after('id')->index();
            } else {
                // Por si la columna quedó con otra longitud/tipo
                DB::connection($this->conn)->statement("ALTER TABLE pagos MODIFY cuenta_id CHAR(36) NULL");
                DB::connection($this->conn)->statement("UPDATE pagos SET cuenta_id = NULL WHERE cuenta_id = ''");
                DB::connection($this->conn)->statement("ALTER TABLE pagos MODIFY cuenta_id CHAR(36) NOT NULL");
            }

            // Intenta quitar FK de cliente_id si existe
            try { $t->dropForeign(['cliente_id']); } catch (\Throwable $e) {}
        });

        // Elimina la columna cliente_id si existe
        if (Schema::connection($this->conn)->hasColumn('pagos','cliente_id')) {
            Schema::connection($this->conn)->table('pagos', function (Blueprint $t) {
                $t->dropColumn('cliente_id');
            });
        }

        // (Re)crear FK de cuenta_id → cuentas.id
        // Nombre seguro para la FK
        try {
            DB::connection($this->conn)->statement(
                "ALTER TABLE pagos ADD CONSTRAINT pagos_cuenta_id_fk FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE"
            );
        } catch (\Throwable $e) {
            // si ya existe, no pasa nada
        }
    }

    public function down(): void
    {
        // Volver a agregar cliente_id nullable (rollback simple)
        Schema::connection($this->conn)->table('pagos', function (Blueprint $t) {
            try { $t->dropForeign('pagos_cuenta_id_fk'); } catch (\Throwable $e) {}
            if (!Schema::connection($this->conn)->hasColumn('pagos','cliente_id')) {
                $t->unsignedBigInteger('cliente_id')->nullable()->after('id')->index();
            }
        });
    }
};
