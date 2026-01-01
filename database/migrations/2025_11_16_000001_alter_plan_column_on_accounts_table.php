<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esta migración corre en la conexión mysql_admin
     */
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        // ✅ Si la tabla no existe en admin, NO reventamos migraciones de clientes.
        if (!Schema::connection($this->connection)->hasTable('accounts')) {
            return;
        }

        // ✅ Si la columna no existe, no hacemos change().
        if (!Schema::connection($this->connection)->hasColumn('accounts', 'plan')) {
            return;
        }

        Schema::connection($this->connection)->table('accounts', function (Blueprint $table) {
            // Cambiamos plan a VARCHAR(50) para poder guardar: free, pro, etc.
            $table->string('plan', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable('accounts')) {
            return;
        }
        if (!Schema::connection($this->connection)->hasColumn('accounts', 'plan')) {
            return;
        }

        Schema::connection($this->connection)->table('accounts', function (Blueprint $table) {
            // Ajusta esto al tipo original si era diferente
            $table->tinyInteger('plan')->nullable()->change();
        });
    }
};
