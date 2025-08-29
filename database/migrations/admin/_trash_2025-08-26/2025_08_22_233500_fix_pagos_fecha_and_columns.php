<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected string $conn = 'mysql_admin';

    public function up(): void
    {
        // 1) Fecha: nullable y con valor por defecto NOW() (si tu MySQL lo permite).
        //    Si tu server no acepta default CURRENT_TIMESTAMP en DATETIME,
        //    la dejamos nullable y el seeder enviará el valor.
        if (Schema::connection($this->conn)->hasTable('pagos')) {
            // Asegura columna 'fecha'
            if (!Schema::connection($this->conn)->hasColumn('pagos','fecha')) {
                Schema::connection($this->conn)->table('pagos', function (Blueprint $t) {
                    $t->dateTime('fecha')->nullable()->index()->after('monto');
                });
            } else {
                // Asegura que sea nullable
                DB::connection($this->conn)->statement("ALTER TABLE pagos MODIFY fecha DATETIME NULL");
                // Index por si no existía
                try { DB::connection($this->conn)->statement("CREATE INDEX pagos_fecha_index ON pagos (fecha)"); } catch (\Throwable $e) {}
            }

            // 2) Columna 'cliente_id' (heredada de otra variante): hacerla NULL por compat.
            if (Schema::connection($this->conn)->hasColumn('pagos','cliente_id')) {
                DB::connection($this->conn)->statement("ALTER TABLE pagos MODIFY cliente_id BIGINT UNSIGNED NULL");
            }

            // 3) 'cuenta_id' como CHAR(36) (UUID). Si hay datos viejos que no caben, los dejamos tal cual (NULL) para no truncar.
            if (Schema::connection($this->conn)->hasColumn('pagos','cuenta_id')) {
                try {
                    DB::connection($this->conn)->statement("ALTER TABLE pagos MODIFY cuenta_id CHAR(36) NULL");
                } catch (\Throwable $e) {
                    // Si hay advertencia por truncado, no forzamos; el seeder insertará bien a partir de ahora.
                }
            }
        }
    }

    public function down(): void
    {
        // No regresamos tipos para evitar pérdida de datos.
    }
};
