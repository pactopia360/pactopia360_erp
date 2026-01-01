<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysql_clientes')->hasTable('usuarios_cuenta')) {
            return;
        }

        Schema::connection('mysql_clientes')->create('usuarios_cuenta', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('cuenta_id')->nullable()->index();

            $table->string('tipo', 50)->nullable()->index();
            $table->string('rol', 50)->nullable()->index();
            $table->string('nombre', 180)->nullable();
            $table->string('email', 191)->nullable();

            $table->string('password')->nullable();
            $table->string('password_temp')->nullable();
            $table->boolean('must_change_password')->default(false)->index();
            $table->boolean('activo')->default(true)->index();

            $table->rememberToken();
            $table->timestamps();

            $table->unique('email', 'uq_usuarios_cuenta_email');
            $table->index(['cuenta_id', 'activo'], 'ix_uc_cuenta_activo');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('usuarios_cuenta');
    }
};
