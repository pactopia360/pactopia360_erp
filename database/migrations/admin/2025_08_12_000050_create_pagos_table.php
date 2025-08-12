<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->uuid('cuenta_id');
            $table->unsignedBigInteger('suscripcion_id');
            $table->string('pasarela', 50); // stripe, conekta, manual
            $table->decimal('monto', 10, 2);
            $table->string('moneda', 10)->default('MXN');
            $table->enum('estatus', ['exitoso', 'fallido', 'revisar'])->default('revisar');
            $table->string('referencia_pasarela')->nullable();
            $table->string('comprobante_url')->nullable();
            $table->unsignedBigInteger('registrado_en_admin_por')->nullable();
            $table->timestamps();

            $table->foreign('cuenta_id')->references('id')->on('cuentas')->onDelete('cascade');
            $table->foreign('suscripcion_id')->references('id')->on('suscripciones')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('pagos');
    }
};
