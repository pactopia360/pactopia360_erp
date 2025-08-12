<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_clientes')->create('cuentas_cliente', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo_cliente', 50)->unique();
            $table->string('customer_no', 50)->nullable();
            $table->string('rfc_padre', 20)->index();
            $table->string('razon_social')->nullable();
            $table->enum('plan_actual', ['FREE', 'PREMIUM'])->default('FREE');
            $table->enum('modo_cobro', ['mensual', 'anual'])->nullable();
            $table->enum('estado_cuenta', ['activa', 'bloqueada', 'pendiente'])->default('activa');
            $table->unsignedInteger('espacio_asignado_mb')->default(0);
            $table->unsignedInteger('hits_asignados')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('cuentas_cliente');
    }
};
