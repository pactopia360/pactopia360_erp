<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/2025_05_23_000003_create_cuentas_table.php
return new class extends Migration {
    public function up(): void {
        Schema::create('cuentas', function (Blueprint $t) {
            $t->id();
            $t->string('rfc_padre', 20)->unique();
            $t->string('razon_social')->nullable();
            $t->string('codigo_cliente', 64)->unique();
            $t->enum('plan_actual', ['free','premium'])->default('free');
            $t->enum('ciclo', ['mensual','anual'])->nullable();
            $t->enum('estatus_cuenta', ['activa','pendiente_pago','bloqueada','suspendida'])->default('activa');
            $t->unsignedSmallInteger('espacio_gb')->default(1);
            $t->unsignedInteger('hits_incluidos')->default(20);
            $t->unsignedInteger('hits_consumidos')->default(0);
            $t->date('fecha_alta');
            $t->date('fecha_ultimo_pago')->nullable();
            $t->date('fecha_vencimiento')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cuentas'); }
};
