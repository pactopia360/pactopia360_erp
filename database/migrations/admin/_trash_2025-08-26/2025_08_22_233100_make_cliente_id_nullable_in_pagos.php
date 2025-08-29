<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected string $conn = 'mysql_admin';
    public function up(): void
    {
        if (Schema::connection($this->conn)->hasColumn('pagos','cliente_id')) {
            // quita FK si existe para poder alterar la columna
            try { Schema::connection($this->conn)->table('pagos', fn(Blueprint $t) => $t->dropForeign(['cliente_id'])); } catch (\Throwable $e) {}
            DB::connection($this->conn)->statement("ALTER TABLE pagos MODIFY cliente_id BIGINT UNSIGNED NULL");
        }
    }
    public function down(): void
    {
        if (Schema::connection($this->conn)->hasColumn('pagos','cliente_id')) {
            DB::connection($this->conn)->statement("ALTER TABLE pagos MODIFY cliente_id BIGINT UNSIGNED NOT NULL");
        }
    }
};
