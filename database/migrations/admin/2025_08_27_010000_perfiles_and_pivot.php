<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    // perfiles
    if (!Schema::hasTable('perfiles')) {
      Schema::create('perfiles', function (Blueprint $t) {
        $t->id();
        $t->string('nombre', 120)->unique();
        $t->boolean('activo')->default(true)->index();
        $t->text('notas')->nullable();
        $t->timestamps();
      });
    }

    // pivot perfil_permiso
    if (!Schema::hasTable('perfil_permiso')) {
      Schema::create('perfil_permiso', function (Blueprint $t) {
        $t->unsignedBigInteger('perfil_id');
        $t->unsignedBigInteger('permiso_id');
        $t->primary(['perfil_id','permiso_id']);
        $t->index('perfil_idx', 'perfil_idx');
        $t->index('permiso_idx', 'permiso_idx');
        // FKs opcionales (si prefieres sin FK, comenta las dos líneas siguientes)
        // $t->foreign('perfil_id')->references('id')->on('perfiles')->onDelete('cascade');
        // $t->foreign('permiso_id')->references('id')->on('permisos')->onDelete('cascade');
      });
    }

    // columna perfil_id en usuario_administrativos
    if (Schema::hasTable('usuario_administrativos') && !Schema::hasColumn('usuario_administrativos','perfil_id')) {
      Schema::table('usuario_administrativos', function (Blueprint $t) {
        $t->unsignedBigInteger('perfil_id')->nullable()->after('rol');
        $t->index('perfil_id');
      });
    }
  }

  public function down(): void {
    if (Schema::hasTable('perfil_permiso')) Schema::drop('perfil_permiso');
    if (Schema::hasTable('perfiles')) Schema::drop('perfiles');
    if (Schema::hasTable('usuario_administrativos') && Schema::hasColumn('usuario_administrativos','perfil_id')) {
      Schema::table('usuario_administrativos', function (Blueprint $t) { $t->dropColumn('perfil_id'); });
    }
  }
};
