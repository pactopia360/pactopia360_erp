<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('usuarios_clientes', function (Blueprint $table) {
            $table->id();
            $table->uuid('cliente_id');
            $table->string('nombre');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('codigo_usuario')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_login')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cliente_id')->references('id')->on('clientes');
        });
    }

    public function down(): void {
        Schema::dropIfExists('usuarios_clientes');
    }
};
