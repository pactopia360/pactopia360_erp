<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * ADMIN NO usa `users`.
         * La tabla real de login admin es `usuario_administrativos`.
         *
         * Esta migración:
         * 1) Asegura usuario_administrativos
         * 2) Elimina pivot legacy perfil_usuario (si existe)
         * 3) Crea usuario_perfil (la que tu modelo usa en hasPerm())
         */

        // 1) Tabla base admins
        if (!Schema::hasTable('usuario_administrativos')) {
            Schema::create('usuario_administrativos', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('nombre', 150);
                $table->string('email', 190)->unique();
                $table->string('password', 255);

                $table->string('rol', 60)->nullable();
                $table->boolean('activo')->default(true);
                $table->boolean('es_superadmin')->default(false);
                $table->boolean('force_password_change')->default(false);

                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();

                $table->string('codigo_usuario', 80)->nullable()->unique();
                $table->string('estatus', 30)->nullable();
                $table->boolean('is_blocked')->default(false);
                $table->timestamp('ultimo_login_at')->nullable();
                $table->string('ip_ultimo_login', 45)->nullable();

                $table->timestamps();
            });
        }

        // 2) Legacy
        if (Schema::hasTable('perfil_usuario')) {
            Schema::drop('perfil_usuario');
        }

        // 3) Pivot correcta
        if (Schema::hasTable('usuario_perfil')) {
            return;
        }

        Schema::create('usuario_perfil', function (Blueprint $table) {
            $table->id();
            $table->uuid('usuario_id');
            $table->unsignedBigInteger('perfil_id');
            $table->timestamps();

            $table->unique(['usuario_id', 'perfil_id'], 'uq_usuario_perfil');

            $table->foreign('usuario_id')
                ->references('id')
                ->on('usuario_administrativos')
                ->onDelete('cascade');

            $table->foreign('perfil_id')
                ->references('id')
                ->on('perfiles')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_perfil');
    }
};
