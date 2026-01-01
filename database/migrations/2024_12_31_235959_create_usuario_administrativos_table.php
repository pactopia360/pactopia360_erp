<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuario_administrativos')) {
            return;
        }

        Schema::create('usuario_administrativos', function (Blueprint $table) {
            // UUID (string) como en tu modelo
            $table->uuid('id')->primary();

            $table->string('nombre', 150);
            $table->string('email', 190)->unique();

            // Password hash (bcrypt/argon2)
            $table->string('password', 255);

            // Rol / flags básicos
            $table->string('rol', 60)->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('es_superadmin')->default(false);
            $table->boolean('force_password_change')->default(false);

            // Auditoría / login
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            // Campos opcionales (tu modelo los contempla)
            $table->string('codigo_usuario', 80)->nullable()->unique();
            $table->string('estatus', 30)->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('ultimo_login_at')->nullable();
            $table->string('ip_ultimo_login', 45)->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index(['activo']);
            $table->index(['rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_administrativos');
    }
};
