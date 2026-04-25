<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    private string $table = 'cfdis';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        if (! Schema::connection($this->connection)->hasColumn($this->table, 'uuid')) {
            return;
        }

        DB::connection($this->connection)->statement("
            ALTER TABLE {$this->table}
            MODIFY uuid VARCHAR(64) NOT NULL
        ");
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        if (! Schema::connection($this->connection)->hasColumn($this->table, 'uuid')) {
            return;
        }

        DB::connection($this->connection)->statement("
            ALTER TABLE {$this->table}
            MODIFY uuid VARCHAR(36) NOT NULL
        ");
    }
};