<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cuentas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo_cliente', 36)->unique();
            $table->string('customer_no', 20)->unique();
            $table->string('rfc_padre', 13)->unique();
            $table->string('razon_social', 255);
            $table->string('email_contacto')->index();
            $table->string('telefono', 20)->nullable();
            $table->enum('plan_actual', ['FREE', 'PREMIUM'])->default('FREE');
            $table->enum('modo_cobro', ['mensual', 'anual'])->nullable();
            $table->enum('estado_cuenta', ['activa', 'bloqueada', 'suspendida'])->default('activa');
            $table->boolean('email_verificado')->default(false);
            $table->integer('espacio_asignado_mb')->default(1024); // FREE 1GB
            $table->integer('hits_asignados')->default(20);
            $table->bigInteger('sync_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('cuentas');
    }
};
