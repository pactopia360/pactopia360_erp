<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('modulos_por_cuenta', function (Blueprint $table) {
            $table->id();
            $table->uuid('cuenta_id');
            $table->unsignedBigInteger('modulo_id');
            $table->timestamps();

            $table->foreign('cuenta_id')->references('id')->on('cuentas')->onDelete('cascade');
            $table->foreign('modulo_id')->references('id')->on('modulos')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('modulos_por_cuenta');
    }
};
