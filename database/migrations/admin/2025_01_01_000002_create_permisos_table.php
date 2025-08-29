<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('permisos', function (Blueprint $t) {
      $t->id();
      $t->string('clave')->unique();     // p.ej: usuarios.view
      $t->string('grupo')->index();      // p.ej: usuarios
      $t->string('label');               // p.ej: Ver usuarios
      $t->text('descripcion')->nullable();
      $t->boolean('activo')->default(true);
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('permisos'); }
};
