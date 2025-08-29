<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta en la conexión de CLIENTES.
     */
    protected string $connection = 'mysql_clientes';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        // Si no existe la tabla, no hacemos nada
        if (! $schema->hasTable('clientes')) {
            return;
        }

        // Agrega deleted_at solo si no existe
        if (! $schema->hasColumn('clientes', 'deleted_at')) {
            $schema->table('clientes', function (Blueprint $table) {
                // Soft delete estándar (timestamp nullable)
                $table->softDeletes(); // crea 'deleted_at' NULL
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('clientes')) {
            return;
        }

        if ($schema->hasColumn('clientes', 'deleted_at')) {
            $schema->table('clientes', function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        }
    }
};
