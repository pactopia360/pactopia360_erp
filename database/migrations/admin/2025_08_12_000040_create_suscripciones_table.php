<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('cuenta_id');
            $table->unsignedBigInteger('plan_id');
            $table->enum('modo', ['mensual', 'anual']);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->enum('estatus', ['activa', 'pendiente', 'cancelada'])->default('pendiente');
            $table->decimal('precio_base', 10, 2);
            $table->decimal('descuento_aplicado', 10, 2)->default(0);
            $table->decimal('precio_neto', 10, 2);
            $table->string('origen', 50)->nullable();
            $table->bigInteger('sync_version')->default(1);
            $table->timestamps();

            $table->foreign('cuenta_id')->references('id')->on('cuentas')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('planes')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('suscripciones');
    }
};
