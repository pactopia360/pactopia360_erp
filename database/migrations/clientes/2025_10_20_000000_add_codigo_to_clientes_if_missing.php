<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_clientes';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable('clientes')) {
            return; // evita crashear si la tabla aún no existe en este paso
        }

        Schema::connection($this->conn)->table('clientes', function (Blueprint $table) {
            if (!Schema::connection($this->conn)->hasColumn('clientes', 'codigo')) {
                $table->string('codigo', 40)->nullable()->after('id');
                $table->index('codigo', 'clientes_codigo_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection($this->conn)->hasTable('clientes')) {
            return;
        }
        Schema::connection($this->conn)->table('clientes', function (Blueprint $table) {
            if (Schema::connection($this->conn)->hasColumn('clientes', 'codigo')) {
                $table->dropIndex('clientes_codigo_idx');
                $table->dropColumn('codigo');
            }
        });
    }
};
