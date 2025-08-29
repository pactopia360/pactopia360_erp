<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_admin';

    public function up(): void
    {
        // Ajusta cuentas.id a char(36)
        Schema::connection($this->conn)->table('cuentas', function (Blueprint $t) {
            // Si tenía PK distinta, caerá en modify. Si no, quitar/poner PK según sea necesario.
            $t->char('id', 36)->change();
        });

        // Alinea referencias que apuntan a cuentas.id
        if (Schema::connection($this->conn)->hasTable('pagos')) {
            Schema::connection($this->conn)->table('pagos', function (Blueprint $t) {
                // En tu migración creaste: $table->uuid('cuenta_id');
                // Si por alguna razón quedó de otra longitud, la normalizamos:
                $t->char('cuenta_id', 36)->change();
            });
        }

        if (Schema::connection($this->conn)->hasTable('estados_cuenta')) {
            Schema::connection($this->conn)->table('estados_cuenta', function (Blueprint $t) {
                $t->char('cuenta_id', 36)->change();
            });
        }
    }

    public function down(): void
    {
        // Si necesitas revertir, déjalo como estaba (ajusta la longitud real previa si era distinta)
        Schema::connection($this->conn)->table('cuentas', function (Blueprint $t) {
            $t->char('id', 36)->change();
        });
        if (Schema::connection($this->conn)->hasTable('pagos')) {
            Schema::connection($this->conn)->table('pagos', function (Blueprint $t) {
                $t->char('cuenta_id', 36)->change();
            });
        }
        if (Schema::connection($this->conn)->hasTable('estados_cuenta')) {
            Schema::connection($this->conn)->table('estados_cuenta', function (Blueprint $t) {
                $t->char('cuenta_id', 36)->change();
            });
        }
    }
};
