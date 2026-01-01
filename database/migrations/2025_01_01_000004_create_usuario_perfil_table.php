<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Asegura tabla base de admins
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

        // 2) Elimina pivot legacy si existe (si venías de otra versión)
        if (Schema::hasTable('perfil_usuario')) {
            Schema::drop('perfil_usuario');
        }

        // 3) Pivot correcta que tu modelo realmente usa: usuario_perfil
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
