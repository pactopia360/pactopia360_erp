<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('usuarios_cuenta')
            && Schema::connection($this->connection)->hasColumn('usuarios_cuenta', 'must_change_password')) {
            DB::connection($this->connection)
                ->statement("ALTER TABLE usuarios_cuenta 
                             MODIFY must_change_password TINYINT(1) NOT NULL DEFAULT 1");
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('usuarios_cuenta')
            && Schema::connection($this->connection)->hasColumn('usuarios_cuenta', 'must_change_password')) {
            DB::connection($this->connection)
                ->statement("ALTER TABLE usuarios_cuenta 
                             MODIFY must_change_password TINYINT(1) NOT NULL DEFAULT 0");
        }
    }
};
