<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $connection = 'mysql_clientes';
    private string $table = 'sat_user_metadata_items';
    private string $index = 'sat_meta_unique_owner_direction_uuid';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        if ($this->indexExists()) {
            return;
        }

        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $table->unique(
                ['cuenta_id', 'usuario_id', 'rfc_owner', 'direction', 'uuid'],
                'sat_meta_unique_owner_direction_uuid'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable($this->table)) {
            return;
        }

        if (!$this->indexExists()) {
            return;
        }

        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $table->dropUnique('sat_meta_unique_owner_direction_uuid');
        });
    }

    private function indexExists(): bool
    {
        try {
            $database = DB::connection($this->connection)->getDatabaseName();

            $row = DB::connection($this->connection)
                ->table('information_schema.statistics')
                ->where('table_schema', $database)
                ->where('table_name', $this->table)
                ->where('index_name', $this->index)
                ->first();

            return $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }
};