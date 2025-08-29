<?php
// database/migrations/clientes/2025_08_13_000020_alter_users_add_account_id_and_admin_fields.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $conn = 'mysql_clientes';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable('users')) return;

        Schema::connection($this->conn)->table('users', function (Blueprint $t) {
            if (!Schema::connection($this->conn)->hasColumn('users','account_id')) {
                $t->unsignedBigInteger('account_id')->nullable()->after('id')->index();
            }
            if (!Schema::connection($this->conn)->hasColumn('users','role')) {
                $t->string('role', 30)->default('owner')->after('password')->index();
            }
            if (!Schema::connection($this->conn)->hasColumn('users','email_verified')) {
                $t->boolean('email_verified')->default(false)->after('remember_token')->index();
            }
            if (!Schema::connection($this->conn)->hasColumn('users','customer_code')) {
                $t->string('customer_code', 64)->nullable()->unique()->after('email');
            }
            if (!Schema::connection($this->conn)->hasColumn('users','rfc')) {
                $t->string('rfc', 13)->nullable()->after('name')->index();
            }
        });
    }

    public function down(): void
    {
        // No eliminamos columnas en down por seguridad.
    }
};
