<?php
// database/migrations/clientes/2025_08_12_000101_create_account_users_mirror_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::connection('mysql_clientes')->hasTable('account_users')) return;

        Schema::connection('mysql_clientes')->create('account_users', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('account_id'); // id espejo de admin.accounts
            $t->boolean('is_owner')->default(false);
            $t->string('nombre');
            $t->string('email')->index();
            $t->string('password');
            $t->string('codigo_usuario', 40)->unique();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['account_id','email']);
        });
    }
    public function down(): void {
        Schema::connection('mysql_clientes')->dropIfExists('account_users');
    }
};
