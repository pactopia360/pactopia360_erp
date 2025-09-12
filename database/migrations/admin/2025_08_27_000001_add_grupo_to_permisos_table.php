<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ¡No tipar! La clase padre ya define el tipo.
     */
    protected $connection = 'mysql_admin';

    private string $table = 'permisos';
    private string $uniqueIndex = 'permisos_clave_unique';

    public function up(): void
    {
        // Si no existe la tabla, no hacemos nada aquí (la crea su migración correspondiente)
        if (!Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        // 1) Agregar columna 'grupo' si no existe
        if (!Schema::connection($this->connection)->hasColumn($this->table, 'grupo')) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                // Ajusta posición si quieres: ->after('clave')
                $table->string('grupo', 191)->nullable();
            });
        }

        // 2) Asegurar índice único en 'clave' SOLO si no existe
        $db = DB::connection($this->connection);

        if (!$this->indexExists($db, $this->table, $this->uniqueIndex)) {
            // Asegura que exista la columna
            if (!Schema::connection($this->connection)->hasColumn($this->table, 'clave')) {
                Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                    $table->string('clave', 191);
                });
            }

            // Crea el índice único
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->unique('clave', $this->uniqueIndex);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        // Quitar índice único si existe
        $db = DB::connection($this->connection);
        if ($this->indexExists($db, $this->table, $this->uniqueIndex)) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->dropUnique($this->uniqueIndex);
            });
        }

        // Quitar columna 'grupo' si existe
        if (Schema::connection($this->connection)->hasColumn($this->table, 'grupo')) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->dropColumn('grupo');
            });
        }
    }

    /**
     * Verifica si un índice existe en MySQL sin doctrine/dbal.
     */
    private function indexExists(\Illuminate\Database\Connection $db, string $table, string $indexName): bool
    {
        if ($db->getDriverName() !== 'mysql') return false;
        $res = $db->select("SHOW INDEX FROM `{$table}` WHERE `Key_name` = ?", [$indexName]);
        return !empty($res);
    }
};
