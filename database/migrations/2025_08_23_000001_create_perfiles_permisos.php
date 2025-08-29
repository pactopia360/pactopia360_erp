<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('perfiles')) {
            Schema::create('perfiles', function (Blueprint $t) {
                $t->id();
                $t->string('clave')->unique();
                $t->string('nombre')->nullable();
                $t->string('descripcion')->nullable();
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('permisos')) {
            Schema::create('permisos', function (Blueprint $t) {
                $t->id();
                $t->string('clave')->unique();
                $t->string('nombre')->nullable();
                $t->string('descripcion')->nullable();
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('perfil_permiso')) {
            Schema::create('perfil_permiso', function (Blueprint $t) {
                $t->unsignedBigInteger('perfil_id');
                $t->unsignedBigInteger('permiso_id');
                $t->timestamps();
                $t->primary(['perfil_id','permiso_id']);
            });
        }
        if (!Schema::hasTable('usuario_perfil')) {
            Schema::create('usuario_perfil', function (Blueprint $t) {
                $t->unsignedBigInteger('usuario_id');
                $t->unsignedBigInteger('perfil_id');
                $t->string('asignado_por')->nullable();
                $t->timestamps();
                $t->primary(['usuario_id','perfil_id']);
            });
        }
    }

    public function down(): void
    {
        // no hacemos drop para no perder datos
    }
};
