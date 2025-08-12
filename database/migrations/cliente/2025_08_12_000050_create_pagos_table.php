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
            $table->decimal('monto', 10, 2);
            $table->string('moneda', 10)->default('MXN');
            $table->enum('estatus', ['exitoso', 'fallido', 'revisar'])->default('revisar');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('pagos');
    }
};
