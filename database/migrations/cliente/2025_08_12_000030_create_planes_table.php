<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->decimal('precio_mensual', 10, 2)->nullable();
            $table->decimal('precio_anual', 10, 2)->nullable();
            $table->integer('espacio_mb')->default(1024);
            $table->integer('hits_iniciales')->default(20);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('planes');
    }
};
