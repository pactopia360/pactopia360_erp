<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('perfiles', function (Blueprint $t) {
      $t->id();
      $t->string('clave')->unique();
      $t->string('nombre');
      $t->text('descripcion')->nullable();
      $t->boolean('activo')->default(true);
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('perfiles'); }
};
