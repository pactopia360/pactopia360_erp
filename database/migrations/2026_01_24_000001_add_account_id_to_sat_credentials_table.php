<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_clientes';
    private string $table = 'sat_credentials';

    public function up(): void
    {
        // 1) Agrega columna si falta
        if (!Schema::connection($this->conn)->hasColumn($this->table, 'account_id')) {
            Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
                $table->char('account_id', 36)->nullable()->after('id');
            });
        }

        // 2) Backfill: account_id = cuenta_id donde esté null
        DB::connection($this->conn)->statement("
            UPDATE {$this->table}
               SET account_id = cuenta_id
             WHERE account_id IS NULL
        ");

        // 3) Índice (evita error si ya existe)
        $indexes = DB::connection($this->conn)->select("SHOW INDEX FROM {$this->table}");
        $indexNames = array_map(fn($r) => $r->Key_name, $indexes);

        if (!in_array('sat_credentials_account_id_idx', $indexNames, true)) {
            Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
                $table->index('account_id', 'sat_credentials_account_id_idx');
            });
        }

        /**
         * 4) Opcional (solo si estás 100% seguro que SIEMPRE habrá account_id):
         *    convertir a NOT NULL. Lo dejo comentado para no romper producción.
         *
         * Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
         *     $table->char('account_id', 36)->nullable(false)->change();
         * });
         */
    }

    public function down(): void
    {
        // Quita índice/columna solo si existe (rollback seguro)
        if (Schema::connection($this->conn)->hasColumn($this->table, 'account_id')) {

            $indexes = DB::connection($this->conn)->select("SHOW INDEX FROM {$this->table}");
            $indexNames = array_map(fn($r) => $r->Key_name, $indexes);

            if (in_array('sat_credentials_account_id_idx', $indexNames, true)) {
                Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
                    $table->dropIndex('sat_credentials_account_id_idx');
                });
            }

            Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
                $table->dropColumn('account_id');
            });
        }
    }
};
