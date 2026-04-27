<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    private string $tableName = 'cfdis';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->tableName)) {
            return;
        }

        if (! Schema::connection($this->connection)->hasColumn($this->tableName, 'cuenta_id')) {
            DB::connection($this->connection)->statement("
                ALTER TABLE {$this->tableName}
                ADD cuenta_id VARCHAR(64) NULL AFTER cliente_id
            ");
        }

        try {
            DB::connection($this->connection)->statement("
                CREATE INDEX cfdis_cuenta_id_index
                ON {$this->tableName} (cuenta_id)
            ");
        } catch (\Throwable $e) {
            // El índice ya existe o la BD no permite duplicarlo.
        }
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->tableName)) {
            return;
        }

        if (Schema::connection($this->connection)->hasColumn($this->tableName, 'cuenta_id')) {
            DB::connection($this->connection)->statement("
                ALTER TABLE {$this->tableName}
                DROP COLUMN cuenta_id
            ");
        }
    }
};