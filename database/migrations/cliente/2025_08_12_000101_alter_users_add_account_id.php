<?php
// database/migrations/clientes/2025_08_12_000101_alter_users_add_account_id.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $conn = 'mysql_clientes';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable('users')) return;

        Schema::connection($this->conn)->table('users', function (Blueprint $t) {
            if (!Schema::connection($this->conn)->hasColumn('users', 'account_id')) {
                $t->unsignedBigInteger('account_id')->nullable()->after('id')->index();
            }
            if (!Schema::connection($this->conn)->hasColumn('users', 'role')) {
                $t->string('role', 32)->default('owner')->after('password')->index();
            }
            if (!Schema::connection($this->conn)->hasColumn('users', 'email_verified')) {
                $t->boolean('email_verified')->default(false)->after('role');
            }
            if (!Schema::connection($this->conn)->hasColumn('users', 'codigo_usuario')) {
                $t->string('codigo_usuario', 64)->nullable()->after('email')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection($this->conn)->hasTable('users')) return;

        Schema::connection($this->conn)->table('users', function (Blueprint $t) {
            if (Schema::connection($this->conn)->hasColumn('users', 'codigo_usuario')) $t->dropColumn('codigo_usuario');
            if (Schema::connection($this->conn)->hasColumn('users', 'email_verified')) $t->dropColumn('email_verified');
            if (Schema::connection($this->conn)->hasColumn('users', 'role')) $t->dropColumn('role');
            if (Schema::connection($this->conn)->hasColumn('users', 'account_id')) $t->dropColumn('account_id');
        });
    }
};
