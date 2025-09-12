<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ¡No tipar! La clase padre ya define el tipo en Laravel 12.
     */
    protected $connection = 'mysql_admin';

    private string $table = 'permisos';
    private string $uniqueIndex = 'permisos_clave_unique';

    public function up(): void
    {
        // Si no existe la tabla base, no hacemos nada (la crea su propia migración)
        if (!Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        // 1) Agregar columnas si faltan (idempotente)
        if (!Schema::connection($this->connection)->hasColumn($this->table, 'label')) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->string('label', 191)->nullable()->after('grupo');
            });
        }

        if (!Schema::connection($this->connection)->hasColumn($this->table, 'activo')) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('label');
            });
        }

        // 2) Asegurar índice único en 'clave' SOLO si no existe
        $db = DB::connection($this->connection);
        if (!$this->indexExists($db, $this->table, $this->uniqueIndex)) {
            // Asegura que exista la columna 'clave'
            if (!Schema::connection($this->connection)->hasColumn($this->table, 'clave')) {
                Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                    $table->string('clave', 191)->nullable(false);
                });
            }

            // Si tuvieras posibles duplicados de 'clave', aquí podrías normalizarlos antes.
            // En tu caso ya existe el índice desde migraciones anteriores, así que no lo recreamos.

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

        // Quitar índice único si existe (opcional)
        $db = DB::connection($this->connection);
        if ($this->indexExists($db, $this->table, $this->uniqueIndex)) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->dropUnique($this->uniqueIndex);
            });
        }

        // Eliminar columnas si existen (opcional y seguro)
        if (Schema::connection($this->connection)->hasColumn($this->table, 'activo')) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->dropColumn('activo');
            });
        }
        if (Schema::connection($this->connection)->hasColumn($this->table, 'label')) {
            Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
                $table->dropColumn('label');
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
        // Si cambias a otro motor en el futuro, ajusta esta verificación.
    }
};
