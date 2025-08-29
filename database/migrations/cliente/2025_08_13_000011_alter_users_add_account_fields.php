<?php
// database/migrations/clientes/2025_08_13_000011_alter_users_add_account_fields.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $conn = 'mysql_clientes';
    private string $table = 'users';

    public function up(): void
    {
        // Si no existe la tabla users en clientes, la creamos mínima
        if (!Schema::connection($this->conn)->hasTable($this->table)) {
            Schema::connection($this->conn)->create($this->table, function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('account_id')->index(); // espejo con admin.accounts
                $t->string('name');
                $t->string('email')->unique();
                $t->string('password');
                $t->string('role', 40)->default('owner')->index(); // owner|admin|user
                $t->boolean('email_verified')->default(false);
                $t->rememberToken();
                $t->timestamps();
                $t->softDeletes();
            });
            return;
        }

        Schema::connection($this->conn)->table($this->table, function (Blueprint $t) {
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'account_id')) {
                $t->unsignedBigInteger('account_id')->index()->after('id');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'role')) {
                $t->string('role', 40)->default('owner')->index()->after('password');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'email_verified')) {
                $t->boolean('email_verified')->default(false)->after('role');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'deleted_at')) {
                $t->softDeletes();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection($this->conn)->hasTable($this->table)) return;

        Schema::connection($this->conn)->table($this->table, function (Blueprint $t) {
            foreach (['account_id','role','email_verified'] as $col) {
                if (Schema::connection($this->conn)->hasColumn($this->table, $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
