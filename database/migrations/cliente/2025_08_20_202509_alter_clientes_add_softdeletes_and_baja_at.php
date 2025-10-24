<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Importante:
     *  - Debe ser PUBLIC y SIN tipo para ser compatible con Illuminate\Database\Migrations\Migration.
     */
    public $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn = Schema::connection($this->connection);
        $tableName = 'clientes';

        if (!$conn->hasTable($tableName)) {
            return;
        }

        $conn->table($tableName, function (Blueprint $table) use ($conn, $tableName) {
            // Agregar baja_at si no existe
            if (!$conn->hasColumn($tableName, 'baja_at')) {
                $table->timestamp('baja_at')->nullable()->after('updated_at');
            }

            // Agregar deleted_at (soft deletes) si no existe
            if (!$conn->hasColumn($tableName, 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('baja_at');
                // Si prefieres helper de soft deletes:
                // $table->softDeletes(); // pero sólo si no existe ya la columna
            }
        });
    }

    public function down(): void
    {
        $conn = Schema::connection($this->connection);
        $tableName = 'clientes';

        if (!$conn->hasTable($tableName)) {
            return;
        }

        $conn->table($tableName, function (Blueprint $table) use ($conn, $tableName) {
            if ($conn->hasColumn($tableName, 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
            if ($conn->hasColumn($tableName, 'baja_at')) {
                $table->dropColumn('baja_at');
            }
        });
    }
};
