<?php
// database/migrations/admin/2025_08_12_000002_create_account_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::connection('mysql_admin')->hasTable('account_users')) return;

        Schema::connection('mysql_admin')->create('account_users', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('account_id');
            $t->boolean('is_owner')->default(false); // primer registro = padre
            $t->string('nombre');
            $t->string('email')->index();
            $t->string('password');
            $t->string('codigo_usuario', 40)->unique(); // ver generador
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();

            $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $t->unique(['account_id','email']); // una cuenta puede tener muchos usuarios (hijos)
        });
    }
    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('account_users');
    }
};
