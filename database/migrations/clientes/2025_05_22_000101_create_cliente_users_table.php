<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::connection('mysql_clientes')->hasTable('users_cliente')) return;

        Schema::connection('mysql_clientes')->create('users_cliente', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_usuario', 32)->unique(); // mismo que admin
            $table->unsignedBigInteger('account_admin_id')->index(); // FK lógica al admin.accounts.id
            $table->string('name', 160);
            $table->string('email', 160)->index();
            $table->string('password');                      // espejo (hash compatible)
            $table->string('rfc', 13)->nullable();
            $table->enum('rol', ['owner','admin','usuario'])->default('usuario'); // en ambiente cliente
            $table->string('foto_path')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::connection('mysql_clientes')->dropIfExists('users_cliente');
    }
};
