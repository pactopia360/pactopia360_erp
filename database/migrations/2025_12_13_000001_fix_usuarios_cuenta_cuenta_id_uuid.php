<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('mysql_clientes')->hasTable('usuarios_cuenta')) return;

        // Verifica tipo actual de cuenta_id
        $col = DB::connection('mysql_clientes')->selectOne("
            SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'usuarios_cuenta'
              AND COLUMN_NAME = 'cuenta_id'
            LIMIT 1
        ");

        if (!$col) return;

        $isBigint = str_contains(strtolower((string)$col->COLUMN_TYPE), 'bigint');

        if ($isBigint) {
            Schema::connection('mysql_clientes')->table('usuarios_cuenta', function (Blueprint $table) {
                $table->uuid('cuenta_id')->nullable()->change();
            });
        }

        // índice si no existe (opcional)
        try {
            Schema::connection('mysql_clientes')->table('usuarios_cuenta', function (Blueprint $table) {
                $table->index('cuenta_id', 'idx_usuarios_cuenta_cuenta_id');
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // En producción NO conviene regresar a bigint; lo dejamos sin rollback destructivo.
    }
};
