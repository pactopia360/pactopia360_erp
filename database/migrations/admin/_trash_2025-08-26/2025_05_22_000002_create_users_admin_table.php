<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::connection('mysql_admin')->hasTable('users_admin')) return;

        Schema::connection('mysql_admin')->create('users_admin', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->nullable()->index(); // null = staff interno (ERP Admin)
            $table->string('codigo_usuario', 32)->unique();   // legible + no repetible (helper)
            $table->string('name', 160);
            $table->string('email', 160)->unique();
            $table->string('password');
            $table->string('rfc', 13)->nullable()->index();   // puede repetirse en hijos de la misma cuenta
            $table->enum('tipo', ['superadmin','admin','soporte','ventas','dev','cliente'])->default('cliente');
            $table->json('permisos')->nullable();             // matriz por módulo
            $table->string('foto_path')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('users_admin');
    }
};
