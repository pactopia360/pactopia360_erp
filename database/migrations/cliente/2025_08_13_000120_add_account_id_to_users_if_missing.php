<?php
// database/migrations/clientes/2025_08_13_000120_add_account_id_to_users_if_missing.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_clientes';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable('users')) return;

        if (!Schema::connection($this->conn)->hasColumn('users', 'account_id')) {
            Schema::connection($this->conn)->table('users', function (Blueprint $t) {
                $t->unsignedBigInteger('account_id')->nullable()->after('email');
                $t->index('account_id', 'users_account_id_idx');
                // No forzamos FK aquí para no romper clones; puedes agregarla luego:
                // $t->foreign('account_id')->references('id')->on('accounts');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::connection($this->conn)->hasTable('users')) return;

        if (Schema::connection($this->conn)->hasColumn('users', 'account_id')) {
            Schema::connection($this->conn)->table('users', function (Blueprint $t) {
                $t->dropIndex('users_account_id_idx');
                $t->dropColumn('account_id');
            });
        }
    }
};
