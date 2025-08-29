<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('perfil_permiso', function (Blueprint $t) {
      $t->id();
      $t->foreignId('perfil_id')->constrained('perfiles')->cascadeOnDelete();
      $t->foreignId('permiso_id')->constrained('permisos')->cascadeOnDelete();
      $t->timestamps();
      $t->unique(['perfil_id','permiso_id']);
    });
  }
  public function down(): void { Schema::dropIfExists('perfil_permiso'); }
};
