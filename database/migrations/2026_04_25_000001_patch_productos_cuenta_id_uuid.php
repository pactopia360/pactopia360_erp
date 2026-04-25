<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    private string $tableName = 'productos';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->tableName)) {
            return;
        }

        if (! Schema::connection($this->connection)->hasColumn($this->tableName, 'cuenta_id')) {
            return;
        }

        DB::connection($this->connection)->statement("
            ALTER TABLE {$this->tableName}
            MODIFY cuenta_id VARCHAR(64) NOT NULL
        ");
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->tableName)) {
            return;
        }

        if (! Schema::connection($this->connection)->hasColumn($this->tableName, 'cuenta_id')) {
            return;
        }

        DB::connection($this->connection)->statement("
            ALTER TABLE {$this->tableName}
            MODIFY cuenta_id BIGINT UNSIGNED NOT NULL
        ");
    }
};