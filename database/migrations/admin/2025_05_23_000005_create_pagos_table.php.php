<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ...000005_create_pagos_table.php
return new class extends Migration {
    public function up(): void {
        Schema::create('pagos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $t->foreignId('suscripcion_id')->nullable()->constrained('suscripciones')->nullOnDelete();
            $t->enum('metodo', ['stripe','transferencia']);
            $t->string('referencia', 80)->nullable();
            $t->decimal('monto', 10, 2);
            $t->string('moneda', 8)->default('MXN');
            $t->enum('estatus', ['pendiente','pagado','fallido','reembolsado'])->default('pendiente');
            $t->date('fecha_pago')->nullable();
            $t->string('factura_uuid', 40)->nullable();
            $t->string('recibo_url')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('pagos'); }
};