<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('usuarios_cuenta', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cuenta_id');
            $table->enum('tipo', ['padre', 'hijo'])->default('padre');
            $table->string('nombre', 150);
            $table->string('email')->index();
            $table->string('password');
            $table->enum('rol', ['owner', 'admin', 'operador'])->default('owner');
            $table->json('permisos_json')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_login_at')->nullable();
            $table->string('ip_ultimo_login', 45)->nullable();
            $table->bigInteger('sync_version')->default(1);
            $table->timestamps();

            $table->foreign('cuenta_id')->references('id')->on('cuentas')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('usuarios_cuenta');
    }
};
