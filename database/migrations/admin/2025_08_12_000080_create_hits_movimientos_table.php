<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('hits_movimientos', function (Blueprint $table) {
            $table->id();
            $table->uuid('cuenta_id');
            $table->enum('tipo', ['compra', 'consumo', 'ajuste']);
            $table->integer('cantidad');
            $table->integer('saldo_despues');
            $table->string('referencia')->nullable();
            $table->timestamps();

            $table->foreign('cuenta_id')->references('id')->on('cuentas')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('hits_movimientos');
    }
};
