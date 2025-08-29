<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // permisos (si no existiera)
        if (!Schema::hasTable('permisos')) {
            Schema::create('permisos', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 128)->unique();
                $table->string('grupo', 64)->default('')->index();
                $table->string('label', 191)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('permisos', function (Blueprint $table) {
                if (!Schema::hasColumn('permisos','label'))  $table->string('label',191)->nullable();
                if (!Schema::hasColumn('permisos','activo')) $table->boolean('activo')->default(true);
                // unique clave
                try { $table->unique('clave'); } catch (\Throwable $e) {}
            });
        }

        // perfiles
        if (!Schema::hasTable('perfiles')) {
            Schema::create('perfiles', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 128)->unique();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        // pivot perfil_permiso
        if (!Schema::hasTable('perfil_permiso')) {
            Schema::create('perfil_permiso', function (Blueprint $table) {
                $table->unsignedBigInteger('perfil_id');
                $table->unsignedBigInteger('permiso_id');
                $table->primary(['perfil_id','permiso_id']);

                $table->foreign('perfil_id')->references('id')->on('perfiles')->cascadeOnDelete();
                $table->foreign('permiso_id')->references('id')->on('permisos')->cascadeOnDelete();
            });
        }

        // pivot usuario_permiso (opcional, por-usuario)
        if (!Schema::hasTable('usuario_permiso')) {
            Schema::create('usuario_permiso', function (Blueprint $table) {
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedBigInteger('permiso_id');
                $table->primary(['usuario_id','permiso_id']);
                // Ajusta el nombre de la tabla de admins si difiere
                $table->foreign('usuario_id')->references('id')->on('usuario_administrativos')->cascadeOnDelete();
                $table->foreign('permiso_id')->references('id')->on('permisos')->cascadeOnDelete();
            });
        }

        // Columna perfil_id en usuario_administrativos (si no existe)
        if (Schema::hasTable('usuario_administrativos') && !Schema::hasColumn('usuario_administrativos','perfil_id')) {
            Schema::table('usuario_administrativos', function (Blueprint $table) {
                $table->unsignedBigInteger('perfil_id')->nullable()->after('rol');
                $table->foreign('perfil_id')->references('id')->on('perfiles')->nullOnDelete();
            });
        }
    }

    public function down(): void {
        // No bajamos nada para no romper producción; si quisieras:
        // Schema::dropIfExists('usuario_permiso');
        // Schema::dropIfExists('perfil_permiso');
        // Schema::dropIfExists('perfiles');
        // (y quitar columnas en usuario_administrativos…)
    }
};
