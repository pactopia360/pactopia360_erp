<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $conn = 'mysql_clientes';
    private string $table = 'usuarios_cuenta';

    public function up(): void
    {
        Schema::connection($this->conn)->table($this->table, function (Blueprint $t) {
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'password_temp')) {
                $t->string('password_temp', 255)->nullable()->after('password');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'password_plain')) {
                $t->string('password_plain', 255)->nullable()->after('password_temp');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'must_change_password')) {
                $t->boolean('must_change_password')->nullable()->default(true)->after('password_plain');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'remember_token')) {
                $t->rememberToken()->nullable();
            }
        });
    }

    public function down(): void
    {
        // No bajamos columnas para no romper compatibilidad.
    }
};
