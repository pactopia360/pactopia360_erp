<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ...000004_create_suscripciones_table.php
return new class extends Migration {
    public function up(): void {
        Schema::create('suscripciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained('planes');
            $t->enum('ciclo', ['mensual','anual']);
            $t->date('inicio_periodo');
            $t->date('fin_periodo');
            $t->enum('estatus', ['activa','pendiente','vencida','cancelada'])->default('pendiente');
            $t->decimal('precio_lista', 10, 2);
            $t->decimal('descuento', 10, 2)->default(0);
            $t->decimal('impuestos', 10, 2)->default(0);
            $t->decimal('total', 10, 2);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('suscripciones'); }
};