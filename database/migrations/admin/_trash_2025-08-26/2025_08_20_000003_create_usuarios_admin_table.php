<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('usuarios_admin', function (Blueprint $table) {
            $table->id();
            $table->uuid('cuenta_id');
            $table->string('nombre');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('rol')->default('admin'); // superadmin, admin, soporte
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_login')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cuenta_id')->references('id')->on('cuentas');
        });
    }

    public function down(): void {
        Schema::dropIfExists('usuarios_admin');
    }
};
