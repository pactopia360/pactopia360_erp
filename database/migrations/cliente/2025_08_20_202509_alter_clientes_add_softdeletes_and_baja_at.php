<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Forzamos esta conexión para cuando se ejecute esta migración por carpeta
    protected string $connection = 'mysql_clientes';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('clientes')) {
            Schema::connection($this->connection)->table('clientes', function (Blueprint $table) {
                if (!Schema::connection($this->connection)->hasColumn('clientes', 'baja_at')) {
                    $table->timestamp('baja_at')->nullable()->after('updated_at');
                }
                if (!Schema::connection($this->connection)->hasColumn('clientes', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->after('baja_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('clientes')) {
            Schema::connection($this->connection)->table('clientes', function (Blueprint $table) {
                if (Schema::connection($this->connection)->hasColumn('clientes', 'deleted_at')) {
                    $table->dropColumn('deleted_at');
                }
                if (Schema::connection($this->connection)->hasColumn('clientes', 'baja_at')) {
                    $table->dropColumn('baja_at');
                }
            });
        }
    }
};
