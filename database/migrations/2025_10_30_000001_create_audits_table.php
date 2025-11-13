<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Usamos la conexión ADMIN explícitamente
        if (Schema::connection('mysql_admin')->hasTable('audits')) {
            // Ya existe => nada que hacer (evita el 1050)
            return;
        }

        Schema::connection('mysql_admin')->create('audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->uuid('usuario_id')->nullable()->index();
            $table->string('event', 80)->index();
            $table->string('rfc', 20)->nullable()->index();
            $table->string('razon_social', 200)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('plan', 20)->nullable();
            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // 🔒 Dejamos SIN FK hasta que confirmemos la existencia/ nombre de 'accounts'
            // $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Elimina solo si existe, para evitar errores en rollbacks parciales
        Schema::connection('mysql_admin')->dropIfExists('audits');
    }

};
